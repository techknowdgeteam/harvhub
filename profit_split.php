<?php
    session_start();

    // ==================== CHECK LOGIN ====================
    if (!isset($_SESSION['user_email'])) {
        header("Location: index.php");
        exit;
    }

    $email  = strtolower($_SESSION['user_email']);

    $host   = "sql312.infinityfree.com";
    $dbname = "if0_40473107_harvhub";
    $user   = "if0_40473107";
    $pass   = "InDQmdl53FZ85";

    $tableName              = "harvhub";
    $serverAccountTable     = "server_account";
    $revenueHistoryTable    = "revenue_history";
    $programmeInvestorsTable= "programme_investors";
    $programmeTable         = "programme";

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

    // ==================== FETCH USER ====================
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    $userId = (int)$user['id'];

    // ==================== FETCH SERVER ACCOUNT (fallbacks) ====================
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$serverAccount) {
        die("Server configuration not found.");
    }

    $darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
    $darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

    // ==================== DEFAULTS FROM SERVER ACCOUNT ====================
    $SERVER_SHARE_PERCENT = (int)($serverAccount['server_share_percent'] ?? 30);
    $USER_SHARE_PERCENT   = (int)($serverAccount['user_share_percent'] ?? 70);
    $MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);

    // ==================== FIND USER'S ACTIVE PROGRAMME INVESTMENT ====================
    // The programme the user invested in has its own developer/investor split
    // and its own minimum profit threshold. These override the server defaults.
    $activeInvestment = null;
    $investmentProgramme = null;
    $investmentDeveloper = null;

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM $programmeInvestorsTable
            WHERE investorid = ?
            ORDER BY invested_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $activeInvestment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($activeInvestment) {
            if (!empty($activeInvestment['programme_id'])) {
                $stmtP = $pdo->prepare("SELECT * FROM $programmeTable WHERE id = ? LIMIT 1");
                $stmtP->execute([(int)$activeInvestment['programme_id']]);
                $investmentProgramme = $stmtP->fetch(PDO::FETCH_ASSOC);
            }

            if (!empty($activeInvestment['developerid'])) {
                $stmtD = $pdo->prepare("SELECT id, fullname, email FROM $tableName WHERE id = ? LIMIT 1");
                $stmtD->execute([(int)$activeInvestment['developerid']]);
                $investmentDeveloper = $stmtD->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $activeInvestment = null;
    }

    $hasProgramme = ($activeInvestment && !empty($activeInvestment['programme_id']));

    // ==================== DERIVED SPLIT VALUES ====================
    // Programme-level split takes priority. Server account is the fallback.
    $PROGRAMME_NAME = '';
    $DEVELOPER_NAME = '';

    if ($hasProgramme) {
        $pi_dev = (int)($activeInvestment['developer_percentage'] ?? 0);
        $pi_inv = (int)($activeInvestment['investor_percentage'] ?? 0);

        if ($pi_dev > 0) $SERVER_SHARE_PERCENT = $pi_dev;
        if ($pi_inv > 0) $USER_SHARE_PERCENT   = $pi_inv;

        if ($investmentProgramme && !empty($investmentProgramme['program_name'])) {
            $PROGRAMME_NAME = $investmentProgramme['program_name'];
        }
        if ($investmentDeveloper && !empty($investmentDeveloper['fullname'])) {
            $DEVELOPER_NAME = $investmentDeveloper['fullname'];
        }
    }

    // ==================== USER FINANCIAL STATE ====================
    $brokerBalance   = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss   = (float)($user['profitandloss'] ?? 0);
    $loyaltiesStatus = $user['loyalties'] ?? null;

    $profitToSplit = max(0, $profitAndLoss);
    $serverShare   = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
    $userShare     = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);

    // ==================== LATEST REVENUE HISTORY ====================
    $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $latestRevenue = $stmt->fetch(PDO::FETCH_ASSOC);

    $latestRevenueLoyalty = $latestRevenue['loyalties'] ?? null;

    // Determine if this is a retry
    $isRetry = isset($_GET['retry']) && $_GET['retry'] == 1;

    // ==================== DETERMINE STATUS ====================
    $isUnpaid      = ($loyaltiesStatus === 'unpaid-payment' || $loyaltiesStatus === 'unpaid');
    $isFailed      = ($loyaltiesStatus === 'payment-failed' || $loyaltiesStatus === 'failed-payment');
    $isPaymentMade = ($loyaltiesStatus === 'payment-made');

    if ($loyaltiesStatus === null) {
        $isUnpaid      = ($latestRevenueLoyalty === 'unpaid-payment' || $latestRevenueLoyalty === 'unpaid');
        $isFailed      = ($latestRevenueLoyalty === 'payment-failed' || $latestRevenueLoyalty === 'failed-payment');
        $isPaymentMade = ($latestRevenueLoyalty === 'payment-made');
    }

    // =====================================================================
    // BROKER LINK RESOLUTION
    // =====================================================================
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

                $broker_name = (strpos($entry, ':') !== false)
                    ? trim(substr($entry, strrpos($entry, ':') + 1))
                    : $entry;

                $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);
                $broker_name = trim($broker_name);
                $broker_name_clean = strtolower($broker_name);

                if (!empty($broker_name)) {
                    $formatted_name = ucfirst($broker_name);
                    $link = '';

                    foreach ($cleaned_links as $cleaned_link) {
                        if (strpos($cleaned_link, $broker_name_clean) !== false) {
                            $link = $cleaned_link;
                            break;
                        }
                    }

                    if (empty($link) && isset($cleaned_links[$index])) {
                        $link = $cleaned_links[$index];
                    }

                    if (!in_array($formatted_name, $allowed_brokers)) {
                        $allowed_brokers[] = $formatted_name;
                        $broker_links[$formatted_name] = $link;
                    }
                }
            }
            sort($allowed_brokers);
        }
    } catch (PDOException $e) {}

    // ==================== DETERMINE BROKER LINK FOR THIS USER ====================
    $brokerLink = '';

    $userBrokerRaw = trim($user['broker'] ?? '');
    $userBroker = ucfirst(strtolower($userBrokerRaw));

    if (!empty($userBroker) && isset($broker_links[$userBroker]) && !empty($broker_links[$userBroker])) {
        $brokerLink = $broker_links[$userBroker];
    } else {
        foreach ($broker_links as $name => $url) {
            if (strcasecmp($name, $userBroker) === 0 && !empty($url)) {
                $brokerLink = $url;
                break;
            }
        }

        if (empty($brokerLink)) {
            foreach ($broker_links as $name => $url) {
                if (!empty($url) && stripos($userBrokerRaw, $name) !== false) {
                    $brokerLink = $url;
                    break;
                }
            }
        }
    }

    if (empty($brokerLink)) {
        foreach ($broker_links as $name => $url) {
            if ((strcasecmp($name, 'Harvhub') === 0 || strcasecmp($name, 'HarvHub') === 0) && !empty($url)) {
                $brokerLink = $url;
                break;
            }
        }
        if (empty($brokerLink)) {
            foreach ($broker_links as $name => $url) {
                if (!empty($url)) {
                    $brokerLink = $url;
                    break;
                }
            }
        }
    }

    if (!empty($brokerLink) && strpos($brokerLink, '://') === false) {
        $brokerLink = 'https://' . $brokerLink;
    }

    $brokerTarget = !empty($brokerLink) ? htmlspecialchars($brokerLink) : 'about:blank';

    // =========================================================================
    // POST Handling - Confirm Payment
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_confirm_payment'])) {
        $coin = $_POST['payment_coin'] ?? 'N/A';
        $amount = $_POST['server_share_amount'] ?? 0.00;
        $datetime = date('Y-m-d H:i:s');
        $paymentDetails = "Amount: $" . number_format($amount, 2) . ", Coin: " . htmlspecialchars($coin) . ", Confirmed_at: " . $datetime;

        $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $latestRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latestRecord) {
            $updateStmt = $pdo->prepare("
                UPDATE $revenueHistoryTable
                SET loyalties = 'payment-made', payment_details = ?, payment_date = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$paymentDetails, $datetime, $latestRecord['id']]);
        }

        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = 'payment-made', paymentdetails = ? WHERE email = ?");
        $upd->execute([$paymentDetails, $email]);

        $_SESSION['prg_redirect_safe'] = true;
        $_SESSION['payment_success_message'] = "Payment submitted successfully! Waiting for server confirmation.";
        header("Location: app.php#mydashboard");
        exit;
    }

    $paymentSuccessMessage = $_SESSION['payment_success_message'] ?? null;
    unset($_SESSION['payment_success_message']);

    $showPaymentForm = ($isUnpaid || $isFailed || $isRetry);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
    <title>Profit Split - Harvhub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'style.php'; ?>
    <style>
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
        .profit-split-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .profit-split-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .profit-split-header h1 {
            font-size: 1.8rem;
            color: var(--text);
            margin: 0;
        }

        .profit-split-header .back-btn {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 10px 24px;
            border-radius: var(--radius-sm);
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .profit-split-header .back-btn:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .profit-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
        }

        .profit-card .profit-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--success);
            text-align: center;
            margin: 10px 0;
        }

        .profit-card .profit-label {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .programme-badge {
            display: inline-block;
            background: rgba(46, 139, 87, 0.12);
            border: 1px solid var(--accent);
            color: var(--accent);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .split-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 24px 0;
        }

        .split-box {
            background: var(--bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 20px;
            text-align: center;
        }

        .split-box .split-percent {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text);
        }

        .split-box .split-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .split-box .split-amount {
            font-size: 1.5rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .split-box.user-box .split-amount { color: var(--info); }
        .split-box.server-box .split-amount { color: #9b59b6; }

        .action-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .payment-status {
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-status.info {
            background: var(--info-bg);
            border: 1px solid var(--info);
            color: var(--info);
        }

        .payment-status.warning {
            background: var(--warning-bg);
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        .payment-status.success {
            background: var(--success-bg);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .payment-status.danger {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .btn-pay-server {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-pay-server:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(155, 89, 182, 0.3);
        }

        .btn-pay-server:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-withdraw-profit {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            text-decoration: none;
            text-align: center;
        }

        .btn-withdraw-profit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(46, 204, 113, 0.3);
        }

        .btn-withdraw-profit.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            transform: none;
        }

        .btn-retry {
            display: inline-block;
            padding: 14px 40px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(243, 156, 18, 0.3);
        }

        .coin-selector {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 16px 0;
            flex-wrap: wrap;
        }

        .coin-selector input[type="radio"] {
            display: none;
        }

        .coin-selector label {
            padding: 10px 24px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            background: var(--bg);
            color: var(--text);
        }

        .coin-selector input[type="radio"]:checked + label {
            border-color: var(--accent);
            background: var(--accent-light);
            color: var(--accent);
        }

        .coin-selector label:hover {
            border-color: var(--accent);
        }

        .crypto-details {
            background: var(--bg);
            padding: 16px;
            border-radius: var(--radius-sm);
            margin: 16px 0;
            text-align: center;
        }

        .crypto-details .address {
            font-family: 'SF Mono', monospace;
            font-size: 0.9rem;
            word-break: break-all;
            color: var(--accent);
            cursor: pointer;
            padding: 8px;
            background: var(--bg-card);
            border-radius: 4px;
            display: inline-block;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 16px 0;
            cursor: pointer;
        }

        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .disclaimer {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 12px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .modal-actions button {
            padding: 10px 30px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .modal-actions .btn-cancel {
            background: #555;
            color: white;
        }

        .modal-actions .btn-cancel:hover {
            background: #666;
        }

        .modal-actions .btn-confirm {
            background: var(--success);
            color: white;
        }

        .modal-actions .btn-confirm:hover {
            background: #27ae60;
        }

        .modal-actions .btn-confirm:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .split-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 16px;
        }

        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 40px 30px;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .info-card h2 {
            color: var(--text);
            margin-bottom: 8px;
        }

        .info-card p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .split-grid {
                grid-template-columns: 1fr;
            }
            .split-actions {
                grid-template-columns: 1fr;
            }
            .profit-split-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="profit-split-container">
    <div class="profit-split-header">
        <h1>Profit Split</h1>
        <a href="app.php#mydashboard" class="back-btn">← Back to Dashboard</a>
    </div>

    <?php if ($paymentSuccessMessage): ?>
        <div class="payment-status success">
            <span><?= htmlspecialchars($paymentSuccessMessage) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!$hasProgramme): ?>
        <div class="info-card">
            <h2>No Programme Investment</h2>
            <p>You are not currently invested in any programme. Please join a programme before profit splits are calculated.</p>
            <a href="programmes.php" class="back-btn" style="display: inline-block;">Explore Programmes</a>
        </div>

    <?php elseif ($isPaymentMade): ?>
        <div class="payment-status info">
            <div>
                <div class="status-title">Payment Submitted</div>
                <p style="margin-top: 4px;">Your payment is pending confirmation from the server. Please check back later.</p>
            </div>
        </div>
        <div class="profit-card" style="text-align: center;">
            <?php if ($DEVELOPER_NAME): ?>
                <div class="programme-badge">
                    <?= htmlspecialchars($DEVELOPER_NAME) ?>'s Programme
                    <?php if ($PROGRAMME_NAME): ?> • <?= htmlspecialchars($PROGRAMME_NAME) ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="profit-label">Contract Profit</div>
            <div class="profit-amount">$<?= number_format($profitToSplit, 2) ?></div>
            <div style="margin-top: 16px; color: var(--text-muted);">
                <p>Programme Share: <strong style="color: #9b59b6;">$<?= number_format($serverShare, 2) ?></strong></p>
                <p>Your Share: <strong style="color: var(--info);">$<?= number_format($userShare, 2) ?></strong></p>
            </div>
            <div style="margin-top: 20px; padding: 16px; background: var(--warning-bg); border-radius: var(--radius-sm);">
                <p style="color: var(--warning); font-weight: 500;">Awaiting server confirmation...</p>
            </div>
        </div>

    <?php elseif ($isUnpaid): ?>
        <div class="payment-status warning">
            <div>
                <div class="status-title">Payment Required</div>
                <p style="margin-top: 4px;">Please complete the profit split payment to continue.</p>
            </div>
        </div>

        <div class="profit-card">
            <?php if ($DEVELOPER_NAME): ?>
                <div class="programme-badge">
                    <?= htmlspecialchars($DEVELOPER_NAME) ?>'s Programme
                    <?php if ($PROGRAMME_NAME): ?> • <?= htmlspecialchars($PROGRAMME_NAME) ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="profit-label">Contract Profit</div>
            <div class="profit-amount">$<?= number_format($profitToSplit, 2) ?></div>

            <div class="split-grid">
                <div class="split-box user-box">
                    <div class="split-percent"><?= $USER_SHARE_PERCENT ?>%</div>
                    <div class="split-label">Your Share</div>
                    <div class="split-amount">$<?= number_format($userShare, 2) ?></div>
                </div>
                <div class="split-box server-box">
                    <div class="split-percent"><?= $SERVER_SHARE_PERCENT ?>%</div>
                    <div class="split-label">Programme Share</div>
                    <div class="split-amount">$<?= number_format($serverShare, 2) ?></div>
                </div>
            </div>

            <div class="split-actions">
                <a href="<?= $brokerTarget ?>" target="_blank" class="btn-withdraw-profit <?= (empty($brokerLink) || $brokerLink === 'about:blank') ? 'disabled' : '' ?>">
                    Withdraw Your Share
                </a>
                <button onclick="openPaymentModal()" class="btn-pay-server">
                    Pay Programme Share
                </button>
            </div>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-top: 12px;">
                Pay $<?= number_format($serverShare, 2) ?> to remain eligible for future contracts
            </p>
        </div>

    <?php elseif ($isFailed || $isRetry): ?>
        <div class="payment-status danger">
            <div>
                <div class="status-title">Payment Failed</div>
                <p style="margin-top: 4px;">Your previous payment attempt could not be verified. Please retry.</p>
            </div>
        </div>

        <div class="profit-card">
            <?php if ($DEVELOPER_NAME): ?>
                <div class="programme-badge">
                    <?= htmlspecialchars($DEVELOPER_NAME) ?>'s Programme
                    <?php if ($PROGRAMME_NAME): ?> • <?= htmlspecialchars($PROGRAMME_NAME) ?><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="profit-label">Contract Profit</div>
            <div class="profit-amount">$<?= number_format($profitToSplit, 2) ?></div>

            <div class="split-grid">
                <div class="split-box user-box">
                    <div class="split-percent"><?= $USER_SHARE_PERCENT ?>%</div>
                    <div class="split-label">Your Share</div>
                    <div class="split-amount">$<?= number_format($userShare, 2) ?></div>
                </div>
                <div class="split-box server-box">
                    <div class="split-percent"><?= $SERVER_SHARE_PERCENT ?>%</div>
                    <div class="split-label">Programme Share</div>
                    <div class="split-amount">$<?= number_format($serverShare, 2) ?></div>
                </div>
            </div>

            <div class="split-actions">
                <a href="<?= $brokerTarget ?>" target="_blank" class="btn-withdraw-profit <?= (empty($brokerLink) || $brokerLink === 'about:blank') ? 'disabled' : '' ?>">
                    Withdraw Your Share
                </a>
                <button onclick="openPaymentModal()" class="btn-retry">
                    Retry Payment
                </button>
            </div>
            <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-top: 12px;">
                Retry paying $<?= number_format($serverShare, 2) ?> to the programme
            </p>
        </div>

    <?php else: ?>
        <div class="info-card">
            <h2>No Active Profit Split Required</h2>
            <p>You don't have any pending profit split payments at this time.</p>
            <a href="app.php#mydashboard" class="back-btn" style="display: inline-block;">Return to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($hasProgramme && ($isUnpaid || $isFailed || $isRetry)): ?>
<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--accent);">Pay Programme Share</h2>
        <p style="margin: 1rem 0; opacity: 0.8; text-align: center;">
            Send <strong id="paymentAmountDisplay">$<?= number_format($serverShare, 2) ?></strong> worth of the selected cryptocurrency
        </p>

        <input type="hidden" id="serverShareAmountHidden" value="<?= number_format($serverShare, 2, '.', '') ?>">

        <div class="coin-selector">
            <input type="radio" id="coin_btc" name="coin" value="btc" checked onchange="updatePaymentDetails('btc')">
            <label for="coin_btc">BTC</label>

            <input type="radio" id="coin_eth" name="coin" value="eth" onchange="updatePaymentDetails('eth')">
            <label for="coin_eth">ETH</label>

            <input type="radio" id="coin_usdt" name="coin" value="usdt" onchange="updatePaymentDetails('usdt')">
            <label for="coin_usdt">USDT</label>
        </div>

        <div class="crypto-details">
            <p>Network: <strong id="paymentNetwork">N/A</strong></p>
            <p>Address:</p>
            <span class="address" id="paymentAddress">N/A</span>
        </div>

        <button class="btn-pay-server" id="copyAddressBtn" style="width: 100%; margin-bottom: 12px;">
            Copy Address
        </button>

        <label class="checkbox-container">
            <input type="checkbox" id="paymentConfirmationCheck" onchange="togglePaidButton()">
            I have made the payment
        </label>

        <button class="btn-pay-server" id="confirmPaidBtn" disabled onclick="triggerFinalConfirmation()" style="background: var(--success);">
            Confirm Payment
        </button>

        <p class="disclaimer">Click only after payment has been successfully sent. Your payment will be verified by the server.</p>

        <div class="modal-actions">
            <button onclick="closePaymentModal()" class="btn-cancel">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Final Confirmation Modal -->
<div id="finalConfirmationModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--success);">Final Confirmation</h2>
        <p style="margin: 1.5rem 0; line-height: 1.6; text-align: center;">
            Confirm that you have sent <strong id="finalConfirmAmount" style="color: var(--success);">$<?= number_format($serverShare, 2) ?></strong> to the
            <strong id="finalConfirmCoin">N/A</strong> address.
        </p>

        <div class="modal-actions">
            <button onclick="document.getElementById('finalConfirmationModal').classList.remove('active')" class="btn-cancel">
                Cancel
            </button>
            <form method="POST" style="display:inline;" id="finalPaymentForm">
                <input type="hidden" name="final_confirm_payment" value="1">
                <input type="hidden" name="server_share_amount" id="formServerShareAmount" value="<?= number_format($serverShare, 2, '.', '') ?>">
                <input type="hidden" name="payment_coin" id="formPaymentCoin" value="">
                <button type="submit" id="finalConfirmButton" class="btn-confirm">
                    Yes, I've Paid
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    const serverAccounts = {
        btc: {
            address: "<?= htmlspecialchars($serverAccount['btc_address'] ?? 'N/A') ?>",
            network: "Bitcoin"
        },
        eth: {
            address: "<?= htmlspecialchars($serverAccount['eth_address'] ?? 'N/A') ?>",
            network: "<?= htmlspecialchars($serverAccount['eth_network'] ?? 'ERC20') ?>"
        },
        usdt: {
            address: "<?= htmlspecialchars($serverAccount['usdt_address'] ?? 'N/A') ?>",
            network: "<?= htmlspecialchars($serverAccount['usdt_network'] ?? 'TRC20') ?>"
        }
    };

    const paymentAddressElement = document.getElementById('paymentAddress');
    const paymentNetworkElement = document.getElementById('paymentNetwork');
    const copyAddressBtn = document.getElementById('copyAddressBtn');
    const confirmPaidBtn = document.getElementById('confirmPaidBtn');
    const paymentConfirmationCheck = document.getElementById('paymentConfirmationCheck');

    function openPaymentModal() {
        document.getElementById('paymentModal').classList.add('active');
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton();
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.remove('active');
        paymentConfirmationCheck.checked = false;
        togglePaidButton();
    }

    function getSelectedCoin() {
        const selected = document.querySelector('input[name="coin"]:checked');
        return selected ? selected.value : 'btc';
    }

    function updatePaymentDetails(coin) {
        const data = serverAccounts[coin];
        if (data) {
            paymentAddressElement.textContent = data.address;
            paymentNetworkElement.textContent = data.network;
            paymentAddressElement.dataset.address = data.address;
        }
    }

    function togglePaidButton() {
        confirmPaidBtn.disabled = !paymentConfirmationCheck.checked;
    }

    function triggerFinalConfirmation() {
        const selectedCoin = getSelectedCoin();
        const serverShareAmount = <?php echo number_format($serverShare, 2, '.', ''); ?>;

        document.getElementById('finalConfirmAmount').innerHTML = '$' + serverShareAmount.toFixed(2);
        document.getElementById('finalConfirmCoin').innerHTML = selectedCoin.toUpperCase();

        document.getElementById('formServerShareAmount').value = serverShareAmount;
        document.getElementById('formPaymentCoin').value = selectedCoin;

        document.getElementById('paymentModal').classList.remove('active');
        document.getElementById('finalConfirmationModal').classList.add('active');
    }

    copyAddressBtn.addEventListener('click', function() {
        const address = paymentAddressElement.textContent;
        if (navigator.clipboard && address && address !== 'N/A') {
            navigator.clipboard.writeText(address).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                setTimeout(() => { this.textContent = originalText; }, 2000);
            }).catch(err => {
                alert('Could not copy address. Please copy manually.');
            });
        } else {
            alert('Address not available.');
        }
    });

    paymentAddressElement.addEventListener('click', function() {
        copyAddressBtn.click();
    });

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton();
    });
</script>
<?php endif; ?>

</body>
</html>