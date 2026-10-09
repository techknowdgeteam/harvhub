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

function redirectByLastApp($lastApp) {
    $lastApp = trim((string)$lastApp);
    switch ($lastApp) {
        case 'traderapp':
        case 'trader_app':
            header("Location: traderapp.php");
            exit;
        case 'investorapp':
        default:
            header("Location: investorapp.php");
            exit;
    }
}

function roleFromLastApp($lastApp) {
    $lastApp = trim((string)$lastApp);
    return ($lastApp === 'traderapp' || $lastApp === 'trader_app') ? 'developer' : 'investor';
}

function resolveInvestorSubAccount($pdo, $tableName, $email, $lastAccount) {
    $email = strtolower(trim($email));
    $lastAccount = trim((string)$lastAccount);

    if ($lastAccount !== '') {
        if (preg_match('/^SA(\d+)$/', $lastAccount, $m)) {
            $subId = (int)$m[1];
            if ($subId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
                $q->execute([$subId, $email]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }
        if (preg_match('/^s(\d+)$/', $lastAccount, $m)) {
            $subId = (int)$m[1];
            if ($subId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
                $q->execute([$subId, $email]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row) return $row;
            }
        }
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

    $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $q->execute([$email]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

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

                redirectByLastApp($lastApp);
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

        redirectByLastApp($lastApp);
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
<title>HarvHub</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  :root{
    --bg:#02030a;
    --bg2:#050816;
    --card:rgba(11,15,31,.82);
    --card-strong:#0b1020;
    --text:#f5f7ff;
    --muted:#a8b1c7;
    --accent:#16c784;
    --accent2:#0ea66d;
    --blue:#4d7cff;
    --soft:rgba(22,199,132,.11);
    --soft-blue:rgba(77,124,255,.12);
    --border:rgba(255,255,255,.10);
    --border-strong:rgba(255,255,255,.17);
    --shadow:0 22px 70px rgba(0,0,0,.42);
    --r:20px;
  }

  *{box-sizing:border-box;margin:0;padding:0}
  html{
    scroll-behavior:smooth;
    scrollbar-width:none;
    -ms-overflow-style:none;
    background:#02030a;
  }
  html::-webkit-scrollbar,
  body::-webkit-scrollbar,
  .modal::-webkit-scrollbar,
  .mc::-webkit-scrollbar{display:none;width:0;height:0}

  body{
    min-height:100vh;
    font-family:Segoe UI,system-ui,-apple-system,BlinkMacSystemFont,Roboto,sans-serif;
    background:
      radial-gradient(circle at 15% 20%, rgba(63,28,120,.28), transparent 32%),
      radial-gradient(circle at 85% 15%, rgba(0,72,120,.22), transparent 30%),
      radial-gradient(circle at 70% 80%, rgba(0,118,82,.15), transparent 30%),
      #02030a;
    color:var(--text);
    line-height:1.6;
    overflow-x:hidden;
    scrollbar-width:none;
    -ms-overflow-style:none;
    position:relative;
  }
  body::-webkit-scrollbar{display:none}

  body::before{
    content:"";
    position:fixed;
    inset:0;
    z-index:-2;
    pointer-events:none;
    background:
      radial-gradient(circle at 20% 80%, #1a0033 0%, transparent 50%),
      radial-gradient(circle at 80% 20%, #000033 0%, transparent 50%),
      url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'><circle cx='10' cy='10' r='1' fill='white'/><circle cx='30' cy='70' r='1.5' fill='white'/><circle cx='70' cy='30' r='1' fill='white'/><circle cx='90' cy='80' r='1.2' fill='white'/><circle cx='50' cy='50' r='1.8' fill='white'/></svg>") repeat;
    background-size:cover,cover,120px 120px;
    opacity:.48;
  }

  body::after{
    content:"";
    position:fixed;
    inset:0;
    z-index:-1;
    pointer-events:none;
    background:
      radial-gradient(circle at 50% 0%, rgba(255,255,255,.04), transparent 36%),
      linear-gradient(180deg,rgba(2,3,10,.12),rgba(2,3,10,.76));
  }

  html.modal-open,
  body.modal-open{
    overflow:hidden !important;
    scrollbar-width:none !important;
    -ms-overflow-style:none !important;
  }
  body.modal-open::-webkit-scrollbar{display:none !important}

  a{text-decoration:none;color:inherit}
  button,input,select{font:inherit}
  button{touch-action:manipulation}
  input,select{
    font-size:16px !important;
    touch-action:manipulation;
  }

  .wrap{max-width:1180px;margin:auto;padding:0 20px}

  .nav{
    position:sticky;
    top:0;
    z-index:50;
    background:rgba(2,3,10,.78);
    backdrop-filter:blur(16px);
    border-bottom:1px solid var(--border);
  }
  .navin{height:68px;display:flex;align-items:center;justify-content:space-between}
  .logo{display:flex;align-items:center;gap:10px;font-weight:900}
  .logo i{
    display:grid;place-items:center;width:36px;height:36px;border-radius:11px;
    background:var(--accent);color:#03110b;font-style:normal
  }
  .navlinks{display:flex;gap:25px;color:var(--muted);font-size:.9rem;font-weight:700}
  .navlinks a:hover{color:var(--accent)}
  .actions{display:flex;gap:9px}

  .btn{
    border:0;border-radius:12px;padding:11px 18px;font-weight:800;cursor:pointer;
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    transition:transform .18s,opacity .18s,*background* .18s,border-color .18s;
  }
  .btn:hover{transform:translateY(-1px)}
  .primary{background:var(--accent);color:#03110b}
  .primary:hover{background:#22d998}
  .ghost{background:rgba(255,255,255,.035);border:1px solid var(--border-strong);color:var(--text)}
  .ghost:hover{background:rgba(255,255,255,.07)}

  .hero{
    background:
      radial-gradient(circle at 75% 30%,rgba(22,199,132,.18),transparent 35%),
      radial-gradient(circle at 20% 70%,rgba(77,124,255,.14),transparent 32%);
    color:var(--text);
    padding:82px 0 90px;
    overflow:hidden;
  }
  .heroGrid{display:grid;grid-template-columns:1.15fr .85fr;gap:45px;align-items:center}
  .badge{
    display:inline-block;background:rgba(255,255,255,.07);border:1px solid var(--border-strong);
    padding:7px 14px;border-radius:99px;font-size:.76rem;font-weight:900;letter-spacing:.5px;margin-bottom:18px
  }
  h1{font-size:clamp(2.3rem,5vw,4rem);line-height:1.05;letter-spacing:-1.8px;margin-bottom:18px}
  .hero p{max-width:650px;color:#d9deea;font-size:1.05rem;margin-bottom:28px}
  .heroBtns{display:flex;gap:12px;flex-wrap:wrap}
  /* Alternating role-button prompt: each button gets its own brief spotlight. */
  .role-prompt-stage{position:relative;overflow:visible;isolation:isolate;}
  .role-action-wrap{position:relative;display:inline-flex;align-items:center;overflow:visible;}
  .role-prompt-btn{position:relative;z-index:1;transform-origin:center;}
  .role-prompt-btn.is-prompt-active{z-index:3;animation:roleButtonSpotlight 1s cubic-bezier(.16,1,.3,1) both;}
  .role-float-hint{
    position:absolute;z-index:5;right:-8px;top:-17px;display:inline-flex;align-items:center;justify-content:center;
    min-height:27px;padding:4px 10px;border:1px solid rgba(255,255,255,.25);border-radius:999px;
    background:rgba(5,10,23,.96);color:#fff;font-size:.72rem;font-weight:900;letter-spacing:.2px;
    box-shadow:0 8px 24px rgba(0,0,0,.28),0 0 20px rgba(22,199,132,.18);
    opacity:0;visibility:hidden;pointer-events:none;white-space:nowrap;transform:translate3d(0,8px,0) scale(.82);
  }
  .role-action-wrap[data-role-prompt="developer"] .role-float-hint{box-shadow:0 8px 24px rgba(0,0,0,.28),0 0 20px rgba(77,124,255,.22);}
  .role-float-hint.is-active{visibility:visible;animation:roleHintFloat 1s cubic-bezier(.16,1,.3,1) both;}
  @keyframes roleButtonSpotlight{
    0%{transform:translateY(0) scale(1);box-shadow:0 0 0 rgba(22,199,132,0);}
    28%{transform:translateY(-5px) scale(1.035);box-shadow:0 12px 32px rgba(22,199,132,.24),0 0 0 4px rgba(22,199,132,.09);}
    72%{transform:translateY(-4px) scale(1.025);box-shadow:0 10px 28px rgba(22,199,132,.18);}
    100%{transform:translateY(0) scale(1);box-shadow:0 0 0 rgba(22,199,132,0);}
  }
  .role-action-wrap[data-role-prompt="developer"] .role-prompt-btn.is-prompt-active{animation-name:traderButtonSpotlight;}
  @keyframes traderButtonSpotlight{
    0%{transform:translateY(0) scale(1);box-shadow:0 0 0 rgba(77,124,255,0);}
    28%{transform:translateY(-5px) scale(1.035);box-shadow:0 12px 32px rgba(77,124,255,.25),0 0 0 4px rgba(77,124,255,.10);}
    72%{transform:translateY(-4px) scale(1.025);box-shadow:0 10px 28px rgba(77,124,255,.18);}
    100%{transform:translateY(0) scale(1);box-shadow:0 0 0 rgba(77,124,255,0);}
  }
  @keyframes roleHintFloat{
    0%{opacity:0;transform:translate3d(0,8px,0) scale(.82);}
    22%{opacity:1;transform:translate3d(0,-2px,0) scale(1.04);}
    72%{opacity:1;transform:translate3d(0,-5px,0) scale(1);}
    100%{opacity:0;transform:translate3d(0,-12px,0) scale(.94);}
  }
  @media(max-width:520px){
    .heroBtns{gap:15px 10px;}
    .role-action-wrap{max-width:100%;}
    .role-prompt-btn{max-width:100%;white-space:normal;text-align:center;}
    .role-float-hint{right:-5px;top:-15px;}
  }
  @media(prefers-reduced-motion:reduce){
    .role-prompt-btn.is-prompt-active,.role-float-hint.is-active{animation:none!important;}
    .role-prompt-btn.is-prompt-active{outline:2px solid var(--accent);outline-offset:3px;}
    .role-float-hint.is-active{opacity:1;visibility:visible;transform:none;}
  }

  .hero .primary{background:#fff;color:#07100c}
  .hero .ghost{color:#fff;border-color:rgba(255,255,255,.25)}
  .heroCard{
    background:rgba(255,255,255,.055);border:1px solid var(--border-strong);padding:25px;
    border-radius:20px;backdrop-filter:blur(10px);box-shadow:var(--shadow)
  }
  .heroCard h3{margin-bottom:15px}
  .mini{
    display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,.12);
    font-size:.9rem;color:#d7dceb
  }
  .mini b{color:#fff}.mini:last-child{border:0}

  section{padding:78px 0}
  .alt{background:rgba(255,255,255,.025);border-top:1px solid rgba(255,255,255,.035);border-bottom:1px solid rgba(255,255,255,.035)}
  .head{text-align:center;max-width:760px;margin:0 auto 42px}
  .tag{color:var(--accent);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:1px}
  .head h2{font-size:2rem;line-height:1.15;margin:7px 0 10px}
  .head p{color:var(--muted)}

  .roleGrid{display:grid;grid-template-columns:1fr 1fr;gap:22px}
  .roleCard{
    background:var(--card);border:1px solid var(--border);border-radius:22px;padding:30px;
    box-shadow:var(--shadow);position:relative;overflow:hidden;backdrop-filter:blur(8px)
  }
  .roleCard.dev{border-top:4px solid var(--accent)}
  .roleCard.inv{border-top:4px solid var(--blue)}
  .roleIcon{
    width:50px;height:50px;border-radius:15px;background:var(--soft);display:grid;
    place-items:center;color:var(--accent);font-size:1.25rem;margin-bottom:17px
  }
  .inv .roleIcon{background:var(--soft-blue);color:var(--blue)}
  .roleCard h3{font-size:1.35rem;margin-bottom:8px}
  .roleCard p{color:var(--muted);font-size:.92rem}
  .list{margin:18px 0;display:grid;gap:10px}
  .list div{display:flex;gap:9px;font-size:.88rem;color:#d9deea}
  .list i{color:var(--accent);margin-top:4px}

  .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
  .box{
    background:var(--card);border:1px solid var(--border);border-radius:18px;padding:23px;
    box-shadow:0 12px 40px rgba(0,0,0,.22)
  }
  .num{color:var(--accent);font-weight:900;font-size:.8rem}
  .box h3{margin:7px 0}.box p{color:var(--muted);font-size:.86rem}

  .featureGrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
  .feature{
    background:var(--card);border:1px solid var(--border);border-radius:18px;padding:24px;
    box-shadow:0 12px 40px rgba(0,0,0,.18)
  }
  .feature i{color:var(--accent);font-size:1.25rem;margin-bottom:12px}
  .feature p{color:var(--muted);font-size:.88rem}

  .markets-section{padding-top:30px}
  .market-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px}
  .market-card{
    background:var(--card);border:1px solid var(--border);border-radius:17px;padding:20px;
    display:flex;align-items:center;gap:13px;min-height:105px
  }
  .market-card i{color:var(--accent);font-size:1.25rem}
  .market-card strong{display:block;font-size:.92rem}.market-card span{display:block;color:var(--muted);font-size:.76rem;margin-top:2px}

  .cta{
    background:
      radial-gradient(circle at 50% 0%,rgba(22,199,132,.16),transparent 48%),
      #06100d;
    color:white;text-align:center;padding:70px 20px;border-top:1px solid var(--border)
  }
  .cta h2{font-size:2.2rem}.cta p{color:#b9c2d4;max-width:650px;margin:10px auto 23px}
  footer{padding:25px;text-align:center;color:var(--muted);font-size:.78rem}

  .modal{
    position:fixed;inset:0;background:rgba(0,0,0,.72);display:none;align-items:center;justify-content:center;
    padding:20px;z-index:100;backdrop-filter:blur(8px);overscroll-behavior:contain
  }
  .modal.open{display:flex}
  .mc{
    background:#080d1b;color:var(--text);width:min(520px,100%);max-height:90vh;overflow-y:auto;
    border:1px solid var(--border-strong);border-radius:22px;padding:28px;
    box-shadow:0 25px 80px rgba(0,0,0,.65);overscroll-behavior:contain;
    scrollbar-width:none;-ms-overflow-style:none
  }
  .mc::-webkit-scrollbar{display:none}
  .mc h3{font-size:1.35rem;margin-bottom:5px}
  .sub{color:var(--muted);font-size:.87rem;margin-bottom:18px}

  .field{margin-bottom:14px}
  .field label{
    display:block;font-size:.73rem;text-transform:uppercase;letter-spacing:.5px;
    font-weight:800;color:var(--muted);margin-bottom:6px
  }
  .field input,.field select{
    width:100%;padding:12px 13px;border:1px solid var(--border-strong);border-radius:12px;
    background:#050916;color:var(--text);font:inherit;outline:none
  }
  .field input:focus,.field select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(22,199,132,.08)}
  .roleChoice{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
  .roleChoice label{
    border:1px solid var(--border);border-radius:14px;padding:13px;cursor:pointer;background:rgba(255,255,255,.025)
  }
  .roleChoice input{margin-right:6px}
  .roleChoice label:has(input:checked){border-color:var(--accent);background:var(--soft)}
  .err{
    display:none;background:rgba(224,75,75,.11);color:#ff8d8d;
    padding:10px 12px;border-radius:10px;font-size:.84rem;margin-bottom:14px
  }
  .err.show{display:block}
  .notice{background:var(--soft);padding:12px;border-radius:12px;color:#dfe9e4;font-size:.82rem;margin-bottom:15px}
  .check{font-size:.8rem;color:var(--muted);display:flex;gap:8px;margin:10px 0 18px}
  .switch{text-align:center;color:var(--muted);font-size:.83rem;margin-top:15px}
  .switch a{color:var(--accent);font-weight:800;cursor:pointer}

  .adGrid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
  .ad{
    background:var(--card);border:1px solid var(--border);border-radius:20px;padding:27px;
    box-shadow:var(--shadow)
  }
  .ad h3{font-size:1.3rem;margin-bottom:8px}.ad p{color:var(--muted);font-size:.9rem}
  .ad ul{padding-left:20px;color:var(--muted);font-size:.86rem;margin:15px 0}
  .ad li{margin:7px 0}

  /* ---------------------------------------------------------------
     HARVHUB MOTION SYSTEM — cinematic, Apple-inspired scroll reveals
     --------------------------------------------------------------- */
  :root {
    --ease-out-expo: cubic-bezier(.16, 1, .3, 1);
    --ease-smooth: cubic-bezier(.22, 1, .36, 1);
  }
  body { isolation:isolate; }
  .nav { transition:background .45s ease, border-color .45s ease, box-shadow .45s ease; }
  .nav.scrolled { background:rgba(3,6,15,.9); box-shadow:0 12px 40px rgba(0,0,0,.22); border-color:rgba(255,255,255,.13); }
  .logo i { box-shadow:0 0 0 0 rgba(22,199,132,.38); animation:logoPulse 3.6s ease-in-out infinite; }
  .hero { position:relative; isolation:isolate; }
  .hero::before { content:""; position:absolute; width:540px; height:540px; right:-140px; top:-180px; border-radius:50%; background:radial-gradient(circle,rgba(22,199,132,.14),rgba(77,124,255,.055) 38%,transparent 70%); filter:blur(12px); z-index:-1; animation:orbDrift 13s ease-in-out infinite alternate; pointer-events:none; }
  .hero::after { content:""; position:absolute; inset:0; pointer-events:none; opacity:.22; z-index:-1; background:linear-gradient(115deg,transparent 25%,rgba(255,255,255,.035) 48%,transparent 70%); background-size:220% 100%; animation:ambientSweep 12s linear infinite; }
  .hero h1 { animation:heroRise .95s var(--ease-out-expo) both; }
  .hero p { animation:heroRise .95s .12s var(--ease-out-expo) both; }
  .hero .badge { animation:heroRise .8s .04s var(--ease-out-expo) both; }
  .heroBtns { animation:heroRise .95s .2s var(--ease-out-expo) both; }
  .heroCard { position:relative; overflow:hidden; transform:translateZ(0); animation:cardArrive 1.1s .12s var(--ease-out-expo) both, cardFloat 7s 1.5s ease-in-out infinite; transition:border-color .45s ease, background .45s ease, box-shadow .45s ease, transform .45s var(--ease-smooth); }
  .heroCard::before,.ad::before,.roleCard::before,.feature::before,.box::before { content:""; position:absolute; inset:0; pointer-events:none; opacity:0; background:linear-gradient(115deg,transparent 20%,rgba(255,255,255,.075) 48%,transparent 72%); background-size:220% 100%; transition:opacity .45s ease; }
  .heroCard:hover,.ad:hover,.roleCard:hover,.feature:hover,.box:hover { border-color:rgba(255,255,255,.2); box-shadow:0 28px 80px rgba(0,0,0,.34),0 0 35px rgba(22,199,132,.055); }
  .heroCard:hover::before,.ad:hover::before,.roleCard:hover::before,.feature:hover::before,.box:hover::before { opacity:1; animation:ambientSweep 1.3s ease both; }
  .mini { transition:padding .35s var(--ease-smooth), color .35s ease; }
  .heroCard:hover .mini { padding-left:5px; padding-right:5px; }
  section { position:relative; }
  .head .tag { display:inline-flex; align-items:center; gap:8px; }
  .head .tag::before { content:""; width:18px; height:1px; background:currentColor; opacity:.8; transition:width .45s var(--ease-out-expo); }
  .head.is-visible .tag::before { width:30px; }
  .head h2 { letter-spacing:-.7px; }
  .adGrid { perspective:1200px; }
  .ad { position:relative; isolation:isolate; overflow:hidden; transform:translateZ(0); background:linear-gradient(145deg,rgba(18,25,47,.88),rgba(7,11,24,.88)); transition:transform .65s var(--ease-out-expo), border-color .4s ease, box-shadow .5s ease, background .5s ease; }
  .ad:nth-child(1) { --ad-glow:rgba(22,199,132,.15); }
  .ad:nth-child(2) { --ad-glow:rgba(77,124,255,.17); }
  .ad::after { content:""; position:absolute; width:230px; height:230px; top:-115px; right:-90px; border-radius:50%; background:radial-gradient(circle,var(--ad-glow,rgba(22,199,132,.13)),transparent 70%); z-index:-1; transition:transform .8s var(--ease-out-expo),opacity .5s ease; opacity:.75; }
  .ad:hover { transform:translateY(-8px) scale(1.012); background:linear-gradient(145deg,rgba(22,30,55,.96),rgba(7,11,24,.96)); }
  .ad:hover::after { transform:translate(-24px,28px) scale(1.25); opacity:1; }
  .ad h3 { position:relative; letter-spacing:-.35px; }
  .ad h3::after { content:""; display:block; width:34px; height:2px; margin-top:13px; border-radius:4px; background:linear-gradient(90deg,var(--accent),rgba(22,199,132,0)); transition:width .55s var(--ease-out-expo); }
  .ad:hover h3::after { width:76px; }
  .ad ul { list-style:none; padding:0; }
  .ad li { position:relative; padding-left:23px; transition:transform .35s var(--ease-out-expo),color .35s ease; }
  .ad li::before { content:""; position:absolute; left:0; top:.58em; width:8px; height:8px; border-radius:50%; border:1px solid var(--accent); box-shadow:0 0 12px rgba(22,199,132,.18); transition:background .3s ease,box-shadow .3s ease,transform .3s ease; }
  .ad li:hover { transform:translateX(4px); color:#edf5f2; }
  .ad li:hover::before { background:var(--accent); box-shadow:0 0 16px rgba(22,199,132,.45); transform:scale(1.08); }
  .roleCard,.feature,.box,.market-card { position:relative; overflow:hidden; transition:transform .65s var(--ease-out-expo),border-color .4s ease,box-shadow .5s ease,background .5s ease; }
  .roleCard:hover,.feature:hover,.box:hover { transform:translateY(-6px); }
  .roleIcon { transition:transform .5s var(--ease-out-expo),border-radius .5s ease,box-shadow .5s ease; }
  .roleCard:hover .roleIcon { transform:translateY(-3px) rotate(-4deg); border-radius:18px; box-shadow:0 10px 28px rgba(22,199,132,.12); }
  .inv:hover .roleIcon { box-shadow:0 10px 28px rgba(77,124,255,.14); }
  .market-card { transition:transform .45s var(--ease-out-expo),border-color .35s ease,background .35s ease; }
  .market-card:hover { transform:translateY(-5px); border-color:rgba(22,199,132,.28); background:rgba(15,26,35,.92); }
  .market-card i { transition:transform .45s var(--ease-out-expo); }
  .market-card:hover i { transform:scale(1.16) rotate(-5deg); }
  .btn { position:relative; overflow:hidden; isolation:isolate; transition:transform .35s var(--ease-out-expo),box-shadow .35s ease,background .25s ease,border-color .25s ease; }
  .btn::after { content:""; position:absolute; inset:-1px; z-index:-1; transform:translateX(-130%) skewX(-18deg); background:linear-gradient(90deg,transparent,rgba(255,255,255,.23),transparent); transition:transform .7s var(--ease-out-expo); }
  .btn:hover::after { transform:translateX(130%) skewX(-18deg); }
  .btn:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,0,0,.18); }
  .btn:active { transform:translateY(0) scale(.985); }
  .navlinks a { position:relative; transition:color .25s ease; }
  .navlinks a::after { content:""; position:absolute; left:0; right:100%; bottom:-8px; height:2px; border-radius:3px; background:var(--accent); transition:right .35s var(--ease-out-expo); }
  .navlinks a:hover::after { right:0; }
  .cta { position:relative; overflow:hidden; }
  .cta::before { content:""; position:absolute; width:420px; height:260px; left:50%; top:-190px; transform:translateX(-50%); border-radius:50%; background:rgba(22,199,132,.14); filter:blur(55px); pointer-events:none; animation:ctaBreath 6s ease-in-out infinite alternate; }
  .cta h2,.cta p,.cta button { position:relative; }
  .reveal { opacity:0; transform:translate3d(0,38px,0) scale(.985); filter:blur(5px); transition:opacity .85s var(--ease-out-expo),transform .95s var(--ease-out-expo),filter .85s ease; transition-delay:var(--reveal-delay,0ms); will-change:opacity,transform; }
  .reveal.from-left { transform:translate3d(-42px,12px,0) scale(.99); }
  .reveal.from-right { transform:translate3d(42px,12px,0) scale(.99); }
  .reveal.from-scale { transform:translate3d(0,22px,0) scale(.94); }
  .reveal.is-visible { opacity:1; transform:translate3d(0,0,0) scale(1); filter:blur(0); }
  .modal { opacity:0; transition:opacity .25s ease,backdrop-filter .3s ease; }
  .modal.open { opacity:1; }
  .modal .mc { transform:translateY(22px) scale(.975); opacity:0; transition:transform .45s var(--ease-out-expo),opacity .3s ease; }
  .modal.open .mc { transform:translateY(0) scale(1); opacity:1; }
  @keyframes heroRise { from { opacity:0; transform:translateY(24px); } to { opacity:1; transform:translateY(0); } }
  @keyframes cardArrive { from { opacity:0; transform:translateY(30px) rotateX(5deg) scale(.97); } to { opacity:1; transform:translateY(0) rotateX(0) scale(1); } }
  @keyframes cardFloat { 0%,100% { translate:0 0; } 50% { translate:0 -7px; } }
  @keyframes orbDrift { from { transform:translate3d(0,0,0) scale(1); } to { transform:translate3d(-45px,35px,0) scale(1.12); } }
  @keyframes ambientSweep { from { background-position:200% 0; } to { background-position:-200% 0; } }
  @keyframes ctaBreath { from { opacity:.55; transform:translateX(-50%) scale(.9); } to { opacity:1; transform:translateX(-50%) scale(1.15); } }
  @keyframes logoPulse { 0%,100% { box-shadow:0 0 0 0 rgba(22,199,132,0); } 50% { box-shadow:0 0 0 7px rgba(22,199,132,.06); } }
  @media (prefers-reduced-motion:reduce) {
    *,*::before,*::after { scroll-behavior:auto !important; animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; }
    .reveal { opacity:1; transform:none; filter:none; }
  }

  @media(max-width:1000px){
    .market-grid{grid-template-columns:repeat(3,1fr)}
  }
  @media(max-width:850px){
    .heroGrid,.roleGrid,.adGrid{grid-template-columns:1fr}
    .steps{grid-template-columns:1fr 1fr}
    .featureGrid{grid-template-columns:1fr}
    .navlinks{display:none}
  }
  @media(max-width:600px){
    .market-grid{grid-template-columns:1fr 1fr}
  }
  @media(max-width:520px){
    .steps{grid-template-columns:1fr}
    .actions .btn{padding:9px 11px;font-size:.8rem}
    .hero{padding:60px 0}
    .mc{padding:22px;max-height:88vh}
    .roleChoice{grid-template-columns:1fr}
    .market-grid{grid-template-columns:1fr}
  }

  /* Prevent accidental page movement while a modal is open. */
  .modal.open \~ *{pointer-events:auto}
</style>
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
<div class="heroBtns role-prompt-stage" id="heroRolePromptStage" aria-label="Choose your HarvHub role">
<div class="role-action-wrap" data-role-prompt="investor">
<button class="btn primary role-prompt-btn" type="button" onclick="openRole('investor')"><i class="fa-solid fa-wallet"></i> I want to invest</button>
<span class="role-float-hint" aria-hidden="true">Invest?</span>
</div>
<div class="role-action-wrap" data-role-prompt="developer">
<button class="btn ghost role-prompt-btn" type="button" onclick="openRole('developer')"><i class="fa-solid fa-user-tie"></i> I'm a professional trader</button>
<span class="role-float-hint" aria-hidden="true">Trade?</span>
</div>
</div>
</div>
<div class="heroCard">
<h3>Two ways to use HarvHub</h3>
<div class="mini"><span>Investor</span><b>Invest in a trader's programme</b></div>
<div class="mini"><span>Professional Trader</span><b>Build and Provide a programme</b></div>
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

<!-- ROLE MODAL (only shown if user clicks signup without a reference button) -->
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

<!-- SIGNUP — role selector hidden when opened via reference button -->
<div class="modal" id="signupModal"><div class="mc">
<h3>Create your account</h3>
<div class="err <?= $signupError ? 'show':'' ?>" id="signupErr"><?= esc($signupError) ?></div>
<form method="post">
<input type="hidden" name="auth_action" value="signup">

<div class="roleChoice" id="signupRoleChoice"><label><input type="radio" name="role" value="investor" checked> Investor</label><label><input type="radio" name="role" value="developer"> Professional Trader</label></div>

<div class="field"><label>First name</label><input type="text" name="first_name" maxlength="80" autocomplete="given-name" required></div>

<div class="field"><label>Last name</label><input type="text" name="last_name" maxlength="80" autocomplete="family-name" required></div>

<div class="field"><label>Username</label><input type="text" name="username" maxlength="80" autocomplete="username" pattern="[A-Za-z0-9._\-]{3,80}" title="3–80 characters; letters, numbers, dots, underscores and hyphens only" required></div>

<div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required></div>

<div class="field"><label>Password</label><input type="password" name="password" minlength="8" autocomplete="new-password" required></div>

<div class="field"><label>Confirm password</label><input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>

<label class="check"><input type="checkbox" name="terms" required> <span>I confirm that I am making my own choice to join HarvHub, and I understand that any decisions I make here are my own responsibility.</span></label>

<button class="btn primary" style="width:100%;">Create account</button>
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
        // When opening signup directly (no reference button), show the role choice
        if (type === 'signup') {
            const roleChoice = $('signupRoleChoice');
            if (roleChoice) roleChoice.style.display = '';
        }
        openM(type === 'login' ? 'loginModal' : 'signupModal');
    }

    /**
     * Open the signup modal directly with a pre-selected role and hide the role choice.
     * Used when a reference button (investor / professional trader) is clicked.
     */
    function openSignupWithRole(role){
        // Set the radio button in the signup modal
        const target = document.querySelector('#signupModal input[name="role"][value="' + role + '"]');
        if (target) target.checked = true;

        // Hide the role choice section
        const roleChoice = $('signupRoleChoice');
        if (roleChoice) roleChoice.style.display = 'none';

        // Open the signup modal directly
        openM('signupModal');
    }

    /**
     * Reference buttons call this. Go straight to signup with the role pre-selected,
     * and hide the role choice inputs.
     */
    function openRole(role){
        openSignupWithRole(role);
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
        openSignupWithRole(r.value);
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

    // Replaying scroll reveals: animate every time an element enters the viewport,
    // and reset it when it leaves so scrolling down AND back up retriggers the effect.
    (function initHarvHubMotion(){
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const nav = document.querySelector('.nav');
      const revealTargets = document.querySelectorAll(
        '.head, .roleCard, .ad, .feature, .box, .market-card, .heroCard, .cta h2, .cta p, .cta button, footer'
      );

      revealTargets.forEach(function(el, index){
        el.classList.add('reveal');
        if (el.classList.contains('ad') && index % 2 === 0) el.classList.add('from-left');
        else if (el.classList.contains('ad')) el.classList.add('from-right');
        else if (el.classList.contains('market-card')) el.classList.add('from-scale');
        el.style.setProperty('--reveal-delay', (el.classList.contains('market-card') ? (index % 5) * 75 : (index % 3) * 85) + 'ms');
      });

      if (reduceMotion || !('IntersectionObserver' in window)) {
        // Respect accessibility settings and ensure the page remains readable.
        revealTargets.forEach(function(el){el.classList.add('is-visible');});
      } else {
        const observer = new IntersectionObserver(function(entries){
          entries.forEach(function(entry){
            if (entry.isIntersecting) {
              // Add the class on entry. If it was removed on exit, the transition replays.
              entry.target.classList.add('is-visible');
            } else {
              // Reset off-screen elements so they animate again on the next entry,
              // whether the visitor scrolls down or back up.
              entry.target.classList.remove('is-visible');
            }
          });
        }, {
          threshold: 0.12,
          // A small viewport margin avoids retriggering right at the screen edge.
          rootMargin: '0px 0px -8% 0px'
        });

        revealTargets.forEach(function(el){observer.observe(el);});
      }

      function updateNav(){ if(nav) nav.classList.toggle('scrolled', window.scrollY > 18); }
      updateNav();
      window.addEventListener('scroll', updateNav, {passive:true});

      // Role CTA spotlight loop:
      // Investor button + hint for 1s, wait 1s, trader button + hint for 1s,
      // then wait 20s before repeating. Runs only while the group remains in view.
      (function initRolePromptLoop(){
        const stage = document.getElementById('heroRolePromptStage');
        if (!stage) return;
        const roleItems = Array.from(stage.querySelectorAll('.role-action-wrap'));
        const investor = roleItems.find(function(item){ return item.dataset.rolePrompt === 'investor'; });
        const trader = roleItems.find(function(item){ return item.dataset.rolePrompt === 'developer'; });
        if (!investor || !trader) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        let stageIsVisible = false;
        let timer = null;
        let loopToken = 0;

        function clearSpotlight(){
          roleItems.forEach(function(item){
            const button = item.querySelector('.role-prompt-btn');
            const hint = item.querySelector('.role-float-hint');
            if (button) button.classList.remove('is-prompt-active');
            if (hint) hint.classList.remove('is-active');
          });
        }
        function cancelLoop(){
          loopToken++;
          if (timer !== null) window.clearTimeout(timer);
          timer = null;
          clearSpotlight();
        }
        function spotlight(item, token){
          if (!stageIsVisible || token !== loopToken) return;
          clearSpotlight();
          const button = item.querySelector('.role-prompt-btn');
          const hint = item.querySelector('.role-float-hint');
          if (button) { button.classList.remove('is-prompt-active'); void button.offsetWidth; button.classList.add('is-prompt-active'); }
          if (hint) { hint.classList.remove('is-active'); void hint.offsetWidth; hint.classList.add('is-active'); }
        }
        function schedule(callback, delay, token){
          timer = window.setTimeout(function(){
            timer = null;
            if (!stageIsVisible || token !== loopToken) return;
            callback(token);
          }, delay);
        }
        function runCycle(token){
          if (!stageIsVisible || token !== loopToken) return;
          spotlight(investor, token);
          schedule(function(currentToken){
            clearSpotlight();
            // One-second pause between the two role buttons.
            schedule(function(nextToken){
              spotlight(trader, nextToken);
              schedule(function(lastToken){
                clearSpotlight();
                // Wait 20 seconds after the trader spotlight before repeating.
                schedule(function(restartToken){ runCycle(restartToken); }, 1000, lastToken);
              }, 1000, nextToken);
            }, 1000, currentToken);
          }, 1000, token);
        }
        function startLoop(){
          if (!stageIsVisible || timer !== null) return;
          const token = ++loopToken;
          runCycle(token);
        }
        if ('IntersectionObserver' in window) {
          const stageObserver = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
              if (entry.target !== stage) return;
              const nowVisible = entry.isIntersecting && entry.intersectionRatio >= 0.2;
              if (nowVisible && !stageIsVisible) { stageIsVisible = true; startLoop(); }
              else if (!nowVisible && stageIsVisible) { stageIsVisible = false; cancelLoop(); }
            });
          }, {threshold:[0, 0.2, 0.5], rootMargin:'0px'});
          stageObserver.observe(stage);
        } else {
          function checkVisibility(){
            const rect = stage.getBoundingClientRect();
            const visible = rect.bottom > 0 && rect.top < window.innerHeight;
            if (visible && !stageIsVisible) { stageIsVisible = true; startLoop(); }
            else if (!visible && stageIsVisible) { stageIsVisible = false; cancelLoop(); }
          }
          checkVisibility();
          window.addEventListener('scroll', checkVisibility, {passive:true});
          window.addEventListener('resize', checkVisibility);
        }
        document.addEventListener('visibilitychange', function(){
          if (document.hidden) cancelLoop();
          else if (stageIsVisible) startLoop();
        });
      })();

      // Gentle pointer-based depth on desktop; disabled for touch/reduced-motion.
      if(!reduceMotion && window.matchMedia('(hover:hover) and (pointer:fine)').matches){
        document.querySelectorAll('.ad, .roleCard, .feature').forEach(function(card){
          card.addEventListener('pointermove', function(e){
            const r=card.getBoundingClientRect();
            const x=(e.clientX-r.left)/r.width;
            const y=(e.clientY-r.top)/r.height;
            card.style.setProperty('--pointer-x',(x*100)+'%');
            card.style.setProperty('--pointer-y',(y*100)+'%');
          });
          card.addEventListener('pointerleave', function(){
            card.style.removeProperty('--pointer-x');
            card.style.removeProperty('--pointer-y');
          });
        });
      }
    })();

    <?php if ($loginError): ?>
    openM('loginModal');
    <?php elseif ($signupError): ?>
    openM('signupModal');
    <?php endif; ?>
</script>
</body>
</html>