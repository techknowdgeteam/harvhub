<?php
/** CLI cron entry point: expire VPS and programme VPS revocations after the 2-day grace period. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/mysqlconnection.php';
if (!function_exists('getAvailablePdoConnection')) {
    fwrite(STDERR, "getAvailablePdoConnection() is not available.\n");
    exit(1);
}
$pdo = getAvailablePdoConnection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(1);
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
require_once __DIR__ . '/vps_revocation_helpers.php';
vpsRevocationEnsureSchema($pdo);
$count = vpsRevocationProcessExpired($pdo, 100);
echo 'Expired VPS revocations processed: ' . $count . PHP_EOL;
