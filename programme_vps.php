<?php
// programme_vps.php — strictly programme-scoped VPS hub (PROGRAMME SIDE ONLY)
session_start();
require_once 'usersdb.php';

try { $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
catch (Exception $e) { die("Database connection failed."); }

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php?role=developer"); exit; }
$userId = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($activeProgrammeId <= 0) { header("Location: traderapp.php?tab=vps"); exit; }

$stmt = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
$stmt->execute([$activeProgrammeId, $userId]);
$programme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$programme) { header("Location: traderapp.php?tab=vps"); exit; }

$fullName = $user['fullname'] ?? 'User';
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ==================== HELPERS ====================
if (!function_exists('resolveProgrammeDisplayName')) {
    function resolveProgrammeDisplayName(array $p) {
        $n = trim((string)($p['program_name'] ?? ''));
        return $n !== '' ? $n : 'Programme #' . (int)($p['id'] ?? 0);
    }
}
if (!function_exists('resolveHostDisplayName')) {
    function resolveHostDisplayName(array $r) {
        foreach (['username','first_name','last_name','fullname'] as $k) {
            $v = trim((string)($r[$k] ?? ''));
            if ($v !== '') return $v;
        }
        return 'N/A';
    }
}
if (!function_exists('resolveSubAccountName')) {
    function resolveSubAccountName(array $row) {
        $subName = trim((string)($row['sub_account_name'] ?? ''));
        if ($subName !== '') return $subName;
        $username = trim((string)($row['username'] ?? ''));
        if ($username !== '') return $username;
        $fullName = trim((string)($row['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        $em = trim((string)($row['email'] ?? ''));
        if ($em !== '' && strpos($em, '@') !== false) return explode('@', $em)[0];
        return 'Sub Account';
    }
}
if (!function_exists('resolveUserEmailById')) {
    function resolveUserEmailById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT email FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            return strtolower(trim((string)($q->fetchColumn() ?: '')));
        } catch (Throwable $e) { return ''; }
    }
}
if (!function_exists('resolveOwnerMainAccountId')) {
    function resolveOwnerMainAccountId($pdo, $ownerId) {
        try {
            $q = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$ownerId]);
            return (int)($q->fetchColumn() ?: 0);
        } catch (Throwable $e) { return 0; }
    }
}
if (!function_exists('countActiveProgrammeFollowers')) {
    function countActiveProgrammeFollowers($pdo, $ownerProgrammeId) {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM programme_vps_hosts_followers WHERE owner_programme_id = ? AND host_status = 'active'");
            $q->execute([$ownerProgrammeId]);
            return (int)($q->fetchColumn() ?: 0);
        } catch (PDOException $e) { return 0; }
    }
}
if (!function_exists('recordProgrammeNotification')) {
    function recordProgrammeNotification($pdo, $programmeId, $userEmail, array $opts) {
        try {
            $key = (string)($opts['notification_key'] ?? ('pn-' . $programmeId . '-' . date('YmdHis') . '-' . mt_rand()));
            $stmt = $pdo->prepare("INSERT INTO programme_notifications (programme_id, user_email, notification_key, title, message, type, section, action_tab, seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->execute([
                (int)$programmeId,
                strtolower((string)$userEmail),
                $key,
                (string)($opts['title'] ?? 'Notification'),
                (string)($opts['message'] ?? ''),
                (string)($opts['type'] ?? 'info'),
                (string)($opts['section'] ?? 'General'),
                isset($opts['action_tab']) ? (string)$opts['action_tab'] : null
            ]);
            return true;
        } catch (Throwable $e) { return false; }
    }
}
if (!function_exists('programmeOwnsAnyVps')) {
    function programmeOwnsAnyVps($pdo, $userId) {
        try {
            $q = $pdo->prepare("
                SELECT pv.id
                FROM programme_vps pv
                INNER JOIN programme p ON p.id = pv.programme_id
                WHERE p.userid = ?
                LIMIT 1
            ");
            $q->execute([$userId]);
            return (bool)$q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return false; }
    }
}
if (!function_exists('findUserSubAccountVps')) {
    function findUserSubAccountVps($pdo, $userId, $mainAccountId) {
        try {
            $sql = "
                SELECT v.*, h.sub_account_name, h.username, h.fullname, h.email,
                       h.sub_account_id AS h_sub_account_id
                FROM vps v
                INNER JOIN harvhub h ON h.id = v.user_id AND h.sub_account_id = v.sub_account_id
                WHERE v.user_id = ?
            ";
            $params = [$userId];
            if ($mainAccountId > 0) {
                $sql .= " AND (h.main_account_id = ? OR h.main_account_id = 0)";
                $params[] = $mainAccountId;
            }
            $sql .= " LIMIT 1";
            $q = $pdo->prepare($sql);
            $q->execute($params);
            return $q->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { return null; }
    }
}
if (!function_exists('linkSubAccountVpsToProgramme')) {
    function linkSubAccountVpsToProgramme($pdo, $vpsRow, $programmeId) {
        try {
            $chk = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
            $chk->execute([$programmeId]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) return true;

            $ins = $pdo->prepare("
                INSERT INTO programme_vps
                  (programme_id, user_id, sub_account_id, server_location, subscription_duration, visibility)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                (int)$programmeId,
                (int)$vpsRow['user_id'],
                (int)($vpsRow['sub_account_id'] ?? 0),
                (string)($vpsRow['server_location'] ?? ''),
                (int)($vpsRow['subscription_duration'] ?? 0),
                'public',
            ]);
            return true;
        } catch (Throwable $e) {
            error_log('linkSubAccountVpsToProgramme failed: ' . $e->getMessage());
            return false;
        }
    }
}

// ==================== FETCH PUBLIC PROGRAMME HOSTS (INCLUDING OWN) ====================
$programmeHosts = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.programme_id, v.user_id, v.server_location, v.subscription_duration, v.visibility,
               p.program_name, h.fullname, h.first_name, h.last_name, h.username, h.email AS host_email
        FROM programme_vps v
        INNER JOIN programme p ON p.id = v.programme_id
        INNER JOIN harvhub   h ON h.id = v.user_id
        WHERE v.visibility = 'public'
        ORDER BY p.program_name ASC
    ");
    $stmt->execute();
    $programmeHosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $programmeHosts = []; }

$followerCountsProgramme = [];
foreach ($programmeHosts as $h) {
    $followerCountsProgramme[(int)$h['programme_id']] = countActiveProgrammeFollowers($pdo, (int)$h['programme_id']);
}

// ==================== FLAGS (STRICTLY PROGRAMME-SCOPED) ====================
$userOwnsProgrammeVps  = false;
$isAlreadyFollowerProg = false;

try {
    $s = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
    $s->execute([$activeProgrammeId]);
    if ($s->fetch(PDO::FETCH_ASSOC)) $userOwnsProgrammeVps = true;
} catch (PDOException $e) {}

try {
    $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
    $s->execute([$activeProgrammeId]);
    if ($s->fetch(PDO::FETCH_ASSOC)) $isAlreadyFollowerProg = true;
} catch (PDOException $e) {}

$userHasVps = ($userOwnsProgrammeVps || $isAlreadyFollowerProg);

// ==================== LINKABLE SUB-ACCOUNT VPS DETECTION ====================
$linkableSubVps = null;
$anyProgrammeOwnsVps = false;

if (!$userOwnsProgrammeVps) {
    $anyProgrammeOwnsVps = programmeOwnsAnyVps($pdo, $userId);

    if (!$anyProgrammeOwnsVps) {
        $linkableSubVps = findUserSubAccountVps($pdo, $userId, $mainAccountId);
    }
}

// ==================== LINK SUB-ACCOUNT VPS TO PROGRAMME (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_subaccount_vps'])) {
    header('Content-Type: application/json');

    if ($userOwnsProgrammeVps) {
        echo json_encode(['success' => false, 'message' => 'This programme already owns a VPS.']);
        exit;
    }

    $anyProgrammeOwnsVps2 = programmeOwnsAnyVps($pdo, $userId);
    if ($anyProgrammeOwnsVps2) {
        echo json_encode(['success' => false, 'message' => 'Another programme already owns a VPS.']);
        exit;
    }

    $subVps = findUserSubAccountVps($pdo, $userId, $mainAccountId);
    if (!$subVps) {
        echo json_encode(['success' => false, 'message' => 'No sub-account VPS found to link.']);
        exit;
    }

    $ok = linkSubAccountVpsToProgramme($pdo, $subVps, $activeProgrammeId);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'VPS linked to this programme successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to link VPS.']);
    }
    exit;
}

// ==================== REQUEST SPACE ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_vps_space'])) {
    header('Content-Type: application/json');

    $ownerProgId = (int)($_POST['owner_programme_id'] ?? 0);

    if ($isAlreadyFollowerProg) {
        echo json_encode(['success'=>false,'message'=>'This programme is already a follower of another VPS.']);
        exit;
    }

    if ($ownerProgId <= 0 || $ownerProgId === $activeProgrammeId) {
        echo json_encode(['success'=>false,'message'=>'Invalid host.']);
        exit;
    }

    try {
        $s = $pdo->prepare("SELECT user_id FROM programme_vps WHERE programme_id = ? AND visibility = 'public' LIMIT 1");
        $s->execute([$ownerProgId]);
        $ownerId = (int)($s->fetchColumn() ?: 0);
    } catch (PDOException $e) { $ownerId = 0; }
    if ($ownerId <= 0) {
        echo json_encode(['success'=>false,'message'=>'This host is not available.']);
        exit;
    }

    try {
        $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_requestors WHERE owner_programme_id = ? AND requestor_programme_id = ? AND request_status IN ('pending','accept') LIMIT 1");
        $s->execute([$ownerProgId, $activeProgrammeId]);
        if ($s->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode(['success'=>false,'message'=>'This programme already has an active request with this host.']);
            exit;
        }
    } catch (PDOException $e) {}

    $requestorDisplayName = resolveProgrammeDisplayName($programme);
    try {
        $ins = $pdo->prepare("
            INSERT INTO programme_vps_hosts_requestors
              (owner_programme_id, requestor_programme_id, request_status)
            VALUES (?, ?, 'pending')
        ");
        $ins->execute([$ownerProgId, $activeProgrammeId]);

        $ownerEmail     = resolveUserEmailById($pdo, $ownerId);
        $ownerMainAccId = resolveOwnerMainAccountId($pdo, $ownerId);

        if ($ownerEmail !== '') {
            recordContractNotification($pdo, [
                'user_email'       => $ownerEmail,
                'sub_account_id'   => $activeSubAccountId,
                'main_account_id'  => $ownerMainAccId,
                'notification_key' => 'pvps-req-received-' . $activeProgrammeId . '-' . date('YmdHis'),
                'title'            => 'New VPS Space Request',
                'message'          => $requestorDisplayName . ' has requested space on your programme VPS.',
                'type'             => 'info',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
                'force'            => true
            ]);

            recordProgrammeNotification($pdo, $activeProgrammeId, $email, [
                'notification_key' => 'pvps-sent-prog-' . $activeProgrammeId . '-' . date('YmdHis'),
                'title'            => 'VPS Request Sent',
                'message'          => 'Your request to ' . $requestorDisplayName . ' has been submitted.',
                'type'             => 'info',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
            ]);
        }
        echo json_encode(['success'=>true,'message'=>'Request submitted successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Failed to submit request.']);
    }
    exit;
}

// ==================== GET HOST DETAILS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_host_details'])) {
    header('Content-Type: application/json');
    $maxHosts = 5;

    $ownerProgId = (int)($_POST['owner_programme_id'] ?? 0);
    if ($ownerProgId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid host.']);
        exit;
    }

    try {
        $s = $pdo->prepare("
            SELECT v.programme_id, v.user_id, v.server_location, v.subscription_duration,
                   p.program_name, h.fullname, h.first_name, h.last_name, h.username, h.email AS host_email
            FROM programme_vps v
            INNER JOIN programme p ON p.id = v.programme_id
            INNER JOIN harvhub   h ON h.id = v.user_id
            WHERE v.programme_id = ? AND v.visibility = 'public'
            LIMIT 1
        ");
        $s->execute([$ownerProgId]);
        $hostData = $s->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $hostData = null; }
    if (!$hostData) {
        echo json_encode(['success'=>false,'message'=>'Host not found.']);
        exit;
    }

    $hostName = resolveProgrammeDisplayName($hostData);
    if ($hostName === 'Programme #' . (int)$hostData['programme_id']) {
        $hostName = resolveHostDisplayName($hostData);
    }

    $followerCount = countActiveProgrammeFollowers($pdo, $ownerProgId);

    $alreadyRequested = false;
    try {
        $q = $pdo->prepare("SELECT id FROM programme_vps_hosts_requestors WHERE owner_programme_id = ? AND requestor_programme_id = ? AND request_status IN ('pending','accept') LIMIT 1");
        $q->execute([$ownerProgId, $activeProgrammeId]);
        $alreadyRequested = (bool)$q->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    $isSelf = ($ownerProgId === $activeProgrammeId);
    $requestLocked = $isSelf || $isAlreadyFollowerProg || ($followerCount >= $maxHosts) || $alreadyRequested;

    echo json_encode(['success'=>true,'host'=>[
        'source' => 'programme',
        'owner_programme_id' => (int)$hostData['programme_id'],
        'owner_id' => (int)$hostData['user_id'],
        'fullname' => $hostName,
        'email' => $hostData['host_email'],
        'server_location' => $hostData['server_location'],
        'subscription_duration' => $hostData['subscription_duration'],
        'current_hosts' => $followerCount,
        'max_hosts' => $maxHosts,
        'is_self' => $isSelf,
        'is_full' => ($followerCount >= $maxHosts),
        'already_requested' => $alreadyRequested,
        'is_follower_lock' => $isAlreadyFollowerProg,
        'request_locked' => $requestLocked,
    ]]);
    exit;
}

// ==================== MY FOLLOWER OWNER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_follower_owner'])) {
    header('Content-Type: application/json');

    try {
        $s = $pdo->prepare("
            SELECT f.id AS follower_row_id, f.host_status, f.owner_programme_id,
                   p.program_name, h.fullname, h.first_name, h.last_name, h.username,
                   v.server_location, v.subscription_duration
            FROM programme_vps_hosts_followers f
            LEFT JOIN programme p ON p.id = f.owner_programme_id
            LEFT JOIN harvhub    h ON h.id = p.userid
            LEFT JOIN programme_vps v ON v.programme_id = f.owner_programme_id
            WHERE f.follower_programme_id = ?
              AND f.host_status = 'active'
            LIMIT 1
        ");
        $s->execute([$activeProgrammeId]);
        $progRow = $s->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $progRow = null; }

    if ($progRow) {
        $ownerName = $progRow['program_name'] ?: resolveHostDisplayName($progRow);
        echo json_encode([
            'success'=>true,
            'is_follower'=>true,
            'source'=>'programme',
            'owner'=>[
                'owner_programme_id'=>(int)$progRow['owner_programme_id'],
                'fullname'=>$ownerName,
                'server_location'=>$progRow['server_location'] ?? '',
                'subscription_duration'=>$progRow['subscription_duration'] ?? 0,
                'total_followers'=>countActiveProgrammeFollowers($pdo,(int)$progRow['owner_programme_id'])
            ],
            'self'=>[
                'follower_row_id'=>$progRow['follower_row_id'],
                'host_status'=>$progRow['host_status'],
                'display_name'=>resolveProgrammeDisplayName($programme)
            ]
        ]);
        exit;
    }

    echo json_encode(['success'=>true,'is_follower'=>false]);
    exit;
}

// ==================== SENT REQUESTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_sent_requests'])) {
    header('Content-Type: application/json');
    try {
        $s = $pdo->prepare("
            SELECT r.id, r.owner_programme_id, r.request_status, r.created_at,
                   p.program_name,
                   pv.server_location AS owner_location
            FROM programme_vps_hosts_requestors r
            LEFT JOIN programme p ON p.id = r.owner_programme_id
            LEFT JOIN programme_vps pv ON pv.programme_id = r.owner_programme_id
            WHERE r.requestor_programme_id = ?
              AND r.request_status IN ('pending','reject')
            ORDER BY r.created_at DESC
        ");
        $s->execute([$activeProgrammeId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $rows = []; }

    $unique = [];
    foreach ($rows as $r) {
        $r['source'] = 'programme';
        $r['owner_display_name'] = $r['program_name'] ?: ('Programme #' . (int)$r['owner_programme_id']);
        $r['owner_location'] = $r['owner_location'] ?? '';
        $unique[] = $r;
    }
    echo json_encode(['success'=>true,'requests'=>$unique]);
    exit;
}

// ==================== INCOMING REQUESTS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_incoming_requests'])) {
    header('Content-Type: application/json');
    if (!$userOwnsProgrammeVps) {
        echo json_encode(['success'=>false,'message'=>'This programme does not own a VPS.','count'=>0]);
        exit;
    }

    try {
        $s = $pdo->prepare("
            SELECT r.id, r.requestor_programme_id, r.request_status, r.created_at,
                   p.program_name
            FROM programme_vps_hosts_requestors r
            LEFT JOIN programme p ON p.id = r.requestor_programme_id
            WHERE r.owner_programme_id = ?
              AND r.request_status = 'pending'
            ORDER BY r.created_at DESC
        ");
        $s->execute([$activeProgrammeId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $rows = []; }

    foreach ($rows as &$r) {
        $r['requestor_display_name'] = $r['program_name'] ?: ('Programme #' . (int)$r['requestor_programme_id']);
    }
    unset($r);

    echo json_encode(['success'=>true,'requests'=>$rows,'count'=>count($rows)]);
    exit;
}

// ==================== UPDATE REQUEST STATUS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    header('Content-Type: application/json');
    $requestId = (int)($_POST['request_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');
    if ($requestId <= 0 || !in_array($newStatus, ['accept','reject'], true)) {
        echo json_encode(['success'=>false,'message'=>'Invalid request.']);
        exit;
    }

    try {
        $s = $pdo->prepare("SELECT * FROM programme_vps_hosts_requestors WHERE id = ? LIMIT 1");
        $s->execute([$requestId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success'=>false,'message'=>'Not found.']);
            exit;
        }
        if ((int)$row['owner_programme_id'] !== $activeProgrammeId) {
            echo json_encode(['success'=>false,'message'=>'Not authorized.']);
            exit;
        }
        if ($row['request_status'] !== 'pending') {
            echo json_encode(['success'=>false,'message'=>'Already processed.']);
            exit;
        }

        $upd = $pdo->prepare("
            UPDATE programme_vps_hosts_requestors
            SET request_status = ?
            WHERE id = ? AND request_status = 'pending'
        ");
        $upd->execute([$newStatus, $requestId]);

        if ($newStatus === 'accept') {
            try {
                $chk = $pdo->prepare("
                    SELECT id FROM programme_vps_hosts_followers
                    WHERE owner_programme_id = ? AND follower_programme_id = ?
                    LIMIT 1
                ");
                $chk->execute([$activeProgrammeId, (int)$row['requestor_programme_id']]);
                if (!$chk->fetch(PDO::FETCH_ASSOC)) {
                    $ins = $pdo->prepare("
                        INSERT INTO programme_vps_hosts_followers
                          (owner_programme_id, follower_programme_id, host_status)
                        VALUES (?, ?, 'active')
                    ");
                    $ins->execute([$activeProgrammeId, (int)$row['requestor_programme_id']]);
                }
            } catch (Throwable $e) {}
        }

        $requestorProgrammeId = (int)$row['requestor_programme_id'];
        $requestorEmail = '';
        $requestorUserId = 0;
        try {
            $q = $pdo->prepare("SELECT userid FROM programme WHERE id = ? LIMIT 1");
            $q->execute([$requestorProgrammeId]);
            $requestorUserId = (int)($q->fetchColumn() ?: 0);
            if ($requestorUserId > 0) {
                $requestorEmail = resolveUserEmailById($pdo, $requestorUserId);
            }
        } catch (Throwable $e) {}

        $ownerDisplay = resolveProgrammeDisplayName($programme);

        if ($requestorEmail !== '') {
            recordContractNotification($pdo, [
                'user_email'       => $requestorEmail,
                'sub_account_id'   => $activeSubAccountId,
                'main_account_id'  => $mainAccountId,
                'notification_key' => 'pvps-' . $newStatus . '-' . $activeProgrammeId . '-' . date('YmdHis'),
                'title'            => $newStatus === 'accept' ? 'VPS Request Accepted' : 'VPS Request Declined',
                'message'          => $newStatus === 'accept'
                                        ? $ownerDisplay . ' has accepted your request.'
                                        : $ownerDisplay . ' has declined your VPS space request.',
                'type'             => $newStatus === 'accept' ? 'success' : 'warning',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
                'force'            => true
            ]);

            recordProgrammeNotification($pdo, $requestorProgrammeId, $requestorEmail, [
                'notification_key' => 'pvps-' . $newStatus . '-' . $activeProgrammeId . '-' . date('YmdHis'),
                'title'            => $newStatus === 'accept' ? 'VPS Request Accepted' : 'VPS Request Declined',
                'message'          => $newStatus === 'accept'
                                        ? $ownerDisplay . ' has accepted your request.'
                                        : $ownerDisplay . ' has declined your VPS space request.',
                'type'             => $newStatus === 'accept' ? 'success' : 'warning',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
            ]);
        }

        echo json_encode(['success'=>true,'message'=>'Request ' . $newStatus . 'ed successfully.','new_status'=>$newStatus]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Failed.']);
    }
    exit;
}

// ==================== DELETE REQUEST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    header('Content-Type: application/json');
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($requestId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid.']);
        exit;
    }
    try {
        $s = $pdo->prepare("SELECT * FROM programme_vps_hosts_requestors WHERE id = ? LIMIT 1");
        $s->execute([$requestId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['requestor_programme_id'] !== $activeProgrammeId) {
            echo json_encode(['success'=>false,'message'=>'Not authorized.']);
            exit;
        }
        $pdo->prepare("DELETE FROM programme_vps_hosts_requestors WHERE id = ?")->execute([$requestId]);
        echo json_encode(['success'=>true,'message'=>'Deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Failed.']);
    }
    exit;
}

// ==================== MY FOLLOWERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_followers'])) {
    header('Content-Type: application/json');
    if (!$userOwnsProgrammeVps) {
        echo json_encode(['success'=>false,'message'=>'No VPS.']);
        exit;
    }
    try {
        $s = $pdo->prepare("
            SELECT f.id, f.follower_programme_id, f.host_status, f.created_at,
                   p.program_name
            FROM programme_vps_hosts_followers f
            LEFT JOIN programme p ON p.id = f.follower_programme_id
            WHERE f.owner_programme_id = ?
            ORDER BY f.created_at DESC
        ");
        $s->execute([$activeProgrammeId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $rows = []; }

    foreach ($rows as &$r) {
        $followerProgId = (int)$r['follower_programme_id'];
        $belongsToUser = false;
        try {
            $chk = $pdo->prepare("SELECT userid FROM programme WHERE id = ? LIMIT 1");
            $chk->execute([$followerProgId]);
            $belongsToUser = ((int)$chk->fetchColumn() === $userId);
        } catch (Throwable $e) {}

        if ($belongsToUser) {
            $subName = trim((string)($r['program_name'] ?? ''));
            $r['follower_display_name'] = 'Your account (' . ($subName ?: 'Account') . ')';
        } else {
            $r['follower_display_name'] = $r['program_name'] ?: ('Programme #' . $followerProgId);
        }
    }
    unset($r);

    echo json_encode(['success'=>true,'followers'=>$rows]);
    exit;
}

// ==================== REMOVE FOLLOWER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_follower'])) {
    header('Content-Type: application/json');
    $rowId = (int)($_POST['follower_row_id'] ?? 0);
    if ($rowId <= 0) {
        echo json_encode(['success'=>false,'message'=>'Invalid.']);
        exit;
    }
    try {
        $s = $pdo->prepare("SELECT * FROM programme_vps_hosts_followers WHERE id = ? LIMIT 1");
        $s->execute([$rowId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['owner_programme_id'] !== $activeProgrammeId) {
            echo json_encode(['success'=>false,'message'=>'Not authorized.']);
            exit;
        }
        $pdo->prepare("DELETE FROM programme_vps_hosts_followers WHERE id = ?")->execute([$rowId]);
        echo json_encode(['success'=>true,'message'=>'Follower removed.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Failed.']);
    }
    exit;
}

function showSpinner() {
    echo '<style>
        .spinner-overlay{display:none;position:fixed;inset:0;background:transparent;z-index:99999;justify-content:center;align-items:center;flex-direction:column;pointer-events:none;}
        .spinner-overlay.active{display:flex;}
        .spinner{display:inline-block;width:40px;height:40px;border:3px solid rgba(0,0,0,0.1);border-radius:50%;border-top-color:var(--accent,#10b981);animation:spin .6s linear infinite;margin-bottom:12px;}
        .spinner-text{color:var(--text-muted,#888);font-size:14px;margin:0;letter-spacing:.5px;}
        @keyframes spin{to{transform:rotate(360deg);}}
    </style>
    <div class="spinner-overlay active" id="spinnerOverlay"><div class="spinner"></div><p class="spinner-text">Loading...</p></div>
    <script>
        window.addEventListener("load",function(){var o=document.getElementById("spinnerOverlay");if(o)setTimeout(function(){o.classList.remove("active");},300);});
        document.addEventListener("DOMContentLoaded",function(){var o=document.getElementById("spinnerOverlay");if(o)setTimeout(function(){o.classList.remove("active");},200);});
    </script>';
}
showSpinner();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programme VPS - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'vps_style.php'; ?>
<style>
    .vps-topbar { position: relative; display: flex; align-items: center; justify-content: center; }
    .vps-topbar-back { position: absolute; left: 0; top: 50%; transform: translateY(-50%); }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> vps-page-body">

    <div class="vps-page-wrapper">
        <div class="vps-sticky-top" id="vpsStickyTop">
            <div class="vps-topbar">
                <a href="#" class="vps-topbar-back" onclick="event.preventDefault(); harvhubGoToTab('signals'); return false;" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></a>
                <h1 class="vps-topbar-title">Programme VPS</h1>
            </div>
            <div class="vps-search-wrap">
                <input type="text" id="vpsSearchInput" class="vps-search-input" placeholder="Search username or url" autocomplete="off" oninput="onVpsSearch(this.value)" onfocus="onVpsSearch(this.value)">
            </div>
            <div class="vps-tabs" id="vpsTabs">
                <button class="vps-tab active" data-tab="purchase" onclick="switchVpsTab('purchase')">Purchase VPS</button>
                <button class="vps-tab" data-tab="users" onclick="switchVpsTab('users')">VPS Owners</button>
                <?php if ($userOwnsProgrammeVps): ?>
                    <button class="vps-tab" data-tab="incoming" onclick="switchVpsTab('incoming')">Incoming Requests <span class="vps-tab-badge" id="incomingTabBadge" style="display:none;">0</span></button>
                <?php endif; ?>
                <?php if ($isAlreadyFollowerProg): ?>
                    <button class="vps-tab" data-tab="myspace" onclick="switchVpsTab('myspace')">My Space</button>
                <?php else: ?>
                    <button class="vps-tab" data-tab="sent" onclick="switchVpsTab('sent')">Sent Requests</button>
                <?php endif; ?>
                <?php if ($userOwnsProgrammeVps): ?>
                    <button class="vps-tab" data-tab="followers" onclick="switchVpsTab('followers')">My Followers</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="vps-scroll-area" id="vpsScrollArea">
            <div class="vps-tab-content active" id="vpsTabPurchase"><div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>Not available at the moment</p></div></div>

            <div class="vps-tab-content" id="vpsTabUsers">
                <?php if ($isAlreadyFollowerProg): ?>
                    <div class="vps-follower-lock" style="margin-bottom:16px;padding:12px 16px;background:rgba(243,156,18,0.1);border-radius:8px;font-size:13px;line-height:1.5;color:var(--text);"><strong>This programme is already a follower of another VPS.</strong> You cannot send requests to other hosts.</div>
                <?php endif; ?>

                <?php if ($linkableSubVps): ?>
                    <?php
                        $subName = resolveSubAccountName($linkableSubVps);
                    ?>
                    <div class="vps-link-card" id="linkableVpsCard">
                        <p class="vps-link-note">
                            You have an existing VPS in <strong><?= htmlspecialchars($subName) ?></strong>, do you want to link it to this programme?
                        </p>
                        <button class="vps-btn-link" onclick="linkSubAccountVps()">
                            <i class="fa-solid fa-link"></i> Link
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (empty($programmeHosts)): ?>
                    <div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>No public VPS hosts available at the moment</p></div>
                <?php else: ?>
                    <div class="vps-hosts-list" id="vpsHostsList">
                        <?php foreach ($programmeHosts as $h):
                            $ownerProgId = (int)$h['programme_id'];
                            $name = resolveProgrammeDisplayName($h);
                            if ($name === 'Programme #' . $ownerProgId) $name = resolveHostDisplayName($h);
                            $maxHosts = 5;
                            $currentHosts = (int)($followerCountsProgramme[$ownerProgId] ?? 0);
                            $isSelf = ($ownerProgId === $activeProgrammeId);
                            $isFull = ($currentHosts >= $maxHosts);
                            $isLocked = $isAlreadyFollowerProg;
                            $searchText = strtolower(($h['program_name'] ?? '') . ' ' . ($h['username'] ?? '') . ' ' . ($h['fullname'] ?? ''));
                        ?>
                            <div class="vps-host-item" data-search-text="<?= htmlspecialchars($searchText) ?>">
                                <div class="vps-host-name" onclick="showHostModal(<?= $ownerProgId ?>)" role="button" tabindex="0">
                                    <?= htmlspecialchars($name) ?>
                                </div>
                                <div class="vps-host-meta">
                                    <span class="vps-host-stat"><span class="vps-host-stat-label">Proxy</span><span class="vps-host-stat-value"><?= htmlspecialchars($h['server_location'] ?: 'N/A') ?></span></span>
                                    <span class="vps-host-stat"><span class="vps-host-stat-label">Maximum host</span><span class="vps-host-stat-value"><?= $maxHosts ?></span></span>
                                    <span class="vps-host-stat"><span class="vps-host-stat-label">Current hosts</span><span class="vps-host-stat-value"><?= $currentHosts ?></span></span>
                                </div>
                                <div class="vps-host-actions">
                                    <?php if ($isSelf): ?>
                                        <button class="vps-btn-request disabled" disabled>Your VPS</button>
                                    <?php elseif ($isLocked): ?>
                                        <button class="vps-btn-request disabled" disabled>Request Space</button>
                                    <?php elseif ($isFull): ?>
                                        <button class="vps-btn-request disabled" disabled>Full</button>
                                    <?php else: ?>
                                        <button class="vps-btn-request" onclick="showHostModal(<?= $ownerProgId ?>)">Request Space</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="vpsSearchEmpty" class="vps-empty-state" style="display:none;"><span class="vps-empty-icon">—</span><p>No hosts match your search</p></div>
                <?php endif; ?>
            </div>

            <?php if ($isAlreadyFollowerProg): ?>
                <div class="vps-tab-content" id="vpsTabMySpace"><div id="mySpaceContainer" class="vps-requests-list"><div class="vps-requests-loading">Loading your space...</div></div></div>
            <?php else: ?>
                <div class="vps-tab-content" id="vpsTabSent"><div id="sentRequestsContainer" class="vps-requests-list"><div class="vps-requests-loading">Loading sent requests...</div></div></div>
            <?php endif; ?>

            <?php if ($userOwnsProgrammeVps): ?>
                <div class="vps-tab-content" id="vpsTabIncoming"><div id="incomingRequestsContainer" class="vps-requests-list"><div class="vps-requests-loading">Loading incoming requests...</div></div></div>
                <div class="vps-tab-content" id="vpsTabFollowers"><div id="followersContainer" class="vps-requests-list"><div class="vps-requests-loading">Loading followers...</div></div></div>
            <?php endif; ?>
        </div>
    </div>

    <div id="vpsHostModal" class="vps-modal"><div class="vps-modal-content"><h2 class="vps-modal-title">Host Profile</h2><div class="vps-modal-body" id="vpsHostModalBody"><div class="vps-modal-loading">Loading...</div></div><div class="vps-modal-actions" id="vpsHostModalActions"><button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button></div></div></div>
    <div id="vpsConfirmModal" class="vps-modal"><div class="vps-modal-content"><h2 class="vps-modal-title" id="vpsConfirmModalTitle">Confirm Removal</h2><div class="vps-modal-body"><div class="vps-modal-row" id="vpsConfirmTargetRow"><span class="vps-modal-label" id="vpsConfirmTargetLabel">Follower</span><span class="vps-modal-value" id="vpsConfirmFollowerName">—</span></div><p style="margin-top:14px;color:var(--text-muted);font-size:13px;line-height:1.5;" id="vpsConfirmMessage">Are you sure?</p></div><div class="vps-modal-actions"><button class="vps-btn-confirm" id="vpsConfirmRemoveBtn" onclick="confirmModalAction()">Yes, Remove</button><button class="vps-modal-close" onclick="closeVpsConfirmModal()">Cancel</button></div></div></div>
    <div id="vpsAlertModal" class="vps-modal"><div class="vps-modal-content"><h2 class="vps-modal-title" id="vpsAlertModalTitle">Notice</h2><div class="vps-modal-body"><p id="vpsAlertMessage" style="color:var(--text-muted);font-size:14px;line-height:1.5;margin:0;">Message</p></div><div class="vps-modal-actions"><button class="vps-btn-confirm" id="vpsAlertOkBtn" onclick="closeVpsAlertModal()">OK</button></div></div></div>

<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-vps');
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
        });
        var WATCHED = ['page-vps','page-connect_trader_broker','page-disconnect_trader_broker','page-programme_training','page-trader_menu'];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type:'bodyClass',
                    add:add,
                    remove: WATCHED.filter(function (c){ return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes:true, attributeFilter:['class'] });
        broadcast();
        try { window.parent.postMessage({ type:'requestTheme' }, '*'); } catch (e) {}
    })();

    var userOwnsProgrammeVps = <?= $userOwnsProgrammeVps ? 'true' : 'false' ?>;
    var isAlreadyFollowerProg = <?= $isAlreadyFollowerProg ? 'true' : 'false' ?>;
    var userHasVps = <?= $userHasVps ? 'true' : 'false' ?>;
    var hasLinkableSubVps = <?= $linkableSubVps ? 'true' : 'false' ?>;

    var pendingConfirmAction=null, pendingConfirmId=null, pendingConfirmName='';

    function updateIncomingBadge(count) {
        var b = document.getElementById('incomingTabBadge');
        if (!b) return;
        var n = parseInt(count,10)||0;
        if (n>0) { b.textContent = n; b.style.display = 'inline-flex'; } else b.style.display='none';
    }
    window.updateIncomingBadge = updateIncomingBadge;

    function onVpsSearch(q) {
        q = (q||'').trim().toLowerCase();
        var listEl = document.getElementById('vpsHostsList');
        if (!listEl) return;
        var items = listEl.querySelectorAll('.vps-host-item');
        var any=false;
        items.forEach(function(el){
            var st = el.getAttribute('data-search-text')||'';
            var m = (q==='')||(st.indexOf(q)!==-1);
            if(m){el.style.display='';any=true;}else el.style.display='none';
        });
        var emptyEl = document.getElementById('vpsSearchEmpty');
        if (emptyEl) emptyEl.style.display = (any||q==='')?'none':'';
        listEl.style.display = any?'':'none';
    }
    window.onVpsSearch = onVpsSearch;

    function switchVpsTab(tab) {
        document.querySelectorAll('.vps-tab').forEach(function(el){ el.classList.toggle('active', el.dataset.tab === tab); });
        document.querySelectorAll('.vps-tab-content').forEach(function(el){ el.classList.remove('active'); });
        var scrollArea = document.getElementById('vpsScrollArea');
        if (scrollArea) scrollArea.scrollTop = 0;

        if (tab === 'purchase') document.getElementById('vpsTabPurchase').classList.add('active');
        else if (tab === 'users') {
            document.getElementById('vpsTabUsers').classList.add('active');
            var s=document.getElementById('vpsSearchInput'); if(s)s.value='';
            onVpsSearch('');
        }
        else if (tab === 'sent') { var el=document.getElementById('vpsTabSent'); if(el){el.classList.add('active');loadSentRequests();} }
        else if (tab === 'myspace') { var el2=document.getElementById('vpsTabMySpace'); if(el2){el2.classList.add('active');loadMySpace();} }
        else if (tab === 'incoming' && userOwnsProgrammeVps) { var el3=document.getElementById('vpsTabIncoming'); if(el3){el3.classList.add('active');loadIncomingRequests();} }
        else if (tab === 'followers' && userOwnsProgrammeVps) { var el4=document.getElementById('vpsTabFollowers'); if(el4){el4.classList.add('active');loadMyFollowers();} }
        showStickyTop();
    }
    window.switchVpsTab = switchVpsTab;

    var stickyTop = document.getElementById('vpsStickyTop'), scrollArea = document.getElementById('vpsScrollArea'),
        lastScrollTop = 0, isStickyHidden = false, raf = null;

    function hideStickyTop(){ if(isStickyHidden||!stickyTop)return; stickyTop.classList.add('is-hidden'); isStickyHidden = true; }
    function showStickyTop(){ if(stickyTop) stickyTop.classList.remove('is-hidden'); isStickyHidden = false; }
    window.showStickyTop = showStickyTop;

    function handleScroll(){
        if(!scrollArea) return;
        var cur = scrollArea.scrollTop||0;
        var max = scrollArea.scrollHeight - scrollArea.clientHeight;
        var atBottom = cur>=max-5;
        if(cur>lastScrollTop+1){if(!atBottom)hideStickyTop();}
        else if(cur<lastScrollTop-1)showStickyTop();
        if(cur<=0)showStickyTop();
        if(atBottom)showStickyTop();
        lastScrollTop = cur<=0?0:cur;
    }
    function onScrollThrottled(){ if(raf)cancelAnimationFrame(raf); raf=requestAnimationFrame(handleScroll); }
    if(scrollArea) scrollArea.addEventListener('scroll', onScrollThrottled, {passive:true});

    function showVpsAlert(message, title, reloadAfter) {
        document.getElementById('vpsAlertModalTitle').textContent = title||'Notice';
        document.getElementById('vpsAlertMessage').textContent = message == null ? '' : String(message);
        var okBtn = document.getElementById('vpsAlertOkBtn');
        if (okBtn) okBtn.onclick = function(){
            closeVpsAlertModal();
            if (reloadAfter) {
                if (reloadAfter==='incoming') loadIncomingRequests();
                else window.location.reload();
            }
        };
        document.getElementById('vpsAlertModal').classList.add('active');
        lockBodyScroll();
    }
    window.showVpsAlert = showVpsAlert;

    function closeVpsAlertModal(){ document.getElementById('vpsAlertModal').classList.remove('active'); unlockBodyScroll(); }
    window.closeVpsAlertModal = closeVpsAlertModal;

    function openVpsConfirm(opts){
        pendingConfirmAction = opts.type||null;
        pendingConfirmId = opts.id||null;
        pendingConfirmName = opts.name||'';
        document.getElementById('vpsConfirmModalTitle').textContent = opts.title||'Confirm';
        document.getElementById('vpsConfirmTargetLabel').textContent = opts.label||'Target';
        document.getElementById('vpsConfirmFollowerName').textContent = opts.name||'—';
        document.getElementById('vpsConfirmMessage').textContent = opts.message||'';
        document.getElementById('vpsConfirmRemoveBtn').textContent = opts.confirmText||'Yes, Confirm';
        document.getElementById('vpsConfirmTargetRow').style.display = opts.name?'':'none';
        document.getElementById('vpsConfirmModal').classList.add('active');
        lockBodyScroll();
    }
    window.openVpsConfirm = openVpsConfirm;

    function closeVpsConfirmModal(){
        document.getElementById('vpsConfirmModal').classList.remove('active');
        unlockBodyScroll();
        pendingConfirmAction=null;pendingConfirmId=null;pendingConfirmName='';
    }
    window.closeVpsConfirmModal = closeVpsConfirmModal;

    function confirmModalAction() {
        if (!pendingConfirmAction || !pendingConfirmId) { closeVpsConfirmModal(); return; }
        var btn = document.getElementById('vpsConfirmRemoveBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        if (pendingConfirmAction === 'remove_follower') {
            fetch('programme_vps.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body:'remove_follower=1&follower_row_id=' + encodeURIComponent(pendingConfirmId)
            })
            .then(function(r){return r.json();})
            .then(function(data){
                btn.disabled=false; btn.textContent=originalText;
                if (data.success) { closeVpsConfirmModal(); showVpsAlert(data.message||'Follower removed.','Success'); loadMyFollowers(); }
                else showVpsAlert(data.message||'Failed.','Error');
            })
            .catch(function(){ btn.disabled=false; btn.textContent=originalText; showVpsAlert('Network error.','Error'); });
        } else if (pendingConfirmAction === 'delete_request') {
            fetch('programme_vps.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body:'delete_request=1&request_id=' + encodeURIComponent(pendingConfirmId)
            })
            .then(function(r){return r.json();})
            .then(function(data){
                btn.disabled=false; btn.textContent=originalText;
                if (data.success) { closeVpsConfirmModal(); showVpsAlert(data.message||'Deleted.','Success'); loadSentRequests(); }
                else showVpsAlert(data.message||'Failed.','Error');
            })
            .catch(function(){ btn.disabled=false; btn.textContent=originalText; showVpsAlert('Network error.','Error'); });
        } else {
            btn.disabled=false; btn.textContent=originalText; closeVpsConfirmModal();
        }
    }
    window.confirmModalAction = confirmModalAction;

    function linkSubAccountVps() {
        var btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Linking...';

        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'link_subaccount_vps=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (data.success) {
                showVpsAlert(data.message || 'VPS linked successfully!', 'Success');
                setTimeout(function () {
                    try {
                        if (window.parent && window.parent !== window) {
                            window.parent.postMessage({ type: 'reloadIframe' }, '*');
                        }
                    } catch (e) {}
                    window.location.reload();
                }, 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-link"></i> Link';
                showVpsAlert(data.message || 'Failed to link.', 'Error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-link"></i> Link';
            showVpsAlert('Network error.', 'Error');
        });
    }
    window.linkSubAccountVps = linkSubAccountVps;

    function loadSentRequests(){
        var c = document.getElementById('sentRequestsContainer');
        if (!c) return;
        c.innerHTML = '<div class="vps-requests-loading">Loading sent requests...</div>';
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_sent_requests=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (!data.success || !data.requests.length) {
                c.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>This programme has not sent any requests yet</p></div>';
                return;
            }
            renderRequestList(c, data.requests, 'sent');
        })
        .catch(function(){ c.innerHTML = '<div class="vps-empty-state"><p>Failed to load sent requests</p></div>'; });
    }
    window.loadSentRequests = loadSentRequests;

    function loadMySpace(){
        var c = document.getElementById('mySpaceContainer');
        if (!c) return;
        c.innerHTML = '<div class="vps-requests-loading">Loading your space...</div>';
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_my_follower_owner=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (!data || !data.success || !data.is_follower) {
                c.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>You are not a follower of any VPS</p></div>';
                return;
            }
            renderMySpace(c, data.owner, data.self);
        })
        .catch(function(){ c.innerHTML = '<div class="vps-empty-state"><p>Failed to load your space</p></div>'; });
    }
    window.loadMySpace = loadMySpace;

    function renderMySpace(container, owner, self){
        var totalFollowers = parseInt(owner.total_followers, 10) || 0;
        var status = (self.host_status || 'active').toLowerCase();
        var myName = self.display_name || 'You';
        var html = '';
        html += '<div class="vps-owner-card"><div class="vps-owner-badge">VPS Owner</div><div class="vps-owner-name">' + escapeHtml(owner.fullname || 'Anonymous') + '</div><div class="vps-owner-meta">';
        html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Proxy</span><span class="vps-owner-stat-value">' + escapeHtml(owner.server_location || 'N/A') + '</span></div>';
        if (owner.subscription_duration) html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Subscription Duration</span><span class="vps-owner-stat-value">' + escapeHtml(String(owner.subscription_duration)) + ' days</span></div>';
        html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Total Followers</span><span class="vps-owner-stat-value">' + totalFollowers + '</span></div></div></div>';
        html += '<div class="vps-followers-section"><div class="vps-followers-title">You</div><div class="vps-hosts-list"><div class="vps-request-item vps-follower-self"><div class="vps-request-row"><div class="vps-request-label">Follower</div><div class="vps-request-value">' + escapeHtml(myName) + ' <span class="vps-self-badge">you</span></div></div><div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div></div></div></div>';
        container.innerHTML = html;
    }

    function loadIncomingRequests(){
        var c = document.getElementById('incomingRequestsContainer');
        if (!c) return;
        c.innerHTML = '<div class="vps-requests-loading">Loading incoming requests...</div>';
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_incoming_requests=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (!data.success) {
                c.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>' + escapeHtml(data.message || 'Failed') + '</p></div>';
                updateIncomingBadge(0);
                return;
            }
            updateIncomingBadge(data.count || (data.requests ? data.requests.length : 0));
            if (!data.requests || !data.requests.length) {
                c.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>No pending requests at the moment</p></div>';
                return;
            }
            renderRequestList(c, data.requests, 'incoming');
        })
        .catch(function(){ c.innerHTML = '<div class="vps-empty-state"><p>Failed to load incoming requests</p></div>'; });
    }
    window.loadIncomingRequests = loadIncomingRequests;

    function loadMyFollowers(){
        var c = document.getElementById('followersContainer');
        if (!c) return;
        c.innerHTML = '<div class="vps-requests-loading">Loading followers...</div>';
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_my_followers=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (!data.success || !data.followers.length) {
                c.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>This programme has no followers yet</p></div>';
                return;
            }
            var html = '<div class="vps-hosts-list">';
            data.followers.forEach(function(f){
                var name = f.follower_display_name || 'Unknown';
                var status = (f.host_status || 'active').toLowerCase();
                html += '<div class="vps-request-item"><div class="vps-request-row"><div class="vps-request-label">Follower</div><div class="vps-request-value">' + escapeHtml(name) + '</div></div><div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div><div class="vps-request-row"><div class="vps-request-label">Action</div><div class="vps-request-value"><button class="vps-btn-delete" data-follower-id="' + f.id + '" data-follower-name="' + escapeAttr(name) + '" onclick="removeFollowerClicked(this)">Remove</button></div></div></div>';
            });
            html += '</div>';
            c.innerHTML = html;
        })
        .catch(function(){ c.innerHTML = '<div class="vps-empty-state"><p>Failed to load followers</p></div>'; });
    }
    window.loadMyFollowers = loadMyFollowers;

    function renderRequestList(container, requests, mode){
        var html = '<div class="vps-hosts-list">';
        requests.forEach(function(req){
            var name = (mode === 'sent') ? (req.owner_display_name || 'Anonymous') : (req.requestor_display_name || 'Anonymous');
            var label = (mode === 'sent') ? 'Host' : 'Requestor';
            var location = (mode === 'sent' && req.owner_location) ? req.owner_location : '';
            var status = (req.request_status || 'pending').toLowerCase();

            html += '<div class="vps-request-item"><div class="vps-request-row"><div class="vps-request-label">' + label + '</div><div class="vps-request-value">' + escapeHtml(name) + '</div></div>';
            if (location) html += '<div class="vps-request-row"><div class="vps-request-label">Proxy</div><div class="vps-request-value">' + escapeHtml(location) + '</div></div>';
            html += '<div class="vps-request-row"><div class="vps-request-label">Status</div><div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div></div>';
            html += '<div class="vps-request-row"><div class="vps-request-label">Action</div><div class="vps-request-value">';

            if (mode === 'incoming') {
                if (status === 'pending') {
                    html += '<select class="vps-request-select" data-request-id="' + req.id + '" onchange="updateRequestStatus(this)"><option value="">-- Select --</option><option value="accept">Accept</option><option value="reject">Reject</option></select>';
                } else {
                    html += '<span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span>';
                }
            } else {
                html += '<button class="vps-btn-delete" data-request-id="' + req.id + '" data-request-name="' + escapeAttr(name) + '" onclick="deleteRequestClicked(this)">Delete Request</button>';
            }

            html += '</div></div></div>';
        });
        html += '</div>';
        container.innerHTML = html;
    }

    function removeFollowerClicked(btn){
        var rowId = btn.getAttribute('data-follower-id');
        var name = btn.getAttribute('data-follower-name') || '';
        if (!rowId) return;
        openVpsConfirm({
            type:'remove_follower',
            id:rowId,
            name:name,
            title:'Remove Follower',
            label:'Follower',
            message:'Are you sure you want to remove this follower from this programme VPS?',
            confirmText:'Yes, Remove'
        });
    }
    window.removeFollowerClicked = removeFollowerClicked;

    function deleteRequestClicked(btn){
        var reqId = btn.getAttribute('data-request-id');
        var name = btn.getAttribute('data-request-name') || '';
        if (!reqId) return;
        openVpsConfirm({
            type:'delete_request',
            id:reqId,
            name:name,
            title:'Delete Request',
            label:'Host',
            message:'Are you sure you want to delete this sent request?',
            confirmText:'Yes, Delete'
        });
    }
    window.deleteRequestClicked = deleteRequestClicked;

    var _updatingRequestIds = {};
    function updateRequestStatus(selectEl){
        var requestId = selectEl.getAttribute('data-request-id');
        var newStatus = selectEl.value;
        if (!requestId || !newStatus) return;
        if (_updatingRequestIds[requestId]) return;
        _updatingRequestIds[requestId] = true;
        selectEl.disabled = true;
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'update_request_status=1&request_id=' + encodeURIComponent(requestId) + '&new_status=' + encodeURIComponent(newStatus)
        })
        .then(function(r){return r.json();})
        .then(function(data){
            _updatingRequestIds[requestId] = false;
            if (data.success) {
                loadIncomingRequests();
                showVpsAlert(newStatus === 'accept' ? 'Request accepted.' : 'Request rejected.', 'Success');
            } else {
                showVpsAlert(data.message || 'Failed.', 'Error');
                selectEl.value = '';
                selectEl.disabled = false;
                loadIncomingRequests();
            }
        })
        .catch(function(){
            _updatingRequestIds[requestId] = false;
            showVpsAlert('Network error.', 'Error');
            selectEl.value = '';
            selectEl.disabled = false;
        });
    }
    window.updateRequestStatus = updateRequestStatus;

    function lockBodyScroll(){
        if(document.body.dataset.vpsModalScrollLocked === '1') return;
        var y = window.scrollY || window.pageYOffset || 0;
        document.body.dataset.vpsModalScrollLocked = '1';
        document.body.dataset.vpsModalScrollY = y;
        document.body.classList.add('modal-open');
        document.body.style.overflow='hidden';
        document.body.style.position='fixed';
        document.body.style.width='100%';
        document.body.style.top='-' + y + 'px';
    }
    function unlockBodyScroll(){
        if(document.body.dataset.vpsModalScrollLocked !== '1') return;
        var y = parseInt(document.body.dataset.vpsModalScrollY || '0', 10) || 0;
        document.body.dataset.vpsModalScrollLocked = '0';
        document.body.classList.remove('modal-open');
        document.body.style.overflow='';
        document.body.style.position='';
        document.body.style.width='';
        document.body.style.top='';
        window.scrollTo(0, y);
    }

    function showHostModal(ownerProgId) {
        var modal = document.getElementById('vpsHostModal');
        var body = document.getElementById('vpsHostModalBody');
        var actions = document.getElementById('vpsHostModalActions');

        body.innerHTML = '<div class="vps-modal-loading">Loading...</div>';
        actions.innerHTML = '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';
        modal.classList.add('active');
        lockBodyScroll();

        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_host_details=1&owner_programme_id=' + encodeURIComponent(ownerProgId)
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (data.success) {
                var h = data.host;
                body.innerHTML =
                    '<div class="vps-modal-row"><span class="vps-modal-label">Programme</span><span class="vps-modal-value">' + escapeHtml(h.fullname) + '</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Proxy</span><span class="vps-modal-value">' + escapeHtml(h.server_location || 'N/A') + '</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Subscription Duration</span><span class="vps-modal-value">' + escapeHtml(String(h.subscription_duration || 0)) + ' days</span></div>' +
                    '<div class="vps-modal-row"><span class="vps-modal-label">Current Hosts</span><span class="vps-modal-value">' + h.current_hosts + ' / ' + h.max_hosts + '</span></div>';

                var actionsHtml = '';
                if (h.request_locked) {
                    var reason = 'Request Space';
                    if (h.is_self) reason = 'Your VPS';
                    else if (h.is_follower_lock) reason = 'Already a Follower';
                    else if (h.is_full) reason = 'Full';
                    else if (h.already_requested) reason = 'Requested';
                    actionsHtml += '<button class="vps-modal-close requested" disabled>' + escapeHtml(reason) + '</button>';
                } else {
                    actionsHtml += '<button class="vps-btn-confirm" onclick="requestVpsSpace(' + h.owner_programme_id + ')">Request Space</button>';
                }
                actionsHtml += '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';
                actions.innerHTML = actionsHtml;
            } else {
                body.innerHTML = '<div class="vps-modal-error">' + escapeHtml(data.message || 'Unable.') + '</div>';
            }
        })
        .catch(function(){ body.innerHTML = '<div class="vps-modal-error">Network error.</div>'; });
    }
    window.showHostModal = showHostModal;

    function closeVpsHostModal(){
        document.getElementById('vpsHostModal').classList.remove('active');
        unlockBodyScroll();
    }
    window.closeVpsHostModal = closeVpsHostModal;

    function requestVpsSpace(ownerProgId) {
        if (isAlreadyFollowerProg) {
            showVpsAlert('Already a follower of another VPS.', 'Error');
            return;
        }
        var btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'request_vps_space=1&owner_programme_id=' + encodeURIComponent(ownerProgId)
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (data.success) {
                btn.textContent = 'Requested';
                btn.className = 'vps-modal-close requested';
                closeVpsHostModal();
                showVpsAlert(data.message || 'Request submitted!', 'Success');
            } else {
                btn.disabled = false;
                btn.textContent = 'Request Space';
                showVpsAlert(data.message || 'Unable.', 'Error');
            }
        })
        .catch(function(){
            btn.disabled = false;
            btn.textContent = 'Request Space';
            showVpsAlert('Network error.', 'Error');
        });
    }
    window.requestVpsSpace = requestVpsSpace;

    function escapeHtml(text){
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }
    function escapeAttr(text){ return escapeHtml(text).replace(/"/g, '&quot;'); }

    function refreshVisibleRequestLists(){
        var sentContent = document.getElementById('vpsTabSent');
        if (sentContent && sentContent.classList.contains('active')) loadSentRequests();

        var msContent = document.getElementById('vpsTabMySpace');
        if (msContent && msContent.classList.contains('active')) loadMySpace();

        if (userOwnsProgrammeVps) {
            var inc = document.getElementById('vpsTabIncoming');
            if (inc && inc.classList.contains('active')) loadIncomingRequests();
            var fol = document.getElementById('vpsTabFollowers');
            if (fol && fol.classList.contains('active')) loadMyFollowers();
        }
    }
    window.refreshVisibleRequestLists = refreshVisibleRequestLists;

    function refreshIncomingBadge(){
        if (!userOwnsProgrammeVps) return;
        fetch('programme_vps.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body:'get_incoming_requests=1'
        })
        .then(function(r){return r.json();})
        .then(function(data){
            if (data && data.success) updateIncomingBadge(data.count || (data.requests ? data.requests.length : 0));
        })
        .catch(function(){});
    }
    window.refreshIncomingBadge = refreshIncomingBadge;

    document.addEventListener('visibilitychange', function(){
        if (!document.hidden) { refreshVisibleRequestLists(); refreshIncomingBadge(); }
    });
    window.addEventListener('focus', function(){ refreshVisibleRequestLists(); refreshIncomingBadge(); });

    var _vpsRefreshTimer = null;
    function startVpsAutoRefresh(){
        if (_vpsRefreshTimer) clearInterval(_vpsRefreshTimer);
        _vpsRefreshTimer = setInterval(function(){
            if (!document.hidden) { refreshVisibleRequestLists(); refreshIncomingBadge(); }
        }, 8000);
    }
    startVpsAutoRefresh();
    window.addEventListener('beforeunload', function(){
        if (_vpsRefreshTimer) { clearInterval(_vpsRefreshTimer); _vpsRefreshTimer = null; }
    });
    if (userOwnsProgrammeVps) refreshIncomingBadge();
</script>
</body>
</html>