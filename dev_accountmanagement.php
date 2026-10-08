<?php
    // programme_accountmanagement.php — Account Management (Standalone, shelled)
    // NOW FULLY PER-PROGRAMME. All list-type settings live in their own
    // programme-scoped tables. Only simple scalar/JSON columns remain in
    // the `accountmanagement` master row.

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

    // ==================== FETCH USER ====================
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

    $currentBroker = $developer['broker'] ?? '';

    // ==================== RESOLVE ACTIVE PROGRAMME ====================
    $programmeId = (int)($_SESSION['selected_programme_id'] ?? 0);
    if ($programmeId <= 0) {
        $q = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
        $q->execute([$userId]);
        $programmeId = (int)($q->fetchColumn() ?: 0);
        if ($programmeId > 0) $_SESSION['selected_programme_id'] = $programmeId;
    }

    $programme = null;
    if ($programmeId > 0) {
        $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
        $q->execute([$programmeId, $userId]);
        $programme = $q->fetch(PDO::FETCH_ASSOC);
    }

    if (!$programme) {
        die("No programme selected. Please create or select a programme.");
    }

    // ==================== ACCOUNT MANAGEMENT COLUMNS (master row only) ====================
    // These are the ONLY columns still living in `accountmanagement`.
    // All list-type settings now have dedicated programme-scoped tables.
    $ACCOUNT_MGMT_COLUMNS = [
        'enable_risk_reward_correction'              => ['label' => 'Enable Risk Reward Correction',              'type' => 'bool',   'json' => false],
        'minimum_risk_reward'                        => ['label' => 'Minimum Risk Reward',                        'type' => 'decimal','json' => false],
        'fixed_risk_reward'                          => ['label' => 'Fixed Risk Reward',                          'type' => 'decimal','json' => false],
        'enable_breakeven'                           => ['label' => 'Enable Breakeven',                           'type' => 'bool',   'json' => false],
        'daily_target_config'                        => ['label' => 'Daily Target Config',                         'type' => 'json',   'json' => true],
        'restrict_order_from_timeframe'              => ['label' => 'Restrict Order From Timeframe',              'type' => 'varchar','json' => false],
        'use_recent_highest_balance_as_current_balance' => ['label' => 'Use Recent Highest Balance As Current Balance','type' => 'bool','json' => false],
        'additional_configurations'                  => ['label' => 'Additional Configurations',                  'type' => 'json',   'json' => true],
        'skip_orders_close_to_position'              => ['label' => 'Skip Orders Close To Position',               'type' => 'bool',   'json' => false],
        'cancel_orders_close_to_position'            => ['label' => 'Cancel Orders Close To Position',            'type' => 'bool',   'json' => false],
        'also_restrict_opposite_order_too_close_to_position' => ['label' => 'Also Restrict Opposite Order Too Close To Position','type' => 'bool','json' => false],
        'switch_invalid_to_instant_order'            => ['label' => 'Switch Invalid To Instant Order',             'type' => 'bool',   'json' => false],
        'enable_order_type_conversion'               => ['label' => 'Enable Order Type Conversion',                'type' => 'bool',   'json' => false],
    ];

    // ==================== LIST-TABLE REGISTRY ====================
    // Each entry maps a "logical key" to its DB table and column layout.
    $LIST_TABLES = [
        'trades_breakeven' => [
            'table'   => 'accountmanagement_trades_breakeven',
            'label'   => 'Trades Breakeven',
            'columns' => [
                'floating_profit_at_risk_reward' => ['label' => 'Floating Profit At Risk Reward', 'type' => 'decimal'],
                'breakeven_at_risk_reward'       => ['label' => 'Breakeven At Risk Reward',       'type' => 'decimal'],
            ],
        ],
        'min_balance_risk_distance' => [
            'table'   => 'accountmanagement_min_balance_risk_distance',
            'label'   => 'Minimum Balance Risk Distance',
            'columns' => [
                'from_balance_range' => ['label' => 'From Balance Range', 'type' => 'decimal'],
                'to_balance_range'   => ['label' => 'To Balance Range',   'type' => 'decimal'],
                'risk_amount'        => ['label' => 'Risk Amount',        'type' => 'decimal'],
            ],
        ],
        'max_balance_risk_distance' => [
            'table'   => 'accountmanagement_max_balance_risk_distance',
            'label'   => 'Maximum Balance Risk Distance',
            'columns' => [
                'from_balance_range' => ['label' => 'From Balance Range', 'type' => 'decimal'],
                'to_balance_range'   => ['label' => 'To Balance Range',   'type' => 'decimal'],
                'risk_amount'        => ['label' => 'Risk Amount',        'type' => 'decimal'],
            ],
        ],
        'balance_default_risk' => [
            'table'   => 'accountmanagement_balance_default_risk',
            'label'   => 'Account Balance Default Risk Management',
            'columns' => [
                'from_balance_range' => ['label' => 'From Balance Range', 'type' => 'decimal'],
                'to_balance_range'   => ['label' => 'To Balance Range',   'type' => 'decimal'],
                'risk_amount'        => ['label' => 'Risk Amount',        'type' => 'decimal'],
            ],
        ],
        'balance_maximum_risk' => [
            'table'   => 'accountmanagement_balance_maximum_risk',
            'label'   => 'Account Balance Maximum Risk Management',
            'columns' => [
                'from_balance_range' => ['label' => 'From Balance Range', 'type' => 'decimal'],
                'to_balance_range'   => ['label' => 'To Balance Range',   'type' => 'decimal'],
                'risk_amount'        => ['label' => 'Risk Amount',        'type' => 'decimal'],
            ],
        ],
        'restricted_days' => [
            'table'   => 'accountmanagement_restricted_days',
            'label'   => 'Restricted Days',
            'columns' => [
                'day'       => ['label' => 'Day',       'type' => 'day'],
                'from_time' => ['label' => 'From',      'type' => 'time'],
                'to_time'   => ['label' => 'To',        'type' => 'time'],
            ],
        ],
    ];

    // ==================== HELPERS ====================
    function getDeveloperInvestors(PDO $pdo, int $developerId): array {
        $stmt = $pdo->prepare("
            SELECT DISTINCT pi.investorid, h.fullname, h.email
            FROM programme_investors pi
            LEFT JOIN harvhub h ON h.id = pi.investorid
            WHERE pi.developerid = ? AND pi.investorid > 0
        ");
        $stmt->execute([$developerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function ensureAccountManagementRow(PDO $pdo, int $developerId, int $programmeId, int $investorId): int {
        $stmt = $pdo->prepare("SELECT id FROM accountmanagement WHERE developerid = ? AND programme_id = ? AND investorid = ? LIMIT 1");
        $stmt->execute([$developerId, $programmeId, $investorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
        $ins = $pdo->prepare("INSERT INTO accountmanagement (developerid, programme_id, investorid) VALUES (?, ?, ?)");
        $ins->execute([$developerId, $programmeId, $investorId]);
        return (int)$pdo->lastInsertId();
    }

    function syncInvestorRows(PDO $pdo, int $developerId, int $programmeId, array $columns): void {
        $stmt = $pdo->prepare("SELECT * FROM accountmanagement WHERE developerid = ? AND programme_id = ? AND investorid = 0 LIMIT 1");
        $stmt->execute([$developerId, $programmeId]);
        $master = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$master) return;

        $investors = getDeveloperInvestors($pdo, $developerId);
        if (empty($investors)) return;

        $cols = array_keys($columns);
        if (empty($cols)) return;

        $setParts = [];
        foreach ($cols as $c) { $setParts[] = "`$c` = ?"; }
        $setSql = implode(', ', $setParts);

        $upd = $pdo->prepare("UPDATE accountmanagement SET $setSql WHERE developerid = ? AND programme_id = ? AND investorid = ?");
        $insCols = array_merge(['developerid', 'programme_id', 'investorid'], $cols);
        $insPlace = implode(',', array_fill(0, count($insCols), '?'));
        $insSql = "INSERT INTO accountmanagement (" . implode(',', array_map(function($c){ return "`$c`"; }, $insCols)) . ") VALUES ($insPlace)";
        $ins = $pdo->prepare($insSql);

        foreach ($investors as $inv) {
            $invId = (int)$inv['investorid'];
            $chk = $pdo->prepare("SELECT id FROM accountmanagement WHERE developerid = ? AND programme_id = ? AND investorid = ? LIMIT 1");
            $chk->execute([$developerId, $programmeId, $invId]);
            $exists = $chk->fetch(PDO::FETCH_ASSOC);

            $vals = [];
            foreach ($cols as $c) { $vals[] = $master[$c]; }
            if ($exists) {
                $upd->execute(array_merge($vals, [$developerId, $programmeId, $invId]));
            } else {
                $ins->execute(array_merge([$developerId, $programmeId, $invId], $vals));
            }
        }
    }

    /**
     * Recursive JSON preview renderer.
     */
    function renderJsonPreview($data, $depth = 0) {
        if ($data === null) return '<span class="dd-json-null">null</span>';
        if (is_bool($data)) return '<span class="dd-json-bool">' . ($data ? 'true' : 'false') . '</span>';
        if (is_string($data)) return '<span class="dd-json-str">"' . htmlspecialchars($data) . '"</span>';
        if (is_numeric($data)) return '<span class="dd-json-num">' . htmlspecialchars((string)$data) . '</span>';
        if (is_array($data)) {
            if (empty($data)) return '[]';
            $isList = array_keys($data) === range(0, count($data) - 1);
            $html = $isList ? '[' : '{';
            $html .= '<div class="dd-json-preview-inner" style="padding-left:12px;">';
            foreach ($data as $k => $v) {
                $html .= '<div>';
                if (!$isList) $html .= '<span class="dd-json-key">"' . htmlspecialchars((string)$k) . '"</span>: ';
                $html .= renderJsonPreview($v, $depth + 1);
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= $isList ? ']' : '}';
            return $html;
        }
        return htmlspecialchars((string)$data);
    }

    // ==================== FETCH LIST-TABLE ROWS ====================
    function fetchListRows(PDO $pdo, string $table, int $developerId, int $programmeId): array {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE developerid = ? AND programme_id = ? ORDER BY id ASC");
            $stmt->execute([$developerId, $programmeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    // ==================== HANDLE AJAX: SAVE ACCOUNT MANAGEMENT ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account_management'])) {
        header('Content-Type: application/json');

        $singleCol = isset($_POST['single_col']) ? trim($_POST['single_col']) : '';

        if ($singleCol !== '') {
            $val = $_POST['single_value'] ?? '';
            if (!array_key_exists($singleCol, $ACCOUNT_MGMT_COLUMNS)) {
                echo json_encode(['success' => false, 'message' => 'Unknown column.']);
                exit;
            }

            $meta = $ACCOUNT_MGMT_COLUMNS[$singleCol];
            if ($meta['type'] === 'bool') {
                $cleanVal = ((int)$val) ? 1 : 0;
            } elseif ($meta['type'] === 'decimal') {
                $cleanVal = (float)$val;
            } else {
                $cleanVal = is_string($val) ? $val : json_encode($val);
            }

            try {
                $pdo->beginTransaction();
                ensureAccountManagementRow($pdo, $userId, $programmeId, 0);

                $upd = $pdo->prepare("UPDATE accountmanagement SET `$singleCol` = ? WHERE developerid = ? AND programme_id = ? AND investorid = 0");
                $upd->execute([$cleanVal, $userId, $programmeId]);

                syncInvestorRows($pdo, $userId, $programmeId, $ACCOUNT_MGMT_COLUMNS);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Column saved and synced to all investors.']);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $e->getMessage()]);
            }
            exit;
        }

        // ---- Full save ----
        $raw = $_POST['fields'] ?? '{}';
        $fields = json_decode($raw, true);
        if (!is_array($fields)) { echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit; }

        $allowed = array_keys($ACCOUNT_MGMT_COLUMNS);
        $clean = [];
        foreach ($fields as $col => $val) {
            if (!in_array($col, $allowed, true)) continue;
            $meta = $ACCOUNT_MGMT_COLUMNS[$col];
            if ($meta['type'] === 'bool') {
                $clean[$col] = ((int)$val) ? 1 : 0;
            } elseif ($meta['type'] === 'decimal') {
                $clean[$col] = (float)$val;
            } else {
                $clean[$col] = is_string($val) ? $val : json_encode($val);
            }
        }

        if (empty($clean)) { echo json_encode(['success' => false, 'message' => 'No fields to save.']); exit; }

        try {
            $pdo->beginTransaction();
            ensureAccountManagementRow($pdo, $userId, $programmeId, 0);

            $setParts = []; $vals = [];
            foreach ($clean as $col => $val) { $setParts[] = "`$col` = ?"; $vals[] = $val; }
            $vals[] = $userId;
            $vals[] = $programmeId;
            $sql = "UPDATE accountmanagement SET " . implode(', ', $setParts) . " WHERE developerid = ? AND programme_id = ? AND investorid = 0";
            $upd = $pdo->prepare($sql);
            $upd->execute($vals);

            syncInvestorRows($pdo, $userId, $programmeId, $ACCOUNT_MGMT_COLUMNS);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Account management saved and synced to all investors.']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $e->getMessage()]);
        }
        exit;
    }

    // ==================== HANDLE AJAX: LIST-TABLE CRUD ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_action'])) {
        header('Content-Type: application/json');

        $listAction = $_POST['list_action'];
        $listKey    = isset($_POST['list_key']) ? trim($_POST['list_key']) : '';

        if (!array_key_exists($listKey, $LIST_TABLES)) {
            echo json_encode(['success' => false, 'message' => 'Unknown list table.']);
            exit;
        }

        $def   = $LIST_TABLES[$listKey];
        $table = $def['table'];
        $cols  = $def['columns'];

        // ---------- LIST: fetch all rows ----------
        if ($listAction === 'list') {
            $rows = fetchListRows($pdo, $table, $userId, $programmeId);
            echo json_encode(['success' => true, 'rows' => $rows]);
            exit;
        }

        // ---------- ADD ----------
        if ($listAction === 'add') {
            $row = $_POST['row'] ?? [];
            if (!is_array($row)) { echo json_encode(['success' => false, 'message' => 'Invalid row data.']); exit; }

            $insertCols = [];
            $insertVals = [];
            foreach ($cols as $colName => $colMeta) {
                $v = $row[$colName] ?? null;

                if ($colMeta['type'] === 'decimal') {
                    $v = ($v === '' || $v === null) ? 0.0 : (float)$v;
                } elseif ($colMeta['type'] === 'day') {
                    $v = trim((string)$v);
                    $validDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    if (!in_array($v, $validDays, true)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid day.']);
                        exit;
                    }
                } elseif ($colMeta['type'] === 'time') {
                    $v = trim((string)$v);
                    // Accept HH:MM or HH:MM:SS
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid time format for ' . $colMeta['label'] . '.']);
                        exit;
                    }
                    if (strlen($v) === 5) $v .= ':00';
                } else {
                    $v = (string)$v;
                }

                $insertCols[] = $colName;
                $insertVals[] = $v;
            }

            // Restricted-days uniqueness: one day per programme
            if ($listKey === 'restricted_days') {
                $dayVal = $row['day'] ?? '';
                $chk = $pdo->prepare("SELECT id FROM `$table` WHERE developerid = ? AND programme_id = ? AND `day` = ? LIMIT 1");
                $chk->execute([$userId, $programmeId, $dayVal]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => false, 'message' => 'That day is already restricted for this programme.']);
                    exit;
                }
            }

            $colSql   = '`' . implode('`,`', $insertCols) . '`';
            $placeSql = implode(',', array_fill(0, count($insertCols), '?'));
            $sql = "INSERT INTO `$table` (`developerid`,`programme_id`,$colSql) VALUES (?,?,$placeSql)";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge([$userId, $programmeId], $insertVals));
                $newId = (int)$pdo->lastInsertId();
                echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Row added.']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to add: ' . $e->getMessage()]);
            }
            exit;
        }

        // ---------- UPDATE ----------
        if ($listAction === 'update') {
            $rowId = (int)($_POST['row_id'] ?? 0);
            $row   = $_POST['row'] ?? [];
            if ($rowId <= 0 || !is_array($row)) {
                echo json_encode(['success' => false, 'message' => 'Invalid row.']);
                exit;
            }

            $setParts = [];
            $setVals  = [];
            foreach ($cols as $colName => $colMeta) {
                if (!array_key_exists($colName, $row)) continue;
                $v = $row[$colName];

                if ($colMeta['type'] === 'decimal') {
                    $v = ($v === '' || $v === null) ? 0.0 : (float)$v;
                } elseif ($colMeta['type'] === 'day') {
                    $v = trim((string)$v);
                    $validDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    if (!in_array($v, $validDays, true)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid day.']);
                        exit;
                    }
                } elseif ($colMeta['type'] === 'time') {
                    $v = trim((string)$v);
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid time format for ' . $colMeta['label'] . '.']);
                        exit;
                    }
                    if (strlen($v) === 5) $v .= ':00';
                } else {
                    $v = (string)$v;
                }

                $setParts[] = "`$colName` = ?";
                $setVals[]  = $v;
            }

            if (empty($setParts)) {
                echo json_encode(['success' => false, 'message' => 'Nothing to update.']);
                exit;
            }

            // Restricted-days uniqueness on update
            if ($listKey === 'restricted_days' && isset($row['day'])) {
                $chk = $pdo->prepare("SELECT id FROM `$table` WHERE developerid = ? AND programme_id = ? AND `day` = ? AND id <> ? LIMIT 1");
                $chk->execute([$userId, $programmeId, $row['day'], $rowId]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => false, 'message' => 'That day is already restricted for this programme.']);
                    exit;
                }
            }

            $setVals[] = $rowId;
            $setVals[] = $userId;
            $setVals[] = $programmeId;
            $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE id = ? AND developerid = ? AND programme_id = ?";

            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($setVals);
                echo json_encode(['success' => true, 'message' => 'Row updated.']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()]);
            }
            exit;
        }

        // ---------- DELETE ----------
        if ($listAction === 'delete') {
            $rowId = (int)($_POST['row_id'] ?? 0);
            if ($rowId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid row.']); exit; }

            try {
                $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ? AND developerid = ? AND programme_id = ?");
                $stmt->execute([$rowId, $userId, $programmeId]);
                echo json_encode(['success' => true, 'message' => 'Row deleted.']);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $e->getMessage()]);
            }
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown list action.']);
        exit;
    }

    // ==================== ENSURE MASTER ROWS ====================
    try {
        ensureAccountManagementRow($pdo, $userId, $programmeId, 0);
        $investors = getDeveloperInvestors($pdo, $userId);
        foreach ($investors as $inv) ensureAccountManagementRow($pdo, $userId, $programmeId, (int)$inv['investorid']);
    } catch (PDOException $e) {}

    // ==================== FETCH ACCOUNT MANAGEMENT MASTER ROW ====================
    $accountMgmt = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM accountmanagement WHERE developerid = ? AND programme_id = ? AND investorid = 0 LIMIT 1");
        $stmt->execute([$userId, $programmeId]);
        $accountMgmt = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) { $accountMgmt = []; }

    // ==================== FETCH LIST-TABLE ROWS ====================
    $listRows = [];
    foreach ($LIST_TABLES as $key => $def) {
        $listRows[$key] = fetchListRows($pdo, $def['table'], $userId, $programmeId);
    }

    $programmeName = $programme['program_name'] ?? 'Unnamed Programme';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Management - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
<?php include 'dev_dashboard_style.php'; ?>
<style>
    /* ============================================================
       ACCOUNT MANAGEMENT — per-programme
       ============================================================ */
    :root {
        --spr-bg: var(--bg, #f4f8f7);
        --spr-card: var(--bg-card, #ffffff);
        --spr-text: var(--text, #12211d);
        --spr-muted: var(--text-muted, #60736d);
        --spr-accent: var(--accent, #12a36b);
        --spr-accent-2: var(--accent-hover, #0b7d52);
        --spr-soft: var(--accent-light, #e3f6ee);
        --spr-border: var(--border-color, #dfe9e5);
        --spr-danger: var(--danger, #df4e4e);
        --spr-warning: var(--warning, #b87513);
        --spr-success: var(--success, #2ecc71);
        --spr-info: var(--info, #3498db);
        --spr-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06));
        --spr-radius: var(--radius, 16px);
        --spr-page-pad: 20px;

        --bg: var(--spr-bg);
        --bg-card: var(--spr-card);
        --text: var(--spr-text);
        --text-muted: var(--spr-muted);
        --accent: var(--spr-accent);
        --accent-hover: var(--spr-accent-2);
        --accent-light: var(--spr-soft);
        --border-color: var(--spr-border);
        --danger: var(--spr-danger);
        --success: var(--spr-success);
        --radius: var(--spr-radius);
        --radius-sm: 10px;
    }

    body.dark-mode {
        --spr-bg: #0c1311;
        --spr-card: #131d1a;
        --spr-text: #e8f2ee;
        --spr-muted: #93a8a1;
        --spr-soft: #12271f;
        --spr-border: #22322d;
        --spr-shadow: 0 10px 30px rgba(0,0,0,.4);
    }

    button, a, input, select, textarea, label { touch-action: manipulation; }
    input, textarea, select { -webkit-touch-callout: none; }
    html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

    @media (max-width: 768px) {
        input, select, textarea,
        .dd-input, .dd-select, .dd-am-input, .dd-inline-input,
        .dd-req-input, .dd-json-edit-textarea, .pt-modal-input,
        .dd-time-input, .dd-time-select {
            font-size: 16px !important;
        }
    }

    .dd-page-body {
        padding: 0 !important; margin: 0 !important;
        height: 100vh !important; height: 100dvh !important;
        min-height: 0 !important; overflow: hidden !important;
        background: var(--spr-bg); color: var(--spr-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55; -webkit-tap-highlight-color: transparent;
    }

    .dd-page-wrapper {
        position: relative; width: 100%; height: 100%;
        overflow: hidden; background: var(--spr-bg); color: var(--spr-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .dd-sticky-top {
        position: absolute; top: 0; left: 0; right: 0;
        background: var(--spr-bg); z-index: 10;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    body.dark-mode .dd-sticky-top { background: var(--spr-bg); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4); }

    .dd-topbar {
        position: relative; display: flex; align-items: center; justify-content: center;
        min-height: 56px; padding: 10px 16px; box-sizing: border-box;
        border-bottom: 1px solid var(--spr-border);
    }
    body.dark-mode .dd-topbar { border-bottom-color: var(--spr-border); }

    .dd-topbar-back {
        position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
        background: transparent; border: none; color: var(--spr-text);
        font-size: 1.2rem; cursor: pointer; padding: 8px;
        width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
        border-radius: 50%; transition: background 0.2s ease; text-decoration: none;
    }
    .dd-topbar-back:hover { background: rgba(0, 0, 0, 0.06); }
    body.dark-mode .dd-topbar-back { color: var(--spr-text); }
    body.dark-mode .dd-topbar-back:hover { background: rgba(255, 255, 255, 0.08); }

    .dd-topbar-title {
        font-size: 1.15rem; font-weight: 800; color: var(--spr-text);
        margin: 0; letter-spacing: -0.3px; text-align: center;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: calc(100% - 120px); line-height: 1.2;
    }

    .dd-scroll-area {
        position: absolute; inset: 0; overflow-y: auto; overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 80px 20px 24px; box-sizing: border-box;
    }
    @media (max-width: 480px) { .dd-scroll-area { padding: 72px 12px 20px; } }

    .dd-notice {
        padding: 12px 16px; border-radius: var(--radius-sm, 10px);
        font-size: 0.85rem; line-height: 1.5; margin-bottom: 16px;
    }
    .dd-notice-info {
        background: var(--spr-soft); border: 1px solid var(--spr-border); color: var(--spr-text);
    }
    body.dark-mode .dd-notice-info {
        background: var(--spr-soft); border-color: var(--spr-border); color: var(--spr-text);
    }

    .dd-card {
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--spr-radius); padding: 20px; margin-bottom: 20px;
        box-shadow: var(--spr-shadow);
    }
    body.dark-mode .dd-card { background: var(--spr-card); border-color: var(--spr-border); }

    .dd-card-head {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 16px; flex-wrap: wrap; margin-bottom: 16px;
    }
    .dd-card-title { font-size: 1.15rem; font-weight: 800; color: var(--spr-text); margin: 0 0 4px 0; }
    .dd-card-subtitle { font-size: 0.82rem; color: var(--spr-muted); margin: 0; line-height: 1.5; }

    .dd-am-note {
        background: var(--spr-soft); border-left: 3px solid var(--spr-accent);
        padding: 10px 14px; border-radius: 0 var(--radius-sm, 10px) var(--radius-sm, 10px) 0;
        font-size: 0.8rem; line-height: 1.55; color: var(--spr-text); margin-bottom: 20px;
    }
    body.dark-mode .dd-am-note { background: var(--spr-soft); color: var(--spr-text); }

    .dd-section-head {
        display: flex; align-items: center; gap: 10px;
        margin: 24px 0 12px; padding-top: 16px;
        border-top: 1px solid var(--spr-border);
    }
    .dd-section-head:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
    .dd-section-head h3 {
        font-size: 1rem; font-weight: 800; color: var(--spr-text);
        margin: 0; display: flex; align-items: center; gap: 8px;
    }
    .dd-section-head h3 i { color: var(--spr-accent); font-size: 0.9rem; }
    .dd-section-head .dd-section-desc {
        font-size: 0.75rem; color: var(--spr-muted); margin-left: auto; text-align: right;
    }

    /* ============================================================
       ACCOUNT MANAGEMENT SIMPLE LIST
       ============================================================ */
    .dd-am-list { display: flex; flex-direction: column; gap: 16px; }

    .dd-am-item {
        background: var(--spr-bg); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 14px 16px;
        display: flex; flex-direction: column; gap: 10px;
        transition: border-color 0.2s ease;
    }
    body.dark-mode .dd-am-item {
        background: rgba(255, 255, 255, 0.03); border-color: var(--spr-border);
    }
    .dd-am-item:hover { border-color: var(--spr-accent); }

    .dd-am-head {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap;
    }
    .dd-am-label {
        font-size: 0.82rem; font-weight: 700; color: var(--spr-text);
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    .dd-am-input-wrap { display: flex; flex-direction: column; gap: 8px; }

    /* ============================================================
       LIST TABLE EDITOR
       ============================================================ */
    .dd-list-editor {
        background: var(--spr-bg); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 16px;
        display: flex; flex-direction: column; gap: 12px;
    }
    body.dark-mode .dd-list-editor { background: rgba(255,255,255,0.03); }

    .dd-list-add-btn {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; padding: 10px 18px; border-radius: var(--radius-sm, 10px);
        background: var(--spr-soft); color: var(--spr-accent);
        border: 1px dashed var(--spr-accent); font-family: inherit;
        font-size: 0.82rem; font-weight: 800; cursor: pointer;
        transition: all 0.15s ease; align-self: flex-start;
        -webkit-tap-highlight-color: transparent;
    }
    .dd-list-add-btn:hover { background: var(--spr-accent); color: #fff; border-style: solid; }
    .dd-list-add-btn:active { transform: scale(0.97); }
    .dd-list-add-btn i { font-size: 0.85rem; }

    .dd-list-form {
        background: var(--spr-card); border: 1px solid var(--spr-accent);
        border-radius: var(--radius-sm, 10px); padding: 14px;
        display: flex; flex-direction: column; gap: 10px;
        animation: ddListSlideIn 0.2s ease;
    }
    body.dark-mode .dd-list-form { background: var(--spr-card); border-color: var(--spr-accent); }

    @keyframes ddListSlideIn {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .dd-list-form-row {
        display: flex; flex-direction: column; gap: 5px;
    }
    .dd-list-form-row label {
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px;
        font-weight: 800; color: var(--spr-muted);
    }
    .dd-list-form-actions {
        display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px;
    }

    .dd-list-rows { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }

    .dd-list-row {
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 12px 14px;
        display: flex; flex-direction: column; gap: 10px;
        transition: border-color 0.15s ease;
    }
    body.dark-mode .dd-list-row { background: var(--spr-card); border-color: var(--spr-border); }
    .dd-list-row:hover { border-color: var(--spr-accent); }

    .dd-list-row-display {
        display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center;
    }
    .dd-list-row-display .dd-row-field {
        display: flex; flex-direction: column; gap: 2px; min-width: 90px;
    }
    .dd-list-row-display .dd-row-field-label {
        font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.4px;
        font-weight: 700; color: var(--spr-muted);
    }
    .dd-list-row-display .dd-row-field-value {
        font-size: 0.92rem; font-weight: 800; color: var(--spr-text);
        word-break: break-word;
    }

    .dd-list-row-actions {
        display: flex; gap: 8px; flex-wrap: wrap;
    }

    .dd-list-row-empty {
        padding: 20px 14px; text-align: center; color: var(--spr-muted);
        font-size: 0.82rem; font-style: italic;
        border: 1px dashed var(--spr-border); border-radius: var(--radius-sm, 10px);
    }

    /* Time input group */
    .dd-time-group {
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    }
    .dd-time-input {
        width: 60px; text-align: center; padding: 8px 6px !important;
        border: 1px solid var(--spr-border); border-radius: 8px;
        background: var(--spr-card); color: var(--spr-text);
        font-family: inherit; font-weight: 700;
        -webkit-appearance: none; appearance: none;
    }
    .dd-time-input:focus { outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 2px var(--spr-soft); }
    .dd-time-sep { font-weight: 800; color: var(--spr-muted); font-size: 1rem; }
    .dd-time-select {
        padding: 8px 10px !important; border: 1px solid var(--spr-border);
        border-radius: 8px; background: var(--spr-card); color: var(--spr-text);
        font-family: inherit; font-weight: 700; cursor: pointer;
        -webkit-appearance: none; appearance: none;
    }
    .dd-time-select:focus { outline: none; border-color: var(--spr-accent); }

    /* ============================================================
       INPUTS
       ============================================================ */
    .dd-input {
        width: 100%; box-sizing: border-box; padding: 10px 14px;
        border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px);
        background: var(--spr-card); color: var(--spr-text);
        font-size: 16px !important; font-family: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        -webkit-appearance: none; appearance: none;
    }
    .dd-input:focus {
        outline: none; border-color: var(--spr-accent);
        box-shadow: 0 0 0 3px var(--spr-soft);
    }
    body.dark-mode .dd-input {
        background: var(--spr-card); border-color: var(--spr-border); color: var(--spr-text);
    }
    .dd-am-input { max-width: 100%; }
    .dd-select { cursor: pointer; }

    /* ============================================================
       BUTTONS
       ============================================================ */
    .dd-btn-primary {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; padding: 12px 24px; border-radius: var(--radius-sm, 10px);
        font-family: inherit; font-size: 0.88rem; font-weight: 800;
        cursor: pointer; border: none; background: var(--spr-accent); color: #fff;
        transition: background 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
        width: 100%; margin-top: 8px; letter-spacing: 0.2px;
        -webkit-tap-highlight-color: transparent;
    }
    .dd-btn-primary:hover {
        background: var(--spr-accent-2); box-shadow: 0 4px 14px rgba(18, 163, 107, 0.3);
    }
    .dd-btn-primary:active { transform: scale(0.98); }
    .dd-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

    .dd-btn-ghost {
        display: inline-flex; align-items: center; justify-content: center;
        gap: 8px; padding: 10px 20px; border-radius: var(--radius-sm, 10px);
        font-family: inherit; font-size: 0.85rem; font-weight: 700;
        cursor: pointer; border: 1px solid var(--spr-border);
        background: transparent; color: var(--spr-text);
        transition: background 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
        -webkit-tap-highlight-color: transparent;
    }
    .dd-btn-ghost:hover {
        background: var(--spr-soft); border-color: var(--spr-accent); color: var(--spr-accent);
    }
    .dd-btn-ghost:active { transform: scale(0.98); }
    body.dark-mode .dd-btn-ghost { border-color: var(--spr-border); color: var(--spr-text); }
    body.dark-mode .dd-btn-ghost:hover {
        background: var(--spr-soft); border-color: var(--spr-accent); color: var(--spr-accent);
    }

    .dd-btn-mini {
        padding: 7px 14px; border-radius: 8px; font-family: inherit;
        font-size: 0.75rem; font-weight: 800; cursor: pointer;
        border: 1px solid transparent; transition: all 0.15s ease;
        white-space: nowrap; -webkit-tap-highlight-color: transparent;
        display: inline-flex; align-items: center; gap: 5px;
    }
    .dd-btn-mini:active { transform: scale(0.96); }

    .dd-btn-save {
        background: var(--spr-accent); color: #fff; border-color: var(--spr-accent);
    }
    .dd-btn-save:hover { background: var(--spr-accent-2); }

    .dd-btn-cancel {
        background: transparent; color: var(--spr-muted); border-color: var(--spr-border);
    }
    .dd-btn-cancel:hover { background: rgba(0, 0, 0, 0.05); }

    .dd-btn-edit {
        background: var(--spr-soft); color: var(--spr-accent); border-color: var(--spr-border);
    }
    .dd-btn-edit:hover { background: var(--spr-soft); border-color: var(--spr-accent); }

    .dd-btn-danger {
        background: rgba(231, 76, 60, 0.1); color: #e74c3c;
        border-color: rgba(231, 76, 60, 0.3);
    }
    .dd-btn-danger:hover { background: rgba(231, 76, 60, 0.2); }

    /* ============================================================
       COLUMN HEAD ACTION ROW
       ============================================================ */
    .dd-am-col-actions {
        display: flex; gap: 10px; align-items: center;
        flex-wrap: wrap; justify-content: flex-end;
    }

    .dd-btn-col {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 18px; border-radius: 999px;
        font-family: inherit; font-size: 0.78rem; font-weight: 700;
        letter-spacing: 0.3px; cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        border: 1.5px solid transparent; white-space: nowrap; line-height: 1;
        -webkit-tap-highlight-color: transparent;
    }
    .dd-btn-col:active { transform: scale(0.96); }
    .dd-btn-col i { font-size: 0.9rem; line-height: 1; }

    .dd-btn-col-view {
        background: transparent; color: var(--spr-accent); border-color: var(--spr-accent);
    }
    .dd-btn-col-view:hover { background: var(--spr-soft); }

    .dd-btn-col-save {
        background: var(--spr-accent); color: #fff; border-color: var(--spr-accent);
        box-shadow: 0 3px 10px rgba(18, 163, 107, 0.3);
    }
    .dd-btn-col-save:hover {
        background: var(--spr-accent-2); box-shadow: 0 5px 14px rgba(18, 163, 107, 0.4);
    }
    .dd-btn-col-save:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

    .dd-btn-col-construct {
        background: var(--spr-soft); color: var(--spr-accent); border-color: var(--spr-border);
    }
    .dd-btn-col-construct:hover { background: var(--spr-soft); border-color: var(--spr-accent); }

    /* ============================================================
       SWITCH (toggle) for JSON columns
       ============================================================ */
    .dd-am-switch {
        position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0;
    }
    .dd-am-switch input { opacity: 0; width: 0; height: 0; }
    .dd-am-slider {
        position: absolute; cursor: pointer; inset: 0;
        background-color: #ccc; border-radius: 24px; transition: 0.3s;
    }
    .dd-am-slider:before {
        content: ""; position: absolute; height: 18px; width: 18px;
        left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: 0.3s;
    }
    .dd-am-switch input:checked + .dd-am-slider { background-color: var(--spr-accent); }
    .dd-am-switch input:checked + .dd-am-slider:before { transform: translateX(20px); }
    .dd-am-toggle-label { font-size: 0.78rem; font-weight: 600; color: var(--spr-muted); }

    /* ============================================================
       FOLDABLE JSON PREVIEW
       ============================================================ */
    .dd-foldable { position: relative; }
    .dd-foldable.is-collapsed { max-height: 180px; overflow: hidden; }
    .dd-foldable.is-collapsed::after {
        content: ""; position: absolute; left: 0; right: 0; bottom: 0;
        height: 56px; background: linear-gradient(to bottom, transparent, var(--spr-bg));
        pointer-events: none; border-radius: 0 0 var(--radius-sm, 10px) var(--radius-sm, 10px);
    }
    body.dark-mode .dd-foldable.is-collapsed::after {
        background: linear-gradient(to bottom, transparent, var(--spr-card));
    }
    .dd-fold-toggle {
        display: inline-flex; align-items: center; gap: 6px;
        margin-top: 10px; font-family: inherit; font-size: 0.72rem;
        font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;
        color: var(--spr-accent); background: transparent;
        border: 1px solid var(--spr-accent); border-radius: var(--radius-sm, 10px);
        padding: 6px 14px; cursor: pointer;
        transition: background 0.15s ease, transform 0.1s ease;
    }
    .dd-fold-toggle:hover { background: var(--spr-soft); }
    .dd-fold-toggle:active { transform: scale(0.97); }
    .dd-fold-toggle .dd-fold-icon { transition: transform 0.2s ease; display: inline-block; font-size: 0.85rem; line-height: 1; }
    .dd-fold-toggle.is-expanded .dd-fold-icon { transform: rotate(180deg); }

    /* ============================================================
       JSON PREVIEW
       ============================================================ */
    .dd-json-preview {
        background: var(--spr-bg); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 12px 14px;
        font-family: 'Courier New', monospace; font-size: 0.78rem;
        line-height: 1.55; overflow: auto; max-height: 400px; color: var(--spr-text);
    }
    body.dark-mode .dd-json-preview { background: var(--spr-bg); border-color: var(--spr-border); color: var(--spr-text); }
    .dd-json-preview-empty { color: var(--spr-muted); font-style: italic; }
    .dd-json-key { color: #e67e22; }
    .dd-json-str { color: #27ae60; }
    .dd-json-num { color: #2980b9; }
    .dd-json-bool { color: #8e44ad; }
    .dd-json-null { color: #95a5a6; }
    body.dark-mode .dd-json-key { color: #f0a35e; }
    body.dark-mode .dd-json-str { color: #5fd38a; }
    body.dark-mode .dd-json-num { color: #5dade2; }
    body.dark-mode .dd-json-bool { color: #c39bd3; }
    body.dark-mode .dd-json-null { color: #aab7b8; }

    .dd-json-fold { margin: 0; }
    .dd-json-fold summary {
        cursor: pointer; font-size: 0.78rem; font-weight: 600;
        color: var(--spr-accent); padding: 4px 0; user-select: none;
    }
    .dd-json-fold summary:hover { text-decoration: underline; }

    /* ============================================================
       MODAL
       ============================================================ */
    .dd-modal {
        display: none; position: fixed; inset: 0;
        background: rgba(0, 0, 0, 0.58);
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        z-index: 9999; align-items: center; justify-content: center;
        padding: 20px; box-sizing: border-box; overscroll-behavior: contain;
    }
    .dd-modal.active { display: flex; }

    .dd-modal-content {
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--spr-radius); padding: 24px;
        max-width: 420px; width: 100%;
        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
        max-height: 85vh; overflow-y: auto;
        -webkit-overflow-scrolling: touch; overscroll-behavior: contain;
        animation: ddModalSlideIn 0.25s ease;
    }
    body.dark-mode .dd-modal-content { background: var(--spr-card); border-color: var(--spr-border); }

    @keyframes ddModalSlideIn {
        from { opacity: 0; transform: scale(0.96) translateY(16px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .dd-json-view-modal .dd-modal-content { max-width: 600px; }

    .dd-modal-title {
        font-size: 1.15rem; font-weight: 800; color: var(--spr-accent);
        margin: 0 0 16px 0; text-align: center;
    }
    .dd-modal-body { margin-bottom: 16px; }
    .dd-modal-text { font-size: 0.95rem; line-height: 1.6; color: var(--spr-text); margin: 0; text-align: center; }
    .dd-modal-actions { display: flex; flex-direction: column; gap: 10px; }

    .dd-json-view-body {
        background: var(--spr-bg); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 14px 16px;
        max-height: 60vh; overflow: auto;
    }
    .dd-json-view-body pre {
        margin: 0; font-family: 'Courier New', monospace;
        font-size: 0.82rem; line-height: 1.55;
        white-space: pre-wrap; word-break: break-word; color: var(--spr-text);
    }
    body.dark-mode .dd-json-view-body { background: var(--spr-bg); border-color: var(--spr-border); }
    body.dark-mode .dd-json-view-body pre { color: var(--spr-text); }

    .dd-json-edit-textarea {
        display: none; width: 100%; box-sizing: border-box;
        min-height: 320px; max-height: 60vh; resize: vertical;
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 12px 14px;
        font-family: 'Courier New', monospace; font-size: 0.82rem;
        line-height: 1.55; color: var(--spr-text);
    }
    .dd-json-edit-textarea:focus {
        outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 3px var(--spr-soft);
    }
    body.dark-mode .dd-json-edit-textarea {
        background: var(--spr-card); border-color: var(--spr-border); color: var(--spr-text);
    }

    .dd-json-view-actions {
        display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap;
    }
    .dd-json-view-actions .dd-btn-primary,
    .dd-json-view-actions .dd-btn-ghost {
        width: auto; flex: 1; min-width: 100px; margin-top: 0;
    }

    /* ============================================================
       INLINE EDITORS (in-page JSON tree)
       ============================================================ */
    .dd-inline-editor {
        background: var(--spr-soft); border: 1px solid var(--spr-border);
        border-radius: var(--radius-sm, 10px); padding: 12px 14px;
        margin: 8px 0; display: flex; flex-direction: column; gap: 10px;
    }
    body.dark-mode .dd-inline-editor { background: var(--spr-soft); border-color: var(--spr-border); }

    .dd-inline-editor-row {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .dd-inline-label {
        font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.3px; color: var(--spr-muted); min-width: 50px;
    }
    .dd-inline-input {
        flex: 1; min-width: 120px; padding: 8px 12px !important; font-size: 14px !important;
    }
    .dd-inline-editor-actions { display: flex; gap: 8px; flex-wrap: wrap; }

    .dd-btn-add {
        background: var(--spr-accent); color: #fff; border-color: var(--spr-accent);
    }
    .dd-btn-add:hover { background: var(--spr-accent-2); }

    /* ============================================================
       JSON TREE NODES
       ============================================================ */
    .dd-json-node { margin: 4px 0; padding: 6px 0; }
    .dd-json-node-head {
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px;
    }
    .dd-json-node-label { font-weight: 700; font-size: 0.8rem; color: var(--spr-text); }
    .dd-json-badge {
        display: inline-block; padding: 2px 8px; border-radius: 999px;
        background: var(--spr-soft); color: var(--spr-accent);
        font-size: 0.68rem; font-weight: 700;
    }
    .dd-json-children {
        padding-left: 16px; border-left: 2px solid var(--spr-border); margin-left: 6px;
    }
    .dd-json-child { margin: 4px 0; }
    .dd-json-leaf {
        padding: 2px 0; font-size: 0.78rem; display: flex;
        align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .dd-json-key-row {
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 4px 0;
    }
    .dd-json-key-name { font-weight: 700; color: #e67e22; font-size: 0.78rem; }
    body.dark-mode .dd-json-key-name { color: #f0a35e; }
    .dd-json-str-label { font-size: 0.72rem; color: var(--spr-muted); font-weight: 600; }
    .dd-json-str-value { color: #27ae60; font-size: 0.78rem; }
    body.dark-mode .dd-json-str-value { color: #5fd38a; }

    /* ============================================================
       TOAST
       ============================================================ */
    .dd-save-toast {
        position: fixed; bottom: 24px; left: 50%;
        transform: translateX(-50%) translateY(80px);
        background: var(--spr-success); color: #fff;
        font-family: inherit; font-size: 0.85rem; font-weight: 700;
        padding: 12px 26px; border-radius: 24px;
        box-shadow: 0 6px 24px rgba(46, 204, 113, 0.4);
        opacity: 0; transition: opacity 0.3s ease, transform 0.3s ease;
        z-index: 999999; pointer-events: none; max-width: 88vw; text-align: center;
    }
    .dd-save-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .dd-save-toast.error { background: var(--spr-danger); box-shadow: 0 6px 24px rgba(223, 78, 78, 0.4); }

    /* ============================================================
       RESPONSIVE
       ============================================================ */
    @media (max-width: 480px) {
        .dd-topbar { min-height: 52px; padding: 8px 12px; }
        .dd-topbar-back { left: 12px; width: 34px; height: 34px; font-size: 1.05rem; }
        .dd-topbar-title { font-size: 1.05rem; max-width: calc(100% - 100px); }
        .dd-card { padding: 14px 12px; }
        .dd-card-head { flex-direction: column; }
        .dd-card-head .dd-btn-primary { width: 100%; margin-top: 0; }
        .dd-am-col-actions { width: 100%; justify-content: flex-start; }
        .dd-btn-col { padding: 8px 14px; font-size: 0.74rem; }
        .dd-list-editor { padding: 12px; }
        .dd-list-form { padding: 12px; }
        .dd-time-input { width: 52px; }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> dd-page-body">

    <div class="dd-page-wrapper">

        <!-- Fixed top group -->
        <div class="dd-sticky-top">
            <div class="dd-topbar">
                <button type="button" class="dd-topbar-back" onclick="ddGoBack()" aria-label="Back">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <h1 class="dd-topbar-title">Account Management</h1>
            </div>
        </div>

        <!-- Scrollable content -->
        <div class="dd-scroll-area">

            <!-- Broker status -->
            <?php if (!empty($currentBroker)): ?>
                <div class="dd-notice dd-notice-info">
                    <strong>Broker:</strong> <?= htmlspecialchars($currentBroker) ?>
                </div>
            <?php endif; ?>

            <div class="dd-notice dd-notice-info">
                <strong>Programme:</strong> <?= htmlspecialchars($programmeName) ?>
                <span style="display:block;font-size:0.78rem;margin-top:4px;">
                    All settings on this page apply only to this programme.
                </span>
            </div>

            <section class="dd-card">
                <div class="dd-card-head">
                    <div>
                        <h2 class="dd-card-title">Account Management</h2>
                        <p class="dd-card-subtitle">
                            Each investor under you has exactly one row in the account management table.
                            Saving here propagates to all of them for this programme.
                        </p>
                    </div>
                    <button type="button" class="dd-btn-primary" style="width:auto;" id="saveAccountMgmtBtn" onclick="saveAccountManagement()">
                        Save All
                    </button>
                </div>

                <div class="dd-am-note">
                    <strong>Note:</strong> Any change here will be applied to all investors under you for <strong><?= htmlspecialchars($programmeName) ?></strong>.
                    JSON columns render an interactive tree directly on this page — each column has its own save button.
                </div>

                <div class="dd-am-list">
                    <?php foreach ($ACCOUNT_MGMT_COLUMNS as $col => $meta):
                        $val = $accountMgmt[$col] ?? null;
                        $isJson = $meta['json'];
                        $isExistingJson = false;
                        $jsonData = null;
                        if ($isJson && $val !== null && $val !== '') {
                            $decoded = json_decode($val, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $isExistingJson = true;
                                $jsonData = $decoded;
                            }
                        }
                    ?>
                        <div class="dd-am-item" data-col="<?= htmlspecialchars($col) ?>">
                            <div class="dd-am-head">
                                <label class="dd-am-label"><?= htmlspecialchars($meta['label']) ?></label>

                                <?php if ($isJson): ?>
                                    <?php if ($isExistingJson): ?>
                                        <div class="dd-am-toggle-wrap dd-am-col-actions">
                                            <button type="button"
                                                    class="dd-btn-col dd-btn-col-view"
                                                    onclick="viewJsonDataCol('<?= htmlspecialchars($col) ?>')">
                                                <i class="fa-regular fa-eye"></i> View JSON Data
                                            </button>
                                            <button type="button"
                                                    class="dd-btn-col dd-btn-col-save"
                                                    onclick="saveSingleColumn('<?= htmlspecialchars($col) ?>')">
                                                <i class="fa-solid fa-floppy-disk"></i> Save Column
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="dd-am-toggle-wrap dd-am-col-actions">
                                            <label class="dd-am-switch">
                                                <input type="checkbox"
                                                       class="dd-am-json-toggle"
                                                       data-col="<?= htmlspecialchars($col) ?>"
                                                       onchange="onJsonToggle(this)">
                                                <span class="dd-am-slider"></span>
                                            </label>
                                            <span class="dd-am-toggle-label">Store JSON</span>
                                            <button type="button"
                                                    class="dd-btn-col dd-btn-col-construct"
                                                    id="constructBtn-<?= htmlspecialchars($col) ?>"
                                                    style="display:none;"
                                                    onclick="startNewJsonForColumn('<?= htmlspecialchars($col) ?>')">
                                                <i class="fa-solid fa-wand-magic-sparkles"></i> Construct JSON
                                            </button>
                                            <button type="button"
                                                    class="dd-btn-col dd-btn-col-save"
                                                    id="saveColBtn-<?= htmlspecialchars($col) ?>"
                                                    style="display:none;"
                                                    onclick="saveSingleColumn('<?= htmlspecialchars($col) ?>')">
                                                <i class="fa-solid fa-floppy-disk"></i> Save Column
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="dd-am-input-wrap">
                                <?php if ($meta['type'] === 'bool'): ?>
                                    <select class="dd-input dd-am-input dd-select" data-col="<?= htmlspecialchars($col) ?>" data-type="bool">
                                        <option value="0" <?= ((int)$val === 0) ? 'selected' : '' ?>>Disabled</option>
                                        <option value="1" <?= ((int)$val === 1) ? 'selected' : '' ?>>Enabled</option>
                                    </select>
                                <?php elseif ($meta['type'] === 'decimal'): ?>
                                    <input type="number" step="0.01" class="dd-input dd-am-input"
                                           data-col="<?= htmlspecialchars($col) ?>" data-type="decimal"
                                           value="<?= htmlspecialchars((string)($val ?? '0.00')) ?>">
                                <?php elseif ($meta['type'] === 'varchar'): ?>
                                    <input type="text" class="dd-input dd-am-input"
                                           data-col="<?= htmlspecialchars($col) ?>" data-type="varchar"
                                           value="<?= htmlspecialchars((string)($val ?? '')) ?>"
                                           placeholder="Enter value">
                                <?php else: ?>
                                    <?php if ($isExistingJson): ?>
                                        <details class="dd-json-fold" open>
                                            <summary>Show JSON</summary>
                                            <div class="dd-json-preview" data-col="<?= htmlspecialchars($col) ?>" id="jsonPreview-<?= htmlspecialchars($col) ?>">
                                                <?= renderJsonPreview($jsonData) ?>
                                            </div>
                                        </details>
                                        <input type="hidden" class="dd-am-json-hidden"
                                               data-col="<?= htmlspecialchars($col) ?>"
                                               value="<?= htmlspecialchars($val) ?>">
                                    <?php else: ?>
                                        <div class="dd-json-preview dd-json-preview-empty"
                                             data-col="<?= htmlspecialchars($col) ?>"
                                             id="jsonPreview-<?= htmlspecialchars($col) ?>"
                                             style="display:none;"></div>
                                        <input type="hidden" class="dd-am-json-hidden"
                                               data-col="<?= htmlspecialchars($col) ?>"
                                               value="">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="dd-btn-primary" id="saveAccountMgmtBtnBottom" onclick="saveAccountManagement()">
                    Save All Account Management
                </button>
            </section>

            <!-- ============================================================
                 LIST-TABLE SECTIONS (per-programme, multi-row)
                 ============================================================ -->
            <?php foreach ($LIST_TABLES as $listKey => $listDef): ?>
                <section class="dd-card" id="dd-list-section-<?= htmlspecialchars($listKey) ?>">
                    <div class="dd-card-head">
                        <div>
                            <h2 class="dd-card-title"><?= htmlspecialchars($listDef['label']) ?></h2>
                            <p class="dd-card-subtitle">
                                Multiple rows are allowed per programme. Each row is stored independently.
                            </p>
                        </div>
                    </div>

                    <div class="dd-list-editor" data-list-key="<?= htmlspecialchars($listKey) ?>">
                        <!-- Add button (stays at top) -->
                        <button type="button" class="dd-list-add-btn"
                                onclick="ddListOpenAdd('<?= htmlspecialchars($listKey) ?>')">
                            <i class="fa-solid fa-plus"></i>
                            <?php
                                if ($listKey === 'restricted_days') echo 'Add day to restrict';
                                elseif (strpos($listKey, 'balance') !== false || strpos($listKey, 'risk') !== false) echo 'Add new balance range risk';
                                else echo 'Add new row';
                            ?>
                        </button>

                        <!-- Add form container (injected by JS) -->
                        <div id="dd-list-add-<?= htmlspecialchars($listKey) ?>"></div>

                        <!-- Existing rows -->
                        <div class="dd-list-rows" id="dd-list-rows-<?= htmlspecialchars($listKey) ?>">
                            <?php if (empty($listRows[$listKey])): ?>
                                <div class="dd-list-row-empty">No rows yet. Add one above.</div>
                            <?php else: ?>
                                <?php foreach ($listRows[$listKey] as $row): ?>
                                    <div class="dd-list-row" data-row-id="<?= (int)$row['id'] ?>">
                                        <div class="dd-list-row-display">
                                            <?php foreach ($listDef['columns'] as $cName => $cMeta): ?>
                                                <div class="dd-row-field">
                                                    <span class="dd-row-field-label"><?= htmlspecialchars($cMeta['label']) ?></span>
                                                    <span class="dd-row-field-value">
                                                        <?php
                                                            $v = $row[$cName] ?? '';
                                                            if ($cMeta['type'] === 'decimal') {
                                                                echo htmlspecialchars(number_format((float)$v, 2, '.', ''));
                                                            } elseif ($cMeta['type'] === 'time') {
                                                                echo htmlspecialchars(substr((string)$v, 0, 5));
                                                            } else {
                                                                echo htmlspecialchars((string)$v);
                                                            }
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="dd-list-row-actions">
                                            <button type="button" class="dd-btn-mini dd-btn-edit"
                                                    onclick="ddListOpenEdit('<?= htmlspecialchars($listKey) ?>', <?= (int)$row['id'] ?>)">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                            <button type="button" class="dd-btn-mini dd-btn-danger"
                                                    onclick="ddListDelete('<?= htmlspecialchars($listKey) ?>', <?= (int)$row['id'] ?>)">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </div>
                                        <div id="dd-list-edit-<?= htmlspecialchars($listKey) ?>-<?= (int)$row['id'] ?>"></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

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

    <!-- ==================== MODAL: JSON DATA VIEW / EDIT ==================== -->
    <div id="ddJsonViewModal" class="dd-modal dd-json-view-modal">
        <div class="dd-modal-content">
            <h2 class="dd-modal-title" id="ddJsonViewTitle">JSON Data</h2>

            <div class="dd-json-view-body" id="ddJsonViewBody">
                <pre id="ddJsonViewPre"></pre>
            </div>

            <textarea id="ddJsonEditTextarea" class="dd-json-edit-textarea" spellcheck="false"></textarea>

            <div class="dd-json-view-actions">
                <button type="button" class="dd-btn-ghost" id="ddJsonEditBtn" onclick="enableJsonEdit()">Edit JSON</button>
                <button type="button" class="dd-btn-ghost" id="ddJsonCancelEditBtn" style="display:none;" onclick="cancelJsonEdit()">Cancel</button>
                <button type="button" class="dd-btn-primary" id="ddJsonApplyBtn" onclick="applyJsonEditAndSave()">Apply JSON &amp; Save</button>
                <button type="button" class="dd-btn-ghost" onclick="closeJsonView()">Close</button>
            </div>
        </div>
    </div>

    <!-- ==================== TOAST ==================== -->
    <div id="ddSaveToast" class="dd-save-toast"></div>

<script>
    // ==================== BACK NAVIGATION ====================
    window.ddGoBack = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'menu' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'programme_menu.php';
    };

    // ==================== THEME SYNC ====================
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') {
            document.body.classList.toggle('dark-mode', !!e.data.dark);
        }
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}

    // ==================== MODAL → SHELL BRIDGE ====================
    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: open ? 'harvhubModalOpen' : 'harvhubModalClose' }, '*');
            }
        } catch (e) {}
    }

    // ==================== STATE ====================
    var ACCOUNT_MGMT_COLUMNS = <?= json_encode($ACCOUNT_MGMT_COLUMNS) ?>;
    var LIST_TABLES = <?= json_encode($LIST_TABLES) ?>;

    var jsonDataByCol = {};
    var jsonEditorByCol = {};
    var jsonRootPickerByCol = {};

    var jsonViewCol = null;
    var jsonViewEditing = false;

    var FOLD_THRESHOLD = 220;

    // ==================== HELPERS ====================
    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }
    function escapeAttr(t) {
        return String(t).replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
    function lockBodyScroll() {
        if (document.body.dataset.ddModalLocked === '1') return;
        var y = window.scrollY || 0;
        document.body.dataset.ddModalLocked = '1';
        document.body.dataset.ddModalY = y;
        document.body.style.overflow = 'hidden';
    }
    function unlockBodyScroll() {
        if (document.body.dataset.ddModalLocked !== '1') return;
        document.body.dataset.ddModalLocked = '0';
        document.body.style.overflow = '';
    }

    // ==================== TOAST ====================
    var _toastTimer = null;
    function showToast(msg, isError) {
        var t = document.getElementById('ddSaveToast');
        if (!t) return;
        t.textContent = msg;
        t.classList.toggle('error', !!isError);
        t.classList.add('show');
        if (_toastTimer) clearTimeout(_toastTimer);
        _toastTimer = setTimeout(function () {
            t.classList.remove('show');
        }, 2200);
    }

    // ==================== ALERT ====================
    function showDdAlert(msg, title) {
        document.getElementById('ddAlertTitle').textContent = title || 'Notice';
        document.getElementById('ddAlertMessage').textContent = msg == null ? '' : String(msg);
        document.getElementById('ddAlertModal').classList.add('active');
        lockBodyScroll();
        notifyParentModal(true);
    }
    function closeDdAlert() {
        document.getElementById('ddAlertModal').classList.remove('active');
        unlockBodyScroll();
        notifyParentModal(false);
    }

    // ==================== FOLD (PAGE PREVIEWS ONLY) ====================
    function toggleFold(btn) {
        var targetId = btn.getAttribute('data-target');
        var target = targetId ? document.getElementById(targetId) : btn.previousElementSibling;
        if (!target) return;
        var collapsed = target.classList.toggle('is-collapsed');
        btn.classList.toggle('is-expanded', !collapsed);
        var label = btn.querySelector('.dd-fold-label');
        if (label) label.textContent = collapsed ? 'Expand' : 'Collapse';
    }
    function ensureFoldable(previewEl, col) {
        if (!previewEl) return;
        previewEl.classList.add('dd-foldable');
        var wrap = previewEl.closest('.dd-am-input-wrap');
        if (!wrap) return;
        var btn = wrap.querySelector('.dd-fold-toggle[data-target="' + previewEl.id + '"]');
        var wasCollapsed = previewEl.classList.contains('is-collapsed');
        previewEl.classList.remove('is-collapsed');
        var fullHeight = previewEl.scrollHeight;
        var shouldFold = fullHeight > FOLD_THRESHOLD;
        if (!shouldFold) {
            if (btn) btn.remove();
            previewEl.classList.remove('is-collapsed');
            return;
        }
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dd-fold-toggle';
            btn.setAttribute('data-target', previewEl.id);
            btn.innerHTML = '<span class="dd-fold-label">Expand</span> <i class="dd-fold-icon">▾</i>';
            btn.addEventListener('click', function () { toggleFold(btn); });
            wrap.appendChild(btn);
        }
        var startCollapsed = true;
        previewEl.classList.toggle('is-collapsed', startCollapsed);
        btn.classList.toggle('is-expanded', !startCollapsed);
        var label = btn.querySelector('.dd-fold-label');
        if (label) label.textContent = startCollapsed ? 'Expand' : 'Collapse';
    }

    // ==================== SINGLE-COLUMN SAVE ====================
    function saveSingleColumn(col) {
        var meta = ACCOUNT_MGMT_COLUMNS[col];
        if (!meta) return;
        var valueToSend;
        if (meta.json) {
            if (jsonDataByCol.hasOwnProperty(col) && jsonDataByCol[col] !== null && jsonDataByCol[col] !== undefined) {
                valueToSend = JSON.stringify(jsonDataByCol[col]);
            } else {
                var hidden = document.querySelector('.dd-am-json-hidden[data-col="' + col + '"]');
                valueToSend = hidden ? (hidden.value || '') : '';
            }
        } else {
            var inp = document.querySelector('.dd-am-input[data-col="' + col + '"]');
            if (!inp) return;
            var type = inp.getAttribute('data-type');
            if (type === 'bool') valueToSend = inp.value === '1' ? 1 : 0;
            else if (type === 'decimal') valueToSend = parseFloat(inp.value) || 0;
            else valueToSend = inp.value;
        }
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'save_account_management=1'
                + '&single_col=' + encodeURIComponent(col)
                + '&single_value=' + encodeURIComponent(valueToSend)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                var hidden = document.querySelector('.dd-am-json-hidden[data-col="' + col + '"]');
                if (hidden && meta.json) hidden.value = valueToSend;
                showToast('Saved: ' + (meta.label || col));
            } else {
                showToast(data.message || 'Failed to save.', true);
            }
        })
        .catch(function () {
            showToast('Network error. Please try again.', true);
        });
    }

    // ==================== FULL SAVE (SAVE ALL) ====================
    function saveAccountManagement() {
        var btn = document.getElementById('saveAccountMgmtBtn');
        var btn2 = document.getElementById('saveAccountMgmtBtnBottom');
        var originalText = btn ? btn.textContent : 'Save All';
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
        if (btn2) { btn2.disabled = true; btn2.textContent = 'Saving...'; }

        var fields = {};
        document.querySelectorAll('.dd-am-input').forEach(function (inp) {
            var col = inp.getAttribute('data-col');
            var type = inp.getAttribute('data-type');
            if (!col) return;
            if (type === 'bool') fields[col] = inp.value === '1' ? 1 : 0;
            else if (type === 'decimal') fields[col] = parseFloat(inp.value) || 0;
            else fields[col] = inp.value;
        });

        Object.keys(ACCOUNT_MGMT_COLUMNS).forEach(function (col) {
            var meta = ACCOUNT_MGMT_COLUMNS[col];
            if (!meta.json) return;
            if (jsonDataByCol.hasOwnProperty(col) && jsonDataByCol[col] !== null) {
                fields[col] = JSON.stringify(jsonDataByCol[col]);
            } else {
                var hidden = document.querySelector('.dd-am-json-hidden[data-col="' + col + '"]');
                fields[col] = hidden ? (hidden.value || '') : '';
            }
        });

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'save_account_management=1&fields=' + encodeURIComponent(JSON.stringify(fields))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            if (btn2) { btn2.disabled = false; btn2.textContent = 'Save All Account Management'; }
            if (data.success) {
                showToast('All columns saved and synced.');
            } else {
                showToast(data.message || 'Failed to save.', true);
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            if (btn2) { btn2.disabled = false; btn2.textContent = 'Save All Account Management'; }
            showToast('Network error. Please try again.', true);
        });
    }

    // ==================== LIST-TABLE CRUD ====================
    var _listRowCache = {};

    function ddListOpenAdd(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        var container = document.getElementById('dd-list-add-' + listKey);
        if (!container) return;

        // Toggle: if a form is already open, close it
        if (container.innerHTML.trim() !== '') {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = ddListBuildFormHtml(listKey, null);
    }

    function ddListOpenEdit(listKey, rowId) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        var rowEl = document.querySelector('#dd-list-rows-' + listKey + ' .dd-list-row[data-row-id="' + rowId + '"]');
        if (!rowEl) return;
        var editContainer = document.getElementById('dd-list-edit-' + listKey + '-' + rowId);
        if (!editContainer) return;

        if (editContainer.innerHTML.trim() !== '') {
            editContainer.innerHTML = '';
            return;
        }

        // Fetch current data from the display values
        var rowData = {};
        var fields = rowEl.querySelectorAll('.dd-row-field');
        var colKeys = Object.keys(def.columns);
        fields.forEach(function (f, idx) {
            if (idx < colKeys.length) {
                rowData[colKeys[idx]] = f.querySelector('.dd-row-field-value').textContent.trim();
            }
        });

        editContainer.innerHTML = ddListBuildFormHtml(listKey, rowId, rowData);
    }

    function ddListBuildFormHtml(listKey, rowId, rowData) {
        var def = LIST_TABLES[listKey];
        if (!def) return '';
        rowData = rowData || {};

        var html = '<div class="dd-list-form">';

        Object.keys(def.columns).forEach(function (colName) {
            var meta = def.columns[colName];
            var val = rowData[colName] !== undefined ? rowData[colName] : '';

            html += '<div class="dd-list-form-row">';
            html += '<label>' + escapeHtml(meta.label) + '</label>';

            if (meta.type === 'day') {
                var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                html += '<select class="dd-input dd-select" id="dd-list-field-' + listKey + '-' + colName + '">';
                html += '<option value="">— Select day —</option>';
                days.forEach(function (d) {
                    html += '<option value="' + d + '"' + (val === d ? ' selected' : '') + '>' + d + '</option>';
                });
                html += '</select>';
            } else if (meta.type === 'time') {
                // Time input group: HH : MM AM/PM
                var hh = '12', mm = '00', ap = 'AM';
                if (val && /^\d{1,2}:\d{2}/.test(val)) {
                    var parts = val.split(':');
                    var h24 = parseInt(parts[0], 10);
                    mm = parts[1] || '00';
                    ap = h24 >= 12 ? 'PM' : 'AM';
                    hh = h24 % 12; if (hh === 0) hh = 12;
                    hh = String(hh).padStart(2, '0');
                }
                html += '<div class="dd-time-group">';
                html += '<input type="number" min="1" max="12" class="dd-time-input" id="dd-list-field-' + listKey + '-' + colName + '-hh" value="' + escapeHtml(hh) + '">';
                html += '<span class="dd-time-sep">:</span>';
                html += '<input type="number" min="0" max="59" class="dd-time-input" id="dd-list-field-' + listKey + '-' + colName + '-mm" value="' + escapeHtml(mm) + '">';
                html += '<select class="dd-time-select" id="dd-list-field-' + listKey + '-' + colName + '-ap">';
                html += '<option value="AM"' + (ap === 'AM' ? ' selected' : '') + '>AM</option>';
                html += '<option value="PM"' + (ap === 'PM' ? ' selected' : '') + '>PM</option>';
                html += '</select>';
                html += '</div>';
            } else if (meta.type === 'decimal') {
                html += '<input type="number" step="0.01" class="dd-input" id="dd-list-field-' + listKey + '-' + colName + '" value="' + escapeHtml(val !== '' ? val : '0.00') + '">';
            } else {
                html += '<input type="text" class="dd-input" id="dd-list-field-' + listKey + '-' + colName + '" value="' + escapeHtml(val) + '">';
            }
            html += '</div>';
        });

        html += '<div class="dd-list-form-actions">';
        if (rowId) {
            html += '<button type="button" class="dd-btn-mini dd-btn-save" onclick="ddListSaveEdit(\'' + escapeAttr(listKey) + '\',' + rowId + ')"><i class="fa-solid fa-floppy-disk"></i> Save</button>';
            html += '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="ddListCancelEdit(\'' + escapeAttr(listKey) + '\',' + rowId + ')">Cancel</button>';
        } else {
            html += '<button type="button" class="dd-btn-mini dd-btn-save" onclick="ddListSaveAdd(\'' + escapeAttr(listKey) + '\')"><i class="fa-solid fa-plus"></i> Add</button>';
            html += '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="ddListCancelAdd(\'' + escapeAttr(listKey) + '\')">Cancel</button>';
        }
        html += '</div>';
        html += '</div>';
        return html;
    }

    function ddListCancelAdd(listKey) {
        var container = document.getElementById('dd-list-add-' + listKey);
        if (container) container.innerHTML = '';
    }

    function ddListCancelEdit(listKey, rowId) {
        var container = document.getElementById('dd-list-edit-' + listKey + '-' + rowId);
        if (container) container.innerHTML = '';
    }

    function ddListCollectFields(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return null;
        var row = {};

        for (var colName in def.columns) {
            if (!def.columns.hasOwnProperty(colName)) continue;
            var meta = def.columns[colName];

            if (meta.type === 'day') {
                var el = document.getElementById('dd-list-field-' + listKey + '-' + colName);
                if (!el || !el.value) {
                    showDdAlert('Please select a day.', 'Missing value');
                    return null;
                }
                row[colName] = el.value;
            } else if (meta.type === 'time') {
                var hhEl = document.getElementById('dd-list-field-' + listKey + '-' + colName + '-hh');
                var mmEl = document.getElementById('dd-list-field-' + listKey + '-' + colName + '-mm');
                var apEl = document.getElementById('dd-list-field-' + listKey + '-' + colName + '-ap');
                if (!hhEl || !mmEl || !apEl) return null;

                var hh = parseInt(hhEl.value, 10);
                var mm = parseInt(mmEl.value, 10);
                if (isNaN(hh) || hh < 1 || hh > 12) { showDdAlert('Hour must be between 1 and 12.', 'Invalid time'); return null; }
                if (isNaN(mm) || mm < 0 || mm > 59) { showDdAlert('Minutes must be between 0 and 59.', 'Invalid time'); return null; }
                var ap = apEl.value;

                var h24 = hh % 12;
                if (ap === 'PM') h24 += 12;
                var hStr = String(h24).padStart(2, '0');
                var mStr = String(mm).padStart(2, '0');
                row[colName] = hStr + ':' + mStr + ':00';
            } else if (meta.type === 'decimal') {
                var el2 = document.getElementById('dd-list-field-' + listKey + '-' + colName);
                row[colName] = el2 ? (parseFloat(el2.value) || 0) : 0;
            } else {
                var el3 = document.getElementById('dd-list-field-' + listKey + '-' + colName);
                row[colName] = el3 ? el3.value : '';
            }
        }
        return row;
    }

    function ddListSaveAdd(listKey) {
        var row = ddListCollectFields(listKey);
        if (!row) return;

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'list_action=add'
                + '&list_key=' + encodeURIComponent(listKey)
                + '&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showToast('Row added.');
                ddListReload(listKey);
            } else {
                showDdAlert(data.message || 'Failed to add row.', 'Error');
            }
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListSaveEdit(listKey, rowId) {
        var row = ddListCollectFields(listKey);
        if (!row) return;

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'list_action=update'
                + '&list_key=' + encodeURIComponent(listKey)
                + '&row_id=' + encodeURIComponent(rowId)
                + '&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showToast('Row updated.');
                ddListReload(listKey);
            } else {
                showDdAlert(data.message || 'Failed to update row.', 'Error');
            }
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListDelete(listKey, rowId) {
        if (!confirm('Delete this row? This cannot be undone.')) return;

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'list_action=delete'
                + '&list_key=' + encodeURIComponent(listKey)
                + '&row_id=' + encodeURIComponent(rowId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showToast('Row deleted.');
                ddListReload(listKey);
            } else {
                showDdAlert(data.message || 'Failed to delete row.', 'Error');
            }
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListReload(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return;

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'list_action=list&list_key=' + encodeURIComponent(listKey)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            ddListRenderRows(listKey, data.rows || []);
            // Clear any open add/edit forms
            var addContainer = document.getElementById('dd-list-add-' + listKey);
            if (addContainer) addContainer.innerHTML = '';
        })
        .catch(function () {});
    }

    function ddListRenderRows(listKey, rows) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        var container = document.getElementById('dd-list-rows-' + listKey);
        if (!container) return;

        if (!rows.length) {
            container.innerHTML = '<div class="dd-list-row-empty">No rows yet. Add one above.</div>';
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            html += '<div class="dd-list-row" data-row-id="' + row.id + '">';
            html += '<div class="dd-list-row-display">';
            Object.keys(def.columns).forEach(function (cName) {
                var cMeta = def.columns[cName];
                var v = row[cName] !== undefined ? row[cName] : '';
                var displayVal;
                if (cMeta.type === 'decimal') {
                    displayVal = parseFloat(v).toFixed(2);
                } else if (cMeta.type === 'time') {
                    displayVal = String(v).substring(0, 5);
                } else {
                    displayVal = String(v);
                }
                html += '<div class="dd-row-field">';
                html += '<span class="dd-row-field-label">' + escapeHtml(cMeta.label) + '</span>';
                html += '<span class="dd-row-field-value">' + escapeHtml(displayVal) + '</span>';
                html += '</div>';
            });
            html += '</div>';
            html += '<div class="dd-list-row-actions">';
            html += '<button type="button" class="dd-btn-mini dd-btn-edit" onclick="ddListOpenEdit(\'' + escapeAttr(listKey) + '\',' + row.id + ')"><i class="fa-solid fa-pen"></i> Edit</button>';
            html += '<button type="button" class="dd-btn-mini dd-btn-danger" onclick="ddListDelete(\'' + escapeAttr(listKey) + '\',' + row.id + ')"><i class="fa-solid fa-trash"></i> Delete</button>';
            html += '</div>';
            html += '<div id="dd-list-edit-' + listKey + '-' + row.id + '"></div>';
            html += '</div>';
        });
        container.innerHTML = html;
    }

    // ==================== JSON VIEW / EDIT MODAL ====================
    function viewJsonDataCol(col) {
        var data = (jsonDataByCol.hasOwnProperty(col) && jsonDataByCol[col] !== null && jsonDataByCol[col] !== undefined)
            ? jsonDataByCol[col]
            : null;
        if (data === null) {
            var hidden = document.querySelector('.dd-am-json-hidden[data-col="' + col + '"]');
            var raw = hidden ? (hidden.value || '') : '';
            if (raw === '') {
                showDdAlert('No JSON data stored for this column yet.', 'JSON Data');
                return;
            }
            try { data = JSON.parse(raw); } catch (e) {
                showDdAlert('Stored JSON data is invalid.', 'JSON Data');
                return;
            }
            jsonDataByCol[col] = data;
        }
        jsonViewCol = col;
        jsonViewEditing = false;
        var meta = ACCOUNT_MGMT_COLUMNS[col] || {};
        document.getElementById('ddJsonViewTitle').textContent = meta.label || col;
        document.getElementById('ddJsonViewPre').textContent = JSON.stringify(data, null, 2);
        document.getElementById('ddJsonViewBody').style.display = 'block';
        document.getElementById('ddJsonViewBody').classList.remove('is-collapsed', 'dd-foldable');
        document.getElementById('ddJsonEditTextarea').style.display = 'none';
        document.getElementById('ddJsonEditBtn').style.display = 'inline-block';
        document.getElementById('ddJsonCancelEditBtn').style.display = 'none';
        document.getElementById('ddJsonApplyBtn').style.display = 'none';
        document.getElementById('ddJsonViewModal').classList.add('active');
        lockBodyScroll();
        notifyParentModal(true);
    }

    function enableJsonEdit() {
        if (!jsonViewCol) return;
        var data = jsonDataByCol[jsonViewCol];
        document.getElementById('ddJsonViewBody').style.display = 'none';
        var ta = document.getElementById('ddJsonEditTextarea');
        ta.value = JSON.stringify(data, null, 2);
        ta.style.display = 'block';
        document.getElementById('ddJsonEditBtn').style.display = 'none';
        document.getElementById('ddJsonCancelEditBtn').style.display = 'inline-block';
        document.getElementById('ddJsonApplyBtn').style.display = 'inline-block';
        jsonViewEditing = true;
        setTimeout(function () { ta.focus(); }, 50);
    }

    function cancelJsonEdit() {
        if (!jsonViewCol) return;
        document.getElementById('ddJsonEditTextarea').style.display = 'none';
        document.getElementById('ddJsonViewBody').style.display = 'block';
        document.getElementById('ddJsonEditBtn').style.display = 'inline-block';
        document.getElementById('ddJsonCancelEditBtn').style.display = 'none';
        document.getElementById('ddJsonApplyBtn').style.display = 'none';
        jsonViewEditing = false;
    }

    function applyJsonEditAndSave() {
        if (!jsonViewCol) return;
        var col = jsonViewCol;
        var parsed;
        if (jsonViewEditing) {
            var ta = document.getElementById('ddJsonEditTextarea');
            try { parsed = JSON.parse(ta.value); } catch (e) {
                showDdAlert('Invalid JSON: ' + e.message, 'Error');
                return;
            }
            jsonDataByCol[col] = parsed;
            jsonEditorByCol[col] = null;
            renderJsonColumn(col);
            document.getElementById('ddJsonViewPre').textContent = JSON.stringify(parsed, null, 2);
        } else {
            parsed = jsonDataByCol[col];
        }
        var btn = document.getElementById('ddJsonApplyBtn');
        var originalText = btn ? btn.textContent : 'Apply JSON & Save';
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
        var valueToSend = JSON.stringify(parsed);
        var hidden = document.querySelector('.dd-am-json-hidden[data-col="' + col + '"]');
        if (hidden) hidden.value = valueToSend;

        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'save_account_management=1'
                + '&single_col=' + encodeURIComponent(col)
                + '&single_value=' + encodeURIComponent(valueToSend)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            if (data.success) {
                showToast('JSON saved for: ' + ((ACCOUNT_MGMT_COLUMNS[col] || {}).label || col));
                cancelJsonEdit();
                setTimeout(closeJsonView, 400);
            } else {
                showDdAlert(data.message || 'Failed to save.', 'Error');
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            showDdAlert('Network error. Please try again.', 'Error');
        });
    }

    function closeJsonView() {
        document.getElementById('ddJsonViewModal').classList.remove('active');
        jsonViewCol = null;
        jsonViewEditing = false;
        unlockBodyScroll();
        notifyParentModal(false);
    }

    // ==================== JSON TOGGLE ====================
    function onJsonToggle(cb) {
        var col = cb.getAttribute('data-col');
        var btn = document.getElementById('constructBtn-' + col);
        var saveBtn = document.getElementById('saveColBtn-' + col);
        if (btn) btn.style.display = cb.checked ? 'inline-flex' : 'none';
        if (saveBtn) saveBtn.style.display = cb.checked ? 'inline-flex' : 'none';
    }

    // ==================== ROOT TYPE PICKER (new JSON) ====================
    function startNewJsonForColumn(col) {
        jsonRootPickerByCol[col] = true;
        jsonDataByCol[col] = null;
        jsonEditorByCol[col] = null;
        renderJsonRootPicker(col);
    }

    function renderJsonRootPicker(col) {
        var preview = document.getElementById('jsonPreview-' + col);
        if (!preview) return;
        preview.style.display = 'block';
        preview.innerHTML =
            '<div class="dd-inline-editor">' +
                '<div class="dd-inline-editor-row">' +
                    '<label class="dd-inline-label">Root Type:</label>' +
                    '<select id="rootType-' + escapeAttr(col) + '" class="dd-input dd-inline-input">' +
                        '<option value="array">Array []</option>' +
                        '<option value="object">Object {}</option>' +
                        '<option value="string">String ""</option>' +
                    '</select>' +
                '</div>' +
                '<div class="dd-inline-editor-actions">' +
                    '<button type="button" class="dd-btn-mini dd-btn-add" onclick="confirmRootTypeCol(\'' + escapeAttr(col) + '\')">Create</button>' +
                    '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="cancelRootPickerCol(\'' + escapeAttr(col) + '\')">Cancel</button>' +
                '</div>' +
            '</div>';
    }

    function confirmRootTypeCol(col) {
        var sel = document.getElementById('rootType-' + col);
        var t = sel ? sel.value : 'array';
        jsonRootPickerByCol[col] = false;
        if (t === 'object') {
            jsonDataByCol[col] = {};
            jsonEditorByCol[col] = { kind: 'addKey', path: 'root' };
        } else if (t === 'string') {
            jsonDataByCol[col] = '';
            jsonEditorByCol[col] = { kind: 'editString', path: 'root' };
        } else {
            jsonDataByCol[col] = [];
            jsonEditorByCol[col] = { kind: 'addArrayValue', path: 'root' };
        }
        renderJsonColumn(col);
    }

    function cancelRootPickerCol(col) {
        jsonRootPickerByCol[col] = false;
        jsonDataByCol[col] = null;
        jsonEditorByCol[col] = null;
        var cb = document.querySelector('.dd-am-json-toggle[data-col="' + col + '"]');
        if (cb) cb.checked = false;
        onJsonToggle(cb);
        var preview = document.getElementById('jsonPreview-' + col);
        if (preview) { preview.style.display = 'none'; preview.innerHTML = ''; }
    }

    // ==================== RENDER JSON COLUMN ====================
    function renderJsonColumn(col) {
        var preview = document.getElementById('jsonPreview-' + col);
        if (!preview) return;
        var data = jsonDataByCol[col];
        if (data === undefined || data === null) {
            if (jsonRootPickerByCol[col]) { renderJsonRootPicker(col); return; }
            preview.style.display = 'none';
            preview.innerHTML = '';
            return;
        }
        preview.style.display = 'block';
        preview.classList.add('dd-foldable');
        preview.innerHTML = renderJsonColumnTree(col, data, 'root', 'root');
        ensureFoldable(preview, col);
    }

    function renderJsonColumnTree(col, node, path, keyLabel) {
        if (Array.isArray(node)) return renderJsonColumnArray(col, node, path, keyLabel);
        if (node !== null && typeof node === 'object') return renderJsonColumnObject(col, node, path, keyLabel);
        if (typeof node === 'string') return renderJsonColumnString(col, node, path, keyLabel);
        return '<div class="dd-json-leaf">' + escapeHtml(String(node)) + '</div>';
    }

    function renderJsonColumnArray(col, arr, path, keyLabel) {
        var editor = jsonEditorByCol[col];
        var html = '<div class="dd-json-node dd-json-array">';
        html += '<div class="dd-json-node-head">';
        html += '<span class="dd-json-node-label">' + escapeHtml(keyLabel || '[]') + ' <span class="dd-json-badge">Array [' + arr.length + ']</span></span>';
        html += '<button type="button" class="dd-btn-mini dd-btn-add" onclick="openEditorForCol(\'' + escapeAttr(col) + '\',\'addArrayValue\',\'' + escapeAttr(path) + '\')">+ Add New Value</button>';
        html += '</div>';
        if (editor && editor.kind === 'addArrayValue' && editor.path === path) {
            html += renderInlineAddArrayEditor(col, path);
        }
        html += '<div class="dd-json-children">';
        arr.forEach(function (item, idx) {
            var childPath = path + '.' + idx;
            html += '<div class="dd-json-child">';
            if (typeof item === 'string') html += renderJsonColumnString(col, item, childPath, '[' + idx + ']');
            else if (Array.isArray(item)) html += renderJsonColumnArray(col, item, childPath, '[' + idx + ']');
            else if (item !== null && typeof item === 'object') html += renderJsonColumnObject(col, item, childPath, '[' + idx + ']');
            else html += '<div class="dd-json-leaf">' + escapeHtml(String(item)) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function renderJsonColumnObject(col, obj, path, keyLabel) {
        var editor = jsonEditorByCol[col];
        var keys = Object.keys(obj);
        var html = '<div class="dd-json-node dd-json-object">';
        html += '<div class="dd-json-node-head">';
        html += '<span class="dd-json-node-label">' + escapeHtml(keyLabel || '{}') + ' <span class="dd-json-badge">Object {' + keys.length + '}</span></span>';
        html += '<button type="button" class="dd-btn-mini dd-btn-add" onclick="openEditorForCol(\'' + escapeAttr(col) + '\',\'addKey\',\'' + escapeAttr(path) + '\')">+ Add New Key</button>';
        html += '</div>';
        if (editor && editor.kind === 'addKey' && editor.path === path) {
            html += renderInlineAddKeyEditor(col, path);
        }
        html += '<div class="dd-json-children">';
        keys.forEach(function (k) {
            var childPath = path + '.' + k;
            var v = obj[k];
            html += '<div class="dd-json-child">';
            if (editor && editor.kind === 'editKey' && editor.path === path && editor.key === k) {
                html += renderInlineEditKeyEditor(col, path, k);
            } else {
                html += '<div class="dd-json-key-row">';
                html += '<span class="dd-json-key-name">"' + escapeHtml(k) + '"</span>';
                html += '<button type="button" class="dd-btn-mini dd-btn-edit" onclick="openEditorForCol(\'' + escapeAttr(col) + '\',\'editKey\',\'' + escapeAttr(path) + '\',\'' + escapeAttr(k) + '\')">Edit Key</button>';
                html += '<button type="button" class="dd-btn-mini dd-btn-danger" onclick="deleteJsonKeyCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\',\'' + escapeAttr(k) + '\')">×</button>';
                html += '</div>';
            }
            if (typeof v === 'string') {
                if (editor && editor.kind === 'editString' && editor.path === childPath) {
                    html += renderInlineEditStringEditor(col, childPath);
                } else {
                    html += renderJsonColumnString(col, v, childPath, 'value');
                }
            } else if (Array.isArray(v)) {
                html += renderJsonColumnArray(col, v, childPath, 'value');
            } else if (v !== null && typeof v === 'object') {
                html += renderJsonColumnObject(col, v, childPath, 'value');
            } else {
                html += '<div class="dd-json-leaf">' + escapeHtml(String(v)) + '</div>';
            }
            html += '</div>';
        });
        html += '</div></div>';
        return html;
    }

    function renderJsonColumnString(col, str, path, keyLabel) {
        var editor = jsonEditorByCol[col];
        if (editor && editor.kind === 'editString' && editor.path === path) {
            return renderInlineEditStringEditor(col, path);
        }
        return '<div class="dd-json-leaf dd-json-string">' +
            '<span class="dd-json-str-label">' + escapeHtml(keyLabel || 'string') + ':</span> ' +
            '<span class="dd-json-str-value">"' + escapeHtml(str) + '"</span> ' +
            '<button type="button" class="dd-btn-mini dd-btn-edit" onclick="openEditorForCol(\'' + escapeAttr(col) + '\',\'editString\',\'' + escapeAttr(path) + '\')">Edit Value</button>' +
            '</div>';
    }

    // ==================== INLINE EDITORS ====================
    function openEditorForCol(col, kind, path, key) {
        jsonEditorByCol[col] = { kind: kind, path: path, key: key || null };
        renderJsonColumn(col);
        setTimeout(function () {
            var map = {
                'addArrayValue': 'inlineAddArrayString-' + col,
                'addKey': 'inlineAddKeyName-' + col,
                'editKey': 'inlineEditKeyName-' + col,
                'editString': 'inlineEditStringValue-' + col
            };
            var id = map[kind];
            if (id) { var el = document.getElementById(id); if (el) { el.focus(); if (el.select) el.select(); } }
        }, 50);
    }

    function cancelEditorForCol(col) {
        jsonEditorByCol[col] = null;
        renderJsonColumn(col);
    }

    function renderInlineAddArrayEditor(col, path) {
        return '<div class="dd-inline-editor">' +
            '<div class="dd-inline-editor-row">' +
                '<label class="dd-inline-label">Type:</label>' +
                '<select id="inlineAddArrayType-' + escapeAttr(col) + '" class="dd-input dd-inline-input" onchange="onInlineAddArrayTypeChangeCol(\'' + escapeAttr(col) + '\',this)">' +
                    '<option value="string">String ""</option>' +
                    '<option value="object">Object {}</option>' +
                    '<option value="array">Array []</option>' +
                '</select>' +
            '</div>' +
            '<div class="dd-inline-editor-row" id="inlineAddArrayStringWrap-' + escapeAttr(col) + '">' +
                '<label class="dd-inline-label">Value:</label>' +
                '<input type="text" id="inlineAddArrayString-' + escapeAttr(col) + '" class="dd-input dd-inline-input" placeholder="Enter string value">' +
            '</div>' +
            '<div class="dd-inline-editor-actions">' +
                '<button type="button" class="dd-btn-mini dd-btn-add" onclick="confirmAddToArrayCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\')">Add</button>' +
                '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="cancelEditorForCol(\'' + escapeAttr(col) + '\')">Cancel</button>' +
            '</div>' +
        '</div>';
    }

    function onInlineAddArrayTypeChangeCol(col, sel) {
        var wrap = document.getElementById('inlineAddArrayStringWrap-' + col);
        if (wrap) wrap.style.display = (sel.value === 'string') ? 'flex' : 'none';
    }

    function confirmAddToArrayCol(col, path) {
        var typeEl = document.getElementById('inlineAddArrayType-' + col);
        var type = typeEl ? typeEl.value : 'string';
        var arr = resolvePathInCol(col, path);
        if (!Array.isArray(arr)) { cancelEditorForCol(col); return; }
        var newVal;
        if (type === 'string') {
            var inp = document.getElementById('inlineAddArrayString-' + col);
            newVal = inp ? inp.value : '';
        } else if (type === 'object') newVal = {};
        else newVal = [];
        arr.push(newVal);
        jsonEditorByCol[col] = null;
        renderJsonColumn(col);
    }

    function renderInlineAddKeyEditor(col, path) {
        return '<div class="dd-inline-editor">' +
            '<div class="dd-inline-editor-row">' +
                '<label class="dd-inline-label">Key:</label>' +
                '<input type="text" id="inlineAddKeyName-' + escapeAttr(col) + '" class="dd-input dd-inline-input" placeholder="Enter key name">' +
            '</div>' +
            '<div class="dd-inline-editor-row">' +
                '<label class="dd-inline-label">Type:</label>' +
                '<select id="inlineAddKeyType-' + escapeAttr(col) + '" class="dd-input dd-inline-input" onchange="onInlineAddKeyTypeChangeCol(\'' + escapeAttr(col) + '\',this)">' +
                    '<option value="string">String ""</option>' +
                    '<option value="object">Object {}</option>' +
                    '<option value="array">Array []</option>' +
                '</select>' +
            '</div>' +
            '<div class="dd-inline-editor-row" id="inlineAddKeyStringWrap-' + escapeAttr(col) + '">' +
                '<label class="dd-inline-label">Value:</label>' +
                '<input type="text" id="inlineAddKeyString-' + escapeAttr(col) + '" class="dd-input dd-inline-input" placeholder="Enter string value">' +
            '</div>' +
            '<div class="dd-inline-editor-actions">' +
                '<button type="button" class="dd-btn-mini dd-btn-add" onclick="confirmAddKeyCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\')">Add</button>' +
                '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="cancelEditorForCol(\'' + escapeAttr(col) + '\')">Cancel</button>' +
            '</div>' +
        '</div>';
    }

    function onInlineAddKeyTypeChangeCol(col, sel) {
        var wrap = document.getElementById('inlineAddKeyStringWrap-' + col);
        if (wrap) wrap.style.display = (sel.value === 'string') ? 'flex' : 'none';
    }

    function confirmAddKeyCol(col, path) {
        var nameEl = document.getElementById('inlineAddKeyName-' + col);
        var typeEl = document.getElementById('inlineAddKeyType-' + col);
        var keyName = nameEl ? (nameEl.value || '').trim() : '';
        var type = typeEl ? typeEl.value : 'string';
        if (!keyName) { showDdAlert('Key name is required.', 'Error'); return; }
        var obj = resolvePathInCol(col, path);
        if (obj == null || typeof obj !== 'object' || Array.isArray(obj)) { cancelEditorForCol(col); return; }
        if (Object.prototype.hasOwnProperty.call(obj, keyName)) { showDdAlert('Key "' + keyName + '" already exists.', 'Error'); return; }
        var newVal;
        if (type === 'string') {
            var inp = document.getElementById('inlineAddKeyString-' + col);
            newVal = inp ? inp.value : '';
        } else if (type === 'object') newVal = {};
        else newVal = [];
        obj[keyName] = newVal;
        jsonEditorByCol[col] = null;
        renderJsonColumn(col);
    }

    function renderInlineEditKeyEditor(col, path, oldKey) {
        return '<div class="dd-json-key-row">' +
            '<input type="text" id="inlineEditKeyName-' + escapeAttr(col) + '" class="dd-input dd-inline-input" value="' + escapeHtml(oldKey) + '" style="max-width:200px;">' +
            '<button type="button" class="dd-btn-mini dd-btn-add" onclick="confirmEditKeyCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\',\'' + escapeAttr(oldKey) + '\')">Save</button>' +
            '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="cancelEditorForCol(\'' + escapeAttr(col) + '\')">Cancel</button>' +
        '</div>';
    }

    function confirmEditKeyCol(col, path, oldKey) {
        var inp = document.getElementById('inlineEditKeyName-' + col);
        var newKey = inp ? (inp.value || '').trim() : '';
        if (!newKey) { showDdAlert('Key name is required.', 'Error'); return; }
        var obj = resolvePathInCol(col, path);
        if (obj == null || typeof obj !== 'object' || Array.isArray(obj)) { cancelEditorForCol(col); return; }
        if (newKey !== oldKey && Object.prototype.hasOwnProperty.call(obj, newKey)) { showDdAlert('Key "' + newKey + '" already exists.', 'Error'); return; }
        var rebuilt = {};
        Object.keys(obj).forEach(function (k) {
            if (k === oldKey) rebuilt[newKey] = obj[k];
            else rebuilt[k] = obj[k];
        });
        Object.keys(obj).forEach(function (k) { delete obj[k]; });
        Object.keys(rebuilt).forEach(function (k) { obj[k] = rebuilt[k]; });
        jsonEditorByCol[col] = null;
        renderJsonColumn(col);
    }

    function renderInlineEditStringEditor(col, path) {
        var cur = resolvePathInCol(col, path);
        return '<div class="dd-json-leaf dd-json-string">' +
            '<span class="dd-json-str-label">value:</span> ' +
            '<input type="text" id="inlineEditStringValue-' + escapeAttr(col) + '" class="dd-input dd-inline-input" value="' + escapeHtml(cur == null ? '' : String(cur)) + '" style="max-width:260px;">' +
            '<button type="button" class="dd-btn-mini dd-btn-add" onclick="confirmEditStringCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\')">Save</button>' +
            '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="cancelEditorForCol(\'' + escapeAttr(col) + '\')">Cancel</button>' +
        '</div>';
    }

    function confirmEditStringCol(col, path) {
        var inp = document.getElementById('inlineEditStringValue-' + col);
        var v = inp ? inp.value : '';
        setPathInCol(col, path, v);
        jsonEditorByCol[col] = null;
        renderJsonColumn(col);
    }

    function deleteJsonKeyCol(col, path, key) {
        var obj = resolvePathInCol(col, path);
        if (obj && typeof obj === 'object' && !Array.isArray(obj)) {
            delete obj[key];
            renderJsonColumn(col);
        }
    }

    // ==================== PATH HELPERS ====================
    function resolvePathInCol(col, path) {
        var data = jsonDataByCol[col];
        if (path === 'root') return data;
        var parts = path.split('.').slice(1);
        var cur = data;
        for (var i = 0; i < parts.length; i++) {
            if (cur == null) return undefined;
            var p = parts[i];
            if (Array.isArray(cur)) cur = cur[parseInt(p, 10)];
            else if (typeof cur === 'object') cur = cur[p];
            else return undefined;
        }
        return cur;
    }

    function setPathInCol(col, path, value) {
        if (path === 'root') { jsonDataByCol[col] = value; return; }
        var parts = path.split('.');
        var key = parts.pop();
        var parent = resolvePathInCol(col, parts.join('.'));
        if (parent == null) return;
        if (Array.isArray(parent)) parent[parseInt(key, 10)] = value;
        else parent[key] = value;
    }

    // ==================== INIT ====================
    function initJsonColumns() {
        document.querySelectorAll('.dd-am-json-hidden').forEach(function (h) {
            var col = h.getAttribute('data-col');
            var v = h.value || '';
            if (v === '') {
                jsonDataByCol[col] = null;
                jsonEditorByCol[col] = null;
                jsonRootPickerByCol[col] = false;
                return;
            }
            try { jsonDataByCol[col] = JSON.parse(v); } catch (e) { jsonDataByCol[col] = null; }
            jsonEditorByCol[col] = null;
            jsonRootPickerByCol[col] = false;
        });
        Object.keys(jsonDataByCol).forEach(function (col) {
            if (jsonDataByCol[col] !== null && jsonDataByCol[col] !== undefined) {
                renderJsonColumn(col);
            }
        });
        document.querySelectorAll('.dd-json-fold .dd-json-preview').forEach(function (preview) {
            var col = preview.getAttribute('data-col');
            if (col) ensureFoldable(preview, col);
        });
    }

    // ==================== KEYBOARD ====================
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (jsonViewEditing) { cancelJsonEdit(); return; }
            var jm = document.getElementById('ddJsonViewModal');
            if (jm && jm.classList.contains('active')) { closeJsonView(); return; }
            var am = document.getElementById('ddAlertModal');
            if (am && am.classList.contains('active')) { closeDdAlert(); }
        }
    });

    document.addEventListener('click', function (e) {
        var jm = document.getElementById('ddJsonViewModal');
        if (jm && jm.classList.contains('active') && e.target === jm) closeJsonView();
        var am = document.getElementById('ddAlertModal');
        if (am && am.classList.contains('active') && e.target === am) closeDdAlert();
    });

    document.addEventListener('DOMContentLoaded', function () {
        initJsonColumns();
    });

    // ==================== EXPORTS ====================
    window.saveAccountManagement     = saveAccountManagement;
    window.saveSingleColumn          = saveSingleColumn;
    window.onJsonToggle              = onJsonToggle;
    window.startNewJsonForColumn     = startNewJsonForColumn;
    window.confirmRootTypeCol        = confirmRootTypeCol;
    window.cancelRootPickerCol       = cancelRootPickerCol;
    window.openEditorForCol          = openEditorForCol;
    window.cancelEditorForCol        = cancelEditorForCol;
    window.onInlineAddArrayTypeChangeCol = onInlineAddArrayTypeChangeCol;
    window.confirmAddToArrayCol      = confirmAddToArrayCol;
    window.onInlineAddKeyTypeChangeCol   = onInlineAddKeyTypeChangeCol;
    window.confirmAddKeyCol          = confirmAddKeyCol;
    window.confirmEditKeyCol         = confirmEditKeyCol;
    window.confirmEditStringCol      = confirmEditStringCol;
    window.deleteJsonKeyCol          = deleteJsonKeyCol;
    window.viewJsonDataCol           = viewJsonDataCol;
    window.enableJsonEdit            = enableJsonEdit;
    window.cancelJsonEdit            = cancelJsonEdit;
    window.applyJsonEditAndSave      = applyJsonEditAndSave;
    window.closeJsonView             = closeJsonView;
    window.closeDdAlert              = closeDdAlert;
    window.showToast                 = showToast;
    window.toggleFold                = toggleFold;

    // List-table exports
    window.ddListOpenAdd             = ddListOpenAdd;
    window.ddListOpenEdit            = ddListOpenEdit;
    window.ddListCancelAdd           = ddListCancelAdd;
    window.ddListCancelEdit          = ddListCancelEdit;
    window.ddListSaveAdd             = ddListSaveAdd;
    window.ddListSaveEdit            = ddListSaveEdit;
    window.ddListDelete              = ddListDelete;
    window.ddListReload              = ddListReload;
</script>

</body>
</html>