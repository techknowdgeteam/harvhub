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

    function determineUserStatus($user, $contractDuration, $minProfitForSplit) {
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
            
            // ===== Check if contract expired with profit > threshold =====
            if ($contract_completed && $profitAndLoss > $minProfitForSplit) {
                $isContractExpiredWithProfit = true;
            }
        }
        
        // Check if contract is cancelled
        $isCancelled = false;
        if (strpos(strtolower($currentLoyalties), 'cancelled') !== false) {
            $isCancelled = true;
        }
        
        // Check if payment failed
        $isFailedPayment = ($currentLoyalties === 'failed-payment');
        
        // ===== SPECIAL: Check for expired contract with profit > threshold =====
        // Even if loyalties is not set, we should show this in revenue
        // ===== SPECIAL: Check for expired contract with profit > threshold =====
        // ===== SPECIAL: Check for expired contract with profit > threshold =====
        if ($isContractExpiredWithProfit && !$isCancelled) {
            $serverSharePercent = (int)($GLOBALS['serverAccount']['server_share_percent'] ?? 30);
            $userSharePercent = (int)($GLOBALS['serverAccount']['user_share_percent'] ?? 70);
            $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
            $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            
            // Check if already has a valid status
            $validStatuses = ['payment-confirmed', 'payment-made', 'unpaid-payment', 'failed-payment'];
            $statusAlreadySet = false;
            foreach ($validStatuses as $validStatus) {
                if (strpos(strtolower($currentLoyalties), $validStatus) !== false) {
                    $statusAlreadySet = true;
                    break;
                }
            }
            
            // If no valid status, use unpaid-payment (Section 2.5c will handle the database save)
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
        
        // ===== SIMPLIFIED: Enable dropdown for ANY user with profit > threshold =====
        // Only disable if contract is active (not ended yet) or no profit
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
        
        // If no profit, disable dropdown
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
        
        // If profit below threshold, disable dropdown
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
        
        // ===== PROFIT > THRESHOLD = ENABLE DROPDOWN =====
        if ($profitAndLoss > $minProfitForSplit) {
            // Get shares from global or use defaults
            $serverSharePercent = (int)($GLOBALS['serverAccount']['server_share_percent'] ?? 30);
            $userSharePercent = (int)($GLOBALS['serverAccount']['user_share_percent'] ?? 70);
            $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
            $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            
            $normalizedCurrent = normalizePaymentStatus($currentLoyalties);
            
            // ENABLED: always show in revenue when profit > threshold
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
        
        // Fallback - disabled
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

    function get_list_array($str) {
        return array_filter(array_map('trim', explode(',', $str ?? '')));
    }

    function format_currency($amount) {
        return '$' . number_format($amount, 2);
    }
    
    // ============================================
    // SECTION 3b: REVENUE HISTORY SYNC FUNCTION (UPDATED)
    // ============================================

    function syncUserRevenueHistory($userId, $sourceTable, $pdo, $serverAccount) {
        try {
            // Get user data with all relevant fields
            $stmt = $pdo->prepare("SELECT * FROM {$sourceTable} WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                return ['success' => false, 'message' => 'User not found'];
            }
            
            // Get configuration
            $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
            $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
            $serverSharePercent = (int)($serverAccount['server_share_percent'] ?? 30);
            $userSharePercent = (int)($serverAccount['user_share_percent'] ?? 70);
            
            // Get user values
            $executionStartDate = $userData['execution_start_date'] ?? null;
            $brokerBalance = (float)($userData['broker_balance'] ?? 0);
            $profitAndLoss = (float)($userData['profitandloss'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            $currentLoyalties = normalizePaymentStatus($userData['loyalties'] ?? '');
            $contractId = $userData['contract_id'] ?? null;
            $investedWith = $userData['invested_with'] ?? null;
            
            // ===== CRITICAL: Determine if contract is active =====
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
            
            // Calculate shares if profit is eligible
            $serverShare = 0;
            $userShare = 0;
            $isEligible = false;
            if ($profitAndLoss > $minProfitForSplit) {
                $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
                $isEligible = true;
            }
            
            // Ensure revenue_history column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM {$sourceTable} LIKE 'revenue_history'");
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$sourceTable} ADD COLUMN revenue_history LONGTEXT DEFAULT NULL");
            }
            
            // Get current revenue history
            $stmt = $pdo->prepare("SELECT revenue_history FROM {$sourceTable} WHERE id = ?");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $history = [];
            if ($result && !empty($result['revenue_history'])) {
                $history = json_decode($result['revenue_history'], true);
                if (!is_array($history)) {
                    $history = [];
                }
            }
            
            // ============================================================
            // STEP 1: CLEAN INVALID RECORDS (remove records with no contract_id)
            // ============================================================
            $invalidRecordsRemoved = 0;
            $validHistory = [];
            foreach ($history as $record) {
                $recordContractId = $record['contract_id'] ?? null;
                if (!empty($recordContractId) && $recordContractId !== 'N/A' && $recordContractId !== 'null') {
                    $validHistory[] = $record;
                } else {
                    $invalidRecordsRemoved++;
                }
            }
            $history = $validHistory;
            
            // ============================================================
            // STEP 2: FIND OR CREATE RECORD USING CONTRACT_ID
            // ============================================================
            $existingRecordIndex = -1;
            $recordFound = false;
            
            // Check if current loyalties is failed-payment - we should still update the record
            $isFailedPayment = ($currentLoyalties === 'failed-payment');
            
            if (!empty($contractId) && $contractId !== 'N/A' && $contractId !== 'null') {
                foreach ($history as $index => $record) {
                    $recordContractId = $record['contract_id'] ?? null;
                    if (!empty($recordContractId) && $recordContractId === $contractId) {
                        $existingRecordIndex = $index;
                        $recordFound = true;
                        break;
                    }
                }
            }
            
            // ============================================================
            // ============================================================
            // STEP 3: DETERMINE FINAL STATUS
            // ============================================================
            $finalStatus = $currentLoyalties;

            // Check if contract is cancelled (based on loyalties)
            $isContractCancelled = false;
            if (strpos(strtolower($currentLoyalties), 'cancelled') !== false) {
                $isContractCancelled = true;
            }

            // ===== CRITICAL: If contract is cancelled, FORCE the history status to 'contract_cancelled' =====
            // The revenue_history record should ALWAYS show 'contract_cancelled' regardless of column changes
            // This means even if the main loyalties column is updated to 'unpaid-payment', 'payment-made', etc.
            // the history record remains 'contract_cancelled'
            if ($isContractCancelled) {
                $finalStatus = 'contract_cancelled';
            }
            
            // ============================================================
            // STEP 4: GENERATE CONTRACT_ID IF MISSING
            // ============================================================
            if (empty($contractId) || $contractId === 'N/A' || $contractId === 'null') {
                if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00' && !empty($executionEndDate)) {
                    $startFormatted = date('dmY', strtotime($executionStartDate));
                    $endFormatted = date('dmY', strtotime($executionEndDate));
                    $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
                    
                    $updateContractId = $pdo->prepare("UPDATE {$sourceTable} SET contract_id = ? WHERE id = ?");
                    $updateContractId->execute([$contractId, $userId]);
                } else if ($isFailedPayment) {
                    // For failed payments without a contract_id, generate one based on current date
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
            
            // ============================================================
            // STEP 5: UPDATE EXISTING RECORD OR CREATE NEW
            // ============================================================
            $now = date('Y-m-d H:i:s');
            
            if ($recordFound && $existingRecordIndex !== -1) {
                // ===== UPDATE EXISTING RECORD =====
                $history[$existingRecordIndex]['contract_id'] = $contractId;
                $history[$existingRecordIndex]['loyalties'] = $finalStatus;
                $history[$existingRecordIndex]['server_share'] = $serverShare;
                $history[$existingRecordIndex]['user_share'] = $userShare;
                $history[$existingRecordIndex]['current_balance'] = $currentBalance;
                $history[$existingRecordIndex]['profit'] = $profitAndLoss;
                $history[$existingRecordIndex]['updated_at'] = $now;
                $history[$existingRecordIndex]['execution_start_date'] = $executionStartDate;
                $history[$existingRecordIndex]['execution_end_date'] = $executionEndDate;
                $history[$existingRecordIndex]['starting_balance'] = $brokerBalance;
                
                if (!isset($history[$existingRecordIndex]['invested_with']) && !empty($investedWith)) {
                    $history[$existingRecordIndex]['invested_with'] = $investedWith;
                }
                
                // If status is payment-confirmed, add confirmation details
                if ($currentLoyalties === 'payment-confirmed') {
                    $history[$existingRecordIndex]['confirmed_at'] = $now;
                }
                
                // If status is failed-payment, add failure details
                if ($currentLoyalties === 'failed-payment' || $isFailedPayment) {
                    $history[$existingRecordIndex]['failed_at'] = $now;
                    $history[$existingRecordIndex]['failed_reason'] = 'Payment verification failed';
                }
                
            } else {
                // ===== CREATE NEW RECORD =====
                $newId = time();
                if (!empty($history)) {
                    foreach ($history as $item) {
                        if (isset($item['id']) && is_numeric($item['id']) && $item['id'] >= $newId) {
                            $newId = (int)$item['id'] + 1;
                        }
                    }
                }
                
                if (empty($executionStartDate) || $executionStartDate === '0000-00-00') {
                    $executionStartDate = date('Y-m-d');
                    $start = new DateTime($executionStartDate);
                    $end = clone $start;
                    $end->modify("+{$contractDuration} days");
                    $executionEndDate = $end->format('Y-m-d');
                }
                
                $newRecord = [
                    'id' => $newId,
                    'contract_id' => $contractId,
                    'execution_start_date' => $executionStartDate,
                    'execution_end_date' => $executionEndDate,
                    'starting_balance' => $brokerBalance,
                    'current_balance' => $currentBalance,
                    'profit' => $profitAndLoss,
                    'user_share' => $userShare,
                    'server_share' => $serverShare,
                    'loyalties' => $finalStatus,
                    'recorded_at' => $now,
                    'invested_with' => $investedWith
                ];
                
                if ($currentLoyalties === 'payment-confirmed') {
                    $newRecord['confirmed_at'] = $now;
                }
                
                if ($currentLoyalties === 'failed-payment' || $isFailedPayment) {
                    $newRecord['failed_at'] = $now;
                    $newRecord['failed_reason'] = 'Payment verification failed';
                }
                
                $history[] = $newRecord;
            }
            
            // ============================================================
            // STEP 6: SORT NEWEST FIRST BY ID
            // ============================================================
            usort($history, function($a, $b) {
                $idA = isset($a['id']) ? (int)$a['id'] : 0;
                $idB = isset($b['id']) ? (int)$b['id'] : 0;
                return $idB - $idA;
            });
            
            // ============================================================
            // STEP 7: SAVE UPDATED HISTORY
            // ============================================================
            $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
            $updateStmt = $pdo->prepare("UPDATE {$sourceTable} SET revenue_history = ? WHERE id = ?");
            $updateStmt->execute([$jsonHistory, $userId]);
            
            $message = $recordFound ? 'Revenue history updated' : 'New revenue record created';
            if ($invalidRecordsRemoved > 0) {
                $message .= " (removed {$invalidRecordsRemoved} invalid record(s) with no contract_id)";
            }
            
            if ($isFailedPayment) {
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
    // ============================================
    // This runs on every page load to automatically mark expired contracts
    // with profit above threshold as 'unpaid-payment'

    if ($authenticated) {
        try {
            $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
            $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            // Valid statuses that should NOT be overwritten
            $validStatuses = ['payment-confirmed', 'payment-made', 'unpaid-payment', 'failed-payment'];
            
            // Check both tables
            foreach ([$insidersTable, $insidersServerTable] as $table) {
                try {
                    // Get all users with execution_start_date
                    $stmt = $pdo->prepare("
                        SELECT id, execution_start_date, profitandloss, loyalties 
                        FROM {$table} 
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
                        
                        // Check if contract is expired
                        try {
                            $start = new DateTime($executionStartDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            // Skip if contract is still active
                            if ($today <= $end) {
                                continue;
                            }
                        } catch (Exception $e) {
                            continue;
                        }
                        
                        // Skip if profit is not above threshold
                        if ($profitAndLoss <= $minProfitForSplit) {
                            continue;
                        }
                        
                        // Check if loyalties already has a valid status
                        $statusAlreadySet = false;
                        foreach ($validStatuses as $validStatus) {
                            if (strpos($currentLoyalties, $validStatus) !== false) {
                                $statusAlreadySet = true;
                                break;
                            }
                        }
                        
                        // If no valid status, auto-mark as unpaid-payment
                        if (!$statusAlreadySet) {
                            $updateStmt = $pdo->prepare("UPDATE {$table} SET loyalties = 'unpaid-payment' WHERE id = ?");
                            $updateStmt->execute([$user['id']]);
                            
                            // Sync revenue history
                            if (function_exists('syncUserRevenueHistory')) {
                                syncUserRevenueHistory($user['id'], $table, $pdo, $serverAccount);
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Skip this table on error
                }
            }
        } catch (Exception $e) {
            // Silent fail - don't break the page
        }
    }
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
                $minProfitForSplit = (float)($serverAccount['min_profit_for_split'] ?? 30);
                
                // ===== FIX: Function to check if user should be in active list =====
                function shouldBeActive($user, $contractDuration, $minProfitForSplit) {
                    // MUST have application_status containing 'approved'
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false; // Not approved = skip
                    }
                    
                    // MUST have login (not empty)
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false; // No login = skip
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    
                    // ============================================================
                    // FIRST: Check if contract is still active based on dates
                    // ============================================================
                    $execDate = $user['execution_start_date'] ?? null;
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime();
                            $todayObj->setTime(0, 0, 0);
                            
                            // If contract end date is >= today, contract is ACTIVE
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            $isContractActive = false;
                        }
                    }
                    
                    // ============================================================
                    // RULE 1: If contract is ACTIVE (not expired), user is ACTIVE
                    // ============================================================
                    if ($isContractActive) {
                        return true; // ACTIVE - contract still running
                    }
                    
                    // ============================================================
                    // RULE 2: Contract is EXPIRED - check loyalties
                    // ============================================================
                    
                    // ACTIVE STATUSES (these users are active even after contract expired)
                    $activeStatuses = ['payment-made', 'payment_made', 'unpaid-payment', 'unpaid_payment', 'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'];
                    
                    // Check if loyalties matches any active status
                    foreach ($activeStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return true; // ACTIVE
                        }
                    }
                    
                    // If loyalties is payment-confirmed, user is NOT active
                    if (strpos($loyalties, 'payment-confirmed') !== false || strpos($loyalties, 'payment_confirmed') !== false) {
                        return false;
                    }
                    
                    // If loyalties contains 'cancelled', user is NOT active
                    if (strpos($loyalties, 'cancelled') !== false) {
                        return false;
                    }
                    
                    // If loyalties is null or empty AND contract is expired, user is NOT active
                    if (empty($loyalties)) {
                        return false;
                    }
                    
                    // ANY other loyalties value with expired contract = NOT active
                    return false;
                }
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, execution_start_date, profitandloss, broker_balance, loyalties, application_status, '{$insidersServerTable}' as source, ? as contract_duration
                            FROM {$insidersServerTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt1->execute([$contractDuration]);
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            if (shouldBeActive($user, $contractDuration, $minProfitForSplit)) {
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) { }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, execution_start_date, profitandloss, broker_balance, loyalties, application_status, '{$insidersTable}' as source, ? as contract_duration
                            FROM {$insidersTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt2->execute([$contractDuration]);
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            if (shouldBeActive($user, $contractDuration, $minProfitForSplit)) {
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
        // 5aa2: Get Unusual Users (with daily_balance_log analysis) - FIXED to only show active users
        if ($action === 'get_unusual_users') {
            try {
                $search = trim($_POST['search'] ?? '');
                $users = [];
                $today = date('Y-m-d');
                $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
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

                // ===== Helper function to check if user is ACTIVE =====
                function isUserActive($user, $contractDuration) {
                    // MUST have application_status containing 'approved'
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false; // Not approved = skip
                    }
                    
                    // MUST have login (not empty)
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false; // No login = skip
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    
                    // FIRST: Check if contract is still active based on dates
                    $execDate = $user['execution_start_date'] ?? null;
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime();
                            $todayObj->setTime(0, 0, 0);
                            
                            // If contract end date is >= today, contract is ACTIVE
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            $isContractActive = false;
                        }
                    }
                    
                    // RULE 1: If contract is ACTIVE (not expired), user is ACTIVE
                    if ($isContractActive) {
                        return true; // ACTIVE - contract still running
                    }
                    
                    // RULE 2: Contract is EXPIRED - check loyalties
                    $activeStatuses = ['payment-made', 'payment_made', 'unpaid-payment', 'unpaid_payment', 'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'];
                    
                    // Check if loyalties matches any active status
                    foreach ($activeStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return true; // ACTIVE
                        }
                    }
                    
                    // NOT active
                    return false;
                }
                
                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $sql = "SELECT id, fullname, email, broker, login, broker_balance, profitandloss, daily_balance_log, loyalties, execution_start_date, application_status, '{$insidersServerTable}' as source FROM {$insidersServerTable} WHERE execution_start_date IS NOT NULL AND execution_start_date != '0000-00-00' AND execution_start_date <= ?";
                        if (!empty($search)) {
                            $sql .= " AND (fullname LIKE ? OR email LIKE ? OR id LIKE ?)";
                        }
                        $sql .= " ORDER BY id DESC";
                        $stmt1 = $pdo->prepare($sql);
                        if (!empty($search)) {
                            $searchTerm = '%' . $search . '%';
                            $stmt1->execute([$today, $searchTerm, $searchTerm, $searchTerm]);
                        } else {
                            $stmt1->execute([$today]);
                        }
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($results as $user) {
                            // ===== CRITICAL: Only include if user is ACTIVE =====
                            if (isUserActive($user, $contractDuration) && checkUnusualActivity($user['daily_balance_log'] ?? '')) {
                                $summary = getUnusualSummary($user['daily_balance_log'] ?? '');
                                $user['withdrawal_count'] = $summary['withdrawal_count'];
                                $user['unauthorized_trade_count'] = $summary['unauthorized_trade_count'];
                                $user['unauthorized_balance'] = $summary['unauthorized_balance'];
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_unusual_users (insiders_server): " . $e->getMessage());
                }
                
                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $sql = "SELECT id, fullname, email, broker, login, broker_balance, profitandloss, daily_balance_log, loyalties, execution_start_date, application_status, '{$insidersTable}' as source FROM {$insidersTable} WHERE execution_start_date IS NOT NULL AND execution_start_date != '0000-00-00' AND execution_start_date <= ?";
                        if (!empty($search)) {
                            $sql .= " AND (fullname LIKE ? OR email LIKE ? OR id LIKE ?)";
                        }
                        $sql .= " ORDER BY id DESC";
                        $stmt2 = $pdo->prepare($sql);
                        if (!empty($search)) {
                            $searchTerm = '%' . $search . '%';
                            $stmt2->execute([$today, $searchTerm, $searchTerm, $searchTerm]);
                        } else {
                            $stmt2->execute([$today]);
                        }
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($results as $user) {
                            // ===== CRITICAL: Only include if user is ACTIVE =====
                            if (isUserActive($user, $contractDuration) && checkUnusualActivity($user['daily_balance_log'] ?? '')) {
                                $summary = getUnusualSummary($user['daily_balance_log'] ?? '');
                                $user['withdrawal_count'] = $summary['withdrawal_count'];
                                $user['unauthorized_trade_count'] = $summary['unauthorized_trade_count'];
                                $user['unauthorized_balance'] = $summary['unauthorized_balance'];
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_unusual_users (insiders): " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5aa3: Get User Daily Balance Log
        if ($action === 'get_user_daily_log') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
                echo json_encode(['error' => 'Invalid user selection']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT daily_balance_log, daily_target_met, fullname, email, broker_balance, profitandloss FROM {$source_table} WHERE id = ?");
                $stmt->execute([$user_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $log = [];
                $dailyTarget = [];
                $userData = [];
                
                if ($result) {
                    if (!empty($result['daily_balance_log'])) {
                        $log = json_decode($result['daily_balance_log'], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $log = [];
                        }
                    }
                    if (!empty($result['daily_target_met'])) {
                        $dailyTarget = $result['daily_target_met'];
                        $parsed = json_decode($dailyTarget, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $dailyTarget = $parsed;
                        }
                    }
                    $userData = [
                        'id' => $user_id,
                        'fullname' => $result['fullname'] ?? 'N/A',
                        'email' => $result['email'] ?? 'N/A',
                        'broker_balance' => $result['broker_balance'] ?? 0,
                        'profitandloss' => $result['profitandloss'] ?? 0,
                        'source' => $source_table
                    ];
                }
                
                echo json_encode([
                    'success' => true, 
                    'log' => $log,
                    'daily_target' => $dailyTarget,
                    'user' => $userData
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }

        // 5aa4: Get User Data (for detail modal)
        if ($action === 'get_user_data') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            
            if (empty($user_id) || !in_array($source_table, [$insidersServerTable, $insidersTable])) {
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

        // 5aa5: Update Payment Status (for completed tab)
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
            
            if (!empty($user_id) && !empty($new_status) && in_array($source_table, [$insidersServerTable, $insidersTable])) {
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

        // 5ab: Get Completed Investors with enhanced data
        if ($action === 'get_completed_investors') {
            try {
                $users = array();
                $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                
                // Get from insiders_server table - ALL users with revenue_history
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        // ===== FIX: Include broker and login in SELECT =====
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, loyalties, invested_with, execution_start_date, 
                                profitandloss, broker_balance, 
                                broker, login,
                                revenue_history,
                                ? as contract_duration,
                                '{$insidersServerTable}' as source
                            FROM {$insidersServerTable} 
                            ORDER BY id DESC
                        ");
                        $stmt1->execute([$contractDuration]);
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            $history = [];
                            $hasHistory = false;
                            
                            if (!empty($user['revenue_history']) && $user['revenue_history'] !== '[]') {
                                $history = json_decode($user['revenue_history'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($history) && !empty($history)) {
                                    $hasHistory = true;
                                } else {
                                    $history = [];
                                }
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
                                'contract_duration' => $user['contract_duration'] ?? $contractDuration
                            ];
                            
                            $users[] = $userData;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_completed_investors (insiders_server): " . $e->getMessage());
                }
                
                // Get from insiders table - ALL users with revenue_history
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        // ===== FIX: Include broker and login in SELECT =====
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, loyalties, invested_with, execution_start_date, 
                                profitandloss, broker_balance, 
                                broker, login,
                                revenue_history,
                                ? as contract_duration,
                                '{$insidersTable}' as source
                            FROM {$insidersTable} 
                            ORDER BY id DESC
                        ");
                        $stmt2->execute([$contractDuration]);
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            $history = [];
                            $hasHistory = false;
                            
                            if (!empty($user['revenue_history']) && $user['revenue_history'] !== '[]') {
                                $history = json_decode($user['revenue_history'], true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($history) && !empty($history)) {
                                    $hasHistory = true;
                                } else {
                                    $history = [];
                                }
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
                                'contract_duration' => $user['contract_duration'] ?? $contractDuration
                            ];
                            
                            $users[] = $userData;
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_completed_investors (insiders): " . $e->getMessage());
                }
                
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
                    // Sort NEWEST FIRST
                    if (is_array($history) && !empty($history)) {
                        usort($history, function($a, $b) {
                            $dateA = isset($a['recorded_at']) ? strtotime($a['recorded_at']) : (isset($a['id']) ? $a['id'] : 0);
                            $dateB = isset($b['recorded_at']) ? strtotime($b['recorded_at']) : (isset($b['id']) ? $b['id'] : 0);
                            return $dateB - $dateA;
                        });
                    }
                }

                // Also fetch invested_with for the user to add to history records if missing
                $stmt2 = $pdo->prepare("SELECT invested_with FROM {$source_table} WHERE id = ?");
                $stmt2->execute([$user_id]);
                $userInvestedWith = $stmt2->fetch(PDO::FETCH_ASSOC)['invested_with'] ?? null;

                if (is_array($history) && !empty($history) && $userInvestedWith) {
                    $needsUpdate = false;
                    foreach ($history as &$record) {
                        if (!isset($record['invested_with'])) {
                            $record['invested_with'] = $userInvestedWith;
                            $needsUpdate = true;
                        }
                    }
                    if ($needsUpdate) {
                        $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
                        $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
                        $updateStmt->execute([$jsonHistory, $user_id]);
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
            
            $stmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash, contract_duration, min_profit_for_split FROM {$serverAccountTable} WHERE id = 1");
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
            $minProfitForSplit = (float)($adminData['min_profit_for_split'] ?? 30);
            $contractId = $userData['contract_id'] ?? null;
            
            // Get current profit and loss
            $profitAndLoss = (float)($userData['profitandloss'] ?? 0);
            $brokerBalance = (float)($userData['broker_balance'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            $executionStartDate = $userData['execution_start_date'] ?? null;
            
            // ================================================================
            // ===== CRITICAL FIX: Determine loyalties based on profit =====
            // ================================================================
            $loyaltiesToSet = 'contract_cancelled';
            if ($profitAndLoss > $minProfitForSplit) {
                // Profit above threshold - user owes payment
                $loyaltiesToSet = 'unpaid-payment';
            }
            // else: profit is zero, negative, or below threshold - just cancelled
            
            // Calculate shares based on current profit
            $serverSharePercent = (int)($serverAccount['server_share_percent'] ?? 30);
            $userSharePercent = (int)($serverAccount['user_share_percent'] ?? 70);
            
            $serverShare = 0;
            $userShare = 0;
            if ($profitAndLoss > $minProfitForSplit) {
                $serverShare = round(($profitAndLoss * $serverSharePercent) / 100, 2);
                $userShare = round(($profitAndLoss * $userSharePercent) / 100, 2);
            }
            
            // Calculate execution end date
            $executionEndDate = null;
            if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00') {
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $executionEndDate = $end->format('Y-m-d');
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
            
            // ===== FIND RECORD BY CONTRACT_ID =====
            $existingRecordIndex = -1;
            if (!empty($contractId)) {
                foreach ($history as $index => $record) {
                    if (isset($record['contract_id']) && $record['contract_id'] === $contractId) {
                        $existingRecordIndex = $index;
                        break;
                    }
                }
            }

            // If not found by contract_id, try by dates (fallback)
            if ($existingRecordIndex === -1 && !empty($executionStartDate) && !empty($executionEndDate)) {
                foreach ($history as $index => $record) {
                    if (($record['execution_start_date'] ?? '') === $executionStartDate && 
                        ($record['execution_end_date'] ?? '') === $executionEndDate) {
                        $existingRecordIndex = $index;
                        break;
                    }
                }
            }

            if ($existingRecordIndex !== -1) {
                // ===== UPDATE EXISTING RECORD =====
                // CRITICAL: loyalty in history ALWAYS remains 'contract_cancelled' for cancelled contracts
                $history[$existingRecordIndex]['loyalties'] = 'contract_cancelled'; // FORCE this value
                $history[$existingRecordIndex]['cancelled_at'] = date('Y-m-d H:i:s');
                $history[$existingRecordIndex]['cancelled_by'] = $login_id;
                $history[$existingRecordIndex]['profit'] = $profitAndLoss;
                $history[$existingRecordIndex]['server_share'] = $serverShare;
                $history[$existingRecordIndex]['user_share'] = $userShare;
                $history[$existingRecordIndex]['current_balance'] = $currentBalance;
                $history[$existingRecordIndex]['ending_balance'] = $currentBalance;
                $history[$existingRecordIndex]['updated_at'] = date('Y-m-d H:i:s');
                $history[$existingRecordIndex]['contract_id'] = $contractId;
                
                if (!isset($history[$existingRecordIndex]['invested_with']) && isset($userData['invested_with'])) {
                    $history[$existingRecordIndex]['invested_with'] = $userData['invested_with'];
                }
            } else {
                // ===== CREATE NEW CANCELLED RECORD =====
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
                
                // Generate contract_id if not exists
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
                
                $cancelledRecord = [
                    'id' => $newId,
                    'contract_id' => $contractId,
                    'execution_start_date' => $executionStartDate,
                    'execution_end_date' => $executionEndDate,
                    'starting_balance' => $brokerBalance,
                    'current_balance' => $currentBalance,
                    'ending_balance' => $currentBalance,
                    'profit' => $profitAndLoss,
                    'user_share' => $userShare,
                    'server_share' => $serverShare,
                    'loyalties' => 'contract_cancelled', // ALWAYS contract_cancelled in history
                    'recorded_at' => date('Y-m-d H:i:s'),
                    'cancelled_at' => date('Y-m-d H:i:s'),
                    'cancelled_by' => $login_id,
                    'invested_with' => $userData['invested_with'] ?? null
                ];
                
                $history[] = $cancelledRecord;
            }
            
            // Save updated history
            $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
            
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN revenue_history LONGTEXT DEFAULT NULL");
            }
            
            $updateStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
            $updateStmt->execute([$jsonHistory, $user_id]);
            
            // ================================================================
            // ===== SET LOYALTIES BASED ON PROFIT =====
            // ================================================================
            // If profit > threshold: set to 'unpaid-payment' so user owes
            // If profit <= threshold: set to 'contract_cancelled'
            $updateLoyalties = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
            $updateLoyalties->execute([$loyaltiesToSet, $user_id]);
            
            // ===== SET reset_contract = 1 FOR ALL CANCELLED CONTRACTS =====
            $updateReset = $pdo->prepare("UPDATE {$source_table} SET reset_contract = 1 WHERE id = ?");
            $updateReset->execute([$user_id]);
            
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
                'message' => "Contract cancelled successfully. Profit of $".number_format($profitAndLoss, 2)." recorded. Loyalties set to '{$loyaltiesToSet}'. reset_contract set to 1.",
                'new_execution_date' => $newExecutionDateStr,
                'profit_recorded' => $profitAndLoss,
                'server_share_recorded' => $serverShare,
                'user_share_recorded' => $userShare,
                'loyalties_set_to' => $loyaltiesToSet,
                'reset_contract_set_to' => 1
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
        // 5z8: Get Inactive Users (FIXED - Proper active/inactive logic)
        if ($action === 'get_inactive_users') {
            try {
                $users = array();
                $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
                $today = date('Y-m-d');

                // Helper function to check if user is inactive
                function isUserInactive($user, $contractDuration, $today) {
                    // MUST have application_status containing 'approved'
                    $appStatus = strtolower(trim($user['application_status'] ?? ''));
                    if (strpos($appStatus, 'approved') === false) {
                        return false; // Not approved = skip
                    }
                    
                    // MUST have login (not empty)
                    $login = trim($user['login'] ?? '');
                    if (empty($login)) {
                        return false; // No login = skip
                    }
                    
                    $loyalties = strtolower(trim($user['loyalties'] ?? ''));
                    
                    // FIRST: Check if contract is still active based on dates
                    $execDate = $user['execution_start_date'] ?? null;
                    $isContractActive = false;
                    
                    if (!empty($execDate) && $execDate !== '0000-00-00' && $execDate !== null) {
                        try {
                            $start = new DateTime($execDate);
                            $end = clone $start;
                            $end->modify("+{$contractDuration} days");
                            $end->setTime(0, 0, 0);
                            
                            $todayObj = new DateTime($today);
                            $todayObj->setTime(0, 0, 0);
                            
                            // If contract end date is >= today, contract is ACTIVE
                            if ($end >= $todayObj) {
                                $isContractActive = true;
                            }
                        } catch (Exception $e) {
                            // If date parsing fails, treat as inactive
                            $isContractActive = false;
                        }
                    }
                    
                    // ============================================================
                    // RULE 1: If contract is ACTIVE (not expired), user is ACTIVE
                    // ============================================================
                    if ($isContractActive) {
                        return false; // NOT inactive - contract still running
                    }
                    
                    // ============================================================
                    // RULE 2: Contract is EXPIRED - check loyalties
                    // ============================================================
                    
                    // ACTIVE STATUSES (these users are active even after contract expired)
                    $activeStatuses = ['payment-made', 'payment_made', 'unpaid-payment', 'unpaid_payment', 'failed-payment', 'failed_payment', 'payment-failed', 'payment_failed'];
                    
                    // If loyalties matches any active status, user is ACTIVE
                    foreach ($activeStatuses as $status) {
                        if (strpos($loyalties, $status) !== false) {
                            return false; // NOT inactive
                        }
                    }
                    
                    // If loyalties is payment-confirmed, user is INACTIVE (contract completed)
                    if (strpos($loyalties, 'payment-confirmed') !== false || strpos($loyalties, 'payment_confirmed') !== false) {
                        return true; // INACTIVE
                    }
                    
                    // If loyalties contains 'cancelled', user is INACTIVE
                    if (strpos($loyalties, 'cancelled') !== false) {
                        return true; // INACTIVE
                    }
                    
                    // If loyalties is null or empty AND contract is expired, user is INACTIVE
                    if (empty($loyalties)) {
                        return true; // INACTIVE
                    }
                    
                    // ANY other loyalties value with expired contract = INACTIVE
                    return true;
                }

                // Get from insiders_server table
                try {
                    $checkTable1 = $pdo->query("SHOW TABLES LIKE '{$insidersServerTable}'");
                    if ($checkTable1->rowCount() > 0) {
                        $stmt1 = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, broker_balance, profitandloss, 
                                loyalties, execution_start_date, invested_with, application_status,
                                '{$insidersServerTable}' as source 
                            FROM {$insidersServerTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt1->execute();
                        $results = $stmt1->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            if (isUserInactive($user, $contractDuration, $today)) {
                                $user['contract_duration'] = $contractDuration;
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_inactive_users (insiders_server): " . $e->getMessage());
                }

                // Get from insiders table
                try {
                    $checkTable2 = $pdo->query("SHOW TABLES LIKE '{$insidersTable}'");
                    if ($checkTable2->rowCount() > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT id, fullname, email, broker, login, broker_balance, profitandloss, 
                                loyalties, execution_start_date, invested_with, application_status,
                                '{$insidersTable}' as source 
                            FROM {$insidersTable} 
                            WHERE application_status LIKE '%approved%'
                            AND login IS NOT NULL 
                            AND login != ''
                            ORDER BY id DESC
                        ");
                        $stmt2->execute();
                        $results = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($results as $user) {
                            if (isUserInactive($user, $contractDuration, $today)) {
                                $user['contract_duration'] = $contractDuration;
                                $users[] = $user;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error in get_inactive_users (insiders): " . $e->getMessage());
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
        // 5z9: Initialize Enrollment (Admin-initiated enrollment for inactive users)
        if ($action === 'initialize_enrollment') {
            $user_id = $_POST['user_id'] ?? '';
            $source_table = $_POST['source_table'] ?? '';
            $broker_balance = (float)($_POST['broker_balance'] ?? 0);
            $admin_password = $_POST['admin_password'] ?? '';
            $login_id = $_POST['login_id'] ?? '';
            $contractDuration = (int)($serverAccount['contract_duration'] ?? 30);
            $minBrokerBalance = (float)($serverAccount['min_broker_balance'] ?? 30);
            
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
            
            // Validate broker balance
            if ($broker_balance < $minBrokerBalance) {
                echo json_encode(['error' => 'Broker balance must be at least $' . number_format($minBrokerBalance, 2)]);
                exit;
            }
            
            // Check if user exists
            $checkUser = $pdo->prepare("SELECT id, fullname FROM {$source_table} WHERE id = ?");
            $checkUser->execute([$user_id]);
            if ($checkUser->rowCount() === 0) {
                echo json_encode(['error' => 'User does not exist']);
                exit;
            }
            
            // Get today's date
            $today = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$contractDuration} days", strtotime($today)));
            $startFormatted = date('dmY', strtotime($today));
            $endFormatted = date('dmY', strtotime($endDate));
            $contractId = "sd-{$startFormatted}-ed-{$endFormatted}";
            
            // Update the user - initialize enrollment
            // ===== FIX: Set daily_balance_log and daily_target_met to NULL =====
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
            
            // Also update revenue_history to add a new active contract record
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
            
            // Filter out records with empty contract_id
            $filteredHistory = [];
            foreach ($history as $record) {
                $recordContractId = $record['contract_id'] ?? null;
                if (!empty($recordContractId) && $recordContractId !== 'N/A' && $recordContractId !== 'null') {
                    $filteredHistory[] = $record;
                }
            }
            $history = $filteredHistory;
            
            // Create new revenue entry
            $newId = time();
            if (!empty($history)) {
                foreach ($history as $item) {
                    if (isset($item['id']) && is_numeric($item['id']) && $item['id'] >= $newId) {
                        $newId = (int)$item['id'] + 1;
                    }
                }
            }
            
            $newRecord = [
                'id' => $newId,
                'contract_id' => $contractId,
                'execution_start_date' => $today,
                'execution_end_date' => $endDate,
                'starting_balance' => $broker_balance,
                'current_balance' => $broker_balance,
                'profit' => 0,
                'user_share' => 0,
                'server_share' => 0,
                'loyalties' => 'active',
                'recorded_at' => date('Y-m-d H:i:s'),
                'invested_with' => null // Will be updated from user data if available
            ];
            
            $history[] = $newRecord;
            
            // Sort newest first
            usort($history, function($a, $b) {
                $idA = isset($a['id']) ? (int)$a['id'] : 0;
                $idB = isset($b['id']) ? (int)$b['id'] : 0;
                return $idB - $idA;
            });
            
            $jsonHistory = json_encode($history, JSON_PRETTY_PRINT);
            
            if ($checkColumn->rowCount() == 0) {
                $pdo->exec("ALTER TABLE {$source_table} ADD COLUMN revenue_history LONGTEXT DEFAULT NULL");
            }
            
            $updateHistoryStmt = $pdo->prepare("UPDATE {$source_table} SET revenue_history = ? WHERE id = ?");
            $updateHistoryStmt->execute([$jsonHistory, $user_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Enrollment initialized successfully',
                'contract_id' => $contractId,
                'execution_start_date' => $today,
                'broker_balance' => $broker_balance
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
        // 5z11: Verify Admin Password (for column removal)
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

                // ===== NEW: Handle columns_to_reset from hidden input =====
                $columns_to_reset = $_POST['columns_to_reset'] ?? '[]';
                // Validate JSON
                $columns_to_reset_decoded = json_decode($columns_to_reset, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($columns_to_reset_decoded)) {
                    $columns_to_reset_decoded = [];
                }
                // Filter out empty values
                $columns_to_reset_decoded = array_filter($columns_to_reset_decoded, function($val) {
                    return !empty(trim($val));
                });
                // Re-index and ensure unique values
                $columns_to_reset_decoded = array_values(array_unique($columns_to_reset_decoded));
                $columns_to_reset_json = json_encode($columns_to_reset_decoded, JSON_UNESCAPED_UNICODE);

                $stmt = $pdo->prepare("
                    UPDATE {$serverAccountTable} SET 
                        btc_address = ?, eth_address = ?, eth_network = ?, usdt_address = ?, usdt_network = ?, 
                        minimum_deposit = ?, contract_duration = ?, server_share_percent = ?, user_share_percent = ?,
                        min_profit_for_split = ?, min_broker_balance = ?, minimum_contract_days = ?, expiry_threshold_days = ?,
                        columns_to_reset = ?
                    WHERE id = 1
                ");
                $stmt->execute([
                    $btc_address, $eth_address, $eth_network, $usdt_address, $usdt_network, 
                    $minimum_deposit, $contract_duration, $server_share_percent, $user_share_percent,
                    $min_profit_for_split, $min_broker_balance, $minimum_contract_days, $expiry_threshold_days,
                    $columns_to_reset_json
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
                        
                        // ===== SPECIAL CASE: failed-payment can be set on any eligible user =====
                        // This allows admins to mark payments as failed without restrictions
                        $isFailedPayment = ($normalizedStatus === 'failed-payment');
                        
                        // Allow failed-payment to be set even if not eligible (for manual correction)
                        if ($isFailedPayment || ($decision['has_eligible_profit'] && $decision['should_show_in_revenue'])) {
                            // Update the main loyalties field
                            $stmt = $pdo->prepare("UPDATE {$source_table} SET loyalties = ? WHERE id = ?");
                            $stmt->execute([$normalizedStatus, $user_id]);
                            
                            // ===== CRITICAL: Only set reset_contract for payment-confirmed =====
                            // reset_contract should only be set to 1 when payment is confirmed
                            // DO NOT set reset_contract for failed-payment
                            if ($normalizedStatus === 'payment-confirmed') {
                                $stmtReset = $pdo->prepare("UPDATE {$source_table} SET reset_contract = 1 WHERE id = ?");
                                $stmtReset->execute([$user_id]);
                            }
                            // For failed-payment, we do NOT change reset_contract
                            
                            // Call the sync function to update revenue history
                            $syncResult = syncUserRevenueHistory($user_id, $source_table, $pdo, $serverAccount);
                            
                            if ($syncResult['success']) {
                                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Payment status updated to '{$normalizedStatus}' for User ID {$user_id}!</span>";
                            } else {
                                $_SESSION['admin_message'] = "<span style='color:orange;'>⚠️ Status updated but revenue history sync failed: {$syncResult['message']}</span>";
                            }
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
        // 6z: Save Columns to Reset (separate action)
        if (isset($_POST['save_columns_to_reset']) && $re_authenticated_for_action) {
            try {
                // Get the columns_to_reset data from POST
                $columns_to_reset_raw = $_POST['columns_to_reset'] ?? '[]';
                
                // Parse the JSON data
                $columns_data = json_decode($columns_to_reset_raw, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($columns_data)) {
                    $columns_data = [];
                }
                
                // Validate and sanitize each entry
                $validated_columns = [];
                foreach ($columns_data as $entry) {
                    if (is_array($entry) && isset($entry['column']) && !empty(trim($entry['column']))) {
                        $column_name = trim($entry['column']);
                        
                        // Handle value - store as string or int based on input
                        $value = $entry['value'] ?? null;
                        if ($value !== null) {
                            // Check if it's numeric
                            if (is_numeric($value)) {
                                // Store as integer if it's a whole number, float if decimal
                                if (strpos($value, '.') !== false) {
                                    $value = (float)$value;
                                } else {
                                    $value = (int)$value;
                                }
                            } else {
                                // Store as string
                                $value = (string)$value;
                            }
                        }
                        
                        $validated_columns[] = [
                            'column' => $column_name,
                            'value' => $value
                        ];
                    }
                }
                
                // Remove duplicates by column name
                $unique_columns = [];
                $seen_columns = [];
                foreach ($validated_columns as $entry) {
                    if (!in_array($entry['column'], $seen_columns)) {
                        $seen_columns[] = $entry['column'];
                        $unique_columns[] = $entry;
                    }
                }
                
                $columns_to_reset_json = json_encode($unique_columns, JSON_UNESCAPED_UNICODE);
                
                $stmt = $pdo->prepare("UPDATE {$serverAccountTable} SET columns_to_reset = ? WHERE id = 1");
                $stmt->execute([$columns_to_reset_json]);
                
                $_SESSION['admin_message'] = "<span style='color:green;'>✅ Columns to reset updated successfully! (" . count($unique_columns) . " columns configured)</span>";
            } catch (Exception $e) {
                $_SESSION['admin_message'] = "<span style='color:red;'>❌ Error updating columns to reset: " . htmlspecialchars($e->getMessage()) . "</span>";
            }
            header("Location: serveraccount.php?view=settings");
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

    // ============================================
    // SECTION 7a: Paid Users / Revenue Dashboard Data
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

        // ========== FETCH ALL USERS (Active, Inactive, Completed) ==========
        // Fetch ALL users from insiders table - no filter
        $stmt1 = $pdo->prepare("SELECT {$selectFields}, '{$insidersTable}' AS source FROM {$insidersTable}");
        $stmt1->execute();
        $allUsers = array_merge($allUsers, $stmt1->fetchAll(PDO::FETCH_ASSOC));

        // Fetch ALL users from insiders_server table - no filter
        $stmt2 = $pdo->prepare("SELECT {$selectFields}, '{$insidersServerTable}' AS source FROM {$insidersServerTable}");
        $stmt2->execute();
        $allUsers = array_merge($allUsers, $stmt2->fetchAll(PDO::FETCH_ASSOC));
        
        // Process each user for the table and summaries
        foreach ($allUsers as &$user) {
            $brokerBalance = (float)($user['broker_balance'] ?? 0);
            $profitAndLoss = (float)($user['profitandloss'] ?? 0);
            $currentBalance = $brokerBalance + $profitAndLoss;
            
            $user['broker_balance_display'] = $brokerBalance;
            $user['profitandloss_display'] = $profitAndLoss;
            $user['current_balance'] = $currentBalance;
            
            // ========== DASHBOARD SUMMARY (ALL ACTIVE USERS) ==========
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
                
                // Track payment statuses for dashboard
                $rawStatus = $user['loyalties'] ?? '';
                $normalizedStatus = normalizePaymentStatus($rawStatus);
                if ($normalizedStatus === 'payment-confirmed') {
                    $dashboardSummary['total_payments_received'] += $potentialServerShare;
                } elseif ($normalizedStatus === 'payment-made') {
                    $dashboardSummary['total_payments_made'] += $potentialServerShare;
                } else {
                    $dashboardSummary['total_unpaid_payments'] += $potentialServerShare;
                }
            }
            
            // ========== TABLE SUMMARY & DISPLAY LOGIC ==========
            // Determine user status based on contract rules
            $decision = determineUserStatus($user, $contractDuration, $minProfitForSplit);
            
            $user['should_show_in_revenue'] = $decision['should_show_in_revenue'];
            $user['server_share'] = $decision['server_share'];
            $user['user_share'] = $decision['user_share'];
            $user['expected_payment'] = $decision['expected_payment'];
            $user['has_eligible_profit'] = $decision['has_eligible_profit'];
            $user['determined_status'] = $decision['status'];
            $user['decision_reason'] = $decision['reason'];
            
            // Determine current status for each user
            $rawStatus = $user['loyalties'] ?? '';
            $normalizedStatus = normalizePaymentStatus($rawStatus);
            $user['loyalties_normalized'] = $normalizedStatus;

            // Check if contract is active based on execution_start_date and contract duration
            $isContractActive = false;
            $executionStartDate = $user['execution_start_date'] ?? null;
            if (!empty($executionStartDate) && $executionStartDate !== '0000-00-00') {
                $start = new DateTime($executionStartDate);
                $end = clone $start;
                $end->modify("+{$contractDuration} days");
                $today = new DateTime();
                $today->setTime(0, 0, 0);
                $isContractActive = ($today <= $end);
            }

            // Determine user's current status category
            if ($normalizedStatus === 'payment-confirmed') {
                $user['current_status'] = 'completed';
                $user['status_label'] = '✅ Completed (Payment Confirmed)';
                $user['should_show_in_revenue'] = true; // Show but don't add to active totals
            } elseif ($normalizedStatus === 'payment-made' || $normalizedStatus === 'unpaid-payment') {
                $user['current_status'] = 'active';
                $user['status_label'] = '📈 Active (' . $normalizedStatus . ')';
                $user['should_show_in_revenue'] = true;
            } elseif ($isContractActive) {
                $user['current_status'] = 'active';
                $user['status_label'] = '📈 Active (Contract Running)';
                $user['should_show_in_revenue'] = false; // Contract active but no profit split yet
            } else {
                // Inactive users - contract ended with no profit or no contract
                $user['current_status'] = 'inactive';
                if ($profitAndLoss < 0) {
                    $user['status_label'] = '⏹️ Inactive (Loss)';
                } elseif ($profitAndLoss > 0 && $profitAndLoss <= $minProfitForSplit) {
                    $user['status_label'] = '⏹️ Inactive (Below Min Profit)';
                } elseif (empty($executionStartDate) || $executionStartDate === '0000-00-00') {
                    $user['status_label'] = '⏹️ Inactive (No Contract)';
                } else {
                    $user['status_label'] = '⏹️ Inactive (Contract Ended)';
                }
                $user['should_show_in_revenue'] = false;
            }
            
            $unpaidAge = ['ended_on' => null, 'age' => null, 'is_ended' => false];
            if ($user['has_eligible_profit'] && !empty($user['execution_start_date']) && $contractDuration > 0) {
                $unpaidAge = calculateUnpaidAge($user['execution_start_date'], $contractDuration);
            }
            $user['unpaid_payment_age'] = $unpaidAge;
            
            $rawStatus = $user['loyalties'] ?? '';
            $normalizedStatus = normalizePaymentStatus($rawStatus);
            $user['loyalties_normalized'] = $normalizedStatus;
            
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
            
            // Add to table summary ONLY for active users (not completed or inactive)
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
                
                <?php if ($currentView !== 'menu'): ?>
                    <a href="serveraccount.php?view=menu" class="back-btn">← Back to Menu</a>
                <?php endif; ?>


                
            <!-- ============================================ -->
            <!-- SECTION 10a: MENU / NAVIGATION                -->
            <!-- ============================================ -->
            <?php if ($currentView === 'menu'): ?>
                <h2> Server Dashboard</h2>
                <div class="nav-menu">
                    <a href="serveraccount.php?view=settings">
                        <span class="nav-icon">⚙️</span>
                        <span class="nav-label">
                            Server Settings
                            <span class="sub-text">Configuration &amp; Payment</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=system_config">
                        <span class="nav-icon">🖥️</span>
                        <span class="nav-label">
                            Virtual Private Servers
                            <span class="sub-text">IP &amp; System config</span>
                        </span>
                    </a>
                    <a href="serveraccount.php?view=paid_users" style="display: none;">
                        <span class="nav-icon">💰</span>
                        <span class="nav-label">
                            Revenue Dashboard
                            <span class="sub-text">Investors Revenue Share</span>
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
                    <a href="serveraccount.php?view=manual">
                        <span class="nav-icon">📚</span>
                        <span class="nav-label">
                            Manual
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
            <?php elseif ($currentView === 'system_config'): ?>
                <?php include 'system_server_config.php'; ?>
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