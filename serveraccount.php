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
    // SECTION 2.5: SYNC ACCOUNTMANAGEMENT TO ACCOUNTMANAGEMENT_CONFIGS
    // ============================================
    // This runs on every page load to ensure accountmanagement_configs always reflects
    // the current data from accountmanagement column

    try {
        // Check if accountmanagement column exists and has data
        if (isset($serverAccount['accountmanagement']) && !empty($serverAccount['accountmanagement'])) {
            $currentManagementData = json_decode($serverAccount['accountmanagement'], true);
            
            // Only proceed if we have valid JSON data
            if (json_last_error() === JSON_ERROR_NONE && is_array($currentManagementData) && !empty($currentManagementData)) {
                
                // Get current configs to compare
                $currentConfigs = !empty($serverAccount['accountmanagement_configs']) 
                    ? json_decode($serverAccount['accountmanagement_configs'], true) 
                    : [];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentConfigs = [];
                }
                
                // Check if sync is needed - compare the data
                $needsSync = false;
                
                // If configs is empty, definitely needs sync
                if (empty($currentConfigs)) {
                    $needsSync = true;
                } else {
                    // Check if any management data keys are missing from configs
                    foreach ($currentManagementData as $key => $value) {
                        if (!isset($currentConfigs[$key]) || $currentConfigs[$key] !== $value) {
                            $needsSync = true;
                            break;
                        }
                    }
                }
                
                // If sync needed, update the configs column
                if ($needsSync) {
                    // Merge: start with existing configs, then override with management data
                    $mergedData = array_merge($currentConfigs, $currentManagementData);
                    $jsonData = json_encode($mergedData, JSON_PRETTY_PRINT);
                    
                    $updateStmt = $pdo->prepare("UPDATE {$serverAccountTable} SET accountmanagement_configs = ? WHERE id = 1");
                    $updateStmt->execute([$jsonData]);
                    
                    // Update the local $serverAccount variable with new configs
                    $serverAccount['accountmanagement_configs'] = $jsonData;
                    
                    // Optional: Log the sync (comment out if not needed)
                    // error_log("Synced accountmanagement to accountmanagement_configs at " . date('Y-m-d H:i:s'));
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page if sync fails
        // error_log("Error syncing accountmanagement to configs: " . $e->getMessage());
    }
    // ============================================
    // SECTION 2.5b: SYNC ACCOUNTMANAGEMENT_CONFIGS TO ACCOUNTMANAGEMENT (REVERSE SYNC)
    // ============================================
    // This runs on every page load to ensure accountmanagement always reflects
    // the current data from accountmanagement_configs where keys exist in both

    try {
        // Check if accountmanagement_configs column exists and has data
        if (isset($serverAccount['accountmanagement_configs']) && !empty($serverAccount['accountmanagement_configs'])) {
            $configsData = json_decode($serverAccount['accountmanagement_configs'], true);
            
            // Only proceed if we have valid JSON data
            if (json_last_error() === JSON_ERROR_NONE && is_array($configsData) && !empty($configsData)) {
                
                // Get current management data to compare
                $currentManagementData = !empty($serverAccount['accountmanagement']) 
                    ? json_decode($serverAccount['accountmanagement'], true) 
                    : [];
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentManagementData = [];
                }
                
                // Check if sync is needed - compare the data
                $needsSync = false;
                $mergedData = $currentManagementData;
                
                // For each key in configs, ensure it exists in management with the same value
                foreach ($configsData as $key => $value) {
                    if (!isset($mergedData[$key]) || $mergedData[$key] !== $value) {
                        $mergedData[$key] = $value;
                        $needsSync = true;
                    }
                }
                
                // If sync needed, update the management column
                if ($needsSync) {
                    $jsonData = json_encode($mergedData, JSON_PRETTY_PRINT);
                    
                    $updateStmt = $pdo->prepare("UPDATE {$serverAccountTable} SET accountmanagement = ? WHERE id = 1");
                    $updateStmt->execute([$jsonData]);
                    
                    // Update the local $serverAccount variable with new management data
                    $serverAccount['accountmanagement'] = $jsonData;
                    
                    // Optional: Log the sync (comment out if not needed)
                    // error_log("Synced accountmanagement_configs to accountmanagement at " . date('Y-m-d H:i:s'));
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail - don't break the page if sync fails
        // error_log("Error syncing accountmanagement_configs to accountmanagement: " . $e->getMessage());
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
            'unpaid payment' => 'unpaid-payment',
            'failedpayment' => 'failed-payment',
            'failed_payment' => 'failed-payment',
            'failed-payment' => 'failed-payment',
            'failed payment' => 'failed-payment',
            'paymentfailed' => 'failed-payment',
            'payment_failed' => 'failed-payment',
            'payment-failed' => 'failed-payment'
        ];
        
        return $statusMap[$status] ?? $status;
    }
    function calculatePaymentSummaryFromHistory($history) {
        $summary = [
            'total_unpaid_revenue' => 0,
            'total_payment_made' => 0,
            'total_payment_confirmed' => 0,
            'total_cancelled_contracts' => 0,
            'total_failed_payments' => 0,
            'unpaid_count' => 0,
            'payment_made_count' => 0,
            'payment_confirmed_count' => 0,
            'cancelled_count' => 0,
            'failed_count' => 0
        ];
        
        if (!is_array($history)) {
            return $summary;
        }
        
        foreach ($history as $record) {
            $loyalties = strtolower($record['loyalties'] ?? '');
            // FIX: Use server_share from the record
            $serverShare = (float)($record['server_share'] ?? 0);
            
            // Check if this is an active contract (should be excluded from totals)
            if (strpos($loyalties, 'active') !== false) {
                continue; // Skip active contracts
            }
            
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
            } elseif (in_array($loyalties, ['failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'])) {
                $summary['total_failed_payments'] += $serverShare;
                $summary['failed_count']++;
            } elseif (in_array($loyalties, ['loss_completed', 'below_threshold', 'contract_ended'])) {
                // These don't have server_share, so they don't add to totals
                // But we might want to count them
            }
        }
        
        return $summary;
    }

    function determineUserStatus($user, $contractDuration, $minProfitForSplit, $serverSharePercent = null, $userSharePercent = null) {
        // Fallback to globals if not provided
        if ($serverSharePercent === null) {
            $serverSharePercent = (int)($GLOBALS['serverAccount']['server_share_percent'] ?? 30);
        }
        if ($userSharePercent === null) {
            $userSharePercent = (int)($GLOBALS['serverAccount']['user_share_percent'] ?? 70);
        }
        
        $executionStartDate = $user['execution_start_date'] ?? null;
        $profitAndLoss = (float)($user['profitandloss'] ?? 0);
        $currentLoyalties = normalizePaymentStatus($user['loyalties'] ?? '');
        $contractId = $user['contract_id'] ?? null;
        
        $is_execution_empty = ($executionStartDate === null || $executionStartDate === '0000-00-00');
        
        $contract_completed = false;
        $is_contract_active = false;
        $has_valid_execution = false;
        $isContractExpiredWithProfit = false;
        
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
            
            if ($contract_completed && $profitAndLoss > $minProfitForSplit) {
                $isContractExpiredWithProfit = true;
            }
        }
        
        $isCancelled = false;
        if (strpos(strtolower($currentLoyalties), 'cancelled') !== false) {
            $isCancelled = true;
        }
        
        $isFailedPayment = ($currentLoyalties === 'failed-payment');
        
        // SPECIAL: Expired contract with profit > threshold
        if ($isContractExpiredWithProfit && !$isCancelled) {
            $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
            $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            
            $validStatuses = ['payment-confirmed', 'payment-made', 'unpaid-payment', 'failed-payment'];
            $statusAlreadySet = false;
            foreach ($validStatuses as $validStatus) {
                if (strpos(strtolower($currentLoyalties), $validStatus) !== false) {
                    $statusAlreadySet = true;
                    break;
                }
            }
            
            $statusToSet = $statusAlreadySet ? $currentLoyalties : 'unpaid-payment';
            
            return [
                'status' => $statusToSet,
                'should_show_in_revenue' => true,
                'server_share' => $serverShare,
                'user_share' => $userShare,
                'expected_payment' => $serverShare,
                'has_eligible_profit' => true,
                'reason' => 'Contract expired with profit above threshold'
            ];
        }
        
        if ($is_contract_active) {
            return [
                'status' => $currentLoyalties ?: 'active',
                'should_show_in_revenue' => false,
                'server_share' => 0,
                'user_share' => 0,
                'expected_payment' => 0,
                'has_eligible_profit' => false,
                'reason' => 'Contract active - no updates allowed'
            ];
        }
        
        if ($profitAndLoss <= 0) {
            return [
                'status' => $currentLoyalties ?: 'inactive',
                'should_show_in_revenue' => false,
                'server_share' => 0,
                'user_share' => 0,
                'expected_payment' => 0,
                'has_eligible_profit' => false,
                'reason' => 'No profit to split'
            ];
        }
        
        if ($profitAndLoss <= $minProfitForSplit) {
            return [
                'status' => $currentLoyalties ?: 'below_threshold',
                'should_show_in_revenue' => false,
                'server_share' => 0,
                'user_share' => 0,
                'expected_payment' => 0,
                'has_eligible_profit' => false,
                'reason' => 'Profit below split threshold'
            ];
        }
        
        if ($profitAndLoss > $minProfitForSplit) {
            $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
            $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            
            $normalizedCurrent = normalizePaymentStatus($currentLoyalties);
            
            return [
                'status' => $normalizedCurrent ?: 'unpaid-payment',
                'should_show_in_revenue' => true,
                'server_share' => $serverShare,
                'user_share' => $userShare,
                'expected_payment' => $serverShare,
                'has_eligible_profit' => true,
                'reason' => 'Eligible for profit split'
            ];
        }
        
        return [
            'status' => $currentLoyalties ?: 'inactive',
            'should_show_in_revenue' => false,
            'server_share' => 0,
            'user_share' => 0,
            'expected_payment' => 0,
            'has_eligible_profit' => false,
            'reason' => 'Default'
        ];
    }

    function getProgrammeInvestorData($pdo, $userId) {
        // 1) Try: user is enrolled as an investor in someone's programme
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    pi.contract_duration,
                    pi.developer_percentage,
                    pi.investor_percentage,
                    pi.developerid,
                    pi.programme_id,
                    d.fullname AS developer_name,
                    p.program_name AS programme_name
                FROM programme_investors pi
                LEFT JOIN developers d ON d.id = pi.developerid
                LEFT JOIN programme p ON p.id = pi.programme_id
                WHERE pi.investorid = ?
                ORDER BY pi.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'has_programme'         => true,
                    'contract_duration'     => (int)$row['contract_duration'],
                    'developer_percentage'  => (int)$row['developer_percentage'],
                    'investor_percentage'   => (int)$row['investor_percentage'],
                    'developerid'           => (int)$row['developerid'],
                    'developer_name'        => $row['developer_name'] ?? 'N/A',
                    'programme_id'          => (int)$row['programme_id'],
                    'programme_name'        => $row['programme_name'] ?? '',
                    'role'                  => 'investor'
                ];
            }
        } catch (Exception $e) { /* fall through */ }

        // 2) Fallback: user is a developer with a programme template row
        //    (covers the "investor is also a developer" case)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    pi.contract_duration,
                    pi.developer_percentage,
                    pi.investor_percentage,
                    pi.developerid,
                    pi.programme_id,
                    d.fullname AS developer_name,
                    p.program_name AS programme_name
                FROM programme_investors pi
                LEFT JOIN developers d ON d.id = pi.developerid
                LEFT JOIN programme p ON p.id = pi.programme_id
                WHERE pi.developerid = ?
                ORDER BY pi.id DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'has_programme'         => true,
                    'contract_duration'     => (int)$row['contract_duration'],
                    'developer_percentage'  => (int)$row['developer_percentage'],
                    'investor_percentage'   => (int)$row['investor_percentage'],
                    'developerid'           => (int)$row['developerid'],
                    'developer_name'        => $row['developer_name'] ?? 'N/A',
                    'programme_id'          => (int)$row['programme_id'],
                    'programme_name'        => $row['programme_name'] ?? '',
                    'role'                  => 'developer'  // acting as own investor
                ];
            }
        } catch (Exception $e) { /* fall through */ }

        // 3) No programme anywhere → defaults
        return [
            'has_programme'         => false,
            'contract_duration'     => (int)($GLOBALS['serverAccount']['contract_duration'] ?? 30),
            'developer_percentage'  => (int)($GLOBALS['serverAccount']['server_share_percent'] ?? 30),
            'investor_percentage'   => (int)($GLOBALS['serverAccount']['user_share_percent'] ?? 70),
            'developerid'           => 0,
            'developer_name'        => 'N/A',
            'programme_id'          => 0,
            'programme_name'        => '',
            'role'                  => 'none'
        ];
    }


    function format_currency($amount) {
        return '$' . number_format($amount, 2);
    }
    
    // ============================================
    // SECTION 3b: REVENUE HISTORY SYNC FUNCTION (UPDATED - TABLE BASED)
    // ============================================

    function syncUserRevenueHistory($userId, $sourceTable, $pdo, $serverAccount) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM {$sourceTable} WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Get programme-specific values
            $progData = getProgrammeInvestorData($pdo, $userId);
            $contractDuration = $progData['contract_duration'];
            $serverSharePercent = $progData['developer_percentage'];
            $userSharePercent = $progData['investor_percentage'];
            
            $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
            
            $executionStartDate = $userData['execution_start_date'] ?? null;
            $brokerBalance = (float)($userData['broker_balance'] ?? 0);
            $profitAndLoss = (float)($userData['profitandloss'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            $currentLoyalties = normalizePaymentStatus($userData['loyalties'] ?? '');
            $contractId = $userData['contract_id'] ?? null;
            $investedWith = $userData['invested_with'] ?? null;
            $userEmail = $userData['email'] ?? '';
            
            $isContractActive = false;
            $executionEndDate = null;
            
            if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00') {
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $executionEndDate = $end->format('Y-m-d');
                
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $isContractActive = ($today <= $end);
            }
            
            $serverShare = 0;
            $userShare = 0;
            $isEligible = false;
            if ($profitAndLoss > $minProfitForSplit) {
                $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                $isEligible = true;
            }
            
            if (empty($contractId) || $contractId === 'N/A' || $contractId === 'null') {
                if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00' && !empty($executionEndDate)) {
                    $startFormatted = date('dmY', strtotime($executionStartDate));
                    $endFormatted = date('dmY', strtotime($executionEndDate));
                    $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
                    
                    $updateContractId = $pdo->prepare("UPDATE {$sourceTable} SET contract_id = ? WHERE id = ?");
                    $updateContractId->execute([$contractId, $userId]);
                } else if ($currentLoyalties === 'failed-payment' || strpos($currentLoyalties, 'failed') !== false) {
                    $now = new DateTime();
                    $executionStartDate = $now->format('Y-m-d');
                    $start = clone $now;
                    $end = clone $start;
                    $end->modify("+{$contractDuration} days");
                    $executionEndDate = $end->format('Y-m-d');
                    $startFormatted = date('dmY', strtotime($executionStartDate));
                    $endFormatted = date('dmY', strtotime($executionEndDate));
                    $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
                    
                    $updateContractId = $pdo->prepare("UPDATE {$sourceTable} SET contract_id = ? WHERE id = ?");
                    $updateContractId->execute([$contractId, $userId]);
                } else {
                    return ['success' => true, 'message' => 'No valid contract_id or dates available'];
                }
            }
            
            $now = date('Y-m-d H:i:s');
            $finalStatus = $currentLoyalties;
            
            if (strpos(strtolower($currentLoyalties), 'cancelled') !== false) {
                $finalStatus = 'contract_cancelled';
            }
            
            $stmt = $pdo->prepare("SELECT * FROM revenue_history WHERE user_email = ? AND contract_id = ?");
            $stmt->execute([$userEmail, $contractId]);
            $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                $updateFields = [
                    'execution_start_date' => $executionStartDate,
                    'execution_end_date' => $executionEndDate,
                    'starting_balance' => $brokerBalance,
                    'current_balance' => $currentBalance,
                    'profit' => $profitAndLoss,
                    'user_share' => $userShare,
                    'server_share' => $serverShare,
                    'loyalties' => $finalStatus,
                    'invested_with' => $investedWith,
                    'updated_at' => $now
                ];
                
                if ($currentLoyalties === 'payment-confirmed') {
                    $updateFields['payment_date'] = $now;
                }
                
                if ($currentLoyalties === 'failed-payment' || strpos($currentLoyalties, 'failed') !== false) {
                    $updateFields['payment_details'] = 'Payment verification failed';
                }
                
                $setClause = [];
                $params = [];
                foreach ($updateFields as $key => $value) {
                    $setClause[] = "{$key} = ?";
                    $params[] = $value;
                }
                $params[] = $userEmail;
                $params[] = $contractId;
                
                $updateStmt = $pdo->prepare("UPDATE revenue_history SET " . implode(', ', $setClause) . " WHERE user_email = ? AND contract_id = ?");
                $updateStmt->execute($params);
                
                $message = 'Revenue history updated';
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO revenue_history (
                        user_email, contract_id, execution_start_date, execution_end_date,
                        starting_balance, current_balance, profit, user_share, server_share,
                        loyalties, invested_with, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insertStmt->execute([
                    $userEmail,
                    $contractId,
                    $executionStartDate,
                    $executionEndDate,
                    $brokerBalance,
                    $currentBalance,
                    $profitAndLoss,
                    $userShare,
                    $serverShare,
                    $finalStatus,
                    $investedWith,
                    $now,
                    $now
                ]);
                
                $message = 'New revenue record created';
            }
            
            if ($currentLoyalties === 'failed-payment' || strpos($currentLoyalties, 'failed') !== false) {
                $message .= " - Payment marked as failed";
            }
            
            return ['success' => true, 'message' => $message];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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
    // SECTION 4.5: AUTO-MARK EXPIRED CONTRACTS AS UNPAID
    // UPDATED: Uses programme_investors for contract duration per user
    // ============================================

    if ($authenticated) {
        try {
            $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            $validStatuses = ['payment-confirmed', 'payment-made', 'unpaid-payment', 'failed-payment'];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT id, execution_start_date, profitandloss, loyalties, email, invested_with 
                    FROM {$harvhubTable} 
                    WHERE execution_start_date IS NOT NULL 
                    AND execution_start_date != '0000-00-00'
                    AND execution_start_date != ''
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($users as $user) {
                    $executionStartDate = $user['execution_start_date'];
                    $profitAndLoss = (float)($user['profitandloss'] ?? 0);
                    $currentLoyalties = strtolower(trim($user['loyalties'] ?? ''));
                    $userId = $user['id'];
                    
                    // Get programme-specific contract duration
                    $progData = getProgrammeInvestorData($pdo, $userId);
                    $contractDuration = $progData['contract_duration'];
                    
                    try {
                        $start = new DateTime($executionStartDate);
                        $end = clone $start;
                        $end->modify("+{$contractDuration} days");
                        $end->setTime(0, 0, 0);
                        
                        if ($today <= $end) {
                            continue;
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                    
                    if ($profitAndLoss <= $minProfitForSplit) {
                        continue;
                    }
                    
                    $statusAlreadySet = false;
                    foreach ($validStatuses as $validStatus) {
                        if (strpos($currentLoyalties, $validStatus) !== false) {
                            $statusAlreadySet = true;
                            break;
                        }
                    }
                    
                    if (!$statusAlreadySet) {
                        $updateStmt = $pdo->prepare("UPDATE {$harvhubTable} SET loyalties = 'unpaid-payment' WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        
                        if (function_exists('syncUserRevenueHistory')) {
                            syncUserRevenueHistory($userId, $harvhubTable, $pdo, $serverAccount);
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip on error
            }
        } catch (Exception $e) {
            // Silent fail
        }
    }
    // ============================================
    // SECTION 5: AJAX ENDPOINTS (Live Updates & Account Management)
    // ============================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' && $authenticated) {
        header('Content-Type: application/json');
        
        $action = $_POST['action'] ?? '';
        // 5a0: Get Programme Investors Map (userid => programme data)
        if ($action === 'get_programme_investors_map') {
            try {
                $investorsMap = [];

                // One row per investor — the latest enrolment, joined to programme + developer
                // (developer here is the programme owner, i.e. programme.userid)
                $stmt = $pdo->query("
                    SELECT
                        pi.investorid,
                        pi.developerid,
                        pi.programme_id,
                        pi.contract_duration,
                        pi.developer_percentage,
                        pi.investor_percentage,
                        pi.minimum_investment_amount,
                        pi.maximum_investment_amount,
                        d.fullname  AS developer_name,
                        d.email     AS developer_email,
                        p.program_name AS programme_name
                    FROM programme_investors pi
                    LEFT JOIN harvhub    d ON d.id = pi.developerid
                    LEFT JOIN programme  p ON p.id = pi.programme_id
                    WHERE pi.investorid > 0
                    ORDER BY pi.id ASC
                ");

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $investorId = (int)$row['investorid'];
                    // Last row wins — matches programmes.php's "one active enrolment" model.
                    $investorsMap[$investorId] = [
                        'developerid'               => (int)$row['developerid'],
                        'developer_name'            => $row['developer_name']  ?? 'N/A',
                        'developer_email'           => $row['developer_email'] ?? '',
                        'programme_id'              => (int)$row['programme_id'],
                        'programme_name'            => $row['programme_name']  ?? '',
                        'contract_duration'         => (int)$row['contract_duration'],
                        'developer_percentage'      => (int)$row['developer_percentage'],
                        'investor_percentage'       => (int)$row['investor_percentage'],
                        'minimum_investment_amount' => (float)$row['minimum_investment_amount'],
                        'maximum_investment_amount' => (float)$row['maximum_investment_amount'],
                        'has_programme'             => true
                    ];
                }

                echo json_encode(['success' => true, 'investors' => $investorsMap]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5a1: Get programme investor data for a single user
        if ($action === 'get_programme_investor_for_user') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            if ($user_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        pi.investorid,
                        pi.developerid,
                        pi.programme_id,
                        pi.contract_duration,
                        pi.developer_percentage,
                        pi.investor_percentage,
                        pi.minimum_investment_amount,
                        pi.maximum_investment_amount,
                        d.fullname AS developer_name,
                        p.program_name AS programme_name
                    FROM programme_investors pi
                    LEFT JOIN developers d ON d.id = pi.developerid
                    LEFT JOIN programme p ON p.id = pi.programme_id
                    WHERE pi.investorid = ?
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($row) {
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'developerid' => (int)$row['developerid'],
                            'developer_name' => $row['developer_name'] ?? 'N/A',
                            'programme_id' => (int)$row['programme_id'],
                            'programme_name' => $row['programme_name'] ?? '',
                            'contract_duration' => (int)$row['contract_duration'],
                            'developer_percentage' => (int)$row['developer_percentage'],
                            'investor_percentage' => (int)$row['investor_percentage'],
                            'minimum_investment_amount' => (float)$row['minimum_investment_amount'],
                            'maximum_investment_amount' => (float)$row['maximum_investment_amount'],
                            'has_programme' => true
                        ]
                    ]);
                } else {
                    echo json_encode(['success' => true, 'data' => null]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        
        // 5a: Live User Data Update
        if (empty($action)) {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && $source_table === $harvhubTable) {
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
        // 5aa: Get Active Investors (users with active contracts) - UPDATED for programme_investors
        if ($action === 'get_active_investors') {
            try {
                $users = array();
                $defaultContractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $today = date('Y-m-d');
                $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                
                function shouldBeActive($user, $contractDuration, $minProfitForSplit) {
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false;
                    }
                    
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false;
                    }
                    
                    $execDate = $user['execution_start_date'] ?? null;
                    if (empty($execDate) || $execDate === '0000-00-00' || $execDate === null) {
                        return false;
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    
                    if (strpos($loyalties, 'cancelled') !== false) {
                        return false;
                    }
                    
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime();
                            $todayObj->setTime(0, 0, 0);
                            
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            $isContractActive = false;
                        }
                    }
                    
                    if ($isContractActive) {
                        return true;
                    }
                    
                    $activeStatuses = ['payment-made', 'payment_made', 'unpaid-payment', 'unpaid_payment', 'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'];
                    
                    foreach ($activeStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return true;
                        }
                    }
                    
                    if (strpos($loyalties, 'payment-confirmed') !== false || strpos($loyalties, 'payment_confirmed') !== false) {
                        return false;
                    }
                    
                    return false;
                }
                
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, execution_start_date, profitandloss, broker_balance, loyalties, application_status, '{$harvhubTable}' as source
                            FROM {$harvhubTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt->execute();
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            // Get programme-specific data for this user
                            $progData = getProgrammeInvestorData($pdo, $user['id']);
                            $contractDuration = $progData['contract_duration'];
                            
                            if (shouldBeActive($user, $contractDuration, $minProfitForSplit)) {
                                $user['contract_duration'] = $contractDuration;
                                $user['developer_name'] = $progData['developer_name'];
                                $user['programme_name'] = $progData['programme_name'];
                                $user['developer_percentage'] = $progData['developer_percentage'];
                                $user['investor_percentage'] = $progData['investor_percentage'];
                                $users[] = $user;
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
        // 5aa2: Get Unusual Users (with daily_balance_log analysis) - UPDATED for programme_investors
        if ($action === 'get_unusual_users') {
            try {
                $search = trim($_POST['search'] ?? '');
                $users = [];
                $today = date('Y-m-d');
                $defaultContractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                
                function checkUnusualActivity($dailyLog) {
                    if (empty($dailyLog)) return false;
                    $log = json_decode($dailyLog, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($log)) return false;
                    foreach ($log as $dayData) {
                        if (isset($dayData['unusual_activity']) && $dayData['unusual_activity'] === true) {
                            return true;
                        }
                    }
                    return false;
                }
                
                function getUnusualSummary($dailyLog) {
                    if (empty($dailyLog)) return ['withdrawal_count' => 0, 'unauthorized_trade_count' => 0, 'unauthorized_balance' => 0];
                    $log = json_decode($dailyLog, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($log)) {
                        return ['withdrawal_count' => 0, 'unauthorized_trade_count' => 0, 'unauthorized_balance' => 0];
                    }
                    $withdrawalCount = 0;
                    $unauthorizedTradeCount = 0;
                    $unauthorizedBalance = 0;
                    foreach ($log as $dayData) {
                        if (isset($dayData['unusual_activity']) && $dayData['unusual_activity'] === true) {
                            if (isset($dayData['day_unauthorized_withdrawals']) && $dayData['day_unauthorized_withdrawals'] > 0) {
                                $withdrawalCount++;
                                $unauthorizedBalance += $dayData['day_unauthorized_withdrawals'];
                            }
                            $unauthorizedTradeCount += $dayData['unauthorized_trades_count'] ?? 0;
                        }
                    }
                    return [
                        'withdrawal_count' => $withdrawalCount,
                        'unauthorized_trade_count' => $unauthorizedTradeCount,
                        'unauthorized_balance' => $unauthorizedBalance
                    ];
                }

                function isUserActive($user, $contractDuration) {
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false;
                    }
                    
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false;
                    }
                    
                    $execDate = $user['execution_start_date'] ?? null;
                    if (empty($execDate) || $execDate === '0000-00-00' || $execDate === null) {
                        return false;
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime();
                            $todayObj->setTime(0, 0, 0);
                            
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            $isContractActive = false;
                        }
                    }
                    
                    if ($isContractActive) {
                        return true;
                    }
                    
                    $activeStatuses = ['payment-made', 'payment_made', 'unpaid-payment', 'unpaid_payment', 'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'];
                    
                    foreach ($activeStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return true;
                        }
                    }
                    
                    return false;
                }
                
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $sql = "SELECT id, fullname, email, broker, login, broker_balance, profitandloss, daily_balance_log, loyalties, execution_start_date, application_status, '{$harvhubTable}' as source FROM {$harvhubTable} WHERE execution_start_date IS NOT NULL AND execution_start_date != '0000-00-00' AND execution_start_date <= ?";
                        if (!empty($search)) {
                            $sql .= " AND (fullname LIKE ? OR email LIKE ? OR id LIKE ?)";
                        }
                        $sql .= " ORDER BY id DESC";
                        $stmt = $pdo->prepare($sql);
                        if (!empty($search)) {
                            $searchTerm = '%' . $search . '%';
                            $stmt->execute([$today, $searchTerm, $searchTerm, $searchTerm]);
                        } else {
                            $stmt->execute([$today]);
                        }
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($results as $user) {
                            // Get programme-specific duration
                            $progData = getProgrammeInvestorData($pdo, $user['id']);
                            $contractDuration = $progData['contract_duration'];
                            
                            if (isUserActive($user, $contractDuration) && checkUnusualActivity($user['daily_balance_log'] ?? '')) {
                                $summary = getUnusualSummary($user['daily_balance_log'] ?? '');
                                $user['withdrawal_count'] = $summary['withdrawal_count'];
                                $user['unauthorized_trade_count'] = $summary['unauthorized_trade_count'];
                                $user['unauthorized_balance'] = $summary['unauthorized_balance'];
                                $user['contract_duration'] = $contractDuration;
                                $user['developer_name'] = $progData['developer_name'];
                                $user['programme_name'] = $progData['programme_name'];
                                $user['developer_percentage'] = $progData['developer_percentage'];
                                $user['investor_percentage'] = $progData['investor_percentage'];
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_unusual_users: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // ============================================================
        // 5aa3: Get User Daily Balance Log + Daily Target (TABLE-BASED)
        //
        // Reads:
        //   balance_log           → { 'dd-mm-yyyy': {...} }
        //   daily_target_revenue  → { week_X: { Day: {...} } }
        //   harvhub               → basic user fields
        // ============================================================
        if ($action === 'get_user_daily_log') {
            $user_id      = (int)($_POST['user_id'] ?? 0);
            $source_table = $_POST['source_table'] ?? '';

            if ($user_id <= 0 || $source_table !== $harvhubTable) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }

            // Basic user info
            $userData = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT id, fullname, email, broker_balance, profitandloss
                    FROM {$source_table}
                    WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $userData = [
                        'id'             => $row['id'],
                        'fullname'       => $row['fullname'] ?? 'N/A',
                        'email'          => $row['email'] ?? 'N/A',
                        'broker_balance' => $row['broker_balance'] ?? 0,
                        'profitandloss'  => $row['profitandloss'] ?? 0,
                        'source'         => $source_table,
                    ];
                }
            } catch (Exception $e) {
                $userData = [];
            }

            // -------- Balance log (per-day) --------
            // Key by dd-mm-yyyy so the JS side can reuse the existing parser.
            $balanceLog = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT date, day_starting_balance, day_authorized_trades_pnl,
                        day_unauthorized_trades_pnl, day_unauthorized_withdrawals,
                        day_closing_balance, unusual_activity,
                        authorized_trades_count, unauthorized_trades_count
                    FROM balance_log
                    WHERE userid = ?
                    ORDER BY id ASC
                ");
                $stmt->execute([$user_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $raw = trim((string)$r['date']);
                    if ($raw === '') continue;

                    // Try ISO first (Y-m-d), then d-m-Y
                    $dt = DateTime::createFromFormat('Y-m-d', $raw);
                    if (!$dt || $dt->format('Y-m-d') !== $raw) {
                        $dt = DateTime::createFromFormat('d-m-Y', $raw);
                    }
                    if (!$dt) {
                        $ts = strtotime($raw);
                        if ($ts) $dt = new DateTime(date('Y-m-d', $ts));
                    }
                    if (!$dt) continue;

                    $key = $dt->format('d-m-Y');

                    $balanceLog[$key] = [
                        'date'                          => $key,
                        'day_starting_balance'          => (float)($r['day_starting_balance'] ?? 0),
                        'day_authorized_trades_pnl'     => (float)($r['day_authorized_trades_pnl'] ?? 0),
                        'day_unauthorized_trades_pnl'   => (float)($r['day_unauthorized_trades_pnl'] ?? 0),
                        'day_unauthorized_withdrawals'  => (float)($r['day_unauthorized_withdrawals'] ?? 0),
                        'day_closing_balance'           => (float)($r['day_closing_balance'] ?? 0),
                        'unusual_activity'              => ((int)$r['unusual_activity'] === 1),
                        'authorized_trades_count'       => (int)($r['authorized_trades_count'] ?? 0),
                        'unauthorized_trades_count'     => (int)($r['unauthorized_trades_count'] ?? 0),
                    ];
                }
            } catch (Exception $e) {
                $balanceLog = [];
            }

            // -------- Daily target (nested: { week_X: { Day: {...} } }) --------
            $dailyTarget = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT week, day, date, daily_target, status,
                        profit_allocated, remaining_needed
                    FROM daily_target_revenue
                    WHERE userid = ?
                    ORDER BY
                        CAST(REPLACE(week, 'week_', '') AS UNSIGNED) ASC,
                        FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') ASC
                ");
                $stmt->execute([$user_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $wk  = $r['week'];
                    $day = $r['day'];
                    if (!isset($dailyTarget[$wk])) $dailyTarget[$wk] = [];

                    $dailyTarget[$wk][$day] = [
                        'date'             => $r['date'],
                        'daily_target'     => (float)($r['daily_target'] ?? 0),
                        'status'           => $r['status'] ?? '',
                        'profit_allocated' => (float)($r['profit_allocated'] ?? 0),
                        'remaining_needed' => (float)($r['remaining_needed'] ?? 0),
                        'is_listed'        => true,
                    ];
                }
            } catch (Exception $e) {
                $dailyTarget = [];
            }

            echo json_encode([
                'success'      => true,
                'log'          => $dailyTarget,     // retained key name for compatibility
                'balance_log'  => $balanceLog,      // NEW: table-sourced daily balance log
                'daily_target' => $dailyTarget,     // kept for backwards compat
                'user'         => $userData,
            ]);
            exit;
        }

        // 5aa4: Get User Data (for detail settings_modal)
        if ($action === 'get_user_data') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || $source_table !== $harvhubTable) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT id, fullname, email, broker_balance, profitandloss, invested_with, execution_start_date FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'user' => $result ?: []
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5aa5: Update Payment Status - UPDATED for programme_investors
        if ($action === 'update_payment_status') {
            $user_id = $_POST['user_id'] ?? '';
            $new_status = trim($_POST['payment_status'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
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
            
            $normalizedStatus = normalizePaymentStatus($new_status);
            
            if (!empty($user_id) && !empty($new_status) && $source_table === $harvhubTable) {
                try {
                    $stmt = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
                    $stmt->execute([$normalizedStatus, $user_id]);
                    if ($normalizedStatus === 'payment-confirmed') {
                        $stmtReset = $pdo->prepare("UPDATE {$source_table} SET reset_contract = 1 WHERE id = ?");
                        $stmtReset->execute([$user_id]);
                    }
                    $syncResult = syncUserRevenueHistory($user_id, $source_table, $pdo, $serverAccount);
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } catch (Exception $e) {
                    echo json_encode(['error' => $e->getMessage()]);
                }
            } else {
                echo json_encode(['error' => 'Invalid request']);
            }
            exit;
        }

        // 5ab: Get Completed Investors with enhanced data - UPDATED for programme_investors
        if ($action === 'get_completed_investors') {
            try {
                $users = array();
                $defaultContractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, loyalties, invested_with, execution_start_date, 
                                profitandloss, broker_balance, 
                                broker, login,
                                '{$harvhubTable}' as source
                            FROM {$harvhubTable} 
                            ORDER BY id DESC
                        ");
                        $stmt->execute();
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            // Get programme-specific data
                            $progData = getProgrammeInvestorData($pdo, $user['id']);
                            
                            $history = [];
                            $hasHistory = false;
                            
                            $historyStmt = $pdo->prepare("
                                SELECT * FROM revenue_history 
                                WHERE user_email = ? 
                                ORDER BY created_at DESC
                            ");
                            $historyStmt->execute([$user['email']]);
                            $historyRecords = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($historyRecords)) {
                                $history = $historyRecords;
                                $hasHistory = true;
                            }
                            
                            $userData = [
                                'id' => $user['id'],
                                'source' => $user['source'],
                                'fullname' => $user['fullname'] ?? 'N/A',
                                'email' => $user['email'] ?? 'N/A',
                                'broker' => $user['broker'] ?? 'N/A',
                                'login' => $user['login'] ?? 'N/A',
                                'has_history' => $hasHistory,
                                'history_count' => $hasHistory ? count($history) : 0,
                                'current_loyalties' => $user['loyalties'] ?? null,
                                'payment_summary' => $hasHistory ? calculatePaymentSummaryFromHistory($history) : [
                                    'total_unpaid_revenue' => 0,
                                    'total_payment_made' => 0,
                                    'total_payment_confirmed' => 0,
                                    'total_cancelled_contracts' => 0,
                                    'total_failed_payments' => 0,
                                    'unpaid_count' => 0,
                                    'payment_made_count' => 0,
                                    'payment_confirmed_count' => 0,
                                    'cancelled_count' => 0,
                                    'failed_count' => 0
                                ],
                                'invested_with' => $user['invested_with'] ?? null,
                                'execution_start_date' => $user['execution_start_date'] ?? null,
                                'profitandloss' => (float)($user['profitandloss'] ?? 0),
                                'broker_balance' => (float)($user['broker_balance'] ?? 0),
                                'revenue_history' => $history,
                                'contract_duration' => $progData['contract_duration'],
                                'developer_name' => $progData['developer_name'],
                                'programme_name' => $progData['programme_name'],
                                'developer_percentage' => $progData['developer_percentage'],
                                'investor_percentage' => $progData['investor_percentage'],
                                'developerid' => $progData['developerid'],
                                'programme_id' => $progData['programme_id']
                            ];
                            
                            $users[] = $userData;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_completed_investors: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5ac: Get Revenue History for a specific user (UPDATED - Table Based)
        if ($action === 'get_revenue_history') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || $source_table !== $harvhubTable) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                // First get user email
                $stmt2 = $pdo->prepare("SELECT email, invested_with FROM {$source_table} WHERE id = ?");
                $stmt2->execute([$user_id]);
                $userInfo = $stmt2->fetch(PDO::FETCH_ASSOC);
                
                if (!$userInfo) {
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }
                
                $userEmail = $userInfo['email'];
                $userInvestedWith = $userInfo['invested_with'] ?? null;
                
                // Fetch from revenue_history table using user_email
                $stmt = $pdo->prepare("
                    SELECT * FROM revenue_history 
                    WHERE user_email = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$userEmail]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Update missing invested_with
                if (!empty($history) && $userInvestedWith) {
                    foreach ($history as $record) {
                        if (!isset($record['invested_with']) || empty($record['invested_with'])) {
                            $updateStmt = $pdo->prepare("
                                UPDATE revenue_history SET invested_with = ? 
                                WHERE user_email = ? AND contract_id = ?
                            ");
                            $updateStmt->execute([$userInvestedWith, $userEmail, $record['contract_id']]);
                        }
                    }
                    // Re-fetch after updates
                    $stmt = $pdo->prepare("
                        SELECT * FROM revenue_history 
                        WHERE user_email = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$userEmail]);
                    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            if (!empty($user_id) && $source_table === $harvhubTable) {
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
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$harvhubTable} LIKE 'unauthorized_actions'");
            $hasUnauthorizedColumn = $checkColumn->rowCount() > 0;
            
            if ($hasUnauthorizedColumn) {
                $stmt = $pdo->prepare("SELECT id, fullname, email, application_status, unauthorized_actions, '{$harvhubTable}' as source FROM {$harvhubTable} ORDER BY id DESC");
            } else {
                $stmt = $pdo->prepare("SELECT id, fullname, email, application_status, '' as unauthorized_actions, '{$harvhubTable}' as source FROM {$harvhubTable} ORDER BY id DESC");
            }
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                if (empty($user_id) || $source_table !== $harvhubTable) {
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
                if (!empty($user_id) && $source_table === $harvhubTable) {
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
                if (!empty($user_id) && $source_table === $harvhubTable) {
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
                
                // Check and get from harvhub table
                if (columnExists($pdo, $harvhubTable, 'invested_with')) {
                    $stmt = $pdo->prepare("SELECT id, fullname, email, invested_with, '{$harvhubTable}' as source FROM {$harvhubTable} ORDER BY id DESC");
                    $stmt->execute();
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    // Column doesn't exist, select without it and add null value
                    $stmt = $pdo->prepare("SELECT id, fullname, email, '{$harvhubTable}' as source FROM {$harvhubTable} ORDER BY id DESC");
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($results as &$row) {
                        $row['invested_with'] = null;
                    }
                    $users = $results;
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
            
            // Verify credentials (same as before)
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
            if (empty($user_id) || $source_table !== $harvhubTable) {
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
                
                // Only fetch from harvhub table (where column exists)
                $stmt = $pdo->prepare("SELECT id, executions_notification FROM {$harvhubTable} WHERE executions_notification IS NOT NULL AND executions_notification != ''");
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
                
                if (empty($user_id) || $source_table !== $harvhubTable) {
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
            
            if (empty($user_id) || $source_table !== $harvhubTable) {
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
            
            // Verify credentials
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
            if (empty($user_id) || $source_table !== $harvhubTable) {
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
            
            // Verify credentials
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
            if (empty($user_id) || $source_table !== $harvhubTable) {
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $checkColumn = $pdo->query("SHOW COLUMNS FROM {$harvhubTable} LIKE 'invested_with'");
                        if ($checkColumn->rowCount() > 0) {
                            $stmt = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                                FROM {$harvhubTable} 
                                WHERE invested_with IS NOT NULL 
                                AND invested_with != '' 
                                AND execution_start_date IS NOT NULL 
                                AND execution_start_date != '0000-00-00'
                                AND enable_autotrading = 1
                                AND broker_balance >= ?
                            ");
                            $stmt->execute([$minBrokerBalance]);
                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status = 'pending'
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status IN ('suspended', 'blacklisted')
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status = 'just-joined'
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status = 'just-joined-and-valid_credentials'
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, broker_balance, account_mode, demo_account, contract_days_left, terminal_path, '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status = 'approved'
                        ");
                        $stmt->execute();
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            // Verify credentials
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
            if (empty($user_id) || $source_table !== $harvhubTable) {
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
                
                // Get from harvhub table
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $checkColumn = $pdo->query("SHOW COLUMNS FROM {$harvhubTable} LIKE 'bypass_restriction'");
                        if ($checkColumn->rowCount() > 0) {
                            $stmt = $pdo->prepare("
                                SELECT id, fullname, email, broker, invested_with, execution_start_date, enable_autotrading, bypass_restriction, broker_balance, account_mode, demo_account, unauthorized_actions, '{$harvhubTable}' as source 
                                FROM {$harvhubTable} 
                                WHERE bypass_restriction = 1
                            ");
                            $stmt->execute();
                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            // Verify credentials
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
                
                // Get current configs data
                $stmt = $pdo->prepare("SELECT accountmanagement_configs, accountmanagement FROM {$updateTable} WHERE id = ?");
                $stmt->execute([$updateId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $configsData = !empty($result['accountmanagement_configs']) ? json_decode($result['accountmanagement_configs'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $configsData = [];
                }
                
                $managementData = !empty($result['accountmanagement']) ? json_decode($result['accountmanagement'], true) : [];
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $managementData = [];
                }
                
                // Update the specific entry in configs
                if ($value === null) {
                    // Delete the entry from both
                    unset($configsData[$entry_key]);
                    unset($managementData[$entry_key]);
                } else {
                    // Update or add the entry in both
                    $configsData[$entry_key] = $value;
                    $managementData[$entry_key] = $value;
                }
                
                // Save both columns
                $jsonConfigs = json_encode($configsData, JSON_PRETTY_PRINT);
                $jsonManagement = json_encode($managementData, JSON_PRETTY_PRINT);
                
                $stmt = $pdo->prepare("UPDATE {$updateTable} SET accountmanagement_configs = ?, accountmanagement = ? WHERE id = ?");
                $stmt->execute([$jsonConfigs, $jsonManagement, $updateId]);
                
                echo json_encode(['success' => true, 'data' => $configsData, 'synced_to_management' => true]);
            } else {
                echo json_encode(['error' => 'Invalid target type']);
            }
            exit;
        }
        // 5w2: Get Tier Limit Configuration
        if ($action === 'settings_get_tier_limit') {
            try {
                $stmt = $pdo->prepare("SELECT tier_limit FROM {$serverAccountTable} WHERE id = 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $tierData = [];
                if ($result && !empty($result['tier_limit'])) {
                    $decoded = json_decode($result['tier_limit'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $tierData = $decoded;
                    }
                }
                
                echo json_encode(['success' => true, 'tiers' => $tierData]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5w3: Save/Update Tier Limit Configuration
        if ($action === 'settings_save_tier_limit') {
            $tiers_json = $_POST['tiers'] ?? '{}';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            $tiers = json_decode($tiers_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($tiers)) {
                echo json_encode(['error' => 'Invalid JSON data']);
                exit;
            }
            
            // Verify credentials
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
                $jsonData = json_encode($tiers, JSON_PRETTY_PRINT);
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET tier_limit = ? WHERE id = 1");
                $stmt->execute([$jsonData]);
                
                echo json_encode(['success' => true, 'tiers' => $tiers]);
            } catch (Exception $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
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
        // 5y: Cancel Contract - UPDATED to use programme_investors
        if ($action === 'cancel_contract') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            if (empty($admin_password)) {
                echo json_encode(['error' => 'Password is required']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash, contract_duration, min_profit_for_split, server_share_percent, user_share_percent FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$adminData || 
                $login_id !== ($adminData['admin_login_id'] ?? '') || 
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }
            
            if (empty($user_id) || $source_table !== $harvhubTable) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
            $stmt->execute([$user_id]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                echo json_encode(['error' => 'User not found']);
                exit;
            }
            
            // Get programme-specific values
            $progData = getProgrammeInvestorData($pdo, $user_id);
            $contractDuration = $progData['contract_duration'];
            $serverSharePercent = $progData['developer_percentage'];
            $userSharePercent = $progData['investor_percentage'];
            
            $minProfitForSplit = (float)($adminData['min_profit_for_split'] ?? 30);
            $contractId = $userData['contract_id'] ?? null;
            
            $profitAndLoss = (float)($userData['profitandloss'] ?? 0);
            $brokerBalance = (float)($userData['broker_balance'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            $executionStartDate = $userData['execution_start_date'] ?? null;
            $userEmail = $userData['email'] ?? '';
            $investedWith = $userData['invested_with'] ?? null;
            
            $loyaltiesToSet = 'contract_cancelled';
            $isAboveThreshold = ($profitAndLoss > $minProfitForSplit);
            
            if ($isAboveThreshold) {
                $loyaltiesToSet = 'unpaid-payment';
            }
            
            $serverShare = 0;
            $userShare = 0;
            if ($isAboveThreshold) {
                $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            }
            
            $executionEndDate = null;
            if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00') {
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $executionEndDate = $end->format('Y-m-d');
            }
            
            if (empty($contractId) && !empty($executionStartDate) && !empty($executionEndDate)) {
                $startFormatted = date('dmY', strtotime($executionStartDate));
                $endFormatted = date('dmY', strtotime($executionEndDate));
                $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
            } elseif (empty($contractId)) {
                $now = new DateTime();
                $executionStartDate = $now->format('Y-m-d');
                $start = clone $now;
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $executionEndDate = $end->format('Y-m-d');
                $startFormatted = date('dmY', strtotime($executionStartDate));
                $endFormatted = date('dmY', strtotime($executionEndDate));
                $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
            }
            
            $checkStmt = $pdo->prepare("SELECT * FROM revenue_history WHERE user_email = ? AND contract_id = ?");
            $checkStmt->execute([$userEmail, $contractId]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                $updateStmt = $pdo->prepare("
                    UPDATE revenue_history SET 
                        loyalties = 'contract_cancelled',
                        profit = ?,
                        server_share = ?,
                        user_share = ?,
                        current_balance = ?,
                        updated_at = ?
                    WHERE user_email = ? AND contract_id = ?
                ");
                $updateStmt->execute([
                    $profitAndLoss,
                    $serverShare,
                    $userShare,
                    $currentBalance,
                    date('Y-m-d H:i:s'),
                    $userEmail,
                    $contractId
                ]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO revenue_history (
                        user_email, contract_id, execution_start_date, execution_end_date,
                        starting_balance, current_balance, profit, user_share, server_share,
                        loyalties, invested_with, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $userEmail,
                    $contractId,
                    $executionStartDate,
                    $executionEndDate,
                    $brokerBalance,
                    $currentBalance,
                    $profitAndLoss,
                    $userShare,
                    $serverShare,
                    'contract_cancelled',
                    $investedWith,
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s')
                ]);
            }
            
            $updateLoyalties = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
            $updateLoyalties->execute([$loyaltiesToSet, $user_id]);
            
            $updateReset = $pdo->prepare("UPDATE {$source_table} SET reset_contract = 1 WHERE id = ?");
            $updateReset->execute([$user_id]);
            
            $updateExecDate = $pdo->prepare("UPDATE {$source_table} SET execution_start_date = NULL, contract_id = NULL WHERE id = ?");
            $updateExecDate->execute([$user_id]);
            
            $updateCleanup = $pdo->prepare("UPDATE {$source_table} SET daily_balance_log = NULL, daily_target_met = NULL WHERE id = ?");
            $updateCleanup->execute([$user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => "Contract cancelled successfully. User moved to inactive tab.",
                'profit_recorded' => $profitAndLoss,
                'server_share_recorded' => $serverShare,
                'user_share_recorded' => $userShare,
                'loyalties_set_to' => $loyaltiesToSet,
                'history_loyalties_set_to' => 'contract_cancelled',
                'is_above_threshold' => $isAboveThreshold,
                'reset_contract_set_to' => 1,
                'execution_start_date_set_to' => null
            ]);
            exit;
        }
        // ============================================================
        // 5z: Get User Analytics Data — NEW (table-based)
        //
        // Reads:
        //   investors_analytics  (pre-aggregated row per userid)
        //   authorized_trades    (raw trades → calendar, symbols, all trades, streaks)
        //   unauthorized_trades  (same)
        //
        // Returns a structured JSON payload for analytics.php:
        //   {
        //     success: true,
        //     start_date, end_date,
        //     authorized:   { ...all summary fields + daily_trades_record + symbols + all_trades
        //                     + highest_sequential_losses_trades + highest_sequential_days_in_loss_days },
        //     unauthorized: { ...same shape... }
        //   }
        // ============================================================
        if ($action === 'get_user_analytics') {
            $user_id      = (int)($_POST['user_id'] ?? 0);
            $source_table = $_POST['source_table'] ?? '';

            if ($user_id <= 0 || $source_table !== $harvhubTable) {
                echo json_encode(['success' => false, 'error' => 'Invalid user selection']);
                exit;
            }

            try {
                // ----------------------------------------------------
                // 1. Read the aggregated row from investors_analytics
                // ----------------------------------------------------
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM investors_analytics
                    WHERE userid = ?
                    LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Even if there is no row, we still return an empty structure so the UI shows defaults.
                if (!$row) {
                    $row = [];
                }

                // ----------------------------------------------------
                // 2. Build the per-trade-type payloads
                // ----------------------------------------------------
                // Helper: given a raw-trades table, build a rich summary block.
                $buildBlock = function($tableName) use ($pdo, $user_id, $row) {
                    // ---- Pull all trades ordered by closed_time ----
                    $tStmt = $pdo->prepare("
                        SELECT id, userid, symbol, entry, stoploss, target, ticket, pnl, closed_time
                        FROM {$tableName}
                        WHERE userid = ?
                        ORDER BY closed_time ASC, id ASC
                    ");
                    $tStmt->execute([$user_id]);
                    $trades = $tStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Normalize numeric fields
                    foreach ($trades as &$t) {
                        $t['pnl'] = (float)($t['pnl'] ?? 0);
                    }
                    unset($t);

                    $totalTrades   = count($trades);
                    $totalPnl      = 0.0;
                    $profitTrades  = 0;
                    $lossTrades    = 0;
                    $profitAmount  = 0.0;
                    $lossAmount    = 0.0;
                    $highestLoss   = 0.0;

                    // Per-day aggregation
                    $dailyRecord = [];   // ['YYYY-MM-DD' => ['profit_and_loss'=>x,'trades_count'=>n,'trade_summary'=>[], 'all_trades'=>[]]]
                    $perWeek     = [];   // ['YYYY-Www' => count]

                    // Per-symbol aggregation
                    $symbols     = [];

                    // Sequential streaks
                    $runningLossStreakCount = 0;
                    $runningLossStreakSum   = 0.0;
                    $bestLossStreakCount    = 0;
                    $bestLossStreakSum      = 0.0;
                    $bestLossStreakTrades   = [];

                    $currentDayStreakDates  = [];
                    $bestDayStreakDates     = [];
                    $runningDayLossCount    = 0;
                    $runningDayLossSum      = 0.0;
                    $bestDayLossCount       = 0;
                    $bestDayLossSum         = 0.0;

                    foreach ($trades as $t) {
                        $pnl    = $t['pnl'];
                        $totalPnl += $pnl;

                        if ($pnl > 0) {
                            $profitTrades++;
                            $profitAmount += $pnl;
                            // Reset loss streak
                            $runningLossStreakCount = 0;
                            $runningLossStreakSum   = 0.0;
                        } elseif ($pnl < 0) {
                            $lossTrades++;
                            $lossAmount += abs($pnl);
                            if (abs($pnl) > $highestLoss) $highestLoss = abs($pnl);

                            // Track sequential loss streak
                            $runningLossStreakCount++;
                            $runningLossStreakSum += abs($pnl);
                            if ($runningLossStreakCount > $bestLossStreakCount) {
                                $bestLossStreakCount  = $runningLossStreakCount;
                                $bestLossStreakSum    = $runningLossStreakSum;
                                // capture trades for that streak (last N trades)
                                $bestLossStreakTrades = array_slice($trades, max(0, count($trades) - $runningLossStreakCount - 1));
                            }
                        }

                        // ---- Symbols ----
                        $sym = $t['symbol'] ?: 'UNKNOWN';
                        if (!isset($symbols[$sym])) {
                            $symbols[$sym] = [
                                'symbol'       => $sym,
                                'total_trades' => 0,
                                'total_profit' => 0.0,
                                'total_loss'   => 0.0,
                            ];
                        }
                        $symbols[$sym]['total_trades']++;
                        if ($pnl > 0) $symbols[$sym]['total_profit'] += $pnl;
                        if ($pnl < 0) $symbols[$sym]['total_loss']   += abs($pnl);

                        // ---- Daily ----
                        $dateKey = $t['closed_time'] ? substr($t['closed_time'], 0, 10) : null;
                        if ($dateKey) {
                            if (!isset($dailyRecord[$dateKey])) {
                                $dailyRecord[$dateKey] = [
                                    'profit_and_loss' => 0.0,
                                    'trades_count'    => 0,
                                    'trade_summary'   => [],
                                    'all_trades'      => [],
                                ];
                            }
                            $dailyRecord[$dateKey]['profit_and_loss'] += $pnl;
                            $dailyRecord[$dateKey]['trades_count']++;
                            if (!isset($dailyRecord[$dateKey]['trade_summary'][$sym])) {
                                $dailyRecord[$dateKey]['trade_summary'][$sym] = 0.0;
                            }
                            $dailyRecord[$dateKey]['trade_summary'][$sym] += $pnl;

                            if (!isset($dailyRecord[$dateKey]['all_trades'][$sym])) {
                                $dailyRecord[$dateKey]['all_trades'][$sym] = [];
                            }
                            $dailyRecord[$dateKey]['all_trades'][$sym][] = [
                                'ticket'      => $t['ticket'],
                                'symbol'      => $sym,
                                'entry'       => $t['entry'],
                                'stoploss'    => $t['stoploss'],
                                'target'      => $t['target'],
                                'pnl'         => $pnl,
                                'closed_time' => $t['closed_time'],
                            ];

                            // ISO week key for weekly aggregation
                            $ts   = strtotime($dateKey);
                            $week = date('o-\WW', $ts);   // e.g. 2025-W03
                            if (!isset($perWeek[$week])) $perWeek[$week] = 0;
                            $perWeek[$week]++;
                        }
                    }

                    // ---- Sequential days in loss ----
                    ksort($dailyRecord);
                    $prevWasLossDay = false;
                    foreach ($dailyRecord as $date => $day) {
                        if ($day['profit_and_loss'] < 0) {
                            if (!$prevWasLossDay) {
                                $runningDayLossCount = 0;
                                $runningDayLossSum   = 0.0;
                                $currentDayStreakDates = [];
                            }
                            $runningDayLossCount++;
                            $runningDayLossSum += abs($day['profit_and_loss']);
                            $currentDayStreakDates[] = $date;

                            if ($runningDayLossCount > $bestDayLossCount) {
                                $bestDayLossCount = $runningDayLossCount;
                                $bestDayLossSum   = $runningDayLossSum;
                                $bestDayStreakDates = $currentDayStreakDates;
                            }
                            $prevWasLossDay = true;
                        } else {
                            $prevWasLossDay = false;
                        }
                    }

                    // Build the map of day => trades for the best day-streak modal
                    $bestDayStreakDays = [];
                    foreach ($bestDayStreakDates as $d) {
                        $bestDayStreakDays[$d] = $dailyRecord[$d]['all_trades'] ?? [];
                    }

                    // Recompute best streak trades properly (walk forward)
                    $bestLossStreakTrades = [];
                    $runCount = 0; $runSum = 0.0; $runTrades = [];
                    foreach ($trades as $t) {
                        if (($t['pnl'] ?? 0) < 0) {
                            $runCount++;
                            $runSum += abs($t['pnl']);
                            $runTrades[] = $t;
                            if ($runCount > $bestLossStreakCount || ($runCount === $bestLossStreakCount && $runSum > $bestLossStreakSum)) {
                                // If first time reaching this count in this loop, capture
                                if (count($bestLossStreakTrades) < $runCount) {
                                    $bestLossStreakTrades = $runTrades;
                                }
                            }
                        } else {
                            // finalize a streak before reset
                            if ($runCount > $bestLossStreakCount ||
                            ($runCount === $bestLossStreakCount && $runSum > $bestLossStreakSum)) {
                                $bestLossStreakCount = $runCount;
                                $bestLossStreakSum   = $runSum;
                                $bestLossStreakTrades = $runTrades;
                            }
                            $runCount = 0; $runSum = 0.0; $runTrades = [];
                        }
                    }
                    if ($runCount > $bestLossStreakCount ||
                    ($runCount === $bestLossStreakCount && $runSum > $bestLossStreakSum)) {
                        $bestLossStreakCount = $runCount;
                        $bestLossStreakSum   = $runSum;
                        $bestLossStreakTrades = $runTrades;
                    }

                    // ---- Weekly min/max/avg ----
                    $weeklyCounts = array_values($perWeek);
                    $lowestWeek  = $weeklyCounts ? min($weeklyCounts) : 0;
                    $highestWeek = $weeklyCounts ? max($weeklyCounts) : 0;
                    $avgWeek     = $weeklyCounts ? (int)round(array_sum($weeklyCounts) / count($weeklyCounts)) : 0;

                    // ---- Daily min/max/avg ----
                    $dailyCounts = array_map(fn($d) => $d['trades_count'], array_values($dailyRecord));
                    $lowestDay  = $dailyCounts ? min($dailyCounts) : 0;
                    $highestDay = $dailyCounts ? max($dailyCounts) : 0;
                    $avgDay     = $dailyCounts ? (int)round(array_sum($dailyCounts) / count($dailyCounts)) : 0;

                    // ---- Revenue % ----
                    $revenuePercentage       = 0.0;
                    $revenueProfitPercentage = 0.0;
                    $revenueLossPercentage   = 0.0;
                    if ($profitAmount + $lossAmount > 0) {
                        $revenueProfitPercentage = round(($profitAmount / ($profitAmount + $lossAmount)) * 100, 2);
                        $revenueLossPercentage   = round(($lossAmount   / ($profitAmount + $lossAmount)) * 100, 2);
                    }
                    if ($profitAmount > 0) {
                        $revenuePercentage = round((($profitAmount - $lossAmount) / $profitAmount) * 100, 2);
                    }

                    return [
                        // Totals
                        'total_trades'    => $totalTrades,
                        'total_pnl'       => $totalPnl,
                        'profit_trades'   => $profitTrades,
                        'loss_trades'     => $lossTrades,
                        'profit_amount'   => $profitAmount,
                        'loss_amount'     => $lossAmount,

                        // Daily / weekly trade density
                        'lowest_trades_per_day'    => $lowestDay,
                        'highest_trades_per_day'   => $highestDay,
                        'average_trades_per_day'   => $avgDay,
                        'lowest_trades_per_week'   => $lowestWeek,
                        'highest_trades_per_week'  => $highestWeek,
                        'average_trades_per_week'  => $avgWeek,

                        // Risk metrics
                        'highest_loss_per_trade'   => $highestLoss,
                        'highest_drawdown'         => 0,  // computed from row below
                        'symbols_traded'           => count($symbols),
                        'closed_deals_with_sl_tp'  => 0,
                        'closed_deals_without_sl_tp' => 0,

                        // Streaks
                        'consecutive_losses_count'   => $bestLossStreakCount,
                        'total_loss_pnl'             => $bestLossStreakSum,
                        'highest_sequential_losses_trades' => array_map(function($t){
                            return [
                                'ticket'      => $t['ticket'],
                                'symbol'      => $t['symbol'],
                                'entry'       => $t['entry'],
                                'stoploss'    => $t['stoploss'],
                                'target'      => $t['target'],
                                'pnl'         => (float)$t['pnl'],
                                'closed_time' => $t['closed_time'],
                            ];
                        }, $bestLossStreakTrades),

                        'consecutive_days_in_loss_count' => $bestDayLossCount,
                        'consecutive_days_in_loss_count_total_loss_pnl' => $bestDayLossSum,
                        'highest_sequential_days_in_loss_days' => $bestDayStreakDays,

                        // Revenue
                        'revenue_percentage'         => $revenuePercentage,
                        'revenue_profit_percentage'  => $revenueProfitPercentage,
                        'revenue_loss_percentage'    => $revenueLossPercentage,

                        // Calendar / tables
                        'daily_trades_record' => $dailyRecord,
                        'symbols'             => $symbols,
                        'all_trades'          => array_map(function($t){
                            return [
                                'ticket'      => $t['ticket'],
                                'symbol'      => $t['symbol'],
                                'entry'       => $t['entry'],
                                'stoploss'    => $t['stoploss'],
                                'target'      => $t['target'],
                                'pnl'         => (float)$t['pnl'],
                                'closed_time' => $t['closed_time'],
                            ];
                        }, $trades),
                    ];
                };

                $authorizedBlock   = $buildBlock('authorized_trades');
                $unauthorizedBlock = $buildBlock('unauthorized_trades');

                // ----------------------------------------------------
                // 3. Merge in the pre-aggregated investors_analytics row
                //    (this is the authoritative source for the summary cards)
                // ----------------------------------------------------
                $map = [
                    'total_trades'                   => 'total_trades',
                    'total_pnl'                      => 'total_pnl',
                    'profit_trades'                  => 'profit_trades',
                    'loss_trades'                    => 'loss_trades',
                    'profit_amount'                  => 'profit_amount',
                    'loss_amount'                    => 'loss_amount',
                    'lowest_trades_per_day'          => 'lowest_trades_per_day',
                    'highest_trades_per_day'         => 'highest_trades_per_day',
                    'average_trades_per_day'         => 'average_trades_per_day',
                    'lowest_trades_per_week'         => 'lowest_trades_per_week',
                    'highest_trades_per_week'        => 'highest_trades_per_week',
                    'average_trades_per_week'        => 'average_trades_per_week',
                    'highest_loss_per_trade'         => 'highest_loss_per_trade',
                    'highest_drawdown'               => 'highest_drawdown',
                    'symbols_traded'                 => 'symbols_traded',
                    'closed_deals_with_sl_tp'        => 'closed_deals_with_sl_tp',
                    'closed_deals_without_sl_tp'     => 'closed_deals_without_sl_tp',
                    'consecutive_losses_count'       => 'consecutive_losses_count',
                    'total_loss_pnl'                 => 'total_loss_pnl',
                    'consecutive_days_in_loss_count' => 'consecutive_days_in_loss_count',
                    'consecutive_days_in_loss_count_total_loss_pnl' => 'consecutive_days_in_loss_count_total_loss_pnl',
                    'revenue_percentage'             => 'revenue_percentage',
                    'revenue_profit_percentage'      => 'revenue_profit_percentage',
                    'revenue_loss_percentage'        => 'revenue_loss_percentage',
                ];

                // investors_analytics holds one row per user; it doesn't distinguish auth/unauth.
                // So we apply the row values to BOTH blocks only where the row has a value.
                $applyRow = function(&$block, $row, $map) {
                    foreach ($map as $jsonKey => $col) {
                        if (array_key_exists($col, $row) && $row[$col] !== null && $row[$col] !== '') {
                            $block[$jsonKey] = (strpos($col, 'percentage') !== false)
                                ? (float)$row[$col]
                                : (is_numeric($row[$col]) ? (float)$row[$col] : $row[$col]);
                        }
                    }
                };
                $applyRow($authorizedBlock,   $row, $map);
                $applyRow($unauthorizedBlock, $row, $map);

                // ----------------------------------------------------
                // 4. Assemble payload
                // ----------------------------------------------------
                $payload = [
                    'success'    => true,
                    'user_id'    => $user_id,
                    'start_date' => $row['start_date'] ?? null,
                    'end_date'   => $row['end_date']   ?? null,
                    'last_updated' => $row['last_updated'] ?? null,
                    'authorized'   => $authorizedBlock,
                    'unauthorized' => $unauthorizedBlock,
                ];

                echo json_encode($payload);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
                
        // Add this AJAX endpoint to get investor details including revenue history (add to SECTION 5)
        if ($action === 'get_investor_details') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && $source_table === $harvhubTable) {
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
                    
                    // Add invested_with to history records if missing
                    if (is_array($history) && !empty($history) && isset($user['invested_with'])) {
                        $needsUpdate = false;
                        foreach ($history as &$record) {
                            if (!isset($record['invested_with'])) {
                                $record['invested_with'] = $user['invested_with'];
                                $needsUpdate = true;
                            }
                        }
                        if ($needsUpdate) {
                            $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
                            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
                            $updateStmt->execute([$jsonHistory, $user_id]);
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
                            'revenue_history' => $history,
                            'invested_with' => $user['invested_with'] ?? null
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

        if ($action === 'settings_update_payment') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET btc_address = ?, eth_address = ?, eth_network = ?, usdt_address = ?, usdt_network = ? WHERE id = 1");
            $stmt->execute([
                trim($_POST['btc_address'] ?? ''),
                trim($_POST['eth_address'] ?? ''),
                trim($_POST['eth_network'] ?? 'ERC20'),
                trim($_POST['usdt_address'] ?? ''),
                trim($_POST['usdt_network'] ?? 'TRC20')
            ]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'settings_get_brokers') {
            $stmt = $pdo->prepare("SELECT brokers, brokers_link FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'brokers' => $row['brokers'] ?? '',
                'brokers_link' => $row['brokers_link'] ?? ''
            ]);
            exit;
        }

        // ============================================
        // SAVE BROKER (with duplicate check)
        // ============================================
        if ($action === 'settings_save_broker') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $broker = trim($_POST['broker'] ?? '');
            $broker_link = trim($_POST['broker_link'] ?? '');

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash, brokers, brokers_link FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            // Validate presence
            if ($broker === '' || $broker_link === '') {
                echo json_encode(['error' => 'Both broker name and link are required']);
                exit;
            }

            // Normalizer for matching broker name inside URL
            $norm = function($s) {
                $s = strtolower($s);
                $s = preg_replace('#^https?://#', '', $s);
                $s = preg_replace('#^www\.#', '', $s);
                $s = preg_replace('/[^a-z0-9]/', '', $s);
                return $s;
            };

            // Broker name must contain letters/numbers
            if ($norm($broker) === '') {
                echo json_encode(['error' => 'Broker name must contain letters or numbers']);
                exit;
            }

            // URL must contain the broker name
            if (strpos($norm($broker_link), $norm($broker)) === false) {
                echo json_encode(['error' => 'URL does not match broker name']);
                exit;
            }

            // Build existing lists
            $existingBrokers = array_values(array_filter(array_map('trim', explode(',', $adminData['brokers'] ?? ''))));
            $existingLinks   = array_values(array_filter(array_map('trim', explode(',', $adminData['brokers_link'] ?? ''))));

            // Duplicate broker name check (case-insensitive)
            foreach ($existingBrokers as $b) {
                if (strcasecmp($b, $broker) === 0) {
                    echo json_encode(['error' => 'Broker already exists']);
                    exit;
                }
            }

            // Add new broker + link
            $existingBrokers[] = $broker;

            $linkExists = false;
            foreach ($existingLinks as $l) {
                if (strcasecmp($l, $broker_link) === 0) { $linkExists = true; break; }
            }
            if (!$linkExists) {
                $existingLinks[] = $broker_link;
            }

            $newBrokers = implode(',', $existingBrokers);
            $newLinks   = implode(',', $existingLinks);

            $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ?, brokers_link = ? WHERE id = 1");
            $stmt->execute([$newBrokers, $newLinks]);

            echo json_encode([
                'success' => true,
                'brokers' => $newBrokers,
                'brokers_link' => $newLinks
            ]);
            exit;
        }

        // ============================================
        // DELETE BROKER (removes broker name + its matched link)
        // ============================================
        if ($action === 'settings_delete_broker') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $broker = trim($_POST['broker'] ?? '');
            $broker_link = trim($_POST['broker_link'] ?? '');

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash, brokers, brokers_link FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            if ($broker === '' && $broker_link === '') {
                echo json_encode(['error' => 'Nothing to delete']);
                exit;
            }

            // Build current lists
            $existingBrokers = array_values(array_filter(array_map('trim', explode(',', $adminData['brokers'] ?? ''))));
            $existingLinks   = array_values(array_filter(array_map('trim', explode(',', $adminData['brokers_link'] ?? ''))));

            $removedBroker = false;
            $removedLink   = false;

            // Remove the broker name (exact, case-insensitive match)
            if ($broker !== '') {
                $filteredBrokers = [];
                foreach ($existingBrokers as $b) {
                    if (strcasecmp($b, $broker) === 0) {
                        $removedBroker = true;
                        continue;
                    }
                    $filteredBrokers[] = $b;
                }
                $existingBrokers = $filteredBrokers;
            }

            // Remove the link (exact, case-insensitive match)
            if ($broker_link !== '') {
                $filteredLinks = [];
                foreach ($existingLinks as $l) {
                    if (strcasecmp($l, $broker_link) === 0) {
                        $removedLink = true;
                        continue;
                    }
                    $filteredLinks[] = $l;
                }
                $existingLinks = $filteredLinks;
            }

            if (!$removedBroker && !$removedLink) {
                echo json_encode(['error' => 'Broker or link not found']);
                exit;
            }

            $newBrokers = implode(',', $existingBrokers);
            $newLinks   = implode(',', $existingLinks);

            $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET brokers = ?, brokers_link = ? WHERE id = 1");
            $stmt->execute([$newBrokers, $newLinks]);

            echo json_encode([
                'success' => true,
                'brokers' => $newBrokers,
                'brokers_link' => $newLinks
            ]);
            exit;
        }

        if ($action === 'settings_update_requirements') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            // Minimum deposit and min broker balance must share the same value
            $minDeposit = (float)($_POST['minimum_deposit'] ?? 0);
            $minBrokerBalance = (float)($_POST['min_broker_balance'] ?? 0);
            $shared = max($minDeposit, $minBrokerBalance);

            $stmt = $pdo->prepare("
                UPDATE {$serverAccountTable} SET
                    minimum_deposit = ?,
                    min_broker_balance = ?,
                    contract_duration = ?,
                    server_share_percent = ?,
                    user_share_percent = ?,
                    min_profit_for_split = ?,
                    expiry_threshold_days = ?
                WHERE id = 1
            ");
            $stmt->execute([
                $shared,
                $shared,
                (int)($_POST['contract_duration'] ?? 0),
                (int)($_POST['server_share_percent'] ?? 30),
                (int)($_POST['user_share_percent'] ?? 70),
                (float)($_POST['min_profit_for_split'] ?? 30),
                (int)($_POST['expiry_threshold_days'] ?? 5)
            ]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'settings_update_mailer') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET mailer_email = ?, mailer_password = ? WHERE id = 1");
            $stmt->execute([
                trim($_POST['mailer_email'] ?? ''),
                $_POST['mailer_password'] ?? ''
            ]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'settings_update_credentials') {
            $currentPassword = $_POST['admin_password_current'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $newLoginId = trim($_POST['admin_login_id'] ?? '');
            $newPassword = $_POST['admin_password'] ?? '';

            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData || $login_id !== ($adminData['admin_login_id'] ?? '') ||
                !password_verify($currentPassword, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['error' => 'Invalid password']);
                exit;
            }

            if ($newLoginId === '') {
                echo json_encode(['error' => 'Admin Login ID is required']);
                exit;
            }

            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET admin_login_id = ?, admin_password_hash = ? WHERE id = 1");
                $stmt->execute([$newLoginId, $hash]);
            } else {
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET admin_login_id = ? WHERE id = 1");
                $stmt->execute([$newLoginId]);
            }

            echo json_encode(['success' => true]);
            exit;
        }
        // ============================================================
        // 5z5: VPS MANAGEMENT — NEW SYSTEM (per-user VPS rows)
        // These handlers back the new vps_config.php admin page.
        // The old `system_server_config` JSON column is no longer used.
        // ============================================================

        // 5z5a: Get ALL users (for "Register VPS for Users" tab)
        if ($action === 'vps_get_all_users') {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, fullname, email
                    FROM {$harvhubTable}
                    ORDER BY fullname ASC
                ");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $withVps = [];
                try {
                    $vstmt = $pdo->query("SELECT user_id FROM vps");
                    while ($r = $vstmt->fetch(PDO::FETCH_ASSOC)) {
                        $withVps[(int)$r['user_id']] = true;
                    }
                } catch (Exception $e) {}

                foreach ($users as &$u) {
                    $u['has_vps'] = isset($withVps[(int)$u['id']]);
                }
                unset($u);

                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5b: Get the VPS row for a single user (for edit form)
        if ($action === 'vps_get_user_vps') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }

            try {
                $ustmt = $pdo->prepare("SELECT id, fullname, email FROM {$harvhubTable} WHERE id = ?");
                $ustmt->execute([$user_id]);
                $userRow = $ustmt->fetch(PDO::FETCH_ASSOC);
                if (!$userRow) {
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }

                $vstmt = $pdo->prepare("SELECT * FROM vps WHERE user_id = ? LIMIT 1");
                $vstmt->execute([$user_id]);
                $vpsRow = $vstmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'user'    => $userRow,
                    'vps'     => $vpsRow ?: null
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5c: Save / Update VPS for a user
        if ($action === 'vps_save_user_vps') {
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id       = $_POST['login_id'] ?? '';
            $user_id        = (int)($_POST['user_id'] ?? 0);

            // Re-verify credentials
            $astmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $astmt->execute();
            $adminData = $astmt->fetch(PDO::FETCH_ASSOC);

            if (!$adminData
                || $login_id !== ($adminData['admin_login_id'] ?? '')
                || !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['success' => false, 'error' => 'Invalid password']);
                exit;
            }

            if ($user_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }

            $server_location        = trim($_POST['server_location'] ?? '');
            $subscription_duration  = (int)($_POST['subscription_duration'] ?? 30);
            $subscription_start     = trim($_POST['subscription_start_date'] ?? '');
            $vps_ip_address         = trim($_POST['vps_ip_address'] ?? '');
            $vps_provider_login     = trim($_POST['vps_provider_login'] ?? '');
            $vps_provider_password  = (string)($_POST['vps_provider_password'] ?? '');
            $computer_username      = trim($_POST['computer_username'] ?? '');
            $computer_password      = (string)($_POST['computer_password'] ?? '');
            $rdp_password           = (string)($_POST['rdp_password'] ?? '');
            $visibility             = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

            if ($server_location === '') {
                echo json_encode(['success' => false, 'error' => 'Server location is required']);
                exit;
            }

            if ($subscription_start === '' || $subscription_start === '0000-00-00') {
                $subscription_start = null;
            } else {
                $ts = strtotime($subscription_start);
                $subscription_start = $ts ? date('Y-m-d', $ts) : null;
            }

            try {
                $check = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? LIMIT 1");
                $check->execute([$user_id]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $upd = $pdo->prepare("
                        UPDATE vps SET
                            server_location = ?,
                            subscription_duration = ?,
                            subscription_start_date = ?,
                            vps_ip_address = ?,
                            vps_provider_login = ?,
                            vps_provider_password = ?,
                            computer_username = ?,
                            computer_password = ?,
                            rdp_password = ?,
                            visibility = ?
                        WHERE user_id = ?
                    ");
                    $upd->execute([
                        $server_location,
                        $subscription_duration,
                        $subscription_start,
                        $vps_ip_address,
                        $vps_provider_login,
                        $vps_provider_password,
                        $computer_username,
                        $computer_password,
                        $rdp_password,
                        $visibility,
                        $user_id
                    ]);
                    echo json_encode(['success' => true, 'message' => 'VPS updated']);
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO vps
                            (user_id, server_location, subscription_duration, subscription_start_date,
                            vps_ip_address, vps_provider_login, vps_provider_password,
                            computer_username, computer_password, rdp_password, visibility)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([
                        $user_id,
                        $server_location,
                        $subscription_duration,
                        $subscription_start,
                        $vps_ip_address,
                        $vps_provider_login,
                        $vps_provider_password,
                        $computer_username,
                        $computer_password,
                        $rdp_password,
                        $visibility
                    ]);
                    echo json_encode(['success' => true, 'message' => 'VPS registered']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5d: List all users with VPS
        if ($action === 'vps_list_with_vps') {
            try {
                $stmt = $pdo->query("
                    SELECT v.*, h.fullname, h.email
                    FROM vps v
                    INNER JOIN {$harvhubTable} h ON h.id = v.user_id
                    ORDER BY h.fullname ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $fCounts = [];
                $rCounts = [];

                try {
                    $fs = $pdo->query("SELECT owner_id, COUNT(*) AS c FROM vps_hosts_followers GROUP BY owner_id");
                    while ($r = $fs->fetch(PDO::FETCH_ASSOC)) {
                        $fCounts[(int)$r['owner_id']] = (int)$r['c'];
                    }
                } catch (Exception $e) {}

                try {
                    $rs = $pdo->query("SELECT owner_id, COUNT(*) AS c FROM vps_hosts_requestors GROUP BY owner_id");
                    while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
                        $rCounts[(int)$r['owner_id']] = (int)$r['c'];
                    }
                } catch (Exception $e) {}

                foreach ($rows as &$r) {
                    $r['follower_count'] = $fCounts[(int)$r['user_id']] ?? 0;
                    $r['requestor_count'] = $rCounts[(int)$r['user_id']] ?? 0;
                }
                unset($r);

                echo json_encode(['success' => true, 'users' => $rows]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5e: Get VPS details for a specific user
        if ($action === 'vps_get_details') {
            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid user']);
                exit;
            }

            try {
                $ustmt = $pdo->prepare("SELECT id, fullname, email FROM {$harvhubTable} WHERE id = ?");
                $ustmt->execute([$user_id]);
                $userRow = $ustmt->fetch(PDO::FETCH_ASSOC);
                if (!$userRow) {
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                    exit;
                }

                $vstmt = $pdo->prepare("SELECT * FROM vps WHERE user_id = ? LIMIT 1");
                $vstmt->execute([$user_id]);
                $vpsRow = $vstmt->fetch(PDO::FETCH_ASSOC);

                if (!$vpsRow) {
                    echo json_encode(['success' => false, 'error' => 'User has no VPS']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'user' => $userRow,
                    'vps'  => $vpsRow
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5f: Get VPS followers for a given owner
        if ($action === 'vps_get_followers') {
            $owner_id = (int)($_POST['owner_id'] ?? 0);
            if ($owner_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid owner']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT f.id, f.follower_id, f.host_status, f.created_at,
                        h.fullname, h.email
                    FROM vps_hosts_followers f
                    LEFT JOIN {$harvhubTable} h ON h.id = f.follower_id
                    WHERE f.owner_id = ?
                    ORDER BY f.created_at DESC
                ");
                $stmt->execute([$owner_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'followers' => $rows]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5g: Get VPS requestors for a given owner
        if ($action === 'vps_get_requestors') {
            $owner_id = (int)($_POST['owner_id'] ?? 0);
            if ($owner_id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid owner']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT r.id, r.requestor_id, r.request_status, r.created_at,
                        h.fullname, h.email
                    FROM vps_hosts_requestors r
                    LEFT JOIN {$harvhubTable} h ON h.id = r.requestor_id
                    WHERE r.owner_id = ?
                    ORDER BY r.created_at DESC
                ");
                $stmt->execute([$owner_id]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'requestors' => $rows]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5z5h: List users without VPS
        if ($action === 'vps_list_without_vps') {
            try {
                $stmt = $pdo->query("
                    SELECT h.id, h.fullname, h.email
                    FROM {$harvhubTable} h
                    LEFT JOIN vps v ON v.user_id = h.id
                    WHERE v.id IS NULL
                    ORDER BY h.fullname ASC
                ");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'users' => $rows]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5z8: Get Inactive Users - UPDATED for programme_investors
        if ($action === 'get_inactive_users') {
            try {
                $users = array();
                $defaultContractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $today = date('Y-m-d');
                $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);

                function isUserInactive($user, $contractDuration, $today, $minProfitForSplit) {
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false;
                    }
                    
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false;
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    $profitAndLoss = (float)($user['profitandloss'] ?? 0);
                    
                    if (strpos($loyalties, 'cancelled') !== false) {
                        if ($profitAndLoss > $minProfitForSplit) {
                            return false;
                        }
                        return true;
                    }
                    
                    $paymentStatuses = [
                        'payment-made', 'payment_made',
                        'unpaid-payment', 'unpaid_payment', 'unpaid',
                        'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'
                    ];
                    
                    foreach ($paymentStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return false;
                        }
                    }
                    
                    $execDate = $user['execution_start_date'] ?? null;
                    if (empty($execDate) || $execDate === '0000-00-00' || $execDate === null) {
                        return true;
                    }
                    
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime($today);
                            $todayObj->setTime(0, 0, 0);
                            
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            $isContractActive = false;
                        }
                    }
                    
                    if ($isContractActive) {
                        return false;
                    }
                    
                    return true;
                }

                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$harvhubTable}'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, broker_balance, profitandloss, 
                                loyalties, execution_start_date, invested_with, application_status,
                                '{$harvhubTable}' as source 
                            FROM {$harvhubTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt->execute();
                        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            // Get programme-specific duration
                            $progData = getProgrammeInvestorData($pdo, $user['id']);
                            $contractDuration = $progData['contract_duration'];
                            
                            if (isUserInactive($user, $contractDuration, $today, $minProfitForSplit)) {
                                $user['contract_duration'] = $contractDuration;
                                $user['developer_name'] = $progData['developer_name'];
                                $user['programme_name'] = $progData['programme_name'];
                                $user['developer_percentage'] = $progData['developer_percentage'];
                                $user['investor_percentage'] = $progData['investor_percentage'];
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_inactive_users: " . $e->getMessage());
                }

                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
        // 5z8: Get User Details by IDs
        if ($action === 'get_users_by_ids') {
            $user_ids = json_decode($_POST['user_ids'] ?? '[]', true);
            $source_table = $_POST['source_table'] ?? $harvhubTable;
            
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
        // 5z9: Initialize Enrollment - UPDATED to use programme_investors
        if ($action === 'initialize_enrollment') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $broker_balance = (float)($_POST['broker_balance'] ?? 0);
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $minBrokerBalance = (float)($serverAccount['min_broker_balance'] ?? 30);
            
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
            
            if (empty($user_id) || $source_table !== $harvhubTable) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            if ($broker_balance < $minBrokerBalance) {
                echo json_encode(['error' => 'Broker balance must be at least $' . number_format($minBrokerBalance, 2)]);
                exit;
            }
            
            $checkUser = $pdo->prepare("SELECT id, fullname, email, invested_with FROM {$source_table} WHERE id = ?");
            $checkUser->execute([$user_id]);
            $userData = $checkUser->fetch(PDO::FETCH_ASSOC);
            if (!$userData) {
                echo json_encode(['error' => 'User does not exist']);
                exit;
            }
            
            // Get programme-specific contract duration
            $progData = getProgrammeInvestorData($pdo, $user_id);
            $contractDuration = $progData['contract_duration'];
            
            $today = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$contractDuration} days", strtotime($today)));
            $startFormatted = date('dmY', strtotime($today));
            $endFormatted = date('dmY', strtotime($endDate));
            $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
            
            $updateStmt = $pdo->prepare("
                UPDATE {$source_table} SET 
                    broker_balance = ?,
                    balance_verification = 'verified',
                    loyalties = NULL,
                    execution_start_date = ?,
                    profitandloss = 0,
                    reset_contract = 0,
                    contract_id = ?,
                    daily_balance_log = NULL,
                    daily_target_met = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$broker_balance, $today, $contractId, $user_id]);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO revenue_history (
                    user_email, contract_id, execution_start_date, execution_end_date,
                    starting_balance, current_balance, profit, user_share, server_share,
                    loyalties, invested_with, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $userData['email'] ?? '',
                $contractId,
                $today,
                $endDate,
                $broker_balance,
                $broker_balance,
                0,
                0,
                0,
                'active',
                $userData['invested_with'] ?? null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Enrollment initialized successfully',
                'contract_id' => $contractId,
                'execution_start_date' => $today,
                'broker_balance' => $broker_balance,
                'contract_duration' => $contractDuration,
                'developer_name' => $progData['developer_name'],
                'programme_name' => $progData['programme_name']
            ]);
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
            
            // Verify credentials
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
        // 5z11: Verify Password (for column removal)
        if ($action === 'verify_password') {
            $password = $_POST['password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            
            if (empty($password) || empty($login_id)) {
                echo json_encode(['success' => false, 'error' => 'Missing credentials']);
                exit;
            }
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
            $stmt->execute();
            $adminData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adminData && 
                $login_id === ($adminData['admin_login_id'] ?? '') && 
                password_verify($password, $adminData['admin_password_hash'] ?? '')) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
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
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Action failed: Invalid Password confirmation. Session terminated.</span>";
                unset($_SESSION['admin_logged_in']);
                header("Location: serveraccount.php");
                exit;
            }
        }

        // 6e: Update Payment Status (with hierarchical validation and revenue history update) - UPDATED Table Based
        if (isset($_POST['update_payment_status']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $new_status = trim($_POST['payment_status'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            $normalizedStatus = normalizePaymentStatus($new_status);
            
            if (!empty($user_id) && !empty($new_status) && $source_table === $harvhubTable) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM {$source_table} WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($targetUser) {
                        $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                        $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                        $decision = determineUserStatus($targetUser, $contractDuration, $minProfitForSplit);
                        
                        // SPECIAL CASE: failed-payment can be set on any eligible user
                        $isFailedPayment = ($normalizedStatus === 'failed-payment');
                        
                        if ($isFailedPayment || ($decision['has_eligible_profit'] && $decision['should_show_in_revenue'])) {
                            // Update the main loyalties field
                            $stmt = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
                            $stmt->execute([$normalizedStatus, $user_id]);
                            
                            // Only set reset_contract for payment-confirmed
                            if ($normalizedStatus === 'payment-confirmed') {
                                $stmtReset = $pdo->prepare("UPDATE {$source_table} SET reset_contract = 1 WHERE id = ?");
                                $stmtReset->execute([$user_id]);
                            }
                            
                            // Update revenue_history table
                            $syncResult = syncUserRevenueHistory($user_id, $source_table, $pdo, $serverAccount);
                            
                            if ($syncResult['success']) {
                                $_SESSION['admin_message'] = "<span style='color:green;'>Payment status updated to '{$normalizedStatus}' for User ID {$user_id}!</span>";
                            } else {
                                $_SESSION['admin_message'] = "<span style='color:orange;'>Status updated but revenue history sync failed: {$syncResult['message']}</span>";
                            }
                        } else {
                            $_SESSION['admin_message'] = "<span style='color:red;'>Cannot update status: User does not have an eligible profit split scenario. Reason: {$decision['reason']}</span>";
                        }
                    } else {
                        $_SESSION['admin_message'] = "<span style='color:red;'>User not found.</span>";
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = "<span style='color:red;'>Error updating payment status: " . htmlspecialchars($e->getMessage()) . "</span>";
                }
            } else {
                $_SESSION['admin_message'] = "<span style='color:red;'>Invalid update request. Please fill all fields.</span>";
            }
            header("Location: serveraccount.php?view=paid_users");
            exit;
        }

        // 6f: Update Server Decision
        if (isset($_POST['update_server_decision']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $server_decision = trim($_POST['server_decision'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && !empty($server_decision) && $source_table === $harvhubTable) {
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

        // 6h: Update Application Status
        if (isset($_POST['update_application_status']) && $re_authenticated_for_action) {
            $user_id = $_POST['user_id'] ?? '';
            $new_status = trim($_POST['new_application_status'] ?? '');
            $source_table = $_POST['source_table'] ?? '';
            
            if (!empty($user_id) && !empty($new_status) && $source_table === $harvhubTable) {
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
        $checkColumn = $pdo->query("SHOW COLUMNS FROM {$harvhubTable} LIKE 'server_decision'");
        if ($checkColumn->rowCount() == 0) {
            $pdo->exec("ALTER TABLE {$harvhubTable} ADD COLUMN server_decision VARCHAR(50) DEFAULT NULL");
        }
    }

    // ============================================
    // SECTION 7: DATA FETCHING FOR VIEWS
    // ============================================

    // ============================================
    // SECTION 7a: Paid Users / Revenue Dashboard Data (UPDATED - Table Based)
    // ============================================
    if ($authenticated && $currentView === 'paid_users') {
        $allUsers = [];
        
        // Get contract duration for filtering
        $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
        $today = date('Y-m-d');
        
        // DASHBOARD SUMMARY - Only for active investors
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
        
        // TABLE SUMMARY
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

        // FETCH ALL USERS FROM SINGLE TABLE
        $stmt = $pdo->prepare("SELECT {$selectFields}, '{$harvhubTable}' AS source FROM {$harvhubTable}");
        $stmt->execute();
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process each user for the table and summaries
        foreach ($allUsers as &$user) {
            $brokerBalance = (float)($user['broker_balance'] ?? 0);
            $profitAndLoss = (float)($user['profitandloss'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            
            $user['broker_balance_display'] = $brokerBalance;
            $user['profitandloss_display'] = $profitAndLoss;
            $user['current_balance'] = $currentBalance;
            
            // DASHBOARD SUMMARY
            $dashboardSummary['total_broker_balance'] += $brokerBalance;
            $dashboardSummary['total_profit'] += $profitAndLoss;
            $dashboardSummary['total_current_balance'] += $currentBalance;
            
            // Calculate potential shares for dashboard
            if ($profitAndLoss > $minProfitForSplit) {
                $dashboardSummary['users_with_profit']++;
                $potentialServerShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $potentialUserShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                $dashboardSummary['total_server_share'] += $potentialServerShare;
                $dashboardSummary['total_user_share'] += $potentialUserShare;
                $dashboardSummary['total_expected_payment'] += $potentialServerShare;
                
                // Track payment statuses from revenue_history table using user_email
                $historyStmt = $pdo->prepare("
                    SELECT loyalties, server_share FROM revenue_history 
                    WHERE user_email = ? 
                    ORDER BY created_at DESC LIMIT 1
                ");
                $historyStmt->execute([$user['email']]);
                $latestRecord = $historyStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($latestRecord) {
                    $normalizedStatus = normalizePaymentStatus($latestRecord['loyalties'] ?? '');
                    $recordedServerShare = (float)($latestRecord['server_share'] ?? 0);
                    if ($normalizedStatus === 'payment-confirmed') {
                        $dashboardSummary['total_payments_received'] += $recordedServerShare;
                    } elseif ($normalizedStatus === 'payment-made') {
                        $dashboardSummary['total_payments_made'] += $recordedServerShare;
                    } else {
                        $dashboardSummary['total_unpaid_payments'] += $potentialServerShare;
                    }
                }
            }
            
            // TABLE SUMMARY & DISPLAY LOGIC
            $decision = determineUserStatus($user, $contractDuration, $minProfitForSplit);
            
            $user['should_show_in_revenue'] = $decision['should_show_in_revenue'];
            $user['server_share'] = $decision['server_share'];
            $user['user_share'] = $decision['user_share'];
            $user['expected_payment'] = $decision['expected_payment'];
            $user['has_eligible_profit'] = $decision['has_eligible_profit'];
            $user['determined_status'] = $decision['status'];
            $user['decision_reason'] = $decision['reason'];
            
            $rawStatus = $user['loyalties'] ?? '';
            $normalizedStatus = normalizePaymentStatus($rawStatus);
            $user['loyalties_normalized'] = $normalizedStatus;

            // Check if contract is active
            $isContractActive = false;
            $executionStartDate = $user['execution_start_date'] ?? null;
            if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00') {
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $todayDate = new DateTime();
                $todayDate->setTime(0, 0, 0);
                $isContractActive = ($todayDate <= $end);
            }

            // Determine user's current status category
            if ($normalizedStatus === 'payment-confirmed') {
                $user['current_status'] = 'completed';
                $user['status_label'] = 'Completed (Payment Confirmed)';
                $user['should_show_in_revenue'] = true;
            } elseif ($normalizedStatus === 'payment-made' || $normalizedStatus === 'unpaid-payment') {
                $user['current_status'] = 'active';
                $user['status_label'] = 'Active (' . $normalizedStatus . ')';
                $user['should_show_in_revenue'] = true;
            } elseif ($isContractActive) {
                $user['current_status'] = 'active';
                $user['status_label'] = 'Active (Contract Running)';
                $user['should_show_in_revenue'] = false;
            } else {
                $user['current_status'] = 'inactive';
                if ($profitAndLoss < 0) {
                    $user['status_label'] = 'Inactive (Loss)';
                } elseif ($profitAndLoss > 0 && $profitAndLoss <= $minProfitForSplit) {
                    $user['status_label'] = 'Inactive (Below Min Profit)';
                } elseif (empty($executionStartDate) || $executionStartDate === '0000-00-00') {
                    $user['status_label'] = 'Inactive (No Contract)';
                } else {
                    $user['status_label'] = 'Inactive (Contract Ended)';
                }
                $user['should_show_in_revenue'] = false;
            }
            
            $unpaidAge = ['ended_on' => null, 'age' => null, 'is_ended' => false];
            if ($user['has_eligible_profit'] && !empty($user['execution_start_date']) && $contractDuration > 0) {
                $unpaidAge = calculateUnpaidAge($user['execution_start_date'], $contractDuration);
            }
            $user['unpaid_payment_age'] = $unpaidAge;
            
            // Determine display status for the table
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
            
            // Add to table summary ONLY for active users
            if ($user['current_status'] === 'active' && $user['should_show_in_revenue']) {
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
        
        // Use dashboard summary for display
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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>HCP</text></svg>">
    <title>Harvhub CP</title>
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
                <h2><?= $initialSetupRequired ? '🔑 Setup' : '🔒 Login' ?></h2>
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
    <!-- SECTION 10: AUTHENTICATED DASHBOARD     -->
    <!-- ============================================ -->
        <?php else: ?>
            <div class="container">
                <a href="?logout=1" class="logout-link">Logout</a>
                
                <?php if ($currentView !== 'menu'): ?>
                    <a href="serveraccount.php?view=menu" class="back-btn">← Back to Menu</a>
                <?php endif; ?>

                
            <!-- ============================================ -->
            <!-- SECTION 10a: MENU / NAVIGATION                -->
            <!-- ============================================ -->
            <?php if ($currentView === 'menu'): ?>
                <h2> Harvhub CP</h2>
                <div class="nav-menu">
                    <a href="serveraccount.php?view=settings">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-label">
                            Server Settings
                            <span class="sub-text">Configuration &amp; Payment</span>
                        </span>
                    </a>
                    
                    <a href="serveraccount.php?view=vps">
                        <span class="nav-icon">🖥️</span>
                        <span class="nav-label">
                            Virtual Private Servers
                            <span class="sub-text">IP &amp; System config</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=paid_users">
                        <span class="nav-icon">💰</span>
                        <span class="nav-label">
                            Revenue Dashboard
                            <span class="sub-text">Investors Revenue Share</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=account_management">
                        <span class="nav-icon">👥</span>
                        <span class="nav-label">
                            Account Management
                            <span class="sub-text">User Accounts &amp; Status</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=analytics">
                        <span class="nav-icon">📈</span>
                        <span class="nav-label">
                            Analytics
                            <span class="sub-text">Data &amp; Insights</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=risk_dictionary">
                        <span class="nav-icon">💹</span>
                        <span class="nav-label">
                            Risks Dictionary
                            <span class="sub-text">Risk Recovery Generator</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=manual">
                        <span class="nav-icon">📚</span>
                        <span class="nav-label">
                            Manuals
                            <span class="sub-text">Documentation &amp; Guide</span>
                        </span>
                    </a>
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
            <?php elseif ($currentView === 'vps'): ?>
                <?php include 'vps_config.php'; ?>
            <?php elseif ($currentView === 'manual'): ?>
                <?php include 'manual.php'; ?>
            <!-- ============================================ -->
            <!-- SECTION 10d: SETTINGS & CONFIGURATION        -->
            <!-- ============================================ -->
            <?php elseif ($currentView === 'settings'): ?>
                <?php include 'settings.php'; ?> 
            <!-- ============================================ -->
            <!-- SECTION 10e: REVENUE DASHBOARD               -->
            <!-- ============================================ -->
            <?php elseif ($currentView === 'paid_users'): ?>
                <?php include 'revenue.php'; ?>  
            <?php elseif ($currentView === 'risk_dictionary'): ?>
                <?php include 'risk_dictionary.php'; ?>
            <?php endif; ?>
                
            </div>
            
            <!-- ============================================ -->
            <!-- SECTION 11: settings_modalS                           -->
            <!-- ============================================ -->
            
            <!-- Password settings_modal -->
            <div id="password-settings_modal" class="settings_modal">
                <div class="settings_modal-content">
                    <h3 id="settings_modal-title">SECURITY CHECK</h3>
                    <p id="settings_modal-paragraph">Please enter your Password.</p>
                    <input type="password" id="settings_modal-password-input" placeholder="Password" required>
                    <div class="settings_modal-buttons">
                        <button type="button" id="settings_modal-confirm-btn">Confirm</button>
                        <button type="button" id="settings_modal-cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>



        <?php endif; ?>
    </div>

</body>
</html>