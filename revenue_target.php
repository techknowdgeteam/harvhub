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
    $stmt = $pdo->prepare("SELECT id FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRow) {
        header("Location: index.php");
        exit;
    }

    $userid = (int)$userRow['id'];

    // =====================================================================
    // Fetch daily target rows directly from daily_target_revenue table
    // =====================================================================
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

        // Rebuild the nested { week_X: { Day: {...} } } structure
        foreach ($rows as $r) {
            $wk = $r['week'];
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

    $fullName = $userRow['fullname'] ?? 'User';
?>
<div class="trade-container">
    <!-- Daily Target Tab -->
    <div id="tab-daily-target" class="tab-content active">
        <div class="trades-header">
            <h1 style="margin-left: 10px">Daily Revenue</h1>
        </div>
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
                                        <?php if (isset($dayData['daily_target']) && isset($dayData['profit_allocated'])): ?>
                                        <?php endif; ?>
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
    <div style="margin-bottom:120px"></div>
</div>