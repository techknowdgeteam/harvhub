
<?php
/**
 * mysqlconnection.php
 *
 * Centralized database connection manager for AwardSpace and InfinityFree.
 *
 * Connection order:
 * 1. AwardSpace
 * 2. InfinityFree
 *
 * IMPORTANT:
 * - Each hosting provider has its own separate database.
 * - The first successful connection is returned.
 * - This script does not synchronize database contents.
 * - This file must not produce browser output.
 * - Save this file as UTF-8 WITHOUT BOM.
 * - Configure database passwords through environment variables.
 */

if (!function_exists('getDatabaseServers')) {

    /**
     * Return configured database servers in priority order.
     */
    function getDatabaseServers(): array
    {
        return [
            [
                'name'     => 'AwardSpace',
                'host'     => 'fdb1030.awardspace.net',
                'database' => '4798495_harvhub',
                'username' => '4798495_harvhub',
                'password' => getenv('AWARDSPACE_DB_PASSWORD') !== false
                    ? getenv('AWARDSPACE_DB_PASSWORD')
                    : '@harvesthub12#',
                'port'     => 3306,
                'charset'  => 'utf8mb4',
            ],

            [
                'name'     => 'InfinityFree',
                'host'     => 'sql312.infinityfree.com',
                'database' => 'if0_40473107_harvhub',
                'username' => 'if0_40473107',
                'password' => getenv('INFINITYFREE_DB_PASSWORD') !== false
                    ? getenv('INFINITYFREE_DB_PASSWORD')
                    : '',
                'port'     => 3306,
                'charset'  => 'utf8mb4',
            ],
        ];
    }
}

/**
 * Check whether a database password is configured.
 */
if (!function_exists('isDatabasePasswordConfigured')) {

    function isDatabasePasswordConfigured(string $password): bool
    {
        return $password !== ''
            && strpos($password, 'REPLACE_WITH_YOUR_') !== 0
            && $password !== 'CHANGE_ME_INFINITYFREE_PASSWORD';
    }
}

/**
 * Return the first available PDO connection.
 *
 * The connection is cached for reuse during the current request.
 *
 * @throws RuntimeException If no configured database is reachable.
 */
if (!function_exists('getAvailablePdoConnection')) {

    function getAvailablePdoConnection(): PDO
    {
        static $connection = null;

        if ($connection instanceof PDO) {
            return $connection;
        }

        if (!extension_loaded('pdo_mysql')) {
            error_log(
                '[DB CONNECTION FAILURE] PDO MySQL extension is not enabled.'
            );

            throw new RuntimeException(
                'PDO MySQL is unavailable on this hosting account.'
            );
        }

        foreach (getDatabaseServers() as $server) {

            $serverName = $server['name'];

            $requiredKeys = [
                'host',
                'database',
                'username',
                'password',
                'port',
                'charset',
            ];

            $configurationIsComplete = true;

            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $server)) {
                    $configurationIsComplete = false;
                    break;
                }
            }

            if (!$configurationIsComplete) {
                error_log(
                    '[DB CONNECTION FAILURE] ' .
                    $serverName .
                    ': incomplete database configuration.'
                );

                continue;
            }

            if (
                !isDatabasePasswordConfigured(
                    (string) $server['password']
                )
            ) {
                error_log(
                    '[DB CONNECTION FAILURE] ' .
                    $serverName .
                    ': database password is not configured.'
                );

                continue;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $server['host'],
                $server['port'],
                $server['database'],
                $server['charset']
            );

            try {
                $pdo = new PDO(
                    $dsn,
                    $server['username'],
                    $server['password'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                        PDO::ATTR_PERSISTENT         => false,
                        PDO::ATTR_TIMEOUT            => 10,
                    ]
                );

                // Verify that the database connection can execute a query.
                $pdo->query('SELECT 1');

                $connection = $pdo;

                // Record connection details without recording credentials.
                $GLOBALS['ACTIVE_DATABASE_SERVER'] = [
                    'name'     => $server['name'],
                    'host'     => $server['host'],
                    'database' => $server['database'],
                    'username' => $server['username'],
                    'port'     => $server['port'],
                ];

                error_log(
                    '[DB CONNECTION SUCCESS] Connected to ' .
                    $serverName .
                    ' (' . $server['host'] . ').'
                );

                return $connection;

            } catch (Throwable $e) {

                // Do not log database passwords.
                error_log(
                    '[DB CONNECTION FAILURE] ' .
                    $serverName .
                    ' (' . $server['host'] . '): ' .
                    $e->getMessage()
                );
            }
        }

        error_log(
            '[DB CONNECTION FAILURE] ' .
            'All configured database connections failed.'
        );

        throw new RuntimeException(
            'Unable to connect to any configured database. ' .
            'Check the hosting PHP error log and database configuration.'
        );
    }
}

/**
 * Get details of the database server used by the current request.
 */
if (!function_exists('getActiveDatabaseServer')) {

    function getActiveDatabaseServer(): ?array
    {
        return $GLOBALS['ACTIVE_DATABASE_SERVER'] ?? null;
    }
}

/**
 * Backwards-compatible connection helper.
 *
 * Existing scripts can continue using:
 * $pdo = getDatabaseConnection();
 */
if (!function_exists('getDatabaseConnection')) {

    function getDatabaseConnection(): PDO
    {
        return getAvailablePdoConnection();
    }
}
