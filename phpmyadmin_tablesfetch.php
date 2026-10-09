<?php
// phpmyadmin_tablesfetch.php
// Dependency-aware multi-statement SQL executor with DROP deduplication.
// Parses each statement, builds a dependency graph per table,
// orders them safely, executes, and returns per-statement results
// in the ORIGINAL input order.

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

/* =====================================================================
 * 1. SQL SPLITTER (respects quotes + comments)
 * ===================================================================== */
function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $len = strlen($sql);
    $inSingle = $inDouble = $inBacktick = false;
    $inLineComment = $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch   = $sql[$i];
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
            $inSingle = !$inSingle; $current .= $ch; continue;
        }
        if ($ch === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble && $next === '"') { $current .= '""'; $i++; continue; }
            $inDouble = !$inDouble; $current .= $ch; continue;
        }
        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick; $current .= $ch; continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $t = trim($current);
            if ($t !== '') $statements[] = $t;
            $current = '';
            continue;
        }
        $current .= $ch;
    }
    $t = trim($current);
    if ($t !== '') $statements[] = $t;
    return $statements;
}

/* =====================================================================
 * 2. STATEMENT ANALYZER
 * ===================================================================== */

// Priority: LOWER number = runs FIRST.
const PRIORITY = [
    'CREATE_DATABASE' => 0,
    'CREATE_TABLE'    => 1,
    'CREATE_INDEX'    => 2,
    'ALTER_TABLE'     => 3,
    'INSERT'          => 4,
    'UPDATE'          => 5,
    'DELETE'          => 6,
    'SELECT'          => 7,
    'TRUNCATE'        => 8,
    'DROP_INDEX'      => 9,
    'DROP_TABLE'      => 10,
    'DROP_DATABASE'   => 11,
    'OTHER'           => 5,
];

function analyzeStatement(string $sql): array {
    $clean = ltrim($sql);
    $clean = preg_replace('/^(\/\*.*?\*\/|--[^\n]*\n|#[^\n]*\n|\s)+/s', '', $clean);

    $info = [
        'type'  => 'OTHER',
        'table' => null,
        'raw'   => $sql,
    ];

    if (preg_match('/^CREATE\s+(TEMPORARY\s+)?TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'CREATE_TABLE';
        $info['table'] = $m[3];
        return $info;
    }
    if (preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\s+`?[a-zA-Z0-9_]+`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'CREATE_INDEX';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^CREATE\s+DATABASE\s+(IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'CREATE_DATABASE';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'ALTER_TABLE';
        $info['table'] = $m[1];
        return $info;
    }
    if (preg_match('/^INSERT\s+(INTO\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'INSERT';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'UPDATE';
        $info['table'] = $m[1];
        return $info;
    }
    if (preg_match('/^DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'DELETE';
        $info['table'] = $m[1];
        return $info;
    }
    if (preg_match('/^TRUNCATE\s+(TABLE\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'TRUNCATE';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^DROP\s+TABLE\s+(IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'DROP_TABLE';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^DROP\s+INDEX\s+`?[a-zA-Z0-9_]+`?\s+ON\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'DROP_INDEX';
        $info['table'] = $m[1];
        return $info;
    }
    if (preg_match('/^DROP\s+DATABASE\s+(IF\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'DROP_DATABASE';
        $info['table'] = $m[2];
        return $info;
    }
    if (preg_match('/^SELECT\b/i', $clean)) {
        $info['type'] = 'SELECT';
        if (preg_match('/\bFROM\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
            $info['table'] = $m[1];
        }
        return $info;
    }
    if (preg_match('/^(DESCRIBE|DESC|EXPLAIN)\s+`?([a-zA-Z0-9_]+)`?/i', $clean, $m)) {
        $info['type']  = 'SELECT';
        $info['table'] = $m[2];
        return $info;
    }

    return $info;
}

/* =====================================================================
 * 3. DEPENDENCY-AWARE SCHEDULER (with DROP deduplication)
 * ===================================================================== */
function scheduleStatements(array $statements): array {
    // 1. Analyze each
    $analyzed = [];
    foreach ($statements as $i => $sql) {
        $info = analyzeStatement($sql);
        $info['originalIndex'] = $i;
        $info['dupOf'] = null;
        $analyzed[] = $info;
    }

    // 2. Dedupe DROP_* per (type, table): keep the FIRST, mark the rest as dupOf
    $firstDropByTable = [];
    foreach ($analyzed as $idx => $a) {
        $t = $a['table'];
        if ($t === null) continue;
        if (in_array($a['type'], ['DROP_TABLE', 'DROP_DATABASE', 'DROP_INDEX'], true)) {
            $key = $a['type'] . ':' . $t;
            if (isset($firstDropByTable[$key])) {
                $analyzed[$idx]['dupOf'] = $firstDropByTable[$key];
            } else {
                $firstDropByTable[$key] = $a['originalIndex'];
            }
        }
    }

    // 3. Group by table
    $byTable = [];
    foreach ($analyzed as $a) {
        if ($a['table'] !== null) $byTable[$a['table']][] = $a['originalIndex'];
    }

    // 4. Which tables get a CREATE in this batch?
    $tablesBeingCreated = [];
    foreach ($analyzed as $a) {
        if ($a['type'] === 'CREATE_TABLE' && $a['table']) $tablesBeingCreated[$a['table']] = true;
    }

    // 5. Build dependency map
    $deps = [];
    foreach ($analyzed as $a) {
        $deps[$a['originalIndex']] = [];

        // Duplicate DROP depends on its original (runs after it, so we can skip)
        if ($a['dupOf'] !== null) {
            $deps[$a['originalIndex']][] = $a['dupOf'];
        }

        if (!$a['table']) continue;
        $t = $a['table'];

        foreach ($byTable[$t] as $otherIdx) {
            if ($otherIdx === $a['originalIndex']) continue;
            // Don't chain onto duplicates of other statements (avoid cycles)
            if ($analyzed[$otherIdx]['dupOf'] !== null
                && $analyzed[$otherIdx]['dupOf'] !== $a['originalIndex']) continue;

            $other = $analyzed[$otherIdx];

            if ($other['type'] === 'CREATE_TABLE' && $a['type'] !== 'CREATE_TABLE') {
                $deps[$a['originalIndex']][] = $otherIdx;
            }
            if ($a['type'] === 'ALTER_TABLE' && $other['type'] === 'CREATE_TABLE') {
                $deps[$a['originalIndex']][] = $otherIdx;
            }
            if (in_array($a['type'], ['INSERT', 'UPDATE', 'DELETE', 'SELECT', 'TRUNCATE'], true)) {
                if ($other['type'] === 'CREATE_TABLE') $deps[$a['originalIndex']][] = $otherIdx;
                if ($other['type'] === 'ALTER_TABLE')  $deps[$a['originalIndex']][] = $otherIdx;
            }
            if (in_array($a['type'], ['DROP_TABLE', 'DROP_INDEX', 'DROP_DATABASE'], true)
                && !in_array($other['type'], ['DROP_TABLE', 'DROP_INDEX', 'DROP_DATABASE'], true)) {
                $deps[$a['originalIndex']][] = $otherIdx;
            }
            if ($a['type'] === 'DROP_TABLE' && $other['type'] === 'DROP_INDEX') {
                $deps[$a['originalIndex']][] = $otherIdx;
            }
        }
    }

    // 6. Topological sort with priority tie-breaker
    $remaining = array_column($analyzed, 'originalIndex');
    $ordered   = [];
    $maxIter   = count($remaining) + 5;
    $iter      = 0;

    while (!empty($remaining) && $iter++ < $maxIter) {
        $ready = [];
        foreach ($remaining as $idx) {
            $allDone = true;
            foreach ($deps[$idx] as $d) {
                if (!in_array($d, $ordered, true)) { $allDone = false; break; }
            }
            if ($allDone) $ready[] = $idx;
        }
        if (empty($ready)) {
            foreach ($remaining as $idx) $ordered[] = $idx;
            break;
        }

        usort($ready, function($a, $b) use ($analyzed, $tablesBeingCreated) {
            $A = $analyzed[$a];
            $B = $analyzed[$b];
            $pa = PRIORITY[$A['type']] ?? 5;
            $pb = PRIORITY[$B['type']] ?? 5;
            if ($pa !== $pb) return $pa <=> $pb;

            $ac = isset($tablesBeingCreated[$A['table']]) ? 1 : 0;
            $bc = isset($tablesBeingCreated[$B['table']]) ? 1 : 0;
            if ($ac !== $bc) return $bc <=> $ac;

            return $A['originalIndex'] <=> $B['originalIndex'];
        });

        $pick = $ready[0];
        $ordered[] = $pick;
        $remaining = array_values(array_diff($remaining, [$pick]));
    }

    $orderInfo = [];
    foreach ($ordered as $idx) $orderInfo[] = $analyzed[$idx];
    return $orderInfo;
}

/* =====================================================================
 * 4. EXECUTE ONE STATEMENT
 * ===================================================================== */
function executeSingleStatement(PDO $pdo, string $sql, int $index, string $type): array {
    $result = [
        'index'        => $index,
        'sql'          => $sql,
        'type'         => $type,
        'status'       => 'unknown',
        'message'      => '',
        'rows'         => [],
        'columnMeta'   => [],
        'affectedRows' => 0,
        'isReadQuery'  => false,
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

/* =====================================================================
 * 5. MAIN HANDLER
 * ===================================================================== */
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

            // ---- Schedule in dependency-safe order ----
            $scheduled = scheduleStatements($statements);

            $resultsByOriginal = [];
            $successCount = 0;
            $errorCount   = 0;
            $emptyCount   = 0;
            $skippedCount = 0;

            foreach ($scheduled as $pos => $info) {
                $origIdx = $info['originalIndex'];
                $stmtSql = $info['raw'];
                $type    = $info['type'];

                // ─── Duplicate DROP → skip cleanly ───
                if ($info['dupOf'] !== null) {
                    $resultsByOriginal[$origIdx] = [
                        'index'        => $origIdx,
                        'sql'          => $stmtSql,
                        'type'         => $type,
                        'status'       => 'skipped',
                        'message'      => 'Skipped — same table already scheduled for DROP earlier in this batch.',
                        'rows'         => [],
                        'columnMeta'   => [],
                        'affectedRows' => 0,
                        'isReadQuery'  => false,
                    ];
                    $skippedCount++;
                    continue;
                }

                // ─── Skip if a prerequisite failed ───
                $depFailed = false;
                foreach ($scheduled as $other) {
                    if ($other['originalIndex'] === $origIdx) continue;
                    if ($other['dupOf'] !== null) continue; // duplicates don't gate others
                    if ($other['table'] && $info['table'] && $other['table'] === $info['table']) {
                        if (isset($resultsByOriginal[$other['originalIndex']])) {
                            $depStatus = $resultsByOriginal[$other['originalIndex']]['status'];
                            if ($depStatus === 'error' || $depStatus === 'skipped') {
                                $depType = $other['type'];
                                $isPrereq = in_array($depType, ['CREATE_TABLE', 'ALTER_TABLE', 'CREATE_INDEX', 'CREATE_DATABASE'], true);
                                if ($info['type'] === 'DROP_TABLE' || $info['type'] === 'DROP_DATABASE') {
                                    $isPrereq = true;
                                }
                                if ($isPrereq) { $depFailed = true; break; }
                            }
                        }
                    }
                }

                if ($depFailed) {
                    $resultsByOriginal[$origIdx] = [
                        'index'        => $origIdx,
                        'sql'          => $stmtSql,
                        'type'         => $type,
                        'status'       => 'skipped',
                        'message'      => 'Skipped because a prerequisite statement failed.',
                        'rows'         => [],
                        'columnMeta'   => [],
                        'affectedRows' => 0,
                        'isReadQuery'  => false,
                    ];
                    $skippedCount++;
                    continue;
                }

                $r = executeSingleStatement($pdo, $stmtSql, $origIdx, $type);
                $resultsByOriginal[$origIdx] = $r;

                if ($r['status'] === 'success')    $successCount++;
                else if ($r['status'] === 'error') $errorCount++;
                else if ($r['status'] === 'empty') $emptyCount++;
            }

            ksort($resultsByOriginal);
            $results = array_values($resultsByOriginal);

            $total = count($results);
            $ok    = $successCount + $emptyCount;

            $parts = [];
            if ($successCount > 0) $parts[] = $successCount . ' succeeded';
            if ($emptyCount   > 0) $parts[] = $emptyCount   . ' returned no rows';
            if ($skippedCount > 0) $parts[] = $skippedCount . ' skipped';
            if ($errorCount   > 0) $parts[] = $errorCount   . ' failed';

            if ($errorCount === 0 && $skippedCount === 0)  $overallStatus = 'success';
            else if ($ok > 0)                              $overallStatus = 'partial';
            else                                           $overallStatus = 'error';

            $message = implode(', ', $parts) . ' (of ' . $total . ' statement(s)).';

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
                    'skipped'   => $skippedCount,
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