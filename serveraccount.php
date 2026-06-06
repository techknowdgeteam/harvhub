<?php
    // ============================================
    // SECTION 1: SESSION & INITIALIZATION
    // ============================================
    session_start();
    require_once 'db.php';

    // ============================================
    // ============================================
    // SESSION TIMEOUT - 1 MINUTE
    // ============================================
    $session_timeout = 60; // 1 minute in seconds

    // Check if user is logged in
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Check if last activity time is set
        if (isset($_SESSION['last_activity'])) {
            // Calculate session age
            $session_age = time() - $_SESSION['last_activity'];
            
            // If session is older than timeout, destroy it
            if ($session_age > $session_timeout) {
                session_unset();
                session_destroy();
                
                // Redirect to login page
                header("Location: serveraccount.php");
                exit;
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
    }

    // If not logged in, make sure last_activity is not set
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        unset($_SESSION['last_activity']);
    }

    // ============================================
    // SECTION 2: DATABASE FETCH & SERVER ACCOUNT
    // ============================================
    $stmt = $pdo->prepare("SELECT * FROM {$serverAccountTable} WHERE id = 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    // Initial setup check/initial row creation
    if (!$serverAccount) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$serverAccountTable} 
                (id, btc_address, eth_address, eth_network, usdt_address, usdt_network, admin_login_id, minimum_deposit, server_share_percent, user_share_percent, min_profit_for_split)
                VALUES (1, '', '', 'ERC20', '', 'TRC20', 'admin', 0.00, 30, 70, 30.00)
            ");
            $stmt->execute();
            $stmt = $pdo->prepare("SELECT * FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) { /* silent fail for setup */ }
    }

    $initialSetupRequired = empty($serverAccount['admin_password_hash'] ?? '');
    $currentView = $_GET['view'] ?? 'menu';
    $message = '';
    if (isset($_SESSION['admin_message'])) {
        $message = $_SESSION['admin_message'];
        unset($_SESSION['admin_message']);
    }

    // ============================================
    // SECTION 3: HELPER FUNCTIONS
    // ============================================

    function calculateUnpaidAge($startDate, $contractDuration, $endDate = null) {
        if (!$startDate || !$contractDuration || $contractDuration <= 0) {
            return ['ended_on' => null, 'age' => null, 'is_ended' => false];
        }
        
        $start = new DateTime($startDate);
        $end = $endDate ? new DateTime($endDate) : new DateTime();
        
        $contractEnd = clone $start;
        $contractEnd->modify("+{$contractDuration} days");
        
        $now = new DateTime();
        if ($now < $contractEnd) {
            $daysRemaining = $now->diff($contractEnd)->days;
            return [
                'ended_on' => $contractEnd->format('Y-m-d H:i:s'),
                'age' => "Contract ends in {$daysRemaining} days",
                'is_ended' => false
            ];
        }
        
        $ageInterval = $contractEnd->diff($end);
        
        $years = $ageInterval->y;
        $months = $ageInterval->m;
        $days = $ageInterval->d;
        $hours = $ageInterval->h;
        $minutes = $ageInterval->i;
        
        if ($years > 0) {
            $ageString = $years . " year" . ($years > 1 ? "s" : "");
        } elseif ($months > 0) {
            $ageString = $months . " month" . ($months > 1 ? "s" : "");
        } elseif ($days > 0) {
            $ageString = $days . " day" . ($days > 1 ? "s" : "");
        } elseif ($hours > 0) {
            $ageString = $hours . " hour" . ($hours > 1 ? "s" : "");
        } elseif ($minutes > 0) {
            $ageString = $minutes . " minute" . ($minutes > 1 ? "s" : "");
        } else {
            $ageString = "Just ended";
        }
        
        return [
            'ended_on' => $contractEnd->format('Y-m-d H:i:s'),
            'age' => $ageString,
            'is_ended' => true
        ];
    }

    function normalizePaymentStatus($status) {
        $status = strtolower(trim($status ?? ''));
        
        $statusMap = [
            'paymentconfirmed' => 'payment-confirmed',
            'payment_confirmed' => 'payment-confirmed',
            'paymentmade' => 'payment-made',
            'payment_made' => 'payment-made',
            'payment-made' => 'payment-made',
            'payment made' => 'payment-made',
            'unpaidpayment' => 'unpaid-payment',
            'unpaid_payment' => 'unpaid-payment',
            'unpaid-payment' => 'unpaid-payment',
            'unpaid payment' => 'unpaid-payment'
        ];
        
        return $statusMap[$status] ?? $status;
    }
    // Add this after calculateUnpaidAge() or normalizePaymentStatus()
    function calculatePaymentSummaryFromHistory($history) {
        $summary = [
            'total_unpaid_revenue' => 0,
            'total_payment_made' => 0,
            'total_payment_confirmed' => 0,
            'total_cancelled_contracts' => 0,
            'unpaid_count' => 0,
            'payment_made_count' => 0,
            'payment_confirmed_count' => 0,
            'cancelled_count' => 0
        ];
        
        if (!is_array($history)) {
            return $summary;
        }
        
        foreach ($history as $record) {
            $loyalties = strtolower($record['loyalties'] ?? '');
            $serverShare = (float)($record['server_share'] ?? 0);
            
            if (in_array($loyalties, ['unpaid-payment', 'unpaid_payment', 'unpaid payment', 'unpaid'])) {
                $summary['total_unpaid_revenue'] += $serverShare;
                $summary['unpaid_count']++;
            } elseif (in_array($loyalties, ['payment-made', 'payment_made', 'payment made'])) {
                $summary['total_payment_made'] += $serverShare;
                $summary['payment_made_count']++;
            } elseif (in_array($loyalties, ['payment-confirmed', 'payment_confirmed', 'payment confirmed'])) {
                $summary['total_payment_confirmed'] += $serverShare;
                $summary['payment_confirmed_count']++;
            } elseif (in_array($loyalties, ['contract_cancelled', 'contract-cancelled', 'contract cancelled'])) {
                $summary['total_cancelled_contracts'] += $serverShare;
                $summary['cancelled_count']++;
            }
        }
        
        return $summary;
    }

    function determineUserStatus($user, $contractDuration, $minProfitForSplit) {
        $executionStartDate = $user['execution_start_date'] ?? null;
        $profitAndLoss = (float)($user['profitandloss'] ?? 0);
        $currentLoyalties = normalizePaymentStatus($user['loyalties'] ?? '');
        
        $is_execution_empty = ($executionStartDate === null || $executionStartDate === '0000-00-00');
        
        $contract_completed = false;
        $is_contract_active = false;
        $has_valid_execution = false;
        
        if (!$is_execution_empty) {
            $start = new DateTime($executionStartDate);
            $end = clone $start;
            $end->modify("+{$contractDuration} days");
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $contractDaysLeft = $today->diff($end)->format('%r%a');
            $contract_completed = ($contractDaysLeft <= 0);
            $is_contract_active = ($contractDaysLeft > 0);
            $has_valid_execution = true;
        }
        
        if ($is_execution_empty) {
            return [
                'status' => null,
                'should_show_in_revenue' => false,
                'server_share' => 0,
                'user_share' => 0,
                'expected_payment' => 0,
                'has_eligible_profit' => false,
                'reason' => 'No execution start date'
            ];
        }
        
        if ($is_contract_active) {
            return [
                'status' => $currentLoyalties,
                'should_show_in_revenue' => false,
                'server_share' => 0,
                'user_share' => 0,
                'expected_payment' => 0,
                'has_eligible_profit' => false,
                'reason' => 'Contract active'
            ];
        }
        
        if ($contract_completed && $has_valid_execution) {
            if ($profitAndLoss < 0) {
                return [
                    'status' => null,
                    'should_show_in_revenue' => false,
                    'server_share' => 0,
                    'user_share' => 0,
                    'expected_payment' => 0,
                    'has_eligible_profit' => false,
                    'reason' => 'Contract ended with loss'
                ];
            }
            
            if ($profitAndLoss <= $minProfitForSplit) {
                return [
                    'status' => null,
                    'should_show_in_revenue' => false,
                    'server_share' => 0,
                    'user_share' => 0,
                    'expected_payment' => 0,
                    'has_eligible_profit' => false,
                    'reason' => 'Profit below split threshold'
                ];
            }
            
            if ($profitAndLoss > $minProfitForSplit) {
                $serverSharePercent = (int)($GLOBALS['serverAccount']['server_share_percent'] ?? 30);
                $userSharePercent = (int)($GLOBALS['serverAccount']['user_share_percent'] ?? 70);
                $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                
                $normalizedCurrent = normalizePaymentStatus($currentLoyalties);
                $shouldShowInRevenue = true;
                
                return [
                    'status' => $normalizedCurrent ?: 'unpaid-payment',
                    'should_show_in_revenue' => $shouldShowInRevenue,
                    'server_share' => $serverShare,
                    'user_share' => $userShare,
                    'expected_payment' => $serverShare,
                    'has_eligible_profit' => true,
                    'reason' => 'Valid profit split'
                ];
            }
        }
        
        return [
            'status' => $currentLoyalties,
            'should_show_in_revenue' => false,
            'server_share' => 0,
            'user_share' => 0,
            'expected_payment' => 0,
            'has_eligible_profit' => false,
            'reason' => 'Default'
        ];
    }

    function get_list_array($str) {
        return array_filter(array_map('trim', explode(',', $str ?? '')));
    }

    function format_currency($amount) {
        return '$' . number_format($amount, 2);
    }

    // ============================================
    // SECTION 4: LOGIN & AUTHENTICATION HANDLING
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $login_id = trim($_POST['login_id'] ?? $serverAccount['admin_login_id'] ?? '');
        $password_input = $_POST['admin_confirmation_password'] ?? $_POST['password'] ?? ''; 
        
        if (isset($_POST['initial_setup']) && $initialSetupRequired) {
            if (!empty($login_id) && !empty($password_input)) {
                $password_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE {$serverAccountTable} SET admin_login_id = ?, admin_password_hash = ? WHERE id = 1");
                $upd->execute([$login_id, $password_hash]);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Setup completed successfully!</span>";
                header("Location: serveraccount.php?view=menu"); 
                exit;
            } else {
                $message = "<span style='color:red;'>❌ Both Login ID and Password are required for setup.</span>";
            }
        
        } elseif (!$initialSetupRequired && isset($_POST['password'])) {
            if (isset($serverAccount['admin_login_id']) && $login_id === $serverAccount['admin_login_id'] && password_verify($password_input, $serverAccount['admin_password_hash'] ?? '')) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Login successful!</span>";
                header("Location: serveraccount.php?view=menu");
                exit;
            } else {
                $message = "<span style='color:red;'>❌ Invalid Login ID or Password.</span>";
            }
        }
    }

    $authenticated = ($_SESSION['admin_logged_in'] ?? false) && !$initialSetupRequired;

    // ============================================
    // SECTION 5: AJAX ENDPOINTS (Live Updates & Account Management)
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && $authenticated) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'] ?? '';
        
        // 5a: Live User Data Update
        if (empty($action)) {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $liveUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($liveUser) {
                    $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                    $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                    $serverSharePercent = (int)($serverAccount['server_share_percent'] ?? 30);
                    $userSharePercent = (int)($serverAccount['user_share_percent'] ?? 70);
                    
                    $decision = determineUserStatus($liveUser, $contractDuration, $minProfitForSplit);
                    
                    $brokerBalance = (float)($liveUser['broker_balance'] ?? 0);
                    $profitAndLoss = (float)($liveUser['profitandloss'] ?? 0);
                    $currentBalance = $brokerBalance + $profitAndLoss;
                    
                    $serverShare = $decision['server_share'];
                    $userShare = $decision['user_share'];
                    $expectedPayment = $decision['expected_payment'];
                    $hasEligibleProfit = $decision['has_eligible_profit'];
                    $displayStatus = $decision['status'];
                    
                    $unpaidAge = ['ended_on' => null, 'age' => null, 'is_ended' => false];
                    if ($hasEligibleProfit && !empty($liveUser['execution_start_date']) && $contractDuration > 0) {
                        $unpaidAge = calculateUnpaidAge($liveUser['execution_start_date'], $contractDuration);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'broker_balance' => number_format($brokerBalance, 2),
                        'profit_loss' => number_format($profitAndLoss, 2),
                        'current_balance' => number_format($currentBalance, 2),
                        'profit_loss_class' => $profitAndLoss >= 0 ? 'profit' : 'loss',
                        'current_balance_class' => $currentBalance >= 0 ? 'profit' : 'loss',
                        'server_share' => $hasEligibleProfit ? number_format($serverShare, 2) : '-',
                        'user_share' => $hasEligibleProfit ? number_format($userShare, 2) : '-',
                        'expected_payment' => $hasEligibleProfit ? number_format($expectedPayment, 2) : '-',
                        'display_status' => $displayStatus ?: '-',
                        'should_show_in_revenue' => $decision['should_show_in_revenue'],
                        'unpaid_age_ended_on' => $unpaidAge['ended_on'],
                        'unpaid_age' => $unpaidAge['age'],
                        'unpaid_is_ended' => $unpaidAge['is_ended'],
                        'loyalties' => $liveUser['loyalties'] ?? null
                    ]);
                } else {
                    echo json_encode(['error' => 'User not found']);
                }
            } else {
                echo json_encode(['error' => 'Invalid request']);
            }
            exit;
        }
        // 5aa: Get Active Investors (users with active contracts)
        if ($action === 'get_active_investors') {
            try {
                $users = array();
                $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $today = date('Y-m-d');
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, execution_start_date, profitandloss, '{$insidersServerTable}' as source, ? as contract_duration
                            FROM {$insidersServerTable} 
                            WHERE execution_start_date IS NOT NULL 
                            AND execution_start_date != '0000-00-00'
                            AND execution_start_date <= ?
                            ORDER BY id DESC
                        ");
                        $stmt1->execute([$contractDuration, $today]);
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Filter for active contracts
                        foreach ($results as $user) {
                            if (!empty($user['execution_start_date'])) {
                                $start = new DateTime($user['execution_start_date']);
                                $end = clone $start;
                                $end->modify("+{$contractDuration} days");
                                $todayDT = new DateTime();
                                if ($todayDT <= $end) {
                                    $users[] = $user;
                                }
                            }
                        }
                    }
                } catch (Exception $e) { }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, execution_start_date, profitandloss, '{$insidersTable}' as source, ? as contract_duration
                            FROM {$insidersTable} 
                            WHERE execution_start_date IS NOT NULL 
                            AND execution_start_date != '0000-00-00'
                            AND execution_start_date <= ?
                            ORDER BY id DESC
                        ");
                        $stmt2->execute([$contractDuration, $today]);
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            if (!empty($user['execution_start_date'])) {
                                $start = new DateTime($user['execution_start_date']);
                                $end = clone $start;
                                $end->modify("+{$contractDuration} days");
                                $todayDT = new DateTime();
                                if ($todayDT <= $end) {
                                    $users[] = $user;
                                }
                            }
                        }
                    }
                } catch (Exception $e) { }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5ab: Get Completed Investors with enhanced data
        if ($action === 'get_completed_investors') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkColumn1 = $pdo->query("SHOW COLUMNS FROM {$insidersServerTable} LIKE 'revenue_history'");
                    if ($checkColumn1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, revenue_history, loyalties, '{$insidersServerTable}' as source
                            FROM {$insidersServerTable} 
                            WHERE revenue_history IS NOT NULL 
                            AND revenue_history != ''
                            AND revenue_history != '[]'
                            ORDER BY id DESC
                        ");
                        $stmt1->execute();
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            $history = json_decode($user['revenue_history'], true);
                            $user['history_count'] = is_array($history) ? count($history) : 0;
                            $user['current_loyalties'] = $user['loyalties'] ?? null;
                            
                            // Calculate payment summaries from history
                            $paymentSummary = calculatePaymentSummaryFromHistory($history);
                            $user['payment_summary'] = $paymentSummary;
                            
                            $users[] = $user;
                        }
                    }
                } catch (Exception $e) { }
                
                // Get from insiders table
                try {
                    $checkColumn2 = $pdo->query("SHOW COLUMNS FROM {$insidersTable} LIKE 'revenue_history'");
                    if ($checkColumn2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, revenue_history, loyalties, '{$insidersTable}' as source
                            FROM {$insidersTable} 
                            WHERE revenue_history IS NOT NULL 
                            AND revenue_history != ''
                            AND revenue_history != '[]'
                            ORDER BY id DESC
                        ");
                        $stmt2->execute();
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            $history = json_decode($user['revenue_history'], true);
                            $user['history_count'] = is_array($history) ? count($history) : 0;
                            $user['current_loyalties'] = $user['loyalties'] ?? null;
                            
                            // Calculate payment summaries from history
                            $paymentSummary = calculatePaymentSummaryFromHistory($history);
                            $user['payment_summary'] = $paymentSummary;
                            
                            $users[] = $user;
                        }
                    }
                } catch (Exception $e) { }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5ac: Get Revenue History for a specific user
        if ($action === 'get_revenue_history') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                // Check if revenue_history column exists
                $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'revenue_history'");
                if ($checkColumn->rowCount() == 0) {
                    echo json_encode(['success' => true, 'history' => []]);
                    exit;
                }
                
                $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $history = [];
                if ($result && !empty($result['revenue_history'])) {
                    $history = json_decode($result['revenue_history'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $history = [];
                    }
                }
                
                echo json_encode(['success' => true, 'history' => $history]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        
        // 5b: Get User Account Management Data
        if ($action === 'get_account_management') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                $stmt = $pdo->prepare("SELECT accountmanagement FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $accountManagement = $result['accountmanagement'] ?? null;
                $data = null;
                
                if (!empty($accountManagement)) {
                    $data = json_decode($accountManagement, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $data = null;
                    }
                }
                
                echo json_encode(['success' => true, 'data' => $data ?: new stdClass()]);
            } else {
                echo json_encode(['error' => 'Invalid user ID or source table']);
            }
            exit;
        }
        
        // 5c: Get Server Account Management Data
        if ($action === 'get_server_account_management') {
            $stmt = $pdo->prepare("SELECT accountmanagement FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $accountManagement = $result['accountmanagement'] ?? null;
            $data = null;
            
            if (!empty($accountManagement)) {
                $data = json_decode($accountManagement, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $data = null;
                }
            }
            
            echo json_encode(['success' => true, 'data' => $data ?: new stdClass()]);
            exit;
        }
        
        // 5d: Get All Users for Management List
        if ($action === 'get_all_users_for_management') {
            $users = [];
            
            // Check if unauthorized_actions column exists before selecting it
            $checkColumn1 = $pdo->query("SHOW COLUMNS FROM {$insidersTable} LIKE 'unauthorized_actions'");
            $hasUnauthorizedColumn1 = $checkColumn1->rowCount() > 0;
            
            $checkColumn2 = $pdo->query("SHOW COLUMNS FROM {$insidersServerTable} LIKE 'unauthorized_actions'");
            $hasUnauthorizedColumn2 = $checkColumn2->rowCount() > 0;
            
            if ($hasUnauthorizedColumn1) {
                $stmt1 = $pdo->prepare("SELECT id, fullname, email, application_status, unauthorized_actions, '{$insidersTable}' as source FROM {$insidersTable} ORDER BY id DESC");
            } else {
                $stmt1 = $pdo->prepare("SELECT id, fullname, email, application_status, '' as unauthorized_actions, '{$insidersTable}' as source FROM {$insidersTable} ORDER BY id DESC");
            }
            $stmt1->execute();
            $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
            
            if ($hasUnauthorizedColumn2) {
                $stmt2 = $pdo->prepare("SELECT id, fullname, email, application_status, unauthorized_actions, '{$insidersServerTable}' as source FROM {$insidersServerTable} ORDER BY id DESC");
            } else {
                $stmt2 = $pdo->prepare("SELECT id, fullname, email, application_status, '' as unauthorized_actions, '{$insidersServerTable}' as source FROM {$insidersServerTable} ORDER BY id DESC");
            }
            $stmt2->execute();
            $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
            
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
        }
        
        // 5f: Update JSON Value
        if ($action === 'update_json_value') {
            $target_type = $_POST['target_type'] ?? '';
            $path = $_POST['path'] ?? '';
            $value = json_decode($_POST['value'] ?? 'null', true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'Invalid JSON value']);
                exit;
            }
            
            $currentData = null;
            $updateTable = null;
            $updateId = null;
            
            if ($target_type === 'user') {
                $user_id = $_POST['user_id'] ?? '';
                $source_table = $_POST['source_table'] ?? '';
                
                // IMPORTANT: First verify the user exists
                if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                    echo json_encode(['error' => 'Invalid user selection']);
                    exit;
                }
                
                $updateTable = $source_table;
                $updateId = $user_id;
                
                // Check if user exists
                $checkUser = $pdo->prepare("SELECT id FROM {$updateTable} WHERE id = ?");
                $checkUser->execute([$updateId]);
                if ($checkUser->rowCount() === 0) {
                    echo json_encode(['error' => 'User does not exist']);
                    exit;
                }
                
                // User exists - get current configuration (even if empty or invalid)
                $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Allow editing even if current data is empty, null, or invalid JSON
                if (!empty($result['accountmanagement'])) {
                    $currentData = json_decode($result['accountmanagement'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        // If existing JSON is invalid, start fresh
                        $currentData = [];
                    }
                } else {
                    // Empty or null - start with empty array
                    $currentData = [];
                }
                
            } elseif ($target_type === 'server') {
                $updateTable = $serverAccountTable;
                $updateId = 1;
                $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentData = [];
                }
            } else {
                echo json_encode(['error' => 'Invalid target type']);
                exit;
            }
            
            if ($currentData === null) {
                $currentData = [];
            }
            
            $target = &$currentData;
            $parts = [];
            
            if (!empty($path)) {
                preg_match_all('/(?:\["([^"]+)"\]|\[(\d+)\]|\.([^.\[\]]+))/', $path, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (isset($match[1]) && $match[1] !== '') {
                        $parts[] = $match[1];
                    } elseif (isset($match[2]) && $match[2] !== '') {
                        $parts[] = (int)$match[2];
                    } elseif (isset($match[3]) && $match[3] !== '') {
                        $parts[] = $match[3];
                    }
                }
            }
            
            if (count($parts) === 0) {
                $currentData = $value;
            } else {
                $lastPart = array_pop($parts);
                foreach ($parts as $part) {
                    if (!isset($target[$part])) {
                        $target[$part] = [];
                    }
                    $target = &$target[$part];
                }
                $target[$lastPart] = $value;
            }
            
            $jsonData = json_encode($currentData, JSON_PRETTY_PRINT);
            if ($updateTable && $updateId) {
                $stmt = $pdo->prepare("UPDATE {$updateTable} SET accountmanagement = ? WHERE id = ?");
                $stmt->execute([$jsonData, $updateId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid update target']);
            }
            exit;
        }
        
        // 5g: Delete JSON Value
        if ($action === 'delete_json_value') {
            $target_type = $_POST['target_type'] ?? '';
            $path = $_POST['path'] ?? '';
            
            $currentData = null;
            $updateTable = null;
            $updateId = null;
            
            if ($target_type === 'user') {
                $user_id = $_POST['user_id'] ?? '';
                $source_table = $_POST['source_table'] ?? '';
                if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                    $updateTable = $source_table;
                    $updateId = $user_id;
                    $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                    $stmt->execute([$updateId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $currentData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
                }
            } elseif ($target_type === 'server') {
                $updateTable = $serverAccountTable;
                $updateId = 1;
                $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
            } else {
                echo json_encode(['error' => 'Invalid target type']);
                exit;
            }
            
            if ($currentData === null) {
                $currentData = [];
            }
            
            $target = &$currentData;
            $parts = [];
            
            if (!empty($path)) {
                preg_match_all('/(?:\["([^"]+)"\]|\[(\d+)\]|\.([^.\[\]]+))/', $path, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    if (isset($match[1]) && $match[1] !== '') {
                        $parts[] = $match[1];
                    } elseif (isset($match[2]) && $match[2] !== '') {
                        $parts[] = (int)$match[2];
                    } elseif (isset($match[3]) && $match[3] !== '') {
                        $parts[] = $match[3];
                    }
                }
            }
            
            if (count($parts) > 0) {
                $lastPart = array_pop($parts);
                foreach ($parts as $part) {
                    if (!isset($target[$part])) {
                        $target[$part] = [];
                    }
                    $target = &$target[$part];
                }
                unset($target[$lastPart]);
            } else {
                $currentData = null;
            }
            
            $jsonData = json_encode($currentData, JSON_PRETTY_PRINT);
            if ($updateTable && $updateId) {
                $stmt = $pdo->prepare("UPDATE {$updateTable} SET accountmanagement = ? WHERE id = ?");
                $stmt->execute([$jsonData, $updateId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid update target']);
            }
            exit;
        }
        // 5h: NEW - Advanced Update JSON Value (Supports key editing)
        if ($action === 'update_json_value_advanced') {
            $target_type = $_POST['target_type'] ?? '';
            $path = $_POST['path'] ?? '';
            $edit_type = $_POST['edit_type'] ?? 'value';
            $original_key = $_POST['original_key'] ?? '';
            $new_key = $_POST['new_key'] ?? '';
            $value = json_decode($_POST['value'] ?? 'null', true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'Invalid JSON value']);
                exit;
            }
            
            $currentData = null;
            $updateTable = null;
            $updateId = null;
            
            if ($target_type === 'user') {
                $user_id = $_POST['user_id'] ?? '';
                $source_table = $_POST['source_table'] ?? '';
                if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                    $updateTable = $source_table;
                    $updateId = $user_id;
                    $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                    $stmt->execute([$updateId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $currentData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
                }
            } elseif ($target_type === 'server') {
                $updateTable = $serverAccountTable;
                $updateId = 1;
                $stmt = $pdo->prepare("SELECT accountmanagement FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $currentData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
            } else {
                echo json_encode(['error' => 'Invalid target type']);
                exit;
            }
            
            if ($currentData === null) {
                $currentData = [];
            }
            
            // Parse path to navigate to the target
            $target = &$currentData;
            $parts = [];
            
            if (!empty($path)) {
                // Parse path like "settings.enable_auto_trading" or "grid_prices_setup.grid_levels"
                $pathParts = explode('.', $path);
                foreach ($pathParts as $part) {
                    $parts[] = $part;
                }
            }
            
            // Navigate to parent of target
            if (count($parts) > 0) {
                $lastPart = array_pop($parts);
                foreach ($parts as $part) {
                    if (!isset($target[$part])) {
                        $target[$part] = [];
                    }
                    $target = &$target[$part];
                }
                
                // Handle different edit types
                if ($edit_type === 'key') {
                    // Only change the key name, keep the same value
                    if (isset($target[$original_key])) {
                        $tempValue = $target[$original_key];
                        unset($target[$original_key]);
                        $target[$new_key] = $tempValue;
                    } else {
                        echo json_encode(['error' => 'Original key not found']);
                        exit;
                    }
                } elseif ($edit_type === 'value') {
                    // Only change the value, keep the same key
                    if (isset($target[$lastPart])) {
                        $target[$lastPart] = $value;
                    } else {
                        echo json_encode(['error' => 'Target path not found']);
                        exit;
                    }
                } elseif ($edit_type === 'both') {
                    // Change both key and value
                    if (isset($target[$original_key])) {
                        unset($target[$original_key]);
                        $target[$new_key] = $value;
                    } else {
                        echo json_encode(['error' => 'Original key not found']);
                        exit;
                    }
                }
            } else {
                // Root level update
                if ($edit_type === 'key' || $edit_type === 'both') {
                    // For root level, we can't rename without knowing structure
                    echo json_encode(['error' => 'Cannot rename root level keys']);
                    exit;
                } else {
                    $currentData = $value;
                }
            }
            
            $jsonData = json_encode($currentData, JSON_PRETTY_PRINT);
            if ($updateTable && $updateId) {
                $stmt = $pdo->prepare("UPDATE {$updateTable} SET accountmanagement = ? WHERE id = ?");
                $stmt->execute([$jsonData, $updateId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Invalid update target']);
            }
            exit;
        }
        // 5i: Get Users with INVESTED_WITH field
        if ($action === 'get_users_invested_with') {
            try {
                $users = [];
                
                // Function to check if column exists in table
                function columnExists($pdo, $table, $column) {
                    try {
                        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
                        $stmt->execute([$column]);
                        return $stmt->rowCount() > 0;
                    } catch (Exception $e) {
                        return false;
                    }
                }
                
                // Check and get from insiders_server table
                if (columnExists($pdo, $insidersServerTable, 'invested_with')) {
                    $stmt1 = $pdo->prepare("SELECT id, fullname, email, invested_with, '{$insidersServerTable}' as source FROM {$insidersServerTable} ORDER BY id DESC");
                    $stmt1->execute();
                    $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                } else {
                    // Column doesn't exist, select without it and add null value
                    $stmt1 = $pdo->prepare("SELECT id, fullname, email, '{$insidersServerTable}' as source FROM {$insidersServerTable} ORDER BY id DESC");
                    $stmt1->execute();
                    $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as &$row) {
                        $row['invested_with'] = null;
                    }
                    $users = array_merge($users, $results);
                }
                
                // Check and get from insiders table
                if (columnExists($pdo, $insidersTable, 'invested_with')) {
                    $stmt2 = $pdo->prepare("SELECT id, fullname, email, invested_with, '{$insidersTable}' as source FROM {$insidersTable} ORDER BY id DESC");
                    $stmt2->execute();
                    $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                } else {
                    // Column doesn't exist, select without it and add null value
                    $stmt2 = $pdo->prepare("SELECT id, fullname, email, '{$insidersTable}' as source FROM {$insidersTable} ORDER BY id DESC");
                    $stmt2->execute();
                    $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as &$row) {
                        $row['invested_with'] = null;
                    }
                    $users = array_merge($users, $results);
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5j: Update INVESTED_WITH for a user
        if ($action === 'update_invested_with') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $invested_with = trim($_POST['invested_with'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials (same as before)
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            // Check if user exists
            $checkUser = $pdo->prepare("SELECT id FROM {$source_table} WHERE id = ?");
            $checkUser->execute([$user_id]);
            if ($checkUser->rowCount() === 0) {
                echo json_encode(['error' => 'User does not exist']);
                exit;
            }
            
            // Check if invested_with column exists, if not add it
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'invested_with'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN invested_with VARCHAR(100) DEFAULT NULL");
            }
            
            // Update the invested_with field
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET invested_with = ? WHERE id = ?");
            $updateStmt->execute([$invested_with, $user_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        // 5k: Get Execution History
        if ($action === 'get_execution_history') {
            try {
                $history = [];
                
                // Only fetch from insiders table (where column exists)
                $stmt = $pdo->prepare("SELECT id, executions_notification FROM {$insidersTable} WHERE executions_notification IS NOT NULL AND executions_notification != ''");
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $notifications = json_decode($row['executions_notification'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($notifications)) {
                        foreach ($notifications as $key => $notification) {
                            $history[$key] = [
                                'message' => $notification['message'] ?? '',
                                'time' => $notification['time'] ?? '',
                                'type' => $notification['type'] ?? 'info',
                                'update' => $notification['update'] ?? 'none',
                                'section' => $notification['section'] ?? ''
                            ];
                        }
                    }
                }
                
                // Sort by key descending (newest first) - assuming higher key number is newer
                krsort($history);
                
                echo json_encode(['success' => true, 'history' => $history]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5k2: Get User Execution History (specific user)
        if ($action === 'get_user_execution_history') {
            try {
                $user_id = $_POST['user_id'] ?? '';
                $source_table = $_POST['source_table'] ?? '';
                
                if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                    echo json_encode(['error' => 'Invalid user selection']);
                    exit;
                }
                
                $history = [];
                
                // Fetch from the specific user's table
                $stmt = $pdo->prepare("SELECT executions_notification FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && !empty($result['executions_notification'])) {
                    $notifications = json_decode($result['executions_notification'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($notifications)) {
                        foreach ($notifications as $key => $notification) {
                            $history[$key] = [
                                'message' => $notification['message'] ?? '',
                                'time' => $notification['time'] ?? '',
                                'type' => $notification['type'] ?? 'info',
                                'update' => $notification['update'] ?? 'none',
                                'section' => $notification['section'] ?? ''
                            ];
                        }
                    }
                }
                
                // Sort by key descending (newest first)
                krsort($history);
                
                echo json_encode(['success' => true, 'history' => $history]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5l: Get User Setting (for auto trading)
        if ($action === 'get_user_setting') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $column_name = $_POST['column_name'] ?? '';
            
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid request']);
                exit;
            }
            
            $allowed_columns = ['enable_autotrading', 'bypass_restriction'];
            if (!in_array($column_name, $allowed_columns)) {
                echo json_encode(['error' => 'Invalid column name']);
                exit;
            }
            
            // Check if column exists, if not add it
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE '{$column_name}'");
            if ($checkColumn->rowCount() == 0) {
                $defaultValue = ($column_name === 'enable_autotrading') ? 1 : 0;
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN {$column_name} TINYINT(1) DEFAULT {$defaultValue}");
            }
            
            $stmt = $pdo->prepare("SELECT {$column_name} FROM {$source_table} WHERE id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo json_encode(['success' => true, 'value' => (int)$result[$column_name]]);
            } else {
                echo json_encode(['success' => false, 'error' => 'User not found']);
            }
            exit;
        }

        // 5m: Update User Setting (single)
        if ($action === 'update_user_setting') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $column_name = $_POST['column_name'] ?? '';
            $value = (int)$_POST['value'] ?? 0;
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            $allowed_columns = ['enable_autotrading', 'bypass_restriction'];
            if (!in_array($column_name, $allowed_columns)) {
                echo json_encode(['error' => 'Invalid column name']);
                exit;
            }
            
            // Check if column exists, if not add it
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE '{$column_name}'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN {$column_name} TINYINT(1) DEFAULT 0");
            }
            
            // Update the setting
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET {$column_name} = ? WHERE id = ?");
            $updateStmt->execute([$value, $user_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }

        // 5n: Update User Settings (batch)
        if ($action === 'update_user_settings_batch') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $enable_autotrading = (int)$_POST['enable_autotrading'] ?? 1;
            $bypass_restriction = (int)$_POST['bypass_restriction'] ?? 0;
            $demo_account = (int)$_POST['demo_account'] ?? 0;
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            // Check and add columns if needed
            $checkAutoTrading = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'enable_autotrading'");
            if ($checkAutoTrading->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN enable_autotrading TINYINT(1) DEFAULT 1");
            }
            
            $checkBypass = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'bypass_restriction'");
            if ($checkBypass->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN bypass_restriction TINYINT(1) DEFAULT 0");
            }
            
            $checkDemoAccount = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'demo_account'");
            if ($checkDemoAccount->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN demo_account TINYINT(1) DEFAULT 0");
            }
            
            // Update settings
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET enable_autotrading = ?, bypass_restriction = ?, demo_account = ? WHERE id = ?");
            $updateStmt->execute([$enable_autotrading, $bypass_restriction, $demo_account, $user_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        // 5o: Get Verified Users
        if ($action === 'get_verified_users') {
            try {
                $minBrokerBalance = (float)($serverAccount['min_broker_balance'] ?? 30.00);
                $users = array();
                
                // Get from insiders_server table if table exists
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $checkColumn1 = $pdo->query("SHOW COLUMNS FROM {$insidersServerTable} LIKE 'invested_with'");
                        if ($checkColumn1->rowCount() > 0) {
                            $stmt1 = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                                FROM {$insidersServerTable} 
                                WHERE invested_with IS NOT NULL 
                                AND invested_with != '' 
                                AND execution_start_date IS NOT NULL 
                                AND execution_start_date != '0000-00-00'
                                AND enable_autotrading = 1
                                AND broker_balance >= ?
                            ");
                            $stmt1->execute([$minBrokerBalance]);
                            $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                        }
                    }
                } catch (Exception $e) {
                    // Table or column doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $checkColumn2 = $pdo->query("SHOW COLUMNS FROM {$insidersTable} LIKE 'invested_with'");
                        if ($checkColumn2->rowCount() > 0) {
                            $stmt2 = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                                FROM {$insidersTable} 
                                WHERE invested_with IS NOT NULL 
                                AND invested_with != '' 
                                AND execution_start_date IS NOT NULL 
                                AND execution_start_date != '0000-00-00'
                                AND enable_autotrading = 1
                                AND broker_balance >= ?
                            ");
                            $stmt2->execute([$minBrokerBalance]);
                            $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                        }
                    }
                } catch (Exception $e) {
                    // Table or column doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5p: Get Pending Users
        if ($action === 'get_pending_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status = 'pending'
                        ");
                        $stmt1->execute();
                        $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status = 'pending'
                        ");
                        $stmt2->execute();
                        $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5q: Get Suspended Users
        if ($action === 'get_suspended_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status IN ('suspended', 'blacklisted')
                        ");
                        $stmt1->execute();
                        $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status IN ('suspended', 'blacklisted')
                        ");
                        $stmt2->execute();
                        $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5r: Get Just Joined Users
        if ($action === 'get_just_joined_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status = 'just-joined'
                        ");
                        $stmt1->execute();
                        $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status = 'just-joined'
                        ");
                        $stmt2->execute();
                        $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5s: Get Just Joined & Valid Credentials Users
        if ($action === 'get_just_joined_valid_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status = 'just-joined-and-valid_credentials'
                        ");
                        $stmt1->execute();
                        $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status = 'just-joined-and-valid_credentials'
                        ");
                        $stmt2->execute();
                        $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5t: Get Approved Users
        if ($action === 'get_approved_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status = 'approved'
                        ");
                        $stmt1->execute();
                        $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status = 'approved'
                        ");
                        $stmt2->execute();
                        $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                    }
                } catch (Exception $e) {
                    // Table doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5u: Update Application Status (batch)
        if ($action === 'update_application_status_batch') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $application_status = trim($_POST['application_status'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            $allowed_statuses = ['approved', 'declined', 'pending', 'suspended', 'blacklisted'];
            if (!in_array($application_status, $allowed_statuses)) {
                echo json_encode(['error' => 'Invalid status value']);
                exit;
            }
            
            // Check if application_status column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'application_status'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN application_status VARCHAR(255) DEFAULT NULL");
            }
            
            // Update the status
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET application_status = ? WHERE id = ?");
            $updateStmt->execute([$application_status, $user_id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
        // 5v: Get Bypassed Users (bypass_restriction = 1)
        if ($action === 'get_bypassed_users') {
            try {
                $users = array();
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $checkColumn1 = $pdo->query("SHOW COLUMNS FROM {$insidersServerTable} LIKE 'bypass_restriction'");
                        if ($checkColumn1->rowCount() > 0) {
                            $stmt1 = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, bypass_restriction, broker_balance, account_mode, demo_account, unauthorized_actions, '{$insidersServerTable}' as source 
                                FROM {$insidersServerTable} 
                                WHERE bypass_restriction = 1
                            ");
                            $stmt1->execute();
                            $users = array_merge($users, $stmt1->fetchAll(PDO::FETCH_ASSOC));
                        }
                    }
                } catch (Exception $e) {
                    // Table or column doesn't exist, skip
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $checkColumn2 = $pdo->query("SHOW COLUMNS FROM {$insidersTable} LIKE 'bypass_restriction'");
                        if ($checkColumn2->rowCount() > 0) {
                            $stmt2 = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, bypass_restriction, broker_balance, account_mode, demo_account, unauthorized_actions, '{$insidersTable}' as source 
                                FROM {$insidersTable} 
                                WHERE bypass_restriction = 1
                            ");
                            $stmt2->execute();
                            $users = array_merge($users, $stmt2->fetchAll(PDO::FETCH_ASSOC));
                        }
                    }
                } catch (Exception $e) {
                    // Table or column doesn't exist, skip
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5w: Update specific configuration entry (for accountmanagement_configs)
        if ($action === 'update_config_entry') {
            $target_type = $_POST['target_type'] ?? '';
            $entry_key = $_POST['entry_key'] ?? '';
            $value = json_decode($_POST['value'] ?? 'null', true);
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'Invalid JSON value']);
                exit;
            }
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            if ($target_type === 'server') {
                $updateTable = $serverAccountTable;
                $updateId = 1;
                
                // Get current data
                $stmt = $pdo->prepare("SELECT accountmanagement_configs FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $currentData = !empty($result['accountmanagement_configs']) ? json_decode($result['accountmanagement_configs'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentData = [];
                }
                
                // Update the specific entry
                if ($value === null) {
                    // Delete the entry
                    unset($currentData[$entry_key]);
                } else {
                    // Update or add the entry
                    $currentData[$entry_key] = $value;
                }
                
                $jsonData = json_encode($currentData, JSON_PRETTY_PRINT);
                $stmt = $pdo->prepare("UPDATE {$updateTable} SET accountmanagement_configs = ? WHERE id = ?");
                $stmt->execute([$jsonData, $updateId]);
                
                echo json_encode(['success' => true, 'data' => $currentData]);
            } else {
                echo json_encode(['error' => 'Invalid target type']);
            }
            exit;
        }

        // 5x: Get specific configuration entry
        if ($action === 'get_config_entry') {
            $target_type = $_POST['target_type'] ?? '';
            $entry_key = $_POST['entry_key'] ?? '';
            
            if ($target_type === 'server') {
                $stmt = $pdo->prepare("SELECT accountmanagement_configs FROM {$serverAccountTable} WHERE id = 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $currentData = !empty($result['accountmanagement_configs']) ? json_decode($result['accountmanagement_configs'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentData = [];
                }
                
                // If entry_key is 'all' or empty, return all data
                if (empty($entry_key) || $entry_key === 'all') {
                    echo json_encode(['success' => true, 'all_data' => $currentData]);
                } else {
                    $entryData = isset($currentData[$entry_key]) ? $currentData[$entry_key] : null;
                    echo json_encode(['success' => true, 'data' => $entryData, 'all_data' => $currentData]);
                }
            } else {
                echo json_encode(['error' => 'Invalid target type']);
            }
            exit;
        }
        // 5y: Cancel Contract - Update active record to contract_cancelled
        if ($action === 'cancel_contract') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash, contract_duration FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            // Get current user data before cancellation
            $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
            $stmt->execute([$user_id]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                echo json_encode(['error' => 'User not found']);
                exit;
            }
            
            // Get contract duration from server account
            $contractDuration = (int)($adminData['contract_duration'] ?? 30);
            
            // Get current revenue history
            $history = [];
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'revenue_history'");
            if ($checkColumn->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['revenue_history'])) {
                    $history = json_decode($result['revenue_history'], true);
                    if (!is_array($history)) {
                        $history = [];
                    }
                }
            }
            
            // Sort history to find the latest record
            usort($history, function($a, $b) {
                $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                return $dateB - $dateA;
            });
            
            $latestRecord = !empty($history) ? $history[0] : null;
            $latestLoyalties = strtolower($latestRecord['loyalties'] ?? '');
            
            // Check if latest record has 'active' status
            if (!$latestRecord || strpos($latestLoyalties, 'active') === false) {
                echo json_encode(['error' => 'Cannot cancel contract: No active record found or active is not the latest record']);
                exit;
            }
            
            // Update the latest active record to contract_cancelled
            $history[0]['loyalties'] = 'contract_cancelled';
            $history[0]['cancelled_at'] = date('Y-m-d H:i:s');
            $history[0]['cancelled_by'] = $login_id;
            
            // Save updated history
            $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
            
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN revenue_history LONGTEXT DEFAULT NULL");
            }
            
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
            $updateStmt->execute([$jsonHistory, $user_id]);
            
            // Set loyalties to contract_cancelled
            $updateLoyalties = $pdo->prepare("UPDATE {$source_table} SET loyalties = 'contract_cancelled' WHERE id = ?");
            $updateLoyalties->execute([$user_id]);
            
            // Calculate new execution start date that makes the contract expired
            $today = new DateTime();
            $newExecutionDate = clone $today;
            $daysToSubtract = $contractDuration + 2;
            $newExecutionDate->modify("-{$daysToSubtract} days");
            $newExecutionDateStr = $newExecutionDate->format('Y-m-d');
            
            // Update the user's execution_start_date
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET execution_start_date = ? WHERE id = ?");
            $updateStmt->execute([$newExecutionDateStr, $user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => "Contract cancelled successfully. Active record updated to contract_cancelled.",
                'new_execution_date' => $newExecutionDateStr
            ]);
            exit;
        }
        // 5z: Get User Analytics Data
        if ($action === 'get_user_analytics') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                try {
                    // Debug log to see what's happening (optional, remove in production)
                    error_log("Fetching analytics for user_id: $user_id, table: $source_table");
                    
                    // First, let's check what columns exist in the table
                    $columns = $pdo->query("SHOW COLUMNS FROM {$source_table}")->fetchAll(PDO::FETCH_COLUMN);
                    error_log("Available columns: " . implode(', ', $columns));
                    
                    // Check if analytics column exists (your column is named 'analytics' from the table structure)
                    if (!in_array('analytics', $columns)) {
                        // If not, check for 'analytics_history' as fallback
                        if (in_array('analytics_history', $columns)) {
                            $stmt = $pdo->prepare("SELECT analytics_history as analytics FROM {$source_table} WHERE id = ?");
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Analytics column not found in table']);
                            exit;
                        }
                    } else {
                        $stmt = $pdo->prepare("SELECT analytics FROM {$source_table} WHERE id = ?");
                    }
                    
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result) {
                        $analytics = $result['analytics'] ?? null;
                        error_log("Raw analytics data length: " . strlen($analytics ?? 'null'));
                        
                        // Return the data as-is - don't try to parse it here
                        echo json_encode(['success' => true, 'analytics' => $analytics]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'User not found']);
                    }
                } catch (Exception $e) {
                    error_log("Error in get_user_analytics: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid user selection']);
            }
            exit;
        }
        // Add this AJAX endpoint to get investor details including revenue history (add to SECTION 5)
        if ($action === 'get_investor_details') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                    $brokerBalance = (float)($user['broker_balance'] ?? 0);
                    $profitAndLoss = (float)($user['profitandloss'] ?? 0);
                    $currentBalance = $brokerBalance + $profitAndLoss;
                    
                    // Get revenue history to check latest record
                    $history = [];
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'revenue_history'");
                    if ($checkColumn->rowCount() > 0) {
                        $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result && !empty($result['revenue_history'])) {
                            $history = json_decode($result['revenue_history'], true);
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'user' => [
                            'fullname' => $user['fullname'],
                            'email' => $user['email'],
                            'execution_start_date' => $user['execution_start_date'],
                            'contract_duration' => $contractDuration,
                            'profitandloss' => $profitAndLoss,
                            'current_balance' => $currentBalance,
                            'broker_balance' => $brokerBalance,
                            'server_share' => 0,
                            'user_share' => 0,
                            'revenue_history' => $history
                        ]
                    ]);
                } else {
                    echo json_encode(['error' => 'User not found']);
                }
            } else {
                echo json_encode(['error' => 'Invalid request']);
            }
            exit;
        }
        // 5z2: Update revenue history record status (for payment-made -> payment-confirmed)
        if ($action === 'update_revenue_status') {
            $record_id = $_POST['record_id'] ?? '';
            $new_status = $_POST['new_status'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                // Get current revenue history
                $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result || empty($result['revenue_history'])) {
                    echo json_encode(['error' => 'No revenue history found']);
                    exit;
                }
                
                $history = json_decode($result['revenue_history'], true);
                if (!is_array($history)) {
                    echo json_encode(['error' => 'Invalid revenue history format']);
                    exit;
                }
                
                // FIRST: Sort history by recorded_at DESCENDING (newest FIRST)
                usort($history, function($a, $b) {
                    $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                    $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                    return $dateB - $dateA; // Descending - newest first
                });
                
                $updated = false;
                $latestPaymentMadeIndex = -1;
                
                // Find the FIRST (newest) record with 'payment-made' in loyalties
                for ($i = 0; $i < count($history); $i++) {
                    $loyalties = strtolower($history[$i]['loyalties'] ?? '');
                    if (strpos($loyalties, 'payment-made') !== false) {
                        $latestPaymentMadeIndex = $i;
                        break; // Stop at the first one (which is the newest after sorting)
                    }
                }
                
                // If found a payment-made record, update it
                if ($latestPaymentMadeIndex !== -1) {
                    // Update the latest payment-made record
                    $history[$latestPaymentMadeIndex]['loyalties'] = 'payment-confirmed';
                    $updated = true;
                } else {
                    echo json_encode(['error' => 'No payment-made record found to update']);
                    exit;
                }
                
                if ($updated) {
                    // Save updated history (maintain original order or resort by recorded_at)
                    usort($history, function($a, $b) {
                        $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                        $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                        return $dateA - $dateB; // Ascending for storage
                    });
                    
                    $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
                    $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
                    $updateStmt->execute([$jsonHistory, $user_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Revenue history updated successfully']);
                } else {
                    echo json_encode(['error' => 'No changes made']);
                }
                
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        // 5z3: Update user's current loyalties status (for current revenue table)
        if ($action === 'update_user_loyalties') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $new_status = $_POST['new_status'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            // Validate input
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            $allowed_statuses = ['payment-confirmed', 'payment-made', 'unpaid-payment', 'failed-payment'];
            if (!in_array($new_status, $allowed_statuses)) {
                echo json_encode(['error' => 'Invalid status value']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        // 5z4: Check and create active contract record in revenue history
        if ($action === 'ensure_active_contract_record') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                // Get user data
                $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userData) {
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                // Check if contract is active
                $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $executionStartDate = $userData['execution_start_date'] ?? null;
                
                if (empty($executionStartDate) || $executionStartDate === '0000-00-00') {
                    echo json_encode(['error' => 'No execution start date set']);
                    exit;
                }
                
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                
                // Check if contract is still active
                if ($today > $end) {
                    echo json_encode(['error' => 'Contract is not active anymore']);
                    exit;
                }
                
                // Get current revenue history
                $history = [];
                $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'revenue_history'");
                if ($checkColumn->rowCount() > 0) {
                    $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['revenue_history'])) {
                        $history = json_decode($result['revenue_history'], true);
                        if (!is_array($history)) {
                            $history = [];
                        }
                    }
                }
                
                // Sort history by recorded_at or id to find the latest
                usort($history, function($a, $b) {
                    $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                    $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                    return $dateB - $dateA;
                });
                
                $latestRecord = !empty($history) ? $history[0] : null;
                $latestLoyalties = strtolower($latestRecord['loyalties'] ?? '');
                
                // Check if latest record is already 'active'
                if ($latestRecord && strpos($latestLoyalties, 'active') !== false) {
                    echo json_encode(['success' => true, 'message' => 'Active record already exists as latest', 'already_active' => true]);
                    exit;
                }
                
                // Create new active record
                $brokerBalance = (float)($userData['broker_balance'] ?? 0);
                $profitAndLoss = (float)($userData['profitandloss'] ?? 0);
                $currentBalance = $brokerBalance + $profitAndLoss;
                
                $serverSharePercent = (int)($serverAccount['server_share_percent'] ?? 30);
                $userSharePercent = (int)($serverAccount['user_share_percent'] ?? 70);
                $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                
                $serverShare = 0;
                $userShare = 0;
                if ($profitAndLoss > $minProfitForSplit) {
                    $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                    $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                }
                
                // Generate new ID
                $newId = 1;
                if (!empty($history)) {
                    $maxId = 0;
                    foreach ($history as $item) {
                        if (isset($item['id']) && is_numeric($item['id']) && $item['id'] > $maxId) {
                            $maxId = (int)$item['id'];
                        }
                    }
                    $newId = $maxId + 1;
                }
                
                $executionEndDate = clone $start;
                $executionEndDate->modify("+{$contractDuration} days");
                
                $activeRecord = [
                    'id' => $newId,
                    'execution_start_date' => $executionStartDate,
                    'execution_end_date' => $executionEndDate->format('Y-m-d'),
                    'starting_balance' => $brokerBalance,
                    'current_balance' => $currentBalance,
                    'profit' => $profitAndLoss,
                    'user_share' => $userShare,
                    'server_share' => $serverShare,
                    'loyalties' => 'active',
                    'recorded_at' => date('Y-m-d H:i:s')
                ];
                
                // Add to history (as latest)
                $history[] = $activeRecord;
                
                // Sort to ensure active is latest (by recorded_at)
                usort($history, function($a, $b) {
                    $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : 0;
                    $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : 0;
                    return $dateB - $dateA;
                });
                
                // Save updated history
                $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
                
                if ($checkColumn->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN revenue_history LONGTEXT DEFAULT NULL");
                }
                
                $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
                $updateStmt->execute([$jsonHistory, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Active contract record created successfully', 'record' => $activeRecord]);
                
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
        // 5z5: Get System Server IP Configuration
        if ($action === 'get_system_config') {
            try {
                $stmt = $pdo->prepare("SELECT system_server_config FROM {$serverAccountTable} WHERE id = 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $config = [];
                if ($result && !empty($result['system_server_config'])) {
                    $config = json_decode($result['system_server_config'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $config = [];
                    }
                }
                
                echo json_encode(['success' => true, 'config' => $config]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z6: Update System Server IP Configuration
        if ($action === 'update_system_config') {
            $config = json_decode($_POST['config'] ?? '{}', true);
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'Invalid JSON configuration']);
                exit;
            }
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            try {
                $jsonConfig = json_encode($config, JSON_PRETTY_PRINT);
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET system_server_config = ? WHERE id = 1");
                $stmt->execute([$jsonConfig]);
                
                echo json_encode(['success' => true, 'message' => 'Configuration updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }

        // 5z7: Search Users for IP Assignment - SEARCHES ALL USERS
        if ($action === 'search_users_for_config') {
            $search = trim($_POST['search'] ?? '');
            $exclude_ids = isset($_POST['exclude_ids']) ? json_decode($_POST['exclude_ids'], true) : [];
            
            if (strlen($search) < 1) {
                echo json_encode(['success' => true, 'users' => []]);
                exit;
            }
            
            $users = [];
            $searchTerm = '%' . $search . '%';
            
            // Search in insiders_server table - NO STATUS FILTERING, get ALL users
            try {
                $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                if ($checkTable1->rowCount() > 0) {
                    // First, check what columns exist in this table
                    $availableColumns = [];
                    $colQuery = $pdo->query("SHOW COLUMNS FROM {$insidersServerTable}");
                    while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
                        $availableColumns[] = $col['Field'];
                    }
                    
                    // Build SELECT with available columns only
                    $selectFields = "id, fullname, email, '{$insidersServerTable}' as source";
                    if (in_array('broker', $availableColumns)) $selectFields .= ", broker";
                    if (in_array('login', $availableColumns)) $selectFields .= ", login";
                    if (in_array('broker_balance', $availableColumns)) $selectFields .= ", broker_balance";
                    if (in_array('application_status', $availableColumns)) $selectFields .= ", application_status";
                    
                    $stmt1 = $pdo->prepare("
                        SELECT {$selectFields} 
                        FROM {$insidersServerTable} 
                        WHERE (fullname LIKE ? OR email LIKE ? OR id LIKE ?)
                        ORDER BY fullname ASC
                        LIMIT 50
                    ");
                    $stmt1->execute([$searchTerm, $searchTerm, $searchTerm]);
                    $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as $user) {
                        if (!in_array($user['id'], $exclude_ids)) {
                            $users[] = $user;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error searching insiders_server_table: " . $e->getMessage());
            }
            
            // Search in insiders table - NO STATUS FILTERING, get ALL users
            try {
                $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                if ($checkTable2->rowCount() > 0) {
                    // First, check what columns exist in this table
                    $availableColumns = [];
                    $colQuery = $pdo->query("SHOW COLUMNS FROM {$insidersTable}");
                    while ($col = $colQuery->fetch(PDO::FETCH_ASSOC)) {
                        $availableColumns[] = $col['Field'];
                    }
                    
                    // Build SELECT with available columns only
                    $selectFields = "id, fullname, email, '{$insidersTable}' as source";
                    if (in_array('broker', $availableColumns)) $selectFields .= ", broker";
                    if (in_array('login', $availableColumns)) $selectFields .= ", login";
                    if (in_array('broker_balance', $availableColumns)) $selectFields .= ", broker_balance";
                    if (in_array('application_status', $availableColumns)) $selectFields .= ", application_status";
                    
                    $stmt2 = $pdo->prepare("
                        SELECT {$selectFields} 
                        FROM {$insidersTable} 
                        WHERE (fullname LIKE ? OR email LIKE ? OR id LIKE ?)
                        ORDER BY fullname ASC
                        LIMIT 50
                    ");
                    $stmt2->execute([$searchTerm, $searchTerm, $searchTerm]);
                    $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as $user) {
                        if (!in_array($user['id'], $exclude_ids)) {
                            $users[] = $user;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Error searching insiders_table: " . $e->getMessage());
            }
            
            // Remove duplicates by ID (in case a user appears in both tables - unlikely but safe)
            $uniqueUsers = [];
            $seenIds = [];
            foreach ($users as $user) {
                if (!in_array($user['id'], $seenIds)) {
                    $seenIds[] = $user['id'];
                    $uniqueUsers[] = $user;
                }
            }
            
            echo json_encode(['success' => true, 'users' => $uniqueUsers]);
            exit;
        }

        // 5z8: Get User Details by IDs
        if ($action === 'get_users_by_ids') {
            $user_ids = json_decode($_POST['user_ids'] ?? '[]', true);
            $source_table = $_POST['source_table'] ?? $insidersTable;
            
            if (empty($user_ids) || !is_array($user_ids)) {
                echo json_encode(['success' => true, 'users' => []]);
                exit;
            }
            
            $users = [];
            $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
            
            try {
                $stmt = $pdo->prepare("
                    SELECT id, fullname, email, broker, login, broker_balance, profitandloss, application_status 
                    FROM {$source_table} 
                    WHERE id IN ({$placeholders})
                ");
                $stmt->execute($user_ids);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5z9: Get Manual Content
        if ($action === 'get_manual_content') {
            try {
                $stmt = $pdo->prepare("SELECT manual FROM {$serverAccountTable} WHERE id = 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $manual = [];
                if ($result && !empty($result['manual'])) {
                    $manual = json_decode($result['manual'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $manual = [];
                    }
                }
                
                echo json_encode(['success' => true, 'manual' => $manual]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z10: Update Manual Content
        if ($action === 'update_manual_content') {
            $manual = json_decode($_POST['manual'] ?? '[]', true);
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['error' => 'Invalid JSON format']);
                exit;
            }
            
            // Verify admin credentials
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            try {
                $jsonManual = json_encode($manual, JSON_PRETTY_PRINT);
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET manual = ? WHERE id = 1");
                $stmt->execute([$jsonManual]);
                
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
        }
    }

    // ============================================
    // SECTION 6: AUTHENTICATED POST HANDLING (Settings, Updates, etc.)
    // ============================================
    if ($authenticated) {
        $re_authenticated_for_action = false;
        if (isset($_POST['admin_confirmation_password'])) {
            $login_id_reauth = trim($_POST['login_id'] ?? '');
            $password_reauth = $_POST['admin_confirmation_password'];
            
            if (isset($serverAccount['admin_login_id']) && $login_id_reauth === $serverAccount['admin_login_id'] && password_verify($password_reauth, $serverAccount['admin_password_hash'] ?? '')) {
                $re_authenticated_for_action = true;
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Action failed: Invalid Admin Password confirmation. Session terminated.</span>";
                unset($_SESSION['admin_logged_in']);
                header("Location: serveraccount.php");
                exit;
            }
        }

        // 6a: Update Addresses and Settings
        if (isset($_POST['update_addresses']) && $re_authenticated_for_action) {
            try {
                $btc_address = trim($_POST['btc_address'] ?? '');
                $eth_address = trim($_POST['eth_address'] ?? '');
                $eth_network = trim($_POST['eth_network'] ?? 'ERC20');
                $usdt_address = trim($_POST['usdt_address'] ?? '');
                $usdt_network = trim($_POST['usdt_network'] ?? 'TRC20');
                
                $minimum_deposit = floatval($_POST['minimum_deposit'] ?? 0.00);
                $contract_duration = is_numeric($_POST['contract_duration'] ?? null) ? (int)$_POST['contract_duration'] : null;
                
                $server_share_percent = is_numeric($_POST['server_share_percent'] ?? null) ? (int)$_POST['server_share_percent'] : 30;
                $user_share_percent = is_numeric($_POST['user_share_percent'] ?? null) ? (int)$_POST['user_share_percent'] : 70;
                $min_profit_for_split = floatval($_POST['min_profit_for_split'] ?? 30.00);
                $min_broker_balance = floatval($_POST['min_broker_balance'] ?? 30.00);
                $minimum_contract_days = is_numeric($_POST['minimum_contract_days'] ?? null) ? (int)$_POST['minimum_contract_days'] : 5;
                $expiry_threshold_days = is_numeric($_POST['expiry_threshold_days'] ?? null) ? (int)$_POST['expiry_threshold_days'] : 5;

                $stmt = $pdo->prepare("
                    UPDATE {$serverAccountTable} SET 
                        btc_address = ?, eth_address = ?, eth_network = ?, usdt_address = ?, usdt_network = ?, 
                        minimum_deposit = ?, contract_duration = ?, server_share_percent = ?, user_share_percent = ?,
                        min_profit_for_split = ?, min_broker_balance = ?, minimum_contract_days = ?, expiry_threshold_days = ?
                    WHERE id = 1
                ");
                $stmt->execute([
                    $btc_address, $eth_address, $eth_network, $usdt_address, $usdt_network, 
                    $minimum_deposit, $contract_duration, $server_share_percent, $user_share_percent,
                    $min_profit_for_split, $min_broker_balance, $minimum_contract_days, $expiry_threshold_days
                ]);
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Payment settings updated successfully!</span>";
                header("Location: serveraccount.php?view=settings");
                exit;
            } catch (Exception $e) {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating settings: " . htmlspecialchars($e->getMessage()) . "</span>";
                header("Location: serveraccount.php?view=settings");
                exit;
            }
        }
        
        // 6b: Update Admin Credentials
        if (isset($_POST['update_credentials']) && $re_authenticated_for_action) {
            $new_login_id = trim($_POST['new_login_id'] ?? $serverAccount['admin_login_id']);
            $new_password = $_POST['new_password'] ?? '';

            try {
                $update_query = "UPDATE {$serverAccountTable} SET admin_login_id = ?";
                $params = [$new_login_id];

                if (!empty($new_password)) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query .= ", admin_password_hash = ?";
                    $params[] = $password_hash;
                }
                
                $update_query .= " WHERE id = 1";
                $stmt = $pdo->prepare($update_query);
                $stmt->execute($params);
                
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Credentials updated successfully! Please re-login with your new details.</span>";
                unset($_SESSION['admin_logged_in']);
                header("Location: serveraccount.php");
                exit;
            } catch (Exception $e) {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating credentials: " . htmlspecialchars($e->getMessage()) . "</span>";
                header("Location: serveraccount.php?view=settings");
                exit;
            }
        }

        // 6c: Add New Broker/Link
        if (isset($_POST['add_broker']) && $re_authenticated_for_action) {
            $new_broker = trim($_POST['new_broker'] ?? '');
            if (!empty($new_broker)) {
                try {
                    $current_brokers = $serverAccount['brokers'] ?? '';
                    $brokers_array = array_filter(array_map('trim', explode(',', $current_brokers)));
                    if (!in_array($new_broker, $brokers_array)) {
                        $brokers_array[] = $new_broker;
                        $updated_brokers = implode(',', $brokers_array);
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ? WHERE id = 1");
                        $stmt->execute([$updated_brokers]);
                        $_SESSION['admin_message'] = "<span style='color:green;'>✅ Broker '{$new_broker}' added successfully!</span>";
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:orange;'>⚠️ Broker '{$new_broker}' already exists.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error adding broker: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ New broker field cannot be empty.</span>";
            }
            header("Location: serveraccount.php?view=settings");
            exit;
        }

        if (isset($_POST['add_brokers_link']) && $re_authenticated_for_action) {
            $new_link = trim($_POST['new_link'] ?? '');
            if (!empty($new_link)) {
                try {
                    $current_links = $serverAccount['brokers_link'] ?? '';
                    $links_array = array_filter(array_map('trim', explode(',', $current_links)));
                    if (!in_array($new_link, $links_array)) {
                        $links_array[] = $new_link;
                        $updated_links = implode(',', $links_array);
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers_link = ? WHERE id = 1");
                        $stmt->execute([$updated_links]);
                        $_SESSION['admin_message'] = "<span style='color:green;'>✅ Broker link '{$new_link}' added successfully!</span>";
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:orange;'>⚠️ Broker link '{$new_link}' already exists.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error adding broker link: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ New broker link field cannot be empty.</span>";
            }
            header("Location: serveraccount.php?view=settings");
            exit;
        }

        // 6d: Delete Broker/Link
        if (isset($_POST['delete_broker']) && $re_authenticated_for_action) {
            $broker_to_delete = trim($_POST['broker_value'] ?? '');
            if (!empty($broker_to_delete)) {
                try {
                    $current_brokers = $serverAccount['brokers'] ?? '';
                    $brokers_array = array_filter(array_map('trim', explode(',', $current_brokers)));
                    $key = array_search($broker_to_delete, $brokers_array);
                    if ($key !== false) {
                        unset($brokers_array[$key]);
                        $updated_brokers = implode(',', array_filter($brokers_array));
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ? WHERE id = 1");
                        $stmt->execute([$updated_brokers]);
                        $_SESSION['admin_message'] = "<span style='color:green;'>✅ Broker '{$broker_to_delete}' deleted successfully!</span>";
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:orange;'>⚠️ Broker '{$broker_to_delete}' not found.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error deleting broker: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            }
            header("Location: serveraccount.php?view=settings");
            exit;
        }
        
        if (isset($_POST['delete_brokers_link']) && $re_authenticated_for_action) {
            $link_to_delete = trim($_POST['link_value'] ?? '');
            if (!empty($link_to_delete)) {
                try {
                    $current_links = $serverAccount['brokers_link'] ?? '';
                    $links_array = array_filter(array_map('trim', explode(',', $current_links)));
                    $key = array_search($link_to_delete, $links_array);
                    if ($key !== false) {
                        unset($links_array[$key]);
                        $updated_links = implode(',', array_filter($links_array));
                        $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers_link = ? WHERE id = 1");
                        $stmt->execute([$updated_links]);
                        $_SESSION['admin_message'] = "<span style='color:green;'>✅ Broker link '{$link_to_delete}' deleted successfully!</span>";
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:orange;'>⚠️ Broker link '{$link_to_delete}' not found.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error deleting broker link: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            }
            header("Location: serveraccount.php?view=settings");
            exit;
        }

        // 6e: Update Payment Status (with hierarchical validation and revenue history update)
        if (isset($_POST['update_payment_status']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $new_status = trim($_POST['payment_status'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            $normalizedStatus = normalizePaymentStatus($new_status);
            
            if (!empty($user_id) && !empty($new_status) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($targetUser) {
                        $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                        $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                        $decision = determineUserStatus($targetUser, $contractDuration, $minProfitForSplit);
                        
                        if ($decision['has_eligible_profit'] && $decision['should_show_in_revenue']) {
                            // Update the main loyalties field
                            $stmt = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
                            $stmt->execute([$normalizedStatus, $user_id]);
                            // If confirming payment, also update the revenue history
                            if ($normalizedStatus === 'payment-confirmed') {
                                // Get current revenue history
                                $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'revenue_history'");
                                if ($checkColumn->rowCount() > 0) {
                                    $stmt = $pdo->prepare("SELECT revenue_history FROM {$source_table} WHERE id = ?");
                                    $stmt->execute([$user_id]);
                                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($result && !empty($result['revenue_history'])) {
                                        $history = json_decode($result['revenue_history'], true);
                                        if (is_array($history)) {
                                            // FIRST: Sort by recorded_at DESCENDING (newest first)
                                            usort($history, function($a, $b) {
                                                $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                                                $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                                                return $dateB - $dateA;
                                            });
                                            
                                            // Find the FIRST (newest) payment-made record
                                            $latestPaymentMadeIndex = -1;
                                            for ($i = 0; $i < count($history); $i++) {
                                                $loyalties = strtolower($history[$i]['loyalties'] ?? '');
                                                if (strpos($loyalties, 'payment-made') !== false) {
                                                    $latestPaymentMadeIndex = $i;
                                                    break; // Stop at first (newest)
                                                }
                                            }
                                            
                                            // Update if found
                                            if ($latestPaymentMadeIndex !== -1) {
                                                $history[$latestPaymentMadeIndex]['loyalties'] = 'payment-confirmed';
                                                // Resort by ID or recorded_at for storage
                                                usort($history, function($a, $b) {
                                                    $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                                                    $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                                                    return $dateA - $dateB;
                                                });
                                                $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
                                                $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
                                                $updateStmt->execute([$jsonHistory, $user_id]);
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $_SESSION['admin_message'] = "<span style='color:green;'>✅ Payment status updated to '{$normalizedStatus}' for User ID {$user_id}!</span>";
                        } else {
                            $_SESSION['admin_message'] = "<span style='color:red;'>❌ Cannot update status: User does not have an eligible profit split scenario. Reason: {$decision['reason']}</span>";
                        }
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:red;'>❌ User not found.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating payment status: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Invalid update request. Please fill all fields.</span>";
            }
            header("Location: serveraccount.php?view=paid_users");
            exit;
        }

        // 6f: Update Server Decision
        if (isset($_POST['update_server_decision']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $server_decision = trim($_POST['server_decision'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && !empty($server_decision) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                try {
                    $checkColumn = $pdo->query("SHOW COLUMNS FROM {$source_table} LIKE 'server_decision'");
                    if ($checkColumn->rowCount() == 0) {
                        $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN server_decision VARCHAR(50) DEFAULT NULL");
                    }
                    
                    $stmt = $pdo->prepare("UPDATE {$source_table} SET server_decision = ? WHERE id = ?");
                    $stmt->execute([$server_decision, $user_id]);
                    $_SESSION['admin_message'] = "<span style='color:green;'>✅ Server decision updated to '{$server_decision}' for User ID {$user_id}!</span>";
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating server decision: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Invalid update request. Please fill all fields.</span>";
            }
            header("Location: serveraccount.php?view=paid_users");
            exit;
        }

        // 6g: Update News
        if (isset($_POST['update_news']) && $re_authenticated_for_action) {
            $news_content = trim($_POST['news_content'] ?? '');
            try {
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET news = ? WHERE id = 1");
                $stmt->execute([$news_content]);
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ News updated successfully!</span>";
            } catch (Exception $e) {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating news: " . htmlspecialchars($e->getMessage()) . "</span>";
            }
            header("Location: serveraccount.php?view=settings");
            exit;
        }
        
        // 6h: Update Application Status
        if (isset($_POST['update_application_status']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $new_status = trim($_POST['new_application_status'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && !empty($new_status) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
                try {
                    $stmt = $pdo->prepare("UPDATE {$source_table} SET application_status = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    $_SESSION['admin_message'] = "<span style='color:green;'>✅ Application status updated to '{$new_status}' for User ID {$user_id}!</span>";
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating application status: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Invalid update request. Please fill all fields.</span>";
            }
            header("Location: serveraccount.php?view=account_management");
            exit;
        }
        
        // Re-fetch account data after any potential update
        $stmt = $pdo->prepare("SELECT * FROM {$serverAccountTable} WHERE id = 1");
        $stmt->execute();
        $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check and add server_decision column if needed
        foreach ([$insidersTable, $insidersServerTable] as $table) {
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$table} LIKE 'server_decision'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$table} ADD COLUMN server_decision VARCHAR(50) DEFAULT NULL");
            }
        }
    }

    // ============================================
    // SECTION 7: DATA FETCHING FOR VIEWS
    // ============================================

    // 7a: Paid Users / Revenue Dashboard Data
    // 7a: Paid Users / Revenue Dashboard Data
    if ($authenticated && $currentView === 'paid_users') {
        $allUsers = [];
        
        // DASHBOARD SUMMARY - Calculate totals for ALL users (no filtering)
        $dashboardSummary = [
            'total_broker_balance' => 0,
            'total_profit' => 0,
            'total_current_balance' => 0,
            'total_server_share' => 0,
            'total_user_share' => 0,
            'total_expected_payment' => 0,
            'users_with_profit' => 0,
            'total_payments_received' => 0,
            'total_payments_made' => 0,
            'total_unpaid_payments' => 0
        ];
        
        // TABLE SUMMARY - Will be populated with filtered data for display
        $tableSummary = [
            'total_broker_balance' => 0,
            'total_profit' => 0,
            'total_current_balance' => 0,
            'total_server_share' => 0,
            'total_user_share' => 0,
            'total_expected_payment' => 0,
            'users_with_profit' => 0,
            'total_payments_received' => 0,
            'total_payments_made' => 0,
            'total_unpaid_payments' => 0
        ];
        
        $selectFields = "id, fullname, email, broker, login, loyalties, paymentdetails, broker_balance, profitandloss, submitted_at, execution_start_date";

        $serverSharePercent = (int)($serverAccount['server_share_percent'] ?? 30);
        $userSharePercent = (int)($serverAccount['user_share_percent'] ?? 70);
        $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30.00);
        $contractDuration = (int)($serverAccount['contract_duration'] ?? 0);

        $stmt1 = $pdo->prepare("SELECT {$selectFields}, '{$insidersTable}' AS source FROM {$insidersTable}");
        $stmt1->execute();
        $allUsers = array_merge($allUsers, $stmt1->fetchAll(PDO::FETCH_ASSOC));

        $stmt2 = $pdo->prepare("SELECT {$selectFields}, '{$insidersServerTable}' AS source FROM {$insidersServerTable}");
        $stmt2->execute();
        $allUsers = array_merge($allUsers, $stmt2->fetchAll(PDO::FETCH_ASSOC));
        
        foreach ($allUsers as &$user) {
            $brokerBalance = (float)($user['broker_balance'] ?? 0);
            $profitAndLoss = (float)($user['profitandloss'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            
            $user['broker_balance_display'] = $brokerBalance;
            $user['profitandloss_display'] = $profitAndLoss;
            $user['current_balance'] = $currentBalance;
            
            // ========== DASHBOARD SUMMARY (ALL USERS - NO RULES) ==========
            $dashboardSummary['total_broker_balance'] += $brokerBalance;
            $dashboardSummary['total_profit'] += $profitAndLoss;
            $dashboardSummary['total_current_balance'] += $currentBalance;
            
            // Calculate potential shares for dashboard (using raw profit, no eligibility check)
            if ($profitAndLoss > $minProfitForSplit) {
                $dashboardSummary['users_with_profit']++;
                $potentialServerShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $potentialUserShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                $dashboardSummary['total_server_share'] += $potentialServerShare;
                $dashboardSummary['total_user_share'] += $potentialUserShare;
                $dashboardSummary['total_expected_payment'] += $potentialServerShare;
                
                // Track payment statuses for dashboard (based on actual loyalties)
                $rawStatus = $user['loyalties'] ?? '';
                $normalizedStatus = normalizePaymentStatus($rawStatus);
                if ($normalizedStatus === 'payment-confirmed') {
                    $dashboardSummary['total_payments_received'] += $potentialServerShare;
                    $dashboardSummary['total_unpaid_payments'] += 0;
                } elseif ($normalizedStatus === 'payment-made') {
                    $dashboardSummary['total_payments_made'] += $potentialServerShare;
                    $dashboardSummary['total_unpaid_payments'] += 0;
                } else {
                    $dashboardSummary['total_unpaid_payments'] += $potentialServerShare;
                }
            }
            
            // ========== TABLE SUMMARY & DISPLAY LOGIC (WITH RULES) ==========
            $decision = determineUserStatus($user, $contractDuration, $minProfitForSplit);
            
            $user['should_show_in_revenue'] = $decision['should_show_in_revenue'];
            $user['server_share'] = $decision['server_share'];
            $user['user_share'] = $decision['user_share'];
            $user['expected_payment'] = $decision['expected_payment'];
            $user['has_eligible_profit'] = $decision['has_eligible_profit'];
            $user['determined_status'] = $decision['status'];
            $user['decision_reason'] = $decision['reason'];
            
            $unpaidAge = ['ended_on' => null, 'age' => null, 'is_ended' => false];
            if ($user['has_eligible_profit'] && !empty($user['execution_start_date']) && $contractDuration > 0) {
                $unpaidAge = calculateUnpaidAge($user['execution_start_date'], $contractDuration);
            }
            $user['unpaid_payment_age'] = $unpaidAge;
            
            $rawStatus = $user['loyalties'] ?? '';
            $normalizedStatus = normalizePaymentStatus($rawStatus);
            $user['loyalties_normalized'] = $normalizedStatus;
            
            if (!$user['should_show_in_revenue']) {
                $displayStatus = '';
            } elseif ($user['has_eligible_profit']) {
                if ($normalizedStatus === 'payment-confirmed') {
                    $displayStatus = 'payment-confirmed';
                    $tableSummary['total_payments_received'] += $user['expected_payment'];
                } elseif ($normalizedStatus === 'payment-made') {
                    $displayStatus = 'payment-made';
                    $tableSummary['total_payments_made'] += $user['expected_payment'];
                } else {
                    $displayStatus = 'unpaid-payment';
                    $tableSummary['total_unpaid_payments'] += $user['expected_payment'];
                    $tableSummary['total_expected_payment'] += $user['expected_payment'];
                }
            } else {
                $displayStatus = 'Not Eligible';
            }
            $user['display_status'] = $displayStatus;
            
            if ($user['should_show_in_revenue']) {
                $tableSummary['total_broker_balance'] += $brokerBalance;
                $tableSummary['total_profit'] += $profitAndLoss;
                $tableSummary['total_current_balance'] += $currentBalance;
                
                if ($user['has_eligible_profit']) {
                    $tableSummary['users_with_profit']++;
                    $tableSummary['total_server_share'] += $user['server_share'];
                    $tableSummary['total_user_share'] += $user['user_share'];
                }
            }
        }
        unset($user);
        
        // Use dashboard summary for display (this will be shown in summary cards)
        $revenueSummary = $dashboardSummary;
    }

    // ============================================
    // SECTION 8: LOGOUT HANDLING
    // ============================================
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: serveraccount.php");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Admin Dashboard</title>
    <?php include 'server_style.php' ?>
    <?php include 'server_script.php' ?>
</head>
<body>
    <div id="custom-body">
        <?php if ($initialSetupRequired || !$authenticated): ?>
            <!-- ============================================ -->
            <!-- SECTION 9: LOGIN / SETUP SCREEN               -->
            <!-- ============================================ -->
            <div class="container login-container">
                <h2><?= $initialSetupRequired ? '🔑 Admin Setup' : '🔒 Admin Login' ?></h2>
                <?php if ($message): ?>
                    <p class="message"><?= $message ?></p>
                <?php endif; ?>
                
                <?php if (!$authenticated && !$initialSetupRequired): ?>
                    <p style="text-align: center; color: #e74c3c; font-weight: bold;">Session expired or login required.</p>
                <?php endif; ?>

                <form method="POST" action="serveraccount.php">
                    <input type="hidden" name="<?= $initialSetupRequired ? 'initial_setup' : 'admin_login' ?>" value="1">
                    <label for="login_id">Login ID:</label>
                    <input type="text" id="login_id" name="login_id" required autofocus 
                            value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? ($_POST['login_id'] ?? '')) ?>">

                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>

                    <button type="submit"><?= $initialSetupRequired ? 'Set Credentials & Login' : 'Login' ?></button>
                </form>
            </div>


            
    <!-- ============================================ -->
    <!-- SECTION 10: AUTHENTICATED ADMIN DASHBOARD     -->
    <!-- ============================================ -->
        <?php else: ?>
            <div class="container">
                <a href="?logout=1" class="logout-link">Logout</a>

                <?php if ($message): ?>
                    <p class="message"><?= $message ?></p>
                <?php endif; ?>
                
                <?php if ($currentView !== 'menu'): ?>
                    <a href="serveraccount.php?view=menu" class="back-btn">← Back to Menu</a>
                <?php endif; ?>


                
            <!-- ============================================ -->
            <!-- SECTION 10a: MENU / NAVIGATION                -->
            <!-- ============================================ -->
                <?php if ($currentView === 'menu'): ?>
                    <h2> Admin Navigation</h2>
                    <div class="nav-menu">
                        <a href="serveraccount.php?view=settings"> Server Settings & Configuration</a>
                        <a href="serveraccount.php?view=system_config"> System Servers Config</a> 
                        <a href="serveraccount.php?view=paid_users"> Revenue & Users Dashboard</a>
                        <a href="serveraccount.php?view=account_management">Account Management</a>
                        <a href="serveraccount.php?view=analytics">Analytics</a>
                        <a href="serveraccount.php?view=manual">📚 Manual</a>
                    </div>
                    
            <!-- ============================================ -->
            <!-- SECTION 10b: UNIFIED ACCOUNT MANAGEMENT      -->
            <!-- ============================================ -->
            <?php elseif ($currentView === 'account_management'): ?>
                <?php include 'accountmanagement.php'?>
            <!-- ============================================ -->
            <!-- SECTION 10c: ANALYTICS (NEW)                  -->
            <!-- ============================================ -->
            <?php elseif ($currentView === 'analytics'): ?>
                <?php include 'analytics.php'; ?>     
            <?php elseif ($currentView === 'system_config'): ?>
                <?php include 'system_server_config.php'; ?>
            <?php elseif ($currentView === 'manual'): ?>
                <?php include 'manual.php'; ?>
            <!-- ============================================ -->
            <!-- SECTION 10d: SETTINGS & CONFIGURATION        -->
            <!-- ============================================ -->
                <?php elseif ($currentView === 'settings'): ?>
                    <h2> Server Settings</h2>
                    
                    <form method="POST" action="serveraccount.php?view=settings" id="address-form">
                        <input type="hidden" name="update_addresses" value="1">
                        
                        <h3> Payment & Revenue Settings</h3>
                        <div class="settings-grid">
                            <div class="settings-card">
                                <label for="contract_duration">Contract Duration (Days)</label>
                                <input type="number" min="0" id="contract_duration" name="contract_duration" value="<?= htmlspecialchars($serverAccount['contract_duration'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <h3>Profit Sharing Settings</h3>
                        <div class="settings-grid">
                            <div class="settings-card">
                                <label for="server_share_percent">Server Share (%)</label>
                                <input type="number" min="0" max="100" id="server_share_percent" name="server_share_percent" value="<?= htmlspecialchars($serverAccount['server_share_percent'] ?? '30') ?>">
                            </div>
                            <div class="settings-card">
                                <label for="user_share_percent">User Share (%)</label>
                                <input type="number" min="0" max="100" id="user_share_percent" name="user_share_percent" value="<?= htmlspecialchars($serverAccount['user_share_percent'] ?? '70') ?>">
                            </div>
                            <div class="settings-card">
                                <label for="min_profit_for_split">Min Profit for Split ($)</label>
                                <input type="number" step="0.01" min="0" id="min_profit_for_split" name="min_profit_for_split" value="<?= htmlspecialchars($serverAccount['min_profit_for_split'] ?? '30.00') ?>">
                            </div>
                            <div class="settings-card">
                                <label for="min_broker_balance">Min Broker Balance ($)</label>
                                <input type="number" step="0.01" min="0" id="min_broker_balance" name="min_broker_balance" value="<?= htmlspecialchars($serverAccount['min_broker_balance'] ?? '30.00') ?>">
                            </div>
                        </div>
                        
                        <h3> Crypto Addresses</h3>
                        <div class="settings-grid">
                            <div class="settings-card">
                                <label for="btc_address">BTC Address</label>
                                <input type="text" id="btc_address" name="btc_address" value="<?= htmlspecialchars($serverAccount['btc_address'] ?? '') ?>" required>
                            </div>
                            <div class="settings-card">
                                <label for="eth_address">ETH Address</label>
                                <input type="text" id="eth_address" name="eth_address" value="<?= htmlspecialchars($serverAccount['eth_address'] ?? '') ?>" required>
                            </div>
                            <div class="settings-card">
                                <label for="eth_network">ETH Network</label>
                                <input type="text" id="eth_network" name="eth_network" value="<?= htmlspecialchars($serverAccount['eth_network'] ?? 'ERC20') ?>" required>
                            </div>
                            <div class="settings-card">
                                <label for="usdt_address">USDT Address</label>
                                <input type="text" id="usdt_address" name="usdt_address" value="<?= htmlspecialchars($serverAccount['usdt_address'] ?? '') ?>" required>
                            </div>
                            <div class="settings-card">
                                <label for="usdt_network">USDT Network</label>
                                <input type="text" id="usdt_network" name="usdt_network" value="<?= htmlspecialchars($serverAccount['usdt_network'] ?? 'TRC20') ?>" required>
                            </div>
                        </div>

                        <button type="submit">💾 Save All Settings</button>
                    </form>

                    <hr>

                    <hr>

                    <h3> Broker Management</h3>
                    <?php $current_brokers = get_list_array($serverAccount['brokers'] ?? ''); ?>
                    <div class="list-management">
                        <h4>Current Brokers (<?= count($current_brokers) ?>)</h4>
                        <?php if (!empty($current_brokers)): ?>
                            <?php foreach ($current_brokers as $broker): ?>
                                <div class="list-item">
                                    <span><?= htmlspecialchars($broker) ?></span>
                                    <form method="POST" action="serveraccount.php?view=settings" class="delete-broker-form" style="display:inline;">
                                        <input type="hidden" name="delete_broker" value="1">
                                        <input type="hidden" name="broker_value" value="<?= htmlspecialchars($broker) ?>">
                                        <button type="submit" class="list-item-btn">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #7f8c8d;">No brokers configured.</p>
                        <?php endif; ?>

                        <h4>Add New Broker</h4>
                        <form method="POST" action="serveraccount.php?view=settings" id="add-broker-form" class="add-new-form">
                            <input type="hidden" name="add_broker" value="1">
                            <input type="text" name="new_broker" placeholder="e.g., BrokerXYZ" required>
                            <button type="submit">Add Broker</button>
                        </form>
                    </div>
                    
                    <h4 style="margin-top: 30px;">🔗 Broker Links</h4>
                    <?php $current_links = get_list_array($serverAccount['brokers_link'] ?? ''); ?>
                    <div class="list-management">
                        <h4>Current Links (<?= count($current_links) ?>)</h4>
                        <p style="font-size: 12px; color: #7f8c8d;">Links correspond to broker order above.</p>
                        <?php if (!empty($current_links)): ?>
                            <?php foreach ($current_links as $link): ?>
                                <div class="list-item">
                                    <span style="font-size: 13px; word-break: break-all;"><?= htmlspecialchars($link) ?></span>
                                    <form method="POST" action="serveraccount.php?view=settings" class="delete-link-form" style="display:inline;">
                                        <input type="hidden" name="delete_brokers_link" value="1">
                                        <input type="hidden" name="link_value" value="<?= htmlspecialchars($link) ?>">
                                        <button type="submit" class="list-item-btn">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: #7f8c8d;">No broker links configured.</p>
                        <?php endif; ?>
                        
                        <h4>Add New Link</h4>
                        <form method="POST" action="serveraccount.php?view=settings" id="add-link-form" class="add-new-form">
                            <input type="hidden" name="add_brokers_link" value="1">
                            <input type="text" name="new_link" placeholder="https://broker.com/signup" required>
                            <button type="submit">Add Link</button>
                        </form>
                    </div>

                    <button type="button" id="toggle-credentials" class="toggle-btn">👤 Edit Admin Credentials</button>

                    <div id="credentials-section" class="credentials-section">
                        <h2>Edit Admin Credentials</h2>
                        <form method="POST" action="serveraccount.php?view=settings" id="credentials-form">
                            <input type="hidden" name="update_credentials" value="1">
                            
                            <label for="new_login_id">New Login ID:</label>
                            <input type="text" id="new_login_id" name="new_login_id" value="<?= htmlspecialchars($serverAccount['admin_login_id'] ?? '') ?>" required>

                            <label for="new_password">New Password (leave blank to keep):</label>
                            <input type="password" id="new_password" name="new_password" placeholder="********">
                            
                            <button type="submit">Update Credentials</button>
                        </form>
                    </div>
                

                    
            <!-- ============================================ -->
            <!-- SECTION 10e: REVENUE DASHBOARD               -->
            <!-- ============================================ -->
                <?php elseif ($currentView === 'paid_users'): ?>
                    <?php include 'revenue.php'; ?>  
                <?php endif; ?>
                
            </div>
            
            <!-- ============================================ -->
            <!-- SECTION 11: MODALS                           -->
            <!-- ============================================ -->
            
            <!-- Password Modal -->
            <div id="password-modal" class="modal">
                <div class="modal-content">
                    <h3 id="modal-title">SECURITY CHECK</h3>
                    <p id="modal-paragraph">Please enter your Admin Password.</p>
                    <input type="password" id="modal-password-input" placeholder="Admin Password" required>
                    <div class="modal-buttons">
                        <button type="button" id="modal-confirm-btn">Confirm</button>
                        <button type="button" id="modal-cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>



        <?php endif; ?>
    </div>

</body>
</html>

