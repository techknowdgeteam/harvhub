<?php
    $host = "sql312.infinityfree.com";
    $dbname = "if0_40473107_harvhub";
    $user = "if0_40473107";
    $pass = "InDQmdl53FZ85";
    $serverAccountTable = "server_account";
    $harvhubTable = "harvhub_server";
    $harvhubTable = "harvhub";

    $message = "";
    $authenticated = false;
    $initialSetupRequired = true;
    $currentView = $_GET['view'] ?? 'menu'; // Default to menu/navigation screen

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        die("Database connection failed: " . $e->getMessage());
    }
?>