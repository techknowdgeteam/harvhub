<?php
// disconnect_broker.php
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

// ==================== GET CURRENT VALUES ====================
$current_broker      = $user['broker'] ?? '';
$current_server      = $user['server'] ?? '';
$current_login       = $user['login'] ?? '';
$fullname            = $user['fullname'] ?? '';
$application_status  = $user['application_status'] ?? '';

$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$broker_connected = !empty($current_broker) && !empty($current_server) && !empty($current_login);

// ==================== DETECT POLICY VIOLATION STATE ====================
$needs_warning    = false;
$violation_reason = '';

// ---- Contract duration config ----
$contract_duration = 30;
try {
    $stmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
    $cfg = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cfg && !empty($cfg['contract_duration'])) {
        $contract_duration = (int)$cfg['contract_duration'];
    }
} catch (Exception $e) {}

// ---- Check active contract ----
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
            $violation_reason = 'Your trading contract is currently active.';
        }
    } catch (Exception $e) {}
}

// ---- Check payment-era statuses ----
$payment_issue_statuses = [
    'unpaid-payment',
    'unpaid',
    'contract-cancelled-unpaid',
    'contract-cancelled-unpaid-payment',
    'contract-cancelled-payment-required',
    'payment-failed',
    'failed-payment',
    'contract-cancelled-failed-payment',
    'contract-cancelled-payment-failed',
    'payment-made',
    'contract-cancelled-payment-made',
    'pending_payment'
];

$loyalties_status = $user['loyalties'] ?? null;

if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
    $needs_warning = true;
    if (in_array($loyalties_status, ['unpaid-payment', 'unpaid', 'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment', 'contract-cancelled-payment-required', 'pending_payment'], true)) {
        $violation_reason = 'You have an unpaid profit-split payment.';
    } elseif (in_array($loyalties_status, ['payment-failed', 'failed-payment', 'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed'], true)) {
        $violation_reason = 'Your profit-split payment has failed.';
    } elseif (in_array($loyalties_status, ['payment-made', 'contract-cancelled-payment-made'], true)) {
        $violation_reason = 'Your profit-split payment is awaiting confirmation.';
    }
}

// ---- Also check latest revenue history record ----
try {
    $stmt = $pdo->prepare("SELECT loyalties FROM revenue_history WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $revRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($revRecord && !empty($revRecord['loyalties'])) {
        if (in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            $needs_warning = true;
            if (empty($violation_reason)) {
                $violation_reason = 'You have a pending payment obligation.';
            }
        }
    }
} catch (Exception $e) {}

// ==================== VERIFICATION GATE ====================
$session_key = 'verified_disconnect_broker';

// Simply check the boolean flag — no timer, no expiry.
$is_verified = (!empty($_SESSION[$session_key]) && $_SESSION[$session_key] === true);

// ==================== HANDLE DISCONNECT (POST) ====================
$disconnect_success = false;
$disconnect_error   = '';
$disconnect_message = '';
$was_suspended      = false;
$requires_verification = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_broker'])) {

    // ---- Verification gate check ----
    if (!$is_verified) {
        $requires_verification = true;
    } else {

        // Re-fetch user for latest state
        $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
        $stmt->execute([$email]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            $disconnect_error = 'User not found.';
        } else {

            // ---- Re-evaluate policy violation state on POST ----
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
                    if ($daysLeft > 0) {
                        $post_needs_warning = true;
                    }
                } catch (Exception $e) {}
            }

            $post_loyalties = $currentUser['loyalties'] ?? null;
            if ($post_loyalties && in_array($post_loyalties, $payment_issue_statuses, true)) {
                $post_needs_warning = true;
            }

            try {
                $stmt = $pdo->prepare("SELECT loyalties FROM revenue_history WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$email]);
                $postRev = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($postRev && !empty($postRev['loyalties']) && in_array($postRev['loyalties'], $payment_issue_statuses, true)) {
                    $post_needs_warning = true;
                }
            } catch (Exception $e) {}

            try {
                if ($post_needs_warning) {
                    // SCENARIO B: POLICY VIOLATION — delete + suspend
                    $stmt = $pdo->prepare("
                        UPDATE harvhub
                        SET broker = NULL,
                            server = NULL,
                            login = NULL,
                            broker_password = NULL,
                            application_status = 'suspended'
                        WHERE email = ?
                    ");
                    $stmt->execute([$email]);

                    $disconnect_success = true;
                    $was_suspended      = true;
                    $disconnect_message = 'Broker disconnected. Your account has been suspended due to policy violation.';

                } else {
                    // SCENARIO A: CLEAN DISCONNECT
                    $stmt = $pdo->prepare("
                        UPDATE harvhub
                        SET broker = NULL,
                            server = NULL,
                            login = NULL,
                            broker_password = NULL
                        WHERE email = ?
                    ");
                    $stmt->execute([$email]);

                    $disconnect_success = true;
                    $was_suspended      = false;
                    $disconnect_message = 'Broker disconnected successfully.';
                }

                // Consume the verification token (one-time use)
                unset($_SESSION[$session_key]);

            } catch (PDOException $e) {
                $disconnect_error = 'Failed to disconnect broker. Please try again.';
            }
        }
    }
}

// ---- If verification was required, redirect to verify_code.php ----
if ($requires_verification) {
    header('Location: verify_code.php?source=disconnect_broker&action=' . urlencode('Disconnect Broker'));
    exit;
}

// ---- If we just got back from a successful verification AND the form hasn't
//      been submitted yet, auto-submit the disconnect via POST ----
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>Disconnect Broker - HarvHub</title>
<?php include 'style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="connect-broker-container">
    <div class="connect-broker-card">

        <!-- Back to Dashboard - TOP -->
        <div class="back-link-top">
            <a href="app.php#menu">← Menu</a>
        </div>

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

        <?php if (!$broker_connected): ?>

            <!-- STATE 1: No broker connected -->
            <div class="error-message" style="display:block;">
                No broker account is currently connected.
            </div>
            <div class="btn-connect-wrapper" style="margin-top:20px;">
                <a href="connect_investor_broker.php" class="btn-secondary" style="text-decoration:none; display:inline-block; padding:12px 32px;">
                    Go to Connect Broker
                </a>
            </div>

        <?php elseif ($disconnect_success): ?>

            <!-- STATE 2: Disconnect succeeded -->
            <div class="success-message" style="display:block;">
                <?= htmlspecialchars($disconnect_message) ?>
            </div>

            <?php if ($was_suspended): ?>
                <div class="info-note" style="margin-top:18px; border-left:4px solid #dc3545;">
                    <strong style="color:#dc3545;">Account Suspended:</strong>
                    Your broker details have been removed and your account is now suspended.
                    You may contact support if you believe this was an error.
                </div>
            <?php endif; ?>

            <div class="btn-connect-wrapper" style="margin-top:20px;">
                <a href="app.php#mydashboard" class="btn-submit" style="text-decoration:none; display:inline-block; padding:12px 32px;">
                    Return to Dashboard
                </a>
            </div>

        <?php elseif ($auto_submit_disconnect): ?>

            <!-- STATE 3: Just returned from verify_code.php — show processing -->
            <div class="info-note" style="margin-bottom:18px;">
                <strong>✓ Identity verified.</strong>
                Processing your disconnect request...
            </div>

            <div style="text-align:center; padding:24px 0;">
                <div class="spinner" style="display:inline-block; width:40px; height:40px; border:3px solid rgba(255,255,255,0.1); border-radius:50%; border-top-color:var(--accent, #2e8b57); animation:spin 0.6s linear infinite;"></div>
                <p style="margin-top:12px; color:var(--text-muted, #888); font-size:0.85rem;">Please wait…</p>
            </div>

            <style>
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>

            <!-- Auto-submit form -->
            <form id="disconnectBrokerForm" method="POST" style="display:none;">
                <input type="hidden" name="disconnect_broker" value="1">
            </form>

        <?php else: ?>

            <!-- STATE 4: Broker connected — show current details + disconnect button -->
            <?php if (!empty($disconnect_error)): ?>
                <div class="error-message" style="display:block;">
                    <?= htmlspecialchars($disconnect_error) ?>
                </div>
            <?php endif; ?>

            <?php if ($is_verified): ?>
                <div class="success-message" style="display:block;">
                    ✓ Identity verified. You may now proceed with the disconnect.
                </div>
            <?php endif; ?>

            <!-- Current broker details -->
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
                <br><br>
                <strong>Security:</strong> You will be asked to verify your email with a 6-digit code before the disconnect is processed.
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

<?php if ($broker_connected && !$disconnect_success && !$auto_submit_disconnect): ?>

    <!-- DISCONNECT CONFIRMATION MODAL -->
    <div class="modal-overlay" id="disconnectConfirmModal">
        <div class="modal-box" style="max-width:420px;">

            <?php if ($needs_warning): ?>
                <!-- SCENARIO B: POLICY VIOLATION -->
                <h2 style="color:#dc3545;">Policy Violation Warning</h2>
                <p>
                    Disconnecting your MT5 at this time violates our policy.
                    <br><br>
                    <?php if (!empty($violation_reason)): ?>
                        <strong><?= htmlspecialchars($violation_reason) ?></strong><br><br>
                    <?php endif; ?>
                    Your broker details will be deleted on our end and you will be
                    <strong>suspended</strong> in our community.
                    <br><br>
                    You will be asked to verify your email before proceeding.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                    <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed Anyway</button>
                </div>

            <?php else: ?>
                <!-- SCENARIO A: CLEAN DISCONNECT -->
                <h2>Disconnect Broker</h2>
                <p>
                    Your MT5 details will be deleted from our end.
                    <br><br>
                    You can reconnect at any time from the Connect Broker page.
                    <br><br>
                    You will be asked to verify your email before proceeding.
                </p>
                <div class="modal-actions">
                    <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                    <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed</button>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <!-- Hidden POST form used by submitDisconnect() -->
    <form id="disconnectBrokerForm" method="POST" style="display:none;">
        <input type="hidden" name="disconnect_broker" value="1">
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

    // Close modal when clicking the overlay background
    document.addEventListener('click', function(event) {
        var modal = document.getElementById('disconnectConfirmModal');
        if (modal && event.target === modal) {
            closeDisconnectConfirmModal();
        }
    });

    // Expose globally
    window.openDisconnectConfirmModal  = openDisconnectConfirmModal;
    window.closeDisconnectConfirmModal = closeDisconnectConfirmModal;
    window.submitDisconnect            = submitDisconnect;

    <?php if ($auto_submit_disconnect): ?>
    // Auto-submit the disconnect form after successful verification
    (function() {
        var form = document.getElementById('disconnectBrokerForm');
        if (form) {
            setTimeout(function() {
                form.submit();
            }, 300);
        }
    })();
    <?php endif; ?>
</script>

</body>
</html>