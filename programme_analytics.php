<?php
// programme_analytics.php — Programme analytics view for the trader shell
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

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { unset($_SESSION['user_email'], $_SESSION['auth_role']); header("Location: index.php?role=developer"); exit; }

$userId   = (int)$user['id'];
$darkMode = !empty($user['dark_mode']);

$selectedProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
$programme = null;
if ($selectedProgrammeId > 0) {
    $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $q->execute([$selectedProgrammeId, $userId]);
    $programme = $q->fetch(PDO::FETCH_ASSOC);
}

function esc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Fetch analytics rows
$analyticsRows = [];
if ($programme) {
    try {
        $q = $pdo->prepare("
            SELECT *
            FROM programme_analytics
            WHERE programme_id = ?
            ORDER BY FIELD(period, 'daily', 'weekly', 'monthly'), id ASC
        ");
        $q->execute([(int)$programme['id']]);
        $analyticsRows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $analyticsRows = [];
    }
}

// Columns we expose — match your DESCRIBE output
$columns = [
    'id'                         => 'ID',
    'sub_account_id'             => 'Sub Account',
    'programme_id'               => 'Programme',
    'period'                     => 'Period',
    'winrate'                    => 'Winrate',
    'lossrate'                   => 'Lossrate',
    'risk_reward'                => 'Risk / Reward',
    'consecutive_sequential_loss'=> 'Consecutive Seq. Loss',
    'highest_drawdown'           => 'Highest Drawdown',
    'won_trades_risk_reward'     => 'Won Trades (R:R)',
    'lost_trades_risk_reward'    => 'Lost Trades (R:R)',
    'total_trades_risk_reward'   => 'Total Trades (R:R)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title>Programme Analytics</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    :root{
        --pa-bg: var(--bg, #f4f8f7);
        --pa-card: var(--bg-card, #ffffff);
        --pa-text: var(--text, #12211d);
        --pa-muted: var(--text-muted, #60736d);
        --pa-accent: var(--accent, #12a36b);
        --pa-border: var(--border-color, #dfe9e5);
        --pa-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06));
        --pa-radius: var(--radius, 16px);
    }

    /* Pre-paint the dark bg based on system preference so there is
       no white flash before the parent theme message arrives. */
    @media (prefers-color-scheme: dark) {
        html:not(.force-light) {
            --pa-bg: #0c1311;
            --pa-card: #131d1a;
            --pa-text: #e8f2ee;
            --pa-muted: #93a8a1;
            --pa-border: #22322d;
            --pa-shadow: 0 10px 30px rgba(0,0,0,.4);
        }
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    /* Both html and body paint the bg + fill the viewport so no
       white strip appears anywhere below content in dark mode. */
    html, body {
        background: var(--pa-bg) !important;
        color: var(--pa-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55;
        -webkit-tap-highlight-color: transparent;
        min-height: 100vh;
        min-height: 100dvh;
    }

    body.dark-mode {
        --pa-bg:#0c1311; --pa-card:#131d1a; --pa-text:#e8f2ee; --pa-muted:#93a8a1;
        --pa-border:#22322d; --pa-shadow: 0 10px 30px rgba(0,0,0,.4);
        background: var(--pa-bg) !important;
    }

    body::-webkit-scrollbar { display: none; }

    .pa-page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px 20px 100px;
        width: 100%;
        min-height: 100vh;
        min-height: 100dvh;
        background: var(--pa-bg);
    }
    @media (max-width: 480px) { .pa-page { padding: 16px 14px 90px; } }

    .pa-header { margin-bottom: 18px; }
    .pa-title { font-size: 1.15rem; font-weight: 800; letter-spacing: -0.3px; }
    .pa-sub { font-size: 0.85rem; color: var(--pa-muted); margin-top: 20px; }

    .pa-empty {
        padding: 32px 20px; text-align: center; color: var(--pa-muted);
        border: 1px dashed var(--pa-border); border-radius: 14px; background: var(--pa-card);
    }
    .pa-empty i { font-size: 1.6rem; margin-bottom: 10px; color: var(--pa-accent); display: block; }
    .pa-empty strong { display: block; color: var(--pa-text); font-size: .98rem; margin-bottom: 4px; }
    .pa-empty p { font-size: .85rem; }

    .pa-table-wrap {
        background: var(--pa-card);
        border: 1px solid var(--pa-border);
        border-radius: var(--pa-radius);
        box-shadow: var(--pa-shadow);
        overflow: hidden;
    }
    .pa-table-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    table.pa-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1100px;
        font-size: 0.86rem;
        background: var(--pa-card);
    }
    table.pa-table th, table.pa-table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--pa-border);
        white-space: nowrap;
    }
    table.pa-table thead th {
        background: var(--pa-bg);
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--pa-muted);
        font-weight: 800;
        /* No sticky: sticky thead was leaving a white band in dark mode */
    }
    table.pa-table tbody tr:last-child td { border-bottom: none; }
    table.pa-table tbody tr:hover td { background: rgba(46,204,143,0.06); }

    .pa-period-chip {
        display: inline-block; padding: 3px 10px; border-radius: 999px;
        font-size: 0.72rem; font-weight: 800; text-transform: capitalize;
        background: rgba(46,204,143,0.12); color: var(--pa-accent);
    }
    .pa-period-chip.daily   { background: rgba(46,204,143,0.12); color: var(--pa-accent); }
    .pa-period-chip.weekly  { background: rgba(52,152,219,0.14); color: #3498db; }
    .pa-period-chip.monthly { background: rgba(184,117,19,0.14); color: #b87513; }

    .pa-num { font-variant-numeric: tabular-nums; }
    .pa-null { color: var(--pa-muted); font-style: italic; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="pa-page">
    <div class="pa-header">
        <div class="pa-title">Programme Analytics</div>
        <div class="pa-sub">
            <?php if ($programme): ?>
                 &nbsp;•&nbsp; <strong><?= esc_h($programme['program_name'] ?: 'Unnamed Programme') ?></strong> Analytics
            <?php else: ?>
                No programme selected.
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$programme): ?>
        <div class="pa-empty">
            <i class="fa-solid fa-diagram-project"></i>
            <strong>No programme selected</strong>
            <p>Choose or create a programme from the header menu.</p>
        </div>
    <?php elseif (!$analyticsRows): ?>
        <div class="pa-empty">
            <i class="fa-solid fa-chart-line"></i>
            <strong>No analytics yet</strong>
            <p>Analytics will appear here once your programme has generated trades.</p>
        </div>
    <?php else: ?>
        <div class="pa-table-wrap">
            <div class="pa-table-scroll">
                <table class="pa-table">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $col => $label): ?>
                                <th><?= esc_h($label) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analyticsRows as $row): ?>
                            <tr>
                                <?php foreach ($columns as $col => $label): ?>
                                    <?php
                                        $val = $row[$col] ?? null;
                                        if ($col === 'period') {
                                            $cls = 'pa-period-chip ' . preg_replace('/[^a-z]/i', '', (string)$val);
                                            echo '<td><span class="' . esc_h($cls) . '">' . esc_h((string)$val) . '</span></td>';
                                        } elseif ($val === null || $val === '') {
                                            echo '<td class="pa-null">—</td>';
                                        } else {
                                            echo '<td class="pa-num">' . esc_h((string)$val) . '</td>';
                                        }
                                    ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    // Parent theme sync — parent posts { type: 'theme', dark: bool }
    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') {
            var dark = !!e.data.dark;
            document.body.classList.toggle('dark-mode', dark);
            document.documentElement.classList.toggle('force-light', !dark);
        }
    });

    // Ask parent for current theme on load
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
})();
</script>

</body>
</html>