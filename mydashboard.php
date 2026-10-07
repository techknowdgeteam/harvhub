<?php
   // mydashboard.php
    session_start();

    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isDirectCall = ($scriptName === 'mydashboard.php');

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

    $tableName                 = "harvhub";
    $serverAccountTable        = "server_account";
    $revenueHistoryTable       = "revenue_history";
    $vpsTable                  = "vps";
    $vpsFollowersTable         = "vps_hosts_followers";
    $vpsRequestorsTable        = "vps_hosts_requestors";
    $programmeInvestorsTable   = "programme_investors";
    $programmeTable            = "programme";
    $mainAccountsTable         = "main_accounts";
    $programmeRevenueDealsTable= "programme_revenue_deals";

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

    require_once __DIR__ . '/notification_service.php';

    $activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

    if ($activeSubAccountId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
        $stmt->execute([$activeSubAccountId, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    $userId             = (int)$user['id'];
    $activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
    $mainAccountId      = (int)($user['main_account_id'] ?? 0);

    $_SESSION['active_sub_account_id']  = $activeSubAccountId;
    $_SESSION['active_main_account_id'] = $mainAccountId;

    if ($activeSubAccountId <= 0) {
        $repair = $pdo->prepare("UPDATE $tableName SET sub_account_id = id WHERE id = ?");
        $repair->execute([$userId]);
        $activeSubAccountId = $userId;
        $_SESSION['active_sub_account_id'] = $activeSubAccountId;
    }

    if (!function_exists('normalizeAccountName')) {
        function normalizeAccountName($name) {
            $name = preg_replace('/[^A-Za-z0-9 ]+/', '', (string)$name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = strtolower(trim($name));
            return substr($name, 0, 20);
        }
    }

    if (!function_exists('resolveDeveloperDisplayName')) {
        function resolveDeveloperDisplayName(array $devRow) {
            $username = trim((string)($devRow['username']   ?? ''));
            if ($username !== '') return $username;
            $firstName = trim((string)($devRow['first_name'] ?? ''));
            if ($firstName !== '') return $firstName;
            $lastName = trim((string)($devRow['last_name'] ?? ''));
            if ($lastName !== '') return $lastName;
            $fullName = trim((string)($devRow['fullname'] ?? ''));
            if ($fullName !== '') return $fullName;
            return 'N/A';
        }
    }

    if (!function_exists('resolveGreetingName')) {
        function resolveGreetingName(array $u) {
            $firstName = trim((string)($u['first_name'] ?? ''));
            if ($firstName !== '') return $firstName;
            $fullName = trim((string)($u['fullname'] ?? ''));
            if ($fullName !== '') {
                $parts = preg_split('/\s+/', $fullName);
                return $parts[0] ?? $fullName;
            }
            return 'there';
        }
    }

    if (!function_exists('getLatestRevenueHistoryForSubAccount')) {
        function getLatestRevenueHistoryForSubAccount($pdo, $revenueHistoryTable, $email, $subAccountId) {
            if ($subAccountId <= 0) return false;

            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM $revenueHistoryTable
                    WHERE user_email = ?
                      AND sub_account_id = ?
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $subAccountId]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                return false;
            }
        }
    }

    if (!function_exists('updateRevenueHistoryLoyaltiesForSubAccount')) {
        function updateRevenueHistoryLoyaltiesForSubAccount($pdo, $revenueHistoryTable, $email, $subAccountId, $loyaltiesStatus, $paymentDetails = null) {
            if ($subAccountId <= 0) return false;

            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM $revenueHistoryTable
                    WHERE user_email = ?
                      AND sub_account_id = ?
                    ORDER BY created_at DESC, id DESC
                    LIMIT 1
                ");
                $stmt->execute([$email, $subAccountId]);
                $latestRecord = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$latestRecord) return false;

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
                $params[] = $subAccountId;

                $updateStmt = $pdo->prepare(
                    "UPDATE $revenueHistoryTable SET " . implode(', ', $setClauses) .
                    " WHERE id = ? AND sub_account_id = ?"
                );
                $updateStmt->execute($params);
                return true;
            } catch (PDOException $e) {
                return false;
            }
        }
    }

    if (!function_exists('buildInvestorContractId')) {
        function buildInvestorContractId(string $startYmd, string $endYmd, int $developerId, int $subAccountId): string {
            $startFormatted = date('dmY', strtotime($startYmd));
            $endFormatted   = date('dmY', strtotime($endYmd));
            $devIdForContract = $developerId > 0 ? $developerId : 0;
            return "sd-{$startFormatted}-ed-{$endFormatted}-dev-{$devIdForContract}-sub-{$subAccountId}";
        }
    }

    if (!function_exists('syncInvestorProfitIntoProgrammeRevenueDeals')) {
        function syncInvestorProfitIntoProgrammeRevenueDeals($pdo, $programmeRevenueDealsTable, $investorUserId, $investorSubAccountId, $pnl, $contractId = null) {
            if ($investorUserId <= 0 || $investorSubAccountId <= 0) return;

            try {
                if ($contractId !== null && $contractId !== '') {
                    $q = $pdo->prepare("
                        SELECT contract_id, accepted_at, contract_duration, investor_profit
                        FROM $programmeRevenueDealsTable
                        WHERE investor_id = ?
                          AND investor_sub_account_id = ?
                          AND investor_contract_id = ?
                        ORDER BY contract_id DESC
                        LIMIT 1
                    ");
                    $q->execute([$investorUserId, $investorSubAccountId, $contractId]);
                } else {
                    $q = $pdo->prepare("
                        SELECT contract_id, accepted_at, contract_duration, investor_profit
                        FROM $programmeRevenueDealsTable
                        WHERE investor_id = ?
                          AND investor_sub_account_id = ?
                        ORDER BY contract_id DESC
                        LIMIT 1
                    ");
                    $q->execute([$investorUserId, $investorSubAccountId]);
                }
                $deal = $q->fetch(PDO::FETCH_ASSOC);

                if (!$deal) return;

                $dealId = (int)$deal['contract_id'];

                $shouldUpdate = true;
                if (!empty($deal['accepted_at']) && (int)$deal['contract_duration'] > 0) {
                    try {
                        $start = new DateTime($deal['accepted_at']);
                        $end   = clone $start;
                        $end->modify('+' . (int)$deal['contract_duration'] . ' days');

                        $today = new DateTime();
                        $today->setTime(0, 0, 0);
                        $endClone = clone $end;
                        $endClone->setTime(0, 0, 0);
                        $diff = (int)$today->diff($endClone)->format('%r%a');

                        if ($diff <= 0) {
                            $currentFrozen = (float)($deal['investor_profit'] ?? 0);
                            if (abs($currentFrozen) > 0.0001) {
                                $shouldUpdate = false;
                            }
                        }
                    } catch (Exception $e) {
                    }
                }

                if (!$shouldUpdate) return;

                $u = $pdo->prepare("UPDATE $programmeRevenueDealsTable SET investor_profit = ? WHERE contract_id = ?");
                $u->execute([$pnl, $dealId]);

            } catch (Throwable $e) {
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['set_sub_account_name'])) {
            header('Content-Type: application/json; charset=utf-8');

            $rawName = (string)($_POST['account_name'] ?? '');
            $normalized = normalizeAccountName($rawName);

            if ($normalized === '') {
                echo json_encode(['success' => false, 'errors' => ['Account name is required.']]);
                exit;
            }
            if (strlen($normalized) > 20) {
                echo json_encode(['success' => false, 'errors' => ['Account name cannot exceed 20 characters.']]);
                exit;
            }

            try {
                $chk = $pdo->prepare("
                    SELECT id FROM $tableName
                    WHERE LOWER(email) = ?
                      AND sub_account_id <> ?
                      AND LOWER(sub_account_name) = ?
                    LIMIT 1
                ");
                $chk->execute([$email, $activeSubAccountId, $normalized]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => false, 'errors' => ['That account name is already used. Please choose another.']]);
                    exit;
                }
            } catch (Throwable $e) {}

            try {
                $upd = $pdo->prepare("UPDATE $tableName SET sub_account_name = ? WHERE id = ?");
                $upd->execute([$normalized, $userId]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Account name saved.',
                    'account_name' => $normalized
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'errors' => ['Failed to save account name.']]);
            }
            exit;
        }

        if (isset($_POST['mark_notifications_read'])) {
            header('Content-Type: application/json; charset=utf-8');
            $ok = markContractNotificationsRead($pdo, $email, $activeSubAccountId);
            echo json_encode([
                'success' => $ok,
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['get_notifications_list'])) {
            header('Content-Type: application/json; charset=utf-8');
            $list = getContractNotifications($pdo, $email, $activeSubAccountId, 100);
            echo json_encode([
                'success' => true,
                'notifications' => $list,
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['check_new_notifications'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['get_notification_preferences'])) {
            header('Content-Type: application/json; charset=utf-8');
            $prefs = getNotificationPreferences($pdo, $email, $mainAccountId);
            echo json_encode([
                'success' => true,
                'preferences' => [
                    'browser_notifications_enabled' => (int)($prefs['browser_notifications_enabled'] ?? 0),
                    'notification_sound_enabled' => (int)($prefs['notification_sound_enabled'] ?? 0),
                    'permission_prompt_seen' => (int)($prefs['permission_prompt_seen'] ?? 0)
                ]
            ]);
            exit;
        }

        if (isset($_POST['save_notification_preferences'])) {
            header('Content-Type: application/json; charset=utf-8');

            $browser = isset($_POST['browser_notifications_enabled'])
                ? (int)$_POST['browser_notifications_enabled'] : null;
            $sound = isset($_POST['notification_sound_enabled'])
                ? (int)$_POST['notification_sound_enabled'] : null;
            $seen = isset($_POST['permission_prompt_seen'])
                ? (int)$_POST['permission_prompt_seen'] : null;

            $ok = saveNotificationPreferences(
                $pdo,
                $email,
                $mainAccountId,
                $browser,
                $sound,
                $seen
            );

            echo json_encode(['success' => $ok]);
            exit;
        }

        if (isset($_POST['toggle_dark_mode_ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            $v = isset($_POST['dark_mode_checkbox']) ? (int)$_POST['dark_mode_checkbox'] : 0;
            $v = $v ? 1 : 0;
            try {
                $u = $pdo->prepare("UPDATE $tableName SET dark_mode = ? WHERE LOWER(email) = ?");
                $u->execute([$v, $email]);
                echo json_encode(['success'=>true,'dark_mode'=>$v]);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'dark_mode'=>(int)($user['dark_mode'] ?? 0)]);
            }
            exit;
        }

        if (isset($_POST['connect_broker_ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            $broker = trim($_POST['broker'] ?? '');
            $server = trim($_POST['server'] ?? '');
            $login  = trim($_POST['login'] ?? '');
            $broker_password = $_POST['broker_password'] ?? '';
            $errors = [];
            if ($broker === '') $errors[] = 'Broker is required';
            if ($server === '') $errors[] = 'Server is required';
            if ($login === '') $errors[] = 'Login is required';
            if ($broker_password === '') $errors[] = 'Password is required';
            if ($errors) { echo json_encode(['success'=>false,'errors'=>$errors]); exit; }

            $allowed_brokers = [];
            try {
                $q = $pdo->query("SELECT brokers FROM $serverAccountTable LIMIT 1");
                $cfg = $q->fetch(PDO::FETCH_ASSOC);
                foreach (explode(',', $cfg['brokers'] ?? '') as $entry) {
                    $entry = trim($entry);
                    if ($entry === '') continue;
                    $name = strpos($entry, ':') !== false ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
                    $name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
                    if ($name !== '') $allowed_brokers[] = ucfirst($name);
                }
                $allowed_brokers = array_values(array_unique($allowed_brokers));
                sort($allowed_brokers);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'errors'=>['Failed to load broker configuration.']]);
                exit;
            }
            if (!in_array($broker, $allowed_brokers, true)) {
                echo json_encode(['success'=>false,'errors'=>['Invalid broker selected.']]);
                exit;
            }

            try {
                $dup = $pdo->prepare("
                    SELECT id FROM $tableName
                    WHERE LOWER(email) = ?
                      AND sub_account_id <> ?
                      AND login = ?
                    LIMIT 1
                ");
                $dup->execute([$email, $activeSubAccountId, $login]);
                if ($dup->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode([
                        'success' => false,
                        'errors'  => ['This broker login is already connected to another one of your accounts.']
                    ]);
                    exit;
                }
            } catch (Throwable $e) {}

            try {
                $u = $pdo->prepare("UPDATE $tableName SET broker = ?, server = ?, login = ?, broker_password = ? WHERE id = ?");
                $u->execute([$broker,$server,$login,$broker_password,$userId]);

                recordContractNotification($pdo, [
                    'user_email' => $email,
                    'sub_account_id' => $activeSubAccountId,
                    'main_account_id' => $mainAccountId,
                    'notification_key' => 'broker-details-connected-' . $activeSubAccountId . '-' . date('YmdHis') . '-' . $userId,
                    'title' => 'Broker Details Connected',
                    'message' => 'Your broker details have been connected successfully for this account.',
                    'type' => 'success',
                    'section' => 'Broker',
                    'action_tab' => 'connect_investor_broker',
                    'force' => true
                ]);

                echo json_encode(['success'=>true,'message'=>'Broker connected successfully!']);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false,'errors'=>['Failed to save broker details.']]);
            }
            exit;
        }

        if (isset($_POST['sync_account_management'])) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $fresh = $pdo->prepare("SELECT dark_mode, balance_display, broker, server, login, balance_verification, reset_contract, execution_start_date FROM $tableName WHERE id = ? LIMIT 1");
                $fresh->execute([$userId]);
                $row = $fresh->fetch(PDO::FETCH_ASSOC) ?: [];
                echo json_encode(['success'=>true,'dark_mode'=>(int)($row['dark_mode'] ?? 0),'balance_display'=>$row['balance_display'] ?? 'show','broker_connected'=>(!empty($row['broker']) && !empty($row['server']) && !empty($row['login'])),'balance_verification'=>$row['balance_verification'] ?? 'not-verified','reset_contract'=>(int)($row['reset_contract'] ?? 0),'execution_start_date'=>$row['execution_start_date'] ?? null]);
            } catch (Throwable $e) {
                echo json_encode(['success'=>false]);
            }
            exit;
        }

        if (isset($_POST['complete_profile_ajax'])) {
            header('Content-Type: application/json; charset=utf-8');

            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name']  ?? '');
            $username  = trim($_POST['username']   ?? '');

            $errors = [];
            if ($firstName === '' || mb_strlen($firstName) > 80) $errors[] = 'First name is required (max 80 characters).';
            if ($lastName  === '' || mb_strlen($lastName)  > 80) $errors[] = 'Last name is required (max 80 characters).';
            if ($username  === '' || mb_strlen($username)  > 80) $errors[] = 'Username is required (max 80 characters).';
            if ($username !== '' && !preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
                $errors[] = 'Username may only contain letters, numbers, dots, underscores, and hyphens (3–80 characters).';
            }

            if ($errors) {
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit;
            }

            try {
                $chk = $pdo->prepare("SELECT id FROM $tableName WHERE username = ? AND LOWER(email) <> ? LIMIT 1");
                $chk->execute([$username, $email]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => false, 'errors' => ['That username is already taken. Please choose another.']]);
                    exit;
                }
            } catch (Throwable $e) {}

            $fullName = trim($firstName . ' ' . $lastName);

            try {
                $upd = $pdo->prepare("
                    UPDATE $tableName
                    SET first_name = ?, last_name = ?, username = ?, fullname = ?
                    WHERE LOWER(email) = ?
                ");
                $upd->execute([$firstName, $lastName, $username, $fullName, $email]);

                echo json_encode([
                    'success'    => true,
                    'message'    => 'Profile saved.',
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'username'   => $username,
                    'fullname'   => $fullName
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'errors' => ['Failed to save profile. Please try again.']]);
            }
            exit;
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$serverAccount) {
        die("Server configuration not found. Please contact administrator.");
    }

    $darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
    $darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

    $SERVER_MIN_BROKER_BALANCE   = (float)($serverAccount['min_broker_balance'] ?? 0);
    $SERVER_CONTRACT_DURATION    = (int)($serverAccount['contract_duration'] ?? 30);
    $SERVER_SHARE_PERCENT        = (int)($serverAccount['server_share_percent'] ?? 30);
    $SERVER_USER_SHARE_PERCENT   = (int)($serverAccount['user_share_percent'] ?? 70);
    $SERVER_MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);
    $MIN_INITIAL_DEPOSIT         = (float)($serverAccount['min_broker_balance'] ?? 0);

    $profFirstName = trim((string)($user['first_name'] ?? ''));
    $profLastName  = trim((string)($user['last_name']  ?? ''));
    $profUsername  = trim((string)($user['username']   ?? ''));

    $needsProfileCompletion = (
        $profFirstName === '' ||
        $profLastName  === '' ||
        $profUsername  === ''
    );

    $subAccountName   = trim((string)($user['sub_account_name'] ?? ''));
    $needsAccountName = ($subAccountName === '');

    $greetingName = resolveGreetingName($user);

    $activeInvestment = null;
    $investmentDeveloper = null;
    $investmentProgramme  = null;

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM $programmeInvestorsTable
            WHERE investorid = ?
              AND sub_account_id = ?
            ORDER BY invested_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $activeInvestment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($activeInvestment) {
            if (!empty($activeInvestment['programme_id'])) {
                $stmtP = $pdo->prepare("SELECT * FROM $programmeTable WHERE id = ? LIMIT 1");
                $stmtP->execute([(int)$activeInvestment['programme_id']]);
                $investmentProgramme = $stmtP->fetch(PDO::FETCH_ASSOC);
            }

            if (!empty($activeInvestment['developerid'])) {
                $stmtD = $pdo->prepare("SELECT id, fullname, first_name, last_name, username, email FROM $tableName WHERE id = ? LIMIT 1");
                $stmtD->execute([(int)$activeInvestment['developerid']]);
                $investmentDeveloper = $stmtD->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $activeInvestment = null;
    }

    $hasProgramme = ($activeInvestment && $activeInvestment['programme_id']);

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
        if ($investmentDeveloper) {
            $DEVELOPER_NAME = resolveDeveloperDisplayName($investmentDeveloper);
            $DEVELOPER_ID   = (int)$investmentDeveloper['id'];
        }
    }

    $brokerBalance          = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss          = (float)($user['profitandloss'] ?? 0);
    $executionStartDate     = $user['execution_start_date'] ?? null;
    $loyaltiesStatus        = $user['loyalties'] ?? null;
    $resetContract          = (int)($user['reset_contract'] ?? 0);
    $balanceVerificationStatus = $user['balance_verification'] ?? 'not-verified';
    $recentHighestBalance   = (float)($user['recent_highest_balance'] ?? 0);

    $userHasVps = false;
    $userOwnsVps = false;

    try {
        $stmt = $pdo->prepare("
            SELECT id
            FROM $vpsTable
            WHERE user_id = ?
              AND sub_account_id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $userHasVps  = true;
            $userOwnsVps = true;
        }
    } catch (PDOException $e) {}

    if (!$userHasVps) {
        try {
            $stmt = $pdo->prepare("
                SELECT id
                FROM $vpsFollowersTable
                WHERE follower_id = ?
                  AND follower_sub_account_id = ?
                  AND host_status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$userId, $activeSubAccountId]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $userHasVps = true;
            }
        } catch (PDOException $e) {}
    }

    $incomingInvestmentRequestCount = 0;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS cnt
            FROM programme_investment_requestors r
            WHERE r.developerid = ?
              AND r.owner_sub_account_id = ?
              AND r.request_status = 'pending'
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $incomingInvestmentRequestCount = (int)($row['cnt'] ?? 0);
    } catch (PDOException $e) {
        $incomingInvestmentRequestCount = 0;
    }

    $incomingVpsRequestCount = 0;
    if ($userOwnsVps) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM vps_hosts_requestors
                WHERE owner_id = ?
                  AND sub_account_id = ?
                  AND request_status = 'pending'
            ");
            $stmt->execute([$userId, $activeSubAccountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $incomingVpsRequestCount = (int)($row['cnt'] ?? 0);
        } catch (PDOException $e) {
            $incomingVpsRequestCount = 0;
        }
    }

    $hasAnyIncomingRequests = ($incomingInvestmentRequestCount > 0 || $incomingVpsRequestCount > 0);

    $latestRevenueRecord = getLatestRevenueHistoryForSubAccount(
        $pdo,
        $revenueHistoryTable,
        $email,
        $activeSubAccountId
    );

    if ($loyaltiesStatus !== null && $latestRevenueRecord) {
        $latestRevenueLoyalty = $latestRevenueRecord['loyalties'] ?? null;
        if ($latestRevenueLoyalty !== $loyaltiesStatus) {
            updateRevenueHistoryLoyaltiesForSubAccount(
                $pdo,
                $revenueHistoryTable,
                $email,
                $activeSubAccountId,
                $loyaltiesStatus
            );
            $latestRevenueRecord = getLatestRevenueHistoryForSubAccount(
                $pdo,
                $revenueHistoryTable,
                $email,
                $activeSubAccountId
            );
        }
    }

    $currentInvestorContractId = null;
    if ($latestRevenueRecord && !empty($latestRevenueRecord['contract_id'])) {
        $currentInvestorContractId = (string)$latestRevenueRecord['contract_id'];
    }

    syncInvestorProfitIntoProgrammeRevenueDeals(
        $pdo,
        $programmeRevenueDealsTable,
        $userId,
        $activeSubAccountId,
        $profitAndLoss,
        $currentInvestorContractId
    );

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

        $hasConfirmedPayment = false;
        foreach ($allLoyaltyStatuses as $s) {
            if (in_array($s, $confirmedStatuses, true)) { $hasConfirmedPayment = true; break; }
        }

        if (!$hasConfirmedPayment) {
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

        if ($hasConfirmedPayment) {
            $state['show_payment_confirmed_notice'] = true;
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

    $fullName        = $user['fullname'];
    $login           = $user['login'] ?? 'N/A';
    $server          = $user['server'] ?? 'N/A';
    $balanceDisplay  = $user['balance_display'] ?? 'show';
    $broker          = strtolower($user['broker'] ?? 'unknown');
    $tradesString    = $user['trades'] ?? '';
    $broker_connected = (!empty($user['broker']) && !empty($user['server']) && !empty($user['login']));
    $application_status = $user['application_status'] ?? '';

    syncDashboardContractNotifications(
        $pdo,
        $user,
        $latestRevenueRecord,
        $userHasVps,
        (bool)$hasProgramme,
        $broker_connected,
        $is_contract_active,
        $contract_completed,
        $brokerBalance,
        $MIN_INITIAL_DEPOSIT,
        (string)$balanceVerificationStatus,
        $mainAccountId,
        $activeSubAccountId
    );

    $depositBalance = $brokerBalance;
    $currentBalance = $brokerBalance + $profitAndLoss;

    $profitToSplit = max(0, $profitAndLoss);
    $serverShare   = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
    $userShare     = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_balance_display'])) {
        $currentStatus = $user['balance_display'];
        $newStatus = ($currentStatus === 'show') ? 'hide' : 'show';

        $upd = $pdo->prepare("UPDATE $tableName SET balance_display = ? WHERE id = ?");
        $upd->execute([$newStatus, $userId]);

        if ($newStatus === 'show') unset($_SESSION['password_verified']);
        unset($_SESSION['password_error']);
        $_SESSION['prg_redirect_safe'] = true;
        $_SESSION['toggle_success_message'] = "Balance display toggled to " . ucfirst($newStatus) . ".";

        header("Location: mydashboard.php", true, 303);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reenroll'])) {
        $today   = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$CONTRACT_DURATION} days", strtotime($today)));

        $contractId = buildInvestorContractId($today, $endDate, $DEVELOPER_ID, $activeSubAccountId);

        $stmt = $pdo->prepare("SELECT broker_balance FROM $tableName WHERE id = ?");
        $stmt->execute([$userId]);
        $currentData     = $stmt->fetch(PDO::FETCH_ASSOC);
        $startingBalance = (float)($currentData['broker_balance'] ?? 0);

        $insertStmt = $pdo->prepare("
            INSERT INTO $revenueHistoryTable
            (user_email, sub_account_id, contract_id, execution_start_date, execution_end_date, starting_balance, current_balance, profit, user_share, server_share, loyalties, invested_with, developer_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $email,
            $activeSubAccountId,
            $contractId,
            $today,
            $endDate,
            $startingBalance,
            $startingBalance,
            0,
            0,
            0,
            'active',
            $DEVELOPER_NAME ?: null,
            $DEVELOPER_ID > 0 ? $DEVELOPER_ID : 0
        ]);

        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = NULL, profitandloss = 0, execution_start_date = ?, contract_id = ?, reset_contract = 0 WHERE id = ?");
        $upd->execute([$today, $contractId, $userId]);

        try {
            $dealUpd = $pdo->prepare("
                UPDATE $programmeRevenueDealsTable
                SET investor_contract_id = ?,
                    accepted_at = ?,
                    investor_profit = 0.00
                WHERE investor_id = ?
                  AND investor_sub_account_id = ?
                  AND (investor_contract_id IS NULL OR investor_contract_id = '')
                ORDER BY contract_id DESC
                LIMIT 1
            ");
            $dealUpd->execute([$contractId, $today . ' 00:00:00', $userId, $activeSubAccountId]);
        } catch (Throwable $e) {
            try {
                $dealUpd = $pdo->prepare("
                    UPDATE $programmeRevenueDealsTable
                    SET investor_contract_id = ?,
                        accepted_at = ?,
                        investor_profit = 0.00
                    WHERE investor_id = ?
                      AND investor_sub_account_id = ?
                    ORDER BY contract_id DESC
                    LIMIT 1
                ");
                $dealUpd->execute([$contractId, $today . ' 00:00:00', $userId, $activeSubAccountId]);
            } catch (Throwable $e2) {}
        }

        unset($_SESSION['reenroll_password_verified']);
        unset($_SESSION['reenroll_password_verified_time']);

        $_SESSION['prg_redirect_safe'] = true;
        $_SESSION['enroll_success_message'] = "Contract enrolled successfully! Your " . $CONTRACT_DURATION . "-day contract has started.";

        recordContractNotification($pdo, [
            'user_email' => $email,
            'sub_account_id' => $activeSubAccountId,
            'main_account_id' => $mainAccountId,
            'notification_key' => 'contract-enrolled-' . $activeSubAccountId . '-' . $contractId,
            'title' => 'Contract Enrolled',
            'message' => 'Your new ' . $CONTRACT_DURATION . '-day trading contract is now active.',
            'type' => 'success',
            'section' => 'Contract',
            'action_tab' => 'mydashboard',
            'force' => true
        ]);

        header("Location: mydashboard.php", true, 303);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_for_verification'])) {
        try {
            $upd = $pdo->prepare("UPDATE $tableName SET balance_verification = 'applied-for-verification' WHERE id = ?");
            $upd->execute([$userId]);

            $_SESSION['apply_success_message'] = "Your application has been submitted successfully!";
            $_SESSION['apply_success_details'] = "Our team will verify your account. Please ensure you have deposited the minimum required amount of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . ".";
            $_SESSION['prg_redirect_safe'] = true;

            recordContractNotification($pdo, [
                'user_email' => $email,
                'sub_account_id' => $activeSubAccountId,
                'main_account_id' => $mainAccountId,
                'notification_key' => 'verification-applied-' . $activeSubAccountId . '-' . date('YmdHis') . '-' . $userId,
                'title' => 'Verification Application Submitted',
                'message' => 'Your balance verification application has been submitted and is waiting for confirmation.',
                'type' => 'info',
                'section' => 'Verification',
                'action_tab' => 'mydashboard',
                'force' => true
            ]);

            header("Location: mydashboard.php", true, 303);
            exit;
        } catch (Exception $e) {
            $_SESSION['apply_error'] = "An error occurred. Please try again.";
            header("Location: mydashboard.php", true, 303);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset_contract'])) {
        if ($latestRevenueRecord && $latestRevenueRecord['loyalties'] !== 'payment-confirmed') {
            updateRevenueHistoryLoyaltiesForSubAccount(
                $pdo,
                $revenueHistoryTable,
                $email,
                $activeSubAccountId,
                'payment-confirmed'
            );
        }

        if ($latestRevenueRecord && !empty($latestRevenueRecord['contract_id'])) {
            syncInvestorProfitIntoProgrammeRevenueDeals(
                $pdo,
                $programmeRevenueDealsTable,
                $userId,
                $activeSubAccountId,
                $profitAndLoss,
                (string)$latestRevenueRecord['contract_id']
            );
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
        $params[] = $userId;

        $upd = $pdo->prepare("UPDATE $tableName SET " . implode(', ', $setClauses) . " WHERE id = ?");
        $upd->execute($params);

        $_SESSION['prg_redirect_safe'] = true;
        $_SESSION['reset_success_message'] = "Your contract has been reset successfully. Please apply for verification to start a new contract.";

        recordContractNotification($pdo, [
            'user_email' => $email,
            'sub_account_id' => $activeSubAccountId,
            'main_account_id' => $mainAccountId,
            'notification_key' => 'contract-reset-' . $activeSubAccountId . '-' . date('YmdHis') . '-' . $userId,
            'title' => 'Contract Reset',
            'message' => 'Your previous contract has been reset. Apply for verification to begin a new contract.',
            'type' => 'info',
            'section' => 'Contract',
            'action_tab' => 'mydashboard',
            'force' => true
        ]);

        header("Location: mydashboard.php", true, 303);
        exit;
    }

    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

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

        $liveSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);

        if ($liveSubId > 0) {
            $stmt = $pdo->prepare("
                SELECT id, sub_account_id, main_account_id, sub_account_name,
                       fullname, first_name, last_name, username, broker, server, login, broker_balance, profitandloss,
                       loyalties, execution_start_date, application_status, balance_verification,
                       reset_contract, recent_highest_balance, balance_display
                FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1
            ");
            $stmt->execute([$liveSubId, $email]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, sub_account_id, main_account_id, sub_account_name,
                       fullname, first_name, last_name, username, broker, server, login, broker_balance, profitandloss,
                       loyalties, execution_start_date, application_status, balance_verification,
                       reset_contract, recent_highest_balance, balance_display
                FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1
            ");
            $stmt->execute([$email]);
        }
        $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$liveUser) {
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $liveSubAccountId = (int)($liveUser['sub_account_id'] ?? $liveUser['id']);
        $liveUserId       = (int)$liveUser['id'];

        $liveInvestment = null;
        try {
            $s = $pdo->prepare("SELECT * FROM $programmeInvestorsTable WHERE investorid = ? AND sub_account_id = ? ORDER BY invested_at DESC, id DESC LIMIT 1");
            $s->execute([$liveUserId, $liveSubAccountId]);
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
        $liveDeveloperId   = 0;

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
                    $d = $pdo->prepare("SELECT id, fullname, first_name, last_name, username FROM $tableName WHERE id = ? LIMIT 1");
                    $d->execute([(int)$liveInvestment['developerid']]);
                    $rowD = $d->fetch(PDO::FETCH_ASSOC);
                    if ($rowD) {
                        $liveDeveloperName = resolveDeveloperDisplayName($rowD);
                        $liveDeveloperId   = (int)$rowD['id'];
                    }
                } catch (PDOException $e) {}
            }
        }

        $liveUserHasVps = false;

        try {
            $s = $pdo->prepare("
                SELECT id
                FROM $vpsTable
                WHERE user_id = ?
                  AND sub_account_id = ?
                LIMIT 1
            ");
            $s->execute([$liveUserId, $liveSubAccountId]);
            if ($s->fetch(PDO::FETCH_ASSOC)) {
                $liveUserHasVps = true;
            }
        } catch (PDOException $e) {}

        if (!$liveUserHasVps) {
            try {
                $s = $pdo->prepare("
                    SELECT id
                    FROM $vpsFollowersTable
                    WHERE follower_id = ?
                      AND follower_sub_account_id = ?
                      AND host_status = 'active'
                    LIMIT 1
                ");
                $s->execute([$liveUserId, $liveSubAccountId]);
                if ($s->fetch(PDO::FETCH_ASSOC)) {
                    $liveUserHasVps = true;
                }
            } catch (PDOException $e) {}
        }

        $latestRevenueRecord = getLatestRevenueHistoryForSubAccount(
            $pdo,
            $revenueHistoryTable,
            $email,
            $liveSubAccountId
        );

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
                $updPeak = $pdo->prepare("UPDATE $tableName SET recent_highest_balance = ?, recent_highest_balance_last_update = CURDATE() WHERE id = ?");
                $updPeak->execute([$currentBalance, $liveUserId]);
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

        $liveContractId = null;
        if ($latestRevenueRecord && !empty($latestRevenueRecord['contract_id'])) {
            $liveContractId = (string)$latestRevenueRecord['contract_id'];
        }

        syncInvestorProfitIntoProgrammeRevenueDeals(
            $pdo,
            $programmeRevenueDealsTable,
            $liveUserId,
            $liveSubAccountId,
            $profitAndLoss,
            $liveContractId
        );

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

        $liveIncomingInvestmentCount = 0;
        try {
            $q = $pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM programme_investment_requestors r
                WHERE r.developerid = ?
                  AND r.owner_sub_account_id = ?
                  AND r.request_status = 'pending'
            ");
            $q->execute([$liveUserId, $liveSubAccountId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            $liveIncomingInvestmentCount = (int)($row['cnt'] ?? 0);
        } catch (PDOException $e) {}

        $liveIncomingVpsCount = 0;
        $liveOwnsVps = false;
        try {
            $s = $pdo->prepare("SELECT id FROM $vpsTable WHERE user_id = ? AND sub_account_id = ? LIMIT 1");
            $s->execute([$liveUserId, $liveSubAccountId]);
            if ($s->fetch(PDO::FETCH_ASSOC)) $liveOwnsVps = true;
        } catch (PDOException $e) {}

        if ($liveOwnsVps) {
            try {
                $q = $pdo->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM vps_hosts_requestors
                    WHERE owner_id = ?
                      AND sub_account_id = ?
                      AND request_status = 'pending'
                ");
                $q->execute([$liveUserId, $liveSubAccountId]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $liveIncomingVpsCount = (int)($row['cnt'] ?? 0);
            } catch (PDOException $e) {}
        }

        echo json_encode([
            'success'                       => true,
            'sub_account_id'                => $liveSubAccountId,
            'sub_account_name'              => (string)($liveUser['sub_account_name'] ?? ''),
            'main_account_id'               => (int)($liveUser['main_account_id'] ?? 0),
            'deposit_balance'               => number_format($brokerBalance, 2, '.', ''),
            'profit_loss'                   => number_format($profitAndLoss, 2, '.', ''),
            'current_balance'               => number_format($currentBalance, 2, '.', ''),
            'recent_highest_balance'        => number_format($recentHighestBalance, 2, '.', ''),
            'profit_loss_class'             => $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative',
            'current_balance_class'         => $currentBalance >= 0 ? 'profit-positive' : 'profit-negative',
            'peak_above'                    => ($recentHighestBalance > $currentBalance),
            'contract_days_left'            => $is_contract_active ? $contractDaysLeft : 0,
            'is_contract_active'            => $is_contract_active,
            'contract_completed'            => $contract_completed,
            'formatted_start_date'          => $formatted_start_date,
            'formatted_end_date'            => $formatted_end_date,
            'contract_duration'             => $liveContractDuration,
            'balance_unverified'            => $balance_unverified,
            'balance_under_verification'    => $balance_under_verification,
            'balance_check_failed'          => $balance_check_failed,
            'min_initial_deposit'           => $liveMinInitialDeposit,
            'broker_connected'              => $liveBrokerConnected,
            'user_has_vps'                  => $liveUserHasVps,
            'user_owns_vps'                 => $liveOwnsVps,
            'has_programme'                 => $liveHasProgramme,
            'incoming_investment_requests'  => $liveIncomingInvestmentCount,
            'incoming_vps_requests'         => $liveIncomingVpsCount,
            'programme_name'                => $liveProgrammeName,
            'developer_name'                => $liveDeveloperName,
            'min_profit_for_split'          => $liveMinProfitSplit,
            'min_broker_balance'            => $liveMinBrokerBalance,
            'server_share_percent'          => $liveServerSharePercent,
            'user_share_percent'            => $liveUserSharePercent,
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
            'profit_to_split'               => number_format(max(0, $profitAndLoss), 2, '.', ''),
            'application_status'            => $liveUser['application_status'] ?? '',
        ]);
        exit;
    }

    $loyalty_btn_onclick = '';
    switch ($loyalty_btn_action) {
        case 'explore_programme':
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'programmes\')"';
            break;
        case 'get_vps':
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'vps\')"';
            break;
        case 'enroll':
            $loyalty_btn_onclick = 'onclick="openReenrollModal()"';
            break;
        case 'profit_split_redirect':
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'profit_split\')"';
            break;
        case 'payment_failed_redirect':
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'profit_split\')"';
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
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'connect_investor_broker\')"';
            break;
        case 'find_manager':
            $loyalty_btn_onclick = 'onclick="harvhubGoToTab(\'programmes\')"';
            break;
        default:
            $loyalty_btn_onclick = '';
            break;
    }

    $availableBrokers = [];
    foreach (explode(',', $serverAccount['brokers'] ?? '') as $entry) {
        $entry = trim($entry);
        if ($entry === '') continue;
        $name = strpos($entry, ':') !== false ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
        $name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
        if ($name !== '') $availableBrokers[] = ucfirst($name);
    }
    $availableBrokers = array_values(array_unique($availableBrokers));
    sort($availableBrokers);

    $sessionModalState = '';
    $enrollSuccessMessage = $_SESSION['enroll_success_message'] ?? null;
    $applySuccessMessage  = $_SESSION['apply_success_message'] ?? null;
    $applySuccessDetails  = $_SESSION['apply_success_details'] ?? null;
    $resetSuccessMessage  = $_SESSION['reset_success_message'] ?? null;
    $toggleSuccessMessage = $_SESSION['toggle_success_message'] ?? null;

    if ($needsProfileCompletion) {
        $sessionModalState = '';
    } elseif ($enrollSuccessMessage !== null) {
        $sessionModalState = 'enroll_success';
        unset($_SESSION['enroll_success_message'], $_SESSION['enroll_success_details']);
    } elseif ($applySuccessMessage !== null) {
        $sessionModalState = 'apply_success';
        unset($_SESSION['apply_success_message'], $_SESSION['apply_success_details']);
    } elseif ($resetSuccessMessage !== null) {
        $sessionModalState = 'reset_success';
        unset($_SESSION['reset_success_message']);
    } elseif ($toggleSuccessMessage !== null) {
        $sessionModalState = 'toggle_success';
        unset($_SESSION['toggle_success_message']);
    } else {
        $allModalLoyaltyStatuses = [];
        if ($loyaltiesStatus !== null) $allModalLoyaltyStatuses[] = $loyaltiesStatus;
        if ($latestRevenueRecord && isset($latestRevenueRecord['loyalties']) && $latestRevenueRecord['loyalties'] !== null) {
            $allModalLoyaltyStatuses[] = $latestRevenueRecord['loyalties'];
        }
        $allModalLoyaltyStatuses = array_values(array_unique($allModalLoyaltyStatuses));

        $hasConfirmedPayment = in_array('payment-confirmed', $allModalLoyaltyStatuses, true);
        $hasPendingPayment = false; $hasFailedPayment = false; $hasUnpaidPayment = false;
        if (!$hasConfirmedPayment) {
            foreach ($allModalLoyaltyStatuses as $status) {
                if (in_array($status, ['payment-made','contract-cancelled-payment-made'], true)) { $hasPendingPayment = true; break; }
            }
            if (!$hasPendingPayment) foreach ($allModalLoyaltyStatuses as $status) {
                if (in_array($status, ['payment-failed','failed-payment','contract-cancelled-failed-payment','contract-cancelled-payment-failed'], true)) { $hasFailedPayment = true; break; }
            }
            if (!$hasPendingPayment && !$hasFailedPayment) foreach ($allModalLoyaltyStatuses as $status) {
                if (in_array($status, ['unpaid-payment','unpaid','contract-cancelled-unpaid','contract-cancelled-unpaid-payment','contract-cancelled-payment-required'], true)) { $hasUnpaidPayment = true; break; }
            }
        }

        if ($hasPendingPayment) {
            $sessionModalState = 'payment_pending';
        } elseif ($hasFailedPayment) {
            $sessionModalState = 'payment_failed';
        } elseif ($hasUnpaidPayment) {
            $sessionModalState = 'profit_split';
        }
        elseif (!$userHasVps) {
            $sessionModalState = 'no_vps';
        }
        elseif (!$broker_connected) {
            $sessionModalState = 'no_broker';
        }
        elseif (!$hasProgramme) {
            $sessionModalState = 'no_programme';
        }
        elseif ($resetContract === 1) {
            $sessionModalState = 'reset';
        } elseif ($balanceVerificationStatus === 'applied-for-verification') {
            $sessionModalState = 'under_review';
        } elseif ($balanceVerificationStatus === 'not-verified' || $balanceVerificationStatus === '' || $balanceVerificationStatus === null) {
            $sessionModalState = 'apply';
        } elseif ($balanceVerificationStatus === 'verified' && $brokerBalance < $MIN_INITIAL_DEPOSIT) {
            $sessionModalState = 'deposit';
        } elseif ($is_contract_active) {
            $sessionModalState = 'active';
        } elseif ($show_reenroll_button) {
            $sessionModalState = 'enroll';
        }

        if ($sessionModalState !== '') {
            if (!isset($_SESSION['shown_session_modals']) || !is_array($_SESSION['shown_session_modals'])) $_SESSION['shown_session_modals'] = [];
            if (in_array($sessionModalState, $_SESSION['shown_session_modals'], true)) $sessionModalState = '';
            else $_SESSION['shown_session_modals'][] = $sessionModalState;
        }
    }

    $sessionModalTitle = '';
    $sessionModalMessage = '';
    $sessionModalClass = 'info';
    switch ($sessionModalState) {
        case 'enroll_success': $sessionModalTitle='Enrollment Successful!'; $sessionModalMessage=$enrollSuccessMessage ?: 'Your contract has been enrolled successfully. Your trading contract is now active.'; $sessionModalClass='success'; break;
        case 'apply_success': $sessionModalTitle='Application Submitted!'; $sessionModalMessage=trim(($applySuccessMessage ?: 'Your application has been submitted successfully.') . ' ' . ($applySuccessDetails ?: '')); $sessionModalClass='success'; break;
        case 'reset_success': $sessionModalTitle='Contract Reset Successful'; $sessionModalMessage=$resetSuccessMessage ?: 'Your contract has been reset. Please apply for verification to start a new contract.'; $sessionModalClass='success'; break;
        case 'toggle_success': $sessionModalTitle='Balance Display Updated'; $sessionModalMessage=$toggleSuccessMessage ?: 'Your balance display preference has been saved.'; $sessionModalClass='success'; break;
        case 'payment_pending': $sessionModalTitle='Payment Pending Confirmation'; $sessionModalMessage='Your payment has been recorded and is awaiting server confirmation. You will be notified once it is confirmed.'; break;
        case 'payment_failed': $sessionModalTitle='Payment Failed'; $sessionModalMessage='Your previous payment attempt was not successful. Please retry the profit split payment to continue.'; $sessionModalClass='danger'; break;
        case 'profit_split': $sessionModalTitle='Profit Split Required'; $sessionModalMessage='Your contract has ended with profit. Please complete the profit split payment to continue with a new contract.'; $sessionModalClass='warning'; break;
        case 'payment_confirmed': $sessionModalTitle='Payment Confirmed'; $sessionModalMessage='Your payment has been confirmed. You can now proceed with the next steps to start a new contract.'; $sessionModalClass='success'; break;
        case 'no_vps': $sessionModalTitle='VPS Required'; $sessionModalMessage='You need a Virtual Private Server before connecting a broker or joining a programme. Get one to unlock your dashboard.'; $sessionModalClass='warning'; break;
        case 'no_broker': $sessionModalTitle='Connect Your Broker'; $sessionModalMessage='You need to connect your broker account before you can start trading. Use the Connect Broker button on the dashboard to continue.'; break;
        case 'no_programme': $sessionModalTitle='Explore Programmes'; $sessionModalMessage='You have not joined any programme yet. Explore programmes to start your investment journey.'; $sessionModalClass='info'; break;
        case 'reset': $sessionModalTitle='Time for the Next Phase!'; $sessionModalMessage='Your previous contract is complete. You can now start a new journey by applying for verification again.'; break;
        case 'under_review': $sessionModalTitle='Balance Under Review'; $sessionModalMessage='Your balance verification is in progress. This check usually takes between 24 and 48 hours. We will notify you once it is complete.'; break;
        case 'apply': $sessionModalTitle='Balance Verification Required'; $sessionModalMessage='Please apply for verification if you have deposited the minimum required amount into your broker account.'; break;
        case 'deposit': $sessionModalTitle='Deposit Required'; $sessionModalMessage='Your broker balance is below the minimum required deposit of $'.number_format($MIN_INITIAL_DEPOSIT,2).'. Please deposit funds to continue.'; $sessionModalClass='warning'; break;
        case 'active': $sessionModalTitle='Contract is Active'; $sessionModalMessage='Your trading contract is currently active. We will notify you when it ends.'; $sessionModalClass='success'; break;
        case 'enroll': $sessionModalTitle='Ready to Enroll'; $sessionModalMessage='You are ready to start a new trading contract. Click Enroll on the dashboard to begin.'; $sessionModalClass='success'; break;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Harvhub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<?php include 'style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<?php if ($needsAccountName && !$needsProfileCompletion): ?>
<div id="accountNameModal" class="modal active">
    <div class="modal-content">
        <h2 class="modal-title-accent">Name your account</h2>
        <p class="modal-description">
            Give this sub account a short name (max 20 characters, letters, numbers and spaces only).
            This is what you'll see in your account switcher.
        </p>

        <div class="ac-error" id="acErrorBox"></div>

        <form id="accountNameForm" onsubmit="submitAccountName(event)">
            <div class="modal-field">
                <label for="acName">Account name</label>
                <input id="acName" type="text" maxlength="20" autocomplete="off"
                       placeholder="e.g. my trading account"
                       value="" required>
                <div class="ac-hint">Letters, numbers and spaces only. Special characters are removed automatically. Max 20 characters. Must be unique for your accounts.</div>
            </div>

            <div class="modal-actions modal-actions-column">
                <button id="acSubmitBtn" type="submit" class="btn-full">Save account name</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($needsProfileCompletion): ?>
<div id="profileCompleteModal" class="modal active">
    <div class="modal-content">
        <h2 class="modal-title-accent">Complete your profile</h2>
        <p class="modal-description">
            Please provide your first name, last name, and a username to continue using HarvHub.
            These details are shared across all of your accounts.
        </p>

        <div class="pc-error" id="pcErrorBox"></div>
        <div class="pc-success" id="pcSuccessBox"></div>

        <form id="profileCompleteForm" onsubmit="submitProfileCompletion(event)">
            <div class="modal-field">
                <label for="pcFirstName">First name</label>
                <input id="pcFirstName" type="text" maxlength="80" autocomplete="given-name"
                       value="<?= htmlspecialchars($profFirstName) ?>" required>
            </div>

            <div class="modal-field">
                <label for="pcLastName">Last name</label>
                <input id="pcLastName" type="text" maxlength="80" autocomplete="family-name"
                       value="<?= htmlspecialchars($profLastName) ?>" required>
            </div>

            <div class="modal-field">
                <label for="pcUsername">Username</label>
                <input id="pcUsername" type="text" maxlength="80" autocomplete="username"
                       pattern="[A-Za-z0-9._\-]{3,80}"
                       title="3–80 characters; letters, numbers, dots, underscores and hyphens only"
                       value="<?= htmlspecialchars($profUsername) ?>" required>
                <div class="field-hint">
                    This is what investors will see next to your programme name. It is shared across all your accounts.
                </div>
            </div>

            <div class="modal-actions modal-actions-column">
                <button id="pcSubmitBtn" type="submit" class="btn-full">Save profile</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($sessionModalState !== ''): ?>
<div id="sessionInfoModal" class="modal session-info-modal active" data-state="<?= htmlspecialchars($sessionModalState) ?>">
    <div class="modal-content session-modal-content <?= htmlspecialchars($sessionModalClass) ?>">
        <h2 class="session-modal-title"><?= htmlspecialchars($sessionModalTitle) ?></h2>
        <p class="session-modal-message"><?= htmlspecialchars($sessionModalMessage) ?></p>
        <div class="modal-actions">
            <button type="button" class="btn-action btn-loyalty-action session-modal-ok" onclick="closeSessionInfoModal()">Okay</button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="mydashboard-box">

    <div id="thresholdWarning" class="threshold-warning" <?= ($contract_completed && $profitAndLoss <= $MIN_PROFIT_FOR_SPLIT && $profitAndLoss > 0) ? '' : 'style="display:none;"' ?>>
        <span class="warning-icon">!</span>
        <span id="thresholdWarningText">
            Your profit of $<?= number_format($profitAndLoss, 2) ?> is below the minimum split threshold of $<?= number_format($MIN_PROFIT_FOR_SPLIT, 2) ?>. No profit split required - you can enroll directly.
        </span>
    </div>

    <div class="md-greeting">
        <div class="md-greeting-top">
            <div class="md-greeting-avatar"><?= htmlspecialchars(strtoupper(substr($greetingName, 0, 1))) ?></div>
            <div class="md-greeting-text">
                <p class="md-greet-account" id="greetAccountName" <?= $subAccountName !== '' ? '' : 'style="display:none;"' ?>>
                    Account &nbsp;•&nbsp; <span id="greetAccountNameValue"><?= htmlspecialchars($subAccountName) ?></span>
                </p>
                <p class="md-greet-title">Hi there <?= htmlspecialchars($greetingName) ?></p>
                <p class="md-greet-sub" id="dashboardDisclaimer">
                    <span id="dashboardDisclaimerText"><?= htmlspecialchars($dashboard_disclaimer) ?></span>
                    <span class="payment-required-badge" id="paymentRequiredBadge" <?= ($loyaltiesStatus === 'unpaid-payment') ? '' : 'style="display:none;"' ?>>Payment Required</span>
                </p>
                <p class="md-greet-sub" id="encouragementNote" <?= ($profitAndLoss < 0 && $contract_completed) ? 'style="margin-top:6px;"' : 'style="display:none;margin-top:6px;"' ?>>
                    Don't give up! Every loss is a setup for a greater comeback! Your next contract could be your breakthrough!
                </p>
            </div>
        </div>

        <?php
        $showProgrammeCard = ($hasProgramme && $DEVELOPER_NAME !== 'N/A' && $DEVELOPER_NAME !== '');
        ?>
        <div class="md-greeting-divider" id="greetingDivider" <?= $showProgrammeCard ? '' : 'style="display:none;"' ?>></div>

        <button
            type="button"
            class="md-greeting-programme"
            id="greetingProgrammeBlock"
            onclick="goToProgrammes()"
            <?= $showProgrammeCard ? '' : 'style="display:none;"' ?>
            aria-label="View programmes"
        >
            <span class="md-prog-text">
                <span class="account-label" id="accountDeveloperLabel"><?= htmlspecialchars($DEVELOPER_NAME) ?>'s <span class="account-number" id="accountProgrammeName"><?= htmlspecialchars($PROGRAMME_NAME ?: 'Programme') ?></span></span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev account-label"></i>
        </button>

        <div class="md-greeting-actions" id="greetingActions">
            <?php if (!$userHasVps): ?>
                <a href="javascript:void(0)" id="greetingActionBtn" onclick="harvhubGoToTab('vps')" class="btn-account-action btn-get-vps">Get VPS</a>
            <?php elseif (!$broker_connected): ?>
                <a href="javascript:void(0)" id="greetingActionBtn" onclick="harvhubGoToTab('connect_investor_broker')" class="btn-account-action btn-connect-broker">Connect Broker</a>
            <?php elseif (!$hasProgramme): ?>
                <a href="javascript:void(0)" id="greetingActionBtn" onclick="harvhubGoToTab('programmes')" class="btn-account-action btn-get-vps">Explore Programme</a>
            <?php else: ?>
                <a href="javascript:void(0)" id="greetingActionBtn" style="display:none;" class="btn-account-action"></a>
            <?php endif; ?>
        </div>

        <div class="md-greeting-incoming" id="greetingIncomingRequests"
             <?= $hasAnyIncomingRequests ? '' : 'style="display:none;"' ?>>

            <button type="button"
                    class="md-incoming-row is-investment"
                    id="incomingInvestmentBtn"
                    onclick="goToTraderProgrammes()"
                    <?= ($incomingInvestmentRequestCount > 0) ? '' : 'style="display:none;"' ?>>
                <span class="md-incoming-row-left">
                    <span class="md-incoming-row-icon">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </span>
                    <span class="md-incoming-row-label">
                        <span class="md-incoming-row-title">Investment requests</span>
                        <span class="md-incoming-row-sub">Review and respond</span>
                    </span>
                </span>
                <span class="md-incoming-row-right">
                    <span class="md-incoming-row-count" id="incomingInvestmentCount">
                        <?= (int)$incomingInvestmentRequestCount ?>
                    </span>
                    <i class="fa-solid fa-chevron-right md-incoming-row-chev"></i>
                </span>
            </button>

            <button type="button"
                    class="md-incoming-row is-vps"
                    id="incomingVpsBtn"
                    onclick="harvhubGoToTab('vps')"
                    <?= ($incomingVpsRequestCount > 0) ? '' : 'style="display:none;"' ?>>
                <span class="md-incoming-row-left">
                    <span class="md-incoming-row-icon">
                        <i class="fa-solid fa-server"></i>
                    </span>
                    <span class="md-incoming-row-label">
                        <span class="md-incoming-row-title">VPS space requests</span>
                        <span class="md-incoming-row-sub">Review and respond</span>
                    </span>
                </span>
                <span class="md-incoming-row-right">
                    <span class="md-incoming-row-count" id="incomingVpsCount">
                        <?= (int)$incomingVpsRequestCount ?>
                    </span>
                    <i class="fa-solid fa-chevron-right md-incoming-row-chev"></i>
                </span>
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card balance-card">
            <div class="card-label">Total Investment</div>
            <?php if (!$userHasVps): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">VPS Required</span>
                </div>
                <div class="card-sub">Get a VPS to unlock your dashboard</div>
            <?php elseif (!$broker_connected): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">No connected broker</span>
                </div>
                <div class="card-sub">Connect your broker to get started</div>
            <?php elseif (!$hasProgramme): ?>
                <div class="card-value status-unverified">
                    <span class="status-badge warning">No Programme</span>
                </div>
                <div class="card-sub">Explore a programme to get started</div>
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

            <div class="md-account-meta">
                <div class="md-account-meta-item">
                    <span class="md-account-meta-label">Account</span>
                    <span class="md-account-meta-value" id="accountLoginValue"><?= htmlspecialchars($login) ?></span>
                </div>
                <div class="md-account-meta-item">
                    <span class="md-account-meta-label">Server</span>
                    <span class="md-account-meta-value" id="accountServerValue"><?= htmlspecialchars($server) ?></span>
                </div>
            </div>

            <button type="button" class="btn-revenue-history" onclick="openRevenueHistory()">
                View Revenue History
            </button>
        </div>

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

                var observer = new MutationObserver(function() {
                    var wrapper = document.getElementById('chartBarsWrapper');
                    if (wrapper && wrapper.children.length === 0) renderChartBars(true);
                });
                observer.observe(document.body, { childList: true, subtree: true });

                window.renderChartBars = renderChartBars;
                window.updateChartBars = updateChartBars;
            </script>
        </div>

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

    <div class="loyalty-card" id="loyaltyCard" <?= ($hasProgramme && $userHasVps && $broker_connected) ? '' : 'style="display:none;"' ?>>
        <div class="loyalty-header">
            <div class="loyalty-status">
                <span class="status-indicator <?= strpos($loyalties_message, 'Active') !== false ? 'active' : (strpos($loyalties_message, 'Completed') !== false ? 'completed' : '') ?>" id="statusIndicator"></span>
                <span class="loyalty-status-msg" id="loyaltyStatusMsg"><?= htmlspecialchars($loyalties_message) ?></span>
            </div>
            <div class="loyalty-badge" id="loyaltyBadge"><?= htmlspecialchars($loyalty_text) ?></div>
        </div>

        <div class="loyalty-body">
            <div class="contract-dates" id="contractDates" <?= ($is_contract_active && $executionStartDate && $executionStartDate !== '0000-00-00') ? '' : 'style="display:none;"' ?>>
                <span class="date-label">Started</span>
                <span class="date-value" id="contractStartDate"><?= htmlspecialchars($formatted_start_date) ?></span>
                <span class="date-divider">→</span>
                <span class="date-label">Ends</span>
                <span class="date-value" id="contractEndDate"><?= htmlspecialchars($formatted_end_date) ?></span>
            </div>

            <div class="contract-duration" id="contractDurationRow" <?= $is_contract_active ? '' : 'style="display:none;"' ?>>
                <span class="duration-label">Contract Duration</span>
                <span class="duration-value" id="contractDurationValue"><?= $CONTRACT_DURATION ?> days</span>
            </div>

            <div class="split-threshold" id="splitThresholdRow" <?= ($MIN_PROFIT_FOR_SPLIT > 0) ? '' : 'style="display:none;"' ?>>
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
                <?= ($loyalty_btn_action === 'deposit') ? '' : 'style="display:none;"' ?>
            >
                I have deposited, apply for verification
            </button>
        </div>
    </div>
</div>

<div id="reenrollModal" class="modal">
    <div class="modal-content">
        <h2>Contract Enrollment Protocol</h2>
        <p class="modal-description">
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
        <div class="modal-actions modal-actions-column">
            <button id="reenrollProceedBtn" class="reenroll-confirm-btn" disabled onclick="proceedToEnrollment()">
                Proceed to Enrollment
            </button>
            <button onclick="closeReenrollModal()" class="btn-cancel">
                Cancel
            </button>
        </div>
    </div>
</div>

<form id="reenrollForm" method="POST" action="mydashboard.php" style="display:none;">
    <input type="hidden" name="confirm_reenroll" value="1">
</form>

<div id="applyModal" class="modal">
    <div class="modal-content">
        <h2>Balance Verification Application</h2>
        <p class="modal-description">Before proceeding with your application, please ensure:</p>
        <div class="apply-instructions">
            <ul>
                <li>Your broker account is active and accessible with same login credentials</li>
                <li>You have deposited into your broker account</li>
            </ul>
        </div>
        <div class="apply-warning">
            <strong>Important:</strong>
            <p>Ensure you have deposited into your account before confirming application.</p>
        </div>
        <div class="modal-actions modal-actions-column">
            <form method="POST" action="mydashboard.php">
                <input type="hidden" name="apply_for_verification" value="1">
                <button type="submit" class="btn-full">Confirm Application</button>
            </form>
            <button onclick="closeApplyModal()" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<div id="resetModal" class="modal">
    <div class="modal-content">
        <h2>Start a new Journey</h2>
        <div class="reset-note">
            <strong>Important:</strong>
            <p>This will clear your current contract state (balance, profit &amp; loss, and verification status). Please ensure you have deposited funds into your broker account before applying for verification again.</p>
        </div>
        <div class="modal-actions modal-actions-column">
            <form method="POST" action="mydashboard.php">
                <input type="hidden" name="confirm_reset_contract" value="1">
                <button type="submit" class="btn-loyalty-action btn-full">Continue</button>
            </form>
            <button onclick="closeResetModal()" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: open ? 'harvhubModalOpen' : 'harvhubModalClose'
                }, '*');
            }
        } catch (e) {}
    }

    function anyModalOpen() {
        var ids = ['sessionInfoModal', 'profileCompleteModal', 'accountNameModal',
                   'reenrollModal', 'applyModal', 'resetModal'];
        for (var i = 0; i < ids.length; i++) {
            var el = document.getElementById(ids[i]);
            if (!el) continue;
            if (el.classList.contains('active')) return true;
            var disp = (el.style.display || '').toLowerCase();
            if (disp === 'flex' || disp === 'block') return true;
        }
        return false;
    }

    function lockBodyScroll() {
        document.body.classList.add('sd-scroll-locked');
    }

    function unlockBodyScrollIfNoModal() {
        if (!anyModalOpen()) {
            document.body.classList.remove('sd-scroll-locked');
        }
    }

    function maybeNotifyClose() {
        if (!anyModalOpen()) {
            unlockBodyScrollIfNoModal();
            notifyParentModal(false);
        }
    }

    function openModalShell(el) {
        if (!el) return;
        el.classList.add('active');
        el.style.display = 'flex';
        lockBodyScroll();
        notifyParentModal(true);
    }

    function closeModalShell(el) {
        if (!el) return;
        el.classList.remove('active');
        el.style.display = 'none';
        maybeNotifyClose();
    }

    (function bootstrapModalState() {
        if (anyModalOpen()) {
            lockBodyScroll();
            notifyParentModal(true);
        }
    })();

    document.addEventListener('DOMContentLoaded', function () {
        if (anyModalOpen()) {
            lockBodyScroll();
            notifyParentModal(true);
        }
    });

    setInterval(function () {
        if (anyModalOpen()) notifyParentModal(true);
    }, 500);

    window.addEventListener('beforeunload', function () {
        try { notifyParentModal(false); } catch (e) {}
    });

    function submitAccountName(ev) {
        ev.preventDefault();

        var btn = document.getElementById('acSubmitBtn');
        var errBox = document.getElementById('acErrorBox');
        var nameInput = document.getElementById('acName');
        var rawName = nameInput ? nameInput.value : '';

        var normalized = String(rawName)
            .replace(/[^A-Za-z0-9 ]+/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .substring(0, 20);

        errBox.style.display = 'none';
        errBox.textContent = '';

        if (normalized === '') {
            errBox.textContent = 'Please enter an account name.';
            errBox.style.display = 'block';
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

        fetch('mydashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'set_sub_account_name=1&account_name=' + encodeURIComponent(normalized)
        })
        .then(function(r) { return r.json().catch(function(){ return {}; }); })
        .then(function(data) {
            if (data && data.success) {
                var modal = document.getElementById('accountNameModal');
                if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
                maybeNotifyClose();
                window.location.reload();
            } else {
                if (btn) { btn.disabled = false; btn.textContent = 'Save account name'; }
                errBox.textContent = (data && data.errors ? data.errors.join(' ') : 'Failed to save account name. Please try again.');
                errBox.style.display = 'block';
            }
        })
        .catch(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Save account name'; }
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        });
    }
    window.submitAccountName = submitAccountName;

    function submitProfileCompletion(ev) {
        ev.preventDefault();

        var btn = document.getElementById('pcSubmitBtn');
        var errBox = document.getElementById('pcErrorBox');
        var okBox  = document.getElementById('pcSuccessBox');

        var firstName = document.getElementById('pcFirstName').value.trim();
        var lastName  = document.getElementById('pcLastName').value.trim();
        var username  = document.getElementById('pcUsername').value.trim();

        errBox.style.display = 'none';
        errBox.textContent = '';
        okBox.style.display = 'none';
        okBox.textContent = '';

        var errors = [];
        if (!firstName) errors.push('First name is required.');
        if (!lastName)  errors.push('Last name is required.');
        if (!username)  errors.push('Username is required.');
        if (username && !/^[a-zA-Z0-9._\-]{3,80}$/.test(username)) {
            errors.push('Username may only contain letters, numbers, dots, underscores, and hyphens (3–80 characters).');
        }
        if (errors.length) {
            errBox.textContent = errors.join(' ');
            errBox.style.display = 'block';
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

        fetch('mydashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'complete_profile_ajax=1'
                + '&first_name=' + encodeURIComponent(firstName)
                + '&last_name='  + encodeURIComponent(lastName)
                + '&username='   + encodeURIComponent(username)
        })
        .then(function(r) { return r.json().catch(function(){ return {}; }); })
        .then(function(data) {
            if (data && data.success) {
                okBox.textContent = 'Saved. Loading your dashboard...';
                okBox.style.display = 'block';
                setTimeout(function() {
                    var modal = document.getElementById('profileCompleteModal');
                    if (modal) { modal.classList.remove('active'); modal.style.display = 'none'; }
                    maybeNotifyClose();
                    window.location.reload();
                }, 700);
            } else {
                if (btn) { btn.disabled = false; btn.textContent = 'Save profile'; }
                errBox.textContent = (data.errors || ['Failed to save profile. Please try again.']).join(' ');
                errBox.style.display = 'block';
            }
        })
        .catch(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Save profile'; }
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        });
    }
    window.submitProfileCompletion = submitProfileCompletion;

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

    function goToProgrammes() {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'programmes' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=programmes';
    }
    window.goToProgrammes = goToProgrammes;

    function goToTraderProgrammes() {
        try {
            if (window.top && window.top !== window) {
                window.top.location.href = 'traderapp.php?tab=signals';
                return;
            }
            if (window.parent && window.parent !== window) {
                window.parent.location.href = 'traderapp.php?tab=signals';
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=signals';
    }
    window.goToTraderProgrammes = goToTraderProgrammes;

    function openRevenueHistory() { harvhubGoToTab('revenue_history'); }
    window.openRevenueHistory = openRevenueHistory;

    function closeSessionInfoModal() {
        var modal = document.getElementById('sessionInfoModal');
        closeModalShell(modal);
    }
    window.closeSessionInfoModal = closeSessionInfoModal;

    function openConnectBrokerModal() { harvhubGoToTab('connect_investor_broker'); }
    window.openConnectBrokerModal = openConnectBrokerModal;

    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href.split("?")[0]);
    }

    function openReenrollModal() {
        document.getElementById('reenrollConfirmCheck').checked = false;
        document.getElementById('reenrollProceedBtn').disabled = true;
        openModalShell(document.getElementById('reenrollModal'));
    }
    function closeReenrollModal() {
        closeModalShell(document.getElementById('reenrollModal'));
    }
    function toggleReenrollButton() {
        const checkbox = document.getElementById('reenrollConfirmCheck');
        const button = document.getElementById('reenrollProceedBtn');
        button.disabled = !checkbox.checked;
    }
    function proceedToEnrollment() {
        closeModalShell(document.getElementById('reenrollModal'));
        var form = document.getElementById('reenrollForm');
        form.action = 'mydashboard.php';
        form.submit();
    }

    function openApplyModal()  { openModalShell(document.getElementById('applyModal')); }
    function closeApplyModal() { closeModalShell(document.getElementById('applyModal')); }
    function openResetModal()  { openModalShell(document.getElementById('resetModal')); }
    function closeResetModal() { closeModalShell(document.getElementById('resetModal')); }

    window.openReenrollModal     = openReenrollModal;
    window.closeReenrollModal    = closeReenrollModal;
    window.toggleReenrollButton  = toggleReenrollButton;
    window.proceedToEnrollment   = proceedToEnrollment;
    window.openApplyModal        = openApplyModal;
    window.closeApplyModal       = closeApplyModal;
    window.openResetModal        = openResetModal;
    window.closeResetModal       = closeResetModal;
</script>

<script>
    var DASHBOARD_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            var url = new URL('mydashboard.php', base);
            return url.toString();
        } catch (e) {
            return 'mydashboard.php';
        }
    })();

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

    function refreshDashboardUI(data) {
        if (!data || !data.success) return;

        var depositNum = toNum(data.deposit_balance);
        var profitNum  = toNum(data.profit_loss);
        var currentNum = toNum(data.current_balance);
        var peakNum    = toNum(data.recent_highest_balance);

        if (depositBalanceEl && data.deposit_balance !== undefined) {
            animateValue(depositBalanceEl, depositBalanceEl.innerText, depositNum, '');
        }

        if (profitLossEl && data.profit_loss !== undefined) {
            animateValue(profitLossEl, profitLossEl.innerText, profitNum, '');

            var pnlCard = profitLossEl.closest('.card-value');
            if (pnlCard) pnlCard.className = 'card-value ' + data.profit_loss_class;

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

        if (peakAmountEl && data.recent_highest_balance !== undefined) {
            peakAmountEl.textContent = peakNum.toFixed(2);
            if (peakContainer) {
                if (data.peak_above) peakContainer.classList.add('peak-above');
                else                 peakContainer.classList.remove('peak-above');
            }
        }

        if (typeof window.renderChartBars === 'function') {
            window.renderChartBars(false, depositNum, currentNum, !!data.broker_connected);
        }

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

        var loyaltyBadge = document.getElementById('loyaltyBadge');
        if (loyaltyBadge && data.loyalty_text !== undefined) loyaltyBadge.innerHTML = data.loyalty_text;

        var loyaltiesMsgEl = document.getElementById('loyaltyStatusMsg');
        if (loyaltiesMsgEl && data.loyalties_message !== undefined) {
            loyaltiesMsgEl.innerHTML = data.loyalties_message;
        }

        var statusIndicator = document.getElementById('statusIndicator');
        if (statusIndicator && data.loyalties_message) {
            statusIndicator.classList.remove('active', 'completed');
            if (data.loyalties_message.indexOf('Active') !== -1) {
                statusIndicator.classList.add('active');
            } else if (data.loyalties_message.indexOf('Completed') !== -1) {
                statusIndicator.classList.add('completed');
            }
        }

        var greetAccountEl = document.getElementById('greetAccountName');
        var greetAccountValueEl = document.getElementById('greetAccountNameValue');
        if (greetAccountValueEl && data.sub_account_name !== undefined && data.sub_account_name !== '') {
            greetAccountValueEl.textContent = data.sub_account_name;
            if (greetAccountEl) greetAccountEl.style.display = '';
        }

        var disclaimerTextEl = document.getElementById('dashboardDisclaimerText');
        if (disclaimerTextEl && data.dashboard_disclaimer !== undefined) {
            disclaimerTextEl.textContent = data.dashboard_disclaimer;
        }
        var badge = document.getElementById('paymentRequiredBadge');
        if (badge) {
            badge.style.display = (data.loyalties_status === 'unpaid-payment') ? '' : 'none';
        }

        var encourageEl = document.getElementById('encouragementNote');
        if (encourageEl) {
            if (profitNum < 0 && data.contract_completed) {
                encourageEl.style.display = '';
            } else {
                encourageEl.style.display = 'none';
            }
        }

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

        var loyaltyCard = document.getElementById('loyaltyCard');
        var loyaltyBtn  = document.getElementById('loyaltyActionBtn');
        var depositBtn  = document.getElementById('loyaltyDepositBtn');

        var shouldShowLoyaltyCard = !!(data.has_programme && data.user_has_vps && data.broker_connected);
        if (loyaltyCard) {
            loyaltyCard.style.display = shouldShowLoyaltyCard ? '' : 'none';
        }

        if (loyaltyBtn) {
            if (data.show_get_vps || data.user_has_vps === false) {
                loyaltyBtn.textContent = "Get VPS";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-get-vps';
                loyaltyBtn.onclick = function() { harvhubGoToTab('vps'); };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_connect_broker || data.broker_connected === false) {
                loyaltyBtn.textContent = "Connect Broker";
                loyaltyBtn.className = 'btn-action btn-connect-broker';
                loyaltyBtn.onclick = function() { harvhubGoToTab('connect_investor_broker'); };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.show_explore_programme || data.has_programme === false) {
                loyaltyBtn.textContent = "Explore Programme";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-explore-programme';
                loyaltyBtn.onclick = function() { harvhubGoToTab('programmes'); };
                loyaltyBtn.disabled = false;
                if (depositBtn) depositBtn.style.display = 'none';
            } else if (data.reset_contract === 1) {
                loyaltyBtn.textContent = "Let's get started";
                loyaltyBtn.className = 'btn-action btn-loyalty-action btn-reset';
                loyaltyBtn.onclick = openResetModal;
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
                        loyaltyBtn.onclick = function() { harvhubGoToTab('programmes'); };
                        break;
                    case 'get_vps':
                        loyaltyBtn.onclick = function() { harvhubGoToTab('vps'); };
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
                        loyaltyBtn.onclick = function() { harvhubGoToTab('profit_split'); };
                        break;
                    case 'payment_failed_redirect':
                        loyaltyBtn.onclick = function() { harvhubGoToTab('profit_split'); };
                        break;
                    case 'apply':
                        loyaltyBtn.onclick = openApplyModal;
                        break;
                    case 'reset':
                        loyaltyBtn.onclick = openResetModal;
                        break;
                    case 'connect_broker':
                        loyaltyBtn.onclick = function() { harvhubGoToTab('connect_investor_broker'); };
                        break;
                    case 'find_manager':
                        loyaltyBtn.onclick = function() { harvhubGoToTab('programmes'); };
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

        var greetingDivider = document.getElementById('greetingDivider');
        var greetingProgrammeBlock = document.getElementById('greetingProgrammeBlock');
        var devLabel = document.getElementById('accountDeveloperLabel');
        var progName = document.getElementById('accountProgrammeName');

        var shouldShowProgramme = !!(data.has_programme && data.developer_name && data.developer_name !== 'N/A');
        if (greetingDivider) {
            greetingDivider.style.display = shouldShowProgramme ? '' : 'none';
        }
        if (greetingProgrammeBlock) {
            greetingProgrammeBlock.style.display = shouldShowProgramme ? '' : 'none';
        }
        if (devLabel && progName && shouldShowProgramme) {
            var progNameText = data.programme_name || 'Programme';
            devLabel.textContent = data.developer_name + "'s " + progNameText;
            progName.textContent = progNameText;
            progName.style.display = '';
        }

        var greetingActionBtn = document.getElementById('greetingActionBtn');
        if (greetingActionBtn) {
            if (!data.user_has_vps) {
                greetingActionBtn.style.display = '';
                greetingActionBtn.textContent = 'Get VPS';
                greetingActionBtn.className = 'btn-account-action btn-get-vps';
                greetingActionBtn.onclick = function() { harvhubGoToTab('vps'); };
            } else if (!data.broker_connected) {
                greetingActionBtn.style.display = '';
                greetingActionBtn.textContent = 'Connect Broker';
                greetingActionBtn.className = 'btn-account-action btn-connect-broker';
                greetingActionBtn.onclick = function() { harvhubGoToTab('connect_investor_broker'); };
            } else if (!data.has_programme) {
                greetingActionBtn.style.display = '';
                greetingActionBtn.textContent = 'Explore Programme';
                greetingActionBtn.className = 'btn-account-action btn-get-vps';
                greetingActionBtn.onclick = function() { harvhubGoToTab('programmes'); };
            } else {
                greetingActionBtn.style.display = 'none';
            }
        }

        var incomingWrap       = document.getElementById('greetingIncomingRequests');
        var incomingInvBtn     = document.getElementById('incomingInvestmentBtn');
        var incomingInvCountEl = document.getElementById('incomingInvestmentCount');
        var incomingVpsBtn     = document.getElementById('incomingVpsBtn');
        var incomingVpsCountEl = document.getElementById('incomingVpsCount');

        var invCount = parseInt(data.incoming_investment_requests, 10) || 0;
        var vpsCount = parseInt(data.incoming_vps_requests, 10) || 0;

        if (incomingInvBtn) {
            incomingInvBtn.style.display = (invCount > 0) ? '' : 'none';
        }
        if (incomingInvCountEl) {
            incomingInvCountEl.textContent = invCount > 99 ? '99+' : String(invCount);
        }
        if (incomingVpsBtn) {
            incomingVpsBtn.style.display = (vpsCount > 0) ? '' : 'none';
        }
        if (incomingVpsCountEl) {
            incomingVpsCountEl.textContent = vpsCount > 99 ? '99+' : String(vpsCount);
        }

        if (incomingWrap) {
            incomingWrap.style.display = (invCount > 0 || vpsCount > 0) ? '' : 'none';
        }
    }

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

    <?php if (!$needsProfileCompletion && !$needsAccountName): ?>
    startLiveUpdates();
    <?php endif; ?>

    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>

<script>
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

        <?php if (!$needsProfileCompletion && !$needsAccountName): ?>
        startSync(10);
        <?php endif; ?>

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

<script>
    (function () {
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
            if (e.data.type === 'reloadWithSpinner') {
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.postMessage({ type: 'showSpinner' }, '*');
                    }
                } catch (err) {}
                window.location.reload();
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
            'page-verify_code',
            'page-forgot_password'
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
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeClass: ['class'] });
        broadcast();

        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();
</script>
</body>
</html>