<?php
// signals_provision_request.php — Trader provision request browser
session_start();
require_once __DIR__ . '/mysqlconnection.php';

try {
    $pdo = getAvailablePdoConnection();
} catch (Throwable $e) {
    error_log(
        basename(__FILE__) .
        ' database connection failed: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}

require_once __DIR__ . '/notification_service.php';

if (!isset($_SESSION['user_email'])) { header("Location: index.php?role=developer"); exit; }
$email = strtolower($_SESSION['user_email']);

$stmt = $pdo->prepare("SELECT * FROM harvhub WHERE LOWER(email) = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { unset($_SESSION['user_email'], $_SESSION['auth_role']); header("Location: index.php?role=developer"); exit; }

$userId   = (int)$user['id'];
$fullName = $user['fullname'] ?? 'User';
$darkMode = !empty($user['dark_mode']);

$activeSubAccountId = (int)($user['sub_account_id'] ?? $userId);
$mainAccountId      = (int)($user['main_account_id'] ?? 0);
$_SESSION['active_sub_account_id'] = $activeSubAccountId;

$selectedProgrammeId = (int)($_SESSION['selected_programme_id'] ?? 0);
if ($selectedProgrammeId <= 0) {
    $q = $pdo->prepare("SELECT id FROM programme WHERE userid = ? ORDER BY id DESC LIMIT 1");
    $q->execute([$userId]);
    $selectedProgrammeId = (int)($q->fetchColumn() ?: 0);
    if ($selectedProgrammeId > 0) $_SESSION['selected_programme_id'] = $selectedProgrammeId;
}

$programme = null;
if ($selectedProgrammeId > 0) {
    $q = $pdo->prepare("SELECT * FROM programme WHERE id = ? AND userid = ? LIMIT 1");
    $q->execute([$selectedProgrammeId, $userId]);
    $programme = $q->fetch(PDO::FETCH_ASSOC);
}

function esc_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function jres($a){ header('Content-Type: application/json'); echo json_encode($a); exit; }

function getProgrammeTrainingReadiness(PDO $pdo, int $userId, int $programmeId): array {
    $result = ['has_root' => false, 'trades_configured' => false];

    if ($userId <= 0 || $programmeId <= 0) return $result;

    $tableInfo = function (string $table) use ($pdo): array {
        $allowed = [
            'programme_training_configuration',
            'programme_configuration_confirmation',
            'programme_training_confirmation_configuration'
        ];
        if (!in_array($table, $allowed, true)) return ['exists' => false, 'columns' => []];

        try {
            $q = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $cols = [];
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($row['Field'])) $cols[strtolower((string)$row['Field'])] = (string)$row['Field'];
            }
            return ['exists' => !empty($cols), 'columns' => $cols];
        } catch (Throwable $e) {
            return ['exists' => false, 'columns' => []];
        }
    };

    $countRows = function (string $table, array $info, int $userId, int $programmeId, bool $rootsOnly, bool $tradesOnly) use ($pdo): int {
        if (empty($info['exists'])) return 0;
        $cols = $info['columns'];

        $userCol = $cols['userid'] ?? $cols['user_id'] ?? $cols['developerid'] ?? null;
        $programmeCol = $cols['programmeid'] ?? $cols['programme_id'] ?? null;
        if (!$userCol || !$programmeCol) return 0;

        $where = ["`{$userCol}` = ?", "`{$programmeCol}` = ?"];
        $params = [$userId, $programmeId];

        if ($rootsOnly && isset($cols['row_role'])) {
            $where[] = "`{$cols['row_role']}` = 'root'";
        } elseif ($rootsOnly && isset($cols['configuration_type'])) {
            $where[] = "`{$cols['configuration_type']}` = 'root'";
        }

        if ($tradesOnly) {
            // The configuration is selected by trade_for, not a boolean toggle.
            $tradeForCol = $cols['trade_for'] ?? null;
            if (!$tradeForCol) return 0;
            $where[] = "LOWER(CAST(`{$tradeForCol}` AS CHAR)) IN ('root','root_confirmation')";
        }

        try {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE " . implode(' AND ', $where);
            $q = $pdo->prepare($sql);
            $q->execute($params);
            return (int)$q->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };

    $mainTable = 'programme_training_configuration';
    $mainInfo = $tableInfo($mainTable);
    $result['has_root'] = $countRows($mainTable, $mainInfo, $userId, $programmeId, true, false) > 0;

    foreach ([
        'programme_training_configuration',
        'programme_configuration_confirmation',
        'programme_training_confirmation_configuration'
    ] as $table) {
        $info = ($table === $mainTable) ? $mainInfo : $tableInfo($table);
        if ($countRows($table, $info, $userId, $programmeId, true, true) > 0) {
            $result['trades_configured'] = true;
            break;
        }
    }

    return $result;
}

$serverConfig = [];
try {
    $q = $pdo->query("SELECT * FROM server_account WHERE id = 1 LIMIT 1");
    $serverConfig = $q->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$dayDuration     = (int)($serverConfig['day_challenge_duration'] ?? 30);
$weeklyDuration  = (int)($serverConfig['weekly_challenge_duration'] ?? 60);
$monthlyDuration = (int)($serverConfig['monthly_challenge_duration'] ?? 90);

$trainingReadiness = getProgrammeTrainingReadiness($pdo, $userId, (int)($programme['id'] ?? 0));
$programmeHasVps = false;
if ($programme) {
    try {
        $vpsQ = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
        $vpsQ->execute([(int)$programme['id']]);
        $programmeHasVps = (bool)$vpsQ->fetchColumn();
    } catch (Throwable $e) {}
    if (!$programmeHasVps) {
        try {
            $vpsQ = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
            $vpsQ->execute([(int)$programme['id']]);
            $programmeHasVps = (bool)$vpsQ->fetchColumn();
        } catch (Throwable $e) {}
    }
}
$programmeHasBroker = $programme
    && !empty($programme['broker'])
    && !empty($programme['server'])
    && !empty($programme['login']);

// Once the programme is advertised and approved, a provision test is no longer required.
$programmeIsAdvertised = $programme
    && (int)($programme['advertisement'] ?? 0) === 1
    && (int)($programme['server_advertisement_approval'] ?? 0) === 1;


$existingInterest = null;
if ($programme) {
    try {
        $q = $pdo->prepare("
            SELECT *
            FROM signals_provider_interest
            WHERE user_id = ? AND programme_id = ?
              AND interest_status IN ('interested','pending','active','ongoing')
            ORDER BY id DESC
            LIMIT 1
        ");
        $q->execute([$userId, (int)$programme['id']]);
        $existingInterest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {}
}

function spiHasPartialUniqueKey(PDO $pdo): bool {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $q = $pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'signals_provider_interest'
              AND COLUMN_NAME = 'live_uniq_user_id'
        ");
        $q->execute();
        $cached = ((int)$q->fetchColumn() > 0);
    } catch (Throwable $e) {
        $cached = false;
    }
    return $cached;
}

// ==================== HELPERS ====================
function isProfitableOrBreakeven(int $expectedWin, int $consecutiveLoss, float $rr): bool {
    if ($rr <= 0) return false;
    return ($expectedWin * $rr) >= $consecutiveLoss;
}

/**
 * Live provision challenge comparison.
 *
 * Main-root and confirmation-root trades are intentionally read in one
 * combined stream. Historical rows (live_trades = 0) never participate.
 */
/**
 * Period-based signal provision challenge engine.
 * The live analytics stream is combined across all main roots and confirmations.
 * Only live_trades=1 rows from signal_testing_started_at through the challenge end
 * are used. Restricted dates and weekdays are frozen when the test starts.
 */
function spc_ensureLiveTradesColumn(PDO $pdo): bool {
    try {
        $table = $pdo->query("SHOW TABLES LIKE 'programme_analytics'");
        if (!$table || !$table->fetchColumn()) return false;
        $columns = $pdo->query("SHOW COLUMNS FROM `programme_analytics`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('live_trades', $columns, true)) {
            $pdo->exec("ALTER TABLE `programme_analytics` ADD COLUMN `live_trades` TINYINT(1) NOT NULL DEFAULT 0");
            $columns = $pdo->query("SHOW COLUMNS FROM `programme_analytics`")->fetchAll(PDO::FETCH_COLUMN);
        }
        return in_array('live_trades', $columns, true);
    } catch (Throwable $e) {
        error_log('[signals challenge] live_trades schema check failed: ' . $e->getMessage());
        return false;
    }
}

function spc_ensureChallengeSchema(PDO $pdo): bool {
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $table = $pdo->query("SHOW TABLES LIKE 'signals_provider_interest'");
        if (!$table || !$table->fetchColumn()) return $ready = false;
        $definitions = [
            'daily_progress_json' => 'LONGTEXT NULL',
            'weekly_progress_json' => 'LONGTEXT NULL',
            'monthly_progress_json' => 'LONGTEXT NULL',
            'challenge_restricted_dates_json' => 'LONGTEXT NULL',
            'challenge_restricted_days_json' => 'LONGTEXT NULL',
            'challenge_duration_days' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'challenge_end_at' => 'DATETIME NULL',
            'challenge_timezone' => 'VARCHAR(64) NULL',
            'challenge_total_periods' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'challenge_completed_periods' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'challenge_current_period_index' => 'INT NOT NULL DEFAULT -1',
            'challenge_failed_period_index' => 'INT NULL DEFAULT NULL',
            'challenge_failed_at' => 'DATETIME NULL',
            'challenge_failure_reason' => 'TEXT NULL',
            'challenge_completed_at' => 'DATETIME NULL',
            'challenge_last_evaluated_at' => 'DATETIME NULL'
        ];
        $columns = $pdo->query("SHOW COLUMNS FROM `signals_provider_interest`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($definitions as $column => $definition) {
            if (!in_array($column, $columns, true)) {
                $pdo->exec("ALTER TABLE `signals_provider_interest` ADD COLUMN `{$column}` {$definition}");
                $columns[] = $column;
            }
        }
        return $ready = spc_ensureLiveTradesColumn($pdo);
    } catch (Throwable $e) {
        error_log('[signals challenge] additive challenge schema migration failed: ' . $e->getMessage());
        return $ready = false;
    }
}

function spc_encode($value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? '[]' : $json;
}

function spc_timezone(string $name): DateTimeZone {
    try { return new DateTimeZone(trim($name) !== '' ? trim($name) : 'UTC'); }
    catch (Throwable $e) { return new DateTimeZone('UTC'); }
}

function spc_parseDateTime($value, DateTimeZone $timezone): ?DateTimeImmutable {
    if ($value instanceof DateTimeInterface) {
        return (new DateTimeImmutable($value->format('Y-m-d H:i:s.uP')))->setTimezone($timezone);
    }
    $raw = trim((string)$value);
    if ($raw === '') return null;
    try { return new DateTimeImmutable($raw, $timezone); }
    catch (Throwable $e) { return null; }
}

function spc_firstRowValue(array $row, array $keys) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
    }
    return null;
}

function spc_normalizeOutcome($raw): string {
    $value = strtolower(trim((string)$raw));
    $value = preg_replace('/[\\s_-]+/', ' ', $value);
    $aliases = [
        'breakeven' => 'closed flat', 'break even' => 'closed flat',
        'closed at breakeven' => 'closed flat', 'closed breakeven' => 'closed flat',
        'closed profit' => 'closed in profit', 'profit' => 'closed in profit',
        'profitable' => 'closed in profit', 'closed loss' => 'closed in loss',
        'loss' => 'closed in loss', 'losing' => 'closed in loss'
    ];
    return $aliases[$value] ?? $value;
}

function spc_durationDays(string $period, array $serverConfig = []): int {
    $period = strtolower(trim($period));
    if ($period === 'weekly') $value = (int)($serverConfig['weekly_challenge_duration'] ?? 60);
    elseif ($period === 'monthly') $value = (int)($serverConfig['monthly_challenge_duration'] ?? 90);
    else $value = (int)($serverConfig['day_challenge_duration'] ?? 30);
    if ($value <= 0) $value = $period === 'weekly' ? 60 : ($period === 'monthly' ? 90 : 30);
    return max(1, min(366, $value));
}

function spc_periodCount(string $period, int $durationDays): int {
    $period = strtolower(trim($period));
    if ($period === 'weekly') return max(1, (int)round($durationDays / 7.5)); // 60 days = 8 challenge weeks.
    if ($period === 'monthly') return max(1, (int)round($durationDays / 30)); // 90 days = 3 challenge months.
    return max(1, $durationDays);
}

function spc_normalizeDayName($raw): string {
    $v = strtolower(trim((string)$raw));
    $map = [
        '0'=>'sunday','sun'=>'sunday','sunday'=>'sunday',
        '1'=>'monday','mon'=>'monday','monday'=>'monday',
        '2'=>'tuesday','tue'=>'tuesday','tues'=>'tuesday','tuesday'=>'tuesday',
        '3'=>'wednesday','wed'=>'wednesday','weds'=>'wednesday','wednesday'=>'wednesday',
        '4'=>'thursday','thu'=>'thursday','thur'=>'thursday','thurs'=>'thursday','thursday'=>'thursday',
        '5'=>'friday','fri'=>'friday','friday'=>'friday',
        '6'=>'saturday','sat'=>'saturday','saturday'=>'saturday'
    ];
    return $map[$v] ?? '';
}

function spc_getRestrictedCalendar(PDO $pdo, int $userId, int $programmeId, string $email = ''): array {
    $dates = [];
    $days = [];
    if ($programmeId <= 0) return ['dates'=>[], 'days'=>[]];

    $developerIds = [];
    if (trim($email) !== '') {
        try {
            $developerQuery = $pdo->prepare("SELECT id FROM developers WHERE LOWER(email) = ? LIMIT 1");
            $developerQuery->execute([strtolower(trim($email))]);
            $developerId = (int)$developerQuery->fetchColumn();
            if ($developerId > 0) $developerIds[] = $developerId;
        } catch (Throwable $e) {}
    }
    // Restricted calendar rows are owned by developers. Fall back to the
    // HarvHub user id only on installations where no developer record exists.
    if (!$developerIds && $userId > 0) $developerIds[] = $userId;
    $developerIds = array_values(array_unique(array_filter(array_map('intval', $developerIds))));
    if (!$developerIds) return ['dates'=>[], 'days'=>[]];

    $idPlaceholders = implode(',', array_fill(0, count($developerIds), '?'));
    try {
        $dateTable = $pdo->query("SHOW TABLES LIKE 'accountmanagement_restricted_dates'");
        if ($dateTable && $dateTable->fetchColumn()) {
            $cols = $pdo->query("SHOW COLUMNS FROM `accountmanagement_restricted_dates`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('developerid', $cols, true) && in_array('programme_id', $cols, true) && in_array('restricted_date', $cols, true)) {
                $q = $pdo->prepare("SELECT `restricted_date` FROM `accountmanagement_restricted_dates` WHERE `developerid` IN ({$idPlaceholders}) AND `programme_id` = ?");
                $q->execute(array_merge($developerIds, [$programmeId]));
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $rawDate) {
                    $date = substr(trim((string)$rawDate), 0, 10);
                    if (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) $dates[$date] = $date;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[signals challenge] restricted-date lookup failed: ' . $e->getMessage());
    }

    try {
        $dayTable = $pdo->query("SHOW TABLES LIKE 'accountmanagement_restricted_days'");
        if ($dayTable && $dayTable->fetchColumn()) {
            $cols = $pdo->query("SHOW COLUMNS FROM `accountmanagement_restricted_days`")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('developerid', $cols, true) && in_array('programme_id', $cols, true) && in_array('day', $cols, true)) {
                $q = $pdo->prepare("SELECT `day` FROM `accountmanagement_restricted_days` WHERE `developerid` IN ({$idPlaceholders}) AND `programme_id` = ?");
                $q->execute(array_merge($developerIds, [$programmeId]));
                foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $rawDay) {
                    $day = spc_normalizeDayName($rawDay);
                    if ($day !== '') $days[$day] = $day;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[signals challenge] restricted-day lookup failed: ' . $e->getMessage());
    }

    $dates = array_values($dates);
    $days = array_values($days);
    sort($dates);
    sort($days);
    return ['dates'=>$dates, 'days'=>$days];
}

function spc_periodLabel(string $period, int $index): string {
    $prefix = $period === 'weekly' ? 'Week' : ($period === 'monthly' ? 'Month' : 'Day');
    return $prefix . ' ' . ($index + 1);
}

function spc_restrictionDetail(string $date, bool $restrictedDate, bool $restrictedDay, string $weekday): array {
    $reasons = [];
    if ($restrictedDate) $reasons[] = 'restricted date';
    if ($restrictedDay) $reasons[] = 'restricted day';
    return [
        'date' => $date,
        'weekday' => ucfirst($weekday),
        'reason' => implode(' + ', $reasons),
        'label' => $date . ' (' . ucfirst($weekday) . ' — ' . implode(' + ', $reasons) . ')'
    ];
}

function spc_buildChallengePeriods(DateTimeImmutable $startAt, string $period, int $durationDays, array $restricted, DateTimeImmutable $now): array {
    $period = in_array($period, ['daily','weekly','monthly'], true) ? $period : 'daily';
    $durationDays = max(1, $durationDays);
    $restrictedDateSet = array_fill_keys(array_map('strval', $restricted['dates'] ?? []), true);
    $restrictedDaySet = array_fill_keys(array_map('strval', $restricted['days'] ?? []), true);
    $windowStart = $startAt->setTime(0, 0, 0);
    $restrictedDayLabels = array_map('ucfirst', array_keys($restrictedDaySet));
    $count = $period === 'daily' ? $durationDays : spc_periodCount($period, $durationDays);
    $periods = [];

    // Keep every calendar checkpoint in the sequence. Restricted days remain
    // visible as skipped cards, but do not count toward required checkpoints.
    for ($i = 0; $i < $count; $i++) {
        if ($period === 'daily') {
            $offsetStart = $i;
            $offsetEndExclusive = $i + 1;
        } else {
            $offsetStart = (int)floor(($i * $durationDays) / $count);
            $offsetEndExclusive = (int)floor((($i + 1) * $durationDays) / $count);
            if ($offsetEndExclusive <= $offsetStart) $offsetEndExclusive = $offsetStart + 1;
        }

        $periodStart = $windowStart->modify('+' . $offsetStart . ' days');
        $periodEnd = $windowStart->modify('+' . $offsetEndExclusive . ' days');
        $activeDates = [];
        $excludedDetails = [];
        $calendarRestrictedDays = [];

        for ($dayOffset = $offsetStart; $dayOffset < $offsetEndExclusive; $dayOffset++) {
            $dateObject = $windowStart->modify('+' . $dayOffset . ' days');
            $date = $dateObject->format('Y-m-d');
            $weekday = strtolower($dateObject->format('l'));
            $isRestrictedDate = isset($restrictedDateSet[$date]);
            $isRestrictedDay = isset($restrictedDaySet[$weekday]);

            if ($isRestrictedDate || $isRestrictedDay) {
                if ($isRestrictedDate) $excludedDetails[] = spc_restrictionDetail($date, true, $isRestrictedDay, $weekday);
                if ($isRestrictedDay) $calendarRestrictedDays[$weekday] = ucfirst($weekday);
            } else {
                $activeDates[] = $date;
            }
        }

        $calendarDays = max(0, $offsetEndExclusive - $offsetStart);
        $eligibleDays = count($activeDates);
        $periods[] = [
            'index' => $i,
            'label' => spc_periodLabel($period, $i),
            'start_date' => $periodStart->format('Y-m-d'),
            'end_date' => $periodEnd->modify('-1 day')->format('Y-m-d'),
            'start_at' => $periodStart->format('Y-m-d H:i:s'),
            'end_at' => $periodEnd->format('Y-m-d H:i:s'),
            'calendar_days' => $calendarDays,
            'eligible_days' => $eligibleDays,
            'active_dates' => $activeDates,
            'excluded_dates' => $excludedDetails,
            // Show the programme's configured restricted weekdays on each card;
            // the excluded date list above is scoped to this exact checkpoint.
            'excluded_days' => $restrictedDayLabels,
            'progress' => 0,
            'status' => 'locked',
            'locked' => true,
            'skipped' => $eligibleDays === 0,
            'trades' => 0,
            'wins' => 0,
            'consecutive_losses' => 0,
            'trades_passed' => false,
            'wins_passed' => false,
            'loss_gate_passed' => false,
            'requirements_met' => false
        ];
    }

    return $periods;
}

function spc_previewLookbackStart(DateTimeImmutable $now, string $period): DateTimeImmutable {
    $period = strtolower(trim($period));
    if ($period === 'monthly') return $now->modify('-2 months');
    if ($period === 'weekly') return $now->modify('-14 days');
    return $now->modify('-7 days');
}

function spc_buildPreviewPeriod(string $period, DateTimeImmutable $lookbackStart, DateTimeImmutable $now, array $restricted): array {
    $restrictedDateSet = array_fill_keys(array_map('strval', $restricted['dates'] ?? []), true);
    $restrictedDaySet = array_fill_keys(array_map('strval', $restricted['days'] ?? []), true);
    $calendarStart = $lookbackStart->setTime(0, 0, 0);
    $calendarEnd = $now->setTime(0, 0, 0);
    $activeDates = [];
    $excludedDetails = [];
    $cursor = $calendarStart;
    while ($cursor <= $calendarEnd) {
        $date = $cursor->format('Y-m-d');
        $weekday = strtolower($cursor->format('l'));
        $isRestrictedDate = isset($restrictedDateSet[$date]);
        $isRestrictedDay = isset($restrictedDaySet[$weekday]);
        if ($isRestrictedDate || $isRestrictedDay) {
            // Restricted Dates lists only dates explicitly configured in the
            // account-management calendar. Recurring weekday exclusions appear
            // separately in Restricted Days.
            if ($isRestrictedDate) $excludedDetails[] = spc_restrictionDetail($date, true, $isRestrictedDay, $weekday);
        } else {
            $activeDates[] = $date;
        }
        $cursor = $cursor->modify('+1 day');
    }
    $restrictedDayLabels = array_map('ucfirst', array_keys($restrictedDaySet));
    $label = $period === 'weekly' ? '2 Weeks Preview' : ($period === 'monthly' ? '2 Months Preview' : '7 Days Preview');
    return [
        'index' => 0,
        'label' => $label,
        'start_date' => $lookbackStart->format('Y-m-d'),
        'end_date' => $now->format('Y-m-d'),
        'start_at' => $lookbackStart->format('Y-m-d H:i:s'),
        'end_at' => $now->modify('+1 second')->format('Y-m-d H:i:s'),
        'calendar_days' => (int)$calendarStart->diff($calendarEnd)->days + 1,
        'eligible_days' => count($activeDates),
        'active_dates' => $activeDates,
        'excluded_dates' => $excludedDetails,
        'excluded_days' => $restrictedDayLabels,
        'progress' => 0,
        'status' => 'preview',
        'locked' => false,
        'skipped' => count($activeDates) === 0,
        'preview' => true,
        'trades' => 0,
        'wins' => 0,
        'consecutive_losses' => 0,
        'trades_passed' => false,
        'wins_passed' => false,
        'loss_gate_passed' => false,
        'requirements_met' => false
    ];
}

function spc_countEligibleDays(DateTimeImmutable $startAt, int $durationDays, array $restricted): int {
    $restrictedDateSet = array_fill_keys(array_map('strval', $restricted['dates'] ?? []), true);
    $restrictedDaySet = array_fill_keys(array_map('strval', $restricted['days'] ?? []), true);
    $eligible = 0;
    $start = $startAt->setTime(0, 0, 0);
    for ($i = 0; $i < max(1, $durationDays); $i++) {
        $date = $start->modify('+' . $i . ' days');
        $iso = $date->format('Y-m-d');
        $weekday = strtolower($date->format('l'));
        if (isset($restrictedDateSet[$iso]) || isset($restrictedDaySet[$weekday])) continue;
        $eligible++;
    }
    return $eligible;
}


function spc_durationDescription(string $period, int $durationDays, int $eligibleDays): string {
    $period = strtolower(trim($period));
    if ($period === 'weekly') {
        $count = spc_periodCount('weekly', $durationDays);
        return $count . ' Weeks Duration Provision. Each week must meet the provision challenge, excluding the restricted days and dates in each week. There are ' . $eligibleDays . ' eligible days within ' . $durationDays . ' calendar days.';
    }
    if ($period === 'monthly') {
        $count = spc_periodCount('monthly', $durationDays);
        return $count . ' Months Duration Provision. Each month must meet the provision challenge, excluding the restricted days and dates in each month. There are ' . $eligibleDays . ' eligible days within ' . $durationDays . ' calendar days.';
    }
    return $eligibleDays . ' Days Duration Provision. Each day must meet the provision challenge. (' . $durationDays . ' calendar days before restricted dates and days are excluded.)';
}

function spc_getLiveProvisionEvents(PDO $pdo, int $userId, int $programmeId, DateTimeImmutable $startAt, DateTimeImmutable $endAt, DateTimeZone $timezone): array {
    $result = ['tracking_available'=>false, 'events'=>[]];
    if ($userId <= 0 || $programmeId <= 0 || !spc_ensureLiveTradesColumn($pdo)) return $result;
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM `programme_analytics`")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['programme_id','userid','live_trades','trade_configured'] as $required) {
            if (!in_array($required, $columns, true)) return $result;
        }
        $where = ['`programme_id` = ?', '`userid` = ?', '`live_trades` = 1', '`trade_configured` = 1'];
        if (in_array('matched_family', $columns, true)) $where[] = 'COALESCE(`matched_family`, 1) <> 0';
        $orderColumn = in_array('created_at', $columns, true) ? 'created_at' : (in_array('id', $columns, true) ? 'id' : 'root_id');
        $q = $pdo->prepare('SELECT * FROM `programme_analytics` WHERE ' . implode(' AND ', $where) . ' ORDER BY `' . $orderColumn . '` ASC');
        $q->execute([$programmeId, $userId]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        $startTs = $startAt->getTimestamp();
        $endTs = $endAt->getTimestamp();
        foreach ($rows as $index => $row) {
            if ((int)($row['live_trades'] ?? 0) !== 1 || (int)($row['trade_configured'] ?? 0) !== 1) continue;
            if (array_key_exists('matched_family', $row) && (int)$row['matched_family'] === 0) continue;
            $tradeTimeRaw = spc_firstRowValue($row, ['entry_trigger_time','entry_anchor_time','event_time','trade_time','open_time','created_at']);
            $tradeTime = spc_parseDateTime($tradeTimeRaw, $timezone);
            if (!$tradeTime) continue;
            $tradeTs = $tradeTime->getTimestamp();
            if ($tradeTs < $startTs || $tradeTs >= $endTs) continue;

            $outcome = spc_normalizeOutcome($row['outcome'] ?? '');
            $pnlValue = spc_firstRowValue($row, ['pnl','profit_loss','profitandloss']);
            $pnl = ($pnlValue !== null && is_numeric($pnlValue)) ? (float)$pnlValue : null;
            $type = '';
            if (in_array($outcome, ['take profit reached','closed in profit'], true)) $type = 'win';
            elseif (in_array($outcome, ['stoploss hit','closed in loss'], true)) $type = 'loss';
            elseif ($outcome === 'closed flat') $type = 'flat';
            elseif (in_array($outcome, ['closed','position closed','manual close','manually closed'], true)) {
                if ($pnl !== null && $pnl > 0) $type = 'win';
                elseif ($pnl !== null && $pnl < 0) $type = 'loss';
                elseif ($pnl !== null && $pnl == 0) $type = 'flat';
            }

            if ($type === 'loss') {
                $eventTimeRaw = spc_firstRowValue($row, ['stoploss_trigger_time','close_time','closed_at','event_time','entry_trigger_time','entry_anchor_time','created_at']);
            } elseif ($type === 'win') {
                $eventTimeRaw = spc_firstRowValue($row, ['take_profit_trigger_time','close_time','closed_at','event_time','entry_trigger_time','entry_anchor_time','created_at']);
            } elseif ($type === 'flat') {
                $eventTimeRaw = spc_firstRowValue($row, ['breakeven_trigger_time','close_time','closed_at','event_time','entry_trigger_time','entry_anchor_time','created_at']);
            } else {
                $eventTimeRaw = $tradeTimeRaw;
            }
            $eventTime = spc_parseDateTime($eventTimeRaw, $timezone);
            $result['events'][] = [
                'type' => $type,
                'trade_date' => $tradeTime->format('Y-m-d'),
                'trade_timestamp' => $tradeTs,
                'event_timestamp' => $eventTime ? $eventTime->getTimestamp() : $tradeTs,
                'row_id' => (int)($row['id'] ?? $index),
                'index' => $index
            ];
        }
        $result['tracking_available'] = true;
    } catch (Throwable $e) {
        error_log('[signals challenge] live analytics read failed: ' . $e->getMessage());
    }
    return $result;
}

function spc_computePeriodStats(array $events, array $period): array {
    $dateSet = array_fill_keys($period['active_dates'] ?? [], true);
    $selected = [];
    $trades = 0;
    foreach ($events as $event) {
        if (!isset($dateSet[$event['trade_date'] ?? ''])) continue;
        $trades++;
        if (in_array($event['type'] ?? '', ['win','loss','flat'], true)) $selected[] = $event;
    }
    usort($selected, static function($a, $b) {
        if ($a['event_timestamp'] !== $b['event_timestamp']) return $a['event_timestamp'] <=> $b['event_timestamp'];
        if ($a['row_id'] !== $b['row_id']) return $a['row_id'] <=> $b['row_id'];
        return $a['index'] <=> $b['index'];
    });
    $wins = 0;
    $losses = 0;
    $run = 0;
    $maxRun = 0;
    foreach ($selected as $event) {
        if ($event['type'] === 'win') {
            $wins++;
            $run = 0;
        } elseif ($event['type'] === 'loss') {
            $losses++;
            $run++;
            $maxRun = max($maxRun, $run);
        } else {
            $run = 0;
        }
    }
    return ['trades'=>$trades, 'wins'=>$wins, 'losses'=>$losses, 'consecutive_losses'=>$maxRun];
}

function spc_progressColumn(string $period): string {
    return $period === 'weekly' ? 'weekly_progress_json' : ($period === 'monthly' ? 'monthly_progress_json' : 'daily_progress_json');
}

function spc_periodStorage(array $periods): array {
    $out = [];
    foreach ($periods as $p) {
        $out[] = [
            'index' => (int)$p['index'], 'label' => (string)$p['label'],
            'start_date' => (string)$p['start_date'], 'end_date' => (string)$p['end_date'],
            'progress' => (int)($p['progress'] ?? 0), 'status' => (string)($p['status'] ?? ''),
            'skipped' => !empty($p['skipped']), 'trades' => (int)($p['trades'] ?? 0),
            'wins' => (int)($p['wins'] ?? 0), 'consecutive_losses' => (int)($p['consecutive_losses'] ?? 0),
            'eligible_days' => (int)($p['eligible_days'] ?? 0),
            'excluded_dates' => array_values($p['excluded_dates'] ?? []),
            'excluded_days' => array_values($p['excluded_days'] ?? [])
        ];
    }
    return $out;
}

function spc_savePeriodProgress(PDO $pdo, int $interestId, int $userId, int $programmeId, string $period, array $periods, int $completed, int $total, int $currentIndex, string $nowText): void {
    $daily = $period === 'daily' ? spc_encode(spc_periodStorage($periods)) : '[]';
    $weekly = $period === 'weekly' ? spc_encode(spc_periodStorage($periods)) : '[]';
    $monthly = $period === 'monthly' ? spc_encode(spc_periodStorage($periods)) : '[]';
    $q = $pdo->prepare("UPDATE `signals_provider_interest`
        SET `daily_progress_json` = ?, `weekly_progress_json` = ?, `monthly_progress_json` = ?,
            `challenge_total_periods` = ?, `challenge_completed_periods` = ?,
            `challenge_current_period_index` = ?, `challenge_last_evaluated_at` = ?, `updated_at` = CURRENT_TIMESTAMP
        WHERE `id` = ? AND `user_id` = ? AND `programme_id` = ?");
    $q->execute([$daily,$weekly,$monthly,$total,$completed,$currentIndex,$nowText,$interestId,$userId,$programmeId]);
}

function spc_startProvisionTest(PDO $pdo, int $userId, int $programmeId, array $interest, array $programme, array $serverConfig = [], string $email = ''): array {
    if (!spc_ensureChallengeSchema($pdo)) return ['success'=>false, 'message'=>'The challenge progress schema or live programme analytics is not available.'];
    $period = strtolower(trim((string)($interest['trades_provision'] ?? 'daily')));
    if (!in_array($period, ['daily','weekly','monthly'], true)) $period = 'daily';
    $duration = spc_durationDays($period, $serverConfig);
    $timezoneName = trim((string)($programme['timezone'] ?? ''));
    $timezone = spc_timezone($timezoneName);
    $now = new DateTimeImmutable('now', $timezone);
    $endAt = $now->setTime(0, 0, 0)->modify('+' . $duration . ' days');
    $restricted = spc_getRestrictedCalendar($pdo, $userId, $programmeId, $email);
    $periods = spc_buildChallengePeriods($now, $period, $duration, $restricted, $now);
    $totalRequired = 0;
    foreach ($periods as $p) if ((int)$p['eligible_days'] > 0) $totalRequired++;
    if ($totalRequired <= 0) {
        return ['success'=>false, 'message'=>'Every day in this challenge window is restricted. Adjust the restricted dates/days before beginning the test.'];
    }
    $initial = [];
    foreach ($periods as $p) {
        $initial[] = [
            'index'=>(int)$p['index'], 'label'=>$p['label'], 'start_date'=>$p['start_date'],
            'end_date'=>$p['end_date'], 'progress'=>((int)$p['eligible_days'] <= 0 ? 1 : 0), 'status'=>((int)$p['eligible_days'] <= 0 ? 'restricted' : 'locked'),
            'skipped'=>((int)$p['eligible_days'] <= 0), 'trades'=>0, 'wins'=>0,
            'consecutive_losses'=>0, 'eligible_days'=>(int)$p['eligible_days'],
            'excluded_dates'=>$p['excluded_dates'], 'excluded_days'=>$p['excluded_days']
        ];
    }
    $zero = '[]';
    $daily = $period === 'daily' ? spc_encode($initial) : $zero;
    $weekly = $period === 'weekly' ? spc_encode($initial) : $zero;
    $monthly = $period === 'monthly' ? spc_encode($initial) : $zero;
    $q = $pdo->prepare("UPDATE `signals_provider_interest`
        SET `begin_test` = 1, `interest_status` = 'ongoing', `signal_testing_started_at` = ?,
            `daily_progress_json` = ?, `weekly_progress_json` = ?, `monthly_progress_json` = ?,
            `challenge_restricted_dates_json` = ?, `challenge_restricted_days_json` = ?,
            `challenge_duration_days` = ?, `challenge_end_at` = ?, `challenge_timezone` = ?,
            `challenge_total_periods` = ?, `challenge_completed_periods` = 0,
            `challenge_current_period_index` = 0, `challenge_failed_period_index` = NULL,
            `challenge_failed_at` = NULL, `challenge_failure_reason` = NULL,
            `challenge_completed_at` = NULL, `challenge_last_evaluated_at` = NULL,
            `updated_at` = CURRENT_TIMESTAMP
        WHERE `id` = ? AND `user_id` = ? AND `programme_id` = ?");
    $q->execute([
        $now->format('Y-m-d H:i:s'), $daily, $weekly, $monthly,
        spc_encode($restricted['dates']), spc_encode($restricted['days']),
        $duration, $endAt->format('Y-m-d H:i:s'), $timezone->getName(),
        $totalRequired, (int)$interest['id'], $userId, $programmeId
    ]);
    return ['success'=>true, 'message'=>'Challenge test started.', 'started_at'=>$now->format('Y-m-d H:i:s'), 'end_at'=>$endAt->format('Y-m-d H:i:s'), 'duration_days'=>$duration, 'total_periods'=>$totalRequired];
}

function spc_decodeStringList($raw): array {
    if (is_array($raw)) $decoded = $raw;
    else {
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) $decoded = [];
    }
    $out = [];
    foreach ($decoded as $value) {
        if (!is_scalar($value)) continue;
        $value = trim((string)$value);
        if ($value !== '') $out[$value] = $value;
    }
    return array_values($out);
}

function spc_evaluateProvision(PDO $pdo, int $userId, int $programmeId, array $interest, array $programme, array $serverConfig = [], string $email = ''): array {
    spc_ensureChallengeSchema($pdo);
    $period = strtolower(trim((string)($interest['trades_provision'] ?? 'daily')));
    if (!in_array($period, ['daily','weekly','monthly'], true)) $period = 'daily';

    $beginTest = (int)($interest['begin_test'] ?? 0);
    $currentStatus = strtolower(trim((string)($interest['interest_status'] ?? 'interested')));
    $alreadyFailed = in_array($currentStatus, ['failed','breached'], true);
    $alreadyPassed = $currentStatus === 'passed';
    $advertisement = (int)($programme['advertisement'] ?? 0);
    $timezoneName = trim((string)($interest['challenge_timezone'] ?? $programme['timezone'] ?? ''));
    $timezone = spc_timezone($timezoneName);
    $now = new DateTimeImmutable('now', $timezone);

    $duration = (int)($interest['challenge_duration_days'] ?? 0);
    if ($duration <= 0) $duration = spc_durationDays($period, $serverConfig);

    $startedAtRaw = trim((string)($interest['signal_testing_started_at'] ?? ''));
    $storedStartAt = $startedAtRaw !== '' ? spc_parseDateTime($startedAtRaw, $timezone) : null;
    $hasChallengeRun = ($storedStartAt !== null) && ($beginTest === 1 || $alreadyFailed || $alreadyPassed || $currentStatus === 'ongoing');
    $hasStarted = ($beginTest === 1 && $storedStartAt !== null && !$alreadyFailed);
    $previewMode = (!$hasChallengeRun && !$alreadyFailed && !$alreadyPassed && in_array($currentStatus, ['interested','pending','active'], true));

    $startAt = $hasChallengeRun ? $storedStartAt : $now->setTime(0, 0, 0);
    if (!$startAt) $startAt = $now->setTime(0, 0, 0);
    $endAt = spc_parseDateTime($interest['challenge_end_at'] ?? '', $timezone);
    if (!$endAt) $endAt = $startAt->setTime(0, 0, 0)->modify('+' . $duration . ' days');

    $snapshotDatesRaw = trim((string)($interest['challenge_restricted_dates_json'] ?? ''));
    $snapshotDaysRaw = trim((string)($interest['challenge_restricted_days_json'] ?? ''));
    $hasRestrictionSnapshot = ($hasChallengeRun && $snapshotDatesRaw !== '' && $snapshotDaysRaw !== '');
    if ($hasRestrictionSnapshot) {
        $restricted = [
            'dates' => spc_decodeStringList($snapshotDatesRaw),
            'days' => spc_decodeStringList($snapshotDaysRaw)
        ];
    } else {
        $restricted = spc_getRestrictedCalendar($pdo, $userId, $programmeId, $email);
    }

    $tracking = ['tracking_available'=>false, 'events'=>[]];
    $periods = [];
    $currentIndex = -1;
    $lookbackStart = null;
    $lookbackEnd = null;

    if ($hasChallengeRun) {
        $periods = spc_buildChallengePeriods($startAt, $period, $duration, $restricted, $now);
        foreach ($periods as $idx => $p) {
            $pStart = spc_parseDateTime($p['start_at'], $timezone);
            $pEnd = spc_parseDateTime($p['end_at'], $timezone);
            if ($pStart && $pEnd && $now >= $pStart && $now < $pEnd) { $currentIndex = $idx; break; }
        }
        if ($currentIndex < 0 && !empty($periods)) {
            foreach ($periods as $idx => $p) {
                $pStart = spc_parseDateTime($p['start_at'], $timezone);
                if ($pStart && $now < $pStart) { $currentIndex = $idx; break; }
            }
            if ($currentIndex < 0) $currentIndex = count($periods) - 1;
        }
        if ($hasStarted && !$alreadyFailed && !$alreadyPassed) {
            // Programme comparison begins at midnight of signal_testing_started_at's
            // date, so live trades earlier on the button-click date are included.
            $comparisonStart = $startAt->setTime(0, 0, 0);
            $tracking = spc_getLiveProvisionEvents($pdo, $userId, $programmeId, $comparisonStart, $endAt, $timezone);
        }
    } elseif ($previewMode) {
        // Before Begin Test, keep dummy readings live using the requested rolling
        // limits: daily=7 days, weekly=14 days, monthly=2 months. Preview is read-only.
        $lookbackStart = spc_previewLookbackStart($now, $period);
        $lookbackEnd = $now->modify('+1 second');
        $previewPeriod = spc_buildPreviewPeriod($period, $lookbackStart, $now, $restricted);
        $periods = [$previewPeriod];
        $currentIndex = 0;
        $tracking = spc_getLiveProvisionEvents($pdo, $userId, $programmeId, $lookbackStart, $lookbackEnd, $timezone);
    } else {
        // A failed/breached row retains its last challenge calendar, but its
        // checkpoint readings stay reset until the user explicitly restarts it.
        $periods = spc_buildChallengePeriods($startAt, $period, $duration, $restricted, $now);
        $currentIndex = (int)($interest['challenge_failed_period_index'] ?? 0);
        if ($currentIndex < 0 || $currentIndex >= count($periods)) $currentIndex = 0;
    }

    $minimumTrades = max(0, (int)($interest['trades_count'] ?? 0));
    $minimumWins = max(0, (int)($interest['expected_win_count'] ?? 0));
    $maximumLosses = max(0, (int)($interest['consecutive_expected_loss'] ?? 0));
    $aggregate = ['trades'=>0,'wins'=>0,'consecutive_losses'=>0];
    $failedIndex = null;
    $failureReason = trim((string)($interest['challenge_failure_reason'] ?? ''));

    foreach ($periods as $idx => &$p) {
        $pStart = spc_parseDateTime($p['start_at'] ?? '', $timezone);
        $pEnd = spc_parseDateTime($p['end_at'] ?? '', $timezone);
        $isFuture = ($pStart !== null && $now < $pStart);
        $isClosed = ($pEnd !== null && $now >= $pEnd);
        $p['locked'] = $hasChallengeRun ? $isFuture : false;
        if ($alreadyFailed) $p['locked'] = false;
        $p['status'] = $p['locked'] ? 'locked' : ($previewMode ? 'preview' : 'active');
        $p['progress'] = 0;
        $p['skipped'] = ((int)($p['eligible_days'] ?? 0) === 0);
        $stats = ['trades'=>0,'wins'=>0,'losses'=>0,'consecutive_losses'=>0];

        if (($hasStarted || $previewMode) && !$p['locked'] && !$alreadyFailed && !$alreadyPassed && !empty($tracking['tracking_available'])) {
            $stats = spc_computePeriodStats($tracking['events'], $p);
        }

        $p['trades'] = (int)$stats['trades'];
        $p['wins'] = (int)$stats['wins'];
        $p['consecutive_losses'] = (int)$stats['consecutive_losses'];
        $p['trades_passed'] = ($minimumTrades > 0 && $stats['trades'] >= $minimumTrades);
        $p['wins_passed'] = ($minimumWins > 0 && $stats['wins'] >= $minimumWins);
        $p['loss_gate_passed'] = ($maximumLosses > 0 && $stats['consecutive_losses'] < $maximumLosses);
        $p['requirements_met'] = ($p['trades_passed'] && $p['wins_passed'] && $p['loss_gate_passed']);

        $aggregate['trades'] += $stats['trades'];
        $aggregate['wins'] += $stats['wins'];
        $aggregate['consecutive_losses'] = max($aggregate['consecutive_losses'], $stats['consecutive_losses']);

        if ($alreadyFailed) {
            $p['progress'] = 0;
            $p['status'] = ((int)($interest['challenge_failed_period_index'] ?? -1) === $idx) ? 'failed' : 'reset';
            $p['trades'] = $p['wins'] = $p['consecutive_losses'] = 0;
            $p['trades_passed'] = $p['wins_passed'] = $p['loss_gate_passed'] = $p['requirements_met'] = false;
            continue;
        }

        if ($p['skipped']) {
            // Entirely restricted checkpoint: excluded from the challenge duration.
            $p['progress'] = ($hasStarted || $alreadyPassed) ? 1 : 0;
            $p['status'] = 'restricted';
            continue;
        }

        // Dummy readings can show whether the provision would currently pass,
        // but they must never fail the interest or publish the programme.
        if ($previewMode) {
            $p['status'] = 'preview';
            $p['progress'] = 0;
            continue;
        }

        if (!$hasStarted || $p['locked'] || $alreadyPassed) continue;

        if ($maximumLosses > 0 && $stats['consecutive_losses'] >= $maximumLosses) {
            $failedIndex = $idx;
            $failureReason = 'The maximum consecutive-loss limit was reached in ' . $p['label'] . '.';
            break;
        }
        if ($isClosed) {
            if ($p['requirements_met']) {
                $p['progress'] = 1;
                $p['status'] = 'passed';
            } else {
                $failedIndex = $idx;
                $failureReason = $p['label'] . ' ended without meeting the minimum trades and expected-win requirements.';
                break;
            }
        } elseif ($p['requirements_met']) {
            $p['progress'] = 1;
            $p['status'] = 'requirements_met';
        }
    }
    unset($p);

    $plannedPeriods = spc_buildChallengePeriods($startAt, $period, $duration, $restricted, $now);
    $totalRequired = 0;
    $completedRequired = 0;
    if ($hasChallengeRun) {
        foreach ($periods as $p) {
            if ((int)($p['eligible_days'] ?? 0) <= 0) continue;
            $totalRequired++;
            if ((int)($p['progress'] ?? 0) === 1) $completedRequired++;
        }
    } else {
        foreach ($plannedPeriods as $p) if ((int)($p['eligible_days'] ?? 0) > 0) $totalRequired++;
    }

    $failureStatus = $advertisement === 1 ? 'breached' : 'failed';
    $nowText = $now->format('Y-m-d H:i:s');
    if ($hasStarted && !$alreadyFailed && !$alreadyPassed && !empty($tracking['tracking_available']) && $failedIndex !== null) {
        $resetPeriods = $periods;
        foreach ($resetPeriods as &$rp) {
            $rp['progress'] = 0;
            $rp['status'] = ((int)$rp['index'] === (int)$failedIndex) ? 'failed' : 'reset';
            $rp['locked'] = false;
            $rp['trades'] = $rp['wins'] = $rp['consecutive_losses'] = 0;
            $rp['trades_passed'] = $rp['wins_passed'] = $rp['loss_gate_passed'] = $rp['requirements_met'] = false;
        }
        unset($rp);
        $zeroDaily = $period === 'daily' ? spc_encode(spc_periodStorage($resetPeriods)) : '[]';
        $zeroWeekly = $period === 'weekly' ? spc_encode(spc_periodStorage($resetPeriods)) : '[]';
        $zeroMonthly = $period === 'monthly' ? spc_encode(spc_periodStorage($resetPeriods)) : '[]';
        try {
            $u = $pdo->prepare("UPDATE `signals_provider_interest`
                SET `interest_status` = ?, `begin_test` = 0,
                    `daily_progress_json` = ?, `weekly_progress_json` = ?, `monthly_progress_json` = ?,
                    `challenge_completed_periods` = 0, `challenge_failed_period_index` = ?,
                    `challenge_failed_at` = ?, `challenge_failure_reason` = ?,
                    `challenge_current_period_index` = ?, `challenge_completed_at` = NULL,
                    `challenge_last_evaluated_at` = ?, `updated_at` = CURRENT_TIMESTAMP
                WHERE `id` = ? AND `user_id` = ? AND `programme_id` = ? AND `begin_test` = 1");
            $u->execute([$failureStatus,$zeroDaily,$zeroWeekly,$zeroMonthly,$failedIndex,$nowText,$failureReason,$failedIndex,$nowText,(int)$interest['id'],$userId,$programmeId]);
            $interest['interest_status'] = $failureStatus;
            $interest['begin_test'] = 0;
            $interest['challenge_failure_reason'] = $failureReason;
            $interest['challenge_failed_period_index'] = $failedIndex;
            $interest['challenge_failed_at'] = $nowText;
            $interest['challenge_completed_periods'] = 0;
            $periods = $resetPeriods;
            $aggregate = ['trades'=>0,'wins'=>0,'consecutive_losses'=>0];
            $completedRequired = 0;
            $alreadyFailed = true;
            $hasStarted = false;
        } catch (Throwable $e) {
            error_log('[signals challenge] failed-state persistence failed: ' . $e->getMessage());
        }
    }

    $allPeriodsDone = $hasStarted && $now >= $endAt && $failedIndex === null && !empty($tracking['tracking_available']);
    if ($allPeriodsDone) {
        foreach ($periods as $p) {
            if ((int)($p['eligible_days'] ?? 0) > 0 && (int)($p['progress'] ?? 0) !== 1) { $allPeriodsDone = false; break; }
        }
    }

    if ($hasStarted && !$alreadyFailed && !$alreadyPassed && $allPeriodsDone) {
        foreach ($periods as &$p) {
            $p['progress'] = 1;
            $p['status'] = ((int)($p['eligible_days'] ?? 0) > 0) ? 'passed' : 'restricted';
            $p['locked'] = false;
        }
        unset($p);
        try {
            spc_savePeriodProgress($pdo,(int)$interest['id'],$userId,$programmeId,$period,$periods,$totalRequired,$totalRequired,count($periods)-1,$nowText);
            $u = $pdo->prepare("UPDATE `signals_provider_interest` SET `interest_status` = 'passed', `begin_test` = 1,
                `challenge_completed_periods` = ?, `challenge_total_periods` = ?, `challenge_current_period_index` = ?,
                `challenge_completed_at` = ?, `challenge_last_evaluated_at` = ?, `updated_at` = CURRENT_TIMESTAMP
                WHERE `id` = ? AND `user_id` = ? AND `programme_id` = ? AND `begin_test` = 1");
            $u->execute([$totalRequired,$totalRequired,count($periods)-1,$nowText,$nowText,(int)$interest['id'],$userId,$programmeId]);
            $interest['interest_status'] = 'passed';
            $interest['challenge_completed_periods'] = $totalRequired;
            $interest['challenge_total_periods'] = $totalRequired;
            $interest['challenge_completed_at'] = $nowText;
            $interest['challenge_current_period_index'] = count($periods)-1;
            $u2 = $pdo->prepare("UPDATE `programme` SET `advertisement` = 1 WHERE `id` = ? AND `userid` = ?");
            $u2->execute([$programmeId,$userId]);
            $advertisement = 1;
            $alreadyPassed = true;
        } catch (Throwable $e) {
            error_log('[signals challenge] passing-state persistence failed: ' . $e->getMessage());
        }
    } elseif ($hasStarted && !$alreadyFailed && !$alreadyPassed && !empty($tracking['tracking_available'])) {
        try {
            spc_savePeriodProgress($pdo,(int)$interest['id'],$userId,$programmeId,$period,$periods,$completedRequired,$totalRequired,max(0,$currentIndex),$nowText);
            $interest['challenge_completed_periods'] = $completedRequired;
            $interest['challenge_total_periods'] = $totalRequired;
            $interest['challenge_current_period_index'] = max(0,$currentIndex);
        } catch (Throwable $e) {
            error_log('[signals challenge] progress persistence failed: ' . $e->getMessage());
        }
    }

    if ($alreadyFailed) {
        $failureReason = $failureReason !== '' ? $failureReason : 'Your challenge failed, you can restart or join a new provision challenge.';
        $failIndex = $interest['challenge_failed_period_index'] ?? $failedIndex;
        foreach ($periods as &$p) {
            $p['locked'] = false;
            $p['progress'] = 0;
            $p['status'] = ($failIndex !== null && (int)$p['index'] === (int)$failIndex) ? 'failed' : 'reset';
            $p['trades'] = $p['wins'] = $p['consecutive_losses'] = 0;
            $p['trades_passed'] = $p['wins_passed'] = $p['loss_gate_passed'] = $p['requirements_met'] = false;
        }
        unset($p);
        $aggregate = ['trades'=>0,'wins'=>0,'consecutive_losses'=>0];
        $completedRequired = 0;
    }

    if ($alreadyPassed) {
        foreach ($periods as &$p) {
            $p['locked'] = false;
            $p['progress'] = 1;
            $p['status'] = ((int)($p['eligible_days'] ?? 0) > 0) ? 'passed' : 'restricted';
        }
        unset($p);
        $completedRequired = $totalRequired;
    }

    $selectedIndex = $currentIndex;
    if (($alreadyFailed || $alreadyPassed) && !empty($periods)) {
        $selectedIndex = $alreadyFailed
            ? (int)($interest['challenge_failed_period_index'] ?? 0)
            : (count($periods) - 1);
    }
    if ($selectedIndex < 0 && !empty($periods)) $selectedIndex = 0;
    if (!empty($periods) && $selectedIndex >= count($periods)) $selectedIndex = count($periods) - 1;

    $restrictionStart = $hasChallengeRun ? $startAt : ($lookbackStart ?: spc_previewLookbackStart($now, $period));
    $restrictionEnd = $hasChallengeRun ? $endAt : ($lookbackEnd ?: $now->modify('+1 second'));
    $restrictionStartDate = $restrictionStart->format('Y-m-d');
    $restrictionEndDate = $hasChallengeRun ? $restrictionEnd->format('Y-m-d') : $now->format('Y-m-d');
    $windowRestrictedDates = array_values(array_filter($restricted['dates'] ?? [], static function ($date) use ($restrictionStartDate, $restrictionEndDate, $hasChallengeRun) {
        $date = substr(trim((string)$date), 0, 10);
        return $hasChallengeRun
            ? ($date >= $restrictionStartDate && $date < $restrictionEndDate)
            : ($date >= $restrictionStartDate && $date <= $restrictionEndDate);
    }));

    $periodStats = $periods[$selectedIndex] ?? null;
    $tradesPassed = $periodStats ? !empty($periodStats['trades_passed']) : false;
    $winsPassed = $periodStats ? !empty($periodStats['wins_passed']) : false;
    $lossGatePassed = $periodStats ? !empty($periodStats['loss_gate_passed']) : false;
    $allRequirementsMet = $periodStats ? !empty($periodStats['requirements_met']) : false;
    $durationAnchor = $hasChallengeRun ? $startAt : $now->setTime(0, 0, 0);
    $eligibleDurationDays = spc_countEligibleDays($durationAnchor, $duration, $restricted);
    $durationDescription = spc_durationDescription($period, $duration, $eligibleDurationDays);
    $begun = $hasChallengeRun || $beginTest === 1;

    return [
        'interest'=>$interest, 'stats'=>$aggregate,
        'minimum_trades'=>$minimumTrades, 'minimum_wins'=>$minimumWins,
        'maximum_consecutive_losses'=>$maximumLosses,
        'trades_passed'=>$tradesPassed, 'wins_passed'=>$winsPassed,
        'loss_gate_passed'=>$lossGatePassed, 'all_requirements_met'=>$allRequirementsMet,
        'loss_limit_reached'=>($alreadyFailed || ($periodStats && $maximumLosses > 0 && (int)$periodStats['consecutive_losses'] >= $maximumLosses)),
        'read_only'=>!$hasStarted, 'begin_test'=>(int)($interest['begin_test'] ?? 0),
        'challenge_begun'=>$begun,
        'challenge_begun_label'=>$begun ? 'Provision challenge has begun' : 'Provision challenge hasn\'t begun',
        'challenge_preview'=>$previewMode,
        'advertisement'=>$advertisement, 'programme_advertised'=>$advertisement === 1 && (int)($programme['server_advertisement_approval'] ?? 0) === 1,
        'period'=>$period, 'duration_days'=>$duration, 'duration_period_count'=>spc_periodCount($period, $duration),
        'duration_eligible_days'=>$eligibleDurationDays, 'duration_description'=>$durationDescription,
        'timezone'=>$timezone->getName(),
        'tracking_available'=>!empty($tracking['tracking_available']),
        'periods'=>$periods, 'current_period_index'=>$selectedIndex,
        'total_periods'=>$totalRequired, 'completed_periods'=>$completedRequired,
        'failure_reason'=>$alreadyFailed ? $failureReason : '',
        'failed_period_index'=>$alreadyFailed ? ($interest['challenge_failed_period_index'] ?? $failedIndex) : null,
        'challenge_started_at'=>$hasChallengeRun ? $startAt->format('Y-m-d H:i:s') : null,
        'challenge_end_at'=>$hasChallengeRun ? $endAt->format('Y-m-d H:i:s') : null,
        'lookback_start'=>$previewMode && $lookbackStart ? $lookbackStart->format('Y-m-d H:i:s') : null,
        'lookback_end'=>$previewMode && $lookbackEnd ? $lookbackEnd->format('Y-m-d H:i:s') : null,
        'restricted_dates'=>$windowRestrictedDates, 'restricted_days'=>$restricted['days'],
        'failed'=>$alreadyFailed, 'passed'=>$alreadyPassed
    ];
}

// Run the additive migration once per request before AJAX handlers or page rendering.
spc_ensureChallengeSchema($pdo);


function generateRealisticSystemChallenges($period) {
    $rows = [];
    $now  = date('Y-m-d H:i:s');

    $mk = function ($rr, $tc, $win, $loss, $type) use ($period, $now) {
        return [
            'id' => 0, 'user_id' => 0, 'account_management_id' => 0, 'programme_id' => 0,
            'risk_reward_type' => $type,
            'risk_reward' => $rr,
            'trades_provision' => $period,
            'consecutive_loss' => $loss,
            'trades_count' => $tc,
            'expected_win' => $win,
            'source' => 'system', 'is_active' => 1, 'created_at' => $now,
            'username' => 'System', 'first_name' => 'System', 'last_name' => '',
            'fullname' => 'System Provision', 'user_email' => '',
        ];
    };

    $types = ['fixed_risk_reward', 'minimum_risk_reward', 'custom_and_default_risk_reward'];
    $seen  = [];

    for ($rr = 1; $rr <= 20; $rr++) {
        $tcs = array_unique([
            max(1, min(100, $rr)),
            max(1, min(100, (int)round($rr * 1.5))),
            max(1, min(100, (int)round($rr / 2))),
            10, 20, 30, 50, 100,
        ]);
        sort($tcs);

        foreach ($tcs as $tc) {
            if ($tc < 1) continue;

            $shapes = [
                ['win' => $tc, 'loss' => max(1, $tc - 1)],
                ['win' => max(1, (int)ceil($tc / 2)), 'loss' => max(1, (int)ceil($tc / 2))],
                ['win' => 1, 'loss' => 1],
                ['win' => 1, 'loss' => max(1, $tc - 1)],
            ];

            foreach ($shapes as $s) {
                if (!isProfitableOrBreakeven($s['win'], $s['loss'], $rr)) continue;

                foreach ($types as $type) {
                    $key = "$rr|$tc|{$s['win']}|{$s['loss']}|$type";
                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $rows[] = $mk($rr, $tc, $s['win'], $s['loss'], $type);
                }
            }
        }
    }

    for ($rr = 21; $rr <= 100; $rr++) {
        $tc = max(1, min(100, $rr));
        $shapes = [
            ['win' => $tc, 'loss' => max(1, $tc - 1)],
            ['win' => 1,   'loss' => 1],
        ];
        foreach ($shapes as $s) {
            if (!isProfitableOrBreakeven($s['win'], $s['loss'], $rr)) continue;
            foreach ($types as $type) {
                $key = "$rr|$tc|{$s['win']}|{$s['loss']}|$type";
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $rows[] = $mk($rr, $tc, $s['win'], $s['loss'], $type);
            }
        }
    }

    usort($rows, function ($a, $b) {
        if ($a['risk_reward'] != $b['risk_reward']) return $a['risk_reward'] - $b['risk_reward'];
        if ($a['trades_count'] != $b['trades_count']) return $a['trades_count'] - $b['trades_count'];
        return $b['expected_win'] - $a['expected_win'];
    });

    return $rows;
}

function synthesizeFilteredCard(string $type, int $rr, int $trades, int $win, int $loss, string $period) {
    if ($trades < 1) return null;
    if ($win < 1 || $win > $trades) return null;
    if ($loss < 1 || $loss >= $trades) return null;
    if ($rr < 1) return null;
    if (!isProfitableOrBreakeven($win, $loss, $rr)) return null;

    return [
        'id' => 0, 'user_id' => 0, 'account_management_id' => 0, 'programme_id' => 0,
        'risk_reward_type' => $type,
        'risk_reward' => $rr,
        'trades_provision' => $period,
        'consecutive_loss' => $loss,
        'trades_count' => $trades,
        'expected_win' => $win,
        'source' => 'system', 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'),
        'username' => 'System', 'first_name' => 'System', 'last_name' => '',
        'fullname' => 'System Provision', 'user_email' => '',
    ];
}

// ==================== AJAX ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'get_requests') {
        $period = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        try {
            $systemRows = generateRealisticSystemChallenges($period);
            jres(['success' => true, 'requests' => $systemRows]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load requests: ' . $e->getMessage()]);
        }
    }

    if ($action === 'filter_requests') {
        $period = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        $type   = trim($_POST['risk_reward_type'] ?? '');
        $rr     = isset($_POST['risk_reward'])     && $_POST['risk_reward']     !== '' ? (int)$_POST['risk_reward']     : null;
        $trades = isset($_POST['trades_count'])    && $_POST['trades_count']    !== '' ? (int)$_POST['trades_count']    : null;
        $win    = isset($_POST['expected_win'])    && $_POST['expected_win']    !== '' ? (int)$_POST['expected_win']    : null;
        $loss   = isset($_POST['consecutive_loss'])&& $_POST['consecutive_loss']!== '' ? (int)$_POST['consecutive_loss']: null;

        $allowedTypes = ['fixed_risk_reward', 'minimum_risk_reward', 'custom_and_default_risk_reward'];
        if (!in_array($type, $allowedTypes, true)) {
            jres(['success' => false, 'filter_error' => true, 'message' => 'Please select a risk reward type.']);
        }

        if ($rr === null || $trades === null || $win === null || $loss === null) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Fill in all fields: risk reward, trades count, expected win, and consecutive loss.'
            ]);
        }

        if ($win > $trades) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Expected win (' . $win . ') cannot be greater than trades count (' . $trades . ').'
            ]);
        }
        if ($loss >= $trades) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Consecutive loss (' . $loss . ') must be less than trades count (' . $trades . ') — otherwise you would be blown.'
            ]);
        }
        if (!isProfitableOrBreakeven($win, $loss, $rr)) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'This combination is not profitable or breakeven. '
                           . 'For a 1:' . $rr . ' risk reward with ' . $win . ' winning trade(s) and '
                           . $loss . ' consecutive loss(es), the edge would be negative. '
                           . 'Profitable/breakeven rule: expected_win × rr ≥ consecutive_loss ('
                           . $win . ' × ' . $rr . ' = ' . ($win * $rr) . ' must be ≥ ' . $loss . ').'
            ]);
        }

        $card = synthesizeFilteredCard($type, $rr, $trades, $win, $loss, $period);
        if ($card === null) {
            jres([
                'success' => false,
                'filter_error' => true,
                'message' => 'Could not generate this configuration.'
            ]);
        }

        jres(['success' => true, 'requests' => [$card]]);
    }

    if ($action === 'save_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        $requestId = (int)($_POST['request_id'] ?? 0);
        $period    = trim($_POST['period'] ?? 'daily');
        if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

        $allowedTypes = ['fixed_risk_reward', 'minimum_risk_reward', 'custom_and_default_risk_reward'];
        $submittedType = (string)($_POST['risk_reward_type'] ?? 'fixed_risk_reward');
        if (!in_array($submittedType, $allowedTypes, true)) {
            jres(['success' => false, 'message' => 'Invalid risk-to-reward type.']);
        }
        $submittedRr = max(1.0, (float)($_POST['risk_reward'] ?? 1));
        $request = [
            'risk_reward_type' => $submittedType,
            'risk_reward'      => $submittedRr,
            'trades_provision' => $period,
            'consecutive_loss' => (int)($_POST['consecutive_loss'] ?? 2),
            'trades_count'     => (int)($_POST['trades_count'] ?? 1),
            'expected_win'     => (int)($_POST['expected_win'] ?? 1),
        ];

        $programmeId = (int)$programme['id'];
        $interestId  = 0;
        $actionTaken = 'interested';
        $wasInserted = false;

        try {
            $pdo->beginTransaction();

            $eq = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                  AND interest_status IN ('interested','pending','active','ongoing')
                ORDER BY id DESC
                LIMIT 1
                FOR UPDATE
            ");
            $eq->execute([$userId, $programmeId]);
            $existing = $eq->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($existing) {
                $sameShape =
                    ((float)$existing['risk_reward']             === (float)$request['risk_reward']) &&
                    ((string)$existing['risk_reward_type']       === (string)$request['risk_reward_type']) &&
                    ((int)$existing['consecutive_expected_loss'] === (int)$request['consecutive_loss']) &&
                    ((int)$existing['trades_count']              === (int)$request['trades_count']) &&
                    ((int)$existing['expected_win_count']        === (int)$request['expected_win']) &&
                    ((string)$existing['trades_provision']       === (string)$request['trades_provision']);

                if ($sameShape) {
                    $interestId  = (int)$existing['id'];
                    $actionTaken = 'interested';
                    $wasInserted = false;
                } else {
                    $pdo->rollBack();
                    jres([
                        'success' => false,
                        'message' => 'You already have an active provision interest for this programme. The current provision must be cancelled before choosing another.'
                    ]);
                }
            } else {
                if (!spiHasPartialUniqueKey($pdo)) {
                    try {
                        $del = $pdo->prepare("
                            DELETE FROM signals_provider_interest
                            WHERE user_id = ?
                              AND programme_id = ?
                              AND request_id = ?
                              AND accountmanagement_id = 0
                              AND interest_status NOT IN ('interested','pending','active','ongoing')
                        ");
                        $del->execute([$userId, $programmeId, $requestId]);
                    } catch (Throwable $delErr) {
                        error_log('[signals_provision_request] stale-row cleanup failed: ' . $delErr->getMessage());
                    }
                }

                $ins = $pdo->prepare("
                    INSERT INTO signals_provider_interest
                        (user_id, programme_id, accountmanagement_id, request_id,
                         risk_reward, risk_reward_type,
                         consecutive_expected_loss, trades_count, expected_win_count,
                         trades_provision, interest_status, begin_test, signal_testing_started_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'interested', 0, NULL)
                ");
                $ins->execute([
                    $userId,
                    $programmeId,
                    0,
                    $requestId,
                    $request['risk_reward'],
                    $request['risk_reward_type'],
                    $request['consecutive_loss'],
                    $request['trades_count'],
                    $request['expected_win'],
                    $request['trades_provision'],
                ]);

                $interestId  = (int)$pdo->lastInsertId();
                $actionTaken = 'interested';
                $wasInserted = true;
            }

            try {
                $logIns = $pdo->prepare("
                    INSERT INTO signals_request_file (user_id, programme_id, request_id, action, snapshot_data)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $logIns->execute([
                    $userId,
                    $programmeId,
                    $requestId,
                    $actionTaken,
                    json_encode($request)
                ]);
            } catch (Throwable $logErr) {
                error_log('[signals_provision_request] audit log failed: ' . $logErr->getMessage());
            }

            $pdo->commit();

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $ignored) {}
            }

            $msg = $e->getMessage();
            if (strpos($msg, 'uq_spi') !== false || strpos($msg, 'Duplicate entry') !== false) {
                jres([
                    'success' => false,
                    'message' => 'A provision interest for this programme already exists. Cancel the current provision before choosing another.'
                ]);
            }

            jres(['success' => false, 'message' => 'Failed to save interest: ' . $msg]);
        }

        /* ------------------------------------------------
         * 4) Notification — outside the transaction.
         *    Uses a timestamped key so every join attempt
         *    produces a fresh in-app row + email, even on
         *    idempotent re-clicks of the same challenge.
         * ------------------------------------------------ */

        try {
            $programmeName = trim((string)($programme['program_name'] ?? ''));
            if ($programmeName === '') $programmeName = 'Programme #' . $programmeId;

            $periodLabel = ucfirst($period);

            $joinMessage = 'Your programme ' . $programmeName . ' has joined a ' . $periodLabel . ' signals provision.';

            recordProgrammeNotification($pdo, $programmeId, $email, [
                'notification_key' => 'prog-signals-interest-' . $programmeId . '-' . $interestId . '-' . date('YmdHis'),
                'title'            => 'Provision Joined',
                'message'          => $joinMessage,
                'type'             => 'success',
                'section'          => 'Signals',
                'action_tab'       => 'signals_provision_request',
                'force'            => true
            ]);
        } catch (Throwable $notifErr) {
            error_log('[signals_provision_request] join notification failed: ' . $notifErr->getMessage());
        }

        jres([
            'success'     => true,
            'message'     => 'Provision interest saved.',
            'interest_id' => $interestId
        ]);
    }

    if ($action === 'check_my_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme.']);
        try {
            $q = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                  AND interest_status IN ('interested','pending','active','ongoing')
                ORDER BY id DESC
                LIMIT 1
            ");
            $q->execute([$userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($interest) {
                $evaluation = spc_evaluateProvision($pdo, $userId, (int)$programme['id'], $interest, $programme, $serverConfig, $email);
                $interest = $evaluation['interest'];
            }
            jres(['success' => true, 'interest' => $interest]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to check interest.']);
        }
    }

    if ($action === 'get_my_interests') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        try {
            $q = $pdo->prepare("
                SELECT *
                FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                ORDER BY id DESC
            ");
            $q->execute([$userId, (int)$programme['id']]);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);

            $out = [];
            $r = $rows[0] ?? null;
            if ($r) {
                $status = strtolower((string)($r['interest_status'] ?? 'interested'));
                if (in_array($status, ['interested', 'pending', 'active', 'ongoing', 'passed', 'failed', 'breached'], true)) {
                    $evaluation = spc_evaluateProvision($pdo, $userId, (int)$programme['id'], $r, $programme, $serverConfig, $email);
                    $r = $evaluation['interest'];
                    $programme['advertisement'] = (int)$evaluation['advertisement'];
                    $status = strtolower((string)($r['interest_status'] ?? $status));

                    $period = strtolower((string)($r['trades_provision'] ?? 'daily'));
                    if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';

                    $out[] = [
                        'id'                     => (int)$r['id'],
                        'risk_reward_type'       => (string)($r['risk_reward_type'] ?? 'fixed_risk_reward'),
                        'risk_reward'            => (float)($r['risk_reward'] ?? 0),
                        'trades_provision'       => $period,
                        'consecutive_loss'       => (int)($r['consecutive_expected_loss'] ?? 0),
                        'trades_count'           => (int)($r['trades_count'] ?? 0),
                        'expected_win'           => (int)($r['expected_win_count'] ?? 0),
                        'interest_status'        => $status,
                        'begin_test'             => (int)($r['begin_test'] ?? 0),
                        'created_at'             => (string)($r['created_at'] ?? ''),
                        'live_tracking_available'=> !empty($evaluation['tracking_available']),
                        'live_trades_count'      => (int)($evaluation['stats']['trades'] ?? 0),
                        'live_wins_count'        => (int)($evaluation['stats']['wins'] ?? 0),
                        'live_consecutive_losses'=> (int)($evaluation['stats']['consecutive_losses'] ?? 0),
                        'minimum_trades_passed'  => !empty($evaluation['trades_passed']),
                        'minimum_wins_passed'    => !empty($evaluation['wins_passed']),
                        'loss_gate_passed'       => !empty($evaluation['loss_gate_passed']),
                        'provision_passed'       => !empty($evaluation['all_requirements_met']),
                        'provision_read_only'    => !empty($evaluation['read_only']),
                        'challenge_periods'      => $evaluation['periods'] ?? [],
                        'challenge_current_period_index' => (int)($evaluation['current_period_index'] ?? 0),
                        'challenge_total_periods' => (int)($evaluation['total_periods'] ?? 0),
                        'challenge_completed_periods' => (int)($evaluation['completed_periods'] ?? 0),
                        'challenge_progress_percent' => ((int)($evaluation['total_periods'] ?? 0) > 0 ? round(((int)($evaluation['completed_periods'] ?? 0) / (int)$evaluation['total_periods']) * 100, 1) : 0),
                        'challenge_failure_reason' => (string)($evaluation['failure_reason'] ?? ''),
                        'challenge_failed_period_index' => $evaluation['failed_period_index'] ?? null,
                        'challenge_started_at'    => $evaluation['challenge_started_at'] ?? null,
                        'challenge_end_at'        => $evaluation['challenge_end_at'] ?? null,
                        'challenge_duration_days' => (int)($evaluation['duration_days'] ?? 0),
                        'challenge_duration_period_count' => (int)($evaluation['duration_period_count'] ?? 0),
                        'challenge_duration_eligible_days' => (int)($evaluation['duration_eligible_days'] ?? 0),
                        'challenge_duration_description' => (string)($evaluation['duration_description'] ?? ''),
                        'challenge_begun' => !empty($evaluation['challenge_begun']),
                        'challenge_begun_label' => (string)($evaluation['challenge_begun_label'] ?? 'Provision challenge hasn\'t begun'),
                        'challenge_preview' => !empty($evaluation['challenge_preview']),
                        'challenge_lookback_description' => (string)($evaluation['lookback_description'] ?? ''),
                        'challenge_lookback_start' => $evaluation['lookback_start'] ?? null,
                        'challenge_lookback_end' => $evaluation['lookback_end'] ?? null,
                        'challenge_timezone'      => (string)($evaluation['timezone'] ?? ''),
                        'challenge_restricted_dates' => $evaluation['restricted_dates'] ?? [],
                        'challenge_restricted_days' => $evaluation['restricted_days'] ?? [],
                        'challenge_failed'        => !empty($evaluation['failed']),
                        'challenge_tracking_available' => !empty($evaluation['tracking_available']),
                    ];
                }
            }

            jres([
                'success' => true,
                'interests' => $out,
                'programme_is_advertised' => ((int)($programme['advertisement'] ?? 0) === 1
                    && (int)($programme['server_advertisement_approval'] ?? 0) === 1)
            ]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to load interests: ' . $e->getMessage()]);
        }
    }

    if ($action === 'begin_test') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);
        // An already-advertised programme does not need a test; skip all test/readiness checks.
        if ((int)($programme['advertisement'] ?? 0) === 1 && (int)($programme['server_advertisement_approval'] ?? 0) === 1) {
            jres(['success' => true, 'already_advertised' => true, 'message' => 'This programme is already advertised. No provision test is required.']);
        }
        try {
            $q = $pdo->prepare("
                SELECT * FROM signals_provider_interest
                WHERE user_id = ? AND programme_id = ?
                  AND interest_status IN ('interested','pending','active','ongoing')
                ORDER BY id DESC LIMIT 1
            ");
            $q->execute([$userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$interest) jres(['success' => false, 'message' => 'No provision interest found.']);
            if ((int)($interest['begin_test'] ?? 0) === 1 || strtolower((string)($interest['interest_status'] ?? '')) === 'ongoing') {
                jres(['success' => false, 'message' => 'The provision test has already begun.']);
            }
            $readiness = getProgrammeTrainingReadiness($pdo, $userId, (int)$programme['id']);
            if (empty($readiness['has_root'])) {
                jres(['success' => false, 'message' => 'Train your programme before beginning the test.']);
            }
            if (empty($readiness['trades_configured'])) {
                jres(['success' => false, 'message' => 'You haven’t configured your programme for trading yet.']);
            }
            $hasVps = false;
            try {
                $v = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
                $v->execute([(int)$programme['id']]);
                $hasVps = (bool)$v->fetchColumn();
            } catch (Throwable $e) {}
            if (!$hasVps) {
                try {
                    $v = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
                    $v->execute([(int)$programme['id']]);
                    $hasVps = (bool)$v->fetchColumn();
                } catch (Throwable $e) {}
            }
            $hasBroker = !empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']);
            if (!$hasVps || !$hasBroker) {
                jres(['success' => false, 'message' => 'Connect your ' . (!$hasVps ? 'VPS' : 'broker') . ' to begin testing your programme.']);
            }
            $testStart = spc_startProvisionTest($pdo, $userId, (int)$programme['id'], $interest, $programme, $serverConfig, $email);
            if (empty($testStart['success'])) {
                jres(['success' => false, 'message' => (string)($testStart['message'] ?? 'Unable to start the challenge test.')]);
            }
            $refresh = $pdo->prepare('SELECT * FROM signals_provider_interest WHERE id = ? AND user_id = ? AND programme_id = ? LIMIT 1');
            $refresh->execute([(int)$interest['id'], $userId, (int)$programme['id']]);
            $freshInterest = $refresh->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($freshInterest) spc_evaluateProvision($pdo, $userId, (int)$programme['id'], $freshInterest, $programme, $serverConfig, $email);
            try {
                recordProgrammeNotification($pdo, (int)$programme['id'], $email, [
                    'notification_key' => 'signals-test-started-' . (int)$programme['id'] . '-' . (int)$interest['id'] . '-' . date('YmdHis'),
                    'title' => 'Provision Test Started',
                    'message' => 'Your signals provision test has begun.',
                    'type' => 'success',
                    'section' => 'Signals',
                    'action_tab' => 'signals',
                    'force' => true
                ]);
            } catch (Throwable $e) {}
            jres(['success' => true, 'message' => 'Test started.', 'challenge' => $testStart]);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to start test.']);
        }
    }

    if ($action === 'restart_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);
        $interestId = (int)($_POST['interest_id'] ?? 0);
        if ($interestId <= 0) jres(['success' => false, 'message' => 'Invalid provision.']);
        try {
            $q = $pdo->prepare("SELECT * FROM signals_provider_interest WHERE id = ? AND user_id = ? AND programme_id = ? LIMIT 1");
            $q->execute([$interestId, $userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$interest) jres(['success' => false, 'message' => 'Provision interest not found.']);
            if (strtolower((string)($interest['interest_status'] ?? '')) !== 'failed') {
                jres(['success' => false, 'message' => 'Only a failed challenge can be restarted.']);
            }
            $readiness = getProgrammeTrainingReadiness($pdo, $userId, (int)$programme['id']);
            if (empty($readiness['has_root'])) jres(['success' => false, 'message' => 'Train your programme before restarting the challenge.']);
            if (empty($readiness['trades_configured'])) jres(['success' => false, 'message' => 'You haven’t configured your programme for trading yet.']);
            $hasVps = false;
            try {
                $v = $pdo->prepare("SELECT id FROM programme_vps WHERE programme_id = ? LIMIT 1");
                $v->execute([(int)$programme['id']]);
                $hasVps = (bool)$v->fetchColumn();
            } catch (Throwable $e) {}
            if (!$hasVps) {
                try {
                    $v = $pdo->prepare("SELECT id FROM programme_vps_hosts_followers WHERE follower_programme_id = ? AND host_status = 'active' LIMIT 1");
                    $v->execute([(int)$programme['id']]);
                    $hasVps = (bool)$v->fetchColumn();
                } catch (Throwable $e) {}
            }
            $hasBroker = !empty($programme['broker']) && !empty($programme['server']) && !empty($programme['login']);
            if (!$hasVps || !$hasBroker) jres(['success' => false, 'message' => 'Connect your ' . (!$hasVps ? 'VPS' : 'broker') . ' to restart the challenge.']);
            $restart = spc_startProvisionTest($pdo, $userId, (int)$programme['id'], $interest, $programme, $serverConfig, $email);
            if (empty($restart['success'])) jres(['success' => false, 'message' => (string)($restart['message'] ?? 'Unable to restart the challenge.')]);
            try {
                recordProgrammeNotification($pdo, (int)$programme['id'], $email, [
                    'notification_key' => 'signals-test-restarted-' . (int)$programme['id'] . '-' . $interestId . '-' . date('YmdHis'),
                    'title' => 'Challenge Restarted',
                    'message' => 'Your failed signals provision challenge has been restarted.',
                    'type' => 'success', 'section' => 'Signals', 'action_tab' => 'signals', 'force' => true
                ]);
            } catch (Throwable $e) {}
            jres(['success' => true, 'message' => 'Your challenge has been restarted.', 'challenge' => $restart]);
        } catch (Throwable $e) {
            error_log('[signals_provision_request] restart challenge failed: ' . $e->getMessage());
            jres(['success' => false, 'message' => 'Failed to restart challenge.']);
        }
    }

    if ($action === 'cancel_interest') {
        if (!$programme) jres(['success' => false, 'message' => 'No programme selected.']);

        $interestId = (int)($_POST['interest_id'] ?? 0);
        if ($interestId <= 0) jres(['success' => false, 'message' => 'Invalid provision.']);

        try {
            $q = $pdo->prepare("SELECT * FROM signals_provider_interest WHERE id = ? AND user_id = ? AND programme_id = ? LIMIT 1");
            $q->execute([$interestId, $userId, (int)$programme['id']]);
            $interest = $q->fetch(PDO::FETCH_ASSOC);

            if (!$interest) {
                jres(['success' => false, 'message' => 'Provision interest not found.']);
            }

            $status = strtolower((string)($interest['interest_status'] ?? ''));
            if (in_array($status, ['cancelled', 'passed'], true)) {
                jres(['success' => false, 'message' => 'This provision interest can no longer be cancelled.']);
            }

            $upd = $pdo->prepare("
                UPDATE signals_provider_interest
                SET interest_status = 'cancelled', begin_test = 0,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND user_id = ? AND programme_id = ?
            ");
            $upd->execute([$interestId, $userId, (int)$programme['id']]);

            try {
                $logIns = $pdo->prepare("
                    INSERT INTO signals_request_file (user_id, programme_id, request_id, action, snapshot_data)
                    VALUES (?, ?, ?, 'cancelled', ?)
                ");
                $logIns->execute([
                    $userId,
                    (int)$programme['id'],
                    (int)($interest['request_id'] ?? 0),
                    json_encode([
                        'interest_id' => $interestId,
                        'previous_status' => $status
                    ])
                ]);
            } catch (Throwable $e) {}

            $programmeName = trim((string)($programme['program_name'] ?? ''));
            if ($programmeName === '') $programmeName = 'Programme #' . (int)$programme['id'];

            $period = strtolower((string)($interest['trades_provision'] ?? 'daily'));
            if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) $period = 'daily';
            $periodLabel = ucfirst($period);

            $cancelMessage = 'Your programme ' . $programmeName . ' has cancelled its ' . $periodLabel . ' signals provision.';

            try {
                recordProgrammeNotification($pdo, (int)$programme['id'], $email, [
                    'notification_key' => 'prog-signals-cancelled-' . (int)$programme['id'] . '-' . $interestId . '-' . date('YmdHis'),
                    'title'            => 'Provision Cancelled',
                    'message'          => $cancelMessage,
                    'type'             => 'warning',
                    'section'          => 'Signals',
                    'action_tab'       => 'signals_provision_request',
                    'force'            => true
                ]);
            } catch (Throwable $notifErr) {
                error_log('[signals_provision_request] cancel notification failed: ' . $notifErr->getMessage());
            }

            jres(['success' => true, 'message' => 'Provision interest cancelled successfully.']);
        } catch (Throwable $e) {
            jres(['success' => false, 'message' => 'Failed to cancel interest: ' . $e->getMessage()]);
        }
    }

    jres(['success' => false, 'message' => 'Unknown action.']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
<title>Signals Provision Request</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    :root{
        --spr-bg: var(--bg, #f4f8f7); --spr-card: var(--bg-card, #ffffff); --spr-text: var(--text, #12211d);
        --spr-muted: var(--text-muted, #60736d); --spr-accent: var(--accent, #12a36b); --spr-accent-2: var(--accent-hover, #0b7d52);
        --spr-soft: var(--accent-light, #e3f6ee); --spr-border: var(--border-color, #dfe9e5);
        --spr-danger: var(--danger, #df4e4e); --spr-warning: var(--warning, #b87513);
        --spr-success: var(--success, #2ecc71); --spr-info: var(--info, #3498db);
        --spr-shadow: var(--shadow, 0 2px 12px rgba(18,33,29,.06)); --spr-radius: var(--radius, 16px);
        --spr-page-pad: 20px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        background: var(--spr-bg); color: var(--spr-text);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.55; -webkit-tap-highlight-color: transparent;
        min-height: 100vh;
    }
    body::-webkit-scrollbar { display: none; }
    body.dark-mode { --spr-bg:#0c1311; --spr-card:#131d1a; --spr-text:#e8f2ee; --spr-muted:#93a8a1; --spr-soft:#12271f; --spr-border:#22322d; --spr-shadow: 0 10px 30px rgba(0,0,0,.4); }

    body.spr-scroll-locked { overflow: hidden; }

    .spr-page {
        max-width: 1100px;
        margin: 0 auto;
        width: 100%;
        display: flex;
        flex-direction: column;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
    }

    .spr-fixed-header {
        flex: 0 0 auto;
        background: var(--spr-bg);
        z-index: 20;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    body.dark-mode .spr-fixed-header { box-shadow: 0 1px 3px rgba(0,0,0,0.4); }

    .spr-header {
        padding: calc(env(safe-area-inset-top, 0px) + 14px) var(--spr-page-pad) 0;
        display: flex; align-items: center; justify-content: center; position: relative;
        max-width: 1100px; margin: 0 auto; width: 100%;
    }
    .spr-header-back {
        position: absolute; left: var(--spr-page-pad); top: 50%; transform: translateY(-50%);
        background: transparent; border: none; color: var(--spr-text); font-size: 1.2rem;
        cursor: pointer; padding: 8px; width: 40px; height: 40px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .spr-header-title { font-size: 1.15rem; font-weight: 800; }

    .spr-main-tabs {
        display: flex; gap: 4px; border-bottom: 1px solid var(--spr-border);
        margin: 0 auto; padding: 0 var(--spr-page-pad);
        overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;
        max-width: 1100px; width: 100%;
    }
    .spr-main-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .spr-main-tab {
        flex: 1 1 0; min-width: max-content; background: transparent; border: none;
        color: var(--spr-muted); padding: 12px 8px; font-weight: 700; font-size: .88rem;
        cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        margin-bottom: -1px; font-family: inherit; text-align: center;
    }
    .spr-main-tab.active { color: var(--spr-accent); border-bottom-color: var(--spr-accent); }

    .spr-sub-tabs {
        display: flex; gap: 4px;
        margin: 0 auto; padding: 0 var(--spr-page-pad);
        overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;
        max-width: 1100px; width: 100%;
    }
    .spr-sub-tabs::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .spr-sub-tab {
        flex: 1 1 0; min-width: max-content; background: transparent; border: none;
        color: var(--spr-muted); padding: 10px 8px; font-weight: 700; font-size: .82rem;
        cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap;
        margin-bottom: -1px; font-family: inherit; text-align: center;
        transition: color .18s, border-color .18s;
    }
    .spr-sub-tab.active { color: var(--spr-accent); border-bottom-color: var(--spr-accent); }

    .spr-sub-tabs[hidden] { display: none; }

    .spr-filter-float {
        position: fixed;
        top: calc(env(safe-area-inset-top, 0px) + 139px);
        left: 50%; transform: translateX(-50%);
        z-index: 9999; background: transparent; border: none;
        padding: 0 var(--spr-page-pad); width: 100%; max-width: 1100px;
        display: flex; justify-content: space-between; align-items: center;
        pointer-events: none;
    }
    .spr-filter-pill,
    .spr-cancel-pill {
        pointer-events: auto; display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 18px; border-radius: 99px; background: var(--spr-card);
        border: 1px solid var(--spr-border); color: var(--spr-text);
        font-weight: 800; font-size: .82rem; cursor: pointer; font-family: inherit;
        box-shadow: var(--spr-shadow); transition: color .18s, transform .15s, background .18s;
    }
    .spr-filter-pill i, .spr-cancel-pill i { font-size: 1rem; }
    .spr-filter-pill:hover { color: var(--spr-accent); background: var(--spr-soft); }
    .spr-filter-pill:active { transform: scale(.97); }
    .spr-cancel-pill { color: var(--spr-danger); }
    .spr-cancel-pill:hover { color: #fff; background: var(--spr-danger); border-color: var(--spr-danger); }
    .spr-cancel-pill:active { transform: scale(.97); }
    .spr-cancel-pill[hidden] { display: none; }
    body.dark-mode .spr-filter-pill { background: var(--spr-card); border-color: var(--spr-border); color: #e8f2ee; }
    body.dark-mode .spr-filter-pill:hover { color: var(--spr-accent); background: var(--spr-soft); }
    body.dark-mode .spr-cancel-pill { background: var(--spr-card); border-color: var(--spr-border); color: #ff9c9c; }
    body.dark-mode .spr-cancel-pill:hover { color: #fff; background: var(--spr-danger); border-color: var(--spr-danger); }

    .spr-scroll-area {
        flex: 1 1 auto; overflow-y: auto; overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        padding: 56px var(--spr-page-pad) 100px;
        max-width: 1100px; margin: 0 auto; width: 100%;
    }

    .spr-list { display: flex; flex-direction: column; gap: 12px; }

    .spr-item {
        background: var(--spr-card); border: 1px solid var(--spr-border);
        border-radius: var(--spr-radius); padding: 16px 20px;
        display: flex; align-items: center; justify-content: space-between;
        gap: 16px; box-shadow: var(--spr-shadow);
    }

    .spr-item-main { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 0; }
    .spr-item-header { display: flex; flex-direction: column; gap: 2px; }
    .spr-item-title { font-size: 1rem; font-weight: 800; color: var(--spr-text); word-break: break-word; line-height: 1.25; }
    .spr-item-sub { font-size: .78rem; color: var(--spr-muted); }
    .spr-challenge-begun-indicator { margin-top:3px; font-size:.72rem; font-weight:800; color:var(--spr-info); }
    .spr-duration-provision { margin-top:5px; max-width:850px; color:var(--spr-muted); font-size:.72rem; line-height:1.5; }

    .spr-meta-row {
        display: flex; flex-wrap: wrap; align-items: baseline;
        gap: 8px 22px; margin-top: 2px; width: 100%;
    }

    .spr-meta-item { display: flex; align-items: baseline; gap: 5px; font-size: .84rem; line-height: 1.4; white-space: normal; flex: 0 1 auto; max-width: 100%; word-break: break-word; }
    .spr-meta-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .4px; color: var(--spr-muted); font-weight: 700; white-space: nowrap; }
    .spr-meta-value { font-size: .88rem; font-weight: 700; color: var(--spr-text); word-break: break-word; }

    .spr-progress-list { display:grid; gap:10px; width:100%; margin-top:9px; }
    .spr-progress-item { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:7px 10px; align-items:center; width:100%; }
    .spr-progress-heading { grid-column:1/-1; display:flex; align-items:baseline; justify-content:space-between; gap:12px; min-width:0; }
    .spr-progress-label { color:var(--spr-muted); font-size:.71rem; font-weight:800; letter-spacing:.025em; }
    .spr-progress-count { color:var(--spr-text); font-size:.75rem; font-weight:850; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .spr-progress-track { height:9px; min-width:0; width:100%; border-radius:999px; overflow:hidden; background:rgba(120,135,145,.18); position:relative; }
    .spr-progress-fill { height:100%; width:0; border-radius:999px; background:var(--spr-info); transition:width .35s ease,background .25s ease; }
    .spr-progress-fill.is-complete { background:#16a36b; }
    .spr-progress-loss-track { background:#159b68; }
    .spr-progress-loss-fill { height:100%; width:0; max-width:100%; border-radius:999px; background:#e14d59; transition:width .35s ease; }
    .spr-progress-mark { width:19px; height:19px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:900; color:#fff; background:#16a36b; box-shadow:0 0 0 2px rgba(22,163,107,.14); }
    .spr-progress-mark.is-failed { background:#e14d59; box-shadow:0 0 0 2px rgba(225,77,89,.14); }
    .spr-progress-note { grid-column:1/-1; color:var(--spr-muted); font-size:.65rem; margin-top:-3px; }
    .spr-progress-readonly { grid-column:1/-1; color:var(--spr-info); font-size:.64rem; font-weight:750; margin-top:2px; }
    .spr-period-nav { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; padding:8px 0 10px; border-bottom:1px solid var(--spr-border); margin:3px 0 7px; }
    .spr-period-nav-btn { width:34px; height:34px; flex:0 0 34px; border:1px solid var(--spr-border); border-radius:10px; color:var(--spr-text); background:var(--spr-card); cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:1rem; }
    .spr-period-nav-btn:disabled { opacity:.28; cursor:not-allowed; }
    .spr-period-nav-title { flex:1; min-width:0; text-align:center; font-size:.94rem; font-weight:900; color:var(--spr-text); }
    .spr-period-nav-range { display:block; font-size:.67rem; font-weight:600; color:var(--spr-muted); margin-top:1px; }
    .spr-progress-lock { color:var(--spr-muted); font-size:.82rem; }
    .spr-restricted-list { margin-top:8px; padding:9px 10px; border-radius:9px; background:rgba(120,135,145,.08); color:var(--spr-muted); font-size:.69rem; }
    .spr-restricted-list strong { display:block; color:var(--spr-text); font-size:.7rem; margin-bottom:4px; }
    .spr-restricted-list ul { margin-left:16px; display:grid; gap:2px; }
    .spr-restricted-list > div + div { margin-top:4px; }
    .spr-challenge-failed { padding:10px 12px; margin-top:10px; border:1px solid rgba(223,78,78,.35); border-radius:10px; color:var(--spr-danger); font-size:.79rem; font-weight:800; }
    .spr-restart-btn { flex-shrink:0; padding:10px 22px; border:0; border-radius:10px; background:var(--spr-accent); color:#fff; font-weight:800; font-size:.85rem; cursor:pointer; font-family:inherit; white-space:nowrap; }
    .spr-restart-btn:hover { background:var(--spr-accent-2); }

    .spr-item-actions { display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; align-self: center; }

    .spr-interested-btn {
        flex-shrink: 0; align-self: center; padding: 10px 22px;
        border-radius: 10px; border: none; background: var(--spr-accent);
        color: #fff; font-weight: 800; font-size: .85rem; cursor: pointer; font-family: inherit;
        transition: background .18s, transform .15s, box-shadow .18s; white-space: nowrap;
    }
    .spr-interested-btn:hover { background: var(--spr-accent-2); transform: scale(1.04); box-shadow: 0 6px 18px rgba(18,163,107,.28); }
    .spr-interested-btn:active { transform: scale(.98); }
    .spr-interested-btn:disabled { background: var(--spr-muted); cursor: not-allowed; opacity: .7; transform: none; box-shadow: none; }
    .spr-interested-btn.is-mine { background: var(--spr-info); cursor: default; }
    .spr-interested-btn.is-mine:hover { background: var(--spr-info); transform: none; box-shadow: none; }
    .spr-interested-btn.is-readonly {
        background: transparent;
        border: 1.5px dashed var(--spr-border);
        color: var(--spr-muted);
        cursor: default;
        opacity: .8;
    }
    .spr-interested-btn.is-readonly:hover {
        background: transparent;
        transform: none;
        box-shadow: none;
    }

    .spr-cancel-btn {
        flex-shrink: 0; align-self: center; padding: 10px 22px;
        border-radius: 10px; border: none; background: var(--spr-danger);
        color: #fff; font-weight: 800; font-size: .85rem; cursor: pointer; font-family: inherit;
        transition: background .18s, transform .15s, box-shadow .18s; white-space: nowrap;
    }
    .spr-cancel-btn:hover { background: #c43c3c; transform: scale(1.04); box-shadow: 0 6px 18px rgba(223,78,78,.28); }
    .spr-cancel-btn:active { transform: scale(.98); }
    .spr-cancel-btn:disabled { background: var(--spr-muted); cursor: not-allowed; opacity: .7; transform: none; box-shadow: none; }
    .spr-cancel-btn.is-readonly-provision, .spr-cancel-btn.is-readonly-provision:disabled { background: var(--spr-muted); color: #fff; opacity: .58; filter: none; cursor: not-allowed; pointer-events: none; transform: none; box-shadow: none; }
    .spr-begin-test-btn { flex-shrink:0; padding:10px 22px; border:0; border-radius:10px; background:var(--spr-info); color:#fff; font-weight:800; font-size:.85rem; cursor:pointer; font-family:inherit; white-space:nowrap; }
    .spr-begin-test-btn:hover { filter:brightness(.92); }


    @media (max-width: 640px) {
        :root { --spr-page-pad: 14px; }
        .spr-item { flex-direction: column; align-items: stretch; gap: 12px; }
        .spr-interested-btn, .spr-cancel-btn, .spr-restart-btn { align-self: stretch; width: 100%; }
        .spr-item-actions { width: 100%; }
        .spr-meta-row { gap: 6px 14px; }
        .spr-scroll-area { padding: 52px var(--spr-page-pad) 90px; }
        .spr-filter-float { top: calc(env(safe-area-inset-top, 0px) + 132px); }
    }

    .spr-empty { padding: 36px 20px; text-align: center; color: var(--spr-muted); border: 1px dashed var(--spr-border); border-radius: 14px; background: var(--spr-bg); }
    .spr-empty i { font-size: 1.6rem; margin-bottom: 10px; color: var(--spr-accent); display: block; }
    .spr-empty strong { display: block; color: var(--spr-text); font-size: .98rem; margin-bottom: 4px; }
    .spr-empty p { font-size: .85rem; }

    .spr-modal { position: fixed; inset: 0; background: rgba(0,0,0,.58); display: none; align-items: center; justify-content: center; padding: 60px 18px; z-index: 99999; overscroll-behavior: contain; }
    .spr-modal.open { display: flex; }
    .spr-modal-card { width: min(460px, 100%); max-height: calc(100vh - 120px); overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; background: var(--spr-card); border-radius: 20px; padding: 25px; box-shadow: 0 25px 80px rgba(0,0,0,.4); }
    .spr-modal-card h3 { margin-bottom: 5px; font-size: 1.05rem; font-weight: 800; }
    .spr-modal-card .spr-sub { color: var(--spr-muted); font-size: .82rem; margin-bottom: 16px; }

    .spr-field { margin: 12px 0; }
    .spr-field label { display: block; font-size: .7rem; text-transform: uppercase; color: var(--spr-muted); font-weight: 800; margin-bottom: 6px; }
    .spr-field input, .spr-field select { width: 100%; padding: 12px; border: 1px solid var(--spr-border); border-radius: 11px; background: var(--spr-bg); color: var(--spr-text); font: inherit; }
    .spr-field input:focus, .spr-field select:focus { outline: none; border-color: var(--spr-accent); box-shadow: 0 0 0 3px var(--spr-soft); }

    .spr-filter-details { display: none; }
    .spr-filter-details.visible { display: block; }

    .spr-actions { display: grid; gap: 9px; margin-top: 16px; }
    .spr-btn { border: 0; border-radius: 11px; padding: 12px 16px; font-weight: 800; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-size: .88rem; }
    .spr-btn.primary { background: var(--spr-accent); color: #fff; }
    .spr-btn.primary:hover { background: var(--spr-accent-2); }
    .spr-btn.ghost { background: transparent; color: var(--spr-text); border: 1px solid var(--spr-border); }
    .spr-btn.ghost:hover { border-color: var(--spr-accent); color: var(--spr-accent); }
    .spr-btn.danger { background: var(--spr-danger); color: #fff; }
    .spr-btn.danger:hover { background: #c43c3c; }

    .spr-err { display: none; background: #fdecec; color: var(--spr-danger); padding: 10px; border-radius: 10px; font-size: .82rem; margin-bottom: 10px; }
    body.dark-mode .spr-err { background: #3a1c1c; color: #ff9c9c; }

    .spr-confirm-card { text-align: center; }
    .spr-confirm-icon { width: 64px; height: 64px; border-radius: 50%; background: var(--spr-soft); color: var(--spr-accent); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 14px; }
    .spr-confirm-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; }
    .spr-confirm-text { font-size: .88rem; color: var(--spr-muted); line-height: 1.55; margin-bottom: 18px; }

    .spr-alert-card { text-align: center; }
    .spr-alert-icon { width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 14px; }
    .spr-alert-icon.info    { background: var(--spr-soft); color: var(--spr-info); }
    .spr-alert-icon.success { background: var(--spr-soft); color: var(--spr-success); }
    .spr-alert-icon.warning { background: #fef5e7; color: var(--spr-warning); }
    .spr-alert-icon.error   { background: #fdecec; color: var(--spr-danger); }
    body.dark-mode .spr-alert-icon.warning { background: #3a2e1c; color: #f0b429; }
    body.dark-mode .spr-alert-icon.error { background: #3a1c1c; color: #ff9c9c; }
    .spr-alert-title { font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; }
    .spr-alert-text { font-size: .88rem; color: var(--spr-muted); line-height: 1.55; margin-bottom: 18px; }
</style>
</head>
<body class="<?= $darkMode ? 'dark-mode' : '' ?>">

<div class="spr-page">
    <div class="spr-fixed-header">
        <div class="spr-header">
            <button class="spr-header-back" onclick="sprGoBack()" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></button>
            <div class="spr-header-title">Provision Requests</div>
        </div>

        <div class="spr-main-tabs">
            <button class="spr-main-tab active" data-main-tab="challenges">Signals Provision</button>
            <button class="spr-main-tab" data-main-tab="interested">Interested Provision</button>
        </div>

        <div class="spr-sub-tabs" id="sprSubTabs">
            <button class="spr-sub-tab active" data-period="daily">Daily</button>
            <button class="spr-sub-tab" data-period="weekly">Weekly</button>
            <button class="spr-sub-tab" data-period="monthly">Monthly</button>
        </div>
    </div>

    <div class="spr-filter-float" id="sprFilterFloat">
        <button class="spr-filter-pill" onclick="sprOpenFilterModal()" aria-label="Filter">
            <i class="fa-solid fa-filter"></i> Filter
        </button>
        <button class="spr-cancel-pill" id="sprCancelFilterBtn" onclick="sprCancelFilter()" aria-label="Cancel filter" hidden>
            <i class="fa-solid fa-xmark"></i> Cancel Filter
        </button>
    </div>

    <div class="spr-scroll-area" id="sprScrollArea">
        <div id="sprRequestList" class="spr-list">
            <div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading provisions…</p></div>
        </div>
    </div>
</div>

<!-- FILTER MODAL -->
<div class="spr-modal" id="sprFilterModal">
    <div class="spr-modal-card">
        <h3>Filter Provisions</h3>
        <p class="spr-sub">Select a risk reward type to continue. Fill all fields to find your exact match.</p>

        <div class="spr-field">
            <label>Risk Reward Type</label>
            <select id="sprFilterType">
                <option value="">Select risk reward type to continue</option>
                <option value="fixed_risk_reward">Fixed Risk Reward</option>
                <option value="minimum_risk_reward">Minimum Risk Reward</option>
                <option value="custom_and_default_risk_reward">Custom &amp; Default Risk Reward</option>
            </select>
        </div>

        <div class="spr-filter-details" id="sprFilterDetails">
            <div class="spr-field">
                <label id="sprRrLabel">Risk Reward (1:?)</label>
                <input type="text" inputmode="numeric" maxlength="7" class="spr-field-input" id="sprFilterRr" placeholder="1:2" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            </div>

            <div class="spr-field">
                <label>Minimum Trades Count</label>
                <input type="number" id="sprFilterTrades" min="1" max="100" step="1" placeholder="e.g. 10" autocomplete="off">
            </div>

            <div class="spr-field">
                <label>Minimum Expected Win Count</label>
                <input type="number" id="sprFilterWin" min="1" max="100" step="1" placeholder="e.g. 8" autocomplete="off">
            </div>

            <div class="spr-field">
                <label>Maximum Expected Consecutive Loss</label>
                <input type="number" id="sprFilterLoss" min="1" max="100" step="1" placeholder="e.g. 3" autocomplete="off">
            </div>
        </div>

        <div id="sprFilterErr" class="spr-err"></div>

        <div class="spr-actions">
            <button class="spr-btn primary" onclick="sprApplyFilter()">Apply Filter</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprFilterModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- CONFIRM INTEREST MODAL -->
<div class="spr-modal" id="sprConfirmModal">
    <div class="spr-modal-card spr-confirm-card">
        <div class="spr-confirm-icon"><i class="fa-solid fa-trophy"></i></div>
        <div class="spr-confirm-title">Confirm Provision Interest</div>
        <div class="spr-confirm-text" id="sprConfirmInterestText">
            By joining this provision, you agree to meet its duration and trading-performance requirements.
        </div>
        <div class="spr-actions">
            <button class="spr-btn primary" id="sprConfirmInterestBtn">Confirm to Proceed</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprConfirmModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- CANCEL INTEREST CONFIRM MODAL -->
<div class="spr-modal" id="sprCancelConfirmModal">
    <div class="spr-modal-card spr-confirm-card">
        <div class="spr-confirm-icon" style="background:#fdecec;color:var(--spr-danger);"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="spr-confirm-title" id="sprCancelConfirmTitle">Cancel Provision Interest</div>
        <div class="spr-confirm-text" id="sprCancelConfirmText">
            This provision status is read-only. You cannot cancel it from this button.
        </div>
        <div class="spr-actions">
            <button class="spr-btn danger" id="sprCancelInterestConfirmBtn">Yes, Cancel Provision</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprCancelConfirmModal')">Keep Provision</button>
        </div>
    </div>
</div>

<!-- BEGIN PROVISION TEST MODAL -->
<div class="spr-modal" id="sprBeginTestModal">
    <div class="spr-modal-card spr-confirm-card" style="text-align:center;">
        <div class="spr-confirm-icon" style="background:rgba(52,152,219,.12);color:#3498db;"><i class="fa-solid fa-play"></i></div>
        <div class="spr-confirm-title">Begin Test</div>
        <div class="spr-confirm-text" id="sprBeginTestText">Once you agree to begin the test, live trades will be generated from your trained programme for the next <?= (int)($dayDuration ?: 30) ?> days. Your strategy will be evaluated against the challenge requirements, and once they are met, your programme will be advertised.</div>
        <div class="spr-actions">
            <button class="spr-btn primary" id="sprConfirmBeginTestBtn">Confirm Test</button>
            <button class="spr-btn ghost" onclick="sprCloseModal('sprBeginTestModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- CUSTOM ALERT MODAL -->
<div class="spr-modal" id="sprAlertModal">
    <div class="spr-modal-card spr-alert-card">
        <div class="spr-alert-icon info" id="sprAlertIcon"><i class="fa-solid fa-circle-info"></i></div>
        <div class="spr-alert-title" id="sprAlertTitle">Notice</div>
        <div class="spr-alert-text" id="sprAlertText">Message</div>
        <div class="spr-actions">
            <button class="spr-btn primary" id="sprAlertOkBtn">OK</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var CURRENT_MAIN_TAB = 'challenges';
    var CURRENT_PERIOD = 'daily';
    var CHALLENGE_DURATIONS = { daily: <?= (int)$dayDuration ?>, weekly: <?= (int)$weeklyDuration ?>, monthly: <?= (int)$monthlyDuration ?> };
    var TRAINING_HAS_ROOT = <?= !empty($trainingReadiness['has_root']) ? 'true' : 'false' ?>;
    var TRAINING_TRADES_CONFIGURED = <?= !empty($trainingReadiness['trades_configured']) ? 'true' : 'false' ?>;
    var PROGRAMME_HAS_VPS = <?= $programmeHasVps ? 'true' : 'false' ?>;
    var PROGRAMME_HAS_BROKER = <?= $programmeHasBroker ? 'true' : 'false' ?>;
    var PROGRAMME_IS_ADVERTISED = <?= $programmeIsAdvertised ? 'true' : 'false' ?>;

    var REQUESTS = [];
    var INTERESTS = [];
    var PENDING_REQUEST = null;
    var PENDING_INTEREST_ID = null;
    var ACTIVE_FILTERS = null;
    var MY_INTEREST = null;
    var SPR_SELECTED_PERIODS = {};
    var SPR_BEGIN_ACTION = 'begin_test';

    var cancelFilterBtn = document.getElementById('sprCancelFilterBtn');
    var filterFloat = document.getElementById('sprFilterFloat');
    var subTabs = document.getElementById('sprSubTabs');
    var requestListEl = document.getElementById('sprRequestList');

    function notifyParentModal(open) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: open ? 'harvhubModalOpen' : 'harvhubModalClose' }, '*');
            }
        } catch (e) {}
    }

    function anyModalOpen() { return !!document.querySelector('.spr-modal.open'); }
    function lockBodyScroll() { document.body.classList.add('spr-scroll-locked'); }
    function unlockBodyScroll() { if (!anyModalOpen()) document.body.classList.remove('spr-scroll-locked'); }

    window.sprGoBack = function () {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'switchTab', tab: 'signals' }, '*');
                return;
            }
        } catch (e) {}
        window.location.href = 'traderapp.php?tab=signals';
    };

    window.sprOpenModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.add('open');
        lockBodyScroll();
        notifyParentModal(true);
    };

    window.sprCloseModal = function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('open');
        if (!anyModalOpen()) { unlockBodyScroll(); notifyParentModal(false); }
    };

    document.querySelectorAll('.spr-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) sprCloseModal(m.id); });
    });

    window.sprAlert = function (message, kind, title) {
        kind = kind || 'info';
        var iconEl  = document.getElementById('sprAlertIcon');
        var titleEl = document.getElementById('sprAlertTitle');
        var textEl  = document.getElementById('sprAlertText');

        iconEl.classList.remove('info', 'success', 'warning', 'error');
        iconEl.classList.add(kind);

        var iconHtml = '<i class="fa-solid fa-circle-info"></i>';
        if (kind === 'success') iconHtml = '<i class="fa-solid fa-circle-check"></i>';
        if (kind === 'warning') iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        if (kind === 'error')   iconHtml = '<i class="fa-solid fa-triangle-exclamation"></i>';
        iconEl.innerHTML = iconHtml;

        titleEl.textContent = title || (kind === 'error' ? 'Error' : (kind === 'success' ? 'Success' : (kind === 'warning' ? 'Warning' : 'Notice')));
        textEl.textContent  = message || '';

        sprOpenModal('sprAlertModal');
    };

    document.getElementById('sprAlertOkBtn').addEventListener('click', function () {
        sprCloseModal('sprAlertModal');
    });

    function post(data, cb) {
        var body = Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&');
        fetch('signals_provision_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: body
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { cb(d || {}); })
        .catch(function () { cb({ success: false }); });
    }

    function escapeHtml(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

    function rrTypeLabel(t) {
        switch (t) {
            case 'fixed_risk_reward': return 'Fixed Risk Reward';
            case 'minimum_risk_reward': return 'Minimum Risk Reward';
            case 'custom_and_default_risk_reward': return 'Custom & Default Risk Reward';
            default: return 'Fixed Risk Reward';
        }
    }

    function renderRequest(req, myInterest) {
        var isSystem = (parseInt(req.user_id, 10) || 0) === 0;
        var isMine = !!(myInterest && req.id && parseInt(myInterest.id, 10) === parseInt(req.id, 10));
        var hasAnyInterest = !!(myInterest && myInterest.id);

        var name = isSystem ? 'System Provision' :
            (req.username || req.first_name || req.fullname || ('Trader #' + req.user_id));

        var rr = parseFloat(req.risk_reward) || 0;
        var rrType = rrTypeLabel(req.risk_reward_type);
        var loss = parseInt(req.consecutive_loss, 10) || 0;
        var trCount = parseInt(req.trades_count, 10) || 0;
        var expWin = parseInt(req.expected_win, 10) || 0;

        var periodLabel = CURRENT_PERIOD.charAt(0).toUpperCase() + CURRENT_PERIOD.slice(1);

        var html = '';
        html += '<div class="spr-item" data-request-id="' + (req.id || 0) + '">';
        html +=   '<div class="spr-item-main">';
        html +=     '<div class="spr-item-header">';
        html +=       '<div class="spr-item-title">' + escapeHtml(name) + (isSystem ? ' <span style="font-size:.72rem;color:var(--spr-muted);font-weight:600;">· System</span>' : '') + '</div>';
        html +=       '<div class="spr-item-sub">' + escapeHtml(periodLabel) + ' trades provision</div>';
        html +=     '</div>';
        html +=     '<div class="spr-meta-row">';

        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Trades Provision:</span><span class="spr-meta-value">' + escapeHtml(periodLabel) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward Type:</span><span class="spr-meta-value">' + escapeHtml(rrType) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward</span><span class="spr-meta-value">1:' + rr + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Minimum Trades Count</span><span class="spr-meta-value">' + trCount + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Minimum Expected Win Count</span><span class="spr-meta-value">' + expWin + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Maximum Expected Consecutive Loss</span><span class="spr-meta-value">' + loss + '</span></div>';

        html +=     '</div>';
        html +=   '</div>';

        if (isMine) {
            html += '<button class="spr-interested-btn is-mine" disabled>Interested in provision ✓</button>';
        } else if (hasAnyInterest) {
            html += '<button class="spr-interested-btn is-readonly" disabled title="You already have an active provision interest for this programme.">Already providing signals</button>';
        } else {
            html += '<button class="spr-interested-btn" onclick="sprSelectRequest(' + (req.id || 0) + ')">Interested in provision</button>';
        }

        html += '</div>';
        return html;
    }

    function renderInterestItem(interest) {
        var periodValue = (interest.trades_provision || 'daily').toLowerCase();
        var periodLabel = periodValue.charAt(0).toUpperCase() + periodValue.slice(1);
        var rrType = rrTypeLabel(interest.risk_reward_type);
        var status = (interest.interest_status || 'interested').toLowerCase();
        var statusColors = {
            'interested': 'var(--spr-info)', 'pending': 'var(--spr-warning)',
            'active': 'var(--spr-accent)', 'ongoing': 'var(--spr-info)',
            'passed': '#16a36b', 'failed': 'var(--spr-danger)', 'breached': 'var(--spr-danger)'
        };
        var statusColor = statusColors[status] || 'var(--spr-info)';
        var tradesTarget = Math.max(0, parseInt(interest.trades_count, 10) || 0);
        var winsTarget = Math.max(0, parseInt(interest.expected_win, 10) || 0);
        var lossesLimit = Math.max(0, parseInt(interest.consecutive_loss, 10) || 0);
        var periods = Array.isArray(interest.challenge_periods) ? interest.challenge_periods : [];
        var savedIndex = Object.prototype.hasOwnProperty.call(SPR_SELECTED_PERIODS, String(interest.id))
            ? SPR_SELECTED_PERIODS[String(interest.id)] : parseInt(interest.challenge_current_period_index, 10);
        if (!Number.isFinite(savedIndex) || savedIndex < 0) savedIndex = 0;
        if (periods.length && savedIndex >= periods.length) savedIndex = periods.length - 1;
        if (periods.length) SPR_SELECTED_PERIODS[String(interest.id)] = savedIndex;
        var moment = periods[savedIndex] || null;
        var locked = !!(moment && moment.locked);
        var tracking = !!interest.challenge_tracking_available;
        var readonly = !!interest.provision_read_only;
        var liveTrades = moment ? Math.max(0, parseInt(moment.trades, 10) || 0) : 0;
        var liveWins = moment ? Math.max(0, parseInt(moment.wins, 10) || 0) : 0;
        var liveLosses = moment ? Math.max(0, parseInt(moment.consecutive_losses, 10) || 0) : 0;
        var tradesPassed = !!(moment && moment.trades_passed);
        var winsPassed = !!(moment && moment.wins_passed);
        var lossPassed = !!(moment && moment.loss_gate_passed);
        var tradesPercent = tradesTarget > 0 ? Math.min(100, (liveTrades / tradesTarget) * 100) : 0;
        var winsPercent = winsTarget > 0 ? Math.min(100, (liveWins / winsTarget) * 100) : 0;
        var lossPercent = lossesLimit > 0 ? Math.min(100, (liveLosses / lossesLimit) * 100) : 0;
        var lossFailed = lossesLimit > 0 && liveLosses >= lossesLimit;

        function progressBlock(label, current, target, percent, passed, isLoss) {
            var right = locked
                ? '<i class="fa-solid fa-lock spr-progress-lock" aria-label="Locked until this checkpoint begins"></i>'
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
                + '<div class="spr-progress-heading"><span class="spr-progress-label">' + label + '</span>' + right + '</div>'
                + '<div class="' + trackClass + '"><div class="' + fillClass + '" style="width:' + (locked ? 0 : percent).toFixed(2) + '%"></div></div>'
                + mark
                + '</div>';
        }

        var selectedLabel = moment ? moment.label : (periodLabel + ' Checkpoint');
        var selectedRange = moment ? (moment.start_date + (moment.end_date && moment.end_date !== moment.start_date ? ' – ' + moment.end_date : '')) : '';
        var globalRestrictedDates = Array.isArray(interest.challenge_restricted_dates) ? interest.challenge_restricted_dates : [];
        var datesForCard = moment && Array.isArray(moment.excluded_dates) ? moment.excluded_dates : globalRestrictedDates;
        var restrictedDayRows = moment && Array.isArray(moment.excluded_days) ? moment.excluded_days : (interest.challenge_restricted_days || []);
        function displayChallengeDate(value) {
            var raw = String(value || '').slice(0, 10);
            var parts = raw.split('-');
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            if (parts.length !== 3) return raw;
            var monthIndex = parseInt(parts[1], 10) - 1;
            if (monthIndex < 0 || monthIndex > 11) return raw;
            return parts[2].padStart(2, '0') + ' ' + months[monthIndex] + ' ' + parts[0];
        }
        var restrictedDayText = restrictedDayRows.length
            ? restrictedDayRows.map(function (d) { return String(d).charAt(0).toUpperCase() + String(d).slice(1).toLowerCase(); }).join(', ')
            : 'None';
        var restrictedDateText = datesForCard.length
            ? datesForCard.map(function (d) { return displayChallengeDate(typeof d === 'object' ? (d.date || '') : d); }).filter(Boolean).join(', ')
            : 'None';
        var restrictionsHtml = '<div class="spr-restricted-list">'
            + '<div><strong>Weekly Restricted Trading Days:</strong> ' + escapeHtml(restrictedDayText || 'None') + '</div>'
            + '<div><strong>Restricted Trading Dates:</strong> ' + escapeHtml(restrictedDateText || 'None') + '</div>'
            + '</div>';

        var html = '';
        html += '<div class="spr-item" data-interest-id="' + interest.id + '">';
        html +=   '<div class="spr-item-main">';
        html +=     '<div class="spr-item-header">';
        html +=       '<div class="spr-item-title">' + escapeHtml(periodLabel) + ' Provision</div>';
        html +=       '<div class="spr-item-sub">Status: <span style="color:' + statusColor + ';font-weight:800;text-transform:capitalize;">' + escapeHtml(status) + '</span></div>';
        html +=       '<div class="spr-challenge-begun-indicator">' + escapeHtml(interest.challenge_begun_label || ((parseInt(interest.begin_test, 10) || 0) === 1 ? 'Provision challenge has begun' : 'Provision challenge hasn\'t begun')) + '</div>';
        if (interest.challenge_duration_description) html += '<div class="spr-duration-provision">' + escapeHtml(interest.challenge_duration_description) + '</div>';
        if (interest.challenge_preview && interest.challenge_lookback_description) html += '<div class="spr-duration-provision">' + escapeHtml(interest.challenge_lookback_description) + '</div>';
        html +=     '</div>';
        if (periods.length) {
            html += '<div class="spr-period-nav">';
            html +=   '<button type="button" class="spr-period-nav-btn" aria-label="Previous checkpoint" ' + (savedIndex <= 0 ? 'disabled' : '') + ' onclick="sprMoveChallengePeriod(' + interest.id + ',-1)"><i class="fa-solid fa-chevron-left"></i></button>';
            html +=   '<div class="spr-period-nav-title">' + escapeHtml(selectedLabel) + '<span class="spr-period-nav-range">' + escapeHtml(selectedRange) + '</span></div>';
            html +=   '<button type="button" class="spr-period-nav-btn" aria-label="Next checkpoint" ' + (savedIndex >= periods.length - 1 ? 'disabled' : '') + ' onclick="sprMoveChallengePeriod(' + interest.id + ',1)"><i class="fa-solid fa-chevron-right"></i></button>';
            html += '</div>';
        }
        html +=     '<div class="spr-meta-row">';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward Type:</span><span class="spr-meta-value">' + escapeHtml(rrType) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Risk Reward</span><span class="spr-meta-value">1:' + (parseFloat(interest.risk_reward) || 0) + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Minimum Trades Count</span><span class="spr-meta-value">' + tradesTarget + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Minimum Expected Win Count</span><span class="spr-meta-value">' + winsTarget + '</span></div>';
        html +=       '<div class="spr-meta-item"><span class="spr-meta-label">Maximum Expected Consecutive Loss</span><span class="spr-meta-value">' + lossesLimit + '</span></div>';
        html +=     '</div>';
        html +=     '<div class="spr-progress-list">';
        html +=       progressBlock('Minimum Trades Count', liveTrades, tradesTarget, tradesPercent, tradesPassed, false);
        html +=       progressBlock('Minimum Expected Win Count', liveWins, winsTarget, winsPercent, winsPassed, false);
        html +=       progressBlock('Maximum Expected Consecutive Loss', liveLosses, lossesLimit, lossPercent, lossPassed, true);
        html +=       restrictionsHtml;
        html +=     '</div>';
        html +=   '</div>';
        html +=   '<div class="spr-item-actions">';
        var canBeginTest = !PROGRAMME_IS_ADVERTISED && status === 'interested' && (parseInt(interest.begin_test, 10) || 0) === 0
            && TRAINING_HAS_ROOT && TRAINING_TRADES_CONFIGURED && PROGRAMME_HAS_VPS && PROGRAMME_HAS_BROKER;
        if (canBeginTest) {
            html += '<button class="spr-begin-test-btn" onclick="sprBeginTest(' + interest.id + ', ' + JSON.stringify(periodLabel) + ')"><i class="fa-solid fa-play"></i> Begin Test</button>';
        }
        if (status === 'failed') {
            html += '<button class="spr-restart-btn" onclick="sprRestartInterest(' + interest.id + ', ' + JSON.stringify(periodLabel) + ')"><i class="fa-solid fa-rotate-right"></i> Restart Challenge</button>';
        }
        if (['interested','pending','active','ongoing','failed','breached'].indexOf(status) !== -1) {
            html += '<button class="spr-cancel-btn" type="button" onclick="sprCancelInterest(' + interest.id + ')">Cancel Provision</button>';
        }
        html +=   '</div>';
        html += '</div>';
        return html;
    }

    function updateCancelFilterButton() {
        cancelFilterBtn.hidden = !ACTIVE_FILTERS;
    }

    function updateUIForTab() {
        if (CURRENT_MAIN_TAB === 'challenges') {
            subTabs.hidden = false;
            filterFloat.style.display = '';
        } else {
            subTabs.hidden = true;
            filterFloat.style.display = 'none';
        }
    }

    function renderChallenges() {
        if (!REQUESTS.length) {
            requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-trophy"></i><strong>No provisions match</strong><p>Try a different filter or period.</p></div>';
            updateCancelFilterButton();
            return;
        }

        post({ action: 'check_my_interest' }, function (d) {
            MY_INTEREST = (d && d.success && d.interest) ? d.interest : null;
            var html = '';
            REQUESTS.forEach(function (r) { html += renderRequest(r, MY_INTEREST); });
            requestListEl.innerHTML = html;
            updateCancelFilterButton();
        });
    }

    function renderInterests() {
        if (!INTERESTS.length) {
            requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-bookmark"></i><strong>No active provision interests</strong><p>Go to Signals Provision tab and select Interested in provision.</p></div>';
            return;
        }

        var html = '';
        INTERESTS.forEach(function (i) { html += renderInterestItem(i); });
        requestListEl.innerHTML = html;
    }

    function loadChallenges() {
        requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading provisions…</p></div>';

        post({ action: 'get_requests', period: CURRENT_PERIOD }, function (d) {
            if (!d || !d.success) {
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load provisions.') + '</p></div>';
                return;
            }
            REQUESTS = d.requests || [];
            renderChallenges();
        });
    }

    function loadInterests(silent) {
        if (!silent) requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Loading your interests…</p></div>';

        post({ action: 'get_my_interests' }, function (d) {
            if (!d || !d.success) {
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-exclamation-triangle"></i><strong>Error</strong><p>' + escapeHtml(d.message || 'Failed to load interests.') + '</p></div>';
                return;
            }
            INTERESTS = d.interests || [];
            if (d.programme_is_advertised === true) PROGRAMME_IS_ADVERTISED = true;
            renderInterests();
        });
    }

    function loadCurrentTab() {
        if (CURRENT_MAIN_TAB === 'challenges') {
            loadChallenges();
        } else {
            loadInterests();
        }
    }

    document.querySelectorAll('.spr-main-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.spr-main-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            CURRENT_MAIN_TAB = t.dataset.mainTab;
            ACTIVE_FILTERS = null;
            updateCancelFilterButton();
            updateUIForTab();
            loadCurrentTab();
        });
    });

    document.querySelectorAll('.spr-sub-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.spr-sub-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            CURRENT_PERIOD = t.dataset.period;
            ACTIVE_FILTERS = null;
            updateCancelFilterButton();
            loadChallenges();
        });
    });

    window.sprCancelFilter = function () {
        ACTIVE_FILTERS = null;
        updateCancelFilterButton();
        loadChallenges();
    };

    var filterTypeSelect = document.getElementById('sprFilterType');
    var filterDetails = document.getElementById('sprFilterDetails');
    var rrLabel = document.getElementById('sprRrLabel');

    function syncFilterDetailsVisibility() {
        var v = filterTypeSelect.value;
        if (v) {
            filterDetails.classList.add('visible');
            if (v === 'minimum_risk_reward') rrLabel.textContent = 'Minimum Risk Reward (1:?)';
            else if (v === 'fixed_risk_reward') rrLabel.textContent = 'Fixed Risk Reward (1:?)';
            else if (v === 'custom_and_default_risk_reward') rrLabel.textContent = 'Default Risk Reward (1:?)';
            else rrLabel.textContent = 'Risk Reward (1:?)';
        } else {
            filterDetails.classList.remove('visible');
        }
    }

    filterTypeSelect.addEventListener('change', function () {
        syncFilterDetailsVisibility();
        document.getElementById('sprFilterErr').style.display = 'none';
    });

    window.sprOpenFilterModal = function () {
        document.getElementById('sprFilterErr').style.display = 'none';
        syncFilterDetailsVisibility();
        sprOpenModal('sprFilterModal');
    };

    (function initRrInput() {
        var el = document.getElementById('sprFilterRr');
        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('spellcheck', 'false');
        el.setAttribute('name', 'rr_' + Math.random().toString(36).slice(2));
        el.setAttribute('type', 'text');

        function extractDigits(v) {
            if (!v) return '';
            var s = v;
            if (s.indexOf('1:') === 0) s = s.slice(2);
            return s.replace(/[^0-9]/g, '').slice(0, 5);
        }

        el.addEventListener('input', function () {
            var digits = extractDigits(this.value);
            this.value = digits === '' ? '' : '1:' + digits;
        });

        el.addEventListener('keydown', function (e) {
            if (e.ctrlKey || e.metaKey) return;
            if (['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Tab','Home','End','Enter','Escape'].indexOf(e.key) !== -1) return;
            if (e.key === 'Backspace') {
                var v = this.value || '';
                if (v === '1:' || v === '') { e.preventDefault(); this.value = ''; return; }
                var s = this.selectionStart, t = this.selectionEnd;
                if (s === t && s <= 2) { e.preventDefault(); this.value = ''; return; }
                return;
            }
            if (e.key.length === 1 && !/[0-9]/.test(e.key)) e.preventDefault();
        });

        el.addEventListener('focus', function () {
            var digits = extractDigits(this.value);
            this.value = digits === '' ? '' : '1:' + digits;
        });
        el.addEventListener('blur', function () {
            if (this.value === '1:') { this.value = ''; return; }
            var digits = extractDigits(this.value);
            if (digits !== '' && parseInt(digits, 10) < 1) digits = '1';
            this.value = digits === '' ? '' : '1:' + digits;
        });
    })();

    function parseRrInput() {
        var el = document.getElementById('sprFilterRr');
        var raw = (el.value || '').trim();
        if (raw === '' || raw === '1:') return null;
        if (raw.indexOf('1:') === 0) raw = raw.slice(2);
        var digits = raw.replace(/[^0-9]/g, '');
        if (digits === '') return null;
        var num = parseInt(digits, 10);
        return isNaN(num) ? null : num;
    }

    window.sprApplyFilter = function () {
        var err = document.getElementById('sprFilterErr');
        var selectedType = filterTypeSelect.value;

        if (!selectedType) {
            err.textContent = 'Select risk reward type before proceeding';
            err.style.display = 'block';
            return;
        }

        var f = {
            type: selectedType,
            rr: parseRrInput(),
            trades: document.getElementById('sprFilterTrades').value !== '' ? parseInt(document.getElementById('sprFilterTrades').value, 10) : null,
            win: document.getElementById('sprFilterWin').value !== '' ? parseInt(document.getElementById('sprFilterWin').value, 10) : null,
            loss: document.getElementById('sprFilterLoss').value !== '' ? parseInt(document.getElementById('sprFilterLoss').value, 10) : null,
        };

        if (f.rr === null || f.trades === null || f.win === null || f.loss === null) {
            err.textContent = 'Fill in all fields: risk reward, trades count, expected win, and consecutive loss.';
            err.style.display = 'block';
            return;
        }

        if (f.rr < 1 || f.rr > 100) { err.textContent = 'Risk reward must be 1–100.'; err.style.display = 'block'; return; }
        if (f.trades < 1 || f.trades > 100) { err.textContent = 'Trades count must be 1–100.'; err.style.display = 'block'; return; }
        if (f.win < 1 || f.win > 100) { err.textContent = 'Expected win must be 1–100.'; err.style.display = 'block'; return; }
        if (f.loss < 1 || f.loss > 100) { err.textContent = 'Consecutive loss must be 1–100.'; err.style.display = 'block'; return; }

        if (f.win > f.trades) {
            err.textContent = 'Expected win cannot be greater than trades count.'; err.style.display = 'block'; return;
        }
        if (f.loss >= f.trades) {
            err.textContent = 'Consecutive loss must be less than trades count — otherwise you would be blown.'; err.style.display = 'block'; return;
        }

        ACTIVE_FILTERS = f;
        updateCancelFilterButton();

        requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-spinner fa-spin"></i><p>Applying filter…</p></div>';

        var payload = {
            action: 'filter_requests',
            period: CURRENT_PERIOD,
            risk_reward_type: f.type,
            risk_reward: f.rr,
            trades_count: f.trades,
            expected_win: f.win,
            consecutive_loss: f.loss,
        };

        post(payload, function (d) {
            if (!d || !d.success) {
                err.textContent = (d && d.message) || 'Could not apply filter.';
                err.style.display = 'block';
                sprOpenModal('sprFilterModal');
                requestListEl.innerHTML = '<div class="spr-empty"><i class="fa-solid fa-trophy"></i><strong>No match</strong><p>' + escapeHtml((d && d.message) || 'This configuration is not valid.') + '</p></div>';
                return;
            }

            REQUESTS = d.requests || [];
            sprCloseModal('sprFilterModal');
            renderChallenges();
        });
    };

    window.sprSelectRequest = function (requestId) {
        PENDING_REQUEST = REQUESTS.filter(function (r) { return parseInt(r.id, 10) === requestId; })[0] || null;
        if (!PENDING_REQUEST) return;

        var period = CURRENT_PERIOD;
        var duration = parseInt(CHALLENGE_DURATIONS[period], 10) || ({ daily: 30, weekly: 60, monthly: 90 }[period]);
        var trades = parseInt(PENDING_REQUEST.trades_count, 10) || 0;
        var wins = parseInt(PENDING_REQUEST.expected_win, 10) || 0;
        var losses = parseInt(PENDING_REQUEST.consecutive_loss, 10) || 0;
        var rr = Math.max(1, parseFloat(PENDING_REQUEST.risk_reward) || 1);
        var type = PENDING_REQUEST.risk_reward_type || 'fixed_risk_reward';
        var rrRequirement = type === 'fixed_risk_reward'
            ? 'Each trade must use a fixed 1:' + rr + ' risk-to-reward ratio.'
            : (type === 'minimum_risk_reward'
                ? 'Each trade must have a risk-to-reward ratio of at least 1:' + rr + '.'
                : 'Each trade will use its generated custom risk-to-reward ratio.');

        document.getElementById('sprConfirmInterestText').textContent =
            'By joining this ' + period + ' challenge, your strategy\'s performance and compliance with the specified requirements will be evaluated over the next ' + duration + ' days. ' +
            'Minimum trades count: ' + trades + '. ' +
            'Minimum expected win count: ' + wins + '. ' +
            'Maximum expected consecutive losses: ' + losses + '. ' +
            rrRequirement;

        sprOpenModal('sprConfirmModal');
    };

    document.getElementById('sprConfirmInterestBtn').addEventListener('click', function () {
        if (!PENDING_REQUEST) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Saving…';

        var data = {
            action: 'save_interest',
            request_id: PENDING_REQUEST.id || 0,
            period: CURRENT_PERIOD,
            risk_reward_type: PENDING_REQUEST.risk_reward_type || 'fixed_risk_reward',
            risk_reward: PENDING_REQUEST.risk_reward || 2,
            consecutive_loss: PENDING_REQUEST.consecutive_loss || 2,
            trades_count: PENDING_REQUEST.trades_count || 1,
            expected_win: PENDING_REQUEST.expected_win || 1,
        };

        post(data, function (d) {
            btn.disabled = false;
            btn.textContent = 'Confirm to Proceed';
            if (d && d.success) {
                sprCloseModal('sprConfirmModal');
                sprAlert('Your provision interest has been saved successfully. You can now begin your training and testing.', 'success', 'Interest Registered');
                loadChallenges();
            } else {
                sprAlert((d && d.message) || 'Failed to save interest.', 'error', 'Could not save');
            }
        });
    });

    window.sprMoveChallengePeriod = function (interestId, direction) {
        var item = INTERESTS.find(function (x) { return parseInt(x.id, 10) === parseInt(interestId, 10); });
        if (!item || !Array.isArray(item.challenge_periods) || !item.challenge_periods.length) return;
        var key = String(interestId);
        var current = Object.prototype.hasOwnProperty.call(SPR_SELECTED_PERIODS, key) ? SPR_SELECTED_PERIODS[key] : (parseInt(item.challenge_current_period_index, 10) || 0);
        SPR_SELECTED_PERIODS[key] = Math.max(0, Math.min(item.challenge_periods.length - 1, current + (parseInt(direction, 10) || 0)));
        renderInterests();
    };

    window.sprBeginTest = function (interestId, periodLabel) {
        SPR_BEGIN_ACTION = 'begin_test';
        document.querySelector('#sprBeginTestModal .spr-confirm-title').textContent = 'Begin Test';
        document.getElementById('sprConfirmBeginTestBtn').textContent = 'Confirm Test';
        var duration = parseInt(CHALLENGE_DURATIONS[String(periodLabel || 'Daily').toLowerCase()], 10) || 30;
        document.getElementById('sprBeginTestText').textContent =
            'Once you agree to begin the test, live trades will be generated from your trained programme for the next ' + duration +
            ' calendar days. Restricted dates and weekdays are excluded. Every day, each of the eight weeks, or each of the three months must meet the provision requirement before the challenge can pass.';
        window.SPR_BEGIN_TEST_INTEREST_ID = parseInt(interestId, 10) || 0;
        sprOpenModal('sprBeginTestModal');
    };

    window.sprRestartInterest = function (interestId, periodLabel) {
        SPR_BEGIN_ACTION = 'restart_interest';
        window.SPR_BEGIN_TEST_INTEREST_ID = parseInt(interestId, 10) || 0;
        document.querySelector('#sprBeginTestModal .spr-confirm-title').textContent = 'Restart Challenge';
        document.getElementById('sprBeginTestText').textContent =
            'Restarting will reset every daily, weekly, or monthly checkpoint to zero and begin a fresh ' + periodLabel.toLowerCase() + ' challenge window. Restricted dates and weekdays will be applied again.';
        document.getElementById('sprConfirmBeginTestBtn').textContent = 'Confirm Restart';
        sprOpenModal('sprBeginTestModal');
    };

    document.getElementById('sprConfirmBeginTestBtn').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Starting…';
        var actionName = SPR_BEGIN_ACTION === 'restart_interest' ? 'restart_interest' : 'begin_test';
        post({ action: actionName, interest_id: window.SPR_BEGIN_TEST_INTEREST_ID || 0 }, function (d) {
            btn.disabled = false;
            btn.textContent = 'Confirm Test';
            SPR_BEGIN_ACTION = 'begin_test';
            document.querySelector('#sprBeginTestModal .spr-confirm-title').textContent = 'Begin Test';
            if (d && d.success) {
                sprCloseModal('sprBeginTestModal');
                sprAlert(actionName === 'restart_interest' ? 'Your challenge has been restarted and all checkpoints are reset.' : 'Your signals provision test has begun.', 'success', actionName === 'restart_interest' ? 'Challenge Restarted' : 'Test Started');
                loadInterests();
            } else {
                sprAlert((d && d.message) || 'Failed to begin test.', 'error', 'Could not begin test');
            }
        });
    });

    window.sprCancelInterest = function (interestId) {
        PENDING_INTEREST_ID = interestId;
        document.getElementById('sprCancelConfirmTitle').textContent = 'Cancel Provision Interest';
        document.getElementById('sprCancelConfirmText').textContent =
            'Cancel this provision challenge? Your current challenge will stop. You can then join a new provision challenge.';
        sprOpenModal('sprCancelConfirmModal');
    };

    document.getElementById('sprCancelInterestConfirmBtn').addEventListener('click', function () {
        if (!PENDING_INTEREST_ID) return;
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Cancelling…';

        post({ action: 'cancel_interest', interest_id: PENDING_INTEREST_ID }, function (d) {
            btn.disabled = false;
            btn.textContent = 'Yes, Cancel Provision';

            if (d && d.success) {
                sprCloseModal('sprCancelConfirmModal');
                sprAlert(
                    'Your provision interest has been cancelled. Your programme will need to embark on a new provision duration to participate again.',
                    'warning',
                    'Provision Cancelled'
                );
                PENDING_INTEREST_ID = null;
                loadInterests();
            } else {
                sprAlert((d && d.message) || 'Failed to cancel provision.', 'error', 'Could not cancel');
            }
        });
    });

    window.addEventListener('message', function (e) {
        if (!e.data || typeof e.data !== 'object') return;
        if (e.data.type === 'theme') document.body.classList.toggle('dark-mode', !!e.data.dark);
    });
    try { window.parent.postMessage({ type: 'requestTheme' }, '*'); } catch (e) {}

    window.setInterval(function () {
        if (CURRENT_MAIN_TAB === 'interested') loadInterests(true);
    }, 12000);

    syncFilterDetailsVisibility();
    updateCancelFilterButton();
    updateUIForTab();
    loadCurrentTab();
})();
</script>

</body>
</html>