<?php
    // programme_accountmanagement.php — Account Management (Standalone, shelled)
    // Fully per-programme.
    //
    // No dynamic JSON construction anywhere. Every JSON column that used to
    // exist has become a dedicated programme-scoped table.
    //
    // List tables:
    //   - accountmanagement_trades_breakeven
    //   - accountmanagement_trades_martingale
    //   - accountmanagement_min_balance_risk_distance
    //   - accountmanagement_max_balance_risk_distance
    //   - accountmanagement_balance_default_risk
    //   - accountmanagement_balance_maximum_risk
    //   - accountmanagement_restricted_days
    //   - programme_revenue_target (hierarchical: week -> day -> range)

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
    $ACCOUNT_MGMT_COLUMNS = [
        'skip_orders_close_to_position'                       => ['label' => 'Skip Orders Close To Position',                       'type' => 'bool'],
        'cancel_orders_close_to_position'                     => ['label' => 'Cancel Orders Close To Position',                     'type' => 'bool'],
        'also_restrict_opposite_order_too_close_to_position'  => ['label' => 'Also Restrict Opposite Order Too Close To Position',  'type' => 'bool'],
        'switch_invalid_to_instant_order'                     => ['label' => 'Switch Invalid To Instant Order',                     'type' => 'bool'],
        'enable_order_type_conversion'                        => ['label' => 'Enable Order Type Conversion',                        'type' => 'bool'],
    ];

    // ==================== LIST-TABLE REGISTRY ====================
    $LIST_TABLES = [
        'trades_breakeven' => [
            'table'   => 'accountmanagement_trades_breakeven',
            'label'   => 'Trades Breakeven',
            'columns' => [
                'floating_profit_at_risk_reward' => ['label' => 'Floating Profit At Risk Reward', 'type' => 'rr_decimal'],
                'breakeven_at_risk_reward'       => ['label' => 'Breakeven At Risk Reward',       'type' => 'rr_decimal'],
            ],
        ],
        'trades_martingale' => [
            'table'   => 'accountmanagement_trades_martingale',
            'label'   => 'Trades Martingale',
            'columns' => [
                'from_balance'                              => ['label' => 'From Balance',                              'type' => 'decimal'],
                'to_balance'                                => ['label' => 'To Balance',                                'type' => 'decimal_null'],
                'enable_martingale'                         => ['label' => 'Enable Martingale',                         'type' => 'bool'],
                'martingale_type'                           => ['label' => 'Martingale Type',                           'type' => 'enum', 'options' => ['balance_based','loss_streak'], 'default' => 'balance_based'],
                'martingale_factor'                         => ['label' => 'Martingale Factor',                         'type' => 'enum', 'options' => ['profit_factor','stoploss_factor'], 'default' => ''],
                'martingale_for_position_order_scale'       => ['label' => 'Martingale For Position Order Scale',       'type' => 'bool'],
                'martingale_loss_recovery_adder_percentage' => ['label' => 'Martingale Loss Recovery Adder Percentage', 'type' => 'percent_string'],
                'pre_drawdown_assumption'                   => ['label' => 'Pre-Drawdown Assumption',                   'type' => 'bool'],
                'martingale_pre_scaling'                    => ['label' => 'Martingale Pre Scaling',                    'type' => 'bool'],
                'martingale_linear_scaling'                 => ['label' => 'Martingale Linear Scaling',                 'type' => 'bool'],
                'enable_initial_risk_retention'             => ['label' => 'Enable Initial Risk Retention',             'type' => 'bool'],
                'initial_risk_retention_percentage'         => ['label' => 'Initial Risk Retention Percentage',         'type' => 'percent_string'],
                'martingale_per_stage_drawdown_amount'      => ['label' => 'Martingale Per Stage Drawdown Amount',      'type' => 'decimal'],
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
                'day'       => ['label' => 'Day',  'type' => 'day'],
                'from_time' => ['label' => 'From', 'type' => 'time'],
                'to_time'   => ['label' => 'To',   'type' => 'time'],
            ],
        ],
    ];

    $REVENUE_TARGET_TABLE = 'programme_revenue_target';

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

    function fetchListRows(PDO $pdo, string $table, int $developerId, int $programmeId): array {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE developerid = ? AND programme_id = ? ORDER BY id ASC");
            $stmt->execute([$developerId, $programmeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function normalizePercentString($v): string {
        $v = trim((string)$v);
        if ($v === '') return '0%';
        $v = rtrim($v, "% \t\n\r\0\x0B");
        if ($v === '' || !is_numeric($v)) return '0%';
        return $v . '%';
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
            $cleanVal = ($meta['type'] === 'bool') ? (((int)$val) ? 1 : 0) : (string)$val;

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

        $raw = $_POST['fields'] ?? '{}';
        $fields = json_decode($raw, true);
        if (!is_array($fields)) { echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit; }

        $allowed = array_keys($ACCOUNT_MGMT_COLUMNS);
        $clean = [];
        foreach ($fields as $col => $val) {
            if (!in_array($col, $allowed, true)) continue;
            $meta = $ACCOUNT_MGMT_COLUMNS[$col];
            $clean[$col] = ($meta['type'] === 'bool') ? (((int)$val) ? 1 : 0) : (string)$val;
        }
        if (empty($clean)) { echo json_encode(['success' => false, 'message' => 'No fields to save.']); exit; }

        try {
            $pdo->beginTransaction();
            ensureAccountManagementRow($pdo, $userId, $programmeId, 0);
            $setParts = []; $vals = [];
            foreach ($clean as $col => $val) { $setParts[] = "`$col` = ?"; $vals[] = $val; }
            $vals[] = $userId; $vals[] = $programmeId;
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

        // ---------- PROGRAMME REVENUE TARGET ----------
        if ($listKey === 'programme_revenue_target') {
            $table = $REVENUE_TARGET_TABLE;

            $rtCols = [
                'target_daily_profit'         => ['type' => 'bool'],
                'include_missed_days'         => ['type' => 'bool'],
                'include_current_day_as_owed' => ['type' => 'bool'],
                'loss_streak_chances'         => ['type' => 'int'],
                'week_no'                     => ['type' => 'int'],
                'day_name'                    => ['type' => 'day'],
                'pre_pending_sum'             => ['type' => 'bool'],
                'from_balance'                => ['type' => 'decimal'],
                'to_balance'                  => ['type' => 'decimal_null'],
                'risk_amount'                 => ['type' => 'decimal'],
            ];

            if ($listAction === 'list') {
                try {
                    $stmt = $pdo->prepare("
                        SELECT * FROM `$table`
                        WHERE developerid = ? AND programme_id = ?
                        ORDER BY week_no ASC,
                                 FIELD(day_name,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                                 from_balance ASC,
                                 id ASC
                    ");
                    $stmt->execute([$userId, $programmeId]);
                    echo json_encode(['success' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Failed to load: ' . $e->getMessage()]);
                }
                exit;
            }

            if ($listAction === 'add') {
                $rowRaw = $_POST['row'] ?? '';
                $row = is_string($rowRaw) ? json_decode($rowRaw, true) : (is_array($rowRaw) ? $rowRaw : []);
                if (!is_array($row) || empty($row)) { echo json_encode(['success' => false, 'message' => 'Invalid row data.']); exit; }

                $insertCols = []; $insertVals = [];
                foreach ($rtCols as $colName => $colMeta) {
                    $v = $row[$colName] ?? null;
                    if ($colMeta['type'] === 'bool') { $v = ((int)$v) ? 1 : 0; }
                    elseif ($colMeta['type'] === 'int') { $v = (int)$v; }
                    elseif ($colMeta['type'] === 'decimal') { $v = ($v === '' || $v === null) ? 0.0 : (float)$v; }
                    elseif ($colMeta['type'] === 'decimal_null') { $v = ($v === '' || $v === null) ? null : (float)$v; }
                    elseif ($colMeta['type'] === 'day') {
                        $v = trim((string)$v);
                        $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        if (!in_array($v, $validDays, true)) { echo json_encode(['success' => false, 'message' => 'Invalid day.']); exit; }
                    } else { $v = (string)$v; }
                    $insertCols[] = $colName; $insertVals[] = $v;
                }
                $colSql   = '`' . implode('`,`', $insertCols) . '`';
                $placeSql = implode(',', array_fill(0, count($insertCols), '?'));
                $sql = "INSERT INTO `$table` (`developerid`,`programme_id`,$colSql) VALUES (?,?,$placeSql)";
                try {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge([$userId, $programmeId], $insertVals));
                    echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => 'Row added.']);
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000) echo json_encode(['success' => false, 'message' => 'A row with that week, day and from-balance already exists.']);
                    else echo json_encode(['success' => false, 'message' => 'Failed to add: ' . $e->getMessage()]);
                }
                exit;
            }

            if ($listAction === 'update') {
                $rowId  = (int)($_POST['row_id'] ?? 0);
                $rowRaw = $_POST['row'] ?? '';
                $row = is_string($rowRaw) ? json_decode($rowRaw, true) : (is_array($rowRaw) ? $rowRaw : []);
                if ($rowId <= 0 || !is_array($row) || empty($row)) { echo json_encode(['success' => false, 'message' => 'Invalid row.']); exit; }

                $setParts = []; $setVals = [];
                foreach ($rtCols as $colName => $colMeta) {
                    if (!array_key_exists($colName, $row)) continue;
                    $v = $row[$colName];
                    if ($colMeta['type'] === 'bool') { $v = ((int)$v) ? 1 : 0; }
                    elseif ($colMeta['type'] === 'int') { $v = (int)$v; }
                    elseif ($colMeta['type'] === 'decimal') { $v = ($v === '' || $v === null) ? 0.0 : (float)$v; }
                    elseif ($colMeta['type'] === 'decimal_null') { $v = ($v === '' || $v === null) ? null : (float)$v; }
                    elseif ($colMeta['type'] === 'day') {
                        $v = trim((string)$v);
                        $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                        if (!in_array($v, $validDays, true)) { echo json_encode(['success' => false, 'message' => 'Invalid day.']); exit; }
                    } else { $v = (string)$v; }
                    $setParts[] = "`$colName` = ?"; $setVals[] = $v;
                }
                if (empty($setParts)) { echo json_encode(['success' => false, 'message' => 'Nothing to update.']); exit; }
                $setVals[] = $rowId; $setVals[] = $userId; $setVals[] = $programmeId;
                $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE id = ? AND developerid = ? AND programme_id = ?";
                try {
                    $stmt = $pdo->prepare($sql); $stmt->execute($setVals);
                    echo json_encode(['success' => true, 'message' => 'Row updated.']);
                } catch (PDOException $e) {
                    if ((int)$e->getCode() === 23000) echo json_encode(['success' => false, 'message' => 'A row with that week, day and from-balance already exists.']);
                    else echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()]);
                }
                exit;
            }

            if ($listAction === 'delete') {
                $rowId = (int)($_POST['row_id'] ?? 0);
                if ($rowId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid row.']); exit; }
                try {
                    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ? AND developerid = ? AND programme_id = ?");
                    $stmt->execute([$rowId, $userId, $programmeId]);
                    echo json_encode(['success' => true, 'message' => 'Row deleted.']);
                } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $e->getMessage()]); }
                exit;
            }

            if ($listAction === 'delete_week') {
                $weekNo = (int)($_POST['week_no'] ?? 0);
                if ($weekNo <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid week.']); exit; }
                try {
                    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE developerid = ? AND programme_id = ? AND week_no = ?");
                    $stmt->execute([$userId, $programmeId, $weekNo]);
                    echo json_encode(['success' => true, 'message' => 'Week deleted.']);
                } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete week: ' . $e->getMessage()]); }
                exit;
            }

            if ($listAction === 'delete_day') {
                $weekNo  = (int)($_POST['week_no'] ?? 0);
                $dayName = trim((string)($_POST['day_name'] ?? ''));
                if ($weekNo <= 0 || $dayName === '') { echo json_encode(['success' => false, 'message' => 'Invalid day.']); exit; }
                try {
                    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE developerid = ? AND programme_id = ? AND week_no = ? AND day_name = ?");
                    $stmt->execute([$userId, $programmeId, $weekNo, $dayName]);
                    echo json_encode(['success' => true, 'message' => 'Day deleted.']);
                } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete day: ' . $e->getMessage()]); }
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'Unknown revenue target action.']);
            exit;
        }

        // ---------- SIMPLE LIST TABLES ----------
        if (!array_key_exists($listKey, $LIST_TABLES)) {
            echo json_encode(['success' => false, 'message' => 'Unknown list table.']);
            exit;
        }

        $def   = $LIST_TABLES[$listKey];
        $table = $def['table'];
        $cols  = $def['columns'];

        if ($listAction === 'list') {
            echo json_encode(['success' => true, 'rows' => fetchListRows($pdo, $table, $userId, $programmeId)]);
            exit;
        }

        if ($listAction === 'add') {
            $rowRaw = $_POST['row'] ?? '';
            $row = is_string($rowRaw) ? json_decode($rowRaw, true) : (is_array($rowRaw) ? $rowRaw : []);
            if (!is_array($row) || empty($row)) { echo json_encode(['success' => false, 'message' => 'Invalid row data.']); exit; }

            $insertCols = []; $insertVals = [];
            foreach ($cols as $colName => $colMeta) {
                $v = $row[$colName] ?? null;
                if ($colMeta['type'] === 'bool') { $v = ((int)$v) ? 1 : 0; }
                elseif ($colMeta['type'] === 'decimal' || $colMeta['type'] === 'rr_decimal') { $v = ($v === '' || $v === null) ? 0.0 : (float)$v; }
                elseif ($colMeta['type'] === 'decimal_null') { $v = ($v === '' || $v === null) ? null : (float)$v; }
                elseif ($colMeta['type'] === 'percent_string') { $v = normalizePercentString($v); }
                elseif ($colMeta['type'] === 'enum') {
                    $v = trim((string)$v);
                    $allowed = $colMeta['options'] ?? [];
                    $defVal  = $colMeta['default'] ?? '';
                    if ($v === '' && $defVal !== '') $v = $defVal;
                    if (!in_array($v, $allowed, true)) { echo json_encode(['success' => false, 'message' => 'Invalid value for ' . $colMeta['label'] . '.']); exit; }
                } elseif ($colMeta['type'] === 'day') {
                    $v = trim((string)$v);
                    $validDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    if (!in_array($v, $validDays, true)) { echo json_encode(['success' => false, 'message' => 'Invalid day.']); exit; }
                } elseif ($colMeta['type'] === 'time') {
                    $v = trim((string)$v);
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v)) { echo json_encode(['success' => false, 'message' => 'Invalid time format for ' . $colMeta['label'] . '.']); exit; }
                    if (strlen($v) === 5) $v .= ':00';
                } else { $v = (string)$v; }
                $insertCols[] = $colName; $insertVals[] = $v;
            }

            if ($listKey === 'restricted_days') {
                $dayVal = $row['day'] ?? '';
                $chk = $pdo->prepare("SELECT id FROM `$table` WHERE developerid = ? AND programme_id = ? AND `day` = ? LIMIT 1");
                $chk->execute([$userId, $programmeId, $dayVal]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) { echo json_encode(['success' => false, 'message' => 'That day is already restricted for this programme.']); exit; }
            }

            $colSql   = '`' . implode('`,`', $insertCols) . '`';
            $placeSql = implode(',', array_fill(0, count($insertCols), '?'));
            $sql = "INSERT INTO `$table` (`developerid`,`programme_id`,$colSql) VALUES (?,?,$placeSql)";
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_merge([$userId, $programmeId], $insertVals));
                echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => 'Row added.']);
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) echo json_encode(['success' => false, 'message' => 'A row with the same identifier already exists for this programme.']);
                else echo json_encode(['success' => false, 'message' => 'Failed to add: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($listAction === 'update') {
            $rowId  = (int)($_POST['row_id'] ?? 0);
            $rowRaw = $_POST['row'] ?? '';
            $row = is_string($rowRaw) ? json_decode($rowRaw, true) : (is_array($rowRaw) ? $rowRaw : []);
            if ($rowId <= 0 || !is_array($row) || empty($row)) { echo json_encode(['success' => false, 'message' => 'Invalid row.']); exit; }

            $setParts = []; $setVals = [];
            foreach ($cols as $colName => $colMeta) {
                if (!array_key_exists($colName, $row)) continue;
                $v = $row[$colName];
                if ($colMeta['type'] === 'bool') { $v = ((int)$v) ? 1 : 0; }
                elseif ($colMeta['type'] === 'decimal' || $colMeta['type'] === 'rr_decimal') { $v = ($v === '' || $v === null) ? 0.0 : (float)$v; }
                elseif ($colMeta['type'] === 'decimal_null') { $v = ($v === '' || $v === null) ? null : (float)$v; }
                elseif ($colMeta['type'] === 'percent_string') { $v = normalizePercentString($v); }
                elseif ($colMeta['type'] === 'enum') {
                    $v = trim((string)$v);
                    $allowed = $colMeta['options'] ?? [];
                    $defVal  = $colMeta['default'] ?? '';
                    if ($v === '' && $defVal !== '') $v = $defVal;
                    if (!in_array($v, $allowed, true)) { echo json_encode(['success' => false, 'message' => 'Invalid value for ' . $colMeta['label'] . '.']); exit; }
                } elseif ($colMeta['type'] === 'day') {
                    $v = trim((string)$v);
                    $validDays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    if (!in_array($v, $validDays, true)) { echo json_encode(['success' => false, 'message' => 'Invalid day.']); exit; }
                } elseif ($colMeta['type'] === 'time') {
                    $v = trim((string)$v);
                    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v)) { echo json_encode(['success' => false, 'message' => 'Invalid time format for ' . $colMeta['label'] . '.']); exit; }
                    if (strlen($v) === 5) $v .= ':00';
                } else { $v = (string)$v; }
                $setParts[] = "`$colName` = ?"; $setVals[] = $v;
            }

            if (empty($setParts)) { echo json_encode(['success' => false, 'message' => 'Nothing to update.']); exit; }

            if ($listKey === 'restricted_days' && isset($row['day'])) {
                $chk = $pdo->prepare("SELECT id FROM `$table` WHERE developerid = ? AND programme_id = ? AND `day` = ? AND id <> ? LIMIT 1");
                $chk->execute([$userId, $programmeId, $row['day'], $rowId]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) { echo json_encode(['success' => false, 'message' => 'That day is already restricted for this programme.']); exit; }
            }

            $setVals[] = $rowId; $setVals[] = $userId; $setVals[] = $programmeId;
            $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE id = ? AND developerid = ? AND programme_id = ?";
            try {
                $stmt = $pdo->prepare($sql); $stmt->execute($setVals);
                echo json_encode(['success' => true, 'message' => 'Row updated.']);
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) echo json_encode(['success' => false, 'message' => 'A row with the same identifier already exists for this programme.']);
                else echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()]);
            }
            exit;
        }

        if ($listAction === 'delete') {
            $rowId = (int)($_POST['row_id'] ?? 0);
            if ($rowId <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid row.']); exit; }
            try {
                $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ? AND developerid = ? AND programme_id = ?");
                $stmt->execute([$rowId, $userId, $programmeId]);
                echo json_encode(['success' => true, 'message' => 'Row deleted.']);
            } catch (PDOException $e) { echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $e->getMessage()]); }
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

    // ==================== FETCH REVENUE TARGET ROWS ====================
    $revenueTargetRows = [];
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `$REVENUE_TARGET_TABLE`
            WHERE developerid = ? AND programme_id = ?
            ORDER BY week_no ASC,
                     FIELD(day_name,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                     from_balance ASC,
                     id ASC
        ");
        $stmt->execute([$userId, $programmeId]);
        $revenueTargetRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $revenueTargetRows = []; }

    $revenueGrouped = [];
    foreach ($revenueTargetRows as $r) {
        $w = (int)$r['week_no'];
        $d = (string)$r['day_name'];
        if (!isset($revenueGrouped[$w])) $revenueGrouped[$w] = [];
        if (!isset($revenueGrouped[$w][$d])) $revenueGrouped[$w][$d] = [];
        $revenueGrouped[$w][$d][] = $r;
    }
    ksort($revenueGrouped);
    $revenueWeeks = array_keys($revenueGrouped);
    sort($revenueWeeks);

    $programmeName = $programme['program_name'] ?? 'Unnamed Programme';

    // ==================== SAFE JSON PAYLOADS ====================
    $JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    $jsonAccountMgmtColumns = json_encode($ACCOUNT_MGMT_COLUMNS, $JSON_FLAGS);
    $jsonListTables         = json_encode($LIST_TABLES,         $JSON_FLAGS);
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
        --spr-success: var(--success, #2ecc71);
        --spr-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06));
        --spr-radius: var(--radius, 16px);

        --bg: var(--spr-bg); --bg-card: var(--spr-card); --text: var(--spr-text);
        --text-muted: var(--spr-muted); --accent: var(--spr-accent);
        --accent-hover: var(--spr-accent-2); --accent-light: var(--spr-soft);
        --border-color: var(--spr-border); --danger: var(--spr-danger);
        --success: var(--spr-success); --radius: var(--spr-radius); --radius-sm: 10px;
    }
    body.dark-mode {
        --spr-bg: #0c1311; --spr-card: #131d1a; --spr-text: #e8f2ee;
        --spr-muted: #93a8a1; --spr-soft: #12271f; --spr-border: #22322d;
        --spr-shadow: 0 10px 30px rgba(0,0,0,.4);
    }
    button, a, input, select, textarea, label { touch-action: manipulation; }
    html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
    @media (max-width: 768px) {
        input, select, textarea,
        .dd-input, .dd-select, .dd-am-input, .dd-time-input, .dd-time-select, .dd-rr-input {
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
    .dd-page-wrapper { position: relative; width: 100%; height: 100%; overflow: hidden; background: var(--spr-bg); color: var(--spr-text); }
    .dd-sticky-top { position: absolute; top: 0; left: 0; right: 0; background: var(--spr-bg); z-index: 10; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    body.dark-mode .dd-sticky-top { background: var(--spr-bg); box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
    .dd-topbar { position: relative; display: flex; align-items: center; justify-content: center; min-height: 56px; padding: 10px 16px; box-sizing: border-box; border-bottom: 1px solid var(--spr-border); }
    body.dark-mode .dd-topbar { border-bottom-color: var(--spr-border); }
    .dd-topbar-back { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--spr-text); font-size: 1.2rem; cursor: pointer; padding: 8px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s ease; text-decoration: none; }
    .dd-topbar-back:hover { background: rgba(0,0,0,0.06); }
    body.dark-mode .dd-topbar-back { color: var(--spr-text); }
    body.dark-mode .dd-topbar-back:hover { background: rgba(255,255,255,0.08); }
    .dd-topbar-title { font-size: 1.15rem; font-weight: 800; color: var(--spr-text); margin: 0; letter-spacing: -0.3px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 120px); line-height: 1.2; }
    .dd-scroll-area { position: absolute; inset: 0; overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; padding: 80px 20px 24px; box-sizing: border-box; }
    @media (max-width: 480px) { .dd-scroll-area { padding: 72px 12px 20px; } }
    .dd-notice { padding: 12px 16px; border-radius: var(--radius-sm, 10px); font-size: 0.85rem; line-height: 1.5; margin-bottom: 16px; }
    .dd-notice-info { background: var(--spr-soft); border: 1px solid var(--spr-border); color: var(--spr-text); }
    .dd-card { background: var(--spr-card); border: 1px solid var(--spr-border); border-radius: var(--spr-radius); padding: 20px; margin-bottom: 20px; box-shadow: var(--spr-shadow); }
    body.dark-mode .dd-card { background: var(--spr-card); border-color: var(--spr-border); }
    .dd-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .dd-card-title { font-size: 1.15rem; font-weight: 800; color: var(--spr-text); margin: 0 0 4px 0; }
    .dd-card-subtitle { font-size: 0.82rem; color: var(--spr-muted); margin: 0; line-height: 1.5; }
    .dd-am-note { background: var(--spr-soft); border-left: 3px solid var(--spr-accent); padding: 10px 14px; border-radius: 0 var(--radius-sm, 10px) var(--radius-sm, 10px) 0; font-size: 0.8rem; line-height: 1.55; color: var(--spr-text); margin-bottom: 20px; }
    .dd-am-list { display: flex; flex-direction: column; gap: 16px; }
    .dd-am-item { background: var(--spr-bg); border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; transition: border-color 0.2s ease; }
    body.dark-mode .dd-am-item { background: rgba(255,255,255,0.03); border-color: var(--spr-border); }
    .dd-am-item:hover { border-color: var(--spr-accent); }
    .dd-am-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .dd-am-label { font-size: 0.82rem; font-weight: 700; color: var(--spr-text); text-transform: uppercase; letter-spacing: 0.4px; }
    .dd-am-input-wrap { display: flex; flex-direction: column; gap: 8px; }
    .dd-list-editor { background: var(--spr-bg); border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); padding: 16px; display: flex; flex-direction: column; gap: 12px; }
    body.dark-mode .dd-list-editor { background: rgba(255,255,255,0.03); }
    .dd-list-add-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: var(--radius-sm, 10px); background: var(--spr-soft); color: var(--spr-accent); border: 1px dashed var(--spr-accent); font-family: inherit; font-size: 0.82rem; font-weight: 800; cursor: pointer; transition: all 0.15s ease; align-self: flex-start; -webkit-tap-highlight-color: transparent; }
    .dd-list-add-btn:hover { background: var(--spr-accent); color: #fff; border-style: solid; }
    .dd-list-form { background: var(--spr-card); border: 1px solid var(--spr-accent); border-radius: var(--radius-sm, 10px); padding: 14px; display: flex; flex-direction: column; gap: 10px; }
    .dd-list-form-row { display: flex; flex-direction: column; gap: 5px; }
    .dd-list-form-row label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 800; color: var(--spr-muted); }
    .dd-list-form-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
    .dd-list-rows { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
    .dd-list-row { background: var(--spr-card); border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
    body.dark-mode .dd-list-row { background: var(--spr-card); border-color: var(--spr-border); }
    .dd-list-row:hover { border-color: var(--spr-accent); }
    .dd-list-row-display { display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center; }
    .dd-list-row-display .dd-row-field { display: flex; flex-direction: column; gap: 2px; min-width: 90px; }
    .dd-list-row-display .dd-row-field-label { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; color: var(--spr-muted); }
    .dd-list-row-display .dd-row-field-value { font-size: 0.92rem; font-weight: 800; color: var(--spr-text); word-break: break-word; }
    .dd-list-row-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .dd-list-row-empty { padding: 20px 14px; text-align: center; color: var(--spr-muted); font-size: 0.82rem; font-style: italic; border: 1px dashed var(--spr-border); border-radius: var(--radius-sm, 10px); }
    .dd-time-group { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .dd-time-input { width: 60px; text-align: center; padding: 8px 6px !important; border: 1px solid var(--spr-border); border-radius: 8px; background: var(--spr-card); color: var(--spr-text); font-family: inherit; font-weight: 700; -webkit-appearance: none; appearance: none; }
    .dd-time-input:focus { outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 2px var(--spr-soft); }
    .dd-time-sep { font-weight: 800; color: var(--spr-muted); font-size: 1rem; }
    .dd-time-select { padding: 8px 10px !important; border: 1px solid var(--spr-border); border-radius: 8px; background: var(--spr-card); color: var(--spr-text); font-family: inherit; font-weight: 700; cursor: pointer; -webkit-appearance: none; appearance: none; }
    .dd-time-select:focus { outline: none; border-color: var(--spr-accent); }
    .dd-rr-wrap { display: flex; align-items: center; gap: 0; border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); background: var(--spr-card); padding: 0 10px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
    .dd-rr-wrap:focus-within { border-color: var(--spr-accent); box-shadow: 0 0 0 3px var(--spr-soft); }
    .dd-rr-prefix { font-weight: 800; color: var(--spr-muted); padding-right: 4px; user-select: none; font-size: 16px !important; line-height: 1; }
    .dd-rr-input { flex: 1; border: none; outline: none; background: transparent; color: var(--spr-text); font-family: inherit; font-weight: 700; font-size: 16px !important; padding: 10px 0; min-width: 0; -webkit-appearance: none; appearance: none; }
    .dd-rt-wrap { display: flex; flex-direction: column; gap: 16px; }
    .dd-rt-week { background: var(--spr-bg); border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); padding: 14px; display: flex; flex-direction: column; gap: 12px; }
    .dd-rt-week-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .dd-rt-week-title { font-size: 0.95rem; font-weight: 800; color: var(--spr-text); display: flex; align-items: center; gap: 8px; }
    .dd-rt-week-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .dd-rt-days { display: flex; flex-direction: column; gap: 10px; }
    .dd-rt-day { background: var(--spr-card); border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); padding: 12px; display: flex; flex-direction: column; gap: 10px; }
    .dd-rt-day-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .dd-rt-day-title { font-size: 0.85rem; font-weight: 800; color: var(--spr-text); display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.4px; }
    .dd-rt-ranges { display: flex; flex-direction: column; gap: 8px; }
    .dd-rt-range { background: var(--spr-bg); border: 1px solid var(--spr-border); border-radius: 8px; padding: 10px 12px; display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center; justify-content: space-between; }
    .dd-rt-range-fields { display: flex; flex-wrap: wrap; gap: 10px 20px; align-items: center; }
    .dd-rt-range-fields .dd-row-field { display: flex; flex-direction: column; gap: 2px; min-width: 90px; }
    .dd-rt-range-fields .dd-row-field-label { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; color: var(--spr-muted); }
    .dd-rt-range-fields .dd-row-field-value { font-size: 0.88rem; font-weight: 800; color: var(--spr-text); }
    .dd-rt-range-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .dd-rt-empty { padding: 16px 14px; text-align: center; color: var(--spr-muted); font-size: 0.8rem; font-style: italic; border: 1px dashed var(--spr-border); border-radius: var(--radius-sm, 10px); }
    .dd-rt-weeks-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
    .dd-rt-week-tab { padding: 6px 12px; border-radius: 999px; background: var(--spr-bg); border: 1px solid var(--spr-border); color: var(--spr-text); font-family: inherit; font-size: 0.75rem; font-weight: 800; cursor: pointer; transition: all 0.15s ease; }
    .dd-rt-week-tab:hover { border-color: var(--spr-accent); }
    .dd-rt-week-tab.active { background: var(--spr-accent); color: #fff; border-color: var(--spr-accent); }
    .dd-rt-new-week { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 12px; border: 1px dashed var(--spr-border); border-radius: var(--radius-sm, 10px); background: var(--spr-bg); }
    .dd-rt-new-week label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.4px; color: var(--spr-muted); }
    .dd-rt-new-week input { width: 90px; }
    .dd-input { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid var(--spr-border); border-radius: var(--radius-sm, 10px); background: var(--spr-card); color: var(--spr-text); font-size: 16px !important; font-family: inherit; transition: border-color 0.2s ease, box-shadow 0.2s ease; -webkit-appearance: none; appearance: none; }
    .dd-input:focus { outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 3px var(--spr-soft); }
    .dd-btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 24px; border-radius: var(--radius-sm, 10px); font-family: inherit; font-size: 0.88rem; font-weight: 800; cursor: pointer; border: none; background: var(--spr-accent); color: #fff; width: 100%; margin-top: 8px; }
    .dd-btn-primary:hover { background: var(--spr-accent-2); }
    .dd-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .dd-btn-mini { padding: 7px 14px; border-radius: 8px; font-family: inherit; font-size: 0.75rem; font-weight: 800; cursor: pointer; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 5px; }
    .dd-btn-save { background: var(--spr-accent); color: #fff; border-color: var(--spr-accent); }
    .dd-btn-cancel { background: transparent; color: var(--spr-muted); border-color: var(--spr-border); }
    .dd-btn-edit { background: var(--spr-soft); color: var(--spr-accent); border-color: var(--spr-border); }
    .dd-btn-danger { background: rgba(231,76,60,0.1); color: #e74c3c; border-color: rgba(231,76,60,0.3); }
    .dd-save-toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px); background: var(--spr-success); color: #fff; font-family: inherit; font-size: 0.85rem; font-weight: 700; padding: 12px 26px; border-radius: 24px; opacity: 0; transition: opacity 0.3s ease, transform 0.3s ease; z-index: 999999; pointer-events: none; max-width: 88vw; text-align: center; }
    .dd-save-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    .dd-save-toast.error { background: var(--spr-danger); }
    .dd-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.58); z-index: 9999; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box; }
    .dd-modal.active { display: flex; }
    .dd-modal-content { background: var(--spr-card); border: 1px solid var(--spr-border); border-radius: var(--spr-radius); padding: 24px; max-width: 420px; width: 100%; max-height: 85vh; overflow-y: auto; }
    .dd-modal-title { font-size: 1.15rem; font-weight: 800; color: var(--spr-accent); margin: 0 0 16px 0; text-align: center; }
    .dd-modal-body { margin-bottom: 16px; }
    .dd-modal-text { font-size: 0.95rem; line-height: 1.6; color: var(--spr-text); margin: 0; text-align: center; }
    .dd-modal-actions { display: flex; flex-direction: column; gap: 10px; }
    @media (max-width: 480px) {
        .dd-topbar { min-height: 52px; padding: 8px 12px; }
        .dd-topbar-back { left: 12px; width: 34px; height: 34px; font-size: 1.05rem; }
        .dd-topbar-title { font-size: 1.05rem; max-width: calc(100% - 100px); }
        .dd-card { padding: 14px 12px; }
        .dd-card-head { flex-direction: column; }
        .dd-list-editor { padding: 12px; }
        .dd-list-form { padding: 12px; }
        .dd-time-input { width: 52px; }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> dd-page-body">

<div class="dd-page-wrapper">

    <div class="dd-sticky-top">
        <div class="dd-topbar">
            <button type="button" class="dd-topbar-back" onclick="ddGoBack()" aria-label="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h1 class="dd-topbar-title">Account Management</h1>
        </div>
    </div>

    <div class="dd-scroll-area">

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
                <strong>Note:</strong> Any change here will be applied to all investors under you for
                <strong><?= htmlspecialchars($programmeName) ?></strong>. Every setting is a direct input or a dedicated table.
            </div>

            <div class="dd-am-list">
                <?php foreach ($ACCOUNT_MGMT_COLUMNS as $col => $meta):
                    $val = $accountMgmt[$col] ?? null;
                ?>
                    <div class="dd-am-item" data-col="<?= htmlspecialchars($col) ?>">
                        <div class="dd-am-head">
                            <label class="dd-am-label"><?= htmlspecialchars($meta['label']) ?></label>
                        </div>
                        <div class="dd-am-input-wrap">
                            <?php if ($meta['type'] === 'bool'): ?>
                                <select class="dd-input dd-am-input dd-select" data-col="<?= htmlspecialchars($col) ?>" data-type="bool">
                                    <option value="0" <?= ((int)$val === 0) ? 'selected' : '' ?>>Disabled</option>
                                    <option value="1" <?= ((int)$val === 1) ? 'selected' : '' ?>>Enabled</option>
                                </select>
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
             PROGRAMME REVENUE TARGET
             ============================================================ -->
        <section class="dd-card" id="dd-rt-section">
            <div class="dd-card-head">
                <div>
                    <h2 class="dd-card-title">Programme Revenue Target</h2>
                    <p class="dd-card-subtitle">
                        Independent weeks. Each week can have any subset of days.
                        Each day can have as many balance-range risk rows as you need.
                    </p>
                </div>
            </div>

            <?php if (!empty($revenueWeeks)): ?>
                <div class="dd-rt-weeks-tabs" id="ddRtWeekTabs">
                    <?php foreach ($revenueWeeks as $w): ?>
                        <button type="button"
                                class="dd-rt-week-tab <?= $w === $revenueWeeks[0] ? 'active' : '' ?>"
                                data-week="<?= (int)$w ?>"
                                onclick="ddRtSelectWeek(<?= (int)$w ?>)">
                            Week <?= (int)$w ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="dd-rt-new-week">
                <label>Add a new week</label>
                <input type="number" min="1" max="255" class="dd-input" id="ddRtNewWeekInput" value="<?= empty($revenueWeeks) ? 1 : (max($revenueWeeks) + 1) ?>">
                <button type="button" class="dd-list-add-btn" onclick="ddRtCreateWeek()">
                    <i class="fa-solid fa-plus"></i> Create Week
                </button>
            </div>

            <div class="dd-rt-wrap" id="ddRtWeeksContainer">
                <?php if (empty($revenueWeeks)): ?>
                    <div class="dd-rt-empty">
                        No weeks yet. Create a week above to start adding days and balance ranges.
                    </div>
                <?php else: ?>
                    <?php foreach ($revenueWeeks as $wIndex => $w): ?>
                        <div class="dd-rt-week"
                             data-week="<?= (int)$w ?>"
                             style="<?= $wIndex === 0 ? '' : 'display:none;' ?>"
                             id="ddRtWeek-<?= (int)$w ?>">

                            <div class="dd-rt-week-head">
                                <div class="dd-rt-week-title">
                                    <i class="fa-solid fa-calendar-week"></i>
                                    Week <?= (int)$w ?>
                                </div>
                                <div class="dd-rt-week-actions">
                                    <button type="button" class="dd-list-add-btn" style="padding:8px 14px;font-size:0.75rem;"
                                            onclick="ddRtOpenAddDay(<?= (int)$w ?>)">
                                        <i class="fa-solid fa-plus"></i> Add Day
                                    </button>
                                    <button type="button" class="dd-btn-mini dd-btn-danger"
                                            onclick="ddRtDeleteWeek(<?= (int)$w ?>)">
                                        <i class="fa-solid fa-trash"></i> Delete Week
                                    </button>
                                </div>
                            </div>

                            <div id="ddRtAddDay-<?= (int)$w ?>"></div>

                            <div class="dd-rt-days" id="ddRtDays-<?= (int)$w ?>">
                                <?php
                                    $daysOrder = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                                    $presentDays = isset($revenueGrouped[$w]) ? array_keys($revenueGrouped[$w]) : [];
                                    usort($presentDays, function($a, $b) use ($daysOrder) {
                                        return array_search($a, $daysOrder, true) <=> array_search($b, $daysOrder, true);
                                    });
                                ?>
                                <?php if (empty($presentDays)): ?>
                                    <div class="dd-rt-empty">No days in this week yet. Add a day above.</div>
                                <?php else: ?>
                                    <?php foreach ($presentDays as $d): ?>
                                        <div class="dd-rt-day" data-week="<?= (int)$w ?>" data-day="<?= htmlspecialchars($d) ?>">
                                            <div class="dd-rt-day-head">
                                                <div class="dd-rt-day-title">
                                                    <i class="fa-solid fa-calendar-day"></i>
                                                    <?= htmlspecialchars($d) ?>
                                                </div>
                                                <div class="dd-rt-day-actions">
                                                    <button type="button" class="dd-btn-mini dd-btn-edit"
                                                            onclick="ddRtOpenAddRange(<?= (int)$w ?>, '<?= htmlspecialchars($d) ?>')">
                                                        <i class="fa-solid fa-plus"></i> Add Range
                                                    </button>
                                                    <button type="button" class="dd-btn-mini dd-btn-danger"
                                                            onclick="ddRtDeleteDay(<?= (int)$w ?>, '<?= htmlspecialchars($d) ?>')">
                                                        <i class="fa-solid fa-trash"></i> Delete Day
                                                    </button>
                                                </div>
                                            </div>

                                            <div id="ddRtAddRange-<?= (int)$w ?>-<?= htmlspecialchars($d) ?>"></div>

                                            <div class="dd-rt-ranges" id="ddRtRanges-<?= (int)$w ?>-<?= htmlspecialchars($d) ?>">
                                                <?php foreach ($revenueGrouped[$w][$d] as $r):
                                                    $id = (int)$r['id'];
                                                    $from = (float)$r['from_balance'];
                                                    $to   = $r['to_balance'] === null ? null : (float)$r['to_balance'];
                                                    $risk = (float)$r['risk_amount'];
                                                    $pre  = (int)$r['pre_pending_sum'];
                                                ?>
                                                    <div class="dd-rt-range" data-range-id="<?= $id ?>">
                                                        <div class="dd-rt-range-fields">
                                                            <div class="dd-row-field">
                                                                <span class="dd-row-field-label">From</span>
                                                                <span class="dd-row-field-value"><?= htmlspecialchars(number_format($from, 2, '.', '')) ?></span>
                                                            </div>
                                                            <div class="dd-row-field">
                                                                <span class="dd-row-field-label">To</span>
                                                                <span class="dd-row-field-value"><?= $to === null ? '∞' : htmlspecialchars(number_format($to, 2, '.', '')) ?></span>
                                                            </div>
                                                            <div class="dd-row-field">
                                                                <span class="dd-row-field-label">Risk Amount</span>
                                                                <span class="dd-row-field-value"><?= htmlspecialchars(number_format($risk, 2, '.', '')) ?></span>
                                                            </div>
                                                            <div class="dd-row-field">
                                                                <span class="dd-row-field-label">Pre-Pending Sum</span>
                                                                <span class="dd-row-field-value"><?= $pre ? 'Yes' : 'No' ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="dd-rt-range-actions">
                                                            <button type="button" class="dd-btn-mini dd-btn-edit"
                                                                    onclick="ddRtOpenEditRange(<?= $id ?>, <?= (int)$w ?>, '<?= htmlspecialchars($d) ?>')">
                                                                <i class="fa-solid fa-pen"></i> Edit
                                                            </button>
                                                            <button type="button" class="dd-btn-mini dd-btn-danger"
                                                                    onclick="ddRtDeleteRange(<?= $id ?>)">
                                                                <i class="fa-solid fa-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                        <div id="ddRtEditRange-<?= $id ?>"></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <!-- ============================================================
             SIMPLE LIST-TABLE SECTIONS
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
                    <button type="button" class="dd-list-add-btn"
                            onclick="ddListOpenAdd('<?= htmlspecialchars($listKey) ?>')">
                        <i class="fa-solid fa-plus"></i>
                        <?php
                            if ($listKey === 'restricted_days') echo 'Add day to restrict';
                            elseif ($listKey === 'trades_martingale') echo 'Add new balance range';
                            elseif (strpos($listKey, 'balance') !== false || strpos($listKey, 'risk') !== false) echo 'Add new balance range risk';
                            else echo 'Add new row';
                        ?>
                    </button>

                    <div id="dd-list-add-<?= htmlspecialchars($listKey) ?>"></div>

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
                                                        } elseif ($cMeta['type'] === 'rr_decimal') {
                                                            echo '1:' . htmlspecialchars(number_format((float)$v, 2, '.', ''));
                                                        } elseif ($cMeta['type'] === 'decimal_null') {
                                                            echo $v === null || $v === '' ? '∞' : htmlspecialchars(number_format((float)$v, 2, '.', ''));
                                                        } elseif ($cMeta['type'] === 'bool') {
                                                            echo ((int)$v) ? 'Yes' : 'No';
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

<div id="ddSaveToast" class="dd-save-toast"></div>

<!-- ============================================================
     JSON config for JavaScript — separate tags, each on its own
     line, with hex-escaped content so nothing can break the page.
     ============================================================ -->
<script type="application/json" id="ddAmColumnsData"><?= $jsonAccountMgmtColumns ?></script>
<script type="application/json" id="ddListTablesData"><?= $jsonListTables ?></script>

<script>
(function () {
    'use strict';

    // ---------------- Config from JSON script tags ----------------
    var ACCOUNT_MGMT_COLUMNS = {};
    var LIST_TABLES = {};
    try {
        var elAm = document.getElementById('ddAmColumnsData');
        var elLt = document.getElementById('ddListTablesData');
        ACCOUNT_MGMT_COLUMNS = elAm ? JSON.parse(elAm.textContent) : {};
        LIST_TABLES = elLt ? JSON.parse(elLt.textContent) : {};
    } catch (err) {
        console.error('Failed to parse embedded config:', err);
    }

    // ---------------- Back navigation ----------------
    window.ddGoBack = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'menu' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'programme_menu.php';
    };

    // ---------------- Theme sync ----------------
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') {
            document.body.classList.toggle('dark-mode', !!e.data.dark);
        }
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}

    // ---------------- Modal → shell bridge ----------------
    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: open ? 'harvhubModalOpen' : 'harvhubModalClose' }, '*');
            }
        } catch (e) {}
    }

    // ---------------- Helpers ----------------
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
        document.body.dataset.ddModalLocked = '1';
        document.body.style.overflow = 'hidden';
    }
    function unlockBodyScroll() {
        if (document.body.dataset.ddModalLocked !== '1') return;
        document.body.dataset.ddModalLocked = '0';
        document.body.style.overflow = '';
    }

    // ---------------- Toast ----------------
    var _toastTimer = null;
    function showToast(msg, isError) {
        var t = document.getElementById('ddSaveToast');
        if (!t) return;
        t.textContent = msg;
        t.classList.toggle('error', !!isError);
        t.classList.add('show');
        if (_toastTimer) clearTimeout(_toastTimer);
        _toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2200);
    }

    // ---------------- Alert modal ----------------
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

    // ---------------- Account management save ----------------
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
            else fields[col] = inp.value;
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
            if (data && data.success) showToast('All columns saved and synced.');
            else showToast((data && data.message) || 'Failed to save.', true);
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            if (btn2) { btn2.disabled = false; btn2.textContent = 'Save All Account Management'; }
            showToast('Network error. Please try again.', true);
        });
    }

    // ---------------- Simple list CRUD ----------------
    function ddListOpenAdd(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        var c = document.getElementById('dd-list-add-' + listKey);
        if (!c) return;
        if (c.innerHTML.trim() !== '') { c.innerHTML = ''; return; }
        c.innerHTML = ddListBuildFormHtml(listKey, null);
    }

    function ddListOpenEdit(listKey, rowId) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        var rowEl = document.querySelector('#dd-list-rows-' + listKey + ' .dd-list-row[data-row-id="' + rowId + '"]');
        if (!rowEl) return;
        var ec = document.getElementById('dd-list-edit-' + listKey + '-' + rowId);
        if (!ec) return;
        if (ec.innerHTML.trim() !== '') { ec.innerHTML = ''; return; }

        var rowData = {};
        var fields = rowEl.querySelectorAll('.dd-row-field');
        var colKeys = Object.keys(def.columns);
        fields.forEach(function (f, idx) {
            if (idx < colKeys.length) {
                var meta = def.columns[colKeys[idx]];
                var txt = f.querySelector('.dd-row-field-value').textContent.trim();
                if (meta && meta.type === 'rr_decimal' && txt.indexOf('1:') === 0) txt = txt.substring(2);
                rowData[colKeys[idx]] = txt;
            }
        });
        ec.innerHTML = ddListBuildFormHtml(listKey, rowId, rowData);
    }

    function ddListBuildFormHtml(listKey, rowId, rowData) {
        var def = LIST_TABLES[listKey];
        if (!def) return '';
        rowData = rowData || {};
        var html = '<div class="dd-list-form">';

        Object.keys(def.columns).forEach(function (colName) {
            var meta = def.columns[colName];
            var val = rowData[colName] !== undefined ? rowData[colName] : '';
            var id = 'dd-list-field-' + listKey + '-' + colName;

            html += '<div class="dd-list-form-row">';
            html += '<label>' + escapeHtml(meta.label) + '</label>';

            if (meta.type === 'day') {
                var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                html += '<select class="dd-input dd-select" id="' + id + '">';
                html += '<option value="">— Select day —</option>';
                days.forEach(function (d) {
                    html += '<option value="' + d + '"' + (val === d ? ' selected' : '') + '>' + d + '</option>';
                });
                html += '</select>';
            } else if (meta.type === 'time') {
                var hh = '12', mm = '00', ap = 'AM';
                if (val && /^\d{1,2}:\d{2}/.test(val)) {
                    var parts = val.split(':');
                    var h24 = parseInt(parts[0], 10);
                    mm = parts[1] || '00';
                    ap = h24 >= 12 ? 'PM' : 'AM';
                    hh = h24 % 12; if (hh === 0) hh = 12;
                    hh = ('0' + hh).slice(-2);
                }
                html += '<div class="dd-time-group">';
                html += '<input type="number" min="1" max="12" class="dd-time-input" id="' + id + '-hh" value="' + escapeHtml(hh) + '">';
                html += '<span class="dd-time-sep">:</span>';
                html += '<input type="number" min="0" max="59" class="dd-time-input" id="' + id + '-mm" value="' + escapeHtml(mm) + '">';
                html += '<select class="dd-time-select" id="' + id + '-ap">';
                html += '<option value="AM"' + (ap === 'AM' ? ' selected' : '') + '>AM</option>';
                html += '<option value="PM"' + (ap === 'PM' ? ' selected' : '') + '>PM</option>';
                html += '</select></div>';
            } else if (meta.type === 'decimal') {
                html += '<input type="number" step="0.01" class="dd-input" id="' + id + '" value="' + escapeHtml(val !== '' ? val : '0.00') + '">';
            } else if (meta.type === 'decimal_null') {
                html += '<input type="number" step="0.01" class="dd-input" id="' + id + '" value="' + escapeHtml(val) + '" placeholder="∞ if blank">';
            } else if (meta.type === 'rr_decimal') {
                html += '<div class="dd-rr-wrap"><span class="dd-rr-prefix">1:</span>';
                html += '<input type="number" step="0.01" class="dd-rr-input" id="' + id + '" value="' + escapeHtml(val !== '' ? val : '') + '"></div>';
            } else if (meta.type === 'bool') {
                html += '<select class="dd-input dd-select" id="' + id + '">';
                html += '<option value="0"' + ((val === '0' || val === 0 || val === '' || val === 'No') ? ' selected' : '') + '>No</option>';
                html += '<option value="1"' + ((val === '1' || val === 1 || val === 'Yes') ? ' selected' : '') + '>Yes</option>';
                html += '</select>';
            } else if (meta.type === 'enum') {
                var opts = meta.options || [];
                html += '<select class="dd-input dd-select" id="' + id + '">';
                if (meta.default === '') html += '<option value="">— Select —</option>';
                opts.forEach(function (o) {
                    var sel = '';
                    if (val !== '' && val === o) sel = ' selected';
                    else if (val === '' && meta.default === o) sel = ' selected';
                    html += '<option value="' + o + '"' + sel + '>' + o + '</option>';
                });
                html += '</select>';
            } else if (meta.type === 'percent_string') {
                html += '<input type="text" class="dd-input" id="' + id + '" value="' + escapeHtml(val !== '' ? val : '0%') + '" placeholder="e.g. 0%">';
            } else {
                html += '<input type="text" class="dd-input" id="' + id + '" value="' + escapeHtml(val) + '">';
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
        html += '</div></div>';
        return html;
    }

    function ddListCancelAdd(listKey) {
        var c = document.getElementById('dd-list-add-' + listKey);
        if (c) c.innerHTML = '';
    }
    function ddListCancelEdit(listKey, rowId) {
        var c = document.getElementById('dd-list-edit-' + listKey + '-' + rowId);
        if (c) c.innerHTML = '';
    }

    function ddListCollectFields(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return null;
        var row = {};
        for (var colName in def.columns) {
            if (!def.columns.hasOwnProperty(colName)) continue;
            var meta = def.columns[colName];
            var id = 'dd-list-field-' + listKey + '-' + colName;

            if (meta.type === 'day') {
                var el = document.getElementById(id);
                if (!el || !el.value) { showDdAlert('Please select a day.', 'Missing value'); return null; }
                row[colName] = el.value;
            } else if (meta.type === 'time') {
                var hhEl = document.getElementById(id + '-hh');
                var mmEl = document.getElementById(id + '-mm');
                var apEl = document.getElementById(id + '-ap');
                if (!hhEl || !mmEl || !apEl) return null;
                var hh = parseInt(hhEl.value, 10);
                var mm = parseInt(mmEl.value, 10);
                if (isNaN(hh) || hh < 1 || hh > 12) { showDdAlert('Hour must be between 1 and 12.', 'Invalid time'); return null; }
                if (isNaN(mm) || mm < 0 || mm > 59) { showDdAlert('Minutes must be between 0 and 59.', 'Invalid time'); return null; }
                var ap = apEl.value;
                var h24 = hh % 12; if (ap === 'PM') h24 += 12;
                row[colName] = ('0' + h24).slice(-2) + ':' + ('0' + mm).slice(-2) + ':00';
            } else if (meta.type === 'decimal' || meta.type === 'rr_decimal') {
                var el2 = document.getElementById(id);
                var raw = el2 ? el2.value : '';
                row[colName] = (raw === '' ? 0 : (parseFloat(raw) || 0));
            } else if (meta.type === 'decimal_null') {
                var el3 = document.getElementById(id);
                var raw3 = el3 ? el3.value.trim() : '';
                row[colName] = (raw3 === '' ? null : (parseFloat(raw3) || 0));
            } else if (meta.type === 'bool') {
                var el4 = document.getElementById(id);
                row[colName] = (el4 && el4.value === '1') ? 1 : 0;
            } else if (meta.type === 'enum') {
                var el5 = document.getElementById(id);
                var v5 = el5 ? el5.value : '';
                if (v5 === '' && meta.default !== '') v5 = meta.default;
                row[colName] = v5;
            } else if (meta.type === 'percent_string') {
                var el6 = document.getElementById(id);
                row[colName] = el6 ? el6.value : '';
            } else {
                var el7 = document.getElementById(id);
                row[colName] = el7 ? el7.value : '';
            }
        }
        return row;
    }

    function ddListSaveAdd(listKey) {
        var row = ddListCollectFields(listKey);
        if (!row) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=add&list_key=' + encodeURIComponent(listKey) + '&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Row added.'); ddListReload(listKey); }
            else showDdAlert((data && data.message) || 'Failed to add row.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListSaveEdit(listKey, rowId) {
        var row = ddListCollectFields(listKey);
        if (!row) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=update&list_key=' + encodeURIComponent(listKey) + '&row_id=' + encodeURIComponent(rowId) + '&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Row updated.'); ddListReload(listKey); }
            else showDdAlert((data && data.message) || 'Failed to update row.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListDelete(listKey, rowId) {
        if (!confirm('Delete this row? This cannot be undone.')) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=delete&list_key=' + encodeURIComponent(listKey) + '&row_id=' + encodeURIComponent(rowId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Row deleted.'); ddListReload(listKey); }
            else showDdAlert((data && data.message) || 'Failed to delete row.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddListReload(listKey) {
        var def = LIST_TABLES[listKey];
        if (!def) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=list&list_key=' + encodeURIComponent(listKey)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) return;
            ddListRenderRows(listKey, data.rows || []);
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
                if (cMeta.type === 'decimal') displayVal = parseFloat(v).toFixed(2);
                else if (cMeta.type === 'rr_decimal') displayVal = '1:' + parseFloat(v).toFixed(2);
                else if (cMeta.type === 'decimal_null') displayVal = (v === null || v === '' ? '∞' : parseFloat(v).toFixed(2));
                else if (cMeta.type === 'bool') displayVal = (parseInt(v, 10) ? 'Yes' : 'No');
                else if (cMeta.type === 'time') displayVal = String(v).substring(0, 5);
                else displayVal = String(v);

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

    // ---------------- Programme Revenue Target ----------------
    var REVENUE_DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

    function ddRtSelectWeek(weekNo) {
        document.querySelectorAll('.dd-rt-week-tab').forEach(function (t) {
            t.classList.toggle('active', parseInt(t.getAttribute('data-week'), 10) === weekNo);
        });
        document.querySelectorAll('.dd-rt-week').forEach(function (w) {
            w.style.display = (parseInt(w.getAttribute('data-week'), 10) === weekNo) ? '' : 'none';
        });
    }

    function ddRtCreateWeek() {
        var input = document.getElementById('ddRtNewWeekInput');
        if (!input) return;
        var weekNo = parseInt(input.value, 10);
        if (isNaN(weekNo) || weekNo < 1 || weekNo > 255) {
            showDdAlert('Week number must be between 1 and 255.', 'Invalid week');
            return;
        }
        var existing = document.getElementById('ddRtWeek-' + weekNo);
        if (existing) { ddRtSelectWeek(weekNo); return; }

        var container = document.getElementById('ddRtWeeksContainer');
        if (!container) return;
        var empty = container.querySelector('.dd-rt-empty');
        if (empty) empty.remove();

        var tabBar = document.getElementById('ddRtWeekTabs');
        if (!tabBar) {
            tabBar = document.createElement('div');
            tabBar.className = 'dd-rt-weeks-tabs';
            tabBar.id = 'ddRtWeekTabs';
            container.parentNode.insertBefore(tabBar, container);
        }

        var tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'dd-rt-week-tab';
        tab.setAttribute('data-week', weekNo);
        tab.textContent = 'Week ' + weekNo;
        tab.onclick = function () { ddRtSelectWeek(weekNo); };
        tabBar.appendChild(tab);

        var weekHtml = ''
            + '<div class="dd-rt-week-head">'
            +   '<div class="dd-rt-week-title"><i class="fa-solid fa-calendar-week"></i> Week ' + weekNo + '</div>'
            +   '<div class="dd-rt-week-actions">'
            +     '<button type="button" class="dd-list-add-btn" style="padding:8px 14px;font-size:0.75rem;" onclick="ddRtOpenAddDay(' + weekNo + ')"><i class="fa-solid fa-plus"></i> Add Day</button>'
            +     '<button type="button" class="dd-btn-mini dd-btn-danger" onclick="ddRtDeleteWeek(' + weekNo + ')"><i class="fa-solid fa-trash"></i> Delete Week</button>'
            +   '</div>'
            + '</div>'
            + '<div id="ddRtAddDay-' + weekNo + '"></div>'
            + '<div class="dd-rt-days" id="ddRtDays-' + weekNo + '">'
            +   '<div class="dd-rt-empty">No days in this week yet. Add a day above.</div>'
            + '</div>';

        var weekEl = document.createElement('div');
        weekEl.className = 'dd-rt-week';
        weekEl.setAttribute('data-week', weekNo);
        weekEl.id = 'ddRtWeek-' + weekNo;
        weekEl.innerHTML = weekHtml;
        container.appendChild(weekEl);

        input.value = weekNo + 1;
        ddRtSelectWeek(weekNo);
    }

    function ddRtOpenAddDay(weekNo) {
        var c = document.getElementById('ddRtAddDay-' + weekNo);
        if (!c) return;
        if (c.innerHTML.trim() !== '') { c.innerHTML = ''; return; }

        var html = '<div class="dd-list-form">'
            + '<div class="dd-list-form-row">'
            +   '<label>Day</label>'
            +   '<select class="dd-input dd-select" id="ddRtNewDaySelect-' + weekNo + '">'
            +     '<option value="">— Select day —</option>';
        REVENUE_DAYS.forEach(function (d) { html += '<option value="' + d + '">' + d + '</option>'; });
        html +=   '</select></div>'
            + '<div class="dd-list-form-actions">'
            +   '<button type="button" class="dd-btn-mini dd-btn-save" onclick="ddRtConfirmAddDay(' + weekNo + ')"><i class="fa-solid fa-plus"></i> Add Day</button>'
            +   '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="ddRtCancelAddDay(' + weekNo + ')">Cancel</button>'
            + '</div></div>';
        c.innerHTML = html;
    }

    function ddRtCancelAddDay(weekNo) {
        var c = document.getElementById('ddRtAddDay-' + weekNo);
        if (c) c.innerHTML = '';
    }

    function ddRtConfirmAddDay(weekNo) {
        var sel = document.getElementById('ddRtNewDaySelect-' + weekNo);
        if (!sel || !sel.value) { showDdAlert('Please select a day.', 'Missing value'); return; }
        var day = sel.value;
        if (document.querySelector('.dd-rt-day[data-week="' + weekNo + '"][data-day="' + day + '"]')) {
            showDdAlert('That day already exists in this week.', 'Duplicate day');
            return;
        }
        var daysContainer = document.getElementById('ddRtDays-' + weekNo);
        if (!daysContainer) return;
        var empty = daysContainer.querySelector('.dd-rt-empty');
        if (empty) empty.remove();

        var dayHtml = ''
            + '<div class="dd-rt-day-head">'
            +   '<div class="dd-rt-day-title"><i class="fa-solid fa-calendar-day"></i> ' + escapeHtml(day) + '</div>'
            +   '<div class="dd-rt-day-actions">'
            +     '<button type="button" class="dd-btn-mini dd-btn-edit" onclick="ddRtOpenAddRange(' + weekNo + ', \'' + escapeAttr(day) + '\')"><i class="fa-solid fa-plus"></i> Add Range</button>'
            +     '<button type="button" class="dd-btn-mini dd-btn-danger" onclick="ddRtDeleteDay(' + weekNo + ', \'' + escapeAttr(day) + '\')"><i class="fa-solid fa-trash"></i> Delete Day</button>'
            +   '</div>'
            + '</div>'
            + '<div id="ddRtAddRange-' + weekNo + '-' + escapeAttr(day) + '"></div>'
            + '<div class="dd-rt-ranges" id="ddRtRanges-' + weekNo + '-' + escapeAttr(day) + '">'
            +   '<div class="dd-rt-empty">No balance ranges yet. Add one above.</div>'
            + '</div>';

        var dayEl = document.createElement('div');
        dayEl.className = 'dd-rt-day';
        dayEl.setAttribute('data-week', weekNo);
        dayEl.setAttribute('data-day', day);
        dayEl.innerHTML = dayHtml;
        daysContainer.appendChild(dayEl);

        ddRtCancelAddDay(weekNo);
    }

    function ddRtOpenAddRange(weekNo, dayName) {
        var c = document.getElementById('ddRtAddRange-' + weekNo + '-' + dayName);
        if (!c) return;
        if (c.innerHTML.trim() !== '') { c.innerHTML = ''; return; }
        c.innerHTML = ddRtBuildRangeForm(weekNo, dayName, null, null);
    }

    function ddRtCancelAddRange(weekNo, dayName) {
        var c = document.getElementById('ddRtAddRange-' + weekNo + '-' + dayName);
        if (c) c.innerHTML = '';
    }

    function ddRtBuildRangeForm(weekNo, dayName, rangeId, prefill) {
        prefill = prefill || {};
        var f = prefill.from_balance !== undefined ? prefill.from_balance : '';
        var t = prefill.to_balance !== undefined ? prefill.to_balance : '';
        var r = prefill.risk_amount !== undefined ? prefill.risk_amount : '';
        var p = prefill.pre_pending_sum !== undefined ? prefill.pre_pending_sum : 0;

        var html = '<div class="dd-list-form">';
        html += '<div class="dd-list-form-row"><label>From Balance</label>';
        html += '<input type="number" step="0.01" class="dd-input" id="ddRtFrom-' + weekNo + '-' + escapeAttr(dayName) + '" value="' + escapeHtml(f) + '"></div>';
        html += '<div class="dd-list-form-row"><label>To Balance (leave blank for ∞)</label>';
        html += '<input type="number" step="0.01" class="dd-input" id="ddRtTo-' + weekNo + '-' + escapeAttr(dayName) + '" value="' + escapeHtml(t) + '"></div>';
        html += '<div class="dd-list-form-row"><label>Risk Amount</label>';
        html += '<input type="number" step="0.01" class="dd-input" id="ddRtRisk-' + weekNo + '-' + escapeAttr(dayName) + '" value="' + escapeHtml(r) + '"></div>';
        html += '<div class="dd-list-form-row"><label>Pre-Pending Sum</label>';
        html += '<select class="dd-input dd-select" id="ddRtPre-' + weekNo + '-' + escapeAttr(dayName) + '">';
        html += '<option value="0"' + ((parseInt(p, 10) === 0) ? ' selected' : '') + '>No</option>';
        html += '<option value="1"' + ((parseInt(p, 10) === 1) ? ' selected' : '') + '>Yes</option>';
        html += '</select></div>';
        html += '<div class="dd-list-form-actions">';
        if (rangeId) {
            html += '<button type="button" class="dd-btn-mini dd-btn-save" onclick="ddRtSaveEditRange(' + rangeId + ', ' + weekNo + ', \'' + escapeAttr(dayName) + '\')"><i class="fa-solid fa-floppy-disk"></i> Save</button>';
            html += '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="ddRtCancelEditRange(' + rangeId + ')">Cancel</button>';
        } else {
            html += '<button type="button" class="dd-btn-mini dd-btn-save" onclick="ddRtSaveAddRange(' + weekNo + ', \'' + escapeAttr(dayName) + '\')"><i class="fa-solid fa-plus"></i> Add</button>';
            html += '<button type="button" class="dd-btn-mini dd-btn-cancel" onclick="ddRtCancelAddRange(' + weekNo + ', \'' + escapeAttr(dayName) + '\')">Cancel</button>';
        }
        html += '</div></div>';
        return html;
    }

    function ddRtCollectRangeFields(weekNo, dayName) {
        var fromEl = document.getElementById('ddRtFrom-' + weekNo + '-' + dayName);
        var toEl   = document.getElementById('ddRtTo-' + weekNo + '-' + dayName);
        var riskEl = document.getElementById('ddRtRisk-' + weekNo + '-' + dayName);
        var preEl  = document.getElementById('ddRtPre-' + weekNo + '-' + dayName);
        if (!fromEl || !riskEl || !preEl) return null;

        var from = parseFloat(fromEl.value);
        if (isNaN(from)) { showDdAlert('From Balance is required.', 'Missing value'); return null; }
        var risk = parseFloat(riskEl.value);
        if (isNaN(risk)) { showDdAlert('Risk Amount is required.', 'Missing value'); return null; }

        var toRaw = toEl.value.trim();
        var to = toRaw === '' ? null : parseFloat(toRaw);
        if (toRaw !== '' && isNaN(to)) { showDdAlert('To Balance is invalid.', 'Invalid value'); return null; }

        return {
            week_no: weekNo,
            day_name: dayName,
            from_balance: from,
            to_balance: to,
            risk_amount: risk,
            pre_pending_sum: parseInt(preEl.value, 10) ? 1 : 0,
            target_daily_profit: 0,
            include_missed_days: 0,
            include_current_day_as_owed: 0,
            loss_streak_chances: 0
        };
    }

    function ddRtSaveAddRange(weekNo, dayName) {
        var row = ddRtCollectRangeFields(weekNo, dayName);
        if (!row) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=add&list_key=programme_revenue_target&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Range added.'); window.location.reload(); }
            else showDdAlert((data && data.message) || 'Failed to add range.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddRtSaveEditRange(rangeId, weekNo, dayName) {
        var row = ddRtCollectRangeFields(weekNo, dayName);
        if (!row) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=update&list_key=programme_revenue_target&row_id=' + encodeURIComponent(rangeId) + '&row=' + encodeURIComponent(JSON.stringify(row))
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Range updated.'); window.location.reload(); }
            else showDdAlert((data && data.message) || 'Failed to update range.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddRtOpenEditRange(rangeId, weekNo, dayName) {
        var c = document.getElementById('ddRtEditRange-' + rangeId);
        if (!c) return;
        if (c.innerHTML.trim() !== '') { c.innerHTML = ''; return; }

        var rangeEl = document.querySelector('.dd-rt-range[data-range-id="' + rangeId + '"]');
        if (!rangeEl) return;

        var fields = rangeEl.querySelectorAll('.dd-row-field-value');
        var from = fields[0] ? fields[0].textContent.trim() : '';
        var to   = fields[1] ? fields[1].textContent.trim() : '';
        if (to === '∞') to = '';
        var risk = fields[2] ? fields[2].textContent.trim() : '';
        var pre  = fields[3] ? (fields[3].textContent.trim() === 'Yes' ? 1 : 0) : 0;

        c.innerHTML = ddRtBuildRangeForm(weekNo, dayName, rangeId, {
            from_balance: from, to_balance: to, risk_amount: risk, pre_pending_sum: pre
        });
    }

    function ddRtCancelEditRange(rangeId) {
        var c = document.getElementById('ddRtEditRange-' + rangeId);
        if (c) c.innerHTML = '';
    }

    function ddRtDeleteRange(rangeId) {
        if (!confirm('Delete this balance range? This cannot be undone.')) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=delete&list_key=programme_revenue_target&row_id=' + encodeURIComponent(rangeId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Range deleted.'); window.location.reload(); }
            else showDdAlert((data && data.message) || 'Failed to delete range.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddRtDeleteDay(weekNo, dayName) {
        if (!confirm('Delete all balance ranges for ' + dayName + ' in Week ' + weekNo + '?')) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=delete_day&list_key=programme_revenue_target&week_no=' + encodeURIComponent(weekNo) + '&day_name=' + encodeURIComponent(dayName)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Day deleted.'); window.location.reload(); }
            else showDdAlert((data && data.message) || 'Failed to delete day.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    function ddRtDeleteWeek(weekNo) {
        if (!confirm('Delete ALL rows for Week ' + weekNo + '? This cannot be undone.')) return;
        fetch('programme_accountmanagement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'list_action=delete_week&list_key=programme_revenue_target&week_no=' + encodeURIComponent(weekNo)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) { showToast('Week deleted.'); window.location.reload(); }
            else showDdAlert((data && data.message) || 'Failed to delete week.', 'Error');
        })
        .catch(function () { showDdAlert('Network error.', 'Error'); });
    }

    // ---------------- Keyboard ----------------
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var am = document.getElementById('ddAlertModal');
            if (am && am.classList.contains('active')) closeDdAlert();
        }
    });
    document.addEventListener('click', function (e) {
        var am = document.getElementById('ddAlertModal');
        if (am && am.classList.contains('active') && e.target === am) closeDdAlert();
    });

    // ---------------- Exports ----------------
    window.saveAccountManagement = saveAccountManagement;
    window.closeDdAlert          = closeDdAlert;
    window.showToast             = showToast;

    window.ddListOpenAdd         = ddListOpenAdd;
    window.ddListOpenEdit        = ddListOpenEdit;
    window.ddListCancelAdd       = ddListCancelAdd;
    window.ddListCancelEdit      = ddListCancelEdit;
    window.ddListSaveAdd         = ddListSaveAdd;
    window.ddListSaveEdit        = ddListSaveEdit;
    window.ddListDelete          = ddListDelete;
    window.ddListReload          = ddListReload;

    window.ddRtSelectWeek        = ddRtSelectWeek;
    window.ddRtCreateWeek        = ddRtCreateWeek;
    window.ddRtOpenAddDay        = ddRtOpenAddDay;
    window.ddRtCancelAddDay      = ddRtCancelAddDay;
    window.ddRtConfirmAddDay     = ddRtConfirmAddDay;
    window.ddRtOpenAddRange      = ddRtOpenAddRange;
    window.ddRtCancelAddRange    = ddRtCancelAddRange;
    window.ddRtSaveAddRange      = ddRtSaveAddRange;
    window.ddRtSaveEditRange     = ddRtSaveEditRange;
    window.ddRtOpenEditRange     = ddRtOpenEditRange;
    window.ddRtCancelEditRange   = ddRtCancelEditRange;
    window.ddRtDeleteRange       = ddRtDeleteRange;
    window.ddRtDeleteDay         = ddRtDeleteDay;
    window.ddRtDeleteWeek        = ddRtDeleteWeek;
    window.ddRtReloadAll         = function () { window.location.reload(); };
})();
</script>

</body>
</html>