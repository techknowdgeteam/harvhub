<?php
    session_start();
    // mydashboard.php

    // --- Configuration and Connection ---

    // 1. Enforce a clean state on non-POST requests unless we just successfully POSTed.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        // Check if the session contains a "just redirected" flag.
        if (isset($_SESSION['prg_redirect_safe'])) {
            // Safe redirect (Post-Redirect-Get), do nothing.
            unset($_SESSION['prg_redirect_safe']);
        } else {
            // Standard GET request/Reload: Unset verification flag to force re-prompt.
            unset($_SESSION['passkey_verified']);
            unset($_SESSION['passkey_error']);
        }
    }

    // 2. Check for logged-in user email
    if (!isset($_SESSION['user_email'])) {
        header("Location: index.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    // Database credentials
    $host = "sql312.infinityfree.com";
    $dbname = "if0_40473107_harvhub";
    $user = "if0_40473107";
    $pass = "InDQmdl53FZ85";
    $tableName = "insiders";
    $serverAccountTable = "server_account";

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

    // 3. Fetch user data
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ? AND application_status = 'approved'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // 3a. Fetch Server Account Data and Dynamic Configuration
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$serverAccount) {
        $serverAccount = [
            'btc_address' => 'N/A', 
            'eth_address' => 'N/A', 
            'eth_network' => 'ERC20', 
            'usdt_address' => 'N/A', 
            'usdt_network' => 'TRC20',
            'minimum_deposit' => 0.00, 
            'brokers_link' => '',
            'contract_duration' => 30,
            'server_share_percent' => 30,
            'user_share_percent' => 70,
            'min_broker_balance' => 30,
            'min_profit_for_split' => 30,
        ];
    }
    
    // Set dynamic values from DB
    $MIN_INITIAL_DEPOSIT = (float)($serverAccount['minimum_deposit'] ?? 0.00); 
    $CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 30);
    $SERVER_SHARE_PERCENT = (int)($serverAccount['server_share_percent'] ?? 30);
    $USER_SHARE_PERCENT = (int)($serverAccount['user_share_percent'] ?? 70);
    $MIN_BROKER_BALANCE = (float)($serverAccount['min_broker_balance'] ?? 30);
    $MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);
    
    // Extract user data
    $brokerBalance = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss = (float)($user['profitandloss'] ?? 0);
    $executionStartDate = $user['execution_start_date'] ?? null;
    $loyaltiesStatus = $user['loyalties'] ?? null;
    
    // Calculate contract details
    $contractEndDate = null;
    $contractDaysLeft = 0;
    $formatted_start_date = "Not started";
    $formatted_end_date = "Not started";
    $is_contract_active = false;
    $contract_completed = false;
    
    // Calculate contract details if execution_start_date exists
    if ($executionStartDate && $executionStartDate !== '0000-00-00' && $executionStartDate !== null) {
        $start = new DateTime($executionStartDate);
        $formatted_start_date = $start->format('M d, Y');
        
        $end = clone $start;
        $end->modify("+{$CONTRACT_DURATION} days");
        $formatted_end_date = $end->format('M d, Y');
        
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $end_clone = clone $end;
        $end_clone->setTime(0, 0, 0);
        
        $interval = $today->diff($end_clone);
        $contractDaysLeft = (int)$interval->format('%r%a');
        
        // Check if contract is completed (days left <= 0)
        if ($contractDaysLeft <= 0) {
            $contract_completed = true;
            $is_contract_active = false;
        } else {
            $is_contract_active = true;
        }
    }
    
    // Initial Balance Check
    $balance_check_failed = false;
    if ($brokerBalance < $MIN_INITIAL_DEPOSIT) {
        $balance_check_failed = true;
    }

    // Extract remaining user data
    $fullName = $user['fullname'] ?? $email;
    $login = $user['login'] ?? 'N/A';
    $server = $user['server'] ?? 'N/A';
    $balanceDisplay = $user['balance_display'] ?? 'show'; 
    $broker = strtolower($user['broker'] ?? 'unknown');
    $tradesString = $user['trades'] ?? ''; 

    // --- BALANCE CALCULATIONS (UPDATED AS REQUESTED) ---
    // Deposit Balance is always the broker_balance value (the initial deposit)
    $depositBalance = $brokerBalance;
    
    // Current Balance = broker_balance + profitandloss (profitandloss can be negative or positive)
    // If profitandloss is negative, it will automatically subtract from broker_balance
    // If profitandloss is positive, it will automatically add to broker_balance
    $currentBalance = $brokerBalance + $profitAndLoss;
    
    // Calculate Profit Split values
    $profitToSplit = max(0, $profitAndLoss);
    $serverShare = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
    $userShare = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);
    
    // --- Determine Deposit Link ---
    $brokerLink = '';
    $brokerLinks = [];
    
    if (!empty($serverAccount['brokers_link'])) {
        $linkParts = explode(',', $serverAccount['brokers_link']);
        
        foreach ($linkParts as $part) {
            $part = trim($part);
            if (strpos($part, ':') !== false) {
                list($keyRaw, $link) = explode(':', $part, 2);
                $key = trim(strtolower($keyRaw));
                
                $linkName = strtolower(basename(parse_url('http://' . trim($link), PHP_URL_HOST) ?? ''));
                $linkName = str_replace(array('.com', '.co', '.net'), '', $linkName);
                
                if (!empty($linkName)) {
                     $brokerLinks[$linkName] = trim($link);
                }
                
                $brokerLinks[$key] = trim($link);
            }
        }
    }
    
    $userBrokerNormalized = strtolower($broker);

    if (!empty($userBrokerNormalized) && isset($brokerLinks[$userBrokerNormalized])) {
        $brokerLink = $brokerLinks[$userBrokerNormalized];
    } elseif (isset($brokerLinks['insiders'])) {
        $brokerLink = $brokerLinks['insiders'];
    }
    
    $brokerLink = (strpos($brokerLink, '://') === false && !empty($brokerLink)) ? 'https://' . $brokerLink : $brokerLink;
    $brokerTarget = !empty($brokerLink) ? htmlspecialchars($brokerLink) : 'about:blank';

    // --- NEW LOYALTY LOGIC BASED ON REQUIREMENTS (IN EXACT ORDER) ---
    $showProfitSplit = false;
    $showWithdrawButtons = false;
    $loyalty_text = "";
    $loyalty_status_message = "";
    $dashboard_disclaimer = "";
    $show_reenroll_button = false;
    $show_payment_note = false;
    
    $loyalty_btn_action = "disabled";
    $loyalty_btn_text = "Not available";
    $loyalty_btn_class = "";
    
    $is_execution_empty = ($executionStartDate === null || $executionStartDate === '0000-00-00');

    // CASE 1: Balance check failed - minimum deposit not met (Keep this as top priority)
    if ($balance_check_failed) {
        $dashboard_disclaimer = "You need to deposit minimum of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . " to participate.";
        $loyalty_text = "Your account is not yet eligible. Please fund your broker account with a minimum of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . ".";
        $loyalty_status_message = "Minimum Deposit Required";
        
        $loyalty_btn_text = "Deposit $" . number_format($MIN_INITIAL_DEPOSIT, 2);
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"window.open('{$brokerTarget}', '_blank')\"";
    
    // CASE 2: loyalties is 'justjoined' - New member welcome
    } elseif ($loyaltiesStatus === 'justjoined') {
        $dashboard_disclaimer = "Welcome to HarvHub!";
        $loyalty_text = "Welcome aboard! You're now a member of the HarvHub community. Get ready to start your trading journey!";
        $loyalty_status_message = "Welcome New Member!";
        $show_reenroll_button = true;
        $loyalty_btn_text = "Re-enroll";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
    
    // CASE 3: loyalties is null AND execution start date is empty AND profit is positive
    } elseif ($loyaltiesStatus === null && $is_execution_empty && $profitAndLoss > 0) {
        $dashboard_disclaimer = "Profit earned - Split required";
        $loyalty_text = "You have earned a profit of $" . number_format($profitAndLoss, 2) . "! Please complete the profit split to continue.";
        $loyalty_status_message = "Profit Split Required";
        $showWithdrawButtons = true;
        $showProfitSplit = true;
        $loyalty_btn_text = "View Profit Split";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('profitSplitModal').classList.add('active')\"";
    
    // CASE 4: loyalties is null AND execution start date is empty AND profit is negative
    } elseif ($loyaltiesStatus === null && $is_execution_empty && $profitAndLoss < 0) {
        $dashboard_disclaimer = "Loss incurred - Keep going!";
        $loyalty_text = "Don't give up! Every loss is a learning opportunity. You can re-enroll for a new {$CONTRACT_DURATION}-day contract and bounce back stronger!";
        $loyalty_status_message = "Ready for Another Attempt";
        $show_reenroll_button = true;
        $loyalty_btn_text = "Re-enroll";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
    
    // CASE 5: loyalties is 'payment-made' - Payment pending
    } elseif ($loyaltiesStatus === 'payment-made') {
        $dashboard_disclaimer = "Payment submitted for verification.";
        $loyalty_text = "Your payment has been recorded. Once the server confirms the payment, you will be able to re-enroll for a new contract.";
        $loyalty_status_message = "Payment Pending Confirmation";
        $show_payment_note = true;
        $loyalty_btn_text = "Awaiting Confirmation";
        $loyalty_btn_class = "btn-loyalty-paid";
    
    // CASE 6: loyalties is 'payment-confirmed' - Reset and ready
    } elseif ($loyaltiesStatus === 'payment-confirmed') {
        // Reset execution_start_date to NULL and profitandloss to 0
        $upd = $pdo->prepare("UPDATE $tableName SET execution_start_date = NULL, profitandloss = 0, loyalties = NULL WHERE email = ?");
        $upd->execute([$email]);
        
        // Refresh user data after reset
        $executionStartDate = null;
        $profitAndLoss = 0;
        $loyaltiesStatus = null;
        
        $dashboard_disclaimer = "Ready to start a new contract.";
        $loyalty_text = "Here we go again! Your payment has been confirmed. You can now start a new trading contract.";
        $loyalty_status_message = "Ready to Re-enroll";
        $show_reenroll_button = true;
        $loyalty_btn_text = "Re-enroll";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
    
    // CASE 7: Contract completed (execution date met/expired) - Keep this for backward compatibility
    } elseif ($contract_completed) {
        if ($profitAndLoss > 0) {
            // Contract completed with PROFIT - show withdraw/pay buttons
            $dashboard_disclaimer = "Contract completed with profit.";
            $loyalty_text = "Your contract period has ended with a profit of $" . number_format($profitAndLoss, 2) . ". Please complete the profit split to continue.";
            $loyalty_status_message = "Contract Ended - Profit Split Required";
            $showWithdrawButtons = true;
            $showProfitSplit = true;
            $loyalty_btn_text = "View Profit Split";
            $loyalty_btn_class = "btn-loyalty-action";
            $loyalty_btn_action = "onclick=\"document.getElementById('profitSplitModal').classList.add('active')\"";
            
        } elseif ($profitAndLoss < 0) {
            // Contract completed with LOSS - show encouragement and re-enroll
            $dashboard_disclaimer = "Contract completed with loss.";
            $loyalty_text = "Don't give up! Every loss is a learning opportunity. You can re-enroll for a new {$CONTRACT_DURATION}-day contract and bounce back stronger!";
            $loyalty_status_message = "Contract Ended - Ready for New Start";
            $show_reenroll_button = true;
            $loyalty_btn_text = "Re-enroll";
            $loyalty_btn_class = "btn-loyalty-action";
            $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
            
        } else {
            // Contract completed with zero profit
            $dashboard_disclaimer = "Contract completed.";
            $loyalty_text = "Your contract period has ended. You can re-enroll for a new {$CONTRACT_DURATION}-day contract.";
            $loyalty_status_message = "Contract Ended";
            $show_reenroll_button = true;
            $loyalty_btn_text = "Re-enroll";
            $loyalty_btn_class = "btn-loyalty-action";
            $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
        }
    
    // CASE 8: Active contract running
    } elseif ($is_contract_active) {
        $dashboard_disclaimer = "Trading is active.";
        $loyalty_text = "{$contractDaysLeft} days left.";
        $loyalty_status_message = "Contract Active";
        $loyalty_btn_text = "Active";
        $loyalty_btn_class = "btn-loyalty-confirmed";
    
    // CASE 9: Default - no active contract, no special status
    } else {
        $dashboard_disclaimer = "No active contract.";
        $loyalty_text = "You don't have an active contract. Click Re-enroll to start a new {$CONTRACT_DURATION}-day trading contract.";
        $loyalty_status_message = "Ready to Start";
        $show_reenroll_button = true;
        $loyalty_btn_text = "Re-enroll";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\"";
    }

    // Parse Trades Data
    $tradesData = [
        'Trades' => 0,
        'won' => 0,
        'lost' => 0,
        'symbolsthatwon' => [],
        'symbolsthatlost' => []
    ];
    
    if (!empty($tradesString) && strtolower($tradesString) !== 'none') {
        $sections = preg_split('/,(?![^()]*\))/', $tradesString);
        foreach ($sections as $section) {
            $section = trim($section);
            if (preg_match('/^(\d+):(\w+)$/', $section, $matches)) {
                $value = (int)$matches[1];
                $key = strtolower($matches[2]); 
                if ($key === 'trades') {
                    $tradesData['Trades'] = $value;
                } elseif ($key === 'won') {
                    $tradesData['won'] = $value;
                } elseif ($key === 'lost') {
                    $tradesData['lost'] = $value;
                }
            } elseif (preg_match('/^(symbolsthat(lost|won)):\((.*)\)$/i', $section, $matches)) {
                $key = strtolower($matches[1]); 
                $symbolsString = $matches[3]; 
                if (strtolower($symbolsString) !== 'none' && !empty($symbolsString)) {
                    $symbolParts = explode(', ', $symbolsString);
                    $symbolsArray = [];
                    foreach ($symbolParts as $part) {
                        if (strpos($part, ':') !== false) {
                            list($symbol, $amount) = array_map('trim', explode(':', $part, 2));
                            $symbolsArray[] = [
                                'symbol' => htmlspecialchars($symbol),
                                'amount' => htmlspecialchars($amount)
                            ];
                        }
                    }
                    if (!empty($symbolsArray)) {
                        shuffle($symbolsArray);
                        $tradesData[$key] = $symbolsArray;
                    }
                }
            }
        }
    }
    
    $tradesCountDisplay = number_format($tradesData['Trades']);
    $wonCountDisplay = number_format($tradesData['won']);
    $lostCountDisplay = number_format($tradesData['lost']);

    // --- POST Handling ---

    // Create Passkey
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_passkey'])) {
        if (!empty($_POST['new_passkey'])) {
            $passkey = password_hash($_POST['new_passkey'], PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE $tableName SET passkey = ? WHERE email = ?");
            $upd->execute([$passkey, $email]);
            $_SESSION['passkey_verified'] = true;
            $_SESSION['prg_redirect_safe'] = true;
        }
        header("Location: mydashboard.php");
        exit;
    }

    // Verify Passkey
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_passkey'])) {
        if (password_verify($_POST['passkey'], $user['passkey'] ?? '')) {
            $_SESSION['passkey_verified'] = true;
            unset($_SESSION['passkey_error']); 
        } else {
            $_SESSION['passkey_error'] = "Incorrect passkey."; 
            unset($_SESSION['passkey_verified']);
        }
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: mydashboard.php"); 
        exit;
    }

    // Toggle Balance Display
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_balance_display'])) {
        $currentStatus = $user['balance_display'];
        $newStatus = ($currentStatus === 'show') ? 'hide' : 'show';

        $upd = $pdo->prepare("UPDATE $tableName SET balance_display = ? WHERE email = ?");
        $upd->execute([$newStatus, $email]);

        if ($newStatus === 'show') {
            unset($_SESSION['passkey_verified']); 
        }
        
        unset($_SESSION['passkey_error']); 
        $_SESSION['prg_redirect_safe'] = true;

        header("Location: mydashboard.php"); 
        exit;
    }
    
    // Handle Server Payment (User clicked "Pay server" and confirmed)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_confirm_payment'])) {
        $coin = $_POST['payment_coin'] ?? 'N/A';
        $amount = $_POST['server_share_amount'] ?? 0.00;
        $datetime = date('Y-m-d H:i:s');
        
        $paymentDetails = "Amount: $" . number_format($amount, 2) . ", Coin: " . htmlspecialchars($coin) . ", Confirmed_at: " . $datetime;
        
        // Set loyalties to 'payment-made', keep profitandloss as is for record, save payment details
        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = 'payment-made', paymentdetails = ? WHERE email = ?");
        $upd->execute([$paymentDetails, $email]);
        
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: mydashboard.php"); 
        exit;
    }

    // Handle Re-enrollment - Resets everything for new contract
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reenroll'])) {
        $today = date('Y-m-d');
        
        // Reset: loyalties to NULL, profitandloss to 0, set new execution_start_date
        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = NULL, profitandloss = 0, execution_start_date = ? WHERE email = ?");
        $upd->execute([$today, $email]);
        
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: mydashboard.php"); 
        exit;
    }

    // Disconnect Account
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_disconnect'])) {
        $pdo->prepare("UPDATE $tableName SET application_status = 'blacklisted' WHERE email = ?")
             ->execute([$email]);
            
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

    // Logout
    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header("Location: index.php");
        exit;
    }

    $show_passkey_form = empty($user['passkey']);
    $passkey_verified = $_SESSION['passkey_verified'] ?? false;
    $passkey_error = $_SESSION['passkey_error'] ?? null; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HarvHub Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    :root {
        --bg-light: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --bg-dark: linear-gradient(135deg, #141e30 0%, #243b55 100%);
        --text-light: #1e293b;
        --text-dark: #f1f5f9;
        --card-light: rgba(255, 255, 255, 0.95);
        --card-dark: rgba(30, 41, 59, 0.95);
        --accent: #10b981;
        --accent-hover: #059669;
        --danger: #ef4444;
        --warning: #f59e0b;
        --info: #3b82f6;
        --success: #10b981;
        --glass-border: rgba(255, 255, 255, 0.2);
        --shadow-sm: 0 10px 40px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.15);
        --shadow-hover: 0 30px 70px rgba(16, 185, 129, 0.2);
        
        /* Passkey modal original colors */
        --passkey-bg: rgba(255, 255, 255, 0.95);
        --passkey-text: #1c1e21;
        --error-color: #ff6b6b;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg-light: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --text-light: #f1f5f9;
            --card-light: rgba(30, 41, 59, 0.95);
            --glass-border: rgba(255, 255, 255, 0.1);
            /* Preserve passkey dark mode colors */
            --passkey-bg: rgba(40, 40, 40, 0.9);
            --passkey-text: #e4e6eb;
        }
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    html, body {
        height: 100%;
        background: var(--bg-light);
        color: var(--text-light);
        overflow-x: hidden;
        transition: background 0.3s ease;
    }

    body {
        overflow-y: hidden;
        position: relative;
    }

    /* Animated background particles (only for dashboard, not passkey) */
    body:not(.passkey-active)::before {
        content: "";
        position: fixed;
        inset: 0;
        background: 
            radial-gradient(circle at 20% 30%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
            radial-gradient(circle at 80% 70%, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
            repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 8px);
        pointer-events: none;
        z-index: -1;
        animation: gradientShift 15s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 0.8; }
    }

    /* ===== PASSKEY MODAL - PRESERVED ORIGINAL STYLES ===== */
    .passkey-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .passkey-screen {
        background: var(--passkey-bg);
        color: var(--passkey-text);
        backdrop-filter: blur(12px);
        padding: 3rem 2.5rem;
        border-radius: 20px;
        width: 100%;
        max-width: 480px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.1);
    }

    .passkey-screen h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: var(--passkey-text);
        background: none;
        -webkit-text-fill-color: var(--passkey-text);
    }

    .passkey-screen p {
        margin: 1.5rem 0;
        opacity: 0.9;
        font-size: 1rem;
    }

    .passkey-screen input[type="password"] {
        width: 100%;
        padding: 16px;
        margin: 20px 0;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        font-size: 1.1rem;
        text-align: center;
        background: rgba(0,0,0,0.05);
        color: var(--passkey-text);
    }

    @media (prefers-color-scheme: dark) {
        .passkey-screen input[type="password"] { 
            background: rgba(255,255,255,0.1); 
        }
    }

    .passkey-screen .error-message { 
        color: var(--error-color); 
        margin: -10px 0 10px; 
        font-weight: bold; 
    }

    .passkey-screen .btn-full {
        width: 100%;
        padding: 16px;
        background: var(--accent);
        color: #000;
        border: none;
        border-radius: 12px;
        font-weight: bold;
        font-size: 1.1rem;
        cursor: pointer;
        transition: opacity 0.3s;
    }

    .passkey-screen .btn-full:hover {
        opacity: 0.9;
    }

    .passkey-screen a {
        display: block;
        margin: 20px 0;
        color: var(--accent);
        font-size: 0.95rem;
        text-decoration: none;
    }

    .passkey-screen a:hover {
        text-decoration: underline;
    }

    .passkey-screen a[href*="logout"] {
        color: #ff6b6b;
    }
    /* ===== END PASSKEY MODAL STYLES ===== */

    .dashboard-wrapper {
        width: 100%;
        max-width: 1300px;
        height: 100vh;
        margin: 0 auto;
        padding: 2rem;
        overflow-y: auto;
        scroll-behavior: smooth;
        -ms-overflow-style: none;
        scrollbar-width: none;
        position: relative;
    }

    .dashboard-wrapper::-webkit-scrollbar {
        display: none;
    }

    h1 {
        font-size: 3.5rem;
        font-weight: 800;
        background: linear-gradient(135deg, var(--accent) 0%, #3b82f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-align: center;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
        animation: fadeInDown 0.6s ease;
    }

    .welcome {
        text-align: center;
        font-size: 1.25rem;
        margin-bottom: 2rem;
        opacity: 0.9;
        animation: fadeInUp 0.6s ease 0.2s both;
    }

    .welcome strong {
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin: 2rem 0;
        animation: fadeInUp 0.6s ease 0.4s both;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        h1 {
            font-size: 2.5rem;
        }
        
        .dashboard-wrapper {
            padding: 1rem;
        }
    }

    /* Enhanced Stat Cards */
    .stat-card {
        position: relative;
        background: var(--card-light);
        backdrop-filter: blur(20px);
        padding: 1.75rem;
        border-radius: 24px;
        text-align: center;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-sm);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        overflow: hidden;
        animation: cardAppear 0.5s ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--accent), var(--info), var(--accent));
        transform: translateX(-100%);
        transition: transform 0.5s ease;
    }

    .stat-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: var(--shadow-hover);
        border-color: var(--accent);
    }

    .stat-card:hover::before {
        transform: translateX(0);
    }

    .stat-card h3 {
        font-size: 1.1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.7;
        margin-bottom: 1rem;
    }

    .stat-card h2 {
        font-size: 2.8rem;
        font-weight: 800;
        line-height: 1.2;
        margin: 0.5rem 0;
        transition: all 0.3s ease;
        position: relative;
        display: inline-block;
    }

    .stat-card h2::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--accent), var(--info));
        transition: width 0.3s ease;
        border-radius: 2px;
    }

    .stat-card:hover h2::after {
        width: 50%;
    }

    /* Balance Toggle Button */
    .balance-toggle-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--glass-border);
        color: var(--text-light);
        font-size: 1.25rem;
        cursor: pointer;
        padding: 8px;
        border-radius: 12px;
        opacity: 0.6;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
        z-index: 10;
    }

    .balance-toggle-btn:hover {
        opacity: 1;
        background: var(--accent);
        color: white;
        transform: rotate(15deg);
    }

    /* Profit/Loss Colors with Animation */
    .profit-positive {
        color: var(--success) !important;
        text-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
    }

    .profit-negative {
        color: var(--danger) !important;
        text-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
    }

    /* Stat Details */
    .stat-details-info {
        font-size: 0.9rem;
        opacity: 0.6;
        margin-top: 1rem;
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .stat-card:hover .stat-details-info {
        opacity: 0.9;
        background: rgba(16, 185, 129, 0.1);
    }

    /* Trades Card Special Styling */
    .stat-card.trades-card {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));
        border: 2px solid transparent;
        background-clip: padding-box;
        position: relative;
    }

    .stat-card.trades-card::before {
        content: '';
        position: absolute;
        inset: -2px;
        background: linear-gradient(135deg, var(--accent), var(--info));
        border-radius: 26px;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: -1;
    }

    .stat-card.trades-card:hover::before {
        opacity: 0.3;
    }

    .trades-count {
        font-size: 4.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
        margin-bottom: 0.5rem;
        animation: pulse 2s infinite;
    }

    .trades-count span {
        font-size: 1rem;
        font-weight: 500;
        opacity: 0.7;
        color: var(--text-light);
        background: none;
        -webkit-text-fill-color: var(--text-light);
        margin-top: 0.5rem;
    }

    .trades-won-lost {
        display: flex;
        justify-content: space-around;
        margin: 1.5rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 16px;
    }

    .trades-detail-item {
        font-size: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .trades-detail-item strong {
        font-size: 1.5rem;
        display: block;
        margin-top: 0.25rem;
    }

    .btn-view-history {
        margin-top: 1rem;
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, var(--accent), var(--info));
        color: white;
        border: none;
        border-radius: 50px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.9rem;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-view-history:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
    }

    /* Loyalty Card */
    .stat-card.loyalty-card {
        grid-column: 1 / -1;
        max-width: 800px;
        margin: 2rem auto;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(59, 130, 246, 0.15));
        border: 2px solid rgba(16, 185, 129, 0.3);
    }

    .loyalty-status-msg {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .loyalty-card p {
        font-size: 1.1rem;
        line-height: 1.6;
        opacity: 0.9;
        max-width: 600px;
        margin: 0 auto 1rem;
    }

    .contract-dates {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0.5rem;
        backdrop-filter: blur(10px);
    }

    .contract-days-left {
        display: inline-block;
        padding: 0.25rem 1rem;
        background: var(--accent);
        color: white;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    /* Loyalty Buttons */
    .loyalty-card button {
        margin: 1.5rem auto 0;
        padding: 1rem 3rem;
        font-size: 1.1rem;
        font-weight: 700;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-loyalty-action {
        background: linear-gradient(135deg, var(--accent), var(--info)) !important;
        color: white !important;
    }

    .btn-loyalty-action:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5) !important;
    }

    .btn-loyalty-paid {
        background: linear-gradient(135deg, #6b7280, #4b5563) !important;
        color: white !important;
        cursor: not-allowed !important;
        opacity: 0.7;
    }

    .btn-loyalty-confirmed {
        background: linear-gradient(135deg, var(--success), #059669) !important;
        color: white !important;
        cursor: default !important;
    }

    /* Dashboard Disclaimer */
    .dashboard-disclaimer {
        text-align: center;
        margin: 1.5rem auto;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(16, 185, 129, 0.2));
        border: 1px solid rgba(16, 185, 129, 0.3);
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1rem;
        max-width: 600px;
        backdrop-filter: blur(10px);
        animation: slideIn 0.5s ease;
    }

    /* Encouragement Note */
    /* Replace these existing styles */
    .note-btndanger{
        display: flex;
        justify-content: center
        width: 100%;
    }
    .note-btndanger-block{
        width: auto;
    }

    /* With these updated styles */
    .note-btndanger {
        display: flex;
        justify-content: center;
        width: 100%;
        margin: 20px 0;
    }

    .note-btndanger-block {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        max-width: 600px;
        width: 100%;
    }

    .note {
        margin-bottom: 15px;
        opacity: 0.8;
        line-height: 1.6;
    }
    .encouragement-note {
        text-align: center;
        margin: 1rem auto;
        padding: 1rem;
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(239, 68, 68, 0.2));
        border: 1px solid var(--warning);
        border-radius: 16px;
        font-style: italic;
        font-size: 1.1rem;
        max-width: 800px;
        animation: pulse 2s infinite;
    }

    /* Danger Button */
    .btn-danger {
        display: block;
        margin-bottom: 10px;
        margin-top: 10px;
        padding: 1rem 1rem;
        background: linear-gradient(135deg, var(--danger), #dc2626);
        color: white;
        border: none;
        border-radius: 20px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 10px 30px rgba(239, 68, 68, 0.5);
    }

    /* Logout Link */
    .logout-link-p{
        margin-bottom: 60px;
    }
    .logout-link {
        display: block;
        text-align: center;
        margin-top: 1rem;
        padding: 0.5rem;
        color: var(--text-light);
        text-decoration: none;
        opacity: 0.6;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .logout-link:hover {
        opacity: 1;
        color: var(--danger);
        transform: translateY(-2px);
    }

    /* Blur Mode Effect */
    .dashboard-wrapper.blur-mode .stat-card h2 {
        filter: blur(8px);
        transition: filter 0.3s ease;
        user-select: none;
    }

    .dashboard-wrapper.blur-mode .stat-card:hover h2 {
        filter: blur(6px);
    }

    /* Modal Styles (for non-passkey modals) */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        align-items: center;
        justify-content: center;
        z-index: 999;
        padding: 1rem;
        animation: fadeIn 0.3s ease;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-light);
        backdrop-filter: blur(20px);
        padding: 2.5rem;
        border-radius: 24px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid var(--glass-border);
        box-shadow: var(--shadow-lg);
        animation: modalSlideUp 0.4s ease;
    }

    .modal-content h2 {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, var(--accent), var(--info));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Split Items in Modal */
    .split-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 1.5rem;
        border-radius: 16px;
        margin: 1rem 0;
        border: 1px solid var(--glass-border);
        transition: all 0.3s ease;
    }

    .split-item:hover {
        transform: translateY(-3px);
        border-color: var(--accent);
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
    }

    .split-item h4 {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    /* Coin Selector */
    .coin-selector {
        display: flex;
        gap: 1rem;
        margin: 2rem 0;
    }

    .coin-selector label {
        flex: 1;
        padding: 1rem;
        text-align: center;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid transparent;
        border-radius: 12px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .coin-selector input[type="radio"]:checked + label {
        background: linear-gradient(135deg, var(--accent), var(--info));
        color: white;
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
    }

    .coin-selector input[type="radio"] {
        display: none;
    }

    /* Crypto Address Display */
    .btc-address {
        display: block;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 0.9rem;
        word-break: break-all;
        border: 1px dashed var(--accent);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btc-address:hover {
        background: rgba(16, 185, 129, 0.1);
        transform: scale(1.02);
    }

    /* History Section */
    .history-section {
        margin-top: 1rem;
        max-height: 300px;
        overflow-y: auto;
        padding: 1rem;
        background: rgba(0, 0, 0, 0.05);
        border-radius: 12px;
    }

    .history-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        border-radius: 8px;
    }

    .history-item:hover {
        background: rgba(16, 185, 129, 0.1);
        transform: translateX(5px);
    }

    .history-symbol {
        font-weight: 600;
    }

    .history-amount-won {
        color: var(--success);
        font-weight: 700;
    }

    .history-amount-lost {
        color: var(--danger);
        font-weight: 700;
    }

    /* Modal Actions */
    .modal-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .modal-actions button {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-actions button:hover {
        transform: translateY(-2px);
    }

    /* Animations */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes cardAppear {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    @keyframes modalSlideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.02);
        }
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .stat-card h2 {
            font-size: 2rem;
        }
        
        .trades-count {
            font-size: 3rem;
        }
        
        .loyalty-status-msg {
            font-size: 1.2rem;
        }
        
        .modal-content {
            padding: 1.5rem;
        }
        
        .coin-selector {
            flex-direction: column;
        }
    }

    /* Loading States */
    .loading {
        position: relative;
        overflow: hidden;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        animation: loading 1.5s infinite;
    }

    @keyframes loading {
        0% {
            transform: translateX(-100%);
        }
        100% {
            transform: translateX(100%);
        }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--accent), var(--info));
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--accent-hover), #2563eb);
    }
</style>
</head>
<body>

    <?php if ($show_passkey_form): ?>
        <div class="passkey-overlay">
            <div class="passkey-screen">
                <h2>Create Your Passkey</h2>
                <p style="margin:1.5rem 0; opacity:0.9;">Secure your HarvHub dashboard access</p>
                <form method="POST">
                    <input type="password" name="new_passkey" placeholder="Enter strong passkey" required autofocus>
                    <button type="submit" name="create_passkey" class="btn-full">Save & Continue</button>
                </form>
            </div>
        </div>
    <?php elseif (!$passkey_verified): ?>
        <div class="passkey-overlay">
            <div class="passkey-screen">
                <h2>Welcome Back</h2>
                <p style="margin:1.5rem 0; opacity:0.9;">Enter your passkey to access dashboard</p>
                <form method="POST">
                    <input type="password" name="passkey" placeholder="Your passkey" required autofocus>
                    <?php if ($passkey_error): ?>
                        <p class="error-message"><?= htmlspecialchars($passkey_error) ?></p>
                    <?php endif; ?>
                    <button type="submit" name="verify_passkey" class="btn-full">Enter Dashboard</button>
                </form>
                <a href="mailto:support@harvhub.com" style="display:block; margin:20px 0; color:var(--accent); font-size:0.95rem;">Forgot passkey?</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-wrapper <?= $balanceDisplay === 'hide' && $passkey_verified ? 'blur-mode' : '' ?>">
        <h1>🌾HarvHub Dashboard</h1>
        <p class="welcome">Hello, <strong><?= htmlspecialchars($fullName) ?></strong></p>

        <?php if (!empty($dashboard_disclaimer)): ?>
            <p class="dashboard-disclaimer">
                <?= htmlspecialchars($dashboard_disclaimer) ?>
            </p>
        <?php endif; ?>

        <?php if ($profitAndLoss < 0 && ($loyaltiesStatus === null && $is_execution_empty)): ?>
            <p class="encouragement-note">
                🌟 Don't give up! Every loss is a setup for a greater comeback. Your next contract could be your breakthrough!
            </p>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="toggle_balance_display" value="1">
                    <button type="submit" title="<?= $balanceDisplay === 'show' ? 'Hide Balance' : 'Show Balance' ?>" class="balance-toggle-btn">
                        <?php if ($balanceDisplay === 'show'): ?>
                            👁️
                        <?php else: ?>
                            🔒 
                        <?php endif; ?>
                    </button>
                </form>
                
                <h3> Deposit Balance</h3>
                <div class="stat-details-info">
                    <?= htmlspecialchars($login) ?>
                    <?= htmlspecialchars($server) ?>
                </div>
                <h2>$<?= number_format($depositBalance, 2) ?></h2>
                <div class="stat-details-info">
                    🌱 Seed
                </div>
            </div>
            
            <div class="stat-card">
                <h3>Profit & Loss</h3>
                <h2 class="<?= $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    $<?= number_format($profitAndLoss, 2) ?>
                </h2>
                <div class="stat-details-info">
                    🌶️🥕Yield
                </div>
            </div>

            <div class="stat-card">
                <h3>Current Balance</h3>
                <h2 class="<?= $currentBalance >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    $<?= number_format($currentBalance, 2) ?>
                </h2>
                <div class="stat-details-info">
                    🚜 Harvest
                </div>
            </div>
            
            <div class="stat-card trades-card" style="grid-column: 1 / -1;"> 
                <h3>Trade Summary</h3>
                <div class="trades-layout-container">
                    <div class="trades-count">
                        <?= $tradesCountDisplay ?>
                        <span>Trades</span>
                    </div>
                    
                    <div class="trades-won-lost">
                        <div class="trades-detail-item left">
                            Won: 
                            <strong style="color:var(--success-color);"><?= $wonCountDisplay ?></strong>
                        </div>
                        
                        <div class="trades-detail-item right">
                            Lost: 
                            <strong style="color:var(--error-color);"><?= $lostCountDisplay ?></strong>
                        </div>
                    </div>
                    <button class="btn-view-history" onclick="document.getElementById('tradeHistoryModal').classList.add('active')">
                        Markets 
                    </button>
                </div>
            </div>
            
            <div class="stat-card loyalty-card">
                <span class="loyalty-status-msg"><?= htmlspecialchars($loyalty_status_message) ?></span>
                <p><?= htmlspecialchars($loyalty_text) ?></p>

                <p><strong><?= $CONTRACT_DURATION ?> days contract duration</strong></p>

                <?php if ($executionStartDate && $executionStartDate !== '0000-00-00'): ?>
                    <span class="contract-dates">
                        Started: <?= htmlspecialchars($formatted_start_date) ?> | Ends: <?= htmlspecialchars($formatted_end_date) ?>
                    </span>
                    <?php if ($contractDaysLeft > 0): ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($show_payment_note): ?>
                    <p style="color: var(--info-color); margin-top: 10px; font-style: italic;">
                        ⏳ Your payment is on review. Once confirmed by the server, you'll be able to re-enroll.
                    </p>
                <?php endif; ?>
                
                <?php if (!$show_payment_note): ?>
                    <button 
                        <?= $loyalty_btn_action ?>
                        class="<?= htmlspecialchars($loyalty_btn_class) ?>"
                    >
                        <?= htmlspecialchars($loyalty_btn_text) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
       <div class="note-btndanger">
            <div class="note-btndanger-block">
                <p class="note">
                    Your PnL is updated every 24 hours.<br>
                    Automated execution runs 24/7 on your connected account.
                </p>

                <button class="btn-danger" onclick="document.getElementById('disconnectModal').classList.add('active')">
                    Disconnect My Account
                </button>
                <p class="logout-link-p">
                <a href="?logout=1" style="color:#ff6b6b;">← Logout</a></p>
            </div>
        </div>
        
        <a href="?logout=1" class="logout-link">Logout</a>
    </div>

    <!-- Modals -->
    <div id="disconnectModal" class="modal">
        <div class="modal-content">
            <h2 style="color:#ff6b6b;">Disconnect Account?</h2>
            <p style="margin:1.5rem 0; line-height:1.6;">
                This action will disconnect your account from trading activities permanently.
            </p>
            <div class="modal-actions"> 
                <button onclick="this.closest('.modal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Cancel
                </button>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="confirm_disconnect"
                        style="background:#e74c3c; color:white; border:none;">
                        Yes, Disconnect
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="profitSplitModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--info-color);">Profit Split Required</h2>
            
            <p style="margin-bottom: 2rem; opacity: 0.8;">
                Your contract has ended with a profit of $<?= number_format($profitToSplit, 2) ?>.
            </p>
            <p class="split-total">
                Total Profit: $<?= number_format($profitToSplit, 2) ?>
            </p>

            <div class="split-container">
                <div class="split-item">
                    <h4 style="color:var(--success-color);"><?= $USER_SHARE_PERCENT ?>%</h4>
                    <p>Your Share</p>
                    <h4 style="color:var(--success-color);">$<?= number_format($userShare, 2) ?></h4>
                    <button class="btn-withdraw" 
                            onclick="window.open('<?= $brokerTarget ?>', '_blank')"
                            style="background:#2ecc71; color:white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; display: block; width: 100%;">
                        Withdraw Your Share
                    </button>
                    <small style="display:block; margin-top:10px; opacity:0.6;">Withdraw your $<?= number_format($userShare, 2) ?> profit share</small>
                </div>
                <div class="split-item">
                    <h4 style="color:var(--success-color);"><?= $SERVER_SHARE_PERCENT ?>%</h4>
                    <p>Server Share</p>
                    <h4 style="color:var(--success-color);">$<?= number_format($serverShare, 2) ?></h4>
                    <button class="btn-pay" onclick="document.getElementById('profitSplitModal').classList.remove('active'); document.getElementById('paymentModal').classList.add('active');"
                            style="padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; display: block; width: 100%;">
                        Pay Server Share
                    </button>
                    <small style="display:block; margin-top:10px; opacity:0.6;">Pay $<?= number_format($serverShare, 2) ?> to remain eligible</small>
                </div>
            </div>

            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--accent);">Pay Server Share</h2>
            <p style="margin:1rem 0; opacity:0.8;">
                Send $<?= number_format($serverShare, 2) ?> worth of the selected cryptocurrency
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
                <span class="btc-address" id="paymentAddress">N/A</span>
            </div>
            
            <button class="btn-full btn-paid" id="copyAddressBtn">
                Copy Address
            </button>

            <label class="checkbox-container">
                <input type="checkbox" id="paymentConfirmationCheck" onchange="togglePaidButton()">
                I have made the payment
            </label>

            <button class="btn-full btn-paid" id="confirmPaidBtn" disabled onclick="triggerFinalConfirmation()">
                Confirm Payment
            </button>
            
            <p class="disclaimer">Click only after payment has been successfully sent. Your payment will be verified by the server.</p>
            
            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <div id="finalConfirmationModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--success-color);">Final Confirmation</h2>
            <p style="margin:1.5rem 0; line-height:1.6;">
                Confirm that you have sent <strong id="finalConfirmAmount">$0.00</strong> to the 
                <strong id="finalConfirmCoin">N/A</strong> address.
            </p>
            
            <div class="modal-actions"> 
                <button onclick="document.getElementById('finalConfirmationModal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Cancel
                </button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="final_confirm_payment" value="1">
                    <input type="hidden" name="server_share_amount" id="formServerShareAmount" value="">
                    <input type="hidden" name="payment_coin" id="formPaymentCoin" value="">
                    <button type="submit"
                        style="background:var(--success-color); color:#000; border:none;">
                        Yes, I've Paid
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="reenrollModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--info-color);">Start New Contract</h2>
            <p style="margin:1.5rem 0; line-height:1.6;">
                Start a new <?= $CONTRACT_DURATION ?>-day trading contract from today.
                <?php if ($profitAndLoss < 0): ?>
                    <br><br>🌟 Remember: Every successful trader faced losses. This is your chance for a fresh start!
                <?php endif; ?>
            </p>
            <div class="modal-actions"> 
                <button onclick="this.closest('.modal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Cancel
                </button>
                <form method="POST" style="display:inline;">
                    <button type="submit" name="confirm_reenroll"
                        style="background:var(--success-color); color:#000; border:none;">
                        Start New Contract
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="tradeHistoryModal" class="modal">
        <div class="modal-content">
            <h2>Trade History Summary</h2>
            <p style="margin:1rem 0; opacity:0.8;">Currency pairs that won/lost</p>

            <h3 style="color:var(--success-color); margin-top:2rem;">Won Trades (<?= count($tradesData['symbolsthatwon']) ?> Symbols)</h3>
            <div class="history-section">
                <?php if (!empty($tradesData['symbolsthatwon'])): ?>
                    <?php foreach ($tradesData['symbolsthatwon'] as $trade): ?>
                        <div class="history-item">
                            <span class="history-symbol"><?= $trade['symbol'] ?></span>
                            <span class="history-amount-won">+<?= ltrim($trade['amount'], '+-') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; opacity: 0.7;">No winning symbol data available.</p>
                <?php endif; ?>
            </div>
            
            <h3 style="color:var(--error-color); margin-top:2rem;">Lost Trades (<?= count($tradesData['symbolsthatlost']) ?> Symbols)</h3>
            <div class="history-section">
                <?php if (!empty($tradesData['symbolsthatlost'])): ?>
                    <?php foreach ($tradesData['symbolsthatlost'] as $trade): ?>
                        <div class="history-item">
                            <span class="history-symbol"><?= $trade['symbol'] ?></span>
                            <span class="history-amount-lost"><?= $trade['amount'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; opacity: 0.7;">No losing symbol data available.</p>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')"
                    style="background:#555; color:white; border:none;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
    // Clean URL
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href.split("?")[0]);
    }

    // Payment selector logic
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
    const serverShareAmountHidden = document.getElementById('serverShareAmountHidden');
    
    const finalConfirmationModal = document.getElementById('finalConfirmationModal');
    const finalConfirmAmount = document.getElementById('finalConfirmAmount');
    const finalConfirmCoin = document.getElementById('finalConfirmCoin');
    const formServerShareAmount = document.getElementById('formServerShareAmount');
    const formPaymentCoin = document.getElementById('formPaymentCoin');

    function getSelectedCoin() {
        return document.querySelector('input[name="coin"]:checked').value;
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
        const amount = serverShareAmountHidden.value;
        
        finalConfirmAmount.textContent = '$' + amount;
        finalConfirmCoin.textContent = selectedCoin.toUpperCase();
        
        formServerShareAmount.value = amount;
        formPaymentCoin.value = selectedCoin;
        
        document.getElementById('paymentModal').classList.remove('active');
        finalConfirmationModal.classList.add('active');
    }

    copyAddressBtn.addEventListener('click', function() {
        const address = paymentAddressElement.textContent;
        if (navigator.clipboard && address && address !== 'N/A') {
            navigator.clipboard.writeText(address).then(() => {
                alert('Payment address copied to clipboard!');
            }).catch(err => {
                console.error('Could not copy text: ', err);
            });
        } else {
             alert('Address not available or clipboard access denied.');
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton();
        
        document.querySelectorAll('input[name="coin"]').forEach(radio => {
            radio.addEventListener('change', (event) => updatePaymentDetails(event.target.value));
        });
    });

    paymentAddressElement.addEventListener('click', function() {
        copyAddressBtn.click();
    });
    </script>
</body>
</html>