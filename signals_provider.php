<?php
// signals_provider.php — HarvHub Signal Provider website (public page + signup/signin)
session_start();

// ==================== DATABASE ====================
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

// ==================== SESSION USER ====================
$user = null;
if (isset($_SESSION['user_email'])) {
    $s = $pdo->prepare("SELECT * FROM harvhub WHERE email = ? LIMIT 1");
    $s->execute([strtolower($_SESSION['user_email'])]);
    $user = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
$loggedIn  = $user !== null;
$darkMode  = ($loggedIn && !empty($user['dark_mode'])) ? 1 : 0;

// ==================== HAS INTEREST? (dashboard is earned) ====================
$hasInterest = false;
if ($loggedIn) {
    $chk = $pdo->prepare("SELECT id FROM signal_provider_interest WHERE user_id = ? LIMIT 1");
    $chk->execute([(int)$user['id']]);
    $hasInterest = (bool)$chk->fetch();
}

// ==================== HELPERS ====================
function cleanCfg($c) {
    if (!is_array($c)) return null;
    $periods = ['daily', 'weekly', 'monthly'];
    $types   = ['fixed_risk_reward', 'custom_and_minimum_risk_reward'];
    $period  = $c['period'] ?? '';
    if (!in_array($period, $periods, true)) return null;
    $type = in_array($c['rr_type'] ?? '', $types, true) ? $c['rr_type'] : 'fixed_risk_reward';

    $num = function ($v, $min, $max, $int) {
        if ($v === null || $v === '') return null;
        $v = (float)$v;
        $v = max($min, min($max, $v));
        return $int ? (int)round($v) : round($v, 2);
    };
    return [
        'period' => $period,
        'rr_type' => $type,
        'rr' => $num($c['rr'] ?? null, 2, 20, false),
        'loss' => $num($c['loss'] ?? null, 1, 20, true),
        'trades' => $num($c['trades'] ?? null, 1, 50, true),
        'win' => $num($c['win'] ?? null, 1, 50, false),
    ];
}

/** Records the provider interest ONLY if one doesn't already exist for this user. */
function recordInterest(PDO $pdo, int $uid, ?array $cfg): string {
    if (!$cfg) return 'skipped';
    $chk = $pdo->prepare("SELECT id FROM signal_provider_interest WHERE user_id = ? LIMIT 1");
    $chk->execute([$uid]);
    if ($chk->fetch()) return 'exists';
    $ins = $pdo->prepare("INSERT INTO signal_provider_interest
        (user_id, risk_reward, risk_reward_type, trades_count, expected_win, expected_consecutive_losses, period)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$uid, $cfg['rr'], $cfg['rr_type'], $cfg['trades'], $cfg['win'], $cfg['loss'], $cfg['period']]);
    return 'saved';
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $cfgRaw = $_POST['config'] ?? '';
    $cfg = ($cfgRaw !== '') ? cleanCfg(json_decode($cfgRaw, true)) : null;

    try {
        // ---------- LOGOUT ----------
        if ($action === 'logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Signed out.']);
            exit;
        }

        // ---------- SIGN UP ----------
        if ($action === 'signup') {
            $fullname = trim($_POST['fullname'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $pass     = $_POST['password'] ?? '';
            if ($fullname === '' || strlen($fullname) > 120) { echo json_encode(['success' => false, 'message' => 'Enter your full name.']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))   { echo json_encode(['success' => false, 'message' => 'Enter a valid email address.']); exit; }
            if (strlen($pass) < 8)                            { echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); exit; }

            $e = $pdo->prepare("SELECT id FROM harvhub WHERE email = ? LIMIT 1");
            $e->execute([$email]);
            if ($e->fetch()) { echo json_encode(['success' => false, 'message' => 'That email already has an account. Please sign in instead.']); exit; }

            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO harvhub (fullname, email, password) VALUES (?, ?, ?)");
            $ins->execute([$fullname, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            $uid = (int)$pdo->lastInsertId();
            $status = recordInterest($pdo, $uid, $cfg);
            $pdo->commit();

            $_SESSION['user_email'] = $email;

            if ($status === 'saved' || $status === 'exists') {
                echo json_encode(['success' => true, 'status' => $status, 'redirect' => 'signals_dashboard.php']);
            } else {
                echo json_encode(['success' => true, 'status' => 'none', 'redirect' => null]);
            }
            exit;
        }

        // ---------- SIGN IN ----------
        if ($action === 'signin') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password'] ?? '';
            $s = $pdo->prepare("SELECT * FROM harvhub WHERE email = ? LIMIT 1");
            $s->execute([$email]);
            $u = $s->fetch(PDO::FETCH_ASSOC);
            $ok = $u && isset($u['password']) && (password_verify($pass, $u['password']) || hash_equals((string)$u['password'], $pass));
            if (!$ok) { echo json_encode(['success' => false, 'message' => 'Incorrect email or password.']); exit; }

            $_SESSION['user_email'] = $email;
            $status = recordInterest($pdo, (int)$u['id'], $cfg);

            if ($status === 'saved' || $status === 'exists') {
                echo json_encode(['success' => true, 'status' => $status, 'redirect' => 'signals_dashboard.php']);
            } else {
                echo json_encode(['success' => true, 'status' => 'none', 'redirect' => null]);
            }
            exit;
        }

        // ---------- INTERESTED (already logged in) ----------
        if ($action === 'interested') {
            if (!$loggedIn) { echo json_encode(['success' => false, 'auth' => true, 'message' => 'Please sign in.']); exit; }
            if (!$cfg)      { echo json_encode(['success' => false, 'message' => 'Invalid offer.']); exit; }
            $status = recordInterest($pdo, (int)$user['id'], $cfg);
            if ($status === 'saved') {
                echo json_encode(['success' => true, 'status' => 'saved', 'redirect' => 'signals_dashboard.php']);
            } else {
                echo json_encode(['success' => true, 'status' => 'exists', 'redirect' => 'signals_dashboard.php']);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Become a Forex Signal Provider — HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{
  --bg:#f4f8f7;--card:#fff;--text:#12211d;--muted:#5f736d;--soft:#8ea19b;
  --accent:#12a36b;--accent2:#0b7d52;--accent-soft:#e3f6ee;--border:#dfe9e5;
  --danger:#e04b4b;--shadow:0 10px 30px rgba(18,33,29,.07);--r:18px;
  --hero1:#0b3d2e;--hero2:#12a36b;
}
body.dark-mode{
  --bg:#0c1311;--card:#131d1a;--text:#e8f2ee;--muted:#93a8a1;--soft:#65786f;
  --accent:#2ecc8f;--accent2:#22a872;--accent-soft:#12271f;--border:#22322d;
  --shadow:0 10px 30px rgba(0,0,0,.4);--hero1:#06231a;--hero2:#0f6b48;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.6}
a{color:inherit;text-decoration:none}
button{font-family:inherit}
input,select{font-size:16px}
.wrap{max-width:1140px;margin:0 auto;padding:0 20px}

/* NAV */
.nav{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}
.nav .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}
.logo{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.15rem}
.logo i{width:34px;height:34px;border-radius:10px;background:var(--accent);color:#fff;display:grid;place-items:center;font-style:normal;font-size:1rem}
.nav-links{display:flex;gap:26px;font-size:.9rem;font-weight:600;color:var(--muted)}
.nav-links a:hover{color:var(--accent)}
.nav-actions{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:12px;padding:11px 20px;font-weight:700;font-size:.9rem;cursor:pointer;transition:.18s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2);transform:translateY(-1px)}
.btn-ghost{background:transparent;color:var(--text);border:1px solid var(--border)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-danger{background:transparent;color:var(--danger);border:1px solid var(--danger)}.btn-danger:hover{background:var(--danger);color:#fff}
.btn-block{width:100%}
.btn:disabled{opacity:.55;cursor:not-allowed}
@media(max-width:760px){.nav-links{display:none}.nav-actions .btn{padding:9px 14px;font-size:.82rem}}

/* HERO */
.hero{background:linear-gradient(135deg,var(--hero1),var(--hero2));color:#fff;padding:84px 0 96px;position:relative;overflow:hidden}
.hero::after{content:"";position:absolute;right:-120px;top:-120px;width:420px;height:420px;border-radius:50%;background:rgba(255,255,255,.07)}
.hero .wrap{position:relative;z-index:1;display:grid;grid-template-columns:1.2fr .8fr;gap:40px;align-items:center}
.pill{display:inline-block;background:rgba(255,255,255,.15);padding:6px 14px;border-radius:99px;font-size:.78rem;font-weight:700;letter-spacing:.4px;margin-bottom:18px}
.hero h1{font-size:clamp(2rem,4.6vw,3.2rem);line-height:1.12;letter-spacing:-1px;margin-bottom:16px}
.hero p{font-size:1.05rem;opacity:.9;max-width:560px;margin-bottom:26px}
.hero .btn-primary{background:#fff;color:var(--hero1)}
.hero .btn-ghost{color:#fff;border-color:rgba(255,255,255,.4)}
.hero-actions{display:flex;gap:12px;flex-wrap:wrap}
.hero-card{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:var(--r);padding:22px;backdrop-filter:blur(8px)}
.hero-card h4{font-size:.8rem;text-transform:uppercase;letter-spacing:.7px;opacity:.8;margin-bottom:14px}
.mini{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.15);font-size:.92rem}
.mini:last-child{border:none}.mini b{font-weight:700}
@media(max-width:880px){.hero .wrap{grid-template-columns:1fr}.hero{padding:56px 0 64px}}

/* SECTIONS */
section{padding:72px 0}
.sec-head{text-align:center;max-width:680px;margin:0 auto 40px}
.sec-head .tag{color:var(--accent);font-weight:800;font-size:.78rem;letter-spacing:1px;text-transform:uppercase}
.sec-head h2{font-size:clamp(1.6rem,3.2vw,2.2rem);letter-spacing:-.5px;margin:8px 0 10px}
.sec-head p{color:var(--muted)}
.grid{display:grid;gap:18px}
.g3{grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
.g4{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.box{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:24px;box-shadow:var(--shadow)}
.box .ic{width:46px;height:46px;border-radius:13px;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:1.1rem;margin-bottom:14px}
.box h3{font-size:1.05rem;margin-bottom:6px}.box p{color:var(--muted);font-size:.92rem}
.step-n{font-size:2rem;font-weight:800;color:var(--accent);opacity:.5;margin-bottom:6px}
.chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.chip{background:var(--accent-soft);color:var(--accent);padding:6px 12px;border-radius:99px;font-size:.8rem;font-weight:700}
.alt{background:var(--card);border-block:1px solid var(--border)}

/* OFFERS */
.tabs{display:flex;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:6px;max-width:520px;margin:0 auto 22px}
.tab{flex:1;background:transparent;border:none;padding:12px 10px;border-radius:10px;font-weight:700;font-size:.92rem;color:var(--muted);cursor:pointer;transition:.18s}
.tab.active{background:var(--accent);color:#fff;box-shadow:0 4px 14px rgba(18,163,107,.3)}
.subbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;justify-content:center;margin-bottom:26px}
.filter-btn{background:var(--text);color:var(--bg);border:none;border-radius:99px;padding:8px 18px;font-weight:700;font-size:.82rem;cursor:pointer;display:inline-flex;gap:8px;align-items:center}
.filter-note{display:flex;justify-content:center;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px;font-size:.88rem;color:var(--muted)}
.filter-note button{background:none;border:none;color:var(--danger);font-weight:700;cursor:pointer}
.offer-desc{text-align:center;color:var(--muted);font-size:.92rem;margin-bottom:22px}
.cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.offer{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;display:flex;flex-direction:column;gap:10px;transition:.2s;box-shadow:var(--shadow)}
.offer:hover{transform:translateY(-3px);border-color:var(--accent)}
.offer.hl{border:2px solid var(--accent)}
.o-tag{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--accent)}
.o-big{font-size:2rem;font-weight:800;letter-spacing:-1px}
.o-title{font-weight:700;font-size:.95rem}
.o-list{list-style:none;color:var(--muted);font-size:.86rem;display:flex;flex-direction:column;gap:6px;flex:1}
.o-list li::before{content:"✓ ";color:var(--accent);font-weight:800}
.more-wrap{text-align:center;margin-top:24px}

/* FAQ */
.faq{max-width:760px;margin:0 auto}
.faq details{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:10px}
.faq summary{font-weight:700;cursor:pointer}
.faq p{color:var(--muted);margin-top:10px;font-size:.92rem}
footer{padding:36px 0;text-align:center;color:var(--soft);font-size:.85rem;border-top:1px solid var(--border)}

/* MODALS */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:999;align-items:center;justify-content:center;padding:18px}
.modal.open{display:flex}
.mc{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:28px;max-width:440px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);animation:pop .25s ease}
@keyframes pop{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:none}}
.mc h3{font-size:1.25rem;margin-bottom:6px;text-align:center}
.mc .sub{color:var(--muted);text-align:center;font-size:.9rem;margin-bottom:18px}
.mc .ic-big{width:64px;height:64px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-size:1.6rem;margin:0 auto 14px}
.field{margin-bottom:14px}
.field label{display:block;font-size:.74rem;text-transform:uppercase;letter-spacing:.5px;font-weight:700;color:var(--muted);margin-bottom:6px}
.field input,.field select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text);font-family:inherit}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.hint{font-size:.74rem;color:var(--soft);margin-top:4px}
.err{background:rgba(224,75,75,.1);border-left:3px solid var(--danger);color:var(--danger);padding:10px 12px;border-radius:10px;font-size:.85rem;margin-bottom:12px;display:none}
.stack{display:flex;flex-direction:column;gap:10px}
.summary{background:var(--accent-soft);border-radius:12px;padding:12px 14px;font-size:.85rem;color:var(--text);margin-bottom:16px}
.summary b{color:var(--accent)}
.info-box{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:12px 14px;font-size:.82rem;color:var(--muted);margin-bottom:16px}
.info-box li{margin:4px 0 4px 18px}
.switch-link{text-align:center;font-size:.85rem;color:var(--muted);margin-top:14px}
.switch-link a{color:var(--accent);font-weight:700;cursor:pointer}
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<!-- NAV -->
<nav class="nav"><div class="wrap">
  <a href="#top" class="logo"><i>H</i> HarvHub <span style="color:var(--accent)">Signals</span></a>
  <div class="nav-links">
    <a href="#why">Why provide</a><a href="#how">How it works</a><a href="#symbols">Symbols</a><a href="#offers">Offers</a><a href="#faq">FAQ</a>
  </div>
  <div class="nav-actions">
    <?php if ($loggedIn && $hasInterest): ?>
      <a href="signals_dashboard.php" class="btn btn-primary">My Dashboard</a>
      <button type="button" class="btn btn-danger" id="logoutBtn">Logout</button>
    <?php elseif ($loggedIn && !$hasInterest): ?>
      <button type="button" class="btn btn-danger" id="logoutBtn">Logout</button>
    <?php else: ?>
      <button type="button" class="btn btn-ghost" onclick="openAuthDirect('signin')">Sign in</button>
      <button type="button" class="btn btn-primary" onclick="openAuthDirect('signup')">Sign up</button>
    <?php endif; ?>
  </div>
</div></nav>

<!-- HERO -->
<header class="hero" id="top"><div class="wrap">
  <div>
    <span class="pill"><i class="fa-solid fa-bolt"></i> FOREX SIGNAL PROVIDER PROGRAM</span>
    <h1>Turn your trading edge into a signal business.</h1>
    <p>Become a verified trade provider on HarvHub. Publish forex signals to investors who trust performance, not promises, and earn a share of the revenue when they follow you.</p>
    <div class="hero-actions">
      <a href="#offers" class="btn btn-primary">Choose your offer</a>
      <a href="#how" class="btn btn-ghost">See how it works</a>
    </div>
  </div>
  <div class="hero-card">
    <h4>What you commit to</h4>
    <div class="mini"><span>Risk : Reward</span><b>From 1:2 up to 1:20</b></div>
    <div class="mini"><span>Consecutive losses</span><b>Up to 20 max</b></div>
    <div class="mini"><span>Trades per period</span><b>1 to 50+</b></div>
    <div class="mini"><span>Verification</span><b>30 day demo test</b></div>
  </div>
</div></header>

<!-- WHY -->
<section id="why"><div class="wrap">
  <div class="sec-head"><span class="tag">Why provide on HarvHub</span><h2>Investors find you. You keep trading your way.</h2>
  <p>We give serious traders an audience, a verification pipeline and a revenue share, with no marketing budget required.</p></div>
  <div class="grid g3">
    <div class="box"><div class="ic"><i class="fa-solid fa-users"></i></div><h3>Built in investor audience</h3><p>Once approved and published, your signal appears to investors browsing for proven providers.</p></div>
    <div class="box"><div class="ic"><i class="fa-solid fa-percent"></i></div><h3>Revenue share</h3><p>You set your signal provider percentage. When investors profit following your signals, you earn your share.</p></div>
    <div class="box"><div class="ic"><i class="fa-solid fa-shield-halved"></i></div><h3>Trust through verification</h3><p>Every provider is tested on a demo account first, so investors only follow signals that met their own stated targets.</p></div>
    <div class="box"><div class="ic"><i class="fa-solid fa-sliders"></i></div><h3>You define the rules</h3><p>Pick the risk reward, loss tolerance, trade count and win expectations you can honestly deliver.</p></div>
  </div>
</div></section>

<!-- HOW -->
<section id="how" class="alt"><div class="wrap">
  <div class="sec-head"><span class="tag">How it works</span><h2>From interest to published signal in four steps</h2></div>
  <div class="grid g4">
    <div class="box"><div class="step-n">01</div><h3>Select your offer</h3><p>Choose what you can afford to provide: risk reward, consecutive loss limit, trades count and expected wins, per day, week or month.</p></div>
    <div class="box"><div class="step-n">02</div><h3>Create your account</h3><p>Sign up or sign in. Your selected offer is saved as your provider interest and becomes your criteria.</p></div>
    <div class="box"><div class="ic" style="display:none"></div><div class="step-n">03</div><h3>30 day demo test</h3><p>Pick a broker, then drop your signals. Our server tests them on a demo account and updates your analytics every 24 hours for one month.</p></div>
    <div class="box"><div class="step-n">04</div><h3>Go live</h3><p>When your analytics meet your criteria, the server approves your signal. Switch it to public and investors can start following.</p></div>
  </div>
</div></section>

<!-- RISK REWARD TYPES + SYMBOLS -->
<section id="symbols"><div class="wrap">
  <div class="sec-head"><span class="tag">Signal types &amp; symbols</span><h2>Know what you're offering</h2></div>
  <div class="grid g3">
    <div class="box"><div class="ic"><i class="fa-solid fa-lock"></i></div><h3>Fixed risk reward</h3><p>You send entry and stop loss only. We automatically attach the take profit using your fixed ratio, so every signal is 1:X exactly.</p></div>
    <div class="box"><div class="ic"><i class="fa-solid fa-arrows-up-down"></i></div><h3>Custom &amp; minimum risk reward</h3><p>You choose entry, stop loss and take profit yourself, as long as each trade stays at or above your minimum 1:X.</p></div>
    <div class="box"><div class="ic"><i class="fa-solid fa-coins"></i></div><h3>Symbols you can provide</h3><p>Any symbol offered by the broker you connect to the server account.</p>
      <div class="chips"><span class="chip">EURUSD</span><span class="chip">GBPUSD</span><span class="chip">USDJPY</span><span class="chip">AUDUSD</span><span class="chip">USDCAD</span><span class="chip">XAUUSD</span><span class="chip">GBPJPY</span><span class="chip">NAS100</span></div></div>
  </div>
</div></section>

<!-- OFFERS -->
<section id="offers" class="alt"><div class="wrap">
  <div class="sec-head"><span class="tag">Select your offer</span><h2>What can you afford to provide?</h2>
  <p>Pick a period, browse the configurations, then tap <b>Interested</b> on the one that fits you.</p></div>

  <div class="tabs" id="periodTabs">
    <button class="tab active" data-p="daily">Daily Trades</button>
    <button class="tab" data-p="weekly">Weekly Trades</button>
    <button class="tab" data-p="monthly">Monthly Trades</button>
  </div>

  <div class="subbar" id="subbar">
    <button class="filter-btn" id="openFilter"><i class="fa-solid fa-filter"></i> Filter</button>
  </div>

  <div class="filter-note" id="filterNote" style="display:none">
    <span><i class="fa-solid fa-filter"></i> Showing configurations for your filter.</span>
    <button id="clearFilter">Clear filter</button>
  </div>
  <p class="offer-desc" id="offerDesc"></p>
  <div class="cards" id="cards"></div>
  <div class="more-wrap"><button class="btn btn-ghost" id="moreBtn" style="display:none">Show more</button></div>
</div></section>

<!-- FAQ -->
<section id="faq"><div class="wrap">
  <div class="sec-head"><span class="tag">FAQ</span><h2>Questions providers ask</h2></div>
  <div class="faq">
    <details><summary>Do I need to be a developer?</summary><p>No. Any HarvHub user can become a signal provider.</p></details>
    <details><summary>Why is there a demo test?</summary><p>Before your signal is published, the server tests your trades on a demo account for one month. Reports update on your dashboard every 24 hours so investors can trust your record.</p></details>
    <details><summary>Can I have more than one signal?</summary><p>No, each provider runs one signal. Focus on making it excellent.</p></details>
    <details><summary>What if I don't meet my criteria?</summary><p>Your analytics are compared against the offer you selected. You will have to reapply and join the queue again.</p></details>
    <details><summary>How do I get paid?</summary><p>You set a signal provider percentage on your dashboard. It's your share of the revenue from investors following your signal.</p></details>
  </div>
</div></section>

<footer>© <?= date('Y') ?> HarvHub. Trading forex involves substantial risk. Past performance does not guarantee future results.</footer>

<!-- FILTER MODAL -->
<div class="modal" id="filterModal"><div class="mc">
  <h3>Configure Filter</h3>
  <p class="sub" id="filterSub">Fill every field to see matching configurations.</p>
  <div class="err" id="filterErr"></div>
  <div class="field"><label>Risk Reward Type</label>
    <select id="fType"><option value="custom_and_minimum_risk_reward">Custom and Minimum Risk Reward</option><option value="fixed_risk_reward">Fixed Risk Reward</option></select></div>
  <div class="field"><label>Risk Reward (1 : ?)</label><input type="number" id="fRr" min="2" max="20" step="0.5" placeholder="2 to 20"><div class="hint">Minimum 2, maximum 20.</div></div>
  <div class="field"><label id="lLoss">Consecutive Expected Loss</label><input type="number" id="fLoss" min="1" max="20" step="1" placeholder="1 to 20"><div class="hint">Maximum 20.</div></div>
  <div class="field"><label id="lTrades">Trades Count</label><input type="number" id="fTrades" min="1" max="50" step="1" placeholder="1 to 50"></div>
  <div class="field"><label id="lWin">Expected Win</label><input type="number" id="fWin" min="1" max="50" step="1" placeholder="1 to 50"></div>
  <div class="stack"><button class="btn btn-primary btn-block" id="applyFilter">Apply Filter</button><button class="btn btn-ghost btn-block" data-close="filterModal">Cancel</button></div>
</div></div>

<!-- AUTH PROMPT -->
<div class="modal" id="authPrompt"><div class="mc">
  <div class="ic-big"><i class="fa-solid fa-user-lock"></i></div>
  <h3>Sign in to continue</h3>
  <p class="sub">Your selected offer is saved once you're in.</p>
  <div class="summary" id="promptSummary"></div>
  <div class="err" id="pErr"></div>
  <div class="field"><label>Email</label><input id="pEmail" type="email" autocomplete="email" placeholder="you@example.com"></div>
  <div class="field"><label>Password</label><input id="pPass" type="password" autocomplete="current-password" placeholder="Your password"></div>
  <button class="btn btn-primary btn-block" id="pSigninBtn">Sign in</button>
  <div class="switch-link">Don't have an account? <a id="goSignup">Sign up</a></div>
  <div class="stack" style="margin-top:12px">
    <button class="btn btn-ghost btn-block" data-close="authPrompt" style="border:none;color:var(--muted)">Not now</button>
  </div>
</div></div>

<!-- SIGNUP -->
<div class="modal" id="signupModal"><div class="mc">
  <h3>Create your account</h3>
  <p class="sub" id="signupSub">Takes less than a minute.</p>
  <div class="summary" id="signupSummary" style="display:none"></div>
  <div class="err" id="signupErr"></div>
  <div class="field"><label>Full name</label><input id="suName" autocomplete="name" placeholder="Jane Doe"></div>
  <div class="field"><label>Email</label><input id="suEmail" type="email" autocomplete="email" placeholder="you@example.com"></div>
  <div class="field"><label>Password</label><input id="suPass" type="password" autocomplete="new-password" placeholder="At least 8 characters"></div>
  <button class="btn btn-primary btn-block" id="signupBtn">Create account</button>
  <div class="switch-link">Already have an account? <a id="toSignin">Sign in</a></div>
</div></div>

<!-- SIGNIN -->
<div class="modal" id="signinModal"><div class="mc">
  <h3>Welcome back</h3>
  <p class="sub" id="signinSub">Sign in to continue.</p>
  <div class="summary" id="signinSummary" style="display:none"></div>
  <div class="err" id="signinErr"></div>
  <div class="field"><label>Email</label><input id="siEmail" type="email" autocomplete="email"></div>
  <div class="field"><label>Password</label><input id="siPass" type="password" autocomplete="current-password"></div>
  <button class="btn btn-primary btn-block" id="signinBtn">Sign in</button>
  <div class="switch-link">New here? <a id="toSignup">Create an account</a></div>
</div></div>

<!-- RESULT -->
<div class="modal" id="resultModal"><div class="mc">
  <div class="ic-big"><i class="fa-solid fa-circle-check"></i></div>
  <h3 id="resTitle"></h3><p class="sub" id="resText"></p>
  <button class="btn btn-primary btn-block" id="resOk">Continue</button>
</div></div>

<script>
var LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
var HAS_INTEREST = <?= $hasInterest ? 'true' : 'false' ?>;
var PERIODS = {daily:{label:'Daily',unit:'day'},weekly:{label:'Weekly',unit:'week'},monthly:{label:'Monthly',unit:'month'}};
var state = {period:'daily', shown:12, filter:{daily:null,weekly:null,monthly:null}};
var CURRENT = [], PENDING = null, REDIRECT = null;

// dark mode fallback for visitors
if (!document.body.classList.contains('dark-mode') && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) document.body.classList.add('dark-mode');

function $(id){return document.getElementById(id)}
function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML}
function openM(id){$(id).classList.add('open')}
function closeM(id){$(id).classList.remove('open')}
document.querySelectorAll('[data-close]').forEach(function(b){b.onclick=function(){closeM(b.dataset.close)}});

/* ---------- LOGOUT ---------- */
var logoutBtn = $('logoutBtn');
if (logoutBtn) {
  logoutBtn.onclick = function() {
    logoutBtn.disabled = true;
    logoutBtn.textContent = 'Signing out...';
    post({action:'logout'}, function(d){
      location.reload();
    }, logoutBtn);
  };
}

/* ---------- Direct nav auth ---------- */
function openAuthDirect(kind){
    PENDING = null;   // no offer attached
    if (kind === 'signup') {
        $('signupSub').textContent = 'Takes less than a minute.';
        setSummary('signupSummary', null);
        $('signupErr').style.display = 'none';
        openM('signupModal');
    } else {
        $('signinSub').textContent = 'Sign in to your account.';
        setSummary('signinSummary', null);
        $('signinErr').style.display = 'none';
        openM('signinModal');
    }
}
window.openAuthDirect = openAuthDirect;

/* ---------- card generation ---------- */
var FIXED='fixed_risk_reward', CUSTOM='custom_and_minimum_risk_reward';

function generateAll(period) {
    var list = [];
    var base = { period: period, rr_type: null, rr: null, loss: null, trades: null, win: null, kind: 'Standard' };

    for (var rr = 2; rr <= 20; rr++) {
        list.push(Object.assign({}, base, { rr_type: FIXED, rr: rr, loss: 2, trades: 1, win: 1, kind: 'Fixed risk reward' }));
        list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: rr, loss: 2, trades: 1, win: 1, kind: 'Custom & minimum risk reward' }));
    }
    for (var loss = 1; loss <= 20; loss++) {
        list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: 2, loss: loss, trades: 1, win: 1, kind: 'Consecutive loss' }));
    }
    for (var tr = 1; tr <= 50; tr++) {
        list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: 2, loss: 2, trades: tr, win: 1, kind: 'Trades count' }));
    }
    for (var win = 1; win <= 50; win++) {
        list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: 2, loss: 2, trades: 1, win: win, kind: 'Expected win' }));
    }
    list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: 3, loss: 5, trades: 10, win: 7, kind: 'Balanced' }));
    list.push(Object.assign({}, base, { rr_type: FIXED, rr: 5, loss: 3, trades: 20, win: 15, kind: 'Aggressive' }));
    list.push(Object.assign({}, base, { rr_type: CUSTOM, rr: 2, loss: 2, trades: 1, win: 1, kind: 'Conservative' }));

    var seen = {}, unique = [];
    list.forEach(function(cfg) {
        var key = [cfg.period, cfg.rr_type, cfg.rr, cfg.loss, cfg.trades, cfg.win].join('|');
        if (!seen[key]) { seen[key] = true; unique.push(cfg); }
    });
    return unique;
}

function lines(cfg){
  var P=PERIODS[cfg.period],l=[];
  if(cfg.rr!=null) l.push(cfg.rr_type===FIXED ? 'Fixed 1:'+cfg.rr+', we attach your take profit' : 'Custom take profit, never below 1:'+cfg.rr);
  if(cfg.loss!=null) l.push('No more than '+cfg.loss+' consecutive losses');
  if(cfg.trades!=null) l.push('At least '+cfg.trades+' '+P.label.toLowerCase()+' trade'+(cfg.trades>1?'s':''));
  if(cfg.win!=null) l.push('At least '+cfg.win+' winning trade'+(cfg.win>1?'s':'')+' each '+P.unit);
  return l;
}
function big(cfg){
  if(cfg.rr!=null&&cfg.loss==null&&cfg.trades==null&&cfg.win==null) return (cfg.rr_type===FIXED?'':'≥ ')+'1:'+cfg.rr;
  if(cfg.loss!=null&&cfg.rr==null&&cfg.trades==null&&cfg.win==null) return '≤ '+cfg.loss;
  if(cfg.trades!=null&&cfg.rr==null&&cfg.loss==null&&cfg.win==null) return cfg.trades+'+';
  if(cfg.win!=null&&cfg.rr==null&&cfg.loss==null&&cfg.trades==null) return cfg.win+'+';
  return '1:'+cfg.rr;
}
function summaryText(cfg){
  return '<b>'+PERIODS[cfg.period].label+' offer</b><br>'+lines(cfg).map(esc).join('<br>');
}
function setSummary(elId, cfg){
  var el = $(elId);
  if (cfg) { el.innerHTML = summaryText(cfg); el.style.display = 'block'; }
  else { el.innerHTML = ''; el.style.display = 'none'; }
}
function cardHTML(cfg,i,hl){
  return '<div class="offer'+(hl?' hl':'')+'"><div class="o-tag">'+esc(hl?'Your filter':(cfg.kind||'Custom'))+' · '+PERIODS[cfg.period].label+'</div>'+
    '<div class="o-big">'+esc(big(cfg))+'</div><div class="o-title">'+esc(PERIODS[cfg.period].label)+' signals</div>'+
    '<ul class="o-list">'+lines(cfg).map(function(t){return '<li>'+esc(t)+'</li>'}).join('')+'</ul>'+
    '<button class="btn btn-primary btn-block" data-i="'+i+'">Interested</button></div>';
}

/* ---------- render ---------- */
function render(){
  var f=state.filter[state.period], list, hl=false;
  $('filterNote').style.display=f?'flex':'none';
  if(f){ list=f; hl=true; $('offerDesc').textContent='Configurations generated from your filter. The first card is your exact selection.'; }
  else {
    list=generateAll(state.period);
    $('offerDesc').textContent='Full configuration list for '+PERIODS[state.period].label+' trades. Showing every detail per card.';
  }
  CURRENT=list;
  var shown=f?list.length:Math.min(state.shown,list.length), html='';
  for(var i=0;i<shown;i++) html+=cardHTML(list[i],i,f&&i===0);
  $('cards').innerHTML=html;
  $('moreBtn').style.display=(!f&&shown<list.length)?'inline-flex':'none';
  $('moreBtn').textContent='Show more ('+(list.length-shown)+' left)';
}

/* ---------- period tabs ---------- */
$('periodTabs').onclick=function(e){var b=e.target.closest('.tab');if(!b)return;
  document.querySelectorAll('.tab').forEach(function(t){t.classList.toggle('active',t===b)});
  state.period=b.dataset.p;state.shown=12;render();};

/* ---------- show more ---------- */
$('moreBtn').onclick=function(){state.shown+=12;render()};

/* ---------- clear filter ---------- */
$('clearFilter').onclick=function(){
  state.filter[state.period]=null;
  $('openFilter').style.display = '';
  $('filterNote').style.display = 'none';
  var c = $('clearFilterSub'); if (c) c.remove();
  render();
};

/* ---------- open filter modal ---------- */
$('openFilter').onclick=function(){
  var P=PERIODS[state.period];
  $('lLoss').textContent=P.label+' Consecutive Expected Loss';
  $('lTrades').textContent=P.label+' Trades Count';
  $('lWin').textContent='Expected '+P.label+' Win';
  $('filterSub').textContent='Configure your '+P.label.toLowerCase()+' offer. Fill every field to continue.';
  $('filterErr').style.display='none';openM('filterModal');
};

/* ---------- apply filter ---------- */
$('applyFilter').onclick=function(){
  var t=$('fType').value,rr=parseFloat($('fRr').value),loss=parseInt($('fLoss').value,10),tr=parseInt($('fTrades').value,10),win=parseInt($('fWin').value,10),er=$('filterErr');
  var msg='';
  if(isNaN(rr)||rr<2||rr>20) msg='Risk reward must be between 2 and 20.';
  else if(isNaN(loss)||loss<1||loss>20) msg='Consecutive expected loss must be between 1 and 20.';
  else if(isNaN(tr)||tr<1||tr>50) msg='Trades count must be between 1 and 50.';
  else if(isNaN(win)||win<1||win>50) msg='Expected win must be between 1 and 50.';
  if(msg){er.textContent=msg;er.style.display='block';return;}
  var mk=function(v){return{period:state.period,rr_type:t,rr:v,loss:loss,trades:tr,win:win,kind:'Filtered'}};
  var list=[mk(rr)];
  for(var v=rr+1;v<=Math.min(rr+5,20);v++) list.push(mk(v));
  state.filter[state.period]=list;
  closeM('filterModal');

  var subbar = $('subbar');
  var existingClear = $('clearFilterSub'); if (existingClear) existingClear.remove();
  var clearBtnNew = document.createElement('button');
  clearBtnNew.className = 'filter-btn';
  clearBtnNew.id = 'clearFilterSub';
  clearBtnNew.innerHTML = '<i class="fa-solid fa-times"></i> Cancel filter';
  clearBtnNew.onclick = function() {
    state.filter[state.period]=null;
    this.remove();
    $('openFilter').style.display = '';
    render();
  };
  subbar.appendChild(clearBtnNew);
  $('openFilter').style.display = 'none';
  $('filterNote').style.display = 'none';
  render();
};

/* ---------- Interested flow ---------- */
function pick(cfg){
  PENDING = cfg;

  if (!LOGGED_IN) {
    setSummary('promptSummary', cfg);
    $('pErr').style.display = 'none';
    openM('authPrompt');
    return;
  }

  post({action:'interested', config:JSON.stringify(cfg)}, handleResult);
}

function authPayload(extra){
  var data = extra || {};
  if (PENDING) data.config = JSON.stringify(PENDING);
  return data;
}

function post(data,cb,btn){
  var body=Object.keys(data).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(data[k])}).join('&');
  if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Please wait...';}
  fetch('signals_provider.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
   .then(function(r){return r.json()}).then(function(d){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb(d)})
   .catch(function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb({success:false,message:'Network error. Try again.'})});
}

function handleResult(d){
  if(!d.success){ if(d.auth){openM('authPrompt');} else alert(d.message||'Failed.'); return; }
  ['authPrompt','signupModal','signinModal'].forEach(closeM);

  if (d.status === 'saved') {
    REDIRECT = d.redirect || 'signals_dashboard.php';
    $('resTitle').textContent = 'Interest saved!';
    $('resText').textContent = 'Your offer was saved as your provider criteria. Head to your dashboard to name your signal and begin.';
    openM('resultModal');
  } else if (d.status === 'exists') {
    REDIRECT = d.redirect || 'signals_dashboard.php';
    $('resTitle').textContent = 'Existing signal found';
    $('resText').textContent = 'You already have an existing signal you can afford to provide. Your original criteria stay untouched. Check your dashboard.';
    openM('resultModal');
  } else {
    REDIRECT = null;
    $('resTitle').textContent = 'You are signed in';
    $('resText').textContent = 'Your account is ready. Pick an offer below and tap Interested to register your signal criteria.';
    openM('resultModal');
  }
}

$('resOk').onclick=function(){
  if (REDIRECT) { location.href = REDIRECT; }
  else { closeM('resultModal'); location.reload(); }
};

/* ---------- auth prompt inline sign in ---------- */
$('pSigninBtn').onclick=function(){
  var er=$('pErr');er.style.display='none';
  post(authPayload({action:'signin',email:$('pEmail').value,password:$('pPass').value}),
   function(d){if(!d.success){er.textContent=d.message;er.style.display='block';}else handleResult(d)},$('pSigninBtn'));
};
['pEmail','pPass'].forEach(function(id){$(id).addEventListener('keydown',function(e){if(e.key==='Enter')$('pSigninBtn').click();})});

/* ---------- switch between auth modals ---------- */
$('goSignup').onclick=function(){
  closeM('authPrompt');
  setSummary('signupSummary', PENDING);
  $('signupSub').textContent = PENDING ? 'Your selected offer is saved with your new account.' : 'Takes less than a minute.';
  $('signupErr').style.display = 'none';
  openM('signupModal');
};
$('toSignin').onclick=function(){
  closeM('signupModal');
  setSummary('signinSummary', PENDING);
  $('signinSub').textContent = PENDING ? 'Sign in and your offer is saved.' : 'Sign in to continue.';
  $('signinErr').style.display = 'none';
  openM('signinModal');
};
$('toSignup').onclick=function(){
  closeM('signinModal');
  setSummary('signupSummary', PENDING);
  $('signupSub').textContent = PENDING ? 'Your selected offer is saved with your new account.' : 'Takes less than a minute.';
  $('signupErr').style.display = 'none';
  openM('signupModal');
};

/* ---------- signup / signin submit ---------- */
$('signupBtn').onclick=function(){
  var er=$('signupErr');er.style.display='none';
  post(authPayload({action:'signup',fullname:$('suName').value,email:$('suEmail').value,password:$('suPass').value}),
   function(d){if(!d.success){er.textContent=d.message;er.style.display='block';}else handleResult(d)},$('signupBtn'));
};
$('signinBtn').onclick=function(){
  var er=$('signinErr');er.style.display='none';
  post(authPayload({action:'signin',email:$('siEmail').value,password:$('siPass').value}),
   function(d){if(!d.success){er.textContent=d.message;er.style.display='block';}else handleResult(d)},$('signinBtn'));
};
['suName','suEmail','suPass'].forEach(function(id){$(id).addEventListener('keydown',function(e){if(e.key==='Enter')$('signupBtn').click();})});
['siEmail','siPass'].forEach(function(id){$(id).addEventListener('keydown',function(e){if(e.key==='Enter')$('signinBtn').click();})});

document.querySelectorAll('.modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m&&m.id!=='resultModal')m.classList.remove('open')})});

$('cards').onclick=function(e){var b=e.target.closest('button[data-i]');if(!b)return;pick(CURRENT[+b.dataset.i]);};

/* initial render */
render();
</script>
</body>
</html>