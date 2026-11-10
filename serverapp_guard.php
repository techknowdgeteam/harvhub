<?php
/** Shared DB/auth/CSRF bootstrap for independent Server App feature pages. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Keep server-admin sessions persistent; there is deliberately no inactivity logout.
    ini_set('session.gc_maxlifetime', '315360000');
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 315360000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// Admin pages and, especially, decrypted VPS detail responses must never be browser-cached.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');

if (!function_exists('serverAppPdo')) {
    function serverAppPdo(): PDO {
        require_once __DIR__ . '/mysqlconnection.php';
        if (!function_exists('getAvailablePdoConnection')) throw new RuntimeException('getAvailablePdoConnection() is missing from mysqlconnection.php.');
        $connection = getAvailablePdoConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('The shared database manager did not return PDO.');
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $connection;
    }
}

try {
    $pdo = serverAppPdo();
    require_once __DIR__ . '/serverapp_schema.php';
    serverAppEnsureConfigurationSchema($pdo);
} catch (Throwable $e) {
    error_log('Server app database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

$serverAppConfig = $pdo->query('SELECT dark_mode FROM server_theme_settings WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: ['dark_mode'=>0];
$serverAppConfig = array_merge($serverAppConfig, $pdo->query('SELECT * FROM server_requirements WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: []);
$serverAppConfig = array_merge($serverAppConfig, $pdo->query('SELECT * FROM server_operational_settings WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: []);
$serverAppDarkMode = (int)($serverAppConfig['dark_mode'] ?? 0) === 1 ? 1 : 0;
$serverAppTheme = $serverAppDarkMode ? 'dark' : 'light';
$serverAppAdminCredentials = [];
$serverAppAdminCredentials = $pdo->query('SELECT admin_login_id, admin_password_hash FROM server_admin_credentials WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$isServerAppAjax = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

// Authentication is persistent until explicit logout. No last_activity or inactivity_timer check.
if (empty($_SESSION['admin_logged_in'])) {
    if ($isServerAppAjax) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        http_response_code(401);
        echo json_encode(['success'=>false,'error'=>'Administrator login required. Sign in to continue.']);
    } else {
        header('Location: serverapp.php', true, 303);
    }
    exit;
}

if (empty($_SESSION['serverapp_csrf'])) $_SESSION['serverapp_csrf'] = bin2hex(random_bytes(32));
$serverAppCsrf = (string)$_SESSION['serverapp_csrf'];

if (!function_exists('serverAppJson')) {
    function serverAppJson(array $payload, int $status=200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
if (!function_exists('serverAppCheckCsrf')) {
    function serverAppCheckCsrf(): bool {
        $sent = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        return isset($_SESSION['serverapp_csrf']) && $sent !== '' && hash_equals((string)$_SESSION['serverapp_csrf'], $sent);
    }
}
if (!function_exists('serverAppVerifyAdmin')) {
    function serverAppVerifyAdmin(PDO $pdo, string $loginId, string $password): bool {
        $q=$pdo->query('SELECT admin_login_id,admin_password_hash FROM server_admin_credentials WHERE id=1 LIMIT 1');
        $row=$q->fetch(PDO::FETCH_ASSOC) ?: [];
        return $loginId !== '' && hash_equals((string)($row['admin_login_id'] ?? ''),$loginId)
            && $password !== '' && password_verify($password,(string)($row['admin_password_hash'] ?? ''));
    }
}
if (!function_exists('serverAppH')) {
    function serverAppH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
?>
