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

    // --- Detect whether this script is being included or called directly ---
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isDirectCall = ($scriptName === 'mydashboard.php');

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

    // ==================== FIND THE USER'S ACTIVE INVESTMENT ====================
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

    $hasProgramme = ($activeInvestment && $activeInvestment['programme_id']);

    // ==================== DERIVED VALUES ====================
    $CONTRACT_DURATION    = $SERVER_CONTRACT_DURATION;
    $MIN_BROKER_BALANCE   = $SERVER_MIN_BROKER_BALANCE;
    $MIN_INITIAL_DEPOSIT  = $SERVER_MIN_BROKER_BALANCE;
    $SERVER_SHARE_PERCENT = $SERVER_SHARE_PERCENT;
    $USER_SHARE_PERCENT   = $SERVER_USER_SHARE_PERCENT;
    $MIN_PROFIT_FOR_SPLIT = $SERVER_MIN_PROFIT_FOR_SPLIT;
    $PROGRAMME_NAME       = '';
    $DEVELOPER_NAME       = '';
    $DEVELOPER_ID         = 0;

    if ($hasProgramme) {
        $pi_cd = (int)($activeInvestment['contract_duration'] ?? 0);
        if ($pi_cd > 0) $CONTRACT_DURATION = $pi_cd;

        $pi_min = (float)($activeInvestment['minimum_investment_amount'] ?? 0);
        if ($pi_min > 0) {
            $MIN_BROKER_BALANCE  = $pi_min;
            $MIN_INITIAL_DEPOSIT = $pi_min;
        }

        $pi_dev = (int)($activeInvestment['developer_percentage'] ?? 0);
        $pi_inv = (int)($activeInvestment['investor_percentage'] ?? 0);
        if ($pi_dev > 0) $SERVER_SHARE_PERCENT = $pi_dev;
        if ($pi_inv > 0) $USER_SHARE_PERCENT   = $pi_inv;

        if ($investmentProgramme && !empty($investmentProgramme['program_name'])) {
            $PROGRAMME_NAME = $investmentProgramme['program_name'];
        }
        if ($investmentDeveloper && !empty($investmentDeveloper['fullname'])) {
            $DEVELOPER_NAME = $investmentDeveloper['fullname'];
            $DEVELOPER_ID   = (int)$investmentDeveloper['id'];
        }
    }

    // Extract user data
    $brokerBalance          = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss          = (float)($user['profitandloss'] ?? 0);
    $executionStartDate     = $user['execution_start_date'] ?? null;
    $loyaltiesStatus        = $user['loyalties'] ?? null;
    $resetContract          = (int)($user['reset_contract'] ?? 0);
    $balanceVerificationStatus = $user['balance_verification'] ?? 'not-verified';
    $recentHighestBalance   = (float)($user['recent_highest_balance'] ?? 0);

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
    $brokerLink   = '';
    $brokerLinks  = [];

    if (!empty($serverAccount['brokers_link']) && !empty($serverAccount['brokers'])) {
        $raw_links   = explode(',', $serverAccount['brokers_link']);
        $raw_brokers = explode(',', $serverAccount['brokers']);

        $cleaned_links = [];
        foreach ($raw_links as $link) {
            $link = trim($link);
            if ($link === '') continue;

            if (strpos($link, ':') !== false) {
                $link = trim(substr($link, strrpos($link, ':') + 1));
            }

            if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,})/', $link, $matches)) {
                $link = $matches[1];
            }

            $link = strtolower(trim($link));
            if (!empty($link)) $cleaned_links[] = $link;
        }

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

    $userBrokerNormalized = strtolower(trim($broker));
    $matchedLink = '';

    foreach ($brokerLinks as $name => $link) {
        if (strtolower($name) === $userBrokerNormalized) {
            $matchedLink = $link;
            break;
        }
    }

    if (empty($matchedLink)) {
        foreach ($brokerLinks as $name => $link) {
            if (strpos(strtolower($name), $userBrokerNormalized) !== false) {
                $matchedLink = $link;
                break;
            }
        }
    }

    if (empty($matchedLink) && isset($brokerLinks['harvhub'])) {
        $matchedLink = $brokerLinks['harvhub'];
    }
    if (empty($matchedLink) && !empty($brokerLinks)) {
        $first = reset($brokerLinks);
        if (!empty($first)) $matchedLink = $first;
    }

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

    // ==================== AJAX: LIVE STATE (every 1s poll) ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

        while (ob_get_level() > 0) { ob_end_clean(); }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        if (!isset($_SESSION['user_email'])) {
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $email = strtolower($_SESSION['user_email']);

        $stmt = $pdo->prepare("
            SELECT id, fullname, broker, server, login, broker_balance, profitandloss,
                   loyalties, execution_start_date, application_status, balance_verification,
                   reset_contract, recent_highest_balance, balance_display
            FROM $tableName WHERE email = ?
        ");
        $stmt->execute([$email]);
        $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$liveUser) {
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $liveInvestment = null;
        try {
            $s = $pdo->prepare("SELECT * FROM $programmeInvestorsTable WHERE investorid = ? ORDER BY invested_at DESC, id DESC LIMIT 1");
            $s->execute([(int)$liveUser['id']]);
            $liveInvestment = $s->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        $liveHasProgramme = ($liveInvestment && !empty($liveInvestment['programme_id']));

        $liveContractDuration    = (int)($serverAccount['contract_duration'] ?? 30);
        $liveMinBrokerBalance    = (float)($serverAccount['min_broker_balance'] ?? 0);
        $liveMinInitialDeposit   = $liveMinBrokerBalance;
        $liveMinProfitSplit      = (float)($serverAccount['min_profit_for_split'] ?? 30);
        $liveServerSharePercent  = (int)($serverAccount['server_share_percent'] ?? 30);
        $liveUserSharePercent    = (int)($serverAccount['user_share_percent'] ?? 70);

        $liveProgrammeName = '';
        $liveDeveloperName = '';

        if ($liveHasProgramme) {
            $pi_cd  = (int)($liveInvestment['contract_duration'] ?? 0);
            $pi_min = (float)($liveInvestment['minimum_investment_amount'] ?? 0);
            $pi_dev = (int)($liveInvestment['developer_percentage'] ?? 0);
            $pi_inv = (int)($liveInvestment['investor_percentage'] ?? 0);
            if ($pi_cd  > 0) $liveContractDuration   = $pi_cd;
            if ($pi_min > 0) { $liveMinBrokerBalance = $pi_min; $liveMinInitialDeposit = $pi_min; }
            if ($pi_dev > 0) $liveServerSharePercent = $pi_dev;
            if ($pi_inv > 0) $liveUserSharePercent   = $pi_inv;

            if (!empty($liveInvestment['programme_id'])) {
                try {
                    $p = $pdo->prepare("SELECT program_name FROM $programmeTable WHERE id = ? LIMIT 1");
                    $p->execute([(int)$liveInvestment['programme_id']]);
                    $rowP = $p->fetch(PDO::FETCH_ASSOC);
                    if ($rowP) $liveProgrammeName = $rowP['program_name'] ?? '';
                } catch (PDOException $e) {}
            }

            if (!empty($liveInvestment['developerid'])) {
                try {
                    $d = $pdo->prepare("SELECT fullname FROM $tableName WHERE id = ? LIMIT 1");
                    $d->execute([(int)$liveInvestment['developerid']]);
                    $rowD = $d->fetch(PDO::FETCH_ASSOC);
                    if ($rowD) $liveDeveloperName = $rowD['fullname'] ?? '';
                } catch (PDOException $e) {}
            }
        }

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

        $brokerBalance        = (float)($liveUser['broker_balance'] ?? 0);
        $profitAndLoss        = (float)($liveUser['profitandloss'] ?? 0);
        $currentBalance       = $brokerBalance + $profitAndLoss;
        $executionStartDate   = $liveUser['execution_start_date'] ?? null;
        $loyaltiesStatus      = $liveUser['loyalties'] ?? null;
        $balanceVerificationStatus = $liveUser['balance_verification'] ?? 'not-verified';
        $resetContractStatus  = (int)($liveUser['reset_contract'] ?? 0);
        $recentHighestBalance = (float)($liveUser['recent_highest_balance'] ?? 0);

        if ($currentBalance > $recentHighestBalance) {
            try {
                $updPeak = $pdo->prepare("UPDATE $tableName SET recent_highest_balance = ?, recent_highest_balance_last_update = CURDATE() WHERE email = ?");
                $updPeak->execute([$currentBalance, $email]);
                $recentHighestBalance = $currentBalance;
            } catch (PDOException $e) {}
        }

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

        $balance_unverified         = false;
        $balance_under_verification = false;
        $balance_check_failed       = false;

        if ($balanceVerificationStatus === 'not-verified' || empty($balanceVerificationStatus)) {
            $balance_unverified = true;
        } elseif ($balanceVerificationStatus === 'applied-for-verification') {
            $balance_under_verification = true;
        } elseif ($balanceVerificationStatus === 'verified' && $brokerBalance < $liveMinInitialDeposit) {
            $balance_check_failed = true;
        }

        $liveBrokerConnected = (!empty($liveUser['broker']) && !empty($liveUser['server']) && !empty($liveUser['login']));

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

        echo json_encode([
            'success'                       => true,

            // Balances
            'deposit_balance'               => number_format($brokerBalance, 2, '.', ''),
            'profit_loss'                   => number_format($profitAndLoss, 2, '.', ''),
            'current_balance'               => number_format($currentBalance, 2, '.', ''),
            'recent_highest_balance'        => number_format($recentHighestBalance, 2, '.', ''),
            'profit_loss_class'             => $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative',
            'current_balance_class'         => $currentBalance >= 0 ? 'profit-positive' : 'profit-negative',
            'peak_above'                    => ($recentHighestBalance > $currentBalance),

            // Contract dates
            'contract_days_left'            => $is_contract_active ? $contractDaysLeft : 0,
            'is_contract_active'            => $is_contract_active,
            'contract_completed'            => $contract_completed,
            'formatted_start_date'          => $formatted_start_date,
            'formatted_end_date'            => $formatted_end_date,
            'contract_duration'             => $liveContractDuration,

            // Balance card state
            'balance_unverified'            => $balance_unverified,
            'balance_under_verification'    => $balance_under_verification,
            'balance_check_failed'          => $balance_check_failed,
            'min_initial_deposit'           => $liveMinInitialDeposit,

            // Connectivity
            'broker_connected'              => $liveBrokerConnected,
            'user_has_vps'                  => $liveUserHasVps,
            'has_programme'                 => $liveHasProgramme,

            // Programme identity
            'programme_name'                => $liveProgrammeName,
            'developer_name'                => $liveDeveloperName,

            // Contract settings
            'min_profit_for_split'          => $liveMinProfitSplit,
            'min_broker_balance'            => $liveMinBrokerBalance,
            'server_share_percent'          => $liveServerSharePercent,
            'user_share_percent'            => $liveUserSharePercent,

            // Loyalties / state
            'loyalties_status'              => $loyaltiesStatus,
            'balance_verification_status'   => $balanceVerificationStatus,
            'reset_contract'                => $resetContractStatus,

            // State machine output
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

            // State machine strings
            'loyalties_message'             => $ajaxState['loyalties_message'],
            'loyalty_text'                  => $ajaxState['loyalty_text'],
            'loyalty_btn_text'              => $ajaxState['loyalty_btn_text'],
            'loyalty_btn_class'             => $ajaxState['loyalty_btn_class'],
            'loyalty_btn_action'            => $ajaxState['loyalty_btn_action'],
            'dashboard_disclaimer'          => $ajaxState['dashboard_disclaimer'],

            // Extras
            'broker'                        => strtolower($liveUser['broker'] ?? 'unknown'),
            'profit_to_split'               => number_format(max(0, $profitAndLoss), 2, '.', ''),
            'application_status'            => $liveUser['application_status'] ?? '',
        ]);
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

    <div id="thresholdWarning" class="threshold-warning" style="<?= ($contract_completed && $profitAndLoss <= $MIN_PROFIT_FOR_SPLIT && $profitAndLoss > 0) ? '' : 'display:none;' ?>">
        <span class="warning-icon">!</span>
        <span id="thresholdWarningText">
            Your profit of $<?= number_format($profitAndLoss, 2) ?> is below the minimum split threshold of $<?= number_format($MIN_PROFIT_FOR_SPLIT, 2) ?>. No profit split required - you can enroll directly.
        </span>
    </div>

    <!-- Account Header -->
    <div class="account-header">
        <div class="account-info">
            <?php if ($hasProgramme && $DEVELOPER_NAME): ?>
                <span class="account-label" id="accountDeveloperLabel"><?= htmlspecialchars($DEVELOPER_NAME) ?>'s Programme</span>
                <?php if ($PROGRAMME_NAME): ?>
                    <span class="account-number" id="accountProgrammeName"><?= htmlspecialchars($PROGRAMME_NAME) ?></span>
                <?php else: ?>
                    <span class="account-number" id="accountProgrammeName" style="display:none;"></span>
                <?php endif; ?>
            <?php else: ?>
                <span class="account-label" id="accountDeveloperLabel" style="display:none;"></span>
                <span class="account-number" id="accountProgrammeName" style="display:none;"></span>
            <?php endif; ?>
        </div>
        <div class="account-info">
            <span class="account-label">Account</span>
            <span class="account-number"><?= htmlspecialchars($login) ?></span>
            <span class="account-server"><?= htmlspecialchars($server) ?></span>
        </div>
        <div class="account-info">
            <span class="account-label" id="dashboardDisclaimer">
                <?= htmlspecialchars($dashboard_disclaimer) ?>
                <span class="payment-required-badge" id="paymentRequiredBadge" style="<?= ($loyaltiesStatus === 'unpaid-payment') ? '' : 'display:none;' ?>">Payment Required</span>
            </span>
        </div>
        <div class="account-info">
            <span class="account-label" id="encouragementNote" style="<?= ($profitAndLoss < 0 && $contract_completed) ? '' : 'display:none;' ?>">
                Don't give up! Every loss is a setup for a greater comeback. Your next contract could be your breakthrough!
            </span>
        </div>

        <div class="account-actions" style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap; border-top: 1px solid var(--border-color); padding-top: 14px;">
            <?php if (!$hasProgramme): ?>
                <a href="programmes.php" class="btn-account-action btn-get-vps">Explore Programme</a>
            <?php elseif (!$userHasVps): ?>
                <a href="vps.php" class="btn-account-action btn-get-vps">Get VPS</a>
            <?php elseif (!$broker_connected): ?>
                <a href="#connect_investor_broker" class="btn-account-action btn-connect-broker">Connect Broker</a>
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

        <!-- Profit & Loss Card (with inline chart) -->
        <div class="stat-card pnl-card">
            <div class="card-label">Profit & Loss</div>
            <div class="card-value-row">
                <div class="card-value <?= $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    <span class="currency-symbol">$</span>
                    <span class="value-amount"><?= number_format($profitAndLoss, 2) ?></span>
                </div>

                <div class="chart-bars-container">
                    <div class="chart-bars-wrapper" id="chartBarsWrapper"></div>
                </div>
            </div>
            <div class="card-sub">Yield Performance</div>
            <div class="pnl-indicator <?= $profitAndLoss > 0 ? 'positive' : ($profitAndLoss < 0 ? 'negative' : 'neutral') ?>">
                <?php
                if ($profitAndLoss > 0) echo '▲ Profit';
                elseif ($profitAndLoss < 0) echo '▼ Loss';
                else echo '— Static';
                ?>
            </div>

            <script>
                // =================================================================
                // Chart state — caches the last rendered values and the last
                // rendered broker-connected flag, so we only touch the DOM when
                // something actually changed.
                // =================================================================
                window.__chartState = window.__chartState || {
                    start: null,
                    current: null,
                    brokerConnected: null
                };

                function renderChartBars(force, overrideStart, overrideCurrent, overrideConnected) {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (!wrapper) return;

                    var startingBalance = (typeof overrideStart === 'number')
                        ? overrideStart
                        : <?php echo isset($depositBalance) ? (float)$depositBalance : 0; ?>;
                    var currentBalance  = (typeof overrideCurrent === 'number')
                        ? overrideCurrent
                        : <?php echo isset($currentBalance) ? (float)$currentBalance : 0; ?>;
                    var brokerConnected = (typeof overrideConnected === 'boolean')
                        ? overrideConnected
                        : <?php echo $broker_connected ? 'true' : 'false'; ?>;

                    if (!brokerConnected) {
                        if (wrapper.children.length !== 0) wrapper.innerHTML = '';
                        window.__chartState.start = startingBalance;
                        window.__chartState.current = currentBalance;
                        window.__chartState.brokerConnected = false;
                        return;
                    }

                    // If nothing changed and we already have 9 bars, do nothing.
                    var state = window.__chartState;
                    if (!force &&
                        state.start !== null &&
                        state.current !== null &&
                        state.brokerConnected === true &&
                        Math.abs(state.start   - startingBalance) < 0.005 &&
                        Math.abs(state.current - currentBalance)  < 0.005 &&
                        wrapper.children.length === 9) {
                        return;
                    }

                    state.start = startingBalance;
                    state.current = currentBalance;
                    state.brokerConnected = true;

                    var totalBars = 9;
                    var isProfit    = currentBalance > startingBalance;
                    var isBreakEven = Math.abs(currentBalance - startingBalance) < 0.005;

                    var containerHeight = wrapper.offsetHeight || 50;
                    var usableHeight = containerHeight - 2;

                    var minBarHeight = 1;
                    var maxBarHeight = Math.max(usableHeight, minBarHeight + (totalBars - 1));
                    var step = (maxBarHeight - minBarHeight) / (totalBars - 1);

                    var bars = [];

                    if (isBreakEven) {
                        for (var i = 0; i < totalBars; i++) {
                            var h = minBarHeight + step * i;
                            bars.push({ height: h, color: 'equal' });
                        }
                    } else if (isProfit) {
                        for (var i = 0; i < totalBars; i++) {
                            var h = minBarHeight + step * i;
                            bars.push({ height: h, color: 'green' });
                        }
                    } else {
                        for (var i = 0; i < totalBars; i++) {
                            var h = maxBarHeight - step * i;
                            bars.push({ height: h, color: 'red' });
                        }
                    }

                    var html = '';
                    for (var i = 0; i < bars.length; i++) {
                        html += '<div class="chart-bar-item">' +
                                '<div class="chart-bar ' + bars[i].color + '" ' +
                                'style="height:' + bars[i].height.toFixed(2) + 'px;"></div>' +
                                '</div>';
                    }
                    wrapper.innerHTML = html;

                    window.chartData = {
                        startingBalance: startingBalance,
                        currentBalance: currentBalance,
                        profitAndLoss: currentBalance - startingBalance
                    };
                }

                function updateChartBars() { renderChartBars(true); }

                function handleResize() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (wrapper && wrapper.children.length > 0) renderChartBars(true);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', function() {
                        renderChartBars(true);
                        window.addEventListener('resize', handleResize);
                    });
                } else {
                    renderChartBars(true);
                    window.addEventListener('resize', handleResize);
                }

                // Only rebuild if the wrapper was wiped out by something else.
                var observer = new MutationObserver(function() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (wrapper && wrapper.children.length === 0) renderChartBars(true);
                });
                observer.observe(document.body, { childList: true, subtree: true });

                window.renderChartBars = renderChartBars;
                window.updateChartBars = updateChartBars;
            </script>
        </div>

        <!-- Current Balance Card (with Peak Balance badge) -->
        <div class="stat-card current-balance-card">
            <div class="card-label">Current Balance</div>
            <div class="card-value-row">
                <div class="card-value <?= $currentBalance >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    <span class="currency-symbol">$</span>
                    <span class="value-amount"><?= number_format($currentBalance, 2) ?></span>
                </div>

                <?php
                $peakBalance = $recentHighestBalance;
                $isPeakAbove = ($peakBalance > $currentBalance);
                ?>
                <div class="peak-balance <?= $isPeakAbove ? 'peak-above' : '' ?>">
                    <span class="peak-label">Peak</span>
                    <span class="peak-value">
                        <span class="peak-currency">$</span><span class="peak-amount"><?= number_format($peakBalance, 2) ?></span>
                    </span>
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
        </div>
    </div>

    <!-- Loyalty / Contract Card -->
    <?php if ($hasProgramme && $userHasVps && $broker_connected): ?>
    <div class="loyalty-card" id="loyaltyCard">
        <div class="loyalty-header">
            <div class="loyalty-status">
                <span class="status-indicator <?= strpos($loyalties_message, 'Active') !== false ? 'active' : (strpos($loyalties_message, 'Completed') !== false ? 'completed' : '') ?>" id="statusIndicator"></span>
                <span class="loyalty-status-msg" id="loyaltyStatusMsg"><?= htmlspecialchars($loyalties_message) ?></span>
            </div>
            <div class="loyalty-badge" id="loyaltyBadge"><?= htmlspecialchars($loyalty_text) ?></div>
        </div>

        <div class="loyalty-body">
            <div class="contract-dates" id="contractDates" style="<?= ($is_contract_active && $executionStartDate && $executionStartDate !== '0000-00-00') ? '' : 'display:none;' ?>">
                <span class="date-label">Started</span>
                <span class="date-value" id="contractStartDate"><?= htmlspecialchars($formatted_start_date) ?></span>
                <span class="date-divider">→</span>
                <span class="date-label">Ends</span>
                <span class="date-value" id="contractEndDate"><?= htmlspecialchars($formatted_end_date) ?></span>
            </div>

            <div class="contract-duration" id="contractDurationRow" style="<?= $is_contract_active ? '' : 'display:none;' ?>">
                <span class="duration-label">Contract Duration</span>
                <span class="duration-value" id="contractDurationValue"><?= $CONTRACT_DURATION ?> days</span>
            </div>

            <div class="split-threshold" id="splitThresholdRow" style="<?= ($MIN_PROFIT_FOR_SPLIT > 0) ? '' : 'display:none;' ?>">
                <span class="threshold-label">Min profit for split</span>
                <span class="threshold-value" id="splitThresholdValue">$<?= number_format($MIN_PROFIT_FOR_SPLIT, 2) ?></span>
            </div>
        </div>

        <div class="loyalty-actions">
            <button
                id="loyaltyActionBtn"
                <?= $loyalty_btn_onclick ?>
                class="btn-action <?= htmlspecialchars($loyalty_btn_class) ?>"
                <?= ($loyalty_btn_action === '') ? 'disabled' : '' ?>
            >
                <?= htmlspecialchars($loyalty_btn_text) ?>
            </button>

            <button
                id="loyaltyDepositBtn"
                onclick="openApplyModal()"
                class="btn-action btn-loyalty-action"
                style="<?= ($loyalty_btn_action === 'deposit') ? '' : 'display:none;' ?>"
            >
                I have deposited, apply for verification
            </button>
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
    // =====================================================================
    // LIVE DASHBOARD — polls every 1 second and refreshes every field
    // =====================================================================

    var DASHBOARD_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            var url = new URL('mydashboard.php', base);
            return url.toString();
        } catch (e) {
            return 'mydashboard.php';
        }
    })();

    // --- Cached DOM references ---
    var depositBalanceEl = document.querySelector('.stat-card:first-child .value-amount');
    var profitLossEl     = document.querySelector('.stat-card:nth-child(2) .value-amount');
    var currentBalanceEl = document.querySelector('.stat-card:nth-child(3) .value-amount');
    var peakAmountEl     = document.querySelector('.peak-balance .peak-amount');
    var peakContainer    = document.querySelector('.peak-balance');

    var isUpdating       = false;
    var updateInterval   = null;
    var retryCount       = 0;
    var MAX_RETRIES      = 5;
    var currentInterval  = 1000;
    var pollRunning      = true;

    // ---------- Animation helper ----------
    function animateValue(element, start, end, prefix, suffix, duration) {
        if (!element) return;
        prefix = prefix || '';
        suffix = suffix || '';
        duration = duration || 300;

        start = parseFloat(String(start).replace(/[^0-9.-]/g, '')) || 0;
        end   = parseFloat(String(end).replace(/[^0-9.-]/g, '')) || 0;

        if (start === end) {
            element.innerText = prefix + end.toFixed(2) + suffix;
            return;
        }

        var range = end - start;
        var current = start;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var elapsed = timestamp - startTime;
            var progress = Math.min(1, elapsed / duration);
            current = start + (range * progress);
            element.innerText = prefix + current.toFixed(2) + suffix;
            if (progress < 1) requestAnimationFrame(step);
            else element.innerText = prefix + end.toFixed(2) + suffix;
        }
        requestAnimationFrame(step);
    }

    function toNum(v) {
        return parseFloat(String(v).replace(/[^0-9.-]/g, '')) || 0;
    }

    // ---------- Update all dashboard UI from the JSON payload ----------
    function refreshDashboardUI(data) {
        if (!data || !data.success) return;

        var depositNum = toNum(data.deposit_balance);
        var profitNum  = toNum(data.profit_loss);
        var currentNum = toNum(data.current_balance);
        var peakNum    = toNum(data.recent_highest_balance);

        // ---- Balances ----
        if (depositBalanceEl && data.deposit_balance !== undefined) {
            animateValue(depositBalanceEl, depositBalanceEl.innerText, depositNum, '');
        }

        if (profitLossEl && data.profit_loss !== undefined) {
            animateValue(profitLossEl, profitLossEl.innerText, profitNum, '');

            var pnlCard = profitLossEl.closest('.card-value');
            if (pnlCard) pnlCard.className = 'card-value ' + data.profit_loss_class;

            // P&L indicator — always derived from the polled value
            var pnlIndicator = document.querySelector('.pnl-card .pnl-indicator');
            if (pnlIndicator) {
                if (profitNum > 0) {
                    pnlIndicator.className = 'pnl-indicator positive';
                    pnlIndicator.textContent = '▲ Profit';
                } else if (profitNum < 0) {
                    pnlIndicator.className = 'pnl-indicator negative';
                    pnlIndicator.textContent = '▼ Loss';
                } else {
                    pnlIndicator.className = 'pnl-indicator neutral';
                    pnlIndicator.textContent = '— Static';
                }
            }
        }

        if (currentBalanceEl && data.current_balance !== undefined) {
            animateValue(currentBalanceEl, currentBalanceEl.innerText, currentNum, '');

            var currentCard = currentBalanceEl.closest('.card-value');
            if (currentCard) currentCard.className = 'card-value ' + data.current_balance_class;

            // Current balance indicator — Nourishing / Deteriorating / Fallow
            var currentIndicator = document.querySelector('.current-balance-card .pnl-indicator');
            if (currentIndicator) {
                if (currentNum > depositNum) {
                    currentIndicator.className = 'pnl-indicator positive';
                    currentIndicator.textContent = '▲ Nourishing';
                } else if (currentNum < depositNum) {
                    currentIndicator.className = 'pnl-indicator negative';
                    currentIndicator.textContent = '▼ Deteriorating';
                } else {
                    currentIndicator.className = 'pnl-indicator neutral';
                    currentIndicator.textContent = '— Fallow';
                }
            }
        }

        // ---- Peak badge ----
        if (peakAmountEl && data.recent_highest_balance !== undefined) {
            peakAmountEl.textContent = peakNum.toFixed(2);
            if (peakContainer) {
                if (data.peak_above) peakContainer.classList.add('peak-above');
                else                 peakContainer.classList.remove('peak-above');
            }
        }

        // ---- Chart ----
        // Pass the fresh values into the chart function. The chart itself
        // will short-circuit if the values are unchanged, so no flicker.
        if (typeof window.renderChartBars === 'function') {
            window.renderChartBars(false, depositNum, currentNum, !!data.broker_connected);
        }

        // ---- Contract dates ----
        var contractDatesEl = document.getElementById('contractDates');
        if (contractDatesEl) {
            if (data.formatted_start_date && data.formatted_end_date) {
                document.getElementById('contractStartDate').textContent = data.formatted_start_date;
                document.getElementById('contractEndDate').textContent   = data.formatted_end_date;
                contractDatesEl.style.display = '';
            } else {
                contractDatesEl.style.display = 'none';
            }
        }

        // ---- Contract duration row ----
        var contractDurationRow = document.getElementById('contractDurationRow');
        if (contractDurationRow) {
            if (data.is_contract_active) {
                var durVal = document.getElementById('contractDurationValue');
                if (durVal) durVal.textContent = data.contract_duration + ' days';
                contractDurationRow.style.display = '';
            } else {
                contractDurationRow.style.display = 'none';
            }
        }

        // ---- Split threshold row ----
        var splitRow = document.getElementById('splitThresholdRow');
        if (splitRow) {
            if (data.min_profit_for_split > 0) {
                var splitVal = document.getElementById('splitThresholdValue');
                if (splitVal) splitVal.textContent = '$' + parseFloat(data.min_profit_for_split).toFixed(2);
                splitRow.style.display = '';
            } else {
                splitRow.style.display = 'none';
            }
        }

        // ---- Loyalty text ----
        var loyaltyBadge = document.getElementById('loyaltyBadge');
        if (loyaltyBadge && data.loyalty_text !== undefined) loyaltyBadge.innerHTML = data.loyalty_text;

        var loyaltiesMsgEl = document.getElementById('loyaltyStatusMsg');
        if (loyaltiesMsgEl && data.loyalties_message !== undefined) {
            loyaltiesMsgEl.innerHTML = data.loyalties_message;
        }

        // ---- Status indicator dot ----
        var statusIndicator = document.getElementById('statusIndicator');
        if (statusIndicator && data.loyalties_message) {
            statusIndicator.classList.remove('active', 'completed');
            if (data.loyalties_message.indexOf('Active') !== -1) {
                statusIndicator.classList.add('active');
            } else if (data.loyalties_message.indexOf('Completed') !== -1) {
                statusIndicator.classList.add('completed');
            }
        }

        // ---- Dashboard disclaimer ----
        var disclaimerEl = document.getElementById('dashboardDisclaimer');
        if (disclaimerEl && data.dashboard_disclaimer !== undefined) {
            var badge = document.getElementById('paymentRequiredBadge');
            disclaimerEl.innerHTML = '';
            disclaimerEl.appendChild(document.createTextNode(data.dashboard_disclaimer));
            if (badge) {
                badge.style.display = (data.loyalties_status === 'unpaid-payment') ? '' : 'none';
                disclaimerEl.appendChild(badge);
            }
        }

        // ---- Encouragement note ----
        var encourageEl = document.getElementById('encouragementNote');
        if (encourageEl) {
            if (profitNum < 0 && data.contract_completed) {
                encourageEl.style.display = '';
            } else {
                encourageEl.style.display = 'none';
            }
        }

        // ---- Threshold warning ----
        var thresholdWarn = document.getElementById('thresholdWarning');
        var thresholdTxt  = document.getElementById('thresholdWarningText');
        if (thresholdWarn && thresholdTxt) {
            var minSplit = parseFloat(String(data.min_profit_for_split).replace(/[^0-9.-]/g, '')) || 0;
            if (data.contract_completed && profitNum > 0 && profitNum <= minSplit) {
                thresholdTxt.textContent =
                    'Your profit of $' + profitNum.toFixed(2) +
                    ' is below the minimum split threshold of $' + minSplit.toFixed(2) +
                    '. No profit split required - you can enroll directly.';
                thresholdWarn.style.display = '';
            } else {
                thresholdWarn.style.display = 'none';
            }
        }

        // ---- Loyalty action button ----
        var loyaltyBtn = document.getElementById('loyaltyActionBtn');
        var depositBtn = document.getElementById('loyaltyDepositBtn');

        if (loyaltyBtn) {
            if (data.show_explore_programme || data.has_programme === false) {
                loyaltyBtn.textContent = "Explore Programme";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-explore-programme';
                loyaltyBtn.onclick = function() { window.location.href = 'programmes.php'; };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_get_vps || data.user_has_vps === false) {
                loyaltyBtn.textContent = "Get VPS";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-get-vps';
                loyaltyBtn.onclick = function() { window.location.href = 'vps.php'; };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.reset_contract === 1) {
                loyaltyBtn.textContent = "Let's get started";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-reset';
                loyaltyBtn.onclick = openResetModal;
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_connect_broker) {
                loyaltyBtn.textContent = "Connect Broker";
                loyaltyBtn.className = 'btn-action btn-connect-broker';
                loyaltyBtn.onclick = function() { window.location.href = 'app.php#connect_investor_broker'; };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_apply_button) {
                loyaltyBtn.textContent = "Apply for Verification";
                loyaltyBtn.className = 'btn-action btn-apply';
                loyaltyBtn.onclick = openApplyModal;
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_reenroll_button) {
                loyaltyBtn.textContent = "Enroll";
                loyaltyBtn.className = 'btn-action btn-loyalty-action';
                loyaltyBtn.onclick = openReenrollModal;
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_payment_note) {
                loyaltyBtn.textContent = data.loyalty_btn_text || "Awaiting Confirmation";
                loyaltyBtn.className = 'btn-action btn-loyalty-paid';
                loyaltyBtn.onclick = null;
                loyaltyBtn.disabled = true;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.loyalty_btn_text) {
                loyaltyBtn.textContent = data.loyalty_btn_text;
                loyaltyBtn.className = 'btn-action ' + (data.loyalty_btn_class || '');
                loyaltyBtn.disabled = false;

                switch (data.loyalty_btn_action) {
                    case 'explore_programme':
                        loyaltyBtn.onclick = function() { window.location.href = 'programmes.php'; };
                        break;
                    case 'get_vps':
                        loyaltyBtn.onclick = function() { window.location.href = 'vps.php'; };
                        break;
                    case 'enroll':
                        loyaltyBtn.onclick = openReenrollModal;
                        break;
                    case 'deposit':
                        var brokerTarget = '<?= htmlspecialchars($brokerTarget) ?>';
                        loyaltyBtn.onclick = function() { window.open(brokerTarget, '_blank'); };
                        if (depositBtn) depositBtn.style.display = '';
                        break;
                    case 'profit_split_redirect':
                        loyaltyBtn.onclick = function() { window.location.href = 'profit_split.php'; };
                        break;
                    case 'payment_failed_redirect':
                        loyaltyBtn.onclick = function() { window.location.href = 'profit_split.php?retry=1'; };
                        break;
                    case 'apply':
                        loyaltyBtn.onclick = openApplyModal;
                        break;
                    case 'reset':
                        loyaltyBtn.onclick = openResetModal;
                        break;
                    case 'connect_broker':
                        loyaltyBtn.onclick = function() { window.location.href = 'app.php#connect_investor_broker'; };
                        break;
                    case 'find_manager':
                        loyaltyBtn.onclick = function() { window.location.href = 'programmes.php'; };
                        break;
                    default:
                        loyaltyBtn.disabled = true;
                        if (depositBtn) depositBtn.style.display = 'none';
                        break;
                }

                if (data.loyalty_btn_action !== 'deposit' && depositBtn) {
                    depositBtn.style.display = 'none';
                }
            }
        }

        // ---- Programme name / developer name in header ----
        var devLabel = document.getElementById('accountDeveloperLabel');
        var progName = document.getElementById('accountProgrammeName');
        if (devLabel && progName) {
            if (data.has_programme && data.developer_name) {
                devLabel.textContent = data.developer_name + "'s Programme";
                devLabel.style.display = '';
                if (data.programme_name) {
                    progName.textContent = data.programme_name;
                    progName.style.display = '';
                } else {
                    progName.style.display = 'none';
                }
            } else {
                devLabel.style.display = 'none';
                progName.style.display = 'none';
            }
        }
    }

    // ---------- Poll the server ----------
    async function fetchLiveBalances() {
        if (isUpdating) return;
        isUpdating = true;

        try {
            var response = await fetch(DASHBOARD_POLL_URL, {
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
                refreshDashboardUI(data);
            } else if (data && data.error) {
                retryCount++;
            }
        } catch (error) {
            retryCount++;
        } finally {
            isUpdating = false;
        }

        if (retryCount === 0) {
            currentInterval = 1000;
        } else if (retryCount === 1) {
            currentInterval = 3000;
        } else if (retryCount === 2) {
            currentInterval = 5000;
        } else if (retryCount >= MAX_RETRIES) {
            currentInterval = 15000;
        }

        scheduleNextPoll();
    }

    function scheduleNextPoll() {
        if (!pollRunning) return;
        if (updateInterval) clearTimeout(updateInterval);
        updateInterval = setTimeout(fetchLiveBalances, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchLiveBalances();
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

    window.addEventListener('focus', function() { if (pollRunning) fetchLiveBalances(); });

    startLiveUpdates();

    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>

<script>
    // ============== NOTIFICATION SYSTEM ==============
    let notificationPanelOpen = false;

    var NOTIF_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('mydashboard.php', base).toString();
        } catch (e) {
            return 'mydashboard.php';
        }
    })();

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
        fetch(NOTIF_URL, {
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
        fetch(NOTIF_URL, {
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
        fetch(NOTIF_URL, {
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
<script>
    // ============== ACCOUNT MANAGEMENT AUTO-SYNC ==============
    (function() {
        var syncInterval = null;
        var isSyncing = false;

        var SYNC_URL = (function() {
            try {
                var base = document.baseURI || window.location.href;
                return new URL('mydashboard.php', base).toString();
            } catch (e) {
                return 'mydashboard.php';
            }
        })();

        function syncAccountManagement() {
            if (isSyncing) return;
            isSyncing = true;

            fetch(SYNC_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'sync_account_management=1',
                credentials: 'same-origin'
            })
            .then(function(response) { return response.json(); })
            .then(function() { isSyncing = false; })
            .catch(function() { isSyncing = false; });
        }

        function startSync(intervalSeconds) {
            stopSync();
            syncAccountManagement();
            syncInterval = setInterval(syncAccountManagement, intervalSeconds * 1000);
        }

        function stopSync() {
            if (syncInterval) {
                clearInterval(syncInterval);
                syncInterval = null;
            }
        }

        startSync(10);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) syncAccountManagement();
        });

        window.addEventListener('focus', function() {
            syncAccountManagement();
        });

        window.addEventListener('beforeunload', function() {
            stopSync();
        });

        window.syncAccountManagement = syncAccountManagement;
    })();
</script>
</body>
</html>