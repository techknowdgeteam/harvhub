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
        $loyalty_text = "Your contract is running normally. {$contractDaysLeft} days remaining.";
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
<title>HarvHub | Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        /* Light mode - Modern professional palette with green accent */
        --bg-primary: #f8fafc;
        --bg-secondary: #ffffff;
        --bg-card: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #475569;
        --text-tertiary: #64748b;
        --accent-primary: #2e8b57;
        --accent-secondary: #3cb371;
        --accent-light: #d1fae5;
        --success: #10b981;
        --success-light: #d1fae5;
        --warning: #f59e0b;
        --warning-light: #fef3c7;
        --danger: #ef4444;
        --danger-light: #fee2e2;
        --info: #6366f1;
        --info-light: #e0e7ff;
        --border: #e2e8f0;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-tertiary: #94a3b8;
            --accent-primary: #2e8b57;
            --accent-secondary: #3cb371;
            --accent-light: #1e3a5f;
            --success: #10b981;
            --success-light: #064e3b;
            --warning: #f59e0b;
            --warning-light: #92400e;
            --danger: #ef4444;
            --danger-light: #7f1d1d;
            --info: #6366f1;
            --info-light: #312e81;
            --border: #334155;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.4);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.4);
            --shadow-xl: 0 20px 25px rgba(0,0,0,0.5);
        }
    }

    body {
        font-family: "Inter", -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--bg-primary);
        color: var(--text-primary);
        line-height: 1.5;
        min-height: 100vh;
        overflow: hidden; /* Disable body scrolling */
    }

    /* Main scrollable container */
    .app-container {
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        position: relative;
        scroll-behavior: smooth;
    }

    /* Disable scrolling when modal is active */
    body.modal-open {
        overflow: hidden;
    }

    /* Passkey Overlay */
    .passkey-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .passkey-screen {
        background: var(--bg-card);
        padding: 2.5rem;
        border-radius: 1.5rem;
        width: 100%;
        max-width: 420px;
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--border);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .passkey-screen h2 {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .passkey-screen p {
        color: var(--text-secondary);
        margin-bottom: 2rem;
    }

    .passkey-screen input {
        width: 100%;
        padding: 1rem;
        background: var(--bg-secondary);
        border: 2px solid var(--border);
        border-radius: 1rem;
        font-size: 1rem;
        color: var(--text-primary);
        margin-bottom: 1.5rem;
        transition: all 0.2s;
    }

    .passkey-screen input:focus {
        outline: none;
        border-color: var(--accent-primary);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    .btn-full {
        width: 100%;
        padding: 1rem;
        background: var(--accent-primary);
        color: white;
        border: none;
        border-radius: 1rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-full:hover {
        background: var(--accent-secondary);
        transform: translateY(-2px);
    }

    .error-message {
        color: var(--danger);
        font-size: 0.875rem;
        margin-top: -1rem;
        margin-bottom: 1rem;
        font-weight: 500;
    }

    /* Dashboard Layout */
    .dashboard {
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem;
        position: relative;
    }

    /* Header */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .harvest-sticker {
        background: linear-gradient(135deg, #2e8b57, #3cb371);
        color: white;
        padding: 0.5rem 1.5rem;
        border-radius: 2rem;
        font-size: 0.875rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: var(--shadow-md);
    }

    .harvest-sticker span {
        font-size: 1.2rem;
    }

    .header-left h1 {
        font-size: 2rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .header-left p {
        color: var(--text-secondary);
        font-size: 1rem;
    }

    .header-right {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--bg-card);
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        border: 1px solid var(--border);
    }

    .user-avatar {
        width: 2.5rem;
        height: 2.5rem;
        background: var(--accent-light);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent-primary);
        font-weight: 600;
        font-size: 1.2rem;
    }

    .user-name {
        font-weight: 500;
        color: var(--text-primary);
    }

    .logout-link {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.875rem;
        transition: color 0.2s;
    }

    .logout-link:hover {
        color: var(--danger);
    }

    /* Disclaimer */
    .dashboard-disclaimer {
        background: var(--info-light);
        border: 1px solid var(--info);
        color: var(--info);
        padding: 1rem 1.5rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .encouragement-note {
        background: var(--warning-light);
        border: 1px solid var(--warning);
        color: var(--warning);
        padding: 1rem 1.5rem;
        border-radius: 1rem;
        margin-bottom: 2rem;
        font-style: italic;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 1.5rem;
        padding: 1.5rem;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        border-color: var(--accent-light);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .stat-header h3 {
        color: var(--text-secondary);
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .balance-toggle-btn {
        background: none;
        border: none;
        color: var(--text-tertiary);
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0.25rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
    }

    .balance-toggle-btn:hover {
        color: var(--accent-primary);
        background: var(--accent-light);
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: var(--text-tertiary);
        font-size: 0.875rem;
    }

    .stat-details {
        font-size: 0.75rem;
        color: var(--text-tertiary);
        margin-top: 0.5rem;
    }

    .profit-positive {
        color: var(--success) !important;
    }

    .profit-negative {
        color: var(--danger) !important;
    }

    /* Trade Summary Card */
    .stat-card.trades-card {
        grid-column: 1 / -1;
    }

    .trades-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .trades-count-large {
        font-size: 3rem;
        font-weight: 700;
        color: var(--accent-primary);
        line-height: 1;
    }

    .trades-count-label {
        color: var(--text-tertiary);
        font-size: 0.875rem;
    }

    .trades-stats {
        display: flex;
        gap: 2rem;
    }

    .trades-stat {
        text-align: right;
    }

    .trades-stat .label {
        color: var(--text-tertiary);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .trades-stat .value {
        font-size: 1.5rem;
        font-weight: 600;
    }

    .value.won {
        color: var(--success);
    }

    .value.lost {
        color: var(--danger);
    }

    .btn-view-history {
        background: var(--accent-light);
        color: var(--accent-primary);
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 2rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-view-history:hover {
        background: var(--accent-primary);
        color: white;
    }

    /* Loyalty Card */
    .stat-card.loyalty-card {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, var(--bg-card), var(--bg-secondary));
    }

    .loyalty-status {
        display: inline-block;
        padding: 0.25rem 1rem;
        background: var(--accent-light);
        color: var(--accent-primary);
        border-radius: 2rem;
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    .loyalty-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .loyalty-text {
        flex: 1;
        min-width: 300px;
    }

    .loyalty-text p {
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }

    .contract-info {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: var(--text-tertiary);
        margin-top: 0.5rem;
        flex-wrap: wrap;
    }

    .contract-dates {
        background: var(--bg-primary);
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .contract-days-left {
        background: var(--success-light);
        color: var(--success);
        padding: 0.25rem 0.75rem;
        border-radius: 2rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .loyalty-actions {
        display: flex;
        gap: 1rem;
    }

    .btn-loyalty {
        padding: 0.75rem 2rem;
        border-radius: 2rem;
        font-weight: 600;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .btn-loyalty-action {
        background: var(--accent-primary);
        color: white;
    }

    .btn-loyalty-action:hover {
        background: var(--accent-secondary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .btn-loyalty-paid {
        background: var(--warning);
        color: white;
        opacity: 0.8;
        cursor: not-allowed;
    }

    .btn-loyalty-confirmed {
        background: var(--success);
        color: white;
        cursor: default;
    }

    /* Danger Button */
    .btn-danger {
        width: 100%;
        padding: 1rem;
        background: var(--danger-light);
        color: var(--danger);
        border: 1px solid var(--danger);
        border-radius: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 1.5rem;
    }

    .btn-danger:hover {
        background: var(--danger);
        color: white;
    }

    /* Blur Mode */
    .dashboard.blur-mode .stat-value,
    .dashboard.blur-mode .stat-details,
    .dashboard.blur-mode .trades-count-large,
    .dashboard.blur-mode .trades-stat .value {
        filter: blur(15px);
        user-select: none;
    }

    /* Modals */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 2rem;
        padding: 2.5rem;
        max-width: 500px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-xl);
        animation: slideUp 0.3s ease;
    }

    .modal-content h2 {
        font-size: 1.8rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--text-primary);
    }

    .modal-content p {
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
    }

    /* Profit Split */
    .split-total {
        font-size: 2rem;
        font-weight: 700;
        color: var(--success);
        margin-bottom: 2rem;
        text-align: center;
    }

    .split-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .split-item {
        background: var(--bg-primary);
        padding: 1.5rem;
        border-radius: 1.5rem;
        border: 1px solid var(--border);
        text-align: center;
    }

    .split-item h4 {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .split-item p {
        color: var(--text-tertiary);
        margin-bottom: 1rem;
    }

    .split-item .btn-withdraw,
    .split-item .btn-pay {
        width: 100%;
        padding: 0.75rem;
        border: none;
        border-radius: 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        margin-bottom: 0.5rem;
    }

    .split-item .btn-withdraw {
        background: var(--success);
        color: white;
    }

    .split-item .btn-pay {
        background: var(--info);
        color: white;
    }

    .split-item small {
        color: var(--text-tertiary);
        font-size: 0.75rem;
    }

    /* Payment Modal */
    .coin-selector {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .coin-selector input[type="radio"] {
        display: none;
    }

    .coin-selector label {
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 1rem;
        text-align: center;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .coin-selector input[type="radio"]:checked + label {
        background: var(--accent-primary);
        color: white;
        border-color: var(--accent-primary);
    }

    .crypto-details {
        background: var(--bg-primary);
        padding: 1.5rem;
        border-radius: 1rem;
        margin-bottom: 1.5rem;
    }

    .crypto-details p {
        margin-bottom: 0.5rem;
    }

    .btc-address {
        display: block;
        background: var(--bg-card);
        padding: 1rem;
        border-radius: 0.75rem;
        font-family: monospace;
        font-size: 0.875rem;
        color: var(--accent-primary);
        word-break: break-all;
        cursor: pointer;
        border: 1px solid var(--border);
        margin-top: 0.5rem;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        cursor: pointer;
    }

    .checkbox-container input {
        width: 1.2rem;
        height: 1.2rem;
        cursor: pointer;
    }

    .btn-paid {
        background: var(--accent-primary) !important;
        color: white !important;
        opacity: 1 !important;
    }

    .btn-paid:disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }

    .disclaimer {
        color: var(--text-tertiary);
        font-size: 0.75rem;
        margin-top: 1rem;
    }

    /* Modal Actions */
    .modal-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .modal-actions button {
        flex: 1;
        padding: 1rem;
        border: none;
        border-radius: 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .modal-actions button:first-child {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .modal-actions button:last-child {
        background: var(--danger);
        color: white;
    }

    .modal-actions form {
        flex: 1;
    }

    .modal-actions form button {
        width: 100%;
        background: var(--success);
        color: white;
    }

    /* Trade History */
    .history-section {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border);
        border-radius: 1rem;
        background: var(--bg-primary);
    }

    .history-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
    }

    .history-item:last-child {
        border-bottom: none;
    }

    .history-symbol {
        font-weight: 600;
    }

    .history-amount-won {
        color: var(--success);
        font-weight: 500;
    }

    .history-amount-lost {
        color: var(--danger);
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard {
            padding: 1.5rem;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .trades-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }

        .trades-stats {
            width: 100%;
            justify-content: space-between;
        }

        .loyalty-content {
            flex-direction: column;
        }

        .loyalty-actions {
            width: 100%;
        }

        .btn-loyalty {
            flex: 1;
            text-align: center;
        }

        .split-container {
            grid-template-columns: 1fr;
        }

        .contract-info {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
</head>
<body>

    <?php if ($show_passkey_form): ?>
        <div class="passkey-overlay">
            <div class="passkey-screen">
                <h2>🔐 Create Passkey</h2>
                <p>Secure your HarvHub dashboard access</p>
                <form method="POST">
                    <input type="password" name="new_passkey" placeholder="Enter strong passkey" required autofocus>
                    <button type="submit" name="create_passkey" class="btn-full">Save & Continue</button>
                </form>
            </div>
        </div>
    <?php elseif (!$passkey_verified): ?>
        <div class="passkey-overlay">
            <div class="passkey-screen">
                <h2>👋 Welcome Back</h2>
                <p>Enter your passkey to access dashboard</p>
                <form method="POST">
                    <input type="password" name="passkey" placeholder="Your passkey" required autofocus>
                    <?php if ($passkey_error): ?>
                        <p class="error-message"><?= htmlspecialchars($passkey_error) ?></p>
                    <?php endif; ?>
                    <button type="submit" name="verify_passkey" class="btn-full">Enter Dashboard</button>
                </form>
                <a href="mailto:support@harvhub.com" style="display:block; text-align:center; margin-top:1.5rem; color:var(--accent-primary); font-size:0.875rem;">Forgot passkey?</a>
            </div>
        </div>
    <?php else: ?>
    <div class="app-container" id="appContainer">
        <div class="dashboard <?= $balanceDisplay === 'hide' ? 'blur-mode' : '' ?>">
            <div class="dashboard-header">
                <div class="header-left">
                    <div>
                        <h1><span>🌾</span> HarvHub Dashboard</h1>
                        <p>Monitor your trading performance</p>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?= strtoupper(substr($fullName, 0, 1)) ?>
                        </div>
                        <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
                    </div>
                    <a href="?logout=1" class="logout-link">Logout →</a>
                </div>
            </div>

            <?php if (!empty($dashboard_disclaimer)): ?>
                <div class="dashboard-disclaimer">
                    <span>📊</span>
                    <?= htmlspecialchars($dashboard_disclaimer) ?>
                </div>
            <?php endif; ?>

            <?php if ($profitAndLoss < 0 && ($loyaltiesStatus === null && $is_execution_empty)): ?>
                <div class="encouragement-note">
                    🌟 Don't give up! Every loss is a setup for a greater comeback. Your next contract could be your breakthrough!
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Deposit Balance</h3>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="toggle_balance_display" value="1">
                            <button type="submit" class="balance-toggle-btn">
                                <?= $balanceDisplay === 'show' ? '👁️' : '🔒' ?>
                            </button>
                        </form>
                    </div>
                    <div class="stat-value">$<?= number_format($depositBalance, 2) ?></div>
                    <div class="stat-details">
                        <?= htmlspecialchars($login) ?> · <?= htmlspecialchars($server) ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Profit & Loss</h3>
                    </div>
                    <div class="stat-value <?= $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                        $<?= number_format($profitAndLoss, 2) ?>
                    </div>
                    <div class="stat-details">
                        Since contract start
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Current Balance</h3>
                    </div>
                    <div class="stat-value <?= $currentBalance >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                        $<?= number_format($currentBalance, 2) ?>
                    </div>
                    <div class="stat-details">
                        Deposit + P&L
                    </div>
                </div>

                <div class="stat-card trades-card">
                    <div class="trades-header">
                        <div>
                            <div class="trades-count-large"><?= $tradesCountDisplay ?></div>
                            <div class="trades-count-label">Total Trades</div>
                        </div>
                        <div class="trades-stats">
                            <div class="trades-stat">
                                <div class="label">Won</div>
                                <div class="value won"><?= $wonCountDisplay ?></div>
                            </div>
                            <div class="trades-stat">
                                <div class="label">Lost</div>
                                <div class="value lost"><?= $lostCountDisplay ?></div>
                            </div>
                        </div>
                    </div>
                    <button class="btn-view-history" onclick="document.getElementById('tradeHistoryModal').classList.add('active')">
                        View Trade History →
                    </button>
                </div>

                <div class="stat-card loyalty-card">
                    <span class="loyalty-status"><?= htmlspecialchars($loyalty_status_message) ?></span>
                    
                    <div class="loyalty-content">
                        <div class="loyalty-text">
                            <p><?= htmlspecialchars($loyalty_text) ?></p>
                            
                            <?php if ($executionStartDate && $executionStartDate !== '0000-00-00'): ?>
                                <div class="contract-info">
                                    <span class="contract-dates">
                                        📅 <?= htmlspecialchars($formatted_start_date) ?> → <?= htmlspecialchars($formatted_end_date) ?>
                                    </span>
                                    <?php if ($contractDaysLeft > 0): ?>
                                        <span class="contract-days-left">
                                            ⏳ <?= $contractDaysLeft ?> days left
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div style="margin-top: 0.5rem; color: var(--text-tertiary); font-size: 0.875rem;">
                                ⏱️ <?= $CONTRACT_DURATION ?> day contract
                            </div>
                        </div>

                        <?php if ($show_payment_note): ?>
                            <p style="color: var(--warning); font-style: italic;">
                                ⏳ Your payment is being reviewed. Once confirmed, you'll be able to re-enroll.
                            </p>
                        <?php endif; ?>

                        <?php if (!$show_payment_note): ?>
                            <div class="loyalty-actions">
                                <button 
                                    <?= $loyalty_btn_action ?>
                                    class="btn-loyalty <?= htmlspecialchars($loyalty_btn_class) ?>"
                                >
                                    <?= htmlspecialchars($loyalty_btn_text) ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <p style="color: var(--text-tertiary); font-size: 0.875rem; text-align: center; margin: 1rem 0;">
                PnL updates every 24 hours · Automated execution runs 24/7
            </p>

            <button class="btn-danger" onclick="document.getElementById('disconnectModal').classList.add('active')">
                Disconnect My Account
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Disconnect Modal -->
    <div id="disconnectModal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--danger);">⚠️ Disconnect Account?</h2>
            <p>This action will permanently disconnect your account from trading activities.</p>
            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')">Cancel</button>
                <form method="POST">
                    <button type="submit" name="confirm_disconnect">Yes, Disconnect</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Profit Split Modal -->
    <div id="profitSplitModal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--info);">💰 Profit Split Required</h2>
            <p>Your contract has ended with a profit of $<?= number_format($profitToSplit, 2) ?>.</p>
            
            <div class="split-total">$<?= number_format($profitToSplit, 2) ?></div>

            <div class="split-container">
                <div class="split-item">
                    <h4 style="color: var(--success);"><?= $USER_SHARE_PERCENT ?>%</h4>
                    <p>Your Share</p>
                    <h4 style="color: var(--success);">$<?= number_format($userShare, 2) ?></h4>
                    <button class="btn-withdraw" 
                            onclick="window.open('<?= $brokerTarget ?>', '_blank')">
                        Withdraw
                    </button>
                    <small>Withdraw your share</small>
                </div>
                
                <div class="split-item">
                    <h4 style="color: var(--info);"><?= $SERVER_SHARE_PERCENT ?>%</h4>
                    <p>Server Share</p>
                    <h4 style="color: var(--info);">$<?= number_format($serverShare, 2) ?></h4>
                    <button class="btn-pay" 
                            onclick="document.getElementById('profitSplitModal').classList.remove('active'); document.getElementById('paymentModal').classList.add('active');">
                        Pay Server
                    </button>
                    <small>Pay to continue</small>
                </div>
            </div>

            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')">Close</button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--accent-primary);">💳 Pay Server Share</h2>
            <p>Send $<?= number_format($serverShare, 2) ?> worth of cryptocurrency</p>
            
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
                <span>I have made the payment</span>
            </label>

            <button class="btn-full btn-paid" id="confirmPaidBtn" disabled onclick="triggerFinalConfirmation()">
                Confirm Payment
            </button>
            
            <p class="disclaimer">Click only after payment has been successfully sent. Your payment will be verified by the server.</p>
            
            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Final Confirmation Modal -->
    <div id="finalConfirmationModal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--success);">✅ Final Confirmation</h2>
            <p>Confirm that you have sent <strong id="finalConfirmAmount">$0.00</strong> to the <strong id="finalConfirmCoin">N/A</strong> address.</p>
            
            <div class="modal-actions">
                <button onclick="document.getElementById('finalConfirmationModal').classList.remove('active')">Cancel</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="final_confirm_payment" value="1">
                    <input type="hidden" name="server_share_amount" id="formServerShareAmount" value="">
                    <input type="hidden" name="payment_coin" id="formPaymentCoin" value="">
                    <button type="submit">Yes, I've Paid</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Re-enroll Modal -->
    <div id="reenrollModal" class="modal">
        <div class="modal-content">
            <h2 style="color: var(--info);">🔄 Start New Contract</h2>
            <p>Start a new <?= $CONTRACT_DURATION ?>-day trading contract from today.</p>
            <?php if ($profitAndLoss < 0): ?>
                <p style="color: var(--warning); font-style: italic; margin-top: 0.5rem;">
                    🌟 Remember: Every successful trader faced losses. This is your chance for a fresh start!
                </p>
            <?php endif; ?>
            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')">Cancel</button>
                <form method="POST">
                    <button type="submit" name="confirm_reenroll">Start New Contract</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Trade History Modal -->
    <div id="tradeHistoryModal" class="modal">
        <div class="modal-content">
            <h2>📊 Trade History</h2>
            <p>Currency pairs performance</p>

            <h3 style="color: var(--success); margin: 1.5rem 0 1rem;">Won Trades (<?= count($tradesData['symbolsthatwon']) ?>)</h3>
            <div class="history-section">
                <?php if (!empty($tradesData['symbolsthatwon'])): ?>
                    <?php foreach ($tradesData['symbolsthatwon'] as $trade): ?>
                        <div class="history-item">
                            <span class="history-symbol"><?= $trade['symbol'] ?></span>
                            <span class="history-amount-won">+<?= ltrim($trade['amount'], '+-') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 1rem; color: var(--text-tertiary);">No winning symbol data available.</p>
                <?php endif; ?>
            </div>
            
            <h3 style="color: var(--danger); margin: 1.5rem 0 1rem;">Lost Trades (<?= count($tradesData['symbolsthatlost']) ?>)</h3>
            <div class="history-section">
                <?php if (!empty($tradesData['symbolsthatlost'])): ?>
                    <?php foreach ($tradesData['symbolsthatlost'] as $trade): ?>
                        <div class="history-item">
                            <span class="history-symbol"><?= $trade['symbol'] ?></span>
                            <span class="history-amount-lost"><?= $trade['amount'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; padding: 1rem; color: var(--text-tertiary);">No losing symbol data available.</p>
                <?php endif; ?>
            </div>

            <div class="modal-actions">
                <button onclick="this.closest('.modal').classList.remove('active')">Close</button>
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

    // Function to handle body scroll when modal opens/closes
    function handleModalOpen(modalId) {
        document.body.classList.add('modal-open');
        document.getElementById(modalId).classList.add('active');
    }

    function handleModalClose(modalElement) {
        document.body.classList.remove('modal-open');
        modalElement.classList.remove('active');
    }

    // Update all modal open/close handlers
    document.querySelectorAll('[onclick*=".classList.add(\'active\')"]').forEach(button => {
        const originalOnclick = button.getAttribute('onclick');
        if (originalOnclick && originalOnclick.includes('document.getElementById')) {
            const modalId = originalOnclick.match(/'([^']+)'/)[1];
            button.setAttribute('onclick', `handleModalOpen('${modalId}')`);
        }
    });

    // Update modal close buttons
    document.querySelectorAll('.modal-actions button, .modal-actions form button').forEach(button => {
        if (button.getAttribute('onclick')?.includes('closest')) {
            button.setAttribute('onclick', button.getAttribute('onclick').replace(
                'this.closest(\'.modal\').classList.remove(\'active\')',
                'handleModalClose(this.closest(\'.modal\'))'
            ));
        }
    });

    // Add escape key handler
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal.active');
            if (activeModal) {
                handleModalClose(activeModal);
            }
        }
    });

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
        
        handleModalClose(document.getElementById('paymentModal'));
        handleModalOpen('finalConfirmationModal');
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