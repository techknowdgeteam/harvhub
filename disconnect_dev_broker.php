<?php
// disconnect_dev_broker.php — Standalone (No Verification Required)
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
$stmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$developer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header("Location: dev_app.php");
    exit;
}

// ==================== GET CURRENT VALUES ====================
$current_broker = $developer['broker'] ?? '';
$current_server = $developer['server'] ?? '';
$current_login  = $developer['login'] ?? '';

$fullname           = $user['fullname'] ?? '';
$application_status = $developer['application_status'] ?? ($user['application_status'] ?? '');

$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$broker_connected = !empty($current_broker) && !empty($current_server) && !empty($current_login);
$brokerConnected  = $broker_connected; // For dev_tabs.php

// ==================== HANDLE DISCONNECT (POST) ====================
$disconnect_success = false;
$disconnect_error   = '';
$disconnect_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_dev_broker'])) {

    // Re-fetch developer row for latest state
    $stmt = $pdo->prepare("SELECT * FROM developers WHERE email = ?");
    $stmt->execute([$email]);
    $currentDev = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentDev) {
        $disconnect_error = 'Developer record not found.';
    } else {
        try {
            // Clean disconnect — just clear broker fields
            $stmt = $pdo->prepare("
                UPDATE developers
                SET broker = NULL,
                    server = NULL,
                    login = NULL,
                    broker_password = NULL
                WHERE email = ?
            ");
            $stmt->execute([$email]);

            $disconnect_success = true;
            $disconnect_message = 'Broker disconnected successfully.';

        } catch (PDOException $e) {
            $disconnect_error = 'Failed to disconnect broker. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>Disconnect Broker - HarvHub</title>
<?php include 'dev_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div id="page-content">
        <div class="connect-broker-container">
            <div class="connect-broker-card">

                <div class="logo-area">
                    <p>Disconnect Your Broker Account</p>
                </div>

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

                <?php if (!$broker_connected && !$disconnect_success): ?>

                    <!-- STATE 1: No broker connected -->
                    <div class="error-message" style="display:block;">
                        No broker account is currently connected.
                    </div>
                    <div class="btn-connect-wrapper" style="margin-top:20px;">
                        <a href="connect_dev_broker.php" class="btn-secondary" style="text-decoration:none; display:inline-block; padding:12px 32px;">
                            Go to Connect Broker
                        </a>
                    </div>

                <?php elseif ($disconnect_success): ?>

                    <!-- STATE 2: Disconnect succeeded -->
                    <div class="success-message" style="display:block;">
                        <?= htmlspecialchars($disconnect_message) ?>
                    </div>

                    <div class="btn-connect-wrapper" style="margin-top:20px;">
                        <a href="connect_dev_broker.php" class="btn-submit" style="text-decoration:none; display:inline-block; padding:12px 32px;">
                            Return to Connect Broker
                        </a>
                    </div>

                <?php else: ?>

                    <!-- STATE 3: Broker connected — show current details + disconnect button -->
                    <?php if (!empty($disconnect_error)): ?>
                        <div class="error-message" style="display:block;">
                            <?= htmlspecialchars($disconnect_error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="section-title">Current Broker Details</div>
                    <div class="info-note" style="margin-bottom:18px;">
                        <div style="margin-bottom:6px;"><strong>Broker:</strong> <?= htmlspecialchars($current_broker) ?></div>
                        <div style="margin-bottom:6px;"><strong>Server:</strong> <?= htmlspecialchars($current_server) ?></div>
                        <div><strong>Login:</strong> <?= htmlspecialchars($current_login) ?></div>
                    </div>

                    <hr class="broker-divider">

                    <div class="section-title">Disconnect Broker</div>

                    <div class="info-note" style="margin-bottom:18px;">
                        <strong>Note:</strong> Disconnecting will remove your broker credentials from our servers.
                        You can reconnect at any time from the Connect Broker page.
                    </div>

                    <button type="button"
                            class="btn-submit"
                            style="background:#dc3545;"
                            onclick="openDisconnectConfirmModal()">
                        Disconnect Broker
                    </button>

                <?php endif; ?>

                <div class="info-note">
                    <strong>Secure:</strong> Your broker credentials are encrypted and stored securely.
                    Disconnecting will remove them from our servers.
                </div>

            </div>
        </div>
    </div>

    <?php if ($broker_connected && !$disconnect_success): ?>

        <!-- DISCONNECT CONFIRMATION MODAL -->
        <div class="modal-overlay" id="disconnectConfirmModal">
            <div class="modal-box" style="max-width:420px;">
                <h2>Disconnect Broker</h2>
                <p>
                    Your MT5 details will be deleted from our end.
                    <br><br>
                    You can reconnect at any time from the Connect Broker page.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                    <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed</button>
                </div>
            </div>
        </div>

        <form id="disconnectBrokerForm" method="POST" style="display:none;">
            <input type="hidden" name="disconnect_dev_broker" value="1">
        </form>

    <?php endif; ?>

<script>
    function openDisconnectConfirmModal() {
        var modal = document.getElementById('disconnectConfirmModal');
        if (modal) modal.classList.add('active');
    }

    function closeDisconnectConfirmModal() {
        var modal = document.getElementById('disconnectConfirmModal');
        if (modal) modal.classList.remove('active');
    }

    function submitDisconnect() {
        var form = document.getElementById('disconnectBrokerForm');
        if (form) form.submit();
    }

    document.addEventListener('click', function(event) {
        var modal = document.getElementById('disconnectConfirmModal');
        if (modal && event.target === modal) {
            closeDisconnectConfirmModal();
        }
    });

    window.openDisconnectConfirmModal  = openDisconnectConfirmModal;
    window.closeDisconnectConfirmModal = closeDisconnectConfirmModal;
    window.submitDisconnect            = submitDisconnect;

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
</script>

</body>
</html>