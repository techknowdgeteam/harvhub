<?php
// vps_config.php - Admin VPS Management (Server side)
// Included by serveraccount.php when view=vps

// ---------------------------------------------------------------
// SECTION A: HANDLE AJAX REQUESTS
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $authenticated) {

    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ============================================================
    // TAB 1: REGISTER VPS FOR USERS
    // ============================================================
    if ($action === 'vps_get_all_users') {
        try {
            $stmt = $pdo->prepare("
                SELECT id, fullname, email
                FROM {$harvhubTable}
                ORDER BY fullname ASC
            ");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $withVps = [];
            try {
                $vstmt = $pdo->query("SELECT user_id FROM vps");
                while ($r = $vstmt->fetch(PDO::FETCH_ASSOC)) {
                    $withVps[(int)$r['user_id']] = true;
                }
            } catch (Exception $e) {}

            foreach ($users as &$u) {
                $u['has_vps'] = isset($withVps[(int)$u['id']]);
            }
            unset($u);

            echo json_encode(['success' => true, 'users' => $users]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'vps_get_user_vps') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user']);
            exit;
        }

        try {
            $ustmt = $pdo->prepare("SELECT id, fullname, email FROM {$harvhubTable} WHERE id = ?");
            $ustmt->execute([$user_id]);
            $userRow = $ustmt->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            $vstmt = $pdo->prepare("SELECT * FROM vps WHERE user_id = ? LIMIT 1");
            $vstmt->execute([$user_id]);
            $vpsRow = $vstmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'user'    => $userRow,
                'vps'     => $vpsRow ?: null
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'vps_save_user_vps') {
        $admin_password = $_POST['admin_password'] ?? '';
        $login_id       = $_POST['login_id'] ?? '';
        $user_id        = (int)($_POST['user_id'] ?? 0);

        $astmt = $pdo->prepare("SELECT admin_login_id, admin_password_hash FROM {$serverAccountTable} WHERE id = 1");
        $astmt->execute();
        $adminData = $astmt->fetch(PDO::FETCH_ASSOC);

        if (!$adminData
            || $login_id !== ($adminData['admin_login_id'] ?? '')
            || !password_verify($admin_password, $adminData['admin_password_hash'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid password']);
            exit;
        }

        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user']);
            exit;
        }

        $server_location        = trim($_POST['server_location'] ?? '');
        $subscription_duration  = (int)($_POST['subscription_duration'] ?? 30);
        $subscription_start     = trim($_POST['subscription_start_date'] ?? '');
        $vps_ip_address         = trim($_POST['vps_ip_address'] ?? '');
        $vps_provider_login     = trim($_POST['vps_provider_login'] ?? '');
        $vps_provider_password  = (string)($_POST['vps_provider_password'] ?? '');
        $computer_username      = trim($_POST['computer_username'] ?? '');
        $computer_password      = (string)($_POST['computer_password'] ?? '');
        $rdp_password           = (string)($_POST['rdp_password'] ?? '');
        $visibility             = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

        if ($server_location === '') {
            echo json_encode(['success' => false, 'error' => 'Server location is required']);
            exit;
        }

        if ($subscription_start === '' || $subscription_start === '0000-00-00') {
            $subscription_start = null;
        } else {
            $ts = strtotime($subscription_start);
            $subscription_start = $ts ? date('Y-m-d', $ts) : null;
        }

        try {
            $check = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? LIMIT 1");
            $check->execute([$user_id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $upd = $pdo->prepare("
                    UPDATE vps SET
                        server_location = ?,
                        subscription_duration = ?,
                        subscription_start_date = ?,
                        vps_ip_address = ?,
                        vps_provider_login = ?,
                        vps_provider_password = ?,
                        computer_username = ?,
                        computer_password = ?,
                        rdp_password = ?,
                        visibility = ?
                    WHERE user_id = ?
                ");
                $upd->execute([
                    $server_location, $subscription_duration, $subscription_start,
                    $vps_ip_address, $vps_provider_login, $vps_provider_password,
                    $computer_username, $computer_password, $rdp_password,
                    $visibility, $user_id
                ]);
                echo json_encode(['success' => true, 'message' => 'VPS updated']);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO vps
                        (user_id, server_location, subscription_duration, subscription_start_date,
                         vps_ip_address, vps_provider_login, vps_provider_password,
                         computer_username, computer_password, rdp_password, visibility)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $user_id, $server_location, $subscription_duration, $subscription_start,
                    $vps_ip_address, $vps_provider_login, $vps_provider_password,
                    $computer_username, $computer_password, $rdp_password, $visibility
                ]);
                echo json_encode(['success' => true, 'message' => 'VPS registered']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ============================================================
    // TAB 2: USERS WITH VPS
    // ============================================================
    if ($action === 'vps_list_with_vps') {
        try {
            $stmt = $pdo->query("
                SELECT v.*, h.fullname, h.email
                FROM vps v
                INNER JOIN {$harvhubTable} h ON h.id = v.user_id
                ORDER BY h.fullname ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $fCounts = [];
            $rCounts = [];

            try {
                $fs = $pdo->query("SELECT owner_id, COUNT(*) AS c FROM vps_hosts_followers GROUP BY owner_id");
                while ($r = $fs->fetch(PDO::FETCH_ASSOC)) {
                    $fCounts[(int)$r['owner_id']] = (int)$r['c'];
                }
            } catch (Exception $e) {}

            try {
                $rs = $pdo->query("SELECT owner_id, COUNT(*) AS c FROM vps_hosts_requestors GROUP BY owner_id");
                while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
                    $rCounts[(int)$r['owner_id']] = (int)$r['c'];
                }
            } catch (Exception $e) {}

            foreach ($rows as &$r) {
                $r['follower_count'] = $fCounts[(int)$r['user_id']] ?? 0;
                $r['requestor_count'] = $rCounts[(int)$r['user_id']] ?? 0;
            }
            unset($r);

            echo json_encode(['success' => true, 'users' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'vps_get_details') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user']);
            exit;
        }

        try {
            $ustmt = $pdo->prepare("SELECT id, fullname, email FROM {$harvhubTable} WHERE id = ?");
            $ustmt->execute([$user_id]);
            $userRow = $ustmt->fetch(PDO::FETCH_ASSOC);
            if (!$userRow) {
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }

            $vstmt = $pdo->prepare("SELECT * FROM vps WHERE user_id = ? LIMIT 1");
            $vstmt->execute([$user_id]);
            $vpsRow = $vstmt->fetch(PDO::FETCH_ASSOC);

            if (!$vpsRow) {
                echo json_encode(['success' => false, 'error' => 'User has no VPS']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'user' => $userRow,
                'vps'  => $vpsRow
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'vps_get_followers') {
        $owner_id = (int)($_POST['owner_id'] ?? 0);
        if ($owner_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid owner']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT f.id, f.follower_id, f.host_status, f.created_at,
                       h.fullname, h.email
                FROM vps_hosts_followers f
                LEFT JOIN {$harvhubTable} h ON h.id = f.follower_id
                WHERE f.owner_id = ?
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$owner_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'followers' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'vps_get_requestors') {
        $owner_id = (int)($_POST['owner_id'] ?? 0);
        if ($owner_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid owner']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.requestor_id, r.request_status, r.created_at,
                       h.fullname, h.email
                FROM vps_hosts_requestors r
                LEFT JOIN {$harvhubTable} h ON h.id = r.requestor_id
                WHERE r.owner_id = ?
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$owner_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'requestors' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ============================================================
    // TAB 3: USERS WITH NO VPS
    // ============================================================
    if ($action === 'vps_list_without_vps') {
        try {
            $stmt = $pdo->query("
                SELECT h.id, h.fullname, h.email
                FROM {$harvhubTable} h
                LEFT JOIN vps v ON v.user_id = h.id
                WHERE v.id IS NULL
                ORDER BY h.fullname ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'users' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
?>

<!-- ============================================================ -->
<!-- VPS ADMIN CONTAINER                                          -->
<!-- ============================================================ -->
<div class="revenue-container" id="vps-admin-container">
    <!-- Header -->
    <div class="revenue-header">
        <h2>🖥️ VPS Management</h2>
    </div>

    <!-- Main Tabs -->
    <div class="revenue-tabs-wrapper">
        <div class="revenue-tabs main-tabs" id="vps-main-tabs">
            <button class="tab-btn active" data-tab="register" onclick="VpsAdmin.switchTab('register')">
                Register VPS
                <span class="tab-badge" id="vps-register-count">0</span>
            </button>
            <button class="tab-btn" data-tab="with-vps" onclick="VpsAdmin.switchTab('with-vps')">
                Users with VPS
                <span class="tab-badge" id="vps-with-count">0</span>
            </button>
            <button class="tab-btn" data-tab="no-vps" onclick="VpsAdmin.switchTab('no-vps')">
                Users with No VPS
                <span class="tab-badge" id="vps-no-count">0</span>
            </button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: REGISTER VPS                            -->
    <!-- ============================================ -->
    <div id="vps-tab-register" class="tab-content active">
        <!-- Sub Tabs: All Users | Registered | Unregistered -->
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="vps-register-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="all" onclick="VpsAdmin.switchRegisterSubTab('all')">
                    All Users
                    <span class="tab-badge" id="vps-register-all-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="registered" onclick="VpsAdmin.switchRegisterSubTab('registered')">
                    Already Registered
                    <span class="tab-badge" id="vps-register-registered-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="unregistered" onclick="VpsAdmin.switchRegisterSubTab('unregistered')">
                    Not Registered
                    <span class="tab-badge" id="vps-register-unregistered-count">0</span>
                </button>
            </div>
        </div>

        <!-- Summary Cubes -->
        <div class="summary-cubes" id="vps-register-cubes">
            <div class="summary-cube">
                <div class="cube-value" id="vps-register-total">0</div>
                <div class="cube-label">Total Users</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-register-with">0</div>
                <div class="cube-label">With VPS</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-register-without">0</div>
                <div class="cube-label">Without VPS</div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="vps-register-search-dummy" onclick="VpsAdmin.activateSearch('register')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="vps-register-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="vps-register-search-input" class="search-input" placeholder="Search users by name, email, or ID..." oninput="VpsAdmin.filterRegisterTable()" autocomplete="off">
                <span class="search-clear" id="vps-register-search-clear" onclick="VpsAdmin.clearRegisterSearch()" style="display:none;">x</span>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="vps-register-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>VPS Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="vps-register-body">
                        <tr><td colspan="4" style="text-align:center;padding:40px;color:#888;">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: USERS WITH VPS                          -->
    <!-- ============================================ -->
    <div id="vps-tab-with-vps" class="tab-content">
        <!-- Sub Tabs: All | Public | Private -->
        <div class="revenue-tabs-wrapper sub-tabs-wrapper">
            <div class="revenue-tabs sub-tabs" id="vps-with-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-subtab="all" onclick="VpsAdmin.switchWithSubTab('all')">
                    All
                    <span class="tab-badge" id="vps-with-all-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="public" onclick="VpsAdmin.switchWithSubTab('public')">
                    Public
                    <span class="tab-badge" id="vps-with-public-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="private" onclick="VpsAdmin.switchWithSubTab('private')">
                    Private
                    <span class="tab-badge" id="vps-with-private-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="has-followers" onclick="VpsAdmin.switchWithSubTab('has-followers')">
                    Has Followers
                    <span class="tab-badge" id="vps-with-followers-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-subtab="has-requests" onclick="VpsAdmin.switchWithSubTab('has-requests')">
                    Has Requests
                    <span class="tab-badge" id="vps-with-requests-count">0</span>
                </button>
            </div>
        </div>

        <!-- Summary Cubes -->
        <div class="summary-cubes" id="vps-with-cubes">
            <div class="summary-cube">
                <div class="cube-value" id="vps-with-total">0</div>
                <div class="cube-label">Total Hosts</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-with-public">0</div>
                <div class="cube-label">Public Hosts</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-with-private">0</div>
                <div class="cube-label">Private Hosts</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-with-followers">0</div>
                <div class="cube-label">Total Followers</div>
            </div>
            <div class="summary-cube">
                <div class="cube-value" id="vps-with-requests">0</div>
                <div class="cube-label">Total Requests</div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="vps-with-search-dummy" onclick="VpsAdmin.activateSearch('with')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search hosts by name, email, or location...</span>
            </div>
            <div class="search-bar search-bar-real" id="vps-with-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="vps-with-search-input" class="search-input" placeholder="Search hosts by name, email, or location..." oninput="VpsAdmin.filterWithTable()" autocomplete="off">
                <span class="search-clear" id="vps-with-search-clear" onclick="VpsAdmin.clearWithSearch()" style="display:none;">x</span>
            </div>
        </div>

        <!-- Hosts Table -->
        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="vps-with-table">
                    <thead>
                        <tr>
                            <th>Host</th>
                            <th>Server Location</th>
                            <th>IP Address</th>
                            <th>Visibility</th>
                            <th>Followers</th>
                            <th>Requests</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="vps-with-body">
                        <tr><td colspan="7" style="text-align:center;padding:40px;color:#888;">Loading hosts...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: USERS WITH NO VPS                       -->
    <!-- ============================================ -->
    <div id="vps-tab-no-vps" class="tab-content">
        <!-- Search Bar -->
        <div class="search-bar-wrapper">
            <div class="search-bar search-bar-dummy" id="vps-no-search-dummy" onclick="VpsAdmin.activateSearch('no')">
                <span class="search-icon">Q</span>
                <span class="search-placeholder">Search users by name, email, or ID...</span>
            </div>
            <div class="search-bar search-bar-real" id="vps-no-search-real" style="display:none;">
                <span class="search-icon">Q</span>
                <input type="text" id="vps-no-search-input" class="search-input" placeholder="Search users by name, email, or ID..." oninput="VpsAdmin.filterNoVpsTable()" autocomplete="off">
                <span class="search-clear" id="vps-no-search-clear" onclick="VpsAdmin.clearNoVpsSearch()" style="display:none;">x</span>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table-container">
            <div class="table-wrapper">
                <table class="revenue-table" id="vps-no-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="vps-no-body">
                        <tr><td colspan="3" style="text-align:center;padding:40px;color:#888;">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- HOST DETAILS OVERLAY (isolated view)          -->
<!-- ============================================ -->
<div id="vps-host-detail-overlay" class="detail-overlay" style="display:none;">
    <div class="detail-overlay-content">
        <div class="detail-overlay-header">
            <button class="back-btn" onclick="VpsAdmin.closeHostDetail()"><- Back</button>
            <h2 id="vps-detail-title">Host Details</h2>
            <span></span>
        </div>
        <div class="detail-overlay-body" id="vps-detail-body">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading host details...</p>
            </div>
        </div>
    </div>
    <input type="hidden" id="vps-login-id-hidden" value="<?php echo htmlspecialchars($serverAccount['admin_login_id'] ?? 'admin'); ?>">
</div>

<!-- ============================================ -->
<!-- REGISTER / EDIT VPS OVERLAY                   -->
<!-- ============================================ -->
<div id="vps-form-overlay" class="detail-overlay" style="display:none;">
    <div class="detail-overlay-content">
        <div class="detail-overlay-header">
            <button class="back-btn" onclick="VpsAdmin.closeForm()"><- Back</button>
            <h2 id="vps-form-title">Register VPS</h2>
            <span></span>
        </div>
        <div class="detail-overlay-body" id="vps-form-body">
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading form...</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- CUSTOM CONFIRM MODAL                          -->
<!-- ============================================ -->
<div id="vps-confirm-modal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="vps-confirm-title">Confirm Action</span>
            <span class="revenuemodal-close" onclick="VpsAdmin.closeConfirmModal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="vps-confirm-message">Are you sure?</p>
            <div class="revenuemodal-buttons">
                <button class="btn-cancel" onclick="VpsAdmin.closeConfirmModal()">Cancel</button>
                <button class="btn-confirm" id="vps-confirm-btn" onclick="VpsAdmin.confirmModalAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- PASSWORD MODAL                                -->
<!-- ============================================ -->
<div id="vps-password-modal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="vps-password-title">Security Check</span>
            <span class="revenuemodal-close" onclick="VpsAdmin.closePasswordModal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="vps-password-message">Please enter your admin password to continue.</p>
            <input type="password" id="vps-password-input" placeholder="Enter password" autocomplete="off">
            <div id="vps-password-error" style="color:#f44336;font-size:13px;margin-top:6px;display:none;"></div>
            <div class="revenuemodal-buttons">
                <button class="btn-cancel" onclick="VpsAdmin.closePasswordModal()">Cancel</button>
                <button class="btn-confirm" id="vps-password-confirm-btn" onclick="VpsAdmin.confirmPasswordModal()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- NOTIFICATION MODAL                            -->
<!-- ============================================ -->
<div id="vps-notification-modal" class="revenuemodal-overlay" style="display:none;">
    <div class="revenuemodal-container revenuemodal-small">
        <div class="revenuemodal-header">
            <span id="vps-notification-title">Notification</span>
            <span class="revenuemodal-close" onclick="VpsAdmin.closeNotificationModal()">x</span>
        </div>
        <div class="revenuemodal-body">
            <p id="vps-notification-message"></p>
            <div class="revenuemodal-buttons">
                <button class="btn-confirm" onclick="VpsAdmin.closeNotificationModal()">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
const VpsAdmin = {
    // ============================
    // DATA
    // ============================
    allUsers: [],
    filteredUsers: [],
    allWithVps: [],
    filteredWithVps: [],
    allWithoutVps: [],
    filteredWithoutVps: [],
    currentHostFollowers: [],
    currentHostRequestors: [],

    // ============================
    // STATE
    // ============================
    currentTab: 'register',
    currentRegisterSubTab: 'all',
    currentWithSubTab: 'all',
    registerSearchTerm: '',
    withSearchTerm: '',
    noSearchTerm: '',
    selectedHostId: null,
    isHostDetailOpen: false,
    isFormOpen: false,

    // Modal callbacks
    _confirmCallback: null,
    _passwordCallback: null,

    // ============================
    // INIT
    // ============================
    init: function () {
        this.loadUsers();
        this.loadWithVps();
        this.loadWithoutVps();
        this.bindEvents();
    },

    bindEvents: function () {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (VpsAdmin.isHostDetailOpen) VpsAdmin.closeHostDetail();
                if (VpsAdmin.isFormOpen) VpsAdmin.closeForm();
                VpsAdmin.closeConfirmModal();
                VpsAdmin.closePasswordModal();
                VpsAdmin.closeNotificationModal();
                VpsAdmin.deactivateSearch('register');
                VpsAdmin.deactivateSearch('with');
                VpsAdmin.deactivateSearch('no');
            }
            if (e.key === 'Enter') {
                if (document.getElementById('vps-password-modal').style.display === 'flex') {
                    VpsAdmin.confirmPasswordModal();
                }
                if (document.getElementById('vps-confirm-modal').style.display === 'flex') {
                    VpsAdmin.confirmModalAction();
                }
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.search-bar-wrapper') && !e.target.closest('.search-bar')) {
                VpsAdmin.deactivateSearch('register');
                VpsAdmin.deactivateSearch('with');
                VpsAdmin.deactivateSearch('no');
            }
        });
    },

    // ============================
    // TAB NAVIGATION
    // ============================
    switchTab: function (tab) {
        this.currentTab = tab;

        document.querySelectorAll('#vps-main-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        document.querySelectorAll('#vps-admin-container .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'vps-tab-' + tab);
        });
    },

    switchRegisterSubTab: function (subTab) {
        this.currentRegisterSubTab = subTab;
        document.querySelectorAll('#vps-register-sub-tabs .sub-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.subtab === subTab);
        });
        this.filteredUsers = this.getFilteredUsers();
        this.renderUsers();
        this.updateRegisterCubes();
    },

    switchWithSubTab: function (subTab) {
        this.currentWithSubTab = subTab;
        document.querySelectorAll('#vps-with-sub-tabs .sub-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.subtab === subTab);
        });
        this.filteredWithVps = this.getFilteredWithVps();
        this.renderWithVps();
        this.updateWithCubes();
    },

    // ============================
    // SEARCH
    // ============================
    activateSearch: function (tab) {
        const dummy = document.getElementById('vps-' + tab + '-search-dummy');
        const real = document.getElementById('vps-' + tab + '-search-real');
        const input = document.getElementById('vps-' + tab + '-search-input');
        if (dummy) dummy.style.display = 'none';
        if (real) real.style.display = 'flex';
        if (input) {
            input.focus();
            const termMap = { register: this.registerSearchTerm, with: this.withSearchTerm, no: this.noSearchTerm };
            if (termMap[tab]) input.value = termMap[tab];
        }
    },

    deactivateSearch: function (tab) {
        const dummy = document.getElementById('vps-' + tab + '-search-dummy');
        const real = document.getElementById('vps-' + tab + '-search-real');
        const termMap = { register: this.registerSearchTerm, with: this.withSearchTerm, no: this.noSearchTerm };
        if (termMap[tab]) {
            if (dummy) dummy.style.display = 'none';
            if (real) real.style.display = 'flex';
        } else {
            if (dummy) dummy.style.display = 'flex';
            if (real) real.style.display = 'none';
        }
    },

    filterRegisterTable: function () {
        const input = document.getElementById('vps-register-search-input');
        this.registerSearchTerm = input.value.trim();
        document.getElementById('vps-register-search-clear').style.display = this.registerSearchTerm ? 'block' : 'none';
        this.filteredUsers = this.getFilteredUsers();
        this.renderUsers();
        this.updateRegisterCubes();
    },

    clearRegisterSearch: function () {
        document.getElementById('vps-register-search-input').value = '';
        document.getElementById('vps-register-search-clear').style.display = 'none';
        this.registerSearchTerm = '';
        this.deactivateSearch('register');
        this.filteredUsers = this.getFilteredUsers();
        this.renderUsers();
        this.updateRegisterCubes();
    },

    filterWithTable: function () {
        const input = document.getElementById('vps-with-search-input');
        this.withSearchTerm = input.value.trim();
        document.getElementById('vps-with-search-clear').style.display = this.withSearchTerm ? 'block' : 'none';
        this.filteredWithVps = this.getFilteredWithVps();
        this.renderWithVps();
        this.updateWithCubes();
    },

    clearWithSearch: function () {
        document.getElementById('vps-with-search-input').value = '';
        document.getElementById('vps-with-search-clear').style.display = 'none';
        this.withSearchTerm = '';
        this.deactivateSearch('with');
        this.filteredWithVps = this.getFilteredWithVps();
        this.renderWithVps();
        this.updateWithCubes();
    },

    filterNoVpsTable: function () {
        const input = document.getElementById('vps-no-search-input');
        this.noSearchTerm = input.value.trim();
        document.getElementById('vps-no-search-clear').style.display = this.noSearchTerm ? 'block' : 'none';
        this.filteredWithoutVps = this.getFilteredWithoutVps();
        this.renderWithoutVps();
    },

    clearNoVpsSearch: function () {
        document.getElementById('vps-no-search-input').value = '';
        document.getElementById('vps-no-search-clear').style.display = 'none';
        this.noSearchTerm = '';
        this.deactivateSearch('no');
        this.filteredWithoutVps = this.getFilteredWithoutVps();
        this.renderWithoutVps();
    },

    // ============================
    // FILTERS
    // ============================
    getFilteredUsers: function () {
        let users = [...this.allUsers];
        if (this.currentRegisterSubTab === 'registered') {
            users = users.filter(u => u.has_vps);
        } else if (this.currentRegisterSubTab === 'unregistered') {
            users = users.filter(u => !u.has_vps);
        }
        if (this.registerSearchTerm) {
            const term = this.registerSearchTerm.toLowerCase();
            users = users.filter(u => {
                const name = (u.fullname || '').toLowerCase();
                const email = (u.email || '').toLowerCase();
                const id = String(u.id || '');
                return name.includes(term) || email.includes(term) || id.includes(term);
            });
        }
        return users;
    },

    getFilteredWithVps: function () {
        let users = [...this.allWithVps];
        if (this.currentWithSubTab === 'public') {
            users = users.filter(u => u.visibility === 'public');
        } else if (this.currentWithSubTab === 'private') {
            users = users.filter(u => u.visibility !== 'public');
        } else if (this.currentWithSubTab === 'has-followers') {
            users = users.filter(u => (u.follower_count || 0) > 0);
        } else if (this.currentWithSubTab === 'has-requests') {
            users = users.filter(u => (u.requestor_count || 0) > 0);
        }
        if (this.withSearchTerm) {
            const term = this.withSearchTerm.toLowerCase();
            users = users.filter(u => {
                const name = (u.fullname || '').toLowerCase();
                const email = (u.email || '').toLowerCase();
                const loc = (u.server_location || '').toLowerCase();
                const id = String(u.user_id || '');
                return name.includes(term) || email.includes(term) || loc.includes(term) || id.includes(term);
            });
        }
        return users;
    },

    getFilteredWithoutVps: function () {
        let users = [...this.allWithoutVps];
        if (this.noSearchTerm) {
            const term = this.noSearchTerm.toLowerCase();
            users = users.filter(u => {
                const name = (u.fullname || '').toLowerCase();
                const email = (u.email || '').toLowerCase();
                const id = String(u.id || '');
                return name.includes(term) || email.includes(term) || id.includes(term);
            });
        }
        return users;
    },

    // ============================
    // LOADERS
    // ============================
    loadUsers: function () {
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=vps_get_all_users'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.allUsers = data.users || [];
                this.filteredUsers = this.getFilteredUsers();
                this.renderUsers();
                this.updateRegisterCubes();
                this.updateRegisterBadges();
            } else {
                this.allUsers = [];
                this.filteredUsers = [];
                this.renderUsers();
            }
        })
        .catch(() => {
            this.allUsers = [];
            this.filteredUsers = [];
            this.renderUsers();
        });
    },

    loadWithVps: function () {
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=vps_list_with_vps'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.allWithVps = data.users || [];
                this.filteredWithVps = this.getFilteredWithVps();
                this.renderWithVps();
                this.updateWithCubes();
                this.updateWithBadges();
            } else {
                this.allWithVps = [];
                this.filteredWithVps = [];
                this.renderWithVps();
            }
        })
        .catch(() => {
            this.allWithVps = [];
            this.filteredWithVps = [];
            this.renderWithVps();
        });
    },

    loadWithoutVps: function () {
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=vps_list_without_vps'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                this.allWithoutVps = data.users || [];
                this.filteredWithoutVps = this.getFilteredWithoutVps();
                this.renderWithoutVps();
                this.updateBadge('vps-no-count', this.allWithoutVps.length);
            } else {
                this.allWithoutVps = [];
                this.filteredWithoutVps = [];
                this.renderWithoutVps();
            }
        })
        .catch(() => {
            this.allWithoutVps = [];
            this.filteredWithoutVps = [];
            this.renderWithoutVps();
        });
    },

    // ============================
    // RENDERERS
    // ============================
    renderUsers: function () {
        const tbody = document.getElementById('vps-register-body');
        const users = this.filteredUsers;
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:#888;">No users found</td></tr>';
            return;
        }
        let html = '';
        users.forEach(u => {
            const statusClass = u.has_vps ? 'status-confirmed' : 'status-unpaid';
            const statusLabel = u.has_vps ? 'Registered' : 'Not Registered';
            html += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-name">${this.escapeHtml(u.fullname || 'N/A')}</div>
                            <div class="user-id">ID: ${u.id}</div>
                        </div>
                    </td>
                    <td>${this.escapeHtml(u.email || 'N/A')}</td>
                    <td><span class="status-badge ${statusClass}">${statusLabel}</span></td>
                    <td>
                        <button class="action-select" onclick="VpsAdmin.openForm(${u.id})">
                            ${u.has_vps ? 'Edit VPS' : 'Register VPS'}
                        </button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    },

    renderWithVps: function () {
        const tbody = document.getElementById('vps-with-body');
        const users = this.filteredWithVps;
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#888;">No hosts found</td></tr>';
            return;
        }
        let html = '';
        users.forEach(u => {
            const visClass = u.visibility === 'public' ? 'status-confirmed' : 'status-failed';
            const visLabel = u.visibility === 'public' ? 'Public' : 'Private';
            html += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-name">${this.escapeHtml(u.fullname || 'N/A')}</div>
                            <div class="user-email">${this.escapeHtml(u.email || '')}</div>
                            <div class="user-id">ID: ${u.user_id}</div>
                        </div>
                    </td>
                    <td>${this.escapeHtml(u.server_location || 'N/A')}</td>
                    <td>${this.escapeHtml(u.vps_ip_address || 'N/A')}</td>
                    <td><span class="status-badge ${visClass}">${visLabel}</span></td>
                    <td><span class="status-badge status-active">${u.follower_count || 0}</span></td>
                    <td><span class="status-badge status-made">${u.requestor_count || 0}</span></td>
                    <td>
                        <button class="action-select" onclick="VpsAdmin.openHostDetail(${u.user_id})">View</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    },

    renderWithoutVps: function () {
        const tbody = document.getElementById('vps-no-body');
        const users = this.filteredWithoutVps;
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:40px;color:#888;">No users found</td></tr>';
            return;
        }
        let html = '';
        users.forEach(u => {
            html += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-name">${this.escapeHtml(u.fullname || 'N/A')}</div>
                            <div class="user-id">ID: ${u.id}</div>
                        </div>
                    </td>
                    <td>${this.escapeHtml(u.email || 'N/A')}</td>
                    <td><span class="status-badge status-unpaid">No VPS</span></td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    },

    // ============================
    // HOST DETAIL (isolated view)
    // ============================
    openHostDetail: function (userId) {
        this.selectedHostId = userId;
        this.isHostDetailOpen = true;
        document.getElementById('vps-host-detail-overlay').style.display = 'block';
        document.body.style.overflow = 'hidden';
        document.getElementById('vps-detail-title').textContent = 'Host Details - ID: ' + userId;
        document.getElementById('vps-detail-body').innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading host details...</p>
            </div>
        `;
        this.fetchHostDetail(userId);
    },

    fetchHostDetail: function (userId) {
        Promise.all([
            this.fetchAction('vps_get_details', { user_id: userId }),
            this.fetchAction('vps_get_followers', { owner_id: userId }),
            this.fetchAction('vps_get_requestors', { owner_id: userId })
        ])
        .then(([detailRes, followersRes, requestorsRes]) => {
            this.renderHostDetail(
                detailRes.success ? detailRes : null,
                followersRes.success ? (followersRes.followers || []) : [],
                requestorsRes.success ? (requestorsRes.requestors || []) : []
            );
        })
        .catch(err => {
            document.getElementById('vps-detail-body').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">x</div>
                    <div class="empty-text">Error loading host details</div>
                    <div class="empty-sub">${this.escapeHtml(err.message || 'Please try again')}</div>
                </div>
            `;
        });
    },

    renderHostDetail: function (detailRes, followers, requestors) {
        this.currentHostFollowers = followers;
        this.currentHostRequestors = requestors;

        const container = document.getElementById('vps-detail-body');

        if (!detailRes) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">x</div>
                    <div class="empty-text">Host not found</div>
                </div>
            `;
            return;
        }

        const u = detailRes.user;
        const v = detailRes.vps;

        let html = `
            <div class="user-detail-grid">
                <div class="detail-card-full">
                    <div class="detail-user-header">
                        <div>
                            <h3>${this.escapeHtml(u.fullname || 'N/A')}</h3>
                            <p class="detail-user-email">${this.escapeHtml(u.email || 'N/A')}</p>
                            <p class="detail-user-id">ID: ${u.id}</p>
                        </div>
                        <div class="detail-user-status">
                            <span class="status-badge ${v.visibility === 'public' ? 'status-confirmed' : 'status-failed'}">
                                ${v.visibility === 'public' ? 'Public' : 'Private'}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="detail-stats-grid">
                    <div class="detail-stat-card">
                        <div class="stat-label">Followers</div>
                        <div class="stat-value">${followers.length}</div>
                    </div>
                    <div class="detail-stat-card">
                        <div class="stat-label">Requests</div>
                        <div class="stat-value">${requestors.length}</div>
                    </div>
                    <div class="detail-stat-card">
                        <div class="stat-label">Subscription</div>
                        <div class="stat-value">${v.subscription_duration || 0}d</div>
                    </div>
                </div>

                <div class="detail-card-full">
                    <h3 style="margin:0 0 12px 0;font-size:15px;">VPS Information</h3>
        `;

        const rows = [
            ['Server Location', v.server_location],
            ['Subscription Duration', (v.subscription_duration || 0) + ' days'],
            ['Subscription Start', v.subscription_start_date || '—'],
            ['VPS IP Address', v.vps_ip_address || '—'],
            ['VPS Provider Login', v.vps_provider_login || '—'],
            ['VPS Provider Password', v.vps_provider_password || '—'],
            ['Computer Username', v.computer_username || '—'],
            ['Computer Password', v.computer_password || '—'],
            ['RDP Password', v.rdp_password || '—'],
            ['Visibility', v.visibility || 'private']
        ];
        rows.forEach(row => {
            html += `
                <div class="detail-row">
                    <span class="detail-label">${this.escapeHtml(row[0])}</span>
                    <span class="detail-value">${this.escapeHtml(row[1])}</span>
                </div>
            `;
        });

        html += `
                </div>

                <div class="detail-tabs-wrapper">
                    <div class="detail-tabs">
                        <button class="detail-tab-btn active" data-detail-tab="followers" onclick="VpsAdmin.switchDetailTab('followers')">
                            Followers
                            <span class="tab-badge">${followers.length}</span>
                        </button>
                        <button class="detail-tab-btn" data-detail-tab="requestors" onclick="VpsAdmin.switchDetailTab('requestors')">
                            Requestors
                            <span class="tab-badge">${requestors.length}</span>
                        </button>
                    </div>
                </div>

                <div id="vps-detail-tab-followers" class="detail-tab-content active">
                    ${this.renderFollowersList(followers)}
                </div>
                <div id="vps-detail-tab-requestors" class="detail-tab-content" style="display:none;">
                    ${this.renderRequestorsList(requestors)}
                </div>
            </div>
        `;
        container.innerHTML = html;
    },

    renderFollowersList: function (followers) {
        if (!followers.length) {
            return '<div class="empty-state"><div class="empty-text">No followers yet</div></div>';
        }
        let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
        followers.forEach(f => {
            const cls = 'status-' + (f.host_status || 'active');
            html += `
                <div class="detail-card-full" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;">
                    <div>
                        <div style="font-weight:600;">${this.escapeHtml(f.fullname || ('User #' + f.follower_id))}</div>
                        <div style="font-size:12px;opacity:0.6;">${this.escapeHtml(f.email || '')}</div>
                    </div>
                    <span class="status-badge ${cls}">${this.escapeHtml(f.host_status || 'unknown')}</span>
                </div>
            `;
        });
        html += '</div>';
        return html;
    },

    renderRequestorsList: function (requestors) {
        if (!requestors.length) {
            return '<div class="empty-state"><div class="empty-text">No requests yet</div></div>';
        }
        let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
        requestors.forEach(r => {
            const cls = 'status-' + (r.request_status || 'pending');
            html += `
                <div class="detail-card-full" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;">
                    <div>
                        <div style="font-weight:600;">${this.escapeHtml(r.fullname || ('User #' + r.requestor_id))}</div>
                        <div style="font-size:12px;opacity:0.6;">${this.escapeHtml(r.email || '')}</div>
                    </div>
                    <span class="status-badge ${cls}">${this.escapeHtml(r.request_status || 'pending')}</span>
                </div>
            `;
        });
        html += '</div>';
        return html;
    },

    switchDetailTab: function (tabId) {
        document.querySelectorAll('#vps-host-detail-overlay .detail-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.detailTab === tabId);
        });
        document.querySelectorAll('#vps-host-detail-overlay .detail-tab-content').forEach(content => {
            const isActive = content.id === 'vps-detail-tab-' + tabId;
            content.classList.toggle('active', isActive);
            content.style.display = isActive ? 'block' : 'none';
        });
    },

    closeHostDetail: function () {
        document.getElementById('vps-host-detail-overlay').style.display = 'none';
        document.body.style.overflow = '';
        this.isHostDetailOpen = false;
        this.selectedHostId = null;
    },

    // ============================
    // REGISTER/EDIT FORM (isolated view)
    // ============================
    openForm: function (userId) {
        this.isFormOpen = true;
        document.getElementById('vps-form-overlay').style.display = 'block';
        document.body.style.overflow = 'hidden';
        document.getElementById('vps-form-title').textContent = 'Register VPS';
        document.getElementById('vps-form-body').innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <p>Loading form...</p>
            </div>
        `;
        this.fetchAction('vps_get_user_vps', { user_id: userId })
            .then(data => {
                if (!data.success) {
                    this.showNotification(data.error || 'Failed to load user', 'Error', true);
                    this.closeForm();
                    return;
                }
                this.renderForm(data.user, data.vps);
            })
            .catch(() => {
                this.showNotification('Error loading user data', 'Error', true);
                this.closeForm();
            });
    },

    renderForm: function (user, vps) {
        vps = vps || {};
        document.getElementById('vps-form-title').textContent =
            vps.id ? ('Edit VPS - ' + (user.fullname || ('User #' + user.id))) : ('Register VPS - ' + (user.fullname || ('User #' + user.id)));

        const container = document.getElementById('vps-form-body');
        container.innerHTML = `
            <input type="hidden" id="vps-form-user-id" value="${user.id}">
            <div class="detail-card-full">
                <div class="detail-user-header">
                    <div>
                        <h3>${this.escapeHtml(user.fullname || 'N/A')}</h3>
                        <p class="detail-user-email">${this.escapeHtml(user.email || 'N/A')}</p>
                    </div>
                </div>
                <div class="vps-form-grid">
                    <div class="vps-form-group">
                        <label>Server Location *</label>
                        <input type="text" id="vps-form-server-location" value="${this.escapeAttr(vps.server_location || '')}" placeholder="e.g. New York, US">
                    </div>
                    <div class="vps-form-group">
                        <label>Subscription Duration (days)</label>
                        <input type="number" id="vps-form-subscription-duration" value="${vps.subscription_duration || 30}" min="1">
                    </div>
                    <div class="vps-form-group">
                        <label>Subscription Start Date</label>
                        <input type="date" id="vps-form-subscription-start" value="${vps.subscription_start_date || ''}">
                    </div>
                    <div class="vps-form-group">
                        <label>VPS IP Address</label>
                        <input type="text" id="vps-form-ip" value="${this.escapeAttr(vps.vps_ip_address || '')}" placeholder="e.g. 192.168.1.100">
                    </div>
                    <div class="vps-form-group">
                        <label>VPS Provider Login</label>
                        <input type="text" id="vps-form-provider-login" value="${this.escapeAttr(vps.vps_provider_login || '')}">
                    </div>
                    <div class="vps-form-group">
                        <label>VPS Provider Password</label>
                        <input type="text" id="vps-form-provider-password" value="${this.escapeAttr(vps.vps_provider_password || '')}">
                    </div>
                    <div class="vps-form-group">
                        <label>Computer Username</label>
                        <input type="text" id="vps-form-computer-username" value="${this.escapeAttr(vps.computer_username || '')}">
                    </div>
                    <div class="vps-form-group">
                        <label>Computer Password</label>
                        <input type="text" id="vps-form-computer-password" value="${this.escapeAttr(vps.computer_password || '')}">
                    </div>
                    <div class="vps-form-group">
                        <label>RDP Password</label>
                        <input type="text" id="vps-form-rdp-password" value="${this.escapeAttr(vps.rdp_password || '')}">
                    </div>
                    <div class="vps-form-group">
                        <label>Visibility</label>
                        <select id="vps-form-visibility">
                            <option value="private" ${vps.visibility !== 'public' ? 'selected' : ''}>Private</option>
                            <option value="public" ${vps.visibility === 'public' ? 'selected' : ''}>Public</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                    <button class="btn-cancel" style="padding:10px 20px;border-radius:6px;border:1px solid var(--border-color);background:transparent;color:var(--text-color);cursor:pointer;" onclick="VpsAdmin.closeForm()">Cancel</button>
                    <button class="btn-confirm" id="vps-form-save-btn" style="padding:10px 20px;border-radius:6px;border:none;background:var(--accent-color);color:white;cursor:pointer;font-weight:600;" onclick="VpsAdmin.saveForm()">💾 Save VPS</button>
                </div>
            </div>
        `;
    },

    closeForm: function () {
        document.getElementById('vps-form-overlay').style.display = 'none';
        document.body.style.overflow = '';
        this.isFormOpen = false;
    },

    saveForm: function () {
        const userId = document.getElementById('vps-form-user-id').value;
        if (!userId) return;

        const self = this;
        this.showPasswordModal(
            'Save VPS',
            'Enter admin password to save VPS for User ID ' + userId,
            function (password) {
                const loginId = document.getElementById('vps-login-id-hidden')?.value || '';

                const saveBtn = document.getElementById('vps-form-save-btn');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                }

                const body = new URLSearchParams({
                    action: 'vps_save_user_vps',
                    user_id: userId,
                    server_location: document.getElementById('vps-form-server-location').value.trim(),
                    subscription_duration: document.getElementById('vps-form-subscription-duration').value,
                    subscription_start_date: document.getElementById('vps-form-subscription-start').value,
                    vps_ip_address: document.getElementById('vps-form-ip').value.trim(),
                    vps_provider_login: document.getElementById('vps-form-provider-login').value.trim(),
                    vps_provider_password: document.getElementById('vps-form-provider-password').value,
                    computer_username: document.getElementById('vps-form-computer-username').value.trim(),
                    computer_password: document.getElementById('vps-form-computer-password').value,
                    rdp_password: document.getElementById('vps-form-rdp-password').value,
                    visibility: document.getElementById('vps-form-visibility').value,
                    admin_password: password,
                    login_id: loginId
                });

                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                })
                .then(r => r.json())
                .then(data => {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save VPS';
                    }
                    if (data.success) {
                        self.showNotification(data.message || 'Saved successfully', 'Success', false);
                        self.closeForm();
                        self.loadUsers();
                        self.loadWithVps();
                        self.loadWithoutVps();
                    } else {
                        if (data.error === 'Invalid password') {
                            self.showNotification('Password verification failed', 'Error', true);
                        } else {
                            self.showNotification(data.error || 'Failed to save', 'Error', true);
                        }
                    }
                })
                .catch(() => {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = '💾 Save VPS';
                    }
                    self.showNotification('Network error', 'Error', true);
                });
            }
        );
    },

    // ============================
    // CONFIRM MODAL
    // ============================
    showConfirmModal: function (title, message, callback) {
        document.getElementById('vps-confirm-title').textContent = title || 'Confirm Action';
        document.getElementById('vps-confirm-message').textContent = message || 'Are you sure?';
        this._confirmCallback = callback;
        document.getElementById('vps-confirm-modal').style.display = 'flex';
    },

    closeConfirmModal: function () {
        document.getElementById('vps-confirm-modal').style.display = 'none';
        this._confirmCallback = null;
    },

    confirmModalAction: function () {
        const cb = this._confirmCallback;
        this.closeConfirmModal();
        if (typeof cb === 'function') cb();
    },

    // ============================
    // PASSWORD MODAL
    // ============================
    showPasswordModal: function (title, message, callback) {
        document.getElementById('vps-password-title').textContent = title || 'Security Check';
        document.getElementById('vps-password-message').textContent = message || 'Please enter your admin password.';
        document.getElementById('vps-password-input').value = '';
        document.getElementById('vps-password-error').style.display = 'none';
        this._passwordCallback = callback;
        document.getElementById('vps-password-modal').style.display = 'flex';
        setTimeout(() => document.getElementById('vps-password-input').focus(), 100);
    },

    closePasswordModal: function () {
        document.getElementById('vps-password-modal').style.display = 'none';
        this._passwordCallback = null;
        document.getElementById('vps-password-error').style.display = 'none';
    },

    confirmPasswordModal: function () {
        const pw = document.getElementById('vps-password-input').value;
        const errEl = document.getElementById('vps-password-error');
        if (!pw) {
            errEl.textContent = 'Please enter your password.';
            errEl.style.display = 'block';
            return;
        }
        const cb = this._passwordCallback;
        this.closePasswordModal();
        if (typeof cb === 'function') cb(pw);
    },

    // ============================
    // NOTIFICATION MODAL
    // ============================
    showNotification: function (message, title, isError) {
        document.getElementById('vps-notification-title').textContent = title || (isError ? 'Error' : 'Success');
        document.getElementById('vps-notification-message').textContent = message || '';
        document.getElementById('vps-notification-modal').style.display = 'flex';
    },

    closeNotificationModal: function () {
        document.getElementById('vps-notification-modal').style.display = 'none';
    },

    // ============================
    // FETCH HELPER
    // ============================
    fetchAction: function (action, params) {
        const body = new URLSearchParams(Object.assign({ action: action }, params || {}));
        return fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(r => r.json());
    },

    // ============================
    // BADGES / CUBES
    // ============================
    updateBadge: function (id, count) {
        const el = document.getElementById(id);
        if (el) el.textContent = count || 0;
    },

    updateRegisterBadges: function () {
        const total = this.allUsers.length;
        const registered = this.allUsers.filter(u => u.has_vps).length;
        const unregistered = total - registered;

        this.updateBadge('vps-register-count', total);
        this.updateBadge('vps-register-all-count', total);
        this.updateBadge('vps-register-registered-count', registered);
        this.updateBadge('vps-register-unregistered-count', unregistered);
    },

    updateWithBadges: function () {
        const total = this.allWithVps.length;
        const publicCount = this.allWithVps.filter(u => u.visibility === 'public').length;
        const privateCount = total - publicCount;
        const hasFollowers = this.allWithVps.filter(u => (u.follower_count || 0) > 0).length;
        const hasRequests = this.allWithVps.filter(u => (u.requestor_count || 0) > 0).length;

        this.updateBadge('vps-with-count', total);
        this.updateBadge('vps-with-all-count', total);
        this.updateBadge('vps-with-public-count', publicCount);
        this.updateBadge('vps-with-private-count', privateCount);
        this.updateBadge('vps-with-followers-count', hasFollowers);
        this.updateBadge('vps-with-requests-count', hasRequests);
    },

    updateRegisterCubes: function () {
        const total = this.allUsers.length;
        const registered = this.allUsers.filter(u => u.has_vps).length;
        const unregistered = total - registered;

        document.getElementById('vps-register-total').textContent = total;
        document.getElementById('vps-register-with').textContent = registered;
        document.getElementById('vps-register-without').textContent = unregistered;
    },

    updateWithCubes: function () {
        const total = this.allWithVps.length;
        const publicCount = this.allWithVps.filter(u => u.visibility === 'public').length;
        const privateCount = total - publicCount;
        const totalFollowers = this.allWithVps.reduce((sum, u) => sum + (u.follower_count || 0), 0);
        const totalRequests = this.allWithVps.reduce((sum, u) => sum + (u.requestor_count || 0), 0);

        document.getElementById('vps-with-total').textContent = total;
        document.getElementById('vps-with-public').textContent = publicCount;
        document.getElementById('vps-with-private').textContent = privateCount;
        document.getElementById('vps-with-followers').textContent = totalFollowers;
        document.getElementById('vps-with-requests').textContent = totalRequests;
    },

    // ============================
    // UTILITIES
    // ============================
    escapeHtml: function (str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            if (m === "'") return '&#39;';
            return m;
        });
    },

    escapeAttr: function (str) {
        return this.escapeHtml(str);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    VpsAdmin.init();
});
</script>

