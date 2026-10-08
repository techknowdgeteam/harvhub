<?php
// phpmyadmin_tablesfetch.php
// Multi-statement safe executor with per-statement results.

$host       = 'sql312.infinityfree.com';
$dbname     = 'if0_40473107_harvhub';
$dbUsername = 'if0_40473107';
$dbPassword = 'InDQmdl53FZ85';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Split SQL into statements, respecting quotes and comments.
 */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($inLineComment) {
                if ($ch === "\n") { $inLineComment = false; $current .= $ch; }
                continue;
            }
            if ($inBlockComment) {
                if ($ch === '*' && $next === '/') { $inBlockComment = false; $i++; }
                continue;
            }
            if ($ch === '-' && $next === '-') { $inLineComment = true; $i++; continue; }
            if ($ch === '#') { $inLineComment = true; continue; }
            if ($ch === '/' && $next === '*') { $inBlockComment = true; $i++; continue; }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle && $next === "'") { $current .= "''"; $i++; continue; }
            $inSingle = !$inSingle;
            $current .= $ch;
            continue;
        }
        if ($ch === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble && $next === '"') { $current .= '""'; $i++; continue; }
            $inDouble = !$inDouble;
            $current .= $ch;
            continue;
        }
        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $current .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $trimmed = trim($current);
            if ($trimmed !== '') $statements[] = $trimmed;
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    $trimmed = trim($current);
    if ($trimmed !== '') $statements[] = $trimmed;

    return $statements;
}

/**
 * Execute one statement, capture everything.
 */
function executeSingleStatement(PDO $pdo, string $sql, int $index): array {
    $result = [
        'index'         => $index,
        'sql'           => $sql,
        'status'        => 'unknown',
        'message'       => '',
        'rows'          => [],
        'columnMeta'    => [],
        'affectedRows'  => 0,
        'isReadQuery'   => false,
    ];

    try {
        $stmt = $pdo->query($sql);

        if ($stmt === false) {
            $result['status']  = 'error';
            $result['message'] = 'Query returned false.';
            return $result;
        }

        if ($stmt->columnCount() > 0) {
            $result['isReadQuery'] = true;
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result['rows'] = $rows;

            $meta = [];
            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $m = $stmt->getColumnMeta($i);
                if ($m && isset($m['name'])) $meta[] = ['name' => $m['name']];
            }
            $result['columnMeta'] = $meta;

            if (empty($rows)) {
                $result['status']  = 'empty';
                $result['message'] = 'Query ran successfully but returned 0 rows.';
            } else {
                $result['status']  = 'success';
                $result['message'] = 'Query succeeded with ' . count($rows) . ' row(s).';
            }
        } else {
            $affected = $stmt->rowCount();
            $result['affectedRows'] = $affected;
            $result['status']  = 'success';
            $result['message'] = 'Statement executed. Affected rows: ' . $affected;
        }
    } catch (PDOException $e) {
        $result['status']  = 'error';
        $result['message'] = $e->getMessage();
    } catch (Throwable $e) {
        $result['status']  = 'error';
        $result['message'] = $e->getMessage();
    }

    return $result;
}

function handleDatabaseRequest($host, $dbname, $dbUsername, $dbPassword, $table = null, $sqlQuery = null) {
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $dbUsername, $dbPassword,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        if ($sqlQuery !== null && trim($sqlQuery) !== '') {
            $statements = splitSqlStatements($sqlQuery);

            if (empty($statements)) {
                return [
                    'status'   => 'error',
                    'message'  => 'No executable SQL statements found.',
                    'data'     => [],
                    'results'  => [],
                    'tables'   => [],
                    'columns'  => [],
                ];
            }

            $results = [];
            $successCount = 0;
            $errorCount   = 0;
            $emptyCount   = 0;

            foreach ($statements as $i => $stmtSql) {
                $r = executeSingleStatement($pdo, $stmtSql, $i);
                $results[] = $r;
                if ($r['status'] === 'success')    $successCount++;
                else if ($r['status'] === 'error') $errorCount++;
                else if ($r['status'] === 'empty') $emptyCount++;
            }

            $total = count($results);
            $ok    = $successCount + $emptyCount;

            $parts = [];
            if ($successCount > 0) $parts[] = $successCount . ' succeeded';
            if ($emptyCount   > 0) $parts[] = $emptyCount   . ' returned no rows';
            if ($errorCount   > 0) $parts[] = $errorCount   . ' failed';

            if ($errorCount === 0)          $overallStatus = 'success';
            else if ($ok > 0)               $overallStatus = 'partial';
            else                            $overallStatus = 'error';

            $message = implode(', ', $parts) . ' (of ' . $total . ' statement(s)).';

            // Legacy single-statement shape
            $data = [];
            if ($total === 1) {
                $only = $results[0];
                if ($only['status'] !== 'error') {
                    if ($only['isReadQuery']) {
                        $data = ['rows' => $only['rows'], 'columnMeta' => $only['columnMeta']];
                    } else {
                        $data = ['affectedRows' => $only['affectedRows']];
                    }
                }
            }

            return [
                'status'   => $overallStatus,
                'message'  => $message,
                'data'     => $data,
                'results'  => $results,
                'summary'  => [
                    'total'     => $total,
                    'succeeded' => $successCount,
                    'empty'     => $emptyCount,
                    'failed'    => $errorCount,
                ],
                'tables'   => [],
                'columns'  => [],
            ];
        }

        if ($table !== null && $table !== '') {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $stmt = $pdo->query("SHOW COLUMNS FROM `$safeTable`");
            $columns = $stmt->fetchAll();
            return [
                'status'  => 'success',
                'columns' => $columns,
                'message' => "Columns for `$safeTable`",
            ];
        }

        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return [
            'status'  => 'success',
            'tables'  => $tables,
            'message' => 'Tables retrieved',
        ];
    } catch (PDOException $e) {
        return [
            'status'   => 'error',
            'message'  => 'Database error: ' . $e->getMessage(),
            'tables'   => [],
            'columns'  => [],
            'data'     => [],
            'results'  => [],
        ];
    }
}

$table = null;
$sqlQuery = null;

if (isset($_GET['table']))     $table = $_GET['table'];
if (isset($_GET['sql_query'])) $sqlQuery = $_GET['sql_query'];
if (isset($_POST['table']))     $table = $_POST['table'];
if (isset($_POST['sql_query'])) $sqlQuery = $_POST['sql_query'];

$inputJSON = file_get_contents('php://input');
if ($inputJSON) {
    $input = json_decode($inputJSON, true);
    if (is_array($input)) {
        if (isset($input['table']))     $table = $input['table'];
        if (isset($input['sql_query'])) $sqlQuery = $input['sql_query'];
    }
}

echo json_encode(handleDatabaseRequest($host, $dbname, $dbUsername, $dbPassword, $table, $sqlQuery));