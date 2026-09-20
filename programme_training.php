<?php
// programme_training.php — Candlestick chart viewer for a programme
// Multi-root team model: Root #1 is foundation, heirs inherit from existing roots/refs.
// Supports re-projection of a configured higher-TF configuration onto a lower TF.
//
// CHANGES IN THIS VERSION:
//  * Client-side scanner now persists native matches on EVERY chart load
//    (page load, symbol change, timeframe change, candles-modal change).
//  * persist_matches is now a "full snapshot" endpoint: it ALWAYS wipes
//    rows for the current (user, programme, symbol, timeframe) tuple and
//    re-inserts whatever the client sent. Empty payload = table cleaned.
//  * Client tracks a signature of the last persisted snapshot to avoid
//    redundant round-trips when nothing changed.
//  * Persist also fires once full older-candle history has been loaded,
//    so trades whose exit_at is far in the past can resolve.
//  * TIMEFRAME SWITCHING: No modal. Clicking a timeframe directly loads the
//    chart and displays BOTH its native configuration AND all applicable
//    higher-TF projections simultaneously on the same chart.
session_start();

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

// ==================== ACCOUNT MANAGEMENT ====================
$accountManagement = [
    'enable_risk_reward_correction' => 0,
    'minimum_risk_reward'           => '0.00',
    'fixed_risk_reward'             => '0.00',
];
try {
    $am = $pdo->prepare("
        SELECT enable_risk_reward_correction, minimum_risk_reward, fixed_risk_reward
        FROM accountmanagement
        WHERE developerid = ?
        ORDER BY id DESC LIMIT 1
    ");
    $am->execute([$userId]);
    $amRow = $am->fetch(PDO::FETCH_ASSOC);
    if ($amRow) {
        $accountManagement['enable_risk_reward_correction'] = (int)$amRow['enable_risk_reward_correction'];
        $accountManagement['minimum_risk_reward']           = (string)$amRow['minimum_risk_reward'];
        $accountManagement['fixed_risk_reward']             = (string)$amRow['fixed_risk_reward'];
    }
} catch (PDOException $e) { /* defaults */ }

// ==================== CONFIGURATION TREES (multi-root) ====================
$programmeTrees = [];
try {
    if ($programmeId > 0) {
        $cStmt = $pdo->prepare("
            SELECT id, tree_id, row_role, author_id, parent_id,
                   candle_name, price_level, timeframe, candle_type,
                   candle_position, candle_search, operator, order_type,
                   drawing_id, drawing_tools, draw_from, draw_from_price_level,
                   draw_to, draw_to_price_level, drawing_color,
                   entry_from, entry_from_price_level,
                   exit_at, exit_at_price_level,
                   target, target_price_level,
                   authority_source_id, authority_source_role,
                   root_order, evaluation_priority, is_foundation_root,
                   root_ref_count, resolved_root_id, triggered_at
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ?
            ORDER BY tree_id ASC, evaluation_priority ASC, id ASC
        ");
        $cStmt->execute([$userId, $programmeId]);

        $byTree = [];
        while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int)$r['tree_id'];
            if (!isset($byTree[$tid])) {
                $byTree[$tid] = [
                    'tree_id'  => $tid,
                    'roots'    => [],
                    'drawings' => [],
                    'trades'   => [],
                    '_orphans' => []
                ];
            }
            $row = [
                'id'                       => (int)$r['id'],
                'tree_id'                  => $tid,
                'row_role'                 => $r['row_role'],
                'author_id'                => $r['author_id'] !== null ? (int)$r['author_id'] : null,
                'parent_id'                => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
                'candle_name'              => $r['candle_name'],
                'price_level'              => $r['price_level'],
                'timeframe'                => $r['timeframe'],
                'candle_type'              => $r['candle_type'],
                'candle_position'          => $r['candle_position'],
                'candle_search'            => $r['candle_search'],
                'operator'                 => $r['operator'],
                'order_type'               => $r['order_type'],
                'drawing_id'               => $r['drawing_id'] !== null ? (int)$r['drawing_id'] : null,
                'drawing_tools'            => $r['drawing_tools'],
                'draw_from'                => $r['draw_from'],
                'draw_from_price_level'    => $r['draw_from_price_level'],
                'draw_to'                  => $r['draw_to'],
                'draw_to_price_level'      => $r['draw_to_price_level'],
                'drawing_color'            => $r['drawing_color'],
                'entry_from'               => $r['entry_from'],
                'entry_from_price_level'   => $r['entry_from_price_level'],
                'exit_at'                  => $r['exit_at'],
                'exit_at_price_level'      => $r['exit_at_price_level'],
                'target'                   => $r['target'],
                'target_price_level'       => $r['target_price_level'],
                'authority_source_id'      => $r['authority_source_id'] !== null ? (int)$r['authority_source_id'] : null,
                'authority_source_role'    => $r['authority_source_role'],
                'root_order'               => (int)$r['root_order'],
                'evaluation_priority'      => (int)$r['evaluation_priority'],
                'is_foundation_root'       => (int)$r['is_foundation_root'],
                'root_ref_count'           => (int)$r['root_ref_count'],
                'resolved_root_id'         => $r['resolved_root_id'] !== null ? (int)$r['resolved_root_id'] : null,
                'triggered_at'             => $r['triggered_at'],
            ];
            if ($row['row_role'] === 'root') {
                $byTree[$tid]['roots'][] = $row;
            } elseif ($row['row_role'] === 'drawing') {
                $byTree[$tid]['drawings'][] = $row;
            } elseif ($row['row_role'] === 'trade') {
                $byTree[$tid]['trades'][] = $row;
            } else {
                $byTree[$tid]['_orphans'][] = $row;
            }
        }

        foreach ($byTree as $tid => &$tree) {
            $roots   = &$tree['roots'];
            $orphans = isset($tree['_orphans']) ? $tree['_orphans'] : [];

            $refsByRoot = [];
            foreach ($orphans as $row) {
                if ($row['row_role'] === 'root_ref') {
                    $row['re_ref_pairs'] = [];
                    $refsByRoot[$row['parent_id']][] = $row;
                }
            }

            foreach ($orphans as $row) {
                if ($row['row_role'] === 're_ref_author' && $row['parent_id'] !== null) {
                    foreach ($refsByRoot as $rootId => &$refList) {
                        foreach ($refList as &$ref) {
                            if ($ref['id'] === $row['parent_id']) {
                                $servant = null;
                                foreach ($orphans as $cand) {
                                    if ($cand['row_role'] === 're_ref_servant'
                                        && $cand['parent_id'] === $row['id']) {
                                        $servant = $cand; break;
                                    }
                                }
                                if ($servant) {
                                    $ref['re_ref_pairs'][] = [
                                        'author'     => $row,
                                        'referenced' => $servant,
                                    ];
                                }
                            }
                        }
                        unset($ref);
                    }
                }
            }

            foreach ($roots as &$root) {
                $root['root_refs'] = isset($refsByRoot[$root['id']])
                    ? $refsByRoot[$root['id']] : [];
            }
            unset($root);

            unset($tree['_orphans']);
        }
        unset($tree);

        foreach ($byTree as &$tree) {
            usort($tree['roots'], function ($a, $b) {
                $pa = (int)$a['evaluation_priority']; if ($pa <= 0) $pa = 1;
                $pb = (int)$b['evaluation_priority']; if ($pb <= 0) $pb = 1;
                return $pa - $pb;
            });
        }
        unset($tree);

        $programmeTrees = array_values($byTree);
    }
} catch (PDOException $e) { $programmeTrees = []; }

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

// ==================== AJAX: CANDLE BOUNDS (min/max time) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles_bounds'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
    }

    $symbol    = trim((string)($_POST['symbol'] ?? ''));
    $timeframe = trim((string)($_POST['timeframe'] ?? ''));
    if ($symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'symbol and timeframe required.']); exit;
    }

    try {
        $q = $pdo->prepare("
            SELECT MIN(candle_time) AS min_time, MAX(candle_time) AS max_time, COUNT(*) AS total
            FROM candle_records
            WHERE userid=? AND symbol=? AND timeframe=?
        ");
        $q->execute([$userId, $symbol, $timeframe]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success'  => true,
            'min_time' => $row ? $row['min_time'] : null,
            'max_time' => $row ? $row['max_time'] : null,
            'total'    => $row ? (int)$row['total'] : 0,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage()]);
    }
    exit;
}

// ==================== AJAX: FETCH RECENT CANDLES (most recent N) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles_recent'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
    }

    $symbol    = trim((string)($_POST['symbol'] ?? ''));
    $timeframe = trim((string)($_POST['timeframe'] ?? ''));
    $limit     = isset($_POST['limit']) ? max(1, min(20000, (int)$_POST['limit'])) : 5000;

    if ($symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'symbol and timeframe required.','candles'=>[]]); exit;
    }

    try {
        $q = $pdo->prepare("
            SELECT id, candle_time, open_time, close_time,
                   open, high, low, close,
                   candle_center, body_center,
                   high_wick_center, low_wick_center, candle_width_center,
                   volume
            FROM candle_records
            WHERE userid=? AND symbol=? AND timeframe=?
            ORDER BY candle_time DESC LIMIT $limit
        ");
        $q->execute([$userId, $symbol, $timeframe]);

        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $rowsAsc = array_reverse($rows);

        $candles = [];
        foreach ($rowsAsc as $r) {
            $candles[] = [
                'id'                  => (int)$r['id'],
                'time'                => $r['candle_time'],
                'open_time'           => $r['open_time'],
                'close_time'          => $r['close_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'              => $r['volume']              !== null ? (float)$r['volume']              : null,
            ];
        }

        echo json_encode(['success'=>true, 'candles'=>$candles, 'count'=>count($candles)]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false, 'message'=>'Query failed: '.$e->getMessage(), 'candles'=>[]]);
    }
    exit;
}

// ==================== AJAX: PERSIST MATCHES ====================
// Full-snapshot semantics: for the (user, programme, symbol, timeframe)
// tuple we ALWAYS delete existing rows, then insert what the client sent.
// If the client sent an empty `trees` array, the table ends up clean —
// this is the self-cleansing behaviour we want.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['persist_matches'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
    }

    $raw = (string)($_POST['payload'] ?? '');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        echo json_encode(['success'=>false,'message'=>'Invalid payload.']); exit;
    }

    $programmeId = (int)($payload['programmeId'] ?? 0);
    $symbol      = trim((string)($payload['symbol'] ?? ''));
    $timeframe   = trim((string)($payload['timeframe'] ?? ''));
    $runToken    = trim((string)($payload['run_token'] ?? ''));
    $trees       = is_array($payload['trees'] ?? null) ? $payload['trees'] : [];

    if ($programmeId <= 0 || $symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'programmeId, symbol and timeframe are required.']); exit;
    }
    if ($runToken === '') {
        $runToken = bin2hex(random_bytes(16));
    }

    // Guard: ensure the programme actually belongs to this user.
    try {
        $owner = $pdo->prepare("SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1");
        $owner->execute([$programmeId, $userId]);
        if (!$owner->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success'=>false,'message'=>'Programme not found for this user.']); exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Ownership check failed: '.$e->getMessage()]); exit;
    }

    // Pre-scan the payload so we can short-circuit the whole transaction
    // when there is genuinely nothing to insert. We still want the DELETE
    // to run in that case, to enforce "self-cleansing".
    $totalRows = 0;
    foreach ($trees as $tree) {
        $matched = is_array($tree['matched'] ?? null) ? $tree['matched'] : [];
        $totalRows += count($matched);
    }

    try {
        $pdo->beginTransaction();

        $del = $pdo->prepare("
            DELETE FROM programme_candles_configuration
            WHERE userid = ? AND programmeid = ? AND symbol = ? AND timeframe = ?
        ");
        $del->execute([$userId, $programmeId, $symbol, $timeframe]);
        $deleted = $del->rowCount();

        $insertedRows = 0;

        if ($totalRows > 0) {
            $ins = $pdo->prepare("
                INSERT INTO programme_candles_configuration
                    (userid, programmeid, tree_id, run_token, row_role, parent_id, author_id, source_row_id,
                     candle_record_id, symbol, timeframe, candle_time, open_time, close_time,
                     open, high, low, close,
                     candle_center, body_center, high_wick_center, low_wick_center, candle_width_center, volume,
                     candle_name, price_level, candle_type, candle_position, candle_search, operator, order_type,
                     drawing_id, drawing_tools, draw_from, draw_from_price_level,
                     draw_to, draw_to_price_level, drawing_color,
                     draw_from_price, draw_to_price, draw_to_candle_time, draw_to_candle_id,
                     entry_from, entry_from_price_level, exit_at, exit_at_price_level,
                     target, target_price_level,
                     entry_price, exit_price, target_price,
                     resolved_price, resolved_direction, resolved_risk, resolved_reward, resolved_ratio,
                     outcome_status, outcome_candle_time, outcome_price,
                     is_foundation, evaluation_priority, status, triggered_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?)
            ");

            $now = date('Y-m-d H:i:s');

            foreach ($trees as $tree) {
                $treeId  = (int)($tree['tree_id'] ?? 0);
                $matched = is_array($tree['matched'] ?? null) ? $tree['matched'] : [];

                foreach ($matched as $m) {
                    $role = (string)($m['row_role'] ?? 'matched_candle');

                    $ins->execute([
                        $userId, $programmeId, $treeId, $runToken, $role,
                        isset($m['parent_id'])     ? (int)$m['parent_id']     : null,
                        isset($m['author_id'])     ? (int)$m['author_id']     : null,
                        isset($m['source_row_id']) ? (int)$m['source_row_id'] : null,

                        isset($m['candle_record_id']) ? (int)$m['candle_record_id'] : null,
                        $symbol, $timeframe,
                        $m['candle_time']  ?? null,
                        $m['open_time']    ?? null,
                        $m['close_time']   ?? null,

                        $m['open']  ?? null,
                        $m['high']  ?? null,
                        $m['low']   ?? null,
                        $m['close'] ?? null,

                        $m['candle_center']       ?? null,
                        $m['body_center']         ?? null,
                        $m['high_wick_center']    ?? null,
                        $m['low_wick_center']     ?? null,
                        $m['candle_width_center'] ?? null,
                        $m['volume']              ?? null,

                        $m['candle_name']     ?? null,
                        $m['price_level']     ?? null,
                        $m['candle_type']     ?? null,
                        $m['candle_position'] ?? null,
                        $m['candle_search']   ?? null,
                        $m['operator']        ?? null,
                        $m['order_type']      ?? null,

                        isset($m['drawing_id']) ? (int)$m['drawing_id'] : null,
                        $m['drawing_tools']         ?? null,
                        $m['draw_from']             ?? null,
                        $m['draw_from_price_level'] ?? null,
                        $m['draw_to']               ?? null,
                        $m['draw_to_price_level']   ?? null,
                        $m['drawing_color']         ?? null,

                        $m['draw_from_price']     ?? null,
                        $m['draw_to_price']       ?? null,
                        $m['draw_to_candle_time'] ?? null,
                        isset($m['draw_to_candle_id']) ? (int)$m['draw_to_candle_id'] : null,

                        $m['entry_from']             ?? null,
                        $m['entry_from_price_level'] ?? null,
                        $m['exit_at']                ?? null,
                        $m['exit_at_price_level']    ?? null,
                        $m['target']                 ?? null,
                        $m['target_price_level']     ?? null,

                        $m['entry_price']  ?? null,
                        $m['exit_price']   ?? null,
                        $m['target_price'] ?? null,

                        $m['resolved_price']     ?? null,
                        isset($m['resolved_direction']) ? (int)$m['resolved_direction'] : 0,
                        $m['resolved_risk']      ?? null,
                        $m['resolved_reward']    ?? null,
                        $m['resolved_ratio']     ?? null,

                        $m['outcome_status']      ?? null,
                        $m['outcome_candle_time'] ?? null,
                        $m['outcome_price']       ?? null,

                        !empty($m['is_foundation']) ? 1 : 0,
                        isset($m['evaluation_priority']) ? (int)$m['evaluation_priority'] : 1,
                        $m['status'] ?? 'matched',
                        $m['triggered_at'] ?? $now,
                    ]);
                    $insertedRows++;
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'success'   => true,
            'run_token' => $runToken,
            'deleted'   => (int)$deleted,
            'inserted'  => (int)$insertedRows,
            'empty'     => ($totalRows === 0),
        ]);
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success'=>false, 'message'=>$ex->getMessage()]);
    }
    exit;
}

// ==================== AJAX: FETCH PERSISTED MATCHES ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_persisted_matches'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
    }

    $programmeId = isset($_POST['programmeid']) ? (int)$_POST['programmeid'] : 0;
    $symbol      = trim((string)($_POST['symbol'] ?? ''));
    $timeframe   = trim((string)($_POST['timeframe'] ?? ''));

    if ($programmeId <= 0 || $symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'programmeid, symbol and timeframe required.']); exit;
    }

    try {
        $q = $pdo->prepare("
            SELECT id, tree_id, run_token, row_role, parent_id, author_id, source_row_id,
                   candle_record_id, symbol, timeframe, candle_time, open_time, close_time,
                   open, high, low, close,
                   candle_center, body_center, high_wick_center, low_wick_center, candle_width_center, volume,
                   candle_name, price_level, candle_type, candle_position, candle_search, operator, order_type,
                   drawing_id, drawing_tools, draw_from, draw_from_price_level,
                   draw_to, draw_to_price_level, drawing_color,
                   draw_from_price, draw_to_price, draw_to_candle_time, draw_to_candle_id,
                   entry_from, entry_from_price_level, exit_at, exit_at_price_level,
                   target, target_price_level,
                   entry_price, exit_price, target_price,
                   resolved_price, resolved_direction, resolved_risk, resolved_reward, resolved_ratio,
                   outcome_status, outcome_candle_time, outcome_price,
                   is_foundation, evaluation_priority, status, triggered_at, created_at
            FROM programme_candles_configuration
            WHERE userid = ? AND programmeid = ? AND symbol = ? AND timeframe = ?
            ORDER BY tree_id ASC, evaluation_priority ASC, id ASC
        ");
        $q->execute([$userId, $programmeId, $symbol, $timeframe]);

        $rows = [];
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'id'                    => (int)$r['id'],
                'tree_id'               => (int)$r['tree_id'],
                'run_token'             => $r['run_token'],
                'row_role'              => $r['row_role'],
                'parent_id'             => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
                'author_id'             => $r['author_id'] !== null ? (int)$r['author_id'] : null,
                'source_row_id'         => $r['source_row_id'] !== null ? (int)$r['source_row_id'] : null,
                'candle_record_id'      => $r['candle_record_id'] !== null ? (int)$r['candle_record_id'] : null,
                'symbol'                => $r['symbol'],
                'timeframe'             => $r['timeframe'],
                'candle_time'           => $r['candle_time'],
                'open_time'             => $r['open_time'],
                'close_time'            => $r['close_time'],
                'open'                  => $r['open']  !== null ? (float)$r['open']  : null,
                'high'                  => $r['high']  !== null ? (float)$r['high']  : null,
                'low'                   => $r['low']   !== null ? (float)$r['low']   : null,
                'close'                 => $r['close'] !== null ? (float)$r['close'] : null,
                'candle_center'         => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'           => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'      => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'       => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center'   => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'                => $r['volume']              !== null ? (float)$r['volume']              : null,
                'candle_name'           => $r['candle_name'],
                'price_level'           => $r['price_level'],
                'candle_type'           => $r['candle_type'],
                'candle_position'       => $r['candle_position'],
                'candle_search'         => $r['candle_search'],
                'operator'              => $r['operator'],
                'order_type'            => $r['order_type'],
                'drawing_id'            => $r['drawing_id'] !== null ? (int)$r['drawing_id'] : null,
                'drawing_tools'         => $r['drawing_tools'],
                'draw_from'             => $r['draw_from'],
                'draw_from_price_level' => $r['draw_from_price_level'],
                'draw_to'               => $r['draw_to'],
                'draw_to_price_level'   => $r['draw_to_price_level'],
                'drawing_color'         => $r['drawing_color'],
                'draw_from_price'       => $r['draw_from_price'] !== null ? (float)$r['draw_from_price'] : null,
                'draw_to_price'         => $r['draw_to_price']   !== null ? (float)$r['draw_to_price']   : null,
                'draw_to_candle_time'   => $r['draw_to_candle_time'],
                'draw_to_candle_id'     => $r['draw_to_candle_id'] !== null ? (int)$r['draw_to_candle_id'] : null,
                'entry_from'            => $r['entry_from'],
                'entry_from_price_level'=> $r['entry_from_price_level'],
                'exit_at'               => $r['exit_at'],
                'exit_at_price_level'   => $r['exit_at_price_level'],
                'target'                => $r['target'],
                'target_price_level'    => $r['target_price_level'],
                'entry_price'           => $r['entry_price']   !== null ? (float)$r['entry_price']   : null,
                'exit_price'            => $r['exit_price']    !== null ? (float)$r['exit_price']    : null,
                'target_price'          => $r['target_price']  !== null ? (float)$r['target_price']  : null,
                'resolved_price'        => $r['resolved_price']   !== null ? (float)$r['resolved_price']   : null,
                'resolved_direction'    => (int)$r['resolved_direction'],
                'resolved_risk'         => $r['resolved_risk']    !== null ? (float)$r['resolved_risk']    : null,
                'resolved_reward'       => $r['resolved_reward']  !== null ? (float)$r['resolved_reward']  : null,
                'resolved_ratio'        => $r['resolved_ratio']   !== null ? (float)$r['resolved_ratio']   : null,
                'outcome_status'        => $r['outcome_status'],
                'outcome_candle_time'   => $r['outcome_candle_time'],
                'outcome_price'         => $r['outcome_price'] !== null ? (float)$r['outcome_price'] : null,
                'is_foundation'         => (int)$r['is_foundation'],
                'evaluation_priority'   => (int)$r['evaluation_priority'],
                'status'                => $r['status'],
                'triggered_at'          => $r['triggered_at'],
                'created_at'            => $r['created_at'],
            ];
        }

        echo json_encode(['success'=>true, 'rows'=>$rows]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false, 'message'=>'Query failed: '.$e->getMessage()]);
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
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
                FROM candle_records
                WHERE userid=? AND symbol=? AND timeframe=? AND candle_time < ?
                ORDER BY candle_time DESC LIMIT $limit
            ");
            $q->execute([$userId, $symbol, $timeframe, $before]);
        } else {
            $q = $pdo->prepare("
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
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
                'id'                  => (int)$r['id'],
                'time'                => $r['candle_time'],
                'open_time'           => $r['open_time'],
                'close_time'          => $r['close_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'              => $r['volume']              !== null ? (float)$r['volume']              : null,
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

// ==================== AJAX: FETCH CANDLES IN RANGE (bulk) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles_range'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit;
    }

    $symbol     = trim((string)($_POST['symbol'] ?? ''));
    $timeframe  = trim((string)($_POST['timeframe'] ?? ''));
    $fromTime   = trim((string)($_POST['from_time'] ?? ''));
    $toTime     = trim((string)($_POST['to_time'] ?? ''));
    $limit      = isset($_POST['limit']) ? max(1, min(100000, (int)$_POST['limit'])) : 100000;

    if ($symbol === '' || $timeframe === '' || $fromTime === '' || $toTime === '') {
        echo json_encode(['success'=>false,'message'=>'symbol, timeframe, from_time, to_time required.','candles'=>[]]); exit;
    }

    function ptNormTime($t) {
        $s = str_replace('T', ' ', trim((string)$t));
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $s, $m)) return $m[1];
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/', $s, $m)) return $m[1] . ':00';
        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $s, $m)) return $m[1] . ' 00:00:00';
        return $s;
    }
    $fromTime = ptNormTime($fromTime);
    $toTime   = ptNormTime($toTime);

    try {
        $q = $pdo->prepare("
            SELECT id, candle_time, open_time, close_time,
                   open, high, low, close,
                   candle_center, body_center,
                   high_wick_center, low_wick_center, candle_width_center,
                   volume
            FROM candle_records
            WHERE userid=? AND symbol=? AND timeframe=?
              AND candle_time >= ? AND candle_time < ?
            ORDER BY candle_time ASC LIMIT $limit
        ");
        $q->execute([$userId, $symbol, $timeframe, $fromTime, $toTime]);

        $candles = [];
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $candles[] = [
                'id'                  => (int)$r['id'],
                'time'                => $r['candle_time'],
                'open_time'           => $r['open_time'],
                'close_time'          => $r['close_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'              => $r['volume']              !== null ? (float)$r['volume']              : null,
            ];
        }

        echo json_encode([
            'success'  => true,
            'candles'  => $candles,
            'count'    => count($candles),
            'from'     => $fromTime,
            'to'       => $toTime,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false, 'message'=>'Query failed: '.$e->getMessage(), 'candles'=>[]]);
    }
    exit;
}

// ==================== AJAX: FETCH TRADE DETAILS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_trade_details'])) {
    header('Content-Type: application/json');

    $payload = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($payload) || empty($payload['trades'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload.']); exit;
    }

    $symbol    = trim((string)($payload['symbol'] ?? ''));
    $timeframe = trim((string)($payload['timeframe'] ?? ''));
    if ($symbol === '' || $timeframe === '') {
        echo json_encode(['success' => false, 'message' => 'Symbol/timeframe required.']); exit;
    }

    try {
        $q = $pdo->prepare("
            SELECT candle_time, open, high, low, close,
                   candle_center, body_center,
                   high_wick_center, low_wick_center, candle_width_center
            FROM candle_records
            WHERE userid=? AND symbol=? AND timeframe=?
            ORDER BY candle_time DESC LIMIT 500
        ");
        $q->execute([$userId, $symbol, $timeframe]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $candles = [];
        foreach ($rows as $r) {
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
        echo json_encode(['success' => true, 'candles' => $candles]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]);
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

    .pt-trades-btn { position: relative; }
    .pt-trades-count { background: #27ae60; color: #fff; border-radius: 10px;
        font-size: 0.68rem; font-weight: 800; padding: 1px 6px; margin-left: 4px; }

    .pt-trades-modal { position: fixed; inset: 0; z-index: 10002; display: none;
        align-items: center; justify-content: center;
        background: rgba(0,0,0,0.6);
        backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
        padding: 20px; box-sizing: border-box; }
    .pt-trades-modal.active { display: flex; }
    .pt-trades-box { width: 100%; max-width: 640px; max-height: 90vh;
        background: var(--bg-card, #fff); color: var(--text, #222);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 14px; box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        display: flex; flex-direction: column; overflow: hidden;
        font-family: inherit; }
    .pt-trades-head { display: flex; align-items: flex-start; justify-content: space-between;
        padding: 14px 18px 12px; border-bottom: 1px solid var(--border-color, #e0e0e0);
        background: rgba(39,174,96,0.06); flex-shrink: 0; }
    .pt-trades-title { margin: 0; font-size: 1rem; font-weight: 700; color: #27ae60; }
    .pt-trades-sub { margin: 3px 0 0; font-size: 0.78rem; color: var(--text-muted, #888); }
    .pt-trades-close { border: 1px solid transparent; background: transparent;
        color: var(--text-muted, #888); width: 32px; height: 32px; border-radius: 8px;
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
    .pt-trades-close:hover { background: rgba(128,128,128,0.15); color: var(--text, #222); }
    .pt-trades-body { padding: 0; overflow-y: auto; flex: 1; }
    .pt-trades-results { padding: 12px 16px 16px; }
    .pt-trades-tree { margin-bottom: 10px; border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 10px; background: var(--bg, #fafafa); overflow: hidden; }
    .pt-trades-tree-head { display: flex; align-items: center; gap: 8px;
        padding: 10px 12px; cursor: pointer; user-select: none;
        background: rgba(46,139,87,0.06); }
    .pt-trades-tree-head:hover { background: rgba(46,139,87,0.1); }
    .pt-trades-tree-caret { font-size: 0.7rem; transition: transform 0.18s ease; color: #27ae60; }
    .pt-trades-tree[data-open="1"] .pt-trades-tree-caret { transform: rotate(90deg); }
    .pt-trades-tree-icon { color: #27ae60; font-size: 0.95rem; }
    .pt-trades-tree-name { font-weight: 800; font-size: 0.85rem; color: var(--accent, #2e8b57);
        text-transform: uppercase; letter-spacing: 0.4px; }
    .pt-trades-tree-count { font-size: 0.72rem; color: var(--text-muted, #888); margin-left: auto; font-weight: 700; }
    .pt-trades-tree-body { display: none; padding: 8px 12px 12px; }
    .pt-trades-tree[data-open="1"] .pt-trades-tree-body { display: block; }
    .pt-trade-summary { font-size: 0.8rem; color: var(--text-muted, #666);
        margin-bottom: 10px; padding: 8px 10px; border-radius: 8px;
        background: rgba(46,139,87,0.06); line-height: 1.5; }
    .pt-trade-summary b { color: var(--text, #222); }
    .pt-trade-card { border: 1px solid var(--border-color, #e0e0e0); border-left: 3px solid #27ae60;
        border-radius: 10px; background: var(--bg-card, #fff);
        padding: 10px 12px; margin-bottom: 8px; }
    .pt-trade-card-head { display: flex; align-items: center; justify-content: space-between;
        gap: 8px; margin-bottom: 6px; }
    .pt-trade-card-title { font-weight: 800; font-size: 0.82rem; color: #27ae60; }
    .pt-trade-order-badge { font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
        padding: 2px 8px; border-radius: 10px; letter-spacing: 0.4px;
        background: rgba(39,174,96,0.15); color: #27ae60; }
    .pt-trade-order-badge.buy { background: rgba(46,204,113,0.15); color: #27ae60; }
    .pt-trade-order-badge.sell { background: rgba(231,76,60,0.15); color: #c0392b; }
    .pt-trade-order-badge.buy_stop, .pt-trade-order-badge.buy_limit { background: rgba(46,204,113,0.15); color: #27ae60; }
    .pt-trade-order-badge.sell_stop, .pt-trade-order-badge.sell_limit { background: rgba(231,76,60,0.15); color: #c0392b; }
    .pt-trade-kv { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 10px;
        font-size: 0.78rem; margin-bottom: 6px; }
    .pt-trade-kv .k { color: var(--text-muted, #888); font-weight: 700; font-size: 0.7rem; text-transform: uppercase; }
    .pt-trade-kv .v { color: var(--text, #222); font-weight: 600; }
    .pt-trade-candles { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border-color, #e0e0e0); }
    .pt-trade-candles-title { font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 0.4px; color: var(--text-muted, #888); margin-bottom: 4px; }
    .pt-trade-candle-row { display: grid; grid-template-columns: 1.4fr repeat(4, 1fr);
        gap: 4px; font-size: 0.74rem; padding: 3px 0; font-variant-numeric: tabular-nums;
        border-bottom: 1px dotted var(--border-color, #eee); }
    .pt-trade-candle-row:last-child { border-bottom: none; }
    .pt-trade-candle-row .head { color: var(--text-muted, #888); font-weight: 700; font-size: 0.68rem; text-transform: uppercase; }
    .pt-trade-candle-row .up { color: #27ae60; font-weight: 700; }
    .pt-trade-candle-row .dn { color: #c0392b; font-weight: 700; }
    .pt-trades-empty { text-align: center; color: var(--text-muted, #888);
        font-size: 0.85rem; padding: 30px 10px; }
    body.dark-mode .pt-trades-box { background: var(--bg-card, #1e1e2a); border-color: var(--border-color, #333); }
    body.dark-mode .pt-trades-head { background: rgba(39,174,96,0.12); border-bottom-color: var(--border-color, #333); }
    body.dark-mode .pt-trades-tree { background: var(--bg, #171722); border-color: var(--border-color, #333); }
    body.dark-mode .pt-trades-tree-head { background: rgba(46,139,87,0.12); }
    body.dark-mode .pt-trade-card { background: var(--bg-card, #1e1e2a); border-color: var(--border-color, #333); }
    body.dark-mode .pt-trade-kv .v { color: var(--text, #eee); }

    .pt-debug-modal { position: fixed; inset: 0; z-index: 10003; display: none;
        align-items: center; justify-content: center;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
        padding: 20px; box-sizing: border-box; }
    .pt-debug-modal.active { display: flex; }
    .pt-debug-box { width: 100%; max-width: 780px; max-height: 88vh;
        background: #0e1116; color: #d8e0ea; border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        display: flex; flex-direction: column; overflow: hidden;
        font-family: 'SF Mono', 'Fira Code', Menlo, Consolas, monospace; }
    .pt-debug-head { display: flex; align-items: center; justify-content: space-between;
        padding: 10px 14px; background: #161b22; border-bottom: 1px solid #2a3140; flex-shrink: 0; }
    .pt-debug-title { margin: 0; font-size: 0.9rem; font-weight: 700; color: #58d68d; }
    .pt-debug-title i { margin-right: 6px; }
    .pt-debug-actions { display: flex; gap: 6px; align-items: center; }
    .pt-debug-btn { border: 1px solid #30363d; background: #21262d; color: #c9d1d9;
        font-family: inherit; font-size: 0.72rem; padding: 5px 10px; border-radius: 6px; cursor: pointer; }
    .pt-debug-btn:hover { background: #30363d; }
    .pt-debug-close { border: 1px solid transparent; background: transparent; color: #8b949e;
        width: 28px; height: 28px; border-radius: 6px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; }
    .pt-debug-close:hover { background: #21262d; color: #d8e0ea; }
    .pt-debug-body { flex: 1; overflow-y: auto; padding: 8px 0; font-size: 0.75rem; line-height: 1.5; }
    .pt-debug-line { padding: 3px 14px; border-bottom: 1px solid #161b22; white-space: pre-wrap;
        word-break: break-word; }
    .pt-debug-line .ts { color: #6e7681; margin-right: 8px; }
    .pt-debug-line .lvl { font-weight: 800; margin-right: 8px; text-transform: uppercase; font-size: 0.68rem; }
    .pt-debug-line.lvl-info  .lvl { color: #58a6ff; }
    .pt-debug-line.lvl-warn  .lvl { color: #e3b341; }
    .pt-debug-line.lvl-error .lvl { color: #f85149; }
    .pt-debug-line.lvl-net   .lvl { color: #a371f7; }
    .pt-debug-line.lvl-cfg   .lvl { color: #58d68d; }
    .pt-debug-line.lvl-data  .lvl { color: #79c0ff; }
    .pt-debug-payload { margin: 4px 0 6px 22px; padding: 6px 8px; background: #161b22;
        border-radius: 6px; border-left: 2px solid #30363d;
        color: #8b949e; font-size: 0.72rem; overflow-x: auto; white-space: pre-wrap; }
    .pt-debug-body::-webkit-scrollbar { width: 10px; }
    .pt-debug-body::-webkit-scrollbar-thumb { background: #30363d; border-radius: 6px; }
    .pt-debug-body::-webkit-scrollbar-track { background: #0e1116; }

    .pt-trades-summary-card {
        background: linear-gradient(135deg, rgba(39,174,96,0.10), rgba(39,174,96,0.03));
        border: 1px solid rgba(39,174,96,0.35);
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 14px;
        box-shadow: 0 2px 10px rgba(39,174,96,0.08);
    }
    .pt-tsc-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }
    .pt-tsc-cell {
        display: flex;
        flex-direction: column;
        gap: 3px;
        padding: 8px 10px;
        border-radius: 8px;
        background: rgba(255,255,255,0.5);
    }
    body.dark-mode .pt-tsc-cell { background: rgba(255,255,255,0.05); }
    .pt-tsc-label {
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--text-muted, #888);
    }
    .pt-tsc-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: #27ae60;
        font-variant-numeric: tabular-nums;
    }
    .pt-tsc-win   .pt-tsc-value { color: #2ecc71; }
    .pt-tsc-loss  .pt-tsc-value { color: #c0392b; }
    .pt-tsc-pending .pt-tsc-value { color: #e3b341; }
    body.dark-mode .pt-trades-summary-card {
        background: linear-gradient(135deg, rgba(39,174,96,0.18), rgba(39,174,96,0.06));
        border-color: rgba(39,174,96,0.5);
    }

    .pt-longshort-btn .pt-ls-state {
        background: rgba(128,128,128,0.25); color: var(--text-muted, #888);
        border-radius: 10px; font-size: 0.68rem; font-weight: 800;
        padding: 1px 7px; margin-left: 4px; letter-spacing: 0.5px;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .pt-longshort-btn.pt-ls-on .pt-ls-state {
        background: #27ae60; color: #fff;
    }
    .pt-longshort-btn.pt-ls-on i { color: #27ae60; }

    .pt-tsc-danger .pt-tsc-value { color: #e74c3c; }
    .pt-tsc-info   .pt-tsc-value { color: #4a7bd8; }
    .pt-tsc-sub {
        display: block;
        font-size: 0.64rem;
        font-weight: 700;
        color: var(--text-muted, #888);
        letter-spacing: 0.2px;
        margin-top: 2px;
        line-height: 1.3;
    }
    body.dark-mode .pt-tsc-sub { color: var(--text-muted, #aaa); }
    .pt-tsc-winrate-value { color: #ffffff !important; }
    body:not(.dark-mode) .pt-tsc-winrate-value { color: #222 !important; }
    .pt-tsc-days {
        font-weight: 600;
        font-style: italic;
        letter-spacing: 0.15px;
        opacity: 0.85;
        margin-top: 4px;
    }

    .pt-proj-banner {
        position: absolute;
        top: 70px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9;
        display: none;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 20px;
        background: rgba(39,174,96,0.92);
        color: #fff;
        font: 700 0.75rem system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        letter-spacing: 0.3px;
        box-shadow: 0 3px 14px rgba(0,0,0,0.2);
        pointer-events: none;
        white-space: nowrap;
    }
    .pt-proj-banner.active { display: inline-flex; }
    .pt-proj-banner i { font-size: 0.8rem; }
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
            <span>Candles</span>
        </button>

        <button type="button" class="pt-cfg-btn" id="ptCfgBtn" title="Programme Configuration">
            <i class="fa-solid fa-sliders"></i>
            <span>Configuration</span>
        </button>

        <button type="button" class="pt-cfg-btn pt-trades-btn" id="ptTradesBtn" title="View Trades" style="display:none;">
            <i class="fa-solid fa-money-bill-trend-up"></i>
            <span>Trades</span>
            <span class="pt-trades-count" id="ptTradesCount">0</span>
        </button>

        <button type="button" class="pt-cfg-btn pt-longshort-btn" id="ptLongShortBtn" title="Toggle Long/Short drawing" style="display:none;">
            <i class="fa-solid fa-arrows-up-down"></i>
            <span>Long/Short</span>
            <span class="pt-ls-state" id="ptLongShortState">OFF</span>
        </button>

        <button type="button" class="pt-cfg-btn" id="ptDebugBtn" title="Open Debug Console">
            <i class="fa-solid fa-bug"></i>
            <span>Debug</span>
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

        <div class="pt-proj-banner" id="ptProjBanner">
            <i class="fa-solid fa-layer-group"></i>
            <span id="ptProjBannerText">Projection mode active</span>
        </div>

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

    <div id="ptTradesModal" class="pt-trades-modal">
        <div class="pt-trades-box">
            <div class="pt-trades-head">
                <div>
                    <h3 class="pt-trades-title"><i class="fa-solid fa-money-bill-trend-up"></i> Trades</h3>
                    <p class="pt-trades-sub" id="ptTradesSubtitle">—</p>
                </div>
                <button type="button" class="pt-trades-close" id="ptTradesClose" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="pt-trades-body" id="ptTradesBody"></div>
        </div>
    </div>

    <div id="ptDebugModal" class="pt-debug-modal">
        <div class="pt-debug-box">
            <div class="pt-debug-head">
                <h3 class="pt-debug-title"><i class="fa-solid fa-bug"></i> Debug Console</h3>
                <div class="pt-debug-actions">
                    <button type="button" class="pt-debug-btn" id="ptDebugClear">Clear</button>
                    <button type="button" class="pt-debug-btn" id="ptDebugCopy">Copy</button>
                    <button type="button" class="pt-debug-close" id="ptDebugClose" title="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="pt-debug-body" id="ptDebugBody"></div>
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

    var PT_TREES             = <?= json_encode(array_values($programmeTrees)) ?>;

    var PT_ACCOUNT_MGMT      = <?= json_encode($accountManagement) ?>;

    var PT_DEFAULT_AMOUNT    = 2000;

    // ==================== DEBUG CONSOLE ====================
    var PT_DEBUG_LINES = [];
    var PT_DEBUG_MAX   = 800;

    function ptDebugLine(level, msg, payload) {
        var ts = new Date().toLocaleTimeString();
        var entry = { ts: ts, level: level, msg: String(msg == null ? '' : msg), payload: payload };
        PT_DEBUG_LINES.push(entry);
        if (PT_DEBUG_LINES.length > PT_DEBUG_MAX) PT_DEBUG_LINES.shift();
        ptDebugRenderLine(entry);
    }
    window.pcDebug = function (level, msg, payload) { ptDebugLine(level, msg, payload); };

    function ptDebugRenderLine(entry) {
        var body = document.getElementById('ptDebugBody');
        if (!body) return;
        if (!document.getElementById('ptDebugModal').classList.contains('active') && PT_DEBUG_LINES.length > 5) {
            return;
        }
        var div = document.createElement('div');
        div.className = 'pt-debug-line lvl-' + (entry.level || 'info');
        var ts = '<span class="ts">' + entry.ts + '</span>';
        var lvl = '<span class="lvl">' + (entry.level || 'info') + '</span>';
        var msg = document.createElement('span');
        msg.textContent = entry.msg;
        div.innerHTML = ts + lvl;
        div.appendChild(msg);
        body.appendChild(div);
        if (entry.payload !== undefined) {
            try {
                var pre = document.createElement('div');
                pre.className = 'pt-debug-payload';
                pre.textContent = JSON.stringify(entry.payload, null, 2);
                body.appendChild(pre);
            } catch (e) { /* ignore */ }
        }
        body.scrollTop = body.scrollHeight;
    }
    function ptDebugRenderAll() {
        var body = document.getElementById('ptDebugBody');
        if (!body) return;
        body.innerHTML = '';
        PT_DEBUG_LINES.forEach(function (entry) {
            var div = document.createElement('div');
            div.className = 'pt-debug-line lvl-' + (entry.level || 'info');
            var ts = '<span class="ts">' + entry.ts + '</span>';
            var lvl = '<span class="lvl">' + (entry.level || 'info') + '</span>';
            var msg = document.createElement('span');
            msg.textContent = entry.msg;
            div.innerHTML = ts + lvl;
            div.appendChild(msg);
            body.appendChild(div);
            if (entry.payload !== undefined) {
                try {
                    var pre = document.createElement('div');
                    pre.className = 'pt-debug-payload';
                    pre.textContent = JSON.stringify(entry.payload, null, 2);
                    body.appendChild(pre);
                } catch (e) {}
            }
        });
        body.scrollTop = body.scrollHeight;
    }
    function ptDebugOpen() {
        var m = document.getElementById('ptDebugModal');
        if (!m) return;
        m.classList.add('active');
        ptDebugRenderAll();
    }
    function ptDebugClose() {
        var m = document.getElementById('ptDebugModal');
        if (!m) return;
        m.classList.remove('active');
    }

    // ==================== CHART STATE ====================
    var PT_CANDLES     = [];
    var PT_CANVAS      = null;
    var PT_CTX         = null;
    var PT_DRAW_CANVAS = null;
    var PT_DRAW_CTX    = null;
    var PT_HOVER_INDEX = -1;
    var PT_CURSOR_Y    = null;

    var PT_TRADE_MATCH_COUNT  = 0;
    var PT_TRADE_SCAN_DIRTY   = true;

    var PT_LONG_SHORT_ON      = false;

    var PT_PERSISTED_MATCHES      = [];
    var PT_PERSISTED_RUN_TOKEN    = '';
    var PT_PERSISTED_DIRTY        = true;
    var PT_PERSISTED_LOADED       = false;
    var PT_LAST_PERSISTED_SIG     = '';

    // Projection mode: now supports MULTIPLE higher-TF projections at once.
    // Array of { sourceTF, targetTF } entries.
    var PT_PROJECTION_MODES   = [];
    // Projected matches per sourceTF: { sourceTF: [rows...] }
    var PT_PROJECTED_BY_TF    = {};

    var PT_CONFIGURED_ROWS_BY_TF = {};

    var PT_LOAD_TOKEN         = 0;
    var PT_IS_LOADING_OLDER   = false;
    var PT_HAS_MORE_OLDER     = true;
    var PT_INITIAL_LOADED     = false;
    var PT_REQUESTED_AMOUNT   = PT_DEFAULT_AMOUNT;
    var PT_CONSECUTIVE_ERRORS = 0;
    var PT_MAX_CONSECUTIVE_ERRORS = 4;
    var PT_FULL_HISTORY_DONE  = false;

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

    function ptResolveDrawingColor(token) {
        var isDark = document.body.classList.contains('dark-mode');
        switch ((token || '').toLowerCase()) {
            case 'green':  return '#2e8b57';
            case 'blue':   return 'rgba(74,123,216,0.95)';
            case 'red':    return '#e74c3c';
            case 'purple': return '#8e44ad';
            case 'custom': return isDark ? '#ffffff' : '#000000';
            default:       return 'rgba(74,123,216,0.95)';
        }
    }

    function ptParseTime(ts) {
        if (!ts) return null;
        var s = String(ts).replace('T', ' ');
        if (s.length === 16) s += ':00';
        var d = new Date(s.replace(' ', 'T') + 'Z');
        if (isNaN(d.getTime())) return null;
        return d;
    }

    function ptTfMs(tf) {
        if (!tf) return null;
        var s = String(tf).toLowerCase().trim();
        var m = /^(\d+)\s*([mhdw])$/.exec(s);
        if (!m) return null;
        var n = parseInt(m[1], 10);
        var unit = m[2];
        if (unit === 'm') return n * 60 * 1000;
        if (unit === 'h') return n * 60 * 60 * 1000;
        if (unit === 'd') return n * 24 * 60 * 60 * 1000;
        if (unit === 'w') return n * 7 * 24 * 60 * 60 * 1000;
        return null;
    }

    function ptIsLowerTF(a, b) {
        var am = ptTfMs(a), bm = ptTfMs(b);
        if (am == null || bm == null) return false;
        return bm < am;
    }

    function ptSnapshotSignature(treeResults) {
        // Simple, cheap signature of the current snapshot. Used to avoid
        // redundant persist round-trips when nothing changed.
        var s = PT_CURRENT_SYMBOL + '|' + PT_CURRENT_TIMEFRAME + '|' + PT_CANDLES.length;
        var total = 0;
        var tids = [];
        (treeResults || []).forEach(function (r) {
            tids.push(r.tree.tree_id + ':' + (r._matchedFlat ? r._matchedFlat.length : 0));
            total += (r._matchedFlat ? r._matchedFlat.length : 0);
        });
        tids.sort();
        return s + '|' + total + '|' + tids.join(',');
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
        ptAttachTradesModalEvents();
        ptAttachDebugModalEvents();

        ptDebugLine('info', 'Init', { programme: PT_PROGRAMME_NAME, id: PT_PROGRAMME_ID,
            symbols: PT_ALL_SYMBOLS.length, timeframes: PT_ALL_TIMEFRAMES.length,
            trees: PT_TREES.length, accountMgmt: PT_ACCOUNT_MGMT });

        var exploreBtn = document.getElementById('ptExploreBtn');
        if (exploreBtn) {
            exploreBtn.addEventListener('click', function () {
                ptOpenCandlesModal(PT_CURRENT_SYMBOL, PT_CURRENT_TIMEFRAME);
            });
        }

        var cfgBtn = document.getElementById('ptCfgBtn');
        if (cfgBtn) {
            cfgBtn.addEventListener('click', function () {
                ptDebugLine('cfg', 'Opening configuration modal');
                if (typeof window.pcSetTimeframes === 'function') {
                    window.pcSetTimeframes(PT_ALL_TIMEFRAMES, PT_CURRENT_TIMEFRAME);
                }
                if (typeof window.pcSetTrees === 'function') {
                    window.pcSetTrees(PT_TREES);
                }
                if (typeof window.pcOpenConfiguration === 'function') {
                    window.pcOpenConfiguration({
                        programmeId:      PT_PROGRAMME_ID,
                        programmeName:    PT_PROGRAMME_NAME,
                        timeframes:       PT_ALL_TIMEFRAMES,
                        currentTimeframe: PT_CURRENT_TIMEFRAME,
                        trees:            PT_TREES,
                        accountManagement: PT_ACCOUNT_MGMT
                    });
                }
            });
        }

        var tradesBtn = document.getElementById('ptTradesBtn');
        if (tradesBtn) tradesBtn.addEventListener('click', ptOpenTradesModal);

        var lsBtn = document.getElementById('ptLongShortBtn');
        if (lsBtn) lsBtn.addEventListener('click', ptToggleLongShort);

        window.addEventListener('pc:save', function (ev) {
            if (!ev || !ev.detail) return;
            ptDebugLine('cfg', 'Received pc:save', ev.detail);
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
        ptRefreshTradesButton();
        ptUpdateProjBanner();

        if (PT_CURRENT_SYMBOL && PT_CURRENT_TIMEFRAME) {
            ptLoadChart();
        } else {
            ptShowEmpty('No symbol or timeframe selected. Choose one from the top bar.');
        }

        window.addEventListener('resize', function () {
            if (PT_CANDLES.length) ptScheduleDraw();
        });
    });

    // ==================== DEBUG MODAL EVENTS ====================
    function ptAttachDebugModalEvents() {
        var modal = document.getElementById('ptDebugModal');
        var closeBtn = document.getElementById('ptDebugClose');
        var clearBtn = document.getElementById('ptDebugClear');
        var copyBtn  = document.getElementById('ptDebugCopy');
        var btn = document.getElementById('ptDebugBtn');
        if (btn) btn.addEventListener('click', ptDebugOpen);
        if (closeBtn) closeBtn.addEventListener('click', ptDebugClose);
        if (modal) modal.addEventListener('mousedown', function (e) { if (e.target === modal) ptDebugClose(); });
        if (clearBtn) clearBtn.addEventListener('click', function () {
            PT_DEBUG_LINES = [];
            ptDebugRenderAll();
        });
        if (copyBtn) copyBtn.addEventListener('click', function () {
            var text = PT_DEBUG_LINES.map(function (l) {
                return '[' + l.ts + '] [' + l.level + '] ' + l.msg;
            }).join('\n');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    ptDebugLine('info', 'Copied ' + PT_DEBUG_LINES.length + ' lines.');
                }).catch(function () {});
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (modal && modal.classList.contains('active')) ptDebugClose();
            }
        });
    }

    // ==================== TRADES MODAL ====================
    function ptAttachTradesModalEvents() {
        var modal = document.getElementById('ptTradesModal');
        var closeBtn = document.getElementById('ptTradesClose');
        if (closeBtn) closeBtn.addEventListener('click', ptCloseTradesModal);
        if (modal) modal.addEventListener('mousedown', function (e) { if (e.target === modal) ptCloseTradesModal(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('active')) ptCloseTradesModal();
        });
    }
    function ptCloseTradesModal() {
        var modal = document.getElementById('ptTradesModal');
        if (modal) modal.classList.remove('active');
    }

    // ==================== TIMEFRAME SWITCHING (NO MODAL) ====================
    // Clicking a timeframe now simply:
    //   1. Sets PT_CURRENT_TIMEFRAME.
    //   2. Clears any projection state.
    //   3. For every OTHER configured timeframe in PT_ALL_TIMEFRAMES that is
    //      HIGHER than the target, schedules a projection of that timeframe's
    //      saved matches onto the target.
    //   4. Loads the target chart. Everything is drawn on the same canvas.

    function ptSwitchToTimeframe(targetTF) {
        if (!targetTF) return;
        if (targetTF === PT_CURRENT_TIMEFRAME && !PT_PROJECTION_MODES.length) return;

        ptDebugLine('cfg', 'Switching timeframe (no modal)', { to: targetTF });

        PT_CURRENT_TIMEFRAME = targetTF;
        PT_REQUESTED_AMOUNT  = PT_DEFAULT_AMOUNT;
        PT_FULL_HISTORY_DONE = false;

        // Reset native persisted state
        PT_PERSISTED_MATCHES   = [];
        PT_PERSISTED_LOADED    = false;
        PT_PERSISTED_DIRTY     = true;
        PT_LAST_PERSISTED_SIG  = '';

        // Reset projection state
        PT_PROJECTION_MODES = [];
        PT_PROJECTED_BY_TF  = {};

        // Find all higher configured timeframes and request projections
        var higherTFs = [];
        PT_ALL_TIMEFRAMES.forEach(function (tf) {
            if (tf === targetTF) return;
            if (!ptIsLowerTF(tf, targetTF)) return;
            higherTFs.push(tf);
        });

        // Sort descending by duration (highest first)
        higherTFs.sort(function (a, b) {
            return (ptTfMs(b) || 0) - (ptTfMs(a) || 0);
        });

        ptDebugLine('cfg', 'Higher TFs to project', { target: targetTF, sources: higherTFs });

        ptRenderTimeframeStrip();
        ptUpdateProjBanner();
        ptRefreshTradesButton();
        ptLoadChart();

        // Kick off projection for each higher TF as soon as we have that TF's
        // saved rows. Each projection appends to PT_PROJECTED_BY_TF and
        // triggers a redraw once it completes.
        higherTFs.forEach(function (srcTF) {
            PT_PROJECTION_MODES.push({ sourceTF: srcTF, targetTF: targetTF });
            ptEnsureConfiguredRows(srcTF, function () {
                ptProjectConfiguredRowsOnto(srcTF, targetTF);
            });
        });

        ptUpdateProjBanner();
    }

    function ptUpdateProjBanner() {
        var b = document.getElementById('ptProjBanner');
        var t = document.getElementById('ptProjBannerText');
        if (!b || !t) return;
        if (PT_PROJECTION_MODES.length) {
            var total = 0;
            PT_PROJECTION_MODES.forEach(function (m) {
                total += (PT_PROJECTED_BY_TF[m.sourceTF] || []).length;
            });
            var labels = PT_PROJECTION_MODES.map(function (m) { return m.sourceTF; }).join(' + ');
            t.textContent = 'Projections: ' + labels + ' → ' + PT_CURRENT_TIMEFRAME
                          + '  (' + total + ' projected rows)';
            b.classList.add('active');
        } else {
            b.classList.remove('active');
        }
    }

    function ptEnsureConfiguredRows(sourceTF, onReady) {
        if (PT_CONFIGURED_ROWS_BY_TF[sourceTF]) {
            onReady();
            return;
        }

        var body = 'fetch_persisted_matches=1'
                 + '&programmeid=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&symbol='      + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe='   + encodeURIComponent(sourceTF);

        ptDebugLine('net', 'POST fetch_persisted_matches (projection source)', {
            programmeId: PT_PROGRAMME_ID, symbol: PT_CURRENT_SYMBOL, timeframe: sourceTF
        });

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                PT_CONFIGURED_ROWS_BY_TF[sourceTF] = data.rows || [];
            } else {
                PT_CONFIGURED_ROWS_BY_TF[sourceTF] = [];
            }
            ptDebugLine('net', 'Projection source rows fetched', {
                timeframe: sourceTF, count: PT_CONFIGURED_ROWS_BY_TF[sourceTF].length
            });
            onReady();
        })
        .catch(function (err) {
            ptDebugLine('error', 'Projection source fetch failed: ' + (err && err.message ? err.message : 'Unknown'));
            PT_CONFIGURED_ROWS_BY_TF[sourceTF] = [];
            onReady();
        });
    }

    function ptFetchRange(symbol, timeframe, fromTime, toTime, limit) {
        var body = 'fetch_candles_range=1'
                 + '&symbol='    + encodeURIComponent(symbol)
                 + '&timeframe=' + encodeURIComponent(timeframe)
                 + '&from_time=' + encodeURIComponent(fromTime)
                 + '&to_time='   + encodeURIComponent(toTime)
                 + '&limit='     + (limit || 100000);

        return fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) return data.candles || [];
            return [];
        })
        .catch(function () { return []; });
    }

    function ptFetchRecent(symbol, timeframe, limit) {
        var body = 'fetch_candles_recent=1'
                 + '&symbol='    + encodeURIComponent(symbol)
                 + '&timeframe=' + encodeURIComponent(timeframe)
                 + '&limit='     + (limit || 5000);

        return fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) return data.candles || [];
            return [];
        })
        .catch(function () { return []; });
    }

    function ptFetchBounds(symbol, timeframe) {
        var body = 'fetch_candles_bounds=1'
                 + '&symbol='    + encodeURIComponent(symbol)
                 + '&timeframe=' + encodeURIComponent(timeframe);

        return fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .catch(function () { return { success: false }; });
    }

    function ptNormTs(t) {
        if (!t) return '';
        var s = String(t).replace('T', ' ').trim();
        var m = /^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/.exec(s);
        if (m) return m[1];
        m = /^(\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/.exec(s);
        if (m) return m[1] + ':00';
        m = /^(\d{4}-\d{2}-\d{2})$/.exec(s);
        if (m) return m[1] + ' 00:00:00';
        return s;
    }

    function ptDateFromTs(ts) {
        var n = ptNormTs(ts);
        if (!n) return null;
        var d = new Date(n.replace(' ', 'T') + 'Z');
        return isNaN(d.getTime()) ? null : d;
    }

    function ptTsFromDate(d) {
        if (!d) return '';
        function pad(n) { return n < 10 ? '0' + n : '' + n; }
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate())
             + ' ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
    }

    function ptProjectConfiguredRowsOnto(sourceTF, targetTF) {
        var rows = PT_CONFIGURED_ROWS_BY_TF[sourceTF] || [];
        if (!rows.length) {
            PT_PROJECTED_BY_TF[sourceTF] = [];
            ptUpdateProjBanner();
            ptRefreshTradesButton();
            ptScheduleDraw();
            ptDebugLine('warn', 'No source rows to project', { sourceTF: sourceTF });
            return;
        }

        var windows = {};
        rows.forEach(function (r) {
            if (!r.open_time || !r.close_time) return;
            var key = ptNormTs(r.open_time) + '|' + ptNormTs(r.close_time);
            windows[key] = { open_time: ptNormTs(r.open_time), close_time: ptNormTs(r.close_time) };
        });

        var winKeys = Object.keys(windows);
        if (!winKeys.length) {
            PT_PROJECTED_BY_TF[sourceTF] = [];
            ptUpdateProjBanner();
            ptRefreshTradesButton();
            ptScheduleDraw();
            ptDebugLine('warn', 'No windows found in source rows', {});
            return;
        }

        var minOpen = null, maxClose = null;
        winKeys.forEach(function (k) {
            var w = windows[k];
            if (minOpen === null || w.open_time < minOpen) minOpen = w.open_time;
            if (maxClose === null || w.close_time > maxClose) maxClose = w.close_time;
        });

        ptDebugLine('data', 'Projection: requested span', {
            sourceTF: sourceTF, targetTF: targetTF,
            from: minOpen, to: maxClose, windows: winKeys.length
        });

        ptFetchBounds(PT_CURRENT_SYMBOL, targetTF).then(function (bounds) {
            var effFrom = minOpen;
            var effTo   = maxClose;

            if (bounds && bounds.success && bounds.min_time && bounds.max_time) {
                var bMin = ptNormTs(bounds.min_time);
                var bMax = ptNormTs(bounds.max_time);
                if (bMin > effFrom) effFrom = bMin;
                var bMaxDate = ptDateFromTs(bMax);
                if (bMaxDate) {
                    bMaxDate.setUTCSeconds(bMaxDate.getUTCSeconds() + 1);
                    var bMaxPlus = ptTsFromDate(bMaxDate);
                    if (bMaxPlus < effTo) effTo = bMaxPlus;
                }
                ptDebugLine('data', 'Projection: intersected span', {
                    boundsMin: bMin, boundsMax: bMax,
                    effFrom: effFrom, effTo: effTo,
                    lowerTotal: bounds.total
                });
            } else {
                ptDebugLine('warn', 'Projection: could not fetch lower-TF bounds, using raw span', {});
            }

            if (effFrom >= effTo) {
                ptDebugLine('warn', 'Projection: empty intersection — falling back to recent candles', {
                    rawFrom: minOpen, rawTo: maxClose, effFrom: effFrom, effTo: effTo
                });
                ptProjectViaRecentFallback(rows, windows, winKeys, sourceTF, targetTF);
                return;
            }

            var chunks = ptBuildWeeklyChunks(effFrom, effTo);
            ptDebugLine('data', 'Projection: fetching in chunks', { chunkCount: chunks.length });

            var allSubs = [];
            var chain = Promise.resolve();
            chunks.forEach(function (ch) {
                chain = chain.then(function () {
                    return ptFetchRange(PT_CURRENT_SYMBOL, targetTF, ch.from, ch.to, 100000)
                        .then(function (list) {
                            if (list && list.length) {
                                allSubs = allSubs.concat(list);
                            }
                            return null;
                        });
                });
            });

            chain.then(function () {
                ptDebugLine('data', 'Projection: chunks fetched', {
                    totalCandles: allSubs.length, chunks: chunks.length
                });

                if (!allSubs.length) {
                    ptDebugLine('warn', 'Projection: chunked fetch returned 0 — falling back to recent', {});
                    ptProjectViaRecentFallback(rows, windows, winKeys, sourceTF, targetTF);
                    return;
                }

                ptBuildProjectionFromSubs(rows, windows, allSubs, sourceTF, targetTF);
            });
        });
    }

    function ptBuildWeeklyChunks(fromTs, toTs) {
        var out = [];
        var cur = ptDateFromTs(fromTs);
        var end = ptDateFromTs(toTs);
        if (!cur || !end) return [{ from: fromTs, to: toTs }];

        while (cur < end) {
            var next = new Date(cur.getTime() + 7 * 24 * 60 * 60 * 1000);
            if (next > end) next = end;
            out.push({ from: ptTsFromDate(cur), to: ptTsFromDate(next) });
            cur = next;
        }
        if (!out.length) out.push({ from: fromTs, to: toTs });
        return out;
    }

    function ptProjectViaRecentFallback(rows, windows, winKeys, sourceTF, targetTF) {
        ptFetchRecent(PT_CURRENT_SYMBOL, targetTF, 5000).then(function (recent) {
            if (!recent.length) {
                ptDebugLine('error', 'Projection fallback: no recent candles available', {});
                PT_PROJECTED_BY_TF[sourceTF] = [];
                ptUpdateProjBanner();
                ptRefreshTradesButton();
                ptScheduleDraw();
                return;
            }

            var firstTs = ptNormTs(recent[0].time);
            var lastTs  = ptNormTs(recent[recent.length - 1].time);
            ptDebugLine('data', 'Projection fallback: recent range', {
                count: recent.length, first: firstTs, last: lastTs
            });

            var overlapping = rows.filter(function (r) {
                var o = ptNormTs(r.open_time || r.candle_time || '');
                var c = ptNormTs(r.close_time || r.candle_time || '');
                if (!o || !c) return false;
                return !(c < firstTs || o > lastTs);
            });

            if (!overlapping.length) {
                ptDebugLine('warn', 'Projection fallback: no source rows overlap the recent window', {});
                PT_PROJECTED_BY_TF[sourceTF] = [];
                ptUpdateProjBanner();
                ptRefreshTradesButton();
                ptScheduleDraw();
                return;
            }

            ptDebugLine('data', 'Projection fallback: overlapping source rows', {
                count: overlapping.length
            });

            ptBuildProjectionFromSubs(overlapping, windows, recent, sourceTF, targetTF);
        });
    }

    function ptBuildProjectionFromSubs(rows, windows, subs, sourceTF, targetTF) {
        var winKeys = Object.keys(windows);
        var subsByWindow = {};
        winKeys.forEach(function (k) {
            var w = windows[k];
            subsByWindow[k] = subs.filter(function (c) {
                var t = ptNormTs(c.time);
                return t >= w.open_time && t < w.close_time;
            });
        });

        var projected = [];
        rows.forEach(function (r) {
            var o = ptNormTs(r.open_time || '');
            var c = ptNormTs(r.close_time || '');
            if (!o || !c) return;
            var winKey = o + '|' + c;
            var list = subsByWindow[winKey];
            if (!list || !list.length) return;

            var level = (r.price_level || r.entry_from_price_level || r.draw_from_price_level || 'close').toLowerCase();
            var sub = ptPickSubBar(list, level, r);
            if (!sub) return;

            var projectedRow = ptCloneRowWithSubBar(r, sub, level);
            projected.push(projectedRow);
        });

        PT_PROJECTED_BY_TF[sourceTF] = projected;
        ptUpdateProjBanner();
        ptRefreshTradesButton();

        ptReevaluateProjectedTradeOutcomes();

        ptDebugLine('info', 'Projection complete', {
            sourceTF: sourceTF,
            sourceRows: rows.length,
            subsFetched: subs.length,
            projectedRows: projected.length
        });

        var modal = document.getElementById('ptTradesModal');
        if (modal && modal.classList.contains('active')) ptRenderTradesResults();

        ptScheduleDraw();
    }

    function ptPickSubBar(list, level, sourceRow) {
        if (!list || !list.length) return null;

        switch (level) {
            case 'open':
                return list[0];
            case 'close':
                return list[list.length - 1];
            case 'high': {
                var best = list[0];
                for (var i = 1; i < list.length; i++) {
                    if (list[i].high > best.high) best = list[i];
                }
                return best;
            }
            case 'low': {
                var best2 = list[0];
                for (var j = 1; j < list.length; j++) {
                    if (list[j].low < best2.low) best2 = list[j];
                }
                return best2;
            }
            default: {
                var srcVal = sourceRow ? sourceRow[level] : null;
                if (srcVal == null) return list[0];
                var bestIdx = 0;
                var bestDiff = Infinity;
                for (var k = 0; k < list.length; k++) {
                    var v = list[k][level];
                    if (v == null) continue;
                    var d = Math.abs(v - srcVal);
                    if (d < bestDiff) { bestDiff = d; bestIdx = k; }
                }
                return list[bestIdx];
            }
        }
    }

    function ptCloneRowWithSubBar(src, sub, level) {
        var out = {};
        for (var k in src) {
            if (Object.prototype.hasOwnProperty.call(src, k)) out[k] = src[k];
        }

        out.candle_record_id    = sub.id;
        out.candle_time         = sub.time;
        out.open_time           = sub.open_time;
        out.close_time          = sub.close_time;
        out.open                = sub.open;
        out.high                = sub.high;
        out.low                 = sub.low;
        out.close               = sub.close;
        out.candle_center       = sub.candle_center;
        out.body_center         = sub.body_center;
        out.high_wick_center    = sub.high_wick_center;
        out.low_wick_center     = sub.low_wick_center;
        out.candle_width_center = sub.candle_width_center;
        out.volume              = sub.volume;

        out._projected_from      = src.timeframe || null;
        out._projected_level     = level;
        out._projected_sub_time  = sub.time;
        out._projected_is_sub    = true;

        return out;
    }

    // Re-evaluate every projected trade (across ALL source TFs) against the
    // currently loaded candle set.
    function ptReevaluateProjectedTradeOutcomes() {
        if (!PT_CANDLES.length) return;

        var idxByTime = {};
        PT_CANDLES.forEach(function (c, i) { idxByTime[c.time] = i; });

        Object.keys(PT_PROJECTED_BY_TF).forEach(function (srcTF) {
            var list = PT_PROJECTED_BY_TF[srcTF] || [];
            list.forEach(function (r) {
                if (r.row_role !== 'matched_trade') return;
                if (r.entry_from_price_level == null) return;

                var entryIdx = idxByTime[r.candle_time];
                if (entryIdx == null) return;

                var direction = r.resolved_direction || 0;
                if (direction === 0) return;

                var entryPrice = r.entry_price != null ? r.entry_price
                                : (r.candle_center != null ? r.candle_center
                                    : (r.entry_from_price_level && r[r.entry_from_price_level] != null
                                        ? r[r.entry_from_price_level]
                                        : r.close));
                var exitPrice = r.exit_price != null ? r.exit_price : null;
                if (exitPrice == null && r.resolved_risk != null) {
                    exitPrice = entryPrice - direction * r.resolved_risk;
                }
                var targetPrice = r.target_price != null ? r.target_price
                                : (r.resolved_price != null ? r.resolved_price : null);

                if (exitPrice == null && targetPrice == null) return;

                var hitStatus = 'pending';
                var hitTime   = null;
                var hitPrice  = null;

                for (var i = entryIdx; i < PT_CANDLES.length; i++) {
                    var c = PT_CANDLES[i];
                    var hitStop = false;
                    var hitTarget = false;

                    if (direction > 0) {
                        if (exitPrice  != null) hitStop   = (c.low  <= exitPrice);
                        if (targetPrice != null) hitTarget = (c.high >= targetPrice);
                    } else {
                        if (exitPrice  != null) hitStop   = (c.high >= exitPrice);
                        if (targetPrice != null) hitTarget = (c.low  <= targetPrice);
                    }

                    if (hitStop)   { hitStatus = 'stop';   hitTime = c.time; hitPrice = exitPrice;   break; }
                    if (hitTarget) { hitStatus = 'profit'; hitTime = c.time; hitPrice = targetPrice; break; }
                }

                r.outcome_status       = hitStatus;
                r.outcome_candle_time  = hitTime;
                r.outcome_price        = hitPrice;
                r._projected_outcome   = true;
            });
        });
    }

    function ptRefreshTradeButtonSafe() {
        try { ptRefreshTradesButton(); } catch (e) {}
    }

    // ==================== LONG/SHORT TOGGLE ====================
    function ptToggleLongShort() {
        PT_LONG_SHORT_ON = !PT_LONG_SHORT_ON;
        var btn = document.getElementById('ptLongShortBtn');
        var state = document.getElementById('ptLongShortState');
        if (btn) btn.classList.toggle('pt-ls-on', PT_LONG_SHORT_ON);
        if (state) state.textContent = PT_LONG_SHORT_ON ? 'ON' : 'OFF';
        ptDebugLine('info', 'Long/Short overlay ' + (PT_LONG_SHORT_ON ? 'ON' : 'OFF'));
        ptScheduleDraw();
    }

    // ==================== COMBINED MATCH ROWS ====================
    // Returns the "active" match rows for the current chart. If any
    // projections exist, returns the concatenation of all projected sets.
    // Otherwise returns the native persisted matches.
    function ptActiveMatchRows() {
        if (PT_PROJECTION_MODES.length) {
            var combined = [];
            PT_PROJECTION_MODES.forEach(function (m) {
                var list = PT_PROJECTED_BY_TF[m.sourceTF] || [];
                for (var i = 0; i < list.length; i++) combined.push(list[i]);
            });
            return combined;
        }
        return PT_PERSISTED_MATCHES;
    }

    function ptActiveNativeMatches() {
        // Native matches are always drawn (even when projections exist),
        // so the chart shows "everything at once".
        if (PT_PERSISTED_MATCHES.length) return PT_PERSISTED_MATCHES;
        return [];
    }

    function ptRenderTradesResults() {
        var resultsEl = document.getElementById('ptTradesResults');
        if (!resultsEl) return;

        var treeResults = ptScanTrades();

        var allHits = [];
        var byTree  = {};

        treeResults.forEach(function (r) {
            if (!r.hits || !r.hits.length) return;
            var tid = r.tree.tree_id;
            if (!byTree[tid]) byTree[tid] = { tree: r.tree, hits: [] };
            r.hits.forEach(function (h) {
                byTree[tid].hits.push(h);
                allHits.push(h);
            });
        });

        var srcLabel = PT_PROJECTION_MODES.length
            ? ('Projected — ' + PT_PROJECTION_MODES.map(function (m) { return m.sourceTF; }).join(' + ')
                + ' → ' + PT_CURRENT_TIMEFRAME + ' view')
            : ('From live scan — ' + PT_CURRENT_SYMBOL + ' @ ' + PT_CURRENT_TIMEFRAME);

        if (!allHits.length) {
            resultsEl.innerHTML = '<div class="pt-trades-empty">'
                + (PT_PROJECTION_MODES.length
                    ? 'No projected trade matches for this symbol/timeframe.'
                    : 'No trade matches found for this symbol/timeframe.')
                + '</div>';
            return;
        }

        var html = ptRenderTradeSummaryCard(allHits, 'Global Summary — ' + srcLabel);

        Object.keys(byTree).forEach(function (tid) {
            var grp = byTree[tid];
            if (!grp.hits.length) return;

            var rootName = ptFirstRootName(grp.tree);

            html += '<div class="pt-trades-tree" data-open="1" data-tree-idx="' + tid + '">';
            html += '  <div class="pt-trades-tree-head">';
            html += '    <i class="fa-solid fa-chevron-right pt-trades-tree-caret"></i>';
            html += '    <i class="fa-solid fa-sitemap pt-trades-tree-icon"></i>';
            html += '    <span class="pt-trades-tree-name">' + ptEscapeHtml(rootName) + '</span>';
            html += '    <span class="pt-trades-tree-count">' + grp.hits.length + ' match'
                +      (grp.hits.length > 1 ? 'es' : '') + '</span>';
            html += '  </div>';
            html += '  <div class="pt-trades-tree-body">';

            html += ptRenderTradeSummaryCard(grp.hits, 'Summary — ' + rootName);

            var sortedHits = grp.hits.slice().sort(function (a, b) {
                var ta = a.entry && a.entry.candle ? a.entry.candle.time : '';
                var tb = b.entry && b.entry.candle ? b.entry.candle.time : '';
                if (ta === tb) {
                    var xa = a.exit && a.exit.candle ? a.exit.candle.time : '';
                    var xb = b.exit && b.exit.candle ? b.exit.candle.time : '';
                    return String(xb).localeCompare(String(xa));
                }
                return String(tb).localeCompare(String(ta));
            });

            sortedHits.forEach(function (hit, idx) {
                html += ptRenderTradeHitCard(hit, idx);
            });

            html += '  </div>';
            html += '</div>';
        });

        resultsEl.innerHTML = html;
        ptAttachTradesTreeToggles(resultsEl);
    }

    function ptAttachTradesTreeToggles(host) {
        Array.prototype.forEach.call(host.querySelectorAll('.pt-trades-tree-head'), function (head) {
            head.addEventListener('click', function () {
                var tree = head.parentNode;
                var open = tree.getAttribute('data-open') === '1';
                tree.setAttribute('data-open', open ? '0' : '1');
            });
        });
    }

    function ptRefreshTradesButton() {
        var btn   = document.getElementById('ptTradesBtn');
        var lsBtn = document.getElementById('ptLongShortBtn');
        var cnt   = document.getElementById('ptTradesCount');
        if (!btn) return;

        var ruleCount = 0;
        (PT_TREES || []).forEach(function (t) {
            if (t.trades && t.trades.length) ruleCount += t.trades.length;
        });

        if (ruleCount === 0) {
            btn.style.display = 'none';
            if (lsBtn) lsBtn.style.display = 'none';
            return;
        }

        btn.style.display = 'inline-flex';
        if (lsBtn) lsBtn.style.display = 'inline-flex';

        if (cnt) {
            if (!PT_CANDLES.length) {
                cnt.textContent = '…';
                cnt.title = 'Waiting for candles to load…';
            } else {
                var results = ptScanTrades();
                var total = 0;
                results.forEach(function (r) { total += r.hits.length; });
                PT_TRADE_MATCH_COUNT = total;

                cnt.textContent = total;
                cnt.title = total + ' trade match' + (total === 1 ? '' : 'es')
                        + (PT_PROJECTION_MODES.length ? ' (projected)' : ' (live scan)');
            }
        }
    }

    /**
     * Rescan the current chart with the current tree configuration.
     * When `persist` is truthy, the resulting snapshot is written to the DB
     * (deleting the previous snapshot for this symbol/TF first, via the
     * server-side full-snapshot semantics).
     */
    function ptRescanTradeMatches(persist) {
        PT_TRADE_SCAN_DIRTY = true;
        ptRefreshTradesButton();
        setTimeout(function () {
            var results = ptScanTrades();
            var total = 0;
            results.forEach(function (r) { total += r.hits.length; });
            PT_TRADE_MATCH_COUNT = total;
            PT_TRADE_SCAN_DIRTY  = false;
            ptDebugLine('info', 'Trade match count updated', { total: total, persist: !!persist });
            ptRefreshTradesButton();

            if (persist) {
                ptPersistMatches(results);
            }
        }, 0);
    }

    function ptPersistMatches(results) {
        var treesPayload = [];
        (results || []).forEach(function (r) {
            var flat = r._matchedFlat || [];
            if (!flat.length) return;
            treesPayload.push({
                tree_id: r.tree.tree_id,
                matched: flat
            });
        });

        var sig = ptSnapshotSignature(results);
        if (sig === PT_LAST_PERSISTED_SIG) {
            ptDebugLine('data', 'persist_matches skipped (no change)', { sig: sig });
            ptLoadPersistedMatches();
            return;
        }

        var payload = {
            programmeId: PT_PROGRAMME_ID,
            symbol:      PT_CURRENT_SYMBOL,
            timeframe:   PT_CURRENT_TIMEFRAME,
            run_token:   PT_PERSISTED_RUN_TOKEN || '',
            trees:       treesPayload
        };

        ptDebugLine('net', 'POST persist_matches', {
            trees: treesPayload.length,
            totalMatched: treesPayload.reduce(function (a, t) { return a + t.matched.length; }, 0),
            sig: sig
        });

        var body = 'persist_matches=1&payload=' + encodeURIComponent(JSON.stringify(payload));
        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            ptDebugLine('net', 'persist_matches response', data);
            if (data && data.success) {
                PT_PERSISTED_RUN_TOKEN = data.run_token || PT_PERSISTED_RUN_TOKEN;
                PT_LAST_PERSISTED_SIG  = sig;
            }
            ptLoadPersistedMatches();
        })
        .catch(function (err) {
            ptDebugLine('error', 'persist_matches network error: ' + (err && err.message ? err.message : 'Unknown'));
            ptLoadPersistedMatches();
        });
    }

    function ptLoadPersistedMatches() {
        if (!PT_PROGRAMME_ID || !PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME) {
            PT_PERSISTED_MATCHES = [];
            PT_PERSISTED_LOADED  = true;
            PT_PERSISTED_DIRTY   = false;
            ptRefreshTradesButton();
            return;
        }

        if (PT_PROJECTION_MODES.length) {
            PT_PROJECTION_MODES.forEach(function (m) {
                ptEnsureConfiguredRows(m.sourceTF, function () {
                    ptProjectConfiguredRowsOnto(m.sourceTF, m.targetTF);
                });
            });
            PT_PERSISTED_LOADED = true;
            return;
        }

        var body = 'fetch_persisted_matches=1'
                 + '&programmeid=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&symbol='      + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe='   + encodeURIComponent(PT_CURRENT_TIMEFRAME);

        ptDebugLine('net', 'POST fetch_persisted_matches', {
            programmeId: PT_PROGRAMME_ID, symbol: PT_CURRENT_SYMBOL, timeframe: PT_CURRENT_TIMEFRAME
        });

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            ptDebugLine('net', 'fetch_persisted_matches response', {
                success: data && data.success, count: data && data.rows ? data.rows.length : 0
            });
            if (data && data.success) {
                PT_PERSISTED_MATCHES = data.rows || [];
                PT_CONFIGURED_ROWS_BY_TF[PT_CURRENT_TIMEFRAME] = PT_PERSISTED_MATCHES;
            } else {
                PT_PERSISTED_MATCHES = [];
            }
            PT_PERSISTED_LOADED = true;
            PT_PERSISTED_DIRTY  = false;
            ptRefreshTradesButton();

            var modal = document.getElementById('ptTradesModal');
            if (modal && modal.classList.contains('active')) {
                ptRenderTradesResults();
            }
        })
        .catch(function (err) {
            ptDebugLine('error', 'fetch_persisted_matches network error: ' + (err && err.message ? err.message : 'Unknown'));
            PT_PERSISTED_MATCHES = [];
            PT_PERSISTED_LOADED  = true;
            PT_PERSISTED_DIRTY   = false;
            ptRefreshTradesButton();
        });
    }

    function ptFirstRootName(tree) {
        var roots = tree.roots || [];
        if (roots.length && roots[0].candle_name) return roots[0].candle_name;
        return 'Tree ' + tree.tree_id;
    }

    function ptOpenTradesModal() {
        var modal = document.getElementById('ptTradesModal');
        var body  = document.getElementById('ptTradesBody');
        var sub   = document.getElementById('ptTradesSubtitle');
        if (!modal || !body) return;

        if (sub) {
            var lbl = PT_PROJECTION_MODES.length
                ? ('projected ' + PT_PROJECTION_MODES.map(function (m) { return m.sourceTF; }).join(' + ')
                    + ' → ' + PT_CURRENT_TIMEFRAME)
                : (PT_CURRENT_SYMBOL + ' @ ' + PT_CURRENT_TIMEFRAME + ' · live scan');
            sub.textContent = PT_PROGRAMME_NAME + ' · ' + lbl;
        }

        body.innerHTML = '<div id="ptTradesResults" class="pt-trades-results"></div>';
        modal.classList.add('active');

        if (!PT_CANDLES.length) {
            var resultsEl = document.getElementById('ptTradesResults');
            if (resultsEl) {
                resultsEl.innerHTML = '<div class="pt-trades-empty">Waiting for candles to load…</div>';
            }
        } else {
            ptRenderTradesResults();
        }

        ptDebugLine('info', 'Trades modal opened (live scan, projCount=' + PT_PROJECTION_MODES.length + ')');
    }

    // ==================== TRADE STATS ====================
    function ptComputeTradeStats(hitsChronological) {
        var stats = {
            highestConsecutiveLosses: 0,
            highestDrawdownPct:       0,
            peakLiquidity:            100,
            finalLiquidity:           100,
            winrate:                  0,
            wins:                     0,
            losses:                   0,
            resolved:                 0,
            dailyHighest:             0,
            dailyLowest:              0,
            dailyAverage:             null,
            dailyCount:               0,
            weeklyHighest:            0,
            weeklyLowest:             0,
            weeklyAverage:            null,
            weeklyCount:              0,
            weeklyDays:               []
        };
        if (!hitsChronological || !hitsChronological.length) return stats;

        var liquidity = 100;
        var peak      = 100;
        var maxDD     = 0;
        var consecLosses = 0;
        var maxConsecLosses = 0;

        var dailyMap = {};
        var weeklyMap = {};
        var tradingDayMap = {};
        var weekdayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

        hitsChronological.forEach(function (hit) {
            var rr = (hit.targetRR && hit.targetRR.ratio != null) ? hit.targetRR.ratio : 0;
            var status = hit.outcome ? hit.outcome.status : 'pending';

            if (status === 'stop' || status === 'profit') {
                stats.resolved++;
                if (status === 'stop') stats.losses++;
                else                   stats.wins++;
            }

            if (status === 'stop') {
                consecLosses++;
                if (consecLosses > maxConsecLosses) maxConsecLosses = consecLosses;
                liquidity = liquidity - (liquidity * (rr / 100));
            } else if (status === 'profit') {
                consecLosses = 0;
                liquidity = liquidity + (liquidity * (rr / 100));
            }
            if (liquidity > peak) peak = liquidity;
            var dd = ((peak - liquidity) / peak) * 100;
            if (dd > maxDD) maxDD = dd;

            var resCandle = (hit.outcome && hit.outcome.candle) ? hit.outcome.candle : null;
            if (resCandle && resCandle.time) {
                var dayKey = String(resCandle.time).slice(0, 10);
                if (!dailyMap[dayKey]) dailyMap[dayKey] = 0;
                dailyMap[dayKey]++;
            }

            var entryCandle = (hit.entry && hit.entry.candle) ? hit.entry.candle : null;
            if (entryCandle && entryCandle.time) {
                var weekKey = ptWeekKeyFromTime(entryCandle.time);
                if (weekKey) {
                    if (!weeklyMap[weekKey]) weeklyMap[weekKey] = 0;
                    weeklyMap[weekKey]++;
                }
                var dayIdx = ptWeekdayIndexFromTime(entryCandle.time);
                if (dayIdx >= 0) {
                    tradingDayMap[dayIdx] = true;
                }
            }
        });

        stats.highestConsecutiveLosses = maxConsecLosses;
        stats.highestDrawdownPct       = maxDD;
        stats.peakLiquidity            = peak;
        stats.finalLiquidity           = liquidity;

        stats.winrate = stats.resolved > 0
            ? (stats.wins / stats.resolved) * 100
            : 0;

        var dayKeys = Object.keys(dailyMap);
        stats.dailyCount = dayKeys.length;
        if (dayKeys.length) {
            var dailyCounts = dayKeys.map(function (k) { return dailyMap[k]; });
            stats.dailyHighest = Math.max.apply(null, dailyCounts);
            stats.dailyLowest  = Math.min.apply(null, dailyCounts);
            stats.dailyAverage = ptMedianBetween(dailyCounts);
        }

        var weekKeys = Object.keys(weeklyMap).sort();
        stats.weeklyCount = weekKeys.length;
        if (weekKeys.length) {
            var weeklyCounts = weekKeys.map(function (k) { return weeklyMap[k]; });
            stats.weeklyHighest = Math.max.apply(null, weeklyCounts);
            stats.weeklyLowest  = Math.min.apply(null, weeklyCounts);
            stats.weeklyAverage = ptMedianBetween(weeklyCounts);
        }

        var tradingDayIdx = Object.keys(tradingDayMap).map(function (k) { return parseInt(k, 10); });
        tradingDayIdx.sort(function (a, b) { return a - b; });
        stats.weeklyDays = tradingDayIdx.map(function (k) { return weekdayNames[k]; });

        return stats;
    }

    function ptWeekdayIndexFromTime(timeStr) {
        if (!timeStr) return -1;
        var s = String(timeStr).replace('T', ' ');
        var datePart = s.slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return -1;
        var parts = datePart.split('-');
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        var dt = new Date(Date.UTC(y, m, d));
        if (isNaN(dt.getTime())) return -1;
        return dt.getUTCDay();
    }

    function ptWeekKeyFromTime(timeStr) {
        if (!timeStr) return null;
        var s = String(timeStr).replace('T', ' ');
        var datePart = s.slice(0, 10);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(datePart)) return null;
        var parts = datePart.split('-');
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) - 1;
        var d = parseInt(parts[2], 10);
        var dt = new Date(Date.UTC(y, m, d));
        if (isNaN(dt.getTime())) return null;

        var day = dt.getUTCDay() || 7;
        dt.setUTCDate(dt.getUTCDate() + 4 - day);
        var yearStart = new Date(Date.UTC(dt.getUTCFullYear(), 0, 1));
        var weekNo = Math.ceil((((dt - yearStart) / 86400000) + 1) / 7);
        return dt.getUTCFullYear() + '-W' + (weekNo < 10 ? '0' + weekNo : weekNo);
    }

    function ptMedianBetween(counts) {
        if (!counts || !counts.length) return null;
        var uniq = counts.slice().sort(function (a, b) { return a - b; });
        var unique = [];
        for (var i = 0; i < uniq.length; i++) {
            if (i === 0 || uniq[i] !== uniq[i - 1]) unique.push(uniq[i]);
        }
        if (unique.length < 3) return null;

        var min = unique[0];
        var max = unique[unique.length - 1];

        var inner = unique.slice(1, unique.length - 1);
        if (!inner.length) return null;

        var midIdx = Math.floor((inner.length - 1) / 2);
        var candidate = inner[midIdx];
        if (candidate > min && candidate < max) return candidate;
        return null;
    }

    function ptRenderTradeSummaryCard(hits, label) {
        var totalHits   = 0;
        var sumRR       = 0;
        var sumWinRR    = 0;
        var sumLossRR   = 0;
        var wonCount    = 0;
        var lostCount   = 0;
        var pendCount   = 0;

        hits.forEach(function (h) {
            totalHits++;
            var rr = (h.targetRR && h.targetRR.ratio != null) ? h.targetRR.ratio : 0;
            sumRR += rr;
            var st = h.outcome ? h.outcome.status : 'pending';
            if (st === 'profit')      { sumWinRR  += rr; wonCount++;  }
            else if (st === 'stop')   { sumLossRR += rr; lostCount++; }
            else                      { pendCount++; }
        });

        var chrono = hits.slice().sort(function (a, b) {
            var ta = a.entry && a.entry.candle ? a.entry.candle.time : '';
            var tb = b.entry && b.entry.candle ? b.entry.candle.time : '';
            return String(ta).localeCompare(String(tb));
        });
        var stats = ptComputeTradeStats(chrono);

        var html = '';
        html += '<div class="pt-trades-summary-card">';
        if (label) {
            html += '<div style="font-size:0.78rem;font-weight:800;text-transform:uppercase;'
                 +  'letter-spacing:0.4px;color:#27ae60;margin-bottom:10px;">'
                 +  ptEscapeHtml(label) + '</div>';
        }
        html += '  <div class="pt-tsc-row">';

        html += '    <div class="pt-tsc-cell">';
        html += '      <span class="pt-tsc-label">Total Trades</span>';
        html += '      <span class="pt-tsc-value">' + totalHits + '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell">';
        html += '      <span class="pt-tsc-label">Total R:R</span>';
        html += '      <span class="pt-tsc-value">' + sumRR.toFixed(2) + '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-win">';
        html += '      <span class="pt-tsc-label">Won R:R (' + wonCount + ')</span>';
        html += '      <span class="pt-tsc-value">' + sumWinRR.toFixed(2) + '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-loss">';
        html += '      <span class="pt-tsc-label">Lost R:R (' + lostCount + ')</span>';
        html += '      <span class="pt-tsc-value">' + sumLossRR.toFixed(2) + '</span>';
        html += '    </div>';

        if (pendCount > 0) {
            html += '    <div class="pt-tsc-cell pt-tsc-pending">';
            html += '      <span class="pt-tsc-label">Pending</span>';
            html += '      <span class="pt-tsc-value">' + pendCount + '</span>';
            html += '    </div>';
        }

        html += '    <div class="pt-tsc-cell">';
        html += '      <span class="pt-tsc-label">Winrate</span>';
        html += '      <span class="pt-tsc-value pt-tsc-winrate-value">'
             +          stats.winrate.toFixed(2) + '%</span>';
        html += '      <span class="pt-tsc-sub">'
             +          stats.wins + 'W / ' + stats.losses + 'L'
             +          (stats.resolved ? ' of ' + stats.resolved + ' resolved' : ' — no resolved trades')
             +      '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-danger">';
        html += '      <span class="pt-tsc-label">Highest Consecutive Losses</span>';
        html += '      <span class="pt-tsc-value">' + stats.highestConsecutiveLosses + '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-danger">';
        html += '      <span class="pt-tsc-label">Highest Drawdown</span>';
        html += '      <span class="pt-tsc-value">' + stats.highestDrawdownPct.toFixed(2) + '%</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-info">';
        html += '      <span class="pt-tsc-label">Daily Trades Count</span>';
        html += '      <span class="pt-tsc-value">' + stats.dailyCount + ' day'
             +          (stats.dailyCount === 1 ? '' : 's') + '</span>';
        html += '      <span class="pt-tsc-sub">'
             +          'Highest: ' + stats.dailyHighest
             +          ' · Lowest: ' + stats.dailyLowest
             +          (stats.dailyAverage != null ? ' · Average: ' + stats.dailyAverage : '')
             +      '</span>';
        html += '    </div>';

        html += '    <div class="pt-tsc-cell pt-tsc-info">';
        html += '      <span class="pt-tsc-label">Weekly Trades Count</span>';
        html += '      <span class="pt-tsc-value">' + stats.weeklyCount + ' week'
             +          (stats.weeklyCount === 1 ? '' : 's') + '</span>';
        html += '      <span class="pt-tsc-sub">'
             +          'Highest: ' + stats.weeklyHighest
             +          ' · Lowest: ' + stats.weeklyLowest
             +          (stats.weeklyAverage != null ? ' · Average: ' + stats.weeklyAverage : '')
             +      '</span>';
        html += '    </div>';

        if (stats.weeklyDays && stats.weeklyDays.length) {
            html += '    <div class="pt-tsc-cell pt-tsc-info">';
            html += '      <span class="pt-tsc-label">Trading Days</span>';
            html += '      <span class="pt-tsc-value">' + stats.weeklyDays.length + ' day'
                 +          (stats.weeklyDays.length === 1 ? '' : 's') + '</span>';
            html += '      <span class="pt-tsc-sub pt-tsc-days">'
                 +          ptEscapeHtml(stats.weeklyDays.join(' · '))
                 +      '</span>';
            html += '    </div>';
        }

        html += '  </div>';
        html += '</div>';
        return html;
    }

    function ptScanTrades() {
        var out = [];
        if (!Array.isArray(PT_TREES) || !PT_TREES.length) return out;
        if (!PT_CANDLES.length) return out;

        PT_TREES.forEach(function (tree) {
            var roots = tree.roots || [];
            if (!roots.length) return;
            var trades = tree.trades || [];
            var drawings = tree.drawings || [];
            if (!trades.length && !drawings.length) return;

            var rootName = ptFirstRootName(tree);
            var hits = [];
            var seenEntryByRule = {};
            var matchedFlat = [];

            for (var i0 = 0; i0 < PT_CANDLES.length; i0++) {
                var matched = ptEvaluateTreeAt(tree, i0, 0, PT_CANDLES.length - 1);
                if (!matched.length) continue;

                var foundationMatch = matched[0];
                var foundationRoot  = foundationMatch.root;
                var foundationAbs   = foundationMatch.absIdx;

                if (!foundationRoot.timeframe || foundationRoot.timeframe !== PT_CURRENT_TIMEFRAME) continue;
                if (foundationAbs < 0 || foundationAbs >= PT_CANDLES.length) continue;

                matched.forEach(function (m, mi) {
                    var root = m.root;
                    var rc   = PT_CANDLES[m.absIdx];
                    if (!rc) return;

                    matchedFlat.push({
                        row_role:          'matched_root',
                        source_row_id:     root.id || null,
                        candle_record_id:  rc.id || null,
                        candle_time:       rc.time,
                        open_time:         rc.open_time,
                        close_time:        rc.close_time,
                        open:              rc.open,
                        high:              rc.high,
                        low:               rc.low,
                        close:             rc.close,
                        candle_center:     rc.candle_center,
                        body_center:       rc.body_center,
                        high_wick_center:  rc.high_wick_center,
                        low_wick_center:   rc.low_wick_center,
                        candle_width_center: rc.candle_width_center,
                        volume:            rc.volume,
                        candle_name:       root.candle_name,
                        price_level:       root.price_level,
                        candle_type:       root.candle_type,
                        candle_position:   root.candle_position,
                        candle_search:     root.candle_search,
                        operator:          root.operator,
                        is_foundation:     mi === 0 ? 1 : 0,
                        evaluation_priority: mi + 1,
                        status:            'matched'
                    });

                    (root.root_refs || []).forEach(function (ref) {
                        if (ref.row_role !== 'root_ref') return;
                        var refOff = parseInt(ref.candle_position, 10) || 0;
                        var refAbs = m.absIdx + refOff;
                        if (refAbs < 0 || refAbs >= PT_CANDLES.length) return;
                        var rfc = PT_CANDLES[refAbs];
                        if (!rfc) return;

                        matchedFlat.push({
                            row_role:          'matched_ref',
                            source_row_id:     ref.id || null,
                            candle_record_id:  rfc.id || null,
                            candle_time:       rfc.time,
                            open_time:         rfc.open_time,
                            close_time:        rfc.close_time,
                            open:              rfc.open,
                            high:              rfc.high,
                            low:               rfc.low,
                            close:             rfc.close,
                            candle_center:     rfc.candle_center,
                            body_center:       rfc.body_center,
                            high_wick_center:  rfc.high_wick_center,
                            low_wick_center:   rfc.low_wick_center,
                            candle_width_center: rfc.candle_width_center,
                            volume:            rfc.volume,
                            candle_name:       ref.candle_name,
                            price_level:       ref.price_level,
                            candle_type:       ref.candle_type,
                            candle_position:   ref.candle_position,
                            candle_search:     ref.candle_search,
                            operator:          root.operator,
                            evaluation_priority: mi + 1,
                            status:            'matched'
                        });

                        (ref.re_ref_pairs || []).forEach(function (pair) {
                            var a = pair.author, b = pair.referenced;
                            if (!a || !b) return;
                            matchedFlat.push({
                                row_role:          'matched_reref_author',
                                source_row_id:     a.id || null,
                                candle_record_id:  rfc.id || null,
                                candle_time:       rfc.time,
                                open_time:         rfc.open_time,
                                close_time:        rfc.close_time,
                                open:              rfc.open,
                                high:              rfc.high,
                                low:               rfc.low,
                                close:             rfc.close,
                                candle_center:     rfc.candle_center,
                                body_center:       rfc.body_center,
                                high_wick_center:  rfc.high_wick_center,
                                low_wick_center:   rfc.low_wick_center,
                                candle_width_center: rfc.candle_width_center,
                                volume:            rfc.volume,
                                candle_name:       a.candle_name,
                                price_level:       a.price_level,
                                candle_type:       a.candle_type,
                                candle_position:   a.candle_position,
                                candle_search:     a.candle_search,
                                operator:          a.operator,
                                evaluation_priority: mi + 1,
                                status:            'matched'
                            });
                            matchedFlat.push({
                                row_role:          'matched_reref_servant',
                                source_row_id:     b.id || null,
                                candle_record_id:  rfc.id || null,
                                candle_time:       rfc.time,
                                open_time:         rfc.open_time,
                                close_time:        rfc.close_time,
                                open:              rfc.open,
                                high:              rfc.high,
                                low:               rfc.low,
                                close:             rfc.close,
                                candle_center:     rfc.candle_center,
                                body_center:       rfc.body_center,
                                high_wick_center:  rfc.high_wick_center,
                                low_wick_center:   rfc.low_wick_center,
                                candle_width_center: rfc.candle_width_center,
                                volume:            rfc.volume,
                                candle_name:       b.candle_name,
                                price_level:       b.price_level,
                                candle_type:       b.candle_type,
                                candle_position:   b.candle_position,
                                candle_search:     b.candle_search,
                                operator:          a.operator,
                                evaluation_priority: mi + 1,
                                status:            'matched'
                            });
                        });
                    });
                });

                drawings.forEach(function (dr) {
                    var fromIdx = ptResolveSourceIndex(tree, foundationRoot, foundationAbs, dr.draw_from, i0);
                    if (fromIdx == null || fromIdx < 0 || fromIdx >= PT_CANDLES.length) return;
                    var fc = PT_CANDLES[fromIdx];
                    if (!fc) return;
                    matchedFlat.push({
                        row_role:              'matched_drawing',
                        source_row_id:         dr.id || null,
                        candle_record_id:      fc.id || null,
                        candle_time:           fc.time,
                        open_time:             fc.open_time,
                        close_time:            fc.close_time,
                        open:                  fc.open,
                        high:                  fc.high,
                        low:                   fc.low,
                        close:                 fc.close,
                        candle_center:         fc.candle_center,
                        body_center:           fc.body_center,
                        high_wick_center:      fc.high_wick_center,
                        low_wick_center:       fc.low_wick_center,
                        candle_width_center:   fc.candle_width_center,
                        volume:                fc.volume,
                        drawing_id:            dr.drawing_id,
                        drawing_tools:         dr.drawing_tools,
                        draw_from:             dr.draw_from,
                        draw_from_price_level: dr.draw_from_price_level,
                        draw_to:               dr.draw_to,
                        draw_to_price_level:   dr.draw_to_price_level,
                        drawing_color:         dr.drawing_color,
                        evaluation_priority:   1,
                        status:                'matched'
                    });
                });

                trades.forEach(function (trade, ti) {
                    var entry = ptResolveSourceObject(
                        tree, foundationRoot, foundationAbs,
                        trade.entry_from, i0,
                        trade.entry_from_price_level
                    );
                    if (!entry) return;

                    var exit = null;
                    if (trade.exit_at) {
                        exit = ptResolveSourceObject(
                            tree, foundationRoot, foundationAbs,
                            trade.exit_at, i0,
                            trade.exit_at_price_level
                        );
                    }

                    var target          = null;
                    var targetRR        = null;
                    var rrMode          = trade.target_rr_mode || trade.target || '';

                    if (rrMode === 'fixed_risk_reward' || rrMode === 'minimum_risk_reward') {
                        if (exit) {
                            var computed = ptComputeRiskRewardTarget(trade, entry, exit, rrMode);
                            if (computed) {
                                targetRR = computed;
                            }
                        }
                    } else if (trade.target &&
                               trade.target !== 'fixed_risk_reward' &&
                               trade.target !== 'minimum_risk_reward') {
                        target = ptResolveSourceObject(
                            tree, foundationRoot, foundationAbs,
                            trade.target, i0,
                            trade.target_price_level
                        );
                        if (target && exit) {
                            var dyn = ptComputeDynamicRiskReward(trade, entry, exit, target);
                            if (dyn) targetRR = dyn;
                        } else if (target) {
                            targetRR = {
                                ratio:     null,
                                price:     target.price,
                                direction: ptTradeDirection(trade),
                                risk:      null,
                                reward:    null,
                                mode:      'dynamic'
                            };
                        }
                    }

                    var key = ti + '|' + entry.idx;
                    if (seenEntryByRule[key]) return;
                    seenEntryByRule[key] = true;

                    var direction = ptTradeDirection(trade);
                    var targetPrice = (targetRR && targetRR.price != null)
                        ? targetRR.price
                        : (target && target.price != null ? target.price : null);
                    var outcome;
                    if (direction !== 0 && exit && exit.price != null) {
                        outcome = ptDetectTradeOutcome(entry, exit, targetPrice, direction, null);
                    } else {
                        outcome = { status: 'pending', candleIdx: null, candle: null, price: null };
                    }

                    hits.push({
                        trade:     trade,
                        entry:     entry,
                        exit:      exit,
                        target:    target,
                        targetRR:  targetRR,
                        direction: direction,
                        outcome:   outcome,
                        i0:        i0,
                        matched:   matched
                    });

                    var entryCandle = entry.candle;
                    var entryPrice  = (entry.price != null) ? entry.price : null;
                    var exitPrice   = (exit && exit.price != null) ? exit.price : null;
                    var tgtPrice    = (targetRR && targetRR.price != null)
                                        ? targetRR.price
                                        : (target && target.price != null ? target.price : null);

                    matchedFlat.push({
                        row_role:          'matched_trade',
                        source_row_id:     trade.id || null,
                        candle_record_id:  entryCandle ? (entryCandle.id || null) : null,
                        candle_time:       entryCandle ? entryCandle.time       : null,
                        open_time:         entryCandle ? entryCandle.open_time  : null,
                        close_time:        entryCandle ? entryCandle.close_time : null,
                        open:              entryCandle ? entryCandle.open       : null,
                        high:              entryCandle ? entryCandle.high       : null,
                        low:               entryCandle ? entryCandle.low        : null,
                        close:             entryCandle ? entryCandle.close      : null,
                        candle_center:     entryCandle ? entryCandle.candle_center       : null,
                        body_center:       entryCandle ? entryCandle.body_center         : null,
                        high_wick_center:  entryCandle ? entryCandle.high_wick_center    : null,
                        low_wick_center:   entryCandle ? entryCandle.low_wick_center     : null,
                        candle_width_center: entryCandle ? entryCandle.candle_width_center : null,
                        volume:            entryCandle ? entryCandle.volume              : null,
                        candle_name:       foundationRoot.candle_name,
                        price_level:       trade.entry_from_price_level,
                        candle_type:       foundationRoot.candle_type,
                        candle_position:   foundationRoot.candle_position,
                        candle_search:     foundationRoot.candle_search,
                        operator:          foundationRoot.operator,
                        order_type:        trade.order_type,
                        entry_from:        trade.entry_from,
                        entry_from_price_level: trade.entry_from_price_level,
                        exit_at:           trade.exit_at,
                        exit_at_price_level: trade.exit_at_price_level,
                        target:            (rrMode === 'minimum_risk_reward' || rrMode === 'fixed_risk_reward')
                                            ? rrMode
                                            : (trade.target || ''),
                        target_price_level: trade.target_price_level || null,

                        entry_price:       entryPrice,
                        exit_price:        exitPrice,
                        target_price:      tgtPrice,

                        resolved_price:    tgtPrice,
                        resolved_direction: direction,
                        resolved_risk:     targetRR ? targetRR.risk   : null,
                        resolved_reward:   targetRR ? targetRR.reward : null,
                        resolved_ratio:    targetRR ? targetRR.ratio  : null,

                        outcome_status:    outcome.status,
                        outcome_candle_time: outcome.candle ? outcome.candle.time : null,
                        outcome_price:     outcome.price,
                        evaluation_priority: ti + 1,
                        status:            outcome.status === 'profit' ? 'resolved_win'
                                            : outcome.status === 'stop' ? 'resolved_loss'
                                            : 'pending'
                    });
                });
            }

            if (hits.length || matchedFlat.length) {
                hits.sort(function (a, b) {
                    var ta = a.entry && a.entry.candle ? a.entry.candle.time : '';
                    var tb = b.entry && b.entry.candle ? b.entry.candle.time : '';
                    if (ta === tb) {
                        var xa = a.exit && a.exit.candle ? a.exit.candle.time : '';
                        var xb = b.exit && b.exit.candle ? b.exit.candle.time : '';
                        return xb.localeCompare(xa);
                    }
                    return String(tb).localeCompare(String(ta));
                });
                out.push({
                    rootName: rootName,
                    tree: tree,
                    hits: hits,
                    _matchedFlat: matchedFlat
                });
            }
        });

        return out;
    }

    function ptRenderTradeHitCard(hit, idx) {
        var trade     = hit.trade;
        var orderType = (trade.order_type || '').toLowerCase();
        var orderCls  = orderType || 'buy';

        var entryLvl  = trade.entry_from_price_level || '';
        var exitLvl   = trade.exit_at_price_level || '';
        var targetLvl = trade.target_price_level || '';

        var entryPrice  = ptResolvedPrice(hit.entry);
        var exitPrice   = hit.exit ? ptResolvedPrice(hit.exit) : null;
        var targetPrice = hit.target ? ptResolvedPrice(hit.target) : null;

        var html = '';
        html += '<div class="pt-trade-card">';
        html += '  <div class="pt-trade-card-head">';
        html += '    <span class="pt-trade-card-title">#Match ' + (idx + 1) + '</span>';
        html += '    <span class="pt-trade-order-badge ' + ptEscapeHtml(orderCls) + '">' + ptEscapeHtml(trade.order_type || '—') + '</span>';
        html += '  </div>';

        html += '  <div class="pt-trade-kv">';

        html += '    <div><span class="k">Entry</span> <span class="v">' + ptEscapeHtml(hit.entry ? hit.entry.name : '—') + '</span></div>';
        html += '    <div><span class="k">Entry Lvl ' + ptEscapeHtml(entryLvl || '—') + ':</span> '
             +        '<span class="v">' + ptFormatPrice(entryPrice) + '</span></div>';

        html += '    <div><span class="k">Exit</span> <span class="v">' + ptEscapeHtml(hit.exit ? hit.exit.name : '—') + '</span></div>';
        html += '    <div><span class="k">Exit Lvl ' + ptEscapeHtml(exitLvl || '—') + ':</span> '
             +        '<span class="v">' + (exitPrice == null ? '—' : ptFormatPrice(exitPrice)) + '</span></div>';

        var targetLabel;
        var isRR = (trade.target_rr_mode === 'fixed_risk_reward' ||
                    trade.target_rr_mode === 'minimum_risk_reward' ||
                    trade.target === 'fixed_risk_reward' ||
                    trade.target === 'minimum_risk_reward');

        if (isRR) {
            targetLabel = ptTargetDisplay(trade);
            html += '    <div><span class="k">Target</span> <span class="v">' + ptEscapeHtml(targetLabel) + '</span></div>';

            if (hit.targetRR) {
                html += '    <div><span class="k">Target Price' + (hit.targetRR.ratio != null ? ' (1:' + ptEscapeHtml(String(hit.targetRR.ratio)) + ')' : '') + ':</span> '
                     +        '<span class="v">' + ptFormatPrice(hit.targetRR.price) + '</span></div>';
                if (hit.targetRR.risk != null) {
                    html += '    <div><span class="k">Risk Distance:</span> '
                         +        '<span class="v">' + ptFormatPrice(hit.targetRR.risk) + '</span></div>';
                }
                if (hit.targetRR.reward != null) {
                    html += '    <div><span class="k">Reward Distance:</span> '
                         +        '<span class="v">' + ptFormatPrice(hit.targetRR.reward) + '</span></div>';
                }
            } else {
                html += '    <div><span class="k">Target Price:</span> '
                     +        '<span class="v">—</span></div>';
            }
        } else {
            if (hit.target) {
                targetLabel = hit.target.name;
            } else {
                targetLabel = ptSourceFriendlyName(hit.tree, trade.target) || '—';
            }
            html += '    <div><span class="k">Target</span> <span class="v">' + ptEscapeHtml(targetLabel) + '</span></div>';

            if (hit.target) {
                html += '    <div><span class="k">Target Lvl ' + ptEscapeHtml(targetLvl || '—') + ':</span> '
                     +        '<span class="v">' + ptFormatPrice(targetPrice) + '</span></div>';
            }

            if (hit.targetRR) {
                if (hit.targetRR.ratio != null) {
                    html += '    <div><span class="k">Trade Risk Reward:</span> '
                         +        '<span class="v">1:' + ptEscapeHtml(hit.targetRR.ratio.toFixed(2)) + '</span></div>';
                }
                if (hit.targetRR.risk != null) {
                    html += '    <div><span class="k">Risk Distance:</span> '
                         +        '<span class="v">' + ptFormatPrice(hit.targetRR.risk) + '</span></div>';
                }
                if (hit.targetRR.reward != null) {
                    html += '    <div><span class="k">Reward Distance:</span> '
                         +        '<span class="v">' + ptFormatPrice(hit.targetRR.reward) + '</span></div>';
                }
            }
        }

        if (hit.outcome) {
            var statusText, statusCls;
            if (hit.outcome.status === 'stop') {
                statusText = 'Stoploss hit';
                statusCls  = 'dn';
            } else if (hit.outcome.status === 'profit') {
                statusText = 'Profit reached';
                statusCls  = 'up';
            } else {
                statusText = 'Pending';
                statusCls  = '';
            }
            html += '    <div><span class="k">Status:</span> '
                 +        '<span class="v ' + statusCls + '">' + ptEscapeHtml(statusText)
                 +        (hit.outcome.candle
                            ? ' (' + ptEscapeHtml(ptFormatTime(hit.outcome.candle.time)) + ')'
                            : '')
                 +        '</span></div>';
        }

        html += '  </div>';

        html += '  <div class="pt-trade-candles">';
        html += '    <div class="pt-trade-candles-title">Candle details</div>';
        html += '    <div class="pt-trade-candle-row">';
        html += '      <span class="head">Source</span><span class="head">Time</span><span class="head">High</span><span class="head">Low</span><span class="head">Close</span>';
        html += '    </div>';
        html += ptTradeCandleRowFromHit('Entry',  hit.entry);
        html += ptTradeCandleRowFromHit('Exit',   hit.exit);
        if (hit.target) html += ptTradeCandleRowFromHit('Target', hit.target);
        html += '  </div>';

        html += '</div>';
        return html;
    }

    function ptTradeCandleRowFromHit(label, resolved) {
        if (!resolved || !resolved.candle) {
            return '<div class="pt-trade-candle-row">' +
                '<span>' + ptEscapeHtml(label) + '</span>' +
                '<span>—</span><span>—</span><span>—</span><span>—</span>' +
                '</div>';
        }
        var c  = resolved.candle;
        var pl = resolved.priceLevel;
        var displayPrice = (pl && c[pl] != null) ? c[pl] : c.close;
        var cls = (c.close >= c.open) ? 'up' : 'dn';
        return '<div class="pt-trade-candle-row">' +
            '<span>' + ptEscapeHtml(resolved.name) + '</span>' +
            '<span>' + ptEscapeHtml(ptFormatTime(c.time)) + '</span>' +
            '<span class="' + cls + '">' + ptFormatPrice(c.high) + '</span>' +
            '<span class="' + cls + '">' + ptFormatPrice(c.low)  + '</span>' +
            '<span class="' + cls + '">' + ptFormatPrice(displayPrice) + '</span>' +
            '</div>';
    }

    function ptResolvedPrice(resolved, fallbackLevel) {
        if (!resolved) return null;
        if (resolved.price != null) return resolved.price;
        var c = resolved.candle;
        if (!c) return null;
        var lvl = resolved.priceLevel || fallbackLevel;
        if (lvl && c[lvl] != null) return c[lvl];
        return c.close;
    }

    function ptComputeRiskRewardTarget(trade, entry, exitObj, rrMode) {
        if (!trade || !entry || !exitObj) return null;

        var orderType = (trade.order_type || '').toLowerCase();
        var direction;
        if (orderType === 'buy' || orderType === 'buy_stop' || orderType === 'buy_limit') {
            direction = 1;
        } else if (orderType === 'sell' || orderType === 'sell_stop' || orderType === 'sell_limit') {
            direction = -1;
        } else {
            return null;
        }

        var entryPrice = (entry.price != null) ? entry.price : null;
        var exitPrice  = (exitObj.price != null) ? exitObj.price : null;
        if (entryPrice == null || exitPrice == null) return null;

        var ratioStr = (rrMode === 'minimum_risk_reward')
            ? (PT_ACCOUNT_MGMT.minimum_risk_reward || '0')
            : (PT_ACCOUNT_MGMT.fixed_risk_reward   || '0');
        var ratio = parseFloat(ratioStr);
        if (isNaN(ratio) || ratio <= 0) return null;

        var risk   = Math.abs(entryPrice - exitPrice);
        var reward = risk * ratio;
        var price  = entryPrice + direction * reward;

        return {
            ratio:     ratio,
            price:     price,
            direction: direction,
            risk:      risk,
            reward:    reward,
            mode:      rrMode
        };
    }

    function ptComputeDynamicRiskReward(trade, entry, exitObj, targetObj) {
        if (!trade || !entry || !exitObj || !targetObj) return null;

        var orderType = (trade.order_type || '').toLowerCase();
        var direction;
        if (orderType === 'buy' || orderType === 'buy_stop' || orderType === 'buy_limit') {
            direction = 1;
        } else if (orderType === 'sell' || orderType === 'sell_stop' || orderType === 'sell_limit') {
            direction = -1;
        } else {
            return null;
        }

        var entryPrice  = (entry.price != null)     ? entry.price     : null;
        var exitPrice   = (exitObj.price != null)   ? exitObj.price   : null;
        var targetPrice = (targetObj.price != null) ? targetObj.price : null;
        if (entryPrice == null || exitPrice == null || targetPrice == null) return null;

        var risk   = Math.abs(entryPrice - exitPrice);
        if (risk <= 0) return null;

        var reward = Math.abs(targetPrice - entryPrice);
        var ratio  = reward / risk;

        return {
            ratio:     ratio,
            price:     targetPrice,
            direction: direction,
            risk:      risk,
            reward:    reward,
            mode:      'dynamic'
        };
    }

    function ptDetectTradeOutcome(entry, exitObj, targetPrice, direction, horizonIdx) {
        if (!entry || !entry.candle || !exitObj || exitObj.price == null) {
            return { status: 'pending', candleIdx: null, candle: null, price: null };
        }
        var entryIdx = entry.idx;
        if (entryIdx == null || entryIdx < 0 || entryIdx >= PT_CANDLES.length) {
            return { status: 'pending', candleIdx: null, candle: null, price: null };
        }
        var stopPrice = exitObj.price;
        if (stopPrice == null) {
            return { status: 'pending', candleIdx: null, candle: null, price: null };
        }
        if (targetPrice == null || isNaN(targetPrice)) {
            for (var s = entryIdx; s < PT_CANDLES.length; s++) {
                if (horizonIdx != null && s > horizonIdx) break;
                var cs = PT_CANDLES[s];
                if (direction > 0  && cs.low  <= stopPrice) return { status: 'stop', candleIdx: s, candle: cs, price: stopPrice };
                if (direction < 0  && cs.high >= stopPrice) return { status: 'stop', candleIdx: s, candle: cs, price: stopPrice };
            }
            return { status: 'pending', candleIdx: null, candle: null, price: null };
        }

        var lastIdx = (horizonIdx != null) ? Math.min(horizonIdx, PT_CANDLES.length - 1) : (PT_CANDLES.length - 1);

        for (var i = entryIdx; i <= lastIdx; i++) {
            var c = PT_CANDLES[i];
            var hitStop = false;
            var hitTarget = false;

            if (direction > 0) {
                hitStop   = (c.low  <= stopPrice);
                hitTarget = (c.high >= targetPrice);
            } else {
                hitStop   = (c.high >= stopPrice);
                hitTarget = (c.low  <= targetPrice);
            }

            if (hitStop)   return { status: 'stop',   candleIdx: i, candle: c, price: stopPrice };
            if (hitTarget) return { status: 'profit', candleIdx: i, candle: c, price: targetPrice };
        }

        return { status: 'pending', candleIdx: null, candle: null, price: null };
    }

    function ptTradeDirection(trade) {
        if (!trade) return 0;
        var ot = (trade.order_type || '').toLowerCase();
        if (ot === 'buy' || ot === 'buy_stop' || ot === 'buy_limit') return 1;
        if (ot === 'sell' || ot === 'sell_stop' || ot === 'sell_limit') return -1;
        return 0;
    }
    function ptTargetDisplay(trade) {
        if (!trade) return '—';
        var tgt = trade.target || '';
        var rr  = trade.target_rr_mode || '';
        if (tgt === 'fixed_risk_reward' || rr === 'fixed_risk_reward') {
            return 'Fixed R:R';
        }
        if (tgt === 'minimum_risk_reward' || rr === 'minimum_risk_reward') {
            return 'Minimum R:R';
        }
        return tgt || '—';
    }

    // ==================== CONFIG PERSISTENCE ====================
    function ptSaveProgrammeConfiguration(snapshot) {
        var body = 'save_configuration=1'
                 + '&programmeid=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&payload='     + encodeURIComponent(JSON.stringify(snapshot));

        ptDebugLine('net', 'POST programme_configuration.php (save_configuration)', snapshot);

        fetch('programme_configuration.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            ptDebugLine('net', 'save_configuration response', data);
            if (!data || !data.success) {
                if (typeof window.pcShowError === 'function') {
                    window.pcShowError((data && data.message) ? data.message : 'Unknown error while saving configuration.');
                }
                return;
            }

            if (data.accountManagement) {
                PT_ACCOUNT_MGMT = data.accountManagement;
                ptDebugLine('cfg', 'Account Management updated', PT_ACCOUNT_MGMT);
            }

            PT_TREES = ptGroupRowsIntoTrees(data.rows || []);
            if (typeof window.pcSetTrees === 'function') window.pcSetTrees(PT_TREES);

            PT_CONFIGURED_ROWS_BY_TF = {};
            PT_PERSISTED_MATCHES     = [];
            PT_PERSISTED_LOADED      = false;
            PT_PROJECTED_BY_TF       = {};
            PT_PROJECTION_MODES      = [];
            PT_LAST_PERSISTED_SIG    = '';
            ptUpdateProjBanner();

            ptRescanTradeMatches(true);
            ptScheduleDraw();
            if (typeof window.pcCloseConfiguration === 'function') window.pcCloseConfiguration();
            if (typeof window.pcOnSaveSuccess === 'function') window.pcOnSaveSuccess(data);
        })
        .catch(function (err) {
            ptDebugLine('error', 'save_configuration network error: ' + (err && err.message ? err.message : 'Unknown'));
            if (typeof window.pcShowError === 'function') {
                window.pcShowError('Network error while saving configuration: ' + (err && err.message ? err.message : 'Unknown'));
            }
        });
    }

    function ptGroupRowsIntoTrees(rows) {
        var byTree = {};
        rows.forEach(function (r) {
            if (!byTree[r.tree_id]) {
                byTree[r.tree_id] = { tree_id: r.tree_id, roots: [], drawings: [], trades: [], _orphans: [] };
            }
            if (r.row_role === 'root')             byTree[r.tree_id].roots.push(r);
            else if (r.row_role === 'drawing')     byTree[r.tree_id].drawings.push(r);
            else if (r.row_role === 'trade')       byTree[r.tree_id].trades.push(r);
            else                                   byTree[r.tree_id]._orphans.push(r);
        });

        Object.keys(byTree).forEach(function (tid) {
            var t = byTree[tid];
            var orphans = t._orphans || [];

            var refsByRoot = {};
            orphans.forEach(function (row) {
                if (row.row_role === 'root_ref') {
                    row.re_ref_pairs = [];
                    if (!refsByRoot[row.parent_id]) refsByRoot[row.parent_id] = [];
                    refsByRoot[row.parent_id].push(row);
                }
            });

            orphans.forEach(function (row) {
                if (row.row_role === 're_ref_author' && row.parent_id != null) {
                    Object.keys(refsByRoot).forEach(function (rootId) {
                        refsByRoot[rootId].forEach(function (ref) {
                            if (ref.id === row.parent_id) {
                                var servant = null;
                                orphans.forEach(function (c) {
                                    if (c.row_role === 're_ref_servant' && c.parent_id === row.id) servant = c;
                                });
                                if (servant) ref.re_ref_pairs.push({ author: row, referenced: servant });
                            }
                        });
                    });
                }
            });

            t.roots.forEach(function (root) {
                root.root_refs = refsByRoot[root.id] || [];
            });

            t.roots.sort(function (a, b) {
                var pa = parseInt(a.evaluation_priority, 10) || 1;
                var pb = parseInt(b.evaluation_priority, 10) || 1;
                return pa - pb;
            });

            delete t._orphans;
        });

        return Object.keys(byTree).map(function (k) { return byTree[k]; });
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
            // Mark source TFs used for active projections
            if (PT_PROJECTION_MODES.some(function (m) { return m.sourceTF === tf; })) {
                cls += ' pt-tf-proj-src';
            }
            html += '<button type="button" class="' + cls + '" data-tf="' + ptEscapeHtml(tf) + '" '
                  + 'onclick="ptSelectTimeframe(\'' + tf.replace(/'/g, "\\'") + '\')">'
                  + ptEscapeHtml(tf) + '</button>';
        });
        strip.innerHTML = html;
    }
    function ptSelectTimeframe(tf) {
        if (!tf) return;
        if (tf === PT_CURRENT_TIMEFRAME && !PT_PROJECTION_MODES.length) return;
        ptSwitchToTimeframe(tf);
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
        PT_PERSISTED_MATCHES     = [];
        PT_PERSISTED_LOADED      = false;
        PT_PERSISTED_DIRTY       = true;
        PT_PROJECTED_BY_TF       = {};
        PT_CONFIGURED_ROWS_BY_TF = {};
        PT_REQUESTED_AMOUNT      = PT_DEFAULT_AMOUNT;
        PT_LAST_PERSISTED_SIG    = '';
        PT_FULL_HISTORY_DONE     = false;
        ptUpdateSymbolLabel();
        ptCloseSymbolModal();
        ptRefreshTradesButton();
        ptUpdateProjBanner();
        ptLoadChart();

        // Re-project any active projection modes for the new symbol.
        PT_PROJECTION_MODES.forEach(function (m) {
            ptEnsureConfiguredRows(m.sourceTF, function () {
                ptProjectConfiguredRowsOnto(m.sourceTF, m.targetTF);
            });
        });
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
        PT_FULL_HISTORY_DONE = false;
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
        if (subEl)   subEl.textContent   = PT_CURRENT_SYMBOL + ' · ' + PT_CURRENT_TIMEFRAME
            + (PT_PROJECTION_MODES.length ? '  (projected)' : '');

        var rows = [
            ['Timestamp',            c.time],
            ['Open time',            c.open_time || '—'],
            ['Close time',           c.close_time || '—'],
            ['Open',                 ptFormatPrice(c.open)],
            ['High',                 ptFormatPrice(c.high)],
            ['Low',                  ptFormatPrice(c.low)],
            ['Close',                ptFormatPrice(c.close)],
            ['Candle center',        c.candle_center       != null ? ptFormatPrice(c.candle_center)       : '—'],
            ['Body center',          c.body_center         != null ? ptFormatPrice(c.body_center)         : '—'],
            ['High wick center',     c.high_wick_center    != null ? ptFormatPrice(c.high_wick_center)    : '—'],
            ['Low wick center',      c.low_wick_center     != null ? ptFormatPrice(c.low_wick_center)     : '—'],
            ['Candle width center',  c.candle_width_center != null ? ptFormatPrice(c.candle_width_center) : '—'],
            ['Volume',               c.volume              != null ? String(c.volume)                     : '—'],
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
        ptDebugLine('data', 'Loading chart', {
            symbol: PT_CURRENT_SYMBOL, timeframe: PT_CURRENT_TIMEFRAME,
            projections: PT_PROJECTION_MODES.map(function (m) { return m.sourceTF + '→' + m.targetTF; })
        });
        PT_LOAD_TOKEN++;
        PT_CANDLES = [];
        PT_HOVER_INDEX = -1;
        PT_CURSOR_Y = null;
        PT_HAS_MORE_OLDER = true;
        PT_IS_LOADING_OLDER = false;
        PT_INITIAL_LOADED = false;
        PT_CONSECUTIVE_ERRORS = 0;
        PT_FULL_HISTORY_DONE = false;
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
                ptDebugLine('error', 'fetch_candles failed: ' + (data.message || ''));
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
                ptRescanTradeMatches(true);
                return;
            }
            PT_HAS_MORE_OLDER = !!data.has_more && chunk.length > 0;
            if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) PT_HAS_MORE_OLDER = false;
            PT_IS_LOADING_OLDER = false;
            ptUpdateJumpLatestVisibility();

            if (isInitial) {
                ptAutoLoadUpToTarget(token);
            }
        })
        .catch(function (err) {
            if (token !== PT_LOAD_TOKEN) return;
            ptDebugLine('error', 'fetch_candles network error: ' + (err && err.message ? err.message : ''));
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
            ptAfterChartLoaded();
            return;
        }
        if (!PT_HAS_MORE_OLDER) {
            ptShowBgLoading(false); ptFitViewportToCandles(); ptScheduleDraw();
            ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility();
            ptAfterChartLoaded();
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
                ptAfterChartLoaded();
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

    /**
     * Called once the chart has finished loading its initial batch.
     * Persists the native snapshot. Projections are refreshed here too.
     */
    function ptAfterChartLoaded() {
        if (!PT_CANDLES.length) return;

        if (PT_PROJECTION_MODES.length) {
            // Re-evaluate outcomes against the freshly loaded candles.
            ptReevaluateProjectedTradeOutcomes();

            // Re-project any source TFs whose projected rows aren't fully
            // represented in the loaded candle set.
            var timeSet = {};
            PT_CANDLES.forEach(function (c) { timeSet[c.time] = true; });

            PT_PROJECTION_MODES.forEach(function (m) {
                var list = PT_PROJECTED_BY_TF[m.sourceTF] || [];
                var missing = !list.length;
                if (!missing) {
                    for (var i = 0; i < list.length; i++) {
                        if (!timeSet[list[i].candle_time]) { missing = true; break; }
                    }
                }
                if (missing) {
                    ptDebugLine('data', 'Re-projecting after chart load', {
                        sourceTF: m.sourceTF, candleCount: PT_CANDLES.length
                    });
                    ptEnsureConfiguredRows(m.sourceTF, function () {
                        ptProjectConfiguredRowsOnto(m.sourceTF, m.targetTF);
                    });
                }
            });

            ptUpdateProjBanner();
            ptRefreshTradesButton();
            ptScheduleDraw();
            return;
        }

        // Native mode
        if (PT_HAS_MORE_OLDER && !PT_FULL_HISTORY_DONE) {
            ptDebugLine('data', 'Chart loaded — deferring persist until full history', {
                candles: PT_CANDLES.length, hasMore: PT_HAS_MORE_OLDER
            });
            return;
        }

        if (!PT_FULL_HISTORY_DONE) {
            PT_FULL_HISTORY_DONE = true;
        }

        ptDebugLine('data', 'Post-load scan (native)', {
            symbol: PT_CURRENT_SYMBOL,
            timeframe: PT_CURRENT_TIMEFRAME,
            candles: PT_CANDLES.length
        });
        ptRescanTradeMatches(true);

        var tradesModal = document.getElementById('ptTradesModal');
        if (tradesModal && tradesModal.classList.contains('active')) {
            ptRenderTradesResults();
        }
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

        // ---- Draw native programme drawings ----
        ptDrawProgrammeDrawings(pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible);

        // ---- Draw ALL projected drawings (from every source TF) ----
        // We iterate over PT_PROJECTION_MODES and call a variant of the
        // projected-drawings renderer that uses that TF's row set.
        if (PT_PROJECTION_MODES.length) {
            PT_PROJECTION_MODES.forEach(function (m) {
                var rows = PT_PROJECTED_BY_TF[m.sourceTF] || [];
                if (!rows.length) return;
                ptDrawProjectedDrawingRows(rows, pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible);
            });
        }

        // ---- Long/short overlays ----
        if (PT_LONG_SHORT_ON) {
            // Native overlays
            ptDrawLongShortOverlays(pToY, baseX, step, chartTop, chartBottom, firstVisible, lastVisible);
            // Projected overlays
            if (PT_PROJECTION_MODES.length) {
                PT_PROJECTION_MODES.forEach(function (m) {
                    var rows = PT_PROJECTED_BY_TF[m.sourceTF] || [];
                    if (!rows.length) return;
                    ptDrawProjectedLongShortRows(rows, pToY, baseX, step, chartTop, chartBottom, firstVisible, lastVisible);
                });
            }
        }

        PT_CANVAS._ptStep     = step;
        PT_CANVAS._ptBaseX    = baseX;
        PT_CANVAS._ptChartTop = chartTop;
        PT_CANVAS._ptChartBot = chartBottom;

        ptUpdateJumpLatestVisibility();
    }

    // ============================================================
    // DRAWING ENGINE (multi-root team evaluation) — native mode
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
            case '=':  return a === b;
            default:   return true;
        }
    }

    function ptResolvePeerIndices(row, rootIdx, firstVisible, lastVisible) {
        var pos = parseInt(row.candle_position, 10);
        if (isNaN(pos)) pos = 0;
        var search = (row.candle_search || '').toLowerCase();
        if (pos === 0 && search === '') return [rootIdx];
        if (search === 'all') {
            var out = [], step = pos >= 0 ? 1 : -1;
            for (var j = 0; j !== pos + step; j += step) out.push(rootIdx + j);
            return out;
        }
        return [ rootIdx + pos ];
    }

    function ptRootCandidateIndices(firstVisible, lastVisible) {
        var out = [];
        for (var k = firstVisible; k <= lastVisible; k++) out.push(k);
        return out;
    }

    function ptRootMatchesAt(root, absIdx, firstVisible, lastVisible) {
        if (!root) return false;
        if (!root.timeframe || root.timeframe !== PT_CURRENT_TIMEFRAME) return false;
        if (absIdx < 0 || absIdx >= PT_CANDLES.length) return false;

        var rootCandle = PT_CANDLES[absIdx];
        if (!rootCandle) return false;
        if (!ptCandleTypeMatches(root.candle_type, rootCandle)) return false;
        if (!root.price_level) return false;
        var rootVal = rootCandle[root.price_level];
        if (rootVal == null) return false;

        var refs = root.root_refs || [];
        for (var r = 0; r < refs.length; r++) {
            if (refs[r].row_role !== 'root_ref') continue;
            if (!ptRefMatches(refs[r], root, rootVal, absIdx, firstVisible, lastVisible)) {
                return false;
            }
        }
        return true;
    }

    function ptRefMatches(ref, authorRow, authorVal, absIdx, firstVisible, lastVisible) {
        if (!ref.price_level) return false;
        var idxs = ptResolvePeerIndices(ref, absIdx, firstVisible, lastVisible);
        if (!idxs.length) return false;
        var authorOp = authorRow.operator;
        if (!authorOp) return false;

        var checked = 0;
        for (var k = 0; k < idxs.length; k++) {
            var idx = idxs[k];
            if (idx < 0 || idx >= PT_CANDLES.length) return false;
            var c = PT_CANDLES[idx];
            if (!c) return false;
            if (!ptCandleTypeMatches(ref.candle_type, c)) return false;
            var v = c[ref.price_level];
            if (v == null) return false;
            checked++;
            if (!ptCompare(authorVal, authorOp, v)) return false;
        }
        if (checked === 0) return false;

        var pairs = ref.re_ref_pairs || [];
        for (var p = 0; p < pairs.length; p++) {
            if (!ptReRefPairMatches(pairs[p], absIdx, firstVisible, lastVisible)) return false;
        }
        return true;
    }

    function ptReRefPairMatches(pair, absIdx, firstVisible, lastVisible) {
        if (!pair) return true;
        var a = pair.author;
        var b = pair.referenced;
        if (!a || !b) return false;
        if (!a.price_level || !b.price_level) return false;
        if (!a.operator) return false;

        var aIdxs = ptResolvePeerIndices(a, absIdx, firstVisible, lastVisible);
        var bIdxs = ptResolvePeerIndices(b, absIdx, firstVisible, lastVisible);
        if (!aIdxs.length || !bIdxs.length) return false;

        var checked = 0;
        for (var ai = 0; ai < aIdxs.length; ai++) {
            var aIdx = aIdxs[ai];
            if (aIdx < 0 || aIdx >= PT_CANDLES.length) return false;
            var ac = PT_CANDLES[aIdx];
            if (!ac) return false;
            if (!ptCandleTypeMatches(a.candle_type, ac)) return false;
            var av = ac[a.price_level];
            if (av == null) return false;
            for (var bi = 0; bi < bIdxs.length; bi++) {
                var bIdx = bIdxs[bi];
                if (bIdx < 0 || bIdx >= PT_CANDLES.length) return false;
                var bc = PT_CANDLES[bIdx];
                if (!bc) return false;
                if (!ptCandleTypeMatches(b.candle_type, bc)) return false;
                var bv = bc[b.price_level];
                if (bv == null) return false;
                checked++;
                if (!ptCompare(av, a.operator, bv)) return false;
            }
        }
        return checked > 0;
    }

    function ptEvaluateTreeAt(tree, i0, firstVisible, lastVisible) {
        var roots = tree.roots || [];
        if (!roots.length) return [];

        var sorted = roots.slice().sort(function (a, b) {
            var pa = parseInt(a.evaluation_priority, 10) || 1;
            var pb = parseInt(b.evaluation_priority, 10) || 1;
            return pa - pb;
        });

        var foundation = sorted[0];
        var fOff = parseInt(foundation.candle_position, 10);
        if (isNaN(fOff)) fOff = 0;
        var fAbs = i0 + fOff;

        if (!ptRootMatchesAt(foundation, fAbs, firstVisible, lastVisible)) {
            return [];
        }

        var matched = [{ root: foundation, absIdx: fAbs }];

        for (var r = 1; r < sorted.length; r++) {
            var hr  = sorted[r];
            var off = parseInt(hr.candle_position, 10);
            if (isNaN(off)) off = 0;
            var hAbs = i0 + off;
            if (ptRootMatchesAt(hr, hAbs, firstVisible, lastVisible)) {
                matched.push({ root: hr, absIdx: hAbs });
            }
        }
        return matched;
    }

    function ptFirstRootRef(root) {
        var refs = root.root_refs || [];
        for (var i = 0; i < refs.length; i++) {
            if (refs[i].row_role === 'root_ref') return refs[i];
        }
        return refs[0] || null;
    }

    function ptResolveSourceIndex(tree, ownerRoot, ownerRootAbs, sourceKey, treeAnchor) {
        if (!sourceKey) return null;

        var bareKey = sourceKey;
        var axisMatch = /^(.+)_axis$/.exec(sourceKey);
        if (axisMatch) bareKey = axisMatch[1];
        var specMatch = /^(.+)_specific_price_level$/.exec(sourceKey);
        if (specMatch) bareKey = specMatch[1];

        if (bareKey === 'ROOT') return ownerRootAbs;

        var refMatch = /^ROOT:(\d+):REF:(\d+)$/.exec(bareKey);
        if (refMatch) {
            var rIdx   = parseInt(refMatch[1], 10);
            var refIdx = parseInt(refMatch[2], 10);
            var tRoot  = (tree.roots || [])[rIdx];
            if (!tRoot) return null;
            var ref    = (tRoot.root_refs || [])[refIdx];
            if (!ref) return null;
            var rOff   = parseInt(tRoot.candle_position, 10) || 0;
            var refOff = parseInt(ref.candle_position, 10) || 0;
            return treeAnchor + rOff + refOff;
        }

        if (/^REF:\d+$/.test(bareKey)) {
            var ref2 = ptFirstRootRef(ownerRoot);
            if (!ref2) return null;
            var ref2Off = parseInt(ref2.candle_position, 10) || 0;
            return ownerRootAbs + ref2Off;
        }

        if ((ownerRoot.candle_name || '').trim() === bareKey) {
            return ownerRootAbs;
        }

        var ownerRefs = ownerRoot.root_refs || [];
        for (var oj = 0; oj < ownerRefs.length; oj++) {
            var orf = ownerRefs[oj];
            if (orf.row_role && orf.row_role !== 'root_ref') continue;
            if ((orf.candle_name || '').trim() === bareKey) {
                var oRefOff = parseInt(orf.candle_position, 10) || 0;
                return ownerRootAbs + oRefOff;
            }
        }

        var treeRoots = tree.roots || [];
        for (var r = 0; r < treeRoots.length; r++) {
            var rt = treeRoots[r];
            if ((rt.candle_name || '').trim() === bareKey) {
                var rOff2 = parseInt(rt.candle_position, 10) || 0;
                return treeAnchor + rOff2;
            }
        }
        for (var r2 = 0; r2 < treeRoots.length; r2++) {
            var root2 = treeRoots[r2];
            var ownerOff = parseInt(root2.candle_position, 10) || 0;
            var refs2 = root2.root_refs || [];
            for (var j = 0; j < refs2.length; j++) {
                var rf = refs2[j];
                if (rf.row_role && rf.row_role !== 'root_ref') continue;
                if ((rf.candle_name || '').trim() === bareKey) {
                    var refOff2 = parseInt(rf.candle_position, 10) || 0;
                    return treeAnchor + ownerOff + refOff2;
                }
            }
        }

        return null;
    }

    function ptResolveSourceObject(tree, ownerRoot, ownerRootAbs, sourceKey, treeAnchor, priceLevel) {
        var idx = ptResolveSourceIndex(tree, ownerRoot, ownerRootAbs, sourceKey, treeAnchor);
        if (idx == null || idx < 0 || idx >= PT_CANDLES.length) return null;

        var candle = PT_CANDLES[idx];
        if (!candle) return null;

        var val = null;
        if (priceLevel && candle[priceLevel] != null) val = candle[priceLevel];
        else val = candle.close;

        return {
            idx:        idx,
            candle:     candle,
            name:       ptSourceFriendlyName(tree, sourceKey),
            priceLevel: priceLevel || '',
            price:      val
        };
    }

    function ptSourceFriendlyName(tree, sourceKey) {
        if (!sourceKey) return '—';
        if (sourceKey === 'any_candle_intercept') return 'any_candle_intercept';

        if (sourceKey === 'ROOT') {
            var r0 = (tree.roots || [])[0];
            if (r0 && r0.candle_name) return r0.candle_name;
            return 'ROOT';
        }

        var m = /^ROOT:(\d+):REF:(\d+)$/.exec(sourceKey);
        if (m) {
            var ri = parseInt(m[1], 10);
            var fi = parseInt(m[2], 10);
            var rt = (tree.roots || [])[ri];
            if (rt) {
                var ref = (rt.root_refs || [])[fi];
                if (ref && ref.candle_name) return ref.candle_name;
                if (rt.candle_name) return rt.candle_name + ' (ref #' + (fi + 1) + ')';
            }
        }
        return sourceKey;
    }

    function ptDrawProgrammeDrawings(pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible) {
        if (!PT_DRAW_CTX) return;
        if (!Array.isArray(PT_TREES) || !PT_TREES.length) return;
        if (!PT_CANDLES.length) return;

        PT_DRAW_CTX.save();
        PT_DRAW_CTX.lineWidth = 1.6;
        PT_DRAW_CTX.setLineDash([]);

        PT_TREES.forEach(function (tree) {
            var roots = tree.roots || [];
            if (!roots.length) return;

            var drawings = tree.drawings || [];
            if (!drawings.length) return;

            var candidates = ptRootCandidateIndices(firstVisible, lastVisible);

            candidates.forEach(function (i0) {
                if (i0 < firstVisible || i0 > lastVisible) return;
                if (i0 < 0 || i0 >= PT_CANDLES.length) return;

                var matched = ptEvaluateTreeAt(tree, i0, firstVisible, lastVisible);
                if (!matched.length) return;

                var foundationMatch = matched[0];
                var foundationRoot  = foundationMatch.root;
                var foundationAbs   = foundationMatch.absIdx;

                if (!foundationRoot.timeframe || foundationRoot.timeframe !== PT_CURRENT_TIMEFRAME) return;
                if (foundationAbs < 0 || foundationAbs >= PT_CANDLES.length) return;

                drawings.forEach(function (dr) {
                    if (dr.drawing_tools !== 'trendline') return;
                    if (!dr.draw_from || !dr.draw_from_price_level) return;
                    if (!dr.draw_to) return;

                    var fromIdx = ptResolveSourceIndex(tree, foundationRoot, foundationAbs, dr.draw_from, i0);
                    if (fromIdx == null || fromIdx < 0 || fromIdx >= PT_CANDLES.length) return;
                    var fromCandle = PT_CANDLES[fromIdx];
                    if (!fromCandle) return;
                    var fromVal = fromCandle[dr.draw_from_price_level];
                    if (fromVal == null) return;
                    var x1 = baseX + fromIdx * step + step / 2;
                    var y1 = pToY(fromVal);

                    var stroke = ptResolveDrawingColor(dr.drawing_color);
                    var toKey = dr.draw_to || '';

                    var specMatch2 = /^(.+)_specific_price_level$/.exec(toKey);
                    if (specMatch2) {
                        var targetKey = specMatch2[1];
                        var toIdx = ptResolveSourceIndex(tree, foundationRoot, foundationAbs, targetKey, i0);
                        if (toIdx == null || toIdx < 0 || toIdx >= PT_CANDLES.length) return;
                        var toCandle = PT_CANDLES[toIdx];
                        if (!toCandle) return;
                        if (!dr.draw_to_price_level) return;
                        var toVal = toCandle[dr.draw_to_price_level];
                        if (toVal == null) return;
                        drawSegment(x1, y1, baseX + toIdx * step + step / 2, pToY(toVal), stroke);
                        return;
                    }

                    var axisMatch2 = /^(.+)_axis$/.exec(toKey);
                    if (axisMatch2) {
                        var axisKey = axisMatch2[1];
                        var axisIdx = ptResolveSourceIndex(tree, foundationRoot, foundationAbs, axisKey, i0);
                        if (axisIdx == null || axisIdx < 0 || axisIdx >= PT_CANDLES.length) return;
                        drawSegment(x1, y1, baseX + axisIdx * step + step / 2, y1, stroke);
                        return;
                    }

                    if (toKey === 'any_candle_intercept') {
                        var dir = (fromIdx >= foundationAbs) ? 1 : -1;
                        var hit2 = ptFindAnyIntercept(fromIdx, dir, y1, fromVal, baseX, step, firstVisible, lastVisible);
                        if (hit2) drawSegment(x1, y1, hit2.x, hit2.y, stroke);
                        return;
                    }

                    var namedIdx = ptResolveSourceIndex(tree, foundationRoot, foundationAbs, toKey, i0);
                    if (namedIdx != null && namedIdx >= 0 && namedIdx < PT_CANDLES.length) {
                        drawSegment(x1, y1, baseX + namedIdx * step + step / 2, y1, stroke);
                        return;
                    }
                });
            });
        });

        PT_DRAW_CTX.restore();

        function drawSegment(x1, y1, x2, y2, stroke) {
            if (y1 < chartTop - 500 || y1 > chartBottom + 500) return;
            if (y2 < chartTop - 500 || y2 > chartBottom + 500) return;
            PT_DRAW_CTX.strokeStyle = stroke || 'rgba(74,123,216,0.95)';
            PT_DRAW_CTX.beginPath();
            PT_DRAW_CTX.moveTo(x1, y1);
            PT_DRAW_CTX.lineTo(x2, y2);
            PT_DRAW_CTX.stroke();
        }
    }

    // Generic projected-drawings renderer for a given projected row set.
    // This is the old ptDrawProjectedDrawings logic, but takes the rows as
    // an argument so we can call it once per active source TF.
    function ptDrawProjectedDrawingRows(rows, pToY, yToP, baseX, step, chartTop, chartBottom, firstVisible, lastVisible) {
        if (!PT_DRAW_CTX) return;
        if (!rows.length) return;
        if (!PT_CANDLES.length) return;

        var idxByTime = {};
        PT_CANDLES.forEach(function (c, i) { idxByTime[c.time] = i; });

        var projByName = {};
        var projByTreeRole = {};
        rows.forEach(function (r) {
            var nm = (r.candle_name || '').trim();
            if (nm) {
                if (!projByName[nm]) projByName[nm] = [];
                projByName[nm].push(r);
            }
            var key = (r.tree_id || 0) + '|' + (r.row_role || '');
            if (!projByTreeRole[key]) projByTreeRole[key] = [];
            projByTreeRole[key].push(r);
        });

        PT_DRAW_CTX.save();
        PT_DRAW_CTX.lineWidth = 1.6;
        PT_DRAW_CTX.setLineDash([]);

        var drawnCount = 0;

        rows.forEach(function (r) {
            if (r.row_role !== 'matched_drawing') return;
            if (r.drawing_tools !== 'trendline') return;
            if (!r.draw_from || !r.draw_from_price_level) return;
            if (!r.draw_to) return;

            var fromIdx = idxByTime[r.candle_time];
            if (fromIdx == null) {
                if (r.open_time) fromIdx = idxByTime[r.open_time];
            }
            if (fromIdx == null) return;

            var fromCandle = PT_CANDLES[fromIdx];
            if (!fromCandle) return;
            var fromVal = fromCandle[r.draw_from_price_level];
            if (fromVal == null) return;

            var x1 = baseX + fromIdx * step + step / 2;
            var y1 = pToY(fromVal);
            var stroke = ptResolveDrawingColor(r.drawing_color);
            var toKey = r.draw_to || '';

            function resolveTo(key) {
                if (!key) return null;

                var bare = key.replace(/_(axis|specific_price_level)$/, '');
                var candidates = projByName[bare] || [];
                for (var i = 0; i < candidates.length; i++) {
                    if (candidates[i].tree_id === r.tree_id) {
                        var ci = idxByTime[candidates[i].candle_time];
                        if (ci != null) return ci;
                    }
                }
                for (var j = 0; j < candidates.length; j++) {
                    var cj = idxByTime[candidates[j].candle_time];
                    if (cj != null) return cj;
                }

                if (bare === 'ROOT') {
                    var roots = projByTreeRole[(r.tree_id || 0) + '|matched_root'] || [];
                    for (var k = 0; k < roots.length; k++) {
                        if (roots[k].is_foundation) {
                            var rk = idxByTime[roots[k].candle_time];
                            if (rk != null) return rk;
                        }
                    }
                    if (roots.length) {
                        var r0 = idxByTime[roots[0].candle_time];
                        if (r0 != null) return r0;
                    }
                }

                var refMatch = /^REF:(\d+)$/.exec(bare);
                if (refMatch) {
                    var n = parseInt(refMatch[1], 10);
                    var refs = projByTreeRole[(r.tree_id || 0) + '|matched_ref'] || [];
                    if (refs[n]) {
                        var rn = idxByTime[refs[n].candle_time];
                        if (rn != null) return rn;
                    }
                }

                var rrMatch = /^ROOT:(\d+):REF:(\d+)$/.exec(bare);
                if (rrMatch) {
                    var m = parseInt(rrMatch[2], 10);
                    var refs2 = projByTreeRole[(r.tree_id || 0) + '|matched_ref'] || [];
                    if (refs2[m]) {
                        var rm = idxByTime[refs2[m].candle_time];
                        if (rm != null) return rm;
                    }
                }

                return null;
            }

            var specMatch2 = /^(.+)_specific_price_level$/.exec(toKey);
            if (specMatch2) {
                var toIdx = resolveTo(specMatch2[1]);
                if (toIdx != null && r.draw_to_price_level) {
                    var toCandle = PT_CANDLES[toIdx];
                    if (toCandle && toCandle[r.draw_to_price_level] != null) {
                        drawSegment(x1, y1, baseX + toIdx * step + step / 2,
                            pToY(toCandle[r.draw_to_price_level]), stroke);
                        drawnCount++;
                        return;
                    }
                }
                drawSegment(x1, y1, x1 + step * 6, y1, stroke);
                drawnCount++;
                return;
            }

            var axisMatch2 = /^(.+)_axis$/.exec(toKey);
            if (axisMatch2) {
                var axisIdx = resolveTo(axisMatch2[1]);
                if (axisIdx != null) {
                    drawSegment(x1, y1, baseX + axisIdx * step + step / 2, y1, stroke);
                    drawnCount++;
                    return;
                }
                drawSegment(x1, y1, x1 + step * 6, y1, stroke);
                drawnCount++;
                return;
            }

            if (toKey === 'any_candle_intercept') {
                var found = null;
                for (var q = fromIdx + 1; q < PT_CANDLES.length; q++) {
                    var cn = PT_CANDLES[q];
                    if (!cn) continue;
                    if (fromVal <= cn.high && fromVal >= cn.low) { found = q; break; }
                }
                if (found == null) {
                    for (var q2 = fromIdx - 1; q2 >= 0; q2--) {
                        var cn2 = PT_CANDLES[q2];
                        if (!cn2) continue;
                        if (fromVal <= cn2.high && fromVal >= cn2.low) { found = q2; break; }
                    }
                }
                if (found != null) {
                    drawSegment(x1, y1, baseX + found * step + step / 2, y1, stroke);
                    drawnCount++;
                    return;
                }
                drawSegment(x1, y1, x1 + step * 6, y1, stroke);
                drawnCount++;
                return;
            }

            var namedIdx = resolveTo(toKey);
            if (namedIdx != null) {
                drawSegment(x1, y1, baseX + namedIdx * step + step / 2, y1, stroke);
                drawnCount++;
                return;
            }

            drawSegment(x1, y1, x1 + step * 6, y1, stroke);
            drawnCount++;
        });

        PT_DRAW_CTX.restore();

        function drawSegment(x1, y1, x2, y2, stroke) {
            if (y1 < chartTop - 2000 || y1 > chartBottom + 2000) return;
            if (y2 < chartTop - 2000 || y2 > chartBottom + 2000) return;
            PT_DRAW_CTX.strokeStyle = stroke || 'rgba(74,123,216,0.95)';
            PT_DRAW_CTX.beginPath();
            PT_DRAW_CTX.moveTo(x1, y1);
            PT_DRAW_CTX.lineTo(x2, y2);
            PT_DRAW_CTX.stroke();
        }
    }

    // Long/short overlay renderer for a specific projected row set.
    function ptDrawProjectedLongShortRows(rows, pToY, baseX, step, chartTop, chartBottom, firstVisible, lastVisible) {
        if (!PT_DRAW_CTX) return;
        if (!PT_CANDLES.length) return;
        if (!rows.length) return;

        var idxByTime = {};
        PT_CANDLES.forEach(function (c, i) { idxByTime[c.time] = i; });

        var projByName = {};
        var projByTreeRole = {};
        rows.forEach(function (r) {
            var nm = (r.candle_name || '').trim();
            if (nm) {
                if (!projByName[nm]) projByName[nm] = [];
                projByName[nm].push(r);
            }
            var key = (r.tree_id || 0) + '|' + (r.row_role || '');
            if (!projByTreeRole[key]) projByTreeRole[key] = [];
            projByTreeRole[key].push(r);
        });

        var halfBody = PT_VIEW.candleWidth / 2;

        PT_DRAW_CTX.save();

        rows.forEach(function (entryRow) {
            if (entryRow.row_role !== 'matched_trade') return;
            if (!entryRow.resolved_direction) return;

            var treeId = entryRow.tree_id || 0;

            var entryIdx = idxByTime[entryRow.candle_time];
            if (entryIdx == null) return;
            if (entryIdx > lastVisible) return;

            var entryPrice = (entryRow.entry_price != null)
                ? entryRow.entry_price
                : (entryRow.candle_center != null ? entryRow.candle_center : entryRow.close);
            if (entryPrice == null) return;

            function resolveTo(key) {
                if (!key) return null;
                var bare = key.replace(/_(axis|specific_price_level)$/, '');
                var candidates = projByName[bare] || [];
                for (var i = 0; i < candidates.length; i++) {
                    if (candidates[i].tree_id === treeId) {
                        var ci = idxByTime[candidates[i].candle_time];
                        if (ci != null) return ci;
                    }
                }
                for (var j = 0; j < candidates.length; j++) {
                    var cj = idxByTime[candidates[j].candle_time];
                    if (cj != null) return cj;
                }
                if (bare === 'ROOT') {
                    var roots = projByTreeRole[treeId + '|matched_root'] || [];
                    for (var k = 0; k < roots.length; k++) {
                        if (roots[k].is_foundation) {
                            var rk = idxByTime[roots[k].candle_time];
                            if (rk != null) return rk;
                        }
                    }
                    if (roots.length) {
                        var r0 = idxByTime[roots[0].candle_time];
                        if (r0 != null) return r0;
                    }
                }
                var refMatch = /^REF:(\d+)$/.exec(bare);
                if (refMatch) {
                    var n = parseInt(refMatch[1], 10);
                    var refs = projByTreeRole[treeId + '|matched_ref'] || [];
                    if (refs[n]) {
                        var rn = idxByTime[refs[n].candle_time];
                        if (rn != null) return rn;
                    }
                }
                return null;
            }

            var exitIdx   = null;
            var exitPrice = (entryRow.exit_price != null) ? entryRow.exit_price : null;
            if (entryRow.exit_at) {
                exitIdx = resolveTo(entryRow.exit_at);
                if (exitPrice == null && exitIdx != null) {
                    var ec = PT_CANDLES[exitIdx];
                    if (ec) {
                        var lvl = entryRow.exit_at_price_level;
                        exitPrice = (lvl && ec[lvl] != null) ? ec[lvl] : ec.close;
                    }
                }
            }

            var targetIdx   = null;
            var targetPrice = (entryRow.target_price != null)
                ? entryRow.target_price
                : (entryRow.resolved_price != null ? entryRow.resolved_price : null);

            var isRR = (entryRow.target === 'minimum_risk_reward' ||
                        entryRow.target === 'fixed_risk_reward');

            if (!isRR && entryRow.target) {
                targetIdx = resolveTo(entryRow.target);
                if (targetPrice == null && targetIdx != null) {
                    var tc = PT_CANDLES[targetIdx];
                    if (tc) {
                        var lvl2 = entryRow.target_price_level;
                        targetPrice = (lvl2 && tc[lvl2] != null) ? tc[lvl2] : tc.close;
                    }
                }
            }

            if (exitPrice == null || targetPrice == null) return;

            var resolutionIdx = lastVisible;
            if (entryRow.outcome_candle_time && idxByTime[entryRow.outcome_candle_time] != null) {
                resolutionIdx = idxByTime[entryRow.outcome_candle_time];
            } else if (targetIdx != null) {
                resolutionIdx = targetIdx;
            } else if (exitIdx != null) {
                resolutionIdx = exitIdx;
            }
            if (resolutionIdx < entryIdx) resolutionIdx = entryIdx;
            if (resolutionIdx > lastVisible) resolutionIdx = lastVisible;

            if (resolutionIdx < firstVisible && entryIdx < firstVisible) return;

            var entryCenterX      = baseX + entryIdx * step + step / 2;
            var resolutionCenterX = baseX + resolutionIdx * step + step / 2;

            var x1 = entryCenterX - halfBody;
            var x2 = resolutionCenterX + halfBody;
            if (x2 < x1) { var tmpX = x1; x1 = x2; x2 = tmpX; }

            var yEntry  = pToY(entryPrice);
            var yStop   = pToY(exitPrice);
            var yTarget = pToY(targetPrice);

            if (yEntry < chartTop - 4000 || yEntry > chartBottom + 4000) return;

            var redTop    = Math.min(yEntry, yStop);
            var redBottom = Math.max(yEntry, yStop);
            PT_DRAW_CTX.fillStyle = 'rgba(231, 76, 60, 0.12)';
            PT_DRAW_CTX.fillRect(x1, redTop, x2 - x1, redBottom - redTop);

            var greenTop    = Math.min(yEntry, yTarget);
            var greenBottom = Math.max(yEntry, yTarget);
            PT_DRAW_CTX.fillStyle = 'rgba(39, 174, 96, 0.12)';
            PT_DRAW_CTX.fillRect(x1, greenTop, x2 - x1, greenBottom - greenTop);
        });

        PT_DRAW_CTX.restore();
    }

    // Native long/short overlays (unchanged from original)
    function ptDrawLongShortOverlays(pToY, baseX, step, chartTop, chartBottom, firstVisible, lastVisible) {
        if (!PT_DRAW_CTX) return;
        if (!PT_CANDLES.length) return;

        var allHits = ptScanTrades();
        if (!allHits.length) return;

        PT_DRAW_CTX.save();

        var halfBody = PT_VIEW.candleWidth / 2;

        allHits.forEach(function (treeRes) {
            treeRes.hits.forEach(function (hit) {
                var entry  = hit.entry;
                var exitO  = hit.exit;
                var target = hit.targetRR ? { price: hit.targetRR.price }
                                          : (hit.target ? { price: hit.target.price } : null);

                if (!entry || !entry.candle) return;
                if (!exitO || exitO.price == null) return;
                if (!target || target.price == null) return;

                var entryIdx = entry.idx;
                var entryPrice  = entry.price;
                var stopPrice   = exitO.price;
                var targetPrice = target.price;

                if (entryIdx == null || entryIdx < 0) return;

                var resolutionIdx = (hit.outcome && hit.outcome.candleIdx != null)
                    ? hit.outcome.candleIdx
                    : lastVisible;
                if (resolutionIdx < entryIdx) resolutionIdx = entryIdx;
                if (resolutionIdx > lastVisible) resolutionIdx = lastVisible;

                if (resolutionIdx < firstVisible && entryIdx < firstVisible) return;
                if (entryIdx > lastVisible) return;

                var entryCenterX      = baseX + entryIdx * step + step / 2;
                var resolutionCenterX = baseX + resolutionIdx * step + step / 2;

                var x1 = entryCenterX - halfBody;
                var x2 = resolutionCenterX + halfBody;
                if (x2 < x1) { var tmpX = x1; x1 = x2; x2 = tmpX; }

                var yEntry  = pToY(entryPrice);
                var yStop   = pToY(stopPrice);
                var yTarget = pToY(targetPrice);

                if (yEntry < chartTop - 2000 || yEntry > chartBottom + 2000) return;

                var redTop    = Math.min(yEntry, yStop);
                var redBottom = Math.max(yEntry, yStop);
                PT_DRAW_CTX.fillStyle = 'rgba(231, 76, 60, 0.12)';
                PT_DRAW_CTX.fillRect(x1, redTop, x2 - x1, redBottom - redTop);

                var greenTop    = Math.min(yEntry, yTarget);
                var greenBottom = Math.max(yEntry, yTarget);
                PT_DRAW_CTX.fillStyle = 'rgba(39, 174, 96, 0.12)';
                PT_DRAW_CTX.fillRect(x1, greenTop, x2 - x1, greenBottom - greenTop);
            });
        });

        PT_DRAW_CTX.restore();
    }

    function ptFindAnyIntercept(rootIdx, dir, y1, flatPrice, baseX, step, firstVisible, lastVisible) {
        var j = rootIdx + dir;
        var lastIdx = (dir > 0) ? lastVisible : firstVisible;
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
    window.ptOpenTradesModal    = ptOpenTradesModal;
    window.ptCloseTradesModal   = ptCloseTradesModal;
    window.ptToggleLongShort    = ptToggleLongShort;
</script>

</body>
</html>