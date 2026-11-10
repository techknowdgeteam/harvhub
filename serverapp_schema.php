<?php
/**
 * HarvHub Server App schema hotfix: table-only primary-key-safe bootstrap (2026-11-10).
 * Relational schema bootstrap for Server App configuration.
 * Payment methods and tiers use general-purpose, single-row relational records.
 * No configuration value is persisted as JSON or as an aggregate text structure.
 */
if (!function_exists('serverAppSchemaTableExists')) {
    function serverAppSchemaTableExists(PDO $pdo, string $table): bool {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $q->execute([$table]);
        return (int)$q->fetchColumn() > 0;
    }
}
if (!function_exists('serverAppSchemaColumns')) {
    function serverAppSchemaColumns(PDO $pdo, string $table): array {
        $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
        $q->execute([$table]);
        return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
    }
}
if (!function_exists('serverAppSchemaEnsureColumn')) {
    function serverAppSchemaEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void {
        $columns = serverAppSchemaColumns($pdo, $table);
        if (!isset($columns[$column])) $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }
}
if (!function_exists('serverAppSchemaEnsureIndex')) {
    function serverAppSchemaEnsureIndex(PDO $pdo, string $table, string $indexName, string $definition): void {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
        $q->execute([$table, $indexName]);
        if ((int)$q->fetchColumn() === 0) $pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $definition);
    }
}
if (!function_exists('serverAppEnsureConfigurationSchema')) {
    function serverAppEnsureConfigurationSchema(PDO $pdo): void {
        $tables = [
            'server_theme_settings' => "CREATE TABLE server_theme_settings (
                id TINYINT UNSIGNED NOT NULL,
                dark_mode TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_admin_credentials' => "CREATE TABLE server_admin_credentials (
                id TINYINT UNSIGNED NOT NULL,
                admin_login_id VARCHAR(190) NOT NULL DEFAULT 'admin',
                admin_password_hash VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_mailer_settings' => "CREATE TABLE server_mailer_settings (
                id TINYINT UNSIGNED NOT NULL,
                mailer_email VARCHAR(255) NOT NULL DEFAULT '',
                mailer_password TEXT NOT NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_requirements' => "CREATE TABLE server_requirements (
                id TINYINT UNSIGNED NOT NULL,
                minimum_deposit DECIMAL(14,2) NOT NULL DEFAULT 0,
                min_broker_balance DECIMAL(14,2) NOT NULL DEFAULT 30,
                contract_duration INT UNSIGNED NOT NULL DEFAULT 30,
                server_share_percent DECIMAL(7,3) NOT NULL DEFAULT 30,
                user_share_percent DECIMAL(7,3) NOT NULL DEFAULT 70,
                min_profit_for_split DECIMAL(14,2) NOT NULL DEFAULT 30,
                expiry_threshold_days INT UNSIGNED NOT NULL DEFAULT 5,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_operational_settings' => "CREATE TABLE server_operational_settings (
                id TINYINT UNSIGNED NOT NULL,
                day_challenge_duration INT UNSIGNED NOT NULL DEFAULT 0,
                weekly_challenge_duration INT UNSIGNED NOT NULL DEFAULT 0,
                monthly_challenge_duration INT UNSIGNED NOT NULL DEFAULT 0,
                trades_setup_per_group INT UNSIGNED NOT NULL DEFAULT 100,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_payment_methods' => "CREATE TABLE server_payment_methods (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                method_name VARCHAR(190) NOT NULL,
                account_or_address LONGTEXT NULL,
                additional_info LONGTEXT NULL,
                company_name VARCHAR(190) NOT NULL DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_server_payment_methods_order (sort_order, id),
                KEY idx_server_payment_methods_enabled (is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_brokers' => "CREATE TABLE server_brokers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                broker_name VARCHAR(190) NOT NULL,
                broker_url VARCHAR(2048) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY uq_server_broker_name (broker_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'server_tiers' => "CREATE TABLE server_tiers (
                tier INT UNSIGNED NOT NULL,
                minimum_deposit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                maximum_revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                maximum_deposit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                description TEXT NULL,
                PRIMARY KEY (tier)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($tables as $name => $sql) {
            if (!serverAppSchemaTableExists($pdo, $name)) $pdo->exec($sql);
        }

        // Do not silently blend the old crypto-specific / field-matrix schemas into
        // the new design. The supplied one-time SQL migration converts those tables.
        $paymentColumns = serverAppSchemaColumns($pdo, 'server_payment_methods');
        $requiredPayment = ['id','method_name','account_or_address','additional_info','company_name','sort_order','is_enabled'];
        if (array_diff($requiredPayment, array_keys($paymentColumns))) {
            throw new RuntimeException('server_payment_methods is using the old structure. Run server_configuration_general_tables_migration.sql once before loading Server Configuration.');
        }
        $tierColumns = serverAppSchemaColumns($pdo, 'server_tiers');
        $requiredTier = ['tier','minimum_deposit','maximum_revenue','maximum_deposit','description'];
        if (array_diff($requiredTier, array_keys($tierColumns))) {
            throw new RuntimeException('server_tiers is using the old tier structure. Run server_configuration_general_tables_migration.sql once before loading Server Configuration.');
        }

        // Repair other independent singleton tables after interrupted installations.
        $repairs = [
            'server_theme_settings' => ['dark_mode' => 'TINYINT(1) NOT NULL DEFAULT 0'],
            'server_admin_credentials' => ['admin_login_id' => "VARCHAR(190) NOT NULL DEFAULT 'admin'", 'admin_password_hash' => "VARCHAR(255) NOT NULL DEFAULT ''"],
            'server_mailer_settings' => ['mailer_email' => "VARCHAR(255) NOT NULL DEFAULT ''", 'mailer_password' => 'TEXT NULL'],
            'server_requirements' => ['minimum_deposit' => 'DECIMAL(14,2) NOT NULL DEFAULT 0', 'min_broker_balance' => 'DECIMAL(14,2) NOT NULL DEFAULT 30', 'contract_duration' => 'INT UNSIGNED NOT NULL DEFAULT 30', 'server_share_percent' => 'DECIMAL(7,3) NOT NULL DEFAULT 30', 'user_share_percent' => 'DECIMAL(7,3) NOT NULL DEFAULT 70', 'min_profit_for_split' => 'DECIMAL(14,2) NOT NULL DEFAULT 30', 'expiry_threshold_days' => 'INT UNSIGNED NOT NULL DEFAULT 5'],
            'server_operational_settings' => ['day_challenge_duration' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'weekly_challenge_duration' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'monthly_challenge_duration' => 'INT UNSIGNED NOT NULL DEFAULT 0', 'trades_setup_per_group' => 'INT UNSIGNED NOT NULL DEFAULT 100'],
            'server_payment_methods' => ['method_name' => "VARCHAR(190) NOT NULL DEFAULT ''", 'account_or_address' => 'LONGTEXT NULL', 'additional_info' => 'LONGTEXT NULL', 'company_name' => "VARCHAR(190) NOT NULL DEFAULT ''", 'sort_order' => 'INT NOT NULL DEFAULT 0', 'is_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1'],
            'server_brokers' => ['broker_name' => "VARCHAR(190) NOT NULL DEFAULT ''", 'broker_url' => "VARCHAR(2048) NOT NULL DEFAULT ''", 'sort_order' => 'INT NOT NULL DEFAULT 0', 'is_enabled' => 'TINYINT(1) NOT NULL DEFAULT 1']
        ];
        foreach ($repairs as $table => $definitions) {
            foreach ($definitions as $column => $definition) serverAppSchemaEnsureColumn($pdo, $table, $column, $definition);
        }

        // The tier key itself is the row ID / display position (1, 2, 3, ...).
        // No dynamic field definitions or tier-value child tables are used.
        serverAppSchemaEnsureIndex($pdo, 'server_brokers', 'uq_server_broker_name', 'UNIQUE KEY uq_server_broker_name (broker_name)');

        // Seed singleton rows so consumers can rely on one independent relational row.
        $defaults = [
            'server_theme_settings' => 'INSERT IGNORE INTO server_theme_settings (id,dark_mode) VALUES (1,0)',
            'server_admin_credentials' => "INSERT IGNORE INTO server_admin_credentials (id,admin_login_id,admin_password_hash) VALUES (1,'admin','')",
            'server_mailer_settings' => "INSERT IGNORE INTO server_mailer_settings (id,mailer_email,mailer_password) VALUES (1,'','')",
            'server_requirements' => 'INSERT IGNORE INTO server_requirements (id) VALUES (1)',
            'server_operational_settings' => 'INSERT IGNORE INTO server_operational_settings (id) VALUES (1)'
        ];
        foreach ($defaults as $sql) $pdo->exec($sql);
    }
}
