<?php
// programme_vps.php — strictly programme-scoped VPS hub (PROGRAMME SIDE ONLY)
session_start();
try {
    require_once __DIR__ . '/mysqlconnection.php';
    if (!function_exists('getAvailablePdoConnection')) {
        throw new RuntimeException('getAvailablePdoConnection() was not found in mysqlconnection.php.');
    }
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('getAvailablePdoConnection() did not return a PDO connection.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ' database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower(trim((string)$_SESSION['user_email']));

$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($activeProgrammeId <= 0) { header("Location: traderapp.php?tab=vps"); exit; }

// Resolve the selected programme first, then verify its actual HarvHub owner.
// This avoids selecting an unrelated same-email account row with LIMIT 1.
$stmt = $pdo->prepare("SELECT * FROM programme WHERE id = ? LIMIT 1");
$stmt->execute([$activeProgrammeId]);
$programme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$programme) { header("Location: traderapp.php?tab=vps"); exit; }

$userId = (int)($programme['userid'] ?? 0);
if ($userId <= 0) { header("Location: traderapp.php?tab=vps"); exit; }
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE id = ? AND LOWER(email) = ? LIMIT 1");
$stmt->execute([$userId, $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php?role=developer"); exit; }

$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$fullName = $user['fullname'] ?? 'User';
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';
$pendingVpsPurchases = [];
$myProgrammeVpsRegistration = null;
try {
    require_once __DIR__ . '/server_vps_schema.php';
    serverVpsEnsureSchema($pdo);
    require_once __DIR__ . '/vps_revocation_helpers.php';
    vpsRevocationEnsureSchema($pdo);
    vpsRevocationProcessExpired($pdo, 25);
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
        WHERE r.request_type='programme' AND r.user_id=? AND r.sub_account_id=? AND r.programme_id=?
          AND r.payment_status IN ('awaiting_payment','payment-made','payment-confirmed')
          AND COALESCE(r.request_status,'') <> 'revoked_expired'
          AND NOT (r.payment_status='payment-confirmed' AND EXISTS (
              SELECT 1 FROM programme_vps owned WHERE owned.programme_id=r.programme_id
          ))
        ORDER BY r.id DESC LIMIT 50");
    $pendingQuery->execute([$userId,$activeSubAccountId,$activeProgrammeId]);
    $pendingVpsPurchases = $pendingQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $registeredError) {
    error_log('Programme VPS purchase/provisioning status could not be loaded: '.$registeredError->getMessage());
}
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
if (!function_exists('resolveUserSubAccountIdById')) {
    function resolveUserSubAccountIdById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT sub_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
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
// Programme VPS allocations are independent; account VPS records are never imported here.

// ==================== CONSISTENCY HELPERS ====================
if (!function_exists('cleanupStaleAcceptedRequests')) {
    function cleanupStaleAcceptedRequests($pdo, $ownerProgrammeId, $requestorProgrammeId) {
        try {
            $sql = "
                DELETE r
                FROM programme_vps_hosts_requestors r
                LEFT JOIN programme_vps_hosts_followers f
                       ON f.owner_programme_id    = r.owner_programme_id
                      AND f.follower_programme_id = r.requestor_programme_id
                WHERE r.owner_programme_id     = ?
                  AND r.requestor_programme_id = ?
                  AND r.request_status = 'accept'
                  AND f.id IS NULL
            ";
            $q = $pdo->prepare($sql);
            $q->execute([$ownerProgrammeId, $requestorProgrammeId]);
        } catch (Throwable $e) {
            error_log('cleanupStaleAcceptedRequests failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('purgeRequestorRowsForPair')) {
    function purgeRequestorRowsForPair($pdo, $ownerProgrammeId, $requestorProgrammeId) {
        try {
            $q = $pdo->prepare("
                DELETE FROM programme_vps_hosts_requestors
                WHERE owner_programme_id = ?
                  AND requestor_programme_id = ?
            ");
            $q->execute([$ownerProgrammeId, $requestorProgrammeId]);
        } catch (Throwable $e) {
            error_log('purgeRequestorRowsForPair failed: ' . $e->getMessage());
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
        WHERE v.visibility = 1
          AND v.programme_id <> ?
        ORDER BY p.program_name ASC
    ");
    $stmt->execute([$activeProgrammeId]);
    $programmeHosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $programmeHosts = []; }

$followerCountsProgramme = [];
foreach ($programmeHosts as $h) {
    $followerCountsProgramme[(int)$h['programme_id']] = countActiveProgrammeFollowers($pdo, (int)$h['programme_id']);
}

// ==================== FLAGS (STRICTLY PROGRAMME-SCOPED) ====================
$userOwnsProgrammeVps  = false;
$isAlreadyFollowerProg = false;
$myProgrammeVpsId = 0;
$myProgrammeVpsVisibility = '';

try {
    $s = $pdo->prepare("SELECT * FROM programme_vps WHERE programme_id = ? ORDER BY id DESC LIMIT 1");
    $s->execute([$activeProgrammeId]);
    $ownedProgrammeVpsRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($ownedProgrammeVpsRow) {
        $userOwnsProgrammeVps = true;
        $myProgrammeVpsId = (int)($ownedProgrammeVpsRow['id'] ?? 0);
        $myProgrammeVpsVisibility = (int)($ownedProgrammeVpsRow['visibility'] ?? 0);
        $registrationQuery = $pdo->prepare("SELECT id,product_name,provider_name,plan_name,vcpu_count,ram_gb,storage_type,storage_gb,server_location,operating_system,subscription_duration_days,currency,price,registration_status FROM server_registered_vps
            WHERE request_type='programme' AND (programme_vps_id=? OR (user_id=? AND sub_account_id=? AND programme_id=?))
            ORDER BY CASE WHEN programme_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1");
        $registrationQuery->execute([$myProgrammeVpsId,$userId,$activeSubAccountId,$activeProgrammeId,$myProgrammeVpsId]);
        $myProgrammeVpsRegistration = $registrationQuery->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (PDOException $e) {}

try {
    $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
    $s->execute([$activeProgrammeId]);
    if ($s->fetch(PDO::FETCH_ASSOC)) $isAlreadyFollowerProg = true;
} catch (PDOException $e) {}

$userHasVps = ($userOwnsProgrammeVps || $isAlreadyFollowerProg);
$vpsLifecycleCsrf = $_SESSION['vps_lifecycle_csrf'] ??= bin2hex(random_bytes(32));
$pendingProgrammeVpsRevocations = [];
$pendingOwnProgrammeVpsRevocation = null;
try {
    $pendingProgrammeVpsRevocations = vpsRevocationLoadPending($pdo,'programme',$userId,$activeSubAccountId,$activeProgrammeId);
    foreach ($pendingProgrammeVpsRevocations as $revocationRow) {
        if ((int)$revocationRow['programme_vps_id'] === $myProgrammeVpsId) { $pendingOwnProgrammeVpsRevocation = $revocationRow; break; }
    }
} catch (Throwable $e) { error_log('[programme_vps] pending revocation list failed: '.$e->getMessage()); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_vps_purchase'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent=(string)($_POST['csrf_token']??'');
    if($sent===''||!hash_equals((string)$vpsLifecycleCsrf,$sent)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'The request expired. Refresh and try again.']);exit;}
    $requestId=(int)($_POST['request_id']??0);
    try {
        $pdo->beginTransaction();
        $q=$pdo->prepare("SELECT id,vps_id,payment_status FROM server_vps_purchase_requests WHERE id=? AND request_type='programme' AND programme_id=? AND user_id=? AND sub_account_id=? FOR UPDATE");
        $q->execute([$requestId,$activeProgrammeId,$userId,$activeSubAccountId]);$requestRow=$q->fetch(PDO::FETCH_ASSOC);
        if(!$requestRow)throw new RuntimeException('Purchase request not found.');
        if((string)$requestRow['payment_status']!=='awaiting_payment')throw new RuntimeException('This purchase can no longer be cancelled because payment has already been submitted.');
        $pdo->prepare("DELETE FROM server_vps_purchase_requests WHERE id=? AND request_type='programme' AND programme_id=? AND user_id=? AND sub_account_id=? AND payment_status='awaiting_payment'")->execute([$requestId,$activeProgrammeId,$userId,$activeSubAccountId]);
        $planId=(int)$requestRow['vps_id'];
        $pdo->prepare("UPDATE server_available_vps SET buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests r WHERE r.vps_id=? AND r.buy_request=1),1,0) WHERE id=?")->execute([$planId,$planId]);
        $pdo->commit();echo json_encode(['success'=>true,'message'=>'Purchase request cancelled.']);
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_vps_lifecycle_broker'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent=(string)($_POST['csrf_token']??'');
    if($sent===''||!hash_equals((string)$vpsLifecycleCsrf,$sent)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'The request expired.']);exit;}
    $action=(string)($_POST['lifecycle_action']??'');
    if($action==='revoke'){
        $opsId=(int)($_POST['programme_vps_id']??0);
        if(!$userOwnsProgrammeVps||$opsId!==$myProgrammeVpsId||$pendingOwnProgrammeVpsRevocation){echo json_encode(['success'=>false,'message'=>'This VPS is not available for revocation.']);exit;}
    }elseif($action==='unfollow'){
        $rowId=(int)($_POST['follower_row_id']??0);
        $q=$pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE id=? AND follower_programme_id=? AND host_status='active' LIMIT 1");
        $q->execute([$rowId,$activeProgrammeId]);if(!$q->fetchColumn()){echo json_encode(['success'=>false,'message'=>'The VPS follow record could not be found.']);exit;}
    }else{echo json_encode(['success'=>false,'message'=>'Invalid action.']);exit;}
    $linked=vpsRevocationBrokerLinked($pdo,'programme',$userId,$activeSubAccountId,$activeProgrammeId);
    echo json_encode(['success'=>true,'broker_linked'=>$linked,'message'=>$linked?'Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.':'']);exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unfollow_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent=(string)($_POST['csrf_token']??'');
    if($sent===''||!hash_equals((string)$vpsLifecycleCsrf,$sent)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'The request expired.']);exit;}
    try{
        if(vpsRevocationBrokerLinked($pdo,'programme',$userId,$activeSubAccountId,$activeProgrammeId)){echo json_encode(['success'=>false,'code'=>'broker_linked','message'=>'Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.']);exit;}
        $rowId=(int)($_POST['follower_row_id']??0);
        $q=$pdo->prepare("DELETE FROM programme_vps_hosts_followers WHERE id=? AND follower_programme_id=? AND host_status='active'");
        $q->execute([$rowId,$activeProgrammeId]);if($q->rowCount()!==1)throw new RuntimeException('This programme is no longer following that VPS.');
        $pdo->prepare('DELETE FROM programme_vps_hosts_requestors WHERE requestor_programme_id=?')->execute([$activeProgrammeId]);
        echo json_encode(['success'=>true,'message'=>'This programme has unfollowed the VPS.']);
    }catch(Throwable $e){echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_own_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent=(string)($_POST['csrf_token']??'');
    if($sent===''||!hash_equals((string)$vpsLifecycleCsrf,$sent)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'The request expired.']);exit;}
    try{
        if(!$userOwnsProgrammeVps||(int)($_POST['programme_vps_id']??0)!==$myProgrammeVpsId||$pendingOwnProgrammeVpsRevocation)throw new RuntimeException('This VPS is not available for revocation.');
        $result=vpsRevocationStart($pdo,'programme',$userId,$activeSubAccountId,$activeProgrammeId,$myProgrammeVpsId);
        if(!empty($result['blocked'])){echo json_encode(['success'=>false,'code'=>'broker_linked','message'=>$result['message']]);exit;}
        echo json_encode(['success'=>true,'message'=>'Your programme VPS is scheduled for discontinuation in two days.','revocation_id'=>$result['id']]);
    }catch(Throwable $e){error_log('[programme_vps] revoke failed: '.$e->getMessage());echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_own_vps'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sent=(string)($_POST['csrf_token']??'');
    if($sent===''||!hash_equals((string)$vpsLifecycleCsrf,$sent)){http_response_code(419);echo json_encode(['success'=>false,'message'=>'The request expired.']);exit;}
    try{vpsRevocationRestore($pdo,'programme',$userId,$activeSubAccountId,$activeProgrammeId,(int)($_POST['revocation_id']??0));echo json_encode(['success'=>true,'message'=>'Your programme VPS has been restored.']);}
    catch(Throwable $e){echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
    exit;
}

// ==================== UPDATE OWN PROGRAMME VPS VISIBILITY (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_programme_vps_visibility'])) {
    header('Content-Type: application/json; charset=utf-8');
    $visibilityRaw = trim((string)($_POST['visibility'] ?? ''));
    // Accept the numeric values used by the new selector. Text values remain
    // accepted for compatibility with an older open page during deployment.
    if ($visibilityRaw === 'public') $visibilityRaw = '1';
    if ($visibilityRaw === 'private') $visibilityRaw = '0';
    if (!in_array($visibilityRaw, ['0', '1'], true)) {
        echo json_encode(['success' => false, 'message' => 'Choose either Public or Private visibility.']);
        exit;
    }
    $visibility = (int)$visibilityRaw;
    $visibilityLabel = $visibility === 1 ? 'Public' : 'Private';
    if (!$userOwnsProgrammeVps || $myProgrammeVpsId <= 0 || $pendingOwnProgrammeVpsRevocation) {
        echo json_encode(['success' => false, 'message' => 'No VPS owned by this programme was found.']);
        exit;
    }
    try {
        $update = $pdo->prepare("UPDATE `programme_vps` pv INNER JOIN `programme` p ON p.`id` = pv.`programme_id` SET pv.`visibility` = ? WHERE pv.`id` = ? AND pv.`programme_id` = ? AND p.`userid` = ?");
        $update->execute([$visibility, $myProgrammeVpsId, $activeProgrammeId, $userId]);
        echo json_encode([
            'success' => true,
            'message' => 'Programme VPS visibility updated to ' . $visibilityLabel . '.',
            'visibility' => $visibility,
            'visibility_label' => $visibilityLabel
        ]);
    } catch (Throwable $e) {
        error_log('[programme_vps] visibility update failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Unable to update programme VPS visibility. Please try again.']);
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

    cleanupStaleAcceptedRequests($pdo, $ownerProgId, $activeProgrammeId);

    try {
        $s = $pdo->prepare("SELECT user_id FROM programme_vps WHERE programme_id = ? AND visibility = 1 LIMIT 1");
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

        recordProgrammeNotification($pdo, $ownerProgId, resolveUserEmailById($pdo, $ownerId), [
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
            'force'            => true
        ]);

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

    cleanupStaleAcceptedRequests($pdo, $ownerProgId, $activeProgrammeId);

    try {
        $s = $pdo->prepare("
            SELECT v.programme_id, v.user_id, v.server_location, v.subscription_duration,
                   p.program_name, h.fullname, h.first_name, h.last_name, h.username, h.email AS host_email
            FROM programme_vps v
            INNER JOIN programme p ON p.id = v.programme_id
            INNER JOIN harvhub   h ON h.id = v.user_id
            WHERE v.programme_id = ?
              AND v.visibility = 1
              AND v.programme_id <> ?
            LIMIT 1
        ");
        $s->execute([$ownerProgId, $activeProgrammeId]);
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

// ==================== MY FOLLOWER OWNER (SHOWN INSIDE MY VPS) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_follower_owner'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $q=$pdo->prepare("SELECT f.id AS follower_row_id,f.host_status,f.owner_programme_id,
                p.program_name,h.username,h.fullname,h.first_name,h.last_name,
                v.server_location,v.subscription_duration,rv.plan_name,rv.product_name
            FROM programme_vps_hosts_followers f
            LEFT JOIN programme p ON p.id=f.owner_programme_id
            LEFT JOIN harvhub h ON h.id=p.userid
            LEFT JOIN programme_vps v ON v.programme_id=f.owner_programme_id
            LEFT JOIN server_registered_vps rv ON rv.programme_vps_id=v.id AND rv.request_type='programme'
            WHERE f.follower_programme_id=? AND f.host_status='active' LIMIT 1");
        $q->execute([$activeProgrammeId]);$row=$q->fetch(PDO::FETCH_ASSOC);
    } catch(Throwable $e){error_log('[programme_vps] follower owner lookup: '.$e->getMessage());$row=null;}
    if(!$row){echo json_encode(['success'=>true,'is_follower'=>false]);exit;}
    $username=trim((string)($row['username']??''));if($username==='')$username=trim((string)($row['fullname']??''))?:resolveHostDisplayName($row);
    $vpsName=trim((string)($row['program_name']??''));if($vpsName==='')$vpsName=trim((string)($row['plan_name']??''))?: (trim((string)($row['product_name']??''))?:'VPS');
    echo json_encode(['success'=>true,'is_follower'=>true,'owner'=>[
        'username'=>$username,'vps_name'=>$vpsName,'server_location'=>$row['server_location']??'',
        'subscription_duration'=>$row['subscription_duration']??0,
        'total_followers'=>countActiveProgrammeFollowers($pdo,(int)$row['owner_programme_id'])
    ],'self'=>['follower_row_id'=>(int)$row['follower_row_id'],'host_status'=>$row['host_status']]]);
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

        $requestorProgrammeId = (int)$row['requestor_programme_id'];

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
                $chk->execute([$activeProgrammeId, $requestorProgrammeId]);
                if (!$chk->fetch(PDO::FETCH_ASSOC)) {
                    $ins = $pdo->prepare("
                        INSERT INTO programme_vps_hosts_followers
                          (owner_programme_id, follower_programme_id, host_status)
                        VALUES (?, ?, 'active')
                    ");
                    $ins->execute([$activeProgrammeId, $requestorProgrammeId]);
                }
            } catch (Throwable $e) {}

            purgeRequestorRowsForPair($pdo, $activeProgrammeId, $requestorProgrammeId);
        }

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
            recordProgrammeNotification($pdo, $requestorProgrammeId, $requestorEmail, [
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

        $removedFollowerProgId = (int)$row['follower_programme_id'];

        $followerUserId = 0;
        $followerEmail  = '';
        try {
            $q = $pdo->prepare("SELECT userid FROM programme WHERE id = ? LIMIT 1");
            $q->execute([$removedFollowerProgId]);
            $followerUserId = (int)($q->fetchColumn() ?: 0);
            if ($followerUserId > 0) {
                $followerEmail = resolveUserEmailById($pdo, $followerUserId);
            }
        } catch (Throwable $e) {}

        $pdo->prepare("DELETE FROM programme_vps_hosts_followers WHERE id = ?")->execute([$rowId]);

        purgeRequestorRowsForPair($pdo, $activeProgrammeId, $removedFollowerProgId);

        if ($followerEmail !== '' && $followerUserId > 0) {
            $ownerDisplay = resolveProgrammeDisplayName($programme);
            recordProgrammeNotification($pdo, $removedFollowerProgId, $followerEmail, [
                'notification_key' => 'pvps-follower-removed-' . $activeProgrammeId . '-' . date('YmdHis'),
                'title'            => 'Removed from VPS',
                'message'          => $ownerDisplay . ' has removed you from their VPS. You may now request space from another host.',
                'type'             => 'warning',
                'section'          => 'VPS',
                'action_tab'       => 'vps',
                'force'            => true
            ]);
        }

        echo json_encode(['success'=>true,'message'=>'Follower removed.']);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Failed.']);
    }
    exit;
}

function showSpinner() {
    echo '<style>
html,body,*{scrollbar-width:none!important;-ms-overflow-style:none!important}html::-webkit-scrollbar,body::-webkit-scrollbar,*::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}

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
// Public VPS catalogue: public offer fields only; provisioning data is excluded.
$catalogueCsrf = $_SESSION['vps_purchase_csrf'] ??= bin2hex(random_bytes(32));
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
    error_log('Programme VPS catalogue unavailable: ' . $catalogueError->getMessage());
}
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
    .vps-topbar { position: relative; display: flex; align-items: center; justify-content: center; min-height: 44px; }
    .vps-topbar-back { position: absolute; left: 0; top: 50%; transform: translateY(-50%); z-index: 12; }
    /* Host action controls stay on the far right for every host row. */
    .vps-host-actions {
        display:flex !important;
        align-items:center !important;
        justify-content:flex-end !important;
        gap:10px;
        width:100%;
    }
    .vps-host-actions.vps-owner-actions {
        /* Keep the owner status at the far left and the visibility control at the far right. */
        justify-content:space-between !important;
        gap:10px;
        flex-wrap:nowrap;
    }
    .vps-host-actions.vps-owner-actions .vps-owner-status {
        margin-right:auto;
        flex:0 0 auto;
    }
    .vps-host-actions.vps-owner-actions .vps-visibility-select {
        margin-left:auto !important;
        flex:0 0 auto;
    }
    .vps-owner-status {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:40px;
        color:var(--text, #1f2937);
        font-size:13px;
        font-weight:700;
        white-space:nowrap;
    }
    .vps-visibility-select {
        display:inline-block !important;
        width:auto !important;
        min-width:118px;
        max-width:100%;
        min-height:40px;
        height:40px;
        margin:0 !important;
        padding:8px 30px 8px 11px;
        border:1px solid #d8dde5 !important;
        border-radius:9px;
        background-color:#ffffff !important;
        color:#1f2937 !important;
        font:inherit;
        font-size:13px;
        line-height:1.2;
        cursor:pointer;
        color-scheme:light;
    }
    .vps-visibility-select option {
        background-color:#ffffff;
        color:#1f2937;
    }
    body.dark-mode .vps-owner-status {
        color:var(--text, #f3f4f6);
    }
    body.dark-mode .vps-visibility-select {
        background-color:#1f2937 !important;
        color:#f9fafb !important;
        border-color:#475569 !important;
        color-scheme:dark;
    }
    body.dark-mode .vps-visibility-select option {
        background-color:#1f2937;
        color:#f9fafb;
    }
    @media (max-width:420px) {
        .vps-host-actions.vps-owner-actions { gap:7px; }
        .vps-visibility-select { min-width:108px; }
    }
</style>

<style>
.market-vps-intro{margin:5px 0 16px}.market-vps-intro h3{margin:0 0 4px;font-size:18px;color:var(--text,#1a2332)}.market-vps-intro p{margin:0;color:var(--text-muted,#8291a3);font-size:13px}.market-vps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.market-vps-card{background:var(--card-bg,var(--bg-card,#fff));color:var(--text,#1a2332);border:1px solid var(--border-color,#e4eaf0);border-radius:14px;padding:16px;box-shadow:var(--shadow,0 4px 16px rgba(0,0,0,.04))}.market-vps-card-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.market-vps-provider{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted,#8090a0)}.market-vps-card h3{font-size:17px;margin:4px 0 0;color:var(--text,#1a2332)}.market-vps-card-head p{margin:2px 0;color:var(--text-muted,#8090a0);font-size:12px}.market-vps-price{text-align:right;white-space:nowrap}.market-vps-price strong{display:block;font-size:17px;color:var(--accent-color,var(--accent,#159a6d))}.market-vps-price span{display:block;font-size:11px;color:var(--text-muted,#8090a0)}.market-vps-description{font-size:12px;color:var(--text-secondary,#7d8a99);margin:12px 0}.market-vps-specs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:14px 0}.market-vps-specs>div{background:var(--bg-secondary,var(--input-bg,#f7f9fc));color:var(--text,#1a2332);padding:9px;border-radius:8px;min-width:0}.market-vps-specs b{display:block;font-size:12px;overflow-wrap:anywhere}.market-vps-specs span{font-size:10px;color:var(--text-muted,#8793a2)}.market-vps-card-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid var(--border-color,#e8edf2);padding-top:12px;font-size:12px;color:var(--text-muted,#8793a2)}.market-vps-card-foot button{margin:0}.market-vps-card-foot button:disabled{opacity:.55;cursor:not-allowed}
</style>

</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> vps-page-body">

    <div class="vps-page-wrapper">
        <div class="vps-sticky-top" id="vpsStickyTop">
            <div class="vps-topbar">
                <a href="#" class="vps-topbar-back" onclick="event.preventDefault(); harvhubGoBackFromVps(); return false;" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></a>
                <h1 class="vps-topbar-title">Programme VPS</h1>
            </div>
            <?php if (!$isAlreadyFollowerProg): ?>
            <div class="vps-search-wrap">
                <input type="text" id="vpsSearchInput" class="vps-search-input" placeholder="Search username or url" autocomplete="off" oninput="onVpsSearch(this.value)" onfocus="onVpsSearch(this.value)">
            </div>
            <?php endif; ?>
            <div class="vps-tabs" id="vpsTabs">
                <button class="vps-tab active" data-tab="purchase" onclick="switchVpsTab('purchase')">Purchase VPS</button>
                <?php if (!empty($pendingVpsPurchases)): ?>
                    <button class="vps-tab" data-tab="pending" onclick="switchVpsTab('pending')">Purchase Pending <span class="vps-tab-badge"><?= count($pendingVpsPurchases) ?></span></button>
                <?php endif; ?>
                <button class="vps-tab" data-tab="myvps" onclick="switchVpsTab('myvps')">My VPS</button>
                <?php if (!empty($pendingProgrammeVpsRevocations)): ?>
                    <button class="vps-tab" data-tab="revoked" onclick="switchVpsTab('revoked')">Pending Revoked <span class="vps-tab-badge"><?= count($pendingProgrammeVpsRevocations) ?></span></button>
                <?php endif; ?>
                <?php if (!$isAlreadyFollowerProg): ?>
                    <button class="vps-tab" data-tab="users" onclick="switchVpsTab('users')">VPS Owners</button>
                <?php endif; ?>
                <?php if ($userOwnsProgrammeVps && !$pendingOwnProgrammeVpsRevocation): ?>
                    <button class="vps-tab" data-tab="incoming" onclick="switchVpsTab('incoming')">Incoming Requests <span class="vps-tab-badge" id="incomingTabBadge" style="display:none;">0</span></button>
                <?php endif; ?>
                <?php if (!$isAlreadyFollowerProg): ?>
                    <button class="vps-tab" data-tab="sent" onclick="switchVpsTab('sent')">Sent Requests</button>
                <?php endif; ?>
                <?php if ($userOwnsProgrammeVps && !$pendingOwnProgrammeVpsRevocation): ?>
                    <button class="vps-tab" data-tab="followers" onclick="switchVpsTab('followers')">My Followers</button>
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
                            <div class="market-vps-card-foot"><span><?= max(0,(int)$plan['stock_quantity']-(int)$plan['allocated_quantity']) ?> available</span><button type="button" class="vps-btn-link" onclick="startProgrammeVpsPurchase(<?= (int)$plan['id'] ?>, this)" <?= (!empty($userOwnsProgrammeVps) || !empty($pendingVpsPurchases)) ? 'disabled title="This programme already has a VPS or pending purchase"' : '' ?>>Buy now</button></div>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

<?php if (!empty($pendingVpsPurchases)): ?>
            <div class="vps-tab-content" id="vpsTabPending">
                <div class="market-vps-intro"><h3>Purchase Pending</h3></div>
                <?php if (empty($pendingVpsPurchases)): ?>
                    <div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>This programme has no pending VPS purchases.</p></div>
                <?php else: ?>
                    <div class="market-vps-grid">
                    <?php foreach ($pendingVpsPurchases as $purchase):
                        $purchaseStatus = (string)($purchase['payment_status'] ?? '');
                        if ($purchaseStatus === 'payment-confirmed') { $statusLabel = 'Payment confirmed · Setup pending'; $actionLabel = 'View purchase'; }
                        elseif ($purchaseStatus === 'payment-made') { $statusLabel = 'Payment submitted · Awaiting verification'; $actionLabel = 'View payment'; }
                        else { $statusLabel = 'Awaiting payment'; $actionLabel = 'Continue payment'; }
                        $checkoutUrl = 'purchaseprogrammevps.php?request_id='.(int)$purchase['request_id'];
                    ?>
                        <article class="market-vps-card">
                            <div class="market-vps-card-head"><div><span class="market-vps-provider"><?= htmlspecialchars((string)($purchase['provider_name'] ?: 'HarvHub VPS'),ENT_QUOTES,'UTF-8') ?></span><h3><?= htmlspecialchars((string)($purchase['product_name'] ?: 'VPS Plan'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($purchase['plan_name'] ?: ''),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($purchase['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)$purchase['amount'],2) ?></strong><span>Purchase #<?= (int)$purchase['request_id'] ?></span></div></div>
                            <p class="market-vps-description"><strong><?= htmlspecialchars($statusLabel,ENT_QUOTES,'UTF-8') ?></strong></p>
                            <div class="market-vps-specs"><div><b><?= (int)($purchase['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($purchase['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($purchase['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($purchase['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($purchase['server_location']??''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($purchase['operating_system']?:'Choose at provisioning'),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($purchase['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div></div>
                            <?php if ($purchaseStatus !== 'payment-confirmed'): ?><a class="vps-btn-link" href="<?= htmlspecialchars($checkoutUrl,ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($actionLabel,ENT_QUOTES,'UTF-8') ?></a><?php endif; ?>
                            <?php if ($purchaseStatus === 'awaiting_payment'): ?><button type="button" class="vps-btn-request" onclick="confirmCancelProgrammeVpsPurchase(<?= (int)$purchase['request_id'] ?>)">Cancel purchase</button><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
<?php endif; ?>
            <div class="vps-tab-content" id="vpsTabMyVps">
                <div class="market-vps-intro"><h3>My VPS</h3></div>
                <?php if ($userOwnsProgrammeVps && !$pendingOwnProgrammeVpsRevocation && $myProgrammeVpsRegistration): ?>
                    <article class="market-vps-card" style="max-width:850px">
                        <div class="market-vps-card-head"><div><span class="market-vps-provider">My VPS · Programme allocation</span><h3><?= htmlspecialchars((string)($myProgrammeVpsRegistration['product_name'] ?: 'Existing VPS'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($myProgrammeVpsRegistration['plan_name'] ?: 'Existing allocation'),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($myProgrammeVpsRegistration['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)$myProgrammeVpsRegistration['price'],2) ?></strong><span>VPS #<?= (int)$myProgrammeVpsId ?></span></div></div>
                        <?php if (($myProgrammeVpsRegistration['registration_status'] ?? '') === 'registered'): ?><p class="market-vps-description"><strong style="color:#18794e">Ready</strong></p><?php else: ?><p class="market-vps-description"><strong style="color:#b7791f">Setup pending</strong></p><?php endif; ?>
                        <div class="market-vps-specs"><div><b><?= (int)($myProgrammeVpsRegistration['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($myProgrammeVpsRegistration['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($myProgrammeVpsRegistration['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($myProgrammeVpsRegistration['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($myProgrammeVpsRegistration['server_location']??''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($myProgrammeVpsRegistration['operating_system']??''),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($myProgrammeVpsRegistration['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div></div>
                        <div class="market-vps-card-foot"><span>Visibility</span><select class="vps-visibility-select" aria-label="My programme VPS visibility" data-saved-value="<?= (int)$myProgrammeVpsVisibility ?>" onchange="updateProgrammeVpsVisibility(this, <?= (int)$activeProgrammeId ?>)"><option value="1" <?= (int)$myProgrammeVpsVisibility===1?'selected':'' ?>>Public</option><option value="0" <?= (int)$myProgrammeVpsVisibility===0?'selected':'' ?>>Private</option></select>
                            <select class="vps-visibility-select" aria-label="Programme VPS options" onchange="handleProgrammeOwnerVpsOption(this, <?= (int)$myProgrammeVpsId ?>)"><option value="">Options</option><option value="revoke">Revoke VPS</option></select>
                        </div>
                    </article>
                <?php elseif ($userOwnsProgrammeVps && !$pendingOwnProgrammeVpsRevocation): ?>
                    <div class="vps-empty-state"><p>Setup pending.</p><select class="vps-visibility-select" aria-label="Programme VPS options" onchange="handleProgrammeOwnerVpsOption(this, <?= (int)$myProgrammeVpsId ?>)"><option value="">Options</option><option value="revoke">Revoke VPS</option></select></div>
                <?php elseif ($pendingOwnProgrammeVpsRevocation): ?>
                    <div class="vps-empty-state"><p>Your VPS is listed under Pending Revoked.</p></div>
                <?php endif; ?>
                <div id="myProgrammeFollowerVpsContainer" class="vps-requests-list"></div>
                <?php if (!$userOwnsProgrammeVps && !$isAlreadyFollowerProg): ?><div class="vps-empty-state" id="noMyProgrammeVpsNotice"><span class="vps-empty-icon">—</span><p>No VPS is currently assigned to this programme.</p></div><?php endif; ?>
            </div>
<?php if (!empty($pendingProgrammeVpsRevocations)): ?>
            <div class="vps-tab-content" id="vpsTabRevoked">
                <div class="market-vps-intro"><h3>Pending Revoked</h3></div>
                <?php foreach ($pendingProgrammeVpsRevocations as $rev): ?>
                    <article class="market-vps-card" style="max-width:850px">
                        <div class="market-vps-card-head"><div><span class="market-vps-provider">Pending Revoked</span><h3><?= htmlspecialchars((string)($rev['product_name'] ?: 'Existing VPS'),ENT_QUOTES,'UTF-8') ?></h3><p><?= htmlspecialchars((string)($rev['plan_name'] ?: 'Programme VPS allocation'),ENT_QUOTES,'UTF-8') ?></p></div><div class="market-vps-price"><strong><?= htmlspecialchars((string)($rev['currency'] ?: 'USD'),ENT_QUOTES,'UTF-8') ?> <?= number_format((float)($rev['price'] ?? 0),2) ?></strong><span>VPS #<?= (int)$rev['programme_vps_id'] ?></span></div></div>
                        <p class="market-vps-description"><strong>Your VPS will be discontinued in <span class="vps-revocation-countdown" data-discontinue-epoch="<?= (int)($rev['scheduled_discontinuation_epoch'] ?? 0) ?>">calculating…</span></strong></p>
                        <div class="market-vps-specs"><div><b><?= (int)($rev['vcpu_count']??0) ?> vCPU</b><span>Processor</span></div><div><b><?= htmlspecialchars((string)($rev['ram_gb']??0),ENT_QUOTES,'UTF-8') ?> GB RAM</b><span>Memory</span></div><div><b><?= htmlspecialchars((string)($rev['storage_gb']??0),ENT_QUOTES,'UTF-8') ?> GB <?= htmlspecialchars((string)($rev['storage_type']??''),ENT_QUOTES,'UTF-8') ?></b><span>Storage</span></div><div><b><?= htmlspecialchars((string)($rev['snapshot_location'] ?: ''),ENT_QUOTES,'UTF-8') ?></b><span>Location</span></div><div><b><?= htmlspecialchars((string)($rev['operating_system']??''),ENT_QUOTES,'UTF-8') ?></b><span>Operating system</span></div><div><b><?= (int)($rev['subscription_duration_days']??0) ?> days</b><span>Subscription term</span></div><div><b><?= htmlspecialchars((string)($rev['network_port_speed_mbps'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Network port Mbps</span></div><div><b><?= htmlspecialchars((string)($rev['data_transfer'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Data transfer</span></div><div><b><?= htmlspecialchars((string)($rev['backup_option'] ?? 'Not specified'),ENT_QUOTES,'UTF-8') ?></b><span>Backup</span></div><div><b><?= (int)($rev['snapshot_count']??0) ?></b><span>Snapshots</span></div></div>
                        <div class="market-vps-card-foot"><span>Reconsider</span><select class="vps-visibility-select" aria-label="Reconsider programme VPS revocation" onchange="handleProgrammeRestoreVpsOption(this, <?= (int)$rev['id'] ?>)"><option value="">Select option</option><option value="restore">Restore VPS</option></select></div>
                    </article>
                <?php endforeach; ?>
            </div>
<?php endif; ?>
            <div class="vps-tab-content" id="vpsTabUsers">
                <?php if ($isAlreadyFollowerProg): ?>
                    <div class="vps-follower-lock" style="margin-bottom:16px;padding:12px 16px;background:rgba(243,156,18,0.1);border-radius:8px;font-size:13px;line-height:1.5;color:var(--text);"><strong>This programme is already a follower of another VPS.</strong> You cannot send requests to other hosts.</div>
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
                                <div class="vps-host-actions<?= $isSelf ? ' vps-owner-actions' : '' ?>">
                                    <?php if ($isSelf): ?>
                                        <span class="vps-owner-status">Your VPS</span>
                                        <select
                                            class="vps-visibility-select"
                                            aria-label="VPS visibility"
                                            data-saved-value="<?= (int)($h['visibility'] ?? 0) ?>"
                                            onchange="updateProgrammeVpsVisibility(this, <?= $ownerProgId ?>)"
                                        >
                                            <option value="1" <?= (int)($h['visibility'] ?? 0) === 1 ? 'selected' : '' ?>>Public</option>
                                            <option value="0" <?= (int)($h['visibility'] ?? 0) === 0 ? 'selected' : '' ?>>Private</option>
                                        </select>
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

    // The shell records whether Programme VPS was opened from Signals,
    // Programme Menu, or another workspace tab. Ask the shell to restore that
    // exact source rather than always forcing navigation to Signals.
    function harvhubGoBackFromVps() {
        var allowedReturnTabs = ['signals', 'menu', 'training', 'analytics'];
        var returnTab = '';

        // The shell URL is authoritative: traderapp.php writes vps_from whenever
        // it switches into this iframe, so this survives stale child-frame state.
        try {
            if (window.parent && window.parent !== window) {
                var parentUrl = new URL(window.parent.location.href);
                returnTab = parentUrl.searchParams.get('vps_from') || '';
            }
        } catch (e) {}

        // Same-origin session storage is a second fallback; the child page's own
        // query string is used only when opened outside the shell.
        if (allowedReturnTabs.indexOf(returnTab) === -1) {
            try {
                var childParams = new URLSearchParams(window.location.search);
                returnTab = childParams.get('vps_from') || sessionStorage.getItem('harvhub_programme_vps_return_tab') || '';
            } catch (e) {}
        }
        if (allowedReturnTabs.indexOf(returnTab) === -1) returnTab = 'signals';

        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'programmeVpsBack', returnTab: returnTab }, '*');
                return;
            }
        } catch (e) {}

        window.location.href = 'traderapp.php?tab=' + encodeURIComponent(returnTab);
    }
    window.harvhubGoBackFromVps = harvhubGoBackFromVps;

    (function () {
        document.body.classList.add('page-vps');
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
            if (e.data.type === 'harvhubVpsNavigateTab' && typeof window.switchVpsTab === 'function') window.switchVpsTab(String(e.data.tab || 'purchase'));
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
        else if (tab === 'pending') { var pendingPanel=document.getElementById('vpsTabPending'); if(pendingPanel) pendingPanel.classList.add('active'); else { switchVpsTab('myvps'); return; } }
        else if (tab === 'myvps') { document.getElementById('vpsTabMyVps').classList.add('active'); loadProgrammeFollowerVps(); }
        else if (tab === 'revoked') { var rev=document.getElementById('vpsTabRevoked'); if(rev)rev.classList.add('active');else switchVpsTab('myvps'); }
        else if (tab === 'users') {
            if (isAlreadyFollowerProg) { switchVpsTab('myvps'); return; }
            document.getElementById('vpsTabUsers').classList.add('active');
            var s=document.getElementById('vpsSearchInput'); if(s)s.value='';
            onVpsSearch('');
        }
        else if (tab === 'sent') { var el=document.getElementById('vpsTabSent'); if(el){el.classList.add('active');loadSentRequests();} }
        else if (tab === 'incoming' && userOwnsProgrammeVps) { var el3=document.getElementById('vpsTabIncoming'); if(el3){el3.classList.add('active');loadIncomingRequests();} }
        else if (tab === 'followers' && userOwnsProgrammeVps) { var el4=document.getElementById('vpsTabFollowers'); if(el4){el4.classList.add('active');loadMyFollowers();} }
        showStickyTop();
    }
    window.switchVpsTab = switchVpsTab;
    var requestedVpsTab = new URLSearchParams(window.location.search).get('vps_tab') || new URLSearchParams(window.location.search).get('tab');
    if (requestedVpsTab === 'pending' && document.getElementById('vpsTabPending')) switchVpsTab('pending');
    else if (requestedVpsTab === 'revoked' && document.getElementById('vpsTabRevoked')) switchVpsTab('revoked');
    else if (requestedVpsTab === 'myvps') switchVpsTab('myvps');

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

    // Visibility is saved immediately when the programme owner changes the inline selector.
    function updateProgrammeVpsVisibility(select, ownerProgrammeId) {
        if (!select || select.dataset.saving === '1') return;

        var visibility = String(select.value);
        var previousValue = String(select.dataset.savedValue || select.value);
        if (visibility !== '0' && visibility !== '1') {
            select.value = previousValue;
            showVpsAlert('Choose either Public or Private.', 'Choose Visibility');
            return;
        }
        if (visibility === previousValue) return;

        select.dataset.saving = '1';
        select.disabled = true;

        var body = new URLSearchParams({
            set_programme_vps_visibility: '1',
            visibility: visibility
        });

        fetch('programme_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body.toString()
        })
        .then(function(response) {
            if (!response.ok) throw new Error('Request failed');
            return response.json();
        })
        .then(function(data) {
            if (data && data.success) {
                select.dataset.savedValue = String(data.visibility);
                showVpsAlert(data.message || 'Programme VPS visibility updated.', 'Success', true);
            } else {
                select.value = previousValue;
                showVpsAlert((data && data.message) || 'Unable to update programme VPS visibility.', 'Error');
            }
        })
        .catch(function() {
            select.value = previousValue;
            showVpsAlert('Network error while updating programme VPS visibility.', 'Error');
        })
        .finally(function() {
            select.disabled = false;
            select.dataset.saving = '0';
        });
    }
    window.updateProgrammeVpsVisibility = updateProgrammeVpsVisibility;

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

    function openProgrammeVpsCheckout(destination) {
        try {
            var checkout = new URL(String(destination || ''), window.location.href);
            if (checkout.origin !== window.location.origin || checkout.pathname.split('/').pop() !== 'purchaseprogrammevps.php') throw new Error('Invalid programme checkout route.');
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({type:'harvhubOpenVpsCheckout',category:'programme',url:checkout.href}, '*');
                return;
            }
            window.location.href = checkout.href;
        } catch (e) {
            if (typeof showVpsAlert === 'function') showVpsAlert('Unable to open programme VPS checkout. Please try again.', 'Error');
        }
    }

    function confirmModalAction(){
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
        } else if (['unfollow_vps','revoke_vps','restore_vps','cancel_vps_purchase','begin_purchase'].indexOf(pendingConfirmAction)!==-1) {
            var action=pendingConfirmAction,id=pendingConfirmId;
            var p=new URLSearchParams({csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
            if(action==='unfollow_vps'){p.set('unfollow_vps','1');p.set('follower_row_id',String(id));}
            if(action==='revoke_vps'){p.set('revoke_own_vps','1');p.set('programme_vps_id',String(id));}
            if(action==='restore_vps'){p.set('restore_own_vps','1');p.set('revocation_id',String(id));}
            if(action==='cancel_vps_purchase'){p.set('cancel_vps_purchase','1');p.set('request_id',String(id));}
            if(action==='begin_purchase'){
                p.set('csrf_token',<?= json_encode($catalogueCsrf) ?>);p.set('action','begin_purchase');p.set('vps_id',String(id));p.set('return_to','programme');
                fetch('purchaseprogrammevps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:p})
                .then(function(r){return r.json();}).then(function(d){btn.disabled=false;btn.textContent=originalText;if(!d.success){closeVpsConfirmModal();showVpsAlert(d.message||'Unable to start purchase.','Error');return;}openProgrammeVpsCheckout(d.redirect);})
                .catch(function(){btn.disabled=false;btn.textContent=originalText;closeVpsConfirmModal();showVpsAlert('Network error. Please try again.','Error');});return;
            }
            fetch('programme_vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:p})
            .then(function(r){return r.json();}).then(function(d){btn.disabled=false;btn.textContent=originalText;if(d.code==='broker_linked'){closeVpsConfirmModal();showProgrammeBrokerDisconnectNotice();return;}if(!d.success){showVpsAlert(d.message||'Unable to complete this action.','Error');return;}closeVpsConfirmModal();showVpsAlert(d.message||'Updated successfully.','Success');setTimeout(function(){window.location.reload();},700);})
            .catch(function(){btn.disabled=false;btn.textContent=originalText;showVpsAlert('Network error. Please try again.','Error');});
        } else {
            btn.disabled=false; btn.textContent=originalText; closeVpsConfirmModal();
        }
    }
    window.confirmModalAction = confirmModalAction;

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

    function loadProgrammeFollowerVps(){
        var c=document.getElementById('myProgrammeFollowerVpsContainer');if(!c||c.dataset.loaded==='1')return;
        c.innerHTML='<div class="vps-requests-loading">Loading VPS details…</div>';
        fetch('programme_vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'get_my_follower_owner=1'})
        .then(function(r){return r.json();}).then(function(data){c.dataset.loaded='1';if(!data||!data.success||!data.is_follower){c.innerHTML='';return;}renderProgrammeFollowerVps(c,data.owner,data.self);})
        .catch(function(){c.innerHTML='<div class="vps-empty-state"><p>Unable to load VPS details.</p></div>';});
    }
    window.loadProgrammeFollowerVps=loadProgrammeFollowerVps;
    function renderProgrammeFollowerVps(c,owner,self){
        var html='<article class="vps-owner-card"><div class="vps-owner-badge">VPS Owner</div><div class="vps-owner-name">'+escapeHtml(owner.username||'VPS Owner')+'</div>';
        html+='<p class="market-vps-description"><strong>You are hosted in this owner’s VPS as a follower</strong></p><h3>'+escapeHtml(owner.vps_name||'VPS')+'</h3><div class="vps-owner-meta">';
        html+='<div class="vps-owner-stat"><span class="vps-owner-stat-label">Proxy</span><span class="vps-owner-stat-value">'+escapeHtml(owner.server_location||'N/A')+'</span></div>';
        html+='<div class="vps-owner-stat"><span class="vps-owner-stat-label">Subscription Duration</span><span class="vps-owner-stat-value">'+escapeHtml(String(owner.subscription_duration||0))+' days</span></div>';
        html+='<div class="vps-owner-stat"><span class="vps-owner-stat-label">Total Followers</span><span class="vps-owner-stat-value">'+(parseInt(owner.total_followers,10)||0)+'</span></div></div>';
        html+='<div class="market-vps-card-foot"><button type="button" class="vps-btn-request" onclick="requestProgrammeUnfollowVps('+parseInt(self.follower_row_id,10)+',this)">Unfollow VPS</button></div></article>';c.innerHTML=html;
    }
    function showProgrammeBrokerDisconnectNotice(){showVpsAlert('Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.','Disconnect broker first');}
    function requestProgrammeUnfollowVps(rowId,button){
        var p=new URLSearchParams({check_vps_lifecycle_broker:'1',lifecycle_action:'unfollow',follower_row_id:String(rowId),csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
        fetch('programme_vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:p})
        .then(function(r){return r.json();}).then(function(d){if(!d.success){showVpsAlert(d.message||'Unable to continue.','Error');return;}if(d.broker_linked){showProgrammeBrokerDisconnectNotice();return;}openVpsConfirm({type:'unfollow_vps',id:rowId,title:'Unfollow VPS',message:'Are you sure you want to unfollow this VPS?',confirmText:'Yes, Unfollow'});})
        .catch(function(){showVpsAlert('Network error. Please try again.','Error');});
    }
    window.requestProgrammeUnfollowVps=requestProgrammeUnfollowVps;
    function handleProgrammeOwnerVpsOption(select,vpsId){var v=select.value;select.value='';if(v!=='revoke')return;var p=new URLSearchParams({check_vps_lifecycle_broker:'1',lifecycle_action:'revoke',programme_vps_id:String(vpsId),csrf_token:<?= json_encode($vpsLifecycleCsrf) ?>});
        fetch('programme_vps.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:p})
        .then(function(r){return r.json();}).then(function(d){if(!d.success){showVpsAlert(d.message||'Unable to continue.','Error');return;}if(d.broker_linked){showProgrammeBrokerDisconnectNotice();return;}openVpsConfirm({type:'revoke_vps',id:vpsId,title:'Revoke Programme VPS',message:'Are you sure you want to revoke your programme VPS? Your plan with this VPS will be discontinued after the two-day grace period. You can restore it before the period ends.',confirmText:'Yes, Revoke VPS'});})
        .catch(function(){showVpsAlert('Network error. Please try again.','Error');});
    }
    window.handleProgrammeOwnerVpsOption=handleProgrammeOwnerVpsOption;
    function handleProgrammeRestoreVpsOption(select,revocationId){var v=select.value;select.value='';if(v!=='restore')return;openVpsConfirm({type:'restore_vps',id:revocationId,title:'Restore Programme VPS',message:'Are you sure you want to restore this VPS and keep the programme plan active?',confirmText:'Yes, Restore VPS'});}
    window.handleProgrammeRestoreVpsOption=handleProgrammeRestoreVpsOption;
    function confirmCancelProgrammeVpsPurchase(id){openVpsConfirm({type:'cancel_vps_purchase',id:id,title:'Cancel purchase',message:'Are you sure you want to cancel this VPS purchase? This is available only before payment is submitted.',confirmText:'Yes, Cancel Purchase'});}
    window.confirmCancelProgrammeVpsPurchase=confirmCancelProgrammeVpsPurchase;
    function updateProgrammeVpsRevocationCountdowns(){document.querySelectorAll('[data-discontinue-epoch]').forEach(function(el){var ts=(parseInt(el.getAttribute('data-discontinue-epoch')||'0',10)||0)*1000;var d=ts-Date.now();if(!Number.isFinite(ts)||d<=0){el.textContent='processing';return;}var days=Math.floor(d/86400000);d%=86400000;var hours=Math.floor(d/3600000);d%=3600000;var mins=Math.floor(d/60000);el.textContent=String(days).padStart(2,'0')+'d '+String(hours).padStart(2,'0')+'h '+String(mins).padStart(2,'0')+'m';});}
    updateProgrammeVpsRevocationCountdowns();setInterval(updateProgrammeVpsRevocationCountdowns,15000);

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

<script>
function startProgrammeVpsPurchase(planId, button){
    window.openVpsConfirm({type:'begin_purchase',id:planId,title:'Purchase Programme VPS',message:'Create a VPS purchase request for this programme and continue to its separate checkout?',confirmText:'Continue to checkout'});
}
</script>

</body>
</html>