<?php
    // trades.php
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

    // Fetch server config
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    $CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 30);

    // Parse daily_target_met only (balance log moved to activity.php)
    $dailyTargetMet = [];

    if (!empty($user['daily_target_met'])) {
        $decoded = json_decode($user['daily_target_met'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $dailyTargetMet = $decoded;
        }
    }

    $fullName = $user['fullname'] ?? 'User';
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
                                <div class="daily-target-item <?= $dayData['status'] ?? '' ?> <?= isset($dayData['is_listed']) && !$dayData['is_listed'] ? 'not-listed' : '' ?>">
                                    <div class="day-label"><?= htmlspecialchars($day) ?></div>
                                    <?php if (!empty($dayData['date'])): ?>
                                        <div class="day-date"><?= htmlspecialchars($dayData['date']) ?></div>
                                    <?php endif; ?>
                                    <div class="target-amounts">
                                        <div><span class="allocated">Allocated:</span> $<?= number_format($dayData['profit_allocated'] ?? 0, 2) ?></div>
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