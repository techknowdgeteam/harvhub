<?php
    // dev_accountmanagement.php — Account Management (Standalone)
    session_start();
    file_put_contents('debug_hit.log', date('Y-m-d H:i:s') . " - dev_accountmanagement.php was reached\n", FILE_APPEND);
die("STOP - dev_accountmanagement.php reached successfully");

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
        'enable_martingale'                          => ['label' => 'Enable Martingale',                          'type' => 'bool',   'json' => false],
        'martingale_config'                          => ['label' => 'Martingale Config',                          'type' => 'json',   'json' => true],
        'minimum_balance_risk_distance'              => ['label' => 'Minimum Balance Risk Distance',              'type' => 'json',   'json' => true],
        'maximum_balance_risk_distance'              => ['label' => 'Maximum Balance Risk Distance',              'type' => 'json',   'json' => true],
        'martingale_per_stage_drawdown_amount'       => ['label' => 'Martingale Per Stage Drawdown Amount',       'type' => 'json',   'json' => true],
        'account_balance_default_risk_management'    => ['label' => 'Account Balance Default Risk Management',    'type' => 'json',   'json' => true],
        'account_balance_maximum_risk_management'    => ['label' => 'Account Balance Maximum Risk Management',    'type' => 'json',   'json' => true],
        'daily_target_config'                        => ['label' => 'Daily Target Config',                         'type' => 'json',   'json' => true],
        'restrict_order_from_timeframe'              => ['label' => 'Restrict Order From Timeframe',              'type' => 'varchar','json' => false],
        'use_recent_highest_balance_as_current_balance' => ['label' => 'Use Recent Highest Balance As Current Balance','type' => 'bool','json' => false],
        'symbols_grid_strategy'                      => ['label' => 'Symbols Grid Strategy',                       'type' => 'bool',   'json' => false],
        'enable_single_position_and_pending'         => ['label' => 'Enable Single Position And Pending',          'type' => 'bool',   'json' => false],
        'manage_grid_levels_count'                   => ['label' => 'Manage Grid Levels Count',                    'type' => 'json',   'json' => true],
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
        ensureAccountManagementRow($pdo, $userId, 0); // master row
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
<?php include 'dev_dashboard_style.php'; ?>
<style>
    /* JSON view / edit modal */
    .dd-json-view-modal .dd-modal-content {
        max-width: 600px;
    }
    .dd-json-view-body {
        background: var(--bg, #f5f5f5);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: var(--radius-sm, 8px);
        padding: 14px 16px;
        max-height: 55vh;
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
        max-height: 55vh;
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
    }
    .dd-json-view-actions .dd-btn-primary,
    .dd-json-view-actions .dd-btn-ghost {
        width: auto;
        flex: 1;
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
                JSON columns render an interactive tree directly on this page — no modal.
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
                                    <!-- JSON already stored: show "View JSON Data" button instead of the Store JSON toggle -->
                                    <div class="dd-am-toggle-wrap">
                                        <button type="button"
                                                class="dd-btn-mini dd-btn-construct"
                                                onclick="viewJsonDataCol('<?= htmlspecialchars($col) ?>')">
                                            View JSON Data
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="dd-am-toggle-wrap">
                                        <label class="dd-am-switch">
                                            <input type="checkbox"
                                                   class="dd-am-json-toggle"
                                                   data-col="<?= htmlspecialchars($col) ?>"
                                                   onchange="onJsonToggle(this)">
                                            <span class="dd-am-slider"></span>
                                        </label>
                                        <span class="dd-am-toggle-label">Store JSON</span>
                                        <button type="button"
                                                class="dd-btn-mini dd-btn-construct"
                                                id="constructBtn-<?= htmlspecialchars($col) ?>"
                                                style="display:none;"
                                                onclick="startNewJsonForColumn('<?= htmlspecialchars($col) ?>')">
                                            Construct JSON Data
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
                                    <div class="dd-json-preview"
                                         data-col="<?= htmlspecialchars($col) ?>"
                                         id="jsonPreview-<?= htmlspecialchars($col) ?>">
                                        <?= renderJsonPreview($jsonData) ?>
                                    </div>
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

            <!-- Read-only view -->
            <div class="dd-json-view-body" id="ddJsonViewBody">
                <pre id="ddJsonViewPre"></pre>
            </div>

            <!-- Direct edit -->
            <textarea id="ddJsonEditTextarea" class="dd-json-edit-textarea" spellcheck="false"></textarea>

            <div class="dd-json-view-actions">
                <button type="button" class="dd-btn-ghost" id="ddJsonEditBtn" onclick="enableJsonEdit()">Edit JSON</button>
                <button type="button" class="dd-btn-ghost" id="ddJsonCancelEditBtn" style="display:none;" onclick="cancelJsonEdit()">Cancel</button>
                <button type="button" class="dd-btn-primary" id="ddJsonApplyBtn" style="display:none;" onclick="applyJsonEdit()">Apply JSON</button>
                <button type="button" class="dd-btn-ghost" onclick="closeJsonView()">Close</button>
            </div>
        </div>
    </div>

<script>
    // ==================== STATE ====================
    var ACCOUNT_MGMT_COLUMNS = <?= json_encode($ACCOUNT_MGMT_COLUMNS) ?>;

    // Per-column working data for JSON columns.
    var jsonDataByCol = {};

    // Per-column inline editor state.
    var jsonEditorByCol = {};

    // Per-column root type picker state (true = picker visible, no data yet).
    var jsonRootPickerByCol = {};

    // JSON view/edit modal state.
    var jsonViewCol = null;
    var jsonViewEditing = false;

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

    // ==================== JSON DATA VIEW / EDIT ====================
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

        // Reset to view mode
        document.getElementById('ddJsonViewBody').style.display = 'block';
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

    function applyJsonEdit() {
        if (!jsonViewCol) return;
        var ta = document.getElementById('ddJsonEditTextarea');
        var parsed;
        try {
            parsed = JSON.parse(ta.value);
        } catch (e) {
            showDdAlert('Invalid JSON: ' + e.message, 'Error');
            return;
        }

        // Commit the edited data into the column's working state
        jsonDataByCol[jsonViewCol] = parsed;
        jsonEditorByCol[jsonViewCol] = null;
        renderJsonColumn(jsonViewCol);

        // Update the view and return to view mode
        document.getElementById('ddJsonViewPre').textContent = JSON.stringify(parsed, null, 2);
        cancelJsonEdit();
    }

    function closeJsonView() {
        document.getElementById('ddJsonViewModal').classList.remove('active');
        jsonViewCol = null;
        jsonViewEditing = false;
        unlockBodyScroll();
    }

    // ==================== SAVE ACCOUNT MANAGEMENT ====================
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

        // For JSON columns, use the working data (or existing hidden value)
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
            showDdAlert(data.message || (data.success ? 'Saved.' : 'Failed.'), data.success ? 'Success' : 'Error');
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = originalText; }
            if (btn2) { btn2.disabled = false; btn2.textContent = 'Save All Account Management'; }
            showDdAlert('Network error. Please try again.', 'Error');
        });
    }

    // ==================== JSON TOGGLE ====================
    function onJsonToggle(cb) {
        var col = cb.getAttribute('data-col');
        var btn = document.getElementById('constructBtn-' + col);
        if (!btn) return;
        btn.style.display = cb.checked ? 'inline-block' : 'none';
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

    // ==================== RENDER JSON COLUMN (inline, in the dashboard) ====================
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
        preview.innerHTML = renderJsonColumnTree(col, data, 'root', 'root');
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

            // Key row
            if (editor && editor.kind === 'editKey' && editor.path === path && editor.key === k) {
                html += renderInlineEditKeyEditor(col, path, k);
            } else {
                html += '<div class="dd-json-key-row">';
                html += '<span class="dd-json-key-name">"' + escapeHtml(k) + '"</span>';
                html += '<button type="button" class="dd-btn-mini dd-btn-edit" onclick="openEditorForCol(\'' + escapeAttr(col) + '\',\'editKey\',\'' + escapeAttr(path) + '\',\'' + escapeAttr(k) + '\')">Edit Key</button>';
                html += '<button type="button" class="dd-btn-mini dd-btn-danger" onclick="deleteJsonKeyCol(\'' + escapeAttr(col) + '\',\'' + escapeAttr(path) + '\',\'' + escapeAttr(k) + '\')">×</button>';
                html += '</div>';
            }

            // Value
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

    // Close the JSON view when clicking the dark backdrop (outside the white box).
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
    window.applyJsonEdit             = applyJsonEdit;
    window.closeJsonView             = closeJsonView;
    window.closeDdAlert              = closeDdAlert;
</script>

</body>
</html>