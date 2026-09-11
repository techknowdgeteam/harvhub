<?php
// menu.php
session_start();

// Check for logged-in user
if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// Database credentials
require_once 'usersdb.php';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed.");
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

$userId       = (int)$user['id'];
$fullName     = $user['fullname'] ?? 'User';
$userEmail    = $user['email'] ?? '';

// ===== DARK MODE CONTROL =====
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// Check if broker is connected
$broker_connected = (!empty($user['broker']) && !empty($user['server']) && !empty($user['login']));

// Profile avatar initial
$avatarInitial = strtoupper(substr($fullName, 0, 1));

// ============================================================
// INVESTED PROGRAMME (from programme_investors)
// ============================================================
// The "invested_with" column on harvhub is no longer used.
// Instead, we look up the user's active investment in
// programme_investors and resolve the developer name via the
// programme → harvhub join.
// ============================================================
$investedProgrammeName    = '';
$investedDeveloperName    = '';
$userHasProgramme         = false;

try {
    $progStmt = $pdo->prepare("
        SELECT pi.programme_id, pi.developerid,
               p.program_name,
               h.fullname AS developer_name
        FROM programme_investors pi
        LEFT JOIN programme p ON p.id = pi.programme_id
        LEFT JOIN harvhub h  ON h.id = pi.developerid
        WHERE pi.investorid = ?
        ORDER BY pi.id ASC
        LIMIT 1
    ");
    $progStmt->execute([$userId]);
    $investedRow = $progStmt->fetch(PDO::FETCH_ASSOC);

    if ($investedRow) {
        $userHasProgramme      = true;
        $investedProgrammeName = $investedRow['program_name'] ?: '';
        $investedDeveloperName = $investedRow['developer_name'] ?: '';
    }
} catch (PDOException $e) {
    $userHasProgramme = false;
}

// ============================================================
// TIER LIMIT RESOLUTION
// ============================================================
$serverAccountTable = 'server_account';

// Fetch server account tier_limit
$serverTiers = [];
try {
    $stmtServer = $pdo->prepare("SELECT tier_limit FROM {$serverAccountTable} WHERE id = 1");
    $stmtServer->execute();
    $serverRow = $stmtServer->fetch(PDO::FETCH_ASSOC);

    if ($serverRow && !empty($serverRow['tier_limit'])) {
        $decoded = json_decode($serverRow['tier_limit'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $serverTiers = $decoded;
        }
    }
} catch (Exception $e) {
    $serverTiers = [];
}

// Parse the user's tier_limit (comma-separated keys)
$userTierRaw = trim($user['tier_limit'] ?? '');
$userTierKeys = [];
if ($userTierRaw !== '') {
    foreach (explode(',', $userTierRaw) as $key) {
        $key = trim($key);
        if ($key !== '') {
            $userTierKeys[] = $key;
        }
    }
}

// Auto-assign first tier if user has none AND server has tiers
if (empty($userTierKeys) && !empty($serverTiers)) {
    $firstKey = array_key_first($serverTiers);
    if ($firstKey !== null) {
        $userTierKeys = [$firstKey];

        try {
            $upd = $pdo->prepare("UPDATE {$tableName} SET tier_limit = ? WHERE email = ?");
            $upd->execute([$firstKey, $email]);
        } catch (Exception $e) {
            // silent fail
        }
    }
}

$normalizeKey = function ($k) {
    return strtolower(preg_replace('/[\s\-]+/', '_', trim($k)));
};

$serverTiersByNormalized = [];
foreach ($serverTiers as $tierName => $tierObj) {
    $serverTiersByNormalized[$normalizeKey($tierName)] = [
        'original_key' => $tierName,
        'data' => $tierObj,
    ];
}

$resolvedTiers = [];
foreach ($userTierKeys as $userKey) {
    $norm = $normalizeKey($userKey);
    if (isset($serverTiersByNormalized[$norm])) {
        $resolvedTiers[] = $serverTiersByNormalized[$norm];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<title>🌾Harvhub</title>
<?php include 'style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">
<div class="custom-body page-container">

    <!-- ============================================================
         MENU PAGE (default view)
         ============================================================ -->
    <div class="page-view" id="menuPage">
        <div class="menu-container">
            <div class="menu-header">
                <h1>Menu</h1>
                <p>Account settings and options</p>
            </div>

            <!-- User Card - CLICKABLE PROFILE -->
            <div class="user-card profile-clickable" role="button" tabindex="0" aria-label="View profile">
                <div class="user-avatar"><?= htmlspecialchars($avatarInitial) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($fullName) ?></div>
                    <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <span class="profile-chevron"><i class="fa-solid fa-chevron-right"></i></span>
            </div>

            <!-- Menu Items -->
            <div class="menu-items">
                <a href="vps.php" class="menu-item">
                        <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-computer"></i></span>
                            <div class="item-text">
                                <span class="item-title">Vps</span>
                                <span class="item-desc">The computer hosting your broker's terminal for program trades.</span>
                            </div>
                        </div>
                        <span class="item-arrow" style="color: var(--success);">›</span>
                </a>

                <!-- Connect / Update Broker -->
                <a href="#connect_investor_broker" class="menu-item" onclick="event.preventDefault(); if (window.navigateTo) { window.navigateTo('connect_investor_broker'); } else { window.location.href = 'app.php#connect_investor_broker'; }">
                    <div class="item-left">
                        <span class="item-icon">🔗</span>
                        <div class="item-text">
                            <span class="item-title">
                                <?php if ($broker_connected): ?>
                                     Update Broker
                                <?php else: ?>
                                     Connect Broker
                                <?php endif; ?>
                            </span>
                            <span class="item-desc">
                                <?php if ($broker_connected): ?>
                                    Update your broker connection
                                <?php else: ?>
                                    Connect your MT5 account
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </a>

                <!-- Invested Programme (from programme_investors) -->
                <?php if ($userHasProgramme): ?>
                    <a href="programmes.php" class="menu-item">
                        <div class="item-left">
                            <span class="item-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <div class="item-text">
                                <span class="item-title">
                                    <?php if ($investedDeveloperName !== ''): ?>
                                        Invested in <?= htmlspecialchars($investedDeveloperName) ?>'s <?= htmlspecialchars($investedProgrammeName ?: 'Your account trades Provider') ?> Programme
                                    <?php else: ?>
                                        Invested in a Programme
                                    <?php endif; ?>
                                </span>
                                <span class="item-desc">
                                    Your account trades Provider
                                </span>
                            </div>
                        </div>
                        <span class="item-arrow" style="color: var(--success);">✓</span>
                    </a>
                <?php endif; ?>
                <hr class="menu-divider">

                <!-- Dark Mode Toggle -->
                <div class="menu-item dark-mode-toggle-item">
                    <div class="item-left">
                        <span class="dark-mode-icon" id="darkModeIcon">
                            <?= ($darkMode === 1) ? '🌙' : '☀️' ?>
                        </span>
                        <div class="item-text">
                            <span class="item-title">Dark Mode</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span class="mode-label" id="darkModeLabel"><?= ($darkMode === 1) ? 'On' : 'Off' ?></span>
                        <div class="toggle-switch" id="toggleSwitchContainer">
                            <input type="checkbox" id="darkModeToggle" <?= ($darkMode === 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </div>
                    </div>
                </div>

                <hr class="menu-divider">
                <a href="dev_dashboard.php" class="menu-item">
                        <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-chart-area"></i></span>
                            <div class="item-text">
                                <span class="item-title">Developer</span>
                                <span class="item-desc">Build your strategy. Harvhub AI analyzes, spots entries, and executes for you.</span>
                            </div>
                        </div>
                        <span class="item-arrow" style="color: var(--success);">›</span>
                </a>

                <!-- Logout -->
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

                <!-- Disconnect Account -->
                <?php if ($broker_connected): ?>
                <div class="menu-item danger" onclick="window.location.href='disconnect_broker.php'">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-plug-circle-minus"></i></span>
                        <div class="item-text">
                            <span class="item-title">Disconnect Broker</span>
                            <span class="item-desc">Disconnect your MT5 account</span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </div>
                <?php endif; ?>
            </div>

            <div class="version-info">
                HarvHub v1.0.0
            </div>
        </div>
    </div>

    <!-- ============================================================
         PROFILE PAGE (full-screen page, no overlay)
         ============================================================ -->
    <div class="page-view profile-page" id="profilePage">
        <div class="profile-page-inner">

            <!-- Header with back/close button -->
            <div class="profile-page-header">
                <button type="button" class="profile-page-close" onclick="hideProfilePage()" aria-label="Back to menu">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <h2 class="profile-page-title">Profile</h2>
            </div>

            <!-- Avatar -->
            <div class="profile-page-avatar-wrap">
                <div class="profile-page-avatar"><?= htmlspecialchars($avatarInitial) ?></div>
            </div>

            <!-- Full Name -->
            <div class="profile-page-section">
                <div class="profile-page-label">Fullname</div>
                <div class="profile-page-value"><?= htmlspecialchars($fullName) ?></div>
            </div>

            <!-- Email -->
            <div class="profile-page-section">
                <div class="profile-page-label">Email</div>
                <div class="profile-page-value profile-page-email"><?= htmlspecialchars($userEmail) ?></div>

                <!-- TIER LIMIT DISPLAY -->
                <div class="profile-tier-block" id="profileTierBlock">
                    <div class="profile-tier-label">Tier Limit</div>

                    <?php if (empty($resolvedTiers)): ?>
                        <div class="profile-tier-empty">
                            No tier assignment yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($resolvedTiers as $tier): ?>
                            <div class="profile-tier-entry">
                                <div class="profile-tier-entry-key">
                                    <?= htmlspecialchars($tier['original_key']) ?>
                                </div>
                                <div class="profile-tier-entry-fields">
                                    <?php if (is_array($tier['data']) && !empty($tier['data'])): ?>
                                        <?php foreach ($tier['data'] as $fieldName => $fieldValue): ?>
                                            <div class="profile-tier-field-row">
                                                <span class="profile-tier-field-name">
                                                    <?= htmlspecialchars($fieldName) ?>
                                                </span>
                                                <span class="profile-tier-field-value">
                                                    <?= htmlspecialchars(is_scalar($fieldValue) ? (string)$fieldValue : json_encode($fieldValue)) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="profile-tier-empty-fields">No fields</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Forgot Password Button -->
            <div class="profile-page-actions" style="display: none;">
                <a href="forgot_password.php?source=dashboard" class="profile-page-btn">
                    <i class="fa-solid fa-key"></i>
                    <span>Forgot Password</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Logout Confirmation Modal -->
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

    <div style="margin-bottom:120px"></div>
</div>

<script>
    // ==================== PAGE SWITCHING ====================
    function showProfilePage() {
        document.getElementById('menuPage').classList.add('hidden');
        document.getElementById('profilePage').classList.add('active');
        window.scrollTo(0, 0);
    }

    function hideProfilePage() {
        document.getElementById('profilePage').classList.remove('active');
        document.getElementById('menuPage').classList.remove('hidden');
        window.scrollTo(0, 0);
    }

    // ==================== LOGOUT MODAL ====================
    function openLogoutModal() {
        var modal = document.getElementById('logoutModal');
        if (modal) modal.classList.add('active');
    }

    function closeLogoutModal() {
        var modal = document.getElementById('logoutModal');
        if (modal) modal.classList.remove('active');
    }

    function confirmLogout() {
        window.location.href = '?logout=1';
    }

    document.addEventListener('click', function(event) {
        if (event.target.id === 'logoutModal') {
            closeLogoutModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            var logoutModal = document.getElementById('logoutModal');
            if (logoutModal && logoutModal.classList.contains('active')) {
                closeLogoutModal();
                return;
            }
            var profilePage = document.getElementById('profilePage');
            if (profilePage && profilePage.classList.contains('active')) {
                hideProfilePage();
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        var profileCard = document.querySelector('.profile-clickable');
        if (profileCard) {
            profileCard.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    showProfilePage();
                }
            });
        }
    });

    window.showProfilePage = showProfilePage;
    window.hideProfilePage = hideProfilePage;
    window.openLogoutModal = openLogoutModal;
    window.closeLogoutModal = closeLogoutModal;
    window.confirmLogout = confirmLogout;
</script>

</body>
</html>