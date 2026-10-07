<?php
// activity.php — sub-account scoped
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_email'])) { header("Location: index.php"); exit; }
$email = strtolower($_SESSION['user_email']);
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("Database connection failed."); }

// ---- Resolve active sub-account ----
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) { header("Location: index.php"); exit; }

$activeSubAccountId = (int)($user['sub_account_id'] ?? $user['id']);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$darkMode = !empty($user['dark_mode']);
$darkModeClass = $darkMode ? 'dark-mode' : '';

function parseAnyDate($value) {
    if (empty($value)) return null;
    $value = trim($value);
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if ($dt && $dt->format('Y-m-d') === $value) return $dt;
    $dt = DateTime::createFromFormat('d-m-Y', $value);
    if ($dt && $dt->format('d-m-Y') === $value) return $dt;
    $ts = strtotime($value);
    return $ts ? new DateTime(date('Y-m-d', $ts)) : null;
}

/**
 * SUB-ACCOUNT SCOPED — filters balance_log by sub_account_id.
 */
function buildActivityPayload($pdo, $tableName, $email, $activeSubAccountId) {
    // Resolve the user row id for this specific sub-account
    $stmt = $pdo->prepare("SELECT id, sub_account_id FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        // Fallback to lowest-id sub
        $stmt = $pdo->prepare("SELECT id, sub_account_id FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$u) return null;

    $userid       = (int)$u['id'];
    $subAccountId = (int)($u['sub_account_id'] ?? $userid);

    $daily = [];
    try {
        // Scoped by userid AND sub_account_id (with NULL fallback for legacy rows)
        $stmt = $pdo->prepare("
            SELECT date, day_starting_balance, day_authorized_trades_pnl,
                   day_unauthorized_trades_pnl, day_unauthorized_withdrawals,
                   day_closing_balance, unusual_activity,
                   authorized_trades_count, unauthorized_trades_count
            FROM balance_log
            WHERE userid = ?
              AND (sub_account_id = ? OR sub_account_id IS NULL)
        ");
        $stmt->execute([$userid, $subAccountId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dt = parseAnyDate($r['date']);
            if (!$dt) continue;
            $daily[$dt->format('d-m-Y')] = $r;
        }
    } catch (PDOException $e) { $daily = []; }

    $keys = array_keys($daily);
    usort($keys, function ($a, $b) {
        $A = DateTime::createFromFormat('d-m-Y', $a);
        $B = DateTime::createFromFormat('d-m-Y', $b);
        return ($A && $B) ? $B <=> $A : strcmp($b, $a);
    });

    $rows = [];
    foreach ($keys as $k) {
        $d = $daily[$k];
        $o = DateTime::createFromFormat('d-m-Y', $k);
        if (!$o) continue;
        $rows[] = [
            'key' => $k,
            'formatted_date' => $o->format('M d, Y'),
            'day_name' => $o->format('l'),
            'day_starting_balance' => (float)($d['day_starting_balance'] ?? 0),
            'day_authorized_trades_pnl' => (float)($d['day_authorized_trades_pnl'] ?? 0),
            'day_unauthorized_trades_pnl' => (float)($d['day_unauthorized_trades_pnl'] ?? 0),
            'day_unauthorized_withdrawals' => (float)($d['day_unauthorized_withdrawals'] ?? 0),
            'day_closing_balance' => (float)($d['day_closing_balance'] ?? 0),
            'unusual_activity' => !empty($d['unusual_activity']),
            'authorized_trades_count' => (int)($d['authorized_trades_count'] ?? 0),
            'unauthorized_trades_count' => (int)($d['unauthorized_trades_count'] ?? 0),
        ];
    }
    return ['rows' => $rows, 'has_rows' => !empty($rows)];
}

// =====================================================================
// AJAX: LIVE ACTIVITY (JSON)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'error'=>'Unauthorized']);
        exit;
    }

    // Re-resolve sub-account on each poll
    $liveSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);
    if ($liveSubId <= 0) {
        $stmt = $pdo->prepare("SELECT sub_account_id FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
        $stmt->execute([$email]);
        $lr = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lr) $liveSubId = (int)($lr['sub_account_id'] ?? 0);
    }

    $p = buildActivityPayload($pdo, $tableName, $email, $liveSubId);
    if (!$p) {
        echo json_encode(['success'=>false,'error'=>'User not found']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'has_rows' => $p['has_rows'],
        'rows' => $p['rows'],
        'count' => count($p['rows'])
    ]);
    exit;
}

$payload = buildActivityPayload($pdo, $tableName, $email, $activeSubAccountId);
if (!$payload) { header("Location: index.php"); exit; }
$rows = $payload['rows'];
$hasRows = $payload['has_rows'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>HarvHub</title>
<?php include 'style.php'; ?>
<style>
    body {
        padding-top: calc(56px + env(safe-area-inset-top, 0px)) !important;
        padding-bottom: 60px;
        background: var(--bg);
        color: var(--text);
        margin: 0;
        transition: background 0.3s, color 0.3s;
    }
    @media (max-width: 480px) {
        body {
            padding-top: calc(52px + env(safe-area-inset-top, 0px)) !important;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="activi-container">

    <div id="activityStateBlock">
        <?php if ($hasRows): ?>
            <div class="balance-log-list">
                <?php foreach ($rows as $row): ?>
                    <div class="balance-log-item <?= $row['unusual_activity'] ? 'unusual' : '' ?>" data-key="<?= htmlspecialchars($row['key']) ?>">
                        <div class="log-header" onclick="toggleLogDetails(this)">
                            <div class="log-left">
                                <span class="log-date-main"><?= htmlspecialchars($row['formatted_date']) ?></span>
                                <span class="log-day"><?= htmlspecialchars($row['day_name']) ?></span>
                                <?php if ($row['unusual_activity']): ?>
                                    <span class="status-badge status-unusual">Unusual</span>
                                <?php endif; ?>
                            </div>
                            <span class="log-toggle">▲</span>
                        </div>
                        <div class="log-details open">
                            <div class="log-row">
                                <span class="log-label">Open Balance</span>
                                <span class="log-value">$<?= number_format($row['day_starting_balance'], 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Authorized Trades P&L</span>
                                <span class="log-value <?= $row['day_authorized_trades_pnl'] >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format($row['day_authorized_trades_pnl'], 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Trades P&L</span>
                                <span class="log-value <?= $row['day_unauthorized_trades_pnl'] >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format($row['day_unauthorized_trades_pnl'], 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Withdrawals</span>
                                <span class="log-value <?= $row['day_unauthorized_withdrawals'] > 0 ? 'loss' : '' ?>">
                                    $<?= number_format($row['day_unauthorized_withdrawals'], 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Closing Balance</span>
                                <span class="log-value">$<?= number_format($row['day_closing_balance'], 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unusual Activity</span>
                                <span class="log-value <?= $row['unusual_activity'] ? 'unusual' : '' ?>"><?= $row['unusual_activity'] ? 'Yes' : 'No' ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-text">No Activity Data</div>
                <div class="empty-sub">This account's activity log will appear here once trading begins.</div>
            </div>
        <?php endif; ?>
    </div>

    
</div>

<script>
    function toggleLogDetails(headerElement) {
        var details = headerElement.nextElementSibling;
        var toggle = headerElement.querySelector('.log-toggle');
        if (!details) return;
        if (details.classList.contains('open')) {
            details.classList.remove('open');
            if (toggle) toggle.textContent = '▼';
        } else {
            details.classList.add('open');
            if (toggle) toggle.textContent = '▲';
        }
    }

    var ACTIVITY_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('activity.php', base).toString();
        } catch (e) {
            return 'activity.php';
        }
    })();

    var isUpdating = false;
    var updateInterval = null;
    var retryCount = 0;
    var MAX_RETRIES = 5;
    var currentInterval = 1000;
    var pollRunning = true;
    var collapsedKeys = {};

    function captureExpandedState() {
        collapsedKeys = {};
        document.querySelectorAll('.balance-log-item').forEach(function(item) {
            var key = item.getAttribute('data-key');
            var details = item.querySelector('.log-details');
            if (key !== null && details && !details.classList.contains('open')) {
                collapsedKeys[key] = true;
            }
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function fmtMoney(v) {
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        return n.toFixed(2);
    }

    function buildLogItemHtml(row) {
        var isUnusual = !!row.unusual_activity;
        var authPnl = parseFloat(row.day_authorized_trades_pnl) || 0;
        var unauthPnl = parseFloat(row.day_unauthorized_trades_pnl) || 0;
        var withdrawals = parseFloat(row.day_unauthorized_withdrawals) || 0;

        var collapsed = !!collapsedKeys[row.key];
        var detailsClass = collapsed ? 'log-details' : 'log-details open';
        var toggleArrow = collapsed ? '▼' : '▲';

        var html = '';
        html += '<div class="balance-log-item ' + (isUnusual ? 'unusual' : '') + '" data-key="' + escapeHtml(row.key) + '">';
        html += '  <div class="log-header" onclick="toggleLogDetails(this)">';
        html += '    <div class="log-left">';
        html += '      <span class="log-date-main">' + escapeHtml(row.formatted_date) + '</span>';
        html += '      <span class="log-day">' + escapeHtml(row.day_name) + '</span>';
        if (isUnusual) html += '      <span class="status-badge status-unusual">Unusual</span>';
        html += '    </div>';
        html += '    <span class="log-toggle">' + toggleArrow + '</span>';
        html += '  </div>';
        html += '  <div class="' + detailsClass + '">';
        html += '    <div class="log-row"><span class="log-label">Open Balance</span><span class="log-value">$' + fmtMoney(row.day_starting_balance) + '</span></div>';
        html += '    <div class="log-row"><span class="log-label">Authorized Trades P&L</span><span class="log-value ' + (authPnl >= 0 ? 'profit' : 'loss') + '">$' + fmtMoney(authPnl) + '</span></div>';
        html += '    <div class="log-row"><span class="log-label">Unauthorized Trades P&L</span><span class="log-value ' + (unauthPnl >= 0 ? 'profit' : 'loss') + '">$' + fmtMoney(unauthPnl) + '</span></div>';
        html += '    <div class="log-row"><span class="log-label">Unauthorized Withdrawals</span><span class="log-value ' + (withdrawals > 0 ? 'loss' : '') + '">$' + fmtMoney(withdrawals) + '</span></div>';
        html += '    <div class="log-row"><span class="log-label">Closing Balance</span><span class="log-value">$' + fmtMoney(row.day_closing_balance) + '</span></div>';
        html += '    <div class="log-row"><span class="log-label">Unusual Activity</span><span class="log-value ' + (isUnusual ? 'unusual' : '') + '">' + (isUnusual ? 'Yes' : 'No') + '</span></div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    function buildActivityHtml(data) {
        if (!data.has_rows || !data.rows || data.rows.length === 0) {
            return '' +
                '<div class="empty-state">' +
                '  <div class="empty-text">No Activity Data</div>' +
                '  <div class="empty-sub">This account\'s activity log will appear here once trading begins.</div>' +
                '</div>';
        }
        var html = '<div class="balance-log-list">';
        for (var i = 0; i < data.rows.length; i++) html += buildLogItemHtml(data.rows[i]);
        html += '</div>';
        return html;
    }

    function refreshActivityUI(data) {
        if (!data || !data.success) return;
        captureExpandedState();
        var block = document.getElementById('activityStateBlock');
        if (block) block.innerHTML = buildActivityHtml(data);
    }

    async function fetchActivity() {
        if (isUpdating) return;
        isUpdating = true;
        try {
            var response = await fetch(ACTIVITY_POLL_URL, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            var raw = await response.text();
            var data;
            try { data = JSON.parse(raw); } catch (e) { throw new Error('Non-JSON'); }
            if (data && data.success) {
                retryCount = 0;
                currentInterval = 1000;
                refreshActivityUI(data);
            } else {
                retryCount++;
            }
        } catch (error) {
            retryCount++;
        } finally {
            isUpdating = false;
        }
        if (retryCount === 0) currentInterval = 1000;
        else if (retryCount === 1) currentInterval = 3000;
        else if (retryCount === 2) currentInterval = 5000;
        else if (retryCount >= MAX_RETRIES) currentInterval = 15000;
        scheduleNextPoll();
    }

    function scheduleNextPoll() {
        if (!pollRunning) return;
        if (updateInterval) clearTimeout(updateInterval);
        updateInterval = setTimeout(fetchActivity, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchActivity();
    }

    function stopLiveUpdates() {
        pollRunning = false;
        if (updateInterval) { clearTimeout(updateInterval); updateInterval = null; }
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) stopLiveUpdates();
        else startLiveUpdates();
    });

    window.addEventListener('focus', function() {
        if (pollRunning) fetchActivity();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });

    (function () {
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });
        var WATCHED = ['page-connect_investor_broker', 'profile-page-open', 'page-revenue_history'];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();
</script>
</body>
</html>