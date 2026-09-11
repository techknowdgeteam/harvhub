<?php
// programmes.php — Programme view (single, tab-less)
session_start();

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed.");
}

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$userId        = (int)$user['id'];
$fullName      = $user['fullname'] ?? 'User';
$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// =====================================================================
// AJAX ROUTER
// =====================================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');

    // -------- GET PROGRAMME DETAILS --------
    if (isset($_POST['get_programme_details'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.userid, p.program_name, p.visibility, p.advertisement,
                       h.fullname AS developer_name,
                       h.email    AS developer_email
                FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE p.id = ? AND p.visibility = 1
                LIMIT 1
            ");
            $stmt->execute([$pid]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not public.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$pid, (int)$programme['userid']]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $stmt = $pdo->prepare("
                    SELECT contract_duration, developer_percentage, investor_percentage,
                           minimum_investment_amount, maximum_investment_amount
                    FROM programme_investors
                    WHERE programme_id = ? AND investorid = 0
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmt->execute([$pid]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
            $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
            $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
            $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

            $contractDuration = $req && (int)$req['contract_duration'] > 0 ? (int)$req['contract_duration'] : $srvContractDuration;
            $minInvestment    = $req && (float)$req['minimum_investment_amount'] > 0 ? (float)$req['minimum_investment_amount'] : $srvMinBrokerBalance;
            $maxInvestment    = $req ? (float)$req['maximum_investment_amount'] : 0;
            $developerPercent = $req && (int)$req['developer_percentage'] > 0 ? (int)$req['developer_percentage'] : $srvServerShare;
            $investorPercent  = $req && (int)$req['investor_percentage'] > 0 ? (int)$req['investor_percentage'] : $srvUserShare;

            $analytics = [
                'winrate'                    => null,
                'highest_drawdown'           => null,
                'consecutive_sequential_loss'=> null,
                'highest_trades_per_day'     => null,
            ];
            try {
                $aStmt = $pdo->prepare("
                    SELECT winrate, highest_drawdown, consecutive_sequential_loss, highest_trades_per_day
                    FROM programme_analytics
                    WHERE programme_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $aStmt->execute([$pid]);
                $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
                if ($aRow) {
                    $analytics['winrate']                     = $aRow['winrate'];
                    $analytics['highest_drawdown']            = $aRow['highest_drawdown'];
                    $analytics['consecutive_sequential_loss'] = $aRow['consecutive_sequential_loss'];
                    $analytics['highest_trades_per_day']      = $aRow['highest_trades_per_day'];
                }
            } catch (PDOException $e) {}

            $alreadyInvested = false;
            try {
                $chk = $pdo->prepare("SELECT id FROM programme_investors WHERE investorid = ? AND programme_id = ? LIMIT 1");
                $chk->execute([$userId, $pid]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) $alreadyInvested = true;
            } catch (PDOException $e) {}

            $hasAnyInvestment = false;
            try {
                $chk2 = $pdo->prepare("SELECT id FROM programme_investors WHERE investorid = ? LIMIT 1");
                $chk2->execute([$userId]);
                if ($chk2->fetch(PDO::FETCH_ASSOC)) $hasAnyInvestment = true;
            } catch (PDOException $e) {}

            echo json_encode([
                'success'   => true,
                'programme' => [
                    'id'              => (int)$programme['id'],
                    'program_name'    => $programme['program_name'],
                    'developer_name'  => $programme['developer_name'] ?: 'Anonymous',
                    'developer_email' => $programme['developer_email'] ?: '',
                    'visibility'      => (int)$programme['visibility'],
                    'advertisement'   => (int)$programme['advertisement'],
                    'contract_duration'         => $contractDuration,
                    'developer_percentage'      => $developerPercent,
                    'investor_percentage'       => $investorPercent,
                    'minimum_investment_amount' => $minInvestment,
                    'maximum_investment_amount' => $maxInvestment,
                    'analytics'                 => $analytics,
                ],
                'already_invested'   => $alreadyInvested,
                'has_any_investment' => $hasAnyInvestment,
                'viewer_id'          => $userId
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load programme details.']);
        }
        exit;
    }

    // -------- INVEST IN PROGRAMME --------
    if (isset($_POST['invest_in_programme'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT p.id, p.userid FROM programme p WHERE p.id = ? AND p.visibility = 1 LIMIT 1");
            $stmt->execute([$pid]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not public.']);
                exit;
            }

            $developerId = (int)$programme['userid'];

            $chk = $pdo->prepare("SELECT id FROM programme_investors WHERE investorid = ? AND programme_id = ? LIMIT 1");
            $chk->execute([$userId, $pid]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'You have already invested in this programme.']);
                exit;
            }

            $chk2 = $pdo->prepare("SELECT id FROM programme_investors WHERE investorid = ? LIMIT 1");
            $chk2->execute([$userId]);
            if ($chk2->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'You are already participating in a programme. Finish or reset it before joining another.']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$pid, $developerId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $stmt = $pdo->prepare("
                    SELECT contract_duration, developer_percentage, investor_percentage,
                           minimum_investment_amount, maximum_investment_amount
                    FROM programme_investors
                    WHERE programme_id = ? AND investorid = 0
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmt->execute([$pid]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
            $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
            $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
            $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

            $contractDuration = $req && (int)$req['contract_duration'] > 0 ? (int)$req['contract_duration'] : $srvContractDuration;
            $minInvestment    = $req && (float)$req['minimum_investment_amount'] > 0 ? (float)$req['minimum_investment_amount'] : $srvMinBrokerBalance;
            $maxInvestment    = $req ? (float)$req['maximum_investment_amount'] : 0;
            $developerPercent = $req && (int)$req['developer_percentage'] > 0 ? (int)$req['developer_percentage'] : $srvServerShare;
            $investorPercent  = $req && (int)$req['investor_percentage'] > 0 ? (int)$req['investor_percentage'] : $srvUserShare;

            $ins = $pdo->prepare("
                INSERT INTO programme_investors
                    (investorid, developerid, programme_id, invested_at,
                     contract_duration, developer_percentage, investor_percentage,
                     minimum_investment_amount, maximum_investment_amount)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $userId,
                $developerId,
                $pid,
                $contractDuration,
                $developerPercent,
                $investorPercent,
                $minInvestment,
                $maxInvestment
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'You have successfully invested in this programme.',
                'programme_id' => $pid,
                'developer_id' => $developerId
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to invest in programme.']);
        }
        exit;
    }

    // -------- CANCEL PROGRAMME --------
    if (isset($_POST['cancel_programme'])) {
        try {
            $hasActiveContract     = false;
            $hasPaymentRequirement = false;

            $contract_duration_cfg = 30;
            try {
                $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
                $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
                if ($cfg && !empty($cfg['contract_duration'])) {
                    $contract_duration_cfg = (int)$cfg['contract_duration'];
                }
            } catch (Exception $e) {}

            $exec_start = $user['execution_start_date'] ?? null;
            if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
                try {
                    $start = new DateTime($exec_start);
                    $end   = clone $start;
                    $end->modify("+{$contract_duration_cfg} days");
                    $today = new DateTime();
                    $today->setTime(0, 0, 0);
                    $endClone = clone $end;
                    $endClone->setTime(0, 0, 0);
                    $daysLeft = (int)$today->diff($endClone)->format('%r%a');
                    if ($daysLeft > 0) $hasActiveContract = true;
                } catch (Exception $e) {}
            }

            $payment_issue_statuses = [
                'unpaid-payment', 'unpaid',
                'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
                'contract-cancelled-payment-required',
                'payment-failed', 'failed-payment',
                'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
                'payment-made', 'contract-cancelled-payment-made',
                'pending_payment'
            ];

            $loyalties_status = $user['loyalties'] ?? null;
            if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
                $hasPaymentRequirement = true;
            }

            try {
                $revStmt = $pdo->prepare("SELECT loyalties FROM revenue_history WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
                $revStmt->execute([$email]);
                $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
                if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
                    $hasPaymentRequirement = true;
                }
            } catch (Exception $e) {}

            if ($hasActiveContract) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a contract is active."]);
                exit;
            }
            if ($hasPaymentRequirement) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a payment is required."]);
                exit;
            }

            $del = $pdo->prepare("DELETE FROM programme_investors WHERE investorid = ?");
            $del->execute([$userId]);

            echo json_encode([
                'success' => true,
                'message' => 'You have successfully cancelled your programme.'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel programme.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// =====================================================================
// NON-AJAX page render
// =====================================================================

// ==================== DETERMINE USER'S ACTIVE INVESTMENT ====================
$activeInvestment = null;
$activeProgrammeId = 0;
$userHasProgramme = false;

try {
    $stmt = $pdo->prepare("
        SELECT id, programme_id, developerid
        FROM programme_investors
        WHERE investorid = ?
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $activeInvestment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeInvestment) {
        $userHasProgramme = true;
        $activeProgrammeId = (int)$activeInvestment['programme_id'];
    }
} catch (PDOException $e) {}

// ==================== BRANCH: PROGRAMMES LIST (user has NOT invested) ====================
$programmes = [];
if (!$userHasProgramme) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.userid, p.program_name, p.visibility, p.advertisement,
                   h.fullname AS developer_name
            FROM programme p
            INNER JOIN harvhub h ON h.id = p.userid
            WHERE p.visibility = 1
            ORDER BY p.id DESC
        ");
        $stmt->execute();
        $programmes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $programmes = [];
    }
}

// ==================== BRANCH: INVESTED PROGRAMME (user HAS invested) ====================
$investedProgramme = null;
$investedProgrammeData = null;
$investedAnalytics = [
    'winrate' => null, 'highest_drawdown' => null,
    'consecutive_sequential_loss' => null, 'highest_trades_per_day' => null,
];
$investedReq = null;
$cancelEligible = false;
$cancelReason   = '';

$hasActiveContract       = false;
$hasPaymentRequirement   = false;

if ($userHasProgramme) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.userid, p.program_name, p.visibility, p.advertisement,
                   h.fullname AS developer_name,
                   h.email    AS developer_email
            FROM programme p
            INNER JOIN harvhub h ON h.id = p.userid
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$activeProgrammeId]);
        $investedProgramme = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if ($investedProgramme) {
        try {
            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$activeProgrammeId, (int)$investedProgramme['userid']]);
            $investedReq = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        try {
            $stmt = $pdo->prepare("
                SELECT winrate, highest_drawdown, consecutive_sequential_loss, highest_trades_per_day
                FROM programme_analytics
                WHERE programme_id = ?
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$activeProgrammeId]);
            $aRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aRow) {
                $investedAnalytics['winrate']                     = $aRow['winrate'];
                $investedAnalytics['highest_drawdown']            = $aRow['highest_drawdown'];
                $investedAnalytics['consecutive_sequential_loss'] = $aRow['consecutive_sequential_loss'];
                $investedAnalytics['highest_trades_per_day']      = $aRow['highest_trades_per_day'];
            }
        } catch (PDOException $e) {}

        $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
        $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
        $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
        $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

        $investedProgrammeData = [
            'id'                        => (int)$investedProgramme['id'],
            'program_name'              => $investedProgramme['program_name'],
            'developer_name'            => $investedProgramme['developer_name'] ?: 'Anonymous',
            'developer_email'           => $investedProgramme['developer_email'] ?: '',
            'visibility'                => (int)$investedProgramme['visibility'],
            'advertisement'             => (int)$investedProgramme['advertisement'],
            'contract_duration'         => $investedReq && (int)$investedReq['contract_duration'] > 0 ? (int)$investedReq['contract_duration'] : $srvContractDuration,
            'developer_percentage'      => $investedReq && (int)$investedReq['developer_percentage'] > 0 ? (int)$investedReq['developer_percentage'] : $srvServerShare,
            'investor_percentage'       => $investedReq && (int)$investedReq['investor_percentage'] > 0 ? (int)$investedReq['investor_percentage'] : $srvUserShare,
            'minimum_investment_amount' => $investedReq && (float)$investedReq['minimum_investment_amount'] > 0 ? (float)$investedReq['minimum_investment_amount'] : $srvMinBrokerBalance,
            'maximum_investment_amount' => $investedReq ? (float)$investedReq['maximum_investment_amount'] : 0,
            'analytics'                 => $investedAnalytics,
        ];
    }

    // Cancel eligibility
    $contract_duration_cfg = 30;
    try {
        $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
        $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
        if ($cfg && !empty($cfg['contract_duration'])) {
            $contract_duration_cfg = (int)$cfg['contract_duration'];
        }
    } catch (Exception $e) {}

    $exec_start = $user['execution_start_date'] ?? null;
    if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
        try {
            $start = new DateTime($exec_start);
            $end   = clone $start;
            $end->modify("+{$contract_duration_cfg} days");
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $endClone = clone $end;
            $endClone->setTime(0, 0, 0);
            $daysLeft = (int)$today->diff($endClone)->format('%r%a');
            if ($daysLeft > 0) $hasActiveContract = true;
        } catch (Exception $e) {}
    }

    $payment_issue_statuses = [
        'unpaid-payment', 'unpaid',
        'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
        'contract-cancelled-payment-required',
        'payment-failed', 'failed-payment',
        'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
        'payment-made', 'contract-cancelled-payment-made',
        'pending_payment'
    ];

    $loyalties_status = $user['loyalties'] ?? null;
    if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
        $hasPaymentRequirement = true;
    }

    try {
        $revStmt = $pdo->prepare("SELECT loyalties FROM revenue_history WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
        $revStmt->execute([$email]);
        $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
        if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            $hasPaymentRequirement = true;
        }
    } catch (Exception $e) {}

    // Cancel button only shows if neither condition is true
    if ($hasActiveContract || $hasPaymentRequirement) {
        $cancelEligible = false;
    } else {
        $cancelEligible = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programmes - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<style>
    body {
        padding-top: var(--header-height, 60px);
        padding-bottom: var(--nav-height, 100px);
        background: var(--bg);
        color: var(--text);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
        margin: 0;
        transition: background var(--transition-speed, 0.3s), color var(--transition-speed, 0.3s);
    }
    @media (max-width: 480px) {
        body {
            padding-top: var(--header-height-mobile, 52px);
            padding-bottom: var(--nav-height-mobile, 80px);
        }
    }
</style>
<?php include 'programmes_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'harvhub_header.php'; ?>

    <div class="prog-page-wrapper">

        <div class="prog-back-link">
            <a href="app.php#mydashboard">← Back to Dashboard</a>
        </div>

        <div class="prog-page-header">
            <h1><?= $userHasProgramme ? 'Invested Programme' : 'Explore Programmes' ?></h1>
            <p><?= $userHasProgramme
                ? 'Your current Trades Provider'
                : 'Discover and invest in developer-run trading programmes' ?></p>
        </div>

        <?php if ($userHasProgramme): ?>

            <?php if (!$investedProgrammeData): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>Unable to load your programme.</p>
                </div>
            <?php else: ?>
                <?php $ip = $investedProgrammeData; ?>
                <div class="prog-invested-card">

                    <div class="prog-detail-header">
                        <div class="prog-detail-name"><?= htmlspecialchars($ip['program_name']) ?></div>
                        <div class="prog-detail-dev">
                            Developed by <strong><?= htmlspecialchars($ip['developer_name']) ?></strong>
                        </div>
                    </div>

                    <div class="prog-detail-section-title">Analytics</div>
                    <div class="prog-detail-grid prog-detail-grid-2col">
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Winrate</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['winrate'] !== null && $ip['analytics']['winrate'] !== '' ? htmlspecialchars(rtrim(rtrim(number_format((float)$ip['analytics']['winrate'], 2, '.', ''), '0'), '.')) . '%' : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Highest Drawdown</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['highest_drawdown'] !== null && $ip['analytics']['highest_drawdown'] !== '' ? htmlspecialchars(number_format((float)$ip['analytics']['highest_drawdown'], 2, '.', '')) : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Consecutive Sequential Loss</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['consecutive_sequential_loss'] !== null && $ip['analytics']['consecutive_sequential_loss'] !== '' ? htmlspecialchars((string)$ip['analytics']['consecutive_sequential_loss']) : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Highest Trades Per Day</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['highest_trades_per_day'] !== null && $ip['analytics']['highest_trades_per_day'] !== '' ? htmlspecialchars((string)$ip['analytics']['highest_trades_per_day']) : '—' ?></span>
                        </div>
                    </div>

                    <div class="prog-detail-section-title">Requirements</div>
                    <div class="prog-detail-grid">
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Contract Duration</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['contract_duration']) ?> days</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Programme Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['developer_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Investor Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['investor_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Minimum Investment</span>
                            <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['minimum_investment_amount'], 2, '.', '')) ?></span>
                        </div>
                        <?php if ((float)$ip['maximum_investment_amount'] > 0): ?>
                            <div class="prog-detail-row">
                                <span class="prog-detail-label">Maximum Investment</span>
                                <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['maximum_investment_amount'], 2, '.', '')) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($cancelEligible): ?>
                        <button type="button"
                                class="prog-btn-cancel"
                                onclick="cancelProgrammeConfirm()">
                            Cancel Programme
                        </button>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="prog-search-wrap">
                <input type="text"
                       id="progSearchInput"
                       class="prog-search-input"
                       placeholder="Search by programme name or developer name…"
                       autocomplete="off"
                       oninput="onProgSearch(this.value)"
                       onfocus="onProgSearch(this.value)">
            </div>

            <?php if (empty($programmes)): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>No public programmes available at the moment</p>
                </div>
            <?php else: ?>
                <div class="prog-list" id="progList">
                    <?php foreach ($programmes as $p): ?>
                        <div class="prog-item"
                             data-programme-id="<?= (int)$p['id'] ?>"
                             data-programme-name="<?= htmlspecialchars(strtolower($p['program_name'])) ?>"
                             data-developer-name="<?= htmlspecialchars(strtolower($p['developer_name'] ?: 'anonymous')) ?>">
                            <div class="prog-item-main">
                                <div class="prog-item-name"><?= htmlspecialchars($p['program_name']) ?></div>
                                <div class="prog-item-meta">
                                    <span class="prog-item-stat">
                                        <span class="prog-item-stat-label">Developed by</span>
                                        <span class="prog-item-stat-value"><?= htmlspecialchars($p['developer_name'] ?: 'Anonymous') ?></span>
                                    </span>
                                </div>
                            </div>
                            <div class="prog-item-actions">
                                <button type="button"
                                        class="prog-btn-view"
                                        onclick="viewProgramme(<?= (int)$p['id'] ?>)">
                                    View
                                </button>
                                <button type="button"
                                        class="prog-btn-invest"
                                        onclick="investInProgramme(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['program_name'])) ?>')">
                                    Invest
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="progSearchEmpty" class="prog-empty-state" style="display:none;">
                    <span class="prog-empty-icon">—</span>
                    <p>No programmes match your search</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <!-- ==================== MODAL: PROGRAMME DETAILS ==================== -->
    <div id="progDetailModal" class="prog-modal">
        <div class="prog-modal-content prog-modal-wide">
            <div class="prog-modal-header">
                <h2 class="prog-modal-title" id="progDetailTitle">Programme Details</h2>
            </div>
            <div class="prog-modal-body" id="progDetailBody">
                <div class="prog-modal-loading">Loading...</div>
            </div>
            <div class="prog-modal-actions" id="progDetailActions">
                <button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: ALERT ==================== -->
    <div id="progAlertModal" class="prog-modal">
        <div class="prog-modal-content">
            <div class="prog-modal-header">
                <h2 class="prog-modal-title" id="progAlertTitle">Notice</h2>
            </div>
            <div class="prog-modal-body">
                <p id="progAlertMessage" class="prog-alert-text">Message</p>
            </div>
            <div class="prog-modal-actions">
                <button type="button" class="prog-btn-confirm" onclick="closeProgAlert()">OK</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CONFIRM INVEST ==================== -->
    <div id="progConfirmModal" class="prog-modal">
        <div class="prog-modal-content">
            <div class="prog-modal-header">
                <h2 class="prog-modal-title" id="progConfirmTitle">Confirm Investment</h2>
            </div>
            <div class="prog-modal-body">
                <p id="progConfirmMessage" class="prog-alert-text">Are you sure?</p>
            </div>
            <div class="prog-modal-actions">
                <button type="button" class="prog-btn-confirm" id="progConfirmBtn" onclick="progConfirmAction()">Yes, Invest</button>
                <button type="button" class="prog-modal-close" onclick="closeProgConfirm()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CONFIRM CANCEL ==================== -->
    <div id="progCancelModal" class="prog-modal">
        <div class="prog-modal-content">
            <div class="prog-modal-header">
                <h2 class="prog-modal-title" id="progCancelTitle">Cancel Programme</h2>
            </div>
            <div class="prog-modal-body">
                <p id="progCancelMessage" class="prog-alert-text">Are you sure you want to cancel your participation in this programme?</p>
            </div>
            <div class="prog-modal-actions">
                <button type="button" class="prog-btn-confirm" id="progCancelBtn" onclick="progCancelAction()">Yes, Cancel</button>
                <button type="button" class="prog-modal-close" onclick="closeProgCancel()">Keep Programme</button>
            </div>
        </div>
    </div>

<script>
    var _viewerId = <?= (int)$userId ?>;
    var _userHasProgramme = <?= $userHasProgramme ? 'true' : 'false' ?>;
    var _activeProgrammeId = <?= (int)$activeProgrammeId ?>;

    // ==================== HELPERS ====================
    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    function lockBodyScroll() {
        if (document.body.dataset.progModalLocked === '1') return;
        var y = window.scrollY || 0;
        document.body.dataset.progModalLocked = '1';
        document.body.dataset.progModalY = y;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + y + 'px';
    }
    function unlockBodyScroll() {
        if (document.body.dataset.progModalLocked !== '1') return;
        var y = parseInt(document.body.dataset.progModalY || '0', 10) || 0;
        document.body.dataset.progModalLocked = '0';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, y);
    }

    // ==================== SEARCH ====================
    function onProgSearch(query) {
        var q = (query || '').trim().toLowerCase();
        var listEl = document.getElementById('progList');
        if (!listEl) return;

        var items = listEl.querySelectorAll('.prog-item');
        var anyVisible = false;

        items.forEach(function (el) {
            var name = el.getAttribute('data-programme-name') || '';
            var dev  = el.getAttribute('data-developer-name') || '';
            var matches = (q === '') || (name.indexOf(q) !== -1) || (dev.indexOf(q) !== -1);
            if (matches) { el.style.display = ''; anyVisible = true; }
            else { el.style.display = 'none'; }
        });

        var emptyEl = document.getElementById('progSearchEmpty');
        if (emptyEl) emptyEl.style.display = (anyVisible || q === '') ? 'none' : '';
        listEl.style.display = anyVisible ? '' : 'none';
    }

    // ==================== ALERT ====================
    function showProgAlert(msg, title) {
        document.getElementById('progAlertTitle').textContent = title || 'Notice';
        document.getElementById('progAlertMessage').textContent = msg == null ? '' : String(msg);
        document.getElementById('progAlertModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgAlert() {
        document.getElementById('progAlertModal').classList.remove('active');
        unlockBodyScroll();
    }

    // ==================== CONFIRM INVEST ====================
    var _pendingInvestPid = null;
    var _pendingInvestName = '';

    function openProgConfirm(pid, name) {
        _pendingInvestPid = pid;
        _pendingInvestName = name || '';
        document.getElementById('progConfirmMessage').textContent =
            'Invest in ' + (name || 'this programme') + '?\n\nThis will register you as an investor in this programme.';
        document.getElementById('progConfirmModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgConfirm() {
        document.getElementById('progConfirmModal').classList.remove('active');
        unlockBodyScroll();
        _pendingInvestPid = null;
        _pendingInvestName = '';
    }
    function progConfirmAction() {
        if (!_pendingInvestPid) { closeProgConfirm(); return; }
        var pid = _pendingInvestPid;
        var name = _pendingInvestName;
        closeProgConfirm();
        doInvest(pid, name);
    }

    // ==================== CONFIRM CANCEL ====================
    function cancelProgrammeConfirm() {
        document.getElementById('progCancelModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgCancel() {
        document.getElementById('progCancelModal').classList.remove('active');
        unlockBodyScroll();
    }

    function progCancelAction() {
        var btn = document.getElementById('progCancelBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Cancelling...';

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'cancel_programme=1'
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                console.error('Bad JSON from server:', text);
                btn.disabled = false;
                btn.textContent = originalText;
                closeProgCancel();
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();

            if (data.success) {
                showProgAlert(data.message || 'Programme cancelled successfully.', 'Success');
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                showProgAlert(data.message || 'Unable to cancel programme.', 'Error');
            }
        })
        .catch(function (err) {
            console.error('Fetch error:', err);
            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    // ==================== VIEW PROGRAMME ====================
    function viewProgramme(pid) {
        var modal = document.getElementById('progDetailModal');
        var body  = document.getElementById('progDetailBody');
        var actions = document.getElementById('progDetailActions');
        var title = document.getElementById('progDetailTitle');

        title.textContent = 'Programme Details';
        body.innerHTML = '<div class="prog-modal-loading">Loading...</div>';
        actions.innerHTML = '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';

        modal.classList.add('active');
        lockBodyScroll();

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'get_programme_details=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                console.error('Bad JSON from server:', text);
                body.innerHTML = '<div class="prog-modal-error">Server returned an unexpected response.</div>';
                return;
            }

            if (!data.success) {
                body.innerHTML = '<div class="prog-modal-error">' + escapeHtml(data.message || 'Unable to load programme details.') + '</div>';
                return;
            }

            var p = data.programme;
            var html = '';

            html += '<div class="prog-detail-header">';
            html +=   '<div class="prog-detail-name">' + escapeHtml(p.program_name) + '</div>';
            html +=   '<div class="prog-detail-dev">Developed by <strong>' + escapeHtml(p.developer_name) + '</strong></div>';
            html += '</div>';

            html += '<div class="prog-detail-section-title">Analytics</div>';
            html += '<div class="prog-detail-grid prog-detail-grid-2col">';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Winrate</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.winrate : null, '%') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Highest Drawdown</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.highest_drawdown : null, '') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Consecutive Sequential Loss</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.consecutive_sequential_loss : null, '') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Highest Trades Per Day</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.highest_trades_per_day : null, '') + '</span></div>';
            html += '</div>';

            html += '<div class="prog-detail-section-title">Requirements</div>';
            html += '<div class="prog-detail-grid">';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Contract Duration</span><span class="prog-detail-value">' + escapeHtml(String(p.contract_duration)) + ' days</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Programme Share</span><span class="prog-detail-value">' + escapeHtml(String(p.developer_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Investor Share</span><span class="prog-detail-value">' + escapeHtml(String(p.investor_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Minimum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.minimum_investment_amount).toFixed(2)) + '</span></div>';
            if (Number(p.maximum_investment_amount) > 0) {
                html += '<div class="prog-detail-row"><span class="prog-detail-label">Maximum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.maximum_investment_amount).toFixed(2)) + '</span></div>';
            }
            html += '</div>';

            body.innerHTML = html;

            var actionsHtml = '';
            if (data.already_invested) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already Invested</button>';
            } else if (data.has_any_investment) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already in a Programme</button>';
            } else {
                actionsHtml += '<button type="button" class="prog-btn-invest" onclick="openProgConfirm(' + p.id + ', \'' + escapeHtml(p.program_name).replace(/'/g, "\\'") + '\')">Invest in this Programme</button>';
            }
            actionsHtml += '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';
            actions.innerHTML = actionsHtml;
        })
        .catch(function (err) {
            console.error('Fetch error:', err);
            body.innerHTML = '<div class="prog-modal-error">Network error. Please try again.</div>';
        });
    }

    function formatVal(v, suffix) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return escapeHtml(String(v));
        var s;
        if (Number.isInteger(n)) s = String(n);
        else s = n.toFixed(2);
        return escapeHtml(s) + (suffix ? ' ' + suffix : '');
    }

    function closeProgDetailModal() {
        document.getElementById('progDetailModal').classList.remove('active');
        unlockBodyScroll();
    }

    // ==================== INVEST ====================
    function investInProgramme(pid, name) {
        if (!pid) return;

        if (_userHasProgramme) {
            showProgAlert('You are already participating in a programme. Finish or reset it before joining another.', 'Not Allowed');
            return;
        }

        openProgConfirm(pid, name);
    }

    function doInvest(pid, name) {
        var btns = document.querySelectorAll('.prog-btn-invest');
        btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = true; } });

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'invest_in_programme=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                console.error('Bad JSON from server:', text);
                btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });

            if (data.success) {
                showProgAlert(data.message || 'You have successfully invested.', 'Success');
                setTimeout(function () { window.location.reload(); }, 1200);
            } else {
                showProgAlert(data.message || 'Unable to invest in this programme.', 'Error');
            }
        })
        .catch(function (err) {
            console.error('Fetch error:', err);
            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    // ==================== CLOSE ON ESCAPE ====================
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var dm = document.getElementById('progDetailModal');
            if (dm && dm.classList.contains('active')) { closeProgDetailModal(); return; }
            var cm = document.getElementById('progConfirmModal');
            if (cm && cm.classList.contains('active')) { closeProgConfirm(); return; }
            var xm = document.getElementById('progCancelModal');
            if (xm && xm.classList.contains('active')) { closeProgCancel(); return; }
            var am = document.getElementById('progAlertModal');
            if (am && am.classList.contains('active')) { closeProgAlert(); }
        }
    });

    // ==================== EXPORTS ====================
    window.onProgSearch           = onProgSearch;
    window.viewProgramme          = viewProgramme;
    window.closeProgDetailModal   = closeProgDetailModal;
    window.investInProgramme      = investInProgramme;
    window.openProgConfirm        = openProgConfirm;
    window.closeProgConfirm       = closeProgConfirm;
    window.progConfirmAction      = progConfirmAction;
    window.cancelProgrammeConfirm = cancelProgrammeConfirm;
    window.closeProgCancel        = closeProgCancel;
    window.progCancelAction       = progCancelAction;
    window.closeProgAlert         = closeProgAlert;
</script>

</body>
</html>