<?php
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

$email = strtolower($_SESSION['user_email']);

// ==================== FETCH USER DATA ====================
$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.php");
    exit;
}

// ==================== FETCH AVAILABLE TRADERS ====================
// Only show developers where advertisement = 'approved'
$traders = [];
$stmt = $pdo->prepare("
    SELECT id, email, broker, server, login, application_status, advertisement 
    FROM developers 
    WHERE advertisement = 'approved' 
    AND application_status = 'approved'
    ORDER BY id DESC
");
$stmt->execute();
$traders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== HANDLE INVEST WITH TRADER ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invest_with_trader'])) {
    $trader_email = $_POST['trader_email'] ?? '';
    
    if (!empty($trader_email)) {
        $stmt = $pdo->prepare("UPDATE harvhub SET invested_with = ? WHERE email = ?");
        $stmt->execute([$trader_email, $email]);
        
        header("Location: app.php?invested=1");
        exit;
    }
}

// ==================== GET USER DATA ====================
$fullname = $user['fullname'] ?? '';
$application_status = $user['application_status'] ?? '';
$invested_with = $user['invested_with'] ?? '';

// Dark mode
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<title>Find Trade Manager - HarvHub</title>
<?php include 'style.php'; ?>
<style>
    /* ============================================================
    ACCOUNT MANAGER STYLES - INDEPENDENT
    ============================================================ */
    body {
        padding-top: 0;
        padding-bottom: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .account-manager-container {
        max-width: 700px;
        width: 100%;
        padding: 20px;
        margin: 20px;
    }

    .account-manager-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        padding: 32px 28px;
        box-shadow: var(--shadow-lg);
    }

    .account-manager-card .logo-area {
        text-align: center;
        margin-bottom: 24px;
    }

    .account-manager-card .logo-area h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--accent);
        margin: 0;
        letter-spacing: -0.5px;
    }

    .account-manager-card .logo-area p {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-top: 4px;
    }

    .account-manager-card .user-info {
        background: var(--bg);
        border-radius: var(--radius-sm);
        padding: 12px 16px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .account-manager-card .user-info .label {
        font-size: 0.7rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .account-manager-card .user-info .value {
        font-weight: 600;
        color: var(--text);
        font-size: 0.95rem;
    }

    .account-manager-card .user-info .status-badge {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 20px;
    }

    .status-badge.approved {
        background: var(--success-bg);
        color: var(--success);
    }

    .status-badge.pending {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .status-badge.declined {
        background: var(--danger-bg);
        color: var(--danger);
    }

    /* ===== TRADERS GRID ===== */
    .traders-grid {
        display: flex;
        flex-direction: column;
        gap: 14px;
        margin: 20px 0;
    }

    .trader-card {
        background: var(--bg);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 18px 20px;
        transition: all 0.2s ease;
    }

    .trader-card:hover {
        border-color: var(--accent);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    }

    .trader-card .trader-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }

    .trader-card .trader-name {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .trader-card .trader-name .trader-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        flex-shrink: 0;
    }

    .trader-card .trader-status {
        font-size: 0.65rem;
        font-weight: 600;
        text-transform: uppercase;
        padding: 3px 12px;
        border-radius: 20px;
        background: var(--success-bg);
        color: var(--success);
    }

    .trader-card .trader-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 8px;
        padding-top: 10px;
        border-top: 1px solid var(--border-color);
    }

    .trader-card .trader-details .detail-item {
        display: flex;
        gap: 6px;
    }

    .trader-card .trader-details .detail-item .detail-label {
        color: var(--text-muted);
    }

    .trader-card .trader-details .detail-item .detail-value {
        color: var(--text);
        font-weight: 500;
    }

    .trader-card .btn-invest {
        margin-top: 12px;
        padding: 10px 24px;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
    }

    .trader-card .btn-invest:hover {
        background: var(--accent-hover);
        transform: scale(1.01);
    }

    .trader-card .btn-invest:active {
        transform: scale(0.98);
    }

    .trader-card .btn-invest:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none;
    }

    /* ===== EMPTY STATE ===== */
    .empty-traders {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .empty-traders .empty-icon {
        font-size: 48px;
        margin-bottom: 12px;
    }

    .empty-traders .empty-text {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }

    .empty-traders .empty-sub {
        font-size: 14px;
        color: var(--text-muted);
    }

    /* ===== INVESTED WITH MESSAGE ===== */
    .invested-message {
        background: var(--success-bg);
        border: 1px solid var(--success);
        border-radius: var(--radius-sm);
        padding: 16px 20px;
        text-align: center;
        margin: 16px 0;
    }

    .invested-message .message-icon {
        font-size: 24px;
        margin-bottom: 4px;
    }

    .invested-message .message-text {
        font-size: 0.95rem;
        color: var(--text);
    }

    .invested-message .message-text strong {
        color: var(--success);
    }

    /* ===== BACK LINK ===== */
    .account-manager-card .back-link {
        text-align: center;
        margin-top: 20px;
    }

    .account-manager-card .back-link a {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.85rem;
        transition: color 0.2s ease;
    }

    .account-manager-card .back-link a:hover {
        color: var(--accent);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 480px) {
        .account-manager-container {
            padding: 12px;
            margin: 10px;
        }

        .account-manager-card {
            padding: 20px 16px;
        }

        .account-manager-card .logo-area h1 {
            font-size: 1.4rem;
        }

        .trader-card .trader-details {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .trader-card {
            padding: 14px 16px;
        }

        .trader-card .trader-name {
            font-size: 0.95rem;
        }

        .account-manager-card .user-info {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    }

    @media (max-width: 360px) {
        .account-manager-card {
            padding: 16px 12px;
        }

        .trader-card .trader-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="account-manager-container">
    <div class="account-manager-card">
        
        <div class="logo-area">
            <h1>🌾 HarvHub</h1>
            <p>Find a Trade Manager</p>
        </div>

        <div class="user-info">
            <span>
                <span class="label">User</span>
                <span class="value"><?= htmlspecialchars($fullname ?: $email) ?></span>
            </span>
            <span>
                <span class="label">Status</span>
                <span class="status-badge <?= strtolower($application_status) ?>">
                    <?= htmlspecialchars($application_status ?: 'Pending') ?>
                </span>
            </span>
        </div>

        <?php if ($invested_with): ?>
            <div class="invested-message">
                <div class="message-icon">✅</div>
                <div class="message-text">
                    You are currently investing with <strong><?= htmlspecialchars($invested_with) ?></strong>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($traders)): ?>
            <div class="empty-traders">
                <div class="empty-icon">🔍</div>
                <div class="empty-text">No Traders Available</div>
                <div class="empty-sub">There are currently no approved trade managers available. Please check back later.</div>
            </div>
        <?php else: ?>
            <div class="traders-grid">
                <?php foreach ($traders as $trader): 
                    $trader_email = $trader['email'];
                    $trader_broker = $trader['broker'] ?? 'N/A';
                    $trader_server = $trader['server'] ?? 'N/A';
                    $trader_login = $trader['login'] ?? 'N/A';
                    $trader_name = explode('@', $trader_email)[0];
                    $initials = strtoupper(substr($trader_name, 0, 2));
                    
                    // Check if already invested with this trader
                    $is_invested = ($invested_with === $trader_email);
                ?>
                    <div class="trader-card">
                        <div class="trader-header">
                            <div class="trader-name">
                                <span class="trader-avatar"><?= htmlspecialchars($initials) ?></span>
                                <?= htmlspecialchars($trader_name) ?>
                            </div>
                            <span class="trader-status">✅ Approved</span>
                        </div>
                        <div class="trader-details">
                            <div class="detail-item">
                                <span class="detail-label">Broker:</span>
                                <span class="detail-value"><?= htmlspecialchars($trader_broker) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Server:</span>
                                <span class="detail-value"><?= htmlspecialchars($trader_server) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Login:</span>
                                <span class="detail-value"><?= htmlspecialchars($trader_login) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status:</span>
                                <span class="detail-value" style="color: var(--success);">Active</span>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="trader_email" value="<?= htmlspecialchars($trader_email) ?>">
                            <button type="submit" name="invest_with_trader" class="btn-invest" <?= $is_invested ? 'disabled' : '' ?>>
                                <?= $is_invested ? '✅ Currently Invested' : 'Invest With This Trader' ?>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="app.php">← Back to Dashboard</a>
        </div>

    </div>
</div>

</body>
</html>