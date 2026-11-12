<?php
// vps.php — strictly sub-account scoped (INVESTOR SIDE ONLY)
// Fully decoupled from programme_vps. Talks only to:
//   vps, vps_hosts_requestors, vps_hosts_followers, harvhub
session_start();
try {
    require_once __DIR__ . '/mysqlconnection.php';
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Shared database connection manager did not return PDO.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ' database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
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
$pendingVpsPurchases = [];
$myVpsRegistration = null;
try {
    require_once __DIR__ . '/server_vps_schema.php';
    serverVpsEnsureSchema($pdo);
    require_once __DIR__ . '/vps_revocation_helpers.php';
    vpsRevocationEnsureSchema($pdo);
    vpsRevocationProcessExpired($pdo, 25);
    // Account VPS lifecycle is separate from programme VPS lifecycle.
    // Purchases stay pending until their server-side provisioning is complete.
    $pendingQuery = $pdo->prepare("SELECT r.id AS request_id,r.payment_status,r.request_status,r.amount,r.currency,r.created_at,
            COALESCE(NULLIF(rv.product_name,''),a.product_name) AS product_name,
            COALESCE(rv.provider_name,a.provider_name) AS provider_name,
            COALESCE(NULLIF(rv.plan_name,''),a.plan_name) AS plan_name,
            COALESCE(rv.plan_description,a.plan_description) AS plan_description,
            COALESCE(rv.cpu_type,a.cpu_type) AS cpu_type,COALESCE(rv.vcpu_count,a.vcpu_count,0) AS vcpu_count,
            COALESCE(rv.ram_gb,a.ram_gb,0) AS ram_gb,COALESCE(rv.storage_type,a.storage_type) AS storage_type,
            COALESCE(rv.storage_gb,a.storage_gb,0) AS storage_gb,
            COALESCE(rv.network_port_speed_mbps,a.network_port_speed_mbps) AS network_port_speed_mbps,
            COALESCE(rv.data_transfer,a.data_transfer) AS data_transfer,
            COALESCE(NULLIF(rv.server_location,''),a.server_location) AS server_location,
            COALESCE(rv.operating_system,a.operating_system) AS operating_system,
            COALESCE(rv.billing_period,a.billing_period) AS billing_period,
            COALESCE(rv.subscription_duration_days,a.subscription_duration_days) AS subscription_duration_days,
            rv.registration_status
        FROM server_vps_purchase_requests r
        LEFT JOIN server_available_vps a ON a.id=r.vps_id
        LEFT JOIN server_registered_vps rv ON rv.purchase_request_id=r.id
        WHERE r.request_type='account' AND r.user_id=? AND r.sub_account_id=? AND r.programme_id IS NULL
          AND r.payment_status IN ('awaiting_payment','payment-made','payment-confirmed')
          AND COALESCE(r.request_status,'') <> 'revoked_expired'
          AND NOT (r.payment_status='payment-confirmed' AND EXISTS (
              SELECT 1 FROM vps owned WHERE owned.user_id=r.user_id AND owned.sub_account_id=r.sub_account_id
          ))
        ORDER BY r.id DESC LIMIT 50");
    $pendingQuery->execute([$userId,$activeSubAccountId]);
    $pendingVpsPurchases = $pendingQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $registeredError) {
    error_log('Investor VPS purchase/provisioning status could not be loaded: '.$registeredError->getMessage());
}
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
// Account VPS allocations are independent; programme VPS records are never imported here.
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
        WHERE v.visibility = 1
          AND NOT (v.user_id = ? AND v.sub_account_id = ?)
        ORDER BY h.username ASC
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
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
$myVpsId           = 0;
$myVpsVisibility   = 0;
$myVpsRegistration = null;
try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM vps
        WHERE user_id = ?
          AND sub_account_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
    $ownedVpsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($ownedVpsRow) {
        $userOwnsVps = true;
        $myVpsId = (int)($ownedVpsRow['id'] ?? 0);
        $myVpsVisibility = (int)($ownedVpsRow['visibility'] ?? 0) === 1 ? 1 : 0;
        $registrationQuery = $pdo->prepare("SELECT id,product_name,provider_name,plan_name,vcpu_count,ram_gb,storage_type,storage_gb,server_location,operating_system,subscription_duration_days,currency,price,registration_status FROM server_registered_vps
            WHERE request_type='account' AND (account_vps_id=? OR (user_id=? AND sub_account_id=?))
            ORDER BY CASE WHEN account_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1");
        $registrationQuery->execute([$myVpsId,$userId,$activeSubAccountId,$myVpsId]);
        $myVpsRegistration = $registrationQuery->fetch(PDO::FETCH_ASSOC) ?: null;
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
$vpsLifecycleCsrf = $_SESSION['vps_lifecycle_csrf'] ??= bin2hex(random_bytes(32));
$pendingVpsRevocations = [];
$pendingOwnVpsRevocation = null;
try {
    $pendingVpsRevocations = vpsRevocationLoadPending($pdo, 'account', $userId, $activeSubAccountId, 0);
    foreach ($pendingVpsRevocations as $revocationRow) {
        if ((int)$revocationRow['vps_id'] === $myVpsId) { $pendingOwnVpsRevocation = $revocationRow; break; }
    }
} catch (Throwable $e) {
    error_log('[investor_vps] pending revocation list failed: ' . $e->getMessage());
}

// Cancel only an account purchase for which payment has not yet been submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_vps_purchase'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)$vpsLifecycleCsrf, $sent)) {
        http_response_code(419); echo json_encode(['success'=>false,'message'=>'The request expired. Refresh and try again.']); exit;
    }
    $requestId = (int)($_POST['request_id'] ?? 0);
    try {
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT id,vps_id,payment_status FROM server_vps_purchase_requests
            WHERE id=? AND request_type='account' AND user_id=? AND sub_account_id=? AND programme_id IS NULL FOR UPDATE");
        $q->execute([$requestId,$userId,$activeSubAccountId]);
        $requestRow = $q->fetch(PDO::FETCH_ASSOC);
        if (!$requestRow) throw new RuntimeException('Purchase request not found.');
        if ((string)$requestRow['payment_status'] !== 'awaiting_payment') {
            throw new RuntimeException('This purchase can no longer be cancelled because payment has already been submitted.');
        }
        $pdo->prepare("DELETE FROM server_vps_purchase_requests WHERE id=? AND request_type='account' AND user_id=? AND sub_account_id=? AND programme_id IS NULL AND payment_status='awaiting_payment'")->execute([$requestId,$userId,$activeSubAccountId]);
        $planId = (int)$requestRow['vps_id'];
        $pdo->prepare("UPDATE server_available_vps SET buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests r WHERE r.vps_id=? AND r.buy_request=1),1,0) WHERE id=?")->execute([$planId,$planId]);
        $pdo->commit();
        echo json_encode(['success'=>true,'message'=>'Purchase request cancelled.']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// Broker guard for unfollowing and revoking an account VPS.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_vps_lifecycle_broker'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)$vpsLifecycleCsrf, $sent)) { http_response_code(419); echo json_encode(['success'=>false,'message'=>'The request expired.']); exit; }
    $action = (string)($_POST['lifecycle_action'] ?? '');
    if ($action === 'revoke') {
        $opsId = (int)($_POST['vps_id'] ?? 0);
        if (!$userOwnsVps || $opsId !== $myVpsId || $pendingOwnVpsRevocation) { echo json_encode(['success'=>false,'message'=>'This VPS is not available for revocation.']); exit; }
    } elseif ($action === 'unfollow') {
        $followerRowId = (int)($_POST['follower_row_id'] ?? 0);
        $q = $pdo->prepare("SELECT id FROM vps_hosts_followers WHERE id=? AND follower_id=? AND follower_sub_account_id=? AND host_status='active' LIMIT 1");
        $q->execute([$followerRowId,$userId,$activeSubAccountId]);
        if (!$q->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'The VPS follow record could not be found.']); exit; }
    } else { echo json_encode(['success'=>false,'message'=>'Invalid action.']); exit; }
    $linked = vpsRevocationBrokerLinked($pdo,'account',$userId,$activeSubAccountId,0);
    echo json_encode(['success'=>true,'broker_linked'=>$linked,'message'=>$linked?'Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.':'']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unfollow_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)$vpsLifecycleCsrf, $sent)) { http_response_code(419); echo json_encode(['success'=>false,'message'=>'The request expired.']); exit; }
    $followerRowId = (int)($_POST['follower_row_id'] ?? 0);
    try {
        if (vpsRevocationBrokerLinked($pdo,'account',$userId,$activeSubAccountId,0)) {
            echo json_encode(['success'=>false,'code'=>'broker_linked','message'=>'Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.']); exit;
        }
        $q = $pdo->prepare("DELETE FROM vps_hosts_followers WHERE id=? AND follower_id=? AND follower_sub_account_id=? AND host_status='active'");
        $q->execute([$followerRowId,$userId,$activeSubAccountId]);
        if ($q->rowCount() !== 1) throw new RuntimeException('You are no longer following this VPS.');
        $pdo->prepare('DELETE FROM vps_hosts_requestors WHERE requestor_id=? AND requestor_sub_account_id=?')->execute([$userId,$activeSubAccountId]);
        echo json_encode(['success'=>true,'message'=>'You have unfollowed this VPS.']);
    } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_own_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)$vpsLifecycleCsrf, $sent)) { http_response_code(419); echo json_encode(['success'=>false,'message'=>'The request expired.']); exit; }
    try {
        if (!$userOwnsVps || (int)($_POST['vps_id'] ?? 0) !== $myVpsId || $pendingOwnVpsRevocation) throw new RuntimeException('This VPS is not available for revocation.');
        $result = vpsRevocationStart($pdo,'account',$userId,$activeSubAccountId,0,$myVpsId);
        if (!empty($result['blocked'])) { echo json_encode(['success'=>false,'code'=>'broker_linked','message'=>$result['message']]); exit; }
        echo json_encode(['success'=>true,'message'=>'Your VPS is scheduled for discontinuation in two days.','revocation_id'=>$result['id']]);
    } catch (Throwable $e) { error_log('[investor_vps] revoke failed: '.$e->getMessage()); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_own_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent = (string)($_POST['csrf_token'] ?? '');
    if ($sent === '' || !hash_equals((string)$vpsLifecycleCsrf, $sent)) { http_response_code(419); echo json_encode(['success'=>false,'message'=>'The request expired.']); exit; }
    try {
        vpsRevocationRestore($pdo,'account',$userId,$activeSubAccountId,0,(int)($_POST['revocation_id'] ?? 0));
        echo json_encode(['success'=>true,'message'=>'Your VPS has been restored.']);
    } catch (Throwable $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}
// ==================== UPDATE OWN VPS VISIBILITY (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_vps_visibility'])) {
    header('Content-Type: application/json; charset=utf-8');
    $visibilityRaw = strtolower(trim((string)($_POST['visibility'] ?? '')));
    if ($visibilityRaw === 'public') $visibilityRaw = '1';
    if ($visibilityRaw === 'private') $visibilityRaw = '0';
    if (!in_array($visibilityRaw, ['0', '1'], true)) {
        echo json_encode(['success' => false, 'message' => 'Choose either Public or Private visibility.']);
        exit;
    }
    if (!$userOwnsVps || $myVpsId <= 0 || $pendingOwnVpsRevocation) {
        echo json_encode(['success' => false, 'message' => 'Only the VPS owner can change visibility.']);
        exit;
    }
    $visibility = (int)$visibilityRaw;
    $visibilityLabel = $visibility === 1 ? 'Public' : 'Private';
    try {
        $upd = $pdo->prepare("
            UPDATE vps
            SET visibility = ?
            WHERE id = ?
              AND user_id = ?
              AND sub_account_id = ?
            LIMIT 1
        ");
        $upd->execute([$visibility, $myVpsId, $userId, $activeSubAccountId]);
        if ($upd->rowCount() < 1 && $myVpsVisibility !== $visibility) {
            throw new RuntimeException('VPS visibility was not updated.');
        }
        $myVpsVisibility = $visibility;
        echo json_encode([
            'success' => true,
            'visibility' => $visibility,
            'visibility_label' => $visibilityLabel,
            'message' => $visibility === 1
                ? 'Your VPS is now public and visible to other users.'
                : 'Your VPS is now private and hidden from public listings.'
        ]);
    } catch (Throwable $e) {
        error_log('[investor_vps] visibility update failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to update VPS visibility. Please try again.']);
    }
    exit;
}
// Account and programme VPS purchases, ownership and followers remain separate.
// ==================== GET SUB ACCOUNT NAME FOR DISPLAY ====================
$activeSubAccountName = resolveSubAccountName($user);
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
    $stmt = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? AND sub_account_id = ? AND visibility = 1 LIMIT 1");
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
              AND v.visibility = 1
              AND NOT (v.user_id = ? AND v.sub_account_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$ownerId, $ownerSubId, $userId, $activeSubAccountId]);
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
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stmt = $pdo->prepare("SELECT f.id AS follower_row_id,f.host_status,f.owner_id AS owner_id_resolved,
                    f.sub_account_id AS owner_sub_id,h.username,h.fullname,h.first_name,h.last_name,h.sub_account_name,
                    v.server_location,v.subscription_duration,rv.plan_name,rv.product_name
            FROM vps_hosts_followers f
            LEFT JOIN harvhub h ON h.id=f.owner_id AND h.sub_account_id=f.sub_account_id
            LEFT JOIN vps v ON v.user_id=f.owner_id AND v.sub_account_id=f.sub_account_id
            LEFT JOIN server_registered_vps rv ON rv.account_vps_id=v.id AND rv.request_type='account'
            WHERE f.follower_id=? AND f.follower_sub_account_id=? AND f.host_status='active'
            ORDER BY f.created_at DESC LIMIT 1");
        $stmt->execute([$userId,$activeSubAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { error_log('[investor_vps] follower owner lookup: '.$e->getMessage()); $row = null; }
    if (!$row) { echo json_encode(['success'=>true,'is_follower'=>false]); exit; }
    $ownerUsername = trim((string)($row['username'] ?? ''));
    if ($ownerUsername === '') $ownerUsername = trim((string)($row['fullname'] ?? '')) ?: resolveHostDisplayName($row);
    echo json_encode(['success'=>true,'is_follower'=>true,
        'owner'=>[
            'owner_id'=>(int)$row['owner_id_resolved'],
            'username'=>$ownerUsername,
            'vps_name'=>trim((string)($row['plan_name'] ?? '')) ?: (trim((string)($row['product_name'] ?? '')) ?: 'VPS'),
            'server_location'=>$row['server_location'] ?? '',
            'subscription_duration'=>$row['subscription_duration'] ?? 0,
            'total_followers'=>countActiveFollowers($pdo,(int)$row['owner_id_resolved'],(int)$row['owner_sub_id'])
        ],
        'self'=>['follower_row_id'=>(int)$row['follower_row_id'],'host_status'=>$row['host_status']]
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
html,body,*{scrollbar-width:none!important;-ms-overflow-style:none!important}html::-webkit-scrollbar,body::-webkit-scrollbar,*::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}

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
// Public VPS catalogue: only published, in-stock product specifications are selected.
$catalogueCsrf = $_SESSION['vps_purchase_csrf'] ??= bin2hex(random_bytes(32));
$lifecycleCsrfForJs = $vpsLifecycleCsrf;
$availableVpsPlans = [];
try {
    $catalogueQ = $pdo->query("SELECT id, product_name, provider_name, plan_name, plan_description,
        cpu_type, vcpu_count, ram_gb, storage_type, storage_gb, network_port_speed_mbps,
        data_transfer, server_location, operating_system, billing_period,
        subscription_duration_days, price, currency, setup_fee, stock_quantity, allocated_quantity
        FROM server_available_vps
        WHERE status = 'published' AND allocated_quantity < stock_quantity
        ORDER BY price ASC, id DESC");
    $availableVpsPlans = $catalogueQ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $catalogueError) {
    // The marketplace SQL migration may not have been applied yet.
    error_log('Investor VPS catalogue unavailable: ' . $catalogueError->getMessage());
}
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
<style>
    .vps-host-actions.vps-owner-actions {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        width: 100% !important;
        gap: 12px;
    }
    .vps-owner-actions .vps-owner-status {
        margin-right: auto;
        font-weight: 700;
        color: var(--text, #1a2332);
    }
    .vps-visibility-select {
        margin-left: auto;
        min-width: 112px;
        padding: 8px 30px 8px 11px;
        border: 1px solid var(--border, rgba(127,127,127,.35));
        border-radius: 9px;
        background: var(--bg-card, #fff);
        color: var(--text, #1a2332);
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        color-scheme: light;
        cursor: pointer;
    }
    .vps-visibility-select option {
        background: #fff;
        color: #1a2332;
    }
    body.dark-mode .vps-visibility-select {
        background: #111a17;
        color: #e6edf3;
        border-color: rgba(255,255,255,.18);
        color-scheme: dark;
    }
    body.dark-mode .vps-visibility-select option {
        background: #111a17;
        color: #e6edf3;
    }
    .vps-visibility-select:disabled { opacity: .65; cursor: wait; }
    .vps-topbar-back {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
        min-height: 40px !important;
        color: var(--text, #1a2332) !important;
        text-decoration: none !important;
        line-height: 1 !important;
        opacity: 1 !important;
        visibility: visible !important;
        z-index: 20 !important;
    }
    .vps-topbar-back .vps-back-icon {
        display: block !important;
        width: 22px !important;
        height: 22px !important;
        min-width: 22px !important;
        min-height: 22px !important;
        overflow: visible !important;
    }
    body.dark-mode .vps-topbar-back { color: #e6edf3 !important; }
</style>

<style>
.market-vps-intro{margin:5px 0 16px}.market-vps-intro h3{margin:0 0 4px;font-size:18px;color:var(--text,#1a2332)}.market-vps-intro p{margin:0;color:var(--text-muted,#8291a3);font-size:13px}.market-vps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.market-vps-card{background:var(--card-bg,var(--bg-card,#fff));color:var(--text,#1a2332);border:1px solid var(--border-color,#e4eaf0);border-radius:14px;padding:16px;box-shadow:var(--shadow,0 4px 16px rgba(0,0,0,.04))}.market-vps-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.market-vps-provider{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted,#8090a0)}.market-vps-card h3{font-size:17px;margin:4px 0 0;color:var(--text,#1a2332)}.market-vps-card-head p{margin:2px 0;color:var(--text-muted,#8090a0);font-size:12px}.market-vps-price{text-align:right;white-space:nowrap}.market-vps-price strong{display:block;font-size:17px;color:var(--accent-color,var(--accent,#159a6d))}.market-vps-price span{display:block;font-size:11px;color:var(--text-muted,#8090a0)}.market-vps-description{font-size:12px;color:var(--text-secondary,#7d8a99);margin:12px 0}.market-vps-specs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:14px 0}.market-vps-specs>div{background:var(--bg-secondary,var(--input-bg,#f7f9fc));color:var(--text,#1a2332);padding:9px;border-radius:8px;min-width:0}.market-vps-specs b{display:block;font-size:12px;overflow-wrap:anywhere}.market-vps-specs span{font-size:10px;color:var(--text-muted,#8793a2)}.market-vps-card-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid var(--border-color,#e8edf2);padding-top:12px;font-size:12px;color:var(--text-muted,#8793a2)}.market-vps-card-foot button{margin:0}.market-vps-card-foot button:disabled{opacity:.55;cursor:not-allowed}
</style>

</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> vps-page-body">
    <div class="vps-page-wrapper">
        <div class="vps-sticky-top" id="vpsStickyTop">
            <div class="vps-topbar">
                <a href="#"
                   class="vps-topbar-back"
                   onclick="event.preventDefault(); harvhubGoBackFromVps(); return false;"
                   aria-label="Back" title="Back">
                    <svg class="vps-back-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M19 12H5M12 19l-7-7 7-7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </a>
                <h1 class="vps-topbar-title">VPS Hub</h1>
            </div>
            <?php if (!$isAlreadyFollower): ?>
            <div class="vps-search-wrap">
                <input type="text"
                       id="vpsSearchInput"
                       class="vps-search-input"
                       placeholder="Search by profile link, username or email…"
                       autocomplete="off"
                       oninput="onVpsSearch(this.value)"
                       onfocus="onVpsSearch(this.value)">
            </div>
            <?php endif; ?>
            <div class="vps-tabs" id="vpsTabs">
                <button class="vps-tab active" data-tab="purchase" onclick="switchVpsTab('purchase')">
                    Purchase VPS
                </button>
                <?php if (!empty($pendingVpsPurchases)): ?>
                    <button class="vps-tab" data-tab="pending" onclick="switchVpsTab('pending')">Purchase Pending <span class="vps-tab-badge"><?= count($pendingVpsPurchases) ?></span></button>
                <?php endif; ?>
                <button class="vps-tab" data-tab="myvps" onclick="switchVpsTab('myvps')">My VPS</button>
                <?php if (!empty($pendingVpsRevocations)): ?>
                    <button class="vps-tab" data-tab="revoked" onclick="switchVpsTab('revoked')">Pending Revoked <span class="vps-tab-badge"><?= count($pendingVpsRevocations) ?></span></button>
                <?php endif; ?>
                <?php if (!$isAlreadyFollower): ?>
                    <button class="vps-tab" data-tab="users" onclick="switchVpsTab('users')">VPS Owners</button>
                <?php endif; ?>
                <?php if ($userOwnsVps && !$pendingOwnVpsRevocation): ?>
                <button class="vps-tab" data-tab="incoming" onclick="switchVpsTab('incoming')">
                    Incoming Requests
                    <span class="vps-tab-badge" id="incomingTabBadge" style="display:none;">0</span>
                </button>
                <?php endif; ?>
                <?php if (!$isAlreadyFollower): ?>
                    <button class="vps-tab" data-tab="sent" onclick="switchVpsTab('sent')">Sent Requests</button>
                <?php endif; ?>
                <?php if ($userOwnsVps && !$pendingOwnVpsRevocation): ?>
                <button class="vps-tab" data-tab="followers" onclick="switchVpsTab('followers')">
                    My Followers
                </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="vps-scroll-area" id="vpsScrollArea">
            <div class="vps-tab-content active" id="vpsTabPurchase">
                <div class="market-vps-intro"><h3>Available VPS Plans</h3></div>
                <?php if (empty($availableVpsPlans)): ?>
                    <div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>No published VPS plans are currently available.</p></div>
                <?php else: ?>
                    <div class="market-vps-grid">
                    <?php foreach ($availableVpsPlans as $plan): ?>
                        <article class="market-vps-card">
                            <div class="market-vps-card-head"><div><span class="market-vps-provider"><?= htmlspecialchars($plan['provider_name'] ?: 'HarvHub VPS', ENT_QUOTES, 'UTF-8') ?></span><h3><?= htmlspecialchars($plan['product_name'], ENT_QUOTES, 'UTF-8') ?></h3><p><?= htmlspecialchars($plan['plan_name'], ENT_QUOTES, 'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars($plan['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$plan['price'], 2) ?></strong><span>per <?= htmlspecialchars($plan['billing_period'], ENT_QUOTES, 'UTF-8') ?></span><?php if ((float)$plan['setup_fee'] > 0): ?><span>+ <?= htmlspecialchars($plan['currency'], ENT_QUOTES, 'UTF-8') ?> <?= number_format((float)$plan['setup_fee'], 2) ?> setup fee</span><?php endif; ?></div></div>
                            <?php if (!empty($plan['plan_description'])): ?><p class="market-vps-description"><?= nl2br(htmlspecialchars($plan['plan_description'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                            <div class="market-vps-specs"><div><b><?= (int)$plan['vcpu_count'] ?> vCPU</b><span><?= htmlspecialchars($plan['cpu_type'] ?: 'Processor', ENT_QUOTES, 'UTF-8') ?></span></div><div><b><?= htmlspecialchars($plan['ram_gb'], ENT_QUOTES, 'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars($plan['storage_gb'], ENT_QUOTES, 'UTF-8') ?> GB</b><span><?= htmlspecialchars($plan['storage_type'], ENT_QUOTES, 'UTF-8') ?> storage</span></div><div><b><?= htmlspecialchars($plan['server_location'], ENT_QUOTES, 'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars($plan['network_port_speed_mbps'] ? $plan['network_port_speed_mbps'].' Mbps' : 'Not specified', ENT_QUOTES, 'UTF-8') ?></b><span>Network port</span></div><div><b><?= htmlspecialchars($plan['data_transfer'] ?: 'Not specified', ENT_QUOTES, 'UTF-8') ?></b><span>Transfer</span></div><div><b><?= htmlspecialchars($plan['operating_system'] ?: 'Choose at provisioning', ENT_QUOTES, 'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)$plan['subscription_duration_days'] ?> days</b><span>Subscription term</span></div></div>
                            <div class="market-vps-card-foot"><span><?= max(0,(int)$plan['stock_quantity']-(int)$plan['allocated_quantity']) ?> available</span><button type="button" class="vps-btn-link" onclick="startVpsPurchase(<?= (int)$plan['id'] ?>, this)" <?= (!empty($userOwnsVps) || !empty($pendingVpsPurchases)) ? 'disabled title="This subaccount already has a VPS or pending purchase"' : '' ?>>Buy now</button></div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
<?php if (!empty($pendingVpsPurchases)): ?>
            <div class="vps-tab-content" id="vpsTabPending">
                <div class="market-vps-intro"><h3>Purchase Pending</h3></div>
                <?php if (empty($pendingVpsPurchases)): ?>
                    <div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>You have no pending VPS purchases.</p></div>
                <?php else: ?>
                    <div class="market-vps-grid">
                    <?php foreach ($pendingVpsPurchases as $purchase):
                        $purchaseStatus = (string)($purchase['payment_status'] ?? '');
                        if ($purchaseStatus === 'payment-confirmed') { $statusLabel = 'Payment confirmed · Setup pending'; $actionLabel = 'View purchase'; }
                        elseif ($purchaseStatus === 'payment-made') { $statusLabel = 'Payment submitted · Awaiting verification'; $actionLabel = 'View payment'; }
                        else { $statusLabel = 'Awaiting payment'; $actionLabel = 'Continue payment'; }
                        $checkoutUrl = 'purchaseaccountvps.php?request_id='.(int)$purchase['request_id'];
                    ?>
                        <article class="market-vps-card">
                            <div class="market-vps-card-head"><div><span class="market-vps-provider"><?= htmlspecialchars((string)($purchase['provider_name'] ?: 'HarvHub VPS'),ENT_QUOTES,'UTF-8') ?></span><h3><?= htmlspecialchars((string)($purchase['product_name'] ?: 'VPS Plan'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($purchase['plan_name'] ?: ''),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($purchase['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)$purchase['amount'],2) ?></strong><span>Purchase #<?= (int)$purchase['request_id'] ?></span></div></div>
                            <p class="market-vps-description"><strong><?= htmlspecialchars($statusLabel,ENT_QUOTES,'UTF-8') ?></strong></p>
                            <div class="market-vps-specs"><div><b><?= (int)($purchase['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($purchase['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($purchase['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($purchase['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($purchase['server_location']??''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($purchase['operating_system']?:'Choose at provisioning'),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($purchase['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div></div>
                            <?php if ($purchaseStatus !== 'payment-confirmed'): ?><a class="vps-btn-link" href="<?= htmlspecialchars($checkoutUrl,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($actionLabel,ENT_QUOTES,'UTF-8') ?></a><?php endif; ?>
                            <?php if ($purchaseStatus === 'awaiting_payment'): ?><button type="button" class="vps-btn-request" onclick="confirmCancelVpsPurchase(<?= (int)$purchase['request_id'] ?>)">Cancel purchase</button><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
<?php endif; ?>
            <div class="vps-tab-content" id="vpsTabMyVps">
                <div class="market-vps-intro"><h3>My VPS</h3></div>
                <?php if ($userOwnsVps && !$pendingOwnVpsRevocation && $myVpsRegistration): ?>
                    <article class="market-vps-card" style="max-width:850px">
                        <div class="market-vps-card-head"><div><span class="market-vps-provider">My VPS · Account allocation</span><h3><?= htmlspecialchars((string)($myVpsRegistration['product_name'] ?: 'Existing VPS'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($myVpsRegistration['plan_name'] ?: 'Existing allocation'),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($myVpsRegistration['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)$myVpsRegistration['price'],2) ?></strong><span>VPS #<?= (int)$myVpsId ?></span></div></div>
                        <?php if (($myVpsRegistration['registration_status'] ?? '') === 'registered'): ?><p class="market-vps-description"><strong style="color:#18794e">Ready</strong></p><?php else: ?><p class="market-vps-description"><strong style="color:#b7791f">Setup pending</strong></p><?php endif; ?>
                        <div class="market-vps-specs"><div><b><?= (int)($myVpsRegistration['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($myVpsRegistration['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($myVpsRegistration['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($myVpsRegistration['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($myVpsRegistration['server_location']??''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($myVpsRegistration['operating_system']??''),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($myVpsRegistration['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div></div>
                        <div class="market-vps-card-foot"><span>Visibility</span><select class="vps-visibility-select" aria-label="My VPS visibility" data-current-visibility="<?= $myVpsVisibility ?>" onchange="updateOwnVpsVisibility(this)"><option value="1" <?= $myVpsVisibility===1?'selected':'' ?>>Public</option><option value="0" <?= $myVpsVisibility===0?'selected':'' ?>>Private</option></select>
                            <select class="vps-visibility-select" aria-label="VPS options" onchange="handleOwnerVpsOption(this, <?= (int)$myVpsId ?>)"><option value="">Options</option><option value="revoke">Revoke VPS</option></select>
                        </div>
                    </article>
                <?php elseif ($userOwnsVps && !$pendingOwnVpsRevocation): ?>
                    <div class="vps-empty-state"><p>Setup pending.</p><select class="vps-visibility-select" aria-label="VPS options" onchange="handleOwnerVpsOption(this, <?= (int)$myVpsId ?>)"><option value="">Options</option><option value="revoke">Revoke VPS</option></select></div>
                <?php elseif ($pendingOwnVpsRevocation): ?>
                    <div class="vps-empty-state"><p>Your VPS is listed under Pending Revoked.</p></div>
                <?php endif; ?>
                <div id="myFollowerVpsContainer" class="vps-requests-list"></div>
                <?php if (!$userOwnsVps && !$isAlreadyFollower): ?><div class="vps-empty-state" id="noMyVpsNotice"><span class="vps-empty-icon">—</span><p>No VPS is currently assigned to this account.</p></div><?php endif; ?>
            </div>
<?php if (!empty($pendingVpsRevocations)): ?>
            <div class="vps-tab-content" id="vpsTabRevoked">
                <div class="market-vps-intro"><h3>Pending Revoked</h3></div>
                <?php foreach ($pendingVpsRevocations as $rev): ?>
                    <article class="market-vps-card" style="max-width:850px">
                        <div class="market-vps-card-head"><div><span class="market-vps-provider">Pending Revoked</span><h3><?= htmlspecialchars((string)($rev['product_name'] ?: 'Existing VPS'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($rev['plan_name'] ?: 'VPS allocation'),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($rev['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)($rev['price'] ?? 0),2) ?></strong><span>VPS #<?= (int)$rev['vps_id'] ?></span></div></div>
                        <p class="market-vps-description"><strong>Your VPS will be discontinued in <span class="vps-revocation-countdown" data-discontinue-epoch="<?= (int)($rev['scheduled_discontinuation_epoch'] ?? 0) ?>">calculating…</span></strong></p>
                        <div class="market-vps-specs"><div><b><?= (int)($rev['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($rev['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($rev['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($rev['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($rev['snapshot_location'] ?: ''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($rev['operating_system']??''),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($rev['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div><div><b><?= htmlspecialchars((string)($rev['network_port_speed_mbps'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Network port Mbps</span></div><div><b><?= htmlspecialchars((string)($rev['data_transfer'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Data transfer</span></div><div><b><?= htmlspecialchars((string)($rev['backup_option'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Backup</span></div><div><b><?= (int)($rev['snapshot_count']??0) ?></b><span>Snapshots</span></div></div>
                        <div class="market-vps-card-foot"><span>Reconsider</span><select class="vps-visibility-select" aria-label="Reconsider VPS revocation" onchange="handleRestoreVpsOption(this, <?= (int)$rev['id'] ?>)"><option value="">Select option</option><option value="restore">Restore VPS</option></select></div>
                    </article>
                <?php endforeach; ?>
            </div>
<?php endif; ?>
            <div class="vps-tab-content" id="vpsTabUsers">
                <?php if ($isAlreadyFollower): ?>
                    <div class="vps-follower-lock" style="margin-bottom:16px;padding:12px 16px;background:rgba(243,156,18,0.1);border-radius:8px;font-size:13px;line-height:1.5;color:var(--text);">
                        <strong>This account is already a follower of a VPS host.</strong>
                        You cannot send requests to other hosts while this account is an active follower.
                    </div>
                <?php endif; ?>
                <?php if (empty($hosts)): ?>
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
                                <div class="vps-host-actions<?= $isSelf ? ' vps-owner-actions' : '' ?>">
                                    <?php if ($isSelf): ?>
                                        <span class="vps-owner-status">Your VPS</span>
                                        <select
                                            class="vps-visibility-select"
                                            aria-label="VPS visibility"
                                            data-current-visibility="<?= (int)($host['visibility'] ?? 0) === 1 ? '1' : '0' ?>"
                                            onchange="updateOwnVpsVisibility(this)"
                                        >
                                            <option value="1" <?= (int)($host['visibility'] ?? 0) === 1 ? 'selected' : '' ?>>Public</option>
                                            <option value="0" <?= (int)($host['visibility'] ?? 0) === 0 ? 'selected' : '' ?>>Private</option>
                                        </select>
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
            <?php if (!$isAlreadyFollower): ?>
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
    function harvhubGoBackFromVps() {
        var allowedTabs = ['mydashboard', 'trades', 'activity', 'analytics', 'menu', 'revenue_history', 'profit_split', 'programmes', 'disconnect_broker', 'connect_investor_broker'];
        var returnTab = '';
        try {
            if (window.parent && window.parent !== window) {
                var parentUrl = new URL(window.parent.location.href);
                returnTab = parentUrl.searchParams.get('vps_from') || '';
            }
        } catch (e) {}
        if (allowedTabs.indexOf(returnTab) === -1) {
            try {
                var childParams = new URLSearchParams(window.location.search);
                returnTab = childParams.get('vps_from') || sessionStorage.getItem('harvhub_investor_vps_return_tab') || '';
            } catch (e) {}
        }
        if (allowedTabs.indexOf(returnTab) === -1) returnTab = 'mydashboard';
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'investorVpsBack', returnTab: returnTab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=' + encodeURIComponent(returnTab);
    }
    window.harvhubGoBackFromVps = harvhubGoBackFromVps;
    function updateOwnVpsVisibility(select) {
        if (!select || select.dataset.saving === '1') return;
        var next = String(select.value);
        var previous = String(select.dataset.currentVisibility || select.value);
        if (next !== '0' && next !== '1') {
            select.value = previous;
            if (typeof showVpsAlert === 'function') showVpsAlert('Choose either Public or Private.', 'Choose Visibility');
            return;
        }
        if (next === previous) return;
        select.dataset.saving = '1';
        select.disabled = true;
        fetch('vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'set_vps_visibility=1&visibility=' + encodeURIComponent(next)
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Request failed');
            return response.json();
        })
        .then(function (data) {
            if (data && data.success) {
                select.dataset.currentVisibility = String(data.visibility);
                select.value = String(data.visibility);
                if (typeof showVpsAlert === 'function') showVpsAlert(data.message || 'VPS visibility updated.', 'Success');
            } else {
                select.value = previous;
                if (typeof showVpsAlert === 'function') showVpsAlert((data && data.message) || 'Unable to update VPS visibility.', 'Error');
            }
        })
        .catch(function () {
            select.value = previous;
            if (typeof showVpsAlert === 'function') showVpsAlert('Network error while updating VPS visibility.', 'Error');
        })
        .finally(function () {
            select.disabled = false;
            select.dataset.saving = '0';
        });
    }
    window.updateOwnVpsVisibility = updateOwnVpsVisibility;
    (function () {
        document.body.classList.add('page-vps');
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
            if (e.data.type === 'harvhubVpsNavigateTab' && typeof window.switchVpsTab === 'function') {
                window.switchVpsTab(String(e.data.tab || 'purchase'));
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
        } else if (tab === 'pending') {
            var pendingPanel = document.getElementById('vpsTabPending');
            if (pendingPanel) pendingPanel.classList.add('active'); else { switchVpsTab('myvps'); return; }
        } else if (tab === 'myvps') {
            document.getElementById('vpsTabMyVps').classList.add('active');
            loadFollowerVps();
        } else if (tab === 'revoked') {
            var revokedPanel = document.getElementById('vpsTabRevoked');
            if (revokedPanel) revokedPanel.classList.add('active'); else { switchVpsTab('myvps'); return; }
        } else if (tab === 'users') {
            if (isAlreadyFollower) { switchVpsTab('myvps'); return; }
            document.getElementById('vpsTabUsers').classList.add('active');
            var searchInput = document.getElementById('vpsSearchInput');
            if (searchInput) searchInput.value = '';
            onVpsSearch('');
        } else if (tab === 'sent') {
            var sentEl = document.getElementById('vpsTabSent');
            if (sentEl) { sentEl.classList.add('active'); loadSentRequests(); }
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
    var requestedVpsTab = new URLSearchParams(window.location.search).get('vps_tab') || new URLSearchParams(window.location.search).get('tab');
    if (requestedVpsTab === 'pending' && document.getElementById('vpsTabPending')) switchVpsTab('pending');
    else if (requestedVpsTab === 'revoked' && document.getElementById('vpsTabRevoked')) switchVpsTab('revoked');
    else if (requestedVpsTab === 'myvps') switchVpsTab('myvps');
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
    function openAccountVpsCheckout(destination) {
        try {
            var checkout = new URL(String(destination || ''), window.location.href);
            if (checkout.origin !== window.location.origin || checkout.pathname.split('/').pop() !== 'purchaseaccountvps.php') throw new Error('Invalid account checkout route.');
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({type:'harvhubOpenVpsCheckout',category:'account',url:checkout.href}, '*');
                return;
            }
            window.location.href = checkout.href;
        } catch (e) {
            if (typeof showVpsAlert === 'function') showVpsAlert('Unable to open account VPS checkout. Please try again.', 'Error');
        }
    }

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
        } else if (['unfollow_vps','revoke_vps','restore_vps','cancel_vps_purchase','begin_purchase'].indexOf(pendingConfirmAction) !== -1) {
            var action = pendingConfirmAction;
            var id = pendingConfirmId;
            var params = new URLSearchParams({csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
            if (action === 'unfollow_vps') { params.set('unfollow_vps','1'); params.set('follower_row_id',String(id)); }
            if (action === 'revoke_vps') { params.set('revoke_own_vps','1'); params.set('vps_id',String(id)); }
            if (action === 'restore_vps') { params.set('restore_own_vps','1'); params.set('revocation_id',String(id)); }
            if (action === 'cancel_vps_purchase') { params.set('cancel_vps_purchase','1'); params.set('request_id',String(id)); }
            if (action === 'begin_purchase') {
                params.set('csrf_token',<?= json_encode($catalogueCsrf) ?>); params.set('action','begin_purchase'); params.set('vps_id',String(id)); params.set('return_to','account');
                fetch('purchaseaccountvps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:params})
                .then(function(r){return r.json();}).then(function(data){btn.disabled=false;btn.textContent=originalText;if(!data.success){closeVpsConfirmModal();showVpsAlert(data.message||'Unable to start purchase.','Error');return;}openAccountVpsCheckout(data.redirect);})
                .catch(function(){btn.disabled=false;btn.textContent=originalText;closeVpsConfirmModal();showVpsAlert('Network error. Please try again.','Error');});
                return;
            }
            fetch('vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:params})
            .then(function(r){return r.json();}).then(function(data){
                btn.disabled=false;btn.textContent=originalText;
                if (data.code === 'broker_linked') { closeVpsConfirmModal(); showBrokerDisconnectNotice(); return; }
                if (!data.success) { showVpsAlert(data.message || 'Unable to complete this action.','Error'); return; }
                closeVpsConfirmModal(); showVpsAlert(data.message || 'Updated successfully.','Success');
                setTimeout(function(){window.location.reload();},700);
            }).catch(function(){btn.disabled=false;btn.textContent=originalText;showVpsAlert('Network error. Please try again.','Error');});
        } else {
            btn.disabled = false;
            btn.textContent = originalText;
            closeVpsConfirmModal();
        }
    }
    window.confirmModalAction = confirmModalAction;
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
    function loadFollowerVps() {
        var container = document.getElementById('myFollowerVpsContainer');
        if (!container || container.dataset.loaded === '1') return;
        container.innerHTML = '<div class="vps-requests-loading">Loading VPS details…</div>';
        fetch('vps.php', {method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'get_my_follower_owner=1'})
        .then(function(r){return r.json();})
        .then(function(data){
            container.dataset.loaded = '1';
            if (!data || !data.success || !data.is_follower) { container.innerHTML = ''; return; }
            renderFollowerVps(container,data.owner,data.self);
        })
        .catch(function(){container.innerHTML='<div class="vps-empty-state"><p>Unable to load VPS details.</p></div>';});
    }
    window.loadFollowerVps = loadFollowerVps;
    function renderFollowerVps(container, owner, self) {
        var html = '<article class="vps-owner-card">';
        html += '<div class="vps-owner-badge">VPS Owner</div>';
        html += '<div class="vps-owner-name">' + escapeHtml(owner.username || 'VPS Owner') + '</div>';
        html += '<p class="market-vps-description"><strong>You are hosted in this owner’s VPS as a follower</strong></p>';
        html += '<h3>' + escapeHtml(owner.vps_name || 'VPS') + '</h3>';
        html += '<div class="vps-owner-meta">';
        html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Proxy</span><span class="vps-owner-stat-value">' + escapeHtml(owner.server_location || 'N/A') + '</span></div>';
        html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Subscription Duration</span><span class="vps-owner-stat-value">' + escapeHtml(String(owner.subscription_duration || 0)) + ' days</span></div>';
        html += '<div class="vps-owner-stat"><span class="vps-owner-stat-label">Total Followers</span><span class="vps-owner-stat-value">' + (parseInt(owner.total_followers,10)||0) + '</span></div></div>';
        html += '<div class="market-vps-card-foot"><button type="button" class="vps-btn-request" onclick="requestUnfollowVps(' + (parseInt(self.follower_row_id,10)||0) + ', this)">Unfollow VPS</button></div>';
        html += '</article>';
        container.innerHTML = html;
    }
    window.renderFollowerVps = renderFollowerVps;

    function showBrokerDisconnectNotice() {
        showVpsAlert('Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.', 'Disconnect broker first');
    }
    window.showBrokerDisconnectNotice = showBrokerDisconnectNotice;
    function requestUnfollowVps(rowId, button) {
        var params = new URLSearchParams({check_vps_lifecycle_broker:'1',lifecycle_action:'unfollow',follower_row_id:String(rowId),csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
        fetch('vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:params})
        .then(function(r){return r.json();}).then(function(data){
            if (!data.success) { showVpsAlert(data.message || 'Unable to continue.','Error'); return; }
            if (data.broker_linked) { showBrokerDisconnectNotice(); return; }
            openVpsConfirm({type:'unfollow_vps',id:rowId,title:'Unfollow VPS',message:'Are you sure you want to unfollow this VPS?',confirmText:'Yes, Unfollow'});
        }).catch(function(){showVpsAlert('Network error. Please try again.','Error');});
    }
    window.requestUnfollowVps = requestUnfollowVps;
    function handleOwnerVpsOption(select, vpsId) {
        var value = select.value; select.value = '';
        if (value !== 'revoke') return;
        var params = new URLSearchParams({check_vps_lifecycle_broker:'1',lifecycle_action:'revoke',vps_id:String(vpsId),csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
        fetch('vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:params})
        .then(function(r){return r.json();}).then(function(data){
            if (!data.success) { showVpsAlert(data.message || 'Unable to continue.','Error'); return; }
            if (data.broker_linked) { showBrokerDisconnectNotice(); return; }
            openVpsConfirm({type:'revoke_vps',id:vpsId,title:'Revoke VPS',message:'Are you sure you want to revoke your VPS? Your plan with this VPS will be discontinued after the two-day grace period. You can restore it before the period ends.',confirmText:'Yes, Revoke VPS'});
        }).catch(function(){showVpsAlert('Network error. Please try again.','Error');});
    }
    window.handleOwnerVpsOption = handleOwnerVpsOption;
    function handleRestoreVpsOption(select, revocationId) {
        var value = select.value; select.value = '';
        if (value !== 'restore') return;
        openVpsConfirm({type:'restore_vps',id:revocationId,title:'Restore VPS',message:'Are you sure you want to restore this VPS and keep the current plan active?',confirmText:'Yes, Restore VPS'});
    }
    window.handleRestoreVpsOption = handleRestoreVpsOption;
    function confirmCancelVpsPurchase(requestId) {
        openVpsConfirm({type:'cancel_vps_purchase',id:requestId,title:'Cancel purchase',message:'Are you sure you want to cancel this VPS purchase? This is available only before payment is submitted.',confirmText:'Yes, Cancel Purchase'});
    }
    window.confirmCancelVpsPurchase = confirmCancelVpsPurchase;
    function updateVpsRevocationCountdowns() {
        document.querySelectorAll('[data-discontinue-epoch]').forEach(function(el){
            var target = (parseInt(el.getAttribute('data-discontinue-epoch') || '0',10) || 0) * 1000;
            var diff = target - Date.now();
            if (!Number.isFinite(target) || diff <= 0) { el.textContent = 'processing'; return; }
            var days = Math.floor(diff / 86400000); diff %= 86400000;
            var hours = Math.floor(diff / 3600000); diff %= 3600000;
            var minutes = Math.floor(diff / 60000);
            el.textContent = String(days).padStart(2,'0') + 'd ' + String(hours).padStart(2,'0') + 'h ' + String(minutes).padStart(2,'0') + 'm';
        });
    }
    updateVpsRevocationCountdowns();
    setInterval(updateVpsRevocationCountdowns, 15000);
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

<script>
function startVpsPurchase(planId, button){
    window.openVpsConfirm({type:'begin_purchase',id:planId,title:'Purchase VPS',message:'Create a VPS purchase request for this account and continue to checkout?',confirmText:'Continue to checkout'});
}
</script>

</body>
</html>
