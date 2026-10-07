<?php
// vps.php — strictly sub-account scoped (INVESTOR SIDE ONLY)
// Fully decoupled from programme_vps. Talks only to:
//   vps, vps_hosts_requestors, vps_hosts_followers, harvhub
session_start();

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

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== RESOLVE ACTIVE SUB ACCOUNT ====================
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) { header("Location: index.php"); exit; }

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$fullName = $user['fullname'] ?? 'User';
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ==================== HELPERS ====================
if (!function_exists('resolveHostDisplayName')) {
    function resolveHostDisplayName(array $hostRow) {
        $subName = trim((string)($hostRow['sub_account_name'] ?? ''));
        if ($subName !== '') return $subName;
        $username = trim((string)($hostRow['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($hostRow['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($hostRow['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($hostRow['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('resolveSubAccountName')) {
    function resolveSubAccountName(array $row): string {
        $subName = trim((string)($row['sub_account_name'] ?? ''));
        if ($subName !== '') return $subName;
        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') return $username;
        $fullName = trim((string)($row['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        $email = trim((string)($row['email'] ?? ''));
        if ($email !== '' && strpos($email, '@') !== false) {
            return explode('@', $email)[0];
        }
        return 'Account';
    }
}

if (!function_exists('countActiveFollowers')) {
    function countActiveFollowers($pdo, $ownerId, $ownerSubId) {
        try {
            $q = $pdo->prepare("
                SELECT COUNT(*) AS cnt
                FROM vps_hosts_followers
                WHERE owner_id = ?
                  AND sub_account_id = ?
                  AND host_status = 'active'
            ");
            $q->execute([$ownerId, $ownerSubId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['cnt'] ?? 0);
        } catch (PDOException $e) {
            return 0;
        }
    }
}

if (!function_exists('findOtherFollowerLink')) {
    function findOtherFollowerLink($pdo, $requestorId, $requestorSubId, $excludeOwnerId = 0, $excludeOwnerSub = 0) {
        try {
            $sql = "
                SELECT f.id, f.owner_id, f.sub_account_id AS owner_sub_id,
                       h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name
                FROM vps_hosts_followers f
                LEFT JOIN harvhub h ON h.id = f.owner_id
                WHERE f.follower_id = ?
                  AND f.follower_sub_account_id = ?
                  AND f.host_status = 'active'
            ";
            $params = [$requestorId, $requestorSubId];

            if ($excludeOwnerId > 0) {
                $sql .= " AND f.owner_id <> ?";
                $params[] = $excludeOwnerId;
            }
            if ($excludeOwnerId > 0 && $excludeOwnerSub > 0) {
                $sql .= " AND f.sub_account_id <> ?";
                $params[] = $excludeOwnerSub;
            }

            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('resolveOwnerMainAccountId')) {
    function resolveOwnerMainAccountId($pdo, $ownerId) {
        try {
            $q = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$ownerId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['main_account_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('resolveUserEmailById')) {
    function resolveUserEmailById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT email FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return strtolower(trim((string)($row['email'] ?? '')));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('resolveHarvhubRowById')) {
    function resolveHarvhubRowById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT * FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            return $q->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('subAccountOwnsAnyVps')) {
    function subAccountOwnsAnyVps($pdo, $userId) {
        try {
            $q = $pdo->prepare("
                SELECT v.id
                FROM vps v
                INNER JOIN harvhub h ON h.id = v.user_id AND h.sub_account_id = v.sub_account_id
                WHERE v.user_id = ?
                LIMIT 1
            ");
            $q->execute([$userId]);
            return (bool)$q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return false; }
    }
}

if (!function_exists('findUserProgrammeVps')) {
    function findUserProgrammeVps($pdo, $userId) {
        try {
            $q = $pdo->prepare("
                SELECT pv.*, p.program_name, p.id AS prog_id
                FROM programme_vps pv
                INNER JOIN programme p ON p.id = pv.programme_id
                WHERE p.userid = ?
                LIMIT 1
            ");
            $q->execute([$userId]);
            return $q->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }
}

if (!function_exists('linkProgrammeVpsToSubAccount')) {
    function linkProgrammeVpsToSubAccount($pdo, $progVps, $userId, $subAccountId) {
        try {
            $chk = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? AND sub_account_id = ? LIMIT 1");
            $chk->execute([$userId, $subAccountId]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) return true;

            $ins = $pdo->prepare("
                INSERT INTO vps
                  (user_id, sub_account_id, server_location, subscription_duration, visibility)
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([
                (int)$userId,
                (int)$subAccountId,
                (string)($progVps['server_location'] ?? ''),
                (int)($progVps['subscription_duration'] ?? 0),
                (string)($progVps['visibility'] ?? 'public'),
            ]);
            return true;
        } catch (Throwable $e) { return false; }
    }
}

// ==================== FETCH VPS HOSTS (PUBLIC ONLY) ====================
$hosts = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.user_id, v.sub_account_id AS host_sub_account_id,
               v.server_location, v.subscription_duration, v.visibility,
               h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name,
               h.email AS host_email
        FROM vps v
        INNER JOIN harvhub h
            ON h.id = v.user_id
           AND h.sub_account_id = v.sub_account_id
        WHERE v.visibility = 'public'
        ORDER BY h.username ASC
    ");
    $stmt->execute();
    $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hosts = [];
}

// ==================== FETCH HOST FOLLOWER COUNTS ====================
$hostFollowerCounts = [];
if (!empty($hosts)) {
    foreach ($hosts as $h) {
        $ownerId    = (int)$h['user_id'];
        $ownerSubId = (int)$h['host_sub_account_id'];
        $key        = $ownerId . ':' . $ownerSubId;
        $hostFollowerCounts[$key] = countActiveFollowers($pdo, $ownerId, $ownerSubId);
    }
}

// ==================== VPS FLAGS FOR THE ACTIVE SUB ACCOUNT ====================
$userOwnsVps       = false;
$isAlreadyFollower = false;
$userHasVps        = false;

try {
    $stmt = $pdo->prepare("
        SELECT id FROM vps
        WHERE user_id = ?
          AND sub_account_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $userOwnsVps = true;
    }
} catch (PDOException $e) {}

try {
    $stmt = $pdo->prepare("
        SELECT id FROM vps_hosts_followers
        WHERE follower_id = ?
          AND follower_sub_account_id = ?
          AND host_status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $isAlreadyFollower = true;
    }
} catch (PDOException $e) {}

$userHasVps = ($userOwnsVps || $isAlreadyFollower);

// ==================== LINKABLE PROGRAMME VPS DETECTION ====================
$linkableProgVps = null;
$anySubAccountOwnsVps = false;

if (!$userOwnsVps) {
    $anySubAccountOwnsVps = subAccountOwnsAnyVps($pdo, $userId);

    if (!$anySubAccountOwnsVps) {
        $linkableProgVps = findUserProgrammeVps($pdo, $userId);
    }
}

// ==================== GET SUB ACCOUNT NAME FOR DISPLAY ====================
$activeSubAccountName = resolveSubAccountName($user);

// ==================== LINK PROGRAMME VPS TO SUB-ACCOUNT (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_programme_vps'])) {
    header('Content-Type: application/json');

    if ($userOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'This account already owns a VPS.']);
        exit;
    }

    $anySubAccountOwnsVps = subAccountOwnsAnyVps($pdo, $userId);
    if ($anySubAccountOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'Another sub-account already owns a VPS.']);
        exit;
    }

    $progVps = findUserProgrammeVps($pdo, $userId);
    if (!$progVps) {
        echo json_encode(['success' => false, 'message' => 'No programme VPS found to link.']);
        exit;
    }

    $ok = linkProgrammeVpsToSubAccount($pdo, $progVps, $userId, $activeSubAccountId);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'VPS linked to this sub-account successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to link VPS.']);
    }
    exit;
}

// ==================== HANDLE REQUEST SPACE (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_vps_space'])) {
    header('Content-Type: application/json');

    $ownerId      = (int)($_POST['owner_id'] ?? 0);
    $ownerSubId   = (int)($_POST['owner_sub_account_id'] ?? 0);

    if ($ownerId <= 0 || $ownerSubId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid host.']);
        exit;
    }

    if ($ownerId === $userId && $ownerSubId === $activeSubAccountId) {
        echo json_encode(['success' => false, 'message' => 'You cannot request your own VPS.']);
        exit;
    }

    if ($isAlreadyFollower) {
        echo json_encode([
            'success' => false,
            'message' => 'This account is already a follower of another VPS. You cannot request space from other hosts.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? AND sub_account_id = ? AND visibility = 'public' LIMIT 1");
    $stmt->execute([$ownerId, $ownerSubId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'This host is not available.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id FROM vps_hosts_requestors
        WHERE owner_id = ?
          AND sub_account_id = ?
          AND requestor_id = ?
          AND requestor_sub_account_id = ?
          AND request_status IN ('pending','accept')
        LIMIT 1
    ");
    $stmt->execute([$ownerId, $ownerSubId, $userId, $activeSubAccountId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'This account already has an active request with this host.']);
        exit;
    }

    $requestorRow        = resolveHarvhubRowById($pdo, $userId);
    $requestorSubName    = $requestorRow ? resolveSubAccountName($requestorRow) : 'An investor';
    $requestorUsername   = trim((string)($user['username'] ?? ''));
    if ($requestorUsername === '') {
        $requestorUsername = trim((string)($user['fullname'] ?? 'An investor'));
    }

    $isSelfReferral = ((int)$ownerId === $userId);

    if ($isSelfReferral) {
        $notificationMessage = 'Your sub account ' . $requestorSubName . ' requested space on your VPS.';
    } else {
        $notificationMessage = $requestorUsername . ' requested space on your VPS.';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO vps_hosts_requestors
              (owner_id, sub_account_id, requestor_id, requestor_sub_account_id, request_status)
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$ownerId, $ownerSubId, $userId, $activeSubAccountId]);

        $ownerEmail      = resolveUserEmailById($pdo, $ownerId);
        $ownerMainAccId  = resolveOwnerMainAccountId($pdo, $ownerId);

        if ($ownerEmail !== '') {
            recordContractNotification($pdo, [
                'user_email'       => $ownerEmail,
                'sub_account_id'   => $ownerSubId,
                'main_account_id'  => $ownerMainAccId,
                'notification_key' => 'vps-request-received-' . $activeSubAccountId . '-' . date('YmdHis'),
                'title'            => 'New VPS Space Request',
                'message'          => $notificationMessage,
                'type'             => 'info',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
                'force'            => true
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Request submitted successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
    }
    exit;
}

// ==================== HANDLE GET HOST DETAILS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_host_details'])) {
    header('Content-Type: application/json');

    $ownerId    = (int)($_POST['owner_id'] ?? 0);
    $ownerSubId = (int)($_POST['owner_sub_account_id'] ?? 0);

    if ($ownerId <= 0 || $ownerSubId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid host.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT v.user_id, v.sub_account_id AS host_sub_account_id,
                   v.server_location, v.subscription_duration,
                   h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name,
                   h.email AS host_email
            FROM vps v
            INNER JOIN harvhub h
                ON h.id = v.user_id
               AND h.sub_account_id = v.sub_account_id
            WHERE v.user_id = ?
              AND v.sub_account_id = ?
              AND v.visibility = 'public'
            LIMIT 1
        ");
        $stmt->execute([$ownerId, $ownerSubId]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Host not found.']);
        exit;
    }

    $hostData = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hostData) {
        echo json_encode(['success' => false, 'message' => 'Host not found.']);
        exit;
    }

    $hostName = resolveHostDisplayName($hostData);
    $resolvedOwnerSubId = (int)($hostData['host_sub_account_id'] ?? $ownerSubId);
    $followerCount = countActiveFollowers($pdo, $ownerId, $resolvedOwnerSubId);

    $alreadyRequested = false;
    try {
        $stmt = $pdo->prepare("
            SELECT id FROM vps_hosts_requestors
            WHERE owner_id = ?
              AND sub_account_id = ?
              AND requestor_id = ?
              AND requestor_sub_account_id = ?
              AND request_status IN ('pending','accept')
            LIMIT 1
        ");
        $stmt->execute([$ownerId, $ownerSubId, $userId, $activeSubAccountId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) $alreadyRequested = true;
    } catch (PDOException $e) {}

    $maxHosts = 5;
    $isSelf = ($ownerId === $userId && $ownerSubId === $activeSubAccountId);

    $requestLocked = $isSelf || $isAlreadyFollower || ($followerCount >= $maxHosts) || $alreadyRequested;

    echo json_encode([
        'success' => true,
        'host' => [
            'owner_id' => (int)$hostData['user_id'],
            'owner_sub_account_id' => (int)$hostData['host_sub_account_id'],
            'fullname' => $hostName,
            'email' => $hostData['host_email'],
            'server_location' => $hostData['server_location'],
            'subscription_duration' => $hostData['subscription_duration'],
            'current_hosts' => $followerCount,
            'max_hosts' => $maxHosts,
            'is_self' => $isSelf,
            'is_full' => ($followerCount >= $maxHosts),
            'already_requested' => $alreadyRequested,
            'is_follower_lock' => $isAlreadyFollower,
            'request_locked' => $requestLocked
        ]
    ]);
    exit;
}

// ==================== GET MY FOLLOWER OWNER (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_follower_owner'])) {
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT f.id AS follower_row_id, f.follower_id, f.host_status, f.created_at,
                   f.follower_sub_account_id,
                   f.owner_id AS owner_id_resolved,
                   h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name,
                   v.server_location, v.subscription_duration
            FROM vps_hosts_followers f
            LEFT JOIN harvhub h
                ON h.id = f.owner_id
               AND h.sub_account_id = f.sub_account_id
            LEFT JOIN vps v
                ON v.user_id = f.owner_id
               AND v.sub_account_id = f.sub_account_id
            WHERE f.follower_id = ?
              AND f.follower_sub_account_id = ?
              AND f.host_status = 'active'
            ORDER BY f.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $row = null;
    }

    if (!$row) {
        echo json_encode(['success' => true, 'is_follower' => false]);
        exit;
    }

    $ownerId   = (int)$row['owner_id_resolved'];
    $ownerName = resolveHostDisplayName($row);

    $ownerSubId = 0;
    try {
        $sq = $pdo->prepare("SELECT sub_account_id FROM vps_hosts_followers WHERE id = ? LIMIT 1");
        $sq->execute([$row['follower_row_id']]);
        $ownerSubId = (int)($sq->fetchColumn() ?: 0);
    } catch (Throwable $e) {}

    $totalFollowers = countActiveFollowers($pdo, $ownerId, $ownerSubId);

    echo json_encode([
        'success' => true,
        'is_follower' => true,
        'owner' => [
            'owner_id' => $ownerId,
            'fullname' => $ownerName,
            'server_location' => $row['server_location'] ?? '',
            'subscription_duration' => $row['subscription_duration'] ?? 0,
            'total_followers' => $totalFollowers
        ],
        'self' => [
            'follower_row_id' => $row['follower_row_id'],
            'follower_id' => $row['follower_id'],
            'host_status' => $row['host_status'],
            'sub_account_id' => $row['follower_sub_account_id'] ?? null,
            'display_name' => $activeSubAccountName
        ]
    ]);
    exit;
}

// ==================== SENT REQUESTS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_sent_requests'])) {
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.owner_id, r.sub_account_id AS owner_sub_account_id,
                   r.request_status, r.created_at,
                   h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name,
                   v.server_location AS owner_location
            FROM vps_hosts_requestors r
            LEFT JOIN harvhub h
                ON h.id = r.owner_id
               AND h.sub_account_id = r.sub_account_id
            LEFT JOIN vps v
                ON v.user_id = r.owner_id
               AND v.sub_account_id = r.sub_account_id
            WHERE r.requestor_id = ?
              AND r.requestor_sub_account_id = ?
              AND r.request_status IN ('pending', 'reject')
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    $seen = [];
    $unique = [];
    foreach ($rows as $r) {
        $oid = (int)$r['owner_id'];
        $osub = (int)($r['owner_sub_account_id'] ?? 0);
        $key = $oid . ':' . $osub;
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $r['owner_display_name'] = resolveHostDisplayName($r);
            $unique[] = $r;
        }
    }

    echo json_encode(['success' => true, 'requests' => $unique]);
    exit;
}

// ==================== INCOMING REQUESTS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_incoming_requests'])) {
    header('Content-Type: application/json');

    if (!$userOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'This account does not own a VPS.', 'count' => 0]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.requestor_id, r.requestor_sub_account_id,
                   r.request_status, r.created_at,
                   h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name
            FROM vps_hosts_requestors r
            LEFT JOIN harvhub h
                ON h.id = r.requestor_id
               AND h.sub_account_id = r.requestor_sub_account_id
            WHERE r.owner_id = ?
              AND r.sub_account_id = ?
              AND r.request_status = 'pending'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    foreach ($rows as &$r) {
        if ((int)$r['requestor_id'] === $userId) {
            $r['requestor_display_name'] = 'Your sub account ' . resolveSubAccountName($r);
        } else {
            $r['requestor_display_name'] = resolveHostDisplayName($r);
        }
    }
    unset($r);

    echo json_encode([
        'success' => true,
        'requests' => $rows,
        'count' => count($rows)
    ]);
    exit;
}

// ==================== UPDATE REQUEST STATUS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    header('Content-Type: application/json');

    $requestId = (int)($_POST['request_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowed = ['accept', 'reject'];
    if ($requestId <= 0 || !in_array($newStatus, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit;
        }

        $rowOwnerSub = (int)$row['sub_account_id'];
        $rowReqSub   = (int)$row['requestor_sub_account_id'];

        $isOwner = ((int)$row['owner_id'] === $userId && $rowOwnerSub === $activeSubAccountId);

        if (!$isOwner) {
            echo json_encode(['success' => false, 'message' => 'Not authorized.']);
            exit;
        }

        if ($row['request_status'] !== 'pending') {
            echo json_encode([
                'success' => false,
                'message' => 'This request is already ' . $row['request_status'] . '.',
                'current_status' => $row['request_status']
            ]);
            exit;
        }

        $requestorId  = (int)$row['requestor_id'];
        $ownerId      = (int)$row['owner_id'];
        $ownerSubId   = $rowOwnerSub;

        if ($newStatus === 'accept') {
            $otherRow = findOtherFollowerLink($pdo, $requestorId, $rowReqSub, $ownerId, $ownerSubId);

            if ($otherRow) {
                $otherOwnerName = resolveHostDisplayName($otherRow);
                $del = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
                $del->execute([$requestId]);

                echo json_encode([
                    'success' => false,
                    'message' => 'This user is already with another VPS owner (' . ($otherOwnerName ?: 'another host') . '). Their pending request has been cleared.',
                    'cleared' => true,
                    'needs_reload' => true
                ]);
                exit;
            }
        }

        $upd = $pdo->prepare("
            UPDATE vps_hosts_requestors
            SET request_status = ?
            WHERE id = ? AND request_status = 'pending'
        ");
        $upd->execute([$newStatus, $requestId]);

        if ($upd->rowCount() === 0) {
            $chk = $pdo->prepare("SELECT request_status FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
            $chk->execute([$requestId]);
            $cur = $chk->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => false,
                'message' => 'Request is already ' . ($cur['request_status'] ?? 'processed') . '.',
                'current_status' => $cur['request_status'] ?? null
            ]);
            exit;
        }

        $ownerName = 'the host';
        try {
            $q = $pdo->prepare("SELECT fullname, first_name, last_name, username, sub_account_name FROM harvhub WHERE id = ? AND sub_account_id = ? LIMIT 1");
            $q->execute([$ownerId, $ownerSubId]);
            $hostRow = $q->fetch(PDO::FETCH_ASSOC);
            if ($hostRow) $ownerName = resolveHostDisplayName($hostRow);
        } catch (Throwable $e) {}

        if ($newStatus === 'accept') {
            $check = $pdo->prepare("
                SELECT id FROM vps_hosts_followers
                WHERE owner_id = ?
                  AND sub_account_id = ?
                  AND follower_id = ?
                  AND follower_sub_account_id = ?
                LIMIT 1
            ");
            $check->execute([$ownerId, $ownerSubId, $requestorId, $rowReqSub]);
            $exists = $check->fetch(PDO::FETCH_ASSOC);

            if (!$exists) {
                $ins = $pdo->prepare("
                    INSERT INTO vps_hosts_followers
                      (owner_id, sub_account_id, follower_id, follower_sub_account_id, host_status)
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $ins->execute([$ownerId, $ownerSubId, $requestorId, $rowReqSub]);
            }

            $delOthers = $pdo->prepare("
                DELETE FROM vps_hosts_requestors
                WHERE requestor_id = ?
                  AND requestor_sub_account_id = ?
                  AND id <> ?
                  AND NOT (owner_id = ? AND sub_account_id = ?)
            ");
            $delOthers->execute([$requestorId, $rowReqSub, $requestId, $ownerId, $ownerSubId]);

            $delRow = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
            $delRow->execute([$requestId]);
        }

        $requestorEmail     = resolveUserEmailById($pdo, $requestorId);
        $requestorMainAccId = resolveOwnerMainAccountId($pdo, $requestorId);

        if ($requestorEmail !== '') {
            if ($newStatus === 'accept') {
                recordContractNotification($pdo, [
                    'user_email'       => $requestorEmail,
                    'sub_account_id'   => $rowReqSub,
                    'main_account_id'  => $requestorMainAccId,
                    'notification_key' => 'vps-request-accepted-' . $activeSubAccountId . '-' . date('YmdHis'),
                    'title'            => 'VPS Request Accepted',
                    'message'          => $ownerName . ' has accepted your request. You are now a follower on their VPS.',
                    'type'             => 'success',
                    'section'          => 'VPS',
                    'action_tab'       => 'vps',
                    'force'            => true
                ]);
            } else {
                recordContractNotification($pdo, [
                    'user_email'       => $requestorEmail,
                    'sub_account_id'   => $rowReqSub,
                    'main_account_id'  => $requestorMainAccId,
                    'notification_key' => 'vps-request-rejected-' . $activeSubAccountId . '-' . date('YmdHis'),
                    'title'            => 'VPS Request Declined',
                    'message'          => $ownerName . ' has declined your VPS space request.',
                    'type'             => 'warning',
                    'section'          => 'VPS',
                    'action_tab'       => 'vps',
                    'force'            => true
                ]);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Request ' . $newStatus . 'ed successfully.',
            'new_status' => $newStatus
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
    }
    exit;
}

// ==================== DELETE REQUEST (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    header('Content-Type: application/json');

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit;
        }

        if ((int)$row['requestor_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own sent requests.']);
            exit;
        }

        if ((int)$row['requestor_sub_account_id'] !== $activeSubAccountId) {
            echo json_encode(['success' => false, 'message' => 'This request does not belong to the current account.']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
        $del->execute([$requestId]);

        echo json_encode(['success' => true, 'message' => 'Request deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete request.']);
    }
    exit;
}

// ==================== GET MY FOLLOWERS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_followers'])) {
    header('Content-Type: application/json');

    if (!$userOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'This account does not own a VPS.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT f.id, f.follower_id, f.follower_sub_account_id,
                   f.host_status, f.created_at,
                   h.fullname, h.first_name, h.last_name, h.username, h.sub_account_name
            FROM vps_hosts_followers f
            LEFT JOIN harvhub h
                ON h.id = f.follower_id
               AND h.sub_account_id = f.follower_sub_account_id
            WHERE f.owner_id = ?
              AND f.sub_account_id = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId, $activeSubAccountId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $rows = [];
    }

    foreach ($rows as &$r) {
        if ((int)$r['follower_id'] === $userId) {
            $subName = resolveSubAccountName($r);
            $r['follower_display_name'] = 'Your account (' . $subName . ')';
        } else {
            $r['follower_display_name'] = resolveSubAccountName($r);
        }
    }
    unset($r);

    echo json_encode(['success' => true, 'followers' => $rows]);
    exit;
}

// ==================== REMOVE FOLLOWER (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_follower'])) {
    header('Content-Type: application/json');

    $followerRowId = (int)($_POST['follower_row_id'] ?? 0);

    if ($followerRowId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid follower.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_followers WHERE id = ? LIMIT 1");
        $stmt->execute([$followerRowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Follower not found.']);
            exit;
        }

        $isMine = ((int)$row['owner_id'] === $userId && (int)$row['sub_account_id'] === $activeSubAccountId);

        if (!$isMine) {
            echo json_encode(['success' => false, 'message' => 'You can only remove followers from your own VPS.']);
            exit;
        }

        $removedFollowerId  = (int)$row['follower_id'];
        $removedFollowerSub = (int)$row['follower_sub_account_id'];

        $del = $pdo->prepare("DELETE FROM vps_hosts_followers WHERE id = ?");
        $del->execute([$followerRowId]);

        $ownerName = 'The host';
        try {
            $q = $pdo->prepare("SELECT fullname, first_name, last_name, username, sub_account_name FROM harvhub WHERE id = ? AND sub_account_id = ? LIMIT 1");
            $q->execute([$userId, $activeSubAccountId]);
            $ownerRow = $q->fetch(PDO::FETCH_ASSOC);
            if ($ownerRow) $ownerName = resolveHostDisplayName($ownerRow);
        } catch (Throwable $e) {}

        $followerEmail     = resolveUserEmailById($pdo, $removedFollowerId);
        $followerMainAccId = resolveOwnerMainAccountId($pdo, $removedFollowerId);

        if ($followerEmail !== '' && $removedFollowerSub > 0) {
            recordContractNotification($pdo, [
                'user_email'       => $followerEmail,
                'sub_account_id'   => $removedFollowerSub,
                'main_account_id'  => $followerMainAccId,
                'notification_key' => 'vps-follower-removed-' . $activeSubAccountId . '-' . date('YmdHis'),
                'title'            => 'Removed from VPS',
                'message'          => $ownerName . ' has removed you from their VPS. You may now request space from another host.',
                'type'             => 'warning',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
                'force'            => true
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'Follower removed successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove follower.']);
    }
    exit;
}

function showSpinner() {
    echo '<style>
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: transparent;
            z-index: 99999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            pointer-events: none;
        }
        .spinner-overlay.active { display: flex; }
        .spinner {
            display: inline-block;
            width: 40px; height: 40px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--accent, #10b981);
            animation: spin 0.6s linear infinite;
            margin-bottom: 12px;
        }
        .spinner-text {
            color: var(--text-muted, #888);
            font-size: 14px;
            margin: 0;
            letter-spacing: 0.5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    <div class="spinner-overlay active" id="spinnerOverlay">
        <div class="spinner"></div>
        <p class="spinner-text">Loading...</p>
    </div>
    <script>
        window.addEventListener("load", function() {
            var overlay = document.getElementById("spinnerOverlay");
            if (overlay) setTimeout(function() { overlay.classList.remove("active"); }, 300);
        });
        document.addEventListener("DOMContentLoaded", function() {
            var overlay = document.getElementById("spinnerOverlay");
            if (overlay) setTimeout(function() { overlay.classList.remove("active"); }, 200);
        });
    </script>';
}

showSpinner();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VPS Hub - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'vps_style.php'; ?>

</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> vps-page-body">

    <div class="vps-page-wrapper">

        <div class="vps-sticky-top" id="vpsStickyTop">

            <div class="vps-topbar">
                <a href="#"
                   class="vps-topbar-back"
                   onclick="event.preventDefault(); harvhubGoToTab('mydashboard'); return false;"
                   aria-label="Back to Dashboard">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h1 class="vps-topbar-title">VPS Hub</h1>
            </div>

            <div class="vps-search-wrap">
                <input type="text"
                       id="vpsSearchInput"
                       class="vps-search-input"
                       placeholder="Search by profile link, username or email…"
                       autocomplete="off"
                       oninput="onVpsSearch(this.value)"
                       onfocus="onVpsSearch(this.value)">
            </div>

            <div class="vps-tabs" id="vpsTabs">
                <button class="vps-tab active" data-tab="purchase" onclick="switchVpsTab('purchase')">
                    Purchase VPS
                </button>
                <button class="vps-tab" data-tab="users" onclick="switchVpsTab('users')">
                    VPS Owners
                </button>

                <?php if ($userOwnsVps): ?>
                <button class="vps-tab" data-tab="incoming" onclick="switchVpsTab('incoming')">
                    Incoming Requests
                    <span class="vps-tab-badge" id="incomingTabBadge" style="display:none;">0</span>
                </button>
                <?php endif; ?>

                <?php if ($isAlreadyFollower && !$userOwnsVps): ?>
                    <button class="vps-tab" data-tab="myspace" onclick="switchVpsTab('myspace')">
                        My Space
                    </button>
                <?php else: ?>
                    <button class="vps-tab" data-tab="sent" onclick="switchVpsTab('sent')">
                        Sent Requests
                    </button>
                <?php endif; ?>

                <?php if ($userOwnsVps): ?>
                <button class="vps-tab" data-tab="followers" onclick="switchVpsTab('followers')">
                    My Followers
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="vps-scroll-area" id="vpsScrollArea">

            <div class="vps-tab-content active" id="vpsTabPurchase">
                <div class="vps-empty-state">
                    <span class="vps-empty-icon">—</span>
                    <p>Not available at the moment</p>
                </div>
            </div>

            <div class="vps-tab-content" id="vpsTabUsers">
                <?php if ($isAlreadyFollower): ?>
                    <div class="vps-follower-lock" style="margin-bottom:16px;padding:12px 16px;background:rgba(243,156,18,0.1);border-radius:8px;font-size:13px;line-height:1.5;color:var(--text);">
                        <strong>This account is already a follower of a VPS host.</strong>
                        You cannot send requests to other hosts while this account is an active follower.
                    </div>
                <?php endif; ?>

                <?php if ($linkableProgVps): ?>
                    <?php
                        $progName = trim((string)($linkableProgVps['program_name'] ?? ''));
                        if ($progName === '') $progName = 'Programme #' . (int)($linkableProgVps['prog_id'] ?? 0);
                    ?>
                    <div class="vps-link-card" id="linkableProgVpsCard">
                        <p class="vps-link-note">
                            You have an existing VPS in <strong><?= htmlspecialchars($progName) ?></strong>, do you want to link it to this sub-account?
                        </p>
                        <button class="vps-btn-link" onclick="linkProgrammeVps()">
                            <i class="fa-solid fa-link"></i> Link
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (empty($hosts) && !$linkableProgVps): ?>
                    <div class="vps-empty-state">
                        <span class="vps-empty-icon">—</span>
                        <p>No public VPS hosts available at the moment</p>
                    </div>
                <?php elseif (!empty($hosts)): ?>
                    <div class="vps-hosts-list" id="vpsHostsList">
                        <?php foreach ($hosts as $host):
                            $ownerId    = (int)$host['user_id'];
                            $ownerSubId = (int)$host['host_sub_account_id'];
                            $key        = $ownerId . ':' . $ownerSubId;
                            $currentHosts = $hostFollowerCounts[$key] ?? 0;
                            $maxHosts = 5;
                            $hostName = resolveHostDisplayName($host);
                            $serverLocation = $host['server_location'] ?: 'N/A';
                            $isSelf = ($ownerId === $userId && $ownerSubId === $activeSubAccountId);
                            $isFull = ($currentHosts >= $maxHosts);
                            $isLocked = $isAlreadyFollower;

                            $searchText = strtolower(
                                ($host['username'] ?? '') . ' ' .
                                ($host['sub_account_name'] ?? '') . ' ' .
                                ($host['first_name'] ?? '') . ' ' .
                                ($host['last_name'] ?? '') . ' ' .
                                ($host['fullname'] ?? '') . ' ' .
                                ($host['host_email'] ?? '')
                            );
                        ?>
                            <div class="vps-host-item"
                                 data-search-text="<?= htmlspecialchars($searchText) ?>">
                                <div class="vps-host-name" onclick="showHostModal(<?= $ownerId ?>, <?= $ownerSubId ?>)" role="button" tabindex="0">
                                    <?= htmlspecialchars($hostName) ?>
                                </div>
                                <div class="vps-host-meta">
                                    <span class="vps-host-stat">
                                        <span class="vps-host-stat-label">Proxy</span>
                                        <span class="vps-host-stat-value"><?= htmlspecialchars($serverLocation) ?></span>
                                    </span>
                                    <span class="vps-host-stat">
                                        <span class="vps-host-stat-label">Maximum host</span>
                                        <span class="vps-host-stat-value"><?= $maxHosts ?></span>
                                    </span>
                                    <span class="vps-host-stat">
                                        <span class="vps-host-stat-label">Current hosts</span>
                                        <span class="vps-host-stat-value"><?= $currentHosts ?></span>
                                    </span>
                                </div>
                                <div class="vps-host-actions">
                                    <?php if ($isSelf): ?>
                                        <button class="vps-btn-request disabled" disabled>Your VPS</button>
                                    <?php elseif ($isLocked): ?>
                                        <button class="vps-btn-request disabled" disabled title="This account is already a follower of a VPS">Request Space</button>
                                    <?php elseif ($isFull): ?>
                                        <button class="vps-btn-request disabled" disabled>Full</button>
                                    <?php else: ?>
                                        <button class="vps-btn-request" onclick="showHostModal(<?= $ownerId ?>, <?= $ownerSubId ?>)">Request Space</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="vpsSearchEmpty" class="vps-empty-state" style="display:none;">
                        <span class="vps-empty-icon">—</span>
                        <p>No hosts match your search</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isAlreadyFollower && !$userOwnsVps): ?>
                <div class="vps-tab-content" id="vpsTabMySpace">
                    <div id="mySpaceContainer" class="vps-requests-list">
                        <div class="vps-requests-loading">Loading your space...</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="vps-tab-content" id="vpsTabSent">
                    <div id="sentRequestsContainer" class="vps-requests-list">
                        <div class="vps-requests-loading">Loading sent requests...</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($userOwnsVps): ?>
            <div class="vps-tab-content" id="vpsTabIncoming">
                <div id="incomingRequestsContainer" class="vps-requests-list">
                    <div class="vps-requests-loading">Loading incoming requests...</div>
                </div>
            </div>

            <div class="vps-tab-content" id="vpsTabFollowers">
                <div id="followersContainer" class="vps-requests-list">
                    <div class="vps-requests-loading">Loading followers...</div>
                </div>
            </div>
            <?php endif; ?>

        </div>

    </div>

    <div id="vpsHostModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title">Host Profile</h2>
            <div class="vps-modal-body" id="vpsHostModalBody">
                <div class="vps-modal-loading">Loading...</div>
            </div>
            <div class="vps-modal-actions" id="vpsHostModalActions">
                <button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>
            </div>
        </div>
    </div>

    <div id="vpsConfirmModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title" id="vpsConfirmModalTitle">Confirm Removal</h2>
            <div class="vps-modal-body">
                <div class="vps-modal-row" id="vpsConfirmTargetRow">
                    <span class="vps-modal-label" id="vpsConfirmTargetLabel">Follower</span>
                    <span class="vps-modal-value" id="vpsConfirmFollowerName">—</span>
                </div>
                <p style="margin-top:14px;color:var(--text-muted);font-size:13px;line-height:1.5;" id="vpsConfirmMessage">
                    Are you sure?
                </p>
            </div>
            <div class="vps-modal-actions">
                <button class="vps-btn-confirm" id="vpsConfirmRemoveBtn" onclick="confirmModalAction()">Yes, Remove</button>
                <button class="vps-modal-close" onclick="closeVpsConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>

    <div id="vpsAlertModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title" id="vpsAlertModalTitle">Notice</h2>
            <div class="vps-modal-body">
                <p id="vpsAlertMessage" style="color:var(--text-muted);font-size:14px;line-height:1.5;margin:0;">
                    Message
                </p>
            </div>
            <div class="vps-modal-actions">
                <button class="vps-btn-confirm" id="vpsAlertOkBtn" onclick="closeVpsAlertModal()">OK</button>
            </div>
        </div>
    </div>

<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-vps');

        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-trader_app'
        ];
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

    var currentHostOwnerId  = null;
    var currentHostSubId    = 0;
    var userHasVps          = <?= $userHasVps ? 'true' : 'false' ?>;
    var userOwnsVps         = <?= $userOwnsVps ? 'true' : 'false' ?>;
    var isAlreadyFollower   = <?= $isAlreadyFollower ? 'true' : 'false' ?>;
    var modeIsMySpace       = <?= ($isAlreadyFollower && !$userOwnsVps) ? 'true' : 'false' ?>;
    var hasLinkableProgVps  = <?= $linkableProgVps ? 'true' : 'false' ?>;

    var pendingConfirmAction = null;
    var pendingConfirmId = null;
    var pendingConfirmName = '';

    function updateIncomingBadge(count) {
        var badge = document.getElementById('incomingTabBadge');
        if (!badge) return;
        var n = parseInt(count, 10) || 0;
        if (n > 0) {
            badge.textContent = n;
            badge.style.display = 'inline-flex';
        } else {
            badge.style.display = 'none';
        }
    }
    window.updateIncomingBadge = updateIncomingBadge;

    function onVpsSearch(query) {
        var q = (query || '').trim().toLowerCase();
        var listEl = document.getElementById('vpsHostsList');
        if (!listEl) return;

        var items = listEl.querySelectorAll('.vps-host-item');
        var anyVisible = false;

        items.forEach(function (el) {
            var searchText = el.getAttribute('data-search-text') || '';
            var matches = (q === '') || (searchText.indexOf(q) !== -1);
            if (matches) { el.style.display = ''; anyVisible = true; }
            else { el.style.display = 'none'; }
        });

        var emptyEl = document.getElementById('vpsSearchEmpty');
        if (emptyEl) emptyEl.style.display = (anyVisible || q === '') ? 'none' : '';
        listEl.style.display = anyVisible ? '' : 'none';
    }

    function switchVpsTab(tab) {
        document.querySelectorAll('.vps-tab').forEach(function(el) {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        document.querySelectorAll('.vps-tab-content').forEach(function(el) {
            el.classList.remove('active');
        });

        var scrollArea = document.getElementById('vpsScrollArea');
        if (scrollArea) scrollArea.scrollTop = 0;

        if (tab === 'purchase') {
            document.getElementById('vpsTabPurchase').classList.add('active');
        } else if (tab === 'users') {
            document.getElementById('vpsTabUsers').classList.add('active');
            var searchInput = document.getElementById('vpsSearchInput');
            if (searchInput) searchInput.value = '';
            onVpsSearch('');
        } else if (tab === 'sent') {
            var sentEl = document.getElementById('vpsTabSent');
            if (sentEl) { sentEl.classList.add('active'); loadSentRequests(); }
        } else if (tab === 'myspace') {
            var msEl = document.getElementById('vpsTabMySpace');
            if (msEl) { msEl.classList.add('active'); loadMySpace(); }
        } else if (tab === 'incoming' && userOwnsVps) {
            var inc = document.getElementById('vpsTabIncoming');
            if (inc) { inc.classList.add('active'); loadIncomingRequests(); }
        } else if (tab === 'followers' && userOwnsVps) {
            var fol = document.getElementById('vpsTabFollowers');
            if (fol) { fol.classList.add('active'); loadMyFollowers(); }
        }

        showStickyTop();
    }
    window.switchVpsTab = switchVpsTab;

    var stickyTop  = document.getElementById('vpsStickyTop');
    var scrollArea = document.getElementById('vpsScrollArea');
    var lastScrollTop   = 0;
    var isStickyHidden  = false;
    var raf             = null;

    function hideStickyTop() {
        if (isStickyHidden || !stickyTop) return;
        stickyTop.classList.add('is-hidden');
        isStickyHidden = true;
    }
    function showStickyTop() {
        if (stickyTop) stickyTop.classList.remove('is-hidden');
        isStickyHidden = false;
    }
    window.showStickyTop = showStickyTop;

    function handleScroll() {
        if (!scrollArea) return;
        var cur = scrollArea.scrollTop || 0;
        var maxScroll = (scrollArea.scrollHeight - scrollArea.clientHeight);
        var atBottom = (cur >= maxScroll - 5);

        if (cur > lastScrollTop + 1) {
            if (!atBottom) hideStickyTop();
        } else if (cur < lastScrollTop - 1) {
            showStickyTop();
        }

        if (cur <= 0) showStickyTop();
        if (atBottom) showStickyTop();

        lastScrollTop = cur <= 0 ? 0 : cur;
    }

    function onScrollThrottled() {
        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(handleScroll);
    }

    if (scrollArea) {
        scrollArea.addEventListener('scroll', onScrollThrottled, { passive: true });
    }

    function showVpsAlert(message, title, reloadAfter) {
        document.getElementById('vpsAlertModalTitle').textContent = title || 'Notice';
        document.getElementById('vpsAlertMessage').textContent = message == null ? '' : String(message);

        var okBtn = document.getElementById('vpsAlertOkBtn');
        if (okBtn) {
            okBtn.onclick = function () {
                closeVpsAlertModal();
                if (reloadAfter) {
                    if (reloadAfter === 'incoming') loadIncomingRequests();
                    else window.location.reload();
                }
            };
        }

        document.getElementById('vpsAlertModal').classList.add('active');
        lockBodyScroll();
    }
    window.showVpsAlert = showVpsAlert;

    function closeVpsAlertModal() {
        document.getElementById('vpsAlertModal').classList.remove('active');
        unlockBodyScroll();
    }
    window.closeVpsAlertModal = closeVpsAlertModal;

    function openVpsConfirm(opts) {
        pendingConfirmAction = opts.type || null;
        pendingConfirmId     = opts.id || null;
        pendingConfirmName   = opts.name || '';

        document.getElementById('vpsConfirmModalTitle').textContent = opts.title || 'Confirm';
        document.getElementById('vpsConfirmTargetLabel').textContent = opts.label || 'Target';
        document.getElementById('vpsConfirmFollowerName').textContent = opts.name || '—';
        document.getElementById('vpsConfirmMessage').textContent = opts.message || '';
        document.getElementById('vpsConfirmRemoveBtn').textContent = opts.confirmText || 'Yes, Confirm';

        var targetRow = document.getElementById('vpsConfirmTargetRow');
        targetRow.style.display = opts.name ? '' : 'none';

        document.getElementById('vpsConfirmModal').classList.add('active');
        lockBodyScroll();
    }
    window.openVpsConfirm = openVpsConfirm;

    function closeVpsConfirmModal() {
        document.getElementById('vpsConfirmModal').classList.remove('active');
        unlockBodyScroll();
        pendingConfirmAction = null;
        pendingConfirmId = null;
        pendingConfirmName = '';
    }
    window.closeVpsConfirmModal = closeVpsConfirmModal;

    function confirmModalAction() {
        if (!pendingConfirmAction || !pendingConfirmId) {
            closeVpsConfirmModal();
            return;
        }

        var btn = document.getElementById('vpsConfirmRemoveBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        if (pendingConfirmAction === 'remove_follower') {
            var rowId = pendingConfirmId;
            fetch('vps.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'remove_follower=1&follower_row_id=' + encodeURIComponent(rowId)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    closeVpsConfirmModal();
                    showVpsAlert(data.message || 'Follower removed successfully.', 'Success');
                    loadMyFollowers();
                } else {
                    showVpsAlert(data.message || 'Failed to remove follower', 'Error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = originalText;
                showVpsAlert('Network error. Please try again.', 'Error');
            });
        } else if (pendingConfirmAction === 'delete_request') {
            var reqId = pendingConfirmId;
            fetch('vps.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'delete_request=1&request_id=' + encodeURIComponent(reqId)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    closeVpsConfirmModal();
                    showVpsAlert(data.message || 'Request deleted.', 'Success');
                    loadSentRequests();
                } else {
                    showVpsAlert(data.message || 'Failed to delete request', 'Error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = originalText;
                showVpsAlert('Network error. Please try again.', 'Error');
            });
        } else {
            btn.disabled = false;
            btn.textContent = originalText;
            closeVpsConfirmModal();
        }
    }
    window.confirmModalAction = confirmModalAction;

    function linkProgrammeVps() {
        var btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Linking...';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'link_programme_vps=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showVpsAlert(data.message || 'VPS linked successfully!', 'Success', true);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-link"></i> Link';
                showVpsAlert(data.message || 'Failed to link.', 'Error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-link"></i> Link';
            showVpsAlert('Network error. Please try again.', 'Error');
        });
    }
    window.linkProgrammeVps = linkProgrammeVps;

    function loadSentRequests() {
        var container = document.getElementById('sentRequestsContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading sent requests...</div>';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_sent_requests=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.requests.length) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>This account has not sent any requests yet</p></div>';
                return;
            }
            renderRequestList(container, data.requests, 'sent');
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load sent requests</p></div>';
        });
    }
    window.loadSentRequests = loadSentRequests;

    function loadMySpace() {
        var container = document.getElementById('mySpaceContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading your space...</div>';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_my_follower_owner=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success || !data.is_follower) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>You are not a follower of any VPS</p></div>';
                return;
            }
            renderMySpace(container, data.owner, data.self);
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load your space</p></div>';
        });
    }
    window.loadMySpace = loadMySpace;

    function renderMySpace(container, owner, self) {
        var totalFollowers = parseInt(owner.total_followers, 10) || 0;
        var status = (self.host_status || 'active').toLowerCase();
        var myName = self.display_name || 'You';

        var html = '';

        html += '<div class="vps-owner-card">';
        html += '  <div class="vps-owner-badge">VPS Owner</div>';
        html += '  <div class="vps-owner-name">' + escapeHtml(owner.fullname || 'Anonymous') + '</div>';
        html += '  <div class="vps-owner-meta">';
        html += '    <div class="vps-owner-stat"><span class="vps-owner-stat-label">Proxy</span><span class="vps-owner-stat-value">' + escapeHtml(owner.server_location || 'N/A') + '</span></div>';
        if (owner.subscription_duration) {
            html += '    <div class="vps-owner-stat"><span class="vps-owner-stat-label">Subscription Duration</span><span class="vps-owner-stat-value">' + escapeHtml(String(owner.subscription_duration)) + ' days</span></div>';
        }
        html += '    <div class="vps-owner-stat"><span class="vps-owner-stat-label">Total Followers</span><span class="vps-owner-stat-value">' + totalFollowers + '</span></div>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="vps-followers-section">';
        html += '  <div class="vps-followers-title">You</div>';
        html += '  <div class="vps-hosts-list">';
        html += '    <div class="vps-request-item vps-follower-self">';
        html += '      <div class="vps-request-row"><div class="vps-request-label">Follower</div><div class="vps-request-value">' + escapeHtml(myName) + ' <span class="vps-self-badge">you</span></div></div>';
        html += '      <div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div>';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function loadIncomingRequests() {
        var container = document.getElementById('incomingRequestsContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading incoming requests...</div>';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_incoming_requests=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>' + escapeHtml(data.message || 'Failed to load incoming requests') + '</p></div>';
                updateIncomingBadge(0);
                return;
            }

            updateIncomingBadge(data.count || (data.requests ? data.requests.length : 0));

            if (!data.requests || !data.requests.length) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>No pending requests at the moment</p></div>';
                return;
            }
            renderRequestList(container, data.requests, 'incoming');
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load incoming requests</p></div>';
        });
    }
    window.loadIncomingRequests = loadIncomingRequests;

    function loadMyFollowers() {
        var container = document.getElementById('followersContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading followers...</div>';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_my_followers=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.followers.length) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>This account has no followers yet</p></div>';
                return;
            }

            var html = '<div class="vps-hosts-list">';
            data.followers.forEach(function(f) {
                var name = f.follower_display_name || 'Unknown';
                var status = (f.host_status || 'active').toLowerCase();

                html += '<div class="vps-request-item">';
                html += '  <div class="vps-request-row"><div class="vps-request-label">Follower</div><div class="vps-request-value">' + escapeHtml(name) + '</div></div>';
                html += '  <div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div>';
                html += '  <div class="vps-request-row"><div class="vps-request-label">Action</div><div class="vps-request-value">';
                html += '    <button class="vps-btn-delete" data-follower-id="' + f.id + '" data-follower-name="' + escapeAttr(name) + '" onclick="removeFollowerClicked(this)">Remove</button>';
                html += '  </div></div>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load followers</p></div>';
        });
    }
    window.loadMyFollowers = loadMyFollowers;

    function renderRequestList(container, requests, mode) {
        var html = '<div class="vps-hosts-list">';

        requests.forEach(function(req) {
            var name = (mode === 'sent')
                ? (req.owner_display_name || 'Anonymous')
                : (req.requestor_display_name || 'Anonymous');

            var label = (mode === 'sent') ? 'Host' : 'Requestor';
            var location = (mode === 'sent' && req.owner_location) ? req.owner_location : '';
            var status = (req.request_status || 'pending').toLowerCase();

            html += '<div class="vps-request-item">';
            html += '  <div class="vps-request-row"><div class="vps-request-label">' + label + '</div><div class="vps-request-value">' + escapeHtml(name) + '</div></div>';

            if (location) {
                html += '  <div class="vps-request-row"><div class="vps-request-label">Proxy</div><div class="vps-request-value">' + escapeHtml(location) + '</div></div>';
            }

            html += '  <div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div>';
            html += '  <div class="vps-request-row"><div class="vps-request-label">Action</div><div class="vps-request-value">';

            if (mode === 'incoming') {
                if (status === 'pending') {
                    html += '<select class="vps-request-select" data-request-id="' + req.id + '" onchange="updateRequestStatus(this)">';
                    html += '  <option value="">-- Select --</option>';
                    html += '  <option value="accept">Accept</option>';
                    html += '  <option value="reject">Reject</option>';
                    html += '</select>';
                } else {
                    html += '<span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span>';
                }
            } else {
                html += '<button class="vps-btn-delete" data-request-id="' + req.id + '" data-request-name="' + escapeAttr(name) + '" onclick="deleteRequestClicked(this)">Delete Request</button>';
            }

            html += '  </div></div>';
            html += '</div>';
        });

        html += '</div>';
        container.innerHTML = html;
    }

    function removeFollowerClicked(btn) {
        var rowId = btn.getAttribute('data-follower-id');
        var name  = btn.getAttribute('data-follower-name') || '';
        if (!rowId) return;

        openVpsConfirm({
            type: 'remove_follower',
            id: rowId,
            name: name,
            title: 'Remove Follower',
            label: 'Follower',
            message: 'Are you sure you want to remove this follower from this VPS? This will revoke their access and they will be removed from the followers list.',
            confirmText: 'Yes, Remove'
        });
    }
    window.removeFollowerClicked = removeFollowerClicked;

    function deleteRequestClicked(btn) {
        var reqId = btn.getAttribute('data-request-id');
        var name  = btn.getAttribute('data-request-name') || '';
        if (!reqId) return;

        openVpsConfirm({
            type: 'delete_request',
            id: reqId,
            name: name,
            title: 'Delete Request',
            label: 'Host',
            message: 'Are you sure you want to delete this sent request? This action cannot be undone.',
            confirmText: 'Yes, Delete'
        });
    }
    window.deleteRequestClicked = deleteRequestClicked;

    var _updatingRequestIds = {};

    function updateRequestStatus(selectEl) {
        var requestId = selectEl.getAttribute('data-request-id');
        var newStatus = selectEl.value;

        if (!requestId || !newStatus) return;
        if (_updatingRequestIds[requestId]) return;
        _updatingRequestIds[requestId] = true;
        selectEl.disabled = true;

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'update_request_status=1&request_id=' + encodeURIComponent(requestId) + '&new_status=' + encodeURIComponent(newStatus)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _updatingRequestIds[requestId] = false;

            if (data.success) {
                loadIncomingRequests();
                showVpsAlert(
                    newStatus === 'accept' ? 'Request accepted. User added as follower.' : 'Request rejected.',
                    'Success'
                );
            } else {
                if (data.cleared || data.needs_reload) {
                    showVpsAlert(data.message || 'This user is already with another VPS owner.', 'Notice', 'incoming');
                } else {
                    showVpsAlert(data.message || 'Failed to update status', 'Error');
                }
                selectEl.value = '';
                selectEl.disabled = false;
                loadIncomingRequests();
            }
        })
        .catch(function() {
            _updatingRequestIds[requestId] = false;
            showVpsAlert('Network error. Please try again.', 'Error');
            selectEl.value = '';
            selectEl.disabled = false;
        });
    }
    window.updateRequestStatus = updateRequestStatus;

    function lockBodyScroll() {
        if (document.body.dataset.vpsModalScrollLocked === '1') return;
        var scrollY = window.scrollY || window.pageYOffset || 0;
        document.body.dataset.vpsModalScrollLocked = '1';
        document.body.dataset.vpsModalScrollY = scrollY;
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + scrollY + 'px';
    }

    function unlockBodyScroll() {
        if (document.body.dataset.vpsModalScrollLocked !== '1') return;
        var scrollY = parseInt(document.body.dataset.vpsModalScrollY || '0', 10) || 0;
        document.body.dataset.vpsModalScrollLocked = '0';
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, scrollY);
    }

    function showHostModal(ownerId, ownerSubId) {
        currentHostOwnerId = ownerId;
        currentHostSubId   = ownerSubId || 0;

        var modal = document.getElementById('vpsHostModal');
        var body = document.getElementById('vpsHostModalBody');
        var actions = document.getElementById('vpsHostModalActions');

        body.innerHTML = '<div class="vps-modal-loading">Loading...</div>';
        actions.innerHTML = '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';

        modal.classList.add('active');
        lockBodyScroll();

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_host_details=1&owner_id=' + encodeURIComponent(ownerId) + '&owner_sub_account_id=' + encodeURIComponent(ownerSubId || 0)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var h = data.host;
                body.innerHTML =
                    '<div class="vps-modal-row"><span class="vps-modal-label">Username</span><span class="vps-modal-value">' + escapeHtml(h.fullname) + '</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Proxy</span><span class="vps-modal-value">' + escapeHtml(h.server_location || 'N/A') + '</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Subscription Duration</span><span class="vps-modal-value">' + escapeHtml(String(h.subscription_duration || 0)) + ' days</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Current Hosts</span><span class="vps-modal-value">' + h.current_hosts + ' / ' + h.max_hosts + '</span></div>';

                var actionsHtml = '';

                if (h.request_locked) {
                    var reason = 'Request Space';
                    if (h.is_self)                      reason = 'Your VPS';
                    else if (h.is_follower_lock)        reason = 'Already a Follower';
                    else if (h.is_full)                 reason = 'Full';
                    else if (h.already_requested)       reason = 'Requested';
                    actionsHtml += '<button class="vps-modal-close requested" disabled>' + escapeHtml(reason) + '</button>';
                } else {
                    actionsHtml += '<button class="vps-btn-confirm" onclick="requestVpsSpace(' + h.owner_id + ', ' + (h.owner_sub_account_id || 0) + ')">Request Space</button>';
                }

                actionsHtml += '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';
                actions.innerHTML = actionsHtml;
            } else {
                body.innerHTML = '<div class="vps-modal-error">' + escapeHtml(data.message || 'Unable to load host details.') + '</div>';
            }
        })
        .catch(function() {
            body.innerHTML = '<div class="vps-modal-error">Network error. Please try again.</div>';
        });
    }
    window.showHostModal = showHostModal;

    function closeVpsHostModal() {
        document.getElementById('vpsHostModal').classList.remove('active');
        unlockBodyScroll();
        currentHostOwnerId = null;
        currentHostSubId = 0;
    }
    window.closeVpsHostModal = closeVpsHostModal;

    function requestVpsSpace(ownerId, ownerSubId) {
        if (!ownerId) return;

        if (isAlreadyFollower) {
            showVpsAlert('This account is already a follower of another VPS. You cannot request space from other hosts.', 'Error');
            return;
        }

        var btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'request_vps_space=1&owner_id=' + encodeURIComponent(ownerId) + '&owner_sub_account_id=' + encodeURIComponent(ownerSubId || 0)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = 'Requested';
                btn.className = 'vps-modal-close requested';
                closeVpsHostModal();
                showVpsAlert(data.message || 'Request submitted successfully!', 'Success');
            } else {
                btn.disabled = false;
                btn.textContent = 'Request Space';
                showVpsAlert(data.message || 'Unable to submit request.', 'Error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Request Space';
            showVpsAlert('Network error. Please try again.', 'Error');
        });
    }
    window.requestVpsSpace = requestVpsSpace;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    function refreshVisibleRequestLists() {
        var sentTab = document.querySelector('.vps-tab[data-tab="sent"]');
        var sentContent = document.getElementById('vpsTabSent');
        if (sentTab && sentTab.classList.contains('active') && sentContent && sentContent.classList.contains('active')) {
            loadSentRequests();
        }

        var msTab = document.querySelector('.vps-tab[data-tab="myspace"]');
        var msContent = document.getElementById('vpsTabMySpace');
        if (msTab && msTab.classList.contains('active') && msContent && msContent.classList.contains('active')) {
            loadMySpace();
        }

        if (userOwnsVps) {
            var incomingTab = document.querySelector('.vps-tab[data-tab="incoming"]');
            var incomingContent = document.getElementById('vpsTabIncoming');
            if (incomingTab && incomingTab.classList.contains('active') && incomingContent && incomingContent.classList.contains('active')) {
                loadIncomingRequests();
            }

            var folTab = document.querySelector('.vps-tab[data-tab="followers"]');
            var folContent = document.getElementById('vpsTabFollowers');
            if (folTab && folTab.classList.contains('active') && folContent && folContent.classList.contains('active')) {
                loadMyFollowers();
            }
        }
    }
    window.refreshVisibleRequestLists = refreshVisibleRequestLists;

    function refreshIncomingBadge() {
        if (!userOwnsVps) return;
        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_incoming_requests=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data && data.success) {
                updateIncomingBadge(data.count || (data.requests ? data.requests.length : 0));
            }
        })
        .catch(function() {});
    }
    window.refreshIncomingBadge = refreshIncomingBadge;

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshVisibleRequestLists();
            refreshIncomingBadge();
        }
    });

    window.addEventListener('focus', function() {
        refreshVisibleRequestLists();
        refreshIncomingBadge();
    });

    var _vpsRefreshTimer = null;
    function startVpsAutoRefresh() {
        if (_vpsRefreshTimer) clearInterval(_vpsRefreshTimer);
        _vpsRefreshTimer = setInterval(function() {
            if (!document.hidden) {
                refreshVisibleRequestLists();
                refreshIncomingBadge();
            }
        }, 8000);
    }
    startVpsAutoRefresh();

    window.addEventListener('beforeunload', function() {
        if (_vpsRefreshTimer) {
            clearInterval(_vpsRefreshTimer);
            _vpsRefreshTimer = null;
        }
    });

    window.onVpsSearch = onVpsSearch;

    if (userOwnsVps) refreshIncomingBadge();

    (function () {
        function syncVpsTabsVisibility() {
            var tabsContainer = document.getElementById('vpsTabs') || document.querySelector('.vps-tabs');
            if (!tabsContainer) return;

            var visibleCount = 0;
            tabsContainer.querySelectorAll('.vps-tab').forEach(function (tab) {
                if (tab.offsetParent !== null || (tab.getClientRects && tab.getClientRects().length > 0)) {
                    visibleCount++;
                }
            });

            if (visibleCount < 2) {
                tabsContainer.classList.add('vps-tabs-hidden');
            } else {
                tabsContainer.classList.remove('vps-tabs-hidden');
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', syncVpsTabsVisibility);
        } else {
            syncVpsTabsVisibility();
        }

        window.addEventListener('focus', syncVpsTabsVisibility);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) syncVpsTabsVisibility();
        });

        window.syncVpsTabsVisibility = syncVpsTabsVisibility;
    })();
</script>

</body>
</html>