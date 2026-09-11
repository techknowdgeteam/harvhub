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

    // Fetch user data - NO application_status filter
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    // Parse daily_balance_log
    $dailyBalanceLog = [];

    if (!empty($user['daily_balance_log'])) {
        $decoded = json_decode($user['daily_balance_log'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $dailyBalanceLog = $decoded;
        }
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
                    $partsA = explode('-', $a);
                    $partsB = explode('-', $b);
                    if (count($partsA) === 3 && count($partsB) === 3) {
                        $dateA = new DateTime($partsA[2] . '-' . $partsA[1] . '-' . $partsA[0]);
                        $dateB = new DateTime($partsB[2] . '-' . $partsB[1] . '-' . $partsB[0]);
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
                                <span class="log-value">$<?= number_format($dayData['day_starting_balance'] ?? 0, 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Authorized Trades P&L</span>
                                <span class="log-value <?= ($dayData['day_authorized_trades_pnl'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format($dayData['day_authorized_trades_pnl'] ?? 0, 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Trades P&L</span>
                                <span class="log-value <?= ($dayData['day_unauthorized_trades_pnl'] ?? 0) >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format($dayData['day_unauthorized_trades_pnl'] ?? 0, 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unauthorized Withdrawals</span>
                                <span class="log-value <?= ($dayData['day_unauthorized_withdrawals'] ?? 0) > 0 ? 'loss' : '' ?>">
                                    $<?= number_format($dayData['day_unauthorized_withdrawals'] ?? 0, 2) ?>
                                </span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Closing Balance</span>
                                <span class="log-value">$<?= number_format($dayData['day_closing_balance'] ?? 0, 2) ?></span>
                            </div>
                            <div class="log-row">
                                <span class="log-label">Unusual Activity</span>
                                <span class="log-value <?= $isUnusual ? 'unusual' : '' ?>"><?= $isUnusual ? 'Yes' : 'No' ?></span>
                            </div>
                            <?php if (!empty($dayData['day_unauthorized_trades'])): ?>
                                <div class="unauthorized-trades-section">
                                    <div class="log-label">Unauthorized Trades</div>
                                    <?php foreach ($dayData['day_unauthorized_trades'] as $trade): ?>
                                        <div class="trade-row-detail">
                                            <span class="trade-symbol"><?= htmlspecialchars($trade['symbol'] ?? 'N/A') ?></span>
                                            <span class="trade-pnl <?= ($trade['pnl'] ?? 0) < 0 ? 'loss' : 'profit' ?>">
                                                $<?= number_format($trade['pnl'] ?? 0, 2) ?>
                                            </span>
                                            <span class="trade-meta">Ticket: <?= htmlspecialchars($trade['ticket'] ?? 'N/A') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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