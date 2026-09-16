<?php
// programme_configuration.php
// Modal-only configuration builder for a programme.
// Summoned from programme_training.php via:
//   window.pcOpenConfiguration({ programmeId, programmeName });

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== DATABASE CONNECTION (shared) ====================
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO(
            "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
            "if0_40473107",
            "InDQmdl53FZ85",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) { /* host already has $pdo */ }
}

// ==================== DEFAULTS ====================
$pcProgrammeId   = isset($programmeId) ? (int)$programmeId : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$pcUserId        = isset($userId) ? (int)$userId : 0;
$pcProgrammeName = isset($programmeName) ? $programmeName : 'Programme';
$pcTimeframes    = isset($selectedTimeframes) && is_array($selectedTimeframes)
                     ? array_values($selectedTimeframes) : [];
$pcCurrentTf     = isset($selectedTimeframes) && is_array($selectedTimeframes) && count($selectedTimeframes)
                     ? $selectedTimeframes[0] : '';

// ==================== OPTIONS ====================
$pcPriceLevels = [
    'open','high','low','close',
    'candle_center','body_center',
    'high_wick_center','low_wick_center','candle_width_center'
];
$pcCandleTypes = ['bullish','bearish','any'];
$pcDrawingTools = ['trendline'];
$pcDrawingDestinations = [
    'reference',
    'reference_axis',
    'any_candle_intercept',
    'specific_reference_price_levels'
];

// ==================== CANDLE NAMES ====================
$pcCandleNames = [];
if ($pcUserId > 0) {
    try {
        $q = $pdo->prepare("
            SELECT DISTINCT candle_name
            FROM programme_configuration
            WHERE userid = ? AND candle_name IS NOT NULL AND candle_name <> ''
            ORDER BY candle_name ASC LIMIT 200
        ");
        $q->execute([$pcUserId]);
        while ($r = $q->fetch(PDO::FETCH_ASSOC)) $pcCandleNames[] = $r['candle_name'];
    } catch (PDOException $e) { $pcCandleNames = []; }
}

// ==================== EXISTING ROOT TREES ====================
$pcRootTrees = [];
if ($pcUserId > 0 && $pcProgrammeId > 0) {
    try {
        $rq = $pdo->prepare("
            SELECT id, candleid, root, reference_id, re_referenced_root,
                   candle_name, price_level, timeframe, candle_type,
                   candle_position, candle_search, operator,
                   drawing_id, drawing_tools, from_root_price_level,
                   drawing_destination, to_reference_price_level
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ?
            ORDER BY id ASC
            LIMIT 4000
        ");
        $rq->execute([$pcUserId, $pcProgrammeId]);
        $all = [];
        while ($r = $rq->fetch(PDO::FETCH_ASSOC)) {
            $all[] = [
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

        // Roots
        foreach ($all as $row) {
            if ((int)$row['root'] !== 1) continue;
            $pcRootTrees[$row['id']] = [
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
        // References
        foreach ($all as $row) {
            if ((int)$row['root'] === 1) continue;
            if ($row['drawing_id'] !== null) continue;
            $anchor = (int)$row['candleid'];
            if (!isset($pcRootTrees[$anchor])) continue;
            $pcRootTrees[$anchor]['refs'][] = [
                'id'                 => $row['id'],
                'candle_name'        => $row['candle_name'],
                'price_level'        => $row['price_level'],
                'timeframe'          => $row['timeframe'],
                'candle_type'        => $row['candle_type'],
                'position'           => $row['candle_position'],
                'candle_search'      => $row['candle_search'],
                'operator'           => $row['operator'],
                're_referenced_root' => (int)$row['re_referenced_root'],
                'reference_id'       => $row['reference_id'],
            ];
        }
        // Drawings
        foreach ($all as $row) {
            if ($row['drawing_id'] === null) continue;
            $anchor = (int)$row['candleid'];
            if (!isset($pcRootTrees[$anchor])) continue;
            $pcRootTrees[$anchor]['drawings'][] = [
                'id'                       => $row['id'],
                'drawing_id'               => (int)$row['drawing_id'],
                'drawing_tools'            => $row['drawing_tools'],
                'from_root_price_level'    => $row['from_root_price_level'],
                'drawing_destination'      => $row['drawing_destination'],
                'to_reference_price_level' => $row['to_reference_price_level'],
            ];
        }

        $pcRootTrees = array_values($pcRootTrees);
    } catch (PDOException $e) { $pcRootTrees = []; }
}

// ==================== AJAX: SAVE CONFIGURATION ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_configuration'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit;
    }
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        echo json_encode(['success' => false, 'message' => 'No database.']); exit;
    }

    $email = strtolower($_SESSION['user_email']);
    $u = $pdo->prepare("SELECT id FROM harvhub WHERE email = ? LIMIT 1");
    $u->execute([$email]);
    $uRow = $u->fetch(PDO::FETCH_ASSOC);
    if (!$uRow) { echo json_encode(['success' => false, 'message' => 'User not found.']); exit; }
    $uId = (int)$uRow['id'];

    $payload = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($payload)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payload.']); exit;
    }

    $programmeId = (int)($payload['programmeId'] ?? 0);
    $candles     = is_array($payload['candles']  ?? null) ? $payload['candles']  : [];
    $drawings    = is_array($payload['drawings'] ?? null) ? $payload['drawings'] : [];

    try {
        $pdo->beginTransaction();

        $del1 = $pdo->prepare("DELETE FROM programme_configuration WHERE userid = ? AND programmeid = ?");
        $del1->execute([$uId, $programmeId]);

        // ---- Pass 1: insert candle rows ----
        $idMap = [];
        $insertCandle = $pdo->prepare("
            INSERT INTO programme_configuration
                (userid, programmeid, candleid, root, reference_id, re_referenced_root,
                 candle_name, price_level, timeframe, candle_type,
                 candle_position, candle_search, operator,
                 drawing_id, drawing_tools, from_root_price_level,
                 drawing_destination, to_reference_price_level)
            VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL)
        ");

        foreach ($candles as $c) {
            $tf = isset($c['timeframe']) ? trim((string)$c['timeframe']) : '';
            if ($tf === '') $tf = null;

            $isRoot   = !empty($c['isRoot']) ? 1 : 0;
            $isReRef  = !empty($c['reReferencedRoot']) ? 1 : 0;

            $op = isset($c['operator']) ? trim((string)$c['operator']) : '';
            if (!in_array($op, ['>','<','<=','>='], true)) $op = null;

            $ct = isset($c['candle_type']) ? trim((string)$c['candle_type']) : '';
            if (!in_array($ct, ['bullish','bearish','any'], true)) $ct = null;

            $insertCandle->execute([
                $uId,
                $programmeId,
                $isRoot,
                $isReRef,
                $c['candle_name'] ?? null,
                $c['price_level'] ?? null,
                $tf,
                $ct,
                $c['candle_position'] ?? '0',
                $c['candle_search'] ?? null,
                $op,
            ]);
            $idMap[(int)$c['localId']] = (int)$pdo->lastInsertId();
        }

        // ---- Pass 2: patch candleid + reference_id ----
        $upd = $pdo->prepare("
            UPDATE programme_configuration
            SET candleid = ?, reference_id = ?
            WHERE id = ? AND userid = ?
        ");
        foreach ($candles as $c) {
            $localId = (int)$c['localId'];
            if (!isset($idMap[$localId])) continue;
            $newId = $idMap[$localId];

            $anchorLocal = isset($c['anchorLocalId']) ? (int)$c['anchorLocalId'] : 0;

            if (!empty($c['isRoot'])) {
                $upd->execute([$newId, $newId, $newId, $uId]);
            } elseif ($anchorLocal > 0 && isset($idMap[$anchorLocal])) {
                $anchorDbId = $idMap[$anchorLocal];
                $upd->execute([$anchorDbId, $anchorDbId, $newId, $uId]);
            } else {
                $upd->execute([$newId, $newId, $newId, $uId]);
            }
        }

        // ---- Pass 3: insert drawings ----
        // FIX: every drawing is a brand-new row (so multiple drawings per root
        // are supported) — we no longer overwrite the root row's columns.
        $insertDrawing = $pdo->prepare("
            INSERT INTO programme_configuration
                (userid, programmeid, candleid, root, reference_id, re_referenced_root,
                 candle_name, price_level, timeframe, candle_type,
                 candle_position, candle_search, operator,
                 drawing_id, drawing_tools, from_root_price_level,
                 drawing_destination, to_reference_price_level)
            VALUES (?, ?, ?, 0, ?, 0,
                    NULL, NULL, NULL, NULL,
                    NULL, NULL, NULL,
                    ?, ?, ?, ?, ?)
        ");

        foreach ($drawings as $d) {
            $anchorLocal = isset($d['anchorLocalId']) ? (int)$d['anchorLocalId'] : 0;
            if (!isset($idMap[$anchorLocal])) continue;
            $rootDbId = $idMap[$anchorLocal];

            $dId = isset($d['drawing_id']) ? (int)$d['drawing_id'] : 0;
            if ($dId <= 0) {
                // ensure uniqueness server-side
                $dId = (int)round(microtime(true) * 1000) % 2000000000;
            }

            $tool = isset($d['drawing_tools']) ? trim((string)$d['drawing_tools']) : '';
            if (!in_array($tool, ['trendline'], true)) $tool = null;

            $dest = isset($d['drawing_destination']) ? trim((string)$d['drawing_destination']) : '';
            if (!in_array($dest, [
                'reference','reference_axis',
                'any_candle_intercept','specific_reference_price_levels'
            ], true)) $dest = null;

            $fromLevel = isset($d['from_root_price_level']) ? trim((string)$d['from_root_price_level']) : '';
            if ($fromLevel === '') $fromLevel = null;

            $toLevel = isset($d['to_reference_price_level']) ? trim((string)$d['to_reference_price_level']) : '';
            if ($dest !== 'specific_reference_price_levels') $toLevel = null;
            if ($toLevel === '') $toLevel = null;

            $insertDrawing->execute([
                $uId,
                $programmeId,
                $rootDbId,           // candleid -> root's id
                $rootDbId,           // reference_id -> root's id
                $dId,
                $tool,
                $fromLevel,
                $dest,
                $toLevel,
            ]);
        }

        $pdo->commit();

        // ---- Return canonical rows ----
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
        $cStmt->execute([$uId, $programmeId]);
        $rows = [];
        while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
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

        echo json_encode(['success' => true, 'candles' => $rows]);
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    }
    exit;
}
?>

<!-- ============================================================
     PROGRAMME CONFIGURATION — MODAL
     ============================================================ -->
<div id="pcModal" class="pc-modal" aria-hidden="true"
     data-programme-id="<?= (int)$pcProgrammeId ?>"
     data-user-id="<?= (int)$pcUserId ?>">

    <div class="pc-modal-content" role="dialog" aria-modal="true" aria-labelledby="pcModalTitle">

        <div class="pc-modal-header">
            <div class="pc-modal-heading">
                <h2 class="pc-modal-title" id="pcModalTitle">
                    <?= htmlspecialchars($pcProgrammeName) ?> Configuration
                </h2>
                <p class="pc-modal-subtitle">Build candle rules &amp; drawing tools</p>
            </div>
            <button type="button" class="pc-modal-close" id="pcModalClose" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="pc-modal-body" id="pcModalBody">
            <div class="pc-actionbar">
                <button type="button" class="pc-btn pc-btn-primary pc-btn-sm" id="pcAddCandleBtn">
                    <i class="fa-solid fa-plus"></i> Define Candle
                </button>
            </div>

            <div id="pcRootGroups" class="pc-root-groups"></div>
        </div>

        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-ghost" id="pcModalCancel">Close</button>
            <button type="button" class="pc-btn pc-btn-primary" id="pcModalSave">Save</button>
        </div>
    </div>
</div>

<datalist id="pcCandleNameList">
    <?php foreach ($pcCandleNames as $cn): ?>
        <option value="<?= htmlspecialchars($cn) ?>"></option>
    <?php endforeach; ?>
</datalist>

<script type="application/json" id="pcPriceLevelsJson"><?= json_encode(array_values($pcPriceLevels)) ?></script>
<script type="application/json" id="pcCandleTypesJson"><?= json_encode(array_values($pcCandleTypes)) ?></script>
<script type="application/json" id="pcDrawingToolsJson"><?= json_encode(array_values($pcDrawingTools)) ?></script>
<script type="application/json" id="pcDrawingDestinationsJson"><?= json_encode(array_values($pcDrawingDestinations)) ?></script>
<script type="application/json" id="pcCandleNamesJson"><?= json_encode(array_values($pcCandleNames)) ?></script>
<script type="application/json" id="pcTimeframesJson"><?= json_encode(array_values($pcTimeframes)) ?></script>
<script type="application/json" id="pcCurrentTfJson"><?= json_encode($pcCurrentTf) ?></script>
<script type="application/json" id="pcRootTreesJson"><?= json_encode(array_values($pcRootTrees)) ?></script>

<!-- ============================================================
     TEMPLATES
     ============================================================ -->

<template id="pcRootGroupTpl">
    <div class="pc-root-group" data-root-local-id="" data-collapsed="1">
        <div class="pc-root-group-head">
            <button type="button" class="pc-root-toggle" data-action="toggle" title="Expand / collapse">
                <i class="fa-solid fa-chevron-right pc-root-caret"></i>
                <i class="fa-solid fa-seedling pc-root-icon"></i>
                <span class="pc-root-group-title-text" data-role="rootTitle">Root Candle</span>
                <span class="pc-root-group-sub" data-role="rootSub"></span>
            </button>
            <button type="button" class="pc-root-group-remove" title="Remove entire tree" data-action="remove-group">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>

        <div class="pc-root-group-body">
            <div class="pc-root-card" data-root-role="root"></div>
            <div class="pc-ref-list" data-role="refList"></div>

            <button type="button" class="pc-add-ref-btn" data-action="add-ref">
                <i class="fa-solid fa-plus"></i> Add root reference
            </button>

            <div class="pc-drawing-list" data-role="drawingList"></div>

            <button type="button" class="pc-add-drawing-btn" data-action="add-drawing">
                <i class="fa-solid fa-pen-ruler"></i> Add Drawing
            </button>
        </div>
    </div>
</template>

<template id="pcRefWrapTpl">
    <div class="pc-ref-wrap" data-ref-local-id="">
        <div class="pc-ref-card" data-ref-role="ref"></div>

        <div class="pc-reref-stack" data-role="rerefStack"></div>

        <button type="button" class="pc-reref-btn" data-action="re-ref">
            <i class="fa-solid fa-rotate-left"></i> Re-reference root candle
        </button>
    </div>
</template>

<template id="pcRerefPairTpl">
    <div class="pc-reref-pair" data-reref-local-id="">
        <div class="pc-reref-pair-head">
            <span class="pc-reref-pair-title">
                <i class="fa-solid fa-rotate-left"></i> Re-reference
            </span>
            <button type="button" class="pc-block-remove" data-action="remove-reref" title="Remove re-reference">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>

        <div class="pc-reref-author" data-reref-role="author"></div>
        <div class="pc-reref-referenced" data-reref-role="referenced"></div>
    </div>
</template>

<template id="pcCandleBlockTpl">
    <div class="pc-block pc-candle-block" data-block-kind="candle">
        <div class="pc-block-head">
            <div class="pc-block-title">
                <i class="fa-solid fa-candle-holder pc-block-icon"></i>
                <span data-role="roleLabel">Candle Rule</span>
                <span class="pc-block-ref-badge" data-role="refBadge" hidden></span>
                <span class="pc-block-reref-badge" data-role="reRefBadge" hidden>re-ref</span>
            </div>
            <button type="button" class="pc-block-remove" title="Remove" data-action="remove">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>

        <div class="pc-block-grid">
            <div class="pc-field">
                <label class="pc-label">Candle name</label>
                <input type="text" class="pc-input pc-candle-name" list="pcCandleNameList"
                       placeholder="e.g. iamcandlea" autocomplete="off" data-field="candle_name">
            </div>
            <div class="pc-field" data-role="candleTypeField">
                <label class="pc-label">Candle type</label>
                <select class="pc-input pc-select pc-candle-type" data-field="candle_type">
                    <option value="">— none —</option>
                </select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Price level</label>
                <select class="pc-input pc-select pc-price-level" data-field="price_level"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Timeframe <span class="pc-req">*</span></label>
                <select class="pc-input pc-select pc-timeframe" data-field="timeframe"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Candle position</label>
                <input type="number" class="pc-input pc-candle-position" value="0" step="1"
                       placeholder="0 = all, 1 = right, -1 = left" data-field="candle_position">
            </div>
            <div class="pc-field">
                <label class="pc-label">Candle searching</label>
                <select class="pc-input pc-select pc-candle-search" data-field="candle_search">
                    <option value="">— none —</option>
                    <option value="fixed">fixed</option>
                    <option value="random">random</option>
                    <option value="all">all</option>
                </select>
            </div>
            <div class="pc-field" data-role="operatorField">
                <label class="pc-label">Operator</label>
                <select class="pc-input pc-select pc-operator" data-field="operator">
                    <option value="">— none —</option>
                    <option value="&lt;">&lt;</option>
                    <option value="&gt;">&gt;</option>
                    <option value="&lt;=">&lt;=</option>
                    <option value="&gt;=">&gt;=</option>
                </select>
            </div>
        </div>

        <div class="pc-block-foot">
            <span class="pc-block-hint" data-role="hint"></span>
        </div>
    </div>
</template>

<template id="pcDrawingBlockTpl">
    <div class="pc-block pc-drawing-block" data-block-kind="drawing">
        <div class="pc-block-head">
            <div class="pc-block-title">
                <i class="fa-solid fa-pen-ruler pc-block-icon"></i>
                <span>Drawing</span>
            </div>
            <button type="button" class="pc-block-remove" title="Remove" data-action="remove">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>

        <div class="pc-block-grid pc-grid-3">
            <div class="pc-field">
                <label class="pc-label">Drawing tool</label>
                <select class="pc-input pc-select pc-drawing-tool" data-field="drawing_tools">
                    <option value="">— select —</option>
                </select>
            </div>
            <div class="pc-field">
                <label class="pc-label">From root price level</label>
                <select class="pc-input pc-select pc-drawing-from-level" data-field="from_root_price_level">
                    <option value="">— select —</option>
                </select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Drawing destination</label>
                <select class="pc-input pc-select pc-drawing-destination" data-field="drawing_destination">
                    <option value="">— select —</option>
                </select>
            </div>
            <div class="pc-field pc-drawing-to-level-field" data-role="toRefLevelField" style="display:none;">
                <label class="pc-label">To reference price level</label>
                <select class="pc-input pc-select pc-drawing-to-level" data-field="to_reference_price_level">
                    <option value="">— select —</option>
                </select>
            </div>
        </div>
    </div>
</template>

<style>
/* ============================================================
   PROGRAMME CONFIGURATION — styling (pc-*)
   ============================================================ */
.pc-modal { display: none; position: fixed; inset: 0; z-index: 10000;
    background: rgba(0,0,0,0.55); backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px);
    align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
.pc-modal.pc-active { display: flex; }
.pc-modal-content { background: var(--bg-card, #ffffff); color: var(--text, #222);
    border: 1px solid var(--border-color, #e0e0e0); border-radius: var(--radius, 16px);
    box-shadow: 0 20px 60px rgba(0,0,0,0.35); width: 100%; max-width: 1020px; max-height: 92vh;
    display: flex; flex-direction: column; overflow: hidden; }
.pc-modal-header { display: flex; align-items: flex-start; justify-content: space-between;
    gap: 12px; padding: 16px 22px 12px; border-bottom: 1px solid var(--border-color, #e0e0e0); flex-shrink: 0; }
.pc-modal-title { margin: 0; font-size: 1.05rem; font-weight: 700; color: var(--accent, #2e8b57); }
.pc-modal-subtitle { margin: 3px 0 0; font-size: 0.78rem; color: var(--text-muted, #888); }
.pc-modal-close { flex-shrink: 0; width: 34px; height: 34px; border-radius: 8px;
    border: 1px solid transparent; background: transparent; color: var(--text-muted, #888);
    font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.pc-modal-close:hover { background: rgba(128,128,128,0.15); color: var(--text, #222); }
.pc-modal-body { flex: 1; min-height: 220px; max-height: 68vh; overflow-y: auto;
    padding: 16px 20px 20px; background: var(--bg, #fafafa); }
.pc-modal-body::-webkit-scrollbar { width: 8px; }
.pc-modal-body::-webkit-scrollbar-thumb { background: var(--border-color, #ccc); border-radius: 10px; }
.pc-actionbar { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.pc-btn-sm { padding: 8px 12px; font-size: 0.8rem; }
.pc-root-groups { display: flex; flex-direction: column; gap: 10px; margin-bottom: 14px; }
.pc-root-group { border: 1px solid var(--border-color, #e0e0e0); border-radius: 12px;
    background: var(--bg-card, #fff); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
.pc-root-group-head { display: flex; align-items: center; justify-content: space-between;
    gap: 8px; padding: 10px 12px; background: rgba(46,139,87,0.06); border-bottom: 1px solid transparent; }
.pc-root-group[data-collapsed="0"] .pc-root-group-head { border-bottom-color: var(--border-color, #e0e0e0); }
.pc-root-toggle { flex: 1; display: inline-flex; align-items: center; gap: 8px;
    background: transparent; border: none; cursor: pointer; font-family: inherit;
    font-size: 0.85rem; font-weight: 800; color: var(--accent, #2e8b57);
    text-transform: uppercase; letter-spacing: 0.4px; text-align: left; padding: 0; min-width: 0; }
.pc-root-caret { font-size: 0.7rem; transition: transform 0.18s ease; color: var(--accent, #2e8b57); flex-shrink: 0; }
.pc-root-group[data-collapsed="0"] .pc-root-caret { transform: rotate(90deg); }
.pc-root-icon { font-size: 0.95rem; flex-shrink: 0; }
.pc-root-group-title-text { flex-shrink: 0; color: var(--accent, #2e8b57); }
.pc-root-group-sub { font-weight: 600; font-size: 0.75rem; color: var(--text-muted, #888);
    text-transform: none; letter-spacing: 0; margin-left: 6px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-root-group-remove { border: none; background: transparent; color: #c0392b;
    cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 0.85rem; flex-shrink: 0; }
.pc-root-group-remove:hover { background: rgba(192,57,43,0.1); }
.pc-root-group-body { display: none; padding: 12px 14px 14px; }
.pc-root-group[data-collapsed="0"] .pc-root-group-body { display: block; }
.pc-root-card { margin-bottom: 8px; }
.pc-ref-list { display: flex; flex-direction: column; gap: 10px; }
.pc-ref-wrap { border-left: 3px solid #4a7bd8; border-radius: 8px; padding-left: 8px; }
.pc-reref-stack { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.pc-reref-pair { border-left: 3px solid #8e44ad; border-radius: 8px; padding-left: 8px;
    padding-top: 6px; padding-bottom: 6px; background: rgba(155,89,182,0.04); }
.pc-reref-pair-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.pc-reref-pair-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.4px; color: #8e44ad; display: inline-flex; align-items: center; gap: 6px; }
.pc-reref-author { margin-bottom: 6px; }
.pc-drawing-list { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
.pc-add-drawing-btn { margin-top: 10px; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 8px; background: rgba(74,123,216,0.10);
    color: #4a7bd8; border: 1px dashed rgba(74,123,216,0.55);
    font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
.pc-add-drawing-btn:hover { background: rgba(74,123,216,0.18); }
body.dark-mode .pc-add-drawing-btn { color: #9bbcf0; border-color: rgba(155,188,240,0.5); }
.pc-input.pc-locked { background: rgba(155,89,182,0.06); border-color: rgba(155,89,182,0.4);
    cursor: not-allowed; color: var(--text-muted, #888); }
body.dark-mode .pc-input.pc-locked { background: rgba(155,89,182,0.15); border-color: rgba(195,155,211,0.4); }
.pc-block-reref-badge { font-size: 0.62rem; background: rgba(155,89,182,0.18);
    color: #8e44ad; padding: 2px 7px; border-radius: 10px; font-weight: 800; margin-left: 6px;
    text-transform: uppercase; letter-spacing: 0.3px; }
body.dark-mode .pc-block-reref-badge { background: rgba(155,89,182,0.3); color: #c39bd3; }
.pc-role-badge { font-size: 0.62rem; padding: 2px 7px; border-radius: 10px;
    font-weight: 800; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.3px; }
.pc-role-badge.pc-role-author { background: rgba(46,139,87,0.16); color: var(--accent, #2e8b57); }
.pc-role-badge.pc-role-referenced { background: rgba(120,120,120,0.16); color: var(--text-muted, #777); }
.pc-add-ref-btn { margin-top: 10px; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 8px; background: rgba(74,123,216,0.10);
    color: #4a7bd8; border: 1px dashed rgba(74,123,216,0.55);
    font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
.pc-add-ref-btn:hover { background: rgba(74,123,216,0.18); }
body.dark-mode .pc-add-ref-btn { color: #9bbcf0; border-color: rgba(155,188,240,0.5); }
.pc-reref-btn { margin-top: 8px; display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 11px; border-radius: 8px; background: rgba(155,89,182,0.10);
    color: #8e44ad; border: 1px dashed rgba(155,89,182,0.55);
    font-family: inherit; font-size: 0.74rem; font-weight: 700; cursor: pointer; }
.pc-reref-btn:hover { background: rgba(155,89,182,0.18); }
body.dark-mode .pc-reref-btn { color: #c39bd3; border-color: rgba(195,155,211,0.5); }
.pc-blocks { display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; }
.pc-block { background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 12px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.pc-candle-block  { border-left: 4px solid var(--accent, #2e8b57); }
.pc-candle-block.pc-candle-reref-author { border-left-color: #8e44ad; }
.pc-candle-block.pc-candle-reref-referenced { border-left-color: #b39ddb; opacity: 0.9; }
.pc-drawing-block { border-left: 4px solid #4a7bd8; background: rgba(74,123,216,0.03); }
.pc-block-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.pc-block-title { display: inline-flex; align-items: center; gap: 8px; font-weight: 700;
    font-size: 0.85rem; color: var(--text, #222); flex-wrap: wrap; }
.pc-block-icon { color: var(--accent, #2e8b57); }
.pc-candle-reref-author .pc-block-icon { color: #8e44ad; }
.pc-candle-reref-referenced .pc-block-icon { color: #b39ddb; }
.pc-drawing-block .pc-block-icon { color: #4a7bd8; }
.pc-block-ref-badge { font-size: 0.68rem; background: rgba(46,139,87,0.12);
    color: var(--accent, #2e8b57); padding: 2px 8px; border-radius: 10px;
    font-weight: 700; margin-left: 6px; }
.pc-block-remove { border: none; background: transparent; color: #c0392b;
    cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 0.85rem; }
.pc-block-remove:hover { background: rgba(192,57,43,0.1); }
.pc-block-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; }
.pc-grid-3 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
@media (max-width: 900px) { .pc-block-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 600px) { .pc-block-grid, .pc-grid-3 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .pc-block-grid, .pc-grid-3 { grid-template-columns: 1fr; } }
.pc-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.pc-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px;
    color: var(--text-muted, #888); font-weight: 700; }
.pc-req { color: #c0392b; }
.pc-input { width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid var(--border-color, #dcdcdc); border-radius: 8px;
    background: var(--bg, #f7f7f7); color: var(--text, #222); font-family: inherit; font-size: 0.82rem; }
.pc-input:focus { outline: none; border-color: var(--accent, #2e8b57); background: var(--bg-card, #fff); }
.pc-input.pc-invalid { border-color: #c0392b; background: rgba(192,57,43,0.06); }
.pc-select { cursor: pointer; }
.pc-block-foot { margin-top: 6px; min-height: 14px; }
.pc-block-hint { font-size: 0.72rem; color: var(--text-muted, #888); }
.pc-block-hint.pc-warn { color: #c0392b; }
.pc-blocks-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center;
    text-align: center; gap: 6px; padding: 26px 20px; border: 2px dashed var(--border-color, #e0e0e0);
    border-radius: 12px; color: var(--text-muted, #888); background: var(--bg-card, #fff); }
.pc-placeholder-icon { font-size: 1.6rem; color: var(--accent, #2e8b57); opacity: 0.55; }
.pc-placeholder-title { margin: 0; font-size: 0.88rem; font-weight: 700; color: var(--text, #222); }
.pc-placeholder-text  { margin: 0; font-size: 0.76rem; }
.pc-modal-footer { display: flex; justify-content: flex-end; gap: 10px;
    padding: 12px 22px 16px; border-top: 1px solid var(--border-color, #e0e0e0);
    flex-shrink: 0; background: var(--bg-card, #fff); }
.pc-btn { padding: 9px 16px; border-radius: 8px; font-family: inherit;
    font-size: 0.82rem; font-weight: 600; cursor: pointer; border: 1px solid transparent;
    display: inline-flex; align-items: center; gap: 6px; }
.pc-btn-primary { background: var(--accent, #2e8b57); color: #fff; }
.pc-btn-primary:hover { filter: brightness(1.08); }
.pc-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; filter: none; }
.pc-btn-ghost { background: transparent; color: var(--text, #222); border-color: var(--border-color, #e0e0e0); }
.pc-btn-ghost:hover { background: rgba(128,128,128,0.12); }
body.dark-mode .pc-modal-content, body.dark-mode .pc-root-group, body.dark-mode .pc-block,
body.dark-mode .pc-reref-pair, body.dark-mode .pc-blocks-placeholder, body.dark-mode .pc-modal-footer {
    background: var(--bg-card, #1e1e2a); border-color: var(--border-color, #333); color: var(--text, #eee); }
body.dark-mode .pc-modal-body { background: var(--bg, #171722); }
body.dark-mode .pc-input { background: var(--bg, #2a2a3a); border-color: var(--border-color, #333); color: var(--text, #eee); }
body.dark-mode .pc-btn-ghost { color: var(--text, #eee); border-color: var(--border-color, #333); }
body.dark-mode .pc-block-ref-badge { background: rgba(46,139,87,0.2); }
body.dark-mode .pc-ref-wrap { border-left-color: #6c93e0; }
body.dark-mode .pc-reref-pair { border-left-color: #b39ddb; background: rgba(155,89,182,0.08); }
body.dark-mode .pc-root-group-head { background: rgba(46,139,87,0.12); }
body.dark-mode .pc-role-badge.pc-role-referenced { background: rgba(180,180,180,0.16); color: #bbb; }
body.dark-mode .pc-drawing-block { background: rgba(74,123,216,0.08); }
@media (max-width: 600px) {
    .pc-modal { padding: 10px; }
    .pc-modal-content { max-height: 96vh; border-radius: 12px; }
    .pc-modal-header { padding: 12px 14px 10px; }
    .pc-modal-body   { padding: 12px 14px 14px; max-height: 72vh; }
    .pc-modal-footer { padding: 10px 14px 12px; }
}
</style>

<script>
(function () {
    'use strict';

    var PC_PRICE_LEVELS       = JSON.parse(document.getElementById('pcPriceLevelsJson').textContent        || '[]');
    var PC_CANDLE_TYPES       = JSON.parse(document.getElementById('pcCandleTypesJson').textContent        || '[]');
    var PC_DRAWING_TOOLS      = JSON.parse(document.getElementById('pcDrawingToolsJson').textContent       || '[]');
    var PC_DRAWING_DESTS      = JSON.parse(document.getElementById('pcDrawingDestinationsJson').textContent|| '[]');
    var PC_CANDLE_NAMES       = JSON.parse(document.getElementById('pcCandleNamesJson').textContent        || '[]');
    var PC_TIMEFRAMES         = JSON.parse(document.getElementById('pcTimeframesJson').textContent         || '[]');
    var PC_CURRENT_TF         = JSON.parse(document.getElementById('pcCurrentTfJson').textContent          || '""');
    var PC_ROOT_TREES         = JSON.parse(document.getElementById('pcRootTreesJson').textContent         || '[]');

    var PC = {
        programmeId:   <?= (int)$pcProgrammeId ?>,
        programmeName: <?= json_encode($pcProgrammeName) ?>,
        localSeq:      0,
        drawingIdSeq:  0,
        roots:         []
    };

    function $(id) { return document.getElementById(id); }
    function getModal() { return $('pcModal'); }
    function nextLocalId() { return ++PC.localSeq; }
    function nextDrawingId() { return ++PC.drawingIdSeq; }

    function fillSelect(sel, values, opts) {
        opts = opts || {};
        var cur = sel.value;
        sel.innerHTML = '';
        if (opts.placeholder !== undefined) {
            var ph = document.createElement('option');
            ph.value = ''; ph.textContent = opts.placeholder;
            sel.appendChild(ph);
        }
        values.forEach(function (v) {
            var o = document.createElement('option');
            o.value = v; o.textContent = v;
            sel.appendChild(o);
        });
        if (cur !== undefined && cur !== null && cur !== '') sel.value = cur;
    }

    function hydrateFromDb() {
        PC.roots = [];
        PC_ROOT_TREES.forEach(function (t) {
            var root = {
                localId:   nextLocalId(),
                dbId:      t.id,
                collapsed: true,
                fields: {
                    candle_name:     t.candle_name     || '',
                    price_level:     t.price_level     || '',
                    timeframe:       t.timeframe       || PC_CURRENT_TF || '',
                    candle_type:     t.candle_type     || '',
                    candle_position: t.position        || '0',
                    candle_search:   '',
                    operator:        t.operator        || ''
                },
                refs:     [],
                drawings: []
            };

            var plainRefs = [];
            var rerefRows = [];

            (t.refs || []).forEach(function (rf) {
                var isReref = (parseInt(rf.re_referenced_root, 10) === 1);
                if (isReref) rerefRows.push(rf);
                else plainRefs.push(rf);
            });

            plainRefs.forEach(function (rf) {
                root.refs.push({
                    localId: nextLocalId(),
                    dbId:    rf.id,
                    fields: {
                        candle_name:     rf.candle_name     || '',
                        price_level:     rf.price_level     || '',
                        timeframe:       rf.timeframe       || root.fields.timeframe,
                        candle_type:     rf.candle_type     || '',
                        candle_position: rf.position        || '0',
                        candle_search:   rf.candle_search   || '',
                        operator:        rf.operator        || ''
                    },
                    reReferencedRoot: false,
                    rerefStack:       []
                });
            });

            for (var i = 0; i + 1 < rerefRows.length; i += 2) {
                var authorRow     = rerefRows[i];
                var referencedRow = rerefRows[i + 1];

                var pair = {
                    localId: nextLocalId(),
                    author: {
                        localId: nextLocalId(),
                        dbId:    authorRow.id,
                        fields: {
                            candle_name:     authorRow.candle_name     || '',
                            price_level:     authorRow.price_level     || '',
                            timeframe:       authorRow.timeframe       || root.fields.timeframe,
                            candle_position: authorRow.position        || '0',
                            candle_search:   authorRow.candle_search   || '',
                            operator:        authorRow.operator        || ''
                        }
                    },
                    referenced: {
                        localId: nextLocalId(),
                        dbId:    referencedRow.id,
                        fields: {
                            candle_name:     referencedRow.candle_name     || '',
                            price_level:     referencedRow.price_level     || '',
                            timeframe:       referencedRow.timeframe       || root.fields.timeframe,
                            candle_position: referencedRow.position        || '0',
                            candle_search:   referencedRow.candle_search   || '',
                            operator:        ''
                        }
                    }
                };

                var parent = root.refs.length ? root.refs[root.refs.length - 1] : null;
                if (parent && parent.rerefStack) parent.rerefStack.push(pair);
                else root.refs.push({
                    localId: nextLocalId(), dbId: null,
                    fields: {
                        candle_name: '', price_level: '',
                        timeframe: root.fields.timeframe,
                        candle_type: '',
                        candle_position: '0', candle_search: '', operator: ''
                    },
                    reReferencedRoot: false,
                    rerefStack: [pair]
                });
            }

            (t.drawings || []).forEach(function (dr) {
                root.drawings.push({
                    localId:  nextLocalId(),
                    dbId:     dr.id,
                    fields: {
                        drawing_id:               parseInt(dr.drawing_id, 10) || 0,
                        drawing_tools:            dr.drawing_tools || '',
                        from_root_price_level:    dr.from_root_price_level || '',
                        drawing_destination:      dr.drawing_destination || '',
                        to_reference_price_level: dr.to_reference_price_level || ''
                    }
                });
            });

            root.drawings.forEach(function (d) {
                if (d.fields.drawing_id > PC.drawingIdSeq) {
                    PC.drawingIdSeq = d.fields.drawing_id;
                }
            });

            PC.roots.push(root);
        });
    }

    function buildCandleCard(host, block, opts) {
        var tpl = $('pcCandleBlockTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);

        node.setAttribute('data-local-id', block.localId);

        var roleLabel = node.querySelector('[data-role="roleLabel"]');
        if (roleLabel) {
            switch (opts.role) {
                case 'root':             roleLabel.textContent = 'Root Candle';        break;
                case 'ref':              roleLabel.textContent = 'Reference Candle';   break;
                case 'rerefAuthor':      roleLabel.textContent = 'Re-ref (Author)';    break;
                case 'rerefReferenced':  roleLabel.textContent = 'Re-ref (Root copy)'; break;
                default:                 roleLabel.textContent = 'Candle Rule';
            }
        }

        var badge = node.querySelector('[data-role="refBadge"]');
        if (badge) {
            if (opts.role === 'ref' && opts.parentRoot) {
                badge.hidden = false;
                badge.textContent = 'references root';
            } else badge.hidden = true;
        }
        var reRefBadge = node.querySelector('[data-role="reRefBadge"]');
        if (reRefBadge) {
            reRefBadge.hidden = !(opts.role === 'rerefAuthor' || opts.role === 'rerefReferenced');
        }
        if (opts.role === 'rerefAuthor' || opts.role === 'rerefReferenced') {
            var rb = document.createElement('span');
            rb.className = 'pc-role-badge ' + (opts.showOperator ? 'pc-role-author' : 'pc-role-referenced');
            rb.textContent = opts.showOperator ? 'author' : 'referenced';
            node.querySelector('.pc-block-title').appendChild(rb);
        }
        if (opts.role === 'rerefAuthor')      node.classList.add('pc-candle-reref-author');
        if (opts.role === 'rerefReferenced')  node.classList.add('pc-candle-reref-referenced');

        var locked = opts.lockedFields || {};
        var showCandleType = (opts.role === 'root' || opts.role === 'ref');

        var nameInp = node.querySelector('[data-field="candle_name"]');
        nameInp.value = block.fields.candle_name || '';
        if (locked.candle_name) { nameInp.readOnly = true; nameInp.classList.add('pc-locked'); }
        nameInp.addEventListener('input', function () {
            if (locked.candle_name) return;
            block.fields.candle_name = nameInp.value.trim();
            if (opts.onCandleNameChange) opts.onCandleNameChange(block, node);
        });

        var ctField = node.querySelector('[data-role="candleTypeField"]');
        if (!showCandleType) {
            if (ctField) ctField.style.display = 'none';
            block.fields.candle_type = '';
        } else {
            var ctSel = node.querySelector('[data-field="candle_type"]');
            fillSelect(ctSel, PC_CANDLE_TYPES, { placeholder: '— none —' });
            ctSel.value = block.fields.candle_type || '';
            ctSel.addEventListener('change', function () {
                block.fields.candle_type = ctSel.value;
            });
        }

        var levelSel = node.querySelector('[data-field="price_level"]');
        fillSelect(levelSel, PC_PRICE_LEVELS, { placeholder: '— select —' });
        levelSel.value = block.fields.price_level || '';
        levelSel.addEventListener('change', function () {
            block.fields.price_level = levelSel.value;
            validateCard(block, node, opts.role);
        });

        var tfSel = node.querySelector('[data-field="timeframe"]');
        fillSelect(tfSel, PC_TIMEFRAMES, { placeholder: '— timeframe —' });
        tfSel.value = block.fields.timeframe || PC_CURRENT_TF || '';
        if (!block.fields.timeframe) block.fields.timeframe = tfSel.value;

        var tfLocked = locked.timeframe || opts.role !== 'root';
        if (tfLocked) { tfSel.disabled = true; tfSel.classList.add('pc-locked'); }

        tfSel.addEventListener('change', function () {
            if (tfLocked) return;
            block.fields.timeframe = tfSel.value;
            if (opts.onTimeframeChange) opts.onTimeframeChange(block, tfSel.value);
            if (tfSel.value && tfSel.value !== PC_CURRENT_TF) {
                PC_CURRENT_TF = tfSel.value;
                dispatchCustom('pc:timeframeChange', { timeframe: tfSel.value });
            }
            validateCard(block, node, opts.role);
        });

        var posInp = node.querySelector('[data-field="candle_position"]');
        posInp.value = (block.fields.candle_position === '' || block.fields.candle_position == null)
            ? '0' : block.fields.candle_position;
        if (locked.candle_position) { posInp.readOnly = true; posInp.classList.add('pc-locked'); }
        posInp.addEventListener('input', function () {
            if (locked.candle_position) return;
            block.fields.candle_position = posInp.value.trim();
            validateCard(block, node, opts.role);
        });

        var searchSel = node.querySelector('[data-field="candle_search"]');
        searchSel.value = block.fields.candle_search || '';
        if (locked.candle_search) { searchSel.disabled = true; searchSel.classList.add('pc-locked'); }
        searchSel.addEventListener('change', function () {
            if (locked.candle_search) return;
            block.fields.candle_search = searchSel.value;
            validateCard(block, node, opts.role);
        });

        var opField = node.querySelector('[data-role="operatorField"]');
        if (!opts.showOperator) {
            if (opField) opField.style.display = 'none';
            block.fields.operator = '';
        }
        var opSel = node.querySelector('[data-field="operator"]');
        if (opts.showOperator) {
            opSel.value = block.fields.operator || '';
            opSel.addEventListener('change', function () {
                block.fields.operator = opSel.value;
                validateCard(block, node, opts.role);
            });
        }

        var removeBtn = node.querySelector('[data-action="remove"]');
        if (opts.removable === false) removeBtn.style.display = 'none';
        else removeBtn.addEventListener('click', function () {
            if (opts.removeHandler) opts.removeHandler();
        });

        host.appendChild(node);
        validateCard(block, node, opts.role);
    }

    function validateCard(block, node, role) {
        var hintEl = node.querySelector('[data-role="hint"]');
        if (!hintEl) return;
        var msg = ''; var warn = false;

        var tfSel = node.querySelector('[data-field="timeframe"]');
        if (tfSel && !tfSel.disabled) tfSel.classList.toggle('pc-invalid', !block.fields.timeframe);

        if (!block.fields.timeframe) { msg = 'Timeframe is required.'; warn = true; }
        else if (block.fields.candle_search === 'random' && !block.fields.price_level) {
            msg = 'Select a price level before using “random”.'; warn = true;
        } else if ((role === 'root' || role === 'rerefReferenced') && block.fields.candle_search) {
            msg = 'Candle searching is redundant on a root/referenced row.';
        }

        hintEl.textContent = msg;
        hintEl.classList.toggle('pc-warn', warn);
    }

    function buildDrawingBlock(host, drawing, root) {
        var tpl = $('pcDrawingBlockTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-local-id', drawing.localId);

        var toolSel = node.querySelector('[data-field="drawing_tools"]');
        var fromSel = node.querySelector('[data-field="from_root_price_level"]');
        var destSel = node.querySelector('[data-field="drawing_destination"]');
        var toField = node.querySelector('[data-role="toRefLevelField"]');
        var toSel   = node.querySelector('[data-field="to_reference_price_level"]');

        fillSelect(toolSel, PC_DRAWING_TOOLS, { placeholder: '— select —' });
        fillSelect(fromSel, PC_PRICE_LEVELS,  { placeholder: '— select —' });
        fillSelect(destSel, PC_DRAWING_DESTS, { placeholder: '— select —' });
        fillSelect(toSel,   PC_PRICE_LEVELS,  { placeholder: '— select —' });

        toolSel.value = drawing.fields.drawing_tools || '';
        fromSel.value = drawing.fields.from_root_price_level || '';
        destSel.value = drawing.fields.drawing_destination || '';
        toSel.value   = drawing.fields.to_reference_price_level || '';

        function syncToFieldVisibility() {
            var show = (destSel.value === 'specific_reference_price_levels');
            toField.style.display = show ? '' : 'none';
        }
        syncToFieldVisibility();

        toolSel.addEventListener('change', function () { drawing.fields.drawing_tools = toolSel.value; });
        fromSel.addEventListener('change', function () { drawing.fields.from_root_price_level = fromSel.value; });
        destSel.addEventListener('change', function () {
            drawing.fields.drawing_destination = destSel.value;
            syncToFieldVisibility();
            if (destSel.value !== 'specific_reference_price_levels') {
                drawing.fields.to_reference_price_level = '';
                toSel.value = '';
            }
        });
        toSel.addEventListener('change', function () { drawing.fields.to_reference_price_level = toSel.value; });

        node.querySelector('[data-action="remove"]').addEventListener('click', function () {
            root.drawings = root.drawings.filter(function (x) { return x.localId !== drawing.localId; });
            renderRootGroups();
        });

        host.appendChild(node);
    }

    function renderRootGroups() {
        var host = $('pcRootGroups');
        if (!host) return;
        host.innerHTML = '';

        if (!PC.roots.length) {
            var ph = document.createElement('div');
            ph.className = 'pc-blocks-placeholder';
            ph.id = 'pcCandlePlaceholder';
            ph.innerHTML = ''
                + '<i class="fa-solid fa-cubes-stacked pc-placeholder-icon"></i>'
                + '<p class="pc-placeholder-title">No candle rules yet</p>'
                + '<p class="pc-placeholder-text">Click <strong>Define Candle</strong> to start.</p>';
            host.appendChild(ph);
            return;
        }

        PC.roots.forEach(function (root) {
            var tpl = $('pcRootGroupTpl');
            var group = tpl.content.firstElementChild.cloneNode(true);
            group.setAttribute('data-root-local-id', root.localId);
            group.setAttribute('data-collapsed', root.collapsed ? '1' : '0');

            var titleEl = group.querySelector('[data-role="rootTitle"]');
            if (titleEl) {
                titleEl.textContent = (root.fields.candle_name && root.fields.candle_name.trim())
                    ? root.fields.candle_name : 'Root Candle';
            }
            var sub = group.querySelector('[data-role="rootSub"]');
            if (sub) {
                var bits = [];
                if (root.fields.price_level) bits.push(root.fields.price_level);
                if (root.fields.timeframe)   bits.push(root.fields.timeframe);
                if (root.fields.candle_type) bits.push(root.fields.candle_type);
                if (root.fields.operator)    bits.push(root.fields.operator);
                sub.textContent = bits.length ? '· ' + bits.join(' · ') : '';
            }

            group.querySelector('[data-action="toggle"]').addEventListener('click', function () {
                root.collapsed = !root.collapsed;
                group.setAttribute('data-collapsed', root.collapsed ? '1' : '0');
            });

            var rootHost = group.querySelector('[data-root-role="root"]');
            buildCandleCard(rootHost, root, {
                role: 'root', showOperator: true, removable: true,
                removeHandler: function () { removeRootGroup(root.localId); },
                lockedFields: {},
                onCandleNameChange: function () {
                    if (titleEl) {
                        titleEl.textContent = (root.fields.candle_name && root.fields.candle_name.trim())
                            ? root.fields.candle_name : 'Root Candle';
                    }
                },
                onTimeframeChange: function (rootBlock, newTf) {
                    propagateTimeframe(root, newTf);
                    renderRootGroups();
                }
            });

            var refList = group.querySelector('[data-role="refList"]');
            root.refs.forEach(function (ref) { renderReferenceWrap(refList, root, ref); });

            group.querySelector('[data-action="add-ref"]').addEventListener('click', function () {
                addReference(root.localId);
            });

            var drawingList = group.querySelector('[data-role="drawingList"]');
            root.drawings.forEach(function (dr) { buildDrawingBlock(drawingList, dr, root); });

            group.querySelector('[data-action="add-drawing"]').addEventListener('click', function () {
                addDrawing(root.localId);
            });

            group.querySelector('[data-action="remove-group"]').addEventListener('click', function () {
                removeRootGroup(root.localId);
            });

            host.appendChild(group);
        });
    }

    function renderReferenceWrap(refList, root, ref) {
        var refTpl = $('pcRefWrapTpl');
        var wrap = refTpl.content.firstElementChild.cloneNode(true);
        wrap.setAttribute('data-ref-local-id', ref.localId);

        var cardHost = wrap.querySelector('[data-ref-role="ref"]');
        buildCandleCard(cardHost, ref, {
            role: 'ref', parentRoot: root.localId, showOperator: false, removable: true,
            removeHandler: function () { removeReference(root.localId, ref.localId); },
            lockedFields: { timeframe: true }
        });

        var stack = wrap.querySelector('[data-role="rerefStack"]');
        (ref.rerefStack || []).forEach(function (pair) { renderRerefPair(stack, root, ref, pair); });

        wrap.querySelector('[data-action="re-ref"]').addEventListener('click', function () {
            addReReference(root.localId, ref.localId);
        });

        refList.appendChild(wrap);
    }

    function renderRerefPair(stack, root, ref, pair) {
        var pairTpl = $('pcRerefPairTpl');
        var node = pairTpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-reref-local-id', pair.localId);

        node.querySelector('[data-action="remove-reref"]').addEventListener('click', function () {
            ref.rerefStack = (ref.rerefStack || []).filter(function (p) { return p.localId !== pair.localId; });
            renderRootGroups();
        });

        var authorHost = node.querySelector('[data-reref-role="author"]');
        buildCandleCard(authorHost, pair.author, {
            role: 'rerefAuthor', parentRoot: root.localId, parentRef: ref.localId,
            showOperator: true, removable: false,
            lockedFields: { candle_name: true, timeframe: true, candle_position: true, candle_search: true }
        });

        var refHost = node.querySelector('[data-reref-role="referenced"]');
        buildCandleCard(refHost, pair.referenced, {
            role: 'rerefReferenced', parentRoot: root.localId, parentRef: ref.localId,
            showOperator: false, removable: false,
            lockedFields: { candle_name: true, timeframe: true, candle_position: true, candle_search: true }
        });

        stack.appendChild(node);
    }

    function blankFields() {
        return {
            candle_name:     '',
            price_level:     '',
            timeframe:       PC_CURRENT_TF || '',
            candle_type:     '',
            candle_position: '0',
            candle_search:   '',
            operator:        ''
        };
    }

    function addRootGroup() {
        var root = { localId: nextLocalId(), dbId: null, collapsed: false,
            fields: blankFields(), refs: [], drawings: [] };
        PC.roots.push(root);
        renderRootGroups();
        return root;
    }

    function addReference(rootLocalId) {
        var root = PC.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        var ref = { localId: nextLocalId(), dbId: null, fields: blankFields(),
            reReferencedRoot: false, rerefStack: [] };
        ref.fields.timeframe = root.fields.timeframe || PC_CURRENT_TF || '';
        root.refs.push(ref);
        root.collapsed = false;
        renderRootGroups();
        return ref;
    }

    function addReReference(rootLocalId, refLocalId) {
        var root = PC.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        var ref = root.refs.filter(function (x) { return x.localId === refLocalId; })[0];
        if (!ref) return;

        var pair = {
            localId: nextLocalId(),
            author: {
                localId: nextLocalId(), dbId: null,
                fields: {
                    candle_name:     ref.fields.candle_name,
                    price_level:     '',
                    timeframe:       ref.fields.timeframe,
                    candle_position: ref.fields.candle_position,
                    candle_search:   ref.fields.candle_search,
                    operator:        ''
                }
            },
            referenced: {
                localId: nextLocalId(), dbId: null,
                fields: {
                    candle_name:     root.fields.candle_name,
                    price_level:     '',
                    timeframe:       root.fields.timeframe,
                    candle_position: root.fields.candle_position,
                    candle_search:   '',
                    operator:        ''
                }
            }
        };

        if (!ref.rerefStack) ref.rerefStack = [];
        ref.rerefStack.push(pair);
        root.collapsed = false;
        renderRootGroups();
        return pair;
    }

    function addDrawing(rootLocalId) {
        var root = PC.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        var drawing = {
            localId: nextLocalId(), dbId: null,
            fields: {
                drawing_id:               nextDrawingId(),
                drawing_tools:            '',
                from_root_price_level:    '',
                drawing_destination:      '',
                to_reference_price_level: ''
            }
        };
        root.drawings.push(drawing);
        root.collapsed = false;
        renderRootGroups();
        return drawing;
    }

    function propagateTimeframe(root, newTf) {
        root.fields.timeframe = newTf || '';
        (root.refs || []).forEach(function (ref) {
            ref.fields.timeframe = newTf || '';
            (ref.rerefStack || []).forEach(function (pair) {
                pair.author.fields.timeframe     = newTf || '';
                pair.referenced.fields.timeframe = newTf || '';
            });
        });
    }

    function removeRootGroup(rootLocalId) {
        PC.roots = PC.roots.filter(function (r) { return r.localId !== rootLocalId; });
        renderRootGroups();
    }

    function removeReference(rootLocalId, refLocalId) {
        var root = PC.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        root.refs = root.refs.filter(function (x) { return x.localId !== refLocalId; });
        renderRootGroups();
    }

    function pcGetConfiguration() {
        var candles = [];
        var drawings = [];

        PC.roots.forEach(function (root) {
            candles.push({
                localId: root.localId, anchorLocalId: null, isRoot: true, reReferencedRoot: false,
                candle_name: root.fields.candle_name || '',
                price_level: root.fields.price_level || '',
                timeframe: root.fields.timeframe || '',
                candle_type: root.fields.candle_type || '',
                candle_position: root.fields.candle_position === '' ? '0' : root.fields.candle_position,
                candle_search: null,
                operator: root.fields.operator || ''
            });

            root.refs.forEach(function (ref) {
                candles.push({
                    localId: ref.localId, anchorLocalId: root.localId, isRoot: false, reReferencedRoot: false,
                    candle_name: ref.fields.candle_name || '',
                    price_level: ref.fields.price_level || '',
                    timeframe: ref.fields.timeframe || root.fields.timeframe || '',
                    candle_type: ref.fields.candle_type || '',
                    candle_position: ref.fields.candle_position === '' ? '0' : ref.fields.candle_position,
                    candle_search: ref.fields.candle_search || null,
                    operator: ''
                });

                (ref.rerefStack || []).forEach(function (pair) {
                    candles.push({
                        localId: pair.author.localId, anchorLocalId: root.localId,
                        isRoot: false, reReferencedRoot: true,
                        candle_name: pair.author.fields.candle_name || '',
                        price_level: pair.author.fields.price_level || '',
                        timeframe: pair.author.fields.timeframe || root.fields.timeframe || '',
                        candle_type: ref.fields.candle_type || '',
                        candle_position: pair.author.fields.candle_position === '' ? '0' : pair.author.fields.candle_position,
                        candle_search: pair.author.fields.candle_search || null,
                        operator: pair.author.fields.operator || ''
                    });
                    candles.push({
                        localId: pair.referenced.localId, anchorLocalId: root.localId,
                        isRoot: false, reReferencedRoot: true,
                        candle_name: pair.referenced.fields.candle_name || '',
                        price_level: pair.referenced.fields.price_level || '',
                        timeframe: pair.referenced.fields.timeframe || root.fields.timeframe || '',
                        candle_type: root.fields.candle_type || '',
                        candle_position: pair.referenced.fields.candle_position === '' ? '0' : pair.referenced.fields.candle_position,
                        candle_search: null,
                        operator: ''
                    });
                });
            });

            (root.drawings || []).forEach(function (d) {
                drawings.push({
                    localId: d.localId, anchorLocalId: root.localId,
                    drawing_id: d.fields.drawing_id,
                    drawing_tools: d.fields.drawing_tools || '',
                    from_root_price_level: d.fields.from_root_price_level || '',
                    drawing_destination: d.fields.drawing_destination || '',
                    to_reference_price_level: d.fields.to_reference_price_level || ''
                });
            });
        });

        return { programmeId: PC.programmeId, candles: candles, drawings: drawings };
    }

    function pcOpen(opts) {
        opts = opts || {};
        if (opts.programmeId != null) PC.programmeId = parseInt(opts.programmeId, 10) || 0;
        if (opts.programmeName)       PC.programmeName = String(opts.programmeName);
        if (Array.isArray(opts.timeframes) && opts.timeframes.length) PC_TIMEFRAMES = opts.timeframes.slice();
        if (opts.currentTimeframe) PC_CURRENT_TF = String(opts.currentTimeframe);
        if (Array.isArray(opts.rootTrees)) PC_ROOT_TREES = opts.rootTrees.slice();

        if (Array.isArray(opts.candleNames) && opts.candleNames.length) {
            PC_CANDLE_NAMES = opts.candleNames.slice();
            var list = document.getElementById('pcCandleNameList');
            if (list) {
                list.innerHTML = '';
                PC_CANDLE_NAMES.forEach(function (n) {
                    var o = document.createElement('option');
                    o.value = n; list.appendChild(o);
                });
            }
        }

        hydrateFromDb();
        renderRootGroups();

        var modal = getModal();
        if (!modal) return;
        modal.setAttribute('data-programme-id', String(PC.programmeId));
        modal.classList.add('pc-active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function pcClose() {
        var modal = getModal();
        if (!modal) return;
        modal.classList.remove('pc-active');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var modal = getModal();
        if (!modal) return;

        var closeBtn  = $('pcModalClose');
        var cancelBtn = $('pcModalCancel');
        var saveBtn   = $('pcModalSave');
        var addCandle = $('pcAddCandleBtn');

        if (closeBtn)  closeBtn.addEventListener('click', pcClose);
        if (cancelBtn) cancelBtn.addEventListener('click', pcClose);
        modal.addEventListener('mousedown', function (e) { if (e.target === modal) pcClose(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('pc-active')) pcClose();
        });

        if (addCandle) addCandle.addEventListener('click', function () { addRootGroup(); });

        if (saveBtn) saveBtn.addEventListener('click', function () {
            var snap = pcGetConfiguration();
            dispatchCustom('pc:save', snap);
        });
    });

    function dispatchCustom(name, detail) {
        try { window.dispatchEvent(new CustomEvent(name, { detail: detail })); }
        catch (err) {
            var ev = document.createEvent('CustomEvent');
            ev.initCustomEvent(name, true, true, detail);
            window.dispatchEvent(ev);
        }
    }

    window.pcOpenConfiguration  = pcOpen;
    window.pcCloseConfiguration = pcClose;
    window.pcGetConfiguration   = pcGetConfiguration;
    window.pcSetTimeframes = function (tfs, current) {
        if (Array.isArray(tfs) && tfs.length) PC_TIMEFRAMES = tfs.slice();
        if (current) PC_CURRENT_TF = String(current);
    };
    window.pcSetRootTrees = function (trees) {
        PC_ROOT_TREES = Array.isArray(trees) ? trees.slice() : [];
    };

})();
</script>