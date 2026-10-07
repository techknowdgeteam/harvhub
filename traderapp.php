<?php
// traderapp.php — Trader workspace shell (programme-aware)
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

if (isset($_GET['logout'])) {
    if (isset($_SESSION['user_email'])) {
        try {
            $logoutEmail = strtolower($_SESSION['user_email']);
            $activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
            if ($activeProgrammeId > 0) {
                $upd = $pdo->prepare("UPDATE programme SET last_programme_id = ? WHERE id = ?");
                $upd->execute([$activeProgrammeId, $activeProgrammeId]);
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
    header("Location: index.php?role=developer");
    exit;
}

$email = strtolower($_SESSION['user_email']);
$activeProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);

$programme = null;
if ($activeProgrammeId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND LOWER((SELECT email FROM harvhub WHERE harvhub.id = programme.userid LIMIT 1)) = ? LIMIT 1");
    $stmt->execute([$activeProgrammeId, $email]);
    $programme = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$programme) {
    $lastQ = $pdo->prepare("SELECT id FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $lastQ->execute([$email]);
    $lastRow = $lastQ->fetch(PDO::FETCH_ASSOC);
    if ($lastRow) {
        $userId = (int)$lastRow['id'];
        $q = $pdo->prepare("SELECT * FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
        $q->execute([$userId]);
        $programme = $q->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$programme) {
    $userQ = $pdo->prepare("SELECT id FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $userQ->execute([$email]);
    $userRow = $userQ->fetch(PDO::FETCH_ASSOC);
    if ($userRow) {
        $userId = (int)$userRow['id'];
        try {
            $ins = $pdo->prepare("INSERT INTO programme (userid, program_name, visibility, advertisement) VALUES (?, 'My Programme', 0, 0)");
            $ins->execute([$userId]);
            $newId = (int)$pdo->lastInsertId();
            $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? LIMIT 1");
            $q->execute([$newId]);
            $programme = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
    }
}

if (!$programme) {
    unset($_SESSION['user_email'], $_SESSION['auth_role']);
    header("Location: index.php?role=developer");
    exit;
}

$devQ = $pdo->prepare("SELECT * FROM harvhub WHERE id = ? LIMIT 1");
$devQ->execute([(int)$programme['userid']]);
$user = $devQ->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    unset($_SESSION['user_email'], $_SESSION['auth_role']);
    header("Location: index.php?role=developer");
    exit;
}

$userId             = (int)$user['id'];
$activeProgrammeId  = (int)$programme['id'];
$activeProgrammeName= trim((string)($programme['program_name'] ?? ''));
$darkMode           = !empty($user['dark_mode']);

$_SESSION['selected_programme_id'] = $activeProgrammeId;
$_SESSION['user_email'] = strtolower($user['email']);

if (!function_exists('normalizeProgrammeName')) {
    function normalizeProgrammeName($name) {
        $name = preg_replace('/[^A-Za-z0-9 ]+/', '', (string)$name);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = strtolower(trim($name));
        return substr($name, 0, 255);
    }
}

// ---- AJAX ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['get_programmes'])) {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $q = $pdo->prepare("
                SELECT p.id, p.program_name, p.visibility, p.advertisement
                FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE LOWER(h.email) = ?
                ORDER BY p.id ASC
            ");
            $q->execute([$email]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);

            $list = [];
            foreach ($rows as $r) {
                $pid = (int)$r['id'];
                $name = (string)($r['program_name'] ?? '');
                $initial = strtoupper(substr($name !== '' ? $name : 'U', 0, 1));

                // Per-programme counts
                $notifCount = 0;
                $reqCount = 0;
                try {
                    $nq = $pdo->prepare("
                        SELECT COUNT(*) FROM notifications
                        WHERE LOWER(user_email) = ?
                          AND (sub_account_id = ? OR sub_account_id IS NULL)
                          AND is_read = 0
                    ");
                    $nq->execute([$email, $pid]);
                    $notifCount = (int)$nq->fetchColumn();
                } catch (Throwable $e) {}
                try {
                    $rq = $pdo->prepare("
                        SELECT COUNT(*) FROM programme_investment_requestors
                        WHERE developerid = ? AND programme_id = ? AND request_status = 'pending'
                    ");
                    $rq->execute([$userId, $pid]);
                    $reqCount = (int)$rq->fetchColumn();
                } catch (Throwable $e) {}

                $list[] = [
                    'id'             => $pid,
                    'name'           => $name,
                    'initial'        => $initial,
                    'is_active'      => ($pid === $activeProgrammeId),
                    'advertised'     => ((int)$r['advertisement'] === 1),
                    'notification_count' => $notifCount,
                    'request_count'  => $reqCount,
                ];
            }

            // Sort: active first, then by id ascending
            usort($list, function($a, $b) {
                if ($a['is_active'] && !$b['is_active']) return -1;
                if (!$a['is_active'] && $b['is_active']) return 1;
                return $a['id'] - $b['id'];
            });

            echo json_encode([
                'success'          => true,
                'active_programme_name' => $activeProgrammeName,
                'active_programme_id'   => $activeProgrammeId,
                'programmes'       => $list
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load programmes.']);
        }
        exit;
    }

    // -------- LIVE SHELL STATE POLL --------
    // Returns everything the shell needs to stay in sync without a reload:
    //  - the current programme's unread notification count (bell badge)
    //  - per-programme unread counts + request counts (switcher list)
    //  - global pending investment requests count (for cross-programme badge)
    if (isset($_POST['poll_shell_state'])) {
        header('Content-Type: application/json; charset=utf-8');

        $unreadCurrent = 0;
        try {
            $unreadCurrent = getNotificationUnreadCount($pdo, $email, $activeProgrammeId);
        } catch (Throwable $e) {}

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

        // Per-programme maps
        $notifMap = [];
        $reqMap = [];
        try {
            $q = $pdo->prepare("
                SELECT p.id AS pid, p.program_name
                FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE LOWER(h.email) = ?
                ORDER BY p.id ASC
            ");
            $q->execute([$email]);
            $progs = $q->fetchAll(PDO::FETCH_ASSOC);

            foreach ($progs as $p) {
                $pid = (int)$p['pid'];
                $notifMap[$pid] = 0;
                $reqMap[$pid] = 0;
                try {
                    $nq = $pdo->prepare("
                        SELECT COUNT(*) FROM notifications
                        WHERE LOWER(user_email) = ?
                          AND (sub_account_id = ? OR sub_account_id IS NULL)
                          AND is_read = 0
                    ");
                    $nq->execute([$email, $pid]);
                    $notifMap[$pid] = (int)$nq->fetchColumn();
                } catch (Throwable $e) {}
                try {
                    $rq = $pdo->prepare("
                        SELECT COUNT(*) FROM programme_investment_requestors
                        WHERE developerid = ? AND programme_id = ? AND request_status = 'pending'
                    ");
                    $rq->execute([$userId, $pid]);
                    $reqMap[$pid] = (int)$rq->fetchColumn();
                } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {}

        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCurrent,
            'global_incoming_request_count' => $globalIncoming,
            'notif_by_programme' => $notifMap,
            'request_by_programme' => $reqMap,
        ]);
        exit;
    }

    if (isset($_POST['create_programme_from_shell'])) {
        header('Content-Type: application/json; charset=utf-8');
        $rawName = (string)($_POST['programme_name'] ?? '');
        $normalized = normalizeProgrammeName($rawName);
        if ($normalized === '') {
            echo json_encode(['success' => false, 'errors' => ['Programme name is required.']]);
            exit;
        }
        try {
            $ins = $pdo->prepare("INSERT INTO programme (userid, program_name, visibility, advertisement) VALUES (?, ?, 0, 0)");
            $ins->execute([$userId, $normalized]);
            $newId = (int)$pdo->lastInsertId();
            if ($newId <= 0) throw new Exception('Insert failed');
            echo json_encode([
                'success'        => true,
                'message'        => 'Programme created.',
                'programme_id'   => $newId,
                'programme_name' => $normalized
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'errors' => ['Failed to create programme: ' . $e->getMessage()]]);
        }
        exit;
    }

    if (isset($_POST['switch_programme_from_shell'])) {
        header('Content-Type: application/json; charset=utf-8');
        $targetId = (int)($_POST['programme_id'] ?? 0);
        if ($targetId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }
        try {
            $q = $pdo->prepare("
                SELECT p.* FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE p.id = ? AND LOWER(h.email) = ?
                LIMIT 1
            ");
            $q->execute([$targetId, $email]);
            $target = $q->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                echo json_encode(['success' => false, 'message' => 'Programme not found.']);
                exit;
            }
            $_SESSION['selected_programme_id'] = (int)$target['id'];
            $_SESSION['user_email'] = strtolower($email);
            echo json_encode([
                'success'          => true,
                'message'          => 'Switched.',
                'programme_id'     => (int)$target['id'],
                'programme_name'   => $target['program_name']
            ]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to switch.']);
        }
        exit;
    }

    if (isset($_POST['mark_notifications_read'])) {
        header('Content-Type: application/json; charset=utf-8');
        $ok = markContractNotificationsRead($pdo, $email, $activeProgrammeId);
        echo json_encode([
            'success' => $ok,
            'unread_count' => getNotificationUnreadCount($pdo, $email, $activeProgrammeId)
        ]);
        exit;
    }

    if (isset($_POST['get_notifications_list'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'notifications' => getContractNotifications($pdo, $email, $activeProgrammeId, 100),
            'unread_count' => getNotificationUnreadCount($pdo, $email, $activeProgrammeId)
        ]);
        exit;
    }

    if (isset($_POST['check_new_notifications'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'unread_count' => getNotificationUnreadCount($pdo, $email, $activeProgrammeId)
        ]);
        exit;
    }

    if (isset($_POST['get_notification_preferences'])) {
        header('Content-Type: application/json; charset=utf-8');
        $prefs = getNotificationPreferences($pdo, $email, $userId);
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
        $browser = isset($_POST['browser_notifications_enabled']) ? (int)$_POST['browser_notifications_enabled'] : null;
        $sound = isset($_POST['notification_sound_enabled']) ? (int)$_POST['notification_sound_enabled'] : null;
        $seen = isset($_POST['permission_prompt_seen']) ? (int)$_POST['permission_prompt_seen'] : null;
        $ok = saveNotificationPreferences($pdo, $email, $userId, $browser, $sound, $seen);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if (isset($_POST['toggle_dark_mode_ajax'])) {
        header('Content-Type: application/json');
        $v = isset($_POST['dark_mode_checkbox']) ? (int)$_POST['dark_mode_checkbox'] : 0;
        $u = $pdo->prepare("UPDATE harvhub SET dark_mode = ? WHERE LOWER(email) = ?");
        $u->execute([$v, $email]);
        echo json_encode(['success' => true, 'dark_mode' => $v]);
        exit;
    }
}

$initialUnreadCount = getNotificationUnreadCount($pdo, $email, $activeProgrammeId);
$allNotifications = getContractNotifications($pdo, $email, $activeProgrammeId, 100);

// ---- Tabs ----
$navTabs = ['signals', 'training', 'analytics', 'menu'];
$defaultTab = $_GET['tab'] ?? 'signals';

$tabSrc = [
    'signals'                  => 'signals_dashboard.php',
    'training'                 => 'programme_training.php',
    'analytics'                => 'programme_analytics.php',
    'vps'                      => 'programme_vps.php',
    'menu'                     => 'trader_menu.php',
    'connect_trader_broker'    => 'connect_trader_broker.php',
    'disconnect_trader_broker' => 'disconnect_trader_broker.php',
];

if (!array_key_exists($defaultTab, $tabSrc)) $defaultTab = 'signals';

$hiddenTabs = ['vps', 'connect_trader_broker', 'disconnect_trader_broker'];
$activeNavTab = in_array($defaultTab, $navTabs, true) ? $defaultTab : 'menu';

$tabsWithBrandHeader = ['signals'];
$tabsWithNav         = ['signals', 'training', 'analytics', 'menu'];
$tabsWithPageHeader  = ['training', 'analytics', 'menu'];
$tabsWithNoChrome    = ['vps', 'connect_trader_broker', 'disconnect_trader_broker'];

$tabTitles = [
    'signals'                  => 'Signals Dashboard',
    'training'                 => 'Programme Training',
    'analytics'                => 'Programme Analytics',
    'vps'                      => 'Trader VPS',
    'menu'                     => 'Menu',
    'connect_trader_broker'    => 'Connect Broker',
    'disconnect_trader_broker' => 'Disconnect Broker',
];

$showBrandHeader = in_array($defaultTab, $tabsWithBrandHeader, true);
$showNav         = in_array($defaultTab, $tabsWithNav, true);
$showPageHeader  = in_array($defaultTab, $tabsWithPageHeader, true);

if (in_array($defaultTab, $tabsWithNoChrome, true)) {
    $showBrandHeader = false;
    $showNav         = false;
    $showPageHeader  = false;
}

$takeoverTabs = ['vps', 'connect_trader_broker', 'disconnect_trader_broker'];
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
<title>HarvHub Trader</title>
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

    body.page-vps .harvhub-header-top,
    body.page-vps .shell-page-header,
    body.page-vps .bottom-nav,
    body.page-connect_trader_broker .harvhub-header-top,
    body.page-connect_trader_broker .shell-page-header,
    body.page-connect_trader_broker .bottom-nav,
    body.page-disconnect_trader_broker .harvhub-header-top,
    body.page-disconnect_trader_broker .shell-page-header,
    body.page-disconnect_trader_broker .bottom-nav { display: none !important; }

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
    .harvhub-header-top .header-dropdown-btn:hover { background: rgba(46,204,143,0.1); }
    .harvhub-header-top .header-dropdown-btn.is-open { transform: rotate(180deg); }
    body.dark-mode .harvhub-header-top .header-dropdown-btn:hover { background: rgba(46,204,143,0.18); }

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
        inset: 0;
        background: rgba(0,0,0,0.45);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.28s ease;
        z-index: 1100;
    }
    .header-sheet-backdrop.active { opacity: 1; pointer-events: auto; }

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
    body.dark-mode .header-sheet { border-top: 1px solid rgba(255,255,255,0.06); }
    .header-sheet.active { transform: translateY(0); }

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
    .header-sheet-chev { color: var(--text-muted); font-size: 1rem; flex-shrink: 0; }

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
    .header-sheet-row.current-account-row:hover { background: rgba(46,204,143,0.08); }

    /* Investor's Hub avatar — gold */
    .header-sheet-avatar.is-investor-hub {
        background: var(--gold);
        color: #fff;
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
        .header-sheet.active { transform: translateX(-50%) translateY(0); }
    }

    @media (max-width: 1100px) {
        .header-sheet {
            left: 0;
            right: 0;
            max-width: 100%;
            transform: translateY(100%);
        }
        .header-sheet.active { transform: translateY(0); }
    }

    /* Programme list rows */
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
    /* Active programme avatar stays green */
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
    .header-sheet-sub-text .sub-desc {
        font-size: 0.72rem;
        color: var(--text-muted);
    }
    .header-sheet-sub-item .sub-check { color: var(--accent); font-size: 0.85rem; flex-shrink: 0; }

    body.header-sheet-open .harvhub-header-top { visibility: hidden; }
    body.header-sheet-open .shell-page-header  { visibility: hidden; }
    body.header-sheet-open .bottom-nav         { visibility: hidden; }

    /* ---------- FIXED SHELL PAGE HEADER ---------- */
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
        margin-top: var(--brand-header-height, 0px);
    }
    body.shell-no-brand-header .shell-page-header {
        margin-top: 0;
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
        max-width: 1100px;
        margin: 0 auto;
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
        box-shadow: inset 3px 0 0 var(--accent);
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

    /* ---------- VIEWPORT ---------- */
    .viewport {
        flex: 1;
        position: relative;
        overflow: hidden;
        padding-bottom: calc(var(--nav-height) + var(--safe-bottom));
        background: var(--bg);
        padding-top: calc(var(--page-header-height) + var(--safe-top));
    }

    body.shell-no-page-header .viewport {
        padding-top: 0;
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
            <span class="header-logo"><i class="fa-brands fa-pagelines"></i> HarvHub Trader</span>
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
    <div class="header-sheet" id="headerSheet" role="dialog" aria-modal="true" aria-label="Programme menu">
        <div class="header-sheet-handle"></div>
        <div class="header-sheet-title">ALL ACCOUNTS</div>
        <a href="javascript:void(0)" class="header-sheet-row" id="headerSheetInvestorHub">
            <span class="header-sheet-avatar is-investor-hub"><i class="fa-solid fa-layer-group"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Investor's Hub</span>
            </span>
            <i class="fa-solid fa-chevron-right header-sheet-chev"></i>
        </a>
        <div class="header-sheet-divider"></div>
        <div class="header-sheet-title">Programmes</div>
        <div class="header-sheet-sub-list" id="sheetProgrammesList">
            <div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">Loading…</div>
        </div>
        <div class="header-sheet-divider"></div>
        <button type="button" class="header-sheet-row" id="headerSheetCreateProgramme">
            <span class="header-sheet-avatar" style="background:rgba(46,204,143,0.15);color:var(--accent);"><i class="fa-solid fa-plus"></i></span>
            <span class="header-sheet-text">
                <span class="hs-title">Create new programme</span>
                <span class="hs-desc">Start a fresh programme</span>
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

    <div class="header-sheet-backdrop" id="createProgrammeBackdrop"></div>
    <div class="header-sheet" id="createProgrammeSheet" role="dialog" aria-modal="true" aria-label="Create new programme">
        <div class="header-sheet-handle"></div>
        <div class="header-sheet-title">Create new programme</div>
        <div style="padding:4px 10px 0;">
            <label for="createProgrammeInput" style="font-size:0.82rem;color:var(--text-muted);display:block;margin-bottom:6px;">Programme name</label>
            <input id="createProgrammeInput" type="text" maxlength="255" autocomplete="off"
                   placeholder="e.g. My Alpha Strategy"
                   style="width:100%;padding:12px;border-radius:8px;border:1px solid var(--border);background:var(--surface-2);color:var(--text);font-size:0.95rem;">
            <div style="font-size:0.72rem;color:var(--text-muted);margin-top:6px;line-height:1.4;">
                Letters, numbers and spaces only. Special characters removed. Max 255 characters.
            </div>
            <div id="createProgrammeError" style="display:none;background:rgba(239,68,68,0.12);color:#ef4444;padding:10px;margin-top:12px;border-radius:6px;font-size:0.85rem;"></div>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;padding:14px 10px 0;">
            <button type="button" id="createProgrammeSubmit" style="width:100%;padding:14px;border:none;border-radius:10px;background:var(--accent);color:#fff;font-weight:700;font-size:0.95rem;cursor:pointer;">Create programme</button>
            <button type="button" id="createProgrammeCancel" style="width:100%;padding:14px;border:none;border-radius:10px;background:rgba(127,127,127,0.15);color:var(--text);font-weight:600;font-size:0.95rem;cursor:pointer;">Cancel</button>
        </div>
    </div>

    <div class="header-sheet-backdrop" id="confirmSignoutBackdrop"></div>
    <div class="header-sheet" id="confirmSignoutSheet" role="dialog" aria-modal="true" aria-label="Confirm sign out">
        <div class="header-sheet-handle"></div>
        <div class="header-sheet-title">Sign out</div>
        <p style="padding:8px 10px 4px;font-size:0.95rem;line-height:1.5;color:var(--text);">
            Are you sure you want to sign out of your trader account?
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
                Notifications are enabled globally for your HarvHub account, not separately for each programme.
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
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'signals' ? 'active' : '' ?>" data-tab="signals">
            <span class="nav-icon"><i class="fa-solid fa-chart-simple"></i></span>
            <span class="nav-label">Signals</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'training' ? 'active' : '' ?>" data-tab="training">
            <span class="nav-icon"><i class="fa-solid fa-graduation-cap"></i></span>
            <span class="nav-label">Training</span>
        </button>
        <button type="button" class="bottom-nav-item <?= $activeNavTab === 'analytics' ? 'active' : '' ?>" data-tab="analytics">
            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
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
    var TABS_WITH_BRAND_HEADER = ['signals'];
    var TABS_WITH_NAV          = ['signals', 'training', 'analytics', 'menu'];
    var TABS_WITH_PAGE_HEADER  = ['training', 'analytics', 'menu'];
    var TABS_NO_CHROME         = ['vps', 'connect_trader_broker', 'disconnect_trader_broker'];

    var TAB_TITLES = {
        'signals':'Signals Dashboard','training':'Programme Training','analytics':'Programme Analytics',
        'vps':'Trader VPS','menu':'Menu',
        'connect_trader_broker':'Connect Broker','disconnect_trader_broker':'Disconnect Broker'
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

        var pageHeader = document.getElementById('shellPageHeader');
        if (pageHeader) {
            if (showBrandHeader && showPageHeader) {
                pageHeader.style.marginTop = 'var(--brand-header-height)';
            } else {
                pageHeader.style.marginTop = '0';
            }
        }

        var viewport = document.querySelector('.viewport');
        if (viewport) {
            if (showPageHeader) {
                var extraTop = showBrandHeader ? 'var(--brand-header-height)' : '0px';
                viewport.style.paddingTop = 'calc(' + extraTop + ' + var(--page-header-height) + var(--safe-top))';
            } else {
                viewport.style.paddingTop = '0';
            }
        }
    }

    var frames = {
        signals:                  document.getElementById('frame-signals'),
        training:                 document.getElementById('frame-training'),
        analytics:                document.getElementById('frame-analytics'),
        vps:                      document.getElementById('frame-vps'),
        menu:                     document.getElementById('frame-menu'),
        connect_trader_broker:    document.getElementById('frame-connect_trader_broker'),
        disconnect_trader_broker: document.getElementById('frame-disconnect_trader_broker')
    };
    var names = Object.keys(frames);
    var navTabNames = ['signals', 'training', 'analytics', 'menu'];

    var FILE_TO_TAB = {
        'signals_dashboard.php':'signals',
        'programme_training.php':'training',
        'programme_analytics.php':'analytics',
        'programme_vps.php':'vps',
        'trader_menu.php':'menu',
        'connect_trader_broker.php':'connect_trader_broker',
        'disconnect_trader_broker.php':'disconnect_trader_broker'
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

                if (base === 'traderapp.php') {
                    ev.preventDefault();
                    var m = href.match(/[?&]tab=([^&#]+)/);
                    requestSwitch(m ? decodeURIComponent(m[1]) : 'signals');
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
                    if (base === 'traderapp.php') {
                        var m = s.match(/[?&]tab=([^&#]+)/);
                        requestSwitch(m ? decodeURIComponent(m[1]) : 'signals');
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
    function requestLogout() { window.location.href = 'traderapp.php?logout=1'; }

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
                fetch('trader_menu.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: 'save_last_app=1&app=traderapp'
                }).catch(function () {});
            } catch (err) {}
        }
        if (e.data.type === 'logout') window.location.href = 'traderapp.php?logout=1';
        if (e.data.type === 'openHeaderSheet') openHeaderSheet();
        if (e.data.type === 'showSpinner') showBlockingLoader('Loading…');
        if (e.data.type === 'harvhubRefreshShell') {
            // Child iframes can ask the shell to refresh its live badges
            // (e.g. after accepting a request in signals_dashboard.php).
            if (typeof window.harvhubRefreshShellState === 'function') {
                window.harvhubRefreshShellState();
            }
        }

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
    window.__traderSwitch = switchTab;

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
        loadProgrammesIntoSheet();
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

    // ---------- Programme list ----------
    var sheetProgrammesList = document.getElementById('sheetProgrammesList');

    function renderProgrammesList(programmes) {
        if (!sheetProgrammesList) return;
        if (!programmes || programmes.length === 0) {
            sheetProgrammesList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">No programmes</div>';
            return;
        }

        programmes.sort(function(a, b) {
            if (a.is_active && !b.is_active) return -1;
            if (!a.is_active && b.is_active) return 1;
            return (a.id || 0) - (b.id || 0);
        });

        var html = '';
        programmes.forEach(function (prog) {
            var label = prog.name !== '' ? prog.name : 'Unnamed';
            var initial = (prog.initial && prog.initial.length) ? prog.initial : label.charAt(0).toUpperCase();
            var activeCls = prog.is_active ? ' is-active' : '';
            var check = prog.is_active ? '<i class="fa-solid fa-check sub-check"></i>' : '';

            var nCount = parseInt(prog.notification_count, 10) || 0;
            var rCount = parseInt(prog.request_count, 10) || 0;
            var desc =   rCount + ' investment request' + (rCount === 1 ? '' : 's');

            html += '<button type="button" class="header-sheet-sub-item' + activeCls + '" data-programme-id="' + prog.id + '" data-programme-name="' + escapeAttr(label) + '">';
            html +=   '<span class="header-sheet-sub-avatar">' + escapeHtml(initial) + '</span>';
            html +=   '<span class="header-sheet-sub-text">';
            html +=     '<span class="sub-name">' + escapeHtml(label) + '</span>';
            html +=     '<span class="sub-desc">' + escapeHtml(desc) + '</span>';
            html +=   '</span>';
            html +=   check;
            html += '</button>';
        });
        sheetProgrammesList.innerHTML = html;

        sheetProgrammesList.querySelectorAll('.header-sheet-sub-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var progId = parseInt(el.getAttribute('data-programme-id'), 10);
                var progName = el.getAttribute('data-programme-name') || '';
                var isActive = el.classList.contains('is-active');
                if (isActive) { closeHeaderSheet(); return; }

                closeHeaderSheet();
                setTimeout(function () {
                    showBlockingLoader('Switching to ' + progName + '…');
                    switchProgramme(progId);
                }, 360);
            });
        });
    }

    function loadProgrammesIntoSheet() {
        if (!sheetProgrammesList) return;
        sheetProgrammesList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:var(--text-muted);">Loading…</div>';

        fetch('traderapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'get_programmes=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                renderProgrammesList(data.programmes || []);
            } else {
                sheetProgrammesList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:#ef4444;">Failed to load</div>';
            }
        })
        .catch(function () {
            sheetProgrammesList.innerHTML = '<div style="padding:10px 14px;font-size:0.82rem;color:#ef4444;">Network error</div>';
        });
    }

    function switchProgramme(progId) {
        fetch('traderapp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: 'switch_programme_from_shell=1&programme_id=' + encodeURIComponent(progId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                window.location.href = 'traderapp.php?tab=signals&switched=1';
            } else {
                hideBlockingLoader();
                alert((data && data.message) ? data.message : 'Failed to switch.');
            }
        })
        .catch(function () { hideBlockingLoader(); alert('Network error.'); });
    }

    // ---------- Investor's Hub ----------
    var investorHubBtn = document.getElementById('headerSheetInvestorHub');
    if (investorHubBtn) {
        investorHubBtn.addEventListener('click', function () {
            closeHeaderSheet();
            setTimeout(function () {
                showBlockingLoader("Opening Investor's Hub…");
                try {
                    if (window.top && window.top !== window) window.top.location.href = 'investorapp.php';
                    else window.location.href = 'investorapp.php';
                } catch (e) { window.location.href = 'investorapp.php'; }
            }, 360);
        });
    }

    // ---------- Create programme ----------
    var createSheet      = document.getElementById('createProgrammeSheet');
    var createBackdrop   = document.getElementById('createProgrammeBackdrop');
    var createInput      = document.getElementById('createProgrammeInput');
    var createError      = document.getElementById('createProgrammeError');
    var createSubmit     = document.getElementById('createProgrammeSubmit');
    var createCancel     = document.getElementById('createProgrammeCancel');
    var createBtnTrigger = document.getElementById('headerSheetCreateProgramme');
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
                .substring(0, 255);

            if (createError) { createError.style.display = 'none'; createError.textContent = ''; }

            if (normalized === '') {
                createError.textContent = 'Please enter a programme name.';
                createError.style.display = 'block';
                return;
            }

            createSubmit.disabled = true;
            createSubmit.textContent = 'Creating…';

            fetch('traderapp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: 'create_programme_from_shell=1&programme_name=' + encodeURIComponent(normalized)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    closeCreateSheet(function () {
                        showBlockingLoader('Creating ' + (data.programme_name || normalized) + '…');
                        switchProgramme(data.programme_id);
                    });
                } else {
                    createSubmit.disabled = false;
                    createSubmit.textContent = 'Create programme';
                    createError.textContent = (data && data.errors ? data.errors.join(' ') : 'Failed to create programme.');
                    createError.style.display = 'block';
                }
            })
            .catch(function () {
                createSubmit.disabled = false;
                createSubmit.textContent = 'Create programme';
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
                fetch('trader_menu.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: 'save_last_app=1&app=traderapp'
                }).catch(function () {});
            } catch (e) {}
            showBlockingLoader('Signing out…');
            setTimeout(function () {
                window.location.href = 'traderapp.php?logout=1';
            }, 250);
        });
    }

    window.openHeaderSheet = openHeaderSheet;
    window.closeHeaderSheet = closeHeaderSheet;

    // ---------- Notifications ----------
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
            return fetch('traderapp.php', {
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

    // ---------- Live shell state (badges, request counts) ----------
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
            return fetch('traderapp.php', {
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
            if (sheetOpen && sheetProgrammesList && data.notif_by_programme && data.request_by_programme) {
                var notifMap = data.notif_by_programme || {};
                var reqMap   = data.request_by_programme || {};

                // Only patch existing rows — do NOT trigger a full fetch.
                sheetProgrammesList.querySelectorAll('.header-sheet-sub-item').forEach(function (el) {
                    var pid = parseInt(el.getAttribute('data-programme-id'), 10) || 0;
                    if (!pid) return;

                    var nCount = parseInt(notifMap[pid], 10) || 0;
                    var rCount = parseInt(reqMap[pid], 10) || 0;
                    var desc =  rCount + ' investment request' + (rCount === 1 ? '' : 's');

                    var descEl = el.querySelector('.sub-desc');
                    if (descEl && descEl.textContent !== desc) {
                        descEl.textContent = desc;
                    }
                });
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

    var _origSwitch = window.__traderSwitch;
    if (typeof _origSwitch === 'function') {
        window.__traderSwitch = function (t) {
            _origSwitch(t);
            setTimeout(attachScrollListeners, 100);
            if (!window.__harvhubModalOpen) {
                lastScrollTop = 0; isNavHidden = false; isHeaderHidden = false;
                showNav(); showHeader();
            }
        };
    }

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

(function () {
    var WATCHED = [
        'page-connect_trader_broker','page-disconnect_trader_broker','page-vps',
        'profile-page-open','prog-detail-view-open'
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