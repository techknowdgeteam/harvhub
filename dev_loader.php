<?php
    // dev_loader.php - SPA Loader for HarvHub

    session_start();

    if (!isset($_SESSION['user_email'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $page = $_GET['page'] ?? 'connect_dev_broker';

    // Allowed SPA pages: broker pages + dev menu
    $allowedPages = ['connect_dev_broker', 'disconnect_dev_broker', 'dev_menu'];

    if (!in_array($page, $allowedPages)) {
        echo json_encode(['error' => 'Invalid page']);
        exit;
    }

    // Map page to actual file
    $pageMap = [
        'connect_dev_broker'    => 'connect_dev_broker.php',
        'disconnect_dev_broker' => 'disconnect_dev_broker.php',
        'dev_menu'              => 'dev_menu.php'
    ];

    $file = $pageMap[$page];

    // Start output buffering to capture the page content
    ob_start();

    // Include the page - it will use the existing session
    include $file;

    $content = ob_get_clean();

    // Remove duplicate bottom navs
    $content = preg_replace('/<nav[^>]*class="[^"]*bottom-nav[^"]*"[^>]*>.*?<\/nav>/is', '', $content);

    // Remove duplicate notification containers
    $content = preg_replace('/<div[^>]*class="[^"]*notification-container[^"]*"[^>]*>.*?<\/div>/is', '', $content);

    // Remove duplicate top headers
    $content = preg_replace('/<div[^>]*class="[^"]*harvhub-header-top[^"]*"[^>]*>.*?<\/div>/is', '', $content);

    // Remove duplicate notification panels
    $content = preg_replace('/<div[^>]*class="[^"]*notification-panel[^"]*"[^>]*>.*?<\/div>/is', '', $content);

    // Remove any duplicate style tags that might cause conflicts
    $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content, 1);

    echo json_encode([
        'success' => true,
        'html' => $content,
        'page' => $page
    ]);
?>