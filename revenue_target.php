<?php
    // revenue_target.php
    session_start();

    // Check for logged-in user
    if (!isset($_SESSION['user_email'])) {
        header("Location: index.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    // Database credentials
    require_once 'usersdb.php';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    // Fetch user (only need id now for the join)
    $stmt = $pdo->prepare("SELECT id, fullname FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        header("Location: index.php");
        exit;
    }

    $userid   = (int)$userRow['id'];
    $fullName = $userRow['fullname'] ?? 'User';

    // =====================================================================
    // REUSABLE PAYLOAD BUILDER — used by both the initial render and AJAX
    // =====================================================================
    function buildRevenueTargetPayload($pdo, $userid) {
        $dailyTargetMet = [];

        try {
            $stmt = $pdo->prepare("
                SELECT week, day, date, daily_target, status,
                       profit_allocated, remaining_needed
                FROM daily_target_revenue
                WHERE userid = ?
                ORDER BY
                    CAST(REPLACE(week, 'week_', '') AS UNSIGNED) ASC,
                    FIELD(day, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') ASC
            ");
            $stmt->execute([$userid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $wk  = $r['week'];
                $day = $r['day'];

                if (!isset($dailyTargetMet[$wk])) {
                    $dailyTargetMet[$wk] = [];
                }

                $dailyTargetMet[$wk][$day] = [
                    'date'             => $r['date'],
                    'daily_target'     => $r['daily_target'],
                    'status'           => $r['status'],
                    'profit_allocated' => $r['profit_allocated'],
                    'remaining_needed' => $r['remaining_needed'],
                    'is_listed'        => true,
                ];
            }
        } catch (PDOException $e) {
            $dailyTargetMet = [];
        }

        return $dailyTargetMet;
    }

    // =====================================================================
    // AJAX: LIVE DAILY REVENUE TARGET (JSON)
    //
    // Runs BEFORE any HTML is emitted, discards any buffer app.php started,
    // and returns pure JSON. This is what the 1-second poll hits.
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
            // Re-fetch the user id (it can't change, but keeps the pattern consistent)
            $stmt = $pdo->prepare("SELECT id FROM $tableName WHERE email = ?");
            $stmt->execute([$email]);
            $liveRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$liveRow) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            $liveUserId = (int)$liveRow['id'];

            $dailyTargetMet = buildRevenueTargetPayload($pdo, $liveUserId);

            // Sort week keys numerically for deterministic rendering
            $weekKeys = array_keys($dailyTargetMet);
            usort($weekKeys, function($a, $b) {
                $numA = (int)str_replace('week_', '', $a);
                $numB = (int)str_replace('week_', '', $b);
                return $numA - $numB;
            });

            // Build a clean rows array (in sorted week order) to send to the client
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
                'success'   => true,
                'has_rows'  => !empty($orderedWeeks),
                'weeks'     => $orderedWeeks,
                'count'     => count($orderedWeeks),
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to build revenue target payload.'
            ]);
            exit;
        }
    }

    // =====================================================================
    // INITIAL PAGE RENDER (non-AJAX)
    // =====================================================================
    $dailyTargetMet = buildRevenueTargetPayload($pdo, $userid);
?>
<div class="trade-container">
    <!-- Daily Target Tab -->
    <div id="tab-daily-target" class="tab-content active">
        <div class="trades-header">
            <h1 style="margin-left: 10px">Daily Revenue</h1>
        </div>

        <!-- LIVE STATE BLOCK — replaced wholesale on every poll -->
        <div id="dailyRevenueStateBlock">
            <?php if (!empty($dailyTargetMet) && is_array($dailyTargetMet)): ?>
                <div class="daily-target-grid">
                    <?php
                    $weekKeys = array_keys($dailyTargetMet);
                    usort($weekKeys, function($a, $b) {
                        $numA = (int)str_replace('week_', '', $a);
                        $numB = (int)str_replace('week_', '', $b);
                        return $numA - $numB;
                    });

                    foreach ($weekKeys as $weekKey):
                        $weekData = $dailyTargetMet[$weekKey];
                        if (!is_array($weekData)) continue;
                    ?>
                        <div class="week-section">
                            <div class="week-header">
                                <span class="week-label"><?= htmlspecialchars(str_replace('_', ' ', $weekKey)) ?></span>
                            </div>
                            <div class="daily-target-items">
                                <?php foreach ($weekData as $day => $dayData): ?>
                                    <div class="daily-target-item <?= htmlspecialchars($dayData['status'] ?? '') ?> <?= isset($dayData['is_listed']) && !$dayData['is_listed'] ? 'not-listed' : '' ?>">
                                        <div class="day-label"><?= htmlspecialchars($day) ?></div>
                                        <?php if (!empty($dayData['date'])): ?>
                                            <div class="day-date"><?= htmlspecialchars($dayData['date']) ?></div>
                                        <?php endif; ?>
                                        <div class="target-amounts">
                                            <div><span class="allocated">Allocated:</span> $<?= number_format((float)($dayData['profit_allocated'] ?? 0), 2) ?></div>
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
                    <div class="empty-sub">Your daily target data will appear here once available.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div style="margin-bottom:120px"></div>
</div>

<script>
    // =====================================================================
    // LIVE POLL — same pattern as mydashboard.php / revenue_history.php
    // Target is always revenue_target.php, even when embedded in app.php.
    // =====================================================================
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

    // ---- helpers ----
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

    // ---- Build a single day cell ----
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

    // ---- Build the full grid from the JSON payload ----
    function buildDailyTargetHtml(data) {
        if (!data.has_rows || !data.weeks || data.weeks.length === 0) {
            return '' +
                '<div class="empty-state">' +
                '  <div class="empty-text">No Daily Target Data</div>' +
                '  <div class="empty-sub">Your daily target data will appear here once available.</div>' +
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

    // ---- Apply the payload to the DOM ----
    function refreshDailyRevenueUI(data) {
        if (!data || !data.success) return;

        var block = document.getElementById('dailyRevenueStateBlock');
        if (block) {
            block.innerHTML = buildDailyTargetHtml(data);
        }
    }

    // ---- Poll the server ----
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
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                throw new Error('Non-JSON response');
            }

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
        if (document.hidden) {
            stopLiveUpdates();
        } else {
            startLiveUpdates();
        }
    });

    window.addEventListener('focus', function() {
        if (pollRunning) fetchRevenueTarget();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>