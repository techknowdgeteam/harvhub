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
// FETCH REVENUE HISTORY
// =====================================================================
$stmt = $pdo->prepare("SELECT * FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC");
$stmt->execute([$email]);
$revenueHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// DEDUPLICATE / MERGE
// =====================================================================
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

// =====================================================================
// RESOLVE DEVELOPER + PROGRAMME FOR EACH REVENUE RECORD
// =====================================================================
// STRICT RULES:
//   - If the row's status is 'active'  → the fallback to the user's
//     CURRENT programme_investors row is allowed (because the row
//     reflects the live contract).
//   - If the row's status is NOT 'active' (expired / cancelled /
//     completed / failed / unpaid / payment-made / etc.) → the past
//     record is IMMUTABLE. Only `developer_id` stored on the row itself
//     is used. If it is null / 0, display "N/A" — never substitute the
//     user's current programme.
// =====================================================================
$resolvedProgrammeInfo = [];

if (!empty($revenueHistory)) {
    $developerNameCache = [];
    $programmeNameCache = [];

    // Pre-resolve current user id once (used only for active rows).
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

        // -----------------------------------------------------------------
        // Fallback is ONLY permitted for ACTIVE rows.
        // Historical rows are frozen — their stored developer_id is law.
        // -----------------------------------------------------------------
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

        // -----------------------------------------------------------------
        // For historical rows, only look up the programme_id when we
        // actually have a stored developer_id, and only from the row's
        // own contract scope. Never touch the user's current programme.
        // -----------------------------------------------------------------
        if ($programmeId <= 0 && $developerId > 0 && !$isActiveRow) {
            try {
                // Try to match the historical contract via the revenue row's
                // stored contract_id → programme_investors row for this investor.
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

        // -----------------------------------------------------------------
        // Resolve developer name — safe for both active and historical.
        // Only reads what's in `developer_id`.
        // -----------------------------------------------------------------
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

        // -----------------------------------------------------------------
        // Resolve programme name — again, only from the resolved programme_id.
        // -----------------------------------------------------------------
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

<div class="revenue-wrapper">
    <?php if (empty($revenueHistory)): ?>
        <div class="empty-revenue">
            <span class="empty-icon">📭</span>
            <h3>No Revenue History</h3>
            <p>You haven't completed any contracts yet. Start your first contract to see your revenue history here.</p>
        </div>
    <?php else: ?>
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

            // Resolved developer / programme info for this record
            $info            = $resolvedProgrammeInfo[$index] ?? [
                'developer_id'   => 0,
                'developer_name' => 'N/A',
                'programme_id'   => 0,
                'programme_name' => 'N/A',
            ];
            $developerName   = $info['developer_name'];
            $programmeName   = $info['programme_name'];

            // Display label: "{developer_name}'s Programme • {programme_name}"
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
                    <div class="revenue-left">
                        <span class="revenue-icon">💰</span>
                        <span class="revenue-user-share">
                            <?php if ($isActive): ?>
                                Next Revenue
                            <?php else: ?>
                                $<?= number_format($userShare, 2) ?>
                            <?php endif; ?>
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
                            <div class="revenue-detail-row full-width">
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

                            <div class="revenue-detail-row full-width">
                                <span class="revenue-detail-label">Status</span>
                                <span class="revenue-detail-value">
                                    <span class="revenue-status-badge <?= $statusClass ?>" style="font-size:0.65rem; display:inline-block;">
                                        <?= $statusLabel ?>
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<a href="app.php#mydashboard" class="floating-close-btn">
    ✕ Close &amp; Go Back
</a>

<script>
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

</script>

</body>
</html>