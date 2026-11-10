<?php
/** Server Configuration: independent relational settings only. */
require_once __DIR__ . '/serverapp_guard.php';
$serverAccount = $serverAppConfig;

function serverConfigTableExists(PDO $pdo, string $table): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$table]);
    return (int)$q->fetchColumn()>0;
}
function serverConfigLoadTiers(PDO $pdo): array {
    return $pdo->query('SELECT tier,minimum_deposit,maximum_revenue,maximum_deposit,description FROM server_tiers ORDER BY tier ASC')->fetchAll(PDO::FETCH_ASSOC);
}
function serverConfigLoadPaymentMethods(PDO $pdo): array {
    return $pdo->query('SELECT id,method_name,account_or_address,additional_info,company_name,sort_order,is_enabled FROM server_payment_methods ORDER BY sort_order ASC,id ASC')->fetchAll(PDO::FETCH_ASSOC);
}
function serverConfigLoadBrokers(PDO $pdo): array {
    return $pdo->query('SELECT id,broker_name,broker_url FROM server_brokers WHERE is_enabled=1 ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC);
}
function serverConfigRequireAdmin(PDO $pdo): void {
    if(!serverAppCheckCsrf()) serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
    if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password']??''))) serverAppJson(['success'=>false,'error'=>'Invalid administrator login ID or password.'],403);
}
function serverConfigReadSingleton(PDO $pdo,string $table):array {
    $allowed=['server_requirements','server_operational_settings','server_mailer_settings'];
    if(!in_array($table,$allowed,true)) throw new RuntimeException('Invalid settings table.');
    $q=$pdo->query('SELECT * FROM `'.$table.'` WHERE id=1 LIMIT 1');
    return $q->fetch(PDO::FETCH_ASSOC)?:[];
}

$requiredTables=['server_payment_methods','server_brokers','server_requirements','server_operational_settings','server_tiers','server_mailer_settings','server_admin_credentials','server_theme_settings'];
foreach($requiredTables as $table){
    if(!serverConfigTableExists($pdo,$table)){
        http_response_code(503);
        exit('Missing relational configuration table: '.serverAppH($table).'. Run server_configuration_general_tables_migration.sql and check database permissions.');
    }
}

// The current administrator ID is read from the dedicated relational credentials table.
$currentAdminLoginId = (string)($serverAppAdminCredentials['admin_login_id'] ?? '');
if ($currentAdminLoginId === '') {
    $currentAdminLoginId = (string)($pdo->query('SELECT admin_login_id FROM server_admin_credentials WHERE id=1 LIMIT 1')->fetchColumn() ?: '');
}

$requirements=serverConfigReadSingleton($pdo,'server_requirements');
$operationalSettings=serverConfigReadSingleton($pdo,'server_operational_settings');
$mailerSettings=serverConfigReadSingleton($pdo,'server_mailer_settings');
$serverAccount=array_merge($serverAccount,$requirements,$operationalSettings);
$serverAccount['mailer_email']=$mailerSettings['mailer_email']??'';

if($isServerAppAjax){
    $action=(string)($_POST['action']??'');
    try{
        if($action==='settings_get_payment_methods') serverAppJson(['success'=>true,'payment_methods'=>serverConfigLoadPaymentMethods($pdo)]);
        if($action==='settings_save_payment_method'){
            serverConfigRequireAdmin($pdo);
            $id=(int)($_POST['payment_method_id']??0);
            $name=trim((string)($_POST['method_name']??''));
            $account=trim((string)($_POST['account_or_address']??''));
            $additional=trim((string)($_POST['additional_info']??''));
            $company=trim((string)($_POST['company_name']??''));
            $sort=max(0,(int)($_POST['sort_order']??0));
            $enabled=(int)($_POST['is_enabled']??1)===1?1:0;
            if($name==='') serverAppJson(['success'=>false,'error'=>'Payment method name is required.']);
            if(strlen($name)>190||strlen($company)>190) serverAppJson(['success'=>false,'error'=>'Payment method name and company name must be 190 characters or fewer.']);
            if($id>0){
                $q=$pdo->prepare('UPDATE server_payment_methods SET method_name=?,account_or_address=?,additional_info=?,company_name=?,sort_order=?,is_enabled=? WHERE id=?');
                $q->execute([$name,$account,$additional,$company,$sort,$enabled,$id]);
                if($q->rowCount()===0){$check=$pdo->prepare('SELECT id FROM server_payment_methods WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn())serverAppJson(['success'=>false,'error'=>'Payment method record not found.']);}
            }else{
                $q=$pdo->prepare('INSERT INTO server_payment_methods(method_name,account_or_address,additional_info,company_name,sort_order,is_enabled) VALUES(?,?,?,?,?,?)');
                $q->execute([$name,$account,$additional,$company,$sort,$enabled]);
            }
            serverAppJson(['success'=>true,'payment_methods'=>serverConfigLoadPaymentMethods($pdo)]);
        }
        if($action==='settings_delete_payment_method'){
            serverConfigRequireAdmin($pdo);
            $id=(int)($_POST['payment_method_id']??0);
            if($id<=0) serverAppJson(['success'=>false,'error'=>'Select a valid payment method.']);
            $q=$pdo->prepare('DELETE FROM server_payment_methods WHERE id=?');$q->execute([$id]);
            if($q->rowCount()<1) serverAppJson(['success'=>false,'error'=>'Payment method record not found.']);
            serverAppJson(['success'=>true,'payment_methods'=>serverConfigLoadPaymentMethods($pdo)]);
        }
        if($action==='settings_get_brokers') serverAppJson(['success'=>true,'brokers'=>serverConfigLoadBrokers($pdo)]);
        if($action==='settings_get_tiers') serverAppJson(['success'=>true,'tiers'=>serverConfigLoadTiers($pdo)]);
        if($action==='settings_save_tier'){
            serverConfigRequireAdmin($pdo);
            $original=(int)($_POST['original_tier']??0);
            $tier=(int)($_POST['tier']??0);
            $minimum=(float)($_POST['minimum_deposit']??0);
            $maximumRevenue=(float)($_POST['maximum_revenue']??0);
            $maximumDeposit=(float)($_POST['maximum_deposit']??0);
            $description=trim((string)($_POST['description']??''));
            if($tier<1) serverAppJson(['success'=>false,'error'=>'Tier position must be 1 or greater.']);
            if($minimum<0||$maximumRevenue<0||$maximumDeposit<0) serverAppJson(['success'=>false,'error'=>'Tier amounts cannot be negative.']);
            $collision=$pdo->prepare('SELECT tier FROM server_tiers WHERE tier=? AND tier<>? LIMIT 1');
            $collision->execute([$tier,$original]);
            if($collision->fetchColumn()) serverAppJson(['success'=>false,'error'=>'That tier position is already in use. Choose another position.']);
            if($original>0){
                $q=$pdo->prepare('UPDATE server_tiers SET tier=?,minimum_deposit=?,maximum_revenue=?,maximum_deposit=?,description=? WHERE tier=?');
                $q->execute([$tier,$minimum,$maximumRevenue,$maximumDeposit,$description,$original]);
                if($q->rowCount()===0){$check=$pdo->prepare('SELECT tier FROM server_tiers WHERE tier=?');$check->execute([$original]);if(!$check->fetchColumn())serverAppJson(['success'=>false,'error'=>'Tier record not found.']);}
            }else{
                $q=$pdo->prepare('INSERT INTO server_tiers(tier,minimum_deposit,maximum_revenue,maximum_deposit,description) VALUES(?,?,?,?,?)');
                $q->execute([$tier,$minimum,$maximumRevenue,$maximumDeposit,$description]);
            }
            serverAppJson(['success'=>true,'tiers'=>serverConfigLoadTiers($pdo)]);
        }
        if($action==='settings_delete_tier'){
            serverConfigRequireAdmin($pdo);
            $tier=(int)($_POST['tier']??0);
            if($tier<1) serverAppJson(['success'=>false,'error'=>'Select a valid tier position.']);
            $q=$pdo->prepare('DELETE FROM server_tiers WHERE tier=?');$q->execute([$tier]);
            if($q->rowCount()<1) serverAppJson(['success'=>false,'error'=>'Tier record not found.']);
            serverAppJson(['success'=>true,'tiers'=>serverConfigLoadTiers($pdo)]);
        }
        if($action==='settings_save_broker'){
            serverConfigRequireAdmin($pdo);$broker=trim((string)($_POST['broker']??''));$link=trim((string)($_POST['broker_link']??''));
            if($broker===''||$link==='')serverAppJson(['success'=>false,'error'=>'Both broker name and link are required.']);
            if(!filter_var($link,FILTER_VALIDATE_URL)||!preg_match('#^https?://#i',$link))serverAppJson(['success'=>false,'error'=>'Enter a valid http or https URL.']);
            $norm=static function($v){$v=strtolower(preg_replace('#^https?://|^www\\.#i','',(string)$v));return preg_replace('/[^a-z0-9]/','',$v);};
            if($norm($broker)==='' || strpos($norm($link),$norm($broker))===false) serverAppJson(['success'=>false,'error'=>'URL does not match broker name.']);
            $dup=$pdo->prepare('SELECT id FROM server_brokers WHERE LOWER(broker_name)=LOWER(?) LIMIT 1');$dup->execute([$broker]);if($dup->fetchColumn())serverAppJson(['success'=>false,'error'=>'Broker already exists.']);
            $q=$pdo->prepare('INSERT INTO server_brokers(broker_name,broker_url,sort_order) SELECT ?,?,COALESCE(MAX(sort_order),0)+1 FROM server_brokers');$q->execute([$broker,$link]);
            serverAppJson(['success'=>true,'brokers'=>serverConfigLoadBrokers($pdo)]);
        }
        if($action==='settings_delete_broker'){
            serverConfigRequireAdmin($pdo);$brokerId=(int)($_POST['broker_id']??0);
            if($brokerId<=0)serverAppJson(['success'=>false,'error'=>'Select a valid broker record.']);
            $q=$pdo->prepare('DELETE FROM server_brokers WHERE id=?');$q->execute([$brokerId]);
            if($q->rowCount()<1)serverAppJson(['success'=>false,'error'=>'Broker record not found.']);
            serverAppJson(['success'=>true,'brokers'=>serverConfigLoadBrokers($pdo)]);
        }
        if($action==='settings_update_requirements'){
            serverConfigRequireAdmin($pdo);$serverShare=(float)($_POST['server_share_percent']??30);$userShare=(float)($_POST['user_share_percent']??70);
            if($serverShare<0||$userShare<0||abs(($serverShare+$userShare)-100)>0.0001)serverAppJson(['success'=>false,'error'=>'Server and user shares must total 100%.']);
            $minimum=max(0,(float)($_POST['minimum_deposit']??0),(float)($_POST['min_broker_balance']??0));
            $values=[$minimum,$minimum,max(1,(int)($_POST['contract_duration']??30)),$serverShare,$userShare,max(0,(float)($_POST['min_profit_for_split']??30)),max(0,(int)($_POST['expiry_threshold_days']??5))];
            $q=$pdo->prepare('INSERT INTO server_requirements(id,minimum_deposit,min_broker_balance,contract_duration,server_share_percent,user_share_percent,min_profit_for_split,expiry_threshold_days) VALUES(1,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE minimum_deposit=VALUES(minimum_deposit),min_broker_balance=VALUES(min_broker_balance),contract_duration=VALUES(contract_duration),server_share_percent=VALUES(server_share_percent),user_share_percent=VALUES(user_share_percent),min_profit_for_split=VALUES(min_profit_for_split),expiry_threshold_days=VALUES(expiry_threshold_days)');$q->execute($values);
            serverAppJson(['success'=>true]);
        }
        if($action==='settings_update_operational'){
            serverConfigRequireAdmin($pdo);
            $values=[max(0,(int)($_POST['day_challenge_duration']??0)),max(0,(int)($_POST['weekly_challenge_duration']??0)),max(0,(int)($_POST['monthly_challenge_duration']??0)),max(1,(int)($_POST['trades_setup_per_group']??100))];
            $q=$pdo->prepare('INSERT INTO server_operational_settings(id,day_challenge_duration,weekly_challenge_duration,monthly_challenge_duration,trades_setup_per_group) VALUES(1,?,?,?,?) ON DUPLICATE KEY UPDATE day_challenge_duration=VALUES(day_challenge_duration),weekly_challenge_duration=VALUES(weekly_challenge_duration),monthly_challenge_duration=VALUES(monthly_challenge_duration),trades_setup_per_group=VALUES(trades_setup_per_group)');$q->execute($values);
            serverAppJson(['success'=>true,'settings'=>['day_challenge_duration'=>$values[0],'weekly_challenge_duration'=>$values[1],'monthly_challenge_duration'=>$values[2],'trades_setup_per_group'=>$values[3]]]);
        }
        if($action==='settings_update_mailer'){
            serverConfigRequireAdmin($pdo);$email=trim((string)($_POST['mailer_email']??''));$newPassword=(string)($_POST['mailer_password']??'');
            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))serverAppJson(['success'=>false,'error'=>'Enter a valid mailer email address.']);
            if($newPassword===''){$q=$pdo->prepare("INSERT INTO server_mailer_settings(id,mailer_email,mailer_password) VALUES(1,?,'') ON DUPLICATE KEY UPDATE mailer_email=VALUES(mailer_email)");$q->execute([$email]);}
            else{$q=$pdo->prepare('INSERT INTO server_mailer_settings(id,mailer_email,mailer_password) VALUES(1,?,?) ON DUPLICATE KEY UPDATE mailer_email=VALUES(mailer_email),mailer_password=VALUES(mailer_password)');$q->execute([$email,$newPassword]);}
            serverAppJson(['success'=>true]);
        }
        if($action==='settings_update_credentials'){
            if(!serverAppCheckCsrf())serverAppJson(['success'=>false,'error'=>'Form expired. Refreshing this page now.'],419);
            if(!serverAppVerifyAdmin($pdo,(string)($_POST['login_id']??''),(string)($_POST['admin_password_current']??'')))serverAppJson(['success'=>false,'error'=>'Invalid administrator login ID or password.'],403);
            $login=trim((string)($_POST['admin_login_id']??''));$newPassword=(string)($_POST['admin_password']??'');
            if($login==='')serverAppJson(['success'=>false,'error'=>'New administrator login ID is required.']);
            if($newPassword!==''&&strlen($newPassword)<12)serverAppJson(['success'=>false,'error'=>'New password must contain at least 12 characters.']);
            if($newPassword!==''){$q=$pdo->prepare('UPDATE server_admin_credentials SET admin_login_id=?,admin_password_hash=? WHERE id=1');$q->execute([$login,password_hash($newPassword,PASSWORD_DEFAULT)]);}
            else{$q=$pdo->prepare('UPDATE server_admin_credentials SET admin_login_id=? WHERE id=1');$q->execute([$login]);}
            $_SESSION['admin_login_id']=$login;serverAppJson(['success'=>true]);
        }
        if($action==='settings_update_dark_mode'){
            serverConfigRequireAdmin($pdo);$mode=(int)($_POST['dark_mode']??0)===1?1:0;$q=$pdo->prepare('INSERT INTO server_theme_settings(id,dark_mode) VALUES(1,?) ON DUPLICATE KEY UPDATE dark_mode=VALUES(dark_mode)');$q->execute([$mode]);serverAppJson(['success'=>true,'dark_mode'=>$mode]);
        }
        serverAppJson(['success'=>false,'error'=>'Unknown settings action.'],400);
    }catch(Throwable $e){error_log('server_configuration action failed: '.$e->getMessage());serverAppJson(['success'=>false,'error'=>'Unable to save this configuration. Check the relational settings migration and required fields.'],500);}
}
?>
<?php

// Standalone Server Configuration module loaded by serverapp.php.
// Every value is read from and saved to independent relational tables.
// Tier records are held in one table; the tier number is their primary key.

?>

<!doctype html>
<html lang="en" data-theme="<?= serverAppH($serverAppTheme) ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Server Configuration</title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>"><?php require_once __DIR__ . '/server_feature_style.php'; ?>
<style>
.payment-methods-list{display:grid;gap:12px}
.payment-method-row{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;padding:18px;border:1px solid var(--border-color);border-radius:15px;background:var(--container-bg);box-shadow:0 5px 18px var(--shadow-color)}
.payment-method-main{min-width:0;flex:1;display:grid;gap:6px}.payment-method-title{font-size:16px;font-weight:800;color:var(--header-color)}
.payment-method-company{font-size:13px;font-weight:650;color:var(--text-secondary)}
.payment-method-account,.payment-method-additional{white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;line-height:1.55;color:var(--text-color)}
.payment-method-account{padding:10px 12px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px}.payment-method-additional{color:var(--text-muted)}
.payment-method-muted{font-size:13px;color:var(--text-muted);font-style:italic}.payment-method-side{display:flex;flex-direction:column;align-items:flex-end;gap:9px;flex-shrink:0}
.payment-method-status{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:800}.payment-method-status.enabled{background:rgba(37,122,75,.13);color:var(--success-color)}.payment-method-status.disabled{background:var(--bg-tertiary);color:var(--text-muted)}.payment-method-order{font-size:11px;color:var(--text-muted)}
.payment-method-actions,.tier-row-actions{display:flex;gap:7px;flex-wrap:wrap}.tier-table-wrap{width:100%;max-width:100%;overflow-x:auto;border:1px solid var(--border-color);border-radius:13px;scrollbar-width:none}.tier-table-wrap::-webkit-scrollbar{display:none}
.tier-simple-table{width:100%;min-width:930px;border-collapse:separate;border-spacing:0;background:var(--container-bg)}.tier-simple-table th{position:sticky;top:0;z-index:1;padding:13px 12px;text-align:left;font-size:12px;letter-spacing:.02em;color:var(--header-color);background:var(--table-header-bg);border-bottom:1px solid var(--border-color)}
.tier-simple-table td{padding:10px 9px;vertical-align:middle;border-bottom:1px solid var(--border-color)}.tier-simple-table tr:last-child td{border-bottom:0}.tier-input,.tier-description{width:100%;min-width:100px;padding:10px 11px;border:1px solid var(--border-color);border-radius:9px;background:var(--input-bg);color:var(--text-color);font:inherit}.tier-description{min-width:220px;resize:vertical}.tier-position{font-weight:800;color:var(--primary-color)}
.settings-btn-danger{background:rgba(185,67,53,.1)!important;color:var(--danger-color)!important;border:1px solid rgba(185,67,53,.25)!important}
/* Administrator verification must always sit above any editor/confirmation modal. */
#settings-password-modal.settings-modal-overlay{
    position:fixed!important;inset:0!important;z-index:2147483647!important;
    isolation:isolate!important;
}
#settings-password-modal .settings-modal-container{position:relative!important;z-index:1!important}
@media(max-width:680px){.payment-method-row{display:grid;gap:13px;padding:15px}.payment-method-side{display:grid;grid-template-columns:auto 1fr;align-items:center;justify-content:space-between}.payment-method-actions{grid-column:1/-1;width:100%}.payment-method-actions .settings-btn{flex:1;min-height:42px}.tier-table-wrap{margin:0 -2px}.settings-card-header,.settings-card-body,.settings-card-footer{padding-left:15px!important;padding-right:15px!important}}
</style></head><body>
<input type="hidden" id="serverapp-csrf" value="<?= serverAppH($serverAppCsrf) ?>">
<div class="settings-container" id="settings-container"
     data-server-share="<?= (int)($serverAccount['server_share_percent'] ?? 30) ?>"
     data-user-share="<?= (int)($serverAccount['user_share_percent'] ?? 70) ?>"
     data-min-profit="<?= (float)($serverAccount['min_profit_for_split'] ?? 30) ?>"
     data-min-deposit="<?= (float)($serverAccount['min_broker_balance'] ?? 30) ?>">

    <!-- ============================================ -->
    <!-- HEADER                                        -->
    <!-- ============================================ -->
    <div class="settings-header">
        <h2>Server Settings</h2>
        <p class="settings-sub">Manage payment methods, recommended brokers, account requirements, tier limits and administrator settings.</p>
    </div>

    <!-- ============================================ -->
    <!-- MAIN TAB BUTTONS                              -->
    <!-- ============================================ -->
    <div class="settings-tabs-wrapper">
        <div class="settings-tabs main-tabs" id="settings-main-tabs">
            <button class="tab-btn active" data-settings-tab="payment" onclick="Settings.switchTab('payment')">
                Payment Methods
            </button>
            <button class="tab-btn" data-settings-tab="brokers" onclick="Settings.switchTab('brokers')">
                Recommended Brokers
            </button>
            <button class="tab-btn" data-settings-tab="requirements" onclick="Settings.switchTab('requirements')">
                Requirements
            </button>
            <button class="tab-btn" data-settings-tab="operational" onclick="Settings.switchTab('operational')">
                Programme &amp; Analytics
            </button>
            <button class="tab-btn" data-settings-tab="tierlimit" onclick="Settings.switchTab('tierlimit')">
                Tier Limit
            </button>
            <button class="tab-btn" data-settings-tab="mailer" onclick="Settings.switchTab('mailer')">
                Mailer Credentials
            </button>
            <button class="tab-btn" data-settings-tab="credentials" onclick="Settings.switchTab('credentials')">
                Credentials
            </button>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: PAYMENT METHODS -->
    <div id="tab-payment" class="tab-content active">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Payment Methods</h3>
                <p class="settings-card-sub">Add any payment option: bank transfer, cryptocurrency, mobile money, card instructions or another method. Every option is stored as one editable record.</p>
            </div>
            <div class="settings-card-body">
                <div id="payment-methods-list" class="payment-methods-list">
                    <div class="settings-empty">Loading payment methods...</div>
                </div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" type="button" onclick="Settings.openPaymentEdit()">Add New Payment Method</button>
            </div>
        </div>
    </div>

    <!-- TAB 2: RECOMMENDED BROKERS                   -->
    <!-- ============================================ -->
    <div id="tab-brokers" class="tab-content">
        <div class="settings-sub-tabs-wrapper">
            <div class="settings-tabs sub-tabs" id="settings-broker-sub-tabs">
                <button class="tab-btn sub-tab-btn active" data-settings-subtab="existing" onclick="Settings.switchBrokerSubTab('existing')">
                    Existing Brokers
                    <span class="tab-badge" id="broker-existing-count">0</span>
                </button>
                <button class="tab-btn sub-tab-btn" data-settings-subtab="add" onclick="Settings.switchBrokerSubTab('add')">
                    Add New Broker &amp; Link
                </button>
            </div>
        </div>

        <div id="subtab-existing" class="tab-content active">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Existing Brokers</h3>
                    <p class="settings-card-sub">
                        Each broker and its URL are stored together as an individual row in the
                        <code>server_brokers</code> table.
                        Pairs are matched when the broker name (letters/numbers only) is found inside a link.
                        Deleting a broker removes both its name and its matched link.
                    </p>
                </div>
                <div class="settings-card-body">
                    <div id="broker-pairs-list" class="broker-pairs-list">
                        <div class="settings-empty">Loading brokers...</div>
                    </div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn settings-btn-primary" onclick="Settings.switchBrokerSubTab('add')">
                        + Add Broker
                    </button>
                </div>
            </div>
        </div>

        <div id="subtab-add" class="tab-content">
            <div class="settings-card">
                <div class="settings-card-header">
                    <h3>Add Broker &amp; Link</h3>
                    <p class="settings-card-sub">
                        Both fields are required. The broker name (letters/numbers only) must be present inside the URL,
                        otherwise saving is blocked with: <em>"URL does not match broker name."</em>
                        Duplicate broker names are rejected with: <em>"Broker already exists."</em>
                    </p>
                </div>
                <div class="settings-card-body">
                    <div class="settings-field">
                        <label for="settings-broker-name">Broker Name</label>
                        <input type="text" id="settings-broker-name" class="settings-input"
                               placeholder="e.g. Exness">
                        <div class="settings-field-hint">Saved as a record in <code>server_brokers</code>.</div>
                    </div>
                    <div class="settings-field">
                        <label for="settings-broker-link">Broker Link</label>
                        <input type="text" id="settings-broker-link" class="settings-input"
                               placeholder="e.g. https://exness.com/open">
                        <div class="settings-field-hint">Saved alongside its broker in <code>server_brokers</code>.</div>
                    </div>
                    <div class="settings-field-error" id="settings-broker-error" style="display:none;"></div>
                </div>
                <div class="settings-card-footer">
                    <button class="settings-btn" onclick="Settings.switchBrokerSubTab('existing')">Cancel</button>
                    <button class="settings-btn settings-btn-primary" onclick="Settings.saveBroker()">Save Broker</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 3: REQUIREMENTS                          -->
    <!-- ============================================ -->
    <div id="tab-requirements" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Requirements</h3>
                <p class="settings-card-sub">
                    <strong>Minimum Deposit</strong> and <strong>Minimum Broker Balance</strong> are linked — they share the
                    same value and update each other. Server Share % and User Share % must total 100%.
                </p>
            </div>
            <div class="settings-card-body">

                <div class="settings-field">
                    <label for="settings-min-deposit">Minimum Deposit ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-deposit" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['minimum_deposit'] ?? '0.00') ?>"
                           oninput="Settings.onMinDepositChange()">
                    <div class="settings-field-hint">Column: <code>minimum_deposit</code> — linked to Minimum Broker Balance.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-min-broker-balance">Minimum Broker Balance ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-broker-balance" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['min_broker_balance'] ?? '30.00') ?>"
                           oninput="Settings.onMinBrokerBalanceChange()">
                    <div class="settings-field-hint">Column: <code>min_broker_balance</code> — linked to Minimum Deposit.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-contract-duration">Contract Duration (days)</label>
                    <input type="number" min="1" id="settings-contract-duration" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['contract_duration'] ?? '') ?>">
                    <div class="settings-field-hint">Column: <code>contract_duration</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-server-share">Server Share Percent (%)</label>
                    <input type="number" min="0" max="100" id="settings-server-share" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['server_share_percent'] ?? '30') ?>"
                           oninput="Settings.onServerShareChange()">
                    <div class="settings-field-hint">Column: <code>server_share_percent</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-user-share">User Share Percent (%)</label>
                    <input type="number" min="0" max="100" id="settings-user-share" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['user_share_percent'] ?? '70') ?>"
                           oninput="Settings.onUserShareChange()">
                    <div class="settings-field-hint">Column: <code>user_share_percent</code> — Server + User share must total 100%.</div>
                </div>

                <div class="settings-field">
                    <label for="settings-min-profit">Minimum Profit for Split ($)</label>
                    <input type="number" step="0.01" min="0" id="settings-min-profit" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['min_profit_for_split'] ?? '30.00') ?>">
                    <div class="settings-field-hint">Column: <code>min_profit_for_split</code></div>
                </div>

                <div class="settings-field">
                    <label for="settings-expiry-threshold">Expiry Threshold Days</label>
                    <input type="number" min="1" id="settings-expiry-threshold" class="settings-input"
                           value="<?= htmlspecialchars($serverAccount['expiry_threshold_days'] ?? '5') ?>">
                    <div class="settings-field-hint">Column: <code>expiry_threshold_days</code></div>
                </div>

            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveRequirements()">Save Requirements</button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB: PROGRAMME & ANALYTICS OPERATIONAL LIMITS -->
    <!-- ============================================ -->
    <div id="tab-operational" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Programme &amp; Analytics Limits</h3>
                <p class="settings-card-sub">These values are read by the programme provision challenge screen and programme training group builder. They are stored in <code>server_operational_settings</code>.</p>
            </div>
            <div class="settings-card-body">
                <div class="settings-field"><label for="settings-day-challenge">Day Challenge Duration (days)</label><input type="number" min="0" step="1" id="settings-day-challenge" class="settings-input" value="<?= (int)($operationalSettings['day_challenge_duration'] ?? 0) ?>"><div class="settings-field-hint">0 means unconfigured / disabled.</div></div>
                <div class="settings-field"><label for="settings-weekly-challenge">Weekly Challenge Duration (days)</label><input type="number" min="0" step="1" id="settings-weekly-challenge" class="settings-input" value="<?= (int)($operationalSettings['weekly_challenge_duration'] ?? 0) ?>"><div class="settings-field-hint">0 means unconfigured / disabled.</div></div>
                <div class="settings-field"><label for="settings-monthly-challenge">Monthly Challenge Duration (days)</label><input type="number" min="0" step="1" id="settings-monthly-challenge" class="settings-input" value="<?= (int)($operationalSettings['monthly_challenge_duration'] ?? 0) ?>"><div class="settings-field-hint">0 means unconfigured / disabled.</div></div>
                <div class="settings-field"><label for="settings-trades-per-group">Trade Setups Per Group</label><input type="number" min="1" step="1" id="settings-trades-per-group" class="settings-input" value="<?= (int)($operationalSettings['trades_setup_per_group'] ?? 100) ?>"><div class="settings-field-hint">Controls the number of trade setups allowed in each programme training group.</div></div>
            </div>
            <div class="settings-card-footer"><button class="settings-btn settings-btn-primary" onclick="Settings.saveOperational()">Save Programme &amp; Analytics Settings</button></div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 4: TIER LIMITS -->
    <div id="tab-tierlimit" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Tier Limits</h3>
                <p class="settings-card-sub">Each tier is one row. Tier number controls its position in the list.</p>
            </div>
            <div class="settings-card-body">
                <div class="tier-table-wrap">
                    <table class="tier-simple-table">
                        <thead><tr><th>Tier</th><th>Minimum Deposit</th><th>Maximum Revenue</th><th>Maximum Deposit</th><th>Description</th><th>Actions</th></tr></thead>
                        <tbody id="tier-records-list"><tr><td colspan="6" class="settings-empty">Loading tiers...</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" type="button" onclick="Settings.addTierRow()">Add Tier</button>
            </div>
        </div>
    </div>

    <!-- TAB 5: MAILER CREDENTIALS                    -->
    <!-- ============================================ -->
    <div id="tab-mailer" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Mailer Credentials</h3>
                <p class="settings-card-sub">
                    Used by the system for outgoing emails.
                    Stored in the <code>server_mailer_settings</code> table. The saved password is never displayed.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <label for="settings-mailer-email">Mailer Email</label>
                    <input type="email" id="settings-mailer-email" class="settings-input"
                           value="<?= htmlspecialchars($mailerSettings['mailer_email'] ?? '') ?>"
                           placeholder="e.g. no-reply@example.com">
                    <div class="settings-field-hint">Table: <code>server_mailer_settings.mailer_email</code></div>
                </div>
                <div class="settings-field">
                    <label for="settings-mailer-password">Mailer Password</label>
                    <input type="password" id="settings-mailer-password" class="settings-input" autocomplete="new-password"
                           value="" placeholder="Leave blank to keep the saved password">
                    <div class="settings-field-hint">Table: <code>server_mailer_settings.mailer_password</code> · kept hidden</div>
                </div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveMailer()">Save Mailer Credentials</button>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 6: CREDENTIALS                           -->
    <!-- ============================================ -->
    <div id="tab-credentials" class="tab-content">
        <div class="settings-card">
            <div class="settings-card-header">
                <h3>Admin Credentials</h3>
                <p class="settings-card-sub">
                    Change the admin login ID and/or password.
                    Stored in the dedicated <code>server_admin_credentials</code> table. Current credentials are never displayed.
                    Leave the password fields blank if you only want to update the login ID.
                </p>
            </div>
            <div class="settings-card-body">
                <div class="settings-field">
                    <label for="settings-admin-login">Admin Login ID</label>
                    <input type="text" id="settings-admin-login" class="settings-input" value="" placeholder="Enter a new administrator login ID" autocomplete="off">
                    <div class="settings-field-hint">Table: <code>server_admin_credentials.admin_login_id</code>. The current ID is not displayed.</div>
                </div>
                <div class="settings-field">
                    <label for="settings-admin-password">Admin Password (new)</label>
                    <input type="password" id="settings-admin-password" class="settings-input"
                           placeholder="Leave blank to keep current password">
                    <div class="settings-field-hint">Will be hashed and saved in <code>server_admin_credentials.admin_password_hash</code>.</div>
                </div>
                <div class="settings-field">
                    <label for="settings-admin-password-confirm">Confirm Admin Password</label>
                    <input type="password" id="settings-admin-password-confirm" class="settings-input"
                           placeholder="Re-enter new password">
                </div>
        <div class="settings-field-error" id="settings-credentials-error" style="display:none;"></div>
            </div>
            <div class="settings-card-footer">
                <button class="settings-btn settings-btn-primary" onclick="Settings.saveCredentials()">Save Credentials</button>
            </div>
        </div>

        <div class="settings-card" style="margin-top:16px;">
            <div class="settings-card-header"><h3>Appearance</h3><p class="settings-card-sub">The <code>server_theme_settings</code> table controls the appearance of every Server App module. Dark mode = 1; white / light mode = 0.</p></div>
            <div class="settings-card-body"><p>Current appearance: <strong><?= $serverAppDarkMode ? 'Dark mode' : 'White / Light mode' ?></strong></p><button type="button" class="settings-btn settings-btn-primary" onclick="Settings.toggleAppearance()">Switch to <?= $serverAppDarkMode ? 'White / Light' : 'Dark' ?> Mode</button></div>
        </div>
    </div>

</div>

<!-- ============================================ -->
<!-- SETTINGS PASSWORD MODAL                       -->
<!-- ============================================ -->
<div id="settings-password-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-password-modal-title">Security Check</span>
            <span class="settings-modal-close" onclick="Settings.closePasswordModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-password-modal-message">Verify your administrator credentials to continue.</p>
            <div class="settings-field"><label for="settings-password-modal-login-id">Administrator Login ID</label><input type="text" id="settings-password-modal-login-id" class="settings-input" value="<?= serverAppH($currentAdminLoginId) ?>" placeholder="Current administrator login ID" autocomplete="username" readonly aria-readonly="true" tabindex="-1"></div>
            <div class="settings-field"><label for="settings-password-modal-input">Administrator Password</label><input type="password" id="settings-password-modal-input" class="settings-input" value="" placeholder="Enter administrator password" autocomplete="current-password"></div>
            <div id="settings-password-modal-error" class="settings-field-error" style="display:none;"></div>
            <div class="settings-modal-buttons">
                <button class="settings-btn" onclick="Settings.closePasswordModal()">Cancel</button>
                <button class="settings-btn settings-btn-primary" id="settings-password-modal-confirm-btn"
                        onclick="Settings.confirmPasswordModal()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SETTINGS CONFIRM MODAL                        -->
<!-- ============================================ -->
<div id="settings-confirm-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-confirm-modal-title">Confirm Action</span>
            <span class="settings-modal-close" onclick="Settings.closeConfirmModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-confirm-modal-message">Are you sure?</p>
            <div class="settings-modal-buttons">
                <button class="settings-btn" onclick="Settings.closeConfirmModal()">Cancel</button>
                <button class="settings-btn settings-btn-primary" id="settings-confirm-modal-confirm-btn"
                        onclick="Settings.confirmModalAction()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SETTINGS NOTIFICATION MODAL                   -->
<!-- ============================================ -->
<div id="settings-notification-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-small">
        <div class="settings-modal-header">
            <span id="settings-notification-modal-title">Notification</span>
            <span class="settings-modal-close" onclick="Settings.closeNotificationModal()">x</span>
        </div>
        <div class="settings-modal-body">
            <p id="settings-notification-modal-message"></p>
            <div class="settings-modal-buttons">
                <button class="settings-btn settings-btn-primary" onclick="Settings.closeNotificationModal()">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- GENERAL PAYMENT METHOD EDITOR -->
<div id="settings-payment-modal" class="settings-modal-overlay" style="display:none;">
    <div class="settings-modal-container settings-modal-medium">
        <div class="settings-modal-header">
            <span id="settings-payment-modal-title">Add Payment Method</span>
            <span class="settings-modal-close" onclick="Settings.closePaymentEdit()">×</span>
        </div>
        <div class="settings-modal-body">
            <input type="hidden" id="settings-payment-method-id" value="">
            <div class="settings-field"><label for="settings-payment-method-name">Payment Method Name</label><input type="text" id="settings-payment-method-name" class="settings-input" maxlength="190" placeholder="e.g. Bank Transfer, Bitcoin, PayPal"></div>
            <div class="settings-field"><label for="settings-payment-company-name">Company / Institution Name</label><input type="text" id="settings-payment-company-name" class="settings-input" maxlength="190" placeholder="e.g. Example Bank Ltd. (optional)"></div>
            <div class="settings-field"><label for="settings-payment-account">Payment Account / Address</label><textarea id="settings-payment-account" class="settings-input" rows="3" placeholder="Account number, account name, wallet address, payment link or other destination"></textarea></div>
            <div class="settings-field"><label for="settings-payment-additional">Additional Information</label><textarea id="settings-payment-additional" class="settings-input" rows="4" placeholder="Branch, sort code, SWIFT/BIC, network, payment instructions, reference details, or any other information"></textarea></div>
            <div class="settings-form-grid">
                <div class="settings-field"><label for="settings-payment-sort">Display Position</label><input type="number" min="0" step="1" id="settings-payment-sort" class="settings-input" value="0"></div>
                <div class="settings-field"><label for="settings-payment-enabled">Status</label><select id="settings-payment-enabled" class="settings-input"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
            </div>
            <div class="settings-field-error" id="settings-payment-error" style="display:none;"></div>
        </div>
        <div class="settings-modal-buttons settings-modal-buttons-padded">
            <button class="settings-btn" type="button" onclick="Settings.closePaymentEdit()">Cancel</button>
            <button class="settings-btn settings-btn-primary" type="button" onclick="Settings.savePaymentMethod()">Save Payment Method</button>
        </div>
    </div>
</div>

<input type="hidden" id="settings-login-id-hidden" value="">

<script>
const Settings = {
    serverSharePercent: 30,
    userSharePercent: 70,
    minProfitForSplit: 30,
    minBrokerBalance: 30,

    currentTab: 'payment',
    currentBrokerSubTab: 'existing',
    currentTierSubTab: 'tiers',
    brokers: [],

    // Tier data
    paymentMethods: [],
    tiers: [],
    editingPaymentMethodId: 0,
    currentAdminLoginId: '',

    _passwordCallback: null,
    _confirmCallback: null,

    init: function() {
        const adminLoginField = document.getElementById('settings-password-modal-login-id');
        this.currentAdminLoginId = adminLoginField ? adminLoginField.value.trim() : '';
        const container = document.getElementById('settings-container');
        if (container) {
            this.serverSharePercent = parseInt(container.dataset.serverShare) || 30;
            this.userSharePercent   = parseInt(container.dataset.userShare) || 70;
            this.minProfitForSplit  = parseFloat(container.dataset.minProfit) || 30;
            this.minBrokerBalance   = parseFloat(container.dataset.minDeposit) || 30;
        }
        this.loadPaymentMethods();
        this.loadBrokers();
        this.loadTiers();
        this.bindEvents();
    },

    bindEvents: function() {
        const self = this;
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                self.closePasswordModal();
                self.closeConfirmModal();
                self.closeNotificationModal();
                self.closePaymentEdit();
            }
            if (e.key === 'Enter') {
                const pw = document.getElementById('settings-password-modal');
                const cf = document.getElementById('settings-confirm-modal');
                if (pw && pw.style.display === 'flex') {
                    e.preventDefault();
                    self.confirmPasswordModal();
                } else if (cf && cf.style.display === 'flex') {
                    e.preventDefault();
                    self.confirmModalAction();
                }
            }
        });
    },

    // ============================================
    // TAB SWITCHING
    // ============================================
    switchTab: function(tab) {
        this.currentTab = tab;

        document.querySelectorAll('#settings-main-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.settingsTab === tab);
        });

        document.querySelectorAll('#settings-container > .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'tab-' + tab);
        });

        if (tab === 'payment') this.loadPaymentMethods();
        if (tab === 'tierlimit') this.loadTiers();
    },

    switchBrokerSubTab: function(subTab) {
        this.currentBrokerSubTab = subTab;
        document.querySelectorAll('#settings-broker-sub-tabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.settingsSubtab === subTab);
        });
        document.querySelectorAll('#tab-brokers > .tab-content').forEach(el => {
            el.classList.toggle('active', el.id === 'subtab-' + subTab);
        });
    },

    // ============================================
    // MODALS
    // ============================================
    showPasswordModal: function(title, message, callback) {
        const modal = document.getElementById('settings-password-modal');
        const loginField = document.getElementById('settings-password-modal-login-id');
        const passwordField = document.getElementById('settings-password-modal-input');

        document.getElementById('settings-password-modal-title').textContent = title || 'Administrator Verification';
        document.getElementById('settings-password-modal-message').textContent = message || 'Enter your administrator password to continue.';
        if (loginField) loginField.value = this.currentAdminLoginId;
        if (passwordField) passwordField.value = '';
        document.getElementById('settings-login-id-hidden').value = '';
        document.getElementById('settings-password-modal-error').style.display = 'none';
        this._passwordCallback = callback;

        // Move it to the end of <body> and use the highest practical stacking level.
        // This keeps it in front of payment editors, confirmation dialogs and other modals.
        if (modal) {
            document.body.appendChild(modal);
            modal.style.zIndex = '2147483647';
            modal.style.display = 'flex';
        }
        setTimeout(() => passwordField && passwordField.focus(), 100);
    },

    closePasswordModal: function() {
        document.getElementById('settings-password-modal').style.display = 'none';
        document.getElementById('settings-password-modal-login-id').value = '';
        document.getElementById('settings-password-modal-input').value = '';
        this._passwordCallback = null;
        document.getElementById('settings-password-modal-error').style.display = 'none';
    },

    confirmPasswordModal: function() {
        const loginId = this.currentAdminLoginId;
        const password = document.getElementById('settings-password-modal-input').value;
        const errorEl = document.getElementById('settings-password-modal-error');
        if (!password) {
            errorEl.textContent = 'Enter your administrator password to continue.';
            errorEl.style.display = 'block';
            document.getElementById('settings-password-modal-input').focus();
            return;
        }
        if (!loginId) {
            errorEl.textContent = 'The administrator login ID could not be loaded. Refresh this page and try again.';
            errorEl.style.display = 'block';
            return;
        }
        const cb = this._passwordCallback;
        // Existing action callbacks read this hidden ID after password verification.
        document.getElementById('settings-login-id-hidden').value = loginId;
        this.closePasswordModal();
        if (typeof cb === 'function') cb(password);
        document.getElementById('settings-login-id-hidden').value = '';
    },

    showConfirmModal: function(title, message, callback) {
        document.getElementById('settings-confirm-modal-title').textContent = title || 'Confirm Action';
        document.getElementById('settings-confirm-modal-message').textContent = message || 'Are you sure?';
        this._confirmCallback = callback;
        document.getElementById('settings-confirm-modal').style.display = 'flex';
    },

    closeConfirmModal: function() {
        document.getElementById('settings-confirm-modal').style.display = 'none';
        this._confirmCallback = null;
    },

    confirmModalAction: function() {
        const cb = this._confirmCallback;
        this.closeConfirmModal();
        if (typeof cb === 'function') cb();
    },

    showNotification: function(message, title, isError) {
        document.getElementById('settings-notification-modal-title').textContent = title || (isError ? 'Error' : 'Success');
        document.getElementById('settings-notification-modal-message').textContent = message || '';
        document.getElementById('settings-notification-modal').style.display = 'flex';
    },

    closeNotificationModal: function() {
        document.getElementById('settings-notification-modal').style.display = 'none';
    },

    // ============================================
    // LINKED FIELDS
    // ============================================
    onMinDepositChange: function() {
        document.getElementById('settings-min-broker-balance').value =
            document.getElementById('settings-min-deposit').value;
    },
    onMinBrokerBalanceChange: function() {
        document.getElementById('settings-min-deposit').value =
            document.getElementById('settings-min-broker-balance').value;
    },
    onServerShareChange: function() {
        const s = parseInt(document.getElementById('settings-server-share').value) || 0;
        document.getElementById('settings-user-share').value = Math.max(0, 100 - s);
    },
    onUserShareChange: function() {
        const u = parseInt(document.getElementById('settings-user-share').value) || 0;
        document.getElementById('settings-server-share').value = Math.max(0, 100 - u);
    },

    // ============================================
    // AJAX
    // ============================================
    postAction: function(action, params) {
        const body = new URLSearchParams();
        body.append('action', action);
        if (params) Object.entries(params).forEach(([key,value]) => body.append(key, value == null ? '' : String(value)));
        body.append('csrf_token', document.getElementById('serverapp-csrf')?.value || '');
        return fetch(window.location.pathname, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            body: body.toString()
        }).then(async response => {
            const data = await response.json();
            if (response.status === 419) { window.location.reload(); throw new Error('Form expired. Refreshing this page.'); }
            return data;
        });
    },

    toggleAppearance: function() {
        const next = <?= $serverAppDarkMode ? 0 : 1 ?>;
        this.showPasswordModal('Change Appearance', 'Enter your administrator password to change the Server App theme.', password => {
            const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
            this.postAction('settings_update_dark_mode', {dark_mode: next, admin_password: password, login_id: loginId}).then(data => {
                if (data.success) { if (window.parent && window.parent !== window) window.parent.location.reload(); else location.reload(); }
                else this.showNotification(data.error || 'Unable to change appearance.', 'Error', true);
            }).catch(err => this.showNotification(err.message, 'Error', true));
        });
    },

    // ============================================
    // GENERAL PAYMENT METHODS
    // ============================================
    loadPaymentMethods: function() {
        const self=this;
        this.postAction('settings_get_payment_methods',{}).then(data=>{
            if(data.success && Array.isArray(data.payment_methods)) self.paymentMethods=data.payment_methods;
            self.renderPaymentMethods();
        }).catch(()=>self.renderPaymentMethods());
    },
    renderPaymentMethods: function() {
        const target=document.getElementById('payment-methods-list');
        if(!target) return;
        if(!this.paymentMethods.length){target.innerHTML='<div class="settings-empty">No payment methods have been added yet.</div>';return;}
        target.innerHTML=this.paymentMethods.map(method=>{
            const id=Number(method.id)||0;
            const name=this.escapeHtml(method.method_name||'Payment Method');
            const company=this.escapeHtml(method.company_name||'');
            const account=this.escapeHtml(method.account_or_address||'');
            const additional=this.escapeHtml(method.additional_info||'');
            const enabled=Number(method.is_enabled)===1;
            return `<article class="payment-method-row" data-payment-id="${id}">
                <div class="payment-method-main"><div class="payment-method-title">${name}</div>
                ${company?`<div class="payment-method-company">${company}</div>`:''}
                ${account?`<div class="payment-method-account">${account}</div>`:'<div class="payment-method-muted">No account or address supplied</div>'}
                ${additional?`<div class="payment-method-additional">${additional}</div>`:''}</div>
                <div class="payment-method-side"><span class="payment-method-status ${enabled?'enabled':'disabled'}">${enabled?'Enabled':'Disabled'}</span><span class="payment-method-order">Position ${Number(method.sort_order)||0}</span>
                <div class="payment-method-actions"><button class="settings-btn" type="button" onclick="Settings.openPaymentEdit(${id})">Edit</button><button class="settings-btn settings-btn-danger" type="button" onclick="Settings.deletePaymentMethod(${id})">Delete</button></div></div>
            </article>`;
        }).join('');
    },
    openPaymentEdit: function(id=0) {
        const method=this.paymentMethods.find(item=>Number(item.id)===Number(id));
        this.editingPaymentMethodId=method?Number(method.id):0;
        document.getElementById('settings-payment-modal-title').textContent=method?'Edit Payment Method':'Add Payment Method';
        document.getElementById('settings-payment-method-id').value=method?String(method.id):'';
        document.getElementById('settings-payment-method-name').value=method?.method_name||'';
        document.getElementById('settings-payment-company-name').value=method?.company_name||'';
        document.getElementById('settings-payment-account').value=method?.account_or_address||'';
        document.getElementById('settings-payment-additional').value=method?.additional_info||'';
        document.getElementById('settings-payment-sort').value=method?String(method.sort_order||0):String(this.paymentMethods.length?Math.max(...this.paymentMethods.map(x=>Number(x.sort_order)||0))+1:1);
        document.getElementById('settings-payment-enabled').value=method?String(Number(method.is_enabled)===1?1:0):'1';
        document.getElementById('settings-payment-error').style.display='none';
        document.getElementById('settings-payment-modal').style.display='flex';
    },
    closePaymentEdit: function() {
        const modal=document.getElementById('settings-payment-modal');if(modal)modal.style.display='none';
        this.editingPaymentMethodId=0;
    },
    savePaymentMethod: function() {
        const self=this;
        const error=document.getElementById('settings-payment-error');
        const payload={
            payment_method_id:document.getElementById('settings-payment-method-id').value,
            method_name:document.getElementById('settings-payment-method-name').value.trim(),
            company_name:document.getElementById('settings-payment-company-name').value.trim(),
            account_or_address:document.getElementById('settings-payment-account').value.trim(),
            additional_info:document.getElementById('settings-payment-additional').value.trim(),
            sort_order:document.getElementById('settings-payment-sort').value,
            is_enabled:document.getElementById('settings-payment-enabled').value
        };
        if(!payload.method_name){error.textContent='Enter the payment method name.';error.style.display='block';return;}
        this.showPasswordModal(payload.payment_method_id?'Edit Payment Method':'Add Payment Method','Verify your administrator credentials to save this payment method.',function(password){
            payload.admin_password=password;payload.login_id=document.getElementById('settings-login-id-hidden')?.value||'';
            self.postAction('settings_save_payment_method',payload).then(data=>{
                if(!data.success)throw new Error(data.error||'Unable to save payment method.');
                self.paymentMethods=data.payment_methods||[];self.renderPaymentMethods();self.closePaymentEdit();self.showNotification('Payment method saved.','Success',false);
            }).catch(err=>{error.textContent=err.message;error.style.display='block';});
        });
    },
    deletePaymentMethod: function(id) {
        const self=this;
        const method=this.paymentMethods.find(item=>Number(item.id)===Number(id));
        if(!method)return;
        this.showConfirmModal('Delete Payment Method','Delete "'+method.method_name+'"? This does not alter payment records already submitted.',function(){
            self.showPasswordModal('Delete Payment Method','Verify your administrator credentials to delete this payment method.',function(password){
                self.postAction('settings_delete_payment_method',{payment_method_id:id,admin_password:password,login_id:document.getElementById('settings-login-id-hidden')?.value||''}).then(data=>{
                    if(!data.success)throw new Error(data.error||'Unable to delete payment method.');
                    self.paymentMethods=data.payment_methods||[];self.renderPaymentMethods();self.showNotification('Payment method deleted.','Success',false);
                }).catch(err=>self.showNotification(err.message,'Error',true));
            });
        });
    },

    // ============================================
    // BROKERS
    // ============================================
    loadBrokers: function() {
        const self = this;
        this.postAction('settings_get_brokers', {}).then(data => {
            if (data.success && Array.isArray(data.brokers)) self.brokers = data.brokers;
            self.renderBrokerPairs();
        }).catch(() => self.renderBrokerPairs());
    },
    matchBrokerPairs: function() {
        return this.brokers.map(row => ({
            id: Number(row.id),
            broker: String(row.broker_name || ''),
            link: String(row.broker_url || '')
        }));
    },
    renderBrokerPairs: function() {
        const container = document.getElementById('broker-pairs-list');
        const pairs = this.matchBrokerPairs();
        document.getElementById('broker-existing-count').textContent = this.brokers.length;
        if (pairs.length === 0) {
            container.innerHTML = '<div class="settings-empty">No brokers added yet.</div>';
            return;
        }
        let html = '';
        pairs.forEach((p, idx) => {
            const brokerEsc = this.escapeHtml(p.broker || '');
            const linkEsc = this.escapeHtml(p.link || '');
            html += `
                <div class="broker-pair-row">
                    <div class="broker-pair-index">${idx + 1}</div>
                    <div class="broker-pair-body">
                        <div class="broker-pair-name">${p.broker ? brokerEsc : '— (unmatched link)'}</div>
                        <div class="broker-pair-link">
                            ${p.link
                                ? `<a href="${linkEsc}" target="_blank" rel="noopener">${linkEsc}</a>`
                                : '<span class="broker-pair-nolink">No matching link</span>'}
                        </div>
                    </div>
                    <div class="broker-pair-actions">
                        <button class="broker-delete-btn" title="Delete broker"
                                onclick="Settings.deleteBroker(${idx})">Delete</button>
                    </div>
                </div>`;
        });
        container.innerHTML = html;
    },
    deleteBroker: function(pairIndex) {
        const pairs = this.matchBrokerPairs();
        const pair = pairs[pairIndex];
        if (!pair) return;
        const self = this;
        const brokerName = pair.broker || '(unmatched link)';
        const linkName = pair.link || '(no link)';

        this.showConfirmModal(
            'Delete Broker',
            'Are you sure you want to delete "' + brokerName + '" and its link "' + linkName + '"? This cannot be undone.',
            function() {
                self.showPasswordModal(
                    'Delete Broker',
                    'Enter admin password to delete this broker and its link.',
                    function(password) {
                        const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                        self.postAction('settings_delete_broker', {
                            broker_id: pair.id,
                            admin_password: password,
                            login_id: loginId
                        }).then(data => {
                            if (data.success) {
                                self.brokers = Array.isArray(data.brokers) ? data.brokers : [];
                                self.renderBrokerPairs();
                                self.showNotification('Broker deleted successfully!', 'Success', false);
                            } else if (data.error === 'Invalid password') {
                                self.showNotification('Password verification failed.', 'Error', true);
                            } else {
                                self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                            }
                        }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
                    }
                );
            }
        );
    },
    saveBroker: function() {
        const name = document.getElementById('settings-broker-name').value.trim();
        const link = document.getElementById('settings-broker-link').value.trim();
        const errorEl = document.getElementById('settings-broker-error');
        errorEl.style.display = 'none';

        if (!name || !link) {
            errorEl.textContent = 'Both Broker Name and Broker Link are required.';
            errorEl.style.display = 'block';
            return;
        }

        const existsLocally = this.brokers.some(b => String(b.broker_name || '').toLowerCase() === name.toLowerCase());
        if (existsLocally) {
            errorEl.textContent = 'Broker already exists.';
            errorEl.style.display = 'block';
            return;
        }

        const normalize = s => String(s || '').toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/[^a-z0-9]/g, '');
        const nameNorm = normalize(name);
        const linkNorm = normalize(link);
        if (!nameNorm) {
            errorEl.textContent = 'Broker Name must contain letters or numbers.';
            errorEl.style.display = 'block';
            return;
        }
        if (!linkNorm.includes(nameNorm)) {
            errorEl.textContent = 'URL does not match broker name. "' + name + '" was not found in the URL.';
            errorEl.style.display = 'block';
            return;
        }
        const self = this;
        this.showPasswordModal(
            'Save Broker',
            'Enter admin password to save this broker and link.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                self.postAction('settings_save_broker', {
                    broker: name, broker_link: link,
                    admin_password: password, login_id: loginId
                }).then(data => {
                    if (data.success) {
                        self.brokers = Array.isArray(data.brokers) ? data.brokers : [];
                        self.renderBrokerPairs();
                        document.getElementById('settings-broker-name').value = '';
                        document.getElementById('settings-broker-link').value = '';
                        self.switchBrokerSubTab('existing');
                        self.showNotification('Broker saved successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        errorEl.textContent = data.error || 'Unknown error';
                        errorEl.style.display = 'block';
                    }
                }).catch(err => {
                    errorEl.textContent = 'Error: ' + err.message;
                    errorEl.style.display = 'block';
                });
            }
        );
    },

    // ============================================
    // REQUIREMENTS
    // ============================================
    saveRequirements: function() {
        const self = this;
        const payload = {
            minimum_deposit:      document.getElementById('settings-min-deposit').value,
            min_broker_balance:   document.getElementById('settings-min-broker-balance').value,
            contract_duration:    document.getElementById('settings-contract-duration').value,
            server_share_percent: document.getElementById('settings-server-share').value,
            user_share_percent:   document.getElementById('settings-user-share').value,
            min_profit_for_split: document.getElementById('settings-min-profit').value,
            expiry_threshold_days: document.getElementById('settings-expiry-threshold').value
        };
        const s = parseInt(payload.server_share_percent) || 0;
        const u = parseInt(payload.user_share_percent) || 0;
        if (s + u !== 100) {
            this.showNotification('Server Share + User Share must equal 100%. Currently ' + (s + u) + '%.', 'Error', true);
            return;
        }
        this.showPasswordModal(
            'Save Requirements',
            'Enter admin password to save requirements.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                payload.admin_password = password;
                payload.login_id = loginId;
                self.postAction('settings_update_requirements', payload).then(data => {
                    if (data.success) {
                        self.showNotification('Requirements saved successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    // ============================================
    // SINGLE-TABLE TIER LIMITS
    // ============================================
    loadTiers: function() {
        const self=this;
        this.postAction('settings_get_tiers',{}).then(data=>{
            self.tiers=(data.success&&Array.isArray(data.tiers))?data.tiers:[];
            self.renderTierRecords();
        }).catch(()=>{self.tiers=[];self.renderTierRecords();});
    },
    renderTierRecords: function() {
        const target=document.getElementById('tier-records-list');
        if(!target)return;
        if(!this.tiers.length){target.innerHTML='<tr><td colspan="6" class="settings-empty">No tiers have been configured. Use Add Tier to create the first row.</td></tr>';return;}
        target.innerHTML=this.tiers.map(row=>this.tierRowHtml(row)).join('');
    },
    tierRowHtml: function(row) {
        const tier=Number(row.tier)||1;
        return `<tr class="tier-simple-row" data-original-tier="${tier}">
            <td><input class="tier-input tier-position" type="number" min="1" step="1" value="${tier}" aria-label="Tier position"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="${Number(row.minimum_deposit)||0}" aria-label="Minimum deposit"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="${Number(row.maximum_revenue)||0}" aria-label="Maximum revenue"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="${Number(row.maximum_deposit)||0}" aria-label="Maximum deposit"></td>
            <td><textarea class="tier-description" rows="2" aria-label="Description">${this.escapeHtml(row.description||'')}</textarea></td>
            <td class="tier-row-actions"><button class="settings-btn settings-btn-primary" type="button" onclick="Settings.saveTierRow(this)">Save</button><button class="settings-btn settings-btn-danger" type="button" onclick="Settings.deleteTier(${tier})">Delete</button></td>
        </tr>`;
    },
    addTierRow: function() {
        const target=document.getElementById('tier-records-list');
        if(!target)return;
        const next=this.tiers.length?Math.max(...this.tiers.map(row=>Number(row.tier)||0))+1:1;
        if(target.querySelector('[data-new-tier="1"]')){target.querySelector('[data-new-tier="1"] .tier-position')?.focus();return;}
        if(!this.tiers.length)target.innerHTML='';
        const row=document.createElement('tr');row.className='tier-simple-row';row.dataset.originalTier='';row.dataset.newTier='1';
        row.innerHTML=`<td><input class="tier-input tier-position" type="number" min="1" step="1" value="${next}" aria-label="Tier position"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="0" aria-label="Minimum deposit"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="0" aria-label="Maximum revenue"></td>
            <td><input class="tier-input" type="number" min="0" step="0.01" value="0" aria-label="Maximum deposit"></td>
            <td><textarea class="tier-description" rows="2" aria-label="Description"></textarea></td>
            <td class="tier-row-actions"><button class="settings-btn settings-btn-primary" type="button" onclick="Settings.saveTierRow(this)">Save</button><button class="settings-btn" type="button" onclick="this.closest('tr').remove()">Cancel</button></td>`;
        target.appendChild(row);row.querySelector('.tier-position')?.focus();
    },
    saveTierRow: function(button) {
        const self=this,row=button.closest('tr');if(!row)return;
        const inputs=row.querySelectorAll('.tier-input');
        const payload={
            original_tier:row.dataset.originalTier||'',
            tier:inputs[0]?.value||'',
            minimum_deposit:inputs[1]?.value||'0',
            maximum_revenue:inputs[2]?.value||'0',
            maximum_deposit:inputs[3]?.value||'0',
            description:row.querySelector('.tier-description')?.value||''
        };
        if(!Number.isInteger(Number(payload.tier))||Number(payload.tier)<1){this.showNotification('Tier position must be a whole number greater than zero.','Invalid Tier',true);return;}
        if([payload.minimum_deposit,payload.maximum_revenue,payload.maximum_deposit].some(value=>!Number.isFinite(Number(value))||Number(value)<0)){this.showNotification('Tier amounts must be valid non-negative numbers.','Invalid Tier',true);return;}
        this.showPasswordModal('Save Tier','Verify your administrator credentials to save this tier row.',function(password){
            payload.admin_password=password;payload.login_id=document.getElementById('settings-login-id-hidden')?.value||'';
            self.postAction('settings_save_tier',payload).then(data=>{
                if(!data.success)throw new Error(data.error||'Unable to save tier.');
                self.tiers=data.tiers||[];self.renderTierRecords();self.showNotification('Tier saved.','Success',false);
            }).catch(err=>self.showNotification(err.message,'Error',true));
        });
    },
    deleteTier: function(tier) {
        const self=this;
        this.showConfirmModal('Delete Tier','Delete tier '+tier+'? This cannot be undone.',function(){
            self.showPasswordModal('Delete Tier','Verify your administrator credentials to delete this tier.',function(password){
                self.postAction('settings_delete_tier',{tier,admin_password:password,login_id:document.getElementById('settings-login-id-hidden')?.value||''}).then(data=>{
                    if(!data.success)throw new Error(data.error||'Unable to delete tier.');
                    self.tiers=data.tiers||[];self.renderTierRecords();self.showNotification('Tier deleted.','Success',false);
                }).catch(err=>self.showNotification(err.message,'Error',true));
            });
        });
    },

    // ============================================
    // PROGRAMME & ANALYTICS OPERATIONAL SETTINGS
    // ============================================
    saveOperational: function() {
        const self = this;
        const payload = {
            day_challenge_duration: document.getElementById('settings-day-challenge').value,
            weekly_challenge_duration: document.getElementById('settings-weekly-challenge').value,
            monthly_challenge_duration: document.getElementById('settings-monthly-challenge').value,
            trades_setup_per_group: document.getElementById('settings-trades-per-group').value
        };
        this.showPasswordModal('Save Programme & Analytics Settings', 'Verify your administrator credentials to update challenge durations and trade group size.', function(password) {
            payload.admin_password = password;
            payload.login_id = document.getElementById('settings-login-id-hidden')?.value || '';
            self.postAction('settings_update_operational', payload).then(data => {
                if (data.success) self.showNotification('Programme and analytics settings saved.', 'Success', false);
                else self.showNotification(data.error || 'Unable to save operational settings.', 'Error', true);
            }).catch(err => self.showNotification(err.message, 'Error', true));
        });
    },

    // ============================================
    // MAILER
    // ============================================
    saveMailer: function() {
        const self = this;
        const payload = {
            mailer_email:    document.getElementById('settings-mailer-email').value.trim(),
            mailer_password: document.getElementById('settings-mailer-password').value
        };
        this.showPasswordModal(
            'Save Mailer Credentials',
            'Enter admin password to save mailer credentials.',
            function(password) {
                const loginId = document.getElementById('settings-login-id-hidden')?.value || '';
                payload.admin_password = password;
                payload.login_id = loginId;
                self.postAction('settings_update_mailer', payload).then(data => {
                    if (data.success) {
                        self.showNotification('Mailer credentials saved!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    // ============================================
    // CREDENTIALS
    // ============================================
    saveCredentials: function() {
        const self = this;
        const errorEl = document.getElementById('settings-credentials-error');
        errorEl.style.display = 'none';

        const loginId = document.getElementById('settings-admin-login').value.trim();
        const pw      = document.getElementById('settings-admin-password').value;
        const pwConfirm = document.getElementById('settings-admin-password-confirm').value;

        if (!loginId) {
            errorEl.textContent = 'Admin Login ID is required.';
            errorEl.style.display = 'block';
            return;
        }
        if (pw && pw !== pwConfirm) {
            errorEl.textContent = 'Password and Confirm Password do not match.';
            errorEl.style.display = 'block';
            return;
        }

        this.showPasswordModal(
            'Save Credentials',
            'Enter current admin password to confirm changes.',
            function(currentPassword) {
                const payload = {
                    admin_login_id: loginId,
                    admin_password: pw || '',
                    admin_password_current: currentPassword,
                    login_id: document.getElementById('settings-login-id-hidden')?.value || ''
                };
                self.postAction('settings_update_credentials', payload).then(data => {
                    if (data.success) {
                        self.currentAdminLoginId = loginId;
                        const confirmationLoginField = document.getElementById('settings-password-modal-login-id');
                        if (confirmationLoginField) confirmationLoginField.value = loginId;
                        document.getElementById('settings-admin-login').value = '';
                        document.getElementById('settings-admin-password').value = '';
                        document.getElementById('settings-admin-password-confirm').value = '';
                        self.showNotification('Credentials updated successfully!', 'Success', false);
                    } else if (data.error === 'Invalid password') {
                        self.showNotification('Current password verification failed.', 'Error', true);
                    } else {
                        self.showNotification('Error: ' + (data.error || 'Unknown error'), 'Error', true);
                    }
                }).catch(err => self.showNotification('Error: ' + err.message, 'Error', true));
            }
        );
    },

    escapeHtml: function(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            if (m === '"') return '&quot;';
            if (m === "'") return '&#039;';
            return m;
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    Settings.init();
});
</script>
</body></html>
