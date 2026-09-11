<?php
    // dev_app.php — Developer Gate Only
    session_start();

    // ==================== CHECK LOGIN ====================
    if (!isset($_SESSION['user_email'])) {
        header("Location: app.php");
        exit;
    }

    $email = strtolower($_SESSION['user_email']);

    // ==================== DATABASE CONNECTION ====================
    require_once 'usersdb.php';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed.");
    }

    // ==================== FETCH USER (for dark mode) ====================
    $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: app.php");
        exit;
    }

    $darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
    $darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

    // ==================== DEVELOPER CHECK ====================
    $devStmt = $pdo->prepare("SELECT * FROM developers WHERE email = ? LIMIT 1");
    $devStmt->execute([$email]);
    $developer = $devStmt->fetch(PDO::FETCH_ASSOC);

    // If the user IS a developer, redirect them to the main dev page
    // (dev_dashboard.php). Otherwise, show the access-restricted modal.
    if ($developer) {
        header("Location: dev_dashboard.php");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>🌾Dev — Access Restricted</title>
<?php include 'dev_style.php'; ?>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

    <!-- ==================== NOT A DEVELOPER MODAL ==================== -->
    <div class="dev-modal-overlay" id="notDeveloperModal">
        <div class="dev-modal-card">
            <div class="dev-modal-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h2>Access Restricted</h2>
            <p>
                You are currently <strong>not a developer</strong>.
                You need an ID to become one.
            </p>
            <p class="dev-modal-sub">
                Contact support at
                <a href="mailto:harvhub12@gmail.com" class="dev-modal-mail">harvhub12@gmail.com</a>
                and join the queue.
            </p>
            <button class="dev-modal-btn" id="devModalOkBtn">
                <i class="fas fa-check-circle"></i> Okay
            </button>
        </div>
    </div>

    <script>
        (function() {
            // ==================== BODY SCROLL LOCK ====================
            var bodyEl = document.body;
            var scrollLockActive = false;
            var savedScrollY = 0;
            var savedOverflow = '';
            var savedPosition = '';
            var savedTop = '';
            var savedWidth = '';

            function lockBodyScroll() {
                if (scrollLockActive) return;
                savedScrollY = window.scrollY || window.pageYOffset || 0;
                savedOverflow = bodyEl.style.overflow;
                savedPosition = bodyEl.style.position;
                savedTop = bodyEl.style.top;
                savedWidth = bodyEl.style.width;

                bodyEl.style.overflow = 'hidden';
                bodyEl.style.position = 'fixed';
                bodyEl.style.top = '-' + savedScrollY + 'px';
                bodyEl.style.width = '100%';
                scrollLockActive = true;
            }

            function unlockBodyScroll() {
                if (!scrollLockActive) return;
                bodyEl.style.overflow = savedOverflow;
                bodyEl.style.position = savedPosition;
                bodyEl.style.top = savedTop;
                bodyEl.style.width = savedWidth;
                window.scrollTo(0, savedScrollY);
                scrollLockActive = false;
            }

            function syncScrollLock() {
                var anyModalVisible = false;
                var overlays = document.querySelectorAll(
                    '.dev-modal-overlay, .modal-overlay, .modal, .modal-backdrop'
                );
                for (var i = 0; i < overlays.length; i++) {
                    var el = overlays[i];
                    if (!el) continue;
                    var style = window.getComputedStyle(el);
                    if (style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0') {
                        anyModalVisible = true;
                        break;
                    }
                }
                if (anyModalVisible) {
                    lockBodyScroll();
                } else {
                    unlockBodyScroll();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', syncScrollLock);
            } else {
                syncScrollLock();
            }

            if (window.MutationObserver) {
                var observer = new MutationObserver(function() {
                    syncScrollLock();
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }

            window.addEventListener('resize', syncScrollLock);

            window.lockBodyScroll = lockBodyScroll;
            window.unlockBodyScroll = unlockBodyScroll;
            window.syncScrollLock = syncScrollLock;

            // ==================== OK BUTTON ====================
            var okBtn = document.getElementById('devModalOkBtn');
            if (okBtn) {
                okBtn.addEventListener('click', function() {
                    unlockBodyScroll();
                    window.location.href = 'app.php';
                });
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    unlockBodyScroll();
                    window.location.href = 'app.php';
                }
            });
        })();
    </script>
</body>
</html>