
<?php
/*
 * phpmyadmin_tablesfetch.php
 *
 * Compatible with phpmyadmintemplate.php.
 * Uses mysqlconnection.php and getAvailablePdoConnection().
 *
 * Supports:
 * - Fetching tables
 * - Fetching columns
 * - Executing SQL statements
 * - Dependency-aware ordering of common SQL operations
 * - Duplicate DROP statement detection
 * - JSON-only responses
 */

ob_start();

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function jsonResponse($data, $httpCode = 200)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }

    $json = json_encode(
        $data,
        JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    if ($json === false) {
        http_response_code(500);
        echo '{"status":"error","message":"JSON encoding failed.","tables":[],"columns":[],"data":[],"results":[]}';
    } else {
        echo $json;
    }

    exit;
}

/*
 * Convert fatal PHP errors into JSON when possible.
 */
register_shutdown_function(function () {
    $error = error_get_last();

    if (
        $error &&
        in_array(
            $error['type'],
            array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR),
            true
        )
    ) {
        error_log(
            'phpmyadmin_tablesfetch.php fatal error: ' .
            $error['message'] . ' in ' .
            $error['file'] . ':' . $error['line']
        );

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo '{"status":"error","message":"A PHP error occurred. Check the hosting error log.","tables":[],"columns":[],"data":[],"results":[]}';
    }
});

if (isset($_SERVER['REQUEST_METHOD']) &&
    $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(array('status' => 'success', 'message' => 'OK'));
}

/*
 * Load the known-working database connection.
 */
try {
    require_once __DIR__ . '/mysqlconnection.php';

    if (!function_exists('getAvailablePdoConnection')) {
        throw new RuntimeException(
            'getAvailablePdoConnection() is unavailable.'
        );
    }

    $pdo = getAvailablePdoConnection();

    if (!$pdo instanceof PDO) {
        throw new RuntimeException(
            'The database connection did not return PDO.'
        );
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log(
        'phpmyadmin_tablesfetch.php connection error: ' .
        $e->getMessage()
    );

    jsonResponse(array(
        'status' => 'error',
        'message' => 'Database connection failed. Check mysqlconnection.php and the hosting error log.',
        'tables' => array(),
        'columns' => array(),
        'data' => array(),
        'results' => array()
    ), 500);
}

/*
 * Split SQL into statements without splitting on semicolons inside
 * quoted strings or comments.
 */
function splitSqlStatements($sql)
{
    $statements = array();
    $current = '';
    $length = strlen($sql);

    $single = false;
    $double = false;
    $backtick = false;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($ch === "\n") {
                $lineComment = false;
                $current .= "\n";
            }
            continue;
        }

        if ($blockComment) {
            if ($ch === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }

        if (!$single && !$double && !$backtick) {
            if (
                $ch === '#' ||
                (
                    $ch === '-' &&
                    $next === '-' &&
                    (
                        $i + 2 >= $length ||
                        ctype_space($sql[$i + 2])
                    )
                )
            ) {
                $lineComment = true;

                if ($ch === '-') {
                    $i++;
                }
                continue;
            }

            if ($ch === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === '\\' && ($single || $double)) {
            $current .= $ch;

            if ($next !== '') {
                $current .= $next;
                $i++;
            }
            continue;
        }

        if ($ch === "'" && !$double && !$backtick) {
            if ($single && $next === "'") {
                $current .= "''";
                $i++;
                continue;
            }

            $single = !$single;
            $current .= $ch;
            continue;
        }

        if ($ch === '"' && !$single && !$backtick) {
            if ($double && $next === '"') {
                $current .= '""';
                $i++;
                continue;
            }

            $double = !$double;
            $current .= $ch;
            continue;
        }

        if ($ch === '`' && !$single && !$double) {
            $backtick = !$backtick;
            $current .= $ch;
            continue;
        }

        if (
            $ch === ';' &&
            !$single &&
            !$double &&
            !$backtick
        ) {
            if (trim($current) !== '') {
                $statements[] = trim($current);
            }

            $current = '';
            continue;
        }

        $current .= $ch;
    }

    if (trim($current) !== '') {
        $statements[] = trim($current);
    }

    return $statements;
}

/*
 * Identify the operation and, when possible, its primary table.
 */
function analyzeSqlStatement($sql)
{
    $clean = trim($sql);

    $info = array(
        'type' => 'OTHER',
        'table' => null,
        'sql' => $sql
    );

    $patterns = array(
        'CREATE_DATABASE' => '/^CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
        'CREATE_TABLE' => '/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
        'CREATE_INDEX' => '/^CREATE\s+(?:UNIQUE\s+)?INDEX\s+`?[a-zA-Z0-9_]+`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i',
        'ALTER_TABLE' => '/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i',
        'INSERT' => '/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-zA-Z0-9_]+)`?/i',
        'REPLACE' => '/^REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
        'UPDATE' => '/^UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
        'DELETE' => '/^DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
        'TRUNCATE' => '/^TRUNCATE\s+(?:TABLE\s+)?`?([a-zA-Z0-9_]+)`?/i',
        'DROP_TABLE' => '/^DROP\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
        'DROP_DATABASE' => '/^DROP\s+DATABASE\s+(?:IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i',
        'DROP_INDEX' => '/^DROP\s+INDEX\s+`?[a-zA-Z0-9_]+`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i',
        'SELECT' => '/^(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i'
    );

    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $clean, $matches)) {
            $info['type'] = $type;

            if (isset($matches[1]) &&
                in_array($type, array(
                    'CREATE_DATABASE',
                    'CREATE_TABLE',
                    'CREATE_INDEX',
                    'ALTER_TABLE',
                    'INSERT',
                    'REPLACE',
                    'UPDATE',
                    'DELETE',
                    'TRUNCATE',
                    'DROP_TABLE',
                    'DROP_DATABASE',
                    'DROP_INDEX'
                ), true)) {
                $info['table'] = $matches[1];
            }

            if ($type === 'SELECT' &&
                preg_match(
                    '/\bFROM\s+`?([a-zA-Z0-9_]+)`?/i',
                    $clean,
                    $matches
                )) {
                $info['table'] = $matches[1];
            }

            return $info;
        }
    }

    return $info;
}

/*
 * Dependency-aware scheduler.
 *
 * Creation and alterations precede queries on the same table.
 * DROP operations follow other operations involving that table.
 * Duplicate DROP statements are skipped.
 */
function scheduleSqlStatements($statements)
{
    $items = array();
    $count = count($statements);

    foreach ($statements as $i => $sql) {
        $info = analyzeSqlStatement($sql);
        $info['originalIndex'] = $i;
        $info['duplicate'] = false;
        $items[] = $info;
    }

    $seenDrops = array();

    foreach ($items as $i => $item) {
        if (!in_array(
            $item['type'],
            array('DROP_TABLE', 'DROP_DATABASE', 'DROP_INDEX'),
            true
        )) {
            continue;
        }

        $key = $item['type'] . ':' .
            ($item['table'] === null ? '' : $item['table']);

        if (isset($seenDrops[$key])) {
            $items[$i]['duplicate'] = true;
        } else {
            $seenDrops[$key] = true;
        }
    }

    $dependencies = array_fill(0, $count, array());

    foreach ($items as $i => $item) {
        if ($item['duplicate'] || $item['table'] === null) {
            continue;
        }

        foreach ($items as $j => $other) {
            if ($i === $j || $other['duplicate']) {
                continue;
            }

            if ($item['table'] !== $other['table']) {
                continue;
            }

            if (
                $other['type'] === 'CREATE_TABLE' &&
                $item['type'] !== 'CREATE_TABLE'
            ) {
                $dependencies[$i][] = $j;
            }

            if (
                $item['type'] === 'ALTER_TABLE' &&
                $other['type'] === 'CREATE_TABLE'
            ) {
                $dependencies[$i][] = $j;
            }

            if (
                in_array(
                    $item['type'],
                    array('INSERT', 'REPLACE', 'UPDATE', 'DELETE', 'SELECT', 'TRUNCATE'),
                    true
                ) &&
                in_array($other['type'], array('CREATE_TABLE', 'ALTER_TABLE'), true)
            ) {
                $dependencies[$i][] = $j;
            }

            if (
                in_array(
                    $item['type'],
                    array('DROP_TABLE', 'DROP_INDEX'),
                    true
                ) &&
                !in_array(
                    $other['type'],
                    array('DROP_TABLE', 'DROP_INDEX', 'DROP_DATABASE'),
                    true
                )
            ) {
                $dependencies[$i][] = $j;
            }

            if (
                $item['type'] === 'DROP_TABLE' &&
                $other['type'] === 'DROP_INDEX'
            ) {
                $dependencies[$i][] = $j;
            }
        }

        $dependencies[$i] = array_values(
            array_unique($dependencies[$i])
        );
    }

    $priority = array(
        'CREATE_DATABASE' => 0,
        'CREATE_TABLE' => 1,
        'CREATE_INDEX' => 2,
        'ALTER_TABLE' => 3,
        'INSERT' => 4,
        'REPLACE' => 4,
        'UPDATE' => 5,
        'DELETE' => 6,
        'SELECT' => 7,
        'TRUNCATE' => 8,
        'DROP_INDEX' => 9,
        'DROP_TABLE' => 10,
        'DROP_DATABASE' => 11,
        'OTHER' => 5
    );

    $remaining = array();

    for ($i = 0; $i < $count; $i++) {
        $remaining[] = $i;
    }

    $ordered = array();

    while (!empty($remaining)) {
        $ready = array();

        foreach ($remaining as $index) {
            $allDone = true;

            foreach ($dependencies[$index] as $dependency) {
                if (!in_array($dependency, $ordered, true)) {
                    $allDone = false;
                    break;
                }
            }

            if ($allDone) {
                $ready[] = $index;
            }
        }

        /*
         * Avoid an infinite loop if dependencies form a cycle.
         */
        if (empty($ready)) {
            foreach ($remaining as $index) {
                $ordered[] = $index;
            }
            break;
        }

        usort($ready, function ($a, $b) use ($items, $priority) {
            $pa = isset($priority[$items[$a]['type']])
                ? $priority[$items[$a]['type']] : 5;

            $pb = isset($priority[$items[$b]['type']])
                ? $priority[$items[$b]['type']] : 5;

            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return $items[$a]['originalIndex'] <=>
                $items[$b]['originalIndex'];
        });

        $pick = $ready[0];
        $ordered[] = $pick;

        $remaining = array_values(
            array_diff($remaining, array($pick))
        );
    }

    $result = array();

    foreach ($ordered as $index) {
        $result[] = $items[$index];
    }

    /*
     * Return statements in original input order, retaining the
     * execution order separately.
     */
    $executionOrder = array();

    foreach ($result as $position => $item) {
        $executionOrder[$item['originalIndex']] = $position;
    }

    usort($result, function ($a, $b) {
        return $a['originalIndex'] <=> $b['originalIndex'];
    });

    foreach ($result as $i => $item) {
        $result[$i]['executionOrder'] =
            $executionOrder[$item['originalIndex']];
    }

    return $result;
}

/*
 * Execute one SQL statement.
 */
function executeOneSqlStatement($pdo, $item)
{
    $sql = $item['sql'];

    $result = array(
        'index' => $item['originalIndex'],
        'executionOrder' => $item['executionOrder'],
        'sql' => $sql,
        'type' => $item['type'],
        'status' => 'unknown',
        'message' => '',
        'rows' => array(),
        'columnMeta' => array(),
        'affectedRows' => 0,
        'isReadQuery' => false
    );

    if ($item['duplicate']) {
        $result['status'] = 'skipped';
        $result['message'] = 'Duplicate DROP statement skipped.';
        return $result;
    }

    try {
        $stmt = $pdo->query($sql);

        if ($stmt === false) {
            throw new RuntimeException('SQL execution failed.');
        }

        if ($stmt->columnCount() > 0) {
            $result['isReadQuery'] = true;
            $result['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            for ($i = 0; $i < $stmt->columnCount(); $i++) {
                $meta = $stmt->getColumnMeta($i);

                if (is_array($meta) && isset($meta['name'])) {
                    $result['columnMeta'][] = array(
                        'name' => $meta['name']
                    );
                }
            }

            $count = count($result['rows']);

            $result['status'] = $count > 0 ? 'success' : 'empty';

            $result['message'] = $count > 0
                ? 'Query succeeded with ' . $count . ' row(s).'
                : 'Query succeeded but returned 0 rows.';
        } else {
            $result['affectedRows'] = $stmt->rowCount();
            $result['status'] = 'success';
            $result['message'] =
                'Statement executed. Affected rows: ' .
                $result['affectedRows'];
        }

        $stmt->closeCursor();

    } catch (Throwable $e) {
        error_log(
            'phpmyadmin_tablesfetch.php SQL error: ' .
            $e->getMessage()
        );

        $result['status'] = 'error';
        $result['message'] =
            'SQL execution failed. Check the hosting PHP error log.';
    }

    return $result;
}

/*
 * Main request handling.
 */
try {
    $input = array();

    if (!empty($_GET)) {
        $input = array_merge($input, $_GET);
    }

    if (!empty($_POST)) {
        $input = array_merge($input, $_POST);
    }

    $contentType = isset($_SERVER['CONTENT_TYPE'])
        ? $_SERVER['CONTENT_TYPE'] : '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');

        if ($raw !== false && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                jsonResponse(array(
                    'status' => 'error',
                    'message' => 'Invalid JSON request body.',
                    'tables' => array(),
                    'columns' => array(),
                    'data' => array(),
                    'results' => array()
                ), 400);
            }

            $input = array_merge($input, $decoded);
        }
    }

    $table = isset($input['table'])
        ? trim((string) $input['table']) : '';

    $sqlQuery = isset($input['sql_query'])
        ? (string) $input['sql_query'] : '';

    /*
     * Return table list when no table or SQL query is supplied.
     */
    if ($table === '' && trim($sqlQuery) === '') {
        $database = $pdo->query('SELECT DATABASE()')->fetchColumn();

        $tables = $pdo->query('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN, 0);

        jsonResponse(array(
            'status' => 'success',
            'message' => 'Tables retrieved successfully.',
            'database' => $database,
            'table_count' => count($tables),
            'tables' => $tables,
            'columns' => array(),
            'data' => array(),
            'results' => array()
        ));
    }

    /*
     * Return column metadata for the selected table.
     */
    if ($table !== '' && trim($sqlQuery) === '') {
        if (!preg_match('/\A[A-Za-z0-9_]+\z/', $table)) {
            jsonResponse(array(
                'status' => 'error',
                'message' => 'Invalid table name.',
                'tables' => array(),
                'columns' => array()
            ), 400);
        }

        $check = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?'
        );

        $check->execute(array($table));

        if ((int) $check->fetchColumn() === 0) {
            jsonResponse(array(
                'status' => 'error',
                'message' => 'Table not found in the selected database.',
                'tables' => array(),
                'columns' => array()
            ), 404);
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM `" . $table . "`");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(array(
            'status' => 'success',
            'message' => 'Columns retrieved successfully.',
            'table' => $table,
            'columns' => $columns,
            'tables' => array(),
            'data' => array()
        ));
    }

    /*
     * Execute SQL statements in dependency-aware order.
     */
    if (trim($sqlQuery) === '') {
        jsonResponse(array(
            'status' => 'error',
            'message' => 'No SQL query was supplied.',
            'tables' => array(),
            'columns' => array(),
            'data' => array(),
            'results' => array()
        ), 400);
    }

    $statements = splitSqlStatements($sqlQuery);

    if (empty($statements)) {
        jsonResponse(array(
            'status' => 'error',
            'message' => 'No executable SQL statements found.',
            'tables' => array(),
            'columns' => array(),
            'data' => array(),
            'results' => array()
        ), 400);
    }

    $scheduled = scheduleSqlStatements($statements);
    $results = array();

    foreach ($scheduled as $item) {
        $results[] = executeOneSqlStatement($pdo, $item);
    }

    /*
     * Results are in original input order.
     */
    usort($results, function ($a, $b) {
        return $a['index'] <=> $b['index'];
    });

    $success = 0;
    $empty = 0;
    $failed = 0;
    $skipped = 0;
    $totalRows = 0;
    $singleData = array();

    foreach ($results as $result) {
        if ($result['status'] === 'success') {
            $success++;
        } elseif ($result['status'] === 'empty') {
            $empty++;
        } elseif ($result['status'] === 'skipped') {
            $skipped++;
        } else {
            $failed++;
        }

        $totalRows += count($result['rows']);

        if (count($results) === 1) {
            if ($result['isReadQuery']) {
                $singleData = array(
                    'rows' => $result['rows'],
                    'columnMeta' => $result['columnMeta']
                );
            } else {
                $singleData = array(
                    'affectedRows' => $result['affectedRows']
                );
            }
        }
    }

    if ($failed === 0) {
        $overallStatus = 'success';
    } elseif ($success + $empty > 0) {
        $overallStatus = 'partial';
    } else {
        $overallStatus = 'error';
    }

    $messages = array();

    if ($success > 0) {
        $messages[] = $success . ' succeeded';
    }

    if ($empty > 0) {
        $messages[] = $empty . ' returned no rows';
    }

    if ($skipped > 0) {
        $messages[] = $skipped . ' skipped';
    }

    if ($failed > 0) {
        $messages[] = $failed . ' failed';
    }

    $response = array(
        'status' => $overallStatus,
        'message' => implode(', ', $messages) .
            ' (' . count($results) . ' statement(s)).',
        'data' => $singleData,
        'results' => $results,
        'summary' => array(
            'total' => count($results),
            'succeeded' => $success,
            'empty' => $empty,
            'skipped' => $skipped,
            'failed' => $failed,
            'totalRows' => $totalRows
        ),
        'tables' => array(),
        'columns' => array()
    );

    /*
     * Refresh the table list after SQL execution.
     */
    try {
        $response['tables'] = $pdo->query('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (Throwable $ignored) {
        $response['tables'] = array();
    }

    jsonResponse($response);

} catch (Throwable $e) {
    error_log(
        'phpmyadmin_tablesfetch.php request error: ' .
        $e->getMessage()
    );

    jsonResponse(array(
        'status' => 'error',
        'message' =>
            'The database request failed. Check the hosting PHP error log.',
        'tables' => array(),
        'columns' => array(),
        'data' => array(),
        'results' => array()
    ), 500);
}
