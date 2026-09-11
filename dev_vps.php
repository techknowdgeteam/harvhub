<?php
// dev_vps.php — Standalone with sidebar navigation
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

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit;
}
// ==================== DEVELOPER CHECK ====================
$devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$devStmt->execute([$email]);
$developer = $devStmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header("Location: dev_app.php");
    exit;
}

$brokerConnected = !empty($developer['broker']) && !empty($developer['server']) && !empty($developer['login']);
$email = strtolower($_SESSION['user_email']);

// ==================== FETCH USER DATA ====================
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

$userId = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';

$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ==================== DEVELOPER CHECK + SIDEBAR FLAG ====================
$devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
$devStmt->execute([$email]);
$developer = $devStmt->fetch(PDO::FETCH_ASSOC);

if (!$developer) {
    header("Location: dev_app.php");
    exit;
}

$brokerConnected = !empty($developer['broker']) && !empty($developer['server']) && !empty($developer['login']);

// ==================== FETCH VPS HOSTS (PUBLIC ONLY) ====================
$hosts = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.user_id, v.server_location, v.subscription_duration, v.visibility,
               h.fullname AS host_name, h.email AS host_email
        FROM vps v
        INNER JOIN harvhub h ON h.id = v.user_id
        WHERE v.visibility = 'public'
        ORDER BY h.fullname ASC
    ");
    $stmt->execute();
    $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $hosts = [];
}

// ==================== FETCH HOST FOLLOWER COUNTS ====================
$hostFollowerCounts = [];
if (!empty($hosts)) {
    $ownerIds = array_unique(array_column($hosts, 'user_id'));

    if (!empty($ownerIds)) {
        $placeholders = implode(',', array_fill(0, count($ownerIds), '?'));

        try {
            $stmt = $pdo->prepare("
                SELECT owner_id, COUNT(*) AS follower_count
                FROM vps_hosts_followers
                WHERE owner_id IN ($placeholders) AND host_status = 'active'
                GROUP BY owner_id
            ");
            $stmt->execute(array_values($ownerIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $hostFollowerCounts[(int)$row['owner_id']] = (int)$row['follower_count'];
            }
        } catch (PDOException $e) {
            $hostFollowerCounts = [];
        }
    }
}

// ==================== VPS-RELATED FLAGS FOR CURRENT USER ====================
$userOwnsVps = false;
$isAlreadyFollower = false;
$userHasVps = false;

try {
    $stmt = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $userOwnsVps = true;
    }
} catch (PDOException $e) {}

try {
    $stmt = $pdo->prepare("SELECT id FROM vps_hosts_followers WHERE follower_id = ? AND host_status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $isAlreadyFollower = true;
    }
} catch (PDOException $e) {}

$userHasVps = ($userOwnsVps || $isAlreadyFollower);

// ==================== HANDLE REQUEST SPACE (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_vps_space'])) {
    header('Content-Type: application/json');

    $ownerId = (int)($_POST['owner_id'] ?? 0);

    if ($ownerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid host.']);
        exit;
    }

    if ($ownerId === $userId) {
        echo json_encode(['success' => false, 'message' => 'You cannot request your own VPS.']);
        exit;
    }

    if ($isAlreadyFollower) {
        echo json_encode([
            'success' => false,
            'message' => 'You are already a follower of another VPS. You cannot request space from other hosts.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM vps WHERE user_id = ? AND visibility = 'public' LIMIT 1");
    $stmt->execute([$ownerId]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'This host is not available.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM vps_hosts_requestors WHERE owner_id = ? AND requestor_id = ? AND request_status IN ('pending','accept') LIMIT 1");
    $stmt->execute([$ownerId, $userId]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'You already have an active request with this host.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO vps_hosts_requestors (owner_id, requestor_id, request_status) VALUES (?, ?, 'pending')");
        $stmt->execute([$ownerId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Request submitted successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
    }
    exit;
}

// ==================== HANDLE GET HOST DETAILS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_host_details'])) {
    header('Content-Type: application/json');

    $ownerId = (int)($_POST['owner_id'] ?? 0);

    if ($ownerId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid host.']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT v.user_id, v.server_location, v.subscription_duration,
               h.fullname AS host_name, h.email AS host_email
        FROM vps v
        INNER JOIN harvhub h ON h.id = v.user_id
        WHERE v.user_id = ? AND v.visibility = 'public'
        LIMIT 1
    ");
    $stmt->execute([$ownerId]);
    $hostData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hostData) {
        echo json_encode(['success' => false, 'message' => 'Host not found.']);
        exit;
    }

    $followerCount = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM vps_hosts_followers WHERE owner_id = ? AND host_status = 'active'");
        $stmt->execute([$ownerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $followerCount = (int)($row['cnt'] ?? 0);
    } catch (PDOException $e) {}

    $alreadyRequested = false;
    try {
        $stmt = $pdo->prepare("SELECT id FROM vps_hosts_requestors WHERE owner_id = ? AND requestor_id = ? AND request_status IN ('pending','accept') LIMIT 1");
        $stmt->execute([$ownerId, $userId]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $alreadyRequested = true;
        }
    } catch (PDOException $e) {}

    $maxHosts = 5;

    echo json_encode([
        'success' => true,
        'host' => [
            'owner_id' => $hostData['user_id'],
            'fullname' => $hostData['host_name'],
            'email' => $hostData['host_email'],
            'server_location' => $hostData['server_location'],
            'subscription_duration' => $hostData['subscription_duration'],
            'current_hosts' => $followerCount,
            'max_hosts' => $maxHosts,
            'is_self' => ($ownerId === $userId),
            'is_full' => ($followerCount >= $maxHosts),
            'already_requested' => $alreadyRequested,
            'is_follower_lock' => $isAlreadyFollower
        ]
    ]);
    exit;
}

// ==================== GET MY FOLLOWER OWNER (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_follower_owner'])) {
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT f.id AS follower_row_id, f.follower_id, f.host_status, f.created_at,
                   f.owner_id AS owner_id_resolved,
                   h.fullname AS owner_name,
                   v.server_location, v.subscription_duration
            FROM vps_hosts_followers f
            LEFT JOIN harvhub h ON h.id = f.owner_id
            LEFT JOIN vps v ON v.user_id = f.owner_id
            WHERE f.follower_id = ? AND f.host_status = 'active'
            ORDER BY f.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => true, 'is_follower' => false]);
            exit;
        }

        $ownerId = (int)$row['owner_id_resolved'];

        $followers = [];
        if ($ownerId > 0) {
            $fs = $pdo->prepare("
                SELECT f.id, f.follower_id, f.host_status, f.created_at,
                       h.fullname AS follower_name
                FROM vps_hosts_followers f
                LEFT JOIN harvhub h ON h.id = f.follower_id
                WHERE f.owner_id = ?
                ORDER BY f.created_at DESC
            ");
            $fs->execute([$ownerId]);
            $followers = $fs->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'is_follower' => true,
            'owner' => [
                'owner_id' => $ownerId,
                'fullname' => $row['owner_name'] ?? 'Anonymous',
                'server_location' => $row['server_location'] ?? '',
                'subscription_duration' => $row['subscription_duration'] ?? 0
            ],
            'self' => [
                'follower_row_id' => $row['follower_row_id'],
                'follower_id' => $row['follower_id'],
                'host_status' => $row['host_status']
            ],
            'followers' => $followers
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load follower owner.']);
    }
    exit;
}

// ==================== SENT REQUESTS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_sent_requests'])) {
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.owner_id, r.request_status, r.created_at,
                   h.fullname AS owner_name,
                   v.server_location AS owner_location
            FROM vps_hosts_requestors r
            LEFT JOIN harvhub h ON h.id = r.owner_id
            LEFT JOIN vps v ON v.user_id = r.owner_id
            WHERE r.requestor_id = ?
              AND r.request_status IN ('pending', 'reject')
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $seen = [];
        $unique = [];
        foreach ($rows as $r) {
            $oid = (int)$r['owner_id'];
            if (!isset($seen[$oid])) {
                $seen[$oid] = true;
                $unique[] = $r;
            }
        }

        echo json_encode(['success' => true, 'requests' => $unique]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load sent requests.']);
    }
    exit;
}

// ==================== INCOMING REQUESTS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_incoming_requests'])) {
    header('Content-Type: application/json');

    if (!$userOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'You do not own a VPS.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT r.id, r.requestor_id, r.request_status, r.created_at,
                   h.fullname AS requestor_name
            FROM vps_hosts_requestors r
            LEFT JOIN harvhub h ON h.id = r.requestor_id
            WHERE r.owner_id = ? AND r.request_status = 'pending'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'requests' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load incoming requests.']);
    }
    exit;
}

// ==================== UPDATE REQUEST STATUS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request_status'])) {
    header('Content-Type: application/json');

    $requestId = (int)($_POST['request_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    $allowed = ['accept', 'reject'];
    if ($requestId <= 0 || !in_array($newStatus, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
        $stmt->execute([$requestId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit;
        }

        $isOwner = ((int)$row['owner_id'] === $userId);
        $isRequestor = ((int)$row['requestor_id'] === $userId);

        if (!$isOwner && !$isRequestor) {
            echo json_encode(['success' => false, 'message' => 'Not authorized.']);
            exit;
        }

        if ($isRequestor && !$isOwner) {
            echo json_encode(['success' => false, 'message' => 'Only the host can respond to this request.']);
            exit;
        }

        if ($row['request_status'] !== 'pending') {
            echo json_encode([
                'success' => false,
                'message' => 'This request is already ' . $row['request_status'] . '.',
                'current_status' => $row['request_status']
            ]);
            exit;
        }

        if ($newStatus === 'accept') {
            $requestorId = (int)$row['requestor_id'];
            $ownerId     = (int)$row['owner_id'];

            $other = $pdo->prepare("
                SELECT f.id, f.owner_id, h.fullname AS owner_name
                FROM vps_hosts_followers f
                LEFT JOIN harvhub h ON h.id = f.owner_id
                WHERE f.follower_id = ? AND f.host_status = 'active' AND f.owner_id <> ?
                LIMIT 1
            ");
            $other->execute([$requestorId, $ownerId]);

            $otherRow = $other->fetch(PDO::FETCH_ASSOC);

            if ($otherRow) {
                $del = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
                $del->execute([$requestId]);

                echo json_encode([
                    'success' => false,
                    'message' => 'This user is already a follower of another VPS (' . ($otherRow['owner_name'] ?: 'another host') . '). Their pending request has been cleared.',
                    'cleared' => true
                ]);
                exit;
            }
        }

        $upd = $pdo->prepare("
            UPDATE vps_hosts_requestors
            SET request_status = ?
            WHERE id = ? AND request_status = 'pending'
        ");
        $upd->execute([$newStatus, $requestId]);

        if ($upd->rowCount() === 0) {
            $chk = $pdo->prepare("SELECT request_status FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
            $chk->execute([$requestId]);
            $cur = $chk->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => false,
                'message' => 'Request is already ' . ($cur['request_status'] ?? 'processed') . '.',
                'current_status' => $cur['request_status'] ?? null
            ]);
            exit;
        }

        if ($newStatus === 'accept') {
            $ownerId = (int)$row['owner_id'];
            $requestorId = (int)$row['requestor_id'];

            $check = $pdo->prepare("SELECT id FROM vps_hosts_followers WHERE owner_id = ? AND follower_id = ? LIMIT 1");
            $check->execute([$ownerId, $requestorId]);
            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                $ins = $pdo->prepare("INSERT INTO vps_hosts_followers (owner_id, follower_id, host_status) VALUES (?, ?, 'active')");
                $ins->execute([$ownerId, $requestorId]);
            }

            $delRow = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
            $delRow->execute([$requestId]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Request ' . $newStatus . 'ed successfully.',
            'new_status' => $newStatus
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
    }
    exit;
}

// ==================== DELETE REQUEST (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_request'])) {
    header('Content-Type: application/json');

    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_requestors WHERE id = ? LIMIT 1");
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

        $del = $pdo->prepare("DELETE FROM vps_hosts_requestors WHERE id = ?");
        $del->execute([$requestId]);

        echo json_encode(['success' => true, 'message' => 'Request deleted.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete request.']);
    }
    exit;
}

// ==================== GET MY FOLLOWERS (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_my_followers'])) {
    header('Content-Type: application/json');

    if (!$userOwnsVps) {
        echo json_encode(['success' => false, 'message' => 'You do not own a VPS.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT f.id, f.follower_id, f.host_status, f.created_at,
                   h.fullname AS follower_name
            FROM vps_hosts_followers f
            LEFT JOIN harvhub h ON h.id = f.follower_id
            WHERE f.owner_id = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'followers' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load followers.']);
    }
    exit;
}

// ==================== REMOVE FOLLOWER (AJAX) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_follower'])) {
    header('Content-Type: application/json');

    $followerRowId = (int)($_POST['follower_row_id'] ?? 0);

    if ($followerRowId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid follower.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM vps_hosts_followers WHERE id = ? LIMIT 1");
        $stmt->execute([$followerRowId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Follower not found.']);
            exit;
        }

        if ((int)$row['owner_id'] !== $userId) {
            echo json_encode(['success' => false, 'message' => 'You can only remove your own followers.']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM vps_hosts_followers WHERE id = ?");
        $del->execute([$followerRowId]);

        echo json_encode(['success' => true, 'message' => 'Follower removed successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to remove follower.']);
    }
    exit;
}

// ==================== SPINNER ====================
function showSpinner() {
    echo '<style>
        .spinner-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: transparent;
            z-index: 99999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            pointer-events: none;
        }
        .spinner-overlay.active { 
            display: flex; 
        }
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--accent, #10b981);
            animation: spin 0.6s linear infinite;
            margin-bottom: 12px;
        }
        .spinner-text {
            color: var(--text-muted, #888);
            font-size: 14px;
            margin: 0;
            letter-spacing: 0.5px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
    <div class="spinner-overlay active" id="spinnerOverlay">
        <div class="spinner"></div>
        <p class="spinner-text">Loading...</p>
    </div>
    <script>
        window.addEventListener("load", function() {
            var overlay = document.getElementById("spinnerOverlay");
            if (overlay) {
                setTimeout(function() {
                    overlay.classList.remove("active");
                }, 300);
            }
        });
        document.addEventListener("DOMContentLoaded", function() {
            var overlay = document.getElementById("spinnerOverlay");
            if (overlay) {
                setTimeout(function() {
                    overlay.classList.remove("active");
                }, 200);
            }
        });
    </script>';
}

showSpinner();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VPS Hub - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'dev_style.php'; ?>
<style>
    body {
        padding-top: var(--header-height, 60px);
        padding-bottom: var(--nav-height, 100px);
        background: var(--bg);
        color: var(--text);
        font-family: var(--font-family, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
        margin: 0;
        transition: background var(--transition-speed, 0.3s), color var(--transition-speed, 0.3s);
    }

    @media (max-width: 480px) {
        body {
            padding-top: var(--header-height-mobile, 52px);
            padding-bottom: var(--nav-height-mobile, 80px);
        }
    }

    /* Desktop: push content when sidebar is expanded */
    @media (min-width: 769px) {
        body.sidebar-expanded-desktop .vps-page-wrapper {
            margin-left: 240px;
            transition: margin-left 0.3s ease;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <?php include 'dev_tabs.php'; ?>

    <div class="vps-page-wrapper">

        <!-- Page Header -->
        <div class="vps-page-header">
            <h1>VPS Hub</h1>
            <p>Host or join a virtual private server</p>
        </div>

        <!-- Tabs -->
        <div class="vps-tabs">
            <button class="vps-tab active" data-tab="purchase" onclick="switchVpsTab('purchase')">
                Purchase VPS
            </button>
            <button class="vps-tab" data-tab="users" onclick="switchVpsTab('users')">
                Users with VPS
            </button>
            <button class="vps-tab" data-tab="sent" onclick="switchVpsTab('sent')">
                Sent Requests
            </button>
            <?php if ($userOwnsVps): ?>
            <button class="vps-tab" data-tab="incoming" onclick="switchVpsTab('incoming')">
                Incoming Requests
            </button>
            <button class="vps-tab" data-tab="followers" onclick="switchVpsTab('followers')">
                My Followers
            </button>
            <?php endif; ?>
        </div>

        <!-- Tab: Purchase VPS -->
        <div class="vps-tab-content active" id="vpsTabPurchase">
            <div class="vps-empty-state">
                <span class="vps-empty-icon">—</span>
                <p>Not available at the moment</p>
            </div>
        </div>

        <!-- Tab: Users with VPS -->
        <div class="vps-tab-content" id="vpsTabUsers">

            <?php if ($isAlreadyFollower): ?>
                <div class="vps-follower-lock" style="margin-bottom:16px;padding:12px 16px;border-left:4px solid #f39c12;background:rgba(243,156,18,0.1);border-radius:8px;font-size:13px;line-height:1.5;color:var(--text);">
                    <strong>You are already a follower of a VPS host.</strong>
                    You cannot send requests to other hosts while you are an active follower.
                </div>
            <?php endif; ?>

            <?php if (empty($hosts)): ?>
                <div class="vps-empty-state">
                    <span class="vps-empty-icon">—</span>
                    <p>No public VPS hosts available at the moment</p>
                </div>
            <?php else: ?>
                <div class="vps-hosts-list">
                    <?php foreach ($hosts as $host):
                        $ownerId = (int)$host['user_id'];
                        $currentHosts = $hostFollowerCounts[$ownerId] ?? 0;
                        $maxHosts = 5;
                        $hostName = $host['host_name'] ?: 'Anonymous';
                        $serverLocation = $host['server_location'] ?: 'N/A';
                        $isSelf = ($ownerId === $userId);
                        $isFull = ($currentHosts >= $maxHosts);
                        $isLocked = $isAlreadyFollower;
                    ?>
                        <div class="vps-host-item">
                            <div class="vps-host-name" onclick="showHostModal(<?= $ownerId ?>)" role="button" tabindex="0">
                                <?= htmlspecialchars($hostName) ?>
                            </div>
                            <div class="vps-host-meta">
                                <span class="vps-host-stat">
                                    <span class="vps-host-stat-label">Server Location</span>
                                    <span class="vps-host-stat-value"><?= htmlspecialchars($serverLocation) ?></span>
                                </span>
                                <span class="vps-host-stat">
                                    <span class="vps-host-stat-label">Maximum host</span>
                                    <span class="vps-host-stat-value"><?= $maxHosts ?></span>
                                </span>
                                <span class="vps-host-stat">
                                    <span class="vps-host-stat-label">Current hosts</span>
                                    <span class="vps-host-stat-value"><?= $currentHosts ?></span>
                                </span>
                            </div>
                            <div class="vps-host-actions">
                                <?php if ($isSelf): ?>
                                    <button class="vps-btn-request disabled" disabled>Your VPS</button>
                                <?php elseif ($isLocked): ?>
                                    <button class="vps-btn-request disabled" disabled title="You are already a follower of a VPS"></button>
                                <?php elseif ($isFull): ?>
                                    <button class="vps-btn-request disabled" disabled>Full</button>
                                <?php else: ?>
                                    <button class="vps-btn-request" onclick="showHostModal(<?= $ownerId ?>)">Request Space</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab: Sent Requests -->
        <div class="vps-tab-content" id="vpsTabSent">
            <div id="sentRequestsContainer" class="vps-requests-list">
                <div class="vps-requests-loading">Loading sent requests...</div>
            </div>
        </div>

        <!-- Tab: Incoming Requests (only if userOwnsVps) -->
        <?php if ($userOwnsVps): ?>
        <div class="vps-tab-content" id="vpsTabIncoming">
            <div id="incomingRequestsContainer" class="vps-requests-list">
                <div class="vps-requests-loading">Loading incoming requests...</div>
            </div>
        </div>

        <!-- Tab: My Followers (only if userOwnsVps) -->
        <div class="vps-tab-content" id="vpsTabFollowers">
            <div id="followersContainer" class="vps-requests-list">
                <div class="vps-requests-loading">Loading followers...</div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Host Details Modal -->
    <div id="vpsHostModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title">Host Profile</h2>
            <div class="vps-modal-body" id="vpsHostModalBody">
                <div class="vps-modal-loading">Loading...</div>
            </div>
            <div class="vps-modal-actions" id="vpsHostModalActions">
                <button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div id="vpsConfirmModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title" id="vpsConfirmModalTitle">Confirm Removal</h2>
            <div class="vps-modal-body">
                <div class="vps-modal-row" id="vpsConfirmTargetRow">
                    <span class="vps-modal-label" id="vpsConfirmTargetLabel">Follower</span>
                    <span class="vps-modal-value" id="vpsConfirmFollowerName">—</span>
                </div>
                <p style="margin-top:14px;color:var(--text-muted);font-size:13px;line-height:1.5;" id="vpsConfirmMessage">
                    Are you sure you want to remove this follower from your VPS? This will revoke their access and they will be removed from the followers list.
                </p>
            </div>
            <div class="vps-modal-actions">
                <button class="vps-btn-confirm" id="vpsConfirmRemoveBtn" onclick="confirmModalAction()">Yes, Remove</button>
                <button class="vps-modal-close" onclick="closeVpsConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Custom Alert / Notification Modal -->
    <div id="vpsAlertModal" class="vps-modal">
        <div class="vps-modal-content">
            <h2 class="vps-modal-title" id="vpsAlertModalTitle">Notice</h2>
            <div class="vps-modal-body">
                <p id="vpsAlertMessage" style="color:var(--text-muted);font-size:14px;line-height:1.5;margin:0;">
                    Message
                </p>
            </div>
            <div class="vps-modal-actions">
                <button class="vps-btn-confirm" onclick="closeVpsAlertModal()">OK</button>
            </div>
        </div>
    </div>

<script>
    var currentHostOwnerId = null;
    var userHasVps = <?= $userHasVps ? 'true' : 'false' ?>;
    var userOwnsVps = <?= $userOwnsVps ? 'true' : 'false' ?>;
    var isAlreadyFollower = <?= $isAlreadyFollower ? 'true' : 'false' ?>;

    var pendingConfirmAction = null;
    var pendingConfirmId = null;
    var pendingConfirmName = '';

    function switchVpsTab(tab) {
        document.querySelectorAll('.vps-tab').forEach(function(el) {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        document.querySelectorAll('.vps-tab-content').forEach(function(el) {
            el.classList.remove('active');
        });

        if (tab === 'purchase') {
            document.getElementById('vpsTabPurchase').classList.add('active');
        } else if (tab === 'users') {
            document.getElementById('vpsTabUsers').classList.add('active');
        } else if (tab === 'sent') {
            document.getElementById('vpsTabSent').classList.add('active');
            loadSentRequests();
        } else if (tab === 'incoming' && userOwnsVps) {
            var inc = document.getElementById('vpsTabIncoming');
            if (inc) {
                inc.classList.add('active');
                loadIncomingRequests();
            }
        } else if (tab === 'followers' && userOwnsVps) {
            var fol = document.getElementById('vpsTabFollowers');
            if (fol) {
                fol.classList.add('active');
                loadMyFollowers();
            }
        }
    }

    // ==================== CUSTOM ALERT ====================
    function showVpsAlert(message, title) {
        document.getElementById('vpsAlertModalTitle').textContent = title || 'Notice';
        document.getElementById('vpsAlertMessage').textContent = message == null ? '' : String(message);
        var modal = document.getElementById('vpsAlertModal');
        modal.classList.add('active');
        lockBodyScroll();
    }

    function closeVpsAlertModal() {
        document.getElementById('vpsAlertModal').classList.remove('active');
        unlockBodyScroll();
    }

    // ==================== CUSTOM CONFIRM ====================
    function openVpsConfirm(opts) {
        pendingConfirmAction = opts.type || null;
        pendingConfirmId     = opts.id || null;
        pendingConfirmName   = opts.name || '';

        document.getElementById('vpsConfirmModalTitle').textContent = opts.title || 'Confirm';
        document.getElementById('vpsConfirmTargetLabel').textContent = opts.label || 'Target';
        document.getElementById('vpsConfirmFollowerName').textContent = opts.name || '—';
        document.getElementById('vpsConfirmMessage').textContent = opts.message || '';
        document.getElementById('vpsConfirmRemoveBtn').textContent = opts.confirmText || 'Yes, Confirm';

        var targetRow = document.getElementById('vpsConfirmTargetRow');
        if (opts.name) {
            targetRow.style.display = '';
        } else {
            targetRow.style.display = 'none';
        }

        var modal = document.getElementById('vpsConfirmModal');
        modal.classList.add('active');
        lockBodyScroll();
    }

    function closeVpsConfirmModal() {
        document.getElementById('vpsConfirmModal').classList.remove('active');
        unlockBodyScroll();
        pendingConfirmAction = null;
        pendingConfirmId = null;
        pendingConfirmName = '';
    }

    function confirmModalAction() {
        if (!pendingConfirmAction || !pendingConfirmId) {
            closeVpsConfirmModal();
            return;
        }

        var btn = document.getElementById('vpsConfirmRemoveBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Processing...';

        if (pendingConfirmAction === 'remove_follower') {
            var rowId = pendingConfirmId;
            fetch('dev_vps.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'remove_follower=1&follower_row_id=' + encodeURIComponent(rowId)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    closeVpsConfirmModal();
                    showVpsAlert(data.message || 'Follower removed successfully.', 'Success');
                    loadMyFollowers();
                } else {
                    showVpsAlert(data.message || 'Failed to remove follower', 'Error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = originalText;
                showVpsAlert('Network error. Please try again.', 'Error');
            });
        } else if (pendingConfirmAction === 'delete_request') {
            var reqId = pendingConfirmId;
            fetch('dev_vps.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'delete_request=1&request_id=' + encodeURIComponent(reqId)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = originalText;

                if (data.success) {
                    closeVpsConfirmModal();
                    showVpsAlert(data.message || 'Request deleted.', 'Success');
                    loadSentRequests();
                } else {
                    showVpsAlert(data.message || 'Failed to delete request', 'Error');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = originalText;
                showVpsAlert('Network error. Please try again.', 'Error');
            });
        } else {
            btn.disabled = false;
            btn.textContent = originalText;
            closeVpsConfirmModal();
        }
    }

    // ==================== SENT REQUESTS TAB (dual mode) ====================
    function loadSentRequests() {
        var container = document.getElementById('sentRequestsContainer');
        container.innerHTML = '<div class="vps-requests-loading">Loading sent requests...</div>';

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_my_follower_owner=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.is_follower) {
                renderFollowerOwnerView(container, data.owner, data.self, data.followers || []);
                return;
            }

            fetch('dev_vps.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'get_sent_requests=1'
            })
            .then(function(r2) { return r2.json(); })
            .then(function(data2) {
                if (!data2.success || !data2.requests.length) {
                    container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>You have not sent any requests yet</p></div>';
                    return;
                }
                renderRequestList(container, data2.requests, 'sent');
            })
            .catch(function() {
                container.innerHTML = '<div class="vps-empty-state"><p>Failed to load sent requests</p></div>';
            });
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load sent requests</p></div>';
        });
    }

    // ==================== FOLLOWER MODE: OWNER + FOLLOWERS LIST ====================
    function renderFollowerOwnerView(container, owner, self, followers) {
        var html = '';

        html += '<div class="vps-owner-card">';
        html += '  <div class="vps-owner-badge">VPS Owner</div>';
        html += '  <div class="vps-owner-name">' + escapeHtml(owner.fullname || 'Anonymous') + '</div>';
        html += '  <div class="vps-owner-meta">';
        html += '    <div class="vps-owner-stat"><span class="vps-owner-stat-label">Server Location</span><span class="vps-owner-stat-value">' + escapeHtml(owner.server_location || 'N/A') + '</span></div>';
        if (owner.subscription_duration) {
            html += '    <div class="vps-owner-stat"><span class="vps-owner-stat-label">Subscription Duration</span><span class="vps-owner-stat-value">' + escapeHtml(String(owner.subscription_duration)) + ' days</span></div>';
        }
        html += '  </div>';
        html += '</div>';

        html += '<div class="vps-followers-section">';
        html += '  <div class="vps-followers-title">Followers of this VPS</div>';

        if (!followers.length) {
            html += '<div class="vps-empty-state"><p>No followers yet</p></div>';
        } else {
            html += '<div class="vps-hosts-list">';
            followers.forEach(function(f) {
                var isSelf = (parseInt(f.follower_id, 10) === parseInt(self.follower_id, 10));
                var name = f.follower_name || 'Unknown';
                var status = (f.host_status || 'active').toLowerCase();

                html += '<div class="vps-request-item' + (isSelf ? ' vps-follower-self' : '') + '">';
                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">' + (isSelf ? 'You' : 'Follower') + '</div>';
                html += '    <div class="vps-request-value">' + escapeHtml(name) + (isSelf ? ' <span class="vps-self-badge">you</span>' : '') + '</div>';
                html += '  </div>';
                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">Status</div>';
                html += '    <div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div>';
                html += '  </div>';
                html += '</div>';
            });
            html += '</div>';
        }
        html += '</div>';

        container.innerHTML = html;
    }

    // ==================== INCOMING REQUESTS ====================
    function loadIncomingRequests() {
        var container = document.getElementById('incomingRequestsContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading incoming requests...</div>';

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_incoming_requests=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.requests.length) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>No pending requests at the moment</p></div>';
                return;
            }
            renderRequestList(container, data.requests, 'incoming');
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load incoming requests</p></div>';
        });
    }

    // ==================== MY FOLLOWERS ====================
    function loadMyFollowers() {
        var container = document.getElementById('followersContainer');
        if (!container) return;

        container.innerHTML = '<div class="vps-requests-loading">Loading followers...</div>';

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_my_followers=1'
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.followers.length) {
                container.innerHTML = '<div class="vps-empty-state"><span class="vps-empty-icon">—</span><p>You have no followers yet</p></div>';
                return;
            }

            var html = '<div class="vps-hosts-list">';
            data.followers.forEach(function(f) {
                var name = f.follower_name || 'Unknown';
                var status = (f.host_status || 'active').toLowerCase();

                html += '<div class="vps-request-item">';

                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">Follower</div>';
                html += '    <div class="vps-request-value">' + escapeHtml(name) + '</div>';
                html += '  </div>';

                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">Status</div>';
                html += '    <div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div>';
                html += '  </div>';

                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">Action</div>';
                html += '    <div class="vps-request-value">';
                html += '      <button class="vps-btn-delete" data-follower-id="' + f.id + '" data-follower-name="' + escapeAttr(name) + '" onclick="removeFollowerClicked(this)">Remove</button>';
                html += '    </div>';
                html += '  </div>';

                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(function() {
            container.innerHTML = '<div class="vps-empty-state"><p>Failed to load followers</p></div>';
        });
    }

    // ==================== SHARED REQUEST RENDERER ====================
    function renderRequestList(container, requests, mode) {
        var html = '<div class="vps-hosts-list">';

        requests.forEach(function(req) {
            var name = (mode === 'sent')
                ? (req.owner_name || 'Anonymous')
                : (req.requestor_name || 'Anonymous');

            var label = (mode === 'sent') ? 'Host' : 'Requestor';
            var location = (mode === 'sent' && req.owner_location) ? req.owner_location : '';
            var status = (req.request_status || 'pending').toLowerCase();

            html += '<div class="vps-request-item">';

            html += '  <div class="vps-request-row">';
            html += '    <div class="vps-request-label">' + label + '</div>';
            html += '    <div class="vps-request-value">' + escapeHtml(name) + '</div>';
            html += '  </div>';

            if (location) {
                html += '  <div class="vps-request-row">';
                html += '    <div class="vps-request-label">Server Location</div>';
                html += '    <div class="vps-request-value">' + escapeHtml(location) + '</div>';
                html += '  </div>';
            }

            html += '  <div class="vps-request-row">';
            html += '    <div class="vps-request-label">Status</div>';
            html += '    <div class="vps-request-value"><span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span></div>';
            html += '  </div>';

            html += '  <div class="vps-request-row">';
            html += '    <div class="vps-request-label">Action</div>';
            html += '    <div class="vps-request-value">';

            if (mode === 'incoming') {
                if (status === 'pending') {
                    html += '<select class="vps-request-select" data-request-id="' + req.id + '" onchange="updateRequestStatus(this)">';
                    html += '  <option value="">-- Select --</option>';
                    html += '  <option value="accept">Accept</option>';
                    html += '  <option value="reject">Reject</option>';
                    html += '</select>';
                } else {
                    html += '<span class="vps-status-badge vps-status-' + status + '">' + escapeHtml(status) + '</span>';
                }
            } else {
                html += '<button class="vps-btn-delete" data-request-id="' + req.id + '" data-request-name="' + escapeAttr(name) + '" onclick="deleteRequestClicked(this)">Delete Request</button>';
            }

            html += '    </div>';
            html += '  </div>';

            html += '</div>';
        });

        html += '</div>';
        container.innerHTML = html;
    }

    // ==================== REMOVE FOLLOWER (button click) ====================
    function removeFollowerClicked(btn) {
        var rowId = btn.getAttribute('data-follower-id');
        var name  = btn.getAttribute('data-follower-name') || '';
        if (!rowId) return;

        openVpsConfirm({
            type: 'remove_follower',
            id: rowId,
            name: name,
            title: 'Remove Follower',
            label: 'Follower',
            message: 'Are you sure you want to remove this follower from your VPS? This will revoke their access and they will be removed from the followers list.',
            confirmText: 'Yes, Remove'
        });
    }

    // ==================== DELETE REQUEST (button click) ====================
    function deleteRequestClicked(btn) {
        var reqId = btn.getAttribute('data-request-id');
        var name  = btn.getAttribute('data-request-name') || '';
        if (!reqId) return;

        openVpsConfirm({
            type: 'delete_request',
            id: reqId,
            name: name,
            title: 'Delete Request',
            label: 'Host',
            message: 'Are you sure you want to delete this sent request? This action cannot be undone.',
            confirmText: 'Yes, Delete'
        });
    }

    // ==================== UPDATE REQUEST STATUS (incoming only) ====================
    var _updatingRequestIds = {};

    function updateRequestStatus(selectEl) {
        var requestId = selectEl.getAttribute('data-request-id');
        var newStatus = selectEl.value;

        if (!requestId || !newStatus) return;

        if (_updatingRequestIds[requestId]) return;
        _updatingRequestIds[requestId] = true;

        selectEl.disabled = true;

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'update_request_status=1&request_id=' + encodeURIComponent(requestId) + '&new_status=' + encodeURIComponent(newStatus)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _updatingRequestIds[requestId] = false;

            if (data.success) {
                loadIncomingRequests();
                showVpsAlert(
                    newStatus === 'accept' ? 'Request accepted. User added as follower.' : 'Request rejected.',
                    'Success'
                );
            } else {
                showVpsAlert(data.message || 'Failed to update status', 'Error');
                selectEl.value = '';
                selectEl.disabled = false;
                loadIncomingRequests();
            }
        })
        .catch(function() {
            _updatingRequestIds[requestId] = false;
            showVpsAlert('Network error. Please try again.', 'Error');
            selectEl.value = '';
            selectEl.disabled = false;
        });
    }

    // ==================== HOST MODAL ====================
    function lockBodyScroll() {
        if (document.body.dataset.vpsModalScrollLocked === '1') return;
        var scrollY = window.scrollY || window.pageYOffset || 0;
        document.body.dataset.vpsModalScrollLocked = '1';
        document.body.dataset.vpsModalScrollY = scrollY;
        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + scrollY + 'px';
    }

    function unlockBodyScroll() {
        if (document.body.dataset.vpsModalScrollLocked !== '1') return;
        var scrollY = parseInt(document.body.dataset.vpsModalScrollY || '0', 10) || 0;
        document.body.dataset.vpsModalScrollLocked = '0';
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, scrollY);
    }

    function showHostModal(ownerId) {
        currentHostOwnerId = ownerId;
        var modal = document.getElementById('vpsHostModal');
        var body = document.getElementById('vpsHostModalBody');
        var actions = document.getElementById('vpsHostModalActions');

        body.innerHTML = '<div class="vps-modal-loading">Loading...</div>';
        actions.innerHTML = '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';

        modal.classList.add('active');
        lockBodyScroll();

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'get_host_details=1&owner_id=' + encodeURIComponent(ownerId)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var h = data.host;
                body.innerHTML =
                    '<div class="vps-modal-row">' +
                        '<span class="vps-modal-label">Full Name</span>' +
                        '<span class="vps-modal-value">' + escapeHtml(h.fullname) + '</span>' +
                    '</div>' +
                    '<div class="vps-modal-row">' +
                        '<span class="vps-modal-label">Server Location</span>' +
                        '<span class="vps-modal-value">' + escapeHtml(h.server_location || 'N/A') + '</span>' +
                    '</div>' +
                    '<div class="vps-modal-row">' +
                        '<span class="vps-modal-label">Subscription Duration</span>' +
                        '<span class="vps-modal-value">' + escapeHtml(String(h.subscription_duration || 0)) + ' days</span>' +
                    '</div>' +
                    '<div class="vps-modal-row">' +
                        '<span class="vps-modal-label">Current Hosts</span>' +
                        '<span class="vps-modal-value">' + h.current_hosts + ' / ' + h.max_hosts + '</span>' +
                    '</div>';

                var actionsHtml = '';

                if (h.is_self) {
                    actionsHtml += '<button class="vps-modal-close" disabled>Your VPS</button>';
                } else if (h.is_follower_lock || isAlreadyFollower) {
                    actionsHtml += '<button class="vps-modal-close" disabled>Already a Follower</button>';
                } else if (h.is_full) {
                    actionsHtml += '<button class="vps-modal-close" disabled>Full</button>';
                } else if (h.already_requested) {
                    actionsHtml += '<button class="vps-modal-close requested" disabled>Requested</button>';
                } else {
                    actionsHtml += '<button class="vps-btn-confirm" onclick="requestVpsSpace(' + h.owner_id + ')">Request Space</button>';
                }

                actionsHtml += '<button class="vps-modal-close" onclick="closeVpsHostModal()">Close</button>';
                actions.innerHTML = actionsHtml;
            } else {
                body.innerHTML = '<div class="vps-modal-error">' + escapeHtml(data.message || 'Unable to load host details.') + '</div>';
            }
        })
        .catch(function() {
            body.innerHTML = '<div class="vps-modal-error">Network error. Please try again.</div>';
        });
    }

    function closeVpsHostModal() {
        document.getElementById('vpsHostModal').classList.remove('active');
        unlockBodyScroll();
        currentHostOwnerId = null;
    }

    function requestVpsSpace(ownerId) {
        if (!ownerId) return;

        if (isAlreadyFollower) {
            showVpsAlert('You are already a follower of another VPS. You cannot request space from other hosts.', 'Error');
            return;
        }

        var btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Submitting...';

        fetch('dev_vps.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'request_vps_space=1&owner_id=' + encodeURIComponent(ownerId)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                btn.textContent = 'Requested';
                btn.className = 'vps-modal-close requested';
                closeVpsHostModal();
                showVpsAlert(data.message || 'Request submitted successfully!', 'Success');
            } else {
                btn.disabled = false;
                btn.textContent = 'Request Space';
                showVpsAlert(data.message || 'Unable to submit request.', 'Error');
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Request Space';
            showVpsAlert('Network error. Please try again.', 'Error');
        });
    }

    // ==================== HELPERS ====================
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function escapeAttr(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    // ==================== AUTO-REFRESH WHILE TAB IS OPEN ====================
    function refreshVisibleRequestLists() {
        var sentTab = document.querySelector('.vps-tab[data-tab="sent"]');
        var sentContent = document.getElementById('vpsTabSent');
        if (sentTab && sentTab.classList.contains('active') && sentContent && sentContent.classList.contains('active')) {
            loadSentRequests();
        }

        if (userOwnsVps) {
            var incomingTab = document.querySelector('.vps-tab[data-tab="incoming"]');
            var incomingContent = document.getElementById('vpsTabIncoming');
            if (incomingTab && incomingTab.classList.contains('active') && incomingContent && incomingContent.classList.contains('active')) {
                loadIncomingRequests();
            }

            var folTab = document.querySelector('.vps-tab[data-tab="followers"]');
            var folContent = document.getElementById('vpsTabFollowers');
            if (folTab && folTab.classList.contains('active') && folContent && folContent.classList.contains('active')) {
                loadMyFollowers();
            }
        }
    }

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            refreshVisibleRequestLists();
        }
    });

    window.addEventListener('focus', function() {
        refreshVisibleRequestLists();
    });

    var _vpsRefreshTimer = null;
    function startVpsAutoRefresh() {
        if (_vpsRefreshTimer) clearInterval(_vpsRefreshTimer);
        _vpsRefreshTimer = setInterval(function() {
            if (!document.hidden) {
                refreshVisibleRequestLists();
            }
        }, 8000);
    }
    startVpsAutoRefresh();

    window.addEventListener('beforeunload', function() {
        if (_vpsRefreshTimer) {
            clearInterval(_vpsRefreshTimer);
            _vpsRefreshTimer = null;
        }
    });

    // ==================== SIDEBAR NAVIGATION ====================
    (function() {
        var sidebar = document.getElementById('sidebarNav');
        var desktopToggle = document.getElementById('sidebarToggle');
        var mobileMenuBtn = document.getElementById('mobileMenuBtn');
        var overlay = document.getElementById('sidebarOverlay');
        var body = document.body;

        if (!sidebar) return;

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function openMobileSidebar() {
            sidebar.classList.add('expanded');
            overlay.classList.add('active');
            body.style.overflow = 'hidden';
        }

        function closeMobileSidebar() {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
        }

        function toggleDesktopSidebar() {
            var isExpanded = sidebar.classList.contains('expanded');
            if (isExpanded) {
                sidebar.classList.remove('expanded');
                body.classList.remove('sidebar-expanded-desktop');
            } else {
                sidebar.classList.add('expanded');
                body.classList.add('sidebar-expanded-desktop');
            }
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (isMobile()) openMobileSidebar();
            });
        }

        if (desktopToggle) {
            desktopToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (!isMobile()) toggleDesktopSidebar();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                if (isMobile()) closeMobileSidebar();
            });
        }

        document.querySelectorAll('.sidebar-menu-item').forEach(function(item) {
            item.addEventListener('click', function() {
                if (isMobile()) {
                    setTimeout(closeMobileSidebar, 150);
                }
            });
        });

        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (isMobile()) {
                    body.classList.remove('sidebar-expanded-desktop');
                    sidebar.classList.remove('expanded');
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                } else {
                    overlay.classList.remove('active');
                    body.style.overflow = '';
                    if (sidebar.classList.contains('expanded')) {
                        body.classList.add('sidebar-expanded-desktop');
                    }
                }
            }, 150);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isMobile()) {
                closeMobileSidebar();
            }
        });

        if (isMobile()) {
            sidebar.classList.remove('expanded');
            overlay.classList.remove('active');
            body.style.overflow = '';
            body.classList.remove('sidebar-expanded-desktop');
        } else {
            sidebar.classList.remove('expanded');
            body.classList.remove('sidebar-expanded-desktop');
        }
    })();

    // ==================== EXPORTS ====================
    window.switchVpsTab = switchVpsTab;
    window.showHostModal = showHostModal;
    window.closeVpsHostModal = closeVpsHostModal;
    window.requestVpsSpace = requestVpsSpace;
    window.loadSentRequests = loadSentRequests;
    window.loadIncomingRequests = loadIncomingRequests;
    window.loadMyFollowers = loadMyFollowers;
    window.updateRequestStatus = updateRequestStatus;
    window.deleteRequestClicked = deleteRequestClicked;
    window.removeFollowerClicked = removeFollowerClicked;
    window.closeVpsConfirmModal = closeVpsConfirmModal;
    window.confirmModalAction = confirmModalAction;
    window.openVpsConfirm = openVpsConfirm;
    window.showVpsAlert = showVpsAlert;
    window.closeVpsAlertModal = closeVpsAlertModal;
    window.refreshVisibleRequestLists = refreshVisibleRequestLists;
</script>

</body>
</html>