<?php
// phpmyadmin_tablesfetch.php
// Hardcoded credentials + safe for fetch() calls

$host       = 'sql312.infinityfree.com';
$dbname     = 'if0_40473107_harvhub';
$dbUsername = 'if0_40473107';
$dbPassword = 'InDQmdl53FZ85';

// Optional: Only allow requests from your own domain (better than the old block)
if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'https://yourdomain.com') {  // change to your real domain or remove line
    // Remove this whole if() for testing / local use
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  // Remove in production if you want tighter security
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// === NO MORE "Direct access denied" BLOCK ===

function handleDatabaseRequest($host, $dbname, $dbUsername, $dbPassword, $table = null, $sqlQuery = null) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);

        // Custom SQL query
        if ($sqlQuery !== null) {
            $stmt = $pdo->query($sqlQuery);
            $result = [];

            if ($stmt->columnCount() > 0) {
                $result['rows'] = $stmt->fetchAll();
                $result['columnMeta'] = [];
                for ($i = 0; $i < $stmt->columnCount(); $i++) {
                    $result['columnMeta'][] = $stmt->getColumnMeta($i);
                }
            } else {
                $result['affectedRows'] = $stmt->rowCount();
            }

            return ['status' => 'success', 'data' => $result, 'message' => 'Query executed'];
        }

        // Get columns of a table
        elseif ($table !== null) {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            $columns = $stmt->fetchAll();

            return ['status' => 'success', 'columns' => $columns, 'message' => "Columns for `$table`"];
        }

        // List all tables
        else {
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return ['status' => 'success', 'tables' => $tables, 'message' => 'Tables retrieved'];
        }

    } catch (PDOException $e) {
        return [
            'status'   => 'error',
            'message'  => 'Database error: ' . $e->getMessage(),
            'tables'   => [],
            'columns'  => [],
            'data'     => []
        ];
    }
}

// Get parameters safely
$table    = $_GET['table'] ?? null;
$sqlQuery = $_POST['sql_query'] ?? null;

// Return JSON
echo json_encode(handleDatabaseRequest($host, $dbname, $dbUsername, $dbPassword, $table, $sqlQuery));
?>