<?php
// disconnect_broker.php — per sub-account
session_start();

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

// Central notification service
require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== RESOLVE ACTIVE SUB ACCOUNT ====================
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

$user = null;

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && (int)($row['sub_account_id'] ?? 0) === $activeSubAccountId) {
        $user = $row;
    }
}

if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) { header("Location: index.php"); exit; }

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

// ==================== FRESH FETCH OF THE ACTIVE SUB ACCOUNT ROW ====================
$freshUser = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if ($freshUser) {
    $user = $freshUser;
    $userId = (int)$user['id'];
}

$current_broker      = (string)($user['broker'] ?? '');
$current_server      = (string)($user['server'] ?? '');
$current_login       = (string)($user['login'] ?? '');
$fullname            = (string)($user['fullname'] ?? '');
$application_status  = (string)($user['application_status'] ?? '');

$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$broker_connected = ($current_broker !== '' && $current_server !== '' && $current_login !== '');

$needs_warning    = false;
$violation_reason = '';

$contract_duration = 30;
try {
    $stmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cfg && !empty($cfg['contract_duration'])) {
        $contract_duration = (int)$cfg['contract_duration'];
    }
} catch (Exception $e) {}

$execution_start_date = $user['execution_start_date'] ?? null;
if ($execution_start_date && $execution_start_date !== '0000-00-00' && $execution_start_date !== null) {
    try {
        $start = new DateTime($execution_start_date);
        $end   = clone $start;
        $end->modify("+{$contract_duration} days");

        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $endClone = clone $end;
        $endClone->setTime(0, 0, 0);

        $daysLeft = (int)$today->diff($endClone)->format('%r%a');
        if ($daysLeft > 0) {
            $needs_warning    = true;
            $violation_reason = 'This account\'s trading contract is currently active.';
        }
    } catch (Exception $e) {}
}

$payment_issue_statuses = [
    'unpaid-payment', 'unpaid',
    'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
    'contract-cancelled-payment-required',
    'payment-failed', 'failed-payment',
    'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
    'payment-made', 'contract-cancelled-payment-made',
    'pending_payment'
];

$loyalties_status = $user['loyalties'] ?? null;

if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
    $needs_warning = true;
    if (in_array($loyalties_status, ['unpaid-payment', 'unpaid', 'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment', 'contract-cancelled-payment-required', 'pending_payment'], true)) {
        $violation_reason = 'This account has an unpaid profit split payment.';
    } elseif (in_array($loyalties_status, ['payment-failed', 'failed-payment', 'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed'], true)) {
        $violation_reason = 'This account\'s profit split payment has failed.';
    } elseif (in_array($loyalties_status, ['payment-made', 'contract-cancelled-payment-made'], true)) {
        $violation_reason = 'This account\'s profit split payment is awaiting confirmation.';
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT loyalties FROM revenue_history
        WHERE user_email = ?
          AND (sub_account_id = ? OR sub_account_id IS NULL)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $activeSubAccountId]);
    $revRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($revRecord && !empty($revRecord['loyalties'])) {
        if (in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            $needs_warning = true;
            if (empty($violation_reason)) {
                $violation_reason = 'This account has a pending payment obligation.';
            }
        }
    }
} catch (Exception $e) {}

$session_key = 'verified_disconnect_broker_' . $activeSubAccountId;
$is_verified = (!empty($_SESSION[$session_key]) && $_SESSION[$session_key] === true);

$disconnect_success = false;
$disconnect_error   = '';
$disconnect_message = '';
$was_suspended      = false;
$requires_verification = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_broker'])) {
    if (!$is_verified) {
        $requires_verification = true;
    } else {
        // Always re-fetch the exact row before disconnecting
        $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
        $stmt->execute([$activeSubAccountId, $email]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            $disconnect_error = 'Account not found.';
        } else {
            $post_needs_warning = false;

            $post_exec_start = $currentUser['execution_start_date'] ?? null;
            if ($post_exec_start && $post_exec_start !== '0000-00-00' && $post_exec_start !== null) {
                try {
                    $start = new DateTime($post_exec_start);
                    $end   = clone $start;
                    $end->modify("+{$contract_duration} days");

                    $today = new DateTime();
                    $today->setTime(0, 0, 0);
                    $endClone = clone $end;
                    $endClone->setTime(0, 0, 0);

                    $daysLeft = (int)$today->diff($endClone)->format('%r%a');
                    if ($daysLeft > 0) $post_needs_warning = true;
                } catch (Exception $e) {}
            }

            $post_loyalties = $currentUser['loyalties'] ?? null;
            if ($post_loyalties && in_array($post_loyalties, $payment_issue_statuses, true)) {
                $post_needs_warning = true;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT loyalties FROM revenue_history
                    WHERE user_email = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $activeSubAccountId]);
                $postRev = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($postRev && !empty($postRev['loyalties']) && in_array($postRev['loyalties'], $payment_issue_statuses, true)) {
                    $post_needs_warning = true;
                }
            } catch (Exception $e) {}

            // Capture broker details before clearing for the notification message
            $disconnectedBroker = $currentUser['broker'] ?? 'Unknown';
            $disconnectedLogin  = $currentUser['login'] ?? '';

            try {
                if ($post_needs_warning) {
                    $stmt = $pdo->prepare("
                        UPDATE harvhub
                        SET broker = NULL, server = NULL, login = NULL, broker_password = NULL,
                            application_status = 'suspended'
                        WHERE sub_account_id = ? AND LOWER(email) = ?
                    ");
                    $stmt->execute([$activeSubAccountId, $email]);

                    $disconnect_success = true;
                    $was_suspended      = true;
                    $disconnect_message = 'Broker disconnected. This account has been suspended due to a policy violation.';

                    // ============================================
                    // NOTIFICATION: BROKER DISCONNECTED + SUSPENDED
                    // ============================================
                    recordContractNotification($pdo, [
                        'user_email'       => $email,
                        'sub_account_id'   => $activeSubAccountId,
                        'main_account_id'  => $mainAccountId,
                        'notification_key' => 'broker-disconnected-suspended-' . $activeSubAccountId . '-' . date('YmdHis'),
                        'title'            => 'Broker Disconnected - Account Suspended',
                        'message'          => 'Your broker (' . $disconnectedBroker . ' - ' . $disconnectedLogin . ') has been disconnected and your account has been suspended due to a policy violation.',
                        'type'             => 'danger',
                        'section'          => 'Broker',
                        'action_tab'       => 'disconnect_broker',
                        'force'            => true
                    ]);

                } else {
                    $stmt = $pdo->prepare("
                        UPDATE harvhub
                        SET broker = NULL, server = NULL, login = NULL, broker_password = NULL
                        WHERE sub_account_id = ? AND LOWER(email) = ?
                    ");
                    $stmt->execute([$activeSubAccountId, $email]);

                    $disconnect_success = true;
                    $was_suspended      = false;
                    $disconnect_message = 'Broker disconnected successfully.';

                    // ============================================
                    // NOTIFICATION: BROKER DISCONNECTED
                    // ============================================
                    recordContractNotification($pdo, [
                        'user_email'       => $email,
                        'sub_account_id'   => $activeSubAccountId,
                        'main_account_id'  => $mainAccountId,
                        'notification_key' => 'broker-disconnected-' . $activeSubAccountId . '-' . date('YmdHis'),
                        'title'            => 'Broker Disconnected',
                        'message'          => 'Your broker (' . $disconnectedBroker . ' - ' . $disconnectedLogin . ') has been disconnected from your account.',
                        'type'             => 'info',
                        'section'          => 'Broker',
                        'action_tab'       => 'connect_investor_broker',
                        'force'            => true
                    ]);
                }

                unset($_SESSION[$session_key]);
            } catch (PDOException $e) {
                $disconnect_error = 'Failed to disconnect broker. Please try again.';
            }
        }
    }
}

if ($requires_verification) {
    header('Location: verify_code.php?source=disconnect_broker&action=' . urlencode('Disconnect Broker'));
    exit;
}

$auto_submit_disconnect = false;
if (
    $is_verified
    && !$disconnect_success
    && $broker_connected
    && $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['verified'])
    && $_GET['verified'] == '1'
) {
    $auto_submit_disconnect = true;
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
<?php include 'style.php'; ?>
<?php include 'connect_investor_broker_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> broker-page-body">

<div class="broker-page-wrapper">

    <header class="broker-sticky-top">
        <div class="broker-topbar">
            <a href="#"
               class="broker-topbar-back"
               onclick="event.preventDefault(); harvhubGoToTab('menu'); return false;"
               aria-label="Back to Menu">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 class="broker-topbar-title">Disconnect Broker</h1>
        </div>
    </header>

    <div class="broker-scroll-area" id="brokerScrollArea">

        <div class="broker-user-info">
            <span class="broker-user-info-item">
                <span class="broker-user-label">User</span>
                <span class="broker-user-value"><?= htmlspecialchars($fullname ?: $email) ?></span>
            </span>
            <span class="broker-user-info-item">
                <span class="broker-user-label">Status</span>
                <span class="broker-status-badge <?= strtolower($application_status) ?>">
                    <?= htmlspecialchars($application_status ?: 'Pending') ?>
                </span>
            </span>
        </div>

        <?php if (!$broker_connected): ?>
            <div class="broker-error-message" style="display:block;">
                No broker account is currently connected to this account.
            </div>
            <div class="broker-connect-wrapper" style="margin-top:20px;">
                <a href="#" onclick="event.preventDefault(); harvhubGoToTab('connect_investor_broker'); return false;" class="broker-btn-secondary" style="text-decoration:none; display:inline-block;">
                    Go to Connect Broker
                </a>
            </div>

        <?php elseif ($disconnect_success): ?>
            <div class="broker-success-message" style="display:block;">
                <?= htmlspecialchars($disconnect_message) ?>
            </div>

            <?php if ($was_suspended): ?>
                <div class="broker-info-note" style="margin-top:18px; ">
                    <strong style="color:#dc3545;">Account Suspended:</strong>
                    This account's broker details have been removed and it is now suspended.
                    You may contact support if you believe this was an error.
                </div>
            <?php endif; ?>

            <div class="broker-connect-wrapper" style="margin-top:20px;">
                <a href="#" onclick="event.preventDefault(); harvhubGoToTab('mydashboard'); return false;" class="broker-btn-submit" style="text-decoration:none; display:inline-block;">
                    Return to Dashboard
                </a>
            </div>

        <?php elseif ($auto_submit_disconnect): ?>
            <div class="broker-info-note" style="margin-bottom:18px;">
                <strong>Identity verified.</strong>
                Processing your disconnect request.
            </div>

            <div style="text-align:center; padding:24px 0;">
                <div class="spinner" style="display:inline-block; width:40px; height:40px; border:3px solid rgba(255,255,255,0.1); border-radius:50%; border-top-color:var(--accent, #2e8b57); animation:spin 0.6s linear infinite;"></div>
                <p style="margin-top:12px; color:var(--text-muted, #888); font-size:0.85rem;">Please wait.</p>
            </div>

            <style>@keyframes spin { to { transform: rotate(360deg); } }</style>

            <form id="disconnectBrokerForm" method="POST" style="display:none;">
                <input type="hidden" name="disconnect_broker" value="1">
            </form>

        <?php else: ?>
            <?php if (!empty($disconnect_error)): ?>
                <div class="broker-error-message" style="display:block;">
                    <?= htmlspecialchars($disconnect_error) ?>
                </div>
            <?php endif; ?>

            <?php if ($is_verified): ?>
                <div class="broker-success-message" style="display:block;">
                    Identity verified. You may now proceed with the disconnect.
                </div>
            <?php endif; ?>

            <div class="broker-section-title">Current Broker Details</div>
            <div class="broker-info-note" style="margin-bottom:18px;">
                <div style="margin-bottom:6px;"><strong>Broker:</strong> <?= htmlspecialchars($current_broker) ?></div>
                <div style="margin-bottom:6px;"><strong>Server:</strong> <?= htmlspecialchars($current_server) ?></div>
                <div><strong>Login:</strong> <?= htmlspecialchars($current_login) ?></div>
            </div>

            <hr class="broker-divider">

            <div class="broker-section-title">Disconnect Broker</div>

            <div class="broker-info-note" style="margin-bottom:18px;">
                <strong>Note:</strong> Disconnecting will remove this account's broker credentials from our servers.
                You can reconnect at any time from the Connect Broker page.
            </div>

            <button type="button" class="broker-btn-submit" style="background:#dc3545;" onclick="openDisconnectConfirmModal()">
                Disconnect Broker
            </button>
        <?php endif; ?>

    </div>
</div>

<?php if ($broker_connected && !$disconnect_success && !$auto_submit_disconnect): ?>
    <div class="modal-overlay" id="disconnectConfirmModal">
        <div class="modal-box" style="max-width:420px;">
            <?php if ($needs_warning): ?>
                <h2 style="color:#dc3545;">Policy Violation Warning</h2>
                <p>
                    Disconnecting your MT5 at this time violates our policy.
                    <br>
                    <?php if (!empty($violation_reason)): ?>
                        <strong><?= htmlspecialchars($violation_reason) ?></strong><br><br>
                    <?php endif; ?>
                    This account's broker details will be deleted on our end and it will be
                    <strong>suspended</strong> in our community.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                    <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed Anyway</button>
                </div>
            <?php else: ?>
                <h2>Disconnect Broker</h2>
                <p>
                    This account's MT5 details will be deleted from our end.
                    <br>
                    You can reconnect at any time from the Connect Broker page.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                    <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="disconnectBrokerForm" method="POST" style="display:none;">
        <input type="hidden" name="disconnect_broker" value="1">
    </form>
<?php endif; ?>

<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-disconnect_broker');

        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-trader_app'
        ];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();

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

    <?php if ($auto_submit_disconnect): ?>
    (function() {
        var form = document.getElementById('disconnectBrokerForm');
        if (form) {
            setTimeout(function() { form.submit(); }, 300);
        }
    })();
    <?php endif; ?>
</script>

</body>
</html>