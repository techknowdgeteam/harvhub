<?php
session_start();
// mydashboard.php

// --- CHECK FOR REDIRECT TO APP ---
if (isset($_GET['redirect_to_app']) && $_GET['redirect_to_app'] == '1') {
    $queryParams = [];

    if (isset($_SESSION['apply_success_message']))   $queryParams['show_apply_success']  = '1';
    if (isset($_SESSION['reset_success_message']))   $queryParams['show_reset_success']  = '1';
    if (isset($_SESSION['enroll_success_message']))  $queryParams['show_enroll_success'] = '1';
    if (isset($_SESSION['toggle_success_message']))  $queryParams['show_toggle_success'] = '1';

    $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

    header("Location: app.php#mydashboard" . $queryString, true, 303);
    exit;
}

// --- Configuration and Connection ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_SESSION['prg_redirect_safe'])) {
        unset($_SESSION['prg_redirect_safe']);
    } else {
        unset($_SESSION['password_verified']);
        unset($_SESSION['password_error']);
        unset($_SESSION['reenroll_password_verified']);
        unset($_SESSION['reenroll_password_error']);
    }
}

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
$vpsTable               = "vps";
$vpsFollowersTable      = "vps_hosts_followers";
$vpsRequestorsTable     = "vps_hosts_requestors";
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

// ==================== FETCH SERVER ACCOUNT (FALLBACKS) ====================
$stmt = $pdo->prepare("SELECT * FROM $serverAccountTable WHERE id = 1 LIMIT 1");
$stmt->execute();
$serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$serverAccount) {
    die("Server configuration not found. Please contact administrator.");
}

$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// --- Server-side fallbacks ---
$SERVER_MIN_BROKER_BALANCE   = (float)($serverAccount['min_broker_balance'] ?? 0);
$SERVER_CONTRACT_DURATION    = (int)($serverAccount['contract_duration'] ?? 30);
$SERVER_SHARE_PERCENT        = (int)($serverAccount['server_share_percent'] ?? 30);
$SERVER_USER_SHARE_PERCENT   = (int)($serverAccount['user_share_percent'] ?? 70);
$SERVER_MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);
$MIN_INITIAL_DEPOSIT         = (float)($serverAccount['min_broker_balance'] ?? 0);

// ==================== FIND THE USER'S ACTIVE INVESTMENT (programme_investors) ====================
// A user can have many investments over time. We pick the most recent one.
// "Active" = status is 'active' (set at enrollment), OR no status filter — we
// treat the latest row as the current programme.
$activeInvestment = null;
$investmentDeveloper = null;
$investmentProgramme  = null;

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
        // Programme info
        if (!empty($activeInvestment['programme_id'])) {
            $stmtP = $pdo->prepare("SELECT * FROM $programmeTable WHERE id = ? LIMIT 1");
            $stmtP->execute([(int)$activeInvestment['programme_id']]);
            $investmentProgramme = $stmtP->fetch(PDO::FETCH_ASSOC);
        }

        // Developer info (harvhub row of the developer)
        if (!empty($activeInvestment['developerid'])) {
            $stmtD = $pdo->prepare("SELECT id, fullname, email FROM $tableName WHERE id = ? LIMIT 1");
            $stmtD->execute([(int)$activeInvestment['developerid']]);
            $investmentDeveloper = $stmtD->fetch(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    $activeInvestment = null;
}

$hasProgramme = ($activeInvestment && $activeInvestment['programme_id']);

// ==================== DERIVED VALUES (programme first, server fallback) ====================
// Each field falls back to the server_account value if the developer left it as 0/empty.
$CONTRACT_DURATION    = $SERVER_CONTRACT_DURATION;
$MIN_BROKER_BALANCE   = $SERVER_MIN_BROKER_BALANCE;
$MIN_INITIAL_DEPOSIT  = $SERVER_MIN_BROKER_BALANCE;
$SERVER_SHARE_PERCENT = $SERVER_SHARE_PERCENT; // default
$USER_SHARE_PERCENT   = $SERVER_USER_SHARE_PERCENT;
$MIN_PROFIT_FOR_SPLIT = $SERVER_MIN_PROFIT_FOR_SPLIT;
$PROGRAMME_NAME       = '';
$DEVELOPER_NAME       = '';
$DEVELOPER_ID         = 0;

if ($hasProgramme) {
    // Contract duration
    $pi_cd = (int)($activeInvestment['contract_duration'] ?? 0);
    if ($pi_cd > 0) $CONTRACT_DURATION = $pi_cd;

    // Minimum investment amount (= min broker balance floor for the investor)
    $pi_min = (float)($activeInvestment['minimum_investment_amount'] ?? 0);
    if ($pi_min > 0) {
        $MIN_BROKER_BALANCE  = $pi_min;
        $MIN_INITIAL_DEPOSIT = $pi_min;
    }

    // Developer / investor split
    $pi_dev = (int)($activeInvestment['developer_percentage'] ?? 0);
    $pi_inv = (int)($activeInvestment['investor_percentage'] ?? 0);
    if ($pi_dev > 0) $SERVER_SHARE_PERCENT = $pi_dev;
    if ($pi_inv > 0) $USER_SHARE_PERCENT   = $pi_inv;

    // Programme name + developer name
    if ($investmentProgramme && !empty($investmentProgramme['program_name'])) {
        $PROGRAMME_NAME = $investmentProgramme['program_name'];
    }
    if ($investmentDeveloper && !empty($investmentDeveloper['fullname'])) {
        $DEVELOPER_NAME = $investmentDeveloper['fullname'];
        $DEVELOPER_ID   = (int)$investmentDeveloper['id'];
    }
}

// Extract user data
$brokerBalance      = (float)($user['broker_balance'] ?? 0);
$profitAndLoss      = (float)($user['profitandloss'] ?? 0);
$executionStartDate = $user['execution_start_date'] ?? null;
$loyaltiesStatus    = $user['loyalties'] ?? null;
$resetContract      = (int)($user['reset_contract'] ?? 0);
$balanceVerificationStatus = $user['balance_verification'] ?? 'not-verified';

// ==================== VPS CHECK ====================
$userHasVps = false;

try {
    $stmt = $pdo->prepare("SELECT id FROM $vpsTable WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) $userHasVps = true;
} catch (PDOException $e) {}

if (!$userHasVps) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM $vpsFollowersTable WHERE follower_id = ? AND host_status = 'active' LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) $userHasVps = true;
    } catch (PDOException $e) {}
}

// ==================== LATEST REVENUE HISTORY ====================
function getLatestRevenueHistory($pdo, $revenueHistoryTable, $email) {
    $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
$latestRevenueRecord = getLatestRevenueHistory($pdo, $revenueHistoryTable, $email);

function updateRevenueHistoryLoyalties($pdo, $revenueHistoryTable, $email, $loyaltiesStatus, $paymentDetails = null) {
    $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $latestRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latestRecord) {
        $updateData = ['loyalties' => $loyaltiesStatus];
        if ($paymentDetails !== null) {
            $updateData['payment_details'] = $paymentDetails;
            $updateData['payment_date']    = date('Y-m-d H:i:s');
        }
        $setClauses = [];
        $params = [];
        foreach ($updateData as $key => $value) {
            $setClauses[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $latestRecord['id'];
        $updateStmt = $pdo->prepare("UPDATE $revenueHistoryTable SET " . implode(', ', $setClauses) . " WHERE id = ?");
        $updateStmt->execute($params);
    }
}

if ($loyaltiesStatus !== null && $latestRevenueRecord) {
    $latestRevenueLoyalty = $latestRevenueRecord['loyalties'] ?? null;
    if ($latestRevenueLoyalty !== $loyaltiesStatus) {
        updateRevenueHistoryLoyalties($pdo, $revenueHistoryTable, $email, $loyaltiesStatus);
        $latestRevenueRecord = getLatestRevenueHistory($pdo, $revenueHistoryTable, $email);
    }
}

// ==================== DASHBOARD STATE RESOLVER ====================
// Same hierarchy as before, but now with an extra top-level gate:
//   LEVEL 0a: Must have an active programme investment (programme_investors).
//             If no investment => show "Explore Programme" button.
//   LEVEL 0b: Must have VPS.
//   LEVEL 1 : Payment conditions.
//   LEVEL 2 : Broker connection.
//   LEVEL 3 : Core states.
function determineDashboardState(array $u, array $cfg, $latestRevenueRecord, bool $userHasVps = true, bool $hasProgramme = true): array
{
    $brokerBalance      = (float)($u['broker_balance'] ?? 0);
    $profitAndLoss      = (float)($u['profitandloss'] ?? 0);
    $loyaltiesStatus    = $u['loyalties'] ?? null;
    $executionStartDate = $u['execution_start_date'] ?? null;
    $balanceVerif       = $u['balance_verification'] ?? 'not-verified';
    $resetContract      = (int)($u['reset_contract'] ?? 0);
    $brokerConnected    = (!empty($u['broker']) && !empty($u['server']) && !empty($u['login']));
    $applicationStatus  = $u['application_status'] ?? '';

    $minDeposit     = (float)($cfg['min_broker_balance'] ?? 0);
    $contractDur    = (int)($cfg['contract_duration'] ?? 30);
    $minProfitSplit = (float)($cfg['min_profit_for_split'] ?? 0);

    $state = [
        'show_get_vps'          => false,
        'show_explore_programme'=> false,
        'show_reset_button'     => false,
        'show_apply_button'     => false,
        'show_reenroll_button'  => false,
        'show_payment_note'     => false,
        'show_payment_failed'   => false,
        'show_profit_split'     => false,
        'show_withdraw_buttons' => false,
        'show_connect_broker'   => false,
        'show_find_manager'     => false,
        'show_payment_confirmed_notice' => false,
        'loyalties_message'     => '',
        'loyalty_text'          => '',
        'dashboard_disclaimer'  => '',
        'loyalty_btn_text'      => '',
        'loyalty_btn_class'     => '',
        'loyalty_btn_action'    => '',
    ];

    // =====================================================================
    // LEVEL 0a: NO PROGRAMME INVESTMENT => EXPLORE PROGRAMME
    // =====================================================================
    if (!$hasProgramme) {
        $state['show_explore_programme'] = true;
        $state['loyalties_message']   = "No Programme Joined";
        $state['loyalty_text']        = "You haven't invested in any developer programme yet. Explore programmes to start your investment journey.";
        $state['dashboard_disclaimer']= "Join a programme to get started.";
        $state['loyalty_btn_text']    = "Explore Programme";
        $state['loyalty_btn_class']   = "btn-loyalty-action btn-explore-programme";
        $state['loyalty_btn_action']  = "explore_programme";
        return $state;
    }

    // =====================================================================
    // LEVEL 0b: VPS REQUIRED
    // =====================================================================
    if (!$userHasVps) {
        $state['show_get_vps'] = true;
        $state['loyalties_message']   = "VPS Required";
        $state['loyalty_text']        = "You need a Virtual Private Server to run automated trading. Get one to unlock your dashboard.";
        $state['dashboard_disclaimer']= "VPS required before proceeding.";
        $state['loyalty_btn_text']    = "Get VPS";
        $state['loyalty_btn_class']   = "btn-loyalty-action btn-get-vps";
        $state['loyalty_btn_action']  = "get_vps";
        return $state;
    }

    // =====================================================================
    // LEVEL 1: PAYMENT CONDITIONS
    // =====================================================================
    $allLoyaltyStatuses = [];
    if ($loyaltiesStatus !== null) $allLoyaltyStatuses[] = $loyaltiesStatus;
    if ($latestRevenueRecord && isset($latestRevenueRecord['loyalties']) && $latestRevenueRecord['loyalties'] !== null) {
        $allLoyaltyStatuses[] = $latestRevenueRecord['loyalties'];
    }
    $allLoyaltyStatuses = array_unique($allLoyaltyStatuses);

    $paymentMadeStatuses = ['payment-made', 'contract-cancelled-payment-made'];
    $failedStatuses      = ['payment-failed', 'failed-payment', 'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed'];
    $unpaidStatuses      = ['unpaid-payment', 'unpaid', 'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment', 'contract-cancelled-payment-required'];
    $confirmedStatuses   = ['payment-confirmed'];

    foreach ($allLoyaltyStatuses as $status) {
        if (in_array($status, $paymentMadeStatuses)) {
            $state['show_payment_note'] = true;
            $state['loyalties_message'] = "Payment Pending Confirmation";
            $state['loyalty_text']      = "Your payment has been recorded. Waiting for server confirmation.";
            $state['dashboard_disclaimer'] = "Payment submitted for verification.";
            $state['loyalty_btn_text']  = "Awaiting Confirmation";
            $state['loyalty_btn_class'] = "btn-loyalty-paid";
            $state['loyalty_btn_action']= "";
            return $state;
        }
    }

    foreach ($allLoyaltyStatuses as $status) {
        if (in_array($status, $failedStatuses)) {
            $state['show_payment_failed'] = true;
            $state['loyalties_message'] = "Payment Failed";
            $state['loyalty_text']      = "Your previous payment attempt failed. Please retry.";
            $state['dashboard_disclaimer'] = "Payment verification failed!";
            $state['loyalty_btn_text']  = "Retry Payment";
            $state['loyalty_btn_class'] = "btn-loyalty-action";
            $state['loyalty_btn_action']= "payment_failed_redirect";
            return $state;
        }
    }

    foreach ($allLoyaltyStatuses as $status) {
        if (in_array($status, $unpaidStatuses)) {
            $state['show_profit_split'] = true;
            $state['loyalties_message'] = "Payment Required";
            $state['loyalty_text']      = "Profit split payment is required. Click below to complete payment.";
            $state['dashboard_disclaimer'] = "Payment required!";
            $state['loyalty_btn_text']  = "Make Profit Split";
            $state['loyalty_btn_class'] = "btn-loyalty-action";
            $state['loyalty_btn_action']= "profit_split_redirect";
            return $state;
        }
    }

    foreach ($allLoyaltyStatuses as $status) {
        if (in_array($status, $confirmedStatuses)) {
            $state['show_payment_confirmed_notice'] = true;
            break;
        }
    }

    // =====================================================================
    // LEVEL 2: BROKER CONNECTION
    // =====================================================================
    if (!$brokerConnected) {
        $state['show_connect_broker'] = true;
        $state['loyalties_message']   = "Broker Not Connected";
        $state['loyalty_text']        = "Connect your broker account to get started with trading.";
        $state['dashboard_disclaimer']= "Broker connection required.";
        $state['loyalty_btn_text']    = "Connect Broker";
        $state['loyalty_btn_class']   = "btn-loyalty-action btn-connect-broker";
        $state['loyalty_btn_action']  = "connect_broker";
        return $state;
    }

    // =====================================================================
    // LEVEL 3: CHILD STATES
    // =====================================================================
    if ($resetContract === 1) {
        $state['show_reset_button']    = true;
        $state['dashboard_disclaimer'] = "Time for the Next Phase!";
        $state['loyalties_message']    = "Ready for a new Contract?";
        $state['loyalty_text']         = "Your path is clear. Click below to embark on your next contract.";
        $state['loyalty_btn_text']     = "Let's get started";
        $state['loyalty_btn_class']    = "btn-loyalty-action btn-reset";
        $state['loyalty_btn_action']   = "reset";
        return $state;
    }

    if ($balanceVerif === 'not-verified' || $balanceVerif === '' || $balanceVerif === null) {
        $state['show_apply_button']    = true;
        $state['loyalties_message']    = "Balance Verification Required";
        $state['loyalty_text']         = "Please apply for verification now if you have deposited funds.";
        $state['dashboard_disclaimer'] = "Balance verification required. Apply if you have funded your broker account.";
        $state['loyalty_btn_text']     = "Apply for Verification";
        $state['loyalty_btn_class']    = "btn-loyalty-action";
        $state['loyalty_btn_action']   = "apply";
        return $state;
    }

    if ($balanceVerif === 'applied-for-verification') {
        $state['loyalties_message']    = "Balance Verification Pending";
        $state['loyalty_text']         = "Your account is pending balance review. This check usually takes between 24 and 48 hours.";
        $state['dashboard_disclaimer'] = "Balance verification in progress.";
        $state['loyalty_btn_text']     = "Under Review";
        $state['loyalty_btn_class']    = "btn-loyalty-paid";
        $state['loyalty_btn_action']   = "";
        return $state;
    }

    if ($balanceVerif === 'verified') {
        $isExecutionEmpty  = ($executionStartDate === null || $executionStartDate === '' || $executionStartDate === '0000-00-00');
        $isContractActive  = false;
        $contractCompleted = false;
        $contractDaysLeft  = 0;

        if (!$isExecutionEmpty) {
            $start = new DateTime($executionStartDate);
            $end   = clone $start;
            $end->modify("+{$contractDur} days");

            $today = new DateTime(); $today->setTime(0, 0, 0);
            $endClone = clone $end; $endClone->setTime(0, 0, 0);

            $contractDaysLeft = (int)$today->diff($endClone)->format('%r%a');

            if ($contractDaysLeft <= 0) $contractCompleted = true;
            else                        $isContractActive = true;
        }

        if ($isContractActive) {
            $state['loyalties_message']    = "Contract Active";
            $state['loyalty_text']         = $contractDaysLeft . " days left.";
            $state['dashboard_disclaimer'] = "Trading is active.";
            $state['loyalty_btn_text']     = "Active";
            $state['loyalty_btn_class']    = "btn-loyalty-confirmed";
            $state['loyalty_btn_action']   = "";
            return $state;
        }

        if ($contractCompleted) {
            if ($profitAndLoss > $minProfitSplit) {
                $state['show_profit_split'] = true;
                $state['loyalties_message'] = "Contract Ended - Payment Required";
                $state['loyalty_text'] = "Your contract has ended with a profit of $" . number_format($profitAndLoss, 2) . ". Please complete the profit split.";
                $state['dashboard_disclaimer'] = "Contract completed - Profit split required!";
                $state['loyalty_btn_text'] = "Make Profit Split";
                $state['loyalty_btn_class'] = "btn-loyalty-action";
                $state['loyalty_btn_action'] = "profit_split_redirect";
                return $state;
            }

            $state['show_reenroll_button'] = true;
            $state['loyalty_btn_text']     = "Enroll";
            $state['loyalty_btn_class']    = "btn-loyalty-action";
            $state['loyalty_btn_action']   = "enroll";

            if ($profitAndLoss < 0) {
                $state['loyalties_message']    = "Ready for New Contract";
                $state['loyalty_text']         = "Don't give up! Every loss is a learning opportunity. Click Enroll to start a new contract.";
                $state['dashboard_disclaimer'] = "Contract completed with loss. You can start a new contract.";
            } elseif ($profitAndLoss > 0) {
                $state['loyalties_message']    = "Ready for New Contract";
                $state['loyalty_text']         = "Profit of $" . number_format($profitAndLoss, 2) . " is below the split threshold. You keep 100% of the profit.";
                $state['dashboard_disclaimer'] = "Contract completed. Profit below split threshold.";
            } else {
                $state['loyalties_message']    = "Ready for New Contract";
                $state['loyalty_text']         = "Your contract has ended with no profit. Click Enroll to start a new contract.";
                $state['dashboard_disclaimer'] = "Contract completed with no profit.";
            }
            return $state;
        }

        if ($brokerBalance < $minDeposit) {
            $state['loyalties_message']    = "Deposit Required";
            $state['loyalty_text']         = "Your balance is below the minimum deposit of $" . number_format($minDeposit, 2) . ".";
            $state['dashboard_disclaimer'] = "Minimum deposit required before enrollment.";
            $state['loyalty_btn_text']     = "Deposit Funds";
            $state['loyalty_btn_class']    = "btn-loyalty-action";
            $state['loyalty_btn_action']   = "deposit";
            return $state;
        }

        $state['show_reenroll_button'] = true;
        $state['loyalties_message']    = "Ready to Start";
        $state['loyalty_text']         = "Click Enroll to start a new trading contract.";
        $state['dashboard_disclaimer'] = "No active contract.";
        $state['loyalty_btn_text']     = "Enroll";
        $state['loyalty_btn_class']    = "btn-loyalty-action";
        $state['loyalty_btn_action']   = "enroll";
        return $state;
    }

    // Fallback
    $state['show_reenroll_button'] = true;
    $state['loyalties_message']    = "Ready to Start";
    $state['loyalty_text']         = "Click Enroll to start a new trading contract.";
    $state['dashboard_disclaimer'] = "No active contract.";
    $state['loyalty_btn_text']     = "Enroll";
    $state['loyalty_btn_class']    = "btn-loyalty-action";
    $state['loyalty_btn_action']   = "enroll";
    return $state;
}

$state = determineDashboardState(
    $user,
    [
        'min_broker_balance'   => $MIN_BROKER_BALANCE,
        'contract_duration'    => $CONTRACT_DURATION,
        'min_profit_for_split' => $MIN_PROFIT_FOR_SPLIT,
    ],
    $latestRevenueRecord,
    $userHasVps,
    (bool)$hasProgramme
);

$show_get_vps          = $state['show_get_vps'] ?? false;
$show_explore_programme= $state['show_explore_programme'] ?? false;
$show_reset_button     = $state['show_reset_button'];
$show_apply_button     = $state['show_apply_button'];
$show_reenroll_button  = $state['show_reenroll_button'];
$show_payment_note     = $state['show_payment_note'];
$show_payment_failed   = $state['show_payment_failed'];
$showProfitSplit       = $state['show_profit_split'];
$showWithdrawButtons   = $state['show_withdraw_buttons'];
$show_connect_broker   = $state['show_connect_broker'] ?? false;
$show_find_manager     = $state['show_find_manager'] ?? false;
$show_payment_confirmed_notice = $state['show_payment_confirmed_notice'] ?? false;
$loyalties_message     = $state['loyalties_message'];
$loyalty_text          = $state['loyalty_text'];
$dashboard_disclaimer  = $state['dashboard_disclaimer'];
$loyalty_btn_text      = $state['loyalty_btn_text'];
$loyalty_btn_class     = $state['loyalty_btn_class'];
$loyalty_btn_action    = $state['loyalty_btn_action'];

// --- Contract date display values ---
$formatted_start_date = "Not started";
$formatted_end_date   = "Not started";
$contractDaysLeft     = 0;
$is_contract_active   = false;
$contract_completed   = false;

if ($executionStartDate && $executionStartDate !== '0000-00-00') {
    $start = new DateTime($executionStartDate);
    $formatted_start_date = $start->format('M d, Y');

    $end = clone $start;
    $end->modify("+{$CONTRACT_DURATION} days");
    $formatted_end_date = $end->format('M d, Y');

    $today = new DateTime(); $today->setTime(0, 0, 0);
    $end_clone = clone $end; $end_clone->setTime(0, 0, 0);

    $interval = $today->diff($end_clone);
    $contractDaysLeft = (int)$interval->format('%r%a');

    if ($contractDaysLeft <= 0) {
        $contract_completed = true;
        $is_contract_active = false;
    } else {
        $is_contract_active = true;
    }
}

// --- Balance card display status ---
$balance_unverified         = false;
$balance_under_verification = false;
$balance_check_failed       = false;

if ($balanceVerificationStatus === 'not-verified' || empty($balanceVerificationStatus)) {
    $balance_unverified = true;
} elseif ($balanceVerificationStatus === 'applied-for-verification') {
    $balance_under_verification = true;
} elseif ($balanceVerificationStatus === 'verified' && $brokerBalance < $MIN_INITIAL_DEPOSIT) {
    $balance_check_failed = true;
}

// Extract remaining user data
$fullName        = $user['fullname'];
$login           = $user['login'] ?? 'N/A';
$server          = $user['server'] ?? 'N/A';
$balanceDisplay  = $user['balance_display'] ?? 'show';
$broker          = strtolower($user['broker'] ?? 'unknown');
$tradesString    = $user['trades'] ?? '';
$broker_connected = (!empty($user['broker']) && !empty($user['server']) && !empty($user['login']));
$application_status = $user['application_status'] ?? '';

// --- BALANCE CALCULATIONS ---
$depositBalance = $brokerBalance;
$currentBalance = $brokerBalance + $profitAndLoss;

$profitToSplit = max(0, $profitAndLoss);
$serverShare   = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
$userShare     = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);

// --- Determine Deposit Link ---
// --- Determine Deposit Link (uses same logic as connect_investor_broker.php) ---
$brokerLink   = '';
$brokerLinks  = [];   // keyed by formatted broker name, e.g. "Bybit" => "bybit.com"

if (!empty($serverAccount['brokers_link']) && !empty($serverAccount['brokers'])) {
    // Parse brokers_link: "Bybit:https://bybit.com, Exness:https://exness.com"
    $raw_links   = explode(',', $serverAccount['brokers_link']);
    $raw_brokers = explode(',', $serverAccount['brokers']);

    // Extract clean domain names from each link entry
    $cleaned_links = [];
    foreach ($raw_links as $link) {
        $link = trim($link);
        if ($link === '') continue;

        // Drop the "broker:" prefix if present
        if (strpos($link, ':') !== false) {
            $link = trim(substr($link, strrpos($link, ':') + 1));
        }

        // Grab the bare domain (e.g. bybit.com)
        if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,})/', $link, $matches)) {
            $link = $matches[1];
        }

        $link = strtolower(trim($link));
        if (!empty($link)) $cleaned_links[] = $link;
    }

    // Pair each broker name with its cleaned link
    foreach ($raw_brokers as $index => $entry) {
        $entry = trim($entry);
        if ($entry === '') continue;

        $broker_name = (strpos($entry, ':') !== false)
            ? trim(substr($entry, strrpos($entry, ':') + 1))
            : $entry;
        $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);
        $broker_name = trim($broker_name);
        $broker_name_clean = strtolower($broker_name);

        if ($broker_name === '') continue;

        $formatted_name = ucfirst($broker_name);
        $link = isset($cleaned_links[$index]) ? $cleaned_links[$index] : '';

        // Fallback: try to match by broker name inside any cleaned link
        if (empty($link)) {
            foreach ($cleaned_links as $cleaned_link) {
                if (strpos($cleaned_link, $broker_name_clean) !== false) {
                    $link = $cleaned_link;
                    break;
                }
            }
        }

        if (!isset($brokerLinks[$formatted_name])) {
            $brokerLinks[$formatted_name] = $link;
        }
    }
}

// Match the user's current broker (case-insensitive) to a link
$userBrokerNormalized = strtolower(trim($broker));   // e.g. "bybit"
$matchedLink = '';

foreach ($brokerLinks as $name => $link) {
    if (strtolower($name) === $userBrokerNormalized) {
        $matchedLink = $link;
        break;
    }
}

// Fallback: try to find any broker key that contains the user's broker name
if (empty($matchedLink)) {
    foreach ($brokerLinks as $name => $link) {
        if (strpos(strtolower($name), $userBrokerNormalized) !== false) {
            $matchedLink = $link;
            break;
        }
    }
}

// Last-resort fallbacks: harvhub, then any link at all
if (empty($matchedLink) && isset($brokerLinks['harvhub'])) {
    $matchedLink = $brokerLinks['harvhub'];
}
if (empty($matchedLink) && !empty($brokerLinks)) {
    $first = reset($brokerLinks);
    if (!empty($first)) $matchedLink = $first;
}

// Build the final URL (https:// prefix if missing)
if (!empty($matchedLink)) {
    $brokerLink = (strpos($matchedLink, '://') === false) ? 'https://' . $matchedLink : $matchedLink;
}

$brokerTarget = !empty($brokerLink) ? htmlspecialchars($brokerLink) : 'about:blank';

// ==================== POST HANDLING ====================

// Toggle Balance Display
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_balance_display'])) {
    $currentStatus = $user['balance_display'];
    $newStatus = ($currentStatus === 'show') ? 'hide' : 'show';

    $upd = $pdo->prepare("UPDATE $tableName SET balance_display = ? WHERE email = ?");
    $upd->execute([$newStatus, $email]);

    if ($newStatus === 'show') unset($_SESSION['password_verified']);
    unset($_SESSION['password_error']);
    $_SESSION['prg_redirect_safe'] = true;
    $_SESSION['toggle_success_message'] = "Balance display toggled to " . ucfirst($newStatus) . ".";

    header("Location: mydashboard.php?redirect_to_app=1", true, 303);
    exit;
}

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reenroll'])) {
    $today   = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+{$CONTRACT_DURATION} days", strtotime($today)));

    $startFormatted = date('dmY', strtotime($today));
    $endFormatted   = date('dmY', strtotime($endDate));
    $contractId     = "sd-{$startFormatted}-ed-{$endFormatted}";

    $stmt = $pdo->prepare("SELECT broker_balance FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $currentData     = $stmt->fetch(PDO::FETCH_ASSOC);
    $startingBalance = (float)($currentData['broker_balance'] ?? 0);

    $insertStmt = $pdo->prepare("
        INSERT INTO $revenueHistoryTable
        (user_email, contract_id, execution_start_date, execution_end_date, starting_balance, current_balance, profit, user_share, server_share, loyalties, invested_with)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertStmt->execute([
        $email,
        $contractId,
        $today,
        $endDate,
        $startingBalance,
        $startingBalance,
        0,
        0,
        0,
        'active',
        $DEVELOPER_NAME ?: null
    ]);

    $upd = $pdo->prepare("UPDATE $tableName SET loyalties = NULL, profitandloss = 0, execution_start_date = ?, contract_id = ?, reset_contract = 0 WHERE email = ?");
    $upd->execute([$today, $contractId, $email]);

    unset($_SESSION['reenroll_password_verified']);
    unset($_SESSION['reenroll_password_verified_time']);

    $_SESSION['prg_redirect_safe'] = true;
    $_SESSION['enroll_success_message'] = "Contract enrolled successfully! Your " . $CONTRACT_DURATION . "-day contract has started.";

    header("Location: mydashboard.php?redirect_to_app=1", true, 303);
    exit;
}

// Handle Apply for Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_for_verification'])) {
    try {
        $upd = $pdo->prepare("UPDATE $tableName SET balance_verification = 'applied-for-verification' WHERE email = ?");
        $upd->execute([$email]);

        $_SESSION['apply_success_message'] = "Your application has been submitted successfully!";
        $_SESSION['apply_success_details'] = "Our team will verify your account. Please ensure you have deposited the minimum required amount of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . ".";
        $_SESSION['prg_redirect_safe'] = true;

        header("Location: mydashboard.php?redirect_to_app=1", true, 303);
        exit;
    } catch (Exception $e) {
        $_SESSION['apply_error'] = "An error occurred. Please try again.";
        header("Location: mydashboard.php?redirect_to_app=1", true, 303);
        exit;
    }
}

// Handle Reset Contract
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset_contract'])) {
    if ($latestRevenueRecord && $latestRevenueRecord['loyalties'] !== 'payment-confirmed') {
        updateRevenueHistoryLoyalties($pdo, $revenueHistoryTable, $email, 'payment-confirmed');
    }

    $resetData = [
        'broker_balance'      => 0,
        'profitandloss'       => 0,
        'contract_id'         => NULL,
        'execution_start_date'=> NULL,
        'balance_verification'=> 'not-verified',
        'reset_contract'      => 0,
        'recent_highest_balance' => NULL,
        'recent_highest_balance_last_update' => NULL,
        'loyalties'           => NULL
    ];

    $setClauses = []; $params = [];
    foreach ($resetData as $key => $value) {
        $setClauses[] = "$key = ?";
        $params[] = $value;
    }
    $params[] = $email;

    $upd = $pdo->prepare("UPDATE $tableName SET " . implode(', ', $setClauses) . " WHERE email = ?");
    $upd->execute($params);

    $_SESSION['prg_redirect_safe'] = true;
    $_SESSION['reset_success_message'] = "Your contract has been reset successfully. Please apply for verification to start a new contract.";

    header("Location: mydashboard.php?redirect_to_app=1", true, 303);
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

// ==================== NOTIFICATIONS ====================
$notifications = [];
$unreadCount = 0;

if (!empty($user['notifications'])) {
    $notificationsData = json_decode($user['notifications'], true);
    if (is_array($notificationsData)) {
        foreach ($notificationsData as $id => $notification) {
            if (isset($notification['update']) && $notification['update'] === 'new') $unreadCount++;

            $message = $notification['message'] ?? '';
            $message = preg_replace('/^[\?\?]+\s*/', '', $message);
            $message = preg_replace('/[\?\?]/', '', $message);

            $notifications[] = [
                'id'      => $id,
                'section' => $notification['section'] ?? 'General',
                'message' => $message,
                'time'    => $notification['time'] ?? date('Y-m-d H:i:s'),
                'type'    => $notification['type'] ?? 'info',
                'update'  => $notification['update'] ?? 'read'
            ];
        }
        usort($notifications, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
    }
}

// AJAX: mark read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notifications_read'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    $email = strtolower($_SESSION['user_email']);
    $stmt = $pdo->prepare("SELECT notifications FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $currentNotifications = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($currentNotifications && !empty($currentNotifications['notifications'])) {
        $notificationsData = json_decode($currentNotifications['notifications'], true);
        if (is_array($notificationsData)) {
            foreach ($notificationsData as $id => &$notification) {
                if ($notification['update'] === 'new') $notification['update'] = 'read';
            }
            $updatedNotifications = json_encode($notificationsData);
            $upd = $pdo->prepare("UPDATE $tableName SET notifications = ? WHERE email = ?");
            $upd->execute([$updatedNotifications, $email]);
            echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'No notifications to mark']);
    exit;
}

// AJAX: check new
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_new_notifications'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    $email = strtolower($_SESSION['user_email']);
    $stmt = $pdo->prepare("SELECT notifications FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $currentNotifications = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread = 0;
    if ($currentNotifications && !empty($currentNotifications['notifications'])) {
        $notificationsData = json_decode($currentNotifications['notifications'], true);
        if (is_array($notificationsData)) {
            foreach ($notificationsData as $notification) {
                if (isset($notification['update']) && $notification['update'] === 'new') $unread++;
            }
        }
    }
    echo json_encode(['success' => true, 'unread_count' => $unread]);
    exit;
}

// AJAX: get list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_notifications_list'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit; }
    $email = strtolower($_SESSION['user_email']);
    $stmt = $pdo->prepare("SELECT notifications FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notifications = [];
    $unread = 0;
    if (!empty($result['notifications'])) {
        $notificationsData = json_decode($result['notifications'], true);
        if (is_array($notificationsData)) {
            foreach ($notificationsData as $id => $notification) {
                if (isset($notification['update']) && $notification['update'] === 'new') $unread++;
                $message = $notification['message'] ?? '';
                $message = preg_replace('/^[\?\?]+\s*/', '', $message);
                $message = preg_replace('/[\?\?]/', '', $message);
                $message = preg_replace('/[\x{1F300}-\x{1F6FF}]/u', '', $message);
                $notifications[] = [
                    'id'      => $id,
                    'section' => $notification['section'] ?? 'General',
                    'message' => trim($message),
                    'time'    => $notification['time'] ?? date('Y-m-d H:i:s'),
                    'type'    => $notification['type'] ?? 'info',
                    'update'  => $notification['update'] ?? 'read'
                ];
            }
            usort($notifications, function($a, $b) {
                return strtotime($b['time']) - strtotime($a['time']);
            });
        }
    }
    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
    exit;
}

// ==================== AJAX: LIVE STATE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) { echo json_encode(['error' => 'Unauthorized']); exit; }

    $email = strtolower($_SESSION['user_email']);

    $stmt = $pdo->prepare("SELECT id, broker_balance, profitandloss, loyalties, execution_start_date, broker, server, login, application_status, balance_verification, reset_contract FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($liveUser) {
        // Re-resolve active investment on each poll
        $liveInvestment = null;
        try {
            $s = $pdo->prepare("SELECT * FROM $programmeInvestorsTable WHERE investorid = ? ORDER BY invested_at DESC, id DESC LIMIT 1");
            $s->execute([(int)$liveUser['id']]);
            $liveInvestment = $s->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        $liveHasProgramme = ($liveInvestment && !empty($liveInvestment['programme_id']));

        // Resolve per-programme overrides on each poll
        $liveContractDuration    = (int)($serverAccount['contract_duration'] ?? 30);
        $liveMinBrokerBalance    = (float)($serverAccount['min_broker_balance'] ?? 0);
        $liveMinInitialDeposit   = $liveMinBrokerBalance;
        $liveMinProfitSplit      = (float)($serverAccount['min_profit_for_split'] ?? 30);
        $liveServerSharePercent  = (int)($serverAccount['server_share_percent'] ?? 30);
        $liveUserSharePercent    = (int)($serverAccount['user_share_percent'] ?? 70);

        if ($liveHasProgramme) {
            $pi_cd  = (int)($liveInvestment['contract_duration'] ?? 0);
            $pi_min = (float)($liveInvestment['minimum_investment_amount'] ?? 0);
            $pi_dev = (int)($liveInvestment['developer_percentage'] ?? 0);
            $pi_inv = (int)($liveInvestment['investor_percentage'] ?? 0);
            if ($pi_cd  > 0) $liveContractDuration   = $pi_cd;
            if ($pi_min > 0) { $liveMinBrokerBalance = $pi_min; $liveMinInitialDeposit = $pi_min; }
            if ($pi_dev > 0) $liveServerSharePercent = $pi_dev;
            if ($pi_inv > 0) $liveUserSharePercent   = $pi_inv;
        }

        // VPS check
        $liveUserId = (int)$liveUser['id'];
        $liveUserHasVps = false;
        try {
            $s = $pdo->prepare("SELECT id FROM $vpsTable WHERE user_id = ? LIMIT 1");
            $s->execute([$liveUserId]);
            if ($s->fetch(PDO::FETCH_ASSOC)) $liveUserHasVps = true;
        } catch (PDOException $e) {}
        if (!$liveUserHasVps) {
            try {
                $s = $pdo->prepare("SELECT id FROM $vpsFollowersTable WHERE follower_id = ? AND host_status = 'active' LIMIT 1");
                $s->execute([$liveUserId]);
                if ($s->fetch(PDO::FETCH_ASSOC)) $liveUserHasVps = true;
            } catch (PDOException $e) {}
        }

        $latestRevenueRecord = getLatestRevenueHistory($pdo, $revenueHistoryTable, $email);

        $brokerBalance      = (float)($liveUser['broker_balance'] ?? 0);
        $profitAndLoss      = (float)($liveUser['profitandloss'] ?? 0);
        $currentBalance     = $brokerBalance + $profitAndLoss;
        $executionStartDate = $liveUser['execution_start_date'] ?? null;
        $loyaltiesStatus    = $liveUser['loyalties'] ?? null;
        $balanceVerificationStatus = $liveUser['balance_verification'] ?? 'not-verified';
        $resetContractStatus = (int)($liveUser['reset_contract'] ?? 0);

        $contractDaysLeft = 0;
        $is_contract_active = false;
        $contract_completed = false;
        $formatted_start_date = null;
        $formatted_end_date   = null;

        if ($executionStartDate && $executionStartDate !== '0000-00-00' && $executionStartDate !== null) {
            $start = new DateTime($executionStartDate);
            $formatted_start_date = $start->format('M d, Y');

            $end = clone $start;
            $end->modify("+{$liveContractDuration} days");
            $formatted_end_date = $end->format('M d, Y');

            $today = new DateTime(); $today->setTime(0, 0, 0);
            $end_clone = clone $end; $end_clone->setTime(0, 0, 0);
            $interval = $today->diff($end_clone);
            $contractDaysLeft = (int)$interval->format('%r%a');

            if ($contractDaysLeft <= 0) { $contract_completed = true; $is_contract_active = false; }
            else                        { $is_contract_active = true; }
        }

        $ajaxState = determineDashboardState(
            $liveUser,
            [
                'min_broker_balance'   => $liveMinBrokerBalance,
                'contract_duration'    => $liveContractDuration,
                'min_profit_for_split' => $liveMinProfitSplit,
            ],
            $latestRevenueRecord,
            $liveUserHasVps,
            (bool)$liveHasProgramme
        );

        // Recompute live developer name for programme if present
        $liveDeveloperName = '';
        if ($liveHasProgramme && !empty($liveInvestment['developerid'])) {
            try {
                $d = $pdo->prepare("SELECT fullname FROM $tableName WHERE id = ? LIMIT 1");
                $d->execute([(int)$liveInvestment['developerid']]);
                $rowD = $d->fetch(PDO::FETCH_ASSOC);
                if ($rowD) $liveDeveloperName = $rowD['fullname'] ?? '';
            } catch (PDOException $e) {}
        }

        echo json_encode([
            'success'                       => true,
            'deposit_balance'               => number_format($brokerBalance, 2),
            'profit_loss'                   => number_format($profitAndLoss, 2),
            'current_balance'               => number_format($currentBalance, 2),
            'profit_loss_class'             => $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative',
            'current_balance_class'         => $currentBalance >= 0 ? 'profit-positive' : 'profit-negative',
            'contract_days_left'            => $is_contract_active ? $contractDaysLeft : 0,
            'is_contract_active'            => $is_contract_active,
            'contract_completed'            => $contract_completed,
            'formatted_start_date'          => $formatted_start_date,
            'formatted_end_date'            => $formatted_end_date,
            'loyalties_status'              => $loyaltiesStatus,
            'balance_verification_status'   => $balanceVerificationStatus,
            'reset_contract'                => $resetContractStatus,
            'show_explore_programme'        => $ajaxState['show_explore_programme'] ?? false,
            'show_get_vps'                  => $ajaxState['show_get_vps'] ?? false,
            'show_reset_button'             => $ajaxState['show_reset_button'],
            'show_apply_button'             => $ajaxState['show_apply_button'],
            'show_reenroll_button'          => $ajaxState['show_reenroll_button'],
            'show_payment_note'             => $ajaxState['show_payment_note'],
            'show_payment_failed'           => $ajaxState['show_payment_failed'],
            'show_connect_broker'           => $ajaxState['show_connect_broker'] ?? false,
            'show_find_manager'             => $ajaxState['show_find_manager'] ?? false,
            'show_payment_confirmed_notice' => $ajaxState['show_payment_confirmed_notice'] ?? false,
            'loyalties_message'             => $ajaxState['loyalties_message'],
            'loyalty_text'                  => $ajaxState['loyalty_text'],
            'loyalty_btn_text'              => $ajaxState['loyalty_btn_text'],
            'loyalty_btn_class'             => $ajaxState['loyalty_btn_class'],
            'loyalty_btn_action'            => $ajaxState['loyalty_btn_action'],
            'dashboard_disclaimer'          => $ajaxState['dashboard_disclaimer'],
            'broker'                        => strtolower($liveUser['broker'] ?? 'unknown'),
            'profit_to_split'               => number_format(max(0, $profitAndLoss), 2),
            'balance_check_failed'          => ($balanceVerificationStatus === 'verified' && $brokerBalance < $liveMinInitialDeposit),
            'min_initial_deposit'           => $liveMinInitialDeposit,
            'broker_connected'              => (!empty($liveUser['broker']) && !empty($liveUser['server']) && !empty($liveUser['login'])),
            'application_status'            => $liveUser['application_status'] ?? '',
            'user_has_vps'                  => $liveUserHasVps,
            'has_programme'                 => $liveHasProgramme,
            'programme_name'                => $liveHasProgramme && $liveInvestment ? ($liveInvestment['programme_id']) : null,
            'developer_name'                => $liveDeveloperName,
            'contract_duration'             => $liveContractDuration,
            'min_broker_balance'            => $liveMinBrokerBalance,
            'server_share_percent'          => $liveServerSharePercent,
            'user_share_percent'            => $liveUserSharePercent
        ]);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    exit;
}

// ==================== MAP ACTION TO ONCLICK ====================
$loyalty_btn_onclick = '';
switch ($loyalty_btn_action) {
    case 'explore_programme':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'programmes.php\'"';
        break;
    case 'get_vps':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'vps.php\'"';
        break;
    case 'enroll':
        $loyalty_btn_onclick = 'onclick="openReenrollModal()"';
        break;
    case 'profit_split_redirect':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'profit_split.php\'"';
        break;
    case 'payment_failed_redirect':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'profit_split.php?retry=1\'"';
        break;
    case 'deposit':
        $loyalty_btn_onclick = 'onclick="window.open(\'' . $brokerTarget . '\', \'_blank\')"';
        break;
    case 'reset':
        $loyalty_btn_onclick = 'onclick="openResetModal()"';
        break;
    case 'apply':
        $loyalty_btn_onclick = 'onclick="openApplyModal()"';
        break;
    case 'connect_broker':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'app.php#connect_investor_broker\'"';
        break;
    case 'find_manager':
        $loyalty_btn_onclick = 'onclick="window.location.href=\'programmes.php\'"';
        break;
    default:
        $loyalty_btn_onclick = '';
        break;
}

$applySuccessMessage = isset($_SESSION['apply_success_message']) ? $_SESSION['apply_success_message'] : null;
$applySuccessDetails = isset($_SESSION['apply_success_details']) ? $_SESSION['apply_success_details'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Harvhub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<?php include 'style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="mydashboard-box">

    <?php if ($contract_completed && $profitAndLoss <= $MIN_PROFIT_FOR_SPLIT && $profitAndLoss > 0): ?>
        <div class="threshold-warning">
            <span class="warning-icon">!</span>
            Your profit of $<?= number_format($profitAndLoss, 2) ?> is below the minimum split threshold of $<?= number_format($MIN_PROFIT_FOR_SPLIT, 2) ?>. No profit split required - you can enroll directly.
        </div>
    <?php endif; ?>

    <!-- Account Header -->
    <div class="account-header">
        <div class="account-info">
            <?php if ($hasProgramme && $DEVELOPER_NAME): ?>
                <span class="account-label"><?= htmlspecialchars($DEVELOPER_NAME) ?>'s Programme</span>
                <?php if ($PROGRAMME_NAME): ?>
                    <span class="account-number"><?= htmlspecialchars($PROGRAMME_NAME) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="account-actions" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 14px;">
        </div>
        <div class="account-info">
            <span class="account-label">Account</span>
            <span class="account-number"><?= htmlspecialchars($login) ?></span>
            <span class="account-server"><?= htmlspecialchars($server) ?></span>
        </div>
        <div class="account-info">
            <?php if (!empty($dashboard_disclaimer)): ?>
                <span class="account-label"><?= htmlspecialchars($dashboard_disclaimer) ?>
                <?php if ($loyaltiesStatus === 'unpaid-payment'): ?>
                    <span class="payment-required-badge">Payment Required</span>
                <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="account-info">
            <?php if ($profitAndLoss < 0 && $contract_completed): ?>
                <span class="account-label">Don't give up! Every loss is a setup for a greater comeback. Your next contract could be your breakthrough!</span>
            <?php endif; ?>
        </div>

        <div class="account-actions" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 14px;">
            <?php if (!$hasProgramme): ?>
                <a href="programmes.php" class="btn-account-action btn-get-vps">
                    Explore Programme
                </a>
            <?php elseif (!$userHasVps): ?>
                <a href="vps.php" class="btn-account-action btn-get-vps">
                    Get VPS
                </a>
            <?php elseif (!$broker_connected): ?>
                <a href="#connect_investor_broker" class="btn-account-action btn-connect-broker">
                    Connect Broker
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Stats Grid -->
    <div class="stats-grid">
        <!-- Balance Card -->
        <div class="stat-card balance-card">
            <div class="card-label">Total Investment</div>
            <?php if (!$hasProgramme): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">No Programme</span>
                </div>
                <div class="card-sub">Explore a programme to get started</div>
            <?php elseif (!$userHasVps): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">VPS Required</span>
                </div>
                <div class="card-sub">Get a VPS to unlock your dashboard</div>
            <?php elseif (!$broker_connected): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">No connected broker</span>
                </div>
                <div class="card-sub">Connect your broker to get started</div>
            <?php elseif ($balance_unverified): ?>
                <div class="card-value status-unverified">
                    <span class="value-amount">--</span>
                    <span class="status-badge warning">Unverified</span>
                </div>
                <div class="card-sub">Minimum deposit of $<?= number_format($MIN_INITIAL_DEPOSIT, 2) ?> required</div>
            <?php elseif ($balance_under_verification): ?>
                <div class="card-value status-pending">
                    <span class="value-amount">--</span>
                    <span class="status-badge info">Under Review</span>
                </div>
                <div class="card-sub">Verification in progress</div>
            <?php elseif ($balance_check_failed): ?>
                <div class="card-value status-failed">
                    <span class="value-amount">--</span>
                    <span class="status-badge danger">Failed</span>
                </div>
                <div class="card-sub">Minimum deposit of $<?= number_format($MIN_INITIAL_DEPOSIT, 2) ?> required</div>
            <?php else: ?>
                <div class="card-value">
                    <span class="currency-symbol">$</span>
                    <span class="value-amount"><?= number_format($depositBalance, 2) ?></span>
                    <span class="status-badge success">Verified</span>
                </div>
                <div class="card-sub">Starting Balance</div>
            <?php endif; ?>
            <button type="button" class="btn-revenue-history" onclick="window.location.href='revenue_history.php'">
                View Revenue History
            </button>
        </div>

        <!-- Profit & Loss Card -->
        <div class="stat-card pnl-card">
            <div class="card-label">Profit & Loss</div>
            <div class="card-value <?= $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                <span class="currency-symbol">$</span>
                <span class="value-amount"><?= number_format($profitAndLoss, 2) ?></span>
            </div>
            <div class="card-sub">Yield Performance</div>
            <div class="pnl-indicator <?= $profitAndLoss > 0 ? 'positive' : ($profitAndLoss < 0 ? 'negative' : 'neutral') ?>">
                <?php
                if ($profitAndLoss > 0) echo '▲ Profit';
                elseif ($profitAndLoss < 0) echo '▼ Loss';
                else echo '— Static';
                ?>
            </div>
        </div>

        <!-- Current Balance Card -->
        <div class="stat-card current-balance-card">
            <div class="card-label">Current Balance</div>
            <div class="card-value-row">
                <div class="card-value <?= $currentBalance >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    <span class="currency-symbol">$</span>
                    <span class="value-amount"><?= number_format($currentBalance, 2) ?></span>
                </div>

                <div class="chart-bars-container">
                    <div class="chart-bars-wrapper" id="chartBarsWrapper"></div>
                </div>
            </div>
            <div class="card-sub">Harvest Value</div>
            <div class="pnl-indicator <?= $currentBalance > $brokerBalance ? 'positive' : ($currentBalance < $brokerBalance ? 'negative' : 'neutral') ?>">
                <?php
                if ($currentBalance > $brokerBalance) echo '▲ Nourishing';
                elseif ($currentBalance < $brokerBalance) echo '▼ Deteriorating';
                else echo '— Fallow';
                ?>
            </div>

            <script>
                function renderChartBars() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (!wrapper) return;

                    var startingBalance = <?php echo isset($depositBalance) ? $depositBalance : 0; ?>;
                    var currentBalance  = <?php echo isset($currentBalance) ? $currentBalance : 0; ?>;
                    var profitAndLoss   = <?php echo isset($profitAndLoss) ? $profitAndLoss : 0; ?>;
                    var brokerConnected = <?php echo $broker_connected ? 'true' : 'false'; ?>;

                    if (!brokerConnected) { wrapper.innerHTML = ''; return; }

                    var totalBars = 9;
                    var isProfit = currentBalance > startingBalance;
                    var isBreakEven = Math.abs(currentBalance - startingBalance) < 0.01;

                    var containerHeight = wrapper.offsetHeight || 50;
                    var usableHeight = containerHeight - 2;
                    var maxHeightPercent = 0.95;
                    var minHeightPercent = 0.05;

                    var bars = [];

                    if (isBreakEven) {
                        var height = usableHeight * 0.5;
                        for (var i = 0; i < totalBars; i++) bars.push({ height: height, color: 'equal' });
                    } else if (isProfit) {
                        for (var i = 0; i < totalBars; i++) {
                            var progress = i / (totalBars - 1);
                            var easedProgress = progress * progress * (3 - 2 * progress);
                            var valueAtPoint = startingBalance + (currentBalance - startingBalance) * easedProgress;
                            var minValue = Math.min(startingBalance, currentBalance, 0);
                            var maxValue = Math.max(startingBalance, currentBalance, 0.01);
                            var heightPercent = (valueAtPoint - minValue) / (maxValue - minValue);
                            var clampedPercent = Math.max(0, Math.min(1, heightPercent));
                            var height = (minHeightPercent + (maxHeightPercent - minHeightPercent) * clampedPercent) * usableHeight;
                            bars.push({ height: height, color: 'green' });
                        }
                    } else {
                        var minValue = Math.min(startingBalance, currentBalance);
                        var maxValue = Math.max(startingBalance, currentBalance);
                        var range = maxValue - minValue;
                        for (var i = 0; i < totalBars; i++) {
                            var progress = i / (totalBars - 1);
                            var valueAtPoint = startingBalance + (currentBalance - startingBalance) * progress;
                            var heightPercent = (valueAtPoint - minValue) / (range || 0.01);
                            var clampedPercent = Math.max(0, Math.min(1, heightPercent));
                            var height = (minHeightPercent + (maxHeightPercent - minHeightPercent) * clampedPercent) * usableHeight;
                            bars.push({ height: height, color: 'red' });
                        }
                    }

                    var html = '';
                    for (var i = 0; i < bars.length; i++) {
                        html += '<div class="chart-bar-item"><div class="chart-bar ' + bars[i].color + '" style="height: ' + bars[i].height + 'px;"></div></div>';
                    }
                    wrapper.innerHTML = html;

                    window.chartData = { startingBalance: startingBalance, currentBalance: currentBalance, profitAndLoss: profitAndLoss };
                }

                function updateChartBars() { renderChartBars(); }
                function handleResize() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (wrapper && wrapper.children.length > 0) renderChartBars();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        renderChartBars();
                        window.addEventListener('resize', handleResize);
                    });
                } else {
                    renderChartBars();
                    window.addEventListener('resize', handleResize);
                }

                var observer = new MutationObserver(function() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (wrapper && wrapper.children.length === 0) renderChartBars();
                });
                observer.observe(document.body, { childList: true, subtree: true });

                window.renderChartBars = renderChartBars;
                window.updateChartBars = updateChartBars;
            </script>
        </div>
    </div>

    <!-- Loyalty / Contract Card -->
    <?php if ($hasProgramme && $userHasVps && $broker_connected): ?>
    <div class="loyalty-card">
        <div class="loyalty-header">
            <div class="loyalty-status">
                <span class="status-indicator <?= strpos($loyalties_message, 'Active') !== false ? 'active' : (strpos($loyalties_message, 'Completed') !== false ? 'completed' : '') ?>"></span>
                <span class="loyalty-status-msg"><?= htmlspecialchars($loyalties_message) ?></span>
            </div>
            <div class="loyalty-badge"><?= htmlspecialchars($loyalty_text) ?></div>
        </div>

        <div class="loyalty-body">
            <?php if ($is_contract_active && $executionStartDate && $executionStartDate !== '0000-00-00'): ?>
                <div class="contract-dates">
                    <span class="date-label">Started</span>
                    <span class="date-value"><?= htmlspecialchars($formatted_start_date) ?></span>
                    <span class="date-divider">→</span>
                    <span class="date-label">Ends</span>
                    <span class="date-value"><?= htmlspecialchars($formatted_end_date) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($is_contract_active): ?>
                <div class="contract-duration">
                    <span class="duration-label">Contract Duration</span>
                    <span class="duration-value"><?= $CONTRACT_DURATION ?> days</span>
                </div>
            <?php endif; ?>

            <?php if ($MIN_PROFIT_FOR_SPLIT > 0): ?>
                <div class="split-threshold">
                    <span class="threshold-label">Min profit for split</span>
                    <span class="threshold-value">$<?= number_format($MIN_PROFIT_FOR_SPLIT, 2) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="loyalty-actions">
            <?php if (!$show_payment_note): ?>
                <button
                    <?= $loyalty_btn_onclick ?>
                    class="btn-action <?= htmlspecialchars($loyalty_btn_class) ?>"
                    <?= ($loyalty_btn_action === '') ? 'disabled' : '' ?>
                >
                    <?= htmlspecialchars($loyalty_btn_text) ?>
                </button>
            <?php else: ?>
                <button class="btn-action btn-loyalty-paid" disabled>
                    <?= htmlspecialchars($loyalty_btn_text) ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Enrollment Modal -->
<div id="reenrollModal" class="modal">
    <div class="modal-content">
        <h2 style="color:var(--info-color);">Contract Enrollment Protocol</h2>
        <p style="margin:1rem 0; opacity:0.8; font-size:0.95rem;">
            You are about to commence a new <?= $CONTRACT_DURATION ?>-day trading contract.
            Please carefully review the following stipulations before proceeding.
        </p>
        <div class="reenroll-instructions">
            <h4>Enrollment Terms</h4>
            <ul>
                <li><strong>No Manual Trading:</strong> Do not open, close, or modify any trades manually during the automation period.</li>
                <li><strong>No Withdrawals:</strong> Do not withdraw profits or balance from your MT5 account until the contract expires.</li>
                <li><strong>No Deposits:</strong> Do not deposit or transfer funds from external wallets to your broker account during this period.</li>
            </ul>
            <div class="consequence-note">
                Violation of any of these terms will result in permanent disqualification from the programme.
            </div>
        </div>
        <label class="checkbox-container-legal" id="reenrollCheckContainer">
            <input type="checkbox" id="reenrollConfirmCheck" onchange="toggleReenrollButton()">
            <label for="reenrollConfirmCheck">I understand the terms.</label>
        </label>
        <div class="modal-actions" style="flex-direction: column; gap: 10px;">
            <button id="reenrollProceedBtn" class="reenroll-confirm-btn" disabled onclick="proceedToEnrollment()">
                Proceed to Enrollment
            </button>
            <button onclick="closeReenrollModal()" style="width: 100%; padding: 12px; background:#555; color:white; border:none; border-radius: 8px; cursor: pointer;">
                Cancel
            </button>
        </div>
    </div>
</div>

<form id="reenrollForm" method="POST" action="mydashboard.php" style="display:none;">
    <input type="hidden" name="confirm_reenroll" value="1">
</form>

<!-- Apply for Verification Modal -->
<div id="applyModal" class="modal">
    <div class="modal-content">
        <h2 style="color: var(--info);">Balance Verification Application</h2>
        <p style="margin: 1.5rem 0; line-height: 1.6;">Before proceeding with your application, please ensure:</p>
        <div class="apply-instructions" style="background: rgba(255, 255, 255, 0.05); padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            <ul style="list-style: none; padding-left: 0;">
                <li style="margin-bottom: 10px;">Your broker account is active and accessible with same login credentials</li>
                <li style="margin-bottom: 10px;">You have deposited into your broker account</li>
            </ul>
        </div>
        <div class="apply-warning" style="background: rgba(255, 193, 7, 0.1); border-left: 4px solid #ffc107; padding: 1rem; margin: 1rem 0;">
            <strong style="color: #ffc107;">Important:</strong>
            <p style="margin-top: 0.5rem;">Ensure you have deposited into your account before confirming application.</p>
        </div>
        <div class="modal-actions" style="flex-direction: column; gap: 10px;">
            <form method="POST" style="width: 100%;" action="mydashboard.php">
                <input type="hidden" name="apply_for_verification" value="1">
                <button type="submit" class="btn-full" style="background: var(--info); color: white; width: 100%;">Confirm Application</button>
            </form>
            <button onclick="closeApplyModal()" style="width: 100%; padding: 12px; background: #555; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<!-- Reset Contract Confirmation Modal -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <h2 style="color: #ff9800;">Start a new Journey</h2>
        <div class="reset-note" style="background: rgba(46, 204, 113, 0.1); border-left: 4px solid #2ecc71; padding: 1rem; margin: 1rem 0;">
            <strong style="color: #2ecc71;">Important:</strong>
            <p style="margin-top: 0.5rem;">This will clear your current contract state (balance, profit &amp; loss, and verification status). Please ensure you have deposited funds into your broker account before applying for verification again.</p>
        </div>
        <div class="modal-actions" style="flex-direction: column; gap: 10px;">
            <form method="POST" style="width: 100%;" action="mydashboard.php">
                <input type="hidden" name="confirm_reset_contract" value="1">
                <button type="submit" class="btn-loyalty-action" style="background: #ff9800; color: white; width: 100%;">Continue</button>
            </form>
            <button onclick="closeResetModal()" style="width: 100%; padding: 12px; background: #555; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancel</button>
        </div>
    </div>
</div>

<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href.split("?")[0]);
    }

    // ============== Enrollment Flow ==============
    function openReenrollModal() {
        document.getElementById('reenrollConfirmCheck').checked = false;
        document.getElementById('reenrollProceedBtn').disabled = true;
        document.getElementById('reenrollModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }
    function closeReenrollModal() {
        document.getElementById('reenrollModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
    }
    function toggleReenrollButton() {
        const checkbox = document.getElementById('reenrollConfirmCheck');
        const button = document.getElementById('reenrollProceedBtn');
        button.disabled = !checkbox.checked;
        if (checkbox.checked) {
            button.style.background = '#0080bc';
            button.style.color = '#000';
            button.style.cursor = 'pointer';
            button.style.opacity = '1';
        } else {
            button.style.background = '#555';
            button.style.color = '#999';
            button.style.cursor = 'not-allowed';
            button.style.opacity = '0.6';
        }
    }
    function proceedToEnrollment() {
        document.getElementById('reenrollModal').classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        var form = document.getElementById('reenrollForm');
        form.action = 'mydashboard.php';
        form.submit();
    }

    function openApplyModal() { document.getElementById('applyModal').classList.add('active'); }
    function closeApplyModal() { document.getElementById('applyModal').classList.remove('active'); }
    function openResetModal() { document.getElementById('resetModal').classList.add('active'); }
    function closeResetModal() { document.getElementById('resetModal').classList.remove('active'); }
</script>

<script>
    // ============== LIVE BALANCE / STATE UPDATES ==============
    const depositBalanceEl = document.querySelector('.stat-card:first-child .value-amount');
    const profitLossEl     = document.querySelector('.stat-card:nth-child(2) .value-amount');
    const currentBalanceEl = document.querySelector('.stat-card:nth-child(3) .value-amount');

    let isUpdating = false;
    let updateInterval = null;
    let retryCount = 0;
    const MAX_RETRIES = 3;

    async function fetchLiveBalances() {
        if (isUpdating) return;
        isUpdating = true;

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('Network response was not ok');
            const data = await response.json();

            if (data.success) {
                retryCount = 0;

                if (depositBalanceEl && data.deposit_balance)
                    animateValue(depositBalanceEl, depositBalanceEl.innerText.replace('$', ''), data.deposit_balance, '$');
                if (profitLossEl && data.profit_loss) {
                    animateValue(profitLossEl, profitLossEl.innerText.replace('$', ''), data.profit_loss, '$');
                    const pnlCard = profitLossEl.closest('.card-value');
                    if (pnlCard) pnlCard.className = 'card-value ' + data.profit_loss_class;
                }
                if (currentBalanceEl && data.current_balance) {
                    animateValue(currentBalanceEl, currentBalanceEl.innerText.replace('$', ''), data.current_balance, '$');
                    const currentCard = currentBalanceEl.closest('.card-value');
                    if (currentCard) currentCard.className = 'card-value ' + data.current_balance_class;
                }

                if (typeof window.renderChartBars === 'function') window.renderChartBars();

                const contractDatesEl = document.querySelector('.contract-dates');
                if (contractDatesEl && data.formatted_start_date && data.formatted_end_date) {
                    contractDatesEl.innerHTML = `
                        <span class="date-label">Started</span>
                        <span class="date-value">${data.formatted_start_date}</span>
                        <span class="date-divider">→</span>
                        <span class="date-label">Ends</span>
                        <span class="date-value">${data.formatted_end_date}</span>
                    `;
                    contractDatesEl.style.display = 'flex';
                } else if (contractDatesEl && !data.formatted_start_date) {
                    contractDatesEl.style.display = 'none';
                }

                const loyaltyTextEl = document.querySelector('.loyalty-badge');
                if (loyaltyTextEl && data.loyalty_text) loyaltyTextEl.innerHTML = data.loyalty_text;

                const loyaltiesEl = document.querySelector('.loyalty-status-msg');
                if (loyaltiesEl && data.loyalties_message) loyaltiesEl.innerHTML = data.loyalties_message;

                const loyaltyBtn = document.querySelector('.loyalty-actions .btn-action');
                if (loyaltyBtn) {
                    if (data.show_explore_programme || data.has_programme === false) {
                        loyaltyBtn.innerHTML = "Explore Programme";
                        loyaltyBtn.className = 'btn-action btn-loyalty-action btn-explore-programme';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', "window.location.href='programmes.php'");
                        loyaltyBtn.disabled = false;
                        const loyaltiesMsgEl = document.querySelector('.loyalty-status-msg');
                        if (loyaltiesMsgEl) loyaltiesMsgEl.innerHTML = "No Programme Joined";
                        const loyaltyTextEl2 = document.querySelector('.loyalty-badge');
                        if (loyaltyTextEl2) loyaltyTextEl2.innerHTML = "You haven't invested in any developer programme yet. Explore programmes to start your investment journey.";
                        return;
                    }

                    if (data.show_get_vps || data.user_has_vps === false) {
                        loyaltyBtn.innerHTML = "Get VPS";
                        loyaltyBtn.className = 'btn-action btn-get-vps';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', "window.location.href='vps.php'");
                        loyaltyBtn.disabled = false;
                        const loyaltiesMsgEl = document.querySelector('.loyalty-status-msg');
                        if (loyaltiesMsgEl) loyaltiesMsgEl.innerHTML = "VPS Required";
                        const loyaltyTextEl2 = document.querySelector('.loyalty-badge');
                        if (loyaltyTextEl2) loyaltyTextEl2.innerHTML = "You need a Virtual Private Server to run automated trading. Get one to unlock your dashboard.";
                        return;
                    }

                    if (data.reset_contract === 1) {
                        loyaltyBtn.innerHTML = "Let's get started";
                        loyaltyBtn.className = 'btn-action btn-reset';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', 'openResetModal()');
                        loyaltyBtn.disabled = false;
                        const loyaltiesMsgEl = document.querySelector('.loyalty-status-msg');
                        if (loyaltiesMsgEl) loyaltiesMsgEl.innerHTML = "Ready for a new Contract?";
                        const loyaltyTextEl2 = document.querySelector('.loyalty-badge');
                        if (loyaltyTextEl2) loyaltyTextEl2.innerHTML = "Your path is clear. Click below to embark on your next contract.";
                        return;
                    }

                    if (data.show_connect_broker) {
                        loyaltyBtn.innerHTML = "Connect Broker";
                        loyaltyBtn.className = 'btn-action btn-connect-broker';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', "window.location.href='app.php#connect_investor_broker'");
                        loyaltyBtn.disabled = false;
                    } else if (data.show_find_manager) {
                        loyaltyBtn.innerHTML = "Find Trade Manager";
                        loyaltyBtn.className = 'btn-action btn-loyalty-action';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', "window.location.href='programmes.php'");
                        loyaltyBtn.disabled = false;
                    } else if (data.show_apply_button) {
                        loyaltyBtn.innerHTML = "Apply for Verification";
                        loyaltyBtn.className = 'btn-action btn-apply';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', 'openApplyModal()');
                        loyaltyBtn.disabled = false;
                    } else if (data.show_reenroll_button) {
                        loyaltyBtn.innerHTML = "Enroll";
                        loyaltyBtn.className = 'btn-action btn-loyalty-action';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.setAttribute('onclick', 'openReenrollModal()');
                        loyaltyBtn.disabled = false;
                    } else if (data.show_payment_note) {
                        loyaltyBtn.innerHTML = data.loyalty_btn_text || "Awaiting Confirmation";
                        loyaltyBtn.className = 'btn-action btn-loyalty-paid';
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.disabled = true;
                    } else if (data.loyalty_btn_text) {
                        loyaltyBtn.innerHTML = data.loyalty_btn_text;
                        loyaltyBtn.className = 'btn-action ' + data.loyalty_btn_class;
                        loyaltyBtn.removeAttribute('onclick');
                        loyaltyBtn.disabled = false;

                        switch (data.loyalty_btn_action) {
                            case 'explore_programme':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='programmes.php'");
                                break;
                            case 'get_vps':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='vps.php'");
                                break;
                            case 'enroll':
                                loyaltyBtn.setAttribute('onclick', 'openReenrollModal()');
                                break;
                            case 'deposit':
                                const brokerTarget = '<?= htmlspecialchars($brokerTarget) ?>';
                                loyaltyBtn.setAttribute('onclick', `window.open('${brokerTarget}', '_blank')`);
                                break;
                            case 'profit_split_redirect':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='profit_split.php'");
                                break;
                            case 'payment_failed_redirect':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='profit_split.php?retry=1'");
                                break;
                            case 'apply':
                                loyaltyBtn.setAttribute('onclick', 'openApplyModal()');
                                break;
                            case 'reset':
                                loyaltyBtn.setAttribute('onclick', 'openResetModal()');
                                break;
                            case 'connect_broker':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='app.php#connect_investor_broker'");
                                break;
                            case 'find_manager':
                                loyaltyBtn.setAttribute('onclick', "window.location.href='programmes.php'");
                                break;
                            default:
                                loyaltyBtn.disabled = true;
                                break;
                        }
                    }
                }
            } else if (data.error) {
                retryCount++;
                if (retryCount >= MAX_RETRIES) stopLiveUpdates();
            }
        } catch (error) {
            retryCount++;
            if (retryCount >= MAX_RETRIES) stopLiveUpdates();
        } finally {
            isUpdating = false;
        }
    }

    function animateValue(element, start, end, prefix = '', suffix = '', duration = 300) {
        if (!element) return;
        start = parseFloat(start.toString().replace(/[^0-9.-]/g, '')) || 0;
        end = parseFloat(end.toString().replace(/[^0-9.-]/g, '')) || 0;
        if (start === end) return;
        const range = end - start;
        let current = start;
        let startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            const elapsed = timestamp - startTime;
            const progress = Math.min(1, elapsed / duration);
            current = start + (range * progress);
            element.innerText = prefix + current.toFixed(2) + suffix;
            if (progress < 1) requestAnimationFrame(step);
            else element.innerText = prefix + end.toFixed(2) + suffix;
        }
        requestAnimationFrame(step);
    }

    function startLiveUpdates(intervalSeconds = 5) {
        if (updateInterval) clearInterval(updateInterval);
        fetchLiveBalances();
        updateInterval = setInterval(fetchLiveBalances, intervalSeconds * 1000);
    }
    function stopLiveUpdates() {
        if (updateInterval) { clearInterval(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = setInterval(fetchLiveBalances, 30000);
            }
        } else {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = setInterval(fetchLiveBalances, 5000);
            }
            fetchLiveBalances();
        }
    });

    startLiveUpdates(5);
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>

<script>
    // ============== NOTIFICATION SYSTEM ==============
    let notificationPanelOpen = false;

    function toggleNotifications() {
        const panel = document.getElementById('notificationPanel');
        if (notificationPanelOpen) {
            panel.classList.remove('active');
            notificationPanelOpen = false;
            markNotificationsAsRead();
        } else {
            panel.classList.add('active');
            notificationPanelOpen = true;
            refreshNotifications();
        }
    }

    function markNotificationsAsRead() {
        const unreadItems = document.querySelectorAll('.notification-item.unread');
        if (unreadItems.length === 0) return;
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_notifications_read=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                const badge = document.getElementById('notificationBadge');
                if (badge) badge.style.display = 'none';
            }
        })
        .catch(() => {});
    }

    function refreshNotifications() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_notifications_list=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notificationList = document.getElementById('notificationList');
                if (data.notifications && data.notifications.length > 0) {
                    let html = '';
                    data.notifications.forEach(notification => {
                        const unreadClass = notification.update === 'new' ? 'unread' : '';
                        const typeClass = notification.type || 'info';
                        let cleanMessage = notification.message;
                        cleanMessage = cleanMessage.replace(/^[???]+\s*/, '');
                        cleanMessage = cleanMessage.replace(/[???]/g, '');
                        cleanMessage = cleanMessage.replace(/[\u{1F300}-\u{1F6FF}]/gu, '');
                        html += `
                            <div class="notification-item ${unreadClass} ${typeClass}" data-id="${notification.id}" data-update="${notification.update}">
                                <div class="notification-section">${escapeHtml(notification.section)}</div>
                                <div class="notification-message">${escapeHtml(cleanMessage.trim())}</div>
                                <div class="notification-time">${formatDate(notification.time)}</div>
                            </div>
                        `;
                    });
                    notificationList.innerHTML = html;
                } else {
                    notificationList.innerHTML = '<div class="empty-notifications">No notifications</div>';
                }

                const badge = document.getElementById('notificationBadge');
                if (data.unread_count > 0) {
                    if (badge) {
                        badge.textContent = data.unread_count;
                        badge.style.display = 'flex';
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(() => {});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function pollNewNotifications() {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'check_new_notifications=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('notificationBadge');
                if (data.unread_count > 0) {
                    if (badge) {
                        const currentCount = parseInt(badge.textContent) || 0;
                        if (currentCount !== data.unread_count) {
                            badge.textContent = data.unread_count;
                            badge.style.display = 'flex';
                            if (notificationPanelOpen) refreshNotifications();
                        }
                    }
                } else if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(() => {});
    }

    document.addEventListener('click', function(event) {
        const panel = document.getElementById('notificationPanel');
        const bell = document.querySelector('.notification-bell');
        if (notificationPanelOpen && panel && !panel.contains(event.target) && !bell.contains(event.target)) {
            panel.classList.remove('active');
            notificationPanelOpen = false;
            markNotificationsAsRead();
        }
    });

    setInterval(pollNewNotifications, 3000);
    document.addEventListener('DOMContentLoaded', function() { refreshNotifications(); });
</script>

</body>
</html>