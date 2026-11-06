<?php
// programmes.php — Investor view (tabbed: Programmes + Invested + Sent)
// Per sub-account scoped. Broker-gated. Request flow for joining.
session_start();

// ==================== DATABASE CONNECTION ====================
try {
    require_once __DIR__ . '/mysqlconnection.php';
    $pdo = getAvailablePdoConnection();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Shared database connection manager did not return PDO.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log(basename(__FILE__) . ' database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

// ==================== CENTRAL NOTIFICATION SERVICE ====================
require_once __DIR__ . '/notification_service.php';

// ==================== CHECK LOGIN ====================
if (!isset($_SESSION['user_email'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$email = strtolower($_SESSION['user_email']);

// ==================== RESOLVE ACTIVE SUB ACCOUNT ====================
$activeSubAccountId = (int)($_SESSION['active_sub_account_id'] ?? 0);

if ($activeSubAccountId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE sub_account_id = ? AND LOWER(email) = ? LIMIT 1");
    $stmt->execute([$activeSubAccountId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null;
}

if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? AND is_main_account = 0 ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$user) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    header("Location: index.php");
    exit;
}

$userId             = (int)$user['id'];
$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$fullName      = $user['fullname'] ?? 'User';
$darkMode      = isset($user['dark_mode']) ? (int)$user['dark_mode'] : 0;
$darkModeClass = ($darkMode === 1) ? 'dark-mode' : '';

$viewerBroker = strtolower(trim((string)($user['broker'] ?? '')));

// A private programme may be opened only through its direct programme link.
$linkedProgrammeId = (int)($_GET['programme_id'] ?? $_GET['programme'] ?? 0);

// ==================== HELPERS ====================
if (!function_exists('resolveDeveloperDisplayName')) {
    function resolveDeveloperDisplayName(array $devRow) {
        $username = trim((string)($devRow['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($devRow['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($devRow['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($devRow['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('resolvePeriodLabel')) {
    function resolvePeriodLabel($period) {
        $p = strtolower(trim((string)$period));
        if ($p === 'weekly')  return 'Weekly';
        if ($p === 'monthly') return 'Monthly';
        if ($p === 'daily')   return 'Daily';
        return 'Daily';
    }
}

if (!function_exists('normalizeBroker')) {
    function normalizeBroker($b) {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', (string)$b)));
    }
}

if (!function_exists('resolveProgrammeBroker')) {
    function resolveProgrammeBroker(array $programmeRow, $pdo) {
        $b = normalizeBroker($programmeRow['broker'] ?? '');
        if ($b !== '') return $b;

        if (!empty($programmeRow['userid'])) {
            try {
                $q = $pdo->prepare("SELECT broker FROM harvhub WHERE id = ? LIMIT 1");
                $q->execute([(int)$programmeRow['userid']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['broker'])) {
                    return normalizeBroker($row['broker']);
                }
            } catch (PDOException $e) {}
        }
        return '';
    }
}

if (!function_exists('prettyBroker')) {
    function prettyBroker($b) {
        $b = trim((string)$b);
        if ($b === '') return '';
        return ucfirst(strtolower($b));
    }
}

if (!function_exists('prettyTimezone')) {
    function prettyTimezone($tz) {
        $tz = trim((string)$tz);
        if ($tz === '') return '';
        // Convert "Africa/Lagos" → "Africa / Lagos"
        return str_replace('_', ' ', str_replace('/', ' / ', $tz));
    }
}

if (!function_exists('resolveHostDisplayNameCompat')) {
    function resolveHostDisplayNameCompat(array $r) {
        $username = trim((string)($r['username'] ?? ''));
        if ($username !== '') return $username;
        $firstName = trim((string)($r['first_name'] ?? ''));
        if ($firstName !== '') return $firstName;
        $lastName = trim((string)($r['last_name'] ?? ''));
        if ($lastName !== '') return $lastName;
        $fullName = trim((string)($r['fullname'] ?? ''));
        if ($fullName !== '') return $fullName;
        return 'N/A';
    }
}

if (!function_exists('resolveUserEmailById')) {
    function resolveUserEmailById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT email FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return strtolower(trim((string)($row['email'] ?? '')));
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('resolveUserMainAccountIdById')) {
    function resolveUserMainAccountIdById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT main_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['main_account_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('resolveUserSubAccountIdById')) {
    function resolveUserSubAccountIdById($pdo, $userId) {
        try {
            $q = $pdo->prepare("SELECT sub_account_id FROM harvhub WHERE id = ? LIMIT 1");
            $q->execute([$userId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            return (int)($row['sub_account_id'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

// =====================================================================
// INVESTOR READ-ONLY DATA HELPERS
// These functions only SELECT saved programme data. They deliberately do
// not invoke the signals challenge evaluator or analytics write/sync code.
// =====================================================================
if (!function_exists('progInvestorTableColumns')) {
    function progInvestorTableColumns(PDO $pdo, string $table): array {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return [];
        try {
            $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
            return $q ? $q->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('progInvestorTableExists')) {
    function progInvestorTableExists(PDO $pdo, string $table): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        try {
            $q = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
            return (bool)($q ? $q->fetchColumn() : false);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('progInvestorJsonList')) {
    function progInvestorJsonList($raw): array {
        if (is_array($raw)) $decoded = $raw;
        else {
            $decoded = json_decode((string)$raw, true);
            if (!is_array($decoded)) $decoded = [];
        }
        $out = [];
        foreach ($decoded as $value) {
            if (is_array($value)) {
                if (isset($value['date']) && is_scalar($value['date'])) {
                    $value = (string)$value['date'];
                } else {
                    continue;
                }
            }
            if (!is_scalar($value)) continue;
            $value = trim((string)$value);
            if ($value !== '') $out[$value] = $value;
        }
        return array_values($out);
    }
}

if (!function_exists('progInvestorParseList')) {
    function progInvestorParseList($raw): array {
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
}

if (!function_exists('progInvestorFetchConfigRows')) {
    function progInvestorFetchConfigRows(PDO $pdo, string $table, int $programmeId, int $developerId): array {
        $columns = progInvestorTableColumns($pdo, $table);
        if (!$columns) return [];
        $map = [];
        foreach ($columns as $column) $map[strtolower((string)$column)] = (string)$column;
        $programmeColumn = null;
        foreach (['programme_id', 'programmeid', 'program_id'] as $candidate) {
            if (isset($map[$candidate])) { $programmeColumn = $map[$candidate]; break; }
        }
        $userColumn = null;
        foreach (['user_id', 'userid', 'developerid'] as $candidate) {
            if (isset($map[$candidate])) { $userColumn = $map[$candidate]; break; }
        }
        if (!$programmeColumn || !$userColumn) return [];
        try {
            $q = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE `' . $programmeColumn . '` = ? AND `' . $userColumn . '` = ?');
            $q->execute([$programmeId, $developerId]);
            return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Investor programme configuration read failed for ' . $table . ': ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('progInvestorTfMs')) {
    function progInvestorTfMs($timeframe): int {
        $tf = strtolower(trim((string)$timeframe));
        if (!preg_match('/^(\d+)\s*([mhdw])$/', $tf, $m)) return PHP_INT_MAX;
        return (int)$m[1] * ['m'=>60000, 'h'=>3600000, 'd'=>86400000, 'w'=>604800000][$m[2]];
    }
}

if (!function_exists('progInvestorLoadRoots')) {
    function progInvestorLoadRoots(PDO $pdo, int $programmeId, int $developerId): array {
        $roots = [];
        $ensureRoot = static function(int $rootId, string $rootName = '') use (&$roots): void {
            if ($rootId <= 0) return;
            if (!isset($roots[$rootId])) {
                $roots[$rootId] = [
                    'root_id'=>$rootId,
                    'root_name'=>$rootName !== '' ? $rootName : 'Strategy ' . $rootId,
                    'timeframes'=>[], 'trade_enabled'=>false, 'trade_for'=>'root', 'confirmations'=>[]
                ];
            } elseif ($rootName !== '') {
                $roots[$rootId]['root_name'] = $rootName;
            }
        };

        $mainRows = progInvestorFetchConfigRows($pdo, 'programme_training_configuration', $programmeId, $developerId);
        foreach ($mainRows as $row) {
            $rootId = (int)($row['root_id'] ?? 0);
            if ($rootId <= 0) continue;
            $ensureRoot($rootId, trim((string)($row['root_name'] ?? '')));
            foreach (progInvestorParseList($row['timeframe'] ?? '') as $tf) $roots[$rootId]['timeframes'][] = strtolower($tf);
            $tradeFor = strtolower(trim((string)($row['trade_for'] ?? 'root')));
            if ($tradeFor !== '') $roots[$rootId]['trade_for'] = $tradeFor;
            if ($tradeFor === 'root' && (int)($row['use_for_trade'] ?? 0) === 1) $roots[$rootId]['trade_enabled'] = true;
            $legacyConfirmationId = (int)($row['confirmation_root_id'] ?? 0);
            if ($legacyConfirmationId > 0 && !isset($roots[$rootId]['confirmations'][$legacyConfirmationId])) {
                $roots[$rootId]['confirmations'][$legacyConfirmationId] = [
                    'confirmation_root_id'=>$legacyConfirmationId,
                    'confirmation_root_name'=>'Confirmation ' . $legacyConfirmationId,
                    'timeframes'=>[], 'trade_enabled'=>false
                ];
            }
        }

        $confirmationRows = progInvestorFetchConfigRows($pdo, 'programme_training_confirmation_configuration', $programmeId, $developerId);
        foreach ($confirmationRows as $row) {
            $parentId = (int)($row['parent_root_id'] ?? $row['parentrootid'] ?? $row['parent_id'] ?? 0);
            $confirmationId = (int)($row['root_id'] ?? $row['confirmation_root_id'] ?? 0);
            if ($parentId <= 0 || $confirmationId <= 0) continue;
            $ensureRoot($parentId);
            $confirmationName = trim((string)($row['root_name'] ?? $row['confirmation_root_name'] ?? ''));
            if (!isset($roots[$parentId]['confirmations'][$confirmationId])) {
                $roots[$parentId]['confirmations'][$confirmationId] = [
                    'confirmation_root_id'=>$confirmationId,
                    'confirmation_root_name'=>$confirmationName !== '' ? $confirmationName : 'Confirmation ' . $confirmationId,
                    'timeframes'=>[], 'trade_enabled'=>false
                ];
            } elseif ($confirmationName !== '') {
                $roots[$parentId]['confirmations'][$confirmationId]['confirmation_root_name'] = $confirmationName;
            }
            foreach (progInvestorParseList($row['timeframe'] ?? $row['timeframes'] ?? '') as $tf) $roots[$parentId]['confirmations'][$confirmationId]['timeframes'][] = strtolower($tf);
            if ((int)($row['use_for_trade'] ?? $row['trade_enabled'] ?? $row['is_trade_enabled'] ?? 0) === 1) {
                $roots[$parentId]['confirmations'][$confirmationId]['trade_enabled'] = true;
            }
        }

        foreach ($roots as &$root) {
            $root['timeframes'] = array_values(array_unique(array_filter(array_map(static function($v) { return strtolower(trim((string)$v)); }, $root['timeframes']))));
            usort($root['timeframes'], static function($a, $b) {
                $aa = progInvestorTfMs($a); $bb = progInvestorTfMs($b);
                return $aa === $bb ? strcmp($a, $b) : ($aa <=> $bb);
            });
            foreach ($root['confirmations'] as &$confirmation) {
                $confirmation['timeframes'] = array_values(array_unique(array_filter(array_map(static function($v) { return strtolower(trim((string)$v)); }, $confirmation['timeframes']))));
                usort($confirmation['timeframes'], static function($a, $b) {
                    $aa = progInvestorTfMs($a); $bb = progInvestorTfMs($b);
                    return $aa === $bb ? strcmp($a, $b) : ($aa <=> $bb);
                });
            }
            unset($confirmation);
            ksort($root['confirmations'], SORT_NUMERIC);
            $root['confirmations'] = array_values($root['confirmations']);
        }
        unset($root);
        ksort($roots, SORT_NUMERIC);
        return array_values($roots);
    }
}

if (!function_exists('progInvestorTimezone')) {
    function progInvestorTimezone(string $name): DateTimeZone {
        try { return new DateTimeZone(trim($name) !== '' ? trim($name) : 'UTC'); }
        catch (Throwable $e) { return new DateTimeZone('UTC'); }
    }
}

if (!function_exists('progInvestorFetchProvision')) {
    function progInvestorFetchProvision(
        PDO $pdo,
        int $developerId,
        int $programmeId,
        string $programmeTimezone = '',
        bool $isAdvertised = false
    ): array {
        $empty = [
            'available' => false,
            'begin_test' => 0,
            'period' => 'daily',
            'trades_count' => null,
            'expected_win' => null,
            'consecutive_loss' => null,
            'status' => '',
            'failed_period_index' => null,
            'windows' => [],
            'current_index' => 0
        ];

        if ($developerId <= 0 || $programmeId <= 0 || !progInvestorTableExists($pdo, 'signals_provider_interest')) {
            return $empty;
        }

        try {
            // Display the latest saved request only. This is a SELECT-only path;
            // it never evaluates, advances, resets or changes the provision.
            $q = $pdo->prepare(
                'SELECT * FROM `signals_provider_interest`
                 WHERE `user_id` = ? AND `programme_id` = ?
                 ORDER BY `id` DESC LIMIT 1'
            );
            $q->execute([$developerId, $programmeId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Investor provision read failed: ' . $e->getMessage());
            return $empty;
        }

        if (!$row) return $empty;

        $period = strtolower(trim((string)($row['trades_provision'] ?? 'daily')));
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        $timezoneName = trim((string)($row['challenge_timezone'] ?? ''));
        if ($timezoneName === '') $timezoneName = trim($programmeTimezone);
        $timezone = progInvestorTimezone($timezoneName);
        $now = new DateTimeImmutable('now', $timezone);
        $todayDate = $now->format('Y-m-d');

        $startedAt = null;
        $startedRaw = trim((string)($row['signal_testing_started_at'] ?? ''));
        if ($startedRaw !== '') {
            try { $startedAt = new DateTimeImmutable($startedRaw, $timezone); }
            catch (Throwable $e) { $startedAt = null; }
        }

        $challengeEnd = null;
        $endRaw = trim((string)($row['challenge_end_at'] ?? ''));
        if ($endRaw !== '') {
            try { $challengeEnd = new DateTimeImmutable($endRaw, $timezone); }
            catch (Throwable $e) { $challengeEnd = null; }
        }

        $progressColumn = $period === 'weekly'
            ? 'weekly_progress_json'
            : ($period === 'monthly' ? 'monthly_progress_json' : 'daily_progress_json');

        $periodRows = json_decode((string)($row[$progressColumn] ?? '[]'), true);
        if (!is_array($periodRows)) $periodRows = [];
        $periodRows = array_values(array_filter($periodRows, 'is_array'));
        usort($periodRows, static function ($a, $b) {
            $ai = (int)($a['index'] ?? 0);
            $bi = (int)($b['index'] ?? 0);
            return $ai === $bi
                ? strcmp((string)($a['start_date'] ?? ''), (string)($b['start_date'] ?? ''))
                : ($ai <=> $bi);
        });

        $windows = [];
        if ($startedAt !== null && $startedAt <= $now) {
            $testStartDate = $startedAt->format('Y-m-d');
            $testEndDate = $challengeEnd ? $challengeEnd->format('Y-m-d') : '';

            foreach ($periodRows as $periodRow) {
                $rawStart = trim((string)($periodRow['start_date'] ?? ''));
                $rawEnd = trim((string)($periodRow['end_date'] ?? $rawStart));
                if ($rawStart === '') continue;

                try {
                    $periodStart = new DateTimeImmutable($rawStart, $timezone);
                    $periodEnd = new DateTimeImmutable($rawEnd !== '' ? $rawEnd : $rawStart, $timezone);
                } catch (Throwable $e) {
                    continue;
                }

                $periodStartDate = $periodStart->format('Y-m-d');
                $periodEndDate = $periodEnd->format('Y-m-d');

                // Every saved checkpoint in the scheduled test window is retained
                // for a programme that has not yet been advertised. This includes
                // locked future Day / Week / Month windows, even when not completed.
                if ($periodEndDate < $testStartDate) continue;
                if ($testEndDate !== '' && $periodStartDate > $testEndDate) continue;

                $visibleStart = max($periodStartDate, $testStartDate);
                $visibleEnd = $periodEndDate;
                if ($testEndDate !== '' && $visibleEnd > $testEndDate) {
                    $visibleEnd = $testEndDate;
                }

                // Once advertised, the test window is presented only through
                // today's date. Future checkpoints are hidden and the latest
                // current/past checkpoint becomes the default front window.
                if ($isAdvertised) {
                    if ($periodStartDate > $todayDate) continue;
                    $visibleEnd = min($visibleEnd, $todayDate);
                }

                if ($visibleEnd < $visibleStart) continue;

                $checkpointIndex = (int)($periodRow['index'] ?? count($windows));
                $checkpointStatus = strtolower(trim((string)($periodRow['status'] ?? '')));

                $windows[] = [
                    'index' => $checkpointIndex,
                    'label' => trim((string)($periodRow['label'] ?? '')) !== ''
                        ? (string)$periodRow['label']
                        : ucfirst($period) . ' ' . ($checkpointIndex + 1),
                    'start_date' => $visibleStart,
                    'end_date' => $visibleEnd,
                    'status' => $checkpointStatus,
                    'locked' => !empty($periodRow['locked']) || $checkpointStatus === 'locked',
                    'trades' => (int)($periodRow['trades'] ?? 0),
                    'wins' => (int)($periodRow['wins'] ?? 0),
                    'consecutive_losses' => (int)($periodRow['consecutive_losses'] ?? 0),
                    'trades_passed' => !empty($periodRow['trades_passed']),
                    'wins_passed' => !empty($periodRow['wins_passed']),
                    'loss_gate_passed' => !empty($periodRow['loss_gate_passed'])
                ];
            }
        }

        usort($windows, static function ($a, $b) {
            $ai = (int)($a['index'] ?? 0);
            $bi = (int)($b['index'] ?? 0);
            return $ai <=> $bi;
        });

        $currentIndex = 0;
        if ($windows) {
            if ($isAdvertised) {
                // The latest window whose start is not after today is the cover.
                $currentIndex = count($windows) - 1;
            } else {
                $savedCurrentIndex = (int)($row['challenge_current_period_index'] ?? -1);
                $foundCurrent = false;
                foreach ($windows as $position => $window) {
                    if ((int)$window['index'] === $savedCurrentIndex) {
                        $currentIndex = $position;
                        $foundCurrent = true;
                        break;
                    }
                }
                if (!$foundCurrent) {
                    foreach ($windows as $position => $window) {
                        if ((string)$window['start_date'] <= $todayDate) $currentIndex = $position;
                    }
                }
            }
        }

        return [
            'available' => true,
            'begin_test' => (int)($row['begin_test'] ?? 0),
            'period' => $period,
            'trades_count' => $row['trades_count'] ?? null,
            'expected_win' => $row['expected_win_count'] ?? null,
            'consecutive_loss' => $row['consecutive_expected_loss'] ?? null,
            'status' => strtolower(trim((string)($row['interest_status'] ?? ''))),
            'failed_period_index' => array_key_exists('challenge_failed_period_index', $row)
                && $row['challenge_failed_period_index'] !== null
                    ? (int)$row['challenge_failed_period_index']
                    : null,
            'windows' => $windows,
            'current_index' => $currentIndex
        ];
    }
}

if (!function_exists('progInvestorCanViewProgramme')) {
    function progInvestorCanViewProgramme(PDO $pdo, array $programme, int $viewerId, int $activeSubAccountId, int $linkedProgrammeId): bool {
        $pid = (int)($programme['id'] ?? 0);
        $isPublic = (int)($programme['advertisement'] ?? 0) === 1
            && (int)($programme['server_advertisement_approval'] ?? 0) === 1
            && (int)($programme['visibility'] ?? 0) === 1;
        if ($isPublic || ($linkedProgrammeId > 0 && $linkedProgrammeId === $pid)) return true;
        try {
            $q = $pdo->prepare('SELECT `id` FROM `programme_investors` WHERE `investorid` = ? AND (`sub_account_id` = ? OR `sub_account_id` IS NULL) AND `programme_id` = ? LIMIT 1');
            $q->execute([$viewerId, $activeSubAccountId, $pid]);
            return (bool)$q->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('progInvestorFetchAnalytics')) {
    function progInvestorFetchAnalytics(PDO $pdo, int $programmeId, int $developerId, $selectedSymbolsRaw = ''): array {
        $result = ['rows'=>[], 'roots'=>[], 'symbols'=>[], 'error'=>''];
        $mainRows = progInvestorFetchConfigRows($pdo, 'programme_training_configuration', $programmeId, $developerId);
        $hasConfiguredRoots = false;
        foreach ($mainRows as $row) {
            if ((int)($row['root_id'] ?? 0) > 0) { $hasConfiguredRoots = true; break; }
        }
        $roots = $hasConfiguredRoots ? progInvestorLoadRoots($pdo, $programmeId, $developerId) : [];
        if (!progInvestorTableExists($pdo, 'programme_analytics')) {
            $result['error'] = 'The programme analytics table is not installed.';
            return $result;
        }
        $columns = progInvestorTableColumns($pdo, 'programme_analytics');
        $columnMap = array_fill_keys(array_map('strtolower', $columns), true);
        if (!isset($columnMap['programme_id']) || !isset($columnMap['userid'])) {
            $result['error'] = 'The programme analytics table is missing its programme or developer reference columns.';
            return $result;
        }
        try {
            $q = $pdo->prepare('SELECT * FROM `programme_analytics` WHERE `programme_id` = ? AND `userid` = ? ORDER BY `id` ASC');
            $q->execute([$programmeId, $developerId]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Investor programme analytics read failed: ' . $e->getMessage());
            $result['error'] = 'Programme analytics could not be loaded.';
            return $result;
        }

        // Older analytics installations may not contain live_trades. Keep those
        // legacy rows visible in the Live source without altering the database.
        foreach ($rows as &$row) {
            if (!array_key_exists('live_trades', $row)) $row['live_trades'] = 1;
        }
        unset($row);

        // Build an internal-only strategy/confirmation lookup. It is deliberately
        // never returned to the investor's browser.
        if (!$roots && $rows) {
            $fallbackRoots = [];
            foreach ($rows as $row) {
                $rid = (int)($row['root_id'] ?? 0);
                if ($rid <= 0) continue;
                if (!isset($fallbackRoots[$rid])) {
                    $fallbackRoots[$rid] = [
                        'root_id'=>$rid,
                        'root_name'=>trim((string)($row['root_name'] ?? '')) ?: 'Strategy ' . $rid,
                        'timeframes'=>[], 'trade_enabled'=>false, 'trade_for'=>'root', 'confirmations'=>[]
                    ];
                }
                $tf = strtolower(trim((string)($row['timeframe'] ?? '')));
                if ($tf !== '') $fallbackRoots[$rid]['timeframes'][] = $tf;
                if ((int)($row['trade_configured'] ?? 0) === 1) $fallbackRoots[$rid]['trade_enabled'] = true;
                $cid = (int)($row['confirmation_root_id'] ?? 0);
                if ($cid > 0) {
                    if (!isset($fallbackRoots[$rid]['confirmations'][$cid])) {
                        $fallbackRoots[$rid]['confirmations'][$cid] = [
                            'confirmation_root_id'=>$cid,
                            'confirmation_root_name'=>trim((string)($row['confirmation_root_name'] ?? '')) ?: 'Confirmation ' . $cid,
                            'timeframes'=>[], 'trade_enabled'=>false
                        ];
                    }
                    $ctf = strtolower(trim((string)($row['source_timeframe'] ?? $row['timeframe'] ?? '')));
                    if ($ctf !== '') $fallbackRoots[$rid]['confirmations'][$cid]['timeframes'][] = $ctf;
                    if ((int)($row['trade_configured'] ?? 0) === 1) $fallbackRoots[$rid]['confirmations'][$cid]['trade_enabled'] = true;
                }
            }
            foreach ($fallbackRoots as &$root) {
                $root['timeframes'] = array_values(array_unique($root['timeframes']));
                ksort($root['confirmations'], SORT_NUMERIC);
                foreach ($root['confirmations'] as &$confirmation) $confirmation['timeframes'] = array_values(array_unique($confirmation['timeframes']));
                unset($confirmation);
                $root['confirmations'] = array_values($root['confirmations']);
            }
            unset($root);
            ksort($fallbackRoots, SORT_NUMERIC);
            $roots = array_values($fallbackRoots);
        }

        $symbols = progInvestorParseList($selectedSymbolsRaw);
        if (!$symbols) {
            $symbolSet = [];
            foreach ($rows as $row) {
                $symbol = trim((string)($row['symbol'] ?? ''));
                if ($symbol !== '') $symbolSet[$symbol] = $symbol;
            }
            $symbols = array_values($symbolSet);
        }
        $symbols = array_values(array_unique(array_filter(array_map('strval', $symbols))));
        sort($symbols, SORT_NATURAL | SORT_FLAG_CASE);
        $symbolLookup = array_fill_keys($symbols, true);

        $rootLookup = [];
        foreach ($roots as $root) {
            $rid = (int)($root['root_id'] ?? 0);
            if ($rid > 0) $rootLookup[$rid] = $root;
        }
        $rowMatchesTimeframes = static function(array $row, array $timeframes): bool {
            if (!$timeframes) return false;
            $candidates = [
                strtolower(trim((string)($row['timeframe'] ?? ''))),
                strtolower(trim((string)($row['source_timeframe'] ?? '')))
            ];
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && in_array($candidate, $timeframes, true)) return true;
            }
            return false;
        };

        // Only the programme's selected symbols are included. All configured
        // strategies and confirmations are combined here, and all configured
        // timeframes are retained. Internal IDs/configuration are then stripped.
        $publicRows = [];
        foreach ($rows as $row) {
            $symbol = trim((string)($row['symbol'] ?? ''));
            if ($symbol === '' || !isset($symbolLookup[$symbol])) continue;
            if ((int)($row['matched_family'] ?? 0) === 0) continue;

            $rootId = (int)($row['root_id'] ?? 0);
            $root = $rootLookup[$rootId] ?? null;
            if (!$root) continue;
            $rootTimeframes = array_values(array_unique(array_map('strtolower', $root['timeframes'] ?? [])));
            $confirmationId = (int)($row['confirmation_root_id'] ?? 0);

            if ($confirmationId > 0) {
                $confirmation = null;
                foreach (($root['confirmations'] ?? []) as $candidate) {
                    if ((int)($candidate['confirmation_root_id'] ?? 0) === $confirmationId) {
                        $confirmation = $candidate;
                        break;
                    }
                }
                $confirmationTimeframes = $confirmation
                    ? array_values(array_unique(array_map('strtolower', $confirmation['timeframes'] ?? [])))
                    : [];
                if (!$confirmation || !$confirmationTimeframes) continue;
                if ($rootTimeframes && !$rowMatchesTimeframes($row, $rootTimeframes)) continue;
                if (!$rowMatchesTimeframes($row, $confirmationTimeframes)) continue;
            } elseif ($rootTimeframes && !$rowMatchesTimeframes($row, $rootTimeframes)) {
                continue;
            }

            $outcome = strtolower(trim((string)($row['outcome'] ?? '')));
            $normalizedOutcome = preg_replace('/[\\s_-]+/', ' ', $outcome);
            $dateFields = ['entry_trigger_time', 'entry_anchor_time', 'event_time'];
            if (in_array($normalizedOutcome, ['stoploss hit', 'closed in loss', 'closed loss', 'loss', 'losing'], true)) {
                $dateFields = ['stoploss_trigger_time', 'close_time', 'closed_at', 'event_time', 'entry_trigger_time', 'entry_anchor_time'];
            } elseif (in_array($normalizedOutcome, ['take profit reached', 'closed in profit', 'closed profit', 'profit', 'profitable'], true)) {
                $dateFields = ['take_profit_trigger_time', 'close_time', 'closed_at', 'event_time', 'entry_trigger_time', 'entry_anchor_time'];
            } elseif (in_array($normalizedOutcome, ['closed flat', 'breakeven', 'break even', 'closed at breakeven'], true)) {
                $dateFields = ['breakeven_trigger_time', 'close_time', 'closed_at', 'event_time', 'entry_trigger_time', 'entry_anchor_time'];
            }
            $tradeDate = '';
            foreach ($dateFields as $dateField) {
                $dateValue = trim((string)($row[$dateField] ?? ''));
                if ($dateValue !== '') { $tradeDate = substr($dateValue, 0, 10); break; }
            }
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $tradeDate)) $tradeDate = '';

            // This is the complete investor-facing row. No strategy IDs,
            // confirmation IDs, family numbers/signatures, prices, directions,
            // orders, chart identifiers or exact trigger timestamps are exposed.
            $publicRows[] = [
                'symbol' => $symbol,
                'outcome' => (string)($row['outcome'] ?? ''),
                'risk_reward' => $row['risk_reward'] ?? null,
                'trade_configured' => (int)($row['trade_configured'] ?? 0),
                'live_trades' => (int)($row['live_trades'] ?? 1),
                'trade_date' => $tradeDate
            ];
        }

        $result['rows'] = $publicRows;
        $result['symbols'] = $symbols;
        // Do not send roots or confirmation configuration to the client.
        $result['roots'] = [];
        return $result;
    }
}

// =====================================================================
// AJAX ROUTER
// =====================================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');

    // -------- READ-ONLY PROGRAMME ANALYTICS FOR INVESTORS --------
    if (isset($_POST['get_programme_analytics'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success'=>false, 'message'=>'Invalid programme.']);
            exit;
        }
        try {
            $q = $pdo->prepare('SELECT `id`,`userid`,`program_name`,`visibility`,`advertisement`,`server_advertisement_approval`,`selected_symbols` FROM `programme` WHERE `id` = ? LIMIT 1');
            $q->execute([$pid]);
            $programme = $q->fetch(PDO::FETCH_ASSOC);
            if (!$programme || !progInvestorCanViewProgramme($pdo, $programme, $userId, $activeSubAccountId, $linkedProgrammeId)) {
                echo json_encode(['success'=>false, 'message'=>'Programme analytics are not available for this programme.']);
                exit;
            }
            $data = progInvestorFetchAnalytics($pdo, $pid, (int)$programme['userid'], $programme['selected_symbols'] ?? '');
            echo json_encode([
                'success'=>true,
                'programme_name'=>(string)($programme['program_name'] ?? 'Programme'),
                'rows'=>$data['rows'],
                'error'=>$data['error']
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            error_log('Investor programme analytics endpoint failed: ' . $e->getMessage());
            echo json_encode(['success'=>false, 'message'=>'Failed to load programme analytics.']);
        }
        exit;
    }

    // -------- GET PROGRAMME DETAILS --------
    if (isset($_POST['get_programme_details'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id,
                       p.program_name, p.visibility, p.advertisement, p.server_advertisement_approval, p.broker, p.timezone,
                       h.fullname, h.first_name, h.last_name, h.username,
                       h.email AS developer_email
                FROM programme p
                INNER JOIN harvhub h ON h.id = p.userid
                WHERE p.id = ?
                LIMIT 1
            ");
            // The View modal is only called for a programme already surfaced by the
            // programme list or by a direct-URL search. Resolve the requested record
            // by its exact ID here instead of re-applying public-advertisement filters.
            // Those filters are for discovery/listing, not for loading the selected
            // programme's details after it has been found.
            $stmt->execute([$pid]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme || !progInvestorCanViewProgramme($pdo, $programme, $userId, $activeSubAccountId, $linkedProgrammeId)) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not available to this investor.']);
                exit;
            }

            $developerName  = resolveDeveloperDisplayName($programme);
            $programmeBroker = resolveProgrammeBroker($programme, $pdo);
            $programmeTimezone = trim((string)($programme['timezone'] ?? ''));
            $isAdvertised = (int)($programme['advertisement'] ?? 0) === 1
                && (int)($programme['server_advertisement_approval'] ?? 0) === 1;
            $provision = progInvestorFetchProvision($pdo, (int)$programme['userid'], $pid, $programmeTimezone, $isAdvertised);

            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$pid, (int)$programme['userid']]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                $stmt = $pdo->prepare("
                    SELECT contract_duration, developer_percentage, investor_percentage,
                           minimum_investment_amount, maximum_investment_amount
                    FROM programme_investors
                    WHERE programme_id = ? AND investorid = 0
                    ORDER BY id ASC
                    LIMIT 1
                ");
                $stmt->execute([$pid]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
            $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
            $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
            $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

            $contractDuration = $req && (int)$req['contract_duration'] > 0 ? (int)$req['contract_duration'] : $srvContractDuration;
            $minInvestment    = $req && (float)$req['minimum_investment_amount'] > 0 ? (float)$req['minimum_investment_amount'] : $srvMinBrokerBalance;
            $maxInvestment    = $req ? (float)$req['maximum_investment_amount'] : 0;
            $developerPercent = $req && (int)$req['developer_percentage'] > 0 ? (int)$req['developer_percentage'] : $srvServerShare;
            $investorPercent  = $req && (int)$req['investor_percentage'] > 0 ? (int)$req['investor_percentage'] : $srvUserShare;



            $alreadyInvested = false;
            try {
                $chk = $pdo->prepare("
                    SELECT id FROM programme_investors
                    WHERE investorid = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                      AND programme_id = ?
                    LIMIT 1
                ");
                $chk->execute([$userId, $activeSubAccountId, $pid]);
                if ($chk->fetch(PDO::FETCH_ASSOC)) $alreadyInvested = true;
            } catch (PDOException $e) {}

            $hasAnyInvestment = false;
            try {
                $chk2 = $pdo->prepare("
                    SELECT id FROM programme_investors
                    WHERE investorid = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                    LIMIT 1
                ");
                $chk2->execute([$userId, $activeSubAccountId]);
                if ($chk2->fetch(PDO::FETCH_ASSOC)) $hasAnyInvestment = true;
            } catch (PDOException $e) {}

            $alreadyRequested = false;
            try {
                $rq = $pdo->prepare("
                    SELECT id FROM programme_investment_requestors
                    WHERE programme_id = ?
                      AND requestor_id = ?
                      AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                      AND request_status = 'pending'
                    LIMIT 1
                ");
                $rq->execute([$pid, $userId, $activeSubAccountId]);
                if ($rq->fetch(PDO::FETCH_ASSOC)) $alreadyRequested = true;
            } catch (PDOException $e) {}

            $brokerMatches = false;
            if ($programmeBroker !== '' && $viewerBroker !== '') {
                $brokerMatches = ($programmeBroker === $viewerBroker);
            }
            $viewerHasBroker = ($viewerBroker !== '');

            echo json_encode([
                'success'   => true,
                'programme' => [
                    'id'              => (int)$programme['id'],
                    'program_name'    => $programme['program_name'],
                    'developer_name'  => $developerName,
                    'developer_email' => $programme['developer_email'] ?: '',
                    'developer_id'    => (int)$programme['userid'],
                    'developer_sub_account_id' => (int)($programme['developer_sub_account_id'] ?? 0),
                    'visibility'      => (int)$programme['visibility'],
                    'advertisement'   => (int)$programme['advertisement'],
                    'broker'          => $programmeBroker,
                    'broker_pretty'   => prettyBroker($programmeBroker),
                    'timezone'        => $programmeTimezone,
                    'timezone_pretty' => prettyTimezone($programmeTimezone),
                    'contract_duration'         => $contractDuration,
                    'developer_percentage'      => $developerPercent,
                    'investor_percentage'       => $investorPercent,
                    'minimum_investment_amount' => $minInvestment,
                    'maximum_investment_amount' => $maxInvestment,
                    'provision'                 => $provision,
                ],
                'already_invested'   => $alreadyInvested,
                'has_any_investment' => $hasAnyInvestment,
                'already_requested'  => $alreadyRequested,
                'viewer_id'          => $userId,
                'viewer_broker'      => $viewerBroker,
                'viewer_has_broker'  => $viewerHasBroker,
                'broker_matches'     => $brokerMatches,
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load programme details.']);
        }
        exit;
    }

    // -------- SEND INVESTMENT REQUEST --------
    if (isset($_POST['send_investment_request'])) {
        $pid = (int)($_POST['programme_id'] ?? 0);
        if ($pid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid programme.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id, p.broker, p.program_name
                FROM programme p
                WHERE p.id = ?
                  AND (
                        (p.advertisement = 1
                         AND p.server_advertisement_approval = 1
                         AND p.visibility = 1)
                        OR p.id = ?
                  )
                LIMIT 1
            ");
            $stmt->execute([$pid, $linkedProgrammeId]);
            $programme = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$programme) {
                echo json_encode(['success' => false, 'message' => 'Programme not found or not public.']);
                exit;
            }

            $developerId     = (int)$programme['userid'];
            $developerSubId  = (int)($programme['developer_sub_account_id'] ?? 0);
            $programmeBroker = resolveProgrammeBroker($programme, $pdo);
            $programmeName   = (string)($programme['program_name'] ?? 'Programme');

            if ($programmeBroker === '') {
                echo json_encode(['success' => false, 'message' => "This programme doesn't have a broker configured yet. Please try again later."]);
                exit;
            }
            if ($viewerBroker === '') {
                echo json_encode(['success' => false, 'message' => "You need to connect a broker before sending a request. This programme uses " . prettyBroker($programmeBroker) . ". Please connect the same broker to continue."]);
                exit;
            }
            if ($viewerBroker !== $programmeBroker) {
                echo json_encode(['success' => false, 'message' => "Broker mismatch. This programme uses " . prettyBroker($programmeBroker) . ", but your connected broker is " . prettyBroker($viewerBroker) . ". Please link the same broker as the developer."]);
                exit;
            }

            $chk = $pdo->prepare("
                SELECT id FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
                  AND programme_id = ?
                LIMIT 1
            ");
            $chk->execute([$userId, $activeSubAccountId, $pid]);
            if ($chk->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account has already invested in this programme.']);
                exit;
            }

            $chk2 = $pdo->prepare("
                SELECT id FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
                LIMIT 1
            ");
            $chk2->execute([$userId, $activeSubAccountId]);
            if ($chk2->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account is already participating in a programme. Finish or reset it before joining another.']);
                exit;
            }

            $dup = $pdo->prepare("
                SELECT id FROM programme_investment_requestors
                WHERE programme_id = ?
                  AND requestor_id = ?
                  AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                  AND request_status = 'pending'
                LIMIT 1
            ");
            $dup->execute([$pid, $userId, $activeSubAccountId]);
            if ($dup->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['success' => false, 'message' => 'This account already has a pending request for this programme.']);
                exit;
            }

            $ins = $pdo->prepare("
                INSERT INTO programme_investment_requestors
                    (programme_id, developerid, owner_sub_account_id,
                     requestor_id, requestor_sub_account_id, request_status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([
                $pid,
                $developerId,
                $developerSubId ?: null,
                $userId,
                $activeSubAccountId
            ]);

            // ============================================
            // NOTIFICATION: NEW INVESTMENT REQUEST (to developer)
            // ============================================
            $investorDisplayName = trim((string)($user['username'] ?? ''));
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['first_name'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['fullname'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = 'An investor';
            }

            $developerEmail      = resolveUserEmailById($pdo, $developerId);
            $developerMainAccId  = resolveUserMainAccountIdById($pdo, $developerId);
            $developerSubIdResolved = $developerSubId > 0 ? $developerSubId : resolveUserSubAccountIdById($pdo, $developerId);

            if ($developerEmail !== '' && $developerSubIdResolved > 0) {
                recordContractNotification($pdo, [
                    'user_email'       => $developerEmail,
                    'sub_account_id'   => $developerSubIdResolved,
                    'main_account_id'  => $developerMainAccId,
                    'notification_key' => 'programme-investment-request-' . $pid . '-' . $activeSubAccountId . '-' . date('YmdHis'),
                    'title'            => 'New Investment Request',
                    'message'          => $investorDisplayName . ' has requested to join your ' . $programmeName . ' programme.',
                    'type'             => 'info',
                    'section'          => 'Programme',
                    'action_tab'       => 'programmes',
                    'force'            => true
                ]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Investment request sent successfully.',
                'programme_id' => $pid,
                'developer_id' => $developerId
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to send investment request.']);
        }
        exit;
    }

    // -------- GET SENT REQUESTS --------
    if (isset($_POST['get_sent_investment_requests'])) {
        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.programme_id, r.developerid, r.owner_sub_account_id,
                       r.request_status, r.created_at,
                       h.fullname, h.first_name, h.last_name, h.username,
                       p.program_name
                FROM programme_investment_requestors r
                LEFT JOIN harvhub   h ON h.id = r.developerid
                LEFT JOIN programme p ON p.id = r.programme_id
                WHERE r.requestor_id = ?
                  AND (r.requestor_sub_account_id = ? OR r.requestor_sub_account_id IS NULL)
                  AND r.request_status IN ('pending', 'reject')
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$userId, $activeSubAccountId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$r) {
                $r['developer_display_name'] = resolveHostDisplayNameCompat($r);
            }
            unset($r);

            echo json_encode(['success' => true, 'requests' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'requests' => [], 'message' => 'Failed to load sent requests.']);
        }
        exit;
    }

    // -------- DELETE SENT INVESTMENT REQUEST --------
    if (isset($_POST['delete_investment_request'])) {
        $requestId = (int)($_POST['request_id'] ?? 0);
        if ($requestId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM programme_investment_requestors WHERE id = ? LIMIT 1");
            $stmt->execute([$requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['success' => false, 'message' => 'Request not found.']);
                exit;
            }

            if ((int)$row['requestor_id'] !== $userId) {
                echo json_encode(['success' => false, 'message' => 'You can only delete your own sent requests.']);
                exit;
            }

            $rowReqSub = isset($row['requestor_sub_account_id']) ? (int)$row['requestor_sub_account_id'] : 0;
            if ($rowReqSub > 0 && $rowReqSub !== $activeSubAccountId) {
                echo json_encode(['success' => false, 'message' => 'This request does not belong to the current account.']);
                exit;
            }

            $del = $pdo->prepare("DELETE FROM programme_investment_requestors WHERE id = ?");
            $del->execute([$requestId]);

            echo json_encode(['success' => true, 'message' => 'Request deleted.']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete request.']);
        }
        exit;
    }

    // -------- CANCEL PROGRAMME --------
    if (isset($_POST['cancel_programme'])) {
        try {
            $hasActiveContract     = false;
            $hasPaymentRequirement = false;

            $contract_duration_cfg = 30;
            try {
                $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
                $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
                if ($cfg && !empty($cfg['contract_duration'])) {
                    $contract_duration_cfg = (int)$cfg['contract_duration'];
                }
            } catch (Exception $e) {}

            $exec_start = $user['execution_start_date'] ?? null;
            if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
                try {
                    $start = new DateTime($exec_start);
                    $end   = clone $start;
                    $end->modify("+{$contract_duration_cfg} days");
                    $today = new DateTime();
                    $today->setTime(0, 0, 0);
                    $endClone = clone $end;
                    $endClone->setTime(0, 0, 0);
                    $daysLeft = (int)$today->diff($endClone)->format('%r%a');
                    if ($daysLeft > 0) $hasActiveContract = true;
                } catch (Exception $e) {}
            }

            $payment_issue_statuses = [
                'unpaid-payment', 'unpaid',
                'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
                'contract-cancelled-payment-required',
                'payment-failed', 'failed-payment',
                'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
                'payment-made', 'contract-cancelled-payment-made',
                'pending_payment'
            ];

            $loyalties_status = $user['loyalties'] ?? null;
            if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
                $hasPaymentRequirement = true;
            }

            try {
                $revStmt = $pdo->prepare("
                    SELECT loyalties FROM revenue_history
                    WHERE user_email = ?
                      AND (sub_account_id = ? OR sub_account_id IS NULL)
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $revStmt->execute([$email, $activeSubAccountId]);
                $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
                if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
                    $hasPaymentRequirement = true;
                }
            } catch (Exception $e) {}

            if ($hasActiveContract) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a contract is active."]);
                exit;
            }
            if ($hasPaymentRequirement) {
                echo json_encode(['success' => false, 'message' => "You can't cancel while a payment is required."]);
                exit;
            }

            // Capture programme/developer info BEFORE deleting for the notification
            $cancelProgrammeId   = 0;
            $cancelProgrammeName = '';
            $cancelDeveloperId   = 0;
            $cancelDeveloperSubId = 0;
            try {
                $lookup = $pdo->prepare("
                    SELECT pi.programme_id, pi.developerid,
                           p.program_name, p.sub_account_id AS programme_dev_sub
                    FROM programme_investors pi
                    LEFT JOIN programme p ON p.id = pi.programme_id
                    WHERE pi.investorid = ?
                      AND (pi.sub_account_id = ? OR pi.sub_account_id IS NULL)
                    LIMIT 1
                ");
                $lookup->execute([$userId, $activeSubAccountId]);
                $cancelRow = $lookup->fetch(PDO::FETCH_ASSOC);
                if ($cancelRow) {
                    $cancelProgrammeId    = (int)$cancelRow['programme_id'];
                    $cancelProgrammeName  = (string)($cancelRow['program_name'] ?? 'Programme');
                    $cancelDeveloperId    = (int)$cancelRow['developerid'];
                    $cancelDeveloperSubId = (int)($cancelRow['programme_dev_sub'] ?? 0);
                }
            } catch (Throwable $e) {}

            // ============================================================
            // 1) Remove from programme_investors — scoped to THIS sub-account.
            //    This is what makes the investor count drop on the developer side.
            // ============================================================
            $del = $pdo->prepare("
                DELETE FROM programme_investors
                WHERE investorid = ?
                  AND (sub_account_id = ? OR sub_account_id IS NULL)
            ");
            $del->execute([$userId, $activeSubAccountId]);

            // ============================================================
            // 2) Remove the revenue deal row for this (programme, investor, sub-account)
            //    so the developer's revenue card stops counting this investor.
            // ============================================================
            if ($cancelProgrammeId > 0) {
                try {
                    $delDeal = $pdo->prepare("
                        DELETE FROM programme_revenue_deals
                        WHERE programme_id = ?
                          AND investor_id = ?
                          AND (investor_sub_account_id = ? OR investor_sub_account_id IS NULL OR investor_sub_account_id = 0)
                    ");
                    $delDeal->execute([$cancelProgrammeId, $userId, $activeSubAccountId]);
                } catch (Throwable $e) {}
            }

            // ============================================================
            // 3) Remove any pending investment requests this sub-account sent
            //    for that programme (cleanup — otherwise the developer still
            //    sees a phantom pending request after cancellation).
            // ============================================================
            if ($cancelProgrammeId > 0) {
                try {
                    $delReq = $pdo->prepare("
                        DELETE FROM programme_investment_requestors
                        WHERE programme_id = ?
                          AND requestor_id = ?
                          AND (requestor_sub_account_id = ? OR requestor_sub_account_id IS NULL)
                    ");
                    $delReq->execute([$cancelProgrammeId, $userId, $activeSubAccountId]);
                } catch (Throwable $e) {}
            }

            // ============================================================
            // NOTIFICATION: PROGRAMME CANCELLED (to developer)
            // ============================================================
            $investorDisplayName = trim((string)($user['username'] ?? ''));
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['first_name'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = trim((string)($user['fullname'] ?? ''));
            }
            if ($investorDisplayName === '') {
                $investorDisplayName = 'An investor';
            }

            if ($cancelDeveloperId > 0 && $cancelDeveloperSubId > 0) {
                $developerEmail     = resolveUserEmailById($pdo, $cancelDeveloperId);
                $developerMainAccId = resolveUserMainAccountIdById($pdo, $cancelDeveloperId);

                if ($developerEmail !== '') {
                    recordContractNotification($pdo, [
                        'user_email'       => $developerEmail,
                        'sub_account_id'   => $cancelDeveloperSubId,
                        'main_account_id'  => $developerMainAccId,
                        'notification_key' => 'programme-investor-cancelled-' . $cancelProgrammeId . '-' . $activeSubAccountId . '-' . date('YmdHis'),
                        'title'            => 'Investor Cancelled Programme',
                        'message'          => $investorDisplayName . ' has cancelled their participation in your programme "' . $cancelProgrammeName . '".',
                        'type'             => 'warning',
                        'section'          => 'Programme',
                        'action_tab'       => 'programmes',
                        'force'            => true
                    ]);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'This account has successfully cancelled its programme.'
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel programme.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// =====================================================================
// NON-AJAX page render
// =====================================================================

// ---- SUB-ACCOUNT SCOPED: this sub-account's active investment ----
$activeInvestment = null;
$activeProgrammeId = 0;
$userHasProgramme = false;

try {
    $stmt = $pdo->prepare("
        SELECT id, programme_id, developerid
        FROM programme_investors
        WHERE investorid = ?
          AND (sub_account_id = ? OR sub_account_id IS NULL)
        ORDER BY id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId, $activeSubAccountId]);
    $activeInvestment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($activeInvestment) {
        $userHasProgramme = true;
        $activeProgrammeId = (int)$activeInvestment['programme_id'];
    }
} catch (PDOException $e) {}

// ---- Does this sub-account own a programme? ----
$userOwnsProgramme = false;
try {
    $ow = $pdo->prepare("
        SELECT id FROM programme
        WHERE userid = ?
          AND (sub_account_id = ? OR sub_account_id IS NULL)
        LIMIT 1
    ");
    $ow->execute([$userId, $activeSubAccountId]);
    if ($ow->fetch(PDO::FETCH_ASSOC)) $userOwnsProgramme = true;
} catch (PDOException $e) {}
if (!$userOwnsProgramme) {
    try {
        $ow = $pdo->prepare("SELECT id FROM programme WHERE userid = ? LIMIT 1");
        $ow->execute([$userId]);
        if ($ow->fetch(PDO::FETCH_ASSOC)) $userOwnsProgramme = true;
    } catch (PDOException $e) {}
}

// ---- Public programmes list ----
$programmes = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.userid, p.sub_account_id AS developer_sub_account_id,
               p.program_name, p.visibility, p.advertisement, p.broker, p.timezone,
               h.fullname, h.first_name, h.last_name, h.username,
               h.email AS developer_email, h.broker AS developer_broker
        FROM programme p
        INNER JOIN harvhub h ON h.id = p.userid
        WHERE (
                (p.advertisement = 1
                 AND p.server_advertisement_approval = 1
                 AND p.visibility = 1)
                OR p.id = ?
              )
        ORDER BY p.id DESC
    ");
    $stmt->execute([$linkedProgrammeId]);
    $rawProgrammes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rawProgrammes as $p) {
        $developerName = resolveDeveloperDisplayName($p);
        $programmeBroker = resolveProgrammeBroker($p, $pdo);
        $programmeTimezone = trim((string)($p['timezone'] ?? ''));

        $reqStmt = $pdo->prepare("
            SELECT contract_duration, developer_percentage, investor_percentage,
                   minimum_investment_amount, maximum_investment_amount
            FROM programme_investors
            WHERE programme_id = ? AND developerid = ? AND investorid = 0
            ORDER BY id ASC LIMIT 1
        ");
        $reqStmt->execute([$p['id'], $p['userid']]);
        $req = $reqStmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $reqStmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND investorid = 0
                ORDER BY id ASC LIMIT 1
            ");
            $reqStmt->execute([$p['id']]);
            $req = $reqStmt->fetch(PDO::FETCH_ASSOC);
        }

        $periodLabel = 'Daily';
        try {
            $pStmt = $pdo->prepare("SELECT trades_provision FROM signals_provider_interest WHERE user_id = ? AND programme_id = ? ORDER BY id DESC LIMIT 1");
            $pStmt->execute([(int)$p['userid'], (int)$p['id']]);
            $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
            if ($pRow && !empty($pRow['trades_provision'])) $periodLabel = resolvePeriodLabel($pRow['trades_provision']);
        } catch (Throwable $e) {}

        $programmeLink = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? '')
            . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/')
            . '/programmes.php?programme_id=' . (int)$p['id'];
        $programmes[] = [
            'id' => (int)$p['id'],
            'programme_link' => $programmeLink,
            'program_name' => $p['program_name'],
            'broker' => $programmeBroker,
            'broker_pretty' => prettyBroker($programmeBroker),
            'timezone' => $programmeTimezone,
            'timezone_pretty' => prettyTimezone($programmeTimezone),
            'developer_name' => $developerName,
            'developer_email' => $p['developer_email'],
            'developer_username' => $p['username'],
            'developer_first_name' => $p['first_name'],
            'developer_last_name' => $p['last_name'],
            'developer_fullname' => $p['fullname'],
            'contract_duration' => $req ? (int)$req['contract_duration'] : 30,
            'developer_percentage' => $req ? (int)$req['developer_percentage'] : 30,
            'investor_percentage' => $req ? (int)$req['investor_percentage'] : 70,
            'minimum_investment_amount' => $req ? (float)$req['minimum_investment_amount'] : 0,
            'maximum_investment_amount' => $req ? (float)$req['maximum_investment_amount'] : 0,
            'period' => $periodLabel,
        ];
    }
} catch (PDOException $e) {
    $programmes = [];
}

// ==================== FETCH USER'S INVESTED PROGRAMME ====================
$investedProgramme = null;
$investedProgrammeData = null;
$investedReq = null;
$investedTimezone = '';
$cancelEligible = false;

$hasActiveContract       = false;
$hasPaymentRequirement   = false;

if ($userHasProgramme) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id, p.userid, p.program_name, p.visibility, p.advertisement, p.server_advertisement_approval, p.broker, p.timezone,
                   h.fullname, h.first_name, h.last_name, h.username,
                   h.email AS developer_email
            FROM programme p
            INNER JOIN harvhub h ON h.id = p.userid
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$activeProgrammeId]);
        $investedProgramme = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    if ($investedProgramme) {
        $developerName = resolveDeveloperDisplayName($investedProgramme);
        $programmeBroker = resolveProgrammeBroker($investedProgramme, $pdo);
        $investedTimezone = trim((string)($investedProgramme['timezone'] ?? ''));

        try {
            $stmt = $pdo->prepare("
                SELECT contract_duration, developer_percentage, investor_percentage,
                       minimum_investment_amount, maximum_investment_amount
                FROM programme_investors
                WHERE programme_id = ? AND developerid = ? AND investorid = 0
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$activeProgrammeId, (int)$investedProgramme['userid']]);
            $investedReq = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {}



        $srvRow = $pdo->query("SELECT contract_duration, min_broker_balance, server_share_percent, user_share_percent FROM server_account WHERE id = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $srvContractDuration = (int)($srvRow['contract_duration'] ?? 30);
        $srvMinBrokerBalance = (float)($srvRow['min_broker_balance'] ?? 0);
        $srvServerShare      = (int)($srvRow['server_share_percent'] ?? 30);
        $srvUserShare        = (int)($srvRow['user_share_percent'] ?? 70);

        $investedProgrammeData = [
            'id'                        => (int)$investedProgramme['id'],
            'program_name'              => $investedProgramme['program_name'],
            'broker'                    => $programmeBroker,
            'broker_pretty'             => prettyBroker($programmeBroker),
            'timezone'                  => $investedTimezone,
            'timezone_pretty'           => prettyTimezone($investedTimezone),
            'developer_name'            => $developerName,
            'developer_email'           => $investedProgramme['developer_email'] ?: '',
            'visibility'                => (int)$investedProgramme['visibility'],
            'advertisement'             => (int)$investedProgramme['advertisement'],
            'contract_duration'         => $investedReq && (int)$investedReq['contract_duration'] > 0 ? (int)$investedReq['contract_duration'] : $srvContractDuration,
            'developer_percentage'      => $investedReq && (int)$investedReq['developer_percentage'] > 0 ? (int)$investedReq['developer_percentage'] : $srvServerShare,
            'investor_percentage'       => $investedReq && (int)$investedReq['investor_percentage'] > 0 ? (int)$investedReq['investor_percentage'] : $srvUserShare,
            'minimum_investment_amount' => $investedReq && (float)$investedReq['minimum_investment_amount'] > 0 ? (float)$investedReq['minimum_investment_amount'] : $srvMinBrokerBalance,
            'maximum_investment_amount' => $investedReq ? (float)$investedReq['maximum_investment_amount'] : 0,
            'provision'                 => progInvestorFetchProvision(
                $pdo,
                (int)$investedProgramme['userid'],
                (int)$investedProgramme['id'],
                $investedTimezone,
                (int)($investedProgramme['advertisement'] ?? 0) === 1
                    && (int)($investedProgramme['server_advertisement_approval'] ?? 0) === 1
            ),
        ];
    }

    $contract_duration_cfg = 30;
    try {
        $cfgStmt = $pdo->query("SELECT contract_duration FROM server_account LIMIT 1");
        $cfg = $cfgStmt->fetch(PDO::FETCH_ASSOC);
        if ($cfg && !empty($cfg['contract_duration'])) {
            $contract_duration_cfg = (int)$cfg['contract_duration'];
        }
    } catch (Exception $e) {}

    $exec_start = $user['execution_start_date'] ?? null;
    if ($exec_start && $exec_start !== '0000-00-00' && $exec_start !== null) {
        try {
            $start = new DateTime($exec_start);
            $end   = clone $start;
            $end->modify("+{$contract_duration_cfg} days");
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $endClone = clone $end;
            $endClone->setTime(0, 0, 0);
            $daysLeft = (int)$today->diff($endClone)->format('%r%a');
            if ($daysLeft > 0) $hasActiveContract = true;
        } catch (Exception $e) {}
    }

    $payment_issue_statuses = [
        'unpaid-payment', 'unpaid',
        'contract-cancelled-unpaid', 'contract-cancelled-unpaid-payment',
        'contract-cancelled-payment-required',
        'payment-failed', 'failed-payment',
        'contract-cancelled-failed-payment', 'contract-cancelled-payment-failed',
        'payment-made', 'contract-cancelled-payment-made',
        'pending_payment'
    ];

    $loyalties_status = $user['loyalties'] ?? null;
    if ($loyalties_status && in_array($loyalties_status, $payment_issue_statuses, true)) {
        $hasPaymentRequirement = true;
    }

    try {
        $revStmt = $pdo->prepare("
            SELECT loyalties FROM revenue_history
            WHERE user_email = ?
              AND (sub_account_id = ? OR sub_account_id IS NULL)
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $revStmt->execute([$email, $activeSubAccountId]);
        $revRecord = $revStmt->fetch(PDO::FETCH_ASSOC);
        if ($revRecord && !empty($revRecord['loyalties']) && in_array($revRecord['loyalties'], $payment_issue_statuses, true)) {
            $hasPaymentRequirement = true;
        }
    } catch (Exception $e) {}

    if ($hasActiveContract || $hasPaymentRequirement) {
        $cancelEligible = false;
    } else {
        $cancelEligible = true;
    }
}

$defaultTab = ($linkedProgrammeId > 0) ? 'programmes' : ($userHasProgramme ? 'invested' : 'programmes');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programmes - HarvHub</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232ecc71'/><text x='50' y='68' font-size='55' text-anchor='middle' fill='white'>H</text></svg>">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unicons.iconscout.com/release/v4.0.8/css/line.css">
<?php include 'style.php'; ?>
<?php include 'programmes_style.php'; ?>

<style>
    .prog-item-broker {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.2px;
        color: var(--accent, #2ecc8f);
        margin-top: 2px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-transform: uppercase;
    }
    .prog-item-broker i { font-size: 0.72rem; opacity: 0.85; }
    .prog-item-broker.is-empty { color: var(--text-muted, #8a9aa8); text-transform: none; font-weight: 500; }

    .prog-item-timezone {
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        color: var(--text-muted, #6b7c8a);
        margin-top: 2px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .prog-item-timezone i { font-size: 0.72rem; opacity: 0.85; color: var(--accent, #2ecc8f); }
    .prog-item-timezone.is-empty { color: var(--text-muted, #8a9aa8); font-weight: 500; }

    .prog-detail-broker-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(46,204,143,0.12);
        color: var(--accent, #2ecc8f);
        font-weight: 700;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .prog-detail-broker-badge.is-mismatch {
        background: rgba(255,152,0,0.14);
        color: #ff9800;
    }
    .prog-detail-broker-badge.is-empty {
        background: rgba(127,127,127,0.12);
        color: var(--text-muted, #8a9aa8);
        text-transform: none;
        font-weight: 500;
    }

    .prog-broker-warning {
        margin: 0 0 10px 0;
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(255,152,0,0.12);
        color: #ff9800;
        font-size: 0.82rem;
        line-height: 1.45;
        text-align: left;
    }

    .prog-requests-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .prog-requests-loading {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted, #888);
        font-size: 0.9rem;
    }
    .prog-request-item {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border-color, #e0e0e0);
        border-radius: 8px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }
    .prog-request-item:hover {
        border-color: var(--accent, #2e8b57);
        transform: translateY(-1px);
    }
    body.dark-mode .prog-request-item {
        background: var(--bg-card, #1e1e2a);
        border-color: var(--border-color, #333);
    }
    .prog-request-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .prog-request-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-muted, #888);
        font-weight: 600;
    }
    .prog-request-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text, #222);
        text-align: right;
        word-break: break-word;
    }
    body.dark-mode .prog-request-value { color: var(--text, #eee); }

    .prog-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }
    .prog-status-badge.pending { background: rgba(243,156,18,0.15); color: #f39c12; }

    .prog-btn-request-delete {
        padding: 8px 16px;
        background: rgba(231,76,60,0.15);
        color: #e74c3c;
        border: 1px solid #e74c3c;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        font-family: inherit;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .prog-btn-request-delete:hover {
        background: #e74c3c;
        color: #fff;
    }
    .prog-btn-request-delete:active {
        transform: scale(0.97);
    }

    @media (max-width: 480px) {
        .prog-request-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
        .prog-request-value { text-align: left; }
        .prog-btn-request-delete {
            width: 100%;
            text-align: center;
        }
    }
</style>
</head>
<body class="<?= htmlspecialchars($darkModeClass) ?> prog-page-body">

<script>
(function () {
    try {
        document.body.classList.remove('prog-detail-view-open');
        document.body.style.position = '';
        document.body.style.top      = '';
        document.body.style.left     = '';
        document.body.style.right    = '';
        document.body.style.width    = '';
        document.body.style.overflow = '';
        delete document.body.dataset.progModalLocked;
        delete document.body.dataset.progModalY;
        ['progDetailModal','progAlertModal','progConfirmModal','progCancelModal','progAnalyticsModal'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.classList.remove('active');
        });
        if (window.parent && window.parent !== window) {
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: [],
                    remove: ['prog-detail-view-open']
                }, '*');
            } catch (e) {}
        }
    } catch (e) {}
})();
</script>

<div class="prog-page-wrapper" id="progPageWrapper">

    <div class="prog-sticky-top" id="progStickyTop">

        <div class="prog-list-header" id="progListHeader">
            <button type="button"
                    class="prog-list-back"
                    onclick="harvhubGoToTab('mydashboard')"
                    aria-label="Back to Dashboard">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h1 class="prog-list-title" id="progListTitle">Programmes</h1>
        </div>

        <div class="prog-tabs" id="progTabs">
            <button class="prog-tab <?= $defaultTab === 'programmes' ? 'active' : '' ?>"
                    data-tab="programmes"
                    onclick="switchProgTab('programmes')">
                Programmes
            </button>
            <button class="prog-tab <?= $defaultTab === 'invested' ? 'active' : '' ?>"
                    data-tab="invested"
                    onclick="switchProgTab('invested')">
                Invested Programme
            </button>

            <?php if (!$userHasProgramme): ?>
                <button class="prog-tab"
                        data-tab="sent"
                        onclick="switchProgTab('sent')">
                    Sent Requests
                </button>
            <?php endif; ?>

        </div>

        <div class="prog-search-wrap" id="progSearchWrap" style="<?= $defaultTab === 'programmes' && !empty($programmes) ? '' : 'display:none;' ?>">
            <input type="text"
                   id="progSearchInput"
                   class="prog-search-input"
                   placeholder="Search programme name or programme URL"
                   autocomplete="off"
                   oninput="onProgSearch(this.value)"
                   onfocus="onProgSearch(this.value)">
        </div>
    </div>

    <div class="prog-scroll-area" id="progScrollArea">

        <div class="prog-tab-content <?= $defaultTab === 'programmes' ? 'active' : '' ?>" id="progTabProgrammes">

            <?php if (empty($programmes)): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>No advertised and approved programmes are available at the moment</p>
                </div>
            <?php else: ?>
                <div class="prog-list" id="progList">
                    <?php foreach ($programmes as $p): ?>
                        <div class="prog-item"
                             data-programme-id="<?= (int)$p['id'] ?>"
                             data-search-text="<?= htmlspecialchars(strtolower(
                                 ($p['program_name'] ?? '') . ' ' .
                                 ($p['broker'] ?? '') . ' ' .
                                 ($p['timezone'] ?? '') . ' ' .
                                 ($p['developer_username'] ?? '') . ' ' .
                                 ($p['developer_first_name'] ?? '') . ' ' .
                                 ($p['developer_last_name'] ?? '') . ' ' .
                                 ($p['developer_fullname'] ?? '') . ' ' .
                                 ($p['developer_email'] ?? '') . ' ' .
                                 ($p['programme_link'] ?? '')
                             )) ?>">
                            <div class="prog-item-main">
                                <div class="prog-item-header">
                                    <div class="prog-item-name"><?= htmlspecialchars($p['program_name']) ?></div>
                                    <div class="prog-item-actions">
                                        <button type="button"
                                                class="prog-btn-view"
                                                onclick="viewProgramme(<?= (int)$p['id'] ?>, '<?= htmlspecialchars(addslashes($p['program_name'])) ?>')">
                                            View
                                        </button>
                                    </div>
                                </div>

                                <?php if (!empty($p['broker_pretty'])): ?>
                                    <div class="prog-item-broker">
                                        <i class="fa-solid fa-link"></i>
                                        <?= htmlspecialchars($p['broker_pretty']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="prog-item-broker is-empty">
                                        <i class="fa-solid fa-link-slash"></i>
                                        Broker not set
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($p['timezone_pretty'])): ?>
                                    <div class="prog-item-timezone">
                                        <i class="fa-solid fa-globe"></i>
                                        <?= htmlspecialchars($p['timezone_pretty']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="prog-item-meta">
                                    <span class="prog-item-stat">
                                        <span class="prog-item-stat-label">Developed by</span>
                                        <span class="prog-item-stat-value"><?= htmlspecialchars($p['developer_name']) ?></span>
                                    </span>
                                </div>
                                <div class="prog-item-details">
                                    <span class="prog-detail-item">Share: <?= (int)$p['developer_percentage'] ?>%</span>
                                    <span class="prog-detail-item">Min: $<?= number_format($p['minimum_investment_amount'], 2) ?></span>
                                    <?php if ($p['maximum_investment_amount'] > 0): ?>
                                        <span class="prog-detail-item">Max: $<?= number_format($p['maximum_investment_amount'], 2) ?></span>
                                    <?php endif; ?>
                                    <span class="prog-detail-item">Duration: <?= (int)$p['contract_duration'] ?> days</span>
                                    <span class="prog-detail-item"><?= htmlspecialchars($p['period']) ?> Trades Provision</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="progSearchEmpty" class="prog-empty-state" style="display:none;">
                    <span class="prog-empty-icon">—</span>
                    <p>No programmes match your search</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="prog-tab-content <?= $defaultTab === 'invested' ? 'active' : '' ?>" id="progTabInvested">
            <?php if (!$investedProgrammeData): ?>
                <div class="prog-empty-state">
                    <span class="prog-empty-icon">—</span>
                    <p>No invested programme for this account</p>
                    <p style="margin-top:8px;font-size:0.85em;opacity:0.75;">Join a programme from the Programmes tab to see it here.</p>
                </div>
            <?php else: ?>
                <?php $ip = $investedProgrammeData; ?>
                <div class="prog-invested-card">

                    <div class="prog-detail-header">
                        <div class="prog-detail-name"><?= htmlspecialchars($ip['program_name']) ?></div>

                        <?php if (!empty($ip['broker_pretty'])): ?>
                            <div class="prog-detail-broker-badge">
                                <i class="fa-solid fa-link"></i>
                                <?= htmlspecialchars($ip['broker_pretty']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="prog-detail-dev">
                            Developed by <strong><?= htmlspecialchars($ip['developer_name']) ?></strong>
                        </div>
                    </div>

                    <div class="prog-investor-provision-mount"
                         data-programme-id="<?= (int)$ip['id'] ?>"
                         data-programme-name="<?= htmlspecialchars($ip['program_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                         data-provision="<?= htmlspecialchars(json_encode($ip['provision'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></div>

                    <div class="prog-detail-section-title prog-item-stat-value">Investment Requirements</div>

                    <div class="prog-detail-grid">
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Contract Duration</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['contract_duration']) ?> days</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Programme Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['developer_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Investor Share</span>
                            <span class="prog-detail-value"><?= htmlspecialchars((string)$ip['investor_percentage']) ?>%</span>
                        </div>
                        <div class="prog-detail-row">
                            <span class="prog-detail-label">Minimum Investment</span>
                            <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['minimum_investment_amount'], 2, '.', '')) ?></span>
                        </div>
                        <?php if ((float)$ip['maximum_investment_amount'] > 0): ?>
                            <div class="prog-detail-row">
                                <span class="prog-detail-label">Maximum Investment</span>
                                <span class="prog-detail-value">$<?= htmlspecialchars(number_format((float)$ip['maximum_investment_amount'], 2, '.', '')) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($cancelEligible): ?>
                        <button type="button"
                                class="prog-btn-cancel"
                                onclick="cancelProgrammeConfirm()">
                            Cancel Programme
                        </button>
                    <?php endif; ?>

                </div>
            <?php endif; ?>
        </div>

        <?php if (!$userHasProgramme): ?>
        <div class="prog-tab-content <?= $defaultTab === 'sent' ? 'active' : '' ?>" id="progTabSent">
            <div id="sentRequestsContainer" class="prog-requests-list">
                <div class="prog-requests-loading">Loading sent requests...</div>
            </div>
        </div>
        <?php endif; ?>

    </div>

</div>

<div id="progDetailModal" class="prog-modal">
    <div class="prog-modal-content prog-modal-wide">
        <div class="prog-view-header">
            <button type="button" class="prog-view-back" onclick="closeProgDetailModal()" aria-label="Back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h2 class="prog-view-title" id="progDetailTitle">Explore Programme</h2>
        </div>
        <div class="prog-modal-body prog-view-body" id="progDetailBody">
            <div class="prog-modal-loading">Loading...</div>
        </div>
        <div class="prog-view-actions" id="progDetailActions">
            <button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>
        </div>
    </div>
</div>

<div id="progAnalyticsModal" class="prog-modal prog-analytics-modal" aria-hidden="true">
    <div class="prog-modal-content prog-modal-wide prog-analytics-modal-content">
        <div class="prog-view-header prog-analytics-header">
            <button type="button" class="prog-view-back" onclick="handleProgrammeAnalyticsHeaderBack()" aria-label="Back to programme">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <h2 class="prog-view-title" id="progAnalyticsTitle">Programme Analytics</h2>
        </div>
        <div class="prog-modal-body prog-analytics-body" id="progAnalyticsBody">
            <div id="paAnalyticsLoadMessage" class="pa-panel pa-empty">Select a programme to view its recorded analytics.</div>
            <div class="pa-page">
                <div id="paDashboard">
                    <div class="pa-source-tabs" id="paSourceTabs" role="tablist" aria-label="Trade source">
                        <button type="button" class="pa-source-tab active" data-trade-source="live" role="tab" aria-selected="true">Live</button>
                        <button type="button" class="pa-source-tab" data-trade-source="history" role="tab" aria-selected="false">History</button>
                    </div>
                    <div id="paDashboardContent"></div>
                </div>
                <div id="paIsolated" class="pa-hidden"></div>
            </div>
        </div>
    </div>
</div>

<div id="progAlertModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progAlertTitle">Notice</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progAlertMessage" class="prog-alert-text">Message</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progAlertOkBtn" onclick="closeProgAlert()">OK</button>
        </div>
    </div>
</div>

<div id="progConfirmModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progConfirmTitle">Confirm Request</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progConfirmMessage" class="prog-alert-text">Are you sure?</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progConfirmBtn" onclick="progConfirmAction()">Yes, Send Request</button>
            <button type="button" class="prog-modal-close" onclick="closeProgConfirm()">Cancel</button>
        </div>
    </div>
</div>

<div id="progCancelModal" class="prog-modal">
    <div class="prog-modal-content">
        <div class="prog-modal-header">
            <h2 class="prog-modal-title" id="progCancelTitle">Cancel Programme</h2>
        </div>
        <div class="prog-modal-body">
            <p id="progCancelMessage" class="prog-alert-text">Are you sure you want to cancel this account's participation in this programme?</p>
        </div>
        <div class="prog-modal-actions">
            <button type="button" class="prog-btn-confirm" id="progCancelBtn" onclick="progCancelAction()">Yes, Cancel</button>
            <button type="button" class="prog-modal-close" onclick="closeProgCancel()">Keep Programme</button>
        </div>
    </div>
</div>

<script>
    var _viewerId           = <?= (int)$userId ?>;
    var _activeSubAccountId = <?= (int)$activeSubAccountId ?>;
    var _userHasProgramme   = <?= $userHasProgramme ? 'true' : 'false' ?>;
    var _activeProgrammeId  = <?= (int)$activeProgrammeId ?>;
    var _viewerBroker       = <?= json_encode($viewerBroker) ?>;

    if ('<?= $defaultTab ?>' === 'invested') {
        document.body.classList.add('prog-tab-invested');
    }

    function escapeHtml(t) {
        var d = document.createElement('div');
        d.textContent = t == null ? '' : String(t);
        return d.innerHTML;
    }
    function escapeAttr(t) {
        return escapeHtml(t).replace(/"/g, '&quot;');
    }

    function lockBodyScroll() {
        if (document.body.dataset.progModalLocked === '1') return;
        var y = window.scrollY || 0;
        document.body.dataset.progModalLocked = '1';
        document.body.dataset.progModalY = y;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.style.top = '-' + y + 'px';
    }
    function unlockBodyScroll() {
        if (document.body.dataset.progModalLocked !== '1') return;
        var y = parseInt(document.body.dataset.progModalY || '0', 10) || 0;
        document.body.dataset.progModalLocked = '0';
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        document.body.style.top = '';
        window.scrollTo(0, y);
    }

    function setShellHeaderHidden(hidden) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: hidden ? ['prog-detail-view-open'] : [],
                    remove: hidden ? [] : ['prog-detail-view-open']
                }, '*');
            }
        } catch (e) {}

        if (hidden) {
            document.body.classList.add('prog-detail-view-open');
        } else {
            document.body.classList.remove('prog-detail-view-open');
        }
    }

    function switchProgTab(tab) {
        document.querySelectorAll('.prog-tab').forEach(function (el) {
            el.classList.toggle('active', el.dataset.tab === tab);
        });
        document.querySelectorAll('.prog-tab-content').forEach(function (el) {
            el.classList.remove('active');
        });

        document.body.classList.toggle('prog-tab-invested', tab === 'invested');

        if (tab === 'programmes') {
            var t = document.getElementById('progTabProgrammes');
            if (t) t.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Programmes';
            var wrap = document.getElementById('progSearchWrap');
            if (wrap) wrap.style.display = '';

            var listEl = document.getElementById('progList');
            if (listEl) listEl.style.display = '';
            var emptyEl = document.getElementById('progSearchEmpty');
            if (emptyEl) emptyEl.style.display = 'none';

        } else if (tab === 'invested') {
            var t2 = document.getElementById('progTabInvested');
            if (t2) t2.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Invested Programme';
            var wrap2 = document.getElementById('progSearchWrap');
            if (wrap2) wrap2.style.display = 'none';

        } else if (tab === 'sent') {
            var t3 = document.getElementById('progTabSent');
            if (t3) t3.classList.add('active');
            document.getElementById('progListTitle').textContent = 'Sent Investment Request';
            var wrap3 = document.getElementById('progSearchWrap');
            if (wrap3) wrap3.style.display = 'none';
            loadSentInvestmentRequests();
        }

        var scrollArea = document.getElementById('progScrollArea');
        if (scrollArea) scrollArea.scrollTop = 0;
    }
    window.switchProgTab = switchProgTab;

    function onProgSearch(query) {
        var rawQuery = (query || '').trim();
        // If a user pastes a programme invite URL, resolve it through its ID.
        // A direct programme link is treated as the requested programme and can display it.
        var linkMatch = rawQuery.match(/[?&](?:programme_id|programme)=(\d+)/i);
        if (linkMatch && parseInt(linkMatch[1], 10) > 0) {
            var targetUrl = window.location.pathname + '?programme_id=' + encodeURIComponent(linkMatch[1]);
            if (window.location.search !== '?programme_id=' + encodeURIComponent(linkMatch[1])) {
                window.location.href = targetUrl;
                return;
            }
        }
        var q = rawQuery.toLowerCase();
        var listEl = document.getElementById('progList');
        if (!listEl) return;

        var items = listEl.querySelectorAll('.prog-item');
        var anyVisible = false;

        items.forEach(function (el) {
            var searchText = el.getAttribute('data-search-text') || '';
            var matches = (q === '') || (searchText.indexOf(q) !== -1);
            if (matches) { el.style.display = ''; anyVisible = true; }
            else { el.style.display = 'none'; }
        });

        var emptyEl = document.getElementById('progSearchEmpty');
        if (emptyEl) emptyEl.style.display = (anyVisible || q === '') ? 'none' : '';
        listEl.style.display = anyVisible ? '' : 'none';
    }

    function showProgAlert(msg, title, reloadAfter) {
        document.getElementById('progAlertTitle').textContent = title || 'Notice';
        document.getElementById('progAlertMessage').textContent = msg == null ? '' : String(msg);

        var okBtn = document.getElementById('progAlertOkBtn');
        if (okBtn) {
            okBtn.onclick = function () {
                closeProgAlert();
                if (reloadAfter === 'sent') loadSentInvestmentRequests();
                else if (reloadAfter === 'reload') window.location.reload();
            };
        }

        document.getElementById('progAlertModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgAlert() {
        document.getElementById('progAlertModal').classList.remove('active');
        unlockBodyScroll();
    }

    var _pendingRequestPid = null;
    var _pendingRequestName = '';

    function openProgConfirm(pid, name) {
        _pendingRequestPid = pid;
        _pendingRequestName = name || '';
        document.getElementById('progConfirmMessage').textContent =
            'Send an investment request for ' + (name || 'this programme') + '?\n\nThe developer will need to accept before you become an investor.';
        document.getElementById('progConfirmModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgConfirm() {
        document.getElementById('progConfirmModal').classList.remove('active');
        unlockBodyScroll();
        _pendingRequestPid = null;
        _pendingRequestName = '';
    }
    function progConfirmAction() {
        if (!_pendingRequestPid) { closeProgConfirm(); return; }
        var pid = _pendingRequestPid;
        var name = _pendingRequestName;
        closeProgConfirm();
        doSendRequest(pid, name);
    }

    function cancelProgrammeConfirm() {
        document.getElementById('progCancelModal').classList.add('active');
        lockBodyScroll();
    }
    function closeProgCancel() {
        document.getElementById('progCancelModal').classList.remove('active');
        unlockBodyScroll();
    }

    function progCancelAction() {
        var btn = document.getElementById('progCancelBtn');
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Cancelling...';

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'cancel_programme=1'
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                btn.disabled = false;
                btn.textContent = originalText;
                closeProgCancel();
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();

            if (data.success) {
                showProgAlert(data.message || 'Programme cancelled successfully.', 'Success', 'reload');
            } else {
                showProgAlert(data.message || 'Unable to cancel programme.', 'Error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.textContent = originalText;
            closeProgCancel();
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    // ==================================================================
    // Investor provision display.
    // Uses only saved signals_provider_interest checkpoint data. No
    // provision evaluator, status transition, reset or write is performed.
    // ==================================================================
    var PROG_PROVISION_WINDOWS = {};
    var PROG_PROVISION_CACHE = {};
    var PROG_ANALYTICS_RETURN_TO_DETAIL = false;

    function progProvisionNumber(value, fallback) {
        if (value === null || value === undefined || value === '') return fallback;
        var number = Number(value);
        return Number.isFinite(number) ? number : fallback;
    }

    function progProvisionDate(value) {
        var raw = String(value || '').slice(0, 10);
        var parts = raw.split('-');
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        if (parts.length !== 3) return raw || '—';
        var monthIndex = parseInt(parts[1], 10) - 1;
        if (monthIndex < 0 || monthIndex > 11) return raw;
        return String(parts[2]).padStart(2, '0') + ' ' + months[monthIndex] + ' ' + parts[0];
    }

    function progProvisionRange(start, end, period) {
        var first = progProvisionDate(start);
        var last = progProvisionDate(end || start);
        return String(period || '').toLowerCase() === 'daily' || first === last
            ? first
            : first + ' – ' + last;
    }

    function progProvisionProgressBlock(label, current, target, passed, isLoss, locked, lossFailed) {
        current = Math.max(0, progProvisionNumber(current, 0));
        target = Math.max(0, progProvisionNumber(target, 0));
        var percent = target > 0 ? Math.min(100, (current / target) * 100) : 0;
        var right = locked
            ? '<i class="fa-solid fa-lock spr-progress-lock" aria-label="Locked checkpoint" title="Locked checkpoint"></i>'
            : '<span class="spr-progress-count">' + current + ' / ' + target + '</span>';
        var mark = locked
            ? '<i class="fa-solid fa-lock spr-progress-lock" aria-hidden="true"></i>'
            : (passed
                ? '<span class="spr-progress-mark" title="Requirement met" aria-label="Requirement met"><i class="fa-solid fa-check"></i></span>'
                : (isLoss && lossFailed
                    ? '<span class="spr-progress-mark is-failed" title="Loss limit reached" aria-label="Loss limit reached"><i class="fa-solid fa-xmark"></i></span>'
                    : '<span class="spr-progress-mark" style="visibility:hidden" aria-hidden="true"></span>'));
        var trackClass = isLoss ? 'spr-progress-track spr-progress-loss-track' : 'spr-progress-track';
        var fillClass = isLoss ? 'spr-progress-loss-fill' : ('spr-progress-fill' + (passed ? ' is-complete' : ''));
        return '<div class="spr-progress-item">'
            + '<div class="spr-progress-heading"><span class="spr-progress-label">' + escapeHtml(label) + '</span>' + right + '</div>'
            + '<div class="' + trackClass + '"><div class="' + fillClass + '" style="width:' + (locked ? 0 : percent).toFixed(2) + '%"></div></div>'
            + mark
            + '</div>';
    }

    function progProvisionWindowProgressHtml(windowRow, provision) {
        var tradesTarget = Math.max(0, progProvisionNumber(provision.trades_count, 0));
        var winsTarget = Math.max(0, progProvisionNumber(provision.expected_win, 0));
        var lossesLimit = Math.max(0, progProvisionNumber(provision.consecutive_loss, 0));
        var currentTrades = Math.max(0, progProvisionNumber(windowRow.trades, 0));
        var currentWins = Math.max(0, progProvisionNumber(windowRow.wins, 0));
        var currentLosses = Math.max(0, progProvisionNumber(windowRow.consecutive_losses, 0));
        var locked = !!windowRow.locked || String(windowRow.status || '').toLowerCase() === 'locked';
        var lossFailed = lossesLimit > 0 && currentLosses >= lossesLimit;

        return '<div class="spr-progress-list">'
            + progProvisionProgressBlock('Minimum Trades Count', currentTrades, tradesTarget, !!windowRow.trades_passed, false, locked, false)
            + progProvisionProgressBlock('Minimum Expected Win Count', currentWins, winsTarget, !!windowRow.wins_passed, false, locked, false)
            + progProvisionProgressBlock('Maximum Expected Consecutive Loss', currentLosses, lossesLimit, !!windowRow.loss_gate_passed, true, locked, lossFailed)
            + '</div>';
    }

    function renderInvestorProvision(provision, programmeId, scopeKey, programmeName) {
        provision = provision && typeof provision === 'object' ? provision : {};
        var scope = String(scopeKey || ('programme-' + programmeId));
        var available = !!provision.available;
        var period = String(provision.period || 'daily').toLowerCase();
        if (['daily', 'weekly', 'monthly'].indexOf(period) === -1) period = 'daily';
        var periodLabel = period.charAt(0).toUpperCase() + period.slice(1);
        var windows = Array.isArray(provision.windows) ? provision.windows : [];

        PROG_PROVISION_CACHE[scope] = {
            provision: provision,
            programmeId: Number(programmeId) || 0,
            programmeName: String(programmeName || 'Programme')
        };

        var windowIndex = Object.prototype.hasOwnProperty.call(PROG_PROVISION_WINDOWS, scope)
            ? Number(PROG_PROVISION_WINDOWS[scope])
            : Number(provision.current_index);
        if (!Number.isFinite(windowIndex)) windowIndex = Math.max(0, windows.length - 1);
        if (windows.length) windowIndex = Math.max(0, Math.min(windows.length - 1, windowIndex));
        else windowIndex = 0;
        PROG_PROVISION_WINDOWS[scope] = windowIndex;

        var selectedWindow = windows[windowIndex] || null;
        var scopeAttr = escapeAttr(scope);
        var nav = '';
        if (selectedWindow) {
            var selectedLabel = String(selectedWindow.label || (periodLabel + ' ' + (windowIndex + 1)));
            var selectedRange = progProvisionRange(selectedWindow.start_date, selectedWindow.end_date, period);
            nav = '<div class="spr-period-nav">'
                + '<button type="button" class="spr-period-nav-btn" data-provision-window-step="-1" data-provision-scope="' + scopeAttr + '" aria-label="Previous ' + escapeAttr(period) + ' window" ' + (windowIndex <= 0 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>'
                + '<div class="spr-period-nav-title">' + escapeHtml(selectedLabel) + '<span class="spr-period-nav-range">' + escapeHtml(selectedRange) + '</span></div>'
                + '<button type="button" class="spr-period-nav-btn" data-provision-window-step="1" data-provision-scope="' + scopeAttr + '" aria-label="Next ' + escapeAttr(period) + ' window" ' + (windowIndex >= windows.length - 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>'
                + '</div>';
        }

        var progressHtml = selectedWindow
            ? progProvisionWindowProgressHtml(selectedWindow, provision)
            : '<div class="spr-provision-no-checkpoint">' + (available ? 'No provision progress is recorded.' : 'No provision data is available.') + '</div>';

        var card = '<div class="spr-item prog-investor-provision-card">'
            + '<div class="spr-item-main">'
            + '<div class="spr-item-header"><div class="spr-item-title">' + escapeHtml(periodLabel) + ' Provision</div></div>'
            + nav
            + progressHtml
            + '</div></div>';

        var analyticsButton = '<button type="button" class="prog-see-analytics" data-see-analytics="1" data-programme-id="' + (Number(programmeId) || 0) + '" data-programme-name="' + escapeAttr(programmeName || 'Programme') + '"><i class="fa-solid fa-chart-line"></i> See analytics</button>';
        return '<div class="prog-investor-provision" data-provision-scope="' + scopeAttr + '">' + card + analyticsButton + '</div>';
    }

    window.progMoveProvisionWindow = function (scopeKey, direction) {
        var scope = String(scopeKey || '');
        var cached = PROG_PROVISION_CACHE[scope];
        if (!cached || !cached.provision || !Array.isArray(cached.provision.windows)) return;

        var windows = cached.provision.windows;
        if (!windows.length) return;
        var current = Object.prototype.hasOwnProperty.call(PROG_PROVISION_WINDOWS, scope)
            ? Number(PROG_PROVISION_WINDOWS[scope])
            : Number(cached.provision.current_index || 0);
        if (!Number.isFinite(current)) current = 0;
        var next = Math.max(0, Math.min(windows.length - 1, current + (Number(direction) || 0)));
        if (next === current) return;
        PROG_PROVISION_WINDOWS[scope] = next;

        // Delegated clicks survive this replacement, so both arrows keep working
        // after every window navigation and on both the invested and detail views.
        var existing = null;
        document.querySelectorAll('.prog-investor-provision').forEach(function (node) {
            if (node.getAttribute('data-provision-scope') === scope) existing = node;
        });
        if (existing) {
            existing.outerHTML = renderInvestorProvision(
                cached.provision,
                cached.programmeId,
                scope,
                cached.programmeName
            );
        }
    };

    function initializeProvisionPanels() {
        document.querySelectorAll('.prog-investor-provision-mount').forEach(function (mount) {
            if (mount.dataset.rendered === '1') return;
            var provision = {};
            try { provision = JSON.parse(mount.getAttribute('data-provision') || '{}'); }
            catch (e) { provision = {}; }
            var pid = Number(mount.getAttribute('data-programme-id') || 0);
            var programmeName = mount.getAttribute('data-programme-name') || 'Programme';
            var scope = 'invested-' + pid;
            mount.dataset.renderedScope = scope;
            mount.innerHTML = renderInvestorProvision(provision, pid, scope, programmeName);
            mount.dataset.rendered = '1';
        });
    }

    // ==================================================================
    // Investor analytics modal: the same Programme Analytics dashboard
    // templates, supplied with programme-scoped read-only AJAX data.
    // ==================================================================
    function openProgrammeAnalytics(programmeId, programmeName) {
        var modal = document.getElementById('progAnalyticsModal');
        var title = document.getElementById('progAnalyticsTitle');
        var message = document.getElementById('paAnalyticsLoadMessage');
        var detailModal = document.getElementById('progDetailModal');
        PROG_ANALYTICS_RETURN_TO_DETAIL = !!(detailModal && detailModal.classList.contains('active'));
        if (PROG_ANALYTICS_RETURN_TO_DETAIL) detailModal.classList.remove('active');
        title.textContent = (programmeName || 'Programme') + ' · Analytics';
        message.className = 'pa-panel pa-empty';
        message.textContent = 'Loading programme analytics...';
        message.style.display = '';
        document.getElementById('paDashboard').classList.add('pa-hidden');
        document.getElementById('paIsolated').classList.add('pa-hidden');
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        lockBodyScroll();
        setShellHeaderHidden(true);
        fetch('programmes.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
            credentials: 'same-origin',
            body: 'get_programme_analytics=1&programme_id=' + encodeURIComponent(programmeId)
        })
        .then(function(response) { return response.text(); })
        .then(function(text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) { throw new Error('Server returned an unexpected analytics response.'); }
            if (!data.success) throw new Error(data.message || 'Unable to load programme analytics.');
            title.textContent = (data.programme_name || programmeName || 'Programme') + ' · Analytics';
            if (data.error) {
                message.className = 'pa-panel pa-empty';
                message.textContent = data.error;
                message.style.display = '';
                return;
            }
            if (typeof window.setProgrammeAnalyticsData !== 'function') throw new Error('The analytics display could not be initialized.');
            message.style.display = 'none';
            document.getElementById('paDashboard').classList.remove('pa-hidden');
            window.setProgrammeAnalyticsData(data);
        })
        .catch(function(error) {
            message.className = 'pa-panel pa-empty';
            message.textContent = error && error.message ? error.message : 'Unable to load programme analytics.';
            message.style.display = '';
        });
    }
    window.openProgrammeAnalytics = openProgrammeAnalytics;

    function closeProgrammeAnalyticsModal() {
        var modal = document.getElementById('progAnalyticsModal');
        if (!modal) return;
        modal.classList.remove('active');
        modal.setAttribute('aria-hidden', 'true');
        var detailModal = document.getElementById('progDetailModal');
        if (PROG_ANALYTICS_RETURN_TO_DETAIL && detailModal) {
            detailModal.classList.add('active');
            PROG_ANALYTICS_RETURN_TO_DETAIL = false;
            setShellHeaderHidden(true);
        } else {
            unlockBodyScroll();
            setShellHeaderHidden(false);
        }
    }
    window.closeProgrammeAnalyticsModal = closeProgrammeAnalyticsModal;

    document.addEventListener('click', function(e) {
        var windowButton = e.target.closest('[data-provision-window-step]');
        if (windowButton && !windowButton.disabled) {
            window.progMoveProvisionWindow(
                windowButton.getAttribute('data-provision-scope') || '',
                windowButton.getAttribute('data-provision-window-step') || 0
            );
            return;
        }

        var analyticsButton = e.target.closest('[data-see-analytics]');
        if (analyticsButton) {
            openProgrammeAnalytics(
                analyticsButton.getAttribute('data-programme-id'),
                analyticsButton.getAttribute('data-programme-name') || 'Programme'
            );
        }
    });

    function viewProgramme(pid, programmeName) {
        var modal   = document.getElementById('progDetailModal');
        var body    = document.getElementById('progDetailBody');
        var actions = document.getElementById('progDetailActions');
        var title   = document.getElementById('progDetailTitle');

        title.textContent = programmeName || 'Explore Programme';

        body.innerHTML = '<div class="prog-modal-loading">Loading...</div>';
        actions.innerHTML = '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';

        modal.classList.add('active');
        lockBodyScroll();
        setShellHeaderHidden(true);

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'get_programme_details=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                body.innerHTML = '<div class="prog-modal-error">Server returned an unexpected response.</div>';
                return;
            }

            if (!data.success) {
                body.innerHTML = '<div class="prog-modal-error">' + escapeHtml(data.message || 'Unable to load programme details.') + '</div>';
                return;
            }

            var p = data.programme;

            title.textContent = p.program_name || programmeName || 'Explore Programme';

            var html = '';

            html += '<div class="prog-detail-header">';
            html +=   '<div class="prog-detail-dev">Developed by <strong>' + escapeHtml(p.developer_name) + '</strong></div>';

            if (p.broker_pretty) {
                var badgeCls = 'prog-detail-broker-badge';
                if (data.broker_matches) badgeCls += '';
                else if (data.viewer_has_broker) badgeCls += ' is-mismatch';
                html += '<div class="' + badgeCls + '"><i class="fa-solid fa-link"></i> ' + escapeHtml(p.broker_pretty) + '</div>';
            } else {
                html += '<div class="prog-detail-broker-badge is-empty"><i class="fa-solid fa-link-slash"></i> Broker not set</div>';
            }

            html += '</div>';

            html += renderInvestorProvision(p.provision || null, p.id, 'detail-' + p.id, p.program_name);

            html += '<div class="prog-detail-section-title prog-item-stat-value">Investment Requirements</div>';
            html += '<div class="prog-detail-grid">';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Contract Duration</span><span class="prog-detail-value">' + escapeHtml(String(p.contract_duration)) + ' days</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Programme Share</span><span class="prog-detail-value">' + escapeHtml(String(p.developer_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Investor Share</span><span class="prog-detail-value">' + escapeHtml(String(p.investor_percentage)) + '%</span></div>';
            html +=   '<div class="prog-detail-row"><span class="prog-detail-label">Minimum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.minimum_investment_amount).toFixed(2)) + '</span></div>';
            if (Number(p.maximum_investment_amount) > 0) {
                html += '<div class="prog-detail-row"><span class="prog-detail-label">Maximum Investment</span><span class="prog-detail-value">$' + escapeHtml(Number(p.maximum_investment_amount).toFixed(2)) + '</span></div>';
            }
            html += '</div>';

            body.innerHTML = html;

            var actionsHtml = '';

            if (data.already_invested) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already Invested</button>';
            } else if (data.has_any_investment) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Already in a Programme</button>';
            } else if (!p.broker) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Broker Not Configured</button>';
            } else if (!data.viewer_has_broker) {
                actionsHtml += '<div class="prog-broker-warning">'
                             + 'You need to connect a broker first. This programme uses <strong>' + escapeHtml(p.broker_pretty) + '</strong>. Please connect the same broker to continue.'
                             + '</div>';
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Connect Broker First</button>';
            } else if (!data.broker_matches) {
                actionsHtml += '<div class="prog-broker-warning">'
                             + 'Broker mismatch. This programme uses <strong>' + escapeHtml(p.broker_pretty) + '</strong>, '
                             + 'but your connected broker is <strong>' + escapeHtml(_viewerBroker ? (_viewerBroker.charAt(0).toUpperCase() + _viewerBroker.slice(1)) : 'Unknown') + '</strong>. '
                             + 'Please link the same broker as the developer to continue.'
                             + '</div>';
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Broker Mismatch</button>';
            } else if (data.already_requested) {
                actionsHtml += '<button type="button" class="prog-btn-invest disabled" disabled>Requested</button>';
            } else {
                actionsHtml += '<button type="button" class="prog-btn-invest" onclick="openProgConfirm(' + p.id + ', \'' + escapeHtml(p.program_name).replace(/'/g, "\\'") + '\')">Send Investment Request</button>';
            }

            actionsHtml += '<button type="button" class="prog-modal-close" onclick="closeProgDetailModal()">Close</button>';
            actions.innerHTML = actionsHtml;
        })
        .catch(function () {
            body.innerHTML = '<div class="prog-modal-error">Network error. Please try again.</div>';
        });
    }

    function formatVal(v, suffix) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return escapeHtml(String(v));
        var s;
        if (Number.isInteger(n)) s = String(n);
        else s = n.toFixed(2);
        return escapeHtml(s) + (suffix ? ' ' + suffix : '');
    }

    function formatRiskReward(v) {
        if (v === null || v === undefined || v === '') return '—';
        var n = Number(v);
        if (isNaN(n)) return escapeHtml(String(v));
        var s = Number.isInteger(n) ? String(n) : n.toFixed(2);
        return escapeHtml('1:' + s);
    }

    function closeProgDetailModal() {
        document.getElementById('progDetailModal').classList.remove('active');
        var analyticsModal = document.getElementById('progAnalyticsModal');
        if (!analyticsModal || !analyticsModal.classList.contains('active')) {
            unlockBodyScroll();
            setShellHeaderHidden(false);
        }
    }

    function doSendRequest(pid, name) {
        var btns = document.querySelectorAll('.prog-btn-invest');
        btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = true; } });

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'send_investment_request=1&programme_id=' + encodeURIComponent(pid)
        })
        .then(function (r) { return r.text(); })
        .then(function (text) {
            var data;
            try { data = JSON.parse(text); }
            catch (e) {
                btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
                showProgAlert('Server returned an unexpected response.', 'Error');
                return;
            }

            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });

            if (data.success) {
                closeProgDetailModal();
                showProgAlert(data.message || 'Investment request sent.', 'Success', 'reload');
            } else {
                showProgAlert(data.message || 'Unable to send request.', 'Error');
            }
        })
        .catch(function () {
            btns.forEach(function (b) { if (b && !b.classList.contains('disabled')) { b.disabled = false; } });
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }

    function loadSentInvestmentRequests() {
        var container = document.getElementById('sentRequestsContainer');
        if (!container) return;

        container.innerHTML = '<div class="prog-requests-loading">Loading sent requests...</div>';

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'get_sent_investment_requests=1'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.requests.length) {
                container.innerHTML = '<div class="prog-empty-state"><span class="prog-empty-icon">—</span><p>This account has not sent any investment requests yet</p></div>';
                return;
            }
            renderProgrammeRequestList(container, data.requests, 'sent');
        })
        .catch(function () {
            container.innerHTML = '<div class="prog-empty-state"><p>Failed to load sent requests</p></div>';
        });
    }
    window.loadSentInvestmentRequests = loadSentInvestmentRequests;

    function renderProgrammeRequestList(container, requests, mode) {
        var html = '<div class="prog-requests-list" style="display:flex;flex-direction:column;gap:12px;">';

        requests.forEach(function (req) {
            var name = req.developer_display_name || 'Anonymous';

            var label    = 'Developer';
            var status   = (req.request_status || 'pending').toLowerCase();
            var programme = req.program_name || ('Programme #' + req.programme_id);

            html += '<div class="prog-request-item">';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Programme</div><div class="prog-request-value">' + escapeHtml(programme) + '</div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">' + label + '</div><div class="prog-request-value">' + escapeHtml(name) + '</div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Status</div><div class="prog-request-value"><span class="prog-status-badge ' + status + '">' + escapeHtml(status) + '</span></div></div>';
            html += '  <div class="prog-request-row"><div class="prog-request-label">Action</div><div class="prog-request-value">';
            html += '<button class="prog-btn-request-delete" data-request-id="' + req.id + '" data-request-name="' + escapeAttr(name) + '" onclick="deleteInvestmentRequestClicked(this)">Delete Request</button>';
            html += '  </div></div>';
            html += '</div>';
        });

        html += '</div>';
        container.innerHTML = html;
    }

    function deleteInvestmentRequestClicked(btn) {
        var reqId = btn.getAttribute('data-request-id');
        if (!reqId) return;
        if (!confirm('Delete this sent investment request?')) return;
        btn.disabled = true;

        fetch('programmes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'delete_investment_request=1&request_id=' + encodeURIComponent(reqId)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                showProgAlert(data.message || 'Request deleted.', 'Success', 'sent');
            } else {
                btn.disabled = false;
                showProgAlert(data.message || 'Failed to delete request', 'Error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            showProgAlert('Network error. Please try again.', 'Error');
        });
    }
    window.deleteInvestmentRequestClicked = deleteInvestmentRequestClicked;

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var analyticsModal = document.getElementById('progAnalyticsModal');
            if (analyticsModal && analyticsModal.classList.contains('active')) { closeProgrammeAnalyticsModal(); return; }
            var dm = document.getElementById('progDetailModal');
            if (dm && dm.classList.contains('active')) { closeProgDetailModal(); return; }
            var cm = document.getElementById('progConfirmModal');
            if (cm && cm.classList.contains('active')) { closeProgConfirm(); return; }
            var xm = document.getElementById('progCancelModal');
            if (xm && xm.classList.contains('active')) { closeProgCancel(); return; }
            var am = document.getElementById('progAlertModal');
            if (am && am.classList.contains('active')) { closeProgAlert(); }
        }
    });


    // ==================== LIVE PROGRAMME UPDATES ====================
    // VPS uses an 8-second polling interval. Keep this page in sync without
    // reloading the whole document or disturbing the current tab/search.
    var _programmeRefreshTimer = null;
    var _programmeRefreshBusy = false;

    function programmeModalIsOpen() {
        return ['progDetailModal', 'progAlertModal', 'progConfirmModal', 'progCancelModal', 'progAnalyticsModal'].some(function (id) {
            var el = document.getElementById(id);
            return !!(el && el.classList.contains('active'));
        });
    }

    function refreshProgrammePanels() {
        if (_programmeRefreshBusy || document.hidden || programmeModalIsOpen()) return;
        _programmeRefreshBusy = true;

        fetch(window.location.href, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Programme refresh failed');
            return response.text();
        })
        .then(function (htmlText) {
            // Parse the latest server-rendered programme data off-screen.
            var parsed = new DOMParser().parseFromString(htmlText, 'text/html');
            var currentList = document.getElementById('progTabProgrammes');
            var latestList = parsed.getElementById('progTabProgrammes');
            var currentInvested = document.getElementById('progTabInvested');
            var latestInvested = parsed.getElementById('progTabInvested');

            if (currentList && latestList) {
                currentList.innerHTML = latestList.innerHTML;
                // Reapply the user's existing search to newly received programme rows.
                var searchInput = document.getElementById('progSearchInput');
                if (searchInput && searchInput.value.trim() !== '') {
                    onProgSearch(searchInput.value);
                }
            }

            // Investment state can change while this page remains open.
            // Update only the panel contents; keep tabs and page state intact.
            if (currentInvested && latestInvested && !programmeModalIsOpen()) {
                currentInvested.innerHTML = latestInvested.innerHTML;
                initializeProvisionPanels();
            }

            // If the active tab is Sent Requests, its records are refreshed below
            // through the existing AJAX endpoint, just as VPS refreshes its lists.
            var sentTab = document.querySelector('.prog-tab[data-tab="sent"]');
            var sentContent = document.getElementById('progTabSent');
            if (sentTab && sentContent && sentTab.classList.contains('active') && sentContent.classList.contains('active')) {
                loadSentInvestmentRequests();
            }
        })
        .catch(function () {
            // Keep the current display on transient network/server errors.
        })
        .finally(function () {
            _programmeRefreshBusy = false;
        });
    }

    function startProgrammeAutoRefresh() {
        if (_programmeRefreshTimer) window.clearInterval(_programmeRefreshTimer);
        _programmeRefreshTimer = window.setInterval(function () {
            refreshProgrammePanels();
        }, 8000);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshProgrammePanels();
    });
    window.addEventListener('focus', refreshProgrammePanels);
    window.addEventListener('beforeunload', function () {
        if (_programmeRefreshTimer) {
            window.clearInterval(_programmeRefreshTimer);
            _programmeRefreshTimer = null;
        }
    });
    initializeProvisionPanels();
    startProgrammeAutoRefresh();

    window.onProgSearch           = onProgSearch;
    window.viewProgramme          = viewProgramme;
    window.closeProgDetailModal   = closeProgDetailModal;
    window.openProgConfirm        = openProgConfirm;
    window.closeProgConfirm       = closeProgConfirm;
    window.progConfirmAction      = progConfirmAction;
    window.cancelProgrammeConfirm = cancelProgrammeConfirm;
    window.closeProgCancel        = closeProgCancel;
    window.progCancelAction       = progCancelAction;
    window.closeProgAlert         = closeProgAlert;
</script>
<script>
    function harvhubGoToTab(tab) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: tab }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'investorapp.php?tab=' + encodeURIComponent(tab);
    }
    window.harvhubGoToTab = harvhubGoToTab;

    (function () {
        document.body.classList.add('page-programmes');

        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;
            if (e.data.type === 'theme') {
                document.body.classList.toggle('dark-mode', !!e.data.dark);
            }
        });

        var WATCHED = [
            'page-connect_investor_broker',
            'profile-page-open',
            'page-revenue_history',
            'page-profit_split',
            'page-vps',
            'page-disconnect_broker',
            'page-programmes',
            'page-trader_app',
            'prog-detail-view-open'
        ];
        function broadcast() {
            var add = WATCHED.filter(function (c) { return document.body.classList.contains(c); });
            try {
                window.parent.postMessage({
                    type: 'bodyClass',
                    add: add,
                    remove: WATCHED.filter(function (c) { return add.indexOf(c) === -1; })
                }, '*');
            } catch (e) {}
        }
        new MutationObserver(broadcast).observe(document.body, { attributes: true, attributeFilter: ['class'] });
        broadcast();
        try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}
    })();
</script>
<script>
(function(){
'use strict';

var PA_ROWS = [];
var activeTradeSource = 'live';
var pageStack = [];
var viewState = {page:'dashboard'};
var currentTradeRows = [];
var lastStats = null;
var $ = function(id){ return document.getElementById(id); };

function esc(value){
    return String(value == null ? '' : value).replace(/[&<>"']/g, function(c){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
}
function num(value){ var n = Number(value); return Number.isFinite(n) ? n : 0; }
function fmt(value, max){ return num(value).toLocaleString(undefined, {maximumFractionDigits:max == null ? 2 : max}); }
function dateKey(value){ return value ? String(value).slice(0,10) : ''; }
function normalizedOutcome(value){
    var v = String(value == null ? '' : value).trim().toLowerCase().replace(/[\s_-]+/g,' ');
    var aliases = {
        'breakeven':'closed flat','break even':'closed flat','closed at breakeven':'closed flat',
        'closed breakeven':'closed flat','closed profit':'closed in profit','profit':'closed in profit',
        'profitable':'closed in profit','closed loss':'closed in loss','loss':'closed in loss','losing':'closed in loss'
    };
    return aliases[v] || v;
}
function outcomeLabel(value){
    var v = normalizedOutcome(value);
    var labels = {
        'stoploss hit':'Stoploss hit',
        'take profit reached':'Profit reached',
        'closed flat':'Closed flat',
        'closed in profit':'Profit reached',
        'closed in loss':'Stoploss hit',
        'matched family':'Signal recorded',
        "identify but don't trade":'Signal recorded'
    };
    return labels[v] || (value ? String(value) : 'Signal recorded');
}
function isLoss(row){ var v = normalizedOutcome(row && row.outcome); return v === 'stoploss hit' || v === 'closed in loss'; }
function isProfit(row){ var v = normalizedOutcome(row && row.outcome); return v === 'take profit reached' || v === 'closed in profit'; }
function isFlat(row){ return normalizedOutcome(row && row.outcome) === 'closed flat'; }
function isClosed(row){ return isLoss(row) || isProfit(row) || isFlat(row) || ['closed','position closed','manual close','manually closed'].indexOf(normalizedOutcome(row && row.outcome)) !== -1; }
function tradeDateFor(row){ return dateKey(row && row.trade_date); }
function isNonTradeStatus(row){
    var status = normalizedOutcome(row && row.outcome);
    return status === 'pending entry' || status === 'in trade';
}
function tradeRows(rows){
    return (rows || []).filter(function(row){
        // Only completed trade outcomes belong in the investor trade totals/lists.
        // Pending entries and trades still in progress are deliberately excluded.
        return num(row.trade_configured) === 1 && !isNonTradeStatus(row);
    });
}

function calculate(rows){
    rows = Array.isArray(rows) ? rows : [];
    var trades = tradeRows(rows);
    var closed = trades.filter(isClosed);
    var sl = trades.filter(isLoss).length;
    var tp = trades.filter(isProfit).length;
    var flat = trades.filter(isFlat).length;
    var winsR = trades.reduce(function(total, row){ return total + (isProfit(row) ? Math.abs(num(row.risk_reward)) : 0); }, 0);
    var maxLoss = 0, maxWin = 0, lossRun = 0, winRun = 0;
    var equity = 100, highest = 100, maxDrawdown = 0;
    var ordered = closed.slice().sort(function(a,b){ return tradeDateFor(a).localeCompare(tradeDateFor(b)); });
    ordered.forEach(function(row){
        if (isLoss(row)) { lossRun++; winRun = 0; maxLoss = Math.max(maxLoss, lossRun); }
        else if (isProfit(row)) { winRun++; lossRun = 0; maxWin = Math.max(maxWin, winRun); }
        else { lossRun = 0; winRun = 0; }
        equity += isLoss(row) ? -1 : (isProfit(row) ? Math.abs(num(row.risk_reward)) : 0);
        highest = Math.max(highest, equity);
        var drawdown = highest > 0 ? ((highest - equity) / highest) * 100 : 0;
        maxDrawdown = Math.max(maxDrawdown, drawdown);
    });
    return {
        signals: rows.length,
        total: trades.length,
        sl: sl,
        tp: tp,
        flat: flat,
        winsR: winsR,
        netR: winsR - sl,
        closed: closed.length,
        winRate: closed.length ? (tp / closed.length) * 100 : 0,
        maxLoss: maxLoss,
        maxWin: maxWin,
        currentBalance: equity,
        highestBalance: highest,
        highestRevenue: Math.max(0, highest - 100),
        maxDrawdown: maxDrawdown
    };
}

function metricCard(label, value, icon, detail, cls, action, buttonLabel){
    var actionHtml = action
        ? '<button type="button" class="pa-action" data-page-action="' + esc(action) + '">' + esc(buttonLabel || 'See trades') + '</button>'
        : '';
    return '<article class="pa-card pa-metric">'
        + '<div class="pa-metric-top"><span class="pa-label">' + esc(label) + '</span><span class="pa-icon"><i class="fa-solid ' + esc(icon) + '"></i></span></div>'
        + '<div class="pa-value ' + (cls || '') + '">' + esc(value) + '</div>'
        + '<div class="pa-detail">' + (detail || '') + '</div>'
        + actionHtml
        + '</article>';
}
function renderDashboard(){
    var x = calculate(currentTradeRows);
    lastStats = x;
    if (!PA_ROWS.length) {
        $('paDashboardContent').innerHTML = '<div class="pa-panel pa-empty"><i class="fa-solid fa-chart-line"></i><strong>No recorded signals</strong>No analytics records are available for this programme yet.</div>';
        return;
    }
    var html = '';
    html += metricCard('Total Signals', fmt(x.signals,0), 'fa-signal', 'Combined strategy and confirmation signals');
    html += metricCard('Total Trades', fmt(x.total,0), 'fa-arrow-right-arrow-left', 'All selected programme symbols and timeframes', '', 'trades-provision', 'Trades Provision');
    html += metricCard('Stoploss hit', fmt(x.sl,0), 'fa-shield-halved', 'Trades closed at stoploss', 'negative');
    html += metricCard('Lost Risks', '-' + fmt(x.sl,0) + 'RR', 'fa-arrow-trend-down', 'One risk unit for each stoploss', 'negative');
    html += metricCard('Profit reached', fmt(x.tp,0), 'fa-trophy', 'Trades closed in profit', 'positive');
    html += metricCard('Risk rewards', '+' + fmt(x.winsR,2) + 'RR', 'fa-coins', 'Combined realised profit', 'positive');
    html += metricCard('Consecutive Loss', fmt(x.maxLoss,0), 'fa-arrow-down', 'Longest recorded losing sequence');
    html += metricCard('Consecutive Win', fmt(x.maxWin,0), 'fa-arrow-up', 'Longest recorded winning sequence');
    html += metricCard('Highest Drawdown', fmt(x.maxDrawdown,2) + '%', 'fa-chart-line', 'Maximum recorded drawdown', 'negative');
    html += metricCard('Highest Revenue Peak', '+' + fmt(x.highestRevenue,2) + '%', 'fa-arrow-trend-up', 'Highest balance above the starting baseline', 'positive');
    html += metricCard('Win rate', fmt(x.winRate,1) + '%', 'fa-bullseye', fmt(x.tp,0) + ' wins / ' + fmt(x.closed,0) + ' closed');
    html += metricCard('Current virtual balance', fmt(x.currentBalance,2) + '%', 'fa-scale-balanced', 'Starting baseline adjusted by recorded losses and rewards', x.currentBalance >= 100 ? 'positive' : 'negative');
    $('paDashboardContent').innerHTML = '<div class="pa-grid">' + html + '</div>';
}

function resultRiskText(row){
    if (isProfit(row)) return '+' + fmt(Math.abs(num(row.risk_reward)),2) + 'RR';
    if (isLoss(row)) return '-1RR';
    if (isFlat(row)) return '0RR';
    return '—';
}
function renderSignalCard(row){
    var outcome = outcomeLabel(row.outcome);
    var resultClass = isProfit(row) ? 'positive' : (isLoss(row) ? 'negative' : '');
    var statusClass = isProfit(row) ? 'take-profit-reached' : (isLoss(row) ? 'stoploss-hit' : '');
    return '<article class="pa-card pa-signal-card">'
        + '<div class="pa-signal-symbol">' + esc(row.symbol || '—') + '</div>'
        + '<div class="pa-signal-result"><span class="pa-status ' + statusClass + '">' + esc(outcome) + '</span>'
        + '<strong class="pa-signal-rr ' + resultClass + '">' + esc(resultRiskText(row)) + '</strong></div>'
        + '</article>';
}
function renderSignalCards(rows){
    if (!rows.length) return '<div class="pa-panel pa-empty"><i class="fa-solid fa-chart-line"></i><strong>No recorded trades</strong>No trade results were recorded in this period.</div>';
    return rows.slice().sort(function(a,b){
        return tradeDateFor(a).localeCompare(tradeDateFor(b)) || String(a.symbol || '').localeCompare(String(b.symbol || ''));
    }).map(renderSignalCard).join('');
}
function getDateRows(rows){ return tradeRows(rows).filter(function(row){ return !!tradeDateFor(row); }); }
function groupBy(rows, keyFn){
    var map = {};
    rows.forEach(function(row){ var key = keyFn(row); if (key) (map[key] || (map[key] = [])).push(row); });
    return map;
}
function monthWeekNumber(date){
    var day = Number(String(date || '').slice(8,10));
    if (!Number.isFinite(day) || day < 1 || day > 31) return 0;
    return Math.min(4, Math.floor((day - 1) / 7) + 1);
}
function monthWeekKey(month, weekNumber){ return String(month) + '|' + String(weekNumber); }
function parseMonthWeekKey(key){
    var parts = String(key || '').split('|');
    return {month:parts[0] || '', weekNumber:Number(parts[1] || 0)};
}
function monthName(key){
    var date = new Date(key + '-01T12:00:00');
    return date.toLocaleDateString(undefined, {month:'long',year:'numeric'});
}
function monthRows(rows, month){ return rows.filter(function(row){ return tradeDateFor(row).slice(0,7) === month; }); }
function weekRows(rows, weekKey){
    var parsed = parseMonthWeekKey(weekKey);
    if (!parsed.month || parsed.weekNumber < 1 || parsed.weekNumber > 4) return [];
    return rows.filter(function(row){
        var date = tradeDateFor(row);
        return date.slice(0,7) === parsed.month && monthWeekNumber(date) === parsed.weekNumber;
    });
}
function isolatedHeader(title){
    return '<div class="pa-isolated-head"><h2>' + esc(title) + '</h2></div>';
}
function renderTradesProvision(){
    var trades = getDateRows(currentTradeRows);
    var months = groupBy(trades, function(row){ return tradeDateFor(row).slice(0,7); });
    var keys = Object.keys(months).sort().reverse();
    var html = isolatedHeader('Trades Provision');
    if (!keys.length) {
        $('paIsolated').innerHTML = html + '<div class="pa-panel pa-empty"><i class="fa-solid fa-calendar-days"></i><strong>No trade dates yet</strong>Recorded trade dates will appear here.</div>';
        return;
    }
    html += '<div class="pa-period-list">' + keys.map(function(month){
        return '<button type="button" class="pa-period" data-month="' + esc(month) + '">'
            + '<strong>' + esc(monthName(month)) + '</strong>'
            + '<span>' + months[month].length + (months[month].length === 1 ? ' trade' : ' trades') + ' · Open month <i class="fa-solid fa-chevron-right"></i></span>'
            + '</button>';
    }).join('') + '</div>';
    $('paIsolated').innerHTML = html;
}
function renderWeeks(month){
    var trades = getDateRows(currentTradeRows);
    var monthTrades = monthRows(trades, month);
    var weeks = groupBy(monthTrades, function(row){ return String(monthWeekNumber(tradeDateFor(row))); });
    var keys = Object.keys(weeks).sort(function(a,b){ return Number(b) - Number(a); });
    var html = isolatedHeader(monthName(month));
    if (!keys.length) {
        $('paIsolated').innerHTML = html + '<div class="pa-panel pa-empty"><i class="fa-solid fa-calendar-days"></i><strong>No trading weeks</strong>No trades were recorded for this month.</div>';
        return;
    }
    html += '<div class="pa-period-list">' + keys.map(function(weekNumber){
        var key = monthWeekKey(month, weekNumber);
        return '<button type="button" class="pa-period" data-week="' + esc(key) + '">'
            + '<strong>Week ' + esc(weekNumber) + '</strong>'
            + '<span>' + weeks[weekNumber].length + (weeks[weekNumber].length === 1 ? ' trade' : ' trades') + ' · Open week <i class="fa-solid fa-chevron-right"></i></span>'
            + '</button>';
    }).join('') + '</div>';
    $('paIsolated').innerHTML = html;
}
function renderDays(weekKey){
    var parsed = parseMonthWeekKey(weekKey);
    var trades = getDateRows(currentTradeRows);
    var currentWeekRows = weekRows(trades, weekKey);
    var days = groupBy(currentWeekRows, function(row){ return tradeDateFor(row); });
    var keys = Object.keys(days).sort().reverse();
    var html = isolatedHeader('Week ' + parsed.weekNumber);
    if (!keys.length) {
        $('paIsolated').innerHTML = html + '<div class="pa-panel pa-empty"><i class="fa-solid fa-calendar-days"></i><strong>No trading days</strong>No trades were recorded for this week.</div>';
        return;
    }
    html += '<div class="pa-period-list">' + keys.map(function(day){
        var label = new Date(day + 'T12:00:00').toLocaleDateString(undefined, {weekday:'long',day:'numeric',month:'long',year:'numeric'});
        return '<button type="button" class="pa-period" data-day="' + esc(day) + '">'
            + '<strong>' + esc(label) + '</strong>'
            + '<span>' + days[day].length + (days[day].length === 1 ? ' trade' : ' trades') + ' · Open day <i class="fa-solid fa-chevron-right"></i></span>'
            + '</button>';
    }).join('') + '</div>';
    $('paIsolated').innerHTML = html;
}
function renderTradeDay(day){
    var trades = getDateRows(currentTradeRows).filter(function(row){ return tradeDateFor(row) === day; });
    var label = new Date(day + 'T12:00:00').toLocaleDateString(undefined, {weekday:'long',day:'numeric',month:'long',year:'numeric'});
    $('paIsolated').innerHTML = isolatedHeader(label) + '<div class="pa-signal-list">' + renderSignalCards(trades) + '</div>';
}
function syncParentChrome(){
    var nested = viewState.page !== 'dashboard';
    var back = document.querySelector('.prog-analytics-header .prog-view-back');
    if (back) back.setAttribute('aria-label', nested ? 'Back to previous analytics view' : 'Back to programme');
    try { window.parent.postMessage({type:'programmeAnalyticsView', nested:nested}, '*'); } catch(e) {}
}
function renderCurrentView(){
    var isolated = viewState.page !== 'dashboard';
    $('paDashboard').classList.toggle('pa-hidden', isolated);
    $('paIsolated').classList.toggle('pa-hidden', !isolated);
    syncParentChrome();
    if (!isolated) { renderDashboard(); return; }
    if (viewState.page === 'trades-provision') renderTradesProvision();
    else if (viewState.page === 'trades-weeks') renderWeeks(viewState.month);
    else if (viewState.page === 'trades-days') renderDays(viewState.week);
    else if (viewState.page === 'trades-day') renderTradeDay(viewState.day);
}
function goPage(page, extra){
    pageStack.push(viewState);
    viewState = Object.assign({page:page}, extra || {});
    renderCurrentView();
    var scroller = $('progAnalyticsBody');
    if (scroller) scroller.scrollTop = 0;
}
function backPage(){
    viewState = pageStack.length ? pageStack.pop() : {page:'dashboard'};
    renderCurrentView();
    var scroller = $('progAnalyticsBody');
    if (scroller) scroller.scrollTop = 0;
}
function handleProgrammeAnalyticsHeaderBack(){
    if (viewState.page !== 'dashboard') { backPage(); return; }
    if (typeof window.closeProgrammeAnalyticsModal === 'function') window.closeProgrammeAnalyticsModal();
}
window.handleProgrammeAnalyticsHeaderBack = handleProgrammeAnalyticsHeaderBack;
function refresh(){
    currentTradeRows = PA_ROWS.filter(function(row){
        return num(row.live_trades) === (activeTradeSource === 'live' ? 1 : 0);
    });
    renderCurrentView();
}

$('paSourceTabs').addEventListener('click', function(event){
    var tab = event.target.closest('[data-trade-source]');
    if (!tab) return;
    activeTradeSource = tab.dataset.tradeSource === 'history' ? 'history' : 'live';
    $('paSourceTabs').querySelectorAll('[data-trade-source]').forEach(function(button){
        var active = button === tab;
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    pageStack = [];
    viewState = {page:'dashboard'};
    refresh();
});

document.addEventListener('click', function(event){
    var action = event.target.closest('[data-page-action]');
    if (action) {
        if (action.dataset.pageAction === 'trades-provision') goPage('trades-provision');
        return;
    }
    var month = event.target.closest('[data-month]');
    if (month) { goPage('trades-weeks', {month:month.dataset.month}); return; }
    var week = event.target.closest('[data-week]');
    if (week) { goPage('trades-days', {week:week.dataset.week}); return; }
    var day = event.target.closest('[data-day]');
    if (day) { goPage('trades-day', {day:day.dataset.day}); return; }
});

window.setProgrammeAnalyticsData = function(payload){
    payload = payload && typeof payload === 'object' ? payload : {};
    PA_ROWS = Array.isArray(payload.rows) ? payload.rows : [];
    activeTradeSource = 'live';
    pageStack = [];
    viewState = {page:'dashboard'};
    $('paSourceTabs').querySelectorAll('[data-trade-source]').forEach(function(button){
        var active = button.dataset.tradeSource === 'live';
        button.classList.toggle('active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    refresh();
};
window.resetProgrammeAnalyticsData = function(){
    PA_ROWS = [];
    currentTradeRows = [];
    pageStack = [];
    viewState = {page:'dashboard'};
    lastStats = null;
    if ($('paDashboardContent')) $('paDashboardContent').innerHTML = '';
    if ($('paIsolated')) $('paIsolated').innerHTML = '';
};
window.addEventListener('message', function(event){
    if (!event.data || typeof event.data !== 'object') return;
    if (event.data.type === 'theme') {
        document.body.classList.toggle('dark-mode', !!event.data.dark);
        document.documentElement.classList.toggle('force-light', !event.data.dark);
    }
});
try { window.parent.postMessage({type:'requestTheme'}, '*'); } catch(e) {}
})();
</script>
</body>
</html>
