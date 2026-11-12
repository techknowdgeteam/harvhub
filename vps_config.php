<?php
/**
 * Server App VPS administration.
 * Published plans are public; registration/provisioning fields are only returned after
 * an administrator re-authenticates for a specific registered VPS row.
 */
require_once __DIR__ . '/serverapp_guard.php';
require_once __DIR__ . '/server_vps_schema.php';
serverVpsEnsureSchema($pdo);

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
function vpsAdminPrivateFieldNames(): array {
    return ['computer_username','computer_password','ipv4_address','ipv6_address'];
}
function vpsAdminPrivateFields(PDO $pdo,int $registeredVpsId): array {
    $q=$pdo->prepare('SELECT computer_username,computer_password,ipv4_address,ipv6_address,ipv6_included,registration_status FROM server_registered_vps WHERE id=? LIMIT 1');
    $q->execute([$registeredVpsId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: [];
}
function vpsAdminSaveRegisteredPrivate(PDO $pdo,int $registeredVpsId,string $type,array $submitted):array {
    if(!in_array($type,['accounts','programmes'],true)) throw new RuntimeException('Invalid registered VPS type.');
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM server_registered_vps WHERE id=? FOR UPDATE');
        $q->execute([$registeredVpsId]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('VPS registration record not found.');
        $expectedType=$type==='accounts'?'account':'programme';
        if(($row['request_type']??'')!==$expectedType) throw new RuntimeException('The selected VPS belongs to a different registration category.');
        $values=[
            'computer_username'=>trim((string)($submitted['computer_username']??'')),
            'computer_password'=>(string)($submitted['computer_password']??''),
            'ipv4_address'=>trim((string)($submitted['ipv4_address']??'')),
            'ipv6_address'=>trim((string)($submitted['ipv6_address']??''))
        ];
        if($values['computer_username']!=='' && strlen($values['computer_username'])>255) throw new RuntimeException('Computer username must be 255 characters or fewer.');
        if(strlen($values['computer_password'])>4000) throw new RuntimeException('Computer password is too long.');
        if($values['ipv4_address']!=='' && filter_var($values['ipv4_address'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)===false) throw new RuntimeException('Enter a valid IPv4 address.');
        if($values['ipv6_address']!=='' && filter_var($values['ipv6_address'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)===false) throw new RuntimeException('Enter a valid IPv6 address.');
        $complete=$values['computer_username']!=='' && $values['computer_password']!=='' && $values['ipv4_address']!=='' && ((int)($row['ipv6_included']??0)!==1 || $values['ipv6_address']!=='');
        $status=$complete?'registered':'needs_attention';
        $update=$pdo->prepare("UPDATE server_registered_vps SET computer_username=?,computer_password=?,ipv4_address=?,ipv6_address=?,registration_status=?,registration_completed_at=IF(?,COALESCE(registration_completed_at,NOW()),NULL) WHERE id=?");
        $update->execute([$values['computer_username'],$values['computer_password'],$values['ipv4_address'],$values['ipv6_address']!==''?$values['ipv6_address']:null,$status,$complete?1:0,$registeredVpsId]);

        $table=$expectedType==='account'?'vps':'programme_vps';
        $opsField=$expectedType==='account'?'account_vps_id':'programme_vps_id';
        $opsId=(int)($row[$opsField]??0);
        $uid=(int)($row['user_id']??0);
        $subId=(int)($row['sub_account_id']??$uid);
        $programmeId=(int)($row['programme_id']??0);

        // Adopt an existing operational row when an older registration was not linked
        // to it. This is the programme duplicate-row failure that previously bubbled up
        // as the generic "VPS operation could not be completed" message.
        if($opsId>0 && vpsAdminTableExists($pdo,$table)) {
            $linkedCheck=$pdo->prepare("SELECT id FROM `$table` WHERE id=? LIMIT 1 FOR UPDATE");
            $linkedCheck->execute([$opsId]);
            if(!$linkedCheck->fetchColumn()) $opsId=0;
        }
        if($opsId<=0 && vpsAdminTableExists($pdo,$table)) {
            if($expectedType==='account') {
                $find=$pdo->prepare('SELECT id FROM vps WHERE user_id=? AND sub_account_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
                $find->execute([$uid,$subId]);
            } else {
                $find=$pdo->prepare('SELECT id FROM programme_vps WHERE programme_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE');
                $find->execute([$programmeId]);
            }
            $opsId=(int)($find->fetchColumn()?:0);
        }

        // An operational VPS is created only after the server has entered all required
        // private values. A partial save remains an unregistered provisioning record.
        if($opsId<=0 && $complete) {
            if($expectedType==='account') {
                $opsId=vpsAdminInsertProvision($pdo,'vps',[
                    'user_id'=>$uid,'sub_account_id'=>$subId,
                    'server_location'=>(string)($row['server_location']??''),
                    'subscription_duration'=>(int)($row['subscription_duration_days']??30),
                    'subscription_start_date'=>date('Y-m-d'),'vps_ip_address'=>$values['ipv4_address'],
                    'ipv6_address'=>$values['ipv6_address']!==''?$values['ipv6_address']:null,
                    'computer_username'=>$values['computer_username'],'computer_password'=>$values['computer_password'],'visibility'=>0
                ]);
            } else {
                $opsId=vpsAdminInsertProvision($pdo,'programme_vps',[
                    'programme_id'=>$programmeId,'user_id'=>$uid,'sub_account_id'=>$subId,
                    'server_location'=>(string)($row['server_location']??''),
                    'subscription_duration'=>(int)($row['subscription_duration_days']??30),
                    'programme_vps_ip_address'=>$values['ipv4_address'],'vps_ip_address'=>$values['ipv4_address'],
                    'ipv6_address'=>$values['ipv6_address']!==''?$values['ipv6_address']:null,
                    'computer_username'=>$values['computer_username'],'computer_password'=>$values['computer_password'],'visibility'=>0
                ]);
            }
        }

        if($opsId>0) {
            $columns=vpsAdminColumns($pdo,$table);$sets=[];$params=[];$copy=[];
            if(isset($columns['computer_username']))$copy['computer_username']=$values['computer_username'];
            if(isset($columns['computer_password']))$copy['computer_password']=$values['computer_password'];
            if(isset($columns['ipv6_address']))$copy['ipv6_address']=$values['ipv6_address']!==''?$values['ipv6_address']:null;
            if($expectedType==='account' && isset($columns['vps_ip_address']))$copy['vps_ip_address']=$values['ipv4_address'];
            if($expectedType==='programme') {
                if(isset($columns['programme_vps_ip_address']))$copy['programme_vps_ip_address']=$values['ipv4_address'];
                if(isset($columns['vps_ip_address']))$copy['vps_ip_address']=$values['ipv4_address'];
            }
            foreach($copy as $column=>$value){$sets[]='`'.$column.'`=?';$params[]=$value;}
            if($sets){$params[]=$opsId;$q=$pdo->prepare('UPDATE `'.$table.'` SET '.implode(',',$sets).' WHERE id=?');$q->execute($params);}
            $link=$pdo->prepare("UPDATE server_registered_vps SET `$opsField`=? WHERE id=?");
            $link->execute([$opsId,$registeredVpsId]);
        }

        $purchaseId=(int)($row['purchase_request_id']??0);
        if($purchaseId>0 && vpsAdminTableExists($pdo,'server_vps_purchase_requests')) {
            $requestColumns=vpsAdminColumns($pdo,'server_vps_purchase_requests');
            $sets=[];$params=[];
            if(isset($requestColumns['registration_id'])){$sets[]='registration_id=?';$params[]=$opsId>0?$opsId:null;}
            if(isset($requestColumns['registered_vps_id'])){$sets[]='registered_vps_id=?';$params[]=$registeredVpsId;}
            if($sets){$params[]=$purchaseId;$pdo->prepare('UPDATE server_vps_purchase_requests SET '.implode(',',$sets).' WHERE id=?')->execute($params);}
        }
        $pdo->commit();
        return ['registration_status'=>$status,'message'=>$complete?'VPS private provisioning saved. This VPS is now registered.':'Private details saved; this VPS remains under Unregistered until all required values are complete.'];
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
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
function vpsAdminAllocatePurchase(PDO $pdo,int $requestId,string $adminId):array {
    $pdo->beginTransaction();
    try {
        $rq=$pdo->prepare('SELECT * FROM server_vps_purchase_requests WHERE id=? FOR UPDATE');$rq->execute([$requestId]);$request=$rq->fetch(PDO::FETCH_ASSOC);
        if(!$request) throw new RuntimeException('Purchase request not found.');
        if(($request['payment_status']??'')!=='payment-made') throw new RuntimeException('Only payments marked as made can be confirmed.');
        $existing=$pdo->prepare('SELECT id FROM server_registered_vps WHERE purchase_request_id=? LIMIT 1 FOR UPDATE');$existing->execute([$requestId]);
        if($existing->fetchColumn()) throw new RuntimeException('This purchase already has a registered VPS allocation.');
        $planQ=$pdo->prepare('SELECT * FROM server_available_vps WHERE id=? FOR UPDATE');$planQ->execute([(int)$request['vps_id']]);$plan=$planQ->fetch(PDO::FETCH_ASSOC);
        if(!$plan) throw new RuntimeException('The advertised VPS plan no longer exists.');
        $capacity=(int)($plan['stock_quantity']??0);$allocated=(int)($plan['allocated_quantity']??0);
        if($capacity>0 && $allocated>=$capacity) throw new RuntimeException('This plan has no unallocated stock.');

        $uid=(int)$request['user_id'];$subId=(int)$request['sub_account_id'];
        $duration=max(1,(int)($plan['subscription_duration_days']??30));$location=(string)($plan['server_location']??'');
        $targetType=(string)($request['request_type']??'');
        if($targetType==='account') {
            $ownerLock=$pdo->prepare('SELECT id FROM harvhub WHERE id=? AND COALESCE(sub_account_id,id)=? FOR UPDATE');$ownerLock->execute([$uid,$subId]);
            if(!$ownerLock->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('The target subaccount no longer exists or no longer matches this VPS request.');
            $columns=vpsAdminColumns($pdo,'vps');
            $existsSql='SELECT id FROM vps WHERE user_id=?'.(isset($columns['sub_account_id'])?' AND sub_account_id=?':'').' LIMIT 1 FOR UPDATE';
            $exists=$pdo->prepare($existsSql);$exists->execute(isset($columns['sub_account_id'])?[$uid,$subId]:[$uid]);
            if($exists->fetch(PDO::FETCH_ASSOC)) throw new RuntimeException('This subaccount already has a VPS registered. Review the existing VPS before allocating another.');
            $programmeId=null;$accountVpsId=null;$programmeVpsId=null;
        } elseif($targetType==='programme') {
            $programmeId=(int)($request['programme_id']??0);if($programmeId<=0)throw new RuntimeException('The programme reference is missing.');
            $pc=vpsAdminColumns($pdo,'programme');$programmeSql='SELECT id,userid'.(isset($pc['sub_account_id'])?',sub_account_id':'').' FROM programme WHERE id=? AND userid=? FOR UPDATE';
            $ownerLock=$pdo->prepare($programmeSql);$ownerLock->execute([$programmeId,$uid]);$programmeOwner=$ownerLock->fetch(PDO::FETCH_ASSOC);
            if(!$programmeOwner)throw new RuntimeException('The target programme no longer belongs to the recorded account.');
            if(isset($programmeOwner['sub_account_id'])&&(int)$programmeOwner['sub_account_id']>0&&(int)$programmeOwner['sub_account_id']!==$subId)throw new RuntimeException('The target programme subaccount no longer matches this purchase request.');
            $ownerLock2=$pdo->prepare('SELECT id FROM harvhub WHERE id=? AND COALESCE(sub_account_id,id)=? FOR UPDATE');$ownerLock2->execute([$uid,$subId]);
            if(!$ownerLock2->fetch(PDO::FETCH_ASSOC))throw new RuntimeException('The programme owner subaccount could not be verified.');
            $exists=$pdo->prepare('SELECT id FROM programme_vps WHERE programme_id=? LIMIT 1 FOR UPDATE');$exists->execute([$programmeId]);
            if($exists->fetch(PDO::FETCH_ASSOC))throw new RuntimeException('This programme already has a VPS registered. Review the existing programme VPS before allocating another.');
            $accountVpsId=null;$programmeVpsId=null;
        } else throw new RuntimeException('Invalid purchase target type.');

        $snapshot=[
            'purchase_request_id'=>$requestId,'advertised_vps_id'=>(int)$plan['id'],'request_type'=>$targetType,
            'user_id'=>$uid,'sub_account_id'=>$subId,'programme_id'=>$programmeId,
            'account_vps_id'=>$accountVpsId,'programme_vps_id'=>$programmeVpsId,
            'product_name'=>$plan['product_name']??'','provider_name'=>$plan['provider_name']??null,'plan_name'=>$plan['plan_name']??'',
            'plan_description'=>$plan['plan_description']??null,'cpu_type'=>$plan['cpu_type']??null,'vcpu_count'=>(int)($plan['vcpu_count']??0),
            'ram_gb'=>(float)($plan['ram_gb']??0),'storage_type'=>$plan['storage_type']??null,'storage_gb'=>(float)($plan['storage_gb']??0),
            'network_port_speed_mbps'=>isset($plan['network_port_speed_mbps'])?(int)$plan['network_port_speed_mbps']:null,
            'data_transfer'=>$plan['data_transfer']??null,'server_location'=>$plan['server_location']??'',
            'region_code'=>$plan['region_code']??null,'operating_system'=>$plan['operating_system']??null,
            'virtualization_type'=>$plan['virtualization_type']??null,'ipv4_included'=>(int)($plan['ipv4_included']??0),
            'ipv6_included'=>(int)($plan['ipv6_included']??0),'backup_option'=>$plan['backup_option']??null,
            'snapshot_count'=>(int)($plan['snapshot_count']??0),'billing_period'=>$plan['billing_period']??'monthly',
            'subscription_duration_days'=>$duration,'price'=>(float)($plan['price']??0),'currency'=>$plan['currency']??'USD',
            'setup_fee'=>(float)($plan['setup_fee']??0),'stock_quantity'=>(int)($plan['stock_quantity']??0),
            'registration_status'=>'needs_attention'
        ];
        $cols=vpsAdminColumns($pdo,'server_registered_vps');$snapshot=array_intersect_key($snapshot,$cols);
        $names=array_keys($snapshot);$insert=$pdo->prepare('INSERT INTO server_registered_vps (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')');
        $insert->execute(array_values($snapshot));$registeredVpsId=(int)$pdo->lastInsertId();
        if($registeredVpsId<=0)throw new RuntimeException('The sold VPS snapshot could not be saved.');

        $up=$pdo->prepare("UPDATE server_vps_purchase_requests SET registration_id=?,registered_vps_id=?,payment_status='payment-confirmed',request_status='allocated',buy_request=0,payment_decided_at=NOW(),payment_decided_by=?,allocation_message='Payment confirmed. Private provisioning details are required.' WHERE id=?");
        $up->execute([null,$registeredVpsId,$adminId,$requestId]);
        $stock=$pdo->prepare("UPDATE server_available_vps SET allocated_quantity=allocated_quantity+1,status=IF(stock_quantity>0 AND allocated_quantity+1>=stock_quantity,'sold_out',status) WHERE id=?");
        $stock->execute([(int)$plan['id']]);vpsAdminRefreshBuyFlag($pdo,(int)$plan['id']);
        $pdo->commit();
        return ['success'=>true,'registered_vps_id'=>$registeredVpsId,'registration_id'=>null,'message'=>'Payment confirmed. The VPS has been registered to the selected account or programme and is waiting for private provisioning details.'];
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
}
function vpsAdminRegisteredRows(PDO $pdo,string $type):array {
    if(!in_array($type,['accounts','programmes'],true)) throw new RuntimeException('Invalid VPS allocation category.');
    $rvCols=vpsAdminColumns($pdo,'server_registered_vps');
    $requestExists=vpsAdminTableExists($pdo,'server_vps_purchase_requests');
    $rCols=$requestExists?vpsAdminColumns($pdo,'server_vps_purchase_requests'):[];
    $harvhubExists=vpsAdminTableExists($pdo,'harvhub');
    $hCols=$harvhubExists?vpsAdminColumns($pdo,'harvhub'):[];
    $programmeExists=vpsAdminTableExists($pdo,'programme');
    $pCols=$programmeExists?vpsAdminColumns($pdo,'programme'):[];
    $kind=$type==='accounts'?'account':'programme';
    $opsTable=$kind==='account'?'vps':'programme_vps';
    $opsExists=vpsAdminTableExists($pdo,$opsTable);
    $opsCols=$opsExists?vpsAdminColumns($pdo,$opsTable):[];
    $opsField=$kind==='account'?'account_vps_id':'programme_vps_id';

    $col=static function(array $cols,string $alias,string $name,string $fallback='NULL'):string {
        return isset($cols[$name]) ? $alias.'.`'.$name.'`' : $fallback;
    };
    $rJoin=($requestExists && isset($rCols['id']) && isset($rvCols['purchase_request_id']))
        ? ' LEFT JOIN server_vps_purchase_requests r ON r.id=rv.purchase_request_id '
        : '';
    $hJoin=($harvhubExists && isset($hCols['id']) && isset($rvCols['user_id']))
        ? ' LEFT JOIN harvhub h ON h.id=rv.user_id '
        : '';
    $opsJoin=($opsExists && isset($opsCols['id']) && isset($rvCols[$opsField]))
        ? ' LEFT JOIN `'.$opsTable.'` v ON v.id=rv.`'.$opsField.'` '
        : '';

    $requestNameParts=[];
    if($rJoin && isset($rCols['request_name'])) $requestNameParts[]="NULLIF(r.request_name,'')";
    if($hJoin) {
        foreach(($kind==='account'?['sub_account_name','username','fullname']:['fullname','username','sub_account_name']) as $field) {
            if(isset($hCols[$field])) $requestNameParts[]="NULLIF(h.`$field`,'')";
        }
    }
    $requestNameParts[]=$kind==='account'?"'Investor subaccount'":"'Programme owner'";
    $requestName='COALESCE('.implode(',',$requestNameParts).')';

    $emailParts=[];
    if($rJoin && isset($rCols['request_email'])) $emailParts[]="NULLIF(r.request_email,'')";
    if($hJoin && isset($hCols['email'])) $emailParts[]='h.email';
    $emailParts[]="''";
    $emailExpr='COALESCE('.implode(',',$emailParts).')';

    $amountParts=[];
    if($rJoin && isset($rCols['amount'])) $amountParts[]='r.amount';
    if(isset($rvCols['price'])) $amountParts[]='rv.price';
    $amountParts[]='0';
    $amountExpr='COALESCE('.implode(',',$amountParts).')';
    $currencyParts=[];
    if($rJoin && isset($rCols['currency'])) $currencyParts[]='r.currency';
    if(isset($rvCols['currency'])) $currencyParts[]='rv.currency';
    $currencyParts[]="'USD'";
    $currencyExpr='COALESCE('.implode(',',$currencyParts).')';

    $requestIdExpr=($rJoin && isset($rCols['id']))?'r.id':'NULL';
    $decidedParts=[];
    if($rJoin && isset($rCols['payment_decided_at'])) $decidedParts[]='r.payment_decided_at';
    if(isset($rvCols['registration_completed_at'])) $decidedParts[]='rv.registration_completed_at';
    if(isset($rvCols['created_at'])) $decidedParts[]='rv.created_at';
    $decidedExpr=$decidedParts?'COALESCE('.implode(',',$decidedParts).')':'NULL';
    $referenceExpr=($rJoin && isset($rCols['payment_reference']))?'COALESCE(r.payment_reference,\'\')':"''";

    $targetId=$kind==='account'?'rv.sub_account_id':'rv.programme_id';
    $targetTypeLabel=$kind==='account'?'account':'programme';
    $programmeNameExpr=($kind==='programme' && $programmeExists && isset($pCols['id']) && isset($pCols['program_name']))
        ? 'p.program_name'
        : ($kind==='programme' ? "CONCAT('Programme #',COALESCE(rv.programme_id,0))" : "'Investor subaccount'");
    $pJoin=($kind==='programme' && $programmeExists && isset($pCols['id']) && isset($rvCols['programme_id']))
        ? ' LEFT JOIN programme p ON p.id=rv.programme_id '
        : '';

    $columns=[
        "$requestIdExpr AS request_id",
        (($rJoin && isset($rCols['vps_id']))?'r.vps_id':$col($rvCols,'rv','advertised_vps_id','0')).' AS vps_id',
        $col($rvCols,'rv','user_id','0').' AS user_id',
        $col($rvCols,'rv','sub_account_id','0').' AS sub_account_id',
        $col($rvCols,'rv','programme_id','NULL').' AS programme_id',
        "$requestName AS request_name",
        "$emailExpr AS request_email",
        "$amountExpr AS amount",
        "$currencyExpr AS currency",
        "$decidedExpr AS payment_decided_at",
        "$referenceExpr AS payment_reference",
        $col($rvCols,'rv','id','0').' AS registered_vps_id',
        $col($rvCols,'rv',$opsField,'NULL').' AS registration_id'
    ];
    $snapshotFields=['product_name','provider_name','plan_name','plan_description','cpu_type','vcpu_count','ram_gb','storage_type','storage_gb','network_port_speed_mbps','data_transfer','region_code','operating_system','virtualization_type','ipv4_included','ipv6_included','backup_option','snapshot_count','billing_period','subscription_duration_days','setup_fee','registration_status'];
    foreach($snapshotFields as $field) $columns[]=$col($rvCols,'rv',$field)." AS `$field`";
    $columns[]=$col($rvCols,'rv','server_location'). ' AS plan_location';
    $columns[]=$col($rvCols,'rv','price','0'). ' AS plan_price';
    $columns[]=$col($rvCols,'rv','currency',"'USD'"). ' AS plan_currency';
    foreach([
        'computer_username'=>'has_computer_username',
        'computer_password'=>'has_computer_password',
        'ipv4_address'=>'has_ipv4_address',
        'ipv6_address'=>'has_ipv6_address'
    ] as $privateField=>$flag) {
        $columns[]='('.$col($rvCols,'rv',$privateField).' IS NOT NULL AND '.$col($rvCols,'rv',$privateField)."<>'') AS `$flag`";
    }
    foreach(['server_location'=>'v_server_location','subscription_duration'=>'v_subscription_duration','subscription_start_date'=>'v_subscription_start_date','visibility'=>'v_visibility'] as $field=>$alias) {
        $columns[]=$opsJoin?$col($opsCols,'v',$field)." AS `$alias`":"NULL AS `$alias`";
    }
    if($kind==='programme') $columns[]="$programmeNameExpr AS program_name";
    if($hJoin) {
        foreach(['fullname','username','sub_account_name','email'] as $field) $columns[]=$col($hCols,'h',$field)." AS `$field`";
    } else {
        foreach(['fullname','username','sub_account_name','email'] as $field) $columns[]="NULL AS `$field`";
    }

    $orderColumn=isset($rvCols['updated_at'])?'rv.updated_at':(isset($rvCols['created_at'])?'rv.created_at':'rv.id');
    $sql='SELECT '.implode(',',$columns).' FROM server_registered_vps rv '.$rJoin.$hJoin.$pJoin.$opsJoin
        .' WHERE rv.request_type='.$pdo->quote($kind).' ORDER BY CASE WHEN rv.registration_status=\'needs_attention\' THEN 0 ELSE 1 END,'.$orderColumn.' DESC,rv.id DESC LIMIT 1000';
    $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row) {
        $row['target_type']=$targetTypeLabel;
        if($kind==='account') {
            $row['target_name']=trim((string)($row['sub_account_name']??''))?:trim((string)($row['username']??''))?:trim((string)($row['fullname']??''))?:'Investor subaccount';
        } else {
            $row['target_name']=trim((string)($row['program_name']??''))?:'Programme #'.(int)($row['programme_id']??0);
        }
    }
    unset($row);
    return $rows;
}
function vpsAdminMaskValue($value):string{return trim((string)$value)===''?'Needs setup':'••••••••';}


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
            $q=$pdo->query("SELECT r.id,r.vps_id,r.request_type,r.user_id,r.sub_account_id,r.programme_id,r.request_name,r.request_email,r.payment_status,r.request_status,r.amount,r.currency,r.payment_method,r.payment_network,r.payment_reference,r.payment_made_at,r.allocation_message,r.created_at,v.product_name,v.plan_name,v.server_location,p.program_name,rv.registration_status FROM server_vps_purchase_requests r INNER JOIN server_available_vps v ON v.id=r.vps_id LEFT JOIN programme p ON p.id=r.programme_id LEFT JOIN server_registered_vps rv ON rv.purchase_request_id=r.id WHERE r.payment_status IN ('payment-made','payment-failed','payment-confirmed') OR r.request_status='allocation_conflict' ORDER BY CASE r.payment_status WHEN 'payment-made' THEN 0 WHEN 'payment-failed' THEN 1 ELSE 2 END,r.created_at DESC LIMIT 500");
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
            $availableColumns=vpsAdminColumns($pdo,'server_available_vps');foreach(array_keys($data) as $key)if(!isset($availableColumns[$key]))unset($data[$key]);
            $pdo->beginTransaction();
            try {
                if($id>0){
                    $exists=$pdo->prepare('SELECT id FROM server_available_vps WHERE id=? FOR UPDATE');$exists->execute([$id]);
                    if(!$exists->fetch())throw new RuntimeException('Plan not found.');
                    $sets=[];foreach(array_keys($data) as $key)$sets[]='`'.$key.'`=?';
                    $vals=array_values($data);$vals[]=$id;
                    $q=$pdo->prepare('UPDATE server_available_vps SET '.implode(',',$sets).' WHERE id=?');$q->execute($vals);
                } else {
                    $data['created_by']=(string)($_SESSION['admin_login_id']??'server-admin');
                    foreach(array_keys($data) as $key)if(!isset($availableColumns[$key]))unset($data[$key]);
                    $cols=array_keys($data);$q=$pdo->prepare('INSERT INTO server_available_vps (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');
                    $q->execute(array_values($data));$id=(int)$pdo->lastInsertId();
                }
                $pdo->commit();
            } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
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
        if($action==='vps_get_registered') {
            $type=(string)($_POST['target_type']??'accounts');if(!in_array($type,['accounts','programmes'],true))serverAppJson(['success'=>false,'error'=>'Invalid registered VPS type.']);
            if(!vpsAdminTableExists($pdo,'server_registered_vps')) serverAppJson(['success'=>true,'rows'=>[]]);
            try {
                $rows=vpsAdminRegisteredRows($pdo,$type);
                serverAppJson(['success'=>true,'rows'=>$rows]);
            } catch(Throwable $listError) {
                error_log('[VPS provisioning list]['.$type.'] '.$listError->getMessage().' at '.$listError->getFile().':'.$listError->getLine());
                serverAppJson(['success'=>false,'error'=>'Unable to load '.($type==='programmes'?'programme':'account').' VPS allocations. Check the server application log.'],500);
            }
        }
        if($action==='vps_reveal_registered') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator password.']);
            $type=(string)($_POST['target_type']??'');$registeredId=(int)($_POST['registered_vps_id']??0);
            if(!in_array($type,['accounts','programmes'],true)||$registeredId<=0)serverAppJson(['success'=>false,'error'=>'Invalid VPS selection.']);
            $expected=$type==='accounts'?'account':'programme';
            $q=$pdo->prepare("SELECT id FROM server_registered_vps WHERE id=? AND request_type=? LIMIT 1");
            $q->execute([$registeredId,$expected]);
            if(!$q->fetchColumn())serverAppJson(['success'=>false,'error'=>'The selected registered VPS could not be verified.']);
            serverAppJson(['success'=>true,'private'=>vpsAdminPrivateFields($pdo,$registeredId),'message'=>'Registered VPS provisioning details unlocked.']);
        }
        if($action==='vps_save_registered_private') {
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator password.']);
            $type=(string)($_POST['target_type']??'');$registeredId=(int)($_POST['registered_vps_id']??0);
            if($registeredId<=0)serverAppJson(['success'=>false,'error'=>'Select a registered VPS first.']);
            $result=vpsAdminSaveRegisteredPrivate($pdo,$registeredId,$type,[
                'computer_username'=>$_POST['computer_username']??'',
                'computer_password'=>$_POST['computer_password']??'',
                'ipv4_address'=>$_POST['ipv4_address']??'',
                'ipv6_address'=>$_POST['ipv6_address']??''
            ]);
            serverAppJson(['success'=>true]+$result);
        }
        serverAppJson(['success'=>false,'error'=>'Unknown VPS administration action.'],400);
    } catch(Throwable $e) {
        error_log('VPS admin action ['.$action.'] failed: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
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
.vps-page{max-width:1500px;margin:0 auto;padding:2px 0 30px;color:var(--text-color)}.vps-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:18px}.vps-head h1{margin:0;font-size:24px;color:var(--header-color)}.vps-head p{margin:5px 0 0;max-width:850px;color:var(--text-muted)}.vps-tabs{display:flex;gap:7px;overflow:auto;padding:6px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;margin-bottom:16px}.vps-tab{white-space:nowrap;border:0;border-radius:8px;padding:10px 14px;background:transparent;color:var(--text-color);font-weight:750;cursor:pointer}.vps-tab.active{background:var(--accent-color);color:#fff}.vps-tab .count{display:inline-block;margin-left:5px;padding:1px 6px;border-radius:20px;background:rgba(120,130,145,.2);font-size:11px}.vps-panel{display:none}.vps-panel.active{display:block}.vps-card{background:var(--container-bg);border:1px solid var(--border-color);border-radius:14px;overflow:hidden;margin-bottom:14px;box-shadow:0 5px 18px var(--shadow-color)}.vps-card-head{padding:15px 18px;border-bottom:1px solid var(--border-color)}.vps-card-head h2{margin:0;font-size:17px;color:var(--header-color)}.vps-card-head p{margin:4px 0 0;color:var(--text-muted);font-size:12px}.vps-card-body{padding:17px}.vps-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.vps-field{display:grid;gap:6px}.vps-field.wide{grid-column:1/-1}.vps-field label{font-size:12px;font-weight:750;color:var(--text-muted)}.vps-field input,.vps-field select,.vps-field textarea,.vps-search,.vps-modal input{width:100%;border:1px solid var(--border-color);border-radius:9px;padding:10px 12px;background:var(--input-bg);color:var(--text-color);font:inherit}.vps-field textarea{min-height:75px;resize:vertical}.vps-checks{display:flex;flex-wrap:wrap;gap:16px;padding:7px 0}.vps-checks label{display:flex;align-items:center;gap:7px}.vps-actions{display:flex;flex-wrap:wrap;gap:7px}.vps-btn{border:1px solid var(--border-color);border-radius:9px;background:var(--accent-color);color:#fff;padding:9px 13px;font-weight:750;cursor:pointer}.vps-btn.secondary{background:var(--bg-secondary);color:var(--text-color)}.vps-btn.danger{background:var(--danger-color);border-color:var(--danger-color);color:#fff}.vps-btn:disabled{opacity:.55;cursor:not-allowed}.vps-table-wrap{overflow:auto}.vps-table{width:100%;border-collapse:collapse;min-width:1050px}.vps-table th,.vps-table td{padding:11px 12px;text-align:left;vertical-align:top;border-bottom:1px solid var(--border-color)}.vps-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);background:var(--table-header-bg)}.vps-meta{display:block;font-size:12px;color:var(--text-muted);margin-top:3px}.vps-status{display:inline-block;padding:4px 8px;border-radius:20px;background:var(--bg-tertiary);font-size:11px;font-weight:800}.vps-status.good{background:rgba(36,122,75,.12);color:var(--success-color)}html[data-theme="dark"] .vps-status.good{background:rgba(115,200,147,.15);color:var(--success-color)}.vps-status.pending{background:var(--warning-bg);color:var(--text-secondary);border:1px solid var(--border-color)}.vps-status.failed{background:rgba(185,67,53,.12);color:var(--danger-color)}html[data-theme="dark"] .vps-status.failed{background:rgba(239,143,133,.13);color:var(--danger-color)}.vps-specs{display:flex;gap:7px;flex-wrap:wrap}.vps-specs span{padding:4px 7px;border-radius:7px;background:var(--bg-tertiary);font-size:11px}.vps-empty{padding:42px 18px;text-align:center;color:var(--text-muted)}.vps-notice{display:none;padding:11px 13px;border-radius:9px;margin-bottom:12px;background:var(--bg-tertiary);border:1px solid var(--border-color)}.vps-notice.show{display:block}.vps-notice.error{border-color:var(--danger-color);color:var(--danger-color)}.vps-subtabs{display:flex;gap:8px;margin:0 0 13px}.vps-subtab{border:1px solid var(--border-color);border-radius:9px;background:var(--bg-secondary);color:var(--text-color);padding:9px 13px;font-weight:750;cursor:pointer}.vps-subtab.active{background:var(--accent-color);color:#fff}.vps-modal-backdrop{position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;padding:18px}.vps-modal-backdrop.open{display:flex}.vps-modal{width:min(680px,100%);max-height:90vh;overflow:auto;background:var(--container-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:15px;padding:20px;box-shadow:0 15px 60px rgba(0,0,0,.35)}.vps-modal h3{margin:0 0 6px}.vps-modal p{color:var(--text-muted);font-size:13px}.vps-secret-list{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px}.vps-secret{padding:10px;border:1px solid var(--border-color);border-radius:9px;overflow-wrap:anywhere}.vps-secret small{display:block;color:var(--text-muted);font-weight:750;margin-bottom:5px}.vps-lock{font-family:monospace;letter-spacing:.12em;color:var(--text-muted)}#adminAuthError{color:var(--danger-color);font-size:13px;margin-top:9px;overflow-wrap:anywhere}.vps-footnote{padding:12px 17px;color:var(--text-muted);font-size:12px;border-top:1px solid var(--border-color)}
@media(max-width:950px){.vps-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vps-head{flex-direction:column}}
@media(max-width:620px){.vps-grid{grid-template-columns:1fr}.vps-secret-list{grid-template-columns:1fr}.vps-card-body{padding:12px}}
@media(max-width:560px){body{padding:8px}.vps-page{width:100%}.vps-head h1{font-size:20px}.vps-tabs{gap:5px;overflow-x:auto;-webkit-overflow-scrolling:touch}.vps-tab{flex:0 0 auto;font-size:11px;padding:9px 10px}.vps-card-head,.vps-card-body{padding:12px}.vps-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}.vps-table{min-width:1050px}.vps-modal-backdrop{padding:10px;align-items:flex-end}.vps-modal{width:100%;max-width:100%;max-height:90dvh;overflow:auto;border-radius:16px 16px 0 0}}
</style>
</head>
<body><main class="server-feature-wrap"><div class="vps-page">
<header class="vps-head"><div><h1>Virtual Private Servers</h1><p>Publish VPS plans, review purchase submissions, and inspect registered account/programme allocations. Private IP addresses and credentials are never included in normal table responses.</p></div></header>
<div class="vps-notice" id="vpsNotice"></div>
<nav class="vps-tabs"><button class="vps-tab active" data-tab="published" onclick="VPS.switchTab('published')">Published VPS</button><button class="vps-tab" data-tab="advertise" onclick="VPS.switchTab('advertise')">Advertise VPS</button><button class="vps-tab" data-tab="payments" onclick="VPS.switchTab('payments')">VPS Payments <span class="count" id="paymentCount">0</span></button><button class="vps-tab" data-tab="registered" onclick="VPS.switchTab('registered')">VPS Provisioning</button></nav>
<section class="vps-panel active" id="panel-published"><div class="vps-card"><div class="vps-card-head"><h2>Published and saved VPS plans</h2><p>Published plans are available to investor subaccounts and trader programmes. Draft and paused plans remain private.</p></div><div class="vps-table-wrap" id="plansTable"><div class="vps-empty">Loading VPS plans…</div></div></div></section>
<section class="vps-panel" id="panel-advertise"><div class="vps-card"><div class="vps-card-head"><h2 id="planFormTitle">Advertise a VPS plan</h2><p>Enter only the public product, hardware, network, location, operating system, billing and price information that buyers will see.</p></div><div class="vps-card-body"><form id="planForm"><input type="hidden" id="planId"><div class="vps-grid">
<div class="vps-field"><label>Product / VPS family *</label><input id="productName" required maxlength="150" placeholder="Cloud VPS"></div><div class="vps-field"><label>Provider name</label><input id="providerName" maxlength="120" placeholder="Provider"></div><div class="vps-field"><label>Plan name *</label><input id="planName" required maxlength="120" placeholder="VPS 4"></div>
<div class="vps-field wide"><label>Plan description</label><textarea id="planDescription" placeholder="Short public description"></textarea></div><div class="vps-field"><label>CPU / processor</label><input id="cpuType" placeholder="AMD EPYC / Intel Xeon"></div><div class="vps-field"><label>vCPU cores *</label><input id="vcpu" type="number" min="1" required value="2"></div><div class="vps-field"><label>RAM (GB) *</label><input id="ram" type="number" min="0.1" step="0.1" required value="4"></div>
<div class="vps-field"><label>Storage type</label><select id="storageType"><option>NVMe</option><option>SSD</option><option>HDD</option></select></div><div class="vps-field"><label>Storage capacity (GB) *</label><input id="storage" type="number" min="1" step="1" required value="80"></div><div class="vps-field"><label>Network port speed (Mbps)</label><input id="portSpeed" type="number" min="0" placeholder="1000"></div>
<div class="vps-field"><label>Data transfer allowance</label><input id="transfer" placeholder="Unlimited / 10 TB"></div><div class="vps-field"><label>Server location *</label><input id="location" required placeholder="London, UK"></div><div class="vps-field"><label>Region code</label><input id="region" placeholder="uk-lon-1"></div>
<div class="vps-field"><label>Operating system</label><select id="osFamily"><option value="Windows Server" selected>Windows Server</option><option value="Windows">Windows</option><option value="Ubuntu">Ubuntu</option><option value="Debian">Debian</option><option value="CentOS Stream">CentOS Stream</option><option value="Rocky Linux">Rocky Linux</option><option value="AlmaLinux">AlmaLinux</option><option value="Red Hat Enterprise Linux">Red Hat Enterprise Linux</option><option value="Fedora">Fedora</option><option value="SUSE Linux Enterprise Server">SUSE Linux Enterprise Server</option><option value="Oracle Linux">Oracle Linux</option><option value="FreeBSD">FreeBSD</option><option value="Other / Custom">Other / Custom</option></select></div><div class="vps-field"><label>Operating system year</label><select id="osYear"><?php $vpsCurrentYear=(int)date('Y'); for($vpsYear=$vpsCurrentYear;$vpsYear>=2000;$vpsYear--): ?><option value="<?= $vpsYear ?>" <?= $vpsYear===$vpsCurrentYear-1?'selected':'' ?>><?= $vpsYear ?></option><?php endfor; ?></select></div><div class="vps-field" id="osCustomWrap" style="display:none"><label>Custom operating system</label><input id="osCustom" maxlength="120" placeholder="Enter operating system"></div><div class="vps-field"><label>Virtualization type</label><input id="virtualization" placeholder="KVM"></div><div class="vps-field"><label>Backup option</label><input id="backup" placeholder="Daily backup / not included"></div>
<div class="vps-field"><label>Snapshots included</label><input id="snapshots" type="number" min="0" value="0"></div><div class="vps-field"><label>Billing period</label><select id="billing"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="semiannual">Semi-annual</option><option value="annual">Annual</option><option value="one-time">One-time</option></select></div><div class="vps-field"><label>Subscription duration (days) *</label><input id="duration" type="number" min="1" value="30" required></div>
<div class="vps-field"><label>Price *</label><input id="price" type="number" min="0" step="0.01" required value="0"></div><div class="vps-field"><label>Currency</label><select id="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>NGN</option></select></div><div class="vps-field"><label>Setup fee</label><input id="setupFee" type="number" min="0" step="0.01" value="0"></div>
<div class="vps-field"><label>Stock quantity *</label><input id="stock" type="number" min="1" required value="1"></div><div class="vps-field"><label>Publication status</label><select id="planStatus"><option value="published">Published</option><option value="draft">Save as draft</option><option value="paused">Paused</option></select></div><div class="vps-field"><label>IPv4 included</label><div class="vps-checks"><label><input type="checkbox" id="ipv4" checked> Yes</label></div></div><div class="vps-field"><label>IPv6 included</label><div class="vps-checks"><label><input type="checkbox" id="ipv6"> Yes</label></div></div>
</div><div class="vps-actions" style="margin-top:14px"><button type="submit" class="vps-btn">Save VPS Plan</button></div></form></div></div></section>
<section class="vps-panel" id="panel-payments"><div class="vps-card"><div class="vps-card-head"><h2>VPS payment submissions</h2><p>Confirm only after verifying the payment. Confirmation creates the central allocation; private credentials and addresses are entered later by the server administrator.</p></div><div class="vps-table-wrap" id="paymentsTable"><div class="vps-empty">Loading payment requests…</div></div></div></section>
<section class="vps-panel" id="panel-registered"><div class="vps-card"><div class="vps-card-head"><h2>Private VPS provisioning</h2><p>Confirmed purchases and existing account/programme VPS allocations appear here. Only server administrators can enter or edit private credentials and IP addresses.</p></div><div class="vps-card-body" style="padding-bottom:0"><div class="vps-subtabs"><button class="vps-subtab active" data-kind="accounts" onclick="VPS.switchRegistered('accounts')">Accounts</button><button class="vps-subtab" data-kind="programmes" onclick="VPS.switchRegistered('programmes')">Programmes</button></div><div class="vps-subtabs" style="margin-top:10px"><button class="vps-subtab active" data-state="unregistered" onclick="VPS.switchRegistrationState('unregistered')">Unregistered</button><button class="vps-subtab" data-state="registered" onclick="VPS.switchRegistrationState('registered')">Registered</button></div></div><div class="vps-table-wrap" id="registeredTable"><div class="vps-empty">Loading unregistered VPS records…</div></div></div></section>
</div></main>
<div class="vps-modal-backdrop" id="secretModal"><div class="vps-modal"><h3>Registered VPS private provisioning</h3><p>Advertisement details remain unchanged after sale. Re-authenticate to review or edit the credentials and IP addresses assigned to this registration.</p><div id="secretResult"></div><div class="vps-actions" style="margin-top:16px"><button type="button" class="vps-btn" onclick="VPS.unlockSecrets()">Authenticate and Edit Details</button><button type="button" class="vps-btn secondary" onclick="VPS.closeSecrets()">Close</button></div></div></div>
<div class="vps-modal-backdrop" id="adminAuthModal"><div class="vps-modal"><h3 id="adminAuthTitle">Administrator verification</h3><p id="adminAuthMessage">Enter your administrator login ID and password to continue.</p><div class="vps-field"><label for="adminAuthLogin">Administrator login ID</label><input id="adminAuthLogin" type="text" autocomplete="off" value=""></div><div class="vps-field" style="margin-top:10px"><label for="adminAuthPassword">Administrator password</label><input id="adminAuthPassword" type="password" autocomplete="current-password" value=""></div><div id="adminAuthError" class="vps-error"></div><div class="vps-actions" style="margin-top:16px"><button type="button" class="vps-btn" onclick="VPS.confirmAdminAuth()">Verify</button><button type="button" class="vps-btn secondary" onclick="VPS.cancelAdminAuth()">Cancel</button></div></div></div>
<script>
(function () {
    // Keep this standalone module synchronized with the Server App theme switch.
    window.addEventListener('message', function (event) {
        var data = event && event.data;
        if (!data || typeof data !== 'object') return;
        if (data.type === 'serverapp:module-state' && (data.theme === 'dark' || data.theme === 'light')) {
            document.documentElement.setAttribute('data-theme', data.theme);
        }
    });
})();
const VPS={csrf:<?= json_encode($serverAppCsrf) ?>,registeredKind:'accounts',registrationState:'unregistered',revealTarget:null,plans:[],authResolver:null,
async api(action,data={}){const body=new URLSearchParams({action,csrf_token:this.csrf,...data});const response=await fetch('vps_config.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body});if(response.status===419){location.reload();throw new Error('Form expired. The page is refreshing.');}const result=await response.json();if(!result.success)throw new Error(result.error||'Request failed.');return result;},
requestAdminAuth(title,message){return new Promise((resolve,reject)=>{this.authResolver={resolve,reject};document.getElementById('adminAuthTitle').textContent=title||'Administrator verification';document.getElementById('adminAuthMessage').textContent=message||'Enter administrator credentials to continue.';document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';document.getElementById('adminAuthError').textContent='';document.getElementById('adminAuthModal').classList.add('open');setTimeout(()=>document.getElementById('adminAuthLogin').focus(),80);});},
confirmAdminAuth(){const login=document.getElementById('adminAuthLogin').value.trim();const password=document.getElementById('adminAuthPassword').value;const error=document.getElementById('adminAuthError');if(!login||!password){error.textContent='Enter both administrator login ID and password.';return;}const pending=this.authResolver;this.authResolver=null;document.getElementById('adminAuthModal').classList.remove('open');document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';if(pending)pending.resolve({login_id:login,admin_password:password});},
cancelAdminAuth(){const pending=this.authResolver;this.authResolver=null;document.getElementById('adminAuthModal').classList.remove('open');document.getElementById('adminAuthLogin').value='';document.getElementById('adminAuthPassword').value='';if(pending)pending.reject(new Error('Administrator verification cancelled.'));},
notice(message,ok=true){const el=document.getElementById('vpsNotice');el.textContent=message;el.className='vps-notice show'+(ok?'':' error');},
esc(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));},money(value,currency){return this.esc(currency||'USD')+' '+Number(value||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});},
switchTab(tab){document.querySelectorAll('.vps-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));document.querySelectorAll('.vps-panel').forEach(p=>p.classList.toggle('active',p.id==='panel-'+tab));if(tab==='published')this.loadPlans();if(tab==='payments'){this.loadPayments();this.loadCount();}if(tab==='registered')this.loadRegistered();},
toggleCustomOs(){const custom=document.getElementById('osFamily').value==='Other / Custom';document.getElementById('osCustomWrap').style.display=custom?'grid':'none';document.getElementById('osCustom').required=custom;},
switchRegistered(kind){this.registeredKind=kind;document.querySelectorAll('.vps-subtab[data-kind]').forEach(b=>b.classList.toggle('active',b.dataset.kind===kind));this.loadRegistered();},
switchRegistrationState(state){if(!['unregistered','registered'].includes(state))return;this.registrationState=state;document.querySelectorAll('.vps-subtab[data-state]').forEach(b=>b.classList.toggle('active',b.dataset.state===state));this.loadRegistered();},
async loadCount(){try{const d=await this.api('vps_payment_count');document.getElementById('paymentCount').textContent=d.count;}catch(e){}},
async loadPlans(){const target=document.getElementById('plansTable');try{const d=await this.api('vps_list_plans');this.plans=d.plans||[];if(!this.plans.length){target.innerHTML='<div class="vps-empty">No VPS plans have been advertised yet.</div>';return;}let html='<table class="vps-table"><thead><tr><th>Product / Plan</th><th>Resources</th><th>Network and OS</th><th>Location</th><th>Billing</th><th>Price</th><th>Setup fee</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead><tbody>';this.plans.forEach(p=>{const specs=`<div class="vps-specs"><span>${this.esc(p.vcpu_count)} vCPU</span><span>${this.esc(p.ram_gb)} GB RAM</span><span>${this.esc(p.storage_gb)} GB ${this.esc(p.storage_type)}</span></div>`;const status=this.esc(p.status);html+=`<tr><td><b>${this.esc(p.product_name)}</b><span class="vps-meta">${this.esc(p.plan_name)} · ${this.esc(p.provider_name||'Provider not specified')}</span></td><td>${specs}</td><td>${this.esc(p.network_port_speed_mbps||'—')} Mbps<span class="vps-meta">${this.esc(p.data_transfer||'Transfer not specified')} · ${this.esc(p.operating_system||'OS not specified')}</span></td><td>${this.esc(p.server_location)}<span class="vps-meta">${this.esc(p.region_code||'')}</span></td><td>${this.esc(p.billing_period)}<span class="vps-meta">${this.esc(p.subscription_duration_days)} days</span></td><td><b>${this.money(p.price,p.currency)}</b></td><td>${this.money(p.setup_fee,p.currency)}</td><td>${this.esc(p.allocated_quantity)}/${this.esc(p.stock_quantity)}<span class="vps-meta">Buy requests: ${this.esc(p.buy_request)}</span></td><td><span class="vps-status">${status}</span></td><td><div class="vps-actions"><button class="vps-btn secondary" onclick="VPS.editPlan(${p.id})">Edit</button>${p.status!=='sold_out'?`<button class="vps-btn secondary" onclick="VPS.setStatus(${p.id},'${p.status==='published'?'paused':'published'}')">${p.status==='published'?'Pause':'Publish'}</button>`:''}</div></td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load plans. Run server_available_vps.sql first.</div>';this.notice(e.message,false);}},
async editPlan(id){try{const d=await this.api('vps_get_plan',{id});const p=d.plan;const map={planId:'id',productName:'product_name',providerName:'provider_name',planName:'plan_name',planDescription:'plan_description',cpuType:'cpu_type',vcpu:'vcpu_count',ram:'ram_gb',storageType:'storage_type',storage:'storage_gb',portSpeed:'network_port_speed_mbps',transfer:'data_transfer',location:'server_location',region:'region_code',virtualization:'virtualization_type',backup:'backup_option',snapshots:'snapshot_count',billing:'billing_period',duration:'subscription_duration_days',price:'price',currency:'currency',setupFee:'setup_fee',stock:'stock_quantity',planStatus:'status'};Object.entries(map).forEach(([element,key])=>{const node=document.getElementById(element);if(node)node.value=p[key]??'';});const osText=String(p.operating_system||'');const osMatch=osText.match(/^(.*?)(?:\s+(\d{4}))?$/);const osName=(osMatch?.[1]||'Windows Server').trim();const yearValue=osMatch?.[2]||String(new Date().getFullYear()-1);const family=document.getElementById('osFamily');const options=Array.from(family.options).map(o=>o.value);if(options.includes(osName)){family.value=osName;document.getElementById('osCustom').value='';}else{family.value='Other / Custom';document.getElementById('osCustom').value=osName;}document.getElementById('osYear').value=yearValue;this.toggleCustomOs();document.getElementById('ipv4').checked=!!Number(p.ipv4_included);document.getElementById('ipv6').checked=!!Number(p.ipv6_included);document.getElementById('planFormTitle').textContent='Edit VPS plan #'+id;this.switchTab('advertise');}catch(e){this.notice(e.message,false);}},
async savePlan(ev){ev.preventDefault();const selectedOs=document.getElementById('osFamily').value;const osName=selectedOs==='Other / Custom'?document.getElementById('osCustom').value.trim():selectedOs;if(!osName){this.notice('Enter the custom operating system name.',false);return;}const data={id:document.getElementById('planId').value,product_name:document.getElementById('productName').value,provider_name:document.getElementById('providerName').value,plan_name:document.getElementById('planName').value,plan_description:document.getElementById('planDescription').value,cpu_type:document.getElementById('cpuType').value,vcpu_count:document.getElementById('vcpu').value,ram_gb:document.getElementById('ram').value,storage_type:document.getElementById('storageType').value,storage_gb:document.getElementById('storage').value,network_port_speed_mbps:document.getElementById('portSpeed').value,data_transfer:document.getElementById('transfer').value,server_location:document.getElementById('location').value,region_code:document.getElementById('region').value,operating_system:`${osName} ${document.getElementById('osYear').value}`.trim(),virtualization_type:document.getElementById('virtualization').value,backup_option:document.getElementById('backup').value,snapshot_count:document.getElementById('snapshots').value,billing_period:document.getElementById('billing').value,subscription_duration_days:document.getElementById('duration').value,price:document.getElementById('price').value,currency:document.getElementById('currency').value,setup_fee:document.getElementById('setupFee').value,stock_quantity:document.getElementById('stock').value,ipv4_included:document.getElementById('ipv4').checked?'1':'0',ipv6_included:document.getElementById('ipv6').checked?'1':'0',status:document.getElementById('planStatus').value};try{const auth=await this.requestAdminAuth('Save VPS plan','Verify your administrator credentials to save or publish this VPS plan.');Object.assign(data,auth);const d=await this.api('vps_save_plan',data);this.notice(d.message,true);document.getElementById('planId').value=d.id;this.switchTab('published');}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);}},
async setStatus(id,status){try{const auth=await this.requestAdminAuth('Update VPS plan','Verify administrator credentials to change this plan status.');await this.api('vps_set_plan_status',{id,status,...auth});this.notice('VPS plan status updated.');this.loadPlans();}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);}},
async loadPayments(){const target=document.getElementById('paymentsTable');try{const d=await this.api('vps_get_payments');const rows=d.payments||[];if(!rows.length){target.innerHTML='<div class="vps-empty">No VPS payment submissions are waiting for review.</div>';return;}let html='<table class="vps-table"><thead><tr><th>Buyer</th><th>Purchase target</th><th>Plan</th><th>Amount</th><th>Payment method</th><th>Reference</th><th>Submitted</th><th>Status</th><th>Decision</th></tr></thead><tbody>';rows.forEach(r=>{const targetName=r.request_type==='programme'?`Programme: ${this.esc(r.program_name||'#'+r.programme_id)}`:`Investor subaccount #${this.esc(r.sub_account_id)}`;const state=r.payment_status==='payment-confirmed'?'good':(r.payment_status==='payment-failed'?'failed':'pending');const stateLabel=r.payment_status==='payment-confirmed'?(r.registration_status==='registered'?'Payment confirmed · Registration complete':'Payment confirmed · Needs attention'):(r.payment_status==='payment-failed'?'Payment failed':(r.payment_status==='payment-made'?'Payment submitted · Review':'Awaiting payment'));let decision='';if(r.payment_status==='payment-made'){decision=`<div class="vps-actions"><button class="vps-btn" onclick="VPS.decidePayment(${r.id},'confirm')">Confirm Payment &amp; Create Registration</button><button class="vps-btn danger" onclick="VPS.decidePayment(${r.id},'fail')">Payment failed</button></div>`;}else decision=this.esc(r.allocation_message||r.request_status);html+=`<tr><td><b>${this.esc(r.request_name)}</b><span class="vps-meta">${this.esc(r.request_email)}</span><span class="vps-meta">HarvHub row #${this.esc(r.user_id)}</span></td><td>${targetName}<span class="vps-meta">Subaccount #${this.esc(r.sub_account_id)}</span></td><td>${this.esc(r.product_name)}<span class="vps-meta">${this.esc(r.plan_name)}</span></td><td><b>${this.money(r.amount,r.currency)}</b></td><td>${this.esc(r.payment_method||'—')}<span class="vps-meta">${this.esc(r.payment_network||'')}</span></td><td>${this.esc(r.payment_reference||'—')}</td><td>${this.esc(r.payment_made_at||'—')}</td><td><span class="vps-status ${state}">${this.esc(stateLabel)}</span>${r.request_status==='allocation_conflict'?'<span class="vps-meta">Allocation needs review</span>':''}</td><td>${decision}</td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load payment requests. Run the VPS SQL migration.</div>';this.notice(e.message,false);}},
async decidePayment(id,decision){if(decision==='confirm'&&!window.confirm('Confirm this payment and create the VPS allocation for the recorded account or programme? Private provisioning will be completed by the server administrator.'))return;try{const auth=await this.requestAdminAuth(decision==='confirm'?'Confirm VPS payment':'Mark VPS payment failed','Verify administrator credentials to process this payment decision.');const d=await this.api('vps_decide_payment',{request_id:id,decision,...auth});this.notice(d.message||'Payment decision saved.');this.loadPayments();this.loadCount();this.loadPlans();if(decision==='confirm')this.loadRegistered();}catch(e){if(e.message!=='Administrator verification cancelled.')this.notice(e.message,false);this.loadPayments();}},
async loadRegistered(silent=false){const target=document.getElementById('registeredTable');if(!silent)target.innerHTML='<div class="vps-empty">Loading '+this.registrationState+' VPS records…</div>';try{const d=await this.api('vps_get_registered',{target_type:this.registeredKind});const allRows=d.rows||[];const rows=allRows.filter(r=>(r.registration_status==='registered')===(this.registrationState==='registered'));if(!rows.length){target.innerHTML='<div class="vps-empty">No '+this.registrationState+' VPS records found in this category.</div>';return;}let html='<table class="vps-table" style="min-width:1900px"><thead><tr><th>Target</th><th>Buyer</th><th>VPS allocation</th><th>Sold advertisement snapshot</th><th>Resources</th><th>Network / OS</th><th>Location</th><th>Billing / price</th><th>Included features</th><th>Provisioning status</th><th>Computer username</th><th>Computer password</th><th>IPv4</th><th>IPv6</th><th>Private details</th></tr></thead><tbody>';rows.forEach(r=>{const pending=r.registration_status!=='registered';const status=pending?'<span class="vps-status pending">Unregistered · Needs provisioning</span>':'<span class="vps-status good">Registered</span>';const mask='<span class="vps-lock">••••••••</span>';const secret=(v)=>Number(v)===1?mask:'<span class="vps-meta">Not set</span>';const duration=Number(r.subscription_duration_days||0);const location=String(r.plan_location||r.v_server_location||'');const billing=[r.billing_period,duration?duration+' days':''].filter(Boolean).join(' · ');const purchaseId=Number(r.request_id||0);const opId=Number(r.registration_id||0);html+=`<tr><td><b>${this.esc(r.target_name)}</b><span class="vps-meta">${r.target_type==='programme'?'Programme':'Investor subaccount'} #${r.target_type==='programme'?this.esc(r.programme_id):this.esc(r.sub_account_id)}</span><span class="vps-meta">Subaccount #${this.esc(r.sub_account_id)}</span></td><td><b>${this.esc(r.request_name)}</b><span class="vps-meta">${this.esc(r.request_email)}</span><span class="vps-meta">HarvHub #${this.esc(r.user_id)}</span></td><td><b>VPS allocation #${this.esc(r.registered_vps_id)}</b>${opId?'<span class="vps-meta">Operational VPS #'+this.esc(opId)+'</span>':''}${purchaseId?'<span class="vps-meta">Purchase #'+this.esc(purchaseId)+'</span>':'<span class="vps-meta">Legacy VPS allocation</span>'}</td><td><b>${this.esc(r.product_name||'VPS plan')}</b><span class="vps-meta">${this.esc(r.plan_name||'')}</span><span class="vps-meta">${this.esc(r.provider_name||'')}</span><span class="vps-meta">${this.esc(r.plan_description||'')}</span></td><td>${this.esc(r.vcpu_count||'')} vCPU<span class="vps-meta">${this.esc(r.ram_gb||'')} GB RAM · ${this.esc(r.storage_gb||'')} GB ${this.esc(r.storage_type||'')}</span></td><td>${r.network_port_speed_mbps?this.esc(r.network_port_speed_mbps)+' Mbps':''}<span class="vps-meta">${this.esc(r.data_transfer||'')}</span><span class="vps-meta">${this.esc(r.operating_system||'')} ${this.esc(r.virtualization_type||'')}</span></td><td>${this.esc(location)}<span class="vps-meta">${this.esc(r.region_code||'')}</span></td><td>${this.esc(billing)}<span class="vps-meta">${this.money(r.plan_price??r.amount,r.plan_currency||r.currency)}</span><span class="vps-meta">Setup: ${this.money(r.setup_fee||0,r.plan_currency||r.currency)}</span></td><td>${Number(r.ipv4_included)?'IPv4':''}${Number(r.ipv4_included)&&Number(r.ipv6_included)?' · ':''}${Number(r.ipv6_included)?'IPv6':''}<span class="vps-meta">${this.esc(r.backup_option||'')}${Number(r.snapshot_count)>0?' · '+this.esc(r.snapshot_count)+' snapshots':''}</span></td><td>${status}</td><td>${secret(r.has_computer_username)}</td><td>${secret(r.has_computer_password)}</td><td>${secret(r.has_ipv4_address)}</td><td>${secret(r.has_ipv6_address)}</td><td><button class="vps-btn secondary" onclick="VPS.openSecrets('${this.registeredKind}',${opId},${purchaseId},${Number(r.registered_vps_id)})">${pending?'Register / edit private details':'Edit private details'}</button></td></tr>`;});html+='</tbody></table>';target.innerHTML=html;}catch(e){target.innerHTML='<div class="vps-empty">Unable to load '+this.registrationState+' VPS records. Check that the VPS schema migration completed successfully.</div>';this.notice(e.message,false);}},
openSecrets(type,registrationId,requestId,registeredVpsId){this.revealTarget={type,registrationId,requestId,registeredVpsId,auth:null};document.getElementById('secretResult').innerHTML='';document.getElementById('secretModal').classList.add('open');},
closeSecrets(){document.getElementById('secretModal').classList.remove('open');this.revealTarget=null;document.getElementById('secretResult').innerHTML='';},
async unlockSecrets(){if(!this.revealTarget)return;try{const auth=await this.requestAdminAuth('Edit registered VPS provisioning details','Re-enter administrator credentials to review or edit the four private values for this specific registration.');this.revealTarget.auth=auth;const d=await this.api('vps_reveal_registered',{target_type:this.revealTarget.type,registered_vps_id:this.revealTarget.registeredVpsId,request_id:this.revealTarget.requestId,...auth});const f=d.private||{};let html='<div class="vps-grid">';const fields=[['computer_username','Computer username','text'],['computer_password','Computer password','password'],['ipv4_address','IPv4 address','text'],['ipv6_address','IPv6 address','text']];fields.forEach(([key,label,inputType])=>{html+=`<div class="vps-field"><label for="registered_${key}">${label}</label><input id="registered_${key}" type="${inputType}" value="${this.esc(f[key]||'')}" autocomplete="off"></div>`;});html+='</div><p class="vps-meta">Leave a value blank to clear it. IPv4 and IPv6 values are validated before saving. Advertisement specifications remain read-only.</p><div class="vps-actions" style="margin-top:12px"><button type="button" class="vps-btn" onclick="VPS.saveRegisteredPrivate()">Save provisioning changes</button></div>';document.getElementById('secretResult').innerHTML=html;}catch(e){if(e.message!=='Administrator verification cancelled.')document.getElementById('secretResult').textContent=e.message;}},
async saveRegisteredPrivate(){if(!this.revealTarget||!this.revealTarget.auth)return;try{const data={target_type:this.revealTarget.type,registered_vps_id:this.revealTarget.registeredVpsId,computer_username:document.getElementById('registered_computer_username').value,computer_password:document.getElementById('registered_computer_password').value,ipv4_address:document.getElementById('registered_ipv4_address').value,ipv6_address:document.getElementById('registered_ipv6_address').value,...this.revealTarget.auth};const d=await this.api('vps_save_registered_private',data);this.notice(d.message||'Provisioning details updated.');if(d.registration_status==='registered'){this.registrationState='registered';document.querySelectorAll('.vps-subtab[data-state]').forEach(b=>b.classList.toggle('active',b.dataset.state==='registered'));document.getElementById('secretResult').insertAdjacentHTML('afterbegin','<div class="vps-status good">Registration complete</div>');}else{this.registrationState='unregistered';document.querySelectorAll('.vps-subtab[data-state]').forEach(b=>b.classList.toggle('active',b.dataset.state==='unregistered'));document.getElementById('secretResult').insertAdjacentHTML('afterbegin','<div class="vps-status pending">Needs attention: required values are incomplete</div>');}this.loadRegistered(true);}catch(e){this.notice(e.message,false);}},
init(){document.getElementById('planForm').addEventListener('submit',e=>this.savePlan(e));document.getElementById('osFamily').addEventListener('change',()=>this.toggleCustomOs());this.toggleCustomOs();this.loadPlans();this.loadCount();this.paymentCountTimer=setInterval(()=>{if(!document.hidden&&window.__serverAppActive!==false)this.loadCount();},12000);this.paymentTableTimer=setInterval(()=>{if(!document.hidden&&window.__serverAppActive!==false&&document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();},25000);this.liveRefreshTimer=setInterval(()=>{if(document.hidden||window.__serverAppActive===false)return;this.loadCount();if(document.getElementById('panel-published')?.classList.contains('active'))this.loadPlans();if(document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();if(document.getElementById('panel-registered')?.classList.contains('active'))this.loadRegistered(true);},35000);window.addEventListener('serverapp:activechange',e=>{if(e.detail?.active){this.loadCount();const panel=document.querySelector('.vps-panel.active');if(panel?.id==='panel-published')this.loadPlans();if(panel?.id==='panel-payments')this.loadPayments();if(panel?.id==='panel-registered')this.loadRegistered(true);}});document.addEventListener('visibilitychange',()=>{if(!document.hidden&&window.__serverAppActive!==false){this.loadCount();if(document.getElementById('panel-payments')?.classList.contains('active'))this.loadPayments();}});document.getElementById('secretModal').addEventListener('click',e=>{if(e.target.id==='secretModal')this.closeSecrets();});document.getElementById('adminAuthModal').addEventListener('click',e=>{if(e.target.id==='adminAuthModal')this.cancelAdminAuth();});document.getElementById('adminAuthPassword').addEventListener('keydown',e=>{if(e.key==='Enter')this.confirmAdminAuth();});}
};document.addEventListener('DOMContentLoaded',()=>VPS.init());
</script>
</main></body></html>
