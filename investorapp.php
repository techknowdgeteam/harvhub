<?php
    // investorapp.php — Investor workspace shell (sub-account aware)
    session_start();
    require_once 'usersdb.php';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    require_once __DIR__ . '/notification_service.php';

    // ---- LOGOUT ----
    if (isset($_GET['logout'])) {
        if (isset($_SESSION['user_email'])) {
            try {
                $logoutEmail = strtolower($_SESSION['user_email']);
                $subId  = (int)($_SESSION['active_sub_account_id'] ?? 0);

                if ($subId > 0) {
                    $lastAccount = 's' . $subId;
                    $upd = $pdo->prepare("UPDATE $tableName SET last_account = ? WHERE sub_account_id = ? AND LOWER(email) = ?");
                    $upd->execute([$lastAccount, $subId, $logoutEmail]);
                }
            } catch (Throwable $e) {}
        }

        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header("Location: index.php");
        exit;
    }

    if (!isset($_SESSION['user_email'])) {
        header("Location: index.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    $activeSubId = (int)($_SESSION['active_sub_account_id'] ?? 0);

    if ($activeSubId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
        $stmt->execute([$activeSubId, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        $lastQ = $pdo->prepare("SELECT last_account FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
        $lastQ->execute([$email]);
        $lastRow = $lastQ->fetch(PDO::FETCH_ASSOC);
        $lastAccount = trim((string)($lastRow['last_account'] ?? ''));

        $resolved = null;
        if (preg_match('/^s(\d+)$/', $lastAccount, $m)) {
            $subId = (int)$m[1];
            if ($subId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
                $q->execute([$subId, $email]);
                $resolved = $q->fetch(PDO::FETCH_ASSOC);
            }
        }
        if (!$resolved && preg_match('/^m(\d+)$/', $lastAccount, $m)) {
            $mainId = (int)$m[1];
            if ($mainId > 0) {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE main_account_id = ? AND LOWER(email) = ? ORDER BY id ASC LIMIT 1");
                $q->execute([$mainId, $email]);
                $resolved = $q->fetch(PDO::FETCH_ASSOC);
            }
        }
        if (!$resolved) {
            $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
            $q->execute([$email]);
            $resolved = $q->fetch(PDO::FETCH_ASSOC);
        }
        if (!$resolved) {
            $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
            $q->execute([$email]);
            $resolved = $q->fetch(PDO::FETCH_ASSOC);
        }
        $user = $resolved;
    }

    if (!$user) {
        unset($_SESSION['user_email'], $_SESSION['auth_role']);
        header("Location: index.php");
        exit;
    }

    $userId             = (int)$user['id'];
    $activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
    $mainAccountId      = (int)($user['main_account_id'] ?? 0);
    $activeSubName      = trim((string)($user['sub_account_name'] ?? ''));
    $darkMode           = !empty($user['dark_mode']);

    $_SESSION['active_sub_account_id']  = $activeSubAccountId;
    $_SESSION['active_main_account_id'] = $mainAccountId;

    if (!function_exists('normalizeAccountName')) {
        function normalizeAccountName($name) {
            $name = preg_replace('/[^A-Za-z0-9 ]+/', '', (string)$name);
            $name = preg_replace('/\s+/', ' ', $name);
            $name = strtolower(trim($name));
            return substr($name, 0, 20);
        }
    }

    if (!function_exists('getGlobalCredentials')) {
        function getGlobalCredentials($pdo, $tableName, $email) {
            $email = strtolower(trim($email));
            $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? AND is_main_account = 1 ORDER BY id ASC LIMIT 1");
            $q->execute([$email]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;

            $q = $pdo->prepare("SELECT * FROM $tableName WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
            $q->execute([$email]);
            return $q->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if (!function_exists('recordLastAccount')) {
        function recordLastAccount($pdo, $tableName, $email, $subId) {
            try {
                $upd = $pdo->prepare("UPDATE $tableName SET last_account = ? WHERE sub_account_id = ? AND LOWER(email) = ?");
                $upd->execute(['s' . $subId, $subId, $email]);
            } catch (Throwable $e) {}
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['get_sub_accounts'])) {
            header('Content-Type: application/json; charset=utf-8');

            try {
                $q = $pdo->prepare("
                    SELECT id, sub_account_id, sub_account_name, email, is_main_account
                    FROM $tableName
                    WHERE LOWER(email) = ?
                    ORDER BY id ASC
                ");
                $q->execute([$email]);
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);

                $list = [];
                foreach ($rows as $r) {
                    $name = (string)($r['sub_account_name'] ?? '');
                    $list[] = [
                        'id'             => (int)$r['id'],
                        'sub_account_id' => (int)($r['sub_account_id'] ?? $r['id']),
                        'name'           => $name,
                        'initial'        => strtoupper(substr($name !== '' ? $name : 'U', 0, 1)),
                        'is_active'      => ((int)($r['sub_account_id'] ?? $r['id']) === $activeSubAccountId)
                    ];
                }

                echo json_encode([
                    'success'          => true,
                    'active_sub_name'  => $activeSubName,
                    'active_sub_id'    => $activeSubAccountId,
                    'accounts'         => $list
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to load sub accounts.']);
            }
            exit;
        }

        if (isset($_POST['create_sub_account_from_shell'])) {
            header('Content-Type: application/json; charset=utf-8');

            $rawName = (string)($_POST['account_name'] ?? '');
            $normalized = normalizeAccountName($rawName);

            if ($normalized === '') {
                echo json_encode(['success' => false, 'errors' => ['Account name is required.']]);
                exit;
            }
            if (strlen($normalized) > 20) {
                echo json_encode(['success' => false, 'errors' => ['Account name cannot exceed 20 characters.']]);
                exit;
            }

            $globalRow = getGlobalCredentials($pdo, $tableName, $email);
            if (!$globalRow) {
                echo json_encode(['success' => false, 'errors' => ['Could not resolve your account credentials.']]);
                exit;
            }

            if ($mainAccountId <= 0) {
                try {
                    $pdo->prepare("INSERT INTO main_accounts (owner_email) VALUES (?) ON DUPLICATE KEY UPDATE owner_email = VALUES(owner_email)")
                        ->execute([$email]);
                    $q = $pdo->prepare("SELECT id FROM main_accounts WHERE owner_email = ? LIMIT 1");
                    $q->execute([$email]);
                    $row = $q->fetch(PDO::FETCH_ASSOC);
                    $mainAccountId = (int)($row['id'] ?? 0);
                    if ($mainAccountId > 0) {
                        $pdo->prepare("UPDATE $tableName SET main_account_id = ? WHERE LOWER(email) = ?")
                            ->execute([$mainAccountId, $email]);
                    }
                } catch (Throwable $e) {
                    echo json_encode(['success' => false, 'errors' => ['Failed to prepare main account.']]);
                    exit;
                }
            }

            try {
                $chk = $pdo->prepare("
                    SELECT id FROM $tableName
                    WHERE LOWER(email) = ? AND LOWER(sub_account_name) = ?
                    LIMIT 1
                ");
                $chk->execute([$email, $normalized]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => false, 'errors' => ['That account name is already used.']]);
                    exit;
                }
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'errors' => ['Failed to verify uniqueness.']]);
                exit;
            }

            try {
                $ins = $pdo->prepare("
                    INSERT INTO $tableName
                    (fullname, first_name, last_name, username, email, password,
                    main_account_id, sub_account_id, sub_account_name, is_main_account,
                    dark_mode, broker, server, login, broker_password,
                    broker_balance, profitandloss, execution_start_date,
                    balance_verification, reset_contract, loyalties,
                    recent_highest_balance, notifications, contract_id, tier_limit)
                    VALUES
                    (?, ?, ?, ?, ?, ?,
                    ?, NULL, ?, 0,
                    ?, NULL, NULL, NULL, NULL,
                    NULL, NULL, NULL,
                    'not-verified', 0, NULL,
                    NULL, '[]', NULL, NULL)
                ");
                $ins->execute([
                    $globalRow['fullname']   ?? '',
                    $globalRow['first_name'] ?? '',
                    $globalRow['last_name']  ?? '',
                    $globalRow['username']   ?? '',
                    $globalRow['email']      ?? $email,
                    $globalRow['password']   ?? '',
                    $mainAccountId,
                    $normalized,
                    (int)($globalRow['dark_mode'] ?? 0),
                ]);
                $newId = (int)$pdo->lastInsertId();
                if ($newId <= 0) throw new Exception('Insert failed');
                $pdo->prepare("UPDATE $tableName SET sub_account_id = id WHERE id = ?")->execute([$newId]);

                echo json_encode([
                    'success'        => true,
                    'message'        => 'Sub account created.',
                    'sub_account_id' => $newId,
                    'account_name'   => $normalized
                ]);
            } catch (Throwable $e) {
                echo json_encode([
                    'success' => false,
                    'errors'  => ['Failed to create sub account: ' . $e->getMessage()]
                ]);
            }
            exit;
        }

        if (isset($_POST['switch_sub_account_from_shell'])) {
            header('Content-Type: application/json; charset=utf-8');

            $targetSubId = (int)($_POST['sub_account_id'] ?? 0);
            if ($targetSubId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid sub account.']);
                exit;
            }

            try {
                $q = $pdo->prepare("SELECT * FROM $tableName WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
                $q->execute([$targetSubId, $email]);
                $target = $q->fetch(PDO::FETCH_ASSOC);
                if (!$target) {
                    echo json_encode(['success' => false, 'message' => 'Sub account not found.']);
                    exit;
                }

                $_SESSION['user_email'] = strtolower($target['email']);
                $_SESSION['active_sub_account_id'] = (int)$target['sub_account_id'];
                $_SESSION['active_main_account_id'] = (int)$target['main_account_id'];

                recordLastAccount($pdo, $tableName, $target['email'], (int)$target['sub_account_id']);

                echo json_encode([
                    'success'        => true,
                    'message'        => 'Switched.',
                    'sub_account_id' => (int)$target['sub_account_id'],
                    'account_name'   => $target['sub_account_name']
                ]);
            } catch (Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to switch.']);
            }
            exit;
        }

        if (isset($_POST['mark_notifications_read'])) {
            header('Content-Type: application/json; charset=utf-8');
            $ok = markContractNotificationsRead($pdo, $email, $activeSubAccountId);
            echo json_encode([
                'success' => $ok,
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['get_notifications_list'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'notifications' => getContractNotifications($pdo, $email, $activeSubAccountId, 100),
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['check_new_notifications'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'unread_count' => getNotificationUnreadCount($pdo, $email, $activeSubAccountId)
            ]);
            exit;
        }

        if (isset($_POST['get_notification_preferences'])) {
            header('Content-Type: application/json; charset=utf-8');
            $prefs = getNotificationPreferences($pdo, $email, $mainAccountId);
            echo json_encode([
                'success' => true,
                'preferences' => [
                    'browser_notifications_enabled' => (int)($prefs['browser_notifications_enabled'] ?? 0),
                    'notification_sound_enabled' => (int)($prefs['notification_sound_enabled'] ?? 0),
                    'permission_prompt_seen' => (int)($prefs['permission_prompt_seen'] ?? 0)
                ]
            ]);
            exit;
        }

        if (isset($_POST['save_notification_preferences'])) {
            header('Content-Type: application/json; charset=utf-8');

            $browser = isset($_POST['browser_notifications_enabled'])
                ? (int)$_POST['browser_notifications_enabled'] : null;
            $sound = isset($_POST['notification_sound_enabled'])
                ? (int)$_POST['notification_sound_enabled'] : null;
            $seen = isset($_POST['permission_prompt_seen'])
                ? (int)$_POST['permission_prompt_seen'] : null;

            $ok = saveNotificationPreferences(
                $pdo,
                $email,
                $mainAccountId,
                $browser,
                $sound,
                $seen
            );

            echo json_encode(['success' => $ok]);
            exit;
        }

        if (isset($_POST['toggle_dark_mode_ajax'])) {
            header('Content-Type: application/json');
            $v = isset($_POST['dark_mode_checkbox']) ? (int)$_POST['dark_mode_checkbox'] : 0;
            $u = $pdo->prepare("UPDATE $tableName SET dark_mode = ? WHERE LOWER(email) = ?");
            $u->execute([$v, $email]);
            echo json_encode(['success' => true, 'dark_mode' => $v]);
            exit;
        }

        // -------- LIVE SHELL STATE POLL --------
        // Returns everything the shell needs to stay in sync without a reload:
        //  - current sub-account's unread notification count (bell badge)
        //  - per-sub-account unread counts (switcher list)
        //  - active sub-account id + name (to keep the sheet in sync)
        if (isset($_POST['poll_shell_state'])) {
            header('Content-Type: application/json; charset=utf-8');

            $unreadCurrent = 0;
            try {
                $unreadCurrent = getNotificationUnreadCount($pdo, $email, $activeSubAccountId);
            } catch (Throwable $e) {}

            // Per-sub-account unread notification map
            $notifMap = [];
            $subList = [];
            try {
                $q = $pdo->prepare("
                    SELECT id, sub_account_id, sub_account_name, is_main_account
                    FROM $tableName
                    WHERE LOWER(email) = ?
                    ORDER BY id ASC
                ");
                $q->execute([$email]);
                $rows = $q->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $sid = (int)($r['sub_account_id'] ?? $r['id']);
                    $notifMap[$sid] = 0;
                    try {
                        $notifMap[$sid] = (int)getNotificationUnreadCount($pdo, $email, $sid);
                    } catch (Throwable $e) {}

                    $name = (string)($r['sub_account_name'] ?? '');
                    $subList[] = [
                        'id'             => (int)$r['id'],
                        'sub_account_id' => $sid,
                        'name'           => $name,
                        'initial'        => strtoupper(substr($name !== '' ? $name : 'U', 0, 1)),
                        'is_active'      => ($sid === $activeSubAccountId),
                        'unread_count'   => $notifMap[$sid]
                    ];
                }
            } catch (Throwable $e) {}

            echo json_encode([
                'success'              => true,
                'unread_count'         => $unreadCurrent,
                'active_sub_id'        => $activeSubAccountId,
                'active_sub_name'      => $activeSubName,
                'notif_by_sub_account' => $notifMap,
                'sub_accounts'         => $subList,
            ]);
            exit;
        }
    }

    $initialUnreadCount = getNotificationUnreadCount($pdo, $email, $activeSubAccountId);
    $allNotifications = getContractNotifications($pdo, $email, $activeSubAccountId, 100);
    $notificationPreferences = getNotificationPreferences($pdo, $email, $mainAccountId);

    $navTabs = ['mydashboard', 'trades', 'activity', 'analytics', 'menu'];
    $defaultTab = $_GET['tab'] ?? 'mydashboard';

    $tabSrc = [
        'mydashboard'             => 'mydashboard.php',
        'trades'                  => 'revenue_target.php',
        'activity'                => 'activity.php',
        'analytics'               => 'useranalytics.php',
        'menu'                    => 'menu.php',
        'revenue_history'         => 'revenue_history.php',
        'profit_split'            => 'profit_split.php',
        'vps'                     => 'vps.php',
        'disconnect_broker'       => 'disconnect_broker.php',
        'programmes'              => 'programmes.php',
        'connect_investor_broker' => 'connect_investor_broker.php',
    ];

    if (!array_key_exists($defaultTab, $tabSrc)) $defaultTab = 'mydashboard';

    $hiddenTabs = [
        'revenue_history','profit_split','vps','disconnect_broker','programmes','connect_investor_broker'
    ];

    $activeNavTab = in_array($defaultTab, $navTabs, true) ? $defaultTab : 'menu';

    $tabsWithBrandHeader = ['mydashboard'];
    $tabsWithNav         = ['mydashboard', 'trades', 'activity', 'analytics', 'menu'];
    $tabsWithPageHeader  = ['trades', 'activity', 'analytics', 'menu'];
    $tabsWithNoChrome    = ['vps', 'programmes', 'revenue_history'];

    $tabTitles = [
        'mydashboard'             => 'Dashboard',
        'trades'                  => 'Daily Revenue',
        'activity'                => 'Activity',
        'analytics'               => 'Analytics',
        'menu'                    => 'Menu',
        'revenue_history'         => 'Revenue History',
        'profit_split'            => 'Profit Split',
        'vps'                     => 'VPS Hub',
        'programmes'              => 'Programmes',
        'disconnect_broker'       => 'Disconnect Broker',
        'connect_investor_broker' => 'Connect Broker',
    ];

    $showBrandHeader = in_array($defaultTab, $tabsWithBrandHeader, true);
    $showNav         = in_array($defaultTab, $tabsWithNav, true);
    $showPageHeader  = in_array($defaultTab, $tabsWithPageHeader, true);

    if (in_array($defaultTab, $tabsWithNoChrome, true)) {
        $showBrandHeader = false;
        $showNav         = false;
        $showPageHeader  = false;
    }

    $takeoverTabs = ['programmes', 'revenue_history', 'profit_split', 'vps', 'disconnect_broker', 'connect_investor_broker'];
    $bodyExtraClass = in_array($defaultTab, $takeoverTabs, true) ? 'page-' . $defaultTab : '';

    $chromeClasses = [];
    if (!$showBrandHeader && !$showPageHeader) $chromeClasses[] = 'shell-no-header';
    if (!$showBrandHeader)                    $chromeClasses[] = 'shell-no-brand-header';
    if (!$showPageHeader)                     $chromeClasses[] = 'shell-no-page-header';
    if (!$showNav)                            $chromeClasses[] = 'shell-no-nav';
    $chromeClassStr = implode(' ', $chromeClasses);

    $initialPageHeaderTitle = $tabTitles[$defaultTab] ?? 'HarvHub';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>HarvHub Investor</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    :root {
        --bg: #ffffff;
        --text: #1a2332;
        --text-muted: #8a9aa8;
        --accent: #2ecc8f;
        --gold: #f5a623;
        --grey-avatar: #c7cfd6;
        --nav-height: 72px;
        --brand-header-height: 56px;
        --page-header-height: 56px;
        --shell-page-header-height: 56px;
        --safe-top: env(safe-area-inset-top, 0px);
        --safe-bottom: env(safe-area-inset-bottom, 0px);

        --surface: #ffffff;
        --surface-2: #f7fafc;
        --border: rgba(0,0,0,0.08);
        --shadow: 0 8px 32px rgba(0,0,0,0.12);
    }

    @media (max-width: 480px) {
        :root {
            --page-header-height: 52px;
            --shell-page-header-height: 52px;
        }
    }

    body.dark-mode {
        --bg: #0c1311;
        --text: #e6edf3;
        --text-muted: #6e7681;
        --accent: #2ecc8f;
        --gold: #f5a623;
        --grey-avatar: #3a4650;

        --surface: #0c1311;
        --surface-2: #0f1614;
        --border: rgba(255,255,255,0.06);
        --shadow: 0 8px 32px rgba(0,0,0,0.6);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        height: 100%;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: var(--bg);
        color: var(--text);
        -webkit-tap-highlight-color: transparent;
    }

    .shell {
        max-width: 1100px;
        margin: 0 auto;
        width: 100%;
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        height: 100%;
        height: 100dvh;
    }

    @media (max-width: 1100px) {
        .shell {
            max-width: 100%;
        }
    }

    .shell-page-header {
        max-width: 1100px;
        left: 50%;
        right: auto;
        transform: translateX(-50%);
        width: 100%;
    }

    .shell-page-header.is-hidden {
        transform: translateX(-50%) translateY(-100%);
    }

    @media (max-width: 1100px) {
        .shell-page-header {
            max-width: 100%;
            left: 0;
            right: 0;
            transform: none;
        }
        .shell-page-header.is-hidden {
            transform: translateY(-100%);
        }
    }

    .bottom-nav {
        max-width: 500px;
    }

    @media (min-width: 801px) {
        .notification-panel {
            right: calc((100vw - 1100px) / 2);
            width: min(420px, 100%);
        }
    }

    body.shell-no-header       .harvhub-header-top { display: none !important; }
    body.shell-no-brand-header .harvhub-header-top { display: none !important; }
    body.shell-no-page-header  .shell-page-header  { display: none !important; }
    body.shell-no-nav          .bottom-nav         { display: none !important; }

    body.profile-page-open .harvhub-header-top { display: none !important; }
    body.profile-page-open .shell-page-header  { display: none !important; }
    body.profile-page-open .bottom-nav         { display: none !important; }

    body.modal-overlay-open .harvhub-header-top { display: none !important; }
    body.modal-overlay-open .shell-page-header  { display: none !important; }
    body.modal-overlay-open .bottom-nav         { display: none !important; }

    .harvhub-header-top {
        position: relative;
        z-index: 500;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: calc(var(--safe-top) + 10px) 16px 10px;
        background: var(--surface);
        min-height: var(--brand-header-height);
        flex: 0 0 auto;
    }
    .harvhub-header-top .header-left { display: flex; align-items: center; gap: 8px; }

    .harvhub-header-top .header-logo {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--accent);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        letter-spacing: 0.2px;
    }

    .harvhub-header-top .header-dropdown-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        padding: 0;
        margin-left: 4px;
        background: transparent;
        border: none;
        color: var(--accent);
        font-size: 0.85rem;
        cursor: pointer;
        border-radius: 50%;
        transition: background 0.2s ease, transform 0.25s ease;
    }
    .harvhub-header-top .header-dropdown-btn:hover {
        background: rgba(46,204,143,0.1);
    }
    .harvhub-header-top .header-dropdown-btn.is-open {
        transform: rotate(180deg);
    }
    body.dark-mode .harvhub-header-top .header-dropdown-btn:hover {
        background: rgba(46,204,143,0.18);
    }

    .harvhub-header-top .header-right { display: flex; align-items: center; gap: 8px; }
    .harvhub-header-top .notification-bell {
        position: relative;
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: var(--text);
        background: transparent;
        cursor: pointer;
        transition: background 0.2s ease;
        font-size: 1.15rem;
    }
    .harvhub-header-top .notification-bell:hover { background: rgba(0,0,0,0.05); }
    body.dark-mode .harvhub-header-top .notification-bell:hover { background: rgba(255,255,255,0.06); }
    .harvhub-header-top .notification-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        min-width: 16px;
        height: 16px;
        padding: 0 4px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        font-size: 0.62rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        box-shadow: 0 0 0 2px var(--surface);
        transition: transform 0.25s ease;
    }
    .harvhub-header-top .notification-badge.badge-bump {
        animation: badgeBump .45s ease;
    }
    @keyframes badgeBump {
        0% { transform: scale(1); }
        50% { transform: scale(1.35); }
        100% { transform: scale(1); }
    }

    .header-sheet-backdrop {
        position: fixed;
        top: var(--safe-top);               /* ← skip the notch/status-bar strip */
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.45);
        opacity: 0;
        pointer-events: none;
        visibility: hidden;
        transition: opacity 0.28s ease, visibility 0s linear 0.28s;
        z-index: 1100;
    }
    .header-sheet-backdrop.active {
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
        transition: opacity 0.28s ease, visibility 0s linear 0s;
    }

    .header-sheet {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--surface);
        color: var(--text);
        border-top-left-radius: 22px;
        border-top-right-radius: 22px;
        transform: translateY(100%);
        transition: transform 0.34s cubic-bezier(.2,.8,.2,1);
        z-index: 1101;
        padding: 12px 18px calc(20px + var(--safe-bottom));
        max-height: 50vh;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        will-change: transform;
    }
    body.dark-mode .header-sheet {
        border-top: 1px solid rgba(255,255,255,0.06);
    }
    .header-sheet.active {
        transform: translateY(0);
    }

    .header-sheet-handle {
        width: 42px;
        height: 4px;
        border-radius: 999px;
        background: var(--border);
        margin: 4px auto 14px;
        opacity: 0.7;
    }

    .header-sheet-title {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-muted);
        font-weight: 700;
        margin: 4px 4px 10px;
    }

    .header-sheet-row {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 10px;
        border-radius: 14px;
        cursor: pointer;
        transition: background 0.2s ease;
        text-decoration: none;
        color: inherit;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        font-family: inherit;
    }
    .header-sheet-row:hover { background: rgba(46,204,143,0.08); }
    body.dark-mode .header-sheet-row:hover { background: rgba(46,204,143,0.12); }

    .header-sheet-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--grey-avatar);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1.05rem;
        flex-shrink: 0;
        overflow: hidden;
    }

    /* Investor's Hub avatar — gold */
    .header-sheet-avatar.is-investor-hub {
        background: var(--gold);
        color: #fff;
    }

    /* Developer's Hub avatar — gold, icon-based */
    .header-sheet-avatar.is-developer-hub {
        background: var(--gold);
        color: #fff;
    }

    .header-sheet-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
    .header-sheet-text .hs-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: var(--text);
        word-break: break-word;
    }
    .header-sheet-text .hs-desc {
        font-size: 0.76rem;
        color: var(--text-muted);
        line-height: 1.4;
    }
    .header-sheet-chev {
        color: var(--text-muted);
        font-size: 1rem;
        flex-shrink: 0;
    }

    .header-sheet-row.logout-row .header-sheet-avatar {
        background: rgba(239,68,68,0.12);
        color: #ef4444;
    }
    .header-sheet-row.logout-row .hs-title { color: #ef4444; }
    .header-sheet-row.logout-row:hover { background: rgba(239,68,68,0.08); }

    .header-sheet-row.current-account-row {
        background: rgba(46,204,143,0.08);
        cursor: default;
    }
    .header-sheet-row.current-account-row:hover {
        background: rgba(46,204,143,0.08);
    }

    .header-sheet-divider {
        height: 1px;
        background: var(--border);
        margin: 8px 4px;
        opacity: 0.6;
    }

    .header-sheet-sub-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin: 4px 0;
        padding-left: 4px;
    }

    @media (min-width: 801px) {
        .header-sheet {
            left: 50%;
            right: auto;
            width: 100%;
            max-width: 1100px;
            transform: translateX(-50%) translateY(100%);
        }

        .header-sheet.active {
            transform: translateX(-50%) translateY(0);
        }
    }

    @media (max-width: 1100px) {
        .header-sheet {
            left: 0;
            right: 0;
            max-width: 100%;
            transform: translateY(100%);
        }
        .header-sheet.active {
            transform: translateY(0);
        }
    }

    /* Sub-account rows — profile avatar */
    .header-sheet-sub-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        min-height: 58px;
        border-radius: 12px;
        cursor: pointer;
        transition: background 0.18s ease;
        font-size: 0.9rem;
        color: var(--text);
        border: none;
        background: transparent;
        text-align: left;
        width: 100%;
        font-family: inherit;
        position: relative;
    }
    .header-sheet-sub-item:hover { background: rgba(46,204,143,0.08); }
    body.dark-mode .header-sheet-sub-item:hover { background: rgba(46,204,143,0.14); }
    .header-sheet-sub-item.is-active {
        background: rgba(46,204,143,0.14);
        font-weight: 700;
    }

    .header-sheet-sub-avatar {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        border-radius: 50%;
        background: var(--grey-avatar);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
        text-transform: uppercase;
        overflow: hidden;
    }
    /* Active sub-account avatar stays green with ring */
    .header-sheet-sub-item.is-active .header-sheet-sub-avatar {
        background: var(--accent);
        box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--accent);
    }

    .header-sheet-sub-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
    .header-sheet-sub-text .sub-name {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--text);
        word-break: break-word;
    }
    .header-sheet-sub-item .sub-check { color: var(--accent); font-size: 0.85rem; flex-shrink: 0; }

    body.header-sheet-open .harvhub-header-top { visibility: hidden; }
    body.header-sheet-open .shell-page-header  { visibility: hidden; }
    body.header-sheet-open .bottom-nav         { visibility: hidden; }

    .shell-page-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 998;
        background: var(--surface);
        padding-top: var(--safe-top);
        transform: translateY(0);
        transition: transform 0.3s ease, opacity 0.3s ease;
        opacity: 1;
        pointer-events: auto;
    }
    body.dark-mode .shell-page-header { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4); }
    .shell-page-header.is-hidden {
        transform: translateY(-100%);
        opacity: 0;
        pointer-events: none;
    }
    .shell-page-header-inner {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        min-height: var(--page-header-height);
        padding: 10px 16px;
        box-sizing: border-box;
    }
    .shell-page-header-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text);
        margin: 0;
        letter-spacing: -0.3px;
        text-align: left;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        line-height: 1.2;
    }

    @media (max-width: 480px) {
        .shell-page-header-title { font-size: 1.25rem; }
    }

    .notification-panel {
        position: fixed;
        top: 0;
        right: 0;
        width: min(420px, 100%);
        height: 100%;
        background: var(--surface);
        color: var(--text);
        box-shadow: var(--shadow);
        transform: translateX(100%);
        transition: transform 0.28s cubic-bezier(.2,.8,.2,1);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding-top: var(--safe-top);
    }
    .notification-panel.active { transform: translateX(0); }
    .notification-panel .panel-content {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
    }
    .notification-panel .notification-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 18px;
        flex: 0 0 auto;
        border-bottom: 1px solid var(--border);
    }
    .notification-panel .notification-header h3 { font-size: 1rem; font-weight: 700; }
    .notification-panel .close-notifications {
        border: 0;
        background: transparent;
        color: var(--text-muted);
        font-size: 1rem;
        width: 32px; height: 32px;
        border-radius: 8px;
        cursor: pointer;
    }
    .notification-panel .close-notifications:hover { background: rgba(0,0,0,0.05); }
    body.dark-mode .notification-panel .close-notifications:hover { background: rgba(255,255,255,0.06); }
    .notification-panel .notification-list {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 8px 0 24px;
        -webkit-overflow-scrolling: touch;
    }
    .notification-panel .notification-item {
        padding: 14px 18px;
        display: flex;
        flex-direction: column;
        gap: 5px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: background .18s ease;
        position: relative;
    }
    .notification-panel .notification-item:hover { background: var(--surface-2); }
    .notification-panel .notification-item.unread {
        background: rgba(46,204,143,0.07);
    }
    .notification-panel .notification-item.unread::after {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--accent);
        position: absolute;
        right: 18px;
        margin-top: 4px;
    }
    .notification-panel .notification-section {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: var(--accent);
    }
    .notification-panel .notification-title {
        font-size: .92rem;
        font-weight: 700;
        line-height: 1.3;
        color: var(--text);
        padding-right: 18px;
    }
    .notification-panel .notification-message {
        font-size: 0.84rem;
        line-height: 1.45;
        color: var(--text);
        opacity: .9;
    }
    .notification-panel .notification-time {
        font-size: 0.68rem;
        color: var(--text-muted);
    }
    .notification-panel .empty-notifications {
        padding: 50px 18px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.88rem;
    }

    .notification-toast {
        position: fixed;
        top: calc(var(--safe-top) + 12px);
        left: 50%;
        width: min(420px, calc(100% - 24px));
        transform: translate(-50%, -140%);
        opacity: 0;
        pointer-events: none;
        z-index: 3000;
        background: var(--surface);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 14px;
        box-shadow: 0 18px 50px rgba(0,0,0,.22);
        padding: 13px 15px;
        transition: transform .32s cubic-bezier(.2,.8,.2,1), opacity .22s ease;
        cursor: pointer;
    }
    .notification-toast.active {
        transform: translate(-50%, 0);
        opacity: 1;
        pointer-events: auto;
    }
    .notification-toast .toast-section {
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .5px;
        text-transform: uppercase;
        color: var(--accent);
        margin-bottom: 3px;
    }
    .notification-toast .toast-title { font-size: .9rem; font-weight: 750; line-height: 1.3; }
    .notification-toast .toast-message { font-size: .78rem; line-height: 1.4; color: var(--text-muted); margin-top: 3px; }

    .notification-permission-backdrop {
        position: fixed;
        inset: 0;
        z-index: 4000;
        background: rgba(0,0,0,.58);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .notification-permission-backdrop.active { display: flex; }
    .notification-permission-modal {
        width: min(420px, 100%);
        background: var(--surface);
        color: var(--text);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 25px 80px rgba(0,0,0,.3);
        text-align: center;
        animation: notificationPermissionIn .24s ease;
    }
    @keyframes notificationPermissionIn {
        from { opacity: 0; transform: translateY(12px) scale(.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .notification-permission-icon {
        width: 54px;
        height: 54px;
        margin: 0 auto 14px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(46,204,143,.12);
        color: var(--accent);
        font-size: 1.3rem;
    }
    .notification-permission-modal h3 { font-size: 1.1rem; margin-bottom: 8px; }
    .notification-permission-modal p {
        color: var(--text-muted);
        font-size: .84rem;
        line-height: 1.55;
        margin-bottom: 18px;
    }
    .notification-permission-actions { display:flex; flex-direction:column; gap:9px; }
    .notification-permission-actions button {
        width:100%;
        border:0;
        border-radius:11px;
        padding:13px 14px;
        font-weight:700;
        cursor:pointer;
        font-size:.9rem;
    }
    .notification-permission-allow { background:var(--accent); color:#fff; }
    .notification-permission-later { background:var(--surface-2); color:var(--text); }

    .viewport {
        flex: 1;
        position: relative;
        overflow: hidden;
        padding-bottom: calc(var(--nav-height) + var(--safe-bottom));
        background: var(--bg);
    }

    body.shell-no-nav .viewport { padding-bottom: 0; }

    .app-frame {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: 0;
        background: var(--bg);
        opacity: 0;
        pointer-events: none;
        z-index: 1;
        transition: opacity 0.2s ease;
    }
    .app-frame.active { opacity: 1; pointer-events: auto; z-index: 2; }

    .loader {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 14px;
        background: var(--bg);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        z-index: 20;
        transition: opacity 0.3s ease;
        pointer-events: none;
    }
    .loader.hidden { opacity: 0; pointer-events: none; }
    .spinner {
        width: 36px;
        height: 36px;
        border: 3px solid rgba(127,127,127,0.18);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .shell-blocking-loader {
        position: fixed;
        inset: 0;
        background: var(--bg);
        z-index: 999999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 14px;
    }
    .shell-blocking-loader.active { display: flex; }
    .shell-blocking-loader .spinner {
        width: 44px;
        height: 44px;
        border-width: 4px;
    }
    .shell-blocking-loader p {
        font-size: 0.85rem;
        color: var(--text-muted);
        letter-spacing: 0.3px;
    }

    .bottom-nav {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(0);
        width: calc(100% - 40px);
        max-width: 500px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 10px 12px;
        border-radius: 16px;
        z-index: 999;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, opacity 0.3s ease;
        opacity: 1;
        pointer-events: all;
    }
    body.dark-mode .bottom-nav {
        background: rgba(20, 20, 30, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
    }
    .bottom-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: var(--text-muted, #888);
        font-size: 0.6rem;
        transition: color 0.3s;
        padding: 4px 12px;
        border-radius: 8px;
        background: transparent;
        border: none;
        cursor: pointer;
        gap: 2px;
        min-width: 44px;
    }
    .bottom-nav-item .nav-icon { font-size: 1.3rem; line-height: 1.2; }
    .bottom-nav-item .nav-label {
        font-size: 0.55rem;
        font-weight: 500;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .bottom-nav-item.active,
    .bottom-nav-item:hover { color: var(--accent, #2e8b57); }

    @media (max-width: 480px) {
        .bottom-nav {
            width: calc(100% - 32px);
            padding: 8px 10px;
            bottom: 16px;
            border-radius: 14px;
        }
        .bottom-nav-item .nav-icon { font-size: 1.15rem; }
        .bottom-nav-item .nav-label { font-size: 0.5rem; }
    }
    @media (max-width: 360px) {
        .bottom-nav { width: calc(100% - 24px); bottom: 10px; }
        .bottom-nav-item { padding: 4px 6px; }
        .bottom-nav-item .nav-icon { font-size: 1rem; }
        .bottom-nav-item .nav-label { font-size: 0.45rem; }
    }

    body.page-connect_investor_broker .bottom-nav,
    body.profile-page-open .bottom-nav,
    body.modal-overlay-open .bottom-nav,
    body.page-revenue_history .bottom-nav,
    body.page-profit_split .bottom-nav,
    body.page-vps .bottom-nav,
    body.page-disconnect_broker .bottom-nav,
    body.page-programmes .bottom-nav,
    body.page-verify_code .bottom-nav,
    body.page-forgot_password .bottom-nav { display: none !important; }

    body.page-connect_investor_broker .harvhub-header-top,
    body.page-connect_investor_broker .shell-page-header,
    body.profile-page-open .harvhub-header-top,
    body.profile-page-open .shell-page-header,
    body.modal-overlay-open .harvhub-header-top,
    body.modal-overlay-open .shell-page-header,
    body.page-revenue_history .harvhub-header-top,
    body.page-revenue_history .shell-page-header,
    body.page-profit_split .harvhub-header-top,
    body.page-profit_split .shell-page-header,
    body.page-vps .harvhub-header-top,
    body.page-vps .shell-page-header,
    body.page-disconnect_broker .harvhub-header-top,
    body.page-disconnect_broker .shell-page-header,
    body.page-programmes .harvhub-header-top,
    body.page-programmes .shell-page-header,
    body.page-verify_code .harvhub-header-top,
    body.page-verify_code .shell-page-header,
    body.page-forgot_password .harvhub-header-top,
    body.page-forgot_password .shell-page-header { display: none !important; }

    body.prog-detail-view-open .bottom-nav { display: none !important; }
    body.prog-detail-view-open .harvhub-header-top { display: none !important; }
    body.prog-detail-view-open .shell-page-header { display: none !important; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?><?= $bodyExtraClass ? ' ' . htmlspecialchars($bodyExtraClass) : '' ?><?= $chromeClassStr ? ' ' . htmlspecialchars($chromeClassStr) : '' ?>">

<div class="shell-blocking-loader" id="shellBlockingLoader">
    <div class="spinner"></div>
    <p id="shellBlockingLoaderText">Loading…</p>
</div>

<div class="shell">

    <div class="harvhub-header-top" id="shellHeader">
        <div class="header-left">
            <span class="header-logo"><i class="fa-brands fa-pagelines"></i> HarvHub</span>
            <button type="button" class="header-dropdown-btn" id="headerDropdownBtn" aria-label="Open menu" aria-haspopup="true" aria-expanded="false">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
        <div class="header-right">
            <div class="notification-bell" id="headerBell" role="button" tabindex="0" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="notification-badge" id="headerNotificationBadge"
                      style="<?= $initialUnreadCount > 0 ? '' : 'display:none;' ?>">
                    <?= (int)$initialUnreadCount ?>
                </span>
            </div>
        </div>
    </div>

    <div class="shell-page-header" id="shellPageHeader">
        <div class="shell-page-header-inner">
            <h1 class="shell-page-header-title" id="shellPageHeaderTitle">
                <?= htmlspecialchars($initialPageHeaderTitle) ?>
            </h1>
        </div>
    </div>

    <div class="header-sheet-backdrop" id="headerSheetBackdrop"></div>
    <div class="header-sheet" id="headerSheet" role="dialog" aria-modal="true" aria-label="Account menu">
        <div class="header-sheet-handle"></div>

        <div class="header-sheet-title">All Accounts</div>

        <a href="javascript:void(0)" class="header-sheet-row" id="headerSheetOverview" style="display: none;">
            <span class="header-sheet-avatar" style="background:rgba(46,204,143,0.15);color:var(--accent);"><i class="fa-solid fa-layer-group"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Overview</span>
                <span class="hs-desc">Main account overview &amp; analytics</span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev"></i>
        </a>

        <button type="button" class="header-sheet-row" id="headerSheetDeveloperHub">
            <span class="header-sheet-avatar is-developer-hub"><i class="fa-solid fa-chart-line"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Developer's Hub</span>
                <span class="hs-desc">Turn your trading profession into a programme, attract investors and earn.</span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev"></i>
        </button>

        <div class="header-sheet-divider"></div>

        <div class="header-sheet-title">Accounts</div>
        <div class="header-sheet-sub-list" id="sheetSubAccountsList">
            <div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">Loading…</div>
        </div>

        <div class="header-sheet-divider"></div>

        <button type="button" class="header-sheet-row" id="headerSheetCreateAccount">
            <span class="header-sheet-avatar" style="background:rgba(46,204,143,0.15);color:var(--accent);"><i class="fa-solid fa-plus"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Create new account</span>
                <span class="hs-desc">Start a fresh sub account</span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev"></i>
        </button>

        <button type="button" class="header-sheet-row logout-row" id="headerSheetLogout">
            <span class="header-sheet-avatar"><i class="fa-solid fa-right-from-bracket"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Sign out</span>
                <span class="hs-desc">Sign out of your account</span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev"></i>
        </button>
    </div>

    <div class="header-sheet-backdrop" id="createAccountBackdrop"></div>
    <div class="header-sheet" id="createAccountSheet" role="dialog" aria-modal="true" aria-label="Create new account">
        <div class="header-sheet-handle"></div>
        <div class="header-sheet-title">Create new account</div>
        <div style="padding:4px 10px 0;">
            <label for="createAccountInput" style="font-size:0.82rem;color:var(--text-muted);display:block;margin-bottom:6px;">Account name</label>
            <input id="createAccountInput" type="text" maxlength="20" autocomplete="off"
                   placeholder="e.g. My Trading Account"
                   style="width:100%;padding:12px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--text);font-size:0.95rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;line-height:1.4;">
                Letters, numbers and spaces only. Special characters removed. Max 20 characters.
            </div>
            <div id="createAccountError" style="display:none;background:rgba(239,68,68,0.12);color:#ef4444;padding:10px;margin-top:12px;border-radius:6px;font-size:0.85rem;"></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;padding:14px 10px 0;">
            <button type="button" id="createAccountSubmit" style="width:100%;padding:14px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer;">Create account</button>
            <button type="button" id="createAccountCancel" style="width:100%;padding:14px;border:none;border-radius:10px;background:rgba(127,127,127,0.15);color:var(--text);font-weight:600;font-size:0.95rem;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <div class="header-sheet-backdrop" id="confirmSignoutBackdrop"></div>
    <div class="header-sheet" id="confirmSignoutSheet" role="dialog" aria-modal="true" aria-label="Confirm sign out">
        <div class="header-sheet-handle"></div>
        <div class="header-sheet-title">Sign out</div>
        <p style="padding:8px 10px 4px;font-size:0.95rem;line-height:1.5;color:var(--text);">
            Are you sure you want to sign out of your account?
        </p>
        <div style="display:flex;flex-direction:column;gap:10px;padding:14px 10px 0;">
            <button type="button" id="confirmSignoutYes" style="width:100%;padding:14px;border:none;border-radius:10px;background:#ef4444;color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer;">Yes, sign out</button>
            <button type="button" id="confirmSignoutNo" style="width:100%;padding:14px;border:none;border-radius:10px;background:rgba(127,127,127,0.15);color:var(--text);font-weight:600;font-size:0.95rem;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <div class="notification-panel" id="headerNotificationPanel">
        <div class="panel-content">
            <div class="notification-header">
                <h3>Notifications</h3>
                <button class="close-notifications" id="closeNotificationsBtn" aria-label="Close">✕</button>
            </div>
            <div class="notification-list" id="headerNotificationList">
                <?php if (count($allNotifications) > 0): ?>
                    <?php foreach ($allNotifications as $n): ?>
                        <div class="notification-item <?= $n['update'] === 'new' ? 'unread' : '' ?> <?= htmlspecialchars($n['type']) ?>"
                             data-id="<?= htmlspecialchars($n['id']) ?>"
                             data-update="<?= htmlspecialchars($n['update']) ?>">
                            <div class="notification-section"><?= htmlspecialchars($n['section']) ?></div>
                            <div class="notification-title"><?= htmlspecialchars($n['title'] ?? 'Notification') ?></div>
                            <div class="notification-message"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="notification-time"><?= date('M d, H:i', strtotime($n['time'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notifications">No notifications</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="notification-toast" id="notificationToast" role="button" tabindex="0" aria-label="Open new notification">
        <div class="toast-section" id="notificationToastSection">Notification</div>
        <div class="toast-title" id="notificationToastTitle">New notification</div>
        <div class="toast-message" id="notificationToastMessage"></div>
    </div>

    <div class="notification-permission-backdrop" id="notificationPermissionBackdrop">
        <div class="notification-permission-modal" role="dialog" aria-modal="true" aria-labelledby="notificationPermissionTitle">
            <div class="notification-permission-icon"><i class="fa-regular fa-bell"></i></div>
            <h3 id="notificationPermissionTitle">Allow HarvHub notifications?</h3>
            <p>
                Get an alert when an important account action or contract update happens.
                Notifications are enabled globally for your HarvHub account, not separately for each sub-account.
                Sound is also enabled when you allow notifications.
            </p>
            <div class="notification-permission-actions">
                <button type="button" class="notification-permission-allow" id="notificationPermissionAllow">Allow notifications</button>
                <button type="button" class="notification-permission-later" id="notificationPermissionLater">Not now</button>
            </div>
        </div>
    </div>

    <div class="viewport">
        <div class="loader" id="loader">
            <div class="spinner"></div>
        </div>

        <?php foreach ($tabSrc as $tab => $file): ?>
            <iframe
                id="frame-<?= $tab ?>"
                class="app-frame <?= $defaultTab === $tab ? 'active' : '' ?>"
                data-tab="<?= $tab ?>"
                data-hidden="<?= in_array($tab, $hiddenTabs, true) ? '1' : '0' ?>"
                src="<?= htmlspecialchars($file) ?>"
                title="<?= ucfirst(str_replace('_', ' ', $tab)) ?>"
                loading="eager"></iframe>
        <?php endforeach; ?>
    </div>

    <nav class="bottom-nav" id="bottomNav">
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'mydashboard' ? 'active' : '' ?>" data-tab="mydashboard">
            <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
            <span class="nav-label">Home</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'trades' ? 'active' : '' ?>" data-tab="trades">
            <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span>
            <span class="nav-label">Revenue</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'activity' ? 'active' : '' ?>" data-tab="activity">
            <span class="nav-icon"><i class="fa-solid fa-timeline"></i></span>
            <span class="nav-label">Activity</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'analytics' ? 'active' : '' ?>" data-tab="analytics">
            <span class="nav-icon"><i class="fa-solid fa-chart-column"></i></span>
            <span class="nav-label">Analytics</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'menu' ? 'active' : '' ?>" data-tab="menu">
            <span class="nav-icon"><i class="fa-solid fa-bars"></i></span>
            <span class="nav-label">Menu</span>
        </button>
    </nav>
</div>

<script>
(function () {
    var TABS_WITH_BRAND_HEADER = ['mydashboard'];
    var TABS_WITH_NAV          = ['mydashboard', 'trades', 'activity', 'analytics', 'menu'];
    var TABS_WITH_PAGE_HEADER  = ['trades', 'activity', 'analytics', 'menu'];
    var TABS_NO_CHROME         = ['vps', 'programmes', 'revenue_history'];

    var TAB_TITLES = {
        'mydashboard':'Dashboard','trades':'Daily Revenue','activity':'Activity',
        'analytics':'Analytics','menu':'Menu','revenue_history':'Revenue History',
        'profit_split':'Profit Split','vps':'VPS Hub','programmes':'Programmes',
        'disconnect_broker':'Disconnect Broker','connect_investor_broker':'Connect Broker'
    };

    window.__harvhubModalOpen = false;

    function setModalOpen(isOpen) {
        window.__harvhubModalOpen = !!isOpen;
        document.body.classList.toggle('modal-overlay-open', !!isOpen);
    }

    function applyChromeForTab(tab) {
        var showBrandHeader = TABS_WITH_BRAND_HEADER.indexOf(tab) !== -1;
        var showNav         = TABS_WITH_NAV.indexOf(tab) !== -1;
        var showPageHeader  = TABS_WITH_PAGE_HEADER.indexOf(tab) !== -1;

        if (TABS_NO_CHROME.indexOf(tab) !== -1) {
            showBrandHeader = false; showNav = false; showPageHeader = false;
        }

        document.body.classList.toggle('shell-no-header',       !showBrandHeader && !showPageHeader);
        document.body.classList.toggle('shell-no-brand-header', !showBrandHeader);
        document.body.classList.toggle('shell-no-page-header',  !showPageHeader);
        document.body.classList.toggle('shell-no-nav',          !showNav);

        var titleEl = document.getElementById('shellPageHeaderTitle');
        if (titleEl) titleEl.textContent = TAB_TITLES[tab] || 'HarvHub';
    }

    var frames = {
        mydashboard:             document.getElementById('frame-mydashboard'),
        trades:                  document.getElementById('frame-trades'),
        activity:                document.getElementById('frame-activity'),
        analytics:               document.getElementById('frame-analytics'),
        menu:                    document.getElementById('frame-menu'),
        revenue_history:         document.getElementById('frame-revenue_history'),
        profit_split:            document.getElementById('frame-profit_split'),
        vps:                     document.getElementById('frame-vps'),
        disconnect_broker:       document.getElementById('frame-disconnect_broker'),
        programmes:              document.getElementById('frame-programmes'),
        connect_investor_broker: document.getElementById('frame-connect_investor_broker')
    };
    var names = Object.keys(frames);
    var navTabNames = ['mydashboard', 'trades', 'activity', 'analytics', 'menu'];

    var FILE_TO_TAB = {
        'mydashboard.php':'mydashboard','revenue_target.php':'trades','activity.php':'activity',
        'useranalytics.php':'analytics','analytics.php':'analytics','menu.php':'menu',
        'revenue_history.php':'revenue_history','profit_split.php':'profit_split','vps.php':'vps',
        'disconnect_broker.php':'disconnect_broker','programmes.php':'programmes',
        'connect_investor_broker.php':'connect_investor_broker'
    };

    var loader = document.getElementById('loader');
    var navItems = document.querySelectorAll('.bottom-nav-item');
    var loaded = 0;
    var lastNavTab = '<?= $activeNavTab ?>';

    function pushTheme(f) {
        if (!f) return;
        try {
            f.contentWindow.postMessage({
                type: 'theme',
                dark: document.body.classList.contains('dark-mode')
            }, '*');
        } catch (e) {}
    }
    function pushAll() { names.forEach(function (n) { pushTheme(frames[n]); }); }

    function injectNavShim(f) {
        if (!f) return;
        try {
            var w = f.contentWindow;
            var d = w && w.document;
            if (!w || !d) return;
            if (w.__harvhubShimInstalled) return;
            w.__harvhubShimInstalled = true;

            d.addEventListener('click', function (ev) {
                var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
                if (!a) return;
                var href = a.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#') return;
                if (/^(mailto:|tel:|javascript:)/i.test(href)) return;
                if (/^https?:/i.test(href) && href.indexOf(window.location.origin) !== 0) return;

                var clean = href.split('?')[0].split('#')[0];
                var base = clean.substring(clean.lastIndexOf('/') + 1);

                if (base === 'investorapp.php') {
                    ev.preventDefault();
                    var m = href.match(/[?&]tab=([^&#]+)/);
                    requestSwitch(m ? decodeURIComponent(m[1]) : 'mydashboard');
                    return;
                }
                if (FILE_TO_TAB[base]) { ev.preventDefault(); requestSwitch(FILE_TO_TAB[base]); return; }
                if (base === 'index.php') { ev.preventDefault(); requestLogout(); return; }
                if (/\.php(\?|#|$)/i.test(clean) && base !== '') {
                    ev.preventDefault();
                    var guess = base.replace(/\.php$/i, '').replace(/[^a-z0-9_]/gi, '_');
                    if (frames[guess]) requestSwitch(guess);
                    else w.location.href = href;
                }
            }, true);

            try {
                var origAssign  = w.location.assign.bind(w.location);
                var origReplace = w.location.replace.bind(w.location);
                function handleNav(url) {
                    var s = String(url);
                    var clean = s.split('?')[0].split('#')[0];
                    var base = clean.substring(clean.lastIndexOf('/') + 1);
                    if (base === 'investorapp.php') {
                        var m = s.match(/[?&]tab=([^&#]+)/);
                        requestSwitch(m ? decodeURIComponent(m[1]) : 'mydashboard');
                        return true;
                    }
                    if (FILE_TO_TAB[base]) { requestSwitch(FILE_TO_TAB[base]); return true; }
                    if (base === 'index.php') { requestLogout(); return true; }
                    return false;
                }
                w.location.assign  = function (url) { if (handleNav(url)) return; return origAssign(url); };
                w.location.replace = function (url) { if (handleNav(url)) return; return origReplace(url); };
            } catch (e) {}

            try {
                f.contentWindow.postMessage({
                    type: 'theme',
                    dark: document.body.classList.contains('dark-mode')
                }, '*');
            } catch (e) {}
        } catch (e) {}
    }

    function requestSwitch(tab) { if (!frames[tab]) return; switchTab(tab); }
    function requestLogout() { window.location.href = 'investorapp.php?logout=1'; }

    function onLoad(e) {
        pushTheme(e.target);
        injectNavShim(e.target);
        loaded++;
        if (loaded >= names.length) {
            loader.classList.add('hidden');
            setTimeout(function () { if (loader && loader.parentNode) loader.remove(); }, 320);
        }
    }
    names.forEach(function (k) { frames[k].addEventListener('load', onLoad); });
    names.forEach(function (k) { injectNavShim(frames[k]); });

    setTimeout(function () {
        if (loader && !loader.classList.contains('hidden')) loader.classList.add('hidden');
    }, 12000);

    function switchTab(tab) {
        if (!frames[tab]) return;
        names.forEach(function (k) { frames[k].classList.toggle('active', k === tab); });
        if (navTabNames.indexOf(tab) !== -1) lastNavTab = tab;
        navItems.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === lastNavTab); });
        applyChromeForTab(tab);
        try {
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            history.replaceState(null, '', url);
        } catch (e) {}
        pushTheme(frames[tab]);
        setTimeout(function () { injectNavShim(frames[tab]); }, 50);
        lastScrollTop = 0; isNavHidden = false; isHeaderHidden = false;
        if (!window.__harvhubModalOpen) { showNav(); showHeader(); }
    }

    navItems.forEach(function (b) {
        b.addEventListener('click', function () { switchTab(b.dataset.tab); });
    });

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'switchTab' && frames[e.data.tab]) switchTab(e.data.tab);
        if (e.data.type === 'bodyClass') {
            if (Array.isArray(e.data.remove)) e.data.remove.forEach(function (c) { document.body.classList.remove(c); });
            if (Array.isArray(e.data.add))    e.data.add.forEach(function (c) { document.body.classList.add(c); });
        }
        if (e.data.type === 'requestTheme') {
            try {
                e.source.postMessage({
                    type: 'theme',
                    dark: document.body.classList.contains('dark-mode')
                }, '*');
            } catch (err) {}
        }
        if (e.data.type === 'themeRequest') {
            var wantDark = !!e.data.dark;
            document.body.classList.toggle('dark-mode', wantDark);
            pushAll();
        }
        if (e.data.type === 'saveLastApp') {
            try {
                fetch('menu.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: 'save_last_app=1&app=investorapp'
                }).catch(function () {});
            } catch (err) {}
        }
        if (e.data.type === 'logout') window.location.href = 'investorapp.php?logout=1';
        if (e.data.type === 'openHeaderSheet') openHeaderSheet();
        if (e.data.type === 'showSpinner') showBlockingLoader('Loading…');

        // Child iframes can ask the shell to refresh its live badges
        // (e.g. after accepting a request in mydashboard.php).
        if (e.data.type === 'harvhubRefreshShell') {
            if (typeof window.harvhubRefreshShellState === 'function') {
                window.harvhubRefreshShellState();
            }
        }

        // ------------------------------------------------------------------
        // MODAL-OVERLAY BRIDGE — SOLE authority over `modal-overlay-open`
        // ------------------------------------------------------------------
        if (e.data.type === 'harvhubModalOpen') {
            setModalOpen(true);
        }
        if (e.data.type === 'harvhubModalClose') {
            setModalOpen(false);
        }
    });

    new MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) {
            if (muts[i].attributeName === 'class') { pushAll(); break; }
        }
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

    window.addEventListener('load', function () { setTimeout(pushAll, 400); });
    window.__investorSwitch = switchTab;

    var blockingLoader = document.getElementById('shellBlockingLoader');
    var blockingLoaderText = document.getElementById('shellBlockingLoaderText');

    function showBlockingLoader(text) {
        if (blockingLoaderText) blockingLoaderText.textContent = text || 'Loading…';
        if (blockingLoader) blockingLoader.classList.add('active');
    }
    function hideBlockingLoader() {
        if (blockingLoader) blockingLoader.classList.remove('active');
    }
    window.showBlockingLoader = showBlockingLoader;
    window.hideBlockingLoader = hideBlockingLoader;

    // ---------- Header sheet ----------
    var headerSheet         = document.getElementById('headerSheet');
    var headerSheetBackdrop = document.getElementById('headerSheetBackdrop');
    var headerDropdownBtn   = document.getElementById('headerDropdownBtn');
    var sheetOpen           = false;

    function openHeaderSheet() {
        if (sheetOpen) return;
        sheetOpen = true;
        headerSheet.classList.add('active');
        headerSheetBackdrop.classList.add('active');
        document.body.classList.add('header-sheet-open');
        if (headerDropdownBtn) {
            headerDropdownBtn.classList.add('is-open');
            headerDropdownBtn.setAttribute('aria-expanded', 'true');
        }
        loadSubAccountsIntoSheet();
    }

    function closeHeaderSheet(immediate) {
        if (!sheetOpen) return;
        function finishClose() {
            sheetOpen = false;
            headerSheet.classList.remove('active');
            headerSheetBackdrop.classList.remove('active');
            document.body.classList.remove('header-sheet-open');
            if (headerDropdownBtn) {
                headerDropdownBtn.classList.remove('is-open');
                headerDropdownBtn.setAttribute('aria-expanded', 'false');
            }
        }
        if (immediate) finishClose();
        else {
            headerSheet.classList.remove('active');
            headerSheetBackdrop.classList.remove('active');
            setTimeout(finishClose, 340);
        }
    }

    function toggleHeaderSheet() { if (sheetOpen) closeHeaderSheet(); else openHeaderSheet(); }

    if (headerDropdownBtn) {
        headerDropdownBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            toggleHeaderSheet();
        });
    }
    if (headerSheetBackdrop) headerSheetBackdrop.addEventListener('click', function () { closeHeaderSheet(); });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && sheetOpen) closeHeaderSheet(); });

    // ---------- Sub account list ----------
    var sheetSubAccountsList = document.getElementById('sheetSubAccountsList');

    function renderSubAccountsList(accounts) {
        if (!sheetSubAccountsList) return;
        if (!accounts || accounts.length === 0) {
            sheetSubAccountsList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">No sub accounts</div>';
            return;
        }

        // Active first, then by id ascending — matches traderapp ordering.
        accounts.sort(function (a, b) {
            if (a.is_active && !b.is_active) return -1;
            if (!a.is_active && b.is_active) return 1;
            return (a.sub_account_id || 0) - (b.sub_account_id || 0);
        });

        var html = '';
        accounts.forEach(function (acc) {
            var label = acc.name !== '' ? acc.name : 'Unnamed';
            var initial = (acc.initial && acc.initial.length)
                ? acc.initial
                : label.charAt(0).toUpperCase();
            var activeCls = acc.is_active ? ' is-active' : '';
            var check = acc.is_active ? '<i class="fa-solid fa-check sub-check"></i>' : '';
            html += '<button type="button" class="header-sheet-sub-item' + activeCls + '" data-sub-id="' + acc.sub_account_id + '" data-sub-name="' + escapeAttr(label) + '">';
            html +=   '<span class="header-sheet-sub-avatar">' + escapeHtml(initial) + '</span>';
            html +=   '<span class="header-sheet-sub-text">';
            html +=     '<span class="sub-name">' + escapeHtml(label) + '</span>';
            html +=   '</span>';
            html +=   check;
            html += '</button>';
        });
        sheetSubAccountsList.innerHTML = html;

        sheetSubAccountsList.querySelectorAll('.header-sheet-sub-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var subId = parseInt(el.getAttribute('data-sub-id'), 10);
                var subName = el.getAttribute('data-sub-name') || '';
                var isActive = el.classList.contains('is-active');
                if (isActive) { closeHeaderSheet(); return; }

                closeHeaderSheet();
                setTimeout(function () {
                    showBlockingLoader('Switching to ' + subName + '…');
                    switchSubAccount(subId);
                }, 360);
            });
        });
    }

    function loadSubAccountsIntoSheet() {
        if (!sheetSubAccountsList) return;
        sheetSubAccountsList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">Loading…</div>';

        fetch('investorapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'get_sub_accounts=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                renderSubAccountsList(data.accounts || []);
            } else {
                sheetSubAccountsList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:#ef4444;">Failed to load</div>';
            }
        })
        .catch(function () {
            sheetSubAccountsList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:#ef4444;">Network error</div>';
        });
    }

    function switchSubAccount(subId) {
        fetch('investorapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'switch_sub_account_from_shell=1&sub_account_id=' + encodeURIComponent(subId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                window.location.href = 'investorapp.php?tab=mydashboard&switched=1';
            } else {
                hideBlockingLoader();
                alert((data && data.message) ? data.message : 'Failed to switch.');
            }
        })
        .catch(function () { hideBlockingLoader(); alert('Network error.'); });
    }

    // ---------- Developer's Hub ----------
    var developerHubBtn = document.getElementById('headerSheetDeveloperHub');
    if (developerHubBtn) {
        developerHubBtn.addEventListener('click', function () {
            closeHeaderSheet();
            setTimeout(function () {
                showBlockingLoader("Opening Developer's Hub…");
                try {
                    if (window.top && window.top !== window) window.top.location.href = 'traderapp.php';
                    else window.location.href = 'traderapp.php';
                } catch (e) { window.location.href = 'traderapp.php'; }
            }, 360);
        });
    }

    // ---------- Overview ----------
    var overviewBtn = document.getElementById('headerSheetOverview');
    if (overviewBtn) {
        overviewBtn.addEventListener('click', function () {
            closeHeaderSheet();
            setTimeout(function () {
                try { window.top.location.hash = '#mainaccount'; } catch (e) {}
            }, 360);
        });
    }

    // ---------- Create account ----------
    var createSheet      = document.getElementById('createAccountSheet');
    var createBackdrop   = document.getElementById('createAccountBackdrop');
    var createInput      = document.getElementById('createAccountInput');
    var createError      = document.getElementById('createAccountError');
    var createSubmit     = document.getElementById('createAccountSubmit');
    var createCancel     = document.getElementById('createAccountCancel');
    var createBtnTrigger = document.getElementById('headerSheetCreateAccount');
    var createOpen       = false;

    function openCreateSheet() {
        if (createOpen) return;
        createOpen = true;
        if (createError) { createError.style.display = 'none'; createError.textContent = ''; }
        if (createInput) createInput.value = '';
        createSheet.classList.add('active');
        createBackdrop.classList.add('active');
        document.body.classList.add('header-sheet-open');
        setTimeout(function () { if (createInput) createInput.focus(); }, 360);
    }
    function closeCreateSheet(cb) {
        if (!createOpen) { if (typeof cb === 'function') cb(); return; }
        createSheet.classList.remove('active');
        createBackdrop.classList.remove('active');
        document.body.classList.remove('header-sheet-open');
        setTimeout(function () { createOpen = false; if (typeof cb === 'function') cb(); }, 340);
    }

    if (createBtnTrigger) {
        createBtnTrigger.addEventListener('click', function () {
            closeHeaderSheet();
            setTimeout(openCreateSheet, 360);
        });
    }
    if (createBackdrop) createBackdrop.addEventListener('click', function () { closeCreateSheet(); });
    if (createCancel)   createCancel.addEventListener('click',   function () { closeCreateSheet(); });

    if (createSubmit) {
        createSubmit.addEventListener('click', function () {
            var raw = (createInput && createInput.value) ? createInput.value : '';
            var normalized = String(raw)
                .replace(/[^A-Za-z0-9 ]+/g, '')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase()
                .substring(0, 20);

            if (createError) { createError.style.display = 'none'; createError.textContent = ''; }

            if (normalized === '') {
                createError.textContent = 'Please enter an account name.';
                createError.style.display = 'block';
                return;
            }

            createSubmit.disabled = true;
            createSubmit.textContent = 'Creating…';

            fetch('investorapp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: 'create_sub_account_from_shell=1&account_name=' + encodeURIComponent(normalized)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    closeCreateSheet(function () {
                        showBlockingLoader('Creating ' + (data.account_name || normalized) + '…');
                        switchSubAccount(data.sub_account_id);
                    });
                } else {
                    createSubmit.disabled = false;
                    createSubmit.textContent = 'Create account';
                    createError.textContent = (data && data.errors ? data.errors.join(' ') : 'Failed to create account.');
                    createError.style.display = 'block';
                }
            })
            .catch(function () {
                createSubmit.disabled = false;
                createSubmit.textContent = 'Create account';
                createError.textContent = 'Network error. Please try again.';
                createError.style.display = 'block';
            });
        });
    }

    // ---------- Sign out ----------
    var confirmSheet    = document.getElementById('confirmSignoutSheet');
    var confirmBackdrop = document.getElementById('confirmSignoutBackdrop');
    var confirmYes      = document.getElementById('confirmSignoutYes');
    var confirmNo       = document.getElementById('confirmSignoutNo');
    var logoutTrigger   = document.getElementById('headerSheetLogout');
    var confirmOpen     = false;

    function openConfirmSheet() {
        if (confirmOpen) return;
        confirmOpen = true;
        confirmSheet.classList.add('active');
        confirmBackdrop.classList.add('active');
        document.body.classList.add('header-sheet-open');
    }
    function closeConfirmSheet(cb) {
        if (!confirmOpen) { if (typeof cb === 'function') cb(); return; }
        confirmSheet.classList.remove('active');
        confirmBackdrop.classList.remove('active');
        document.body.classList.remove('header-sheet-open');
        setTimeout(function () { confirmOpen = false; if (typeof cb === 'function') cb(); }, 340);
    }

    if (logoutTrigger) {
        logoutTrigger.addEventListener('click', function () {
            closeHeaderSheet();
            setTimeout(openConfirmSheet, 360);
        });
    }
    if (confirmBackdrop) confirmBackdrop.addEventListener('click', function () { closeConfirmSheet(); });
    if (confirmNo) confirmNo.addEventListener('click', function () { closeConfirmSheet(); });

    if (confirmYes) {
        confirmYes.addEventListener('click', function () {
            try {
                fetch('menu.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: 'save_last_app=1&app=investorapp'
                }).catch(function () {});
            } catch (e) {}
            showBlockingLoader('Signing out…');
            setTimeout(function () {
                window.location.href = 'investorapp.php?logout=1';
            }, 250);
        });
    }

    window.openHeaderSheet = openHeaderSheet;
    window.closeHeaderSheet = closeHeaderSheet;

    // ---------- Global notification system ----------
    (function () {
        var panel = document.getElementById('headerNotificationPanel');
        var bell = document.getElementById('headerBell');
        var closeBtn = document.getElementById('closeNotificationsBtn');
        var list = document.getElementById('headerNotificationList');
        var badge = document.getElementById('headerNotificationBadge');
        var toast = document.getElementById('notificationToast');
        var toastSection = document.getElementById('notificationToastSection');
        var toastTitle = document.getElementById('notificationToastTitle');
        var toastMessage = document.getElementById('notificationToastMessage');
        var permissionBackdrop = document.getElementById('notificationPermissionBackdrop');
        var permissionAllow = document.getElementById('notificationPermissionAllow');
        var permissionLater = document.getElementById('notificationPermissionLater');

        var open = false;
        var lastUnreadCount = <?= (int)$initialUnreadCount ?>;
        var firstPoll = true;
        var toastTimer = null;
        var latestNotification = null;

        function post(body) {
            return fetch('investorapp.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function formatDate(dateString) {
            var date = new Date(String(dateString).replace(' ', 'T'));
            if (isNaN(date.getTime())) return '';
            var now = new Date();
            var diffMs = now - date;
            var m = Math.floor(diffMs / 60000);
            var h = Math.floor(diffMs / 3600000);
            var d = Math.floor(diffMs / 86400000);

            if (m < 1) return 'Just now';
            if (m < 60) return m + ' min ago';
            if (h < 24) return h + ' hour' + (h > 1 ? 's' : '') + ' ago';
            if (d < 7) return d + ' day' + (d > 1 ? 's' : '') + ' ago';

            return date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric'
            });
        }

        function renderList(items) {
            if (!items || !items.length) {
                list.innerHTML = '<div class="empty-notifications">No notifications</div>';
                return;
            }

            var html = '';

            items.forEach(function (n) {
                var unread = n.update === 'new' ? 'unread' : '';
                var typeCls = escapeHtml(n.type || 'info');

                html += '<div class="notification-item ' + unread + ' ' + typeCls + '"'
                    + ' data-id="' + escapeHtml(n.id) + '"'
                    + ' data-update="' + escapeHtml(n.update) + '"'
                    + ' data-action-tab="' + escapeHtml(n.action_tab || '') + '">'
                    + '<div class="notification-section">' + escapeHtml(n.section || 'General') + '</div>'
                    + '<div class="notification-title">' + escapeHtml(n.title || 'Notification') + '</div>'
                    + '<div class="notification-message">' + escapeHtml(n.message || '') + '</div>'
                    + '<div class="notification-time">' + escapeHtml(formatDate(n.time)) + '</div>'
                    + '</div>';
            });

            list.innerHTML = html;

            list.querySelectorAll('.notification-item').forEach(function (item) {
                item.addEventListener('click', function () {
                    var tab = item.getAttribute('data-action-tab') || '';
                    closePanel(false);

                    if (tab) {
                        setTimeout(function () {
                            switchTab(tab);
                        }, 80);
                    }
                });
            });
        }

        function updateBadge(count) {
            count = parseInt(count, 10) || 0;

            if (!badge) return;
            var prev = parseInt(badge.textContent, 10) || 0;

            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.style.display = 'flex';
                if (count > prev) {
                    badge.classList.remove('badge-bump');
                    void badge.offsetWidth;
                    badge.classList.add('badge-bump');
                }
            } else {
                badge.textContent = '';
                badge.style.display = 'none';
            }
        }

        function refresh() {
            return post('get_notifications_list=1')
                .then(function (data) {
                    if (!data || !data.success) return;

                    renderList(data.notifications || []);
                    updateBadge(data.unread_count || 0);
                    lastUnreadCount = parseInt(data.unread_count, 10) || 0;

                    if (data.notifications && data.notifications.length) {
                        latestNotification = data.notifications[0];
                    }
                })
                .catch(function () {});
        }

        function markRead() {
            return post('mark_notifications_read=1')
                .then(function (data) {
                    if (data && data.success) {
                        list.querySelectorAll('.notification-item.unread').forEach(function (item) {
                            item.classList.remove('unread');
                        });
                        updateBadge(data.unread_count || 0);
                        lastUnreadCount = parseInt(data.unread_count, 10) || 0;
                    }
                })
                .catch(function () {});
        }

        function openPanel() {
            if (!panel) return;
            panel.classList.add('active');
            open = true;
            refresh();
        }

        function closePanel(markAsReadNow) {
            if (!panel) return;
            panel.classList.remove('active');
            open = false;
            if (markAsReadNow !== false) markRead();
        }

        function playNotificationSound() {
            post('get_notification_preferences=1')
                .then(function (data) {
                    if (!data || !data.success) return;

                    var enabled = !!parseInt(
                        data.preferences.notification_sound_enabled,
                        10
                    );

                    if (!enabled || !window.AudioContext && !window.webkitAudioContext) return;

                    var AudioCtx = window.AudioContext || window.webkitAudioContext;
                    var ctx = new AudioCtx();
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.12);

                    gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    osc.stop(ctx.currentTime + 0.19);

                    setTimeout(function () {
                        try { ctx.close(); } catch (e) {}
                    }, 300);
                })
                .catch(function () {});
        }

        function showToast(notification) {
            if (!toast || !notification) return;

            latestNotification = notification;
            toastSection.textContent = notification.section || 'Notification';
            toastTitle.textContent = notification.title || 'New notification';
            toastMessage.textContent = notification.message || '';

            toast.classList.add('active');

            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = setTimeout(function () {
                toast.classList.remove('active');
            }, 6500);

            post('get_notification_preferences=1')
                .then(function (data) {
                    if (!data || !data.success) return;

                    var prefs = data.preferences || {};
                    var soundEnabled = !!parseInt(prefs.notification_sound_enabled, 10);
                    var browserEnabled = !!parseInt(prefs.browser_notifications_enabled, 10);

                    if (soundEnabled) playNotificationSound();

                    if (
                        browserEnabled &&
                        'Notification' in window &&
                        Notification.permission === 'granted'
                    ) {
                        try {
                            var browserNotification = new Notification(
                                notification.title || 'HarvHub notification',
                                {
                                    body: notification.message || '',
                                    tag: 'harvhub-' + (notification.id || Date.now()),
                                    silent: !soundEnabled
                                }
                            );

                            browserNotification.onclick = function () {
                                window.focus();
                                openPanel();
                                try { browserNotification.close(); } catch (e) {}
                            };
                        } catch (e) {}
                    }
                })
                .catch(function () {});
        }

        function checkPermissionPrompt() {
            post('get_notification_preferences=1')
                .then(function (data) {
                    if (!data || !data.success) return;

                    var prefs = data.preferences || {};
                    var seen = parseInt(prefs.permission_prompt_seen, 10) === 1;

                    if (!seen && permissionBackdrop) {
                        permissionBackdrop.classList.add('active');
                    }
                })
                .catch(function () {});
        }

        function savePrefs(browserEnabled, soundEnabled, promptSeen) {
            var body =
                'save_notification_preferences=1'
                + '&browser_notifications_enabled=' + (browserEnabled ? '1' : '0')
                + '&notification_sound_enabled=' + (soundEnabled ? '1' : '0')
                + '&permission_prompt_seen=' + (promptSeen ? '1' : '0');

            return post(body).catch(function () {});
        }

        function allowNotifications() {
            var browserEnabled = false;

            if ('Notification' in window) {
                return Notification.requestPermission()
                    .then(function (permission) {
                        browserEnabled = permission === 'granted';
                        return savePrefs(browserEnabled, true, true);
                    })
                    .then(function () {
                        if (permissionBackdrop) permissionBackdrop.classList.remove('active');
                        try { playNotificationSound(); } catch (e) {}
                    })
                    .catch(function () {
                        return savePrefs(false, true, true).then(function () {
                            if (permissionBackdrop) permissionBackdrop.classList.remove('active');
                        });
                    });
            }

            return savePrefs(false, true, true).then(function () {
                if (permissionBackdrop) permissionBackdrop.classList.remove('active');
            });
        }

        function declineNotifications() {
            savePrefs(false, false, true);
            if (permissionBackdrop) permissionBackdrop.classList.remove('active');
        }

        function poll() {
            post('get_notifications_list=1')
                .then(function (data) {
                    if (!data || !data.success) return;

                    var count = parseInt(data.unread_count, 10) || 0;

                    if (!firstPoll && count > lastUnreadCount) {
                        var newItem = (data.notifications || []).find(function (n) {
                            return n.update === 'new';
                        });

                        if (newItem) showToast(newItem);
                    }

                    firstPoll = false;
                    lastUnreadCount = count;
                    updateBadge(count);

                    if (open) renderList(data.notifications || []);
                })
                .catch(function () {});
        }

        if (bell) {
            bell.addEventListener('click', function () {
                if (open) closePanel(true);
                else openPanel();
            });

            bell.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (open) closePanel(true);
                    else openPanel();
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                closePanel(true);
            });
        }

        document.addEventListener('click', function (ev) {
            if (!open || !panel) return;
            if (panel.contains(ev.target)) return;
            if (bell && bell.contains(ev.target)) return;
            closePanel(true);
        });

        if (toast) {
            toast.addEventListener('click', function () {
                toast.classList.remove('active');
                openPanel();
            });
            toast.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toast.classList.remove('active');
                    openPanel();
                }
            });
        }

        if (permissionAllow) {
            permissionAllow.addEventListener('click', allowNotifications);
        }

        if (permissionLater) {
            permissionLater.addEventListener('click', declineNotifications);
        }

        refresh();
        checkPermissionPrompt();
        setInterval(poll, 3000);

        window.harvhubOpenNotifications = openPanel;
        window.harvhubRefreshNotifications = refresh;
    })();

    // ---------- Live shell state (badges, sub-account list) ----------
    // Lightweight poller that keeps the header bell and the switcher list
    // in sync without a full page reload. Runs on all shell pages.
    (function () {
        var interval = null;
        var running = false;
        var failCount = 0;
        var BASE_INTERVAL = 4000;   // 4s
        var MAX_INTERVAL  = 20000;  // 20s on error backoff
        var lastState = null;

        function post(body) {
            return fetch('investorapp.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body,
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); });
        }

        function applyState(data) {
            if (!data || !data.success) return;

            // Update header bell badge count
            var badge = document.getElementById('headerNotificationBadge');
            if (badge) {
                var count = parseInt(data.unread_count, 10) || 0;
                var prev = parseInt(badge.textContent, 10) || 0;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.style.display = 'flex';
                    if (count > prev) {
                        badge.classList.remove('badge-bump');
                        void badge.offsetWidth;
                        badge.classList.add('badge-bump');
                    }
                } else {
                    badge.textContent = '';
                    badge.style.display = 'none';
                }
            }

            // If the switcher sheet is open, refresh the list with new counts
            if (sheetOpen && sheetSubAccountsList && Array.isArray(data.sub_accounts)) {
                // Patch in place — only rebuild if the set/order changed.
                var currentIds = Array.prototype.map.call(
                    sheetSubAccountsList.querySelectorAll('.header-sheet-sub-item'),
                    function (el) { return parseInt(el.getAttribute('data-sub-id'), 10) || 0; }
                );

                // Active first
                var nextIds = data.sub_accounts
                    .slice()
                    .sort(function (a, b) {
                        if (a.is_active && !b.is_active) return -1;
                        if (!a.is_active && b.is_active) return 1;
                        return (a.sub_account_id || 0) - (b.sub_account_id || 0);
                    })
                    .map(function (a) { return a.sub_account_id || 0; });

                var same =
                    currentIds.length === nextIds.length &&
                    currentIds.every(function (v, i) { return v === nextIds[i]; });

                if (!same) {
                    renderSubAccountsList(data.sub_accounts);
                } else {
                    // Order unchanged — just update active highlight (in case
                    // server-side session switched it) without a full rebuild.
                    sheetSubAccountsList.querySelectorAll('.header-sheet-sub-item').forEach(function (el) {
                        var sid = parseInt(el.getAttribute('data-sub-id'), 10) || 0;
                        var item = data.sub_accounts.find(function (a) { return (a.sub_account_id || 0) === sid; });
                        if (!item) return;
                        el.classList.toggle('is-active', !!item.is_active);
                        var check = el.querySelector('.sub-check');
                        if (item.is_active && !check) {
                            var icon = document.createElement('i');
                            icon.className = 'fa-solid fa-check sub-check';
                            el.appendChild(icon);
                        } else if (!item.is_active && check) {
                            check.remove();
                        }
                    });
                }
            }

            lastState = data;
        }

        function fetchState() {
            if (!running) return;
            post('poll_shell_state=1')
                .then(function (d) {
                    if (d && d.success) {
                        failCount = 0;
                        applyState(d);
                    } else {
                        failCount++;
                    }
                    schedule();
                })
                .catch(function () {
                    failCount++;
                    schedule();
                });
        }

        function schedule() {
            if (!running) return;
            if (interval) clearTimeout(interval);
            var delay = BASE_INTERVAL;
            if (failCount === 1) delay = 6000;
            else if (failCount === 2) delay = 10000;
            else if (failCount >= 3) delay = MAX_INTERVAL;
            interval = setTimeout(fetchState, delay);
        }

        function start() {
            running = true;
            failCount = 0;
            if (interval) clearTimeout(interval);
            fetchState();
        }

        function stop() {
            running = false;
            if (interval) { clearTimeout(interval); interval = null; }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stop();
            else start();
        });
        window.addEventListener('focus', function () {
            if (running) fetchState();
        });
        window.addEventListener('beforeunload', function () { stop(); });

        // Public hook so child iframes can request an immediate refresh
        window.harvhubRefreshShellState = function () {
            fetchState();
        };

        start();
    })();

    // ---------- Scroll-aware header + nav ----------
    var bottomNav   = document.querySelector('.bottom-nav');
    var pageHeader  = document.getElementById('shellPageHeader');
    var lastScrollTop = 0;
    var isNavHidden    = false;
    var isHeaderHidden = false;
    var raf = null;

    function navAllowed() {
        if (window.__harvhubModalOpen) return false;
        if (document.body.classList.contains('modal-overlay-open')) return false;
        if (document.body.classList.contains('shell-no-nav')) return false;
        if (document.body.classList.contains('profile-page-open')) return false;
        if (document.body.classList.contains('header-sheet-open')) return false;
        return true;
    }
    function headerAllowed() {
        if (window.__harvhubModalOpen) return false;
        if (document.body.classList.contains('modal-overlay-open')) return false;
        if (document.body.classList.contains('shell-no-page-header')) return false;
        if (document.body.classList.contains('profile-page-open')) return false;
        if (document.body.classList.contains('header-sheet-open')) return false;
        return true;
    }

    function getActiveFrame() { return document.querySelector('.app-frame.active'); }

    function getST() {
        var f = getActiveFrame(); if (!f) return 0;
        try {
            var w = f.contentWindow;
            if (w && w.document) return w.pageYOffset || w.document.documentElement.scrollTop || w.document.body.scrollTop || 0;
        } catch (e) {}
        return 0;
    }
    function getDH() {
        var f = getActiveFrame(); if (!f) return 0;
        try {
            var w = f.contentWindow;
            if (w && w.document) {
                var d = w.document;
                return Math.max(d.body ? d.body.scrollHeight : 0, d.documentElement ? d.documentElement.scrollHeight : 0);
            }
        } catch (e) {}
        return 0;
    }
    function getWH() {
        var f = getActiveFrame(); if (!f) return 0;
        try { var w = f.contentWindow; return w ? (w.innerHeight || 0) : 0; } catch (e) {}
        return 0;
    }

    function showNav() {
        if (!bottomNav || !navAllowed()) return;
        bottomNav.style.transform = 'translateX(-50%) translateY(0)';
        bottomNav.style.opacity = '1';
        bottomNav.style.pointerEvents = 'all';
        isNavHidden = false;
    }
    function hideNav() {
        if (!bottomNav || !navAllowed()) return;
        bottomNav.style.transform = 'translateX(-50%) translateY(100px)';
        bottomNav.style.opacity = '0';
        bottomNav.style.pointerEvents = 'none';
        isNavHidden = true;
    }
    function showHeader() {
        if (!pageHeader || !headerAllowed()) return;
        pageHeader.classList.remove('is-hidden');
        isHeaderHidden = false;
    }
    function hideHeader() {
        if (!pageHeader || !headerAllowed()) return;
        pageHeader.classList.add('is-hidden');
        isHeaderHidden = true;
    }

    function handle() {
        if (window.__harvhubModalOpen) return;
        var cur = getST();
        var atBottom = (cur + getWH() >= getDH() - 5);
        if (cur > lastScrollTop + 1) {
            if (!isNavHidden && !atBottom && navAllowed()) hideNav();
            if (!isHeaderHidden && !atBottom && headerAllowed()) hideHeader();
        } else if (cur < lastScrollTop - 1) {
            if (isNavHidden && navAllowed()) showNav();
            if (isHeaderHidden && headerAllowed()) showHeader();
        }
        if (cur <= 0) { showNav(); showHeader(); }
        if (atBottom) { if (isNavHidden) showNav(); if (isHeaderHidden) showHeader(); }
        lastScrollTop = cur <= 0 ? 0 : cur;
    }
    function throttle() { if (raf) cancelAnimationFrame(raf); raf = requestAnimationFrame(handle); }

    function attachScrollListeners() {
        document.querySelectorAll('.app-frame').forEach(function (f) {
            function a() {
                try {
                    var w = f.contentWindow;
                    if (w && w.document) {
                        w.removeEventListener('scroll', throttle);
                        w.addEventListener('scroll', throttle, { passive: true });
                    }
                } catch (e) {}
            }
            f.addEventListener('load', a);
            a();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { attachScrollListeners(); if (!window.__harvhubModalOpen) { showNav(); showHeader(); } });
    } else {
        attachScrollListeners(); if (!window.__harvhubModalOpen) { showNav(); showHeader(); }
    }

    var _origSwitch = window.__investorSwitch;
    if (typeof _origSwitch === 'function') {
        window.__investorSwitch = function (t) {
            _origSwitch(t);
            setTimeout(attachScrollListeners, 100);
            if (!window.__harvhubModalOpen) {
                lastScrollTop = 0; isNavHidden = false; isHeaderHidden = false;
                showNav(); showHeader();
            }
        };
    }

    // ---------- Takeover watcher (profile page only — NOT modal) ----------
    (function () {
        var lastTakeoverState = false;
        function enterTakeoverMode() {
            if (bottomNav) {
                bottomNav.style.transform = 'translateX(-50%) translateY(100px)';
                bottomNav.style.opacity = '0';
                bottomNav.style.pointerEvents = 'none';
            }
            isNavHidden = true;
            if (pageHeader) pageHeader.classList.add('is-hidden');
            isHeaderHidden = true;
        }
        function exitTakeoverMode() {
            if (window.__harvhubModalOpen) return;
            lastScrollTop = 0; isNavHidden = false; isHeaderHidden = false;
            showNav(); showHeader();
        }
        setInterval(function () {
            var shellHasProfile = document.body.classList.contains('profile-page-open');

            var frameHasProfile = false;
            try {
                var active = document.querySelector('.app-frame.active');
                if (active && active.contentDocument && active.contentDocument.body) {
                    var inner = active.contentDocument.body;
                    frameHasProfile = inner.classList.contains('profile-page-open');
                }
            } catch (e) {}

            var profileOpen = shellHasProfile || frameHasProfile;

            if (profileOpen && !shellHasProfile) document.body.classList.add('profile-page-open');
            else if (!profileOpen && shellHasProfile) document.body.classList.remove('profile-page-open');

            if (profileOpen === lastTakeoverState) return;
            lastTakeoverState = profileOpen;
            if (profileOpen) enterTakeoverMode(); else exitTakeoverMode();
        }, 100);
    })();

    applyChromeForTab('<?= htmlspecialchars($defaultTab, ENT_QUOTES) ?>');
    if (!window.__harvhubModalOpen) { showNav(); showHeader(); }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }
    function escapeAttr(text) { return escapeHtml(text).replace(/"/g, '&quot;'); }

})();

// ---------- Body class watcher ----------
(function () {
    var WATCHED = [
        'page-connect_investor_broker','page-revenue_history','page-profit_split','page-vps',
        'page-disconnect_broker','page-programmes','profile-page-open',
        'page-verify_code','page-forgot_password','prog-detail-view-open'
    ];
    setInterval(function () {
        var active = document.querySelector('.app-frame.active');
        if (!active) return;
        try {
            var inner = active.contentDocument && active.contentDocument.body;
            if (!inner) return;
            WATCHED.forEach(function (c) {
                if (inner.classList.contains(c)) document.body.classList.add(c);
                else document.body.classList.remove(c);
            });
        } catch (e) {}
    }, 250);
})();
</script>
</body>
</html>