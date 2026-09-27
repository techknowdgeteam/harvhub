<?php
    // dev_accountmanagement.php — Account Management (Standalone)
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

    $currentBroker = $developer['broker'] ?? '';

    // ==================== ACCOUNT MANAGEMENT COLUMNS ====================
    $ACCOUNT_MGMT_COLUMNS = [
        'enable_risk_reward_correction'              => ['label' => 'Enable Risk Reward Correction',              'type' => 'bool',   'json' => false],
        'minimum_risk_reward'                        => ['label' => 'Minimum Risk Reward',                        'type' => 'decimal','json' => false],
        'fixed_risk_reward'                          => ['label' => 'Fixed Risk Reward',                          'type' => 'decimal','json' => false],
        'enable_breakeven'                           => ['label' => 'Enable Breakeven',                           'type' => 'bool',   'json' => false],
        'breakeven_dictionary'                       => ['label' => 'Breakeven Dictionary',                       'type' => 'json',   'json' => true],
        'restrictions_duration'                      => ['label' => 'Restrictions Duration',                      'type' => 'json',   'json' => true],
        'minimum_balance_risk_distance'              => ['label' => 'Minimum Balance Risk Distance',              'type' => 'json',   'json' => true],
        'maximum_balance_risk_distance'              => ['label' => 'Maximum Balance Risk Distance',              'type' => 'json',   'json' => true],
        'account_balance_default_risk_management'    => ['label' => 'Account Balance Default Risk Management',    'type' => 'json',   'json' => true],
        'account_balance_maximum_risk_management'    => ['label' => 'Account Balance Maximum Risk Management',    'type' => 'json',   'json' => true],
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

    function ensureAccountManagementRow(PDO $pdo, int $developerId, int $investorId): int {
        $stmt = $pdo->prepare("SELECT id FROM accountmanagement WHERE developerid = ? AND investorid = ? LIMIT 1");
        $stmt->execute([$developerId, $investorId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];
        $ins = $pdo->prepare("INSERT INTO accountmanagement (developerid, investorid) VALUES (?, ?)");
        $ins->execute([$developerId, $investorId]);
        return (int)$pdo->lastInsertId();
    }

    function syncInvestorRows(PDO $pdo, int $developerId, array $columns): void {
        $stmt = $pdo->prepare("SELECT * FROM accountmanagement WHERE developerid = ? AND investorid = 0 LIMIT 1");
        $stmt->execute([$developerId]);
        $master = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$master) return;

        $investors = getDeveloperInvestors($pdo, $developerId);
        if (empty($investors)) return;

        $cols = array_keys($columns);
        $setParts = [];
        foreach ($cols as $c) { $setParts[] = "`$c` = ?"; }
        $setSql = implode(', ', $setParts);

        $upd = $pdo->prepare("UPDATE accountmanagement SET $setSql WHERE developerid = ? AND investorid = ?");
        $insCols = array_merge(['developerid', 'investorid'], $cols);
        $insPlace = implode(',', array_fill(0, count($insCols), '?'));
        $insSql = "INSERT INTO accountmanagement (" . implode(',', array_map(function($c){ return "`$c`"; }, $insCols)) . ") VALUES ($insPlace)";
        $ins = $pdo->prepare($insSql);

        foreach ($investors as $inv) {
            $invId = (int)$inv['investorid'];
            $chk = $pdo->prepare("SELECT id FROM accountmanagement WHERE developerid = ? AND investorid = ? LIMIT 1");
            $chk->execute([$developerId, $invId]);
            $exists = $chk->fetch(PDO::FETCH_ASSOC);

            $vals = [];
            foreach ($cols as $c) { $vals[] = $master[$c]; }
            if ($exists) {
                $upd->execute(array_merge($vals, [$developerId, $invId]));
            } else {
                $ins->execute(array_merge([$developerId, $invId], $vals));
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
                ensureAccountManagementRow($pdo, $userId, 0);

                $upd = $pdo->prepare("UPDATE accountmanagement SET `$singleCol` = ? WHERE developerid = ? AND investorid = 0");
                $upd->execute([$cleanVal, $userId]);

                syncInvestorRows($pdo, $userId, $ACCOUNT_MGMT_COLUMNS);

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
            ensureAccountManagementRow($pdo, $userId, 0);

            $setParts = []; $vals = [];
            foreach ($clean as $col => $val) { $setParts[] = "`$col` = ?"; $vals[] = $val; }
            $vals[] = $userId;
            $sql = "UPDATE accountmanagement SET " . implode(', ', $setParts) . " WHERE developerid = ? AND investorid = 0";
            $upd = $pdo->prepare($sql);
            $upd->execute($vals);

            syncInvestorRows($pdo, $userId, $ACCOUNT_MGMT_COLUMNS);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Account management saved and synced to all investors.']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $e->getMessage()]);
        }
        exit;
    }

    // ==================== ENSURE ROWS FOR ALL INVESTORS ====================
    try {
        ensureAccountManagementRow($pdo, $userId, 0);
        $investors = getDeveloperInvestors($pdo, $userId);
        foreach ($investors as $inv) ensureAccountManagementRow($pdo, $userId, (int)$inv['investorid']);
    } catch (PDOException $e) {}

    // ==================== FETCH ACCOUNT MANAGEMENT MASTER ROW ====================
    $accountMgmt = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM accountmanagement WHERE developerid = ? AND investorid = 0 LIMIT 1");
        $stmt->execute([$userId]);
        $accountMgmt = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) { $accountMgmt = []; }
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

    /* Prevent double-tap zoom on tappable elements */
    button, a, input, select, textarea, label {
        touch-action: manipulation;
    }

    input, textarea, select {
        -webkit-touch-callout: none;
    }

    html {
        -webkit-text-size-adjust: 100%;
        text-size-adjust: 100%;
    }

    /* iOS zoom fix */
    @media (max-width: 768px) {
        input, select, textarea,
        .dd-input, .dd-select, .dd-am-input, .dd-inline-input,
        .dd-req-input, .dd-json-edit-textarea, .pt-modal-input {
            font-size: 16px !important;
        }
    }

    /* ============================================================
       MAIN PAGE — FOLDABLE JSON PREVIEW
       (Only on the dashboard, NOT the modal)
       ============================================================ */
    .dd-foldable {
        position: relative;
    }

    .dd-foldable.is-collapsed {
        max-height: 180px;
        overflow: hidden;
    }

    .dd-foldable.is-collapsed::after {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        height: 56px;
        background: linear-gradient(to bottom, transparent, var(--bg, #f5f5f5));
        pointer-events: none;
        border-radius: 0 0 var(--radius-sm, 8px) var(--radius-sm, 8px);
    }

    body.dark-mode .dd-foldable.is-collapsed::after {
        background: linear-gradient(to bottom, transparent, var(--bg-card, #1e1e2a));
    }

    .dd-fold-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        font-family: inherit;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        color: var(--accent, #2e8b57);
        background: transparent;
        border: 1px solid var(--accent, #2e8b57);
        border-radius: var(--radius-sm, 8px);
        padding: 6px 14px;
        cursor: pointer;
        transition: background 0.15s ease, transform 0.1s ease;
    }

    .dd-fold-toggle:hover {
        background: rgba(46, 139, 87, 0.08);
    }

    .dd-fold-toggle:active {
        transform: scale(0.97);
    }

    .dd-fold-toggle .dd-fold-icon {
        transition: transform 0.2s ease;
        display: inline-block;
        font-size: 0.85rem;
        line-height: 1;
    }

    .dd-fold-toggle.is-expanded .dd-fold-icon {
        transform: rotate(180deg);
    }

    /* ============================================================
       JSON VIEW / EDIT MODAL — ALWAYS EXPANDED
       ============================================================ */
    .dd-json-view-modal .dd-modal-content {
        max-width: 600px;
    }
    .dd-json-view-body {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: 14px 16px;
        max-height: 60vh;
        overflow: auto;
    }
    .dd-json-view-body pre {
        margin: 0;
        font-family: 'Courier New', monospace;
        font-size: 0.82rem;
        line-height: 1.55;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--text, #222);
    }
    .dd-json-edit-textarea {
        display: none;
        width: 100%;
        box-sizing: border-box;
        min-height: 320px;
        max-height: 60vh;
        resize: vertical;
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: 12px 14px;
        font-family: 'Courier New', monospace;
        font-size: 0.82rem;
        line-height: 1.55;
        color: var(--text, #222);
    }
    .dd-json-edit-textarea:focus {
        outline: none;
        border-color: var(--accent, #2e8b57);
    }
    .dd-json-view-actions {
        display: flex;
        gap: 10px;
        margin-top: var(--spacing-sm, 12px);
        flex-wrap: wrap;
    }
    .dd-json-view-actions .dd-btn-primary,
    .dd-json-view-actions .dd-btn-ghost {
        width: auto;
        flex: 1;
        min-width: 100px;
    }
    body.dark-mode .dd-json-view-body {
        background: var(--bg, #2a2a3a);
        border-color: var(--border-color, #333);
    }
    body.dark-mode .dd-json-edit-textarea {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
        color: var(--text, #eee);
    }

    /* ============================================================
       COLUMN HEAD ACTION ROW — polished buttons
       ============================================================ */
    .dd-am-col-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .dd-btn-col {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 999px;
        font-family: inherit;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.3px;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
        border: 1.5px solid transparent;
        white-space: nowrap;
        line-height: 1;
        -webkit-tap-highlight-color: transparent;
    }

    .dd-btn-col:active {
        transform: scale(0.96);
    }

    .dd-btn-col i {
        font-size: 0.9rem;
        line-height: 1;
    }

    /* "View JSON Data" — subtle outline style */
    .dd-btn-col-view {
        background: transparent;
        color: var(--accent, #2e8b57);
        border-color: var(--accent, #2e8b57);
    }
    .dd-btn-col-view:hover {
        background: rgba(46, 139, 87, 0.1);
    }

    /* "Save Column" — solid accent style, cooler */
    .dd-btn-col-save {
        background: linear-gradient(135deg, #2e8b57 0%, #1f6b41 100%);
        color: #fff;
        border-color: #2e8b57;
        box-shadow: 0 3px 10px rgba(46, 139, 87, 0.3);
    }
    .dd-btn-col-save:hover {
        background: linear-gradient(135deg, #34a368 0%, #237a4a 100%);
        box-shadow: 0 5px 14px rgba(46, 139, 87, 0.4);
    }
    .dd-btn-col-save:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        box-shadow: none;
    }

    /* "Construct JSON Data" — softer pill */
    .dd-btn-col-construct {
        background: rgba(46, 139, 87, 0.1);
        color: var(--accent, #2e8b57);
        border-color: rgba(46, 139, 87, 0.35);
    }
    .dd-btn-col-construct:hover {
        background: rgba(46, 139, 87, 0.18);
        border-color: var(--accent, #2e8b57);
    }

    body.dark-mode .dd-btn-col-view {
        color: var(--accent, #3fb5c9);
        border-color: var(--accent, #3fb5c9);
    }
    body.dark-mode .dd-btn-col-view:hover {
        background: rgba(63, 181, 201, 0.12);
    }
    body.dark-mode .dd-btn-col-save {
        background: linear-gradient(135deg, #3fb5c9 0%, #2a8fa0 100%);
        border-color: #3fb5c9;
        box-shadow: 0 3px 10px rgba(63, 181, 201, 0.25);
    }
    body.dark-mode .dd-btn-col-construct {
        background: rgba(63, 181, 201, 0.12);
        color: var(--accent, #3fb5c9);
        border-color: rgba(63, 181, 201, 0.35);
    }

    /* ============================================================
       TOAST
       ============================================================ */
    .dd-save-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(80px);
        background: #27ae60;
        color: #fff;
        font-family: inherit;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 12px 26px;
        border-radius: 24px;
        box-shadow: 0 6px 24px rgba(39, 174, 96, 0.4);
        opacity: 0;
        transition: opacity 0.3s ease, transform 0.3s ease;
        z-index: 999999;
        pointer-events: none;
        max-width: 88vw;
        text-align: center;
    }
    .dd-save-toast.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .dd-save-toast.error {
        background: #e74c3c;
        box-shadow: 0 6px 24px rgba(231, 76, 60, 0.4);
    }

    @media (max-width: 480px) {
        .dd-am-col-actions {
            width: 100%;
            justify-content: flex-start;
        }
        .dd-btn-col {
            padding: 8px 14px;
            font-size: 0.74rem;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div class="dd-page-wrapper">

        <!-- Header -->
        <div class="dd-page-header">
            <h1>Account Management</h1>
            <p>Configure trading rules. Changes are automatically synced to all your investors.</p>
        </div>

        <!-- Broker status -->
        <?php if (!empty($currentBroker)): ?>
            <div class="dd-notice dd-notice-info">
                <strong>Broker:</strong> <?= htmlspecialchars($currentBroker) ?>
            </div>
        <?php endif; ?>

        <section class="dd-card">
            <div class="dd-card-head">
                <div>
                    <h2 class="dd-card-title">Account Management</h2>
                    <p class="dd-card-subtitle">
                        Each investor under you has exactly one row in the account management table.
                        Saving here propagates to all of them.
                    </p>
                </div>
                <button type="button" class="dd-btn-primary" style="width:auto;" id="saveAccountMgmtBtn" onclick="saveAccountManagement()">
                    Save All
                </button>
            </div>

            <div class="dd-am-note">
                <strong>Note:</strong> Any change here will be applied to all investors under you.
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
                                <select class="dd-input dd-am-input" data-col="<?= htmlspecialchars($col) ?>" data-type="bool">
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

            <!-- Read-only view — ALWAYS EXPANDED, no fold toggle -->
            <div class="dd-json-view-body" id="ddJsonViewBody">
                <pre id="ddJsonViewPre"></pre>
            </div>

            <!-- Direct edit -->
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
    // ==================== STATE ====================
    var ACCOUNT_MGMT_COLUMNS = <?= json_encode($ACCOUNT_MGMT_COLUMNS) ?>;

    var jsonDataByCol = {};
    var jsonEditorByCol = {};
    var jsonRootPickerByCol = {};

    var jsonViewCol = null;
    var jsonViewEditing = false;

    // Threshold (px) above which a page preview auto-collapses.
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
    }
    function closeDdAlert() {
        document.getElementById('ddAlertModal').classList.remove('active');
        unlockBodyScroll();
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

    /**
     * Wrap a page-side JSON preview in a foldable shell if it's tall enough.
     * Never used for the modal — the modal is always expanded.
     */
    function ensureFoldable(previewEl, col) {
        if (!previewEl) return;

        previewEl.classList.add('dd-foldable');

        var wrap = previewEl.closest('.dd-am-input-wrap');
        if (!wrap) return;

        var btn = wrap.querySelector('.dd-fold-toggle[data-target="' + previewEl.id + '"]');

        // Temporarily measure full height.
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

        // Start collapsed on the page (keeps the card tidy).
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

        fetch('dev_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
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

        fetch('dev_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
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
            try {
                data = JSON.parse(raw);
            } catch (e) {
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

        // Reset to view mode — MODAL IS ALWAYS FULLY EXPANDED (no fold class).
        document.getElementById('ddJsonViewBody').style.display = 'block';
        document.getElementById('ddJsonViewBody').classList.remove('is-collapsed', 'dd-foldable');
        document.getElementById('ddJsonEditTextarea').style.display = 'none';
        document.getElementById('ddJsonEditBtn').style.display = 'inline-block';
        document.getElementById('ddJsonCancelEditBtn').style.display = 'none';
        document.getElementById('ddJsonApplyBtn').style.display = 'none';

        document.getElementById('ddJsonViewModal').classList.add('active');
        lockBodyScroll();
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
            try {
                parsed = JSON.parse(ta.value);
            } catch (e) {
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

        fetch('dev_accountmanagement.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
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

    // ==================== RENDER JSON COLUMN (PAGE) ====================
    function renderJsonColumn(col) {
        var preview = document.getElementById('jsonPreview-' + col);
        if (!preview) return;
        var data = jsonDataByCol[col];
        if (data === undefined || data === null) {
            if (jsonRootPickerByCol[col]) {
                renderJsonRootPicker(col);
                return;
            }
            preview.style.display = 'none';
            preview.innerHTML = '';
            return;
        }
        preview.style.display = 'block';
        preview.classList.add('dd-foldable');
        preview.innerHTML = renderJsonColumnTree(col, data, 'root', 'root');

        // Only the page preview gets fold logic. The modal never does.
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

    // ==================== INIT JSON COLUMNS ====================
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
            try {
                jsonDataByCol[col] = JSON.parse(v);
            } catch (e) {
                jsonDataByCol[col] = null;
            }
            jsonEditorByCol[col] = null;
            jsonRootPickerByCol[col] = false;
        });
        Object.keys(jsonDataByCol).forEach(function (col) {
            if (jsonDataByCol[col] !== null && jsonDataByCol[col] !== undefined) {
                renderJsonColumn(col);
            }
        });

        // Apply fold logic to server-rendered previews (inside <details>).
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
        if (jm && jm.classList.contains('active') && e.target === jm) {
            closeJsonView();
        }
    });

    // ==================== INIT ====================
    document.addEventListener('DOMContentLoaded', function () {
        initJsonColumns();
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
        function openMobileSidebar() { sidebar.classList.add('expanded'); overlay.classList.add('active'); body.style.overflow = 'hidden'; }
        function closeMobileSidebar() { sidebar.classList.remove('expanded'); overlay.classList.remove('active'); body.style.overflow = ''; }
        function toggleDesktopSidebar() {
            var isExpanded = sidebar.classList.contains('expanded');
            if (isExpanded) { sidebar.classList.remove('expanded'); body.classList.remove('sidebar-expanded-desktop'); }
            else { sidebar.classList.add('expanded'); body.classList.add('sidebar-expanded-desktop'); }
        }

        if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', function (e) { e.stopPropagation(); if (isMobile()) openMobileSidebar(); });
        if (desktopToggle) desktopToggle.addEventListener('click', function (e) { e.stopPropagation(); if (!isMobile()) toggleDesktopSidebar(); });
        if (overlay) overlay.addEventListener('click', function () { if (isMobile()) closeMobileSidebar(); });

        document.querySelectorAll('.sidebar-menu-item').forEach(function (item) {
            item.addEventListener('click', function () { if (isMobile()) setTimeout(closeMobileSidebar, 150); });
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
                    if (sidebar.classList.contains('expanded')) body.classList.add('sidebar-expanded-desktop');
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
</script>

</body>
</html>