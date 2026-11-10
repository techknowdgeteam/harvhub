<?php
/**
 * HarvHub Volume — reconciles split volume from investor contract history.
 * This is a volume report, not revenue earned or cash received by HarvHub.
 */
require_once __DIR__ . '/serverapp_guard.php';

function hvTableExists(PDO $pdo, string $name): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$name]);
    return (int)$q->fetchColumn() > 0;
}
function hvColumns(PDO $pdo, string $table): array {
    $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]);
    return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
}
function hvName(array $row): string {
    foreach (['sub_account_name','username','fullname','first_name'] as $key) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') return $value;
    }
    $email = trim((string)($row['email'] ?? ''));
    return $email !== '' ? (strpos($email, '@') !== false ? strstr($email, '@', true) : $email) : 'Account';
}
function hvMoney($value, string $currency='USD'): string {
    return strtoupper($currency) . ' ' . number_format((float)$value, 2);
}

$historyExists = hvTableExists($pdo, 'revenue_history');
$harvhubColumns = hvColumns($pdo, 'harvhub');
$historyColumns = $historyExists ? hvColumns($pdo, 'revenue_history') : [];
$historyRows = [];
$unassigned = 0;
$investorTotal = 0.0;
$programmeTotal = 0.0;
$contractCount = 0;
$groups = [];
$accountsById = [];

if ($historyExists) {
    $accounts = $pdo->query('SELECT * FROM harvhub ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $emailIndex = [];
    $contractIndex = [];
    foreach ($accounts as $account) {
        $id = (int)($account['id'] ?? 0);
        if ($id <= 0) continue;
        $subId = (int)($account['sub_account_id'] ?? $id);
        if ($subId <= 0) $subId = $id;
        $account['_resolved_sub_id'] = $subId;
        $accountsById[$id] = $account;
        $contractId = trim((string)($account['contract_id'] ?? ''));
        if ($contractId !== '') $contractIndex[$contractId][] = $account;
        $emailKey = strtolower(trim((string)($account['email'] ?? '')));
        if ($emailKey !== '') $emailIndex[$emailKey][] = $account;
    }

    $historyRows = $pdo->query('SELECT * FROM revenue_history ORDER BY created_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($historyRows as $record) {
        $owner = null;
        $historyUserId = (int)($record['investor_user_id'] ?? 0);
        $historySubId = (int)($record['sub_account_id'] ?? 0);

        if ($historyUserId > 0 && isset($accountsById[$historyUserId])) {
            $candidate = $accountsById[$historyUserId];
            if ($historySubId <= 0 || (int)$candidate['_resolved_sub_id'] === $historySubId) $owner = $candidate;
        }
        if (!$owner && $historySubId > 0) {
            $userEmail = strtolower(trim((string)($record['user_email'] ?? '')));
            foreach (($emailIndex[$userEmail] ?? []) as $candidate) {
                if ((int)$candidate['_resolved_sub_id'] === $historySubId) {
                    $owner = $candidate;
                    break;
                }
            }
        }
        if (!$owner) {
            // Some legacy rows have no subaccount columns but still retain a row-specific contract_id.
            $contractId = trim((string)($record['contract_id'] ?? ''));
            $candidates = $contractId !== '' ? ($contractIndex[$contractId] ?? []) : [];
            $userEmail = strtolower(trim((string)($record['user_email'] ?? '')));
            $matchesByContract = array_values(array_filter($candidates, static function($candidate) use ($userEmail) {
                return $userEmail === '' || strtolower(trim((string)($candidate['email'] ?? ''))) === $userEmail;
            }));
            if (count($matchesByContract) === 1) $owner = $matchesByContract[0];
        }
        if (!$owner) {
            $userEmail = strtolower(trim((string)($record['user_email'] ?? '')));
            $matches = $emailIndex[$userEmail] ?? [];
            if (count($matches) === 1) $owner = $matches[0];
        }
        if (!$owner) { $unassigned++; continue; }

        $ownerId = (int)$owner['id'];
        $subId = (int)$owner['_resolved_sub_id'];
        $mainId = (int)($owner['main_account_id'] ?? 0);
        $groupId = $mainId > 0 ? $mainId : $ownerId;
        $investorShare = (float)($record['user_share'] ?? 0);
        $programmeShare = (float)($record['server_share'] ?? 0);
        $status = strtolower(str_replace(['_', ' '], '-', trim((string)($record['loyalties'] ?? ''))));
        if (strpos($status, 'active') !== false) continue; // open contracts have not yet crystallised into history volume
        if (abs($investorShare) < 0.00001 && abs($programmeShare) < 0.00001) continue;

        $investorTotal += $investorShare;
        $programmeTotal += $programmeShare;
        $contractCount++;

        if (!isset($groups[$groupId])) {
            $parent = $accountsById[$groupId] ?? $owner;
            $groups[$groupId] = [
                'id'=>$groupId,
                'name'=>hvName($parent),
                'username'=>(string)($parent['username'] ?? ''),
                'email'=>(string)($parent['email'] ?? ''),
                'children'=>[], 'investor'=>0.0, 'programme'=>0.0, 'contracts'=>0
            ];
        }
        if (!isset($groups[$groupId]['children'][$subId])) {
            $groups[$groupId]['children'][$subId] = [
                'id'=>$subId,
                'user_id'=>$ownerId,
                'name'=>hvName($owner),
                'username'=>(string)($owner['username'] ?? ''),
                'email'=>(string)($owner['email'] ?? ''),
                'investor'=>0.0, 'programme'=>0.0, 'contracts'=>0, 'records'=>[]
            ];
        }
        $groups[$groupId]['children'][$subId]['investor'] += $investorShare;
        $groups[$groupId]['children'][$subId]['programme'] += $programmeShare;
        $groups[$groupId]['children'][$subId]['contracts']++;
        $groups[$groupId]['children'][$subId]['records'][] = [
            'contract_id'=>(string)($record['contract_id'] ?? ''),
            'programme'=>(string)($record['invested_with'] ?? ''),
            'investor'=>$investorShare,
            'programme_share'=>$programmeShare,
            'status'=>(string)($record['loyalties'] ?? ''),
            'created'=>(string)($record['created_at'] ?? ''),
            'start'=>(string)($record['execution_start_date'] ?? ''),
            'end'=>(string)($record['execution_end_date'] ?? '')
        ];
        $groups[$groupId]['investor'] += $investorShare;
        $groups[$groupId]['programme'] += $programmeShare;
        $groups[$groupId]['contracts']++;
    }
}

$harvhubTotal = $investorTotal + $programmeTotal;
$totalSubaccounts = 0;
foreach ($groups as &$group) {
    $group['children'] = array_values($group['children']);
    usort($group['children'], static fn($a,$b) => strcasecmp($a['name'], $b['name']));
    $totalSubaccounts += count($group['children']);
}
unset($group);
$groups = array_values($groups);
usort($groups, static fn($a,$b) => strcasecmp($a['name'], $b['name']));
if ($isServerAppAjax && (string)($_POST['action'] ?? '') === 'volume_live_snapshot') {
    serverAppJson(['success'=>true,'harvhub_total'=>$harvhubTotal,'investor_total'=>$investorTotal,'programme_total'=>$programmeTotal,'contract_count'=>$contractCount,'subaccount_count'=>$totalSubaccounts,'unassigned_count'=>$unassigned,'updated_at'=>date(DATE_ATOM)]);
}
?>
<!doctype html>
<html lang="en" data-theme="<?= serverAppH($serverAppTheme) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>HarvHub Volume</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<?php require_once __DIR__ . '/server_feature_style.php'; ?>
<style>
.volume-page{max-width:1500px;margin:0 auto;padding:2px 0 28px;color:var(--text-color)}
.volume-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.volume-head h1{margin:0;font-size:24px;color:var(--header-color)}.volume-head p{margin:5px 0 0;color:var(--text-muted);max-width:850px}
.volume-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px}.volume-card{background:var(--container-bg);border:1px solid var(--border-color);border-radius:15px;padding:18px;box-shadow:0 5px 18px var(--shadow-color)}.volume-card small{display:block;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.06em}.volume-card strong{display:block;font-size:28px;margin-top:8px;letter-spacing:-.03em}.volume-card p{font-size:12px;color:var(--text-muted);margin:5px 0 0}.volume-panel{background:var(--container-bg);border:1px solid var(--border-color);border-radius:15px;overflow:hidden}.volume-panel-head{padding:15px 18px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;gap:12px}.volume-panel-head h2{font-size:16px;margin:0;color:var(--header-color)}.volume-search{width:min(360px,100%);padding:10px 12px;border:1px solid var(--border-color);border-radius:9px;background:var(--input-bg);color:var(--text-color)}.volume-table-wrap{overflow:auto}.volume-table{width:100%;border-collapse:collapse;min-width:800px}.volume-table th,.volume-table td{padding:12px 14px;border-bottom:1px solid var(--border-color);text-align:left;vertical-align:top}.volume-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);background:var(--table-header-bg)}.volume-parent{cursor:pointer}.volume-parent:hover{background:var(--bg-tertiary)}.volume-child{display:none;background:var(--bg-secondary)}.volume-child.open{display:table-row}.volume-subwrap{padding:12px 14px 16px 35px}.volume-subtable{min-width:990px;background:var(--container-bg)}.volume-meta{display:block;font-size:12px;color:var(--text-muted)}.volume-money{font-weight:750;white-space:nowrap}.volume-info{padding:13px 17px;color:var(--text-muted);font-size:12px;line-height:1.6}.volume-empty{padding:50px 18px;text-align:center;color:var(--text-muted)}.volume-toggle{border:0;background:transparent;color:var(--accent-color,#1769aa);font-weight:800;cursor:pointer;font-size:17px;margin-right:7px}.volume-badge{display:inline-block;padding:3px 8px;border-radius:20px;background:var(--bg-tertiary);font-size:11px;color:var(--text-muted)}
@media(max-width:850px){.volume-cards{grid-template-columns:1fr}.volume-head{flex-direction:column}.volume-panel-head{align-items:stretch;flex-direction:column}.volume-search{width:100%}}
@media(max-width:560px){body{padding:9px}.volume-head h1{font-size:20px}.volume-head p{font-size:12px}.volume-cards{grid-template-columns:1fr!important;gap:9px}.volume-panel-head{padding:12px}.volume-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}}
</style>
</head>
<body><main class="server-feature-wrap"><div class="volume-page">
<header class="volume-head"><div><h1>HarvHub Volume</h1><p>Combined contract-split volume recorded across investor subaccounts and their associated programmes. This is contract volume facilitated through HarvHub, not company revenue, cash received, or platform earnings.</p></div><span class="volume-badge" id="volume-record-count"><?= number_format($contractCount) ?> recorded split records</span></header>
<section class="volume-cards">
 <article class="volume-card"><small>HarvHub Volume</small><strong id="volume-harvhub-total"><?= serverAppH(hvMoney($harvhubTotal)) ?></strong><p>Investor Volume + Programme Volume</p></article>
 <article class="volume-card"><small>Investors Volume</small><strong id="volume-investor-total"><?= serverAppH(hvMoney($investorTotal)) ?></strong><p>Combined investor profit shares recorded as <code>revenue_history.user_share</code>.</p></article>
 <article class="volume-card"><small>Programme Volume</small><strong id="volume-programme-total"><?= serverAppH(hvMoney($programmeTotal)) ?></strong><p>Combined programme/developer shares recorded as <code>revenue_history.server_share</code>.</p></article>
</section>
<section class="volume-panel"><div class="volume-panel-head"><h2>Volume by user and subaccount</h2><input class="volume-search" id="volumeSearch" type="search" placeholder="Search user ID, username, email or subaccount"></div>
<?php if(!$historyExists): ?><div class="volume-empty"><strong>Revenue history table not found.</strong><br>Restore the existing revenue_history table before volume can be calculated.</div>
<?php elseif(!$groups): ?><div class="volume-empty"><strong>No completed contract split volume is recorded yet.</strong><br>Open contracts and records without a stored investor/programme share are excluded.</div>
<?php else: ?>
<div class="volume-table-wrap"><table class="volume-table"><thead><tr><th>User</th><th>User ID</th><th>Subaccounts</th><th>Investors Volume</th><th>Programme Volume</th><th>HarvHub Volume</th><th>Contracts</th></tr></thead><tbody>
<?php foreach($groups as $i=>$group): $childId='volume-group-'.$i; $search=strtolower($group['id'].' '.$group['name'].' '.$group['username'].' '.$group['email'].' '.implode(' ',array_map(static fn($c)=>$c['id'].' '.$c['name'].' '.$c['username'].' '.$c['email'],$group['children']))); ?>
<tr class="volume-parent" data-child="<?= serverAppH($childId) ?>" data-search="<?= serverAppH($search) ?>" aria-expanded="false"><td><button type="button" class="volume-toggle" aria-label="Expand">＋</button><strong><?= serverAppH($group['name']) ?></strong><span class="volume-meta"><?= serverAppH($group['email']) ?></span></td><td>#<?= (int)$group['id'] ?></td><td><?= count($group['children']) ?></td><td class="volume-money"><?= serverAppH(hvMoney($group['investor'])) ?></td><td class="volume-money"><?= serverAppH(hvMoney($group['programme'])) ?></td><td class="volume-money"><?= serverAppH(hvMoney($group['investor']+$group['programme'])) ?></td><td><?= (int)$group['contracts'] ?></td></tr>
<tr class="volume-child" id="<?= serverAppH($childId) ?>"><td colspan="7"><div class="volume-subwrap"><table class="volume-table volume-subtable"><thead><tr><th>Subaccount</th><th>Subaccount ID</th><th>HarvHub row ID</th><th>Investors Volume</th><th>Programme Volume</th><th>Combined Volume</th><th>Contracts</th></tr></thead><tbody>
<?php foreach($group['children'] as $child): ?><tr><td><strong><?= serverAppH($child['name']) ?></strong><span class="volume-meta"><?= serverAppH($child['email']) ?></span></td><td>#<?= (int)$child['id'] ?></td><td>#<?= (int)$child['user_id'] ?></td><td class="volume-money"><?= serverAppH(hvMoney($child['investor'])) ?></td><td class="volume-money"><?= serverAppH(hvMoney($child['programme'])) ?></td><td class="volume-money"><?= serverAppH(hvMoney($child['investor']+$child['programme'])) ?></td><td><?= (int)$child['contracts'] ?></td></tr>
<tr><td colspan="7"><details><summary>View contract split records (<?= (int)$child['contracts'] ?>)</summary><div class="volume-table-wrap"><table class="volume-table"><thead><tr><th>Contract</th><th>Programme / Invested with</th><th>Investor share</th><th>Programme share</th><th>Status</th><th>Recorded</th><th>Contract start / end</th></tr></thead><tbody><?php foreach($child['records'] as $record): ?><tr><td><?= serverAppH($record['contract_id'] ?: '—') ?></td><td><?= serverAppH($record['programme'] ?: '—') ?></td><td><?= serverAppH(hvMoney($record['investor'])) ?></td><td><?= serverAppH(hvMoney($record['programme_share'])) ?></td><td><?= serverAppH($record['status'] ?: '—') ?></td><td><?= serverAppH($record['created'] ?: '—') ?></td><td><?= serverAppH(trim($record['start'].' / '.$record['end'],' /') ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div></details></td></tr>
<?php endforeach; ?></tbody></table></div></td></tr>
<?php endforeach; ?></tbody></table></div>
<div class="volume-info">Volumes include non-active history records with a non-zero stored split, regardless of whether the corresponding contract payment is unpaid, submitted, confirmed or failed. Open active contracts are excluded until their split is written to history. The current data source is investor contract history: Investor Volume is <code>user_share</code>; Programme Volume is <code>server_share</code>. <?= (int)$unassigned ?> history records could not be safely linked to a unique investor subaccount and are excluded from the breakdown. Programme Volume here is the programme share of investor contracts, not a separate programme-originated revenue ledger.</div>
<?php endif; ?></section></div></main>
<script>
function toggleVolumeGroup(row){const target=document.getElementById(row.dataset.child);if(!target)return;const open=!target.classList.contains('open');target.classList.toggle('open',open);row.setAttribute('aria-expanded',String(open));const toggle=row.querySelector('.volume-toggle');if(toggle)toggle.textContent=open?'−':'+';}
function applyVolumeSearch(){const q=(document.getElementById('volumeSearch')?.value||'').trim().toLowerCase();document.querySelectorAll('.volume-parent').forEach(row=>{const show=!q||(row.dataset.search||'').includes(q);row.style.display=show?'':'none';const child=document.getElementById(row.dataset.child);if(child){child.style.display=show?'':'none';if(q&&show){child.classList.add('open');row.setAttribute('aria-expanded','true');const toggle=row.querySelector('.volume-toggle');if(toggle)toggle.textContent='−';}else if(!show||!q){child.classList.remove('open');row.setAttribute('aria-expanded','false');const toggle=row.querySelector('.volume-toggle');if(toggle)toggle.textContent='+';}}});}
document.addEventListener('click',e=>{const row=e.target.closest('.volume-parent');if(row){if(e.target.closest('a,button')&&!e.target.closest('.volume-toggle'))return;toggleVolumeGroup(row);}});
document.addEventListener('input',e=>{if(e.target?.id==='volumeSearch')applyVolumeSearch()});
applyVolumeSearch();
async function refreshVolumeLive(){
 if(document.hidden||window.__serverAppActive===false)return;
 try{
  const search=document.getElementById('volumeSearch')?.value||'';
  const expanded=[...document.querySelectorAll('.volume-parent[aria-expanded="true"]')].map(row=>row.dataset.child);
  const response=await fetch('serverapp_volume.php?embedded=1&live=1&ts='+Date.now(),{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
  if(!response.ok)throw new Error('Refresh failed');
  const html=await response.text();const doc=new DOMParser().parseFromString(html,'text/html');
  for(const selector of ['.volume-cards','.volume-panel']){
   const fresh=doc.querySelector(selector),current=document.querySelector(selector);
   if(fresh&&current)current.replaceWith(document.importNode(fresh,true));
  }
  const searchInput=document.getElementById('volumeSearch');if(searchInput)searchInput.value=search;
  applyVolumeSearch();
  if(!search){expanded.forEach(id=>{const row=document.querySelector('.volume-parent[data-child="'+CSS.escape(id)+'"]');const child=document.getElementById(id);if(row&&child){row.setAttribute('aria-expanded','true');child.classList.add('open');const toggle=row.querySelector('.volume-toggle');if(toggle)toggle.textContent='−';}});}
 }catch(e){}
}
let volumeLiveTimer=setInterval(refreshVolumeLive,45000);
window.addEventListener('serverapp:activechange',e=>{if(e.detail?.active)refreshVolumeLive();});
window.addEventListener('message',e=>{if(e.origin===location.origin&&e.data?.type==='serverapp:module-state'&&e.data.active)refreshVolumeLive();});
</script></body></html>
