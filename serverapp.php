<?php
/**
 * serverapp.php — Server Administration shell.
 * Only shell authentication, navigation, table-backed theme preference and logout live here.
 * Feature queries and POST endpoints stay in their individual modules.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Keep the administrator session persistent; only the explicit Sign out action destroys it.
    ini_set('session.gc_maxlifetime', '315360000');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 315360000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');

try {
    require_once __DIR__ . '/mysqlconnection.php';
    if (!function_exists('getAvailablePdoConnection')) throw new RuntimeException('Database connection helper is unavailable.');
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) throw new RuntimeException('Database connection is unavailable.');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    require_once __DIR__ . '/serverapp_schema.php';
    serverAppEnsureConfigurationSchema($pdo);
    $serverAccount = $pdo->query('SELECT dark_mode FROM server_theme_settings WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: ['dark_mode'=>0];
    // An empty credentials table is a valid first-run state: the shell shows the one-time setup form.
    $serverAdminCredentials = $pdo->query('SELECT admin_login_id,admin_password_hash FROM server_admin_credentials WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: ['admin_login_id'=>'','admin_password_hash'=>''];
} catch (Throwable $e) {
    error_log('serverapp.php initialization failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Server administration is temporarily unavailable. Check the database connection and run the table-only configuration migrations.');
}

function serverShellH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function serverShellJson(array $payload, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

// Explicit logout is the only application-level event that ends the admin session.
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: serverapp.php', true, 303);
    exit;
}

$isAjax = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

$initialSetupRequired = empty($serverAdminCredentials['admin_password_hash']);
$loggedIn = !empty($_SESSION['admin_logged_in']);
if ($loggedIn && empty($_SESSION['server_shell_csrf'])) $_SESSION['server_shell_csrf'] = bin2hex(random_bytes(32));
$loginError = (string)($_SESSION['server_shell_error'] ?? '');
$loginPrefill = ''; // Never prefill administrator identifiers.
unset($_SESSION['server_shell_error'], $_SESSION['server_shell_login_id']);

// The shell theme switch is intentionally independent of the configuration tab.
// Its only persistent source is server_theme_settings.dark_mode.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['server_shell_action'] ?? '') === 'theme_toggle') {
    if (empty($_SESSION['admin_logged_in'])) {
        if ($isAjax) serverShellJson(['success'=>false, 'error'=>'Administrator login required.'], 401);
        header('Location: serverapp.php', true, 303);
        exit;
    }
    $sentToken = (string)($_POST['csrf_token'] ?? '');
    if ($sentToken === '' || empty($_SESSION['server_shell_csrf']) || !hash_equals((string)$_SESSION['server_shell_csrf'], $sentToken)) {
        if ($isAjax) serverShellJson(['success'=>false, 'error'=>'The theme request expired. Refresh the page.'], 419);
        http_response_code(419);
        exit('The theme request expired. Refresh the page.');
    }
    $currentMode = (int)($serverAccount['dark_mode'] ?? 0) === 1 ? 1 : 0;
    $nextMode = $currentMode === 1 ? 0 : 1;
    $themeSave = $pdo->prepare('INSERT INTO server_theme_settings (id, dark_mode) VALUES (1, ?) ON DUPLICATE KEY UPDATE dark_mode = VALUES(dark_mode)');
    $themeSave->execute([$nextMode]);
    if ($isAjax) serverShellJson(['success'=>true, 'dark_mode'=>$nextMode]);
    $returnView = (string)($_GET['view'] ?? 'menu');
    header('Location: serverapp.php?view=' . rawurlencode($returnView), true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['server_shell_action'])) {
    $action = (string)$_POST['server_shell_action'];
    if (empty($_SESSION['server_shell_nonce'])) $_SESSION['server_shell_nonce'] = bin2hex(random_bytes(32));
    $postedNonce = (string)($_POST['nonce'] ?? '');
    $submittedLogin = trim((string)($_POST['login_id'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $error = '';

    if ($postedNonce === '' || !hash_equals((string)$_SESSION['server_shell_nonce'], $postedNonce)) {
        $error = 'This sign-in form expired. The page has been refreshed; please submit again.';
    } elseif ($action === 'setup' && $initialSetupRequired) {
        if ($submittedLogin === '' || strlen($password) < 12) {
            $error = 'Choose an administrator login ID and a password with at least 12 characters.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO server_admin_credentials (id,admin_login_id,admin_password_hash) VALUES (1,?,?) ON DUPLICATE KEY UPDATE admin_login_id=VALUES(admin_login_id),admin_password_hash=VALUES(admin_password_hash)');
            $stmt->execute([$submittedLogin, password_hash($password, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_id'] = $submittedLogin;
            $_SESSION['server_shell_nonce'] = bin2hex(random_bytes(32));
            $_SESSION['server_shell_csrf'] = bin2hex(random_bytes(32));
            header('Location: serverapp.php?view=menu', true, 303);
            exit;
        }
    } elseif ($action === 'login' && !$initialSetupRequired) {
        if ($submittedLogin !== '' && hash_equals((string)($serverAdminCredentials['admin_login_id'] ?? ''), $submittedLogin)
            && $password !== '' && password_verify($password, (string)($serverAdminCredentials['admin_password_hash'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_id'] = $submittedLogin;
            $_SESSION['server_shell_nonce'] = bin2hex(random_bytes(32));
            $_SESSION['server_shell_csrf'] = bin2hex(random_bytes(32));
            header('Location: serverapp.php?view=menu', true, 303);
            exit;
        }
        $error = 'Invalid login ID or password.';
    }

    // PRG applies to failed as well as successful submissions. Refreshing never resubmits POST.
    $_SESSION['server_shell_error'] = $error !== '' ? $error : 'Unable to process the sign-in request.';
    unset($_SESSION['server_shell_login_id']);
    $_SESSION['server_shell_nonce'] = bin2hex(random_bytes(32));
    header('Location: serverapp.php?login=1', true, 303);
    exit;
}

$loggedIn = !empty($_SESSION['admin_logged_in']);
if (empty($_SESSION['server_shell_nonce'])) $_SESSION['server_shell_nonce'] = bin2hex(random_bytes(32));
if (empty($_SESSION['server_shell_csrf'])) $_SESSION['server_shell_csrf'] = bin2hex(random_bytes(32));
$darkMode = (int)($serverAccount['dark_mode'] ?? 0) === 1 ? 1 : 0;
$theme = $darkMode ? 'dark' : 'light';

$routes = [
    'configuration' => ['title'=>'Server Configuration','file'=>'server_configuration.php','icon'=>'fa-solid fa-sliders','description'=>'Payment methods, brokers, requirements and tiers'],
    'revenue'       => ['title'=>'Investors Contracts Payments','file'=>'serverapp_revenue.php','icon'=>'fa-solid fa-file-invoice-dollar','description'=>'Investor contract payment records'],
    'volume'        => ['title'=>'HarvHub Volume','file'=>'serverapp_volume.php','icon'=>'fa-solid fa-chart-column','description'=>'Investor and programme contract volume'],
    'vps'           => ['title'=>'VPS Management','file'=>'vps_config.php','icon'=>'fa-solid fa-server','description'=>'VPS plans, requests and allocations'],
    'risk'          => ['title'=>'Risk Dictionary','file'=>'risk_dictionary.php','icon'=>'fa-solid fa-calculator','description'=>'Recovery table generator'],
];
$view = (string)($_GET['view'] ?? 'menu');
if ($view !== 'menu' && !isset($routes[$view])) $view='menu';
$activeTitle = $routes[$view]['title'] ?? 'HarvHub Server Control';
?>
<!doctype html>
<html lang="en" data-theme="<?= serverShellH($theme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= serverShellH($activeTitle) ?> · HarvHub Server Control</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23c99b36'/><path d='M25 72V28h12l13 20 13-20h12v44H63V49L50 67 37 49v23z' fill='%23171008'/></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
:root{color-scheme:light;--page:#fff;--surface:#fff;--surface-2:#fff;--text:#171717;--muted:#77736c;--line:#e9e7e2;--gold:#c99b36;--gold-deep:#a87919;--gold-soft:#fff5dc;--shadow:0 12px 34px rgba(20,18,12,.075);--drawer-width:292px}
html[data-theme="dark"]{color-scheme:dark;--page:#000;--surface:#0a0a0a;--surface-2:#111;--text:#f5f5f5;--muted:#aaa49a;--line:#292929;--gold:#d5aa43;--gold-deep:#edc65f;--gold-soft:#171208;--shadow:0 14px 40px rgba(0,0,0,.5)}
*{box-sizing:border-box;scrollbar-width:none;-ms-overflow-style:none}*::-webkit-scrollbar{display:none;width:0;height:0}
html,body{width:100%;height:100%;margin:0;overflow:hidden;background:var(--page);color:var(--text);font:15px/1.5 Inter,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-tap-highlight-color:transparent}
button,a{font:inherit}button{cursor:pointer}
.shell{width:100%;height:100dvh;min-height:0;display:grid;grid-template-columns:260px minmax(0,1fr);background:var(--page);overflow:hidden}
.sidebar{position:relative;z-index:100;height:100%;min-height:0;display:flex;flex-direction:column;gap:22px;padding:24px 17px 18px;background:var(--surface);border-right:1px solid var(--line);color:var(--text)}
.drawer-brand{padding:5px 12px 12px;font-weight:850;font-size:17px;letter-spacing:.055em;color:var(--text)}.drawer-brand small{display:block;margin-top:2px;color:var(--muted);font-size:10px;font-weight:650;letter-spacing:.19em}
.side-nav{display:grid;gap:7px}.side-nav a{min-height:47px;display:flex;align-items:center;gap:13px;padding:11px 13px;border-radius:13px;border:1px solid transparent;color:var(--text);font-weight:680;text-decoration:none;transition:background .18s,border-color .18s,transform .18s}.side-nav a i{width:21px;text-align:center;color:var(--gold);font-size:16px}.side-nav a:hover{background:var(--gold-soft);border-color:rgba(201,155,54,.28)}.side-nav a.active{background:linear-gradient(135deg,var(--gold),var(--gold-deep));border-color:var(--gold);color:#171008;box-shadow:0 8px 20px rgba(174,126,31,.18)}.side-nav a.active i{color:#171008}.side-nav a:active{transform:scale(.985)}
.drawer-bottom{display:grid;gap:10px;margin-top:auto;padding:13px 4px 3px;border-top:1px solid var(--line)}.drawer-action{width:100%;min-height:45px;display:flex;align-items:center;gap:12px;padding:10px 13px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text);font-weight:650;text-decoration:none}.drawer-action i{width:20px;text-align:center;color:var(--gold)}.drawer-action:hover{border-color:var(--gold);background:var(--gold-soft)}.drawer-action.primary{background:var(--gold);border-color:var(--gold);color:#171008}.drawer-action.primary i{color:#171008}
.app-main{height:100%;min-width:0;min-height:0;display:flex;flex-direction:column;overflow:hidden;background:var(--page)}
.desktop-topbar{flex:0 0 auto;width:min(1500px,calc(100% - 52px));min-height:68px;margin:18px auto 14px;padding:14px 20px;display:flex;align-items:center;gap:14px;border:1px solid var(--line);border-radius:17px;background:var(--surface);box-shadow:var(--shadow)}.desktop-topbar h1{margin:0;font-size:21px;line-height:1.2;letter-spacing:-.03em;font-weight:780}
.mobile-topbar{display:none}
.app-content{width:min(1500px,calc(100% - 52px));min-height:0;min-width:0;flex:1;margin:0 auto 20px;position:relative;overflow:auto;background:var(--page)}
.overview-view{min-height:100%;padding:3px 0 18px}.menu-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:17px}.menu-card{position:relative;min-height:158px;display:flex;flex-direction:column;align-items:flex-start;justify-content:flex-start;gap:12px;padding:23px;border:1px solid var(--gold);border-radius:18px;background:linear-gradient(145deg,var(--gold),var(--gold-deep));color:#171008;text-decoration:none;overflow:hidden;box-shadow:0 11px 26px rgba(174,126,31,.18);transition:transform .2s,box-shadow .2s,filter .2s}.menu-card:before{content:"";position:absolute;inset:0 0 auto;height:3px;background:rgba(255,255,255,.55)}.menu-card:hover{transform:translateY(-3px);box-shadow:0 17px 34px rgba(174,126,31,.25);filter:saturate(1.04)}.menu-card:active{transform:scale(.985)}.menu-card .menu-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;border:1px solid rgba(23,16,8,.2);background:rgba(255,255,255,.23);font-size:20px;color:#171008}.menu-card h2{margin:0;font-size:16px;line-height:1.3;font-weight:800;color:#171008}.menu-card p{margin:-7px 0 0;color:rgba(23,16,8,.78);font-size:12px;line-height:1.45}
.module-view{position:relative;width:100%;height:100%;min-height:0;overflow:hidden;border:1px solid var(--line);border-radius:17px;background:var(--surface);box-shadow:var(--shadow)}.module-frame{position:absolute;inset:0;display:block;width:100%;height:100%;min-height:0;border:0;background:var(--page);visibility:hidden;pointer-events:none;opacity:0}.module-frame.is-active{position:relative;visibility:visible;pointer-events:auto;opacity:1}
.loading-cover{position:absolute;inset:0;z-index:5;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:13px;background:var(--surface);color:var(--muted);font-size:13px;font-weight:650}.loading-cover[hidden]{display:none}.loader-ring{width:38px;height:38px;border:3px solid var(--line);border-top-color:var(--gold);border-right-color:var(--gold);border-radius:50%;animation:serverSpin .75s linear infinite}.loader-bars{display:flex;gap:4px;height:18px;align-items:center}.loader-bars span{width:4px;height:8px;border-radius:4px;background:var(--gold);animation:serverPulse .75s ease-in-out infinite alternate}.loader-bars span:nth-child(2){animation-delay:.12s}.loader-bars span:nth-child(3){animation-delay:.24s}.loader-bars span:nth-child(4){animation-delay:.36s}@keyframes serverSpin{to{transform:rotate(360deg)}}@keyframes serverPulse{to{height:18px;opacity:.5}}
.drawer-scrim{display:none}
.login-wrap{height:100dvh;display:grid;place-items:center;padding:22px;background:var(--page)}.login-card{width:min(440px,100%);padding:30px;background:var(--surface);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow)}.login-card h1{margin:0 0 6px;font-size:25px}.login-card p{margin:0 0 22px;color:var(--muted)}.field{display:grid;gap:7px;margin:15px 0}.field label{font-size:13px;font-weight:700}.field input{width:100%;min-height:46px;padding:12px 14px;border:1px solid var(--line);border-radius:11px;background:var(--surface);color:var(--text);font:inherit}.field input:focus{outline:0;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,155,54,.16)}.btn{width:100%;min-height:46px;border:1px solid var(--gold);border-radius:11px;background:var(--gold);color:#171008;font-weight:780}.btn:hover{background:var(--gold-deep)}.error{padding:11px 13px;border-radius:10px;background:#fff0f0;color:#a42020;margin:0 0 15px}
@media(max-width:800px) and (orientation:portrait){
 .shell{display:flex;flex-direction:column;height:100dvh;min-height:0}
 .sidebar{position:fixed;inset:0 auto 0 0;width:min(var(--drawer-width),86vw);height:100dvh;padding:23px 17px 17px;gap:19px;transform:translateX(-105%);transition:transform .28s cubic-bezier(.2,.75,.25,1);box-shadow:18px 0 48px rgba(0,0,0,.2);border-right:1px solid var(--line);z-index:120}.shell.drawer-open .sidebar{transform:translateX(0)}
 .drawer-scrim{display:block;position:fixed;inset:0;z-index:110;background:rgba(0,0,0,.48);opacity:0;visibility:hidden;transition:opacity .22s,visibility .22s}.shell.drawer-open .drawer-scrim{opacity:1;visibility:visible}
 .mobile-topbar{position:sticky;top:0;z-index:80;flex:0 0 calc(60px + env(safe-area-inset-top));height:calc(60px + env(safe-area-inset-top));min-height:calc(60px + env(safe-area-inset-top));display:flex;align-items:center;gap:14px;padding:env(safe-area-inset-top) 17px 0;border-bottom:1px solid var(--line);background:var(--surface);box-shadow:0 4px 16px rgba(0,0,0,.055)}.mobile-topbar[hidden]{display:none!important}.mobile-topbar .bar-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:16px;font-weight:800;letter-spacing:-.02em}.bar-icon{width:38px;height:38px;flex:0 0 38px;display:grid;place-items:center;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--gold-deep);font-size:16px}.bar-icon:active{transform:scale(.95)}
 .desktop-topbar{display:none}.app-main{flex:1;min-height:0;height:100dvh;max-height:100dvh}.app-content{width:100%;height:auto;min-height:0;flex:1;margin:0;padding:0;overflow:hidden}.overview-view{height:100%;overflow:auto;padding:17px 15px calc(24px + env(safe-area-inset-bottom));}.menu-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.menu-card{min-height:145px;padding:16px 15px;border-radius:16px;gap:10px;box-shadow:0 8px 20px rgba(174,126,31,.15)}.menu-card .menu-icon{width:41px;height:41px;border-radius:13px;font-size:18px}.menu-card h2{font-size:13px;line-height:1.3}.menu-card p{font-size:10.5px;line-height:1.4;margin-top:-5px}
 .module-view{width:100%;height:100%;border:0;border-radius:0;box-shadow:none}.module-frame{height:100%;width:100%}.loading-cover{padding-bottom:20px}
 .login-wrap{padding:15px}.login-card{padding:23px 19px;border-radius:17px}
}
@media(max-width:380px) and (orientation:portrait){.mobile-topbar{padding:0 12px}.overview-view{padding-left:11px;padding-right:11px}.menu-grid{gap:9px}.menu-card{min-height:136px;padding:13px 12px}.menu-card p{font-size:10px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
</style>
</head>
<body>
<?php if (!$loggedIn): ?>
<div class="login-wrap"><form class="login-card" method="post" action="serverapp.php" autocomplete="off">
    <h1><?= $initialSetupRequired ? 'Set up Server Administration' : 'Server Administration' ?></h1>
    <p><?= $initialSetupRequired ? 'Create the administrator credentials for this control panel.' : 'Sign in to manage HarvHub server modules.' ?></p>
    <?php if ($loginError !== ''): ?><div class="error"><?= serverShellH($loginError) ?></div><?php endif; ?>
    <input type="hidden" name="nonce" value="<?= serverShellH($_SESSION['server_shell_nonce']) ?>">
    <input type="hidden" name="server_shell_action" value="<?= $initialSetupRequired ? 'setup' : 'login' ?>">
    <div class="field"><label for="login_id">Admin Login ID</label><input id="login_id" name="login_id" required value="<?= serverShellH($loginPrefill) ?>" autocomplete="username"></div>
    <div class="field"><label for="password"><?= $initialSetupRequired ? 'New Password (12+ characters)' : 'Password' ?></label><input id="password" name="password" type="password" required <?= $initialSetupRequired ? 'minlength="12"' : '' ?> autocomplete="current-password"></div>
    <button class="btn" type="submit"><?= $initialSetupRequired ? 'Create Administrator' : 'Sign In' ?></button>
</form></div>
<?php else: ?>
<div class="shell" id="serverShell" data-view="<?= serverShellH($view) ?>">
    <div class="drawer-scrim" id="drawerScrim" aria-hidden="true"></div>
    <aside class="sidebar" id="serverDrawer" aria-label="Server navigation">
        <div class="drawer-brand">HARVHUB <small>SERVER CONTROL</small></div>
        <nav class="side-nav" aria-label="Server sections">
            <a href="serverapp.php?view=menu" data-server-view="menu" class="<?= $view==='menu'?'active':'' ?>"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Overview</span></a>
            <?php foreach ($routes as $key=>$item): ?><a href="serverapp.php?view=<?= serverShellH($key) ?>" data-server-view="<?= serverShellH($key) ?>" class="<?= $view===$key?'active':'' ?>"><i class="<?= serverShellH($item['icon']) ?>" aria-hidden="true"></i><span><?= serverShellH($item['title']) ?></span></a><?php endforeach; ?>
        </nav>
        <div class="drawer-bottom">
            <button class="drawer-action" type="button" id="themeToggle"><i id="themeIcon" class="fa-solid <?= $darkMode?'fa-sun':'fa-moon' ?>" aria-hidden="true"></i><span id="themeText"><?= $darkMode?'Light mode':'Dark mode' ?></span></button>
            <a class="drawer-action" href="serverapp.php?logout=1"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i><span>Sign out</span></a>
            <input type="hidden" id="serverThemeCsrf" value="<?= serverShellH($_SESSION['server_shell_csrf']) ?>">
        </div>
    </aside>

    <div class="app-main">
        <header class="mobile-topbar" id="mobileHomeBar" <?= $view!=='menu'?'hidden':'' ?>>
            <button type="button" class="bar-icon" id="openDrawer" aria-label="Open navigation"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            <span class="bar-title">HarvHub Server Control</span>
        </header>
        <header class="mobile-topbar" id="mobileModuleBar" <?= $view==='menu'?'hidden':'' ?>>
            <button type="button" class="bar-icon" id="backOverview" aria-label="Back to overview"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
            <span class="bar-title" id="mobileModuleTitle"><?= serverShellH($activeTitle) ?></span>
        </header>
        <header class="desktop-topbar"><h1 id="desktopTitle"><?= serverShellH($activeTitle) ?></h1></header>
        <main class="app-content" id="appContent">
            <section class="overview-view" id="overviewView" <?= $view!=='menu'?'hidden':'' ?> aria-label="Server modules">
                <div class="menu-grid">
                <?php foreach ($routes as $key=>$item): ?>
                    <a class="menu-card" href="serverapp.php?view=<?= serverShellH($key) ?>" data-server-view="<?= serverShellH($key) ?>">
                        <span class="menu-icon"><i class="<?= serverShellH($item['icon']) ?>" aria-hidden="true"></i></span>
                        <h2><?= serverShellH($item['title']) ?></h2>
                        <p><?= serverShellH($item['description']) ?></p>
                    </a>
                <?php endforeach; ?>
                </div>
            </section>
            <section class="module-view" id="moduleView" <?= $view==='menu'?'hidden':'' ?> aria-label="Selected server module">
                <div class="loading-cover" id="moduleLoader" <?= $view==='menu'?'hidden':'' ?> role="status" aria-live="polite">
                    <div class="loader-ring" aria-hidden="true"></div><div>Loading module</div><div class="loader-bars" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                </div>
                <?php foreach ($routes as $key=>$item): ?>
                    <iframe class="module-frame <?= $view===$key?'is-active':'' ?>" id="frame-<?= serverShellH($key) ?>" data-server-frame="<?= serverShellH($key) ?>" src="<?= serverShellH($item['file']) ?>?embedded=1" title="<?= serverShellH($item['title']) ?>" loading="eager"></iframe>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
</div>
<script>
(function(){
 'use strict';
 const shell=document.getElementById('serverShell'); if(!shell)return;
 const routes=<?= json_encode(array_map(static fn($r)=>['title'=>$r['title'],'file'=>$r['file']],$routes),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;
 const overview=document.getElementById('overviewView'), moduleView=document.getElementById('moduleView'), loader=document.getElementById('moduleLoader');
 const homeBar=document.getElementById('mobileHomeBar'), moduleBar=document.getElementById('mobileModuleBar');
 const mobileTitle=document.getElementById('mobileModuleTitle'), desktopTitle=document.getElementById('desktopTitle');
 const scrim=document.getElementById('drawerScrim'), themeToggle=document.getElementById('themeToggle');
 let currentView=shell.dataset.view||'menu'; const loaded=new Set();
 const isPortraitMobile=()=>window.matchMedia('(max-width:800px) and (orientation:portrait)').matches;
 function postFrameState(frame,active){try{frame.contentWindow.postMessage({type:'serverapp:module-state',active:!!active,theme:document.documentElement.dataset.theme},location.origin)}catch(e){}}
 function enhanceFrame(frame){
   const key=frame.dataset.serverFrame;
   try{
     const doc=frame.contentDocument; if(!doc||!doc.head)return;
     let style=doc.getElementById('serverapp-embedded-shell-style');
     if(!style){style=doc.createElement('style');style.id='serverapp-embedded-shell-style';style.textContent=`
       html,body{min-height:100%!important;height:100%!important;width:100%!important;margin:0!important;overflow-x:hidden!important}
       body{padding:0!important}
       .head,.volume-head,.vps-head,.settings-header,.server-feature-wrap>h2{display:none!important}
       .server-feature-wrap{width:100%!important;max-width:none!important;margin:0!important;padding:15px 16px 24px!important}
       .settings-container,.risk-dictionary-container{min-height:0!important;margin:0!important;padding-top:10px!important}
       .wrap{width:100%!important;max-width:none!important;padding:15px 16px 24px!important}
       .volume-page{width:100%!important;max-width:none!important;padding:15px 16px 24px!important}
       .vps-wrap,.vps-container,.vps-page{max-width:none!important;width:100%!important;margin:0!important;padding:15px 16px 24px!important}
       .settings-container{padding:8px 16px 24px!important}
       .risk-dictionary-container{padding:0 0 24px!important}
       @media(max-width:800px) and (orientation:portrait){.server-feature-wrap,.wrap,.volume-page,.vps-wrap,.vps-container,.vps-page{padding:12px 12px calc(22px + env(safe-area-inset-bottom))!important}.settings-container{padding:8px 12px calc(22px + env(safe-area-inset-bottom))!important}}
     `;doc.head.appendChild(style);}
     doc.documentElement.setAttribute('data-serverapp-embedded','1');
     doc.documentElement.setAttribute('data-theme',document.documentElement.dataset.theme||'light');
     postFrameState(frame,key===currentView);
   }catch(e){}
 }
 document.querySelectorAll('[data-server-frame]').forEach(frame=>{
   const markLoaded=()=>{loaded.add(frame.dataset.serverFrame);enhanceFrame(frame);if(frame.dataset.serverFrame===currentView)loader.hidden=true;};
   frame.addEventListener('load',markLoaded);
   // A cached/preloaded frame can finish before this shell script binds its load listener.
   // Detect that case so the custom loader never remains stuck over an already-ready page.
   try{if(frame.contentDocument&&frame.contentDocument.readyState==='complete')markLoaded();}catch(e){}
 });
 function closeDrawer(){shell.classList.remove('drawer-open');scrim.setAttribute('aria-hidden','true');document.body.style.overflow='';}
 function openDrawer(){shell.classList.add('drawer-open');scrim.setAttribute('aria-hidden','false');}
 function setView(view,opts={}){
   if(view!=='menu'&&!routes[view])view='menu';
   currentView=view;shell.dataset.view=view;
   overview.hidden=view!=='menu';moduleView.hidden=view==='menu';
   homeBar.hidden=view!=='menu';moduleBar.hidden=view==='menu';
   const title=view==='menu'?'HarvHub Server Control':routes[view].title;
   if(desktopTitle)desktopTitle.textContent=title;if(mobileTitle)mobileTitle.textContent=title;
   document.title=title+' · HarvHub Server Control';
   document.querySelectorAll('.side-nav [data-server-view]').forEach(a=>a.classList.toggle('active',a.dataset.serverView===view));
   document.querySelectorAll('[data-server-frame]').forEach(frame=>{
     const active=frame.dataset.serverFrame===view;frame.classList.toggle('is-active',active);
     postFrameState(frame,active);
     if(active){
       enhanceFrame(frame);
       loader.hidden=loaded.has(view);
       if(!loaded.has(view))loader.hidden=false;
     }
   });
   if(view==='menu')loader.hidden=true;
   closeDrawer();
   if(opts.push!==false){const url=view==='menu'?'serverapp.php?view=menu':'serverapp.php?view='+encodeURIComponent(view);history.pushState({view},'',url);}
 }
 document.addEventListener('click',e=>{
   const link=e.target.closest('[data-server-view]');if(link){e.preventDefault();setView(link.dataset.serverView);return;}
 });
 document.getElementById('openDrawer')?.addEventListener('click',openDrawer);
 document.getElementById('backOverview')?.addEventListener('click',()=>setView('menu'));
 scrim?.addEventListener('click',closeDrawer);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDrawer();});
 window.addEventListener('popstate',()=>{const view=new URLSearchParams(location.search).get('view')||'menu';setView(view,{push:false});});
 window.addEventListener('resize',()=>{if(!isPortraitMobile())closeDrawer();});
 themeToggle?.addEventListener('click',async()=>{
   const csrf=document.getElementById('serverThemeCsrf')?.value||'';
   const body=new URLSearchParams({server_shell_action:'theme_toggle',csrf_token:csrf});
   themeToggle.disabled=true;
   try{
     const res=await fetch('serverapp.php?view='+encodeURIComponent(currentView),{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body});
     const data=await res.json();if(!res.ok||!data.success)throw new Error(data.error||'Unable to change appearance.');
     const theme=Number(data.dark_mode)===1?'dark':'light';document.documentElement.dataset.theme=theme;
     const icon=document.getElementById('themeIcon'),label=document.getElementById('themeText');
     icon.className='fa-solid '+(theme==='dark'?'fa-sun':'fa-moon');label.textContent=theme==='dark'?'Light mode':'Dark mode';
     document.querySelectorAll('[data-server-frame]').forEach(frame=>{try{frame.contentDocument.documentElement.dataset.theme=theme;}catch(e){}postFrameState(frame,frame.dataset.serverFrame===currentView);});
   }catch(err){alert(err.message||'Unable to change appearance.');}finally{themeToggle.disabled=false;}
 });
 // All five feature pages are loaded in independent same-origin frames at startup.
 // Each frame retains its own PHP/AJAX endpoint, forms, JavaScript and refresh lifecycle.
 setView(currentView,{push:false});
})();
</script>
<?php endif; ?>
</body></html>
