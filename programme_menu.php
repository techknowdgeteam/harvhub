<?php
// programme_menu.php — programme-scoped menu
session_start();
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("Database connection failed."); }

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

// Resolve user
$u = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$u->execute([$email]);
$user = $u->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php?role=developer"); exit; }
$userId = (int)$user['id'];

// Resolve active programme
$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($activeProgrammeId <= 0) {
    $q = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
    $q->execute([$userId]);
    $activeProgrammeId = (int)($q->fetchColumn() ?: 0);
    if ($activeProgrammeId > 0) $_SESSION['selected_programme_id'] = $activeProgrammeId;
}

$programme = null;
if ($activeProgrammeId > 0) {
    $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $q->execute([$activeProgrammeId, $userId]);
    $programme = $q->fetch(PDO::FETCH_ASSOC);
}

$fullName  = $user['fullname'] ?? 'User';
$userEmail = $user['email'] ?? '';
$darkMode  = (int)($user['dark_mode'] ?? 0);
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';
$avatarInitial = strtoupper(substr($fullName, 0, 1));

$broker_connected = $programme && (!empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']));
$programme_name = $programme ? ($programme['program_name'] ?: 'Unnamed Programme') : '';

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    if (isset($_POST['menu_live_state'])) {
        header('Content-Type: application/json; charset=utf-8');
        $hasVps = false;
        if ($programme) {
            try {
                $s = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
                $s->execute([(int)$programme['id']]);
                $hasVps = (bool)$s->fetchColumn();
            } catch (Throwable $e) {}
            if (!$hasVps) {
                try {
                    $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
                    $s->execute([(int)$programme['id']]);
                    $hasVps = (bool)$s->fetchColumn();
                } catch (Throwable $e) {}
            }
        }
        echo json_encode([
            'success'         => true,
            'fullname'        => $fullName,
            'email'           => $userEmail,
            'avatar_initial'  => $avatarInitial,
            'dark_mode'       => $darkMode,
            'broker_connected'=> $broker_connected,
            'has_vps'         => $hasVps,
            'programme_name'  => $programme_name,
            'programme_id'    => $activeProgrammeId,
        ]);
        exit;
    }

    if (isset($_POST['delete_programme_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');

        if (!$programme) { echo json_encode(['success' => false, 'message' => 'No programme selected.']); exit; }

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM programme WHERE userid = ?");
            $count->execute([$userId]);
            if ((int)$count->fetchColumn() <= 1) {
                echo json_encode(['success' => false, 'message' => 'You cannot delete your only programme.']);
                exit;
            }

            $del = $pdo->prepare("DELETE FROM programme WHERE id = ? AND userid = ?");
            $del->execute([$activeProgrammeId, $userId]);

            $next = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
            $next->execute([$userId]);
            $nextId = (int)($next->fetchColumn() ?: 0);
            $_SESSION['selected_programme_id'] = $nextId;

            echo json_encode(['success' => true, 'next_programme_id' => $nextId]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete programme.']);
        }
        exit;
    }

    if (isset($_POST['toggle_dark_mode_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        $v = isset($_POST['dark_mode_checkbox']) ? (int)$_POST['dark_mode_checkbox'] : 0;
        $v = $v ? 1 : 0;
        try {
            $u = $pdo->prepare("UPDATE harvhub SET dark_mode = ? WHERE LOWER(email) = ?");
            $u->execute([$v, $email]);
            echo json_encode(['success' => true, 'dark_mode' => $v]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>HarvHub</title>
<?php include 'style.php'; ?>
<?php include 'menu_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="custom-body page-container">
    <div class="page-view" id="menuPage">
        <div class="menu-container">

            <div class="user-card profile-clickable" role="button" tabindex="0" aria-label="View profile">
                <div class="user-avatar" id="menuUserAvatar"><?= htmlspecialchars($avatarInitial) ?></div>
                <div class="user-info">
                    <div class="user-name" id="menuUserName"><?= htmlspecialchars($fullName) ?></div>
                    <div class="user-email" id="menuUserEmail"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <span class="profile-chevron"><i class="fa-solid fa-chevron-right"></i></span>
            </div>

            <div class="menu-items">
                <a href="#" class="menu-item" data-tab="vps">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-computer"></i></span>
                        <div class="item-text">
                            <span class="item-title">Vps</span>
                            <span class="item-desc">The computer hosting your programme's broker terminal.</span>
                        </div>
                    </div>
                    <span class="item-arrow" style="color: var(--success);">›</span>
                </a>

                <a href="#" class="menu-item" data-tab="connect_trader_broker">
                    <div class="item-left">
                        <span class="item-icon">🔗</span>
                        <div class="item-text">
                            <span class="item-title" id="menuBrokerItemTitle"><?= $broker_connected ? 'Update Broker' : 'Connect Broker' ?></span>
                            <span class="item-desc" id="menuBrokerItemDesc"><?= $broker_connected ? 'Update your broker connection' : 'Connect your MT5 account' ?></span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </a>

                <!-- NEW: Account Management (below Update Broker) -->
                <a href="#" class="menu-item" data-tab="account_management">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-sliders"></i></span>
                        <div class="item-text">
                            <span class="item-title">Account Management</span>
                            <span class="item-desc">Risk rules, breakeven, JSON config for your programme.</span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </a>

                <hr class="menu-divider">

                <div class="menu-item dark-mode-toggle-item">
                    <div class="item-left">
                        <span class="dark-mode-icon" id="darkModeIcon"><?= ($darkMode === 1) ? '🌙' : '☀️' ?></span>
                        <div class="item-text"><span class="item-title">Dark Mode</span></div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="mode-label" id="darkModeLabel"><?= ($darkMode === 1) ? 'On' : 'Off' ?></span>
                        <div class="toggle-switch" id="toggleSwitchContainer">
                            <input type="checkbox" id="darkModeToggle" <?= ($darkMode === 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </div>
                </div>

                <hr class="menu-divider">

                <a href="#" class="menu-item danger" data-tab="disconnect_trader_broker" id="menuDisconnectBrokerItem" style="<?= $broker_connected ? '' : 'display:none;' ?>">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-plug-circle-minus"></i></span>
                        <div class="item-text">
                            <span class="item-title">Disconnect Broker</span>
                            <span class="item-desc">Disconnect your programme's MT5 account</span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </a>

                <div class="menu-item" onclick="openLogoutModal()">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                        <div class="item-text">
                            <span class="item-title">Logout</span>
                            <span class="item-desc">Sign out of your account</span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </div>
            </div>

            <div class="version-info">HarvHub</div>
        </div>
    </div>

    <!-- PROFILE PAGE -->
    <div class="page-view profile-page" id="profilePage">
        <div class="profile-page-inner">
            <div class="profile-page-hero">
                <button type="button" class="profile-page-back" onclick="hideProfilePage()" aria-label="Back">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="profile-page-avatar" id="profilePageAvatar"><?= htmlspecialchars($avatarInitial) ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Full name</div>
                <div class="profile-page-value" id="profileFullNameValue"><?= htmlspecialchars($fullName) ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Email</div>
                <div class="profile-page-value" id="profileEmailValue"><?= htmlspecialchars($userEmail) ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Programme</div>
                <div class="profile-page-value" id="profileProgrammeValue"><?= htmlspecialchars($programme_name ?: '—') ?></div>
            </div>

            <div class="profile-page-actions">
                <a href="forgot_password.php?source=dashboard" class="profile-page-btn">
                    <i class="fa-solid fa-key"></i>
                    <span>Change Password</span>
                </a>
                <button type="button" class="profile-page-btn profile-page-btn-danger" onclick="openDeleteProgrammeModal()">
                    <i class="fa-solid fa-trash"></i>
                    <span>Delete Programme</span>
                </button>
            </div>
        </div>
    </div>

    <!-- DELETE PROGRAMME MODAL -->
    <div class="modal-overlay" id="deleteProgrammeModal">
        <div class="modal-box">
            <h2 class="modal-title-danger">Delete Programme</h2>
            <p>Are you sure you want to permanently delete
                <strong id="deleteProgrammeName">—</strong>?
                This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeDeleteProgrammeModal()">Cancel</button>
                <button class="btn-danger-confirm" id="deleteProgrammeConfirmBtn" onclick="confirmDeleteProgramme()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="logoutModal">
        <div class="modal-box">
            <h2>Logout</h2>
            <p>Are you sure you want to logout of your account?</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeLogoutModal()">Cancel</button>
                <button class="btn-danger-confirm" onclick="confirmLogout()">Yes, Logout</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var IN_SHELL = false;
        try { IN_SHELL = (window.parent && window.parent !== window); } catch (e) {}

        function go(tab) {
            if (IN_SHELL) { try { window.parent.postMessage({ type: 'switchTab', tab: tab }, '*'); return; } catch (e) {} }
            window.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);
        }
        window.harvhubGoToTab = go;

        document.body.classList.add('page-trader_menu');

        document.addEventListener('click', function (ev) {
            var link = ev.target.closest ? ev.target.closest('[data-tab]') : null;
            if (!link) return;
            var tab = link.getAttribute('data-tab');
            if (!tab) return;
            ev.preventDefault();
            go(tab);
        }, true);
    })();

    function showProfilePage() {
        var m = document.getElementById('menuPage');
        var p = document.getElementById('profilePage');
        if (m) m.classList.add('hidden');
        if (p) p.classList.add('active');
        window.scrollTo(0, 0);
        document.body.classList.add('profile-page-open');
        try { window.parent.postMessage({ type: 'bodyClass', add: ['profile-page-open'], remove: [] }, '*'); } catch (e) {}
    }
    function hideProfilePage() {
        var m = document.getElementById('menuPage');
        var p = document.getElementById('profilePage');
        if (p) p.classList.remove('active');
        if (m) m.classList.remove('hidden');
        window.scrollTo(0, 0);
        document.body.classList.remove('profile-page-open');
        try { window.parent.postMessage({ type: 'bodyClass', add: [], remove: ['profile-page-open'] }, '*'); } catch (e) {}
    }

    function syncModalOverlayState() {
        var anyOpen = !!document.querySelector('.modal-overlay.active');
        if (anyOpen) document.body.classList.add('modal-overlay-open');
        else document.body.classList.remove('modal-overlay-open');
        try {
            window.parent.postMessage({
                type: 'bodyClass',
                add: anyOpen ? ['modal-overlay-open'] : [],
                remove: anyOpen ? [] : ['modal-overlay-open']
            }, '*');
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        var card = document.querySelector('.profile-clickable');
        if (card) {
            card.addEventListener('click', function (e) { e.preventDefault(); showProfilePage(); });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showProfilePage(); }
            });
        }
    });

    // ---- Logout ----
    function openLogoutModal() { var m = document.getElementById('logoutModal'); if (m) { m.classList.add('active'); syncModalOverlayState(); } }
    function closeLogoutModal() { var m = document.getElementById('logoutModal'); if (m) { m.classList.remove('active'); syncModalOverlayState(); } }
    function confirmLogout() {
        if (window.parent && window.parent !== window) {
            try { window.parent.postMessage({ type: 'saveLastApp' }, '*'); } catch (e) {}
            try { window.parent.postMessage({ type: 'logout' }, '*'); } catch (e) {}
            return;
        }
        window.location.href = 'traderapp.php?logout=1';
    }

    // ---- Delete programme ----
    function openDeleteProgrammeModal() {
        var el = document.getElementById('deleteProgrammeName');
        if (el) el.textContent = '<?= htmlspecialchars($programme_name, ENT_QUOTES) ?>';
        var m = document.getElementById('deleteProgrammeModal');
        if (m) { m.classList.add('active'); syncModalOverlayState(); }
    }
    function closeDeleteProgrammeModal() {
        var m = document.getElementById('deleteProgrammeModal');
        if (m) { m.classList.remove('active'); syncModalOverlayState(); }
    }
    function confirmDeleteProgramme() {
        var btn = document.getElementById('deleteProgrammeConfirmBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }
        fetch('programme_menu.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'delete_programme_ajax=1'
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Yes, Delete'; }
            if (data && data.success) {
                try {
                    if (window.parent && window.parent !== window) {
                        window.parent.location.href = 'traderapp.php?tab=signals&programme_deleted=1';
                    } else {
                        window.location.href = 'traderapp.php?tab=signals&programme_deleted=1';
                    }
                } catch (e) { window.location.reload(); }
            } else {
                alert((data && data.message) || 'Unable to delete programme.');
                closeDeleteProgrammeModal();
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Yes, Delete'; }
            alert('Network error.');
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.id === 'logoutModal') closeLogoutModal();
        if (e.target.id === 'deleteProgrammeModal') closeDeleteProgrammeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var lm = document.getElementById('logoutModal');
            if (lm && lm.classList.contains('active')) { closeLogoutModal(); return; }
            var dm = document.getElementById('deleteProgrammeModal');
            if (dm && dm.classList.contains('active')) { closeDeleteProgrammeModal(); return; }
            var pp = document.getElementById('profilePage');
            if (pp && pp.classList.contains('active')) hideProfilePage();
        }
    });

    // ---- Dark mode toggle ----
    (function () {
        var toggle = document.getElementById('darkModeToggle');
        if (!toggle) return;
        function persist(dark) {
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'themeRequest', dark: !!dark }, '*');
                }
            } catch (e) {}
            fetch('programme_menu.php', {
                method: 'POST',
                headers: { 'Content-Type':'application/x-www-form-urlencoded' },
                body: 'toggle_dark_mode_ajax=1&dark_mode_checkbox=' + (dark ? 1 : 0),
                credentials: 'same-origin'
            }).catch(function () {});
        }
        toggle.addEventListener('change', function () {
            var on = this.checked ? 1 : 0;
            document.body.classList.toggle('dark-mode', !!on);
            var icon = document.getElementById('darkModeIcon');
            var label = document.getElementById('darkModeLabel');
            if (icon) icon.textContent = on ? '🌙' : '☀️';
            if (label) label.textContent = on ? 'On' : 'Off';
            persist(on);
        });
    })();

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') {
            var d = !!e.data.dark;
            document.body.classList.toggle('dark-mode', d);
            var t = document.getElementById('darkModeToggle');
            var ic = document.getElementById('darkModeIcon');
            var lb = document.getElementById('darkModeLabel');
            if (t) t.checked = d;
            if (ic) ic.textContent = d ? '🌙' : '☀️';
            if (lb) lb.textContent = d ? 'On' : 'Off';
        }
    });

    window.showProfilePage = showProfilePage;
    window.hideProfilePage = hideProfilePage;
    window.openLogoutModal = openLogoutModal;
    window.closeLogoutModal = closeLogoutModal;
    window.confirmLogout = confirmLogout;
    window.openDeleteProgrammeModal = openDeleteProgrammeModal;
    window.closeDeleteProgrammeModal = closeDeleteProgrammeModal;
    window.confirmDeleteProgramme = confirmDeleteProgramme;

    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
</script>
</body>
</html>