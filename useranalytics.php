<?php
// useranalytics.php — sub-account scoped
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

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
// RESOLVE ACTIVE SUB-ACCOUNT
// =====================================================================
function resolveActiveSubAccount($pdo, $tableName, $email) {
    $email = strtolower(trim($email));
    $activeSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);

    if ($activeSubId > 0) {
        $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
        $q->execute([$activeSubId, $email]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
    }

    $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $q->execute([$email]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if ($row) return $row;

    $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $q->execute([$email]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

$activeRow = resolveActiveSubAccount($pdo, $tableName, $email);
if (!$activeRow) {
    header("Location: index.php");
    exit;
}

$activeSubAccountId = (int)($activeRow['sub_account_id'] ?? $activeRow['id']);
$activeUserId       = (int)$activeRow['id'];
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

// =====================================================================
// REUSABLE PAYLOAD BUILDER — SCOPED to sub-account
// =====================================================================
function buildAnalyticsPayload($pdo, $activeRow, $activeSubAccountId, $tableName, $serverAccountTable) {
    $userid = (int)$activeRow['id'];
    $subAccountId = $activeSubAccountId;

    // Fetch server config
    $stmt = $pdo->prepare("SELECT * FROM $serverAccountTable LIMIT 1");
    $stmt->execute();
    $serverAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    $CONTRACT_DURATION = (int)($serverAccount['contract_duration'] ?? 30);

    // ---- analytics row scoped by sub_account_id ----
    $authData = [];
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM investors_analytics
            WHERE userid = ?
              AND (sub_account_id = ? OR sub_account_id IS NULL)
            LIMIT 1
        ");
        $stmt->execute([$userid, $subAccountId]);
        $authData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        $authData = [];
    }

    // ---- unauthorized trades scoped by sub_account_id ----
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
              AND (sub_account_id = ? OR sub_account_id IS NULL)
        ");
        $stmt->execute([$userid, $subAccountId]);
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
        // leave zeros
    }

    // ---- scalars from authData ----
    $authTotalTrades     = (int)($authData['total_trades'] ?? 0);
    $authTotalPnl        = (float)($authData['total_pnl'] ?? 0);
    $authProfitTrades    = (int)($authData['profit_trades'] ?? 0);
    $authLossTrades      = (int)($authData['loss_trades'] ?? 0);
    $authProfitAmount    = (float)($authData['profit_amount'] ?? 0);
    $authLossAmount      = (float)($authData['loss_amount'] ?? 0);

    $lowestTradesPerDay  = (int)($authData['lowest_trades_per_day'] ?? 0);
    $highestTradesPerDay = (int)($authData['highest_trades_per_day'] ?? 0);
    $averageTradesPerDay = (int)($authData['average_trades_per_day'] ?? 0);

    $lowestTradesPerWeek  = (int)($authData['lowest_trades_per_week'] ?? 0);
    $highestTradesPerWeek = (int)($authData['highest_trades_per_week'] ?? 0);
    $averageTradesPerWeek = (int)($authData['average_trades_per_week'] ?? 0);

    $highestLossPerTrade = (float)($authData['highest_loss_per_trade'] ?? 0);
    $highestDrawdown     = (float)($authData['highest_drawdown'] ?? 0);

    $symbolsCount = (int)($authData['symbols_traded'] ?? 0);

    $sequentialLossCount = (int)($authData['consecutive_losses_count'] ?? 0);
    $sequentialLossTotal = (float)($authData['total_loss_pnl'] ?? 0);

    $sequentialDaysCount = (int)($authData['consecutive_days_in_loss_count'] ?? 0);
    $sequentialDaysTotal = (float)($authData['consecutive_days_in_loss_count_total_loss_pnl'] ?? 0);

    $revenuePercent       = (float)($authData['revenue_percentage'] ?? 0);
    $revenueProfitPercent = (float)($authData['revenue_profit_percentage'] ?? 0);
    $revenueLossPercent   = (float)($authData['revenue_loss_percentage'] ?? 0);

    $winRate = ($authProfitTrades + $authLossTrades) > 0
        ? round(($authProfitTrades / ($authProfitTrades + $authLossTrades)) * 100, 2)
        : 0;

    // ---- symbols breakdown scoped by sub_account_id ----
    $symbols = [];
    try {
        $stmt = $pdo->prepare("
            SELECT symbol,
                   SUM(CASE WHEN pnl > 0 THEN pnl ELSE 0 END) AS total_profit,
                   SUM(CASE WHEN pnl < 0 THEN -pnl ELSE 0 END) AS total_loss
            FROM authorized_trades
            WHERE userid = ?
              AND (sub_account_id = ? OR sub_account_id IS NULL)
            GROUP BY symbol
            ORDER BY symbol ASC
        ");
        $stmt->execute([$userid, $subAccountId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $symbols[$r['symbol']] = [
                'total_profit' => (float)$r['total_profit'],
                'total_loss'   => (float)$r['total_loss'],
            ];
        }
    } catch (PDOException $e) {
        $symbols = [];
    }

    // ---- user display data ----
    $fullName       = $activeRow['fullname'] ?? 'User';
    $brokerBalance  = (float)($activeRow['broker_balance'] ?? 0);
    $profitAndLoss  = (float)($activeRow['profitandloss'] ?? 0);
    $currentBalance = $brokerBalance + $profitAndLoss;

    $executionStartDate   = $activeRow['execution_start_date'] ?? null;
    $formatted_start_date = 'N/A';
    $formatted_end_date   = 'N/A';

    if ($executionStartDate && $executionStartDate !== '0000-00-00') {
        $start = new DateTime($executionStartDate);
        $formatted_start_date = $start->format('M d, Y');
        $end = clone $start;
        $end->modify("+{$CONTRACT_DURATION} days");
        $formatted_end_date = $end->format('M d, Y');
    }

    $hasData = ($authTotalTrades > 0) || ($unauthTotalTrades > 0);

    return [
        'user'      => $activeRow,
        'userid'    => $userid,
        'fullName'  => $fullName,
        'formatted_start_date' => $formatted_start_date,
        'formatted_end_date'   => $formatted_end_date,
        'currentBalance'       => $currentBalance,
        'hasData'              => $hasData,

        'revenuePercent'       => $revenuePercent,
        'revenueProfitPercent' => $revenueProfitPercent,
        'revenueLossPercent'   => $revenueLossPercent,

        'authTotalPnl'         => $authTotalPnl,
        'highestDrawdown'      => $highestDrawdown,
        'sequentialLossCount'  => $sequentialLossCount,
        'sequentialLossTotal'  => $sequentialLossTotal,
        'sequentialDaysCount'  => $sequentialDaysCount,
        'sequentialDaysTotal'  => $sequentialDaysTotal,

        'lowestTradesPerWeek'  => $lowestTradesPerWeek,
        'averageTradesPerWeek' => $averageTradesPerWeek,
        'highestTradesPerWeek' => $highestTradesPerWeek,
        'lowestTradesPerDay'   => $lowestTradesPerDay,
        'averageTradesPerDay'  => $averageTradesPerDay,
        'highestTradesPerDay'  => $highestTradesPerDay,

        'authProfitAmount'     => $authProfitAmount,
        'authLossAmount'       => $authLossAmount,
        'symbolsCount'         => $symbolsCount,

        'unauthTotalTrades'    => $unauthTotalTrades,
        'unauthTotalPnl'       => $unauthTotalPnl,
        'unauthProfitTrades'   => $unauthProfitTrades,
        'unauthLossTrades'     => $unauthLossTrades,
        'unauthProfitAmount'   => $unauthProfitAmount,
        'unauthLossAmount'     => $unauthLossAmount,

        'symbols'              => $symbols,
    ];
}

// =====================================================================
// AJAX: LIVE ANALYTICS (JSON)
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
        // Re-resolve active sub-account on each poll
        $liveRow = resolveActiveSubAccount($pdo, $tableName, $email);
        if (!$liveRow) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }
        $liveSubId = (int)($liveRow['sub_account_id'] ?? $liveRow['id']);

        $payload = buildAnalyticsPayload($pdo, $liveRow, $liveSubId, $tableName, $serverAccountTable);

        $symbolsOut = [];
        foreach ($payload['symbols'] as $sym => $data) {
            $symbolsOut[] = [
                'symbol'       => $sym,
                'total_profit' => $data['total_profit'],
                'total_loss'   => $data['total_loss'],
                'net'          => $data['total_profit'] - $data['total_loss'],
            ];
        }

        echo json_encode([
            'success'              => true,
            'has_data'             => $payload['hasData'],
            'full_name'            => $payload['fullName'],
            'start_date'           => $payload['formatted_start_date'],
            'end_date'             => $payload['formatted_end_date'],
            'current_balance'      => number_format($payload['currentBalance'], 2),

            'revenue_percent'        => number_format($payload['revenuePercent'], 2, '.', ''),
            'revenue_profit_percent' => number_format($payload['revenueProfitPercent'], 2, '.', ''),
            'revenue_loss_percent'   => number_format($payload['revenueLossPercent'], 2, '.', ''),

            'auth_total_pnl'        => number_format($payload['authTotalPnl'], 2, '.', ''),
            'highest_drawdown'      => number_format($payload['highestDrawdown'], 2, '.', ''),
            'sequential_loss_count' => $payload['sequentialLossCount'],
            'sequential_loss_total' => number_format($payload['sequentialLossTotal'], 2, '.', ''),
            'sequential_days_count' => $payload['sequentialDaysCount'],
            'sequential_days_total' => number_format($payload['sequentialDaysTotal'], 2, '.', ''),

            'lowest_trades_per_week'  => $payload['lowestTradesPerWeek'],
            'average_trades_per_week' => $payload['averageTradesPerWeek'],
            'highest_trades_per_week' => $payload['highestTradesPerWeek'],
            'lowest_trades_per_day'   => $payload['lowestTradesPerDay'],
            'average_trades_per_day'  => $payload['averageTradesPerDay'],
            'highest_trades_per_day'  => $payload['highestTradesPerDay'],

            'auth_profit_amount'    => number_format($payload['authProfitAmount'], 2, '.', ''),
            'auth_loss_amount'      => number_format($payload['authLossAmount'], 2, '.', ''),
            'symbols_count'         => $payload['symbolsCount'],

            'unauth_total_trades'   => $payload['unauthTotalTrades'],
            'unauth_total_pnl'      => number_format($payload['unauthTotalPnl'], 2, '.', ''),
            'unauth_profit_trades'  => $payload['unauthProfitTrades'],
            'unauth_loss_trades'    => $payload['unauthLossTrades'],
            'unauth_profit_amount'  => number_format($payload['unauthProfitAmount'], 2, '.', ''),
            'unauth_loss_amount'    => number_format($payload['unauthLossAmount'], 2, '.', ''),
            'unauth_win_rate'       => ($payload['unauthProfitTrades'] + $payload['unauthLossTrades']) > 0
                ? round(($payload['unauthProfitTrades'] / ($payload['unauthProfitTrades'] + $payload['unauthLossTrades'])) * 100, 1)
                : 0,

            'symbols'               => $symbolsOut,
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to build analytics payload.'
        ]);
        exit;
    }
}

// =====================================================================
// INITIAL PAGE RENDER (non-AJAX)
// =====================================================================
$payload = buildAnalyticsPayload($pdo, $activeRow, $activeSubAccountId, $tableName, $serverAccountTable);

$fullName             = $payload['fullName'];
$formatted_start_date = $payload['formatted_start_date'];
$formatted_end_date   = $payload['formatted_end_date'];
$currentBalance       = $payload['currentBalance'];
$hasData              = $payload['hasData'];

$revenuePercent       = $payload['revenuePercent'];
$revenueProfitPercent = $payload['revenueProfitPercent'];
$revenueLossPercent   = $payload['revenueLossPercent'];

$authTotalPnl         = $payload['authTotalPnl'];
$highestDrawdown      = $payload['highestDrawdown'];
$sequentialLossCount  = $payload['sequentialLossCount'];
$sequentialLossTotal  = $payload['sequentialLossTotal'];
$sequentialDaysCount  = $payload['sequentialDaysCount'];
$sequentialDaysTotal  = $payload['sequentialDaysTotal'];

$lowestTradesPerWeek  = $payload['lowestTradesPerWeek'];
$averageTradesPerWeek = $payload['averageTradesPerWeek'];
$highestTradesPerWeek = $payload['highestTradesPerWeek'];
$lowestTradesPerDay   = $payload['lowestTradesPerDay'];
$averageTradesPerDay  = $payload['averageTradesPerDay'];
$highestTradesPerDay  = $payload['highestTradesPerDay'];

$authProfitAmount     = $payload['authProfitAmount'];
$authLossAmount       = $payload['authLossAmount'];
$symbolsCount         = $payload['symbolsCount'];

$unauthTotalTrades    = $payload['unauthTotalTrades'];
$unauthTotalPnl       = $payload['unauthTotalPnl'];
$unauthProfitTrades   = $payload['unauthProfitTrades'];
$unauthLossTrades     = $payload['unauthLossTrades'];
$unauthProfitAmount   = $payload['unauthProfitAmount'];
$unauthLossAmount     = $payload['unauthLossAmount'];

$symbols              = $payload['symbols'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>🌾Harvhub</title>
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
<body>
<div class="custom-body page-container">
    <div class="analytics-container">
        <div class="analytics-header">
            <p>Trading performance and metrics</p>

            <div class="user-info">
                <span><strong><?= htmlspecialchars($fullName) ?></strong></span>
                <span><?= $formatted_start_date ?> to <?= $formatted_end_date ?></span>
                <span>Current Balance: <strong>$<?= number_format($currentBalance, 2) ?></strong></span>
            </div>
        </div>

        <div id="analyticsStateBlock">
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
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total P&L</div>
                        <div class="stat-value <?= $authTotalPnl >= 0 ? 'profit' : 'loss' ?>">
                            $<?= number_format($authTotalPnl, 2) ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Highest Drawdown</div>
                        <div class="stat-value loss">
                            -$<?= number_format($highestDrawdown, 2) ?>
                        </div>
                    </div>
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

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Lowest Trades/Week</div>
                        <div class="stat-value neutral"><?= $lowestTradesPerWeek ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Average Trades/Week</div>
                        <div class="stat-value neutral"><?= $averageTradesPerWeek ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Highest Trades/Week</div>
                        <div class="stat-value neutral"><?= $highestTradesPerWeek ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Trades per Day</div>
                        <div class="stat-value neutral">
                            <?= $lowestTradesPerDay ?> / <?= $averageTradesPerDay ?> / <?= $highestTradesPerDay ?>
                        </div>
                        <div class="stat-sub">low / avg / high</div>
                    </div>
                </div>

                <div class="section-card" style="">
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

                <?php if ($unauthTotalTrades > 0): ?>
                    <div class="section-card" >
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
                <div class="empty-state">
                    <div class="empty-text">No Analytics Data Yet</div>
                    <div class="empty-sub">This account's trading analytics will appear here once you have completed some trades.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    var ANALYTICS_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('useranalytics.php', base).toString();
        } catch (e) {
            return 'useranalytics.php';
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

    function fmtMoney(v) {
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        return n.toFixed(2);
    }

    function buildAnalyticsHtml(data) {
        var html = '';

        html += '<div class="section-card" style="background: rgba(16, 185, 129, 0.04);">';
        html += '  <div class="section-title"><span>Revenue Summary</span></div>';
        html += '  <div style="display:flex; justify-content:space-around; flex-wrap:wrap; gap:10px;">';
        html += '    <div style="text-align:center;">';
        html += '      <div style="font-size:24px;font-weight:bold;color:var(--success);">' + escapeHtml(data.revenue_profit_percent) + '%</div>';
        html += '      <div style="font-size:12px;color:var(--text-muted);">Profit Revenue</div>';
        html += '    </div>';
        html += '    <div style="text-align:center;">';
        html += '      <div style="font-size:24px;font-weight:bold;color:var(--danger);">' + escapeHtml(data.revenue_loss_percent) + '%</div>';
        html += '      <div style="font-size:12px;color:var(--text-muted);">Loss Revenue</div>';
        html += '    </div>';
        html += '    <div style="text-align:center;">';
        html += '      <div style="font-size:24px;font-weight:bold;color:var(--text);">' + escapeHtml(data.revenue_percent) + '%</div>';
        html += '      <div style="font-size:12px;color:var(--text-muted);">Total Revenue %</div>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        if (!data.has_data) {
            html += '<div class="empty-state">';
            html += '  <div class="empty-text">No Analytics Data Yet</div>';
            html += '  <div class="empty-sub">This account\'s trading analytics will appear here once you have completed some trades.</div>';
            html += '</div>';
            return html;
        }

        var pnl = parseFloat(data.auth_total_pnl) || 0;

        html += '<div class="stats-grid">';

        html += '  <div class="stat-card">';
        html += '    <div class="stat-label">Total P&L</div>';
        html += '    <div class="stat-value ' + (pnl >= 0 ? 'profit' : 'loss') + '">$' + fmtMoney(data.auth_total_pnl) + '</div>';
        html += '  </div>';

        html += '  <div class="stat-card">';
        html += '    <div class="stat-label">Highest Drawdown</div>';
        html += '    <div class="stat-value loss">-$' + fmtMoney(data.highest_drawdown) + '</div>';
        html += '  </div>';

        html += '  <div class="stat-card">';
        html += '    <div class="stat-label">Sequential Losses</div>';
        html += '    <div class="stat-value loss">' + (parseInt(data.sequential_loss_count) > 0 ? parseInt(data.sequential_loss_count) : 0) + '</div>';
        html += '    <div class="stat-sub">Consecutive Trades</div>';
        if (parseInt(data.sequential_loss_count) > 0) {
            html += '    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Total Loss: -$' + fmtMoney(data.sequential_loss_total) + '</div>';
        }
        html += '  </div>';

        html += '  <div class="stat-card">';
        html += '    <div class="stat-label">Sequential Days in Loss</div>';
        html += '    <div class="stat-value loss">' + (parseInt(data.sequential_days_count) > 0 ? parseInt(data.sequential_days_count) : 0) + '</div>';
        html += '    <div class="stat-sub">Consecutive Days</div>';
        if (parseInt(data.sequential_days_count) > 0) {
            html += '    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Total Loss: -$' + fmtMoney(data.sequential_days_total) + '</div>';
        }
        html += '  </div>';

        html += '</div>';

        html += '<div class="stats-grid">';
        html += '  <div class="stat-card"><div class="stat-label">Lowest Trades/Week</div><div class="stat-value neutral">' + parseInt(data.lowest_trades_per_week) + '</div></div>';
        html += '  <div class="stat-card"><div class="stat-label">Average Trades/Week</div><div class="stat-value neutral">' + parseInt(data.average_trades_per_week) + '</div></div>';
        html += '  <div class="stat-card"><div class="stat-label">Highest Trades/Week</div><div class="stat-value neutral">' + parseInt(data.highest_trades_per_week) + '</div></div>';
        html += '  <div class="stat-card">';
        html += '    <div class="stat-label">Trades per Day</div>';
        html += '    <div class="stat-value neutral">' + parseInt(data.lowest_trades_per_day) + ' / ' + parseInt(data.average_trades_per_day) + ' / ' + parseInt(data.highest_trades_per_day) + '</div>';
        html += '    <div class="stat-sub">low / avg / high</div>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="section-card" >';
        html += '  <div class="section-title"><span> Authorized Trades</span></div>';
        html += '  <div class="trades-grid">';
        html += '    <div class="trade-stat-box"><div class="trade-stat-label">Profit</div><div class="trade-stat-value profit">+$' + fmtMoney(data.auth_profit_amount) + '</div></div>';
        html += '    <div class="trade-stat-box"><div class="trade-stat-label">Loss</div><div class="trade-stat-value loss">-$' + fmtMoney(data.auth_loss_amount) + '</div></div>';
        html += '    <div class="trade-stat-box"><div class="trade-stat-label">Net P&L</div><div class="trade-stat-value ' + (pnl >= 0 ? 'profit' : 'loss') + '">' + (pnl >= 0 ? '+' : '-') + '$' + fmtMoney(Math.abs(pnl)) + '</div></div>';
        html += '    <div class="trade-stat-box"><div class="trade-stat-label">Symbols Traded</div><div class="trade-stat-value neutral">' + parseInt(data.symbols_count) + '</div><div class="trade-stat-count">unique symbols</div></div>';
        html += '  </div>';
        html += '</div>';

        if (parseInt(data.unauth_total_trades) > 0) {
            var uPnl = parseFloat(data.unauth_total_pnl) || 0;
            html += '<div class="section-card" >';
            html += '  <div class="section-title"><span> Unauthorized Trades</span></div>';
            html += '  <div class="trades-grid">';
            html += '    <div class="trade-stat-box"><div class="trade-stat-label">Profit</div><div class="trade-stat-value profit">+$' + fmtMoney(data.unauth_profit_amount) + '</div></div>';
            html += '    <div class="trade-stat-box"><div class="trade-stat-label">Loss</div><div class="trade-stat-value loss">-$' + fmtMoney(data.unauth_loss_amount) + '</div></div>';
            html += '    <div class="trade-stat-box"><div class="trade-stat-label">Net P&L</div><div class="trade-stat-value ' + (uPnl >= 0 ? 'profit' : 'loss') + '">' + (uPnl >= 0 ? '+' : '-') + '$' + fmtMoney(Math.abs(uPnl)) + '</div></div>';
            html += '    <div class="trade-stat-box"><div class="trade-stat-label">Trade Ratio</div><div class="trade-stat-value neutral">' + escapeHtml(data.unauth_win_rate) + '%</div><div class="trade-stat-count">win rate</div></div>';
            html += '  </div>';
            html += '</div>';
        }

        if (data.symbols && data.symbols.length > 0) {
            html += '<div class="section-card">';
            html += '  <div class="section-title"><span>Traded Symbols</span><span class="badge">' + data.symbols.length + ' symbols</span></div>';
            html += '  <div class="symbols-list">';
            for (var i = 0; i < data.symbols.length; i++) {
                var s = data.symbols[i];
                var net = parseFloat(s.net) || 0;
                html += '    <div class="symbol-row">';
                html += '      <div class="symbol-name">' + escapeHtml(s.symbol) + '</div>';
                html += '      <div class="symbol-pnl ' + (net >= 0 ? 'profit' : 'loss') + '">$' + fmtMoney(net) + '</div>';
                html += '    </div>';
            }
            html += '  </div>';
            html += '</div>';
        }

        return html;
    }

    function refreshAnalyticsUI(data) {
        if (!data || !data.success) return;

        var block = document.getElementById('analyticsStateBlock');
        if (block) {
            block.innerHTML = buildAnalyticsHtml(data);
        }

        var info = document.querySelector('.analytics-header .user-info');
        if (info) {
            info.innerHTML =
                '<span><strong>' + escapeHtml(data.full_name) + '</strong></span>' +
                '<span>' + escapeHtml(data.start_date) + ' to ' + escapeHtml(data.end_date) + '</span>' +
                '<span>Current Balance: <strong>$' + escapeHtml(data.current_balance) + '</strong></span>';
        }
    }

    async function fetchAnalytics() {
        if (isUpdating) return;
        isUpdating = true;

        try {
            var response = await fetch(ANALYTICS_POLL_URL, {
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
                refreshAnalyticsUI(data);
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
        updateInterval = setTimeout(fetchAnalytics, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchAnalytics();
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
        if (pollRunning) fetchAnalytics();
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

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-trader_app'
        ];

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