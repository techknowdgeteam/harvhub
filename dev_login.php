<?php
    session_start();
    //dev_login.php
    // ==================== DATABASE CONNECTION ====================
    try {
        $pdo = new PDO(
            "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
            "if0_40473107",
            "InDQmdl53FZ85",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    // ==================== FETCH SERVER ACCOUNT SETTINGS ====================
    $min_broker_balance = 50.00;
    try {
        $stmt = $pdo->query("SELECT min_broker_balance FROM server_account LIMIT 1");
        $serverConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($serverConfig && isset($serverConfig['min_broker_balance'])) {
            $min_broker_balance = (float)$serverConfig['min_broker_balance'];
        }
    } catch (PDOException $e) {}

    // ==================== FETCH BROKER CONFIGURATION ====================
    $allowed_brokers = [];
    $broker_targets = [];
    $error = '';

    try {
        $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config) {
            // ============ PARSE BROKERS ============
            $raw_brokers = explode(',', $config['brokers'] ?? '');
            foreach ($raw_brokers as $entry) {
                $entry = trim($entry);
                if (empty($entry)) continue;
                
                $broker_name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
                $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);
                $broker_name = trim($broker_name);
                
                if (!empty($broker_name) && !in_array(ucfirst($broker_name), $allowed_brokers)) {
                    $allowed_brokers[] = ucfirst($broker_name);
                }
            }

            // ============ PARSE BROKER LINKS ============
            $raw_links = explode(',', $config['brokers_link'] ?? '');
            foreach ($raw_links as $entry) {
                $entry = trim($entry);
                if (empty($entry)) continue;
                
                if (strpos($entry, ':') !== false) {
                    $parts = explode(':', $entry);
                    $parts = array_filter(array_map('trim', $parts));
                    
                    if (count($parts) >= 2) {
                        $last = end($parts);
                        if (strpos($last, 'http') !== false || strpos($last, '.') !== false) {
                            $link_url = $last;
                            $link_name = (count($parts) >= 3) ? $parts[count($parts) - 2] : $parts[0];
                        } else {
                            $link_url = '';
                            $link_name = '';
                            foreach ($parts as $part) {
                                if (strpos($part, 'http') !== false || strpos($part, '.') !== false) {
                                    $link_url = $part;
                                } else {
                                    $link_name = $part;
                                }
                            }
                            if (empty($link_url) && !empty($parts)) {
                                $link_url = 'https://' . end($parts);
                                $link_name = $parts[0];
                            }
                        }
                    } else {
                        $link_url = $parts[0];
                        $url_parts = parse_url($link_url);
                        $host = $url_parts['host'] ?? '';
                        $link_name = ucfirst(explode('.', $host)[0] ?? '');
                    }
                } else {
                    if (strpos($entry, '.') !== false || strpos($entry, '://') !== false) {
                        $link_url = $entry;
                        $url_parts = parse_url($link_url);
                        $host = $url_parts['host'] ?? '';
                        $link_name = ucfirst(explode('.', $host)[0] ?? '');
                    } else {
                        $link_name = ucfirst(trim($entry));
                        $link_url = '';
                    }
                }
                
                $link_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $link_name);
                $link_name = trim($link_name);
                
                if (!empty($link_url)) {
                    $link_url = trim($link_url);
                    if (strpos($link_url, '://') === false && !empty($link_url)) {
                        $link_url = 'https://' . $link_url;
                    }
                }
                
                if (!empty($link_name) && !empty($link_url)) {
                    $broker_targets[$link_name] = $link_url;
                }
            }
        }
        
        $allowed_brokers = array_unique($allowed_brokers);
        sort($allowed_brokers);
        
    } catch (PDOException $e) {
        $error = "Failed to load broker configuration.";
    }

    // Handle logout - redirect to dev_login.php with logged_out parameter
    if (isset($_GET['logout']) && $_GET['logout'] == 1) {
        session_unset();
        session_destroy();
        header("Location: dev_login.php?logged_out=1");
        exit;
    }

    $logged_out_message = '';
    if (isset($_GET['logged_out']) && $_GET['logged_out'] == 1) {
        $logged_out_message = 'You have been logged out successfully.';
    }
    
    $logged_in_email = $_SESSION['user_email'] ?? '';
    $user_fullname = '';
    $login_error = '';
    $show_not_developer_modal = false;

    // ==================== CHECK USER STATUS ====================
    // Only check if email is verified, nothing else
    if ($logged_in_email !== '') {
        $stmt = $pdo->prepare("SELECT email_verified, fullname FROM harvhub WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower($logged_in_email)]);
        $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_check) {
            // STEP 1: ALWAYS CHECK EMAIL VERIFICATION FIRST
            if (isset($user_check['email_verified']) && $user_check['email_verified'] == 0) {
                $_SESSION['pending_verification_email'] = $logged_in_email;
                $_SESSION['otp_step'] = 'request';
                $_SESSION['return_after_verify'] = 'dev_login.php';
                $_SESSION['is_approved_user'] = false;
                header("Location: verify_email.php?source=dev_login");
                exit;
            }
            
            $user_fullname = $user_check['fullname'] ?? '';
            
            // If email is verified, go straight to dev_app.php
            header("Location: dev_app.php");
            exit;
        }
    }

    // ==================== HANDLE LOGIN ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_email'])) {
        $email = trim(strtolower($_POST['login_email']));
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id, password, email_verified, fullname FROM harvhub WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // STEP 1: ALWAYS CHECK EMAIL VERIFICATION FIRST
                if (isset($user['email_verified']) && $user['email_verified'] == 0) {
                    $_SESSION['pending_verification_email'] = $email;
                    $_SESSION['otp_step'] = 'request';
                    $_SESSION['return_after_verify'] = 'dev_login.php';
                    $_SESSION['is_approved_user'] = false;
                    $_SESSION['user_email'] = $email;
                    header("Location: verify_email.php?source=dev_login");
                    exit;
                }
                
                // STEP 2: Check passkey
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    if (password_verify($_POST['password'], $user['password'] ?? '')) {
                        $_SESSION['user_email'] = $email;
                        header("Location: dev_app.php");
                        exit;
                    } else {
                        $login_error = "Incorrect password. Please try again.";
                        $_SESSION['login_email_temp'] = $email;
                        $_SESSION['login_error'] = $login_error;
                        header("Location: dev_login.php");
                        exit;
                    }
                } else {
                    $_SESSION['login_email_temp'] = $email;
                    $_SESSION['show_password_field'] = true;
                    header("Location: dev_login.php");
                    exit;
                }
            } else {
                $login_error = "No account found with this email. Please create an account.";
                $_SESSION['login_error'] = $login_error;
                $_SESSION['login_email_temp'] = $email;
                header("Location: dev_login.php");
                exit;
            }
        } else {
            $login_error = "Invalid email address.";
            $_SESSION['login_error'] = $login_error;
            header("Location: dev_login.php");
            exit;
        }
    }

    // Clear session flags after handling
    $show_password_field = $_SESSION['show_password_field'] ?? false;
    $login_email_temp = $_SESSION['login_email_temp'] ?? '';
    $login_error = $_SESSION['login_error'] ?? '';
    $show_not_developer_modal = $_SESSION['show_not_developer_modal'] ?? false;
    
    unset($_SESSION['show_password_field']);
    unset($_SESSION['login_email_temp']);
    unset($_SESSION['login_error']);
    unset($_SESSION['show_not_developer_modal']);

    function showSpinner() {
        echo '<style>
            .spinner-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
                -webkit-backdrop-filter: blur(4px);
                z-index: 99999;
                justify-content: center;
                align-items: center;
                flex-direction: column;
            }
            .spinner-overlay.active { 
                display: flex; 
            }
            .spinner {
                display: inline-block;
                width: 40px;
                height: 40px;
                border: 3px solid rgba(255, 255, 255, 0.1);
                border-radius: 50%;
                border-top-color: var(--accent, #10b981);
                animation: spin 0.6s linear infinite;
                margin-bottom: 12px;
            }
            .spinner-text {
                color: rgba(255, 255, 255, 0.7);
                font-size: 14px;
                margin: 0;
                letter-spacing: 0.5px;
            }
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
        <div class="spinner-overlay active" id="spinnerOverlay">
            <div class="spinner"></div>
            <p class="spinner-text">Loading...</p>
        </div>
        <script>
            window.addEventListener("load", function() {
                var overlay = document.getElementById("spinnerOverlay");
                if (overlay) {
                    setTimeout(function() {
                        overlay.classList.remove("active");
                    }, 300);
                }
            });
            document.addEventListener("DOMContentLoaded", function() {
                var overlay = document.getElementById("spinnerOverlay");
                if (overlay) {
                    setTimeout(function() {
                        overlay.classList.remove("active");
                    }, 200);
                }
            });
        </script>';
    }

    showSpinner();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Developer Platform</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include 'index_style.php' ?>

</head>
<body>
        <header>
            <div>
                <h1>HarvHub Developers</h1>
            </div>
        </header>
        
        <div class="info-grid">
            <div class="info-card">
                <h3>For Developers</h3>
                <p>Build and train your AI to understand your strategy. Analysis and precision will be taken on your preferred symbols.</p>
                <ul>
                    <li>Submit technical analysis via the Developer Dashboard for review.</li>
                    <li>Automated strategy validation.</li>
                    <li>Live market execution.</li>
                    <li>Monitor performance metrics in real-time.</li>
                </ul>
            </div>
            
            <div class="info-card">
                <h3>Developer Requirements & Guidelines</h3>
                <p>Build and submit your trading strategies for automated analysis on your chosen markets.</p>
                <ul>
                    <li>Your developed strategy must have at least a 40% win rate.</li>
                    <li>Real Account: Only real accounts will be verified; demo accounts are not allowed.</li>
                    <li>Automated strategy validation.</li>
                    <li>Submit technical analysis via the Developer Dashboard for review.</li>
                </ul>
            </div>
        </div>
        
        <div style="text-align:center; margin:2rem 0;">
            <div style="margin-bottom: 60px;">
                <button class="btn" id="joinBtn" onclick="openEmailModal()">
                    Sign up or Login
                </button>
            </div>
        </div>

    <!-- Email/Login Modal -->
    <div id="emailModal" class="modal <?php echo ($show_password_field || $login_error) ? 'active' : ''; ?>">
        <div class="modal-content">
            <span class="close" onclick="closeModal('emailModal')">×</span>
            <h2 style="text-align:center;">Harvhub Login</h2>
            <form method="POST" style="margin-top:30px;" id="loginForm">
                <input type="email" name="login_email" id="loginEmailInput" placeholder="Enter your email" required style="text-align:center; font-size:1.1rem;" value="<?= htmlspecialchars($login_email_temp) ?>">
                
                <?php if ($login_error): ?>
                    <p class="error-text" style="color: #ff6b6b; margin-top: 12px; text-align: center;"><?= htmlspecialchars($login_error) ?></p>
                <?php endif; ?>
                
                <?php if ($show_password_field): ?>
                    <div id="passwordFieldContainer" style="margin-top: 15px;">
                        <input type="password" name="password" id="loginPasswordInput" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn" style="width:100%; margin-top:15px;">Login</button>
                    <p style="margin-top: 15px; text-align: center; font-size: 0.9rem; opacity: 0.7;">
                        <a href="forgot_password.php?source=dev_login" style="color: var(--accent);">Forgot Password?</a>
                    </p>
                <?php else: ?>
                    <button type="submit" class="btn" style="width:100%; margin-top:15px;">Continue</button>
                <?php endif; ?>
                
                <div style="margin-top: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <p style="opacity:0.7; font-size:0.9rem;">Need an account? Contact support to register as a developer.</p>
                </div>
            </form>
        </div>
    </div>

    <!-- Not Developer Modal -->
    <div id="notDeveloperModal" class="modal <?php echo $show_not_developer_modal ? 'active' : ''; ?>">
        <div class="modal-content">
            <span class="close" onclick="window.location.href='?logout=1'">×</span>
            <div style="text-align:center; padding:2rem 1rem;">
                <h2 style="color: #ff6b6b;">Access Restricted</h2>
                <p style="font-size:1.2rem; line-height:1.7; margin:1.5rem 0;">
                    This platform is exclusively for developers. You are currently not registered as a developer.
                </p>
                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; margin: 20px 0;">
                    <h3 style="color: var(--accent);">Contact Support</h3>
                    <p style="opacity:0.8;">To gain developer access, please contact our support team:</p>
                    <p style="color: var(--accent); font-size: 1.1rem; margin-top: 10px;">
                        <a href="mailto:harvhub12@gmail.com" style="color: var(--accent);">support@harvhub.com</a>
                    </p>
                </div>
                <button class="btn" onclick="window.location.href='?logout=1'" style="margin-top: 10px;">
                    Logout Now
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let logoutTimerInterval = null;
        
        <?php if ($show_not_developer_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            let seconds = 10;
            const timerElement = document.getElementById('logoutTimer');
            
            logoutTimerInterval = setInterval(function() {
                seconds--;
                if (timerElement) {
                    timerElement.textContent = seconds;
                }
                if (seconds <= 0) {
                    clearInterval(logoutTimerInterval);
                    window.location.href = '?logout=1';
                }
            }, 1000);
        });
        <?php endif; ?>
        
        function openEmailModal() { 
            document.getElementById('emailModal').classList.add('active'); 
            setTimeout(() => {
                const emailInput = document.getElementById('loginEmailInput');
                if (emailInput) emailInput.focus();
            }, 100);
        }
        
        function closeModal(id) { 
            document.getElementById(id).classList.remove('active'); 
            if(id === 'emailModal') {
                const passwordContainer = document.getElementById('passwordFieldContainer');
                if (passwordContainer) passwordContainer.remove();
                const errorEl = document.querySelector('#emailModal .error-text');
                if (errorEl) errorEl.remove();
                const loginBtn = document.querySelector('#emailModal button[type="submit"]');
                if (loginBtn) loginBtn.textContent = 'Continue';
                const emailInput = document.getElementById('loginEmailInput');
                if (emailInput) emailInput.value = '';
            }
            if(id === 'notDeveloperModal') {
                if (logoutTimerInterval) {
                    clearInterval(logoutTimerInterval);
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    const passwordInput = document.getElementById('loginPasswordInput');
                    if (passwordInput) {
                        passwordInput.required = true;
                    }
                });
            }
            
            <?php if ($show_not_developer_modal): ?>
                document.getElementById('notDeveloperModal').classList.add('active');
            <?php endif; ?>
            
            <?php if ($show_password_field || $login_error): ?>
                openEmailModal();
            <?php endif; ?>
        });
        
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) e.target.classList.remove('active');
        };
    </script>

</body>
</html>