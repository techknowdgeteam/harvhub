<?php

// connect_programme_broker.php — programme-scoped broker connection

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



$u = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");

$u->execute([$email]);

$user = $u->fetch(PDO::FETCH_ASSOC);

if (!$user) { header("Location: index.php?role=developer"); exit; }



$userId             = (int)$user['id'];

$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);

$mainAccountId      = (int)($user['main_account_id'] ?? 0);

$_SESSION['active_sub_account_id'] = $activeSubAccountId;



$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);

if ($activeProgrammeId <= 0) { header("Location: traderapp.php?tab=menu"); exit; }



$p = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");

$p->execute([$activeProgrammeId, $userId]);

$programme = $p->fetch(PDO::FETCH_ASSOC);

if (!$programme) { header("Location: traderapp.php?tab=menu"); exit; }



$fullName      = $user['fullname'] ?? 'User';

$darkMode      = (int)($user['dark_mode'] ?? 0);

$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';



// ============================================================================

// HELPERS

// ============================================================================

if (!function_exists('resolveProgrammeBrokerState')) {

    /**

     * Normalized snapshot of the active programme's broker state.

     */

    function resolveProgrammeBrokerState(PDO $pdo, int $programmeId, int $userId): array {

        $stmt = $pdo->prepare("

            SELECT broker, server, login, broker_login_status,

                   broker_login_attempt_used, broker_total_login_attempt,

                   disconnect_broker_request, demo_account

            FROM programme

            WHERE id = ? AND userid = ?

            LIMIT 1

        ");

        $stmt->execute([$programmeId, $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];



        $broker    = trim((string)($row['broker'] ?? ''));

        $server    = trim((string)($row['server'] ?? ''));

        $login     = trim((string)($row['login']  ?? ''));

        $connected = ($broker !== '' && $server !== '' && $login !== '');



        return [

            'broker'                     => $broker,

            'server'                     => $server,

            'login'                      => $login,

            'broker_login_status'        => trim((string)($row['broker_login_status'] ?? '')),

            'broker_login_attempt_used'  => $row['broker_login_attempt_used'] ?? null,

            'broker_total_login_attempt' => $row['broker_total_login_attempt'] ?? null,

            'broker_connected'           => $connected,

            'disconnect_broker_request'  => (int)($row['disconnect_broker_request'] ?? 0),

            'demo_account'               => (int)($row['demo_account'] ?? 0),

        ];

    }

}



if (!function_exists('emptyProgrammeBrokerLoginMeta')) {

    /**

     * Clear broker_login_status + broker_login_attempt_used for a programme

     * whenever the user re-saves broker details so the probe loop starts

     * fresh on the next tick.

     */

    function emptyProgrammeBrokerLoginMeta(PDO $pdo, int $programmeId, int $userId): void {

        $stmt = $pdo->prepare("

            UPDATE programme

            SET broker_login_status = NULL,

                broker_login_attempt_used = NULL

            WHERE id = ? AND userid = ?

        ");

        $stmt->execute([$programmeId, $userId]);

    }

}



if (!function_exists('resolveProgrammeDisplayNameLocal')) {

    function resolveProgrammeDisplayNameLocal(array $p) {

        $n = trim((string)($p['program_name'] ?? ''));

        return $n !== '' ? $n : 'Programme #' . (int)($p['id'] ?? 0);

    }

}



// ============================================================================

// ALLOWED BROKERS

// ============================================================================

$allowed_brokers = [];

$broker_links    = [];



try {

    $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");

    $config = $stmt->fetch(PDO::FETCH_ASSOC);



    if ($config) {

        $raw_brokers = explode(',', $config['brokers'] ?? '');

        $raw_links   = explode(',', $config['brokers_link'] ?? '');



        $cleaned_links = [];

        foreach ($raw_links as $link) {

            $link = trim($link);

            if ($link === '') continue;

            if (strpos($link, ':') !== false) $link = trim(substr($link, strrpos($link, ':') + 1));

            if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]***\\.**[a-zA-Z]{2,})/', $link, $m)) $link = $m[1];

            $link = strtolower(trim($link));

            if ($link !== '') $cleaned_links[] = $link;

        }



        foreach ($raw_brokers as $i => $entry) {

            $entry = trim($entry);

            if ($entry === '') continue;

            $broker_name = (strpos($entry, ':') !== false)

                ? trim(substr($entry, strrpos($entry, ':') + 1))

                : $entry;

            $broker_name = trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name));

            if ($broker_name === '') continue;

            $formatted = ucfirst($broker_name);

            $link = $cleaned_links[$i] ?? '';

            if (!in_array($formatted, $allowed_brokers, true)) {

                $allowed_brokers[] = $formatted;

                $broker_links[$formatted] = $link;

            }

        }

        sort($allowed_brokers);

    }

} catch (PDOException $e) {}



// ============================================================================

// CURRENT STATE

// ============================================================================

$state = resolveProgrammeBrokerState($pdo, $activeProgrammeId, $userId);



$current_broker             = $state['broker'];

$current_server             = $state['server'];

$current_login              = $state['login'];

$broker_connected           = $state['broker_connected'];

$disconnect_broker_request  = $state['disconnect_broker_request'];

$broker_locked              = ($disconnect_broker_request === 1);



$has_links = false;

foreach ($allowed_brokers as $b) {

    if (!empty($broker_links[$b])) { $has_links = true; break; }

}



// ============================================================================

// AJAX: LIVE STATE POLL

// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_programme_broker_state'])) {

    header('Content-Type: application/json; charset=utf-8');



    $s = resolveProgrammeBrokerState($pdo, $activeProgrammeId, $userId);



    echo json_encode([

        'success'                     => true,

        'broker'                      => $s['broker'],

        'server'                      => $s['server'],

        'login'                       => $s['login'],

        'broker_connected'            => (bool)$s['broker_connected'],

        'broker_login_status'         => $s['broker_login_status'],

        'broker_login_attempt_used'   => $s['broker_login_attempt_used'],

        'broker_total_login_attempt'  => $s['broker_total_login_attempt'],

        'disconnect_broker_request'   => $s['disconnect_broker_request'],

        'demo_account'                => $s['demo_account'],

        'broker_locked'               => ($s['disconnect_broker_request'] === 1),

    ]);

    exit;

}



// ============================================================================

// AJAX: CONNECT / UPDATE PROGRAMME BROKER

// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_broker_ajax'])) {

    header('Content-Type: application/json; charset=utf-8');



    // Block if a disconnect is pending — the previous terminal has not

    // yet been deleted from the VPS.

    $preState = resolveProgrammeBrokerState($pdo, $activeProgrammeId, $userId);

    if ((int)$preState['disconnect_broker_request'] === 1) {

        echo json_encode([

            'success' => false,

            'errors'  => [

                'You cannot add a broker yet. Please wait while we finish deleting the previous broker terminal from the VPS. You will be notified once that is complete.'

            ]

        ]);

        exit;

    }



    $broker          = trim($_POST['broker'] ?? '');

    $server          = trim($_POST['server'] ?? '');

    $login           = trim($_POST['login']  ?? '');

    $broker_password = $_POST['broker_password'] ?? '';

    $demo_account    = isset($_POST['demo_account']) ? (int)$_POST['demo_account'] : 0;

    $demo_account    = ($demo_account === 1) ? 1 : 0;



    $errors = [];

    if ($broker === '')          $errors[] = 'Broker is required';

    if ($server === '')          $errors[] = 'Server is required';

    if ($login  === '')          $errors[] = 'Login is required';

    if ($broker_password === '') $errors[] = 'Password is required';

    if ($errors) { echo json_encode(['success' => false, 'errors' => $errors]); exit; }



    if (!in_array($broker, $allowed_brokers, true)) {

        echo json_encode(['success' => false, 'errors' => ['Invalid broker selected.']]);

        exit;

    }



    // Prevent duplicate broker login across programmes owned by same user

    try {

        $dup = $pdo->prepare("

            SELECT id FROM programme

            WHERE userid = ? AND id <> ? AND login = ?

            LIMIT 1

        ");

        $dup->execute([$userId, $activeProgrammeId, $login]);

        if ($dup->fetch(PDO::FETCH_ASSOC)) {

            echo json_encode([

                'success' => false,

                'errors'  => ['This broker login is already connected to another one of your programmes.']

            ]);

            exit;

        }

    } catch (Throwable $e) {}



    $isUpdate = $broker_connected;



    try {

        $upd = $pdo->prepare("

            UPDATE programme

            SET broker = ?, server = ?, login = ?, broker_password = ?,

                demo_account = ?,

                disconnect_broker_request = 0

            WHERE id = ? AND userid = ?

        ");

        $upd->execute([

            $broker, $server, $login, $broker_password,

            $demo_account,

            $activeProgrammeId, $userId

        ]);



        emptyProgrammeBrokerLoginMeta($pdo, $activeProgrammeId, $userId);



        if (!$isUpdate) {

            // ============================================

            // FIRST-TIME CONNECTION

            // ============================================

            recordProgrammeNotification($pdo, $activeProgrammeId, $email, [

                'notification_key' => 'prog-broker-connected-' . $activeProgrammeId,

                'title'            => 'Broker Connected',

                'message'          => 'Your programme broker (' . $broker . ' - ' . $login . ') has been connected successfully.',

                'type'             => 'success',

                'section'          => 'Broker',

                'action_tab'       => 'connect_trader_broker',

                'force'            => false,

                'recipient_name'   => $fullName,

                'send_email'       => true,

            ]);

        } else {

            // ============================================

            // UPDATE — always notify on the update form

            // ============================================

            $tsKey = 'prog-broker-updated-' . $activeProgrammeId . '-' . date('YmdHis');



            recordProgrammeNotification($pdo, $activeProgrammeId, $email, [

                'notification_key' => $tsKey,

                'title'            => 'Broker Details Updated',

                'message'          => 'Your programme broker details have been updated to ' . $broker . ' - ' . $login . '.',

                'type'             => 'info',

                'section'          => 'Broker',

                'action_tab'       => 'connect_trader_broker',

                'force'            => true,

                'recipient_name'   => $fullName,

                'send_email'       => true,

            ]);

        }



        $fresh = resolveProgrammeBrokerState($pdo, $activeProgrammeId, $userId);



        echo json_encode([

            'success'                    => true,

            'message'                    => $isUpdate ? 'Broker details updated successfully!' : 'Broker connected successfully!',

            'is_update'                  => $isUpdate,

            'broker_connected'           => (bool)$fresh['broker_connected'],

            'broker'                     => $fresh['broker'],

            'server'                     => $fresh['server'],

            'login'                      => $fresh['login'],

            'demo_account'               => $fresh['demo_account'],

            'disconnect_broker_request'  => $fresh['disconnect_broker_request'],

            'broker_locked'              => ($fresh['disconnect_broker_request'] === 1),

        ]);

    } catch (Throwable $e) {

        echo json_encode(['success' => false, 'errors' => ['Failed to save broker details.']]);

    }

    exit;

}

?>

\<!DOCTYPE html>

\<html lang="en">

\<head>

\<meta charset="UTF-8">

\<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,\<svg xmlns='http\://www\.w3.org/2000/svg' viewBox='0 0 100 100'>\<rect width='100' height='100' rx='20' fill='%232ecc71'/>\<text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H\</text>\</svg>">

\<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">

\<link rel="stylesheet" href="https\://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

\<link rel="stylesheet" href="https\://unicons.iconscout.com/release/v4.0.8/css/line.css">

\<title>Connect Broker - HarvHub\</title>

<?php include 'style.php'; ?>

<?php include 'connect_investor_broker_style.php'; ?>

\<style>

    .broker-confirm-overlay {

        position: fixed; inset: 0;

        background: rgba(0,0,0,0.62);

        display: none; align-items: center; justify-content: center;

        padding: 20px; z-index: 99999;

    }

    .broker-confirm-overlay.active { display: flex; }

    .broker-confirm-box {

        width: min(92vw, 460px); max-width: 460px;

        background: var(--card-bg, #fff);

        color: var(--text, #1a2332);

        border-radius: 16px; padding: 22px;

        box-shadow: 0 20px 60px rgba(0,0,0,0.35);

        text-align: center;

    }

    body.dark-mode .broker-confirm-box { background: #0f1614; color: #e6edf3; }

    .broker-confirm-box h3 {

        margin: 0 0 12px; font-size: 1.05rem;

        color: var(--accent, #2ecc8f);

    }

    .broker-confirm-box p {

        margin: 0 0 20px; font-size: 0.92rem;

        line-height: 1.6; opacity: 0.9; text-align: left;

    }

    .broker-confirm-actions { display: flex; flex-direction: column; gap: 10px; }

    .broker-confirm-actions button {

        width: 100%; padding: 13px; border-radius: 10px;

        border: none; font-weight: 700; font-size: 0.95rem; cursor: pointer;

    }

    .broker-confirm-actions .btn-yes { background: var(--accent, #2ecc8f); color: #fff; }

    .broker-confirm-actions .btn-cancel {

        background: rgba(127,127,127,0.15);

        color: var(--text, #1a2332);

    }

    body.dark-mode .broker-confirm-actions .btn-cancel { color: #e6edf3; }



    /* Live state pill */

    .broker-live-pill {

        display: inline-flex; align-items: center; gap: 6px;

        padding: 4px 10px; border-radius: 999px;

        font-size: 0.72rem; font-weight: 700;

        letter-spacing: 0.3px; text-transform: uppercase;

    }

    .broker-live-pill .dot {

        width: 7px; height: 7px; border-radius: 50%;

        background: currentColor; box-shadow: 0 0 0 3px rgba(0,0,0,0.05);

    }

    .broker-live-pill.state-connected { background: rgba(46,204,143,0.14); color: #2ecc8f; }

    .broker-live-pill.state-pending   { background: rgba(245,166,35,0.16); color: #f5a623; }

    .broker-live-pill.state-error     { background: rgba(239,68,68,0.14);  color: #ef4444; }

    .broker-live-pill.state-idle      { background: rgba(127,127,127,0.14); color: var(--text-muted, #8a9aa8); }



    /* Lock card */

    .broker-lock-card {

        background: rgba(245,166,35,0.10);

        border: 1px solid rgba(245,166,35,0.35);

        border-radius: 14px; padding: 18px 16px;

        margin-bottom: 18px; color: var(--text, #1a2332);

        line-height: 1.6; font-size: 0.92rem;

    }

    body.dark-mode .broker-lock-card {

        background: rgba(245,166,35,0.10);

        border-color: rgba(245,166,35,0.35);

        color: #e6edf3;

    }

    .broker-lock-card .lock-title {

        font-weight: 700; margin-bottom: 6px;

        color: #f5a623; display: flex; align-items: center; gap: 8px;

    }

    .broker-lock-card .lock-title i { font-size: 1.05rem; }



    /* Demo / real account toggle */

    .broker-account-type-row {

        display: flex; gap: 10px; align-items: center;

        margin-top: 4px; flex-wrap: wrap;

    }

    .broker-account-type-row label {

        display: inline-flex; align-items: center; gap: 6px;

        cursor: pointer; padding: 8px 14px;

        border-radius: 10px;

        background: rgba(127,127,127,0.10);

        font-size: 0.9rem;

        user-select: none;

    }

    .broker-account-type-row label input { accent-color: #2ecc8f; }

    .broker-account-type-row label.active {

        background: rgba(46,204,143,0.16);

        color: #2ecc8f; font-weight: 700;

    }

\</style>

\</head>

\<body class="\<?= htmlspecialchars($darkModeClass) ?> broker-page-body">



\<div class="broker-page-wrapper">

    \<header class="broker-sticky-top">

        \<div class="broker-topbar">

            \<a href="#" class="broker-topbar-back"

               onclick="event.preventDefault(); harvhubGoToTab('menu'); return false;"

               aria-label="Back">

                \<i class="fa-solid fa-arrow-left">\</i>

            \</a>

            \<h1 class="broker-topbar-title">Connect Broker\</h1>

        \</div>

    \</header>



    \<div class="broker-scroll-area" id="brokerScrollArea">



        \<div class="broker-user-info">

            \<span class="broker-user-info-item">

                \<span class="broker-user-label">Programme\</span>

                \<span class="broker-user-value">\<?= htmlspecialchars($programme['program_name'] ?: 'Unnamed Programme') ?>\</span>

            \</span>

            \<span class="broker-user-info-item">

                \<span class="broker-user-label">Status\</span>

                \<span id="brokerLivePill" class="broker-live-pill state-idle">

                    \<span class="dot">\</span>

                    \<span id="brokerLivePillText">Checking…\</span>

                \</span>

            \</span>

        \</div>



        \<div id="brokerError" class="broker-error-message" style="display: none;">\</div>

        \<div id="brokerSuccess" class="broker-success-message" style="display: none;">\</div>



        \<!-- Lock card: shown while a disconnect request is pending -->

        \<div id="brokerLockCard" class="broker-lock-card" style="\<?= $broker_locked ? '' : 'display:none;' ?>">

            \<div class="lock-title">

                \<i class="fa-solid fa-hourglass-half">\</i>

                \<span>Broker Deletion in Progress\</span>

            \</div>

            \<div>

                You cannot add a broker yet. Please wait while we finish deleting

                the previous broker terminal from the VPS. You will be notified

                once that is complete, and you will then be able to add new

                broker details.

            \</div>

        \</div>



        \<!-- Update form: shown when broker is already connected -->

        \<div id="updateFormWrapper" style="\<?= ($broker_connected && !$broker_locked) ? '' : 'display:none;' ?>">

            \<div class="broker-section-title">Update Your Broker Account\</div>



            \<form id="updateBrokerForm" novalidate>

                \<div class="broker-form-group">

                    \<label for="update_broker_select">Select Broker\</label>

                    \<select name="broker" id="update_broker_select" required>

                        \<option value="">-- Select Broker --\</option>

                        <?php foreach ($allowed_brokers as $b): ?>

                            \<option value="\<?= htmlspecialchars($b) ?>" \<?= ($current_broker === $b) ? 'selected' : '' ?>>

                                \<?= htmlspecialchars($b) ?>

                            \</option>

                        <?php endforeach; ?>

                    \</select>

                \</div>



                \<div class="broker-form-group">

                    \<label for="update_server_input">Server\</label>

                    \<input type="text" name="server" id="update_server_input" placeholder="broker server" value="\<?= htmlspecialchars($current_server) ?>" required>

                \</div>



                \<div class="broker-form-group">

                    \<label for="update_login_input">Login Number\</label>

                    \<input type="text" name="login" id="update_login_input" placeholder="Your MT5 login number" value="\<?= htmlspecialchars($current_login) ?>" required>

                \</div>



                \<div class="broker-form-group">

                    \<label for="update_password_input">MT5 Password\</label>

                    \<div class="broker-password-wrapper">

                        \<input type="password" name="broker_password" id="update_password_input" placeholder="Re-enter your MT5 password" required>

                        \<button type="button" class="broker-password-toggle" onclick="togglePasswordField('update_password_input')">Show\</button>

                    \</div>

                \</div>



                \<div class="broker-form-group">

                    \<label>Account Type\</label>

                    \<div class="broker-account-type-row" id="update_account_type_row">

                        \<label class="\<?= ($state['demo_account'] === 0) ? 'active' : '' ?>">

                            \<input type="radio" name="demo_account_update" value="0" \<?= ($state['demo_account'] === 0) ? 'checked' : '' ?>>

                            Real Account

                        \</label>

                        \<label class="\<?= ($state['demo_account'] === 1) ? 'active' : '' ?>">

                            \<input type="radio" name="demo_account_update" value="1" \<?= ($state['demo_account'] === 1) ? 'checked' : '' ?>>

                            Demo Account

                        \</label>

                    \</div>

                \</div>



                \<button type="submit" id="updateBrokerBtn" class="broker-btn-submit">

                    Update Broker Details

                \</button>

            \</form>

        \</div>



        \<!-- Connect (fresh) form: shown when no broker is connected -->

        \<div id="connectFormWrapper" style="\<?= ($broker_connected || $broker_locked) ? 'display:none;' : '' ?>">

            \<div id="availableBrokersSection">

                \<div class="broker-section-title">Available Brokers\</div>



                \<div id="brokerSelectionGrid" class="broker-selection-grid">

                    <?php foreach ($allowed_brokers as $b):

                        $link = $broker_links[$b] ?? '';

                    ?>

                        \<div class="broker-selection-item">

                            \<span class="broker-name">\<?= htmlspecialchars($b) ?>\</span>

                            \<div class="broker-action">

                                <?php if ($link !== ''): ?>

                                    \<a href="https\://\<?= htmlspecialchars($link) ?>" target="_blank" class="broker-link-btn">

                                        Go to \<?= htmlspecialchars($b) ?>

                                    \</a>

                                <?php else: ?>

                                    \<span class="broker-link-btn no-link">No Link\</span>

                                <?php endif; ?>

                            \</div>

                        \</div>

                    <?php endforeach; ?>

                \</div>



                \<div id="connectButtonWrapper" class="broker-connect-wrapper">

                    \<button type="button" class="broker-btn-secondary" onclick="showConnectForm()">

                        I Already Have an Account

                    \</button>

                \</div>

            \</div>



            \<div id="connectForm" class="\<?= ($has_links && !$broker_connected) ? 'hidden' : '' ?>">

                \<hr class="broker-divider">



                \<form id="connectBrokerForm" novalidate>

                    \<div class="broker-form-group">

                        \<label for="connect_broker_select">Select Broker\</label>

                        \<select name="broker" id="connect_broker_select" required>

                            \<option value="">-- Select Broker --\</option>

                            <?php foreach ($allowed_brokers as $b): ?>

                                \<option value="\<?= htmlspecialchars($b) ?>">\<?= htmlspecialchars($b) ?>\</option>

                            <?php endforeach; ?>

                        \</select>

                    \</div>



                    \<div class="broker-form-group">

                        \<label for="connect_server_input">Server\</label>

                        \<input type="text" name="server" id="connect_server_input" placeholder="e.g. Exness-MT5" required>

                    \</div>



                    \<div class="broker-form-group">

                        \<label for="connect_login_input">Login Number\</label>

                        \<input type="text" name="login" id="connect_login_input" placeholder="Your MT5 login number" required>

                    \</div>



                    \<div class="broker-form-group">

                        \<label for="connect_password_input">MT5 Password\</label>

                        \<div class="broker-password-wrapper">

                            \<input type="password" name="broker_password" id="connect_password_input" placeholder="Your MT5 password" required>

                            \<button type="button" class="broker-password-toggle" onclick="togglePasswordField('connect_password_input')">Show\</button>

                        \</div>

                    \</div>



                    \<div class="broker-form-group">

                        \<label>Account Type\</label>

                        \<div class="broker-account-type-row" id="connect_account_type_row">

                            \<label class="active">

                                \<input type="radio" name="demo_account_connect" value="0" checked>

                                Real Account

                            \</label>

                            \<label>

                                \<input type="radio" name="demo_account_connect" value="1">

                                Demo Account

                            \</label>

                        \</div>

                    \</div>



                    \<button type="submit" id="connectBrokerBtn" class="broker-btn-submit">

                        Connect Broker

                    \</button>

                \</form>

            \</div>

        \</div>



        \<div class="broker-info-note">

            \<strong>Secure:\</strong> Your broker credentials are encrypted and stored securely.

            They are only used for automated trading execution.

        \</div>

    \</div>

\</div>



\<div id="brokerConfirmModal" class="broker-confirm-overlay" aria-hidden="true">

    \<div class="broker-confirm-box" role="dialog" aria-modal="true" aria-labelledby="brokerConfirmTitle">

        \<h3 id="brokerConfirmTitle">Confirm Broker Connection\</h3>

        \<p>

            Before connecting, please make sure the full name on your broker account matches the name registered on your HarvHub profile. If the credentials do not match, this programme may be suspended. Do you want to proceed?

        \</p>

        \<div class="broker-confirm-actions">

            \<button type="button" class="btn-yes" id="brokerConfirmYes">Yes, Proceed\</button>

            \<button type="button" class="btn-cancel" id="brokerConfirmCancel">Cancel\</button>

        \</div>

    \</div>

\</div>



\<script>

    function harvhubGoToTab(tab) {

        try {

            if (window\.parent && window\.parent !== window) {

                window\.parent.postMessage({ type: 'switchTab', tab: tab }, '*');

                return;

            }

        } catch (e) {}

        window\.location.href = 'traderapp.php?tab=' + encodeURIComponent(tab);

    }

    window\.harvhubGoToTab = harvhubGoToTab;



    (function () {

        document.body.classList.add('page-connect_trader_broker');



        window\.addEventListener('message', function (e) {

            if (!e.data || typeof e.data !== 'object') return;

            if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);

        });



        var WATCHED = ['page-connect_trader_broker','page-disconnect_trader_broker','page-trader_menu'];

        function broadcast() {

            var add = WATCHED.filter(function (c){ return document.body.classList.contains(c); });

            try {

                window\.parent.postMessage({

                    type: 'bodyClass',

                    add: add,

                    remove: WATCHED.filter(function(c){ return add.indexOf(c) === -1; })

                }, '*');

            } catch (e) {}

        }

        new MutationObserver(broadcast).observe(document.body, { attributes:true, attributeFilter:['class'] });

        broadcast();

        try { window\.parent.postMessage({ type:'requestTheme' }, '*'); } catch (e) {}

    })();



    function togglePasswordField(inputId) {

        var input = document.getElementById(inputId);

        if (!input) return;

        var toggle = input.parentElement.querySelector('.broker-password-toggle');

        if (input.type === 'password') {

            input.type = 'text';

            if (toggle) toggle.textContent = 'Hide';

        } else {

            input.type = 'password';

            if (toggle) toggle.textContent = 'Show';

        }

    }

    window\.togglePasswordField = togglePasswordField;



    function showConnectForm() {

        var section = document.getElementById('availableBrokersSection');

        if (section) section.classList.add('hidden');

        var grid = document.getElementById('brokerSelectionGrid');

        if (grid) grid.classList.add('hidden');

        var wrapper = document.getElementById('connectButtonWrapper');

        if (wrapper) wrapper.classList.add('hidden');

        var form = document.getElementById('connectForm');

        if (form) {

            form.classList.remove('hidden');

            setTimeout(function() {

                form.scrollIntoView({ behavior: 'smooth', block: 'start' });

            }, 100);

        }

    }

    window\.showConnectForm = showConnectForm;



    function showBrokerError(msg) {

        var box = document.getElementById('brokerError');

        if (!box) return;

        box.textContent = msg || 'Something went wrong.';

        box.style.display = 'block';

        box.scrollIntoView({ behavior: 'smooth', block: 'center' });

    }

    function hideBrokerError() {

        var box = document.getElementById('brokerError');

        if (box) { box.style.display = 'none'; box.textContent = ''; }

    }

    function showBrokerSuccess(msg) {

        var box = document.getElementById('brokerSuccess');

        if (!box) return;

        box.textContent = msg || 'Saved.';

        box.style.display = 'block';

        box.scrollIntoView({ behavior: 'smooth', block: 'center' });

    }

    function hideBrokerSuccess() {

        var box = document.getElementById('brokerSuccess');

        if (box) { box.style.display = 'none'; box.textContent = ''; }

    }



    // ---------- Account type toggle ----------

    function bindAccountTypeRow(rowId) {

        var row = document.getElementById(rowId);

        if (!row || row\.dataset.bound === '1') return;

        row\.dataset.bound = '1';



        row\.querySelectorAll('input[type="radio"]').forEach(function (radio) {

            radio.addEventListener('change', function () {

                row\.querySelectorAll('label').forEach(function (lbl) {

                    var inp = lbl.querySelector('input[type="radio"]');

                    if (inp && inp.checked) lbl.classList.add('active');

                    else lbl.classList.remove('active');

                });

            });

        });

    }



    // ---------- Confirmation modal ----------

    var _pendingSubmit = null;



    function openBrokerConfirm(onYes) {

        _pendingSubmit = onYes || null;

        var modal = document.getElementById('brokerConfirmModal');

        if (modal) {

            modal.classList.add('active');

            modal.setAttribute('aria-hidden', 'false');

        }

    }

    function closeBrokerConfirm() {

        _pendingSubmit = null;

        var modal = document.getElementById('brokerConfirmModal');

        if (modal) {

            modal.classList.remove('active');

            modal.setAttribute('aria-hidden', 'true');

        }

    }



    document.addEventListener('DOMContentLoaded', function () {

        var yesBtn = document.getElementById('brokerConfirmYes');

        var cancelBtn = document.getElementById('brokerConfirmCancel');

        var modal = document.getElementById('brokerConfirmModal');



        if (yesBtn) {

            yesBtn.addEventListener('click', function () {

                var cb = _pendingSubmit;

                closeBrokerConfirm();

                if (typeof cb === 'function') cb();

            });

        }

        if (cancelBtn) cancelBtn.addEventListener('click', closeBrokerConfirm);

        if (modal) {

            modal.addEventListener('click', function (e) {

                if (e.target === modal) closeBrokerConfirm();

            });

        }

        document.addEventListener('keydown', function (e) {

            if (e.key === 'Escape' && modal && modal.classList.contains('active')) {

                closeBrokerConfirm();

            }

        });

    });



    // ---------- Live state polling ----------

    var _livePoll = null;

    var _livePollInterval = 5000;



    function applyLiveState(state) {

        if (!state) return;



        var connected = !!state.broker_connected;

        var locked    = !!state.broker_locked;

        var status    = (state.broker_login_status || '').toString().toLowerCase();



        var updateWrap  = document.getElementById('updateFormWrapper');

        var connectWrap = document.getElementById('connectFormWrapper');

        var lockCard    = document.getElementById('brokerLockCard');



        if (lockCard) lockCard.style.display = locked ? '' : 'none';



        if (locked) {

            if (updateWrap)  updateWrap.style.display  = 'none';

            if (connectWrap) connectWrap.style.display = 'none';

        } else {

            if (updateWrap && connectWrap) {

                var currentlyShowingUpdate = updateWrap.style.display !== 'none';

                if (connected && !currentlyShowingUpdate) {

                    updateWrap.style.display = '';

                    connectWrap.style.display = 'none';



                    var ub = document.getElementById('update_broker_select');

                    var us = document.getElementById('update_server_input');

                    var ul = document.getElementById('update_login_input');

                    if (ub && state.broker) ub.value = state.broker;

                    if (us) us.value = state.server || '';

                    if (ul) ul.value = state.login || '';

                } else if (!connected && currentlyShowingUpdate) {

                    updateWrap.style.display = 'none';

                    connectWrap.style.display = '';

                } else if (connected) {

                    var ub2 = document.getElementById('update_broker_select');

                    var us2 = document.getElementById('update_server_input');

                    var ul2 = document.getElementById('update_login_input');

                    if (ub2 && state.broker && !ub2.matches(':focus')) ub2.value = state.broker;

                    if (us2 && !us2.matches(':focus')) us2.value = state.server || '';

                    if (ul2 && !ul2.matches(':focus')) ul2.value = state.login || '';

                }

            }

        }



        // Sync account type radio buttons with live demo_account value

        var demoVal = (parseInt(state.demo_account, 10) === 1) ? '1' : '0';

        ['update_account_type_row', 'connect_account_type_row'].forEach(function (rowId) {

            var row = document.getElementById(rowId);

            if (!row) return;

            row\.querySelectorAll('input[type="radio"]').forEach(function (radio) {

                if (radio.value === demoVal) radio.checked = true;

            });

            row\.querySelectorAll('label').forEach(function (lbl) {

                var inp = lbl.querySelector('input[type="radio"]');

                if (inp && inp.checked) lbl.classList.add('active');

                else lbl.classList.remove('active');

            });

        });



        var pill = document.getElementById('brokerLivePill');

        var pillText = document.getElementById('brokerLivePillText');

        if (pill && pillText) {

            pill.classList.remove('state-connected', 'state-pending', 'state-error', 'state-idle');



            if (locked) {

                pill.classList.add('state-pending');

                pillText.textContent = 'Deletion Pending';

            } else if (status === 'invalid_credentials') {

                pill.classList.add('state-error');

                pillText.textContent = 'Credentials Invalid';

            } else if (status === 'fullname_mismatch') {

                pill.classList.add('state-error');

                pillText.textContent = 'Name Mismatch';

            } else if (status === 'invalid_account') {

                pill.classList.add('state-error');

                pillText.textContent = 'Demo Not Allowed';

            } else if (status === 'valid_credentials') {

                pill.classList.add('state-connected');

                pillText.textContent = 'Live';

            } else if (connected) {

                pill.classList.add('state-pending');

                pillText.textContent = 'Awaiting Confirmation';

            } else {

                pill.classList.add('state-idle');

                pillText.textContent = 'Not Connected';

            }

        }

    }



    function fetchLiveState() {

        fetch('connect_programme_broker.php', {

            method: 'POST',

            headers: {

                'Content-Type': 'application/x-www-form-urlencoded',

                'X-Requested-With': 'XMLHttpRequest'

            },

            credentials: 'same-origin',

            body: 'get_programme_broker_state=1'

        })

        .then(function (r) { return r.json(); })

        .then(function (data) {

            if (data && data.success) applyLiveState(data);

        })

        .catch(function () {});

    }



    function startLivePoll() {

        if (_livePoll) return;

        fetchLiveState();

        _livePoll = setInterval(fetchLiveState, _livePollInterval);

    }

    function stopLivePoll() {

        if (_livePoll) { clearInterval(_livePoll); _livePoll = null; }

    }



    document.addEventListener('visibilitychange', function () {

        if (document.hidden) stopLivePoll();

        else startLivePoll();

    });

    window\.addEventListener('focus', function () { fetchLiveState(); });

    window\.addEventListener('beforeunload', function () { stopLivePoll(); });



    // ---------- Form bindings ----------

    function bindBrokerForm(formId, buttonId, accountTypeRowId) {

        var form = document.getElementById(formId);

        if (!form) return;

        if (form.dataset.bound === '1') return;

        form.dataset.bound = '1';



        form.addEventListener('submit', function (ev) {

            ev.preventDefault();

            hideBrokerError();

            hideBrokerSuccess();



            var brokerEl = form.querySelector('select[name="broker"]');

            var serverEl = form.querySelector('input[name="server"]');

            var loginEl  = form.querySelector('input[name="login"]');

            var passEl   = form.querySelector('input[name="broker_password"]');

            var btn      = document.getElementById(buttonId);



            var broker = brokerEl ? brokerEl.value.trim() : '';

            var server = serverEl ? serverEl.value.trim() : '';

            var login  = loginEl  ? loginEl.value.trim()  : '';

            var pass   = passEl   ? passEl.value          : '';



            var demo_account = 0;

            if (accountTypeRowId) {

                var row = document.getElementById(accountTypeRowId);

                if (row) {

                    var checked = row\.querySelector('input[type="radio"]:checked');

                    if (checked) demo_account = parseInt(checked.value, 10) || 0;

                }

            }



            var errors = [];

            if (!broker) errors.push('Please select a broker.');

            if (!server) errors.push('Server is required.');

            if (!login)  errors.push('Login is required.');

            if (!pass)   errors.push('Password is required.');



            if (errors.length) {

                showBrokerError(errors.join(' '));

                return;

            }



            openBrokerConfirm(function () {

                doSaveBroker(broker, server, login, pass, demo_account, passEl, btn);

            });

        });

    }



    function doSaveBroker(broker, server, login, pass, demo_account, passEl, btn) {

        if (btn) {

            btn.disabled = true;

            btn.dataset.originalText = btn.textContent;

            btn.textContent = 'Saving...';

        }



        var body = 'connect_broker_ajax=1'

                 + '&broker='          + encodeURIComponent(broker)

                 + '&server='          + encodeURIComponent(server)

                 + '&login='           + encodeURIComponent(login)

                 + '&broker_password=' + encodeURIComponent(pass)

                 + '&demo_account='    + encodeURIComponent(demo_account);



        fetch('connect_programme_broker.php', {

            method: 'POST',

            headers: {

                'Content-Type': 'application/x-www-form-urlencoded',

                'X-Requested-With': 'XMLHttpRequest'

            },

            credentials: 'same-origin',

            body: body

        })

        .then(function (r) { return r.text(); })

        .then(function (text) {

            var data;

            try { data = JSON.parse(text); }

            catch (e) {

                console.error('Bad JSON from server:', text);

                if (btn) {

                    btn.disabled = false;

                    btn.textContent = btn.dataset.originalText || 'Save';

                }

                showBrokerError('Server returned an unexpected response. Please try again.');

                return;

            }



            if (data && data.success) {

                if (btn) {

                    btn.disabled = true;

                    btn.textContent = 'Saved';

                }

                showBrokerSuccess(data.message || 'Broker connected successfully.');



                if (passEl) passEl.value = '';



                fetchLiveState();



                setTimeout(function () {

                    try {

                        if (window\.parent && window\.parent !== window) {

                            window\.parent.postMessage({ type: 'reloadWithSpinner' }, '*');

                            return;

                        }

                    } catch (e) {}

                    window\.location.href = 'traderapp.php?tab=signals';

                }, 1400);

            } else {

                if (btn) {

                    btn.disabled = false;

                    btn.textContent = btn.dataset.originalText || 'Save';

                }

                var errs = (data && data.errors) ? data.errors : ['Failed to save broker details.'];

                showBrokerError(errs.join(' '));

            }

        })

        .catch(function (err) {

            console.error('Fetch error:', err);

            if (btn) {

                btn.disabled = false;

                btn.textContent = btn.dataset.originalText || 'Save';

            }

            showBrokerError('Network error. Please try again.');

        });

    }



    function init() {

        bindBrokerForm('connectBrokerForm', 'connectBrokerBtn', 'connect_account_type_row');

        bindBrokerForm('updateBrokerForm',  'updateBrokerBtn',  'update_account_type_row');



        bindAccountTypeRow('connect_account_type_row');

        bindAccountTypeRow('update_account_type_row');



        startLivePoll();

    }



    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', init);

    } else {

        init();

    }

\</script>



\</body>

\</html>