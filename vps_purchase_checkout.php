<?php
/** Shared checkout renderer with strict account/programme request isolation. */
require_once __DIR__ . '/vps_purchase_guard.php';

if (!isset($purchaseType) || !in_array($purchaseType, ['account','programme'], true)) {
    http_response_code(404);
    exit('VPS checkout route not found.');
}
$type = $purchaseType;
$programmeId = 0;
$programmeName = '';
if ($type === 'programme') {
    $programmeId = (int)($_SESSION['selected_programme_id'] ?? 0);
    if ($programmeId <= 0) { http_response_code(403); exit('Select a programme before purchasing a programme VPS.'); }
    $programmeColumns = serverVpsSchemaColumns($purchasePdo, 'programme');
    $programmeSql = 'SELECT id,userid' . (isset($programmeColumns['sub_account_id']) ? ',sub_account_id' : '') . ',program_name FROM programme WHERE id=? AND userid=? LIMIT 1';
    $q = $purchasePdo->prepare($programmeSql);
    $q->execute([$programmeId, $purchaseUserId]);
    $programme = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$programme) { http_response_code(403); exit('The selected programme does not belong to this account.'); }
    if (isset($programme['sub_account_id']) && (int)$programme['sub_account_id'] > 0 && (int)$programme['sub_account_id'] !== $purchaseSubId) {
        http_response_code(403); exit('The selected programme belongs to another subaccount.');
    }
    $programmeName = trim((string)($programme['program_name'] ?? '')) ?: 'Programme #' . $programmeId;
}

$pageFile = ($type === 'programme') ? 'purchaseprogrammevps.php' : 'purchaseaccountvps.php';
$hubPage = ($type === 'programme') ? 'programme_vps.php' : 'vps.php';
$returnType = $type; // The wrapper filename is the authority; a query string can never switch categories.
$scriptDirectory = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
if ($scriptDirectory === '/' || $scriptDirectory === '.' || $scriptDirectory === '') $scriptDirectory = '';
$sitePrefix = $scriptDirectory !== '' ? rtrim($scriptDirectory, '/') . '/' : '/';
$checkoutPageUrl = $sitePrefix . $pageFile;
$hubPurchaseUrl = $sitePrefix . $hubPage . '?vps_tab=purchase';
$hubPendingUrl = $sitePrefix . $hubPage . '?vps_tab=pending';
$hubMyVpsUrl = $sitePrefix . $hubPage . '?vps_tab=myvps';
$appShellFile = ($type === 'programme') ? 'traderapp.php' : 'investorapp.php';
$appShellQuery = ($type === 'programme')
    ? 'tab=vps&vps_from=menu'
    : 'tab=vps&switched=1&vps_from=menu';
$shellReturnUrl = $sitePrefix . $appShellFile . '?' . $appShellQuery;
$sourceReturnTabs = ($type === 'programme')
    ? ['signals', 'menu', 'training', 'analytics']
    : ['mydashboard', 'trades', 'activity', 'analytics', 'menu', 'revenue_history', 'profit_split', 'vps', 'disconnect_broker', 'programmes', 'connect_investor_broker'];
$returnStorageKey = ($type === 'programme')
    ? 'harvhub_programme_vps_return_tab'
    : 'harvhub_investor_vps_return_tab';
$requestedReturnType = (string)($_GET['return_to'] ?? $type);
if ($requestedReturnType !== $type) {
    // Ignore mismatched return hints. The account/programme wrapper remains authoritative.
    $requestedReturnType = $type;
}
$targetLabel = ($type === 'programme') ? $programmeName : purchaseUserDisplayName($purchaseUser);
$checkoutDarkMode = (int)($purchaseUser['dark_mode'] ?? 0) === 1;
$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

function vpsCheckoutOwnedRequest(PDO $pdo, int $id, string $type, int $userId, int $subId, int $programmeId): array {
    if ($id <= 0) throw new RuntimeException('Purchase request not found.');
    $scopeSql = ($type === 'programme')
        ? "r.request_type='programme' AND r.user_id=? AND r.sub_account_id=? AND r.programme_id=?"
        : "r.request_type='account' AND r.user_id=? AND r.sub_account_id=? AND r.programme_id IS NULL";
    $scopeParams = ($type === 'programme') ? [$userId,$subId,$programmeId] : [$userId,$subId];
    $sql = "SELECT r.*,
        COALESCE(rv.id,r.registered_vps_id) AS current_registered_vps_id,
        COALESCE(NULLIF(rv.product_name,''),NULLIF(a.product_name,''),'VPS Plan') AS product_name,
        COALESCE(NULLIF(rv.provider_name,''),NULLIF(a.provider_name,''),'HarvHub VPS') AS provider_name,
        COALESCE(NULLIF(rv.plan_name,''),NULLIF(a.plan_name,''),'') AS plan_name,
        COALESCE(rv.plan_description,a.plan_description) AS plan_description,
        COALESCE(rv.cpu_type,a.cpu_type) AS cpu_type,
        COALESCE(rv.vcpu_count,a.vcpu_count,0) AS vcpu_count,
        COALESCE(rv.ram_gb,a.ram_gb,0) AS ram_gb,
        COALESCE(rv.storage_type,a.storage_type) AS storage_type,
        COALESCE(rv.storage_gb,a.storage_gb,0) AS storage_gb,
        COALESCE(rv.network_port_speed_mbps,a.network_port_speed_mbps) AS network_port_speed_mbps,
        COALESCE(rv.data_transfer,a.data_transfer) AS data_transfer,
        COALESCE(NULLIF(rv.server_location,''),NULLIF(a.server_location,''),'') AS server_location,
        COALESCE(rv.region_code,a.region_code) AS region_code,
        COALESCE(rv.operating_system,a.operating_system) AS operating_system,
        COALESCE(rv.virtualization_type,a.virtualization_type) AS virtualization_type,
        COALESCE(rv.ipv4_included,a.ipv4_included,0) AS ipv4_included,
        COALESCE(rv.ipv6_included,a.ipv6_included,0) AS ipv6_included,
        COALESCE(rv.backup_option,a.backup_option) AS backup_option,
        COALESCE(rv.snapshot_count,a.snapshot_count,0) AS snapshot_count,
        COALESCE(rv.billing_period,a.billing_period,'monthly') AS billing_period,
        COALESCE(rv.subscription_duration_days,a.subscription_duration_days,30) AS subscription_duration_days,
        COALESCE(rv.price,a.price,0) AS plan_price,
        COALESCE(rv.currency,a.currency,r.currency,'USD') AS plan_currency,
        COALESCE(rv.setup_fee,a.setup_fee,0) AS setup_fee,
        GREATEST(COALESCE(a.stock_quantity,rv.stock_quantity,0)-COALESCE(a.allocated_quantity,0),0) AS available_units,
        COALESCE(rv.registration_status,'') AS registration_status
        FROM server_vps_purchase_requests r
        LEFT JOIN server_available_vps a ON a.id=r.vps_id
        LEFT JOIN server_registered_vps rv ON rv.purchase_request_id=r.id
        WHERE r.id=? AND {$scopeSql} LIMIT 1";
    $q = $pdo->prepare($sql);
    $q->execute(array_merge([$id],$scopeParams));
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('This purchase request is not assigned to the active account or programme.');
    return $row;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $isPurchaseAjax) {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'begin_purchase') {
        if (!purchaseCheckCsrf()) purchaseJson(['success'=>false,'message'=>'Form expired. Refresh the page and try again.'],419);
        $postedReturnType = (string)($_POST['return_to'] ?? '');
        if ($postedReturnType !== $type) purchaseJson(['success'=>false,'message'=>'The selected checkout category does not match this VPS page. Return to the correct VPS hub and try again.'],403);
        try {
            $planId = (int)($_POST['vps_id'] ?? 0);
            $id = purchaseBeginRequest($purchasePdo,$type,$purchaseUser,$purchaseSubId,$planId,$programmeId);
            purchaseJson(['success'=>true,'request_id'=>$id,'redirect'=>$checkoutPageUrl.'?request_id='.$id.'&return_to='.rawurlencode($returnType)]);
        } catch (Throwable $e) {
            error_log('[VPS checkout]['.$type.'] begin purchase: '.$e->getMessage());
            purchaseJson(['success'=>false,'message'=>$e->getMessage()],400);
        }
    }
    purchaseJson(['success'=>false,'message'=>'Unknown checkout action.'],400);
}

$paymentError = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['submit_payment'])) {
    if (!purchaseCheckCsrf()) { http_response_code(419); exit('The checkout form expired. Refresh the page and try again.'); }
    if ((string)($_POST['return_to'] ?? '') !== $type) { http_response_code(403); exit('The selected checkout category does not match this VPS page.'); }
    try {
        purchaseMarkPayment(
            $purchasePdo,
            $requestId,
            $type,
            $purchaseUserId,
            $purchaseSubId,
            $programmeId,
            (int)($_POST['payment_method_id'] ?? 0),
            trim((string)($_POST['payment_reference'] ?? ''))
        );
        header('Location: '.$checkoutPageUrl.'?request_id='.$requestId.'&return_to='.rawurlencode($returnType).'&submitted=1', true, 303);
        exit;
    } catch (Throwable $e) {
        $paymentError = $e->getMessage();
    }
}

if (($_GET['action'] ?? '') === 'status') {
    if ((string)($_GET['return_to'] ?? $type) !== $type) purchaseJson(['success'=>false,'message'=>'Checkout category mismatch.'],403);
    try {
        $row = vpsCheckoutOwnedRequest($purchasePdo,(int)($_GET['request_id'] ?? 0),$type,$purchaseUserId,$purchaseSubId,$programmeId);
        $registeredId = (int)($row['current_registered_vps_id'] ?? 0);
        $status = (string)($row['payment_status'] ?? '');
        $registrationStatus = (string)($row['registration_status'] ?? '');
        if ($registeredId > 0 && $registrationStatus === '') {
            $q = $purchasePdo->prepare('SELECT registration_status FROM server_registered_vps WHERE id=? LIMIT 1');
            $q->execute([$registeredId]);
            $registrationStatus = (string)($q->fetchColumn() ?: '');
        }
        $hubTab = ($registrationStatus === 'registered') ? 'myvps' : 'pending';
        $hubRoute = ($hubTab === 'myvps') ? $hubMyVpsUrl : $hubPendingUrl;
        purchaseJson(['success'=>true,'payment_status'=>$status,'registration_status'=>$registrationStatus,'hub_url'=>$hubRoute]);
    } catch (Throwable $e) {
        purchaseJson(['success'=>false,'message'=>$e->getMessage()],404);
    }
}

$request = null;
$requestRegistrationStatus = '';
$methods = [];
if ($requestId > 0) {
    if ((string)($_GET['return_to'] ?? $type) !== $type) { http_response_code(403); exit('Checkout category mismatch.'); }
    try {
        $request = vpsCheckoutOwnedRequest($purchasePdo,$requestId,$type,$purchaseUserId,$purchaseSubId,$programmeId);
        $requestRegistrationStatus = (string)($request['registration_status'] ?? '');
    } catch (Throwable $e) {
        http_response_code(404);
        exit(htmlspecialchars($e->getMessage(),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
    }
    $methods = $purchasePdo->query('SELECT id,method_name,account_or_address,additional_info,company_name FROM server_payment_methods WHERE is_enabled=1 ORDER BY sort_order ASC,id ASC')->fetchAll(PDO::FETCH_ASSOC);
}
function vpsCheckoutH($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function vpsCheckoutMoney($value,$currency): string { return vpsCheckoutH($currency ?: 'USD').' '.number_format((float)$value,2); }
function vpsCheckoutValue($value, string $fallback = 'Not specified'): string {
    $text = trim((string)($value ?? ''));
    return $text === '' ? $fallback : $text;
}
$paymentStatus = (string)($request['payment_status'] ?? '');
$canSubmitPayment = $request && $paymentStatus === 'awaiting_payment';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Vps Purchase Checkout - HarvHub</title>
<style>
:root{color-scheme:light;--checkout-bg:#f3f6f8;--checkout-card:#fff;--checkout-text:#17212b;--checkout-muted:#647482;--checkout-border:#dfe6eb;--checkout-panel:#f7f9fb;--checkout-input:#fff;--checkout-accent:#159a6d;--checkout-warn-bg:#fff8e7;--checkout-warn-text:#855d08;--checkout-error-bg:#fff0ef;--checkout-error-text:#a93232;--checkout-success-bg:#eafaf1;--checkout-success-text:#176a42;--checkout-shadow:0 8px 28px rgba(16,32,48,.07)}
body.dark-mode{color-scheme:dark;--checkout-bg:#0b1210;--checkout-card:#131d1a;--checkout-text:#e6edf3;--checkout-muted:#9aa9a2;--checkout-border:#303d37;--checkout-panel:#1a2823;--checkout-input:#17221d;--checkout-accent:#2ecc8f;--checkout-warn-bg:#2b2518;--checkout-warn-text:#e1b65c;--checkout-error-bg:#2b1b1b;--checkout-error-text:#ff8d86;--checkout-success-bg:#182b20;--checkout-success-text:#70d69d;--checkout-shadow:0 10px 32px rgba(0,0,0,.3)}
*{box-sizing:border-box}html,body,*{scrollbar-width:none!important;-ms-overflow-style:none!important}html::-webkit-scrollbar,body::-webkit-scrollbar,*::-webkit-scrollbar{display:none!important;width:0!important;height:0!important}html,body{min-height:100%;margin:0}body{background:var(--checkout-bg);color:var(--checkout-text);font:15px/1.55 -apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;padding:86px 18px 32px;transition:background-color .18s ease,color .18s ease}
.checkout-header{position:fixed;z-index:1000;top:0;left:0;right:0;height:66px;display:grid;grid-template-columns:52px minmax(0,1fr) 52px;align-items:center;padding:0 max(16px,env(safe-area-inset-left));background:var(--checkout-card);border-bottom:1px solid var(--checkout-border);box-shadow:0 3px 14px rgba(0,0,0,.05)}
.checkout-back{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;color:var(--checkout-text);text-decoration:none;font-size:25px;font-weight:500}.checkout-back:hover{background:var(--checkout-panel)}.checkout-title{margin:0;text-align:center;font-size:17px;font-weight:800;letter-spacing:.1px;color:var(--checkout-text)}
.wrap{width:min(100%,960px);margin:0 auto}.card{background:var(--checkout-card);color:var(--checkout-text);border:1px solid var(--checkout-border);border-radius:17px;padding:22px;margin:0 0 17px;box-shadow:var(--checkout-shadow)}h1{font-size:22px;margin:0 0 5px;color:var(--checkout-text)}h2{font-size:18px;margin:0 0 13px;color:var(--checkout-text)}h3{margin:0;color:var(--checkout-text)}.muted{color:var(--checkout-muted);font-size:13px}.purchase-target{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin-top:7px;color:var(--checkout-muted);font-size:13px}.target-chip{border:1px solid var(--checkout-border);background:var(--checkout-panel);padding:5px 9px;border-radius:999px;color:var(--checkout-text);font-weight:700}
.product-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:15px}.provider{font-weight:800;color:var(--checkout-accent);font-size:13px;text-transform:uppercase;letter-spacing:.07em}.product-name{font-size:22px;font-weight:800;margin:4px 0}.plan-name{font-size:15px;color:var(--checkout-muted);font-weight:600}.price-summary{min-width:178px;text-align:right}.price-summary strong{display:block;font-size:21px;color:var(--checkout-text)}.price-summary span{display:block;font-size:12px;color:var(--checkout-muted)}.description{margin:14px 0 17px;padding:13px 15px;border:1px solid var(--checkout-border);border-radius:11px;background:var(--checkout-panel);white-space:pre-wrap;overflow-wrap:anywhere}
.details-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.detail{min-width:0;padding:11px 12px;background:var(--checkout-panel);border:1px solid var(--checkout-border);border-radius:11px}.detail-label{display:block;font-size:11px;line-height:1.35;color:var(--checkout-muted);font-weight:700;text-transform:uppercase;letter-spacing:.045em}.detail-value{display:block;margin-top:5px;color:var(--checkout-text);font-weight:750;overflow-wrap:anywhere}.price-breakdown{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--checkout-border)}.price-row{display:flex;gap:8px;justify-content:space-between;min-width:170px;flex:1}.price-row span{color:var(--checkout-muted)}.price-row strong{color:var(--checkout-text)}.price-total{flex-basis:100%;font-size:17px}
.field{display:grid;gap:6px;margin:13px 0}.field label{font-size:13px;font-weight:750;color:var(--checkout-text)}.field input,.field select{width:100%;padding:12px;border:1px solid var(--checkout-border);border-radius:10px;background:var(--checkout-input);color:var(--checkout-text);font:inherit}.field option{background:var(--checkout-card);color:var(--checkout-text)}.field input:focus,.field select:focus{outline:2px solid color-mix(in srgb,var(--checkout-accent) 35%,transparent);outline-offset:1px}
.method{border:1px solid var(--checkout-border);background:var(--checkout-panel);color:var(--checkout-text);border-radius:12px;padding:14px;margin:10px 0}.method strong{display:block;color:var(--checkout-text)}.method pre{white-space:pre-wrap;overflow-wrap:anywhere;font-family:inherit;font-size:13px;line-height:1.5;color:var(--checkout-text);margin:8px 0}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:10px;background:var(--checkout-accent);color:#fff;padding:12px 17px;font-weight:800;cursor:pointer;text-decoration:none}.btn:disabled{opacity:.55;cursor:not-allowed}.alert{padding:13px 14px;border:1px solid var(--checkout-border);border-radius:11px;background:var(--checkout-warn-bg);color:var(--checkout-warn-text);margin:12px 0}.alert.error{background:var(--checkout-error-bg);color:var(--checkout-error-text)}.alert.success{background:var(--checkout-success-bg);color:var(--checkout-success-text)}.status{padding:14px;border:1px solid var(--checkout-border);background:var(--checkout-panel);color:var(--checkout-text);border-radius:11px;margin-top:15px}.status strong{display:block;color:var(--checkout-text);margin-bottom:3px}.section-note{color:var(--checkout-muted);font-size:13px;margin:0 0 10px}
@media(max-width:760px){.details-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.card{padding:17px}.product-head{display:block}.price-summary{text-align:left;margin-top:12px}.price-summary strong{font-size:19px}}
@media(max-width:430px){body{padding:78px 10px 24px}.checkout-header{height:60px;padding:0 10px;grid-template-columns:46px minmax(0,1fr) 46px}.checkout-title{font-size:15px}.details-grid{grid-template-columns:1fr 1fr;gap:7px}.detail{padding:9px}.detail-value{font-size:13px}.card{border-radius:14px;padding:14px}.product-name{font-size:19px}}
</style>
</head>
<body class="<?= $checkoutDarkMode ? 'dark-mode' : '' ?>">
<header class="checkout-header">
    <a class="checkout-back" href="<?= vpsCheckoutH($shellReturnUrl) ?>" target="_top" data-harvhub-shell-managed="1" onclick="return checkoutNavigateBack(event)" aria-label="Back to <?= $type==='programme'?'Trader app VPS':'Investor app VPS' ?>" title="Back to VPS workspace">&#8592;</a>
    <h1 class="checkout-title">Vps Purchase Checkout</h1>
    <span aria-hidden="true"></span>
</header>
<main class="wrap">
<?php if ($request): ?>
<section class="card">
    <div class="muted">Purchase request #<?= (int)$requestId ?></div>
    <div class="purchase-target"><span class="target-chip"><?= $type==='programme'?'Programme VPS':'Account VPS' ?></span><span>Purchase target: <strong><?= vpsCheckoutH($targetLabel) ?></strong></span></div>
</section>
<section class="card">
    <div class="product-head">
        <div>
            <span class="provider"><?= vpsCheckoutH(vpsCheckoutValue($request['provider_name'] ?? '', 'HarvHub VPS')) ?></span>
            <div class="product-name"><?= vpsCheckoutH(vpsCheckoutValue($request['product_name'] ?? '', 'VPS Plan')) ?></div>
            <div class="plan-name"><?= vpsCheckoutH(vpsCheckoutValue($request['plan_name'] ?? '', 'Plan')) ?></div>
        </div>
        <div class="price-summary"><strong><?= vpsCheckoutMoney($request['amount'] ?? 0,$request['currency'] ?? 'USD') ?></strong><span>Total amount due</span></div>
    </div>
    <?php if (trim((string)($request['plan_description'] ?? '')) !== ''): ?><div class="description"><?= nl2br(vpsCheckoutH($request['plan_description'])) ?></div><?php endif; ?>
    <h2>Complete VPS advertisement details</h2>
    <div class="details-grid">
        <?php
        $details = [
            ['Processor',vpsCheckoutValue($request['cpu_type'] ?? '', 'Processor not specified')],
            ['vCPU',((int)($request['vcpu_count'] ?? 0)).' vCPU'],
            ['RAM',number_format((float)($request['ram_gb'] ?? 0),2).' GB'],
            ['Storage',number_format((float)($request['storage_gb'] ?? 0),2).' GB'],
            ['Storage type',vpsCheckoutValue($request['storage_type'] ?? '')],
            ['Network port speed',($request['network_port_speed_mbps'] ?? null) !== null ? ((int)$request['network_port_speed_mbps']).' Mbps' : 'Not specified'],
            ['Data transfer',vpsCheckoutValue($request['data_transfer'] ?? '')],
            ['Server location / Proxy',vpsCheckoutValue($request['server_location'] ?? '')],
            ['Region',vpsCheckoutValue($request['region_code'] ?? '')],
            ['Operating system',vpsCheckoutValue($request['operating_system'] ?? '')],
            ['Virtualization',vpsCheckoutValue($request['virtualization_type'] ?? '')],
            ['IPv4 address included',(int)($request['ipv4_included'] ?? 0) === 1 ? 'Yes' : 'No'],
            ['IPv6 address included',(int)($request['ipv6_included'] ?? 0) === 1 ? 'Yes' : 'No'],
            ['Backup option',vpsCheckoutValue($request['backup_option'] ?? '')],
            ['Snapshots',(int)($request['snapshot_count'] ?? 0)],
            ['Billing period',ucfirst(vpsCheckoutValue($request['billing_period'] ?? 'monthly'))],
            ['Subscription duration',(int)($request['subscription_duration_days'] ?? 0).' days'],
            ['Available quantity',(int)($request['available_units'] ?? 0)],
        ];
        foreach ($details as $detail): ?>
            <div class="detail"><span class="detail-label"><?= vpsCheckoutH($detail[0]) ?></span><span class="detail-value"><?= vpsCheckoutH($detail[1]) ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="price-breakdown">
        <div class="price-row"><span>VPS plan price</span><strong><?= vpsCheckoutMoney($request['plan_price'] ?? 0,$request['plan_currency'] ?? $request['currency'] ?? 'USD') ?></strong></div>
        <div class="price-row"><span>Setup fee</span><strong><?= vpsCheckoutMoney($request['setup_fee'] ?? 0,$request['plan_currency'] ?? $request['currency'] ?? 'USD') ?></strong></div>
        <div class="price-row price-total"><span>Purchase total</span><strong><?= vpsCheckoutMoney($request['amount'] ?? 0,$request['currency'] ?? 'USD') ?></strong></div>
    </div>
</section>
<section class="card">
    <h2>Purchase progress</h2>
    <?php if ($paymentStatus === 'payment-confirmed'): ?>
        <?php if ($requestRegistrationStatus === 'registered'): ?>
            <div class="alert success">Payment confirmed. Your VPS is ready.</div>
            <a class="btn" href="<?= vpsCheckoutH($hubMyVpsUrl) ?>" data-harvhub-shell-managed="1" onclick="return checkoutNavigateToHub(this.href,event)">View My VPS</a>
        <?php else: ?>
            <div class="alert">Payment confirmed. Your VPS purchase is being processed.</div>
            <a class="btn" href="<?= vpsCheckoutH($hubPendingUrl) ?>" data-harvhub-shell-managed="1" onclick="return checkoutNavigateToHub(this.href,event)">Return to Purchase Pending</a>
        <?php endif; ?>
    <?php elseif ($paymentStatus === 'payment-made'): ?>
        <div class="status"><strong>Payment submitted · Awaiting verification</strong><span class="muted">This purchase cannot be cancelled after payment submission.</span></div>
    <?php elseif ($paymentStatus === 'payment-failed'): ?>
        <div class="alert error">This payment was marked as failed. Please contact support.</div>
    <?php elseif ($paymentStatus === 'awaiting_payment'): ?>
        <?php if ($paymentError !== ''): ?><div class="alert error"><?= vpsCheckoutH($paymentError) ?></div><?php endif; ?>
        <p class="section-note">Choose a payment method, follow its instructions, then submit your transaction reference.</p>
        <?php if (!$methods): ?>
            <div class="alert">No active payment methods are configured at the moment.</div>
        <?php else: ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="submit_payment" value="1">
                <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
                <input type="hidden" name="csrf_token" value="<?= vpsCheckoutH($purchaseCsrf) ?>">
                <input type="hidden" name="return_to" value="<?= vpsCheckoutH($returnType) ?>">
                <div class="field"><label for="paymentMethod">Payment method</label><select id="paymentMethod" name="payment_method_id" required onchange="showPaymentMethod(this.value)"><option value="">Choose payment method</option><?php foreach ($methods as $method): ?><option value="<?= (int)$method['id'] ?>"><?= vpsCheckoutH($method['method_name']) ?><?= trim((string)$method['company_name'])!==''?' · '.vpsCheckoutH($method['company_name']):'' ?></option><?php endforeach; ?></select></div>
                <?php foreach ($methods as $method): ?>
                    <div class="method" data-method="<?= (int)$method['id'] ?>" hidden><strong><?= vpsCheckoutH($method['method_name']) ?><?= trim((string)$method['company_name'])!==''?' · '.vpsCheckoutH($method['company_name']):'' ?></strong><pre><?= vpsCheckoutH($method['account_or_address'] ?? '') ?></pre><?php if (trim((string)($method['additional_info'] ?? ''))!==''): ?><div class="muted"><?= nl2br(vpsCheckoutH($method['additional_info'])) ?></div><?php endif; ?></div>
                <?php endforeach; ?>
                <div class="field"><label for="paymentReference">Transaction reference / hash</label><input id="paymentReference" name="payment_reference" maxlength="190" required placeholder="Enter your transaction reference"></div>
                <button class="btn" type="submit">I have made payment</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert error">This purchase request is no longer available.</div>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="card"><h2>Purchase not found</h2><p class="muted">This purchase request could not be found for the selected <?= $type==='programme'?'programme':'account' ?>.</p><a class="btn" href="<?= vpsCheckoutH($hubPurchaseUrl) ?>" data-harvhub-shell-managed="1" onclick="return checkoutNavigateToHub(this.href,event)">Back to VPS</a></section>
<?php endif; ?>
</main>
<script>
(function(){
    window.addEventListener('message',function(event){var data=event&&event.data;if(!data||typeof data!=='object'||data.type!=='theme')return;document.body.classList.toggle('dark-mode',!!data.dark);});
})();
function checkoutNavigateToHub(destination, event){
    if(event){ event.preventDefault(); event.stopPropagation(); }
    var target = String(destination || <?= json_encode($hubPurchaseUrl) ?>);
    try {
        var parsed = new URL(target, window.location.href);
        if (parsed.origin !== window.location.origin) throw new Error('Invalid VPS hub destination.');
        if (window.parent && window.parent !== window) {
            // Preferred path: invoke the app shell's verified direct bridge.
            // This switches back to the already-loaded VPS iframe without reloading the app.
            try {
                if (typeof window.parent.harvhubNavigateFromVpsCheckout === 'function' &&
                    window.parent.harvhubNavigateFromVpsCheckout(<?= json_encode($type) ?>, parsed.href, window) === true) {
                    return false;
                }
            } catch (bridgeError) {}
            // Compatibility path for shells that have not yet deployed the direct bridge.
            try {
                window.parent.postMessage({type:'harvhubVpsNavigateHub',category:<?= json_encode($type) ?>,url:parsed.href}, window.location.origin);
                return false;
            } catch (messageError) {}
            // Last-resort fallback stays inside this iframe rather than navigating the app shell.
            window.location.href = parsed.href;
            return false;
        }
        window.location.href = parsed.href;
    } catch (error) {
        if (window.parent && window.parent !== window) {
            try {
                if (typeof window.parent.harvhubNavigateFromVpsCheckout === 'function') {
                    window.parent.harvhubNavigateFromVpsCheckout(<?= json_encode($type) ?>, <?= json_encode($hubPurchaseUrl) ?>, window);
                    return false;
                }
            } catch (fallbackError) {}
        }
        window.location.href = <?= json_encode($hubPurchaseUrl) ?>;
    }
    return false;
}
function checkoutNavigateBack(event){
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var appShellFile = <?= json_encode($appShellFile) ?>;
    var allowedSourceTabs = <?= json_encode($sourceReturnTabs) ?>;
    var returnStorageKey = <?= json_encode($returnStorageKey) ?>;
    var fallbackUrl = <?= json_encode($shellReturnUrl) ?>;

    // The checkout runs in a persistent iframe inside the Investor/Trader app.
    // Read the real parent URL, restore tab=vps, and preserve its vps_from value.
    try {
        if (window.parent && window.parent !== window) {
            var appUrl = new URL(window.parent.location.href);
            var appFile = appUrl.pathname.split('/').pop();

            if (appUrl.origin === window.location.origin && appFile === appShellFile) {
                var sourceTab = appUrl.searchParams.get('vps_from') || '';

                if (allowedSourceTabs.indexOf(sourceTab) === -1) {
                    try {
                        sourceTab = window.parent.sessionStorage.getItem(returnStorageKey)
                            || window.sessionStorage.getItem(returnStorageKey)
                            || '';
                    } catch (storageError) {}
                }
                if (allowedSourceTabs.indexOf(sourceTab) === -1) sourceTab = 'menu';

                appUrl.searchParams.set('tab', 'vps');
                appUrl.searchParams.set('vps_from', sourceTab);
                appUrl.searchParams.delete('request_id');
                <?php if ($type === 'account'): ?>
                appUrl.searchParams.set('switched', '1');
                <?php endif; ?>

                // Navigate the actual app shell, not the checkout iframe or a
                // standalone VPS hub URL. This also works if the shell bridge
                // is unavailable or the iframe reference has changed.
                window.parent.location.replace(appUrl.href);
                return false;
            }
        }
    } catch (parentNavigationError) {}

    // Standalone checkout fallback (not opened from an app iframe).
    window.location.replace(fallbackUrl);
    return false;
}
function showPaymentMethod(id){document.querySelectorAll('[data-method]').forEach(function(el){el.hidden=(el.dataset.method!==String(id));});}
<?php if ($request && in_array($paymentStatus,['payment-made','payment-confirmed'],true)): ?>
const statusUrl = <?= json_encode($checkoutPageUrl.'?action=status&request_id='.$requestId.'&return_to='.$returnType) ?>;
async function checkPaymentStatus(){try{const response=await fetch(statusUrl,{cache:'no-store',credentials:'same-origin'});const data=await response.json();if(data.success&&data.payment_status==='payment-confirmed'&&data.hub_url&&<?= json_encode($paymentStatus==='payment-made') ?>){checkoutNavigateToHub(data.hub_url);}}catch(e){}}
<?php if ($paymentStatus==='payment-made'): ?>
checkPaymentStatus();setInterval(checkPaymentStatus,10000);
<?php endif; ?>
<?php endif; ?>
</script>
</body>
</html>
