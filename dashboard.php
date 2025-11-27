<?php
    session_start();
    // dashboard.php

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
            unset($_SESSION['passkey_error']); // Clear error on standard GET
        }
    }

    // 2. Check for logged-in user email
    if (!isset($_SESSION['user_email'])) {
        header("Location: servharv.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    // Database credentials (You should move these to a secure configuration file)
    $host = "sql312.infinityfree.com";
    $dbname = "if0_40473107_harvhub";
    $user = "if0_40473107";
    $pass = "InDQmdl53FZ85";
    $tableName = "insiders_server";
    $serverAccountTable = "server_account"; // Table for server configurations

    // --- Fixed Configuration (These remain hardcoded) ---
    $SERVER_SHARE_PERCENT = 30; // 30% for the server/loyalty payment
    $USER_SHARE_PERCENT = 70;    // 70% for the user withdrawal
    $MIN_BROKER_BALANCE = 30;
    $MAX_CONTRACT_DAYS = 5;
    $MIN_PROFIT_FOR_SPLIT = 30; // Constant for minimum profit check
    // $CONTRACT_DURATION will be fetched from DB
    // $MIN_INITIAL_DEPOSIT will be fetched from DB
    // --- End Fixed Configuration ---


    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        // In a real application, you would log this error, not die()
        die("Database connection failed.");
    }

    // 3. Fetch user data 
    // Fetch all necessary columns
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ? AND application_status = 'approved'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC for clarity

    if (!$user) {
        // User no longer approved or doesn't exist, log out
        session_unset();
        session_destroy();
        header("Location: servharv.php");
        exit;
    }

    // 3a. Fetch Server Account Data and Dynamic Configuration
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$serverAccount) {
        // Fallback if no server account is configured - Setting all minimums to 0 to prevent errors
        $serverAccount = [
            'btc_address' => 'N/A', 
            'eth_address' => 'N/A', 
            'eth_network' => 'ERC20', 
            'usdt_address' => 'N/A', 
            'usdt_network' => 'TRC20',
            'minimum_deposit' => 0.00, 
            'brokers_link' => '',
            'contract_duration' => 10, // Fallback for contract duration set to 10 as per request
        ];
    }
    
    // 🛑 MODIFICATION START: Set dynamic minimum deposit and contract duration
    $MIN_INITIAL_DEPOSIT = (float)($serverAccount['minimum_deposit'] ?? 0.00); 
    // Use 10 days as hard default if DB value is missing or invalid, matching the request
    $CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 10); 
    // 🛑 MODIFICATION END
    
    // Extract Data needed for early checks
    $brokerBalance = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss = (float)($user['profitandloss'] ?? 0); // Initialize P&L here
    $contractDaysLeft = (int)($user['contract_days_left'] ?? 99); // Initialize contract days here

    // 3b. Initial Balance Check 🛑
    $balance_check_failed = false;
    // NOTE: This check should logically use the brokerBalance (the *total* balance), 
    // but the code uses it to check against MIN_INITIAL_DEPOSIT. 
    // We keep this check as is based on existing logic.
    if ($brokerBalance < $MIN_INITIAL_DEPOSIT) {
        $balance_check_failed = true;
    }


    // Extract remaining Data
    $fullName = $user['fullname'] ?? $email;
    $login = $user['login'] ?? 'N/A';
    $server = $user['server'] ?? 'N/A';
    $balanceDisplay = $user['balance_display'] ?? 'show'; 
    $broker = strtolower($user['broker'] ?? 'unknown'); // Get user's broker name
    // $profitAndLoss is already set above
    $tradesString = $user['trades'] ?? ''; 
    $loyaltiesStatus = $user['loyalties'] ?? null; // Fetch loyalties status
    // $contractDaysLeft is already set above

    // Calculate Current Balance and Profit Split
    // 🛑 START OF MODIFIED LOGIC BLOCK
    
    // The actual total balance in the broker account (renamed to currentBalance for display)
    $currentBalance = $brokerBalance; 
    
    // Calculate the Deposit Balance (Net Deposit)
    // Deposit Balance = Total Broker Value - ProfitAndLoss
    $depositBalance = $brokerBalance - $profitAndLoss;
    
    // 🛑 END OF MODIFIED LOGIC BLOCK
    
    // Calculate Profit Split values
    $profitToSplit = max(0, $profitAndLoss); // Only split positive profit
    $serverShare = round($profitToSplit * ($SERVER_SHARE_PERCENT / 100), 2);
    $userShare = round($profitToSplit * ($USER_SHARE_PERCENT / 100), 2);
    
    // --- New: Determine Deposit Link (based on user's broker) ---
    $brokerLink = '';
    // Parse the brokers_link field (e.g., "insiders_server: deriv.com")
    $brokerLinks = [];
    $linkParts = explode(',', $serverAccount['brokers_link'] ?? '');
    
    // --- FIX START ---
    // Change parsing logic to use a simple map key => link
    foreach ($linkParts as $part) {
        $part = trim($part);
        if (strpos($part, ':') !== false) {
            // Split by the first colon only
            list($keyRaw, $link) = explode(':', $part, 2);
            // We need to normalize the key, e.g., 'insiders_server' or 'insiders'
            // We'll use a combined key of "source:broker" for specific links, and just "source" for general ones.
            $key = trim(strtolower($keyRaw));
            
            // Assuming your links are consistently stored as:
            // "insiders_server: deriv.com"
            // "insiders: exness.com"
            // We can check if the link part contains a known broker name to try and map it.
            
            $linkName = strtolower(basename(parse_url('http://' . trim($link), PHP_URL_HOST) ?? ''));
            $linkName = str_replace(array('.com', '.co', '.net'), '', $linkName);
            
            // Map the link to the key, but override if it's a specific broker link
            if (!empty($linkName)) {
                 // Store specific links as 'deriv' => 'deriv.com'
                 $brokerLinks[$linkName] = trim($link);
            }
            
            // Also store general links for fallback: 'insiders_server' => 'exness.com' (last one wins)
            $brokerLinks[$key] = trim($link);

        }
    }
    
    $userBrokerNormalized = strtolower($broker); // e.g., 'bybit'

    // 1. PRIORITIZE: Look for the link matching the user's specific broker name
    if (!empty($userBrokerNormalized) && isset($brokerLinks[$userBrokerNormalized])) {
        $brokerLink = $brokerLinks[$userBrokerNormalized];
    } 
    // 2. FALLBACK: Use the general 'insiders_server' link (last one parsed)
    elseif (isset($brokerLinks['insiders_server'])) {
        $brokerLink = $brokerLinks['insiders_server'];
    }
    // 3. FALLBACK: Use the generic 'insiders' link (last one parsed)
    elseif (isset($brokerLinks['insiders'])) {
        $brokerLink = $brokerLinks['insiders'];
    }

    // --- FIX END ---
    
    // Final check to ensure it's a valid URL format for opening in a new window
    $brokerLink = (strpos($brokerLink, '://') === false && !empty($brokerLink)) ? 'https://' . $brokerLink : $brokerLink;


    // --- LOYALTIES LOGIC (State Machine) ---
    $showProfitSplit = false;
    $loyalty_text = "";
    $loyalty_status_message = "";
    $dashboard_disclaimer = "";
    $show_contract_days = true;
    $show_reenroll_button = false; // Flag for re-enrollment

    // Helper to update loyalty and contract days
    function updateLoyaltyAndDays($pdo, $tableName, $email, $newLoyalty, $newDays) {
        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = ?, contract_days_left = ? WHERE email = ?");
        $upd->execute([$newLoyalty, $newDays, $email]);
        return $newLoyalty;
    }

    // --- Group of states that lead to a running contract ---
    $running_statuses = ['justjoined', 're-enrolled'];
    
    $loyalty_btn_action = "disabled"; // Default action
    $loyalty_btn_text = "Not yet";
    $loyalty_btn_class = "";
    $show_reenroll_button = false;
    
    // Convert deposit link for JS/HTML usage
    $brokerTarget = !empty($brokerLink) ? htmlspecialchars($brokerLink) : 'about:blank';


    if ($balance_check_failed) {
        // NEW CONDITION: Balance is too low
        $dashboard_disclaimer = "You need to deposit minimum of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . " to participate.";
        $loyalty_text = "Your account is not yet eligible. Please fund your broker account with a minimum of $" . number_format($MIN_INITIAL_DEPOSIT, 2) . ".";
        $loyalty_status_message = "Minimum Deposit Required";
        $show_contract_days = false;
        
        // --- MODIFIED: DEPOSIT LINK ACTION ---
        $loyalty_btn_text = "Deposit $" . number_format($MIN_INITIAL_DEPOSIT, 2);
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"window.open('{$brokerTarget}', '_blank')\"";
        
    } elseif (in_array($loyaltiesStatus, $running_statuses) && $contractDaysLeft > $MAX_CONTRACT_DAYS) {
        // Condition 1: Contract running normally (> 5 days left)
        $dashboard_disclaimer = "Trading is active.";
        
        if ($loyaltiesStatus === 'justjoined') {
            $loyalty_text = "You are welcome to our board.";
            $loyalty_status_message = "Eligible for next contract period";
        } elseif ($loyaltiesStatus === 're-enrolled') {
            $loyalty_text = "We go again for better turnup.";
            $loyalty_status_message = "Contract Active";
        }
        $loyalty_btn_text = "Eligible";
        $loyalty_btn_class = "btn-loyalty-confirmed";
    
    } elseif (in_array($loyaltiesStatus, $running_statuses) && $contractDaysLeft <= $MAX_CONTRACT_DAYS) {
        // Condition 2: Contract expiring soon (<= 5 days left) - Trigger point for renewal
        $show_contract_days = false;

        if ($profitAndLoss >= $MIN_PROFIT_FOR_SPLIT) {
            // High enough profit: Trigger profit split flow
            if ($brokerBalance >= $MIN_BROKER_BALANCE) {
                $showProfitSplit = true;
                $loyalty_text = "Contract end reached. Profit split is required to continue.";
                $loyalty_status_message = "Contract Ended / Profit Split Required";
                $loyalty_btn_text = "View Profit Split";
                $loyalty_btn_class = "btn-loyalty-action";
                $loyalty_btn_action = "onclick=\"document.getElementById('profitSplitModal').classList.add('active')\"";
                
                // Set loyalty status to pending in DB
                $loyaltiesStatus = updateLoyaltyAndDays($pdo, $tableName, $email, 'pending', $contractDaysLeft);
            } else {
                // High profit, but balance is too low.
                $loyalty_text = "Contract ended. Insufficient broker balance to proceed with split. Please review funds.";
                $loyalty_status_message = "Contract Ended / Balance Too Low";
                $dashboard_disclaimer = "Trading is paused.";
            }

        } else {
            // Low Profit (profitandloss < 30): User must re-enroll or disconnect
            $loyalty_text = "Your profit is yet to meet the minimum requirement. You can decide to re-enroll or disconnect your account.";
            $loyalty_status_message = "Minimum Profit Not Met";
            $dashboard_disclaimer = "Trading is paused.";
            $show_reenroll_button = true; // <-- Flag for re-enrollment button
            $loyalty_btn_text = "Re-enroll"; // <-- Change text for re-enrollment prompt
            $loyalty_btn_class = "btn-loyalty-action"; // Use the action class
            $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\""; // <-- New action handler
        }

    } elseif ($loyaltiesStatus === 'paymentconfirmed') {
        // Condition 3: Payment is confirmed, but user needs to manually re-enroll to start the new contract
        // 🛑 MODIFICATION: Use $CONTRACT_DURATION in text
        $loyalty_text = "You have maintained your eligibility. Click Re-enroll to activate your new contract period ({$CONTRACT_DURATION} days).";
        $loyalty_status_message = "Eligibility Confirmed / Awaiting Re-enrollment";
        $dashboard_disclaimer = "Trading is paused until you re-enroll.";
        $show_contract_days = false;
        $show_reenroll_button = true; // <-- Flag for re-enrollment button
        $loyalty_btn_text = "Re-enroll"; // <-- Change text for re-enrollment prompt
        $loyalty_btn_class = "btn-loyalty-action"; // Use the action class
        $loyalty_btn_action = "onclick=\"document.getElementById('reenrollModal').classList.add('active')\""; // <-- New action handler

    } elseif ($loyaltiesStatus === 'pending') {
        // Condition 4: Currently in the split payment flow (pending)
        $showProfitSplit = true;
        $loyalty_text = "Action required: Complete profit split to remain eligible.";
        $loyalty_status_message = "Profit split pending payment.";
        $show_contract_days = false;
        $loyalty_btn_text = "View Profit Split";
        $loyalty_btn_class = "btn-loyalty-action";
        $loyalty_btn_action = "onclick=\"document.getElementById('profitSplitModal').classList.add('active')\"";

    } elseif ($loyaltiesStatus === 'paid') {
        // Condition 5: Payment confirmed by user, awaiting admin confirmation
        $loyalty_text = "Your payment will be confirmed within 24 hours.";
        $loyalty_status_message = "Payment Received, Awaiting Confirmation";
        $dashboard_disclaimer = "Your account will resume trading activities once payment is confirmed.";
        $show_contract_days = false;
        $loyalty_btn_text = "Awaiting Confirmation";
        $loyalty_btn_class = "btn-loyalty-paid";

    } else {
        // Fallback
            $loyalty_text = "Your contract is currently active.";
            $loyalty_status_message = "Will be updated when contract ends";
            $loyalty_btn_text = "Eligible";
            $loyalty_btn_class = "btn-loyalty-confirmed";
    }

    // --- End LOYALTIES LOGIC ---


    // Parse Trades Data
    $tradesData = [
        'Trades' => 0,
        'won' => 0,
        'lost' => 0,
        'symbolsthatwon' => [],
        'symbolsthatlost' => []
    ];
    // Check if the trades string is not empty or 'none'
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

    // --- POST Handling (Using PRG Pattern) ---

    // 4. Create Passkey
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_passkey'])) {
        if (!empty($_POST['new_passkey'])) {
            $passkey = password_hash($_POST['new_passkey'], PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE $tableName SET passkey = ? WHERE email = ?");
            $upd->execute([$passkey, $email]);
            $_SESSION['passkey_verified'] = true;
            $_SESSION['prg_redirect_safe'] = true;
        }
        header("Location: dashboard.php"); // Use correct file name
        exit;
    }

    // 5. Verify Passkey
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_passkey'])) {
        if (password_verify($_POST['passkey'], $user['passkey'] ?? '')) {
            $_SESSION['passkey_verified'] = true;
            unset($_SESSION['passkey_error']); 
        } else {
            $_SESSION['passkey_error'] = "Incorrect passkey."; 
            unset($_SESSION['passkey_verified']);
        }
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: dashboard.php"); 
        exit;
    }

    // 6. TOGGLE BALANCE DISPLAY
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

        header("Location: dashboard.php"); 
        exit;
    }
    
    // 7. HANDLE LOYALTY PAYMENT CONFIRMATION (FINAL SUBMISSION)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['final_confirm_payment'])) {
        $coin = $_POST['payment_coin'] ?? 'N/A';
        $amount = $_POST['server_share_amount'] ?? 0.00;
        
        // 🚨 MODIFICATION: Include current date and time
        $datetime = date('Y-m-d H:i:s');
        
        // Format payment details string to be saved
        $paymentDetails = "Amount: $" . number_format($amount, 2) . ", Coin: " . htmlspecialchars($coin) . ", Confirmed_at: " . $datetime;
        
        // Update DB: Set loyalty status to 'paid', reset P&L, and save payment details
        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = 'paid', profitandloss = 0, paymentdetails = ? WHERE email = ?");
        $upd->execute([$paymentDetails, $email]);
        
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: dashboard.php"); 
        exit;
    }

    // 7b. HANDLE RE-ENROLL BUTTON
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reenroll'])) {
        // Reset profit and set status to re-enrolled with new contract days
        // Updates loyalties to 're-enrolled', resets P&L to 0, and sets days left to $CONTRACT_DURATION.
        // 🛑 MODIFICATION: Use $CONTRACT_DURATION instead of $NEW_CONTRACT_DAYS
        $upd = $pdo->prepare("UPDATE $tableName SET loyalties = 're-enrolled', contract_days_left = ?, profitandloss = 0 WHERE email = ?");
        $upd->execute([$CONTRACT_DURATION, $email]);
        
        $_SESSION['prg_redirect_safe'] = true;
        header("Location: dashboard.php"); 
        exit;
    }


    // 8. Disconnect Account (Blacklist and Logout)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_disconnect'])) {
        $pdo->prepare("UPDATE $tableName SET application_status = 'blacklisted' WHERE email = ?")
             ->execute([$email]);
            
        session_unset();
        session_destroy();
        header("Location: servharv.php");
        exit;
    }

    // 9. Logout
    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header("Location: servharv.php");
        exit;
    }

    // --- Final State Check for Rendering ---
    // Note: If a user first joins, $user['contract_days_left'] will likely be NULL (or 0) in DB.
    // The previous script had no logic for a new user joining. Assuming 'justjoined' logic handles this.
    // If $user['loyalties'] is NULL (newly approved but not "justjoined"), the fallback case is used, 
    // which results in $loyalty_text = "Your contract is currently active."
    // For a brand new user, the admin must set loyalties to 'justjoined' and contract_days_left to $CONTRACT_DURATION 
    // for them to be in the active loop (Condition 1).

    $show_passkey_form = empty($user['passkey']);
    $passkey_verified = $_SESSION['passkey_verified'] ?? false;
    $passkey_error = $_SESSION['passkey_error'] ?? null; 

    // --- Loyalty Card Logic Setup (for rendering) ---
    // If the split condition is met and it's not confirmed/paid yet, force modal
    $loyalty_trigger_modal = $showProfitSplit; 
    
    // Re-check button action to ensure it matches the final state determined in the main logic
    if ($balance_check_failed) {
        // Action is already set to open the deposit link
    } elseif ($show_reenroll_button) {
        // Action is already set to open the re-enroll modal
    } elseif ($loyaltiesStatus === 'paid') {
        // Action is already set to disabled
    } elseif ($loyalty_trigger_modal) {
        // Action is already set to open the profit split modal
    } elseif (in_array($loyaltiesStatus, ['justjoined', 're-enrolled']) && $contractDaysLeft > $MAX_CONTRACT_DAYS) {
        // Normal running contract states - Action is already set to disabled
    } 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HarvHub Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
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
                <a href="?logout=1" style="color:#ff6b6b;">← Logout</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-wrapper <?= $balanceDisplay === 'hide' && $passkey_verified ? 'blur-mode' : '' ?>">
        <h1>HarvHub Dashboard</h1>
        <p class="welcome">Hello, <strong><?= htmlspecialchars($fullName) ?></strong></p>

        <?php if (!empty($dashboard_disclaimer)): ?>
            <p class="dashboard-disclaimer">
                <?= htmlspecialchars($dashboard_disclaimer) ?>
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
                
                <h3>Deposit Balance</h3>
                
                <div class="stat-details-info">
                    <?= htmlspecialchars($login) ?><br>
                    <?= htmlspecialchars($server) ?>
                </div>
                <h2>$<?= number_format($depositBalance, 2) ?></h2> </div>
            
            <div class="stat-card">
                <h3>Profit & Loss</h3>
                <h2 class="<?= $profitAndLoss >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    $<?= number_format($profitAndLoss, 2) ?>
                </h2>
            </div>

            <div class="stat-card">
                <h3>Current Balance</h3>
                <h2 class="<?= $currentBalance >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                    $<?= number_format($currentBalance, 2) ?> </h2>
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

                <?php 
                    // 🛑 START OF REQUESTED MODIFICATION
                    if ($show_contract_days) {
                        echo "<p><strong>{$CONTRACT_DURATION} days contract duration</strong></p>";
                    }
                    // 🛑 END OF REQUESTED MODIFICATION
                ?>

                <?php if ($show_contract_days): ?>
                    <span class="contract-days-left">
                        <?= $contractDaysLeft ?> days left
                    </span>
                <?php endif; ?>
                
                <button 
                    <?= $loyalty_btn_action ?>
                    class="<?= htmlspecialchars($loyalty_btn_class) ?>"
                >
                    <?= htmlspecialchars($loyalty_btn_text) ?>
                </button>
            </div>
            </div>

        <p class="note">
            Your PnL is updated every 24 hours.<br>
            Automated execution runs 24/7 on your connected account.
        </p>

        <button class="btn-danger" onclick="document.getElementById('disconnectModal').classList.add('active')">
            Disconnect My Account
        </button>
        
        <a href="?logout=1" class="logout-link">Logout</a>

    </div>

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
                Your contract requires a profit split to continue trading eligibility.
            </p>
            <p class="split-total">
                $<?= number_format($profitToSplit, 2) ?> profit
            </p>

            <div class="split-container">
                <div class="split-item">
                    <h4 style="color:var(--success-color);"><?= $USER_SHARE_PERCENT ?>%</h4>
                    <p>Your Share</p>
                    <h4 style="color:var(--success-color);">$<?= number_format($userShare, 2) ?></h4>
                    <button class="btn-withdraw" 
                            onclick="window.open('<?= $brokerTarget ?>', '_blank')"
                            style="background:#2ecc71; color:white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; display: block; width: 100%;">
                        Withdraw Profit
                    </button>
                    <small style="display:block; margin-top:10px; opacity:0.6;">Withdraw your $<?= number_format($userShare, 2) ?> profit share</small>
                </div>
                <div class="split-item">
                    <h4 style="color:var(--success-color);"><?= $SERVER_SHARE_PERCENT ?>%</h4>
                    <p>Pay server%</p>
                    <h4 style="color:var(--success-color);">$<?= number_format($serverShare, 2) ?></h4>
                    <button class="btn-pay" onclick="document.getElementById('profitSplitModal').classList.remove('active'); document.getElementById('paymentModal').classList.add('active');"
                            style="padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; display: block; width: 100%;">
                        Pay server
                    </button>
                    <small style="display:block; margin-top:10px; opacity:0.6;">Pay to remain eligible</small>
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
            <h2 style="color:var(--accent);">Server Payment</h2>
            <p style="margin:1rem 0; opacity:0.8;">
                Send $<?= number_format($serverShare, 2) ?> worth of the selected address
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
                Paid
            </button>
            
            <p class="disclaimer">Click paid only after payment has been successfully sent.</p>
            
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
                        Yes, Paid
                    </button>
                </form>
            </div>
        </div>
    </div>


    <div id="reenrollModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--info-color);">Re-enrollment Option</h2>
            <p style="margin:1.5rem 0; line-height:1.6;">
                <?php if ($loyaltiesStatus === 'paymentconfirmed'): ?>
                    There will be trading activities in your account for the next <?= $CONTRACT_DURATION ?> days and within these period you mustn't perform trading or withdrawal activity on your broker account.
                <?php else: ?>
                    Your profit is below the minimum $<?= $MIN_PROFIT_FOR_SPLIT ?> profit requirement.
                    You can re-enroll for a new contract period of <?= $CONTRACT_DURATION ?> days. 
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
                        Re-enroll
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
    // 1. Cleans the URL of query parameters after a redirect (PRG pattern cleanup)
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href.split("?")[0]);
    }

    // 2. Dynamic Payment Selector Logic
    
    // PHP variables passed to JavaScript
    const serverAccounts = {
        btc: { 
            address: "<?= htmlspecialchars($serverAccount['btc_address']) ?>", 
            network: "Bitcoin" 
        },
        eth: { 
            address: "<?= htmlspecialchars($serverAccount['eth_address']) ?>", 
            network: "<?= htmlspecialchars($serverAccount['eth_network']) ?>" 
        },
        usdt: { 
            address: "<?= htmlspecialchars($serverAccount['usdt_address']) ?>", 
            network: "<?= htmlspecialchars($serverAccount['usdt_network']) ?>" 
        }
    };
    
    // Elements from Payment Modal
    const paymentAddressElement = document.getElementById('paymentAddress');
    const paymentNetworkElement = document.getElementById('paymentNetwork');
    const copyAddressBtn = document.getElementById('copyAddressBtn');
    const confirmPaidBtn = document.getElementById('confirmPaidBtn');
    const paymentConfirmationCheck = document.getElementById('paymentConfirmationCheck');
    const serverShareAmountHidden = document.getElementById('serverShareAmountHidden');
    
    // Elements from Final Confirmation Modal
    const finalConfirmationModal = document.getElementById('finalConfirmationModal');
    const finalConfirmAmount = document.getElementById('finalConfirmAmount');
    const finalConfirmCoin = document.getElementById('finalConfirmCoin');
    const formServerShareAmount = document.getElementById('formServerShareAmount');
    const formPaymentCoin = document.getElementById('formPaymentCoin');


    // Function to get the currently selected coin
    function getSelectedCoin() {
        return document.querySelector('input[name="coin"]:checked').value;
    }

    // Function to update the displayed address and network
    function updatePaymentDetails(coin) {
        const data = serverAccounts[coin];
        if (data) {
            paymentAddressElement.textContent = data.address;
            paymentNetworkElement.textContent = data.network;
            paymentAddressElement.dataset.address = data.address;
        }
    }

    // Function to toggle the Paid button's disabled state
    function togglePaidButton() {
        confirmPaidBtn.disabled = !paymentConfirmationCheck.checked;
    }
    
    // Function to trigger the final confirmation modal
    function triggerFinalConfirmation() {
        const selectedCoin = getSelectedCoin();
        const amount = serverShareAmountHidden.value;
        
        // 1. Update confirmation modal text
        finalConfirmAmount.textContent = '$' + amount;
        finalConfirmCoin.textContent = selectedCoin.toUpperCase();
        
        // 2. Populate hidden form fields for submission
        formServerShareAmount.value = amount;
        formPaymentCoin.value = selectedCoin;
        
        // 3. Close current modal and open confirmation modal
        document.getElementById('paymentModal').classList.remove('active');
        finalConfirmationModal.classList.add('active');
    }


    // Function to copy the address
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

    // Initialize the payment details on load (default to BTC)
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure the default selected coin is set on page load
        updatePaymentDetails(getSelectedCoin());
        togglePaidButton(); // Set initial state of the Paid button
        
        // Add event listeners to radio buttons to update details
        document.querySelectorAll('input[name="coin"]').forEach(radio => {
            radio.addEventListener('change', (event) => updatePaymentDetails(event.target.value));
        });
    });

    // Attach event listeners to address element for tap/click copy
    paymentAddressElement.addEventListener('click', function() {
        copyAddressBtn.click();
    });

    </script>
</body>
</html>