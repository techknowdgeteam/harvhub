<?php
/** Investors Contracts Payments — grouped by HarvHub parent, detailed per subaccount and contract. */
require_once __DIR__ . '/serverapp_guard.php';

function icpTableExists(PDO $pdo,string $table):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$table]);return (int)$q->fetchColumn()>0;}
function icpColumns(PDO $pdo,string $table):array{$q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');$q->execute([$table]);return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);}
function icpStatus($value):string{
 $s=strtolower(trim((string)$value));if($s==='')return '';
 $cancelled=strpos($s,'cancelled')!==false||strpos($s,'canceled')!==false||strpos($s,'contract_cancelled')!==false;
 $base='';
 if(strpos($s,'payment-confirmed')!==false||strpos($s,'payment_confirmed')!==false||strpos($s,'paymentconfirmed')!==false)$base='payment-confirmed';
 elseif(strpos($s,'payment-made')!==false||strpos($s,'payment_made')!==false||strpos($s,'paymentmade')!==false||strpos($s,'payment-pending')!==false)$base='payment-made';
 elseif(strpos($s,'failed-payment')!==false||strpos($s,'failed_payment')!==false||strpos($s,'payment-failed')!==false||strpos($s,'payment_failed')!==false||$s==='failed')$base='payment-failed';
 elseif(strpos($s,'unpaid-payment')!==false||strpos($s,'unpaid_payment')!==false||strpos($s,'payment-required')!==false||strpos($s,'payment_required')!==false||strpos($s,'unpaid')!==false||strpos($s,'pending-payment')!==false)$base='unpaid-payment';
 if($base!=='')return $base;
 if($cancelled)return 'contract-cancelled';
 return str_replace(['_',' '],'-',$s);
}
function icpIsPaymentStatus(string $status):bool{return in_array($status,['unpaid-payment','payment-made','payment-confirmed','payment-failed'],true);}
function icpName(array $row):string{foreach(['sub_account_name','username','fullname','first_name'] as $k){$v=trim((string)($row[$k]??''));if($v!=='')return $v;} $email=trim((string)($row['email']??''));return $email!==''?(str_contains($email,'@')?strstr($email,'@',true):$email):'Account';}
function icpMoney($v):string{return '$'.number_format((float)$v,2);}
function icpDate($v):string{$v=trim((string)$v);if($v===''||$v==='0000-00-00'||$v==='0000-00-00 00:00:00')return '—';try{return (new DateTimeImmutable($v))->format('Y-m-d');}catch(Throwable $e){return $v;}}
function icpContractState(array $r,int $defaultDays):string{
 $start=trim((string)($r['execution_start_date']??''));$end=trim((string)($r['execution_end_date']??''));
 if($start===''||$start==='0000-00-00')return 'No contract date';
 try{$begin=new DateTimeImmutable($start);$finish=($end!==''&&$end!=='0000-00-00')?new DateTimeImmutable($end):$begin->modify('+'.max(1,$defaultDays).' days');$today=new DateTimeImmutable('today');if($today<$finish)return 'In contract · '.$today->diff($finish)->days.' days left';return 'Ended · '.$finish->format('Y-m-d');}catch(Throwable $e){return 'Date unavailable';}
}
function icpHistoryMatch(array $h,array $historyCols,array $usersById,array $usersBySub,array $usersByContract,array $emailCounts):?int{
 $email=strtolower(trim((string)($h['user_email']??'')));
 if(isset($historyCols['investor_user_id'])&&(int)($h['investor_user_id']??0)>0){$id=(int)$h['investor_user_id'];if(isset($usersById[$id])){$candidateSub=(int)($usersById[$id]['sub_account_id']??$id);$historySub=(int)($h['sub_account_id']??0);if($historySub<=0||$candidateSub===$historySub)return $id;}}
 if(isset($historyCols['sub_account_id'])&&(int)($h['sub_account_id']??0)>0){$sid=(int)$h['sub_account_id'];$candidates=$usersBySub[$sid]??[];if($email!==''){$matches=[];foreach($candidates as $candidateId){if(strtolower(trim((string)($usersById[$candidateId]['email']??'')))===$email)$matches[]=$candidateId;}if(count($matches)===1)return (int)$matches[0];}elseif(count($candidates)===1){return (int)$candidates[0];}}
 // Legacy revenue_history often has no subaccount identity, but contract_id can still identify the exact HarvHub row.
 $contractId=trim((string)($h['contract_id']??''));if($contractId!==''&&isset($usersByContract[$contractId])){$candidates=$usersByContract[$contractId];$matches=[];foreach($candidates as $candidateId){$candidateEmail=strtolower(trim((string)($usersById[$candidateId]['email']??'')));if($email===''||$candidateEmail===$email)$matches[]=$candidateId;}if(count($matches)===1)return (int)$matches[0];}
 if($email!==''&&($emailCounts[$email]??0)===1){foreach($usersById as $id=>$u)if(strtolower((string)($u['email']??''))===$email)return (int)$id;}
 return null;
}

$defaultDuration=max(1,(int)($serverAppConfig['contract_duration']??30));
$minProfit=(float)($serverAppConfig['min_profit_for_split']??30);
$hasHistory=icpTableExists($pdo,'revenue_history');
$hasProgrammes=icpTableExists($pdo,'programme');
$hasProgrammeInvestors=icpTableExists($pdo,'programme_investors');
$users=$pdo->query('SELECT * FROM harvhub ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$usersById=[];$usersBySub=[];$usersByContract=[];$emailCounts=[];
foreach($users as $u){$id=(int)($u['id']??0);if($id<=0)continue;$usersById[$id]=$u;$sid=(int)($u['sub_account_id']??$id);if($sid<=0)$sid=$id;$usersBySub[$sid][]=$id;$contractId=trim((string)($u['contract_id']??''));if($contractId!=='')$usersByContract[$contractId][]=$id;$em=strtolower(trim((string)($u['email']??'')));if($em!=='')$emailCounts[$em]=($emailCounts[$em]??0)+1;}

$historyCols=$hasHistory?icpColumns($pdo,'revenue_history'):[];
$historyRows=[];
if($hasHistory){try{$historyRows=$pdo->query('SELECT * FROM revenue_history ORDER BY created_at DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){error_log('Investors Contracts Payments history read failed: '.$e->getMessage());}}
$historyByUser=[];$unassignedHistory=0;
foreach($historyRows as $h){$matchId=icpHistoryMatch($h,$historyCols,$usersById,$usersBySub,$usersByContract,$emailCounts);if($matchId===null){$unassignedHistory++;continue;}$historyByUser[$matchId][]=$h;}

// Build a page-local contract payment list for every subaccount. A direct harvhub status is used only
// when the current contract does not already have a corresponding history record.
$recordsByUser=[];$subStateByUser=[];
foreach($usersById as $id=>$u){
 $sid=(int)($u['sub_account_id']??$id);if($sid<=0)$sid=$id;
 $duration=$defaultDuration;
 $dealData=['programme_id'=>0,'programme_name'=>'','developer_percentage'=>(float)($serverAppConfig['server_share_percent']??30),'investor_percentage'=>(float)($serverAppConfig['user_share_percent']??70)];
 if($hasProgrammeInvestors){try{
  $q=$pdo->prepare('SELECT pi.contract_duration,pi.developer_percentage,pi.investor_percentage,pi.programme_id,p.program_name FROM programme_investors pi LEFT JOIN programme p ON p.id=pi.programme_id WHERE pi.investorid=? AND (pi.sub_account_id=? OR pi.sub_account_id IS NULL) ORDER BY pi.id DESC LIMIT 1');
  $q->execute([$id,$sid]);$deal=$q->fetch(PDO::FETCH_ASSOC)?:[];
  if((int)($deal['contract_duration']??0)>0)$duration=(int)$deal['contract_duration'];
  if((float)($deal['developer_percentage']??0)>0)$dealData['developer_percentage']=(float)$deal['developer_percentage'];
  if((float)($deal['investor_percentage']??0)>0)$dealData['investor_percentage']=(float)$deal['investor_percentage'];
  $dealData['programme_id']=(int)($deal['programme_id']??0);$dealData['programme_name']=(string)($deal['program_name']??'');
 }catch(Throwable $e){}}
 $items=[];
 foreach(($historyByUser[$id]??[]) as $h){
  $status=icpStatus($h['loyalties']??'');
  $profit=(float)($h['profit']??0);
  if(!icpIsPaymentStatus($status)&&in_array($status,['active','contract-active',''],true)){
   $end=trim((string)($h['execution_end_date']??''));
   if($end!==''&&$end!=='0000-00-00'&&$profit>$minProfit&&strtotime($end.' 23:59:59')<time())$status='unpaid-payment';
  }
  if(!icpIsPaymentStatus($status))continue;
  $amount=(float)($h['server_share']??0);
  $programmeName=trim((string)($h['programme_name']??''));
  if($programmeName==='')$programmeName=trim((string)($h['invested_with']??''));
  $items[]=[
   'status'=>$status,'contract_id'=>(string)($h['contract_id']??''),'programme_name'=>$programmeName,'programme_id'=>(int)($h['programme_id']??0),
   'start'=>(string)($h['execution_start_date']??''),'end'=>(string)($h['execution_end_date']??''),'profit'=>$profit,
   'investor_share'=>(float)($h['user_share']??0),'programme_share'=>(float)($h['server_share']??0),'amount'=>$amount,
   'date'=>(string)($h['payment_date']??$h['updated_at']??$h['created_at']??''),
   'reference'=>(string)($h['payment_reference']??$h['payment_details']??$h['transaction_reference']??''),
   'method'=>(string)($h['payment_method']??''),'currency'=>(string)($h['currency']??'USD'),
   'contract_state'=>icpContractState($h,$duration)
  ];
 }
 $currentStatus=icpStatus($u['loyalties']??'');
 $currentContract=(string)($u['contract_id']??'');
 $start=(string)($u['execution_start_date']??'');
 $currentRecorded=false;
 foreach($items as $item){
  if(($currentContract!==''&&$item['contract_id']===$currentContract)||($start!==''&&$item['start']===$start)){$currentRecorded=true;break;}
 }
 if(icpIsPaymentStatus($currentStatus)&&!$currentRecorded){
  $profit=(float)($u['profitandloss']??0);$end='';
  if($start!==''&&$start!=='0000-00-00'){try{$end=(new DateTimeImmutable($start))->modify('+'.$duration.' days')->format('Y-m-d');}catch(Throwable $e){}}
  $programmeShare=(float)($u['server_share']??0);$investorShare=(float)($u['user_share']??0);
  if($programmeShare<=0&&$profit>0)$programmeShare=round($profit*$dealData['developer_percentage']/100,2);
  if($investorShare<=0&&$profit>0)$investorShare=round($profit*$dealData['investor_percentage']/100,2);
  $items[]=['status'=>$currentStatus,'contract_id'=>$currentContract,'programme_name'=>$dealData['programme_name'],'programme_id'=>$dealData['programme_id'],'start'=>$start,'end'=>$end,'profit'=>$profit,'investor_share'=>$investorShare,'programme_share'=>$programmeShare,'amount'=>$programmeShare,'date'=>(string)($u['payment_date']??$u['updated_at']??''),'reference'=>(string)($u['payment_reference']??$u['paymentdetails']??''),'method'=>'','currency'=>'USD','contract_state'=>icpContractState(['execution_start_date'=>$start,'execution_end_date'=>$end],$duration)];
  $currentRecorded=true;
 }
 // Older installations did not always persist an unpaid state onto harvhub/revenue_history.
 // Reconstruct the current payable contract if its timer and profit satisfy the same contract rule.
 if(!$currentRecorded&&$currentStatus!=='contract-cancelled'&&$start!==''&&$start!=='0000-00-00'&&(float)($u['profitandloss']??0)>$minProfit){
  try{
   $profit=(float)$u['profitandloss'];$finish=(new DateTimeImmutable($start))->modify('+'.$duration.' days');
   if(new DateTimeImmutable('today')>=$finish){
    $share=round($profit*$dealData['developer_percentage']/100,2);
    $investorShare=round($profit*$dealData['investor_percentage']/100,2);
    $items[]=['status'=>'unpaid-payment','contract_id'=>$currentContract,'programme_name'=>$dealData['programme_name'],'programme_id'=>$dealData['programme_id'],'start'=>$start,'end'=>$finish->format('Y-m-d'),'profit'=>$profit,'investor_share'=>$investorShare,'programme_share'=>$share,'amount'=>$share,'date'=>'','reference'=>'','method'=>'','currency'=>'USD','contract_state'=>'Contract ended · '.$finish->format('Y-m-d')];
   }
  }catch(Throwable $e){}
 }
 $recordsByUser[$id]=$items;
 $subStateByUser[$id]=['sub_id'=>$sid,'name'=>icpName($u),'email'=>(string)($u['email']??''),'username'=>(string)($u['username']??''),'user_id'=>$id,'contract'=>icpContractState($u,$duration)];
 // Attach a programme name when the history row only contains an investor/developer relation.
 if($hasProgrammes&&$hasProgrammeInvestors){try{$q=$pdo->prepare('SELECT DISTINCT p.id,p.program_name FROM programme_investors pi INNER JOIN programme p ON p.id=pi.programme_id WHERE pi.investorid=? AND (pi.sub_account_id=? OR pi.sub_account_id IS NULL)');$q->execute([$id,$sid]);$rel=$q->fetchAll(PDO::FETCH_ASSOC);$fallback=count($rel)===1?(string)($rel[0]['program_name']??''):'';foreach($recordsByUser[$id] as &$ri){if($ri['programme_name']===''&&$fallback!=='')$ri['programme_name']=$fallback;}unset($ri);}catch(Throwable $e){}}

}

// Group every HarvHub row under its parent, then keep only parents with at least one payment status.
$groups=[];
foreach($usersById as $id=>$u){
 $mainId=(int)($u['main_account_id']??0);$groupId=$mainId>0?$mainId:$id;
 if(!isset($groups[$groupId]))$groups[$groupId]=['id'=>$groupId,'parent'=>null,'children'=>[]];
 if($id===$groupId||(int)($u['is_main_account']??0)===1)$groups[$groupId]['parent']=$u;
 $groups[$groupId]['children'][]=['user'=>$u,'items'=>$recordsByUser[$id]??[],'state'=>$subStateByUser[$id]??[]];
}
$visibleGroups=[];$summary=['groups'=>0,'unpaid'=>0,'made'=>0,'confirmed'=>0,'failed'=>0,'attention'=>0,'contracts'=>0];
foreach($groups as $g){
 $counts=['unpaid'=>0,'made'=>0,'confirmed'=>0,'failed'=>0];$hasPayment=false;
 foreach($g['children'] as $child){foreach($child['items'] as $it){$hasPayment=true;$summary['contracts']++;$k=match($it['status']){'unpaid-payment'=>'unpaid','payment-made'=>'made','payment-confirmed'=>'confirmed','payment-failed'=>'failed',default=>null};if($k){$counts[$k]++;$summary[$k]++;}}}
 if(!$hasPayment)continue;
 $parent=$g['parent']??($g['children'][0]['user']??['id'=>$g['id'],'email'=>'','fullname'=>'Account']);
 $g['parent']=$parent;$g['parent_name']=icpName($parent);$g['parent_email']=(string)($parent['email']??'');$g['parent_username']=(string)($parent['username']??'');$g['parent_id']=(int)($parent['id']??$g['id']);$g['counts']=$counts;
 $g['search']=strtolower($g['parent_name'].' '.$g['parent_email'].' '.$g['parent_username'].' '.$g['parent_id'].' '.implode(' ',array_map(function($c){return icpName($c['user']).' '.($c['user']['username']??'').' '.($c['user']['email']??'').' '.($c['state']['sub_id']??'').' '.($c['state']['user_id']??'');},$g['children'])));
 $visibleGroups[]=$g;$summary['groups']++;$summary['attention']+=$counts['unpaid']+$counts['made']+$counts['failed'];
}
usort($visibleGroups,fn($a,$b)=>strcasecmp($a['parent_name'],$b['parent_name']));
$tabMap=['all'=>'all','unpaid-payment'=>'unpaid-payment','payment-made'=>'payment-made','payment-confirmed'=>'payment-confirmed','payment-failed'=>'payment-failed'];
if ($isServerAppAjax && (string)($_POST['action'] ?? '') === 'revenue_live_snapshot') {
 serverAppJson(['success'=>true,'summary'=>$summary,'unassigned_history'=>$unassignedHistory,'updated_at'=>date(DATE_ATOM)]);
}
?>
<!doctype html>
<html lang="en" data-theme="<?= serverAppH($serverAppTheme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Investors Contracts Payments</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<?php require_once __DIR__ . '/server_feature_style.php'; ?>
<style>
:root{--page:var(--bg-color);--panel:var(--container-bg);--ink:var(--text-color);--muted:var(--text-muted);--line:var(--border-color);--soft:var(--bg-secondary);--head:var(--table-header-bg)}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;background:var(--page);color:var(--ink);font:14px/1.5 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}body{padding:18px}.wrap{max-width:1500px;margin:0 auto}.head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:16px}.head h1{font-size:23px;margin:0}.head p{margin:4px 0 0;color:var(--muted)}.btn{border:1px solid var(--line);background:var(--panel);color:var(--ink);border-radius:9px;padding:9px 13px;font-weight:700;cursor:pointer}.metrics{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:11px;margin:0 0 16px}.metric{background:var(--panel);border:1px solid var(--line);border-radius:13px;padding:13px 15px;text-align:left;cursor:pointer;color:var(--ink)}.metric span{display:block;color:var(--muted);font-size:12px}.metric strong{display:block;font-size:21px;margin-top:3px}.metric.active{outline:2px solid var(--accent-color,#2463a0);border-color:var(--accent-color,#2463a0)}.tabs{display:flex;gap:7px;overflow:auto;padding:6px;background:var(--soft);border:1px solid var(--line);border-radius:11px;margin-bottom:13px}.tab{border:0;background:transparent;color:var(--text-secondary);padding:9px 13px;border-radius:8px;white-space:nowrap;font-weight:750;cursor:pointer}.tab.active{background:var(--accent-color,#2463a0);color:white}.tab b{display:inline-block;margin-left:5px;padding:1px 6px;border-radius:99px;background:rgba(120,130,145,.18);font-size:10px}.panel{background:var(--panel);border:1px solid var(--line);border-radius:14px;overflow:hidden}.panel-head{padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:12px}.panel-head h2{margin:0;font-size:16px}.panel-head input{width:min(360px,100%);padding:10px 12px;border:1px solid var(--line);border-radius:9px;background:var(--input-bg);color:var(--ink)}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:11px 13px;text-align:left;vertical-align:top;border-bottom:1px solid var(--line)}th{background:var(--head);color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em}.parent-row{cursor:pointer}.parent-row:hover{background:var(--soft)}.parent-row td:first-child{font-weight:800}.chevron{display:inline-block;width:18px;color:var(--accent-color);transition:transform .15s}.parent-row[aria-expanded=true] .chevron{transform:rotate(90deg)}.parent-meta,.muted{display:block;color:var(--muted);font-size:12px;font-weight:450}.child-row{display:none;background:var(--soft)}.child-row.open{display:table-row}.child-wrap{padding:10px 12px 14px 28px}.child-table{min-width:1250px;background:var(--panel)}.child-table th{background:var(--head)}.pill{display:inline-flex;padding:4px 8px;border-radius:99px;font-size:11px;font-weight:800;white-space:nowrap}.pill.unpaid{background:#fff1d5;color:#855400}.pill.made{background:#e6f1ff;color:#205b96}.pill.confirmed{background:#dcf5e8;color:#116340}.pill.failed{background:#ffe4e4;color:#9f2626}.pill.none{background:var(--bg-tertiary);color:var(--muted)}.amounts{display:flex;gap:6px;flex-wrap:wrap;font-size:12px}.amounts span{white-space:nowrap}.empty{padding:52px 20px;text-align:center;color:var(--muted)}.empty strong{display:block;color:var(--ink);font-size:17px;margin-bottom:5px}.note{padding:12px 15px;border-top:1px solid var(--line);font-size:12px;color:var(--muted)}.search-match{background:rgba(50,130,200,.08)}@media(max-width:900px){body{padding:12px}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.head{flex-direction:column;align-items:flex-start}.panel-head{flex-direction:column;align-items:stretch}.panel-head input{width:100%}}
@media(max-width:560px){body{padding:9px}.wrap{width:100%}.head h1{font-size:19px}.head p{font-size:12px}.metrics{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.metric{padding:11px}.metric strong{font-size:18px}.tabs{gap:5px}.tab{font-size:11px;padding:8px 10px}.panel-head{padding:12px}.table-scroll{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}table{min-width:760px}.child-table{min-width:1100px}.child-wrap{padding:8px}}
</style>
</head>
<body><main class="wrap">
<header class="head"><div><h1>Investors Contracts Payments</h1><p>Investor contract payments are tracked by parent account and subaccount. Confirmed payments are good terms and require no attention.</p></div><span id="revenueLiveStatus" class="muted" aria-live="polite">Live updates enabled</span></header>
<section class="metrics" aria-label="Contract payment summary">
<button class="metric active" data-metric="all" onclick="setPaymentTab('all')"><span>Accounts with payment records</span><strong id="revenue-metric-groups"><?= (int)$summary['groups'] ?></strong></button>
<button class="metric" data-metric="unpaid-payment" onclick="setPaymentTab('unpaid-payment')"><span>Unpaid contract payments</span><strong id="revenue-metric-unpaid"><?= (int)$summary['unpaid'] ?></strong></button>
<button class="metric" data-metric="payment-made" onclick="setPaymentTab('payment-made')"><span>Contract payments made</span><strong id="revenue-metric-made"><?= (int)$summary['made'] ?></strong></button>
<button class="metric" data-metric="payment-confirmed" onclick="setPaymentTab('payment-confirmed')"><span>Confirmed · Good terms</span><strong id="revenue-metric-confirmed"><?= (int)$summary['confirmed'] ?></strong></button>
<button class="metric" data-metric="payment-failed" onclick="setPaymentTab('payment-failed')"><span>Failed payments</span><strong id="revenue-metric-failed"><?= (int)$summary['failed'] ?></strong></button>
</section>
<nav class="tabs" aria-label="Contract payment status filters">
<button class="tab active" data-tab="all" onclick="setPaymentTab('all')">All contracts <b id="revenue-tab-contracts"><?= (int)$summary['contracts'] ?></b></button>
<button class="tab" data-tab="unpaid-payment" onclick="setPaymentTab('unpaid-payment')">Unpaid contract payments <b id="revenue-tab-unpaid"><?= (int)$summary['unpaid'] ?></b></button>
<button class="tab" data-tab="payment-made" onclick="setPaymentTab('payment-made')">Contract payments made <b id="revenue-tab-made"><?= (int)$summary['made'] ?></b></button>
<button class="tab" data-tab="payment-confirmed" onclick="setPaymentTab('payment-confirmed')">Contract payments confirmed <b id="revenue-tab-confirmed"><?= (int)$summary['confirmed'] ?></b></button>
<button class="tab" data-tab="payment-failed" onclick="setPaymentTab('payment-failed')">Failed payments <b id="revenue-tab-failed"><?= (int)$summary['failed'] ?></b></button>
</nav>
<section class="panel"><div class="panel-head"><h2>Investor accounts and subaccounts</h2><input id="paymentSearch" type="search" placeholder="Search by user ID, username or email"></div>
<?php if(!$visibleGroups): ?><div class="empty"><strong>No investor contract payments to display.</strong>Accounts appear here when a subaccount has an unpaid, submitted, confirmed or failed contract payment.</div>
<?php else: ?><div class="table-scroll"><table><thead><tr><th>User ID</th><th>Username / Name</th><th>Email</th><th>Unpaid</th><th>Payment made</th><th>Confirmed</th><th>Failed</th><th>Accounts</th><th>Expand</th></tr></thead><tbody>
<?php foreach($visibleGroups as $index=>$g): ?>
<tr class="parent-row" tabindex="0" aria-expanded="false" data-child="contract-group-<?= $index ?>" data-search="<?= serverAppH($g['search']) ?>" onclick="toggleAccountGroup(this)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleAccountGroup(this)}">
<td>#<?= (int)$g['parent_id'] ?></td><td><span class="chevron">›</span><?= serverAppH($g['parent_username']!==''?$g['parent_username']:$g['parent_name']) ?><span class="parent-meta"><?= serverAppH($g['parent_name']) ?></span></td><td><?= serverAppH($g['parent_email']) ?></td><td><b><?= (int)$g['counts']['unpaid'] ?></b></td><td><b><?= (int)$g['counts']['made'] ?></b></td><td><b><?= (int)$g['counts']['confirmed'] ?></b></td><td><b><?= (int)$g['counts']['failed'] ?></b></td><td><?= count($g['children']) ?></td><td><span class="muted">View subaccounts</span></td></tr>
<tr class="child-row" id="contract-group-<?= $index ?>" data-group="1"><td colspan="9"><div class="child-wrap"><div class="table-scroll"><table class="child-table"><thead><tr><th>Subaccount name</th><th>Username / Email</th><th>Subaccount ID</th><th>Contract ID</th><th>Programme</th><th>Contract term</th><th>Profit</th><th>Investor share</th><th>Programme share</th><th>Payment amount</th><th>Payment status</th><th>Payment date</th><th>Reference / Details</th></tr></thead><tbody>
<?php foreach($g['children'] as $child): $u=$child['user'];$state=$child['state'];$subRecords=$child['items'];if(!$subRecords)$subRecords=[['status'=>'no-payment-status','contract_id'=>'','programme_name'=>'','start'=>'','end'=>'','profit'=>0,'investor_share'=>0,'programme_share'=>0,'amount'=>0,'date'=>'','reference'=>'','method'=>'','contract_state'=>$state['contract']??'No contract status','currency'=>'USD']];foreach($subRecords as $rec):$status=$rec['status'];$pill=match($status){'unpaid-payment'=>'unpaid','payment-made'=>'made','payment-confirmed'=>'confirmed','payment-failed'=>'failed',default=>'none'};$statusText=match($status){'unpaid-payment'=>'Payment owing','payment-made'=>'Payment submitted','payment-confirmed'=>'Good terms · No attention needed','payment-failed'=>'Payment failed',default=>'No payment due'};$searchable=strtolower(icpName($u).' '.($u['username']??'').' '.($u['email']??'').' '.($state['sub_id']??'').' '.($rec['contract_id']??'')); ?>
<tr class="contract-detail" data-status="<?= serverAppH($status) ?>" data-search="<?= serverAppH($searchable) ?>"><td><b><?= serverAppH(icpName($u)) ?></b></td><td><?= serverAppH($u['username']??'') ?><span class="parent-meta"><?= serverAppH($u['email']??'') ?></span></td><td>#<?= (int)($state['sub_id']??0) ?></td><td><?= serverAppH($rec['contract_id']?:'—') ?><span class="parent-meta"><?= serverAppH(icpDate($rec['start']??'')) ?> → <?= serverAppH(icpDate($rec['end']??'')) ?></span></td><td><?= serverAppH($rec['programme_name']?:'—') ?><?= !empty($rec['programme_id'])?'<span class="parent-meta">Programme #'.(int)$rec['programme_id'].'</span>':'' ?></td><td><?= serverAppH($rec['contract_state']??$state['contract']??'—') ?></td><td><?= icpMoney($rec['profit']??0) ?></td><td><?= icpMoney($rec['investor_share']??0) ?></td><td><?= icpMoney($rec['programme_share']??0) ?></td><td><b><?= icpMoney($rec['amount']??0) ?></b></td><td><span class="pill <?= $pill ?>"><?= serverAppH($statusText) ?></span></td><td><?= serverAppH(icpDate($rec['date']??'')) ?></td><td><?= serverAppH($rec['reference']?:'—') ?><?= !empty($rec['method'])?'<span class="parent-meta">'.serverAppH($rec['method']).'</span>':'' ?></td></tr>
<?php endforeach;endforeach; ?></tbody></table></div></div></td></tr>
<?php endforeach; ?></tbody></table></div><div class="note">Summary counts are contract-history records, not unique subaccounts. Confirmed payments are classified as Good terms / No attention needed. Unlinked legacy history records: <?= (int)$unassignedHistory ?>. Legacy records with a shared email cannot be assigned to a specific subaccount reliably; the migration only backfills emails with exactly one HarvHub row.</div><?php endif; ?>
</section></main>
<script>
let paymentTab='all';
function toggleAccountGroup(row){const child=document.getElementById(row.dataset.child);const open=row.getAttribute('aria-expanded')==='true';row.setAttribute('aria-expanded',String(!open));if(child)child.classList.toggle('open',!open)}
function setPaymentTab(tab){paymentTab=tab;document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active',x.dataset.tab===tab));document.querySelectorAll('.metric').forEach(x=>x.classList.toggle('active',x.dataset.metric===tab));applyPaymentFilters()}
function applyPaymentFilters(){const q=(document.getElementById('paymentSearch')?.value||'').trim().toLowerCase();document.querySelectorAll('.parent-row').forEach(row=>{const group=document.getElementById(row.dataset.child);let matched=0;if(group){group.querySelectorAll('.contract-detail').forEach(detail=>{const status=detail.dataset.status;const searchMatch=!q||(row.dataset.search||'').includes(q)||(detail.dataset.search||'').includes(q);const statusMatch=paymentTab==='all'?true:status===paymentTab;const show=searchMatch&&statusMatch;detail.style.display=show?'':'none';if(show)matched++});const visible=(matched>0)&&(!q||(row.dataset.search||'').includes(q)||matched>0);row.style.display=visible?'':'none';group.classList.toggle('open',!!q&&visible);row.setAttribute('aria-expanded',String(!!q&&visible));group.style.display=visible?'':'none';}else row.style.display='none';});}
document.addEventListener('input',e=>{if(e.target?.id==='paymentSearch')applyPaymentFilters()});
applyPaymentFilters();
async function refreshRevenueLive(){
 if(document.hidden||window.__serverAppActive===false)return;
 const status=document.getElementById('revenueLiveStatus');
 try{
  const search=document.getElementById('paymentSearch')?.value||'';
  const expanded=[...document.querySelectorAll('.parent-row[aria-expanded="true"]')].map(row=>row.dataset.child);
  const response=await fetch('serverapp_revenue.php?embedded=1&live=1&ts='+Date.now(),{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
  if(!response.ok)throw new Error('Refresh failed');
  const html=await response.text();const doc=new DOMParser().parseFromString(html,'text/html');
  for(const selector of ['.metrics','.tabs','.panel']){
   const fresh=doc.querySelector(selector),current=document.querySelector(selector);
   if(fresh&&current)current.replaceWith(document.importNode(fresh,true));
  }
  const freshHead=doc.querySelector('.head');
  if(status&&freshHead)status.textContent='Updated '+new Date().toLocaleTimeString();
  const input=document.getElementById('paymentSearch');if(input)input.value=search;
  setPaymentTab(paymentTab);
  if(!search){expanded.forEach(id=>{const row=document.querySelector('.parent-row[data-child="'+CSS.escape(id)+'"]');const group=document.getElementById(id);if(row&&group){row.setAttribute('aria-expanded','true');group.classList.add('open');}});}
 }catch(e){if(status)status.textContent='Live update reconnecting';}
}
let revenueLiveTimer=setInterval(refreshRevenueLive,30000);
window.addEventListener('serverapp:activechange',e=>{if(e.detail?.active)refreshRevenueLive();});
window.addEventListener('message',e=>{if(e.origin===location.origin&&e.data?.type==='serverapp:module-state'&&e.data.active)refreshRevenueLive();});
</script></body></html>
