<?php
// signals_provision_request.php — Trader challenge request browser
session_start();
require_once 'usersdb.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Database connection failed.");
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

$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

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

$serverConfig = [];
try {
    $q = $pdo->query("SELECT * FROM server_account WHERE id = 1 LIMIT 1");
    $serverConfig = $q->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$dayDuration     = (int)($serverConfig['day_challenge_duration'] ?? 30);
$weeklyDuration  = (int)($serverConfig['weekly_challenge_duration'] ?? 32);
$monthlyDuration = (int)($serverConfig['monthly_challenge_duration'] ?? 90);

$existingInterest = null;
if ($programme) {
    try {
        $q = $pdo->prepare("
            SELECT *
            FROM signals_provider_interest
            WHERE user_id = ? AND programme_id = ?
              AND interest_status IN ('interested','pending','active')
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->execute([$userId, (int)$programme['id']]);
        $existingInterest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}
}

function spiHasPartialUniqueKey(PDO $pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'signals_provider_interest'
              AND COLUMN_NAME = 'live_uniq_user_id'
        ");
        $q->execute();
        $cached = ((int)$q->fetchColumn() > 0);
    } catch (Throwable $e) {
        $cached = false;
    }
    return $cached;
}

// ==================== HELPERS ====================
function isProfitableOrBreakeven(int $expectedWin, int $consecutiveLoss, float $rr): bool {
    if ($rr <= 0) return false;
    return ($expectedWin * $rr) >= $consecutiveLoss;
}

function generateRealisticSystemChallenges($period) {
    $rows = [];
    $now  = date('Y-m-d H:i:s');

    $mk = function ($rr, $tc, $win, $loss, $type) use ($period, $now) {
        return [
            'id' => 0, 'user_id' => 0, 'account_management_id' => 0, 'programme_id' => 0,
            'risk_reward_type' => $type,
            'risk_reward' => $rr,
            'trades_provision' => $period,
            'consecutive_loss' => $loss,
            'trades_count' => $tc,
            'expected_win' => $win,
            'source' => 'system', 'is_active' => 1, 'created_at' => $now,
            'username' => 'System', 'first_name' => 'System', 'last_name' => '',
            'fullname' => 'System Challenge', 'user_email' => '',
        ];
    };

    $types = ['fixed_risk_reward', 'custom_and_minimum_risk_reward', 'custom_and_fixed_risk_reward'];
    $seen  = [];

    for ($rr = 1; $rr <= 20; $rr++) {
        $tcs = array_unique([
            max(1, min(100, $rr)),
            max(1, min(100, (int)round($rr * 1.5))),
            max(1, min(100, (int)round($rr / 2))),
            10, 20, 30, 50, 100,
        ]);
        sort($tcs);

        foreach ($tcs as $tc) {
            if ($tc < 1) continue;

            $shapes = [
                ['win' => $tc, 'loss' => max(1, $tc - 1)],
                ['win' => max(1, (int)ceil($tc / 2)), 'loss' => max(1, (int)ceil($tc / 2))],
                ['win' => 1, 'loss' => 1],
                ['win' => 1, 'loss' => max(1, $tc - 1)],
            ];

            foreach ($shapes as $s) {
                if (!isProfitableOrBreakeven($s['win'], $s['loss'], $rr)) continue;

                foreach ($types as $type) {
                    $key = "$rr|$tc|{$s['win']}|{$s['loss']}|$type";
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $rows[] = $mk($rr, $tc, $s['win'], $s['loss'], $type);
                }
            }
        }
    }

    for ($rr = 21; $rr <= 100; $rr++) {
        $tc = max(1, min(100, $rr));
        $shapes = [
            ['win' => $tc, 'loss' => max(1, $tc - 1)],
            ['win' => 1,   'loss' => 1],
        ];
        foreach ($shapes as $s) {
            if (!isProfitableOrBreakeven($s['win'], $s['loss'], $rr)) continue;
            foreach ($types as $type) {
                $key = "$rr|$tc|{$s['win']}|{$s['loss']}|$type";
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $rows[] = $mk($rr, $tc, $s['win'], $s['loss'], $type);
            }
        }
    }

    usort($rows, function ($a, $b) {
        if ($a['risk_reward'] != $b['risk_reward']) return $a['risk_reward'] - $b['risk_reward'];
        if ($a['trades_count'] != $b['trades_count']) return $a['trades_count'] - $b['trades_count'];
        return $b['expected_win'] - $a['expected_win'];
    });

    return $rows;
}

function synthesizeFilteredCard(string $type, int $rr, int $trades, int $win, int $loss, string $period) {
    if ($trades < 1) return null;
    if ($win < 1 || $win > $trades) return null;
    if ($loss < 1 || $loss >= $trades) return null;
    if ($rr < 1) return null;
    if (!isProfitableOrBreakeven($win, $loss, $rr)) return null;

    return [
        'id' => 0, 'user_id' => 0, 'account_management_id' => 0, 'programme_id' => 0,
        'risk_reward_type' => $type,
        'risk_reward' => $rr,
        'trades_provision' => $period,
        'consecutive_loss' => $loss,
        'trades_count' => $trades,
        'expected_win' => $win,
        'source' => 'system', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        'username' => 'System', 'first_name' => 'System', 'last_name' => '',
        'fullname' => 'System Challenge', 'user_email' => '',
    ];
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'get_requests') {
        $period = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        try {
            $systemRows = generateRealisticSystemChallenges($period);
            jres(['success' => true, 'requests' => $systemRows]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load requests: ' . $e->getMessage()]);
        }
    }

    if ($action === 'filter_requests') {
        $period = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        $type   = trim($_POST['risk_reward_type'] ?? '');
        $rr     = isset($_POST['risk_reward'])     && $_POST['risk_reward']     !== '' ? (int)$_POST['risk_reward']     : null;
        $trades = isset($_POST['trades_count'])    && $_POST['trades_count']    !== '' ? (int)$_POST['trades_count']    : null;
        $win    = isset($_POST['expected_win'])    && $_POST['expected_win']    !== '' ? (int)$_POST['expected_win']    : null;
        $loss   = isset($_POST['consecutive_loss'])&& $_POST['consecutive_loss']!== '' ? (int)$_POST['consecutive_loss']: null;

        $allowedTypes = ['fixed_risk_reward', 'custom_and_minimum_risk_reward', 'custom_and_fixed_risk_reward'];
        if (!in_array($type, $allowedTypes, true)) {
            jres(['success' => false, 'filter_error' => true, 'message' => 'Please select a risk reward type.']);
        }

        if ($rr === null || $trades === null || $win === null || $loss === null) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Fill in all fields: risk reward, trades count, expected win, and consecutive loss.'
            ]);
        }

        if ($win > $trades) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Expected win (' . $win . ') cannot be greater than trades count (' . $trades . ').'
            ]);
        }
        if ($loss >= $trades) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Consecutive loss (' . $loss . ') must be less than trades count (' . $trades . ') — otherwise you would be blown.'
            ]);
        }
        if (!isProfitableOrBreakeven($win, $loss, $rr)) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'This combination is not profitable or breakeven. '
                           . 'For a 1:' . $rr . ' risk reward with ' . $win . ' winning trade(s) and '
                           . $loss . ' consecutive loss(es), the edge would be negative. '
                           . 'Profitable/breakeven rule: expected_win × rr ≥ consecutive_loss ('
                           . $win . ' × ' . $rr . ' = ' . ($win * $rr) . ' must be ≥ ' . $loss . ').'
            ]);
        }

        $card = synthesizeFilteredCard($type, $rr, $trades, $win, $loss, $period);
        if ($card === null) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Could not generate this configuration.'
            ]);
        }

        jres(['success' => true, 'requests' => [$card]]);
    }

    if ($action === 'save_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        $requestId = (int)($_POST['request_id'] ?? 0);
        $period    = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        $request = [
            'risk_reward_type' => $_POST['risk_reward_type'] ?? 'fixed_risk_reward',
            'risk_reward'      => (float)($_POST['risk_reward'] ?? 2),
            'trades_provision' => $period,
            'consecutive_loss' => (int)($_POST['consecutive_loss'] ?? 2),
            'trades_count'     => (int)($_POST['trades_count'] ?? 1),
            'expected_win'     => (int)($_POST['expected_win'] ?? 1),
        ];

        $programmeId = (int)$programme['id'];
        $interestId  = 0;
        $actionTaken = 'interested';
        $wasInserted = false;

        try {
            $pdo->beginTransaction();

            $eq = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                  AND interest_status IN ('interested','pending','active')
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");
            $eq->execute([$userId, $programmeId]);
            $existing = $eq->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existing) {
                $sameShape =
                    ((float)$existing['risk_reward']             === (float)$request['risk_reward']) &&
                    ((string)$existing['risk_reward_type']       === (string)$request['risk_reward_type']) &&
                    ((int)$existing['consecutive_expected_loss'] === (int)$request['consecutive_loss']) &&
                    ((int)$existing['trades_count']              === (int)$request['trades_count']) &&
                    ((int)$existing['expected_win_count']        === (int)$request['expected_win']) &&
                    ((string)$existing['trades_provision']       === (string)$request['trades_provision']);

                if ($sameShape) {
                    $interestId  = (int)$existing['id'];
                    $actionTaken = 'interested';
                    $wasInserted = false;
                } else {
                    $pdo->rollBack();
                    jres([
                        'success' => false,
                        'message' => 'You already have an active challenge interest for this programme. Cancel it first to choose a different challenge.'
                    ]);
                }
            } else {
                if (!spiHasPartialUniqueKey($pdo)) {
                    try {
                        $del = $pdo->prepare("
                            DELETE FROM signals_provider_interest
                            WHERE user_id = ?
                              AND programme_id = ?
                              AND request_id = ?
                              AND accountmanagement_id = 0
                              AND interest_status NOT IN ('interested','pending','active')
                        ");
                        $del->execute([$userId, $programmeId, $requestId]);
                    } catch (Throwable $delErr) {
                        error_log('[signals_provision_request] stale-row cleanup failed: ' . $delErr->getMessage());
                    }
                }

                $ins = $pdo->prepare("
                    INSERT INTO signals_provider_interest
                        (user_id, programme_id, accountmanagement_id, request_id,
                         risk_reward, risk_reward_type,
                         consecutive_expected_loss, trades_count, expected_win_count,
                         trades_provision, interest_status, begin_test, signal_testing_started_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'interested', 0, NULL)
                ");
                $ins->execute([
                    $userId,
                    $programmeId,
                    0,
                    $requestId,
                    $request['risk_reward'],
                    $request['risk_reward_type'],
                    $request['consecutive_loss'],
                    $request['trades_count'],
                    $request['expected_win'],
                    $request['trades_provision'],
                ]);

                $interestId  = (int)$pdo->lastInsertId();
                $actionTaken = 'interested';
                $wasInserted = true;
            }

            try {
                $logIns = $pdo->prepare("
                    INSERT INTO signals_request_file (user_id, programme_id, request_id, action, snapshot_data)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $logIns->execute([
                    $userId,
                    $programmeId,
                    $requestId,
                    $actionTaken,
                    json_encode($request)
                ]);
            } catch (Throwable $logErr) {
                error_log('[signals_provision_request] audit log failed: ' . $logErr->getMessage());
            }

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $ignored) {}
            }

            $msg = $e->getMessage();
            if (strpos($msg, 'uq_spi') !== false || strpos($msg, 'Duplicate entry') !== false) {
                jres([
                    'success' => false,
                    'message' => 'A challenge interest for this programme already exists. Cancel it first if you want to choose a different challenge.'
                ]);
            }

            jres(['success' => false, 'message' => 'Failed to save interest: ' . $msg]);
        }

        /* ------------------------------------------------
         * 4) Notification — outside the transaction.
         *    Uses a timestamped key so every join attempt
         *    produces a fresh in-app row + email, even on
         *    idempotent re-clicks of the same challenge.
         * ------------------------------------------------ */

        try {
            $programmeName = trim((string)($programme['program_name'] ?? ''));
            if ($programmeName === '') $programmeName = 'Programme #' . $programmeId;

            $periodLabel = ucfirst($period);

            $joinMessage = 'Your programme ' . $programmeName . ' has joined a ' . $periodLabel . ' signals challenge.';

            recordProgrammeNotification($pdo, $programmeId, $email, [
                'notification_key' => 'prog-signals-interest-' . $programmeId . '-' . $interestId . '-' . date('YmdHis'),
                'title'            => 'Challenge Joined',
                'message'          => $joinMessage,
                'type'             => 'success',
                'section'          => 'Signals',
                'action_tab'       => 'signals_provision_request',
                'force'            => true
            ]);
        } catch (Throwable $notifErr) {
            error_log('[signals_provision_request] join notification failed: ' . $notifErr->getMessage());
        }

        jres([
            'success'     => true,
            'message'     => 'Challenge interest saved.',
            'interest_id' => $interestId
        ]);
    }

    if ($action === 'check_my_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme.']);
        try {
            $q = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                  AND interest_status IN ('interested','pending','active')
                ORDER BY id DESC
                LIMIT 1
            ");
            $q->execute([$userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            jres(['success' => true, 'interest' => $interest]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to check interest.']);
        }
    }

    if ($action === 'get_my_interests') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        try {
            $q = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                ORDER BY id DESC
            ");
            $q->execute([$userId, (int)$programme['id']]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);

            $out = [];
            foreach ($rows as $r) {
                $status = strtolower((string)($r['interest_status'] ?? 'interested'));

                if (!in_array($status, ['interested', 'pending', 'active'], true)) {
                    continue;
                }

                $period = strtolower((string)($r['trades_provision'] ?? 'daily'));
                if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

                $out[] = [
                    'id'                    => (int)$r['id'],
                    'risk_reward_type'      => (string)($r['risk_reward_type'] ?? 'fixed_risk_reward'),
                    'risk_reward'           => (float)($r['risk_reward'] ?? 0),
                    'trades_provision'      => $period,
                    'consecutive_loss'      => (int)($r['consecutive_expected_loss'] ?? 0),
                    'trades_count'          => (int)($r['trades_count'] ?? 0),
                    'expected_win'          => (int)($r['expected_win_count'] ?? 0),
                    'interest_status'       => $status,
                    'begin_test'            => (int)($r['begin_test'] ?? 0),
                    'created_at'            => (string)($r['created_at'] ?? ''),
                ];
            }

            jres(['success' => true, 'interests' => $out]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load interests: ' . $e->getMessage()]);
        }
    }

    if ($action === 'cancel_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        $interestId = (int)($_POST['interest_id'] ?? 0);
        if ($interestId <= 0) jres(['success' => false, 'message' => 'Invalid challenge.']);

        try {
            $q = $pdo->prepare("SELECT * FROM signals_provider_interest WHERE id = ? AND user_id = ? AND programme_id = ? LIMIT 1");
            $q->execute([$interestId, $userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC);

            if (!$interest) {
                jres(['success' => false, 'message' => 'Challenge interest not found.']);
            }

            $status = strtolower((string)($interest['interest_status'] ?? ''));
            if (in_array($status, ['cancelled', 'failed', 'breached', 'passed'], true)) {
                jres(['success' => false, 'message' => 'This challenge interest can no longer be cancelled.']);
            }

            $upd = $pdo->prepare("
                UPDATE signals_provider_interest
                SET interest_status = 'cancelled',
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ? AND programme_id = ?
            ");
            $upd->execute([$interestId, $userId, (int)$programme['id']]);

            try {
                $logIns = $pdo->prepare("
                    INSERT INTO signals_request_file (user_id, programme_id, request_id, action, snapshot_data)
                    VALUES (?, ?, ?, 'cancelled', ?)
                ");
                $logIns->execute([
                    $userId,
                    (int)$programme['id'],
                    (int)($interest['request_id'] ?? 0),
                    json_encode([
                        'interest_id' => $interestId,
                        'previous_status' => $status
                    ])
                ]);
            } catch (Throwable $e) {}

            $programmeName = trim((string)($programme['program_name'] ?? ''));
            if ($programmeName === '') $programmeName = 'Programme #' . (int)$programme['id'];

            $period = strtolower((string)($interest['trades_provision'] ?? 'daily'));
            if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';
            $periodLabel = ucfirst($period);

            $cancelMessage = 'Your programme ' . $programmeName . ' has cancelled its ' . $periodLabel . ' signals challenge.';

            try {
                recordProgrammeNotification($pdo, (int)$programme['id'], $email, [
                    'notification_key' => 'prog-signals-cancelled-' . (int)$programme['id'] . '-' . $interestId . '-' . date('YmdHis'),
                    'title'            => 'Challenge Cancelled',
                    'message'          => $cancelMessage,
                    'type'             => 'warning',
                    'section'          => 'Signals',
                    'action_tab'       => 'signals_provision_request',
                    'force'            => true
                ]);
            } catch (Throwable $notifErr) {
                error_log('[signals_provision_request] cancel notification failed: ' . $notifErr->getMessage());
            }

            jres(['success' => true, 'message' => 'Challenge interest cancelled successfully.']);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to cancel interest: ' . $e->getMessage()]);
        }
    }

    jres(['success' => false, 'message' => 'Unknown action.']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Signals Provision Request</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    :root{
        --spr-bg: var(--bg, #f4f8f7); --spr-card: var(--bg-card, #ffffff); --spr-text: var(--text, #12211d);
        --spr-muted: var(--text-muted, #60736d); --spr-accent: var(--accent, #12a36b); --spr-accent-2: var(--accent-hover, #0b7d52);
        --spr-soft: var(--accent-light, #e3f6ee); --spr-border: var(--border-color, #dfe9e5);
        --spr-danger: var(--danger, #df4e4e); --spr-warning: var(--warning, #b87513);
        --spr-success: var(--success, #2ecc71); --spr-info: var(--info, #3498db);
        --spr-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06)); --spr-radius: var(--radius, 16px);
        --spr-page-pad: 20px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        background: var(--spr-bg); color: var(--spr-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55; -webkit-tap-highlight-color: transparent;
        min-height: 100vh;
    }
    body::-webkit-scrollbar { display: none; }
    body.dark-mode { --spr-bg:#0c1311; --spr-card:#131d1a; --spr-text:#e8f2ee; --spr-muted:#93a8a1; --spr-soft:#12271f; --spr-border:#22322d; --spr-shadow: 0 10px 30px rgba(0,0,0,.4); }

    body.spr-scroll-locked { overflow: hidden; }

    .spr-page {
        max-width: 1100px;
        margin: 0 auto;
        width: 100%;
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
    }

    .spr-fixed-header {
        flex: 0 0 auto;
        background: var(--spr-bg);
        z-index: 20;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    body.dark-mode .spr-fixed-header { box-shadow: 0 1px 3px rgba(0,0,0,0.4); }

    .spr-header {
        padding: calc(env(safe-area-inset-top, 0px) + 14px) var(--spr-page-pad) 0;
        display: flex; align-items: center; justify-content: center; position: relative;
        max-width: 1100px; margin: 0 auto; width: 100%;
    }
    .spr-header-back {
        position: absolute; left: var(--spr-page-pad); top: 50%; transform: translateY(-50%);
        background: transparent; border: none; color: var(--spr-text); font-size: 1.2rem;
        cursor: pointer; padding: 8px; width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .spr-header-title { font-size: 1.15rem; font-weight: 800; }

    .spr-main-tabs {
        display: flex; gap: 4px; border-bottom: 1px solid var(--spr-border);
        margin: 0 auto; padding: 0 var(--spr-page-pad);
        overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;
        max-width: 1100px; width: 100%;
    }
    .spr-main-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .spr-main-tab {
        flex: 1 1 0; min-width: max-content; background: transparent; border: none;
        color: var(--spr-muted); padding: 12px 8px; font-weight: 700; font-size: .88rem;
        cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        margin-bottom: -1px; font-family: inherit; text-align: center;
    }
    .spr-main-tab.active { color: var(--spr-accent); border-bottom-color: var(--spr-accent); }

    .spr-sub-tabs {
        display: flex; gap: 4px;
        margin: 0 auto; padding: 0 var(--spr-page-pad);
        overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;
        max-width: 1100px; width: 100%;
    }
    .spr-sub-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .spr-sub-tab {
        flex: 1 1 0; min-width: max-content; background: transparent; border: none;
        color: var(--spr-muted); padding: 10px 8px; font-weight: 700; font-size: .82rem;
        cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        margin-bottom: -1px; font-family: inherit; text-align: center;
        transition: color .18s, border-color .18s;
    }
    .spr-sub-tab.active { color: var(--spr-accent); border-bottom-color: var(--spr-accent); }

    .spr-sub-tabs[hidden] { display: none; }

    .spr-filter-float {
        position: fixed;
        top: calc(env(safe-area-inset-top, 0px) + 139px);
        left: 50%; transform: translateX(-50%);
        z-index: 9999; background: transparent; border: none;
        padding: 0 var(--spr-page-pad); width: 100%; max-width: 1100px;
        display: flex; justify-content: space-between; align-items: center;
        pointer-events: none;
    }
    .spr-filter-pill,
    .spr-cancel-pill {
        pointer-events: auto; display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px; border-radius: 99px; background: var(--spr-card);
        border: 1px solid var(--spr-border); color: var(--spr-text);
        font-weight: 800; font-size: .82rem; cursor: pointer; font-family: inherit;
        box-shadow: var(--spr-shadow); transition: color .18s, transform .15s, background .18s;
    }
    .spr-filter-pill i, .spr-cancel-pill i { font-size: 1rem; }
    .spr-filter-pill:hover { color: var(--spr-accent); background: var(--spr-soft); }
    .spr-filter-pill:active { transform: scale(.97); }
    .spr-cancel-pill { color: var(--spr-danger); }
    .spr-cancel-pill:hover { color: #fff; background: var(--spr-danger); border-color: var(--spr-danger); }
    .spr-cancel-pill:active { transform: scale(.97); }
    .spr-cancel-pill[hidden] { display: none; }
    body.dark-mode .spr-filter-pill { background: var(--spr-card); border-color: var(--spr-border); color: #e8f2ee; }
    body.dark-mode .spr-filter-pill:hover { color: var(--spr-accent); background: var(--spr-soft); }
    body.dark-mode .spr-cancel-pill { background: var(--spr-card); border-color: var(--spr-border); color: #ff9c9c; }
    body.dark-mode .spr-cancel-pill:hover { color: #fff; background: var(--spr-danger); border-color: var(--spr-danger); }

    .spr-scroll-area {
        flex: 1 1 auto; overflow-y: auto; overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 56px var(--spr-page-pad) 100px;
        max-width: 1100px; margin: 0 auto; width: 100%;
    }

    .spr-list { display: flex; flex-direction: column; gap: 12px; }

    .spr-item {
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--spr-radius); padding: 16px 20px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; box-shadow: var(--spr-shadow);
    }

    .spr-item-main { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 0; }
    .spr-item-header { display: flex; flex-direction: column; gap: 2px; }
    .spr-item-title { font-size: 1rem; font-weight: 800; color: var(--spr-text); word-break: break-word; line-height: 1.25; }
    .spr-item-sub { font-size: .78rem; color: var(--spr-muted); }

    .spr-meta-row {
        display: flex; flex-wrap: wrap; align-items: baseline;
        gap: 8px 22px; margin-top: 2px; width: 100%;
    }

    .spr-meta-item { display: flex; align-items: baseline; gap: 5px; font-size: .84rem; line-height: 1.4; white-space: normal; flex: 0 1 auto; max-width: 100%; word-break: break-word; }
    .spr-meta-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; color: var(--spr-muted); font-weight: 700; white-space: nowrap; }
    .spr-meta-value { font-size: .88rem; font-weight: 700; color: var(--spr-text); word-break: break-word; }

    .spr-item-actions { display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; align-self: center; }

    .spr-interested-btn {
        flex-shrink: 0; align-self: center; padding: 10px 22px;
        border-radius: 10px; border: none; background: var(--spr-accent);
        color: #fff; font-weight: 800; font-size: .85rem; cursor: pointer; font-family: inherit;
        transition: background .18s, transform .15s, box-shadow .18s; white-space: nowrap;
    }
    .spr-interested-btn:hover { background: var(--spr-accent-2); transform: scale(1.04); box-shadow: 0 6px 18px rgba(18,163,107,.28); }
    .spr-interested-btn:active { transform: scale(.98); }
    .spr-interested-btn:disabled { background: var(--spr-muted); cursor: not-allowed; opacity: .7; transform: none; box-shadow: none; }
    .spr-interested-btn.is-mine { background: var(--spr-info); cursor: default; }
    .spr-interested-btn.is-mine:hover { background: var(--spr-info); transform: none; box-shadow: none; }
    .spr-interested-btn.is-readonly {
        background: transparent;
        border: 1.5px dashed var(--spr-border);
        color: var(--spr-muted);
        cursor: default;
        opacity: .8;
    }
    .spr-interested-btn.is-readonly:hover {
        background: transparent;
        transform: none;
        box-shadow: none;
    }

    .spr-cancel-btn {
        flex-shrink: 0; align-self: center; padding: 10px 22px;
        border-radius: 10px; border: none; background: var(--spr-danger);
        color: #fff; font-weight: 800; font-size: .85rem; cursor: pointer; font-family: inherit;
        transition: background .18s, transform .15s, box-shadow .18s; white-space: nowrap;
    }
    .spr-cancel-btn:hover { background: #c43c3c; transform: scale(1.04); box-shadow: 0 6px 18px rgba(223,78,78,.28); }
    .spr-cancel-btn:active { transform: scale(.98); }
    .spr-cancel-btn:disabled { background: var(--spr-muted); cursor: not-allowed; opacity: .7; transform: none; box-shadow: none; }

    @media (max-width: 640px) {
        :root { --spr-page-pad: 14px; }
        .spr-item { flex-direction: column; align-items: stretch; gap: 12px; }
        .spr-interested-btn, .spr-cancel-btn { align-self: stretch; width: 100%; }
        .spr-item-actions { width: 100%; }
        .spr-meta-row { gap: 6px 14px; }
        .spr-scroll-area { padding: 52px var(--spr-page-pad) 90px; }
        .spr-filter-float { top: calc(env(safe-area-inset-top, 0px) + 132px); }
    }

    .spr-empty { padding: 36px 20px; text-align: center; color: var(--spr-muted); border: 1px dashed var(--spr-border); border-radius: 14px; background: var(--spr-bg); }
    .spr-empty i { font-size: 1.6rem; margin-bottom: 10px; color: var(--spr-accent); display: block; }
    .spr-empty strong { display: block; color: var(--spr-text); font-size: .98rem; margin-bottom: 4px; }
    .spr-empty p { font-size: .85rem; }

    .spr-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 99999; overscroll-behavior: contain; }
    .spr-modal.open { display: flex; }
    .spr-modal-card { width: min(460px, 100%); max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; background: var(--spr-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .spr-modal-card h3 { margin-bottom: 5px; font-size: 1.05rem; font-weight: 800; }
    .spr-modal-card .spr-sub { color: var(--spr-muted); font-size: .82rem; margin-bottom: 16px; }

    .spr-field { margin: 12px 0; }
    .spr-field label { display: block; font-size: .7rem; text-transform: uppercase; color: var(--spr-muted); font-weight: 800; margin-bottom: 6px; }
    .spr-field input, .spr-field select { width: 100%; padding: 12px; border: 1px solid var(--spr-border); border-radius: 11px; background: var(--spr-bg); color: var(--spr-text); font: inherit; }
    .spr-field input:focus, .spr-field select:focus { outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 3px var(--spr-soft); }

    .spr-filter-details { display: none; }
    .spr-filter-details.visible { display: block; }

    .spr-actions { display: grid; gap: 9px; margin-top: 16px; }
    .spr-btn { border: 0; border-radius: 11px; padding: 12px 16px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-size: .88rem; }
    .spr-btn.primary { background: var(--spr-accent); color: #fff; }
    .spr-btn.primary:hover { background: var(--spr-accent-2); }
    .spr-btn.ghost { background: transparent; color: var(--spr-text); border: 1px solid var(--spr-border); }
    .spr-btn.ghost:hover { border-color: var(--spr-accent); color: var(--spr-accent); }
    .spr-btn.danger { background: var(--spr-danger); color: #fff; }
    .spr-btn.danger:hover { background: #c43c3c; }

    .spr-err { display: none; background: #fdecec; color: var(--spr-danger); padding: 10px; border-radius: 10px; font-size: .82rem; margin-bottom: 10px; }
    body.dark-mode .spr-err { background: #3a1c1c; color: #ff9c9c; }

    .spr-confirm-card { text-align: center; }
    .spr-confirm-icon { width: 64px; height: 64px; border-radius: 50%; background: var(--spr-soft); color: var(--spr-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 14px; }
    .spr-confirm-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; }
    .spr-confirm-text { font-size: .88rem; color: var(--spr-muted); line-height: 1.55; margin-bottom: 18px; }

    .spr-alert-card { text-align: center; }
    .spr-alert-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 14px; }
    .spr-alert-icon.info    { background: var(--spr-soft); color: var(--spr-info); }
    .spr-alert-icon.success { background: var(--spr-soft); color: var(--spr-success); }
    .spr-alert-icon.warning { background: #fef5e7; color: var(--spr-warning); }
    .spr-alert-icon.error   { background: #fdecec; color: var(--spr-danger); }
    body.dark-mode .spr-alert-icon.warning { background: #3a2e1c; color: #f0b429; }
    body.dark-mode .spr-alert-icon.error { background: #3a1c1c; color: #ff9c9c; }
    .spr-alert-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; }
    .spr-alert-text { font-size: .88rem; color: var(--spr-muted); line-height: 1.55; margin-bottom: 18px; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="spr-page">
    <div class="spr-fixed-header">
        <div class="spr-header">
            <button class="spr-header-back" onclick="sprGoBack()" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></button>
            <div class="spr-header-title">Challenge Requests</div>
        </div>

        <div class="spr-main-tabs">
            <button class="spr-main-tab active" data-main-tab="challenges">Signals Challenge</button>
            <button class="spr-main-tab" data-main-tab="interested">Interested Challenge</button>
        </div>

        <div class="spr-sub-tabs" id="sprSubTabs">
            <button class="spr-sub-tab active" data-period="daily">Daily</button>
            <button class="spr-sub-tab" data-period="weekly">Weekly</button>
            <button class="spr-sub-tab" data-period="monthly">Monthly</button>
        </div>
    </div>

    <div class="spr-filter-float" id="sprFilterFloat">
        <button class="spr-filter-pill" onclick="sprOpenFilterModal()" aria-label="Filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>
        <button class="spr-cancel-pill" id="sprCancelFilterBtn" onclick="sprCancelFilter()" aria-label="Cancel filter" hidden>
            <i class="fa-solid fa-xmark"></i> Cancel Filter
        </button>
    </div>

    <div class="spr-scroll-area" id="sprScrollArea">
        <div id="sprRequestList" class="spr-list">
            <div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading challenges…</p></div>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div class="spr-modal" id="sprFilterModal">
    <div class="spr-modal-card">
        <h3>Filter Challenges</h3>
        <p class="spr-sub">Select a risk reward type to continue. Fill all fields to find your exact match.</p>

        <div class="spr-field">
            <label>Risk Reward Type</label>
            <select id="sprFilterType">
                <option value="">Select risk reward type to continue</option>
                <option value="fixed_risk_reward">Fixed Risk Reward</option>
                <option value="custom_and_minimum_risk_reward">Custom &amp; Minimum Risk Reward</option>
                <option value="custom_and_fixed_risk_reward">Custom &amp; Fixed Risk Reward</option>
            </select>
        </div>

        <div class="spr-filter-details" id="sprFilterDetails">
            <div class="spr-field">
                <label id="sprRrLabel">Risk Reward (1:?)</label>
                <input type="text" inputmode="numeric" maxlength="7" class="spr-field-input" id="sprFilterRr" placeholder="1:2" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            </div>

            <div class="spr-field">
                <label>Trades Count</label>
                <input type="number" id="sprFilterTrades" min="1" max="100" step="1" placeholder="e.g. 10" autocomplete="off">
            </div>

            <div class="spr-field">
                <label>Expected Win Count</label>
                <input type="number" id="sprFilterWin" min="1" max="100" step="1" placeholder="e.g. 8" autocomplete="off">
            </div>

            <div class="spr-field">
                <label>Consecutive Expected Loss</label>
                <input type="number" id="sprFilterLoss" min="1" max="100" step="1" placeholder="e.g. 3" autocomplete="off">
            </div>
        </div>

        <div id="sprFilterErr" class="spr-err"></div>

        <div class="spr-actions">
            <button class="spr-btn primary" onclick="sprApplyFilter()">Apply Filter</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprFilterModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- CONFIRM INTEREST MODAL -->
<div class="spr-modal" id="sprConfirmModal">
    <div class="spr-modal-card spr-confirm-card">
        <div class="spr-confirm-icon"><i class="fa-solid fa-trophy"></i></div>
        <div class="spr-confirm-title">Confirm Challenge Interest</div>
        <div class="spr-confirm-text">
            Choosing interest in this request means your trading strategy must meet the values — it mustn't go below the minimum requests and maximum requests. Once these are met, you will be reviewed if your edge request will be profitable for investors.
        </div>
        <div class="spr-actions">
            <button class="spr-btn primary" id="sprConfirmInterestBtn">Confirm to Proceed</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprConfirmModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- CANCEL INTEREST CONFIRM MODAL -->
<div class="spr-modal" id="sprCancelConfirmModal">
    <div class="spr-modal-card spr-confirm-card">
        <div class="spr-confirm-icon" style="background:#fdecec;color:var(--spr-danger);"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="spr-confirm-title" id="sprCancelConfirmTitle">Cancel Challenge Interest</div>
        <div class="spr-confirm-text" id="sprCancelConfirmText">
            If you cancel this signal challenge, your programme will need to embark on a new challenge duration to participate again. This action cannot be undone.
        </div>
        <div class="spr-actions">
            <button class="spr-btn danger" id="sprCancelInterestConfirmBtn">Yes, Cancel Challenge</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprCancelConfirmModal')">Keep Challenge</button>
        </div>
    </div>
</div>

<!-- CUSTOM ALERT MODAL -->
<div class="spr-modal" id="sprAlertModal">
    <div class="spr-modal-card spr-alert-card">
        <div class="spr-alert-icon info" id="sprAlertIcon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="spr-alert-title" id="sprAlertTitle">Notice</div>
        <div class="spr-alert-text" id="sprAlertText">Message</div>
        <div class="spr-actions">
            <button class="spr-btn primary" id="sprAlertOkBtn">OK</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var CURRENT_MAIN_TAB = 'challenges';
    var CURRENT_PERIOD = 'daily';
    var REQUESTS = [];
    var INTERESTS = [];
    var PENDING_REQUEST = null;
    var PENDING_INTEREST_ID = null;
    var ACTIVE_FILTERS = null;
    var MY_INTEREST = null;

    var cancelFilterBtn = document.getElementById('sprCancelFilterBtn');
    var filterFloat = document.getElementById('sprFilterFloat');
    var subTabs = document.getElementById('sprSubTabs');
    var requestListEl = document.getElementById('sprRequestList');

    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: open ? 'harvhubModalOpen' : 'harvhubModalClose' }, '*');
            }
        } catch (e) {}
    }

    function anyModalOpen() { return !!document.querySelector('.spr-modal.open'); }
    function lockBodyScroll() { document.body.classList.add('spr-scroll-locked'); }
    function unlockBodyScroll() { if (!anyModalOpen()) document.body.classList.remove('spr-scroll-locked'); }

    window.sprGoBack = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'signals' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=signals';
    };

    window.sprOpenModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    window.sprCloseModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        if (!anyModalOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    document.querySelectorAll('.spr-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) sprCloseModal(m.id); });
    });

    window.sprAlert = function (message, kind, title) {
        kind = kind || 'info';
        var iconEl  = document.getElementById('sprAlertIcon');
        var titleEl = document.getElementById('sprAlertTitle');
        var textEl  = document.getElementById('sprAlertText');

        iconEl.classList.remove('info', 'success', 'warning', 'error');
        iconEl.classList.add(kind);

        var iconHtml = '<i class="fa-solid fa-circle-info"></i>';
        if (kind === 'success') iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        if (kind === 'warning') iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        if (kind === 'error')   iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        iconEl.innerHTML = iconHtml;

        titleEl.textContent = title || (kind === 'error' ? 'Error' : (kind === 'success' ? 'Success' : (kind === 'warning' ? 'Warning' : 'Notice')));
        textEl.textContent  = message || '';

        sprOpenModal('sprAlertModal');
    };

    document.getElementById('sprAlertOkBtn').addEventListener('click', function () {
        sprCloseModal('sprAlertModal');
    });

    function post(data, cb) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        fetch('signals_provision_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { cb(d || {}); })
        .catch(function () { cb({ success: false }); });
    }

    function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

    function rrTypeLabel(t) {
        switch (t) {
            case 'fixed_risk_reward': return 'Fixed Risk Reward';
            case 'custom_and_minimum_risk_reward': return 'Custom & Minimum Risk Reward';
            case 'custom_and_fixed_risk_reward': return 'Custom & Fixed Risk Reward';
            default: return 'Fixed Risk Reward';
        }
    }

    function renderRequest(req, myInterest) {
        var isSystem = (parseInt(req.user_id, 10) || 0) === 0;
        var isMine = !!(myInterest && req.id && parseInt(myInterest.id, 10) === parseInt(req.id, 10));
        var hasAnyInterest = !!(myInterest && myInterest.id);

        var name = isSystem ? 'System Challenge' :
            (req.username || req.first_name || req.fullname || ('Trader #' + req.user_id));

        var rr = parseFloat(req.risk_reward) || 0;
        var rrType = rrTypeLabel(req.risk_reward_type);
        var loss = parseInt(req.consecutive_loss, 10) || 0;
        var trCount = parseInt(req.trades_count, 10) || 0;
        var expWin = parseInt(req.expected_win, 10) || 0;

        var periodLabel = CURRENT_PERIOD.charAt(0).toUpperCase() + CURRENT_PERIOD.slice(1);

        var html = '';
        html += '<div class="spr-item" data-request-id="' + (req.id || 0) + '">';
        html +=   '<div class="spr-item-main">';
        html +=     '<div class="spr-item-header">';
        html +=       '<div class="spr-item-title">' + escapeHtml(name) + (isSystem ? ' <span style="font-size:.72rem;color:var(--spr-muted);font-weight:600;">· System</span>' : '') + '</div>';
        html +=       '<div class="spr-item-sub">' + escapeHtml(periodLabel) + ' trades challenge</div>';
        html +=     '</div>';
        html +=     '<div class="spr-meta-row">';

        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Trades Provision:</span><span class="spr-meta-value">' + escapeHtml(periodLabel) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward Type:</span><span class="spr-meta-value">' + escapeHtml(rrType) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward</span><span class="spr-meta-value">1:' + rr + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Trades Count</span><span class="spr-meta-value">' + trCount + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Expected Win</span><span class="spr-meta-value">' + expWin + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Consecutive Expected Loss</span><span class="spr-meta-value">' + loss + '</span></div>';

        html +=     '</div>';
        html +=   '</div>';

        if (isMine) {
            html += '<button class="spr-interested-btn is-mine" disabled>Interested ✓</button>';
        } else if (hasAnyInterest) {
            html += '<button class="spr-interested-btn is-readonly" disabled title="You already have an active challenge interest for this programme.">Already In a challenge</button>';
        } else {
            html += '<button class="spr-interested-btn" onclick="sprSelectRequest(' + (req.id || 0) + ')">Interested</button>';
        }

        html += '</div>';
        return html;
    }

    function renderInterestItem(interest) {
        var periodLabel = (interest.trades_provision || 'daily').charAt(0).toUpperCase() + (interest.trades_provision || 'daily').slice(1);
        var rrType = rrTypeLabel(interest.risk_reward_type);
        var status = (interest.interest_status || 'interested').toLowerCase();

        var statusColors = {
            'interested': 'var(--spr-info)',
            'pending': 'var(--spr-warning)',
            'active': 'var(--spr-accent)'
        };
        var statusColor = statusColors[status] || 'var(--spr-info)';

        var html = '';
        html += '<div class="spr-item" data-interest-id="' + interest.id + '">';
        html +=   '<div class="spr-item-main">';
        html +=     '<div class="spr-item-header">';
        html +=       '<div class="spr-item-title">' + escapeHtml(periodLabel) + ' Challenge</div>';
        html +=       '<div class="spr-item-sub">Status: <span style="color:' + statusColor + ';font-weight:800;text-transform:capitalize;">' + escapeHtml(status) + '</span></div>';
        html +=     '</div>';
        html +=     '<div class="spr-meta-row">';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward Type:</span><span class="spr-meta-value">' + escapeHtml(rrType) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward</span><span class="spr-meta-value">1:' + (parseFloat(interest.risk_reward) || 0) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Trades Count</span><span class="spr-meta-value">' + (parseInt(interest.trades_count, 10) || 0) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Expected Win</span><span class="spr-meta-value">' + (parseInt(interest.expected_win, 10) || 0) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Consecutive Expected Loss</span><span class="spr-meta-value">' + (parseInt(interest.consecutive_loss, 10) || 0) + '</span></div>';
        html +=     '</div>';
        html +=   '</div>';
        html +=   '<div class="spr-item-actions">';
        html +=     '<button class="spr-cancel-btn" onclick="sprCancelInterest(' + interest.id + ')">Cancel Challenge</button>';
        html +=   '</div>';
        html += '</div>';
        return html;
    }

    function updateCancelFilterButton() {
        cancelFilterBtn.hidden = !ACTIVE_FILTERS;
    }

    function updateUIForTab() {
        if (CURRENT_MAIN_TAB === 'challenges') {
            subTabs.hidden = false;
            filterFloat.style.display = '';
        } else {
            subTabs.hidden = true;
            filterFloat.style.display = 'none';
        }
    }

    function renderChallenges() {
        if (!REQUESTS.length) {
            requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-trophy"></i><strong>No challenges match</strong><p>Try a different filter or period.</p></div>';
            updateCancelFilterButton();
            return;
        }

        post({ action: 'check_my_interest' }, function (d) {
            MY_INTEREST = (d && d.success && d.interest) ? d.interest : null;
            var html = '';
            REQUESTS.forEach(function (r) { html += renderRequest(r, MY_INTEREST); });
            requestListEl.innerHTML = html;
            updateCancelFilterButton();
        });
    }

    function renderInterests() {
        if (!INTERESTS.length) {
            requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-bookmark"></i><strong>No active challenge interests</strong><p>Go to Signals Challenge tab and click Interested to join a challenge.</p></div>';
            return;
        }

        var html = '';
        INTERESTS.forEach(function (i) { html += renderInterestItem(i); });
        requestListEl.innerHTML = html;
    }

    function loadChallenges() {
        requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading challenges…</p></div>';

        post({ action: 'get_requests', period: CURRENT_PERIOD }, function (d) {
            if (!d || !d.success) {
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load challenges.') + '</p></div>';
                return;
            }
            REQUESTS = d.requests || [];
            renderChallenges();
        });
    }

    function loadInterests() {
        requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading your interests…</p></div>';

        post({ action: 'get_my_interests' }, function (d) {
            if (!d || !d.success) {
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load interests.') + '</p></div>';
                return;
            }
            INTERESTS = d.interests || [];
            renderInterests();
        });
    }

    function loadCurrentTab() {
        if (CURRENT_MAIN_TAB === 'challenges') {
            loadChallenges();
        } else {
            loadInterests();
        }
    }

    document.querySelectorAll('.spr-main-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.spr-main-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            CURRENT_MAIN_TAB = t.dataset.mainTab;
            ACTIVE_FILTERS = null;
            updateCancelFilterButton();
            updateUIForTab();
            loadCurrentTab();
        });
    });

    document.querySelectorAll('.spr-sub-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.spr-sub-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            CURRENT_PERIOD = t.dataset.period;
            ACTIVE_FILTERS = null;
            updateCancelFilterButton();
            loadChallenges();
        });
    });

    window.sprCancelFilter = function () {
        ACTIVE_FILTERS = null;
        updateCancelFilterButton();
        loadChallenges();
    };

    var filterTypeSelect = document.getElementById('sprFilterType');
    var filterDetails = document.getElementById('sprFilterDetails');
    var rrLabel = document.getElementById('sprRrLabel');

    function syncFilterDetailsVisibility() {
        var v = filterTypeSelect.value;
        if (v) {
            filterDetails.classList.add('visible');
            if (v === 'custom_and_minimum_risk_reward') rrLabel.textContent = 'Minimum Risk Reward (1:?)';
            else if (v === 'custom_and_fixed_risk_reward') rrLabel.textContent = 'Fixed Risk Reward (1:?)';
            else rrLabel.textContent = 'Risk Reward (1:?)';
        } else {
            filterDetails.classList.remove('visible');
        }
    }

    filterTypeSelect.addEventListener('change', function () {
        syncFilterDetailsVisibility();
        document.getElementById('sprFilterErr').style.display = 'none';
    });

    window.sprOpenFilterModal = function () {
        document.getElementById('sprFilterErr').style.display = 'none';
        syncFilterDetailsVisibility();
        sprOpenModal('sprFilterModal');
    };

    (function initRrInput() {
        var el = document.getElementById('sprFilterRr');
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('spellcheck', 'false');
        el.setAttribute('name', 'rr_' + Math.random().toString(36).slice(2));
        el.setAttribute('type', 'text');

        function extractDigits(v) {
            if (!v) return '';
            var s = v;
            if (s.indexOf('1:') === 0) s = s.slice(2);
            return s.replace(/[^0-9]/g, '').slice(0, 5);
        }

        el.addEventListener('input', function () {
            var digits = extractDigits(this.value);
            this.value = digits === '' ? '' : '1:' + digits;
        });

        el.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.metaKey) return;
            if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Tab','Home','End','Enter','Escape'].indexOf(e.key) !== -1) return;
            if (e.key === 'Backspace') {
                var v = this.value || '';
                if (v === '1:' || v === '') { e.preventDefault(); this.value = ''; return; }
                var s = this.selectionStart, t = this.selectionEnd;
                if (s === t && s <= 2) { e.preventDefault(); this.value = ''; return; }
                return;
            }
            if (e.key.length === 1 && !/[0-9]/.test(e.key)) e.preventDefault();
        });

        el.addEventListener('focus', function () {
            var digits = extractDigits(this.value);
            this.value = digits === '' ? '' : '1:' + digits;
        });
        el.addEventListener('blur', function () { if (this.value === '1:') this.value = ''; });
    })();

    function parseRrInput() {
        var el = document.getElementById('sprFilterRr');
        var raw = (el.value || '').trim();
        if (raw === '' || raw === '1:') return null;
        if (raw.indexOf('1:') === 0) raw = raw.slice(2);
        var digits = raw.replace(/[^0-9]/g, '');
        if (digits === '') return null;
        var num = parseInt(digits, 10);
        return isNaN(num) ? null : num;
    }

    window.sprApplyFilter = function () {
        var err = document.getElementById('sprFilterErr');
        var selectedType = filterTypeSelect.value;

        if (!selectedType) {
            err.textContent = 'Select risk reward type before proceeding';
            err.style.display = 'block';
            return;
        }

        var f = {
            type: selectedType,
            rr: parseRrInput(),
            trades: document.getElementById('sprFilterTrades').value !== '' ? parseInt(document.getElementById('sprFilterTrades').value, 10) : null,
            win: document.getElementById('sprFilterWin').value !== '' ? parseInt(document.getElementById('sprFilterWin').value, 10) : null,
            loss: document.getElementById('sprFilterLoss').value !== '' ? parseInt(document.getElementById('sprFilterLoss').value, 10) : null,
        };

        if (f.rr === null || f.trades === null || f.win === null || f.loss === null) {
            err.textContent = 'Fill in all fields: risk reward, trades count, expected win, and consecutive loss.';
            err.style.display = 'block';
            return;
        }

        if (f.rr < 1 || f.rr > 100) { err.textContent = 'Risk reward must be 1–100.'; err.style.display = 'block'; return; }
        if (f.trades < 1 || f.trades > 100) { err.textContent = 'Trades count must be 1–100.'; err.style.display = 'block'; return; }
        if (f.win < 1 || f.win > 100) { err.textContent = 'Expected win must be 1–100.'; err.style.display = 'block'; return; }
        if (f.loss < 1 || f.loss > 100) { err.textContent = 'Consecutive loss must be 1–100.'; err.style.display = 'block'; return; }

        if (f.win > f.trades) {
            err.textContent = 'Expected win cannot be greater than trades count.'; err.style.display = 'block'; return;
        }
        if (f.loss >= f.trades) {
            err.textContent = 'Consecutive loss must be less than trades count — otherwise you would be blown.'; err.style.display = 'block'; return;
        }

        ACTIVE_FILTERS = f;
        updateCancelFilterButton();

        requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Applying filter…</p></div>';

        var payload = {
            action: 'filter_requests',
            period: CURRENT_PERIOD,
            risk_reward_type: f.type,
            risk_reward: f.rr,
            trades_count: f.trades,
            expected_win: f.win,
            consecutive_loss: f.loss,
        };

        post(payload, function (d) {
            if (!d || !d.success) {
                err.textContent = (d && d.message) || 'Could not apply filter.';
                err.style.display = 'block';
                sprOpenModal('sprFilterModal');
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-trophy"></i><strong>No match</strong><p>' + escapeHtml((d && d.message) || 'This configuration is not valid.') + '</p></div>';
                return;
            }

            REQUESTS = d.requests || [];
            sprCloseModal('sprFilterModal');
            renderChallenges();
        });
    };

    window.sprSelectRequest = function (requestId) {
        PENDING_REQUEST = REQUESTS.filter(function (r) { return parseInt(r.id, 10) === requestId; })[0] || null;
        if (!PENDING_REQUEST) return;
        sprOpenModal('sprConfirmModal');
    };

    document.getElementById('sprConfirmInterestBtn').addEventListener('click', function () {
        if (!PENDING_REQUEST) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Saving…';

        var data = {
            action: 'save_interest',
            request_id: PENDING_REQUEST.id || 0,
            period: CURRENT_PERIOD,
            risk_reward_type: PENDING_REQUEST.risk_reward_type || 'fixed_risk_reward',
            risk_reward: PENDING_REQUEST.risk_reward || 2,
            consecutive_loss: PENDING_REQUEST.consecutive_loss || 2,
            trades_count: PENDING_REQUEST.trades_count || 1,
            expected_win: PENDING_REQUEST.expected_win || 1,
        };

        post(data, function (d) {
            btn.disabled = false;
            btn.textContent = 'Confirm to Proceed';
            if (d && d.success) {
                sprCloseModal('sprConfirmModal');
                sprAlert('Your challenge interest has been saved successfully. You can now begin your training and testing.', 'success', 'Interest Registered');
                loadChallenges();
            } else {
                sprAlert((d && d.message) || 'Failed to save interest.', 'error', 'Could not save');
            }
        });
    });

    window.sprCancelInterest = function (interestId) {
        PENDING_INTEREST_ID = interestId;
        document.getElementById('sprCancelConfirmTitle').textContent = 'Cancel Challenge Interest';
        document.getElementById('sprCancelConfirmText').textContent =
            'If you cancel this signal challenge, your programme will need to embark on a new challenge duration to participate again. This action cannot be undone.';
        sprOpenModal('sprCancelConfirmModal');
    };

    document.getElementById('sprCancelInterestConfirmBtn').addEventListener('click', function () {
        if (!PENDING_INTEREST_ID) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Cancelling…';

        post({ action: 'cancel_interest', interest_id: PENDING_INTEREST_ID }, function (d) {
            btn.disabled = false;
            btn.textContent = 'Yes, Cancel Challenge';

            if (d && d.success) {
                sprCloseModal('sprCancelConfirmModal');
                sprAlert(
                    'Your challenge interest has been cancelled. Your programme will need to embark on a new challenge duration to participate again.',
                    'warning',
                    'Challenge Cancelled'
                );
                PENDING_INTEREST_ID = null;
                loadInterests();
            } else {
                sprAlert((d && d.message) || 'Failed to cancel challenge.', 'error', 'Could not cancel');
            }
        });
    });

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}

    syncFilterDetailsVisibility();
    updateCancelFilterButton();
    updateUIForTab();
    loadCurrentTab();
})();
</script>

</body>
</html>