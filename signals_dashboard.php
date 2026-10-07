<?php
// signals_dashboard.php — programme-specific trader signal dashboard
session_start();
require_once 'usersdb.php';

try { $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
catch (Exception $e) { die("Database connection failed."); }

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { unset($_SESSION['user_email'], $_SESSION['auth_role']); header("Location: index.php?role=developer"); exit; }

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = !empty($user['dark_mode']);

// -------------------- PROGRAMME SELECTION --------------------
$selectedProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($selectedProgrammeId <= 0) {
    $q = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
    $q->execute([$userId]);
    $selectedProgrammeId = (int)($q->fetchColumn() ?: 0);
    if ($selectedProgrammeId > 0) $_SESSION['selected_programme_id'] = $selectedProgrammeId;
}

$programme = null;
if ($selectedProgrammeId > 0) {
    $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $q->execute([$selectedProgrammeId, $userId]);
    $programme = $q->fetch(PDO::FETCH_ASSOC);
}

function esc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function jres($a){ header('Content-Type: application/json'); echo json_encode($a); exit; }

function parseCleanList($raw) {
    if ($raw === null || $raw === '') return [];
    $raw = (string)$raw;
    $trimmed = trim($raw);
    if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $flat = [];
            array_walk_recursive($decoded, function($v) use (&$flat) { $flat[] = $v; });
            $decoded = $flat;
        } else { $decoded = null; }
    } else { $decoded = null; }
    if (!is_array($decoded)) {
        $raw = str_replace(['[', ']', '{', '}', '"', "'"], '', $raw);
        $decoded = explode(',', $raw);
    }
    $out = [];
    foreach ($decoded as $item) {
        $item = trim((string)$item);
        $item = str_replace(['[', ']', '{', '}', '"', "'"], '', $item);
        $item = trim($item);
        if ($item === '') continue;
        $out[] = $item;
    }
    return array_values(array_unique($out));
}

// -------------------- HELPER: SYNC INVESTOR PROFIT INTO DEALS --------------------
// For every deal that is still within its contract window, pull the investor's
// current profitandloss from harvhub and write it into programme_revenue_deals.
// Once the deal's contract window has ended, stop updating (frozen snapshot).
function syncInvestorProfitsIntoDeals($pdo) {
    try {
        $q = $pdo->query("
            SELECT d.contract_id, d.investor_id, d.investor_sub_account_id, d.accepted_at, d.contract_duration,
                   d.investor_profit, h.profitandloss
            FROM programme_revenue_deals d
            LEFT JOIN harvhub h ON h.id = d.investor_id
            WHERE d.investor_id > 0
              AND d.contract_duration > 0
        ");
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    foreach ($rows as $r) {
        if (empty($r['accepted_at']) || (int)$r['contract_duration'] <= 0) continue;
        try {
            $start = new DateTime($r['accepted_at']);
        } catch (Exception $e) {
            continue;
        }
        $end = clone $start;
        $end->modify('+' . (int)$r['contract_duration'] . ' days');
        $endClone = clone $end;
        $endClone->setTime(0, 0, 0);
        $diff = (int)$today->diff($endClone)->format('%r%a');
        if ($diff <= 0) continue; // contract ended — do not overwrite

        $pnl = (float)($r['profitandloss'] ?? 0);
        try {
            $u = $pdo->prepare("UPDATE programme_revenue_deals SET investor_profit = ? WHERE contract_id = ?");
            $u->execute([$pnl, (int)$r['contract_id']]);
        } catch (Throwable $e) {}
    }
}

// -------------------- HELPER: DEAL STATE --------------------
// Classify a deal as "active" (contract running) or "inactive" (contract
// has ended but still recorded in the deals table).
function classifyDealState($acceptedAt, $contractDuration) {
    if (empty($acceptedAt) || (int)$contractDuration <= 0) {
        return ['state' => 'inactive', 'end' => null, 'diff' => null, 'start' => null];
    }
    try {
        $start = new DateTime($acceptedAt);
    } catch (Exception $e) {
        return ['state' => 'inactive', 'end' => null, 'diff' => null, 'start' => null];
    }
    $end = clone $start;
    $end->modify('+' . (int)$contractDuration . ' days');

    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $endClone = clone $end;
    $endClone->setTime(0, 0, 0);

    $diff = (int)$today->diff($endClone)->format('%r%a');
    $state = ($diff > 0) ? 'active' : 'inactive';

    return ['state' => $state, 'end' => $end, 'diff' => $diff, 'start' => $start];
}

// -------------------- HELPER: BUILD LIVE STATE --------------------
function buildLiveState($pdo, $userId, $programme, $email) {
    $state = [
        'success' => true,
        'programme_id' => 0,
        'programme_name' => '',
        'has_vps' => false,
        'has_broker' => false,
        'published' => false,
        'is_public' => false,
        'broker' => '',
        'broker_server' => '',
        'broker_login' => '',
        'revenue_amount' => '0.00',
        'investor_count' => 0,
        'developer_percentage' => '0.00',
        'incoming_request_count' => 0,
        'global_incoming_request_count' => 0,
        'contract_duration' => 0,
        'min_investment' => '0.00',
        'max_investment' => '0.00',
        'server_contract_duration' => 0,
        'server_min_broker_balance' => '0.00',
        'symbols' => [],
        'timeframes' => [],
        'trades' => [],
        'trades_html' => '',
        'programme_status_html' => '',
        'greeting_sub_html' => '',
        'greeting_banners_html' => '',
    ];

    // ----- Global incoming request count across ALL programmes owned by user -----
    $globalIncoming = 0;
    try {
        $g = $pdo->prepare("
            SELECT COUNT(*) FROM programme_investment_requestors r
            INNER JOIN programme p ON p.id = r.programme_id
            WHERE p.userid = ? AND r.request_status = 'pending'
        ");
        $g->execute([$userId]);
        $globalIncoming = (int)$g->fetchColumn();
    } catch (Throwable $e) {}
    $state['global_incoming_request_count'] = $globalIncoming;

    if (!$programme) return $state;

    $programmeId = (int)$programme['id'];
    $state['programme_id'] = $programmeId;
    $state['programme_name'] = (string)($programme['program_name'] ?? '');
    $state['is_public'] = ((int)($programme['visibility'] ?? 0) === 1);
    $state['published'] = ((int)($programme['advertisement'] ?? 0) === 1);

    // ----- VPS -----
    $hasVps = false;
    try {
        $s = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
        $s->execute([$programmeId]);
        $hasVps = (bool)$s->fetchColumn();
    } catch (Throwable $e) {}
    if (!$hasVps) {
        try {
            $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
            $s->execute([$programmeId]);
            $hasVps = (bool)$s->fetchColumn();
        } catch (Throwable $e) {}
    }
    $state['has_vps'] = $hasVps;

    // ----- Broker -----
    $hasBroker = (!empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']));
    $state['has_broker'] = $hasBroker;
    $state['broker'] = (string)($programme['broker'] ?? '');
    $state['broker_server'] = (string)($programme['server'] ?? '');
    $state['broker_login'] = (string)($programme['login'] ?? '');

    // ----- Revenue settings -----
    $revenueSettings = null;
    try {
        $s = $pdo->prepare("SELECT * FROM programme_investors WHERE developerid = ? AND programme_id = ? AND investorid = 0 ORDER BY id DESC LIMIT 1");
        $s->execute([$userId, $programmeId]);
        $revenueSettings = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}

    $developerPercentage = 0.0;
    $contractDuration = 0;
    $minInvestment = 0.0;
    $maxInvestment = 0.0;

    if ($revenueSettings) {
        if ($revenueSettings['developer_percentage'] !== null) $developerPercentage = (float)$revenueSettings['developer_percentage'];
        $contractDuration = isset($revenueSettings['contract_duration']) ? (int)$revenueSettings['contract_duration'] : 0;
        $minInvestment    = isset($revenueSettings['minimum_investment_amount']) ? (float)$revenueSettings['minimum_investment_amount'] : 0.0;
        $maxInvestment    = isset($revenueSettings['maximum_investment_amount']) ? (float)$revenueSettings['maximum_investment_amount'] : 0.0;
    }

    // ----- Server floors -----
    $serverContractDuration = 0;
    $serverMinBrokerBalance = 0.0;
    try {
        $srv = $pdo->query("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($srv) {
            $serverContractDuration = (int)($srv['contract_duration'] ?? 0);
            $serverMinBrokerBalance = (float)($srv['min_broker_balance'] ?? 0);
        }
    } catch (Throwable $e) {}
    if ($contractDuration < $serverContractDuration) $contractDuration = $serverContractDuration;
    if ($minInvestment < $serverMinBrokerBalance) $minInvestment = $serverMinBrokerBalance;

    $state['developer_percentage'] = number_format($developerPercentage, 2, '.', '');
    $state['contract_duration'] = $contractDuration;
    $state['min_investment'] = number_format($minInvestment, 2, '.', '');
    $state['max_investment'] = number_format($maxInvestment, 2, '.', '');
    $state['server_contract_duration'] = $serverContractDuration;
    $state['server_min_broker_balance'] = number_format($serverMinBrokerBalance, 2, '.', '');

    // ----- Active investor count (matches Active Investors tab: contract running) -----
    $investorCount = 0;
    try {
        $q = $pdo->prepare("
            SELECT accepted_at, contract_duration
            FROM programme_revenue_deals
            WHERE programme_id = ? AND investor_id > 0
        ");
        $q->execute([$programmeId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $c = classifyDealState($r['accepted_at'] ?? null, (int)($r['contract_duration'] ?? 0));
            if ($c['state'] === 'active') $investorCount++;
        }
    } catch (Throwable $e) {}
    $state['investor_count'] = $investorCount;

    // ----- Revenue amount -----
    $revenueAmount = 0.0;
    try {
        $s = $pdo->prepare("SELECT h.profitandloss FROM programme_investors pi INNER JOIN harvhub h ON h.id = pi.investorid WHERE pi.developerid = ? AND pi.programme_id = ? AND pi.investorid > 0");
        $s->execute([$userId, $programmeId]);
        while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            $pnl = (float)($r['profitandloss'] ?? 0);
            if ($pnl > 0) $revenueAmount += ($pnl * $developerPercentage / 100);
        }
    } catch (Throwable $e) {}
    $state['revenue_amount'] = number_format($revenueAmount, 2, '.', '');

    // ----- Incoming request count (this programme) -----
    $incomingRequestCount = 0;
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM programme_investment_requestors WHERE developerid = ? AND programme_id = ? AND request_status = 'pending'");
        $s->execute([$userId, $programmeId]);
        $incomingRequestCount = (int)$s->fetchColumn();
    } catch (Throwable $e) {}
    $state['incoming_request_count'] = $incomingRequestCount;

    // ----- Market settings -----
    $symbols = parseCleanList((string)($programme['selected_symbols'] ?? ''));
    $timeframes = parseCleanList((string)($programme['selected_timeframes'] ?? ''));
    if (empty($symbols)) $symbols = parseCleanList((string)($programme['broker_symbols'] ?? ''));
    if (empty($timeframes)) $timeframes = parseCleanList((string)($programme['broker_timeframes'] ?? ''));
    $state['symbols'] = $symbols;
    $state['timeframes'] = $timeframes;

    // ----- Greeting sub text -----
    if (!$hasVps) {
        $state['greeting_sub_html'] = "Your programme won't generate revenue while you have no VPS.";
    } elseif (!$hasBroker) {
        $state['greeting_sub_html'] = "Your programme won't generate revenue — no broker details to analyze your programme training.";
    } elseif ($state['published']) {
        $state['greeting_sub_html'] = "Your programme is advertised and generating signals.";
    } else {
        $state['greeting_sub_html'] = "Programme is set up. Publish when you're ready.";
    }

    // ----- Greeting banners HTML -----
    $bannersHtml = '';
    if (!$hasVps) {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab(\'vps\')">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-server"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Get VPS</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">Required to analyse your programme</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
    } elseif (!$hasBroker) {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab(\'connect_trader_broker\')">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-plug"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Connect Broker</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">Attach broker to this programme</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
    } else {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab(\'vps\')">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-server"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Your VPS</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">Your MT5 terminal broker details are logged in to this VPS</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab(\'connect_trader_broker\')">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-plug"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Your broker</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">' . esc_h(($programme['broker'] ?? '') . ' · ' . ($programme['login'] ?? '')) . '</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
    }

    // Investment requests banner uses GLOBAL count; opens the picker modal
    if ($globalIncoming > 0) {
        $bannersHtml .= '<button type="button" class="sd-incoming-banner" onclick="sdOpenRequestPicker()">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Investment requests</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right">';
        $bannersHtml .=     '<span class="sd-incoming-count" id="sdIncomingReqCount">' . (int)$globalIncoming . '</span>';
        $bannersHtml .=     '<i class="fa-solid fa-chevron-right sd-incoming-chev"></i>';
        $bannersHtml .=   '</span>';
        $bannersHtml .= '</button>';
    }
    $state['greeting_banners_html'] = $bannersHtml;

    // ----- Programme status HTML -----
    $statusHtml = '';
    $statusHtml .= '<div class="sd-status-grid">';

    $statusHtml .= '<div class="sd-status-tile">';
    $statusHtml .=   '<div class="sd-status-tile-label"><i class="fa-solid fa-coins"></i> Symbols</div>';
    $statusHtml .=   '<div class="sd-status-tile-value">';
    if (!empty($symbols)) {
        foreach ($symbols as $sym) {
            $statusHtml .= '<span class="sd-chip">' . esc_h($sym) . '</span>';
        }
    } else {
        $statusHtml .= 'No symbols selected';
    }
    $statusHtml .=   '</div>';
    $statusHtml .= '</div>';

    $statusHtml .= '<div class="sd-status-tile">';
    $statusHtml .=   '<div class="sd-status-tile-label"><i class="fa-solid fa-building-columns"></i> Broker</div>';
    $statusHtml .=   '<div class="sd-status-tile-value">' . ($hasBroker ? esc_h($programme['broker']) : '—') . '</div>';
    $statusHtml .= '</div>';

    $statusHtml .= '<div class="sd-status-tile">';
    $statusHtml .=   '<div class="sd-status-tile-label"><i class="fa-solid fa-clock"></i> Timeframes</div>';
    $statusHtml .=   '<div class="sd-status-tile-value">';
    if (!empty($timeframes)) {
        foreach ($timeframes as $tf) {
            $statusHtml .= '<span class="sd-chip">' . esc_h($tf) . '</span>';
        }
    } else {
        $statusHtml .= 'No timeframes selected';
    }
    $statusHtml .=   '</div>';
    $statusHtml .= '</div>';

    $statusHtml .= '</div>';
    $state['programme_status_html'] = $statusHtml;

    // ----- Trades -----
    $trades = [];
    try {
        $s = $pdo->prepare("SELECT * FROM programme_trades WHERE userid = ? AND programmeid = ? ORDER BY created_at DESC LIMIT 200");
        $s->execute([$userId, $programmeId]);
        $trades = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    $state['trades'] = $trades;

    // ----- Trades HTML -----
    $tradesHtml = '';
    if (!$trades) {
        $tradesHtml .= '<div class="sd-empty"><i class="fa-solid fa-chart-simple"></i><strong>No trades recorded yet</strong><p>Trades will appear here once they are generated.</p></div>';
    } else {
        $tradesHtml .= '<div class="sd-trades-list">';
        foreach ($trades as $t) {
            $modifyVal = (string)($t['modify_trade'] ?? 'keep-running');
            if (!in_array($modifyVal, ['keep-running','breakeven','close'], true)) $modifyVal = 'keep-running';
            $tradesHtml .= '<div class="sd-trade-row" data-trade-id="' . (int)$t['id'] . '">';
            $tradesHtml .=   '<div><div class="sd-t-label">Symbol</div><b>' . esc_h((string)$t['symbol']) . ' · ' . esc_h($t['timeframe']) . '</b></div>';
            $tradesHtml .=   '<div><div class="sd-t-label">Entry</div><b>' . esc_h(rtrim(rtrim(number_format((float)$t['entry'], 5), '0'), '.')) . '</b></div>';
            $tradesHtml .=   '<div><div class="sd-t-label">SL / TP</div><b>' . esc_h(rtrim(rtrim(number_format((float)$t['exit'], 5), '0'), '.')) . ' / ' . ($t['target'] !== null ? esc_h(rtrim(rtrim(number_format((float)$t['target'], 5), '0'), '.')) : '—') . '</b></div>';
            $tradesHtml .=   '<div><div class="sd-t-label">R:R</div><b>1:' . esc_h(number_format((float)$t['risk_reward'], 2)) . '</b></div>';
            $tradesHtml .=   '<div>';
            $tradesHtml .=     '<div class="sd-t-label">Modify trade</div>';
            $tradesHtml .=     '<select class="sd-modify-select" data-trade-id="' . (int)$t['id'] . '" data-mode="' . esc_h($modifyVal) . '">';
            $tradesHtml .=       '<option value="keep-running" ' . ($modifyVal === 'keep-running' ? 'selected' : '') . '>Keep running</option>';
            $tradesHtml .=       '<option value="breakeven" ' . ($modifyVal === 'breakeven' ? 'selected' : '') . '>Breakeven</option>';
            $tradesHtml .=       '<option value="close" ' . ($modifyVal === 'close' ? 'selected' : '') . '>Close</option>';
            $tradesHtml .=     '</select>';
            $tradesHtml .=   '</div>';
            $tradesHtml .= '</div>';
        }
        $tradesHtml .= '</div>';
    }
    $state['trades_html'] = $tradesHtml;

    return $state;
}

// -------------------- HELPER: DEALS FOR PROGRAMME --------------------
// Returns two arrays: active deals (contract running) and inactive deals
// (contract has ended). Both are derived strictly from programme_revenue_deals.
function getDealsForProgramme($pdo, $userId, $programmeId) {
    $active = [];
    $inactive = [];
    try {
        $q = $pdo->prepare("
            SELECT d.contract_id, d.investor_id, d.investor_sub_account_id,
                   d.accepted_at, d.contract_duration,
                   d.developer_percentage, d.investor_percentage,
                   d.investor_profit, d.investor_payment_status, d.server_payment_status,
                   d.programme_name,
                   h.username, h.first_name, h.last_name, h.fullname, h.email AS investor_email,
                   h.profitandloss
            FROM programme_revenue_deals d
            LEFT JOIN harvhub h ON h.id = d.investor_id
            WHERE d.programme_id = ? AND d.investor_id > 0
            ORDER BY d.accepted_at DESC, d.contract_id DESC
        ");
        $q->execute([$programmeId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['active' => [], 'inactive' => []];
    }

    foreach ($rows as $r) {
        $c = classifyDealState($r['accepted_at'] ?? null, (int)($r['contract_duration'] ?? 0));
        if (!$c['start'] || !$c['end']) continue;

        $name = trim((string)($r['username'] ?? '')) ?: (trim((string)($r['first_name'] ?? '')) ?: (trim((string)($r['fullname'] ?? '')) ?: (string)($r['investor_email'] ?? ('Investor #' . (int)$r['investor_id']))));
        $initial = strtoupper(substr($name !== '' ? $name : 'I', 0, 1));

        $pnl = (float)($r['profitandloss'] ?? 0);
        $devPct = (float)($r['developer_percentage'] ?? 0);
        $traderRevenue = $pnl > 0 ? ($pnl * $devPct / 100) : 0;

        $entry = [
            'contract_id'             => (int)$r['contract_id'],
            'investor_id'             => (int)$r['investor_id'],
            'name'                    => $name,
            'initial'                 => $initial,
            'trader_revenue'          => $traderRevenue,
            'investor_profit'         => (float)($r['investor_profit'] ?? 0),
            'your_split_pct'          => $devPct,
            'investor_pct'            => (float)($r['investor_percentage'] ?? 0),
            'start_date'              => $c['start']->format('Y-m-d'),
            'end_date'                => $c['end']->format('Y-m-d'),
            'days_left'               => $c['diff'],
            'contract_duration'       => (int)$r['contract_duration'],
            'accepted_at'             => (string)$r['accepted_at'],
            'programme_name'          => (string)($r['programme_name'] ?? ''),
            'investor_payment_status' => (string)($r['investor_payment_status'] ?? ''),
            'server_payment_status'   => (string)($r['server_payment_status'] ?? ''),
        ];

        if ($c['state'] === 'active') $active[] = $entry;
        else                          $inactive[] = $entry;
    }

    return ['active' => $active, 'inactive' => $inactive];
}

// -------------------- HELPER: CLOSED DEALS grouped by contract window --------------------
function getClosedDealsForProgramme($pdo, $userId, $programmeId) {
    $deals = getDealsForProgramme($pdo, $userId, $programmeId);
    $inactive = $deals['inactive'];

    // Group by "start – end" contract date string
    $grouped = [];
    foreach ($inactive as $it) {
        try {
            $start = new DateTime($it['start_date']);
            $end   = new DateTime($it['end_date']);
        } catch (Exception $e) { continue; }
        $key = $start->format('M d, Y') . ' – ' . $end->format('M d, Y');
        if (!isset($grouped[$key])) $grouped[$key] = [];
        $grouped[$key][] = [
            'contract_id'             => (int)$it['contract_id'],
            'name'                    => (string)$it['name'],
            'initial'                 => (string)$it['initial'],
            'investor_profit'         => (float)$it['investor_profit'],
            'investor_payment_status' => (string)$it['investor_payment_status'],
            'server_payment_status'   => (string)$it['server_payment_status'],
        ];
    }

    $out = [];
    foreach ($grouped as $range => $items) {
        $out[] = ['range' => $range, 'items' => $items];
    }
    return $out;
}

// -------------------- AJAX --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // -------- LIVE STATE POLL --------
    if ($action === 'get_live_state') {
        try {
            syncInvestorProfitsIntoDeals($pdo);
            jres(buildLiveState($pdo, $userId, $programme, $email));
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load live state: ' . $e->getMessage()]);
        }
    }

    // -------- GLOBAL REQUEST PROGRAMMES (for picker modal) --------
    if ($action === 'get_request_programmes') {
        try {
            $q = $pdo->prepare("
                SELECT p.id, p.program_name,
                       COUNT(r.id) AS pending_count
                FROM programme p
                INNER JOIN programme_investment_requestors r ON r.programme_id = p.id
                WHERE p.userid = ? AND r.request_status = 'pending'
                GROUP BY p.id, p.program_name
                ORDER BY p.id DESC
            ");
            $q->execute([$userId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);

            $currentId = $programme ? (int)$programme['id'] : 0;
            $list = [];
            $currentRow = null;
            foreach ($rows as $r) {
                $item = [
                    'programme_id'   => (int)$r['id'],
                    'programme_name' => (string)($r['program_name'] ?? ''),
                    'pending_count'  => (int)$r['pending_count'],
                    'is_current'     => ((int)$r['id'] === $currentId),
                ];
                if ($item['is_current']) $currentRow = $item;
                else $list[] = $item;
            }
            // Current programme first
            $final = [];
            if ($currentRow) $final[] = $currentRow;
            foreach ($list as $item) $final[] = $item;

            jres(['success' => true, 'programmes' => $final]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load request programmes.']);
        }
    }

    // -------- SWITCH PROGRAMME (used by picker modal for other programmes) --------
    if ($action === 'switch_programme') {
        $targetId = (int)($_POST['programme_id'] ?? 0);
        if ($targetId <= 0) jres(['success' => false, 'message' => 'Invalid programme.']);
        try {
            $q = $pdo->prepare("SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1");
            $q->execute([$targetId, $userId]);
            if (!$q->fetchColumn()) jres(['success' => false, 'message' => 'Programme not found.']);
            $_SESSION['selected_programme_id'] = $targetId;
            jres(['success' => true, 'programme_id' => $targetId]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to switch.']);
        }
    }

    // -------- GET INVESTORS (active + inactive + requestors + closed) --------
    if ($action === 'get_investors') {
        if (!$programme) jres(['success' => false]);
        try {
            $programmeId = (int)$programme['id'];

            // Active + Inactive deals (strictly from programme_revenue_deals)
            $deals = getDealsForProgramme($pdo, $userId, $programmeId);
            $active   = $deals['active'];
            $inactive = $deals['inactive'];

            // Pending requestors for the current programme
            $rq = $pdo->prepare("
                SELECT r.id, r.programme_id, r.developerid, r.owner_sub_account_id,
                       r.requestor_id, r.requestor_sub_account_id,
                       r.request_status, r.created_at,
                       h.username, h.first_name, h.last_name, h.fullname, h.email AS requestor_email
                FROM programme_investment_requestors r
                LEFT JOIN harvhub h ON h.id = r.requestor_id
                WHERE r.developerid = ? AND r.programme_id = ? AND r.request_status = 'pending'
                ORDER BY r.created_at DESC
            ");
            $rq->execute([$userId, $programmeId]);
            $requestors = $rq->fetchAll(PDO::FETCH_ASSOC);

            $requestorList = [];
            foreach ($requestors as $r) {
                $name = trim((string)($r['username'] ?? '')) ?: (trim((string)($r['first_name'] ?? '')) ?: (trim((string)($r['fullname'] ?? '')) ?: (string)($r['requestor_email'] ?? ('Requestor #' . (int)$r['requestor_id']))));
                $initial = strtoupper(substr($name !== '' ? $name : 'R', 0, 1));
                $requestorList[] = [
                    'request_id'     => (int)$r['id'],
                    'requestor_id'   => (int)$r['requestor_id'],
                    'name'           => $name,
                    'initial'        => $initial,
                    'request_status' => $r['request_status'],
                    'created_at'     => (string)($r['created_at'] ?? ''),
                ];
            }

            // Closed deals grouped by contract date range (inactive deals)
            $closed = getClosedDealsForProgramme($pdo, $userId, $programmeId);

            jres([
                'success'    => true,
                'active'     => $active,
                'inactive'   => $inactive,
                'requestors' => $requestorList,
                'closed'     => $closed,
            ]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load investors: ' . $e->getMessage()]);
        }
    }

    // -------- ACCEPT / REJECT INVESTMENT REQUEST --------
    if ($action === 'update_request_status') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        $requestId = (int)($_POST['request_id'] ?? 0);
        $newStatus = trim($_POST['new_status'] ?? '');

        $allowed = ['accept', 'reject'];
        if ($requestId <= 0 || !in_array($newStatus, $allowed, true)) {
            jres(['success' => false, 'message' => 'Invalid request.']);
        }

        try {
            $stmt = $pdo->prepare("
                SELECT r.*, p.program_name, p.userid AS dev_userid, p.sub_account_id AS dev_sub_id,
                       h.email AS requestor_email, h.username, h.first_name, h.last_name, h.fullname
                FROM programme_investment_requestors r
                LEFT JOIN programme p ON p.id = r.programme_id
                LEFT JOIN harvhub h ON h.id = r.requestor_id
                WHERE r.id = ? AND r.programme_id = ? AND r.developerid = ?
                LIMIT 1
            ");
            $stmt->execute([$requestId, (int)$programme['id'], $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                jres(['success' => false, 'message' => 'Request not found or not for this programme.']);
            }

            if ($row['request_status'] !== 'pending') {
                jres(['success' => false, 'message' => 'This request is already ' . $row['request_status'] . '.']);
            }

            $requestorId  = (int)$row['requestor_id'];
            $reqSubId     = (int)($row['requestor_sub_account_id'] ?? 0);
            $programmeId  = (int)$row['programme_id'];
            $developerId  = (int)$row['developerid'];

            if ($newStatus === 'accept') {
                $existing = null;
                try {
                    $chk = $pdo->prepare("
                        SELECT pi.id, pi.programme_id, p.program_name
                        FROM programme_investors pi
                        LEFT JOIN programme p ON p.id = pi.programme_id
                        WHERE pi.investorid = ?
                          AND (pi.sub_account_id = ? OR pi.sub_account_id IS NULL)
                        LIMIT 1
                    ");
                    $chk->execute([$requestorId, $reqSubId]);
                    $existing = $chk->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {}

                if ($existing) {
                    $del = $pdo->prepare("DELETE FROM programme_investment_requestors WHERE id = ?");
                    $del->execute([$requestId]);
                    jres([
                        'success'      => false,
                        'message'      => 'This user is already invested in a programme (' . ($existing['program_name'] ?? 'another programme') . '). Their pending request has been cleared.',
                        'cleared'      => true,
                        'needs_reload' => true
                    ]);
                }

                $piReq = null;
                try {
                    $ps = $pdo->prepare("
                        SELECT contract_duration, developer_percentage, investor_percentage,
                               minimum_investment_amount, maximum_investment_amount
                        FROM programme_investors
                        WHERE programme_id = ? AND developerid = ? AND investorid = 0
                        ORDER BY id ASC LIMIT 1
                    ");
                    $ps->execute([$programmeId, $developerId]);
                    $piReq = $ps->fetch(PDO::FETCH_ASSOC);

                    if (!$piReq) {
                        $ps = $pdo->prepare("
                            SELECT contract_duration, developer_percentage, investor_percentage,
                                   minimum_investment_amount, maximum_investment_amount
                            FROM programme_investors
                            WHERE programme_id = ? AND investorid = 0
                            ORDER BY id ASC LIMIT 1
                        ");
                        $ps->execute([$programmeId]);
                        $piReq = $ps->fetch(PDO::FETCH_ASSOC);
                    }
                } catch (PDOException $e) {}

                $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
                $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
                $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
                $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

                $contractDuration = $piReq && (int)$piReq['contract_duration'] > 0 ? (int)$piReq['contract_duration'] : $srvContractDuration;
                $minInvestment    = $piReq && (float)$piReq['minimum_investment_amount'] > 0 ? (float)$piReq['minimum_investment_amount'] : $srvMinBrokerBalance;
                $maxInvestment    = $piReq ? (float)$piReq['maximum_investment_amount'] : 0;
                $developerPercent = $piReq && (int)$piReq['developer_percentage'] > 0 ? (int)$piReq['developer_percentage'] : $srvServerShare;
                $investorPercent  = $piReq && (int)$piReq['investor_percentage'] > 0 ? (int)$piReq['investor_percentage'] : $srvUserShare;

                $ins = $pdo->prepare("
                    INSERT INTO programme_investors
                        (investorid, developerid, programme_id, sub_account_id, invested_at,
                         contract_duration, developer_percentage, investor_percentage,
                         minimum_investment_amount, maximum_investment_amount)
                    VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $requestorId,
                    $developerId,
                    $programmeId,
                    $reqSubId ?: null,
                    $contractDuration,
                    $developerPercent,
                    $investorPercent,
                    $minInvestment,
                    $maxInvestment
                ]);

                // Insert into programme_revenue_deals — including investor_id
                // and initial profit snapshot (currently 0). investor_profit will
                // be continuously synced while contract is active.
                $dealIns = $pdo->prepare("
                    INSERT INTO programme_revenue_deals
                        (programme_id, investor_id, investor_sub_account_id,
                         programme_name, accepted_at, investor_investment_amount,
                         contract_duration, programme_percentage, minimum_investment, maximum_investment,
                         investor_profit, investor_payment_status, server_payment_status)
                    VALUES (?, ?, ?, ?, NOW(), 0.00, ?, ?, ?, ?, 0.00, NULL, NULL)
                ");
                $dealIns->execute([
                    $programmeId,
                    $requestorId,
                    $reqSubId ?: null,
                    $row['program_name'] ?? 'Programme',
                    $contractDuration,
                    $developerPercent,
                    $minInvestment,
                    $maxInvestment
                ]);

                try {
                    if ($reqSubId > 0) {
                        $delOthers = $pdo->prepare("
                            DELETE FROM programme_investment_requestors
                            WHERE requestor_id = ?
                              AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                              AND id <> ?
                              AND NOT (programme_id = ? AND developerid = ?)
                        ");
                        $delOthers->execute([$requestorId, $reqSubId, $requestId, $programmeId, $developerId]);
                    } else {
                        $delOthers = $pdo->prepare("
                            DELETE FROM programme_investment_requestors
                            WHERE requestor_id = ?
                              AND id <> ?
                              AND NOT (programme_id = ? AND developerid = ?)
                        ");
                        $delOthers->execute([$requestorId, $requestId, $programmeId, $developerId]);
                    }
                } catch (PDOException $e) {}

                $delRow = $pdo->prepare("DELETE FROM programme_investment_requestors WHERE id = ?");
                $delRow->execute([$requestId]);

                $requestorEmail = strtolower(trim((string)($row['requestor_email'] ?? '')));
                if ($requestorEmail !== '' && $reqSubId > 0) {
                    try {
                        $mainAccId = 0;
                        $mq = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
                        $mq->execute([$requestorId]);
                        $mainAccId = (int)($mq->fetchColumn() ?: 0);

                        recordContractNotification($pdo, [
                            'user_email'       => $requestorEmail,
                            'sub_account_id'   => $reqSubId,
                            'main_account_id'  => $mainAccId,
                            'notification_key' => 'programme-investment-accepted-' . $programmeId . '-' . $reqSubId . '-' . date('YmdHis'),
                            'title'            => 'Investment Request Accepted',
                            'message'          => 'Your request to join the ' . ($row['program_name'] ?? 'Programme') . ' programme has been accepted.',
                            'type'             => 'success',
                            'section'          => 'Programme',
                            'action_tab'       => 'programmes',
                            'force'            => true
                        ]);
                    } catch (Throwable $e) {}
                }

                jres(['success' => true, 'message' => 'Request accepted. User added as investor.']);

            } else {
                $upd = $pdo->prepare("UPDATE programme_investment_requestors SET request_status = 'reject' WHERE id = ? AND request_status = 'pending'");
                $upd->execute([$requestId]);

                if ($upd->rowCount() === 0) {
                    jres(['success' => false, 'message' => 'Request is already processed.']);
                }

                $requestorEmail = strtolower(trim((string)($row['requestor_email'] ?? '')));
                if ($requestorEmail !== '' && $reqSubId > 0) {
                    try {
                        $mainAccId = 0;
                        $mq = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
                        $mq->execute([$requestorId]);
                        $mainAccId = (int)($mq->fetchColumn() ?: 0);

                        recordContractNotification($pdo, [
                            'user_email'       => $requestorEmail,
                            'sub_account_id'   => $reqSubId,
                            'main_account_id'  => $mainAccId,
                            'notification_key' => 'programme-investment-rejected-' . $programmeId . '-' . $reqSubId . '-' . date('YmdHis'),
                            'title'            => 'Investment Request Declined',
                            'message'          => 'Your request to join the ' . ($row['program_name'] ?? 'Programme') . ' programme has been declined.',
                            'type'             => 'warning',
                            'section'          => 'Programme',
                            'action_tab'       => 'programmes',
                            'force'            => true
                        ]);
                    } catch (Throwable $e) {}
                }

                jres(['success' => true, 'message' => 'Request rejected.']);
            }
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to update request: ' . $e->getMessage()]);
        }
    }

    if ($action === 'get_symbols_timeframes') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $brokerSymbols    = parseCleanList((string)($programme['broker_symbols'] ?? ''));
            $brokerTimeframes = parseCleanList((string)($programme['broker_timeframes'] ?? ''));
            $selectedSymbols  = parseCleanList((string)($programme['selected_symbols'] ?? ''));
            $selectedTFs      = parseCleanList((string)($programme['selected_timeframes'] ?? ''));

            if (empty($brokerSymbols) || empty($brokerTimeframes)) {
                try {
                    $bs = $pdo->prepare("SELECT symbol, selected_timeframes, symbol_selected FROM broker_symbols WHERE userid = ? ORDER BY symbol ASC");
                    $bs->execute([$userId]);
                    while ($row = $bs->fetch(PDO::FETCH_ASSOC)) {
                        if (($row['symbol'] ?? '') === '__GLOBAL_TF__') {
                            if (empty($brokerTimeframes)) $brokerTimeframes = parseCleanList((string)($row['selected_timeframes'] ?? ''));
                        } else {
                            $sym = trim((string)($row['symbol'] ?? ''));
                            if ($sym !== '') $brokerSymbols[] = $sym;
                            if ((int)($row['symbol_selected'] ?? 0) === 1 && $sym !== '') $selectedSymbols[] = $sym;
                        }
                    }
                    $brokerSymbols    = array_values(array_unique($brokerSymbols));
                    $brokerTimeframes = array_values(array_unique($brokerTimeframes));
                    $selectedSymbols  = array_values(array_unique($selectedSymbols));
                } catch (Throwable $e) {}
            }

            jres([
                'success'              => true,
                'broker_symbols'       => $brokerSymbols,
                'broker_timeframes'    => $brokerTimeframes,
                'selected_symbols'     => $selectedSymbols,
                'selected_timeframes'  => $selectedTFs,
            ]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load symbols.']);
        }
    }

    if ($action === 'save_symbols_timeframes') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $symbols     = isset($_POST['symbols'])    ? parseCleanList((string)$_POST['symbols'])    : [];
            $timeframes  = isset($_POST['timeframes']) ? parseCleanList((string)$_POST['timeframes']) : [];

            $symsCsv = implode(',', $symbols);
            $tfsJson = json_encode(array_values($timeframes));

            $upd = $pdo->prepare("UPDATE programme SET selected_symbols = ?, selected_timeframes = ? WHERE id = ? AND userid = ?");
            $upd->execute([$symsCsv, $tfsJson, (int)$programme['id'], $userId]);

            jres(['success' => true, 'selected_symbols' => $symbols, 'selected_timeframes' => $timeframes]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to save.']);
        }
    }

    if ($action === 'save_visibility') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $vis = !empty($_POST['visibility']) ? 1 : 0;
            $upd = $pdo->prepare("UPDATE programme SET visibility = ? WHERE id = ? AND userid = ?");
            $upd->execute([$vis, (int)$programme['id'], $userId]);
            jres(['success' => true, 'visibility' => $vis]);
        } catch (Throwable $e) { jres(['success' => false, 'message' => 'Failed to save visibility.']); }
    }

    if ($action === 'save_requirements') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $contractDuration = (int)($_POST['contract_duration'] ?? 0);
            $developerPercent = (float)($_POST['developer_percentage'] ?? 0);
            $minInvestment    = (float)($_POST['minimum_investment_amount'] ?? 0);
            $maxInvestment    = (float)($_POST['maximum_investment_amount'] ?? 0);

            $developerPercent = max(0, min(100, $developerPercent));
            $investorPercent  = 100 - $developerPercent;

            $floorContract = 0; $floorMinBal = 0.0;
            try {
                $srv = $pdo->query("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($srv) { $floorContract = (int)($srv['contract_duration'] ?? 0); $floorMinBal = (float)($srv['min_broker_balance'] ?? 0); }
            } catch (Throwable $e) {}

            if ($contractDuration < $floorContract) jres(['success'=>false,'message'=>"Contract Duration must be at least {$floorContract} days."]);
            if ($minInvestment < $floorMinBal) jres(['success'=>false,'message'=>'Minimum Investment must be at least $'.number_format($floorMinBal,2).'.']);
            if ($maxInvestment < 0) $maxInvestment = 0;

            $checkRow = $pdo->prepare("SELECT id FROM programme_investors WHERE programme_id=? AND developerid=? AND investorid=0 LIMIT 1");
            $checkRow->execute([(int)$programme['id'], $userId]);
            $existing = $checkRow->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $upd = $pdo->prepare("UPDATE programme_investors SET contract_duration=?, developer_percentage=?, investor_percentage=?, minimum_investment_amount=?, maximum_investment_amount=? WHERE id=?");
                $upd->execute([$contractDuration, $developerPercent, $investorPercent, $minInvestment, $maxInvestment, (int)$existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO programme_investors (investorid, developerid, programme_id, contract_duration, developer_percentage, investor_percentage, minimum_investment_amount, maximum_investment_amount) VALUES (0, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$userId, (int)$programme['id'], $contractDuration, $developerPercent, $investorPercent, $minInvestment, $maxInvestment]);
            }

            $sync = $pdo->prepare("UPDATE programme_investors SET contract_duration=?, developer_percentage=?, investor_percentage=?, minimum_investment_amount=?, maximum_investment_amount=? WHERE programme_id=? AND developerid=? AND investorid>0");
            $sync->execute([$contractDuration, $developerPercent, $investorPercent, $minInvestment, $maxInvestment, (int)$programme['id'], $userId]);

            jres(['success'=>true, 'developer_percentage'=>$developerPercent, 'investor_percentage'=>$investorPercent]);
        } catch (Throwable $e) { jres(['success' => false, 'message' => 'Failed to save requirements.']); }
    }

    if ($action === 'modify_trade') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        $tradeId = (int)($_POST['trade_id'] ?? 0);
        $mode    = (string)($_POST['modify_trade'] ?? '');
        $allowed = ['keep-running', 'breakeven', 'close'];
        if ($tradeId <= 0 || !in_array($mode, $allowed, true)) jres(['success' => false, 'message' => 'Invalid request.']);
        try {
            $u = $pdo->prepare("UPDATE programme_trades SET modify_trade = ? WHERE id = ? AND userid = ? AND programmeid = ?");
            $u->execute([$mode, $tradeId, $userId, (int)$programme['id']]);
            jres(['success' => true, 'modify_trade' => $mode]);
        } catch (Throwable $e) { jres(['success' => false, 'message' => 'Failed to update trade.']); }
    }

    if ($action === 'toggle_dark_mode') {
        $mode = !empty($_POST['dark_mode']) ? 1 : 0;
        $u = $pdo->prepare("UPDATE harvhub SET dark_mode = ? WHERE id = ?");
        $u->execute([$mode, $userId]);
        jres(['success' => true, 'dark_mode' => $mode]);
    }

    jres(['success' => false]);
}

// -------------------- DERIVED VALUES (initial server render) --------------------
syncInvestorProfitsIntoDeals($pdo);

$revenueSettings = null; $developerPercentage = 0.0; $investorCount = 0; $revenueAmount = 0.0;
$contractDuration = 0; $minInvestment = 0.0; $maxInvestment = 0.0;
$globalIncomingRequestCount = 0;

// Global count across ALL programmes for the banner
try {
    $g = $pdo->prepare("
        SELECT COUNT(*) FROM programme_investment_requestors r
        INNER JOIN programme p ON p.id = r.programme_id
        WHERE p.userid = ? AND r.request_status = 'pending'
    ");
    $g->execute([$userId]);
    $globalIncomingRequestCount = (int)$g->fetchColumn();
} catch (Throwable $e) {}

if ($programme) {
    try { $s = $pdo->prepare("SELECT * FROM programme_investors WHERE developerid = ? AND programme_id = ? AND investorid = 0 ORDER BY id DESC LIMIT 1"); $s->execute([$userId, (int)$programme['id']]); $revenueSettings = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) {}
    if ($revenueSettings && $revenueSettings['developer_percentage'] !== null) $developerPercentage = (float)$revenueSettings['developer_percentage'];
    if ($revenueSettings) {
        $contractDuration = isset($revenueSettings['contract_duration']) ? (int)$revenueSettings['contract_duration'] : 0;
        $minInvestment    = isset($revenueSettings['minimum_investment_amount']) ? (float)$revenueSettings['minimum_investment_amount'] : 0.0;
        $maxInvestment    = isset($revenueSettings['maximum_investment_amount']) ? (float)$revenueSettings['maximum_investment_amount'] : 0.0;
    }

    // Active investor count = contract currently running (mirrors investors tab)
    try {
        $q = $pdo->prepare("
            SELECT accepted_at, contract_duration
            FROM programme_revenue_deals
            WHERE programme_id = ? AND investor_id > 0
        ");
        $q->execute([(int)$programme['id']]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $c = classifyDealState($r['accepted_at'] ?? null, (int)($r['contract_duration'] ?? 0));
            if ($c['state'] === 'active') $investorCount++;
        }
    } catch (Throwable $e) {}

    try {
        $s = $pdo->prepare("SELECT h.profitandloss FROM programme_investors pi INNER JOIN harvhub h ON h.id = pi.investorid WHERE pi.developerid = ? AND pi.programme_id = ? AND pi.investorid > 0");
        $s->execute([$userId, (int)$programme['id']]);
        while ($r = $s->fetch(PDO::FETCH_ASSOC)) { $pnl = (float)($r['profitandloss'] ?? 0); if ($pnl > 0) $revenueAmount += ($pnl * $developerPercentage / 100); }
    } catch (Throwable $e) {}
}

$hasVps = false;
if ($programme) {
    try { $s = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1"); $s->execute([(int)$programme['id']]); $hasVps = (bool)$s->fetchColumn(); } catch (Throwable $e) {}
    if (!$hasVps) {
        try { $s = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1"); $s->execute([(int)$programme['id']]); $hasVps = (bool)$s->fetchColumn(); } catch (Throwable $e) {}
    }
}

$hasBroker = false;
if ($programme) $hasBroker = (!empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']));
$published = $programme && (int)$programme['advertisement'] === 1;
$isPublic  = $programme && (int)($programme['visibility'] ?? 0) === 1;

$incomingRequestCount = 0;
if ($programme) {
    try { $s = $pdo->prepare("SELECT COUNT(*) FROM programme_investment_requestors WHERE developerid = ? AND programme_id = ? AND request_status = 'pending'"); $s->execute([$userId, (int)$programme['id']]); $incomingRequestCount = (int)$s->fetchColumn(); } catch (Throwable $e) {}
}

$trades = [];
if ($programme) {
    try { $s = $pdo->prepare("SELECT * FROM programme_trades WHERE userid = ? AND programmeid = ? ORDER BY created_at DESC LIMIT 200"); $s->execute([$userId, (int)$programme['id']]); $trades = $s->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}
}

$serverContractDuration = 0; $serverMinBrokerBalance = 0.0;
try {
    $srv = $pdo->query("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($srv) { $serverContractDuration = (int)($srv['contract_duration'] ?? 0); $serverMinBrokerBalance = (float)($srv['min_broker_balance'] ?? 0); }
} catch (Throwable $e) {}
if ($contractDuration < $serverContractDuration) $contractDuration = $serverContractDuration;
if ($minInvestment < $serverMinBrokerBalance) $minInvestment = $serverMinBrokerBalance;

$allBrokerSymbols = []; $availableTimeframes = [];
try {
    $bs = $pdo->prepare("SELECT symbol, timeframes FROM broker_symbols WHERE userid = ? ORDER BY symbol ASC");
    $bs->execute([$userId]);
    while ($r = $bs->fetch(PDO::FETCH_ASSOC)) {
        if (($r['symbol'] ?? '') === '__GLOBAL_TF__') continue;
        $allBrokerSymbols[] = $r['symbol'];
        $rawTfs = (string)($r['timeframes'] ?? '');
        $rawTfs = str_replace(['[', ']', '{', '}', '"', "'", ' '], '', $rawTfs);
        foreach (array_filter(explode(',', $rawTfs)) as $tf) {
            $tf = trim($tf);
            if ($tf !== '') $availableTimeframes[$tf] = true;
        }
    }
    $availableTimeframes = array_keys($availableTimeframes);
    sort($availableTimeframes);
} catch (Throwable $e) {}

function getProgrammeMarketSettings(array $programme): array {
    $symbols    = parseCleanList((string)($programme['selected_symbols']    ?? ''));
    $timeframes = parseCleanList((string)($programme['selected_timeframes'] ?? ''));
    if (empty($symbols))    $symbols    = parseCleanList((string)($programme['broker_symbols'] ?? ''));
    if (empty($timeframes)) $timeframes = parseCleanList((string)($programme['broker_timeframes'] ?? ''));
    return ['symbols' => $symbols, 'timeframes' => $timeframes];
}

$marketSettings = $programme ? getProgrammeMarketSettings($programme) : ['symbols'=>[], 'timeframes'=>[]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Signals Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    :root{
        --sd-bg: var(--bg, #f4f8f7); --sd-card: var(--bg-card, #ffffff); --sd-text: var(--text, #12211d);
        --sd-muted: var(--text-muted, #60736d); --sd-accent: var(--accent, #12a36b); --sd-accent-2: var(--accent-hover, #0b7d52);
        --sd-soft: var(--accent-light, #e3f6ee); --sd-border: var(--border-color, #dfe9e5);
        --sd-danger: var(--danger, #df4e4e); --sd-warning: var(--warning, #b87513);
        --sd-success: var(--success, #2ecc71); --sd-info: var(--info, #3498db);
        --sd-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06)); --sd-radius: var(--radius, 16px);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        background: var(--sd-bg); color: var(--sd-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55; -webkit-tap-highlight-color: transparent;
        min-height: 100vh;
    }
    body::-webkit-scrollbar { display: none; }
    a { text-decoration: none; color: inherit; }
    body.dark-mode { --sd-bg:#0c1311; --sd-card:#131d1a; --sd-text:#e8f2ee; --sd-muted:#93a8a1; --sd-soft:#12271f; --sd-border:#22322d; --sd-shadow: 0 10px 30px rgba(0,0,0,.4); }

    body.sd-scroll-locked { overflow: hidden; }

    .sd-page { max-width: 1100px; margin: 0 auto; padding: 0 20px 100px; width: 100%; }
    @media (max-width: 480px) { .sd-page { padding: 0 14px 90px; } }

    .sd-greeting { background: linear-gradient(135deg, rgba(46,204,143,0.12), rgba(46,204,143,0.03)); border-radius: 14px; padding: 16px 18px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 12px; }
    body.dark-mode .sd-greeting { background: linear-gradient(135deg, rgba(46,204,143,0.18), rgba(46,204,143,0.04)); }
    .sd-greeting-top { display: flex; align-items: center; gap: 14px; }
    .sd-greeting-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--sd-accent); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1.15rem; flex-shrink:0; }
    .sd-greeting-text { flex: 1; min-width: 0; }
    .sd-greeting-text .sd-greet-account { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--sd-accent); font-weight: 700; margin: 0 0 2px 0; }
    .sd-greeting-text .sd-greet-title { font-size: 1.02rem; font-weight: 700; margin: 0 0 2px 0; word-break: break-word; }
    .sd-greeting-text .sd-greet-sub { font-size: 0.82rem; color: var(--sd-muted); margin: 0; line-height: 1.45; }
    .sd-greeting-divider { height: 1px; background: var(--sd-border); margin: 0 -18px; opacity: 0.6; }

    .sd-incoming-banner { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; padding: 12px 14px; background: var(--sd-card); border: 1px solid var(--sd-border); border-radius: 12px; color: var(--sd-text); font-family: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer; text-align: left; transition: transform .15s, border-color .2s; }
    .sd-incoming-banner:hover { border-color: var(--sd-accent); transform: translateX(2px); }
    .sd-incoming-left { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
    .sd-incoming-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: rgba(46,204,143,0.12); color: var(--sd-accent); font-size: .9rem; }
    .sd-incoming-title { font-size: .9rem; font-weight: 700; }
    .sd-incoming-sub { font-size: .72rem; font-weight: 500; color: var(--sd-muted); }
    .sd-incoming-right { display: flex; align-items: center; gap: 10px; }
    .sd-incoming-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; background: var(--sd-accent); color: #fff; font-size: .7rem; font-weight: 800; }
    .sd-incoming-chev { color: var(--sd-muted); font-size: .85rem; }

    .sd-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; margin-bottom: 18px; }
    @media (max-width: 640px) { .sd-stats-grid { grid-template-columns: 1fr; } }
    .sd-stat-card { background: var(--sd-card); border: 1px solid var(--sd-border); border-radius: var(--sd-radius); padding: 20px 22px; box-shadow: var(--sd-shadow); display: flex; flex-direction: column; gap: 8px; position: relative; }
    .sd-card-label { font-size: .68rem; text-transform: uppercase; letter-spacing: 1px; color: var(--sd-muted); font-weight: 700; }
    .sd-card-value { display: flex; align-items: center; gap: 4px; font-size: 1.9rem; font-weight: 800; letter-spacing: -0.5px; line-height: 1.1; flex-wrap: wrap; }
    .sd-card-value .sd-currency { font-size: 1.2rem; font-weight: 400; color: var(--sd-muted); }
    .sd-card-sub { font-size: .8rem; color: var(--sd-muted); }

    .sd-investors-badge { position: absolute; right: 14px; top: 14px; display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: rgba(46,204,143,0.12); color: var(--sd-accent); font-size: .78rem; font-weight: 800; cursor: pointer; border: 1px solid rgba(46,204,143,0.25); }
    .sd-investors-badge:hover { background: rgba(46,204,143,0.2); }
    .sd-investors-badge i { font-size: .75rem; }

    .sd-account-meta { margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--sd-border); display: flex; flex-wrap: wrap; gap: 8px 16px; font-size: 0.78rem; }
    .sd-account-meta-item { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
    .sd-account-meta-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--sd-muted); font-weight: 600; }
    .sd-account-meta-value { font-weight: 700; color: var(--sd-text); word-break: break-word; }

    .sd-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--sd-border); margin: 20px 0 18px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; }
    .sd-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .sd-tab { background: transparent; border: none; color: var(--sd-muted); padding: 12px 18px; font-weight: 700; font-size: .88rem; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -1px; font-family: inherit; }
    .sd-tab.active { color: var(--sd-accent); border-bottom-color: var(--sd-accent); }
    .sd-panel { display: none; } .sd-panel.active { display: block; }

    .sd-card { background: var(--sd-card); border: 1px solid var(--sd-border); border-radius: var(--sd-radius); padding: 22px; margin-bottom: 18px; box-shadow: var(--sd-shadow); }
    .sd-card-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
    .sd-card-title { font-size: 1.05rem; font-weight: 800; }
    .sd-card-desc { font-size: .82rem; color: var(--sd-muted); margin-top: 2px; }

    .sd-empty { padding: 32px 20px; text-align: center; color: var(--sd-muted); border: 1px dashed var(--sd-border); border-radius: 14px; background: var(--sd-bg); }
    .sd-empty i { font-size: 1.6rem; margin-bottom: 10px; color: var(--sd-accent); display: block; }
    .sd-empty strong { display: block; color: var(--sd-text); font-size: .98rem; margin-bottom: 4px; }
    .sd-empty p { font-size: .85rem; }

    .sd-btn { border: 0; border-radius: 11px; padding: 10px 16px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-size: .85rem; }
    .sd-btn.primary { background: var(--sd-accent); color: #fff; }
    .sd-btn.ghost { background: transparent; color: var(--sd-text); border: 1px solid var(--sd-border); }
    .sd-btn.primary:hover { background: var(--sd-accent-2); }

    .sd-trades-list { display: grid; gap: 9px; }
    .sd-trade-row { display: grid; grid-template-columns: 1.1fr 1fr 1.4fr 0.8fr 1fr; gap: 10px; border: 1px solid var(--sd-border); border-radius: 13px; padding: 13px; background: var(--sd-bg); font-size: .82rem; align-items: center; }
    @media (max-width: 760px) { .sd-trade-row { grid-template-columns: 1fr 1fr; } }
    .sd-trade-row .sd-t-label { font-size: .66rem; color: var(--sd-muted); text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }

    .sd-modify-select { width: 100%; padding: 8px 10px; border-radius: 9px; border: 1px solid var(--sd-border); background: var(--sd-card); color: var(--sd-text); font-family: inherit; font-size: .82rem; font-weight: 600; cursor: pointer; }
    .sd-modify-select:focus { outline: none; border-color: var(--sd-accent); }
    .sd-modify-select[data-mode="breakeven"] { border-color: var(--sd-warning); color: var(--sd-warning); }
    .sd-modify-select[data-mode="close"]     { border-color: var(--sd-danger);  color: var(--sd-danger); }

    .sd-status-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    @media (max-width: 640px) { .sd-status-grid { grid-template-columns: 1fr; } }
    .sd-status-tile { background: var(--sd-bg); border: 1px solid var(--sd-border); border-radius: 12px; padding: 14px; }
    .sd-status-tile-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .6px; color: var(--sd-muted); font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
    .sd-status-tile-label i { color: var(--sd-accent); }
    .sd-status-tile-value { font-size: .95rem; font-weight: 700; word-break: break-word; }
    .sd-status-tile-value .sd-chip { display: inline-block; padding: 3px 9px; margin: 2px 4px 2px 0; background: rgba(46,204,143,0.12); color: var(--sd-accent); border-radius: 8px; font-size: .78rem; font-weight: 700; }

    .sd-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 99999; overscroll-behavior: contain; }
    .sd-modal.open { display: flex; }
    .sd-modal-card { width: min(500px, 100%); max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; background: var(--sd-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .sd-modal-card h3 { margin-bottom: 5px; font-size: 1.05rem; font-weight: 800; }
    .sd-modal-card .sd-sub { color: var(--sd-muted); font-size: .82rem; }

    /* Request picker modal (global list) */
    .sd-picker-card { width: min(460px, 100%); max-height: calc(100vh - 120px); overflow-y: auto; background: var(--sd-card); border-radius: 20px; padding: 22px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .sd-picker-card h3 { margin-bottom: 5px; font-size: 1.05rem; font-weight: 800; }
    .sd-picker-card .sd-sub { color: var(--sd-muted); font-size: .82rem; margin-bottom: 14px; }
    .sd-picker-list { display: flex; flex-direction: column; gap: 8px; }
    .sd-picker-item { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; padding: 13px 14px; border-radius: 12px; background: var(--sd-bg); border: 1px solid var(--sd-border); cursor: pointer; font-family: inherit; text-align: left; color: var(--sd-text); transition: border-color .15s, transform .15s; }
    .sd-picker-item:hover { border-color: var(--sd-accent); transform: translateX(2px); }
    .sd-picker-item.is-current { border-color: var(--sd-accent); background: rgba(46,204,143,0.08); }
    .sd-picker-item-left { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
    .sd-picker-item-title { font-weight: 800; font-size: .92rem; word-break: break-word; }
    .sd-picker-item-sub { font-size: .72rem; color: var(--sd-muted); font-weight: 500; }
    .sd-picker-item-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .sd-picker-item-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; background: var(--sd-accent); color: #fff; font-size: .7rem; font-weight: 800; }
    .sd-picker-item-chev { color: var(--sd-muted); font-size: .85rem; }
    .sd-picker-item.is-current .sd-picker-item-chev { color: var(--sd-accent); }

    .sd-field { margin: 13px 0; }
    .sd-field label { display: block; font-size: .7rem; text-transform: uppercase; color: var(--sd-muted); font-weight: 800; margin-bottom: 6px; }
    .sd-field input, .sd-field select { width: 100%; padding: 12px; border: 1px solid var(--sd-border); border-radius: 11px; background: var(--sd-bg); color: var(--sd-text); font: inherit; }

    .sd-fold-section { margin: 10px 0; border: 1px solid var(--sd-border); border-radius: 12px; overflow: hidden; }
    .sd-fold-toggle { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 13px 15px; background: var(--sd-bg); border: 0; font-weight: 800; font-size: .88rem; color: var(--sd-text); cursor: pointer; font-family: inherit; }
    .sd-fold-toggle i { transition: transform .2s; color: var(--sd-muted); font-size: .75rem; }
    .sd-fold-toggle.open i { transform: rotate(180deg); }
    .sd-fold-body { padding: 0 15px 14px; display: none; }
    .sd-fold-body.open { display: block; }
    .sd-check-list { display: block; max-height: 200px; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }
    .sd-check-row { display: flex; align-items: center; gap: 10px; padding: 8px 10px; margin-bottom: 6px; border-radius: 9px; background: var(--sd-card); border: 1px solid var(--sd-border); font-size: .84rem; cursor: pointer; }
    .sd-check-row:last-child { margin-bottom: 0; }
    .sd-check-row input { accent-color: var(--sd-accent); }
    .sd-req-grid { display: grid; gap: 12px; }
    .sd-err { display: none; background: #fdecec; color: var(--sd-danger); padding: 10px; border-radius: 10px; font-size: .82rem; margin-bottom: 10px; }
    body.dark-mode .sd-err { background: #3a1c1c; color: #ff9c9c; }

    .sd-investors-view { position: fixed; inset: 0; background: var(--sd-bg); z-index: 99998; display: none; flex-direction: column; overflow: hidden; overscroll-behavior: contain; }
    .sd-investors-view.open { display: flex; }
    .sd-investors-view-header { display:flex; align-items:center; justify-content:center; position:relative; padding: calc(env(safe-area-inset-top, 0px) + 14px) 16px 14px; background: var(--sd-bg); min-height:56px; flex: 0 0 auto; }
    .sd-investors-view-back { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--sd-text); font-size: 1.2rem; cursor: pointer; padding: 8px; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .sd-investors-view-title { font-size: 1.15rem; font-weight: 800; }
    .sd-investors-view-body { flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding: 20px; max-width: 720px; margin: 0 auto; width: 100%; }

    /* Investors view tabs — hide horizontal scrollbar */
    .sd-inv-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--sd-border); margin-bottom: 18px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; }
    .sd-inv-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .sd-inv-tab { background: transparent; border: none; color: var(--sd-muted); padding: 10px 16px; font-weight: 700; font-size: .85rem; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -1px; font-family: inherit; }
    .sd-inv-tab.active { color: var(--sd-accent); border-bottom-color: var(--sd-accent); }
    .sd-inv-panel { display: none; }
    .sd-inv-panel.active { display: block; }

    .sd-inv-card { display: flex; gap: 14px; border-radius: 14px; padding: 16px 0px; margin-bottom: 12px; }
    .sd-inv-avatar { width: 54px; height: 54px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.35rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .sd-inv-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; justify-content: center; }
    .sd-inv-username { font-size: 1rem; font-weight: 800; color: var(--sd-text); word-break: break-word; line-height: 1.25; }
    .sd-inv-stat { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: .85rem; }
    .sd-inv-stat-key { color: var(--sd-muted); font-weight: 600; }
    .sd-inv-stat-val { font-weight: 800; color: var(--sd-text); text-align: right; word-break: break-word; }
    .sd-inv-stat-val.money { color: var(--sd-accent);font-size: 1.15rem;}
    .sd-inv-stat-val.ended { color: var(--sd-danger); }

    /* Inactive investor card — slightly muted to visually differentiate */
    .sd-inv-card.is-inactive .sd-inv-avatar { background: var(--sd-muted); }
    .sd-inv-card.is-inactive .sd-inv-stat-val.money { color: var(--sd-muted); font-size: 1.1rem; }

    .sd-req-card { display: flex; gap: 14px; border-radius: 14px; padding: 16px 0px; margin-bottom: 12px; box-shadow: var(--sd-shadow); align-items: center; }
    .sd-req-avatar { width: 54px; height: 54px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.35rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .sd-req-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .sd-req-username { font-size: 1rem; font-weight: 800; color: var(--sd-text); word-break: break-word; }
    .sd-req-meta { font-size: .8rem; color: var(--sd-muted); }
    .sd-req-select { padding: 8px 12px; border: 1px solid var(--sd-border); border-radius: 9px; background: var(--sd-bg); color: var(--sd-text); font-family: inherit; font-size: .82rem; font-weight: 600; cursor: pointer; min-width: 100px; flex-shrink: 0; }
    .sd-req-select:focus { outline: none; border-color: var(--sd-accent); }

    /* Closed deals view */
    .sd-closed-group { margin-bottom: 22px; }
    .sd-closed-range { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: var(--sd-accent); padding: 6px 0 10px; border-bottom: 1px solid var(--sd-border); margin-bottom: 12px; }
    .sd-closed-item { display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px dashed var(--sd-border); }
    .sd-closed-item:last-child { border-bottom: none; }
    .sd-closed-avatar { width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; text-transform: uppercase; }
    .sd-closed-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
    .sd-closed-name { font-size: .95rem; font-weight: 800; color: var(--sd-text); word-break: break-word; }
    .sd-closed-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: .82rem; }
    .sd-closed-key { color: var(--sd-muted); font-weight: 600; }
    .sd-closed-val { font-weight: 800; color: var(--sd-text); text-align: right; word-break: break-word; }
    .sd-closed-val.money { color: var(--sd-accent); }
    .sd-closed-val.muted { color: var(--sd-muted); font-weight: 600; }

    .sd-confirm-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 999999; }
    .sd-confirm-modal.open { display: flex; }
    .sd-confirm-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .sd-confirm-card h3 { margin-bottom: 8px; font-size: 1.05rem; font-weight: 800; }
    .sd-confirm-card p { color: var(--sd-muted); font-size: .88rem; margin-bottom: 18px; line-height: 1.5; }
    .sd-confirm-actions { display: grid; gap: 9px; }

    /* Live update highlight pulse */
    @keyframes sdLivePulse {
        0% { background: rgba(46,204,143,0.0); }
        50% { background: rgba(46,204,143,0.12); }
        100% { background: rgba(46,204,143,0.0); }
    }
    .sd-live-pulse { animation: sdLivePulse 1.2s ease; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="sd-page" id="sdPageRoot">

<?php if ($programme): ?>

    <div class="sd-greeting">
        <div class="sd-greeting-top">
            <div class="sd-greeting-avatar"><?= esc_h(strtoupper(substr($fullName ?: 'U', 0, 1))) ?></div>
            <div class="sd-greeting-text">
                <p class="sd-greet-account">Programme &nbsp;•&nbsp; <?= esc_h($programme['program_name'] ?: 'Unnamed Programme') ?></p>
                <p class="sd-greet-title">Hi there <?= esc_h($fullName) ?></p>
                <p class="sd-greet-sub" id="sdGreetSub"><?= esc_h($programme['program_name'] ? '' : '') ?></p>
            </div>
        </div>

        <div id="sdGreetingBanners">
            <?php if (!$hasVps): ?>
                <div class="sd-greeting-divider"></div>
                <button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab('vps')">
                    <span class="sd-incoming-left">
                        <span class="sd-incoming-icon"><i class="fa-solid fa-server"></i></span>
                        <span class="sd-incoming-label">
                            <span class="sd-incoming-title">Get VPS</span>
                            <span class="sd-incoming-sub">Required to analyse your programme</span>
                        </span>
                    </span>
                    <span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>
                </button>
            <?php elseif (!$hasBroker): ?>
                <div class="sd-greeting-divider"></div>
                <button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab('connect_trader_broker')">
                    <span class="sd-incoming-left">
                        <span class="sd-incoming-icon"><i class="fa-solid fa-plug"></i></span>
                        <span class="sd-incoming-label">
                            <span class="sd-incoming-title">Connect Broker</span>
                            <span class="sd-incoming-sub">Attach broker to this programme</span>
                        </span>
                    </span>
                    <span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>
                </button>
            <?php else: ?>
                <div class="sd-greeting-divider"></div>
                <button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab('vps')">
                    <span class="sd-incoming-left">
                        <span class="sd-incoming-icon"><i class="fa-solid fa-server"></i></span>
                        <span class="sd-incoming-label">
                            <span class="sd-incoming-title">Your VPS</span>
                            <span class="sd-incoming-sub">Your MT5 terminal broker details are logged in to this VPS</span>
                        </span>
                    </span>
                    <span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>
                </button>
                <button type="button" class="sd-incoming-banner" onclick="harvhubGoToTab('connect_trader_broker')">
                    <span class="sd-incoming-left">
                        <span class="sd-incoming-icon"><i class="fa-solid fa-plug"></i></span>
                        <span class="sd-incoming-label">
                            <span class="sd-incoming-title">Your broker</span>
                            <span class="sd-incoming-sub"><?= esc_h(($programme['broker'] ?? '') . ' · ' . ($programme['login'] ?? '')) ?></span>
                        </span>
                    </span>
                    <span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>
                </button>
            <?php endif; ?>

            <?php if ($globalIncomingRequestCount > 0): ?>
                <button type="button" class="sd-incoming-banner" onclick="sdOpenRequestPicker()">
                    <span class="sd-incoming-left">
                        <span class="sd-incoming-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                        <span class="sd-incoming-label">
                            <span class="sd-incoming-title">Investment requests</span>
                            <span class="sd-incoming-sub">Across all your programmes</span>
                        </span>
                    </span>
                    <span class="sd-incoming-right">
                        <span class="sd-incoming-count" id="sdIncomingReqCount"><?= (int)$globalIncomingRequestCount ?></span>
                        <i class="fa-solid fa-chevron-right sd-incoming-chev"></i>
                    </span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="sd-stats-grid">
        <div class="sd-stat-card">
            <div class="sd-card-label">Revenue</div>
            <div class="sd-card-value">
                <span class="sd-currency">$</span><span id="sdRevenueValue"><?= esc_h(number_format($revenueAmount, 2)) ?></span>
            </div>
            <div class="sd-card-sub">Your percentage reward across investors.</div>

            <div class="sd-investors-badge" onclick="sdOpenInvestorsView()" role="button" tabindex="0" aria-label="View investors">
                <i class="fa-solid fa-users"></i>
                <span id="sdInvestorCountLabel"><?= (int)$investorCount ?> investor<?= $investorCount === 1 ? '' : 's' ?></span>
            </div>

            <div class="sd-account-meta">
                <div class="sd-account-meta-item">
                    <span class="sd-account-meta-label">Account</span>
                    <span class="sd-account-meta-value" id="sdAccountLogin"><?= $hasBroker ? esc_h($programme['login']) : 'N/A' ?></span>
                </div>
                <div class="sd-account-meta-item">
                    <span class="sd-account-meta-label">Server</span>
                    <span class="sd-account-meta-value" id="sdAccountServer"><?= $hasBroker ? esc_h($programme['server']) : 'N/A' ?></span>
                </div>
            </div>
        </div>

        <div class="sd-stat-card">
            <div class="sd-card-label">Profit Split</div>
            <div class="sd-card-value">
                <span id="sdProfitSplitValue"><?= esc_h(number_format($developerPercentage, 2)) ?></span><span style="font-size:1.2rem;">%</span>
            </div>
            <div class="sd-card-sub">Your share of each investor's profit.</div>
        </div>
    </div>

    <div class="sd-tabs">
        <button class="sd-tab active" data-tab="trades">Trades</button>
        <button class="sd-tab" data-tab="configuration">Configuration</button>
    </div>

    <div class="sd-panel active" id="sd-tab-trades">
        <div class="sd-card">
            <div class="sd-card-head">
                <div>
                    <div class="sd-card-title">Trades</div>
                    <div class="sd-card-desc">Trades generated from this programme.</div>
                </div>
            </div>
            <div id="sdTradesContainer">
                <?php if (!$trades): ?>
                    <div class="sd-empty"><i class="fa-solid fa-chart-simple"></i><strong>No trades recorded yet</strong><p>Trades will appear here once they are generated.</p></div>
                <?php else: ?>
                    <div class="sd-trades-list">
                        <?php foreach ($trades as $t): ?>
                            <?php
                                $modifyVal = (string)($t['modify_trade'] ?? 'keep-running');
                                if (!in_array($modifyVal, ['keep-running','breakeven','close'], true)) $modifyVal = 'keep-running';
                            ?>
                            <div class="sd-trade-row" data-trade-id="<?= (int)$t['id'] ?>">
                                <div><div class="sd-t-label">Symbol</div><b><?= esc_h((string)$t['symbol']) ?> · <?= esc_h($t['timeframe']) ?></b></div>
                                <div><div class="sd-t-label">Entry</div><b><?= esc_h(rtrim(rtrim(number_format((float)$t['entry'], 5), '0'), '.')) ?></b></div>
                                <div><div class="sd-t-label">SL / TP</div><b><?= esc_h(rtrim(rtrim(number_format((float)$t['exit'], 5), '0'), '.')) ?> / <?= $t['target'] !== null ? esc_h(rtrim(rtrim(number_format((float)$t['target'], 5), '0'), '.')) : '—' ?></b></div>
                                <div><div class="sd-t-label">R:R</div><b>1:<?= esc_h(number_format((float)$t['risk_reward'], 2)) ?></b></div>
                                <div>
                                    <div class="sd-t-label">Modify trade</div>
                                    <select class="sd-modify-select" data-trade-id="<?= (int)$t['id'] ?>" data-mode="<?= esc_h($modifyVal) ?>">
                                        <option value="keep-running" <?= $modifyVal === 'keep-running' ? 'selected' : '' ?>>Keep running</option>
                                        <option value="breakeven"    <?= $modifyVal === 'breakeven'    ? 'selected' : '' ?>>Breakeven</option>
                                        <option value="close"        <?= $modifyVal === 'close'        ? 'selected' : '' ?>>Close</option>
                                    </select>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sd-panel" id="sd-tab-configuration">
        <div class="sd-card">
            <div class="sd-card-head">
                <div>
                    <div class="sd-card-title">Programme Status</div>
                    <div class="sd-card-desc">Programme-specific configuration and testing status.</div>
                </div>
                <button class="sd-btn ghost" onclick="sdOpenModal('sdSettingsModal')"><i class="fa-solid fa-sliders"></i> Settings</button>
            </div>
            <div id="sdProgrammeStatusContainer">
                <div class="sd-status-grid">
                    <div class="sd-status-tile">
                        <div class="sd-status-tile-label"><i class="fa-solid fa-building-columns"></i> Broker</div>
                        <div class="sd-status-tile-value"><?= $hasBroker ? esc_h($programme['broker']) : '—' ?></div>
                    </div>
                    <div class="sd-status-tile">
                        <div class="sd-status-tile-label"><i class="fa-solid fa-coins"></i> Symbols</div>
                        <div class="sd-status-tile-value">
                            <?php if (!empty($marketSettings['symbols'])): foreach ($marketSettings['symbols'] as $sym): ?>
                                <span class="sd-chip"><?= esc_h($sym) ?></span>
                            <?php endforeach; else: ?>
                                No symbols selected
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sd-status-tile">
                        <div class="sd-status-tile-label"><i class="fa-solid fa-clock"></i> Timeframes</div>
                        <div class="sd-status-tile-value">
                            <?php if (!empty($marketSettings['timeframes'])): foreach ($marketSettings['timeframes'] as $tf): ?>
                                <span class="sd-chip"><?= esc_h($tf) ?></span>
                            <?php endforeach; else: ?>
                                No timeframes selected
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="sd-empty" style="margin-top:40px;">
        <i class="fa-solid fa-diagram-project"></i>
        <strong>No programme selected</strong>
        <p>Choose or create a programme from the header menu.</p>
    </div>
<?php endif; ?>

</div>

<div class="sd-modal" id="sdSettingsModal">
    <div class="sd-modal-card">
        <h3>Programme Settings</h3>
        <p class="sd-sub">These settings apply only to the selected programme.</p>

        <div class="sd-field">
            <label>Visibility</label>
            <select id="sdVisSelect">
                <option value="1" <?= $isPublic ? 'selected' : '' ?>>Public</option>
                <option value="0" <?= !$isPublic ? 'selected' : '' ?>>Private</option>
            </select>
        </div>

        <div class="sd-fold-section">
            <button type="button" class="sd-fold-toggle" data-target="sdFoldTimeframes"><span>Select Timeframes</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="sd-fold-body" id="sdFoldTimeframes">
                <div class="sd-check-list">
                    <?php foreach ($availableTimeframes as $tf): ?>
                    <label class="sd-check-row">
                        <input type="checkbox" value="<?= esc_h($tf) ?>" class="sd-settings-tf-cb" <?= in_array($tf, $marketSettings['timeframes'], true) ? 'checked' : '' ?>>
                        <span><?= esc_h($tf) ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($availableTimeframes)): ?><p class="sd-sub">No timeframes available.</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="sd-fold-section">
            <button type="button" class="sd-fold-toggle" data-target="sdFoldSymbols"><span>Select Symbols</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="sd-fold-body" id="sdFoldSymbols">
                <div class="sd-check-list">
                    <?php foreach ($allBrokerSymbols as $sym): ?>
                    <label class="sd-check-row">
                        <input type="checkbox" value="<?= esc_h($sym) ?>" class="sd-settings-symbol-cb" <?= in_array($sym, $marketSettings['symbols'], true) ? 'checked' : '' ?>>
                        <span><?= esc_h($sym) ?></span>
                    </label>
                    <?php endforeach; ?>
                    <?php if (empty($allBrokerSymbols)): ?><p class="sd-sub">No symbols available.</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="sd-fold-section">
            <button type="button" class="sd-fold-toggle" data-target="sdFoldRequirements"><span>Set Requirements</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="sd-fold-body" id="sdFoldRequirements">
                <div class="sd-req-grid">
                    <div class="sd-field">
                        <label>Contract Duration (days)</label>
                        <input id="sdReqContract" type="number" min="<?= (int)$serverContractDuration ?>" step="1" value="<?= (int)$contractDuration ?>">
                        <p class="sd-sub" style="margin-top:4px">Minimum: <?= (int)$serverContractDuration ?> days</p>
                    </div>
                    <div class="sd-field">
                        <label>Your Percentage (%)</label>
                        <input id="sdRevInput" type="number" min="0" max="100" step="0.01" value="<?= esc_h(number_format($developerPercentage,2,'.','')) ?>">
                        <p class="sd-sub" style="margin-top:4px">Your share of each investor's profit.</p>
                    </div>
                    <div class="sd-field">
                        <label>Minimum Investment Amount</label>
                        <input id="sdReqMinInv" type="number" min="<?= esc_h(number_format($serverMinBrokerBalance,2,'.','')) ?>" step="0.01" value="<?= esc_h(number_format($minInvestment,2,'.','')) ?>">
                        <p class="sd-sub" style="margin-top:4px">Minimum: $<?= esc_h(number_format($serverMinBrokerBalance,2)) ?></p>
                    </div>
                    <div class="sd-field">
                        <label>Maximum Investment Amount</label>
                        <input id="sdReqMaxInv" type="number" min="0" step="0.01" value="<?= esc_h(number_format($maxInvestment,2,'.','')) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div id="sdSettingsErr" class="sd-err"></div>

        <div style="display:grid;gap:9px;margin-top:10px;">
            <button class="sd-btn primary" id="sdSaveSettingsBtn" style="width:100%;">Save Settings</button>
            <button class="sd-btn ghost" onclick="sdCloseModal('sdSettingsModal')" style="width:100%;">Cancel</button>
        </div>
    </div>
</div>

<!-- GLOBAL REQUEST PICKER MODAL -->
<div class="sd-modal" id="sdRequestPickerModal">
    <div class="sd-picker-card">
        <h3>Investment Requests</h3>
        <p class="sd-sub">Programmes with pending requests.</p>
        <div class="sd-picker-list" id="sdRequestPickerList">
            <div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading programmes…</p></div>
        </div>
        <div style="display:grid;gap:9px;margin-top:16px;">
            <button class="sd-btn ghost" onclick="sdCloseModal('sdRequestPickerModal')" style="width:100%;">Close</button>
        </div>
    </div>
</div>

<div class="sd-investors-view" id="sdInvestorsView">
    <div class="sd-investors-view-header">
        <button class="sd-investors-view-back" onclick="sdCloseInvestorsView()" aria-label="Close"><i class="fa-solid fa-arrow-left"></i></button>
        <div class="sd-investors-view-title">Investors</div>
    </div>
    <div class="sd-investors-view-body" id="sdInvestorsViewBody">
        <div class="sd-inv-tabs">
            <button class="sd-inv-tab active" data-inv-tab="active" onclick="sdSwitchInvTab('active')">Active Investors</button>
            <button class="sd-inv-tab" data-inv-tab="inactive" onclick="sdSwitchInvTab('inactive')">Inactive Investors</button>
            <button class="sd-inv-tab" data-inv-tab="requestors" onclick="sdSwitchInvTab('requestors')">Investment Requestors</button>
            <button class="sd-inv-tab" data-inv-tab="closed" onclick="sdSwitchInvTab('closed')">Closed Deals</button>
        </div>
        <div id="sdInvPanelActive" class="sd-inv-panel active">
            <div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading investors…</p></div>
        </div>
        <div id="sdInvPanelInactive" class="sd-inv-panel">
            <div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading inactive investors…</p></div>
        </div>
        <div id="sdInvPanelRequestors" class="sd-inv-panel">
            <div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading requestors…</p></div>
        </div>
        <div id="sdInvPanelClosed" class="sd-inv-panel">
            <div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading closed deals…</p></div>
        </div>
    </div>
</div>

<div class="sd-confirm-modal" id="sdConfirmModal">
    <div class="sd-confirm-card">
        <h3 id="sdConfirmTitle">Confirm Action</h3>
        <p id="sdConfirmMessage">Are you sure?</p>
        <div class="sd-confirm-actions">
            <button class="sd-btn primary" id="sdConfirmYes" style="width:100%;">Yes, Confirm</button>
            <button class="sd-btn ghost" onclick="sdCloseConfirm()" style="width:100%;">Cancel</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: open ? 'harvhubModalOpen' : 'harvhubModalClose'
                }, '*');
            }
        } catch (e) {}
    }

    function anyOverlayOpen() {
        return !!document.querySelector('.sd-modal.open, .sd-investors-view.open, .sd-confirm-modal.open');
    }
    function lockBodyScroll() {
        document.body.classList.add('sd-scroll-locked');
    }
    function unlockBodyScroll() {
        if (!anyOverlayOpen()) {
            document.body.classList.remove('sd-scroll-locked');
        }
    }

    window.harvhubGoToTab = function (tab) {
        try { if (window.parent && window.parent !== window) { window.parent.postMessage({ type: 'switchTab', tab: tab }, '*'); return; } } catch (e) {}
        window.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);
    };

    document.querySelectorAll('.sd-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            var target = t.dataset.tab;
            document.querySelectorAll('.sd-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            document.querySelectorAll('.sd-panel').forEach(function (x) { x.classList.toggle('active', x.id === 'sd-tab-' + target); });
        });
    });

    window.sdOpenModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    window.sdCloseModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        if (!anyOverlayOpen()) {
            unlockBodyScroll();
            notifyParentModal(false);
        }
    };

    document.querySelectorAll('.sd-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) sdCloseModal(m.id);
        });
    });

    document.querySelectorAll('.sd-fold-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.getElementById(btn.dataset.target);
            if (!body) return;
            var open = body.classList.toggle('open');
            btn.classList.toggle('open', open);
        });
    });

    function post(data, cb) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        fetch('signals_dashboard.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) { cb(d || {}); })
            .catch(function () { cb({ success: false }); });
    }

    function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function fmtMoney(v) { var n = parseFloat(v) || 0; return '$' + n.toFixed(2); }

    // ---------- LIVE UPDATE ENGINE ----------
    var liveStateInterval = null;
    var liveStateRunning = false;
    var liveFailCount = 0;
    var LIVE_BASE_INTERVAL = 1000;
    var LIVE_MAX_INTERVAL = 15000;
    var LIVE_STATE_LAST = null;

    function pulse(el) {
        if (!el) return;
        el.classList.remove('sd-live-pulse');
        void el.offsetWidth;
        el.classList.add('sd-live-pulse');
        setTimeout(function () { el.classList.remove('sd-live-pulse'); }, 1300);
    }

    function updateText(el, newText, doPulse) {
        if (!el) return false;
        var t = String(newText == null ? '' : newText);
        if (el.textContent !== t) {
            el.textContent = t;
            if (doPulse) pulse(el);
            return true;
        }
        return false;
    }

    function updateHtml(el, newHtml, doPulse) {
        if (!el) return false;
        if (el.innerHTML !== newHtml) {
            el.innerHTML = newHtml;
            if (doPulse) pulse(el);
            return true;
        }
        return false;
    }

    function applyLiveState(data) {
        if (!data || !data.success) return;

        // Revenue
        var revEl = document.getElementById('sdRevenueValue');
        if (revEl) {
            var newRev = parseFloat(data.revenue_amount || 0).toFixed(2);
            updateText(revEl, newRev, true);
        }

        // Investor count
        var invLabel = document.getElementById('sdInvestorCountLabel');
        if (invLabel) {
            var c = parseInt(data.investor_count, 10) || 0;
            updateText(invLabel, c + ' investor' + (c === 1 ? '' : 's'), true);
        }

        // Profit split
        var psEl = document.getElementById('sdProfitSplitValue');
        if (psEl) {
            var newPct = parseFloat(data.developer_percentage || 0).toFixed(2);
            updateText(psEl, newPct, true);
        }

        // Account meta
        var loginEl = document.getElementById('sdAccountLogin');
        if (loginEl) {
            updateText(loginEl, data.has_broker ? (data.broker_login || '') : 'N/A', false);
        }
        var serverEl = document.getElementById('sdAccountServer');
        if (serverEl) {
            updateText(serverEl, data.has_broker ? (data.broker_server || '') : 'N/A', false);
        }

        // Greeting sub text
        var greetSub = document.getElementById('sdGreetSub');
        if (greetSub && data.greeting_sub_html !== undefined) {
            updateText(greetSub, data.greeting_sub_html, false);
        }

        // Greeting banners
        var bannersEl = document.getElementById('sdGreetingBanners');
        if (bannersEl && data.greeting_banners_html !== undefined) {
            updateHtml(bannersEl, data.greeting_banners_html, false);
        }

        // Global incoming request count
        var reqCountEl = document.getElementById('sdIncomingReqCount');
        if (reqCountEl && data.global_incoming_request_count !== undefined) {
            updateText(reqCountEl, String(data.global_incoming_request_count || 0), true);
        }

        // Programme status
        var statusEl = document.getElementById('sdProgrammeStatusContainer');
        if (statusEl && data.programme_status_html !== undefined) {
            updateHtml(statusEl, data.programme_status_html, false);
        }

        // Trades
        var tradesEl = document.getElementById('sdTradesContainer');
        if (tradesEl && data.trades_html !== undefined) {
            var prevCount = (LIVE_STATE_LAST && LIVE_STATE_LAST.trades) ? LIVE_STATE_LAST.trades.length : -1;
            var newCount = (data.trades || []).length;
            var prevFirstId = (LIVE_STATE_LAST && LIVE_STATE_LAST.trades && LIVE_STATE_LAST.trades[0]) ? LIVE_STATE_LAST.trades[0].id : null;
            var newFirstId = (data.trades && data.trades[0]) ? data.trades[0].id : null;

            if (prevCount !== newCount || prevFirstId !== newFirstId) {
                updateHtml(tradesEl, data.trades_html, true);
                bindModifySelects();
            }
        }

        LIVE_STATE_LAST = data;
    }

    function fetchLiveState() {
        if (!liveStateRunning) return;
        post({ action: 'get_live_state' }, function (d) {
            if (d && d.success) {
                liveFailCount = 0;
                applyLiveState(d);
            } else {
                liveFailCount++;
            }
            scheduleNextLiveState();
        });
    }

    function scheduleNextLiveState() {
        if (!liveStateRunning) return;
        if (liveStateInterval) clearTimeout(liveStateInterval);
        var delay = LIVE_BASE_INTERVAL;
        if (liveFailCount === 1) delay = 3000;
        else if (liveFailCount === 2) delay = 5000;
        else if (liveFailCount >= 3) delay = LIVE_MAX_INTERVAL;
        liveStateInterval = setTimeout(fetchLiveState, delay);
    }

    function startLiveState() {
        liveStateRunning = true;
        liveFailCount = 0;
        if (liveStateInterval) clearTimeout(liveStateInterval);
        fetchLiveState();
    }

    function stopLiveState() {
        liveStateRunning = false;
        if (liveStateInterval) { clearTimeout(liveStateInterval); liveStateInterval = null; }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stopLiveState();
        else startLiveState();
    });
    window.addEventListener('focus', function () {
        if (liveStateRunning) fetchLiveState();
    });
    window.addEventListener('beforeunload', function () { stopLiveState(); });

    var HAS_PROGRAMME = <?= $programme ? 'true' : 'false' ?>;
    if (HAS_PROGRAMME) startLiveState();

    // ---------- GLOBAL REQUEST PICKER ----------
    window.sdOpenRequestPicker = function () {
        var list = document.getElementById('sdRequestPickerList');
        list.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading programmes…</p></div>';
        sdOpenModal('sdRequestPickerModal');

        post({ action: 'get_request_programmes' }, function (d) {
            if (!d || !d.success || !d.programmes || !d.programmes.length) {
                list.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-hand-holding-dollar"></i><strong>No pending requests</strong><p>Requests will appear here when investors apply.</p></div>';
                return;
            }

            var html = '';
            d.programmes.forEach(function (p) {
                var cls = 'sd-picker-item' + (p.is_current ? ' is-current' : '');
                html += '<button type="button" class="' + cls + '" data-programme-id="' + p.programme_id + '" data-is-current="' + (p.is_current ? '1' : '0') + '">';
                html +=   '<span class="sd-picker-item-left">';
                html +=     '<span class="sd-picker-item-title">' + escapeHtml(p.programme_name || 'Unnamed') + '</span>';
                html +=     '<span class="sd-picker-item-sub">' + (p.is_current ? 'Current programme' : 'Tap to switch programme') + '</span>';
                html +=   '</span>';
                html +=   '<span class="sd-picker-item-right">';
                html +=     '<span class="sd-picker-item-count">' + p.pending_count + '</span>';
                html +=     '<i class="fa-solid fa-chevron-right sd-picker-item-chev"></i>';
                html +=   '</span>';
                html += '</button>';
            });
            list.innerHTML = html;

            list.querySelectorAll('.sd-picker-item').forEach(function (el) {
                el.addEventListener('click', function () {
                    var pid = parseInt(el.getAttribute('data-programme-id'), 10) || 0;
                    var isCurrent = el.getAttribute('data-is-current') === '1';

                    sdCloseModal('sdRequestPickerModal');

                    if (isCurrent) {
                        setTimeout(function () {
                            sdOpenInvestorsView();
                            setTimeout(function () { sdSwitchInvTab('requestors'); }, 30);
                        }, 60);
                    } else {
                        post({ action: 'switch_programme', programme_id: pid }, function (res) {
                            if (res && res.success) {
                                try {
                                    if (window.parent && window.parent !== window) {
                                        window.parent.postMessage({ type: 'switchTab', tab: 'signals' }, '*');
                                    }
                                } catch (e) {}
                                window.location.reload();
                            } else {
                                alert((res && res.message) || 'Failed to switch programme.');
                            }
                        });
                    }
                });
            });
        });
    };

    // ---------- Investors view ----------
    var _currentInvestorsData = null;

    function renderInvestorCard(inv, inactive) {
        var initial = inv.initial && inv.initial.length ? inv.initial : (String(inv.name || 'I').charAt(0).toUpperCase());
        var cls = 'sd-inv-card' + (inactive ? ' is-inactive' : '');
        var html = '';
        html += '<div class="' + cls + '">';
        html +=   '<div class="sd-inv-avatar">' + escapeHtml(initial) + '</div>';
        html +=   '<div class="sd-inv-info">';
        html +=     '<div class="sd-inv-username">' + escapeHtml(inv.name || 'Investor') + '</div>';
        html +=     '<div class="sd-inv-stat">';
        html +=       '<span class="sd-inv-stat-key">Revenue from investor</span>';
        html +=       '<span class="sd-inv-stat-val money">' + fmtMoney(inv.trader_revenue) + '</span>';
        html +=     '</div>';
        if (inv.start_date) {
            html +=   '<div class="sd-inv-stat">';
            html +=     '<span class="sd-inv-stat-key">Started</span>';
            html +=     '<span class="sd-inv-stat-val">' + escapeHtml(inv.start_date) + '</span>';
            html +=   '</div>';
        }
        if (inv.end_date) {
            html +=   '<div class="sd-inv-stat">';
            html +=     '<span class="sd-inv-stat-key">Ending</span>';
            html +=     '<span class="sd-inv-stat-val">' + escapeHtml(inv.end_date) + '</span>';
            html +=   '</div>';
        }
        if (inv.end_date) {
            html +=   '<div class="sd-inv-stat">';
            html +=     '<span class="sd-inv-stat-key">Days Left</span>';
            var dl = inv.days_left !== null ? inv.days_left : 0;
            if (inactive || dl <= 0) {
                html +=   '<span class="sd-inv-stat-val ended">Ended</span>';
            } else {
                html +=   '<span class="sd-inv-stat-val">' + dl + ' day' + (dl === 1 ? '' : 's') + ' left</span>';
            }
            html +=   '</div>';
        }
        html +=   '</div>';
        html += '</div>';
        return html;
    }

    window.sdOpenInvestorsView = function () {
        var view = document.getElementById('sdInvestorsView');
        if (!view) return;
        view.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);

        var activePanel = document.getElementById('sdInvPanelActive');
        var inactivePanel = document.getElementById('sdInvPanelInactive');
        var requestorsPanel = document.getElementById('sdInvPanelRequestors');
        var closedPanel = document.getElementById('sdInvPanelClosed');

        activePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading investors…</p></div>';
        inactivePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading inactive investors…</p></div>';
        requestorsPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading requestors…</p></div>';
        closedPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading closed deals…</p></div>';

        sdSwitchInvTab('active');

        post({ action: 'get_investors' }, function (d) {
            if (!d.success) {
                activePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load investors.') + '</p></div>';
                inactivePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load inactive investors.') + '</p></div>';
                requestorsPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load requestors.') + '</p></div>';
                closedPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load closed deals.') + '</p></div>';
                return;
            }

            _currentInvestorsData = d;

            // ---------- ACTIVE INVESTORS (contract currently running) ----------
            if (!d.active || !d.active.length) {
                activePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-users"></i><strong>No active investors yet</strong><p>Investors with a currently running contract will appear here.</p></div>';
            } else {
                var html = '';
                d.active.forEach(function (inv) { html += renderInvestorCard(inv, false); });
                activePanel.innerHTML = html;
            }

            // ---------- INACTIVE INVESTORS (contract has ended) ----------
            if (!d.inactive || !d.inactive.length) {
                inactivePanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-user-clock"></i><strong>No inactive investors yet</strong><p>Investors whose contract has ended will appear here.</p></div>';
            } else {
                var ihtml = '';
                d.inactive.forEach(function (inv) { ihtml += renderInvestorCard(inv, true); });
                inactivePanel.innerHTML = ihtml;
            }

            // ---------- REQUESTORS ----------
            if (!d.requestors || !d.requestors.length) {
                requestorsPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-hand-holding-dollar"></i><strong>No pending requests</strong><p>Investment requests will appear here.</p></div>';
            } else {
                var rhtml = '';
                d.requestors.forEach(function (req) {
                    var initial = req.initial && req.initial.length ? req.initial : (String(req.name || 'R').charAt(0).toUpperCase());
                    rhtml += '<div class="sd-req-card" data-request-id="' + req.request_id + '">';
                    rhtml +=   '<div class="sd-req-avatar">' + escapeHtml(initial) + '</div>';
                    rhtml +=   '<div class="sd-req-info">';
                    rhtml +=     '<div class="sd-req-username">' + escapeHtml(req.name || 'Requestor') + '</div>';
                    rhtml +=     '<div class="sd-req-meta">Requested ' + escapeHtml(req.created_at || '') + '</div>';
                    rhtml +=   '</div>';
                    rhtml +=   '<select class="sd-req-select" data-request-id="' + req.request_id + '" onchange="sdHandleRequestAction(this)">';
                    rhtml +=     '<option value="">Action</option>';
                    rhtml +=     '<option value="accept">Accept</option>';
                    rhtml +=     '<option value="reject">Reject</option>';
                    rhtml +=   '</select>';
                    rhtml += '</div>';
                });
                requestorsPanel.innerHTML = rhtml;
            }

            // ---------- CLOSED DEALS ----------
            if (!d.closed || !d.closed.length) {
                closedPanel.innerHTML = '<div class="sd-empty"><i class="fa-solid fa-box-archive"></i><strong>No closed deals yet</strong><p>Ended contracts will appear here grouped by date range.</p></div>';
            } else {
                var chtml = '';
                d.closed.forEach(function (group) {
                    chtml += '<div class="sd-closed-group">';
                    chtml +=   '<div class="sd-closed-range">' + escapeHtml(group.range) + '</div>';
                    group.items.forEach(function (it) {
                        var initial = it.initial && it.initial.length ? it.initial : (String(it.name || 'I').charAt(0).toUpperCase());
                        chtml += '<div class="sd-closed-item">';
                        chtml +=   '<div class="sd-closed-avatar">' + escapeHtml(initial) + '</div>';
                        chtml +=   '<div class="sd-closed-body">';
                        chtml +=     '<div class="sd-closed-name">' + escapeHtml(it.name || 'Investor') + '</div>';
                        chtml +=     '<div class="sd-closed-row">';
                        chtml +=       '<span class="sd-closed-key">Revenue from investor</span>';
                        chtml +=       '<span class="sd-closed-val money">' + fmtMoney(it.investor_profit) + '</span>';
                        chtml +=     '</div>';
                        chtml +=     '<div class="sd-closed-row">';
                        chtml +=       '<span class="sd-closed-key">Investor payment status</span>';
                        chtml +=       '<span class="sd-closed-val' + (it.investor_payment_status ? '' : ' muted') + '">' + escapeHtml(it.investor_payment_status || '—') + '</span>';
                        chtml +=     '</div>';
                        chtml +=     '<div class="sd-closed-row">';
                        chtml +=       '<span class="sd-closed-key">Server payment status</span>';
                        chtml +=       '<span class="sd-closed-val' + (it.server_payment_status ? '' : ' muted') + '">' + escapeHtml(it.server_payment_status || '—') + '</span>';
                        chtml +=     '</div>';
                        chtml +=   '</div>';
                        chtml += '</div>';
                    });
                    chtml += '</div>';
                });
                closedPanel.innerHTML = chtml;
            }
        });
    };

    window.sdCloseInvestorsView = function () {
        var view = document.getElementById('sdInvestorsView');
        if (!view) return;
        view.classList.remove('open');
        if (!anyOverlayOpen()) {
            unlockBodyScroll();
            notifyParentModal(false);
        }
    };

    window.sdSwitchInvTab = function (tab) {
        document.querySelectorAll('.sd-inv-tab').forEach(function (t) {
            t.classList.toggle('active', t.dataset.invTab === tab);
        });
        document.querySelectorAll('.sd-inv-panel').forEach(function (p) {
            p.classList.remove('active');
        });
        if (tab === 'active') {
            document.getElementById('sdInvPanelActive').classList.add('active');
        } else if (tab === 'inactive') {
            document.getElementById('sdInvPanelInactive').classList.add('active');
        } else if (tab === 'requestors') {
            document.getElementById('sdInvPanelRequestors').classList.add('active');
        } else if (tab === 'closed') {
            document.getElementById('sdInvPanelClosed').classList.add('active');
        }
    };

    var _pendingRequestId = null;
    var _pendingRequestAction = null;
    var _pendingSelectEl = null;

    window.sdHandleRequestAction = function (selectEl) {
        var requestId = selectEl.getAttribute('data-request-id');
        var action = selectEl.value;
        if (!requestId || !action) return;

        _pendingRequestId = requestId;
        _pendingRequestAction = action;
        _pendingSelectEl = selectEl;

        var title = action === 'accept' ? 'Accept Request' : 'Reject Request';
        var msg = action === 'accept'
            ? 'Are you sure you want to accept this investment request? The investor will be added to this programme.'
            : 'Are you sure you want to reject this investment request?';

        document.getElementById('sdConfirmTitle').textContent = title;
        document.getElementById('sdConfirmMessage').textContent = msg;
        document.getElementById('sdConfirmYes').textContent = action === 'accept' ? 'Yes, Accept' : 'Yes, Reject';
        document.getElementById('sdConfirmModal').classList.add('open');
        lockBodyScroll();
    };

    window.sdCloseConfirm = function () {
        document.getElementById('sdConfirmModal').classList.remove('open');
        if (_pendingSelectEl) {
            _pendingSelectEl.value = '';
            _pendingSelectEl.disabled = false;
        }
        _pendingRequestId = null;
        _pendingRequestAction = null;
        _pendingSelectEl = null;
        if (!anyOverlayOpen()) {
            unlockBodyScroll();
            notifyParentModal(false);
        }
    };

    document.getElementById('sdConfirmYes').addEventListener('click', function () {
        if (!_pendingRequestId || !_pendingRequestAction) return;
        var requestId = _pendingRequestId;
        var action = _pendingRequestAction;
        var selectEl = _pendingSelectEl;

        document.getElementById('sdConfirmModal').classList.remove('open');
        if (selectEl) selectEl.disabled = true;

        post({ action: 'update_request_status', request_id: requestId, new_status: action }, function (d) {
            if (d.success) {
                sdOpenInvestorsView();
                if (liveStateRunning) fetchLiveState();
            } else {
                alert(d.message || 'Failed to update request.');
                if (selectEl) {
                    selectEl.value = '';
                    selectEl.disabled = false;
                }
                if (!anyOverlayOpen()) {
                    unlockBodyScroll();
                    notifyParentModal(false);
                }
            }
            _pendingRequestId = null;
            _pendingRequestAction = null;
            _pendingSelectEl = null;
        });
    });

    // ---------- Modify trade (re-bindable) ----------
    function bindModifySelects() {
        document.querySelectorAll('.sd-modify-select').forEach(function (sel) {
            if (sel.dataset.sdBound === '1') return;
            sel.dataset.sdBound = '1';
            sel.addEventListener('change', function () {
                var tradeId = parseInt(sel.getAttribute('data-trade-id'), 10) || 0;
                var mode    = sel.value;
                if (tradeId <= 0) return;

                sel.disabled = true;
                post({ action: 'modify_trade', trade_id: tradeId, modify_trade: mode }, function (d) {
                    sel.disabled = false;
                    if (d && d.success) {
                        sel.setAttribute('data-mode', mode);
                    } else {
                        alert((d && d.message) ? d.message : 'Failed to update trade.');
                    }
                });
            });
        });
    }
    bindModifySelects();

    // ---------- Save settings ----------
    var saveBtn = document.getElementById('sdSaveSettingsBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var er = document.getElementById('sdSettingsErr');
            er.style.display = 'none';

            var symbols = []; document.querySelectorAll('.sd-settings-symbol-cb:checked').forEach(function (cb) { symbols.push(cb.value); });
            var tfs = []; document.querySelectorAll('.sd-settings-tf-cb:checked').forEach(function (cb) { tfs.push(cb.value); });

            saveBtn.disabled = true;
            var oldText = saveBtn.textContent;
            saveBtn.textContent = 'Saving…';

            post({ action: 'save_visibility', visibility: document.getElementById('sdVisSelect').value }, function (d1) {
                if (!d1.success) { showErr(d1.message); return; }
                post({ action: 'save_symbols_timeframes', symbols: symbols.join(','), timeframes: tfs.join(',') }, function (d2) {
                    if (!d2.success) { showErr(d2.message); return; }
                    post({
                        action: 'save_requirements',
                        contract_duration: document.getElementById('sdReqContract').value,
                        developer_percentage: document.getElementById('sdRevInput').value,
                        minimum_investment_amount: document.getElementById('sdReqMinInv').value,
                        maximum_investment_amount: document.getElementById('sdReqMaxInv').value
                    }, function (d3) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = oldText;
                        if (!d3.success) { showErr(d3.message); return; }
                        sdCloseModal('sdSettingsModal');
                        if (liveStateRunning) fetchLiveState();
                    });
                });
            });

            function showErr(msg) {
                saveBtn.disabled = false;
                saveBtn.textContent = oldText;
                er.textContent = msg || 'Failed to save.';
                er.style.display = 'block';
            }
        });
    }

    // ---------- Theme ----------
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
})();
</script>

</body>
</html>