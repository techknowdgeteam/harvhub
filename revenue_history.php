<?php
// revenue_history.php
session_start();

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

$host = "sql312.infinityfree.com";
$dbname = "if0_40473107_harvhub";
$user = "if0_40473107";
$pass = "InDQmdl53FZ85";

$tableName              = "harvhub";
$revenueHistoryTable    = "revenue_history";
$programmeInvestorsTable= "programme_investors";
$programmeTable         = "programme";

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

// Dark mode
$stmt = $pdo->prepare("SELECT dark_mode FROM $tableName WHERE email = ?");
$stmt->execute([$email]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

$darkMode = isset($userRow['dark_mode']) ? (int)$userRow['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// =====================================================================
// STATUS GROUPS
// =====================================================================
$paymentMadeStatuses = ['payment-made', 'contract-cancelled-payment-made'];
$failedStatuses      = ['payment-failed', 'failed-payment', 'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed'];
$unpaidStatuses      = ['unpaid-payment', 'unpaid', 'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment', 'contract-cancelled-payment-required'];

function resolveMergedStatus($latestStatus, $oldStatus, $paymentMadeStatuses, $failedStatuses, $unpaidStatuses) {
    if ($latestStatus === null || $oldStatus === null) return null;
    if ($latestStatus === $oldStatus) return null;

    $groups = [
        'payment-made' => ['payment-made', 'contract-cancelled-payment-made'],
        'failed'       => ['payment-failed', 'contract-cancelled-failed-payment'],
        'unpaid'       => ['unpaid-payment', 'contract-cancelled-unpaid'],
    ];

    foreach ($groups as $group => $pair) {
        $latestIsBase      = in_array($latestStatus, $paymentMadeStatuses, true) && $group === 'payment-made'
                             || in_array($latestStatus, $failedStatuses, true) && $group === 'failed'
                             || in_array($latestStatus, $unpaidStatuses, true) && $group === 'unpaid';
        $oldIsBase         = in_array($oldStatus, $paymentMadeStatuses, true) && $group === 'payment-made'
                             || in_array($oldStatus, $failedStatuses, true) && $group === 'failed'
                             || in_array($oldStatus, $unpaidStatuses, true) && $group === 'unpaid';

        $latestIsCancelled = (strpos($latestStatus, 'contract-cancelled-') === 0);
        $oldIsCancelled    = (strpos($oldStatus, 'contract-cancelled-') === 0);

        if (($latestIsBase && !$latestIsCancelled && $oldIsCancelled)
            || ($oldIsBase && !$oldIsCancelled && $latestIsCancelled)) {
            if ($group === 'payment-made') return 'contract-cancelled-payment-made';
            if ($group === 'failed')       return 'contract-cancelled-failed-payment';
            if ($group === 'unpaid')       return 'contract-cancelled-unpaid';
        }
    }

    return null;
}

// =====================================================================
// REUSABLE: FETCH + DEDUPE + RESOLVE DEVELOPER/PROGRAMME
// =====================================================================
function buildRevenueHistoryPayload($pdo, $email, $tableName, $revenueHistoryTable, $programmeInvestorsTable, $programmeTable, $paymentMadeStatuses, $failedStatuses, $unpaidStatuses) {
    // ---- Fetch ----
    $stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC");
    $stmt->execute([$email]);
    $revenueHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ---- Dedupe / merge ----
    if (!empty($revenueHistory)) {
        $latestRecord     = $revenueHistory[0];
        $latestId         = $latestRecord['id'] ?? null;
        $latestContractId = $latestRecord['contract_id'] ?? null;
        $latestStatus     = $latestRecord['loyalties'] ?? null;

        $rowsToDelete = [];
        $mergedStatus = $latestStatus;

        for ($i = 1; $i < count($revenueHistory); $i++) {
            $other           = $revenueHistory[$i];
            $otherId         = $other['id'] ?? null;
            $otherContractId = $other['contract_id'] ?? null;
            $otherStatus     = $other['loyalties'] ?? null;

            if ($latestContractId === null || $otherContractId === null) continue;
            if ($latestContractId !== $otherContractId) continue;
            if ($otherId === $latestId) continue;

            if ($otherStatus === $mergedStatus) {
                $rowsToDelete[] = $otherId;
                continue;
            }

            $candidate = resolveMergedStatus($mergedStatus, $otherStatus, $paymentMadeStatuses, $failedStatuses, $unpaidStatuses);
            if ($candidate !== null) {
                $mergedStatus = $candidate;
                $rowsToDelete[] = $otherId;
            }
        }

        if ($mergedStatus !== $latestStatus && $latestId !== null) {
            $upd = $pdo->prepare("UPDATE $revenueHistoryTable SET loyalties = ? WHERE id = ?");
            $upd->execute([$mergedStatus, $latestId]);
            $revenueHistory[0]['loyalties'] = $mergedStatus;
        }

        if (!empty($rowsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($rowsToDelete), '?'));
            $del = $pdo->prepare("DELETE FROM $revenueHistoryTable WHERE id IN ($placeholders)");
            $del->execute($rowsToDelete);

            $deleteLookup = array_flip($rowsToDelete);
            $revenueHistory = array_values(array_filter($revenueHistory, function($row) use ($deleteLookup) {
                return !isset($deleteLookup[$row['id'] ?? null]);
            }));
        }
    }

    // ---- Resolve developer + programme ----
    $resolvedProgrammeInfo = [];

    if (!empty($revenueHistory)) {
        $developerNameCache = [];
        $programmeNameCache = [];

        $currentUserId = 0;
        try {
            $uStmt = $pdo->prepare("SELECT id FROM $tableName WHERE email = ? LIMIT 1");
            $uStmt->execute([$email]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) $currentUserId = (int)$uRow['id'];
        } catch (PDOException $e) {}

        foreach ($revenueHistory as $idx => $record) {
            $status       = $record['loyalties'] ?? null;
            $isActiveRow  = ($status === 'active');

            $developerId  = isset($record['developer_id']) ? (int)$record['developer_id'] : 0;
            $programmeId  = 0;
            $contractId   = $record['contract_id'] ?? null;

            if ($developerId <= 0 && $isActiveRow && $currentUserId > 0) {
                try {
                    $piStmt = $pdo->prepare("
                        SELECT developerid, programme_id
                        FROM $programmeInvestorsTable
                        WHERE investorid = ?
                        ORDER BY invested_at DESC, id DESC
                        LIMIT 1
                    ");
                    $piStmt->execute([$currentUserId]);
                    $piRow = $piStmt->fetch(PDO::FETCH_ASSOC);
                    if ($piRow) {
                        $developerId = (int)$piRow['developerid'];
                        $programmeId = (int)$piRow['programme_id'];
                    }
                } catch (PDOException $e) {}
            }

            if ($programmeId <= 0 && $developerId > 0 && !$isActiveRow) {
                try {
                    $contractLookup = $pdo->prepare("
                        SELECT programme_id
                        FROM $programmeInvestorsTable
                        WHERE investorid = ?
                          AND developerid = ?
                          AND programme_id IS NOT NULL
                        ORDER BY invested_at DESC, id DESC
                        LIMIT 1
                    ");
                    $contractLookup->execute([$currentUserId, $developerId]);
                    $row = $contractLookup->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $programmeId = (int)$row['programme_id'];
                    }
                } catch (PDOException $e) {}
            }

            $developerName = 'N/A';
            if ($developerId > 0) {
                if (isset($developerNameCache[$developerId])) {
                    $developerName = $developerNameCache[$developerId];
                } else {
                    try {
                        $dStmt = $pdo->prepare("SELECT fullname FROM $tableName WHERE id = ? LIMIT 1");
                        $dStmt->execute([$developerId]);
                        $dRow = $dStmt->fetch(PDO::FETCH_ASSOC);
                        $developerName = $dRow && !empty($dRow['fullname']) ? $dRow['fullname'] : 'N/A';
                    } catch (PDOException $e) {
                        $developerName = 'N/A';
                    }
                    $developerNameCache[$developerId] = $developerName;
                }
            }

            $programmeName = 'N/A';
            if ($programmeId > 0) {
                if (isset($programmeNameCache[$programmeId])) {
                    $programmeName = $programmeNameCache[$programmeId];
                } else {
                    try {
                        $pStmt = $pdo->prepare("SELECT program_name FROM $programmeTable WHERE id = ? LIMIT 1");
                        $pStmt->execute([$programmeId]);
                        $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                        $programmeName = $pRow && !empty($pRow['program_name']) ? $pRow['program_name'] : 'N/A';
                    } catch (PDOException $e) {
                        $programmeName = 'N/A';
                    }
                    $programmeNameCache[$programmeId] = $programmeName;
                }
            }

            $resolvedProgrammeInfo[$idx] = [
                'developer_id'   => $developerId,
                'developer_name' => $developerName,
                'programme_id'   => $programmeId,
                'programme_name' => $programmeName,
            ];
        }
    }

    return [$revenueHistory, $resolvedProgrammeInfo];
}

// =====================================================================
// AJAX: LIVE REVENUE HISTORY (JSON)
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
        list($revenueHistory, $resolvedProgrammeInfo) = buildRevenueHistoryPayload(
            $pdo, $email, $tableName, $revenueHistoryTable,
            $programmeInvestorsTable, $programmeTable,
            $paymentMadeStatuses, $failedStatuses, $unpaidStatuses
        );

        $rows = [];

        foreach ($revenueHistory as $index => $record) {
            $userShare       = (float)($record['user_share'] ?? 0);
            $serverShare     = (float)($record['server_share'] ?? 0);
            $profit          = (float)($record['profit'] ?? 0);
            $startingBalance = (float)($record['starting_balance'] ?? 0);
            $currentBalance  = (float)($record['current_balance'] ?? 0);
            $status          = $record['loyalties'] ?? 'unknown';

            $isActive = ($status === 'active');

            $labelInvested       = $isActive ? 'Starting Balance' : 'INVESTED';
            $labelCurrentBalance = $isActive ? 'Current Balance'  : 'HARVEST VALUE';
            $labelTotalProfit    = $isActive ? 'Total Profit'     : 'Total Gain';
            $labelYourShare      = $isActive ? 'Your Share'       : 'Harvested';
            $labelServerShare    = $isActive ? 'Server Share'     : 'Trades Provider Share';

            $statusClass = 'status-default';
            $statusLabel = ucwords(str_replace('-', ' ', str_replace('_', ' ', $status)));

            switch ($status) {
                case 'active': $statusClass = 'status-active'; $statusLabel = 'Active'; break;
                case 'payment-confirmed': $statusClass = 'status-payment-confirmed'; $statusLabel = 'Confirmed'; break;
                case 'payment-made': $statusClass = 'status-payment-made'; $statusLabel = 'Pending'; break;
                case 'unpaid-payment': $statusClass = 'status-unpaid-payment'; $statusLabel = 'Payment Required'; break;
                case 'payment-failed': $statusClass = 'status-payment-failed'; $statusLabel = 'Payment Failed'; break;
                case 'failed-payment': $statusClass = 'status-failed-payment'; $statusLabel = 'Payment Failed'; break;
                case 'loss_completed': $statusClass = 'status-loss_completed'; $statusLabel = 'Loss'; break;
                case 'below_threshold': $statusClass = 'status-below_threshold'; $statusLabel = 'Below Threshold'; break;
                case 'completed': $statusClass = 'status-completed'; $statusLabel = 'Completed'; break;
                case 'contract-cancelled-payment-made': $statusClass = 'status-payment-made'; $statusLabel = 'Cancelled (Payment Made)'; break;
                case 'contract-cancelled-failed-payment': $statusClass = 'status-failed-payment'; $statusLabel = 'Cancelled (Payment Failed)'; break;
                case 'contract-cancelled-unpaid': $statusClass = 'status-unpaid-payment'; $statusLabel = 'Cancelled (Unpaid)'; break;
                default: $statusLabel = ucwords(str_replace('_', ' ', $status));
            }

            $profitClass = $profit > 0 ? 'profit-positive' : ($profit < 0 ? 'profit-negative' : 'profit-neutral');
            $profitDisplay = '$' . number_format($profit, 2);

            $startDisplay = $record['execution_start_date'] && $record['execution_start_date'] !== '0000-00-00'
                ? date('M d, Y', strtotime($record['execution_start_date']))
                : 'Not started';
            $endDisplay = $record['execution_end_date'] && $record['execution_end_date'] !== '0000-00-00'
                ? date('M d, Y', strtotime($record['execution_end_date']))
                : 'Not started';

            $contractId  = $record['contract_id'] ?? 'N/A';

            $info            = $resolvedProgrammeInfo[$index] ?? [
                'developer_id'   => 0,
                'developer_name' => 'N/A',
                'programme_id'   => 0,
                'programme_name' => 'N/A',
            ];
            $developerName   = $info['developer_name'];
            $programmeName   = $info['programme_name'];

            if ($developerName !== 'N/A' && $programmeName !== 'N/A') {
                $programmeDisplay = $developerName . "'s Programme • " . $programmeName;
            } elseif ($developerName !== 'N/A') {
                $programmeDisplay = $developerName . "'s Programme";
            } elseif ($programmeName !== 'N/A') {
                $programmeDisplay = $programmeName;
            } else {
                $programmeDisplay = 'N/A';
            }

            // Compact date-range string for the folded header
            $foldedDateRange = $startDisplay . ' – ' . $endDisplay;

            $rows[] = [
                'index'             => $index,
                'contract_id'       => $contractId,
                'start_date'        => $startDisplay,
                'end_date'          => $endDisplay,
                'date_range'        => $foldedDateRange,
                'starting_balance'  => number_format($startingBalance, 2),
                'current_balance'   => number_format($currentBalance, 2),
                'user_share'        => number_format($userShare, 2),
                'server_share'      => number_format($serverShare, 2),
                'profit'            => $profitDisplay,
                'profit_class'      => $profitClass,
                'status'            => $status,
                'status_class'      => $statusClass,
                'status_label'      => $statusLabel,
                'is_active'         => $isActive,
                'label_invested'    => $labelInvested,
                'label_current'     => $labelCurrentBalance,
                'label_profit'      => $labelTotalProfit,
                'label_user_share'  => $labelYourShare,
                'label_server_share'=> $labelServerShare,
                'programme_display' => $programmeDisplay,
            ];
        }

        echo json_encode([
            'success'   => true,
            'has_rows'  => !empty($rows),
            'count'     => count($rows),
            'rows'      => $rows,
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to build revenue history payload.'
        ]);
        exit;
    }
}

// =====================================================================
// INITIAL PAGE RENDER (non-AJAX)
// =====================================================================
list($revenueHistory, $resolvedProgrammeInfo) = buildRevenueHistoryPayload(
    $pdo, $email, $tableName, $revenueHistoryTable,
    $programmeInvestorsTable, $programmeTable,
    $paymentMadeStatuses, $failedStatuses, $unpaidStatuses
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Revenue History - Harvhub</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include 'style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="revenue-wrapper" id="revenueWrapper">
    <?php if (empty($revenueHistory)): ?>
        <div class="empty-revenue" id="emptyRevenueState">
            <span class="empty-icon">📭</span>
            <h3>No Revenue History</h3>
            <p>You haven't completed any contracts yet. Start your first contract to see your revenue history here.</p>
        </div>
    <?php else: ?>
        <div id="revenueListContainer">
        <?php foreach ($revenueHistory as $index => $record):
            $userShare       = (float)($record['user_share'] ?? 0);
            $serverShare     = (float)($record['server_share'] ?? 0);
            $profit          = (float)($record['profit'] ?? 0);
            $startingBalance = (float)($record['starting_balance'] ?? 0);
            $currentBalance  = (float)($record['current_balance'] ?? 0);
            $status          = $record['loyalties'] ?? 'unknown';

            $isActive = ($status === 'active');

            $labelInvested       = $isActive ? 'Starting Balance' : 'INVESTED';
            $labelCurrentBalance = $isActive ? 'Current Balance'  : 'HARVEST VALUE';
            $labelTotalProfit    = $isActive ? 'Total Profit'     : 'Total Gain';
            $labelYourShare      = $isActive ? 'Your Share'       : 'Harvested';
            $labelServerShare    = $isActive ? 'Server Share'     : 'Trades Provider Share';

            $statusClass = 'status-default';
            $statusLabel = ucwords(str_replace('-', ' ', str_replace('_', ' ', $status)));

            switch ($status) {
                case 'active': $statusClass = 'status-active'; $statusLabel = 'Active'; break;
                case 'payment-confirmed': $statusClass = 'status-payment-confirmed'; $statusLabel = 'Confirmed'; break;
                case 'payment-made': $statusClass = 'status-payment-made'; $statusLabel = 'Pending'; break;
                case 'unpaid-payment': $statusClass = 'status-unpaid-payment'; $statusLabel = 'Payment Required'; break;
                case 'payment-failed': $statusClass = 'status-payment-failed'; $statusLabel = 'Payment Failed'; break;
                case 'failed-payment': $statusClass = 'status-failed-payment'; $statusLabel = 'Payment Failed'; break;
                case 'loss_completed': $statusClass = 'status-loss_completed'; $statusLabel = 'Loss'; break;
                case 'below_threshold': $statusClass = 'status-below_threshold'; $statusLabel = 'Below Threshold'; break;
                case 'completed': $statusClass = 'status-completed'; $statusLabel = 'Completed'; break;
                case 'contract-cancelled-payment-made': $statusClass = 'status-payment-made'; $statusLabel = 'Cancelled (Payment Made)'; break;
                case 'contract-cancelled-failed-payment': $statusClass = 'status-failed-payment'; $statusLabel = 'Cancelled (Payment Failed)'; break;
                case 'contract-cancelled-unpaid': $statusClass = 'status-unpaid-payment'; $statusLabel = 'Cancelled (Unpaid)'; break;
                default: $statusLabel = ucwords(str_replace('_', ' ', $status));
            }

            $profitClass = $profit > 0 ? 'profit-positive' : ($profit < 0 ? 'profit-negative' : 'profit-neutral');
            $profitDisplay = '$' . number_format($profit, 2);

            $startDisplay = $record['execution_start_date'] && $record['execution_start_date'] !== '0000-00-00'
                ? date('M d, Y', strtotime($record['execution_start_date']))
                : 'Not started';
            $endDisplay = $record['execution_end_date'] && $record['execution_end_date'] !== '0000-00-00'
                ? date('M d, Y', strtotime($record['execution_end_date']))
                : 'Not started';

            $contractId  = $record['contract_id'] ?? 'N/A';

            $info            = $resolvedProgrammeInfo[$index] ?? [
                'developer_id'   => 0,
                'developer_name' => 'N/A',
                'programme_id'   => 0,
                'programme_name' => 'N/A',
            ];
            $developerName   = $info['developer_name'];
            $programmeName   = $info['programme_name'];

            if ($developerName !== 'N/A' && $programmeName !== 'N/A') {
                $programmeDisplay = $developerName . "'s Programme • " . $programmeName;
            } elseif ($developerName !== 'N/A') {
                $programmeDisplay = $developerName . "'s Programme";
            } elseif ($programmeName !== 'N/A') {
                $programmeDisplay = $programmeName;
            } else {
                $programmeDisplay = 'N/A';
            }
        ?>
            <div class="revenue-item" data-index="<?= $index ?>" onclick="toggleRevenue(this)">
                <div class="revenue-header-folded">
                    <div class="revenue-icon-wrap">
                        <span class="revenue-icon">💰</span>
                    </div>
                    <div class="revenue-folded-text">
                        <span class="revenue-user-share">
                            <?php if ($isActive): ?>
                                Next Revenue
                            <?php else: ?>
                                $<?= number_format($userShare, 2) ?>
                            <?php endif; ?>
                        </span>
                        <span class="revenue-date-range">
                            <?= htmlspecialchars($startDisplay) ?> – <?= htmlspecialchars($endDisplay) ?>
                        </span>
                    </div>
                    <div class="revenue-right">
                        <span class="revenue-status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                        <span class="revenue-toggle">▼</span>
                    </div>
                </div>

                <div class="revenue-details">
                    <div class="revenue-details-inner">
                        <div class="revenue-detail-row full-width">
                            <span class="revenue-detail-label">Contract ID</span>
                            <span class="revenue-detail-value mono"><?= htmlspecialchars($contractId) ?></span>
                        </div>

                        <div class="revenue-detail-row">
                            <span class="revenue-detail-label">Start Date</span>
                            <span class="revenue-detail-value"><?= htmlspecialchars($startDisplay) ?></span>
                        </div>
                        <div class="revenue-detail-row">
                            <span class="revenue-detail-label">End Date</span>
                            <span class="revenue-detail-value"><?= htmlspecialchars($endDisplay) ?></span>
                        </div>

                        <div class="revenue-detail-row">
                            <span class="revenue-detail-label"><?= $labelInvested ?></span>
                            <span class="revenue-detail-value">$<?= number_format($startingBalance, 2) ?></span>
                        </div>

                        <?php if (!$isActive): ?>
                            <div class="revenue-detail-row">
                                <span class="revenue-detail-label"><?= $labelCurrentBalance ?></span>
                                <span class="revenue-detail-value">$<?= number_format($currentBalance, 2) ?></span>
                            </div>
                            <div class="revenue-detail-row">
                                <span class="revenue-detail-label"><?= $labelTotalProfit ?></span>
                                <span class="revenue-detail-value <?= $profitClass ?>"><?= $profitDisplay ?></span>
                            </div>
                            <div class="revenue-detail-row">
                                <span class="revenue-detail-label"><?= $labelYourShare ?></span>
                                <span class="revenue-detail-value" style="color: var(--info, #17a2b8);">$<?= number_format($userShare, 2) ?></span>
                            </div>
                            <div class="revenue-detail-row">
                                <span class="revenue-detail-label"><?= $labelServerShare ?></span>
                                <span class="revenue-detail-value" style="color: #9b59b6;">$<?= number_format($serverShare, 2) ?></span>
                            </div>

                            <div class="revenue-detail-row full-width">
                                <span class="revenue-detail-label">Programme</span>
                                <span class="revenue-detail-value"><?= htmlspecialchars($programmeDisplay) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<a href="app.php#mydashboard" class="floating-close-btn">
    ✕ Close &amp; Go Back
</a>

<script>
    // =====================================================================
    // CLICK-TO-EXPAND
    // =====================================================================
    function toggleRevenue(element) {
        if (event && event.target.closest('a, button')) return;

        var allItems = document.querySelectorAll('.revenue-item');
        allItems.forEach(function(item) {
            if (item !== element && item.classList.contains('expanded')) {
                item.classList.remove('expanded');
            }
        });

        element.classList.toggle('expanded');
    }

    document.addEventListener('click', function(event) {
        var revenueWrapper = document.querySelector('.revenue-wrapper');
        if (revenueWrapper && !revenueWrapper.contains(event.target)) {
            document.querySelectorAll('.revenue-item.expanded').forEach(function(item) {
                item.classList.remove('expanded');
            });
        }
    });

    // =====================================================================
    // LIVE POLL — same approach as mydashboard.php
    // =====================================================================
    var REVENUE_POLL_URL = (function() {
        try {
            var base = document.baseURI || window.location.href;
            return new URL('revenue_history.php', base).toString();
        } catch (e) {
            return 'revenue_history.php';
        }
    })();

    var isUpdating        = false;
    var updateInterval    = null;
    var retryCount        = 0;
    var MAX_RETRIES       = 5;
    var currentInterval   = 1000;
    var pollRunning       = true;

    var expandedIndexes = {};

    function captureExpandedState() {
        expandedIndexes = {};
        document.querySelectorAll('.revenue-item.expanded').forEach(function(item) {
            var idx = item.getAttribute('data-index');
            if (idx !== null) expandedIndexes[idx] = true;
        });
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function buildRevenueItemHtml(row) {
        var isActive = !!row.is_active;
        var headerShareHtml = isActive
            ? 'Next Revenue'
            : '$' + escapeHtml(row.user_share);

        var html = '';
        html += '<div class="revenue-item" data-index="' + row.index + '" onclick="toggleRevenue(this)">';
        html += '  <div class="revenue-header-folded">';
        html += '    <div class="revenue-icon-wrap">';
        html += '      <span class="revenue-icon">💰</span>';
        html += '    </div>';
        html += '    <div class="revenue-folded-text">';
        html += '      <span class="revenue-user-share">' + headerShareHtml + '</span>';
        html += '      <span class="revenue-date-range">' + escapeHtml(row.date_range) + '</span>';
        html += '    </div>';
        html += '    <div class="revenue-right">';
        html += '      <span class="revenue-status-badge ' + escapeHtml(row.status_class) + '">' + escapeHtml(row.status_label) + '</span>';
        html += '      <span class="revenue-toggle">▼</span>';
        html += '    </div>';
        html += '  </div>';
        html += '  <div class="revenue-details">';
        html += '    <div class="revenue-details-inner">';

        html += '      <div class="revenue-detail-row full-width">';
        html += '        <span class="revenue-detail-label">Contract ID</span>';
        html += '        <span class="revenue-detail-value mono">' + escapeHtml(row.contract_id) + '</span>';
        html += '      </div>';

        html += '      <div class="revenue-detail-row">';
        html += '        <span class="revenue-detail-label">Start Date</span>';
        html += '        <span class="revenue-detail-value">' + escapeHtml(row.start_date) + '</span>';
        html += '      </div>';

        html += '      <div class="revenue-detail-row">';
        html += '        <span class="revenue-detail-label">End Date</span>';
        html += '        <span class="revenue-detail-value">' + escapeHtml(row.end_date) + '</span>';
        html += '      </div>';

        html += '      <div class="revenue-detail-row">';
        html += '        <span class="revenue-detail-label">' + escapeHtml(row.label_invested) + '</span>';
        html += '        <span class="revenue-detail-value">$' + escapeHtml(row.starting_balance) + '</span>';
        html += '      </div>';

        if (!isActive) {
            html += '      <div class="revenue-detail-row">';
            html += '        <span class="revenue-detail-label">' + escapeHtml(row.label_current) + '</span>';
            html += '        <span class="revenue-detail-value">$' + escapeHtml(row.current_balance) + '</span>';
            html += '      </div>';

            html += '      <div class="revenue-detail-row">';
            html += '        <span class="revenue-detail-label">' + escapeHtml(row.label_profit) + '</span>';
            html += '        <span class="revenue-detail-value ' + escapeHtml(row.profit_class) + '">' + escapeHtml(row.profit) + '</span>';
            html += '      </div>';

            html += '      <div class="revenue-detail-row">';
            html += '        <span class="revenue-detail-label">' + escapeHtml(row.label_user_share) + '</span>';
            html += '        <span class="revenue-detail-value" style="color: var(--info, #17a2b8);">$' + escapeHtml(row.user_share) + '</span>';
            html += '      </div>';

            html += '      <div class="revenue-detail-row">';
            html += '        <span class="revenue-detail-label">' + escapeHtml(row.label_server_share) + '</span>';
            html += '        <span class="revenue-detail-value" style="color: #9b59b6;">$' + escapeHtml(row.server_share) + '</span>';
            html += '      </div>';

            html += '      <div class="revenue-detail-row full-width">';
            html += '        <span class="revenue-detail-label">Programme</span>';
            html += '        <span class="revenue-detail-value">' + escapeHtml(row.programme_display) + '</span>';
            html += '      </div>';
        }

        html += '    </div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    function refreshRevenueHistory(data) {
        if (!data || !data.success) return;

        var wrapper = document.getElementById('revenueWrapper');
        if (!wrapper) return;

        captureExpandedState();

        if (!data.has_rows || !data.rows || data.rows.length === 0) {
            wrapper.innerHTML =
                '<div class="empty-revenue" id="emptyRevenueState">' +
                '  <span class="empty-icon">📭</span>' +
                '  <h3>No Revenue History</h3>' +
                '  <p>You haven\'t completed any contracts yet. Start your first contract to see your revenue history here.</p>' +
                '</div>';
            return;
        }

        var html = '<div id="revenueListContainer">';
        for (var i = 0; i < data.rows.length; i++) {
            html += buildRevenueItemHtml(data.rows[i]);
        }
        html += '</div>';

        wrapper.innerHTML = html;

        Object.keys(expandedIndexes).forEach(function(idx) {
            var el = wrapper.querySelector('.revenue-item[data-index="' + idx + '"]');
            if (el) el.classList.add('expanded');
        });
    }

    async function fetchRevenueHistory() {
        if (isUpdating) return;
        isUpdating = true;

        try {
            var response = await fetch(REVENUE_POLL_URL, {
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
                refreshRevenueHistory(data);
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
        updateInterval = setTimeout(fetchRevenueHistory, currentInterval);
    }

    function startLiveUpdates() {
        pollRunning = true;
        if (updateInterval) clearTimeout(updateInterval);
        fetchRevenueHistory();
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
        if (pollRunning) fetchRevenueHistory();
    });

    startLiveUpdates();
    window.addEventListener('beforeunload', function() { stopLiveUpdates(); });
</script>

</body>
</html>