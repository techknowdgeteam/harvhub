<?php
// signals_dashboard.php — Signal Provider Dashboard (standalone, with inline auth)
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

function esc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function jres($arr) { header('Content-Type: application/json'); echo json_encode($arr); exit; }

// ==================== AUTH AJAX (works even when not logged in) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['dash_signup', 'dash_signin'], true)) {
    $action = $_POST['action'];
    try {
        if ($action === 'dash_signup') {
            $fullname = trim($_POST['fullname'] ?? '');
            $email    = strtolower(trim($_POST['email'] ?? ''));
            $pass     = $_POST['password'] ?? '';
            if ($fullname === '' || strlen($fullname) > 120) jres(['success' => false, 'message' => 'Enter your full name.']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jres(['success' => false, 'message' => 'Enter a valid email address.']);
            if (strlen($pass) < 8) jres(['success' => false, 'message' => 'Password must be at least 8 characters.']);

            $e = $pdo->prepare("SELECT id FROM harvhub WHERE email = ? LIMIT 1");
            $e->execute([$email]);
            if ($e->fetch()) jres(['success' => false, 'message' => 'That email already has an account. Please sign in instead.']);

            $ins = $pdo->prepare("INSERT INTO harvhub (fullname, email, password) VALUES (?, ?, ?)");
            $ins->execute([$fullname, $email, password_hash($pass, PASSWORD_DEFAULT)]);

            $_SESSION['user_email'] = $email;
            jres(['success' => true, 'message' => 'Account created.']);
        }

        if ($action === 'dash_signin') {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password'] ?? '';
            $s = $pdo->prepare("SELECT * FROM harvhub WHERE email = ? LIMIT 1");
            $s->execute([$email]);
            $u = $s->fetch(PDO::FETCH_ASSOC);
            $ok = $u && isset($u['password']) && (password_verify($pass, $u['password']) || hash_equals((string)$u['password'], $pass));
            if (!$ok) jres(['success' => false, 'message' => 'Incorrect email or password.']);

            $_SESSION['user_email'] = $email;
            jres(['success' => true, 'message' => 'Signed in.']);
        }
    } catch (Throwable $e) {
        jres(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
}

// ==================== LOGIN CHECK (render inline auth instead of redirecting) ====================
$loggedIn = isset($_SESSION['user_email']);
$user = null;
if ($loggedIn) {
    $email = strtolower($_SESSION['user_email']);
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { unset($_SESSION['user_email']); $loggedIn = false; }
}

if (!$loggedIn) {
    // ---- Render inline signup/signin page and stop ----
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sign In — Signal Provider Dashboard — HarvHub</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
    :root{
      --bg:#f4f8f7;--card:#fff;--text:#12211d;--muted:#5f736d;--soft:#8ea19b;
      --accent:#12a36b;--accent2:#0b7d52;--accent-soft:#e3f6ee;--border:#dfe9e5;
      --danger:#e04b4b;--danger-soft:#fdecec;--shadow:0 10px 30px rgba(18,33,29,.08);
    }
    @media (prefers-color-scheme: dark){
      :root{
        --bg:#0c1311;--card:#131d1a;--text:#e8f2ee;--muted:#93a8a1;--soft:#65786f;
        --accent:#2ecc8f;--accent2:#22a872;--accent-soft:#12271f;--border:#22322d;
        --danger:#ff6b6b;--danger-soft:#2a1414;--shadow:0 10px 30px rgba(0,0,0,.4);
      }
    }
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
    input,button{font-family:inherit;font-size:16px}
    .top{display:flex;justify-content:center;padding:26px 16px 0}
    .logo{display:flex;align-items:center;gap:9px;font-weight:800;font-size:1.1rem;text-decoration:none;color:inherit}
    .logo i{width:34px;height:34px;border-radius:10px;background:var(--accent);color:#fff;display:grid;place-items:center;font-style:normal}
    .wrap{flex:1;display:flex;align-items:center;justify-content:center;padding:30px 16px}
    .auth-card{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:30px 26px;max-width:420px;width:100%;box-shadow:var(--shadow)}
    .auth-card h1{font-size:1.35rem;text-align:center;margin-bottom:6px;letter-spacing:-.4px}
    .auth-card .sub{color:var(--muted);text-align:center;font-size:.88rem;margin-bottom:22px}
    .field{margin-bottom:14px}
    .field label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;font-weight:700;color:var(--muted);margin-bottom:6px}
    .field input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:var(--bg);color:var(--text)}
    .field input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
    .err{background:var(--danger-soft);border-left:3px solid var(--danger);color:var(--danger);padding:10px 12px;border-radius:10px;font-size:.85rem;margin-bottom:14px;display:none}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:12px;padding:12px 20px;font-weight:700;font-size:.92rem;cursor:pointer;width:100%;transition:.15s}
    .btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2)}
    .btn:disabled{opacity:.55;cursor:not-allowed}
    .switch-line{text-align:center;font-size:.85rem;color:var(--muted);margin-top:16px}
    .switch-line a{color:var(--accent);font-weight:700;cursor:pointer;text-decoration:none}
    .info-box{background:var(--accent-soft);border-radius:12px;padding:12px 14px;font-size:.8rem;color:var(--text);margin-bottom:18px;line-height:1.5}
    .info-box i{color:var(--accent);margin-right:6px}
    .panel{display:none}
    .panel.active{display:block}
    footer{text-align:center;padding:20px;color:var(--soft);font-size:.78rem}
    </style>
    </head>
    <body>
    <div class="top"><a href="signals_provider.php" class="logo"><i>H</i> HarvHub Signals</a></div>
    <div class="wrap">
      <div class="auth-card">

        <!-- SIGNUP (default) -->
        <div class="panel active" id="panelSignup">
          <h1>Create your signal provider account</h1>
          <p class="sub">Sign up to access your Signal Provider Dashboard.</p>
          <div class="info-box"><i class="fa-solid fa-circle-info"></i>Once you're in, you can name your signal strategy, run your demo test and manage your signal — all from your dashboard.</div>
          <div class="err" id="suErr"></div>
          <div class="field"><label>Full name</label><input id="suName" autocomplete="name" placeholder="Jane Doe"></div>
          <div class="field"><label>Email</label><input id="suEmail" type="email" autocomplete="email" placeholder="you@example.com"></div>
          <div class="field"><label>Password</label><input id="suPass" type="password" autocomplete="new-password" placeholder="At least 8 characters"></div>
          <button class="btn btn-primary" id="suBtn">Create account</button>
          <div class="switch-line">Already have an account? <a id="toSignin">Sign in</a></div>
        </div>

        <!-- SIGNIN -->
        <div class="panel" id="panelSignin">
          <h1>Welcome back</h1>
          <p class="sub">Sign in to open your Signal Provider Dashboard.</p>
          <div class="err" id="siErr"></div>
          <div class="field"><label>Email</label><input id="siEmail" type="email" autocomplete="email" placeholder="you@example.com"></div>
          <div class="field"><label>Password</label><input id="siPass" type="password" autocomplete="current-password"></div>
          <button class="btn btn-primary" id="siBtn">Sign in</button>
          <div class="switch-line">New here? <a id="toSignup">Create an account</a></div>
        </div>

      </div>
    </div>
    <footer>© <?= date('Y') ?> HarvHub</footer>

    <script>
    function $(id){return document.getElementById(id)}
    function showPanel(id){
      document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('active')});
      $(id).classList.add('active');
    }
    $('toSignin').onclick=function(){showPanel('panelSignin')};
    $('toSignup').onclick=function(){showPanel('panelSignup')};

    function post(data,cb,btn){
      var body=Object.keys(data).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(data[k])}).join('&');
      if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Please wait...';}
      fetch('signals_dashboard.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
       .then(function(r){return r.json()})
       .then(function(d){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb(d)})
       .catch(function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb({success:false,message:'Network error. Try again.'})});
    }

    $('suBtn').onclick=function(){
      var er=$('suErr');er.style.display='none';
      post({action:'dash_signup',fullname:$('suName').value,email:$('suEmail').value,password:$('suPass').value},
        function(d){ if(!d.success){er.textContent=d.message;er.style.display='block';return;} location.reload(); },
        $('suBtn'));
    };
    $('siBtn').onclick=function(){
      var er=$('siErr');er.style.display='none';
      post({action:'dash_signin',email:$('siEmail').value,password:$('siPass').value},
        function(d){ if(!d.success){er.textContent=d.message;er.style.display='block';return;} location.reload(); },
        $('siBtn'));
    };
    ['suName','suEmail','suPass'].forEach(function(id){$(id).addEventListener('keydown',function(e){if(e.key==='Enter')$('suBtn').click();})});
    ['siEmail','siPass'].forEach(function(id){$(id).addEventListener('keydown',function(e){if(e.key==='Enter')$('siBtn').click();})});
    </script>
    </body>
    </html>
    <?php
    exit;
}

// ==================== LOGGED IN FROM HERE ====================
$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = !empty($user['dark_mode']) ? 1 : 0;

// ==================== CONSTANTS ====================
$PERIOD_LABEL = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
$PERIOD_UNIT  = ['daily' => 'day', 'weekly' => 'week', 'monthly' => 'month'];
$FIXED  = 'fixed_risk_reward';
$CUSTOM = 'custom_and_minimum_risk_reward';

// ==================== HELPERS ====================
function getInterest(PDO $pdo, int $uid) {
    $s = $pdo->prepare("SELECT * FROM signal_provider_interest WHERE user_id = ? LIMIT 1");
    $s->execute([$uid]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Returns ONLY the signal (programme) explicitly linked to this signal provider
 * via signal_provider_interest.programme_id. This guarantees we never collide
 * with or pick up an unrelated existing programme row.
 */
function getProgramme(PDO $pdo, int $uid) {
    $s = $pdo->prepare("
        SELECT p.*
        FROM programme p
        INNER JOIN signal_provider_interest spi ON spi.programme_id = p.id
        WHERE spi.user_id = ?
        ORDER BY p.id ASC
        LIMIT 1
    ");
    $s->execute([$uid]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getAnalytics(PDO $pdo, int $pid) {
    $s = $pdo->prepare("SELECT * FROM programme_analytics WHERE programme_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$pid]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function getRevenueRow(PDO $pdo, int $uid, int $pid) {
    $s = $pdo->prepare("SELECT id, developer_percentage FROM programme_investors WHERE developerid = ? AND programme_id = ? AND investorid = 0 LIMIT 1");
    $s->execute([$uid, $pid]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function getInvestors(PDO $pdo, int $uid, int $pid) {
    $s = $pdo->prepare("
        SELECT pi.id, pi.investorid, pi.invested_at, h.fullname, h.email, h.broker_balance, h.profitandloss
        FROM programme_investors pi
        LEFT JOIN harvhub h ON h.id = pi.investorid
        WHERE pi.developerid = ? AND pi.programme_id = ? AND pi.investorid > 0
        ORDER BY pi.invested_at DESC
    ");
    $s->execute([$uid, $pid]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function getTrades(PDO $pdo, int $uid, int $pid) {
    $s = $pdo->prepare("SELECT * FROM programme_trades WHERE userid = ? AND programmeid = ? ORDER BY created_at DESC LIMIT 200");
    $s->execute([$uid, $pid]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
function parseList($str) {
    if (!is_string($str) || trim($str) === '') return [];
    $str = trim($str);
    if ($str[0] === '[') {
        $d = json_decode($str, true);
        if (is_array($d)) return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $d)))));
    }
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $str)))));
}
function getSymbolsAndTfs(PDO $pdo, int $uid) {
    $out = ['symbols' => [], 'timeframes' => []];
    try {
        $s = $pdo->prepare("SELECT symbol, selected_timeframes FROM broker_symbols WHERE userid = ? AND symbol_selected = 1 AND symbol <> '__GLOBAL_TF__' ORDER BY symbol ASC");
        $s->execute([$uid]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $out['symbols'][] = $r['symbol'];
            if (empty($out['timeframes'])) $out['timeframes'] = parseList($r['selected_timeframes'] ?? '');
        }
    } catch (PDOException $e) {}
    return $out;
}
function getServerBrokers(PDO $pdo) {
    $brokers = [];
    try {
        $s = $pdo->query("SELECT brokers FROM server_account LIMIT 1");
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            foreach (explode(',', $row['brokers'] ?? '') as $entry) {
                $entry = trim($entry);
                if ($entry === '') continue;
                $name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
                $name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
                if ($name !== '') $brokers[] = ucfirst($name);
            }
            $brokers = array_values(array_unique($brokers));
            sort($brokers);
        }
    } catch (PDOException $e) {}
    return $brokers;
}
function explainInterest(array $interest, array $PERIOD_LABEL, string $FIXED): array {
    $p = $interest['period'];
    $label = $PERIOD_LABEL[$p] ?? ucfirst($p);
    $lines = [];
    if ($interest['risk_reward_type'] === $FIXED) {
        $lines[] = "Ensure your trades use a fixed risk reward of 1:" . rtrim(rtrim(number_format((float)$interest['risk_reward'], 2), '0'), '.') . " — we'll attach your take profit automatically.";
    } else {
        $lines[] = "Ensure your trades risk reward doesn't go below 1:" . rtrim(rtrim(number_format((float)$interest['risk_reward'], 2), '0'), '.') . ".";
    }
    if (!empty($interest['trades_count'])) {
        $lines[] = "Ensure {$label} Trades count meets at least " . (int)$interest['trades_count'] . " trade(s).";
    }
    if (!empty($interest['expected_win'])) {
        $lines[] = "Ensure you achieve at least " . (int)$interest['expected_win'] . " winning trade(s) each " . $label . " period.";
    }
    if (!empty($interest['expected_consecutive_losses'])) {
        $lines[] = "Ensure you don't exceed " . (int)$interest['expected_consecutive_losses'] . " consecutive losing trades.";
    }
    return $lines;
}

// ==================== DASHBOARD AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ---- LOGOUT ----
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        jres(['success' => true, 'message' => 'Signed out.']);
    }

    try {
        // ---- CREATE SIGNAL: ask for strategy name, create programme, link it in interest ----
        if ($action === 'create_programme') {
            $name = trim($_POST['name'] ?? '');
            if ($name === '' || strlen($name) > 255) jres(['success' => false, 'message' => 'Please enter a valid signal strategy name.']);

            // One signal only — refuse if already linked
            $existing = getProgramme($pdo, $userId);
            if ($existing) jres(['success' => false, 'message' => 'You already have a signal.']);

            $pdo->beginTransaction();

            // Create the signal (programme) with the strategy name — a NEW row, never an existing one
            $ins = $pdo->prepare("INSERT INTO programme (userid, program_name, visibility, advertisement) VALUES (?, ?, 0, 0)");
            $ins->execute([$userId, $name]);
            $programmeId = (int)$pdo->lastInsertId();

            // Register the strategy name + programme link into signal_provider_interest (upsert-safe)
            $interest = getInterest($pdo, $userId);
            if ($interest) {
                $u = $pdo->prepare("UPDATE signal_provider_interest SET signal_strategy_name = ?, programme_id = ? WHERE id = ?");
                $u->execute([$name, $programmeId, (int)$interest['id']]);
            } else {
                // No interest row yet (e.g. provider offer not picked) — create one with safe defaults
                $i = $pdo->prepare("
                    INSERT INTO signal_provider_interest
                        (user_id, signal_strategy_id, signal_strategy_name, programme_id, risk_reward, risk_reward_type, trades_count, expected_win, expected_consecutive_losses, period, begin_test)
                    VALUES (?, NULL, ?, ?, 1.00, ?, 0, 0, 0, 'daily', 0)
                ");
                $i->execute([$userId, $name, $programmeId, $FIXED]);
            }

            $pdo->commit();
            jres(['success' => true, 'message' => 'Your signal was created.']);
        }

        if ($action === 'save_visibility') {
            $prog = getProgramme($pdo, $userId);
            if (!$prog) jres(['success' => false, 'message' => 'No signal found.']);
            $vis = ((int)($_POST['visibility'] ?? 0)) ? 1 : 0;
            $u = $pdo->prepare("UPDATE programme SET visibility = ? WHERE id = ? AND userid = ?");
            $u->execute([$vis, (int)$prog['id'], $userId]);
            jres(['success' => true, 'message' => 'Visibility updated.', 'visibility' => $vis]);
        }

        if ($action === 'save_revenue') {
            $prog = getProgramme($pdo, $userId);
            if (!$prog) jres(['success' => false, 'message' => 'No signal found.']);
            $pct = (float)($_POST['developer_percentage'] ?? -1);
            if ($pct < 0 || $pct > 100) jres(['success' => false, 'message' => 'Enter a percentage between 0 and 100.']);
            $row = getRevenueRow($pdo, $userId, (int)$prog['id']);
            if ($row) {
                $u = $pdo->prepare("UPDATE programme_investors SET developer_percentage = ? WHERE id = ?");
                $u->execute([$pct, (int)$row['id']]);
            } else {
                $i = $pdo->prepare("INSERT INTO programme_investors (investorid, developerid, programme_id, developer_percentage) VALUES (0, ?, ?, ?)");
                $i->execute([$userId, (int)$prog['id'], $pct]);
            }
            jres(['success' => true, 'message' => 'Revenue share saved.', 'value' => $pct]);
        }

        if ($action === 'select_broker') {
            $interest = getInterest($pdo, $userId);
            if (!$interest) jres(['success' => false, 'message' => 'No provider interest found.']);
            $broker = trim($_POST['broker'] ?? '');
            $allowed = getServerBrokers($pdo);
            if (!in_array($broker, $allowed, true)) jres(['success' => false, 'message' => 'Invalid broker selected.']);
            $u = $pdo->prepare("UPDATE signal_provider_interest SET broker = ? WHERE id = ?");
            $u->execute([$broker, (int)$interest['id']]);
            jres(['success' => true, 'message' => 'Broker saved.']);
        }

        if ($action === 'begin_test') {
            $interest = getInterest($pdo, $userId);
            if (!$interest) jres(['success' => false, 'message' => 'No provider interest found.']);
            if (empty($interest['broker'])) jres(['success' => false, 'message' => 'Please select a broker first.']);
            if ((int)$interest['begin_test'] === 1) jres(['success' => false, 'message' => 'Testing already started.']);
            $u = $pdo->prepare("UPDATE signal_provider_interest SET begin_test = 1, signal_testing_started_at = NOW() WHERE id = ?");
            $u->execute([(int)$interest['id']]);
            jres(['success' => true, 'message' => 'Your demo testing has begun. Good luck!']);
        }

        if ($action === 'submit_trade') {
            $prog = getProgramme($pdo, $userId);
            $interest = getInterest($pdo, $userId);
            if (!$prog)    jres(['success' => false, 'message' => 'No signal found.']);
            if (!$interest || (int)$interest['begin_test'] !== 1) jres(['success' => false, 'message' => 'Begin your test before submitting trades.']);

            $symbol    = trim($_POST['symbol'] ?? '');
            $timeframe = trim($_POST['timeframe'] ?? '');
            $entry     = (float)($_POST['entry'] ?? 0);
            $exit      = (float)($_POST['exit'] ?? 0);

            if ($symbol === '' || $timeframe === '') jres(['success' => false, 'message' => 'Symbol and timeframe are required.']);
            if ($entry <= 0 || $exit <= 0) jres(['success' => false, 'message' => 'Entry and stoploss must be valid prices.']);
            if (abs($entry - $exit) < 0.0000000001) jres(['success' => false, 'message' => 'Stoploss cannot equal entry.']);

            $rr = (float)$interest['risk_reward'];
            $distance = abs($entry - $exit);
            $isBuy = $entry > $exit;

            if ($interest['risk_reward_type'] === $FIXED) {
                $target = $isBuy ? ($entry + $distance * $rr) : ($entry - $distance * $rr);
            } else {
                $target = (float)($_POST['target'] ?? 0);
                if ($target <= 0) jres(['success' => false, 'message' => 'Take profit is required.']);
                $reward = abs($target - $entry);
                $actualRr = $reward / $distance;
                if ($actualRr < $rr - 0.0001) jres(['success' => false, 'message' => 'Take profit must give at least 1:' . $rr . ' risk reward.']);
                $rr = round($actualRr, 2);
            }

            $ins = $pdo->prepare("
                INSERT INTO programme_trades (userid, programmeid, symbol, timeframe, entry, exit, target, risk_reward, trade_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([$userId, (int)$prog['id'], $symbol, $timeframe, $entry, $exit, $target, $rr]);
            jres(['success' => true, 'message' => 'Trade submitted.', 'target' => round($target, 5)]);
        }

        jres(['success' => false, 'message' => 'Unknown action.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jres(['success' => false, 'message' => 'Something went wrong. Please try again.']);
    }
}

// ==================== PAGE DATA ====================
$interest    = getInterest($pdo, $userId);
$programme   = getProgramme($pdo, $userId);
$analytics   = $programme ? getAnalytics($pdo, (int)$programme['id']) : null;
$revenueRow  = $programme ? getRevenueRow($pdo, $userId, (int)$programme['id']) : null;
$investors   = $programme ? getInvestors($pdo, $userId, (int)$programme['id']) : [];
$trades      = $programme ? getTrades($pdo, $userId, (int)$programme['id']) : [];
$symTf       = getSymbolsAndTfs($pdo, $userId);
$brokers     = getServerBrokers($pdo);

$published   = $programme && (int)$programme['advertisement'] === 1;
$isPublic    = $programme && (int)$programme['visibility'] === 1;
$beginTest   = $interest && (int)$interest['begin_test'] === 1;
$explainLines = $interest ? explainInterest($interest, $PERIOD_LABEL, $FIXED) : [];

// Page heading shows the signal (programme) name once it exists
$pageHeading = $programme ? ($programme['program_name'] ?: 'My Signal') : 'Signal Provider Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= esc_h($pageHeading) ?> — HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{
  --bg:#f4f8f7;--card:#fff;--text:#12211d;--muted:#5f736d;--soft:#8ea19b;
  --accent:#12a36b;--accent2:#0b7d52;--accent-soft:#e3f6ee;--border:#dfe9e5;
  --danger:#e04b4b;--danger-soft:#fdecec;--warn:#d98c1f;--warn-soft:#fdf3e2;
  --shadow:0 8px 26px rgba(18,33,29,.07);--r:16px;
}
body.dark-mode{
  --bg:#0c1311;--card:#131d1a;--text:#e8f2ee;--muted:#93a8a1;--soft:#65786f;
  --accent:#2ecc8f;--accent2:#22a872;--accent-soft:#12271f;--border:#22322d;
  --danger:#ff6b6b;--danger-soft:#2a1414;--warn:#e0a63d;--warn-soft:#2a2113;
  --shadow:0 8px 26px rgba(0,0,0,.4);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,-apple-system,Roboto,sans-serif;background:var(--bg);color:var(--text);line-height:1.55}
a{color:inherit}
input,select,textarea,button{font-family:inherit;font-size:16px}
.wrap{max-width:980px;margin:0 auto;padding:0 18px}

/* TOP BAR */
.topbar{position:sticky;top:0;z-index:40;background:color-mix(in srgb,var(--bg) 90%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--border)}
.topbar .wrap{display:flex;align-items:center;justify-content:space-between;height:60px}
.logo{display:flex;align-items:center;gap:9px;font-weight:800;text-decoration:none}
.logo i{width:32px;height:32px;border-radius:9px;background:var(--accent);color:#fff;display:grid;place-items:center;font-style:normal}
.user-chip{display:flex;align-items:center;gap:10px;font-size:.85rem;color:var(--muted)}
.user-chip .av{width:32px;height:32px;border-radius:50%;background:var(--accent-soft);color:var(--accent);display:grid;place-items:center;font-weight:800}
.logout-btn{display:inline-flex;align-items:center;gap:7px;background:transparent;border:1px solid var(--border);color:var(--muted);border-radius:10px;padding:7px 13px;font-weight:700;font-size:.78rem;cursor:pointer;transition:.15s}
.logout-btn:hover{border-color:var(--danger);color:var(--danger)}

.page{padding:26px 0 60px}
.page-head{margin-bottom:20px}
.page-head h1{font-size:1.5rem;letter-spacing:-.5px;word-break:break-word}
.page-head p{color:var(--muted);font-size:.9rem;margin-top:2px}

/* NOTICE */
.notice{border-radius:14px;padding:14px 16px;margin-bottom:18px;font-size:.88rem;line-height:1.6;display:flex;gap:12px;align-items:flex-start}
.notice i{margin-top:2px}
.notice.info{background:var(--accent-soft);border-left:4px solid var(--accent)}
.notice.warn{background:var(--warn-soft);border-left:4px solid var(--warn)}
.notice.danger{background:var(--danger-soft);border-left:4px solid var(--danger)}
.notice .btn{margin-left:auto;flex-shrink:0}

/* TABS */
.tabs{display:flex;gap:6px;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:6px;margin-bottom:20px;overflow-x:auto}
.tab{flex:1;min-width:110px;background:transparent;border:none;padding:10px 12px;border-radius:10px;font-weight:700;font-size:.84rem;color:var(--muted);cursor:pointer;white-space:nowrap}
.tab.active{background:var(--accent);color:#fff}
.panel{display:none}
.panel.active{display:block}

/* CARD */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-bottom:18px;box-shadow:var(--shadow)}
.card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.card h2{font-size:1.02rem}
.card-sub{color:var(--muted);font-size:.83rem;margin-top:2px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;border-radius:11px;padding:10px 18px;font-weight:700;font-size:.85rem;cursor:pointer;transition:.15s}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent2)}
.btn-ghost{background:transparent;color:var(--text);border:1px solid var(--border)}.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.btn-danger{background:var(--danger);color:#fff}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-block{width:100%}

/* GRIDS */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px}
.stat{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:14px}
.stat .l{font-size:.68rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:6px}
.stat .v{font-size:1.2rem;font-weight:800}
.stat .v.pos{color:var(--accent)}.stat .v.neg{color:var(--danger)}

.empty{text-align:center;padding:34px 16px;color:var(--muted)}
.empty i{font-size:1.8rem;opacity:.4;margin-bottom:8px;display:block}

/* CRITERIA */
.crit-list{display:flex;flex-direction:column;gap:10px}
.crit-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:12px;font-size:.86rem}
.crit-tag{font-weight:700}
.crit-badge{font-size:.7rem;font-weight:800;padding:3px 10px;border-radius:99px}
.crit-badge.met{background:var(--accent-soft);color:var(--accent)}
.crit-badge.miss{background:var(--danger-soft);color:var(--danger)}
.crit-badge.na{background:var(--border);color:var(--muted)}

/* INVESTORS */
.inv-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:12px;margin-bottom:8px}
.inv-name{font-weight:700;font-size:.9rem}
.inv-stats{display:flex;gap:16px}
.inv-stat{text-align:right}
.inv-stat .l{font-size:.62rem;text-transform:uppercase;color:var(--muted);font-weight:700}
.inv-stat .v{font-weight:800;font-size:.88rem}

/* REVENUE */
.rev-box{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-radius:14px;padding:20px}
.rev-box .amt{font-size:1.8rem;font-weight:800}
.rev-box .lbl{font-size:.78rem;opacity:.85;margin-top:2px}
.rev-box button{background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.35);border-radius:11px;padding:10px 18px;font-weight:700;font-size:.85rem;cursor:pointer}
.rev-box button:hover{background:rgba(255,255,255,.28)}

/* TRADES TABLE */
.tlist{display:flex;flex-direction:column;gap:8px}
.trow{display:grid;grid-template-columns:1fr 1fr 1fr 1fr auto;gap:8px;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:12px;font-size:.82rem;align-items:center}
.trow .l{font-size:.62rem;text-transform:uppercase;color:var(--muted);font-weight:700}
.trow .v{font-weight:700}
.status{font-size:.7rem;font-weight:800;padding:4px 10px;border-radius:99px;text-transform:capitalize;text-align:center}
.status.pending{background:var(--warn-soft);color:var(--warn)}
.status.won,.status.win{background:var(--accent-soft);color:var(--accent)}
.status.lost,.status.loss{background:var(--danger-soft);color:var(--danger)}
@media(max-width:700px){.trow{grid-template-columns:1fr 1fr}}

/* MODALS */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:999;align-items:center;justify-content:center;padding:18px}
.modal.open{display:flex}
.mc{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:26px;max-width:460px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.mc h3{font-size:1.15rem;margin-bottom:6px;text-align:center}
.mc .sub{color:var(--muted);text-align:center;font-size:.86rem;margin-bottom:18px}
.field{margin-bottom:14px}
.field label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.4px;font-weight:700;color:var(--muted);margin-bottom:6px}
.field input,.field select{width:100%;padding:11px 13px;border-radius:11px;border:1px solid var(--border);background:var(--bg);color:var(--text)}
.field input:focus,.field select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-soft)}
.field input[readonly]{background:var(--accent-soft);color:var(--accent);font-weight:700;border-color:var(--accent)}
.hint{font-size:.75rem;color:var(--soft);margin-top:4px}
.err{background:var(--danger-soft);border-left:3px solid var(--danger);color:var(--danger);padding:10px 12px;border-radius:10px;font-size:.84rem;margin-bottom:12px;display:none}
.stack{display:flex;flex-direction:column;gap:10px}
.broker-list{display:flex;flex-direction:column;gap:8px;max-height:260px;overflow-y:auto;margin-bottom:16px}
.broker-item{display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;cursor:pointer;font-weight:700;font-size:.9rem}
.broker-item.sel{border-color:var(--accent);background:var(--accent-soft);color:var(--accent)}
.broker-item input{width:auto}
.explain-list{display:flex;flex-direction:column;gap:10px;margin-bottom:18px}
.explain-item{display:flex;gap:10px;background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:12px 14px;font-size:.85rem}
.explain-item i{color:var(--accent);margin-top:2px}
.note-box{background:var(--warn-soft);border-left:4px solid var(--warn);border-radius:10px;padding:10px 12px;font-size:.82rem;color:var(--text);margin-top:10px}
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="topbar"><div class="wrap">
  <a href="signals_provider.php" class="logo"><i>H</i> HarvHub Signals</a>
  <div class="user-chip">
    <div class="av"><?= esc_h(strtoupper(substr($fullName,0,1) ?: 'U')) ?></div>
    <span><?= esc_h($fullName) ?></span>
    <button type="button" class="logout-btn" id="logoutBtn" title="Sign out">
      Sign out <i class="fa-solid fa-arrow-right-from-bracket"></i>
    </button>
  </div>
</div></div>

<div class="page"><div class="wrap">
  <div class="page-head">
    <h1><?= esc_h($pageHeading) ?></h1>
    <p><?= $programme ? 'Manage your signal, track testing progress and view your investors.' : 'Name your signal strategy to get started.' ?></p>
  </div>

  <?php if (!$interest || ((int)($interest['signal_strategy_id'] ?? 0) === 0 && empty($interest['risk_reward_type']))): ?>
    <div class="notice warn">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <div>You haven't selected a provider offer yet. Head to the <a href="signals_provider.php" style="color:var(--accent);font-weight:700">signal provider page</a> to choose your risk reward, trades count and expected win.</div>
    </div>
  <?php endif; ?>

  <?php if (!$programme): ?>
    <div class="card">
      <div class="card-head"><div><h2>Create Your Signal</h2><p class="card-sub">Give your signal a strategy name. You can only create one signal.</p></div></div>
      <div id="createErr" class="err"></div>
      <div class="field"><label>Signal Strategy Name</label><input id="progName" placeholder="e.g. Gold Momentum Pro" maxlength="255"></div>
      <button class="btn btn-primary btn-block" id="createBtn">Create Signal</button>
    </div>
  <?php endif; ?>

  <?php if ($programme): ?>

    <!-- STATUS DISCLAIMER -->
    <?php if ($published): ?>
      <div class="notice info">
        <i class="fa-solid fa-circle-check"></i>
        <div><strong>Your signal is now live!</strong> You can only see this<?= $isPublic ? '' : ', change settings if you want investors to find you' ?>.</div>
        <button class="btn btn-ghost" onclick="openM('visModal')">Change Settings</button>
      </div>
    <?php else: ?>
      <div class="notice warn">
        <i class="fa-solid fa-hourglass-half"></i>
        <div>
          <?php if ((int)$programme['advertisement'] === 0 && (int)$programme['visibility'] === 1): ?>
            Your signal is currently private. The server has not approved advertisement yet — visibility settings only take effect once approved.
          <?php else: ?>
            Your signal is private. It will go live once our server reviews and approves your testing results.
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
      <button class="tab active" data-t="overview">Overview</button>
      <button class="tab" data-t="criteria">Criteria Status</button>
      <button class="tab" data-t="trades">Signals Dropped</button>
    </div>

    <!-- OVERVIEW -->
    <div class="panel active" id="p-overview">

      <div class="card">
        <div class="card-head"><div><h2><?= esc_h($programme['program_name']) ?></h2>
          <p class="card-sub"><?= $published ? 'Published' : 'Private' ?> · <?= $isPublic ? 'Public visibility' : 'Private visibility' ?></p></div></div>

        <?php if (!$interest): ?>
          <div class="empty"><i class="fa-solid fa-clipboard-question"></i><p>No provider criteria on file yet.</p></div>
        <?php elseif (!$beginTest): ?>
          <p style="font-size:.88rem;color:var(--muted);margin-bottom:14px">
            Before your signal can be published, we need to verify your trading on a demo account. Reports update on this dashboard every 24 hours for one month.
          </p>
          <button class="btn btn-primary" id="imReadyBtn">I'm Ready</button>
        <?php else: ?>
          <p style="font-size:.85rem;color:var(--muted);margin-bottom:10px">
            Testing started <?= $interest['signal_testing_started_at'] ? esc_h(date('M j, Y g:ia', strtotime($interest['signal_testing_started_at']))) : '—' ?>.
            Broker: <strong><?= esc_h($interest['broker'] ?: '—') ?></strong>.
          </p>
          <button class="btn btn-primary" id="submitTradeBtn">Submit New Trade</button>
        <?php endif; ?>
      </div>

      <!-- ANALYTICS -->
      <div class="card">
        <div class="card-head"><div><h2>Performance Analytics</h2><p class="card-sub">Server-generated results from your demo test.</p></div></div>
        <?php if (!$analytics): ?>
          <div class="empty">
            <i class="fa-solid fa-chart-line"></i>
            <p><strong>No analytics yet.</strong> You're either new, on a break, or haven't started testing.<br>
            Before publishing, the server tests your trades on a demo account, updating this dashboard every 24 hours for one month so your record can be trusted.</p>
          </div>
        <?php else: ?>
          <div class="stat-grid">
            <div class="stat"><div class="l">Win Rate</div><div class="v pos"><?= esc_h(number_format((float)$analytics['winrate'],2)) ?>%</div></div>
            <div class="stat"><div class="l">Loss Rate</div><div class="v neg"><?= esc_h(number_format((float)$analytics['lossrate'],2)) ?>%</div></div>
            <div class="stat"><div class="l">Risk Reward</div><div class="v">1:<?= esc_h(number_format((float)$analytics['risk_reward'],2)) ?></div></div>
            <div class="stat"><div class="l">Consecutive Losses</div><div class="v"><?= (int)$analytics['consecutive_sequential_loss'] ?></div></div>
            <div class="stat"><div class="l">Losing Days</div><div class="v"><?= (int)$analytics['consecutive_losing_days'] ?></div></div>
            <div class="stat"><div class="l">Highest Drawdown</div><div class="v neg"><?= esc_h(number_format((float)$analytics['highest_drawdown'],2)) ?></div></div>
            <div class="stat"><div class="l">Won Trades</div><div class="v pos"><?= (int)$analytics['won_trades'] ?></div></div>
            <div class="stat"><div class="l">Lost Trades</div><div class="v neg"><?= (int)$analytics['lost_trades'] ?></div></div>
          </div>
        <?php endif; ?>
      </div>

      <!-- INVESTORS -->
      <div class="card">
        <div class="card-head"><div><h2>Signal Investors</h2><p class="card-sub">Investors currently following your signal.</p></div></div>
        <?php if (empty($investors)): ?>
          <div class="empty"><i class="fa-solid fa-user-group"></i><p>No investors yet.</p></div>
        <?php else: foreach ($investors as $inv):
          $name = $inv['fullname'] ?: ($inv['email'] ?: 'Anonymous');
          $bal  = (float)($inv['broker_balance'] ?? 0);
          $pnl  = (float)($inv['profitandloss'] ?? 0);
        ?>
          <div class="inv-row">
            <div class="inv-name"><?= esc_h($name) ?></div>
            <div class="inv-stats">
              <div class="inv-stat"><div class="l">Invested</div><div class="v">$<?= esc_h(number_format($bal,2)) ?></div></div>
              <div class="inv-stat"><div class="l">PnL</div><div class="v" style="color:<?= $pnl>=0?'var(--accent)':'var(--danger)' ?>">$<?= esc_h(number_format($pnl,2)) ?></div></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- REVENUE -->
      <div class="card">
        <div class="card-head"><div><h2>Revenue</h2><p class="card-sub">Your share of signal revenue from investors.</p></div></div>
        <div class="rev-box">
          <div>
            <div class="amt" id="revAmt"><?= $revenueRow && $revenueRow['developer_percentage'] !== null ? '$' . esc_h(number_format((float)$revenueRow['developer_percentage'],2)) . '%' : 'No value set yet' ?></div>
            <div class="lbl">Percentage Share From Investors Revenue</div>
          </div>
          <button onclick="openM('revModal')">Settings</button>
        </div>
      </div>
    </div>

    <!-- CRITERIA STATUS -->
    <div class="panel" id="p-criteria">
      <div class="card">
        <div class="card-head"><div><h2>Criteria Status</h2><p class="card-sub">How your analytics compare to the offer you selected.</p></div></div>
        <?php if (!$interest): ?>
          <div class="empty"><i class="fa-solid fa-clipboard-question"></i><p>No criteria on file.</p></div>
        <?php else: ?>
          <div class="crit-list">
            <?php
              $rows = [
                ['Risk Reward', $interest['risk_reward_type'] === $FIXED ? 'Fixed 1:' . $interest['risk_reward'] : 'Min 1:' . $interest['risk_reward'],
                  $analytics ? ((float)$analytics['risk_reward'] >= (float)$interest['risk_reward']) : null],
                ['Consecutive Loss Limit', (int)$interest['expected_consecutive_losses'] . ' max',
                  $analytics ? ((int)$analytics['consecutive_sequential_loss'] <= (int)$interest['expected_consecutive_losses']) : null],
                [$PERIOD_LABEL[$interest['period']] . ' Trades Count', 'At least ' . (int)$interest['trades_count'],
                  $analytics ? (((int)$analytics['won_trades'] + (int)$analytics['lost_trades']) >= (int)$interest['trades_count']) : null],
                ['Expected Win', 'At least ' . (int)$interest['expected_win'],
                  $analytics ? ((int)$analytics['won_trades'] >= (int)$interest['expected_win']) : null],
              ];
              foreach ($rows as $r):
                list($tag, $target, $met) = $r;
                $badge = $met === null ? ['na','No data yet'] : ($met ? ['met','Met'] : ['miss','Not met']);
            ?>
              <div class="crit-row">
                <div><div class="crit-tag"><?= esc_h($tag) ?></div><div style="color:var(--muted);font-size:.8rem"><?= esc_h($target) ?></div></div>
                <span class="crit-badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- SIGNALS DROPPED -->
    <div class="panel" id="p-trades">
      <div class="card">
        <div class="card-head"><div><h2>Signals Dropped</h2><p class="card-sub">All trades you've submitted for this signal.</p></div></div>
        <?php if (empty($trades)): ?>
          <div class="empty"><i class="fa-solid fa-chart-simple"></i><p>No signals submitted yet.</p></div>
        <?php else: ?>
          <div class="tlist">
            <?php foreach ($trades as $t): ?>
              <div class="trow">
                <div><div class="l">Symbol</div><div class="v"><?= esc_h($t['symbol']) ?> · <?= esc_h($t['timeframe']) ?></div></div>
                <div><div class="l">Entry</div><div class="v"><?= esc_h(rtrim(rtrim(number_format((float)$t['entry'],5),'0'),'.')) ?></div></div>
                <div><div class="l">Stoploss / TP</div><div class="v"><?= esc_h(rtrim(rtrim(number_format((float)$t['exit'],5),'0'),'.')) ?> / <?= $t['target'] !== null ? esc_h(rtrim(rtrim(number_format((float)$t['target'],5),'0'),'.')) : '—' ?></div></div>
                <div><div class="l">Risk Reward</div><div class="v">1:<?= esc_h(number_format((float)$t['risk_reward'],2)) ?></div></div>
                <div class="status <?= esc_h(strtolower($t['trade_status'])) ?>"><?= esc_h($t['trade_status']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div></div>

<!-- VISIBILITY MODAL -->
<div class="modal" id="visModal"><div class="mc">
  <h3>Signal Settings</h3><p class="sub">Choose whether investors can find your signal.</p>
  <div class="field"><label>Visibility</label>
    <select id="visSelect">
      <option value="1" <?= $isPublic ? 'selected' : '' ?>>Public — investors can find me</option>
      <option value="0" <?= !$isPublic ? 'selected' : '' ?>>Private — hidden from investors</option>
    </select>
  </div>
  <div class="stack"><button class="btn btn-primary btn-block" id="saveVisBtn">Save</button><button class="btn btn-ghost btn-block" data-close="visModal">Cancel</button></div>
</div></div>

<!-- REVENUE MODAL -->
<div class="modal" id="revModal"><div class="mc">
  <h3>Revenue Settings</h3><p class="sub">Set your signal provider percentage share.</p>
  <div id="revErr" class="err"></div>
  <div class="field"><label>Signal Provider Percentage (%)</label>
    <input type="number" id="revInput" min="0" max="100" step="0.01" value="<?= $revenueRow && $revenueRow['developer_percentage'] !== null ? esc_h($revenueRow['developer_percentage']) : '' ?>" placeholder="e.g. 20">
  </div>
  <div class="stack"><button class="btn btn-primary btn-block" id="saveRevBtn">Save</button><button class="btn btn-ghost btn-block" data-close="revModal">Cancel</button></div>
</div></div>

<!-- BROKER SELECT MODAL -->
<div class="modal" id="brokerModal"><div class="mc">
  <h3>Select a Broker</h3><p class="sub">Choose the broker you want to provide analysis from.</p>
  <div id="brokerErr" class="err"></div>
  <div class="broker-list" id="brokerList">
    <?php foreach ($brokers as $b): ?>
      <label class="broker-item"><input type="radio" name="brk" value="<?= esc_h($b) ?>"> <?= esc_h($b) ?></label>
    <?php endforeach; ?>
    <?php if (empty($brokers)): ?><div class="empty"><p>No brokers configured.</p></div><?php endif; ?>
  </div>
  <div class="stack"><button class="btn btn-primary btn-block" id="confirmBrokerBtn">Confirm</button><button class="btn btn-ghost btn-block" data-close="brokerModal">Cancel</button></div>
</div></div>

<!-- EXPLANATION / CONFIRM MODAL -->
<div class="modal" id="explainModal"><div class="mc">
  <h3>Before You Begin</h3><p class="sub">Once confirmed, you must ensure the following throughout your test:</p>
  <div class="explain-list">
    <?php foreach ($explainLines as $line): ?>
      <div class="explain-item"><i class="fa-solid fa-circle-check"></i><span><?= esc_h($line) ?></span></div>
    <?php endforeach; ?>
  </div>
  <div class="stack"><button class="btn btn-primary btn-block" id="yesBeginBtn">Yes, Begin Testing</button><button class="btn btn-ghost btn-block" data-close="explainModal">Not Yet</button></div>
</div></div>

<!-- SUBMIT TRADE MODAL -->
<div class="modal" id="tradeModal"><div class="mc">
  <h3>Submit New Trade</h3><p class="sub">Drop a signal for this test period.</p>
  <div id="tradeErr" class="err"></div>
  <div class="field"><label>Symbol</label>
    <select id="tSymbol">
      <option value="">-- Select Symbol --</option>
      <?php foreach ($symTf['symbols'] as $s): ?><option value="<?= esc_h($s) ?>"><?= esc_h($s) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Timeframe</label>
    <select id="tTimeframe">
      <option value="">-- Select Timeframe --</option>
      <?php foreach ($symTf['timeframes'] as $tf): ?><option value="<?= esc_h($tf) ?>"><?= esc_h($tf) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Entry</label><input type="number" step="any" id="tEntry" placeholder="Entry price"></div>
  <div class="field"><label>Stoploss</label><input type="number" step="any" id="tExit" placeholder="Stoploss price"></div>

  <?php if ($interest && $interest['risk_reward_type'] === $CUSTOM): ?>
    <div class="field"><label>Take Profit</label><input type="number" step="any" id="tTarget" placeholder="Take profit price"></div>
    <div class="field"><label>Minimum Risk Reward</label><input type="text" readonly value="1:<?= esc_h($interest['risk_reward']) ?>"></div>
  <?php elseif ($interest): ?>
    <div class="field"><label>Fixed Risk Reward</label><input type="text" readonly value="1:<?= esc_h($interest['risk_reward']) ?>"></div>
    <div class="note-box"><i class="fa-solid fa-circle-info"></i> Don't worry — we will attach the take profit price using your fixed risk reward.</div>
  <?php endif; ?>

  <div class="stack" style="margin-top:14px"><button class="btn btn-primary btn-block" id="submitTradeConfirmBtn">Submit Trade</button><button class="btn btn-ghost btn-block" data-close="tradeModal">Cancel</button></div>
</div></div>

<!-- ALERT -->
<div class="modal" id="alertModal"><div class="mc">
  <h3 id="alertTitle">Notice</h3><p class="sub" id="alertText"></p>
  <button class="btn btn-primary btn-block" id="alertOk">OK</button>
</div></div>

<script>
function $(id){return document.getElementById(id)}
function openM(id){$(id).classList.add('open')}
function closeM(id){$(id).classList.remove('open')}
document.querySelectorAll('[data-close]').forEach(function(b){b.onclick=function(){closeM(b.dataset.close)}});
function alertBox(title,text,cb){$('alertTitle').textContent=title;$('alertText').textContent=text;openM('alertModal');$('alertOk').onclick=function(){closeM('alertModal');if(cb)cb();};}

function post(data,cb,btn){
  var body=Object.keys(data).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(data[k])}).join('&');
  if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Please wait...';}
  fetch('signals_dashboard.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
   .then(function(r){return r.json()})
   .then(function(d){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb(d)})
   .catch(function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}cb({success:false,message:'Network error.'})});
}

/* LOGOUT */
if ($('logoutBtn')) $('logoutBtn').onclick=function(){
  post({action:'logout'},function(){
    window.location.href='signals_provider.php';
  },$('logoutBtn'));
};

/* TABS */
document.querySelectorAll('.tab').forEach(function(t){t.onclick=function(){
  document.querySelectorAll('.tab').forEach(function(x){x.classList.toggle('active',x===t)});
  document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('active')});
  $('p-'+t.dataset.t).classList.add('active');
}});

/* CREATE SIGNAL */
if ($('createBtn')) $('createBtn').onclick=function(){
  var name=$('progName').value.trim(), er=$('createErr');er.style.display='none';
  if(!name){er.textContent='Please enter a signal strategy name.';er.style.display='block';return;}
  post({action:'create_programme',name:name},function(d){
    if(!d.success){er.textContent=d.message;er.style.display='block';return;}
    location.reload();
  },$('createBtn'));
};

/* VISIBILITY */
if ($('saveVisBtn')) $('saveVisBtn').onclick=function(){
  post({action:'save_visibility',visibility:$('visSelect').value},function(d){
    if(!d.success){alertBox('Error',d.message);return;}
    closeM('visModal');location.reload();
  },$('saveVisBtn'));
};

/* REVENUE */
if ($('saveRevBtn')) $('saveRevBtn').onclick=function(){
  var er=$('revErr');er.style.display='none';
  var v=$('revInput').value;
  post({action:'save_revenue',developer_percentage:v},function(d){
    if(!d.success){er.textContent=d.message;er.style.display='block';return;}
    $('revAmt').textContent='$'+parseFloat(d.value).toFixed(2)+'%';
    closeM('revModal');
  },$('saveRevBtn'));
};

/* I'M READY -> BROKER -> EXPLAIN -> BEGIN */
if ($('imReadyBtn')) $('imReadyBtn').onclick=function(){ openM('brokerModal'); };

if ($('brokerList')) $('brokerList').addEventListener('click', function(e){
  var item = e.target.closest('.broker-item'); if(!item) return;
  document.querySelectorAll('.broker-item').forEach(function(b){b.classList.remove('sel')});
  item.classList.add('sel');
  item.querySelector('input').checked = true;
});

if ($('confirmBrokerBtn')) $('confirmBrokerBtn').onclick=function(){
  var sel = document.querySelector('input[name="brk"]:checked'), er=$('brokerErr');er.style.display='none';
  if(!sel){er.textContent='Please select a broker.';er.style.display='block';return;}
  post({action:'select_broker',broker:sel.value},function(d){
    if(!d.success){er.textContent=d.message;er.style.display='block';return;}
    closeM('brokerModal');openM('explainModal');
  },$('confirmBrokerBtn'));
};

if ($('yesBeginBtn')) $('yesBeginBtn').onclick=function(){
  post({action:'begin_test'},function(d){
    if(!d.success){alertBox('Error',d.message);return;}
    closeM('explainModal');
    alertBox('Testing Started',d.message,function(){location.reload();});
  },$('yesBeginBtn'));
};

/* SUBMIT TRADE */
if ($('submitTradeBtn')) $('submitTradeBtn').onclick=function(){ openM('tradeModal'); };
if ($('submitTradeConfirmBtn')) $('submitTradeConfirmBtn').onclick=function(){
  var er=$('tradeErr');er.style.display='none';
  var data={
    action:'submit_trade',
    symbol:$('tSymbol').value,
    timeframe:$('tTimeframe').value,
    entry:$('tEntry').value,
    exit:$('tExit').value
  };
  var tTarget=$('tTarget');
  if(tTarget) data.target=tTarget.value;
  if(!data.symbol||!data.timeframe){er.textContent='Please select symbol and timeframe.';er.style.display='block';return;}
  if(!data.entry||!data.exit){er.textContent='Entry and stoploss are required.';er.style.display='block';return;}
  post(data,function(d){
    if(!d.success){er.textContent=d.message;er.style.display='block';return;}
    closeM('tradeModal');
    alertBox('Trade Submitted',d.message,function(){location.reload();});
  },$('submitTradeConfirmBtn'));
};

document.querySelectorAll('.modal').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m&&m.id!=='alertModal')m.classList.remove('open')})});
</script>
</body>
</html>