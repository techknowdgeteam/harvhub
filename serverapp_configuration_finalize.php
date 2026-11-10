<?php
/**
 * One-time table-only finalizer.
 * Creates/repairs relational configuration tables, imports any available legacy
 * scalar/CSV/tier data once, then removes duplicate configuration columns.
 * Runtime Server Configuration never reads a legacy configuration field.
 */
require_once __DIR__ . '/serverapp_guard.php';

function scfH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function scfTableExists(PDO $pdo, string $table): bool { return serverAppSchemaTableExists($pdo, $table); }
function scfColumns(PDO $pdo, string $table): array {
    if (!scfTableExists($pdo, $table)) return [];
    $q=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]);
    return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
}
function scfRead(array $row, array $columns, string $name, $default = null) {
    return isset($columns[$name]) && array_key_exists($name, $row) ? $row[$name] : $default;
}
function scfInsertSingleton(PDO $pdo, string $table, array $columns, array $values): bool {
    $check=$pdo->query('SELECT id FROM `' . $table . '` WHERE id=1 LIMIT 1');
    if ($check->fetchColumn()) return false;
    $names=array_merge(['id'], $columns);
    $sql='INSERT INTO `' . $table . '` (`' . implode('`,`',$names) . '`) VALUES (' . implode(',',array_fill(0,count($names),'?')) . ')';
    $q=$pdo->prepare($sql);
    $q->execute(array_merge([1],$values));
    return true;
}
function scfImportTierRows(PDO $pdo, string $raw): array {
    $counts=['tiers'=>0,'fields'=>0,'values'=>0,'skipped'=>0];
    if (trim($raw)==='') return $counts;
    // This decoder exists only in the one-time finalizer to preserve old values.
    // No active page reads or writes tier configuration in this legacy format.
    $legacy=json_decode($raw, false, 512);
    if (!is_object($legacy)) {
        $counts['skipped']=1;
        return $counts;
    }

    $existing=(int)$pdo->query('SELECT COUNT(*) FROM server_tiers')->fetchColumn();
    if ($existing>0) return $counts; // Never overwrite already-normalized relational tier records.

    $insertTier=$pdo->prepare('INSERT INTO server_tiers(tier_key,sort_order,is_enabled) VALUES(?,?,1)');
    $insertField=$pdo->prepare('INSERT INTO server_tier_fields(field_name,sort_order) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $findField=$pdo->prepare('SELECT id FROM server_tier_fields WHERE field_name=? LIMIT 1');
    $insertValue=$pdo->prepare('INSERT INTO server_tier_values(tier_id,field_id,field_value) VALUES(?,?,?)');
    $tierOrder=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),-1)+1 FROM server_tiers')->fetchColumn();
    $fieldOrder=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),-1)+1 FROM server_tier_fields')->fetchColumn();

    foreach (get_object_vars($legacy) as $tierName=>$fieldObject) {
        $tierName=trim((string)$tierName);
        if ($tierName==='' || strlen($tierName)>190 || !is_object($fieldObject)) { $counts['skipped']++; continue; }
        $insertTier->execute([$tierName,$tierOrder++]);
        $tierId=(int)$pdo->lastInsertId();
        $counts['tiers']++;
        foreach (get_object_vars($fieldObject) as $fieldName=>$fieldValue) {
            $fieldName=trim((string)$fieldName);
            if ($fieldName==='' || strlen($fieldName)>190 || (!is_scalar($fieldValue) && $fieldValue!==null)) { $counts['skipped']++; continue; }
            $insertField->execute([$fieldName,$fieldOrder++]);
            $findField->execute([$fieldName]);
            $fieldId=(int)$findField->fetchColumn();
            if ($fieldId<=0) { $counts['skipped']++; continue; }
            $insertValue->execute([$tierId,$fieldId,$fieldValue===null?'':(string)$fieldValue]);
            $counts['values']++;
        }
    }
    $counts['fields']=(int)$pdo->query('SELECT COUNT(*) FROM server_tier_fields')->fetchColumn();
    return $counts;
}
function scfImportBrokerRows(PDO $pdo, array $legacy, array $columns): int {
    if ((int)$pdo->query('SELECT COUNT(*) FROM server_brokers')->fetchColumn()>0) return 0;
    $nameRaw=trim((string)scfRead($legacy,$columns,'brokers',''));
    $linkRaw=trim((string)scfRead($legacy,$columns,'brokers_link',''));
    if ($nameRaw==='') return 0;
    $names=str_getcsv($nameRaw);
    $links=$linkRaw!==''?str_getcsv($linkRaw):[];
    $insert=$pdo->prepare('INSERT IGNORE INTO server_brokers(broker_name,broker_url,sort_order,is_enabled) VALUES(?,?,?,1)');
    $count=0;
    foreach ($names as $i=>$name) {
        $name=trim((string)$name);
        if ($name==='') continue;
        $url=trim((string)($links[$i]??''));
        if (strpos($name,':')!==false && !preg_match('#^https?://#i',$url)) {
            $parts=explode(':',$name,2);
            if (count($parts)===2) $name=trim($parts[1]);
        }
        if ($name==='') continue;
        $insert->execute([$name,$url,$count]);
        $count++;
    }
    return $count;
}
function scfMigrateAndRemoveLegacyColumns(PDO $pdo): array {
    // server_account and its id=1 row are optional; no configuration operation depends on them.
    $legacyTableExists=scfTableExists($pdo,'server_account');
    $legacyColumns=$legacyTableExists?scfColumns($pdo,'server_account'):[];
    $legacy=[];
    if ($legacyTableExists && isset($legacyColumns['id'])) {
        $legacy=$pdo->query('SELECT * FROM server_account WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $counts=['tiers'=>['tiers'=>0,'fields'=>0,'values'=>0,'skipped'=>0],'brokers_added'=>0];
    $pdo->beginTransaction();
    try {
        // Every singleton setting has one normalized row. Existing relational data wins.
        scfInsertSingleton($pdo,'server_admin_credentials',['admin_login_id','admin_password_hash'],[
            trim((string)scfRead($legacy,$legacyColumns,'admin_login_id','')) ?: 'admin',
            (string)scfRead($legacy,$legacyColumns,'admin_password_hash','')
        ]);
        scfInsertSingleton($pdo,'server_mailer_settings',['mailer_email','mailer_password'],[
            (string)scfRead($legacy,$legacyColumns,'mailer_email',''),
            (string)scfRead($legacy,$legacyColumns,'mailer_password','')
        ]);
        scfInsertSingleton($pdo,'server_requirements',['minimum_deposit','min_broker_balance','contract_duration','server_share_percent','user_share_percent','min_profit_for_split','expiry_threshold_days'],[
            (float)scfRead($legacy,$legacyColumns,'minimum_deposit',0),
            (float)scfRead($legacy,$legacyColumns,'min_broker_balance',30),
            max(1,(int)scfRead($legacy,$legacyColumns,'contract_duration',30)),
            (float)scfRead($legacy,$legacyColumns,'server_share_percent',30),
            (float)scfRead($legacy,$legacyColumns,'user_share_percent',70),
            (float)scfRead($legacy,$legacyColumns,'min_profit_for_split',30),
            max(0,(int)scfRead($legacy,$legacyColumns,'expiry_threshold_days',5))
        ]);
        scfInsertSingleton($pdo,'server_operational_settings',['day_challenge_duration','weekly_challenge_duration','monthly_challenge_duration','trades_setup_per_group'],[
            max(0,(int)scfRead($legacy,$legacyColumns,'day_challenge_duration',0)),
            max(0,(int)scfRead($legacy,$legacyColumns,'weekly_challenge_duration',0)),
            max(0,(int)scfRead($legacy,$legacyColumns,'monthly_challenge_duration',0)),
            max(1,(int)scfRead($legacy,$legacyColumns,'trades_setup_per_group',100))
        ]);
        scfInsertSingleton($pdo,'server_theme_settings',['dark_mode'],[(int)scfRead($legacy,$legacyColumns,'dark_mode',0)===1?1:0]);

        // Payment methods are three independent rows, never address columns on server_account.
        $payments=[
            ['BTC','Bitcoin',(string)scfRead($legacy,$legacyColumns,'btc_address',''),'Bitcoin',10],
            ['ETH','Ethereum',(string)scfRead($legacy,$legacyColumns,'eth_address',''),(string)scfRead($legacy,$legacyColumns,'eth_network','ERC20'),20],
            ['USDT','Tether',(string)scfRead($legacy,$legacyColumns,'usdt_address',''),(string)scfRead($legacy,$legacyColumns,'usdt_network','TRC20'),30]
        ];
        $paymentInsert=$pdo->prepare('INSERT IGNORE INTO server_payment_methods(method_code,method_name,wallet_address,network,sort_order,is_enabled) VALUES(?,?,?,?,?,1)');
        foreach($payments as $payment)$paymentInsert->execute($payment);

        $counts['brokers_added']=scfImportBrokerRows($pdo,$legacy,$legacyColumns);
        if (isset($legacyColumns['tier_limit']) && trim((string)($legacy['tier_limit']??''))!=='') {
            $counts['tiers']=scfImportTierRows($pdo,(string)$legacy['tier_limit']);
        }
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }

    // Verify all singleton rows exist, including installations that had no legacy data row.
    foreach(['server_admin_credentials','server_mailer_settings','server_requirements','server_operational_settings','server_theme_settings'] as $table) {
        $q=$pdo->query('SELECT COUNT(*) FROM `' . $table . '` WHERE id=1');
        if((int)$q->fetchColumn()!==1) throw new RuntimeException('The relational settings row is missing from ' . $table . '.');
    }

    $legacyConfigColumns=[
        'tier_limit','brokers','brokers_link','btc_address','eth_address','eth_network','usdt_address','usdt_network',
        'minimum_deposit','min_broker_balance','contract_duration','server_share_percent','user_share_percent',
        'min_profit_for_split','expiry_threshold_days','day_challenge_duration','weekly_challenge_duration',
        'monthly_challenge_duration','trades_setup_per_group','mailer_email','mailer_password','admin_login_id',
        'admin_password_hash','dark_mode'
    ];
    $dropped=[];$failures=[];
    if($legacyTableExists) {
        foreach($legacyConfigColumns as $column) {
            if(!isset($legacyColumns[$column])) continue;
            try {
                $pdo->exec('ALTER TABLE server_account DROP COLUMN `' . $column . '`');
                $dropped[]=$column;
            } catch(Throwable $e) {
                $failures[$column]=$e->getMessage();
                error_log('Could not remove legacy server_account.' . $column . ': ' . $e->getMessage());
            }
        }
    }
    return [
        'legacy_table_found'=>$legacyTableExists,
        'legacy_row_found'=>(bool)$legacy,
        'tiers'=>$counts['tiers'],
        'brokers_added'=>$counts['brokers_added'],
        'columns_removed'=>$dropped,
        'drop_failures'=>$failures,
        'remaining_legacy_columns'=>array_values(array_diff(array_filter($legacyConfigColumns,fn($c)=>isset($legacyColumns[$c])),$dropped))
    ];
}

$message='';$messageType='info';$result=null;
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if(!serverAppCheckCsrf()) {$message='The request token expired. Refresh this page and try again.';$messageType='error';http_response_code(419);}
    elseif((string)($_POST['confirm_finalize']??'')!=='yes') {$message='The finalization was not confirmed. No data was changed.';$messageType='error';http_response_code(400);}
    elseif(!serverAppVerifyAdmin($pdo,trim((string)($_POST['login_id']??'')),(string)($_POST['admin_password']??''))) {$message='Administrator verification failed. Check the login ID and password.';$messageType='error';http_response_code(403);}
    else {
        try {
            $result=scfMigrateAndRemoveLegacyColumns($pdo);
            if(!$result['remaining_legacy_columns']) {
                $message='Table-only configuration is finalized. All available settings are in relational tables; duplicate server_account configuration columns have been removed.';
                if($result['tiers']['skipped']>0) $message.=' Some malformed or nested legacy tier values could not be imported and were skipped.';
                $messageType='success';
            } else {$message='Relational values were migrated, but the hosting database did not allow removal of every old configuration column. Review the remaining columns below.';$messageType='error';}
        } catch(Throwable $e) {error_log('Server configuration finalization failed: '.$e->getMessage());$message=$e->getMessage();$messageType='error';}
    }
}
?>
<!doctype html>
<html lang="en" data-theme="<?= scfH($serverAppTheme ?? 'light') ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Finalize Table-Only Server Configuration</title>
<?php require_once __DIR__ . '/server_feature_style.php'; ?>
<style>
.scf-page{width:min(960px,100%);margin:0 auto;padding:12px 0 38px}.scf-card{background:var(--container-bg);border:1px solid var(--border-color);border-radius:18px;padding:clamp(18px,3vw,30px);box-shadow:0 8px 28px var(--shadow-color)}.scf-card h1{margin:0 0 8px;font-size:clamp(22px,3vw,30px);color:var(--header-color)}.scf-card p{color:var(--text-secondary);line-height:1.65}.scf-note{padding:14px 16px;border:1px solid var(--border-color);background:var(--bg-secondary);border-radius:12px;margin:18px 0}.scf-alert{padding:14px 16px;border-radius:12px;margin:0 0 18px;border:1px solid var(--border-color);overflow-wrap:anywhere}.scf-alert.success{background:rgba(36,122,75,.10);border-color:rgba(36,122,75,.35)}.scf-alert.error{background:rgba(185,67,53,.10);border-color:rgba(185,67,53,.35)}.scf-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:16px}.scf-stat{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:12px;padding:13px}.scf-stat strong{display:block;font-size:23px;color:var(--header-color)}.scf-stat span{font-size:12px;color:var(--text-muted)}.scf-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:22px}.scf-button{appearance:none;border:0;border-radius:11px;padding:12px 17px;font-weight:750;cursor:pointer;background:var(--primary-color);color:#21180a}.scf-link{color:var(--primary-color);font-weight:700;text-decoration:none}.scf-overlay{position:fixed;inset:0;z-index:9999;background:var(--settings_modal-overlay);display:none;align-items:center;justify-content:center;padding:16px}.scf-overlay.open{display:flex}.scf-modal{width:min(440px,100%);background:var(--container-bg);border:1px solid var(--border-color);border-radius:17px;padding:22px;box-shadow:0 20px 70px var(--shadow-light)}.scf-modal h2{margin:0 0 8px;font-size:20px;color:var(--header-color)}.scf-modal label{display:block;margin:13px 0 5px;font-size:13px;font-weight:700;color:var(--text-secondary)}.scf-modal input{width:100%;min-height:46px;padding:10px 12px;border-radius:10px;border:1px solid var(--border-color);background:var(--input-bg);color:var(--text-color)}.scf-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}.scf-cancel{border:1px solid var(--border-color);border-radius:10px;padding:10px 15px;background:var(--bg-secondary);color:var(--text-color);cursor:pointer}@media(max-width:560px){body{padding:8px}.scf-page{padding-top:3px}.scf-card{border-radius:14px;padding:16px}.scf-stats{grid-template-columns:1fr}.scf-actions>*{width:100%;text-align:center}.scf-modal{padding:17px}}
</style>
</head>
<body>
<main class="scf-page"><section class="scf-card">
<h1>Finalize Table-Only Server Configuration</h1>
<p>This operation creates any missing relational configuration tables, imports available old settings once, and removes duplicate configuration columns from <code>server_account</code>. It also works when the old table or its configuration row is absent.</p>
<?php if($message!==''): ?><div class="scf-alert <?= scfH($messageType) ?>" role="status"><?= scfH($message) ?></div><?php endif; ?>
<?php if($result!==null): ?>
<div class="scf-stats">
<div class="scf-stat"><strong><?= (int)$result['tiers']['tiers'] ?></strong><span>Legacy tier entries imported</span></div>
<div class="scf-stat"><strong><?= (int)$result['brokers_added'] ?></strong><span>Broker records imported</span></div>
<div class="scf-stat"><strong><?= count($result['columns_removed']) ?></strong><span>Legacy columns removed</span></div>
</div>
<p>Tier fields: <?= (int)$result['tiers']['fields'] ?> · Tier values: <?= (int)$result['tiers']['values'] ?> · Skipped legacy tier values: <?= (int)$result['tiers']['skipped'] ?>.</p>
<p>Legacy <code>server_account</code> table found: <?= $result['legacy_table_found']?'Yes':'No' ?> · Legacy row found: <?= $result['legacy_row_found']?'Yes':'No' ?>.</p>
<?php if($result['remaining_legacy_columns']): ?><p><strong>Columns still present:</strong> <?= scfH(implode(', ',$result['remaining_legacy_columns'])) ?>.</p><?php foreach($result['drop_failures'] as $column=>$reason): ?><p><strong><?= scfH($column) ?>:</strong> <?= scfH($reason) ?></p><?php endforeach; ?><?php endif; ?>
<?php endif; ?>
<div class="scf-note"><strong>Table-only storage</strong><p>After finalization, payment methods are separate rows in <code>server_payment_methods</code>; brokers are separate rows in <code>server_brokers</code>; tier entries, reusable fields and values live in <code>server_tiers</code>, <code>server_tier_fields</code> and <code>server_tier_values</code>. Other settings each have their own table. JSON is not used as a live configuration store.</p><p>Back up the database before proceeding. The normal Server App now creates and repairs these relational tables automatically, so old migration v3/v4 files are not prerequisites.</p></div>
<div class="scf-actions"><button class="scf-button" type="button" id="openFinalizeModal">Verify and finalize table-only configuration</button><a class="scf-link" href="server_configuration.php">Back to Server Configuration</a></div>
</section></main>
<div class="scf-overlay" id="scfModal" aria-hidden="true"><form class="scf-modal" method="post" autocomplete="off">
<h2>Administrator verification</h2><p>Enter your current administrator credentials. Both fields start blank.</p>
<input type="hidden" name="csrf_token" value="<?= scfH($serverAppCsrf ?? '') ?>"><input type="hidden" name="confirm_finalize" value="yes">
<label for="scfLogin">Administrator login ID</label><input id="scfLogin" name="login_id" type="text" autocomplete="off" required>
<label for="scfPassword">Administrator password</label><input id="scfPassword" name="admin_password" type="password" autocomplete="new-password" required>
<div class="scf-modal-actions"><button class="scf-cancel" type="button" id="closeFinalizeModal">Cancel</button><button class="scf-button" type="submit">Verify and finalize</button></div>
</form></div>
<script>
(function(){const modal=document.getElementById('scfModal'),login=document.getElementById('scfLogin'),password=document.getElementById('scfPassword');document.getElementById('openFinalizeModal').addEventListener('click',function(){modal.classList.add('open');modal.setAttribute('aria-hidden','false');login.value='';password.value='';login.focus();});function close(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');login.value='';password.value='';}document.getElementById('closeFinalizeModal').addEventListener('click',close);modal.addEventListener('click',function(e){if(e.target===modal)close();});document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});})();
</script>
</body></html>
