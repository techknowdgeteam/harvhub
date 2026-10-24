<?php

// connect_investor_broker.php — per sub-account

session_start();



/* Centralized database connection: mysqlconnection.php owns all credentials. */
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



if (!isset($_SESSION['user_email'])) {

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



if (!$user) { header("Location: index.php"); exit; }



$userId             = (int)$user['id'];

$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);

$mainAccountId      = (int)($user['main_account_id'] ?? 0);

$_SESSION['active_sub_account_id'] = $activeSubAccountId;



// ==================== HELPERS ====================

if (!function_exists('resolveBrokerState')) {

    /**

     * Returns a normalized snapshot of the active sub-account's broker

     * state, including the disconnect request flag.

     */

    function resolveBrokerState($pdo, $subAccountId, $email) {

        $stmt = $pdo->prepare("

            SELECT broker, server, login, broker_login_status,

                   broker_login_attempt_used, broker_total_login_attempt,

                   disconnect_broker_request

            FROM harvhub

            WHERE sub_account_id = ? AND LOWER(email) = ?

            LIMIT 1

        ");

        $stmt->execute([$subAccountId, $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];



        $broker   = trim((string)($row['broker']   ?? ''));

        $server   = trim((string)($row['server']   ?? ''));

        $login    = trim((string)($row['login']    ?? ''));

        $connected = ($broker !== '' && $server !== '' && $login !== '');



        $disconnectRequest = (int)($row['disconnect_broker_request'] ?? 0);



        return [

            'broker'                       => $broker,

            'server'                       => $server,

            'login'                        => $login,

            'broker_login_status'          => trim((string)($row['broker_login_status'] ?? '')),

            'broker_login_attempt_used'    => $row['broker_login_attempt_used'] ?? null,

            'broker_total_login_attempt'   => $row['broker_total_login_attempt'] ?? null,

            'broker_connected'             => $connected,

            'disconnect_broker_request'    => $disconnectRequest,

        ];

    }

}



if (!function_exists('emptyBrokerLoginMeta')) {

    /**

     * Clear broker_login_status and broker_login_attempt_used for a

     * sub-account. Called whenever the user re-saves broker details so

     * the probe loop starts fresh on the next tick.

     */

    function emptyBrokerLoginMeta($pdo, $userId) {

        $stmt = $pdo->prepare("

            UPDATE harvhub

            SET broker_login_status = NULL,

                broker_login_attempt_used = NULL

            WHERE id = ?

        ");

        $stmt->execute([$userId]);

    }

}



// ==================== FETCH BROKER CONFIGURATION ====================

$allowed_brokers = [];

$broker_links = [];



try {

    $stmt = $pdo->query("SELECT brokers, brokers_link FROM server_account LIMIT 1");

    $config = $stmt->fetch(PDO::FETCH_ASSOC);



    if ($config) {

        $raw_brokers = explode(',', $config['brokers'] ?? '');

        $raw_links   = explode(',', $config['brokers_link'] ?? '');



        $cleaned_links = [];

        foreach ($raw_links as $link) {

            $link = trim($link);

            if (empty($link)) continue;

            if (strpos($link, ':') !== false) {

                $link = trim(substr($link, strrpos($link, ':') + 1));

            }

            if (preg_match('/([a-zA-Z0-9][-a-zA-Z0-9]***\\.**[a-zA-Z]{2,})/', $link, $matches)) {

                $link = $matches[1];

            }

            $link = strtolower(trim($link));

            if (!empty($link)) $cleaned_links[] = $link;

        }



        foreach ($raw_brokers as $index => $entry) {

            $entry = trim($entry);

            if (empty($entry)) continue;



            $broker_name = (strpos($entry, ':') !== false) ? trim(substr($entry, strrpos($entry, ':') + 1)) : $entry;

            $broker_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $broker_name);

            $broker_name = trim($broker_name);

            $broker_name_clean = strtolower($broker_name);



            if (!empty($broker_name)) {

                $formatted_name = ucfirst($broker_name);

                $link = isset($cleaned_links[$index]) ? $cleaned_links[$index] : '';



                if (empty($link)) {

                    foreach ($cleaned_links as $cleaned_link) {

                        if (strpos($cleaned_link, $broker_name_clean) !== false) {

                            $link = $cleaned_link;

                            break;

                        }

                    }

                }



                if (!in_array($formatted_name, $allowed_brokers)) {

                    $allowed_brokers[] = $formatted_name;

                    $broker_links[$formatted_name] = $link;

                }

            }

        }

        sort($allowed_brokers);

    }

} catch (PDOException $e) {

    $error = "Failed to load broker configuration.";

}



$current_broker = $user['broker'] ?? '';

$current_server = $user['server'] ?? '';

$current_login  = $user['login'] ?? '';

$fullname       = $user['fullname'] ?? '';

$application_status = $user['application_status'] ?? '';



$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;

$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';



$broker_connected         = !empty($current_broker) && !empty($current_server) && !empty($current_login);

$disconnect_broker_request = (int)($user['disconnect_broker_request'] ?? 0);



// Page-level UI decision.

// If a disconnect is pending, we lock the connect/update forms entirely.

$broker_locked = ($disconnect_broker_request === 1);



$has_links = false;

foreach ($allowed_brokers as $broker) {

    if (!empty($broker_links[$broker])) {

        $has_links = true;

        break;

    }

}



// ==================== AJAX HANDLER: LIVE STATE POLL ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_broker_state'])) {

    header('Content-Type: application/json; charset=utf-8');



    $state = resolveBrokerState($pdo, $activeSubAccountId, $email);



    echo json_encode([

        'success'                     => true,

        'broker'                      => $state['broker'],

        'server'                      => $state['server'],

        'login'                       => $state['login'],

        'broker_connected'            => (bool)$state['broker_connected'],

        'broker_login_status'         => $state['broker_login_status'],

        'broker_login_attempt_used'   => $state['broker_login_attempt_used'],

        'broker_total_login_attempt'  => $state['broker_total_login_attempt'],

        'disconnect_broker_request'   => $state['disconnect_broker_request'],

        'broker_locked'               => ($state['disconnect_broker_request'] === 1),

    ]);

    exit;

}



// ==================== AJAX HANDLER: CONNECT/UPDATE BROKER ====================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_broker_ajax'])) {

    header('Content-Type: application/json; charset=utf-8');



    // Block if a disconnect is pending — the previous terminal has not

    // yet been deleted from the VPS.

    $preState = resolveBrokerState($pdo, $activeSubAccountId, $email);

    if ((int)$preState['disconnect_broker_request'] === 1) {

        echo json_encode([

            'success' => false,

            'errors'  => [

                'You cannot add a broker yet. Please wait while we finish deleting the previous broker terminal from the VPS. You will be notified once that is complete.'

            ]

        ]);

        exit;

    }



    $broker = trim($_POST['broker'] ?? '');

    $server = trim($_POST['server'] ?? '');

    $login  = trim($_POST['login'] ?? '');

    $broker_password = $_POST['broker_password'] ?? '';



    $errors = [];

    if ($broker === '') $errors[] = 'Broker is required';

    if ($server === '') $errors[] = 'Server is required';

    if ($login === '') $errors[] = 'Login is required';

    if ($broker_password === '') $errors[] = 'Password is required';



    if ($errors) {

        echo json_encode(['success' => false, 'errors' => $errors]);

        exit;

    }



    if (!in_array($broker, $allowed_brokers, true)) {

        echo json_encode(['success' => false, 'errors' => ['Invalid broker selected.']]);

        exit;

    }



    try {

        $dup = $pdo->prepare("

            SELECT id FROM harvhub

            WHERE LOWER(email) = ?

              AND sub_account_id <> ?

              AND login = ?

            LIMIT 1

        ");

        $dup->execute([$email, $activeSubAccountId, $login]);

        if ($dup->fetch(PDO::FETCH_ASSOC)) {

            echo json_encode([

                'success' => false,

                'errors'  => ['This broker login is already connected to another one of your accounts.']

            ]);

            exit;

        }

    } catch (Throwable $e) {}



    $isUpdate = $broker_connected;



    try {

        $u = $pdo->prepare("

            UPDATE harvhub

            SET broker = ?, server = ?, login = ?, broker_password = ?,

                disconnect_broker_request = 0

            WHERE id = ?

        ");

        $u->execute([$broker, $server, $login, $broker_password, $userId]);



        emptyBrokerLoginMeta($pdo, $userId);



        if (!$isUpdate) {

            recordContractNotification($pdo, [

                'user_email'       => $email,

                'sub_account_id'   => $activeSubAccountId,

                'main_account_id'  => $mainAccountId,

                'notification_key' => 'broker-connected-' . $activeSubAccountId,

                'title'            => 'Broker Connected',

                'message'          => 'Your broker account (' . $broker . ' - ' . $login . ') has been connected successfully.',

                'type'             => 'success',

                'section'          => 'Broker',

                'action_tab'       => 'connect_investor_broker',

                'force'            => false

            ]);

        } else {

            recordContractNotification($pdo, [

                'user_email'       => $email,

                'sub_account_id'   => $activeSubAccountId,

                'main_account_id'  => $mainAccountId,

                'notification_key' => 'broker-updated-' . $activeSubAccountId . '-' . date('YmdHis'),

                'title'            => 'Broker Details Updated',

                'message'          => 'Your broker details have been updated to ' . $broker . ' - ' . $login . '.',

                'type'             => 'info',

                'section'          => 'Broker',

                'action_tab'       => 'connect_investor_broker',

                'force'            => true

            ]);

        }



        $state = resolveBrokerState($pdo, $activeSubAccountId, $email);



        echo json_encode([

            'success'                    => true,

            'message'                    => $isUpdate ? 'Broker details updated successfully!' : 'Broker connected successfully!',

            'is_update'                  => $isUpdate,

            'broker_connected'           => (bool)$state['broker_connected'],

            'broker'                     => $state['broker'],

            'server'                     => $state['server'],

            'login'                      => $state['login'],

            'disconnect_broker_request'  => $state['disconnect_broker_request'],

            'broker_locked'              => ($state['disconnect_broker_request'] === 1),

        ]);



    } catch (Throwable $e) {

        echo json_encode(['success' => false, 'errors' => ['Failed to save broker details.']]);

    }

    exit;

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http\://www\.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">

<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">

<link rel="stylesheet" href="https\://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="https\://unicons.iconscout.com/release/v4.0.8/css/line.css">

<title>Connect Broker - HarvHub</title>

<?php include 'style.php'; ?>

<?php include 'connect_investor_broker_style.php'; ?>

<style>

    .broker-confirm-overlay {

        position: fixed; inset: 0;

        background: rgba(0,0,0,0.62);

        display: none;

        align-items: center;

        justify-content: center;

        padding: 20px;

        z-index: 99999;

    }

    .broker-confirm-overlay.active { display: flex; }

    .broker-confirm-box {

        width: min(92vw, 460px);

        max-width: 460px;

        background: var(--card-bg, #fff);

        color: var(--text, #1a2332);

        border-radius: 16px;

        padding: 22px;

        box-shadow: 0 20px 60px rgba(0,0,0,0.35);

        text-align: center;

    }

    body.dark-mode .broker-confirm-box { background: #0f1614; color: #e6edf3; }

    .broker-confirm-box h3 {

        margin: 0 0 12px;

        font-size: 1.05rem;

        color: var(--accent, #2ecc8f);

    }

    .broker-confirm-box p {

        margin: 0 0 20px;

        font-size: 0.92rem;

        line-height: 1.6;

        opacity: 0.9;

        text-align: left;

    }

    .broker-confirm-actions {

        display: flex;

        flex-direction: column;

        gap: 10px;

    }

    .broker-confirm-actions button {

        width: 100%;

        padding: 13px;

        border-radius: 10px;

        border: none;

        font-weight: 700;

        font-size: 0.95rem;

        cursor: pointer;

    }

    .broker-confirm-actions .btn-yes {

        background: var(--accent, #2ecc8f);

        color: #fff;

    }

    .broker-confirm-actions .btn-cancel {

        background: rgba(127,127,127,0.15);

        color: var(--text, #1a2332);

    }

    body.dark-mode .broker-confirm-actions .btn-cancel { color: #e6edf3; }



    /* Live state pill */

    .broker-live-pill {

        display: inline-flex;

        align-items: center;

        gap: 6px;

        padding: 4px 10px;

        border-radius: 999px;

        font-size: 0.72rem;

        font-weight: 700;

        letter-spacing: 0.3px;

        text-transform: uppercase;

    }

    .broker-live-pill .dot {

        width: 7px;

        height: 7px;

        border-radius: 50%;

        background: currentColor;

        box-shadow: 0 0 0 3px rgba(0,0,0,0.05);

    }

    .broker-live-pill.state-connected {

        background: rgba(46,204,143,0.14);

        color: #2ecc8f;

    }

    .broker-live-pill.state-pending {

        background: rgba(245,166,35,0.16);

        color: #f5a623;

    }

    .broker-live-pill.state-error {

        background: rgba(239,68,68,0.14);

        color: #ef4444;

    }

    .broker-live-pill.state-idle {

        background: rgba(127,127,127,0.14);

        color: var(--text-muted, #8a9aa8);

    }



    /* Lock card */

    .broker-lock-card {

        background: rgba(245,166,35,0.10);

        border: 1px solid rgba(245,166,35,0.35);

        border-radius: 14px;

        padding: 18px 16px;

        margin-bottom: 18px;

        color: var(--text, #1a2332);

        line-height: 1.6;

        font-size: 0.92rem;

    }

    body.dark-mode .broker-lock-card {

        background: rgba(245,166,35,0.10);

        border-color: rgba(245,166,35,0.35);

        color: #e6edf3;

    }

    .broker-lock-card .lock-title {

        font-weight: 700;

        margin-bottom: 6px;

        color: #f5a623;

        display: flex;

        align-items: center;

        gap: 8px;

    }

    .broker-lock-card .lock-title i {

        font-size: 1.05rem;

    }

</style>

</head>

<body class="<?= htmlspecialchars($darkModeClass) ?> broker-page-body">



<div class="broker-page-wrapper">



    <header class="broker-sticky-top">

        <div class="broker-topbar">

            <a href="#"

               class="broker-topbar-back"

               onclick="event.preventDefault(); harvhubGoToTab('mydashboard'); return false;"

               aria-label="Back to Dashboard">

                <i class="fa-solid fa-arrow-left"></i>

            </a>

            <h1 class="broker-topbar-title">Connect Broker</h1>

        </div>

    </header>



    <div class="broker-scroll-area" id="brokerScrollArea">



        <div class="broker-user-info">

            <span class="broker-user-info-item">

                <span class="broker-user-label">User</span>

                <span class="broker-user-value"><?= htmlspecialchars($fullname ?: $email) ?></span>

            </span>

            <span class="broker-user-info-item">

                <span class="broker-user-label">Status</span>

                <span id="brokerLivePill" class="broker-live-pill state-idle">

                    <span class="dot"></span>

                    <span id="brokerLivePillText">Checking…</span>

                </span>

            </span>

        </div>



        <div id="brokerError" class="broker-error-message" style="display: none;"></div>

        <div id="brokerSuccess" class="broker-success-message" style="display: none;"></div>



        <!-- Lock card: shown while a disconnect request is pending -->

        <div id="brokerLockCard" class="broker-lock-card" style="<?= $broker_locked ? '' : 'display:none;' ?>">

            <div class="lock-title">

                <i class="fa-solid fa-hourglass-half"></i>

                <span>Broker Deletion in Progress</span>

            </div>

            <div>

                You cannot add a broker yet. Please wait while we finish deleting

                the previous broker terminal from the VPS. You will be notified

                once that is complete, and you will then be able to add new

                broker details.

            </div>

        </div>



        <!-- Update form: shown when broker is already connected -->

        <div id="updateFormWrapper" style="<?= ($broker_connected && !$broker_locked) ? '' : 'display:none;' ?>">

            <div class="broker-section-title">Update Your Broker Account</div>



            <form id="updateBrokerForm" novalidate>

                <div class="broker-form-group">

                    <label for="update_broker_select">Select Broker</label>

                    <select name="broker" id="update_broker_select" required>

                        <option value="">-- Select Broker --</option>

                        <?php foreach ($allowed_brokers as $broker): ?>

                            <option value="<?= htmlspecialchars($broker) ?>" <?= ($current_broker == $broker) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($broker) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <div class="broker-form-group">

                    <label for="update_server_input">Server</label>

                    <input type="text" name="server" id="update_server_input" placeholder="broker server" value="<?= htmlspecialchars($current_server) ?>" required>

                </div>



                <div class="broker-form-group">

                    <label for="update_login_input">Login Number</label>

                    <input type="text" name="login" id="update_login_input" placeholder="Your MT5 login number" value="<?= htmlspecialchars($current_login) ?>" required>

                </div>



                <div class="broker-form-group">

                    <label for="update_password_input">MT5 Password</label>

                    <div class="broker-password-wrapper">

                        <input type="password" name="broker_password" id="update_password_input" placeholder="Re-enter your MT5 password" required>

                        <button type="button" class="broker-password-toggle" onclick="togglePasswordField('update_password_input')">Show</button>

                    </div>

                </div>



                <button type="submit" id="updateBrokerBtn" class="broker-btn-submit">

                    Update Broker Details

                </button>

            </form>

        </div>



        <!-- Connect (fresh) form: shown when no broker is connected -->

        <div id="connectFormWrapper" style="<?= ($broker_connected || $broker_locked) ? 'display:none;' : '' ?>">

            <div id="availableBrokersSection">

                <div class="broker-section-title">Available Brokers</div>



                <div id="brokerSelectionGrid" class="broker-selection-grid">

                    <?php foreach ($allowed_brokers as $broker):

                        $link = isset($broker_links[$broker]) ? $broker_links[$broker] : '';

                    ?>

                        <div class="broker-selection-item">

                            <span class="broker-name"><?= htmlspecialchars($broker) ?></span>

                            <div class="broker-action">

                                <?php if (!empty($link)): ?>

                                    <a href="https\://<?= htmlspecialchars($link) ?>" target="_blank" class="broker-link-btn">

                                        Go to <?= htmlspecialchars($broker) ?>

                                    </a>

                                <?php else: ?>

                                    <span class="broker-link-btn no-link">No Link</span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>



                <div id="connectButtonWrapper" class="broker-connect-wrapper">

                    <button type="button" class="broker-btn-secondary" onclick="showConnectForm()">

                        I Already Have an Account

                    </button>

                </div>

            </div>



            <div id="connectForm" class="<?= ($has_links && !$broker_connected) ? 'hidden' : '' ?>">

                <hr class="broker-divider">



                <form id="connectBrokerForm" novalidate>

                    <div class="broker-form-group">

                        <label for="connect_broker_select">Select Broker</label>

                        <select name="broker" id="connect_broker_select" required>

                            <option value="">-- Select Broker --</option>

                            <?php foreach ($allowed_brokers as $broker): ?>

                                <option value="<?= htmlspecialchars($broker) ?>">

                                    <?= htmlspecialchars($broker) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>



                    <div class="broker-form-group">

                        <label for="connect_server_input">Server</label>

                        <input type="text" name="server" id="connect_server_input" placeholder="e.g. Exness-MT5" required>

                    </div>



                    <div class="broker-form-group">

                        <label for="connect_login_input">Login Number</label>

                        <input type="text" name="login" id="connect_login_input" placeholder="Your MT5 login number" required>

                    </div>



                    <div class="broker-form-group">

                        <label for="connect_password_input">MT5 Password</label>

                        <div class="broker-password-wrapper">

                            <input type="password" name="broker_password" id="connect_password_input" placeholder="Your MT5 password" required>

                            <button type="button" class="broker-password-toggle" onclick="togglePasswordField('connect_password_input')">Show</button>

                        </div>

                    </div>



                    <button type="submit" id="connectBrokerBtn" class="broker-btn-submit">

                        Connect Broker

                    </button>

                </form>

            </div>

        </div>



        <div class="broker-info-note">

            <strong>Secure:</strong> Your broker credentials are encrypted and stored securely.

            They are only used for automated trading execution.

        </div>



    </div>

</div>



<div id="brokerConfirmModal" class="broker-confirm-overlay" aria-hidden="true">

    <div class="broker-confirm-box" role="dialog" aria-modal="true" aria-labelledby="brokerConfirmTitle">

        <h3 id="brokerConfirmTitle">Confirm Broker Connection</h3>

        <p>

            Before connecting, please make sure the full name on your broker account matches the name registered on your HarvHub profile. If the credentials do not match, this account will be removed from your VPS. Repeated invalid broker submissions may lead to account suspension. Do you want to proceed?

        </p>

        <div class="broker-confirm-actions">

            <button type="button" class="btn-yes" id="brokerConfirmYes">Yes, Proceed</button>

            <button type="button" class="btn-cancel" id="brokerConfirmCancel">Cancel</button>

        </div>

    </div>

</div>



<script>

    function harvhubGoToTab(tab) {

        try {

            if (window\.parent && window\.parent !== window) {

                window\.parent.postMessage({ type: 'switchTab', tab: tab }, '*');

                return;

            }

        } catch (e) {}

        window\.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);

    }

    window\.harvhubGoToTab = harvhubGoToTab;



    (function () {

        document.body.classList.add('page-connect_investor_broker');



        window\.addEventListener('message', function (e) {

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

            'page-trader_app'

        ];

        function broadcast() {

            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });

            try {

                window\.parent.postMessage({

                    type: 'bodyClass',

                    add: add,

                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })

                }, '*');

            } catch (e) {}

        }

        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });

        broadcast();

        try { window\.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}

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

        if (cancelBtn) {

            cancelBtn.addEventListener('click', function () {

                closeBrokerConfirm();

            });

        }

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



        // Lock takes priority — nothing else should be visible.

        if (lockCard) {

            lockCard.style.display = locked ? '' : 'none';

        }



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

        fetch('connect_investor_broker.php', {

            method: 'POST',

            headers: {

                'Content-Type': 'application/x-www-form-urlencoded',

                'X-Requested-With': 'XMLHttpRequest'

            },

            credentials: 'same-origin',

            body: 'get_broker_state=1'

        })

        .then(function (r) { return r.json(); })

        .then(function (data) {

            if (data && data.success) {

                applyLiveState(data);

            }

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

    function bindBrokerForm(formId, buttonId) {

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

                doSaveBroker(broker, server, login, pass, passEl, btn);

            });

        });

    }



    function doSaveBroker(broker, server, login, pass, passEl, btn) {

        if (btn) {

            btn.disabled = true;

            btn.dataset.originalText = btn.textContent;

            btn.textContent = 'Saving...';

        }



        var body = 'connect_broker_ajax=1'

                 + '&broker='          + encodeURIComponent(broker)

                 + '&server='          + encodeURIComponent(server)

                 + '&login='           + encodeURIComponent(login)

                 + '&broker_password=' + encodeURIComponent(pass);



        fetch('connect_investor_broker.php', {

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

                    btn.disabled = false;

                    btn.textContent = btn.dataset.originalText || 'Save';

                }

                showBrokerSuccess(data.message || 'Broker connected successfully.');



                if (passEl) passEl.value = '';



                fetchLiveState();



                try {

                    if (window\.parent && window\.parent !== window) {

                        window\.parent.postMessage({ type: 'harvhubRefreshShell' }, '*');

                    }

                } catch (e) {}

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

        bindBrokerForm('connectBrokerForm', 'connectBrokerBtn');

        bindBrokerForm('updateBrokerForm',  'updateBrokerBtn');



        <?php if (!$has_links && !$broker_connected && !$broker_locked): ?>

            showConnectForm();

        <?php endif; ?>



        startLivePoll();

    }



    if (document.readyState === 'loading') {

        document.addEventListener('DOMContentLoaded', init);

    } else {

        init();

    }

</script>



</body>

</html>