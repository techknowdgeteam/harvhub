<?php
// connect_programme_broker.php — programme-scoped broker connection
session_start();
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("Database connection failed."); }

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

$u = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$u->execute([$email]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php?role=developer"); exit; }

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($activeProgrammeId <= 0) { header("Location: traderapp.php?tab=menu"); exit; }

$p = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
$p->execute([$activeProgrammeId, $userId]);
$programme = $p->fetch(PDO::FETCH_ASSOC);
if (!$programme) { header("Location: traderapp.php?tab=menu"); exit; }

$fullName  = $user['fullname'] ?? 'User';
$darkMode  = (int)($user['dark_mode'] ?? 0);
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ------- Allowed brokers from server_account -------
$allowed_brokers = [];
$broker_links = [];
try {
    $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($config) {
        $raw_brokers = explode(',', $config['brokers'] ?? '');
        $raw_links   = explode(',', $config['brokers_link'] ?? '');
        $cleaned_links = [];
        foreach ($raw_links as $link) {
            $link = trim($link); if ($link === '') continue;
            if (strpos($link, ':') !== false) $link = trim(substr($link, strrpos($link, ':') + 1));
            if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,})/', $link, $m)) $link = $m[1];
            $link = strtolower(trim($link));
            if ($link !== '') $cleaned_links[] = $link;
        }
        foreach ($raw_brokers as $i => $entry) {
            $entry = trim($entry); if ($entry === '') continue;
            $broker_name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;
            $broker_name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name));
            if ($broker_name === '') continue;
            $formatted = ucfirst($broker_name);
            $link = $cleaned_links[$i] ?? '';
            if (!in_array($formatted, $allowed_brokers, true)) {
                $allowed_brokers[] = $formatted;
                $broker_links[$formatted] = $link;
            }
        }
        sort($allowed_brokers);
    }
} catch (PDOException $e) {}

$current_broker = (string)($programme['broker'] ?? '');
$current_server = (string)($programme['server'] ?? '');
$current_login  = (string)($programme['login']  ?? '');

$broker_connected = ($current_broker !== '' && $current_server !== '' && $current_login !== '');

$has_links = false;
foreach ($allowed_brokers as $b) {
    if (!empty($broker_links[$b])) { $has_links = true; break; }
}

// ==================== HELPERS ====================
if (!function_exists('resolveProgrammeDisplayNameLocal')) {
    function resolveProgrammeDisplayNameLocal(array $p) {
        $n = trim((string)($p['program_name'] ?? ''));
        return $n !== '' ? $n : 'Programme #' . (int)($p['id'] ?? 0);
    }
}

// ==================== AJAX: CONNECT / UPDATE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_broker_ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $broker = trim($_POST['broker'] ?? '');
    $server = trim($_POST['server'] ?? '');
    $login  = trim($_POST['login']  ?? '');
    $broker_password = $_POST['broker_password'] ?? '';

    $errors = [];
    if ($broker === '') $errors[] = 'Broker is required';
    if ($server === '') $errors[] = 'Server is required';
    if ($login  === '') $errors[] = 'Login is required';
    if ($broker_password === '') $errors[] = 'Password is required';
    if ($errors) { echo json_encode(['success' => false, 'errors' => $errors]); exit; }

    if (!in_array($broker, $allowed_brokers, true)) {
        echo json_encode(['success' => false, 'errors' => ['Invalid broker selected.']]);
        exit;
    }

    // Prevent duplicate broker login across programmes owned by same user
    try {
        $dup = $pdo->prepare("
            SELECT id FROM programme
            WHERE userid = ? AND id <> ? AND login = ?
            LIMIT 1
        ");
        $dup->execute([$userId, $activeProgrammeId, $login]);
        if ($dup->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success' => false, 'errors' => ['This broker login is already connected to another one of your programmes.']]);
            exit;
        }
    } catch (Throwable $e) {}

    $isUpdate = $broker_connected;

    try {
        $upd = $pdo->prepare("UPDATE programme SET broker = ?, server = ?, login = ?, broker_password = ? WHERE id = ? AND userid = ?");
        $upd->execute([$broker, $server, $login, $broker_password, $activeProgrammeId, $userId]);

        if (!$isUpdate) {
            // ============================================
            // FIRST-TIME CONNECTION — programme notification only
            // ============================================
            recordProgrammeNotification($pdo, $activeProgrammeId, $email, [
                'notification_key' => 'prog-broker-connected-' . $activeProgrammeId,
                'title'   => 'Broker Connected',
                'message' => 'Your programme broker (' . $broker . ' - ' . $login . ') has been connected successfully.',
                'type'    => 'success',
                'section' => 'Broker',
                'action_tab' => 'broker',
                'force'   => false
            ]);

        } else {
            // ============================================
            // UPDATE — always notify on the update form
            // ============================================
            $tsKey = 'prog-broker-updated-' . $activeProgrammeId . '-' . date('YmdHis');

            recordProgrammeNotification($pdo, $activeProgrammeId, $email, [
                'notification_key' => $tsKey,
                'title'   => 'Broker Details Updated',
                'message' => 'Your programme broker details have been updated to ' . $broker . ' - ' . $login . '.',
                'type'    => 'info',
                'section' => 'Broker',
                'action_tab' => 'broker',
                'force'   => true
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => $isUpdate ? 'Broker details updated successfully!' : 'Broker connected successfully!',
            'is_update' => $isUpdate,
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'errors' => ['Failed to save broker details.']]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>Connect Broker - HarvHub</title>
<?php include 'style.php'; ?>
<?php include 'connect_investor_broker_style.php'; ?>
<style>
    /* identical to connect_investor_broker.php */
    .broker-confirm-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.62); display:none; align-items:center; justify-content:center; padding:20px; z-index:99999; }
    .broker-confirm-overlay.active { display:flex; }
    .broker-confirm-box { width:min(92vw,460px); max-width:460px; background: var(--card-bg,#fff); color: var(--text,#1a2332); border-radius:16px; padding:22px; box-shadow:0 20px 60px rgba(0,0,0,.35); text-align:center; }
    body.dark-mode .broker-confirm-box { background:#0f1614; color:#e6edf3; }
    .broker-confirm-box h3 { margin:0 0 12px; font-size:1.05rem; color: var(--accent,#2ecc8f); }
    .broker-confirm-box p { margin:0 0 20px; font-size:.92rem; line-height:1.6; opacity:.9; text-align:left; }
    .broker-confirm-actions { display:flex; flex-direction:column; gap:10px; }
    .broker-confirm-actions button { width:100%; padding:13px; border-radius:10px; border:none; font-weight:700; font-size:.95rem; cursor:pointer; }
    .broker-confirm-actions .btn-yes { background: var(--accent,#2ecc8f); color:#fff; }
    .broker-confirm-actions .btn-cancel { background: rgba(127,127,127,0.15); color: var(--text,#1a2332); }
    body.dark-mode .broker-confirm-actions .btn-cancel { color:#e6edf3; }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> broker-page-body">

<div class="broker-page-wrapper">
    <header class="broker-sticky-top">
        <div class="broker-topbar">
            <a href="#" class="broker-topbar-back"
               onclick="event.preventDefault(); harvhubGoToTab('menu'); return false;"
               aria-label="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <h1 class="broker-topbar-title">Connect Broker</h1>
        </div>
    </header>

    <div class="broker-scroll-area" id="brokerScrollArea">
        <div class="broker-user-info">
            <span class="broker-user-info-item">
                <span class="broker-user-label">Programme</span>
                <span class="broker-user-value"><?= htmlspecialchars($programme['program_name'] ?: 'Unnamed Programme') ?></span>
            </span>
        </div>

        <div id="brokerError" class="broker-error-message" style="display:none;"></div>
        <div id="brokerSuccess" class="broker-success-message" style="display:none;"></div>

        <?php if ($broker_connected): ?>
            <div class="broker-section-title">Update Your Broker Account</div>
            <form id="connectBrokerForm" novalidate>
                <div class="broker-form-group">
                    <label for="broker_select">Select Broker</label>
                    <select name="broker" id="broker_select" required>
                        <option value="">-- Select Broker --</option>
                        <?php foreach ($allowed_brokers as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= ($current_broker === $b) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="broker-form-group">
                    <label for="server_input">Server</label>
                    <input type="text" name="server" id="server_input" placeholder="broker server" value="<?= htmlspecialchars($current_server) ?>" required>
                </div>
                <div class="broker-form-group">
                    <label for="login_input">Login Number</label>
                    <input type="text" name="login" id="login_input" placeholder="Your MT5 login number" value="<?= htmlspecialchars($current_login) ?>" required>
                </div>
                <div class="broker-form-group">
                    <label for="password_input">MT5 Password</label>
                    <div class="broker-password-wrapper">
                        <input type="password" name="broker_password" id="password_input" placeholder="Re-enter your MT5 password" required>
                        <button type="button" class="broker-password-toggle" onclick="togglePasswordField()">Show</button>
                    </div>
                </div>
                <button type="submit" id="connectBrokerBtn" class="broker-btn-submit">Update Broker Details</button>
            </form>
        <?php else: ?>
            <div id="availableBrokersSection">
                <div class="broker-section-title">Available Brokers</div>
                <div id="brokerSelectionGrid" class="broker-selection-grid">
                    <?php foreach ($allowed_brokers as $b):
                        $link = $broker_links[$b] ?? '';
                    ?>
                        <div class="broker-selection-item">
                            <span class="broker-name"><?= htmlspecialchars($b) ?></span>
                            <div class="broker-action">
                                <?php if ($link !== ''): ?>
                                    <a href="https://<?= htmlspecialchars($link) ?>" target="_blank" class="broker-link-btn">Go to <?= htmlspecialchars($b) ?></a>
                                <?php else: ?>
                                    <span class="broker-link-btn no-link">No Link</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="connectButtonWrapper" class="broker-connect-wrapper">
                    <button type="button" class="broker-btn-secondary" onclick="showConnectForm()">I Already Have an Account</button>
                </div>
            </div>

            <div id="connectForm" class="hidden">
                <hr class="broker-divider">
                <form id="connectBrokerForm" novalidate>
                    <div class="broker-form-group">
                        <label for="broker_select">Select Broker</label>
                        <select name="broker" id="broker_select" required>
                            <option value="">-- Select Broker --</option>
                            <?php foreach ($allowed_brokers as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="broker-form-group">
                        <label for="server_input">Server</label>
                        <input type="text" name="server" id="server_input" placeholder="e.g. Exness-MT5" required>
                    </div>
                    <div class="broker-form-group">
                        <label for="login_input">Login Number</label>
                        <input type="text" name="login" id="login_input" placeholder="Your MT5 login number" required>
                    </div>
                    <div class="broker-form-group">
                        <label for="password_input">MT5 Password</label>
                        <div class="broker-password-wrapper">
                            <input type="password" name="broker_password" id="password_input" placeholder="Your MT5 password" required>
                            <button type="button" class="broker-password-toggle" onclick="togglePasswordField()">Show</button>
                        </div>
                    </div>
                    <button type="submit" id="connectBrokerBtn" class="broker-btn-submit">Connect Broker</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="broker-info-note">
            <strong>Secure:</strong> Your broker credentials are encrypted and stored securely.
            They are only used for automated trading execution.
        </div>
    </div>
</div>

<div id="brokerConfirmModal" class="broker-confirm-overlay" aria-hidden="true">
    <div class="broker-confirm-box" role="dialog" aria-modal="true">
        <h3>Confirm Broker Connection</h3>
        <p>
            Before connecting, please make sure the full name on your broker account matches the name registered on your HarvHub profile. If the credentials do not match, this programme may be suspended. Do you want to proceed?
        </p>
        <div class="broker-confirm-actions">
            <button type="button" class="btn-yes" id="brokerConfirmYes">Yes, Proceed</button>
            <button type="button" class="btn-cancel" id="brokerConfirmCancel">Cancel</button>
        </div>
    </div>
</div>

<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-connect_trader_broker');
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
        });
        var WATCHED = ['page-connect_trader_broker','page-disconnect_trader_broker','page-trader_menu'];
        function broadcast() {
            var add = WATCHED.filter(function (c){ return document.body.classList.contains(c); });
            try { window.parent.postMessage({ type:'bodyClass', add:add, remove: WATCHED.filter(function(c){ return add.indexOf(c) === -1; }) }, '*'); } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes:true, attributeFilter:['class'] });
        broadcast();
        try { window.parent.postMessage({ type:'requestTheme' }, '*'); } catch (e) {}
    })();

    function togglePasswordField() {
        var input = document.getElementById('password_input');
        if (!input) return;
        var t = input.parentElement.querySelector('.broker-password-toggle');
        if (input.type === 'password') { input.type = 'text'; if (t) t.textContent = 'Hide'; }
        else { input.type = 'password'; if (t) t.textContent = 'Show'; }
    }
    function showConnectForm() {
        var sec = document.getElementById('availableBrokersSection'); if (sec) sec.classList.add('hidden');
        var grid = document.getElementById('brokerSelectionGrid'); if (grid) grid.classList.add('hidden');
        var wrap = document.getElementById('connectButtonWrapper'); if (wrap) wrap.classList.add('hidden');
        var f = document.getElementById('connectForm');
        if (f) { f.classList.remove('hidden'); setTimeout(function(){ f.scrollIntoView({behavior:'smooth',block:'start'}); }, 100); }
    }
    function showBrokerError(msg) {
        var b = document.getElementById('brokerError'); if (!b) return;
        b.textContent = msg || 'Something went wrong.'; b.style.display = 'block';
        b.scrollIntoView({ behavior:'smooth', block:'center' });
    }
    function hideBrokerError() { var b = document.getElementById('brokerError'); if (b) { b.style.display='none'; b.textContent=''; } }
    function showBrokerSuccess(msg) {
        var b = document.getElementById('brokerSuccess'); if (!b) return;
        b.textContent = msg || 'Saved.'; b.style.display = 'block';
        b.scrollIntoView({ behavior:'smooth', block:'center' });
    }
    function hideBrokerSuccess() { var b = document.getElementById('brokerSuccess'); if (b) { b.style.display='none'; b.textContent=''; } }

    var _pendingSubmit = null;
    function openBrokerConfirm(onYes) {
        _pendingSubmit = onYes || null;
        var m = document.getElementById('brokerConfirmModal');
        if (m) { m.classList.add('active'); m.setAttribute('aria-hidden','false'); }
    }
    function closeBrokerConfirm() {
        _pendingSubmit = null;
        var m = document.getElementById('brokerConfirmModal');
        if (m) { m.classList.remove('active'); m.setAttribute('aria-hidden','true'); }
    }
    document.addEventListener('DOMContentLoaded', function () {
        var y = document.getElementById('brokerConfirmYes');
        var c = document.getElementById('brokerConfirmCancel');
        var m = document.getElementById('brokerConfirmModal');
        if (y) y.addEventListener('click', function () {
            var cb = _pendingSubmit; closeBrokerConfirm();
            if (typeof cb === 'function') cb();
        });
        if (c) c.addEventListener('click', closeBrokerConfirm);
        if (m) m.addEventListener('click', function (e) { if (e.target === m) closeBrokerConfirm(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && m && m.classList.contains('active')) closeBrokerConfirm();
        });
    });

    function initBrokerForm() {
        var form = document.getElementById('connectBrokerForm');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            hideBrokerError(); hideBrokerSuccess();
            var brokerEl = form.querySelector('#broker_select');
            var serverEl = form.querySelector('#server_input');
            var loginEl  = form.querySelector('#login_input');
            var passEl   = form.querySelector('#password_input');
            var btn      = form.querySelector('#connectBrokerBtn');

            var broker = brokerEl ? brokerEl.value.trim() : '';
            var server = serverEl ? serverEl.value.trim() : '';
            var login  = loginEl  ? loginEl.value.trim()  : '';
            var pass   = passEl   ? passEl.value          : '';

            var errors = [];
            if (!broker) errors.push('Please select a broker.');
            if (!server) errors.push('Server is required.');
            if (!login)  errors.push('Login is required.');
            if (!pass)   errors.push('Password is required.');
            if (errors.length) { showBrokerError(errors.join(' ')); return; }

            openBrokerConfirm(function () {
                doSaveBroker(broker, server, login, pass, passEl, btn);
            });
        });
    }

    function doSaveBroker(broker, server, login, pass, passEl, btn) {
        if (btn) { btn.disabled = true; btn.dataset.originalText = btn.textContent; btn.textContent = 'Saving...'; }
        var body = 'connect_broker_ajax=1'
                 + '&broker='          + encodeURIComponent(broker)
                 + '&server='          + encodeURIComponent(server)
                 + '&login='           + encodeURIComponent(login)
                 + '&broker_password=' + encodeURIComponent(pass);

        fetch('connect_programme_broker.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                if (btn) { btn.disabled = false; btn.textContent = btn.dataset.originalText || 'Save'; }
                showBrokerError('Server returned an unexpected response.');
                return;
            }
            if (data && data.success) {
                if (btn) { btn.disabled = true; btn.textContent = 'Saved'; }
                showBrokerSuccess(data.message || 'Broker connected successfully.');
                if (passEl) passEl.value = '';
                setTimeout(function () {
                    try {
                        if (window.parent && window.parent !== window) {
                            window.parent.postMessage({ type: 'reloadWithSpinner' }, '*');
                            return;
                        }
                    } catch (e) {}
                    window.location.href = 'traderapp.php?tab=signals';
                }, 1200);
            } else {
                if (btn) { btn.disabled = false; btn.textContent = btn.dataset.originalText || 'Save'; }
                var errs = (data && data.errors) ? data.errors : ['Failed to save broker details.'];
                showBrokerError(errs.join(' '));
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = btn.dataset.originalText || 'Save'; }
            showBrokerError('Network error. Please try again.');
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initBrokerForm);
    else initBrokerForm();

    <?php if (!$has_links && !$broker_connected): ?>
        document.addEventListener('DOMContentLoaded', showConnectForm);
    <?php endif; ?>
</script>
</body>
</html>