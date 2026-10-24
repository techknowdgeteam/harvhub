<?php
// programme_investors.php — standalone programme investors dashboard
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
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { unset($_SESSION['user_email'], $_SESSION['auth_role']); header("Location: index.php?role=developer"); exit; }

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = !empty($user['dark_mode']);

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

/**
 * Sync investor_profit from harvhub.profitandloss into the deal row.
 *
 * Joins harvhub on BOTH id and sub_account_id so we read the correct
 * sub-account's P&L, not an arbitrary sibling row.
 */
function syncInvestorProfitsIntoDeals($pdo) {
    try {
        $q = $pdo->query("
            SELECT d.contract_id, d.investor_id, d.investor_sub_account_id,
                   d.accepted_at, d.contract_duration,
                   h.profitandloss
            FROM programme_revenue_deals d
            LEFT JOIN harvhub h
                ON h.id = d.investor_id
               AND (
                    (d.investor_sub_account_id IS NOT NULL AND d.investor_sub_account_id > 0
                     AND h.sub_account_id = d.investor_sub_account_id)
                    OR
                    ((d.investor_sub_account_id IS NULL OR d.investor_sub_account_id = 0)
                     AND h.is_main_account = 1)
               )
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
        if ($diff <= 0) continue;

        $pnl = (float)($r['profitandloss'] ?? 0);
        try {
            $u = $pdo->prepare("UPDATE programme_revenue_deals SET investor_profit = ? WHERE contract_id = ?");
            $u->execute([$pnl, (int)$r['contract_id']]);
        } catch (Throwable $e) {}
    }
}

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

function normalizeInterestRiskRewardType($raw) {
    $s = trim((string)$raw);
    if ($s === '') return '—';
    $s = str_replace('_', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function formatModifyTradeLabel($raw) {
    $s = trim((string)$raw);
    if ($s === '') return '—';
    $s = str_replace(['_', '-'], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return ucwords($s);
}

// ============================================================
// >>> SYNC ENGINE
// ============================================================

/**
 * Get ACTIVE investors for a programme.
 *
 * IDENTICAL to signals_dashboard.php:
 *  - joins harvhub on id AND sub_account_id
 *  - requires harvhub.account_contract_status = 'contract_active'
 *  - requires the deal's own accepted_at + contract_duration window still open
 *  - returns (investor_id, sub_account_id) pairs
 */
function getActiveInvestorsForProgramme($pdo, $programmeId) {
    $out = [];
    try {
        $q = $pdo->prepare("
            SELECT DISTINCT
                d.investor_id,
                d.investor_sub_account_id
            FROM programme_revenue_deals d
            INNER JOIN harvhub h
                    ON h.id = d.investor_id
                   AND h.sub_account_id = d.investor_sub_account_id
            WHERE d.programme_id = ?
              AND d.investor_id > 0
              AND h.account_contract_status = 'contract_active'
        ");
        $q->execute([$programmeId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $seen = [];

        foreach ($rows as $r) {
            $invId = (int)$r['investor_id'];
            $subId = (int)($r['investor_sub_account_id'] ?? 0);
            if ($invId <= 0 || $subId <= 0) continue;

            $key = $invId . '|' . $subId;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = [
                'investor_id'    => $invId,
                'sub_account_id' => $subId,
            ];
        }
    } catch (Throwable $e) {}
    return $out;
}

function syncProgrammeTradesToInvestors($pdo, $userId, $programmeId) {
    try {
        // 1) Programme-owned trades
        $pq = $pdo->prepare("
            SELECT *
            FROM programme_trades
            WHERE userid = ? AND programmeid = ? AND investor_id IS NULL
        ");
        $pq->execute([$userId, $programmeId]);
        $programmeTrades = $pq->fetchAll(PDO::FETCH_ASSOC);

        $programmeByTradeId = [];
        foreach ($programmeTrades as $pt) {
            $tid = (string)($pt['tradeid'] ?? '');
            if ($tid === '') continue;
            $programmeByTradeId[$tid] = $pt;
        }

        // 2) Active investors — pair based
        $activeInvestors = getActiveInvestorsForProgramme($pdo, $programmeId);

        $activePairs = [];
        foreach ($activeInvestors as $inv) {
            $invId = (int)$inv['investor_id'];
            $subId = (int)$inv['sub_account_id'];
            if ($invId <= 0 || $subId <= 0) continue;
            $activePairs[$invId . '|' . $subId] = true;
        }
        $activePairs = array_keys($activePairs);

        // 3) Current investor rows
        $iq = $pdo->prepare("
            SELECT id, tradeid, investor_id, investor_sub_account_id,
                   modify_trade, orderstatus, pnl, pnl_risk_reward
            FROM programme_trades
            WHERE userid = ? AND programmeid = ? AND investor_id IS NOT NULL
        ");
        $iq->execute([$userId, $programmeId]);
        $investorRows = $iq->fetchAll(PDO::FETCH_ASSOC);

        $investorIndex = [];
        foreach ($investorRows as $ir) {
            $tid   = (string)($ir['tradeid'] ?? '');
            $invId = (int)($ir['investor_id'] ?? 0);
            $subId = (int)($ir['investor_sub_account_id'] ?? 0);
            $investorIndex[$tid . '|' . $invId . '|' . $subId] = $ir;
        }

        // 4) Insert / update
        $insStmt = $pdo->prepare("
            INSERT INTO programme_trades
                (userid, programmeid, investor_id, investor_sub_account_id,
                 tradeid, ordertype, symbol, entry, stoploss, takeprofit,
                 orderstatus, trade_risk_reward, interest_risk_reward, interest_risk_reward_type,
                 pnl, pnl_risk_reward, modify_trade, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($activeInvestors as $inv) {
            foreach ($programmeByTradeId as $tid => $pt) {
                $key = $tid . '|' . $inv['investor_id'] . '|' . $inv['sub_account_id'];
                if (isset($investorIndex[$key])) {
                    $existing = $investorIndex[$key];

                    $progModify = (string)($pt['modify_trade'] ?? '');
                    $invModify  = (string)($existing['modify_trade'] ?? '');
                    $progStatus = (string)($pt['orderstatus'] ?? '');
                    $invStatus  = (string)($existing['orderstatus'] ?? '');
                    $progPnl    = $pt['pnl'] ?? null;
                    $invPnl     = $existing['pnl'] ?? null;
                    $progPrr    = $pt['pnl_risk_reward'] ?? null;
                    $invPrr     = $existing['pnl_risk_reward'] ?? null;

                    if (
                        $progModify !== $invModify ||
                        $progStatus !== $invStatus ||
                        (string)$progPnl !== (string)$invPnl ||
                        (string)$progPrr !== (string)$invPrr
                    ) {
                        try {
                            $u = $pdo->prepare("
                                UPDATE programme_trades
                                SET modify_trade = ?, orderstatus = ?, pnl = ?, pnl_risk_reward = ?
                                WHERE id = ?
                            ");
                            $u->execute([
                                $progModify,
                                $progStatus !== '' ? $progStatus : null,
                                $progPnl,
                                $progPrr,
                                (int)$existing['id']
                            ]);
                        } catch (Throwable $e) {}
                    }
                    continue;
                }

                try {
                    $insStmt->execute([
                        $userId,
                        $programmeId,
                        $inv['investor_id'],
                        $inv['sub_account_id'] ?: null,
                        $tid,
                        $pt['ordertype'] ?? null,
                        $pt['symbol'] ?? null,
                        $pt['entry'] ?? null,
                        $pt['stoploss'] ?? null,
                        $pt['takeprofit'] ?? null,
                        $pt['orderstatus'] ?? null,
                        $pt['trade_risk_reward'] ?? null,
                        $pt['interest_risk_reward'] ?? null,
                        $pt['interest_risk_reward_type'] ?? null,
                        $pt['pnl'] ?? null,
                        $pt['pnl_risk_reward'] ?? null,
                        $pt['modify_trade'] ?? null,
                        $pt['created_at'] ?? date('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable $e) {}
            }
        }

        // 5) HARD DELETE PASS A — trade-level orphan cleanup
        $programmeTradeIds = array_keys($programmeByTradeId);
        if (!empty($programmeTradeIds)) {
            $placeholders = implode(',', array_fill(0, count($programmeTradeIds), '?'));
            $params = array_merge([$userId, $programmeId], $programmeTradeIds);
            try {
                $sql = "
                    DELETE FROM programme_trades
                    WHERE userid = ?
                      AND programmeid = ?
                      AND investor_id IS NOT NULL
                      AND (tradeid IS NULL OR tradeid = '' OR tradeid NOT IN ($placeholders))
                ";
                $d = $pdo->prepare($sql);
                $d->execute($params);
            } catch (Throwable $e) {}
        } else {
            try {
                $d = $pdo->prepare("
                    DELETE FROM programme_trades
                    WHERE userid = ?
                      AND programmeid = ?
                      AND investor_id IS NOT NULL
                ");
                $d->execute([$userId, $programmeId]);
            } catch (Throwable $e) {}
        }

        // 6) HARD DELETE PASS B — pair-based inactive cleanup.
        if (!empty($activePairs)) {
            $pairConditions = [];
            $pairParams = [];
            foreach ($activePairs as $pair) {
                list($pInv, $pSub) = explode('|', $pair);
                $pairConditions[] = "(investor_id = ? AND investor_sub_account_id = ?)";
                $pairParams[] = (int)$pInv;
                $pairParams[] = (int)$pSub;
            }
            $pairSql = implode(' OR ', $pairConditions);
            $params = array_merge([$userId, $programmeId], $pairParams);
            try {
                $sql = "
                    DELETE FROM programme_trades
                    WHERE userid = ?
                      AND programmeid = ?
                      AND investor_id IS NOT NULL
                      AND NOT ($pairSql)
                ";
                $d = $pdo->prepare($sql);
                $d->execute($params);
            } catch (Throwable $e) {}
        } else {
            try {
                $d = $pdo->prepare("
                    DELETE FROM programme_trades
                    WHERE userid = ?
                      AND programmeid = ?
                      AND investor_id IS NOT NULL
                ");
                $d->execute([$userId, $programmeId]);
            } catch (Throwable $e) {}
        }

    } catch (Throwable $e) {
        error_log('[syncProgrammeTradesToInvestors] ' . $e->getMessage());
    }
}

/**
 * Return ACTIVE / INACTIVE deals for a programme.
 *
 * A deal is ACTIVE only when BOTH:
 *   1. harvhub row for (investor_id, investor_sub_account_id) has
 *      account_contract_status = 'contract_active'.
 *   2. The deal's own window (accepted_at + contract_duration) is still open.
 *
 * Deals are deduplicated per (investor_id, sub_account_id): only the newest
 * deal row is kept.
 */
function getDealsForProgramme($pdo, $userId, $programmeId) {
    $active = [];
    $inactive = [];
    try {
        $q = $pdo->prepare("
            SELECT
                d.contract_id, d.investor_id, d.investor_sub_account_id,
                d.accepted_at, d.contract_duration,
                d.developer_percentage, d.investor_percentage,
                d.investor_profit, d.investor_payment_status, d.server_payment_status,
                d.programme_name,
                h.account_contract_status,
                COALESCE(h.username, main.username)               AS username,
                COALESCE(h.first_name, main.first_name)           AS first_name,
                COALESCE(h.last_name, main.last_name)             AS last_name,
                COALESCE(h.fullname, main.fullname)               AS fullname,
                COALESCE(h.email, main.email)                     AS investor_email,
                COALESCE(h.profitandloss, main.profitandloss)     AS profitandloss
            FROM programme_revenue_deals d

            LEFT JOIN harvhub h
                ON h.id = d.investor_id
               AND h.sub_account_id = d.investor_sub_account_id
               AND d.investor_sub_account_id IS NOT NULL
               AND d.investor_sub_account_id > 0

            LEFT JOIN harvhub main
                ON main.id = d.investor_id
               AND main.is_main_account = 1

            WHERE d.programme_id = ? AND d.investor_id > 0
            ORDER BY d.accepted_at DESC, d.contract_id DESC
        ");
        $q->execute([$programmeId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['active' => [], 'inactive' => []];
    }

    // Deduplicate: keep only the newest deal per (investor_id, sub_account_id).
    $deduped = [];
    foreach ($rows as $r) {
        $key = (int)$r['investor_id'] . '|' . (int)($r['investor_sub_account_id'] ?? 0);
        if (!isset($deduped[$key])) {
            $deduped[$key] = $r;
            continue;
        }
        $existingId  = (int)($deduped[$key]['contract_id'] ?? 0);
        $candidateId = (int)($r['contract_id'] ?? 0);
        if ($candidateId > $existingId) {
            $deduped[$key] = $r;
        } elseif ($candidateId === $existingId) {
            $existingAt  = (string)($deduped[$key]['accepted_at'] ?? '');
            $candidateAt = (string)($r['accepted_at'] ?? '');
            if ($candidateAt > $existingAt) $deduped[$key] = $r;
        }
    }
    $rows = array_values($deduped);

    foreach ($rows as $r) {
        $c = classifyDealState($r['accepted_at'] ?? null, (int)($r['contract_duration'] ?? 0));
        if (!$c['start'] || !$c['end']) continue;

        $name = trim((string)($r['username'] ?? ''))
             ?: (trim((string)($r['first_name'] ?? ''))
             ?: (trim((string)($r['fullname'] ?? ''))
             ?: (string)($r['investor_email'] ?? ('Investor #' . (int)$r['investor_id']))));
        $initial = strtoupper(substr($name !== '' ? $name : 'I', 0, 1));

        $pnl    = (float)($r['profitandloss'] ?? 0);
        $devPct = (float)($r['developer_percentage'] ?? 0);
        $traderRevenue = $pnl > 0 ? ($pnl * $devPct / 100) : 0;

        // ACTIVE rule: purely account_contract_status.
        // No accepted_at / contract_duration date math — matches
        // signals_dashboard.php and programmetrades.py exactly.
        $statusActive = (($r['account_contract_status'] ?? '') === 'contract_active');
        $isActive     = $statusActive;

        $entry = [
            'contract_id'             => (int)$r['contract_id'],
            'investor_id'             => (int)$r['investor_id'],
            'sub_account_id'          => (int)($r['investor_sub_account_id'] ?? 0),
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

        if ($isActive) {
            $active[] = $entry;
        } else {
            $inactive[] = $entry;
        }
    }

    return ['active' => $active, 'inactive' => $inactive];
}

function getClosedDealsForProgramme($pdo, $userId, $programmeId) {
    $deals = getDealsForProgramme($pdo, $userId, $programmeId);
    $inactive = $deals['inactive'];

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

function renderInvestorTradesHtml(array $trades) {
    if (!$trades) {
        return '<div class="pi-empty"><i class="fa-solid fa-chart-simple"></i><strong>No trades inherited yet</strong><p>Trades from the programme will appear here once allocated to this investor.</p></div>';
    }

    $groups = ['running' => [], 'pending' => [], 'closed' => [], 'failed' => []];
    foreach ($trades as $t) {
        $status = strtolower(trim((string)($t['orderstatus'] ?? '')));
        if ($status === 'running')      $groups['running'][] = $t;
        elseif ($status === 'closed')   $groups['closed'][]  = $t;
        elseif ($status === 'failed')   $groups['failed'][]  = $t;
        else                            $groups['pending'][] = $t;
    }

    $labels = ['running' => 'Running', 'pending' => 'Pending', 'closed' => 'Closed', 'failed' => 'Failed'];

    $html  = '<div class="sd-trade-tabs">';
    foreach ($labels as $key => $label) {
        $count = count($groups[$key]);
        $active = ($key === 'running') ? ' active' : '';
        $html .= '<button type="button" class="sd-trade-tab' . $active . '" data-trade-tab="inv-' . $key . '" onclick="sdSwitchTradeTab(\'inv-' . $key . '\')">';
        $html .=   $label . ' <span class="sd-trade-tab-count">' . $count . '</span>';
        $html .= '</button>';
    }
    $html .= '</div>';

    foreach ($labels as $key => $label) {
        $panelActive = ($key === 'running') ? ' active' : '';
        $html .= '<div class="sd-trade-panel' . $panelActive . '" id="sdTradePanel-inv-' . $key . '">';
        if (empty($groups[$key])) {
            $html .= '<div class="pi-empty"><i class="fa-solid fa-inbox"></i><strong>No ' . strtolower($label) . ' trades</strong></div>';
        } else {
            $html .= '<div class="sd-trades-list">';
            foreach ($groups[$key] as $t) {
                $orderType = strtoupper((string)($t['ordertype'] ?? '—'));
                $symbol    = strtoupper((string)($t['symbol'] ?? '—'));
                $entry     = $t['entry']      !== null ? rtrim(rtrim(number_format((float)$t['entry'], 5), '0'), '.') : '—';
                $sl        = $t['stoploss']   !== null ? rtrim(rtrim(number_format((float)$t['stoploss'], 5), '0'), '.') : '—';
                $tp        = $t['takeprofit'] !== null ? rtrim(rtrim(number_format((float)$t['takeprofit'], 5), '0'), '.') : '—';
                $status    = strtoupper((string)($t['orderstatus'] ?? '—'));
                $trr       = $t['trade_risk_reward'] !== null ? number_format((float)$t['trade_risk_reward'], 2) : '—';
                $irr       = $t['interest_risk_reward'] !== null ? number_format((float)$t['interest_risk_reward'], 2) : '—';
                $irrType   = normalizeInterestRiskRewardType($t['interest_risk_reward_type'] ?? '');
                $modifyVal = (string)($t['modify_trade'] ?? '');

                $statusLower = strtolower((string)($t['orderstatus'] ?? ''));
                $statusClass = 'sd-order-status';
                if ($statusLower === 'running')      $statusClass .= ' is-running';
                elseif ($statusLower === 'pending')  $statusClass .= ' is-pending';
                elseif ($statusLower === 'closed')   $statusClass .= ' is-closed';
                elseif ($statusLower === 'failed')   $statusClass .= ' is-failed';

                $html .= '<div class="sd-trade-modal-card">';
                $html .=   '<div class="sd-trade-grid">';
                $html .=     '<div><div class="sd-t-label">Symbol</div><b>' . esc_h($symbol) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Order type</div><b>' . esc_h($orderType) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Entry</div><b>' . esc_h($entry) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Stop loss</div><b>' . esc_h($sl) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Take profit</div><b>' . esc_h($tp) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Order status</div><b class="' . $statusClass . '">' . esc_h($status) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Trade risk reward</div><b> 1:' . esc_h($trr) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Interest risk reward</div><b> 1:' . esc_h($irr) . '</b></div>';
                $html .=     '<div><div class="sd-t-label">Interest risk reward type</div><b>' . esc_h($irrType) . '</b></div>';

                if ($statusLower === 'closed') {
                    $pnlVal = $t['pnl'] !== null ? (float)$t['pnl'] : 0.0;
                    $prrVal = $t['pnl_risk_reward'] !== null ? (float)$t['pnl_risk_reward'] : null;
                    $prrTxt = $prrVal !== null ? number_format($prrVal, 2) : '—';

                    if ($pnlVal > 0.01)      { $pnlLabel = 'Closed in Profit'; $pnlClass = 'is-profit'; }
                    elseif ($pnlVal < -0.01) { $pnlLabel = 'Closed in Loss';   $pnlClass = 'is-loss'; }
                    else                     { $pnlLabel = 'Closed Flat';      $pnlClass = 'is-flat'; }

                    $html .= '<div><div class="sd-t-label">Outcome</div><b class="sd-pnl-outcome ' . $pnlClass . '">' . esc_h($pnlLabel) . '</b></div>';
                    $html .= '<div><div class="sd-t-label">PnL risk reward</div><b> 1:' . esc_h($prrTxt) . '</b></div>';
                }

                $html .=   '</div>';

                if ($statusLower === 'running' || $statusLower === 'pending') {
                    $modifyLabel = formatModifyTradeLabel($modifyVal);
                    $html .=   '<div class="sd-trade-modify">';
                    $html .=     '<div class="sd-t-label">Trade modification</div>';
                    $html .=     '<b class="sd-trade-modify-value">' . esc_h($modifyLabel) . '</b>';
                    $html .=   '</div>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    return $html;
}

function buildInvestorsState($pdo, $userId, $programme) {
    $state = [
        'success' => true,
        'programme_id' => 0,
        'active' => [],
        'inactive' => [],
        'requestors' => [],
        'closed' => [],
        'counts' => ['active' => 0, 'inactive' => 0, 'all' => 0, 'requestors' => 0, 'closed' => 0],
    ];

    if (!$programme) return $state;

    $programmeId = (int)$programme['id'];
    $state['programme_id'] = $programmeId;

    // >>> SYNC (hard delete for inactive + orphan rows) before reading
    syncProgrammeTradesToInvestors($pdo, $userId, $programmeId);

    $deals = getDealsForProgramme($pdo, $userId, $programmeId);
    $active   = $deals['active'];
    $inactive = $deals['inactive'];

    $rq = $pdo->prepare("
        SELECT r.id, r.programme_id, r.developerid, r.owner_sub_account_id,
            r.requestor_id, r.requestor_sub_account_id,
            r.request_status, r.created_at,
            COALESCE(sub.username, main.username)     AS username,
            COALESCE(sub.first_name, main.first_name) AS first_name,
            COALESCE(sub.last_name, main.last_name)   AS last_name,
            COALESCE(sub.fullname, main.fullname)     AS fullname,
            COALESCE(sub.email, main.email)           AS requestor_email
        FROM programme_investment_requestors r

        LEFT JOIN harvhub sub
            ON sub.id = r.requestor_id
        AND sub.sub_account_id = r.requestor_sub_account_id
        AND r.requestor_sub_account_id IS NOT NULL
        AND r.requestor_sub_account_id > 0

        LEFT JOIN harvhub main
            ON main.id = r.requestor_id
        AND main.is_main_account = 1

        WHERE r.developerid = ? AND r.programme_id = ? AND r.request_status = 'pending'
        ORDER BY r.created_at DESC
    ");
    $rq->execute([$userId, $programmeId]);
    $requestors = $rq->fetchAll(PDO::FETCH_ASSOC);

    $requestorList = [];
    foreach ($requestors as $r) {
        $name = trim((string)($r['username'] ?? ''))
             ?: (trim((string)($r['first_name'] ?? ''))
             ?: (trim((string)($r['fullname'] ?? ''))
             ?: (string)($r['requestor_email'] ?? ('Requestor #' . (int)$r['requestor_id']))));
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

    $closed = getClosedDealsForProgramme($pdo, $userId, $programmeId);

    $counts = [
        'active'     => count($active),
        'inactive'   => count($inactive),
        'all'        => count($active) + count($inactive),
        'requestors' => count($requestorList),
        'closed'     => 0,
    ];
    foreach ($closed as $g) { $counts['closed'] += count($g['items']); }

    $state['active'] = $active;
    $state['inactive'] = $inactive;
    $state['requestors'] = $requestorList;
    $state['closed'] = $closed;
    $state['counts'] = $counts;

    return $state;
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'get_investors') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            syncInvestorProfitsIntoDeals($pdo);
            jres(buildInvestorsState($pdo, $userId, $programme));
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load investors: ' . $e->getMessage()]);
        }
    }

    if ($action === 'get_investor_trades') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);

        $investorId   = (int)($_POST['investor_id'] ?? 0);
        $subAccountId = (int)($_POST['sub_account_id'] ?? 0);
        if ($investorId <= 0) jres(['success' => false, 'message' => 'Invalid investor.']);

        try {
            $programmeId = (int)$programme['id'];

            // Re-sync first — hard-deletes inactive investor rows
            syncProgrammeTradesToInvestors($pdo, $userId, $programmeId);

            // Resolve deal for THIS investor/sub-account on THIS programme.
            if ($subAccountId > 0) {
                $dq = $pdo->prepare("
                    SELECT d.accepted_at, d.contract_duration,
                           COALESCE(sub.username, main.username)     AS username,
                           COALESCE(sub.first_name, main.first_name) AS first_name,
                           COALESCE(sub.last_name, main.last_name)   AS last_name,
                           COALESCE(sub.fullname, main.fullname)     AS fullname,
                           COALESCE(sub.email, main.email)           AS email
                    FROM programme_revenue_deals d

                    LEFT JOIN harvhub sub
                        ON sub.id = d.investor_id
                       AND sub.sub_account_id = d.investor_sub_account_id

                    LEFT JOIN harvhub main
                        ON main.id = d.investor_id
                       AND main.is_main_account = 1

                    WHERE d.programme_id = ?
                      AND d.investor_id = ?
                      AND d.investor_sub_account_id = ?
                    ORDER BY d.contract_id DESC LIMIT 1
                ");
                $dq->execute([$programmeId, $investorId, $subAccountId]);
            } else {
                $dq = $pdo->prepare("
                    SELECT d.accepted_at, d.contract_duration,
                           COALESCE(sub.username, main.username)     AS username,
                           COALESCE(sub.first_name, main.first_name) AS first_name,
                           COALESCE(sub.last_name, main.last_name)   AS last_name,
                           COALESCE(sub.fullname, main.fullname)     AS fullname,
                           COALESCE(sub.email, main.email)           AS email
                    FROM programme_revenue_deals d

                    LEFT JOIN harvhub sub
                        ON sub.id = d.investor_id
                       AND sub.sub_account_id = d.investor_sub_account_id

                    LEFT JOIN harvhub main
                        ON main.id = d.investor_id
                       AND main.is_main_account = 1

                    WHERE d.programme_id = ?
                      AND d.investor_id = ?
                    ORDER BY d.contract_id DESC LIMIT 1
                ");
                $dq->execute([$programmeId, $investorId]);
            }
            $deal = $dq->fetch(PDO::FETCH_ASSOC);

            $name = trim((string)($deal['username'] ?? ''))
                ?: (trim((string)($deal['first_name'] ?? ''))
                ?: (trim((string)($deal['fullname'] ?? ''))
                ?: (string)($deal['email'] ?? ('Investor #' . $investorId))));
            $initial = strtoupper(substr($name !== '' ? $name : 'I', 0, 1));

            $startDate = null; $endDate = null; $dealWindowOpen = false;
            if (!empty($deal['accepted_at']) && (int)($deal['contract_duration'] ?? 0) > 0) {
                try {
                    $s = new DateTime($deal['accepted_at']);
                    $e = clone $s; $e->modify('+' . (int)$deal['contract_duration'] . ' days');
                    $startDate = $s->format('Y-m-d');
                    $endDate   = $e->format('Y-m-d');

                    $today = new DateTime(); $today->setTime(0, 0, 0);
                    $eClone = clone $e; $eClone->setTime(0, 0, 0);
                    $dealWindowOpen = ((int)$today->diff($eClone)->format('%r%a') > 0);
                } catch (Exception $e) {}
            }

            // Cross-check against getActiveInvestorsForProgramme() — that function
            // already enforces BOTH conditions (status=contract_active AND deal window open).
            $activeSet = [];
            foreach (getActiveInvestorsForProgramme($pdo, $programmeId) as $a) {
                $activeSet[(int)$a['investor_id'] . '|' . (int)$a['sub_account_id']] = true;
            }
            $key = $investorId . '|' . (int)$subAccountId;
            $isActive = isset($activeSet[$key]);

            if (!$isActive) {
                jres([
                    'success'      => true,
                    'investor_id'  => $investorId,
                    'name'         => $name,
                    'initial'      => $initial,
                    'start_date'   => $startDate,
                    'end_date'     => $endDate,
                    'account_contract_status' => 'no_contract',
                    'trades_html'  => '<div class="pi-empty"><i class="fa-solid fa-user-clock"></i><strong>Investor is inactive</strong><p>No trades are allocated to inactive investors.</p></div>',
                ]);
            }

            // Only return rows whose tradeid still exists on the programme.
            $tq = $pdo->prepare("
                SELECT t.*
                FROM programme_trades t
                WHERE t.userid = ?
                  AND t.programmeid = ?
                  AND t.investor_id = ?
                  AND t.tradeid IN (
                      SELECT p.tradeid FROM programme_trades p
                      WHERE p.userid = ?
                        AND p.programmeid = ?
                        AND p.investor_id IS NULL
                        AND p.tradeid IS NOT NULL
                  )
                ORDER BY t.created_at DESC
                LIMIT 300
            ");
            $tq->execute([$userId, $programmeId, $investorId, $userId, $programmeId]);
            $trades = $tq->fetchAll(PDO::FETCH_ASSOC);

            jres([
                'success'      => true,
                'investor_id'  => $investorId,
                'name'         => $name,
                'initial'      => $initial,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'account_contract_status' => 'contract_active',
                'trades_html'  => renderInvestorTradesHtml($trades),
            ]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load investor trades: ' . $e->getMessage()]);
        }
    }

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

            if (!$row) jres(['success' => false, 'message' => 'Request not found or not for this programme.']);
            if ($row['request_status'] !== 'pending') jres(['success' => false, 'message' => 'This request is already ' . $row['request_status'] . '.']);

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

                $existingDeal = null;
                try {
                    if ($reqSubId > 0) {
                        $chkDeal = $pdo->prepare("
                            SELECT contract_id FROM programme_revenue_deals
                            WHERE programme_id = ?
                              AND investor_id = ?
                              AND investor_sub_account_id = ?
                            LIMIT 1
                        ");
                        $chkDeal->execute([$programmeId, $requestorId, $reqSubId]);
                    } else {
                        $chkDeal = $pdo->prepare("
                            SELECT contract_id FROM programme_revenue_deals
                            WHERE programme_id = ?
                              AND investor_id = ?
                              AND (investor_sub_account_id IS NULL OR investor_sub_account_id = 0)
                            LIMIT 1
                        ");
                        $chkDeal->execute([$programmeId, $requestorId]);
                    }
                    $existingDeal = $chkDeal->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {}

                if ($existingDeal) {
                    $updDeal = $pdo->prepare("
                        UPDATE programme_revenue_deals
                        SET programme_name = ?,
                            accepted_at = NOW(),
                            contract_duration = ?,
                            programme_percentage = ?,
                            minimum_investment = ?,
                            maximum_investment = ?,
                            investor_profit = 0.00,
                            investor_payment_status = NULL,
                            server_payment_status = NULL
                        WHERE contract_id = ?
                    ");
                    $updDeal->execute([
                        $row['program_name'] ?? 'Programme',
                        $contractDuration,
                        $developerPercent,
                        $minInvestment,
                        $maxInvestment,
                        (int)$existingDeal['contract_id']
                    ]);
                } else {
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
                }

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

                syncProgrammeTradesToInvestors($pdo, $userId, $programmeId);

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
                            'notification_key' => 'programme-investment-accepted-' . $programmeId . '-' . $requestId,
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
                            'notification_key' => 'programme-investment-rejected-' . $programmeId . '-' . $requestId,
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

    jres(['success' => false]);
}

// -------------------- DERIVED VALUES (initial server render) --------------------
syncInvestorProfitsIntoDeals($pdo);
$initialInvestorsState = buildInvestorsState($pdo, $userId, $programme);
$programmeName = $programme ? ($programme['program_name'] ?: 'Unnamed Programme') : 'No Programme';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Programme Investors</title>
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
    body.dark-mode { --sd-bg:#0c1311; --sd-card:#131d1a; --sd-text:#e8f2ee; --sd-muted:#93a8a1; --sd-soft:#12271f; --sd-border:#22322d; --sd-shadow: 0 10px 30px rgba(0,0,0,.4); }

    body.pi-scroll-locked { overflow: hidden; }

    .pi-page { max-width: 720px; margin: 0 auto; padding: 0 20px 100px; width: 100%; }
    @media (max-width: 480px) { .pi-page { padding: 0 14px 90px; } }

    .pi-header { display: flex; align-items: center; justify-content: center; position: relative; padding: calc(env(safe-area-inset-top, 0px) + 14px) 16px 10px; background: var(--sd-bg); min-height: 56px; }
    .pi-header-back { position: absolute; left: 16px; top: calc(env(safe-area-inset-top, 0px) + 14px); background: transparent; border: none; color: var(--sd-text); font-size: 1.2rem; cursor: pointer; padding: 8px; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .pi-header-title { font-size: 1.15rem; font-weight: 800; }

    .pi-tabs-wrap { position: sticky; top: 0; z-index: 100; background: var(--sd-bg); border-bottom: 1px solid var(--sd-border); padding: 0 20px; max-width: 720px; margin: 0 auto; width: 100%; }

    .pi-tabs { display: flex; gap: 4px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; }
    .pi-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .pi-tab { background: transparent; border: none; color: var(--sd-muted); padding: 12px 16px; font-weight: 700; font-size: .85rem; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -1px; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; }
    .pi-tab.active { color: var(--sd-accent); border-bottom-color: var(--sd-accent); }
    .pi-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--sd-border); color: var(--sd-muted); font-size: .65rem; font-weight: 800; }
    .pi-tab.active .pi-tab-count { background: var(--sd-accent); color: #fff; }

    .pi-panel { display: none; }
    .pi-panel.active { display: block; }

    .pi-body { padding-top: 20px; }

    .pi-inv-card { display: flex; gap: 12px; border-radius: 14px; padding: 12px 4px; margin-bottom: 0; border-bottom: 1px solid var(--sd-border); }
    .pi-inv-card:last-child { border-bottom: none; }
    .pi-inv-avatar { width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; letter-spacing: 0.3px; }
    .pi-inv-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
    .pi-inv-username { font-size: .95rem; font-weight: 700; color: var(--sd-text); word-break: break-word; line-height: 1.3; }

    .pi-inv-stat { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: .78rem; }
    .pi-inv-stat-key { color: var(--sd-muted); font-weight: 600; }
    .pi-inv-stat-val { font-weight: 700; color: var(--sd-text); text-align: right; word-break: break-word; }
    .pi-inv-stat-val.money { color: var(--sd-accent); font-size: .88rem; font-weight: 800; }

    .pi-inv-check-btn { margin-top: 6px; padding: 7px 12px; font-size: .72rem; align-self: flex-start; }

    .pi-inv-card.is-inactive .pi-inv-avatar { background: var(--sd-muted); }
    .pi-inv-card.is-inactive .pi-inv-stat { display: none; }

    .pi-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 800; letter-spacing: 0.3px; }
    .pi-status-pill.is-active { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
    .pi-status-pill.is-inactive { background: rgba(148, 163, 184, 0.18); color: var(--sd-muted); }
    body.dark-mode .pi-status-pill.is-active { color: #4ade80; }

    .pi-all-row { display: flex; align-items: center; gap: 12px; padding: 12px 4px; border-bottom: 1px solid var(--sd-border); }
    .pi-all-row:last-child { border-bottom: none; }
    .pi-all-avatar { width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; }
    .pi-all-row.is-inactive .pi-all-avatar { background: var(--sd-muted); }
    .pi-all-name { flex: 1; min-width: 0; font-size: .95rem; font-weight: 700; color: var(--sd-text); word-break: break-word; }

    .pi-req-card { display: flex; gap: 12px; border-radius: 14px; padding: 12px 4px; margin-bottom: 0; align-items: center; border-bottom: 1px solid var(--sd-border); }
    .pi-req-card:last-child { border-bottom: none; }
    .pi-req-avatar { width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; letter-spacing: 0.3px; }
    .pi-req-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .pi-req-username { font-size: .95rem; font-weight: 700; color: var(--sd-text); word-break: break-word; }
    .pi-req-meta { font-size: .75rem; color: var(--sd-muted); font-weight: 500; }
    .pi-req-select { padding: 7px 10px; border: 1px solid var(--sd-border); border-radius: 9px; background: var(--sd-bg); color: var(--sd-text); font-family: inherit; font-size: .78rem; font-weight: 600; cursor: pointer; min-width: 92px; flex-shrink: 0; }
    .pi-req-select:focus { outline: none; border-color: var(--sd-accent); }

    .pi-closed-group { margin-bottom: 18px; }
    .pi-closed-range { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: var(--sd-accent); padding: 6px 0 8px; border-bottom: 1px solid var(--sd-border); margin-bottom: 4px; }
    .pi-closed-item { display: flex; gap: 12px; padding: 12px 4px; border-bottom: 1px solid var(--sd-border); }
    .pi-closed-item:last-child { border-bottom: none; }
    .pi-closed-avatar { width: 40px; height: 40px; flex-shrink: 0; border-radius: 50%; background: var(--sd-accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.05rem; text-transform: uppercase; }
    .pi-closed-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
    .pi-closed-name { font-size: .9rem; font-weight: 700; color: var(--sd-text); word-break: break-word; }
    .pi-closed-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; font-size: .76rem; }
    .pi-closed-key { color: var(--sd-muted); font-weight: 600; }
    .pi-closed-val { font-weight: 700; color: var(--sd-text); text-align: right; word-break: break-word; }
    .pi-closed-val.money { color: var(--sd-accent); font-weight: 800; }
    .pi-closed-val.muted { color: var(--sd-muted); font-weight: 600; }

    .pi-empty { padding: 28px 20px; text-align: center; color: var(--sd-muted); border: 1px dashed var(--sd-border); border-radius: 14px; background: var(--sd-bg); }
    .pi-empty i { font-size: 1.4rem; margin-bottom: 8px; color: var(--sd-accent); display: block; }
    .pi-empty strong { display: block; color: var(--sd-text); font-size: .92rem; margin-bottom: 4px; font-weight: 700; }
    .pi-empty p { font-size: .8rem; }

    .pi-btn { border: 0; border-radius: 10px; padding: 9px 14px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-family: inherit; font-size: .78rem; }
    .pi-btn.primary { background: var(--sd-accent); color: #fff; }
    .pi-btn.ghost { background: transparent; color: var(--sd-text); border: 1px solid var(--sd-border); }
    .pi-btn.primary:hover { background: var(--sd-accent-2); }

    .sd-trade-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--sd-border); margin-bottom: 14px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; max-height: 48px; }
    .sd-trade-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .sd-trade-tab { background: transparent; border: none; color: var(--sd-muted); padding: 9px 14px; font-weight: 700; font-size: .8rem; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -1px; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; }
    .sd-trade-tab.active { color: var(--sd-accent); border-bottom-color: var(--sd-accent); }
    .sd-trade-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--sd-border); color: var(--sd-muted); font-size: .65rem; font-weight: 800; }
    .sd-trade-tab.active .sd-trade-tab-count { background: var(--sd-accent); color: #fff; }
    .sd-trade-panel { display: none; } .sd-trade-panel.active { display: block; }

    .sd-trades-list { display: grid; gap: 10px; }
    .sd-trade-modal-card { border: 1px solid var(--sd-border); border-radius: 14px; padding: 14px; background: var(--sd-bg); }
    .sd-trade-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin-bottom: 12px; }
    @media (max-width: 600px) { .sd-trade-grid { grid-template-columns: 1fr 1fr; gap: 8px 12px; } }
    .sd-trade-grid > div { min-width: 0; }
    .sd-trade-modal-card .sd-t-label { font-size: .6rem; color: var(--sd-muted); text-transform: uppercase; font-weight: 700; letter-spacing: .5px; margin-bottom: 2px; }
    .sd-trade-modal-card .sd-t-label + b { font-size: .84rem; font-weight: 800; word-break: break-word; display: block; }
    .sd-trade-modify { padding-top: 10px; border-top: 1px dashed var(--sd-border); }
    .sd-trade-modify-value { font-size: .9rem; font-weight: 800; color: var(--sd-text); display: block; margin-top: 2px; }

    .sd-order-status { font-weight: 800; }
    .sd-order-status.is-running { color: var(--sd-success, #2ecc71); }
    .sd-order-status.is-pending { color: var(--sd-warning, #b87513); }
    .sd-order-status.is-closed  { color: var(--sd-info, #3498db); }
    .sd-order-status.is-failed  { color: var(--sd-danger, #df4e4e); }
    body.dark-mode .sd-order-status.is-running { color: #4ade80; }
    body.dark-mode .sd-order-status.is-pending { color: #facc15; }
    body.dark-mode .sd-order-status.is-closed  { color: #60a5fa; }
    body.dark-mode .sd-order-status.is-failed  { color: #ff8080; }

    .sd-pnl-outcome { font-weight: 800; }
    .sd-pnl-outcome.is-profit { color: var(--sd-success, #2ecc71); }
    .sd-pnl-outcome.is-loss   { color: var(--sd-danger, #df4e4e); }
    .sd-pnl-outcome.is-flat   { color: var(--sd-muted, #60736d); }
    body.dark-mode .sd-pnl-outcome.is-profit { color: #4ade80; }
    body.dark-mode .sd-pnl-outcome.is-loss   { color: #ff8080; }
    body.dark-mode .sd-pnl-outcome.is-flat   { color: #93a8a1; }

    .pi-inv-trades-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 40px 16px; z-index: 1000000; overscroll-behavior: contain; }
    .pi-inv-trades-modal.open { display: flex; }
    .pi-inv-trades-card { width: min(680px, 100%); height: min(720px, calc(100vh - 80px)); max-height: calc(100vh - 80px); display: flex; flex-direction: column; overflow: hidden; background: var(--sd-card); border-radius: 20px; padding: 22px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .pi-inv-trades-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; flex: 0 0 auto; }
    .pi-inv-trades-head .pi-inv-avatar { width: 44px; height: 44px; font-size: 1.1rem; }
    .pi-inv-trades-head .pi-inv-trades-name { font-size: .98rem; font-weight: 800; word-break: break-word; }
    .pi-inv-trades-head .pi-inv-trades-dates { font-size: .76rem; color: var(--sd-muted); }
    .pi-inv-trades-section-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; font-weight: 800; color: var(--sd-muted); margin: 0 0 8px; flex: 0 0 auto; }
    .pi-inv-trades-body { flex: 1 1 auto; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding-right: 4px; scrollbar-width: thin; min-height: 0; }
    .pi-inv-trades-body::-webkit-scrollbar { width: 6px; }
    .pi-inv-trades-body::-webkit-scrollbar-thumb { background: var(--sd-border); border-radius: 999px; }
    .pi-inv-trades-body .sd-trade-tabs { position: sticky; top: 0; z-index: 5; background: var(--sd-card); padding-top: 4px; padding-bottom: 4px; margin-bottom: 12px; }

    .pi-confirm-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 999999; }
    .pi-confirm-modal.open { display: flex; }
    .pi-confirm-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 22px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .pi-confirm-card h3 { margin-bottom: 8px; font-size: 1rem; font-weight: 800; }
    .pi-confirm-card p { color: var(--sd-muted); font-size: .85rem; margin-bottom: 16px; line-height: 1.5; }
    .pi-confirm-actions { display: grid; gap: 9px; }

    .pi-alert-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 9999999; }
    .pi-alert-modal.open { display: flex; }
    .pi-alert-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 22px; box-shadow: 0 25px 80px rgba(0,0,0,.4); text-align: center; }
    .pi-alert-icon { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin: 0 auto 12px; }
    .pi-alert-icon.info    { background: rgba(52,152,219,0.12); color: var(--sd-info); }
    .pi-alert-icon.success { background: rgba(46,204,143,0.12); color: var(--sd-success); }
    .pi-alert-icon.error   { background: #fdecec; color: var(--sd-danger); }
    body.dark-mode .pi-alert-icon.error { background: #3a1c1c; color: #ff9c9c; }
    .pi-alert-title { font-size: 1rem; font-weight: 800; margin-bottom: 6px; }
    .pi-alert-text { color: var(--sd-muted); font-size: .85rem; margin-bottom: 16px; line-height: 1.5; }

    @keyframes piLivePulse {
        0% { background: rgba(46,204,143,0.0); }
        50% { background: rgba(46,204,143,0.12); }
        100% { background: rgba(46,204,143,0.0); }
    }
    .pi-live-pulse { animation: piLivePulse 1.2s ease; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="pi-page">
    <div class="pi-header">
        <button class="pi-header-back" onclick="piGoBack()" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></button>
        <div class="pi-header-title">Investors</div>
    </div>

    <div class="pi-tabs-wrap">
        <div class="pi-tabs">
            <button class="pi-tab" data-pi-tab="all" onclick="piSwitchTab('all')">All <span class="pi-tab-count" id="piTabCountAll">0</span></button>
            <button class="pi-tab active" data-pi-tab="active" onclick="piSwitchTab('active')">Active <span class="pi-tab-count" id="piTabCountActive">0</span></button>
            <button class="pi-tab" data-pi-tab="inactive" onclick="piSwitchTab('inactive')">Inactive <span class="pi-tab-count" id="piTabCountInactive">0</span></button>
            <button class="pi-tab" data-pi-tab="requestors" onclick="piSwitchTab('requestors')">Requestors <span class="pi-tab-count" id="piTabCountRequestors">0</span></button>
            <button class="pi-tab" data-pi-tab="closed" onclick="piSwitchTab('closed')">Closed Deals <span class="pi-tab-count" id="piTabCountClosed">0</span></button>
        </div>
    </div>

    <div class="pi-body" id="piBody">
        <div id="piPanelActive" class="pi-panel active">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading investors…</p></div>
        </div>
        <div id="piPanelInactive" class="pi-panel">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading inactive investors…</p></div>
        </div>
        <div id="piPanelAll" class="pi-panel">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading all investors…</p></div>
        </div>
        <div id="piPanelRequestors" class="pi-panel">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading requestors…</p></div>
        </div>
        <div id="piPanelClosed" class="pi-panel">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading closed deals…</p></div>
        </div>
    </div>
</div>

<!-- INVESTOR TRADES MODAL -->
<div class="pi-inv-trades-modal" id="piInvTradesModal">
    <div class="pi-inv-trades-card">
        <div class="pi-inv-trades-head">
            <div class="pi-inv-avatar" id="piInvTradesAvatar">I</div>
            <div style="flex:1;min-width:0;">
                <div class="pi-inv-trades-name" id="piInvTradesName">Investor</div>
                <div class="pi-inv-trades-dates" id="piInvTradesDates">—</div>
            </div>
            <button class="pi-btn ghost" onclick="piCloseInvTradesModal()" aria-label="Close" style="padding:8px 12px;flex-shrink:0;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="pi-inv-trades-section-title">Trades</div>
        <div class="pi-inv-trades-body" id="piInvTradesBody">
            <div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading trades…</p></div>
        </div>
    </div>
</div>

<!-- CONFIRM MODAL -->
<div class="pi-confirm-modal" id="piConfirmModal">
    <div class="pi-confirm-card">
        <h3 id="piConfirmTitle">Confirm Action</h3>
        <p id="piConfirmMessage">Are you sure?</p>
        <div class="pi-confirm-actions">
            <button class="pi-btn primary" id="piConfirmYes" style="width:100%;">Yes, Confirm</button>
            <button class="pi-btn ghost" onclick="piCloseConfirm()" style="width:100%;">Cancel</button>
        </div>
    </div>
</div>

<!-- ALERT MODAL -->
<div class="pi-alert-modal" id="piAlertModal">
    <div class="pi-alert-card">
        <div class="pi-alert-icon info" id="piAlertIcon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="pi-alert-title" id="piAlertTitle">Notice</div>
        <div class="pi-alert-text" id="piAlertText">Message</div>
        <button class="pi-btn primary" id="piAlertOkBtn" style="width:100%;">OK</button>
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
        return !!document.querySelector('.pi-inv-trades-modal.open, .pi-confirm-modal.open, .pi-alert-modal.open');
    }
    function lockBodyScroll() { document.body.classList.add('pi-scroll-locked'); }
    function unlockBodyScroll() { if (!anyOverlayOpen()) document.body.classList.remove('pi-scroll-locked'); }

    window.piGoBack = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'signals' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=signals';
    };

    function post(data, cb) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        fetch('programme_investors.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (d) { cb(d || {}); })
            .catch(function () { cb({ success: false }); });
    }

    function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function fmtMoney(v) { var n = parseFloat(v) || 0; return '$' + n.toFixed(2); }

    window.piAlert = function (message, kind, title) {
        kind = kind || 'info';
        var iconEl  = document.getElementById('piAlertIcon');
        var titleEl = document.getElementById('piAlertTitle');
        var textEl  = document.getElementById('piAlertText');

        iconEl.classList.remove('info', 'success', 'error');
        iconEl.classList.add(kind);

        var iconHtml = '<i class="fa-solid fa-circle-info"></i>';
        if (kind === 'success') iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        if (kind === 'error')   iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        iconEl.innerHTML = iconHtml;

        titleEl.textContent = title || (kind === 'error' ? 'Error' : (kind === 'success' ? 'Success' : 'Notice'));
        textEl.textContent  = message || '';

        var el = document.getElementById('piAlertModal');
        el.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    document.getElementById('piAlertOkBtn').addEventListener('click', function () {
        var el = document.getElementById('piAlertModal');
        el.classList.remove('open');
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    });

    var _pendingRequestId = null;
    var _pendingRequestAction = null;
    var _pendingSelectEl = null;

    window.piHandleRequestAction = function (selectEl) {
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

        document.getElementById('piConfirmTitle').textContent = title;
        document.getElementById('piConfirmMessage').textContent = msg;
        document.getElementById('piConfirmYes').textContent = action === 'accept' ? 'Yes, Accept' : 'Yes, Reject';
        document.getElementById('piConfirmModal').classList.add('open');
        lockBodyScroll();
    };

    document.getElementById('piConfirmYes').addEventListener('click', function () {
        if (_pendingRequestId && _pendingRequestAction) {
            var requestId = _pendingRequestId;
            var action = _pendingRequestAction;
            var selectEl = _pendingSelectEl;

            document.getElementById('piConfirmModal').classList.remove('open');
            if (selectEl) selectEl.disabled = true;

            post({ action: 'update_request_status', request_id: requestId, new_status: action }, function (d) {
                if (d.success) {
                    piLoadInvestors();
                } else {
                    piAlert(d.message || 'Failed to update request.', 'error', 'Request failed');
                    if (selectEl) { selectEl.value = ''; selectEl.disabled = false; }
                    if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
                }
                _pendingRequestId = null;
                _pendingRequestAction = null;
                _pendingSelectEl = null;
            });
        }
    });

    window.piCloseConfirm = function () {
        document.getElementById('piConfirmModal').classList.remove('open');
        if (_pendingSelectEl) { _pendingSelectEl.value = ''; _pendingSelectEl.disabled = false; }
        _pendingRequestId = null; _pendingRequestAction = null; _pendingSelectEl = null;
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    window.piSwitchTab = function (tab) {
        document.querySelectorAll('.pi-tab').forEach(function (t) { t.classList.toggle('active', t.dataset.piTab === tab); });
        document.querySelectorAll('.pi-panel').forEach(function (p) { p.classList.remove('active'); });
        if (tab === 'active') document.getElementById('piPanelActive').classList.add('active');
        else if (tab === 'inactive') document.getElementById('piPanelInactive').classList.add('active');
        else if (tab === 'all') document.getElementById('piPanelAll').classList.add('active');
        else if (tab === 'requestors') document.getElementById('piPanelRequestors').classList.add('active');
        else if (tab === 'closed') document.getElementById('piPanelClosed').classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.sdSwitchTradeTab = function (tab) {
        document.querySelectorAll('.sd-trade-tab').forEach(function (x) {
            x.classList.toggle('active', x.dataset.tradeTab === tab);
        });
        document.querySelectorAll('.sd-trade-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === 'sdTradePanel-' + tab);
        });
    };

    function renderActiveInvestorCard(inv) {
        var initial = inv.initial && inv.initial.length ? inv.initial : (String(inv.name || 'I').charAt(0).toUpperCase());
        var html = '';
        html += '<div class="pi-inv-card">';
        html +=   '<div class="pi-inv-avatar">' + escapeHtml(initial) + '</div>';
        html +=   '<div class="pi-inv-info">';
        html +=     '<div class="pi-inv-username">' + escapeHtml(inv.name || 'Investor') + '</div>';
        html +=     '<div class="pi-inv-stat"><span class="pi-inv-stat-key">Revenue from investor</span><span class="pi-inv-stat-val money">' + fmtMoney(inv.trader_revenue) + '</span></div>';
        if (inv.start_date) html += '<div class="pi-inv-stat"><span class="pi-inv-stat-key">Started</span><span class="pi-inv-stat-val">' + escapeHtml(inv.start_date) + '</span></div>';
        if (inv.end_date)   html += '<div class="pi-inv-stat"><span class="pi-inv-stat-key">Ending</span><span class="pi-inv-stat-val">' + escapeHtml(inv.end_date) + '</span></div>';
        if (inv.end_date) {
            html += '<div class="pi-inv-stat"><span class="pi-inv-stat-key">Days Left</span>';
            var dl = inv.days_left !== null ? inv.days_left : 0;
            html += '<span class="pi-inv-stat-val">' + dl + ' day' + (dl === 1 ? '' : 's') + ' left</span>';
            html += '</div>';
        }
        html +=     '<button type="button" class="pi-btn ghost pi-inv-check-btn" data-investor-id="' + (inv.investor_id || 0) + '" data-sub-account-id="' + (inv.sub_account_id || 0) + '" data-investor-name="' + escapeHtml(inv.name || 'Investor') + '">';
        html +=       '<i class="fa-solid fa-list-check"></i> Check Investor Trades';
        html +=     '</button>';
        html +=   '</div>';
        html += '</div>';
        return html;
    }

    function renderInactiveInvestorCard(inv) {
        var initial = inv.initial && inv.initial.length ? inv.initial : (String(inv.name || 'I').charAt(0).toUpperCase());
        var html = '';
        html += '<div class="pi-inv-card is-inactive">';
        html +=   '<div class="pi-inv-avatar">' + escapeHtml(initial) + '</div>';
        html +=   '<div class="pi-inv-info">';
        html +=     '<div class="pi-inv-username">' + escapeHtml(inv.name || 'Investor') + '</div>';
        html +=     '<div><span class="pi-status-pill is-inactive">Not active</span></div>';
        html +=     '<button type="button" class="pi-btn ghost pi-inv-check-btn" data-investor-id="' + (inv.investor_id || 0) + '" data-sub-account-id="' + (inv.sub_account_id || 0) + '" data-investor-name="' + escapeHtml(inv.name || 'Investor') + '">';
        html +=       '<i class="fa-solid fa-list-check"></i> Check Investor Trades';
        html +=     '</button>';
        html +=   '</div>';
        html += '</div>';
        return html;
    }

    function renderAllInvestorRow(inv, isActive) {
        var initial = inv.initial && inv.initial.length ? inv.initial : (String(inv.name || 'I').charAt(0).toUpperCase());
        var cls = 'pi-all-row' + (isActive ? '' : ' is-inactive');
        var pill = isActive
            ? '<span class="pi-status-pill is-active">Active contract</span>'
            : '<span class="pi-status-pill is-inactive">Not active</span>';

        var html = '';
        html += '<div class="' + cls + '">';
        html +=   '<div class="pi-all-avatar">' + escapeHtml(initial) + '</div>';
        html +=   '<div class="pi-all-name">' + escapeHtml(inv.name || 'Investor') + '</div>';
        html +=   pill;
        html += '</div>';
        return html;
    }

    function bindInvestorCheckButtons() {
        document.querySelectorAll('.pi-inv-check-btn').forEach(function (btn) {
            if (btn.dataset.piBound === '1') return;
            btn.dataset.piBound = '1';
            btn.addEventListener('click', function () {
                var invId = parseInt(btn.getAttribute('data-investor-id'), 10) || 0;
                var subId = parseInt(btn.getAttribute('data-sub-account-id'), 10) || 0;
                var name  = btn.getAttribute('data-investor-name') || 'Investor';
                if (invId > 0) piOpenInvTradesModal(invId, subId, name);
            });
        });
    }

    var _piData = null;

    function renderInvestorsPanels(d) {
        _piData = d;

        var counts = d.counts || { active: 0, inactive: 0, all: 0, requestors: 0, closed: 0 };
        var elA = document.getElementById('piTabCountActive'); if (elA) elA.textContent = counts.active;
        var elI = document.getElementById('piTabCountInactive'); if (elI) elI.textContent = counts.inactive;
        var elAll = document.getElementById('piTabCountAll'); if (elAll) elAll.textContent = counts.all;
        var elR = document.getElementById('piTabCountRequestors'); if (elR) elR.textContent = counts.requestors;
        var elC = document.getElementById('piTabCountClosed'); if (elC) elC.textContent = counts.closed;

        var activePanel = document.getElementById('piPanelActive');
        var inactivePanel = document.getElementById('piPanelInactive');
        var allPanel = document.getElementById('piPanelAll');
        var requestorsPanel = document.getElementById('piPanelRequestors');
        var closedPanel = document.getElementById('piPanelClosed');

        if (!d.active || !d.active.length) {
            activePanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-users"></i><strong>No active investors yet</strong><p>Investors with a currently running contract will appear here.</p></div>';
        } else {
            var html = '';
            d.active.forEach(function (inv) { html += renderActiveInvestorCard(inv); });
            activePanel.innerHTML = html;
        }

        if (!d.inactive || !d.inactive.length) {
            inactivePanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-user-clock"></i><strong>No inactive investors yet</strong><p>Investors whose contract has ended will appear here.</p></div>';
        } else {
            var ihtml = '';
            d.inactive.forEach(function (inv) { ihtml += renderInactiveInvestorCard(inv); });
            inactivePanel.innerHTML = ihtml;
        }

        var allRows = []
            .concat((d.active || []).map(function (x) { return { inv: x, isActive: true }; }))
            .concat((d.inactive || []).map(function (x) { return { inv: x, isActive: false }; }));

        if (!allRows.length) {
            allPanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-users"></i><strong>No investors yet</strong><p>Investors will appear here once they join.</p></div>';
        } else {
            var ahtml = '';
            allRows.forEach(function (entry) { ahtml += renderAllInvestorRow(entry.inv, entry.isActive); });
            allPanel.innerHTML = ahtml;
        }

        if (!d.requestors || !d.requestors.length) {
            requestorsPanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-hand-holding-dollar"></i><strong>No pending requests</strong><p>Investment requests will appear here.</p></div>';
        } else {
            var rhtml = '';
            d.requestors.forEach(function (req) {
                var initial = req.initial && req.initial.length ? req.initial : (String(req.name || 'R').charAt(0).toUpperCase());
                rhtml += '<div class="pi-req-card" data-request-id="' + req.request_id + '">';
                rhtml +=   '<div class="pi-req-avatar">' + escapeHtml(initial) + '</div>';
                rhtml +=   '<div class="pi-req-info">';
                rhtml +=     '<div class="pi-req-username">' + escapeHtml(req.name || 'Requestor') + '</div>';
                rhtml +=     '<div class="pi-req-meta">Requested ' + escapeHtml(req.created_at || '') + '</div>';
                rhtml +=   '</div>';
                rhtml +=   '<select class="pi-req-select" data-request-id="' + req.request_id + '" onchange="piHandleRequestAction(this)">';
                rhtml +=     '<option value="">Action</option>';
                rhtml +=     '<option value="accept">Accept</option>';
                rhtml +=     '<option value="reject">Reject</option>';
                rhtml +=   '</select>';
                rhtml += '</div>';
            });
            requestorsPanel.innerHTML = rhtml;
        }

        if (!d.closed || !d.closed.length) {
            closedPanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-box-archive"></i><strong>No closed deals yet</strong><p>Ended contracts will appear here grouped by date range.</p></div>';
        } else {
            var chtml = '';
            d.closed.forEach(function (group) {
                chtml += '<div class="pi-closed-group">';
                chtml +=   '<div class="pi-closed-range">' + escapeHtml(group.range) + '</div>';
                group.items.forEach(function (it) {
                    var initial = it.initial && it.initial.length ? it.initial : (String(it.name || 'I').charAt(0).toUpperCase());
                    chtml += '<div class="pi-closed-item">';
                    chtml +=   '<div class="pi-closed-avatar">' + escapeHtml(initial) + '</div>';
                    chtml +=   '<div class="pi-closed-body">';
                    chtml +=     '<div class="pi-closed-name">' + escapeHtml(it.name || 'Investor') + '</div>';
                    chtml +=     '<div class="pi-closed-row"><span class="pi-closed-key">Revenue from investor</span><span class="pi-closed-val money">' + fmtMoney(it.investor_profit) + '</span></div>';
                    chtml +=     '<div class="pi-closed-row"><span class="pi-closed-key">Investor payment status</span><span class="pi-closed-val' + (it.investor_payment_status ? '' : ' muted') + '">' + escapeHtml(it.investor_payment_status || '—') + '</span></div>';
                    chtml +=     '<div class="pi-closed-row"><span class="pi-closed-key">Server payment status</span><span class="pi-closed-val' + (it.server_payment_status ? '' : ' muted') + '">' + escapeHtml(it.server_payment_status || '—') + '</span></div>';
                    chtml +=   '</div>';
                    chtml += '</div>';
                });
                chtml += '</div>';
            });
            closedPanel.innerHTML = chtml;
        }

        bindInvestorCheckButtons();
    }

    window.piLoadInvestors = function () {
        var activePanel = document.getElementById('piPanelActive');
        if (activePanel) activePanel.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading investors…</p></div>';

        post({ action: 'get_investors' }, function (d) {
            if (!d.success) {
                var html = '<div class="pi-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load investors.') + '</p></div>';
                ['piPanelActive','piPanelInactive','piPanelAll','piPanelRequestors','piPanelClosed'].forEach(function(id){
                    var p = document.getElementById(id); if (p) p.innerHTML = html;
                });
                return;
            }
            renderInvestorsPanels(d);
        });
    };

    window.piOpenInvTradesModal = function (investorId, subAccountId, name) {
        var modal = document.getElementById('piInvTradesModal');
        if (!modal) return;

        document.getElementById('piInvTradesAvatar').textContent = (name || 'I').charAt(0).toUpperCase();
        document.getElementById('piInvTradesName').textContent = name || 'Investor';
        document.getElementById('piInvTradesDates').textContent = '—';

        var body = document.getElementById('piInvTradesBody');
        body.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading trades…</p></div>';
        body.scrollTop = 0;

        modal.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);

        post({ action: 'get_investor_trades', investor_id: investorId, sub_account_id: subAccountId || 0 }, function (d) {
            if (!d || !d.success) {
                body.innerHTML = '<div class="pi-empty"><i class="fa-solid fa-triangle-exclamation"></i><strong>Error</strong><p>' + escapeHtml((d && d.message) || 'Failed to load trades.') + '</p></div>';
                return;
            }
            document.getElementById('piInvTradesAvatar').textContent = (d.initial || (name || 'I').charAt(0)).toUpperCase();
            document.getElementById('piInvTradesName').textContent = d.name || name || 'Investor';
            if (d.start_date || d.end_date) {
                document.getElementById('piInvTradesDates').textContent = (d.start_date || '—') + '  →  ' + (d.end_date || '—');
            } else {
                document.getElementById('piInvTradesDates').textContent = '—';
            }
            body.innerHTML = d.trades_html || '';
            body.scrollTop = 0;
        });
    };

    window.piCloseInvTradesModal = function () {
        var modal = document.getElementById('piInvTradesModal');
        if (modal) modal.classList.remove('open');
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    var invTradesModalEl = document.getElementById('piInvTradesModal');
    if (invTradesModalEl) {
        invTradesModalEl.addEventListener('click', function (e) {
            if (e.target === invTradesModalEl) piCloseInvTradesModal();
        });
    }

    var liveStateInterval = null;
    var liveStateRunning = false;
    var liveFailCount = 0;
    var LIVE_BASE_INTERVAL = 2000;
    var LIVE_MAX_INTERVAL = 15000;
    var PI_STATE_LAST = null;

    function fetchLiveState() {
        if (!liveStateRunning) return;
        post({ action: 'get_investors' }, function (d) {
            if (d && d.success) {
                liveFailCount = 0;
                renderInvestorsPanels(d);
                PI_STATE_LAST = d;
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
        if (liveFailCount === 1) delay = 5000;
        else if (liveFailCount === 2) delay = 8000;
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
    window.addEventListener('focus', function () { if (liveStateRunning) fetchLiveState(); });
    window.addEventListener('beforeunload', function () { stopLiveState(); });

    var HAS_PROGRAMME = <?= $programme ? 'true' : 'false' ?>;
    if (HAS_PROGRAMME) startLiveState();

    var INITIAL_STATE = <?= json_encode($initialInvestorsState) ?>;
    if (INITIAL_STATE && INITIAL_STATE.success) {
        renderInvestorsPanels(INITIAL_STATE);
    }

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
        if (e.data.type === 'switchInvestorsTab' && e.data.tab) {
            piSwitchTab(e.data.tab);
        }
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
})();
</script>

</body>
</html>