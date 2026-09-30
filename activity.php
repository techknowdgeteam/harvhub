<?php
    // activity.php
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

    // =====================================================================
    // Helper: parse a date string that may be 'YYYY-MM-DD' OR 'dd-mm-yyyy'
    // =====================================================================
    function parseAnyDate($value) {
        if (empty($value)) return null;
        $value = trim($value);

        // Try ISO first
        $dt = DateTime::createFromFormat('Y-m-d', $value);
        if ($dt && $dt->format('Y-m-d') === $value) return $dt;

        // Try dd-mm-yyyy
        $dt = DateTime::createFromFormat('d-m-Y', $value);
        if ($dt && $dt->format('d-m-Y') === $value) return $dt;

        // Fallback to strtotime
        $ts = strtotime($value);
        return $ts ? new DateTime(date('Y-m-d', $ts)) : null;
    }

    // =====================================================================
    // REUSABLE PAYLOAD BUILDER — used by both the initial render and AJAX
    // =====================================================================
    function buildActivityPayload($pdo, $email, $tableName) {
        // Fetch user
        $stmt = $pdo->prepare("SELECT id, fullname FROM $tableName WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $userid = (int)$user['id'];

        // Fetch daily balance log rows
        $dailyBalanceLog = [];

        try {
            $stmt = $pdo->prepare("
                SELECT date, day_starting_balance, day_authorized_trades_pnl,
                       day_unauthorized_trades_pnl, day_unauthorized_withdrawals,
                       day_closing_balance, unusual_activity,
                       authorized_trades_count, unauthorized_trades_count
                FROM balance_log
                WHERE userid = ?
            ");
            $stmt->execute([$userid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $r) {
                $dt = parseAnyDate($r['date']);
                if (!$dt) continue;

                $key = $dt->format('d-m-Y');

                $dailyBalanceLog[$key] = [
                    'day_starting_balance'          => $r['day_starting_balance'],
                    'day_authorized_trades_pnl'     => $r['day_authorized_trades_pnl'],
                    'day_unauthorized_trades_pnl'   => $r['day_unauthorized_trades_pnl'],
                    'day_unauthorized_withdrawals'  => $r['day_unauthorized_withdrawals'],
                    'day_closing_balance'           => $r['day_closing_balance'],
                    'unusual_activity'              => (bool)$r['unusual_activity'],
                    'authorized_trades_count'       => $r['authorized_trades_count'],
                    'unauthorized_trades_count'     => $r['unauthorized_trades_count'],
                ];
            }
        } catch (PDOException $e) {
            $dailyBalanceLog = [];
        }

        // Sort dates descending
        $logDates = array_keys($dailyBalanceLog);
        usort($logDates, function($a, $b) {
            $dateA = DateTime::createFromFormat('d-m-Y', $a);
            $dateB = DateTime::createFromFormat('d-m-Y', $b);
            if ($dateA && $dateB) {
                return $dateB <=> $dateA;
            }
            return strcmp($b, $a);
        });

        // Build ordered rows for the client
        $orderedRows = [];
        foreach ($logDates as $key) {
            $dayData = $dailyBalanceLog[$key];
            if (!is_array($dayData)) continue;

            $dateObj = DateTime::createFromFormat('d-m-Y', $key);
            if (!$dateObj) continue;

            $orderedRows[] = [
                'key'                           => $key,
                'formatted_date'                => $dateObj->format('M d, Y'),
                'day_name'                      => $dateObj->format('l'),
                'day_starting_balance'          => (float)($dayData['day_starting_balance'] ?? 0),
                'day_authorized_trades_pnl'     => (float)($dayData['day_authorized_trades_pnl'] ?? 0),
                'day_unauthorized_trades_pnl'   => (float)($dayData['day_unauthorized_trades_pnl'] ?? 0),
                'day_unauthorized_withdrawals'  => (float)($dayData['day_unauthorized_withdrawals'] ?? 0),
                'day_closing_balance'           => (float)($dayData['day_closing_balance'] ?? 0),
                'unusual_activity'              => !empty($dayData['unusual_activity']),
                'authorized_trades_count'       => (int)($dayData['authorized_trades_count'] ?? 0),
                'unauthorized_trades_count'     => (int)($dayData['unauthorized_trades_count'] ?? 0),
            ];
        }

        return [
            'user'      => $user,
            'userid'    => $userid,
            'fullName'  => $user['fullname'] ?? 'User',
            'rows'      => $orderedRows,
            'has_rows'  => !empty($orderedRows),
        ];
    }

    // =====================================================================
    // AJAX: LIVE ACTIVITY (JSON)
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
            $payload = buildActivityPayload($pdo, $email, $tableName);

            if (!$payload) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            echo json_encode([
                'success'   => true,
                'has_rows'  => $payload['has_rows'],
                'rows'      => $payload['rows'],
                'count'     => count($payload['rows']),
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error'   => 'Failed to build activity payload.'
            ]);
            exit;
        }
    }

    // =====================================================================
    // INITIAL PAGE RENDER (non-AJAX)
    // =====================================================================
    $payload = buildActivityPayload($pdo, $email, $tableName);

    if (!$payload) {
        header("Location: index.php");
        exit;
    }

    $fullName = $payload['fullName'];
    $rows     = $payload['rows'];
    $hasRows  = $payload['has_rows'];
?>
<div class="activi-container">
    <div class="activity-header">
        <h1>Activities</h1>
        <p>Your Daily Balance Log</p>
    </div>

    <!-- LIVE STATE BLOCK — replaced wholesale on every poll -->
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
                <div class="empty-sub">Your activity log will appear here once trading begins.</div>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-bottom:120px"></div>
</div>

<script>
    // =====================================================================
    // CLICK-TO-EXPAND (delegated so it survives innerHTML swaps)
    // =====================================================================
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

    // =====================================================================
    // LIVE POLL — same pattern as mydashboard.php / revenue_history.php
    // =====================================================================
    var ACTIVITY_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('activity.php', base).toString();
        } catch (e) {
            return 'activity.php';
        }
    })();

    var isUpdating      = false;
    var updateInterval  = null;
    var retryCount      = 0;
    var MAX_RETRIES     = 5;
    var currentInterval = 1000;
    var pollRunning     = true;

    // Track which rows the user has collapsed (by key)
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

    // ---- Build a single log item ----
    function buildLogItemHtml(row) {
        var isUnusual = !!row.unusual_activity;
        var authPnl   = parseFloat(row.day_authorized_trades_pnl) || 0;
        var unauthPnl = parseFloat(row.day_unauthorized_trades_pnl) || 0;
        var withdrawals = parseFloat(row.day_unauthorized_withdrawals) || 0;

        var collapsed = !!collapsedKeys[row.key];
        var detailsClass = collapsed ? 'log-details' : 'log-details open';
        var toggleArrow  = collapsed ? '▼' : '▲';

        var html = '';
        html += '<div class="balance-log-item ' + (isUnusual ? 'unusual' : '') + '" data-key="' + escapeHtml(row.key) + '">';
        html += '  <div class="log-header" onclick="toggleLogDetails(this)">';
        html += '    <div class="log-left">';
        html += '      <span class="log-date-main">' + escapeHtml(row.formatted_date) + '</span>';
        html += '      <span class="log-day">' + escapeHtml(row.day_name) + '</span>';
        if (isUnusual) {
            html += '      <span class="status-badge status-unusual">Unusual</span>';
        }
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
                '  <div class="empty-sub">Your activity log will appear here once trading begins.</div>' +
                '</div>';
        }

        var html = '<div class="balance-log-list">';
        for (var i = 0; i < data.rows.length; i++) {
            html += buildLogItemHtml(data.rows[i]);
        }
        html += '</div>';
        return html;
    }

    function refreshActivityUI(data) {
        if (!data || !data.success) return;

        captureExpandedState();

        var block = document.getElementById('activityStateBlock');
        if (block) {
            block.innerHTML = buildActivityHtml(data);
        }
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
            try {
                data = JSON.parse(raw);
            } catch (parseErr) {
                throw new Error('Non-JSON response');
            }

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

        if (retryCount === 0)      currentInterval = 1000;
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
        if (document.hidden) {
            stopLiveUpdates();
        } else {
            startLiveUpdates();
        }
    });

    window.addEventListener('focus', function() {
        if (pollRunning) fetchActivity();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>