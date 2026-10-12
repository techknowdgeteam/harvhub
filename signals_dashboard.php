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

// ============================================================
// >>> WORLD TIMEZONES (alphabetically sorted)
// ============================================================
function getTimezoneOptions(): array {
    return [
        'Africa' => [
            'Africa/Abidjan'      => 'Abidjan (GMT)',
            'Africa/Accra'        => 'Accra (GMT)',
            'Africa/Addis_Ababa'  => 'Addis Ababa (EAT, UTC+3)',
            'Africa/Algiers'      => 'Algiers (CET, UTC+1)',
            'Africa/Cairo'        => 'Cairo (EET, UTC+2)',
            'Africa/Casablanca'   => 'Casablanca (WET, UTC+1)',
            'Africa/Johannesburg' => 'Johannesburg (SAST, UTC+2)',
            'Africa/Lagos'        => 'Lagos (WAT, UTC+1)',
            'Africa/Nairobi'      => 'Nairobi (EAT, UTC+3)',
            'Africa/Tripoli'      => 'Tripoli (EET, UTC+2)',
            'Africa/Tunis'        => 'Tunis (CET, UTC+1)',
            'Africa/Windhoek'     => 'Windhoek (CAT, UTC+2)',
        ],
        'America' => [
            'America/Anchorage'              => 'Anchorage (AKST, UTC-9)',
            'America/Argentina/Buenos_Aires' => 'Buenos Aires (ART, UTC-3)',
            'America/Bogota'                 => 'Bogota (COT, UTC-5)',
            'America/Caracas'                => 'Caracas (VET, UTC-4)',
            'America/Chicago'                => 'Chicago (CST, UTC-6)',
            'America/Denver'                 => 'Denver (MST, UTC-7)',
            'America/Halifax'                => 'Halifax (AST, UTC-4)',
            'America/Lima'                   => 'Lima (PET, UTC-5)',
            'America/Los_Angeles'            => 'Los Angeles (PST, UTC-8)',
            'America/Mexico_City'            => 'Mexico City (CST, UTC-6)',
            'America/New_York'               => 'New York (EST, UTC-5)',
            'America/Phoenix'                => 'Phoenix (MST, UTC-7)',
            'America/Santiago'               => 'Santiago (CLT, UTC-4)',
            'America/Sao_Paulo'              => 'Sao Paulo (BRT, UTC-3)',
            'America/St_Johns'               => 'St. Johns (NST, UTC-3:30)',
            'America/Toronto'                => 'Toronto (EST, UTC-5)',
            'America/Vancouver'              => 'Vancouver (PST, UTC-8)',
        ],
        'Asia' => [
            'Asia/Almaty'       => 'Almaty (ALMT, UTC+6)',
            'Asia/Baghdad'      => 'Baghdad (AST, UTC+3)',
            'Asia/Bangkok'      => 'Bangkok (ICT, UTC+7)',
            'Asia/Colombo'      => 'Colombo (IST, UTC+5:30)',
            'Asia/Dhaka'        => 'Dhaka (BST, UTC+6)',
            'Asia/Dubai'        => 'Dubai (GST, UTC+4)',
            'Asia/Hong_Kong'    => 'Hong Kong (HKT, UTC+8)',
            'Asia/Jakarta'      => 'Jakarta (WIB, UTC+7)',
            'Asia/Jerusalem'    => 'Jerusalem (IST, UTC+2)',
            'Asia/Karachi'      => 'Karachi (PKT, UTC+5)',
            'Asia/Kathmandu'    => 'Kathmandu (NPT, UTC+5:45)',
            'Asia/Kolkata'      => 'Kolkata / Mumbai (IST, UTC+5:30)',
            'Asia/Kuala_Lumpur' => 'Kuala Lumpur (MYT, UTC+8)',
            'Asia/Manila'       => 'Manila (PHT, UTC+8)',
            'Asia/Riyadh'       => 'Riyadh (AST, UTC+3)',
            'Asia/Seoul'        => 'Seoul (KST, UTC+9)',
            'Asia/Shanghai'     => 'Shanghai (CST, UTC+8)',
            'Asia/Singapore'    => 'Singapore (SGT, UTC+8)',
            'Asia/Taipei'       => 'Taipei (CST, UTC+8)',
            'Asia/Tehran'       => 'Tehran (IRST, UTC+3:30)',
            'Asia/Tokyo'        => 'Tokyo (JST, UTC+9)',
        ],
        'Australia / Pacific' => [
            'Australia/Adelaide'  => 'Adelaide (ACST, UTC+9:30)',
            'Australia/Brisbane'  => 'Brisbane (AEST, UTC+10)',
            'Australia/Darwin'    => 'Darwin (ACST, UTC+9:30)',
            'Australia/Hobart'    => 'Hobart (AEDT, UTC+11)',
            'Australia/Melbourne' => 'Melbourne (AEDT, UTC+11)',
            'Australia/Perth'     => 'Perth (AWST, UTC+8)',
            'Australia/Sydney'    => 'Sydney (AEDT, UTC+11)',
            'Pacific/Auckland'    => 'Auckland (NZDT, UTC+13)',
            'Pacific/Fiji'        => 'Fiji (FJT, UTC+12)',
            'Pacific/Honolulu'    => 'Honolulu (HST, UTC-10)',
        ],
        'Europe' => [
            'Europe/Amsterdam'  => 'Amsterdam (CET, UTC+1)',
            'Europe/Athens'     => 'Athens (EET, UTC+2)',
            'Europe/Berlin'     => 'Berlin (CET, UTC+1)',
            'Europe/Brussels'   => 'Brussels (CET, UTC+1)',
            'Europe/Bucharest'  => 'Bucharest (EET, UTC+2)',
            'Europe/Budapest'   => 'Budapest (CET, UTC+1)',
            'Europe/Copenhagen' => 'Copenhagen (CET, UTC+1)',
            'Europe/Dublin'     => 'Dublin (GMT, UTC+0)',
            'Europe/Helsinki'   => 'Helsinki (EET, UTC+2)',
            'Europe/Istanbul'   => 'Istanbul (TRT, UTC+3)',
            'Europe/Kyiv'       => 'Kyiv (EET, UTC+2)',
            'Europe/Lisbon'     => 'Lisbon (WET, UTC+0)',
            'Europe/London'     => 'London (GMT, UTC+0)',
            'Europe/Madrid'     => 'Madrid (CET, UTC+1)',
            'Europe/Moscow'     => 'Moscow (MSK, UTC+3)',
            'Europe/Oslo'       => 'Oslo (CET, UTC+1)',
            'Europe/Paris'      => 'Paris (CET, UTC+1)',
            'Europe/Prague'     => 'Prague (CET, UTC+1)',
            'Europe/Rome'       => 'Rome (CET, UTC+1)',
            'Europe/Stockholm'  => 'Stockholm (CET, UTC+1)',
            'Europe/Vienna'     => 'Vienna (CET, UTC+1)',
            'Europe/Warsaw'     => 'Warsaw (CET, UTC+1)',
            'Europe/Zurich'     => 'Zurich (CET, UTC+1)',
        ],
        'UTC / Global' => [
            'UTC' => 'UTC (Coordinated Universal Time)',
        ],
    ];
}

function timezoneIsValid(string $tz): bool {
    if ($tz === '') return false;
    foreach (getTimezoneOptions() as $group) {
        if (isset($group[$tz])) return true;
    }
    return false;
}

function syncInvestorProfitsIntoDeals($pdo) {
    try {
        $q = $pdo->query("
            SELECT d.contract_id, d.investor_id, d.investor_sub_account_id, d.accepted_at, d.contract_duration,
                   d.investor_profit, h.profitandloss
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

function getSignalProviderInterest($pdo, $userId, $programmeId) {
    try {
        $q = $pdo->prepare("SELECT * FROM signals_provider_interest WHERE user_id = ? AND programme_id = ? ORDER BY id DESC LIMIT 1");
        $q->execute([$userId, $programmeId]);
        return $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function getServerAccountConfig($pdo) {
    try {
        $q = $pdo->query("SELECT * FROM server_account WHERE id = 1 LIMIT 1");
        return $q->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function countProgrammeVpsRequests($pdo, $programmeId) {
    try {
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM programme_vps_hosts_requestors
            WHERE owner_programme_id = ? AND request_status = 'pending'
        ");
        $q->execute([$programmeId]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function countProgrammeInvestmentRequests($pdo, $userId, $programmeId) {
    try {
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM programme_investment_requestors
            WHERE developerid = ? AND programme_id = ? AND request_status = 'pending'
        ");
        $q->execute([$userId, $programmeId]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function syncInterestRiskRewardIntoTrades($pdo, $userId, $programmeId, $interest) {
    if (!$interest) return;

    $type  = $interest['risk_reward_type'] ?? null;
    $value = $interest['risk_reward']      ?? null;
    if ($type === null && $value === null) return;

    try {
        $u = $pdo->prepare("
            UPDATE programme_trades
            SET interest_risk_reward_type = ?,
                interest_risk_reward      = ?
            WHERE userid = ? AND programmeid = ?
              AND (
                    interest_risk_reward_type IS NULL OR interest_risk_reward_type <> ?
                 OR interest_risk_reward      IS NULL OR interest_risk_reward      <> ?
              )
        ");
        $u->execute([$type, $value, $userId, $programmeId, $type, $value]);
    } catch (Throwable $e) {}
}

function normalizeInterestRiskRewardType($raw) {
    $s = trim((string)$raw);
    if ($s === '') return '—';
    $s = str_replace('_', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

// ============================================================
// >>> SYNC ENGINE (shared)
// ============================================================

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

/**
 * Count distinct investors currently enrolled in a programme.
 */
function getAllInvestorsCountForProgramme($pdo, $programmeId) {
    try {
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT DISTINCT
                    pi.investorid AS investor_id,
                    COALESCE(pi.sub_account_id, 0) AS sub_account_id
                FROM programme_investors pi
                INNER JOIN programme_revenue_deals d
                        ON d.programme_id = pi.programme_id
                       AND d.investor_id = pi.investorid
                       AND COALESCE(d.investor_sub_account_id, 0) = COALESCE(pi.sub_account_id, 0)
                WHERE pi.programme_id = ?
                  AND pi.investorid > 0
            ) AS enrolled
        ");
        $q->execute([$programmeId]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        try {
            $q = $pdo->prepare("
                SELECT COUNT(DISTINCT investor_id)
                FROM programme_revenue_deals
                WHERE programme_id = ? AND investor_id > 0
            ");
            $q->execute([$programmeId]);
            return (int)$q->fetchColumn();
        } catch (Throwable $e2) {
            return 0;
        }
    }
}

function syncProgrammeTradesToInvestors($pdo, $userId, $programmeId) {
    try {
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

        $activeInvestors = getActiveInvestorsForProgramme($pdo, $programmeId);

        $activePairs = [];
        foreach ($activeInvestors as $inv) {
            $invId = (int)$inv['investor_id'];
            $subId = (int)$inv['sub_account_id'];
            if ($invId <= 0 || $subId <= 0) continue;
            $activePairs[$invId . '|' . $subId] = true;
        }
        $activePairs = array_keys($activePairs);

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

function propagateTradeToInvestors($pdo, $userId, $programmeId, $tradeId, $changes) {
    if (empty($changes)) return;

    try {
        $q = $pdo->prepare("
            SELECT tradeid
            FROM programme_trades
            WHERE id = ? AND userid = ? AND programmeid = ?
              AND investor_id IS NULL
            LIMIT 1
        ");
        $q->execute([$tradeId, $userId, $programmeId]);
        $tradeid = $q->fetchColumn();
    } catch (Throwable $e) { return; }
    if (!$tradeid) return;

    $set = []; $params = [];
    foreach ($changes as $col => $val) {
        $set[] = "`$col` = ?";
        $params[] = $val;
    }
    $params[] = $tradeid;
    $params[] = $programmeId;

    try {
        $u = $pdo->prepare("
            UPDATE programme_trades
            SET " . implode(', ', $set) . "
            WHERE tradeid = ?
              AND programmeid = ?
              AND investor_id IS NOT NULL
        ");
        $u->execute($params);
    } catch (Throwable $e) {}
}

function determineSignalsDashboardState($pdo, $userId, $programme, $interest, $serverConfig, $hasVps, $hasBroker) {
    $state = [
        'advertisement' => 0,
        'interest_status' => '',
        'begin_test' => 0,
        'signal_testing_started_at' => null,
        'show_explore_challenges' => false,
        'show_begin_test' => false,
        'show_session_modal' => false,
        'session_modal_title' => '',
        'session_modal_message' => '',
        'session_modal_class' => 'info',
        'session_modal_key' => '',
        'greeting_sub' => '',
        'greeting_sub_html' => '',
        'greeting_banners_html' => '',
        'vps_request_count' => 0,
        'incoming_request_count' => 0,
    ];

    if (!$programme) return $state;

    $advertisement = (int)($programme['advertisement'] ?? 0);
    $interestStatus = $interest ? (string)($interest['interest_status'] ?? '') : '';
    $beginTest = $interest ? (int)($interest['begin_test'] ?? 0) : 0;
    $signalTestingStartedAt = $interest ? ($interest['signal_testing_started_at'] ?? null) : null;

    $state['advertisement'] = $advertisement;
    $state['interest_status'] = $interestStatus;
    $state['begin_test'] = $beginTest;
    $state['signal_testing_started_at'] = $signalTestingStartedAt;

    $programmeId = (int)$programme['id'];

    $state['incoming_request_count'] = countProgrammeInvestmentRequests($pdo, $userId, $programmeId);

    $ownsVps = false;
    try {
        $s = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
        $s->execute([$programmeId]);
        $ownsVps = (bool)$s->fetchColumn();
    } catch (Throwable $e) {}

    if ($ownsVps) {
        $state['vps_request_count'] = countProgrammeVpsRequests($pdo, $programmeId);
    }

    $noInterest = empty($interest) || $interestStatus === '' || $interestStatus === 'breached' || $interestStatus === 'failed';

    $buildChallengeChevron = function ($periodLabel) {
        $label = trim((string)$periodLabel);
        if ($label === '') $label = 'Signals';
        $safe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        return '<button type="button" class="sd-challenge-chevron" onclick="sdGoToChallenges()">'
             .   '<span class="sd-challenge-chevron-text">In a ' . $safe . ' signals challenge</span>'
             .   '<span class="sd-challenge-chevron-right"><i class="fa-solid fa-chevron-right"></i></span>'
             . '</button>';
    };

    $periodLabel = '';
    if ($interest) {
        $period = strtolower((string)($interest['trades_provision'] ?? ''));
        if ($period === 'daily')   $periodLabel = 'Daily';
        elseif ($period === 'weekly')  $periodLabel = 'Weekly';
        elseif ($period === 'monthly') $periodLabel = 'Monthly';
    }

    if ($advertisement === 0 && $noInterest) {
        if (!$hasVps) {
            $state['greeting_sub'] = "Your programme won't generate revenue while you have no VPS.";
        } elseif (!$hasBroker) {
            $state['greeting_sub'] = "Your programme won't generate revenue — no broker details to analyze your programme training.";
        } else {
            $state['show_explore_challenges'] = true;
            $state['greeting_sub'] = "Explore challenges to start your signal provision journey.";
        }
    }

    if ($advertisement === 1 && $interestStatus === 'breached') {
        $state['show_explore_challenges'] = true;
        $state['show_session_modal'] = true;
        $state['session_modal_title'] = 'Challenge Breached';
        $state['session_modal_message'] = 'Your programme has breached the challenge request. You need to train your programme or join a new challenge.';
        $state['session_modal_class'] = 'danger';
        $state['session_modal_key']   = 'breached:' . $programmeId;
        $state['greeting_sub'] = "Your programme breached the challenge. Train or join a new challenge.";
    }

    if ($advertisement === 0 && $interestStatus === 'failed') {
        $state['show_explore_challenges'] = true;
        $state['show_session_modal'] = true;
        $state['session_modal_title'] = 'Challenge Failed';
        $state['session_modal_message'] = 'Your programme failed to meet up the challenge. Train your programme or join a new challenge.';
        $state['session_modal_class'] = 'warning';
        $state['session_modal_key']   = 'failed:' . $programmeId;
        $state['greeting_sub'] = "Your programme failed the challenge. Train or join a new challenge.";
    }

    if ($advertisement === 0 && $interestStatus === 'passed') {
        try {
            $upd = $pdo->prepare("UPDATE programme SET advertisement = 1 WHERE id = ?");
            $upd->execute([$programmeId]);
            $state['advertisement'] = 1;
        } catch (Throwable $e) {}
    }

    if ($advertisement === 1 && $interestStatus === 'passed') {
        $progName = $programme['program_name'] ?: 'Programme';
        if (!$hasVps || !$hasBroker) {
            $missing = !$hasVps ? 'VPS' : 'broker';
            $state['greeting_sub'] = "Your programme is still earning but to get current market analysis you have to connect {$missing}.";
        } else {
            $state['greeting_sub'] = "{$progName} is now advertised to investors. You will earn from investor's profit once they invest in this programme.";
        }
    }

    if ($advertisement === 0 && $interestStatus === 'interested' && $beginTest === 0) {
        if ($hasVps && $hasBroker) {
            $state['show_begin_test'] = true;
            $state['show_session_modal'] = true;
            $state['session_modal_title'] = 'Begin Your Challenge Test';
            $state['session_modal_message'] = 'You have shown interest in a challenge. Ensure you begin test after training your programme to perfectly deliver your challenge request.';
            $state['session_modal_class'] = 'info';
            $interestId = $interest ? (int)($interest['id'] ?? 0) : 0;
            $state['session_modal_key']   = 'begin_test:' . $programmeId . ':' . $interestId;
            $state['greeting_sub'] = '';
            $state['greeting_sub_html'] = $buildChallengeChevron($periodLabel ?: 'Daily');
        } else {
            $missing = !$hasVps ? 'VPS' : 'broker';
            $state['greeting_sub'] = "Connect your {$missing} to begin testing your programme.";
        }
    }

    if ($advertisement === 0 && $interestStatus === 'ongoing') {
        $state['greeting_sub'] = '';
        $state['greeting_sub_html'] = $buildChallengeChevron($periodLabel ?: 'Daily');
    }

    if ($state['greeting_sub_html'] === '') {
        $state['greeting_sub_html'] = $state['greeting_sub'];
    }

    $bannersHtml = '';

    if (!$hasVps && $advertisement === 0) {
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
    } elseif (!$hasBroker && $advertisement === 0) {
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
    }

    if ($state['show_explore_challenges']) {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner sd-explore-challenges" onclick="sdGoToChallenges()">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-trophy"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Explore Challenges</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">Join a challenge to start earning</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
    }

    if ($state['show_begin_test']) {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner sd-begin-test" onclick="sdOpenBeginTestModal()">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-play"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Begin Test</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">Start comparing your trades with challenge interest</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right"><i class="fa-solid fa-chevron-right sd-incoming-chev"></i></span>';
        $bannersHtml .= '</button>';
    }

    if ((int)$state['incoming_request_count'] > 0) {
        $reqCount = (int)$state['incoming_request_count'];
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner sd-investment-requests" onclick="sdGoToInvestorsRequests()">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">Investment Requests</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">' . $reqCount . ' pending request' . ($reqCount === 1 ? '' : 's') . '</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right">';
        $bannersHtml .=     '<span class="sd-incoming-count">' . $reqCount . '</span>';
        $bannersHtml .=     '<i class="fa-solid fa-chevron-right sd-incoming-chev"></i>';
        $bannersHtml .=   '</span>';
        $bannersHtml .= '</button>';
    }

    if ($state['vps_request_count'] > 0) {
        $bannersHtml .= '<div class="sd-greeting-divider"></div>';
        $bannersHtml .= '<button type="button" class="sd-incoming-banner sd-vps-requests" onclick="harvhubGoToTab(\'vps\')">';
        $bannersHtml .=   '<span class="sd-incoming-left">';
        $bannersHtml .=     '<span class="sd-incoming-icon"><i class="fa-solid fa-server"></i></span>';
        $bannersHtml .=     '<span class="sd-incoming-label">';
        $bannersHtml .=       '<span class="sd-incoming-title">VPS Space Requests</span>';
        $bannersHtml .=       '<span class="sd-incoming-sub">' . (int)$state['vps_request_count'] . ' pending request' . ($state['vps_request_count'] === 1 ? '' : 's') . '</span>';
        $bannersHtml .=     '</span>';
        $bannersHtml .=   '</span>';
        $bannersHtml .=   '<span class="sd-incoming-right">';
        $bannersHtml .=     '<span class="sd-incoming-count">' . (int)$state['vps_request_count'] . '</span>';
        $bannersHtml .=     '<i class="fa-solid fa-chevron-right sd-incoming-chev"></i>';
        $bannersHtml .=   '</span>';
        $bannersHtml .= '</button>';
    }

    $state['greeting_banners_html'] = $bannersHtml;

    return $state;
}

/**
 * Renders programme trade cards.
 */
function renderProgrammeTradesHtml(array $trades, array $activeIds = []) {
    if (!$trades) {
        return '<div class="sd-empty"><i class="fa-solid fa-chart-simple"></i><strong>No trades recorded yet</strong><p>Trades will appear here once they are generated.</p></div>';
    }

    $programmeRows = [];
    foreach ($trades as $t) {
        if (isset($t['investor_id']) && $t['investor_id'] !== null && (int)$t['investor_id'] > 0) continue;
        $programmeRows[] = $t;
    }

    $investorStats = [];
    foreach ($trades as $t) {
        if (!isset($t['investor_id']) || $t['investor_id'] === null || (int)$t['investor_id'] <= 0) continue;

        $invId = (int)$t['investor_id'];
        $subId = (int)($t['investor_sub_account_id'] ?? 0);
        $pairKey = $invId . '|' . $subId;
        if (!empty($activeIds) && empty($activeIds[$pairKey])) continue;

        $tid = (string)($t['tradeid'] ?? '');
        if ($tid === '') continue;
        if (!isset($investorStats[$tid])) $investorStats[$tid] = ['success' => 0, 'failed' => 0, 'total' => 0];
        $st = strtolower(trim((string)($t['orderstatus'] ?? '')));
        $investorStats[$tid]['total']++;
        if ($st === 'failed') $investorStats[$tid]['failed']++;
        else                  $investorStats[$tid]['success']++;
    }

    $groups = ['running' => [], 'pending' => [], 'closed' => [], 'failed' => []];

    foreach ($programmeRows as $t) {
        $status = strtolower(trim((string)($t['orderstatus'] ?? '')));
        $tid    = (string)($t['tradeid'] ?? '');

        if ($status === 'running')      $groups['running'][] = $t;
        elseif ($status === 'pending')  $groups['pending'][] = $t;
        elseif ($status === 'closed')   $groups['closed'][]  = $t;
        elseif ($status === 'failed')   $groups['failed'][]  = $t;
        else                            $groups['pending'][] = $t;

        $stats = ($tid !== '' && isset($investorStats[$tid])) ? $investorStats[$tid] : null;

        if ($status !== 'failed' && $stats && $stats['failed'] > 0) {
            $groups['failed'][] = $t;
        }
        if ($status !== 'running' && $stats && $stats['success'] > 0) {
            if ($status === 'failed') $groups['running'][] = $t;
        }
    }

    foreach ($groups as $k => $list) {
        $seen = []; $dedup = [];
        foreach ($list as $row) {
            $rid = (int)($row['id'] ?? 0);
            if (isset($seen[$rid])) continue;
            $seen[$rid] = true;
            $dedup[] = $row;
        }
        $groups[$k] = $dedup;
    }

    $labels = ['running' => 'Running', 'pending' => 'Pending', 'closed' => 'Closed', 'failed' => 'Failed'];

    $html  = '<div class="sd-trade-tabs">';
    foreach ($labels as $key => $label) {
        $count = count($groups[$key]);
        $active = ($key === 'running') ? ' active' : '';
        $html .= '<button type="button" class="sd-trade-tab' . $active . '" data-trade-tab="' . $key . '" onclick="sdSwitchTradeTab(\'' . $key . '\')">';
        $html .=   $label . ' <span class="sd-trade-tab-count">' . $count . '</span>';
        $html .= '</button>';
    }
    $html .= '</div>';

    foreach ($labels as $key => $label) {
        $panelActive = ($key === 'running') ? ' active' : '';
        $html .= '<div class="sd-trade-panel' . $panelActive . '" id="sdTradePanel-' . $key . '">';

        if (empty($groups[$key])) {
            $html .= '<div class="sd-empty"><i class="fa-solid fa-inbox"></i><strong>No ' . strtolower($label) . ' trades</strong><p>Trades with orderstatus "' . $label . '" will appear here.</p></div>';
        } else {
            $html .= '<div class="sd-trades-scroll">';
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

                $statusLower = strtolower((string)($t['orderstatus'] ?? ''));
                $statusClass = 'sd-order-status';
                if ($statusLower === 'running')      $statusClass .= ' is-running';
                elseif ($statusLower === 'pending')  $statusClass .= ' is-pending';
                elseif ($statusLower === 'closed')   $statusClass .= ' is-closed';
                elseif ($statusLower === 'failed')   $statusClass .= ' is-failed';

                $tid = (string)($t['tradeid'] ?? '');
                $stats = ($tid !== '' && isset($investorStats[$tid])) ? $investorStats[$tid] : ['success' => 0, 'failed' => 0, 'total' => 0];
                $showInvestorCounts = in_array($key, ['running', 'failed'], true) && $stats['total'] > 0;

                $html .= '<div class="sd-trade-modal-card" data-trade-id="' . (int)$t['id'] . '">';
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

                if ($showInvestorCounts) {
                    $html .= '<div style="grid-column:1/-1;">';
                    $html .=   '<div class="sd-t-label">Investor breakdown</div>';
                    $html .=   '<div class="sd-inv-breakdown">';
                    $html .=     '<span class="sd-inv-count sd-inv-count-success"><i class="fa-solid fa-circle-check"></i> success for ' . (int)$stats['success'] . ' investor' . ($stats['success'] === 1 ? '' : 's') . '</span>';
                    $html .=     '<span class="sd-inv-count sd-inv-count-failed"><i class="fa-solid fa-circle-xmark"></i> failed for ' . (int)$stats['failed'] . ' investor' . ($stats['failed'] === 1 ? '' : 's') . '</span>';
                    $html .=   '</div>';
                    $html .= '</div>';
                }

                $html .=   '</div>';

                $modifyVal = (string)($t['modify_trade'] ?? '');
                if ($key === 'running') {
                    $html .=   '<div class="sd-trade-modify">';
                    $html .=     '<div class="sd-t-label">Modify trade</div>';
                    $html .=     '<select class="sd-modify-select" data-trade-id="' . (int)$t['id'] . '">';
                    $html .=       '<option value="">Select action</option>';
                    $html .=       '<option value="breakeven"' . ($modifyVal === 'breakeven' ? ' selected' : '') . '>Breakeven</option>';
                    $html .=       '<option value="close-position"' . ($modifyVal === 'close-position' ? ' selected' : '') . '>Close Position</option>';
                    $html .=     '</select>';
                    $html .=   '</div>';
                } elseif ($key === 'pending') {
                    $html .=   '<div class="sd-trade-modify">';
                    $html .=     '<div class="sd-t-label">Modify trade</div>';
                    $html .=     '<select class="sd-modify-select" data-trade-id="' . (int)$t['id'] . '">';
                    $html .=       '<option value="">Select action</option>';
                    $html .=       '<option value="delete-order"' . ($modifyVal === 'delete-order' ? ' selected' : '') . '>Delete Order</option>';
                    $html .=     '</select>';
                    $html .=   '</div>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    return $html;
}

function fetchActiveProgrammeTrades($pdo, $userId, $programmeId, $limit = 400) {
    $trades = [];
    try {
        $s = $pdo->prepare("
            SELECT t.*
            FROM programme_trades t
            WHERE t.userid = ? AND t.programmeid = ?
            ORDER BY t.created_at DESC
            LIMIT " . (int)$limit . "
        ");
        $s->execute([$userId, $programmeId]);
        $trades = $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
    return $trades;
}

/**
 * Purge `programme_revenue_deals` rows whose (investor_id, sub_account_id)
 * pair no longer has a matching row in `programme_investors` for the same
 * programme.
 */
function purgeOrphanedRevenueDeals($pdo, $programmeId) {
    try {
        $del = $pdo->prepare("
            DELETE d
            FROM programme_revenue_deals d
            LEFT JOIN programme_investors pi
                   ON pi.programme_id = d.programme_id
                  AND pi.investorid = d.investor_id
                  AND COALESCE(pi.sub_account_id, 0) = COALESCE(d.investor_sub_account_id, 0)
            WHERE d.programme_id = ?
              AND d.investor_id > 0
              AND pi.id IS NULL
        ");
        $del->execute([$programmeId]);
    } catch (Throwable $e) {
        // Silent — best-effort cleanup.
    }
}

// ============================================================================
// >>> Programme Status card renderer — Broker tile FIRST, then Symbols,
// then Timeframes, then Timezone.
// ============================================================================
function renderProgrammeStatusHtml($programme, $hasBroker, $symbols, $timeframes, $timezone) {
    $statusHtml = '';
    $statusHtml .= '<div class="sd-status-grid">';

    // 1) Broker
    $statusHtml .= '<div class="sd-status-tile">';
    $statusHtml .=   '<div class="sd-status-tile-label"><i class="fa-solid fa-building-columns"></i> Broker</div>';
    $statusHtml .=   '<div class="sd-status-tile-value">' . ($hasBroker ? esc_h($programme['broker']) : '—') . '</div>';
    $statusHtml .= '</div>';

    // 2) Symbols
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

    // 3) Timeframes
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

    // 4) Timezone
    $statusHtml .= '<div class="sd-status-tile">';
    $statusHtml .=   '<div class="sd-status-tile-label"><i class="fa-solid fa-globe"></i> Timezone</div>';
    $statusHtml .=   '<div class="sd-status-tile-value">' . ($timezone !== '' ? esc_h($timezone) : '—') . '</div>';
    $statusHtml .= '</div>';

    $statusHtml .= '</div>';
    return $statusHtml;
}

// ============================================================================
// >>> Renders the settings-modal symbol + timeframe checkbox lists.
// Used both on initial server render AND from the JS live-update path.
// ============================================================================
function renderSymbolChecklistHtml(array $allBrokerSymbols, array $selectedSymbols): string {
    if (empty($allBrokerSymbols)) {
        return '<p class="sd-sub">No symbols available.</p>';
    }
    $selectedLookup = [];
    foreach ($selectedSymbols as $s) $selectedLookup[$s] = true;

    $html = '';
    foreach ($allBrokerSymbols as $sym) {
        $checked = isset($selectedLookup[$sym]) ? ' checked' : '';
        $html .= '<label class="sd-check-row">';
        $html .=   '<input type="checkbox" value="' . esc_h($sym) . '" class="sd-settings-symbol-cb"' . $checked . '>';
        $html .=   '<span>' . esc_h($sym) . '</span>';
        $html .= '</label>';
    }
    return $html;
}

function renderTimeframeChecklistHtml(array $availableTimeframes, array $selectedTimeframes): string {
    if (empty($availableTimeframes)) {
        return '<p class="sd-sub">No timeframes available.</p>';
    }
    $selectedLookup = [];
    foreach ($selectedTimeframes as $t) $selectedLookup[$t] = true;

    $html = '';
    foreach ($availableTimeframes as $tf) {
        $checked = isset($selectedLookup[$tf]) ? ' checked' : '';
        $html .= '<label class="sd-check-row">';
        $html .=   '<input type="checkbox" value="' . esc_h($tf) . '" class="sd-settings-tf-cb"' . $checked . '>';
        $html .=   '<span>' . esc_h($tf) . '</span>';
        $html .= '</label>';
    }
    return $html;
}

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
        'timezone' => '',
        'revenue_amount' => '0.00',
        'investor_count' => 0,
        'active_investor_count' => 0,
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
        // >>> broker metadata exposed for live AJAX updates
        'broker_symbols' => [],
        'broker_timeframes' => [],
        'broker_symbols_count' => 0,
        'broker_timeframes_count' => 0,
        'trades' => [],
        'trades_html' => '',
        'programme_status_html' => '',
        'greeting_sub_html' => '',
        'greeting_banners_html' => '',
        'signals_state' => [],
        'vps_request_count' => 0,
    ];

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

    syncProgrammeTradesToInvestors($pdo, $userId, $programmeId);

    purgeOrphanedRevenueDeals($pdo, $programmeId);

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

    $hasBroker = (!empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']));
    $state['has_broker'] = $hasBroker;
    $state['broker'] = (string)($programme['broker'] ?? '');
    $state['broker_server'] = (string)($programme['server'] ?? '');
    $state['broker_login'] = (string)($programme['login'] ?? '');
    $state['timezone'] = (string)($programme['timezone'] ?? '');

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

    $state['investor_count'] = getAllInvestorsCountForProgramme($pdo, $programmeId);

    $activeInvestors = getActiveInvestorsForProgramme($pdo, $programmeId);
    $activeInvestorIdSet = [];
    foreach ($activeInvestors as $inv) {
        $invId = (int)$inv['investor_id'];
        $subId = (int)($inv['sub_account_id'] ?? 0);
        if ($invId <= 0 || $subId <= 0) continue;
        $activeInvestorIdSet[$invId . '|' . $subId] = true;
    }
    $state['active_investor_count'] = count($activeInvestorIdSet);

    $revenueAmount = 0.0;
    try {
        $s = $pdo->prepare("
            SELECT h.profitandloss
            FROM programme_investors pi
            INNER JOIN harvhub h
                    ON h.id = pi.investorid
                   AND (pi.sub_account_id IS NULL OR pi.sub_account_id = 0 OR h.sub_account_id = pi.sub_account_id)
            WHERE pi.developerid = ?
              AND pi.programme_id = ?
              AND pi.investorid > 0
        ");
        $s->execute([$userId, $programmeId]);
        while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            $pnl = (float)($r['profitandloss'] ?? 0);
            if ($pnl > 0) $revenueAmount += ($pnl * $developerPercentage / 100);
        }
    } catch (Throwable $e) {}
    $state['revenue_amount'] = number_format($revenueAmount, 2, '.', '');

    $incomingRequestCount = countProgrammeInvestmentRequests($pdo, $userId, $programmeId);
    $state['incoming_request_count'] = $incomingRequestCount;

    // ============================================================
    // >>> BROKER METADATA — read the fresh snapshot first.
    // This is the source of truth for both the settings picker
    // and the "selected" chips cleanup below.
    // ============================================================
    $brokerSymbols    = parseCleanList((string)($programme['broker_symbols']    ?? ''));
    $brokerTimeframes = parseCleanList((string)($programme['broker_timeframes'] ?? ''));
    sort($brokerSymbols);
    sort($brokerTimeframes);

    $state['broker_symbols']          = $brokerSymbols;
    $state['broker_timeframes']       = $brokerTimeframes;
    $state['broker_symbols_count']    = count($brokerSymbols);
    $state['broker_timeframes_count'] = count($brokerTimeframes);

    // ============================================================
    // >>> CLEANUP: if broker_symbols is empty, selected_symbols
    // must also be empty for this programme. Same for timeframes.
    // This keeps the DB consistent when a broker is disconnected
    // or its metadata hasn't been collected yet.
    // ============================================================
    $symbols    = parseCleanList((string)($programme['selected_symbols']    ?? ''));
    $timeframes = parseCleanList((string)($programme['selected_timeframes'] ?? ''));

    $symbolsChanged    = false;
    $timeframesChanged = false;

    if (empty($brokerSymbols) && !empty($symbols)) {
        // broker list is empty but user has selections — wipe them
        $symbols = [];
        try {
            $updSym = $pdo->prepare("UPDATE programme SET selected_symbols = NULL WHERE id = ? AND userid = ?");
            $updSym->execute([$programmeId, $userId]);
            $symbolsChanged = true;
        } catch (Throwable $e) {}
    }

    if (empty($brokerTimeframes) && !empty($timeframes)) {
        // broker timeframe list is empty but user has selections — wipe them
        $timeframes = [];
        try {
            $updTf = $pdo->prepare("UPDATE programme SET selected_timeframes = NULL WHERE id = ? AND userid = ?");
            $updTf->execute([$programmeId, $userId]);
            $timeframesChanged = true;
        } catch (Throwable $e) {}
    }

    // Also: drop any selected symbol that no longer exists in broker_symbols.
    if (!empty($brokerSymbols) && !empty($symbols)) {
        $brokerSymLookup = [];
        foreach ($brokerSymbols as $bs) $brokerSymLookup[$bs] = true;

        $prunedSymbols = [];
        foreach ($symbols as $s) {
            if (isset($brokerSymLookup[$s])) $prunedSymbols[] = $s;
        }

        if (count($prunedSymbols) !== count($symbols)) {
            $symbols = $prunedSymbols;
            try {
                $updSym2 = $pdo->prepare("UPDATE programme SET selected_symbols = ? WHERE id = ? AND userid = ?");
                $updSym2->execute([implode(',', $symbols), $programmeId, $userId]);
                $symbolsChanged = true;
            } catch (Throwable $e) {}
        }
    }

    // Same pruning for timeframes.
    if (!empty($brokerTimeframes) && !empty($timeframes)) {
        $brokerTfLookup = [];
        foreach ($brokerTimeframes as $bt) $brokerTfLookup[$bt] = true;

        $prunedTimeframes = [];
        foreach ($timeframes as $t) {
            if (isset($brokerTfLookup[$t])) $prunedTimeframes[] = $t;
        }

        if (count($prunedTimeframes) !== count($timeframes)) {
            $timeframes = $prunedTimeframes;
            try {
                $updTf2 = $pdo->prepare("UPDATE programme SET selected_timeframes = ? WHERE id = ? AND userid = ?");
                $updTf2->execute([json_encode(array_values($timeframes)), $programmeId, $userId]);
                $timeframesChanged = true;
            } catch (Throwable $e) {}
        }
    }

    // Reflect the (possibly modified) programme row back into the
    // PHP array so the rest of buildLiveState() uses fresh values.
    if ($symbolsChanged)    $programme['selected_symbols']    = empty($symbols)    ? null : implode(',', $symbols);
    if ($timeframesChanged) $programme['selected_timeframes'] = empty($timeframes) ? null : json_encode(array_values($timeframes));

    $state['symbols']    = $symbols;
    $state['timeframes'] = $timeframes;

    // ---- Programme status HTML uses the same symbols/timeframes ----
    $timezone = (string)($programme['timezone'] ?? '');

    $interest = getSignalProviderInterest($pdo, $userId, $programmeId);

    syncInterestRiskRewardIntoTrades($pdo, $userId, $programmeId, $interest);

    $serverConfig = getServerAccountConfig($pdo);
    $signalsState = determineSignalsDashboardState($pdo, $userId, $programme, $interest, $serverConfig, $hasVps, $hasBroker);

    $state['signals_state'] = $signalsState;
    $state['greeting_sub_html'] = $signalsState['greeting_sub_html'];
    $state['greeting_banners_html'] = $signalsState['greeting_banners_html'];
    $state['vps_request_count'] = $signalsState['vps_request_count'];

    $state['programme_status_html'] = renderProgrammeStatusHtml($programme, $hasBroker, $symbols, $timeframes, $timezone);

    $trades = fetchActiveProgrammeTrades($pdo, $userId, $programmeId, 400);
    $state['trades'] = $trades;
    $state['trades_html'] = renderProgrammeTradesHtml($trades, $activeInvestorIdSet);

    return $state;
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'get_live_state') {
        try {
            syncInvestorProfitsIntoDeals($pdo);
            jres(buildLiveState($pdo, $userId, $programme, $email));
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load live state: ' . $e->getMessage()]);
        }
    }

    if ($action === 'begin_test') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $interest = getSignalProviderInterest($pdo, $userId, (int)$programme['id']);
            if (!$interest) jres(['success' => false, 'message' => 'No challenge interest found.']);

            $interestId = (int)$interest['id'];

            $upd = $pdo->prepare("UPDATE signals_provider_interest SET begin_test = 1, interest_status = 'ongoing', signal_testing_started_at = NOW() WHERE id = ?");
            $upd->execute([$interestId]);

            try {
                recordProgrammeNotification($pdo, (int)$programme['id'], $email, [
                    'notification_key' => 'signals-test-started-' . (int)$programme['id'] . '-' . $interestId . '-' . date('YmdHis'),
                    'title'            => 'Challenge Test Started',
                    'message'          => 'Your challenge test has begun.',
                    'type'             => 'success',
                    'section'          => 'Signals',
                    'action_tab'       => 'signals',
                    'force'            => true
                ]);
            } catch (Throwable $e) {}

            jres(['success' => true, 'message' => 'Test started.']);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to start test.']);
        }
    }

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
            $final = [];
            if ($currentRow) $final[] = $currentRow;
            foreach ($list as $item) $final[] = $item;

            jres(['success' => true, 'programmes' => $final]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load request programmes.']);
        }
    }

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

    if ($action === 'save_timezone') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $tz = trim((string)($_POST['timezone'] ?? ''));
            if ($tz !== '' && !timezoneIsValid($tz)) {
                jres(['success' => false, 'message' => 'Invalid timezone selection.']);
            }
            if ($tz === '') $tz = null;

            $upd = $pdo->prepare("UPDATE programme SET timezone = ? WHERE id = ? AND userid = ?");
            $upd->execute([$tz, (int)$programme['id'], $userId]);
            jres(['success' => true, 'timezone' => $tz]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to save timezone.']);
        }
    }

    if ($action === 'save_requirements') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        try {
            $contractDuration = (int)($_POST['contract_duration'] ?? 0);
            $developerPercent = (float)($_POST['developer_percentage'] ?? 0);
            $minInvestment    = (float)($_POST['minimum_investment_amount'] ?? 0);
            $maxInvestment    = (float)($_POST['maximum_investment_amount'] ?? 0);
            $timezone         = trim((string)($_POST['timezone'] ?? ''));

            if ($timezone !== '' && !timezoneIsValid($timezone)) {
                jres(['success' => false, 'message' => 'Invalid timezone selection.']);
            }
            if ($timezone === '') $timezone = null;

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

            try {
                $tzUpd = $pdo->prepare("UPDATE programme SET timezone = ? WHERE id = ? AND userid = ?");
                $tzUpd->execute([$timezone, (int)$programme['id'], $userId]);
            } catch (Throwable $e) {}

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

            jres(['success'=>true, 'developer_percentage'=>$developerPercent, 'investor_percentage'=>$investorPercent, 'timezone'=>$timezone]);
        } catch (Throwable $e) { jres(['success' => false, 'message' => 'Failed to save requirements.']); }
    }

    if ($action === 'modify_trade') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme']);
        $tradeId = (int)($_POST['trade_id'] ?? 0);
        $mode    = (string)($_POST['modify_trade'] ?? '');

        $allowed = ['keep-position', 'breakeven', 'close-position', 'keep-order', 'delete-order'];
        if ($tradeId <= 0 || !in_array($mode, $allowed, true)) {
            jres(['success' => false, 'message' => 'Invalid request.']);
        }

        try {
            $u = $pdo->prepare("
                UPDATE programme_trades
                SET modify_trade = ?
                WHERE id = ? AND userid = ? AND programmeid = ?
                  AND investor_id IS NULL
            ");
            $u->execute([$mode, $tradeId, $userId, (int)$programme['id']]);

            propagateTradeToInvestors($pdo, $userId, (int)$programme['id'], $tradeId, [
                'modify_trade' => $mode,
            ]);

            jres(['success' => true, 'modify_trade' => $mode]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to update trade.']);
        }
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
$activeInvestorIdSet = [];
$timezoneValue = '';

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
    $timezoneValue = (string)($programme['timezone'] ?? '');
    try { $s = $pdo->prepare("SELECT * FROM programme_investors WHERE developerid = ? AND programme_id = ? AND investorid = 0 ORDER BY id DESC LIMIT 1"); $s->execute([$userId, (int)$programme['id']]); $revenueSettings = $s->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable $e) {}
    if ($revenueSettings && $revenueSettings['developer_percentage'] !== null) $developerPercentage = (float)$revenueSettings['developer_percentage'];
    if ($revenueSettings) {
        $contractDuration = isset($revenueSettings['contract_duration']) ? (int)$revenueSettings['contract_duration'] : 0;
        $minInvestment    = isset($revenueSettings['minimum_investment_amount']) ? (float)$revenueSettings['minimum_investment_amount'] : 0.0;
        $maxInvestment    = isset($revenueSettings['maximum_investment_amount']) ? (float)$revenueSettings['maximum_investment_amount'] : 0.0;
    }

    purgeOrphanedRevenueDeals($pdo, (int)$programme['id']);

    $investorCount = getAllInvestorsCountForProgramme($pdo, (int)$programme['id']);

    $activeInvestors = getActiveInvestorsForProgramme($pdo, (int)$programme['id']);
    foreach ($activeInvestors as $inv) {
        $invId = (int)$inv['investor_id'];
        $subId = (int)($inv['sub_account_id'] ?? 0);
        if ($invId <= 0 || $subId <= 0) continue;
        $activeInvestorIdSet[$invId . '|' . $subId] = true;
    }

    try {
        $s = $pdo->prepare("
            SELECT h.profitandloss
            FROM programme_investors pi
            INNER JOIN harvhub h
                    ON h.id = pi.investorid
                   AND (pi.sub_account_id IS NULL OR pi.sub_account_id = 0 OR h.sub_account_id = pi.sub_account_id)
            WHERE pi.developerid = ?
              AND pi.programme_id = ?
              AND pi.investorid > 0
        ");
        $s->execute([$userId, (int)$programme['id']]);
        while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
            $pnl = (float)($r['profitandloss'] ?? 0);
            if ($pnl > 0) $revenueAmount += ($pnl * $developerPercentage / 100);
        }
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
    $incomingRequestCount = countProgrammeInvestmentRequests($pdo, $userId, (int)$programme['id']);
}

if ($programme) {
    syncProgrammeTradesToInvestors($pdo, $userId, (int)$programme['id']);
}

$trades = [];
if ($programme) {
    $trades = fetchActiveProgrammeTrades($pdo, $userId, (int)$programme['id'], 400);
}

$serverContractDuration = 0; $serverMinBrokerBalance = 0.0;
try {
    $srv = $pdo->query("SELECT contract_duration, min_broker_balance FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($srv) { $serverContractDuration = (int)($srv['contract_duration'] ?? 0); $serverMinBrokerBalance = (float)($srv['min_broker_balance'] ?? 0); }
} catch (Throwable $e) {}
if ($contractDuration < $serverContractDuration) $contractDuration = $serverContractDuration;
if ($minInvestment < $serverMinBrokerBalance) $minInvestment = $serverMinBrokerBalance;

// ============================================================================
// >>> Settings-modal picker source.
// Prefer programme.broker_symbols / broker_timeframes (written by
// brokerdetails.py) over the legacy `broker_symbols` table. Fall back to
// the table only if the programme columns are empty.
// ============================================================================
$allBrokerSymbols = []; $availableTimeframes = [];
if ($programme) {
    $allBrokerSymbols    = parseCleanList((string)($programme['broker_symbols']    ?? ''));
    $availableTimeframes = parseCleanList((string)($programme['broker_timeframes'] ?? ''));
    sort($allBrokerSymbols);
    sort($availableTimeframes);
}

if (empty($allBrokerSymbols) || empty($availableTimeframes)) {
    try {
        $bs = $pdo->prepare("SELECT symbol, timeframes FROM broker_symbols WHERE userid = ? ORDER BY symbol ASC");
        $bs->execute([$userId]);
        $tableSymbols = [];
        $tableTfs = [];
        while ($r = $bs->fetch(PDO::FETCH_ASSOC)) {
            if (($r['symbol'] ?? '') === '__GLOBAL_TF__') continue;
            $tableSymbols[] = $r['symbol'];
            $rawTfs = (string)($r['timeframes'] ?? '');
            $rawTfs = str_replace(['[', ']', '{', '}', '"', "'", ' '], '', $rawTfs);
            foreach (array_filter(explode(',', $rawTfs)) as $tf) {
                $tf = trim($tf);
                if ($tf !== '') $tableTfs[$tf] = true;
            }
        }
        if (empty($allBrokerSymbols))    $allBrokerSymbols    = $tableSymbols;
        if (empty($availableTimeframes)) {
            $availableTimeframes = array_keys($tableTfs);
            sort($availableTimeframes);
        }
    } catch (Throwable $e) {}
}

function getProgrammeMarketSettings(array $programme): array {
    $symbols    = parseCleanList((string)($programme['selected_symbols']    ?? ''));
    $timeframes = parseCleanList((string)($programme['selected_timeframes'] ?? ''));
    return ['symbols' => $symbols, 'timeframes' => $timeframes];
}

$marketSettings = $programme ? getProgrammeMarketSettings($programme) : ['symbols'=>[], 'timeframes'=>[]];

$interest = $programme ? getSignalProviderInterest($pdo, $userId, (int)$programme['id']) : null;

if ($programme) {
    syncInterestRiskRewardIntoTrades($pdo, $userId, (int)$programme['id'], $interest);
}

$serverConfig = getServerAccountConfig($pdo);
$signalsState = $programme ? determineSignalsDashboardState($pdo, $userId, $programme, $interest, $serverConfig, $hasVps, $hasBroker) : [];

$greetingSub = $signalsState['greeting_sub'] ?? '';
$greetingSubHtml = $signalsState['greeting_sub_html'] ?? ($greetingSub);
$greetingBanners = $signalsState['greeting_banners_html'] ?? '';
$showSessionModal = $signalsState['show_session_modal'] ?? false;
$sessionModalTitle = $signalsState['session_modal_title'] ?? '';
$sessionModalMessage = $signalsState['session_modal_message'] ?? '';
$sessionModalClass = $signalsState['session_modal_class'] ?? 'info';
$sessionModalKey   = $signalsState['session_modal_key'] ?? '';
$vpsRequestCount = $signalsState['vps_request_count'] ?? 0;

$TIMEZONE_OPTIONS = getTimezoneOptions();

$initialStatusHtml = $programme
    ? renderProgrammeStatusHtml($programme, $hasBroker, $marketSettings['symbols'], $marketSettings['timeframes'], $timezoneValue)
    : '';

if ($programme && !empty($email)) {
    try {
        $signalsEmail = strtolower((string)($user['email'] ?? $email));
        syncSignalsDashboardNotifications(
            $pdo,
            $user,
            $programme,
            $interest,
            $hasVps,
            $hasBroker,
            0,
            0
        );
    } catch (Throwable $e) {
        error_log('[signals_dashboard] sync signals notification failed: ' . $e->getMessage());
    }
}
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

    .sd-challenge-chevron { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; padding: 6px 0; background: transparent; border: none; color: var(--sd-text); font-family: inherit; font-size: 0.77rem; cursor: pointer; text-align: left; transition: color .18s, transform .15s; }
    .sd-challenge-chevron:hover { transform: translateX(2px); }
    .sd-challenge-chevron-text { min-width: 0; word-break: break-word; }
    .sd-challenge-chevron-right { flex-shrink: 0; color: var(--sd-muted); font-size: 0.70rem; transition: color .18s; }
    .sd-challenge-chevron:hover .sd-challenge-chevron-right { color: var(--sd-accent); }

    .sd-incoming-banner { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; padding: 12px 14px; background: var(--sd-card); border: 1px solid var(--sd-border); border-radius: 12px; color: var(--sd-text); font-family: inherit; font-size: 0.9rem; font-weight: 600; cursor: pointer; text-align: left; transition: transform .15s, border-color .2s; }
    .sd-incoming-banner:hover { border-color: var(--sd-accent); transform: translateX(2px); }
    .sd-incoming-left { display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1; }
    .sd-incoming-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: rgba(46,204,143,0.12); color: var(--sd-accent); font-size: .9rem; }
    .sd-incoming-title { font-size: .9rem; font-weight: 700; }
    .sd-incoming-sub { font-size: .72rem; font-weight: 500; color: var(--sd-muted); margin-left: 10px}
    .sd-incoming-right { display: flex; align-items: center; gap: 10px; }
    .sd-incoming-count { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; padding: 0 7px; border-radius: 999px; background: var(--sd-accent); color: #fff; font-size: .7rem; font-weight: 800; }
    .sd-incoming-chev { color: var(--sd-muted); font-size: .85rem; }

    .sd-explore-challenges .sd-incoming-icon { background: rgba(245,166,35,0.15); color: var(--sd-gold, #f5a623); }
    .sd-begin-test .sd-incoming-icon { background: rgba(52,152,219,0.15); color: #3498db; }
    .sd-vps-requests .sd-incoming-icon { background: rgba(155,89,182,0.15); color: #9b59b6; }

    .sd-investment-requests .sd-incoming-icon { background: rgba(245,166,35,0.18); color: var(--sd-gold, #f5a623); }
    .sd-investment-requests .sd-incoming-count { background: var(--sd-gold, #f5a623); }

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

    .sd-trade-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--sd-border); margin-bottom: 14px; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none; max-height: 48px; }
    .sd-trade-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .sd-trade-tab { background: transparent; border: none; color: var(--sd-muted); padding: 9px 14px; font-weight: 700; font-size: .82rem; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; margin-bottom: -1px; font-family: inherit; display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; }
    .sd-trade-tab.active { color: var(--sd-accent); border-bottom-color: var(--sd-accent); }
    .sd-trade-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 999px; background: var(--sd-border); color: var(--sd-muted); font-size: .65rem; font-weight: 800; }
    .sd-trade-tab.active .sd-trade-tab-count { background: var(--sd-accent); color: #fff; }
    .sd-trade-panel { display: none; } .sd-trade-panel.active { display: block; }

    .sd-trades-scroll { max-height: 520px; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; padding-right: 4px; scrollbar-width: thin; }
    .sd-trades-scroll::-webkit-scrollbar { width: 6px; }
    .sd-trades-scroll::-webkit-scrollbar-thumb { background: var(--sd-border); border-radius: 999px; }
    @media (max-width: 600px) { .sd-trades-scroll { max-height: 380px; } }

    .sd-trades-list { display: grid; gap: 10px; }
    .sd-trade-modal-card { border: 1px solid var(--sd-border); border-radius: 14px; padding: 14px; background: var(--sd-bg); }
    .sd-trade-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin-bottom: 12px; }
    @media (max-width: 600px) { .sd-trade-grid { grid-template-columns: 1fr 1fr; gap: 8px 12px; } }
    .sd-trade-grid > div { min-width: 0; }
    .sd-trade-modal-card .sd-t-label { font-size: .6rem; color: var(--sd-muted); text-transform: uppercase; font-weight: 700; letter-spacing: .5px; margin-bottom: 2px; }
    .sd-trade-modal-card .sd-t-label + b { font-size: .84rem; font-weight: 800; word-break: break-word; display: block; }
    .sd-trade-modify { padding-top: 10px; border-top: 1px dashed var(--sd-border); }

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

    .sd-inv-breakdown { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
    .sd-inv-count { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border-radius: 999px; font-size: .72rem; font-weight: 800; }
    .sd-inv-count-success { background: rgba(46,204,143,0.12); color: var(--sd-success, #2ecc71); }
    .sd-inv-count-failed  { background: rgba(223,78,78,0.12); color: var(--sd-danger, #df4e4e); }
    body.dark-mode .sd-inv-count-success { color: #4ade80; }
    body.dark-mode .sd-inv-count-failed  { color: #ff8080; }

    .sd-modify-select { width: 100%; padding: 8px 10px; border-radius: 9px; border: 1px solid var(--sd-border); background: var(--sd-card); color: var(--sd-text); font-family: inherit; font-size: .82rem; font-weight: 600; cursor: pointer; }
    .sd-modify-select:focus { outline: none; border-color: var(--sd-accent); }

    .sd-status-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
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

    .sd-session-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 9999999; }
    .sd-session-modal.open { display: flex; }
    .sd-session-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); text-align: center; }
    .sd-session-card h3 { margin-bottom: 8px; font-size: 1.05rem; font-weight: 800; }
    .sd-session-card p { color: var(--sd-muted); font-size: .88rem; margin-bottom: 18px; line-height: 1.5; }
    .sd-session-card.info h3 { color: var(--sd-info); }
    .sd-session-card.warning h3 { color: var(--sd-warning); }
    .sd-session-card.danger h3 { color: var(--sd-danger); }

    .sd-alert-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 9999999; }
    .sd-alert-modal.open { display: flex; }
    .sd-alert-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); text-align: center; }
    .sd-alert-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 14px; }
    .sd-alert-icon.info    { background: rgba(52,152,219,0.12); color: var(--sd-info); }
    .sd-alert-icon.success { background: rgba(46,204,143,0.12); color: var(--sd-success); }
    .sd-alert-icon.error   { background: #fdecec; color: var(--sd-danger); }
    body.dark-mode .sd-alert-icon.error { background: #3a1c1c; color: #ff9c9c; }
    .sd-alert-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; }
    .sd-alert-text { color: var(--sd-muted); font-size: .88rem; margin-bottom: 18px; line-height: 1.5; }

    .sd-confirm-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 999999; }
    .sd-confirm-modal.open { display: flex; }
    .sd-confirm-card { width: min(400px, 100%); background: var(--sd-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .sd-confirm-card h3 { margin-bottom: 8px; font-size: 1.05rem; font-weight: 800; }
    .sd-confirm-card p { color: var(--sd-muted); font-size: .88rem; margin-bottom: 18px; line-height: 1.5; }
    .sd-confirm-actions { display: grid; gap: 9px; }

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
                <p class="sd-greet-sub" id="sdGreetSub"><?= $greetingSubHtml ?></p>
            </div>
        </div>

        <div id="sdGreetingBanners">
            <?= $greetingBanners ?>
        </div>
    </div>

    <div class="sd-stats-grid">
        <div class="sd-stat-card">
            <div class="sd-card-label">Revenue</div>
            <div class="sd-card-value">
                <span class="sd-currency">$</span><span id="sdRevenueValue"><?= esc_h(number_format($revenueAmount, 2)) ?></span>
            </div>
            <div class="sd-card-sub">Your percentage reward across investors.</div>

            <div class="sd-investors-badge" onclick="sdGoToInvestors()" role="button" tabindex="0" aria-label="View investors">
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
                    <div class="sd-card-desc" id="sdTradesCardDesc">
                        Trades generated from this programme for <?= count($activeInvestorIdSet) ?> active investor<?= count($activeInvestorIdSet) === 1 ? '' : 's' ?>
                    </div>
                </div>
            </div>
            <div id="sdTradesContainer">
                <?= renderProgrammeTradesHtml($trades, $activeInvestorIdSet) ?>
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
                <?= $initialStatusHtml ?>
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

<!-- SETTINGS MODAL -->
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
                <div class="sd-check-list" id="sdTimeframesList">
                    <?= renderTimeframeChecklistHtml($availableTimeframes, $marketSettings['timeframes']) ?>
                </div>
            </div>
        </div>

        <div class="sd-fold-section">
            <button type="button" class="sd-fold-toggle" data-target="sdFoldSymbols"><span>Select Symbols</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="sd-fold-body" id="sdFoldSymbols">
                <div class="sd-check-list" id="sdSymbolsList">
                    <?= renderSymbolChecklistHtml($allBrokerSymbols, $marketSettings['symbols']) ?>
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

                    <div class="sd-field">
                        <label>Timezone</label>
                        <select id="sdReqTimezone">
                            <option value="">— No timezone selected —</option>
                            <?php foreach ($TIMEZONE_OPTIONS as $groupLabel => $groupTzs): ?>
                                <optgroup label="<?= esc_h($groupLabel) ?>">
                                    <?php foreach ($groupTzs as $tzKey => $tzLabel): ?>
                                        <option value="<?= esc_h($tzKey) ?>" <?= ($timezoneValue === $tzKey) ? 'selected' : '' ?>>
                                            <?= esc_h($tzLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <p class="sd-sub" style="margin-top:4px">Used for market analysis timing and challenge windows.</p>
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

<!-- REQUEST PICKER MODAL -->
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

<!-- BEGIN TEST MODAL -->
<div class="sd-modal" id="sdBeginTestModal">
    <div class="sd-modal-card" style="text-align:center;">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(52,152,219,0.12);color:#3498db;display:flex;align-items:center;justify-content:center;font-size:1.6rem;margin:0 auto 14px;">
            <i class="fa-solid fa-play"></i>
        </div>
        <h3>Begin Test</h3>
        <p class="sd-sub" style="margin-bottom:20px;">Once you agree to begin test, your trades analytics will be compared with the challenge interest and once met, you will be advertised.</p>
        <div style="display:grid;gap:9px;">
            <button class="sd-btn primary" id="sdConfirmBeginTest" style="width:100%;">Confirm Test</button>
            <button class="sd-btn ghost" onclick="sdCloseModal('sdBeginTestModal')" style="width:100%;">Cancel</button>
        </div>
    </div>
</div>

<!-- SESSION INFO MODAL -->
<div class="sd-session-modal" id="sdSessionModal">
    <div class="sd-session-card info" id="sdSessionCard">
        <h3 id="sdSessionTitle">Notice</h3>
        <p id="sdSessionMessage">Message</p>
        <button class="sd-btn primary" onclick="sdCloseSessionModal()" style="width:100%;">Okay</button>
    </div>
</div>

<!-- CUSTOM ALERT MODAL -->
<div class="sd-alert-modal" id="sdAlertModal">
    <div class="sd-alert-card">
        <div class="sd-alert-icon info" id="sdAlertIcon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="sd-alert-title" id="sdAlertTitle">Notice</div>
        <div class="sd-alert-text" id="sdAlertText">Message</div>
        <button class="sd-btn primary" id="sdAlertOkBtn" style="width:100%;">OK</button>
    </div>
</div>

<!-- CONFIRM MODAL (trade modify) -->
<div class="sd-confirm-modal" id="sdConfirmModal">
    <div class="sd-confirm-card">
        <h3 id="sdConfirmTitle">Confirm Action</h3>
        <p id="sdConfirmMessage">Are you sure?</p>
        <div class="sd-confirm-actions">
            <button class="sd-btn primary" id="sdConfirmYes" style="width:100%;">Yes, Confirm</button>
            <button class="sd-btn ghost" id="sdConfirmNo" style="width:100%;">Cancel</button>
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
        return !!document.querySelector('.sd-modal.open, .sd-session-modal.open, .sd-alert-modal.open, .sd-confirm-modal.open');
    }
    function lockBodyScroll() { document.body.classList.add('sd-scroll-locked'); }
    function unlockBodyScroll() { if (!anyOverlayOpen()) document.body.classList.remove('sd-scroll-locked'); }

    window.harvhubGoToTab = function (tab) {
        try { if (window.parent && window.parent !== window) { window.parent.postMessage({ type: 'switchTab', tab: tab }, '*'); return; } } catch (e) {}
        window.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);
    };

    window.sdGoToChallenges = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'signals_provision_request' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=signals_provision_request';
    };

    window.sdGoToInvestors = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'programme_investors' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=programme_investors';
    };

    window.sdGoToInvestorsRequests = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'programme_investors' }, '*');
                setTimeout(function () {
                    try {
                        window.parent.postMessage({ type: 'switchInvestorsTab', tab: 'requestors' }, '*');
                    } catch (e) {}
                }, 260);
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=programme_investors';
    };

    document.querySelectorAll('.sd-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            var target = t.dataset.tab;
            document.querySelectorAll('.sd-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            document.querySelectorAll('.sd-panel').forEach(function (x) { x.classList.toggle('active', x.id === 'sd-tab-' + target); });
        });
    });

    window.sdSwitchTradeTab = function (tab) {
        document.querySelectorAll('.sd-trade-tab').forEach(function (x) {
            x.classList.toggle('active', x.dataset.tradeTab === tab);
        });
        document.querySelectorAll('.sd-trade-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === 'sdTradePanel-' + tab);
        });
    };

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
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    document.querySelectorAll('.sd-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) sdCloseModal(m.id); });
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

    window.sdAlert = function (message, kind, title) {
        kind = kind || 'info';
        var iconEl  = document.getElementById('sdAlertIcon');
        var titleEl = document.getElementById('sdAlertTitle');
        var textEl  = document.getElementById('sdAlertText');

        iconEl.classList.remove('info', 'success', 'error');
        iconEl.classList.add(kind);

        var iconHtml = '<i class="fa-solid fa-circle-info"></i>';
        if (kind === 'success') iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        if (kind === 'error')   iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        iconEl.innerHTML = iconHtml;

        titleEl.textContent = title || (kind === 'error' ? 'Error' : (kind === 'success' ? 'Success' : 'Notice'));
        textEl.textContent  = message || '';

        var el = document.getElementById('sdAlertModal');
        el.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    document.getElementById('sdAlertOkBtn').addEventListener('click', function () {
        var el = document.getElementById('sdAlertModal');
        el.classList.remove('open');
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    });

    var SESSION_SEEN = {};

    window.sdShowSessionModal = function (title, message, cls, key) {
        if (!title || !message) return;
        if (key && SESSION_SEEN[key]) return;
        if (key) SESSION_SEEN[key] = true;

        var card = document.getElementById('sdSessionCard');
        card.classList.remove('info', 'warning', 'danger');
        card.classList.add(cls || 'info');
        document.getElementById('sdSessionTitle').textContent = title;
        document.getElementById('sdSessionMessage').textContent = message;

        var el = document.getElementById('sdSessionModal');
        if (!el.classList.contains('open')) {
            el.classList.add('open');
            lockBodyScroll();
            notifyParentModal(true);
        }
    };

    window.sdCloseSessionModal = function () {
        var el = document.getElementById('sdSessionModal');
        if (el) el.classList.remove('open');
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    var INITIAL_SESSION = {
        show: <?= $showSessionModal ? 'true' : 'false' ?>,
        title: <?= json_encode($sessionModalTitle) ?>,
        message: <?= json_encode($sessionModalMessage) ?>,
        cls: <?= json_encode($sessionModalClass) ?>,
        key: <?= json_encode($sessionModalKey) ?>
    };
    if (INITIAL_SESSION.show && INITIAL_SESSION.title && INITIAL_SESSION.message) {
        setTimeout(function () {
            sdShowSessionModal(INITIAL_SESSION.title, INITIAL_SESSION.message, INITIAL_SESSION.cls, INITIAL_SESSION.key);
        }, 800);
    }

    window.sdOpenBeginTestModal = function () { sdOpenModal('sdBeginTestModal'); };

    var confirmBeginTestBtn = document.getElementById('sdConfirmBeginTest');
    if (confirmBeginTestBtn) {
        confirmBeginTestBtn.addEventListener('click', function () {
            confirmBeginTestBtn.disabled = true;
            confirmBeginTestBtn.textContent = 'Starting…';
            post({ action: 'begin_test' }, function (d) {
                confirmBeginTestBtn.disabled = false;
                confirmBeginTestBtn.textContent = 'Confirm Test';
                if (d && d.success) {
                    sdCloseModal('sdBeginTestModal');
                    window.location.reload();
                } else {
                    sdAlert((d && d.message) || 'Failed to begin test.', 'error', 'Could not begin test');
                }
            });
        });
    }

    var _sdConfirmOnYes = null;
    var _sdConfirmOnCancel = null;

    window.sdShowConfirm = function (title, message, yesLabel, onYes, onCancel) {
        var modal = document.getElementById('sdConfirmModal');
        if (!modal) { if (typeof onYes === 'function') onYes(); return; }

        document.getElementById('sdConfirmTitle').textContent = title || 'Confirm Action';
        document.getElementById('sdConfirmMessage').textContent = message || '';
        document.getElementById('sdConfirmYes').textContent = yesLabel || 'Yes, Confirm';

        _sdConfirmOnYes = onYes;
        _sdConfirmOnCancel = onCancel;

        modal.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    function sdCloseConfirmModal(runCancel) {
        var modal = document.getElementById('sdConfirmModal');
        if (!modal) return;
        modal.classList.remove('open');
        if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
        var cancelFn = _sdConfirmOnCancel;
        _sdConfirmOnYes = null;
        _sdConfirmOnCancel = null;
        if (runCancel && typeof cancelFn === 'function') cancelFn();
    }

    var sdConfirmYesBtn = document.getElementById('sdConfirmYes');
    if (sdConfirmYesBtn) {
        sdConfirmYesBtn.addEventListener('click', function () {
            var fn = _sdConfirmOnYes;
            _sdConfirmOnYes = null;
            _sdConfirmOnCancel = null;
            var modal = document.getElementById('sdConfirmModal');
            if (modal) modal.classList.remove('open');
            if (!anyOverlayOpen()) { unlockBodyScroll(); notifyParentModal(false); }
            if (typeof fn === 'function') fn();
        });
    }

    var sdConfirmNoBtn = document.getElementById('sdConfirmNo');
    if (sdConfirmNoBtn) {
        sdConfirmNoBtn.addEventListener('click', function () { sdCloseConfirmModal(true); });
    }

    var sdConfirmModalEl = document.getElementById('sdConfirmModal');
    if (sdConfirmModalEl) {
        sdConfirmModalEl.addEventListener('click', function (e) {
            if (e.target === sdConfirmModalEl) sdCloseConfirmModal(true);
        });
    }

    // ---------- LIVE UPDATE ENGINE ----------
    var liveStateInterval = null;
    var liveStateRunning = false;
    var liveFailCount = 0;
    var LIVE_BASE_INTERVAL = 1000;
    var LIVE_MAX_INTERVAL = 15000;
    var LIVE_STATE_LAST = null;

    // remember which symbols/timeframes the user has currently ticked
    // so a live refresh doesn't wipe out an in-progress selection.
    function captureCurrentSelections() {
        var syms = {};
        document.querySelectorAll('.sd-settings-symbol-cb:checked').forEach(function (cb) { syms[cb.value] = true; });
        var tfs = {};
        document.querySelectorAll('.sd-settings-tf-cb:checked').forEach(function (cb) { tfs[cb.value] = true; });
        return { syms: syms, tfs: tfs };
    }

    function restoreSelections(sel) {
        document.querySelectorAll('.sd-settings-symbol-cb').forEach(function (cb) {
            cb.checked = !!sel.syms[cb.value];
        });
        document.querySelectorAll('.sd-settings-tf-cb').forEach(function (cb) {
            cb.checked = !!sel.tfs[cb.value];
        });
    }

    // rebuild the checkbox lists from the live state, but preserve
    // whatever the user has ticked or unticked since the last refresh.
    function renderSettingsPickers(brokerSymbols, brokerTimeframes, selectedSymbols, selectedTimeframes) {
        var symsList = document.getElementById('sdSymbolsList');
        var tfsList  = document.getElementById('sdTimeframesList');
        if (!symsList || !tfsList) return;

        var current = captureCurrentSelections();

        // Symbols
        if (!brokerSymbols || !brokerSymbols.length) {
            symsList.innerHTML = '<p class="sd-sub">No symbols available.</p>';
        } else {
            var selectedSymLookup = {};
            (selectedSymbols || []).forEach(function (s) { selectedSymLookup[s] = true; });

            var html = '';
            brokerSymbols.forEach(function (sym) {
                var checked = current.syms[sym] || selectedSymLookup[sym];
                html += '<label class="sd-check-row">';
                html +=   '<input type="checkbox" value="' + escapeHtml(sym) + '" class="sd-settings-symbol-cb"' + (checked ? ' checked' : '') + '>';
                html +=   '<span>' + escapeHtml(sym) + '</span>';
                html += '</label>';
            });
            symsList.innerHTML = html;
        }

        // Timeframes
        if (!brokerTimeframes || !brokerTimeframes.length) {
            tfsList.innerHTML = '<p class="sd-sub">No timeframes available.</p>';
        } else {
            var selectedTfLookup = {};
            (selectedTimeframes || []).forEach(function (t) { selectedTfLookup[t] = true; });

            var htmlTf = '';
            brokerTimeframes.forEach(function (tf) {
                var checked = current.tfs[tf] || selectedTfLookup[tf];
                htmlTf += '<label class="sd-check-row">';
                htmlTf +=   '<input type="checkbox" value="' + escapeHtml(tf) + '" class="sd-settings-tf-cb"' + (checked ? ' checked' : '') + '>';
                htmlTf +=   '<span>' + escapeHtml(tf) + '</span>';
                htmlTf += '</label>';
            });
            tfsList.innerHTML = htmlTf;
        }
    }

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
        if (el.textContent !== t) { el.textContent = t; if (doPulse) pulse(el); return true; }
        return false;
    }

    function updateHtml(el, newHtml, doPulse) {
        if (!el) return false;
        if (el.innerHTML !== newHtml) { el.innerHTML = newHtml; if (doPulse) pulse(el); return true; }
        return false;
    }

    function tradesSignature(trades) {
        if (!trades || !trades.length) return '';
        var parts = [];
        for (var i = 0; i < trades.length; i++) {
            var t = trades[i] || {};
            parts.push((t.id || '') + ':' + (t.modify_trade || '') + ':' + (t.orderstatus || ''));
        }
        return parts.join('|');
    }

    function brokerSymbolsSignature(arr) {
        if (!arr || !arr.length) return '';
        return arr.length + ':' + arr[0] + ':' + arr[arr.length - 1];
    }

    function applyLiveState(data) {
        if (!data || !data.success) return;

        var revEl = document.getElementById('sdRevenueValue');
        if (revEl) updateText(revEl, parseFloat(data.revenue_amount || 0).toFixed(2), true);

        var invLabel = document.getElementById('sdInvestorCountLabel');
        if (invLabel) {
            var c = parseInt(data.investor_count, 10) || 0;
            updateText(invLabel, c + ' investor' + (c === 1 ? '' : 's'), true);
        }

        var tradesDescEl = document.getElementById('sdTradesCardDesc');
        if (tradesDescEl && data.active_investor_count !== undefined) {
            var ac = parseInt(data.active_investor_count, 10) || 0;
            updateText(
                tradesDescEl,
                'Trades generated from this programme for ' + ac + ' active investor' + (ac === 1 ? '' : 's'),
                true
            );
        }

        var psEl = document.getElementById('sdProfitSplitValue');
        if (psEl) updateText(psEl, parseFloat(data.developer_percentage || 0).toFixed(2), true);

        var loginEl = document.getElementById('sdAccountLogin');
        if (loginEl) updateText(loginEl, data.has_broker ? (data.broker_login || '') : 'N/A', false);
        var serverEl = document.getElementById('sdAccountServer');
        if (serverEl) updateText(serverEl, data.has_broker ? (data.broker_server || '') : 'N/A', false);

        var greetSub = document.getElementById('sdGreetSub');
        if (greetSub && data.greeting_sub_html !== undefined) updateHtml(greetSub, data.greeting_sub_html, false);

        var bannersEl = document.getElementById('sdGreetingBanners');
        if (bannersEl && data.greeting_banners_html !== undefined) updateHtml(bannersEl, data.greeting_banners_html, false);

        var statusEl = document.getElementById('sdProgrammeStatusContainer');
        if (statusEl && data.programme_status_html !== undefined) updateHtml(statusEl, data.programme_status_html, false);

        // ============================================================
        // >>> LIVE BROKER SYMBOLS / TIMEFRAMES REFRESH
        // Rebuild the settings-modal checkbox lists from the freshest
        // programme.broker_symbols / broker_timeframes, preserving any
        // in-progress user ticks. Detects change via a cheap signature
        // so we don't thrash the DOM every second.
        // ============================================================
        if (data.broker_symbols !== undefined && data.broker_timeframes !== undefined) {
            var newSig = brokerSymbolsSignature(data.broker_symbols)
                       + '|'
                       + brokerSymbolsSignature(data.broker_timeframes);
            var oldSig = LIVE_STATE_LAST
                ? (brokerSymbolsSignature(LIVE_STATE_LAST.broker_symbols)
                    + '|' + brokerSymbolsSignature(LIVE_STATE_LAST.broker_timeframes))
                : null;

            if (oldSig !== newSig) {
                renderSettingsPickers(
                    data.broker_symbols || [],
                    data.broker_timeframes || [],
                    data.symbols || [],
                    data.timeframes || []
                );
            }
        }

        var tradesEl = document.getElementById('sdTradesContainer');
        if (tradesEl && data.trades_html !== undefined) {
            var prevSig = LIVE_STATE_LAST ? tradesSignature(LIVE_STATE_LAST.trades) : null;
            var newSigT = tradesSignature(data.trades);
            if (prevSig !== newSigT) {
                updateHtml(tradesEl, data.trades_html, true);
                bindModifySelects();
            }
        }

        if (data.signals_state) {
            var ss = data.signals_state;
            if (ss.show_session_modal && ss.session_modal_title && ss.session_modal_message) {
                sdShowSessionModal(
                    ss.session_modal_title,
                    ss.session_modal_message,
                    ss.session_modal_class || 'info',
                    ss.session_modal_key || ''
                );
            }
        }

        LIVE_STATE_LAST = data;
    }

    function fetchLiveState() {
        if (!liveStateRunning) return;
        post({ action: 'get_live_state' }, function (d) {
            if (d && d.success) { liveFailCount = 0; applyLiveState(d); }
            else { liveFailCount++; }
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
    window.addEventListener('focus', function () { if (liveStateRunning) fetchLiveState(); });
    window.addEventListener('beforeunload', function () { stopLiveState(); });

    var HAS_PROGRAMME = <?= $programme ? 'true' : 'false' ?>;
    if (HAS_PROGRAMME) startLiveState();

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
                            sdGoToInvestors();
                            setTimeout(function () {
                                try {
                                    if (window.parent && window.parent !== window) {
                                        window.parent.postMessage({ type: 'switchInvestorsTab', tab: 'requestors' }, '*');
                                    }
                                } catch (e) {}
                            }, 200);
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
                                sdAlert((res && res.message) || 'Failed to switch programme.', 'error', 'Switch failed');
                            }
                        });
                    }
                });
            });
        });
    };

    function bindModifySelects() {
        document.querySelectorAll('.sd-modify-select').forEach(function (sel) {
            if (sel.dataset.sdBound === '1') return;
            sel.dataset.sdBound = '1';
            sel.addEventListener('change', function () {
                var tradeId = parseInt(sel.getAttribute('data-trade-id'), 10) || 0;
                var mode    = sel.value;
                if (tradeId <= 0 || mode === '') return;

                var label = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : mode;
                sdShowConfirm(
                    'Confirm Action',
                    'Are you sure you want to "' + label + '" for trade #' + tradeId + '?',
                    label,
                    function () {
                        sel.disabled = true;
                        post({ action: 'modify_trade', trade_id: tradeId, modify_trade: mode }, function (d) {
                            sel.disabled = false;
                            if (d && d.success) {
                                sel.value = mode;
                            } else {
                                sdAlert((d && d.message) ? d.message : 'Failed to update trade.', 'error', 'Trade update failed');
                                sel.value = '';
                            }
                        });
                    },
                    function () {
                        sel.value = '';
                    }
                );
            });
        });
    }
    bindModifySelects();

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

            var tzSelect = document.getElementById('sdReqTimezone');
            var tzValue = tzSelect ? tzSelect.value : '';

            post({ action: 'save_visibility', visibility: document.getElementById('sdVisSelect').value }, function (d1) {
                if (!d1.success) { showErr(d1.message); return; }
                post({ action: 'save_symbols_timeframes', symbols: symbols.join(','), timeframes: tfs.join(',') }, function (d2) {
                    if (!d2.success) { showErr(d2.message); return; }
                    post({
                        action: 'save_requirements',
                        contract_duration: document.getElementById('sdReqContract').value,
                        developer_percentage: document.getElementById('sdRevInput').value,
                        minimum_investment_amount: document.getElementById('sdReqMinInv').value,
                        maximum_investment_amount: document.getElementById('sdReqMaxInv').value,
                        timezone: tzValue
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

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
})();
</script>

</body>
</html>