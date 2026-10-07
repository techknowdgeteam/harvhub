<?php
// index.php — HarvHub role-based landing page, authentication and advertisements
session_start();

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

function esc($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirectByRole($role) {
    $role = ($role === 'developer') ? 'developer' : 'investor';
    header("Location: " . ($role === 'developer' ? 'traderapp.php' : 'investorapp.php'));
    exit;
}

/**
 * Resolve where a logged-in user should land based on harvhub.last_app.
 * Whitelisted values only. Anything else (including NULL and 'managerapp'
 * for now) falls back to investorapp.php.
 */
function redirectByLastApp($lastApp) {
    $lastApp = trim((string)$lastApp);
    switch ($lastApp) {
        case 'trader_app':
            header("Location: traderapp.php");
            exit;
        case 'investorapp':
        default:
            header("Location: investorapp.php");
            exit;
    }
}

/**
 * Map harvhub.last_app → the role string used by $_SESSION['auth_role']
 * and by redirectByRole(). Keeps the rest of the app consistent.
 */
function roleFromLastApp($lastApp) {
    $lastApp = trim((string)$lastApp);
    return ($lastApp === 'trader_app') ? 'developer' : 'investor';
}

/**
 * Resolve the active sub account row for an investor based on last_account.
 * last_account format: 's{sub_account_id}' for sub accounts, 'm{main_account_id}' for main accounts.
 * Returns the harvhub row (assoc) or null.
 */
function resolveInvestorSubAccount($pdo, $tableName, $email, $lastAccount) {
    $email = strtolower(trim($email));
    $lastAccount = trim((string)$lastAccount);

    if ($lastAccount !== '') {
        // s{id} → sub account
        if (preg_match('/^s(\d+)$/', $lastAccount, $m)) {
            $subId = (int)$m[1];
            if ($subId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
                $q->execute([$subId, $email]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }
        // m{id} → main account (resolve to first sub account under it)
        if (preg_match('/^m(\d+)$/', $lastAccount, $m)) {
            $mainId = (int)$m[1];
            if ($mainId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE main_account_id = ? AND LOWER(email) = ? ORDER BY id ASC LIMIT 1");
                $q->execute([$mainId, $email]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }
    }

    // Fallback: lowest-id non-main sub account
    $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $q->execute([$email]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    // Absolute fallback: any row for this email
    $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $q->execute([$email]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

// -------------------- LOGOUT --------------------
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header("Location: index.php?logged_out=1");
    exit;
}

// -------------------- ROLE (for signup only) --------------------
$role = $_POST['role'] ?? $_GET['role'] ?? $_SESSION['auth_role'] ?? '';
$role = in_array($role, ['investor','developer'], true) ? $role : '';

$authError = '';
$signupError = '';
$loginError = '';
$loggedOut = isset($_GET['logged_out']) && $_GET['logged_out'] === '1';

// -------------------- AUTH --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['auth_action'] ?? '';

    if ($action === 'login') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $loginError = 'Please enter a valid email address.';
        } elseif ($password === '') {
            $loginError = 'Please enter your password.';
        } else {
            $stmt = $pdo->prepare("SELECT id, fullname, email, password, email_verified, last_app, last_account FROM harvhub WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$u || !isset($u['password']) ||
                !(password_verify($password, $u['password']) || hash_equals((string)$u['password'], $password))) {
                $loginError = 'Incorrect email or password.';
            } elseif ((int)($u['email_verified'] ?? 0) !== 1) {
                $_SESSION['pending_verification_email'] = $email;
                $_SESSION['pending_verification_role']  = roleFromLastApp($u['last_app'] ?? '');
                $_SESSION['otp_step'] = 'request';
                $_SESSION['return_after_verify'] = 'index.php';
                $_SESSION['is_approved_user'] = false;
                $_SESSION['user_email'] = $email;
                header("Location: verify_email.php?source=index");
                exit;
            } else {
                $lastApp     = trim((string)($u['last_app'] ?? ''));
                $lastAccount = trim((string)($u['last_account'] ?? ''));

                $_SESSION['user_email'] = $email;
                $_SESSION['is_approved_user'] = true;

                // ---- Resolve role from last_app ----
                $roleForSession = roleFromLastApp($lastApp);
                $_SESSION['auth_role'] = $roleForSession;

                // ---- For investors, resolve the recorded sub account ----
                if ($roleForSession === 'investor') {
                    $resolved = resolveInvestorSubAccount($pdo, 'harvhub', $email, $lastAccount);
                    if ($resolved) {
                        $subId  = (int)($resolved['sub_account_id'] ?? $resolved['id']);
                        $mainId = (int)($resolved['main_account_id'] ?? 0);

                        // Repair if sub_account_id is 0
                        if ($subId <= 0) {
                            $subId = (int)$resolved['id'];
                            try {
                                $pdo->prepare("UPDATE harvhub SET sub_account_id = id WHERE id = ?")->execute([$subId]);
                            } catch (Throwable $e) {}
                        }

                        $_SESSION['active_sub_account_id']  = $subId;
                        $_SESSION['active_main_account_id'] = $mainId;
                    }
                }

                // ---- Redirect ----
                if ($lastApp === 'trader_app') {
                    header("Location: traderapp.php");
                    exit;
                }
                header("Location: investorapp.php");
                exit;
            }
        }
    }

    if ($action === 'signup') {
        $selectedRole = $_POST['role'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);

        if (!in_array($selectedRole, ['investor','developer'], true)) {
            $signupError = 'Please choose Investor or Professional Trader.';
        } elseif ($firstName === '' || strlen($firstName) > 80) {
            $signupError = 'Please enter your first name.';
        } elseif ($lastName === '' || strlen($lastName) > 80) {
            $signupError = 'Please enter your last name.';
        } elseif ($username === '' || strlen($username) > 80) {
            $signupError = 'Please choose a username.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $username)) {
            $signupError = 'Username may only contain letters, numbers, dots, underscores, and hyphens (3–80 characters).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signupError = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $signupError = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $signupError = 'Passwords do not match.';
        } elseif (!$terms) {
            $signupError = 'Please accept the account terms to continue.';
        } else {
            $stmt = $pdo->prepare("SELECT id, email_verified FROM harvhub WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            $usernameTaken = false;
            try {
                $uStmt = $pdo->prepare("SELECT id FROM harvhub WHERE username = ? AND email <> ? LIMIT 1");
                $uStmt->execute([$username, $email]);
                if ($uStmt->fetch(PDO::FETCH_ASSOC)) {
                    $usernameTaken = true;
                }
            } catch (PDOException $e) {
                $usernameTaken = false;
            }

            if ($existing && (int)$existing['email_verified'] === 1) {
                $signupError = 'This email already has a verified account. Please sign in.';
            } elseif ($usernameTaken) {
                $signupError = 'That username is already taken. Please choose another.';
            } else {
                $fullName = trim($firstName . ' ' . $lastName);

                $_SESSION['pending_verification_email']     = $email;
                $_SESSION['pending_verification_firstname'] = $firstName;
                $_SESSION['pending_verification_lastname']  = $lastName;
                $_SESSION['pending_verification_username']  = $username;
                $_SESSION['pending_verification_fullname']  = $fullName;
                $_SESSION['pending_verification_password']  = password_hash($password, PASSWORD_DEFAULT);
                $_SESSION['pending_verification_role']      = $selectedRole;
                $_SESSION['otp_step'] = 'request';
                $_SESSION['return_after_verify'] = 'index.php';
                $_SESSION['signup_in_progress'] = true;
                header("Location: verify_email.php?source=index");
                exit;
            }
        }
    }
}

// Restore the selected workspace after email verification.
if (empty($_SESSION['auth_role']) && !empty($_SESSION['pending_verification_role'])) {
    $restoredRole = $_SESSION['pending_verification_role'];
    if (in_array($restoredRole, ['investor','developer'], true)) {
        $_SESSION['auth_role'] = $restoredRole;
    }
}

// -------------------- ALREADY AUTHENTICATED --------------------
if (!empty($_SESSION['user_email']) && !$authError && !$loginError && !$signupError) {
    $email = strtolower($_SESSION['user_email']);
    $stmt = $pdo->prepare("SELECT email_verified, last_app, last_account FROM harvhub WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u && (int)$u['email_verified'] === 1) {
        $lastApp     = trim((string)($u['last_app'] ?? ''));
        $lastAccount = trim((string)($u['last_account'] ?? ''));
        $roleForSession = roleFromLastApp($lastApp);
        $_SESSION['auth_role'] = $roleForSession;

        if ($roleForSession === 'investor') {
            $resolved = resolveInvestorSubAccount($pdo, 'harvhub', $email, $lastAccount);
            if ($resolved) {
                $subId  = (int)($resolved['sub_account_id'] ?? $resolved['id']);
                $mainId = (int)($resolved['main_account_id'] ?? 0);
                if ($subId <= 0) {
                    $subId = (int)$resolved['id'];
                    try {
                        $pdo->prepare("UPDATE harvhub SET sub_account_id = id WHERE id = ?")->execute([$subId]);
                    } catch (Throwable $e) {}
                }
                $_SESSION['active_sub_account_id']  = $subId;
                $_SESSION['active_main_account_id'] = $mainId;
            }
        }

        if ($lastApp === 'trader_app') {
            header("Location: traderapp.php");
            exit;
        }
        header("Location: investorapp.php");
        exit;
    }
}

// -------------------- BROKER/ACCOUNT DISPLAY CONFIG --------------------
$minBrokerBalance = 50.00;
$allowedBrokers = [];
try {
    $s = $pdo->query("SELECT min_broker_balance, brokers FROM server_account LIMIT 1");
    $cfg = $s->fetch(PDO::FETCH_ASSOC);
    if ($cfg) {
        $minBrokerBalance = (float)($cfg['min_broker_balance'] ?? $minBrokerBalance);
        foreach (explode(',', $cfg['brokers'] ?? '') as $entry) {
            $entry = trim($entry);
            if ($entry === '') continue;
            $name = strpos($entry, ':') !== false ? substr($entry, strrpos($entry, ':') + 1) : $entry;
            $name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
            if ($name !== '') $allowedBrokers[] = ucfirst($name);
        }
        $allowedBrokers = array_values(array_unique($allowedBrokers));
        sort($allowedBrokers);
    }
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>HarvHub — Invest with a Professional Trader or Trade Professionally</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?php include __DIR__ . '/index_style.php'; ?>
</head>
<body>
<nav class="nav"><div class="wrap navin">
<a href="#top" class="logo"><i>H</i> HarvHub</a>
<div class="navlinks"><a href="#roles">Choose your role</a><a href="#investors">Investors</a><a href="#traders">Professional Traders</a><a href="#how">How it works</a></div>
<div class="actions">
<button class="btn ghost" onclick="openAuth('login')">Sign in</button>
<button class="btn primary" onclick="openAuth('signup')">Sign up</button>
</div>
</div></nav>

<header class="hero" id="top"><div class="wrap heroGrid">
<div>
<span class="badge"><i class="fa-solid fa-chart-line"></i> HARVHUB</span>
<h1>Choose how you want to participate in professional trading.</h1>
<p>HarvHub connects investors who want access to professional trading strategies with experienced traders who build, test and provide signals through programmes.</p>
<div class="heroBtns">
<button class="btn primary" onclick="openRole('investor')"><i class="fa-solid fa-wallet"></i> I want to invest</button>
<button class="btn ghost" onclick="openRole('developer')"><i class="fa-solid fa-user-tie"></i> I'm a professional trader</button>
</div>
</div>
<div class="heroCard">
<h3>Two ways to use HarvHub</h3>
<div class="mini"><span>Investor</span><b>Choose a trader</b></div>
<div class="mini"><span>Professional Trader</span><b>Provide a programme</b></div>
<div class="mini"><span>Investor account</span><b>Connect & monitor</b></div>
<div class="mini"><span>Trader account</span><b>Build & manage signals</b></div>
</div>
</div></header>

<section id="roles"><div class="wrap">
<div class="head"><span class="tag">Start here</span><h2>What are you joining HarvHub as?</h2><p>Choose the path that matches what you want to do. After verification, you will enter the area designed for your chosen path.</p></div>
<div class="roleGrid">
<div class="roleCard inv"><div class="roleIcon"><i class="fa-solid fa-wallet"></i></div><h3>I want to invest</h3><p>For people who have capital and want to connect an investment account to a professional trader rather than trade manually themselves.</p><div class="list"><div><i class="fa-solid fa-check"></i><span>Browse available trading programmes.</span></div><div><i class="fa-solid fa-check"></i><span>Compare each trader's analytics and programme requirements.</span></div><div><i class="fa-solid fa-check"></i><span>Choose a programme whose risk profile and terms fit your decision.</span></div><div><i class="fa-solid fa-check"></i><span>Connect your investment account and monitor its performance.</span></div></div><button class="btn primary" onclick="openRole('investor')">Continue as Investor</button></div>
<div class="roleCard dev"><div class="roleIcon"><i class="fa-solid fa-user-tie"></i></div><h3>I'm a professional trader</h3><p>For experienced forex and crypto traders who want to create programmes and provide signals that can be executed for participating investors.</p><div class="list"><div><i class="fa-solid fa-check"></i><span>Create and manage trading programmes.</span></div><div><i class="fa-solid fa-check"></i><span>Configure symbols, timeframes, visibility and programme requirements.</span></div><div><i class="fa-solid fa-check"></i><span>Review programme analytics and potential earnings.</span></div><div><i class="fa-solid fa-check"></i><span>Trading activity is handled through the programme, so there is no need to enter trades manually here.</span></div></div><button class="btn primary" onclick="openRole('developer')">Continue as Professional Trader</button></div>
</div></div></section>

<section id="investors" class="alt"><div class="wrap">
<div class="head"><span class="tag">For investors</span><h2>Your capital. Your programme choice.</h2><p>HarvHub is designed so an investor can review available professional trading programmes before deciding which trader to connect with.</p></div>
<div class="adGrid">
<div class="ad"><h3>Review the trader before connecting</h3><p>Different traders can operate with different strategies, analytics and revenue arrangements.</p><ul><li>Review programme performance analytics where available.</li><li>Review the programme's stated requirements and investment limits.</li><li>Review the trader's revenue profit-share terms.</li><li>Make your own decision about which programme you want to follow.</li></ul></div>
<div class="ad">
  <h3>Connect your investment account</h3>
  <p>After signing up, the investor workflow takes you into the investment application where your account can be configured for the selected trading arrangement. To invest on HarvHub, you must sign up with one of the available brokers so your investment account can be linked correctly.</p>
  <ul>
    <li>Complete investor account verification.</li>
    <li>Sign up with one of the available brokers.</li>
    <li>Your investment account must be registered under a VPS. You can either purchase a VPS inside HarvHub or request a space from an existing VPS owner.</li>
    <li>Connect the required investment account once your broker and VPS are in place.</li>
    <li>Review and follow HarvHub's terms and conditions for the duration of your investment.</li>
    <li>Monitor the resulting activity from your investor dashboard.</li>
  </ul>
</div>
</div></div></section>

<section id="traders"><div class="wrap">
<div class="head"><span class="tag">For professional traders</span><h2>Turn your trading profession into a revenue source.</h2><p>Professional traders can build programmes, define how they operate, review performance and make a programme available to investors when it is ready.</p></div>
<div class="featureGrid">
<div class="feature"><i class="fa-solid fa-people-group"></i><h3>Reach investors</h3><p>Available public programmes can be presented to investors looking for professional trading strategies.</p></div>
<div class="feature"><i class="fa-solid fa-chart-line"></i><h3>Show analytics</h3><p>Programme performance analytics give investors measurable information to review rather than relying only on promotional claims.</p></div>
<div class="feature"><i class="fa-solid fa-percent"></i><h3>Set your profit share</h3>
<p>Programme requirements can define the trader's profit percentage from each investor's profit.</p></div>
</div>
</div></section>

<section id="markets" class="markets-section">
  <div class="wrap">
    <div class="head">
      <span class="tag">Trading markets</span>
      <h2>Analyse markets with MT5.</h2>
      <p>Professional traders can work with MetaTrader 5 and a wide range of familiar markets and symbols.</p>
    </div>
    <div class="market-grid">
      <div class="market-card"><i class="fa-solid fa-coins"></i><div><strong>Gold</strong><span>XAUUSD</span></div></div>
      <div class="market-card"><i class="fa-solid fa-sterling-sign"></i><div><strong>Pounds</strong><span>GBPUSD</span></div></div>
      <div class="market-card"><i class="fa-solid fa-dollar-sign"></i><div><strong>US Dollar</strong><span>USD pairs</span></div></div>
      <div class="market-card"><i class="fa-brands fa-bitcoin"></i><div><strong>Bitcoin</strong><span>BTCUSD</span></div></div>
      <div class="market-card"><i class="fa-solid fa-chart-line"></i><div><strong>Many more</strong><span>Additional MT5 symbols</span></div></div>
    </div>
  </div>
</section>

<section id="how" class="alt"><div class="wrap">
<div class="head"><span class="tag">Simple routing</span><h2>One account, the right experience.</h2></div>
<div class="steps">
<div class="box"><div class="num">01</div><h3>Choose a role</h3><p>Choose whether you want to invest capital or provide professional trading.</p></div>
<div class="box"><div class="num">02</div><h3>Verify your account</h3><p>Sign up with your details and complete the verification process.</p></div>
<div class="box"><div class="num">03</div><h3>Enter your area</h3><p>Investors continue to the investment area. Professional traders continue to the trading area.</p></div>
<div class="box"><div class="num">04</div><h3>Continue your workflow</h3><p>Investors choose programmes; professional traders provides and manage their programmes.</p></div>
</div></div></section>

<div class="cta"><h2>Choose your path on HarvHub.</h2><p>Choose the path that fits what you want to do.</p><button class="btn primary" onclick="openRole('investor')">Get started</button></div>
<footer>© <?= date('Y') ?> HarvHub. Trading involves substantial risk. Review programme information and make your own investment decisions.</footer>

<!-- ROLE MODAL -->
<div class="modal" id="roleModal"><div class="mc">
<h3>Choose your role</h3><p class="sub">Choose how you want to use HarvHub.</p>
<div class="roleChoice">
<label><input type="radio" name="roleSelect" value="investor" checked> Investor</label>
<label><input type="radio" name="roleSelect" value="developer"> Professional Trader</label>
</div>
<div class="notice" id="roleExplain">Investors review professional trading programmes and use the investor area.</div>
<div style="display:flex;gap:10px"><button class="btn ghost" style="flex:1" onclick="closeM('roleModal')">Cancel</button><button class="btn primary" style="flex:1" onclick="continueRole()">Continue</button></div>
</div></div>

<!-- LOGIN — no role selector; the target shell is decided by harvhub.last_app -->
<div class="modal" id="loginModal"><div class="mc">
<h3>Sign in</h3><p class="sub">Enter your account credentials to continue.</p>
<div class="err <?= $loginError ? 'show':'' ?>" id="loginErr"><?= esc($loginError) ?></div>
<form method="post">
<input type="hidden" name="auth_action" value="login">
<div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required></div>
<div class="field"><label>Password</label><input type="password" name="password" autocomplete="current-password" required></div>
<button class="btn primary" style="width:100%">Sign in</button>
</form>
<div class="switch">Need an account? <a onclick="closeM('loginModal');openAuth('signup')">Create one</a></div>
</div></div>

<!-- SIGNUP — keeps the role selector -->
<div class="modal" id="signupModal"><div class="mc">
<h3>Create your HarvHub account</h3><p class="sub">Registration is completed with email verification before your account is opened.</p>
<div class="err <?= $signupError ? 'show':'' ?>" id="signupErr"><?= esc($signupError) ?></div>
<form method="post">
<input type="hidden" name="auth_action" value="signup">

<div class="roleChoice"><label><input type="radio" name="role" value="investor" checked> Investor</label><label><input type="radio" name="role" value="developer"> Professional Trader</label></div>

<div class="field"><label>First name</label><input type="text" name="first_name" maxlength="80" autocomplete="given-name" required></div>

<div class="field"><label>Last name</label><input type="text" name="last_name" maxlength="80" autocomplete="family-name" required></div>

<div class="field"><label>Username</label><input type="text" name="username" maxlength="80" autocomplete="username" pattern="[A-Za-z0-9._\-]{3,80}" title="3–80 characters; letters, numbers, dots, underscores and hyphens only" required></div>

<div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required></div>

<div class="field"><label>Password</label><input type="password" name="password" minlength="8" autocomplete="new-password" required></div>

<div class="field"><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>

<label class="check"><input type="checkbox" name="terms" required> <span>I understand that HarvHub is not responsible for my decision, and that proceeding involves trading risk entirely under my own awareness and decision.</span></label>

<button class="btn primary" style="width:100%;">Create account &amp; verify email</button>
</form>
<button class="btn ghost" style="width:100%; margin-top: 10px;" onclick="closeM('signupModal')">Cancel</button>
<div class="switch">Already have an account? <a onclick="closeM('signupModal');openAuth('login')">Sign in</a></div>

</div></div>

<script>
    function $(id){ return document.getElementById(id); }
    let savedScrollY = 0;
    let modalLockCount = 0;

    function lockPageScroll(){
        if (modalLockCount === 0) {
            savedScrollY = window.scrollY || window.pageYOffset || 0;
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');
            document.body.style.position = 'fixed';
            document.body.style.top = '-' + savedScrollY + 'px';
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            document.body.style.overflow = 'hidden';
        }
        modalLockCount++;
    }

    function unlockPageScroll(){
        modalLockCount = Math.max(0, modalLockCount - 1);
        if (modalLockCount === 0) {
            document.documentElement.classList.remove('modal-open');
            document.body.classList.remove('modal-open');
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            document.body.style.overflow = '';
            window.scrollTo(0, savedScrollY);
        }
    }

    function openM(id){
        const modal = $(id);
        if (!modal) return;
        if (!modal.classList.contains('open')) {
            modal.classList.add('open');
            lockPageScroll();
        }
    }

    function closeM(id){
        const modal = $(id);
        if (!modal) return;
        if (modal.classList.contains('open')) {
            modal.classList.remove('open');
            unlockPageScroll();
        }
    }

    function closeAllModals(){
        document.querySelectorAll('.modal.open').forEach(function(modal){
            modal.classList.remove('open');
        });
        modalLockCount = 1;
        unlockPageScroll();
    }

    function openAuth(type){
        openM(type === 'login' ? 'loginModal' : 'signupModal');
    }

    function openRole(role){
        document.querySelectorAll('input[name="roleSelect"]').forEach(function(r){
            r.checked = (r.value === role);
        });
        updateRoleExplain();
        openM('roleModal');
    }

    function updateRoleExplain(){
        const r = document.querySelector('input[name="roleSelect"]:checked');
        const el = $('roleExplain');
        if (!r || !el) return;

        el.textContent = r.value === 'investor'
            ? 'Review professional trading programmes, choose a trader and continue to the investment area.'
            : 'Create and manage professional trading programmes and continue to the trading area.';
    }

    function continueRole(){
        const r = document.querySelector('input[name="roleSelect"]:checked');
        if (!r) return;

        closeM('roleModal');
        openAuth('signup');

        const target = document.querySelector('#signupModal input[name="role"][value="' + r.value + '"]');
        if (target) target.checked = true;
    }

    document.querySelectorAll('input[name="roleSelect"]').forEach(function(r){
        r.addEventListener('change', updateRoleExplain);
    });

    document.querySelectorAll('.modal').forEach(function(modal){
        modal.addEventListener('click', function(e){
            if (e.target === modal) closeM(modal.id);
        });
    });

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.open');
            if (openModal) closeM(openModal.id);
        }
    });

    window.addEventListener('pageshow', function(){
        const openModal = document.querySelector('.modal.open');
        if (!openModal) {
            document.documentElement.classList.remove('modal-open');
            document.body.classList.remove('modal-open');
        }
    });

    <?php if ($loginError): ?>
    openM('loginModal');
    <?php elseif ($signupError): ?>
    openM('signupModal');
    <?php endif; ?>
</script>
</body>
</html>