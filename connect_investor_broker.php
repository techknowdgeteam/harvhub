<?php
//connect_investor_broker.php
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

// ==================== FETCH USER DATA ====================
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
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
$current_broker = $user['broker'] ?? '';
$current_server = $user['server'] ?? '';
$current_login = $user['login'] ?? '';
$fullname = $user['fullname'] ?? '';
$application_status = $user['application_status'] ?? '';

$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$broker_connected = !empty($current_broker) && !empty($current_server) && !empty($current_login);

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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<title>Connect Broker - HarvHub</title>
<?php include 'style.php'; ?>
<style>
    /* Additional styles for the connect broker page */
    .debug-console {
        background: #1e1e1e;
        color: #00ff00;
        font-family: monospace;
        font-size: 11px;
        padding: 10px;
        margin: 10px 0;
        border-radius: 4px;
        max-height: 150px;
        overflow-y: auto;
        display: none;
        white-space: pre-wrap;
        word-break: break-all;
        border: 1px solid #333;
    }
    .debug-console.active {
        display: block;
    }
    .debug-console .error {
        color: #ff4444;
    }
    .debug-console .success {
        color: #44ff44;
    }
    .debug-console .info {
        color: #44aaff;
    }
    /* ============================================================
    GLOBAL iOS ZOOM FIX
    iOS Safari auto-zooms any input with font-size < 16px.
    Force 16px on all form controls at mobile widths.
    ============================================================ */
    @media (max-width: 768px) {
        input,
        select,
        textarea,
        .dd-input,
        .dd-select,
        .dd-am-input,
        .dd-inline-input,
        .dd-req-input,
        .dd-json-edit-textarea,
        .pt-modal-input {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body>

<div class="connect-broker-container">
    <div class="connect-broker-card">
        
        <!-- Back to Dashboard - TOP -->
        <div class="back-link-top">
            <a href="#" onclick="event.preventDefault(); if (window.navigateTo) { window.navigateTo('mydashboard'); } else { window.location.href = 'app.php#mydashboard'; }">
                ← Back to Dashboard
            </a>
        </div>
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

        <!-- Error Display for AJAX -->
        <div id="brokerError" class="error-message" style="display: none;"></div>
        <div id="brokerSuccess" class="success-message" style="display: none;"></div>
        
        <!-- Debug Console -->
        <div id="debugConsole" class="debug-console"></div>

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
            
            <!-- Broker Selection Grid -->
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

            <!-- Connect Button -->
            <div id="connectButtonWrapper" class="btn-connect-wrapper">
                <button type="button" class="btn-secondary" onclick="showConnectForm()" style="padding: 12px 32px; font-size: 0.95rem;">
                    I Already Have an Account
                </button>
            </div>

            <!-- Connect Form - Hidden initially -->
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

<script>
    // Password toggle function
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

    // Auto-show form if no links
    <?php if (!$has_links && !$broker_connected): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showConnectForm();
        });
    <?php endif; ?>
    
    // Make functions globally accessible
    window.togglePasswordField = togglePasswordField;
    window.showConnectForm = showConnectForm;
</script>

</body>
</html>