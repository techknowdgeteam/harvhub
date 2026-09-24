<?php
// programme_configuration.php
// Modal-only configuration builder for a programme.
// Refined: multi-root with heir inheritance, authority selection, one-ref-per-root.
// NEW: per-tree global timeframe (dictator) + GLOBAL anchor candle (across all trees).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== DATABASE CONNECTION ====================
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

// ==================== ACCOUNT MANAGEMENT ====================
$pcAccountManagement = [
    'enable_risk_reward_correction' => 0,
    'minimum_risk_reward'           => '0.00',
    'fixed_risk_reward'             => '0.00',
];
if ($pcUserId > 0) {
    try {
        $am = $pdo->prepare("
            SELECT enable_risk_reward_correction, minimum_risk_reward, fixed_risk_reward
            FROM accountmanagement
            WHERE developerid = ?
            ORDER BY id DESC LIMIT 1
        ");
        $am->execute([$pcUserId]);
        $amRow = $am->fetch(PDO::FETCH_ASSOC);
        if ($amRow) {
            $pcAccountManagement['enable_risk_reward_correction'] = (int)$amRow['enable_risk_reward_correction'];
            $pcAccountManagement['minimum_risk_reward']           = (string)$amRow['minimum_risk_reward'];
            $pcAccountManagement['fixed_risk_reward']             = (string)$amRow['fixed_risk_reward'];
        }
    } catch (PDOException $e) { /* keep defaults */ }
}

// ==================== OPTIONS ====================
$pcPriceLevels = [
    'open','high','low','close',
    'candle_center','body_center',
    'high_wick_center','low_wick_center','candle_width_center'
];
$pcCandleTypes = ['bullish','bearish','any'];
$pcOperators   = ['<','>','<=','>=','='];
$pcOrderTypes  = ['buy','buy_stop','buy_limit','sell','sell_stop','sell_limit'];
$pcDrawingTools = ['trendline'];
$pcDrawingColors = ['green','blue','red','purple','custom'];
$pcTargetTypes = ['risk_reward','set_target'];
$pcRiskRewardModes = ['fixed_risk_reward','minimum_risk_reward'];
$pcAnchorIndexes = ['open_time','close_time'];

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

// ==================== EXISTING TREES (with hierarchy) ====================
$pcTrees = [];
if ($pcUserId > 0 && $pcProgrammeId > 0) {
    try {
        $rq = $pdo->prepare("
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
                   root_ref_count, resolved_root_id, triggered_at,
                   anchor_candle_id, anchor_candle_index
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ?
            ORDER BY tree_id ASC, evaluation_priority ASC, id ASC
            LIMIT 8000
        ");
        $rq->execute([$pcUserId, $pcProgrammeId]);
        $byTree = [];
        while ($r = $rq->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int)$r['tree_id'];
            if (!isset($byTree[$tid])) {
                $byTree[$tid] = [
                    'tree_id'   => $tid,
                    'timeframe' => '',
                    'anchor_candle_id'    => null,
                    'anchor_candle_index' => '',
                    'collapsed' => true,
                    'roots'     => [],
                    'drawings'  => [],
                    'trades'    => [],
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
                'anchor_candle_id'         => $r['anchor_candle_id'] !== null ? (int)$r['anchor_candle_id'] : null,
                'anchor_candle_index'      => $r['anchor_candle_index'],
            ];
            if ($r['row_role'] === 'root') {
                $byTree[$tid]['roots'][] = $row;
                if ($byTree[$tid]['timeframe'] === '' && !empty($row['timeframe'])) {
                    $byTree[$tid]['timeframe'] = $row['timeframe'];
                }
                // Tree-level anchor: first root that carries one wins
                if ($byTree[$tid]['anchor_candle_id'] === null && $row['anchor_candle_id'] !== null) {
                    $byTree[$tid]['anchor_candle_id']    = $row['anchor_candle_id'];
                    $byTree[$tid]['anchor_candle_index'] = $row['anchor_candle_index'];
                }
            } elseif ($r['row_role'] === 'drawing') {
                $byTree[$tid]['drawings'][] = $row;
            } elseif ($r['row_role'] === 'trade') {
                $byTree[$tid]['trades'][] = $row;
            } else {
                $byTree[$tid]['_orphan_rows'][] = $row;
            }
        }

        foreach ($byTree as $tid => &$tree) {
            $roots = &$tree['roots'];
            $orphans = isset($tree['_orphan_rows']) ? $tree['_orphan_rows'] : [];

            $refsByRoot = [];
            foreach ($orphans as $row) {
                if ($row['row_role'] === 'root_ref') {
                    $row['re_ref_pairs'] = [];
                    $refsByRoot[$row['parent_id']][] = $row;
                }
            }

            foreach ($orphans as $row) {
                if ($row['row_role'] === 're_ref_author'
                    && $row['parent_id'] !== null) {
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

            unset($tree['_orphan_rows']);
        }
        unset($tree);

        foreach ($byTree as &$t) {
            if ($t['timeframe'] === '' && !empty($t['roots'])) {
                $t['timeframe'] = $t['roots'][0]['timeframe'] ?: $pcCurrentTf;
            }
        }
        unset($t);

        $pcTrees = array_values($byTree);
    } catch (PDOException $e) { $pcTrees = []; }
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
    $trees       = is_array($payload['trees'] ?? null) ? $payload['trees'] : [];
    $singleTreeId = isset($payload['tree_id']) ? (int)$payload['tree_id'] : 0;

    $oldRootIdToName  = [];
    $oldRootIdToOrder = [];
    $pendingAnchors   = [];
    if ($singleTreeId > 0) {
        $capture = $pdo->prepare("
            SELECT id, candle_name, root_order
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ? AND tree_id = ? AND row_role = 'root'
        ");
        $capture->execute([$uId, $programmeId, $singleTreeId]);
        while ($cr = $capture->fetch(PDO::FETCH_ASSOC)) {
            $oldRootIdToName[(int)$cr['id']]  = (string)$cr['candle_name'];
            $oldRootIdToOrder[(int)$cr['id']] = (int)$cr['root_order'];
        }
    }

    $rrOverrides = is_array($payload['accountManagement'] ?? null) ? $payload['accountManagement'] : [];

    try {
        $pdo->beginTransaction();

        if ($singleTreeId > 0) {
            $del = $pdo->prepare("DELETE FROM programme_configuration WHERE userid = ? AND programmeid = ? AND tree_id = ?");
            $del->execute([$uId, $programmeId, $singleTreeId]);
            $nextTreeId = $singleTreeId;
            $insertTrees = $trees;
        } else {
            $del = $pdo->prepare("DELETE FROM programme_configuration WHERE userid = ? AND programmeid = ?");
            $del->execute([$uId, $programmeId]);
            $nextTreeId = 1;
            $insertTrees = $trees;
        }

        $ins = $pdo->prepare("
            INSERT INTO programme_configuration
                (userid, programmeid, tree_id, row_role, author_id, parent_id,
                 candle_name, price_level, timeframe, candle_type,
                 candle_position, candle_search, operator, order_type,
                 drawing_id, drawing_tools, draw_from, draw_from_price_level,
                 draw_to, draw_to_price_level, drawing_color,
                 entry_from, entry_from_price_level,
                 exit_at, exit_at_price_level,
                 target, target_price_level,
                 authority_source_id, authority_source_role,
                 root_order, evaluation_priority, is_foundation_root,
                 root_ref_count, resolved_root_id, triggered_at,
                 anchor_candle_id, anchor_candle_index)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($insertTrees as $tree) {
            $treeId = $singleTreeId > 0 ? $singleTreeId : $nextTreeId++;

            $treeTimeframe = isset($tree['timeframe']) ? trim((string)$tree['timeframe']) : '';
            $treeAnchorLocalKey = isset($tree['anchor_local_key']) ? (string)$tree['anchor_local_key'] : '';
            $treeAnchorIndex    = isset($tree['anchor_candle_index']) ? trim((string)$tree['anchor_candle_index']) : '';

            $roots = is_array($tree['roots'] ?? null) ? $tree['roots'] : [];
            if (!$roots) continue;

            usort($roots, function ($a, $b) {
                return ((int)($a['evaluation_priority'] ?? 1)) - ((int)($b['evaluation_priority'] ?? 1));
            });

            $rootLocalToDb        = [];
            $rootDbIds            = [];
            $rootLocalKeyByIndex  = [];

            // ---- 1) INSERT ROOTS (anchor written at tree level below) ----
            foreach ($roots as $rIdx => $rootRow) {
                $isFoundation = ($rIdx === 0) ? 1 : 0;
                $rootOrder    = $rIdx + 1;
                $evalPriority = $rootOrder;

                $rowTf = $treeTimeframe !== '' ? $treeTimeframe : self_tf($rootRow['timeframe'] ?? null);

                $ins->execute([
                    $uId, $programmeId, $treeId, 'root', null, null,
                    $rootRow['candle_name'] ?? null,
                    $rootRow['price_level'] ?? null,
                    $rowTf,
                    self_ct($rootRow['candle_type'] ?? null),
                    $rootRow['candle_position'] ?? '0',
                    null,
                    self_op($rootRow['operator'] ?? null),
                    null,
                    null, null, null, null, null, null, null,
                    null, null, null, null, null, null,
                    null, 'none',
                    $rootOrder, $evalPriority, $isFoundation,
                    0, null, null,
                    null, null
                ]);

                $rootDbId = (int)$pdo->lastInsertId();
                $rootDbIds[$rIdx] = $rootDbId;

                $lk = isset($rootRow['local_key']) ? (string)$rootRow['local_key'] : '';
                if ($lk !== '') {
                    $rootLocalToDb[$lk] = $rootDbId;
                    $rootLocalKeyByIndex[$rIdx] = $lk;
                }
            }

            // ---- 2) INSERT ROOT REFERENCES ----
            $refLocalToDb = [];

            foreach ($roots as $rIdx => $rootRow) {
                $rootDbId = isset($rootDbIds[$rIdx]) ? (int)$rootDbIds[$rIdx] : 0;
                if ($rootDbId <= 0) continue;

                $rootRefs = is_array($rootRow['root_refs'] ?? null) ? $rootRow['root_refs'] : [];
                if (count($rootRefs) > 1) {
                    $rootRefs = [ $rootRefs[0] ];
                }

                foreach ($rootRefs as $refIdx => $ref) {
                    $ins->execute([
                        $uId, $programmeId, $treeId, 'root_ref', $rootDbId, $rootDbId,
                        $ref['candle_name'] ?? null,
                        $ref['price_level'] ?? null,
                        $treeTimeframe !== '' ? $treeTimeframe : self_tf($ref['timeframe'] ?? null),
                        self_ct($ref['candle_type'] ?? null),
                        $ref['candle_position'] ?? '0',
                        self_search($ref['candle_search'] ?? null),
                        null, null,
                        null, null, null, null, null, null, null,
                        null, null, null, null, null, null,
                        null, 'none',
                        1, 1, 0,
                        0, null, null,
                        null, null
                    ]);
                    $refDbId = (int)$pdo->lastInsertId();

                    $refLocalKey = 'ROOT:' . $rIdx . ':REF:' . $refIdx;
                    $refLocalToDb[$refLocalKey] = $refDbId;

                    $upd = $pdo->prepare("UPDATE programme_configuration SET root_ref_count = 1 WHERE id = ?");
                    $upd->execute([$rootDbId]);

                    // ---- 3) RE-REFERENCE PAIRS ----
                    $pairs = is_array($ref['re_ref_pairs'] ?? null) ? $ref['re_ref_pairs'] : [];
                    foreach ($pairs as $pair) {
                        $author  = $pair['author']     ?? null;
                        $servant = $pair['referenced'] ?? null;
                        if (!$author || !$servant) continue;

                        $ins->execute([
                            $uId, $programmeId, $treeId, 're_ref_author', $refDbId, $refDbId,
                            $ref['candle_name'] ?? null,
                            $author['price_level'] ?? null,
                            $treeTimeframe !== '' ? $treeTimeframe : self_tf($ref['timeframe'] ?? null),
                            self_ct($ref['candle_type'] ?? null),
                            $ref['candle_position'] ?? '0',
                            self_search($ref['candle_search'] ?? null),
                            self_op($author['operator'] ?? null),
                            null,
                            null, null, null, null, null, null, null,
                            null, null, null, null, null, null,
                            null, 'none',
                            1, 1, 0, 0, null, null,
                            null, null
                        ]);
                        $authorDbId = (int)$pdo->lastInsertId();

                        $ins->execute([
                            $uId, $programmeId, $treeId, 're_ref_servant', $authorDbId, $authorDbId,
                            $rootRow['candle_name'] ?? null,
                            $servant['price_level'] ?? null,
                            $treeTimeframe !== '' ? $treeTimeframe : self_tf($rootRow['timeframe'] ?? null),
                            self_ct($rootRow['candle_type'] ?? null),
                            $rootRow['candle_position'] ?? '0',
                            null, null, null,
                            null, null, null, null, null, null, null,
                            null, null, null, null, null, null,
                            null, 'none',
                            1, 1, 0, 0, null, null,
                            null, null
                        ]);
                    }
                }
            }

            // ---- 4) UPDATE AUTHORITY SOURCE IDS ----
            foreach ($roots as $rIdx => $rootRow) {
                if (empty($rootRow['authority_local_key'])) continue;
                $childDbId = isset($rootDbIds[$rIdx]) ? (int)$rootDbIds[$rIdx] : 0;
                if ($childDbId <= 0) continue;

                $srcLocalKey = (string)$rootRow['authority_local_key'];

                $srcDbId = isset($rootLocalToDb[$srcLocalKey]) ? (int)$rootLocalToDb[$srcLocalKey] : 0;
                $srcRole = 'root';

                if ($srcDbId <= 0 && isset($refLocalToDb[$srcLocalKey])) {
                    $srcDbId = (int)$refLocalToDb[$srcLocalKey];
                    $srcRole = 'root_ref';
                }

                if ($srcDbId <= 0) {
                    foreach ($rootLocalKeyByIndex as $i3 => $lk3) {
                        if ($lk3 === $srcLocalKey && isset($rootDbIds[$i3])) {
                            $srcDbId = (int)$rootDbIds[$i3];
                            $srcRole = 'root';
                            break;
                        }
                    }
                }

                if ($srcDbId <= 0) continue;

                $upd = $pdo->prepare("
                    UPDATE programme_configuration
                    SET authority_source_id = ?, authority_source_role = ?
                    WHERE id = ?
                ");
                $upd->execute([$srcDbId, $srcRole, $childDbId]);
            }

            // ---- 4.5) APPLY TREE-LEVEL ANCHOR TO ALL ROOTS ----
            //
            // The anchor is GLOBAL across all trees. It may point at:
            //   1) a root in THIS tree (fast path — resolved via $rootLocalToDb)
            //   2) a root in ANOTHER tree (resolved by candle name across the
            //      whole programme)
            //
            // We also record the anchor intent so a post-pass (section 7) can
            // re-resolve it after every tree has been inserted — this is what
            // makes "Save All" survive a page reload.
            $anchorGlobalKey  = isset($tree['anchor_global_key'])  ? (string)$tree['anchor_global_key']  : '';
            $anchorCandleName = isset($tree['anchor_candle_name']) ? trim((string)$tree['anchor_candle_name']) : '';
            $anchorIndexRaw   = isset($tree['anchor_candle_index']) ? trim((string)$tree['anchor_candle_index']) : '';
            $anchorIndex      = in_array($anchorIndexRaw, ['open_time', 'close_time'], true)
                                    ? $anchorIndexRaw : null;

            $anchorDbId = 0;

            // Fast path: anchor points at a root in the current tree.
            if ($anchorGlobalKey !== '' && isset($rootLocalToDb[$anchorGlobalKey])) {
                $anchorDbId = (int)$rootLocalToDb[$anchorGlobalKey];
            }

            // Cross-tree path: resolve by candle name across the whole programme.
            // This also covers the case where the anchor root was inserted
            // earlier in this same request (previous tree in the loop).
            if ($anchorDbId <= 0 && $anchorCandleName !== '') {
                $anchorLookup = $pdo->prepare("
                    SELECT id
                    FROM programme_configuration
                    WHERE userid = ? AND programmeid = ? AND row_role = 'root'
                      AND candle_name = ?
                    ORDER BY tree_id ASC, root_order ASC
                    LIMIT 1
                ");
                $anchorLookup->execute([$uId, $programmeId, $anchorCandleName]);
                $anchorRow = $anchorLookup->fetch(PDO::FETCH_ASSOC);
                if ($anchorRow) {
                    $anchorDbId = (int)$anchorRow['id'];
                }
            }

            if ($anchorDbId > 0) {
                foreach ($rootDbIds as $rdb) {
                    $upd2 = $pdo->prepare("
                        UPDATE programme_configuration
                        SET anchor_candle_id = ?, anchor_candle_index = ?
                        WHERE id = ?
                    ");
                    $upd2->execute([$anchorDbId, $anchorIndex, (int)$rdb]);
                }
            } else if ($anchorCandleName !== '') {
                // Anchor target not inserted yet (it lives in a LATER tree in
                // this request). Queue it for the post-pass below.
                if (!isset($pendingAnchors)) { $pendingAnchors = []; }
                $pendingAnchors[] = [
                    'root_db_ids'  => array_values($rootDbIds),
                    'candle_name'  => $anchorCandleName,
                    'anchor_index' => $anchorIndex
                ];
            }
            // ---- 4.6) REPAIR CROSS-TREE ANCHOR REFERENCES ----
            if ($singleTreeId > 0 && !empty($oldRootIdToName)) {
                $newRootIdByNameOrder = [];
                foreach ($rootDbIds as $rIdx2 => $newRootId) {
                    $rootRowNow = isset($roots[$rIdx2]) ? $roots[$rIdx2] : null;
                    if (!$rootRowNow) continue;
                    $nm    = (string)($rootRowNow['candle_name'] ?? '');
                    $order = $rIdx2 + 1;
                    if ($nm === '') continue;
                    $newRootIdByNameOrder[$nm . '::' . $order] = (int)$newRootId;
                }

                foreach ($oldRootIdToName as $oldId => $oldName) {
                    $oldOrder = isset($oldRootIdToOrder[$oldId]) ? $oldRootIdToOrder[$oldId] : 0;
                    $key = $oldName . '::' . $oldOrder;
                    if (!isset($newRootIdByNameOrder[$key])) continue;
                    $newAnchorId = $newRootIdByNameOrder[$key];

                    $repair = $pdo->prepare("
                        UPDATE programme_configuration
                        SET anchor_candle_id = ?
                        WHERE userid = ? AND programmeid = ?
                        AND tree_id <> ?
                        AND anchor_candle_id = ?
                    ");
                    $repair->execute([$newAnchorId, $uId, $programmeId, $singleTreeId, $oldId]);
                }
            }

            // ---- 5) DRAWINGS ----
            $drawings = is_array($tree['drawings'] ?? null) ? $tree['drawings'] : [];
            foreach ($drawings as $d) {
                $drawFrom = trim((string)($d['draw_from'] ?? ''));
                $drawFromLvl = trim((string)($d['draw_from_price_level'] ?? ''));
                $drawTo = trim((string)($d['draw_to'] ?? ''));
                $drawToLvl = trim((string)($d['draw_to_price_level'] ?? ''));

                if ($drawFrom === '' || $drawFromLvl === '' || $drawTo === '') continue;
                if (substr($drawTo, -21) === '_specific_price_level' && $drawToLvl === '') continue;

                $dId = isset($d['drawing_id']) ? (int)$d['drawing_id'] : 0;
                if ($dId <= 0) $dId = (int)round(microtime(true) * 1000) % 2000000000;

                $tool = trim((string)($d['drawing_tools'] ?? ''));
                if (!in_array($tool, ['trendline'], true)) $tool = null;

                $color = trim((string)($d['drawing_color'] ?? ''));
                if (!in_array($color, ['green','blue','red','purple','custom'], true)) $color = null;

                $ins->execute([
                    $uId, $programmeId, $treeId, 'drawing', null, null,
                    null, null,
                    $treeTimeframe !== '' ? $treeTimeframe : null,
                    null, null, null, null, null,
                    $dId, $tool,
                    $drawFrom, $drawFromLvl,
                    $drawTo,
                    ($drawToLvl === '' ? null : $drawToLvl),
                    $color,
                    null, null, null, null, null, null,
                    null, 'none',
                    1, 1, 0, 0, null, null,
                    null, null
                ]);
            }

            // ---- 6) TRADES ----
            $trades = is_array($tree['trades'] ?? null) ? $tree['trades'] : [];
            foreach ($trades as $t) {
                $orderType = trim((string)($t['order_type'] ?? ''));
                $entryFrom = trim((string)($t['entry_from'] ?? ''));
                $entryLvl  = trim((string)($t['entry_from_price_level'] ?? ''));
                $exitAt    = trim((string)($t['exit_at'] ?? ''));
                $exitLvl   = trim((string)($t['exit_at_price_level'] ?? ''));
                $targetType = trim((string)($t['target_type'] ?? ''));
                $target    = trim((string)($t['target'] ?? ''));
                $targetLvl = trim((string)($t['target_price_level'] ?? ''));

                if (!in_array($orderType, ['buy','buy_stop','buy_limit','sell','sell_stop','sell_limit'], true)) {
                    continue;
                }
                if ($entryFrom === '' || $entryLvl === '' || $exitAt === '' || $exitLvl === '') continue;

                if (!in_array($targetType, ['risk_reward','set_target'], true)) {
                    continue;
                }
                if ($targetType === 'set_target') {
                    if ($target === '' || $targetLvl === '') continue;
                } else {
                    if (!in_array($target, ['fixed_risk_reward','minimum_risk_reward'], true)) continue;
                    $targetLvl = null;
                }

                $ins->execute([
                    $uId, $programmeId, $treeId, 'trade', null, null,
                    null, null,
                    $treeTimeframe !== '' ? $treeTimeframe : null,
                    null, null, null, null, $orderType,
                    null, null, null, null, null, null, null,
                    $entryFrom, $entryLvl,
                    $exitAt, $exitLvl,
                    $target, $targetLvl,
                    null, 'none',
                    1, 1, 0, 0, null, null,
                    null, null
                ]);
            }
        }

        // ---- 6.5) PERSIST R:R OVERRIDES ----
        $amFixed   = null;
        $amMin     = null;
        if (array_key_exists('fixed_risk_reward', $rrOverrides)) {
            $raw = trim((string)$rrOverrides['fixed_risk_reward']);
            if ($raw !== '' && is_numeric($raw) && (float)$raw >= 0) {
                $amFixed = number_format((float)$raw, 2, '.', '');
            }
        }
        if (array_key_exists('minimum_risk_reward', $rrOverrides)) {
            $raw = trim((string)$rrOverrides['minimum_risk_reward']);
            if ($raw !== '' && is_numeric($raw) && (float)$raw >= 0) {
                $amMin = number_format((float)$raw, 2, '.', '');
            }
        }

        if ($amFixed !== null || $amMin !== null) {
            $chk = $pdo->prepare("
                SELECT id, fixed_risk_reward, minimum_risk_reward
                FROM accountmanagement
                WHERE developerid = ?
                ORDER BY id DESC LIMIT 1
            ");
            $chk->execute([$uId]);
            $amRow = $chk->fetch(PDO::FETCH_ASSOC);

            if ($amRow) {
                $curFixed = (string)$amRow['fixed_risk_reward'];
                $curMin   = (string)$amRow['minimum_risk_reward'];
                $newFixed = ($amFixed !== null) ? $amFixed : $curFixed;
                $newMin   = ($amMin   !== null) ? $amMin   : $curMin;

                if ($newFixed !== $curFixed || $newMin !== $curMin) {
                    $updAm = $pdo->prepare("
                        UPDATE accountmanagement
                        SET fixed_risk_reward   = ?,
                            minimum_risk_reward = ?
                        WHERE id = ?
                    ");
                    $updAm->execute([$newFixed, $newMin, (int)$amRow['id']]);
                }
            } else {
                $newFixed = ($amFixed !== null) ? $amFixed : '0.00';
                $newMin   = ($amMin   !== null) ? $amMin   : '0.00';
                $insAm = $pdo->prepare("
                    INSERT INTO accountmanagement
                        (developerid, enable_risk_reward_correction,
                         minimum_risk_reward, fixed_risk_reward)
                    VALUES (?, 0, ?, ?)
                ");
                $insAm->execute([$uId, $newMin, $newFixed]);
            }
        }

        // ---- 7) POST-PASS: RESOLVE DEFERRED GLOBAL ANCHORS ----
        //
        // Any tree whose anchor pointed at a root belonging to a LATER tree in
        // this same request could not be resolved during that tree's insert.
        // Now that every root across every tree exists in the DB, resolve them
        // by candle name.
        if (!empty($pendingAnchors) && is_array($pendingAnchors)) {
            foreach ($pendingAnchors as $pa) {
                $paName = isset($pa['candle_name']) ? trim((string)$pa['candle_name']) : '';
                if ($paName === '') continue;

                $lookup = $pdo->prepare("
                    SELECT id
                    FROM programme_configuration
                    WHERE userid = ? AND programmeid = ? AND row_role = 'root'
                      AND candle_name = ?
                    ORDER BY tree_id ASC, root_order ASC
                    LIMIT 1
                ");
                $lookup->execute([$uId, $programmeId, $paName]);
                $resolvedRow = $lookup->fetch(PDO::FETCH_ASSOC);
                if (!$resolvedRow) continue;

                $resolvedId    = (int)$resolvedRow['id'];
                $resolvedIndex = isset($pa['anchor_index']) ? $pa['anchor_index'] : null;
                if (!in_array($resolvedIndex, ['open_time', 'close_time'], true)) {
                    $resolvedIndex = null;
                }

                $upd = $pdo->prepare("
                    UPDATE programme_configuration
                    SET anchor_candle_id = ?, anchor_candle_index = ?
                    WHERE id = ?
                ");
                foreach ((array)$pa['root_db_ids'] as $rootId) {
                    $upd->execute([$resolvedId, $resolvedIndex, (int)$rootId]);
                }
            }
        }

        $pdo->commit();

        // ---- Return canonical rows + latest accountmanagement ----
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
                   root_ref_count, resolved_root_id, triggered_at,
                   anchor_candle_id, anchor_candle_index
            FROM programme_configuration
            WHERE userid = ? AND programmeid = ?
            ORDER BY tree_id ASC, evaluation_priority ASC, id ASC
        ");
        $cStmt->execute([$uId, $programmeId]);
        $rows = [];
        while ($r = $cStmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[] = [
                'id'                       => (int)$r['id'],
                'tree_id'                  => (int)$r['tree_id'],
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
                'anchor_candle_id'         => $r['anchor_candle_id'] !== null ? (int)$r['anchor_candle_id'] : null,
                'anchor_candle_index'      => $r['anchor_candle_index'],
            ];
        }

        $amOut = [
            'enable_risk_reward_correction' => 0,
            'minimum_risk_reward'           => '0.00',
            'fixed_risk_reward'             => '0.00',
        ];
        try {
            $am2 = $pdo->prepare("
                SELECT enable_risk_reward_correction, minimum_risk_reward, fixed_risk_reward
                FROM accountmanagement
                WHERE developerid = ?
                ORDER BY id DESC LIMIT 1
            ");
            $am2->execute([$uId]);
            $amRow2 = $am2->fetch(PDO::FETCH_ASSOC);
            if ($amRow2) {
                $amOut['enable_risk_reward_correction'] = (int)$amRow2['enable_risk_reward_correction'];
                $amOut['minimum_risk_reward']           = (string)$amRow2['minimum_risk_reward'];
                $amOut['fixed_risk_reward']             = (string)$amRow2['fixed_risk_reward'];
            }
        } catch (PDOException $e) { /* keep defaults */ }

        echo json_encode(['success' => true, 'rows' => $rows, 'accountManagement' => $amOut]);
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    }
    exit;
}

// ==================== SANITIZERS ====================
function self_tf($tf) { $tf = trim((string)$tf); return $tf === '' ? null : $tf; }
function self_ct($ct) { $ct = trim((string)$ct); return in_array($ct, ['bullish','bearish','any'], true) ? $ct : null; }
function self_op($op) { $op = trim((string)$op); return in_array($op, ['<','>','<=','>=','='], true) ? $op : null; }
function self_search($s) { $s = trim((string)$s); return in_array($s, ['fixed','random','all'], true) ? $s : null; }
?>

<!-- ============================================================
     PROGRAMME CONFIGURATION — MODAL (Multi-Root / Heir Model)
     Tree-level global timeframe + GLOBAL anchor candle.
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
                <p class="pc-modal-subtitle">Build candle rules, drawings &amp; trades</p>
            </div>
            <button type="button" class="pc-modal-close" id="pcModalClose" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="pc-modal-body" id="pcModalBody">
            <div class="pc-actionbar">
                <button type="button" class="pc-btn pc-btn-primary pc-btn-sm" id="pcAddTreeBtn">
                    <i class="fa-solid fa-plus"></i> Add Tree
                </button>
            </div>
            <div id="pcTreeList" class="pc-tree-list"></div>
        </div>

        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-ghost" id="pcModalCancel">Close</button>
            <button type="button" class="pc-btn pc-btn-primary" id="pcModalSave">Save All</button>
        </div>
    </div>
</div>

<!-- ERROR MODAL -->
<div id="pcErrorModal" class="pc-modal" aria-hidden="true">
    <div class="pc-modal-content pc-error-content" role="dialog" aria-modal="true" aria-labelledby="pcErrorTitle">
        <div class="pc-modal-header">
            <div class="pc-modal-heading">
                <h2 class="pc-modal-title" id="pcErrorTitle" style="color:#c0392b;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Save Failed
                </h2>
                <p class="pc-modal-subtitle">The configuration could not be saved.</p>
            </div>
            <button type="button" class="pc-modal-close" id="pcErrorClose" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="pc-modal-body" style="max-height:50vh;">
            <p id="pcErrorText" style="font-size:0.88rem;line-height:1.5;word-break:break-word;white-space:pre-wrap;"></p>
        </div>
        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-primary" id="pcErrorOk">OK</button>
        </div>
    </div>
</div>

<!-- SAVE CONFIRMATION MODAL -->
<div id="pcSuccessModal" class="pc-modal" aria-hidden="true">
    <div class="pc-modal-content pc-error-content" role="dialog" aria-modal="true" aria-labelledby="pcSuccessTitle">
        <div class="pc-modal-header">
            <div class="pc-modal-heading">
                <h2 class="pc-modal-title" id="pcSuccessTitle" style="color:#2e8b57;">
                    <i class="fa-solid fa-circle-check"></i> Configuration Saved
                </h2>
                <p class="pc-modal-subtitle" id="pcSuccessSub">Your configuration has been saved successfully.</p>
            </div>
            <button type="button" class="pc-modal-close" id="pcSuccessClose" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="pc-modal-body" style="max-height:50vh;">
            <p id="pcSuccessText" style="font-size:0.9rem;line-height:1.5;color:var(--text,#222);white-space:pre-wrap;"></p>
        </div>
        <div class="pc-modal-footer">
            <button type="button" class="pc-btn pc-btn-primary" id="pcSuccessOk">OK</button>
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
<script type="application/json" id="pcOperatorsJson"><?= json_encode(array_values($pcOperators)) ?></script>
<script type="application/json" id="pcOrderTypesJson"><?= json_encode(array_values($pcOrderTypes)) ?></script>
<script type="application/json" id="pcDrawingToolsJson"><?= json_encode(array_values($pcDrawingTools)) ?></script>
<script type="application/json" id="pcDrawingColorsJson"><?= json_encode(array_values($pcDrawingColors)) ?></script>
<script type="application/json" id="pcCandleNamesJson"><?= json_encode(array_values($pcCandleNames)) ?></script>
<script type="application/json" id="pcTimeframesJson"><?= json_encode(array_values($pcTimeframes)) ?></script>
<script type="application/json" id="pcCurrentTfJson"><?= json_encode($pcCurrentTf) ?></script>
<script type="application/json" id="pcTreesJson"><?= json_encode(array_values($pcTrees)) ?></script>
<script type="application/json" id="pcTargetTypesJson"><?= json_encode(array_values($pcTargetTypes)) ?></script>
<script type="application/json" id="pcRiskRewardModesJson"><?= json_encode(array_values($pcRiskRewardModes)) ?></script>
<script type="application/json" id="pcAccountManagementJson"><?= json_encode($pcAccountManagement) ?></script>
<script type="application/json" id="pcAnchorIndexesJson"><?= json_encode(array_values($pcAnchorIndexes)) ?></script>

<!-- ============================================================ TEMPLATES ============================================================ -->

<template id="pcTreeTpl">
    <div class="pc-tree" data-tree-local-id="" data-collapsed="1">
        <div class="pc-tree-head">
            <button type="button" class="pc-tree-toggle" data-action="toggle">
                <i class="fa-solid fa-chevron-right pc-tree-caret"></i>
                <i class="fa-solid fa-sitemap pc-tree-icon"></i>
                <span data-role="treeTitle">Tree</span>
                <span class="pc-tree-sub" data-role="treeSub"></span>
            </button>
            <button type="button" class="pc-tree-save" data-action="save-tree" title="Save this tree only">
                <i class="fa-solid fa-floppy-disk"></i>
            </button>
            <button type="button" class="pc-tree-remove" data-action="remove-tree" title="Remove entire tree">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
        <div class="pc-tree-body">
            <!-- TREE-LEVEL GLOBAL TIMEFRAME (the dictator) -->
            <div class="pc-tree-timeframe-bar">
                <div class="pc-tree-timeframe-field">
                    <label class="pc-label">
                        <i class="fa-solid fa-clock"></i>
                        Global timeframe <span class="pc-req">*</span>
                    </label>
                    <select class="pc-input pc-select pc-tree-timeframe" data-field="tree_timeframe"></select>
                    <span class="pc-section-hint">
                        Applied to every root, ref, drawing and trade in this tree.
                    </span>
                </div>
            </div>

            <!-- GLOBAL ANCHOR CANDLE (across all trees) -->
            <div class="pc-tree-anchor-bar" data-role="treeAnchorBar">
                <div class="pc-tree-anchor-field">
                    <label class="pc-label">
                        <i class="fa-solid fa-anchor"></i>
                        Anchor candle
                        <span class="pc-section-hint" style="display:inline;margin-left:4px;">(optional — sync every root to one candle's time)</span>
                    </label>
                    <div class="pc-tree-anchor-grid">
                        <div class="pc-field">
                            <label class="pc-label">Anchor to candle</label>
                            <select class="pc-input pc-select pc-tree-anchor-root" data-field="tree_anchor_root"></select>
                        </div>
                        <div class="pc-field pc-tree-anchor-index-field" data-role="treeAnchorIndexField" style="display:none;">
                            <label class="pc-label">Select <span data-role="treeAnchorNameLabel">candle</span> index time:</label>
                            <select class="pc-input pc-select pc-tree-anchor-index" data-field="tree_anchor_index"></select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pc-tree-section">
                <div class="pc-tree-section-title">
                    Roots
                    <span class="pc-section-hint">(Root #1 is the foundation — must match first)</span>
                </div>
                <div data-role="rootList" class="pc-root-list"></div>
                <button type="button" class="pc-add-btn pc-add-root-btn" data-action="add-root">
                    <i class="fa-solid fa-plus"></i> Add Heir Root
                </button>
            </div>
            <div class="pc-tree-section">
                <div class="pc-tree-section-title">Drawings</div>
                <div data-role="drawingList" class="pc-drawing-list"></div>
                <button type="button" class="pc-add-btn pc-add-drawing-btn" data-action="add-drawing">
                    <i class="fa-solid fa-pen-ruler"></i> Add drawing
                </button>
            </div>
            <div class="pc-tree-section">
                <div class="pc-tree-section-title">
                    Trades
                    <span class="pc-section-hint">(entry / exit / target prices)</span>
                </div>
                <div data-role="tradeList" class="pc-trade-list"></div>
                <button type="button" class="pc-add-btn pc-add-trade-btn" data-action="add-trade">
                    <i class="fa-solid fa-money-bill-trend-up"></i> Add trade price
                </button>
            </div>
        </div>
    </div>
</template>

<template id="pcRootWrapTpl">
    <div class="pc-root-wrap" data-root-local-id="">
        <div class="pc-root-head">
            <span class="pc-root-title" data-role="rootTitle">Root #1</span>
            <span class="pc-root-authority" data-role="rootAuthority" hidden></span>
            <button type="button" class="pc-block-remove" data-action="remove-root" title="Remove root">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
        <div data-role="rootCandleHost"></div>

        <div class="pc-root-ref-section">
            <div class="pc-tree-section-title" style="margin-top:8px;">
                Root Reference
                <span class="pc-section-hint">(max 1 per root)</span>
            </div>
            <div data-role="refList" class="pc-ref-list"></div>
            <button type="button" class="pc-add-btn pc-add-ref-btn" data-action="add-ref">
                <i class="fa-solid fa-plus"></i> Add root ref candle
            </button>
        </div>
        <div class="pc-authority-select" data-role="authoritySelect" hidden>
            <label class="pc-label">Select existing root and ref as authority for next root</label>
            <select class="pc-input pc-select pc-authority-picker" data-field="authority_source"></select>
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
                <i class="fa-solid fa-rotate-left"></i> Re-reference pair
            </span>
            <button type="button" class="pc-block-remove" data-action="remove-reref" title="Remove">
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
                <span class="pc-block-reref-badge" data-role="reRefBadge" hidden>re-ref</span>
                <span class="pc-author-badge" data-role="authorBadge" hidden>author</span>
                <span class="pc-servant-badge" data-role="servantBadge" hidden>servant</span>
                <span class="pc-inherited-badge" data-role="inheritedBadge" hidden>inherited</span>
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
            <div class="pc-field pc-timeframe-field" data-role="timeframeField">
                <label class="pc-label">Timeframe</label>
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
        <div class="pc-block-grid pc-grid-5">
            <div class="pc-field">
                <label class="pc-label">Drawing tool</label>
                <select class="pc-input pc-select pc-drawing-tool" data-field="drawing_tools"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Draw from</label>
                <select class="pc-input pc-select pc-drawing-from" data-field="draw_from"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Draw from price level</label>
                <select class="pc-input pc-select pc-drawing-from-level" data-field="draw_from_price_level"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Draw to</label>
                <select class="pc-input pc-select pc-drawing-to" data-field="draw_to"></select>
            </div>
            <div class="pc-field pc-drawing-to-level-field" data-role="toRefLevelField" style="display:none;">
                <label class="pc-label">Draw to price level</label>
                <select class="pc-input pc-select pc-drawing-to-level" data-field="draw_to_price_level"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Color</label>
                <select class="pc-input pc-select pc-drawing-color" data-field="drawing_color"></select>
            </div>
        </div>
    </div>
</template>

<template id="pcTradeBlockTpl">
    <div class="pc-block pc-trade-block" data-block-kind="trade">
        <div class="pc-block-head">
            <div class="pc-block-title">
                <i class="fa-solid fa-money-bill-trend-up pc-block-icon"></i>
                <span>Trade</span>
            </div>
            <button type="button" class="pc-block-remove" title="Remove" data-action="remove">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        </div>
        <div class="pc-block-grid pc-grid-7">
            <div class="pc-field">
                <label class="pc-label">Order type</label>
                <select class="pc-input pc-select pc-order-type" data-field="order_type"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Entry from</label>
                <select class="pc-input pc-select pc-entry-from" data-field="entry_from"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Entry price level</label>
                <select class="pc-input pc-select pc-entry-level" data-field="entry_from_price_level"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Stoploss at</label>
                <select class="pc-input pc-select pc-exit-at" data-field="exit_at"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Stoploss price level</label>
                <select class="pc-input pc-select pc-exit-level" data-field="exit_at_price_level"></select>
            </div>
            <div class="pc-field">
                <label class="pc-label">Target type</label>
                <select class="pc-input pc-select pc-target-type" data-field="target_type"></select>
            </div>
            <div class="pc-field pc-rr-mode-field" data-role="rrModeField" style="display:none;">
                <label class="pc-label">Risk / Reward</label>
                <select class="pc-input pc-select pc-rr-mode" data-field="target_rr_mode"></select>
            </div>
            <div class="pc-field pc-rr-value-field" data-role="rrValueField" style="display:none;">
                <label class="pc-label">
                    R:R Value <span class="pc-req">*</span>
                </label>
                <input type="number" min="0" step="0.01"
                       class="pc-input pc-rr-value"
                       data-field="rr_value"
                       placeholder="e.g. 2.00">
            </div>
            <div class="pc-field pc-target-field" data-role="targetField">
                <label class="pc-label">Target</label>
                <select class="pc-input pc-select pc-target" data-field="target"></select>
            </div>
            <div class="pc-field pc-target-level-field" data-role="targetLevelField">
                <label class="pc-label">Target price level</label>
                <select class="pc-input pc-select pc-target-level" data-field="target_price_level"></select>
            </div>
        </div>
        <div class="pc-block-foot">
            <span class="pc-block-hint" data-role="hint"></span>
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
.pc-error-content { max-width: 520px; }
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

.pc-tree-list { display: flex; flex-direction: column; gap: 12px; }
.pc-tree { border: 1px solid var(--border-color, #e0e0e0); border-radius: 12px;
    background: var(--bg-card, #fff); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; }
.pc-tree-head { display: flex; align-items: center; justify-content: space-between;
    gap: 8px; padding: 10px 12px; background: rgba(46,139,87,0.06); }
.pc-tree[data-collapsed="0"] .pc-tree-head { border-bottom: 1px solid var(--border-color, #e0e0e0); }
.pc-tree-toggle { flex: 1; display: inline-flex; align-items: center; gap: 8px;
    background: transparent; border: none; cursor: pointer; font-family: inherit;
    font-size: 0.85rem; font-weight: 800; color: var(--accent, #2e8b57);
    text-transform: uppercase; letter-spacing: 0.4px; text-align: left; padding: 0; min-width: 0; }
.pc-tree-caret { font-size: 0.7rem; transition: transform 0.18s ease; flex-shrink: 0; }
.pc-tree[data-collapsed="0"] .pc-tree-caret { transform: rotate(90deg); }
.pc-tree-icon { font-size: 0.95rem; flex-shrink: 0; }
.pc-tree-sub { font-weight: 600; font-size: 0.75rem; color: var(--text-muted, #888);
    text-transform: none; letter-spacing: 0; margin-left: 6px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pc-tree-save { border: none; background: transparent; color: var(--accent, #2e8b57);
    cursor: pointer; padding: 4px 8px; border-radius: 6px; font-size: 0.85rem; flex-shrink: 0; }
.pc-tree-save:hover { background: rgba(46,139,87,0.12); }
.pc-tree-save:disabled { opacity: 0.5; cursor: wait; }
.pc-tree-remove { border: none; background: transparent; color: #c0392b;
    cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 0.85rem; flex-shrink: 0; }
.pc-tree-remove:hover { background: rgba(192,57,43,0.1); }
.pc-tree-body { display: none; padding: 12px 14px 14px; }
.pc-tree[data-collapsed="0"] .pc-tree-body { display: block; }
.pc-tree-section { margin-bottom: 14px; }
.pc-tree-section:last-child { margin-bottom: 0; }
.pc-tree-section-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.4px; color: var(--accent, #2e8b57); margin-bottom: 8px; display: flex; gap: 6px; align-items: baseline; }
.pc-section-hint { font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--text-muted, #888); font-size: 0.7rem; }

/* Tree-level global timeframe bar */
.pc-tree-timeframe-bar {
    margin-bottom: 10px; padding: 12px 14px; border-radius: 10px;
    background: rgba(46,139,87,0.07);
    border: 1px solid rgba(46,139,87,0.25);
}
.pc-tree-timeframe-field { display: flex; flex-direction: column; gap: 4px; }
.pc-tree-timeframe-field .pc-label {
    font-size: 0.76rem; color: var(--accent, #2e8b57); font-weight: 800;
    display: inline-flex; align-items: center; gap: 6px;
}
.pc-tree-timeframe-field .pc-select { max-width: 260px; }
.pc-tree-timeframe-field .pc-section-hint { display: block; margin-top: 4px; }
body.dark-mode .pc-tree-timeframe-bar {
    background: rgba(46,139,87,0.16); border-color: rgba(46,139,87,0.5);
}

/* GLOBAL anchor candle bar (across all trees) */
.pc-tree-anchor-bar {
    margin-bottom: 14px; padding: 12px 14px; border-radius: 10px;
    background: rgba(74,123,216,0.05);
    border: 1px dashed rgba(74,123,216,0.5);
}
.pc-tree-anchor-field { display: flex; flex-direction: column; gap: 6px; }
.pc-tree-anchor-field > .pc-label {
    font-size: 0.76rem; color: #4a7bd8; font-weight: 800;
    display: inline-flex; align-items: center; gap: 6px; margin: 0;
}
.pc-tree-anchor-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.pc-tree-anchor-grid .pc-field { min-width: 0; }
.pc-tree-anchor-grid .pc-label { color: #4a7bd8; }
@media (max-width: 600px) {
    .pc-tree-anchor-grid { grid-template-columns: 1fr; }
}
body.dark-mode .pc-tree-anchor-bar {
    background: rgba(74,123,216,0.1); border-color: rgba(74,123,216,0.5);
}
body.dark-mode .pc-tree-anchor-field > .pc-label,
body.dark-mode .pc-tree-anchor-grid .pc-label { color: #6c93e0; }

.pc-root-list { display: flex; flex-direction: column; gap: 12px; }
.pc-root-wrap { border-left: 3px solid var(--accent, #2e8b57); border-radius: 8px;
    padding-left: 8px; padding-top: 4px; padding-bottom: 4px; }
.pc-root-wrap.pc-heir-root { border-left-color: #8e44ad; }
.pc-root-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.pc-root-title { font-size: 0.78rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.4px; color: var(--accent, #2e8b57); }
.pc-heir-root .pc-root-title { color: #8e44ad; }
.pc-root-authority { font-size: 0.68rem; font-weight: 600; color: #8e44ad;
    background: rgba(155,89,182,0.1); padding: 2px 8px; border-radius: 10px; }
.pc-root-ref-section { margin-top: 10px; }
.pc-authority-select { margin-top: 10px; padding: 10px; border: 1px dashed #8e44ad;
    border-radius: 8px; background: rgba(155,89,182,0.04); }
.pc-authority-select .pc-label { color: #8e44ad; margin-bottom: 6px; display: block; }

.pc-ref-list { display: flex; flex-direction: column; gap: 10px; }
.pc-ref-wrap { border-left: 3px solid #4a7bd8; border-radius: 8px; padding-left: 8px; }
.pc-reref-stack { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.pc-reref-pair { border-left: 3px solid #8e44ad; border-radius: 8px; padding-left: 8px;
    padding-top: 6px; padding-bottom: 6px; background: rgba(155,89,182,0.04); }
.pc-reref-pair-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.pc-reref-pair-title { font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 0.4px; color: #8e44ad; display: inline-flex; align-items: center; gap: 6px; }
.pc-reref-author { margin-bottom: 6px; }
.pc-drawing-list, .pc-trade-list { display: flex; flex-direction: column; gap: 10px; }

.pc-add-btn { margin-top: 8px; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 8px; background: rgba(74,123,216,0.10);
    color: #4a7bd8; border: 1px dashed rgba(74,123,216,0.55);
    font-family: inherit; font-size: 0.78rem; font-weight: 700; cursor: pointer; }
.pc-add-btn:hover { background: rgba(74,123,216,0.18); }
.pc-add-trade-btn { background: rgba(39,174,96,0.10); color: #27ae60; border-color: rgba(39,174,96,0.55); }
.pc-add-trade-btn:hover { background: rgba(39,174,96,0.18); }
.pc-add-root-btn { background: rgba(155,89,182,0.10); color: #8e44ad; border-color: rgba(155,89,182,0.55); }
.pc-add-root-btn:hover { background: rgba(155,89,182,0.18); }
body.dark-mode .pc-add-btn { color: #9bbcf0; border-color: rgba(155,188,240,0.5); }
body.dark-mode .pc-add-trade-btn { color: #7ee2a8; border-color: rgba(126,226,168,0.5); }
body.dark-mode .pc-add-root-btn { color: #c39bd3; border-color: rgba(195,155,211,0.5); }
.pc-reref-btn { margin-top: 8px; display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 11px; border-radius: 8px; background: rgba(155,89,182,0.10);
    color: #8e44ad; border: 1px dashed rgba(155,89,182,0.55);
    font-family: inherit; font-size: 0.74rem; font-weight: 700; cursor: pointer; }
.pc-reref-btn:hover { background: rgba(155,89,182,0.18); }
body.dark-mode .pc-reref-btn { color: #c39bd3; border-color: rgba(195,155,211,0.5); }

.pc-block { background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 12px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.pc-candle-block { border-left: 4px solid var(--accent, #2e8b57); }
.pc-candle-block.pc-role-reref-author { border-left-color: #8e44ad; }
.pc-candle-block.pc-role-reref-servant { border-left-color: #b39ddb; opacity: 0.95; }
.pc-candle-block.pc-role-inherited { border-left-color: #8e44ad; }
.pc-drawing-block { border-left: 4px solid #4a7bd8; background: rgba(74,123,216,0.03); }
.pc-trade-block { border-left: 4px solid #27ae60; background: rgba(39,174,96,0.03); }
.pc-trade-block .pc-block-icon { color: #27ae60; }
.pc-block-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.pc-block-title { display: inline-flex; align-items: center; gap: 8px; font-weight: 700;
    font-size: 0.85rem; color: var(--text, #222); flex-wrap: wrap; }
.pc-block-icon { color: var(--accent, #2e8b57); }
.pc-role-reref-author .pc-block-icon { color: #8e44ad; }
.pc-role-reref-servant .pc-block-icon { color: #b39ddb; }
.pc-role-inherited .pc-block-icon { color: #8e44ad; }
.pc-drawing-block .pc-block-icon { color: #4a7bd8; }
.pc-block-remove { border: none; background: transparent; color: #c0392b;
    cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 0.85rem; }
.pc-block-remove:hover { background: rgba(192,57,43,0.1); }
.pc-block-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; }
.pc-grid-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
.pc-grid-6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
.pc-grid-7 { grid-template-columns: repeat(7, minmax(0, 1fr)); }
@media (max-width: 900px) { .pc-block-grid, .pc-grid-5, .pc-grid-6, .pc-grid-7 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 600px) { .pc-block-grid, .pc-grid-5, .pc-grid-6, .pc-grid-7 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .pc-block-grid, .pc-grid-5, .pc-grid-6, .pc-grid-7 { grid-template-columns: 1fr; } }

.pc-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.pc-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px;
    color: var(--text-muted, #888); font-weight: 700; }
.pc-req { color: #c0392b; }
.pc-input { width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid var(--border-color, #dcdcdc); border-radius: 8px;
    background: var(--bg, #f7f7f7); color: var(--text, #222); font-family: inherit; font-size: 0.82rem; }
.pc-input:focus { outline: none; border-color: var(--accent, #2e8b57); background: var(--bg-card, #fff); }
.pc-input.pc-invalid { border-color: #c0392b; background: rgba(192,57,43,0.06); }
.pc-input.pc-locked { background: rgba(155,89,182,0.06); border-color: rgba(155,89,182,0.4);
    cursor: not-allowed; color: var(--text-muted, #888); }
.pc-select { cursor: pointer; }
.pc-input[readonly], .pc-input:disabled { cursor: not-allowed; }
.pc-rr-value-hint { font-size: 0.7rem; color: var(--text-muted, #888); margin-top: 3px; }

.pc-author-badge { font-size: 0.62rem; background: rgba(46,139,87,0.18); color: var(--accent, #2e8b57);
    padding: 2px 7px; border-radius: 10px; font-weight: 800; margin-left: 6px;
    text-transform: uppercase; letter-spacing: 0.3px; }
.pc-servant-badge { font-size: 0.62rem; background: rgba(120,120,120,0.16); color: var(--text-muted, #777);
    padding: 2px 7px; border-radius: 10px; font-weight: 800; margin-left: 6px;
    text-transform: uppercase; letter-spacing: 0.3px; }
.pc-block-reref-badge { font-size: 0.62rem; background: rgba(155,89,182,0.18);
    color: #8e44ad; padding: 2px 7px; border-radius: 10px; font-weight: 800; margin-left: 6px;
    text-transform: uppercase; letter-spacing: 0.3px; }
.pc-inherited-badge { font-size: 0.62rem; background: rgba(155,89,182,0.18);
    color: #8e44ad; padding: 2px 7px; border-radius: 10px; font-weight: 800; margin-left: 6px;
    text-transform: uppercase; letter-spacing: 0.3px; }
body.dark-mode .pc-block-reref-badge { background: rgba(155,89,182,0.3); color: #c39bd3; }
body.dark-mode .pc-inherited-badge { background: rgba(155,89,182,0.3); color: #c39bd3; }

.pc-block-foot { margin-top: 6px; min-height: 14px; }
.pc-block-hint { font-size: 0.72rem; color: var(--text-muted, #888); }
.pc-block-hint.pc-warn { color: #c0392b; }

.pc-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center;
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
body.dark-mode .pc-modal-content, body.dark-mode .pc-tree, body.dark-mode .pc-block,
body.dark-mode .pc-reref-pair, body.dark-mode .pc-placeholder, body.dark-mode .pc-modal-footer {
    background: var(--bg-card, #1e1e2a); border-color: var(--border-color, #333); color: var(--text, #eee); }
body.dark-mode .pc-modal-body { background: var(--bg, #171722); }
body.dark-mode .pc-input { background: var(--bg, #2a2a3a); border-color: var(--border-color, #333); color: var(--text, #eee); }
body.dark-mode .pc-btn-ghost { color: var(--text, #eee); border-color: var(--border-color, #333); }
body.dark-mode .pc-ref-wrap { border-left-color: #6c93e0; }
body.dark-mode .pc-reref-pair { border-left-color: #b39ddb; background: rgba(155,89,182,0.08); }
body.dark-mode .pc-tree-head { background: rgba(46,139,87,0.12); }
body.dark-mode .pc-drawing-block { background: rgba(74,123,216,0.08); }
body.dark-mode .pc-trade-block { background: rgba(39,174,96,0.08); }
body.dark-mode .pc-servant-badge { background: rgba(180,180,180,0.16); color: #bbb; }
body.dark-mode .pc-root-wrap { border-left-color: #58d68d; }
body.dark-mode .pc-root-wrap.pc-heir-root { border-left-color: #c39bd3; }
body.dark-mode .pc-authority-select { background: rgba(155,89,182,0.08); }

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

    var PC_PRICE_LEVELS    = JSON.parse(document.getElementById('pcPriceLevelsJson').textContent      || '[]');
    var PC_CANDLE_TYPES    = JSON.parse(document.getElementById('pcCandleTypesJson').textContent      || '[]');
    var PC_OPERATORS       = JSON.parse(document.getElementById('pcOperatorsJson').textContent        || '[]');
    var PC_ORDER_TYPES     = JSON.parse(document.getElementById('pcOrderTypesJson').textContent       || '[]');
    var PC_DRAWING_TOOLS   = JSON.parse(document.getElementById('pcDrawingToolsJson').textContent     || '[]');
    var PC_DRAWING_COLORS  = JSON.parse(document.getElementById('pcDrawingColorsJson').textContent    || '[]');
    var PC_CANDLE_NAMES    = JSON.parse(document.getElementById('pcCandleNamesJson').textContent      || '[]');
    var PC_TIMEFRAMES      = JSON.parse(document.getElementById('pcTimeframesJson').textContent       || '[]');
    var PC_CURRENT_TF      = JSON.parse(document.getElementById('pcCurrentTfJson').textContent        || '""');
    var PC_TREES_SERVER    = JSON.parse(document.getElementById('pcTreesJson').textContent            || '[]');
    var PC_TARGET_TYPES    = JSON.parse(document.getElementById('pcTargetTypesJson').textContent      || '[]');
    var PC_RR_MODES        = JSON.parse(document.getElementById('pcRiskRewardModesJson').textContent  || '[]');
    var PC_ACCOUNT_MGMT    = JSON.parse(document.getElementById('pcAccountManagementJson').textContent|| '{}');
    var PC_ANCHOR_INDEXES  = JSON.parse(document.getElementById('pcAnchorIndexesJson').textContent   || '["open_time","close_time"]');

    var PC = {
        programmeId:   <?= (int)$pcProgrammeId ?>,
        programmeName: <?= json_encode($pcProgrammeName) ?>,
        localSeq:      0,
        drawingIdSeq:  0,
        trees:         []
    };

    function $(id) { return document.getElementById(id); }
    function getModal() { return $('pcModal'); }
    function getErrorModal() { return $('pcErrorModal'); }
    function getSuccessModal() { return $('pcSuccessModal'); }
    function nextLocalId() { return ++PC.localSeq; }
    function nextDrawingId() { return ++PC.drawingIdSeq; }
    function uid() { return 'loc_' + nextLocalId() + '_' + Date.now(); }

    function pcDebugLog(level, msg, payload) {
        try {
            if (typeof window.pcDebug === 'function') {
                window.pcDebug(level, '[config] ' + msg, payload);
            }
        } catch (e) { /* ignore */ }
    }

    function fillSelect(sel, values, opts) {
        if (!sel) return;
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
            if (typeof v === 'object' && v !== null) {
                o.value = v.value; o.textContent = v.label;
            } else {
                o.value = v; o.textContent = v;
            }
            sel.appendChild(o);
        });
        if (cur !== undefined && cur !== null && cur !== '') sel.value = cur;
    }

    // ============================================================
    // GLOBAL CANDLE COLLECTION (across ALL trees)
    // ============================================================
    function buildGlobalAnchorOptions() {
        var opts = [];
        var seen = {};

        PC.trees.forEach(function (tree, treeIdx) {
            (tree.roots || []).forEach(function (root, rootIdx) {
                var nm = (root.fields.candle_name || '').trim();
                if (!nm) return;

                var key = nm + '::root::' + tree.localId + '::' + root.localId;
                if (seen[key]) return;
                seen[key] = true;

                opts.push({
                    value: key,
                    label: nm + ' (Tree ' + (treeIdx + 1) + ' Root #' + (rootIdx + 1) + ')'
                });

                (root.refs || []).forEach(function (ref, refIdx) {
                    var refNm = (ref.fields.candle_name || '').trim();
                    if (!refNm) return;

                    var refKey = refNm + '::ref::' + tree.localId + '::' + ref.localId;
                    if (seen[refKey]) return;
                    seen[refKey] = true;

                    opts.push({
                        value: refKey,
                        label: refNm + ' (Tree ' + (treeIdx + 1) + ' Root#' + (rootIdx + 1) + ' Ref#' + (refIdx + 1) + ')'
                    });
                });
            });
        });

        return opts;
    }

    // Resolve an anchor selection value to a concrete root in the CURRENT tree.
    // Anchor values are global: "candleName::root::treeLocalId::rootLocalId"
    // or "candleName::ref::treeLocalId::refLocalId"
    function resolveAnchorForTree(tree, anchorValue) {
        if (!anchorValue || !tree) return null;

        var parts = anchorValue.split('::');
        if (parts.length < 4) return null;

        var kind      = parts[1]; // 'root' or 'ref'
        var treeLid   = parts[2];
        var entityLid = parts[3];

        // Only return a match when the anchor lives in the tree being saved.
        if (treeLid !== tree.localId) return null;

        if (kind === 'root') {
            var root = (tree.roots || []).filter(function (r) { return r.localId === entityLid; })[0];
            if (root) return { type: 'root', localId: root.localId };

            // Fallback: anchor stored as a root local key from a previous save
            var asRoot = (tree.roots || []).filter(function (r) { return r.localId === parts[0]; })[0];
            if (asRoot) return { type: 'root', localId: asRoot.localId };
            return null;
        }

        if (kind === 'ref') {
            var found = null;
            (tree.roots || []).forEach(function (r) {
                (r.refs || []).forEach(function (ref) {
                    if (ref.localId === entityLid) found = ref.localId;
                });
            });
            if (found) return { type: 'ref', localId: found };
            return null;
        }

        return null;
    }

    // ============================================================
    // SOURCE OPTIONS
    // ============================================================
    function buildSourceOptions(tree, rootLocalId) {
        var opts = [];
        var root = (tree.roots || []).filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return opts;

        var rootName = (root.fields.candle_name || '').trim() || 'Root';
        opts.push({ value: 'ROOT', label: rootName });

        (tree.roots || []).forEach(function (r, ri) {
            (r.refs || []).forEach(function (ref, refIdx) {
                var nm = (ref.fields.candle_name || '').trim() || ('Root#' + (ri+1) + ' Ref#' + (refIdx+1));
                opts.push({ value: 'ROOT:' + ri + ':REF:' + refIdx, label: nm });
            });
        });
        return opts;
    }

    function buildDrawToOptions(tree, rootLocalId, drawFromValue) {
        var opts = [];
        var root = (tree.roots || []).filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return opts;

        var rootName = (root.fields.candle_name || '').trim() || 'Root';
        var excludeRoot = (drawFromValue === 'ROOT');

        if (!excludeRoot) {
            opts.push({ value: 'ROOT',                       label: rootName });
            opts.push({ value: 'ROOT_axis',                  label: rootName + '_axis' });
            opts.push({ value: 'ROOT_specific_price_level',  label: rootName + '_specific_price_level' });
        }

        (tree.roots || []).forEach(function (r, ri) {
            (r.refs || []).forEach(function (ref, refIdx) {
                var nm = (ref.fields.candle_name || '').trim() || ('Root#' + (ri+1) + ' Ref#' + (refIdx+1));
                var refKey = 'ROOT:' + ri + ':REF:' + refIdx;
                if (drawFromValue === refKey) return;
                opts.push({ value: refKey,                            label: nm });
                opts.push({ value: refKey + '_axis',                  label: nm + '_axis' });
                opts.push({ value: refKey + '_specific_price_level',  label: nm + '_specific_price_level' });
            });
        });

        opts.push({ value: 'any_candle_intercept', label: 'any_candle_intercept' });
        return opts;
    }

    // ============================================================
    // ERROR / SUCCESS MODALS
    // ============================================================
    function pcShowError(message) {
        pcDebugLog('error', message || 'Unknown error.');
        var modal = getErrorModal();
        var text  = $('pcErrorText');
        if (text) text.textContent = message || 'Unknown error.';
        if (modal) { modal.classList.add('pc-active'); modal.setAttribute('aria-hidden', 'false'); }
    }
    function pcCloseError() {
        var modal = getErrorModal();
        if (!modal) return;
        modal.classList.remove('pc-active');
        modal.setAttribute('aria-hidden', 'true');
    }
    function pcShowSuccess(message, sub) {
        pcDebugLog('info', message || 'Saved.');
        var modal = getSuccessModal();
        var text  = $('pcSuccessText');
        var subEl = $('pcSuccessSub');
        if (text)  text.textContent  = message || 'Your configuration has been saved successfully.';
        if (subEl) subEl.textContent = sub || 'All changes were applied.';
        if (modal) { modal.classList.add('pc-active'); modal.setAttribute('aria-hidden', 'false'); }
    }
    function pcCloseSuccess() {
        var modal = getSuccessModal();
        if (!modal) return;
        modal.classList.remove('pc-active');
        modal.setAttribute('aria-hidden', 'true');
    }
    window.pcShowError   = pcShowError;
    window.pcShowSuccess = pcShowSuccess;

    // ============================================================
    // HYDRATE FROM SERVER
    // ============================================================
    function hydrateFromServer() {
        PC.trees = [];
        PC_TREES_SERVER.forEach(function (t) {
            var treeTf = (t.timeframe || '').trim();
            if (!treeTf) {
                treeTf = (t.roots && t.roots[0] && t.roots[0].timeframe)
                    ? t.roots[0].timeframe : (PC_CURRENT_TF || '');
            }

            var tree = {
                localId:  uid(),
                dbTreeId: t.tree_id,
                collapsed: true,
                timeframe: treeTf,
                anchorLocalKey: null,      // global anchor value (new format) or local key fallback
                anchorDbId: t.anchor_candle_id || null,
                anchorIndex: t.anchor_candle_index || '',
                roots:    [],
                drawings: [],
                trades:   []
            };

            var dbIdToLocal = {};

            (t.roots || []).forEach(function (rootRow, rIdx) {
                var rootLocal = uid();
                var root = {
                    localId: rootLocal,
                    dbId: rootRow.id,
                    rootOrder: rIdx + 1,
                    isFoundation: rIdx === 0,
                    authorityLocalKey: null,
                    fields: {
                        candle_name:     rootRow.candle_name || '',
                        price_level:     rootRow.price_level || '',
                        timeframe:       treeTf || rootRow.timeframe || PC_CURRENT_TF || '',
                        candle_type:     rootRow.candle_type || '',
                        candle_position: rootRow.candle_position || '0',
                        operator:        rootRow.operator || ''
                    },
                    refs: []
                };
                tree.roots.push(root);
                if (rootRow.id) dbIdToLocal[rootRow.id] = rootLocal;

                (rootRow.root_refs || []).forEach(function (rf) {
                    var refBlock = {
                        localId: uid(),
                        dbId: rf.id,
                        fields: {
                            candle_name:     rf.candle_name || '',
                            price_level:     rf.price_level || '',
                            timeframe:       treeTf || rf.timeframe || PC_CURRENT_TF || '',
                            candle_type:     rf.candle_type || '',
                            candle_position: rf.candle_position || '0',
                            candle_search:   rf.candle_search || ''
                        },
                        reRefPairs: []
                    };
                    root.refs.push(refBlock);

                    (rf.re_ref_pairs || []).forEach(function (pair) {
                        if (!pair.author || !pair.referenced) return;
                        var pairBlock = {
                            localId: uid(),
                            author: {
                                localId: uid(), dbId: pair.author.id,
                                fields: {
                                    candle_name:     refBlock.fields.candle_name,
                                    price_level:     pair.author.price_level || '',
                                    timeframe:       treeTf,
                                    candle_type:     refBlock.fields.candle_type,
                                    candle_position: refBlock.fields.candle_position,
                                    candle_search:   refBlock.fields.candle_search,
                                    operator:        pair.author.operator || ''
                                }
                            },
                            servant: {
                                localId: uid(), dbId: pair.referenced.id,
                                fields: {
                                    candle_name:     root.fields.candle_name,
                                    price_level:     pair.referenced.price_level || '',
                                    timeframe:       treeTf,
                                    candle_type:     root.fields.candle_type,
                                    candle_position: root.fields.candle_position,
                                    candle_search:   ''
                                }
                            }
                        };
                        refBlock.reRefPairs.push(pairBlock);
                    });
                });
            });

            // Stash the raw DB anchor id; resolution happens in a second sweep
            // once every tree's roots are known.
            tree._rawAnchorDbId = tree.anchorDbId || null;

            (t.drawings || []).forEach(function (dr) {
                tree.drawings.push({
                    localId: uid(),
                    dbId: dr.id,
                    fields: {
                        drawing_id:               parseInt(dr.drawing_id, 10) || 0,
                        drawing_tools:            dr.drawing_tools || '',
                        draw_from:                dr.draw_from || '',
                        draw_from_price_level:    dr.draw_from_price_level || '',
                        draw_to:                  dr.draw_to || '',
                        draw_to_price_level:      dr.draw_to_price_level || '',
                        drawing_color:            dr.drawing_color || ''
                    }
                });
                if (tree.drawings[tree.drawings.length - 1].fields.drawing_id > PC.drawingIdSeq) {
                    PC.drawingIdSeq = tree.drawings[tree.drawings.length - 1].fields.drawing_id;
                }
            });

            (t.trades || []).forEach(function (tr) {
                var targetRaw = tr.target || '';
                var isRR = (targetRaw === 'fixed_risk_reward' || targetRaw === 'minimum_risk_reward');
                var rrMode = isRR ? targetRaw : 'fixed_risk_reward';
                var rrValue = (rrMode === 'minimum_risk_reward')
                    ? (PC_ACCOUNT_MGMT.minimum_risk_reward || '0.00')
                    : (PC_ACCOUNT_MGMT.fixed_risk_reward   || '0.00');

                tree.trades.push({
                    localId: uid(),
                    dbId: tr.id,
                    fields: {
                        order_type:             tr.order_type || '',
                        entry_from:             tr.entry_from || '',
                        entry_from_price_level: tr.entry_from_price_level || '',
                        exit_at:                tr.exit_at || '',
                        exit_at_price_level:    tr.exit_at_price_level || '',
                        target_type:            isRR ? 'risk_reward' : (targetRaw ? 'set_target' : 'set_target'),
                        target_rr_mode:         rrMode,
                        rr_value:               rrValue,
                        target:                 isRR ? '' : targetRaw,
                        target_price_level:     isRR ? '' : (tr.target_price_level || '')
                    }
                });
            });

            PC.trees.push(tree);
        });

        // ---- Second sweep: resolve every tree's raw anchor DB id to a
        // global anchor key now that all trees' roots are available. ----
        PC.trees.forEach(function (tree) {
            var rawId = tree._rawAnchorDbId;
            if (!rawId) {
                tree.anchorLocalKey = tree.anchorLocalKey || null;
                return;
            }

            var foundGlobal = null;
            PC.trees.forEach(function (otherTree) {
                (otherTree.roots || []).forEach(function (otherRoot) {
                    if (otherRoot.dbId === rawId) {
                        var nm = (otherRoot.fields.candle_name || '').trim();
                        if (nm) {
                            foundGlobal = nm + '::root::' + otherTree.localId + '::' + otherRoot.localId;
                        }
                    }
                    (otherRoot.refs || []).forEach(function (otherRef) {
                        if (otherRef.dbId === rawId) {
                            var refNm = (otherRef.fields.candle_name || '').trim();
                            if (refNm) {
                                foundGlobal = refNm + '::ref::' + otherTree.localId + '::' + otherRef.localId;
                            }
                        }
                    });
                });
            });

            tree.anchorLocalKey = foundGlobal || null;
            delete tree._rawAnchorDbId;
        });
    }

    // ============================================================
    // LIVE UPDATE HELPERS
    // ============================================================
    function updateServantsFromRoot(tree, root, fieldKey, newValue) {
        (root.refs || []).forEach(function (ref) {
            (ref.reRefPairs || []).forEach(function (pair) {
                if (!pair.servant) return;
                if (fieldKey === 'candle_name')     pair.servant.fields.candle_name     = newValue;
                if (fieldKey === 'candle_type')     pair.servant.fields.candle_type     = newValue;
                if (fieldKey === 'candle_position') pair.servant.fields.candle_position = newValue;
            });
        });
    }
    function updateAuthorsFromRef(tree, ref, fieldKey, newValue) {
        (ref.reRefPairs || []).forEach(function (pair) {
            if (!pair.author) return;
            if (fieldKey === 'candle_name')     pair.author.fields.candle_name     = newValue;
            if (fieldKey === 'candle_type')     pair.author.fields.candle_type     = newValue;
            if (fieldKey === 'candle_position') pair.author.fields.candle_position = newValue;
            if (fieldKey === 'candle_search')   pair.author.fields.candle_search   = newValue;
        });
    }
    function updateServantDOMs(tree, root, fieldKey, newValue) {
        (root.refs || []).forEach(function (ref) {
            (ref.reRefPairs || []).forEach(function (pair) {
                if (!pair.servant) return;
                var blockEl = document.querySelector(
                    '.pc-role-reref-servant[data-block-local-id="' + pair.servant.localId + '"]'
                );
                if (!blockEl) return;
                var input = blockEl.querySelector('[data-field="' + fieldKey + '"]');
                if (!input) return;
                input.value = newValue || '';
            });
        });
    }
    function updateAuthorDOMs(tree, ref, fieldKey, newValue) {
        (ref.reRefPairs || []).forEach(function (pair) {
            if (!pair.author) return;
            var blockEl = document.querySelector(
                '.pc-role-reref-author[data-block-local-id="' + pair.author.localId + '"]'
            );
            if (!blockEl) return;
            var input = blockEl.querySelector('[data-field="' + fieldKey + '"]');
            if (!input) return;
            input.value = newValue || '';
        });
    }

    // ============================================================
    // CANDLE CARD
    // ============================================================
    function buildCandleCard(host, block, opts) {
        var tpl = $('pcCandleBlockTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);
        if (block && block.localId) node.setAttribute('data-block-local-id', block.localId);

        var roleLabel = node.querySelector('[data-role="roleLabel"]');
        if (roleLabel) {
            switch (opts.role) {
                case 'root':           roleLabel.textContent = 'Root Candle'; break;
                case 'heirRoot':       roleLabel.textContent = 'Heir Root Candle'; break;
                case 'ref':            roleLabel.textContent = 'Root Reference'; break;
                case 'rerefAuthor':    roleLabel.textContent = 'Re-ref Author'; break;
                case 'rerefServant':   roleLabel.textContent = 'Re-ref Servant'; break;
                default:               roleLabel.textContent = 'Candle Rule';
            }
        }
        if (opts.role === 'rerefAuthor') {
            node.classList.add('pc-role-reref-author');
            var ab = node.querySelector('[data-role="authorBadge"]');
            if (ab) ab.hidden = false;
        }
        if (opts.role === 'rerefServant') {
            node.classList.add('pc-role-reref-servant');
            var sb = node.querySelector('[data-role="servantBadge"]');
            if (sb) sb.hidden = false;
        }
        if (opts.role === 'heirRoot') {
            node.classList.add('pc-role-inherited');
            var ib = node.querySelector('[data-role="inheritedBadge"]');
            if (ib) ib.hidden = false;
        }

        var editable;
        switch (opts.role) {
            case 'root':
                editable = { candle_name:true, candle_type:true, price_level:true, candle_position:true, candle_search:true, operator:true };
                break;
            case 'heirRoot':
                editable = { candle_name:false, candle_type:false, price_level:true, candle_position:false, candle_search:false, operator:true };
                break;
            case 'ref':
                editable = { candle_name:true, candle_type:true, price_level:true, candle_position:true, candle_search:true, operator:false };
                break;
            case 'rerefAuthor':
                editable = { candle_name:false, candle_type:false, price_level:true, candle_position:false, candle_search:false, operator:true };
                break;
            case 'rerefServant':
                editable = { candle_name:false, candle_type:false, price_level:true, candle_position:false, candle_search:false, operator:false };
                break;
            default:
                editable = { candle_name:true, candle_type:true, price_level:true, candle_position:true, candle_search:true, operator:true };
        }

        var nameInp = node.querySelector('[data-field="candle_name"]');
        nameInp.value = block.fields.candle_name || '';
        if (!editable.candle_name) {
            nameInp.readOnly = true; nameInp.classList.add('pc-locked');
        } else {
            nameInp.addEventListener('input', function () {
                block.fields.candle_name = nameInp.value;
                if (opts.onNameChange) opts.onNameChange(nameInp.value);
            });
        }

        var ctField = node.querySelector('[data-role="candleTypeField"]');
        var ctSel   = node.querySelector('[data-field="candle_type"]');
        var showCandleType = (opts.role === 'root' || opts.role === 'heirRoot' || opts.role === 'ref' ||
                              opts.role === 'rerefAuthor' || opts.role === 'rerefServant');
        if (!showCandleType) {
            if (ctField) ctField.style.display = 'none';
        } else {
            fillSelect(ctSel, PC_CANDLE_TYPES, { placeholder: '— none —' });
            ctSel.value = block.fields.candle_type || '';
            if (!editable.candle_type) {
                ctSel.disabled = true; ctSel.classList.add('pc-locked');
            } else {
                ctSel.addEventListener('change', function () {
                    block.fields.candle_type = ctSel.value;
                    if (opts.onTypeChange) opts.onTypeChange(ctSel.value);
                });
            }
        }

        var levelSel = node.querySelector('[data-field="price_level"]');
        fillSelect(levelSel, PC_PRICE_LEVELS, { placeholder: '— select —' });
        levelSel.value = block.fields.price_level || '';
        if (editable.price_level) {
            levelSel.addEventListener('change', function () { block.fields.price_level = levelSel.value; });
        } else {
            levelSel.disabled = true; levelSel.classList.add('pc-locked');
        }

        var tfField = node.querySelector('[data-role="timeframeField"]');
        if (tfField) tfField.style.display = 'none';

        var posInp = node.querySelector('[data-field="candle_position"]');
        posInp.value = (block.fields.candle_position === '' || block.fields.candle_position == null)
            ? '0' : block.fields.candle_position;
        if (editable.candle_position) {
            posInp.addEventListener('input', function () {
                block.fields.candle_position = posInp.value.trim();
                if (opts.onPositionChange) opts.onPositionChange(posInp.value.trim());
            });
        } else {
            posInp.readOnly = true; posInp.classList.add('pc-locked');
        }

        var searchSel = node.querySelector('[data-field="candle_search"]');
        if (block.fields.candle_search) searchSel.value = block.fields.candle_search;
        if (editable.candle_search) {
            searchSel.addEventListener('change', function () {
                block.fields.candle_search = searchSel.value;
                if (opts.onSearchChange) opts.onSearchChange(searchSel.value);
            });
        } else {
            searchSel.disabled = true; searchSel.classList.add('pc-locked');
        }

        var opField = node.querySelector('[data-role="operatorField"]');
        var opSel   = node.querySelector('[data-field="operator"]');
        var showOperator = (opts.role === 'root' || opts.role === 'heirRoot' || opts.role === 'rerefAuthor');
        if (!showOperator) {
            if (opField) opField.style.display = 'none';
        } else {
            fillSelect(opSel, PC_OPERATORS, { placeholder: '— none —' });
            opSel.value = block.fields.operator || '';
            if (editable.operator) {
                opSel.addEventListener('change', function () { block.fields.operator = opSel.value; });
            } else {
                opSel.disabled = true; opSel.classList.add('pc-locked');
            }
        }

        var removeBtn = node.querySelector('[data-action="remove"]');
        if (opts.removable === false) removeBtn.style.display = 'none';
        else removeBtn.addEventListener('click', function () {
            if (opts.removeHandler) opts.removeHandler();
        });

        host.appendChild(node);

        var hintEl = node.querySelector('[data-role="hint"]');
        if (hintEl) {
            if (opts.role === 'root') {
                hintEl.textContent = 'Foundation root — sets ALL terms. Uses the tree\'s global timeframe.';
            } else if (opts.role === 'heirRoot') {
                hintEl.textContent = 'Heir root — inherits everything from its authority. Only price level & operator are configurable.';
            } else if (opts.role === 'ref') {
                hintEl.textContent = 'Sets its OWN name, type, position & search. Uses the tree\'s global timeframe.';
            } else if (opts.role === 'rerefAuthor') {
                hintEl.textContent = 'Inherits from the ref (name, type, position, search). Sets its OWN price level & operator.';
            } else if (opts.role === 'rerefServant') {
                hintEl.textContent = 'Inherits from the root (name, type, position). Only price level is configurable.';
            }
        }
    }

    // ============================================================
    // DRAWING BLOCK
    // ============================================================
    function buildDrawingBlock(host, drawing, tree, rootLocalId) {
        var tpl = $('pcDrawingBlockTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-local-id', drawing.localId);

        var toolSel = node.querySelector('[data-field="drawing_tools"]');
        var fromSel = node.querySelector('[data-field="draw_from"]');
        var fromLvl = node.querySelector('[data-field="draw_from_price_level"]');
        var toSel   = node.querySelector('[data-field="draw_to"]');
        var toField = node.querySelector('[data-role="toRefLevelField"]');
        var toLvl   = node.querySelector('[data-field="draw_to_price_level"]');
        var colSel  = node.querySelector('[data-field="drawing_color"]');

        fillSelect(toolSel, PC_DRAWING_TOOLS, { placeholder: '— select —' });
        fillSelect(fromSel, buildSourceOptions(tree, rootLocalId), { placeholder: '— from —' });
        fillSelect(fromLvl, PC_PRICE_LEVELS, { placeholder: '— price —' });
        fillSelect(toSel,   buildDrawToOptions(tree, rootLocalId, drawing.fields.draw_from || ''), { placeholder: '— to —' });
        fillSelect(toLvl,   PC_PRICE_LEVELS, { placeholder: '— price —' });
        fillSelect(colSel,  PC_DRAWING_COLORS, { placeholder: '— default —' });

        toolSel.value = drawing.fields.drawing_tools || '';
        fromSel.value = drawing.fields.draw_from || '';
        fromLvl.value = drawing.fields.draw_from_price_level || '';
        toSel.value   = drawing.fields.draw_to || '';
        toLvl.value   = drawing.fields.draw_to_price_level || '';
        colSel.value  = drawing.fields.drawing_color || '';

        function syncToLevel() {
            var isSpec = /_specific_price_level$/.test(toSel.value || '');
            toField.style.display = isSpec ? '' : 'none';
        }
        syncToLevel();

        toolSel.addEventListener('change', function () { drawing.fields.drawing_tools = toolSel.value; });
        fromSel.addEventListener('change', function () {
            drawing.fields.draw_from = fromSel.value;
            var curTo = toSel.value;
            var newToOpts = buildDrawToOptions(tree, rootLocalId, fromSel.value);
            var curValid = newToOpts.some(function (o) { return o.value === curTo; });
            fillSelect(toSel, newToOpts, { placeholder: '— to —' });
            if (curValid) toSel.value = curTo;
            else {
                toSel.value = '';
                drawing.fields.draw_to = '';
                drawing.fields.draw_to_price_level = '';
                toLvl.value = '';
            }
            syncToLevel();
        });
        fromLvl.addEventListener('change', function () { drawing.fields.draw_from_price_level = fromLvl.value; });
        toSel.addEventListener('change', function () {
            drawing.fields.draw_to = toSel.value;
            syncToLevel();
            if (!/_specific_price_level$/.test(toSel.value || '')) {
                drawing.fields.draw_to_price_level = '';
                toLvl.value = '';
            }
        });
        toLvl.addEventListener('change', function () { drawing.fields.draw_to_price_level = toLvl.value; });
        colSel.addEventListener('change', function () { drawing.fields.drawing_color = colSel.value; });

        node.querySelector('[data-action="remove"]').addEventListener('click', function () {
            tree.drawings = tree.drawings.filter(function (x) { return x.localId !== drawing.localId; });
            renderTrees();
        });

        host.appendChild(node);
    }

    // ============================================================
    // TRADE BLOCK
    // ============================================================
    function buildTradeBlock(host, trade, tree, rootLocalId) {
        var tpl = $('pcTradeBlockTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-local-id', trade.localId);

        var orderSel     = node.querySelector('[data-field="order_type"]');
        var entryFrom    = node.querySelector('[data-field="entry_from"]');
        var entryLvl     = node.querySelector('[data-field="entry_from_price_level"]');
        var exitAt       = node.querySelector('[data-field="exit_at"]');
        var exitLvl      = node.querySelector('[data-field="exit_at_price_level"]');
        var targetType   = node.querySelector('[data-field="target_type"]');
        var rrMode       = node.querySelector('[data-field="target_rr_mode"]');
        var rrField      = node.querySelector('[data-role="rrModeField"]');
        var rrValField   = node.querySelector('[data-role="rrValueField"]');
        var rrValInput   = node.querySelector('[data-field="rr_value"]');
        var targetSel    = node.querySelector('[data-field="target"]');
        var targetLvl    = node.querySelector('[data-field="target_price_level"]');
        var targetField  = node.querySelector('[data-role="targetField"]');
        var targetLvlFld = node.querySelector('[data-role="targetLevelField"]');
        var hintEl       = node.querySelector('[data-role="hint"]');

        var srcOpts = buildSourceOptions(tree, rootLocalId);

        fillSelect(orderSel,  PC_ORDER_TYPES,   { placeholder: '— order —' });
        fillSelect(entryFrom, srcOpts,          { placeholder: '— source —' });
        fillSelect(entryLvl,  PC_PRICE_LEVELS,  { placeholder: '— price —' });
        fillSelect(exitAt,    srcOpts,          { placeholder: '— source —' });
        fillSelect(exitLvl,   PC_PRICE_LEVELS,  { placeholder: '— price —' });
        fillSelect(targetType, PC_TARGET_TYPES, { placeholder: '— target type —' });
        fillSelect(rrMode,     PC_RR_MODES,     { placeholder: '— mode —' });
        fillSelect(targetSel,  srcOpts,         { placeholder: '— source —' });
        fillSelect(targetLvl,  PC_PRICE_LEVELS, { placeholder: '— price —' });

        orderSel.value   = trade.fields.order_type || '';
        entryFrom.value  = trade.fields.entry_from || '';
        entryLvl.value   = trade.fields.entry_from_price_level || '';
        exitAt.value     = trade.fields.exit_at || '';
        exitLvl.value    = trade.fields.exit_at_price_level || '';
        targetType.value = trade.fields.target_type || 'set_target';
        rrMode.value     = trade.fields.target_rr_mode || 'fixed_risk_reward';
        targetSel.value  = trade.fields.target || '';
        targetLvl.value  = trade.fields.target_price_level || '';

        function defaultRRValueFor(mode) {
            if (mode === 'minimum_risk_reward') {
                return (PC_ACCOUNT_MGMT.minimum_risk_reward || '0.00');
            }
            return (PC_ACCOUNT_MGMT.fixed_risk_reward || '0.00');
        }

        if (!trade.fields.rr_value) {
            trade.fields.rr_value = defaultRRValueFor(rrMode.value);
        }
        rrValInput.value = trade.fields.rr_value;

        function syncTargetVisibility() {
            var isRR = (targetType.value === 'risk_reward');
            if (isRR) {
                rrField.style.display = '';
                rrValField.style.display = '';
                targetField.style.display = 'none';
                targetLvlFld.style.display = 'none';
                targetSel.disabled = true;
                targetLvl.disabled = true;
                trade.fields.target = rrMode.value || 'fixed_risk_reward';
                trade.fields.target_price_level = '';
                targetSel.value = '';
                targetLvl.value = '';
                if (hintEl) {
                    hintEl.textContent = 'Edit the value — it will be saved to your Account Management settings when you save the configuration.';
                    hintEl.classList.remove('pc-warn');
                }
            } else {
                rrField.style.display = 'none';
                rrValField.style.display = 'none';
                targetField.style.display = '';
                targetLvlFld.style.display = '';
                targetSel.disabled = false;
                targetLvl.disabled = false;
                if (trade.fields.target === 'fixed_risk_reward' || trade.fields.target === 'minimum_risk_reward') {
                    trade.fields.target = '';
                }
                if (hintEl) {
                    hintEl.textContent = 'Target uses a candle source and price level.';
                    hintEl.classList.remove('pc-warn');
                }
            }
        }
        syncTargetVisibility();

        orderSel.addEventListener('change', function () { trade.fields.order_type = orderSel.value; });
        entryFrom.addEventListener('change', function () { trade.fields.entry_from = entryFrom.value; });
        entryLvl .addEventListener('change', function () { trade.fields.entry_from_price_level = entryLvl.value; });
        exitAt   .addEventListener('change', function () { trade.fields.exit_at = exitAt.value; });
        exitLvl  .addEventListener('change', function () { trade.fields.exit_at_price_level = exitLvl.value; });

        targetType.addEventListener('change', function () {
            trade.fields.target_type = targetType.value;
            syncTargetVisibility();
        });

        rrMode.addEventListener('change', function () {
            trade.fields.target_rr_mode = rrMode.value;
            if (targetType.value === 'risk_reward') {
                trade.fields.target = rrMode.value;
                var prev = defaultRRValueFor(trade.fields.target_rr_mode === rrMode.value
                    ? (rrMode.value === 'minimum_risk_reward' ? 'fixed_risk_reward' : 'minimum_risk_reward')
                    : rrMode.value);
                if (!trade.fields.rr_value || trade.fields.rr_value === prev) {
                    trade.fields.rr_value = defaultRRValueFor(rrMode.value);
                    rrValInput.value = trade.fields.rr_value;
                }
            }
        });

        rrValInput.addEventListener('input', function () {
            trade.fields.rr_value = rrValInput.value;
        });

        targetSel.addEventListener('change', function () { trade.fields.target = targetSel.value; });
        targetLvl.addEventListener('change', function () { trade.fields.target_price_level = targetLvl.value; });

        node.querySelector('[data-action="remove"]').addEventListener('click', function () {
            tree.trades = tree.trades.filter(function (x) { return x.localId !== trade.localId; });
            renderTrees();
        });

        host.appendChild(node);
    }

    // ============================================================
    // RENDER
    // ============================================================
    function renderTrees() {
        var host = $('pcTreeList');
        if (!host) return;
        host.innerHTML = '';

        if (!PC.trees.length) {
            var ph = document.createElement('div');
            ph.className = 'pc-placeholder';
            ph.innerHTML = ''
                + '<i class="fa-solid fa-sitemap pc-placeholder-icon"></i>'
                + '<p class="pc-placeholder-title">No trees yet</p>'
                + '<p class="pc-placeholder-text">Click <strong>Add Tree</strong> to start.</p>';
            host.appendChild(ph);
            return;
        }

        PC.trees.forEach(function (tree) {
            var tpl = $('pcTreeTpl');
            var group = tpl.content.firstElementChild.cloneNode(true);
            group.setAttribute('data-tree-local-id', tree.localId);
            group.setAttribute('data-collapsed', tree.collapsed ? '1' : '0');

            var titleEl = group.querySelector('[data-role="treeTitle"]');
            if (titleEl) {
                var firstRoot = tree.roots[0];
                titleEl.textContent = (firstRoot && firstRoot.fields.candle_name && firstRoot.fields.candle_name.trim())
                    ? firstRoot.fields.candle_name : 'Tree';
            }
            var sub = group.querySelector('[data-role="treeSub"]');
            if (sub) {
                var bits = [];
                if (tree.timeframe) bits.push(tree.timeframe);
                bits.push(tree.roots.length + ' root' + (tree.roots.length > 1 ? 's' : ''));
                if (tree.drawings.length) bits.push(tree.drawings.length + ' draw');
                if (tree.trades.length)   bits.push(tree.trades.length + ' trade');
                sub.textContent = bits.length ? '· ' + bits.join(' · ') : '';
            }

            group.querySelector('[data-action="toggle"]').addEventListener('click', function () {
                tree.collapsed = !tree.collapsed;
                group.setAttribute('data-collapsed', tree.collapsed ? '1' : '0');
            });

            group.querySelector('[data-action="save-tree"]').addEventListener('click', function () {
                pcSaveTree(tree, this);
            });

            // ── GLOBAL TIMEFRAME SELECT ──
            var treeTfSel = group.querySelector('[data-field="tree_timeframe"]');
            if (treeTfSel) {
                fillSelect(treeTfSel, PC_TIMEFRAMES, { placeholder: '— select timeframe —' });
                if (tree.timeframe) treeTfSel.value = tree.timeframe;
                treeTfSel.addEventListener('change', function () {
                    var newTf = treeTfSel.value;
                    tree.timeframe = newTf;
                    applyTreeTimeframe(tree, newTf);
                    renderTrees();
                });
            }
            // ── GLOBAL ANCHOR CANDLE (across all trees) ──
            var treeAnchorRootSel    = group.querySelector('[data-field="tree_anchor_root"]');
            var treeAnchorIndexField = group.querySelector('[data-role="treeAnchorIndexField"]');
            var treeAnchorIndexSel   = group.querySelector('[data-field="tree_anchor_index"]');
            var treeAnchorNameLabel  = group.querySelector('[data-role="treeAnchorNameLabel"]');

            if (treeAnchorRootSel) {
                // ---- Derive anchorLocalKey from the persisted DB id, every render.
                //      This makes the anchor immune to uid() churn and render order.
                if (tree.anchorDbId) {
                    var resolvedKey = null;
                    PC.trees.forEach(function (otherTree) {
                        (otherTree.roots || []).forEach(function (otherRoot) {
                            if (otherRoot.dbId === tree.anchorDbId) {
                                var nm = (otherRoot.fields.candle_name || '').trim();
                                if (nm) {
                                    resolvedKey = nm + '::root::' + otherTree.localId + '::' + otherRoot.localId;
                                }
                            }
                            (otherRoot.refs || []).forEach(function (otherRef) {
                                if (otherRef.dbId === tree.anchorDbId) {
                                    var refNm = (otherRef.fields.candle_name || '').trim();
                                    if (refNm) {
                                        resolvedKey = refNm + '::ref::' + otherTree.localId + '::' + otherRef.localId;
                                    }
                                }
                            });
                        });
                    });
                    if (resolvedKey) {
                        tree.anchorLocalKey = resolvedKey;
                    }
                }

                var anchorOpts = buildGlobalAnchorOptions();

                if (anchorOpts.length === 0) {
                    fillSelect(treeAnchorRootSel, [
                        { value: '', label: 'No named candles to anchor to' }
                    ]);
                    treeAnchorRootSel.disabled = true;
                    if (treeAnchorIndexField) treeAnchorIndexField.style.display = 'none';
                } else {
                    treeAnchorRootSel.disabled = false;
                    fillSelect(treeAnchorRootSel, anchorOpts, { placeholder: '— none —' });

                    // ---- Restore the model value WITHOUT ever nulling it.
                    //      If the saved key is not among the current options
                    //      (target renamed / tree deleted), inject a stale
                    //      option so the user sees it and the value survives.
                    if (tree.anchorLocalKey) {
                        var exists = anchorOpts.some(function (o) { return o.value === tree.anchorLocalKey; });
                        if (!exists) {
                            var staleName = String(tree.anchorLocalKey).split('::')[0] || 'saved anchor';
                            var staleOpt  = document.createElement('option');
                            staleOpt.value = tree.anchorLocalKey;
                            staleOpt.textContent = staleName + ' (saved — target not found)';
                            treeAnchorRootSel.appendChild(staleOpt);
                        }
                        treeAnchorRootSel.value = tree.anchorLocalKey;
                    } else {
                        treeAnchorRootSel.value = '';
                    }

                    function syncTreeAnchorIndexField() {
                        var chosen = treeAnchorRootSel.value;

                        // Resolve the chosen key back to a numeric db id so
                        // the model always carries a durable anchorDbId.
                        var newAnchorDbId = null;
                        if (chosen) {
                            var parts = String(chosen).split('::');
                            if (parts.length >= 4) {
                                var chosenKind = parts[1];
                                var chosenTlid = parts[2];
                                var chosenEid  = parts[3];
                                PC.trees.forEach(function (otherTree) {
                                    if (otherTree.localId !== chosenTlid) return;
                                    if (chosenKind === 'root') {
                                        (otherTree.roots || []).forEach(function (r) {
                                            if (r.localId === chosenEid) newAnchorDbId = r.dbId;
                                        });
                                    } else if (chosenKind === 'ref') {
                                        (otherTree.roots || []).forEach(function (r) {
                                            (r.refs || []).forEach(function (rf) {
                                                if (rf.localId === chosenEid) newAnchorDbId = rf.dbId;
                                            });
                                        });
                                    }
                                });
                            }
                        }

                        if (!chosen) {
                            if (treeAnchorIndexField) treeAnchorIndexField.style.display = 'none';
                            tree.anchorLocalKey = null;
                            tree.anchorDbId     = null;
                            tree.anchorIndex    = '';
                            if (treeAnchorIndexSel) treeAnchorIndexSel.value = '';
                            return;
                        }

                        tree.anchorLocalKey = chosen;
                        tree.anchorDbId     = newAnchorDbId;

                        // Derive a display name from the option label
                        var chosenOpt = anchorOpts.filter(function (o) { return o.value === chosen; })[0];
                        var chosenName = chosenOpt ? chosenOpt.label : 'candle';
                        if (treeAnchorNameLabel) treeAnchorNameLabel.textContent = chosenName;

                        if (treeAnchorIndexSel) {
                            fillSelect(treeAnchorIndexSel, PC_ANCHOR_INDEXES, { placeholder: '— select index —' });
                            if (tree.anchorIndex && PC_ANCHOR_INDEXES.indexOf(tree.anchorIndex) !== -1) {
                                treeAnchorIndexSel.value = tree.anchorIndex;
                            }
                        }
                        if (treeAnchorIndexField) treeAnchorIndexField.style.display = '';
                    }

                    treeAnchorRootSel.addEventListener('change', syncTreeAnchorIndexField);
                    if (treeAnchorIndexSel) {
                        treeAnchorIndexSel.addEventListener('change', function () {
                            tree.anchorIndex = treeAnchorIndexSel.value;
                        });
                    }

                    syncTreeAnchorIndexField();
                }
            }

            var rootList = group.querySelector('[data-role="rootList"]');
            tree.roots.forEach(function (root, rIdx) {
                renderRootWrap(rootList, tree, root, rIdx, group);
            });

            group.querySelector('[data-action="add-root"]').addEventListener('click', function () {
                addHeirRoot(tree.localId);
            });

            var drawingList = group.querySelector('[data-role="drawingList"]');
            tree.drawings.forEach(function (dr) {
                buildDrawingBlock(drawingList, dr, tree, tree.roots[0].localId);
            });

            group.querySelector('[data-action="add-drawing"]').addEventListener('click', function () {
                addDrawing(tree.localId);
            });

            var tradeList = group.querySelector('[data-role="tradeList"]');
            tree.trades.forEach(function (tr) {
                buildTradeBlock(tradeList, tr, tree, tree.roots[0].localId);
            });

            group.querySelector('[data-action="add-trade"]').addEventListener('click', function () {
                addTrade(tree.localId);
            });

            group.querySelector('[data-action="remove-tree"]').addEventListener('click', function () {
                removeTree(tree.localId);
            });

            host.appendChild(group);
        });
    }

    function applyTreeTimeframe(tree, tf) {
        (tree.roots || []).forEach(function (root) {
            root.fields.timeframe = tf;
            (root.refs || []).forEach(function (ref) {
                ref.fields.timeframe = tf;
                (ref.reRefPairs || []).forEach(function (pair) {
                    if (pair.author)  pair.author.fields.timeframe  = tf;
                    if (pair.servant) pair.servant.fields.timeframe = tf;
                });
            });
        });
    }

    function renderRootWrap(rootList, tree, root, rIdx, groupEl) {
        var tpl = $('pcRootWrapTpl');
        var wrap = tpl.content.firstElementChild.cloneNode(true);
        wrap.setAttribute('data-root-local-id', root.localId);
        if (rIdx > 0) wrap.classList.add('pc-heir-root');

        var titleEl = wrap.querySelector('[data-role="rootTitle"]');
        if (titleEl) titleEl.textContent = 'Root #' + (rIdx + 1) + (rIdx === 0 ? ' (Foundation)' : ' (Heir)');

        var authorityEl = wrap.querySelector('[data-role="rootAuthority"]');
        if (authorityEl && rIdx > 0 && root.authorityLocalKey) {
            var found = null;
            tree.roots.forEach(function (r, ri) {
                if (r.localId === root.authorityLocalKey) found = { name: r.fields.candle_name, type: 'Root #' + (ri+1) };
                (r.refs || []).forEach(function (ref, refIdx) {
                    if (ref.localId === root.authorityLocalKey) found = { name: ref.fields.candle_name, type: 'Root#' + (ri+1) + ' Ref#' + (refIdx+1) };
                });
            });
            if (found) {
                authorityEl.hidden = false;
                authorityEl.textContent = 'inherits from ' + (found.name || found.type);
            }
        }

        var rootCandleHost = wrap.querySelector('[data-role="rootCandleHost"]');
        buildCandleCard(rootCandleHost, root, {
            role: rIdx === 0 ? 'root' : 'heirRoot',
            removable: true,
            removeHandler: function () { removeRoot(tree.localId, root.localId); },
            onNameChange: function (newVal) {
                // NO renderTrees() here — that would destroy the input being typed in.
                // Instead, update the tree header + GLOBAL anchor dropdowns in place.
                refreshTreeHeaderInPlace(tree);
                refreshAllAnchorDropdownsInPlace();
                updateServantsFromRoot(tree, root, 'candle_name', newVal);
                updateServantDOMs(tree, root, 'candle_name', newVal);
                refreshDynamicSelects(tree, groupEl);
            },
            onTypeChange: function (newVal) {
                updateServantsFromRoot(tree, root, 'candle_type', newVal);
                updateServantDOMs(tree, root, 'candle_type', newVal);
            },
            onPositionChange: function (newVal) {
                updateServantsFromRoot(tree, root, 'candle_position', newVal || '0');
                updateServantDOMs(tree, root, 'candle_position', newVal || '0');
            }
        });

        // Authority selector for heir roots
        var authSelectWrap = wrap.querySelector('[data-role="authoritySelect"]');
        var authSelect = wrap.querySelector('[data-field="authority_source"]');
        if (rIdx > 0 && authSelectWrap && authSelect) {
            authSelectWrap.hidden = false;
            var authOpts = [];
            tree.roots.forEach(function (r, ri) {
                if (r.localId === root.localId) return;
                authOpts.push({ value: r.localId, label: 'Root #' + (ri+1) + ': ' + (r.fields.candle_name || 'unnamed') });
                (r.refs || []).forEach(function (ref, refIdx) {
                    authOpts.push({
                        value: ref.localId,
                        label: 'Root #' + (ri+1) + ' Ref #' + (refIdx+1) + ': ' + (ref.fields.candle_name || 'unnamed')
                    });
                });
            });
            fillSelect(authSelect, authOpts, { placeholder: '— select authority —' });
            if (root.authorityLocalKey) authSelect.value = root.authorityLocalKey;

            authSelect.addEventListener('change', function () {
                var chosenKey = authSelect.value;
                root.authorityLocalKey = chosenKey;
                var src = null;
                tree.roots.forEach(function (r) {
                    if (r.localId === chosenKey) src = r;
                    (r.refs || []).forEach(function (ref) {
                        if (ref.localId === chosenKey) src = ref;
                    });
                });
                if (src) {
                    root.fields.candle_name     = src.fields.candle_name;
                    root.fields.candle_type     = src.fields.candle_type;
                    root.fields.candle_position = src.fields.candle_position;
                    root.fields.candle_search   = src.fields.candle_search || '';
                }
                // Select change → full re-render is fine here (no text focus to lose).
                renderTrees();
            });
        }

        var refList = wrap.querySelector('[data-role="refList"]');
        var addRefBtn = wrap.querySelector('[data-action="add-ref"]');
        root.refs.forEach(function (ref) {
            renderRefWrap(refList, tree, root, ref, groupEl);
        });
        if (root.refs && root.refs.length >= 1) {
            addRefBtn.style.display = 'none';
        } else {
            addRefBtn.style.display = '';
            addRefBtn.addEventListener('click', function () {
                addRef(tree.localId, root.localId);
            });
        }

        rootList.appendChild(wrap);
    }

    // ============================================================
    // IN-PLACE REFRESHERS (avoid nuking focus while typing)
    // ============================================================
    function refreshTreeHeaderInPlace(tree) {
        var group = document.querySelector('.pc-tree[data-tree-local-id="' + tree.localId + '"]');
        if (!group) return;

        var titleEl = group.querySelector('[data-role="treeTitle"]');
        if (titleEl) {
            var firstRoot = tree.roots[0];
            titleEl.textContent = (firstRoot && firstRoot.fields.candle_name && firstRoot.fields.candle_name.trim())
                ? firstRoot.fields.candle_name : 'Tree';
        }
        var sub = group.querySelector('[data-role="treeSub"]');
        if (sub) {
            var bits = [];
            if (tree.timeframe) bits.push(tree.timeframe);
            bits.push(tree.roots.length + ' root' + (tree.roots.length > 1 ? 's' : ''));
            if (tree.drawings.length) bits.push(tree.drawings.length + ' draw');
            if (tree.trades.length)   bits.push(tree.trades.length + ' trade');
            sub.textContent = bits.length ? '· ' + bits.join(' · ') : '';
        }
    }

    // Refresh EVERY tree's global anchor dropdown (since anchors span all trees)
    function refreshAllAnchorDropdownsInPlace() {
        var anchorOpts = buildGlobalAnchorOptions();
        var validValues = anchorOpts.map(function (o) { return o.value; });

        PC.trees.forEach(function (tree) {
            var group = document.querySelector('.pc-tree[data-tree-local-id="' + tree.localId + '"]');
            if (!group) return;

            var treeAnchorRootSel    = group.querySelector('[data-field="tree_anchor_root"]');
            var treeAnchorIndexField = group.querySelector('[data-role="treeAnchorIndexField"]');
            var treeAnchorIndexSel   = group.querySelector('[data-field="tree_anchor_index"]');
            var treeAnchorNameLabel  = group.querySelector('[data-role="treeAnchorNameLabel"]');

            if (!treeAnchorRootSel) return;

            var curVal = treeAnchorRootSel.value;

            if (anchorOpts.length === 0) {
                fillSelect(treeAnchorRootSel, [
                    { value: '', label: 'No named candles to anchor to' }
                ]);
                treeAnchorRootSel.disabled = true;
                if (treeAnchorIndexField) treeAnchorIndexField.style.display = 'none';
                return;
            }

            treeAnchorRootSel.disabled = false;
            treeAnchorRootSel.innerHTML = '';
            var ph = document.createElement('option');
            ph.value = ''; ph.textContent = '— none —';
            treeAnchorRootSel.appendChild(ph);
            anchorOpts.forEach(function (o) {
                var opt = document.createElement('option');
                opt.value = o.value; opt.textContent = o.label;
                treeAnchorRootSel.appendChild(opt);
            });

            // Restore prior value if it still exists; NEVER null the model
            // from a refresh — the model is the source of truth.
            var desiredKey = curVal || tree.anchorLocalKey || '';
            if (desiredKey && validValues.indexOf(desiredKey) === -1) {
                // Inject a stale option so the user still sees what's stored.
                var staleName2 = String(desiredKey).split('::')[0] || 'saved anchor';
                var staleOpt2  = document.createElement('option');
                staleOpt2.value = desiredKey;
                staleOpt2.textContent = staleName2 + ' (saved — target not found)';
                treeAnchorRootSel.appendChild(staleOpt2);
            }
            treeAnchorRootSel.value = desiredKey || '';

            // Refresh the index-time label/field for whatever is selected now
            var chosen = treeAnchorRootSel.value;
            if (chosen) {
                var chosenOpt = anchorOpts.filter(function (o) { return o.value === chosen; })[0];
                var chosenName = chosenOpt ? chosenOpt.label : 'candle';
                if (treeAnchorNameLabel) treeAnchorNameLabel.textContent = chosenName;
                if (treeAnchorIndexField) treeAnchorIndexField.style.display = '';
                if (treeAnchorIndexSel) {
                    fillSelect(treeAnchorIndexSel, PC_ANCHOR_INDEXES, { placeholder: '— select index —' });
                    if (tree.anchorIndex && PC_ANCHOR_INDEXES.indexOf(tree.anchorIndex) !== -1) {
                        treeAnchorIndexSel.value = tree.anchorIndex;
                    }
                }
            } else {
                if (treeAnchorIndexField) treeAnchorIndexField.style.display = 'none';
            }
        });
    }

    function refreshDynamicSelects(tree, groupEl) {
        if (!groupEl) return;
        tree.drawings.forEach(function (dr) {
            var blockEl = groupEl.querySelector('.pc-drawing-block[data-local-id="' + dr.localId + '"]');
            if (!blockEl) return;
            var fromSel = blockEl.querySelector('[data-field="draw_from"]');
            var toSel   = blockEl.querySelector('[data-field="draw_to"]');
            var curFrom = fromSel.value, curTo = toSel.value;
            var rootId = tree.roots[0].localId;
            fillSelect(fromSel, buildSourceOptions(tree, rootId), { placeholder: '— from —' }); fromSel.value = curFrom;
            var drawToOpts = buildDrawToOptions(tree, rootId, curFrom);
            var curToValid = drawToOpts.some(function (o) { return o.value === curTo; });
            fillSelect(toSel, drawToOpts, { placeholder: '— to —' });
            if (curToValid) toSel.value = curTo;
            else {
                toSel.value = '';
                dr.fields.draw_to = '';
                dr.fields.draw_to_price_level = '';
                var toLvlEl = blockEl.querySelector('[data-field="draw_to_price_level"]');
                if (toLvlEl) toLvlEl.value = '';
            }
        });

        tree.trades.forEach(function (tr) {
            var blockEl = groupEl.querySelector('.pc-trade-block[data-local-id="' + tr.localId + '"]');
            if (!blockEl) return;
            var rootId = tree.roots[0].localId;
            var srcOpts = buildSourceOptions(tree, rootId);
            [['entry_from'],['exit_at'],['target']].forEach(function (pair) {
                var sel = blockEl.querySelector('[data-field="' + pair[0] + '"]');
                if (!sel) return;
                var cur = sel.value;
                fillSelect(sel, srcOpts, { placeholder: '— source —' });
                sel.value = cur;
            });
        });
    }

    function renderRefWrap(refList, tree, root, ref, groupEl) {
        var tpl = $('pcRefWrapTpl');
        var wrap = tpl.content.firstElementChild.cloneNode(true);
        wrap.setAttribute('data-ref-local-id', ref.localId);

        var cardHost = wrap.querySelector('[data-ref-role="ref"]');
        buildCandleCard(cardHost, ref, {
            role: 'ref',
            removable: true,
            removeHandler: function () { removeRef(tree.localId, root.localId, ref.localId); },
            onNameChange: function (newVal) {
                updateAuthorsFromRef(tree, ref, 'candle_name', newVal);
                updateAuthorDOMs(tree, ref, 'candle_name', newVal);
                refreshAllAnchorDropdownsInPlace();
                refreshDynamicSelects(tree, groupEl);
            },
            onTypeChange: function (newVal) {
                updateAuthorsFromRef(tree, ref, 'candle_type', newVal);
                updateAuthorDOMs(tree, ref, 'candle_type', newVal);
            },
            onPositionChange: function (newVal) {
                updateAuthorsFromRef(tree, ref, 'candle_position', newVal || '0');
                updateAuthorDOMs(tree, ref, 'candle_position', newVal || '0');
            },
            onSearchChange: function (newVal) {
                updateAuthorsFromRef(tree, ref, 'candle_search', newVal);
                updateAuthorDOMs(tree, ref, 'candle_search', newVal);
            }
        });

        var stack = wrap.querySelector('[data-role="rerefStack"]');
        (ref.reRefPairs || []).forEach(function (pair) { renderRerefPair(stack, tree, root, ref, pair); });

        wrap.querySelector('[data-action="re-ref"]').addEventListener('click', function () {
            addReRef(tree.localId, root.localId, ref.localId);
        });

        refList.appendChild(wrap);
    }

    function renderRerefPair(stack, tree, root, ref, pair) {
        var tpl = $('pcRerefPairTpl');
        var node = tpl.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-reref-local-id', pair.localId);

        node.querySelector('[data-action="remove-reref"]').addEventListener('click', function () {
            ref.reRefPairs = (ref.reRefPairs || []).filter(function (p) { return p.localId !== pair.localId; });
            renderTrees();
        });

        var authorHost = node.querySelector('[data-reref-role="author"]');
        buildCandleCard(authorHost, pair.author, { role: 'rerefAuthor', removable: false });

        var servantHost = node.querySelector('[data-reref-role="referenced"]');
        buildCandleCard(servantHost, pair.servant, { role: 'rerefServant', removable: false });

        stack.appendChild(node);
    }

    // ============================================================
    // MUTATORS
    // ============================================================
    function blankFields(tf) {
        return {
            candle_name: '',
            price_level: '',
            timeframe: tf || PC_CURRENT_TF || '',
            candle_type: '',
            candle_position: '0',
            candle_search: '',
            operator: ''
        };
    }

    function addTree() {
        var tree = {
            localId: uid(),
            dbTreeId: null,
            collapsed: false,
            timeframe: PC_CURRENT_TF || (PC_TIMEFRAMES[0] || ''),
            anchorLocalKey: null,
            anchorIndex: '',
            roots: [],
            drawings: [],
            trades: []
        };
        tree.roots.push({
            localId: uid(), dbId: null, rootOrder: 1, isFoundation: true,
            authorityLocalKey: null,
            fields: blankFields(tree.timeframe),
            refs: []
        });
        PC.trees.push(tree); renderTrees(); return tree;
    }

    function addHeirRoot(treeLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        tree.roots.push({
            localId: uid(), dbId: null, rootOrder: tree.roots.length + 1, isFoundation: false,
            authorityLocalKey: null,
            fields: blankFields(tree.timeframe),
            refs: []
        });
        tree.collapsed = false;
        renderTrees();
    }

    function addRef(treeLocalId, rootLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        var root = tree.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        if (root.refs.length >= 1) return;
        var ref = { localId: uid(), dbId: null,
            fields: {
                candle_name: '', candle_type: '',
                timeframe: tree.timeframe || PC_CURRENT_TF || '',
                price_level: '', candle_position: '0',
                candle_search: '', operator: ''
            },
            reRefPairs: [] };
        root.refs.push(ref); tree.collapsed = false; renderTrees(); return ref;
    }

    function addReRef(treeLocalId, rootLocalId, refLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        var root = tree.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        var ref = root.refs.filter(function (r) { return r.localId === refLocalId; })[0];
        if (!ref) return;

        var pair = {
            localId: uid(),
            author: { localId: uid(), dbId: null,
                fields: {
                    candle_name: ref.fields.candle_name,
                    candle_type: ref.fields.candle_type,
                    timeframe: tree.timeframe || '',
                    candle_position: ref.fields.candle_position,
                    candle_search: ref.fields.candle_search,
                    price_level: '', operator: ''
                } },
            servant: { localId: uid(), dbId: null,
                fields: {
                    candle_name: root.fields.candle_name,
                    candle_type: root.fields.candle_type,
                    timeframe: tree.timeframe || '',
                    candle_position: root.fields.candle_position,
                    candle_search: '', price_level: ''
                } }
        };
        if (!ref.reRefPairs) ref.reRefPairs = [];
        ref.reRefPairs.push(pair);
        tree.collapsed = false;
        renderTrees();
        return pair;
    }

    function addDrawing(treeLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        tree.drawings.push({ localId: uid(), dbId: null,
            fields: {
                drawing_id: nextDrawingId(),
                drawing_tools: 'trendline',
                draw_from: '', draw_from_price_level: '',
                draw_to: '', draw_to_price_level: '',
                drawing_color: ''
            } });
        tree.collapsed = false;
        renderTrees();
    }

    function addTrade(treeLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        tree.trades.push({ localId: uid(), dbId: null,
            fields: {
                order_type: '',
                entry_from: '',
                entry_from_price_level: '',
                exit_at: '',
                exit_at_price_level: '',
                target_type: 'set_target',
                target_rr_mode: 'fixed_risk_reward',
                target: '',
                target_price_level: ''
            }
        });
        tree.collapsed = false;
        renderTrees();
    }

    function removeTree(treeLocalId) { PC.trees = PC.trees.filter(function (t) { return t.localId !== treeLocalId; }); renderTrees(); }

    function removeRoot(treeLocalId, rootLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        if (tree.roots.length <= 1) return;
        tree.roots = tree.roots.filter(function (r) { return r.localId !== rootLocalId; });
        tree.roots.forEach(function (r, i) { r.rootOrder = i + 1; r.isFoundation = (i === 0); });
        // Clear tree-level anchor if it pointed at the removed root
        if (tree.anchorLocalKey && tree.anchorLocalKey.indexOf('::' + tree.localId + '::' + rootLocalId) !== -1) {
            tree.anchorLocalKey = null;
            tree.anchorIndex = '';
        }
        renderTrees();
    }

    function removeRef(treeLocalId, rootLocalId, refLocalId) {
        var tree = PC.trees.filter(function (t) { return t.localId === treeLocalId; })[0];
        if (!tree) return;
        var root = tree.roots.filter(function (r) { return r.localId === rootLocalId; })[0];
        if (!root) return;
        root.refs = root.refs.filter(function (r) { return r.localId !== refLocalId; });
        renderTrees();
    }

    // ============================================================
    // SNAPSHOT
    // ============================================================
    function pcBuildTreeSnapshot(tree) {
        // Send BOTH the global anchor key AND the anchor candle name so the
        // server can resolve the anchor even when it lives in a different tree.
        // Do NOT null out cross-tree anchors here — that was the original bug.
        var anchorGlobalKey  = tree.anchorLocalKey || null;
        var anchorCandleName = null;

        if (anchorGlobalKey) {
            // Global anchor value format:
            //   "candleName::root::treeLocalId::rootLocalId"
            //   "candleName::ref::treeLocalId::refLocalId"
            var parts = String(anchorGlobalKey).split('::');
            if (parts.length >= 4 && parts[0]) {
                anchorCandleName = parts[0];
            } else {
                // Legacy fallback: anchor stored as a plain local key that
                // belongs to THIS tree. Resolve it locally so the server still
                // receives something usable.
                var resolved = resolveAnchorForTree(tree, anchorGlobalKey);
                if (resolved) {
                    if (resolved.type === 'root') {
                        var r = (tree.roots || []).filter(function (x) {
                            return x.localId === resolved.localId;
                        })[0];
                        if (r) anchorCandleName = (r.fields.candle_name || '').trim() || null;
                    } else if (resolved.type === 'ref') {
                        (tree.roots || []).forEach(function (r) {
                            (r.refs || []).forEach(function (ref) {
                                if (ref.localId === resolved.localId) {
                                    anchorCandleName = (ref.fields.candle_name || '').trim() || null;
                                }
                            });
                        });
                    }
                }
            }
        }

        var t = {
            timeframe:           tree.timeframe || '',
            anchor_global_key:   anchorGlobalKey,
            anchor_candle_name:  anchorCandleName,
            anchor_candle_index: tree.anchorIndex || '',
            roots:               [],
            drawings:            [],
            trades:              []
        };

        (tree.roots || []).forEach(function (root, rIdx) {
            var rootSnap = {
                local_key:           root.localId,
                candle_name:         root.fields.candle_name || '',
                price_level:         root.fields.price_level || '',
                timeframe:           tree.timeframe || root.fields.timeframe || '',
                candle_type:         root.fields.candle_type || '',
                candle_position:     root.fields.candle_position === '' ? '0' : root.fields.candle_position,
                candle_search:       root.fields.candle_search || '',
                operator:            root.fields.operator || '',
                authority_local_key: root.authorityLocalKey || null,
                root_refs:           []
            };
            (root.refs || []).forEach(function (ref) {
                var refSnap = {
                    candle_name:     ref.fields.candle_name || '',
                    candle_type:     ref.fields.candle_type || '',
                    timeframe:       tree.timeframe || ref.fields.timeframe || '',
                    price_level:     ref.fields.price_level || '',
                    candle_position: ref.fields.candle_position === '' ? '0' : ref.fields.candle_position,
                    candle_search:   ref.fields.candle_search || null,
                    re_ref_pairs:    []
                };
                (ref.reRefPairs || []).forEach(function (p) {
                    refSnap.re_ref_pairs.push({
                        author:     {
                            price_level: p.author.fields.price_level || '',
                            operator:    p.author.fields.operator || ''
                        },
                        referenced: {
                            price_level: p.servant.fields.price_level || ''
                        }
                    });
                });
                rootSnap.root_refs.push(refSnap);
            });
            t.roots.push(rootSnap);
        });

        (tree.drawings || []).forEach(function (d) {
            t.drawings.push({
                drawing_id:            d.fields.drawing_id,
                drawing_tools:         d.fields.drawing_tools || '',
                draw_from:             d.fields.draw_from || '',
                draw_from_price_level: d.fields.draw_from_price_level || '',
                draw_to:               d.fields.draw_to || '',
                draw_to_price_level:   d.fields.draw_to_price_level || '',
                drawing_color:         d.fields.drawing_color || ''
            });
        });

        (tree.trades || []).forEach(function (tr) {
            var f = tr.fields;
            var snap = {
                order_type:             f.order_type || '',
                entry_from:             f.entry_from || '',
                entry_from_price_level: f.entry_from_price_level || '',
                exit_at:                f.exit_at || '',
                exit_at_price_level:    f.exit_at_price_level || '',
                target_type:            f.target_type || 'set_target',
                target:                 '',
                target_price_level:     ''
            };
            if (snap.target_type === 'risk_reward') {
                snap.target = (f.target_rr_mode === 'minimum_risk_reward')
                    ? 'minimum_risk_reward'
                    : 'fixed_risk_reward';
                snap.target_price_level = '';
            } else {
                snap.target = f.target || '';
                snap.target_price_level = f.target_price_level || '';
            }
            t.trades.push(snap);
        });

        return t;
    }

    function pcGetConfiguration() {
        var trees = [];
        PC.trees.forEach(function (tree) { trees.push(pcBuildTreeSnapshot(tree)); });

        var amFixed = null;
        var amMin   = null;
        PC.trees.forEach(function (tree) {
            (tree.trades || []).forEach(function (tr) {
                var f = tr.fields || {};
                if (f.target_type !== 'risk_reward') return;
                var mode = f.target_rr_mode || 'fixed_risk_reward';
                var val  = String(f.rr_value == null ? '' : f.rr_value).trim();
                if (val === '' || isNaN(parseFloat(val))) return;
                var normalized = parseFloat(val).toFixed(2);
                if (mode === 'minimum_risk_reward') {
                    amMin = normalized;
                } else {
                    amFixed = normalized;
                }
            });
        });

        var out = { programmeId: PC.programmeId, trees: trees };
        var am = {};
        if (amFixed !== null) am.fixed_risk_reward   = amFixed;
        if (amMin   !== null) am.minimum_risk_reward = amMin;
        if (Object.keys(am).length) out.accountManagement = am;
        return out;
    }

    // ============================================================
    // SAVE SINGLE TREE
    // ============================================================
    function pcSaveTree(tree, btnEl) {
        var amFixed = null;
        var amMin   = null;
        (tree.trades || []).forEach(function (tr) {
            var f = tr.fields || {};
            if (f.target_type !== 'risk_reward') return;
            var mode = f.target_rr_mode || 'fixed_risk_reward';
            var val  = String(f.rr_value == null ? '' : f.rr_value).trim();
            if (val === '' || isNaN(parseFloat(val))) return;
            var normalized = parseFloat(val).toFixed(2);
            if (mode === 'minimum_risk_reward') amMin   = normalized;
            else                                amFixed = normalized;
        });

        var payload = {
            programmeId: PC.programmeId,
            tree_id:     tree.dbTreeId || 0,
            trees:       [ pcBuildTreeSnapshot(tree) ]
        };
        var am = {};
        if (amFixed !== null) am.fixed_risk_reward   = amFixed;
        if (amMin   !== null) am.minimum_risk_reward = amMin;
        if (Object.keys(am).length) payload.accountManagement = am;

        if (btnEl) { btnEl.disabled = true; }
        pcDebugLog('info', 'Saving single tree', payload);

        fetch('programme_configuration.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'save_configuration=1&payload=' + encodeURIComponent(JSON.stringify(payload))
        })
        .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Invalid server response (HTTP ' + r.status + ').' }; }); })
        .then(function (data) {
            if (btnEl) { btnEl.disabled = false; }
            if (!data || !data.success) {
                pcShowError((data && data.message) ? data.message : 'Unknown error while saving tree.');
                return;
            }
            PC_TREES_SERVER = data.rows || [];
            if (data.accountManagement) PC_ACCOUNT_MGMT = data.accountManagement;
            var oldTrees = PC.trees.slice();
            hydrateFromServer();
            PC.trees.forEach(function (nt) {
                oldTrees.forEach(function (ot) { if (ot.dbTreeId && ot.dbTreeId === nt.dbTreeId) nt.collapsed = ot.collapsed; });
            });
            renderTrees();
            pcShowSuccess('Tree saved successfully.', 'The selected tree has been saved and will be applied to the chart.');
            dispatchCustom('pc:save', {
                programmeId: PC.programmeId,
                trees: PC_TREES_SERVER,
                accountManagement: PC_ACCOUNT_MGMT
            });
        })
        .catch(function (err) {
            if (btnEl) { btnEl.disabled = false; }
            pcShowError('Network error while saving tree: ' + (err && err.message ? err.message : 'Unknown'));
        });
    }

    // ============================================================
    // OPEN / CLOSE
    // ============================================================
    function pcOpen(opts) {
        opts = opts || {};
        if (opts.programmeId != null) PC.programmeId = parseInt(opts.programmeId, 10) || 0;
        if (opts.programmeName)       PC.programmeName = String(opts.programmeName);
        if (Array.isArray(opts.timeframes) && opts.timeframes.length) PC_TIMEFRAMES = opts.timeframes.slice();
        if (opts.currentTimeframe) PC_CURRENT_TF = String(opts.currentTimeframe);
        if (Array.isArray(opts.trees)) PC_TREES_SERVER = opts.trees.slice();
        if (opts.accountManagement) PC_ACCOUNT_MGMT = opts.accountManagement;

        pcDebugLog('info', 'Opening configuration modal', { programmeId: PC.programmeId, trees: PC_TREES_SERVER.length });

        hydrateFromServer();
        renderTrees();

        var modal = getModal();
        if (!modal) return;
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
        var addTreeBtn = $('pcAddTreeBtn');

        if (closeBtn)  closeBtn.addEventListener('click', pcClose);
        if (cancelBtn) cancelBtn.addEventListener('click', pcClose);
        modal.addEventListener('mousedown', function (e) { if (e.target === modal) pcClose(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('pc-active')) pcClose();
        });

        if (addTreeBtn) addTreeBtn.addEventListener('click', function () { addTree(); });

        if (saveBtn) saveBtn.addEventListener('click', function () {
            var snap = pcGetConfiguration();
            pcDebugLog('info', 'Save all clicked', snap);
            dispatchCustom('pc:save', snap);
        });

        var errModal = getErrorModal();
        if (errModal) {
            var errClose = $('pcErrorClose');
            var errOk    = $('pcErrorOk');
            if (errClose) errClose.addEventListener('click', pcCloseError);
            if (errOk)    errOk.addEventListener('click', pcCloseError);
            errModal.addEventListener('mousedown', function (e) { if (e.target === errModal) pcCloseError(); });
        }

        var sucModal = getSuccessModal();
        if (sucModal) {
            var sucClose = $('pcSuccessClose');
            var sucOk    = $('pcSuccessOk');
            if (sucClose) sucClose.addEventListener('click', pcCloseSuccess);
            if (sucOk)    sucOk.addEventListener('click', pcCloseSuccess);
            sucModal.addEventListener('mousedown', function (e) { if (e.target === sucModal) pcCloseSuccess(); });
        }
    });

    function dispatchCustom(name, detail) {
        try { window.dispatchEvent(new CustomEvent(name, { detail: detail })); }
        catch (err) {
            var ev = document.createEvent('CustomEvent');
            ev.initCustomEvent(name, true, true, detail);
            ev.dispatchEvent && window.dispatchEvent(ev);
        }
    }

    window.pcOpenConfiguration  = pcOpen;
    window.pcCloseConfiguration = pcClose;
    window.pcGetConfiguration   = pcGetConfiguration;
    window.pcSetTimeframes = function (tfs, current) {
        if (Array.isArray(tfs) && tfs.length) PC_TIMEFRAMES = tfs.slice();
        if (current) PC_CURRENT_TF = String(current);
    };
    window.pcSetTrees = function (trees) { PC_TREES_SERVER = Array.isArray(trees) ? trees.slice() : []; };
    window.pcDebugState = function () {
        return {
            trees: PC.trees.map(function (t) {
                return {
                    localId: t.localId,
                    dbTreeId: t.dbTreeId,
                    anchorLocalKey: t.anchorLocalKey,
                    anchorDbId: t.anchorDbId,
                    anchorIndex: t.anchorIndex,
                    roots: t.roots.map(function (r) {
                        return { localId: r.localId, dbId: r.dbId, name: r.fields.candle_name };
                    })
                };
            })
        };
    };

})();
</script>