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

    // =====================================================================
    // REUSABLE PAYLOAD BUILDER — used by both the initial render and AJAX
    // =====================================================================
    function buildProfitSplitPayload($pdo, $email, $userId, $user, $serverAccount, $tableName, $revenueHistoryTable, $programmeInvestorsTable, $programmeTable) {
        // ---- Server-level split defaults ----
        $SERVER_SHARE_PERCENT = (int)($serverAccount['server_share_percent'] ?? 30);
        $USER_SHARE_PERCENT   = (int)($serverAccount['user_share_percent'] ?? 70);
        $MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);
        $SERVER_CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 30);

        // ---- Find user's active programme investment ----
        $activeInvestment     = null;
        $investmentProgramme  = null;
        $investmentDeveloper  = null;

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

        // ---- Contract duration (needed to compute expiry) ----
        $contractDuration = $SERVER_CONTRACT_DURATION;
        if ($hasProgramme && !empty($activeInvestment['contract_duration'])) {
            $pi_cd = (int)$activeInvestment['contract_duration'];
            if ($pi_cd > 0) $contractDuration = $pi_cd;
        }

        // ---- User financial state ----
        $brokerBalance   = (float)($user['broker_balance'] ?? 0);
        $profitAndLoss   = (float)($user['profitandloss'] ?? 0);
        $loyaltiesStatus = $user['loyalties'] ?? null;

        $profitToSplit = max(0, $profitAndLoss);
        $serverShare   = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
        $userShare     = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);

        // ---- Contract expiry check ----
        $executionStartDate  = $user['execution_start_date'] ?? null;
        $balanceVerification = $user['balance_verification'] ?? 'not-verified';

        $isContractExpired = false;
        $isContractActive  = false;

        if ($executionStartDate && $executionStartDate !== '0000-00-00' && $executionStartDate !== null) {
            try {
                $start = new DateTime($executionStartDate);
                $end   = clone $start;
                $end->modify("+{$contractDuration} days");

                $today    = new DateTime(); $today->setTime(0, 0, 0);
                $endClone = clone $end;    $endClone->setTime(0, 0, 0);

                $daysLeft = (int)$today->diff($endClone)->format('%r%a');

                if ($daysLeft <= 0) { $isContractExpired = true; $isContractActive = false; }
                else                { $isContractActive  = true;  $isContractExpired = false; }
            } catch (Exception $e) {
                // Bad date — treat as no contract
            }
        }

        // ---- Latest revenue history row ----
        $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $latestRevenue = $stmt->fetch(PDO::FETCH_ASSOC);

        $latestRevenueLoyalty = $latestRevenue['loyalties'] ?? null;

        // ---- Status flags ----
        $isUnpaid      = ($loyaltiesStatus === 'unpaid-payment' || $loyaltiesStatus === 'unpaid');
        $isFailed      = ($loyaltiesStatus === 'payment-failed' || $loyaltiesStatus === 'failed-payment');
        $isPaymentMade = ($loyaltiesStatus === 'payment-made');

        if ($loyaltiesStatus === null) {
            $isUnpaid      = ($latestRevenueLoyalty === 'unpaid-payment' || $latestRevenueLoyalty === 'unpaid');
            $isFailed      = ($latestRevenueLoyalty === 'payment-failed' || $latestRevenueLoyalty === 'failed-payment');
            $isPaymentMade = ($latestRevenueLoyalty === 'payment-made');
        }

        // ---- INFER unpaid state (mirrors determineDashboardState) ----
        if (!$isUnpaid && !$isFailed && !$isPaymentMade) {
            $isVerified           = ($balanceVerification === 'verified');
            $profitAboveThreshold = ($profitAndLoss > $MIN_PROFIT_FOR_SPLIT);

            if ($isVerified && $isContractExpired && $profitAboveThreshold) {
                $isUnpaid = true;
            }
        }

        // ---- Broker link resolution ----
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

        $brokerTarget = !empty($brokerLink) ? $brokerLink : 'about:blank';

        return [
            'hasProgramme'         => $hasProgramme,
            'PROGRAMME_NAME'       => $PROGRAMME_NAME,
            'DEVELOPER_NAME'       => $DEVELOPER_NAME,
            'SERVER_SHARE_PERCENT' => $SERVER_SHARE_PERCENT,
            'USER_SHARE_PERCENT'   => $USER_SHARE_PERCENT,
            'MIN_PROFIT_FOR_SPLIT' => $MIN_PROFIT_FOR_SPLIT,
            'contractDuration'     => $contractDuration,
            'brokerBalance'        => $brokerBalance,
            'profitAndLoss'        => $profitAndLoss,
            'profitToSplit'        => $profitToSplit,
            'serverShare'          => $serverShare,
            'userShare'            => $userShare,
            'loyaltiesStatus'      => $loyaltiesStatus,
            'latestRevenueLoyalty' => $latestRevenueLoyalty,
            'isUnpaid'             => $isUnpaid,
            'isFailed'             => $isFailed,
            'isPaymentMade'        => $isPaymentMade,
            'isContractExpired'    => $isContractExpired,
            'isContractActive'     => $isContractActive,
            'balanceVerification'  => $balanceVerification,
            'brokerLink'           => $brokerLink,
            'brokerTarget'         => $brokerTarget,
        ];
    }

    // =====================================================================
    // AJAX: LIVE PROFIT SPLIT (JSON)
    // =====================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

        while (ob_get_level() > 0) { ob_end_clean(); }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (!isset($_SESSION['user_email'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
            $stmt->execute([$email]);
            $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liveUser) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            $liveUserId = (int)$liveUser['id'];

            $payload = buildProfitSplitPayload(
                $pdo, $email, $liveUserId, $liveUser, $serverAccount,
                $tableName, $revenueHistoryTable, $programmeInvestorsTable, $programmeTable
            );

            echo json_encode([
                'success'              => true,
                'has_programme'        => $payload['hasProgramme'],
                'programme_name'       => $payload['PROGRAMME_NAME'],
                'developer_name'       => $payload['DEVELOPER_NAME'],
                'server_share_percent' => $payload['SERVER_SHARE_PERCENT'],
                'user_share_percent'   => $payload['USER_SHARE_PERCENT'],
                'min_profit_for_split' => $payload['MIN_PROFIT_FOR_SPLIT'],
                'contract_duration'    => $payload['contractDuration'],
                'broker_balance'       => number_format($payload['brokerBalance'], 2),
                'profit_and_loss'      => number_format($payload['profitAndLoss'], 2),
                'profit_to_split'      => number_format($payload['profitToSplit'], 2),
                'server_share'         => number_format($payload['serverShare'], 2),
                'user_share'           => number_format($payload['userShare'], 2),
                'loyalties_status'     => $payload['loyaltiesStatus'],
                'latest_revenue_loyalty' => $payload['latestRevenueLoyalty'],
                'is_unpaid'            => $payload['isUnpaid'],
                'is_failed'            => $payload['isFailed'],
                'is_payment_made'      => $payload['isPaymentMade'],
                'is_contract_expired'  => $payload['isContractExpired'],
                'is_contract_active'   => $payload['isContractActive'],
                'balance_verification' => $payload['balanceVerification'],
                'broker_link'          => $payload['brokerLink'],
                'broker_target'        => htmlspecialchars($payload['brokerTarget']),
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to build profit split payload.'
            ]);
            exit;
        }
    }

    // =====================================================================
    // INITIAL PAGE RENDER (non-AJAX)
    // =====================================================================
    $payload = buildProfitSplitPayload(
        $pdo, $email, $userId, $user, $serverAccount,
        $tableName, $revenueHistoryTable, $programmeInvestorsTable, $programmeTable
    );

    $hasProgramme         = $payload['hasProgramme'];
    $PROGRAMME_NAME       = $payload['PROGRAMME_NAME'];
    $DEVELOPER_NAME       = $payload['DEVELOPER_NAME'];
    $SERVER_SHARE_PERCENT = $payload['SERVER_SHARE_PERCENT'];
    $USER_SHARE_PERCENT   = $payload['USER_SHARE_PERCENT'];
    $MIN_PROFIT_FOR_SPLIT = $payload['MIN_PROFIT_FOR_SPLIT'];
    $contractDuration     = $payload['contractDuration'];
    $brokerBalance        = $payload['brokerBalance'];
    $profitAndLoss        = $payload['profitAndLoss'];
    $profitToSplit        = $payload['profitToSplit'];
    $serverShare          = $payload['serverShare'];
    $userShare            = $payload['userShare'];
    $loyaltiesStatus      = $payload['loyaltiesStatus'];
    $latestRevenueLoyalty = $payload['latestRevenueLoyalty'];
    $isUnpaid             = $payload['isUnpaid'];
    $isFailed             = $payload['isFailed'];
    $isPaymentMade        = $payload['isPaymentMade'];
    $isContractExpired    = $payload['isContractExpired'];
    $isContractActive     = $payload['isContractActive'];
    $balanceVerification  = $payload['balanceVerification'];
    $brokerLink           = $payload['brokerLink'];
    $brokerTarget         = htmlspecialchars($payload['brokerTarget']);

    $isRetry = isset($_GET['retry']) && $_GET['retry'] == 1;

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

    /* ============================================================
    CREAM BLUE PALETTE
    ============================================================ */
    :root {
        --cb-light:  #eaf4fb;
        --cb-mid:    #a8d0ec;
        --cb-base:   #6fa8d1;
        --cb-deep:   #4a7ea8;
        --cb-dark:   #2e5d80;
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
        background: var(--cb-base);
        color: #fff;
        border-color: var(--cb-base);
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
        background: rgba(111, 168, 209, 0.12);
        border: 1px solid var(--cb-base);
        color: var(--cb-deep);
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
    .split-box.server-box .split-amount { color: var(--cb-deep); }

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

    /* ============================================================
    PAY PROGRAMME SHARE — CREAM BLUE
    ============================================================ */
    .btn-pay-server {
        display: inline-block;
        padding: 14px 40px;
        background: linear-gradient(135deg, var(--cb-mid), var(--cb-base));
        color: #1a3c53;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        box-shadow: 0 2px 8px rgba(111, 168, 209, 0.25);
        -webkit-tap-highlight-color: transparent;
    }

    .btn-pay-server:hover {
        background: linear-gradient(135deg, var(--cb-light), var(--cb-mid));
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(111, 168, 209, 0.4);
    }

    .btn-pay-server:active {
        transform: translateY(0);
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
        -webkit-tap-highlight-color: transparent;
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
        -webkit-tap-highlight-color: transparent;
    }

    .btn-retry:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(243, 156, 18, 0.3);
    }

    /* ============================================================
    CONFIRM PAYMENT button inside modal — cream blue
    ============================================================ */
    .btn-confirm-payment {
        display: inline-block;
        padding: 14px 40px;
        background: linear-gradient(135deg, var(--cb-mid), var(--cb-base));
        color: #1a3c53;
        border: none;
        border-radius: var(--radius-sm);
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        box-shadow: 0 2px 8px rgba(111, 168, 209, 0.25);
        -webkit-tap-highlight-color: transparent;
    }

    .btn-confirm-payment:hover {
        background: linear-gradient(135deg, var(--cb-light), var(--cb-mid));
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(111, 168, 209, 0.4);
    }

    .btn-confirm-payment:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }

    /* ============================================================
    COIN SELECTOR (BTC / ETH / USDT)
    - Kill the browser's default white tap-highlight
    - Kill the focus/active ring on labels
    - Keep the same cream-blue look in every state
    ============================================================ */
    .coin-selector {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin: 16px 0;
        flex-wrap: wrap;
    }

    .coin-selector input[type="radio"] {
        /* Visually hidden but still focusable for accessibility */
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    .coin-selector label {
        padding: 10px 24px;
        border: 2px solid var(--border-color);
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.15s ease,
                    border-color 0.15s ease,
                    color 0.15s ease,
                    box-shadow 0.15s ease;
        background: var(--bg);
        color: var(--text);

        /* Crucial: kill the browser's white tap highlight */
        -webkit-tap-highlight-color: transparent;
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        outline: none;
    }

    /* Hover state */
    .coin-selector label:hover {
        border-color: var(--cb-base);
        background: var(--cb-light);
        color: var(--cb-dark);
    }

    /* Active / pressed state — override the browser white flash */
    .coin-selector label:active {
        background: var(--cb-mid);
        border-color: var(--cb-base);
        color: #12303f;
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.25);
    }

    /* Focus state — keep it cream-blue, not browser-default */
    .coin-selector label:focus,
    .coin-selector label:focus-visible {
        outline: none;
        border-color: var(--cb-base);
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.35);
        background: var(--cb-light);
        color: var(--cb-dark);
    }

    /* Selected (radio checked) state */
    .coin-selector input[type="radio"]:checked + label {
        border-color: var(--cb-base);
        background: var(--cb-light);
        color: var(--cb-dark);
        font-weight: 700;
    }

    /* Selected + hover — do not let it flip to another colour */
    .coin-selector input[type="radio"]:checked + label:hover {
        border-color: var(--cb-base);
        background: var(--cb-light);
        color: var(--cb-dark);
    }

    /* Selected + active — keep it cream-blue so no white flash */
    .coin-selector input[type="radio"]:checked + label:active {
        border-color: var(--cb-base);
        background: var(--cb-mid);
        color: #12303f;
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.35);
    }

    /* Selected + focus — cream-blue ring, no white */
    .coin-selector input[type="radio"]:checked + label:focus,
    .coin-selector input[type="radio"]:checked + label:focus-visible {
        outline: none;
        border-color: var(--cb-base);
        background: var(--cb-light);
        color: var(--cb-dark);
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.35);
    }

    /* Dark mode — same states, just tinted for the dark palette */
    body.dark-mode .coin-selector label {
        background: var(--bg-card);
        color: var(--text);
    }

    body.dark-mode .coin-selector label:hover,
    body.dark-mode .coin-selector label:focus,
    body.dark-mode .coin-selector label:focus-visible,
    body.dark-mode .coin-selector input[type="radio"]:checked + label,
    body.dark-mode .coin-selector input[type="radio"]:checked + label:hover,
    body.dark-mode .coin-selector input[type="radio"]:checked + label:focus {
        background: #2e5d80;
        color: #f3f9fd;
        border-color: var(--cb-mid);
    }

    body.dark-mode .coin-selector label:active,
    body.dark-mode .coin-selector input[type="radio"]:checked + label:active {
        background: #4a7ea8;
        color: #ffffff;
        border-color: var(--cb-light);
        box-shadow: 0 0 0 3px rgba(168, 208, 236, 0.35);
    }

    /* ============================================================
    CRYPTO DETAILS + ADDRESS
    ============================================================ */
    .crypto-details {
        background: var(--bg);
        padding: 16px;
        border-radius: var(--radius-sm);
        margin: 16px 0;
        text-align: center;
    }

    .crypto-details .address {
        font-family: 'SF Mono', 'Courier New', monospace;
        font-size: 0.9rem;
        word-break: break-all;
        color: var(--cb-dark);
        background: var(--bg-card);
        cursor: pointer;
        padding: 8px 12px;
        border-radius: 4px;
        display: inline-block;
        border: 1px solid transparent;
        transition: background-color 0.15s ease,
                    color 0.15s ease,
                    border-color 0.15s ease,
                    box-shadow 0.15s ease;
        user-select: all;
        -webkit-user-select: all;
        -webkit-tap-highlight-color: transparent;
    }

    .crypto-details .address:hover {
        background: var(--cb-light);
        color: var(--cb-dark);
        border-color: var(--cb-mid);
    }

    .crypto-details .address:active,
    .crypto-details .address:focus,
    .crypto-details .address:focus-visible,
    .crypto-details .address.selected {
        background: #d6e8f5;
        color: #12303f;
        border-color: var(--cb-base);
        outline: none;
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.25);
        font-weight: 600;
    }

    body.dark-mode .crypto-details .address {
        background: #1a2733;
        color: #cfe4f2;
        border-color: transparent;
    }

    body.dark-mode .crypto-details .address:hover {
        background: #22323f;
        color: #e4f0fa;
        border-color: var(--cb-deep);
    }

    body.dark-mode .crypto-details .address:active,
    body.dark-mode .crypto-details .address:focus,
    body.dark-mode .crypto-details .address:focus-visible,
    body.dark-mode .crypto-details .address.selected {
        background: #2e5d80;
        color: #f3f9fd;
        border-color: var(--cb-mid);
        box-shadow: 0 0 0 3px rgba(111, 168, 209, 0.35);
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 16px 0;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
        -webkit-user-select: none;
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
        -webkit-tap-highlight-color: transparent;
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

    <div id="paymentSuccessNotice" style="<?= $paymentSuccessMessage ? '' : 'display:none;' ?>">
        <?php if ($paymentSuccessMessage): ?>
            <div class="payment-status success">
                <span><?= htmlspecialchars($paymentSuccessMessage) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- LIVE STATE BLOCK — replaced wholesale on every poll -->
    <div id="profitSplitStateBlock">
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
                    <p>Programme Share: <strong style="color: var(--cb-deep);">$<?= number_format($serverShare, 2) ?></strong></p>
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
                    <button type="button" data-action="open-payment-modal" class="btn-pay-server">
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
                    <button type="button" data-action="open-payment-modal" class="btn-retry">
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
</div>

<?php if ($hasProgramme && ($isUnpaid || $isFailed || $isRetry)): ?>
<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--cb-deep);">Pay Programme Share</h2>
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

        <button type="button" class="btn-confirm-payment" id="copyAddressBtn" style="margin-bottom: 12px;">
            Copy Address
        </button>

        <label class="checkbox-container">
            <input type="checkbox" id="paymentConfirmationCheck" onchange="togglePaidButton()">
            I have made the payment
        </label>

        <button type="button" class="btn-confirm-payment" id="confirmPaidBtn" disabled onclick="triggerFinalConfirmation()">
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
<?php endif; ?>

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

    // =====================================================================
    // POLL PAUSE FLAG — the live poll will skip re-rendering while a modal
    // is open, so the user can interact with the payment form / address.
    // =====================================================================
    var modalOpen = false;

    function openPaymentModal() {
        const modal = document.getElementById('paymentModal');
        if (!modal) return;
        modalOpen = true;
        modal.classList.add('active');
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton();
    }

    function closePaymentModal() {
        const modal = document.getElementById('paymentModal');
        if (!modal) return;
        modal.classList.remove('active');
        modalOpen = false;
        if (paymentConfirmationCheck) paymentConfirmationCheck.checked = false;
        togglePaidButton();
    }

    function getSelectedCoin() {
        const selected = document.querySelector('input[name="coin"]:checked');
        return selected ? selected.value : 'btc';
    }

    function updatePaymentDetails(coin) {
        const data = serverAccounts[coin];
        if (data) {
            if (paymentAddressElement) {
                paymentAddressElement.textContent = data.address;
                paymentAddressElement.dataset.address = data.address;
            }
            if (paymentNetworkElement) {
                paymentNetworkElement.textContent = data.network;
            }
        }
    }

    function togglePaidButton() {
        if (!confirmPaidBtn) return;
        confirmPaidBtn.disabled = !(paymentConfirmationCheck && paymentConfirmationCheck.checked);
    }

    function triggerFinalConfirmation() {
        const selectedCoin = getSelectedCoin();
        const amountEl = document.getElementById('serverShareAmountHidden');
        const serverShareAmount = amountEl ? parseFloat(amountEl.value) : 0;

        const finalAmountEl = document.getElementById('finalConfirmAmount');
        const finalCoinEl   = document.getElementById('finalConfirmCoin');
        const formAmount    = document.getElementById('formServerShareAmount');
        const formCoin      = document.getElementById('formPaymentCoin');

        if (finalAmountEl) finalAmountEl.innerHTML = '$' + serverShareAmount.toFixed(2);
        if (finalCoinEl)   finalCoinEl.innerHTML   = selectedCoin.toUpperCase();
        if (formAmount)    formAmount.value        = serverShareAmount;
        if (formCoin)      formCoin.value          = selectedCoin;

        const payModal = document.getElementById('paymentModal');
        const confModal = document.getElementById('finalConfirmationModal');
        if (payModal) payModal.classList.remove('active');
        if (confModal) confModal.classList.add('active');
    }

    if (copyAddressBtn) {
        copyAddressBtn.addEventListener('click', function() {
            const address = paymentAddressElement ? paymentAddressElement.textContent : '';
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
    }

    if (paymentAddressElement) {
        paymentAddressElement.addEventListener('click', function() {
            if (copyAddressBtn) copyAddressBtn.click();
        });
    }

    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.remove('active');
                if (this.id === 'paymentModal') {
                    modalOpen = false;
                }
            }
        });
    });

    // =====================================================================
    // EVENT DELEGATION — attach the "open payment modal" handler on a
    // STABLE parent (#profitSplitStateBlock) so re-rendering the inner
    // HTML does not destroy the handler.
    // =====================================================================
    (function bindPaymentDelegation() {
        var stableParent = document.getElementById('profitSplitStateBlock');
        if (!stableParent) return;

        stableParent.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-action="open-payment-modal"]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                openPaymentModal();
            }
        });
    })();

    document.addEventListener('DOMContentLoaded', function() {
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton();
    });

    // =====================================================================
    // LIVE POLL — same pattern as mydashboard.php / revenue_history.php
    // =====================================================================
    var PROFIT_SPLIT_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('profit_split.php', base).toString();
        } catch (e) {
            return 'profit_split.php';
        }
    })();

    var isUpdating      = false;
    var updateInterval  = null;
    var retryCount      = 0;
    var MAX_RETRIES     = 5;
    var currentInterval = 1000;
    var pollRunning     = true;

    // ---- helpers ----
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    // ---- Build the inner HTML for the state block from the JSON payload ----
    function buildStateBlockHtml(data) {
        if (!data.has_programme) {
            return '' +
                '<div class="info-card">' +
                '  <h2>No Programme Investment</h2>' +
                '  <p>You are not currently invested in any programme. Please join a programme before profit splits are calculated.</p>' +
                '  <a href="programmes.php" class="back-btn" style="display: inline-block;">Explore Programmes</a>' +
                '</div>';
        }

        if (data.is_payment_made) {
            var badge = '';
            if (data.developer_name) {
                badge = '<div class="programme-badge">' + escapeHtml(data.developer_name) + "'s Programme" +
                        (data.programme_name ? ' • ' + escapeHtml(data.programme_name) : '') +
                        '</div>';
            }
            return '' +
                '<div class="payment-status info">' +
                '  <div>' +
                '    <div class="status-title">Payment Submitted</div>' +
                '    <p style="margin-top: 4px;">Your payment is pending confirmation from the server. Please check back later.</p>' +
                '  </div>' +
                '</div>' +
                '<div class="profit-card" style="text-align: center;">' +
                '  ' + badge +
                '  <div class="profit-label">Contract Profit</div>' +
                '  <div class="profit-amount">$' + escapeHtml(data.profit_to_split) + '</div>' +
                '  <div style="margin-top: 16px; color: var(--text-muted);">' +
                '    <p>Programme Share: <strong style="color: var(--cb-deep);">$' + escapeHtml(data.server_share) + '</strong></p>' +
                '    <p>Your Share: <strong style="color: var(--info);">$' + escapeHtml(data.user_share) + '</strong></p>' +
                '  </div>' +
                '  <div style="margin-top: 20px; padding: 16px; background: var(--warning-bg); border-radius: var(--radius-sm);">' +
                '    <p style="color: var(--warning); font-weight: 500;">Awaiting server confirmation...</p>' +
                '  </div>' +
                '</div>';
        }

        if (data.is_unpaid) {
            return buildPayableBlock(data, false);
        }

        if (data.is_failed) {
            return buildPayableBlock(data, true);
        }

        return '' +
            '<div class="info-card">' +
            '  <h2>No Active Profit Split Required</h2>' +
            '  <p>You don\'t have any pending profit split payments at this time.</p>' +
            '  <a href="app.php#mydashboard" class="back-btn" style="display: inline-block;">Return to Dashboard</a>' +
            '</div>';
    }

    function buildPayableBlock(data, isFailed) {
        var statusClass = isFailed ? 'danger' : 'warning';
        var statusTitle = isFailed ? 'Payment Failed' : 'Payment Required';
        var statusMsg   = isFailed
            ? 'Your previous payment attempt could not be verified. Please retry.'
            : 'Please complete the profit split payment to continue.';
        var ctaClass    = isFailed ? 'btn-retry' : 'btn-pay-server';
        var ctaLabel    = isFailed ? 'Retry Payment' : 'Pay Programme Share';
        var ctaHint     = isFailed
            ? ('Retry paying $' + escapeHtml(data.server_share) + ' to the programme')
            : ('Pay $' + escapeHtml(data.server_share) + ' to remain eligible for future contracts');

        var badge = '';
        if (data.developer_name) {
            badge = '<div class="programme-badge">' + escapeHtml(data.developer_name) + "'s Programme" +
                    (data.programme_name ? ' • ' + escapeHtml(data.programme_name) : '') +
                    '</div>';
        }

        var withdrawDisabled = (!data.broker_link) ? ' disabled' : '';
        var withdrawHref     = data.broker_link ? data.broker_target : 'about:blank';

        return '' +
            '<div class="payment-status ' + statusClass + '">' +
            '  <div>' +
            '    <div class="status-title">' + statusTitle + '</div>' +
            '    <p style="margin-top: 4px;">' + statusMsg + '</p>' +
            '  </div>' +
            '</div>' +
            '<div class="profit-card">' +
            '  ' + badge +
            '  <div class="profit-label">Contract Profit</div>' +
            '  <div class="profit-amount">$' + escapeHtml(data.profit_to_split) + '</div>' +
            '  <div class="split-grid">' +
            '    <div class="split-box user-box">' +
            '      <div class="split-percent">' + escapeHtml(String(data.user_share_percent)) + '%</div>' +
            '      <div class="split-label">Your Share</div>' +
            '      <div class="split-amount">$' + escapeHtml(data.user_share) + '</div>' +
            '    </div>' +
            '    <div class="split-box server-box">' +
            '      <div class="split-percent">' + escapeHtml(String(data.server_share_percent)) + '%</div>' +
            '      <div class="split-label">Programme Share</div>' +
            '      <div class="split-amount">$' + escapeHtml(data.server_share) + '</div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="split-actions">' +
            '    <a href="' + escapeHtml(withdrawHref) + '" target="_blank" class="btn-withdraw-profit' + withdrawDisabled + '">' +
            '      Withdraw Your Share' +
            '    </a>' +
            '    <button type="button" data-action="open-payment-modal" class="' + ctaClass + '">' +
            '      ' + ctaLabel +
            '    </button>' +
            '  </div>' +
            '  <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; margin-top: 12px;">' +
            '    ' + ctaHint +
            '  </p>' +
            '</div>';
    }

    // ---- Apply the payload to the DOM ----
    function refreshProfitSplitUI(data) {
        if (!data || !data.success) return;

        // Do NOT replace the state block while a modal is open — that
        // would tear down the modal's interactive elements.
        if (!modalOpen) {
            var block = document.getElementById('profitSplitStateBlock');
            if (block) {
                block.innerHTML = buildStateBlockHtml(data);
            }
        }

        // Keep the modal's displayed amount and hidden inputs in sync
        var hiddenAmount = document.getElementById('serverShareAmountHidden');
        if (hiddenAmount && data.server_share !== undefined) {
            hiddenAmount.value = parseFloat(data.server_share).toFixed(2);
        }
        var displayAmount = document.getElementById('paymentAmountDisplay');
        if (displayAmount && data.server_share !== undefined) {
            displayAmount.textContent = '$' + data.server_share;
        }
        var formAmount = document.getElementById('formServerShareAmount');
        if (formAmount && data.server_share !== undefined) {
            formAmount.value = parseFloat(data.server_share).toFixed(2);
        }
        var finalAmount = document.getElementById('finalConfirmAmount');
        if (finalAmount && data.server_share !== undefined) {
            finalAmount.innerHTML = '$' + data.server_share;
        }
    }

    // ---- Poll the server ----
    async function fetchProfitSplit() {
        if (isUpdating) return;
        isUpdating = true;

        try {
            var response = await fetch(PROFIT_SPLIT_POLL_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            var raw = await response.text();
            var data;
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                throw new Error('Non-JSON response');
            }

            if (data && data.success) {
                retryCount = 0;
                currentInterval = 1000;
                refreshProfitSplitUI(data);
            } else {
                retryCount++;
            }
        } catch (error) {
            retryCount++;
        } finally {
            isUpdating = false;
        }

        if (retryCount === 0)      currentInterval = 1000;
        else if (retryCount === 1) currentInterval = 3000;
        else if (retryCount === 2) currentInterval = 5000;
        else if (retryCount >= MAX_RETRIES) currentInterval = 15000;

        scheduleNextPoll();
    }

    function scheduleNextPoll() {
        if (!pollRunning) return;
        if (updateInterval) clearTimeout(updateInterval);
        updateInterval = setTimeout(fetchProfitSplit, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchProfitSplit();
    }

    function stopLiveUpdates() {
        pollRunning = false;
        if (updateInterval) { clearTimeout(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopLiveUpdates();
        } else {
            startLiveUpdates();
        }
    });

    window.addEventListener('focus', function() {
        if (pollRunning) fetchProfitSplit();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>

</body>
</html>