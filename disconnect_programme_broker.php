<?php
// disconnect_programme_broker.php — programme-scoped broker disconnect
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

$fullName = $user['fullname'] ?? 'User';
$darkMode = (int)($user['dark_mode'] ?? 0);
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$current_broker = (string)($programme['broker'] ?? '');
$current_server = (string)($programme['server'] ?? '');
$current_login  = (string)($programme['login']  ?? '');

$broker_connected = ($current_broker !== '' && $current_server !== '' && $current_login !== '');

$programme_display = $programme['program_name'] ?: 'Unnamed Programme';

$disconnect_success = false;
$disconnect_error   = '';
$disconnect_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disconnect_broker'])) {
    try {
        $upd = $pdo->prepare("UPDATE programme SET broker = NULL, server = NULL, login = NULL, broker_password = NULL WHERE id = ? AND userid = ?");
        $upd->execute([$activeProgrammeId, $userId]);

        $disconnect_success = true;
        $disconnect_message = 'Broker disconnected successfully.';

        // Programme-scoped notification (in-app row + email via notification_service.php)
        recordProgrammeNotification($pdo, $activeProgrammeId, $email, [
            'notification_key' => 'prog-broker-disconnected-' . $activeProgrammeId . '-' . date('YmdHis'),
            'title'            => 'Broker Disconnected',
            'message'          => 'Your programme broker (' . $current_broker . ' - ' . $current_login . ') has been disconnected.',
            'type'             => 'info',
            'section'          => 'Broker',
            'action_tab'       => 'broker',
            'force'            => true,
            'recipient_name'   => $fullName
        ]);
    } catch (PDOException $e) {
        $disconnect_error = 'Failed to disconnect broker. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>Disconnect Broker - HarvHub</title>
<?php include 'style.php'; ?>
<?php include 'connect_investor_broker_style.php'; ?>
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
            <h1 class="broker-topbar-title">Disconnect Broker</h1>
        </div>
    </header>

    <div class="broker-scroll-area" id="brokerScrollArea">
        <div class="broker-user-info">
            <span class="broker-user-info-item">
                <span class="broker-user-label">Programme</span>
                <span class="broker-user-value"><?= htmlspecialchars($programme_display) ?></span>
            </span>
        </div>

        <?php if (!$broker_connected): ?>
            <div class="broker-error-message" style="display:block;">
                No broker account is currently connected to this programme.
            </div>
            <div class="broker-connect-wrapper" style="margin-top:20px;">
                <a href="#" onclick="event.preventDefault(); harvhubGoToTab('connect_trader_broker'); return false;"
                   class="broker-btn-secondary" style="text-decoration:none; display:inline-block;">
                    Go to Connect Broker
                </a>
            </div>

        <?php elseif ($disconnect_success): ?>
            <div class="broker-success-message" style="display:block;">
                <?= htmlspecialchars($disconnect_message) ?>
            </div>
            <div class="broker-connect-wrapper" style="margin-top:20px;">
                <a href="#" onclick="event.preventDefault(); harvhubGoToTab('signals'); return false;"
                   class="broker-btn-submit" style="text-decoration:none; display:inline-block;">
                    Return to Signals
                </a>
            </div>

        <?php else: ?>
            <?php if ($disconnect_error !== ''): ?>
                <div class="broker-error-message" style="display:block;"><?= htmlspecialchars($disconnect_error) ?></div>
            <?php endif; ?>

            <div class="broker-section-title">Current Broker Details</div>
            <div class="broker-info-note" style="margin-bottom:18px;">
                <div style="margin-bottom:6px;"><strong>Broker:</strong> <?= htmlspecialchars($current_broker) ?></div>
                <div style="margin-bottom:6px;"><strong>Server:</strong> <?= htmlspecialchars($current_server) ?></div>
                <div><strong>Login:</strong> <?= htmlspecialchars($current_login) ?></div>
            </div>

            <hr class="broker-divider">

            <div class="broker-section-title">Disconnect Broker</div>
            <div class="broker-info-note" style="margin-bottom:18px;">
                <strong>Note:</strong> Disconnecting will remove this programme's broker credentials from our servers.
                You can reconnect at any time from the Connect Broker page.
            </div>

            <button type="button" class="broker-btn-submit" style="background:#dc3545;"
                    onclick="openDisconnectConfirmModal()">Disconnect Broker</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($broker_connected && !$disconnect_success): ?>
    <div class="modal-overlay" id="disconnectConfirmModal">
        <div class="modal-box" style="max-width:420px;">
            <h2>Disconnect Broker</h2>
            <p>
                This programme's MT5 details will be deleted from our end.
                <br>
                You can reconnect at any time from the Connect Broker page.
            </p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeDisconnectConfirmModal()">Cancel</button>
                <button class="btn-danger-confirm" onclick="submitDisconnect()">Proceed</button>
            </div>
        </div>
    </div>

    <form id="disconnectBrokerForm" method="POST" style="display:none;">
        <input type="hidden" name="disconnect_broker" value="1">
    </form>
<?php endif; ?>

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
        document.body.classList.add('page-disconnect_trader_broker');
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

    function openDisconnectConfirmModal() { var m = document.getElementById('disconnectConfirmModal'); if (m) m.classList.add('active'); }
    function closeDisconnectConfirmModal() { var m = document.getElementById('disconnectConfirmModal'); if (m) m.classList.remove('active'); }
    function submitDisconnect() { var f = document.getElementById('disconnectBrokerForm'); if (f) f.submit(); }

    document.addEventListener('click', function(e){
        var m = document.getElementById('disconnectConfirmModal');
        if (m && e.target === m) closeDisconnectConfirmModal();
    });

    window.openDisconnectConfirmModal  = openDisconnectConfirmModal;
    window.closeDisconnectConfirmModal = closeDisconnectConfirmModal;
    window.submitDisconnect            = submitDisconnect;
</script>
</body>
</html>