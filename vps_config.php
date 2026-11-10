<?php
/**
 * Server App VPS administration.
 * Published plans are public; registration/provisioning fields are only returned after
 * an administrator re-authenticates for a specific registered VPS row.
 */
require_once __DIR__ . '/serverapp_guard.php';
require_once __DIR__ . '/server_vps_security.php';

function vpsAdminColumns(PDO $pdo, string $table): array {
    $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]); return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
}
function vpsAdminTableExists(PDO $pdo,string $table):bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]); return (int)$q->fetchColumn()>0;
}
function vpsAdminRefreshBuyFlag(PDO $pdo,int $vpsId):void {
    $q=$pdo->prepare("UPDATE server_available_vps SET buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests r WHERE r.vps_id=? AND r.buy_request=1),1,0) WHERE id=?");
    $q->execute([$vpsId,$vpsId]);
}
function vpsAdminInsertProvision(PDO $pdo,string $table,array $values):int {
    if(!vpsAdminTableExists($pdo,$table)) throw new RuntimeException('The destination table '.$table.' is missing.');
    $columns=vpsAdminColumns($pdo,$table);$data=[];
    foreach($values as $column=>$value) if(isset($columns[$column])) $data[$column]=$value;
    if(!$data) throw new RuntimeException('The destination table does not contain the required VPS registration columns.');
    $names=array_keys($data);$sql='INSERT INTO `'.$table.'` (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
    $q=$pdo->prepare($sql);$q->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}
function vpsAdminDecryptPlanValue(array $plan,string $column):string {
    $encrypted=trim((string)($plan[$column] ?? ''));
    if($encrypted==='') return '';
    $value=serverVpsDecryptValue($encrypted);
    if($value===null) throw new RuntimeException('A private VPS field cannot be decrypted. Verify SERVER_VPS_ENCRYPTION_KEY and run the legacy VPS field conversion if this is an older record.');
    return $value;
}
function vpsAdminAllocatePurchase(PDO $pdo,int $requestId,string $adminId):array {
    $pdo->beginTransaction();
    try {
        $rq=$pdo->prepare('SELECT * FROM server_vps_purchase_requests WHERE id=? FOR UPDATE');$rq->execute([$requestId]);$request=$rq->fetch(PDO::FETCH_ASSOC);
        if(!$request) throw new RuntimeException('Purchase request not found.');
        if(($request['payment_status'] ?? '')!=='payment-made') throw new RuntimeException('Only payments marked as made can be confirmed.');
        $planQ=$pdo->prepare('SELECT * FROM server_available_vps WHERE id=? FOR UPDATE');$planQ->execute([(int)$request['vps_id']]);$plan=$planQ->fetch(PDO::FETCH_ASSOC);
        if(!$plan) throw new RuntimeException('The advertised VPS plan no longer exists.');
        $capacity=(int)($plan['stock_quantity'] ?? 0);$allocated=(int)($plan['allocated_quantity'] ?? 0);
        if($capacity>0 && $allocated >= $capacity) throw new RuntimeException('This plan has no unallocated stock.');

        $uid=(int)$request['user_id'];$subId=(int)$request['sub_account_id'];
        $duration=max(1,(int)($plan['subscription_duration_days'] ?? 30));
        $private=[
            'vps_ip_address'=>vpsAdminDecryptPlanValue($plan,'vps_ip_address_enc'),
            'vps_provider_login'=>vpsAdminDecryptPlanValue($plan,'vps_provider_login_enc'),
            'vps_provider_password'=>vpsAdminDecryptPlanValue($plan,'vps_provider_password_enc'),
            'computer_username'=>vpsAdminDecryptPlanValue($plan,'computer_username_enc'),
            'computer_password'=>vpsAdminDecryptPlanValue($plan,'computer_password_enc'),
            'rdp_password'=>vpsAdminDecryptPlanValue($plan,'rdp_password_enc')
        ];
        $location=(string)($plan['server_location'] ?? '');

        if(($request['request_type'] ?? '')==='account') {
            $ownerLock=$pdo->prepare('SELECT id FROM harvhub WHERE id=? AND COALESCE(sub_account_id,id)=? FOR UPDATE');$ownerLock->execute([$uid,$subId]);
            if(!$ownerLock->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('The target subaccount no longer exists or no longer matches this VPS request.');
            $columns=vpsAdminColumns($pdo,'vps');
            $existsSql='SELECT id FROM vps WHERE user_id=?'.(isset($columns['sub_account_id'])?' AND sub_account_id=?':'').' LIMIT 1 FOR UPDATE';
            $exists=$pdo->prepare($existsSql);$exists->execute(isset($columns['sub_account_id'])?[$uid,$subId]:[$uid]);
            if($exists->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('This subaccount already has a VPS registered. Review the existing VPS before allocating another.');
            $values=[
                'user_id'=>$uid,'sub_account_id'=>$subId,'server_location'=>$location,
                'subscription_duration'=>$duration,'subscription_start_date'=>date('Y-m-d'),
                'vps_ip_address'=>$private['vps_ip_address'],'vps_provider_login'=>$private['vps_provider_login'],
                'vps_provider_password'=>$private['vps_provider_password'],'computer_username'=>$private['computer_username'],
                'computer_password'=>$private['computer_password'],'rdp_password'=>$private['rdp_password'],'visibility'=>0
            ];
            $registrationId=vpsAdminInsertProvision($pdo,'vps',$values);
        } else {
            $programmeId=(int)($request['programme_id'] ?? 0);
            if($programmeId<=0) throw new RuntimeException('The programme reference is missing.');
            $pc=vpsAdminColumns($pdo,'programme');
            $programmeSql='SELECT id,userid'.(isset($pc['sub_account_id'])?',sub_account_id':'').' FROM programme WHERE id=? AND userid=? FOR UPDATE';
            $ownerLock=$pdo->prepare($programmeSql);$ownerLock->execute([$programmeId,$uid]);$programmeOwner=$ownerLock->fetch(PDO::FETCH_ASSOC);
            if(!$programmeOwner) throw new RuntimeException('The target programme no longer belongs to the recorded account.');
            if(isset($programmeOwner['sub_account_id']) && (int)$programmeOwner['sub_account_id']>0 && (int)$programmeOwner['sub_account_id']!==$subId) throw new RuntimeException('The target programme subaccount no longer matches this purchase request.');
            $ownerLock2=$pdo->prepare('SELECT id FROM harvhub WHERE id=? AND COALESCE(sub_account_id,id)=? FOR UPDATE');$ownerLock2->execute([$uid,$subId]);
            if(!$ownerLock2->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('The programme owner subaccount could not be verified.');
            $exists=$pdo->prepare('SELECT id FROM programme_vps WHERE programme_id=? LIMIT 1 FOR UPDATE');$exists->execute([$programmeId]);
            if($exists->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('This programme already has a VPS registered. Review the existing programme VPS before allocating another.');
            $values=[
                'programme_id'=>$programmeId,'user_id'=>$uid,'sub_account_id'=>$subId,
                'server_location'=>$location,'subscription_duration'=>$duration,
                'programme_vps_ip_address'=>$private['vps_ip_address'],'vps_ip_address'=>$private['vps_ip_address'],
                'vps_provider_login'=>$private['vps_provider_login'],'vps_provider_password'=>$private['vps_provider_password'],
                'computer_username'=>$private['computer_username'],'computer_password'=>$private['computer_password'],
                'rdp_password'=>$private['rdp_password'],'visibility'=>0
            ];
            $registrationId=vpsAdminInsertProvision($pdo,'programme_vps',$values);
        }
        if (empty($registrationId)) throw new RuntimeException('The VPS registration row could not be identified.');
        $up=$pdo->prepare("UPDATE server_vps_purchase_requests SET registration_id=?,payment_status='payment-confirmed',request_status='allocated',buy_request=0,payment_decided_at=NOW(),payment_decided_by=?,allocation_message='Registered from the published VPS plan' WHERE id=?");
        $up->execute([$registrationId,$adminId,$requestId]);
        $stock=$pdo->prepare("UPDATE server_available_vps SET allocated_quantity=allocated_quantity+1,status=IF(stock_quantity>0 AND allocated_quantity+1>=stock_quantity,'sold_out',status) WHERE id=?");
        $stock->execute([(int)$plan['id']]);
        vpsAdminRefreshBuyFlag($pdo,(int)$plan['id']);
        $pdo->commit();
        return ['success'=>true,'message'=>'Payment confirmed and VPS registered to the selected account or programme.'];
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function vpsAdminMaskValue($value): string { return trim((string)$value)===''?'Not configured':'••••••••'; }
function vpsAdminRegisteredRows(PDO $pdo,string $type):array {
    if($type==='accounts') {
        $vc=vpsAdminColumns($pdo,'vps');
        $subJoin=isset($vc['sub_account_id'])?' AND v.sub_account_id=r.sub_account_id':'';
        $safeCols=['id','server_location','subscription_duration','subscription_start_date','visibility','created_at','updated_at'];
        $extra='';foreach($safeCols as $col)if(isset($vc[$col]))$extra.=',v.`'.$col.'` AS `v_'.$col.'`';
        $sql="SELECT r.id AS request_id,r.vps_id,r.user_id,r.sub_account_id,r.request_name,r.request_email,r.amount,r.currency,r.payment_decided_at,r.payment_reference,v.id AS registration_id{$extra},a.product_name,a.provider_name,a.plan_name,a.plan_description,a.cpu_type,a.vcpu_count,a.ram_gb,a.storage_type,a.storage_gb,a.network_port_speed_mbps,a.data_transfer,a.server_location AS plan_location,a.region_code,a.operating_system,a.virtualization_type,a.billing_period,a.subscription_duration_days,a.ipv4_included,a.ipv6_included,a.backup_option,a.snapshot_count,a.price AS plan_price,a.currency AS plan_currency,a.setup_fee,h.fullname,h.username,h.sub_account_name,h.email FROM server_vps_purchase_requests r INNER JOIN vps v ON ((r.registration_id IS NOT NULL AND v.id=r.registration_id) OR (r.registration_id IS NULL AND v.user_id=r.user_id{$subJoin})) LEFT JOIN server_available_vps a ON a.id=r.vps_id LEFT JOIN harvhub h ON h.id=r.user_id WHERE r.request_type='account' AND r.payment_status='payment-confirmed' AND r.request_status='allocated' ORDER BY r.payment_decided_at DESC,r.id DESC LIMIT 500";
        $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){$row['target_type']='account';$row['target_name']=trim((string)($row['sub_account_name']??''))?:trim((string)($row['username']??''))?:trim((string)($row['fullname']??''))?:'Investor subaccount';}
        unset($row);return $rows;
    }
    $vc=vpsAdminColumns($pdo,'programme_vps');
    $safeCols=['id','server_location','subscription_duration','subscription_start_date','visibility','created_at','updated_at'];
    $extra='';foreach($safeCols as $col)if(isset($vc[$col]))$extra.=',v.`'.$col.'` AS `v_'.$col.'`';
    $sql="SELECT r.id AS request_id,r.vps_id,r.user_id,r.sub_account_id,r.programme_id,r.request_name,r.request_email,r.amount,r.currency,r.payment_decided_at,r.payment_reference,v.id AS registration_id{$extra},a.product_name,a.provider_name,a.plan_name,a.plan_description,a.cpu_type,a.vcpu_count,a.ram_gb,a.storage_type,a.storage_gb,a.network_port_speed_mbps,a.data_transfer,a.server_location AS plan_location,a.region_code,a.operating_system,a.virtualization_type,a.billing_period,a.subscription_duration_days,a.ipv4_included,a.ipv6_included,a.backup_option,a.snapshot_count,a.price AS plan_price,a.currency AS plan_currency,a.setup_fee,p.program_name,h.fullname,h.username,h.sub_account_name,h.email FROM server_vps_purchase_requests r INNER JOIN programme_vps v ON ((r.registration_id IS NOT NULL AND v.id=r.registration_id) OR (r.registration_id IS NULL AND v.programme_id=r.programme_id)) LEFT JOIN server_available_vps a ON a.id=r.vps_id LEFT JOIN programme p ON p.id=r.programme_id LEFT JOIN harvhub h ON h.id=r.user_id WHERE r.request_type='programme' AND r.payment_status='payment-confirmed' AND r.request_status='allocated' ORDER BY r.payment_decided_at DESC,r.id DESC LIMIT 500";
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){$row['target_type']='programme';$row['target_name']=trim((string)($row['program_name']??''))?:'Programme #'.(int)$row['programme_id'];}
    unset($row);return $rows;
}
function vpsAdminPrivateFields(PDO $pdo,string $type,int $registrationId):array {
    $table=$type==='accounts'?'vps':'programme_vps';
    $columns=vpsAdminColumns($pdo,$table);
    $select=[];
    foreach(['vps_ip_address','programme_vps_ip_address','vps_provider_login','vps_provider_password','computer_username','computer_password','rdp_password'] as $col)if(isset($columns[$col]))$select[]='`'.$col.'`';
    if(!$select)return [];
    $q=$pdo->prepare('SELECT '.implode(',',$select).' FROM `'.$table.'` WHERE id=? LIMIT 1');$q->execute([$registrationId]);
    return $q->fetch(PDO::FETCH_ASSOC)?:[];
}

if($isServerAppAjax) {
    $action=(string)($_POST['action'] ?? '');
    try {
        if($action==='vps_list_plans') {
            $q=$pdo->query("SELECT id,product_name,provider_name,plan_name,plan_description,cpu_type,vcpu_count,ram_gb,storage_type,storage_gb,network_port_speed_mbps,data_transfer,server_location,region_code,operating_system,virtualization_type,ipv4_included,ipv6_included,backup_option,snapshot_count,billing_period,subscription_duration_days,price,currency,setup_fee,stock_quantity,allocated_quantity,status,buy_request,created_at,updated_at FROM server_available_vps ORDER BY CASE status WHEN 'published' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END,id DESC");
            serverAppJson(['success'=>true,'plans'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if($action==='vps_payment_count') {
            $count=(int)$pdo->query("SELECT COUNT(*) FROM server_vps_purchase_requests WHERE payment_status='payment-made'")->fetchColumn();
            serverAppJson(['success'=>true,'count'=>$count]);
        }
        if($action==='vps_get_payments') {
            $q=$pdo->query("SELECT r.id,r.vps_id,r.request_type,r.user_id,r.sub_account_id,r.programme_id,r.request_name,r.request_email,r.payment_status,r.request_status,r.amount,r.currency,r.payment_method,r.payment_network,r.payment_reference,r.payment_made_at,r.allocation_message,r.created_at,v.product_name,v.plan_name,v.server_location,p.program_name FROM server_vps_purchase_requests r INNER JOIN server_available_vps v ON v.id=r.vps_id LEFT JOIN programme p ON p.id=r.programme_id WHERE r.payment_status IN ('payment-made','payment-failed','payment-confirmed') OR r.request_status='allocation_conflict' ORDER BY CASE r.payment_status WHEN 'payment-made' THEN 0 WHEN 'payment-failed' THEN 1 ELSE 2 END,r.created_at DESC LIMIT 500");
            serverAppJson(['success'=>true,'payments'=>$q->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if($action==='vps_get_plan') {
            $id=(int)($_POST['id'] ?? 0);
            $q=$pdo->prepare("SELECT id,product_name,provider_name,plan_name,plan_description,cpu_type,vcpu_count,ram_gb,storage_type,storage_gb,network_port_speed_mbps,data_transfer,server_location,region_code,operating_system,virtualization_type,ipv4_included,ipv6_included,backup_option,snapshot_count,billing_period,subscription_duration_days,price,currency,setup_fee,stock_quantity,allocated_quantity,status FROM server_available_vps WHERE id=?");
            $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            serverAppJson($row?['success'=>true,'plan'=>$row]:['success'=>false,'error'=>'Plan not found.']);
        }
        if($action==='vps_save_plan') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator credentials.']);
            $id=(int)($_POST['id']??0);$product=trim((string)($_POST['product_name']??''));$plan=trim((string)($_POST['plan_name']??''));$location=trim((string)($_POST['server_location']??''));
            $vcpu=(int)($_POST['vcpu_count']??0);$ram=(float)($_POST['ram_gb']??0);$storage=(float)($_POST['storage_gb']??0);$price=(float)($_POST['price']??-1);$stock=(int)($_POST['stock_quantity']??0);$duration=(int)($_POST['subscription_duration_days']??30);
            if($product===''||$plan===''||$location===''||$vcpu<1||$ram<=0||$storage<=0||$price<0||$stock<1||$duration<1)serverAppJson(['success'=>false,'error'=>'Product, plan, location, positive CPU/RAM/storage, price, stock and duration are required.']);
            $status=(string)($_POST['status']??'draft');if(!in_array($status,['published','paused'],true))$status='draft';
            $data=[
                'product_name'=>$product,'provider_name'=>trim((string)($_POST['provider_name']??'')),'plan_name'=>$plan,'plan_description'=>trim((string)($_POST['plan_description']??'')),
                'cpu_type'=>trim((string)($_POST['cpu_type']??'')),'vcpu_count'=>$vcpu,'ram_gb'=>$ram,'storage_type'=>trim((string)($_POST['storage_type']??'NVMe')),'storage_gb'=>$storage,
                'network_port_speed_mbps'=>($_POST['network_port_speed_mbps']??'')===''?null:max(0,(int)$_POST['network_port_speed_mbps']),
                'data_transfer'=>trim((string)($_POST['data_transfer']??'')),'server_location'=>$location,'region_code'=>trim((string)($_POST['region_code']??'')),
                'operating_system'=>trim((string)($_POST['operating_system']??'')),'virtualization_type'=>trim((string)($_POST['virtualization_type']??'')),
                'ipv4_included'=>!empty($_POST['ipv4_included'])?1:0,'ipv6_included'=>!empty($_POST['ipv6_included'])?1:0,'backup_option'=>trim((string)($_POST['backup_option']??'')),
                'snapshot_count'=>max(0,(int)($_POST['snapshot_count']??0)),'billing_period'=>trim((string)($_POST['billing_period']??'monthly')),
                'subscription_duration_days'=>$duration,'price'=>$price,'currency'=>strtoupper(substr(trim((string)($_POST['currency']??'USD')),0,3)),
                'setup_fee'=>max(0,(float)($_POST['setup_fee']??0)),'stock_quantity'=>$stock,'status'=>$status
            ];
            $privateMap=['vps_ip_address'=>'vps_ip_address_enc','vps_provider_login'=>'vps_provider_login_enc','vps_provider_password'=>'vps_provider_password_enc','computer_username'=>'computer_username_enc','computer_password'=>'computer_password_enc','rdp_password'=>'rdp_password_enc'];
            foreach($privateMap as $input=>$column){$plain=trim((string)($_POST[$input]??''));if($plain!==''){$encrypted=serverVpsEncryptValue($plain);if($encrypted===null)serverAppJson(['success'=>false,'error'=>'Set SERVER_VPS_ENCRYPTION_KEY (24+ characters) before saving private VPS information.']);$data[$column]=$encrypted;}}
            if(!empty($_POST['clear_private_fields']))foreach($privateMap as $column)$data[$column]=null;
            $availableColumns=vpsAdminColumns($pdo,'server_available_vps');foreach(array_keys($data) as $key)if(!isset($availableColumns[$key]))unset($data[$key]);
            if($id>0){$sets=[];foreach(array_keys($data) as $key)$sets[]='`'.$key.'`=?';$vals=array_values($data);$vals[]=$id;$q=$pdo->prepare('UPDATE server_available_vps SET '.implode(',',$sets).' WHERE id=?');$q->execute($vals);$exists=$pdo->prepare('SELECT id FROM server_available_vps WHERE id=?');$exists->execute([$id]);if(!$exists->fetch())serverAppJson(['success'=>false,'error'=>'Plan not found.']);}
            else{$data['created_by']=(string)($_SESSION['admin_login_id']??'server-admin');$cols=array_keys($data);$q=$pdo->prepare('INSERT INTO server_available_vps (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');$q->execute(array_values($data));$id=(int)$pdo->lastInsertId();}
            serverAppJson(['success'=>true,'id'=>$id,'message'=>$status==='published'?'VPS plan published.':'VPS plan saved as draft.']);
        }
        if($action==='vps_set_plan_status') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator credentials.']);
            $id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'paused');if(!in_array($status,['published','paused','draft'],true))serverAppJson(['success'=>false,'error'=>'Invalid plan status.']);
            $q=$pdo->prepare('UPDATE server_available_vps SET status=? WHERE id=? AND (status<>\'sold_out\' OR ?<>\'published\')');$q->execute([$status,$id,$status]);serverAppJson(['success'=>true]);
        }
        if($action==='vps_decide_payment') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            $login=(string)($_POST['login_id']??'');$password=(string)($_POST['admin_password']??'');
            if(!serverAppVerifyAdmin($pdo,$login,$password))serverAppJson(['success'=>false,'error'=>'Invalid administrator credentials.']);
            $requestId=(int)($_POST['request_id']??0);$decision=(string)($_POST['decision']??'');if($requestId<=0||!in_array($decision,['confirm','fail'],true))serverAppJson(['success'=>false,'error'=>'Invalid payment decision.']);
            if($decision==='confirm'){
                try{serverAppJson(vpsAdminAllocatePurchase($pdo,$requestId,$login));}catch(Throwable $e){try{$q=$pdo->prepare("UPDATE server_vps_purchase_requests SET request_status='allocation_conflict',allocation_message=? WHERE id=? AND payment_status='payment-made'");$q->execute([substr($e->getMessage(),0,240),$requestId]);}catch(Throwable $ignored){}serverAppJson(['success'=>false,'error'=>$e->getMessage()]);}
            }
            $q=$pdo->prepare("UPDATE server_vps_purchase_requests SET payment_status='payment-failed',request_status='failed',buy_request=0,payment_decided_at=NOW(),payment_decided_by=?,allocation_message='Payment marked as failed by administrator' WHERE id=? AND payment_status='payment-made'");$q->execute([$login,$requestId]);
            $planQ=$pdo->prepare('SELECT vps_id FROM server_vps_purchase_requests WHERE id=?');$planQ->execute([$requestId]);$pid=(int)$planQ->fetchColumn();if($pid>0)vpsAdminRefreshBuyFlag($pdo,$pid);
            serverAppJson(['success'=>true,'message'=>'Payment marked as failed.']);
        }
        if($action==='vps_migrate_legacy_private_fields') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator password.']);
            $catalogColumns=vpsAdminColumns($pdo,'server_available_vps');
            $legacyColumn=isset($catalogColumns['private_payload_encrypted']);
            $map=['vps_ip_address'=>'vps_ip_address_enc','vps_provider_login'=>'vps_provider_login_enc','vps_provider_password'=>'vps_provider_password_enc','computer_username'=>'computer_username_enc','computer_password'=>'computer_password_enc','rdp_password'=>'rdp_password_enc'];
            $availableMap=[];
            foreach($map as $legacyName=>$columnName) if(isset($catalogColumns[$columnName])) $availableMap[$legacyName]=$columnName;
            if(!$availableMap) serverAppJson(['success'=>false,'error'=>'The VPS catalogue does not contain the individual encrypted provisioning columns. Run the VPS database migration first.']);
            $select=['id'];if($legacyColumn)$select[]='private_payload_encrypted';foreach($availableMap as $columnName)$select[]=$columnName;
            $rows=$pdo->query('SELECT `'.implode('`,`',$select).'` FROM server_available_vps ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
            $pdo->beginTransaction();$migrated=0;$convertedValues=0;
            try{
                foreach($rows as $row){
                    $legacy=[];
                    if($legacyColumn && trim((string)($row['private_payload_encrypted']??''))!==''){
                        $legacy=serverVpsDecryptLegacyPayload((string)$row['private_payload_encrypted']);
                        if(!$legacy) throw new RuntimeException('Legacy VPS plan #'.(int)$row['id'].' cannot be decrypted. Check SERVER_VPS_ENCRYPTION_KEY before retrying.');
                    }
                    $sets=[];$values=[];$changed=false;
                    foreach($availableMap as $legacyName=>$columnName){
                        $encrypted=trim((string)($row[$columnName]??''));$plain=null;
                        if($encrypted!==''){
                            $plain=serverVpsNormalizeLegacyEncryptedValue($encrypted);
                            if($plain===null) throw new RuntimeException('VPS plan #'.(int)$row['id'].' field '.$legacyName.' cannot be decrypted. Check SERVER_VPS_ENCRYPTION_KEY.');
                        }
                        if(($plain===null||$plain==='') && array_key_exists($legacyName,$legacy)) $plain=trim((string)$legacy[$legacyName]);
                        if($plain!==null && $plain!==''){
                            $fresh=serverVpsEncryptValue($plain);
                            if($fresh===null) throw new RuntimeException('Set SERVER_VPS_ENCRYPTION_KEY before converting VPS provisioning values.');
                            $sets[]='`'.$columnName.'`=?';$values[]=$fresh;$changed=true;$convertedValues++;
                        }
                    }
                    if($legacyColumn){$sets[]='`private_payload_encrypted`=NULL';$changed=true;}
                    if($sets){$values[]=(int)$row['id'];$q=$pdo->prepare('UPDATE server_available_vps SET '.implode(',',$sets).' WHERE id=?');$q->execute($values);}
                    if($changed)$migrated++;
                }
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            // Retire the old aggregate field only after every row has been converted successfully.
            if($legacyColumn){
                try{$pdo->exec('ALTER TABLE server_available_vps DROP COLUMN private_payload_encrypted');}
                catch(Throwable $e){error_log('Could not remove server_available_vps.private_payload_encrypted: '.$e->getMessage());throw new RuntimeException('All legacy VPS values were converted, but the database host blocked removal of private_payload_encrypted. Remove that obsolete column manually after taking a backup.');}
            }
            serverAppJson(['success'=>true,'migrated'=>$migrated,'converted_values'=>$convertedValues,'message'=>$migrated.' VPS plan record(s) converted to separate raw encrypted fields. The old aggregate payload column has been removed where it existed.']);
        }
        if($action==='vps_get_registered') {
            $type=(string)($_POST['target_type']??'accounts');if(!in_array($type,['accounts','programmes'],true))serverAppJson(['success'=>false,'error'=>'Invalid registered VPS type.']);
            if(!vpsAdminTableExists($pdo,'server_vps_purchase_requests')||!vpsAdminTableExists($pdo,$type==='accounts'?'vps':'programme_vps'))serverAppJson(['success'=>true,'rows'=>[]]);
            serverAppJson(['success'=>true,'rows'=>vpsAdminRegisteredRows($pdo,$type)]);
        }
        if($action==='vps_reveal_registered') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator password.']);
            $type=(string)($_POST['target_type']??'');$regId=(int)($_POST['registration_id']??0);$requestId=(int)($_POST['request_id']??0);
            if(!in_array($type,['accounts','programmes'],true)||$regId<=0||$requestId<=0)serverAppJson(['success'=>false,'error'=>'Invalid registered VPS selection.']);
            if($type==='accounts'){
                $vc=vpsAdminColumns($pdo,'vps');$subJoin=isset($vc['sub_account_id'])?' AND v.sub_account_id=r.sub_account_id':'';
                $q=$pdo->prepare("SELECT v.id FROM server_vps_purchase_requests r INNER JOIN vps v ON v.id=? AND ((r.registration_id IS NOT NULL AND v.id=r.registration_id) OR (r.registration_id IS NULL AND v.user_id=r.user_id{$subJoin})) WHERE r.id=? AND r.request_type='account' AND r.payment_status='payment-confirmed' AND r.request_status='allocated'");$q->execute([$regId,$requestId]);
                if(!$q->fetch())serverAppJson(['success'=>false,'error'=>'The selected registered VPS could not be verified.']);
            }else{
                $q=$pdo->prepare("SELECT v.id FROM server_vps_purchase_requests r INNER JOIN programme_vps v ON v.id=? AND ((r.registration_id IS NOT NULL AND v.id=r.registration_id) OR (r.registration_id IS NULL AND v.programme_id=r.programme_id)) WHERE r.id=? AND r.request_type='programme' AND r.payment_status='payment-confirmed' AND r.request_status='allocated'");$q->execute([$regId,$requestId]);
                if(!$q->fetch())serverAppJson(['success'=>false,'error'=>'The selected programme VPS could not be verified.']);
            }
            $private=vpsAdminPrivateFields($pdo,$type,$regId);
            serverAppJson(['success'=>true,'private'=>$private,'message'=>'Sensitive registration details unlocked for this request.']);
        }
        serverAppJson(['success'=>false,'error'=>'Unknown VPS administration action.'],400);
    } catch(Throwable $e) {
        error_log('VPS admin action failed: '.$e->getMessage());
        serverAppJson(['success'=>false,'error'=>'The VPS operation could not be completed. Check the VPS migrations and application logs.'],500);
    }
}
?>
<!doctype html>
<html lang="en" data-theme="<?= serverAppH($serverAppTheme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>VPS Management</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<?php require_once __DIR__ . '/server_feature_style.php'; ?>
<style>
.vps-page{max-width:1500px;margin:0 auto;padding:2px 0 30px;color:var(--text-color)}.vps-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:18px}.vps-head h1{margin:0;font-size:24px;color:var(--header-color)}.vps-head p{margin:5px 0 0;max-width:850px;color:var(--text-muted)}.vps-tabs{display:flex;gap:7px;overflow:auto;padding:6px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;margin-bottom:16px}.vps-tab{white-space:nowrap;border:0;border-radius:8px;padding:10px 14px;background:transparent;color:var(--text-color);font-weight:750;cursor:pointer}.vps-tab.active{background:var(--accent-color);color:#fff}.vps-tab .count{display:inline-block;margin-left:5px;padding:1px 6px;border-radius:20px;background:rgba(120,130,145,.2);font-size:11px}.vps-panel{display:none}.vps-panel.active{display:block}.vps-card{background:var(--container-bg);border:1px solid var(--border-color);border-radius:14px;overflow:hidden;margin-bottom:14px;box-shadow:0 5px 18px var(--shadow-color)}.vps-card-head{padding:15px 18px;border-bottom:1px solid var(--border-color)}.vps-card-head h2{margin:0;font-size:17px;color:var(--header-color)}.vps-card-head p{margin:4px 0 0;color:var(--text-muted);font-size:12px}.vps-card-body{padding:17px}.vps-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.vps-field{display:grid;gap:6px}.vps-field.wide{grid-column:1/-1}.vps-field label{font-size:12px;font-weight:750;color:var(--text-muted)}.vps-field input,.vps-field select,.vps-field textarea,.vps-search,.vps-modal input{width:100%;border:1px solid var(--border-color);border-radius:9px;padding:10px 12px;background:var(--input-bg);color:var(--text-color);font:inherit}.vps-field textarea{min-height:75px;resize:vertical}.vps-checks{display:flex;flex-wrap:wrap;gap:16px;padding:7px 0}.vps-checks label{display:flex;align-items:center;gap:7px}.vps-actions{display:flex;flex-wrap:wrap;gap:7px}.vps-btn{border:1px solid var(--border-color);border-radius:9px;background:var(--accent-color);color:#fff;padding:9px 13px;font-weight:750;cursor:pointer}.vps-btn.secondary{background:var(--bg-secondary);color:var(--text-color)}.vps-btn.danger{background:#b83232;border-color:#b83232;color:#fff}.vps-btn:disabled{opacity:.55;cursor:not-allowed}.vps-table-wrap{overflow:auto}.vps-table{width:100%;border-collapse:collapse;min-width:1050px}.vps-table th,.vps-table td{padding:11px 12px;text-align:left;vertical-align:top;border-bottom:1px solid var(--border-color)}.vps-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);background:var(--table-header-bg)}.vps-meta{display:block;font-size:12px;color:var(--text-muted);margin-top:3px}.vps-status{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--bg-tertiary);font-size:11px;font-weight:800}.vps-status.good{background:#dff4e8;color:#12623d}.vps-status.pending{background:#fff0d7;color:#805400}.vps-status.failed{background:#ffe3e3;color:#a52626}.vps-specs{display:flex;gap:7px;flex-wrap:wrap}.vps-specs span{padding:4px 7px;border-radius:7px;background:var(--bg-tertiary);font-size:11px}.vps-empty{padding:42px 18px;text-align:center;color:var(--text-muted)}.vps-notice{display:none;padding:11px 13px;border-radius:9px;margin-bottom:12px;background:var(--bg-tertiary);border:1px solid var(--border-color)}.vps-notice.show{display:block}.vps-notice.error{border-color:#b83232;color:#ba3e3e}.vps-subtabs{display:flex;gap:8px;margin:0 0 13px}.vps-subtab{border:1px solid var(--border-color);border-radius:9px;background:var(--bg-secondary);color:var(--text-color);padding:9px 13px;font-weight:750;cursor:pointer}.vps-subtab.active{background:var(--accent-color);color:#fff}.vps-modal-backdrop{position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;padding:18px}.vps-modal-backdrop.open{display:flex}.vps-modal{width:min(680px,100%);max-height:90vh;overflow:auto;background:var(--container-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:15px;padding:20px;box-shadow:0 15px 60px rgba(0,0,0,.35)}.vps-modal h3{margin:0 0 6px}.vps-modal p{color:var(--text-muted);font-size:13px}.vps-secret-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.vps-secret{padding:10px;border:1px solid var(--border-color);border-radius:9px;overflow-wrap:anywhere}.vps-secret small{display:block;color:var(--text-muted);font-weight:750;margin-bottom:5px}.vps-lock{font-family:monospace;letter-spacing:.12em;color:var(--text-muted)}#adminAuthError{color:var(--danger-color);font-size:13px;margin-top:9px;overflow-wrap:anywhere}.vps-footnote{padding:12px 17px;color:var(--text-muted);font-size:12px;border-top:1px solid var(--border-color)}
@media(max-width:950px){.vps-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vps-head{flex-direction:column}}
@media(max-width:620px){.vps-grid{grid-template-columns:1fr}.vps-secret-list{grid-template-columns:1fr}.vps-card-body{padding:12px}}
@media(max-width:560px){body{padding:8px}.vps-page{width:100%}.vps-head h1{font-size:20px}.vps-tabs{gap:5px;overflow-x:auto;-webkit-overflow-scrolling:touch}.vps-tab{flex:0 0 auto;font-size:11px;padding:9px 10px}.vps-card-head,.vps-card-body{padding:12px}.vps-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}.vps-table{min-width:1050px}.vps-modal-backdrop{padding:10px;align-items:flex-end}.vps-modal{width:100%;max-width:100%;max-height:90dvh;overflow:auto;border-radius:16px 16px 0 0}}
</style>
</head>
<body><main class="server-feature-wrap"><div class="vps-page">
<header class="vps-head"><div><h1>Virtual Private Servers</h1><p>Publish VPS plans, review purchase submissions, and inspect registered account/programme allocations. Private IP addresses and credentials are never included in normal table responses.</p></div></header>
<div class="vps-notice" id="vpsNotice"></div>
<nav class="vps-tabs"><button class="vps-tab active" data-tab="published" onclick="VPS.switchTab('published')">Published VPS</button><button class="vps-tab" data-tab="advertise" onclick="VPS.switchTab('advertise')">Advertise VPS</button><button class="vps-tab" data-tab="payments" onclick="VPS.switchTab('payments')">VPS Payments <span class="count" id="paymentCount">0</span></button><button class="vps-tab" data-tab="registered" onclick="VPS.switchTab('registered')">Registered VPS</button></nav>
<section class="vps-panel active" id="panel-published"><div class="vps-card"><div class="vps-card-head"><h2>Published and saved VPS plans</h2><p>Published plans are available to investor subaccounts and trader programmes. Draft and paused plans remain private.</p></div><div class="vps-table-wrap" id="plansTable"><div class="vps-empty">Loading VPS plans…</div></div></div></section>
<section class="vps-panel" id="panel-advertise"><div class="vps-card"><div class="vps-card-head"><h2 id="planFormTitle">Advertise a VPS plan</h2><p>Enter published hardware, network, location, operating system, billing and price information. The private provisioning values are encrypted before being stored.</p></div><div class="vps-card-body"><form id="planForm"><input type="hidden" id="planId"><div class="vps-grid">
<div class="vps-field"><label>Product / VPS family *</label><input id="productName" required maxlength="150" placeholder="Cloud VPS"></div><div class="vps-field"><label>Provider name</label><input id="providerName" maxlength="120" placeholder="Provider"></div><div class="vps-field"><label>Plan name *</label><input id="planName" required maxlength="120" placeholder="VPS 4"></div>
<div class="vps-field wide"><label>Plan description</label><textarea id="planDescription" placeholder="Short public description"></textarea></div><div class="vps-field"><label>CPU / processor</label><input id="cpuType" placeholder="AMD EPYC / Intel Xeon"></div><div class="vps-field"><label>vCPU cores *</label><input id="vcpu" type="number" min="1" required value="2"></div><div class="vps-field"><label>RAM (GB) *</label><input id="ram" type="number" min="0.1" step="0.1" required value="4"></div>
<div class="vps-field"><label>Storage type</label><select id="storageType"><option>NVMe</option><option>SSD</option><option>HDD</option></select></div><div class="vps-field"><label>Storage capacity (GB) *</label><input id="storage" type="number" min="1" step="1" required value="80"></div><div class="vps-field"><label>Network port speed (Mbps)</label><input id="portSpeed" type="number" min="0" placeholder="1000"></div>
<div class="vps-field"><label>Data transfer allowance</label><input id="transfer" placeholder="Unlimited / 10 TB"></div><div class="vps-field"><label>Server location *</label><input id="location" required placeholder="London, UK"></div><div class="vps-field"><label>Region code</label><input id="region" placeholder="uk-lon-1"></div>
<div class="vps-field"><label>Operating system</label><input id="os" placeholder="Windows Server 2022"></div><div class="vps-field"><label>Virtualization type</label><input id="virtualization" placeholder="KVM"></div><div class="vps-field"><label>Backup option</label><input id="backup" placeholder="Daily backup / not included"></div>
<div class="vps-field"><label>Snapshots included</label><input id="snapshots" type="number" min="0" value="0"></div><div class="vps-field"><label>Billing period</label><select id="billing"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semiannual">Semi-annual</option><option value="annual">Annual</option><option value="one-time">One-time</option></select></div><div class="vps-field"><label>Subscription duration (days) *</label><input id="duration" type="number" min="1" value="30" required></div>
<div class="vps-field"><label>Price *</label><input id="price" type="number" min="0" step="0.01" required value="0"></div><div class="vps-field"><label>Currency</label><select id="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>NGN</option></select></div><div class="vps-field"><label>Setup fee</label><input id="setupFee" type="number" min="0" step="0.01" value="0"></div>
<div class="vps-field"><label>Stock quantity *</label><input id="stock" type="number" min="1" required value="1"></div><div class="vps-field"><label>Publication status</label><select id="planStatus"><option value="published">Published</option><option value="draft">Save as draft</option><option value="paused">Paused</option></select></div><div class="vps-field"><label>IPv4 included</label><div class="vps-checks"><label><input type="checkbox" id="ipv4" checked> Yes</label></div></div><div class="vps-field"><label>IPv6 included</label><div class="vps-checks"><label><input type="checkbox" id="ipv6"> Yes</label></div></div>
</div><h3 style="margin:24px 0 8px">Private provisioning details (server-side only)</h3><p style="margin:0 0 12px;color:var(--text-muted);font-size:12px">These values are not part of the public VPS advertisement. Leave blank when editing to retain the existing encrypted value. They are copied to the registered VPS record after payment confirmation.</p><div style="margin:0 0 14px"><button type="button" class="vps-btn secondary" onclick="VPS.migrateLegacy()">Convert older encrypted provisioning records to individual fields</button></div><div class="vps-grid">
<div class="vps-field"><label>VPS IP address</label><input id="privateIp" autocomplete="off" placeholder="Private IP address"></div><div class="vps-field"><label>Provider login / account ID</label><input id="providerLogin" autocomplete="off"></div><div class="vps-field"><label>Provider password / API secret</label><input id="providerPassword" type="password" autocomplete="new-password"></div><div class="vps-field"><label>Computer username</label><input id="computerUsername" autocomplete="off"></div><div class="vps-field"><label>Computer password</label><input id="computerPassword" type="password" autocomplete="new-password"></div><div class="vps-field"><label>RDP password</label><input id="rdpPassword" type="password" autocomplete="new-password"></div></div><div class="vps-checks"><label><input type="checkbox" id="clearPrivate"> Clear all saved private provisioning values</label></div>
<div class="vps-actions" style="margin-top:14px"><button type="submit" class="vps-btn">Save VPS Plan</button></div></form></div></div></section>
<section class="vps-panel" id="panel-payments"><div class="vps-card"><div class="vps-card-head"><h2>VPS payment submissions</h2><p>Confirm only after verifying the payment. Confirmation creates the corresponding registered VPS record.</p></div><div class="vps-table-wrap" id="paymentsTable"><div class="vps-empty">Loading payment requests…</div></div></div></section>
<section class="vps-panel" id="panel-registered"><div class="vps-card"><div class="vps-card-head"><h2>Registered VPS allocations</h2><p>Only successfully confirmed and allocated account and programme requests appear here. Sensitive fields remain server-side until re-authenticated.</p></div><div class="vps-card-body" style="padding-bottom:0"><div class="vps-subtabs"><button class="vps-subtab active" data-kind="accounts" onclick="VPS.switchRegistered('accounts')">Accounts</button><button class="vps-subtab" data-kind="programmes" onclick="VPS.switchRegistered('programmes')">Programmes</button></div></div><div class="vps-table-wrap" id="registeredTable"><div class="vps-empty">Loading registered VPS…</div></div></div></section>
</div></main>
<div class="vps-modal-backdrop" id="secretModal"><div class="vps-modal"><h3>Registered VPS private details</h3><p>Private IP and credential values are hidden until you verify administrator credentials for this specific registration.</p><div id="secretResult"></div><div class="vps-actions" style="margin-top:16px"><button type="button" class="vps-btn" onclick="VPS.unlockSecrets()">Authenticate and View</button><button type="button" class="vps-btn secondary" onclick="VPS.closeSecrets()">Close</button></div></div></div>
<div class="vps-modal-backdrop" id="adminAuthModal"><div class="vps-modal"><h3 id="adminAuthTitle">Administrator verification</h3><p id="adminAuthMessage">Enter your administrator login ID and password to continue.</p><div class="vps-field"><label for="adminAuthLogin">Administrator login ID</label><input id="adminAuthLogin" type="text" autocomplete="off" value=""></div><div class="vps-field" style="margin-top:10px"><label for="adminAuthPassword">Administrator password</label><input id="adminAuthPassword" type="password" autocomplete="current-password" value=""></div><div id="adminAuthError" class="vps-error"></div><div class="vps-actions" style="margin-top:16px"><button type="button" class="vps-btn" onclick="VPS.confirmAdminAuth()">Verify</button><button type="button" class="vps-btn secondary" onclick="VPS.cancelAdminAuth()">Cancel</button></div></div></div>
<script>
const VPS={csrf:<?= json_encode($serverAppCsrf) ?>,registeredKind:'accounts',revealTarget:null,plans:[],authResolver:null,
async api(action,data={}){const body=new URLSearchParams({action,csrf_token:this.csrf,...data});const response=await fetch('vps_config.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body});if(response.status===419){location.reload();throw new Error('Form expired. The page is refreshing.');}const result=await response.json();if(!result.success)throw new Error(result.error||'Request failed.');return result;},
requestAdminAuth(title,message){return new Promise((resolve,reject)=>{this.authResolver={resolve,reject};document.getElementById('adminAuthTitle').textContent=title||'Administrator verification';document.getElementById('adminAuthMessage').textContent=message||'Enter administrator credentials to continue.';document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';document.getElementById('adminAuthError').textContent='';document.getElementById('adminAuthModal').classList.add('open');setTimeout(()=>document.getElementById('adminAuthLogin').focus(),80);});},
confirmAdminAuth(){const login=document.getElementById('adminAuthLogin').value.trim();const password=document.getElementById('adminAuthPassword').value;const error=document.getElementById('adminAuthError');if(!login||!password){error.textContent='Enter both administrator login ID and password.';return;}const pending=this.authResolver;this.authResolver=null;document.getElementById('adminAuthModal').classList.remove('open');document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';if(pending)pending.resolve({login_id:login,admin_password:password});},
cancelAdminAuth(){const pending=this.authResolver;this.authResolver=null;document.getElementById('adminAuthModal').classList.remove('open');document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';if(pending)pending.reject(new Error('Administrator verification cancelled.'));},
notice(message,ok=true){const el=document.getElementById('vpsNotice');el.textContent=message;el.className='vps-notice show'+(ok?'':' error');},
esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));},money(value,currency){return this.esc(currency||'USD')+' '+Number(value||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});},
switchTab(tab){document.querySelectorAll('.vps-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));document.querySelectorAll('.vps-panel').forEach(p=>p.classList.toggle('active',p.id==='panel-'+tab));if(tab==='published')this.loadPlans();if(tab==='payments'){this.loadPayments();this.loadCount();}if(tab==='registered')this.loadRegistered();},
switchRegistered(kind){this.registeredKind=kind;document.querySelectorAll('.vps-subtab').forEach(b=>b.classList.toggle('active',b.dataset.kind===kind));this.loadRegistered();},
async loadCount(){try{const d=await this.api('vps_payment_count');document.getElementById('paymentCount').textContent=d.count;}catch(e){}},
async loadPlans(){const target=document.getElementById('plansTable');try{const d=await this.api('vps_list_plans');this.plans=d.plans||[];if(!this.plans.length){target.innerHTML='<div class="vps-empty">No VPS plans have been advertised yet.</div>';return;}let html='<table class="vps-table"><thead><tr><th>Product / Plan</th><th>Resources</th><th>Network and OS</th><th>Location</th><th>Billing</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>';this.plans.forEach(p=>{const specs=`<div class="vps-specs"><span>${this.esc(p.vcpu_count)} vCPU</span><span>${this.esc(p.ram_gb)} GB RAM</span><span>${this.esc(p.storage_gb)} GB ${this.esc(p.storage_type)}</span></div>`;const status=this.esc(p.status);html+=`<tr><td><b>${this.esc(p.product_name)}</b><span class="vps-meta">${this.esc(p.plan_name)} · ${this.esc(p.provider_name||'Provider not specified')}</span></td><td>${specs}</td><td>${this.esc(p.network_port_speed_mbps||'—')} Mbps<span class="vps-meta">${this.esc(p.data_transfer||'Transfer not specified')} · ${this.esc(p.operating_system||'OS not specified')}</span></td><td>${this.esc(p.server_location)}<span class="vps-meta">${this.esc(p.region_code||'')}</span></td><td>${this.esc(p.billing_period)}<span class="vps-meta">${this.esc(p.subscription_duration_days)} days</span></td><td><b>${this.money(p.price,p.currency)}</b><span class="vps-meta">Setup ${this.money(p.setup_fee,p.currency)}</span></td><td>${this.esc(p.allocated_quantity)}/${this.esc(p.stock_quantity)}<span class="vps-meta">Buy requests: ${this.esc(p.buy_request)}</span></td><td><span class="vps-status">${status}</span></td><td><div class="vps-actions"><button class="vps-btn secondary" onclick="VPS.editPlan(${p.id})">Edit</button>${p.status!=='sold_out'?`<button class="vps-btn secondary" onclick="VPS.setStatus(${p.id},'${p.status==='published'?'paused':'published'}')">${p.status==='published'?'Pause':'Publish'}</button>`:''}</div></td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load plans. Run server_available_vps.sql first.</div>';this.notice(e.message,false);}},
async editPlan(id){try{const d=await this.api('vps_get_plan',{id});const p=d.plan;const map={planId:'id',productName:'product_name',providerName:'provider_name',planName:'plan_name',planDescription:'plan_description',cpuType:'cpu_type',vcpu:'vcpu_count',ram:'ram_gb',storageType:'storage_type',storage:'storage_gb',portSpeed:'network_port_speed_mbps',transfer:'data_transfer',location:'server_location',region:'region_code',os:'operating_system',virtualization:'virtualization_type',backup:'backup_option',snapshots:'snapshot_count',billing:'billing_period',duration:'subscription_duration_days',price:'price',currency:'currency',setupFee:'setup_fee',stock:'stock_quantity',planStatus:'status'};Object.entries(map).forEach(([element,key])=>{const node=document.getElementById(element);if(node)node.value=p[key]??'';});document.getElementById('ipv4').checked=!!Number(p.ipv4_included);document.getElementById('ipv6').checked=!!Number(p.ipv6_included);['privateIp','providerLogin','providerPassword','computerUsername','computerPassword','rdpPassword'].forEach(id=>document.getElementById(id).value='');document.getElementById('clearPrivate').checked=false;document.getElementById('planFormTitle').textContent='Edit VPS plan #'+id;this.switchTab('advertise');}catch(e){this.notice(e.message,false);}},
async savePlan(ev){ev.preventDefault();const data={id:document.getElementById('planId').value,product_name:document.getElementById('productName').value,provider_name:document.getElementById('providerName').value,plan_name:document.getElementById('planName').value,plan_description:document.getElementById('planDescription').value,cpu_type:document.getElementById('cpuType').value,vcpu_count:document.getElementById('vcpu').value,ram_gb:document.getElementById('ram').value,storage_type:document.getElementById('storageType').value,storage_gb:document.getElementById('storage').value,network_port_speed_mbps:document.getElementById('portSpeed').value,data_transfer:document.getElementById('transfer').value,server_location:document.getElementById('location').value,region_code:document.getElementById('region').value,operating_system:document.getElementById('os').value,virtualization_type:document.getElementById('virtualization').value,backup_option:document.getElementById('backup').value,snapshot_count:document.getElementById('snapshots').value,billing_period:document.getElementById('billing').value,subscription_duration_days:document.getElementById('duration').value,price:document.getElementById('price').value,currency:document.getElementById('currency').value,setup_fee:document.getElementById('setupFee').value,stock_quantity:document.getElementById('stock').value,ipv4_included:document.getElementById('ipv4').checked?'1':'0',ipv6_included:document.getElementById('ipv6').checked?'1':'0',status:document.getElementById('planStatus').value,vps_ip_address:document.getElementById('privateIp').value,vps_provider_login:document.getElementById('providerLogin').value,vps_provider_password:document.getElementById('providerPassword').value,computer_username:document.getElementById('computerUsername').value,computer_password:document.getElementById('computerPassword').value,rdp_password:document.getElementById('rdpPassword').value,clear_private_fields:document.getElementById('clearPrivate').checked?'1':'0'};try{const auth=await this.requestAdminAuth('Save VPS plan','Verify your administrator credentials to save or publish this VPS plan.');Object.assign(data,auth);const d=await this.api('vps_save_plan',data);this.notice(d.message,true);document.getElementById('planId').value=d.id;['privateIp','providerLogin','providerPassword','computerUsername','computerPassword','rdpPassword'].forEach(id=>document.getElementById(id).value='');document.getElementById('clearPrivate').checked=false;this.switchTab('published');}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);}},
async setStatus(id,status){try{const auth=await this.requestAdminAuth('Update VPS plan','Verify administrator credentials to change this plan status.');await this.api('vps_set_plan_status',{id,status,...auth});this.notice('VPS plan status updated.');this.loadPlans();}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);}},
async loadPayments(){const target=document.getElementById('paymentsTable');try{const d=await this.api('vps_get_payments');const rows=d.payments||[];if(!rows.length){target.innerHTML='<div class="vps-empty">No VPS payment submissions are waiting for review.</div>';return;}let html='<table class="vps-table"><thead><tr><th>Buyer</th><th>Purchase target</th><th>Plan</th><th>Amount</th><th>Payment method</th><th>Reference</th><th>Submitted</th><th>Status</th><th>Decision</th></tr></thead><tbody>';rows.forEach(r=>{const targetName=r.request_type==='programme'?`Programme: ${this.esc(r.program_name||'#'+r.programme_id)}`:`Investor subaccount #${this.esc(r.sub_account_id)}`;const state=r.payment_status==='payment-confirmed'?'good':(r.payment_status==='payment-failed'?'failed':'pending');const stateLabel=r.payment_status==='payment-confirmed'?'Good terms · No attention needed':(r.payment_status==='payment-failed'?'Payment failed':(r.payment_status==='payment-made'?'Payment submitted · Review':'Awaiting payment'));let decision='';if(r.payment_status==='payment-made'){decision=`<div class="vps-actions"><button class="vps-btn" onclick="VPS.decidePayment(${r.id},'confirm')">Confirm &amp; Register</button><button class="vps-btn danger" onclick="VPS.decidePayment(${r.id},'fail')">Payment failed</button></div>`;}else decision=this.esc(r.allocation_message||r.request_status);html+=`<tr><td><b>${this.esc(r.request_name)}</b><span class="vps-meta">${this.esc(r.request_email)}</span><span class="vps-meta">HarvHub row #${this.esc(r.user_id)}</span></td><td>${targetName}<span class="vps-meta">Subaccount #${this.esc(r.sub_account_id)}</span></td><td>${this.esc(r.product_name)}<span class="vps-meta">${this.esc(r.plan_name)}</span></td><td><b>${this.money(r.amount,r.currency)}</b></td><td>${this.esc(r.payment_method||'—')}<span class="vps-meta">${this.esc(r.payment_network||'')}</span></td><td>${this.esc(r.payment_reference||'—')}</td><td>${this.esc(r.payment_made_at||'—')}</td><td><span class="vps-status ${state}">${this.esc(stateLabel)}</span>${r.request_status==='allocation_conflict'?'<span class="vps-meta">Allocation needs review</span>':''}</td><td>${decision}</td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load payment requests. Run the VPS SQL migration.</div>';this.notice(e.message,false);}},
async decidePayment(id,decision){if(decision==='confirm'&&!window.confirm('Confirm this payment and register the VPS against the recorded subaccount or programme?'))return;try{const auth=await this.requestAdminAuth(decision==='confirm'?'Confirm VPS payment':'Mark VPS payment failed','Verify administrator credentials to process this payment decision.');const d=await this.api('vps_decide_payment',{request_id:id,decision,...auth});this.notice(d.message||'Payment decision saved.');this.loadPayments();this.loadCount();this.loadPlans();if(decision==='confirm')this.loadRegistered();}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);this.loadPayments();}},
async migrateLegacy(){try{const auth=await this.requestAdminAuth('Migrate legacy VPS details','Verify administrator credentials before converting legacy provisioning records.');const d=await this.api('vps_migrate_legacy_private_fields',auth);this.notice(d.message||'Legacy VPS provisioning details migrated.');}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);}},
async loadRegistered(silent=false){const target=document.getElementById('registeredTable');if(!silent)target.innerHTML='<div class="vps-empty">Loading registered allocations…</div>';try{const d=await this.api('vps_get_registered',{target_type:this.registeredKind});const rows=d.rows||[];if(!rows.length){target.innerHTML='<div class="vps-empty">No payment-confirmed VPS registrations found in this category.</div>';return;}let html='<table class="vps-table" style="min-width:2200px"><thead><tr><th>Registered target</th><th>Buyer</th><th>Registration</th><th>VPS product / plan</th><th>CPU</th><th>RAM</th><th>Storage</th><th>Network / transfer</th><th>OS / virtualization</th><th>Location</th><th>Billing / duration</th><th>Price</th><th>Included features</th><th>Start date</th><th>Visibility</th><th>Payment reference</th><th>IP address</th><th>Provider login</th><th>Provider password</th><th>Computer username</th><th>Computer password</th><th>RDP password</th><th>Unlock</th></tr></thead><tbody>';rows.forEach(r=>{const start=r.v_subscription_start_date||'—';const duration=r.v_subscription_duration||r.subscription_duration_days||'—';const visibility=Number(r.v_visibility)===1?'Public':'Private';const masked='<span class="vps-lock">••••••••</span>';html+=`<tr><td><b>${this.esc(r.target_name)}</b><span class="vps-meta">${r.target_type==='programme'?'Programme':'Investor subaccount'} #${r.target_type==='programme'?this.esc(r.programme_id):this.esc(r.sub_account_id)}</span><span class="vps-meta">Subaccount ID #${this.esc(r.sub_account_id)}</span></td><td>${this.esc(r.request_name)}<span class="vps-meta">${this.esc(r.request_email)}</span><span class="vps-meta">HarvHub ID #${this.esc(r.user_id)}</span></td><td>#${this.esc(r.registration_id)}<span class="vps-meta">Request #${this.esc(r.request_id)}</span></td><td><b>${this.esc(r.product_name||'VPS plan')}</b><span class="vps-meta">${this.esc(r.plan_name||'')}</span><span class="vps-meta">${this.esc(r.provider_name||'')}</span><span class="vps-meta">${this.esc(r.plan_description||'')}</span></td><td>${this.esc(r.cpu_type||'—')}<span class="vps-meta">${this.esc(r.vcpu_count||'—')} vCPU</span></td><td>${this.esc(r.ram_gb||'—')} GB</td><td>${this.esc(r.storage_gb||'—')} GB ${this.esc(r.storage_type||'')}</td><td>${this.esc(r.network_port_speed_mbps||'—')} Mbps<span class="vps-meta">${this.esc(r.data_transfer||'')}</span></td><td>${this.esc(r.operating_system||'—')}<span class="vps-meta">${this.esc(r.virtualization_type||'')}</span></td><td>${this.esc(r.v_server_location||r.plan_location||r.server_location||'—')}<span class="vps-meta">${this.esc(r.region_code||'')}</span></td><td>${this.esc(r.billing_period||'—')}<span class="vps-meta">${this.esc(duration)} days</span></td><td>${this.money(r.plan_price??r.amount,r.plan_currency||r.currency)}<span class="vps-meta">Setup ${this.money(r.setup_fee||0,r.plan_currency||r.currency)}</span></td><td>${Number(r.ipv4_included)?'IPv4':'No IPv4'} · ${Number(r.ipv6_included)?'IPv6':'No IPv6'}<span class="vps-meta">${this.esc(r.backup_option||'No backup detail')} · ${this.esc(r.snapshot_count||0)} snapshots</span></td><td>${this.esc(start)}</td><td>${this.esc(visibility)}</td><td>${this.esc(r.payment_reference||'—')}</td><td>${masked}</td><td>${masked}</td><td>${masked}</td><td>${masked}</td><td>${masked}</td><td>${masked}</td><td><button class="vps-btn secondary" onclick="VPS.openSecrets('${this.registeredKind}',${Number(r.registration_id)},${Number(r.request_id)})">View details</button></td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load confirmed VPS registrations. Verify the VPS tables and migrations.</div>';this.notice(e.message,false);}},
openSecrets(type,registrationId,requestId){this.revealTarget={type,registrationId,requestId};document.getElementById('secretResult').innerHTML='';document.getElementById('secretModal').classList.add('open');},closeSecrets(){document.getElementById('secretModal').classList.remove('open');this.revealTarget=null;document.getElementById('secretResult').innerHTML='';},
async unlockSecrets(){if(!this.revealTarget)return;try{const auth=await this.requestAdminAuth('Reveal VPS private details','Re-enter your administrator credentials to reveal the private IP address and provisioning credentials for this registered VPS.');const d=await this.api('vps_reveal_registered',{target_type:this.revealTarget.type,registration_id:this.revealTarget.registrationId,request_id:this.revealTarget.requestId,...auth});const fields=d.private||{};const labels={vps_ip_address:'VPS IP address',programme_vps_ip_address:'Programme VPS IP address',vps_provider_login:'Provider login',vps_provider_password:'Provider password',computer_username:'Computer username',computer_password:'Computer password',rdp_password:'RDP password'};let html="<div class='vps-secret-list'>";Object.entries(labels).forEach(([key,label])=>{const val=fields[key]??'';html+=`<div class="vps-secret"><small>${this.esc(label)}</small>${val!==''?this.esc(val):'<span class="vps-lock">Not configured</span>'}</div>`;});html+='</div>';document.getElementById('secretResult').innerHTML=html;}catch(e){if(e.message!=='Administrator verification cancelled.')document.getElementById('secretResult').textContent=e.message;}},
init(){document.getElementById('planForm').addEventListener('submit',e=>this.savePlan(e));this.loadPlans();this.loadCount();this.paymentCountTimer=setInterval(()=>{if(!document.hidden&&window.__serverAppActive!==false)this.loadCount();},12000);this.paymentTableTimer=setInterval(()=>{if(!document.hidden&&window.__serverAppActive!==false&&document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();},25000);this.liveRefreshTimer=setInterval(()=>{if(document.hidden||window.__serverAppActive===false)return;this.loadCount();if(document.getElementById('panel-published')?.classList.contains('active'))this.loadPlans();if(document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();if(document.getElementById('panel-registered')?.classList.contains('active'))this.loadRegistered(true);},35000);window.addEventListener('serverapp:activechange',e=>{if(e.detail?.active){this.loadCount();const panel=document.querySelector('.vps-panel.active');if(panel?.id==='panel-published')this.loadPlans();if(panel?.id==='panel-payments')this.loadPayments();if(panel?.id==='panel-registered')this.loadRegistered(true);}});document.addEventListener('visibilitychange',()=>{if(!document.hidden&&window.__serverAppActive!==false){this.loadCount();if(document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();}});document.getElementById('secretModal').addEventListener('click',e=>{if(e.target.id==='secretModal')this.closeSecrets();});document.getElementById('adminAuthModal').addEventListener('click',e=>{if(e.target.id==='adminAuthModal')this.cancelAdminAuth();});document.getElementById('adminAuthPassword').addEventListener('keydown',e=>{if(e.key==='Enter')this.confirmAdminAuth();});}
};document.addEventListener('DOMContentLoaded',()=>VPS.init());
</script>
</main></body></html>
