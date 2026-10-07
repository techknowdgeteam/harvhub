<?php
// programmes.php — Investor view (tabbed: Programmes + Invested + Sent)
// Per sub-account scoped. Broker-gated. Request flow for joining.
session_start();

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die("Database connection failed.");
}

// ==================== CENTRAL NOTIFICATION SERVICE ====================
require_once __DIR__ . '/notification_service.php';

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== RESOLVE ACTIVE SUB ACCOUNT ====================
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$fullName      = $user['fullname'] ?? 'User';
$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$viewerBroker = strtolower(trim((string)($user['broker'] ?? '')));

// ==================== HELPERS ====================
if (!function_exists('resolveDeveloperDisplayName')) {
    function resolveDeveloperDisplayName(array $devRow) {
        $username = trim((string)($devRow['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($devRow['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($devRow['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($devRow['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('resolvePeriodLabel')) {
    function resolvePeriodLabel($period) {
        $p = strtolower(trim((string)$period));
        if ($p === 'weekly')  return 'Weekly';
        if ($p === 'monthly') return 'Monthly';
        if ($p === 'daily')   return 'Daily';
        return 'Daily';
    }
}

if (!function_exists('normalizeBroker')) {
    function normalizeBroker($b) {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', (string)$b)));
    }
}

if (!function_exists('resolveProgrammeBroker')) {
    function resolveProgrammeBroker(array $programmeRow, $pdo) {
        $b = normalizeBroker($programmeRow['broker'] ?? '');
        if ($b !== '') return $b;

        if (!empty($programmeRow['userid'])) {
            try {
                $q = $pdo->prepare("SELECT broker FROM harvhub WHERE id = ? LIMIT 1");
                $q->execute([(int)$programmeRow['userid']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['broker'])) {
                    return normalizeBroker($row['broker']);
                }
            } catch (PDOException $e) {}
        }
        return '';
    }
}

if (!function_exists('prettyBroker')) {
    function prettyBroker($b) {
        $b = trim((string)$b);
        if ($b === '') return '';
        return ucfirst(strtolower($b));
    }
}

if (!function_exists('resolveHostDisplayNameCompat')) {
    function resolveHostDisplayNameCompat(array $r) {
        $username = trim((string)($r['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($r['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($r['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($r['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('resolveUserEmailById')) {
    function resolveUserEmailById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT email FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return strtolower(trim((string)($row['email'] ?? '')));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('resolveUserMainAccountIdById')) {
    function resolveUserMainAccountIdById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['main_account_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('resolveUserSubAccountIdById')) {
    function resolveUserSubAccountIdById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT sub_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['sub_account_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

// =====================================================================
// AJAX ROUTER
// =====================================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');

    // -------- GET PROGRAMME DETAILS --------
    if (isset($_POST['get_programme_details'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id,
                       p.program_name, p.visibility, p.advertisement, p.broker,
                       h.fullname, h.first_name, h.last_name, h.username,
                       h.email AS developer_email
                FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE p.id = ? AND p.visibility = 1
                LIMIT 1
            ");
            $stmt->execute([$pid]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not public.']);
                exit;
            }

            $developerName  = resolveDeveloperDisplayName($programme);
            $programmeBroker = resolveProgrammeBroker($programme, $pdo);

            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$pid, (int)$programme['userid']]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $stmt = $pdo->prepare("
                    SELECT contract_duration, developer_percentage, investor_percentage,
                           minimum_investment_amount, maximum_investment_amount
                    FROM programme_investors
                    WHERE programme_id = ? AND investorid = 0
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmt->execute([$pid]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
            $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
            $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
            $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

            $contractDuration = $req && (int)$req['contract_duration'] > 0 ? (int)$req['contract_duration'] : $srvContractDuration;
            $minInvestment    = $req && (float)$req['minimum_investment_amount'] > 0 ? (float)$req['minimum_investment_amount'] : $srvMinBrokerBalance;
            $maxInvestment    = $req ? (float)$req['maximum_investment_amount'] : 0;
            $developerPercent = $req && (int)$req['developer_percentage'] > 0 ? (int)$req['developer_percentage'] : $srvServerShare;
            $investorPercent  = $req && (int)$req['investor_percentage'] > 0 ? (int)$req['investor_percentage'] : $srvUserShare;

            $analytics = [
                'winrate'                    => null,
                'lossrate'                   => null,
                'risk_reward'                => null,
                'period'                     => 'Daily',
                'highest_drawdown'           => null,
                'consecutive_sequential_loss'=> null,
                'highest_trades_per_day'     => null,
            ];
            try {
                $aStmt = $pdo->prepare("
                    SELECT winrate, lossrate, risk_reward, period,
                           highest_drawdown, consecutive_sequential_loss, highest_trades_per_day
                    FROM programme_analytics
                    WHERE programme_id = ?
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $aStmt->execute([$pid]);
                $aRow = $aStmt->fetch(PDO::FETCH_ASSOC);
                if ($aRow) {
                    $analytics['winrate']                     = $aRow['winrate'];
                    $analytics['lossrate']                    = $aRow['lossrate'];
                    $analytics['risk_reward']                 = $aRow['risk_reward'];
                    $analytics['period']                      = resolvePeriodLabel($aRow['period'] ?? 'daily');
                    $analytics['highest_drawdown']            = $aRow['highest_drawdown'];
                    $analytics['consecutive_sequential_loss'] = $aRow['consecutive_sequential_loss'];
                    $analytics['highest_trades_per_day']      = $aRow['highest_trades_per_day'];
                }
            } catch (PDOException $e) {}

            $alreadyInvested = false;
            try {
                $chk = $pdo->prepare("
                    SELECT id FROM programme_investors
                    WHERE investorid = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                      AND programme_id = ?
                    LIMIT 1
                ");
                $chk->execute([$userId, $activeSubAccountId, $pid]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) $alreadyInvested = true;
            } catch (PDOException $e) {}

            $hasAnyInvestment = false;
            try {
                $chk2 = $pdo->prepare("
                    SELECT id FROM programme_investors
                    WHERE investorid = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                    LIMIT 1
                ");
                $chk2->execute([$userId, $activeSubAccountId]);
                if ($chk2->fetch(PDO::FETCH_ASSOC)) $hasAnyInvestment = true;
            } catch (PDOException $e) {}

            $alreadyRequested = false;
            try {
                $rq = $pdo->prepare("
                    SELECT id FROM programme_investment_requestors
                    WHERE programme_id = ?
                      AND requestor_id = ?
                      AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                      AND request_status = 'pending'
                    LIMIT 1
                ");
                $rq->execute([$pid, $userId, $activeSubAccountId]);
                if ($rq->fetch(PDO::FETCH_ASSOC)) $alreadyRequested = true;
            } catch (PDOException $e) {}

            $brokerMatches = false;
            if ($programmeBroker !== '' && $viewerBroker !== '') {
                $brokerMatches = ($programmeBroker === $viewerBroker);
            }
            $viewerHasBroker = ($viewerBroker !== '');

            echo json_encode([
                'success'   => true,
                'programme' => [
                    'id'              => (int)$programme['id'],
                    'program_name'    => $programme['program_name'],
                    'developer_name'  => $developerName,
                    'developer_email' => $programme['developer_email'] ?: '',
                    'developer_id'    => (int)$programme['userid'],
                    'developer_sub_account_id' => (int)($programme['developer_sub_account_id'] ?? 0),
                    'visibility'      => (int)$programme['visibility'],
                    'advertisement'   => (int)$programme['advertisement'],
                    'broker'          => $programmeBroker,
                    'broker_pretty'   => prettyBroker($programmeBroker),
                    'contract_duration'         => $contractDuration,
                    'developer_percentage'      => $developerPercent,
                    'investor_percentage'       => $investorPercent,
                    'minimum_investment_amount' => $minInvestment,
                    'maximum_investment_amount' => $maxInvestment,
                    'analytics'                 => $analytics,
                ],
                'already_invested'   => $alreadyInvested,
                'has_any_investment' => $hasAnyInvestment,
                'already_requested'  => $alreadyRequested,
                'viewer_id'          => $userId,
                'viewer_broker'      => $viewerBroker,
                'viewer_has_broker'  => $viewerHasBroker,
                'broker_matches'     => $brokerMatches,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load programme details.']);
        }
        exit;
    }

    // -------- SEND INVESTMENT REQUEST --------
    if (isset($_POST['send_investment_request'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id, p.broker, p.program_name
                FROM programme p
                WHERE p.id = ? AND p.visibility = 1
                LIMIT 1
            ");
            $stmt->execute([$pid]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not public.']);
                exit;
            }

            $developerId     = (int)$programme['userid'];
            $developerSubId  = (int)($programme['developer_sub_account_id'] ?? 0);
            $programmeBroker = resolveProgrammeBroker($programme, $pdo);
            $programmeName   = (string)($programme['program_name'] ?? 'Programme');

            if ($programmeBroker === '') {
                echo json_encode(['success' => false, 'message' => "This programme doesn't have a broker configured yet. Please try again later."]);
                exit;
            }
            if ($viewerBroker === '') {
                echo json_encode(['success' => false, 'message' => "You need to connect a broker before sending a request. This programme uses " . prettyBroker($programmeBroker) . ". Please connect the same broker to continue."]);
                exit;
            }
            if ($viewerBroker !== $programmeBroker) {
                echo json_encode(['success' => false, 'message' => "Broker mismatch. This programme uses " . prettyBroker($programmeBroker) . ", but your connected broker is " . prettyBroker($viewerBroker) . ". Please link the same broker as the developer."]);
                exit;
            }

            $chk = $pdo->prepare("
                SELECT id FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
                  AND programme_id = ?
                LIMIT 1
            ");
            $chk->execute([$userId, $activeSubAccountId, $pid]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account has already invested in this programme.']);
                exit;
            }

            $chk2 = $pdo->prepare("
                SELECT id FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
                LIMIT 1
            ");
            $chk2->execute([$userId, $activeSubAccountId]);
            if ($chk2->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account is already participating in a programme. Finish or reset it before joining another.']);
                exit;
            }

            $dup = $pdo->prepare("
                SELECT id FROM programme_investment_requestors
                WHERE programme_id = ?
                  AND requestor_id = ?
                  AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                  AND request_status = 'pending'
                LIMIT 1
            ");
            $dup->execute([$pid, $userId, $activeSubAccountId]);
            if ($dup->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account already has a pending request for this programme.']);
                exit;
            }

            $ins = $pdo->prepare("
                INSERT INTO programme_investment_requestors
                    (programme_id, developerid, owner_sub_account_id,
                     requestor_id, requestor_sub_account_id, request_status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([
                $pid,
                $developerId,
                $developerSubId ?: null,
                $userId,
                $activeSubAccountId
            ]);

            // ============================================
            // NOTIFICATION: NEW INVESTMENT REQUEST (to developer)
            // ============================================
            $investorDisplayName = trim((string)($user['username'] ?? ''));
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['first_name'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['fullname'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = 'An investor';
            }

            $developerEmail      = resolveUserEmailById($pdo, $developerId);
            $developerMainAccId  = resolveUserMainAccountIdById($pdo, $developerId);
            $developerSubIdResolved = $developerSubId > 0 ? $developerSubId : resolveUserSubAccountIdById($pdo, $developerId);

            if ($developerEmail !== '' && $developerSubIdResolved > 0) {
                recordContractNotification($pdo, [
                    'user_email'       => $developerEmail,
                    'sub_account_id'   => $developerSubIdResolved,
                    'main_account_id'  => $developerMainAccId,
                    'notification_key' => 'programme-investment-request-' . $pid . '-' . $activeSubAccountId . '-' . date('YmdHis'),
                    'title'            => 'New Investment Request',
                    'message'          => $investorDisplayName . ' has requested to join your ' . $programmeName . ' programme.',
                    'type'             => 'info',
                    'section'          => 'Programme',
                    'action_tab'       => 'programmes',
                    'force'            => true
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Investment request sent successfully.',
                'programme_id' => $pid,
                'developer_id' => $developerId
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send investment request.']);
        }
        exit;
    }

    // -------- GET SENT REQUESTS --------
    if (isset($_POST['get_sent_investment_requests'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.programme_id, r.developerid, r.owner_sub_account_id,
                       r.request_status, r.created_at,
                       h.fullname, h.first_name, h.last_name, h.username,
                       p.program_name
                FROM programme_investment_requestors r
                LEFT JOIN harvhub   h ON h.id = r.developerid
                LEFT JOIN programme p ON p.id = r.programme_id
                WHERE r.requestor_id = ?
                  AND (r.requestor_sub_account_id = ? OR r.requestor_sub_account_id IS NULL)
                  AND r.request_status IN ('pending', 'reject')
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$userId, $activeSubAccountId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $r['developer_display_name'] = resolveHostDisplayNameCompat($r);
            }
            unset($r);

            echo json_encode(['success' => true, 'requests' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'requests' => [], 'message' => 'Failed to load sent requests.']);
        }
        exit;
    }

    // -------- DELETE SENT INVESTMENT REQUEST --------
    if (isset($_POST['delete_investment_request'])) {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM programme_investment_requestors WHERE id = ? LIMIT 1");
            $stmt->execute([$requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Request not found.']);
                exit;
            }

            if ((int)$row['requestor_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'You can only delete your own sent requests.']);
                exit;
            }

            $rowReqSub = isset($row['requestor_sub_account_id']) ? (int)$row['requestor_sub_account_id'] : 0;
            if ($rowReqSub > 0 && $rowReqSub !== $activeSubAccountId) {
                echo json_encode(['success' => false, 'message' => 'This request does not belong to the current account.']);
                exit;
            }

            $del = $pdo->prepare("DELETE FROM programme_investment_requestors WHERE id = ?");
            $del->execute([$requestId]);

            echo json_encode(['success' => true, 'message' => 'Request deleted.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete request.']);
        }
        exit;
    }

    // -------- CANCEL PROGRAMME --------
    if (isset($_POST['cancel_programme'])) {
        try {
            $hasActiveContract     = false;
            $hasPaymentRequirement = false;

            $contract_duration_cfg = 30;
            try {
                $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
                $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
                if ($cfg && !empty($cfg['contract_duration'])) {
                    $contract_duration_cfg = (int)$cfg['contract_duration'];
                }
            } catch (Exception $e) {}

            $exec_start = $user['execution_start_date'] ?? null;
            if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
                try {
                    $start = new DateTime($exec_start);
                    $end   = clone $start;
                    $end->modify("+{$contract_duration_cfg} days");
                    $today = new DateTime();
                    $today->setTime(0, 0, 0);
                    $endClone = clone $end;
                    $endClone->setTime(0, 0, 0);
                    $daysLeft = (int)$today->diff($endClone)->format('%r%a');
                    if ($daysLeft > 0) $hasActiveContract = true;
                } catch (Exception $e) {}
            }

            $payment_issue_statuses = [
                'unpaid-payment', 'unpaid',
                'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
                'contract-cancelled-payment-required',
                'payment-failed', 'failed-payment',
                'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
                'payment-made', 'contract-cancelled-payment-made',
                'pending_payment'
            ];

            $loyalties_status = $user['loyalties'] ?? null;
            if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
                $hasPaymentRequirement = true;
            }

            try {
                $revStmt = $pdo->prepare("
                    SELECT loyalties FROM revenue_history
                    WHERE user_email = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $revStmt->execute([$email, $activeSubAccountId]);
                $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
                if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
                    $hasPaymentRequirement = true;
                }
            } catch (Exception $e) {}

            if ($hasActiveContract) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a contract is active."]);
                exit;
            }
            if ($hasPaymentRequirement) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a payment is required."]);
                exit;
            }

            // Capture programme/developer info BEFORE deleting for the notification
            $cancelProgrammeId = 0;
            $cancelProgrammeName = '';
            $cancelDeveloperId = 0;
            $cancelDeveloperSubId = 0;
            try {
                $lookup = $pdo->prepare("
                    SELECT pi.programme_id, pi.developerid,
                           p.program_name, p.sub_account_id AS programme_dev_sub
                    FROM programme_investors pi
                    LEFT JOIN programme p ON p.id = pi.programme_id
                    WHERE pi.investorid = ?
                      AND (pi.sub_account_id = ? OR pi.sub_account_id IS NULL)
                    LIMIT 1
                ");
                $lookup->execute([$userId, $activeSubAccountId]);
                $cancelRow = $lookup->fetch(PDO::FETCH_ASSOC);
                if ($cancelRow) {
                    $cancelProgrammeId    = (int)$cancelRow['programme_id'];
                    $cancelProgrammeName  = (string)($cancelRow['program_name'] ?? 'Programme');
                    $cancelDeveloperId    = (int)$cancelRow['developerid'];
                    $cancelDeveloperSubId = (int)($cancelRow['programme_dev_sub'] ?? 0);
                }
            } catch (Throwable $e) {}

            $del = $pdo->prepare("
                DELETE FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
            ");
            $del->execute([$userId, $activeSubAccountId]);

            // ============================================
            // NOTIFICATION: PROGRAMME CANCELLED (to developer)
            // ============================================
            $investorDisplayName = trim((string)($user['username'] ?? ''));
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['first_name'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['fullname'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = 'An investor';
            }

            if ($cancelDeveloperId > 0 && $cancelDeveloperSubId > 0) {
                $developerEmail     = resolveUserEmailById($pdo, $cancelDeveloperId);
                $developerMainAccId = resolveUserMainAccountIdById($pdo, $cancelDeveloperId);

                if ($developerEmail !== '') {
                    recordContractNotification($pdo, [
                        'user_email'       => $developerEmail,
                        'sub_account_id'   => $cancelDeveloperSubId,
                        'main_account_id'  => $developerMainAccId,
                        'notification_key' => 'programme-investor-cancelled-' . $cancelProgrammeId . '-' . $activeSubAccountId . '-' . date('YmdHis'),
                        'title'            => 'Investor Cancelled Programme',
                        'message'          => $investorDisplayName . ' has cancelled their participation in your programme "' . $cancelProgrammeName . '".',
                        'type'             => 'warning',
                        'section'          => 'Programme',
                        'action_tab'       => 'programmes',
                        'force'            => true
                    ]);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'This account has successfully cancelled its programme.'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel programme.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// =====================================================================
// NON-AJAX page render
// =====================================================================

// ---- SUB-ACCOUNT SCOPED: this sub-account's active investment ----
$activeInvestment = null;
$activeProgrammeId = 0;
$userHasProgramme = false;

try {
    $stmt = $pdo->prepare("
        SELECT id, programme_id, developerid
        FROM programme_investors
        WHERE investorid = ?
          AND (sub_account_id = ? OR sub_account_id IS NULL)
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
    $activeInvestment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeInvestment) {
        $userHasProgramme = true;
        $activeProgrammeId = (int)$activeInvestment['programme_id'];
    }
} catch (PDOException $e) {}

// ---- Does this sub-account own a programme? ----
$userOwnsProgramme = false;
try {
    $ow = $pdo->prepare("
        SELECT id FROM programme
        WHERE userid = ?
          AND (sub_account_id = ? OR sub_account_id IS NULL)
        LIMIT 1
    ");
    $ow->execute([$userId, $activeSubAccountId]);
    if ($ow->fetch(PDO::FETCH_ASSOC)) $userOwnsProgramme = true;
} catch (PDOException $e) {}
if (!$userOwnsProgramme) {
    try {
        $ow = $pdo->prepare("SELECT id FROM programme WHERE userid = ? LIMIT 1");
        $ow->execute([$userId]);
        if ($ow->fetch(PDO::FETCH_ASSOC)) $userOwnsProgramme = true;
    } catch (PDOException $e) {}
}

// ---- Public programmes list ----
$programmes = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id,
               p.program_name, p.visibility, p.advertisement, p.broker,
               h.fullname, h.first_name, h.last_name, h.username,
               h.email AS developer_email, h.broker AS developer_broker
        FROM programme p
        INNER JOIN harvhub h ON h.id = p.userid
        WHERE p.visibility = 1
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    $rawProgrammes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawProgrammes as $p) {
        $developerName = resolveDeveloperDisplayName($p);
        $programmeBroker = resolveProgrammeBroker($p, $pdo);

        $reqStmt = $pdo->prepare("
            SELECT contract_duration, developer_percentage, investor_percentage,
                   minimum_investment_amount, maximum_investment_amount
            FROM programme_investors
            WHERE programme_id = ? AND developerid = ? AND investorid = 0
            ORDER BY id ASC LIMIT 1
        ");
        $reqStmt->execute([$p['id'], $p['userid']]);
        $req = $reqStmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $reqStmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND investorid = 0
                ORDER BY id ASC LIMIT 1
            ");
            $reqStmt->execute([$p['id']]);
            $req = $reqStmt->fetch(PDO::FETCH_ASSOC);
        }

        $periodLabel = 'Daily';
        try {
            $pStmt = $pdo->prepare("SELECT period FROM programme_analytics WHERE programme_id = ? ORDER BY id ASC LIMIT 1");
            $pStmt->execute([$p['id']]);
            $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
            if ($pRow && !empty($pRow['period'])) $periodLabel = resolvePeriodLabel($pRow['period']);
        } catch (PDOException $e) {}

        $programmes[] = [
            'id' => (int)$p['id'],
            'program_name' => $p['program_name'],
            'broker' => $programmeBroker,
            'broker_pretty' => prettyBroker($programmeBroker),
            'developer_name' => $developerName,
            'developer_email' => $p['developer_email'],
            'developer_username' => $p['username'],
            'developer_first_name' => $p['first_name'],
            'developer_last_name' => $p['last_name'],
            'developer_fullname' => $p['fullname'],
            'contract_duration' => $req ? (int)$req['contract_duration'] : 30,
            'developer_percentage' => $req ? (int)$req['developer_percentage'] : 30,
            'investor_percentage' => $req ? (int)$req['investor_percentage'] : 70,
            'minimum_investment_amount' => $req ? (float)$req['minimum_investment_amount'] : 0,
            'maximum_investment_amount' => $req ? (float)$req['maximum_investment_amount'] : 0,
            'period' => $periodLabel,
        ];
    }
} catch (PDOException $e) {
    $programmes = [];
}

// ==================== FETCH USER'S INVESTED PROGRAMME ====================
$investedProgramme = null;
$investedProgrammeData = null;
$investedAnalytics = [
    'winrate' => null, 'lossrate' => null, 'risk_reward' => null,
    'period' => 'Daily',
    'highest_drawdown' => null,
    'consecutive_sequential_loss' => null, 'highest_trades_per_day' => null,
];
$investedReq = null;
$cancelEligible = false;

$hasActiveContract       = false;
$hasPaymentRequirement   = false;

if ($userHasProgramme) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.userid, p.program_name, p.visibility, p.advertisement, p.broker,
                   h.fullname, h.first_name, h.last_name, h.username,
                   h.email AS developer_email
            FROM programme p
            INNER JOIN harvhub h ON h.id = p.userid
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$activeProgrammeId]);
        $investedProgramme = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if ($investedProgramme) {
        $developerName = resolveDeveloperDisplayName($investedProgramme);
        $programmeBroker = resolveProgrammeBroker($investedProgramme, $pdo);

        try {
            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$activeProgrammeId, (int)$investedProgramme['userid']]);
            $investedReq = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}

        try {
            $stmt = $pdo->prepare("
                SELECT winrate, lossrate, risk_reward, period,
                       highest_drawdown, consecutive_sequential_loss, highest_trades_per_day
                FROM programme_analytics
                WHERE programme_id = ?
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$activeProgrammeId]);
            $aRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($aRow) {
                $investedAnalytics['winrate']                     = $aRow['winrate'];
                $investedAnalytics['lossrate']                    = $aRow['lossrate'];
                $investedAnalytics['risk_reward']                 = $aRow['risk_reward'];
                $investedAnalytics['period']                      = resolvePeriodLabel($aRow['period'] ?? 'daily');
                $investedAnalytics['highest_drawdown']            = $aRow['highest_drawdown'];
                $investedAnalytics['consecutive_sequential_loss'] = $aRow['consecutive_sequential_loss'];
                $investedAnalytics['highest_trades_per_day']      = $aRow['highest_trades_per_day'];
            }
        } catch (PDOException $e) {}

        $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
        $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
        $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
        $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

        $investedProgrammeData = [
            'id'                        => (int)$investedProgramme['id'],
            'program_name'              => $investedProgramme['program_name'],
            'broker'                    => $programmeBroker,
            'broker_pretty'             => prettyBroker($programmeBroker),
            'developer_name'            => $developerName,
            'developer_email'           => $investedProgramme['developer_email'] ?: '',
            'visibility'                => (int)$investedProgramme['visibility'],
            'advertisement'             => (int)$investedProgramme['advertisement'],
            'contract_duration'         => $investedReq && (int)$investedReq['contract_duration'] > 0 ? (int)$investedReq['contract_duration'] : $srvContractDuration,
            'developer_percentage'      => $investedReq && (int)$investedReq['developer_percentage'] > 0 ? (int)$investedReq['developer_percentage'] : $srvServerShare,
            'investor_percentage'       => $investedReq && (int)$investedReq['investor_percentage'] > 0 ? (int)$investedReq['investor_percentage'] : $srvUserShare,
            'minimum_investment_amount' => $investedReq && (float)$investedReq['minimum_investment_amount'] > 0 ? (float)$investedReq['minimum_investment_amount'] : $srvMinBrokerBalance,
            'maximum_investment_amount' => $investedReq ? (float)$investedReq['maximum_investment_amount'] : 0,
            'analytics'                 => $investedAnalytics,
        ];
    }

    $contract_duration_cfg = 30;
    try {
        $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
        $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
        if ($cfg && !empty($cfg['contract_duration'])) {
            $contract_duration_cfg = (int)$cfg['contract_duration'];
        }
    } catch (Exception $e) {}

    $exec_start = $user['execution_start_date'] ?? null;
    if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
        try {
            $start = new DateTime($exec_start);
            $end   = clone $start;
            $end->modify("+{$contract_duration_cfg} days");
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $endClone = clone $end;
            $endClone->setTime(0, 0, 0);
            $daysLeft = (int)$today->diff($endClone)->format('%r%a');
            if ($daysLeft > 0) $hasActiveContract = true;
        } catch (Exception $e) {}
    }

    $payment_issue_statuses = [
        'unpaid-payment', 'unpaid',
        'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
        'contract-cancelled-payment-required',
        'payment-failed', 'failed-payment',
        'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
        'payment-made', 'contract-cancelled-payment-made',
        'pending_payment'
    ];

    $loyalties_status = $user['loyalties'] ?? null;
    if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
        $hasPaymentRequirement = true;
    }

    try {
        $revStmt = $pdo->prepare("
            SELECT loyalties FROM revenue_history
            WHERE user_email = ?
              AND (sub_account_id = ? OR sub_account_id IS NULL)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $revStmt->execute([$email, $activeSubAccountId]);
        $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
        if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            $hasPaymentRequirement = true;
        }
    } catch (Exception $e) {}

    if ($hasActiveContract || $hasPaymentRequirement) {
        $cancelEligible = false;
    } else {
        $cancelEligible = true;
    }
}

$defaultTab = $userHasProgramme ? 'invested' : 'programmes';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programmes - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'programmes_style.php'; ?>
<style>
    .prog-item-broker {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.2px;
        color: var(--accent, #2ecc8f);
        margin-top: 2px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-transform: uppercase;
    }
    .prog-item-broker i { font-size: 0.72rem; opacity: 0.85; }
    .prog-item-broker.is-empty { color: var(--text-muted, #8a9aa8); text-transform: none; font-weight: 500; }

    .prog-detail-broker-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(46,204,143,0.12);
        color: var(--accent, #2ecc8f);
        font-weight: 700;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .prog-detail-broker-badge.is-mismatch {
        background: rgba(255,152,0,0.14);
        color: #ff9800;
    }
    .prog-detail-broker-badge.is-empty {
        background: rgba(127,127,127,0.12);
        color: var(--text-muted, #8a9aa8);
        text-transform: none;
        font-weight: 500;
    }

    .prog-broker-warning {
        margin: 0 0 10px 0;
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(255,152,0,0.12);
        color: #ff9800;
        font-size: 0.82rem;
        line-height: 1.45;
        text-align: left;
    }

    .prog-requests-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .prog-requests-loading {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted, #888);
        font-size: 0.9rem;
    }
    .prog-request-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 8px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }
    .prog-request-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }
    body.dark-mode .prog-request-item {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }
    .prog-request-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .prog-request-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }
    .prog-request-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
        text-align: right;
        word-break: break-word;
    }
    body.dark-mode .prog-request-value { color: var(--text, #eee); }

    .prog-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }
    .prog-status-badge.pending { background: rgba(243,156,18,0.15); color: #f39c12; }

    .prog-btn-request-delete {
        padding: 8px 16px;
        background: rgba(231,76,60,0.15);
        color: #e74c3c;
        border: 1px solid #e74c3c;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .prog-btn-request-delete:hover {
        background: #e74c3c;
        color: #fff;
    }
    .prog-btn-request-delete:active {
        transform: scale(0.97);
    }

    @media (max-width: 480px) {
        .prog-request-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        .prog-request-value { text-align: left; }
        .prog-btn-request-delete {
            width: 100%;
            text-align: center;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> prog-page-body">

<script>
(function () {
    try {
        document.body.classList.remove('prog-detail-view-open');
        document.body.style.position = '';
        document.body.style.top      = '';
        document.body.style.left     = '';
        document.body.style.right    = '';
        document.body.style.width    = '';
        document.body.style.overflow = '';
        delete document.body.dataset.progModalLocked;
        delete document.body.dataset.progModalY;
        ['progDetailModal','progAlertModal','progConfirmModal','progCancelModal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('active');
        });
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: [],
                    remove: ['prog-detail-view-open']
                }, '*');
            } catch (e) {}
        }
    } catch (e) {}
})();
</script>

<div class="prog-page-wrapper" id="progPageWrapper">

    <div class="prog-sticky-top" id="progStickyTop">

        <div class="prog-list-header" id="progListHeader">
            <button type="button"
                    class="prog-list-back"
                    onclick="harvhubGoToTab('mydashboard')"
                    aria-label="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h1 class="prog-list-title" id="progListTitle">Programmes</h1>
        </div>

        <div class="prog-tabs" id="progTabs">
            <button class="prog-tab <?= $defaultTab === 'programmes' ? 'active' : '' ?>"
                    data-tab="programmes"
                    onclick="switchProgTab('programmes')">
                Programmes
            </button>
            <button class="prog-tab <?= $defaultTab === 'invested' ? 'active' : '' ?>"
                    data-tab="invested"
                    onclick="switchProgTab('invested')">
                Invested Programme
            </button>

            <?php if (!$userHasProgramme): ?>
                <button class="prog-tab"
                        data-tab="sent"
                        onclick="switchProgTab('sent')">
                    Sent Requests
                </button>
            <?php endif; ?>

        </div>

        <div class="prog-search-wrap" id="progSearchWrap" style="<?= $defaultTab === 'programmes' && !empty($programmes) ? '' : 'display:none;' ?>">
            <input type="text"
                   id="progSearchInput"
                   class="prog-search-input"
                   placeholder="Search Programme, programme url  or dev username"
                   autocomplete="off"
                   oninput="onProgSearch(this.value)"
                   onfocus="onProgSearch(this.value)">
        </div>
    </div>

    <div class="prog-scroll-area" id="progScrollArea">

        <div class="prog-tab-content <?= $defaultTab === 'programmes' ? 'active' : '' ?>" id="progTabProgrammes">

            <?php if (empty($programmes)): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>No public programmes available at the moment</p>
                </div>
            <?php else: ?>
                <div class="prog-list" id="progList">
                    <?php foreach ($programmes as $p): ?>
                        <div class="prog-item"
                             data-programme-id="<?= (int)$p['id'] ?>"
                             data-search-text="<?= htmlspecialchars(strtolower(
                                 ($p['program_name'] ?? '') . ' ' .
                                 ($p['broker'] ?? '') . ' ' .
                                 ($p['developer_username'] ?? '') . ' ' .
                                 ($p['developer_first_name'] ?? '') . ' ' .
                                 ($p['developer_last_name'] ?? '') . ' ' .
                                 ($p['developer_fullname'] ?? '') . ' ' .
                                 ($p['developer_email'] ?? '')
                             )) ?>">
                            <div class="prog-item-main">
                                <div class="prog-item-header">
                                    <div class="prog-item-name"><?= htmlspecialchars($p['program_name']) ?></div>
                                    <div class="prog-item-actions">
                                        <button type="button"
                                                class="prog-btn-view"
                                                onclick="viewProgramme(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['program_name'])) ?>')">
                                            View
                                        </button>
                                    </div>
                                </div>

                                <?php if (!empty($p['broker_pretty'])): ?>
                                    <div class="prog-item-broker">
                                        <i class="fa-solid fa-link"></i>
                                        <?= htmlspecialchars($p['broker_pretty']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="prog-item-broker is-empty">
                                        <i class="fa-solid fa-link-slash"></i>
                                        Broker not set
                                    </div>
                                <?php endif; ?>

                                <div class="prog-item-meta">
                                    <span class="prog-item-stat">
                                        <span class="prog-item-stat-label">Developed by</span>
                                        <span class="prog-item-stat-value"><?= htmlspecialchars($p['developer_name']) ?></span>
                                    </span>
                                </div>
                                <div class="prog-item-details">
                                    <span class="prog-detail-item">Share: <?= (int)$p['developer_percentage'] ?>%</span>
                                    <span class="prog-detail-item">Min: $<?= number_format($p['minimum_investment_amount'], 2) ?></span>
                                    <?php if ($p['maximum_investment_amount'] > 0): ?>
                                        <span class="prog-detail-item">Max: $<?= number_format($p['maximum_investment_amount'], 2) ?></span>
                                    <?php endif; ?>
                                    <span class="prog-detail-item">Duration: <?= (int)$p['contract_duration'] ?> days</span>
                                    <span class="prog-detail-item"><?= htmlspecialchars($p['period']) ?> Trades Provision</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="progSearchEmpty" class="prog-empty-state" style="display:none;">
                    <span class="prog-empty-icon">—</span>
                    <p>No programmes match your search</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="prog-tab-content <?= $defaultTab === 'invested' ? 'active' : '' ?>" id="progTabInvested">
            <?php if (!$investedProgrammeData): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>No invested programme for this account</p>
                    <p style="margin-top:8px;font-size:0.85em;opacity:0.75;">Join a programme from the Programmes tab to see it here.</p>
                </div>
            <?php else: ?>
                <?php $ip = $investedProgrammeData; ?>
                <div class="prog-invested-card">

                    <div class="prog-detail-header">
                        <div class="prog-detail-name"><?= htmlspecialchars($ip['program_name']) ?></div>

                        <?php if (!empty($ip['broker_pretty'])): ?>
                            <div class="prog-detail-broker-badge">
                                <i class="fa-solid fa-link"></i>
                                <?= htmlspecialchars($ip['broker_pretty']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="prog-detail-dev">
                            Developed by <strong><?= htmlspecialchars($ip['developer_name']) ?></strong>
                        </div>
                    </div>

                    <div class="prog-detail-section-title prog-item-stat-value">Analytics</div>
                    <div class="prog-detail-grid prog-detail-grid-2col">
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Winrate</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['winrate'] !== null && $ip['analytics']['winrate'] !== '' ? htmlspecialchars(rtrim(rtrim(number_format((float)$ip['analytics']['winrate'], 2, '.', ''), '0'), '.')) . '%' : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Lossrate</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['lossrate'] !== null && $ip['analytics']['lossrate'] !== '' ? htmlspecialchars(rtrim(rtrim(number_format((float)$ip['analytics']['lossrate'], 2, '.', ''), '0'), '.')) . '%' : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Risk Reward</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['risk_reward'] !== null && $ip['analytics']['risk_reward'] !== '' ? htmlspecialchars('1:' . rtrim(rtrim(number_format((float)$ip['analytics']['risk_reward'], 2, '.', ''), '0'), '.')) : '—' ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Trades Provision</span>
                            <span class="prog-detail-value"><?= htmlspecialchars($ip['analytics']['period']) ?></span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Consecutive Sequential Loss</span>
                            <span class="prog-detail-value"><?= $ip['analytics']['consecutive_sequential_loss'] !== null && $ip['analytics']['consecutive_sequential_loss'] !== '' ? htmlspecialchars((string)$ip['analytics']['consecutive_sequential_loss']) : '—' ?></span>
                        </div>
                    </div>

                    <div class="prog-detail-section-title prog-item-stat-value">Requirements</div>
                    <div class="prog-detail-grid">
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Contract Duration</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['contract_duration']) ?> days</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Programme Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['developer_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Investor Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['investor_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Minimum Investment</span>
                            <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['minimum_investment_amount'], 2, '.', '')) ?></span>
                        </div>
                        <?php if ((float)$ip['maximum_investment_amount'] > 0): ?>
                            <div class="prog-detail-row">
                                <span class="prog-detail-label">Maximum Investment</span>
                                <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['maximum_investment_amount'], 2, '.', '')) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($cancelEligible): ?>
                        <button type="button"
                                class="prog-btn-cancel"
                                onclick="cancelProgrammeConfirm()">
                            Cancel Programme
                        </button>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        </div>

        <?php if (!$userHasProgramme): ?>
        <div class="prog-tab-content <?= $defaultTab === 'sent' ? 'active' : '' ?>" id="progTabSent">
            <div id="sentRequestsContainer" class="prog-requests-list">
                <div class="prog-requests-loading">Loading sent requests...</div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<div id="progDetailModal" class="prog-modal">
    <div class="prog-modal-content prog-modal-wide">
        <div class="prog-view-header">
            <button type="button" class="prog-view-back" onclick="closeProgDetailModal()" aria-label="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h2 class="prog-view-title" id="progDetailTitle">Explore Programme</h2>
        </div>
        <div class="prog-modal-body prog-view-body" id="progDetailBody">
            <div class="prog-modal-loading">Loading...</div>
        </div>
        <div class="prog-view-actions" id="progDetailActions">
            <button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>
        </div>
    </div>
</div>

<div id="progAlertModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progAlertTitle">Notice</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progAlertMessage" class="prog-alert-text">Message</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progAlertOkBtn" onclick="closeProgAlert()">OK</button>
        </div>
    </div>
</div>

<div id="progConfirmModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progConfirmTitle">Confirm Request</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progConfirmMessage" class="prog-alert-text">Are you sure?</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progConfirmBtn" onclick="progConfirmAction()">Yes, Send Request</button>
            <button type="button" class="prog-modal-close" onclick="closeProgConfirm()">Cancel</button>
        </div>
    </div>
</div>

<div id="progCancelModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progCancelTitle">Cancel Programme</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progCancelMessage" class="prog-alert-text">Are you sure you want to cancel this account's participation in this programme?</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progCancelBtn" onclick="progCancelAction()">Yes, Cancel</button>
            <button type="button" class="prog-modal-close" onclick="closeProgCancel()">Keep Programme</button>
        </div>
    </div>
</div>

<script>
    var _viewerId           = <?= (int)$userId ?>;
    var _activeSubAccountId = <?= (int)$activeSubAccountId ?>;
    var _userHasProgramme   = <?= $userHasProgramme ? 'true' : 'false' ?>;
    var _activeProgrammeId  = <?= (int)$activeProgrammeId ?>;
    var _viewerBroker       = <?= json_encode($viewerBroker) ?>;

    if ('<?= $defaultTab ?>' === 'invested') {
        document.body.classList.add('prog-tab-invested');
    }

    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }
    function escapeAttr(t) {
        return escapeHtml(t).replace(/"/g, '&quot;');
    }

    function lockBodyScroll() {
        if (document.body.dataset.progModalLocked === '1') return;
        var y = window.scrollY || 0;
        document.body.dataset.progModalLocked = '1';
        document.body.dataset.progModalY = y;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + y + 'px';
    }
    function unlockBodyScroll() {
        if (document.body.dataset.progModalLocked !== '1') return;
        var y = parseInt(document.body.dataset.progModalY || '0', 10) || 0;
        document.body.dataset.progModalLocked = '0';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, y);
    }

    function setShellHeaderHidden(hidden) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: hidden ? ['prog-detail-view-open'] : [],
                    remove: hidden ? [] : ['prog-detail-view-open']
                }, '*');
            }
        } catch (e) {}

        if (hidden) {
            document.body.classList.add('prog-detail-view-open');
        } else {
            document.body.classList.remove('prog-detail-view-open');
        }
    }

    function switchProgTab(tab) {
        document.querySelectorAll('.prog-tab').forEach(function (el) {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        document.querySelectorAll('.prog-tab-content').forEach(function (el) {
            el.classList.remove('active');
        });

        document.body.classList.toggle('prog-tab-invested', tab === 'invested');

        if (tab === 'programmes') {
            var t = document.getElementById('progTabProgrammes');
            if (t) t.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Programmes';
            var wrap = document.getElementById('progSearchWrap');
            if (wrap) wrap.style.display = '';

            var listEl = document.getElementById('progList');
            if (listEl) listEl.style.display = '';
            var emptyEl = document.getElementById('progSearchEmpty');
            if (emptyEl) emptyEl.style.display = 'none';

        } else if (tab === 'invested') {
            var t2 = document.getElementById('progTabInvested');
            if (t2) t2.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Invested Programme';
            var wrap2 = document.getElementById('progSearchWrap');
            if (wrap2) wrap2.style.display = 'none';

        } else if (tab === 'sent') {
            var t3 = document.getElementById('progTabSent');
            if (t3) t3.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Sent Investment Request';
            var wrap3 = document.getElementById('progSearchWrap');
            if (wrap3) wrap3.style.display = 'none';
            loadSentInvestmentRequests();
        }

        var scrollArea = document.getElementById('progScrollArea');
        if (scrollArea) scrollArea.scrollTop = 0;
    }
    window.switchProgTab = switchProgTab;

    function onProgSearch(query) {
        var q = (query || '').trim().toLowerCase();
        var listEl = document.getElementById('progList');
        if (!listEl) return;

        var items = listEl.querySelectorAll('.prog-item');
        var anyVisible = false;

        items.forEach(function (el) {
            var searchText = el.getAttribute('data-search-text') || '';
            var matches = (q === '') || (searchText.indexOf(q) !== -1);
            if (matches) { el.style.display = ''; anyVisible = true; }
            else { el.style.display = 'none'; }
        });

        var emptyEl = document.getElementById('progSearchEmpty');
        if (emptyEl) emptyEl.style.display = (anyVisible || q === '') ? 'none' : '';
        listEl.style.display = anyVisible ? '' : 'none';
    }

    function showProgAlert(msg, title, reloadAfter) {
        document.getElementById('progAlertTitle').textContent = title || 'Notice';
        document.getElementById('progAlertMessage').textContent = msg == null ? '' : String(msg);

        var okBtn = document.getElementById('progAlertOkBtn');
        if (okBtn) {
            okBtn.onclick = function () {
                closeProgAlert();
                if (reloadAfter === 'sent') loadSentInvestmentRequests();
                else if (reloadAfter === 'reload') window.location.reload();
            };
        }

        document.getElementById('progAlertModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgAlert() {
        document.getElementById('progAlertModal').classList.remove('active');
        unlockBodyScroll();
    }

    var _pendingRequestPid = null;
    var _pendingRequestName = '';

    function openProgConfirm(pid, name) {
        _pendingRequestPid = pid;
        _pendingRequestName = name || '';
        document.getElementById('progConfirmMessage').textContent =
            'Send an investment request for ' + (name || 'this programme') + '?\n\nThe developer will need to accept before you become an investor.';
        document.getElementById('progConfirmModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgConfirm() {
        document.getElementById('progConfirmModal').classList.remove('active');
        unlockBodyScroll();
        _pendingRequestPid = null;
        _pendingRequestName = '';
    }
    function progConfirmAction() {
        if (!_pendingRequestPid) { closeProgConfirm(); return; }
        var pid = _pendingRequestPid;
        var name = _pendingRequestName;
        closeProgConfirm();
        doSendRequest(pid, name);
    }

    function cancelProgrammeConfirm() {
        document.getElementById('progCancelModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgCancel() {
        document.getElementById('progCancelModal').classList.remove('active');
        unlockBodyScroll();
    }

    function progCancelAction() {
        var btn = document.getElementById('progCancelBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Cancelling...';

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'cancel_programme=1'
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                btn.disabled = false;
                btn.textContent = originalText;
                closeProgCancel();
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();

            if (data.success) {
                showProgAlert(data.message || 'Programme cancelled successfully.', 'Success', 'reload');
            } else {
                showProgAlert(data.message || 'Unable to cancel programme.', 'Error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    function viewProgramme(pid, programmeName) {
        var modal   = document.getElementById('progDetailModal');
        var body    = document.getElementById('progDetailBody');
        var actions = document.getElementById('progDetailActions');
        var title   = document.getElementById('progDetailTitle');

        title.textContent = programmeName || 'Explore Programme';

        body.innerHTML = '<div class="prog-modal-loading">Loading...</div>';
        actions.innerHTML = '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';

        modal.classList.add('active');
        lockBodyScroll();
        setShellHeaderHidden(true);

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'get_programme_details=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                body.innerHTML = '<div class="prog-modal-error">Server returned an unexpected response.</div>';
                return;
            }

            if (!data.success) {
                body.innerHTML = '<div class="prog-modal-error">' + escapeHtml(data.message || 'Unable to load programme details.') + '</div>';
                return;
            }

            var p = data.programme;

            title.textContent = p.program_name || programmeName || 'Explore Programme';

            var html = '';

            html += '<div class="prog-detail-header">';
            html +=   '<div class="prog-detail-dev">Developed by <strong>' + escapeHtml(p.developer_name) + '</strong></div>';

            if (p.broker_pretty) {
                var badgeCls = 'prog-detail-broker-badge';
                if (data.broker_matches) badgeCls += '';
                else if (data.viewer_has_broker) badgeCls += ' is-mismatch';
                html += '<div class="' + badgeCls + '"><i class="fa-solid fa-link"></i> ' + escapeHtml(p.broker_pretty) + '</div>';
            } else {
                html += '<div class="prog-detail-broker-badge is-empty"><i class="fa-solid fa-link-slash"></i> Broker not set</div>';
            }

            html += '</div>';

            html += '<div class="prog-detail-section-title prog-item-stat-value">Analytics</div>';
            html += '<div class="prog-detail-grid prog-detail-grid-2col">';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Winrate</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.winrate : null, '%') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Risk Reward</span><span class="prog-detail-value">' + formatRiskReward(p.analytics ? p.analytics.risk_reward : null) + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Trades Provision</span><span class="prog-detail-value">' + escapeHtml(p.analytics && p.analytics.period ? p.analytics.period : 'Daily') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Consecutive Sequential Loss</span><span class="prog-detail-value">' + formatVal(p.analytics ? p.analytics.consecutive_sequential_loss : null, '') + '</span></div>';
            html += '</div>';

            html += '<div class="prog-detail-section-title prog-item-stat-value">Requirements</div>';
            html += '<div class="prog-detail-grid">';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Contract Duration</span><span class="prog-detail-value">' + escapeHtml(String(p.contract_duration)) + ' days</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Trades Provision</span><span class="prog-detail-value">' + escapeHtml(p.analytics && p.analytics.period ? p.analytics.period : 'Daily') + '</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Programme Share</span><span class="prog-detail-value">' + escapeHtml(String(p.developer_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Investor Share</span><span class="prog-detail-value">' + escapeHtml(String(p.investor_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Minimum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.minimum_investment_amount).toFixed(2)) + '</span></div>';
            if (Number(p.maximum_investment_amount) > 0) {
                html += '<div class="prog-detail-row"><span class="prog-detail-label">Maximum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.maximum_investment_amount).toFixed(2)) + '</span></div>';
            }
            html += '</div>';

            body.innerHTML = html;

            var actionsHtml = '';

            if (data.already_invested) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already Invested</button>';
            } else if (data.has_any_investment) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already in a Programme</button>';
            } else if (!p.broker) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Broker Not Configured</button>';
            } else if (!data.viewer_has_broker) {
                actionsHtml += '<div class="prog-broker-warning">'
                             + 'You need to connect a broker first. This programme uses <strong>' + escapeHtml(p.broker_pretty) + '</strong>. Please connect the same broker to continue.'
                             + '</div>';
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Connect Broker First</button>';
            } else if (!data.broker_matches) {
                actionsHtml += '<div class="prog-broker-warning">'
                             + 'Broker mismatch. This programme uses <strong>' + escapeHtml(p.broker_pretty) + '</strong>, '
                             + 'but your connected broker is <strong>' + escapeHtml(_viewerBroker ? (_viewerBroker.charAt(0).toUpperCase() + _viewerBroker.slice(1)) : 'Unknown') + '</strong>. '
                             + 'Please link the same broker as the developer to continue.'
                             + '</div>';
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Broker Mismatch</button>';
            } else if (data.already_requested) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Requested</button>';
            } else {
                actionsHtml += '<button type="button" class="prog-btn-invest" onclick="openProgConfirm(' + p.id + ', \'' + escapeHtml(p.program_name).replace(/'/g, "\\'") + '\')">Send Investment Request</button>';
            }

            actionsHtml += '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';
            actions.innerHTML = actionsHtml;
        })
        .catch(function () {
            body.innerHTML = '<div class="prog-modal-error">Network error. Please try again.</div>';
        });
    }

    function formatVal(v, suffix) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return escapeHtml(String(v));
        var s;
        if (Number.isInteger(n)) s = String(n);
        else s = n.toFixed(2);
        return escapeHtml(s) + (suffix ? ' ' + suffix : '');
    }

    function formatRiskReward(v) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return escapeHtml(String(v));
        var s = Number.isInteger(n) ? String(n) : n.toFixed(2);
        return escapeHtml('1:' + s);
    }

    function closeProgDetailModal() {
        document.getElementById('progDetailModal').classList.remove('active');
        unlockBodyScroll();
        setShellHeaderHidden(false);
    }

    function doSendRequest(pid, name) {
        var btns = document.querySelectorAll('.prog-btn-invest');
        btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = true; } });

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'send_investment_request=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });

            if (data.success) {
                closeProgDetailModal();
                showProgAlert(data.message || 'Investment request sent.', 'Success', 'reload');
            } else {
                showProgAlert(data.message || 'Unable to send request.', 'Error');
            }
        })
        .catch(function () {
            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    function loadSentInvestmentRequests() {
        var container = document.getElementById('sentRequestsContainer');
        if (!container) return;

        container.innerHTML = '<div class="prog-requests-loading">Loading sent requests...</div>';

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'get_sent_investment_requests=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.requests.length) {
                container.innerHTML = '<div class="prog-empty-state"><span class="prog-empty-icon">—</span><p>This account has not sent any investment requests yet</p></div>';
                return;
            }
            renderProgrammeRequestList(container, data.requests, 'sent');
        })
        .catch(function () {
            container.innerHTML = '<div class="prog-empty-state"><p>Failed to load sent requests</p></div>';
        });
    }
    window.loadSentInvestmentRequests = loadSentInvestmentRequests;

    function renderProgrammeRequestList(container, requests, mode) {
        var html = '<div class="prog-requests-list" style="display:flex;flex-direction:column;gap:12px;">';

        requests.forEach(function (req) {
            var name = req.developer_display_name || 'Anonymous';

            var label    = 'Developer';
            var status   = (req.request_status || 'pending').toLowerCase();
            var programme = req.program_name || ('Programme #' + req.programme_id);

            html += '<div class="prog-request-item">';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Programme</div><div class="prog-request-value">' + escapeHtml(programme) + '</div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">' + label + '</div><div class="prog-request-value">' + escapeHtml(name) + '</div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Status</div><div class="prog-request-value"><span class="prog-status-badge ' + status + '">' + escapeHtml(status) + '</span></div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Action</div><div class="prog-request-value">';
            html += '<button class="prog-btn-request-delete" data-request-id="' + req.id + '" data-request-name="' + escapeAttr(name) + '" onclick="deleteInvestmentRequestClicked(this)">Delete Request</button>';
            html += '  </div></div>';
            html += '</div>';
        });

        html += '</div>';
        container.innerHTML = html;
    }

    function deleteInvestmentRequestClicked(btn) {
        var reqId = btn.getAttribute('data-request-id');
        if (!reqId) return;
        if (!confirm('Delete this sent investment request?')) return;
        btn.disabled = true;

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'delete_investment_request=1&request_id=' + encodeURIComponent(reqId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showProgAlert(data.message || 'Request deleted.', 'Success', 'sent');
            } else {
                btn.disabled = false;
                showProgAlert(data.message || 'Failed to delete request', 'Error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }
    window.deleteInvestmentRequestClicked = deleteInvestmentRequestClicked;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var dm = document.getElementById('progDetailModal');
            if (dm && dm.classList.contains('active')) { closeProgDetailModal(); return; }
            var cm = document.getElementById('progConfirmModal');
            if (cm && cm.classList.contains('active')) { closeProgConfirm(); return; }
            var xm = document.getElementById('progCancelModal');
            if (xm && xm.classList.contains('active')) { closeProgCancel(); return; }
            var am = document.getElementById('progAlertModal');
            if (am && am.classList.contains('active')) { closeProgAlert(); }
        }
    });

    window.onProgSearch           = onProgSearch;
    window.viewProgramme          = viewProgramme;
    window.closeProgDetailModal   = closeProgDetailModal;
    window.openProgConfirm        = openProgConfirm;
    window.closeProgConfirm       = closeProgConfirm;
    window.progConfirmAction      = progConfirmAction;
    window.cancelProgrammeConfirm = cancelProgrammeConfirm;
    window.closeProgCancel        = closeProgCancel;
    window.progCancelAction       = progCancelAction;
    window.closeProgAlert         = closeProgAlert;
</script>
<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-programmes');

        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-trader_app',
            'prog-detail-view-open'
        ];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();
</script>
</body>
</html>