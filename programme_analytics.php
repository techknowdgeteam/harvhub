<?php
session_start();
try {
    require_once __DIR__ . '/mysqlconnection.php';
    if (!function_exists('getAvailablePdoConnection')) throw new RuntimeException('getAvailablePdoConnection() was not found.');
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) throw new RuntimeException('Database connection did not return PDO.');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ' database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}
if (!isset($_SESSION['user_email'])) {
    header('Location: index.php?role=developer');
    exit;
}
$email = strtolower(trim((string)$_SESSION['user_email']));
$userStmt = $pdo->prepare('SELECT id, email, dark_mode FROM harvhub WHERE LOWER(email) = ? LIMIT 1');
$userStmt->execute([$email]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    unset($_SESSION['user_email'], $_SESSION['auth_role']);
    header('Location: index.php?role=developer');
    exit;
}
$userId = (int)$user['id'];
$darkMode = !empty($user['dark_mode']);
$selectedProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
$programme = null;
if ($selectedProgrammeId > 0) {
    $p = $pdo->prepare('SELECT id, userid, program_name, selected_symbols FROM programme WHERE id = ? AND userid = ? LIMIT 1');
    $p->execute([$selectedProgrammeId, $userId]);
    $programme = $p->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$programme) {
    $p = $pdo->prepare('SELECT id, userid, program_name, selected_symbols FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1');
    $p->execute([$userId]);
    $programme = $p->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($programme) {
        $selectedProgrammeId = (int)$programme['id'];
        $_SESSION['selected_programme_id'] = $selectedProgrammeId;
    }
}
function pa_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pa_json($value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? '[]' : $json;
}
function pa_tableColumns(PDO $pdo, string $table): array {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        return [];
    }
}
function pa_tableExists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
    try {
        $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        return (bool)($stmt ? $stmt->fetchColumn() : false);
    } catch (Throwable $e) {
        return false;
    }
}
function pa_ensureLiveTradesColumn(PDO $pdo): bool {
    if (!pa_tableExists($pdo, 'programme_analytics')) return false;
    try {
        $columns = pa_tableColumns($pdo, 'programme_analytics');
        if (!in_array('live_trades', $columns, true)) {
            $position = in_array('trade_configured', $columns, true) ? ' AFTER `trade_configured`' : '';
            $pdo->exec("ALTER TABLE `programme_analytics` ADD COLUMN `live_trades` TINYINT(1) NOT NULL DEFAULT 0" . $position);
            $columns = pa_tableColumns($pdo, 'programme_analytics');
        }
        return in_array('live_trades', $columns, true);
    } catch (Throwable $e) {
        error_log('Programme analytics live_trades migration failed: ' . $e->getMessage());
        return false;
    }
}
function pa_fetchConfigRows(PDO $pdo, string $table, int $programmeId, int $userId): array {
    if (!pa_tableExists($pdo, $table)) return [];
    $columns = pa_tableColumns($pdo, $table);
    $columnMap = [];
    foreach ($columns as $column) $columnMap[strtolower((string)$column)] = (string)$column;
    $programmeColumn = null;
    foreach (['programme_id', 'programmeid', 'program_id'] as $candidate) {
        if (isset($columnMap[$candidate])) { $programmeColumn = $columnMap[$candidate]; break; }
    }
    $userColumn = null;
    foreach (['user_id', 'userid', 'developerid'] as $candidate) {
        if (isset($columnMap[$candidate])) { $userColumn = $columnMap[$candidate]; break; }
    }
    if (!$programmeColumn || !$userColumn) {
        error_log('Programme analytics could not identify programme/user columns for ' . $table . '. Available columns: ' . implode(',', $columns));
        return [];
    }
    try {
        $stmt = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $programmeColumn . '` = ? AND `' . $userColumn . '` = ?');
        $stmt->execute([$programmeId, $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Programme analytics configuration read failed for ' . $table . ': ' . $e->getMessage());
        return [];
    }
}
function pa_parseList($raw): array {
    if ($raw === null || $raw === '') return [];
    if (is_array($raw)) {
        $items = [];
        array_walk_recursive($raw, static function($value) use (&$items) { $items[] = $value; });
    } else {
        $value = trim((string)$raw);
        if ($value === '') return [];
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $items = [];
            array_walk_recursive($decoded, static function($item) use (&$items) { $items[] = $item; });
        } else {
            $items = preg_split('/\s*,\s*/', trim($value, " \t\n\r\0\x0B[]{}\"'"));
        }
    }
    $out = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item === '') continue;
        foreach (preg_split('/\s*,\s*/', $item) as $part) {
            $part = trim($part, " \t\n\r\0\x0B[]{}\"'");
            if ($part !== '') $out[$part] = $part;
        }
    }
    return array_values($out);
}
function pa_tfMs($timeframe): int {
    $tf = strtolower(trim((string)$timeframe));
    if (!preg_match('/^(\d+)\s*([mhdw])$/', $tf, $m)) return PHP_INT_MAX;
    $n = (int)$m[1];
    return $n * ['m'=>60000, 'h'=>3600000, 'd'=>86400000, 'w'=>604800000][$m[2]];
}
function pa_sortTimeframes(array &$timeframes): void {
    $timeframes = array_values(array_unique(array_filter(array_map(static function($v) { return strtolower(trim((string)$v)); }, $timeframes))));
    usort($timeframes, static function($a, $b) {
        $aMs = pa_tfMs($a);
        $bMs = pa_tfMs($b);
        return $aMs === $bMs ? strcmp($a, $b) : ($aMs <=> $bMs);
    });
}
function pa_loadRootConfiguration(PDO $pdo, int $programmeId, int $userId): array {
    $roots = [];
    $ensureRoot = static function(int $rootId, string $rootName = '') use (&$roots): void {
        if ($rootId <= 0) return;
        if (!isset($roots[$rootId])) {
            $roots[$rootId] = ['root_id'=>$rootId, 'root_name'=>$rootName !== '' ? $rootName : 'Strategy ' . $rootId, 'timeframes'=>[], 'trade_enabled'=>false, 'trade_for'=>'root', 'confirmations'=>[]];
        } elseif ($rootName !== '') {
            $roots[$rootId]['root_name'] = $rootName;
        }
    };
    $mainRows = pa_fetchConfigRows($pdo, 'programme_training_configuration', $programmeId, $userId);
    foreach ($mainRows as $row) {
        $rootId = (int)($row['root_id'] ?? 0);
        if ($rootId <= 0) continue;
        $rootName = trim((string)($row['root_name'] ?? ''));
        $ensureRoot($rootId, $rootName);
        foreach (pa_parseList($row['timeframe'] ?? '') as $tf) $roots[$rootId]['timeframes'][] = strtolower($tf);
        $tradeFor = strtolower(trim((string)($row['trade_for'] ?? 'root')));
        if ($tradeFor !== '') $roots[$rootId]['trade_for'] = $tradeFor;
        if ($tradeFor === 'root' && (int)($row['use_for_trade'] ?? 0) === 1) $roots[$rootId]['trade_enabled'] = true;
        $legacyConfirmationId = (int)($row['confirmation_root_id'] ?? 0);
        if ($legacyConfirmationId > 0 && !isset($roots[$rootId]['confirmations'][$legacyConfirmationId])) {
            $roots[$rootId]['confirmations'][$legacyConfirmationId] = ['confirmation_root_id'=>$legacyConfirmationId, 'confirmation_root_name'=>'Confirmation ' . $legacyConfirmationId, 'timeframes'=>[], 'trade_enabled'=>false];
        }
    }
    $confirmationRows = pa_fetchConfigRows($pdo, 'programme_training_confirmation_configuration', $programmeId, $userId);
    foreach ($confirmationRows as $row) {
        $parentId = (int)($row['parent_root_id'] ?? $row['parentrootid'] ?? $row['parent_id'] ?? 0);
        $confirmationId = (int)($row['root_id'] ?? $row['confirmation_root_id'] ?? 0);
        if ($parentId <= 0 || $confirmationId <= 0) continue;
        $parentName = '';
        $ensureRoot($parentId, $parentName);
        $confirmationName = trim((string)($row['root_name'] ?? $row['confirmation_root_name'] ?? ''));
        if (!isset($roots[$parentId]['confirmations'][$confirmationId])) {
            $roots[$parentId]['confirmations'][$confirmationId] = ['confirmation_root_id'=>$confirmationId, 'confirmation_root_name'=>$confirmationName !== '' ? $confirmationName : 'Confirmation ' . $confirmationId, 'timeframes'=>[], 'trade_enabled'=>false];
        } elseif ($confirmationName !== '') {
            $roots[$parentId]['confirmations'][$confirmationId]['confirmation_root_name'] = $confirmationName;
        }
        foreach (pa_parseList($row['timeframe'] ?? $row['timeframes'] ?? '') as $tf) $roots[$parentId]['confirmations'][$confirmationId]['timeframes'][] = strtolower($tf);
        if ((int)($row['use_for_trade'] ?? $row['trade_enabled'] ?? $row['is_trade_enabled'] ?? 0) === 1) $roots[$parentId]['confirmations'][$confirmationId]['trade_enabled'] = true;
    }
    foreach ($roots as &$root) {
        pa_sortTimeframes($root['timeframes']);
        foreach ($root['confirmations'] as &$confirmation) {
            pa_sortTimeframes($confirmation['timeframes']);
        }
        unset($confirmation);
        ksort($root['confirmations'], SORT_NUMERIC);
        $root['confirmations'] = array_values($root['confirmations']);
    }
    unset($root);
    ksort($roots, SORT_NUMERIC);
    return array_values($roots);
}
function pa_loadSymbols($raw): array {
    $symbols = pa_parseList($raw);
    $symbols = array_values(array_unique($symbols));
    sort($symbols, SORT_NATURAL | SORT_FLAG_CASE);
    return $symbols;
}

/**
 * Additive programme-trade schema support. The existing programme_trades
 * columns are preserved; chart identity is stored separately so broker tradeid
 * and all existing consumers keep their current meaning.
 */
function pa_ensureProgrammeTradeSyncSchema(PDO $pdo): bool {
    if (!pa_tableExists($pdo, 'programme_trades')) return false;
    $columns = pa_tableColumns($pdo, 'programme_trades');
    if (!in_array('contract_id', $columns, true)) {
        $pdo->exec("ALTER TABLE `programme_trades` ADD COLUMN `contract_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER `programmeid`");
    }
    $indexes = [];
    try {
        foreach ($pdo->query("SHOW INDEX FROM `programme_trades`")->fetchAll(PDO::FETCH_ASSOC) as $indexRow) {
            $indexes[(string)($indexRow['Key_name'] ?? '')] = true;
        }
    } catch (Throwable $e) {}
    if (empty($indexes['idx_programme_trades_contract'])) {
        try {
            $pdo->exec("ALTER TABLE `programme_trades` ADD KEY `idx_programme_trades_contract` (`userid`,`programmeid`,`contract_id`)");
        } catch (Throwable $e) {
            error_log('Programme trades contract index migration: ' . $e->getMessage());
        }
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS `programme_trade_chart_identity` (
        `programme_trade_id` BIGINT(20) UNSIGNED NOT NULL,
        `userid` BIGINT(20) UNSIGNED NOT NULL,
        `programmeid` BIGINT(20) UNSIGNED NOT NULL,
        `contract_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
        `identity_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `chart_trade_id` VARCHAR(255) NULL DEFAULT NULL,
        `root_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
        `confirmation_root_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
        `family_number` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
        `family_signature` TEXT NULL,
        `symbol` VARCHAR(64) NOT NULL,
        `timeframe` VARCHAR(32) NOT NULL,
        `source_timeframe` VARCHAR(32) NULL DEFAULT NULL,
        `entry_anchor_time` VARCHAR(40) NULL DEFAULT NULL,
        `entry_trigger_time` VARCHAR(40) NULL DEFAULT NULL,
        `outcome` VARCHAR(64) NULL DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`programme_trade_id`),
        UNIQUE KEY `uniq_programme_trade_chart_identity` (`userid`,`programmeid`,`identity_hash`),
        KEY `idx_programme_trade_chart_root` (`userid`,`programmeid`,`root_id`,`confirmation_root_id`),
        KEY `idx_programme_trade_chart_contract` (`userid`,`programmeid`,`contract_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    return true;
}

/**
 * Resolve the current programme contract version. The programme.contract_id
 * field is used when that field exists; programme_trades.contract_id is also
 * inspected so a contract producer can publish a newer version there. A
 * programme with no previously published version starts at contract 1.
 */
function pa_latestProgrammeContractId(PDO $pdo, int $programmeId, int $userId): int {
    $latest = 0;
    $programmeColumns = pa_tableColumns($pdo, 'programme');
    if (in_array('contract_id', $programmeColumns, true)) {
        try {
            $q = $pdo->prepare('SELECT `contract_id` FROM `programme` WHERE `id` = ? AND `userid` = ? LIMIT 1');
            $q->execute([$programmeId, $userId]);
            $latest = max($latest, (int)($q->fetchColumn() ?: 0));
        } catch (Throwable $e) {
            error_log('Programme contract id read failed: ' . $e->getMessage());
        }
    }
    try {
        $q = $pdo->prepare('SELECT COALESCE(MAX(`contract_id`),0) FROM `programme_trades` WHERE `userid` = ? AND `programmeid` = ?');
        $q->execute([$userId, $programmeId]);
        $latest = max($latest, (int)$q->fetchColumn());
    } catch (Throwable $e) {
        error_log('Programme trade contract id read failed: ' . $e->getMessage());
    }
    return $latest > 0 ? $latest : 1;
}

function pa_firstRowValue(array $row, array $keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
    }
    return null;
}

function pa_resolveChartTimeframe(array $row, array $rootOptions): string {
    $rootId = (int)($row['root_id'] ?? 0);
    $confirmationId = (int)($row['confirmation_root_id'] ?? 0);
    $root = null;
    foreach ($rootOptions as $candidate) {
        if ((int)($candidate['root_id'] ?? 0) === $rootId) { $root = $candidate; break; }
    }
    $configured = [];
    if ($confirmationId > 0 && $root) {
        foreach (($root['confirmations'] ?? []) as $confirmation) {
            if ((int)($confirmation['confirmation_root_id'] ?? 0) === $confirmationId) {
                $configured = array_map('strtolower', $confirmation['timeframes'] ?? []);
                break;
            }
        }
    } elseif ($root) {
        $configured = array_map('strtolower', $root['timeframes'] ?? []);
    }
    $candidates = [
        strtolower(trim((string)($row['timeframe'] ?? ''))),
        strtolower(trim((string)($row['source_timeframe'] ?? '')))
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && in_array($candidate, $configured, true)) return $candidate;
    }
    if ($confirmationId > 0) {
        return $candidates[1] !== '' ? $candidates[1] : $candidates[0];
    }
    return $candidates[0] !== '' ? $candidates[0] : $candidates[1];
}

/**
 * Keep the programme's canonical chart trades represented in programme_trades.
 * Rows are upserted by a stable chart identity; old, non-mapped broker rows are
 * not deleted or repurposed. Investor copies and broker tradeid values remain
 * untouched by this synchronizer.
 */
function pa_syncProgrammeTrades(PDO $pdo, int $programmeId, int $userId, array $analyticsRows, array $rootOptions): void {
    if ($programmeId <= 0 || $userId <= 0 || !pa_ensureProgrammeTradeSyncSchema($pdo)) return;
    $contractId = pa_latestProgrammeContractId($pdo, $programmeId, $userId);

    // Apply the current version to legacy rows as well as rows created by this sync.
    $backfill = $pdo->prepare('UPDATE `programme_trades` SET `contract_id` = ? WHERE `userid` = ? AND `programmeid` = ? AND (`contract_id` IS NULL OR `contract_id` <> ?)');
    $backfill->execute([$contractId, $userId, $programmeId, $contractId]);
    $mapBackfill = $pdo->prepare('UPDATE `programme_trade_chart_identity` SET `contract_id` = ? WHERE `userid` = ? AND `programmeid` = ? AND `contract_id` <> ?');
    $mapBackfill->execute([$contractId, $userId, $programmeId, $contractId]);

    $findIdentity = $pdo->prepare('SELECT `programme_trade_id` FROM `programme_trade_chart_identity` WHERE `userid` = ? AND `programmeid` = ? AND `identity_hash` = ? LIMIT 1');
    $insertTrade = $pdo->prepare("INSERT INTO `programme_trades`
        (`userid`,`programmeid`,`contract_id`,`investor_id`,`tradeid`,`symbol`,`ordertype`,`orderstatus`,`timeframe`,`entry`,`stoploss`,`takeprofit`,`trade_risk_reward`,`pnl`,`pnl_risk_reward`,`trade_status`)
        VALUES (?,?,?,NULL,NULL,?,?,?,?,?,?,?,?,?,?,?)");
    $updateTrade = $pdo->prepare("UPDATE `programme_trades` SET
        `contract_id` = ?, `symbol` = ?, `ordertype` = ?, `orderstatus` = ?, `timeframe` = ?,
        `entry` = ?, `stoploss` = ?, `takeprofit` = ?, `trade_risk_reward` = ?, `pnl` = ?,
        `pnl_risk_reward` = ?, `trade_status` = ?
        WHERE `id` = ? AND `userid` = ? AND `programmeid` = ? AND `investor_id` IS NULL");
    $upsertIdentity = $pdo->prepare("INSERT INTO `programme_trade_chart_identity`
        (`programme_trade_id`,`userid`,`programmeid`,`contract_id`,`identity_hash`,`chart_trade_id`,`root_id`,`confirmation_root_id`,`family_number`,`family_signature`,`symbol`,`timeframe`,`source_timeframe`,`entry_anchor_time`,`entry_trigger_time`,`outcome`)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
        `programme_trade_id` = VALUES(`programme_trade_id`), `contract_id` = VALUES(`contract_id`),
        `chart_trade_id` = VALUES(`chart_trade_id`), `root_id` = VALUES(`root_id`),
        `confirmation_root_id` = VALUES(`confirmation_root_id`), `family_number` = VALUES(`family_number`),
        `family_signature` = VALUES(`family_signature`), `symbol` = VALUES(`symbol`),
        `timeframe` = VALUES(`timeframe`), `source_timeframe` = VALUES(`source_timeframe`),
        `entry_anchor_time` = VALUES(`entry_anchor_time`), `entry_trigger_time` = VALUES(`entry_trigger_time`),
        `outcome` = VALUES(`outcome`)");

    foreach ($analyticsRows as $row) {
        if ((int)($row['trade_configured'] ?? 0) !== 1) continue;
        if (array_key_exists('matched_family', $row) && (int)$row['matched_family'] === 0) continue;

        $symbol = trim((string)($row['symbol'] ?? ''));
        $timeframe = pa_resolveChartTimeframe($row, $rootOptions);
        if ($symbol === '' || $timeframe === '') continue;

        $rootId = (int)($row['root_id'] ?? 0);
        $confirmationPresent = array_key_exists('confirmation_root_id', $row) && $row['confirmation_root_id'] !== null && $row['confirmation_root_id'] !== '';
        $confirmationId = $confirmationPresent ? (int)$row['confirmation_root_id'] : null;
        $familyNumber = isset($row['family_number']) && $row['family_number'] !== '' ? (int)$row['family_number'] : null;
        $isIdentifiable = $rootId > 0 && $confirmationPresent && $familyNumber !== null && $familyNumber > 0;
        $isConfirmation = $confirmationPresent && (int)$confirmationId > 0;
        $tradeType = $isConfirmation ? 'confirmation' : 'root';
        $chartTradeId = $isIdentifiable
            ? implode('|', [$tradeType, (string)$rootId, (string)(int)$confirmationId, $timeframe, (string)$familyNumber])
            : null;
        $signatureSeed = $chartTradeId !== null
            ? $symbol . '|' . $chartTradeId
            : 'unidentified|' . $symbol . '|' . $timeframe . '|' . (string)($row['id'] ?? hash('sha256', serialize($row)));
        $identityHash = hash('sha256', $signatureSeed);

        $sourceOutcome = trim((string)($row['outcome'] ?? ''));
        $outcome = strtolower($sourceOutcome);
        $outcome = preg_replace('/[\s_-]+/', ' ', $outcome);

        // Read the actual outcome values before selecting orderstatus so every
        // Analytics closure is synchronized into programme_trades. PnL remains
        // the deciding factor for the Signals Dashboard's profit/loss/flat label.
        $pnl = pa_firstRowValue($row, ['pnl', 'profit_loss', 'profitandloss']);
        $pnl = ($pnl !== null && $pnl !== '' && is_numeric($pnl)) ? (float)$pnl : null;
        $pnlRiskReward = pa_firstRowValue($row, ['pnl_risk_reward']);

        $flatOutcomes = ['closed flat', 'breakeven', 'break even', 'closed at breakeven', 'closed breakeven'];
        $profitOutcomes = ['closed in profit', 'closed profit', 'profit', 'profitable'];
        $lossOutcomes = ['closed in loss', 'closed loss', 'loss', 'losing'];
        $terminalOutcomes = array_merge(
            ['stoploss hit', 'take profit reached', 'closed', 'position closed', 'manual close', 'manually closed'],
            $flatOutcomes,
            $profitOutcomes,
            $lossOutcomes
        );

        if (in_array($outcome, $flatOutcomes, true) && $pnl === null) {
            // An explicit breakeven state is a real zero, not an unknown PnL.
            $pnl = 0.0;
        }

        if ($outcome === 'in trade') {
            $orderStatus = 'running';
        } elseif ($outcome === 'pending entry') {
            $orderStatus = 'pending';
        } elseif (in_array($outcome, $terminalOutcomes, true)) {
            $orderStatus = 'closed';
        } else {
            continue;
        }

        $orderType = strtolower(trim((string)($row['order_type'] ?? '')));
        $allowedOrderTypes = ['buy','sell','buy-stop','sell-stop','buy-limit','sell-limit'];
        if (!in_array($orderType, $allowedOrderTypes, true)) {
            $direction = strtolower(trim((string)($row['direction'] ?? '')));
            $orderType = $direction === 'short' ? 'sell' : 'buy';
        }

        $entry = pa_firstRowValue($row, ['entry','entry_price','entry_trigger_price']);
        $stoploss = pa_firstRowValue($row, ['stoploss','stoploss_price']);
        $takeprofit = pa_firstRowValue($row, ['takeprofit','take_profit_price']);
        $riskReward = pa_firstRowValue($row, ['trade_risk_reward','risk_reward']);
        if ($pnlRiskReward === null && in_array($outcome, array_merge(['take profit reached'], $profitOutcomes), true)) $pnlRiskReward = $riskReward;
        if ($pnlRiskReward === null && in_array($outcome, array_merge(['stoploss hit'], $lossOutcomes), true)) $pnlRiskReward = -1;
        if ($pnlRiskReward === null && in_array($outcome, $flatOutcomes, true)) $pnlRiskReward = 0;
        $tradeStatus = $orderStatus;

        $findIdentity->execute([$userId, $programmeId, $identityHash]);
        $tradeRowId = (int)($findIdentity->fetchColumn() ?: 0);
        $tradeExists = false;
        if ($tradeRowId > 0) {
            $check = $pdo->prepare('SELECT `id` FROM `programme_trades` WHERE `id` = ? AND `userid` = ? AND `programmeid` = ? AND `investor_id` IS NULL LIMIT 1');
            $check->execute([$tradeRowId, $userId, $programmeId]);
            $tradeExists = (bool)$check->fetchColumn();
            if (!$tradeExists) $tradeRowId = 0;
        }

        if (!$tradeExists && $chartTradeId !== null && $entry !== null) {
            // Adopt a single unlinked legacy programme row only when all core
            // trade attributes identify exactly one candidate. Ambiguous rows
            // are left untouched and a separate mapped canonical row is added.
            $legacy = $pdo->prepare("SELECT t.`id`
                FROM `programme_trades` t
                LEFT JOIN `programme_trade_chart_identity` c ON c.`programme_trade_id` = t.`id`
                WHERE t.`userid` = ? AND t.`programmeid` = ? AND t.`investor_id` IS NULL
                  AND c.`programme_trade_id` IS NULL
                  AND UPPER(t.`symbol`) = UPPER(?) AND LOWER(t.`timeframe`) = LOWER(?)
                  AND t.`entry` = ? AND t.`stoploss` <=> ? AND t.`takeprofit` <=> ?
                  AND t.`ordertype` = ? AND t.`orderstatus` = ?
                ORDER BY t.`id` DESC LIMIT 2");
            $legacy->execute([$userId,$programmeId,$symbol,$timeframe,$entry,$stoploss,$takeprofit,$orderType,$orderStatus]);
            $legacyIds = $legacy->fetchAll(PDO::FETCH_COLUMN);
            if (count($legacyIds) === 1) {
                $tradeRowId = (int)$legacyIds[0];
                $tradeExists = $tradeRowId > 0;
            }
        }

        if ($tradeRowId > 0 && $tradeExists) {
            $updateTrade->execute([$contractId,$symbol,$orderType,$orderStatus,$timeframe,$entry,$stoploss,$takeprofit,$riskReward,$pnl,$pnlRiskReward,$tradeStatus,$tradeRowId,$userId,$programmeId]);
        } else {
            $insertTrade->execute([$userId,$programmeId,$contractId,$symbol,$orderType,$orderStatus,$timeframe,$entry,$stoploss,$takeprofit,$riskReward,$pnl,$pnlRiskReward,$tradeStatus]);
            $tradeRowId = (int)$pdo->lastInsertId();
        }
        if ($tradeRowId <= 0) continue;

        $upsertIdentity->execute([
            $tradeRowId,$userId,$programmeId,$contractId,$identityHash,$chartTradeId,
            $rootId > 0 ? $rootId : null,$confirmationId,$familyNumber,$row['family_signature'] ?? null,
            $symbol,$timeframe,($row['source_timeframe'] ?? null),($row['entry_anchor_time'] ?? null),
            ($row['entry_trigger_time'] ?? null),($sourceOutcome !== '' ? $sourceOutcome : null)
        ]);
    }
}
$programmeId = $programme ? (int)$programme['id'] : 0;
$programmeName = (string)($programme['program_name'] ?? 'Programme');
$programmeSymbols = $programme ? pa_loadSymbols($programme['selected_symbols'] ?? '') : [];
$mainRootRows = $programme ? pa_fetchConfigRows($pdo, 'programme_training_configuration', $programmeId, $userId) : [];
$hasProgrammeRoots = false;
foreach ($mainRootRows as $mainRootRow) {
    if ((int)($mainRootRow['root_id'] ?? 0) > 0) {
        $hasProgrammeRoots = true;
        break;
    }
}
$rootOptions = $hasProgrammeRoots ? pa_loadRootConfiguration($pdo, $programmeId, $userId) : [];
$analyticsRows = [];
$analyticsError = '';
if ($programme && $hasProgrammeRoots) {
    try {
        if (!pa_tableExists($pdo, 'programme_analytics')) throw new RuntimeException('programme_analytics does not exist.');
        if (!pa_ensureLiveTradesColumn($pdo)) throw new RuntimeException('The programme_analytics.live_trades column is not available. Run programme_analytics_live_trades.sql.');
        $mainColumns = pa_tableColumns($pdo, 'programme_training_configuration');
        $analyticsColumns = pa_tableColumns($pdo, 'programme_analytics');
        if (in_array('programme_id', $mainColumns, true) && in_array('user_id', $mainColumns, true) && in_array('root_id', $mainColumns, true) && in_array('root_name', $mainColumns, true) && in_array('programme_id', $analyticsColumns, true) && in_array('userid', $analyticsColumns, true) && in_array('root_id', $analyticsColumns, true) && in_array('root_name', $analyticsColumns, true)) {
            $syncMain = $pdo->prepare(
                'UPDATE `programme_analytics` pa INNER JOIN (SELECT `programme_id`,`user_id`,`root_id`,MAX(`root_name`) AS `root_name` FROM `programme_training_configuration` WHERE `programme_id` = ? AND `user_id` = ? GROUP BY `programme_id`,`user_id`,`root_id`) cfg ON cfg.`programme_id` = pa.`programme_id` AND cfg.`user_id` = pa.`userid` AND cfg.`root_id` = pa.`root_id` SET pa.`root_name` = cfg.`root_name` WHERE pa.`programme_id` = ? AND pa.`userid` = ? AND cfg.`root_name` IS NOT NULL AND cfg.`root_name` <> ""'
            );
            $syncMain->execute([$programmeId, $userId, $programmeId, $userId]);
        }
        $confirmationColumns = pa_tableColumns($pdo, 'programme_training_confirmation_configuration');
        if (in_array('programme_id', $confirmationColumns, true) && in_array('user_id', $confirmationColumns, true) && in_array('parent_root_id', $confirmationColumns, true) && in_array('root_id', $confirmationColumns, true) && in_array('root_name', $confirmationColumns, true) && in_array('programme_id', $analyticsColumns, true) && in_array('userid', $analyticsColumns, true) && in_array('root_id', $analyticsColumns, true) && in_array('confirmation_root_id', $analyticsColumns, true) && in_array('confirmation_root_name', $analyticsColumns, true)) {
            $syncConfirmation = $pdo->prepare(
                'UPDATE `programme_analytics` pa INNER JOIN (SELECT `programme_id`,`user_id`,`parent_root_id`,`root_id`,MAX(`root_name`) AS `root_name` FROM `programme_training_confirmation_configuration` WHERE `programme_id` = ? AND `user_id` = ? GROUP BY `programme_id`,`user_id`,`parent_root_id`,`root_id`) cfg ON cfg.`programme_id` = pa.`programme_id` AND cfg.`user_id` = pa.`userid` AND cfg.`parent_root_id` = pa.`root_id` AND cfg.`root_id` = pa.`confirmation_root_id` SET pa.`confirmation_root_name` = cfg.`root_name` WHERE pa.`programme_id` = ? AND pa.`userid` = ? AND pa.`confirmation_root_id` > 0 AND cfg.`root_name` IS NOT NULL AND cfg.`root_name` <> ""'
            );
            $syncConfirmation->execute([$programmeId, $userId, $programmeId, $userId]);
        }
        $q = $pdo->prepare('SELECT * FROM `programme_analytics` WHERE `programme_id` = ? AND `userid` = ? ORDER BY `symbol` ASC, `timeframe` ASC, `root_id` ASC, `confirmation_root_id` ASC, `family_number` ASC');
        $q->execute([$programmeId, $userId]);
        $analyticsRows = $q->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $analyticsError = 'The programme analytics table is not ready. Import programme_analytics_schema.sql and verify its columns.';
        error_log('programme_analytics read/sync failed: ' . $e->getMessage());
    }
}
if ($programme && $hasProgrammeRoots && $analyticsError === '') {
    try {
        pa_syncProgrammeTrades($pdo, $programmeId, $userId, $analyticsRows, $rootOptions);
    } catch (Throwable $tradeSyncError) {
        error_log('Programme Analytics programme_trades sync failed: ' . $tradeSyncError->getMessage());
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title>Programme Analytics</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{--pa-bg:var(--bg,#f4f8f7);--pa-card:var(--bg-card,#fff);--pa-text:var(--text,#14231e);--pa-muted:var(--text-muted,#6c7c76);--pa-accent:var(--accent,#12a36b);--pa-border:var(--border-color,#dfe9e5);--pa-shadow:var(--shadow,0 2px 14px rgba(12,35,27,.06));--pa-red:#dd5862;--pa-green:#119b68;--pa-blue:#4295d2;--pa-amber:#c58b2d}
@media(prefers-color-scheme:dark){html:not(.force-light){--pa-bg:#0b1310;--pa-card:#131e1a;--pa-text:#e9f2ed;--pa-muted:#94aaa0;--pa-border:#263831;--pa-shadow:0 10px 28px rgba(0,0,0,.28)}}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:var(--pa-bg)!important;color:var(--pa-text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.5}
body.dark-mode{--pa-bg:#0b1310;--pa-card:#131e1a;--pa-text:#e9f2ed;--pa-muted:#94aaa0;--pa-border:#263831;--pa-shadow:0 10px 28px rgba(0,0,0,.28)}
body::-webkit-scrollbar{display:none}
.pa-page{width:100%;max-width:1280px;margin:10px auto 0;padding:0 18px 100px}
.pa-control-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:12px}
/* USER-LOCKED: Keep this 60px top margin exactly as set; do not change it in future edits. */
.pa-root-row{grid-template-columns:minmax(0,1fr);}
.pa-confirmation-row{grid-template-columns:minmax(0,1fr);margin:0 0 10px}
.pa-scoped-control-row{margin:0 0 13px}
.pa-confirmation-control{min-width:0}
.pa-control{min-width:0}
.pa-control label{display:block;font-size:.68rem;font-weight:800;color:var(--pa-muted);letter-spacing:.055em;text-transform:uppercase;margin:0 0 5px}
.pa-control select,.pa-symbol-button{width:100%;height:42px;min-height:42px;max-height:42px;border:1px solid var(--pa-border);border-radius:11px;background:var(--pa-card);color:var(--pa-text);padding:8px 10px;font:inherit;font-size:.82rem;line-height:1.2;outline:none}
.pa-control select:focus,.pa-symbol-button:focus{border-color:var(--pa-accent)}
.pa-symbol-button{text-align:left;cursor:pointer;display:flex;align-items:center}
.pa-tabs{display:flex;gap:7px;flex-wrap:wrap;margin:4px 0 13px}
.pa-source-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0;width:100%;border:1px solid var(--pa-border);border-radius:11px;overflow:hidden;background:var(--pa-card); margin-bottom: 10px;}
.pa-source-tab{width:100%;min-width:0;border:0;border-right:1px solid var(--pa-border);padding:11px 12px;background:transparent;color:var(--pa-muted);font:inherit;font-size:.82rem;font-weight:850;cursor:pointer}
.pa-source-tab:last-child{border-right:0}
.pa-source-tab.active{background:var(--pa-accent);color:#fff}
.pa-source-tab:focus-visible{outline:2px solid var(--pa-blue);outline-offset:-3px}
.pa-tab{border:1px solid var(--pa-border);border-radius:9px;padding:8px 13px;background:transparent;color:var(--pa-text);font:inherit;font-size:.79rem;font-weight:800;cursor:pointer}
.pa-tab.active{background:var(--pa-accent);border-color:var(--pa-accent);color:#fff}
.pa-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}
.pa-card{min-width:0;background:var(--pa-card);border:1px solid var(--pa-border);border-radius:14px;box-shadow:var(--pa-shadow);padding:13px 14px}
.pa-metric{min-height:114px;display:flex;flex-direction:column;justify-content:space-between}
.pa-metric-top{display:flex;justify-content:space-between;align-items:center;gap:8px}
.pa-label{font-size:.72rem;color:var(--pa-muted);font-weight:750}
.pa-icon{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:color-mix(in srgb,var(--pa-accent) 12%,transparent);color:var(--pa-accent);font-size:.77rem}
.pa-value{font-size:1.42rem;font-weight:850;letter-spacing:-.5px;line-height:1.1;margin-top:9px;font-variant-numeric:tabular-nums}
.pa-detail{font-size:.68rem;color:var(--pa-muted);margin-top:6px}
.pa-action{border:0;background:none;color:var(--pa-accent);font:inherit;font-size:.69rem;font-weight:800;padding:0;margin-top:8px;cursor:pointer;text-align:left}
.pa-action:hover{text-decoration:underline}
.positive{color:var(--pa-green)}
.negative{color:var(--pa-red)}
.pa-panel{border:1px solid var(--pa-border);border-radius:15px;background:var(--pa-card);box-shadow:var(--pa-shadow);overflow:hidden}
.pa-empty{padding:30px 18px;text-align:center;color:var(--pa-muted);font-size:.82rem}
.pa-empty i{display:block;font-size:1.45rem;color:var(--pa-accent);margin-bottom:10px}
.pa-empty strong{display:block;color:var(--pa-text);font-size:.91rem;margin-bottom:4px}
.pa-section-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 10px}
.pa-section-title{font-size:.9rem;font-weight:850}
.pa-section-sub{font-size:.7rem;color:var(--pa-muted)}
.pa-family-list{display:grid;gap:9px}
.pa-family{padding:12px 13px}
.pa-family-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}
.pa-family-title{font-size:.79rem;font-weight:850}
.pa-family-meta{font-size:.67rem;color:var(--pa-muted);margin-top:3px}
.pa-status{display:inline-flex;align-items:center;padding:3px 8px;border-radius:999px;font-size:.64rem;font-weight:850;text-transform:capitalize;white-space:nowrap;background:color-mix(in srgb,var(--pa-blue) 12%,transparent);color:var(--pa-blue)}
.pa-status.stoploss-hit{background:color-mix(in srgb,var(--pa-red) 13%,transparent);color:var(--pa-red)}
.pa-status.take-profit-reached{background:color-mix(in srgb,var(--pa-green) 13%,transparent);color:var(--pa-green)}
.pa-status.in-trade{background:color-mix(in srgb,var(--pa-blue) 13%,transparent);color:var(--pa-blue)}
.pa-status.pending-entry{background:color-mix(in srgb,var(--pa-amber) 14%,transparent);color:var(--pa-amber)}
.pa-trade-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:11px}
.pa-trade-cell{min-width:0}
.pa-trade-cell span{display:block;color:var(--pa-muted);font-size:.63rem}
.pa-trade-cell b{display:block;font-size:.73rem;margin-top:2px;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
.pa-family-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px}
.pa-locate{border:1px solid color-mix(in srgb,var(--pa-accent) 45%,var(--pa-border));border-radius:9px;background:color-mix(in srgb,var(--pa-accent) 8%,transparent);color:var(--pa-accent);font:inherit;font-size:.71rem;font-weight:850;padding:7px 10px;cursor:pointer}
.pa-symbol-modal{position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;padding:16px;overscroll-behavior:contain}
.pa-locate-failure-modal{position:fixed;inset:0;z-index:100001;background:rgba(0,0,0,.58);display:flex;align-items:center;justify-content:center;padding:18px}.pa-locate-failure-card{width:min(390px,100%);background:var(--pa-card);color:var(--pa-text);border:1px solid var(--pa-border);border-radius:16px;padding:22px;box-shadow:var(--pa-shadow);text-align:center}.pa-locate-failure-title{font-size:.95rem;font-weight:850;margin:0 0 16px}.pa-locate-failure-ok{border:0;border-radius:9px;background:var(--pa-accent);color:#fff;font:inherit;font-size:.78rem;font-weight:850;padding:9px 22px;cursor:pointer}
html.pa-modal-open,body.pa-modal-open{height:100%;overflow:hidden!important;overscroll-behavior:none!important}
body.pa-modal-open{position:fixed;left:0;right:0;width:100%;touch-action:none}
.pa-modal-card{width:min(440px,100%);height:60vh;max-height:60vh;min-height:min(240px,60vh);display:flex;flex-direction:column;overflow:hidden;overscroll-behavior:contain;background:var(--pa-card);color:var(--pa-text);border:1px solid var(--pa-border);border-radius:16px;padding:14px;box-shadow:var(--pa-shadow)}
.pa-modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px;flex:0 0 auto;font-weight:850}
.pa-modal-close{border:0;background:transparent;color:var(--pa-text);font-size:1.3rem;cursor:pointer}
.pa-symbol-list{display:grid;align-content:start;gap:7px;flex:1 1 auto;min-height:0;max-height:none;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;touch-action:pan-y;padding:1px 2px}
.pa-symbol-item{display:flex;align-items:center;gap:9px;padding:8px;border:1px solid var(--pa-border);border-radius:9px;font-size:.83rem}
.pa-symbol-item input{accent-color:var(--pa-accent)}
.pa-modal-actions{display:flex;justify-content:space-between;gap:7px;margin-top:10px;flex:0 0 auto}
.pa-modal-actions button,.pa-back{border:1px solid var(--pa-border);border-radius:9px;padding:8px 12px;background:var(--pa-bg);color:var(--pa-text);cursor:pointer;font:inherit;font-size:.77rem;font-weight:800}
.pa-symbol-scope{margin:13px 0 16px}
.pa-symbol-scope-title{font-size:.83rem;font-weight:850;margin-bottom:7px}
.pa-symbol-note{font-size:.73rem;color:var(--pa-muted);padding:8px 0}
.pa-isolated-head{display:flex;align-items:center;gap:12px;margin:0 0 15px}
.pa-isolated-head h2{font-size:.98rem;margin:0}
.pa-isolated-head p{font-size:.7rem;color:var(--pa-muted);margin:2px 0 0}
.pa-period-list{display:grid;gap:8px}
.pa-period{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;border:1px solid var(--pa-border);border-radius:12px;background:var(--pa-card);color:var(--pa-text);padding:13px 14px;text-align:left;font:inherit;cursor:pointer}
.pa-period strong{font-size:.81rem}
.pa-period span{color:var(--pa-muted);font-size:.7rem}
.pa-hidden{display:none!important}.pa-no-data{min-height:calc(100vh - 20px);display:flex;align-items:center;justify-content:center;text-align:center;font-size:1rem;font-weight:800;color:var(--pa-muted)}
.pa-foot{font-size:.65rem;color:var(--pa-muted);margin:12px 2px 0}
@media(max-width:900px){.pa-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:650px){.pa-page{padding:0 12px 88px;margin-top:10px}.pa-control-row{grid-template-columns:1fr 1fr}.pa-root-row{grid-template-columns:1fr;}.pa-control-row.pa-confirmation-row{grid-template-columns:1fr;}.pa-control-root{grid-column:1/-1}.pa-confirmation-control{grid-column:1/-1}.pa-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.pa-card{padding:11px}.pa-metric{min-height:110px}.pa-value{font-size:1.27rem}.pa-trade-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pa-section-title{font-size:.84rem}.pa-modal-card{padding:12px}.pa-modal-actions{gap:6px}.pa-modal-actions button{flex:1 1 0;padding:8px 7px;font-size:.7rem}}
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">
<div class="pa-page">
<?php if (!$programme || !$hasProgrammeRoots): ?>
    <div class="pa-no-data">No data yet</div>
<?php elseif ($analyticsError !== ''): ?>
    <div class="pa-panel pa-empty"><i class="fa-solid fa-database"></i><strong>Analytics storage is not installed</strong><?= pa_h($analyticsError) ?></div>
<?php else: ?>
    <div id="paDashboard">
        <div style="margin-top: 60px;"></div>
        <div class="pa-source-tabs" id="paSourceTabs" role="tablist" aria-label="Trade source">
            <button type="button" class="pa-source-tab active" data-trade-source="live" role="tab" aria-selected="true">Live</button>
            <button type="button" class="pa-source-tab" data-trade-source="history" role="tab" aria-selected="false">History</button>
        </div>
        <div class="pa-control-row pa-root-row" id="paRootRow">
            <div class="pa-control pa-control-root"><label for="paRoot">Strategy</label><select id="paRoot"><option value="all">All Strategies</option></select></div>
        </div>
        <div class="pa-control-row pa-confirmation-row pa-hidden" id="paConfirmationRow">
            <div class="pa-control pa-confirmation-control"><label for="paConfirmation">Confirmation</label><select id="paConfirmation"><option value="all">All confirmations</option></select></div>
        </div>
        <div class="pa-tabs pa-hidden" id="paTabs"></div>
        <div class="pa-control-row pa-scoped-control-row" id="paScopedControls">
            <div class="pa-control"><label>Symbols</label><button type="button" class="pa-symbol-button" id="paSymbolButton">Choose symbols</button></div>
            <div class="pa-control"><label for="paTimeframe">Timeframes</label><select id="paTimeframe"><option value="all">All timeframes</option></select></div>
        </div>
        <div id="paDashboardContent"></div>
    </div>
    <div id="paIsolated" class="pa-hidden"></div>
    <div class="pa-symbol-modal pa-hidden" id="paSymbolModal" role="dialog" aria-modal="true" aria-label="Select symbols">
        <div class="pa-modal-card"><div class="pa-modal-head"><span>Select programme symbols</span><button type="button" class="pa-modal-close" id="paSymbolClose" aria-label="Close">×</button></div><div class="pa-symbol-list" id="paSymbolList"></div><div class="pa-modal-actions"><button type="button" id="paSymbolAll">Select all</button><button type="button" id="paSymbolNone">Clear all</button><button type="button" id="paSymbolApply">Apply selection</button></div></div>
    </div>
    <div class="pa-locate-failure-modal pa-hidden" id="paLocateFailureModal" role="dialog" aria-modal="true" aria-labelledby="paLocateFailureTitle">
        <div class="pa-locate-failure-card">
            <div class="pa-locate-failure-title" id="paLocateFailureTitle">This trade can't be found</div>
            <button type="button" class="pa-locate-failure-ok" id="paLocateFailureOk">OK</button>
        </div>
    </div>
<?php endif; ?>
</div>
<?php if ($programme && $hasProgrammeRoots && $analyticsError === ''): ?>
<script>
(function(){
'use strict';
var PA_ROWS = <?= pa_json($analyticsRows) ?>;
var PA_ROOTS = <?= pa_json($rootOptions) ?>;
var PA_SYMBOLS = <?= pa_json($programmeSymbols) ?>;
var $ = function(id){return document.getElementById(id);};
function showLocateFailure(reason){
    var messages={
        not_found:"This trade can't be found",
        no_chart_data:'This trade no longer exists on the chart data',
        unidentifiable:'Trade record cannot be identified'
    };
    var modal=$('paLocateFailureModal');
    var title=$('paLocateFailureTitle');
    if(!modal||!title)return;
    title.textContent=messages[reason]||messages.not_found;
    modal.classList.remove('pa-hidden');
}
function closeLocateFailure(){
    var modal=$('paLocateFailureModal');
    if(modal)modal.classList.add('pa-hidden');
}
$('paLocateFailureOk').addEventListener('click',closeLocateFailure);
$('paLocateFailureModal').addEventListener('click',function(e){if(e.target===$('paLocateFailureModal'))closeLocateFailure();});
var rootMap = {};
var allSymbols = PA_SYMBOLS.slice();
var selectedSymbols = {};
var draftSymbols = {};
var activeTab = 'all-root';
var activeTradeSource = 'live';
var activeOutcome = 'all';
var pageStack = [];
var viewState = {page:'dashboard'};
var expandedMonths = {};
var expandedWeeks = {};
var currentMatchedRows = [];
var currentTradeRows = [];
var currentTitle = '';
var lastStats = null;
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function num(v){var n=Number(v);return Number.isFinite(n)?n:0;}
function fmt(v,max){return num(v).toLocaleString(undefined,{maximumFractionDigits:max==null?2:max});}
function low(v){return String(v==null?'':v).trim().toLowerCase();}
function stamp(v){if(!v)return '—';return String(v).replace('T',' ').replace(/\.\d+$/,'').slice(0,19);}
function dateKey(v){return v?String(v).slice(0,10):'';}
function tfms(tf){var m=String(tf).toLowerCase().match(/^(\d+)\s*(m|h|d|w)$/);return m?Number(m[1])*({m:60000,h:3600000,d:86400000,w:604800000}[m[2]]):1e15;}
function sortTfs(arr){return Array.from(new Set((arr||[]).map(low).filter(Boolean))).sort(function(a,b){return tfms(a)-tfms(b)||a.localeCompare(b);});}
function statusClass(s){return String(s||'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
function normalizedOutcome(s){var value=low(s).replace(/[\s_-]+/g,' ');var aliases={'breakeven':'closed flat','break even':'closed flat','closed at breakeven':'closed flat','closed breakeven':'closed flat','closed profit':'closed in profit','profit':'closed in profit','profitable':'closed in profit','closed loss':'closed in loss','loss':'closed in loss','losing':'closed in loss'};return aliases[value]||value;}
function outcomeLabel(s){var value=normalizedOutcome(s),labels={'stoploss hit':'Stoploss hit','take profit reached':'Profit reached','closed flat':'Closed Flat','closed in profit':'Closed in Profit','closed in loss':'Closed in Loss','pending entry':'Pending entry','in trade':'In trade','matched family':'Matched family',"identify but don't trade":"Identify but don't trade"};return labels[value]||s||'Trade configured · outcome not recorded';}
function isLossOutcome(r){var value=normalizedOutcome(r&&r.outcome);return value==='stoploss hit'||value==='closed in loss';}
function isProfitOutcome(r){var value=normalizedOutcome(r&&r.outcome);return value==='take profit reached'||value==='closed in profit';}
function isFlatOutcome(r){return normalizedOutcome(r&&r.outcome)==='closed flat';}
function isClosed(r){return isLossOutcome(r)||isProfitOutcome(r)||isFlatOutcome(r)||['closed','position closed','manual close','manually closed'].includes(normalizedOutcome(r&&r.outcome));}
function tradeRows(rows){return rows.filter(function(r){return num(r.trade_configured)===1;});}
function rootFor(id){return rootMap[String(id)]||null;}
function confirmationFor(root,id){if(!root)return null;return root.confirmations[String(id)]||null;}
function makeRootMap(){
    PA_ROOTS.forEach(function(r){
        var id=String(r.root_id);
        if(!rootMap[id])rootMap[id]={id:id,name:r.root_name||('Strategy '+id),timeframes:[],tradeEnabled:!!r.trade_enabled,tradeFor:r.trade_for||'root',confirmations:{}};
        else if(r.root_name)rootMap[id].name=r.root_name;
        rootMap[id].timeframes=rootMap[id].timeframes.concat(r.timeframes||[]);
        rootMap[id].tradeEnabled=rootMap[id].tradeEnabled||!!r.trade_enabled;
        (r.confirmations||[]).forEach(function(c){
            var cid=String(c.confirmation_root_id);
            if(!rootMap[id].confirmations[cid])rootMap[id].confirmations[cid]={id:cid,name:c.confirmation_root_name||('Confirmation '+cid),timeframes:[],tradeEnabled:!!c.trade_enabled};
            else if(c.confirmation_root_name)rootMap[id].confirmations[cid].name=c.confirmation_root_name;
            rootMap[id].confirmations[cid].timeframes=rootMap[id].confirmations[cid].timeframes.concat(c.timeframes||[]);
            rootMap[id].confirmations[cid].tradeEnabled=rootMap[id].confirmations[cid].tradeEnabled||!!c.trade_enabled;
        });
        rootMap[id].timeframes=sortTfs(rootMap[id].timeframes);
        Object.keys(rootMap[id].confirmations).forEach(function(cid){
            rootMap[id].confirmations[cid].timeframes=sortTfs(rootMap[id].confirmations[cid].timeframes);
        });
    });
}
function selectedSymbolList(){return Object.keys(selectedSymbols).filter(function(s){return selectedSymbols[s];});}
function selectedRootId(){return $('paRoot').value||'all';}
function selectedConfirmationId(){return $('paConfirmation').value||'all';}
function confirmationSelectionParts(){
    var value=selectedConfirmationId();
    if(value==='all')return {rootId:'all',confirmationId:'all'};
    if(value.indexOf(':')!==-1){var parts=value.split(':');return {rootId:parts[0],confirmationId:parts[1]||'all'};}
    return {rootId:selectedRootId(),confirmationId:value};
}
function getRootTfs(root){return root?root.timeframes.slice():[];}
function getConfirmationTfs(root,confirmationId){
    if(!root)return [];
    if(confirmationId&&confirmationId!=='all'){var c=confirmationFor(root,confirmationId);return c?c.timeframes.slice():[];}
    var values=[];
    Object.keys(root.confirmations).forEach(function(id){values=values.concat(root.confirmations[id].timeframes);});
    return sortTfs(values);
}
function currentMode(){
    var rid=selectedRootId();
    if(activeTab==='confirmation')return 'confirmation';
    if(rid==='all')return 'all';
    if(activeTab==='root')return 'root';
    return 'matched';
}
function availableTimeframes(){
    var rid=selectedRootId(),confirmationView=activeTab==='confirmation',values=[];
    if(!confirmationView){
        if(rid==='all')Object.keys(rootMap).forEach(function(id){values=values.concat(getRootTfs(rootMap[id]));});
        else values=getRootTfs(rootFor(rid));
        return sortTfs(values);
    }
    var selection=confirmationSelectionParts();
    if(rid==='all'){
        if(selection.confirmationId!=='all'){
            var selectedRoot=rootFor(selection.rootId);
            values=getConfirmationTfs(selectedRoot,selection.confirmationId);
        }else{
            Object.keys(rootMap).forEach(function(id){values=values.concat(getConfirmationTfs(rootMap[id],'all'));});
        }
    }else values=getConfirmationTfs(rootFor(rid),selection.confirmationId);
    return sortTfs(values);
}
function configureRootSelect(){
    var old=selectedRootId();
    var ids=Object.keys(rootMap).sort(function(a,b){return num(a)-num(b);});
    $('paRoot').innerHTML='<option value="all">All Strategies</option>'+ids.map(function(id){return '<option value="'+esc(id)+'">'+esc(rootMap[id].name)+'</option>';}).join('');
    if(old!=='all'&&rootMap[old])$('paRoot').value=old;
}
function configureConfirmationSelect(){
    var rid=selectedRootId(),select=$('paConfirmation'),previous=select.value||'all',options=[];
    if(rid==='all'){
        Object.keys(rootMap).sort(function(a,b){return num(a)-num(b);}).forEach(function(rootId){
            var root=rootMap[rootId];
            Object.keys(root.confirmations).sort(function(a,b){return num(a)-num(b);}).forEach(function(id){
                var confirmation=root.confirmations[id];
                options.push({value:rootId+':'+id,label:root.name+' · '+confirmation.name});
            });
        });
    }else{
        var root=rootFor(rid);
        if(root)Object.keys(root.confirmations).sort(function(a,b){return num(a)-num(b);}).forEach(function(id){
            options.push({value:id,label:root.confirmations[id].name});
        });
    }
    select.innerHTML='<option value="all">All confirmations</option>'+options.map(function(option){return '<option value="'+esc(option.value)+'">'+esc(option.label)+'</option>';}).join('');
    select.value=options.some(function(option){return option.value===previous;})?previous:'all';
    select.disabled=options.length===0;
    select.setAttribute('aria-label',options.length?'Choose a confirmation':'No confirmations configured');
}
function configureTimeframeSelect(){
    var old=$('paTimeframe').value||'all',tfs=availableTimeframes();
    $('paTimeframe').innerHTML='<option value="all">All timeframes</option>'+tfs.map(function(tf){return '<option value="'+esc(tf)+'">'+esc(tf.toUpperCase())+'</option>';}).join('');
    $('paTimeframe').value=old==='all'||tfs.includes(old)?old:'all';
}
function rootChartTimeframe(row,isConfirmation){
    return low(isConfirmation?(row.source_timeframe||''):row.timeframe);
}
function rowTimeframeCandidates(row){
    return [low(row.timeframe),low(row.source_timeframe)].filter(Boolean);
}
function configuredTimeframe(row,configured){
    var candidates=rowTimeframeCandidates(row);
    for(var i=0;i<candidates.length;i++){
        if(configured.includes(candidates[i]))return candidates[i];
    }
    return '';
}
function rowIsConfigured(row){
    var root=rootFor(row.root_id);
    if(!root)return false;
    var cid=num(row.confirmation_root_id),isConfirmation=cid>0;
    var candidates=rowTimeframeCandidates(row);
    if(isConfirmation){
        var conf=confirmationFor(root,String(cid));
        if(!conf||!conf.timeframes.length)return false;
        // Analytics rows may store parent/confirmation timeframes in either field.
        if(root.timeframes.length&&!configuredTimeframe(row,root.timeframes))return false;
        if(!configuredTimeframe(row,conf.timeframes))return false;
    }else{
        var nativeTf=low(row.timeframe);
        if(root.timeframes.length&&!root.timeframes.includes(nativeTf)){
            if(!configuredTimeframe(row,root.timeframes))return false;
        }
    }
    return true;
}
function rowMatchesFilters(row,context){
    if(!rowIsConfigured(row))return false;
    var rid=selectedRootId(),cid=num(row.confirmation_root_id),isConfirmation=cid>0;
    if(rid!=='all'&&String(row.root_id)!==rid)return false;
    var selectedTf=$('paTimeframe').value||'all';
    var rowRoot=rootFor(row.root_id);
    var rowConf=isConfirmation?confirmationFor(rowRoot,String(cid)):null;
    var rowTf=context==='confirmation'&&rowConf
        ?configuredTimeframe(row,rowConf.timeframes)
        :rootChartTimeframe(row,isConfirmation);
    if(selectedTf!=='all'&&rowTf!==selectedTf)return false;
    if((context==='all'||context==='root'||context==='matched')&&isConfirmation)return false;
    if(context==='confirmation'&&!isConfirmation)return false;
    if(context==='confirmation'){
        var selection=confirmationSelectionParts();
        if(selection.confirmationId!=='all'){
            if(String(cid)!==String(selection.confirmationId))return false;
            if(selection.rootId!=='all'&&String(row.root_id)!==String(selection.rootId))return false;
        }
        // trade_configured on the analytics row is authoritative for whether this
        // recorded family is a trade; configuration flags only control setup UI.
    }
    return true;
}
function selectedRows(context){
    context=context||currentMode();
    var symbols=selectedSymbolList();
    return PA_ROWS.filter(function(row){
        if(num(row.live_trades)!==(activeTradeSource==='live'?1:0))return false;
        if(!symbols.includes(String(row.symbol||'')))return false;
        if(num(row.matched_family)===0)return false;
        return rowMatchesFilters(row,context);
    });
}
function mainFamilyRowsForConfirmation(confirmationRows){
    var symbols=selectedSymbolList(),rid=selectedRootId(),selection=confirmationSelectionParts(),selectedTf=low($('paTimeframe').value||'all');
    var scopedPairs={},scopedKeys={};
    if(selectedTf&&selectedTf!=='all'){
        (confirmationRows||[]).forEach(function(row){
            var sourceTf=low(row.source_timeframe||'');
            if(!sourceTf)return;
            var pair=String(row.root_id)+'|'+String(row.symbol||'');
            scopedPairs[pair]=true;
            scopedKeys[pair+'|'+sourceTf]=true;
        });
    }
    return PA_ROWS.filter(function(row){
        var rootId=String(row.root_id),symbol=String(row.symbol||'');
        if(num(row.live_trades)!==(activeTradeSource==='live'?1:0))return false;
        if(!symbols.includes(symbol)||num(row.matched_family)===0||num(row.confirmation_root_id)>0)return false;
        if(!rowIsConfigured(row))return false;
        if(rid!=='all'&&rootId!==rid)return false;
        if(selection.rootId!=='all'&&rootId!==String(selection.rootId))return false;
        if(selectedTf&&selectedTf!=='all'){
            var pair=rootId+'|'+symbol,mainTf=low(row.timeframe||'');
            if(scopedPairs[pair])return !!scopedKeys[pair+'|'+mainTf];
            var root=rootFor(rootId);
            if(root&&root.timeframes.includes(selectedTf))return mainTf===selectedTf;
            // A confirmation timeframe may not share the same label as its main strategy timeframe.
            // When no source-timeframe mapping exists, keep the parent strategy's main families visible.
        }
        return true;
    });
}
function symbolData(rows){
    return allSymbols.map(function(symbol){
        return {symbol:symbol,rows:rows.filter(function(r){return String(r.symbol)===symbol;})};
    }).filter(function(item){return selectedSymbols[item.symbol];});
}
function renderNoDataNotes(rows){
    var items=symbolData(rows).filter(function(x){return !x.rows.length;});
    if(!items.length)return '';
    return '<div class="pa-symbol-note">'+items.map(function(x){return 'No analytics data for <strong>'+esc(x.symbol)+'</strong> under the selected strategy and timeframe.';}).join('<br>')+'</div>';
}
function calculate(rows,mainFamilyRows){
    var matchedRows=Array.isArray(mainFamilyRows)?mainFamilyRows:rows.filter(function(r){return num(r.confirmation_root_id)===0;});
    var trades=tradeRows(rows),closed=trades.filter(isClosed),sl=trades.filter(isLossOutcome).length,tp=trades.filter(isProfitOutcome).length,flat=trades.filter(isFlatOutcome).length,pending=trades.filter(function(r){return normalizedOutcome(r.outcome)==='pending entry';}).length,active=trades.filter(function(r){return normalizedOutcome(r.outcome)==='in trade';}),winsR=trades.reduce(function(s,r){return s+(isProfitOutcome(r)?num(r.risk_reward):0);},0),maxLoss=0,maxWin=0,equity=100,highest=100,maxDrawdown=0,activeSequence=[],ddSequence=[],peakSequence=[],accountLostAt=null;
    var streams={};
    closed.forEach(function(r){var key=[r.symbol,r.timeframe,r.source_timeframe,r.root_id,r.confirmation_root_id].join('|');(streams[key]||(streams[key]=[])).push(r);});
    Object.keys(streams).forEach(function(key){
        var lossRun=0,winRun=0;
        streams[key].sort(function(a,b){return stamp(a.event_time).localeCompare(stamp(b.event_time));}).forEach(function(r){
            if(isLossOutcome(r)){lossRun++;winRun=0;maxLoss=Math.max(maxLoss,lossRun);}
            else if(isProfitOutcome(r)){winRun++;lossRun=0;maxWin=Math.max(maxWin,winRun);}
            else{lossRun=0;winRun=0;}
        });
    });
    closed.sort(function(a,b){return stamp(a.event_time).localeCompare(stamp(b.event_time));}).forEach(function(r){
        var value=isLossOutcome(r)?-1:(isProfitOutcome(r)?Math.max(0,num(r.risk_reward)):0);
        equity+=value;
        activeSequence.push({row:r,value:value});
        var dd=Math.max(0,100-equity);
        if(dd>maxDrawdown){maxDrawdown=dd;ddSequence=activeSequence.slice();}
        if(equity>highest){highest=equity;peakSequence=activeSequence.slice();}
        if(equity<=0&&accountLostAt===null)accountLostAt='Strategy '+r.root_id+' · Family '+r.family_number;
        if(equity>=100)activeSequence=[];
    });
    return {trades:trades,total:trades.length,matched:matchedRows.filter(function(r){return num(r.matched_family)!==0;}).length,sl:sl,tp:tp,flat:flat,pending:pending,active:active,longActive:active.filter(function(r){return String(r.direction)==='long';}).length,shortActive:active.filter(function(r){return String(r.direction)==='short';}).length,winsR:winsR,netR:winsR-sl,closed:closed.length,winRate:closed.length?tp/closed.length*100:0,maxLoss:maxLoss,maxWin:maxWin,currentBalance:equity,highestBalance:highest,highestRevenue:Math.max(0,highest-100),maxDrawdown:maxDrawdown,drawdownSequence:ddSequence,peakSequence:peakSequence,accountLostAt:accountLostAt};
}
function metricCard(label,value,icon,detail,cls,action,buttonLabel){
    return '<article class="pa-card pa-metric"><div class="pa-metric-top"><span class="pa-label">'+label+'</span><span class="pa-icon"><i class="fa-solid '+icon+'"></i></span></div><div class="pa-value '+(cls||'')+'">'+value+'</div><div class="pa-detail">'+(detail||'')+'</div>'+(action?'<button type="button" class="pa-action" data-page-action="'+action+'">'+esc(buttonLabel||'See trades')+'</button>':'')+'</article>';
}
function renderMetrics(rows,mainFamilyRows){
    var x=calculate(rows,mainFamilyRows);
    lastStats=x;
    var html='';
    html+=metricCard('Total Main Families',fmt(x.matched,0),'fa-diagram-project','Main strategy matches, including strategies without trade setup','','matched-families','View main families');
    html+=metricCard('Total Trades',fmt(x.total,0),'fa-arrow-right-arrow-left',fmt(x.matched,0)+' main families','','trades-provision','Trades Provision');
    html+=metricCard('Stoploss hit',fmt(x.sl,0),'fa-shield-halved','Trade families closed at stoploss','negative','outcome:stoploss hit','View trades');
    html+=metricCard('Lost Risks',fmt(x.sl,0)+'R','fa-arrow-trend-down','Each stoploss = −1R · '+fmt(x.sl,0)+' losing families','negative','outcome:stoploss hit','View trades');
    html+=metricCard('Profit reached',fmt(x.tp,0),'fa-trophy','Profitable trade families','positive','outcome:take profit reached','View trades');
    html+=metricCard('Risk rewards','+'+fmt(x.winsR)+'R','fa-coins','Sum of '+fmt(x.tp,0)+' winning families','positive','outcome:take profit reached','View trades');
    html+=metricCard('Consecutive Loss',fmt(x.maxLoss,0),'fa-arrow-down','Longest losing streak before a profitable trade');
    html+=metricCard('Consecutive Win',fmt(x.maxWin,0),'fa-arrow-up','Longest winning streak without a loss');
    html+=metricCard('Highest Drawdown',fmt(x.maxDrawdown,2)+'%','fa-chart-line',x.accountLostAt?('Account exhausted at '+esc(x.accountLostAt)):'100% baseline drawdown sequence','','outcome:drawdown','View sequence');
    html+=metricCard('Highest Revenue Peak','+'+fmt(x.highestRevenue,2)+'%','fa-arrow-trend-up','Highest balance reached: '+fmt(x.highestBalance,2)+'%','','outcome:peak','View sequence');
    html+=metricCard('Win rate',fmt(x.winRate,1)+'%','fa-bullseye',fmt(x.tp,0)+' wins / '+fmt(x.closed,0)+' closed');
    html+=metricCard('Current virtual balance',fmt(x.currentBalance,2)+'%','fa-scale-balanced','100% baseline − lost risks + profit risk rewards',x.currentBalance>=100?'positive':'negative');
    html+=metricCard('Pending entries',fmt(x.pending,0),'fa-hourglass-half','Matched trade families awaiting entry','','outcome:pending entry','View trades');
    html+=metricCard('In trade',fmt(x.active.length,0),'fa-chart-column',fmt(x.longActive,0)+' long · '+fmt(x.shortActive,0)+' short','','outcome:in trade','View trades');
    $('paDashboardContent').innerHTML='<div class="pa-grid">'+html+'</div>';
}
function monthWeekNumber(date){
    var day=Number(String(date||'').slice(8,10));
    if(!Number.isFinite(day)||day<1||day>31)return 0;
    return Math.min(4,Math.floor((day-1)/7)+1);
}
function monthWeekKey(month,weekNumber){return String(month)+'|'+String(weekNumber);}
function parseMonthWeekKey(key){
    var parts=String(key||'').split('|');
    return {month:parts[0]||'',weekNumber:Number(parts[1]||0)};
}
function monthName(key){var d=new Date(key+'-01T12:00:00');return d.toLocaleDateString(undefined,{month:'long',year:'numeric'});}
function tradeDateFor(r){
    var outcome=normalizedOutcome(r.outcome);
    if(outcome==='stoploss hit'||outcome==='closed in loss')return dateKey(r.stoploss_trigger_time||r.close_time||r.closed_at||r.event_time||r.entry_trigger_time||r.entry_anchor_time);
    if(outcome==='take profit reached'||outcome==='closed in profit')return dateKey(r.take_profit_trigger_time||r.close_time||r.closed_at||r.event_time||r.entry_trigger_time||r.entry_anchor_time);
    if(outcome==='closed flat')return dateKey(r.breakeven_trigger_time||r.close_time||r.closed_at||r.event_time||r.entry_trigger_time||r.entry_anchor_time);
    return dateKey(r.entry_trigger_time||r.entry_anchor_time||r.event_time);
}
function familyTitle(r){var root=rootFor(r.root_id),conf=confirmationFor(root,String(r.confirmation_root_id));return (root?root.name:('Strategy '+r.root_id))+(num(r.confirmation_root_id)>0?' · '+(conf?conf.name:('Confirmation '+r.confirmation_root_id)):'')+' · Family '+r.family_number;}
function renderFamilyCard(r){
    var trade=num(r.trade_configured)===1,cl=statusClass(r.outcome),idx=PA_ROWS.indexOf(r),root=rootFor(r.root_id),conf=confirmationFor(root,String(r.confirmation_root_id)),tf=String(r.timeframe||'').toUpperCase(),source=r.source_timeframe&&r.source_timeframe!==r.timeframe?' · Parent '+String(r.source_timeframe).toUpperCase():'';
    var details=[['Entry anchor',stamp(r.entry_anchor_time)],['Entry trigger',stamp(r.entry_trigger_time)],['Stoploss',stamp(r.stoploss_time)+' · '+fmt(r.stoploss_price,5)],['Take profit',stamp(r.take_profit_time)+' · '+fmt(r.take_profit_price,5)]];
    var html='<article class="pa-card pa-family"><div class="pa-family-head"><div><div class="pa-family-title">'+esc(familyTitle(r))+'</div><div class="pa-family-meta">'+esc(r.symbol)+' · '+esc(tf)+esc(source)+' · '+esc(root?root.name:r.root_name||'')+(conf?' · '+esc(conf.name):'')+'</div></div><span class="pa-status '+cl+'">'+esc(outcomeLabel(r.outcome))+'</span></div>';
    if(trade){
        var resultRiskText=isProfitOutcome(r)?'+'+fmt(r.risk_reward,2)+'R':(isLossOutcome(r)?'−1R':(isFlatOutcome(r)?'0R · breakeven':fmt(r.risk_reward,2)+'R planned'));
        html+='<div class="pa-trade-grid">'+details.map(function(item){return '<div class="pa-trade-cell"><span>'+item[0]+'</span><b>'+esc(item[1])+'</b></div>';}).join('')+'</div><div class="pa-family-meta">'+esc(r.direction||'')+' · '+esc(r.order_type||'')+' · '+resultRiskText+'</div>';
    }else{
        html+='<div class="pa-family-meta">Matched family · no trade configured for this family</div>';
    }
    if(trade)html+='<div class="pa-family-actions"><button type="button" class="pa-locate" data-locate="'+idx+'"><i class="fa-solid fa-crosshairs"></i> Locate on chart</button></div>';
    html+='</article>';
    return html;
}
function renderFamilyCards(rows){
    if(!rows.length)return '<div class="pa-panel pa-empty"><i class="fa-solid fa-chart-line"></i><strong>No matched families found</strong>No family records match the selected symbols and configured timeframes.</div>';
    return rows.slice().sort(function(a,b){return num(a.root_id)-num(b.root_id)||num(a.confirmation_root_id)-num(b.confirmation_root_id)||stamp(a.event_time).localeCompare(stamp(b.event_time))||num(a.family_number)-num(b.family_number);}).map(renderFamilyCard).join('');
}
function renderFamilyPage(rows,title){
    return '<div class="pa-section-head"><div class="pa-section-title">'+esc(title)+'</div></div><div class="pa-family-list">'+renderFamilyCards(rows)+'</div>';
}
function rootTradeMode(root){
    if(!root)return {rootTrade:false,confirmationTrade:false,anyConfirmation:false};
    var confirmations=Object.keys(root.confirmations).map(function(id){return root.confirmations[id];});
    return {rootTrade:!!root.tradeEnabled,confirmationTrade:confirmations.some(function(c){return c.tradeEnabled;}),anyConfirmation:confirmations.length>0};
}
function hasAnyMainRootTradeSetup(){
    return Object.keys(rootMap).some(function(id){return rootMap[id]&&rootMap[id].tradeEnabled===true;});
}
function updateTabs(){
    var rid=selectedRootId(),root=rootFor(rid),tabs=$('paTabs'),mode=rootTradeMode(root),html='';
    if(rid==='all'){
        html='<button type="button" class="pa-tab '+(activeTab==='all-root'?'active':'')+'" data-tab="all-root">All Strategies</button>'+
             '<button type="button" class="pa-tab '+(activeTab==='confirmation'?'active':'')+'" data-tab="confirmation">Confirmations</button>';
        tabs.classList.remove('pa-hidden');
    }else if(!root){
        tabs.classList.add('pa-hidden');
        activeTab='matched';
    }else{
        if(mode.rootTrade&&mode.anyConfirmation){
            html='<button type="button" class="pa-tab '+(activeTab==='root'?'active':'')+'" data-tab="root">'+esc(root.name)+'</button><button type="button" class="pa-tab '+(activeTab==='confirmation'?'active':'')+'" data-tab="confirmation">Confirmation</button>';
            tabs.classList.remove('pa-hidden');
        }else if(mode.rootTrade){
            tabs.classList.add('pa-hidden');
            activeTab='root';
        }else if(mode.confirmationTrade){
            html='<button type="button" class="pa-tab '+(activeTab==='matched'?'active':'')+'" data-tab="matched">Matched families</button><button type="button" class="pa-tab '+(activeTab==='confirmation'?'active':'')+'" data-tab="confirmation">Confirmation</button>';
            tabs.classList.remove('pa-hidden');
        }else{
            tabs.classList.add('pa-hidden');
            activeTab='matched';
        }
    }
    tabs.innerHTML=html;
    var hasConfirmations=rid==='all'?Object.keys(rootMap).some(function(id){return Object.keys(rootMap[id].confirmations).length>0;}):!!root&&mode.anyConfirmation;
    var showConfirmationFilter=activeTab==='confirmation'&&hasConfirmations;
    $('paConfirmationRow').classList.toggle('pa-hidden',!showConfirmationFilter);

    // The strategy selector belongs exclusively to the All Strategies view.
    // Confirmation is a separate tab, so hide the entire strategy control row
    // while that tab is active. The selected root remains intact internally and
    // continues to scope the confirmation options when a specific strategy is selected.
    $('paRootRow').classList.toggle('pa-hidden',activeTab==='confirmation'||viewState.page!=='dashboard');
    $('paScopedControls').classList.toggle('pa-hidden',viewState.page!=='dashboard');
}
function rootsConfiguredForTrades(){
    var ids={};
    Object.keys(rootMap).forEach(function(id){
        var root=rootMap[id];
        if(root.tradeEnabled||Object.keys(root.confirmations).some(function(cid){return root.confirmations[cid].tradeEnabled;}))ids[id]=true;
    });
    return ids;
}
function renderDashboard(){
    var rid=selectedRootId(),root=rootFor(rid),mode=rootTradeMode(root);
    configureConfirmationSelect();
    updateTabs();
    if(rid==='all'){
        if(activeTab==='confirmation'){
            // Confirmation mode is an independent category: include confirmation
            // families from every strategy and exclude every main-root record.
            var confirmationRows=selectedRows('confirmation');
            $('paDashboardContent').innerHTML='<div class="pa-grid">'+renderMetricCardsOnly(confirmationRows,confirmationRows,'Total Confirmation Families')+'</div>';
        }else if(hasAnyMainRootTradeSetup()){
            // All Strategies shows only trades configured on main roots.
            var allRootTradeRows=selectedRows('root');
            var allMainFamilyRows=selectedRows('matched');
            $('paDashboardContent').innerHTML='<div class="pa-grid">'+renderMetricCardsOnly(allRootTradeRows,allMainFamilyRows)+'</div>';
        }else{
            // When no main root has a trade setup, show the matched main-root
            // families themselves instead of empty trade-statistic cards.
            $('paDashboardContent').innerHTML=renderFamilyPage(selectedRows('matched'),'Matched Families');
        }
        return;
    }
    if(activeTab==='confirmation'){
        // Confirmation statistics and family drill-downs contain confirmations only.
        var selectedConfirmationRows=selectedRows('confirmation');
        $('paDashboardContent').innerHTML='<div class="pa-grid">'+renderMetricCardsOnly(selectedConfirmationRows,selectedConfirmationRows,'Total Confirmation Families')+'</div>';
        return;
    }
    if(activeTab==='root'&&mode.rootTrade){
        var rootRows=selectedRows('root');
        $('paDashboardContent').innerHTML='<div class="pa-grid">'+renderMetricCardsOnly(rootRows,rootRows)+'</div>';
        return;
    }
    var matchedRows=selectedRows('matched');
    $('paDashboardContent').innerHTML=renderFamilyPage(matchedRows,'Matched Families');
}
function renderMetricCardsOnly(rows,mainFamilyRows,familyLabel){
    var x=calculate(rows,mainFamilyRows);
    lastStats=x;
    var html='';
    var familyMetricLabel=familyLabel||'Total Main Families';
    var familyMetricDetail=familyLabel==='Total Confirmation Families'?'Matched confirmation families across the selected scope':'Main strategy matches, including strategies without trade setup';
    var familyNoun=familyLabel==='Total Confirmation Families'?'confirmation families':'main families';
    html+=metricCard(familyMetricLabel,fmt(x.matched,0),'fa-diagram-project',familyMetricDetail,'','matched-families',familyLabel==='Total Confirmation Families'?'View confirmation families':'View main families');
    html+=metricCard('Total Trades',fmt(x.total,0),'fa-arrow-right-arrow-left',fmt(x.matched,0)+' '+familyNoun,'','trades-provision','Trades Provision');
    html+=metricCard('Stoploss hit',fmt(x.sl,0),'fa-shield-halved','Trade families closed at stoploss','negative','outcome:stoploss hit','View trades');
    html+=metricCard('Lost Risks',fmt(x.sl,0)+'R','fa-arrow-trend-down','Each stoploss = −1R · '+fmt(x.sl,0)+' losing families','negative','outcome:stoploss hit','View trades');
    html+=metricCard('Profit reached',fmt(x.tp,0),'fa-trophy','Profitable trade families','positive','outcome:take profit reached','View trades');
    html+=metricCard('Risk rewards','+'+fmt(x.winsR)+'R','fa-coins','Sum of '+fmt(x.tp,0)+' winning families','positive','outcome:take profit reached','View trades');
    html+=metricCard('Consecutive Loss',fmt(x.maxLoss,0),'fa-arrow-down','Longest losing streak before a profitable trade');
    html+=metricCard('Consecutive Win',fmt(x.maxWin,0),'fa-arrow-up','Longest winning streak without a loss');
    html+=metricCard('Highest Drawdown',fmt(x.maxDrawdown,2)+'%','fa-chart-line',x.accountLostAt?('Account exhausted at '+esc(x.accountLostAt)):'100% baseline drawdown sequence','','outcome:drawdown','View sequence');
    html+=metricCard('Highest Revenue Peak','+'+fmt(x.highestRevenue,2)+'%','fa-arrow-trend-up','Highest balance reached: '+fmt(x.highestBalance,2)+'%','','outcome:peak','View sequence');
    // Keep Win rate immediately beside Current virtual balance in the card grid.
    html+=metricCard('Win rate',fmt(x.winRate,1)+'%','fa-bullseye',fmt(x.tp,0)+' wins / '+fmt(x.closed,0)+' closed');
    html+=metricCard('Current virtual balance',fmt(x.currentBalance,2)+'%','fa-scale-balanced','100% baseline − lost risks + profit risk rewards',x.currentBalance>=100?'positive':'negative');
    html+=metricCard('Pending entries',fmt(x.pending,0),'fa-hourglass-half','Matched trade families awaiting entry','','outcome:pending entry','View trades');
    html+=metricCard('In trade',fmt(x.active.length,0),'fa-chart-column',fmt(x.longActive,0)+' long · '+fmt(x.shortActive,0)+' short','','outcome:in trade','View trades');
    return html;
}
function renderSymbolModal(){
    $('paSymbolList').innerHTML=allSymbols.map(function(symbol){
        var checked=draftSymbols[symbol]!==undefined?draftSymbols[symbol]:!!selectedSymbols[symbol];
        return '<label class="pa-symbol-item"><input type="checkbox" data-symbol="'+esc(symbol)+'" '+(checked?'checked':'')+'><span>'+esc(symbol)+'</span></label>';
    }).join('')||'<div class="pa-empty">No symbols are selected in this programme.</div>';
}
function updateSymbolButton(){
    var selected=selectedSymbolList();
    $('paSymbolButton').textContent=selected.length===0?'0 symbols selected':(selected.length===allSymbols.length?'All symbols selected':selected.length+' symbols selected');
}
function getDateRows(rows){
    return rows.filter(function(r){return num(r.trade_configured)===1&&tradeDateFor(r);});
}
function groupBy(rows,keyFn){
    var map={};
    rows.forEach(function(row){var key=keyFn(row);if(key)(map[key]||(map[key]=[])).push(row);});
    return map;
}
function monthRows(rows,month){
    return rows.filter(function(r){return tradeDateFor(r).slice(0,7)===month;});
}
function weekRows(rows,weekKey){
    var parsed=parseMonthWeekKey(weekKey);
    if(!parsed.month||parsed.weekNumber<1||parsed.weekNumber>4)return [];
    return rows.filter(function(r){
        var date=tradeDateFor(r);
        return date.slice(0,7)===parsed.month&&monthWeekNumber(date)===parsed.weekNumber;
    });
}
function goPage(page,extra){
    pageStack.push(viewState);
    viewState=Object.assign({page:page},extra||{});
    renderCurrentView();
    window.scrollTo(0,0);
}
function backPage(){
    if(!pageStack.length){viewState={page:'dashboard'};renderCurrentView();return;}
    viewState=pageStack.pop();
    renderCurrentView();
    window.scrollTo(0,0);
}
function isolatedHeader(title,subtitle){
    return '<div class="pa-isolated-head"><button type="button" class="pa-back" data-back><i class="fa-solid fa-arrow-left"></i> </button><div><h2>'+esc(title)+'</h2><p>'+esc(subtitle||'')+'</p></div></div>';
}
function renderTradesProvision(){
    var trades=getDateRows(currentTradeRows),months=groupBy(trades,function(r){return tradeDateFor(r).slice(0,7);}),keys=Object.keys(months).sort().reverse();
    var html=isolatedHeader('Trades Provision','Month → Week → Day · grouped by entry and outcome trigger times');
    if(!keys.length){$('paIsolated').innerHTML=html+'<div class="pa-panel pa-empty"><i class="fa-solid fa-calendar-days"></i><strong>No trade dates yet</strong>Trade dates will appear when matching trade timestamps are available.</div>';return;}
    html+='<div class="pa-period-list">'+keys.map(function(month){return '<button type="button" class="pa-period" data-month="'+esc(month)+'"><strong>'+esc(monthName(month))+'</strong><span>'+months[month].length+' trades · Open month <i class="fa-solid fa-chevron-right"></i></span></button>';}).join('')+'</div>';
    $('paIsolated').innerHTML=html;
}
function renderWeeks(month){
    var trades=getDateRows(currentTradeRows),mRows=monthRows(trades,month),weeks=groupBy(mRows,function(r){return String(monthWeekNumber(tradeDateFor(r)));}),keys=Object.keys(weeks).sort(function(a,b){return Number(b)-Number(a);});
    var html=isolatedHeader(monthName(month),'Select a week to view its trading days');
    if(!keys.length){
        $('paIsolated').innerHTML=html+'<div class="pa-panel pa-empty"><i class="fa-solid fa-calendar-days"></i><strong>No trading weeks</strong>No trades were recorded for this month.</div>';
        return;
    }
    html+='<div class="pa-period-list">'+keys.map(function(weekNumber){var weekKey=monthWeekKey(month,weekNumber);return '<button type="button" class="pa-period" data-week="'+esc(weekKey)+'"><strong>Week '+esc(weekNumber)+'</strong><span>'+weeks[weekNumber].length+' trades · Open week <i class="fa-solid fa-chevron-right"></i></span></button>';}).join('')+'</div>';
    $('paIsolated').innerHTML=html;
}
function renderDays(weekKey){
    var parsed=parseMonthWeekKey(weekKey),trades=getDateRows(currentTradeRows),wRows=weekRows(trades,weekKey),days=groupBy(wRows,function(r){return tradeDateFor(r);}),keys=Object.keys(days).sort().reverse();
    var html=isolatedHeader('Week '+parsed.weekNumber,'Select a trading day in '+monthName(parsed.month));
    html+='<div class="pa-period-list">'+keys.map(function(day){return '<button type="button" class="pa-period" data-day="'+esc(day)+'"><strong>'+esc(new Date(day+'T12:00:00').toLocaleDateString(undefined,{weekday:'long',day:'numeric',month:'long',year:'numeric'}))+'</strong><span>'+days[day].length+' trades · Open day <i class="fa-solid fa-chevron-right"></i></span></button>';}).join('')+'</div>';
    $('paIsolated').innerHTML=html;
}
function renderTradeDay(day){
    var trades=getDateRows(currentTradeRows).filter(function(r){return tradeDateFor(r)===day;});
    $('paIsolated').innerHTML=isolatedHeader(new Date(day+'T12:00:00').toLocaleDateString(undefined,{weekday:'long',day:'numeric',month:'long',year:'numeric'}),'Trades recorded on this day')+'<div class="pa-family-list">'+renderFamilyCards(trades)+'</div>';
}
function renderMatchedIsolated(){
    var rows=currentMatchedRows.slice();
    if(viewState.outcome&&viewState.outcome!=='all'){
        if(viewState.outcome==='drawdown'||viewState.outcome==='peak'){
            var stat=lastStats||calculate(currentTradeRows),seq=viewState.outcome==='drawdown'?stat.drawdownSequence:stat.peakSequence;
            rows=seq.map(function(x){return x.row;});
        }else rows=currentTradeRows.filter(function(r){return num(r.trade_configured)===1&&r.outcome===viewState.outcome;});
    }
    var confirmationMode=currentMode()==='confirmation';
    var pageTitle=viewState.outcome&&viewState.outcome!=='all'?'Trade results':(confirmationMode?'Confirmation Families':'Matched Families');
    var pageSubtitle=confirmationMode?'Confirmation family records for the current strategy, symbol and timeframe filters':'Main-root family records for the current strategy, symbol and timeframe filters';
    $('paIsolated').innerHTML=isolatedHeader(pageTitle,pageSubtitle)+'<div class="pa-family-list">'+renderFamilyCards(rows)+'</div>';
}
function syncParentChrome(){
    try{window.parent.postMessage({type:'programmeAnalyticsView',nested:viewState.page!=='dashboard'},'*');}catch(e){}
}
function renderCurrentView(){
    var isolated=viewState.page!=='dashboard';
    $('paDashboard').classList.toggle('pa-hidden',isolated);
    $('paIsolated').classList.toggle('pa-hidden',!isolated);
    syncParentChrome();
    if(!isolated){renderDashboard();return;}
    if(viewState.page==='trades-provision')renderTradesProvision();
    else if(viewState.page==='trades-weeks')renderWeeks(viewState.month);
    else if(viewState.page==='trades-days')renderDays(viewState.week);
    else if(viewState.page==='trades-day')renderTradeDay(viewState.day);
    else if(viewState.page==='matched-families')renderMatchedIsolated();
}
function refresh(){
    configureTimeframeSelect();
    renderDashboard();
    var mode=currentMode();
    if(mode==='confirmation'){
        currentTradeRows=selectedRows('confirmation');
        currentMatchedRows=selectedRows('confirmation');
    }else if(selectedRootId()==='all'){
        currentMatchedRows=selectedRows('matched');
        currentTradeRows=hasAnyMainRootTradeSetup()?selectedRows('root'):currentMatchedRows;
    }else{
        currentTradeRows=mode==='root'?selectedRows('root'):selectedRows('matched');
        currentMatchedRows=selectedRows('matched');
    }
    if(viewState.page!=='dashboard')renderCurrentView();
    else syncParentChrome();
}
var symbolModalScrollY=0;
var symbolModalOpen=false;
function setSymbolModalOpen(open){
    open=!!open;
    if(open===symbolModalOpen)return;
    symbolModalOpen=open;
    var modal=$('paSymbolModal');
    if(open){
        symbolModalScrollY=window.pageYOffset||document.documentElement.scrollTop||0;
        document.documentElement.classList.add('pa-modal-open');
        document.body.classList.add('pa-modal-open');
        document.body.style.top=(-symbolModalScrollY)+'px';
        modal.classList.remove('pa-hidden');
        try{window.parent.postMessage({type:'harvhubModalOpen'},'*');}catch(e){}
    }else{
        modal.classList.add('pa-hidden');
        document.documentElement.classList.remove('pa-modal-open');
        document.body.classList.remove('pa-modal-open');
        document.body.style.position='';
        document.body.style.top='';
        document.body.style.width='';
        window.scrollTo(0,symbolModalScrollY);
        try{window.parent.postMessage({type:'harvhubModalClose'},'*');}catch(e){}
    }
}
function openSymbolModal(){
    draftSymbols=Object.assign({},selectedSymbols);
    renderSymbolModal();
    setSymbolModalOpen(true);
}
$('paSourceTabs').addEventListener('click',function(e){
    var tab=e.target.closest('[data-trade-source]');
    if(!tab)return;
    activeTradeSource=tab.dataset.tradeSource==='history'?'history':'live';
    $('paSourceTabs').querySelectorAll('[data-trade-source]').forEach(function(button){
        var active=button===tab;
        button.classList.toggle('active',active);
        button.setAttribute('aria-selected',active?'true':'false');
    });
    viewState={page:'dashboard'};
    $('paRoot').value='all';
    activeTab='all-root';
    $('paTimeframe').value='all';
    $('paConfirmation').value='all';
    refresh();
});
$('paRoot').addEventListener('change',function(){
    var root=rootFor(selectedRootId()),mode=rootTradeMode(root);
    activeTab=selectedRootId()==='all'?'all-root':(mode.rootTrade?'root':'matched');
    $('paConfirmation').value='all';
    configureConfirmationSelect();
    $('paTimeframe').value='all';
    refresh();
});
$('paTimeframe').addEventListener('change',refresh);
$('paConfirmation').addEventListener('change',function(){$('paTimeframe').value='all';refresh();});
$('paTabs').addEventListener('click',function(e){
    var tab=e.target.closest('[data-tab]');
    if(!tab)return;
    activeTab=tab.dataset.tab;
    $('paTimeframe').value='all';
    refresh();
});
$('paSymbolButton').addEventListener('click',openSymbolModal);
$('paSymbolClose').addEventListener('click',function(){setSymbolModalOpen(false);});
$('paSymbolModal').addEventListener('click',function(e){if(e.target===$('paSymbolModal'))setSymbolModalOpen(false);});
$('paSymbolAll').addEventListener('click',function(){allSymbols.forEach(function(s){draftSymbols[s]=true;});renderSymbolModal();});
$('paSymbolNone').addEventListener('click',function(){allSymbols.forEach(function(s){draftSymbols[s]=false;});renderSymbolModal();});
$('paSymbolList').addEventListener('change',function(e){var item=e.target.closest('[data-symbol]');if(item)draftSymbols[item.dataset.symbol]=item.checked;});
$('paSymbolApply').addEventListener('click',function(){
    selectedSymbols={};
    allSymbols.forEach(function(symbol){selectedSymbols[symbol]=!!draftSymbols[symbol];});
    setSymbolModalOpen(false);
    updateSymbolButton();
    refresh();
});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&symbolModalOpen)setSymbolModalOpen(false);});
document.addEventListener('click',function(e){
    var pageAction=e.target.closest('[data-page-action]');
    if(pageAction){
        var action=pageAction.dataset.pageAction;
        if(action==='trades-provision')goPage('trades-provision');
        else if(action==='matched-families')goPage('matched-families',{outcome:'all'});
        else if(action.indexOf('outcome:')===0)goPage('matched-families',{outcome:action.slice(8)});
        return;
    }
    var back=e.target.closest('[data-back]');
    if(back){backPage();return;}
    var month=e.target.closest('[data-month]');
    if(month){goPage('trades-weeks',{month:month.dataset.month});return;}
    var week=e.target.closest('[data-week]');
    if(week){goPage('trades-days',{week:week.dataset.week});return;}
    var day=e.target.closest('[data-day]');
    if(day){goPage('trades-day',{day:day.dataset.day});return;}
    var locate=e.target.closest('[data-locate]');
    if(locate){
        var r=PA_ROWS[Number(locate.dataset.locate)];
        if(!r)return;
        var root=rootFor(r.root_id);
        var confirmationId=num(r.confirmation_root_id);
        var isConfirmation=confirmationId>0;
        var confirmation=isConfirmation?confirmationFor(root,String(confirmationId)):null;
        var chartTimeframe=isConfirmation
            ?(configuredTimeframe(r,confirmation?confirmation.timeframes:[])||low(r.source_timeframe)||low(r.timeframe))
            :(configuredTimeframe(r,root?root.timeframes:[])||low(r.timeframe)||low(r.source_timeframe));
        var tradeType=isConfirmation?'confirmation':'root';
        var hasConfirmationId=Object.prototype.hasOwnProperty.call(r,'confirmation_root_id')&&r.confirmation_root_id!==null&&r.confirmation_root_id!=='';
        var familyNumber=num(r.family_number);
        if(num(r.root_id)<=0||!hasConfirmationId||familyNumber<=0||!chartTimeframe||!String(r.symbol||'').trim()){
            showLocateFailure('unidentifiable');
            return;
        }
        var chartTradeId=[tradeType,String(r.root_id),String(confirmationId),chartTimeframe,String(r.family_number)].join('|');
        var requestId='pa-'+String(Date.now())+'-'+String(familyNumber);
        window.parent.postMessage({
            type:'programmeAnalyticsLocate',
            trade:{
                programme_id:r.programme_id,
                root_id:r.root_id,
                confirmation_root_id:confirmationId,
                is_confirmation:isConfirmation,
                trade_type:tradeType,
                family_number:r.family_number,
                chart_trade_id:chartTradeId,
                trade_id:chartTradeId,
                request_id:requestId,
                family_signature:r.family_signature||'',
                symbol:r.symbol,
                timeframe:chartTimeframe,
                chart_timeframe:chartTimeframe,
                parent_timeframe:low(r.timeframe),
                source_timeframe:low(r.source_timeframe),
                entry_anchor_time:r.entry_anchor_time||'',
                entry_trigger_time:r.entry_trigger_time||'',
                outcome:r.outcome||''
            }
        },'*');
        return;
    }
});
makeRootMap();
allSymbols.forEach(function(symbol){selectedSymbols[symbol]=true;});
configureRootSelect();
configureConfirmationSelect();
updateSymbolButton();
refresh();
window.addEventListener('message',function(e){
    if(!e.data||typeof e.data!=='object')return;
    if(e.data.type==='theme'){
        document.body.classList.toggle('dark-mode',!!e.data.dark);
        document.documentElement.classList.toggle('force-light',!e.data.dark);
    }
    if(e.data.type==='programmeTradeLocateResult'&&e.data.success!==true){
        showLocateFailure(e.data.reason||'not_found');
    }
});
try{window.parent.postMessage({type:'requestTheme'},'*');}catch(e){}
})();
</script>
<?php endif; ?>
</body>
</html>
