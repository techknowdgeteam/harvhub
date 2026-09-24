<?php
//app.php
    session_start();

    // Handle logout
    if (isset($_GET['logout'])) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: index.php");
        exit;
    }

    if (!isset($_SESSION['user_email'])) {
        header("Location: index.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    require_once 'usersdb.php';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    // ==================== DARK MODE TOGGLE AJAX ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_dark_mode_ajax'])) {
        header('Content-Type: application/json');
        $darkModeValue = isset($_POST['dark_mode_checkbox']) ? (int)$_POST['dark_mode_checkbox'] : 0;
        $upd = $pdo->prepare("UPDATE $tableName SET dark_mode = ? WHERE email = ?");
        $upd->execute([$darkModeValue, $email]);
        echo json_encode(['success' => true, 'dark_mode' => $darkModeValue]);
        exit;
    }

    // ==================== CONNECT BROKER AJAX ====================
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
                UPDATE $tableName 
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

    $darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
    $darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

    // Check broker connection status for session modal
    $brokerConnected = (!empty($user['broker']) && !empty($user['server']) && !empty($user['login']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>🌾Harvhub</title>
<?php include 'style.php'; ?>
<style>
    body {
        padding-top: var(--header-height, 60px);
        padding-bottom: var(--nav-height, 100px);
        background: var(--bg);
        color: var(--text);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
        margin: 0;
        transition: background var(--transition-speed, 0.3s), color var(--transition-speed, 0.3s);
    }
    
    @media (max-width: 480px) {
        body {
            padding-top: var(--header-height-mobile, 52px);
            padding-bottom: var(--nav-height-mobile, 80px);
        }
    }
    
    #page-content { 
        min-height: 80vh;
        transition: opacity 0.15s ease;
    }
    #page-content.loading { opacity: 0.4; }
    
    .spinner-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 99999;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }
    .spinner-overlay.active { display: flex; }
    
    .spinner {
        display: inline-block;
        width: 40px; height: 40px;
        border: 3px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        border-top-color: var(--accent, #fff);
        animation: spin 0.6s linear infinite;
        margin-bottom: 12px;
    }
    .spinner-text {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin: 0;
        letter-spacing: 0.5px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.7);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }

    .bottom-nav {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 40px);
        max-width: 500px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 10px 12px;
        border-radius: var(--radius, 16px);
        z-index: 1000;
        transition: transform 0.3s ease, opacity 0.3s ease, background var(--transition-speed, 0.3s);
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    body.dark-mode .bottom-nav {
        background: rgba(20, 20, 30, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    }

    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: var(--text-muted, #888);
        font-size: 0.6rem;
        transition: color var(--transition-speed, 0.3s);
        padding: 4px 12px;
        border-radius: var(--radius-sm, 8px);
        background: transparent;
        border: none;
        cursor: pointer;
        gap: 2px;
        min-width: 44px;
    }
    
    .bottom-nav-item .nav-icon {
        font-size: 1.3rem;
        line-height: 1.2;
    }
    
    .bottom-nav-item .nav-label {
        font-size: 0.55rem;
        font-weight: 500;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    
    .bottom-nav-item.active { color: var(--accent, #2e8b57); }
    .bottom-nav-item:hover { color: var(--accent, #2e8b57); }
    
    body.page-connect_investor_broker .bottom-nav { display: none !important; }

    /* NEW: hide bottom nav while the profile page is open */
    body.profile-page-open .bottom-nav { display: none !important; }

    #page-content .connect-broker-container {
        max-width: 100% !important;
        width: 100% !important;
        padding: 20px !important;
        margin: 0 auto !important;
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        min-height: 75vh !important;
    }
        
    #page-content .connect-broker-card {
        max-height: 80vh !important;
        overflow-y: auto !important;
        padding: 28px 24px !important;
        margin: 10px auto !important;
        margin-top: 30px !important;
        max-width: 480px !important;
        width: 100% !important;
        background: var(--bg-card) !important;
        border: 1px solid var(--border-color) !important;
        border-radius: var(--radius) !important;
        box-shadow: var(--shadow-lg) !important;
    }
    
    #page-content .connect-broker-card * {
        pointer-events: auto !important;
    }
    
    #page-content .connect-broker-card button,
    #page-content .connect-broker-card a,
    #page-content .connect-broker-card select,
    #page-content .connect-broker-card input,
    #page-content .connect-broker-card .btn-broker-link,
    #page-content .connect-broker-card .btn-secondary,
    #page-content .connect-broker-card .btn-submit,
    #page-content .connect-broker-card .password-toggle,
    #page-content .connect-broker-card .back-link-top a {
        cursor: pointer !important;
        pointer-events: auto !important;
        position: relative !important;
        z-index: 10 !important;
    }
    
    #page-content .connect-broker-card .btn-broker-link.no-link {
        cursor: not-allowed !important;
        pointer-events: none !important;
    }
    
    #page-content .connect-broker-card .btn-submit:disabled {
        cursor: not-allowed !important;
        pointer-events: none !important;
    }
    
    #page-content .connect-broker-card body {
        display: block !important;
        padding: 0 !important;
        margin: 0 !important;
        min-height: auto !important;
        background: transparent !important;
    }

    .session-info-modal .session-modal-content {
        text-align: center;
        padding: 32px 24px;
        max-width: 420px;
        width: 100%;
        border-radius: var(--radius);
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-lg);
    }

    .session-info-modal .session-modal-content.info {
        border-top: 4px solid var(--info, #17a2b8);
    }
    .session-info-modal .session-modal-content.success {
        border-top: 4px solid var(--success, #2ecc71);
    }
    .session-info-modal .session-modal-content.warning {
        border-top: 4px solid var(--warning, #ffc107);
    }
    .session-info-modal .session-modal-content.danger {
        border-top: 4px solid var(--danger, #e74c3c);
    }

    .session-modal-icon {
        font-size: 3rem;
        line-height: 1;
        margin-bottom: 16px;
        display: block;
    }

    .session-modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 12px;
    }

    .session-modal-message {
        font-size: 0.95rem;
        line-height: 1.55;
        color: var(--text-secondary);
        margin: 0 0 24px;
    }

    .session-info-modal .modal-actions {
        display: flex;
        justify-content: center;
    }

    .session-info-modal .btn-action {
        min-width: 140px;
        flex: 0 0 auto;
    }

    /* Clickable user card (menu page) — profile page switch */
    .user-card.profile-clickable {
        cursor: pointer;
        position: relative;
        transition: all 0.2s ease;
    }

    .user-card.profile-clickable:hover {
        background: var(--bg, rgba(0,0,0,0.03));
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    }

    .user-card.profile-clickable:active {
        transform: translateY(0);
    }

    .user-card.profile-clickable:focus {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .profile-chevron {
        margin-left: auto;
        color: var(--text-muted);
        font-size: 14px;
        transition: transform 0.2s ease, color 0.2s ease;
        flex-shrink: 0;
    }

    .user-card.profile-clickable:hover .profile-chevron {
        transform: translateX(3px);
        color: var(--accent);
    }
</style>
</head>
<body>
    
    <?php include 'harvhub_header.php'; ?>
    
    <script>
        document.body.className = '<?= htmlspecialchars($darkModeClass) ?>';
    </script>
    
    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner"></div>
    </div>
    
    <div id="page-content">
        <?php include 'mydashboard.php'; ?>
    </div>
    
    <?php include 'dashboard_tabs.php'; ?>

    <?php
    // =====================================================================
    // SESSION-TRACKED ONE-TIME INFORMATIONAL MODALS
    //
    // IMPORTANT: The order matters. Success modals (from POST redirects)
    // take priority. Then we evaluate the user's CURRENT state on every
    // page load — not just when a status first changes — so the modal
    // always reflects the true live state (balance verification, enroll,
    // active contract, etc.) even after a payment was confirmed.
    // =====================================================================

    $sessionModalState = '';

    // -----------------------------------------------------------------
    // 1) SUCCESS MODALS (from PRG redirects) — highest priority
    // -----------------------------------------------------------------
    if (isset($_GET['show_enroll_success']) && $_GET['show_enroll_success'] == '1' && isset($_SESSION['enroll_success_message'])) {
        $sessionModalState = 'enroll_success';
        $enrollSuccessMessage = $_SESSION['enroll_success_message'];
        unset($_SESSION['enroll_success_message']);
        unset($_SESSION['enroll_success_details']);
    } elseif (isset($_GET['show_apply_success']) && $_GET['show_apply_success'] == '1' && isset($_SESSION['apply_success_message'])) {
        $sessionModalState = 'apply_success';
        $applySuccessMessage = $_SESSION['apply_success_message'];
        $applySuccessDetails = $_SESSION['apply_success_details'] ?? '';
        unset($_SESSION['apply_success_message']);
        unset($_SESSION['apply_success_details']);
    } elseif (isset($_GET['show_reset_success']) && $_GET['show_reset_success'] == '1' && isset($_SESSION['reset_success_message'])) {
        $sessionModalState = 'reset_success';
        $resetSuccessMessage = $_SESSION['reset_success_message'];
        unset($_SESSION['reset_success_message']);
    } elseif (isset($_GET['show_toggle_success']) && $_GET['show_toggle_success'] == '1' && isset($_SESSION['toggle_success_message'])) {
        $sessionModalState = 'toggle_success';
        $toggleSuccessMessage = $_SESSION['toggle_success_message'];
        unset($_SESSION['toggle_success_message']);
    }
    else {
        // -----------------------------------------------------------------
        // 2) LIVE STATE RESOLUTION
        //
        // Build the list of loyalty statuses from both the user row and
        // the latest revenue_history row.
        // -----------------------------------------------------------------
        $allLoyaltyStatuses = [];
        if (isset($loyaltiesStatus) && $loyaltiesStatus !== null) {
            $allLoyaltyStatuses[] = $loyaltiesStatus;
        }
        if (isset($latestRevenueRecord) && is_array($latestRevenueRecord) && isset($latestRevenueRecord['loyalties']) && $latestRevenueRecord['loyalties'] !== null) {
            $allLoyaltyStatuses[] = $latestRevenueRecord['loyalties'];
        }
        $allLoyaltyStatuses = array_unique($allLoyaltyStatuses);

        $paymentMadeStatuses = ['payment-made', 'contract-cancelled-payment-made'];
        $unpaidStatuses      = ['unpaid-payment', 'unpaid', 'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment', 'contract-cancelled-payment-required'];
        $failedStatuses      = ['payment-failed', 'failed-payment', 'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed'];
        $confirmedStatuses   = ['payment-confirmed'];

        // Determine whether a payment is currently pending / failed / unpaid.
        // These take priority over the downstream states because the user
        // must complete the payment before anything else can progress.
        $hasPendingPayment = false;
        $hasFailedPayment  = false;
        $hasUnpaidPayment  = false;
        $hasConfirmedPayment = false;

        foreach ($allLoyaltyStatuses as $status) {
            if (in_array($status, $confirmedStatuses, true)) {
                $hasConfirmedPayment = true;
            }
        }

        // Only treat pending/failed/unpaid as active blockers if we do NOT
        // already have a confirmed payment. A confirmed payment means the
        // cycle has moved on to the next phase.
        if (!$hasConfirmedPayment) {
            foreach ($allLoyaltyStatuses as $status) {
                if (in_array($status, $paymentMadeStatuses, true)) {
                    $hasPendingPayment = true;
                    break;
                }
            }
            if (!$hasPendingPayment) {
                foreach ($allLoyaltyStatuses as $status) {
                    if (in_array($status, $failedStatuses, true)) {
                        $hasFailedPayment = true;
                        break;
                    }
                }
            }
            if (!$hasPendingPayment && !$hasFailedPayment) {
                foreach ($allLoyaltyStatuses as $status) {
                    if (in_array($status, $unpaidStatuses, true)) {
                        $hasUnpaidPayment = true;
                        break;
                    }
                }
            }
        }

        // -----------------------------------------------------------------
        // 3) STATE PRIORITY CHAIN
        //
        // Payment states first (if payment not yet confirmed).
        // If payment IS confirmed (or no payment cycle at all), fall
        // through to the live dashboard state checks.
        // -----------------------------------------------------------------
        if ($hasPendingPayment) {
            $sessionModalState = 'payment_pending';
        } elseif ($hasFailedPayment) {
            $sessionModalState = 'payment_failed';
        } elseif ($hasUnpaidPayment) {
            $sessionModalState = 'profit_split';
        } elseif ($hasConfirmedPayment) {
            // Payment is confirmed — now check the NEXT required step.
            // The user may still need to:
            //   a) connect a broker
            //   b) reset their contract (reset_contract flag)
            //   c) apply for balance verification
            //   d) wait for verification review
            //   e) deposit more funds
            //   f) enroll in a new contract
            //   g) or simply have an active contract
            if (!$brokerConnected) {
                $sessionModalState = 'no_broker';
            } elseif (isset($resetContract) && $resetContract === 1) {
                $sessionModalState = 'reset';
            } elseif (isset($balanceVerificationStatus) && $balanceVerificationStatus === 'applied-for-verification') {
                $sessionModalState = 'under_review';
            } elseif (isset($balanceVerificationStatus) && ($balanceVerificationStatus === 'not-verified' || $balanceVerificationStatus === '' || $balanceVerificationStatus === null)) {
                $sessionModalState = 'apply';
            } elseif (isset($balanceVerificationStatus) && $balanceVerificationStatus === 'verified'
                      && isset($brokerBalance) && isset($MIN_INITIAL_DEPOSIT)
                      && $brokerBalance < $MIN_INITIAL_DEPOSIT) {
                $sessionModalState = 'deposit';
            } elseif (isset($is_contract_active) && $is_contract_active === true) {
                $sessionModalState = 'active';
            } elseif (isset($show_reenroll_button) && $show_reenroll_button === true) {
                $sessionModalState = 'enroll';
            } else {
                // Fallback: payment confirmed, everything else looks settled.
                $sessionModalState = 'payment_confirmed';
            }
        } else {
            // No payment cycle at all — check basic gating states.
            if (!$brokerConnected) {
                $sessionModalState = 'no_broker';
            } elseif (isset($resetContract) && $resetContract === 1) {
                $sessionModalState = 'reset';
            } elseif (isset($balanceVerificationStatus) && $balanceVerificationStatus === 'applied-for-verification') {
                $sessionModalState = 'under_review';
            } elseif (isset($balanceVerificationStatus) && ($balanceVerificationStatus === 'not-verified' || $balanceVerificationStatus === '' || $balanceVerificationStatus === null)) {
                $sessionModalState = 'apply';
            } elseif (isset($balanceVerificationStatus) && $balanceVerificationStatus === 'verified'
                      && isset($brokerBalance) && isset($MIN_INITIAL_DEPOSIT)
                      && $brokerBalance < $MIN_INITIAL_DEPOSIT) {
                $sessionModalState = 'deposit';
            } elseif (isset($is_contract_active) && $is_contract_active === true) {
                $sessionModalState = 'active';
            } elseif (isset($show_reenroll_button) && $show_reenroll_button === true) {
                $sessionModalState = 'enroll';
            }
        }
    }

    $showSessionModal = false;
    $isSuccessModal = in_array($sessionModalState, ['enroll_success', 'apply_success', 'reset_success', 'toggle_success'], true);

    if (!empty($sessionModalState)) {
        if ($isSuccessModal) {
            // Success modals always show (one-time via PRG redirect).
            $showSessionModal = true;
        } else {
            // Informational modals: show once per state per session.
            // If the state changes (e.g. apply → under_review), the new
            // state will not be in the shown list and will display.
            if (!isset($_SESSION['shown_session_modals']) || !is_array($_SESSION['shown_session_modals'])) {
                $_SESSION['shown_session_modals'] = [];
            }
            if (!in_array($sessionModalState, $_SESSION['shown_session_modals'], true)) {
                $showSessionModal = true;
                $_SESSION['shown_session_modals'][] = $sessionModalState;
            }
        }
    }

    $sessionModalTitle   = '';
    $sessionModalMessage = '';
    $sessionModalIcon    = '';
    $sessionModalClass   = '';

    switch ($sessionModalState) {
        case 'enroll_success':
            $sessionModalTitle   = 'Enrollment Successful!';
            $sessionModalMessage = $enrollSuccessMessage ?? 'Your contract has been enrolled successfully. Your trading contract is now active.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'apply_success':
            $sessionModalTitle   = 'Application Submitted!';
            $sessionModalMessage = $applySuccessMessage ?? 'Your application has been submitted successfully.';
            if (!empty($applySuccessDetails)) {
                $sessionModalMessage .= ' ' . $applySuccessDetails;
            }
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'reset_success':
            $sessionModalTitle   = 'Contract Reset Successful';
            $sessionModalMessage = $resetSuccessMessage ?? 'Your contract has been reset. Please apply for verification to start a new contract.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'toggle_success':
            $sessionModalTitle   = 'Balance Display Updated';
            $sessionModalMessage = $toggleSuccessMessage ?? 'Your balance display preference has been saved.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'payment_pending':
            $sessionModalTitle   = 'Payment Pending Confirmation';
            $sessionModalMessage = 'Your payment has been recorded and is awaiting server confirmation. This usually takes a short while. You will be notified once it is confirmed.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'info';
            break;
        case 'payment_failed':
            $sessionModalTitle   = 'Payment Failed';
            $sessionModalMessage = 'Your previous payment attempt was not successful. Please retry the profit split payment to continue.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'danger';
            break;
        case 'profit_split':
            $sessionModalTitle   = 'Profit Split Required';
            $sessionModalMessage = 'Your contract has ended with profit. Please complete the profit split payment to continue with a new contract.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'warning';
            break;
        case 'payment_confirmed':
            $sessionModalTitle   = 'Payment Confirmed';
            $sessionModalMessage = 'Your payment has been confirmed. You can now proceed with the next steps to start a new contract.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'no_broker':
            $sessionModalTitle   = 'Connect Your Broker';
            $sessionModalMessage = 'You need to connect your broker account before you can start trading. Go to the Connect Broker section to get started.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'info';
            break;
        case 'reset':
            $sessionModalTitle   = 'Time for the Next Phase!';
            $sessionModalMessage = 'Your previous contract is complete. You can now start a new journey by applying for verification again.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'info';
            break;
        case 'under_review':
            $sessionModalTitle   = 'Balance Under Review';
            $sessionModalMessage = 'Your balance verification is in progress. This check usually takes between 24 and 48 hours. We will notify you once it is complete.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'info';
            break;
        case 'apply':
            $sessionModalTitle   = 'Balance Verification Required';
            $sessionModalMessage = 'Please apply for verification if you have deposited the minimum required amount into your broker account.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'info';
            break;
        case 'deposit':
            $sessionModalTitle   = 'Deposit Required';
            $sessionModalMessage = 'Your broker balance is below the minimum required deposit of $' . number_format($MIN_INITIAL_DEPOSIT, 2) . '. Please deposit funds to continue.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'warning';
            break;
        case 'active':
            $sessionModalTitle   = 'Contract is Active';
            $sessionModalMessage = 'Your trading contract is currently active. We will notify you when it ends.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        case 'enroll':
            $sessionModalTitle   = 'Ready to Enroll';
            $sessionModalMessage = 'You are ready to start a new trading contract. Click Enroll on the dashboard to begin.';
            $sessionModalIcon    = '';
            $sessionModalClass   = 'success';
            break;
        default:
            $showSessionModal = false;
            break;
    }
    ?>

    <?php if ($showSessionModal): ?>
    <div id="sessionInfoModal" class="modal session-info-modal" data-state="<?= htmlspecialchars($sessionModalState) ?>">
        <div class="modal-content session-modal-content <?= htmlspecialchars($sessionModalClass) ?>">
            <?php if (!empty($sessionModalIcon)): ?>
            <div class="session-modal-icon"><?= $sessionModalIcon ?></div>
            <?php endif; ?>
            <h2 class="session-modal-title"><?= htmlspecialchars($sessionModalTitle) ?></h2>
            <p class="session-modal-message"><?= htmlspecialchars($sessionModalMessage) ?></p>
            <div class="modal-actions">
                <button type="button" class="btn-action btn-loyalty-action session-modal-ok" onclick="closeSessionInfoModal()">
                    Okay
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
            /1\. Header exists/i,
            /2\. Header styles/i,
            /3\. Header offsetHeight/i,
            /4\. Header offsetTop/i,
            /\[WATCHDOG/i,
            /\[SCROLL STATE\]/i,
            /\[BODY SCROLL WATCHDOG\]/i,
            /\[MODAL LOCK\]/i,
            /Smart bottom nav detection initialized/i,
            /showSpinner\(\) CALLED/i,
            /hideSpinner\(\) CALLED/i,
            /closeSessionInfoModal\(\) CALLED/i,
            /OPEN SESSION/i,
            /SESSION ONE-TIME/i,
            /SPA LOADER/i,
            /Invest button clicked/i
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

        window.addEventListener('error', function(e) {
            var msg = (e && e.message) ? e.message : '';
            for (var j = 0; j < SUPPRESS_PATTERNS.length; j++) {
                if (SUPPRESS_PATTERNS[j].test(msg)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
        }, true);

        window.addEventListener('unhandledrejection', function(e) {
            var reason = (e && e.reason) ? (e.reason.message || String(e.reason)) : '';
            for (var j = 0; j < SUPPRESS_PATTERNS.length; j++) {
                if (SUPPRESS_PATTERNS[j].test(reason)) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
        }, true);
    })();
</script>
<script>
    (function() {
        var _lastLockState = null;

        function isAnyModalOrSpinnerVisible() {
            var activeModals = document.querySelectorAll('.modal.active, .modal-overlay.active');
            for (var i = 0; i < activeModals.length; i++) {
                var el = activeModals[i];
                var style = window.getComputedStyle(el);
                if (style.display !== 'none' &&
                    style.visibility !== 'hidden' &&
                    parseFloat(style.opacity || '1') > 0) {
                    return true;
                }
            }
            var spinner = document.getElementById('spinnerOverlay');
            if (spinner && spinner.classList.contains('active')) {
                var s = window.getComputedStyle(spinner);
                if (s.display !== 'none') return true;
            }
            return false;
        }

        function lockBody() {
            if (document.body.dataset.modalScrollLocked === '1') return;
            var scrollY = window.scrollY || window.pageYOffset || 0;
            document.body.dataset.modalScrollLocked = '1';
            document.body.dataset.modalScrollY = scrollY;
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
            document.body.style.top = '-' + scrollY + 'px';
        }

        function unlockBody() {
            var scrollY = parseInt(document.body.dataset.modalScrollY || '0', 10) || 0;
            document.body.dataset.modalScrollLocked = '0';
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.top = '';
            window.scrollTo(0, scrollY);
        }

        function reconcile() {
            var shouldLock = isAnyModalOrSpinnerVisible();
            var isLocked = document.body.dataset.modalScrollLocked === '1';

            if (shouldLock && !isLocked) {
                lockBody();
            } else if (!shouldLock && isLocked) {
                unlockBody();
            } else if (!shouldLock && !isLocked) {
                if (document.body.style.overflow === 'hidden' ||
                    document.body.style.position === 'fixed' ||
                    document.body.style.top !== '') {
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.width = '';
                    document.body.style.top = '';
                    document.body.classList.remove('modal-open');
                }
            }

            if (typeof window.updateBottomNavVisibility === 'function') {
                window.updateBottomNavVisibility();
            }
        }

        window.__reconcileScrollLock = reconcile;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setInterval(reconcile, 60);
                reconcile();
            });
        } else {
            setInterval(reconcile, 60);
            reconcile();
        }

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) reconcile();
        });
    })();
</script>
<script>
    var currentPage = 'mydashboard';
    var pageCache = {};
    var preloadQueue = [];
    var isPreloading = false;
    var hasPreloaded = false;
    var isPageLoading = false;
    var loadingTimeout = null;

    function getCurrentPage() {
        var hash = window.location.hash.replace('#', '');
        return hash || 'mydashboard';
    }

    function loadPage(page, skipPreload) {
        if (page === currentPage && pageCache[page]) return;
        if (isPageLoading) return;
        
        currentPage = page;
        window.location.hash = page;
        
        document.querySelectorAll('.bottom-nav-item').forEach(function(item) {
            item.classList.remove('active');
            var href = item.getAttribute('href');
            if (href && href.indexOf(page) !== -1) {
                item.classList.add('active');
            }
        });
        
        document.body.classList.remove('page-connect_investor_broker', 'page-revenue_history');
        if (page === 'connect_investor_broker') {
            document.body.classList.add('page-connect_investor_broker');
        }
        if (page === 'revenue_history') {
            document.body.classList.add('page-revenue_history');
        }
        
        if (pageCache[page]) {
            document.getElementById('page-content').innerHTML = pageCache[page];
            reinitializePage(page);
            return;
        }
        
        showSpinner();
        document.getElementById('page-content').classList.add('loading');
        isPageLoading = true;
        
        fetchPageContent(page, function(html) {
            if (html) {
                pageCache[page] = html;
                document.getElementById('page-content').innerHTML = html;
                reinitializePage(page);
            }
            hideSpinner();
            document.getElementById('page-content').classList.remove('loading');
            isPageLoading = false;
        });
    }

    function fetchPageContent(page, callback) {
        fetch('loader.php?page=' + page)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var html = data.html;
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    
                    var mainContent = null;
                    var selectors = [
                        '.custom-body', '.page-container', '.dashboard-wrapper',
                        '.trades-container', '.analytics-container', '.menu-container',
                        '.activity-container', '.mydashboard-container',
                        '.connect-broker-container', '.account-manager-container',
                        '.activities-container'
                    ];
                    
                    for (var i = 0; i < selectors.length; i++) {
                        var el = temp.querySelector(selectors[i]);
                        if (el) { mainContent = el; break; }
                    }
                    
                    if (!mainContent) {
                        var bodyEl = temp.querySelector('body');
                        if (bodyEl) {
                            bodyEl.removeAttribute('style');
                            mainContent = bodyEl;
                        } else {
                            mainContent = temp;
                        }
                    }
                    
                    if (mainContent) {
                        var removeSelectors = ['.bottom-nav', '.notification-container', '.harvhub-header-top', '.notification-panel'];
                        for (var j = 0; j < removeSelectors.length; j++) {
                            var elements = mainContent.querySelectorAll(removeSelectors[j]);
                            elements.forEach(function(el) { el.remove(); });
                        }
                        
                        var bodyInside = mainContent.querySelector('body');
                        if (bodyInside) {
                            bodyInside.removeAttribute('style');
                            bodyInside.removeAttribute('class');
                        }
                        
                        var finalHtml = mainContent.innerHTML;
                        finalHtml = finalHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/g, '');
                        callback(finalHtml);
                    } else {
                        var cleanHtml = html;
                        cleanHtml = cleanHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/g, '');
                        cleanHtml = cleanHtml.replace(/<script[\s\S]*?<\/script>/g, '');
                        cleanHtml = cleanHtml.replace(/<body[^>]*>/i, '');
                        cleanHtml = cleanHtml.replace(/<\/body>/i, '');
                        cleanHtml = cleanHtml.replace(/<html[^>]*>/i, '');
                        cleanHtml = cleanHtml.replace(/<\/html>/i, '');
                        cleanHtml = cleanHtml.replace(/<head[^>]*>[\s\S]*?<\/head>/i, '');
                        cleanHtml = cleanHtml.replace(/<!DOCTYPE[^>]*>/i, '');
                        callback(cleanHtml.trim());
                    }
                } else {
                    callback(null);
                }
            })
            .catch(function(error) {
                callback(null);
            });
    }

    function preloadAllPages() {
        if (hasPreloaded) return;
        
        var currentPageName = getCurrentPage();
        var pagesToPreload = ['mydashboard', 'trades', 'analytics', 'menu', 'activity', 'connect_investor_broker', 'accountmanager', 'revenue_history'];
        
        var index = pagesToPreload.indexOf(currentPageName);
        if (index > -1) pagesToPreload.splice(index, 1);
        
        pagesToPreload.forEach(function(page) {
            if (!pageCache[page]) preloadQueue.push(page);
        });
        
        hasPreloaded = true;
        
        if (preloadQueue.length > 0) {
            showSpinner();
            setTimeout(processPreloadQueue, 300);
        }
    }

    function processPreloadQueue() {
        if (preloadQueue.length === 0 || isPreloading) {
            if (preloadQueue.length === 0 && !isPreloading) hideSpinner();
            return;
        }
        
        isPreloading = true;
        var page = preloadQueue.shift();
        
        fetchPageContent(page, function(html) {
            if (html) pageCache[page] = html;
            isPreloading = false;
            
            if (preloadQueue.length > 0) {
                setTimeout(processPreloadQueue, 200);
            } else {
                document.body.classList.add('all-pages-ready');
                setTimeout(hideSpinner, 300);
            }
        });
    }

    function navigateTo(page) {
        /* Leaving the profile page implicitly when navigating anywhere */
        document.body.classList.remove('profile-page-open');

        if (pageCache[page]) {
            currentPage = page;
            window.location.hash = page;
            
            document.querySelectorAll('.bottom-nav-item').forEach(function(item) {
                item.classList.remove('active');
                var href = item.getAttribute('href');
                if (href && href.indexOf(page) !== -1) {
                    item.classList.add('active');
                }
            });
            
            document.body.classList.remove('page-connect_investor_broker');
            if (page === 'connect_investor_broker') {
                document.body.classList.add('page-connect_investor_broker');
            }
            
            document.getElementById('page-content').innerHTML = pageCache[page];
            reinitializePage(page);
        } else {
            loadPage(page);
        }

        if (typeof window.updateBottomNavVisibility === 'function') {
            window.updateBottomNavVisibility();
        }
    }

    function updateBottomNavVisibility() {
        var bottomNav = document.querySelector('.bottom-nav');
        if (!bottomNav) return;

        if (document.body.classList.contains('page-connect_investor_broker')) return;

        /* NEW: hide nav while the profile page is open */
        if (document.body.classList.contains('profile-page-open')) {
            bottomNav.style.display = 'none';
            return;
        }

        var anyModalActive = document.querySelector('.modal.active, .modal-overlay.active');
        var spinnerActive = document.getElementById('spinnerOverlay');
        var spinnerOn = spinnerActive && spinnerActive.classList.contains('active');

        if (anyModalActive || spinnerOn) {
            bottomNav.style.display = 'none';
        } else {
            bottomNav.style.display = 'flex';
        }
    }

    function showSpinner() {
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
            loadingTimeout = null;
        }
        var overlay = document.getElementById('spinnerOverlay');
        if (overlay) {
            overlay.classList.add('active');
            if (typeof window.__reconcileScrollLock === 'function') {
                window.__reconcileScrollLock();
            }
        }
    }

    function hideSpinner() {
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
            loadingTimeout = null;
        }
        loadingTimeout = setTimeout(function() {
            var overlay = document.getElementById('spinnerOverlay');
            if (overlay) {
                overlay.classList.remove('active');
            }
            if (typeof window.__reconcileScrollLock === 'function') {
                window.__reconcileScrollLock();
            }
            loadingTimeout = null;
        }, 200);
    }

    window.addEventListener('beforeunload', function() {
        showSpinner();
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            if (document.readyState === 'complete' || document.readyState === 'interactive') {
                if (!isPreloading && preloadQueue.length === 0) {
                    hideSpinner();
                }
            }
        }, 100);
    });

    document.onreadystatechange = function() {
        if (document.readyState === 'complete') {
            if (!isPreloading && preloadQueue.length === 0) {
                setTimeout(hideSpinner, 300);
            }
        }
    };

    function openSessionInfoModal() {
        var modal = document.getElementById('sessionInfoModal');
        if (modal) {
            modal.classList.add('active');
            if (typeof window.__reconcileScrollLock === 'function') {
                window.__reconcileScrollLock();
            }
        }
    }

    function closeSessionInfoModal() {
        var modal = document.getElementById('sessionInfoModal');
        if (modal) {
            modal.classList.remove('active');
        }
        if (typeof window.__reconcileScrollLock === 'function') {
            window.__reconcileScrollLock();
        }
    }

    function toggleDarkMode(checkbox) {
        var isChecked = checkbox.checked ? 1 : 0;
        showSpinner();
        
        fetch('app.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'toggle_dark_mode_ajax=1&dark_mode_checkbox=' + isChecked
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                if (data.dark_mode === 1) {
                    document.body.classList.add('dark-mode');
                } else {
                    document.body.classList.remove('dark-mode');
                }
                updateMenuUI(data.dark_mode);
            }
            hideSpinner();
        })
        .catch(function(error) {
            checkbox.checked = !checkbox.checked;
            hideSpinner();
        });
    }

    function updateMenuUI(darkMode) {
        var pageContent = document.getElementById('page-content');
        if (pageContent) {
            var menuContainer = pageContent.querySelector('.menu-container');
            if (menuContainer) {
                var toggleCheckbox = menuContainer.querySelector('#darkModeToggle');
                if (toggleCheckbox) toggleCheckbox.checked = (darkMode === 1);
                var iconSpan = menuContainer.querySelector('.dark-mode-icon');
                if (iconSpan) iconSpan.textContent = (darkMode === 1) ? '🌙' : '☀️';
                var descSpan = menuContainer.querySelector('.item-desc');
                if (descSpan) descSpan.textContent = (darkMode === 1) ? 'Currently enabled' : 'Currently disabled';
                var modeLabel = menuContainer.querySelector('.mode-label');
                if (modeLabel) modeLabel.textContent = (darkMode === 1) ? 'On' : 'Off';
            }
        }
    }

    function navigateToConnectBroker() {
        navigateTo('connect_investor_broker');
    }

    function connectBroker(formData) {
        showSpinner();
        
        fetch('app.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'connect_broker_ajax=1&broker=' + encodeURIComponent(formData.broker) + 
                  '&server=' + encodeURIComponent(formData.server) + 
                  '&login=' + encodeURIComponent(formData.login) + 
                  '&broker_password=' + encodeURIComponent(formData.password)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                var successDiv = document.getElementById('brokerSuccess');
                if (successDiv) {
                    successDiv.style.display = 'block';
                    successDiv.textContent = data.message || 'Broker connected successfully!';
                }
                var submitBtn = document.getElementById('connectBrokerBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '✓ Success!';
                    submitBtn.style.background = '#28a745';
                    submitBtn.style.color = '#fff';
                }
                setTimeout(function() {
                    window.location.href = 'app.php#mydashboard';
                    window.location.reload(true);
                }, 1500);
            } else {
                var errorDiv = document.getElementById('brokerError');
                if (errorDiv) {
                    errorDiv.style.display = 'block';
                    errorDiv.textContent = data.errors ? data.errors.join(', ') : 'Connection failed. Please try again.';
                }
                var submitBtn = document.getElementById('connectBrokerBtn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Try Again';
                }
            }
            hideSpinner();
        })
        .catch(function(error) {
            var errorDiv = document.getElementById('brokerError');
            if (errorDiv) {
                errorDiv.style.display = 'block';
                errorDiv.textContent = 'Network error: ' + error.message;
            }
            var submitBtn = document.getElementById('connectBrokerBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Try Again';
            }
            hideSpinner();
        });
    }

    /* ====================================================================
       PROFILE PAGE SWITCH — global handlers
       (menu.php's <script> is stripped by loader.php, so these MUST live
       here in the SPA shell to survive SPA navigation.)
       ==================================================================== */
    window.showProfilePage = function() {
        var menuPage = document.getElementById('menuPage');
        var profilePage = document.getElementById('profilePage');
        if (menuPage) menuPage.classList.add('hidden');
        if (profilePage) profilePage.classList.add('active');
        document.body.classList.add('profile-page-open');
        window.scrollTo(0, 0);
        if (typeof window.updateBottomNavVisibility === 'function') {
            window.updateBottomNavVisibility();
        }
    };

    window.hideProfilePage = function() {
        var profilePage = document.getElementById('profilePage');
        var menuPage = document.getElementById('menuPage');
        if (profilePage) profilePage.classList.remove('active');
        if (menuPage) menuPage.classList.remove('hidden');
        document.body.classList.remove('profile-page-open');
        window.scrollTo(0, 0);
        if (typeof window.updateBottomNavVisibility === 'function') {
            window.updateBottomNavVisibility();
        }
    };

    function reinitializePage(page) {
        var scripts = document.querySelectorAll('#page-content script');
        scripts.forEach(function(script) {
            var scriptContent = script.textContent || script.src;
            if (scriptContent && scriptContent.length > 10) {
                var existingScripts = document.querySelectorAll('body > script');
                var isDuplicate = false;
                existingScripts.forEach(function(existing) {
                    if (existing.textContent === scriptContent && existing.textContent.length > 20) {
                        isDuplicate = true;
                    }
                });
                if (!isDuplicate) {
                    var newScript = document.createElement('script');
                    if (script.src) {
                        newScript.src = script.src;
                    } else {
                        newScript.textContent = script.textContent;
                    }
                    document.body.appendChild(newScript);
                }
            }
        });
        
        document.querySelectorAll('.modal .close-btn, .modal-actions button[onclick*="remove"]').forEach(function(btn) {
            btn.onclick = function(e) {
                var modal = this.closest('.modal, .modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                    if (modal.classList.contains('modal-overlay')) {
                        modal.style.display = 'none';
                    }
                }
            };
        });
        
        document.querySelectorAll('.modal, .modal-overlay').forEach(function(modal) {
            modal.onclick = function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    if (this.classList.contains('modal-overlay')) {
                        this.style.display = 'none';
                    }
                }
            };
        });
        
        if (page === 'connect_investor_broker') {
            var passwordToggle = document.querySelector('#page-content .password-toggle');
            if (passwordToggle) {
                passwordToggle.onclick = function(e) {
                    e.preventDefault();
                    var input = document.getElementById('password_input');
                    if (input) {
                        if (input.type === 'password') {
                            input.type = 'text';
                            this.textContent = 'Hide';
                        } else {
                            input.type = 'password';
                            this.textContent = 'Show';
                        }
                    }
                };
            }
            
            var showExistingBtn = document.querySelector('#page-content .btn-secondary');
            if (showExistingBtn) {
                showExistingBtn.onclick = function(e) {
                    e.preventDefault();
                    showConnectForm();
                };
            }
            
            var backLink = document.querySelector('#page-content .back-link-top a');
            if (backLink) {
                backLink.onclick = function(e) {
                    e.preventDefault();
                    navigateTo('mydashboard');
                };
            }
            
            var connectForm = document.getElementById('connectBrokerForm');
            if (connectForm) {
                var newForm = connectForm.cloneNode(true);
                connectForm.parentNode.replaceChild(newForm, connectForm);
                connectForm = newForm;
                
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
                    
                    if (typeof window.connectBroker === 'function') {
                        window.connectBroker({
                            broker: broker,
                            server: server,
                            login: login,
                            password: password
                        });
                    }
                });
            }
            
            var allButtons = document.querySelectorAll('#page-content button, #page-content a.btn-broker-link, #page-content .btn-submit');
            allButtons.forEach(function(btn) {
                btn.style.pointerEvents = 'auto';
                btn.style.cursor = 'pointer';
            });
        }
        
        if (page === 'accountmanager') {
            document.querySelectorAll('.btn-invest').forEach(function(btn) {
                if (!btn.disabled) {
                    btn.addEventListener('click', function(e) {
                    });
                }
            });
        }
        
        if (page === 'activity') {
            document.querySelectorAll('.log-header').forEach(function(header) {
                header.onclick = function(e) {
                    var details = this.nextElementSibling;
                    var toggle = this.querySelector('.log-toggle');
                    if (details) {
                        if (details.classList.contains('open')) {
                            details.classList.remove('open');
                            if (toggle) toggle.textContent = '▼';
                        } else {
                            details.classList.add('open');
                            if (toggle) toggle.textContent = '▲';
                        }
                    }
                };
            });
        }
        
        if (page === 'menu') {
            var darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                var newToggle = darkModeToggle.cloneNode(true);
                darkModeToggle.parentNode.replaceChild(newToggle, darkModeToggle);
                newToggle.addEventListener('change', function() {
                    toggleDarkMode(this);
                });
            }

            /* ---- Wire up the clickable profile card (page switch) ---- */
            var profileCard = document.querySelector('#page-content .profile-clickable');
            if (profileCard) {
                var newProfileCard = profileCard.cloneNode(true);
                profileCard.parentNode.replaceChild(newProfileCard, profileCard);
                profileCard = newProfileCard;

                profileCard.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.showProfilePage();
                });
                profileCard.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        window.showProfilePage();
                    }
                });
            }

            /* ---- Wire up the profile page back button ---- */
            var profileBackBtn = document.querySelector('#page-content .profile-page-close');
            if (profileBackBtn) {
                profileBackBtn.onclick = function(e) {
                    e.preventDefault();
                    window.hideProfilePage();
                };
            }

            /* ---- ESC goes back from profile page ---- */
            if (!window.__profileEscBound) {
                window.__profileEscBound = true;
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        var profilePage = document.getElementById('profilePage');
                        if (profilePage && profilePage.classList.contains('active')) {
                            window.hideProfilePage();
                        }
                    }
                });
            }
        }
        
        ['applyModal', 'resetModal', 'reenrollModal', 'revenueHistoryModal'].forEach(function(id) {
            var modal = document.getElementById(id);
            if (modal) {
                modal.onclick = function(e) {
                    if (e.target === this) {
                        var closeFn = window['close' + id.charAt(0).toUpperCase() + id.slice(1)];
                        if (typeof closeFn === 'function') closeFn();
                    }
                };
            }
        });
        
        document.querySelectorAll('.revenue-header').forEach(function(header) {
            header.onclick = function(e) {
                var details = this.nextElementSibling;
                var revenueItem = this.closest('.revenue-item');
                if (details) {
                    if (details.classList.contains('active')) {
                        details.classList.remove('active');
                        if (revenueItem) revenueItem.style.width = '100%';
                    } else {
                        document.querySelectorAll('.revenue-details.active').forEach(function(d) {
                            d.classList.remove('active');
                        });
                        document.querySelectorAll('.revenue-item').forEach(function(item) {
                            item.style.width = '100%';
                        });
                        details.classList.add('active');
                        if (revenueItem) {
                            var originalWidth = revenueItem.offsetWidth;
                            revenueItem.style.width = originalWidth + 'px';
                            setTimeout(function() {
                                revenueItem.style.width = '100%';
                            }, 10);
                        }
                    }
                }
            };
        });
        
        var revenueBtn = document.querySelector('#page-content .btn-revenue-history');
        if (revenueBtn) {
            var newRevenueBtn = revenueBtn.cloneNode(true);
            revenueBtn.parentNode.replaceChild(newRevenueBtn, revenueBtn);
            revenueBtn = newRevenueBtn;
            
            revenueBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                navigateTo('revenue_history');
            };
        }
        
        if (typeof renderChartBars === 'function') {
            renderChartBars();
        } else if (typeof window.renderChartBars === 'function') {
            window.renderChartBars();
        }
        
        ['paymentModal', 'paymentFailedModal', 'finalConfirmationModal', 'profitSplitModal', 'finalDisconnectModal'].forEach(function(id) {
            var modal = document.getElementById(id);
            if (modal) {
                modal.onclick = function(e) {
                    if (e.target === this) this.classList.remove('active');
                };
            }
        });
        
        var paymentCheck = document.getElementById('paymentConfirmationCheck');
        if (paymentCheck) {
            paymentCheck.onchange = function() {
                var btn = document.getElementById('confirmPaidBtn');
                if (btn) btn.disabled = !this.checked;
            };
        }
        
        var copyBtn = document.getElementById('copyAddressBtn');
        if (copyBtn) {
            copyBtn.onclick = function() {
                var addressEl = document.getElementById('paymentAddress');
                if (addressEl && navigator.clipboard) {
                    navigator.clipboard.writeText(addressEl.textContent).then(function() {
                        alert('Payment address copied to clipboard!');
                    }).catch(function(err) {
                    });
                }
            };
        }
        
        var reenrollCheck = document.getElementById('reenrollConfirmCheck');
        if (reenrollCheck) {
            reenrollCheck.onchange = function() {
                var btn = document.getElementById('reenrollProceedBtn');
                if (btn) {
                    btn.disabled = !this.checked;
                    if (this.checked) {
                        btn.style.background = '#0080bc';
                        btn.style.color = '#000';
                        btn.style.cursor = 'pointer';
                        btn.style.opacity = '1';
                    } else {
                        btn.style.background = '#555';
                        btn.style.color = '#999';
                        btn.style.cursor = 'not-allowed';
                        btn.style.opacity = '0.6';
                    }
                }
            };
        }
        
        document.querySelectorAll('input[name="coin"]').forEach(function(radio) {
            radio.onchange = function() {
                var coin = this.value;
                var serverAccounts = window.serverAccounts || {};
                var data = serverAccounts[coin];
                if (data) {
                    var addressEl = document.getElementById('paymentAddress');
                    var networkEl = document.getElementById('paymentNetwork');
                    if (addressEl) addressEl.textContent = data.address;
                    if (networkEl) networkEl.textContent = data.network;
                }
            };
        });
        
        updateBottomNavVisibility();
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

    function openApplyModal() {
        var modal = document.getElementById('applyModal');
        if (modal) modal.classList.add('active');
    }

    function closeApplyModal() {
        var modal = document.getElementById('applyModal');
        if (modal) modal.classList.remove('active');
    }

    function openResetModal() {
        var modal = document.getElementById('resetModal');
        if (modal) modal.classList.add('active');
    }

    function closeResetModal() {
        var modal = document.getElementById('resetModal');
        if (modal) modal.classList.remove('active');
    }

    function openReenrollModal() {
        var check = document.getElementById('reenrollConfirmCheck');
        var btn = document.getElementById('reenrollProceedBtn');
        if (check) check.checked = false;
        if (btn) btn.disabled = true;
        var modal = document.getElementById('reenrollModal');
        if (modal) modal.classList.add('active');
    }

    function closeReenrollModal() {
        var modal = document.getElementById('reenrollModal');
        if (modal) modal.classList.remove('active');
    }

    function proceedToEnrollment() {
        closeReenrollModal();
        var form = document.getElementById('reenrollForm');
        if (form) {
            form.action = 'mydashboard.php';
            form.submit();
        }
    }

    function openRevenueHistoryModal() {
        navigateTo('revenue_history');
    }

    function closeRevenueHistoryModal() {
        var modal = document.getElementById('revenueHistoryModal');
        if (modal) modal.classList.remove('active');
    }

    function loadRevenueHistory() {
        var container = document.getElementById('revenueHistoryContainer');
        if (!container) return;
        
        container.innerHTML = '<div class="empty-revenue">Loading...</div>';
        var historyData = typeof revenueHistoryData !== 'undefined' ? revenueHistoryData : [];
        var history = [];
        
        if (historyData) {
            if (typeof historyData === 'string') {
                try { historyData = JSON.parse(historyData); } catch(e) { historyData = []; }
            }
            if (Array.isArray(historyData)) {
                history = historyData;
            } else if (historyData && typeof historyData === 'object') {
                for (var key in historyData) {
                    if (historyData.hasOwnProperty(key)) history.push(historyData[key]);
                }
            }
        }
        
        if (history && history.length > 0) {
            history.sort(function(a, b) {
                var idA = parseInt(a.id) || 0;
                var idB = parseInt(b.id) || 0;
                return idB - idA;
            });
            
            var html = '';
            history.forEach(function(record) {
                var statusClass = getStatusClass(record.loyalties);
                var statusText = getStatusText(record.loyalties);
                var profitClass = record.profit >= 0 ? 'profit-positive' : 'profit-negative';
                var totalRevenue = record.profit < 0 ? record.profit : ((record.server_share || 0) + (record.user_share || 0));
                var isActiveContract = (record.loyalties === 'active');
                var contractId = record.contract_id || 'N/A';
                
                var statusMessage = '';
                if (record.loyalties === 'pending_payment') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + statusText + '</span>';
                } else if (record.loyalties === 'payment-made') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + statusText + ' - Under Review</span>';
                } else if (record.loyalties === 'payment-confirmed') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> Payment Confirmed</span>';
                } else if (record.loyalties === 'completed') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + statusText + '</span>';
                } else if (record.loyalties === 'loss_completed') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + statusText + '</span>';
                } else if (record.loyalties === 'below_threshold') {
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + statusText + ' (No Split Required)</span>';
                } else if (record.loyalties && record.loyalties.includes('contract_cancelled')) {
                    var displayText = 'Contract Cancelled';
                    if (record.loyalties.includes('payment-confirmed')) displayText = 'Cancelled (Payment Confirmed)';
                    else if (record.loyalties.includes('payment-made')) displayText = 'Cancelled (Payment Made)';
                    else if (record.loyalties.includes('unpaid-payment')) displayText = 'Cancelled (Unpaid)';
                    else if (record.loyalties.includes('inloss')) displayText = 'Cancelled (In Loss)';
                    else if (record.loyalties.includes('below-threshold')) displayText = 'Cancelled (Below Threshold)';
                    statusMessage = '<span class="revenue-status ' + statusClass + '"> ' + displayText + '</span>';
                } else if (record.loyalties === 'active') {
                    statusMessage = '<span class="revenue-status active"> Active Contract</span>';
                } else {
                    statusMessage = '<span class="revenue-status ' + statusClass + '">' + statusText + '</span>';
                }
                
                if (isActiveContract) {
                    var endDate = new Date(record.execution_end_date);
                    var daysRemaining = Math.ceil((endDate - new Date()) / (1000 * 60 * 60 * 24));
                    html += `
                        <div class="revenue-item active-contract-simplified" data-id="${record.id || Math.random()}">
                            <div class="revenue-header" onclick="toggleRevenueDetails(this)">
                                <div class="revenue-header-left">
                                    <div class="revenue-user-share" style="color: var(--success); font-weight: bold; font-size: 0.9rem;">
                                        Next Revenue Harvest
                                    </div>
                                    <div class="revenue-date-range" style="color: var(--text-muted); font-weight: 600;">
                                        ${formatDateSimple(endDate)}
                                    </div>
                                </div>
                            </div>
                            <div class="revenue-details">
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Contract ID:</span>
                                    <span class="revenue-detail-value" style="font-size: 10px; font-family: monospace;">${escapeHtml(contractId)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Invested:</span>
                                    <span class="revenue-detail-value">$${formatNumber(record.starting_balance)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Days Remaining:</span>
                                    <span class="revenue-detail-value">${daysRemaining > 0 ? daysRemaining : 0} days left</span>
                                </div>
                                <div class="revenue-detail-row" style="border-bottom: none;">
                                    <span class="revenue-detail-label">Harvest Date:</span>
                                    <span class="revenue-detail-value">${formatDateSimple(endDate)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="revenue-item" data-id="${record.id || Math.random()}">
                            <div class="revenue-header" onclick="toggleRevenueDetails(this)">
                                <div style="display: flex; align-items: stretch; width: 100%; background: transparent !important;">
                                    <div class="revenue-icon-wrapper" style="background: transparent !important;">
                                        <span class="revenue-icon" style="background: transparent !important;">💰</span>
                                    </div>
                                    <div class="revenue-header-left" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 8px;">
                                            <div class="revenue-user-share">$${formatNumber(record.user_share)}</div>
                                        </div>
                                        <div class="revenue-date-range">${formatDateRange(record.execution_start_date, record.execution_end_date)}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="revenue-details">
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Contract ID:</span>
                                    <span class="revenue-detail-value" style="font-size: 10px; font-family: monospace;">${escapeHtml(contractId)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Invested:</span>
                                    <span class="revenue-detail-value">$${formatNumber(record.starting_balance)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Harvest (Your Share):</span>
                                    <span class="revenue-detail-value ${profitClass}">$${formatNumber(record.user_share)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Server Share:</span>
                                    <span class="revenue-detail-value">$${formatNumber(record.server_share)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Total Revenue:</span>
                                    <span class="revenue-detail-value ${profitClass}">$${formatNumber(totalRevenue)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Final Balance:</span>
                                    <span class="revenue-detail-value">$${formatNumber(record.current_balance)}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-label">Programme:</span>
                                    <span class="revenue-detail-value invested_with-value">${escapeHtml(record.invested_with || 'N/A')}</span>
                                </div>
                                <div class="revenue-detail-row">
                                    <span class="revenue-detail-value">${statusMessage}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-revenue">No revenue history yet. Complete a contract to see your revenue records.</div>';
        }
    }

    function getStatusClass(status) {
        switch(status) {
            case 'active': return 'active';
            case 'completed': return 'completed';
            case 'pending_payment': return 'pending';
            case 'loss_completed': return 'loss';
            case 'below_threshold': return 'completed';
            case 'payment-made': return 'pending';
            case 'payment-confirmed': return 'completed';
            default: return 'pending';
        }
    }

    function getStatusText(status) {
        switch(status) {
            case 'active': return 'Active Contract';
            case 'completed': return 'Completed';
            case 'pending_payment': return 'Payment Required';
            case 'loss_completed': return 'Contract ended in loss';
            case 'below_threshold': return 'Completed (Below Threshold)';
            case 'payment-made': return 'Payment Submitted';
            case 'payment-confirmed': return 'Payment Confirmed';
            default: return status || 'Recorded';
        }
    }

    function formatDateRange(startDate, endDate) {
        if (!startDate || startDate === '0000-00-00') return 'Date not set';
        var start = new Date(startDate);
        var end = new Date(endDate);
        return start.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) + ' - ' + end.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function formatNumber(value) {
        return parseFloat(value).toFixed(2);
    }

    function formatDateSimple(date) {
        if (!date) return 'Date not set';
        try {
            var d = new Date(date);
            if (isNaN(d.getTime())) return 'Invalid date';
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } catch(e) {
            return 'Invalid date';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function toggleRevenueDetails(headerElement) {
        var detailsDiv = headerElement.nextElementSibling;
        var revenueItem = headerElement.closest('.revenue-item');
        if (!detailsDiv) return;
        if (detailsDiv.classList.contains('active')) {
            detailsDiv.classList.remove('active');
            if (revenueItem) revenueItem.style.width = '100%';
        } else {
            document.querySelectorAll('.revenue-details.active').forEach(function(d) {
                d.classList.remove('active');
            });
            document.querySelectorAll('.revenue-item').forEach(function(item) {
                item.style.width = '100%';
            });
            detailsDiv.classList.add('active');
            if (revenueItem) {
                var originalWidth = revenueItem.offsetWidth;
                revenueItem.style.width = originalWidth + 'px';
                setTimeout(function() {
                    revenueItem.style.width = '100%';
                }, 10);
            }
        }
    }

    function getSelectedCoin() {
        var checked = document.querySelector('input[name="coin"]:checked');
        return checked ? checked.value : 'btc';
    }

    function togglePaidButton() {
        var check = document.getElementById('paymentConfirmationCheck');
        var btn = document.getElementById('confirmPaidBtn');
        if (check && btn) btn.disabled = !check.checked;
    }

    function triggerFinalConfirmation() {
        var selectedCoin = getSelectedCoin();
        var amount = document.getElementById('serverShareAmountHidden');
        if (amount) {
            var finalAmount = document.getElementById('finalConfirmAmount');
            var finalCoin = document.getElementById('finalConfirmCoin');
            var formAmount = document.getElementById('formServerShareAmount');
            var formCoin = document.getElementById('formPaymentCoin');
            if (finalAmount) finalAmount.innerHTML = '$' + parseFloat(amount.value).toFixed(2);
            if (finalCoin) finalCoin.innerHTML = selectedCoin.toUpperCase();
            if (formAmount) formAmount.value = amount.value;
            if (formCoin) formCoin.value = selectedCoin;
            var paymentModal = document.getElementById('paymentModal');
            var confirmationModal = document.getElementById('finalConfirmationModal');
            if (paymentModal) paymentModal.classList.remove('active');
            if (confirmationModal) confirmationModal.classList.add('active');
        }
    }

    function openApplySuccessModal(message, details) {
        var modal = document.getElementById('applySuccessModal');
        var msgEl = document.getElementById('applySuccessMessage');
        var detailsEl = document.getElementById('applySuccessDetails');
        if (msgEl) msgEl.textContent = message || 'Your application has been submitted successfully!';
        if (detailsEl) detailsEl.textContent = details || 'Our team will verify your account.';
        if (modal) {
            modal.classList.add('active');
            setTimeout(function() {
                closeApplySuccessModal();
            }, 6000);
        }
    }

    function closeApplySuccessModal() {
        var modal = document.getElementById('applySuccessModal');
        if (modal) modal.classList.remove('active');
    }

    function toggleLogDetails(headerElement) {
        var details = headerElement.nextElementSibling;
        var toggle = headerElement.querySelector('.log-toggle');
        if (details) {
            if (details.classList.contains('open')) {
                details.classList.remove('open');
                if (toggle) toggle.textContent = '▼';
            } else {
                details.classList.add('open');
                if (toggle) toggle.textContent = '▲';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var initialPage = getCurrentPage();
        currentPage = initialPage;
        
        if (initialPage === 'connect_investor_broker') {
            document.body.classList.add('page-connect_investor_broker');
        }
        
        showSpinner();
        loadPage(initialPage, true);
        
        if (document.readyState === 'complete') {
            setTimeout(preloadAllPages, 500);
        } else {
            window.addEventListener('load', function() {
                setTimeout(preloadAllPages, 500);
            });
        }
        
        window.addEventListener('hashchange', function() {
            var page = getCurrentPage();
            document.body.classList.remove('page-connect_investor_broker', 'page-revenue_history', 'profile-page-open');
            if (page === 'connect_investor_broker') {
                document.body.classList.add('page-connect_investor_broker');
            }
            if (page === 'revenue_history') {
                document.body.classList.add('page-revenue_history');
            }
            navigateTo(page);
        });

        if (window.history.replaceState) {
            var url = window.location.href;
            var cleanUrl = url.replace(/([?&])(show_enroll_success|show_apply_success|show_reset_success|show_toggle_success)=1(&|$)/g, function(match, p1, p2, p3) {
                return p3 === '&' ? p1 : '';
            }).replace(/[?&]$/, '').replace(/\?&/, '?');
            if (cleanUrl !== url) {
                window.history.replaceState(null, null, cleanUrl);
            }
        }

        var sessionModal = document.getElementById('sessionInfoModal');
        if (sessionModal) {
            setTimeout(function() {
                openSessionInfoModal();
            }, 400);
        }
    });

    window.navigateTo = navigateTo;
    window.loadPage = loadPage;
    window.navigateToConnectBroker = navigateToConnectBroker;
    window.showConnectForm = showConnectForm;
    window.connectBroker = connectBroker;
    window.openApplyModal = openApplyModal;
    window.closeApplyModal = closeApplyModal;
    window.openResetModal = openResetModal;
    window.closeResetModal = closeResetModal;
    window.openReenrollModal = openReenrollModal;
    window.closeReenrollModal = closeReenrollModal;
    window.proceedToEnrollment = proceedToEnrollment;
    window.openRevenueHistoryModal = openRevenueHistoryModal;
    window.closeRevenueHistoryModal = closeRevenueHistoryModal;
    window.loadRevenueHistory = loadRevenueHistory;
    window.getStatusClass = getStatusClass;
    window.getStatusText = getStatusText;
    window.formatDateRange = formatDateRange;
    window.formatNumber = formatNumber;
    window.formatDateSimple = formatDateSimple;
    window.escapeHtml = escapeHtml;
    window.toggleRevenueDetails = toggleRevenueDetails;
    window.getSelectedCoin = getSelectedCoin;
    window.togglePaidButton = togglePaidButton;
    window.triggerFinalConfirmation = triggerFinalConfirmation;
    window.openApplySuccessModal = openApplySuccessModal;
    window.closeApplySuccessModal = closeApplySuccessModal;
    window.toggleLogDetails = toggleLogDetails;
    window.toggleDarkMode = toggleDarkMode;
    window.updateMenuUI = updateMenuUI;
    window.updateBottomNavVisibility = updateBottomNavVisibility;
    window.openSessionInfoModal = openSessionInfoModal;
    window.closeSessionInfoModal = closeSessionInfoModal;

    // ==================== LOGOUT MODAL (global) ====================
    window.openLogoutModal = function() {
        var modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.add('active');
        }
    };

    window.closeLogoutModal = function() {
        var modal = document.getElementById('logoutModal');
        if (modal) {
            modal.classList.remove('active');
        }
    };

    window.confirmLogout = function() {
        window.closeLogoutModal();
        window.location.href = '?logout=1';
    };

    // Overlay background click closes modals
    document.addEventListener('click', function(event) {
        if (!event.target) return;
        if (event.target.id === 'logoutModal') {
            window.closeLogoutModal();
        }
    });
</script>
<script>
    var bottomNav = document.querySelector('.bottom-nav');
    var lastScrollTop = 0;
    var scrollThreshold = 1;
    var isNavHidden = false;
    var scrollTimeout = null;
    var isAtBottom = false;
    
    function handleScroll() {
        if (!bottomNav) return;
        
        if (document.body.classList.contains('page-connect_investor_broker')) {
            bottomNav.style.display = 'none';
            return;
        }

        /* NEW: keep nav hidden while the profile page is open */
        if (document.body.classList.contains('profile-page-open')) {
            bottomNav.style.display = 'none';
            return;
        }

        if (!document.querySelector('.modal.active, .modal-overlay.active') &&
            !(document.getElementById('spinnerOverlay') &&
              document.getElementById('spinnerOverlay').classList.contains('active'))) {
            bottomNav.style.display = 'flex';
        }
        
        var currentScroll = window.pageYOffset || document.documentElement.scrollTop;
        var docHeight = document.documentElement.scrollHeight;
        var windowHeight = window.innerHeight;
        var isBottomReached = (currentScroll + windowHeight >= docHeight - 5);
        
        if (currentScroll > lastScrollTop + scrollThreshold) {
            if (!isNavHidden && !isBottomReached) {
                bottomNav.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                bottomNav.style.transform = 'translateX(-50%) translateY(100px)';
                bottomNav.style.opacity = '0';
                bottomNav.style.pointerEvents = 'none';
                isNavHidden = true;
            }
        } else if (currentScroll < lastScrollTop - scrollThreshold) {
            if (isNavHidden) {
                bottomNav.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                bottomNav.style.transform = 'translateX(-50%) translateY(0)';
                bottomNav.style.opacity = '1';
                bottomNav.style.pointerEvents = 'all';
                isNavHidden = false;
            }
        }
        
        if (currentScroll <= 0) {
            bottomNav.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            bottomNav.style.transform = 'translateX(-50%) translateY(0)';
            bottomNav.style.opacity = '1';
            bottomNav.style.pointerEvents = 'all';
            isNavHidden = false;
            isAtBottom = false;
        }
        
        if (isBottomReached) {
            if (isNavHidden) {
                bottomNav.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
                bottomNav.style.transform = 'translateX(-50%) translateY(0)';
                bottomNav.style.opacity = '1';
                bottomNav.style.pointerEvents = 'all';
                isNavHidden = false;
            }
            isAtBottom = true;
        } else {
            isAtBottom = false;
        }
        
        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    }
    
    function throttledHandleScroll() {
        if (scrollTimeout) cancelAnimationFrame(scrollTimeout);
        scrollTimeout = requestAnimationFrame(handleScroll);
    }
    
    var resizeTimeout = null;
    function handleResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
        }, 200);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        if (bottomNav) {
            bottomNav.style.transition = 'transform 0.3s ease, opacity 0.3s ease';
            bottomNav.style.transform = 'translateX(-50%) translateY(0)';
            bottomNav.style.opacity = '1';
            bottomNav.style.pointerEvents = 'all';
        }
        
        window.addEventListener('scroll', throttledHandleScroll);
        window.addEventListener('resize', handleResize);
    });
    
    window.addEventListener('beforeunload', function() {
        window.removeEventListener('scroll', throttledHandleScroll);
        window.removeEventListener('resize', handleResize);
    });
</script>
</body>
</html>