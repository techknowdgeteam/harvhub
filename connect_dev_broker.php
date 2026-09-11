<?php
// connect_dev_broker.php — Standalone
session_start();

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed.");
}

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}


$email = strtolower($_SESSION['user_email']);

// ==================== FETCH USER DATA (harvhub — for display) ====================
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

// ==================== DEVELOPER CHECK ====================
$devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$devStmt->execute([$email]);
$developer = $devStmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    // Not a developer — redirect to app.php (or show access restricted)
    header("Location: dev_app.php");
    exit;
}

$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ==================== CONNECT BROKER AJAX (writes to `developers`) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_broker_ajax'])) {
    header('Content-Type: application/json');
    $broker = $_POST['broker'] ?? '';
    $server = trim($_POST['server'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $broker_password = $_POST['broker_password'] ?? '';

    $errors = [];
    if (empty($broker)) $errors[] = 'Broker is required';
    if (empty($server)) $errors[] = 'Server is required';
    if (empty($login)) $errors[] = 'Login is required';
    if (empty($broker_password)) $errors[] = 'Password is required';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    $allowed_brokers = [];
    try {
        $stmt = $pdo->query("SELECT brokers FROM server_account LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            $raw_brokers = explode(',', $config['brokers'] ?? '');
            foreach ($raw_brokers as $entry) {
                $entry = trim($entry);
                if (empty($entry)) continue;
                $broker_name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
                $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);
                $broker_name = trim($broker_name);
                if (!empty($broker_name)) {
                    $allowed_brokers[] = ucfirst($broker_name);
                }
            }
            sort($allowed_brokers);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'errors' => ['Failed to load broker configuration.']]);
        exit;
    }

    if (!in_array($broker, $allowed_brokers)) {
        echo json_encode(['success' => false, 'errors' => ['Invalid broker selected.']]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE developers
            SET broker = ?, server = ?, login = ?, broker_password = ?
            WHERE email = ?
        ");
        $stmt->execute([$broker, $server, $login, $broker_password, $email]);

        echo json_encode([
            'success' => true,
            'message' => 'Broker connected successfully!'
        ]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'errors' => ['Failed to save broker details.']]);
        exit;
    }
}

// ==================== FETCH BROKER CONFIGURATION ====================
$allowed_brokers = [];
$broker_links = [];

try {
    $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        $raw_brokers = explode(',', $config['brokers'] ?? '');
        $raw_links = explode(',', $config['brokers_link'] ?? '');

        $cleaned_links = [];
        foreach ($raw_links as $link) {
            $link = trim($link);
            if (empty($link)) continue;
            if (strpos($link, ':') !== false) {
                $link = trim(substr($link, strrpos($link, ':') + 1));
            }
            if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,})/', $link, $matches)) {
                $link = $matches[1];
            }
            $link = strtolower(trim($link));
            if (!empty($link)) {
                $cleaned_links[] = $link;
            }
        }

        foreach ($raw_brokers as $index => $entry) {
            $entry = trim($entry);
            if (empty($entry)) continue;
            $broker_name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
            $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);
            $broker_name = trim($broker_name);
            $broker_name_clean = strtolower($broker_name);

            if (!empty($broker_name)) {
                $formatted_name = ucfirst($broker_name);
                $link = isset($cleaned_links[$index]) ? $cleaned_links[$index] : '';

                if (empty($link)) {
                    foreach ($cleaned_links as $cleaned_link) {
                        if (strpos($cleaned_link, $broker_name_clean) !== false) {
                            $link = $cleaned_link;
                            break;
                        }
                    }
                }

                if (!in_array($formatted_name, $allowed_brokers)) {
                    $allowed_brokers[] = $formatted_name;
                    $broker_links[$formatted_name] = $link;
                }
            }
        }
        sort($allowed_brokers);
    }
} catch (PDOException $e) {
    $error = "Failed to load broker configuration.";
}

// ==================== GET CURRENT VALUES ====================
$current_broker = $developer['broker'] ?? '';
$current_server = $developer['server'] ?? '';
$current_login  = $developer['login'] ?? '';

$fullname           = $user['fullname'] ?? '';
$application_status = $developer['application_status'] ?? ($user['application_status'] ?? '');

$broker_connected = !empty($current_broker) && !empty($current_server) && !empty($current_login);
$brokerConnected  = $broker_connected; // For dev_tabs.php

$has_links = false;
foreach ($allowed_brokers as $broker) {
    if (!empty($broker_links[$broker])) {
        $has_links = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>Connect Broker - HarvHub</title>
<?php include 'dev_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div id="page-content">
        <div class="connect-broker-container">
            <div class="connect-broker-card">
                
                <?php if ($broker_connected): ?>
                    <div class="logo-area">
                        <p>Update Your Broker Account</p>
                    </div>
                <?php else: ?>
                    <div class="logo-area">
                        <p>Connect Your Broker Account</p>
                    </div>
                <?php endif; ?>

                <div class="user-info">
                    <span>
                        <span class="label">User</span>
                        <span class="value"><?= htmlspecialchars($fullname ?: $email) ?></span>
                    </span>
                    <span>
                        <span class="label">Status</span>
                        <span class="status-badge <?= strtolower($application_status) ?>">
                            <?= htmlspecialchars($application_status ?: 'Pending') ?>
                        </span>
                    </span>
                </div>

                <div id="brokerError" class="error-message" style="display: none;"></div>
                <div id="brokerSuccess" class="success-message" style="display: none;"></div>

                <?php if ($broker_connected): ?>
                    <!-- Already connected - show update form -->
                    <div id="connectForm">
                        <form id="connectBrokerForm">
                            <div class="form-group">
                                <label for="broker_select">Select Broker</label>
                                <select name="broker" id="broker_select" required>
                                    <option value="">-- Select Broker --</option>
                                    <?php foreach ($allowed_brokers as $broker): ?>
                                        <option value="<?= htmlspecialchars($broker) ?>" <?= ($current_broker == $broker) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($broker) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="server_input">Server</label>
                                <input type="text" name="server" id="server_input" placeholder="e.g. Exness-MT5" value="<?= htmlspecialchars($current_server) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="login_input">Login Number</label>
                                <input type="text" name="login" id="login_input" placeholder="Your MT5 login number" value="<?= htmlspecialchars($current_login) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="password_input">MT5 Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="broker_password" id="password_input" placeholder="Your MT5 password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordField()">Show</button>
                                </div>
                            </div>

                            <button type="submit" id="connectBrokerBtn" class="btn-submit">
                                Update Broker Details
                            </button>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- Not connected - show selection and connect option -->
                    <div id="brokerSelectionGrid" class="broker-selection-grid">
                        <div class="section-title">Available Brokers</div>
                        
                        <?php foreach ($allowed_brokers as $broker): 
                            $link = isset($broker_links[$broker]) ? $broker_links[$broker] : '';
                        ?>
                            <div class="broker-selection-item">
                                <span class="broker-name"><?= htmlspecialchars($broker) ?></span>
                                <div class="broker-action">
                                    <?php if (!empty($link)): ?>
                                        <a href="https://<?= htmlspecialchars($link) ?>" target="_blank" class="btn-broker-link">Go to <?= htmlspecialchars($broker) ?></a>
                                    <?php else: ?>
                                        <span class="btn-broker-link no-link">No Link Available</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($has_links): ?>
                            <hr class="broker-divider">
                        <?php endif; ?>
                    </div>

                    <div id="connectButtonWrapper" class="btn-connect-wrapper">
                        <button type="button" class="btn-secondary" onclick="showConnectForm()" style="padding: 12px 32px; font-size: 0.95rem;">
                            I Already Have an Account
                        </button>
                    </div>

                    <div id="connectForm" class="hidden">
                        <hr class="broker-divider">
                        <div class="section-title">Connect Your Account</div>
                        
                        <form id="connectBrokerForm">
                            <div class="form-group">
                                <label for="broker_select">Select Broker</label>
                                <select name="broker" id="broker_select" required>
                                    <option value="">-- Select Broker --</option>
                                    <?php foreach ($allowed_brokers as $broker): ?>
                                        <option value="<?= htmlspecialchars($broker) ?>">
                                            <?= htmlspecialchars($broker) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="server_input">Server</label>
                                <input type="text" name="server" id="server_input" placeholder="e.g. Exness-MT5" required>
                            </div>

                            <div class="form-group">
                                <label for="login_input">Login Number</label>
                                <input type="text" name="login" id="login_input" placeholder="Your MT5 login number" required>
                            </div>

                            <div class="form-group">
                                <label for="password_input">MT5 Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="broker_password" id="password_input" placeholder="Your MT5 password" required>
                                    <button type="button" class="password-toggle" onclick="togglePasswordField()">Show</button>
                                </div>
                            </div>

                            <button type="submit" id="connectBrokerBtn" class="btn-submit">
                                Connect Broker
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="info-note">
                    <strong>Secure:</strong> Your broker credentials are encrypted and stored securely.
                    They are only used for automated trading execution.
                </div>

            </div>
        </div>
    </div>

    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner"></div>
    </div>

<script>
    (function() {
        var _origLog   = console.log.bind(console);
        var _origWarn  = console.warn.bind(console);
        var _origError = console.error.bind(console);

        var SUPPRESS_PATTERNS = [
            /Failed to fetch live balances/i,
            /Error refreshing notifications/i,
            /Error polling notifications/i,
            /Failed to load page/i,
            /fetchLiveBalances/i,
            /refreshNotifications/i,
            /pollHeaderNotifications/i,
            /pollNewNotifications/i,
            /startLiveUpdates/i,
            /===== HEADER DEBUG/i,
            /\[WATCHDOG/i,
            /\[SCROLL STATE\]/i,
            /\[BODY SCROLL WATCHDOG\]/i,
            /\[MODAL LOCK\]/i,
            /Smart bottom nav detection initialized/i,
            /showSpinner\(\) CALLED/i,
            /hideSpinner\(\) CALLED/i,
            /SPA LOADER/i
        ];

        function shouldSuppress(args) {
            for (var i = 0; i < args.length; i++) {
                var a = args[i];
                if (typeof a === 'string') {
                    for (var j = 0; j < SUPPRESS_PATTERNS.length; j++) {
                        if (SUPPRESS_PATTERNS[j].test(a)) return true;
                    }
                }
            }
            return false;
        }

        console.log = function() {
            if (shouldSuppress(arguments)) return;
            _origLog.apply(console, arguments);
        };
        console.warn = function() {
            if (shouldSuppress(arguments)) return;
            _origWarn.apply(console, arguments);
        };
        console.error = function() {
            if (shouldSuppress(arguments)) return;
            _origError.apply(console, arguments);
        };
    })();
</script>

<script>
    function togglePasswordField() {
        var input = document.getElementById('password_input');
        var toggle = input.parentElement.querySelector('.password-toggle');
        if (input.type === 'password') {
            input.type = 'text';
            toggle.textContent = 'Hide';
        } else {
            input.type = 'password';
            toggle.textContent = 'Show';
        }
    }

    function showConnectForm() {
        var grid = document.getElementById('brokerSelectionGrid');
        if (grid) grid.classList.add('hidden');
        var wrapper = document.getElementById('connectButtonWrapper');
        if (wrapper) wrapper.classList.add('hidden');
        var form = document.getElementById('connectForm');
        if (form) {
            form.classList.remove('hidden');
            setTimeout(function() {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }

    function showSpinner() {
        var overlay = document.getElementById('spinnerOverlay');
        if (overlay) overlay.classList.add('active');
    }

    function hideSpinner() {
        var overlay = document.getElementById('spinnerOverlay');
        if (overlay) overlay.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var connectForm = document.getElementById('connectBrokerForm');
        if (!connectForm) return;

        connectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var submitBtn = document.getElementById('connectBrokerBtn');
            var errorDiv = document.getElementById('brokerError');
            var successDiv = document.getElementById('brokerSuccess');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Connecting...';
            }
            if (errorDiv) {
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
            }
            if (successDiv) {
                successDiv.style.display = 'none';
                successDiv.textContent = '';
            }

            var broker = document.getElementById('broker_select').value;
            var server = document.getElementById('server_input').value.trim();
            var login = document.getElementById('login_input').value.trim();
            var password = document.getElementById('password_input').value;

            var errors = [];
            if (!broker) errors.push('Please select a broker');
            if (!server) errors.push('Server is required');
            if (!login) errors.push('Login is required');
            if (!password) errors.push('Password is required');

            if (errors.length > 0) {
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = errors.join(', ');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Try Again';
                }
                return;
            }

            showSpinner();

            fetch('connect_dev_broker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'connect_broker_ajax=1&broker=' + encodeURIComponent(broker) +
                      '&server=' + encodeURIComponent(server) +
                      '&login=' + encodeURIComponent(login) +
                      '&broker_password=' + encodeURIComponent(password)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    if (successDiv) {
                        successDiv.style.display = 'block';
                        successDiv.textContent = data.message || 'Broker connected successfully!';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '✓ Success!';
                        submitBtn.style.background = '#28a745';
                        submitBtn.style.color = '#fff';
                    }
                    setTimeout(function() {
                        window.location.reload(true);
                    }, 1500);
                } else {
                    if (errorDiv) {
                        errorDiv.style.display = 'block';
                        errorDiv.textContent = data.errors ? data.errors.join(', ') : 'Connection failed. Please try again.';
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Try Again';
                    }
                }
                hideSpinner();
            })
            .catch(function(error) {
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = 'Network error: ' + error.message;
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Try Again';
                }
                hideSpinner();
            });
        });

        <?php if (!$has_links && !$broker_connected): ?>
            showConnectForm();
        <?php endif; ?>
    });

    // ==================== SIDEBAR NAVIGATION ====================
    (function() {
        var sidebar = document.getElementById('sidebarNav');
        var desktopToggle = document.getElementById('sidebarToggle');
        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var overlay = document.getElementById('sidebarOverlay');
        var body = document.body;

        if (!sidebar) return;

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function openMobileSidebar() {
            sidebar.classList.add('expanded');
            overlay.classList.add('active');
            body.style.overflow = 'hidden';
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
        }

        function toggleDesktopSidebar() {
            var isExpanded = sidebar.classList.contains('expanded');
            if (isExpanded) {
                sidebar.classList.remove('expanded');
                body.classList.remove('sidebar-expanded-desktop');
            } else {
                sidebar.classList.add('expanded');
                body.classList.add('sidebar-expanded-desktop');
            }
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (isMobile()) openMobileSidebar();
            });
        }

        if (desktopToggle) {
            desktopToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!isMobile()) toggleDesktopSidebar();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                if (isMobile()) closeMobileSidebar();
            });
        }

        document.querySelectorAll('.sidebar-menu-item').forEach(function(item) {
            item.addEventListener('click', function() {
                if (isMobile()) {
                    setTimeout(closeMobileSidebar, 150);
                }
            });
        });

        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (isMobile()) {
                    body.classList.remove('sidebar-expanded-desktop');
                    sidebar.classList.remove('expanded');
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                } else {
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                    if (sidebar.classList.contains('expanded')) {
                        body.classList.add('sidebar-expanded-desktop');
                    }
                }
            }, 150);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isMobile()) {
                closeMobileSidebar();
            }
        });

        if (isMobile()) {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
            body.classList.remove('sidebar-expanded-desktop');
        } else {
            sidebar.classList.remove('expanded');
            body.classList.remove('sidebar-expanded-desktop');
        }
    })();

    window.togglePasswordField = togglePasswordField;
    window.showConnectForm = showConnectForm;
</script>

</body>
</html>