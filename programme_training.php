<?php
// programme_training.php — Candlestick chart viewer for a programme
session_start();

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) { die("Database connection failed."); }

if (!isset($_SESSION['user_email'])) { header("Location: index.php"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php"); exit; }

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$devStmt->execute([$email]);
$developer = $devStmt->fetch(PDO::FETCH_ASSOC);
if (!$developer) { header("Location: dev_app.php"); exit; }

$programmeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$programme = null;
if ($programmeId > 0) {
    $pStmt = $pdo->prepare("SELECT id, program_name, visibility, advertisement FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $pStmt->execute([$programmeId, $userId]);
    $programme = $pStmt->fetch(PDO::FETCH_ASSOC);
}
$programmeName = $programme ? $programme['program_name'] : 'Programme';

$selectedSymbols = [];
try {
    $sStmt = $pdo->prepare("
        SELECT symbol FROM broker_symbols
        WHERE userid = ? AND symbol_selected = 1 AND symbol <> '__GLOBAL_TF__'
        ORDER BY symbol ASC
    ");
    $sStmt->execute([$userId]);
    while ($row = $sStmt->fetch(PDO::FETCH_ASSOC)) $selectedSymbols[] = $row['symbol'];
} catch (PDOException $e) { $selectedSymbols = []; }

function parseList($str) {
    if (!is_string($str) || trim($str) === '') return [];
    $str = trim($str);
    if ($str[0] === '[') {
        $decoded = json_decode($str, true);
        if (is_array($decoded)) {
            $out = [];
            foreach ($decoded as $v) { $v = trim((string)$v); if ($v !== '') $out[] = $v; }
            return array_values(array_unique($out));
        }
    }
    $parts = array_map('trim', explode(',', $str));
    $parts = array_filter($parts, function ($v) { return $v !== ''; });
    return array_values(array_unique($parts));
}
$selectedTimeframes = [];
try {
    $tStmt = $pdo->prepare("
        SELECT selected_timeframes FROM broker_symbols
        WHERE userid = ? AND symbol_selected = 1
          AND selected_timeframes IS NOT NULL
          AND selected_timeframes <> '' AND selected_timeframes <> '[]'
        ORDER BY id DESC LIMIT 1
    ");
    $tStmt->execute([$userId]);
    $tRow = $tStmt->fetch(PDO::FETCH_ASSOC);
    if ($tRow && !empty($tRow['selected_timeframes'])) $selectedTimeframes = parseList($tRow['selected_timeframes']);
} catch (PDOException $e) { $selectedTimeframes = []; }

// ==================== CONFIGURATION ROWS ====================
$programmeConfig = [];
try {
    if ($programmeId > 0) {
        $cStmt = $pdo->prepare("
            SELECT id, candleid, root, reference_id, re_referenced_root,
                   candle_name, price_level, timeframe, candle_type,
                   candle_position, candle_search, operator,
                   drawing_id, drawing_tools, from_root_price_level,
                   drawing_destination, to_reference_price_level
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ?
            ORDER BY id ASC
        ");
        $cStmt->execute([$userId, $programmeId]);
        while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
            $programmeConfig[] = [
                'id'                       => (int)$r['id'],
                'candleid'                 => $r['candleid'] !== null ? (int)$r['candleid'] : null,
                'root'                     => (int)$r['root'],
                'reference_id'             => $r['reference_id'] !== null ? (int)$r['reference_id'] : null,
                're_referenced_root'       => (int)$r['re_referenced_root'],
                'candle_name'              => $r['candle_name'],
                'price_level'              => $r['price_level'],
                'timeframe'                => $r['timeframe'],
                'candle_type'              => $r['candle_type'],
                'candle_position'          => $r['candle_position'],
                'candle_search'            => $r['candle_search'],
                'operator'                 => $r['operator'],
                'drawing_id'               => $r['drawing_id'] !== null ? (int)$r['drawing_id'] : null,
                'drawing_tools'            => $r['drawing_tools'],
                'from_root_price_level'    => $r['from_root_price_level'],
                'drawing_destination'      => $r['drawing_destination'],
                'to_reference_price_level' => $r['to_reference_price_level'],
            ];
        }
    }
} catch (PDOException $e) { $programmeConfig = []; }

// ==================== ROOT TREES ====================
// Structure produced here must mirror the client-side rebuild exactly:
//   root.refs[] = plain reference objects, each with a `rerefStack` array
//   of { author, referenced } pairs. Re-ref rows MUST NOT be pushed as
//   standalone refs — they must be grouped into pairs and attached to the
//   LAST plain ref of the same anchor.
$programmeRootTrees = [];
try {
    if ($programmeId > 0 && count($programmeConfig)) {
        $roots = [];
        foreach ($programmeConfig as $row) {
            if ((int)$row['root'] === 1) {
                $roots[$row['id']] = [
                    'id'          => $row['id'],
                    'candle_name' => $row['candle_name'],
                    'price_level' => $row['price_level'],
                    'timeframe'   => $row['timeframe'],
                    'candle_type' => $row['candle_type'],
                    'position'    => $row['candle_position'],
                    'operator'    => $row['operator'],
                    'refs'        => [],
                    'drawings'    => [],
                ];
            }
        }

        // Bucket re-ref rows per anchor so we can pair them up.
        $pendingReref = [];   // anchor => [row, row, ...]

        foreach ($programmeConfig as $row) {
            if ((int)$row['root'] === 1) continue;
            if ($row['drawing_id'] !== null) continue;
            $anchor = (int)$row['candleid'];
            if (!isset($roots[$anchor])) continue;

            $isReRef = ((int)$row['re_referenced_root'] === 1);

            if ($isReRef) {
                if (!isset($pendingReref[$anchor])) $pendingReref[$anchor] = [];
                $pendingReref[$anchor][] = [
                    'id'            => $row['id'],
                    'candle_name'   => $row['candle_name'],
                    'price_level'   => $row['price_level'],
                    'timeframe'     => $row['timeframe'],
                    'candle_type'   => $row['candle_type'],
                    'position'      => $row['candle_position'],
                    'candle_search' => $row['candle_search'],
                    'operator'      => $row['operator'],
                ];
            } else {
                $roots[$anchor]['refs'][] = [
                    'id'                 => $row['id'],
                    'candle_name'        => $row['candle_name'],
                    'price_level'        => $row['price_level'],
                    'timeframe'          => $row['timeframe'],
                    'candle_type'        => $row['candle_type'],
                    'position'           => $row['candle_position'],
                    'candle_search'      => $row['candle_search'],
                    'operator'           => $row['operator'],
                    're_referenced_root' => 0,
                    'reference_id'       => $row['reference_id'],
                    'rerefStack'         => [],
                ];
            }
        }

        // Pair re-ref rows and attach to the LAST plain ref on each anchor.
        foreach ($pendingReref as $anchor => $rows) {
            $refCount = count($roots[$anchor]['refs']);
            if ($refCount === 0) continue;
            $parentIdx = $refCount - 1;

            for ($i = 0; $i + 1 < count($rows); $i += 2) {
                $author     = $rows[$i];
                $referenced = $rows[$i + 1];
                $roots[$anchor]['refs'][$parentIdx]['rerefStack'][] = [
                    'author'     => $author,
                    'referenced' => $referenced,
                ];
            }
        }

        foreach ($programmeConfig as $row) {
            if ($row['drawing_id'] === null) continue;
            $anchor = (int)$row['candleid'];
            if (isset($roots[$anchor])) {
                $roots[$anchor]['drawings'][] = [
                    'id'                       => $row['id'],
                    'drawing_id'               => (int)$row['drawing_id'],
                    'drawing_tools'            => $row['drawing_tools'],
                    'from_root_price_level'    => $row['from_root_price_level'],
                    'drawing_destination'      => $row['drawing_destination'],
                    'to_reference_price_level' => $row['to_reference_price_level'],
                ];
            }
        }

        $programmeRootTrees = array_values($roots);
    }
} catch (PDOException $e) { $programmeRootTrees = []; }

// ==================== AJAX: COUNT CANDLES ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['count_candles'])) {
    header('Content-Type: application/json');
    $symbol    = trim((string)($_POST['symbol'] ?? ''));
    $timeframe = trim((string)($_POST['timeframe'] ?? ''));
    if ($symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'Symbol and timeframe are required.','total'=>0]); exit;
    }
    try {
        $q = $pdo->prepare("SELECT COUNT(*) AS total FROM candle_records WHERE userid=? AND symbol=? AND timeframe=?");
        $q->execute([$userId, $symbol, $timeframe]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'symbol'=>$symbol,'timeframe'=>$timeframe,'total'=>(int)($row['total'] ?? 0)]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage(),'total'=>0]);
    }
    exit;
}

// ==================== AJAX: FETCH CANDLES ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles'])) {
    header('Content-Type: application/json');
    $symbol    = trim((string)($_POST['symbol'] ?? ''));
    $timeframe = trim((string)($_POST['timeframe'] ?? ''));
    $limit     = isset($_POST['limit'])  ? max(1, min(2000, (int)$_POST['limit'])) : 200;
    $before    = trim((string)($_POST['before'] ?? ''));

    if ($symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'Symbol and timeframe are required.','candles'=>[],'has_more'=>false]); exit;
    }

    try {
        if ($before !== '') {
            $q = $pdo->prepare("
                SELECT candle_time, open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center
                FROM candle_records
                WHERE userid=? AND symbol=? AND timeframe=? AND candle_time < ?
                ORDER BY candle_time DESC LIMIT $limit
            ");
            $q->execute([$userId, $symbol, $timeframe, $before]);
        } else {
            $q = $pdo->prepare("
                SELECT candle_time, open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center
                FROM candle_records
                WHERE userid=? AND symbol=? AND timeframe=?
                ORDER BY candle_time DESC LIMIT $limit
            ");
            $q->execute([$userId, $symbol, $timeframe]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $rowsAsc = array_reverse($rows);

        $candles = [];
        foreach ($rowsAsc as $r) {
            $candles[] = [
                'time'                => $r['candle_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center'] : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center'] : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center'] : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center'] : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
            ];
        }

        echo json_encode([
            'success'   => true,
            'symbol'    => $symbol,
            'timeframe' => $timeframe,
            'count'     => count($candles),
            'has_more'  => count($rows) === $limit,
            'oldest'    => count($candles) ? $candles[0]['time'] : null,
            'newest'    => count($candles) ? $candles[count($candles)-1]['time'] : null,
            'candles'   => $candles,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage(),'candles'=>[],'has_more'=>false]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($programmeName) ?> - Training</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
<?php include 'dev_dashboard_style.php'; ?>
<?php include 'programme_training_style.php'; ?>
<style>
    .pt-chart-fullscreen { position: fixed; inset: 0; overflow: hidden; touch-action: none; user-select: none; -webkit-user-select: none; }
    .pt-chart-canvas { display: block; cursor: crosshair; }
    .pt-chart-canvas.pt-grabbing { cursor: grabbing; }
    .pt-scale-bar { position: absolute; top: 0; right: 0; width: 18px; height: 100%; cursor: ns-resize; z-index: 6; background: transparent; }
    .pt-scale-bar::after { content: ''; position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
        width: 3px; height: 60px; border-radius: 2px; background: rgba(128,128,128,0.45); }
    .pt-scale-bar.pt-active::after { background: rgba(128,128,128,0.9); }
    .pt-bg-loading { position: absolute; left: 12px; bottom: 12px; z-index: 7;
        background: rgba(0,0,0,0.55); color: #fff;
        font: 11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        padding: 4px 8px; border-radius: 6px; display: none; }
    .pt-bg-loading.pt-warn { background: rgba(180, 60, 60, 0.85); }
    .pt-jump-latest { position: absolute; top: 50%; right: 34px; transform: translateY(-50%);
        width: 40px; height: 40px; border-radius: 50%;
        border: 1px solid rgba(128,128,128,0.35);
        background: rgba(40,40,40,0.55); color: #fff;
        display: none; align-items: center; justify-content: center;
        cursor: pointer; z-index: 8;
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
    .pt-jump-latest:hover { background: rgba(60,60,60,0.85); }
    .pt-jump-latest:active { transform: translateY(-50%) scale(0.94); }
    .pt-jump-latest svg { width: 20px; height: 20px; display: block; }
    body.dark-mode .pt-jump-latest { background: rgba(230,230,230,0.75); color: #222; border-color: rgba(0,0,0,0.25); }
    body.dark-mode .pt-jump-latest:hover { background: rgba(255,255,255,0.95); }
    .pt-candles-modal { position: fixed; inset: 0; z-index: 9999; display: none;
        align-items: center; justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
    .pt-candles-modal.active { display: flex; }
    .pt-candles-modal-box { width: 92%; max-width: 400px; background: #fff; color: #222;
        border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        padding: 20px 20px 16px;
        font: 14px system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    body.dark-mode .pt-candles-modal-box { background: #1f1f1f; color: #eee; }
    .pt-candles-title { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
    .pt-candles-sub { font-size: 12px; opacity: 0.75; margin-bottom: 12px; line-height: 1.4; }
    .pt-candles-input { width: 100%; box-sizing: border-box; padding: 10px 12px; font-size: 15px;
        border-radius: 8px; border: 1px solid #ccc; outline: none;
        background: #fafafa; color: inherit; }
    body.dark-mode .pt-candles-input { background: #2b2b2b; border-color: #444; }
    .pt-candles-available { font-size: 12px; margin-top: 8px; color: #2e8b57; font-weight: 600; }
    body.dark-mode .pt-candles-available { color: #5dd39e; }
    .pt-candles-info { font-size: 12px; margin-top: 6px; min-height: 16px; color: #b00020; white-space: pre-line; }
    body.dark-mode .pt-candles-info { color: #ff8080; }
    .pt-candles-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
    .pt-candles-btn { padding: 8px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;
        border: 1px solid transparent; }
    .pt-candles-btn.primary { background: #2e8b57; color: #fff; }
    .pt-candles-btn.primary:hover { background: #27794b; }
    .pt-candles-btn.ghost { background: transparent; color: inherit; border-color: #bbb; }
    body.dark-mode .pt-candles-btn.ghost { border-color: #555; }
    .pt-candles-btn.ghost:hover { background: rgba(128,128,128,0.15); }
    .pt-cfg-btn { pointer-events: auto; display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 14px; background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        color: var(--text, #222);
        font-family: inherit; font-size: 0.85rem; font-weight: 700; cursor: pointer;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        white-space: nowrap; }
    .pt-cfg-btn i { color: var(--accent, #2e8b57); font-size: 0.95rem; }
    .pt-cfg-btn:hover { border-color: var(--accent, #2e8b57); box-shadow: 0 3px 14px rgba(0,0,0,0.1); }
    body.dark-mode .pt-cfg-btn { background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333); color: var(--text, #eee); }
    @media (max-width: 600px) {
        .pt-cfg-btn { padding: 8px 11px; font-size: 0.8rem; }
        .pt-cfg-btn span { display: none; } }
    #ptDrawCanvas { position: absolute; inset: 0; pointer-events: none; z-index: 5; }

    /* === Double-click candle detail modal === */
    .pt-candle-detail-modal {
        position: fixed; inset: 0; z-index: 10001; display: none;
        align-items: center; justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
        padding: 20px; box-sizing: border-box;
    }
    .pt-candle-detail-modal.active { display: flex; }
    .pt-candle-detail-box {
        width: 100%; max-width: 460px;
        background: var(--bg-card, #fff); color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.35);
        overflow: hidden;
        font-family: inherit;
    }
    .pt-candle-detail-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px 12px;
        border-bottom: 1px solid var(--border-color, #e0e0e0);
        background: rgba(46,139,87,0.06);
    }
    .pt-candle-detail-title { margin: 0; font-size: 1rem; font-weight: 700; color: var(--accent, #2e8b57); }
    .pt-candle-detail-sub { margin: 3px 0 0; font-size: 0.78rem; color: var(--text-muted, #888); }
    .pt-candle-detail-close {
        border: 1px solid transparent; background: transparent;
        color: var(--text-muted, #888);
        width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .pt-candle-detail-close:hover { background: rgba(128,128,128,0.15); color: var(--text, #222); }
    .pt-candle-detail-body { padding: 14px 18px 18px; }
    .pt-candle-detail-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 12px; padding: 7px 0;
        border-bottom: 1px dashed var(--border-color, #e0e0e0);
        font-size: 0.85rem;
    }
    .pt-candle-detail-row:last-child { border-bottom: none; }
    .pt-candle-detail-label {
        color: var(--text-muted, #888);
        font-weight: 700; font-size: 0.72rem;
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    .pt-candle-detail-value {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        color: var(--text, #222);
    }
    body.dark-mode .pt-candle-detail-box { background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333); color: var(--text, #eee); }
    body.dark-mode .pt-candle-detail-head { background: rgba(46,139,87,0.12);
        border-bottom-color: var(--border-color, #333); }
    body.dark-mode .pt-candle-detail-row { border-bottom-color: var(--border-color, #333); }
    body.dark-mode .pt-candle-detail-value { color: var(--text, #eee); }
</style>
</head>
<body class="pt-fullbody <?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div class="pt-topbar">
        <button type="button" class="pt-topbar-btn" id="ptSymbolBtn" onclick="ptOpenSymbolModal()">
            <span class="pt-topbar-symbol" id="ptSymbolLabel">Symbol</span>
            <i class="fa-solid fa-chevron-down pt-topbar-caret"></i>
        </button>
        <div class="pt-tf-strip" id="ptTfStrip"></div>

        <button type="button" class="pt-cfg-btn" id="ptExploreBtn" title="Explore candles">
            <i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>Explore</span>
        </button>

        <button type="button" class="pt-cfg-btn" id="ptCfgBtn" title="Programme Configuration">
            <i class="fa-solid fa-sliders"></i>
            <span>Configuration</span>
        </button>
    </div>

    <div class="pt-chart-fullscreen" id="ptChartWrap">
        <canvas id="ptChartCanvas" class="pt-chart-canvas"></canvas>
        <canvas id="ptDrawCanvas"></canvas>

        <div class="pt-scale-bar" id="ptScaleBar" title="Drag to change candle height"></div>

        <button type="button" class="pt-jump-latest" id="ptJumpLatest" title="Jump to latest" onclick="ptJumpToLatest()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="12" x2="18" y2="12"></line>
                <polyline points="12 6 18 12 12 18"></polyline>
            </svg>
        </button>

        <div id="ptChartEmpty" class="pt-chart-empty" style="display:none;">
            <span class="pt-empty-icon">—</span>
            <p>No candle records found for this symbol / timeframe.</p>
        </div>

        <div id="ptChartLoading" class="pt-chart-loading" style="display:none;">
            <div class="pt-loading-spinner"></div>
            <p>Loading candles…</p>
        </div>

        <div id="ptBgLoading" class="pt-bg-loading">Loading older candles…</div>

        <div class="pt-floating-readout" id="ptReadout">
            <div class="pt-fr-row">
                <span class="pt-fr-time" id="ptReadoutTime">—</span>
            </div>
            <div class="pt-fr-grid">
                <div class="pt-fr-item"><span class="pt-fr-label">O</span><span class="pt-fr-value" id="ptReadoutOpen">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">H</span><span class="pt-fr-value" id="ptReadoutHigh">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">L</span><span class="pt-fr-value" id="ptReadoutLow">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">C</span><span class="pt-fr-value" id="ptReadoutClose">—</span></div>
            </div>
        </div>
    </div>

    <div id="ptSymbolModal" class="pt-modal">
        <div class="pt-modal-content">
            <h2 class="pt-modal-title">Select Symbol</h2>
            <div class="pt-modal-search-wrap">
                <input type="text" id="ptSymbolSearch" class="pt-modal-input" placeholder="Search symbol..."
                       autocomplete="off" oninput="ptFilterSymbolModal(this.value)">
            </div>
            <div class="pt-modal-meta"><span id="ptSymbolModalCount">0 symbols</span></div>
            <div id="ptSymbolModalList" class="pt-modal-list"></div>
            <div class="pt-modal-actions">
                <button type="button" class="pt-btn-ghost" onclick="ptCloseSymbolModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="ptCandlesModal" class="pt-candles-modal">
        <div class="pt-candles-modal-box">
            <div class="pt-candles-title">Explore Candles</div>
            <div class="pt-candles-sub" id="ptCandlesSub">Enter the number of candles to display.</div>
            <input type="number" id="ptCandlesInput" class="pt-candles-input"
                   min="1" step="1" placeholder="e.g. 1500" autocomplete="off">
            <div class="pt-candles-available" id="ptCandlesAvailable">Available Candle: —</div>
            <div class="pt-candles-info" id="ptCandlesInfo"></div>
            <div class="pt-candles-actions">
                <button type="button" class="pt-candles-btn ghost" id="ptCandlesCancel">Cancel</button>
                <button type="button" class="pt-candles-btn primary" id="ptCandlesConfirm">Load</button>
            </div>
        </div>
    </div>

    <div id="ptCandleDetailModal" class="pt-candle-detail-modal">
        <div class="pt-candle-detail-box">
            <div class="pt-candle-detail-head">
                <div>
                    <h3 class="pt-candle-detail-title" id="ptCandleDetailTitle">Candle Details</h3>
                    <p class="pt-candle-detail-sub" id="ptCandleDetailSub">—</p>
                </div>
                <button type="button" class="pt-candle-detail-close" id="ptCandleDetailClose" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="pt-candle-detail-body" id="ptCandleDetailBody"></div>
        </div>
    </div>

    <?php include 'programme_configuration.php'; ?>

<script>
    // ==================== SERVER DATA ====================
    var PT_ALL_SYMBOLS       = <?= json_encode(array_values($selectedSymbols)) ?>;
    var PT_ALL_TIMEFRAMES    = <?= json_encode(array_values($selectedTimeframes)) ?>;
    var PT_CURRENT_SYMBOL    = '';
    var PT_CURRENT_TIMEFRAME = '';

    var PT_PROGRAMME_ID      = <?= (int)$programmeId ?>;
    var PT_PROGRAMME_NAME    = <?= json_encode($programmeName) ?>;

    var PT_CONFIG_ROWS       = <?= json_encode($programmeConfig) ?>;
    var PT_ROOT_TREES        = <?= json_encode($programmeRootTrees) ?>;

    var PT_DEFAULT_AMOUNT    = 2000;

    // ==================== CHART STATE ====================
    var PT_CANDLES     = [];
    var PT_CANVAS      = null;
    var PT_CTX         = null;
    var PT_DRAW_CANVAS = null;
    var PT_DRAW_CTX    = null;
    var PT_HOVER_INDEX = -1;
    var PT_CURSOR_Y    = null;

    var PT_LOAD_TOKEN         = 0;
    var PT_IS_LOADING_OLDER   = false;
    var PT_HAS_MORE_OLDER     = true;
    var PT_INITIAL_LOADED     = false;
    var PT_REQUESTED_AMOUNT   = PT_DEFAULT_AMOUNT;
    var PT_CONSECUTIVE_ERRORS = 0;
    var PT_MAX_CONSECUTIVE_ERRORS = 4;

    var PT_VIEW = {
        candleWidth:    9,
        candleSpacing:  5,
        offsetX:        0,
        offsetY:        0,
        priceScale:     1.0,
        minCandleWidth: 0.3,
        maxCandleWidth: 40
    };

    var PT_PAGE_SIZE      = 500;
    var PT_PADDING_LEFT   = 64;
    var PT_PADDING_RIGHT  = 24;
    var PT_PADDING_TOP    = 64;
    var PT_PADDING_BOTTOM = 34;
    var PT_VIRTUAL_PAD    = 2000;

    var PT_FOLLOW_LATEST  = true;

    var PT_DRAW_SCHEDULED = false;
    function ptScheduleDraw() {
        if (PT_DRAW_SCHEDULED) return;
        PT_DRAW_SCHEDULED = true;
        requestAnimationFrame(function () {
            PT_DRAW_SCHEDULED = false;
            ptDrawChart();
        });
    }

    // ==================== HELPERS ====================
    function ptEscapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function ptFormatPrice(v) {
        if (v == null || isNaN(v)) return '—';
        var n = Number(v); var abs = Math.abs(n);
        var decimals = abs >= 1000 ? 2 : (abs >= 1 ? 4 : 6);
        return n.toFixed(decimals);
    }
    function ptFormatTime(ts) { if (!ts) return '—'; return String(ts).replace('T', ' ').slice(0, 16); }
    function ptStep()     { return PT_VIEW.candleWidth + PT_VIEW.candleSpacing; }
    function ptContentW() { return PT_CANDLES.length * ptStep(); }

    function ptLabelStyles() {
        var isDark = document.body.classList.contains('dark-mode');
        return { bg: isDark ? '#2a2a2a' : '#1e1e1e', bgText: '#f2f2f2', border: isDark ? '#444' : '#000' };
    }
    function ptRoundRect(ctx, x, y, w, h, r) {
        if (w < 2 * r) r = w / 2; if (h < 2 * r) r = h / 2;
        ctx.beginPath(); ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y,     x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x,     y + h, r);
        ctx.arcTo(x,     y + h, x,     y,     r);
        ctx.arcTo(x,     y,     x + w, y,     r);
        ctx.closePath();
    }

    // ==================== INIT ====================
    document.addEventListener('DOMContentLoaded', function () {
        PT_CANVAS      = document.getElementById('ptChartCanvas');
        PT_DRAW_CANVAS = document.getElementById('ptDrawCanvas');
        if (PT_CANVAS) ptAttachCanvasEvents();
        if (PT_DRAW_CANVAS) PT_DRAW_CTX = PT_DRAW_CANVAS.getContext('2d');

        ptAttachScaleBarEvents();
        ptAttachCandlesModalEvents();
        ptAttachCandleDetailModalEvents();

        var exploreBtn = document.getElementById('ptExploreBtn');
        if (exploreBtn) {
            exploreBtn.addEventListener('click', function () {
                ptOpenCandlesModal(PT_CURRENT_SYMBOL, PT_CURRENT_TIMEFRAME);
            });
        }

        var cfgBtn = document.getElementById('ptCfgBtn');
        if (cfgBtn) {
            cfgBtn.addEventListener('click', function () {
                if (typeof window.pcSetTimeframes === 'function') {
                    window.pcSetTimeframes(PT_ALL_TIMEFRAMES, PT_CURRENT_TIMEFRAME);
                }
                if (typeof window.pcSetRootTrees === 'function') {
                    window.pcSetRootTrees(PT_ROOT_TREES);
                }
                if (typeof window.pcOpenConfiguration === 'function') {
                    window.pcOpenConfiguration({
                        programmeId:      PT_PROGRAMME_ID,
                        programmeName:    PT_PROGRAMME_NAME,
                        timeframes:       PT_ALL_TIMEFRAMES,
                        currentTimeframe: PT_CURRENT_TIMEFRAME,
                        rootTrees:        PT_ROOT_TREES
                    });
                }
            });
        }

        window.addEventListener('pc:timeframeChange', function (ev) {
            var tf = ev && ev.detail && ev.detail.timeframe;
            if (!tf || tf === PT_CURRENT_TIMEFRAME) return;
            PT_CURRENT_TIMEFRAME = tf;
            ptRenderTimeframeStrip();
            ptLoadChart();
        });

        window.addEventListener('pc:save', function (ev) {
            if (!ev || !ev.detail) return;
            ptSaveProgrammeConfiguration(ev.detail);
        });

        var jumpBtn = document.getElementById('ptJumpLatest');
        if (jumpBtn) {
            jumpBtn.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            jumpBtn.addEventListener('touchstart', function (e) { e.stopPropagation(); }, { passive: true });
        }

        document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });

        PT_CURRENT_SYMBOL    = PT_ALL_SYMBOLS.length    ? PT_ALL_SYMBOLS[0]    : '';
        PT_CURRENT_TIMEFRAME = PT_ALL_TIMEFRAMES.length ? PT_ALL_TIMEFRAMES[0] : '';

        ptRenderTimeframeStrip();
        ptUpdateSymbolLabel();

        if (PT_CURRENT_SYMBOL && PT_CURRENT_TIMEFRAME) {
            ptLoadChart();
        } else {
            ptShowEmpty('No symbol or timeframe selected. Choose one from the top bar.');
        }

        window.addEventListener('resize', function () {
            if (PT_CANDLES.length) ptScheduleDraw();
        });
    });

    // ==================== CONFIG PERSISTENCE ====================
    // Rebuild the client-side PT_ROOT_TREES from canonical DB rows using the
    // SAME shape the PHP side produces: plain refs each with their own
    // rerefStack, so every re-ref rule participates in matching.
    function ptSaveProgrammeConfiguration(snapshot) {
        var body = 'save_configuration=1'
                 + '&programmeid=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&payload='     + encodeURIComponent(JSON.stringify(snapshot));

        fetch('programme_configuration.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) return;
            PT_CONFIG_ROWS = data.candles || [];
            PT_ROOT_TREES = ptRebuildRootTreesFromRows(PT_CONFIG_ROWS);
            if (typeof window.pcSetRootTrees === 'function') window.pcSetRootTrees(PT_ROOT_TREES);
            ptScheduleDraw();
            if (typeof window.pcCloseConfiguration === 'function') window.pcCloseConfiguration();
        })
        .catch(function () { /* silent */ });
    }

    function ptRebuildRootTreesFromRows(rows) {
        var rootsById = {};
        var order = [];

        // Pass 1: roots
        rows.forEach(function (r) {
            if (parseInt(r.root, 10) !== 1) return;
            rootsById[r.id] = {
                id: r.id,
                candle_name: r.candle_name,
                price_level: r.price_level,
                timeframe: r.timeframe,
                candle_type: r.candle_type,
                position: r.candle_position,
                operator: r.operator,
                refs: [],
                drawings: []
            };
            order.push(r.id);
        });

        // Pass 2: plain refs (re_referenced_root != 1), each gets empty rerefStack
        rows.forEach(function (r) {
            if (parseInt(r.root, 10) === 1) return;
            if (r.drawing_id !== null && r.drawing_id !== undefined) return;
            if (parseInt(r.re_referenced_root, 10) === 1) return;
            var anchor = r.candleid;
            if (!rootsById[anchor]) return;
            rootsById[anchor].refs.push({
                id: r.id,
                candle_name: r.candle_name,
                price_level: r.price_level,
                timeframe: r.timeframe,
                candle_type: r.candle_type,
                position: r.candle_position,
                candle_search: r.candle_search,
                operator: r.operator,
                re_referenced_root: 0,
                reference_id: r.reference_id,
                rerefStack: []
            });
        });

        // Pass 3: collect re-ref rows per anchor, pair them, attach to last plain ref
        var pendingReref = {};
        rows.forEach(function (r) {
            if (parseInt(r.root, 10) === 1) return;
            if (r.drawing_id !== null && r.drawing_id !== undefined) return;
            if (parseInt(r.re_referenced_root, 10) !== 1) return;
            var anchor = r.candleid;
            if (!rootsById[anchor]) return;
            if (!pendingReref[anchor]) pendingReref[anchor] = [];
            pendingReref[anchor].push({
                id: r.id,
                candle_name: r.candle_name,
                price_level: r.price_level,
                timeframe: r.timeframe,
                candle_type: r.candle_type,
                position: r.candle_position,
                candle_search: r.candle_search,
                operator: r.operator
            });
        });

        Object.keys(pendingReref).forEach(function (anchor) {
            var refs = rootsById[anchor].refs;
            if (!refs.length) return;
            var parentIdx = refs.length - 1;
            var bucket = pendingReref[anchor];
            for (var i = 0; i + 1 < bucket.length; i += 2) {
                refs[parentIdx].rerefStack.push({
                    author: bucket[i],
                    referenced: bucket[i + 1]
                });
            }
        });

        // Pass 4: drawings
        rows.forEach(function (r) {
            if (r.drawing_id === null || r.drawing_id === undefined) return;
            var anchor = r.candleid;
            if (!rootsById[anchor]) return;
            rootsById[anchor].drawings.push({
                id: r.id,
                drawing_id: parseInt(r.drawing_id, 10) || 0,
                drawing_tools: r.drawing_tools,
                from_root_price_level: r.from_root_price_level,
                drawing_destination: r.drawing_destination,
                to_reference_price_level: r.to_reference_price_level
            });
        });

        return order.map(function (id) { return rootsById[id]; });
    }

    // ==================== TOPBAR ====================
    function ptUpdateSymbolLabel() {
        var el = document.getElementById('ptSymbolLabel');
        if (!el) return;
        el.textContent = PT_CURRENT_SYMBOL || 'Select Symbol';
    }
    function ptRenderTimeframeStrip() {
        var strip = document.getElementById('ptTfStrip');
        if (!strip) return;
        if (!PT_ALL_TIMEFRAMES.length) { strip.innerHTML = '<span class="pt-tf-empty">No timeframes</span>'; return; }
        var html = '';
        PT_ALL_TIMEFRAMES.forEach(function (tf) {
            var cls = 'pt-tf-btn' + (tf === PT_CURRENT_TIMEFRAME ? ' active' : '');
            html += '<button type="button" class="' + cls + '" data-tf="' + ptEscapeHtml(tf) + '" '
                  + 'onclick="ptSelectTimeframe(\'' + tf.replace(/'/g, "\\'") + '\')">'
                  + ptEscapeHtml(tf) + '</button>';
        });
        strip.innerHTML = html;
    }
    function ptSelectTimeframe(tf) {
        if (!tf || tf === PT_CURRENT_TIMEFRAME) return;
        PT_CURRENT_TIMEFRAME = tf;
        ptRenderTimeframeStrip();
        PT_REQUESTED_AMOUNT = PT_DEFAULT_AMOUNT;
        ptLoadChart();
    }

    // ==================== SYMBOL MODAL ====================
    var PT_MODAL_FILTER = '';
    function ptOpenSymbolModal() {
        if (!PT_ALL_SYMBOLS.length) { ptShowEmpty('No symbols selected for this developer.'); return; }
        PT_MODAL_FILTER = '';
        var inp = document.getElementById('ptSymbolSearch');
        if (inp) inp.value = '';
        ptRenderSymbolModalList();
        document.getElementById('ptSymbolModal').classList.add('active');
    }
    function ptCloseSymbolModal() { document.getElementById('ptSymbolModal').classList.remove('active'); }
    function ptFilterSymbolModal(q) { PT_MODAL_FILTER = (q || '').trim().toUpperCase(); ptRenderSymbolModalList(); }
    function ptRenderSymbolModalList() {
        var list = document.getElementById('ptSymbolModalList');
        var countEl = document.getElementById('ptSymbolModalCount');
        if (!list) return;
        if (countEl) countEl.textContent = PT_ALL_SYMBOLS.length + ' symbols';
        var filtered = PT_ALL_SYMBOLS.filter(function (s) {
            if (!PT_MODAL_FILTER) return true;
            return String(s).toUpperCase().indexOf(PT_MODAL_FILTER) !== -1;
        });
        if (!filtered.length) { list.innerHTML = '<div class="pt-modal-empty">No symbols match your search.</div>'; return; }
        var html = '';
        filtered.forEach(function (sym) {
            var cls = 'pt-modal-row' + (sym === PT_CURRENT_SYMBOL ? ' is-active' : '');
            html += '<button type="button" class="' + cls + '" '
                  + 'onclick="ptSelectSymbol(\'' + sym.replace(/'/g, "\\'") + '\')">'
                  + ptEscapeHtml(sym) + '</button>';
        });
        list.innerHTML = html;
    }
    function ptSelectSymbol(sym) {
        if (!sym) return;
        PT_CURRENT_SYMBOL = sym;
        ptUpdateSymbolLabel();
        ptCloseSymbolModal();
        PT_REQUESTED_AMOUNT = PT_DEFAULT_AMOUNT;
        ptLoadChart();
    }

    // ==================== CANDLES-AMOUNT MODAL ====================
    function ptAttachCandlesModalEvents() {
        var confirm = document.getElementById('ptCandlesConfirm');
        var cancel  = document.getElementById('ptCandlesCancel');
        var input   = document.getElementById('ptCandlesInput');
        if (confirm) confirm.addEventListener('click', ptConfirmCandlesModal);
        if (cancel)  cancel.addEventListener('click', ptCloseCandlesModal);
        if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') ptConfirmCandlesModal(); });
    }
    function ptOpenCandlesModal(symbol, timeframe) {
        var modal = document.getElementById('ptCandlesModal');
        var sub   = document.getElementById('ptCandlesSub');
        var info  = document.getElementById('ptCandlesInfo');
        var inp   = document.getElementById('ptCandlesInput');
        var avail = document.getElementById('ptCandlesAvailable');
        if (!modal) return;
        if (!symbol || !timeframe) { if (info) info.textContent = 'Select a symbol and timeframe first.'; return; }
        if (info) info.textContent = '';
        if (inp) inp.value = '';
        if (avail) avail.textContent = 'Available Candle: …';
        if (sub) sub.textContent = 'Enter the number of candles to display for ' + symbol + ' / ' + timeframe + '.';
        modal.classList.add('active');
        setTimeout(function () { if (inp) inp.focus(); }, 50);
        var body = 'count_candles=1' + '&symbol=' + encodeURIComponent(symbol) + '&timeframe=' + encodeURIComponent(timeframe);
        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) { if (avail) avail.textContent = 'Available Candle: —'; return; }
            var total = parseInt(data.total, 10) || 0;
            if (avail) avail.textContent = 'Available Candle: ' + total.toLocaleString();
        })
        .catch(function () { if (avail) avail.textContent = 'Available Candle: —'; });
    }
    function ptCloseCandlesModal() {
        var modal = document.getElementById('ptCandlesModal');
        if (modal) modal.classList.remove('active');
    }
    function ptConfirmCandlesModal() {
        var inp  = document.getElementById('ptCandlesInput');
        var info = document.getElementById('ptCandlesInfo');
        var raw  = inp ? String(inp.value || '').trim() : '';
        var n    = parseInt(raw, 10);
        if (!raw || isNaN(n) || n <= 0) { if (info) info.textContent = 'Please enter a positive number.'; return; }
        PT_REQUESTED_AMOUNT = n;
        ptCloseCandlesModal();
        ptLoadChart();
    }

    // ==================== CANDLE DETAIL MODAL ====================
    function ptAttachCandleDetailModalEvents() {
        var modal = document.getElementById('ptCandleDetailModal');
        var closeBtn = document.getElementById('ptCandleDetailClose');
        if (closeBtn) closeBtn.addEventListener('click', ptCloseCandleDetailModal);
        if (modal) {
            modal.addEventListener('mousedown', function (e) {
                if (e.target === modal) ptCloseCandleDetailModal();
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') ptCloseCandleDetailModal();
        });
    }
    function ptOpenCandleDetailModal(idx) {
        if (idx < 0 || idx >= PT_CANDLES.length) return;
        var c = PT_CANDLES[idx];

        var titleEl = document.getElementById('ptCandleDetailTitle');
        var subEl   = document.getElementById('ptCandleDetailSub');
        var bodyEl  = document.getElementById('ptCandleDetailBody');
        if (!bodyEl) return;

        if (titleEl) titleEl.textContent = 'Candle #' + idx + ' — ' + ptFormatTime(c.time);
        if (subEl)   subEl.textContent   = PT_CURRENT_SYMBOL + ' · ' + PT_CURRENT_TIMEFRAME;

        var rows = [
            ['Timestamp',            c.time],
            ['Open',                 ptFormatPrice(c.open)],
            ['High',                 ptFormatPrice(c.high)],
            ['Low',                  ptFormatPrice(c.low)],
            ['Close',                ptFormatPrice(c.close)],
            ['Candle center',        c.candle_center       != null ? ptFormatPrice(c.candle_center)       : '—'],
            ['Body center',          c.body_center         != null ? ptFormatPrice(c.body_center)         : '—'],
            ['High wick center',     c.high_wick_center    != null ? ptFormatPrice(c.high_wick_center)    : '—'],
            ['Low wick center',      c.low_wick_center     != null ? ptFormatPrice(c.low_wick_center)     : '—'],
            ['Candle width center',  c.candle_width_center != null ? ptFormatPrice(c.candle_width_center) : '—'],
            ['Type',                 (c.close > c.open ? 'bullish' : (c.close < c.open ? 'bearish' : 'flat'))]
        ];

        var html = '';
        rows.forEach(function (row) {
            html += '<div class="pt-candle-detail-row">'
                  +   '<span class="pt-candle-detail-label">' + ptEscapeHtml(row[0]) + '</span>'
                  +   '<span class="pt-candle-detail-value">' + ptEscapeHtml(row[1]) + '</span>'
                  + '</div>';
        });
        bodyEl.innerHTML = html;

        var modal = document.getElementById('ptCandleDetailModal');
        if (modal) modal.classList.add('active');
    }
    function ptCloseCandleDetailModal() {
        var modal = document.getElementById('ptCandleDetailModal');
        if (modal) modal.classList.remove('active');
    }

    // ==================== LOAD CHART ====================
    function ptLoadChart() {
        if (!PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME) { ptShowEmpty('Select a symbol and timeframe.'); return; }
        PT_LOAD_TOKEN++;
        PT_CANDLES = [];
        PT_HOVER_INDEX = -1;
        PT_CURSOR_Y = null;
        PT_HAS_MORE_OLDER = true;
        PT_IS_LOADING_OLDER = false;
        PT_INITIAL_LOADED = false;
        PT_CONSECUTIVE_ERRORS = 0;
        PT_VIEW.offsetX = 0; PT_VIEW.offsetY = 0; PT_VIEW.priceScale = 1.0; PT_VIEW.candleWidth = 9;
        ptShowLoading(true); ptShowEmpty(null); ptClearCanvas();
        ptFetchChunk(null, PT_LOAD_TOKEN, true);
    }

    function ptFetchChunk(before, token, isInitial) {
        var pageSize = PT_PAGE_SIZE;
        if (PT_REQUESTED_AMOUNT !== null) {
            var remaining = PT_REQUESTED_AMOUNT - PT_CANDLES.length;
            if (remaining > 0 && remaining < pageSize) pageSize = remaining;
        }
        var body = 'fetch_candles=1'
                 + '&symbol='    + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe=' + encodeURIComponent(PT_CURRENT_TIMEFRAME)
                 + '&limit='     + pageSize;
        if (before) body += '&before=' + encodeURIComponent(before);

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (token !== PT_LOAD_TOKEN) return;
            if (!data.success) {
                PT_CONSECUTIVE_ERRORS++;
                ptShowLoading(false); ptShowBgLoading(false);
                PT_HAS_MORE_OLDER = false;
                if (!PT_CANDLES.length) ptShowEmpty(data.message || 'Failed to load candles.');
                else { ptShowBgLoading(true, true); ptSetBgLoadingText('Stopped loading (server limit).'); }
                return;
            }
            PT_CONSECUTIVE_ERRORS = 0;
            var chunk = Array.isArray(data.candles) ? data.candles : [];
            if (isInitial) { ptShowLoading(false); PT_INITIAL_LOADED = true; }
            if (chunk.length) {
                if (isInitial) {
                    PT_CANDLES = chunk.slice();
                    ptFitViewportToCandles();
                    ptScheduleDraw();
                    ptScrollToLatest();
                    PT_FOLLOW_LATEST = true;
                    ptScheduleDraw();
                } else {
                    var step = ptStep();
                    PT_VIEW.offsetX += chunk.length * step;
                    PT_CANDLES = chunk.concat(PT_CANDLES);
                    ptScheduleDraw();
                }
            } else if (isInitial) {
                ptClearCanvas();
                ptShowEmpty('No candle records found for this symbol / timeframe.');
                PT_HAS_MORE_OLDER = false;
                return;
            }
            PT_HAS_MORE_OLDER = !!data.has_more && chunk.length > 0;
            if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) PT_HAS_MORE_OLDER = false;
            PT_IS_LOADING_OLDER = false;
            ptUpdateJumpLatestVisibility();
            if (isInitial) ptAutoLoadUpToTarget(token);
        })
        .catch(function (err) {
            if (token !== PT_LOAD_TOKEN) return;
            PT_CONSECUTIVE_ERRORS++;
            ptShowLoading(false);
            PT_IS_LOADING_OLDER = false;
            if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                PT_HAS_MORE_OLDER = false;
                ptShowBgLoading(true, true);
                ptSetBgLoadingText('Stopped loading (network/DB limit).');
                return;
            }
            if (PT_CANDLES.length) setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
            else ptShowEmpty('Network error: ' + (err && err.message ? err.message : 'Unknown'));
        });
    }

    function ptAutoLoadUpToTarget(token) {
        if (token !== PT_LOAD_TOKEN) return;
        if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) {
            ptShowBgLoading(false); ptFitViewportToCandles(); ptScheduleDraw();
            ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility();
            return;
        }
        if (!PT_HAS_MORE_OLDER) {
            ptShowBgLoading(false); ptFitViewportToCandles(); ptScheduleDraw();
            ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility();
            return;
        }
        if (!PT_CANDLES.length) { ptShowBgLoading(false); return; }
        if (PT_IS_LOADING_OLDER) return;
        if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
            ptShowBgLoading(true, true);
            ptSetBgLoadingText('Stopped loading (repeated failures).');
            return;
        }
        PT_IS_LOADING_OLDER = true;
        ptShowBgLoading(true);
        ptSetBgLoadingText('Loading candles… (' + PT_CANDLES.length.toLocaleString()
            + (PT_REQUESTED_AMOUNT !== null ? ' / ' + PT_REQUESTED_AMOUNT.toLocaleString() : '') + ')');
        var oldest = PT_CANDLES[0].time;
        var pageSize = PT_PAGE_SIZE;
        if (PT_REQUESTED_AMOUNT !== null) {
            var remaining = PT_REQUESTED_AMOUNT - PT_CANDLES.length;
            if (remaining > 0 && remaining < pageSize) pageSize = remaining;
        }
        var body = 'fetch_candles=1'
                 + '&symbol='    + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe=' + encodeURIComponent(PT_CURRENT_TIMEFRAME)
                 + '&limit='     + pageSize
                 + '&before='    + encodeURIComponent(oldest);

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            PT_IS_LOADING_OLDER = false;
            if (token !== PT_LOAD_TOKEN) return;
            if (!data.success) {
                PT_CONSECUTIVE_ERRORS++;
                if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                    PT_HAS_MORE_OLDER = false;
                    ptShowBgLoading(true, true);
                    ptSetBgLoadingText('Stopped loading (server limit).');
                } else setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
                return;
            }
            PT_CONSECUTIVE_ERRORS = 0;
            var chunk = Array.isArray(data.candles) ? data.candles : [];
            if (chunk.length) {
                var step = ptStep();
                PT_VIEW.offsetX += chunk.length * step;
                PT_CANDLES = chunk.concat(PT_CANDLES);
                ptScheduleDraw();
                ptUpdateJumpLatestVisibility();
            }
            PT_HAS_MORE_OLDER = !!data.has_more && chunk.length > 0;
            if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) PT_HAS_MORE_OLDER = false;
            if (!PT_HAS_MORE_OLDER) {
                ptFitViewportToCandles(); ptScheduleDraw();
                ptCenterOnLatest(); ptScheduleDraw();
                ptShowBgLoading(false);
                return;
            }
            setTimeout(function () { ptAutoLoadUpToTarget(token); }, 25);
        })
        .catch(function () {
            PT_IS_LOADING_OLDER = false;
            PT_CONSECUTIVE_ERRORS++;
            if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                PT_HAS_MORE_OLDER = false;
                ptShowBgLoading(true, true);
                ptSetBgLoadingText('Stopped loading (network/DB limit).');
            } else setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
        });
    }

    function ptShowBgLoading(on, warn) {
        var el = document.getElementById('ptBgLoading');
        if (!el) return;
        if (on) { el.style.display = 'inline-block'; el.classList.toggle('pt-warn', !!warn); }
        else { el.style.display = 'none'; el.classList.remove('pt-warn'); }
    }
    function ptSetBgLoadingText(t) {
        var el = document.getElementById('ptBgLoading');
        if (el) el.textContent = t;
    }

    // ==================== FIT VIEWPORT ====================
    function ptFitViewportToCandles() {
        var wrap = document.getElementById('ptChartWrap');
        var vw = wrap.clientWidth;
        var target = PT_CANDLES.length;
        if (target < 2) return;
        var avail = vw - PT_PADDING_LEFT - PT_PADDING_RIGHT;
        var gapRatio = 0.35;
        var cw = avail / (target * (1 + gapRatio));
        cw = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, cw));
        PT_VIEW.candleWidth = cw;
        PT_VIEW.candleSpacing = Math.max(0.4, cw * gapRatio);
    }
    function ptCenterOnLatest() {
        var wrap = document.getElementById('ptChartWrap');
        var vw = wrap.clientWidth; var step = ptStep();
        var n = PT_CANDLES.length; if (!n) return;
        var newestCenterX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + (n - 1) * step + step / 2;
        PT_VIEW.offsetX = newestCenterX - (vw / 2);
        ptClampOffsetX();
        PT_FOLLOW_LATEST = false;
    }
    function ptScrollToLatest() {
        var wrap = document.getElementById('ptChartWrap');
        var vw = wrap.clientWidth; var step = ptStep();
        var n = PT_CANDLES.length; if (!n) return;
        var newestRight = PT_PADDING_LEFT + PT_VIRTUAL_PAD + (n - 1) * step + step;
        var targetRightEdge = vw - PT_PADDING_RIGHT - PT_VIEW.candleSpacing;
        PT_VIEW.offsetX = newestRight - targetRightEdge;
        ptClampOffsetX();
        PT_FOLLOW_LATEST = true;
    }

    // ==================== JUMP-TO-LATEST ====================
    function ptUpdateJumpLatestVisibility() {
        var btn = document.getElementById('ptJumpLatest');
        if (!btn) return;
        if (!PT_CANDLES.length) { btn.style.display = 'none'; return; }
        var wrap = document.getElementById('ptChartWrap');
        var vw = wrap.clientWidth; var step = ptStep();
        var baseX = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
        var newestLeft  = baseX + (PT_CANDLES.length - 1) * step + step / 2 - PT_VIEW.candleWidth / 2;
        var newestRight = newestLeft + PT_VIEW.candleWidth;
        var leftLimit = PT_PADDING_LEFT, rightLimit = vw - PT_PADDING_RIGHT;
        var fullyVisible = newestLeft >= leftLimit && newestRight <= rightLimit;
        btn.style.display = fullyVisible ? 'none' : 'flex';
    }
    function ptJumpToLatest() { ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility(); }
    window.ptJumpToLatest = ptJumpToLatest;

    // ==================== LAZY LOAD OLDER ====================
    function ptMaybeLoadOlder() {
        if (!PT_INITIAL_LOADED) return;
        if (!PT_HAS_MORE_OLDER) return;
        if (PT_IS_LOADING_OLDER) return;
        if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) return;
        if (!PT_CANDLES.length) return;
        if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) return;
        if (PT_VIEW.offsetX < 400) ptAutoLoadUpToTarget(PT_LOAD_TOKEN);
    }

    // ==================== UI STATE ====================
    function ptShowLoading(on) {
        var el = document.getElementById('ptChartLoading');
        if (el) el.style.display = on ? 'flex' : 'none';
    }
    function ptShowEmpty(msg) {
        var el = document.getElementById('ptChartEmpty');
        if (!el) return;
        if (msg) { el.querySelector('p').textContent = msg; el.style.display = 'flex'; }
        else el.style.display = 'none';
    }
    function ptClearCanvas() {
        if (!PT_CTX || !PT_CANVAS) return;
        PT_CTX.clearRect(0, 0, PT_CANVAS.width, PT_CANVAS.height);
    }

    // ==================== DRAW ====================
    function ptDrawChart() {
        if (!PT_CANVAS) PT_CANVAS = document.getElementById('ptChartCanvas');
        if (!PT_CANVAS) return;
        PT_CTX = PT_CANVAS.getContext('2d');

        if (!PT_DRAW_CANVAS) PT_DRAW_CANVAS = document.getElementById('ptDrawCanvas');
        if (PT_DRAW_CANVAS) PT_DRAW_CTX = PT_DRAW_CANVAS.getContext('2d');

        var wrap = document.getElementById('ptChartWrap');
        var dpr = window.devicePixelRatio || 1;

        var viewportW = wrap.clientWidth;
        var viewportH = wrap.clientHeight;

        PT_CANVAS.style.width  = viewportW + 'px';
        PT_CANVAS.style.height = viewportH + 'px';
        PT_CANVAS.width  = Math.floor(viewportW * dpr);
        PT_CANVAS.height = Math.floor(viewportH * dpr);
        PT_CTX.setTransform(dpr, 0, 0, dpr, 0, 0);
        PT_CTX.clearRect(0, 0, viewportW, viewportH);

        if (PT_DRAW_CANVAS) {
            PT_DRAW_CANVAS.style.width  = viewportW + 'px';
            PT_DRAW_CANVAS.style.height = viewportH + 'px';
            PT_DRAW_CANVAS.width  = Math.floor(viewportW * dpr);
            PT_DRAW_CANVAS.height = Math.floor(viewportH * dpr);
            PT_DRAW_CTX.setTransform(dpr, 0, 0, dpr, 0, 0);
            PT_DRAW_CTX.clearRect(0, 0, viewportW, viewportH);
        }

        if (!PT_CANDLES.length) { ptUpdateJumpLatestVisibility(); return; }

        var styles = getComputedStyle(document.body);
        var accent   = (styles.getPropertyValue('--accent') || '#2e8b57').trim();
        var textMut  = (styles.getPropertyValue('--text-muted') || '#888').trim();
        var bg       = (styles.getPropertyValue('--bg') || '').trim() || '#ffffff';

        PT_CTX.fillStyle = bg;
        PT_CTX.fillRect(0, 0, viewportW, viewportH);

        var step = ptStep();
        var cw   = PT_VIEW.candleWidth;
        var half = cw / 2;
        var baseX = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;

        var firstVisible = Math.max(0, Math.floor((PT_VIEW.offsetX - PT_PADDING_LEFT - PT_VIRTUAL_PAD) / step));
        var lastVisible  = Math.min(PT_CANDLES.length - 1,
                            Math.ceil((PT_VIEW.offsetX + viewportW - PT_PADDING_LEFT - PT_VIRTUAL_PAD) / step));
        if (lastVisible < firstVisible) { ptUpdateJumpLatestVisibility(); return; }

        var minPrice = Infinity, maxPrice = -Infinity;
        for (var i = firstVisible; i <= lastVisible; i++) {
            var c = PT_CANDLES[i];
            if (c.low  < minPrice) minPrice = c.low;
            if (c.high > maxPrice) maxPrice = c.high;
        }
        if (!isFinite(minPrice) || !isFinite(maxPrice)) { ptUpdateJumpLatestVisibility(); return; }
        if (minPrice === maxPrice) { minPrice -= 1; maxPrice += 1; }

        var chartTop    = PT_PADDING_TOP;
        var chartBottom = viewportH - PT_PADDING_BOTTOM;
        var chartHeight = chartBottom - chartTop;
        if (chartHeight <= 0) { ptUpdateJumpLatestVisibility(); return; }

        var rawRange = maxPrice - minPrice;
        var padRatio = 0.05 * PT_VIEW.priceScale;
        var rangeTop = maxPrice + rawRange * padRatio;
        var rangeBot = minPrice - rawRange * padRatio;
        var mid = (rangeTop + rangeBot) / 2;
        var halfRange = (rangeTop - rangeBot) / 2 * PT_VIEW.priceScale;
        rangeTop = mid + halfRange;
        rangeBot = mid - halfRange;
        var range = rangeTop - rangeBot; if (range <= 0) range = 1;

        var priceShift = (PT_VIEW.offsetY / chartHeight) * range;
        var pTop = rangeTop + priceShift;
        var pBot = rangeBot + priceShift;
        var pRange = pTop - pBot;

        function pToY(p) { return chartTop + (pTop - p) / pRange * chartHeight; }
        function yToP(y) { return pTop - (y - chartTop) / chartHeight * pRange; }

        // Price labels
        PT_CTX.fillStyle = textMut;
        PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
        PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'right';
        var gridLines = 6;
        for (var g = 0; g <= gridLines; g++) {
            var t = g / gridLines;
            var y = chartTop + t * chartHeight;
            var price = pTop - t * pRange;
            PT_CTX.fillText(ptFormatPrice(price), PT_PADDING_LEFT - 8, y);
        }

        // Candles
        for (var k = firstVisible; k <= lastVisible; k++) {
            var cd = PT_CANDLES[k];
            var cx = baseX + k * step + step / 2;
            var yHigh = pToY(cd.high), yLow = pToY(cd.low);
            var yOpen = pToY(cd.open), yClose = pToY(cd.close);
            var up = cd.close >= cd.open;
            var color = up ? accent : '#e74c3c';
            PT_CTX.strokeStyle = color;
            PT_CTX.lineWidth = Math.max(1, Math.min(2, cw * 0.15));
            PT_CTX.beginPath(); PT_CTX.moveTo(cx, yHigh); PT_CTX.lineTo(cx, yLow); PT_CTX.stroke();
            var bodyTop = Math.min(yOpen, yClose);
            var bodyH   = Math.max(1, Math.abs(yClose - yOpen));
            PT_CTX.fillStyle = color;
            PT_CTX.fillRect(cx - half, bodyTop, cw, bodyH);
        }

        // Time labels
        PT_CTX.fillStyle = textMut;
        PT_CTX.textAlign = 'center'; PT_CTX.textBaseline = 'top';
        PT_CTX.font = '10px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
        var labelStep = Math.max(1, Math.round(100 / step));
        var labelStart = Math.ceil(firstVisible / labelStep) * labelStep;
        for (var m = labelStart; m <= lastVisible; m += labelStep) {
            var cdx = baseX + m * step + step / 2;
            var tLabel = ptFormatTime(PT_CANDLES[m].time).slice(5, 16);
            PT_CTX.fillText(tLabel, cdx, chartBottom + 6);
        }

        // Crosshair
        var hasV = PT_HOVER_INDEX >= 0 && PT_HOVER_INDEX < PT_CANDLES.length;
        var hasH = PT_CURSOR_Y !== null && PT_CURSOR_Y >= chartTop && PT_CURSOR_Y <= chartBottom;
        var chColor = 'rgba(128,128,128,0.7)';

        if (hasV) {
            var hx = baseX + PT_HOVER_INDEX * step + step / 2;
            PT_CTX.strokeStyle = chColor; PT_CTX.lineWidth = 1;
            PT_CTX.setLineDash([3, 3]);
            PT_CTX.beginPath(); PT_CTX.moveTo(hx, chartTop); PT_CTX.lineTo(hx, chartBottom); PT_CTX.stroke();
            PT_CTX.setLineDash([]);
            var labelText = ptFormatTime(PT_CANDLES[PT_HOVER_INDEX].time).slice(5, 16);
            var ls = ptLabelStyles();
            PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'center';
            var padX = 8, textW = PT_CTX.measureText(labelText).width;
            var boxW  = textW + padX * 2, boxH = 20;
            var chipX = hx - boxW / 2;
            var chipY = (chartBottom + (viewportH - PT_PADDING_BOTTOM)) / 2 - boxH / 2;
            if (chipX < 0) chipX = 0;
            if (chipX + boxW > viewportW) chipX = viewportW - boxW;
            PT_CTX.fillStyle = ls.bg;
            ptRoundRect(PT_CTX, chipX, chipY, boxW, boxH, 4); PT_CTX.fill();
            PT_CTX.strokeStyle = ls.border; PT_CTX.lineWidth = 1; PT_CTX.stroke();
            PT_CTX.fillStyle = ls.bgText;
            PT_CTX.fillText(labelText, chipX + boxW / 2, chipY + boxH / 2 + 0.5);
        }
        if (hasH) {
            PT_CTX.strokeStyle = chColor; PT_CTX.lineWidth = 1;
            PT_CTX.setLineDash([3, 3]);
            PT_CTX.beginPath(); PT_CTX.moveTo(PT_PADDING_LEFT, PT_CURSOR_Y);
            PT_CTX.lineTo(viewportW - PT_PADDING_RIGHT, PT_CURSOR_Y); PT_CTX.stroke();
            PT_CTX.setLineDash([]);
            var priceVal = yToP(PT_CURSOR_Y);
            var priceText = ptFormatPrice(priceVal);
            var ls2 = ptLabelStyles();
            PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'center';
            var padX2 = 6, boxH2 = 20;
            var textW2 = PT_CTX.measureText(priceText).width;
            var boxW2  = textW2 + padX2 * 2;
            var gutterLeft  = viewportW - PT_PADDING_RIGHT;
            var gutterWidth = PT_PADDING_RIGHT;
            var chipX2 = gutterLeft + Math.max(0, (gutterWidth - boxW2) / 2);
            if (chipX2 + boxW2 > viewportW - 2) chipX2 = viewportW - 2 - boxW2;
            var chipY2 = PT_CURSOR_Y - boxH2 / 2;
            if (chipY2 < 0) chipY2 = 0;
            if (chipY2 + boxH2 > viewportH) chipY2 = viewportH - boxH2;
            PT_CTX.fillStyle = ls2.bg;
            ptRoundRect(PT_CTX, chipX2, chipY2, boxW2, boxH2, 4); PT_CTX.fill();
            PT_CTX.strokeStyle = ls2.border; PT_CTX.lineWidth = 1; PT_CTX.stroke();
            PT_CTX.fillStyle = ls2.bgText;
            PT_CTX.fillText(priceText, chipX2 + boxW2 / 2, chipY2 + boxH2 / 2 + 0.5);
        }

        // Drawings
        ptDrawProgrammeDrawings(pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible);

        PT_CANVAS._ptStep     = step;
        PT_CANVAS._ptBaseX    = baseX;
        PT_CANVAS._ptChartTop = chartTop;
        PT_CANVAS._ptChartBot = chartBottom;

        ptUpdateJumpLatestVisibility();
    }

    // ============================================================
    // DRAWING ENGINE — strict, AND-only semantics
    // ============================================================
    function ptCandleTypeMatches(type, candle) {
        if (!candle) return false;
        var ct = (type || '').toLowerCase();
        if (ct === 'bullish') return candle.close > candle.open;
        if (ct === 'bearish') return candle.close < candle.open;
        return true;
    }
    function ptCompare(a, op, b) {
        if (a == null || b == null) return false;
        switch (op) {
            case '<':  return a <  b;
            case '>':  return a >  b;
            case '<=': return a <= b;
            case '>=': return a >= b;
            default:   return true;
        }
    }

    function ptRootCandidateIndices(root, firstVisible, lastVisible) {
        var pos = parseInt(root.position, 10);
        if (isNaN(pos)) pos = 0;
        var search = (root.candle_search || '').toLowerCase();

        if (pos === 0 && search === '') {
            var out = [];
            for (var k = firstVisible; k <= lastVisible; k++) out.push(k);
            return out;
        }
        if (search !== 'all') {
            var anchor = pos > 0 ? (lastVisible - pos) : (firstVisible - pos);
            return [ anchor ];
        }
        var out2 = [];
        var step = pos >= 0 ? 1 : -1;
        for (var j = 0; j !== pos + step; j += step) out2.push(lastVisible + j);
        return out2;
    }

    function ptResolvePeerIndices(row, rootIdx, firstVisible, lastVisible) {
        var pos = parseInt(row.position, 10);
        if (isNaN(pos)) pos = 0;
        var search = (row.candle_search || '').toLowerCase();

        if (pos === 0 && search === '') return [rootIdx];
        if (search === 'all') {
            var out = [];
            var step = pos >= 0 ? 1 : -1;
            for (var j = 0; j !== pos + step; j += step) out.push(rootIdx + j);
            return out;
        }
        return [ rootIdx + pos ];
    }

    // Strict AND: EVERY reference on the tree, and EVERY re-ref pair on each
    // reference, must hold. Any single failure means the whole tree fails at
    // this root candle.
    function ptTreeMatchesAt(tree, i, firstVisible, lastVisible) {
        var rootCandle = PT_CANDLES[i];
        if (!rootCandle) return false;

        if (!ptCandleTypeMatches(tree.candle_type, rootCandle)) return false;

        var rootKey = tree.price_level;
        if (!rootKey) return false;
        var rootVal = rootCandle[rootKey];
        if (rootVal == null) return false;

        var refs = tree.refs || [];
        if (!refs.length) return true;   // root-only tree

        for (var ri = 0; ri < refs.length; ri++) {
            if (!ptReferenceMatches(refs[ri], tree, rootVal, i, firstVisible, lastVisible)) {
                return false;
            }
        }
        return true;
    }

    function ptReferenceMatches(ref, tree, rootVal, rootIdx, firstVisible, lastVisible) {
        if (!ref) return true;

        var refIndices = ptResolvePeerIndices(ref, rootIdx, firstVisible, lastVisible);
        if (!refIndices.length) return false;

        var refKey = ref.price_level;
        if (!refKey) return false;

        // AND across every resolved reference candle.
        var checked = 0;
        for (var k = 0; k < refIndices.length; k++) {
            var rIdx = refIndices[k];
            if (rIdx < 0 || rIdx >= PT_CANDLES.length) return false;
            var rc = PT_CANDLES[rIdx];
            if (!rc) return false;
            if (!ptCandleTypeMatches(ref.candle_type, rc)) return false;
            var rv = rc[refKey];
            if (rv == null) return false;
            checked++;
            if (!ptCompare(rootVal, tree.operator, rv)) return false;
        }
        if (checked === 0) return false;

        // AND across every re-ref pair on this reference.
        var pairs = ref.rerefStack || [];
        for (var pi = 0; pi < pairs.length; pi++) {
            if (!ptRerefPairMatches(pairs[pi], rootIdx, firstVisible, lastVisible)) {
                return false;
            }
        }
        return true;
    }

    function ptRerefPairMatches(pair, rootIdx, firstVisible, lastVisible) {
        if (!pair) return true;
        var a = pair.author;
        var b = pair.referenced;
        if (!a || !b) return false;

        var aKey = a.price_level;
        var bKey = b.price_level;
        if (!aKey || !bKey) return false;
        if (!a.operator) return false;

        var aIndices = ptResolvePeerIndices(a, rootIdx, firstVisible, lastVisible);
        var bIndices = ptResolvePeerIndices(b, rootIdx, firstVisible, lastVisible);
        if (!aIndices.length || !bIndices.length) return false;

        // AND across every (author, referenced) pairing.
        var checked = 0;
        for (var ai = 0; ai < aIndices.length; ai++) {
            var aIdx = aIndices[ai];
            if (aIdx < 0 || aIdx >= PT_CANDLES.length) return false;
            var ac = PT_CANDLES[aIdx];
            if (!ac) return false;
            if (!ptCandleTypeMatches(a.candle_type, ac)) return false;
            var av = ac[aKey];
            if (av == null) return false;

            for (var bi = 0; bi < bIndices.length; bi++) {
                var bIdx = bIndices[bi];
                if (bIdx < 0 || bIdx >= PT_CANDLES.length) return false;
                var bc = PT_CANDLES[bIdx];
                if (!bc) return false;
                if (!ptCandleTypeMatches(b.candle_type, bc)) return false;
                var bv = bc[bKey];
                if (bv == null) return false;
                checked++;
                if (!ptCompare(av, a.operator, bv)) return false;
            }
        }
        return checked > 0;
    }

    // Walk from root toward the reference candle. At each candle's x, take the
    // FLAT price (the root's from_root_price_level). If that flat price lies
    // inside the candle's [low, high], the line touches. Return the first such
    // touch. The walk always includes the reference candle itself, so if no
    // earlier candle intercepted, the line terminates exactly at the reference
    // candle's x-center. If even the reference candle doesn't contain the flat
    // price, return null (no drawing) — the reference is the hard x-limit.
    function ptFindFlatTouchTowardsRef(rootIdx, refIdx, y1, flatPrice, baseX, step) {
        if (refIdx === rootIdx) return null;
        var dir = (refIdx > rootIdx) ? 1 : -1;
        var j = rootIdx + dir;
        var lastIdx = refIdx;
        for (; dir > 0 ? j <= lastIdx : j >= lastIdx; j += dir) {
            var cn = PT_CANDLES[j];
            if (!cn) continue;
            if (flatPrice <= cn.high && flatPrice >= cn.low) {
                var xj = baseX + j * step + step / 2;
                return { x: xj, y: y1, idx: j };
            }
        }
        return null;
    }

    // Walk from root outward in the reference direction, looking for the
    // first candle whose [low, high] contains a flat price.
    function ptFindAnyIntercept(rootIdx, dir, y1, yFlatPrice, baseX, step, firstVisible, lastVisible) {
        var j = rootIdx + dir;
        var lastIdx = (dir > 0) ? lastVisible : firstVisible;
        for (; dir > 0 ? j <= lastIdx : j >= lastIdx; j += dir) {
            var cn = PT_CANDLES[j];
            if (!cn) continue;
            if (yFlatPrice <= cn.high && yFlatPrice >= cn.low) {
                var xj = baseX + j * step + step / 2;
                return { x: xj, y: y1, idx: j };
            }
        }
        return null;
    }

    function ptDrawProgrammeDrawings(pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible) {
        if (!PT_DRAW_CTX) return;
        if (!Array.isArray(PT_ROOT_TREES) || !PT_ROOT_TREES.length) return;
        if (!PT_CANDLES.length) return;

        PT_DRAW_CTX.save();
        PT_DRAW_CTX.lineWidth = 1.6;
        PT_DRAW_CTX.strokeStyle = 'rgba(74,123,216,0.95)';
        PT_DRAW_CTX.setLineDash([]);

        PT_ROOT_TREES.forEach(function (tree) {
            if (!tree.timeframe || tree.timeframe !== PT_CURRENT_TIMEFRAME) return;
            var drawings = tree.drawings || [];

            var candidates = ptRootCandidateIndices(tree, firstVisible, lastVisible);

            candidates.forEach(function (i) {
                if (i < firstVisible || i > lastVisible) return;
                if (i < 0 || i >= PT_CANDLES.length) return;
                if (!ptTreeMatchesAt(tree, i, firstVisible, lastVisible)) return;

                var rootCandle = PT_CANDLES[i];
                var xR = baseX + i * step + step / 2;

                // Tint the matched root candle (visual feedback)
                PT_DRAW_CTX.save();
                PT_DRAW_CTX.fillStyle = 'rgba(46,139,87,0.14)';
                PT_DRAW_CTX.fillRect(xR - PT_VIEW.candleWidth / 2,
                                     pToY(rootCandle.high),
                                     PT_VIEW.candleWidth,
                                     pToY(rootCandle.low) - pToY(rootCandle.high));
                PT_DRAW_CTX.restore();

                if (!drawings.length) return;

                var ref = (tree.refs && tree.refs.length) ? tree.refs[0] : null;
                if (!ref) return;

                var refPos = parseInt(ref.position, 10);
                if (isNaN(refPos)) refPos = 0;
                var refIdx = i + refPos;
                if (refIdx < 0 || refIdx >= PT_CANDLES.length) return;
                var refCandle = PT_CANDLES[refIdx];
                if (!refCandle) return;

                var xRef = baseX + refIdx * step + step / 2;

                drawings.forEach(function (dr) {
                    if (dr.drawing_tools !== 'trendline') return;
                    var fromKey = dr.from_root_price_level;
                    if (!fromKey) return;
                    var fromVal = rootCandle[fromKey];
                    if (fromVal == null) return;

                    var x1 = xR;
                    var y1 = pToY(fromVal);
                    var dest = dr.drawing_destination || '';

                    if (dest === 'specific_reference_price_levels') {
                        var specKey = dr.to_reference_price_level;
                        if (!specKey) return;
                        var specVal = refCandle[specKey];
                        if (specVal == null) return;
                        var y2 = pToY(specVal);
                        drawSegment(x1, y1, xRef, y2);
                        return;
                    }

                    if (dest === 'reference_axis') {
                        drawSegment(x1, y1, xRef, y1);
                        return;
                    }

                    if (dest === 'reference') {
                        var hit = ptFindFlatTouchTowardsRef(i, refIdx, y1, fromVal, baseX, step);
                        if (!hit) return;
                        drawSegment(x1, y1, hit.x, hit.y);
                        return;
                    }

                    if (dest === 'any_candle_intercept') {
                        var dir = (refPos >= 0) ? 1 : -1;
                        var hit2 = ptFindAnyIntercept(i, dir, y1, fromVal, baseX, step, firstVisible, lastVisible);
                        if (hit2) {
                            drawSegment(x1, y1, hit2.x, hit2.y);
                        } else {
                            drawSegment(x1, y1, xRef, y1);
                        }
                        return;
                    }
                });
            });
        });

        PT_DRAW_CTX.restore();

        function drawSegment(x1, y1, x2, y2) {
            if (y1 < chartTop - 500 || y1 > chartBottom + 500) return;
            if (y2 < chartTop - 500 || y2 > chartBottom + 500) return;
            PT_DRAW_CTX.beginPath();
            PT_DRAW_CTX.moveTo(x1, y1);
            PT_DRAW_CTX.lineTo(x2, y2);
            PT_DRAW_CTX.stroke();
        }
    }

    // ==================== HOVER ====================
    function ptHandleMouseMove(e) {
        if (!PT_CANVAS || !PT_CANDLES.length) return;
        if (PT_DRAG_STATE.mode) return;
        var rect = PT_CANVAS.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var step  = PT_CANVAS._ptStep || ptStep();
        var baseX = PT_CANVAS._ptBaseX || (PT_PADDING_LEFT + PT_VIRTUAL_PAD);
        var idx = Math.floor((x - baseX) / step);
        if (idx < 0 || idx >= PT_CANDLES.length) idx = -1;
        var inPlotHorizontally = (x >= PT_PADDING_LEFT && x <= (PT_CANVAS.clientWidth - PT_PADDING_RIGHT));
        var newCursorY = inPlotHorizontally ? y : null;
        var changed = (idx !== PT_HOVER_INDEX) || (newCursorY !== PT_CURSOR_Y);
        if (changed) { PT_HOVER_INDEX = idx; PT_CURSOR_Y = newCursorY; ptScheduleDraw(); ptUpdateReadout(); }
    }
    function ptHandleMouseLeave() {
        if (PT_HOVER_INDEX !== -1 || PT_CURSOR_Y !== null) {
            PT_HOVER_INDEX = -1; PT_CURSOR_Y = null;
            ptScheduleDraw(); ptUpdateReadout();
        }
    }
    function ptUpdateReadout() {
        if (PT_HOVER_INDEX < 0 || PT_HOVER_INDEX >= PT_CANDLES.length) {
            document.getElementById('ptReadoutTime').textContent  = '—';
            document.getElementById('ptReadoutOpen').textContent  = '—';
            document.getElementById('ptReadoutHigh').textContent  = '—';
            document.getElementById('ptReadoutLow').textContent   = '—';
            document.getElementById('ptReadoutClose').textContent = '—';
            return;
        }
        var c = PT_CANDLES[PT_HOVER_INDEX];
        document.getElementById('ptReadoutTime').textContent  = ptFormatTime(c.time);
        document.getElementById('ptReadoutOpen').textContent  = ptFormatPrice(c.open);
        document.getElementById('ptReadoutHigh').textContent  = ptFormatPrice(c.high);
        document.getElementById('ptReadoutLow').textContent   = ptFormatPrice(c.low);
        document.getElementById('ptReadoutClose').textContent = ptFormatPrice(c.close);
    }

    // ==================== PAN + PINCH + DOUBLE-CLICK ====================
    var PT_DRAG_STATE = { mode: null, startX: 0, startY: 0, startOffsetX: 0, startOffsetY: 0, moved: false, downAt: 0 };
    var PT_TOUCH_STATE = { pointers: {}, pinchStartDist: 0, pinchStartCandleWidth: 0, pinchMidX: 0, _lastPan: null };

    function ptAttachCanvasEvents() {
        PT_CANVAS.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            PT_DRAG_STATE.mode = 'pan';
            PT_DRAG_STATE.startX = e.clientX;
            PT_DRAG_STATE.startY = e.clientY;
            PT_DRAG_STATE.startOffsetX = PT_VIEW.offsetX;
            PT_DRAG_STATE.startOffsetY = PT_VIEW.offsetY;
            PT_DRAG_STATE.moved = false;
            PT_DRAG_STATE.downAt = Date.now();
            PT_CANVAS.classList.add('pt-grabbing');
            e.preventDefault();
        });

        window.addEventListener('mousemove', function (e) {
            if (PT_DRAG_STATE.mode === 'pan') {
                var dx = e.clientX - PT_DRAG_STATE.startX;
                var dy = e.clientY - PT_DRAG_STATE.startY;
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) PT_DRAG_STATE.moved = true;
                PT_VIEW.offsetX = PT_DRAG_STATE.startOffsetX - dx;
                PT_VIEW.offsetY = PT_DRAG_STATE.startOffsetY + dy;
                ptClampOffsetX(); ptClampOffsetY();
                ptScheduleDraw(); ptMaybeLoadOlder();
            }
        });
        window.addEventListener('mouseup', function (e) {
            if (PT_DRAG_STATE.mode === 'pan') {
                PT_DRAG_STATE.mode = null;
                PT_CANVAS.classList.remove('pt-grabbing');
            }
        });

        PT_CANVAS.addEventListener('dblclick', function (e) {
            if (!PT_CANDLES.length) return;
            var rect = PT_CANVAS.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var step  = PT_CANVAS._ptStep || ptStep();
            var baseX = PT_CANVAS._ptBaseX || (PT_PADDING_LEFT + PT_VIRTUAL_PAD);
            var idx = Math.floor((x - baseX) / step);
            if (idx < 0 || idx >= PT_CANDLES.length) return;
            ptOpenCandleDetailModal(idx);
        });

        PT_CANVAS.addEventListener('wheel', function (e) {
            e.preventDefault();
            var rect = PT_CANVAS.getBoundingClientRect();
            var mx = e.clientX - rect.left;
            var factor = e.deltaY > 0 ? 0.9 : 1.1;
            var stepBefore = ptStep();
            var baseXBefore = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
            var idxUnderCursor = (mx - baseXBefore) / stepBefore;
            PT_VIEW.candleWidth *= factor;
            PT_VIEW.candleWidth = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, PT_VIEW.candleWidth));
            PT_VIEW.candleSpacing = Math.max(0.4, PT_VIEW.candleWidth * 0.35);
            var stepAfter = ptStep();
            PT_VIEW.offsetX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + idxUnderCursor * stepAfter - mx;
            ptClampOffsetX(); ptScheduleDraw(); ptMaybeLoadOlder();
        }, { passive: false });

        PT_CANVAS.addEventListener('touchstart', function (e) {
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                PT_TOUCH_STATE.pointers[t.identifier] = { x: t.clientX, y: t.clientY };
            }
            ptUpdatePinchBaseline();
            e.preventDefault();
        }, { passive: false });

        PT_CANVAS.addEventListener('touchmove', function (e) {
            e.preventDefault();
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                PT_TOUCH_STATE.pointers[t.identifier] = { x: t.clientX, y: t.clientY };
            }
            var touches = [];
            for (var id in PT_TOUCH_STATE.pointers) touches.push(PT_TOUCH_STATE.pointers[id]);
            if (touches.length === 1) {
                var p = touches[0];
                if (PT_TOUCH_STATE._lastPan) {
                    var dx = p.x - PT_TOUCH_STATE._lastPan.x;
                    var dy = p.y - PT_TOUCH_STATE._lastPan.y;
                    PT_VIEW.offsetX -= dx; PT_VIEW.offsetY += dy;
                    ptClampOffsetX(); ptClampOffsetY();
                    ptScheduleDraw(); ptMaybeLoadOlder();
                }
                PT_TOUCH_STATE._lastPan = { x: p.x, y: p.y };
                PT_TOUCH_STATE.pinchStartDist = 0;
            } else if (touches.length >= 2) {
                var a = touches[0], b = touches[1];
                var dist = Math.hypot(a.x - b.x, a.y - b.y);
                var midX = (a.x + b.x) / 2;
                if (!PT_TOUCH_STATE.pinchStartDist) {
                    PT_TOUCH_STATE.pinchStartDist = dist;
                    PT_TOUCH_STATE.pinchStartCandleWidth = PT_VIEW.candleWidth;
                    PT_TOUCH_STATE.pinchMidX = midX;
                } else {
                    var scale = dist / PT_TOUCH_STATE.pinchStartDist;
                    var newCW = PT_TOUCH_STATE.pinchStartCandleWidth * scale;
                    newCW = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, newCW));
                    var rect = PT_CANVAS.getBoundingClientRect();
                    var mx = midX - rect.left;
                    var stepBefore = ptStep();
                    var baseBefore = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
                    var idxUnder = (mx - baseBefore) / stepBefore;
                    PT_VIEW.candleWidth = newCW;
                    PT_VIEW.candleSpacing = Math.max(0.4, newCW * 0.35);
                    var stepAfter = ptStep();
                    PT_VIEW.offsetX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + idxUnder * stepAfter - mx;
                    ptClampOffsetX(); ptScheduleDraw(); ptMaybeLoadOlder();
                }
                PT_TOUCH_STATE._lastPan = null;
            }
        }, { passive: false });

        PT_CANVAS.addEventListener('touchend', function (e) {
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                delete PT_TOUCH_STATE.pointers[t.identifier];
            }
            PT_TOUCH_STATE.pinchStartDist = 0;
            PT_TOUCH_STATE._lastPan = null;
            ptUpdatePinchBaseline();
        });
        PT_CANVAS.addEventListener('touchcancel', function () {
            PT_TOUCH_STATE.pointers = {};
            PT_TOUCH_STATE.pinchStartDist = 0;
            PT_TOUCH_STATE._lastPan = null;
        });

        PT_CANVAS.addEventListener('mousemove', ptHandleMouseMove);
        PT_CANVAS.addEventListener('mouseleave', ptHandleMouseLeave);
    }

    function ptUpdatePinchBaseline() {
        var ids = Object.keys(PT_TOUCH_STATE.pointers);
        if (ids.length === 1) {
            var p = PT_TOUCH_STATE.pointers[ids[0]];
            PT_TOUCH_STATE._lastPan = { x: p.x, y: p.y };
        } else PT_TOUCH_STATE._lastPan = null;
    }

    // ==================== CLAMPING ====================
    function ptClampOffsetX() {
        var wrap = document.getElementById('ptChartWrap');
        var vw = wrap.clientWidth;
        var totalW = PT_PADDING_LEFT + PT_VIRTUAL_PAD + ptContentW() + PT_VIRTUAL_PAD + PT_PADDING_RIGHT;
        var minOffset = 0;
        var maxOffset = Math.max(0, totalW - vw);
        if (PT_VIEW.offsetX < minOffset) PT_VIEW.offsetX = minOffset;
        if (PT_VIEW.offsetX > maxOffset) PT_VIEW.offsetX = maxOffset;
    }
    function ptClampOffsetY() {
        var wrap = document.getElementById('ptChartWrap');
        var vh = wrap.clientHeight;
        var maxY = vh * 1.2, minY = -vh * 1.2;
        if (PT_VIEW.offsetY < minY) PT_VIEW.offsetY = minY;
        if (PT_VIEW.offsetY > maxY) PT_VIEW.offsetY = maxY;
    }

    // ==================== SCALE BAR ====================
    var PT_SCALE_DRAG = { active: false, startY: 0, startPriceScale: 1.0 };
    function ptAttachScaleBarEvents() {
        var bar = document.getElementById('ptScaleBar');
        if (!bar) return;
        function start(e) {
            PT_SCALE_DRAG.active = true;
            PT_SCALE_DRAG.startY = (e.touches ? e.touches[0].clientY : e.clientY);
            PT_SCALE_DRAG.startPriceScale = PT_VIEW.priceScale;
            bar.classList.add('pt-active');
            e.preventDefault(); e.stopPropagation();
        }
        function move(e) {
            if (!PT_SCALE_DRAG.active) return;
            var y = (e.touches ? e.touches[0].clientY : e.clientY);
            var dy = y - PT_SCALE_DRAG.startY;
            var factor = Math.exp(dy / 300);
            var s = PT_SCALE_DRAG.startPriceScale * factor;
            s = Math.max(0.1, Math.min(10, s));
            PT_VIEW.priceScale = s;
            ptScheduleDraw();
            e.preventDefault(); e.stopPropagation();
        }
        function end() {
            if (!PT_SCALE_DRAG.active) return;
            PT_SCALE_DRAG.active = false;
            bar.classList.remove('pt-active');
        }
        bar.addEventListener('mousedown', start);
        window.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        bar.addEventListener('touchstart', start, { passive: false });
        bar.addEventListener('touchmove', move, { passive: false });
        bar.addEventListener('touchend', end);
        bar.addEventListener('touchcancel', end);
        bar.addEventListener('dblclick', function () { PT_VIEW.priceScale = 1.0; ptScheduleDraw(); });
    }

    // ==================== EXPORTS ====================
    window.ptOpenSymbolModal    = ptOpenSymbolModal;
    window.ptCloseSymbolModal   = ptCloseSymbolModal;
    window.ptFilterSymbolModal  = ptFilterSymbolModal;
    window.ptSelectSymbol       = ptSelectSymbol;
    window.ptSelectTimeframe    = ptSelectTimeframe;
    window.ptOpenCandleDetail   = ptOpenCandleDetailModal;
    window.ptCloseCandleDetail  = ptCloseCandleDetailModal;
</script>

</body>
</html>