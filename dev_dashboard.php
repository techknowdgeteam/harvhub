<?php
// dev_dashboard.php — Developer Dashboard
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
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== FETCH USER (harvhub) ====================
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';

$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ==================== DEVELOPER CHECK ====================
$devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$devStmt->execute([$email]);
$developer = $devStmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header("Location: dev_app.php");
    exit;
}

$brokerConnected = !empty($developer['broker']) && !empty($developer['server']) && !empty($developer['login']);
$currentBroker   = $developer['broker'] ?? '';

// ==================== FETCH SERVER LIMITS ====================
$serverContractDuration = 0;
$serverMinBrokerBalance = 0.0;

try {
    $stmt = $pdo->prepare("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $srv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($srv) {
        $serverContractDuration = (int)($srv['contract_duration'] ?? 0);
        $serverMinBrokerBalance = (float)($srv['min_broker_balance'] ?? 0);
    }
} catch (PDOException $e) {}

// ==================== HELPER: parse list (handles both CSV and JSON) ====================
function parseList($str) {
    if (!is_string($str) || trim($str) === '') return [];
    $str = trim($str);

    // If it looks like JSON array, decode it
    if (strlen($str) > 0 && $str[0] === '[') {
        $decoded = json_decode($str, true);
        if (is_array($decoded)) {
            $result = [];
            foreach ($decoded as $v) {
                $v = trim((string)$v);
                if ($v !== '') $result[] = $v;
            }
            return array_values(array_unique($result));
        }
    }

    // Otherwise treat as comma-separated
    $parts = array_map('trim', explode(',', $str));
    $parts = array_filter($parts, function ($v) { return $v !== ''; });
    return array_values(array_unique($parts));
}

// ==================== HANDLE AJAX: SAVE SELECTED SYMBOLS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected_symbols'])) {
    header('Content-Type: application/json');

    $raw = $_POST['symbols'] ?? '[]';
    $symbols = json_decode($raw, true);

    if (!is_array($symbols)) {
        echo json_encode(['success' => false, 'message' => 'Invalid symbol data.']);
        exit;
    }

    $clean = [];
    foreach ($symbols as $s) {
        $s = trim((string)$s);
        if ($s !== '' && strlen($s) <= 32) {
            $clean[] = $s;
        }
    }
    $clean = array_values(array_unique($clean));

    try {
        $pdo->beginTransaction();

        $reset = $pdo->prepare("UPDATE broker_symbols SET symbol_selected = 0 WHERE userid = ?");
        $reset->execute([$userId]);

        if (!empty($clean)) {
            $check = $pdo->prepare("SELECT id FROM broker_symbols WHERE userid = ? AND symbol = ? LIMIT 1");
            $insert = $pdo->prepare("INSERT INTO broker_symbols (userid, symbol, symbol_selected) VALUES (?, ?, 1)");
            $update = $pdo->prepare("UPDATE broker_symbols SET symbol_selected = 1 WHERE userid = ? AND symbol = ?");

            foreach ($clean as $sym) {
                $check->execute([$userId, $sym]);
                if ($check->fetch(PDO::FETCH_ASSOC)) {
                    $update->execute([$userId, $sym]);
                } else {
                    $insert->execute([$userId, $sym]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Selected symbols saved.']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save selected symbols.']);
    }
    exit;
}

// ==================== HANDLE AJAX: SAVE SELECTED TIMEFRAMES (GLOBAL) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected_timeframes'])) {
    header('Content-Type: application/json');

    $raw = $_POST['timeframes'] ?? '[]';
    $tfs = json_decode($raw, true);

    if (!is_array($tfs)) {
        echo json_encode(['success' => false, 'message' => 'Invalid timeframe data.']);
        exit;
    }

    $clean = [];
    foreach ($tfs as $t) {
        $t = trim((string)$t);
        if ($t !== '' && strlen($t) <= 32) {
            $clean[] = $t;
        }
    }
    $clean = array_values(array_unique($clean));

    try {
        // GLOBAL timeframes are stored as JSON in a dedicated row.
        $globalSymbol = '__GLOBAL_TF__';
        $jsonValue = json_encode($clean);

        $chk = $pdo->prepare("SELECT id FROM broker_symbols WHERE userid = ? AND symbol = ? LIMIT 1");
        $chk->execute([$userId, $globalSymbol]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $upd = $pdo->prepare("UPDATE broker_symbols SET selected_timeframes = ? WHERE id = ?");
            $upd->execute([$jsonValue, (int)$row['id']]);
        } else {
            $ins = $pdo->prepare("INSERT INTO broker_symbols (userid, symbol, symbol_selected, selected_timeframes) VALUES (?, ?, 0, ?)");
            $ins->execute([$userId, $globalSymbol, $jsonValue]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Timeframes saved.',
            'timeframes' => $clean
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save timeframes.']);
    }
    exit;
}

// ==================== HANDLE AJAX: CREATE PROGRAMME ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_programme'])) {
    header('Content-Type: application/json');

    $programName = trim($_POST['program_name'] ?? '');

    if ($programName === '') {
        echo json_encode(['success' => false, 'message' => 'Programme name is required.']);
        exit;
    }
    if (strlen($programName) > 255) {
        echo json_encode(['success' => false, 'message' => 'Programme name is too long.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO programme (userid, program_name, visibility, advertisement) VALUES (?, ?, 0, 0)");
        $stmt->execute([$userId, $programName]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success'  => true,
            'message'  => 'Programme created successfully.',
            'programme' => [
                'id'           => $newId,
                'program_name' => $programName,
                'visibility'   => 0,
                'advertisement'=> 0
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to create programme.']);
    }
    exit;
}

// ==================== HANDLE AJAX: DELETE PROGRAMME ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_programme'])) {
    header('Content-Type: application/json');

    $programmeId = (int)($_POST['programme_id'] ?? 0);

    if ($programmeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
        exit;
    }

    try {
        $chk = $pdo->prepare("SELECT id, advertisement FROM programme WHERE id = ? AND userid = ? LIMIT 1");
        $chk->execute([$programmeId, $userId]);
        $prog = $chk->fetch(PDO::FETCH_ASSOC);

        if (!$prog) {
            echo json_encode(['success' => false, 'message' => 'Programme not found.']);
            exit;
        }

        if ((int)$prog['advertisement'] === 1) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete a programme with advertisement enabled.']);
            exit;
        }

        $invChk = $pdo->prepare("SELECT COUNT(*) AS cnt FROM programme_investors WHERE programme_id = ? AND developerid = ? AND investorid > 0");
        $invChk->execute([$programmeId, $userId]);
        $invRow = $invChk->fetch(PDO::FETCH_ASSOC);

        if ($invRow && (int)$invRow['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete a programme that has investors.']);
            exit;
        }

        $pdo->beginTransaction();

        $delInv = $pdo->prepare("DELETE FROM programme_investors WHERE programme_id = ? AND developerid = ?");
        $delInv->execute([$programmeId, $userId]);

        $delProg = $pdo->prepare("DELETE FROM programme WHERE id = ? AND userid = ?");
        $delProg->execute([$programmeId, $userId]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Programme deleted successfully.']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete programme: ' . $e->getMessage()]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Unexpected error: ' . $e->getMessage()]);
    }
    exit;
}

// ==================== HANDLE AJAX: SAVE VISIBILITY ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_visibility'])) {
    header('Content-Type: application/json');

    $raw = $_POST['visibility_map'] ?? '{}';
    $map = json_decode($raw, true);

    if (!is_array($map)) {
        echo json_encode(['success' => false, 'message' => 'Invalid visibility data.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $verify = $pdo->prepare("SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1");
        $upd    = $pdo->prepare("UPDATE programme SET visibility = ? WHERE id = ? AND userid = ?");

        $updated = 0;
        foreach ($map as $pid => $val) {
            $pid = (int)$pid;
            $val = (int)$val;
            if ($pid <= 0) continue;
            if ($val !== 0 && $val !== 1) continue;

            $verify->execute([$pid, $userId]);
            if ($verify->fetch(PDO::FETCH_ASSOC)) {
                $upd->execute([$val, $pid, $userId]);
                $updated++;
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => $updated > 0 ? 'Visibility updated.' : 'Nothing to update.',
            'updated' => $updated
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to save visibility.']);
    }
    exit;
}

// ==================== HANDLE AJAX: SAVE REQUIREMENTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_requirements'])) {
    header('Content-Type: application/json');

    $programmeId = (int)($_POST['programme_id'] ?? 0);

    if ($programmeId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
        exit;
    }

    $chk = $pdo->prepare("SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $chk->execute([$programmeId, $userId]);
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Programme not found.']);
        exit;
    }

    $contractDuration   = (int)($_POST['contract_duration'] ?? 0);
    $developerPercent   = (int)($_POST['developer_percentage'] ?? 0);
    $investorPercent    = (int)($_POST['investor_percentage'] ?? 0);
    $minInvestment      = (float)($_POST['minimum_investment_amount'] ?? 0);
    $maxInvestment      = (float)($_POST['maximum_investment_amount'] ?? 0);

    try {
        $srvStmt = $pdo->prepare("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1");
        $srvStmt->execute();
        $srvRow = $srvStmt->fetch(PDO::FETCH_ASSOC);
        if ($srvRow) {
            $floorContractDuration = (int)($srvRow['contract_duration'] ?? 0);
            $floorMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
        } else {
            $floorContractDuration = 0;
            $floorMinBrokerBalance = 0.0;
        }
    } catch (PDOException $e) {
        $floorContractDuration = 0;
        $floorMinBrokerBalance = 0.0;
    }

    if ($contractDuration < $floorContractDuration) {
        echo json_encode([
            'success' => false,
            'message' => "Contract Duration must be at least {$floorContractDuration} days.",
            'field'   => 'contract_duration',
            'floor'   => $floorContractDuration
        ]);
        exit;
    }

    if ($minInvestment < $floorMinBrokerBalance) {
        echo json_encode([
            'success' => false,
            'message' => 'Minimum Investment Amount must be at least $' . number_format($floorMinBrokerBalance, 2) . '.',
            'field'   => 'minimum_investment_amount',
            'floor'   => $floorMinBrokerBalance
        ]);
        exit;
    }

    if ($developerPercent < 0)  $developerPercent = 0;
    if ($investorPercent < 0)   $investorPercent = 0;
    if ($maxInvestment < 0)     $maxInvestment = 0;

    try {
        $checkRow = $pdo->prepare("SELECT id FROM programme_investors WHERE programme_id = ? AND developerid = ? LIMIT 1");
        $checkRow->execute([$programmeId, $userId]);
        $existing = $checkRow->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $upd = $pdo->prepare("
                UPDATE programme_investors
                SET contract_duration = ?,
                    developer_percentage = ?,
                    investor_percentage = ?,
                    minimum_investment_amount = ?,
                    maximum_investment_amount = ?
                WHERE id = ?
            ");
            $upd->execute([
                $contractDuration,
                $developerPercent,
                $investorPercent,
                $minInvestment,
                $maxInvestment,
                (int)$existing['id']
            ]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO programme_investors
                    (investorid, developerid, programme_id,
                     contract_duration, developer_percentage, investor_percentage,
                     minimum_investment_amount, maximum_investment_amount)
                VALUES (0, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $userId,
                $programmeId,
                $contractDuration,
                $developerPercent,
                $investorPercent,
                $minInvestment,
                $maxInvestment
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Requirements saved.'
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to save requirements.']);
    }
    exit;
}

// ==================== FETCH BROKER SYMBOLS ====================
$brokerSymbols = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, symbol, symbol_selected, timeframes, selected_timeframes
        FROM broker_symbols
        WHERE userid = ?
        ORDER BY symbol ASC
    ");
    $stmt->execute([$userId]);
    $brokerSymbols = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $brokerSymbols = [];
}

// Separate real symbols from the global timeframes row
$allBrokerSymbols = [];
$globalTimeframesSelected = [];
foreach ($brokerSymbols as $r) {
    if ($r['symbol'] === '__GLOBAL_TF__') {
        $globalTimeframesSelected = parseList($r['selected_timeframes'] ?? '');
    } else {
        $allBrokerSymbols[] = $r['symbol'];
    }
}

// Selected symbols (only real symbols with symbol_selected = 1)
$selectedSymbols = [];
foreach ($brokerSymbols as $r) {
    if ($r['symbol'] !== '__GLOBAL_TF__' && (int)$r['symbol_selected'] === 1) {
        $selectedSymbols[] = $r['symbol'];
    }
}

// Available timeframes – collected from all real broker symbols
$availableTimeframes = [];
foreach ($brokerSymbols as $r) {
    if ($r['symbol'] === '__GLOBAL_TF__') continue;
    $tfs = parseList($r['timeframes'] ?? '');
    foreach ($tfs as $tf) {
        $availableTimeframes[$tf] = true;
    }
}
$availableTimeframes = array_keys($availableTimeframes);
sort($availableTimeframes);

// ==================== FETCH USER PROGRAMMES ====================
$programmes = [];
$programmeInvestorCounts = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, program_name, visibility, advertisement
        FROM programme
        WHERE userid = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$userId]);
    $programmes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($programmes)) {
        $ids = array_map(function ($p) { return (int)$p['id']; }, $programmes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt2 = $pdo->prepare("
            SELECT programme_id, COUNT(*) AS cnt
            FROM programme_investors
            WHERE developerid = ? AND investorid > 0 AND programme_id IN ($placeholders)
            GROUP BY programme_id
        ");
        $stmt2->execute(array_merge([$userId], $ids));

        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            $programmeInvestorCounts[(int)$row['programme_id']] = (int)$row['cnt'];
        }
    }
} catch (PDOException $e) {
    $programmes = [];
    $programmeInvestorCounts = [];
}

// ==================== FETCH EXISTING REQUIREMENTS ====================
$requirementsByProgramme = [];
try {
    if (!empty($programmes)) {
        $ids = array_map(function ($p) { return (int)$p['id']; }, $programmes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $pdo->prepare("
            SELECT id, programme_id, contract_duration, developer_percentage,
                   investor_percentage, minimum_investment_amount,
                   maximum_investment_amount
            FROM programme_investors
            WHERE developerid = ? AND programme_id IN ($placeholders)
            ORDER BY id ASC
        ");
        $stmt->execute(array_merge([$userId], $ids));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int)$row['programme_id'];
            if (!isset($requirementsByProgramme[$pid])) {
                $requirementsByProgramme[$pid] = $row;
            }
        }
    }
} catch (PDOException $e) {
    $requirementsByProgramme = [];
}

// ==================== FETCH PUBLISHED PROGRAMMES + INVESTORS ====================
$publishedProgrammes = [];
$publishedProgrammesInvestors = [];

try {
    $stmt = $pdo->prepare("
        SELECT id, program_name, visibility, advertisement
        FROM programme
        WHERE userid = ? AND visibility = 1
        ORDER BY id DESC
    ");
    $stmt->execute([$userId]);
    $publishedProgrammes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($publishedProgrammes)) {
        $pubIds = array_map(function ($p) { return (int)$p['id']; }, $publishedProgrammes);
        $placeholders = implode(',', array_fill(0, count($pubIds), '?'));

        $stmt = $pdo->prepare("
            SELECT pi.id,
                   pi.programme_id,
                   pi.investorid,
                   pi.invested_at,
                   h.fullname AS investor_name,
                   h.email    AS investor_email,
                   h.broker_balance,
                   h.profitandloss
            FROM programme_investors pi
            LEFT JOIN harvhub h ON h.id = pi.investorid
            WHERE pi.developerid = ?
              AND pi.investorid > 0
              AND pi.programme_id IN ($placeholders)
            ORDER BY pi.invested_at DESC, pi.id DESC
        ");
        $stmt->execute(array_merge([$userId], $pubIds));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int)$row['programme_id'];
            if (!isset($publishedProgrammesInvestors[$pid])) {
                $publishedProgrammesInvestors[$pid] = [];
            }
            $publishedProgrammesInvestors[$pid][] = $row;
        }
    }
} catch (PDOException $e) {
    $publishedProgrammes = [];
    $publishedProgrammesInvestors = [];
}

// ==================== SPINNER ====================
function showSpinner() {
    echo '<div class="spinner-overlay active" id="spinnerOverlay">
        <div class="spinner"></div>
        <p class="spinner-text">Loading...</p>
    </div>
    <script>
        window.addEventListener("load", function() {
            var o = document.getElementById("spinnerOverlay");
            if (o) setTimeout(function(){ o.classList.remove("active"); }, 300);
        });
        document.addEventListener("DOMContentLoaded", function() {
            var o = document.getElementById("spinnerOverlay");
            if (o) setTimeout(function(){ o.classList.remove("active"); }, 200);
        });
    </script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Developer Dashboard - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
<?php include 'dev_dashboard_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div class="dd-page-wrapper">

        <!-- Header -->
        <div class="dd-page-header">
            <h1>Developer Dashboard</h1>
            <p>Configure broker symbols, timeframes and manage your programmes</p>
        </div>

        <!-- Broker status -->
        <?php if (!$brokerConnected): ?>
            <div class="dd-notice dd-notice-warning">
                <strong>Broker not connected.</strong>
                Connect your broker first on the <a href="connect_dev_broker.php">Connect Broker</a> page.
            </div>
        <?php else: ?>
            <div class="dd-notice dd-notice-info">
                <strong>Broker:</strong> <?= htmlspecialchars($currentBroker) ?>
            </div>
        <?php endif; ?>

        <!-- ==================== SUB-TABS ==================== -->
        <div class="dd-subtabs">
            <button type="button" class="dd-subtab active" data-subtab="programmes" onclick="switchDdSubTab('programmes')">Programmes</button>
            <button type="button" class="dd-subtab" data-subtab="published" onclick="switchDdSubTab('published')">Published</button>
            <button type="button" class="dd-subtab" data-subtab="symbols" onclick="switchDdSubTab('symbols')">Symbols</button>
            <button type="button" class="dd-subtab" data-subtab="timeframes" onclick="switchDdSubTab('timeframes')">Timeframes</button>
            <button type="button" class="dd-subtab" data-subtab="visibility" onclick="switchDdSubTab('visibility')">Visibility</button>
            <button type="button" class="dd-subtab" data-subtab="requirements" onclick="switchDdSubTab('requirements')">Set Requirements</button>
        </div>

        <!-- ==================== SUB-TAB: PROGRAMMES ==================== -->
        <div class="dd-subtab-content active" id="ddSubProgrammes">
            <section class="dd-card">
                <div class="dd-card-head">
                    <div>
                        <h2 class="dd-card-title">Your Programmes</h2>
                        <p class="dd-card-subtitle">Create and manage your trading programmes.</p>
                    </div>
                    <button type="button" class="dd-btn-secondary" onclick="openCreateProgrammeModal()">
                        Create Programme
                    </button>
                </div>

                <div id="programmesList" class="dd-programmes-list">
                    <?php if (empty($programmes)): ?>
                        <div class="dd-empty">
                            <span class="dd-empty-icon">—</span>
                            <p>You don't have any programme yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($programmes as $p):
                            $pid = (int)$p['id'];
                            $advertisement = (int)$p['advertisement'];
                            $investorCount = $programmeInvestorCounts[$pid] ?? 0;
                            $canDelete = ($advertisement === 0 && $investorCount === 0);
                        ?>
                            <div class="dd-programme-item" data-programme-id="<?= $pid ?>">
                                <span class="dd-programme-name"><?= htmlspecialchars($p['program_name']) ?></span>

                                <?php if ($canDelete): ?>
                                    <button type="button"
                                            class="dd-btn-delete"
                                            id="deleteBtn-<?= $pid ?>"
                                            onclick="deleteProgramme(<?= $pid ?>)">
                                        Delete
                                    </button>
                                <?php endif; ?>

                                <button type="button"
                                        class="dd-btn-switch"
                                        onclick="goToProgrammeTraining(<?= $pid ?>)">
                                    Switch
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- ==================== SUB-TAB: PUBLISHED ==================== -->
        <div class="dd-subtab-content" id="ddSubPublished">
            <section class="dd-card">
                <h2 class="dd-card-title">Published Programmes</h2>
                <p class="dd-card-subtitle">Programmes that are public, with their investors.</p>

                <?php if (empty($publishedProgrammes)): ?>
                    <div class="dd-empty">
                        <span class="dd-empty-icon">—</span>
                        <p>You have not published any programme yet.</p>
                    </div>
                <?php else: ?>
                    <div class="dd-published-list">
                        <?php foreach ($publishedProgrammes as $pp):
                            $pid = (int)$pp['id'];
                            $investors = $publishedProgrammesInvestors[$pid] ?? [];
                        ?>
                            <div class="dd-published-item">
                                <div class="dd-published-head">
                                    <span class="dd-published-name"><?= htmlspecialchars($pp['program_name']) ?></span>
                                    <span class="dd-published-badge">Public</span>
                                </div>

                                <div class="dd-published-investors">
                                    <?php if (empty($investors)): ?>
                                        <div class="dd-published-empty">No investors yet.</div>
                                    <?php else: ?>
                                        <?php foreach ($investors as $inv):
                                            $name = $inv['investor_name'] ?: ($inv['investor_email'] ?: 'Anonymous');
                                            $amt  = (float)($inv['broker_balance'] ?? 0);
                                            $pnl  = (float)($inv['profitandloss'] ?? 0);
                                            $pnlCls = $pnl > 0 ? 'pnl-pos' : ($pnl < 0 ? 'pnl-neg' : 'pnl-neutral');
                                        ?>
                                            <div class="dd-investor-row">
                                                <div class="dd-investor-name"><?= htmlspecialchars($name) ?></div>
                                                <div class="dd-investor-stats">
                                                    <div class="dd-investor-stat">
                                                        <span class="dd-investor-stat-label">Invested</span>
                                                        <span class="dd-investor-stat-value">$<?= htmlspecialchars(number_format($amt, 2, '.', '')) ?></span>
                                                    </div>
                                                    <div class="dd-investor-stat">
                                                        <span class="dd-investor-stat-label">PnL</span>
                                                        <span class="dd-investor-stat-value <?= $pnlCls ?>">$<?= htmlspecialchars(number_format($pnl, 2, '.', '')) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ==================== SUB-TAB: SYMBOLS ==================== -->
        <div class="dd-subtab-content" id="ddSubSymbols">
            <section class="dd-card">
                <div class="dd-card-head">
                    <div>
                        <h2 class="dd-card-title">Select Symbols</h2>
                        <p class="dd-card-subtitle">
                            Symbols offered by <strong><?= htmlspecialchars($currentBroker ?: 'your broker') ?></strong>.
                        </p>
                    </div>
                    <button type="button" class="dd-btn-secondary" onclick="openSymbolsModal()">
                        View All Symbols
                    </button>
                </div>

                <div class="dd-selected-header">
                    <span>Selected Symbols</span>
                    <span class="dd-count-badge" id="selectedCount"><?= count($selectedSymbols) ?></span>
                </div>

                <div id="selectedSymbolsList" class="dd-selected-list dd-selected-list-scroll">
                    <!-- Populated by JS -->
                </div>

                <button type="button"
                        class="dd-btn-primary"
                        id="saveSelectedBtn"
                        onclick="saveSelectedSymbols()">
                    Save Selected
                </button>
            </section>
        </div>

        <!-- ==================== SUB-TAB: TIMEFRAMES ==================== -->
        <!-- Timeframes are GLOBAL — not tied to any symbol -->
        <div class="dd-subtab-content" id="ddSubTimeframes">
            <section class="dd-card">
                <div class="dd-card-head">
                    <div>
                        <h2 class="dd-card-title">Select Timeframes</h2>
                        <p class="dd-card-subtitle">
                            Choose the global timeframes that apply to all your trading.
                        </p>
                    </div>
                    <button type="button" class="dd-btn-secondary" onclick="openTimeframesModal()">
                        View All Timeframes
                    </button>
                </div>

                <div class="dd-selected-header">
                    <span>Selected Timeframes</span>
                    <span class="dd-count-badge" id="selectedTfCount">0</span>
                </div>

                <div id="selectedTimeframesList" class="dd-selected-list dd-selected-list-scroll">
                    <!-- Populated by JS -->
                </div>

                <button type="button"
                        class="dd-btn-primary"
                        id="saveSelectedTfBtn"
                        onclick="saveSelectedTimeframes()">
                    Save Timeframes
                </button>
            </section>
        </div>

        <!-- ==================== SUB-TAB: VISIBILITY ==================== -->
        <div class="dd-subtab-content" id="ddSubVisibility">
            <section class="dd-card">
                <h2 class="dd-card-title">Programme Visibility</h2>
                <p class="dd-card-subtitle">Choose whether each programme is public or private.</p>

                <div id="visibilityList" class="dd-visibility-list">
                    <?php if (empty($programmes)): ?>
                        <div class="dd-empty">
                            <span class="dd-empty-icon">—</span>
                            <p>You don't have any programme yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($programmes as $p): ?>
                            <div class="dd-visibility-item">
                                <span class="dd-visibility-name"><?= htmlspecialchars($p['program_name']) ?></span>
                                <select class="dd-select dd-visibility-select"
                                        data-programme-id="<?= (int)$p['id'] ?>">
                                    <option value="1" <?= ((int)$p['visibility'] === 1) ? 'selected' : '' ?>>Public</option>
                                    <option value="0" <?= ((int)$p['visibility'] === 0) ? 'selected' : '' ?>>Private</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($programmes)): ?>
                    <button type="button" class="dd-btn-primary" id="saveVisibilityBtn" onclick="saveVisibility()">
                        Save Visibility
                    </button>
                <?php endif; ?>
            </section>
        </div>

        <!-- ==================== SUB-TAB: SET REQUIREMENTS ==================== -->
        <div class="dd-subtab-content" id="ddSubRequirements">
            <section class="dd-card">
                <h2 class="dd-card-title">Set Requirements</h2>
                <p class="dd-card-subtitle">Click a programme to expand and edit its requirements.</p>

                <div class="dd-req-note">
                    Contract Duration must be at least <strong><?= (int)$serverContractDuration ?> days</strong>.
                    Minimum Investment Amount must be at least
                    <strong>$<?= number_format($serverMinBrokerBalance, 2) ?></strong>.
                </div>

                <?php if (empty($programmes)): ?>
                    <div class="dd-empty">
                        <span class="dd-empty-icon">—</span>
                        <p>You don't have any programme yet.</p>
                    </div>
                <?php else: ?>
                    <div class="dd-req-list">
                        <?php foreach ($programmes as $p):
                            $pid = (int)$p['id'];
                            $req = $requirementsByProgramme[$pid] ?? null;
                            $cd  = $req ? (int)$req['contract_duration'] : 0;
                            $dp  = $req ? (int)$req['developer_percentage'] : 0;
                            $ip  = $req ? (int)$req['investor_percentage'] : 0;
                            $min = $req ? (float)$req['minimum_investment_amount'] : 0;
                            $max = $req ? (float)$req['maximum_investment_amount'] : 0;
                        ?>
                            <div class="dd-req-item" id="reqItem-<?= $pid ?>">
                                <button type="button"
                                        class="dd-req-toggle"
                                        onclick="toggleRequirement(<?= $pid ?>)"
                                        aria-expanded="false">
                                    <span class="dd-req-toggle-name"><?= htmlspecialchars($p['program_name']) ?></span>
                                    <span class="dd-req-toggle-icon">▾</span>
                                </button>

                                <div class="dd-req-body" id="reqBody-<?= $pid ?>" style="display:none;">
                                    <div class="dd-req-grid">
                                        <div class="dd-form-group">
                                            <label class="dd-label" for="cd-<?= $pid ?>">Contract Duration (days)</label>
                                            <input type="number" min="<?= (int)$serverContractDuration ?>" step="1"
                                                   id="cd-<?= $pid ?>"
                                                   class="dd-input dd-req-input"
                                                   data-field="contract_duration"
                                                   data-programme-id="<?= $pid ?>"
                                                   value="<?= (int)$cd ?>">
                                            <div class="dd-field-hint">Minimum: <?= (int)$serverContractDuration ?> days</div>
                                        </div>

                                        <div class="dd-form-group">
                                            <label class="dd-label" for="dp-<?= $pid ?>">Developer Percentage (%)</label>
                                            <input type="number" min="0" max="100" step="1"
                                                   id="dp-<?= $pid ?>"
                                                   class="dd-input dd-req-input"
                                                   data-field="developer_percentage"
                                                   data-programme-id="<?= $pid ?>"
                                                   value="<?= (int)$dp ?>">
                                        </div>

                                        <div class="dd-form-group">
                                            <label class="dd-label" for="ip-<?= $pid ?>">Investor Percentage (%)</label>
                                            <input type="number" min="0" max="100" step="1"
                                                   id="ip-<?= $pid ?>"
                                                   class="dd-input dd-req-input"
                                                   data-field="investor_percentage"
                                                   data-programme-id="<?= $pid ?>"
                                                   value="<?= (int)$ip ?>">
                                        </div>

                                        <div class="dd-form-group">
                                            <label class="dd-label" for="min-<?= $pid ?>">Minimum Investment Amount</label>
                                            <input type="number"
                                                   min="<?= htmlspecialchars(number_format($serverMinBrokerBalance, 2, '.', '')) ?>"
                                                   step="0.01"
                                                   id="min-<?= $pid ?>"
                                                   class="dd-input dd-req-input"
                                                   data-field="minimum_investment_amount"
                                                   data-programme-id="<?= $pid ?>"
                                                   value="<?= htmlspecialchars(number_format($min, 2, '.', '')) ?>">
                                            <div class="dd-field-hint">Minimum: $<?= number_format($serverMinBrokerBalance, 2) ?></div>
                                        </div>

                                        <div class="dd-form-group">
                                            <label class="dd-label" for="max-<?= $pid ?>">Maximum Investment Amount</label>
                                            <input type="number" min="0" step="0.01"
                                                   id="max-<?= $pid ?>"
                                                   class="dd-input dd-req-input"
                                                   data-field="maximum_investment_amount"
                                                   data-programme-id="<?= $pid ?>"
                                                   value="<?= htmlspecialchars(number_format($max, 2, '.', '')) ?>">
                                        </div>
                                    </div>

                                    <button type="button"
                                            class="dd-btn-primary"
                                            id="saveReqBtn-<?= $pid ?>"
                                            onclick="saveRequirements(<?= $pid ?>)">
                                        Save Requirements
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

    </div>

    <!-- ==================== MODAL: SYMBOLS PICKER ==================== -->
    <div id="symbolsPickerModal" class="dd-modal">
        <div class="dd-modal-content dd-symbols-modal-content">
            <h2 class="dd-modal-title">All <?= htmlspecialchars($currentBroker) ?> Symbols</h2>

            <div class="dd-symbols-modal-search-wrap">
                <input type="text"
                       id="symbolsModalSearch"
                       class="dd-input"
                       placeholder="Search symbol..."
                       autocomplete="off"
                       oninput="filterSymbolsModal(this.value)">
            </div>

            <div class="dd-symbols-modal-meta">
                <span id="symbolsModalCount">0 selected</span>
                <button type="button" class="dd-link-btn" onclick="clearAllSymbolChecks()">Clear all</button>
            </div>

            <div id="symbolsModalList" class="dd-symbols-modal-list">
                <!-- Populated by JS -->
            </div>

            <div class="dd-modal-actions">
                <button type="button" class="dd-btn-primary" id="symbolsModalSaveBtn" onclick="applySymbolsModal()">
                    Apply Selection
                </button>
                <button type="button" class="dd-btn-ghost" onclick="closeSymbolsModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: TIMEFRAMES PICKER ==================== -->
    <div id="timeframesPickerModal" class="dd-modal">
        <div class="dd-modal-content dd-symbols-modal-content">
            <h2 class="dd-modal-title">Select Timeframes</h2>

            <div class="dd-symbols-modal-meta">
                <span id="tfModalCount">0 selected</span>
                <button type="button" class="dd-link-btn" onclick="clearAllTfChecks()">Clear all</button>
            </div>

            <div id="timeframesModalList" class="dd-symbols-modal-list">
                <!-- Populated by JS -->
            </div>

            <div class="dd-modal-actions">
                <button type="button" class="dd-btn-primary" onclick="applyTimeframesModal()">
                    Apply Selection
                </button>
                <button type="button" class="dd-btn-ghost" onclick="closeTimeframesModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CREATE PROGRAMME ==================== -->
    <div id="createProgrammeModal" class="dd-modal">
        <div class="dd-modal-content">
            <h2 class="dd-modal-title">Create Programme</h2>
            <div class="dd-modal-body">
                <div class="dd-form-group">
                    <label for="programmeNameInput" class="dd-label">Your Programme Name</label>
                    <input type="text"
                           id="programmeNameInput"
                           class="dd-input"
                           placeholder="e.g. Gold Scalper Pro"
                           maxlength="255">
                </div>
                <div id="programmeModalError" class="dd-modal-error" style="display:none;"></div>
            </div>
            <div class="dd-modal-actions">
                <button type="button" class="dd-btn-primary" id="createProgrammeBtn" onclick="submitCreateProgramme()">
                    Save
                </button>
                <button type="button" class="dd-btn-ghost" onclick="closeCreateProgrammeModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: ALERT ==================== -->
    <div id="ddAlertModal" class="dd-modal">
        <div class="dd-modal-content">
            <h2 class="dd-modal-title" id="ddAlertTitle">Notice</h2>
            <div class="dd-modal-body">
                <p id="ddAlertMessage" class="dd-modal-text">Message</p>
            </div>
            <div class="dd-modal-actions">
                <button type="button" class="dd-btn-primary" onclick="closeDdAlert()">OK</button>
            </div>
        </div>
    </div>

    <!-- ==================== MODAL: CONFIRM ==================== -->
    <div id="ddConfirmModal" class="dd-confirm-overlay" style="display:none;">
        <div class="dd-confirm-card">
            <div class="dd-confirm-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <h3 class="dd-confirm-title" id="ddConfirmTitle">Are you sure?</h3>
            <p class="dd-confirm-text" id="ddConfirmMessage">This action cannot be undone.</p>
            <div class="dd-confirm-actions">
                <button type="button" class="dd-confirm-cancel" id="ddConfirmCancelBtn">Cancel</button>
                <button type="button" class="dd-confirm-danger" id="ddConfirmOkBtn">Delete</button>
            </div>
        </div>
    </div>

<script>
    // ==================== SERVER FLOORS ====================
    var SERVER_CONTRACT_DURATION  = <?= (int)$serverContractDuration ?>;
    var SERVER_MIN_BROKER_BALANCE = <?= json_encode((float)$serverMinBrokerBalance) ?>;

    // ==================== STATE ====================
    // All real broker symbols (used by Symbols tab)
    var ALL_BROKER_SYMBOLS = <?= json_encode(array_values($allBrokerSymbols)) ?>;

    // Selected symbols (Symbols tab)
    var SELECTED_SYMBOLS = <?= json_encode(array_values($selectedSymbols)) ?>;

    // GLOBAL timeframes — available and selected (clean string arrays)
    var AVAILABLE_TIMEFRAMES = <?= json_encode(array_values($availableTimeframes)) ?>;
    var SELECTED_TIMEFRAMES = <?= json_encode(array_values($globalTimeframesSelected)) ?>;

    // Modal drafts
    var MODAL_DRAFT_SELECTION = [];         // symbols modal
    var MODAL_TF_DRAFT = [];                // TF modal draft

    // ==================== HELPERS ====================
    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }

    function escapeAttr(t) {
        return String(t).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    // Ensure value is a clean string (never JSON, never bracketed)
    function cleanString(v) {
        if (v == null) return '';
        var s = String(v).trim();
        // Strip surrounding quotes and brackets if present
        if (s.length >= 2 && s[0] === '"' && s[s.length - 1] === '"') {
            s = s.slice(1, -1);
        }
        if (s.length >= 2 && s[0] === '[' && s[s.length - 1] === ']') {
            try {
                var parsed = JSON.parse(s);
                if (Array.isArray(parsed) && parsed.length) {
                    s = String(parsed[0]).trim();
                }
            } catch (e) {}
        }
        return s.replace(/^[\[\]"]+|[\[\]"]+$/g, '').trim();
    }

    function cleanArray(arr) {
        if (!Array.isArray(arr)) return [];
        var out = [];
        for (var i = 0; i < arr.length; i++) {
            var c = cleanString(arr[i]);
            if (c !== '' && out.indexOf(c) === -1) out.push(c);
        }
        return out;
    }

    function lockBodyScroll() {
        if (document.body.dataset.ddModalLocked === '1') return;
        var y = window.scrollY || 0;
        document.body.dataset.ddModalLocked = '1';
        document.body.dataset.ddModalY = y;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + y + 'px';
    }

    function unlockBodyScroll() {
        if (document.body.dataset.ddModalLocked !== '1') return;
        var y = parseInt(document.body.dataset.ddModalY || '0', 10) || 0;
        document.body.dataset.ddModalLocked = '0';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, y);
    }

    // ==================== ALERT ====================
    function showDdAlert(msg, title) {
        document.getElementById('ddAlertTitle').textContent = title || 'Notice';
        document.getElementById('ddAlertMessage').textContent = msg == null ? '' : String(msg);
        document.getElementById('ddAlertModal').classList.add('active');
        lockBodyScroll();
    }
    function closeDdAlert() {
        document.getElementById('ddAlertModal').classList.remove('active');
        unlockBodyScroll();
    }

    // ==================== CONFIRM ====================
    function showDdConfirm(opts, callback) {
        var title    = (opts && opts.title)    || 'Are you sure?';
        var message  = (opts && opts.message)  || 'This action cannot be undone.';
        var okText   = (opts && opts.okText)   || 'Confirm';
        var cancelText = (opts && opts.cancelText) || 'Cancel';
        var danger   = (opts && opts.danger !== undefined) ? !!opts.danger : true;

        var modal    = document.getElementById('ddConfirmModal');
        var titleEl  = document.getElementById('ddConfirmTitle');
        var msgEl    = document.getElementById('ddConfirmMessage');
        var okBtn    = document.getElementById('ddConfirmOkBtn');
        var cancelBtn= document.getElementById('ddConfirmCancelBtn');

        titleEl.textContent = title;
        msgEl.textContent   = message;
        okBtn.textContent   = okText;
        cancelBtn.textContent = cancelText;

        okBtn.classList.remove('dd-confirm-danger', 'dd-confirm-primary');
        okBtn.classList.add(danger ? 'dd-confirm-danger' : 'dd-confirm-primary');

        var onOk = function () {
            cleanup();
            if (callback) callback(true);
        };
        var onCancel = function () {
            cleanup();
            if (callback) callback(false);
        };
        var onBackdrop = function (e) {
            if (e.target === modal) onCancel();
        };
        var onKey = function (e) {
            if (e.key === 'Escape') onCancel();
        };

        function cleanup() {
            modal.style.display = 'none';
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKey);
            unlockBodyScroll();
        }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKey);

        modal.style.display = 'flex';
        lockBodyScroll();
    }

    // ==================== SUB-TABS ====================
    function switchDdSubTab(tab) {
        document.querySelectorAll('.dd-subtab').forEach(function (el) {
            el.classList.toggle('active', el.dataset.subtab === tab);
        });
        document.querySelectorAll('.dd-subtab-content').forEach(function (el) {
            el.classList.remove('active');
        });

        if (tab === 'programmes') {
            document.getElementById('ddSubProgrammes').classList.add('active');
        } else if (tab === 'published') {
            document.getElementById('ddSubPublished').classList.add('active');
        } else if (tab === 'symbols') {
            document.getElementById('ddSubSymbols').classList.add('active');
        } else if (tab === 'timeframes') {
            document.getElementById('ddSubTimeframes').classList.add('active');
        } else if (tab === 'visibility') {
            document.getElementById('ddSubVisibility').classList.add('active');
        } else if (tab === 'requirements') {
            document.getElementById('ddSubRequirements').classList.add('active');
        }
    }

    // ==================== RENDER SELECTED SYMBOLS (Symbols tab) ====================
    function renderSelectedSymbols() {
        var list = document.getElementById('selectedSymbolsList');
        var count = document.getElementById('selectedCount');
        if (count) count.textContent = String(SELECTED_SYMBOLS.length);

        if (!list) return;

        if (!SELECTED_SYMBOLS.length) {
            list.innerHTML = '<div class="dd-empty dd-empty-small"><p>No symbols selected yet. Tap "View All Symbols" to choose.</p></div>';
            return;
        }

        var html = '';
        SELECTED_SYMBOLS.slice().sort().forEach(function (sym) {
            html += '<div class="dd-chip">' +
                    '<span>' + escapeHtml(cleanString(sym)) + '</span>' +
                    '<button type="button" class="dd-chip-remove" onclick="removeSelectedSymbol(\'' + escapeAttr(sym) + '\')" aria-label="Remove">&times;</button>' +
                    '</div>';
        });
        list.innerHTML = html;
    }

    function removeSelectedSymbol(sym) {
        SELECTED_SYMBOLS = SELECTED_SYMBOLS.filter(function (s) { return s !== sym; });
        renderSelectedSymbols();
    }

    // ==================== SYMBOLS PICKER MODAL ====================
    function openSymbolsModal() {
        MODAL_DRAFT_SELECTION = SELECTED_SYMBOLS.slice();

        var searchInput = document.getElementById('symbolsModalSearch');
        if (searchInput) searchInput.value = '';

        renderSymbolsModalList('');

        document.getElementById('symbolsPickerModal').classList.add('active');
        lockBodyScroll();
    }

    function closeSymbolsModal() {
        document.getElementById('symbolsPickerModal').classList.remove('active');
        unlockBodyScroll();
    }

    function filterSymbolsModal(query) {
        renderSymbolsModalList(query || '');
    }

    function renderSymbolsModalList(query) {
        var list = document.getElementById('symbolsModalList');
        var countEl = document.getElementById('symbolsModalCount');

        if (countEl) {
            countEl.textContent = MODAL_DRAFT_SELECTION.length + ' selected';
        }

        if (!ALL_BROKER_SYMBOLS.length) {
            list.innerHTML = '<div class="dd-symbols-modal-empty">No broker symbols available.</div>';
            return;
        }

        var q = (query || '').trim().toUpperCase();
        var filtered = ALL_BROKER_SYMBOLS.filter(function (s) {
            if (!q) return true;
            return String(s).toUpperCase().indexOf(q) !== -1;
        });

        if (!filtered.length) {
            list.innerHTML = '<div class="dd-symbols-modal-empty">No symbols match your search.</div>';
            return;
        }

        var html = '';
        filtered.forEach(function (sym) {
            var cleanSym = cleanString(sym);
            var checked = MODAL_DRAFT_SELECTION.indexOf(sym) !== -1;
            html += '<label class="dd-symbol-row' + (checked ? ' is-checked' : '') + '">' +
                        '<input type="checkbox" ' + (checked ? 'checked' : '') +
                            ' onchange="toggleModalSymbol(\'' + escapeAttr(sym) + '\', this.checked)">' +
                        '<span class="dd-symbol-row-name">' + escapeHtml(cleanSym) + '</span>' +
                    '</label>';
        });
        list.innerHTML = html;
    }

    function toggleModalSymbol(sym, isChecked) {
        if (isChecked) {
            if (MODAL_DRAFT_SELECTION.indexOf(sym) === -1) {
                MODAL_DRAFT_SELECTION.push(sym);
            }
        } else {
            MODAL_DRAFT_SELECTION = MODAL_DRAFT_SELECTION.filter(function (s) { return s !== sym; });
        }

        var countEl = document.getElementById('symbolsModalCount');
        if (countEl) {
            countEl.textContent = MODAL_DRAFT_SELECTION.length + ' selected';
        }
    }

    function clearAllSymbolChecks() {
        MODAL_DRAFT_SELECTION = [];
        var searchInput = document.getElementById('symbolsModalSearch');
        renderSymbolsModalList(searchInput ? searchInput.value : '');
    }

    function applySymbolsModal() {
        SELECTED_SYMBOLS = MODAL_DRAFT_SELECTION.slice();
        renderSelectedSymbols();
        closeSymbolsModal();
    }

    // ==================== SAVE SELECTED SYMBOLS ====================
    function saveSelectedSymbols() {
        var btn = document.getElementById('saveSelectedBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch('dev_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'save_selected_symbols=1&symbols=' + encodeURIComponent(JSON.stringify(SELECTED_SYMBOLS))
        })
        .then(parseJsonSafe)
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (data.success) {
                showDdAlert(data.message || 'Saved.', 'Success');
            } else {
                showDdAlert(data.message || 'Failed to save.', 'Error');
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            showDdAlert('Network error: ' + (err && err.message ? err.message : 'Unknown error'), 'Error');
        });
    }

    // ==================== TIMEFRAMES — GLOBAL (no symbol) ====================
    function renderSelectedTimeframes() {
        var list = document.getElementById('selectedTimeframesList');
        var count = document.getElementById('selectedTfCount');
        if (!list) return;

        // Ensure we have a clean array of strings
        SELECTED_TIMEFRAMES = cleanArray(SELECTED_TIMEFRAMES);

        if (count) count.textContent = String(SELECTED_TIMEFRAMES.length);

        if (!SELECTED_TIMEFRAMES.length) {
            list.innerHTML = '<div class="dd-empty dd-empty-small"><p>No timeframes selected yet. Tap "View All Timeframes".</p></div>';
            return;
        }

        var html = '';
        SELECTED_TIMEFRAMES.slice().sort().forEach(function (tf) {
            var cleanTf = cleanString(tf);
            html += '<div class="dd-chip">' +
                    '<span>' + escapeHtml(cleanTf) + '</span>' +
                    '<button type="button" class="dd-chip-remove" onclick="removeSelectedTf(\'' + escapeAttr(cleanTf) + '\')" aria-label="Remove">&times;</button>' +
                    '</div>';
        });
        list.innerHTML = html;
    }

    function removeSelectedTf(tf) {
        SELECTED_TIMEFRAMES = SELECTED_TIMEFRAMES.filter(function (t) { return cleanString(t) !== cleanString(tf); });
        renderSelectedTimeframes();
    }

    function saveSelectedTimeframes() {
        var btn = document.getElementById('saveSelectedTfBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        SELECTED_TIMEFRAMES = cleanArray(SELECTED_TIMEFRAMES);

        fetch('dev_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'save_selected_timeframes=1'
                 + '&timeframes=' + encodeURIComponent(JSON.stringify(SELECTED_TIMEFRAMES))
        })
        .then(parseJsonSafe)
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (data.success) {
                showDdAlert(data.message || 'Timeframes saved.', 'Success');
            } else {
                showDdAlert(data.message || 'Failed to save timeframes.', 'Error');
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            showDdAlert('Network error: ' + (err && err.message ? err.message : 'Unknown error'), 'Error');
        });
    }

    // ==================== TIMEFRAMES PICKER MODAL (GLOBAL) ====================
    function openTimeframesModal() {
        if (!AVAILABLE_TIMEFRAMES.length) {
            showDdAlert('No timeframes available.', 'Notice');
            return;
        }

        MODAL_TF_DRAFT = cleanArray(SELECTED_TIMEFRAMES);

        renderTimeframesModalList();

        document.getElementById('timeframesPickerModal').classList.add('active');
        lockBodyScroll();
    }

    function closeTimeframesModal() {
        document.getElementById('timeframesPickerModal').classList.remove('active');
        unlockBodyScroll();
    }

    function renderTimeframesModalList() {
        var list = document.getElementById('timeframesModalList');
        var countEl = document.getElementById('tfModalCount');

        MODAL_TF_DRAFT = cleanArray(MODAL_TF_DRAFT);

        if (countEl) countEl.textContent = MODAL_TF_DRAFT.length + ' selected';

        if (!AVAILABLE_TIMEFRAMES.length) {
            list.innerHTML = '<div class="dd-symbols-modal-empty">No timeframes available.</div>';
            return;
        }

        var html = '';
        AVAILABLE_TIMEFRAMES.slice().sort().forEach(function (tf) {
            var cleanTf = cleanString(tf);
            var checked = MODAL_TF_DRAFT.indexOf(cleanTf) !== -1;
            html += '<label class="dd-symbol-row' + (checked ? ' is-checked' : '') + '">' +
                        '<input type="checkbox" ' + (checked ? 'checked' : '') +
                            ' onchange="toggleModalTf(\'' + escapeAttr(cleanTf) + '\', this.checked)">' +
                        '<span class="dd-symbol-row-name">' + escapeHtml(cleanTf) + '</span>' +
                    '</label>';
        });
        list.innerHTML = html;
    }

    function toggleModalTf(tf, isChecked) {
        tf = cleanString(tf);
        if (isChecked) {
            if (MODAL_TF_DRAFT.indexOf(tf) === -1) MODAL_TF_DRAFT.push(tf);
        } else {
            MODAL_TF_DRAFT = MODAL_TF_DRAFT.filter(function (t) { return cleanString(t) !== tf; });
        }

        var countEl = document.getElementById('tfModalCount');
        if (countEl) countEl.textContent = MODAL_TF_DRAFT.length + ' selected';
    }

    function clearAllTfChecks() {
        MODAL_TF_DRAFT = [];
        renderTimeframesModalList();
    }

    function applyTimeframesModal() {
        SELECTED_TIMEFRAMES = cleanArray(MODAL_TF_DRAFT);
        renderSelectedTimeframes();
        closeTimeframesModal();
    }

    // ==================== CREATE PROGRAMME ====================
    function openCreateProgrammeModal() {
        document.getElementById('programmeNameInput').value = '';
        document.getElementById('programmeModalError').style.display = 'none';
        document.getElementById('programmeModalError').textContent = '';
        document.getElementById('createProgrammeModal').classList.add('active');
        lockBodyScroll();
        setTimeout(function () {
            var i = document.getElementById('programmeNameInput');
            if (i) i.focus();
        }, 100);
    }

    function closeCreateProgrammeModal() {
        document.getElementById('createProgrammeModal').classList.remove('active');
        unlockBodyScroll();
    }

    function submitCreateProgramme() {
        var nameInput = document.getElementById('programmeNameInput');
        var errBox    = document.getElementById('programmeModalError');
        var btn       = document.getElementById('createProgrammeBtn');
        var name      = (nameInput.value || '').trim();

        errBox.style.display = 'none';
        errBox.textContent = '';

        if (!name) {
            errBox.textContent = 'Programme name is required.';
            errBox.style.display = 'block';
            return;
        }

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch('dev_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'create_programme=1&program_name=' + encodeURIComponent(name)
        })
        .then(parseJsonSafe)
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (data.success) {
                appendProgrammeToList(data.programme);
                appendProgrammeToVisibility(data.programme);
                appendProgrammeToRequirements(data.programme);
                closeCreateProgrammeModal();
                showDdAlert(data.message || 'Programme created.', 'Success');
            } else {
                errBox.textContent = data.message || 'Failed to create programme.';
                errBox.style.display = 'block';
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            errBox.textContent = 'Network error: ' + (err && err.message ? err.message : 'Unknown error');
            errBox.style.display = 'block';
        });
    }

    function appendProgrammeToList(p) {
        var list = document.getElementById('programmesList');
        var empty = list.querySelector('.dd-empty');
        if (empty) empty.remove();

        var pid = parseInt(p.id, 10);

        var div = document.createElement('div');
        div.className = 'dd-programme-item';
        div.setAttribute('data-programme-id', String(pid));
        div.innerHTML =
            '<span class="dd-programme-name">' + escapeHtml(p.program_name) + '</span>' +
            '<button type="button" class="dd-btn-delete" id="deleteBtn-' + pid + '" onclick="deleteProgramme(' + pid + ')">Delete</button>' +
            '<button type="button" class="dd-btn-switch" onclick="goToProgrammeTraining(' + pid + ')">Switch</button>';
        list.insertBefore(div, list.firstChild);
    }

    function appendProgrammeToVisibility(p) {
        var list = document.getElementById('visibilityList');
        if (!list) return;

        var empty = list.querySelector('.dd-empty');
        if (empty) empty.remove();

        var div = document.createElement('div');
        div.className = 'dd-visibility-item';
        div.innerHTML =
            '<span class="dd-visibility-name">' + escapeHtml(p.program_name) + '</span>' +
            '<select class="dd-select dd-visibility-select" data-programme-id="' + parseInt(p.id, 10) + '">' +
                '<option value="1">Public</option>' +
                '<option value="0" selected>Private</option>' +
            '</select>';
        list.insertBefore(div, list.firstChild);
    }

    function appendProgrammeToRequirements(p) {
        var list = document.querySelector('.dd-req-list');
        if (!list) return;

        var empty = list.parentElement.querySelector('.dd-empty');
        if (empty) empty.remove();

        var pid = parseInt(p.id, 10);
        var cdMin = SERVER_CONTRACT_DURATION;
        var minMin = SERVER_MIN_BROKER_BALANCE.toFixed(2);

        var div = document.createElement('div');
        div.className = 'dd-req-item';
        div.id = 'reqItem-' + pid;
        div.innerHTML =
            '<button type="button" class="dd-req-toggle" onclick="toggleRequirement(' + pid + ')" aria-expanded="false">' +
                '<span class="dd-req-toggle-name">' + escapeHtml(p.program_name) + '</span>' +
                '<span class="dd-req-toggle-icon">▾</span>' +
            '</button>' +
            '<div class="dd-req-body" id="reqBody-' + pid + '" style="display:none;">' +
                '<div class="dd-req-grid">' +
                    reqField(pid, 'contract_duration', 'Contract Duration (days)', 'number', String(cdMin), String(cdMin), '1', 'Minimum: ' + cdMin + ' days') +
                    reqField(pid, 'developer_percentage', 'Developer Percentage (%)', 'number', '0', '0', '1', '') +
                    reqField(pid, 'investor_percentage', 'Investor Percentage (%)', 'number', '0', '0', '1', '') +
                    reqField(pid, 'minimum_investment_amount', 'Minimum Investment Amount', 'number', minMin, minMin, '0.01', 'Minimum: $' + minMin) +
                    reqField(pid, 'maximum_investment_amount', 'Maximum Investment Amount', 'number', '0.00', '0', '0.01', '') +
                '</div>' +
                '<button type="button" class="dd-btn-primary" id="saveReqBtn-' + pid + '" onclick="saveRequirements(' + pid + ')">Save Requirements</button>' +
            '</div>';
        list.insertBefore(div, list.firstChild);
    }

    function reqField(pid, field, label, type, valStr, minStr, step, hint) {
        return '<div class="dd-form-group">' +
            '<label class="dd-label">' + label + '</label>' +
            '<input type="' + type + '" min="' + minStr + '" step="' + step + '"' +
                ' class="dd-input dd-req-input"' +
                ' data-field="' + field + '"' +
                ' data-programme-id="' + pid + '"' +
                ' value="' + valStr + '">' +
            (hint ? '<div class="dd-field-hint">' + hint + '</div>' : '') +
        '</div>';
    }

    // ==================== SAFE JSON PARSER ====================
    function parseJsonSafe(response) {
        return response.text().then(function (text) {
            var trimmed = (text || '').trim();
            if (!trimmed) {
                throw new Error('Empty response from server.');
            }
            try {
                return JSON.parse(trimmed);
            } catch (e) {
                var snippet = trimmed.length > 300 ? trimmed.slice(0, 300) + '...' : trimmed;
                throw new Error('Server returned non-JSON: ' + snippet);
            }
        });
    }

    // ==================== DELETE PROGRAMME ====================
    function deleteProgramme(pid) {
        if (!pid || pid <= 0) return;

        showDdConfirm({
            title: 'Delete Programme',
            message: 'Are you sure you want to delete this programme? This action cannot be undone.',
            okText: 'Delete',
            cancelText: 'Cancel',
            danger: true
        }, function (confirmed) {
            if (!confirmed) return;

            var btn = document.getElementById('deleteBtn-' + pid);
            var originalText = btn ? btn.textContent : 'Delete';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Deleting...';
            }

            fetch('dev_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'delete_programme=1&programme_id=' + encodeURIComponent(pid)
            })
            .then(parseJsonSafe)
            .then(function (data) {
                if (data.success) {
                    removeProgrammeFromUi(pid);
                    showDdAlert(data.message || 'Programme deleted.', 'Success');
                } else {
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = originalText;
                    }
                    showDdAlert(data.message || 'Failed to delete programme.', 'Error');
                }
            })
            .catch(function (err) {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
                showDdAlert('Delete failed: ' + (err && err.message ? err.message : 'Unknown error'), 'Error');
            });
        });
    }

    function removeProgrammeFromUi(pid) {
        var item = document.querySelector('.dd-programme-item[data-programme-id="' + pid + '"]');
        if (item) item.remove();

        var visSel = document.querySelector('.dd-visibility-select[data-programme-id="' + pid + '"]');
        if (visSel) {
            var visItem = visSel.closest('.dd-visibility-item');
            if (visItem) visItem.remove();
        }

        var reqItem = document.getElementById('reqItem-' + pid);
        if (reqItem) reqItem.remove();

        var list = document.getElementById('programmesList');
        if (list && !list.querySelector('.dd-programme-item')) {
            list.innerHTML = '<div class="dd-empty"><span class="dd-empty-icon">—</span><p>You don\'t have any programme yet.</p></div>';
        }

        var visList = document.getElementById('visibilityList');
        if (visList && !visList.querySelector('.dd-visibility-item')) {
            visList.innerHTML = '<div class="dd-empty"><span class="dd-empty-icon">—</span><p>You don\'t have any programme yet.</p></div>';
        }

        var reqList = document.querySelector('.dd-req-list');
        if (reqList && !reqList.querySelector('.dd-req-item')) {
            var wrapper = reqList.parentElement;
            reqList.remove();
            var emptyDiv = document.createElement('div');
            emptyDiv.className = 'dd-empty';
            emptyDiv.innerHTML = '<span class="dd-empty-icon">—</span><p>You don\'t have any programme yet.</p>';
            wrapper.appendChild(emptyDiv);
        }
    }

    // ==================== REQUIREMENTS (accordion) ====================
    function toggleRequirement(pid) {
        var body = document.getElementById('reqBody-' + pid);
        var item = document.getElementById('reqItem-' + pid);
        if (!body || !item) return;

        var toggle = item.querySelector('.dd-req-toggle');
        var icon   = item.querySelector('.dd-req-toggle-icon');
        var isOpen = body.style.display !== 'none';

        if (isOpen) {
            body.style.display = 'none';
            item.classList.remove('open');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            if (icon) icon.textContent = '▾';
        } else {
            body.style.display = 'block';
            item.classList.add('open');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            if (icon) icon.textContent = '▴';
        }
    }

    // ==================== FLOOR ENFORCEMENT ====================
    function enforceFloorsOnInput(inp) {
        if (!inp) return;

        var field = inp.getAttribute('data-field');
        if (field === 'contract_duration') {
            var v = parseInt(inp.value, 10);
            if (isNaN(v) || v < SERVER_CONTRACT_DURATION) {
                inp.value = String(SERVER_CONTRACT_DURATION);
                inp.classList.add('dd-input-warn');
                setTimeout(function () { inp.classList.remove('dd-input-warn'); }, 900);
            }
        } else if (field === 'minimum_investment_amount') {
            var f = parseFloat(inp.value);
            if (isNaN(f) || f < SERVER_MIN_BROKER_BALANCE) {
                inp.value = SERVER_MIN_BROKER_BALANCE.toFixed(2);
                inp.classList.add('dd-input-warn');
                setTimeout(function () { inp.classList.remove('dd-input-warn'); }, 900);
            }
        }
    }

    document.addEventListener('blur', function (e) {
        var t = e.target;
        if (t && t.classList && t.classList.contains('dd-req-input')) {
            enforceFloorsOnInput(t);
        }
    }, true);

    function saveRequirements(pid) {
        var btn = document.getElementById('saveReqBtn-' + pid);
        if (!btn) return;

        var inputs = document.querySelectorAll('.dd-req-input[data-programme-id="' + pid + '"]');

        var invalid = null;
        inputs.forEach(function (inp) {
            var field = inp.getAttribute('data-field');
            if (field === 'contract_duration') {
                var v = parseInt(inp.value, 10);
                if (isNaN(v) || v < SERVER_CONTRACT_DURATION) {
                    inp.value = String(SERVER_CONTRACT_DURATION);
                    invalid = 'Contract Duration must be at least ' + SERVER_CONTRACT_DURATION + ' days.';
                }
            } else if (field === 'minimum_investment_amount') {
                var f = parseFloat(inp.value);
                if (isNaN(f) || f < SERVER_MIN_BROKER_BALANCE) {
                    inp.value = SERVER_MIN_BROKER_BALANCE.toFixed(2);
                    invalid = 'Minimum Investment Amount must be at least $' + SERVER_MIN_BROKER_BALANCE.toFixed(2) + '.';
                }
            }
        });

        if (invalid) {
            showDdAlert(invalid, 'Invalid Input');
            return;
        }

        var payload = { programme_id: pid };

        inputs.forEach(function (inp) {
            var field = inp.getAttribute('data-field');
            if (field) {
                payload[field] = inp.value;
            }
        });

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        var body = 'save_requirements=1';
        Object.keys(payload).forEach(function (k) {
            body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(payload[k]);
        });

        fetch('dev_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body
        })
        .then(parseJsonSafe)
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (data.success) {
                showDdAlert(data.message || 'Requirements saved.', 'Success');
            } else {
                if (data.field === 'contract_duration' && data.floor !== undefined) {
                    var el = document.getElementById('cd-' + pid);
                    if (el) {
                        el.value = String(data.floor);
                        el.classList.add('dd-input-warn');
                        setTimeout(function () { el.classList.remove('dd-input-warn'); }, 900);
                    }
                } else if (data.field === 'minimum_investment_amount' && data.floor !== undefined) {
                    var el2 = document.getElementById('min-' + pid);
                    if (el2) {
                        el2.value = Number(data.floor).toFixed(2);
                        el2.classList.add('dd-input-warn');
                        setTimeout(function () { el2.classList.remove('dd-input-warn'); }, 900);
                    }
                }
                showDdAlert(data.message || 'Failed to save requirements.', 'Error');
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            showDdAlert('Network error: ' + (err && err.message ? err.message : 'Unknown error'), 'Error');
        });
    }

    // ==================== SAVE VISIBILITY ====================
    function saveVisibility() {
        var btn = document.getElementById('saveVisibilityBtn');
        if (!btn) return;

        var selects = document.querySelectorAll('.dd-visibility-select');
        if (!selects.length) return;

        var map = {};
        selects.forEach(function (sel) {
            var pid = sel.getAttribute('data-programme-id');
            if (pid) {
                map[pid] = sel.value === '1' ? 1 : 0;
            }
        });

        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch('dev_dashboard.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'save_visibility=1&visibility_map=' + encodeURIComponent(JSON.stringify(map))
        })
        .then(parseJsonSafe)
        .then(function (data) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (data.success) {
                showDdAlert(data.message || 'Visibility saved.', 'Success');
            } else {
                showDdAlert(data.message || 'Failed to save visibility.', 'Error');
            }
        })
        .catch(function (err) {
            btn.disabled = false;
            btn.textContent = originalText;
            showDdAlert('Network error: ' + (err && err.message ? err.message : 'Unknown error'), 'Error');
        });
    }

    // ==================== NAVIGATION ====================
    function goToProgrammeTraining(programmeId) {
        window.location.href = 'programme_training.php?id=' + encodeURIComponent(programmeId);
    }

    // ==================== ESC KEY HANDLERS ====================
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var sm = document.getElementById('symbolsPickerModal');
            if (sm && sm.classList.contains('active')) {
                closeSymbolsModal();
                return;
            }
            var tm = document.getElementById('timeframesPickerModal');
            if (tm && tm.classList.contains('active')) {
                closeTimeframesModal();
                return;
            }
            var cm = document.getElementById('createProgrammeModal');
            if (cm && cm.classList.contains('active')) {
                closeCreateProgrammeModal();
                return;
            }
            var am = document.getElementById('ddAlertModal');
            if (am && am.classList.contains('active')) {
                closeDdAlert();
            }
        }
    });

    // ==================== INIT ====================
    document.addEventListener('DOMContentLoaded', function () {
        // Clean arrays before rendering
        SELECTED_SYMBOLS = cleanArray(SELECTED_SYMBOLS);
        SELECTED_TIMEFRAMES = cleanArray(SELECTED_TIMEFRAMES);
        AVAILABLE_TIMEFRAMES = cleanArray(AVAILABLE_TIMEFRAMES);

        renderSelectedSymbols();
        renderSelectedTimeframes();

        document.querySelectorAll('.dd-req-input').forEach(function (inp) {
            enforceFloorsOnInput(inp);
        });
    });

    // ==================== SIDEBAR NAVIGATION ====================
    (function () {
        var sidebar = document.getElementById('sidebarNav');
        var desktopToggle = document.getElementById('sidebarToggle');
        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var overlay = document.getElementById('sidebarOverlay');
        var body = document.body;

        if (!sidebar) return;

        function isMobile() { return window.innerWidth <= 768; }
        function openMobileSidebar() {
            sidebar.classList.add('expanded');
            overlay.classList.add('active');
            body.style.overflow = 'hidden';
        }
        function closeMobileSidebar() {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
        }
        function toggleDesktopSidebar() {
            var isExpanded = sidebar.classList.contains('expanded');
            if (isExpanded) {
                sidebar.classList.remove('expanded');
                body.classList.remove('sidebar-expanded-desktop');
            } else {
                sidebar.classList.add('expanded');
                body.classList.add('sidebar-expanded-desktop');
            }
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (isMobile()) openMobileSidebar();
            });
        }
        if (desktopToggle) {
            desktopToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!isMobile()) toggleDesktopSidebar();
            });
        }
        if (overlay) {
            overlay.addEventListener('click', function () {
                if (isMobile()) closeMobileSidebar();
            });
        }

        document.querySelectorAll('.sidebar-menu-item').forEach(function (item) {
            item.addEventListener('click', function () {
                if (isMobile()) setTimeout(closeMobileSidebar, 150);
            });
        });

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (isMobile()) {
                    body.classList.remove('sidebar-expanded-desktop');
                    sidebar.classList.remove('expanded');
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                } else {
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                    if (sidebar.classList.contains('expanded')) {
                        body.classList.add('sidebar-expanded-desktop');
                    }
                }
            }, 150);
        });

        if (isMobile()) {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
            body.classList.remove('sidebar-expanded-desktop');
        } else {
            sidebar.classList.remove('expanded');
            body.classList.remove('sidebar-expanded-desktop');
        }
    })();

    // ==================== EXPORTS ====================
    window.switchDdSubTab          = switchDdSubTab;

    // Symbols
    window.openSymbolsModal        = openSymbolsModal;
    window.closeSymbolsModal       = closeSymbolsModal;
    window.filterSymbolsModal      = filterSymbolsModal;
    window.toggleModalSymbol       = toggleModalSymbol;
    window.clearAllSymbolChecks    = clearAllSymbolChecks;
    window.applySymbolsModal       = applySymbolsModal;
    window.removeSelectedSymbol    = removeSelectedSymbol;
    window.saveSelectedSymbols     = saveSelectedSymbols;

    // Timeframes — GLOBAL
    window.openTimeframesModal     = openTimeframesModal;
    window.closeTimeframesModal    = closeTimeframesModal;
    window.toggleModalTf           = toggleModalTf;
    window.clearAllTfChecks        = clearAllTfChecks;
    window.applyTimeframesModal    = applyTimeframesModal;
    window.removeSelectedTf        = removeSelectedTf;
    window.saveSelectedTimeframes  = saveSelectedTimeframes;

    // Programmes
    window.openCreateProgrammeModal  = openCreateProgrammeModal;
    window.closeCreateProgrammeModal = closeCreateProgrammeModal;
    window.submitCreateProgramme     = submitCreateProgramme;
    window.goToProgrammeTraining     = goToProgrammeTraining;
    window.deleteProgramme           = deleteProgramme;

    // Alerts / Confirm
    window.closeDdAlert              = closeDdAlert;
    window.showDdAlert               = showDdAlert;
    window.showDdConfirm             = showDdConfirm;

    // Visibility / Requirements
    window.saveVisibility            = saveVisibility;
    window.toggleRequirement         = toggleRequirement;
    window.saveRequirements          = saveRequirements;
</script>

</body>
</html>