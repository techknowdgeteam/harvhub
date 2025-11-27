<?php
    session_start();
    //servharv.php
    // ==================== DATABASE CONNECTION ====================
    // NOTE: Database credentials are a security risk in the script.
    try {
        $pdo = new PDO(
            "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
            "if0_40473107",
            "InDQmdl53FZ85",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        // In a production environment, avoid revealing details like $e->getMessage()
        die("Database connection failed.");
    }

    // ==================== FETCH BROKER CONFIGURATION ====================
    $allowed_brokers = [];
    $broker_links = [];
    $broker_targets = [];
    $error = ''; // Initialize error variable

    try {
        $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            // Process brokers (e.g., "insiders: exness,insiders: bybit,insiders_server: deriv")
            $raw_brokers = explode(',', $config['brokers'] ?? '');
            
            // We want unique broker names used for 'insiders_server' as per context
            foreach ($raw_brokers as $entry) {
                if (preg_match('/(insiders_server):\s*(.+)/i', trim($entry), $matches)) {
                    $broker_name = trim($matches[2]);
                    // Only add if it's not already in the list (making it unique)
                    if (!in_array(ucfirst($broker_name), $allowed_brokers)) {
                        $allowed_brokers[] = ucfirst($broker_name);
                    }
                }
            }

            // Process broker links (e.g., "insiders_server: deriv.com, insiders_server: bybit.com")
            $raw_links = explode(',', $config['brokers_link'] ?? '');
            foreach ($raw_links as $entry) {
                if (preg_match('/(insiders_server):\s*(.+)/i', trim($entry), $matches)) {
                    $broker_key = trim($matches[2]); // e.g., deriv.com
                    $broker_link_parts = explode(':', $broker_key, 2);
                    if (count($broker_link_parts) == 2) {
                        $link_name = trim($broker_link_parts[0]); // e.g., bybit
                        $link_url = trim($broker_link_parts[1]); // e.g., bybit.com
                        
                        // Ensure it starts with http/s, or default to https://
                        $target_url = (strpos($link_url, '://') === false && !empty($link_url)) ? 'https://' . $link_url : $link_url;
                        $broker_targets[ucfirst($link_name)] = $target_url;
                    } elseif (!empty($broker_key)) {
                         // Fallback for single-part values like "deriv.com" if they appear without a broker name prefix (based on provided SQL data)
                        // This part is less robust but handles the single 'deriv.com' example provided by the user if it appeared without a broker name key
                        // The user's input "insiders_server: deriv.com" only suggests deriv.com is the value, not key:value pair.
                        // I'll adjust the regex to better reflect a single key:value structure for the links, which is standard for config.
                        
                        // Re-evaluate based on the single example 'brokers_link' provided: "insiders_server: deriv.com"
                        // Assuming the broker name is 'Deriv' and the link is 'deriv.com'
                        // Since the regex captured 'deriv.com' as $matches[2] from "insiders_server: deriv.com"
                        $key_parts = explode('.', $broker_key);
                        $link_name = ucfirst($key_parts[0]); // Derive broker name from link
                         $target_url = (strpos($broker_key, '://') === false && !empty($broker_key)) ? 'https://' . $broker_key : $broker_key;
                        $broker_targets[$link_name] = $target_url;
                    }
                }
            }
        }
        // Ensure that $allowed_brokers array contains unique broker names derived from 'insiders_server' entries
        $allowed_brokers = array_unique($allowed_brokers);
        sort($allowed_brokers); // Sort for consistent display
        
    } catch (PDOException $e) {
        $error = "Failed to load broker configuration.";
        // Log error: error_log("Broker config load failed: " . $e->getMessage());
    }

    // --- PRG: Check for and retrieve the one-time submission success flag ---
    $just_submitted = $_SESSION['just_submitted'] ?? false;
    // Clear the flash session variable so it doesn't show on refresh
    unset($_SESSION['just_submitted']); 
    // ==================== LOGOUT ====================
    if (isset($_GET['logout'])) {
        session_unset();
        session_destroy();
        header("Location: servharv.php");
        exit;
    }
    // ==================== CURRENT USER STATUS (MODIFIED) ====================
    $logged_in_email = $_SESSION['user_email'] ?? '';
    $already_submitted = false;
    $application_status = ''; // Default to empty string now, to match NULL/empty DB field

    // New variables to hold broker details for display
    $user_broker = '';
    $user_server = '';
    $user_login = '';

    if ($logged_in_email !== '') {
        // Select application_status AND broker details
        $stmt = $pdo->prepare("SELECT application_status, broker, server, login FROM insiders_server WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower($logged_in_email)]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $application_status = $row['application_status'] ?? '';
            
            // Populate broker details if they exist (even if status is pending, approved, etc.)
            $user_broker = $row['broker'] ?? '';
            $user_server = $row['server'] ?? '';
            $user_login = $row['login'] ?? '';

            // *** MODIFIED LOGIC: User is considered 'already submitted' ONLY if application_status is NOT empty/NULL ***
            if (!empty($application_status)) {
                $already_submitted = true;
            } else {
                // application_status is NULL/empty, so they need to submit the broker details form.
                $already_submitted = false;
            }
        } else {
            // If the user's email is logged in but no row exists in the insiders_server table, create a row.
            try {
                // application_status defaults to NULL or 'pending' in your DB schema. We'll rely on the default.
                $stmt = $pdo->prepare("INSERT INTO insiders_server (email) VALUES (?)");
                $stmt->execute([strtolower($logged_in_email)]);
                // $already_submitted remains false and $application_status remains '', allowing registration flow.
            } catch (PDOException $e) {
                // If a race condition occurs and the row exists, just ignore the error.
            }
        }
    }
    // ==================== EMAIL LOGIN ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
        $email = trim(strtolower($_POST['login_email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['user_email'] = $email;
            // Redirect will trigger the logic above, which handles row creation/check
            header("Location: servharv.php");
            exit;
        } else {
            echo "<script>alert('Invalid email address.');</script>";
        }
    }
    // ==================== SUBMIT BROKER DETAILS ====================
    
    // Check for previous error if any from broker config load
    if (empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_insider']) && $logged_in_email !== '') {
        $broker      = $_POST['broker'] ?? '';
        $fullname = trim($_POST['fullname'] ?? '');
        $server      = trim($_POST['server'] ?? '');
        $login       = trim($_POST['login'] ?? ''); 
        $password = $_POST['password'] ?? '';
        
        // Check 1: Validate input fields against dynamically loaded list
        if (!in_array($broker, $allowed_brokers)) { 
            $error = "Invalid broker selected.";
        } elseif (empty($fullname) || empty($server) || empty($login) || empty($password)) {
            $error = "All fields are required.";
        } else {
            // --- NEW LOGIC: Check for existing account with same Server and Login ID ---
            try {
                // Check if the same Login ID and Server combination already exists for ANY user
                $stmt = $pdo->prepare("
                    SELECT email, application_status 
                    FROM insiders_server 
                    WHERE login = ? AND server = ? 
                    LIMIT 1
                ");
                $stmt->execute([$login, $server]);
                $existing_account = $stmt->fetch();

                if ($existing_account) {
                    // Check 2: If the existing account belongs to a different email, or if it's the current email 
                    // but the existing record has a non-empty status (meaning it was already submitted/blacklisted/etc.)
                    if ($existing_account['email'] !== strtolower($logged_in_email)) {
                        // Scenario 1: Login ID + Server is already registered by a DIFFERENT email.
                        $error = "This broker account (Login No. and Server) is already registered by another user.";
                    } elseif ($existing_account['application_status'] === 'blacklisted') {
                        // Scenario 2: Login ID + Server belongs to the CURRENT email, BUT the account is blacklisted.
                        $error = "Your submission is not allowed. This account is blacklisted.";
                    }
                }
            } catch (PDOException $e) {
                // Handle DB check error
                $error = "Database check failed. Please try again.";
            }
        }
        
        // Proceed with update only if there were NO errors
        if (empty($error)) {
            try {
                // Use UPDATE - this logic assumes the row for the email already exists and application_status is NULL/empty.
                // application_status is explicitly set to 'pending' upon successful submission.
                $stmt = $pdo->prepare("
                    UPDATE insiders_server 
                    SET broker = ?, fullname = ?, server = ?, login = ?, password = ?, application_status = 'pending'
                    WHERE email = ?
                ");
                $stmt->execute([$broker, $fullname, $server, $login, $password, strtolower($logged_in_email)]);
                
                // --- PRG: Set a flash message/flag and redirect to prevent form resubmission ---
                $_SESSION['just_submitted'] = true;
                header("Location: servharv.php");
                exit;
            } catch (PDOException $e) {
                // General failure
                $error = "Submission failed. Try again.";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HarvHub – Launching April 30, 2026</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    /* MODIFIED STYLES for Light Mode (default) and Dark Mode */
    :root { 
        --bg: #fff; 
        --text: #1c1e21; 
        --accent: #d8c701ff; 
        --input-bg: #f0f2f5; 
        --section-bg: #fff;
        --section-shadow: 0 4px 12px rgba(0,0,0,0.08); 
        --header-bg: #f5f5f5; 
        --profile-bg: #e9ebee; 
        --profile-icon-bg: #ccc;
        --profile-details-bg: #f9f9f9;
        --profile-details-border: #ddd;
    }
    @media (prefers-color-scheme: dark) {
        :root { 
            --bg: #000; 
            --text: #e4e6eb; 
            --accent: #d8c701ff; 
            --input-bg: #1a1a1a; 
            --section-bg: rgba(255,255,255,0.05); 
            --section-shadow: none; 
            --header-bg: rgba(0,0,0,0.3);
            --profile-bg: #1a1a1a; 
            --profile-icon-bg: #444;
            --profile-details-bg: #0d0d0d;
            --profile-details-border: #333;
        }
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); height: 100vh; overflow: hidden; position: relative;}
    html, body { -ms-overflow-style: none; scrollbar-width: none; }
    html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
    .container, .modal-content { overflow-y: auto; -ms-overflow-style: none; scrollbar-width: none; }
    .container::-webkit-scrollbar, .modal-content::-webkit-scrollbar { display: none; }
    /* Background effects */
    body::before {
        content: ""; position: absolute; inset: 0;
        background: radial-gradient(circle at 20% 80%, #1a0033 0%, transparent 50%),
                    radial-gradient(circle at 80% 20%, #000033 0%, transparent 50%),
                    url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="white"/><circle cx="30" cy="70" r="1.5" fill="white"/><circle cx="70" cy="30" r="1" fill="white"/><circle cx="90" cy="80" r="1.2" fill="white"/><circle cx="50" cy="50" r="1.8" fill="white"/></svg>') repeat;
        background-size: cover, cover, 120px 120px; 
        opacity: 0.5; 
        pointer-events: none; 
        z-index: -1;
    }
    @media (prefers-color-scheme: light) {
        body::before { opacity: 0.1; background-blend-mode: multiply; }
    }
    .container { height: 100vh; padding: 2rem; }
    header { 
        position: relative; text-align: center; padding: 1rem 2rem 2rem; 
        background: var(--header-bg); 
        border-radius: 15px; margin-bottom: 2rem; 
        box-shadow: var(--section-shadow);
        min-height: 120px; 
    }
    h1 { font-size: 4rem; color: var(--accent); margin-bottom: 0.5rem; }
    h2 { margin: 2rem 0 1rem; color: var(--accent); }
    .section { 
        background: var(--section-bg); 
        padding: 2rem; 
        border-radius: 12px; 
        margin-bottom: 2rem; 
        box-shadow: var(--section-shadow);
    }
    .btn { padding: 1rem 2.5rem; background: var(--accent); color: #000; font-weight: bold; border: none; border-radius: 50px; cursor: pointer; display: inline-block; transition: all 0.3s; }
    .btn:hover { opacity: 0.9; transform: scale(1.05); }
    .btn.blacklisted {
        background: #9e9e9e; color: #555; cursor: default;
        opacity: 0.6; pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .btn.blacklisted:hover { opacity: 0.6; transform: none; }

    /* --- DESKTOP PROFILE STYLES (Minimal Icon & Expandable Card) --- */
    .user-profile-status {
        position: absolute;
        top: 30px; 
        left: 30px; 
        z-index: 20; 
    }
    #profileIcon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--profile-icon-bg);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem; 
        color: var(--text);
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        transition: transform 0.2s, background 0.3s;
        font-weight: bold;
    }
    #profileIcon:hover { transform: scale(1.05); }
    #profileIcon.active { background: var(--accent); color: #000; }

    #profileCard {
        position: absolute;
        top: 0;
        left: 60px; 
        width: 250px;
        background: var(--profile-details-bg);
        border: 1px solid var(--profile-details-border);
        border-radius: 10px;
        padding: 15px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.4);
        opacity: 0;
        visibility: hidden;
        transform: translateX(-10px); 
        transition: opacity 0.3s, transform 0.3s, visibility 0.3s;
    }
    #profileCard.active {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
    }
    .profile-details p {
        margin: 5px 0;
        font-size: 0.95rem;
        word-break: break-all;
    }
    .profile-details strong {
        color: var(--accent);
        margin-right: 5px;
    }
    /* Removed .logout-link styles here as the link is moved */

    /* --- MOBILE PROFILE STYLES (Always visible below header) --- */
    #mobileProfileStatus {
        display: none; /* Default to hidden on desktop */
        text-align: left;
        padding: 15px 20px;
        margin: 15px 0 0;
        border-top: 1px solid var(--profile-details-border);
        background: var(--profile-details-bg);
        border-radius: 10px;
    }
    #mobileProfileStatus p {
        margin: 5px 0;
        font-size: 1rem;
    }
    
    /* --- NEW CENTRAL LOGOUT STYLES --- */
    .central-logout {
        text-align: center;
        margin-top: 15px; /* Spacing below the main button */
    }
    .central-logout a {
        font-size: 1rem;
        color: #ff6b6b;
        text-decoration: none;
        padding: 8px 15px;
        border-radius: 5px;
        transition: color 0.2s, background-color 0.2s;
    }
    .central-logout a:hover {
        color: #d63031;
        background-color: rgba(255, 107, 107, 0.1);
    }

    /* --- RESPONSIVE MEDIA QUERIES --- */
    @media (min-width: 768px) {
        /* Desktop: Show icon/card, Hide mobile block */
        #mobileProfileStatus {
            display: none !important;
        }
        .user-profile-status {
            display: block !important;
        }
    }
    @media (max-width: 767px) {
        /* Mobile: Hide icon/card, Show mobile block */
        header {
            min-height: auto; 
            padding-bottom: 1rem; 
        }
        .user-profile-status {
            display: none !important;
        }
        #mobileProfileStatus {
            display: block !important;
        }
    }
    /* --- END RESPONSIVE STYLES --- */

    /* MODAL STYLES (unchanged) */
    .modal { 
        display: none; position: fixed; inset: 0; 
        background: rgba(0,0,0,0.01); 
        backdrop-filter: blur(12px); 
        align-items: center; justify-content: center; z-index: 999; padding: 1rem; 
    }
    .modal.active { display: flex; }
    .modal-content { background: var(--bg); color: var(--text); padding: 2.5rem; border-radius: 20px; width: 90%; max-width: 520px; max-height: 95vh; position: relative; box-shadow: 0 15px 50px rgba(0,0,0,0.6); overflow-y: auto; }
    .close { position: absolute; top: 15px; right: 20px; font-size: 2.5rem; cursor: pointer; opacity: 0.7; }
    .close:hover { opacity: 1; }
    input, select { width: 100%; padding: 14px; margin: 10px 0 16px; border: 1px solid #555; border-radius: 10px; background: var(--input-bg); color: var(--text); font-size: 1rem; }
    select { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ccc'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/path%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 12px center; background-size: 16px; }
    .password-wrapper { position: relative; }
    .password-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text); font-size: 0.9rem; user-select: none; }
    .error-text { color: #ff6b6b; margin-top: 8px; text-align: center; }
    .checkbox-container { display: flex; align-items: center; gap: 12px; margin: 28px 0; font-size: 1rem; cursor: pointer; }
    .checkbox-container input[type="checkbox"] { width: 22px; height: 22px; margin: 0; }
</style>

</head>
<body>
    <div class="container">
        <header>
            <?php if ($logged_in_email !== ''): ?>
                <div class="user-profile-status">
                    <div id="profileIcon">👤</div> 
                    <div id="profileCard" class="profile-details">
                        <p style="font-weight:bold; color:var(--accent); font-size:1.1rem; margin-bottom:8px;">Profile Details</p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($logged_in_email) ?></p>
                        <p><strong>Status:</strong> <?= ucfirst($application_status ?: 'Not Submitted') ?></p>
                        <?php if (!empty($user_broker)): ?>
                            <p><strong>Broker:</strong> <?= htmlspecialchars($user_broker) ?></p>
                            <p><strong>Login:</strong> <?= htmlspecialchars($user_login) ?></p>
                            <p><strong>Server:</strong> <?= htmlspecialchars($user_server) ?></p>
                        <?php endif; ?>
                        </div>
                </div>
            <?php endif; ?>
            
            <h1>HarvHub</h1>
            <p style="font-size:1.6rem; margin:1rem 0;">Public Launch: <strong>April 30, 2026</strong></p>
            <p>Join the waiting list now and become an Insider</p>

            <?php if ($logged_in_email !== ''): ?>
                <div id="mobileProfileStatus" class="profile-details">
                    <p style="font-weight:bold; color:var(--accent); font-size:1.1rem; margin-bottom:8px;">Your Status</p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($logged_in_email) ?></p>
                    <p><strong>Status:</strong> <?= ucfirst($application_status ?: 'Not Submitted') ?></p>
                    <?php if (!empty($user_broker)): ?>
                        <p><strong>Broker:</strong> <?= htmlspecialchars($user_broker) ?></p>
                        <p><strong>Login:</strong> <?= htmlspecialchars($user_login) ?></p>
                        <p><strong>Server:</strong> <?= htmlspecialchars($user_server) ?></p>
                    <?php endif; ?>
                    </div>
            <?php endif; ?>
        </header>
        
        <div class="section">
            <h2>Investing and Harvesting</h2>
            <p>Minimum of $50 deposit to your chosen broker MT5 broker.<br>
                Choose a professional trader by ratings & statistics.<br>
                Your capital is protected - no over-leverage risk.<br>
                Expect minimum profit of at least the double of your deposit in 30 days (70% to you, 30% to developer).</p>
        </div>
        <div class="section">
            <h2>Developer</h2>
            <p>Build your strategy with simple drag-and-drop options in the Developer Dashboard.<br>
                HarvHub executes it 24/7 for you and your investors.</p>
        </div>
        <div class="section">
            <h2>Account Manager</h2>
            <p>Build or purchase a proven strategy (minimum 45% win rate), place ads, and attract investors. <br>
                You deposit to your own MT5 so as to execute along with your investors and earn 20% of investors' profit.<br>
                HarvHub executes everything automatically and manages risk thus charges the remaining 10%.</p>
        </div>
        <div class="section">
            <h2>Requirements & Profit Share</h2>
            <ul>
                <li>Investors: minimum of $50 to broker + same deposit amount as collateral.</li>
                <li>Profit split: 70% investor, 20% manager, 10% fee charged by HarvHub services</li>
                <li>Minimum expected profit of at least your deposit amount in 30 days</li>
            </ul>
        </div>
        <div style="text-align:center; margin:3rem 0;">
            <?php
            $button_text = 'Join insiders_server Now';
            $button_id = 'joinBtn';
            $button_onclick = '';
            $button_class = 'btn';

            if ($logged_in_email === '') {
                $button_text = 'Continue with your Email';
            } elseif ($already_submitted) { // application_status is NOT empty/NULL
                if ($application_status === 'blacklisted') {
                    $button_text = 'You will be invited';
                    $button_id = 'blacklistedBtn';
                    $button_class = 'btn blacklisted'; 
                    $button_onclick = ''; 
                } elseif ($application_status === 'approved') {
                    $button_text = 'Go to Dashboard';
                    $button_id = 'dashboardBtn';
                    $button_onclick = "window.location.href='dashboard.php'";
                } elseif ($application_status === 'declined') {
                    $button_text = 'Not Eligible';
                    $button_id = 'declinedBtn';
                } else { // pending or any other non-empty status
                    $button_text = 'Application Received';
                    $button_id = 'submittedBtn';
                }
            }
            ?>
        <div style="margin-bottom: 100px;">
                <button class="<?= $button_class ?>" id="<?= $button_id ?>" <?= $button_onclick ? "onclick=\"$button_onclick\"" : "" ?>>
                    <?= $button_text ?>
                </button>
                <?php if ($logged_in_email !== ''): ?>
                    <div class="central-logout">
                        <a href="?logout=1">Logout</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('emailModal')">×</span>
            <h2 style="text-align:center;">Continue with Your Email</h2>
            <form method="POST" style="margin-top:30px;">
                <input type="email" name="login_email" placeholder="youremail@gmail.com" required style="text-align:center; font-size:1.1rem;">
                <button type="submit" class="btn" style="width:100%; margin-top:15px;">Continue</button>
            </form>
        </div>
    </div>
    <div id="brokerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('brokerModal')">×</span>
            <h2 style="text-align:center;">Register with a Broker</h2>
            <div style="text-align:center; margin:2rem 0;">
                <?php if (!empty($allowed_brokers)): ?>
                    <?php foreach ($allowed_brokers as $broker): 
                        $link_url = $broker_targets[$broker] ?? 'about:blank';
                    ?>
                        <a href="<?= htmlspecialchars($link_url) ?>" target="_blank" class="btn" style="width:80%; max-width:350px; margin:10px auto;">
                            Open <?= htmlspecialchars($broker) ?> Account
                        </a>
                        <br>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="error-text">Broker configuration is currently unavailable. Please check back later.</p>
                <?php endif; ?>
            </div>
            <p style="text-align:center; font-size:1.4rem; margin:2rem 0; color:var(--accent);">OR</p>
            <button class="btn" onclick="openExistingModal()" style="width:80%; max-width:350px; display:block; margin: 0 auto;">I already have an account</button>
        </div>
    </div>
    <div id="existingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('existingModal')">×</span>
            <h2>Important Instructions</h2>
            <p><strong>1.</strong> Deposit minimum $50 USD to your MT5 account.</p>
            <p><strong>2.</strong> Your capital will certainly double in 30 days.</p>
            <p><strong>3.</strong> Do NOT withdraw during this 30-day period.</p>
            <p><strong>4.</strong> After 30 days, pay 30% of profit to remain eligible.</p>
            <div class="checkbox-container">
                <input type="checkbox" id="agree">
                <label for="agree">I understand and agree to the terms above</label>
            </div>
            <div id="agreeError" class="error-text" style="display:none;">Please agree to continue</div>
            <button class="btn" style="width:100%; margin-top:20px;" onclick="checkAgreement()">Continue to Registration</button>
        </div>
    </div>
    <div id="insiderModal" class="modal <?php echo ($just_submitted || ($logged_in_email !== '' && !$already_submitted && $error)) ? 'active' : ''; ?>">
        <div class="modal-content">
            <span class="close" onclick="closeModal('insiderModal')">×</span>
            <?php if ($already_submitted || $just_submitted): ?>
                <div style="text-align:center; padding:3rem 1rem;">
                    <?php if ($application_status === 'blacklisted'): ?>
                        <h2 style="color:#ff6b6b;">Blacklisted</h2>
                        <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                            Your access to this service has been restricted.
                        </p>
                    <?php elseif ($application_status === 'declined'): ?>
                        <h2 style="color:#ff6b6b;">Application Declined</h2>
                        <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                            Your request to join insiders_server was declined
                        </p>
                    <?php elseif ($application_status === 'approved'): ?>
                        <h2 style="color:#90ee90;">Access Approved</h2>
                        <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                            Your access is approved. Please click 'Go to Dashboard' on the main page.
                        </p>
                    <?php else: ?>
                        <h2 style="color:#90ee90;">Application Received</h2>
                        <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                            We will notify you when execution begins.
                        </p>
                    <?php endif; ?>
                    <button class="btn" style="margin-top:2rem; width:80%; max-width:300px;" onclick="closeModal('insiderModal')">
                        Close
                    </button>
                </div>
            <?php else: ?>
                <h2 style="text-align:center;">Complete Your Registration</h2>
                <p style="text-align:center; margin-bottom:20px;">Email: <?= htmlspecialchars($logged_in_email) ?></p>
                <?php if ($error): ?>
                    <p class="error-text"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>
                <form method="POST">
                    <label>Broker</label>
                    <select name="broker" required>
                        <option value="">-- Select Broker --</option>
                        <?php foreach ($allowed_brokers as $broker): ?>
                            <option value="<?= htmlspecialchars($broker) ?>" <?= (($_POST['broker'] ?? '') == $broker) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($broker) ?>
                            </option>
                        <?php endforeach; ?>
                        </select>
                    <label>Fullname in broker</label>
                    <input type="text" name="fullname" value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
                    <label>Server</label>
                    <input type="text" name="server" value="<?= htmlspecialchars($_POST['server'] ?? '') ?>" placeholder="" required>
                    <label>Login No.</label>
                    <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" required>
                    <label>Mt5 Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="pass" required>
                        <span class="password-toggle" onclick="togglePass()">Show</span>
                    </div>
                    <p style="color:orange; font-weight:bold; margin-top:25px; text-align:center;">
                        This can only be submitted once. Double-check your details.
                    </p>
                    <button type="submit" name="submit_insider" class="btn" style="width:100%; margin-top:15px;">
                        Submit & Join insiders_server
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div id="declinedModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('declinedModal')">×</span>
            <div style="text-align:center; padding:3rem 1rem;">
                <h2 style="color:#ff6b6b;">Access Declined</h2>
                <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                    Your request to join insiders_server was declined
                </p>
                <button class="btn" style="margin-top:2rem; width:80%; max-width:300px;" onclick="closeModal('declinedModal')">
                    Close
                </button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const joinBtn = document.getElementById('joinBtn');
            const declinedBtn = document.getElementById('declinedBtn');
            const submittedBtn = document.getElementById('submittedBtn');
            const blacklistedBtn = document.getElementById('blacklistedBtn'); 
            
            const profileIcon = document.getElementById('profileIcon');
            const profileCard = document.getElementById('profileCard');

            // Profile Icon Click Handler (for desktop/tablet)
            if (profileIcon) {
                profileIcon.addEventListener('click', function(e) {
                    e.stopPropagation(); 
                    profileCard.classList.toggle('active');
                    profileIcon.classList.toggle('active');
                });

                // Close card when clicking anywhere outside it
                document.addEventListener('click', function(e) {
                    if (profileCard.classList.contains('active') && !profileCard.contains(e.target) && e.target !== profileIcon) {
                        profileCard.classList.remove('active');
                        profileIcon.classList.remove('active');
                    }
                });
                // Stop propagation on card to prevent document click closing it when interacting with details
                profileCard.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            // Logic for 'Continue with your Email' or 'Join insiders_server Now'
            if (joinBtn) {
                joinBtn.addEventListener('click', function() {
                    <?php if ($logged_in_email === ''): ?>
                        openEmailModal();
                    <?php else: ?>
                        // Logged in, and application_status is NULL/empty, so start broker flow
                        openBrokerModal();
                    <?php endif; ?>
                });
            }
            
            // Logic for 'Application Received' button 
            if (submittedBtn) {
                submittedBtn.addEventListener('click', function() {
                    document.getElementById('insiderModal').classList.add('active');
                });
            }
            // Logic for 'Not Eligible' button
            if (declinedBtn) {
                declinedBtn.addEventListener('click', function() {
                    document.getElementById('declinedModal').classList.add('active');
                });
            }

            // Logic for 'You will be invited' button (blacklisted)
            if (blacklistedBtn) {
                blacklistedBtn.addEventListener('click', function() {
                    document.getElementById('insiderModal').classList.add('active');
                });
            }


            // Automatically show status modal if just submitted (via PRG flash message)
            <?php if ($just_submitted || ($logged_in_email !== '' && $application_status === 'blacklisted' && $already_submitted)): ?>
                document.getElementById('insiderModal').classList.add('active');
            <?php endif; ?>

        });
        function openEmailModal() { document.getElementById('emailModal').classList.add('active'); }
        function openBrokerModal() { document.getElementById('brokerModal').classList.add('active'); }
        function openExistingModal() { closeModal('brokerModal'); document.getElementById('existingModal').classList.add('active'); }
        function checkAgreement() {
            if (document.getElementById('agree').checked) {
                closeModal('existingModal');
                // This leads to the final registration form (insiderModal)
                document.getElementById('insiderModal').classList.add('active');
            } else {
                document.getElementById('agreeError').style.display = 'block';
            }
        }
        function closeModal(id) { 
            document.getElementById(id).classList.remove('active'); 
            // Clear agreement error when modal closes
            if(id === 'existingModal') {
                document.getElementById('agree').checked = false; // Reset checkbox
                document.getElementById('agreeError').style.display = 'none';
            }
        }
        function togglePass() {
            const p = document.getElementById('pass');
            const t = document.querySelector('.password-toggle');
            if (p.type === 'password') { p.type = 'text'; t.textContent = 'Hide'; }
            else { p.type = 'password'; t.textContent = 'Show'; }
        }
        window.onclick = function(e) {
            // This handles general modal closing for emailModal, brokerModal, etc.
            if (e.target.classList.contains('modal')) e.target.classList.remove('active');
        };
    </script>
</body>
</html>