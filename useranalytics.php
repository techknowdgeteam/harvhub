<?php
    // useranalytics.php
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

    // Fetch user basics (id, fullname, balance, P&L, execution start)
    $stmt = $pdo->prepare("
        SELECT id, fullname, broker_balance, profitandloss, execution_start_date
        FROM $tableName
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: index.php");
        exit;
    }

    $userid = (int)$user['id'];

    // Fetch server config
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    $CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 30);

    // =====================================================================
    // Fetch analytics row from investors_analytics table
    // =====================================================================
    $authData = [];      // authorized aggregates (the only row we have now)
    $unauthData = [];    // unauthorized trades come from the unauthorized_trades table
    $summaries = [];

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM investors_analytics
            WHERE userid = ?
            LIMIT 1
        ");
        $stmt->execute([$userid]);
        $authData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $authData = [];
    }

    // =====================================================================
    // Aggregate unauthorized trades from unauthorized_trades table
    // =====================================================================
    $unauthTotalTrades    = 0;
    $unauthTotalPnl       = 0.0;
    $unauthProfitTrades   = 0;
    $unauthLossTrades     = 0;
    $unauthProfitAmount   = 0.0;
    $unauthLossAmount     = 0.0;

    try {
        $stmt = $pdo->prepare("
            SELECT pnl
            FROM unauthorized_trades
            WHERE userid = ?
        ");
        $stmt->execute([$userid]);
        $utRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($utRows as $r) {
            $pnl = (float)($r['pnl'] ?? 0);
            $unauthTotalTrades++;
            $unauthTotalPnl += $pnl;
            if ($pnl > 0) {
                $unauthProfitTrades++;
                $unauthProfitAmount += $pnl;
            } elseif ($pnl < 0) {
                $unauthLossTrades++;
                $unauthLossAmount += abs($pnl);
            }
        }
    } catch (PDOException $e) {
        // table may not exist yet — leave zeros
    }

    // =====================================================================
    // Pull the analytics values into scalars (was: JSON nesting)
    // =====================================================================
    $authTotalTrades     = (int)($authData['total_trades'] ?? 0);
    $authTotalPnl        = (float)($authData['total_pnl'] ?? 0);
    $authProfitTrades    = (int)($authData['profit_trades'] ?? 0);
    $authLossTrades      = (int)($authData['loss_trades'] ?? 0);
    $authProfitAmount    = (float)($authData['profit_amount'] ?? 0);
    $authLossAmount      = (float)($authData['loss_amount'] ?? 0);

    // Trades per day — from table columns
    $lowestTradesPerDay  = (int)($authData['lowest_trades_per_day'] ?? 0);
    $highestTradesPerDay = (int)($authData['highest_trades_per_day'] ?? 0);
    $averageTradesPerDay = (int)($authData['average_trades_per_day'] ?? 0);

    // Trades per week — from table columns (NEW cards)
    $lowestTradesPerWeek  = (int)($authData['lowest_trades_per_week'] ?? 0);
    $highestTradesPerWeek = (int)($authData['highest_trades_per_week'] ?? 0);
    $averageTradesPerWeek = (int)($authData['average_trades_per_week'] ?? 0);

    // Highest loss per trade (kept for reference, no longer shown)
    $highestLossPerTrade = (float)($authData['highest_loss_per_trade'] ?? 0);

    // ▼ Highest drawdown — now the displayed metric
    $highestDrawdown     = (float)($authData['highest_drawdown'] ?? 0);

    // Symbols traded
    $symbolsCount = (int)($authData['symbols_traded'] ?? 0);

    // Sequential losses
    $sequentialLossCount = (int)($authData['consecutive_losses_count'] ?? 0);
    $sequentialLossTotal = (float)($authData['total_loss_pnl'] ?? 0);

    // Sequential days in loss
    $sequentialDaysCount = (int)($authData['consecutive_days_in_loss_count'] ?? 0);
    $sequentialDaysTotal = (float)($authData['consecutive_days_in_loss_count_total_loss_pnl'] ?? 0);

    // Revenue percentages
    $revenuePercent       = (float)($authData['revenue_percentage'] ?? 0);
    $revenueProfitPercent = (float)($authData['revenue_profit_percentage'] ?? 0);
    $revenueLossPercent   = (float)($authData['revenue_loss_percentage'] ?? 0);

    // Win rate (derived from profit/loss trade counts in table)
    $winRate = ($authProfitTrades + $authLossTrades) > 0
        ? round(($authProfitTrades / ($authProfitTrades + $authLossTrades)) * 100, 2)
        : 0;

    // =====================================================================
    // Symbols breakdown — from authorized_trades table
    //   Aggregate: for each symbol → sum of positive pnl & abs(sum of negative pnl)
    // =====================================================================
    $symbols = [];
    try {
        $stmt = $pdo->prepare("
            SELECT symbol,
                   SUM(CASE WHEN pnl > 0 THEN pnl ELSE 0 END) AS total_profit,
                   SUM(CASE WHEN pnl < 0 THEN -pnl ELSE 0 END) AS total_loss
            FROM authorized_trades
            WHERE userid = ?
            GROUP BY symbol
            ORDER BY symbol ASC
        ");
        $stmt->execute([$userid]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $symbols[$r['symbol']] = [
                'total_profit' => (float)$r['total_profit'],
                'total_loss'   => (float)$r['total_loss'],
            ];
        }
    } catch (PDOException $e) {
        $symbols = [];
    }

    // Extract user data for display
    $fullName = $user['fullname'] ?? 'User';
    $brokerBalance   = (float)($user['broker_balance'] ?? 0);
    $profitAndLoss   = (float)($user['profitandloss'] ?? 0);
    $currentBalance  = $brokerBalance + $profitAndLoss;

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

    $hasData = ($authTotalTrades > 0) || ($unauthTotalTrades > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
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
                <!-- Highest Drawdown (replaces Highest Loss/Trade) -->
                <div class="stat-card">
                    <div class="stat-label">Highest Drawdown</div>
                    <div class="stat-value loss">
                        -$<?= number_format($highestDrawdown, 2) ?>
                    </div>
                </div>
                <!-- Sequential Losses -->
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
                <!-- Sequential Days in Loss -->
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

            <!-- Trades per Week (NEW) -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Lowest Trades/Week</div>
                    <div class="stat-value neutral">
                        <?= $lowestTradesPerWeek ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Trades/Week</div>
                    <div class="stat-value neutral">
                        <?= $averageTradesPerWeek ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Highest Trades/Week</div>
                    <div class="stat-value neutral">
                        <?= $highestTradesPerWeek ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Trades per Day</div>
                    <div class="stat-value neutral">
                        <?= $lowestTradesPerDay ?> / <?= $averageTradesPerDay ?> / <?= $highestTradesPerDay ?>
                    </div>
                    <div class="stat-sub">low / avg / high</div>
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
                                <?= ($unauthProfitTrades + $unauthLossTrades) > 0
                                        ? round(($unauthProfitTrades / ($unauthProfitTrades + $unauthLossTrades)) * 100, 1)
                                        : 0 ?>%
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