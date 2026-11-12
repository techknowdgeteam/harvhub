<?php
/** Compatibility endpoint: private VPS provisioning is server-admin only. */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Pragma: no-cache');
header('Expires: 0');
http_response_code(403);
$type = strtolower(trim((string)($_GET['type'] ?? 'account')));
$hub = $type === 'programme' ? 'programme_vps.php' : 'vps.php';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPS purchase status</title><style>body{margin:0;padding:24px;background:#f4f6f9;color:#243044;font:15px/1.6 system-ui,sans-serif}.card{max-width:640px;margin:8vh auto;background:#fff;border:1px solid #dce2ea;border-radius:14px;padding:24px;box-shadow:0 8px 30px rgba(20,35,55,.08)}a{display:inline-block;margin-top:12px;padding:10px 15px;border-radius:8px;background:#2563eb;color:#fff;text-decoration:none;font-weight:700}</style></head><body><main class="card"><h1>VPS purchase status</h1><p>Your VPS setup is being processed. You can check its status from your VPS page.</p><a href="<?= htmlspecialchars($hub,ENT_QUOTES,'UTF-8') ?>">Return to VPS hub</a></main></body></html>
