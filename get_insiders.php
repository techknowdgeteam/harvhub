<?php
// get_insiders.php - Dedicated endpoint for fetching insiders data
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

$host       = 'sql312.infinityfree.com';
$dbname     = 'if0_40473107_harvhub';
$dbUsername = 'if0_40473107';
$dbPassword = 'InDQmdl53FZ85';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUsername, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Get parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
    $format = isset($_GET['format']) ? $_GET['format'] : 'json'; // json or csv
    
    // Query the insiders table
    $stmt = $pdo->prepare("SELECT * FROM insiders ORDER BY id DESC LIMIT :limit");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $rows = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Output as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="insiders_data.csv"');
        
        $output = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
    } else {
        // Output as JSON
        echo json_encode([
            'status' => 'success',
            'count' => count($rows),
            'data' => $rows,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>