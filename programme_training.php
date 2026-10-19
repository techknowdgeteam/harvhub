<?php
//programme_training.php — Candlestick chart viewer for a programme
// Programme candlestick chart and persistent configuration builder.
// Configuration is stored separately from candle_records. Match results are browser-local JSON only.
 // Live candle refresh is wired in via `fetch_candles_latest`.

session_start();

try {
    $pdo = new PDO(
        "mysql:host=sql312.infinityfree.com;dbname=if0_40473107_harvhub;charset=utf8mb4",
        "if0_40473107",
        "InDQmdl53FZ85",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) { die("Database connection failed."); }

// Add the two per-candle annotation fields when upgrading an existing installation.
// This is a safe, additive migration; candle_records is not modified.
try {
    $cfgColumns = $pdo->query("SHOW COLUMNS FROM `programme_training_configuration`")->fetchAll(PDO::FETCH_COLUMN);
    // Rename the root-level chart application flag in the database.
    if (in_array('run_in_python', $cfgColumns, true) && !in_array('apply_to_chart', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` CHANGE COLUMN `run_in_python` `apply_to_chart` TINYINT(1) NOT NULL DEFAULT 1");
        $cfgColumns = array_values(array_diff($cfgColumns, ['run_in_python']));
        $cfgColumns[] = 'apply_to_chart';
    } elseif (!in_array('apply_to_chart', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `apply_to_chart` TINYINT(1) NOT NULL DEFAULT 1");
        $cfgColumns[] = 'apply_to_chart';
    }
    // Trade configuration columns: support both legacy *_from and current *_at names.
    if (in_array('entry_from', $cfgColumns, true) && !in_array('entry_at', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` CHANGE COLUMN `entry_from` `entry_at` VARCHAR(160) NULL DEFAULT NULL");
        $cfgColumns = array_values(array_diff($cfgColumns, ['entry_from'])); $cfgColumns[] = 'entry_at';
    }
    if (in_array('stoploss_from', $cfgColumns, true) && !in_array('stoploss_at', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` CHANGE COLUMN `stoploss_from` `stoploss_at` VARCHAR(160) NULL DEFAULT NULL");
        $cfgColumns = array_values(array_diff($cfgColumns, ['stoploss_from'])); $cfgColumns[] = 'stoploss_at';
    }
    if (in_array('take_profit_from', $cfgColumns, true) && !in_array('take_profit_at', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` CHANGE COLUMN `take_profit_from` `take_profit_at` VARCHAR(160) NULL DEFAULT NULL");
        $cfgColumns = array_values(array_diff($cfgColumns, ['take_profit_from'])); $cfgColumns[] = 'take_profit_at';
    }
    if (!in_array('order_type', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `order_type` VARCHAR(24) NOT NULL DEFAULT 'buy'");
        $cfgColumns[] = 'order_type';
    }
    if (!in_array('entry_trigger_after', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `entry_trigger_after` VARCHAR(160) NULL DEFAULT NULL");
        $cfgColumns[] = 'entry_trigger_after';
    }
    if (!in_array('entry_order_type', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `entry_order_type` VARCHAR(24) NOT NULL DEFAULT 'buy'");
        $cfgColumns[] = 'entry_order_type';
    }
    if (!in_array('candle_text', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `candle_text` VARCHAR(160) NULL DEFAULT NULL");
    }
    if (!in_array('text_position', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `text_position` VARCHAR(32) NULL DEFAULT NULL");
    }
    if (!in_array('root_name', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `root_name` VARCHAR(160) NULL DEFAULT NULL");
    }
    if (!in_array('text_color', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `text_color` VARCHAR(16) NOT NULL DEFAULT 'purple'");
    }
    if (!in_array('drawing_color', $cfgColumns, true)) {
        $pdo->exec("ALTER TABLE `programme_training_configuration` ADD COLUMN `drawing_color` VARCHAR(16) NOT NULL DEFAULT 'blue'");
    }
} catch (Throwable $e) {
    // Do not break chart viewing when the configuration table has not yet been created.
    error_log('Programme training annotation-column migration: ' . $e->getMessage());
}

if (!isset($_SESSION['user_email'])) { header("Location: index.php"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header("Location: index.php"); exit; }

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

// ---- Resolve active programme (same logic as signals_dashboard.php) ----
$selectedProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($selectedProgrammeId <= 0) {
    $q = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
    $q->execute([$userId]);
    $selectedProgrammeId = (int)($q->fetchColumn() ?: 0);
    if ($selectedProgrammeId > 0) $_SESSION['selected_programme_id'] = $selectedProgrammeId;
}

if (isset($_GET['id']) && (int)$_GET['id'] > 0) {
    $selectedProgrammeId = (int)$_GET['id'];
}

$programme = null;
if ($selectedProgrammeId > 0) {
    $pStmt = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $pStmt->execute([$selectedProgrammeId, $userId]);
    $programme = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$programmeId   = $programme ? (int)$programme['id'] : 0;
$programmeName = $programme ? ($programme['program_name'] ?? 'Programme') : 'Programme';

// ==================== LIST PARSER ====================
function parseCleanList($raw) {
    if ($raw === null || $raw === '') return [];
    $raw = (string)$raw;
    $trimmed = trim($raw);
    if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $flat = [];
            array_walk_recursive($decoded, function($v) use (&$flat) { $flat[] = $v; });
            $decoded = $flat;
        } else { $decoded = null; }
    } else { $decoded = null; }
    if (!is_array($decoded)) {
        $raw = str_replace(['[', ']', '{', '}', '"', "'"], '', $raw);
        $decoded = explode(',', $raw);
    }
    $out = [];
    foreach ($decoded as $item) {
        $item = trim((string)$item);
        $item = str_replace(['[', ']', '{', '}', '"', "'"], '', $item);
        $item = trim($item);
        if ($item === '') continue;
        $out[] = $item;
    }
    return array_values(array_unique($out));
}

// ==================== TIMEFRAME DURATION ====================
function tfToMs($tf) {
    if (!$tf) return PHP_INT_MAX;
    $s = strtolower(trim((string)$tf));
    if (!preg_match('/^(\d+)\s*([mhdw])$/', $s, $m)) return PHP_INT_MAX;
    $n    = (int)$m[1];
    $unit = $m[2];
    if ($unit === 'm') return $n * 60 * 1000;
    if ($unit === 'h') return $n * 60 * 60 * 1000;
    if ($unit === 'd') return $n * 24 * 60 * 60 * 1000;
    if ($unit === 'w') return $n * 7 * 24 * 60 * 60 * 1000;
    return PHP_INT_MAX;
}

// ==================== PROGRAMME OWNERSHIP CHECK ====================
function ptResolveOwnedProgramme($pdo, $userId, $programmeId) {
    static $cache = [];
    $programmeId = (int)$programmeId;
    if ($programmeId <= 0) return null;
    if (array_key_exists($programmeId, $cache)) return $cache[$programmeId];

    $q = $pdo->prepare("SELECT id, program_name, broker, server, login,
                               selected_symbols, selected_timeframes
                          FROM programme
                         WHERE id = ? AND userid = ?
                         LIMIT 1");
    $q->execute([$programmeId, $userId]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    $cache[$programmeId] = $row;
    return $row;
}

// ==================== BROKER CONNECTION CHECK ====================
$hasBroker = false;
if ($programme) {
    $hasBroker = (!empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']));
}

// ==================== SYMBOL + TIMEFRAME RESOLUTION ====================
// ONLY the programme's selected_symbols / selected_timeframes are
// consulted. No legacy-table fallbacks.
$selectedSymbols    = [];
$selectedTimeframes = [];

if ($programme) {
    $selectedSymbols    = parseCleanList((string)($programme['selected_symbols']    ?? ''));
    $selectedTimeframes = parseCleanList((string)($programme['selected_timeframes'] ?? ''));
}

sort($selectedSymbols, SORT_NATURAL | SORT_FLAG_CASE);
$selectedSymbols = array_values(array_unique($selectedSymbols));

usort($selectedTimeframes, function ($a, $b) {
    $am = tfToMs($a);
    $bm = tfToMs($b);
    if ($am === $bm) return strcmp((string)$a, (string)$b);
    return ($am < $bm) ? -1 : 1;
});
$selectedTimeframes = array_values(array_unique($selectedTimeframes));

// Portable UTF-8-safe truncation: do not require the optional mbstring extension.
function ptTextSubstr($value, $length) {
    $value = (string)$value;
    if (function_exists('mb_substr')) return mb_substr($value, 0, $length, 'UTF-8');
    if (function_exists('iconv_substr')) {
        $part = @iconv_substr($value, 0, $length, 'UTF-8');
        if ($part !== false) return $part;
    }
    return substr($value, 0, $length);
}

// ==================== AJAX: LOAD SAVED PROGRAMME CONFIGURATION ====================
// Reads the exact persisted rows for the signed-in user and selected programme.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_configuration'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_email'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.', 'rows' => []]);
        exit;
    }

    $reqProgrammeId = (int)($_POST['programme_id'] ?? 0);
    if ($reqProgrammeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Programme ID is required.', 'rows' => []]);
        exit;
    }

    $ownedStmt = $pdo->prepare('SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1');
    $ownedStmt->execute([$reqProgrammeId, $userId]);
    if (!$ownedStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Programme not found or access denied.', 'rows' => []]);
        exit;
    }

    try {
        $baseSelect = 'SELECT config_id, user_id, programme_id, timeframe, root_id, root_name, tree_id,
                    main_id, main_candle_name, reference_id, reference_candle_name,
                    offset_to, offset_id, offset_position, offset_position_point,
                    offset_candle_name, position, price_level, operator, use_for_trade,
                    entry_at, entry_price_level, stoploss_at, stoploss_price_level,
                    take_profit_at, take_profit_type, take_profit_price_level,
                    risk_reward, order_type, entry_order_type, entry_trigger_after,
                    draw_from, draw_from_price_level, draw_to,
                    draw_to_price_level, apply_to_chart, main_candle_type,
                    ref_candle_type, offset_candle, candle_text, text_position,
                    text_color, drawing_color, confirmation_root_id, confirmation_tree_id,
                    confirmation_timeframes, confirmation_anchor_candle_id,
                    confirmation_anchor_candle_name, confirmation_anchor_time, draw_for, trade_for';
        $stmt = $pdo->prepare($baseSelect . ' FROM programme_training_configuration WHERE user_id = ? AND programme_id = ? ORDER BY root_id, tree_id, config_id');
        $stmt->execute([$userId, $reqProgrammeId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $confirmationRows = [];
        try {
            // The dedicated confirmation table intentionally has a slightly different schema
            // from the parent configuration table (it uses parent_root_id and may not have
            // confirmation_tree_id). Build the SELECT from columns that actually exist.
            $confirmationDbColumns = $pdo->query("SHOW COLUMNS FROM `programme_training_confirmation_configuration`")->fetchAll(PDO::FETCH_COLUMN);
            $loadFields = [
                'config_id','user_id','programme_id','timeframe','root_id','root_name','tree_id',
                'main_id','main_candle_name','reference_id','reference_candle_name',
                'offset_to','offset_id','offset_position','offset_position_point','offset_candle_name',
                'position','price_level','operator','use_for_trade','entry_at','entry_price_level',
                'stoploss_at','stoploss_price_level','take_profit_at','take_profit_type',
                'take_profit_price_level','risk_reward','order_type','entry_order_type','entry_trigger_after',
                'draw_from','draw_from_price_level','draw_to','draw_to_price_level','apply_to_chart',
                'main_candle_type','ref_candle_type','offset_candle','candle_text','text_position',
                'text_color','drawing_color','confirmation_root_id','confirmation_tree_id',
                'confirmation_timeframes','confirmation_anchor_candle_id','confirmation_anchor_candle_name',
                'confirmation_anchor_time','draw_for','trade_for'
            ];
            $confirmSelect = [];
            foreach ($loadFields as $field) {
                if (in_array($field, $confirmationDbColumns, true)) {
                    $confirmSelect[] = '`' . $field . '`';
                } elseif ($field === 'confirmation_tree_id') {
                    $confirmSelect[] = 'NULL AS `confirmation_tree_id`';
                }
            }
            if (in_array('parent_root_id', $confirmationDbColumns, true)) {
                $confirmSelect[] = '`parent_root_id`';
            } else {
                $confirmSelect[] = 'NULL AS `parent_root_id`';
            }
            $confirmOrder = [];
            foreach (['parent_root_id','confirmation_root_id','tree_id','config_id'] as $field) {
                if (in_array($field, $confirmationDbColumns, true)) $confirmOrder[] = '`' . $field . '`';
            }
            $confirmSql = 'SELECT ' . implode(', ', $confirmSelect) .
                ' FROM `programme_training_confirmation_configuration` WHERE user_id = ? AND programme_id = ?' .
                ($confirmOrder ? ' ORDER BY ' . implode(', ', $confirmOrder) : '');
            $cs = $pdo->prepare($confirmSql);
            $cs->execute([$userId, $reqProgrammeId]);
            $confirmationRows = $cs->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $ignored) {
            error_log('Programme confirmation configuration load skipped: ' . $ignored->getMessage());
        }
        echo json_encode(['success' => true, 'rows' => $rows, 'confirmation_rows' => $confirmationRows]);
    } catch (Throwable $e) {
        error_log('Programme training configuration load failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not load saved configuration.', 'rows' => []]);
    }
    exit;
}

// ==================== AJAX: SAVE PROGRAMME CONFIGURATION ====================
// Saves normalized configuration rows ONLY to programme_training_configuration.
// No JSON/config shadow table is created and candle_records is never modified.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_configuration'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_email'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }

    $reqProgrammeId = (int)($_POST['programme_id'] ?? 0);
    $rawPayload = (string)($_POST['payload'] ?? '');
    if ($reqProgrammeId <= 0 || $rawPayload === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Programme and configuration payload are required.']);
        exit;
    }
    if (strlen($rawPayload) > 2 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['success' => false, 'message' => 'Configuration is too large to save.']);
        exit;
    }

    $ownedStmt = $pdo->prepare('SELECT id FROM programme WHERE id = ? AND userid = ? LIMIT 1');
    $ownedStmt->execute([$reqProgrammeId, $userId]);
    if (!$ownedStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Programme not found or access denied.']);
        exit;
    }

    $decodedPayload = json_decode($rawPayload, true);
    if (!is_array($decodedPayload) || !isset($decodedPayload['roots']) || !is_array($decodedPayload['roots'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid configuration payload.']);
        exit;
    }

    // Expand confirmation roots into the same validated tree-row pipeline, but route their
    // normalized rows to the dedicated confirmation table below.
    $parentRoots = $decodedPayload['roots'];
    foreach ($parentRoots as $parentRoot) {
        if (!is_array($parentRoot) || empty($parentRoot['confirmation_root']) || !is_array($parentRoot['confirmation_root'])) continue;
        $cr = $parentRoot['confirmation_root'];

        // The UI's authoritative confirmation-timeframe selector is serialized on the
        // parent as confirmation_timeframes. Prefer it when building the dedicated
        // confirmation-root payload; fall back to the nested object's timeframes only
        // for older clients.
        $selectedConfirmationTimeframes = $parentRoot['confirmation_timeframes'] ?? [];
        if (!is_array($selectedConfirmationTimeframes)) {
            $rawConfirmationTimeframes = trim((string)$selectedConfirmationTimeframes);
            $decodedConfirmationTimeframes = $rawConfirmationTimeframes !== '' ? json_decode($rawConfirmationTimeframes, true) : null;
            $selectedConfirmationTimeframes = is_array($decodedConfirmationTimeframes)
                ? $decodedConfirmationTimeframes
                : ($rawConfirmationTimeframes !== '' ? explode(',', $rawConfirmationTimeframes) : []);
        }
        $nestedConfirmationTimeframes = $cr['timeframes'] ?? [];
        if (!is_array($nestedConfirmationTimeframes)) $nestedConfirmationTimeframes = [];
        $cleanConfirmationTimeframes = static function ($values) {
            return array_values(array_unique(array_filter(array_map(
                static function ($tf) { return trim((string)$tf); },
                is_array($values) ? $values : []
            ), static function ($tf) { return $tf !== ''; })));
        };
        $selectedConfirmationTimeframes = $cleanConfirmationTimeframes($selectedConfirmationTimeframes);
        $nestedConfirmationTimeframes = $cleanConfirmationTimeframes($nestedConfirmationTimeframes);
        $cr['timeframes'] = $selectedConfirmationTimeframes ?: $nestedConfirmationTimeframes;

        $cr['_is_confirmation'] = true;
        $cr['_parent_root_id'] = (int)($parentRoot['id'] ?? 1);
        $cr['_anchor_candle_id'] = (int)($cr['anchor_candle_id'] ?? 0) ?: null;
        $cr['_anchor_candle_name'] = (string)($cr['anchor_candle_name'] ?? '');
        $cr['_anchor_time'] = (string)($cr['anchor_time'] ?? 'open_time');
        $cr['_confirmation_timeframes'] = $cr['timeframes'];
        $cr['id'] = max(1, (int)($cr['id'] ?? ((int)($parentRoot['id'] ?? 1) * 1000 + 1)));
        $cr['root_name'] = (string)($cr['root_name'] ?? ('Confirmation for root ' . (int)($parentRoot['id'] ?? 1)));
        $decodedPayload['roots'][] = $cr;
    }

    // Validate parent roots and confirmation roots against their respective selectors.
    // For confirmation roots, confirmation_timeframes is the priority source because it
    // is the dedicated selector labelled "Select confirmation timeframes (parent-root timeframes are excluded)".
    foreach ($decodedPayload['roots'] as $rootIndex => $rootCheck) {
        if (!is_array($rootCheck)) continue;
        $isConfirmationCheck = !empty($rootCheck['_is_confirmation']);
        // Confirmation timeframes are optional: an empty selection means this confirmation
        // root is saved but is not applied to any chart timeframe.
        if ($isConfirmationCheck) {
            continue;
        } else {
            $rootTimeframes = $rootCheck['timeframes'] ?? null;
            if (!is_array($rootTimeframes)) {
                $legacyTimeframe = trim((string)($rootCheck['timeframe'] ?? ''));
                $decodedTimeframes = $legacyTimeframe !== '' ? json_decode($legacyTimeframe, true) : null;
                $rootTimeframes = is_array($decodedTimeframes)
                    ? $decodedTimeframes
                    : ($legacyTimeframe !== '' ? [$legacyTimeframe] : []);
            }
        }
        if (!is_array($rootTimeframes)) $rootTimeframes = [];
        $rootTimeframes = array_values(array_unique(array_filter(array_map(
            static function ($tf) { return trim((string)$tf); },
            $rootTimeframes
        ), static function ($tf) { return $tf !== ''; })));
        if (!$rootTimeframes) {
            $missingRootId = (int)($rootCheck['id'] ?? ($rootIndex + 1));
            $message = $isConfirmationCheck
                ? 'Confirmation root ' . $missingRootId . ': select at least one timeframe in "Select confirmation timeframes (parent-root timeframes are excluded)" before saving.'
                : 'Root ' . $missingRootId . ': select at least one timeframe before saving.';
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
    }

    try {
        // Fail clearly if the requested destination table has not been created.
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'programme_training_configuration'");
        if (!$tableCheck || !$tableCheck->fetchColumn()) {
            throw new RuntimeException('The required table programme_training_configuration does not exist. Create it using the supplied SQL first.');
        }

        $allowedLevels = ['high','low','open','close','candle_center','body_center','high_wick_center','low_wick_center'];
        $allowedTextPositions = ['high','low'];
        $allowedOperators = ['<','>','=<','>=','='];
        $allowedPositions = ['all','left','right','latest_candle'];
        $allowedOffsetPoints = ['all','random','fixed'];
        $allowedCandleTypes = ['bearish','bullish','bearish_or_bullish'];
        // Persist named CSS colors or the special "custom" theme-aware color.
        $allowedColors = ['custom','purple','red','blue','green','yellow','orange','pink','white','black','cyan','magenta','lime','teal','navy','gold','coral','violet','indigo','brown','gray','grey'];
        $rows = [];

        $cleanText = static function ($value, $max = 160) {
            if ($value === null || $value === '') return null;
            return ptTextSubstr(trim((string)$value), $max);
        };
        $safeChoice = static function ($value, array $allowed, $fallback = null) {
            $value = (string)($value ?? '');
            return in_array($value, $allowed, true) ? $value : $fallback;
        };
        $toDbTakeProfitType = static function ($value) {
            $value = strtolower(trim((string)$value));
            if ($value === 'price_levels' || $value === 'price levels') return 'price levels';
            return 'risk reward';
        };
        $rootFields = static function (array $root) use ($cleanText, $safeChoice, $allowedLevels, $toDbTakeProfitType) {
            $tpType = $toDbTakeProfitType($root['take_profit_type'] ?? 'risk_reward');
            return [
                'root_name' => $cleanText($root['root_name'] ?? '', 160),
                'use_for_trade' => !empty($root['use_for_trades']) ? 1 : 0,
                'entry_at' => $cleanText($root['entry_at'] ?? $root['entry_from'] ?? null),
                'order_type' => $safeChoice($root['order_type'] ?? $root['entry_order_type'] ?? 'buy', ['buy','sell','buy-limit','sell-limit','buy-stop','sell-stop'], 'buy'),
                'entry_order_type' => $safeChoice($root['order_type'] ?? $root['entry_order_type'] ?? 'buy', ['buy','sell','buy-limit','sell-limit','buy-stop','sell-stop'], 'buy'),
                'entry_trigger_after' => $cleanText($root['entry_trigger_after'] ?? null),
                'entry_price_level' => $safeChoice($root['entry_price_level'] ?? null, $allowedLevels),
                'stoploss_at' => $cleanText($root['stoploss_at'] ?? $root['stoploss_from'] ?? null),
                'stoploss_price_level' => $safeChoice($root['stoploss_price_level'] ?? null, $allowedLevels),
                'take_profit_at' => $cleanText($root['take_profit_at'] ?? $root['take_profit_from'] ?? null),
                'take_profit_type' => $tpType,
                'take_profit_price_level' => $tpType === 'risk reward' ? null : $safeChoice($root['take_profit_price_level'] ?? null, $allowedLevels),
                'risk_reward' => isset($root['risk_reward']) && $root['risk_reward'] !== '' && is_numeric($root['risk_reward']) ? round((float)$root['risk_reward'], 3) : null,
                'draw_from' => null,
                'draw_from_price_level' => null,
                'draw_to' => null,
                'draw_to_price_level' => null,
                'apply_to_chart' => array_key_exists('apply_to_chart', $root) ? (!empty($root['apply_to_chart']) ? 1 : 0) : (!empty($root['run_in_python']) ? 1 : 0),
                'confirmation_root_id' => !empty($root['_is_confirmation']) ? (int)($root['id'] ?? 0) : (isset($root['confirmation_root_id']) ? (int)$root['confirmation_root_id'] : null),
                'confirmation_tree_id' => isset($root['confirmation_tree_id']) ? (int)$root['confirmation_tree_id'] : null,
                'confirmation_timeframes' => !empty($root['_is_confirmation']) ? json_encode($root['_confirmation_timeframes'] ?? []) : (isset($root['confirmation_timeframes']) ? (is_array($root['confirmation_timeframes']) ? json_encode($root['confirmation_timeframes']) : (string)$root['confirmation_timeframes']) : null),
                'confirmation_anchor_candle_id' => !empty($root['_is_confirmation']) ? ($root['_anchor_candle_id'] ?? null) : (isset($root['confirmation_anchor_candle_id']) ? (int)$root['confirmation_anchor_candle_id'] : null),
                'confirmation_anchor_candle_name' => !empty($root['_is_confirmation']) ? $cleanText($root['_anchor_candle_name'] ?? '', 160) : $cleanText($root['confirmation_anchor_candle_name'] ?? '', 160),
                'confirmation_anchor_time' => !empty($root['_is_confirmation']) ? $safeChoice($root['_anchor_time'] ?? 'open_time', ['open_time','close_time'], 'open_time') : $safeChoice($root['confirmation_anchor_time'] ?? null, ['open_time','close_time']),
                'draw_for' => !empty($root['_is_confirmation']) ? 'root_confirmation' : $safeChoice($root['draw_for'] ?? 'root', ['root','root_confirmation'], 'root'),
                'trade_for' => $safeChoice($root['trade_for'] ?? 'root', ['root','root_confirmation'], 'root'),
                '__is_confirmation' => !empty($root['_is_confirmation']) ? 1 : 0,
                '__parent_root_id' => !empty($root['_is_confirmation']) ? (int)($root['_parent_root_id'] ?? 0) : null
            ];
        };

        foreach ($decodedPayload['roots'] as $root) {
            if (!is_array($root)) continue;
            $rootId = max(1, (int)($root['id'] ?? 1));
            $rootTimeframes = $root['timeframes'] ?? null;
            if (!is_array($rootTimeframes)) {
                $legacyTimeframe = trim((string)($root['timeframe'] ?? ''));
                $decodedTimeframes = $legacyTimeframe !== '' ? json_decode($legacyTimeframe, true) : null;
                $rootTimeframes = is_array($decodedTimeframes)
                    ? $decodedTimeframes
                    : ($legacyTimeframe !== '' ? [$legacyTimeframe] : []);
            }
            $rootTimeframes = array_values(array_unique(array_filter(array_map(
                static function ($tf) { return trim((string)$tf); },
                $rootTimeframes
            ), static function ($tf) { return $tf !== ''; })));
            if (!$rootTimeframes && empty($root['_is_confirmation'])) {
                $displayRootName = trim((string)($root['root_name'] ?? $root['name'] ?? ''));
                if ($displayRootName === '') $displayRootName = 'Root ' . $rootId;
                throw new RuntimeException($displayRootName . ' (Root ' . $rootId . '): select at least one timeframe before saving.');
            }
            // The schema stores one timeframe string per configuration row.
            // Keep all selected root timeframes intact as a comma-separated value.
            $timeframe = implode(',', $rootTimeframes);
            $common = $rootFields($root);
            $trees = isset($root['trees']) && is_array($root['trees']) ? $root['trees'] : [];

            foreach ($trees as $treeIndex => $tree) {
                if (!is_array($tree)) continue;
                $treeId = max(1, (int)($tree['tree_id'] ?? 1));
                $main = isset($tree['main']) && is_array($tree['main']) ? $tree['main'] : [];
                $reference = isset($tree['reference']) && is_array($tree['reference']) ? $tree['reference'] : [];
                $mainId = isset($main['main_id']) ? (int)$main['main_id'] : (int)($main['id'] ?? 0);
                $referenceId = isset($reference['reference_id']) ? (int)$reference['reference_id'] : (int)($reference['id'] ?? 0);
                $mainId = $mainId > 0 ? $mainId : null;
                $referenceId = $referenceId > 0 ? $referenceId : null;
                $mainName = $cleanText($main['main_candle_name'] ?? '', 120);
                $refName = $cleanText($reference['reference_candle_name'] ?? '', 120);
                $mainType = $safeChoice($main['main_candle_type'] ?? null, $allowedCandleTypes, 'bearish_or_bullish');
                $refType = $safeChoice($reference['ref_candle_type'] ?? null, $allowedCandleTypes, 'bearish_or_bullish');
                $mainOffset = isset($main['offset_to']) && $main['offset_to'] !== '' ? (int)$main['offset_to'] : null;
                $refOffset = $mainId;
                $mainOffsetPosition = isset($main['offset_position']) && $main['offset_position'] !== '' ? max(1, (int)$main['offset_position']) : 1;
                $refOffsetPosition = isset($reference['offset_position']) && $reference['offset_position'] !== '' ? max(1, (int)$reference['offset_position']) : 1;
                $mainPosition = $safeChoice($main['position'] ?? null, $allowedPositions, 'all');
                $refPosition = $safeChoice($reference['position'] ?? null, ['left','right'], 'left');
                $mainOffsetPoint = $safeChoice($main['offset_pos_point'] ?? null, $allowedOffsetPoints, 'all');
                $refOffsetPoint = $safeChoice($reference['offset_pos_point'] ?? null, $allowedOffsetPoints, 'all');
                $mainPrice = $safeChoice($main['price_level'] ?? null, $allowedLevels, 'high');
                $mainText = $cleanText($main['candle_text'] ?? '', 160);
                $mainTextPosition = $safeChoice($main['text_position'] ?? null, $allowedTextPositions, 'high');
                $mainTextColor = $safeChoice($main['text_color'] ?? null, $allowedColors, 'purple');
                $refPrice = $safeChoice($reference['price_level'] ?? null, $allowedLevels, 'high');
                // A new offset is its own candle identity in this tree. Its label must
                // be saved directly to candle_text on this reference/offset row.
                $isNewOffset = (($reference['referenceMode'] ?? 'existing') === 'new_offset');
                $refText = (($treeIndex === 0) || $isNewOffset) ? $cleanText($reference['candle_text'] ?? '', 160) : null;
                $refTextPosition = $safeChoice($reference['text_position'] ?? null, $allowedTextPositions, 'high');
                $refTextColor = $safeChoice($reference['text_color'] ?? null, $allowedColors, 'purple');
                $operator = $safeChoice($main['operator'] ?? null, $allowedOperators, '>');
                $offsetName = $cleanText($main['offset_to_name'] ?? '', 120);
                $offsetType = $mainOffset !== null ? $safeChoice($main['offset_candle'] ?? null, $allowedCandleTypes, 'bearish_or_bullish') : null;

                // Main-candle row: reference_id is NULL so it is distinguishable from the reference row.
                $rows[] = array_merge([
                    'user_id' => $userId, 'programme_id' => $reqProgrammeId, 'timeframe' => ptTextSubstr($timeframe, 32),
                    'root_id' => $rootId, 'tree_id' => $treeId,
                    'main_id' => $mainId, 'main_candle_name' => $mainName,
                    'reference_id' => null, 'reference_candle_name' => null,
                    'offset_to' => $mainOffset, 'offset_id' => $mainOffset,
                    'offset_position' => $mainOffsetPosition, 'offset_position_point' => $mainOffsetPoint,
                    'offset_candle_name' => $offsetName, 'position' => $mainPosition,
                    'price_level' => $mainPrice, 'operator' => $operator,
                    'candle_text' => $mainText, 'text_position' => $mainTextPosition,
                    'text_color' => $mainTextColor, 'drawing_color' => 'blue',
                    'main_candle_type' => $mainType, 'ref_candle_type' => null, 'offset_candle' => $offsetType
                ], $common);

                // Reference-candle row: linked to its tree's main through main_id/offset_to.
                $rows[] = array_merge([
                    'user_id' => $userId, 'programme_id' => $reqProgrammeId, 'timeframe' => ptTextSubstr($timeframe, 32),
                    'root_id' => $rootId, 'tree_id' => $treeId,
                    'main_id' => $mainId, 'main_candle_name' => $mainName,
                    'reference_id' => $referenceId, 'reference_candle_name' => $refName,
                    'offset_to' => $refOffset, 'offset_id' => $refOffset,
                    'offset_position' => $refOffsetPosition, 'offset_position_point' => $refOffsetPoint,
                    'offset_candle_name' => $mainName, 'position' => $refPosition,
                    'price_level' => $refPrice, 'operator' => $operator,
                    'candle_text' => $refText, 'text_position' => $refTextPosition,
                    'text_color' => $refTextColor, 'drawing_color' => 'blue',
                    'main_candle_type' => null, 'ref_candle_type' => $refType,
                    'offset_candle' => $isNewOffset ? $refType : null
                ], $common);
            }

            // Drawing entries are rows in the same table; they are identified by draw_from/draw_to being populated.
            $drawings = isset($root['drawings']) && is_array($root['drawings']) ? $root['drawings'] : [];
            foreach ($drawings as $draw) {
                if (!is_array($draw)) continue;
                $drawTo = $cleanText($draw['draw_to'] ?? '', 160);
                $drawToLevelRaw = (string)($draw['draw_to_price_level'] ?? 'high');
                $drawToLevel = $drawToLevelRaw === 'axis' ? 'axis' : (($drawTo === 'any_intercept_candle') ? null : $safeChoice($drawToLevelRaw, $allowedLevels));
                $drawRow = [
                    'user_id' => $userId, 'programme_id' => $reqProgrammeId, 'timeframe' => ptTextSubstr($timeframe, 32),
                    'root_id' => $rootId, 'tree_id' => max(1, (int)($draw['tree_id'] ?? 1)),
                    'main_id' => null, 'main_candle_name' => null,
                    'reference_id' => null, 'reference_candle_name' => null,
                    'offset_to' => null, 'offset_id' => null,
                    'offset_position' => 1, 'offset_position_point' => 'all', 'offset_candle_name' => null,
                    'position' => 'all', 'price_level' => null, 'operator' => '>',
                    'main_candle_type' => null, 'ref_candle_type' => null, 'offset_candle' => null,
                    'text_color' => 'purple',
                    'drawing_color' => $safeChoice($draw['drawing_color'] ?? null, $allowedColors, 'blue')
                ];
                $drawRow = array_merge($drawRow, $common, [
                    'draw_from' => $cleanText($draw['draw_from'] ?? '', 160),
                    'draw_from_price_level' => $safeChoice($draw['draw_from_price_level'] ?? null, $allowedLevels),
                    'draw_to' => $drawTo,
                    'draw_to_price_level' => $drawToLevel
                ]);
                $rows[] = $drawRow;
            }
        }

        $columns = [
            'user_id','programme_id','timeframe','root_id','root_name','tree_id','main_id','main_candle_name',
            'reference_id','reference_candle_name','offset_to','offset_id','offset_position',
            'offset_position_point','offset_candle_name','position','price_level','operator',
            'use_for_trade','entry_at','entry_price_level','stoploss_at','stoploss_price_level',
            'take_profit_at','take_profit_type','take_profit_price_level','risk_reward','order_type','entry_order_type','entry_trigger_after',
            'draw_from','draw_from_price_level','draw_to','draw_to_price_level','apply_to_chart',
            'main_candle_type','ref_candle_type','offset_candle','candle_text','text_position',
            'text_color','drawing_color','confirmation_root_id','confirmation_tree_id',
            'confirmation_timeframes','confirmation_anchor_candle_id','confirmation_anchor_candle_name',
            'confirmation_anchor_time','draw_for','trade_for'
        ];
        if (count($rows) === 0) {
            throw new RuntimeException('Nothing was saved because no configured tree or drawing produced a database row. Configure at least one tree or drawing, then save again. Existing saved rows were left unchanged.');
        }

        // Prepare INSERT statements against each table's real schema. The confirmation
        // table differs from the parent table: parent_root_id exists there, while
        // confirmation_tree_id may not. Never INSERT a column the target table lacks.
        $mainDbColumns = $pdo->query("SHOW COLUMNS FROM `programme_training_configuration`")->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_values(array_filter($columns, static function ($column) use ($mainDbColumns) {
            return in_array($column, $mainDbColumns, true);
        }));
        foreach (['user_id','programme_id','timeframe','root_id','tree_id'] as $requiredColumn) {
            if (!in_array($requiredColumn, $columns, true)) {
                throw new RuntimeException('The programme_training_configuration table is missing required column: ' . $requiredColumn);
            }
        }
        $columnSql = implode(',', array_map(static function ($c) { return '`' . $c . '`'; }, $columns));
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $insert = $pdo->prepare("INSERT INTO `programme_training_configuration` ($columnSql) VALUES ($placeholders)");

        $confirmationDbColumns = $pdo->query("SHOW COLUMNS FROM `programme_training_confirmation_configuration`")->fetchAll(PDO::FETCH_COLUMN);
        $confirmationColumns = array_values(array_filter(array_merge($columns, ['parent_root_id']), static function ($column) use ($confirmationDbColumns) {
            return in_array($column, $confirmationDbColumns, true);
        }));
        foreach (['user_id','programme_id','timeframe','root_id','tree_id','parent_root_id'] as $requiredColumn) {
            if (!in_array($requiredColumn, $confirmationColumns, true)) {
                throw new RuntimeException('The programme_training_confirmation_configuration table is missing required column: ' . $requiredColumn);
            }
        }
        $confirmationColumnSql = implode(',', array_map(static function ($c) { return '`' . $c . '`'; }, $confirmationColumns));
        $confirmationInsert = $pdo->prepare("INSERT INTO `programme_training_confirmation_configuration` ($confirmationColumnSql) VALUES (" . implode(',', array_fill(0, count($confirmationColumns), '?')) . ")");

        $pdo->beginTransaction();
        $delete = $pdo->prepare('DELETE FROM `programme_training_configuration` WHERE user_id = ? AND programme_id = ?');
        $delete->execute([$userId, $reqProgrammeId]);
        $deleteConfirmation = $pdo->prepare('DELETE FROM `programme_training_confirmation_configuration` WHERE user_id = ? AND programme_id = ?');
        $deleteConfirmation->execute([$userId, $reqProgrammeId]);
        $savedMainRows = 0; $savedConfirmationRows = 0;
        foreach ($rows as $row) {
            $isConfirmationRow = !empty($row['__is_confirmation']);
            $parentRootId = (int)($row['__parent_root_id'] ?? 0);
            unset($row['__is_confirmation'], $row['__parent_root_id']);
            if ($isConfirmationRow) {
                $values = [];
                foreach ($confirmationColumns as $column) {
                    $values[] = ($column === 'parent_root_id') ? $parentRootId : ($row[$column] ?? null);
                }
                $confirmationInsert->execute($values);
                $savedConfirmationRows++;
            } else {
                $values = [];
                foreach ($columns as $column) $values[] = $row[$column] ?? null;
                $insert->execute($values);
                $savedMainRows++;
            }
        }
        $pdo->commit();

        $rootCount = count($parentRoots);
        $treeCount = 0;
        foreach ($parentRoots as $r) $treeCount += isset($r['trees']) && is_array($r['trees']) ? count($r['trees']) : 0;
        echo json_encode([
            'success' => true,
            'message' => 'Parent and confirmation configuration saved.',
            'meta' => ['root_count' => $rootCount, 'tree_count' => $treeCount, 'row_count' => $savedMainRows, 'confirmation_row_count' => $savedConfirmationRows]
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Programme training configuration save failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : ('Could not save configuration: ' . $e->getMessage())]);
    }
    exit;
}

// ==================== AJAX: LIVE STATE (symbols / timeframes / broker) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_live_state'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated.']); exit; }

    $liveProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
    if ($liveProgrammeId <= 0) {
        echo json_encode(['success'=>false,'message'=>'No programme selected.']); exit;
    }

    try {
        $pStmt = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
        $pStmt->execute([$liveProgrammeId, $userId]);
        $liveProgramme = $pStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$liveProgramme) {
            echo json_encode(['success'=>false,'message'=>'Programme not found.']); exit;
        }

        $liveHasBroker = (!empty($liveProgramme['broker']) && !empty($liveProgramme['server']) && !empty($liveProgramme['login']));

        $liveSymbols    = parseCleanList((string)($liveProgramme['selected_symbols']    ?? ''));
        $liveTimeframes = parseCleanList((string)($liveProgramme['selected_timeframes'] ?? ''));

        sort($liveSymbols, SORT_NATURAL | SORT_FLAG_CASE);
        $liveSymbols = array_values(array_unique($liveSymbols));

        usort($liveTimeframes, function ($a, $b) {
            $am = tfToMs($a);
            $bm = tfToMs($b);
            if ($am === $bm) return strcmp((string)$a, (string)$b);
            return ($am < $bm) ? -1 : 1;
        });
        $liveTimeframes = array_values(array_unique($liveTimeframes));

        echo json_encode([
            'success'    => true,
            'has_broker' => $liveHasBroker,
            'symbols'    => $liveSymbols,
            'timeframes' => $liveTimeframes,
            'programme_id'   => (int)$liveProgramme['id'],
            'programme_name' => (string)($liveProgramme['program_name'] ?? 'Programme'),
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage()]);
    }
    exit;
}

// ==================== AJAX: COUNT CANDLES ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['count_candles'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated.','total'=>0]); exit; }

    $reqProgrammeId = (int)($_POST['programme_id'] ?? 0);
    $symbol         = trim((string)($_POST['symbol'] ?? ''));
    $timeframe      = trim((string)($_POST['timeframe'] ?? ''));

    if ($reqProgrammeId <= 0 || $symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'programme_id, symbol and timeframe are required.','total'=>0]); exit;
    }

    $owned = ptResolveOwnedProgramme($pdo, $userId, $reqProgrammeId);
    if (!$owned) {
        echo json_encode(['success'=>false,'message'=>'Programme not found.','total'=>0]); exit;
    }

    try {
        $q = $pdo->prepare("SELECT COUNT(*) AS total FROM candle_records
                             WHERE programmeid = ? AND userid = ?
                               AND symbol = ? AND timeframe = ?");
        $q->execute([$reqProgrammeId, $userId, $symbol, $timeframe]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'symbol'=>$symbol,'timeframe'=>$timeframe,'total'=>(int)($row['total'] ?? 0)]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage(),'total'=>0]);
    }
    exit;
}

// ==================== AJAX: FETCH CANDLES (paginated) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated.','candles'=>[],'has_more'=>false]); exit; }

    $reqProgrammeId = (int)($_POST['programme_id'] ?? 0);
    $symbol         = trim((string)($_POST['symbol'] ?? ''));
    $timeframe      = trim((string)($_POST['timeframe'] ?? ''));
    $limit          = isset($_POST['limit'])  ? max(1, min(2000, (int)$_POST['limit'])) : 200;
    $before         = trim((string)($_POST['before'] ?? ''));

    if ($reqProgrammeId <= 0 || $symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'programme_id, symbol and timeframe are required.','candles'=>[],'has_more'=>false]); exit;
    }
    $owned = ptResolveOwnedProgramme($pdo, $userId, $reqProgrammeId);
    if (!$owned) {
        echo json_encode(['success'=>false,'message'=>'Programme not found.','candles'=>[],'has_more'=>false]); exit;
    }

    try {
        if ($before !== '') {
            $q = $pdo->prepare("
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
                FROM candle_records
                WHERE programmeid = ? AND userid = ?
                  AND symbol = ? AND timeframe = ? AND candle_time < ?
                ORDER BY candle_time DESC LIMIT $limit
            ");
            $q->execute([$reqProgrammeId, $userId, $symbol, $timeframe, $before]);
        } else {
            $q = $pdo->prepare("
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
                FROM candle_records
                WHERE programmeid = ? AND userid = ?
                  AND symbol = ? AND timeframe = ?
                ORDER BY candle_time DESC LIMIT $limit
            ");
            $q->execute([$reqProgrammeId, $userId, $symbol, $timeframe]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $rowsAsc = array_reverse($rows);

        $candles = [];
        foreach ($rowsAsc as $r) {
            $candles[] = [
                'id'                  => (int)$r['id'],
                'time'                => $r['candle_time'],
                'open_time'           => $r['open_time'],
                'close_time'          => $r['close_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'              => $r['volume']              !== null ? (float)$r['volume']              : null,
            ];
        }

        echo json_encode([
            'success'   => true,
            'symbol'    => $symbol,
            'timeframe' => $timeframe,
            'count'     => count($candles),
            'has_more'  => count($rows) === $limit,
            'oldest'    => count($candles) ? $candles[0]['time'] : null,
            'newest'    => count($candles) ? $candles[count($candles)-1]['time'] : null,
            'candles'   => $candles,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage(),'candles'=>[],'has_more'=>false]);
    }
    exit;
}

// ==================== AJAX: FETCH LATEST CANDLES (live tail) ====================
// Returns every candle with candle_time > after_time for the given
// (programme, symbol, timeframe). The browser supplies the newest
// candle_time it already has; the server returns only the tail.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_candles_latest'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['user_email'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated.','candles'=>[]]); exit; }

    $reqProgrammeId = (int)($_POST['programme_id'] ?? 0);
    $symbol         = trim((string)($_POST['symbol'] ?? ''));
    $timeframe      = trim((string)($_POST['timeframe'] ?? ''));
    $afterTs        = isset($_POST['after_ts']) ? (int)$_POST['after_ts'] : 0;
    $afterTime      = $afterTs > 0
                        ? gmdate('Y-m-d H:i:s', $afterTs)
                        : '';
    $limit          = isset($_POST['limit']) ? max(1, min(2000, (int)$_POST['limit'])) : 500;

    if ($reqProgrammeId <= 0 || $symbol === '' || $timeframe === '') {
        echo json_encode(['success'=>false,'message'=>'programme_id, symbol and timeframe are required.','candles'=>[]]); exit;
    }
    $owned = ptResolveOwnedProgramme($pdo, $userId, $reqProgrammeId);
    if (!$owned) {
        echo json_encode(['success'=>false,'message'=>'Programme not found.','candles'=>[]]); exit;
    }

    try {
        if ($afterTime !== '') {
            $q = $pdo->prepare("
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
                FROM candle_records
                WHERE programmeid = ? AND userid = ?
                  AND symbol = ? AND timeframe = ?
                  AND candle_time > ?
                ORDER BY candle_time ASC LIMIT $limit
            ");
            $q->execute([$reqProgrammeId, $userId, $symbol, $timeframe, $afterTime]);
        } else {
            $q = $pdo->prepare("
                SELECT id, candle_time, open_time, close_time,
                       open, high, low, close,
                       candle_center, body_center,
                       high_wick_center, low_wick_center, candle_width_center,
                       volume
                FROM candle_records
                WHERE programmeid = ? AND userid = ?
                  AND symbol = ? AND timeframe = ?
                ORDER BY candle_time ASC LIMIT $limit
            ");
            $q->execute([$reqProgrammeId, $userId, $symbol, $timeframe]);
        }
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);

        $candles = [];
        foreach ($rows as $r) {
            $candles[] = [
                'id'                  => (int)$r['id'],
                'time'                => $r['candle_time'],
                'open_time'           => $r['open_time'],
                'close_time'          => $r['close_time'],
                'open'                => (float)$r['open'],
                'high'                => (float)$r['high'],
                'low'                 => (float)$r['low'],
                'close'               => (float)$r['close'],
                'candle_center'       => $r['candle_center']       !== null ? (float)$r['candle_center']       : null,
                'body_center'         => $r['body_center']         !== null ? (float)$r['body_center']         : null,
                'high_wick_center'    => $r['high_wick_center']    !== null ? (float)$r['high_wick_center']    : null,
                'low_wick_center'     => $r['low_wick_center']     !== null ? (float)$r['low_wick_center']     : null,
                'candle_width_center' => $r['candle_width_center'] !== null ? (float)$r['candle_width_center'] : null,
                'volume'              => $r['volume']              !== null ? (float)$r['volume']              : null,
            ];
        }

        echo json_encode([
            'success'   => true,
            'symbol'    => $symbol,
            'timeframe' => $timeframe,
            'count'     => count($candles),
            'candles'   => $candles,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success'=>false,'message'=>'Query failed: '.$e->getMessage(),'candles'=>[]]);
    }
    exit;
}
$providerRiskReward = null;
try {
    if ($programmeId > 0) {
        $rrStmt = $pdo->prepare("SELECT risk_reward FROM signals_provider_interest WHERE programme_id = ? AND interest_status IN ('interested','pending','active') ORDER BY id DESC LIMIT 1");
        $rrStmt->execute([$programmeId]);
        $rrValue = $rrStmt->fetchColumn();
        if ($rrValue !== false && is_numeric($rrValue)) $providerRiskReward = (float)$rrValue;
    }
} catch (Throwable $e) { $providerRiskReward = null; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($programmeName) ?> - Training</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<style>
    :root{
        --pt-bg:      var(--bg, #ffffff);
        --pt-card:    var(--bg-card, #ffffff);
        --pt-text:    var(--text, #12211d);
        --pt-muted:   var(--text-muted, #60736d);
        --pt-accent:  var(--accent, #12a36b);
        --pt-border:  var(--border-color, #dfe9e5);
        --pt-shadow:  var(--shadow, 0 2px 12px rgba(18,33,29,.06));
        --pt-radius:  var(--radius, 16px);
    }

    @media (prefers-color-scheme: dark) {
        html:not(.force-light) {
            --pt-bg:      #0c1311;
            --pt-card:    #0c1311;
            --pt-text:    #e8f2ee;
            --pt-muted:   #93a8a1;
            --pt-border:  #22322d;
            --pt-shadow:  0 10px 30px rgba(0,0,0,.4);
        }
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        background: var(--pt-bg) !important;
        color: var(--pt-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55;
        -webkit-tap-highlight-color: transparent;
        height: 100%;
        overflow: hidden;
    }

    body.dark-mode {
        --pt-bg:     #0c1311;
        --pt-card:   #0c1311;
        --pt-text:   #e8f2ee;
        --pt-muted:  #93a8a1;
        --pt-border: #22322d;
        --pt-shadow: 0 10px 30px rgba(0,0,0,.4);
        background: var(--pt-bg) !important;
    }

    body::-webkit-scrollbar { display: none; }

    .pt-page {
        position: relative;
        width: 100%;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        background: var(--pt-bg);
    }

    .pt-controls {
        position: relative;
        z-index: 10;
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 8px 14px 14px;
        background: transparent;
        overflow: hidden;
    }

    .pt-controls-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .pt-controls-btn-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 2px;
    }
    .pt-controls-btn-row::-webkit-scrollbar { display:none; width:0; height:0; }
    .pt-controls-btn-row { scrollbar-width:none; -ms-overflow-style:none; padding-left:34px; padding-right:34px; }
    .pt-controls { position:relative; }
    .pt-control-scroll-arrow { position:absolute; top:50%; transform:translateY(-50%); z-index:30; width:34px; height:42px; display:grid; place-items:center; border:1px solid var(--pt-border); border-radius:10px; background:var(--pt-card); color:var(--pt-text); box-shadow:0 3px 12px rgba(0,0,0,.18); cursor:pointer; }
    .pt-control-scroll-arrow.left { left:4px; }
    .pt-control-scroll-arrow.right { right:4px; }
    .pt-control-scroll-arrow[hidden] { display:none !important; }
    .pt-crosshair-toggle { gap:6px; }
    .pt-crosshair-toggle[aria-pressed="true"] { background:var(--pt-accent); color:#fff; border-color:var(--pt-accent); }
    .pt-crosshair-state { font-size:11px; opacity:.8; }
    .pt-crosshair-toggle[aria-pressed="true"] .pt-crosshair-state { display:none; }
    .pt-controls > * { pointer-events: auto; }
    .pt-projection-status{display:none;align-items:center;gap:9px;align-self:flex-start;max-width:100%;padding:6px 11px;border:1px solid var(--pt-border);border-radius:9px;background:var(--pt-card);color:var(--pt-muted);font-size:.78rem;box-shadow:var(--pt-shadow)}
    .pt-projection-status.active{display:inline-flex}
    .pt-projection-status .pt-projection-spinner{width:12px;height:12px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:ptProjectionSpin .75s linear infinite;flex-shrink:0}
    @keyframes ptProjectionSpin{to{transform:rotate(360deg)}}
    .pt-projection-status.done{color:var(--pt-accent)}
    .pt-projection-status.error{color:#d9534f}
    .pt-roots-data-tabs{display:flex;gap:8px;padding:10px 18px 0;flex-shrink:0}
    .pt-roots-data-tab{border:1px solid var(--pt-border);border-radius:9px;padding:9px 16px;background:var(--pt-card);color:var(--pt-text);font:inherit;font-weight:700;cursor:pointer}
    .pt-roots-data-tab.active{background:var(--pt-accent);border-color:var(--pt-accent);color:#fff}


    .pt-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--pt-text);
        white-space: nowrap;
        letter-spacing: -0.3px;
        flex-shrink: 0;
    }
    @media (max-width: 480px) { .pt-title { font-size: 1.1rem; } }

    .pt-topbar-btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 9px 14px;
        background: var(--pt-card);
        border: 1px solid var(--pt-border);
        border-radius: 10px;
        color: var(--pt-text);
        font-family: inherit;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        box-shadow: var(--pt-shadow);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        max-width: 60vw;
        overflow: hidden;
        flex-shrink: 0;
    }
    .pt-topbar-btn:hover { border-color: var(--pt-accent); }

    .pt-topbar-symbol {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pt-topbar-caret {
        font-size: 0.7rem;
        color: var(--pt-muted);
        flex-shrink: 0;
    }

    .pt-tf-strip {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 5px;
        background: var(--pt-card);
        border: 1px solid var(--pt-border);
        border-radius: 10px;
        box-shadow: var(--pt-shadow);
        overflow-x: auto;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
        flex-shrink: 0;
    }
    .pt-tf-strip::-webkit-scrollbar { height: 0; }

    .pt-tf-btn {
        background: transparent;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--pt-muted);
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .pt-tf-btn:hover {
        color: var(--pt-text);
        background: rgba(46, 139, 87, 0.08);
    }
    .pt-tf-btn.active {
        background: var(--pt-accent);
        color: #fff;
    }

    .pt-tf-empty {
        font-size: 0.78rem;
        color: var(--pt-muted);
        padding: 6px 10px;
    }

    .pt-fs-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 38px;
        height: 38px;
        padding: 0;
        background: var(--pt-card);
        border: 1px solid var(--pt-border);
        border-radius: 10px;
        color: var(--pt-text);
        font-size: 0.95rem;
        cursor: pointer;
        box-shadow: var(--pt-shadow);
        transition: border-color 0.2s ease, background 0.2s ease;
        margin-left: auto;
        flex-shrink: 0;
    }
    .pt-fs-btn i { color: var(--pt-accent); }
    .pt-fs-btn:hover { border-color: var(--pt-accent); }
    .pt-fs-btn.is-on { background: var(--pt-accent); border-color: var(--pt-accent); }
    .pt-fs-btn.is-on i { color: #fff; }

    .pt-tf-strip { display: flex; }
    #ptTimeframeBtn { display: none; }

    @media (max-width: 600px), (orientation: portrait) {
        .pt-tf-strip    { display: none !important; }
        #ptTimeframeBtn { display: inline-flex !important; }
    }

    @media (min-width: 601px) and (orientation: landscape) {
        .pt-tf-strip    { display: flex !important; }
        #ptTimeframeBtn { display: none !important; }
    }

    .pt-chart-card {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        background: transparent;
        overflow: hidden;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        z-index: 1;
    }

    .pt-chart-canvas {
        display: block;
        position: absolute;
        inset: 0;
        cursor: crosshair;
        max-width: none;
        width: 100%;
        height: 100%;
    }
    .pt-chart-canvas.pt-grabbing { cursor: grabbing; }
    .pt-family-hover-note{position:fixed;z-index:12000;display:none;max-width:min(320px,calc(100vw - 24px));padding:10px 12px;border:1px solid var(--pt-border);border-radius:10px;background:var(--pt-card);color:var(--pt-text);box-shadow:0 8px 28px rgba(0,0,0,.22);font-size:.78rem;line-height:1.45;pointer-events:none}
    .pt-family-hover-note strong{display:block;margin-bottom:3px;font-size:.8rem}


    .pt-scale-bar {
        position: absolute;
        top: 0;
        right: 0;
        width: 18px;
        height: 100%;
        cursor: ns-resize;
        z-index: 6;
        background: transparent;
    }
    .pt-scale-bar::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 6px;
        transform: translateY(-50%);
        width: 3px;
        height: 60px;
        border-radius: 2px;
        background: rgba(128,128,128,0.45);
    }
    .pt-scale-bar.pt-active::after { background: rgba(128,128,128,0.9); }

    .pt-bg-loading {
        position: absolute;
        left: 12px;
        bottom: 12px;
        z-index: 7;
        background: rgba(0,0,0,0.55);
        color: #fff;
        font: 11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        padding: 4px 8px;
        border-radius: 6px;
        display: none;
    }
    .pt-bg-loading.pt-warn { background: rgba(180, 60, 60, 0.85); }

    .pt-jump-latest {
        position: absolute;
        top: 50%;
        right: 34px;
        transform: translateY(-50%);
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 1px solid rgba(128,128,128,0.35);
        background: rgba(40,40,40,0.55);
        color: #fff;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 8;
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    .pt-jump-latest:hover { background: rgba(60,60,60,0.85); }
    .pt-jump-latest:active { transform: translateY(-50%) scale(0.94); }
    .pt-jump-latest svg { width: 20px; height: 20px; display: block; }
    body.dark-mode .pt-jump-latest {
        background: rgba(230,230,230,0.75);
        color: #222;
        border-color: rgba(0,0,0,0.25);
    }
    body.dark-mode .pt-jump-latest:hover { background: rgba(255,255,255,0.95); }

    .pt-chart-empty,
    .pt-chart-loading {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        text-align: center;
        color: var(--pt-muted);
        background: var(--pt-bg);
        font-size: 0.9rem;
        padding: 40px 20px;
        z-index: 400;
    }
    .pt-empty-icon { font-size: 1.6rem; opacity: 0.4; }
    .pt-chart-empty p,
    .pt-chart-loading p { margin: 0; }

    .pt-loading-spinner {
        width: 28px;
        height: 28px;
        border: 1px solid var(--pt-border);
        border-top-color: var(--pt-accent);
        border-radius: 50%;
        animation: ptSpin 0.8s linear infinite;
    }
    @keyframes ptSpin { to { transform: rotate(360deg); } }

    .pt-floating-readout {
        position: absolute;
        right: 16px;
        bottom: 24px;
        z-index: 500;
        background: var(--pt-card);
        border: 1px solid var(--pt-border);
        border-radius: 10px;
        box-shadow: var(--pt-shadow);
        padding: 10px 14px;
        min-width: 190px;
        pointer-events: none;
    }
    .pt-fr-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        padding-bottom: 6px;
        }
    .pt-fr-time {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--pt-text);
        font-variant-numeric: tabular-nums;
    }
    .pt-fr-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
    }
    .pt-fr-item { display: flex; flex-direction: column; gap: 2px; }
    .pt-fr-label {
        font-size: 0.6rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--pt-muted);
        font-weight: 700;
    }
    .pt-fr-value {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--pt-text);
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 600px) {
        .pt-floating-readout {
            right: 8px;
            bottom: 14px;
            min-width: 160px;
            padding: 8px 11px;
        }
        .pt-fr-grid { grid-template-columns: repeat(3, 1fr); }
    }

    .pt-picker-modal {
        position: fixed;
        inset: 0;
        z-index: 100002;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        padding: 20px;
        box-sizing: border-box;
    }
    .pt-picker-modal.active { display: flex; }
    .pt-picker-box {
        width: 92%;
        max-width: 380px;
        max-height: 55vh;
        background: var(--pt-card);
        color: var(--pt-text);
        border: 1px solid var(--pt-border);
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font: 14px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    .pt-picker-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px 10px;
        flex-shrink: 0;
    }
    .pt-picker-title { font-size: 15px; font-weight: 700; }
    .pt-picker-close {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 1rem;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .pt-picker-close:hover { background: rgba(128,128,128,0.15); }
    .pt-picker-search { padding: 10px 14px 6px; flex-shrink: 0; }
    .pt-picker-search input {
        width: 100%;
        box-sizing: border-box;
        padding: 9px 12px;
        border-radius: 8px;
        border: 1px solid var(--pt-border);
        background: var(--pt-bg);
        color: var(--pt-text);
        font: inherit;
        outline: none;
    }
    .pt-picker-meta {
        padding: 0 16px 6px;
        font-size: 11px;
        color: var(--pt-muted);
        flex-shrink: 0;
    }
    .pt-picker-list {
        flex: 1;
        overflow-y: auto;
        padding: 4px 10px 12px;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }
    .pt-picker-list::-webkit-scrollbar { width: 6px; }
    .pt-picker-list::-webkit-scrollbar-thumb {
        background: rgba(128,128,128,0.35);
        border-radius: 999px;
    }
    .pt-picker-row {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 12px;
        border-radius: 9px;
        border: 1px solid transparent;
        background: transparent;
        color: inherit;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        margin-bottom: 4px;
    }
    .pt-picker-row:hover { background: rgba(46,204,143,0.10); }
    .pt-picker-row.is-active {
        background: rgba(46,204,143,0.16);
        border-color: rgba(46,204,143,0.45);
        color: var(--pt-accent);
    }
    .pt-picker-empty {
        padding: 20px;
        text-align: center;
        color: var(--pt-muted);
        font-size: 13px;
    }

    .pt-candles-modal {
        position: fixed;
        inset: 0;
        z-index: 100002;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }
    .pt-candles-modal.active { display: flex; }
    .pt-candles-modal-box {
        width: 92%;
        max-width: 400px;
        background: var(--pt-card);
        color: var(--pt-text);
        border: 1px solid var(--pt-border);
        border-radius: 12px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        padding: 20px 20px 16px;
        font: 14px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    .pt-candles-title { font-size: 16px; font-weight: 600; margin-bottom: 6px; }
    .pt-candles-sub { font-size: 12px; color: var(--pt-muted); margin-bottom: 12px; line-height: 1.4; }
    .pt-candles-input {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 12px;
        font-size: 15px;
        border-radius: 8px;
        border: 1px solid var(--pt-border);
        outline: none;
        background: var(--pt-bg);
        color: inherit;
    }
    .pt-candles-available {
        font-size: 12px;
        margin-top: 8px;
        color: var(--pt-accent);
        font-weight: 600;
    }
    .pt-candles-info {
        font-size: 12px;
        margin-top: 6px;
        min-height: 16px;
        color: #b00020;
        white-space: pre-line;
    }
    body.dark-mode .pt-candles-info { color: #ff8080; }
    .pt-candles-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 14px;
    }
    .pt-candles-btn {
        padding: 8px 14px;
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        border: 1px solid transparent;
    }
    .pt-candles-btn.primary { background: var(--pt-accent); color: #fff; }
    .pt-candles-btn.primary:hover { filter: brightness(0.92); }
    .pt-candles-btn.ghost {
        background: transparent;
        color: inherit;
        border-color: var(--pt-border);
    }
    .pt-candles-btn.ghost:hover { background: rgba(128,128,128,0.15); }

    .pt-candle-detail-modal {
        position: fixed;
        inset: 0;
        z-index: 100002;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        padding: 20px;
        box-sizing: border-box;
    }
    .pt-candle-detail-modal.active { display: flex; }
    .pt-candle-detail-box {
        width: 92%;
        max-width: 460px;
        max-height: 85vh;
        background: var(--pt-card);
        color: var(--pt-text);
        border: 1px solid var(--pt-border);
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font: 14px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }
    .pt-candle-detail-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 16px 18px 12px;
        flex-shrink: 0;
    }
    .pt-candle-detail-title { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
    .pt-candle-detail-sub   { font-size: 12px; color: var(--pt-muted); }
    .pt-candle-detail-close {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 1rem;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .pt-candle-detail-close:hover { background: rgba(128,128,128,0.15); }
    .pt-candle-detail-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px 18px 18px;
        -webkit-overflow-scrolling: touch;
    }
    .pt-candle-detail-row {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        font-size: 13px;
    }
    .pt-candle-detail-row:last-child { }
    .pt-candle-detail-label { color: var(--pt-muted); font-weight: 600; }
    .pt-candle-detail-value {
        color: var(--pt-text);
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        text-align: right;
        word-break: break-word;
    }

    body.pt-fs-active { overflow: hidden; }

    body.pt-fs-active .pt-controls {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        width: 100%;
        z-index: 100001;
        margin: 0;
        padding: 8px 12px;
        gap: 8px;
        flex-direction: column;
        overflow: hidden;
        background: transparent;
        box-shadow: none;
        pointer-events: none;
    }
    body.pt-fs-active .pt-controls > * { pointer-events: auto; }
    body.pt-fs-active .pt-controls-btn-row { overflow-x: auto; }
    body.pt-fs-active .pt-controls-btn-row::-webkit-scrollbar { display:none; width:0; height:0; }

    body.pt-fs-active .pt-chart-card {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        max-width: 100vw;
        height: 100vh;
        height: 100dvh;
        min-height: 0;
        border-radius: 0;
        border: none;
        box-shadow: none;
        background: transparent;
        z-index: 99998;
    }

    /* ==================== CONFIGURATION MODAL ==================== */
    .pt-cfg-modal {
        position: fixed;
        inset: 0;
        z-index: 100003;
        display: none;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
        padding: 20px;
        box-sizing: border-box;
    }
    .pt-cfg-modal.active { display: flex; }

    .pt-cfg-box {
        width: 92%;
        max-width: 1180px;
        height: 92vh;
        max-height: 92vh;
        background: var(--pt-card);
        color: var(--pt-text);
        border: 1px solid var(--pt-border);
        border-radius: 14px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        font: 13px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    }

    .pt-cfg-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 18px 12px;
        flex-shrink: 0;
        gap: 10px;
    }
    .pt-cfg-title {
        font-size: 16px;
        font-weight: 800;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .pt-cfg-title i { color: var(--pt-accent); }
    .pt-cfg-head-actions { display: flex; align-items: center; gap: 8px; }
    .pt-cfg-close {
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 1rem;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .pt-cfg-close:hover { background: rgba(128,128,128,0.15); }

    .pt-cfg-body {
        flex: 1;
        overflow-y: auto;
        padding: 14px 18px 22px;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }
    .pt-cfg-body::-webkit-scrollbar { width: 8px; }
    .pt-cfg-body::-webkit-scrollbar-thumb {
        background: rgba(128,128,128,0.35);
        border-radius: 999px;
    }

    .pt-cfg-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 18px;
        background: var(--pt-card);
        flex-shrink: 0;
    }
    .pt-cfg-footer-status {
        color: var(--pt-muted);
        font-size: 12px;
        line-height: 1.4;
        min-width: 0;
        overflow-wrap: anywhere;
    }
    .pt-cfg-footer-status.is-ok { color: var(--pt-accent); }
    .pt-cfg-footer-status.is-error { color: #e74c3c; }
    .pt-cfg-save-btn { min-width: 120px; justify-content: center; }
    .pt-cfg-save-btn:disabled { opacity: .55; cursor: not-allowed; }
    @media (max-width: 480px) {
        .pt-cfg-footer { padding: 10px 12px; align-items: stretch; flex-direction: column; }
        .pt-cfg-save-btn { width: 100%; }
    }

    .pt-cfg-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        flex-shrink: 0;
    }
    .pt-cfg-toolbar-hint {
        font-size: 11.5px;
        color: var(--pt-muted);
    }

    .pt-cfg-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 14px;
        border-radius: 9px;
        border: 1px solid var(--pt-border);
        background: var(--pt-card);
        color: var(--pt-text);
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, transform 0.1s ease;
        white-space: nowrap;
    }
    .pt-cfg-btn i { font-size: 0.85rem; }
    .pt-cfg-btn:hover { border-color: var(--pt-accent); color: var(--pt-accent); }
    .pt-cfg-btn:active { transform: scale(0.98); }
    .pt-cfg-btn.primary {
        background: var(--pt-accent);
        border-color: var(--pt-accent);
        color: #fff;
    }
    .pt-cfg-btn.primary:hover { filter: brightness(0.94); color: #fff; }
    .pt-cfg-btn.danger {
        background: transparent;
        border-color: #e74c3c;
        color: #e74c3c;
    }
    .pt-cfg-btn.danger:hover { background: #e74c3c; color: #fff; }

    .pt-cfg-empty {
        padding: 30px 20px;
        text-align: center;
        color: var(--pt-muted);
        border: 1px dashed var(--pt-border);
        border-radius: 12px;
        font-size: 13px;
    }
    .pt-cfg-empty i { font-size: 1.4rem; display: block; margin-bottom: 8px; opacity: 0.5; }

.pt-cfg-root.pt-root-validation-error { border: 1px solid #d93025 !important; box-shadow: 0 0 0 2px rgba(217,48,37,.12); }
    .pt-root-timeframes.pt-timeframe-missing { outline: 1px solid #d93025; outline-offset: 4px; border-radius: 6px; }
    .pt-cfg-root {
        border: 1px solid var(--pt-border);
        border-radius: 12px;
        background: rgba(128,128,128,0.035);
        padding: 12px;
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex-shrink: 0;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    body.dark-mode .pt-cfg-root { background: rgba(255,255,255,0.03); }
    .pt-cfg-root.is-collapsed { margin-bottom: 15px; }

    .pt-cfg-root-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
        width: 100%;
        padding: 10px;
        border-radius: 9px;
        cursor: pointer;
        min-width: 0;
        transition: background .15s ease;
    }
    .pt-cfg-root-head:hover { background: rgba(18,163,107,.07); }
    .pt-cfg-root.is-collapsed .pt-cfg-root-head { min-height: 76px; }
    .pt-cfg-root-head > .pt-cfg-root-tag { flex: 1 1 360px; min-width: 0; }
    .pt-cfg-root-toggle {
        width: 28px; height: 28px; flex: 0 0 28px;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid var(--pt-border); border-radius: 7px;
        color: var(--pt-accent); background: var(--pt-card); cursor: pointer;
    }
    .pt-cfg-root-content { flex-direction: column; gap: 10px; min-width: 0; }
    .pt-cfg-root-state { color: var(--pt-muted); font-size: 10px; font-weight: 600; }
    .pt-cfg-trade-grid { padding: 10px; border: 1px solid var(--pt-border); border-radius: 10px; }
    .pt-cfg-root-tag {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px 14px;
        font-weight: 700;
        font-size: 13px;
        color: var(--pt-accent);
        min-width: 0;
        align-content: center;
    }
    .pt-cfg-root-name-display {
        display: block;
        flex: 1 1 100%;
        min-width: 0;
        color: var(--pt-text);
        font-size: 20px;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .pt-cfg-root-name-display strong { font-weight: 850; }
    .pt-cfg-root-timeframes { color: var(--pt-muted); font-size: 12px; }
    .pt-cfg-root-name-actions { flex: 0 0 auto; }
    .pt-cfg-root-toggle { flex-shrink: 0; }
    @media (max-width: 600px) {
        .pt-cfg-root-head { gap: 8px; padding: 8px 4px; }
        .pt-cfg-root-name-display { font-size: 18px; }
        .pt-cfg-root-head > .pt-cfg-root-tag { flex-basis: calc(100% - 40px); }
    }
    .pt-cfg-root-tag .pt-cfg-chip {
        background: var(--pt-accent);
        color: #fff;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: 0.3px;
    }

    .pt-cfg-root-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }

    .pt-cfg-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .pt-cfg-field > label {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--pt-muted);
        font-weight: 800;
    }
    .pt-cfg-field > input,
    .pt-cfg-field > select {
        width: 100%;
        padding: 7px 9px;
        border: 1px solid var(--pt-border);
        border-radius: 8px;
        background: var(--pt-bg);
        color: var(--pt-text);
        font: inherit;
        font-size: 12.5px;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .pt-cfg-field > input:focus,
    .pt-cfg-field > select:focus {
        border-color: var(--pt-accent);
        box-shadow: 0 0 0 3px rgba(18,163,107,0.14);
    }
    .pt-cfg-field > input:disabled,
    .pt-cfg-field > select:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        background: rgba(128,128,128,0.08);
    }

    .pt-cfg-tree {
        border: 1px solid var(--pt-border);
        border-radius: 10px;
        background: var(--pt-card);
        padding: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .pt-cfg-tree-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        flex-wrap: wrap;
    }
    .pt-cfg-tree-tag {
        font-size: 12px;
        font-weight: 800;
        color: var(--pt-muted);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .pt-cfg-tree-tag .pt-cfg-chip.tree { background: var(--pt-muted); }

    .pt-cfg-row {
        border: 1px dashed var(--pt-border);
        border-radius: 9px;
        padding: 9px 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .pt-cfg-row-tag {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--pt-muted);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .pt-cfg-row-tag.main { color: var(--pt-accent); }

    .pt-cfg-row-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 8px;
    }

    .pt-cfg-chip {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        letter-spacing: 0.3px;
    }

    .pt-cfg-draws {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 4px;
    }
    .pt-cfg-draw {
        border: 1px dashed var(--pt-border);
        border-radius: 9px;
        padding: 8px 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        position: relative;
    }
    .pt-cfg-draw-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .pt-cfg-draw-tag {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--pt-muted);
    }
    .pt-cfg-draw-remove {
        border: 0;
        background: transparent;
        color: #e74c3c;
        cursor: pointer;
        font-size: 0.85rem;
        padding: 2px 6px;
        border-radius: 6px;
    }
    .pt-cfg-draw-remove:hover { background: rgba(231,76,60,0.12); }

    .pt-cfg-tree-foot {
        display: flex;
        justify-content: flex-start;
    }
    .pt-cfg-btn:disabled {
        opacity: .45;
        cursor: not-allowed;
        filter: grayscale(.25);
        box-shadow: none;
    }
    .pt-cfg-tree-cancel {
        margin-left: auto;
        flex-shrink: 0;
    }

    @media (max-width: 640px) {
        .pt-cfg-modal { padding: 10px; }
        .pt-cfg-box { width: 100%; height: 94vh; max-height: 94vh; border-radius: 12px; }
        .pt-cfg-body { padding: 12px 12px 18px; }
        .pt-cfg-head { padding: 12px 14px 10px; }
        .pt-cfg-root-grid { grid-template-columns: 1fr 1fr; }
        .pt-cfg-row-grid  { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 400px) {
        .pt-cfg-root-grid { grid-template-columns: 1fr; }
        .pt-cfg-row-grid  { grid-template-columns: 1fr; }
    }

    .pt-root-timeframes { min-width: 220px; max-width: 100%; }
    .pt-root-timeframe-list { display:flex; flex-wrap:wrap; gap:7px; margin-top:7px; }
    .pt-root-timeframe-option { display:inline-flex; align-items:center; gap:6px; padding:5px 8px; border:1px solid var(--pt-border); border-radius:8px; cursor:pointer; font-size:12px; }
    .pt-root-timeframe-option input { accent-color:var(--pt-accent); }


    /* Destructive root-deletion confirmation */
    .pt-root-delete-modal {
        position: fixed; inset: 0; z-index: 100010; display: none;
        align-items: center; justify-content: center; padding: 18px;
        background: rgba(0,0,0,.66); backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    .pt-root-delete-modal.active { display: flex; }
    .pt-root-delete-box {
        width: min(100%, 430px); padding: 24px; border-radius: 16px;
        background: var(--pt-card); color: var(--pt-text);
        border: 1px solid var(--pt-border); box-shadow: 0 22px 70px rgba(0,0,0,.38);
        animation: ptRootDeleteIn .16s ease-out;
    }
    @keyframes ptRootDeleteIn { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .pt-root-delete-icon { width: 46px; height: 46px; display:flex; align-items:center; justify-content:center;
        border-radius: 13px; background: rgba(220,53,69,.12); color: #dc3545; font-size: 20px; margin-bottom: 14px; }
    .pt-root-delete-title { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
    .pt-root-delete-message { color: var(--pt-muted); font-size: 13px; line-height: 1.65; }
    .pt-root-delete-actions { display:flex; justify-content:flex-end; flex-wrap:wrap; gap:9px; margin-top:22px; }
    .pt-root-delete-actions button { border-radius:9px; padding:10px 14px; font:inherit; font-weight:750; cursor:pointer; }
    .pt-root-delete-cancel { background:var(--pt-card); color:var(--pt-text); border:1px solid var(--pt-border); }
    .pt-root-delete-confirm { background:#dc3545; color:#fff; border:1px solid #dc3545; }
    .pt-root-delete-confirm:hover { background:#bb2d3b; }

    /* Dynamic diagnostics console */
    .pt-dynamic-console-modal{position:fixed;inset:0;z-index:100500;display:none;align-items:center;justify-content:center;padding:14px;background:rgba(0,0,0,.72);backdrop-filter:blur(5px)}
    .pt-dynamic-console-modal.active{display:flex}
    .pt-dynamic-console-box{width:min(1100px,98vw);height:min(88vh,900px);display:flex;flex-direction:column;overflow:hidden;background:var(--pt-card);color:var(--pt-text);border:1px solid var(--pt-border);border-radius:16px;box-shadow:0 25px 90px rgba(0,0,0,.45)}
    .pt-dynamic-console-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;flex-shrink:0}
    .pt-dynamic-console-title{font-size:1rem;font-weight:800}
    .pt-dynamic-console-subtitle{font-size:.78rem;color:var(--pt-muted);margin-top:2px}
    .pt-dynamic-console-actions{display:flex;gap:8px;flex-wrap:wrap}
    .pt-dynamic-console-action{border:1px solid var(--pt-border);background:var(--pt-bg);color:var(--pt-text);padding:8px 12px;border-radius:9px;font:inherit;font-size:.8rem;font-weight:700;cursor:pointer}
    .pt-dynamic-console-action.primary{background:var(--pt-accent);border-color:var(--pt-accent);color:#fff}
    .pt-dynamic-console-status{padding:10px 18px;background:rgba(18,163,107,.08);font-size:.82rem;white-space:pre-wrap;flex-shrink:0}
    .pt-dynamic-console-json{flex:1;width:100%;resize:none;border:0;outline:0;padding:16px 18px;background:#0c1117;color:#cde8d7;font:12px/1.55 Consolas,Monaco,'Courier New',monospace;tab-size:2;white-space:pre;overflow:auto}
    @media(max-width:600px){.pt-dynamic-console-modal{padding:6px}.pt-dynamic-console-box{width:100%;height:94vh;border-radius:12px}.pt-dynamic-console-head{align-items:flex-start;padding:12px}.pt-dynamic-console-actions{gap:5px}.pt-dynamic-console-action{padding:7px 9px}.pt-dynamic-console-json{font-size:11px;padding:12px}}


    .pt-rd-dashboard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
    .pt-rd-stat{display:flex;align-items:center;gap:10px;min-width:0;padding:14px;border:1px solid var(--pt-border);border-radius:13px;background:var(--pt-card, var(--pt-bg));box-shadow:0 5px 18px rgba(0,0,0,.045)}
    .pt-rd-stat-icon{width:38px;height:38px;display:grid;place-items:center;border-radius:11px;background:rgba(128,128,128,.12);font-size:15px;flex-shrink:0}
    .pt-rd-stat.loss .pt-rd-stat-icon,.pt-rd-badge.loss{color:#e05260;background:rgba(224,82,96,.12)}.pt-rd-stat.profit .pt-rd-stat-icon,.pt-rd-badge.profit{color:#16a36a;background:rgba(22,163,106,.12)}.pt-rd-stat.pending .pt-rd-stat-icon,.pt-rd-badge.pending{color:#bd8516;background:rgba(189,133,22,.13)}.pt-rd-stat.active .pt-rd-stat-icon,.pt-rd-badge.active{color:#4388dc;background:rgba(67,136,220,.13)}
    .pt-rd-stat-label{font-size:.72rem;color:var(--pt-muted);font-weight:650}.pt-rd-stat-value{font-size:1.55rem;line-height:1.2;font-weight:800;margin-top:3px}
    .pt-dd-cover[hidden],.pt-dd-details[hidden]{display:none!important}.pt-rd-drawdown-card{align-items:stretch;min-height:0;overflow:hidden}.pt-dd-cover,.pt-dd-details{display:flex;align-items:flex-start;gap:10px;width:100%;min-width:0}.pt-dd-content{min-width:0;flex:1}.pt-dd-details{display:block;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding-right:4px}.pt-dd-detail-heading{font-size:.78rem;font-weight:800;margin-bottom:10px}.pt-dd-sequence{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 12px}.pt-dd-chip{display:inline-flex;padding:6px 8px;border:0;border-radius:7px;font-size:.78rem;font-weight:800;background:rgba(128,128,128,.1);cursor:pointer;font-family:inherit}.pt-dd-chip:focus-visible,.pt-dd-locate:focus-visible{outline:2px solid var(--pt-accent);outline-offset:2px}.pt-dd-chip.loss{color:#e05260;background:rgba(224,82,96,.12)}.pt-dd-chip.profit{color:#16a36a;background:rgba(22,163,106,.12)}.pt-dd-totals{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:8px 0 12px}.pt-dd-totals>div{padding:8px;border-radius:8px;background:rgba(128,128,128,.08)}.pt-dd-totals span{display:block;color:var(--pt-muted);font-size:.68rem}.pt-dd-totals strong{font-size:.95rem}.pt-dd-button{display:inline-flex;align-items:center;justify-content:center;margin-top:9px;padding:7px 10px;border:0;border-radius:8px;background:var(--pt-accent);color:#fff;font:inherit;font-size:.75rem;font-weight:800;cursor:pointer}.pt-dd-button.secondary{background:rgba(128,128,128,.16);color:var(--pt-text)}.pt-dd-empty{font-size:.75rem;color:var(--pt-muted);padding:8px 0}

    .pt-rd-root{border:1px solid var(--pt-border);border-radius:14px;margin-bottom:12px;overflow:hidden;background:var(--pt-card,var(--pt-bg))}.pt-rd-root>summary{list-style:none;display:flex;align-items:center;gap:12px;padding:15px 16px;cursor:pointer;background:rgba(128,128,128,.045)}.pt-rd-root>summary::-webkit-details-marker{display:none}.pt-rd-root-title{font-weight:800;display:flex;align-items:center;gap:9px;flex:1}.pt-rd-root-summary{font-size:.78rem;color:var(--pt-muted);display:flex;gap:10px;align-items:center}.pt-rd-root-summary .is-profit{color:#16a36a}.pt-rd-root-summary .is-loss{color:#e05260}.pt-rd-chevron{transition:transform .18s ease;color:var(--pt-muted)}.pt-rd-root[open] .pt-rd-chevron{transform:rotate(180deg)}
    .pt-rd-root-stats{display:flex;flex-wrap:wrap;gap:8px;padding:12px 16px;}.pt-rd-root-stats span{font-size:.78rem;color:var(--pt-muted);padding:6px 9px;background:rgba(128,128,128,.08);border-radius:8px}.pt-rd-root-stats b{color:var(--pt-text);margin-left:4px}
    .pt-rd-trade{margin:12px;padding:14px;border:1px solid var(--pt-border);border-radius:12px;background:var(--pt-bg)}.pt-rd-trade-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:13px}.pt-rd-trade-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px}.pt-dd-locate{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border:1px solid var(--pt-border);border-radius:8px;background:rgba(128,128,128,.08);color:var(--pt-text);font:inherit;font-size:.75rem;font-weight:800;cursor:pointer}.pt-dd-locate:hover{border-color:var(--pt-accent);color:var(--pt-accent)}.pt-rd-family{font-size:.72rem;letter-spacing:.08em;font-weight:850;color:var(--pt-muted)}.pt-rd-order{margin-left:9px;font-size:.72rem;font-weight:750;padding:4px 7px;border-radius:6px;background:rgba(128,128,128,.12)}.pt-rd-badge{font-size:.72rem;font-weight:800;padding:6px 9px;border-radius:8px;text-transform:capitalize}.pt-rd-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.pt-rd-metrics>div{min-width:0;padding:11px;border-radius:9px;background:rgba(128,128,128,.07)}.pt-rd-metrics span,.pt-rd-details span{display:block;font-size:.7rem;color:var(--pt-muted);margin-bottom:5px}.pt-rd-metrics strong{display:block;font-size:1rem;overflow-wrap:anywhere}.pt-rd-metrics small{display:block;margin-top:5px;font-size:.68rem;color:var(--pt-muted);overflow-wrap:anywhere}.pt-rd-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 14px;margin-top:12px;padding-top:12px;}.pt-rd-details b{font-size:.8rem;font-weight:700;overflow-wrap:anywhere}.pt-rd-empty{padding:16px;color:var(--pt-muted);font-size:.85rem}
    @media(max-width:700px){.pt-rd-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.pt-rd-root>summary{flex-wrap:wrap}.pt-rd-root-summary{order:3;width:100%;flex-wrap:wrap}.pt-rd-metrics{grid-template-columns:1fr}.pt-rd-details{grid-template-columns:1fr}.pt-rd-trade{margin:9px;padding:11px}}

    .pt-provision-toggle{margin:0 0 10px}.pt-provision-panel{max-height:420px;overflow:auto;overscroll-behavior:contain;border:1px solid var(--pt-border);border-radius:12px;padding:10px;margin-bottom:14px}
    .pt-provision-panel[hidden],#pt-summary-global[hidden]{display:none!important}.pt-provision-count{font-size:.78rem;color:var(--pt-muted);font-weight:800;margin:4px 2px 10px}
    .pt-provision-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 16px;align-items:start;padding:4px 3px 18px}
    .pt-provision-grid>.pt-provision-period{margin:0 0 10px;min-width:0;max-height:320px;min-height:0;overflow:auto;overscroll-behavior:contain;padding:14px;border:1px solid var(--pt-border);border-radius:12px;background:var(--pt-card);box-shadow:0 4px 14px rgba(0,0,0,.07)}
    .pt-provision-grid>.pt-provision-period:before{content:'MONTH / PERIOD';display:block;font-size:.62rem;letter-spacing:.12em;font-weight:850;color:var(--pt-accent);margin:-2px 0 7px}
    .pt-provision-month-grid>.pt-provision-period:before{content:'MONTH';}
    .pt-provision-month-grid>.pt-provision-period{border:1px solid var(--pt-border);background:var(--pt-card);box-shadow:0 5px 16px rgba(0,0,0,.10);padding:16px;margin:0;min-width:0;border-radius:12px}
    .pt-provision-period-head{padding-bottom:8px;}
    .pt-provision-period-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:8px}.pt-provision-period-head strong{font-size:.86rem}.pt-provision-period-head span,.pt-provision-caption{font-size:.68rem;color:var(--pt-muted)}.pt-provision-number{font-size:1rem;font-weight:850}.pt-provision-revenue{font-size:.76rem;margin:8px 0;font-weight:750}.pt-provision-revenue .is-profit{color:#16a36a}.pt-provision-revenue .is-loss{color:#e05260}
    .pt-provision-nav{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:16px;padding-bottom:10px;}.pt-provision-nav strong{font-size:.9rem}.pt-provision-days{display:flex;flex-wrap:wrap;gap:5px}.pt-provision-days .pt-dd-button{margin-top:5px}
    @media(max-width:700px){.pt-provision-grid{grid-template-columns:1fr;gap:18px;padding:4px 2px 20px}.pt-provision-panel{max-height:420px}.pt-provision-grid>.pt-provision-period{max-height:300px;margin-bottom:8px;padding:14px}}

/* Trades summary/provision layout fixes */
.pt-rd-stat,
.pt-rd-stat > div,
.pt-dd-content,
.pt-rd-stat-label,
.pt-rd-stat-value {
    min-width: 0;
    max-width: 100%;
}
.pt-rd-stat-label,
.pt-rd-stat-value,
.pt-rd-stat > div,
.pt-dd-totals span,
.pt-dd-totals strong,
.pt-provision-period-head strong,
.pt-provision-period-head span,
.pt-provision-revenue {
    overflow-wrap: anywhere;
    word-break: normal;
    white-space: normal;
}
.pt-rd-stat-value {
    font-size: clamp(1rem, 4vw, 1.55rem);
    line-height: 1.2;
}
.pt-provision-panel {
    max-height: none !important;
    height: auto;
    overflow: visible !important;
    overscroll-behavior: auto;
    min-width: 0;
}
.pt-provision-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    align-items: start;
}
.pt-provision-period {
    max-height: none !important;
    height: auto;
    overflow: visible !important;
    min-width: 0;
    overflow-wrap: anywhere;
}
.pt-provision-period > * { min-width: 0; max-width: 100%; }
.pt-provision-period .pt-dd-sequence { min-width: 0; }
.pt-provision-period .pt-dd-chip { max-width: 100%; overflow-wrap: anywhere; }
@media (max-width: 420px) {
    .pt-rd-stat { gap: 7px; padding: 10px; }
    .pt-rd-stat-icon { width: 30px; height: 30px; flex: 0 0 30px; }
    .pt-rd-stat-value { font-size: clamp(.95rem, 4.2vw, 1.2rem); }
    .pt-provision-grid { gap: 18px; padding-bottom: 20px; }
    .pt-provision-grid>.pt-provision-period { padding: 14px; margin-bottom: 8px; }
    .pt-provision-period-head { flex-direction: column; gap: 2px; }
}
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?>">

<div class="pt-page">
    <div class="pt-controls">
        <div class="pt-controls-title-row">
            <span class="pt-title">Programme Training</span>
        </div>

        <button type="button" class="pt-control-scroll-arrow left" id="ptControlsScrollLeft" aria-label="Scroll controls left" title="Scroll controls left" hidden><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="pt-control-scroll-arrow right" id="ptControlsScrollRight" aria-label="Scroll controls right" title="Scroll controls right" hidden><i class="fa-solid fa-chevron-right"></i></button>
        <div class="pt-controls-btn-row" id="ptControlsButtonRow">
            <button type="button" class="pt-topbar-btn" id="ptSymbolBtn" onclick="ptOpenSymbolModal()">
                <span class="pt-topbar-symbol" id="ptSymbolLabel">Symbol</span>
                <i class="fa-solid fa-chevron-down pt-topbar-caret"></i>
            </button>

            <button type="button" class="pt-topbar-btn" id="ptTimeframeBtn" onclick="ptOpenTimeframeModal()">
                <span class="pt-topbar-symbol" id="ptTimeframeLabel">Timeframe</span>
                <i class="fa-solid fa-chevron-down pt-topbar-caret"></i>
            </button>
            <button type="button" class="pt-topbar-btn pt-crosshair-toggle" id="ptMobileCrosshairToggle"
                    title="Toggle mobile crosshair mode" aria-pressed="false" onclick="ptToggleMobileCrosshair()">
                <i class="fa-solid fa-crosshairs" aria-hidden="true"></i><span></i>
            </button>

            <div class="pt-tf-strip" id="ptTfStrip"></div>

            <button type="button" class="pt-topbar-btn" id="ptConfigBtn" onclick="ptOpenConfigModal()">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                <span>Configuration</span>
            </button>

            <button type="button" class="pt-topbar-btn" id="ptRootsDataBtn" onclick="ptOpenRootsData()" title="View trade records for matched root families">
                <span>Roots Data</span>
            </button>

            <button type="button" class="pt-fs-btn" id="ptFullscreenBtn" title="Toggle fullscreen" onclick="ptToggleFullscreen()">
                <i class="fa-solid fa-expand"></i>
            </button>
        </div>
        <div class="pt-projection-status" id="ptProjectionStatus" role="status" aria-live="polite">
            <span class="pt-projection-spinner" id="ptProjectionSpinner"></span>
            <span id="ptProjectionStatusText">Preparing higher-timeframe projections…</span>
        </div>
    </div>

    <div class="pt-chart-card" id="ptChartCard">
        <canvas id="ptChartCanvas" class="pt-chart-canvas"></canvas>

        <div class="pt-scale-bar" id="ptScaleBar" title="Drag to change candle height"></div>

        <button type="button" class="pt-jump-latest" id="ptJumpLatest" title="Jump to latest" onclick="ptJumpToLatest()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                 stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="12" x2="18" y2="12"></line>
                <polyline points="12 6 18 12 12 18"></polyline>
            </svg>
        </button>

        <div id="ptChartEmpty" class="pt-chart-empty" style="display:none;">
            <span class="pt-empty-icon">—</span>
            <p>No candle records found for this symbol timeframe.</p>
        </div>

        <div id="ptChartLoading" class="pt-chart-loading" style="display:none;">
            <div class="pt-loading-spinner"></div>
            <p>Loading candles…</p>
        </div>

        <div id="ptBgLoading" class="pt-bg-loading">Loading older candles…</div>

        <div class="pt-floating-readout" id="ptReadout">
            <div class="pt-fr-row">
                <span class="pt-fr-time" id="ptReadoutTime">—</span>
            </div>
            <div class="pt-fr-grid">
                <div class="pt-fr-item"><span class="pt-fr-label">O</span><span class="pt-fr-value" id="ptReadoutOpen">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">H</span><span class="pt-fr-value" id="ptReadoutHigh">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">L</span><span class="pt-fr-value" id="ptReadoutLow">—</span></div>
                <div class="pt-fr-item"><span class="pt-fr-label">C</span><span class="pt-fr-value" id="ptReadoutClose">—</span></div>
            </div>
        </div>
    </div>

</div>

<div id="ptSymbolModal" class="pt-picker-modal">
    <div class="pt-picker-box">
        <div class="pt-picker-head">
            <span class="pt-picker-title">Select Symbol</span>
            <button type="button" class="pt-picker-close" onclick="ptCloseSymbolModal()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="pt-picker-search">
            <input type="text" id="ptSymbolSearch" placeholder="Search symbol..." autocomplete="off"
                   oninput="ptFilterSymbolModal(this.value)">
        </div>
        <div class="pt-picker-meta"><span id="ptSymbolModalCount">0 symbols</span></div>
        <div class="pt-picker-list" id="ptSymbolModalList"></div>
    </div>
</div>

<div id="ptTimeframeModal" class="pt-picker-modal">
    <div class="pt-picker-box">
        <div class="pt-picker-head">
            <span class="pt-picker-title">Select Timeframe</span>
            <button type="button" class="pt-picker-close" onclick="ptCloseTimeframeModal()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="pt-picker-search">
            <input type="text" id="ptTimeframeSearch" placeholder="Search timeframe..." autocomplete="off"
                   oninput="ptFilterTimeframeModal(this.value)">
        </div>
        <div class="pt-picker-meta"><span id="ptTimeframeModalCount">0 timeframes</span></div>
        <div class="pt-picker-list" id="ptTimeframeModalList"></div>
    </div>
</div>

<div id="ptCandlesModal" class="pt-candles-modal">
    <div class="pt-candles-modal-box">
        <div class="pt-candles-title">Explore Candles</div>
        <div class="pt-candles-sub" id="ptCandlesSub">Enter the number of candles to display.</div>
        <input type="number" id="ptCandlesInput" class="pt-candles-input"
               min="1" step="1" placeholder="e.g. 1500" autocomplete="off">
        <div class="pt-candles-available" id="ptCandlesAvailable">Available Candle: —</div>
        <div class="pt-candles-info" id="ptCandlesInfo"></div>
        <div class="pt-candles-actions">
            <button type="button" class="pt-candles-btn ghost" id="ptCandlesCancel">Cancel</button>
            <button type="button" class="pt-candles-btn primary" id="ptCandlesConfirm">Load</button>
        </div>
    </div>
</div>

<div id="ptCandleDetailModal" class="pt-candle-detail-modal">
    <div class="pt-candle-detail-box">
        <div class="pt-candle-detail-head">
            <div>
                <h3 class="pt-candle-detail-title" id="ptCandleDetailTitle">Candle Details</h3>
                <p class="pt-candle-detail-sub" id="ptCandleDetailSub">—</p>
            </div>
            <button type="button" class="pt-candle-detail-close" id="ptCandleDetailClose" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="pt-candle-detail-body" id="ptCandleDetailBody"></div>
    </div>
</div>

<!-- ==================== CONFIGURATION MODAL ==================== -->
<div id="ptConfigModal" class="pt-cfg-modal">
    <div class="pt-cfg-box">
        <div class="pt-cfg-head">
            <div class="pt-cfg-title"><i class="fa-solid fa-chart-candlestick"></i> Configuration</div>
            <div class="pt-cfg-head-actions">
                <button type="button" class="pt-cfg-close" onclick="ptCloseConfigModal()" title="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="pt-cfg-body" id="ptCfgBody">
            <div class="pt-cfg-toolbar">
                <button type="button" class="pt-cfg-btn primary" onclick="ptCfgAddRoot()">
                    <i class="fa-solid fa-plus"></i> New Root
                </button>
                <span class="pt-cfg-toolbar-hint" id="ptCfgHint">No roots configured yet.</span>
            </div>

            <div id="ptCfgRoots"></div>

            <div class="pt-cfg-empty" id="ptCfgEmpty">
                <i class="fa-solid fa-sitemap"></i>
                Click <strong>New Root</strong> to start building your configuration tree.
            </div>
        </div>
        <div class="pt-cfg-footer">
            <div class="pt-cfg-footer-status" id="ptCfgFootStatus" role="status" aria-live="polite">
                Add a root, then save your configuration.
            </div>
            <button type="button" class="pt-cfg-btn primary pt-cfg-save-btn" id="ptCfgSaveBtn" onclick="ptCfgSave()" disabled>
                <i class="fa-solid fa-floppy-disk"></i> Save Configuration
            </button>
        </div>
    </div>
</div>

<!-- Custom confirmation modal: deleting a root requires an explicit second action. -->
<div id="ptRootDeleteModal" class="pt-root-delete-modal" role="dialog" aria-modal="true" aria-labelledby="ptRootDeleteTitle" aria-describedby="ptRootDeleteMessage">
    <div class="pt-root-delete-box">
        <div class="pt-root-delete-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h3 class="pt-root-delete-title" id="ptRootDeleteTitle">Delete root configuration?</h3>
        <p class="pt-root-delete-message" id="ptRootDeleteMessage">Are you sure you want to delete this root configuration? This action will remove the root and its configured trees and drawings from the current configuration. You can cancel to keep it.</p>
        <div class="pt-root-delete-actions">
            <button type="button" class="pt-root-delete-cancel" id="ptRootDeleteCancel">Cancel</button>
            <button type="button" class="pt-root-delete-confirm" id="ptRootDeleteConfirm"><i class="fa-solid fa-trash"></i> Yes, delete root</button>
        </div>
    </div>
</div>


<div class="pt-dynamic-console-modal" id="ptRootsDataModal" role="dialog" aria-modal="true" aria-labelledby="ptRootsDataTitle">
    <div class="pt-dynamic-console-box">
        <div class="pt-dynamic-console-head">
            <div><div class="pt-dynamic-console-title" id="ptRootsDataTitle"><i class="fa-solid fa-list-check"></i> Roots Data — Trade Families</div><div class="pt-dynamic-console-subtitle">Matched root families and their entry/exit trigger records</div></div>
            <div class="pt-dynamic-console-actions"><button type="button" class="pt-dynamic-console-action" onclick="ptRefreshRootsData()"><i class="fa-solid fa-rotate"></i> Refresh</button><button type="button" class="pt-dynamic-console-action" onclick="ptCloseRootsData()"><i class="fa-solid fa-xmark"></i> Close</button></div>
        </div>
        <div class="pt-dynamic-console-status" id="ptRootsDataStatus">Only native data for roots configured on the currently viewed timeframe is shown here. Higher-timeframe projection data is not included.</div>
        <div id="ptRootsDataBody" style="flex:1;overflow:auto;padding:16px 18px"></div>
    </div>
</div>

<script>
    // build:<?= time() ?>
    // ==================== SERVER DATA ====================
    var PT_ALL_SYMBOLS       = <?= json_encode(array_values($selectedSymbols)) ?>;
    var PT_ALL_TIMEFRAMES    = <?= json_encode(array_values($selectedTimeframes)) ?>;
    var PT_CURRENT_SYMBOL    = '';
    var PT_CURRENT_TIMEFRAME = '';

    var PT_PROGRAMME_ID      = <?= (int)$programmeId ?>;
    var PT_PROGRAMME_NAME    = <?= json_encode($programmeName) ?>;
    var PT_PROVIDER_RISK_REWARD = <?= $providerRiskReward === null ? 'null' : json_encode($providerRiskReward) ?>;
    var PT_HAS_BROKER        = <?= $hasBroker ? 'true' : 'false' ?>;
    var PT_USER_ID           = <?= (int)$userId ?>;
    var PT_DARK_MODE          = <?= $darkMode ? 'true' : 'false' ?>;
    // Shared by chart rendering and the configuration modal.
    function ptResolveConfiguredColor(value, fallback) {
        var color = String(value || fallback || 'purple').toLowerCase();
        if (color === 'custom') {
            var dark = document.body
                ? document.body.classList.contains('dark-mode')
                : !!PT_DARK_MODE;
            return dark ? '#ffffff' : '#000000';
        }
        return color;
    }

    var PT_DEFAULT_AMOUNT    = 2000;

    var PT_IS_FULLSCREEN     = false;

    // ==================== LIVE STATE (config poll) ====================
    var PT_LIVE_INTERVAL     = null;
    var PT_LIVE_RUNNING      = false;
    var PT_LIVE_FAIL_COUNT   = 0;
    var PT_LIVE_BASE_MS      = 1500;
    var PT_LIVE_MAX_MS       = 15000;
    var PT_LAST_STATE_SIG    = null;

    // ==================== LIVE CANDLE REFRESH (tail poll) ====================
    var PT_REFRESH_MS        = 2000;
    var PT_REFRESH_INTERVAL  = null;
    var PT_REFRESH_RUNNING   = false;
    var PT_REFRESH_INFLIGHT  = false;

    // ==================== CHART STATE ====================
    var PT_CANDLES     = [];
    // Higher-timeframe source candles are cached separately; they never replace the visible chart series.
    var PT_BG_TF_DATA = Object.create(null);
    var PT_BG_TF_PROMISES = Object.create(null);
    var PT_PROJECTION_MAP = null;
    var PT_PROJECTION_RENDER_PASS = false;
    var PT_PROJECTION_REQUEST_TOKEN = 0;
    var PT_CANVAS      = null;
    var PT_CTX         = null;
    var PT_HOVER_INDEX = -1;
    var PT_CURSOR_Y    = null;
    var PT_MOBILE_CROSSHAIR_MODE = false;

    var PT_LOAD_TOKEN         = 0;
    var PT_IS_LOADING_OLDER   = false;
    var PT_HAS_MORE_OLDER     = true;
    var PT_INITIAL_LOADED     = false;
    var PT_REQUESTED_AMOUNT   = PT_DEFAULT_AMOUNT;
    var PT_CONSECUTIVE_ERRORS = 0;
    var PT_MAX_CONSECUTIVE_ERRORS = 4;

    var PT_VIEW = {
        candleWidth:    9,
        candleSpacing:  5,
        offsetX:        0,
        offsetY:        0,
        priceScale:     1.0,
        minCandleWidth: 0.3,
        maxCandleWidth: 40
    };

    // Temporary trade locator overlay. It is drawn over the chart for four seconds.
    var PT_LOCATE_ARROW = null;
    var PT_LOCATE_ANIMATION_FRAME = null;

    var PT_PAGE_SIZE      = 500;
    var PT_PADDING_LEFT   = 64;
    var PT_PADDING_RIGHT  = 24;
    var PT_PADDING_TOP    = 64;
    var PT_PADDING_BOTTOM = 34;
    var PT_VIRTUAL_PAD    = 2000;

    var PT_FOLLOW_LATEST  = true;

    // Fast interaction path: while the user is actively panning/zooming, draw only
    // candles and crosshairs. Heavy matching/projection overlays are redrawn once after
    // the gesture settles, instead of blocking every mousemove/wheel frame.
    var PT_FAST_INTERACTION = false;
    var PT_FAST_INTERACTION_TIMER = null;
    function ptBeginFastInteraction() {
        PT_FAST_INTERACTION = true;
        if (PT_FAST_INTERACTION_TIMER) clearTimeout(PT_FAST_INTERACTION_TIMER);
        PT_FAST_INTERACTION_TIMER = setTimeout(function () {
            PT_FAST_INTERACTION = false;
            PT_FAST_INTERACTION_TIMER = null;
            ptScheduleDraw();
        }, 140);
    }

    var PT_DRAW_SCHEDULED = false;
    function ptScheduleDraw() {
        if (PT_DRAW_SCHEDULED) return;
        PT_DRAW_SCHEDULED = true;
        requestAnimationFrame(function () {
            PT_DRAW_SCHEDULED = false;
            ptDrawChart();
        });
    }

    // ==================== HELPERS ====================
    function ptEscapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }
    function ptFormatPrice(v) {
        if (v == null || isNaN(v)) return '—';
        var n = Number(v); var abs = Math.abs(n);
        var decimals = abs >= 1000 ? 2 : (abs >= 1 ? 4 : 6);
        return n.toFixed(decimals);
    }
    function ptFormatTime(ts) { if (!ts) return '—'; return String(ts).replace('T', ' ').slice(0, 16); }
    function ptStep()     { return PT_VIEW.candleWidth + PT_VIEW.candleSpacing; }
    function ptContentW() { return PT_CANDLES.length * ptStep(); }

    function ptLabelStyles() {
        var isDark = document.body.classList.contains('dark-mode');
        return { bg: isDark ? '#2a2a2a' : '#1e1e1e', bgText: '#f2f2f2', border: isDark ? '#444' : '#000' };
    }
    function ptRoundRect(ctx, x, y, w, h, r) {
        if (w < 2 * r) r = w / 2; if (h < 2 * r) h = h / 2;
        ctx.beginPath(); ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y,     x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x,     y + h, r);
        ctx.arcTo(x,     y + h, x,     y,     r);
        ctx.arcTo(x,     y,     x + w, y,     r);
        ctx.closePath();
    }

    // ==================== SHELL MESSAGING ====================
    function ptPostShell(msg) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage(msg, '*');
            }
        } catch (e) {}
    }

    // ==================== FULLSCREEN ====================
    function ptToggleFullscreen() {
        PT_IS_FULLSCREEN = !PT_IS_FULLSCREEN;

        document.body.classList.toggle('pt-fs-active', PT_IS_FULLSCREEN);

        var btn = document.getElementById('ptFullscreenBtn');
        if (btn) {
            btn.classList.toggle('is-on', PT_IS_FULLSCREEN);
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = PT_IS_FULLSCREEN
                    ? 'fa-solid fa-compress'
                    : 'fa-solid fa-expand';
            }
        }

        ptPostShell({
            type: 'bodyClass',
            add:    PT_IS_FULLSCREEN ? ['shell-no-nav'] : [],
            remove: PT_IS_FULLSCREEN ? [] : ['shell-no-nav']
        });

        setTimeout(function () {
            if (PT_CANDLES.length) {
                ptFitViewportToCandles();
                ptScheduleDraw();
                ptUpdateJumpLatestVisibility();
            }
        }, 80);
        setTimeout(function () {
            if (PT_CANDLES.length) {
                ptFitViewportToCandles();
                ptScheduleDraw();
                ptUpdateJumpLatestVisibility();
            }
        }, 300);
    }
    window.ptToggleFullscreen = ptToggleFullscreen;

    // ==================== INIT ====================
    document.addEventListener('DOMContentLoaded', function () {
        PT_CANVAS = document.getElementById('ptChartCanvas');
        if (PT_CANVAS) ptAttachCanvasEvents();

        ptAttachScaleBarEvents();
        ptAttachCandlesModalEvents();
        ptAttachCandleDetailModalEvents();

        var jumpBtn = document.getElementById('ptJumpLatest');
        if (jumpBtn) {
            jumpBtn.addEventListener('mousedown', function (e) { e.stopPropagation(); });
            jumpBtn.addEventListener('touchstart', function (e) { e.stopPropagation(); }, { passive: true });
        }

        document.addEventListener('gesturestart', function (e) { e.preventDefault(); }, { passive: false });

        ['ptSymbolModal', 'ptTimeframeModal'].forEach(function (id) {
            var m = document.getElementById(id);
            if (!m) return;
            m.addEventListener('mousedown', function (e) {
                if (e.target === m) {
                    if (id === 'ptSymbolModal') ptCloseSymbolModal();
                    else ptCloseTimeframeModal();
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                ptCloseSymbolModal();
                ptCloseTimeframeModal();
                ptCloseCandlesModal();
                ptCloseCandleDetailModal();
                ptCloseConfigModal();
            }
        });

        PT_CURRENT_SYMBOL    = PT_ALL_SYMBOLS.length    ? PT_ALL_SYMBOLS[0]    : '';
        PT_CURRENT_TIMEFRAME = PT_ALL_TIMEFRAMES.length ? PT_ALL_TIMEFRAMES[0] : '';

        ptRenderTimeframeStrip();
        ptUpdateSymbolLabel();
        ptUpdateTimeframeLabel();

        if (!PT_HAS_BROKER) {
            ptShowEmpty('Please connect your Broker to see chart.');
        } else if (PT_CURRENT_SYMBOL && PT_CURRENT_TIMEFRAME) {
            ptLoadChart();
        } else {
            ptShowEmpty('No symbol or timeframe selected. Configure them in the Signals dashboard.');
        }

        window.addEventListener('resize', function () {
            if (PT_CANDLES.length) {
                ptFitViewportToCandles();
                ptScheduleDraw();
            }
        });

        if (PT_PROGRAMME_ID > 0) {
            ptStartLiveState();
            ptStartCandleRefresh();
        }
    });

    // ==================== LIVE STATE (config) ====================
    function ptStateSignature(state) {
        if (!state) return '';
        var syms = state.symbols || [];
        var tfs  = state.timeframes || [];
        return (state.has_broker ? '1' : '0') + '|' +
               syms.join(',') + '|' +
               tfs.join(',');
    }

    function ptFetchLiveState() {
        if (!PT_LIVE_RUNNING) return;

        var body = 'get_live_state=1';
        fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                PT_LIVE_FAIL_COUNT++;
                ptScheduleNextLiveState();
                return;
            }
            PT_LIVE_FAIL_COUNT = 0;

            var newSig = ptStateSignature(data);
            if (newSig !== PT_LAST_STATE_SIG) {
                PT_LAST_STATE_SIG = newSig;
                ptApplyLiveState(data);
            }
            ptScheduleNextLiveState();
        })
        .catch(function () {
            PT_LIVE_FAIL_COUNT++;
            ptScheduleNextLiveState();
        });
    }

    function ptScheduleNextLiveState() {
        if (!PT_LIVE_RUNNING) return;
        if (PT_LIVE_INTERVAL) clearTimeout(PT_LIVE_INTERVAL);

        var delay = PT_LIVE_BASE_MS;
        if (PT_LIVE_FAIL_COUNT === 1) delay = 3000;
        else if (PT_LIVE_FAIL_COUNT === 2) delay = 5000;
        else if (PT_LIVE_FAIL_COUNT >= 3) delay = PT_LIVE_MAX_MS;

        PT_LIVE_INTERVAL = setTimeout(ptFetchLiveState, delay);
    }

    function ptStartLiveState() {
        PT_LIVE_RUNNING = true;
        PT_LIVE_FAIL_COUNT = 0;
        if (PT_LIVE_INTERVAL) clearTimeout(PT_LIVE_INTERVAL);
        ptFetchLiveState();
    }

    function ptStopLiveState() {
        PT_LIVE_RUNNING = false;
        if (PT_LIVE_INTERVAL) {
            clearTimeout(PT_LIVE_INTERVAL);
            PT_LIVE_INTERVAL = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            ptStopLiveState();
            ptStopCandleRefresh();
        } else if (PT_PROGRAMME_ID > 0) {
            ptStartLiveState();
            ptStartCandleRefresh();
        }
    });

    window.addEventListener('focus', function () {
        if (PT_LIVE_RUNNING) ptFetchLiveState();
        if (PT_REFRESH_RUNNING) ptFetchLatestCandles();
    });

    window.addEventListener('beforeunload', function () {
        ptStopLiveState();
        ptStopCandleRefresh();
    });

    function ptApplyLiveState(state) {
        if (!state) return;

        if (state.has_broker !== PT_HAS_BROKER) {
            PT_HAS_BROKER = !!state.has_broker;

            if (!PT_HAS_BROKER) {
                ptStopLiveState();
                ptStopCandleRefresh();
                PT_CANDLES = [];
                ptClearCanvas();
                ptShowLoading(false);
                ptShowBgLoading(false);
                ptShowEmpty('Please connect your Broker to see chart.');
                return;
            } else {
                ptStartLiveState();
                ptStartCandleRefresh();
            }
        }

        if (!PT_HAS_BROKER) {
            ptShowEmpty('Please connect your Broker to see chart.');
            return;
        }

        var newSymbols    = Array.isArray(state.symbols)    ? state.symbols    : [];
        var newTimeframes = Array.isArray(state.timeframes) ? state.timeframes : [];

        var symbolsChanged    = !ptArraysEqual(PT_ALL_SYMBOLS, newSymbols);
        var timeframesChanged = !ptArraysEqual(PT_ALL_TIMEFRAMES, newTimeframes);

        if (symbolsChanged) PT_ALL_SYMBOLS = newSymbols.slice();
        if (timeframesChanged) PT_ALL_TIMEFRAMES = newTimeframes.slice();

        if (!symbolsChanged && !timeframesChanged) return;

        ptRenderTimeframeStrip();

        if (PT_ALL_SYMBOLS.length && PT_ALL_SYMBOLS.indexOf(PT_CURRENT_SYMBOL) === -1) {
            PT_CURRENT_SYMBOL = PT_ALL_SYMBOLS[0];
            ptUpdateSymbolLabel();
        } else if (!PT_ALL_SYMBOLS.length) {
            PT_CURRENT_SYMBOL = '';
            ptUpdateSymbolLabel();
        }

        if (PT_ALL_TIMEFRAMES.length && PT_ALL_TIMEFRAMES.indexOf(PT_CURRENT_TIMEFRAME) === -1) {
            PT_CURRENT_TIMEFRAME = PT_ALL_TIMEFRAMES[0];
            ptUpdateTimeframeLabel();
        } else if (!PT_ALL_TIMEFRAMES.length) {
            PT_CURRENT_TIMEFRAME = '';
            ptUpdateTimeframeLabel();
        }

        if (PT_CURRENT_SYMBOL && PT_CURRENT_TIMEFRAME) {
            PT_REQUESTED_AMOUNT = PT_DEFAULT_AMOUNT;
            ptLoadChart();
            ptRefreshHigherTimeframeProjection();
        } else {
            PT_CANDLES = [];
            ptClearCanvas();
            ptShowLoading(false);
            ptShowEmpty('No symbol or timeframe selected. Configure them in the Signals dashboard.');
        }
    }

    function ptArraysEqual(a, b) {
        if (a === b) return true;
        if (!a || !b) return false;
        if (a.length !== b.length) return false;
        for (var i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) return false;
        }
        return true;
    }

    // ==================== LIVE CANDLE REFRESH (tail) ====================
    function ptStartCandleRefresh() {
        if (PT_REFRESH_RUNNING) return;
        if (!PT_HAS_BROKER) return;
        if (!PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME) return;
        PT_REFRESH_RUNNING = true;
        if (PT_REFRESH_INTERVAL) clearTimeout(PT_REFRESH_INTERVAL);
        PT_REFRESH_INTERVAL = setTimeout(ptFetchLatestCandles, 1200);
    }

    function ptStopCandleRefresh() {
        PT_REFRESH_RUNNING = false;
        if (PT_REFRESH_INTERVAL) {
            clearTimeout(PT_REFRESH_INTERVAL);
            PT_REFRESH_INTERVAL = null;
        }
    }

    function ptScheduleNextCandleRefresh() {
        if (!PT_REFRESH_RUNNING) return;
        if (PT_REFRESH_INTERVAL) clearTimeout(PT_REFRESH_INTERVAL);
        PT_REFRESH_INTERVAL = setTimeout(ptFetchLatestCandles, PT_REFRESH_MS);
    }

    function ptNewestCandleTime() {
        if (!PT_CANDLES.length) return '';
        return PT_CANDLES[PT_CANDLES.length - 1].time || '';
    }
    function ptNewestCandleEpoch() {
        if (!PT_CANDLES.length) return '';
        var t = PT_CANDLES[PT_CANDLES.length - 1].time || '';
        if (!t) return '';
        var ms = Date.parse(t.replace(' ', 'T') + 'Z');
        if (isNaN(ms)) return '';
        return Math.floor(ms / 1000);
    }

    function ptFetchLatestCandles() {
        if (!PT_REFRESH_RUNNING) return;
        if (PT_REFRESH_INFLIGHT) return;
        if (!PT_HAS_BROKER) { ptScheduleNextCandleRefresh(); return; }
        if (!PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME) {
            ptScheduleNextCandleRefresh();
            return;
        }
        if (!PT_INITIAL_LOADED) {
            ptScheduleNextCandleRefresh();
            return;
        }

        var afterTs = ptNewestCandleEpoch();
        var body  = 'fetch_candles_latest=1'
                + '&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
                + '&symbol='       + encodeURIComponent(PT_CURRENT_SYMBOL)
                + '&timeframe='    + encodeURIComponent(PT_CURRENT_TIMEFRAME);
        if (afterTs !== '') body += '&after_ts=' + encodeURIComponent(afterTs);

        PT_REFRESH_INFLIGHT = true;
        var myToken = PT_LOAD_TOKEN;
        var mySym   = PT_CURRENT_SYMBOL;
        var myTf    = PT_CURRENT_TIMEFRAME;

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            PT_REFRESH_INFLIGHT = false;
            if (!PT_REFRESH_RUNNING) return;
            if (myToken !== PT_LOAD_TOKEN) { ptScheduleNextCandleRefresh(); return; }
            if (mySym !== PT_CURRENT_SYMBOL || myTf !== PT_CURRENT_TIMEFRAME) {
                ptScheduleNextCandleRefresh();
                return;
            }
            if (!data || !data.success) { ptScheduleNextCandleRefresh(); return; }

            var chunk = Array.isArray(data.candles) ? data.candles : [];
            if (chunk.length) ptAppendLiveCandles(chunk);
            ptScheduleNextCandleRefresh();
        })
        .catch(function () {
            PT_REFRESH_INFLIGHT = false;
            ptScheduleNextCandleRefresh();
        });
    }

    function ptAppendLiveCandles(chunk) {
        var newest = ptNewestCandleTime();
        var fresh = [];
        for (var i = 0; i < chunk.length; i++) {
            var c = chunk[i];
            if (!c || !c.time) continue;
            if (newest && c.time <= newest) continue;
            fresh.push(c);
        }
        if (!fresh.length) return;

        var wasFollowing = PT_FOLLOW_LATEST;
        var atRightEdge  = ptIsViewportAtRightEdge();

        for (var j = 0; j < fresh.length; j++) {
            PT_CANDLES.push(fresh[j]);
        }

        if (wasFollowing || atRightEdge) {
            ptScrollToLatest();
            PT_FOLLOW_LATEST = true;
        }
        ptScheduleDraw();
        ptUpdateJumpLatestVisibility();
    }

    function ptIsViewportAtRightEdge() {
        var card = document.getElementById('ptChartCard');
        if (!card) return false;
        if (!PT_CANDLES.length) return true;
        var vw = card.clientWidth;
        var step = ptStep();
        var baseX = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
        var newestRight = baseX + (PT_CANDLES.length - 1) * step + step;
        return newestRight <= (vw - PT_PADDING_RIGHT) + 2;
    }

    // ==================== HIGHER-TIMEFRAME BACKGROUND PROJECTION ====================
    function ptTfMilliseconds(tf) {
        var m = String(tf || '').trim().toLowerCase().match(/^(\d+)\s*([mhdw])$/);
        if (!m) return Number.MAX_SAFE_INTEGER;
        var n = Number(m[1]), u = m[2];
        return n * ({m:60000,h:3600000,d:86400000,w:604800000}[u] || Number.MAX_SAFE_INTEGER);
    }
    function ptProjectionStatus(text, state) {
        var box = document.getElementById('ptProjectionStatus');
        var label = document.getElementById('ptProjectionStatusText');
        var spinner = document.getElementById('ptProjectionSpinner');
        if (!box || !label) return;
        if (!text) { box.className = 'pt-projection-status'; return; }
        box.className = 'pt-projection-status active' + (state ? ' ' + state : '');
        label.textContent = text;
        if (spinner) spinner.style.display = state === 'done' || state === 'error' ? 'none' : '';
    }
    function ptParseRootTimeframes(root) {
        var tfs = Array.isArray(root && root.timeframes) ? root.timeframes.slice() : [];
        if (!tfs.length && root && root.timeframe) {
            try { var parsed = JSON.parse(root.timeframe); tfs = Array.isArray(parsed) ? parsed : [root.timeframe]; }
            catch (_) { tfs = String(root.timeframe).split(','); }
        }
        return tfs.map(function (tf) { return String(tf).trim(); }).filter(Boolean);
    }
    function ptProjectionRootsForCurrentView(config) {
        if (!config || !Array.isArray(config.roots)) return [];
        var currentMs = ptTfMilliseconds(PT_CURRENT_TIMEFRAME);
        var names = [];
        config.roots.forEach(function (root) {
            if (root.applyToChart === false || root.applyToChart === 0 || root.applyToChart === '0') return;
            ptParseRootTimeframes(root).forEach(function (tf) {
                // Strictly higher only: never project a lower timeframe onto a higher one.
                if (ptTfMilliseconds(tf) > currentMs && names.indexOf(tf) < 0) names.push(tf);
            });
        });
        return names.sort(function (a,b) { return ptTfMilliseconds(a)-ptTfMilliseconds(b); });
    }
    function ptFetchBackgroundTimeframe(tf, token) {
        var key = [PT_PROGRAMME_ID, PT_CURRENT_SYMBOL, tf].join('|');
        if (PT_BG_TF_PROMISES[key]) return PT_BG_TF_PROMISES[key];
        var result = [], before = '';
        function page() {
            var body = 'fetch_candles=1&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
                + '&symbol=' + encodeURIComponent(PT_CURRENT_SYMBOL)
                + '&timeframe=' + encodeURIComponent(tf) + '&limit=2000'
                + (before ? '&before=' + encodeURIComponent(before) : '');
            return fetch('programme_training.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body:body
            }).then(function(r){return r.json();}).then(function(data){
                if (token !== PT_PROJECTION_REQUEST_TOKEN) return result;
                if (!data || !data.success) throw new Error((data && data.message) || ('Could not load ' + tf + ' candles'));
                var chunk = Array.isArray(data.candles) ? data.candles : [];
                if (!chunk.length) return result;
                result = chunk.concat(result);
                before = chunk[0].time;
                // Bound background history to 50,000 rows per timeframe.
                if (data.has_more && result.length < 50000) return page();
                return result;
            });
        }
        PT_BG_TF_PROMISES[key] = page().then(function(candles){
            if (token === PT_PROJECTION_REQUEST_TOKEN) PT_BG_TF_DATA[tf] = candles || [];
            delete PT_BG_TF_PROMISES[key];
            return candles || [];
        }).catch(function(err){
            delete PT_BG_TF_PROMISES[key];
            throw err;
        });
        return PT_BG_TF_PROMISES[key];
    }
    function ptBuildProjectionMap(higherCandles, lowerCandles) {
        // Parse candle timestamps once. The old implementation reparsed every
        // lower-timeframe candle for every higher-timeframe candle (O(H × L)).
        // Since both candle arrays are chronological, a moving lower-candle
        // pointer builds the same overlap map in near-linear time.
        function ts(v) {
            if (!v) return NaN;
            var s = String(v).trim();
            var n = Date.parse(s.indexOf('T') >= 0 ? s : s.replace(' ','T') + 'Z');
            return Number.isFinite(n) ? n : NaN;
        }
        var high = higherCandles || [], low = lowerCandles || [];
        var lowerIntervals = new Array(low.length);
        var lowerDuration = ptTfMilliseconds(PT_CURRENT_TIMEFRAME);
        for (var li = 0; li < low.length; li++) {
            var lc = low[li], ls = ts(lc.open_time || lc.time), le = ts(lc.close_time);
            if (!Number.isFinite(le)) le = Number.isFinite(ls) ? ls + lowerDuration : NaN;
            lowerIntervals[li] = { start: ls, end: le };
        }
        var result = new Array(high.length), cursor = 0;
        for (var hi = 0; hi < high.length; hi++) {
            var hc = high[hi], hs = ts(hc.open_time || hc.time), he = ts(hc.close_time);
            if (!Number.isFinite(he)) {
                var duration = ptTfMilliseconds(hc.timeframe || PT_CURRENT_TIMEFRAME);
                he = Number.isFinite(hs) ? hs + (Number.isFinite(duration) ? duration : 0) : NaN;
            }
            if (!Number.isFinite(hs) || !Number.isFinite(he) || he <= hs) {
                result[hi] = { first: -1, last: -1 };
                continue;
            }
            while (cursor < lowerIntervals.length &&
                   (!Number.isFinite(lowerIntervals[cursor].end) || lowerIntervals[cursor].end <= hs)) cursor++;
            var first = -1, last = -1, scan = cursor;
            while (scan < lowerIntervals.length) {
                var interval = lowerIntervals[scan];
                if (Number.isFinite(interval.start) && interval.start >= he) break;
                if (Number.isFinite(interval.start) && Number.isFinite(interval.end) &&
                    interval.start < he && interval.end > hs) {
                    if (first < 0) first = scan;
                    last = scan;
                }
                scan++;
            }
            result[hi] = { first: first, last: last };
        }
        return result;
    }
    function ptProjectionPieceIndex(index, level) {
        var map = PT_PROJECTION_MAP && PT_PROJECTION_MAP[index];
        var lower = PT_PROJECTION_LOWER_CANDLES;
        if (!map || map.first < 0 || map.last < 0 || !lower) return -1;
        level = String(level || 'center').toLowerCase();
        if (level === 'open') return map.first;
        if (level === 'close') return map.last;
        if (level !== 'high' && level !== 'low') return Math.floor((map.first + map.last) / 2);
        var best = -1, extreme = level === 'high' ? -Infinity : Infinity;
        for (var i = map.first; i <= map.last; i++) {
            var value = Number(lower[i] && lower[i][level]);
            if (!Number.isFinite(value)) continue;
            if ((level === 'high' && value > extreme) || (level === 'low' && value < extreme)) { extreme = value; best = i; }
        }
        return best >= 0 ? best : map.first;
    }
    function ptOverlayX(geo, index, level) {
        var map = PT_PROJECTION_MAP && PT_PROJECTION_MAP[index];
        if (PT_PROJECTION_MAP && (!map || map.first < 0 || map.last < 0)) return NaN;
        if (!map) return geo.baseX + index * geo.step + geo.step / 2;
        var pieceIndex = ptProjectionPieceIndex(index, level || 'center');
        if (pieceIndex < 0) return NaN;
        return geo.baseX + pieceIndex * geo.step + geo.step / 2;
    }
    function ptProjectionPrice(index, level, fallbackCandles) {
        var lower = PT_PROJECTION_LOWER_CANDLES;
        var piece = ptProjectionPieceIndex(index, level);
        if (lower && piece >= 0 && lower[piece]) {
            if (level === 'open' || level === 'close' || level === 'high' || level === 'low') return Number(lower[piece][level]);
        }
        return ptPrice((fallbackCandles || PT_CANDLES)[index], level);
    }
    function ptRefreshHigherTimeframeProjection() {
        if (!PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME || !PT_HAS_BROKER) return;
        var token = ++PT_PROJECTION_REQUEST_TOKEN;
        PT_BG_TF_DATA = Object.create(null);
        PT_BG_TF_PROMISES = Object.create(null);
        var configPromise = (typeof ptLoadSavedConfiguration === 'function')
            ? ptLoadSavedConfiguration(false).catch(function(){ return null; })
            : Promise.resolve(null);
        configPromise.then(function(){
            if (token !== PT_PROJECTION_REQUEST_TOKEN) return;
            var config = typeof window.ptGetTrainingConfiguration === 'function' ? window.ptGetTrainingConfiguration() : null;
            var tfs = ptProjectionRootsForCurrentView(config);
            if (!tfs.length) { ptProjectionStatus('', ''); return; }
            var rootNames = [];
            (config.roots || []).forEach(function(root){
                if (root.applyToChart === false || root.applyToChart === 0 || root.applyToChart === '0') return;
                if (ptParseRootTimeframes(root).some(function(tf){return tfs.indexOf(tf)>=0;}))
                    rootNames.push(root.name || root.root_name || ('Root ' + root.id));
            });
            ptProjectionStatus('Projecting ' + Array.from(new Set(rootNames)).join(', ') + ' — loading higher-timeframe candles…');
            return Promise.all(tfs.map(function(tf){ return ptFetchBackgroundTimeframe(tf, token); }))
                .then(function(){
                    if (token !== PT_PROJECTION_REQUEST_TOKEN) return;
                    ptProjectionStatus('Projecting ' + Array.from(new Set(rootNames)).join(', ') + ' — ready', 'done');
                    ptScheduleDraw();
                }).catch(function(err){
                    if (token !== PT_PROJECTION_REQUEST_TOKEN) return;
                    ptProjectionStatus('Projection loading failed: ' + (err.message || 'unknown error'), 'error');
                    console.warn('Higher-timeframe projection load failed', err);
                });
        });
    }

    // ==================== TIMEFRAME SWITCHING ====================
    function ptSwitchToTimeframe(targetTF) {
        if (!targetTF) return;
        if (!PT_HAS_BROKER) { ptShowEmpty('Please connect your Broker to see chart.'); return; }
        if (targetTF === PT_CURRENT_TIMEFRAME) return;

        PT_CURRENT_TIMEFRAME = targetTF;
        PT_REQUESTED_AMOUNT  = PT_DEFAULT_AMOUNT;

        ptRenderTimeframeStrip();
        ptUpdateTimeframeLabel();
        ptLoadChart();
        ptRefreshHigherTimeframeProjection();
    }

    // ==================== TOPBAR ====================
    function ptUpdateSymbolLabel() {
        var el = document.getElementById('ptSymbolLabel');
        if (!el) return;
        el.textContent = PT_CURRENT_SYMBOL || 'Select Symbol';
    }

    function ptUpdateTimeframeLabel() {
        var el = document.getElementById('ptTimeframeLabel');
        if (!el) return;
        el.textContent = PT_CURRENT_TIMEFRAME || 'Timeframe';
    }

    function ptRenderTimeframeStrip() {
        var strip = document.getElementById('ptTfStrip');
        if (!strip) return;
        if (!PT_ALL_TIMEFRAMES.length) { strip.innerHTML = '<span class="pt-tf-empty">No timeframes</span>'; return; }
        var html = '';
        PT_ALL_TIMEFRAMES.forEach(function (tf) {
            var cls = 'pt-tf-btn' + (tf === PT_CURRENT_TIMEFRAME ? ' active' : '');
            html += '<button type="button" class="' + cls + '" data-tf="' + ptEscapeHtml(tf) + '" '
                  + 'onclick="ptSelectTimeframe(\'' + tf.replace(/'/g, "\'") + '\')">'
                  + ptEscapeHtml(tf) + '</button>';
        });
        strip.innerHTML = html;
    }

    function ptSelectTimeframe(tf) {
        if (!tf) return;
        if (!PT_HAS_BROKER) { ptShowEmpty('Please connect your Broker to see chart.'); return; }
        ptCloseTimeframeModal();
        if (tf === PT_CURRENT_TIMEFRAME) return;
        ptSwitchToTimeframe(tf);
    }

    // ==================== SYMBOL MODAL ====================
    var PT_SYMBOL_FILTER = '';
    function ptOpenSymbolModal() {
        if (!PT_HAS_BROKER) { ptShowEmpty('Please connect your Broker to see chart.'); return; }
        if (!PT_ALL_SYMBOLS.length) { ptShowEmpty('No symbols selected. Configure them in the Signals dashboard.'); return; }
        PT_SYMBOL_FILTER = '';
        var inp = document.getElementById('ptSymbolSearch');
        if (inp) inp.value = '';
        ptRenderSymbolModalList();
        document.getElementById('ptSymbolModal').classList.add('active');
    }
    function ptCloseSymbolModal() {
        var m = document.getElementById('ptSymbolModal');
        if (m) m.classList.remove('active');
    }
    function ptFilterSymbolModal(q) {
        PT_SYMBOL_FILTER = (q || '').trim().toUpperCase();
        ptRenderSymbolModalList();
    }
    function ptRenderSymbolModalList() {
        var list = document.getElementById('ptSymbolModalList');
        var countEl = document.getElementById('ptSymbolModalCount');
        if (!list) return;
        if (countEl) countEl.textContent = PT_ALL_SYMBOLS.length + ' symbols';
        var filtered = PT_ALL_SYMBOLS.filter(function (s) {
            if (!PT_SYMBOL_FILTER) return true;
            return String(s).toUpperCase().indexOf(PT_SYMBOL_FILTER) !== -1;
        });
        if (!filtered.length) {
            list.innerHTML = '<div class="pt-picker-empty">No symbols match your search.</div>';
            return;
        }
        var html = '';
        filtered.forEach(function (sym) {
            var cls = 'pt-picker-row' + (sym === PT_CURRENT_SYMBOL ? ' is-active' : '');
            html += '<button type="button" class="' + cls + '" '
                  + 'onclick="ptSelectSymbol(\'' + sym.replace(/'/g, "\'") + '\')">'
                  + ptEscapeHtml(sym) + '</button>';
        });
        list.innerHTML = html;
    }
    function ptSelectSymbol(sym) {
        if (!sym) return;
        if (!PT_HAS_BROKER) {
            ptShowEmpty('Please connect your Broker to see chart.');
            ptCloseSymbolModal();
            return;
        }
        PT_CURRENT_SYMBOL = sym;
        PT_REQUESTED_AMOUNT = PT_DEFAULT_AMOUNT;
        ptUpdateSymbolLabel();
        ptCloseSymbolModal();
        ptLoadChart();
        ptRefreshHigherTimeframeProjection();
    }

    // ==================== TIMEFRAME MODAL ====================
    var PT_TIMEFRAME_FILTER = '';
    function ptOpenTimeframeModal() {
        if (!PT_HAS_BROKER) { ptShowEmpty('Please connect your Broker to see chart.'); return; }
        if (!PT_ALL_TIMEFRAMES.length) { ptShowEmpty('No timeframes selected. Configure them in the Signals dashboard.'); return; }
        PT_TIMEFRAME_FILTER = '';
        var inp = document.getElementById('ptTimeframeSearch');
        if (inp) inp.value = '';
        ptRenderTimeframeModalList();
        document.getElementById('ptTimeframeModal').classList.add('active');
    }
    function ptCloseTimeframeModal() {
        var m = document.getElementById('ptTimeframeModal');
        if (m) m.classList.remove('active');
    }
    function ptFilterTimeframeModal(q) {
        PT_TIMEFRAME_FILTER = (q || '').trim().toUpperCase();
        ptRenderTimeframeModalList();
    }
    function ptRenderTimeframeModalList() {
        var list = document.getElementById('ptTimeframeModalList');
        var countEl = document.getElementById('ptTimeframeModalCount');
        if (!list) return;
        if (countEl) countEl.textContent = PT_ALL_TIMEFRAMES.length + ' timeframes';
        var filtered = PT_ALL_TIMEFRAMES.filter(function (t) {
            if (!PT_TIMEFRAME_FILTER) return true;
            return String(t).toUpperCase().indexOf(PT_TIMEFRAME_FILTER) !== -1;
        });
        if (!filtered.length) {
            list.innerHTML = '<div class="pt-picker-empty">No timeframes match your search.</div>';
            return;
        }
        var html = '';
        filtered.forEach(function (tf) {
            var cls = 'pt-picker-row' + (tf === PT_CURRENT_TIMEFRAME ? ' is-active' : '');
            html += '<button type="button" class="' + cls + '" '
                  + 'onclick="ptPickTimeframe(\'' + tf.replace(/'/g, "\'") + '\')">'
                  + ptEscapeHtml(tf) + '</button>';
        });
        list.innerHTML = html;
    }
    function ptPickTimeframe(tf) {
        if (!tf) return;
        if (!PT_HAS_BROKER) {
            ptShowEmpty('Please connect your Broker to see chart.');
            ptCloseTimeframeModal();
            return;
        }
        ptCloseTimeframeModal();
        if (tf === PT_CURRENT_TIMEFRAME) return;
        ptSwitchToTimeframe(tf);
    }

    // ==================== CANDLES-AMOUNT MODAL ====================
    function ptAttachCandlesModalEvents() {
        var confirm = document.getElementById('ptCandlesConfirm');
        var cancel  = document.getElementById('ptCandlesCancel');
        var input   = document.getElementById('ptCandlesInput');
        if (confirm) confirm.addEventListener('click', ptConfirmCandlesModal);
        if (cancel)  cancel.addEventListener('click', ptCloseCandlesModal);
        if (input) input.addEventListener('keydown', function (e) { if (e.key === 'Enter') ptConfirmCandlesModal(); });
    }
    function ptCloseCandlesModal() {
        var modal = document.getElementById('ptCandlesModal');
        if (modal) modal.classList.remove('active');
    }
    function ptConfirmCandlesModal() {
        if (!PT_HAS_BROKER) {
            ptCloseCandlesModal();
            ptShowEmpty('Please connect your Broker to see chart.');
            return;
        }

        var inp  = document.getElementById('ptCandlesInput');
        var info = document.getElementById('ptCandlesInfo');
        var raw  = inp ? String(inp.value || '').trim() : '';
        var n    = parseInt(raw, 10);
        if (!raw || isNaN(n) || n <= 0) { if (info) info.textContent = 'Please enter a positive number.'; return; }
        PT_REQUESTED_AMOUNT = n;
        ptCloseCandlesModal();
        ptLoadChart();
    }

    // ==================== CANDLE DETAIL MODAL ====================
    function ptAttachCandleDetailModalEvents() {
        var modal = document.getElementById('ptCandleDetailModal');
        var closeBtn = document.getElementById('ptCandleDetailClose');
        if (closeBtn) closeBtn.addEventListener('click', ptCloseCandleDetailModal);
        if (modal) {
            modal.addEventListener('mousedown', function (e) {
                if (e.target === modal) ptCloseCandleDetailModal();
            });
        }
    }
    function ptOpenCandleDetailModal(idx) {
        if (!PT_HAS_BROKER) { ptShowEmpty('Please connect your Broker to see chart.'); return; }
        if (idx < 0 || idx >= PT_CANDLES.length) return;
        var c = PT_CANDLES[idx];

        var titleEl = document.getElementById('ptCandleDetailTitle');
        var subEl   = document.getElementById('ptCandleDetailSub');
        var bodyEl  = document.getElementById('ptCandleDetailBody');
        if (!bodyEl) return;

        if (titleEl) titleEl.textContent = 'Candle #' + idx + ' — ' + ptFormatTime(c.time);
        if (subEl)   subEl.textContent   = PT_CURRENT_SYMBOL + ' · ' + PT_CURRENT_TIMEFRAME;

        var rows = [
            ['Timestamp',            c.time],
            ['Open time',            c.open_time || '—'],
            ['Close time',           c.close_time || '—'],
            ['Open',                 ptFormatPrice(c.open)],
            ['High',                 ptFormatPrice(c.high)],
            ['Low',                  ptFormatPrice(c.low)],
            ['Close',                ptFormatPrice(c.close)],
            ['Candle center',        c.candle_center       != null ? ptFormatPrice(c.candle_center)       : '—'],
            ['Body center',          c.body_center         != null ? ptFormatPrice(c.body_center)         : '—'],
            ['High wick center',     c.high_wick_center    != null ? ptFormatPrice(c.high_wick_center)    : '—'],
            ['Low wick center',      c.low_wick_center     != null ? ptFormatPrice(c.low_wick_center)     : '—'],
            ['Candle width center',  c.candle_width_center != null ? ptFormatPrice(c.candle_width_center) : '—'],
            ['Volume',               c.volume              != null ? String(c.volume)                     : '—'],
            ['Type',                 (c.close > c.open ? 'bullish' : (c.close < c.open ? 'bearish' : 'flat'))]
        ];

        var html = '';
        rows.forEach(function (row) {
            html += '<div class="pt-candle-detail-row">'
                  +   '<span class="pt-candle-detail-label">' + ptEscapeHtml(row[0]) + '</span>'
                  +   '<span class="pt-candle-detail-value">' + ptEscapeHtml(row[1]) + '</span>'
                  + '</div>';
        });
        bodyEl.innerHTML = html;

        var modal = document.getElementById('ptCandleDetailModal');
        if (modal) modal.classList.add('active');
    }
    function ptCloseCandleDetailModal() {
        var modal = document.getElementById('ptCandleDetailModal');
        if (modal) modal.classList.remove('active');
    }

    // ==================== LOAD CHART ====================
    function ptLoadChart() {
        if (!PT_HAS_BROKER) {
            ptShowEmpty('Please connect your Broker to see chart.');
            return;
        }
        if (!PT_CURRENT_SYMBOL || !PT_CURRENT_TIMEFRAME) { ptShowEmpty('Select a symbol and timeframe.'); return; }
        PT_LOAD_TOKEN++;
        PT_CANDLES = [];
        PT_HOVER_INDEX = -1;
        PT_CURSOR_Y = null;
        PT_HAS_MORE_OLDER = true;
        PT_IS_LOADING_OLDER = false;
        PT_INITIAL_LOADED = false;
        PT_CONSECUTIVE_ERRORS = 0;
        PT_VIEW.offsetX = 0; PT_VIEW.offsetY = 0; PT_VIEW.priceScale = 1.0; PT_VIEW.candleWidth = 9;
        ptShowLoading(true); ptShowEmpty(null); ptClearCanvas();
        ptFetchChunk(null, PT_LOAD_TOKEN, true);
    }

    function ptFetchChunk(before, token, isInitial) {
        if (!PT_HAS_BROKER) {
            PT_HAS_MORE_OLDER = false;
            PT_IS_LOADING_OLDER = false;
            PT_CANDLES = [];
            ptShowLoading(false);
            ptShowBgLoading(false);
            ptClearCanvas();
            ptShowEmpty('Please connect your Broker to see chart.');
            return;
        }

        var pageSize = PT_PAGE_SIZE;
        if (PT_REQUESTED_AMOUNT !== null) {
            var remaining = PT_REQUESTED_AMOUNT - PT_CANDLES.length;
            if (remaining > 0 && remaining < pageSize) pageSize = remaining;
        }
        var body = 'fetch_candles=1'
                 + '&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&symbol='    + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe=' + encodeURIComponent(PT_CURRENT_TIMEFRAME)
                 + '&limit='     + pageSize;
        if (before) body += '&before=' + encodeURIComponent(before);

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (token !== PT_LOAD_TOKEN) return;
            if (!PT_HAS_BROKER) {
                PT_CANDLES = [];
                ptClearCanvas();
                ptShowLoading(false);
                ptShowBgLoading(false);
                ptShowEmpty('Please connect your Broker to see chart.');
                return;
            }
            if (!data.success) {
                PT_CONSECUTIVE_ERRORS++;
                ptShowLoading(false); ptShowBgLoading(false);
                PT_HAS_MORE_OLDER = false;
                if (!PT_CANDLES.length) ptShowEmpty(data.message || 'Failed to load candles.');
                else { ptShowBgLoading(true, true); ptSetBgLoadingText('Stopped loading (server limit).'); }
                return;
            }
            PT_CONSECUTIVE_ERRORS = 0;
            var chunk = Array.isArray(data.candles) ? data.candles : [];
            if (isInitial) { ptShowLoading(false); PT_INITIAL_LOADED = true; }
            if (chunk.length) {
                if (isInitial) {
                    PT_CANDLES = chunk.slice();
                    ptFitViewportToCandles();
                    ptScheduleDraw();
                    ptScrollToLatest();
                    PT_FOLLOW_LATEST = true;
                    ptScheduleDraw();
                } else {
                    var step = ptStep();
                    PT_VIEW.offsetX += chunk.length * step;
                    PT_CANDLES = chunk.concat(PT_CANDLES);
                    ptScheduleDraw();
                }
            } else if (isInitial) {
                ptClearCanvas();
                ptShowEmpty('No candle records found for this symbol timeframe.');
                PT_HAS_MORE_OLDER = false;
                return;
            }
            PT_HAS_MORE_OLDER = !!data.has_more && chunk.length > 0;
            if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) PT_HAS_MORE_OLDER = false;
            PT_IS_LOADING_OLDER = false;
            ptUpdateJumpLatestVisibility();

            if (isInitial) {
                ptAutoLoadUpToTarget(token);
            }
        })
        .catch(function () {
            if (token !== PT_LOAD_TOKEN) return;
            PT_CONSECUTIVE_ERRORS++;
            ptShowLoading(false);
            PT_IS_LOADING_OLDER = false;
            if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                PT_HAS_MORE_OLDER = false;
                ptShowBgLoading(true, true);
                ptSetBgLoadingText('Stopped loading (network/DB limit).');
                return;
            }
            if (PT_CANDLES.length) setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
            else ptShowEmpty('Network error loading candles.');
        });
    }

    function ptAutoLoadUpToTarget(token) {
        if (!PT_HAS_BROKER) {
            PT_HAS_MORE_OLDER = false;
            PT_IS_LOADING_OLDER = false;
            ptShowBgLoading(false);
            return;
        }

        if (token !== PT_LOAD_TOKEN) return;
        if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) {
            ptShowBgLoading(false); ptFitViewportToCandles(); ptScheduleDraw();
            ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility();
            return;
        }
        if (!PT_HAS_MORE_OLDER) {
            ptShowBgLoading(false); ptFitViewportToCandles(); ptScheduleDraw();
            ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility();
            return;
        }
        if (!PT_CANDLES.length) { ptShowBgLoading(false); return; }
        if (PT_IS_LOADING_OLDER) return;
        if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
            ptShowBgLoading(true, true);
            ptSetBgLoadingText('Stopped loading (repeated failures).');
            return;
        }
        PT_IS_LOADING_OLDER = true;
        ptShowBgLoading(true);
        ptSetBgLoadingText('Loading candles… (' + PT_CANDLES.length.toLocaleString()
            + (PT_REQUESTED_AMOUNT !== null ? ' / ' + PT_REQUESTED_AMOUNT.toLocaleString() : '') + ')');
        var oldest = PT_CANDLES[0].time;
        var pageSize = PT_PAGE_SIZE;
        if (PT_REQUESTED_AMOUNT !== null) {
            var remaining = PT_REQUESTED_AMOUNT - PT_CANDLES.length;
            if (remaining > 0 && remaining < pageSize) pageSize = remaining;
        }
        var body = 'fetch_candles=1'
                 + '&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
                 + '&symbol='    + encodeURIComponent(PT_CURRENT_SYMBOL)
                 + '&timeframe=' + encodeURIComponent(PT_CURRENT_TIMEFRAME)
                 + '&limit='     + pageSize
                 + '&before='    + encodeURIComponent(oldest);

        fetch('programme_training.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/x-www-form-urlencoded', 'X-Requested-With':'XMLHttpRequest' },
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            PT_IS_LOADING_OLDER = false;
            if (token !== PT_LOAD_TOKEN) return;
            if (!PT_HAS_BROKER) {
                PT_CANDLES = [];
                ptClearCanvas();
                ptShowLoading(false);
                ptShowBgLoading(false);
                ptShowEmpty('Please connect your Broker to see chart.');
                return;
            }
            if (!data.success) {
                PT_CONSECUTIVE_ERRORS++;
                if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                    PT_HAS_MORE_OLDER = false;
                    ptShowBgLoading(true, true);
                    ptSetBgLoadingText('Stopped loading (server limit).');
                } else setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
                return;
            }
            PT_CONSECUTIVE_ERRORS = 0;
            var chunk = Array.isArray(data.candles) ? data.candles : [];
            if (chunk.length) {
                var step = ptStep();
                PT_VIEW.offsetX += chunk.length * step;
                PT_CANDLES = chunk.concat(PT_CANDLES);
                ptScheduleDraw();
                ptUpdateJumpLatestVisibility();
            }
            PT_HAS_MORE_OLDER = !!data.has_more && chunk.length > 0;
            if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) PT_HAS_MORE_OLDER = false;
            if (!PT_HAS_MORE_OLDER) {
                ptFitViewportToCandles(); ptScheduleDraw();
                ptCenterOnLatest(); ptScheduleDraw();
                ptShowBgLoading(false);
                return;
            }
            setTimeout(function () { ptAutoLoadUpToTarget(token); }, 25);
        })
        .catch(function () {
            PT_IS_LOADING_OLDER = false;
            PT_CONSECUTIVE_ERRORS++;
            if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) {
                PT_HAS_MORE_OLDER = false;
                ptShowBgLoading(true, true);
                ptSetBgLoadingText('Stopped loading (network/DB limit).');
            } else setTimeout(function () { ptAutoLoadUpToTarget(token); }, 800);
        });
    }

    function ptShowBgLoading(on, warn) {
        var el = document.getElementById('ptBgLoading');
        if (!el) return;
        if (on) { el.style.display = 'inline-block'; el.classList.toggle('pt-warn', !!warn); }
        else { el.style.display = 'none'; el.classList.remove('pt-warn'); }
    }
    function ptSetBgLoadingText(t) {
        var el = document.getElementById('ptBgLoading');
        if (el) el.textContent = t;
    }

    // ==================== FIT VIEWPORT ====================
    function ptFitViewportToCandles() {
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vw = card.clientWidth;
        var target = PT_CANDLES.length;
        if (target < 2) return;
        var avail = vw - PT_PADDING_LEFT - PT_PADDING_RIGHT;
        var gapRatio = 0.35;
        var cw = avail / (target * (1 + gapRatio));
        cw = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, cw));
        PT_VIEW.candleWidth = cw;
        PT_VIEW.candleSpacing = Math.max(0.4, cw * gapRatio);
    }
    function ptCenterOnLatest() {
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vw = card.clientWidth; var step = ptStep();
        var n = PT_CANDLES.length; if (!n) return;
        var newestCenterX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + (n - 1) * step + step / 2;
        PT_VIEW.offsetX = newestCenterX - (vw / 2);
        ptClampOffsetX();
        PT_FOLLOW_LATEST = false;
    }
    function ptScrollToLatest() {
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vw = card.clientWidth; var step = ptStep();
        var n = PT_CANDLES.length; if (!n) return;
        var newestRight = PT_PADDING_LEFT + PT_VIRTUAL_PAD + (n - 1) * step + step;
        var targetRightEdge = vw - PT_PADDING_RIGHT - PT_VIEW.candleSpacing;
        PT_VIEW.offsetX = newestRight - targetRightEdge;
        ptClampOffsetX();
        PT_FOLLOW_LATEST = true;
    }

    // ==================== JUMP-TO-LATEST ====================
    function ptUpdateJumpLatestVisibility() {
        var btn = document.getElementById('ptJumpLatest');
        if (!btn) return;
        if (!PT_CANDLES.length) { btn.style.display = 'none'; return; }
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vw = card.clientWidth; var step = ptStep();
        var baseX = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
        var newestLeft  = baseX + (PT_CANDLES.length - 1) * step + step / 2 - PT_VIEW.candleWidth / 2;
        var newestRight = newestLeft + PT_VIEW.candleWidth;
        var leftLimit = PT_PADDING_LEFT, rightLimit = vw - PT_PADDING_RIGHT;
        var fullyVisible = newestLeft >= leftLimit && newestRight <= rightLimit;
        btn.style.display = fullyVisible ? 'none' : 'flex';
    }
    function ptJumpToLatest() { ptCenterOnLatest(); ptScheduleDraw(); ptUpdateJumpLatestVisibility(); }
    window.ptJumpToLatest = ptJumpToLatest;

    // ==================== LAZY LOAD OLDER ====================
    function ptMaybeLoadOlder() {
        if (!PT_HAS_BROKER) return;
        if (!PT_INITIAL_LOADED) return;
        if (!PT_HAS_MORE_OLDER) return;
        if (PT_IS_LOADING_OLDER) return;
        if (PT_CONSECUTIVE_ERRORS >= PT_MAX_CONSECUTIVE_ERRORS) return;
        if (!PT_CANDLES.length) return;
        if (PT_REQUESTED_AMOUNT !== null && PT_CANDLES.length >= PT_REQUESTED_AMOUNT) return;
        if (PT_VIEW.offsetX < 400) ptAutoLoadUpToTarget(PT_LOAD_TOKEN);
    }

    // ==================== UI STATE ====================
    function ptShowLoading(on) {
        var el = document.getElementById('ptChartLoading');
        if (el) el.style.display = on ? 'flex' : 'none';
    }
    function ptShowEmpty(msg) {
        var el = document.getElementById('ptChartEmpty');
        if (!el) return;
        if (msg) { el.querySelector('p').textContent = msg; el.style.display = 'flex'; }
        else el.style.display = 'none';
    }
    function ptClearCanvas() {
        if (!PT_CTX || !PT_CANVAS) return;
        PT_CTX.clearRect(0, 0, PT_CANVAS.width, PT_CANVAS.height);
    }

    // ==================== DRAW ====================
    function ptDrawChart() {
        if (!PT_CANVAS) PT_CANVAS = document.getElementById('ptChartCanvas');
        if (!PT_CANVAS) return;
        PT_CTX = PT_CANVAS.getContext('2d');

        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var dpr = window.devicePixelRatio || 1;

        var viewportW = card.clientWidth;
        var viewportH = card.clientHeight;

        // Resizing a canvas clears it and reallocates its backing bitmap. Do that
        // only when its actual pixel dimensions changed, not on every hover/pan frame.
        var pixelW = Math.max(1, Math.floor(viewportW * dpr));
        var pixelH = Math.max(1, Math.floor(viewportH * dpr));
        if (PT_CANVAS.width !== pixelW || PT_CANVAS.height !== pixelH) {
            PT_CANVAS.style.width = viewportW + 'px';
            PT_CANVAS.style.height = viewportH + 'px';
            PT_CANVAS.width = pixelW;
            PT_CANVAS.height = pixelH;
        }
        PT_CTX.setTransform(dpr, 0, 0, dpr, 0, 0);
        PT_CTX.clearRect(0, 0, viewportW, viewportH);

        if (!PT_CANDLES.length) { ptUpdateJumpLatestVisibility(); return; }

        var styles = getComputedStyle(document.body);
        var accent   = (styles.getPropertyValue('--pt-accent') || '#12a36b').trim() || '#12a36b';
        var textMut  = (styles.getPropertyValue('--pt-muted')  || '#60736d').trim() || '#60736d';
        var bg       = (styles.getPropertyValue('--pt-card')   || '#ffffff').trim() || '#ffffff';

        PT_CTX.fillStyle = bg;
        PT_CTX.fillRect(0, 0, viewportW, viewportH);

        var step = ptStep();
        var cw   = PT_VIEW.candleWidth;
        var half = cw / 2;
        var baseX = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;

        var firstVisible = Math.max(0, Math.floor((PT_VIEW.offsetX - PT_PADDING_LEFT - PT_VIRTUAL_PAD) / step));
        var lastVisible  = Math.min(PT_CANDLES.length - 1,
                            Math.ceil((PT_VIEW.offsetX + viewportW - PT_PADDING_LEFT - PT_VIRTUAL_PAD) / step));
        if (lastVisible < firstVisible) { ptUpdateJumpLatestVisibility(); return; }

        var minPrice = Infinity, maxPrice = -Infinity;
        for (var i = firstVisible; i <= lastVisible; i++) {
            var c = PT_CANDLES[i];
            if (c.low  < minPrice) minPrice = c.low;
            if (c.high > maxPrice) maxPrice = c.high;
        }
        if (!isFinite(minPrice) || !isFinite(maxPrice)) { ptUpdateJumpLatestVisibility(); return; }
        // While locating a trade, include its entry, stop-loss and take-profit prices
        // in the scale calculation so the complete trade area remains visible.
        if (PT_LOCATE_ARROW) {
            if (Number.isFinite(Number(PT_LOCATE_ARROW.priceMin))) minPrice = Math.min(minPrice, Number(PT_LOCATE_ARROW.priceMin));
            if (Number.isFinite(Number(PT_LOCATE_ARROW.priceMax))) maxPrice = Math.max(maxPrice, Number(PT_LOCATE_ARROW.priceMax));
        }
        if (minPrice === maxPrice) { minPrice -= 1; maxPrice += 1; }

        var chartTop    = PT_PADDING_TOP;
        var chartBottom = viewportH - PT_PADDING_BOTTOM;
        var chartHeight = chartBottom - chartTop;
        if (chartHeight <= 0) { ptUpdateJumpLatestVisibility(); return; }

        var rawRange = maxPrice - minPrice;
        var padRatio = 0.05 * PT_VIEW.priceScale;
        var rangeTop = maxPrice + rawRange * padRatio;
        var rangeBot = minPrice - rawRange * padRatio;
        var mid = (rangeTop + rangeBot) / 2;
        var halfRange = (rangeTop - rangeBot) / 2 * PT_VIEW.priceScale;
        rangeTop = mid + halfRange;
        rangeBot = mid - halfRange;
        var range = rangeTop - rangeBot; if (range <= 0) range = 1;

        // During trade location, pan the existing price scale vertically so the
        // entry/stop-loss area is centred. This changes offset only; priceScale and
        // chartHeight remain untouched, so locating a trade never zooms vertically.
        var priceShift = (PT_VIEW.offsetY / chartHeight) * range;
        if (PT_LOCATE_ARROW && Number.isFinite(Number(PT_LOCATE_ARROW.centerPrice))) {
            var desiredShift = Number(PT_LOCATE_ARROW.centerPrice) - mid;
            PT_VIEW.offsetY = desiredShift * chartHeight / range;
            priceShift = desiredShift;
        }
        var pTop = rangeTop + priceShift;
        var pBot = rangeBot + priceShift;
        var pRange = pTop - pBot;

        function pToY(p) { return chartTop + (pTop - p) / pRange * chartHeight; }
        function yToP(y) { return pTop - (y - chartTop) / chartHeight * pRange; }

        PT_CTX.fillStyle = textMut;
        PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
        PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'right';
        var gridLines = 6;
        for (var g = 0; g <= gridLines; g++) {
            var t = g / gridLines;
            var y = chartTop + t * chartHeight;
            var price = pTop - t * pRange;
            PT_CTX.fillText(ptFormatPrice(price), PT_PADDING_LEFT - 8, y);
        }

        for (var k = firstVisible; k <= lastVisible; k++) {
            var cd = PT_CANDLES[k];
            var cx = baseX + k * step + step / 2;
            var yHigh = pToY(cd.high), yLow = pToY(cd.low);
            var yOpen = pToY(cd.open), yClose = pToY(cd.close);
            var up = cd.close >= cd.open;
            var color = up ? accent : '#e74c3c';
            PT_CTX.strokeStyle = color;
            PT_CTX.lineWidth = Math.max(1, Math.min(2, cw * 0.15));
            PT_CTX.beginPath(); PT_CTX.moveTo(cx, yHigh); PT_CTX.lineTo(cx, yLow); PT_CTX.stroke();
            var bodyTop = Math.min(yOpen, yClose);
            var bodyH   = Math.max(1, Math.abs(yClose - yOpen));
            PT_CTX.fillStyle = color;
            PT_CTX.fillRect(cx - half, bodyTop, cw, bodyH);
        }

        PT_CTX.fillStyle = textMut;
        PT_CTX.textAlign = 'center'; PT_CTX.textBaseline = 'top';
        PT_CTX.font = '10px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
        var labelStep = Math.max(1, Math.round(100 / step));
        var labelStart = Math.ceil(firstVisible / labelStep) * labelStep;
        for (var m = labelStart; m <= lastVisible; m += labelStep) {
            var cdx = baseX + m * step + step / 2;
            var tLabel = ptFormatTime(PT_CANDLES[m].time).slice(5, 16);
            PT_CTX.fillText(tLabel, cdx, chartBottom + 6);
        }

        var hasV = PT_HOVER_INDEX >= 0 && PT_HOVER_INDEX < PT_CANDLES.length;
        var hasH = PT_CURSOR_Y !== null && PT_CURSOR_Y >= chartTop && PT_CURSOR_Y <= chartBottom;
        var chColor = 'rgba(128,128,128,0.7)';

        if (hasV) {
            var hx = baseX + PT_HOVER_INDEX * step + step / 2;
            PT_CTX.strokeStyle = chColor; PT_CTX.lineWidth = 1;
            PT_CTX.setLineDash([3, 3]);
            PT_CTX.beginPath(); PT_CTX.moveTo(hx, chartTop); PT_CTX.lineTo(hx, chartBottom); PT_CTX.stroke();
            PT_CTX.setLineDash([]);
            var labelText = ptFormatTime(PT_CANDLES[PT_HOVER_INDEX].time).slice(5, 16);
            var ls = ptLabelStyles();
            PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'center';
            var padX = 8, textW = PT_CTX.measureText(labelText).width;
            var boxW  = textW + padX * 2, boxH = 20;
            var chipX = hx - boxW / 2;
            var chipY = (chartBottom + (viewportH - PT_PADDING_BOTTOM)) / 2 - boxH / 2;
            if (chipX < 0) chipX = 0;
            if (chipX + boxW > viewportW) chipX = viewportW - boxW;
            PT_CTX.fillStyle = ls.bg;
            ptRoundRect(PT_CTX, chipX, chipY, boxW, boxH, 4); PT_CTX.fill();
            PT_CTX.strokeStyle = ls.border; PT_CTX.lineWidth = 1; PT_CTX.stroke();
            PT_CTX.fillStyle = ls.bgText;
            PT_CTX.fillText(labelText, chipX + boxW / 2, chipY + boxH / 2 + 0.5);
        }
        if (hasH) {
            PT_CTX.strokeStyle = chColor; PT_CTX.lineWidth = 1;
            PT_CTX.setLineDash([3, 3]);
            PT_CTX.beginPath(); PT_CTX.moveTo(PT_PADDING_LEFT, PT_CURSOR_Y);
            PT_CTX.lineTo(viewportW - PT_PADDING_RIGHT, PT_CURSOR_Y); PT_CTX.stroke();
            PT_CTX.setLineDash([]);
            var priceVal = yToP(PT_CURSOR_Y);
            var priceText = ptFormatPrice(priceVal);
            var ls2 = ptLabelStyles();
            PT_CTX.font = '11px system-ui, -apple-system, Segoe UI, Roboto, sans-serif';
            PT_CTX.textBaseline = 'middle'; PT_CTX.textAlign = 'center';
            var padX2 = 6, boxH2 = 20;
            var textW2 = PT_CTX.measureText(priceText).width;
            var boxW2  = textW2 + padX2 * 2;
            var gutterLeft  = viewportW - PT_PADDING_RIGHT;
            var gutterWidth = PT_PADDING_RIGHT;
            var chipX2 = gutterLeft + Math.max(0, (gutterWidth - boxW2) / 2);
            if (chipX2 + boxW2 > viewportW - 2) chipX2 = viewportW - 2 - boxW2;
            var chipY2 = PT_CURSOR_Y - boxH2 / 2;
            if (chipY2 < 0) chipY2 = 0;
            if (chipY2 + boxH2 > viewportH) chipY2 = viewportH - boxH2;
            PT_CTX.fillStyle = ls2.bg;
            ptRoundRect(PT_CTX, chipX2, chipY2, boxW2, boxH2, 4); PT_CTX.fill();
            PT_CTX.strokeStyle = ls2.border; PT_CTX.lineWidth = 1; PT_CTX.stroke();
            PT_CTX.fillStyle = ls2.bgText;
            PT_CTX.fillText(priceText, chipX2 + boxW2 / 2, chipY2 + boxH2 / 2 + 0.5);
        }

        PT_CANVAS._ptStep     = step;
        PT_CANVAS._ptBaseX    = baseX;
        PT_CANVAS._ptChartTop = chartTop;

        PT_CANVAS._ptChartBot = chartBottom;

        // Heavy matcher/projection work is intentionally excluded from active gesture
        // frames. This keeps pointer movement smooth; the complete overlay is restored
        // once the gesture has been idle for 140ms.
        if (!PT_FAST_INTERACTION) {
            ptApplyTrainingOverlay({
                ctx: PT_CTX, baseX: baseX, step: step, candleWidth: cw,
                firstVisible: firstVisible, lastVisible: lastVisible,
                pToY: pToY, chartTop: chartTop, chartBottom: chartBottom,
                viewportW: viewportW, viewportH: viewportH
            });

            // Animated trade locator is deliberately drawn last, above candles and drawings.
            ptDrawLocateArrow({
                ctx: PT_CTX, baseX: baseX, step: step, candleWidth: cw,
                pToY: pToY, chartTop: chartTop, chartBottom: chartBottom,
                viewportW: viewportW, viewportH: viewportH
            });
        }

        ptUpdateJumpLatestVisibility();
    }

    // ==================== VIRTUAL PROGRAMME MATCHING ====================
    // Results exist only in memory/localStorage. No matched candle rows are written to MySQL.
    var PT_VIRTUAL_MATCHES = [];
    var PT_VIRTUAL_CANDLE_JSON_KEY = 'pt_virtual_candles_v1';
    var PT_VIRTUAL_CONFIG_KEY = 'pt_virtual_config_v1';

    function ptNormTfList(root) {
        var tfs = Array.isArray(root.timeframes) ? root.timeframes : [];
        if (!tfs.length && root.timeframe) {
            try {
                var parsed = JSON.parse(root.timeframe);
                tfs = Array.isArray(parsed) ? parsed : [root.timeframe];
            } catch (_) { tfs = String(root.timeframe).split(','); }
        }
        return tfs.map(function (x) { return String(x).trim().toLowerCase(); }).filter(Boolean);
    }
    function ptCandleType(c) {
        return Number(c.close) > Number(c.open) ? 'bullish'
             : Number(c.close) < Number(c.open) ? 'bearish' : 'bearish_or_bullish';
    }
    function ptTypeMatches(c, type) {
        return !type || type === 'bearish_or_bullish' || ptCandleType(c) === type;
    }
    function ptPrice(c, level) {
        if (!c) return null;
        var open = Number(c.open), close = Number(c.close), high = Number(c.high), low = Number(c.low);
        switch (String(level || 'high').toLowerCase()) {
            case 'open': return open;
            case 'close': return close;
            case 'low': return low;
            case 'candle_center': return c.candle_center != null ? Number(c.candle_center) : (high + low) / 2;
            case 'body_center': return c.body_center != null ? Number(c.body_center) : (open + close) / 2;
            case 'high_wick_center': return c.high_wick_center != null ? Number(c.high_wick_center) : (high + Math.max(open, close)) / 2;
            case 'low_wick_center': return c.low_wick_center != null ? Number(c.low_wick_center) : (low + Math.min(open, close)) / 2;
            default: return high;
        }
    }
    function ptCompare(a, op, b) {
        if (a == null || b == null || !isFinite(a) || !isFinite(b)) return false;
        switch (op) {
            case '<': return a < b;
            case '>': return a > b;
            case '=': case '==': return a === b;
            case '<=': case '=<': return a <= b;
            case '>=': return a >= b;
            case '<>': case '!=': return a !== b;
            default: return a < b;
        }
    }
    function ptFindNamedCandle(tree, name, role) {
        if (!tree) return null;
        var main = tree.main || {}, ref = tree.reference || {};
        if (role === 'main') return main.name === name ? main : null;
        if (role === 'reference') return ref.name === name ? ref : null;
        if (main.name === name) return main;
        if (ref.name === name) return ref;
        return null;
    }
    function ptOffsetIndex(index, side, distance, length) {
        var step = Math.max(1, Number(distance) || 1);
        var target = index + (side === 'right' ? step : -step);
        return target >= 0 && target < length ? target : -1;
    }
    function ptApplyTrainingOverlay(geo) {
        PT_VIRTUAL_MATCHES = [];
        if (!Array.isArray(PT_CANDLES) || !PT_CANDLES.length) return;
        var config = (typeof window.ptGetTrainingConfiguration === 'function')
            ? window.ptGetTrainingConfiguration() : null;
        if (!config || !Array.isArray(config.roots)) return;

        var all = PT_CANDLES.map(function (c, i) {
            return Object.assign({}, c, {
                _index: i, _rootTrees: [], _conditionsMet: [],
                _matchRootIds: [], _matchTreeIds: []
            });
        });
        var activeRoots = [];
        config.roots.forEach(function (root) {
            // Disabled roots are configuration-only: they contribute no matches,
            // annotations, drawings, chart snapshot root IDs, or trade records.
            if (root.applyToChart === false || root.applyToChart === 0 || root.applyToChart === '0') {
                root._drawMatches = [];
                return;
            }
            var tfs = ptNormTfList(root);
            if (!tfs.includes(String(PT_CURRENT_TIMEFRAME).toLowerCase())) return;
            activeRoots.push(root);

            // Each configured candle has one canonical identity. Trees may change its role,
            // but may not search for a different chart candle with the same display name.
            var chainStates = [];
            var configuredTrees = (root.trees || []).slice().sort(function (a, b) { return Number(a.id) - Number(b.id); });
            var processedTreeCount = 0;
            configuredTrees.forEach(function (tree, treeIndex) {
                var main = tree.main || {}, ref = tree.reference || {};
                if (!main.name || !ref.name) { chainStates = []; return; }
                processedTreeCount++;
                var mainKey = String(main.selectedExistingId || main.offsetTo || main.id);
                var isNewRef = ref.referenceMode === 'new_offset';
                var refKey = String(!isNewRef && ref.selectedExistingId
                    ? ref.selectedExistingId
                    : (!isNewRef && ref.existingCandleId ? ref.existingCandleId : ref.id));
                var candidates = [];
                var nextStates = [];

                if (treeIndex === 0) {
                    all.forEach(function (mainCandle, mainIdx) {
                        if (!ptTypeMatches(mainCandle, main.mainCandleType)) return;
                        var refIdx = ptOffsetIndex(mainIdx, ref.position || 'right', ref.offsetPosition || 1, all.length);
                        if (refIdx < 0 || !all[refIdx] || !ptTypeMatches(all[refIdx], ref.refCandleType)) return;
                        var leftValue = ptPrice(mainCandle, main.priceLevel || 'high');
                        var rightValue = ptPrice(all[refIdx], ref.priceLevel || 'high');
                        if (!ptCompare(leftValue, main.operator || '<', rightValue)) return;
                        var map = {};
                        map[mainKey] = mainIdx;
                        map[refKey] = refIdx;
                        candidates.push(mainIdx);
                        nextStates.push({ map: map, mainIndex: mainIdx, refIndex: refIdx, conditions: [{
                            rootId: root.id, treeId: tree.id, mainName: main.name, referenceName: ref.name,
                            mainCandleId: mainCandle.id, referenceCandleId: all[refIdx].id,
                            offsetCandleId: isNewRef ? all[refIdx].id : null,
                            mainIdentity: mainKey, referenceIdentity: refKey,
                            operator: main.operator || '<', mainPriceLevel: main.priceLevel || 'high',
                            referencePriceLevel: ref.priceLevel || 'high', mainValue: leftValue, referenceValue: rightValue
                        }] });
                    });
                } else {
                    chainStates.forEach(function (state) {
                        // A later tree's main must resolve to the exact identity selected earlier.
                        var mainIdx = Object.prototype.hasOwnProperty.call(state.map, mainKey) ? state.map[mainKey] : -1;
                        if (mainIdx < 0 || !all[mainIdx] || !ptTypeMatches(all[mainIdx], main.mainCandleType)) return;
                        var refIdx = -1;
                        if (isNewRef) {
                            refIdx = ptOffsetIndex(mainIdx, ref.position || 'left', ref.offsetPosition || 1, all.length);
                        } else if (Object.prototype.hasOwnProperty.call(state.map, refKey)) {
                            // Existing reference points at the same candle already defined in the chain.
                            refIdx = state.map[refKey];
                        }
                        if (refIdx < 0 || !all[refIdx] || !ptTypeMatches(all[refIdx], ref.refCandleType)) return;
                        var leftValue = ptPrice(all[mainIdx], main.priceLevel || 'high');
                        var rightValue = ptPrice(all[refIdx], ref.priceLevel || 'high');
                        if (!ptCompare(leftValue, main.operator || '<', rightValue)) return;
                        var map = Object.assign({}, state.map);
                        if (isNewRef) map[refKey] = refIdx;
                        candidates.push(mainIdx);
                        nextStates.push({ map: map, mainIndex: mainIdx, refIndex: refIdx, conditions: (state.conditions || []).concat([{
                            rootId: root.id, treeId: tree.id, mainName: main.name, referenceName: ref.name,
                            mainCandleId: all[mainIdx].id, referenceCandleId: all[refIdx].id,
                            offsetCandleId: isNewRef ? all[refIdx].id : null,
                            mainIdentity: mainKey, referenceIdentity: refKey,
                            operator: main.operator || '<', mainPriceLevel: main.priceLevel || 'high',
                            referencePriceLevel: ref.priceLevel || 'high', mainValue: leftValue, referenceValue: rightValue
                        }]) });
                    });
                }
                chainStates = nextStates;
            });
            // A root is atomic: every configured tree must be evaluated and pass.
            // Do not publish partial conditions, labels, or drawings for incomplete families.
            var rootTrees = configuredTrees;
            var completeStates = (rootTrees.length > 0 && processedTreeCount === rootTrees.length)
                ? chainStates.filter(function (state) {
                    return Array.isArray(state.conditions) && state.conditions.length === rootTrees.length;
                }) : [];
            completeStates.forEach(function (state) {
                (state.conditions || []).forEach(function (condition) {
                    var mainIdx = all.findIndex(function (c) { return String(c.id) === String(condition.mainCandleId); });
                    if (mainIdx < 0) return;
                    all[mainIdx]._rootTrees.push({ rootId: root.id, treeId: condition.treeId });
                    all[mainIdx]._conditionsMet.push(condition);
                    all[mainIdx]._matchRootIds.push(root.id);
                    all[mainIdx]._matchTreeIds.push(condition.treeId);
                });
            });
            root._drawMatches = completeStates.map(function (state) {
                return { map: Object.assign({}, state.map) };
            });
            // Report tree matches only when they belong to a complete 100% root family.
            rootTrees.forEach(function (tree) {
                var indices = [];
                completeStates.forEach(function (state) {
                    (state.conditions || []).forEach(function (condition) {
                        if (String(condition.treeId) !== String(tree.id)) return;
                        var idx = all.findIndex(function (c) { return String(c.id) === String(condition.mainCandleId); });
                        if (idx >= 0) indices.push(idx);
                    });
                });
                PT_VIRTUAL_MATCHES.push({
                    rootId: root.id, treeId: tree.id, timeframe: PT_CURRENT_TIMEFRAME,
                    symbol: PT_CURRENT_SYMBOL, candleIndices: Array.from(new Set(indices))
                });
            });
        });

        // JSON snapshot is browser-local and is scoped by programme, symbol and timeframe.
        var snapshotKey = PT_VIRTUAL_CANDLE_JSON_KEY + ':' + PT_PROGRAMME_ID + ':' +
            PT_CURRENT_SYMBOL + ':' + PT_CURRENT_TIMEFRAME;
        try {
            localStorage.setItem(snapshotKey, JSON.stringify({
                programmeId: PT_PROGRAMME_ID, symbol: PT_CURRENT_SYMBOL,
                timeframe: PT_CURRENT_TIMEFRAME, generatedAt: new Date().toISOString(),
                rootsApplied: activeRoots.map(function (r) { return r.id; }),
                candles: all
            }));
            localStorage.setItem(PT_VIRTUAL_CONFIG_KEY + ':' + PT_PROGRAMME_ID, JSON.stringify(config));
        } catch (storageError) {
            console.warn('Virtual candle JSON could not be saved in browser storage:', storageError);
        }

        var ctx = geo.ctx;

        // Higher-timeframe candle direction guides:
        // During a projection pass, draw one independent dashed segment for every
        // candle identity defined by each complete root family. Never join different
        // candles or families. Bullish HTF candle: first lower candle LOW -> last
        // lower candle HIGH. Bearish HTF candle: first lower candle HIGH -> last
        // lower candle LOW. This is projection-only and does not affect normal charts.
        if (PT_PROJECTION_RENDER_PASS && PT_PROJECTION_MAP && PT_PROJECTION_LOWER_CANDLES) {
            activeRoots.forEach(function (root) {
                (root._drawMatches || []).forEach(function (family) {
                    var seenIndices = Object.create(null);
                    Object.keys(family.map || {}).forEach(function (identity) {
                        var sourceIndex = Number(family.map[identity]);
                        if (!Number.isInteger(sourceIndex) || sourceIndex < 0 ||
                            sourceIndex >= PT_CANDLES.length || seenIndices[sourceIndex]) return;
                        seenIndices[sourceIndex] = true;

                        var piece = PT_PROJECTION_MAP[sourceIndex];
                        if (!piece || piece.first < 0 || piece.last < 0) return;
                        var firstLower = PT_PROJECTION_LOWER_CANDLES[piece.first];
                        var lastLower = PT_PROJECTION_LOWER_CANDLES[piece.last];
                        var sourceCandle = PT_CANDLES[sourceIndex];
                        if (!firstLower || !lastLower || !sourceCandle) return;

                        var sourceOpen = Number(sourceCandle.open);
                        var sourceClose = Number(sourceCandle.close);
                        if (!Number.isFinite(sourceOpen) || !Number.isFinite(sourceClose)) return;
                        var bullish = sourceClose >= sourceOpen;

                        var x1 = geo.baseX + piece.first * geo.step + geo.step / 2;
                        var x2 = geo.baseX + piece.last * geo.step + geo.step / 2;
                        var startPrice = Number(bullish ? firstLower.low : firstLower.high);
                        var endPrice = Number(bullish ? lastLower.high : lastLower.low);
                        if (!Number.isFinite(x1) || !Number.isFinite(x2) ||
                            !Number.isFinite(startPrice) || !Number.isFinite(endPrice)) return;
                        if (Math.max(x1, x2) < -100 || Math.min(x1, x2) > geo.viewportW + 100) return;

                        ctx.save();
                        ctx.strokeStyle = '#888888';
                        ctx.lineWidth = 0.8;
                        ctx.setLineDash([3, 3]);
                        ctx.beginPath();
                        ctx.moveTo(x1, geo.pToY(startPrice));
                        ctx.lineTo(x2, geo.pToY(endPrice));
                        ctx.stroke();
                        ctx.setLineDash([]);
                        ctx.restore();
                    });
                });
            });
        }

        // Render only user-configured annotation text; no circles or hardcoded labels.
        activeRoots.forEach(function (root) {
            (root.trees || []).forEach(function (tree, treeIndex) {
                var main = tree.main || {}, ref = tree.reference || {};
                var matched = all.filter(function (c) {
                    return c._conditionsMet.some(function (m) { return m.rootId === root.id && m.treeId === tree.id; });
                });
                matched.forEach(function (c) {
                    var condition = c._conditionsMet.find(function (m) { return m.rootId === root.id && m.treeId === tree.id; });
                    if (!condition) return;
                    var isMainReference = main.selectedExistingId || main.offsetTo;
                    var label = (main.candleText || '').trim();
                    var labelLevel = main.textPosition || 'high';
                    if (label && !isMainReference) {
                        var mainIdx = c._index;
                        var mx = ptOverlayX(geo, mainIdx, labelLevel);
                        var my = geo.pToY(PT_PROJECTION_RENDER_PASS ? ptProjectionPrice(mainIdx, labelLevel, all) : ptPrice(all[mainIdx], labelLevel === 'low' ? 'low' : 'high'));
                        ctx.save(); ctx.fillStyle = ptResolveConfiguredColor(main.textColor, 'purple'); ctx.font = 'bold 11px system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        // Canvas text baseline: for HIGH, text bottom sits 2px above the high;
                        // for LOW, text top sits 2px below the low.
                        ctx.textBaseline = labelLevel === 'low' ? 'top' : 'bottom';
                        ctx.fillText(label, mx, labelLevel === 'low' ? my + 2 : my - 2); ctx.restore();
                    }
                    var isTextBearingOffset = ref.referenceMode === 'new_offset' || (tree.offset && tree.offset.id === ref.id);
                    if ((treeIndex === 0 || isTextBearingOffset) && (ref.candleText || (tree.offset && tree.offset.candleText) || '').trim()) {
                        var refIdx = all.findIndex(function (z) { return z.id === condition.referenceCandleId; });
                        if (refIdx >= 0) {
                            var refLabelPosition = (ref.textPosition || 'high') === 'low' ? 'low' : 'high';
                            var rx = ptOverlayX(geo, refIdx, refLabelPosition);
                            var ry = geo.pToY(PT_PROJECTION_RENDER_PASS ? ptProjectionPrice(refIdx, refLabelPosition, all) : ptPrice(all[refIdx], refLabelPosition));
                            ctx.save(); ctx.fillStyle = ptResolveConfiguredColor(ref.textColor || (tree.offset && tree.offset.textColor), 'purple'); ctx.font = 'bold 11px system-ui, sans-serif';
                            ctx.textAlign = 'center'; ctx.textBaseline = refLabelPosition === 'low' ? 'top' : 'bottom';
                            ctx.fillText(String(ref.candleText || (tree.offset && tree.offset.candleText) || '').trim(), rx, refLabelPosition === 'low' ? ry + 2 : ry - 2); ctx.restore();
                        }
                    }
                });
            });
        });

        // Drawings are root-scoped, not tree-scoped. Resolve both endpoints against
        // each complete root match so Tree 1 can connect to Tree 3 (or any other tree).
        activeRoots.forEach(function (root) {
            var trees = (root.trees || []);
            function endpointIdentity(parts) {
                if (!parts || parts.length < 3) return null;
                var treeId = String(parts[0]);
                var kind = String(parts[1] || 'main');
                var suppliedId = String(parts[2]);
                var tree = trees.find(function (t) { return String(t.id) === treeId; });
                if (!tree) return suppliedId;
                var candle = kind === 'main' ? (tree.main || {}) :
                    (kind === 'reference' ? (tree.reference || {}) :
                    ((tree.offset || (tree.reference && tree.reference.referenceMode === 'new_offset') ? (tree.offset || tree.reference) : {})));
                if (!candle || !candle.id) return suppliedId;

                // Accept both current canonical identities and older saved option values.
                if (kind === 'main') return String(candle.selectedExistingId || candle.offsetTo || candle.id);
                if (kind === 'reference') return String(
                    candle.referenceMode !== 'new_offset' && (candle.selectedExistingId || candle.existingCandleId)
                        ? (candle.selectedExistingId || candle.existingCandleId) : candle.id
                );
                return String(
                    candle.referenceMode === 'new_offset'
                        ? candle.id
                        : (candle.selectedExistingId || candle.existingCandleId || candle.id)
                );
            }

            (root.drawings || []).forEach(function (drawing) {
                if (drawing.drawFor === 'root_confirmation') return;
                var fromName = drawing.drawFrom || '', toName = drawing.drawTo || '';
                if (!fromName || !toName) return;

                var fromParts = fromName.split('|');
                var toParts = toName.split('|');
                var fromTreeId = fromParts.length >= 3 ? String(fromParts[0]) : null;
                var toTreeId = toParts.length >= 3 ? String(toParts[0]) : null;
                var fromKey = endpointIdentity(fromParts);
                var toKey = endpointIdentity(toParts);
                var rootMatches = Array.isArray(root._drawMatches) ? root._drawMatches : [];

                rootMatches.forEach(function (match) {
                    var map = match.map || {};
                    var fromIdx = fromKey != null && Object.prototype.hasOwnProperty.call(map, fromKey)
                        ? map[fromKey] : -1;
                    var toIdx = -1;

                    if (fromIdx < 0 || !all[fromIdx]) return;

                    var fromLevel = drawing.drawFromPriceLevel || 'high';
                    var sourcePrice = PT_PROJECTION_RENDER_PASS ? ptProjectionPrice(fromIdx, fromLevel, all) : ptPrice(all[fromIdx], fromLevel);
                    var x1 = ptOverlayX(geo, fromIdx, fromLevel);
                    var y1 = geo.pToY(sourcePrice);

                    if (toName === 'any_intercept_candle') {
                        /*
                         * "Any intercept candle" means a horizontal ray at the
                         * selected source price. Start with the candle immediately
                         * AFTER the source candle and stop at the first candle whose
                         * full high-low range contains that price. If no candle
                         * intersects the ray, do not draw anything.
                         */
                        for (var scanIdx = fromIdx + 1; scanIdx < all.length; scanIdx++) {
                            var candidate = all[scanIdx];
                            if (!candidate) continue;

                            var candleHigh = Number(candidate.high);
                            var candleLow = Number(candidate.low);
                            if (!Number.isFinite(candleHigh) || !Number.isFinite(candleLow)) continue;

                            if (sourcePrice >= candleLow && sourcePrice <= candleHigh) {
                                toIdx = scanIdx;
                                break;
                            }
                        }
                        if (toIdx < 0 || !all[toIdx]) return;
                    } else if (toKey != null && Object.prototype.hasOwnProperty.call(map, toKey)) {
                        toIdx = map[toKey];
                    }

                    if (toIdx < 0 || !all[toIdx]) return;
                    var toLevel = drawing.drawToPriceLevel || 'high';
                    var x2 = ptOverlayX(geo, toIdx, toName === 'any_intercept_candle' ? 'center' : toLevel);
                    // Projected endpoints snap to the exact lower-timeframe candle/price.
                    var y2 = toName === 'any_intercept_candle' ? y1 : (drawing.drawToPriceLevel === 'axis' ? y1 : geo.pToY(PT_PROJECTION_RENDER_PASS ? ptProjectionPrice(toIdx, toLevel, all) : ptPrice(all[toIdx], toLevel)));
                    if (x1 < -100 || x1 > geo.viewportW + 100 || x2 < -100 || x2 > geo.viewportW + 100) return;
                    ctx.save();
                    ctx.strokeStyle = ptResolveConfiguredColor(drawing.drawingColor, 'blue');
                    ctx.lineWidth = 1.6;
                    ctx.beginPath();
                    ctx.moveTo(x1, y1);
                    ctx.lineTo(x2, y2);
                    ctx.stroke();
                    ctx.restore();
                });
            });
        });

        // Confirmation runtime: evaluate each confirmation pattern on the lower-timeframe
        // candles at the exact parent-family anchor timestamp. These matches are kept separate
        // from parent-root matches and are never allowed to satisfy a parent root's own trees.
        if (PT_PROJECTION_RENDER_PASS && PT_PROJECTION_MAP && Array.isArray(PT_PROJECTION_LOWER_CANDLES)) {
            var lowerCandlesForConfirmation = PT_PROJECTION_LOWER_CANDLES;
            var lowerWrappers = lowerCandlesForConfirmation.map(function(c, i) {
                return Object.assign({}, c, {_index:i, _rootTrees:[], _conditionsMet:[], _matchRootIds:[], _matchTreeIds:[]});
            });
            var targetTf = String(PT_PROJECTION_TARGET_TIMEFRAME || '').toLowerCase();
            function timeMs(v) {
                if (v == null || v === '') return NaN;
                if (typeof v === 'number') return v < 1e12 ? v * 1000 : v;
                var n = Number(v);
                if (Number.isFinite(n) && String(v).trim() !== '') return n < 1e12 ? n * 1000 : n;
                var parsed = Date.parse(String(v).replace(' ', 'T') + (String(v).includes('Z') || /[+-]\d\d:?\d\d$/.test(String(v)) ? '' : 'Z'));
                return Number.isFinite(parsed) ? parsed : NaN;
            }
            function confirmationCanonical(tree, kind) {
                var c = kind === 'main' ? (tree.main || {}) : kind === 'reference' ? (tree.reference || {}) : (tree.offset || tree.reference || {});
                if (kind === 'main') return String(c.selectedExistingId || c.offsetTo || c.id || '');
                if (kind === 'reference') return String(c.referenceMode !== 'new_offset' && (c.selectedExistingId || c.existingCandleId) ? (c.selectedExistingId || c.existingCandleId) : (c.id || ''));
                return String(c.referenceMode === 'new_offset' ? c.id : (c.selectedExistingId || c.existingCandleId || c.id || ''));
            }
            function matchConfirmationAt(root, anchorIdx, parentFamily) {
                var cr = root.confirmationRoot;
                if (!cr || !cr.trees || !cr.trees.length || anchorIdx < 0 || anchorIdx >= lowerWrappers.length) return null;
                var states = [], configured = cr.trees.slice().sort(function(a,b){return Number(a.id)-Number(b.id);});
                for (var ti=0; ti<configured.length; ti++) {
                    var tree = configured[ti], main = tree.main || {}, ref = tree.reference || {};
                    if (!main.name || !ref.name) return null;
                    var mainKey = confirmationCanonical(tree, 'main');
                    var isNewRef = ref.referenceMode === 'new_offset';
                    var refKey = confirmationCanonical(tree, 'reference');
                    var next = [];
                    if (ti === 0) {
                        var mi = anchorIdx;
                        var ri = ptOffsetIndex(mi, ref.position || 'right', ref.offsetPosition || 1, lowerWrappers.length);
                        if (ptTypeMatches(lowerWrappers[mi], main.mainCandleType) && ri >= 0 && ptTypeMatches(lowerWrappers[ri], ref.refCandleType) &&
                            ptCompare(ptPrice(lowerWrappers[mi], main.priceLevel || 'high'), main.operator || '<', ptPrice(lowerWrappers[ri], ref.priceLevel || 'high'))) {
                            var firstMap = {}; firstMap[mainKey] = mi; firstMap[refKey] = ri;
                            next.push({map:firstMap, conditions:[{rootId:cr.id,treeId:tree.id,mainName:main.name,referenceName:ref.name,mainCandleId:lowerWrappers[mi].id,referenceCandleId:lowerWrappers[ri].id,mainIdentity:mainKey,referenceIdentity:refKey}]});
                        }
                    } else {
                        states.forEach(function(st) {
                            var mi = Object.prototype.hasOwnProperty.call(st.map, mainKey) ? st.map[mainKey] : -1;
                            if (mi < 0 || !lowerWrappers[mi] || !ptTypeMatches(lowerWrappers[mi], main.mainCandleType)) return;
                            var ri = -1;
                            if (isNewRef) ri = ptOffsetIndex(mi, ref.position || 'left', ref.offsetPosition || 1, lowerWrappers.length);
                            else if (Object.prototype.hasOwnProperty.call(st.map, refKey)) ri = st.map[refKey];
                            if (ri < 0 || !lowerWrappers[ri] || !ptTypeMatches(lowerWrappers[ri], ref.refCandleType)) return;
                            if (!ptCompare(ptPrice(lowerWrappers[mi], main.priceLevel || 'high'), main.operator || '<', ptPrice(lowerWrappers[ri], ref.priceLevel || 'high'))) return;
                            var m = Object.assign({}, st.map); if (isNewRef) m[refKey] = ri;
                            next.push({map:m, conditions:st.conditions.concat([{rootId:cr.id,treeId:tree.id,mainName:main.name,referenceName:ref.name,mainCandleId:lowerWrappers[mi].id,referenceCandleId:lowerWrappers[ri].id,mainIdentity:mainKey,referenceIdentity:refKey}])});
                        });
                    }
                    states = next;
                    if (!states.length) return null;
                }
                if (!states.length || states.some(function(st){return st.conditions.length !== configured.length;})) return null;
                return states.map(function(st){return {map:st.map, anchorIndex:anchorIdx, parentFamily:parentFamily, conditions:st.conditions};});
            }
            config.roots.forEach(function(root) {
                root._confirmationDrawMatches = [];
                if (root.applyToChart === false || root.applyToChart === 0 || root.applyToChart === '0') return;
                var cr = root.confirmationRoot;
                var crTfs = cr && Array.isArray(cr.timeframes) ? cr.timeframes.map(function(v){return String(v).toLowerCase();}) : [];
                if (!cr || !cr.enabled || !crTfs.includes(targetTf) || !cr.trees || !cr.trees.length) return;
                var anchorKey = String(cr.anchorCandleId || '');
                if (!anchorKey) return;
                (root._drawMatches || []).forEach(function(parentFamily) {
                    var parentMap = parentFamily.map || {};
                    if (!Object.prototype.hasOwnProperty.call(parentMap, anchorKey)) return;
                    var parentSourceIndex = Number(parentMap[anchorKey]);
                    var piece = PT_PROJECTION_MAP[parentSourceIndex];
                    if (!piece || piece.first < 0 || piece.last < 0) return;
                    var parentCandle = PT_CANDLES[parentSourceIndex];
                    if (!parentCandle) return;
                    var stamp = cr.anchorTime === 'close_time' ? (parentCandle.close_time || parentCandle.closeTime || parentCandle.time) : (parentCandle.open_time || parentCandle.openTime || parentCandle.time);
                    var anchorMs = timeMs(stamp);
                    if (!Number.isFinite(anchorMs)) return;
                    var anchorIndex = -1;
                    for (var li=0; li<lowerCandlesForConfirmation.length; li++) {
                        var lowerStamp = lowerCandlesForConfirmation[li].open_time || lowerCandlesForConfirmation[li].openTime || lowerCandlesForConfirmation[li].time;
                        var lowerMs = timeMs(lowerStamp);
                        if (Number.isFinite(lowerMs) && lowerMs >= anchorMs) { anchorIndex = li; break; }
                    }
                    if (anchorIndex < 0) return;
                    var found = matchConfirmationAt(root, anchorIndex, parentFamily);
                    if (found) root._confirmationDrawMatches = root._confirmationDrawMatches.concat(found);
                });

                // Render confirmation labels and drawings in native lower-timeframe coordinates.
                var directX = function(idx){ return geo.baseX + idx * geo.step + geo.step / 2; };
                (root._confirmationDrawMatches || []).forEach(function(family) {
                    (family.conditions || []).forEach(function(cond) {
                        var tree = (cr.trees || []).find(function(t){return String(t.id)===String(cond.treeId);});
                        if (!tree) return;
                        var main = tree.main || {}, ref = tree.reference || {};
                        var mi = family.map[confirmationCanonical(tree,'main')];
                        var ri = family.map[confirmationCanonical(tree,'reference')];
                        if (mi != null && main.candleText) {
                            ctx.save(); ctx.fillStyle=ptResolveConfiguredColor(main.textColor,'purple'); ctx.font='bold 11px system-ui,sans-serif'; ctx.textAlign='center';
                            var ml=main.textPosition==='low'?'low':'high'; ctx.textBaseline=ml==='low'?'top':'bottom';
                            var my=geo.pToY(ptPrice(lowerWrappers[mi],ml)); ctx.fillText(String(main.candleText),directX(mi),ml==='low'?my+2:my-2); ctx.restore();
                        }
                        if (ri != null && (ref.candleText || (tree.offset && tree.offset.candleText))) {
                            var rl=ref.textPosition==='low'?'low':'high'; ctx.save(); ctx.fillStyle=ptResolveConfiguredColor(ref.textColor || (tree.offset&&tree.offset.textColor),'purple'); ctx.font='bold 11px system-ui,sans-serif'; ctx.textAlign='center'; ctx.textBaseline=rl==='low'?'top':'bottom';
                            var ry=geo.pToY(ptPrice(lowerWrappers[ri],rl)); ctx.fillText(String(ref.candleText || tree.offset.candleText),directX(ri),rl==='low'?ry+2:ry-2); ctx.restore();
                        }
                    });
                });
                function endpointKey(encoded) {
                    var parts=String(encoded||'').split('|'); if(parts.length<3) return null;
                    var tree=(cr.trees||[]).find(function(t){return String(t.id)===String(parts[0]);}); if(!tree) return String(parts[2]);
                    return confirmationCanonical(tree,parts[1]);
                }
                (cr.drawings || []).concat((root.drawings || []).filter(function(d){return d.drawFor === 'root_confirmation';})).forEach(function(drawing) {
                    if (drawing.drawFor && drawing.drawFor !== 'root_confirmation') return;
                    var fk=endpointKey(drawing.drawFrom), tk=endpointKey(drawing.drawTo);
                    if (!fk || !tk) return;
                    (root._confirmationDrawMatches || []).forEach(function(family) {
                        var fi=family.map[fk], ti=family.map[tk]; if(fi==null) return;
                        var from=lowerWrappers[fi], to=lowerWrappers[ti]; if(!from) return;
                        if (drawing.drawTo === 'any_intercept_candle') {
                            var price=ptPrice(from,drawing.drawFromPriceLevel||'high'); ti=-1;
                            for(var si=fi+1;si<lowerWrappers.length;si++){if(Number(lowerWrappers[si].low)<=price&&Number(lowerWrappers[si].high)>=price){ti=si;break;}}
                            if(ti<0) return; to=lowerWrappers[ti];
                        }
                        if(!to) return;
                        var fp=ptPrice(from,drawing.drawFromPriceLevel||'high');
                        var tp=drawing.drawTo==='any_intercept_candle'?fp:ptPrice(to,drawing.drawToPriceLevel||'high');
                        var x1=directX(fi), x2=directX(ti); if(Math.max(x1,x2)<-100||Math.min(x1,x2)>geo.viewportW+100)return;
                        ctx.save();ctx.strokeStyle=ptResolveConfiguredColor(drawing.drawingColor,'blue');ctx.lineWidth=1.4;ctx.beginPath();ctx.moveTo(x1,geo.pToY(fp));ctx.lineTo(x2,geo.pToY(tp));ctx.stroke();ctx.restore();
                    });
                });

                // Confirmation trades are evaluated against lower candles, not the HTF source
                // array. Their records are kept separate and are merged into Roots Data below.
                if (root.tradeFor === 'root_confirmation' && root.useForTrades && crTfs.includes(targetTf)) {
                    var previousTf = PT_CURRENT_TIMEFRAME;
                    PT_CURRENT_TIMEFRAME = targetTf;
                    var confirmationTrades = ptBuildRootTradeData([root], lowerCandlesForConfirmation).filter(function(t){return t.timeframe===targetTf;});
                    PT_CURRENT_TIMEFRAME = previousTf;
                    confirmationTrades.forEach(function(t){
                        t.dataMode='confirmation'; t.sourceTimeframe=String(PT_CURRENT_TIMEFRAME).toLowerCase();
                        t.timeframe=targetTf; t.confirmationAnchor=cr.anchorCandleName || String(cr.anchorCandleId);
                        PT_CONFIRMATION_TRADE_DATA.push(t);
                        // Draw risk/reward zones directly against lower candle indexes.
                        if(t.entryIndex==null || !lowerCandlesForConfirmation[t.entryIndex]) return;
                        var endIdx=t.outcome==='take profit reached'?t.tpTriggerIndex:(t.outcome==='stoploss hit'?t.slTriggerIndex:(t.triggerIndex!=null?t.triggerIndex:lowerCandlesForConfirmation.length-1));
                        if(endIdx==null || endIdx<0) endIdx=lowerCandlesForConfirmation.length-1;
                        var bx=directX(t.entryIndex)+Number(PT_VIEW.candleWidth||5)/2, ex=directX(endIdx)+Number(PT_VIEW.candleWidth||5)/2;
                        var ey=geo.pToY(t.entryPrice), sy=geo.pToY(t.slPrice), ty=geo.pToY(t.tpPrice);
                        ctx.save();ctx.strokeStyle='#f2c94c';ctx.lineWidth=1.2;ctx.setLineDash([5,3]);ctx.beginPath();ctx.moveTo(bx,ey);ctx.lineTo(ex,ey);ctx.stroke();ctx.setLineDash([]);
                        ctx.fillStyle='rgba(235,64,75,.18)';ctx.fillRect(bx,Math.min(ey,sy),Math.max(1,ex-bx),Math.max(1,Math.abs(sy-ey)));
                        ctx.fillStyle='rgba(46,204,113,.18)';ctx.fillRect(bx,Math.min(ey,ty),Math.max(1,ex-bx),Math.max(1,Math.abs(ty-ey)));ctx.restore();
                    });
                }
            });
        }

        // Trade visualization and deterministic family-level trade records.
        PT_ROOT_TRADE_DATA = ptBuildRootTradeData(config.roots, PT_CANDLES);
        var tradeCandleWidth = Number(PT_VIEW.candleWidth || 5);
        PT_ROOT_TRADE_DATA.forEach(function (trade) {
            if (trade.timeframe !== String(PT_CURRENT_TIMEFRAME).toLowerCase()) return;
            if (trade.entryIndex == null) return;
            // Start exactly at the RIGHT edge of the configured entry candle (candle A).
            // End at the right edge of the SL/TP exit candle when closed; otherwise at
            // the entry-trigger candle, or the latest loaded candle while still pending.
            var firstTradeIndex = trade.entryIndex;
            if (firstTradeIndex < 0 || firstTradeIndex >= PT_CANDLES.length) return;
            var firstTradeCenterX = ptOverlayX(geo, firstTradeIndex);
            var boxStart = firstTradeCenterX + tradeCandleWidth / 2;
            var exitIndex = trade.outcome === 'take profit reached' ? trade.tpTriggerIndex :
                (trade.outcome === 'stoploss hit' ? trade.slTriggerIndex : null);
            var endIndex = exitIndex != null ? exitIndex :
                (trade.triggerIndex != null ? trade.triggerIndex : PT_CANDLES.length - 1);
            if (endIndex < firstTradeIndex) endIndex = firstTradeIndex;
            var endCenterX = ptOverlayX(geo, endIndex);
            var endBodyRightX = endCenterX + tradeCandleWidth / 2;
            var boxWidth = Math.max(1, endBodyRightX - boxStart);
            var entryY = geo.pToY(trade.entryPrice);
            var slY = geo.pToY(trade.slPrice);
            var tpY = geo.pToY(trade.tpPrice);
            if (Math.max(boxStart, endBodyRightX) < -100 || Math.min(boxStart, endBodyRightX) > geo.viewportW + 100) return;
            ctx.save();
            // The entry-price guide begins at the configured entry candle's right body edge.
            ctx.strokeStyle = '#f2c94c'; ctx.lineWidth = 1.5; ctx.setLineDash([5,3]);
            ctx.beginPath(); ctx.moveTo(boxStart, entryY); ctx.lineTo(endBodyRightX, entryY); ctx.stroke(); ctx.setLineDash([]);
            // Borderless TradingView-style risk/reward zones; the candle bodies define both endpoints.
            ctx.fillStyle = 'rgba(235, 64, 75, 0.20)';
            ctx.fillRect(boxStart, Math.min(entryY, slY), boxWidth, Math.max(1, Math.abs(slY-entryY)));
            ctx.fillStyle = 'rgba(46, 204, 113, 0.20)';
            ctx.fillRect(boxStart, Math.min(entryY, tpY), boxWidth, Math.max(1, Math.abs(tpY-entryY)));
            if (trade.triggerIndex != null) {
                var triggerCenterX = ptOverlayX(geo, trade.triggerIndex);
                ctx.fillStyle = '#f2c94c'; ctx.beginPath(); ctx.arc(triggerCenterX, entryY, 3, 0, Math.PI*2); ctx.fill();
            }
            ctx.restore();
        });

        // Run each applicable higher-timeframe root against its own source candles.
        // The lower timeframe remains the visible chart; geometry maps each HTF candle
        // to the available lower candles overlapping its open/close interval.
        if (!PT_PROJECTION_RENDER_PASS && PT_BG_TF_DATA && Object.keys(PT_BG_TF_DATA).length) {
            var savedCandles = PT_CANDLES;
            var savedTimeframe = PT_CURRENT_TIMEFRAME;
            var savedMap = PT_PROJECTION_MAP;
            var savedTradeData = PT_ROOT_TRADE_DATA;
            var savedVirtualMatches = PT_VIRTUAL_MATCHES;
            var projectedTrades = [];
            var mainTradesAcrossTimeframes = Array.isArray(savedTradeData) ? savedTradeData.slice() : [];
            PT_CONFIRMATION_TRADE_DATA = [];
            PT_PROJECTION_TARGET_TIMEFRAME = String(savedTimeframe || '').toLowerCase();
            PT_PROJECTION_LOWER_CANDLES = savedCandles;
            Object.keys(PT_BG_TF_DATA).forEach(function (higherTf) {
                if (ptTfMilliseconds(higherTf) <= ptTfMilliseconds(savedTimeframe)) return;
                var higherCandles = PT_BG_TF_DATA[higherTf] || [];
                if (!higherCandles.length) return;
                var projectionMap = ptBuildProjectionMap(higherCandles, savedCandles);
                PT_CANDLES = higherCandles;
                PT_CURRENT_TIMEFRAME = higherTf;
                PT_PROJECTION_MAP = projectionMap;
                PT_PROJECTION_RENDER_PASS = true;
                ptApplyTrainingOverlay(geo);
                if (Array.isArray(PT_ROOT_TRADE_DATA)) {
                    PT_ROOT_TRADE_DATA.forEach(function(t) {
                        var ep = projectionMap[t.entryIndex];
                        var sp = projectionMap[t.slSourceIndex != null ? t.slSourceIndex : ptIndexForTradeTime(t.slTime)];
                        var tpSourceIndex = t.tpSourceIndex != null ? t.tpSourceIndex : ptIndexForTradeTime(t.tpTime);
                        var tpPiece = tpSourceIndex != null ? projectionMap[tpSourceIndex] : null;
                        if (!ep || ep.first < 0 || ep.last < 0) return;
                        var entryAfter = ep.last; // the 4th 1h candle closes before entry-trigger scanning begins
                        var order = String(t.orderType || 'buy').toLowerCase();
                        var isLong = ['buy','buy-limit','buy-stop'].indexOf(order) >= 0;
                        var triggerIndex = null;
                        for (var i = entryAfter + 1; i < savedCandles.length; i++) {
                            var c = savedCandles[i];
                            var hit = ['buy','buy-limit','sell-stop'].indexOf(order) >= 0 ? Number(c.low) < Number(t.entryPrice) : Number(c.high) > Number(t.entryPrice);
                            if (hit) { triggerIndex = i; break; }
                        }
                        var slTrigger = null, tpTrigger = null;
                        var outcome = triggerIndex == null ? 'pending entry' : 'in trade';
                        if (triggerIndex != null) {
                            for (var j = triggerIndex; j < savedCandles.length; j++) {
                                var cj = savedCandles[j];
                                var slHit = isLong ? Number(cj.low) <= Number(t.slPrice) : Number(cj.high) >= Number(t.slPrice);
                                var tpHit = isLong ? Number(cj.high) >= Number(t.tpPrice) : Number(cj.low) <= Number(t.tpPrice);
                                if (slHit || tpHit) { if (slHit) { outcome='stoploss hit'; slTrigger=j; } else { outcome='take profit reached'; tpTrigger=j; } break; }
                            }
                        }
                        function stampLower(idx) { return idx == null || !savedCandles[idx] ? null : String(savedCandles[idx].time || savedCandles[idx].open_time || ''); }
                        projectedTrades.push(Object.assign({}, t, {
                            dataMode:'projected', sourceTimeframe:higherTf, timeframe:savedTimeframe,
                            familyNumber:t.familyNumber, entrySourceIndex:t.entryIndex,
                            entryIndex:ep.last, entryTime:stampLower(ep.last),
                            triggerIndex:triggerIndex, entryTriggerTime:stampLower(triggerIndex),
                            slTriggerIndex:slTrigger, slTriggerTime:stampLower(slTrigger),
                            tpTriggerIndex:tpTrigger, tpTriggerTime:stampLower(tpTrigger),
                            slSourceIndex:t.slSourceIndex, tpSourceIndex:tpSourceIndex,
                            slTime:sp && sp.last >= 0 ? stampLower(sp.last) : t.slTime,
                            tpTime:tpPiece && tpPiece.last >= 0 ? stampLower(tpPiece.last) : t.tpTime,
                            outcome:outcome
                        }));
                    });
                }
            });
            PT_CANDLES = savedCandles;
            PT_CURRENT_TIMEFRAME = savedTimeframe;
            PT_PROJECTION_MAP = savedMap;
            PT_PROJECTION_RENDER_PASS = false;
            PT_MAIN_TRADE_DATA = mainTradesAcrossTimeframes;
            // Keep confirmation trades in their actual lower-timeframe coordinate system;
            // parent-root projected trades above remain mapped from the HTF source.
            PT_CONFIRMATION_TRADE_DATA.forEach(function(t){ projectedTrades.push(t); });
            PT_PROJECTED_TRADE_DATA = projectedTrades;
            PT_ROOT_TRADE_DATA = PT_MAIN_TRADE_DATA;
            PT_VIRTUAL_MATCHES = savedVirtualMatches;
            PT_PROJECTION_LOWER_CANDLES = null;
            PT_PROJECTION_TARGET_TIMEFRAME = '';
        } else if (!PT_PROJECTION_RENDER_PASS) {
            PT_MAIN_TRADE_DATA = Array.isArray(PT_ROOT_TRADE_DATA) ? PT_ROOT_TRADE_DATA.slice() : [];
            PT_ROOT_TRADE_DATA = PT_MAIN_TRADE_DATA;
        }
    }

    var PT_ROOT_TRADE_DATA = [];
    var PT_MAIN_TRADE_DATA = [];
    var PT_PROJECTED_TRADE_DATA = [];
    var PT_ROOTS_DATA_MODE = 'main'; // Roots Data includes native and configured confirmation trades.
    function ptConfirmationTradeGuidance() {
        var cfg = typeof window.ptGetTrainingConfiguration === 'function' ? window.ptGetTrainingConfiguration() : null;
        if (!cfg || !Array.isArray(cfg.roots)) return '';
        var lines = [];
        cfg.roots.forEach(function(r) {
            if (!r.useForTrades || r.tradeFor !== 'root_confirmation' || !r.confirmationRoot) return;
            var tfs = (r.confirmationRoot.timeframes || []).map(function(tf){return String(tf);});
            if (tfs.length) lines.push('Root ' + r.id + ': switch the chart to ' + tfs.join(', ') + ' to view confirmation-timeframe trades.');
        });
        return lines.join('\n');
    }
    var PT_PROJECTION_LOWER_CANDLES = null;
    var PT_PROJECTION_TARGET_TIMEFRAME = '';
    var PT_CONFIRMATION_TRADE_DATA = [];
    var PT_FAMILY_HOVER_NOTE = null;
    function ptShowFamilyHoverNote(trade, clientX, clientY) {
        if (!trade || !trade.minimumRiskRewardApplied) { if (PT_FAMILY_HOVER_NOTE) PT_FAMILY_HOVER_NOTE.style.display='none'; return; }
        if (!PT_FAMILY_HOVER_NOTE) {
            PT_FAMILY_HOVER_NOTE=document.createElement('div');
            PT_FAMILY_HOVER_NOTE.className='pt-family-hover-note';
            PT_FAMILY_HOVER_NOTE.setAttribute('role','status');
            document.body.appendChild(PT_FAMILY_HOVER_NOTE);
        }
        var actual=Number(trade.configuredTpPrice), entry=Number(trade.entryPrice), risk=Math.abs(entry-Number(trade.slPrice));
        var actualRR=(risk>0&&Number.isFinite(actual))?(trade.isLong?(actual-entry)/risk:(entry-actual)/risk):0;
        PT_FAMILY_HOVER_NOTE.innerHTML='<strong>Minimum risk/reward applied</strong>This trade family\'s configured take-profit provides approximately 1:'+actualRR.toFixed(2)+' reward. The minimum permitted target is 1:1, so the take-profit was adjusted to the minimum allowed level.';
        PT_FAMILY_HOVER_NOTE.style.display='block';
        var left=Math.max(8,Math.min((clientX||12)+14,window.innerWidth-PT_FAMILY_HOVER_NOTE.offsetWidth-8));
        var top=Math.max(8,Math.min((clientY||12)+14,window.innerHeight-PT_FAMILY_HOVER_NOTE.offsetHeight-8));
        PT_FAMILY_HOVER_NOTE.style.left=left+'px';PT_FAMILY_HOVER_NOTE.style.top=top+'px';
    }

    function ptBuildRootTradeData(roots, candles) {
        var records = [];
        function tradeSource(root) {
            return root.tradeFor === 'root_confirmation' && root.confirmationRoot
                ? root.confirmationRoot : root;
        }
        function targetKey(root, encoded) {
            if (!encoded) return null;
            var parts = String(encoded).split('|'); if (parts.length < 3) return null;
            var parentTarget = parts[0] === 'parent';
            var source = parentTarget ? root : tradeSource(root);
            var tree=(source.trees||[]).find(function(t){return String(t.id)===String(parentTarget ? parts[1] : parts[0]);});
            if(!tree) return null;
            var kind = parentTarget ? parts[2] : parts[1];
            var fallbackId = parentTarget ? parts[3] : parts[2];
            var c=tree[kind]; if(!c) return null;
            var identity = String(c.selectedExistingId || (kind==='reference' ? c.existingCandleId : null) || c.offsetTo || c.id || fallbackId);
            return parentTarget ? 'parent:' + identity : identity;
        }
        function stamp(c) { if(!c) return '—'; var v=c.time; if(typeof v==='number') return new Date(v < 1e12 ? v*1000 : v).toISOString(); return String(v||'—'); }
        function targetLabel(root, encoded) {
            if(!encoded) return '—';
            var parts=String(encoded).split('|'), parentTarget=parts[0]==='parent';
            var source=parentTarget ? root : tradeSource(root);
            var tree=(source.trees||[]).find(function(t){return String(t.id)===String(parentTarget ? parts[1] : parts[0]);});
            var c=tree&&tree[parentTarget ? parts[2] : parts[1]];
            return (c&&c.name ? c.name : (parentTarget ? parts[3] : parts[2])||'—') + (parentTarget ? ' (root)' : '');
        }
        function resolveTradeTargetIndex(key, family, candles, level) {
            if (key == null) return null;
            if (String(key).indexOf('parent:') !== 0) {
                var direct = (family.map || {})[key];
                return direct == null ? null : Number(direct);
            }
            var parentFamily = family.parentFamily;
            if (!parentFamily || !parentFamily.map) return null;
            var parentIdentity = String(key).slice(7);
            var parentIndex = parentFamily.map[parentIdentity];
            if (parentIndex == null) return null;
            var piece = PT_PROJECTION_MAP && PT_PROJECTION_MAP[Number(parentIndex)];
            if (!piece || piece.first < 0 || piece.last < 0) return null;
            var lower = Array.isArray(PT_PROJECTION_LOWER_CANDLES) ? PT_PROJECTION_LOWER_CANDLES : candles;
            var normalizedLevel = String(level || 'high').toLowerCase();
            if (normalizedLevel === 'open') return piece.first;
            if (normalizedLevel === 'close') return piece.last;
            var best = piece.first;
            var extreme = normalizedLevel === 'low' ? Infinity : -Infinity;
            for (var li=piece.first; li<=piece.last; li++) {
                var candidate = lower[li];
                if (!candidate) continue;
                var value = normalizedLevel === 'low' ? Number(candidate.low) :
                    normalizedLevel === 'high' ? Number(candidate.high) : Number(ptPrice(candidate, normalizedLevel));
                if (!Number.isFinite(value)) continue;
                if ((normalizedLevel === 'low' && value < extreme) || (normalizedLevel !== 'low' && value > extreme)) {
                    extreme = value; best = li;
                }
            }
            return best;
        }
        roots.forEach(function(root){
            if (root.applyToChart === false || root.applyToChart === 0 || root.applyToChart === '0') return;
            if(!root.useForTrades) return;
            var source = tradeSource(root);
            var tfs = source === root ? ptNormTfList(root) : (Array.isArray(source.timeframes) ? source.timeframes.map(function(v){return String(v).toLowerCase();}) : []);
            if(tfs.indexOf(String(PT_CURRENT_TIMEFRAME).toLowerCase())<0) return;
            var entryKey=targetKey(root,root.entryFrom), slKey=targetKey(root,root.stoplossFrom), tpKey=root.takeProfitType==='risk_reward'?null:targetKey(root,root.takeProfitFrom);
            if(!entryKey || !slKey || (root.takeProfitType!=='risk_reward' && !tpKey)) return;
            var families=source === root ? (Array.isArray(root._drawMatches)?root._drawMatches:[]) : (Array.isArray(root._confirmationDrawMatches)?root._confirmationDrawMatches:[]);
            families.forEach(function(family, famIndex){
                var map=family.map||{};
                var entryIndex=resolveTradeTargetIndex(entryKey,family,candles,root.entryPriceLevel||'high');
                var slIndex=resolveTradeTargetIndex(slKey,family,candles,root.stoplossPriceLevel||'high');
                var tpIndex=tpKey?resolveTradeTargetIndex(tpKey,family,candles,root.takeProfitPriceLevel||'high'):null;
                if(entryIndex==null || slIndex==null || (root.takeProfitType!=='risk_reward' && tpIndex==null)) return;
                var entryC=candles[entryIndex], slC=candles[slIndex], tpC=tpIndex!=null?candles[tpIndex]:null;
                if(!entryC||!slC) return;
                var entryPrice=ptPrice(entryC,root.entryPriceLevel||'high'), slPrice=ptPrice(slC,root.stoplossPriceLevel||'high');
                var order=String(root.orderType||'buy').toLowerCase();
                var isLong=['buy','buy-limit','buy-stop'].indexOf(order)>=0;
                var triggerIndex=null;
                // Entry candle A is excluded: scan from the immediately following candle.
                for(var i=entryIndex+1;i<candles.length;i++){
                    var candle=candles[i];
                    var triggerHit=['buy','buy-limit','sell-stop'].indexOf(order)>=0 ? Number(candle.low)<entryPrice : Number(candle.high)>entryPrice;
                    if(triggerHit){triggerIndex=i;break;}
                }
                var rr=root.takeProfitType==='risk_reward'?Number(window.PT_PROVIDER_RISK_REWARD || root.riskReward || 0):null;
                var riskDistanceForTarget=Math.abs(entryPrice-slPrice);
                var configuredTpPrice = root.takeProfitType==='risk_reward'
                    ? (isLong ? entryPrice+riskDistanceForTarget*(rr>0?rr:1) : entryPrice-riskDistanceForTarget*(rr>0?rr:1))
                    : (tpC?ptPrice(tpC,root.takeProfitPriceLevel||'high'):null);
                var tpPrice = configuredTpPrice;
                var minimumRiskRewardApplied = false;
                // HARD RULE: price-level take profit can never be less than 1:1.
                // If the configured price is too close, on the wrong side, or gives < 1R,
                // clamp it to exactly one risk distance from entry in the profitable direction.
                if(root.takeProfitType!=='risk_reward' && riskDistanceForTarget>0 && tpPrice!==null && Number.isFinite(Number(tpPrice))){
                    var minimumTarget=isLong ? entryPrice+riskDistanceForTarget : entryPrice-riskDistanceForTarget;
                    var actualReward=isLong ? Number(tpPrice)-entryPrice : entryPrice-Number(tpPrice);
                    if(!(actualReward>=riskDistanceForTarget)) { tpPrice=minimumTarget; minimumRiskRewardApplied=true; }
                }
                var slTrigger=null,tpTrigger=null,outcome=triggerIndex==null?'pending entry':'in trade';
                if(triggerIndex!=null){
                    for(var j=triggerIndex;j<candles.length;j++){
                        var cj=candles[j], slHit=isLong?Number(cj.low)<=slPrice:Number(cj.high)>=slPrice,
                            tpHit=isLong?Number(cj.high)>=tpPrice:Number(cj.low)<=tpPrice;
                        if(slHit||tpHit){
                            if(slHit){outcome='stoploss hit';slTrigger=j;}
                            else {outcome='take profit reached';tpTrigger=j;}
                            break;
                        }
                    }
                }
                var riskDistance=Math.abs(entryPrice-slPrice);
                var rewardDistance=(tpPrice!==null && Number.isFinite(Number(tpPrice)))?Math.abs(Number(tpPrice)-entryPrice):null;
                var familyRiskReward=(riskDistance>0 && rewardDistance!==null)?(rewardDistance/riskDistance):null;
                records.push({rootId:root.id,rootName:root.name||('Root '+root.id),familyNumber:famIndex+1,timeframe:String(PT_CURRENT_TIMEFRAME).toLowerCase(),sourceTimeframe:String(PT_CURRENT_TIMEFRAME).toLowerCase(),dataMode:'main',orderType:order,
                    entryIndex:entryIndex,entryName:targetLabel(root,root.entryFrom),entryPrice:entryPrice,entryTime:stamp(entryC),
                    slName:targetLabel(root,root.stoplossFrom),slPrice:slPrice,slTime:stamp(slC),tpType:root.takeProfitType==='risk_reward'?'risk reward':'price level',
                    riskReward:familyRiskReward,configuredRiskReward:rr,isLong:isLong,configuredTpPrice:configuredTpPrice,minimumRiskRewardApplied:minimumRiskRewardApplied,tpName:tpC?targetLabel(root,root.takeProfitFrom):null,tpPrice:tpPrice,tpTime:tpC?stamp(tpC):null,
                    triggerIndex:triggerIndex,entryTriggerTime:triggerIndex!=null?stamp(candles[triggerIndex]):null,slTriggerIndex:slTrigger,tpTriggerIndex:tpTrigger,outcome:outcome,
                    slTriggerTime:slTrigger!=null?stamp(candles[slTrigger]):null,tpTriggerTime:tpTrigger!=null?stamp(candles[tpTrigger]):null});
            });
        });
        return records;
    }
    window.ptOpenRootsData=function(){var m=document.getElementById('ptRootsDataModal');if(m)m.classList.add('active');ptRefreshRootsData();};
    window.ptSetRootsDataMode=function(){PT_ROOTS_DATA_MODE='main';PT_ROOT_TRADE_DATA=PT_MAIN_TRADE_DATA;ptRefreshRootsData();};
    window.ptCloseRootsData=function(){var m=document.getElementById('ptRootsDataModal');if(m)m.classList.remove('active');};
    // Resolve a configured candle timestamp to the currently loaded chart index.
    function ptIndexForTradeTime(value) {
        if (value == null || value === '' || !Array.isArray(PT_CANDLES)) return -1;
        var wanted = String(value).replace('T',' ').slice(0,16);
        for (var i = 0; i < PT_CANDLES.length; i++) {
            if (String(PT_CANDLES[i].time || '').replace('T',' ').slice(0,16) === wanted) return i;
        }
        return -1;
    }

    function ptStopLocateArrowAnimation() {
        if (PT_LOCATE_ANIMATION_FRAME !== null) {
            cancelAnimationFrame(PT_LOCATE_ANIMATION_FRAME);
            PT_LOCATE_ANIMATION_FRAME = null;
        }
    }

    function ptDrawLocateArrow(geo) {
        var a = PT_LOCATE_ARROW;
        if (!a) return;
        var now = Date.now();
        if (now >= a.expiresAt) {
            PT_LOCATE_ARROW = null;
            ptStopLocateArrowAnimation();
            return;
        }

        var ctx = geo.ctx;
        // When a stop-loss was actually triggered, align the arrow with that
        // trigger candle and target its extreme: sell -> high, buy -> low.
        // If no trigger candle exists, fall back to the configured stop-loss price
        // and keep the arrow aligned with the entry-trigger candle.
        var x = geo.baseX + a.index * geo.step + geo.step / 2;
        var targetY = geo.pToY(a.targetPrice);
        var pulse = Math.sin((now - a.startedAt) / 90) * 4;
        // Trigger candle targets sit 1px beyond the relevant wick; without a
        // trigger candle the arrowhead sits directly on the stop-loss price level.
        // The triangle's actual apex is 1px beyond tipY in its pointing
        // direction, so compensate here to land the apex at the requested pixel.
        var tipY = targetY + (a.direction === 'up'
            ? (a.hasStoplossTrigger ? 2 : 1)
            : (a.hasStoplossTrigger ? -2 : -1));
        var shaft = 48 + Math.sin((now - a.startedAt) / 130) * 5;
        var head = 12;

        // Keep the complete pointer inside the plot area without changing its target.
        tipY = Math.max(geo.chartTop + 8, Math.min(geo.chartBottom - 8, tipY));
        var shaftStartY = a.direction === 'up'
            ? Math.min(geo.chartBottom - 5, tipY + shaft)
            : Math.max(geo.chartTop + 5, tipY - shaft);
        var shaftEndY = a.direction === 'up' ? tipY + head : tipY - head;

        ctx.save();
        ctx.globalAlpha = 0.98;
        ctx.strokeStyle = '#ffd400';
        ctx.fillStyle = '#ffd400';
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.shadowColor = 'rgba(0,0,0,0.75)';
        ctx.shadowBlur = 7;

        // The arrow stays on the entry-trigger candle's exact vertical axis.
        ctx.beginPath();
        ctx.moveTo(x, shaftStartY);
        ctx.lineTo(x, shaftEndY);
        ctx.stroke();

        ctx.beginPath();
        if (a.direction === 'up') {
            ctx.moveTo(x, tipY - 1);
            ctx.lineTo(x - head, tipY + head);
            ctx.lineTo(x + head, tipY + head);
        } else {
            ctx.moveTo(x, tipY + 1);
            ctx.lineTo(x - head, tipY - head);
            ctx.lineTo(x + head, tipY - head);
        }
        ctx.closePath();
        ctx.fill();

        // Ring marks the selected target: the stop-loss trigger wick when
        // available, otherwise the configured stop-loss price.
        ctx.beginPath();
        ctx.arc(x, targetY, 7 + Math.abs(pulse) * 0.4, 0, Math.PI * 2);
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(x, targetY, 3, 0, Math.PI * 2);
        ctx.fillStyle = '#ffd400';
        ctx.fill();
        ctx.restore();
    }

    function ptRunLocateArrowAnimation() {
        ptStopLocateArrowAnimation();
        function frame() {
            if (!PT_LOCATE_ARROW || Date.now() >= PT_LOCATE_ARROW.expiresAt) {
                PT_LOCATE_ARROW = null;
                PT_LOCATE_ANIMATION_FRAME = null;
                ptScheduleDraw();
                return;
            }
            ptScheduleDraw();
            PT_LOCATE_ANIMATION_FRAME = requestAnimationFrame(frame);
        }
        PT_LOCATE_ANIMATION_FRAME = requestAnimationFrame(frame);
    }

    // Close the records modal, zoom to the full trade area, and point to its entry trigger.
    window.ptLocateTrade=function(rootId,familyNumber,sourceTimeframe){
        // Native trades live in PT_MAIN_TRADE_DATA. Root-confirmation trades are
        // generated while projecting their parent root onto the lower timeframe and
        // stored separately in PT_PROJECTED_TRADE_DATA with dataMode='confirmation'.
        // Search both collections so a Roots Data click can locate the exact
        // confirmation family on the currently displayed lower-timeframe chart.
        var nativeRecords = Array.isArray(PT_MAIN_TRADE_DATA) ? PT_MAIN_TRADE_DATA :
            (Array.isArray(PT_ROOT_TRADE_DATA) ? PT_ROOT_TRADE_DATA : []);
        var confirmationRecords = (Array.isArray(PT_PROJECTED_TRADE_DATA) ? PT_PROJECTED_TRADE_DATA : []).filter(function(t){
            return t && t.dataMode === 'confirmation';
        });
        var records = nativeRecords.concat(confirmationRecords).filter(function(t){
            return String(t.timeframe||'').toLowerCase()===String(PT_CURRENT_TIMEFRAME||'').toLowerCase() &&
                t.dataMode !== 'projected';
        });
        var trade=records.find(function(t){
            return String(t.rootId)===String(rootId)&&Number(t.familyNumber)===Number(familyNumber)&&(!sourceTimeframe||String(t.sourceTimeframe||'')===String(sourceTimeframe));
        });
        if(!trade) return;
        var expectedTimeframe = String(trade.timeframe || (trade.dataMode === 'projected' ? PT_CURRENT_TIMEFRAME : '')).toLowerCase();
        if (expectedTimeframe && expectedTimeframe !== String(PT_CURRENT_TIMEFRAME).toLowerCase()) {
            alert('You have to switch to ' + expectedTimeframe.toUpperCase() + ' to view this trade.');
            return;
        }
        if (!Array.isArray(PT_CANDLES) || !PT_CANDLES.length) return;

        var entryIndex = Number(trade.triggerIndex != null ? trade.triggerIndex : trade.entryIndex);
        if(!Number.isFinite(entryIndex) || entryIndex < 0 || entryIndex >= PT_CANDLES.length) {
            entryIndex = Number(trade.entryIndex);
        }
        if(!Number.isFinite(entryIndex) || entryIndex < 0 || entryIndex >= PT_CANDLES.length) return;

        var slPrice = Number(trade.slPrice);
        var entryPrice = Number(trade.entryPrice);
        var tpPrice = Number(trade.tpPrice);
        if(!Number.isFinite(slPrice)) slPrice = Number(PT_CANDLES[entryIndex].low);
        if(!Number.isFinite(entryPrice)) entryPrice = Number(PT_CANDLES[entryIndex].close);

        var slIndex = ptIndexForTradeTime(trade.slTime);
        var tpIndex = ptIndexForTradeTime(trade.tpTime);
        var slTriggerIndex = trade.slTriggerIndex != null ? Number(trade.slTriggerIndex) : NaN;
        var hasStoplossTrigger = Number.isFinite(slTriggerIndex) &&
            slTriggerIndex >= 0 && slTriggerIndex < PT_CANDLES.length;
        if(slIndex < 0 && trade.slTriggerIndex != null) slIndex = Number(trade.slTriggerIndex);
        if(tpIndex < 0 && trade.tpTriggerIndex != null) tpIndex = Number(trade.tpTriggerIndex);

        var indices = [entryIndex];
        if(Number.isFinite(slIndex) && slIndex >= 0 && slIndex < PT_CANDLES.length) indices.push(slIndex);
        if(Number.isFinite(tpIndex) && tpIndex >= 0 && tpIndex < PT_CANDLES.length) indices.push(tpIndex);
        var minIndex = Math.min.apply(Math, indices);
        var maxIndex = Math.max.apply(Math, indices);
        var span = Math.max(1, maxIndex - minIndex + 1);
        var card = document.getElementById('ptChartCard');
        if(!card) return;

        var locateIndex = hasStoplossTrigger ? slTriggerIndex : entryIndex;
        var vw = card.clientWidth;
        var avail = Math.max(120, vw - PT_PADDING_LEFT - PT_PADDING_RIGHT - 48);
        // Adjust zoom horizontally only. Keep candles readable, with a modest cap;
        // vertical scale is deliberately not enlarged or otherwise zoomed.
        var fitWidth = avail / (span * 1.35 + 3);
        PT_VIEW.candleWidth = Math.max(3.5, Math.min(12, fitWidth));
        PT_VIEW.candleSpacing = Math.max(1, PT_VIEW.candleWidth * 0.35);

        // Centre the arrow's target candle when a stop-loss trigger exists;
        // otherwise centre the entry-trigger candle as the stop-loss-price fallback.
        var step = ptStep();
        var centerX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + locateIndex * step + step / 2;
        PT_VIEW.offsetX = centerX - vw / 2;
        ptClampOffsetX();
        PT_FOLLOW_LATEST = false;

        // Include the trade's price levels and leave breathing room above/below them.
        var prices = [entryPrice, slPrice];
        if(Number.isFinite(tpPrice)) prices.push(tpPrice);
        var finitePrices = prices.filter(function(v){return Number.isFinite(v);});
        var priceMin = Math.min.apply(Math, finitePrices);
        var priceMax = Math.max.apply(Math, finitePrices);
        if(priceMax === priceMin) {
            var pad = Math.max(Math.abs(priceMax) * 0.002, 0.00001);
            priceMin -= pad; priceMax += pad;
        } else {
            var pricePad = (priceMax - priceMin) * 0.18;
            priceMin -= pricePad; priceMax += pricePad;
        }

        // Infer direction from stop-loss versus entry:
        // SL above entry => sell/down; SL below entry => buy/up.
        var isSell = slPrice > entryPrice;
        var isBuy = slPrice < entryPrice;
        var direction = isSell ? 'down' : (isBuy ? 'up' : (String(trade.orderType || '').toLowerCase().indexOf('sell') === 0 ? 'down' : 'up'));
        var targetIndex = hasStoplossTrigger ? slTriggerIndex : entryIndex;
        var targetPrice = slPrice;
        if (hasStoplossTrigger) {
            var stoplossTriggerCandle = PT_CANDLES[slTriggerIndex];
            targetPrice = direction === 'down'
                ? Number(stoplossTriggerCandle.high)
                : Number(stoplossTriggerCandle.low);
            if (!Number.isFinite(targetPrice)) {
                hasStoplossTrigger = false;
                targetIndex = entryIndex;
                targetPrice = slPrice;
            }
        }
        // Keep the full family vertically centred while ensuring the arrow's
        // selected target price is included in the visible price range.
        if (Number.isFinite(targetPrice)) {
            priceMin = Math.min(priceMin, targetPrice);
            priceMax = Math.max(priceMax, targetPrice);
        }
        var centerPrice = (entryPrice + slPrice) / 2;
        PT_LOCATE_ARROW = {
            rootId: String(rootId),
            familyNumber: Number(familyNumber),
            index: targetIndex,
            slPrice: slPrice,
            targetPrice: targetPrice,
            hasStoplossTrigger: hasStoplossTrigger,
            entryPrice: entryPrice,
            centerPrice: centerPrice,
            priceMin: priceMin,
            priceMax: priceMax,
            direction: direction,
            startedAt: Date.now(),
            expiresAt: Date.now() + 4000
        };

        window.ptCloseRootsData();
        PT_HOVER_INDEX = entryIndex;
        PT_CURSOR_Y = null;
        ptStopLocateArrowAnimation();

        window.requestAnimationFrame(function(){
            // Recalculate the visible price scale after centering the trade's candles.
            ptScheduleDraw();
            if(typeof ptUpdateReadout==='function') ptUpdateReadout();
            if(typeof ptUpdateJumpLatestVisibility==='function') ptUpdateJumpLatestVisibility();
            ptRunLocateArrowAnimation();
        });
    };
    window.ptRefreshRootsData=function(){
        // Opening a data modal must not trigger a full chart rasterization.
        // The chart already owns its pixels; the modal only needs trade data.
        var body=document.getElementById('ptRootsDataBody'),status=document.getElementById('ptRootsDataStatus'); if(!body)return;
        var configuredRoots=(window.ptGetTrainingConfiguration&&window.ptGetTrainingConfiguration().roots)||[];
        var currentTf=String(PT_CURRENT_TIMEFRAME||'').toLowerCase();
        var roots=configuredRoots.filter(function(root){
            if(root.applyToChart===false || root.applyToChart===0 || root.applyToChart==='0') return false;
            var nativeMatch = ptNormTfList(root).map(function(tf){return String(tf).toLowerCase();}).indexOf(currentTf)>=0;
            var confirmationMatch = root.tradeFor === 'root_confirmation' && root.confirmationRoot && root.useForTrades &&
                (root.confirmationRoot.timeframes || []).map(function(tf){return String(tf).toLowerCase();}).indexOf(currentTf)>=0;
            return nativeMatch || confirmationMatch;
        });
        var rootIds=new Set(roots.map(function(root){return String(root.id);}));
        var all=(PT_MAIN_TRADE_DATA||PT_ROOT_TRADE_DATA||[]).concat((PT_PROJECTED_TRADE_DATA||[]).filter(function(t){return t.dataMode==='confirmation';})).filter(function(t){
            return String(t.timeframe||'').toLowerCase()===currentTf && (t.dataMode==='confirmation' || t.dataMode!=='projected') &&
                rootIds.has(String(t.rootId));
        });
        var guidance = ptConfirmationTradeGuidance();
        var subtitle = document.querySelector('#ptRootsDataTitle') && document.querySelector('#ptRootsDataTitle').parentElement ? document.querySelector('#ptRootsDataTitle').parentElement.querySelector('.pt-dynamic-console-subtitle') : null;
        if (subtitle) subtitle.textContent = guidance ? ('Matched root families and entry/exit trigger records.\n' + guidance) : 'Matched root families and their entry/exit trigger records';
        function fmtRR(value){return Number(value).toLocaleString(undefined,{maximumFractionDigits:3});}
        function closed(items){return items.filter(function(t){return t.outcome==='stoploss hit'||t.outcome==='take profit reached';}).sort(function(a,b){
            var ai=a.slTriggerIndex!=null?a.slTriggerIndex:(a.tpTriggerIndex!=null?a.tpTriggerIndex:(a.triggerIndex!=null?a.triggerIndex:a.entryIndex));
            var bi=b.slTriggerIndex!=null?b.slTriggerIndex:(b.tpTriggerIndex!=null?b.tpTriggerIndex:(b.triggerIndex!=null?b.triggerIndex:b.entryIndex));
            return ai-bi || Number(a.entryIndex||0)-Number(b.entryIndex||0);
        });}
        function stats(items){
            var sl=items.filter(function(t){return t.outcome==='stoploss hit';}).length;
            var tp=items.filter(function(t){return t.outcome==='take profit reached';}).length;
            var pending=items.filter(function(t){return t.outcome==='pending entry';}).length;
            var active=items.filter(function(t){return t.outcome==='in trade';}).length;
            var winsR=items.reduce(function(sum,t){return sum+(t.outcome==='take profit reached'&&Number.isFinite(Number(t.riskReward))?Number(t.riskReward):0);},0);
            var netR=winsR-sl, sequence=closed(items), lossRun=0,winRun=0,maxLoss=0,maxWin=0;
            // Virtual balance starts at 100%. Each stoploss subtracts 1 percentage point
            // and each profitable trade adds its risk-reward value in percentage points.
            // Unlike the drawdown display, virtual balance and revenue peak are NOT capped at 100%.
            var equity=100, currentVirtualBalance=100, highestPeakBalance=100, maxDrawdown=0, accountLostAt=null;
            var activeDrawdownTrades=[],highestDrawdownTrades=[],highestDrawdownLostRisks=0,highestDrawdownRewards=0;
            var highestRevenuePeakTrades=[],highestDrawdownEquity=100;
            var previousStreakRootId=null;
            sequence.forEach(function(t){
                var tradeR=t.outcome==='stoploss hit' ? -1 : Math.max(0,Number(t.riskReward)||0);
                // A global dashboard combines totals across roots, but a streak must never
                // continue from one root into another root's unrelated trade sequence.
                if(previousStreakRootId!==null && String(t.rootId)!==String(previousStreakRootId)){
                    lossRun=0;winRun=0;
                }
                previousStreakRootId=t.rootId;
                if(t.outcome==='stoploss hit'){
                    lossRun++;winRun=0;maxLoss=Math.max(maxLoss,lossRun);
                } else {
                    winRun++;lossRun=0;maxWin=Math.max(maxWin,winRun);
                }
                equity += tradeR;
                currentVirtualBalance = equity;
                activeDrawdownTrades.push({label:tradeR<0?'-1R':'+'+fmtRR(tradeR)+'RR',value:tradeR,outcome:t.outcome,rootId:t.rootId,familyNumber:t.familyNumber});
                // Highest drawdown remains measured against the 100% baseline and can recover.
                var dd=Math.max(0,100-equity);
                if(dd>maxDrawdown){
                    maxDrawdown=dd;
                    highestDrawdownTrades=activeDrawdownTrades.slice();
                    highestDrawdownLostRisks=highestDrawdownTrades.reduce(function(sum,tr){return sum+(tr.value<0?1:0);},0);
                    highestDrawdownRewards=highestDrawdownTrades.reduce(function(sum,tr){return sum+(tr.value>0?tr.value:0);},0);
                    highestDrawdownEquity=equity;
                }
                // Revenue peak is the highest cumulative balance ever reached, even after losses.
                if(equity>highestPeakBalance){
                    highestPeakBalance=equity;
                    highestRevenuePeakTrades=activeDrawdownTrades.slice();
                }
                if(equity<=0 && accountLostAt===null){
                    accountLostAt='Root '+String(t.rootId!=null?t.rootId:'?')+' · Trade '+String(t.familyNumber!=null?t.familyNumber:'?');
                }
                if(equity>=100) activeDrawdownTrades=[];
            });
            return {sl:sl,tp:tp,pending:pending,active:active,total:items.length,closed:sl+tp,winRate:(sl+tp)>0?tp/(sl+tp)*100:0,
                winsR:winsR,lostR:sl,netR:netR,maxLoss:maxLoss,maxWin:maxWin,maxDrawdown:maxDrawdown,accountLostAt:accountLostAt,
                currentVirtualBalance:currentVirtualBalance,highestPeakBalance:highestPeakBalance,
                highestRevenuePeak:Math.max(0,highestPeakBalance-100),highestRevenuePeakTrades:highestRevenuePeakTrades,
                highestDrawdownTrades:highestDrawdownTrades,highestDrawdownLostRisks:highestDrawdownLostRisks,highestDrawdownRewards:highestDrawdownRewards};
        }

                function card(label,value,cls,icon,detail){return '<div class="pt-rd-stat '+cls+'"><span class="pt-rd-stat-icon"><i class="fa-solid '+icon+'"></i></span><div><div class="pt-rd-stat-label">'+label+'</div><div class="pt-rd-stat-value">'+value+'</div>'+(detail?'<div class="pt-rd-stat-label">'+detail+'</div>':'')+'</div></div>';}
        function drawdownCard(x,scopeKey){
            var key='pt-dd-'+String(scopeKey).replace(/[^a-zA-Z0-9_-]/g,'-');
            var sequenceHtml=x.highestDrawdownTrades.length
                ? '<div class="pt-dd-sequence">'+x.highestDrawdownTrades.map(function(t){return '<button type="button" class="pt-dd-chip '+(t.value<0?'loss':'profit')+'" title="Locate Root '+ptEscapeHtml(t.rootId)+' · Family '+ptEscapeHtml(t.familyNumber)+' on chart" onclick="ptLocateTrade(\''+ptEscapeHtml(t.rootId)+'\','+Number(t.familyNumber||0)+')">'+ptEscapeHtml(t.label)+' <span aria-hidden="true">#'+ptEscapeHtml(t.familyNumber)+'</span></button>';}).join('')+'</div>'
                : '<div class="pt-dd-empty">No drawdown sequence has been recorded.</div>';
            var rewards=(x.highestDrawdownRewards>=0?'+':'')+fmtRR(x.highestDrawdownRewards)+'R';
            return '<div class="pt-rd-stat loss pt-rd-drawdown-card" id="'+key+'">'+
                '<div class="pt-dd-cover"><span class="pt-rd-stat-icon"><i class="fa-solid fa-chart-line"></i></span><div class="pt-dd-content"><div class="pt-rd-stat-label">Highest Drawdown</div><div class="pt-rd-stat-value">'+fmtRR(x.maxDrawdown)+'%</div><div class="pt-rd-stat-label">'+ptEscapeHtml(x.accountLostAt?'Account exhausted at '+x.accountLostAt:'Fixed 100% balance ceiling · wins restore balance but never exceed 100%')+'</div><button type="button" class="pt-dd-button" onclick="ptToggleDrawdown(\''+key+'\',true)">See trades</button></div></div>'+
                '<div class="pt-dd-details" hidden><div class="pt-dd-detail-heading">Trades making up the highest drawdown</div>'+sequenceHtml+
                '<div class="pt-dd-totals"><div><span>Lost risks</span><strong>'+fmtRR(x.highestDrawdownLostRisks)+'R</strong></div><div><span>Risk rewards</span><strong>'+rewards+'</strong></div></div>'+
                '<button type="button" class="pt-dd-button secondary" onclick="ptToggleDrawdown(\''+key+'\',false)">Back</button></div></div>';
        }
        function highestRevenuePeakCard(x,scopeKey){
            var key='pt-peak-'+String(scopeKey).replace(/[^a-zA-Z0-9_-]/g,'-');
            var sequenceHtml=x.highestRevenuePeakTrades.length
                ? '<div class="pt-dd-sequence">'+x.highestRevenuePeakTrades.map(function(t){
                    return '<button type="button" class="pt-dd-chip '+(t.value<0?'loss':'profit')+'" title="Locate Root '+ptEscapeHtml(t.rootId)+' · Family '+ptEscapeHtml(t.familyNumber)+' on chart" onclick="ptLocateTrade(\''+ptEscapeHtml(t.rootId)+'\','+Number(t.familyNumber||0)+')">'+ptEscapeHtml(t.label)+' <span aria-hidden="true">#'+ptEscapeHtml(t.familyNumber)+'</span></button>';
                  }).join('')+'</div>'
                : '<div class="pt-dd-empty">No profitable peak above the initial 100% balance has been recorded.</div>';
            return '<div class="pt-rd-stat profit pt-rd-drawdown-card" id="'+key+'">'+
                '<div class="pt-dd-cover"><span class="pt-rd-stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span><div class="pt-dd-content"><div class="pt-rd-stat-label">Highest Revenue Peak</div><div class="pt-rd-stat-value">+'+fmtRR(x.highestRevenuePeak)+'%</div><div class="pt-rd-stat-label">Highest balance reached: '+fmtRR(x.highestPeakBalance)+'%</div><button type="button" class="pt-dd-button" onclick="ptToggleDrawdown(\''+key+'\',true)">See trades</button></div></div>'+
                '<div class="pt-dd-details" hidden><div class="pt-dd-detail-heading">Trades contributing to the highest revenue peak</div>'+sequenceHtml+
                '<div class="pt-dd-totals"><div><span>Peak revenue above 100%</span><strong>+'+fmtRR(x.highestRevenuePeak)+'%</strong></div><div><span>Peak balance</span><strong>'+fmtRR(x.highestPeakBalance)+'%</strong></div></div>'+
                '<button type="button" class="pt-dd-button secondary" onclick="ptToggleDrawdown(\''+key+'\',false)">Back</button></div></div>';
        }
        window.ptToggleDrawdown=function(key,showDetails){
            var card=document.getElementById(key); if(!card)return;
            var cover=card.querySelector('.pt-dd-cover'),details=card.querySelector('.pt-dd-details');
            if(showDetails){
                if(!card.dataset.ptOriginalHeight){
                    card.dataset.ptOriginalHeight=String(Math.max(1,Math.round(card.getBoundingClientRect().height)));
                }
                card.style.height=card.dataset.ptOriginalHeight+'px';
                card.style.maxHeight=card.dataset.ptOriginalHeight+'px';
                card.style.minHeight='0';
                if(details){details.hidden=false;details.style.maxHeight='calc('+card.dataset.ptOriginalHeight+'px - 24px)';details.scrollTop=0;}
                if(cover)cover.hidden=true;
            }else{
                if(cover)cover.hidden=false;
                if(details){details.hidden=true;details.style.maxHeight='';details.scrollTop=0;}
                card.style.height='';
                card.style.maxHeight='';
                card.style.minHeight='';
                delete card.dataset.ptOriginalHeight;
            }
        };
        function ptTradeDate(t){
            var idx=t.slTriggerIndex!=null?t.slTriggerIndex:(t.tpTriggerIndex!=null?t.tpTriggerIndex:(t.triggerIndex!=null?t.triggerIndex:t.entryIndex));
            var c=(idx!=null&&PT_CANDLES[idx])?PT_CANDLES[idx]:null;
            var raw=c?(c.time||c.open_time): (t.slTriggerTime||t.tpTriggerTime||t.entryTriggerTime||t.entryTime);
            if(!raw)return null;
            var d=new Date(String(raw).replace(' ','T'));
            return Number.isNaN(d.getTime())?null:d;
        }
        function ptTradeValue(t){return t.outcome==='stoploss hit'?-1:(t.outcome==='take profit reached'?Math.max(0,Number(t.riskReward)||0):0);}
        function ptPeriodStats(items){
            var closedItems=items.filter(function(t){return t.outcome==='stoploss hit'||t.outcome==='take profit reached';});
            var lost=closedItems.filter(function(t){return t.outcome==='stoploss hit';}).length;
            var rewards=closedItems.reduce(function(a,t){return a+(t.outcome==='take profit reached'?Math.max(0,Number(t.riskReward)||0):0);},0);
            return {trades:closedItems,lost:lost,rewards:rewards,net:rewards-lost};
        }
        function ptTradeList(items){
            if(!items.length)return '<div class="pt-dd-empty">No closed trades in this period.</div>';
            return '<div class="pt-dd-sequence">'+items.map(function(t){
                var v=ptTradeValue(t),label=v<0?'-1R':'+'+fmtRR(v)+'R';
                return '<button type="button" class="pt-dd-chip '+(v<0?'loss':'profit')+'" title="Locate trade '+t.familyNumber+' on chart" onclick="ptLocateTrade(\''+ptEscapeHtml(t.rootId)+'\','+Number(t.familyNumber||0)+')">'+ptEscapeHtml(label)+' <span>#'+ptEscapeHtml(t.familyNumber)+'</span></button>';
            }).join('')+'</div>';
        }
        function ptPeriodCard(title,items,rangeLabel,buttonHtml){
            var st=ptPeriodStats(items),net=(st.net>=0?'+':'')+fmtRR(st.net)+'R';
            return '<article class="pt-provision-period"><div class="pt-provision-period-head"><strong>'+ptEscapeHtml(title)+'</strong><span>'+ptEscapeHtml(rangeLabel||'')+'</span></div>'+
                '<div class="pt-provision-number">'+st.trades.length+' total trades</div><div class="pt-provision-caption">Trades</div>'+ptTradeList(st.trades)+
                '<div class="pt-dd-totals"><div><span>Lost risks</span><strong>'+st.lost+'R</strong></div><div><span>Risk rewards</span><strong>+'+fmtRR(st.rewards)+'R</strong></div></div>'+
                '<div class="pt-provision-revenue">Risk reward revenue: <b class="'+(st.net<0?'is-loss':'is-profit')+'">'+net+'</b></div>'+(buttonHtml||'')+'</article>';
        }
        function ptProvisionMonths(items,scopeKey){
            var candles=PT_CANDLES||[],groups={};
            candles.forEach(function(c){var d=new Date(String(c.time||c.open_time||'').replace(' ','T'));if(Number.isNaN(d.getTime()))return;var k=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');if(!groups[k])groups[k]={key:k,dates:[],candleCount:0};groups[k].dates.push(d);groups[k].candleCount++;});
            items.forEach(function(t){var d=ptTradeDate(t);if(!d)return;var k=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');if(groups[k]){if(!groups[k].items)groups[k].items=[];groups[k].items.push(t);}});
            var months=Object.keys(groups).sort().reverse();
            var count=months.length, label=count>1?count+' months':(count===1?'1 month':'current month');
            var html='<div class="pt-provision-nav"><strong>Trades Provision</strong><button type="button" class="pt-dd-button secondary" onclick="ptProvisionToggle(\''+scopeKey+'\',false)">Back to summary</button></div><div class="pt-provision-count">'+ptEscapeHtml(label)+' available from loaded chart candles</div>';
            if(!count)return html+'<div class="pt-rd-empty">No dated candle data is loaded for this timeframe.</div>';
            html+='<div class="pt-provision-grid pt-provision-month-grid">';
            months.forEach(function(k){
                var g=groups[k],dates=g.dates.sort(function(a,b){return a-b;}),first=dates[0],last=dates[dates.length-1];
                var monthName=first.toLocaleString(undefined,{month:'short',year:'numeric'});
                var range=first.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'})+' — '+last.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});
                var id='pt-provision-'+String(scopeKey).replace(/[^a-zA-Z0-9_-]/g,'-');
                html+=ptPeriodCard(monthName,g.items||[],range,'<button class="pt-dd-button" type="button" onclick="ptProvisionShowWeeks(\''+id+'\',\''+k+'\',\''+scopeKey+'\')">See the weeks</button>');
            });
            html+='</div>';
            return html;
        }
        window.ptProvisionShowWeeks=function(id,monthKey,scopeKey){
            var panel=document.getElementById(id);if(!panel)return;
            var items=window.PT_PROVISION_ITEMS&&window.PT_PROVISION_ITEMS[scopeKey]||[];
            var parts=monthKey.split('-'),year=Number(parts[0]),month=Number(parts[1])-1;
            var monthStart=new Date(year,month,1),monthEnd=new Date(year,month+1,0,23,59,59,999);
            var candles=PT_CANDLES||[],hasCandle=function(a,b){return candles.some(function(c){var d=new Date(String(c.time||c.open_time||'').replace(' ','T'));return !Number.isNaN(d.getTime())&&d>=a&&d<=b;});};
            var html='<div class="pt-provision-nav"><button class="pt-dd-button secondary" onclick="ptProvisionBack(\''+id+'\',\''+scopeKey+'\')">← Back</button><strong>'+monthStart.toLocaleString(undefined,{month:'long',year:'numeric'})+' · Weeks</strong></div><div class="pt-provision-grid">';
            var cursor=new Date(monthStart);cursor.setDate(cursor.getDate()-cursor.getDay());var weekNo=1;
            while(cursor<=monthEnd){
                var a=new Date(cursor),b=new Date(cursor);b.setDate(b.getDate()+6);b.setHours(23,59,59,999);
                var inMonth=a<=monthEnd&&b>=monthStart;
                if(inMonth){
                    var weekItems=items.filter(function(t){var d=ptTradeDate(t);return d&&d>=a&&d<=b;});
                    var title='Week '+weekNo,range=a.toLocaleDateString(undefined,{month:'short',day:'numeric'})+' — '+b.toLocaleDateString(undefined,{month:'short',day:'numeric'});
                    var weekStats=ptPeriodStats(weekItems);
                    html+='<article class="pt-provision-period"><div class="pt-provision-period-head"><strong>'+title+'</strong><span>'+range+'</span></div>'+
                        '<div class="pt-provision-number">'+weekStats.trades.length+' total trades</div>';
                    if(!hasCandle(a,b))html+='<div class="pt-dd-empty">No data for this week.</div>';
                    else {
                        html+='<div class="pt-provision-caption">Trades</div>'+ptTradeList(weekStats.trades);
                        var st=weekStats;html+='<div class="pt-dd-totals"><div><span>Lost risks</span><strong>'+st.lost+'R</strong></div><div><span>Risk rewards</span><strong>+'+fmtRR(st.rewards)+'R</strong></div></div><div class="pt-provision-revenue">Risk reward revenue: <b class="'+(st.net<0?'is-loss':'is-profit')+'">'+(st.net>=0?'+':'')+fmtRR(st.net)+'R</b></div>';
                        html+='<button class="pt-dd-button" onclick="ptProvisionShowDays(\''+id+'\',\''+scopeKey+'\',\''+monthKey+'\','+a.getFullYear()+','+a.getMonth()+','+a.getDate()+')">See days</button>';
                    }
                    html+='</article>';weekNo++;
                }
                cursor.setDate(cursor.getDate()+7);
            }
            html+='</div>';panel.innerHTML=html;panel.scrollTop=0;
        };
        window.ptProvisionShowDays=function(id,scopeKey,monthKey,y,m,d){
            var panel=document.getElementById(id);if(!panel)return;var items=window.PT_PROVISION_ITEMS&&window.PT_PROVISION_ITEMS[scopeKey]||[];
            var weekStart=new Date(y,m,d);weekStart.setHours(0,0,0,0);
            var html='<div class="pt-provision-nav"><button class="pt-dd-button secondary" onclick="ptProvisionShowWeeks(\''+id+'\',\''+monthKey+'\',\''+scopeKey+'\')">← Back to weeks</button><strong>Week days · Sun–Sat</strong></div><div class="pt-provision-grid">';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function(dayName,i){
                var day=new Date(weekStart);day.setDate(weekStart.getDate()+i);
                var a=new Date(day);a.setHours(0,0,0,0);var b=new Date(day);b.setHours(23,59,59,999);
                var list=items.filter(function(t){var dt=ptTradeDate(t);return dt&&dt>=a&&dt<=b;});
                var st=ptPeriodStats(list),title=day.toLocaleDateString(undefined,{weekday:'short',month:'short',day:'numeric',year:'numeric'});
                var candleExists=(PT_CANDLES||[]).some(function(c){var dt=new Date(String(c.time||c.open_time||'').replace(' ','T'));return !Number.isNaN(dt.getTime())&&dt>=a&&dt<=b;});
                html+='<article class="pt-provision-period"><div class="pt-provision-period-head"><strong>'+dayName+'</strong><span>'+ptEscapeHtml(title)+'</span></div>';
                if(!candleExists)html+='<div class="pt-dd-empty">No data for this day.</div>';
                else html+='<div class="pt-provision-number">'+st.trades.length+' total trades</div><div class="pt-provision-caption">Trades</div>'+ptTradeList(st.trades)+
                    '<div class="pt-dd-totals"><div><span>Lost risks</span><strong>'+st.lost+'R</strong></div><div><span>Risk rewards</span><strong>+'+fmtRR(st.rewards)+'R</strong></div></div><div class="pt-provision-revenue">Risk reward revenue: <b class="'+(st.net<0?'is-loss':'is-profit')+'">'+(st.net>=0?'+':'')+fmtRR(st.net)+'R</b></div>';
                html+='</article>';
            });
            html+='</div>';panel.innerHTML=html;panel.scrollTop=0;
        };
        window.ptProvisionBack=function(id,scopeKey){var panel=document.getElementById(id);if(!panel)return;panel.innerHTML=ptProvisionMonths(window.PT_PROVISION_ITEMS&&window.PT_PROVISION_ITEMS[scopeKey]||[],scopeKey);panel.scrollTop=0;};
        window.ptProvisionToggle=function(scopeKey,show){
            var summary=document.getElementById('pt-summary-'+scopeKey),panel=document.getElementById('pt-provision-'+String(scopeKey).replace(/[^a-zA-Z0-9_-]/g,'-'));
            if(summary)summary.hidden=!!show;if(panel){panel.hidden=!show;if(show){panel.innerHTML=ptProvisionMonths(window.PT_PROVISION_ITEMS&&window.PT_PROVISION_ITEMS[scopeKey]||[],scopeKey);panel.scrollTop=0;}}
        };
        function dashboard(items,scopeKey){
            var x=stats(items),key=String(scopeKey).replace(/[^a-zA-Z0-9_-]/g,'-');
            window.PT_PROVISION_ITEMS=window.PT_PROVISION_ITEMS||{};window.PT_PROVISION_ITEMS[scopeKey]=items.slice();
            return '<button type="button" class="pt-dd-button pt-provision-toggle" onclick="ptProvisionToggle(\''+scopeKey+'\',true)">Trades Provision</button>'+
                '<div id="pt-summary-'+key+'"><div class="pt-rd-dashboard">'+
                card('Total Trades',x.total,'pending','fa-list-check','Total matched families')+
                card('Stoploss hit',x.sl,'loss','fa-arrow-trend-down')+
                card('Lost Risks',fmtRR(x.lostR)+'R','loss','fa-shield-halved','Each stoploss = −1R · '+x.sl+' losing families')+
                card('Profit reached',x.tp,'profit','fa-arrow-trend-up')+
                card('Risk rewards',(x.winsR>=0?'+':'')+fmtRR(x.winsR)+'R','profit','fa-gift','Sum of '+x.tp+' winning families')+
                card('Win rate',fmtRR(x.winRate)+'%','active','fa-bullseye',x.tp+' wins / '+x.closed+' closed')+
                card('Consecutive Loss',x.maxLoss,'loss','fa-arrow-trend-down','Longest losing streak before a profitable trade')+
                card('Consecutive Win',x.maxWin,'profit','fa-trophy','Longest winning streak without a stoploss')+
                drawdownCard(x,scopeKey)+highestRevenuePeakCard(x,scopeKey)+
                card('Current virtual balance',fmtRR(x.currentVirtualBalance)+'%',x.currentVirtualBalance>=100?'profit':'loss','fa-scale-balanced','100% baseline − all lost risks + all profitable risk rewards')+
                card('Pending entries',x.pending,'pending','fa-hourglass-half')+
                card('In trade',x.active,'active','fa-chart-line')+'</div></div>'+
                '<div class="pt-provision-panel" id="pt-provision-'+key+'" hidden><div class="pt-provision-nav"><strong>Trades Provision</strong><button type="button" class="pt-dd-button secondary" onclick="ptProvisionToggle(\''+scopeKey+'\',false)">Back to summary</button></div></div>';
        }
        // Restore the global summary for all native root trade families on the viewed
        // timeframe. Projection records remain excluded, and the per-root summaries below
        // remain isolated. Global consecutive streaks reset at root boundaries.
        var html='';
        if(!roots.length){
            html='<div class="pt-rd-empty">No root data for '+ptEscapeHtml(PT_CURRENT_SYMBOL)+' · '+ptEscapeHtml(PT_CURRENT_TIMEFRAME)+'. The currently viewed timeframe is not configured in any active root.</div>';
            body.innerHTML=html;
            if(status)status.textContent='No native root data · '+PT_CURRENT_SYMBOL+' · '+PT_CURRENT_TIMEFRAME+'. Configure this timeframe in a root to see its data here. Projection data is excluded.';
            return;
        }
        html+='<section class="pt-rd-global-summary"><div style="font-size:1.05rem;font-weight:800;margin:0 0 12px;display:flex;align-items:center;gap:9px"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Global Summary <span style="font-size:.78rem;font-weight:600;color:var(--pt-muted)">· '+all.length+' native trade families across '+roots.length+' root(s)</span></div>';
        html+=dashboard(all,'global');
        html+='</section><div style="font-size:1.02rem;font-weight:800;margin:22px 0 12px;display:flex;align-items:center;gap:9px"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Per-Root Summaries</div>';
        roots.forEach(function(root){
            var items=all.filter(function(t){return String(t.rootId)===String(root.id);});
            var rs=stats(items);
            // Each root's detailed summary lives inside its foldable content.
            html+='<details class="pt-rd-root"><summary><span class="pt-rd-root-title"><i class="fa-solid fa-layer-group"></i> '+ptEscapeHtml(root.name||('Root '+root.id))+'</span><span class="pt-rd-root-summary">'+items.length+' families · <b class="is-profit">'+rs.tp+' profit</b> · <b class="is-loss">'+rs.sl+' stop</b></span><i class="fa-solid fa-chevron-down pt-rd-chevron"></i></summary>';
            html+='<div class="pt-rd-root-stats"><span>Pending <b>'+rs.pending+'</b></span><span>In trade <b>'+rs.active+'</b></span><span>Closed <b>'+rs.closed+'</b></span></div>';
            html+=dashboard(items,'root-'+root.id);
            if(!items.length){html+='<div class="pt-rd-empty">No complete matched trade families for this root in the current timeframe.</div>';}
            // Render individual trade families newest-first; analytics above retain their own chronological order.
            var displayItems=items.slice().sort(function(a,b){
                var ai=a.triggerIndex!=null?a.triggerIndex:(a.slTriggerIndex!=null?a.slTriggerIndex:(a.tpTriggerIndex!=null?a.tpTriggerIndex:a.entryIndex));
                var bi=b.triggerIndex!=null?b.triggerIndex:(b.slTriggerIndex!=null?b.slTriggerIndex:(b.tpTriggerIndex!=null?b.tpTriggerIndex:b.entryIndex));
                return Number(bi||0)-Number(ai||0);
            });
            displayItems.forEach(function(t){
                var stateClass=t.outcome==='stoploss hit'?'loss':(t.outcome==='take profit reached'?'profit':(t.outcome==='in trade'?'active':'pending'));
                var rrDisplay=(t.riskReward!==null && Number.isFinite(Number(t.riskReward)))?'1 : '+Number(t.riskReward).toLocaleString(undefined,{maximumFractionDigits:3}):'1 : unavailable';
                html+='<article class="pt-rd-trade"><div class="pt-rd-trade-head"><div><span class="pt-rd-family">FAMILY '+t.familyNumber+'</span><span class="pt-rd-order">'+ptEscapeHtml(t.orderType)+'</span></div><span class="pt-rd-badge '+stateClass+'">'+ptEscapeHtml(t.outcome)+'</span></div>';
                html+='<div class="pt-rd-metrics"><div><span>Entry price</span><strong>'+ptEscapeHtml(t.entryPrice)+'</strong><small>'+ptEscapeHtml(t.entryTime)+'</small></div><div><span>Stoploss price</span><strong>'+ptEscapeHtml(t.slPrice)+'</strong><small>'+ptEscapeHtml(t.slTime)+'</small></div><div><span>Take profit</span><strong>'+ptEscapeHtml(t.tpPrice==null?'—':t.tpPrice)+'</strong><small>'+ptEscapeHtml(t.tpType==='risk reward'?'Risk/reward target':'Configured price level · minimum 1:1 enforced')+'</small></div><div><span>Family Risk Reward</span><strong>'+ptEscapeHtml(rrDisplay)+'</strong><small>Reward distance ÷ risk distance</small></div></div>';
                html+='<div class="pt-rd-details"><div><span>Entry at</span><b>'+ptEscapeHtml(t.entryName)+'</b></div><div><span>Stoploss at</span><b>'+ptEscapeHtml(t.slName)+'</b></div>';
                if(t.tpType==='risk reward') html+='<div><span>Take profit type</span><b>Risk/reward target</b></div>';
                else html+='<div><span>Take profit at</span><b>'+ptEscapeHtml(t.tpName||'—')+' · '+ptEscapeHtml(t.tpTime||'—')+'</b></div>';
                html+='<div><span>Family Risk Reward</span><b>'+ptEscapeHtml(rrDisplay)+'</b></div>';
                html+='<div><span>Entry trigger</span><b>'+ptEscapeHtml(t.entryTriggerTime||'Waiting for trigger candle')+'</b></div>';
                if(t.outcome==='stoploss hit')html+='<div><span>Stoploss hit</span><b>'+ptEscapeHtml(t.slTriggerTime||'—')+'</b></div>';
                if(t.outcome==='take profit reached')html+='<div><span>Profit reached</span><b>'+ptEscapeHtml(t.tpTriggerTime||'—')+'</b></div>';
                html+='</div><div class="pt-rd-trade-actions"><button type="button" class="pt-dd-locate" onclick="ptLocateTrade(\''+ptEscapeHtml(t.rootId)+'\','+Number(t.familyNumber||0)+',\''+ptEscapeHtml(t.sourceTimeframe||'')+'\')"><i class="fa-solid fa-crosshairs" aria-hidden="true"></i> Locate on chart</button></div></article>';
            });
            html+='</details>';
        });
        body.innerHTML=html;
        if(status)status.textContent='Native root data · '+PT_CURRENT_SYMBOL+' · '+PT_CURRENT_TIMEFRAME+' · '+roots.length+' configured root(s), '+all.length+' matched trade families. Higher-timeframe projection data is excluded. Price-level take profit is hard-enforced to at least 1:1.';
    };
    function ptEscapeHtml(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

    function ptGetVirtualCandleJson() {
        var key = PT_VIRTUAL_CANDLE_JSON_KEY + ':' + PT_PROGRAMME_ID + ':' +
            PT_CURRENT_SYMBOL + ':' + PT_CURRENT_TIMEFRAME;
        try { return localStorage.getItem(key) || '[]'; } catch (_) { return '[]'; }
    }
    window.ptGetVirtualCandleJson = ptGetVirtualCandleJson;

    // ==================== HOVER ====================
    function ptHandleMouseMove(e) {
        if (!PT_CANVAS || !PT_CANDLES.length) return;
        if (PT_DRAG_STATE.mode) return;
        var rect = PT_CANVAS.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var step  = PT_CANVAS._ptStep || ptStep();
        var baseX = PT_CANVAS._ptBaseX || (PT_PADDING_LEFT + PT_VIRTUAL_PAD);
        var idx = Math.floor((x - baseX) / step);
        if (idx < 0 || idx >= PT_CANDLES.length) idx = -1;
        var hoveredFamily = null;
        if (idx >= 0 && Array.isArray(PT_ROOT_TRADE_DATA)) {
            hoveredFamily = PT_ROOT_TRADE_DATA.find(function(t) {
                if (!t.minimumRiskRewardApplied) return false;
                var endIndex = Math.max(Number(t.slTriggerIndex == null ? t.entryIndex : t.slTriggerIndex),
                                        Number(t.tpTriggerIndex == null ? t.entryIndex : t.tpTriggerIndex),
                                        Number(t.triggerIndex == null ? t.entryIndex : t.triggerIndex));
                return idx >= Math.min(Number(t.entryIndex), Number(t.triggerIndex == null ? t.entryIndex : t.triggerIndex)) && idx <= endIndex;
            }) || null;
        }
        ptShowFamilyHoverNote(hoveredFamily,e.clientX,e.clientY);
        var inPlotHorizontally = (x >= PT_PADDING_LEFT && x <= (PT_CANVAS.clientWidth - PT_PADDING_RIGHT));
        var newCursorY = inPlotHorizontally ? y : null;
        var changed = (idx !== PT_HOVER_INDEX) || (newCursorY !== PT_CURSOR_Y);
        if (changed) { PT_HOVER_INDEX = idx; PT_CURSOR_Y = newCursorY; ptScheduleDraw(); ptUpdateReadout(); }
    }
    function ptHandleMouseLeave() {
        if (PT_FAMILY_HOVER_NOTE) PT_FAMILY_HOVER_NOTE.style.display='none';
        if (PT_HOVER_INDEX !== -1 || PT_CURSOR_Y !== null) {
            PT_HOVER_INDEX = -1; PT_CURSOR_Y = null;
            ptScheduleDraw(); ptUpdateReadout();
        }
    }
    function ptUpdateReadout() {
        if (PT_HOVER_INDEX < 0 || PT_HOVER_INDEX >= PT_CANDLES.length) {
            document.getElementById('ptReadoutTime').textContent  = '—';
            document.getElementById('ptReadoutOpen').textContent  = '—';
            document.getElementById('ptReadoutHigh').textContent  = '—';
            document.getElementById('ptReadoutLow').textContent   = '—';
            document.getElementById('ptReadoutClose').textContent = '—';
            return;
        }
        var c = PT_CANDLES[PT_HOVER_INDEX];
        document.getElementById('ptReadoutTime').textContent  = ptFormatTime(c.time);
        document.getElementById('ptReadoutOpen').textContent  = ptFormatPrice(c.open);
        document.getElementById('ptReadoutHigh').textContent  = ptFormatPrice(c.high);
        document.getElementById('ptReadoutLow').textContent   = ptFormatPrice(c.low);
        document.getElementById('ptReadoutClose').textContent = ptFormatPrice(c.close);
    }

    // ==================== PAN + PINCH + DOUBLE-CLICK ====================
    var PT_DRAG_STATE = { mode: null, startX: 0, startY: 0, startOffsetX: 0, startOffsetY: 0, moved: false, downAt: 0 };
    var PT_TOUCH_STATE = { pointers: {}, pinchStartDist: 0, pinchStartCandleWidth: 0, pinchMidX: 0, _lastPan: null,
        crosshairTimer:null, crosshairActive:false, touchStartX:0, touchStartY:0, latestTouchX:0, latestTouchY:0 };

    function ptAttachCanvasEvents() {
        PT_CANVAS.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            PT_DRAG_STATE.mode = 'pan';
            ptBeginFastInteraction();
            PT_DRAG_STATE.startX = e.clientX;
            PT_DRAG_STATE.startY = e.clientY;
            PT_DRAG_STATE.startOffsetX = PT_VIEW.offsetX;
            PT_DRAG_STATE.startOffsetY = PT_VIEW.offsetY;
            PT_DRAG_STATE.moved = false;
            PT_DRAG_STATE.downAt = Date.now();
            PT_CANVAS.classList.add('pt-grabbing');
            e.preventDefault();
        });

        window.addEventListener('mousemove', function (e) {
            if (PT_DRAG_STATE.mode === 'pan') {
                ptBeginFastInteraction();
                var dx = e.clientX - PT_DRAG_STATE.startX;
                var dy = e.clientY - PT_DRAG_STATE.startY;
                if (Math.abs(dx) > 3 || Math.abs(dy) > 3) {
                    PT_DRAG_STATE.moved = true;
                    PT_FOLLOW_LATEST = false;
                }
                PT_VIEW.offsetX = PT_DRAG_STATE.startOffsetX - dx;
                PT_VIEW.offsetY = PT_DRAG_STATE.startOffsetY + dy;
                ptClampOffsetX(); ptClampOffsetY();
                ptScheduleDraw(); ptMaybeLoadOlder();
            }
        });
        window.addEventListener('mouseup', function () {
            if (PT_DRAG_STATE.mode === 'pan') {
                PT_DRAG_STATE.mode = null;
                PT_CANVAS.classList.remove('pt-grabbing');
            }
        });

        PT_CANVAS.addEventListener('dblclick', function (e) {
            if (!PT_CANDLES.length) return;
            var rect = PT_CANVAS.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var step  = PT_CANVAS._ptStep || ptStep();
            var baseX = PT_CANVAS._ptBaseX || (PT_PADDING_LEFT + PT_VIRTUAL_PAD);
            var idx = Math.floor((x - baseX) / step);
            if (idx < 0 || idx >= PT_CANDLES.length) return;
            ptOpenCandleDetailModal(idx);
        });

        PT_CANVAS.addEventListener('wheel', function (e) {
            e.preventDefault();
            ptBeginFastInteraction();
            var rect = PT_CANVAS.getBoundingClientRect();
            var mx = e.clientX - rect.left;
            var factor = e.deltaY > 0 ? 0.9 : 1.1;
            var stepBefore = ptStep();
            var baseXBefore = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
            var idxUnderCursor = (mx - baseXBefore) / stepBefore;
            PT_VIEW.candleWidth *= factor;
            PT_VIEW.candleWidth = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, PT_VIEW.candleWidth));
            PT_VIEW.candleSpacing = Math.max(0.4, PT_VIEW.candleWidth * 0.35);
            var stepAfter = ptStep();
            PT_VIEW.offsetX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + idxUnderCursor * stepAfter - mx;
            PT_FOLLOW_LATEST = false;
            ptClampOffsetX(); ptScheduleDraw(); ptMaybeLoadOlder();
        }, { passive: false });

        PT_CANVAS.addEventListener('touchstart', function (e) {
            ptBeginFastInteraction();
            if (e.touches.length === 1) {
                var firstTouch=e.touches[0];
                PT_TOUCH_STATE.touchStartX=PT_TOUCH_STATE.latestTouchX=firstTouch.clientX;
                PT_TOUCH_STATE.touchStartY=PT_TOUCH_STATE.latestTouchY=firstTouch.clientY;
                PT_TOUCH_STATE.crosshairActive=false;
                if (PT_TOUCH_STATE.crosshairTimer) clearTimeout(PT_TOUCH_STATE.crosshairTimer);
                PT_TOUCH_STATE.crosshairTimer=setTimeout(function(){
                    if (!PT_TOUCH_STATE.pointers || Object.keys(PT_TOUCH_STATE.pointers).length!==1) return;
                    PT_TOUCH_STATE.crosshairActive=true;
                    var rect=PT_CANVAS.getBoundingClientRect();
                    var x=PT_TOUCH_STATE.latestTouchX-rect.left,y=PT_TOUCH_STATE.latestTouchY-rect.top;
                    var step=PT_CANVAS._ptStep||ptStep(),baseX=PT_CANVAS._ptBaseX||(PT_PADDING_LEFT+PT_VIRTUAL_PAD);
                    var idx=Math.floor((x-baseX)/step);PT_HOVER_INDEX=(idx>=0&&idx<PT_CANDLES.length)?idx:-1;
                    PT_CURSOR_Y=(x>=PT_PADDING_LEFT&&x<=PT_CANVAS.clientWidth-PT_PADDING_RIGHT)?y:null;
                    ptScheduleDraw();ptUpdateReadout();
                },5000);
            } else {
                if (PT_TOUCH_STATE.crosshairTimer) clearTimeout(PT_TOUCH_STATE.crosshairTimer);
                PT_TOUCH_STATE.crosshairActive=false;
            }
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                PT_TOUCH_STATE.pointers[t.identifier] = { x: t.clientX, y: t.clientY };
            }
            ptUpdatePinchBaseline();
            e.preventDefault();
            if (PT_MOBILE_CROSSHAIR_MODE && e.touches.length === 1) {
                var selectedTouch = e.touches[0];
                PT_TOUCH_STATE.crosshairActive = true;
                PT_TOUCH_STATE.latestTouchX = selectedTouch.clientX;
                PT_TOUCH_STATE.latestTouchY = selectedTouch.clientY;
                ptSetMobileCrosshairAt(selectedTouch.clientX, selectedTouch.clientY);
                PT_TOUCH_STATE._lastPan = null;
                return;
            }
        }, { passive: false });

        PT_CANVAS.addEventListener('touchmove', function (e) {
            e.preventDefault();
            ptBeginFastInteraction();
            if (PT_MOBILE_CROSSHAIR_MODE && e.touches.length === 1) {
                var crosshairTouch = e.touches[0];
                PT_TOUCH_STATE.crosshairActive = true;
                PT_TOUCH_STATE.latestTouchX = crosshairTouch.clientX;
                PT_TOUCH_STATE.latestTouchY = crosshairTouch.clientY;
                ptSetMobileCrosshairAt(crosshairTouch.clientX, crosshairTouch.clientY);
                PT_TOUCH_STATE._lastPan = null;
                return;
            }
            if (e.touches.length===1) { PT_TOUCH_STATE.latestTouchX=e.touches[0].clientX; PT_TOUCH_STATE.latestTouchY=e.touches[0].clientY; }
            if (PT_TOUCH_STATE.crosshairActive && e.touches.length===1) {
                var touch=e.touches[0],rect=PT_CANVAS.getBoundingClientRect(),x=touch.clientX-rect.left,y=touch.clientY-rect.top;
                var step=PT_CANVAS._ptStep||ptStep(),baseX=PT_CANVAS._ptBaseX||(PT_PADDING_LEFT+PT_VIRTUAL_PAD);
                var idx=Math.floor((x-baseX)/step);PT_HOVER_INDEX=(idx>=0&&idx<PT_CANDLES.length)?idx:-1;
                PT_CURSOR_Y=(x>=PT_PADDING_LEFT&&x<=PT_CANVAS.clientWidth-PT_PADDING_RIGHT)?y:null;
                ptScheduleDraw();ptUpdateReadout();
                return;
            }
            if (PT_TOUCH_STATE.crosshairTimer && e.touches.length===1) {
                var moving=e.touches[0];
                if (Math.hypot(moving.clientX-PT_TOUCH_STATE.touchStartX,moving.clientY-PT_TOUCH_STATE.touchStartY)>18) {
                    clearTimeout(PT_TOUCH_STATE.crosshairTimer);PT_TOUCH_STATE.crosshairTimer=null;
                }
            }
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                PT_TOUCH_STATE.pointers[t.identifier] = { x: t.clientX, y: t.clientY };
            }
            var touches = [];
            for (var id in PT_TOUCH_STATE.pointers) touches.push(PT_TOUCH_STATE.pointers[id]);
            if (touches.length === 1) {
                var p = touches[0];
                if (PT_TOUCH_STATE._lastPan) {
                    var dx = p.x - PT_TOUCH_STATE._lastPan.x;
                    var dy = p.y - PT_TOUCH_STATE._lastPan.y;
                    PT_VIEW.offsetX -= dx; PT_VIEW.offsetY += dy;
                    PT_FOLLOW_LATEST = false;
                    ptClampOffsetX(); ptClampOffsetY();
                    ptScheduleDraw(); ptMaybeLoadOlder();
                }
                PT_TOUCH_STATE._lastPan = { x: p.x, y: p.y };
                PT_TOUCH_STATE.pinchStartDist = 0;
            } else if (touches.length >= 2) {
                var a = touches[0], b = touches[1];
                var dist = Math.hypot(a.x - b.x, a.y - b.y);
                var midX = (a.x + b.x) / 2;
                if (!PT_TOUCH_STATE.pinchStartDist) {
                    PT_TOUCH_STATE.pinchStartDist = dist;
                    PT_TOUCH_STATE.pinchStartCandleWidth = PT_VIEW.candleWidth;
                    PT_TOUCH_STATE.pinchMidX = midX;
                } else {
                    var scale = dist / PT_TOUCH_STATE.pinchStartDist;
                    var newCW = PT_TOUCH_STATE.pinchStartCandleWidth * scale;
                    newCW = Math.max(PT_VIEW.minCandleWidth, Math.min(PT_VIEW.maxCandleWidth, newCW));
                    var rect = PT_CANVAS.getBoundingClientRect();
                    var mx = midX - rect.left;
                    var stepBefore = ptStep();
                    var baseBefore = PT_PADDING_LEFT + PT_VIRTUAL_PAD - PT_VIEW.offsetX;
                    var idxUnder = (mx - baseBefore) / stepBefore;
                    PT_VIEW.candleWidth = newCW;
                    PT_VIEW.candleSpacing = Math.max(0.4, newCW * 0.35);
                    var stepAfter = ptStep();
                    PT_VIEW.offsetX = PT_PADDING_LEFT + PT_VIRTUAL_PAD + idxUnder * stepAfter - mx;
                    PT_FOLLOW_LATEST = false;
                    ptClampOffsetX(); ptScheduleDraw(); ptMaybeLoadOlder();
                }
                PT_TOUCH_STATE._lastPan = null;
            }
        }, { passive: false });

        PT_CANVAS.addEventListener('touchend', function (e) {
            if (PT_TOUCH_STATE.crosshairTimer) { clearTimeout(PT_TOUCH_STATE.crosshairTimer); PT_TOUCH_STATE.crosshairTimer=null; }
            if (PT_TOUCH_STATE.crosshairActive) {
                PT_TOUCH_STATE.crosshairActive=false;PT_HOVER_INDEX=-1;PT_CURSOR_Y=null;
                ptScheduleDraw();ptUpdateReadout();
            }
            for (var i = 0; i < e.changedTouches.length; i++) {
                var t = e.changedTouches[i];
                delete PT_TOUCH_STATE.pointers[t.identifier];
            }
            PT_TOUCH_STATE.pinchStartDist = 0;
            PT_TOUCH_STATE._lastPan = null;
            ptUpdatePinchBaseline();
        });
        PT_CANVAS.addEventListener('touchcancel', function () {
            if (PT_TOUCH_STATE.crosshairTimer) clearTimeout(PT_TOUCH_STATE.crosshairTimer);
            PT_TOUCH_STATE.crosshairTimer=null;PT_TOUCH_STATE.crosshairActive=false;PT_HOVER_INDEX=-1;PT_CURSOR_Y=null;
            PT_TOUCH_STATE.pointers = {};
            PT_TOUCH_STATE.pinchStartDist = 0;
            PT_TOUCH_STATE._lastPan = null;
        });

        PT_CANVAS.addEventListener('mousemove', ptHandleMouseMove);
        PT_CANVAS.addEventListener('mouseleave', ptHandleMouseLeave);
    }

    function ptUpdatePinchBaseline() {
        var ids = Object.keys(PT_TOUCH_STATE.pointers);
        if (ids.length === 1) {
            var p = PT_TOUCH_STATE.pointers[ids[0]];
            PT_TOUCH_STATE._lastPan = { x: p.x, y: p.y };
        } else PT_TOUCH_STATE._lastPan = null;
    }

    // ==================== CLAMPING ====================
    function ptClampOffsetX() {
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vw = card.clientWidth;
        var totalW = PT_PADDING_LEFT + PT_VIRTUAL_PAD + ptContentW() + PT_VIRTUAL_PAD + PT_PADDING_RIGHT;
        var minOffset = 0;
        var maxOffset = Math.max(0, totalW - vw);
        if (PT_VIEW.offsetX < minOffset) PT_VIEW.offsetX = minOffset;
        if (PT_VIEW.offsetX > maxOffset) PT_VIEW.offsetX = maxOffset;
    }
    function ptClampOffsetY() {
        var card = document.getElementById('ptChartCard');
        if (!card) return;
        var vh = card.clientHeight;
        var maxY = vh * 1.2, minY = -vh * 1.2;
        if (PT_VIEW.offsetY < minY) PT_VIEW.offsetY = minY;
        if (PT_VIEW.offsetY > maxY) PT_VIEW.offsetY = maxY;
    }

    // ==================== SCALE BAR ====================
    var PT_SCALE_DRAG = { active: false, startY: 0, startPriceScale: 1.0 };
    function ptAttachScaleBarEvents() {
        var bar = document.getElementById('ptScaleBar');
        if (!bar) return;
        function start(e) {
            if (PT_SCALE_DRAG.active) return;
            PT_SCALE_DRAG.active = true;
            PT_SCALE_DRAG.startY = (e.touches ? e.touches[0].clientY : e.clientY);
            PT_SCALE_DRAG.startPriceScale = PT_VIEW.priceScale;
            bar.classList.add('pt-active');
            e.preventDefault(); e.stopPropagation();
        }
        function move(e) {
            if (!PT_SCALE_DRAG.active) return;
            var y = (e.touches ? e.touches[0].clientY : e.clientY);
            var dy = y - PT_SCALE_DRAG.startY;
            var factor = Math.exp(dy / 300);
            var s = PT_SCALE_DRAG.startPriceScale * factor;
            s = Math.max(0.1, Math.min(10, s));
            PT_VIEW.priceScale = s;
            ptScheduleDraw();
            e.preventDefault(); e.stopPropagation();
        }
        function end() {
            if (!PT_SCALE_DRAG.active) return;
            PT_SCALE_DRAG.active = false;
            bar.classList.remove('pt-active');
        }
        bar.addEventListener('mousedown', start);
        window.addEventListener('mousemove', move);
        window.addEventListener('mouseup', end);
        bar.addEventListener('touchstart', start, { passive: false });
        bar.addEventListener('touchmove', move, { passive: false });
        bar.addEventListener('touchend', end);
        bar.addEventListener('touchcancel', end);
        bar.addEventListener('dblclick', function () { PT_VIEW.priceScale = 1.0; ptScheduleDraw(); });
    }

    // ==================== THEME SYNC ====================
    (function () {
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                var dark = !!e.data.dark;
                document.body.classList.toggle('dark-mode', dark);
                document.documentElement.classList.toggle('force-light', !dark);
                ptScheduleDraw();
            }
        });
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();

    window.ptOpenSymbolModal      = ptOpenSymbolModal;
    window.ptCloseSymbolModal     = ptCloseSymbolModal;
    window.ptFilterSymbolModal    = ptFilterSymbolModal;
    window.ptSelectSymbol         = ptSelectSymbol;
    window.ptOpenTimeframeModal   = ptOpenTimeframeModal;
    window.ptCloseTimeframeModal  = ptCloseTimeframeModal;
    window.ptFilterTimeframeModal = ptFilterTimeframeModal;
    window.ptPickTimeframe        = ptPickTimeframe;
    window.ptSelectTimeframe      = ptSelectTimeframe;
    window.ptOpenCandleDetail     = ptOpenCandleDetailModal;
    window.ptCloseCandleDetail    = ptCloseCandleDetailModal;

    /* =================================================================
     * ==================== CONFIGURATION SYSTEM =======================
     * =================================================================
     * In-memory model. Nothing is sent to the server, nothing is merged
     * with candle_records. This simply builds configuration rows for
     * roots and trees exactly as described.
     *
     * FOCUS SAFETY:
     *   - Structural changes (add/remove root, tree, drawing, or a
     *     show/hide toggle) call render() which wipes and rebuilds.
     *   - Plain value changes (typing a name, choosing an option)
     *     call onValueChange() which only updates the in-memory model
     *     and the footer status — the DOM is NEVER touched, so focus
     *     and caret position are preserved across keystrokes.
     * ================================================================= */
    (function () {
        'use strict';

        var PT_CFG = {
            roots: [],            // array of root objects
            nextRootId: 1,
            nextTreeId: 1,
            nextCandleId: 1,
            nextMainId: 1, // retained for compatibility with any existing references
            nextRefId:  1,
            nextOffsetId: 1
        };

        var pendingRootDelete = null;

        function openRootDeleteConfirmation(root) {
            pendingRootDelete = root;
            var modal = el('ptRootDeleteModal');
            var message = el('ptRootDeleteMessage');
            var confirmButton = el('ptRootDeleteConfirm');
            if (message) {
                message.textContent = 'Are you sure you want to delete Root ' + root.id + ' configuration? This will remove this root and its configured trees and drawings from the current configuration. This action cannot be undone here.';
            }
            if (confirmButton) confirmButton.disabled = false;
            if (modal) modal.classList.add('active');
        }

        function closeRootDeleteConfirmation() {
            var modal = el('ptRootDeleteModal');
            if (modal) modal.classList.remove('active');
            pendingRootDelete = null;
        }

        function confirmRootDelete() {
            if (!pendingRootDelete) {
                closeRootDeleteConfirmation();
                return;
            }
            var idx = PT_CFG.roots.indexOf(pendingRootDelete);
            if (idx >= 0) PT_CFG.roots.splice(idx, 1);
            closeRootDeleteConfirmation();
            render();
            updateFootStatus();
        }

        var PRICE_LEVELS = [
            'high','low','open','close',
            'candle_center','body_center',
            'high_wick_center','low_wick_center'
        ];
        var PRICE_LEVELS_ALL = ['all'].concat(PRICE_LEVELS);
        var OPERATORS = ['<','>','=<','>=','='];
        var OFFSET_POS_POINTS = ['all','random','fixed'];
        var TP_TYPES = ['risk_reward','price_levels'];
        var DRAW_TO_EXTRA = ['any_intercept_candle'];
        var TEXT_POSITIONS = ['high','low'];
        var COLOR_OPTIONS = [
            {value:'purple',label:'Purple'}, {value:'red',label:'Red'},
            {value:'blue',label:'Blue'}, {value:'green',label:'Green'},
            {value:'yellow',label:'Yellow'}, {value:'orange',label:'Orange'},
            {value:'pink',label:'Pink'}, {value:'cyan',label:'Cyan'},
            {value:'magenta',label:'Magenta'}, {value:'lime',label:'Lime'},
            {value:'teal',label:'Teal'}, {value:'navy',label:'Navy'},
            {value:'gold',label:'Gold'}, {value:'coral',label:'Coral'},
            {value:'violet',label:'Violet'}, {value:'indigo',label:'Indigo'},
            {value:'brown',label:'Brown'}, {value:'gray',label:'Gray'},
            {value:'white',label:'White'}, {value:'black',label:'Black'},
            {value:'custom',label:'Custom (white in dark mode, black in light mode)'}
        ];
        function ptResolveConfiguredColor(value, fallback) {
            var color = String(value || fallback || 'purple').toLowerCase();
            if (color === 'custom') {
                var dark = document.body
                    ? document.body.classList.contains('dark-mode')
                    : !!PT_DARK_MODE;
                return dark ? '#ffffff' : '#000000';
            }
            return color;
        }
        var CANDLE_TYPES = [
            {value:'bearish', label:'bearish'},
            {value:'bullish', label:'bullish'},
            {value:'bearish_or_bullish', label:'bearish_or_bullish'}
        ];
        var DEFAULT_CANDLE_TYPE = 'bearish_or_bullish';

        // ---------- rendering helpers ----------
        function el(id) { return document.getElementById(id); }

        function mkSelect(id, options, value, disabled, onchange) {
            var sel = document.createElement('select');
            sel.id = id;
            if (disabled) sel.disabled = true;
            options.forEach(function (opt) {
                var o = document.createElement('option');
                if (opt && typeof opt === 'object') {
                    o.value = opt.value;
                    o.textContent = opt.label;
                } else {
                    o.value = opt;
                    o.textContent = opt;
                }
                sel.appendChild(o);
            });
            if (value != null) sel.value = value;
            if (onchange) sel.addEventListener('change', onchange);
            return sel;
        }

        function mkInput(id, type, value, placeholder, disabled, oninput) {
            var inp = document.createElement('input');
            inp.id = id;
            inp.type = type || 'text';
            if (value != null) inp.value = value;
            if (placeholder) inp.placeholder = placeholder;
            if (disabled) inp.disabled = true;
            if (oninput) inp.addEventListener('input', oninput);
            return inp;
        }

        function mkField(labelText, control) {
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-field';
            if (labelText) {
                var lab = document.createElement('label');
                lab.textContent = labelText;
                wrap.appendChild(lab);
            }
            wrap.appendChild(control);
            return wrap;
        }

        // ---------- helpers on the model ----------
        function rootById(rootId) {
            for (var i = 0; i < PT_CFG.roots.length; i++) {
                if (PT_CFG.roots[i].id === rootId) return PT_CFG.roots[i];
            }
            return null;
        }

        function treeById(root, treeId) {
            if (!root) return null;
            for (var i = 0; i < root.trees.length; i++) {
                if (root.trees[i].id === treeId) return root.trees[i];
            }
            return null;
        }

        // A tree is saveable only when its required candle selections/names exist.
        // Optional settings already have valid defaults in the model.
        function treeIsConfigured(root, tree, treeIndex) {
            if (!root || !tree || !tree.main || !tree.reference) return false;
            var main = tree.main;
            var ref = tree.reference;
            if (treeIndex === 0) {
                return String(main.name || '').trim() !== '' &&
                       String(ref.name || '').trim() !== '';
            }

            var mainSelection = main.selectedExistingId || main.offsetTo;
            if (!mainSelection || !findCandleById(mainSelection, root)) return false;

            var mode = ref.referenceMode || 'existing';
            if (mode === 'new_offset') {
                return String(ref.name || '').trim() !== '';
            }
            var refSelection = ref.selectedExistingId || ref.existingCandleId;
            return !!(refSelection && findCandleById(refSelection, root));
        }

        function updateTreeAddButtons() {
            document.querySelectorAll('[data-pt-add-tree-root]').forEach(function (button) {
                var root = rootById(parseInt(button.getAttribute('data-pt-add-tree-root'), 10));
                if (!root) { button.disabled = true; return; }
                // Once a root has more than one tree, adding further trees is always allowed.
                if (root.trees.length >= 2) {
                    button.disabled = false;
                    button.title = 'Add another tree';
                    return;
                }
                var firstConfigured = root.trees.length === 1 && treeIsConfigured(root, root.trees[0], 0);
                button.disabled = !firstConfigured;
                button.title = firstConfigured ? 'Add another tree' : 'Configure the first tree main and reference candles first';
            });
        }

        function findCandleById(candleId, rootOnly) {
            if (candleId == null || candleId === '') return null;
            var found = null;
            var rootsToSearch = rootOnly ? [rootOnly] : PT_CFG.roots;
            rootsToSearch.some(function (root) {
                return root.trees.some(function (tree) {
                    return ['main', 'reference', 'offset'].some(function (kind) {
                        var candle = tree[kind];
                        if (candle && String(candle.id) === String(candleId)) {
                            found = { row: candle, kind: kind, tree: tree, root: root };
                            return true;
                        }
                        return false;
                    });
                });
            });
            return found;
        }

        function normalizeCandleType(value) {
            return CANDLE_TYPES.some(function (item) { return item.value === value; })
                ? value : DEFAULT_CANDLE_TYPE;
        }

        function collectOffsetTargets(root, excludeTreeId) {
            var out = [];
            var seenIds = Object.create(null);
            if (!root) return out;

            // A candle referenced by a later tree keeps its original identity.
            // Do not list the same candle again under every tree that reuses it.
            root.trees.forEach(function (tree) {
                if (excludeTreeId && String(tree.id) === String(excludeTreeId)) return;
                ['main', 'reference', 'offset'].forEach(function (kind) {
                    var row = tree[kind];
                    if (!row || row.id == null || !String(row.name || '').trim()) return;

                    var identity = String(row.id);
                    if (seenIds[identity]) return;
                    seenIds[identity] = true;

                    out.push({
                        id: row.id,
                        name: row.name,
                        kind: kind,
                        candleType: normalizeCandleType(
                            row.mainCandleType || row.refCandleType || row.offsetCandle || DEFAULT_CANDLE_TYPE
                        ),
                        treeId: tree.id,
                        rootId: root.id
                    });
                });
            });
            return out;
        }

        // ---------- build one "Main" row ----------
        function buildMainRow(opts) {
            var root = opts.root;
            var tree = opts.tree;
            var row  = opts.row;
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-row';

            var tag = document.createElement('div');
            tag.className = 'pt-cfg-row-tag main';
            tag.innerHTML = '<i class="fa-solid fa-star"></i> Main Candle';
            wrap.appendChild(tag);

            var grid = document.createElement('div');
            grid.className = 'pt-cfg-row-grid';

            // Later trees may only register an existing candle as their main.
            if (!opts.isFirstTree) {
                var targets = collectOffsetTargets(root, tree.id);
                var choices = [{value:'', label:'— select existing candle —'}];
                targets.forEach(function (t) {
                    choices.push({value:String(t.id), label:t.name + ' (' + t.kind + ' · tree ' + t.treeId + ')'});
                });
                var selected = row.selectedExistingId || row.offsetTo || '';
                var existingSel = mkSelect('cfg_main_existing_' + row.id, choices, String(selected), false, function () {
                    var found = null;
                    targets.forEach(function (t) { if (String(t.id) === existingSel.value) found = t; });
                    row.selectedExistingId = found ? found.id : null;
                    row.offsetTo = found ? found.id : null;
                    row.name = found ? found.name : '';
                    row.offsetToName = found ? found.name : '';
                    row.offsetToKind = found ? found.kind : '';
                    if (found) row.mainCandleType = normalizeCandleType(found.candleType || DEFAULT_CANDLE_TYPE);
                    opts.onChange();
                });
                grid.appendChild(mkField('Main candle (existing candle)', existingSel));
                var pl = mkSelect('cfg_main_pl_' + row.id, PRICE_LEVELS, row.priceLevel || 'high', false, function () {
                    row.priceLevel = pl.value; opts.onValueChange();
                });
                grid.appendChild(mkField('Price level', pl));
                var operator = mkSelect('cfg_main_op_' + row.id, OPERATORS, row.operator || '>', false, function () {
                    row.operator = operator.value; opts.onValueChange();
                });
                grid.appendChild(mkField('Operator', operator));
                wrap.appendChild(grid);
                return wrap;
            }

            // A confirmation root's first main candle is anchored directly to a selected
            // parent-root candle timestamp. Its own name remains an independent identity
            // used by later confirmation trees.
            if (root.isConfirmationRoot && opts.isFirstTree) {
                var parentRoot = root.parentRoot || null;
                var anchorOptions = [{value:'', label:'— select existing parent-root candle —'}];
                if (parentRoot && Array.isArray(parentRoot.trees)) {
                    parentRoot.trees.forEach(function (parentTree) {
                        ['main','reference','offset'].forEach(function (kind) {
                            var candle = parentTree[kind] ||
                                (kind === 'offset' && parentTree.reference && parentTree.reference.referenceMode === 'new_offset' ? parentTree.reference : null);
                            if (!candle || !String(candle.name || '').trim()) return;
                            var id = kind === 'main'
                                ? (candle.selectedExistingId || candle.offsetTo || candle.id)
                                : (candle.referenceMode === 'new_offset'
                                    ? candle.id
                                    : (candle.selectedExistingId || candle.existingCandleId || candle.id));
                            var label = candle.name + ' (Tree ' + parentTree.id + ' · ' + kind + ')';
                            if (!anchorOptions.some(function (o) { return String(o.value) === String(id); })) {
                                anchorOptions.push({value:String(id), label:label});
                            }
                        });
                    });
                }
                var anchorSel = mkSelect('cfg_confirmation_anchor_' + root.id,
                    anchorOptions, String(root.anchorCandleId || ''), false, function () {
                        root.anchorCandleId = anchorSel.value;
                        var chosen = anchorOptions.find(function (o) { return String(o.value) === String(anchorSel.value); });
                        root.anchorCandleName = chosen ? chosen.label : '';
                        opts.onValueChange();
                    });
                grid.appendChild(mkField('Select candle to anchor in lower timeframe', anchorSel));

                var anchorTimeSel = mkSelect('cfg_confirmation_anchor_time_' + root.id,
                    [{value:'open_time',label:'Open time'},{value:'close_time',label:'Close time'}],
                    root.anchorTime || 'open_time', false, function () {
                        root.anchorTime = anchorTimeSel.value;
                        opts.onValueChange();
                    });
                grid.appendChild(mkField('Candle time', anchorTimeSel));
            }

            // Main Candle Name
            var nameInput = mkInput('cfg_main_name_' + row.id, 'text', row.name, 'Main candle name', false, function () {
                row.name = nameInput.value;
                if (root.isConfirmationRoot && opts.isFirstTree) {
                    root.anchorMainCandleName = row.name;
                }
                opts.onValueChange();
            });
            grid.appendChild(mkField('Main Candle Name', nameInput));

            var mainTextInput = mkInput('cfg_main_text_' + row.id, 'text', row.candleText || '', 'Text shown on matched candles', false, function () {
                row.candleText = mainTextInput.value; opts.onValueChange();
            });
            grid.appendChild(mkField('Candle text', mainTextInput));
            var mainTextPosition = mkSelect('cfg_main_text_position_' + row.id, TEXT_POSITIONS, row.textPosition || 'high', false, function () {
                row.textPosition = mainTextPosition.value; opts.onValueChange();
            });
            grid.appendChild(mkField('Text position', mainTextPosition));
            var mainTextColor = mkSelect('cfg_main_text_color_' + row.id, COLOR_OPTIONS, row.textColor || 'purple', false, function () {
                row.textColor = mainTextColor.value; opts.onValueChange();
            });
            grid.appendChild(mkField('Text color', mainTextColor));

            // Main candle type is independently configurable for every main row.
            var inheritedMain = row.offsetTo != null ? findCandleById(row.offsetTo) : null;
            row.mainCandleType = normalizeCandleType(row.mainCandleType);
            var mainTypeSel = mkSelect('cfg_main_type_' + row.id, CANDLE_TYPES,
                row.mainCandleType, false, function () {
                    row.mainCandleType = mainTypeSel.value;
                    opts.onValueChange();
                });
            grid.appendChild(mkField('Main Candle Type', mainTypeSel));

            // Offset candle type is its own configurable property and is saved
            // to programme_training_configuration.offset_candle. It is not
            // inferred from the selected offset target.
            if (!opts.isFirstTree) {
                row.offsetCandle = normalizeCandleType(row.offsetCandle);
                var offsetTypeSel = mkSelect('cfg_offset_type_' + row.id,
                    CANDLE_TYPES, row.offsetCandle, false, function () {
                        row.offsetCandle = offsetTypeSel.value;
                        opts.onValueChange();
                    });
                grid.appendChild(mkField('Offset Candle Type', offsetTypeSel));
            }

            // Position
            var positionOptions = opts.isFirstTree
                ? [{value:'all', label:'all'}]
                : [{value:'left', label:'left'}, {value:'right', label:'right'}];
            if (!(root.isConfirmationRoot && opts.isFirstTree)) {
                var posSel = mkSelect('cfg_main_pos_' + row.id, positionOptions, row.position, opts.isFirstTree, function () {
                    row.position = posSel.value;
                    opts.onValueChange();
                });
                grid.appendChild(mkField('Position', posSel));
            }

            // Offset to (only when not first tree)
            if (!opts.isFirstTree) {
                var offsetOptions = [{value:'', label:'— select candle —'}];
                opts.offsetTargets.forEach(function (t) {
                    offsetOptions.push({value: String(t.id), label: t.name + ' (' + t.kind + ' · tree ' + t.treeId + ')'});
                });
                var offsetSel = mkSelect('cfg_main_offset_' + row.id, offsetOptions, row.offsetTo != null ? String(row.offsetTo) : '', false, function () {
                    var v = offsetSel.value;
                    row.offsetTo = v ? parseInt(v, 10) : null;
                    var found = null;
                    opts.offsetTargets.forEach(function (t) { if (String(t.id) === v) found = t; });
                    row.offsetToName = found ? found.name : '';
                    row.offsetToKind = found ? found.kind : '';
                    if (found) {
                        row.mainCandleType = normalizeCandleType(found.candleType || DEFAULT_CANDLE_TYPE);
                    }
                    row.offsetCandle = normalizeCandleType(row.offsetCandle);
                    opts.onChange();
                });
                grid.appendChild(mkField('Offset To', offsetSel));
            }

            // Offset Position + Pos Point (not first tree)
            if (!opts.isFirstTree) {
                var opInput = mkInput('cfg_main_oppos_' + row.id, 'number', row.offsetPosition != null ? row.offsetPosition : '', 'e.g. 1', false, function () {
                    row.offsetPosition = opInput.value === '' ? null : parseInt(opInput.value, 10);
                    opts.onValueChange();
                });
                opInput.min = '1';
                opInput.step = '1';
                grid.appendChild(mkField('Offset Position', opInput));

                var oppSel = mkSelect('cfg_main_oppoint_' + row.id, OFFSET_POS_POINTS, row.offsetPosPoint || 'all', false, function () {
                    row.offsetPosPoint = oppSel.value;
                    opts.onValueChange();
                });
                grid.appendChild(mkField('Offset Pos Point', oppSel));
            }

            // Price Level
            var plSel = mkSelect('cfg_main_pl_' + row.id, PRICE_LEVELS, row.priceLevel || 'high', false, function () {
                row.priceLevel = plSel.value;
                opts.onValueChange();
            });
            grid.appendChild(mkField('Price Level', plSel));

            // Operator
            var opSel = mkSelect('cfg_main_op_' + row.id, OPERATORS, row.operator || '<', false, function () {
                row.operator = opSel.value;
                opts.onValueChange();
            });
            grid.appendChild(mkField('Operator', opSel));

            wrap.appendChild(grid);
            return wrap;
        }

        // ---------- build one "Reference" row ----------
        function buildReferenceRow(opts) {
            var tree = opts.tree;
            var mainRow = tree.main;
            var row  = opts.row;
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-row';

            var tag = document.createElement('div');
            tag.className = 'pt-cfg-row-tag';
            tag.innerHTML = '<i class="fa-solid fa-link"></i> Reference Candle';
            wrap.appendChild(tag);

            var grid = document.createElement('div');
            grid.className = 'pt-cfg-row-grid';

            // Later trees choose either an existing candle reference or a new
            // offset from this tree's main candle.
            if (opts.tree && opts.root.trees.indexOf(opts.tree) > 0) {
                row.referenceMode = row.referenceMode || 'existing';
                var modeSel = mkSelect('cfg_ref_mode_' + row.id, [
                    {value:'existing',label:'Reference existing tree candle'},
                    {value:'new_offset',label:'New offset to this tree main'}
                ], row.referenceMode, false, function () {
                    row.referenceMode = modeSel.value;
                    opts.onChange();
                });
                grid.appendChild(mkField('Reference type', modeSel));
                if (row.referenceMode === 'existing') {
                    var refTargets = collectOffsetTargets(opts.root, opts.tree.id);
                    var refChoices = [{value:'',label:'— select existing candle —'}];
                    refTargets.forEach(function (t) {
                        refChoices.push({value:String(t.id),label:t.name + ' (' + t.kind + ' · tree ' + t.treeId + ')'});
                    });
                    var refSel = mkSelect('cfg_ref_existing_' + row.id, refChoices,
                        String(row.selectedExistingId || row.existingCandleId || ''), false, function () {
                            var chosen = null;
                            refTargets.forEach(function (t) { if (String(t.id) === refSel.value) chosen = t; });
                            row.selectedExistingId = chosen ? chosen.id : null;
                            row.existingCandleId = chosen ? chosen.id : null;
                            row.name = chosen ? chosen.name : '';
                            if (chosen) row.refCandleType = normalizeCandleType(chosen.candleType || DEFAULT_CANDLE_TYPE);
                            opts.onValueChange();
                        });
                    grid.appendChild(mkField('Reference candle', refSel));
                    var refLevel = mkSelect('cfg_ref_pl_' + row.id, PRICE_LEVELS, row.priceLevel || 'high', false, function () {
                        row.priceLevel = refLevel.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Price level', refLevel));
                } else {
                    var offsetToMain = document.createElement('input');
                    offsetToMain.type = 'text'; offsetToMain.disabled = true;
                    offsetToMain.value = (mainRow && mainRow.name ? mainRow.name : 'Selected tree main');
                    grid.appendChild(mkField('Offset to', offsetToMain));
                    var offsetName = mkInput('cfg_new_offset_name_' + row.id, 'text', row.name || '', 'Offset candle name', false, function () {
                        row.name = offsetName.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Offset candle name', offsetName));
                    var offsetText = mkInput('cfg_new_offset_text_' + row.id, 'text', row.candleText || '', 'Text shown on matched candles', false, function () {
                        row.candleText = offsetText.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Candle text', offsetText));
                    var offsetTextPosition = mkSelect('cfg_new_offset_text_position_' + row.id, TEXT_POSITIONS, row.textPosition || 'high', false, function () {
                        row.textPosition = offsetTextPosition.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Text position', offsetTextPosition));
                    var offsetTextColor = mkSelect('cfg_new_offset_text_color_' + row.id, COLOR_OPTIONS, row.textColor || 'purple', false, function () {
                        row.textColor = offsetTextColor.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Text color', offsetTextColor));
                    var offsetType = mkSelect('cfg_new_offset_type_' + row.id, CANDLE_TYPES, row.refCandleType || DEFAULT_CANDLE_TYPE, false, function () {
                        row.refCandleType = offsetType.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Offset candle type', offsetType));
                    var pos = mkSelect('cfg_new_offset_pos_' + row.id, ['left','right'], row.position || 'left', false, function () {
                        row.position = pos.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Position', pos));
                    var offsetPos = mkInput('cfg_new_offset_position_' + row.id, 'number', row.offsetPosition || 1, '1', false, function () {
                        row.offsetPosition = Math.max(1, parseInt(offsetPos.value || '1', 10)); opts.onValueChange();
                    });
                    offsetPos.min = '1'; offsetPos.step = '1';
                    grid.appendChild(mkField('Offset position', offsetPos));
                    var point = mkSelect('cfg_new_offset_point_' + row.id, OFFSET_POS_POINTS, row.offsetPosPoint || 'all', false, function () {
                        row.offsetPosPoint = point.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Offset position point', point));
                    var price = mkSelect('cfg_new_offset_pl_' + row.id, PRICE_LEVELS, row.priceLevel || 'high', false, function () {
                        row.priceLevel = price.value; opts.onValueChange();
                    });
                    grid.appendChild(mkField('Price level', price));
                }
                wrap.appendChild(grid);
                return wrap;
            }

            // Reference candle name
            var nameInput = mkInput('cfg_ref_name_' + row.id, 'text', row.name, 'Reference candle name', false, function () {
                row.name = nameInput.value;
                opts.onValueChange();
            });
            grid.appendChild(mkField('Reference Candle Name', nameInput));

            // The first tree's reference is independently labelable. Later
            // references that point to an existing tree candle inherit that
            // candle's label; a later new-offset reference has its own fields above.
            if (opts.root.trees.indexOf(opts.tree) === 0) {
                var firstRefText = mkInput('cfg_ref_text_' + row.id, 'text', row.candleText || '', 'Text shown on matched reference candles', false, function () {
                    row.candleText = firstRefText.value; opts.onValueChange();
                });
                grid.appendChild(mkField('Candle text', firstRefText));
                var firstRefTextPosition = mkSelect('cfg_ref_text_position_' + row.id, TEXT_POSITIONS, row.textPosition || 'high', false, function () {
                    row.textPosition = firstRefTextPosition.value; opts.onValueChange();
                });
                grid.appendChild(mkField('Text position', firstRefTextPosition));
                var firstRefTextColor = mkSelect('cfg_ref_text_color_' + row.id, COLOR_OPTIONS, row.textColor || 'purple', false, function () {
                    row.textColor = firstRefTextColor.value; opts.onValueChange();
                });
                grid.appendChild(mkField('Text color', firstRefTextColor));
            }

            row.refCandleType = normalizeCandleType(row.refCandleType);
            var refTypeSel = mkSelect('cfg_ref_type_' + row.id, CANDLE_TYPES,
                row.refCandleType, false, function () {
                    row.refCandleType = refTypeSel.value;
                    opts.onValueChange();
                });
            grid.appendChild(mkField('Reference Candle Type', refTypeSel));

            // Position — left/right only
            var posSel = mkSelect('cfg_ref_pos_' + row.id,
                [{value:'left', label:'left'}, {value:'right', label:'right'}],
                row.position || 'left', false, function () {
                    row.position = posSel.value;
                    opts.onValueChange();
                });
            grid.appendChild(mkField('Position', posSel));

            // Offset To — always uneditable, displays main name
            var offsetLabelText = mainRow && mainRow.name ? mainRow.name + ' (this tree main)' : '(this tree main)';
            var offsetDisplay = document.createElement('input');
            offsetDisplay.type = 'text';
            offsetDisplay.disabled = true;
            offsetDisplay.value = offsetLabelText;
            grid.appendChild(mkField('Offset To', offsetDisplay));

            // Offset Position
            var opInput = mkInput('cfg_ref_oppos_' + row.id, 'number', row.offsetPosition != null ? row.offsetPosition : 1, 'e.g. 1', false, function () {
                row.offsetPosition = opInput.value === '' ? null : parseInt(opInput.value, 10);
                opts.onValueChange();
            });
            opInput.min = '1';
            opInput.step = '1';
            grid.appendChild(mkField('Offset Position', opInput));

            // Offset Pos Point
            var oppSel = mkSelect('cfg_ref_oppoint_' + row.id, OFFSET_POS_POINTS, row.offsetPosPoint || 'all', false, function () {
                row.offsetPosPoint = oppSel.value;
                opts.onValueChange();
            });
            grid.appendChild(mkField('Offset Pos Point', oppSel));

            // Price Level
            var plSel = mkSelect('cfg_ref_pl_' + row.id, PRICE_LEVELS, row.priceLevel || 'high', false, function () {
                row.priceLevel = plSel.value;
                opts.onValueChange();
            });
            grid.appendChild(mkField('Price Level', plSel));

            wrap.appendChild(grid);
            return wrap;
        }

        // ---------- build one Tree wrapper ----------
        function buildTree(root, tree, treeIndex, onChange, onValueChange) {
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-tree';

            var head = document.createElement('div');
            head.className = 'pt-cfg-tree-head';

            var tag = document.createElement('div');
            tag.className = 'pt-cfg-tree-tag';
            tag.innerHTML = '<span class="pt-cfg-chip tree">Tree ' + tree.id + '</span>'
                          + (treeIndex === 0 ? 'First tree (root anchor)' : 'Offset tree');
            head.appendChild(tag);

            // An unneeded additional tree can be cancelled without affecting the root anchor.
            if (treeIndex > 0) {
                var cancelTreeBtn = document.createElement('button');
                cancelTreeBtn.type = 'button';
                cancelTreeBtn.className = 'pt-cfg-btn danger pt-cfg-tree-cancel';
                cancelTreeBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Cancel Tree';
                cancelTreeBtn.title = 'Remove this tree from the configuration';
                cancelTreeBtn.addEventListener('click', function () {
                    var idx = root.trees.indexOf(tree);
                    if (idx > 0) root.trees.splice(idx, 1);
                    onChange();
                });
                head.appendChild(cancelTreeBtn);
            }

            // Root timeframes are explicitly selected as a set, not inherited
            // from the currently displayed chart timeframe.
            if (treeIndex === 0 && !root.isConfirmationRoot) {
                var tfWrap = document.createElement('div');
                tfWrap.className = 'pt-cfg-field pt-root-timeframes';
                var tfLabel = document.createElement('label');
                tfLabel.textContent = 'Select timeframes for this root';
                tfWrap.appendChild(tfLabel);
                var tfList = document.createElement('div');
                tfList.className = 'pt-root-timeframe-list';
                (PT_ALL_TIMEFRAMES || []).forEach(function (tf) {
                    var item = document.createElement('label');
                    item.className = 'pt-root-timeframe-option';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.value = tf;
                    cb.checked = Array.isArray(root.timeframes) && root.timeframes.indexOf(tf) !== -1;
                    cb.addEventListener('change', function () {
                        root.timeframes = root.timeframes || [];
                        if (cb.checked && root.timeframes.indexOf(tf) === -1) root.timeframes.push(tf);
                        if (!cb.checked) root.timeframes = root.timeframes.filter(function (x) { return x !== tf; });
                        root.timeframes = (PT_ALL_TIMEFRAMES || []).filter(function (x) { return root.timeframes.indexOf(x) !== -1; });
                        root.timeframe = root.timeframes[0] || '';
                        tfWrap.classList.toggle('pt-timeframe-missing', root.timeframes.length === 0);
                        onChange();
                    });
                    item.appendChild(cb);
                    var text = document.createElement('span');
                    text.textContent = tf;
                    item.appendChild(text);
                    tfList.appendChild(item);
                });
                tfWrap.appendChild(tfList);
                head.appendChild(tfWrap);
            }

            wrap.appendChild(head);

            // Main row
            var mainRow = buildMainRow({
                root: root,
                tree: tree,
                row: tree.main,
                isFirstTree: (treeIndex === 0),
                offsetTargets: collectOffsetTargets(root, tree.id),
                onChange: onChange,
                onValueChange: onValueChange
            });
            wrap.appendChild(mainRow);

            // Reference row
            var refRow = buildReferenceRow({
                root: root,
                tree: tree,
                row: tree.reference,
                onChange: onChange,
                onValueChange: onValueChange
            });
            wrap.appendChild(refRow);

            return wrap;
        }

        // ---------- build the Root wrapper ----------
        function buildRoot(root, onChange, onValueChange) {
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-root' + (root.expanded ? ' is-expanded' : ' is-collapsed');
            wrap.setAttribute('data-root-id', String(root.id));

            var head = document.createElement('div');
            head.className = 'pt-cfg-root-head';

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'pt-cfg-root-toggle';
            toggle.setAttribute('aria-expanded', root.expanded ? 'true' : 'false');
            toggle.innerHTML = '<i class="fa-solid ' + (root.expanded ? 'fa-chevron-down' : 'fa-chevron-right') + '"></i>';
            function toggleRootExpanded() {
                root.expanded = !root.expanded;
                onChange();
            }
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleRootExpanded();
            });
            head.appendChild(toggle);

            var tag = document.createElement('div');
            tag.className = 'pt-cfg-root-tag';
            tag.innerHTML = '<span class="pt-cfg-root-name-display"><strong>'
                          + ptEscapeHtml(root.name || 'Unnamed root') + '</strong></span>'
                          + '<span class="pt-cfg-root-timeframes">Timeframes: <strong>'
                          + ptEscapeHtml((root.timeframes || []).join(', ') || 'Select timeframes')
                          + '</strong></span>'
                          + '<span class="pt-cfg-root-state">' + (root.expanded ? 'Click to collapse' : 'Click anywhere here to expand') + '</span>';
            head.appendChild(tag);

            // The complete root header is an accordion trigger. Controls inside it
            // (such as Edit root name and its input) keep their own click behavior.
            head.addEventListener('click', function (event) {
                if (event.target.closest('button, input, select, textarea, a, label')) return;
                toggleRootExpanded();
            });

            // Root name is editable inline; saved through the normal configuration save.
            var rootNameActions = document.createElement('div');
            rootNameActions.className = 'pt-cfg-root-name-actions';
            var rootNameBtn = document.createElement('button');
            rootNameBtn.type = 'button';
            rootNameBtn.className = 'pt-cfg-btn';
            rootNameBtn.innerHTML = root.name ? '<i class="fa-solid fa-pen"></i> Edit root name' : '<i class="fa-solid fa-plus"></i> Add root name';
            rootNameBtn.addEventListener('click', function (event) {
                event.preventDefault(); event.stopPropagation();
                if (rootNameActions.querySelector('input')) return;
                var input = document.createElement('input');
                input.type = 'text'; input.maxLength = 160; input.value = root.name || '';
                input.className = 'pt-cfg-input'; input.placeholder = 'Enter root name';
                var done = document.createElement('button');
                done.type = 'button'; done.className = 'pt-cfg-btn'; done.textContent = 'Done';
                done.addEventListener('click', function (ev) {
                    ev.preventDefault(); ev.stopPropagation();
                    root.name = input.value.trim();
                    onChange();
                });
                rootNameActions.innerHTML = '';
                rootNameActions.appendChild(input); rootNameActions.appendChild(done);
                input.focus();
            });
            rootNameActions.appendChild(rootNameBtn);
            head.appendChild(rootNameActions);

            // The destructive control is not rendered while the root is folded.
            // It only appears after the user expands the root, and still requires confirmation.
            if (root.expanded) {
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'pt-cfg-btn danger';
                removeBtn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove Root';
                removeBtn.setAttribute('aria-label', 'Remove Root ' + root.id);
                removeBtn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    openRootDeleteConfirmation(root);
                });
                head.appendChild(removeBtn);
            }
            wrap.appendChild(head);

            var content = document.createElement('div');
            content.className = 'pt-cfg-root-content';
            content.style.display = root.expanded ? 'flex' : 'none';

            // Root-level settings remain visible regardless of whether trades are enabled.
            var rootGrid = document.createElement('div');
            rootGrid.className = 'pt-cfg-root-grid';
            var runSel = mkSelect('cfg_run_' + root.id,
                [{value:'1', label:'Yes'}, {value:'0', label:'No'}],
                String(root.applyToChart ? 1 : 0), false, function (e) {
                    root.applyToChart = (e.target.value === '1');
                    onValueChange();
                });
            rootGrid.appendChild(mkField('Apply this root to the chart', runSel));
            content.appendChild(rootGrid);

            // Trees
            var treesWrap = document.createElement('div');
            treesWrap.style.display = 'flex';
            treesWrap.style.flexDirection = 'column';
            treesWrap.style.gap = '10px';
            root.trees.forEach(function (tree, idx) {
                treesWrap.appendChild(buildTree(root, tree, idx, onChange, onValueChange));
            });
            content.appendChild(treesWrap);

            // New Tree button
            var treeFoot = document.createElement('div');
            treeFoot.className = 'pt-cfg-tree-foot';
            var addTreeBtn = document.createElement('button');
            addTreeBtn.type = 'button';
            addTreeBtn.className = 'pt-cfg-btn';
            addTreeBtn.setAttribute('data-pt-add-tree-root', String(root.id));
            addTreeBtn.innerHTML = '<i class="fa-solid fa-plus"></i> New Tree';
            addTreeBtn.addEventListener('click', function () {
                if (root.trees.length < 2 && !(root.trees.length === 1 && treeIsConfigured(root, root.trees[0], 0))) return;
                root.trees.push(createTree(root));
                root.expanded = true;
                onChange();
            });
            treeFoot.appendChild(addTreeBtn);
            content.appendChild(treeFoot);

            // Confirmation-root configuration is visually separate and uses the same tree editor.
            if (!root.confirmationRoot) root.confirmationRoot = {
                id: root.id * 1000 + 1, name: 'Confirmation for root ' + root.id,
                timeframes: [], anchorCandleId: '', anchorCandleName: '',
                anchorTime: 'open_time', trees: [], drawings: []
            };
            var confirmationRoot = root.confirmationRoot;
            confirmationRoot.isConfirmationRoot = true;
            confirmationRoot.parentRoot = root;
            confirmationRoot.parentRootId = root.id;
            var confirmationWrap = document.createElement('section');
            confirmationWrap.className = 'pt-cfg-confirmation-section';
            confirmationWrap.style.cssText = 'display:flex;flex-direction:column;gap:12px;margin-top:16px;padding:14px;border:1px solid #e0b400;border-radius:12px;background:rgba(255,193,7,.10);min-width:0;';
            var confirmationTitle = document.createElement('div');
            confirmationTitle.textContent = 'CONFIRMATION ROOT';
            confirmationTitle.style.cssText = 'font-size:.82rem;font-weight:900;letter-spacing:.08em;color:#b8860b;';
            confirmationWrap.appendChild(confirmationTitle);
            var addConfirmationBtn = document.createElement('button');
            addConfirmationBtn.type = 'button';
            addConfirmationBtn.className = 'pt-cfg-btn';
            addConfirmationBtn.innerHTML = '<i class="fa-solid fa-plus"></i> Add confirmation root';
            addConfirmationBtn.addEventListener('click', function () {
                confirmationRoot.enabled = true;
                if (!confirmationRoot.trees.length) confirmationRoot.trees.push(createTree(confirmationRoot));
                root.expanded = true;
                onChange();
            });
            confirmationWrap.appendChild(addConfirmationBtn);
            if (confirmationRoot.enabled || confirmationRoot.trees.length) {
                var tfLabel = document.createElement('div');
                tfLabel.textContent = 'Select confirmation timeframes (parent-root timeframes are excluded)';
                tfLabel.style.fontWeight = '700';
                confirmationWrap.appendChild(tfLabel);
                var tfList = document.createElement('div');
                tfList.className = 'pt-root-timeframe-list';
                (PT_ALL_TIMEFRAMES || []).filter(function (tf) {
                    return (root.timeframes || []).indexOf(tf) === -1;
                }).forEach(function (tf) {
                    var item = document.createElement('label');
                    item.className = 'pt-root-timeframe-option';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox'; cb.value = tf;
                    cb.checked = (confirmationRoot.timeframes || []).indexOf(tf) !== -1;
                    cb.addEventListener('change', function () {
                        confirmationRoot.timeframes = confirmationRoot.timeframes || [];
                        if (cb.checked && confirmationRoot.timeframes.indexOf(tf) < 0) confirmationRoot.timeframes.push(tf);
                        if (!cb.checked) confirmationRoot.timeframes = confirmationRoot.timeframes.filter(function (v) { return v !== tf; });
                        confirmationRoot.timeframes = (PT_ALL_TIMEFRAMES || []).filter(function (v) { return confirmationRoot.timeframes.indexOf(v) >= 0; });
                        onChange();
                    });
                    item.appendChild(cb);
                    var span = document.createElement('span'); span.textContent = tf; item.appendChild(span);
                    tfList.appendChild(item);
                });
                confirmationWrap.appendChild(tfList);

                confirmationRoot.parentRoot = root;
                confirmationRoot.parentRootId = root.id;
                var confirmationTrees = document.createElement('div');
                confirmationTrees.style.cssText = 'display:flex;flex-direction:column;gap:10px;';
                confirmationRoot.trees.forEach(function (tree, idx) {
                    confirmationTrees.appendChild(buildTree(confirmationRoot, tree, idx, onChange, onValueChange));
                });
                confirmationWrap.appendChild(confirmationTrees);
                // Drawings for either source live in the parent root's blue SET DRAWINGS
                // section. The Draw for selector chooses root candles or confirmation candles.
                var addConfirmationTree = document.createElement('button');
                addConfirmationTree.type = 'button'; addConfirmationTree.className = 'pt-cfg-btn';
                addConfirmationTree.innerHTML = '<i class="fa-solid fa-plus"></i> New confirmation tree';
                addConfirmationTree.addEventListener('click', function () {
                    if (!confirmationRoot.trees.length || treeIsConfigured(confirmationRoot, confirmationRoot.trees[confirmationRoot.trees.length - 1], confirmationRoot.trees.length - 1)) {
                        confirmationRoot.trees.push(createTree(confirmationRoot));
                        onChange();
                    } else {
                        setFootStatus('Complete the current confirmation tree before adding another.', 'error');
                    }
                });
                confirmationWrap.appendChild(addConfirmationTree);
            }
            content.appendChild(confirmationWrap);

            // Trade settings are grouped inside one green outer border.
            var tradeSection = document.createElement('section');
            tradeSection.className = 'pt-cfg-trade-section';
            tradeSection.style.cssText = 'display:flex;flex-direction:column;gap:12px;margin-top:16px;padding:14px;border:1px solid rgba(18,163,107,.48);border-radius:12px;background:rgba(18,163,107,.07);min-width:0;';
            var tradeToggle = document.createElement('div');
            tradeToggle.className = 'pt-cfg-root-grid pt-cfg-trade-toggle-section';
            tradeToggle.style.cssText = 'margin:0;padding:0;border:0;background:transparent;';
            var useSel = mkSelect('cfg_use_' + root.id,
                [{value:'1', label:'Yes'}, {value:'0', label:'No'}],
                String(root.useForTrades ? 1 : 0), false, function (e) {
                    root.useForTrades = (e.target.value === '1');
                    onChange();
                });
            var tradeToggleLabel = mkField('SET TRADES — Use this root for trades', useSel);
            tradeToggleLabel.style.fontWeight = '800';
            tradeToggle.appendChild(tradeToggleLabel);
            tradeSection.appendChild(tradeToggle);
            var tradeSourceSel = mkSelect('cfg_trade_for_' + root.id,
                [{value:'root',label:'root'},{value:'root_confirmation',label:'root confirmation'}],
                root.tradeFor || 'root', false, function () {
                    root.tradeFor = tradeSourceSel.value;
                    onChange();
                });
            tradeSection.appendChild(mkField('Set Trades for', tradeSourceSel));

            // Trade-entry controls are deliberately placed below all trees and above Drawings.
            // They are not rendered at all unless "Use this root for trades" is Yes.
            if (root.useForTrades) {
                var tradeGrid = document.createElement('div');
                tradeGrid.className = 'pt-cfg-root-grid pt-cfg-trade-grid';
                function candleTargetsOptions(includeParentRoot) {
                    var opts = [{value:'', label:'— select candle —'}];
                    var confirmationMode = root.tradeFor === 'root_confirmation' && root.confirmationRoot;
                    var sourceRoot = confirmationMode ? root.confirmationRoot : root;
                    sourceRoot.trees.forEach(function (tree) {
                        ['main','reference','offset'].forEach(function (kind) {
                            var candle = tree[kind];
                            if (!candle || !candle.name) return;
                            opts.push({value:tree.id + '|' + kind + '|' + candle.id,
                                label:candle.name + ' (' + (confirmationMode ? 'confirmation · ' : '') + kind + ' · tree ' + tree.id + ')'});
                        });
                    });
                    // Entry must be anchored to a lower-timeframe confirmation candle only.
                    // SL/TP can also reference the parent/root candle's projected price level.
                    if (confirmationMode && includeParentRoot) {
                        root.trees.forEach(function (tree) {
                            ['main','reference','offset'].forEach(function (kind) {
                                var candle = tree[kind];
                                if (!candle || !candle.name) return;
                                opts.push({value:'parent|' + tree.id + '|' + kind + '|' + candle.id,
                                    label:candle.name + ' (root · ' + kind + ' · tree ' + tree.id + ')'});
                            });
                        });
                    }
                    return opts;
                }
                var orderTypeSel = mkSelect('cfg_ordertype_' + root.id, ['buy','sell','buy-limit','sell-limit','buy-stop','sell-stop'].map(function(v){return {value:v,label:v};}), root.orderType || 'buy', false, function(e){ root.orderType=e.target.value; onValueChange(); });
                tradeGrid.appendChild(mkField('Order type', orderTypeSel));
                var entryFromSel = mkSelect('cfg_entryfrom_' + root.id, candleTargetsOptions(false), root.entryFrom || '', false, function (e) { root.entryFrom = e.target.value; onValueChange(); });
                tradeGrid.appendChild(mkField('Entry at', entryFromSel));
                var entryPlSel = mkSelect('cfg_entrypl_' + root.id, PRICE_LEVELS, root.entryPriceLevel || 'high', false, function (e) { root.entryPriceLevel = e.target.value; onValueChange(); });
                tradeGrid.appendChild(mkField('Entry Price level', entryPlSel));
                var slFromSel = mkSelect('cfg_slfrom_' + root.id, candleTargetsOptions(true), root.stoplossFrom || '', false, function (e) { root.stoplossFrom = e.target.value; onValueChange(); });
                tradeGrid.appendChild(mkField('Stoploss at', slFromSel));
                var slPlSel = mkSelect('cfg_slpl_' + root.id, PRICE_LEVELS, root.stoplossPriceLevel || 'high', false, function (e) { root.stoplossPriceLevel = e.target.value; onValueChange(); });
                tradeGrid.appendChild(mkField('Stoploss Price level', slPlSel));
                var tpTypeSel = mkSelect('cfg_tptype_' + root.id, TP_TYPES, root.takeProfitType || 'risk_reward', false, function (e) { root.takeProfitType = e.target.value; onChange(); });
                tradeGrid.appendChild(mkField('Take Profit type', tpTypeSel));
                if (root.takeProfitType !== 'risk_reward') {
                    var tpFromSel = mkSelect('cfg_tpfrom_' + root.id, candleTargetsOptions(true), root.takeProfitFrom || '', false, function (e) { root.takeProfitFrom = e.target.value; onValueChange(); });
                    tradeGrid.appendChild(mkField('Take profit at', tpFromSel));
                    var tpPlSel = mkSelect('cfg_tppl_' + root.id, PRICE_LEVELS, root.takeProfitPriceLevel || 'high', false, function (e) { root.takeProfitPriceLevel = e.target.value; onValueChange(); });
                    tradeGrid.appendChild(mkField('Take Profit Price level', tpPlSel));
                }
                tradeSection.appendChild(tradeGrid);
            }
            content.appendChild(tradeSection);

            // Drawings title, entries and add button are all inside one blue outer border.
            var drawsWrap = document.createElement('section');
            drawsWrap.className = 'pt-cfg-draws';
            drawsWrap.style.cssText = 'display:flex;flex-direction:column;gap:12px;margin-top:18px;padding:14px;border:1px solid rgba(67,136,220,.5);border-radius:12px;background:rgba(67,136,220,.07);min-width:0;';
            drawsWrap.id = 'cfg_draws_' + root.id;
            var drawingsHeading = document.createElement('div');
            drawingsHeading.textContent = 'SET DRAWINGS';
            drawingsHeading.style.cssText = 'margin:0;font-size:.82rem;font-weight:900;letter-spacing:.08em;color:#4388dc;';
            drawsWrap.appendChild(drawingsHeading);
            root.drawings.forEach(function (draw, didx) {
                drawsWrap.appendChild(buildDrawing(root, draw, didx, onChange, onValueChange));
            });

            var drawFoot = document.createElement('div');
            drawFoot.className = 'pt-cfg-tree-foot';
            var addDrawBtn = document.createElement('button');
            addDrawBtn.type = 'button';
            addDrawBtn.className = 'pt-cfg-btn';
            addDrawBtn.innerHTML = '<i class="fa-solid fa-pen-ruler"></i> Add Drawing';
            addDrawBtn.addEventListener('click', function () {
                var drawing = createDrawing();
                drawing.drawFor = 'root';
                root.drawings.push(drawing);
                root.expanded = true;
                onChange();
            });
            drawFoot.appendChild(addDrawBtn);
            drawsWrap.appendChild(drawFoot);
            content.appendChild(drawsWrap);
            wrap.appendChild(content);
            return wrap;
        }

        function buildDrawing(root, draw, drawIndex, onChange, onValueChange) {
            var wrap = document.createElement('div');
            wrap.className = 'pt-cfg-draw';
            var head = document.createElement('div');
            head.className = 'pt-cfg-draw-head';
            var tag = document.createElement('div');
            tag.className = 'pt-cfg-draw-tag';
            tag.textContent = 'Drawing ' + (drawIndex + 1);
            head.appendChild(tag);
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'pt-cfg-draw-remove';
            rm.innerHTML = '<i class="fa-solid fa-trash"></i>';
            rm.addEventListener('click', function () {
                var idx = root.drawings.indexOf(draw);
                if (idx >= 0) root.drawings.splice(idx, 1);
                onChange();
            });
            head.appendChild(rm);
            wrap.appendChild(head);

            var grid = document.createElement('div');
            grid.className = 'pt-cfg-row-grid';

            var drawForSel = mkSelect('cfg_draw_for_' + draw.id,
                [{value:'root',label:'root'},{value:'root_confirmation',label:'root confirmation'}],
                draw.drawFor || (root && root.parentRootId ? 'root_confirmation' : 'root'), false, function () {
                    draw.drawFor = drawForSel.value;
                    onChange();
                });
            grid.appendChild(mkField('Draw for', drawForSel));

            function drawingSourceRoot() {
                if (root.parentRootId && root.parentRoot) {
                    return draw.drawFor === 'root_confirmation' ? root : root.parentRoot;
                }
                return draw.drawFor === 'root_confirmation' && root.confirmationRoot
                    ? root.confirmationRoot : root;
            }

            function parseTarget(value) {
                var p = String(value || '').split('|');
                return p.length >= 3 ? {treeId: p[0], kind: p[1], id: p[2]} : null;
            }

            /*
             * Cross-tree drawing is enabled by design: both endpoint selectors
             * enumerate every configured candle in every tree in this root.
             * The tree is encoded in each option value (treeId|kind|candleId),
             * so identically named candles in different trees remain distinct.
             */
            function targetOptions(includeIntercept) {
                var list = [{value: '', label: '— select candle —'}];
                var sourceRoot = drawingSourceRoot();
                sourceRoot.trees.forEach(function (tree, index) {
                    ['main', 'reference', 'offset'].forEach(function (kind) {
                        var candle = tree[kind];
                        // A new offset is represented by the reference model.
                        if (!candle && kind === 'offset' &&
                            tree.reference &&
                            tree.reference.referenceMode === 'new_offset') {
                            candle = tree.reference;
                        }
                        if (!candle || !candle.name) return;
                        if (kind === 'offset' && tree.reference &&
                            tree.reference.referenceMode !== 'new_offset' &&
                            !tree.offset) return;

                        var canonicalId;
                        if (kind === 'main') {
                            canonicalId = candle.selectedExistingId || candle.offsetTo || candle.id;
                        } else if (kind === 'reference') {
                            canonicalId = (candle.referenceMode !== 'new_offset' &&
                                (candle.selectedExistingId || candle.existingCandleId))
                                ? (candle.selectedExistingId || candle.existingCandleId) : candle.id;
                        } else {
                            canonicalId = (candle.referenceMode === 'new_offset')
                                ? candle.id
                                : (candle.selectedExistingId || candle.existingCandleId || candle.id);
                        }
                        var identity = String(tree.id) + '|' + kind + '|' + String(canonicalId);
                        var kindLabel = kind === 'reference' ? 'reference' : kind;
                        list.push({
                            value: identity,
                            label: candle.name + ' (Tree ' + tree.id + ' · ' + kindLabel + ')'
                        });
                    });
                });
                if (includeIntercept) {
                    list.push({value: 'any_intercept_candle', label: 'any_intercept_candle'});
                }
                return list;
            }

            var fromOptions = targetOptions(false);
            function normalizeSavedTarget(value, options) {
                value = String(value || '');
                if (options.some(function (option) { return option.value === value; })) return value;
                var parts = value.split('|');
                if (parts.length < 3) return value;
                var tree = drawingSourceRoot().trees.find(function (item) { return String(item.id) === String(parts[0]); });
                if (!tree) return value;
                var kind = parts[1];
                var candle = kind === 'main' ? tree.main :
                    (kind === 'reference' ? tree.reference :
                    ((tree.offset || (tree.reference && tree.reference.referenceMode === 'new_offset')) ? (tree.offset || tree.reference) : null));
                if (!candle || String(candle.id) !== String(parts[2])) return value;
                var normalized = options.find(function (option) {
                    var optionParts = String(option.value || '').split('|');
                    return optionParts.length >= 3 &&
                        String(optionParts[0]) === String(parts[0]) &&
                        String(optionParts[1]) === String(parts[1]);
                });
                return normalized ? normalized.value : value;
            }
            draw.drawFrom = normalizeSavedTarget(draw.drawFrom, fromOptions);
            draw.drawTo = normalizeSavedTarget(draw.drawTo, targetOptions(true));
            var dFromSel = mkSelect(
                'cfg_drawfrom_' + draw.id,
                fromOptions,
                draw.drawFrom || '',
                false,
                function () {
                    draw.drawFrom = dFromSel.value;
                    var target = parseTarget(draw.drawFrom);
                    if (target) draw.drawTreeId = String(target.treeId);
                    onValueChange();
                }
            );
            grid.appendChild(mkField('Draw from candle', dFromSel));

            var dFromPlSel = mkSelect(
                'cfg_drawfrompl_' + draw.id,
                PRICE_LEVELS,
                draw.drawFromPriceLevel || 'high',
                false,
                function () {
                    draw.drawFromPriceLevel = dFromPlSel.value;
                    onValueChange();
                }
            );
            grid.appendChild(mkField('Draw from price level', dFromPlSel));

            var dToSel = mkSelect(
                'cfg_drawto_' + draw.id,
                targetOptions(true),
                draw.drawTo || '',
                false,
                function () {
                    draw.drawTo = dToSel.value;
                    onValueChange();
                }
            );
            grid.appendChild(mkField('Draw to candle', dToSel));

            if (draw.drawTo !== 'any_intercept_candle') {
                var dToPlSel = mkSelect(
                    'cfg_drawtopl_' + draw.id,
                    [{value: 'axis', label: 'axis'}].concat(
                        PRICE_LEVELS.map(function (v) { return {value: v, label: v}; })
                    ),
                    draw.drawToPriceLevel || 'high',
                    false,
                    function () {
                        draw.drawToPriceLevel = dToPlSel.value;
                        onValueChange();
                    }
                );
                grid.appendChild(mkField('Draw to price level', dToPlSel));
            }

            var drawingColorSel = mkSelect(
                'cfg_drawing_color_' + draw.id,
                COLOR_OPTIONS,
                draw.drawingColor || 'blue',
                false,
                function () {
                    draw.drawingColor = drawingColorSel.value;
                    onValueChange();
                }
            );
            grid.appendChild(mkField('Drawing color', drawingColorSel));
            wrap.appendChild(grid);
            return wrap;
        }

        // ---------- model factories ----------
        function createTree(root) {
            var treeId = PT_CFG.nextTreeId++;
            var isFirst = root.trees.length === 0;

            // Candle IDs must be globally unique across main, reference, and offset rows.
            // Dropdowns store the ID, so separate counters can accidentally select the wrong candle.
            var mainId = PT_CFG.nextCandleId++;
            var refId  = PT_CFG.nextCandleId++;

            return {
                id: treeId,
                main: {
                    id: mainId,
                    name: '',
                    candleText: '',
                    textPosition: 'high',
                    textColor: 'purple',
                    mainCandleType: DEFAULT_CANDLE_TYPE,
                    offsetCandle: '',
                    position: isFirst ? 'all' : 'left',
                    offsetTo: null,
                    offsetToName: '',
                    offsetToKind: '',
                    offsetPosition: null,
                    offsetPosPoint: 'all',
                    priceLevel: 'high',
                    operator: '<'
                },
                reference: {
                    id: refId,
                    name: '',
                    candleText: '',
                    textPosition: 'high',
                    textColor: 'purple',
                    refCandleType: DEFAULT_CANDLE_TYPE,
                    position: 'left',
                    offsetPosition: 1,
                    offsetPosPoint: 'all',
                    priceLevel: 'high'
                },
                offset: null
            };
        }

        function createDrawing() {
            return {
                id: PT_CFG.nextOffsetId++,
                drawTreeId: '',
                drawFrom: '',
                drawFromPriceLevel: 'high',
                drawTo: '',
                drawToPriceLevel: 'high',
                drawingColor: 'blue'
            };
        }

        function createRoot() {
            var id = PT_CFG.nextRootId++;
            var root = {
                id: id,
                name: '',
                timeframe: '',
                timeframes: [],
                useForTrades: false,
                expanded: true,
                entryFrom: '',
                orderType: 'buy',
                entryTriggerAfter: '',
                entryPriceLevel: 'high',
                stoplossFrom: '',
                stoplossPriceLevel: 'high',
                takeProfitFrom: '',
                takeProfitType: 'risk_reward',
                takeProfitPriceLevel: 'high',
                applyToChart: true,
                tradeFor: 'root',
                confirmationRoot: {
                    id: id * 1000 + 1, name: 'Confirmation for root ' + id,
                    timeframes: [], anchorCandleId: '', anchorCandleName: '',
                    anchorTime: 'open_time', trees: [], drawings: []
                },
                trees: [],
                drawings: []
            };
            root.trees.push(createTree(root));
            return root;
        }

        // ---------- serialization ----------
        function serializeForSave() {
            var rootsOut = [];
            PT_CFG.roots.forEach(function (root) {
                var rootOut = {
                    id: root.id,
                    root_name: root.name || '',
                    timeframes: Array.isArray(root.timeframes) ? root.timeframes : [],
                    timeframe: Array.isArray(root.timeframes) ? JSON.stringify(root.timeframes) : '',
                    use_for_trades: root.useForTrades ? 1 : 0,
                    apply_to_chart: root.applyToChart ? 1 : 0,
                    trade_for: root.tradeFor || 'root',
                    confirmation_root_id: root.confirmationRoot ? root.confirmationRoot.id : null,
                    confirmation_tree_id: null,
                    confirmation_timeframes: root.confirmationRoot ? root.confirmationRoot.timeframes : [],
                    confirmation_anchor_candle_id: root.confirmationRoot ? (root.confirmationRoot.anchorCandleId || null) : null,
                    confirmation_anchor_candle_name: root.confirmationRoot ? (root.confirmationRoot.anchorCandleName || '') : '',
                    confirmation_anchor_time: root.confirmationRoot ? (root.confirmationRoot.anchorTime || 'open_time') : 'open_time',
                    entry_at: root.entryFrom || '',
                    order_type: root.orderType || 'buy',
                    entry_order_type: root.orderType || 'buy',
                    entry_trigger_after: root.entryTriggerAfter || '',
                    entry_price_level: root.entryPriceLevel || '',
                    stoploss_at: root.stoplossFrom || '',
                    stoploss_price_level: root.stoplossPriceLevel || '',
                    take_profit_at: root.takeProfitFrom || '',
                    take_profit_type: root.takeProfitType || 'risk_reward',
                    take_profit_price_level:
                        root.takeProfitType === 'risk_reward'
                            ? null
                            : (root.takeProfitPriceLevel || ''),
                    trees: [],
                    drawings: []
                };

                root.trees.forEach(function (tree, treeIndex) {
                    // Never serialize an unfinished tree or its placeholder rows.
                    if (!treeIsConfigured(root, tree, treeIndex)) return;
                    var mainRow = tree.main;
                    var refRow  = tree.reference;

                    rootOut.trees.push({
                        tree_id: tree.id,
                        main: {
                            id: mainRow.id,
                            main_id: (mainRow.selectedExistingId || mainRow.id),
                            main_candle_name: mainRow.name || '',
                            candle_text: mainRow.candleText || '',
                            text_position: mainRow.textPosition || 'high',
                            text_color: mainRow.textColor || 'purple',
                            main_candle_type: normalizeCandleType(mainRow.mainCandleType),
                            offset_candle: mainRow.offsetTo != null
                                ? normalizeCandleType(mainRow.offsetCandle || (function () {
                                    var target = findCandleById(mainRow.offsetTo);
                                    return target ? (target.row.mainCandleType || target.row.refCandleType || target.row.offsetCandle) : DEFAULT_CANDLE_TYPE;
                                })())
                                : null,
                            position: mainRow.position || 'all',
                            offset_to: mainRow.offsetTo != null ? mainRow.offsetTo : null,
                            offset_to_name: mainRow.offsetToName || '',
                            offset_position:
                                mainRow.offsetPosition != null ? mainRow.offsetPosition : null,
                            offset_pos_point: mainRow.offsetPosPoint || 'all',
                            price_level: mainRow.priceLevel || 'high',
                            operator: mainRow.operator || '<'
                        },
                        reference: {
                            // Persist the selected mode so PHP can distinguish an existing
                            // reference candle from a newly-created offset candle. Without
                            // this field, PHP defaults to 'existing' and discards offset text.
                            referenceMode: refRow.referenceMode || 'existing',
                            id: (refRow.referenceMode === 'existing' && refRow.selectedExistingId) ? refRow.selectedExistingId : refRow.id,
                            reference_id: (refRow.referenceMode === 'existing' && refRow.selectedExistingId) ? refRow.selectedExistingId : refRow.id,
                            reference_candle_name: refRow.name || '',
                            candle_text: (root.trees.indexOf(tree) === 0 || refRow.referenceMode === 'new_offset')
                                ? ((refRow.candleText || (tree.offset && tree.offset.candleText) || '') + '')
                                : '',
                            text_position: refRow.textPosition || (tree.offset && tree.offset.textPosition) || 'high',
                            text_color: refRow.textColor || (tree.offset && tree.offset.textColor) || 'purple',
                            ref_candle_type: normalizeCandleType(refRow.refCandleType),
                            position: refRow.position || 'left',
                            offset_to: (refRow.referenceMode === 'new_offset') ? (mainRow.selectedExistingId || mainRow.id) : null,
                            offset_to_name: (refRow.referenceMode === 'new_offset') ? (mainRow.name || '') : '',
                            offset_position:
                                refRow.offsetPosition != null ? refRow.offsetPosition : 1,
                            offset_pos_point: refRow.offsetPosPoint || 'all',
                            price_level: refRow.priceLevel || 'high'
                        }
                    });
                });

                root.drawings.forEach(function (draw) {
                    rootOut.drawings.push({
                        id: draw.id,
                        tree_id: parseInt(draw.drawTreeId || (String(draw.drawFrom || '').split('|')[0]) || (root.trees[0] && root.trees[0].id) || 1, 10),
                        draw_from: draw.drawFrom || '',
                        draw_from_price_level: draw.drawFromPriceLevel || '',
                        draw_to: draw.drawTo || '',
                        draw_to_price_level:
                            (draw.drawTo === 'any_intercept_candle')
                                ? null
                                : (draw.drawToPriceLevel || ''),
                        drawing_color: draw.drawingColor || 'blue',
                        draw_for: draw.drawFor || 'root'
                    });
                });

                if (root.confirmationRoot && root.confirmationRoot.enabled) {
                    var cr = root.confirmationRoot;
                    var crOut = {
                        id: cr.id || (root.id * 1000 + 1),
                        root_name: cr.name || ('Confirmation for root ' + root.id),
                        timeframes: Array.isArray(cr.timeframes) ? cr.timeframes : [],
                        timeframe: Array.isArray(cr.timeframes) ? JSON.stringify(cr.timeframes) : '',
                        trees: [], drawings: [],
                        apply_to_chart: root.applyToChart ? 1 : 0,
                        use_for_trades: 0,
                        _parent_root_id: root.id,
                        anchor_candle_id: cr.anchorCandleId || null,
                        anchor_candle_name: cr.anchorCandleName || '',
                        anchor_time: cr.anchorTime || 'open_time'
                    };
                    cr.trees.forEach(function (tree, treeIndex) {
                        if (!treeIsConfigured(cr, tree, treeIndex)) return;
                        var mainRow = tree.main, refRow = tree.reference;
                        crOut.trees.push({
                            tree_id: tree.id,
                            main: {
                                id: mainRow.id, main_id: (mainRow.selectedExistingId || mainRow.id),
                                main_candle_name: mainRow.name || '', candle_text: mainRow.candleText || '',
                                text_position: mainRow.textPosition || 'high', text_color: mainRow.textColor || 'purple',
                                main_candle_type: normalizeCandleType(mainRow.mainCandleType),
                                offset_candle: mainRow.offsetTo != null ? normalizeCandleType(mainRow.offsetCandle || DEFAULT_CANDLE_TYPE) : null,
                                position: mainRow.position || 'all', offset_to: mainRow.offsetTo != null ? mainRow.offsetTo : null,
                                offset_to_name: mainRow.offsetToName || '', offset_position: mainRow.offsetPosition != null ? mainRow.offsetPosition : null,
                                offset_pos_point: mainRow.offsetPosPoint || 'all', price_level: mainRow.priceLevel || 'high', operator: mainRow.operator || '<'
                            },
                            reference: {
                                referenceMode: refRow.referenceMode || 'existing',
                                id: (refRow.referenceMode === 'existing' && refRow.selectedExistingId) ? refRow.selectedExistingId : refRow.id,
                                reference_id: (refRow.referenceMode === 'existing' && refRow.selectedExistingId) ? refRow.selectedExistingId : refRow.id,
                                reference_candle_name: refRow.name || '',
                                candle_text: (treeIndex === 0 || refRow.referenceMode === 'new_offset') ? (refRow.candleText || '') : '',
                                text_position: refRow.textPosition || 'high', text_color: refRow.textColor || 'purple',
                                ref_candle_type: normalizeCandleType(refRow.refCandleType), position: refRow.position || 'left',
                                offset_to: refRow.referenceMode === 'new_offset' ? (mainRow.selectedExistingId || mainRow.id) : null,
                                offset_to_name: refRow.referenceMode === 'new_offset' ? (mainRow.name || '') : '',
                                offset_position: refRow.offsetPosition != null ? refRow.offsetPosition : 1,
                                offset_pos_point: refRow.offsetPosPoint || 'all', price_level: refRow.priceLevel || 'high'
                            }
                        });
                    });
                    rootOut.confirmation_root = crOut;
                }
                rootsOut.push(rootOut);
            });

            var treeCount = 0, rowCount = 0;
            PT_CFG.roots.forEach(function (r) {
                treeCount += r.trees.length;
                rowCount  += r.trees.length * 2;
            });

            return {
                programme_id: PT_PROGRAMME_ID,
                user_id: PT_USER_ID,
                roots: rootsOut,
                meta: {
                    root_count: PT_CFG.roots.length,
                    tree_count: treeCount,
                    row_count:  rowCount
                }
            };
        }

        // ---------- footer status ----------
        function setFootStatus(text, kind) {
            var el2 = el('ptCfgFootStatus');
            if (!el2) return;
            el2.textContent = text || '';
            el2.classList.remove('is-ok', 'is-error');
            if (kind === 'ok')    el2.classList.add('is-ok');
            if (kind === 'error') el2.classList.add('is-error');
        }

        function updateFootStatus() {
            updateTreeAddButtons();
            var btn = el('ptCfgSaveBtn');
            if (!PT_CFG.roots.length) {
                if (btn) btn.disabled = true;
                setFootStatus('Nothing to save yet.');
                return;
            }
            if (btn) btn.disabled = false;

            var trees = 0, rows = 0;
            PT_CFG.roots.forEach(function (r) {
                trees += r.trees.length;
                rows  += r.trees.length * 2;
            });
            setFootStatus(
                PT_CFG.roots.length + ' root(s) · ' +
                trees + ' tree(s) · ' +
                rows + ' row(s) ready to save.'
            );
        }

        // ---------- save ----------
        window.ptCfgSave = function () {
            if (!PT_CFG.roots.length) {
                setFootStatus('Add at least one root before saving.', 'error');
                return;
            }

            var btn = el('ptCfgSaveBtn');
            if (btn) { btn.disabled = true; }

            var hasSelectedTimeframes = function (values) {
                return Array.isArray(values) && values.some(function (tf) {
                    return String(tf == null ? '' : tf).trim() !== '';
                });
            };
            // Only the main root timeframe is required to save. Confirmation timeframe
            // selection is optional; when empty, confirmation is simply not rendered/applied
            // on any chart timeframe.
            var missingRoot = PT_CFG.roots.find(function (r) {
                return !hasSelectedTimeframes(r.timeframes);
            });
            if (missingRoot) {
                if (btn) btn.disabled = false;
                setFootStatus((missingRoot.name || missingRoot.root_name || ('Root ' + missingRoot.id)) + ' (Root ' + missingRoot.id + '): select at least one timeframe before saving.', 'error');
                var rootCards = document.querySelectorAll('.pt-cfg-root');
                rootCards.forEach(function (card) {
                    if (String(card.getAttribute('data-root-id')) === String(missingRoot.id)) {
                        card.classList.add('pt-root-validation-error');
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else card.classList.remove('pt-root-validation-error');
                });
                return;
            }

            // Drop incomplete tree wrappers before saving. Configured trees alone remain in the payload.
            PT_CFG.roots.forEach(function (root) {
                root.trees = root.trees.filter(function (tree, index) {
                    return treeIsConfigured(root, tree, index);
                });
            });
            render();
            var payload = serializeForSave();

            setFootStatus('Saving configuration…');

            var body = 'save_configuration=1'
                     + '&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
                     + '&payload='      + encodeURIComponent(JSON.stringify(payload));

            fetch('programme_training.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); }
                    catch (parseError) {
                        throw new Error('Server returned a non-JSON response (HTTP ' + r.status + '). Check the PHP error log.');
                    }
                    if (!r.ok && (!data || !data.message)) {
                        throw new Error('Save request failed with HTTP ' + r.status + '.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (btn) btn.disabled = false;
                if (data && data.success) {
                    ptRefreshHigherTimeframeProjection();
                    setFootStatus(
                        'Saved: ' + data.meta.root_count + ' root(s), ' +
                        data.meta.tree_count + ' tree(s), ' +
                        data.meta.row_count + ' row(s).',
                        'ok'
                    );
                    setTimeout(function () {
                        window.ptCloseConfigModal();
                    }, 700);
                } else {
                    setFootStatus(
                        (data && data.message) || 'Save failed. Please try again.',
                        'error'
                    );
                }
            })
            .catch(function (error) {
                if (btn) btn.disabled = false;
                setFootStatus((error && error.message) || 'Network error while saving configuration.', 'error');
            });
        };

        // ---------- render ----------
        // Structural render: wipes and rebuilds the whole config DOM.
        // Only call this when elements genuinely need to be added/removed.
        function render() {
            var host = el('ptCfgRoots');
            var empty = el('ptCfgEmpty');
            var hint  = el('ptCfgHint');
            if (!host) return;

            host.innerHTML = '';

            if (!PT_CFG.roots.length) {
                if (empty) empty.style.display = 'block';
                if (hint) hint.textContent = 'No roots configured yet.';
                return;
            }
            if (empty) empty.style.display = 'none';

            var onChange      = function () { render(); };
            var onValueChange = function () { updateFootStatus(); };

            PT_CFG.roots.forEach(function (root) {
                host.appendChild(buildRoot(root, onChange, onValueChange));
            });

            if (hint) {
                var trees = 0;
                PT_CFG.roots.forEach(function (r) { trees += r.trees.length; });
                hint.textContent = PT_CFG.roots.length + ' root(s) · ' + trees + ' tree(s)';
            }
        }

        // ---------- restore saved database rows into the modal's editable UI ----------
        var PT_CFG_LOAD_PROMISE = null;
        var PT_CFG_LOADED = false;

        function ptDbBool(value) {
            return value === true || value === 1 || value === '1';
        }

        function ptParseSavedTimeframes(value) {
            var raw = String(value || '').trim();
            if (!raw) return [];
            try {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) return parsed.map(String).filter(Boolean);
            } catch (e) {}
            return raw.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
        }

        function ptRestoreSavedRows(rows, confirmationRows) {
            rows = (Array.isArray(rows) ? rows : []).concat((Array.isArray(confirmationRows) ? confirmationRows : []).map(function (r) {
                r.__is_confirmation = true; return r;
            }));
            PT_CFG.roots = [];
            PT_CFG.nextRootId = 1;
            PT_CFG.nextTreeId = 1;
            PT_CFG.nextCandleId = 1;
            PT_CFG.nextOffsetId = 1;

            var rootMap = {};
            var treeMap = {};
            (Array.isArray(rows) ? rows : []).forEach(function (row) {
                var isConfirmationRow = !!row.__is_confirmation || row.parent_root_id != null;
                var rid = isConfirmationRow ? (parseInt(row.parent_root_id, 10) || 1) : (parseInt(row.root_id, 10) || 1);
                var tid = parseInt(row.tree_id, 10) || 1;

                if (!rootMap[rid]) {
                    var tfs = ptParseSavedTimeframes(row.timeframe);
                    var root = {
                        id: rid, name: row.root_name || '', timeframe: tfs.join(','), timeframes: tfs,
                        expanded: false,
                        useForTrades: ptDbBool(row.use_for_trade),
                        entryFrom: row.entry_at || row.entry_from || '',
                        orderType: row.order_type || row.entry_order_type || 'buy',
                        entryTriggerAfter: row.entry_trigger_after || '',
                        entryPriceLevel: row.entry_price_level || 'high',
                        stoplossFrom: row.stoploss_at || row.stoploss_from || '',
                        stoplossPriceLevel: row.stoploss_price_level || 'high',
                        takeProfitFrom: row.take_profit_at || row.take_profit_from || '',
                        takeProfitType: (String(row.take_profit_type || '').toLowerCase().replace(/\s+/g, '_') === 'price_levels') ? 'price_levels' : 'risk_reward',
                        takeProfitPriceLevel: row.take_profit_price_level || 'high',
                        applyToChart: ptDbBool(row.apply_to_chart != null ? row.apply_to_chart : row.run_in_python),
                        tradeFor: row.trade_for || 'root',
                        confirmationRoot: {
                            id: parseInt(row.confirmation_root_id, 10) || (rid * 1000 + 1),
                            name: 'Confirmation for root ' + rid,
                            timeframes: ptParseSavedTimeframes(row.confirmation_timeframes),
                            anchorCandleId: row.confirmation_anchor_candle_id || '',
                            anchorCandleName: row.confirmation_anchor_candle_name || '',
                            anchorTime: row.confirmation_anchor_time || 'open_time',
                            enabled: !!(row.confirmation_root_id || row.confirmation_anchor_candle_id || row.confirmation_timeframes),
                            trees: [], drawings: []
                        },
                        trees: [], drawings: []
                    };
                    rootMap[rid] = root;
                    PT_CFG.roots.push(root);
                    PT_CFG.nextRootId = Math.max(PT_CFG.nextRootId, rid + 1);
                }
                var currentRoot = rootMap[rid];
                if (!currentRoot.confirmationRoot) currentRoot.confirmationRoot = {
                    id: parseInt(row.confirmation_root_id, 10) || (rid * 1000 + 1),
                    name: 'Confirmation for root ' + rid,
                    timeframes: ptParseSavedTimeframes(row.confirmation_timeframes),
                    anchorCandleId: row.confirmation_anchor_candle_id || '',
                    anchorCandleName: row.confirmation_anchor_candle_name || '',
                    anchorTime: row.confirmation_anchor_time || 'open_time',
                    enabled: false, trees: [], drawings: []
                };
                var targetRoot = currentRoot;
                if (isConfirmationRow) {
                    var cr = currentRoot.confirmationRoot;
                    cr.id = parseInt(row.confirmation_root_id, 10) || cr.id;
                    cr.enabled = true;
                    cr.timeframes = ptParseSavedTimeframes(row.confirmation_timeframes || row.timeframe);
                    cr.anchorCandleId = row.confirmation_anchor_candle_id || cr.anchorCandleId || '';
                    cr.anchorCandleName = row.confirmation_anchor_candle_name || cr.anchorCandleName || '';
                    cr.anchorTime = row.confirmation_anchor_time || cr.anchorTime || 'open_time';
                    targetRoot = cr;
                }

                // Drawing rows are stored alongside candle rows in the same table.
                if (row.draw_from || row.draw_to) {
                    // Confirmation drawings are rendered in the parent root's drawing section;
                    // only their Draw for value determines which candle list is used.
                    currentRoot.drawings.push({
                        id: PT_CFG.nextOffsetId++,
                        drawTreeId: String(row.tree_id || (String(row.draw_from || '').split('|')[0]) || ''),
                        drawFrom: row.draw_from || '',
                        drawFromPriceLevel: row.draw_from_price_level || 'high',
                        drawTo: row.draw_to || '',
                        drawToPriceLevel: row.draw_to_price_level || 'high',
                        drawingColor: row.drawing_color || 'blue',
                        drawFor: row.draw_for || (isConfirmationRow ? 'root_confirmation' : 'root')
                    });
                    return;
                }

                var key = (isConfirmationRow ? 'confirmation:' + rid + ':' + String(row.confirmation_root_id || '') : 'root:' + rid) + ':' + tid;
                if (!treeMap[key]) {
                    var mainId = parseInt(row.main_id, 10) || PT_CFG.nextCandleId++;
                    var refId = parseInt(row.reference_id, 10) || PT_CFG.nextCandleId++;
                    var tree = {
                        id: tid,
                        main: {
                            id: mainId,
                            name: row.main_candle_name || '',
                            candleText: row.candle_text || '',
                            textPosition: row.text_position || 'high',
                            textColor: row.text_color || 'purple',
                            mainCandleType: row.main_candle_type || DEFAULT_CANDLE_TYPE,
                            offsetCandle: row.offset_candle || '',
                            position: row.position || (targetRoot.trees.length ? 'left' : 'all'),
                            offsetTo: row.offset_to || null,
                            offsetToName: row.offset_candle_name || '',
                            offsetToKind: '',
                            offsetPosition: row.offset_position != null ? parseInt(row.offset_position, 10) : null,
                            offsetPosPoint: row.offset_position_point || 'all',
                            priceLevel: row.price_level || 'high',
                            operator: row.operator || '<',
                            selectedExistingId: null
                        },
                        reference: {
                            id: refId,
                            name: row.reference_candle_name || '',
                            // Do not initialize reference/offset text from a main-row text.
                            // The reference row below owns its own annotation text.
                            candleText: '',
                            textPosition: row.text_position || 'high',
                            textColor: row.text_color || 'purple',
                            refCandleType: row.ref_candle_type || DEFAULT_CANDLE_TYPE,
                            position: row.position === 'right' ? 'right' : 'left',
                            offsetPosition: row.offset_position != null ? parseInt(row.offset_position, 10) : 1,
                            offsetPosPoint: row.offset_position_point || 'all',
                            priceLevel: row.price_level || 'high',
                            referenceMode: 'existing',
                            selectedExistingId: row.reference_id ? parseInt(row.reference_id, 10) : null,
                            existingCandleId: row.reference_id ? parseInt(row.reference_id, 10) : null
                        },
                        offset: null
                    };
                    treeMap[key] = tree;
                    targetRoot.trees.push(tree);
                    PT_CFG.nextTreeId = Math.max(PT_CFG.nextTreeId, tid + 1);
                    PT_CFG.nextCandleId = Math.max(PT_CFG.nextCandleId, mainId + 1, refId + 1);
                }

                var savedTree = treeMap[key];
                // A main row has a NULL reference_id; a reference row has a populated reference_id.
                if (row.reference_id !== null && row.reference_id !== '' && row.reference_candle_name) {
                    savedTree.reference.id = parseInt(row.reference_id, 10) || savedTree.reference.id;
                    savedTree.reference.name = row.reference_candle_name || '';
                    savedTree.reference.refCandleType = row.ref_candle_type || DEFAULT_CANDLE_TYPE;
                    var savedIsNewOffset = !!row.offset_candle && !!row.offset_to;
                    savedTree.reference.referenceMode = savedIsNewOffset ? 'new_offset' : 'existing';
                    savedTree.reference.selectedExistingId = savedIsNewOffset ? null : (parseInt(row.reference_id, 10) || null);
                    savedTree.reference.existingCandleId = savedTree.reference.selectedExistingId;
                    savedTree.reference.position = row.position === 'right' ? 'right' : 'left';
                    savedTree.reference.offsetPosition = row.offset_position != null ? parseInt(row.offset_position, 10) : 1;
                    savedTree.reference.offsetPosPoint = row.offset_position_point || 'all';
                    savedTree.reference.priceLevel = row.price_level || 'high';
                    // Some older saves contain an empty duplicate row after a populated
                    // offset-text row. Never erase a non-empty saved offset annotation
                    // just because a later row has candle_text = NULL/empty.
                    if (row.candle_text !== null && row.candle_text !== undefined &&
                        String(row.candle_text).trim() !== '') {
                        savedTree.reference.candleText = String(row.candle_text);
                    }
                    savedTree.reference.textPosition = row.text_position || savedTree.reference.textPosition || 'high';
                    savedTree.reference.textColor = row.text_color || savedTree.reference.textColor || 'purple';

                    // Keep the persisted anchor relationship even when older rows
                    // failed to persist offset_candle. A drawing endpoint may prove
                    // that this reference row is also the tree's offset candle.
                    if (row.offset_to !== null && row.offset_to !== '') {
                        savedTree.reference.offsetTo = parseInt(row.offset_to, 10) || savedTree.main.id;
                        savedTree.reference.offsetToName = row.offset_candle_name || savedTree.main.name || '';
                    }

                    if (savedIsNewOffset) {
                        savedTree.reference.offsetTo = parseInt(row.offset_to, 10) || savedTree.main.id;
                        savedTree.reference.offsetToName = row.offset_candle_name || savedTree.main.name || '';
                        savedTree.offset = savedTree.reference;
                    }
                } else {
                    savedTree.main.id = parseInt(row.main_id, 10) || savedTree.main.id;
                    savedTree.main.name = row.main_candle_name || '';
                    savedTree.main.candleText = row.candle_text || savedTree.main.candleText || '';
                    savedTree.main.textPosition = row.text_position || savedTree.main.textPosition || 'high';
                    savedTree.main.textColor = row.text_color || savedTree.main.textColor || 'purple';
                    savedTree.main.mainCandleType = row.main_candle_type || DEFAULT_CANDLE_TYPE;
                    savedTree.main.offsetTo = row.offset_to || null;
                    savedTree.main.selectedExistingId = row.offset_to ? parseInt(row.offset_to, 10) : null;
                    savedTree.main.offsetToName = row.offset_candle_name || '';
                    savedTree.main.offsetCandle = row.offset_candle || '';
                    savedTree.main.position = row.position || 'all';
                    savedTree.main.offsetPosition = row.offset_position != null ? parseInt(row.offset_position, 10) : null;
                    savedTree.main.offsetPosPoint = row.offset_position_point || 'all';
                    savedTree.main.priceLevel = row.price_level || 'high';
                    savedTree.main.operator = row.operator || '<';
                }
            });

            // Backward-compatible repair during restoration (no database write):
            // older rows can have offset_candle = NULL even though a saved drawing
            // explicitly points to "treeId|offset|candleId". In that case the
            // offset is the same candle as the tree's reference row. Restore the
            // new-offset UI mode and its alias so its fields and drawing target
            // appear in the correct tree when the modal is reopened.
            PT_CFG.roots.forEach(function (root) {
                var treesById = Object.create(null);
                root.trees.forEach(function (tree) {
                    treesById[String(tree.id)] = tree;
                });

                function restoreOffsetEndpoint(value) {
                    var parts = String(value || '').split('|');
                    if (parts.length < 3 || parts[1] !== 'offset') return;

                    var tree = treesById[String(parts[0])];
                    if (!tree || !tree.reference) return;
                    if (String(tree.reference.id) !== String(parts[2])) return;

                    tree.reference.referenceMode = 'new_offset';
                    tree.reference.selectedExistingId = null;
                    tree.reference.existingCandleId = null;
                    tree.reference.offsetTo = tree.reference.offsetTo ||
                        tree.main.selectedExistingId || tree.main.id;
                    tree.reference.offsetToName = tree.reference.offsetToName ||
                        tree.main.name || '';
                    // The offset and reference are the same configured candle identity.
                    // Keep the saved text/position on that identity when rebuilding the UI.
                    if (typeof tree.reference.candleText !== 'string') tree.reference.candleText = '';
                    tree.reference.textPosition = tree.reference.textPosition || 'high';
                    tree.offset = tree.reference;
                }

                root.drawings.forEach(function (drawing) {
                    restoreOffsetEndpoint(drawing.drawFrom);
                    restoreOffsetEndpoint(drawing.drawTo);
                });

                // Ensure the private ID allocator never reuses a positive ID
                // restored from the database, including IDs on offset aliases.
                root.trees.forEach(function (tree) {
                    ['main', 'reference', 'offset'].forEach(function (kind) {
                        var candle = tree[kind];
                        if (candle && Number(candle.id) > 0) {
                            PT_CFG.nextCandleId = Math.max(PT_CFG.nextCandleId, Number(candle.id) + 1);
                        }
                    });
                });
            });

            PT_CFG.roots.sort(function (a, b) { return a.id - b.id; });
            PT_CFG.roots.forEach(function (root) {
                root.trees.sort(function (a, b) { return a.id - b.id; });
                if (!root.trees.length) root.trees.push(createTree(root));
                if (root.confirmationRoot) {
                    root.confirmationRoot.trees.sort(function (a, b) { return a.id - b.id; });
                    root.confirmationRoot.drawings = root.confirmationRoot.drawings || [];
                }
            });
            PT_CFG_LOADED = true;
        }

        function ptLoadSavedConfiguration(forceReload) {
            if (PT_CFG_LOAD_PROMISE && !forceReload) return PT_CFG_LOAD_PROMISE;
            PT_CFG_LOAD_PROMISE = fetch('programme_training.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'load_configuration=1&programme_id=' + encodeURIComponent(PT_PROGRAMME_ID)
            }).then(function (response) {
                return response.text().then(function (raw) {
                    var data;
                    try { data = JSON.parse(raw); }
                    catch (e) { throw new Error('Could not read saved configuration from the server.'); }
                    if (!response.ok || !data.success) throw new Error(data.message || 'Could not load saved configuration.');
                    ptRestoreSavedRows(data.rows || [], data.confirmation_rows || []);
                    return data.rows || [];
                });
            }).catch(function (error) {
                PT_CFG_LOAD_PROMISE = null;
                setFootStatus((error && error.message) || 'Could not load saved configuration.', 'error');
                throw error;
            });
            return PT_CFG_LOAD_PROMISE;
        }

        // Expose read-only configuration access for the virtual candle matcher.
        window.ptGetTrainingConfiguration = function () { return PT_CFG; };

        // ---------- public API ----------
        window.ptOpenConfigModal = function () {
            var m = el('ptConfigModal');
            if (!m) return;
            m.classList.add('active');
            setFootStatus('Loading saved configuration…');
            ptLoadSavedConfiguration(true).then(function () {
                render();
                updateFootStatus();
                if (!PT_CFG.roots.length) setFootStatus('No saved configuration yet. Add a root to begin.');
                else setFootStatus('Loaded saved configuration: ' + PT_CFG.roots.length + ' root(s).', 'ok');
            }).catch(function () {
                render();
                updateFootStatus();
            });
        };
        window.ptCloseConfigModal = function () {
            var m = el('ptConfigModal');
            if (m) m.classList.remove('active');
            setFootStatus('');
        };
        window.ptCfgAddRoot = function () {
            PT_CFG.roots.push(createRoot());
            render();
            updateFootStatus();
        };

        // Keep the footer status in sync whenever render() runs.
        var _origRender = render;
        render = function () {
            _origRender();
            updateFootStatus();
        };

        // Click outside closes
        document.addEventListener('DOMContentLoaded', function () {
            var m = el('ptConfigModal');
            if (m) {
                m.addEventListener('mousedown', function (e) {
                    if (e.target === m) window.ptCloseConfigModal();
                });
            }

            var deleteModal = el('ptRootDeleteModal');
            var cancelDelete = el('ptRootDeleteCancel');
            var confirmDelete = el('ptRootDeleteConfirm');
            if (cancelDelete) cancelDelete.addEventListener('click', closeRootDeleteConfirmation);
            if (confirmDelete) confirmDelete.addEventListener('click', confirmRootDelete);
            if (deleteModal) {
                deleteModal.addEventListener('mousedown', function (e) {
                    if (e.target === deleteModal) closeRootDeleteConfirmation();
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && deleteModal && deleteModal.classList.contains('active')) {
                    closeRootDeleteConfirmation();
                }
            });

            render();
            updateFootStatus();

            // Load saved roots/trees on page start and re-evaluate the current chart.
            ptLoadSavedConfiguration(false).then(function () {
                try { ptScheduleDraw(); } catch (_) {}
            }).catch(function () {});
        });
    })();

    // Dynamic console diagnostics have been removed.
    // Horizontally scrollable control strip with floating edge arrows.
    function ptUpdateControlsScrollButtons() {
        var row = document.getElementById('ptControlsButtonRow');
        var left = document.getElementById('ptControlsScrollLeft');
        var right = document.getElementById('ptControlsScrollRight');
        if (!row || !left || !right) return;
        var max = Math.max(0, row.scrollWidth - row.clientWidth);
        var hasOverflow = max > 2;
        left.hidden = !hasOverflow || row.scrollLeft <= 2;
        right.hidden = !hasOverflow || row.scrollLeft >= max - 2;
    }
    function ptScrollControls(direction) {
        var row = document.getElementById('ptControlsButtonRow');
        if (!row) return;
        var distance = Math.max(180, Math.floor(row.clientWidth * 0.78));
        row.scrollBy({left: direction * distance, behavior:'smooth'});
        window.setTimeout(ptUpdateControlsScrollButtons, 350);
    }
    window.ptToggleMobileCrosshair = function() {
        var button = document.getElementById('ptMobileCrosshairToggle');
        PT_MOBILE_CROSSHAIR_MODE = !PT_MOBILE_CROSSHAIR_MODE;
        if (button) {
            button.setAttribute('aria-pressed', PT_MOBILE_CROSSHAIR_MODE ? 'true' : 'false');
            button.title = PT_MOBILE_CROSSHAIR_MODE
                ? 'Crosshair mode is on. Touch or move on the chart to position it; toggle off to pan.'
                : 'Toggle mobile crosshair mode';
        }
        if (!PT_MOBILE_CROSSHAIR_MODE) {
            PT_TOUCH_STATE.crosshairActive = false;
        } else if (PT_CANVAS && PT_CANDLES.length) {
            var rect = PT_CANVAS.getBoundingClientRect();
            ptSetMobileCrosshairAt(rect.left + rect.width / 2, rect.top + rect.height / 2);
        }
    };
    function ptSetMobileCrosshairAt(clientX, clientY) {
        if (!PT_CANVAS || !PT_CANDLES.length) return;
        var rect = PT_CANVAS.getBoundingClientRect();
        var x = clientX - rect.left, y = clientY - rect.top;
        var step = PT_CANVAS._ptStep || ptStep();
        var baseX = PT_CANVAS._ptBaseX || (PT_PADDING_LEFT + PT_VIRTUAL_PAD);
        var idx = Math.floor((x - baseX) / step);
        PT_HOVER_INDEX = (idx >= 0 && idx < PT_CANDLES.length) ? idx : -1;
        PT_CURSOR_Y = (x >= PT_PADDING_LEFT && x <= PT_CANVAS.clientWidth - PT_PADDING_RIGHT) ? y : null;
        ptScheduleDraw();
        ptUpdateReadout();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var controlRow = document.getElementById('ptControlsButtonRow');
        var scrollLeftButton = document.getElementById('ptControlsScrollLeft');
        var scrollRightButton = document.getElementById('ptControlsScrollRight');
        if (scrollLeftButton) scrollLeftButton.addEventListener('click', function(){ ptScrollControls(-1); });
        if (scrollRightButton) scrollRightButton.addEventListener('click', function(){ ptScrollControls(1); });
        if (controlRow) {
            controlRow.addEventListener('scroll', ptUpdateControlsScrollButtons, {passive:true});
            window.addEventListener('resize', ptUpdateControlsScrollButtons);
            if (window.ResizeObserver) new ResizeObserver(ptUpdateControlsScrollButtons).observe(controlRow);
            window.setTimeout(ptUpdateControlsScrollButtons, 0);
        }
        var rootsModal = document.getElementById('ptRootsDataModal');
        if (rootsModal) rootsModal.addEventListener('mousedown', function(e){ if(e.target===rootsModal) window.ptCloseRootsData(); });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') window.ptCloseRootsData();
        });
    });

</script>

</body>
</html>