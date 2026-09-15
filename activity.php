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

    // Fetch user - we only need id now
    $stmt = $pdo->prepare("SELECT id, fullname FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    $userid = (int)$user['id'];

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
    // Fetch daily balance log rows directly from balance_log table
    // =====================================================================
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

            // Normalize the key to dd-mm-yyyy for the template
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

    $fullName = $user['fullname'] ?? 'User';
?>
<div class="activity-container">
    <div class="activity-header">
        <h1>Activities</h1>
        <p>Your Daily Balance Log</p>
    </div>

    <!-- Balance Log -->
    <div id="tab-balance-log" class="tab-content active">
        <?php if (!empty($dailyBalanceLog) && is_array($dailyBalanceLog)): ?>
            <div class="balance-log-list">
                <?php
                $logDates = array_keys($dailyBalanceLog);
                usort($logDates, function($a, $b) {
                    $dateA = DateTime::createFromFormat('d-m-Y', $a);
                    $dateB = DateTime::createFromFormat('d-m-Y', $b);
                    if ($dateA && $dateB) {
                        return $dateB <=> $dateA;
                    }
                    return strcmp($b, $a);
                });

                foreach ($logDates as $date):
                    $dayData = $dailyBalanceLog[$date];
                    if (!is_array($dayData)) continue;

                    $dateObj = DateTime::createFromFormat('d-m-Y', $date);
                    if (!$dateObj) continue;
                    $dayName = $dateObj->format('l');
                    $formattedDate = $dateObj->format('M d, Y');

                    $isUnusual = $dayData['unusual_activity'] ?? false;
                ?>
                    <div class="balance-log-item <?= $isUnusual ? 'unusual' : '' ?>">
                        <div class="log-header" onclick="toggleLogDetails(this)">
                            <div class="log-left">
                                <span class="log-date-main"><?= $formattedDate ?></span>
                                <span class="log-day"><?= $dayName ?></span>
                                <?php if ($isUnusual): ?>
                                    <span class="status-badge status-unusual">
                                        Unusual
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="log-toggle">▲</span>
                        </div>
                        <div class="log-details open">
                            <div class="log-row">
                                <span class="log-label">Open Balance</span>
                                <span class="log-value">$<?= number_format((float)($dayData['day_starting_balance'] ?? 0), 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Authorized Trades P&L</span>
                                <span class="log-value <?= ($dayData['day_authorized_trades_pnl'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format((float)($dayData['day_authorized_trades_pnl'] ?? 0), 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Trades P&L</span>
                                <span class="log-value <?= ($dayData['day_unauthorized_trades_pnl'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format((float)($dayData['day_unauthorized_trades_pnl'] ?? 0), 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Withdrawals</span>
                                <span class="log-value <?= ($dayData['day_unauthorized_withdrawals'] ?? 0) > 0 ? 'loss' : '' ?>">
                                    $<?= number_format((float)($dayData['day_unauthorized_withdrawals'] ?? 0), 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Closing Balance</span>
                                <span class="log-value">$<?= number_format((float)($dayData['day_closing_balance'] ?? 0), 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unusual Activity</span>
                                <span class="log-value <?= $isUnusual ? 'unusual' : '' ?>"><?= $isUnusual ? 'Yes' : 'No' ?></span>
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
    function toggleLogDetails(headerElement) {
        var details = headerElement.nextElementSibling;
        var toggle = headerElement.querySelector('.log-toggle');

        if (details.classList.contains('open')) {
            details.classList.remove('open');
            if (toggle) toggle.textContent = '▼';
        } else {
            details.classList.add('open');
            if (toggle) toggle.textContent = '▲';
        }
    }
</script>