<?php
/** Compatibility entry point. The monolithic serveraccount.php has been retired. */
$oldView = (string)($_GET['view'] ?? 'menu');
$map = [
    'settings' => 'configuration',
    'paid_users' => 'revenue',
    'vps' => 'vps',
    'risk_dictionary' => 'risk',
    'menu' => 'menu'
];
$newView = $map[$oldView] ?? 'menu';
header('Location: serverapp.php?view=' . rawurlencode($newView), true, 302);
exit;
