<?php
/** Shared user-side purchase identity, database and CSRF helper. */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
// Checkout and provisioning pages can contain payment and private setup inputs.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');
if (!isset($_SESSION['user_email'])) { header('Location: index.php'); exit; }
try {
    require_once __DIR__ . '/mysqlconnection.php';
    $purchasePdo = getAvailablePdoConnection();
    if (!$purchasePdo instanceof PDO) throw new RuntimeException('Database connection unavailable.');
    $purchasePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $purchasePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    require_once __DIR__ . '/serverapp_schema.php';
    serverAppEnsureConfigurationSchema($purchasePdo);
    require_once __DIR__ . '/server_vps_schema.php';
    serverVpsEnsureSchema($purchasePdo);
} catch (Throwable $e) {
    error_log('VPS purchase connection failed: '.$e->getMessage()); http_response_code(500); exit('Purchase service temporarily unavailable.');
}
$purchaseEmail = strtolower(trim((string)$_SESSION['user_email']));
$purchaseSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);
$purchaseUser = null;
if ($purchaseSubId > 0) {
    $q=$purchasePdo->prepare('SELECT * FROM harvhub WHERE sub_account_id=? AND LOWER(email)=? LIMIT 1');
    $q->execute([$purchaseSubId,$purchaseEmail]); $purchaseUser=$q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$purchaseUser) {
    $q=$purchasePdo->prepare('SELECT * FROM harvhub WHERE LOWER(email)=? AND COALESCE(is_main_account,0)=0 ORDER BY id ASC LIMIT 1');
    $q->execute([$purchaseEmail]); $purchaseUser=$q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$purchaseUser) {
    $q=$purchasePdo->prepare('SELECT * FROM harvhub WHERE LOWER(email)=? ORDER BY id ASC LIMIT 1');
    $q->execute([$purchaseEmail]); $purchaseUser=$q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$purchaseUser) { http_response_code(403); exit('Account not found.'); }
$purchaseUserId=(int)$purchaseUser['id'];
$purchaseSubId=(int)($purchaseUser['sub_account_id'] ?? $purchaseUserId);
if ($purchaseSubId<=0) $purchaseSubId=$purchaseUserId;
$purchaseMainId=(int)($purchaseUser['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id']=$purchaseSubId;
$_SESSION['active_main_account_id']=$purchaseMainId;
if (empty($_SESSION['vps_purchase_csrf'])) $_SESSION['vps_purchase_csrf']=bin2hex(random_bytes(32));
$purchaseCsrf=(string)$_SESSION['vps_purchase_csrf'];
$isPurchaseAjax=($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST' && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))==='xmlhttprequest';
function purchaseJson(array $payload,int $status=200):void {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function purchaseCheckCsrf():bool {$sent=(string)($_POST['csrf_token'] ?? '');return isset($_SESSION['vps_purchase_csrf'])&&$sent!==''&&hash_equals((string)$_SESSION['vps_purchase_csrf'],$sent);}
function purchaseUserDisplayName(array $row):string {foreach(['sub_account_name','fullname','username'] as $k){$v=trim((string)($row[$k] ?? ''));if($v!=='')return $v;}return 'Subaccount';}
function purchaseRefreshPlanFlag(PDO $pdo,int $planId):void {$q=$pdo->prepare('UPDATE server_available_vps SET buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests WHERE vps_id=? AND buy_request=1),1,0) WHERE id=?');$q->execute([$planId,$planId]);}
function purchaseBeginRequest(PDO $pdo,string $type,array $user,int $subId,int $planId,int $programmeId=0):int {
    if (!in_array($type, ['account','programme'], true)) throw new RuntimeException('Invalid VPS purchase category.');
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare("SELECT * FROM server_available_vps WHERE id=? AND status='published' FOR UPDATE");$q->execute([$planId]);$plan=$q->fetch(PDO::FETCH_ASSOC);
        if(!$plan)throw new RuntimeException('This VPS plan is not currently available.');
        $stock=(int)$plan['stock_quantity'];$allocated=(int)$plan['allocated_quantity'];
        if($stock>0&&$allocated>=$stock)throw new RuntimeException('This VPS plan is sold out.');
        if($type==='account') {
            $ownerLock=$pdo->prepare('SELECT id FROM harvhub WHERE id=? AND sub_account_id=? FOR UPDATE');$ownerLock->execute([(int)$user['id'],$subId]);
            if(!$ownerLock->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('The selected subaccount could not be verified. Reload the account and try again.');
            $q=$pdo->prepare('SELECT id FROM vps WHERE user_id=? AND sub_account_id=? LIMIT 1');$q->execute([(int)$user['id'],$subId]);
            if($q->fetch(PDO::FETCH_ASSOC))throw new RuntimeException('This subaccount already has a VPS registered.');
            $registered=$pdo->prepare("SELECT rv.id FROM server_registered_vps rv
                LEFT JOIN server_vps_purchase_requests r ON r.id=rv.purchase_request_id
                WHERE rv.request_type='account' AND rv.user_id=? AND rv.sub_account_id=?
                  AND (r.id IS NULL OR COALESCE(r.request_status,'') <> 'revoked_expired') LIMIT 1");$registered->execute([(int)$user['id'],$subId]);
            if($registered->fetchColumn())throw new RuntimeException('This subaccount already has a VPS purchase or allocation. Check My VPS or Purchase Pending before placing another request.');
            $where='request_type=\'account\' AND user_id=? AND sub_account_id=? AND programme_id IS NULL';$params=[(int)$user['id'],$subId];
        } else {
            if($programmeId<=0) throw new RuntimeException('Select a valid programme before requesting a VPS.');
            $ownerLock=$pdo->prepare('SELECT id FROM programme WHERE id=? AND userid=? FOR UPDATE');$ownerLock->execute([$programmeId,(int)$user['id']]);
            if(!$ownerLock->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('The selected programme does not belong to this subaccount.');
            $q=$pdo->prepare('SELECT id FROM programme_vps WHERE programme_id=? LIMIT 1');$q->execute([$programmeId]);
            if($q->fetch(PDO::FETCH_ASSOC))throw new RuntimeException('This programme already has a VPS registered.');
            $registered=$pdo->prepare("SELECT rv.id FROM server_registered_vps rv
                LEFT JOIN server_vps_purchase_requests r ON r.id=rv.purchase_request_id
                WHERE rv.request_type='programme' AND rv.programme_id=?
                  AND (r.id IS NULL OR COALESCE(r.request_status,'') <> 'revoked_expired') LIMIT 1");$registered->execute([$programmeId]);
            if($registered->fetchColumn())throw new RuntimeException('This programme already has a VPS purchase or allocation. Check My VPS or Purchase Pending before placing another request.');
            $where='request_type=\'programme\' AND programme_id=?';$params=[$programmeId];
        }
        $pending=$pdo->prepare("SELECT id FROM server_vps_purchase_requests WHERE $where AND payment_status IN ('awaiting_payment','payment-made') ORDER BY id DESC LIMIT 1");$pending->execute($params);$existing=(int)($pending->fetchColumn() ?: 0);
        if($existing>0){$pdo->commit();return $existing;}
        $name=purchaseUserDisplayName($user);$email=(string)($user['email'] ?? '');$amount=(float)$plan['price']+(float)$plan['setup_fee'];
        $ins=$pdo->prepare("INSERT INTO server_vps_purchase_requests (vps_id,request_type,user_id,sub_account_id,programme_id,request_name,request_email,buy_request,payment_status,request_status,amount,currency) VALUES (?,?,?,?,?,?,?,1,'awaiting_payment','awaiting_payment',?,?)");
        $ins->execute([$planId,$type,(int)$user['id'],$subId,$type==='programme'?$programmeId:null,$name,$email,$amount,(string)$plan['currency']]);
        $requestId=(int)$pdo->lastInsertId();$up=$pdo->prepare('UPDATE server_available_vps SET buy_request=1 WHERE id=?');$up->execute([$planId]);
        $pdo->commit();return $requestId;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function purchaseMarkPayment(PDO $pdo,int $requestId,string $type,int $userId,int $subId,int $programmeId,int $methodId,string $reference):void {
    if (!in_array($type, ['account','programme'], true)) throw new RuntimeException('Invalid VPS purchase category.');
    $payment=$pdo->prepare('SELECT id,method_name,account_or_address,additional_info,company_name FROM server_payment_methods WHERE id=? AND is_enabled=1 LIMIT 1');
    $payment->execute([$methodId]);
    $paymentRow=$payment->fetch(PDO::FETCH_ASSOC) ?: [];
    $methodName=trim((string)($paymentRow['method_name']??''));
    $address=trim((string)($paymentRow['account_or_address']??''));
    $company=trim((string)($paymentRow['company_name']??''));
    if($methodName==='' || $address==='') throw new RuntimeException('The selected payment method is not configured. Contact support.');
    if($reference==='') throw new RuntimeException('Enter the transaction reference or hash.');
    if(strlen($reference)>190) throw new RuntimeException('Transaction reference is too long.');

    if($type==='programme') {
        if($programmeId<=0) throw new RuntimeException('The selected programme could not be verified.');
        $where='request_type=\'programme\' AND programme_id=? AND user_id=? AND sub_account_id=?';
        $ownershipParams=[$programmeId,$userId,$subId];
    } else {
        $where='request_type=\'account\' AND user_id=? AND sub_account_id=? AND programme_id IS NULL';
        $ownershipParams=[$userId,$subId];
    }
    $sql="UPDATE server_vps_purchase_requests
          SET payment_status='payment-made',request_status='payment_made',payment_method=?,payment_network=?,payment_address_snapshot=?,payment_reference=?,payment_made_at=NOW()
          WHERE id=? AND {$where} AND payment_status='awaiting_payment' AND buy_request=1";
    $q=$pdo->prepare($sql);
    $q->execute(array_merge([$methodName,$company,$address,$reference,$requestId],$ownershipParams));
    if($q->rowCount()!==1) throw new RuntimeException('This purchase request is not awaiting payment or is no longer available.');
}

?>
