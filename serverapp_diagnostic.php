<?php
/**
 * Temporary, read-only/repair-equivalent diagnostic for the HarvHub Server App shell.
 * Remove this file from the server immediately after diagnosis.
 */
declare(strict_types=1);

const SERVERAPP_DIAGNOSTIC_KEY = 'hhdiag-20261110-6f8c2e91b4a74d4db9c17e35';

$providedKey = (string)($_GET['key'] ?? '');
if ($providedKey === '' || !hash_equals(SERVERAPP_DIAGNOSTIC_KEY, $providedKey)) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');

function diagH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function diagColumns(PDO $pdo, string $table): array {
    $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
    $q->execute([$table]);
    return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
}
function diagTableExists(PDO $pdo, string $table): bool {
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $q->execute([$table]);
    return (int)$q->fetchColumn() > 0;
}

$checks = [];
$pdo = null;
$stage = 'Checking mysqlconnection.php file';
$failure = null;
$failureClass = '';
$sqlState = '';

try {
    $connectionFile = __DIR__ . '/mysqlconnection.php';
    if (!is_file($connectionFile) || !is_readable($connectionFile)) {
        throw new RuntimeException('mysqlconnection.php is missing or not readable in this directory.');
    }
    $checks[] = ['mysqlconnection.php', true, 'Found and readable in the same directory as this diagnostic file.'];

    $stage = 'Loading mysqlconnection.php';
    require_once $connectionFile;
    if (!function_exists('getAvailablePdoConnection')) {
        throw new RuntimeException('The file did not define getAvailablePdoConnection().');
    }
    $checks[] = ['getAvailablePdoConnection()', true, 'Function exists.'];

    $stage = 'Opening PDO database connection';
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('getAvailablePdoConnection() did not return a PDO instance.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $checks[] = ['PDO connection', true, 'Connected using PDO driver: ' . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . '.'];

    $stage = 'Testing the selected database';
    $databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === false || $databaseName === null || $databaseName === '') {
        throw new RuntimeException('The PDO connection opened, but no default database is selected. Check the database name in mysqlconnection.php.');
    }
    $pdo->query('SELECT 1')->fetchColumn();
    $checks[] = ['Selected database', true, 'A database is selected and SELECT 1 succeeded. Database name is intentionally hidden.'];

    $stage = 'Loading serverapp_schema.php';
    $schemaFile = __DIR__ . '/serverapp_schema.php';
    if (!is_file($schemaFile) || !is_readable($schemaFile)) {
        throw new RuntimeException('serverapp_schema.php is missing or not readable in this directory.');
    }
    require_once $schemaFile;
    if (!function_exists('serverAppEnsureConfigurationSchema')) {
        throw new RuntimeException('serverapp_schema.php did not define serverAppEnsureConfigurationSchema().');
    }
    $checks[] = ['serverapp_schema.php', true, 'Schema file loaded and bootstrap function exists.'];

    $stage = 'Running table-only configuration schema bootstrap';
    serverAppEnsureConfigurationSchema($pdo);
    $checks[] = ['Schema bootstrap', true, 'serverAppEnsureConfigurationSchema() completed successfully.'];

    $stage = 'Testing Server App shell queries';
    $theme = $pdo->query('SELECT dark_mode FROM server_theme_settings WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $credentials = $pdo->query('SELECT admin_login_id,admin_password_hash FROM server_admin_credentials WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!is_array($theme)) throw new RuntimeException('The server_theme_settings row could not be read.');
    if (!is_array($credentials)) throw new RuntimeException('The server_admin_credentials row could not be read.');
    $checks[] = ['Shell startup queries', true, 'Theme and administrator credential rows are readable. Credential values are not displayed.'];

    $expected = [
        'server_payment_methods' => ['id','method_name','account_or_address','additional_info','company_name','sort_order','is_enabled'],
        'server_tiers' => ['tier','minimum_deposit','maximum_revenue','maximum_deposit','description'],
        'server_brokers' => ['id','broker_name','broker_url','sort_order','is_enabled'],
        'server_requirements' => ['id','minimum_deposit','min_broker_balance','contract_duration','server_share_percent','user_share_percent','min_profit_for_split','expiry_threshold_days'],
        'server_operational_settings' => ['id','day_challenge_duration','weekly_challenge_duration','monthly_challenge_duration','trades_setup_per_group'],
        'server_mailer_settings' => ['id','mailer_email','mailer_password'],
        'server_admin_credentials' => ['id','admin_login_id','admin_password_hash'],
        'server_theme_settings' => ['id','dark_mode']
    ];
    foreach ($expected as $table => $requiredColumns) {
        $stage = 'Checking table structure: ' . $table;
        if (!diagTableExists($pdo, $table)) {
            throw new RuntimeException('Required table is missing: ' . $table . '.');
        }
        $actual = diagColumns($pdo, $table);
        $missing = array_values(array_diff($requiredColumns, array_keys($actual)));
        if ($missing) {
            throw new RuntimeException('Table ' . $table . ' is missing required columns: ' . implode(', ', $missing) . '.');
        }
    }
    $checks[] = ['Required configuration tables', true, 'All eight tables contain the columns expected by the latest Server Configuration.'];
} catch (Throwable $e) {
    $failure = $e->getMessage();
    $failureClass = get_class($e);
    if ($e instanceof PDOException) {
        $info = $e->errorInfo ?? null;
        if (is_array($info) && isset($info[0])) $sqlState = (string)$info[0];
    }
    error_log('HarvHub Server App diagnostic failed at [' . $stage . ']: ' . $failure);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HarvHub Server App Diagnostic</title>
<style>
body{font:15px/1.55 system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:#f6f6f6;color:#171717;margin:0;padding:24px}
main{max-width:900px;margin:0 auto;background:#fff;border:1px solid #ddd;border-radius:12px;padding:22px}
h1{font-size:22px;margin:0 0 8px}.note{color:#555;margin:0 0 18px}.status{padding:12px 14px;border-radius:8px;margin:14px 0;font-weight:700}
.ok{background:#e8f6ed;color:#155d2b}.bad{background:#fff0f0;color:#9b1c1c}
table{width:100%;border-collapse:collapse;margin-top:12px}th,td{text-align:left;padding:10px;border-bottom:1px solid #e5e5e5;vertical-align:top}th{background:#f0f0f0}
code{overflow-wrap:anywhere}.small{font-size:13px;color:#555;margin-top:18px}
</style>
</head>
<body>
<main>
<h1>HarvHub Server App — Startup Diagnostic</h1>
<p class="note">This diagnostic does not display database credentials or account data. It tests the same database and schema-bootstrap steps used by serverapp.php.</p>
<?php if ($failure === null): ?>
<div class="status ok">All startup checks passed.</div>
<p>The database connection, table-only schema bootstrap and Server App shell queries all succeeded. If <code>serverapp.php</code> still shows the generic error, verify that the files were uploaded into this same directory and inspect the PHP error log for a different active copy/path.</p>
<?php else: ?>
<div class="status bad">Startup failed at: <?= diagH($stage) ?></div>
<table>
<tr><th>Exception type</th><td><?= diagH($failureClass) ?></td></tr>
<?php if ($sqlState !== ''): ?><tr><th>SQLSTATE</th><td><code><?= diagH($sqlState) ?></code></td></tr><?php endif; ?>
<tr><th>Exact error</th><td><code><?= diagH($failure) ?></code></td></tr>
</table>
<?php endif; ?>
<table>
<thead><tr><th>Check</th><th>Result</th><th>Details</th></tr></thead>
<tbody>
<?php foreach ($checks as [$label,$ok,$details]): ?>
<tr><td><?= diagH($label) ?></td><td><?= $ok ? 'PASS' : 'FAIL' ?></td><td><?= diagH($details) ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="small"><strong>Security:</strong> Copy the diagnostic result, then immediately delete <code>serverapp_diagnostic.php</code> from your hosting directory. Do not leave this temporary diagnostic endpoint online.</p>
</main>
</body>
</html>
