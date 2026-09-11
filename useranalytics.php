<?php
    //useranalytics.php
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
    $MIN_PROFIT_FOR_SPLIT = (float)($serverAccount['min_profit_for_split'] ?? 30);

    // Parse analytics data
    $analyticsData = [];
    if (!empty($user['analytics'])) {
        $decoded = json_decode($user['analytics'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $analyticsData = $decoded;
        }
    }

    // Extract user data for display
    $fullName = $user['fullname'] ?? 'User';
    $brokerBalance = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss = (float)($user['profitandloss'] ?? 0);
    $currentBalance = $brokerBalance + $profitAndLoss;

    // Get execution dates
    $executionStartDate = $user['execution_start_date'] ?? null;
    $formatted_start_date = 'N/A';
    $formatted_end_date = 'N/A';

    if ($executionStartDate && $executionStartDate !== '0000-00-00') {
        $start = new DateTime($executionStartDate);
        $formatted_start_date = $start->format('M d, Y');
        $end = clone $start;
        $end->modify("+{$CONTRACT_DURATION} days");
        $formatted_end_date = $end->format('M d, Y');
    }

    function getNestedValue($data, $keys, $default = 0) {
        $current = $data;
        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                return $default;
            }
            $current = $current[$key];
        }
        return $current;
    }

    // Extract trade metrics from analytics
    $fromExecution = $analyticsData['from_execution_start_date'] ?? [];

    // Get trade data (prioritize trades_within_risks_config)
    $tradeType = 'trades_within_risks_config';
    if (empty($fromExecution[$tradeType])) {
        $tradeType = 'trades_outside_risks_config';
    }

    $tradeData = $fromExecution[$tradeType] ?? [];
    $summaries = $tradeData['summaries']['summaries_of_profits_only'] ?? [];

    // Get authorized trades data
    $authData = $tradeData['regular_data']['authorized'] ?? [];
    $unauthData = $tradeData['regular_data']['unauthorized'] ?? [];

    // Summary values
    $totalLostTrades = $summaries['total_lost_trades'] ?? 0;
    $totalWonTrades = $summaries['total_won_trades'] ?? 0;
    $winRate = $totalWonTrades + $totalLostTrades > 0 
        ? round(($totalWonTrades / ($totalWonTrades + $totalLostTrades)) * 100, 2) 
        : 0;

    // Authorized trade metrics
    $authTotalTrades = $authData['total_trades'] ?? 0;
    $authTotalPnl = $authData['total_pnl'] ?? 0;
    $authProfitTrades = $authData['profit_trades'] ?? 0;
    $authLossTrades = $authData['loss_trades'] ?? 0;
    $authProfitAmount = $authData['profit_amount'] ?? 0;
    $authLossAmount = $authData['loss_amount'] ?? 0;

    // Unauthorized trade metrics
    $unauthTotalTrades = $unauthData['total_trades'] ?? 0;
    $unauthTotalPnl = $unauthData['total_pnl'] ?? 0;
    $unauthProfitTrades = $unauthData['profit_trades'] ?? 0;
    $unauthLossTrades = $unauthData['loss_trades'] ?? 0;
    $unauthProfitAmount = $unauthData['profit_amount'] ?? 0;
    $unauthLossAmount = $unauthData['loss_amount'] ?? 0;

    // Sequential losses
    $sequentialLosses = $authData['highest_sequential_losses'] ?? [];
    $sequentialLossCount = $sequentialLosses['consecutive_losses_count'] ?? 0;
    $sequentialLossTotal = $sequentialLosses['total_loss_pnl'] ?? 0;

    // Sequential days in loss
    $sequentialDaysLoss = $authData['highest_sequential_days_in_loss'] ?? [];
    $sequentialDaysCount = $sequentialDaysLoss['consecutive_days_count'] ?? 0;
    $sequentialDaysTotal = $sequentialDaysLoss['total_loss_pnl'] ?? 0;

    // Highest loss per trade
    $highestLossPerTrade = $authData['highest_loss_per_trade'] ?? 0;

    // Revenue percentages
    $revenuePercent = $summaries['revenue_percentage'] ?? 0;
    $revenueProfitPercent = $summaries['revenue_profit_percentage'] ?? 0;
    $revenueLossPercent = $summaries['revenue_loss_percentage'] ?? 0;

    // Recent risk reward
    $recentRiskReward = $summaries['recent_risk_reward'] ?? 0;

    // Symbols traded
    $symbols = $authData['all_traded_symbols'] ?? [];
    $symbolsCount = $authData['symbols_traded'] ?? 0;

    // Determine if user has any data
    $hasData = ($authTotalTrades > 0) || ($unauthTotalTrades > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>🌾Harvhub</title>
<?php include 'style.php'; ?>
</head>
<body>
<div class="custom-body page-container">
    <div class="analytics-container">
        <div class="analytics-header">
            <h1> Analytics</h1>
            <p>Trading performance and metrics</p>
            
            <div class="user-info">
                <span><strong><?= htmlspecialchars($fullName) ?></strong></span>
                <span><?= $formatted_start_date ?> to <?= $formatted_end_date ?></span>
                <span>Current Balance: <strong>$<?= number_format($currentBalance, 2) ?></strong></span>
            </div>
        </div>
            <!-- Revenue percentages summary -->
            <div class="section-card" style="background: rgba(16, 185, 129, 0.04);">
                <div class="section-title">
                    <span>Revenue Summary</span>
                </div>
                <div style="display:flex; justify-content:space-around; flex-wrap:wrap; gap:10px;">
                    <div style="text-align:center;">
                        <div style="font-size:24px;font-weight:bold;color:var(--success);"><?= number_format($revenueProfitPercent, 2) ?>%</div>
                        <div style="font-size:12px;color:var(--text-muted);">Profit Revenue</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:24px;font-weight:bold;color:var(--danger);"><?= number_format($revenueLossPercent, 2) ?>%</div>
                        <div style="font-size:12px;color:var(--text-muted);">Loss Revenue</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:24px;font-weight:bold;color:var(--text);"><?= number_format($revenuePercent, 2) ?>%</div>
                        <div style="font-size:12px;color:var(--text-muted);">Total Revenue %</div>
                    </div>
                </div>
            </div>

        <?php if ($hasData): ?>
            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total P&L</div>
                    <div class="stat-value <?= $authTotalPnl >= 0 ? 'profit' : 'loss' ?>">
                        $<?= number_format($authTotalPnl, 2) ?>
                    </div>
                </div>
                <!-- Highest Loss per Trade Card -->
                <div class="stat-card">
                    <div class="stat-label">Highest Loss/Trade</div>
                    <div class="stat-value loss">
                        -$<?= number_format($highestLossPerTrade, 2) ?>
                    </div>
                </div>
                <!-- Sequential Losses Card -->
                <div class="stat-card">
                    <div class="stat-label">Sequential Losses</div>
                    <div class="stat-value loss">
                        <?= $sequentialLossCount > 0 ? $sequentialLossCount : '0' ?>
                    </div>
                    <div class="stat-sub">Consecutive Trades</div>
                    <?php if ($sequentialLossCount > 0): ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            Total Loss: -$<?= number_format($sequentialLossTotal, 2) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Sequential Days in Loss Card -->
                <div class="stat-card">
                    <div class="stat-label">Sequential Days in Loss</div>
                    <div class="stat-value loss">
                        <?= $sequentialDaysCount > 0 ? $sequentialDaysCount : '0' ?>
                    </div>
                    <div class="stat-sub">Consecutive Days</div>
                    <?php if ($sequentialDaysCount > 0): ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            Total Loss: -$<?= number_format($sequentialDaysTotal, 2) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section: Authorized Trades -->
            <div class="section-card" style="border-left: 4px solid var(--success);">
                <div class="section-title">
                    <span> Authorized Trades</span>
                </div>
                <div class="trades-grid">
                    <div class="trade-stat-box">
                        <div class="trade-stat-label">Profit</div>
                        <div class="trade-stat-value profit">+$<?= number_format($authProfitAmount, 2) ?></div>
                    </div>
                    <div class="trade-stat-box">
                        <div class="trade-stat-label">Loss</div>
                        <div class="trade-stat-value loss">-$<?= number_format($authLossAmount, 2) ?></div>
                    </div>
                    <div class="trade-stat-box">
                        <div class="trade-stat-label">Net P&L</div>
                        <div class="trade-stat-value <?= $authTotalPnl >= 0 ? 'profit' : 'loss' ?>">
                            <?= $authTotalPnl >= 0 ? '+' : '-' ?>$<?= number_format(abs($authTotalPnl), 2) ?>
                        </div>
                    </div>
                    <div class="trade-stat-box">
                        <div class="trade-stat-label">Symbols Traded</div>
                        <div class="trade-stat-value neutral"><?= $symbolsCount ?></div>
                        <div class="trade-stat-count">unique symbols</div>
                    </div>
                </div>
            </div>

            <!-- Section: Unauthorized Trades -->
            <?php if ($unauthTotalTrades > 0): ?>
                <div class="section-card" style="border-left: 4px solid var(--danger);">
                    <div class="section-title">
                        <span> Unauthorized Trades</span>
                    </div>
                    <div class="trades-grid">
                        <div class="trade-stat-box">
                            <div class="trade-stat-label">Profit</div>
                            <div class="trade-stat-value profit">+$<?= number_format($unauthProfitAmount, 2) ?></div>
                        </div>
                        <div class="trade-stat-box">
                            <div class="trade-stat-label">Loss</div>
                            <div class="trade-stat-value loss">-$<?= number_format($unauthLossAmount, 2) ?></div>
                        </div>
                        <div class="trade-stat-box">
                            <div class="trade-stat-label">Net P&L</div>
                            <div class="trade-stat-value <?= $unauthTotalPnl >= 0 ? 'profit' : 'loss' ?>">
                                <?= $unauthTotalPnl >= 0 ? '+' : '-' ?>$<?= number_format(abs($unauthTotalPnl), 2) ?>
                            </div>
                        </div>
                        <div class="trade-stat-box">
                            <div class="trade-stat-label">Trade Ratio</div>
                            <div class="trade-stat-value neutral">
                                <?= $unauthProfitTrades + $unauthLossTrades > 0 ? round(($unauthProfitTrades / ($unauthProfitTrades + $unauthLossTrades)) * 100, 1) : 0 ?>%
                            </div>
                            <div class="trade-stat-count">win rate</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Section: Traded Symbols -->
            <?php if (!empty($symbols)): ?>
                <div class="section-card">
                    <div class="section-title">
                        <span>Traded Symbols</span>
                        <span class="badge"><?= count($symbols) ?> symbols</span>
                    </div>
                    <div class="symbols-list">
                        <?php foreach ($symbols as $symbol => $data): ?>
                            <div class="symbol-row">
                                <div class="symbol-name"><?= htmlspecialchars($symbol) ?></div>
                                <div class="symbol-pnl <?= (($data['total_profit'] ?? 0) - ($data['total_loss'] ?? 0)) >= 0 ? 'profit' : 'loss' ?>">
                                    $<?= number_format(($data['total_profit'] ?? 0) - ($data['total_loss'] ?? 0), 2) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- No Data State -->
            <div class="empty-state">
                <div class="empty-text">No Analytics Data Yet</div>
                <div class="empty-sub">Your trading analytics will appear here once you have completed some trades.</div>
            </div>
        <?php endif; ?>
    </div>
   <div style="margin-bottom:120px"></div>
</div>
</body>
</html>