<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_email'])) { header("Location: index.php"); exit; }
$email = strtolower($_SESSION['user_email']);
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("Database connection failed."); }

/* =====================================================================
   RESOLVE ACTIVE SUB-ACCOUNT (never silently fall back to main account)
   ===================================================================== */
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

if (!$user) {
    // Fall back to any of THIS user's sub-accounts (never the main account)
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    // Last resort: the user's own record (main account)
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) { header("Location: index.php"); exit; }

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$subAccountName     = trim((string)($user['sub_account_name'] ?? ''));

$fullName = $user['fullname'] ?? 'User';
$userEmail = $user['email'] ?? '';
$userFirstName = trim((string)($user['first_name'] ?? ''));
$userLastName  = trim((string)($user['last_name']  ?? ''));
$userUsername  = trim((string)($user['username']   ?? ''));

$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$broker_connected = (!empty($user['broker']) && !empty($user['server']) && !empty($user['login']));
$avatarInitial = strtoupper(substr($fullName, 0, 1));

// ==================== HELPERS ====================
if (!function_exists('resolveDeveloperDisplayName')) {
    function resolveDeveloperDisplayName(array $devRow) {
        $username = trim((string)($devRow['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($devRow['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($devRow['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($devRow['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('normalizeAccountName')) {
    function normalizeAccountName($name) {
        $name = preg_replace('/[^A-Za-z0-9 ]+/', '', (string)$name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = strtolower(trim($name));
        return substr($name, 0, 20);
    }
}

function canDeleteCurrentAccount($pdo, $tableName, $revenueHistoryTable, $user, $subAccountId, $serverAccountTable) {
    $userId = (int)$user['id'];
    $email  = strtolower((string)$user['email']);

    $contract_duration_cfg = 30;
    try {
        $cfgStmt = $pdo->query("SELECT contract_duration FROM $serverAccountTable LIMIT 1");
        $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
        if ($cfg && !empty($cfg['contract_duration'])) {
            $contract_duration_cfg = (int)$cfg['contract_duration'];
        }
    } catch (Exception $e) {}

    $exec_start = $user['execution_start_date'] ?? null;
    if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
        try {
            $start = new DateTime($exec_start);
            $end   = clone $start;
            $end->modify("+{$contract_duration_cfg} days");
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $endClone = clone $end;
            $endClone->setTime(0, 0, 0);
            $daysLeft = (int)$today->diff($endClone)->format('%r%a');
            if ($daysLeft > 0) return false;
        } catch (Exception $e) {}
    }

    $payment_issue_statuses = [
        'unpaid-payment', 'unpaid',
        'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
        'contract-cancelled-payment-required',
        'payment-failed', 'failed-payment',
        'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
        'payment-made', 'contract-cancelled-payment-made',
        'pending_payment'
    ];

    $loyalties_status = $user['loyalties'] ?? null;
    if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
        return false;
    }

    try {
        $revStmt = $pdo->prepare("SELECT loyalties FROM $revenueHistoryTable WHERE user_email = ? ORDER BY created_at DESC LIMIT 1");
        $revStmt->execute([$email]);
        $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
        if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            return false;
        }
    } catch (Exception $e) {}

    return true;
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {

    // ---------- MENU LIVE STATE (SCOPED TO ACTIVE SUB-ACCOUNT) ----------
    if (isset($_POST['menu_live_state'])) {
        header('Content-Type: application/json; charset=utf-8');

        // Resolve the CURRENT active sub-account id from session, then load that row
        $freshActiveSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);
        $freshUser = null;

        if ($freshActiveSubId > 0) {
            $freshStmt = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
            $freshStmt->execute([$freshActiveSubId, $email]);
            $freshUser = $freshStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$freshUser) {
            $freshStmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
            $freshStmt->execute([$email]);
            $freshUser = $freshStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$freshUser) {
            $freshStmt = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
            $freshStmt->execute([$email]);
            $freshUser = $freshStmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$freshUser) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
        }

        $freshUserId       = (int)$freshUser['id'];
        $freshSubId        = (int)($freshUser['sub_account_id'] ?? $freshUserId);
        $freshFullName     = $freshUser['fullname'] ?? 'User';
        $freshAvatarInitial = strtoupper(substr($freshFullName !== '' ? $freshFullName : 'U', 0, 1));
        $freshBrokerConnected = (!empty($freshUser['broker']) && !empty($freshUser['server']) && !empty($freshUser['login']));
        $freshSubName = trim((string)($freshUser['sub_account_name'] ?? ''));

        // Programme lookup: scoped to THIS sub-account
        $liveHasProgramme  = false;
        $liveProgrammeName = '';
        $liveDeveloperName = '';
        try {
            $progStmt = $pdo->prepare("
                SELECT pi.programme_id, pi.developerid, p.program_name,
                       h.fullname, h.first_name, h.last_name, h.username
                FROM programme_investors pi
                LEFT JOIN programme p ON p.id = pi.programme_id
                LEFT JOIN harvhub h ON h.id = pi.developerid
                WHERE pi.investorid = ?
                  AND (pi.sub_account_id = ? OR pi.sub_account_id IS NULL)
                ORDER BY pi.id ASC LIMIT 1
            ");
            $progStmt->execute([$freshUserId, $freshSubId]);
            $liveRow = $progStmt->fetch(PDO::FETCH_ASSOC);
            if ($liveRow && !empty($liveRow['programme_id'])) {
                $liveHasProgramme  = true;
                $liveProgrammeName = $liveRow['program_name'] ?: '';
                $liveDeveloperName = resolveDeveloperDisplayName($liveRow);
            }
        } catch (PDOException $e) {}

        $displayName = $freshFullName;
        if (trim((string)($freshUser['first_name'] ?? '')) !== '') {
            $displayName = trim((string)$freshUser['first_name']);
            if (trim((string)($freshUser['last_name'] ?? '')) !== '') {
                $displayName .= ' ' . trim((string)$freshUser['last_name']);
            }
        }

        echo json_encode([
            'success'           => true,
            'id'                => $freshUserId,
            'fullname'          => $displayName,
            'email'             => $freshUser['email'] ?? '',
            'avatar_initial'    => $freshAvatarInitial,
            'dark_mode'         => (int)($freshUser['dark_mode'] ?? 0),
            'broker_connected'  => $freshBrokerConnected,
            'has_programme'     => $liveHasProgramme,
            'programme_name'    => $liveProgrammeName,
            'developer_name'    => $liveDeveloperName,
            'invested_with'     => $liveDeveloperName !== 'N/A' ? $liveDeveloperName : '',
            'username'          => trim((string)($freshUser['username'] ?? '')),
            'first_name'        => trim((string)($freshUser['first_name'] ?? '')),
            'last_name'         => trim((string)($freshUser['last_name'] ?? '')),
            'last_app'          => $freshUser['last_app'] ?? null,
            'sub_account_name'  => $freshSubName,
            'sub_account_id'    => $freshSubId,
            'is_main_account'   => (int)($freshUser['is_main_account'] ?? 0),
        ]);
        exit;
    }

    // ---------- CHANGE ACCOUNT NAME ----------
    if (isset($_POST['change_account_name_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');

        $raw = (string)($_POST['new_account_name'] ?? '');
        $normalized = normalizeAccountName($raw);

        if ($normalized === '') {
            echo json_encode(['success' => false, 'message' => 'Account name is required.']);
            exit;
        }
        if (strlen($normalized) > 20) {
            echo json_encode(['success' => false, 'message' => 'Account name cannot exceed 20 characters.']);
            exit;
        }

        try {
            if ($mainAccountId > 0) {
                $chk = $pdo->prepare("
                    SELECT id FROM $tableName
                    WHERE main_account_id = ?
                      AND sub_account_id <> ?
                      AND LOWER(sub_account_name) = ?
                    LIMIT 1
                ");
                $chk->execute([$mainAccountId, $activeSubAccountId, $normalized]);
            } else {
                $chk = $pdo->prepare("
                    SELECT id FROM $tableName
                    WHERE sub_account_id <> ?
                      AND LOWER(sub_account_name) = ?
                      AND LOWER(email) = ?
                    LIMIT 1
                ");
                $chk->execute([$activeSubAccountId, $normalized, $email]);
            }
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'That account name is already used. Please choose another.']);
                exit;
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to verify uniqueness. Please try again.']);
            exit;
        }

        try {
            $upd = $pdo->prepare("UPDATE $tableName SET sub_account_name = ? WHERE id = ?");
            $upd->execute([$normalized, $userId]);
            echo json_encode(['success' => true, 'message' => 'Account name updated.', 'sub_account_name' => $normalized]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update account name. Please try again.']);
        }
        exit;
    }

    // ---------- DELETE SUB ACCOUNT ----------
    if (isset($_POST['delete_sub_account_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');

        $fr = $pdo->prepare("SELECT * FROM $tableName WHERE id = ? LIMIT 1");
        $fr->execute([$userId]);
        $fresh = $fr->fetch(PDO::FETCH_ASSOC);
        if (!$fresh) {
            echo json_encode(['success' => false, 'message' => 'Account not found.']);
            exit;
        }

        try {
            if ($mainAccountId > 0) {
                $countQ = $pdo->prepare("SELECT COUNT(*) AS c FROM $tableName WHERE main_account_id = ?");
                $countQ->execute([$mainAccountId]);
            } else {
                $countQ = $pdo->prepare("SELECT COUNT(*) AS c FROM $tableName WHERE LOWER(email) = ?");
                $countQ->execute([$email]);
            }
            $countRow = $countQ->fetch(PDO::FETCH_ASSOC);
            $total = (int)($countRow['c'] ?? 0);
            if ($total <= 1) {
                echo json_encode(['success' => false, 'message' => 'You cannot delete your only account.']);
                exit;
            }
        } catch (Throwable $e) {}

        $revenueHistoryTable = 'revenue_history';
        $serverAccountTable  = 'server_account';

        if (!canDeleteCurrentAccount($pdo, $tableName, $revenueHistoryTable, $fresh, $activeSubAccountId, $serverAccountTable)) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete this account while a contract is active or a payment action is pending.']);
            exit;
        }

        $fallback = null;
        try {
            if ($mainAccountId > 0) {
                $fb = $pdo->prepare("
                    SELECT * FROM $tableName
                    WHERE main_account_id = ? AND id <> ?
                    ORDER BY id ASC LIMIT 1
                ");
                $fb->execute([$mainAccountId, $userId]);
            } else {
                $fb = $pdo->prepare("
                    SELECT * FROM $tableName
                    WHERE LOWER(email) = ? AND id <> ?
                    ORDER BY id ASC LIMIT 1
                ");
                $fb->execute([$email, $userId]);
            }
            $fallback = $fb->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        if (!$fallback) {
            echo json_encode(['success' => false, 'message' => 'No fallback account found.']);
            exit;
        }

        try {
            $del = $pdo->prepare("DELETE FROM $tableName WHERE id = ?");
            $del->execute([$userId]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete the account.']);
            exit;
        }

        $_SESSION['user_email'] = strtolower($fallback['email']);
        $_SESSION['active_sub_account_id'] = (int)$fallback['sub_account_id'];
        $_SESSION['active_main_account_id'] = (int)$fallback['main_account_id'];

        try {
            $lastAccount = 's' . (int)$fallback['sub_account_id'];
            $updLast = $pdo->prepare("UPDATE $tableName SET last_account = ? WHERE id = ?");
            $updLast->execute([$lastAccount, (int)$fallback['id']]);
        } catch (Throwable $e) {}

        echo json_encode([
            'success'         => true,
            'message'         => 'Account deleted.',
            'fallback_sub_id' => (int)$fallback['sub_account_id'],
            'fallback_name'   => $fallback['sub_account_name']
        ]);
        exit;
    }

    // ---------- CHANGE USERNAME ----------
    if (isset($_POST['change_username_ajax'])) {
        header('Content-Type: application/json; charset=utf-8');

        $newUsername = trim($_POST['new_username'] ?? '');
        $password    = $_POST['password'] ?? '';

        if ($newUsername === '' || mb_strlen($newUsername) > 80) {
            echo json_encode(['success' => false, 'message' => 'Username is required (max 80 characters).']);
            exit;
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,80}$/', $newUsername)) {
            echo json_encode(['success' => false, 'message' => 'Username may only contain letters, numbers, dots, underscores, and hyphens (3–80 characters).']);
            exit;
        }
        if ($password === '') {
            echo json_encode(['success' => false, 'message' => 'Password is required.']);
            exit;
        }

        $storedPass = $user['password'] ?? '';
        $passwordOk = false;
        if ($storedPass !== '') {
            if (password_verify($password, $storedPass)) {
                $passwordOk = true;
            } elseif ($password === $storedPass) {
                $passwordOk = true;
            }
        }
        if (!$passwordOk) {
            echo json_encode(['success' => false, 'message' => 'Incorrect password. Please try again.']);
            exit;
        }

        if (strcasecmp($newUsername, $userUsername) === 0) {
            echo json_encode(['success' => true, 'message' => 'Username unchanged.', 'username' => $userUsername]);
            exit;
        }

        try {
            $chk = $pdo->prepare("SELECT id FROM $tableName WHERE LOWER(username) = LOWER(?) AND email <> ? LIMIT 1");
            $chk->execute([$newUsername, $email]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'That username is already taken. Please choose another.']);
                exit;
            }
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Unable to verify username uniqueness. Please try again.']);
            exit;
        }

        try {
            $upd = $pdo->prepare("UPDATE $tableName SET username = ? WHERE email = ?");
            $upd->execute([$newUsername, $email]);
            echo json_encode(['success' => true, 'message' => 'Username updated.', 'username' => $newUsername]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update username. Please try again.']);
        }
        exit;
    }

    // ---------- SAVE LAST APP ----------
    if (isset($_POST['save_last_app'])) {
        header('Content-Type: application/json; charset=utf-8');

        $app = trim((string)($_POST['app'] ?? ''));
        $allowed = ['investorapp', 'trader_app', 'managerapp'];

        if ($app === '' || !in_array($app, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid app value.']);
            exit;
        }

        try {
            $upd = $pdo->prepare("UPDATE $tableName SET last_app = ? WHERE email = ?");
            $upd->execute([$app, $email]);
            echo json_encode(['success' => true, 'last_app' => $app]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to save app.']);
        }
        exit;
    }
}

// ==================== FETCH INVESTED PROGRAMME (SCOPED) ====================
$investedProgrammeName = '';
$investedDeveloperName = '';
$userHasProgramme = false;
try {
    $progStmt = $pdo->prepare("
        SELECT pi.programme_id, pi.developerid, p.program_name,
               h.fullname, h.first_name, h.last_name, h.username
        FROM programme_investors pi
        LEFT JOIN programme p ON p.id = pi.programme_id
        LEFT JOIN harvhub h ON h.id = pi.developerid
        WHERE pi.investorid = ?
          AND (pi.sub_account_id = ? OR pi.sub_account_id IS NULL)
        ORDER BY pi.id ASC LIMIT 1
    ");
    $progStmt->execute([$userId, $activeSubAccountId]);
    $investedRow = $progStmt->fetch(PDO::FETCH_ASSOC);
    if ($investedRow && !empty($investedRow['programme_id'])) {
        $userHasProgramme = true;
        $investedProgrammeName = $investedRow['program_name'] ?: '';
        $investedDeveloperName = resolveDeveloperDisplayName($investedRow);
    }
} catch (PDOException $e) {}

// ==================== FETCH SERVER TIERS ====================
$serverAccountTable = 'server_account';
$revenueHistoryTable = 'revenue_history';
$serverTiers = [];
try {
    $stmtServer = $pdo->prepare("SELECT tier_limit FROM {$serverAccountTable} WHERE id = 1");
    $stmtServer->execute();
    $serverRow = $stmtServer->fetch(PDO::FETCH_ASSOC);
    if ($serverRow && !empty($serverRow['tier_limit'])) {
        $decoded = json_decode($serverRow['tier_limit'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $serverTiers = $decoded;
    }
} catch (Exception $e) {}

$userTierRaw = trim($user['tier_limit'] ?? '');
$userTierKeys = [];
if ($userTierRaw !== '') {
    foreach (explode(',', $userTierRaw) as $key) {
        $key = trim($key);
        if ($key !== '') $userTierKeys[] = $key;
    }
}
if (empty($userTierKeys) && !empty($serverTiers)) {
    $firstKey = array_key_first($serverTiers);
    if ($firstKey !== null) {
        $userTierKeys = [$firstKey];
        try {
            $upd = $pdo->prepare("UPDATE {$tableName} SET tier_limit = ? WHERE email = ?");
            $upd->execute([$firstKey, $email]);
        } catch (Exception $e) {}
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

// ==================== CAN DELETE CURRENT ACCOUNT? ====================
$canDeleteCurrent = canDeleteCurrentAccount($pdo, $tableName, $revenueHistoryTable, $user, $activeSubAccountId, $serverAccountTable);

try {
    if ($mainAccountId > 0) {
        $cntQ = $pdo->prepare("SELECT COUNT(*) AS c FROM $tableName WHERE main_account_id = ?");
        $cntQ->execute([$mainAccountId]);
    } else {
        $cntQ = $pdo->prepare("SELECT COUNT(*) AS c FROM $tableName WHERE LOWER(email) = ?");
        $cntQ->execute([$email]);
    }
    $cntRow = $cntQ->fetch(PDO::FETCH_ASSOC);
    if ((int)($cntRow['c'] ?? 0) <= 1) {
        $canDeleteCurrent = false;
    }
} catch (Throwable $e) {
    $canDeleteCurrent = false;
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
                            <span class="item-desc">The computer hosting your broker's terminal for program trades.</span>
                        </div>
                    </div>
                    <span class="item-arrow" style="color: var(--success);">›</span>
                </a>

                <a href="#" class="menu-item" data-tab="connect_investor_broker">
                    <div class="item-left">
                        <span class="item-icon">🔗</span>
                        <div class="item-text">
                            <span class="item-title" id="menuBrokerItemTitle">
                                <?php if ($broker_connected): ?> Update Broker <?php else: ?> Connect Broker <?php endif; ?>
                            </span>
                            <span class="item-desc" id="menuBrokerItemDesc">
                                <?php if ($broker_connected): ?> Update your broker connection <?php else: ?> Connect your MT5 account <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <span class="item-arrow">›</span>
                </a>

                <a href="#" class="menu-item" data-tab="programmes" id="menuProgrammeItem" style="<?= $userHasProgramme ? '' : 'display:none;' ?>">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                        <div class="item-text">
                            <span class="item-title" id="menuProgrammeTitle">
                                <?php if ($userHasProgramme && $investedDeveloperName !== '' && $investedDeveloperName !== 'N/A'): ?>
                                    Invested in <?= htmlspecialchars($investedDeveloperName) ?>'s <?= htmlspecialchars($investedProgrammeName ?: 'Your account trades Provider') ?> Programme
                                <?php elseif ($userHasProgramme): ?>
                                    Invested in a Programme
                                <?php else: ?>
                                    Invested in a Programme
                                <?php endif; ?>
                            </span>
                            <span class="item-desc">Your account trades Provider</span>
                        </div>
                    </div>
                    <span class="item-arrow" style="color: var(--success);">✓</span>
                </a>

                <hr class="menu-divider">

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

                <a href="#" class="menu-item danger" data-tab="disconnect_broker" id="menuDisconnectBrokerItem" style="<?= $broker_connected ? '' : 'display:none;' ?>">
                    <div class="item-left">
                        <span class="item-icon"><i class="fa-solid fa-plug-circle-minus"></i></span>
                        <div class="item-text">
                            <span class="item-title">Disconnect Broker</span>
                            <span class="item-desc">Disconnect your MT5 account</span>
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

            <div class="version-info">HarvestHub</div>
        </div>
    </div>

    <!-- PROFILE PAGE -->
    <div class="page-view profile-page" id="profilePage">
        <div class="profile-page-inner">

            <div class="profile-page-hero">
                <button type="button" class="profile-page-back" onclick="hideProfilePage()" aria-label="Back to menu">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <div class="profile-page-avatar" id="profilePageAvatar"><?= htmlspecialchars($avatarInitial) ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Account name</div>
                <div class="profile-page-value" id="profileAccountNameDisplay">
                    <?= htmlspecialchars($subAccountName !== '' ? $subAccountName : '—') ?>
                </div>
                <button type="button" class="profile-page-btn profile-page-btn-secondary" id="profileChangeAccountNameBtn" onclick="openAccountNameEdit()">
                    <i class="fa-solid fa-pen"></i>
                    <span>Change Account Name</span>
                </button>

                <div class="profile-username-edit" id="profileAccountNameEdit" style="display:none;">
                    <div class="profile-username-edit-msg" id="profileAccountNameMsg" style="display:none;"></div>
                    <label class="profile-username-edit-label" for="profileNewAccountName">New account name</label>
                    <input type="text" id="profileNewAccountName" class="profile-username-edit-input"
                           maxlength="20" autocomplete="off"
                           value="<?= htmlspecialchars($subAccountName) ?>">
                    <div class="profile-username-edit-hint">Letters, numbers and spaces only. Max 20 characters.</div>
                    <div class="profile-username-edit-actions">
                        <button type="button" class="profile-page-btn profile-page-btn-secondary" onclick="closeAccountNameEdit()">Cancel</button>
                        <button type="button" class="profile-page-btn" id="profileAccountNameSaveBtn" onclick="submitAccountNameEdit()">Save</button>
                    </div>
                </div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">First name</div>
                <div class="profile-page-value" id="profileFirstNameValue"><?= htmlspecialchars($userFirstName !== '' ? $userFirstName : '—') ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Last name</div>
                <div class="profile-page-value" id="profileLastNameValue"><?= htmlspecialchars($userLastName !== '' ? $userLastName : '—') ?></div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Username</div>
                <div class="profile-page-value" id="profileUsernameDisplay">
                    <?= htmlspecialchars($userUsername !== '' ? $userUsername : '—') ?>
                </div>
                <button type="button" class="profile-page-btn profile-page-btn-secondary" id="profileChangeUsernameBtn" onclick="openUsernameEdit()">
                    <i class="fa-solid fa-pen"></i>
                    <span>Change Username</span>
                </button>

                <div class="profile-username-edit" id="profileUsernameEdit" style="display:none;">
                    <div class="profile-username-edit-msg" id="profileUsernameMsg" style="display:none;"></div>
                    <label class="profile-username-edit-label" for="profileNewUsername">New username</label>
                    <input type="text" id="profileNewUsername" class="profile-username-edit-input"
                           maxlength="80" autocomplete="username"
                           pattern="[A-Za-z0-9._\-]{3,80}"
                           value="<?= htmlspecialchars($userUsername) ?>">
                    <div class="profile-username-edit-hint">3–80 characters; letters, numbers, dots, underscores, hyphens.</div>
                    <div class="profile-username-edit-actions">
                        <button type="button" class="profile-page-btn profile-page-btn-secondary" onclick="closeUsernameEdit()">Cancel</button>
                        <button type="button" class="profile-page-btn" id="profileUsernameSaveBtn" onclick="submitUsernameEdit()">Save</button>
                    </div>
                </div>
            </div>

            <div class="profile-page-section">
                <div class="profile-page-label">Tier Limit</div>
                <?php if (empty($resolvedTiers)): ?>
                    <div class="profile-tier-empty">No tier assignment yet.</div>
                <?php else: ?>
                    <?php foreach ($resolvedTiers as $tier): ?>
                        <div class="profile-tier-entry">
                            <div class="profile-tier-entry-key"><?= htmlspecialchars($tier['original_key']) ?></div>
                            <div class="profile-tier-entry-fields">
                                <?php if (is_array($tier['data']) && !empty($tier['data'])): ?>
                                    <?php foreach ($tier['data'] as $fieldName => $fieldValue): ?>
                                        <div class="profile-tier-field-row">
                                            <span class="profile-tier-field-name"><?= htmlspecialchars($fieldName) ?></span>
                                            <span class="profile-tier-field-value"><?= htmlspecialchars(is_scalar($fieldValue) ? (string)$fieldValue : json_encode($fieldValue)) ?></span>
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

            <div class="profile-page-actions">
                <a href="forgot_password.php?source=dashboard" class="profile-page-btn">
                    <i class="fa-solid fa-key"></i>
                    <span>Change Password</span>
                </a>

                <?php if ($canDeleteCurrent): ?>
                    <button type="button" class="profile-page-btn profile-page-btn-danger" id="profileDeleteAccountBtn" onclick="openDeleteAccountModal()">
                        <i class="fa-solid fa-trash"></i>
                        <span>Delete Account</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PASSWORD CONFIRMATION MODAL -->
    <div class="modal-overlay" id="usernamePasswordModal">
        <div class="modal-box">
            <h2 class="modal-title-accent">Confirm Change</h2>
            <p>Enter your password to confirm the username change to <strong id="usernamePasswordTarget">—</strong>.</p>
            <div class="profile-username-edit-msg" id="usernamePasswordMsg" style="display:none;"></div>
            <input type="password" id="usernamePasswordInput" class="profile-username-edit-input"
                   placeholder="Your password" autocomplete="current-password">
            <div class="modal-actions" style="margin-top:16px;">
                <button class="btn-cancel" onclick="closeUsernamePasswordModal()">Cancel</button>
                <button class="btn-danger-confirm" id="usernamePasswordConfirmBtn" onclick="confirmUsernamePassword()">Confirm</button>
            </div>
        </div>
    </div>

    <!-- DELETE ACCOUNT MODAL -->
    <div class="modal-overlay" id="deleteAccountModal">
        <div class="modal-box">
            <h2 class="modal-title-danger">Delete Account</h2>
            <p>Are you sure you want to permanently delete the account
                <strong id="deleteAccountName">—</strong>?
                This action cannot be undone. You will be switched to another account automatically.</p>
            <div class="profile-username-edit-msg" id="deleteAccountMsg" style="display:none;"></div>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeDeleteAccountModal()">Cancel</button>
                <button class="btn-danger-confirm" id="deleteAccountConfirmBtn" onclick="confirmDeleteAccount()">Yes, Delete</button>
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

        closeUsernameEdit();
        closeUsernamePasswordModal();
        closeAccountNameEdit();
        closeDeleteAccountModal();

        document.body.classList.remove('profile-page-open');
        try { window.parent.postMessage({ type: 'bodyClass', add: [], remove: ['profile-page-open'] }, '*'); } catch (e) {}
    }

    function syncModalOverlayState() {
        var anyOpen = !!document.querySelector('.modal-overlay.active');
        if (anyOpen) document.body.classList.add('modal-overlay-open');
        else         document.body.classList.remove('modal-overlay-open');
        try {
            window.parent.postMessage({
                type: 'bodyClass',
                add:    anyOpen ? ['modal-overlay-open'] : [],
                remove: anyOpen ? [] : ['modal-overlay-open']
            }, '*');
        } catch (e) {}
    }

    // ==================== ACCOUNT NAME EDIT FLOW ====================
    function openAccountNameEdit() {
        var editBox = document.getElementById('profileAccountNameEdit');
        var msg     = document.getElementById('profileAccountNameMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.classList.remove('error','success'); }
        if (editBox) editBox.style.display = '';
        var input = document.getElementById('profileNewAccountName');
        if (input) { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }
    }

    function closeAccountNameEdit() {
        var editBox = document.getElementById('profileAccountNameEdit');
        var msg     = document.getElementById('profileAccountNameMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.classList.remove('error','success'); }
        if (editBox) editBox.style.display = 'none';
        var input = document.getElementById('profileNewAccountName');
        if (input) input.value = '<?= htmlspecialchars($subAccountName, ENT_QUOTES) ?>';
    }

    function submitAccountNameEdit() {
        var input = document.getElementById('profileNewAccountName');
        var msg   = document.getElementById('profileAccountNameMsg');
        if (!input || !msg) return;

        var newName = (input.value || '').trim();

        newName = newName
            .replace(/[^A-Za-z0-9 ]+/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase()
            .substring(0, 20);

        msg.style.display = 'none';
        msg.textContent = '';
        msg.classList.remove('error','success');

        if (newName === '') {
            msg.textContent = 'Account name is required.';
            msg.classList.add('error');
            msg.style.display = 'block';
            return;
        }

        if (newName.toLowerCase() === '<?= strtolower(htmlspecialchars($subAccountName, ENT_QUOTES)) ?>') {
            msg.textContent = 'Account name unchanged.';
            msg.classList.add('success');
            msg.style.display = 'block';
            setTimeout(closeAccountNameEdit, 800);
            return;
        }

        var btn = document.getElementById('profileAccountNameSaveBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

        fetch('menu.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'change_account_name_ajax=1&new_account_name=' + encodeURIComponent(newName)
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }

            if (data && data.success) {
                var display = document.getElementById('profileAccountNameDisplay');
                if (display) display.textContent = data.sub_account_name || newName;

                msg.textContent = data.message || 'Account name updated.';
                msg.classList.add('success');
                msg.style.display = 'block';

                setTimeout(closeAccountNameEdit, 800);
            } else {
                msg.textContent = (data && data.message) ? data.message : 'Failed to update account name.';
                msg.classList.add('error');
                msg.style.display = 'block';
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
            msg.textContent = 'Network error. Please try again.';
            msg.classList.add('error');
            msg.style.display = 'block';
        });
    }

    // ==================== USERNAME EDIT FLOW ====================
    var __pendingUsername = '';

    function openUsernameEdit() {
        var editBox = document.getElementById('profileUsernameEdit');
        var msg     = document.getElementById('profileUsernameMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
        if (editBox) editBox.style.display = '';
        var input = document.getElementById('profileNewUsername');
        if (input) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }

    function closeUsernameEdit() {
        var editBox = document.getElementById('profileUsernameEdit');
        var msg     = document.getElementById('profileUsernameMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
        if (editBox) editBox.style.display = 'none';
        var input = document.getElementById('profileNewUsername');
        if (input) input.value = '<?= htmlspecialchars($userUsername, ENT_QUOTES) ?>';
    }

    function submitUsernameEdit() {
        var input = document.getElementById('profileNewUsername');
        var msg   = document.getElementById('profileUsernameMsg');
        if (!input || !msg) return;

        var newUsername = (input.value || '').trim();

        msg.style.display = 'none';
        msg.textContent = '';
        msg.classList.remove('error','success');

        if (newUsername === '') {
            msg.textContent = 'Username is required.';
            msg.classList.add('error'); msg.style.display = 'block'; return;
        }
        if (!/^[a-zA-Z0-9._\-]{3,80}$/.test(newUsername)) {
            msg.textContent = 'Username may only contain letters, numbers, dots, underscores, and hyphens (3–80 characters).';
            msg.classList.add('error'); msg.style.display = 'block'; return;
        }
        if (newUsername.toLowerCase() === '<?= strtolower(htmlspecialchars($userUsername, ENT_QUOTES)) ?>') {
            msg.textContent = 'Username unchanged.';
            msg.classList.add('success'); msg.style.display = 'block';
            setTimeout(closeUsernameEdit, 800); return;
        }

        __pendingUsername = newUsername;

        var target = document.getElementById('usernamePasswordTarget');
        if (target) target.textContent = newUsername;

        var pwdMsg = document.getElementById('usernamePasswordMsg');
        if (pwdMsg) { pwdMsg.style.display = 'none'; pwdMsg.textContent = ''; pwdMsg.classList.remove('error','success'); }

        var pwdInput = document.getElementById('usernamePasswordInput');
        if (pwdInput) pwdInput.value = '';

        document.getElementById('usernamePasswordModal').classList.add('active');
        syncModalOverlayState();
        if (pwdInput) setTimeout(function () { pwdInput.focus(); }, 80);
    }

    function closeUsernamePasswordModal() {
        document.getElementById('usernamePasswordModal').classList.remove('active');
        var pwdInput = document.getElementById('usernamePasswordInput');
        if (pwdInput) pwdInput.value = '';
        var pwdMsg = document.getElementById('usernamePasswordMsg');
        if (pwdMsg) { pwdMsg.style.display = 'none'; pwdMsg.textContent = ''; }
        __pendingUsername = '';
        syncModalOverlayState();
    }

    function confirmUsernamePassword() {
        var pwdInput = document.getElementById('usernamePasswordInput');
        var pwdMsg   = document.getElementById('usernamePasswordMsg');
        var btn      = document.getElementById('usernamePasswordConfirmBtn');
        if (!pwdInput || !pwdMsg) return;

        var password = pwdInput.value || '';
        pwdMsg.style.display = 'none';
        pwdMsg.textContent = '';
        pwdMsg.classList.remove('error','success');

        if (password === '') {
            pwdMsg.textContent = 'Password is required.';
            pwdMsg.classList.add('error'); pwdMsg.style.display = 'block'; return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Verifying...'; }

        fetch('menu.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'change_username_ajax=1'
                + '&new_username=' + encodeURIComponent(__pendingUsername)
                + '&password='     + encodeURIComponent(password)
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }

            if (data && data.success) {
                var display = document.getElementById('profileUsernameDisplay');
                if (display) display.textContent = data.username || __pendingUsername;
                pwdMsg.textContent = data.message || 'Username updated.';
                pwdMsg.classList.add('success'); pwdMsg.style.display = 'block';

                var input = document.getElementById('profileNewUsername');
                if (input && data.username) input.value = data.username;

                setTimeout(function () {
                    closeUsernamePasswordModal();
                    closeUsernameEdit();
                }, 700);
            } else {
                pwdMsg.textContent = (data && data.message) ? data.message : 'Incorrect password. Please try again.';
                pwdMsg.classList.add('error'); pwdMsg.style.display = 'block';
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }
            pwdMsg.textContent = 'Network error. Please try again.';
            pwdMsg.classList.add('error'); pwdMsg.style.display = 'block';
        });
    }

    // ==================== DELETE ACCOUNT FLOW ====================
    function openDeleteAccountModal() {
        var nameEl = document.getElementById('deleteAccountName');
        if (nameEl) nameEl.textContent = '<?= htmlspecialchars($subAccountName !== '' ? $subAccountName : 'this account', ENT_QUOTES) ?>';

        var msg = document.getElementById('deleteAccountMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.classList.remove('error','success'); }

        var modal = document.getElementById('deleteAccountModal');
        if (modal) { modal.classList.add('active'); syncModalOverlayState(); }
    }

    function closeDeleteAccountModal() {
        var modal = document.getElementById('deleteAccountModal');
        if (modal) { modal.classList.remove('active'); syncModalOverlayState(); }
    }

    function confirmDeleteAccount() {
        var btn = document.getElementById('deleteAccountConfirmBtn');
        var msg = document.getElementById('deleteAccountMsg');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.classList.remove('error','success'); }

        if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }

        fetch('menu.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'delete_sub_account_ajax=1'
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (btn) { btn.disabled = false; btn.textContent = 'Yes, Delete'; }

            if (data && data.success) {
                if (msg) {
                    msg.textContent = data.message || 'Account deleted.';
                    msg.classList.add('success'); msg.style.display = 'block';
                }
                setTimeout(function () {
                    try {
                        if (window.top && window.top !== window) {
                            window.top.location.href = 'investorapp.php?tab=mydashboard&account_deleted=1';
                        } else {
                            window.location.href = 'investorapp.php?tab=mydashboard&account_deleted=1';
                        }
                    } catch (e) {
                        window.location.reload();
                    }
                }, 600);
            } else {
                if (msg) {
                    msg.textContent = (data && data.message) ? data.message : 'Unable to delete this account.';
                    msg.classList.add('error'); msg.style.display = 'block';
                }
            }
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.textContent = 'Yes, Delete'; }
            if (msg) {
                msg.textContent = 'Network error. Please try again.';
                msg.classList.add('error'); msg.style.display = 'block';
            }
        });
    }

    // ==================== LOGOUT ====================
    function openLogoutModal() {
        var modal = document.getElementById('logoutModal');
        if (modal) { modal.classList.add('active'); syncModalOverlayState(); }
    }
    function closeLogoutModal() {
        var modal = document.getElementById('logoutModal');
        if (modal) { modal.classList.remove('active'); syncModalOverlayState(); }
    }

    function confirmLogout() {
        var insideShell = false;
        try { insideShell = !!(window.parent && window.parent !== window); } catch (e) {}

        if (insideShell) {
            try { window.parent.postMessage({ type: 'saveLastApp' }, '*'); } catch (e) {}
            try { window.parent.postMessage({ type: 'logout' }, '*'); } catch (e) {}
            return;
        }

        try {
            fetch('menu.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: 'save_last_app=1&app=investorapp'
            }).catch(function () {});
        } catch (e) {}

        setTimeout(function () {
            window.location.href = 'index.php?logout=1';
        }, 250);
    }

    document.addEventListener('click', function(event) {
        if (event.target.id === 'logoutModal') closeLogoutModal();
        if (event.target.id === 'usernamePasswordModal') closeUsernamePasswordModal();
        if (event.target.id === 'deleteAccountModal') closeDeleteAccountModal();
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            var lm = document.getElementById('logoutModal');
            if (lm && lm.classList.contains('active')) { closeLogoutModal(); return; }

            var um = document.getElementById('usernamePasswordModal');
            if (um && um.classList.contains('active')) { closeUsernamePasswordModal(); return; }

            var dm = document.getElementById('deleteAccountModal');
            if (dm && dm.classList.contains('active')) { closeDeleteAccountModal(); return; }

            var pp = document.getElementById('profilePage');
            if (pp && pp.classList.contains('active')) hideProfilePage();
        }
    });
    document.addEventListener('DOMContentLoaded', function() {
        var profileCard = document.querySelector('.profile-clickable');
        if (profileCard) {
            profileCard.addEventListener('click', function(e) {
                e.preventDefault();
                showProfilePage();
            });
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

    window.openUsernameEdit = openUsernameEdit;
    window.closeUsernameEdit = closeUsernameEdit;
    window.submitUsernameEdit = submitUsernameEdit;
    window.closeUsernamePasswordModal = closeUsernamePasswordModal;
    window.confirmUsernamePassword = confirmUsernamePassword;

    window.openAccountNameEdit = openAccountNameEdit;
    window.closeAccountNameEdit = closeAccountNameEdit;
    window.submitAccountNameEdit = submitAccountNameEdit;

    window.openDeleteAccountModal = openDeleteAccountModal;
    window.closeDeleteAccountModal = closeDeleteAccountModal;
    window.confirmDeleteAccount = confirmDeleteAccount;

    // =====================================================================
    // TAB NAVIGATION
    // =====================================================================
    (function () {
        var IN_SHELL = false;
        try { IN_SHELL = (window.parent && window.parent !== window); } catch (e) {}

        document.addEventListener('click', function (ev) {
            var fullLink = ev.target.closest ? ev.target.closest('[data-fullnav]') : null;
            if (fullLink) {
                ev.preventDefault();
                ev.stopPropagation();
                var target = fullLink.getAttribute('data-fullnav');
                if (!target) return;
                try {
                    if (window.top && window.top !== window) {
                        window.top.location.href = target;
                    } else {
                        window.location.href = target;
                    }
                } catch (e) {
                    window.location.href = target;
                }
                return;
            }

            var link = ev.target.closest ? ev.target.closest('[data-tab]') : null;
            if (!link) return;

            var tab = link.getAttribute('data-tab');
            if (!tab) return;

            ev.preventDefault();
            ev.stopPropagation();

            if (IN_SHELL) {
                try {
                    window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                    return;
                } catch (e) {}
            }
            window.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);
        }, true);
    })();

    // =====================================================================
    // DARK MODE TOGGLE
    // =====================================================================
    (function () {
        var toggle = document.getElementById('darkModeToggle');
        if (!toggle) return;

        function persistDarkMode(dark) {
            var body = 'toggle_dark_mode_ajax=1&dark_mode_checkbox=' + dark;
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'themeRequest', dark: !!dark }, '*');
                }
            } catch (e) {}

            fetch('investorapp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body,
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .catch(function () {
                fetch('menu.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body,
                    credentials: 'same-origin'
                }).catch(function () {});
            });
        }

        toggle.addEventListener('change', function () {
            var isChecked = this.checked ? 1 : 0;

            if (isChecked) document.body.classList.add('dark-mode');
            else document.body.classList.remove('dark-mode');

            var iconSpan = document.getElementById('darkModeIcon');
            var labelSpan = document.getElementById('darkModeLabel');
            if (iconSpan) iconSpan.textContent = isChecked ? '🌙' : '☀️';
            if (labelSpan) labelSpan.textContent = isChecked ? 'On' : 'Off';

            persistDarkMode(isChecked);
        });
    })();

    // =====================================================================
    // THEME RECEIVER
    // =====================================================================
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') {
            var isDark = !!e.data.dark;
            document.body.classList.toggle('dark-mode', isDark);

            var toggle = document.getElementById('darkModeToggle');
            var iconSpan = document.getElementById('darkModeIcon');
            var labelSpan = document.getElementById('darkModeLabel');
            if (toggle) toggle.checked = isDark;
            if (iconSpan) iconSpan.textContent = isDark ? '🌙' : '☀️';
            if (labelSpan) labelSpan.textContent = isDark ? 'On' : 'Off';
        }
    });

    // =====================================================================
    // BODY-CLASS BRIDGE
    // =====================================================================
    (function () {
        var WATCHED = ['page-connect_investor_broker', 'profile-page-open', 'page-revenue_history', 'page-profit_split', 'modal-overlay-open'];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();

    // =====================================================================
    // LIVE MENU UPDATER (scoped to active sub-account)
    // =====================================================================
    (function () {
        var MENU_POLL_URL = (function () {
            try {
                var base = document.baseURI || window.location.href;
                return new URL('menu.php', base).toString();
            } catch (e) {
                return 'menu.php';
            }
        })();

        var isPolling = false;
        var pollTimer = null;

        function setText(id, value) {
            var el = document.getElementById(id);
            if (el && value !== undefined && value !== null) el.textContent = value;
        }

        function applyLiveState(data) {
            if (!data || !data.success) return;

            setText('menuUserAvatar', data.avatar_initial || '');
            setText('menuUserName',   data.fullname || '');
            setText('menuUserEmail',  data.email || '');

            setText('profilePageAvatar', data.avatar_initial || '');
            setText('profileFirstNameValue', data.first_name !== '' ? data.first_name : '—');
            setText('profileLastNameValue',  data.last_name  !== '' ? data.last_name  : '—');

            var editBox = document.getElementById('profileUsernameEdit');
            var editOpen = editBox && editBox.style.display !== 'none';
            if (!editOpen) {
                setText('profileUsernameDisplay', data.username !== '' ? data.username : '—');
                var input = document.getElementById('profileNewUsername');
                if (input) input.value = data.username || '';
            }

            var acctEdit = document.getElementById('profileAccountNameEdit');
            var acctEditOpen = acctEdit && acctEdit.style.display !== 'none';
            if (!acctEditOpen) {
                setText('profileAccountNameDisplay', (data.sub_account_name && data.sub_account_name !== '') ? data.sub_account_name : '—');
                var acctInput = document.getElementById('profileNewAccountName');
                if (acctInput) acctInput.value = data.sub_account_name || '';
            }

            var brokerTitle = document.getElementById('menuBrokerItemTitle');
            var brokerDesc  = document.getElementById('menuBrokerItemDesc');
            if (brokerTitle && brokerDesc) {
                if (data.broker_connected) {
                    brokerTitle.textContent = 'Update Broker';
                    brokerDesc.textContent  = 'Update your broker connection';
                } else {
                    brokerTitle.textContent = 'Connect Broker';
                    brokerDesc.textContent  = 'Connect your MT5 account';
                }
            }

            var disconnectItem = document.getElementById('menuDisconnectBrokerItem');
            if (disconnectItem) {
                disconnectItem.style.display = data.broker_connected ? '' : 'none';
            }

            var progItem  = document.getElementById('menuProgrammeItem');
            var progTitle = document.getElementById('menuProgrammeTitle');
            if (progItem && progTitle) {
                if (data.has_programme) {
                    progItem.style.display = '';
                    var devName = data.developer_name && data.developer_name !== 'N/A' ? data.developer_name : '';
                    var progName = data.programme_name || 'Your account trades Provider';
                    if (devName) {
                        progTitle.textContent = "Invested in " + devName + "'s " + progName + " Programme";
                    } else {
                        progTitle.textContent = "Invested in a Programme";
                    }
                } else {
                    progItem.style.display = 'none';
                }
            }

            var toggle = document.getElementById('darkModeToggle');
            var iconSpan = document.getElementById('darkModeIcon');
            var labelSpan = document.getElementById('darkModeLabel');
            var isDark = !!data.dark_mode;
            if (toggle)   toggle.checked = isDark;
            if (iconSpan) iconSpan.textContent = isDark ? '🌙' : '☀️';
            if (labelSpan) labelSpan.textContent = isDark ? 'On' : 'Off';
            document.body.classList.toggle('dark-mode', isDark);
        }

        function fetchLiveState() {
            if (isPolling) return;
            isPolling = true;

            fetch(MENU_POLL_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                credentials: 'same-origin',
                cache: 'no-store',
                body: 'menu_live_state=1'
            })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (data) { applyLiveState(data); })
            .catch(function () {})
            .finally(function () {
                isPolling = false;
                scheduleNext();
            });
        }

        function scheduleNext() {
            if (pollTimer) clearTimeout(pollTimer);
            pollTimer = setTimeout(fetchLiveState, 1500);
        }

        function start() { fetchLiveState(); }
        function stop() { if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; } }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else start();
        });
        window.addEventListener('focus', function () { fetchLiveState(); });
        window.addEventListener('beforeunload', function () { stop(); });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);
        } else {
            start();
        }

        window.menuLiveRefresh = fetchLiveState;
    })();
</script>
</body>
</html>