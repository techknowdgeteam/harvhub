<?php
// revenue_target.php — sub-account scoped (Daily Revenue Target)

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_email'])) { header("Location: index.php"); exit; }
$email = strtolower($_SESSION['user_email']);
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("Database connection failed."); }

// ---- Resolve active sub account ----
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT id, fullname, dark_mode, sub_account_id FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $rt_userRow = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $rt_userRow = null;
}

if (!$rt_userRow) {
    $stmt = $pdo->prepare("SELECT id, fullname, dark_mode, sub_account_id FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $rt_userRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$rt_userRow) {
    $stmt = $pdo->prepare("SELECT id, fullname, dark_mode, sub_account_id FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $rt_userRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$rt_userRow) { header("Location: index.php"); exit; }

$rt_userid = (int)$rt_userRow['id'];
$rt_subAccountId = (int)($rt_userRow['sub_account_id'] ?? $rt_userid);
$_SESSION['active_sub_account_id'] = $rt_subAccountId;

$rt_fullName = $rt_userRow['fullname'] ?? 'User';
$rt_darkMode = !empty($rt_userRow['dark_mode']);
$rt_darkModeClass = $rt_darkMode ? 'dark-mode' : '';

// =====================================================================
// PAYLOAD BUILDER — SCOPED to sub-account
// =====================================================================
if (!function_exists('rt_buildRevenueTargetPayload')) {
    function rt_buildRevenueTargetPayload($pdo, $subAccountId, $userId) {
        $out = [];
        try {
            // daily_target_revenue is scoped by userid AND sub_account_id
            // (falls back to NULL for legacy rows)
            $stmt = $pdo->prepare("
                SELECT week, day, date, daily_target, status,
                       profit_allocated, remaining_needed
                FROM daily_target_revenue
                WHERE userid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
                ORDER BY
                    CAST(REPLACE(week, 'week_', '') AS UNSIGNED) ASC,
                    FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') ASC
            ");
            $stmt->execute([$userId, $subAccountId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $wk = $r['week'];
                $day = $r['day'];
                if (!isset($out[$wk])) $out[$wk] = [];
                $out[$wk][$day] = [
                    'date'             => $r['date'],
                    'daily_target'     => $r['daily_target'],
                    'status'           => $r['status'],
                    'profit_allocated' => $r['profit_allocated'],
                    'remaining_needed' => $r['remaining_needed'],
                    'is_listed'        => true,
                ];
            }
        } catch (PDOException $e) { $out = []; }
        return $out;
    }
}

// =====================================================================
// AJAX: LIVE DAILY REVENUE TARGET (JSON)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    try {
        // Re-resolve the active sub-account on each request
        $liveSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);
        $liveUserRow = null;

        if ($liveSubId > 0) {
            $stmt = $pdo->prepare("SELECT id, sub_account_id FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
            $stmt->execute([$liveSubId, $email]);
            $liveUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$liveUserRow) {
            $stmt = $pdo->prepare("SELECT id, sub_account_id FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
            $stmt->execute([$email]);
            $liveUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$liveUserRow) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $liveUserId       = (int)$liveUserRow['id'];
        $liveSubAccountId = (int)($liveUserRow['sub_account_id'] ?? $liveUserId);

        $dailyTargetMet = rt_buildRevenueTargetPayload($pdo, $liveSubAccountId, $liveUserId);

        $weekKeys = array_keys($dailyTargetMet);
        usort($weekKeys, function ($a, $b) {
            return (int)str_replace('week_', '', $a) - (int)str_replace('week_', '', $b);
        });

        $orderedWeeks = [];
        foreach ($weekKeys as $wk) {
            $weekData = $dailyTargetMet[$wk];
            if (!is_array($weekData)) continue;
            $days = [];
            foreach ($weekData as $day => $dayData) {
                $days[] = [
                    'day'              => $day,
                    'date'             => $dayData['date'] ?? '',
                    'daily_target'     => (float)($dayData['daily_target'] ?? 0),
                    'status'           => $dayData['status'] ?? '',
                    'profit_allocated' => (float)($dayData['profit_allocated'] ?? 0),
                    'remaining_needed' => (float)($dayData['remaining_needed'] ?? 0),
                    'is_listed'        => !empty($dayData['is_listed']),
                ];
            }
            $orderedWeeks[] = [
                'week_key'   => $wk,
                'week_label' => str_replace('_', ' ', $wk),
                'days'       => $days,
            ];
        }

        echo json_encode([
            'success'  => true,
            'has_rows' => !empty($orderedWeeks),
            'weeks'    => $orderedWeeks,
            'count'    => count($orderedWeeks),
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to build revenue target payload.']);
        exit;
    }
}

// =====================================================================
// INITIAL PAGE RENDER
// =====================================================================
$rt_dailyTargetMet = rt_buildRevenueTargetPayload($pdo, $rt_subAccountId, $rt_userid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>HarvHub — Daily Revenue</title>
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
<body class="<?= htmlspecialchars($rt_darkModeClass) ?>">

<div class="trade-container">
    <div id="tab-daily-target" class="tab-content active">

        <div id="dailyRevenueStateBlock">
            <?php if (!empty($rt_dailyTargetMet) && is_array($rt_dailyTargetMet)): ?>
                <div class="daily-target-grid">
                    <?php
                    $rt_weekKeys = array_keys($rt_dailyTargetMet);
                    usort($rt_weekKeys, function ($a, $b) {
                        return (int)str_replace('week_', '', $a) - (int)str_replace('week_', '', $b);
                    });
                    foreach ($rt_weekKeys as $rt_weekKey):
                        $rt_weekData = $rt_dailyTargetMet[$rt_weekKey];
                        if (!is_array($rt_weekData)) continue;
                    ?>
                        <div class="week-section">
                            <div class="week-header">
                                <span class="week-label"><?= htmlspecialchars(str_replace('_', ' ', $rt_weekKey)) ?></span>
                            </div>
                            <div class="daily-target-items">
                                <?php foreach ($rt_weekData as $rt_day => $rt_dayData): ?>
                                    <div class="daily-target-item <?= htmlspecialchars($rt_dayData['status'] ?? '') ?> <?= isset($rt_dayData['is_listed']) && !$rt_dayData['is_listed'] ? 'not-listed' : '' ?>">
                                        <div class="day-label"><?= htmlspecialchars($rt_day) ?></div>
                                        <?php if (!empty($rt_dayData['date'])): ?>
                                            <div class="day-date"><?= htmlspecialchars($rt_dayData['date']) ?></div>
                                        <?php endif; ?>
                                        <div class="target-amounts">
                                            <div><span class="allocated">Allocated:</span> $<?= number_format((float)($rt_dayData['profit_allocated'] ?? 0), 2) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-text">No Daily Target Data</div>
                    <div class="empty-sub">This account's daily target data will appear here once available.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
</div>

<script>
    var REVENUE_TARGET_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('revenue_target.php', base).toString();
        } catch (e) {
            return 'revenue_target.php';
        }
    })();

    var isUpdating      = false;
    var updateInterval  = null;
    var retryCount      = 0;
    var MAX_RETRIES     = 5;
    var currentInterval = 1000;
    var pollRunning     = true;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function formatMoney(value) {
        var n = parseFloat(value);
        if (isNaN(n)) n = 0;
        return n.toFixed(2);
    }

    function buildDayCellHtml(day) {
        var cls = '';
        if (day.status) cls += ' ' + escapeHtml(day.status);
        if (!day.is_listed) cls += ' not-listed';

        var html = '';
        html += '<div class="daily-target-item' + cls + '">';
        html += '  <div class="day-label">' + escapeHtml(day.day) + '</div>';
        if (day.date) {
            html += '  <div class="day-date">' + escapeHtml(day.date) + '</div>';
        }
        html += '  <div class="target-amounts">';
        html += '    <div><span class="allocated">Allocated:</span> $' + formatMoney(day.profit_allocated) + '</div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    function buildDailyTargetHtml(data) {
        if (!data.has_rows || !data.weeks || data.weeks.length === 0) {
            return '' +
                '<div class="empty-state">' +
                '  <div class="empty-text">No Daily Target Data</div>' +
                '  <div class="empty-sub">This account\'s daily target data will appear here once available.</div>' +
                '</div>';
        }

        var html = '<div class="daily-target-grid">';
        for (var i = 0; i < data.weeks.length; i++) {
            var wk = data.weeks[i];
            html += '<div class="week-section">';
            html += '  <div class="week-header">';
            html += '    <span class="week-label">' + escapeHtml(wk.week_label) + '</span>';
            html += '  </div>';
            html += '  <div class="daily-target-items">';
            for (var j = 0; j < wk.days.length; j++) {
                html += buildDayCellHtml(wk.days[j]);
            }
            html += '  </div>';
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function refreshDailyRevenueUI(data) {
        if (!data || !data.success) return;
        var block = document.getElementById('dailyRevenueStateBlock');
        if (block) block.innerHTML = buildDailyTargetHtml(data);
    }

    async function fetchRevenueTarget() {
        if (isUpdating) return;
        isUpdating = true;
        try {
            var response = await fetch(REVENUE_TARGET_POLL_URL, {
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
                refreshDailyRevenueUI(data);
            } else {
                retryCount++;
            }
        } catch (error) {
            retryCount++;
        } finally {
            isUpdating = false;
        }
        if (retryCount === 0)      currentInterval = 1000;
        else if (retryCount === 1) currentInterval = 3000;
        else if (retryCount === 2) currentInterval = 5000;
        else if (retryCount >= MAX_RETRIES) currentInterval = 15000;
        scheduleNextPoll();
    }

    function scheduleNextPoll() {
        if (!pollRunning) return;
        if (updateInterval) clearTimeout(updateInterval);
        updateInterval = setTimeout(fetchRevenueTarget, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchRevenueTarget();
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
        if (pollRunning) fetchRevenueTarget();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });

    // ---- Theme + body-class bridge ----
    (function () {
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });
        var WATCHED = ['page-connect_investor_broker', 'profile-page-open', 'page-revenue_history', 'page-profit_split'];
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