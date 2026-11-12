<?php
/**
 * VPS catalogue, purchase snapshot and private-provisioning schema.
 * The central server_registered_vps table is the authoritative provisioning record.
 * Account/programme pages may read its status but never write private provisioning data.
 */
if (!function_exists('serverVpsSchemaTableExists')) {
    function serverVpsSchemaTableExists(PDO $pdo, string $table): bool {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$table]);
        return (int)$q->fetchColumn() > 0;
    }
}
if (!function_exists('serverVpsSchemaColumns')) {
    function serverVpsSchemaColumns(PDO $pdo, string $table): array {
        $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$table]);
        return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN), true);
    }
}
if (!function_exists('serverVpsSchemaEnsureColumn')) {
    function serverVpsSchemaEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void {
        if (!isset(serverVpsSchemaColumns($pdo, $table)[$column])) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }
}
if (!function_exists('serverVpsSchemaEnsureIndex')) {
    function serverVpsSchemaEnsureIndex(PDO $pdo, string $table, string $index, string $definition): void {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?');
        $q->execute([$table, $index]);
        if ((int)$q->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD ' . $definition);
        }
    }
}
if (!function_exists('serverVpsPrivateIsComplete')) {
    function serverVpsPrivateIsComplete(array $values, int $ipv6Required = 0): bool {
        $ipv4 = trim((string)($values['ipv4_address'] ?? ''));
        $ipv6 = trim((string)($values['ipv6_address'] ?? ''));
        return trim((string)($values['computer_username'] ?? '')) !== ''
            && (string)($values['computer_password'] ?? '') !== ''
            && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && ($ipv6Required !== 1 || filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false);
    }
}
if (!function_exists('serverVpsSnapshotColumns')) {
    function serverVpsSnapshotColumns(PDO $pdo, array $values): array {
        $columns = serverVpsSchemaColumns($pdo, 'server_registered_vps');
        return array_intersect_key($values, $columns);
    }
}
if (!function_exists('serverVpsBackfillOperationalRow')) {
    /**
     * Ensure an already-existing operational VPS has a central registration row.
     * Existing private values are copied from the operational row, but no user page
     * can edit them. A matching confirmed purchase is attached where one exists.
     */
    function serverVpsBackfillOperationalRow(PDO $pdo, string $kind, array $ops): void {
        $kind = $kind === 'programme' ? 'programme' : 'account';
        $opsId = (int)($ops['id'] ?? 0);
        $userId = (int)($ops['user_id'] ?? 0);
        $subId = (int)($ops['sub_account_id'] ?? 0);
        if ($subId <= 0) $subId = $userId;
        $programmeId = $kind === 'programme' ? (int)($ops['programme_id'] ?? 0) : 0;
        if ($opsId <= 0 || $userId <= 0 || $subId <= 0 || ($kind === 'programme' && $programmeId <= 0)) return;

        $opsField = $kind === 'account' ? 'account_vps_id' : 'programme_vps_id';
        $existingQ = $pdo->prepare("SELECT * FROM server_registered_vps WHERE request_type=? AND `$opsField`=? ORDER BY id DESC LIMIT 1");
        $existingQ->execute([$kind, $opsId]);
        $central = $existingQ->fetch(PDO::FETCH_ASSOC) ?: null;

        // Find the most relevant confirmed purchase if the operational VPS came from one.
        $purchase = null;
        if (serverVpsSchemaTableExists($pdo, 'server_vps_purchase_requests')) {
            $requestColumns = serverVpsSchemaColumns($pdo, 'server_vps_purchase_requests');
            $priorityOrder = isset($requestColumns['registration_id'])
                ? 'ORDER BY CASE WHEN r.registration_id=? THEN 0 ELSE 1 END,r.id DESC LIMIT 1'
                : 'ORDER BY r.id DESC LIMIT 1';
            if ($kind === 'account') {
                $rq = $pdo->prepare("SELECT r.id AS request_id,r.vps_id,r.amount,r.currency,a.*
                    FROM server_vps_purchase_requests r
                    LEFT JOIN server_available_vps a ON a.id=r.vps_id
                    WHERE r.request_type='account' AND r.user_id=? AND r.sub_account_id=?
                      AND r.payment_status='payment-confirmed' AND COALESCE(r.request_status,'')<>'revoked_expired' {$priorityOrder}");
                $rq->execute(isset($requestColumns['registration_id']) ? [$userId, $subId, $opsId] : [$userId, $subId]);
            } else {
                $rq = $pdo->prepare("SELECT r.id AS request_id,r.vps_id,r.amount,r.currency,a.*
                    FROM server_vps_purchase_requests r
                    LEFT JOIN server_available_vps a ON a.id=r.vps_id
                    WHERE r.request_type='programme' AND r.programme_id=? AND r.payment_status='payment-confirmed'
                      AND COALESCE(r.request_status,'')<>'revoked_expired' {$priorityOrder}");
                $rq->execute(isset($requestColumns['registration_id']) ? [$programmeId, $opsId] : [$programmeId]);
            }
            $purchase = $rq->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$central && $purchase) {
            $findByPurchase = $pdo->prepare('SELECT * FROM server_registered_vps WHERE purchase_request_id=? ORDER BY id DESC LIMIT 1');
            $findByPurchase->execute([(int)$purchase['request_id']]);
            $central = $findByPurchase->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$central) {
            // Adopt an older central registration row for the same owner if its
            // operational VPS foreign key was never populated by the previous flow.
            if ($kind === 'account') {
                $legacy = $pdo->prepare("SELECT * FROM server_registered_vps
                    WHERE request_type='account' AND user_id=? AND sub_account_id=?
                      AND programme_id IS NULL AND (`$opsField` IS NULL OR `$opsField`=0)
                    ORDER BY id DESC LIMIT 1");
                $legacy->execute([$userId, $subId]);
            } else {
                $legacy = $pdo->prepare("SELECT * FROM server_registered_vps
                    WHERE request_type='programme' AND user_id=? AND sub_account_id=? AND programme_id=?
                      AND (`$opsField` IS NULL OR `$opsField`=0)
                    ORDER BY id DESC LIMIT 1");
                $legacy->execute([$userId, $subId, $programmeId]);
            }
            $central = $legacy->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($kind === 'account') {
            $ipv4 = trim((string)($ops['vps_ip_address'] ?? ''));
        } else {
            $ipv4 = trim((string)($ops['programme_vps_ip_address'] ?? ''));
            if ($ipv4 === '') $ipv4 = trim((string)($ops['vps_ip_address'] ?? ''));
        }
        $private = [
            'computer_username' => (string)($ops['computer_username'] ?? ''),
            'computer_password' => (string)($ops['computer_password'] ?? ''),
            'ipv4_address' => $ipv4,
            'ipv6_address' => (string)($ops['ipv6_address'] ?? '')
        ];
        $ipv6Required = (int)($purchase['ipv6_included'] ?? ($central['ipv6_included'] ?? 0));

        if ($central) {
            // Fill only missing central private values from the legacy operational row.
            // Existing central values remain authoritative if already populated.
            foreach (['computer_username', 'computer_password', 'ipv4_address', 'ipv6_address'] as $field) {
                if (trim((string)($central[$field] ?? '')) !== '') $private[$field] = (string)$central[$field];
            }
            $complete = serverVpsPrivateIsComplete($private, $ipv6Required);
            $sets = ["`$opsField`=?", 'computer_username=?', 'computer_password=?', 'ipv4_address=?', 'ipv6_address=?', 'registration_status=?', 'registration_completed_at=IF(?,COALESCE(registration_completed_at,NOW()),NULL)'];
            $params = [$opsId, $private['computer_username'], $private['computer_password'], $private['ipv4_address'], $private['ipv6_address'] !== '' ? $private['ipv6_address'] : null, $complete ? 'registered' : 'needs_attention', $complete ? 1 : 0];
            if ($purchase && empty($central['purchase_request_id'])) {
                $sets[] = 'purchase_request_id=?';
                $params[] = (int)$purchase['request_id'];
            }
            $params[] = (int)$central['id'];
            $pdo->prepare('UPDATE server_registered_vps SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
            if ($purchase) {
                $requestColumns = serverVpsSchemaColumns($pdo, 'server_vps_purchase_requests');
                $requestSets = [];
                $requestParams = [];
                if (isset($requestColumns['registered_vps_id'])) { $requestSets[] = 'registered_vps_id=?'; $requestParams[] = (int)$central['id']; }
                if (isset($requestColumns['registration_id'])) { $requestSets[] = 'registration_id=?'; $requestParams[] = $opsId; }
                if ($requestSets) {
                    $requestParams[] = (int)$purchase['request_id'];
                    $pdo->prepare('UPDATE server_vps_purchase_requests SET ' . implode(',', $requestSets) . ' WHERE id=?')->execute($requestParams);
                }
            }
            return;
        }

        $complete = serverVpsPrivateIsComplete($private, $ipv6Required);
        $snapshot = [
            'purchase_request_id' => $purchase ? (int)$purchase['request_id'] : null,
            'advertised_vps_id' => $purchase ? (int)($purchase['vps_id'] ?? 0) : 0,
            'request_type' => $kind,
            'user_id' => $userId,
            'sub_account_id' => $subId,
            'programme_id' => $kind === 'programme' ? $programmeId : null,
            'account_vps_id' => $kind === 'account' ? $opsId : null,
            'programme_vps_id' => $kind === 'programme' ? $opsId : null,
            'product_name' => $purchase['product_name'] ?? 'Existing VPS',
            'provider_name' => $purchase['provider_name'] ?? null,
            'plan_name' => $purchase['plan_name'] ?? 'Existing allocation',
            'plan_description' => $purchase['plan_description'] ?? 'Existing VPS allocation migrated from the operational VPS records.',
            'cpu_type' => $purchase['cpu_type'] ?? null,
            'vcpu_count' => (int)($purchase['vcpu_count'] ?? 0),
            'ram_gb' => (float)($purchase['ram_gb'] ?? 0),
            'storage_type' => $purchase['storage_type'] ?? null,
            'storage_gb' => (float)($purchase['storage_gb'] ?? 0),
            'network_port_speed_mbps' => isset($purchase['network_port_speed_mbps']) ? (int)$purchase['network_port_speed_mbps'] : null,
            'data_transfer' => $purchase['data_transfer'] ?? null,
            'server_location' => (string)($purchase['server_location'] ?? ($ops['server_location'] ?? '')),
            'region_code' => $purchase['region_code'] ?? null,
            'operating_system' => $purchase['operating_system'] ?? null,
            'virtualization_type' => $purchase['virtualization_type'] ?? null,
            'ipv4_included' => (int)($purchase['ipv4_included'] ?? 0),
            'ipv6_included' => $ipv6Required,
            'backup_option' => $purchase['backup_option'] ?? null,
            'snapshot_count' => (int)($purchase['snapshot_count'] ?? 0),
            'billing_period' => $purchase['billing_period'] ?? 'monthly',
            'subscription_duration_days' => (int)($purchase['subscription_duration_days'] ?? ($ops['subscription_duration'] ?? 30)),
            'price' => (float)($purchase['price'] ?? ($purchase['amount'] ?? 0)),
            'currency' => $purchase['currency'] ?? 'USD',
            'setup_fee' => (float)($purchase['setup_fee'] ?? 0),
            'stock_quantity' => (int)($purchase['stock_quantity'] ?? 0),
            'computer_username' => $private['computer_username'],
            'computer_password' => $private['computer_password'],
            'ipv4_address' => $private['ipv4_address'],
            'ipv6_address' => $private['ipv6_address'] !== '' ? $private['ipv6_address'] : null,
            'registration_status' => $complete ? 'registered' : 'needs_attention',
            'registration_completed_at' => $complete ? date('Y-m-d H:i:s') : null
        ];
        $snapshot = serverVpsSnapshotColumns($pdo, $snapshot);
        if (!$snapshot) return;
        $names = array_keys($snapshot);
        $sql = 'INSERT INTO server_registered_vps (`' . implode('`,`', $names) . '`) VALUES (' . implode(',', array_fill(0, count($names), '?')) . ')';
        $insert = $pdo->prepare($sql);
        $insert->execute(array_values($snapshot));
        $registeredId = (int)$pdo->lastInsertId();
        if ($purchase && $registeredId > 0) {
            $requestColumns = serverVpsSchemaColumns($pdo, 'server_vps_purchase_requests');
            $requestSets = [];
            $requestParams = [];
            if (isset($requestColumns['registered_vps_id'])) { $requestSets[] = 'registered_vps_id=?'; $requestParams[] = $registeredId; }
            if (isset($requestColumns['registration_id'])) { $requestSets[] = 'registration_id=?'; $requestParams[] = $opsId; }
            if ($requestSets) {
                $requestParams[] = (int)$purchase['request_id'];
                $pdo->prepare('UPDATE server_vps_purchase_requests SET ' . implode(',', $requestSets) . ' WHERE id=?')->execute($requestParams);
            }
        }
    }
}

if (!function_exists('serverVpsBackfillConfirmedPurchases')) {
    /** Create central, unregistered snapshots for older confirmed purchases. */
    function serverVpsBackfillConfirmedPurchases(PDO $pdo): void {
        if (!serverVpsSchemaTableExists($pdo, 'server_vps_purchase_requests')) return;
        $sql = "SELECT r.id AS request_id,r.vps_id,r.request_type,r.user_id,r.sub_account_id,r.programme_id,
                       r.amount AS request_amount,r.currency AS request_currency,a.*
                FROM server_vps_purchase_requests r
                LEFT JOIN server_available_vps a ON a.id=r.vps_id
                LEFT JOIN server_registered_vps rv ON rv.purchase_request_id=r.id
                WHERE r.payment_status='payment-confirmed' AND COALESCE(r.request_status,'')<>'revoked_expired' AND rv.id IS NULL
                ORDER BY r.id ASC";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $kind = (string)($row['request_type'] ?? '');
            if (!in_array($kind, ['account','programme'], true)) continue;
            $uid = (int)($row['user_id'] ?? 0);
            $subId = (int)($row['sub_account_id'] ?? $uid);
            if ($uid <= 0 || $subId <= 0) continue;
            $snapshot = [
                'purchase_request_id'=>(int)$row['request_id'],
                'advertised_vps_id'=>(int)($row['vps_id'] ?? 0),
                'request_type'=>$kind,'user_id'=>$uid,'sub_account_id'=>$subId,
                'programme_id'=>$kind==='programme'?(int)($row['programme_id'] ?? 0):null,
                'account_vps_id'=>null,'programme_vps_id'=>null,
                'product_name'=>$row['product_name'] ?? 'VPS Plan',
                'provider_name'=>$row['provider_name'] ?? null,
                'plan_name'=>$row['plan_name'] ?? 'Pending VPS allocation',
                'plan_description'=>$row['plan_description'] ?? null,
                'cpu_type'=>$row['cpu_type'] ?? null,'vcpu_count'=>(int)($row['vcpu_count'] ?? 0),
                'ram_gb'=>(float)($row['ram_gb'] ?? 0),'storage_type'=>$row['storage_type'] ?? null,
                'storage_gb'=>(float)($row['storage_gb'] ?? 0),
                'network_port_speed_mbps'=>isset($row['network_port_speed_mbps'])?(int)$row['network_port_speed_mbps']:null,
                'data_transfer'=>$row['data_transfer'] ?? null,
                'server_location'=>$row['server_location'] ?? '',
                'region_code'=>$row['region_code'] ?? null,'operating_system'=>$row['operating_system'] ?? null,
                'virtualization_type'=>$row['virtualization_type'] ?? null,
                'ipv4_included'=>(int)($row['ipv4_included'] ?? 0),'ipv6_included'=>(int)($row['ipv6_included'] ?? 0),
                'backup_option'=>$row['backup_option'] ?? null,'snapshot_count'=>(int)($row['snapshot_count'] ?? 0),
                'billing_period'=>$row['billing_period'] ?? 'monthly',
                'subscription_duration_days'=>(int)($row['subscription_duration_days'] ?? 30),
                'price'=>(float)($row['price'] ?? ($row['request_amount'] ?? 0)),
                'currency'=>$row['currency'] ?? ($row['request_currency'] ?? 'USD'),
                'setup_fee'=>(float)($row['setup_fee'] ?? 0),'stock_quantity'=>(int)($row['stock_quantity'] ?? 0),
                'registration_status'=>'needs_attention'
            ];
            $snapshot = serverVpsSnapshotColumns($pdo, $snapshot);
            if (!$snapshot) continue;
            $names = array_keys($snapshot);
            $sqlInsert = 'INSERT IGNORE INTO server_registered_vps (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')';
            $pdo->prepare($sqlInsert)->execute(array_values($snapshot));
            $lookup = $pdo->prepare('SELECT id FROM server_registered_vps WHERE purchase_request_id=? LIMIT 1');
            $lookup->execute([(int)$row['request_id']]);
            $id = (int)($lookup->fetchColumn() ?: 0);
            if ($id > 0) {
                $pdo->prepare('UPDATE server_vps_purchase_requests SET registered_vps_id=? WHERE id=?')
                    ->execute([$id,(int)$row['request_id']]);
            }
        }
    }
}

if (!function_exists('serverVpsEnsureSchema')) {
    function serverVpsEnsureSchema(PDO $pdo): void {
        if (!serverVpsSchemaTableExists($pdo, 'server_available_vps')) {
            throw new RuntimeException('The server_available_vps table is missing. Run the existing VPS catalogue migration first.');
        }

        serverVpsSchemaEnsureColumn($pdo, 'server_available_vps', 'setup_fee', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00');
        serverVpsSchemaEnsureColumn($pdo, 'server_available_vps', 'operating_system', 'VARCHAR(150) NULL');

        $pdo->exec("CREATE TABLE IF NOT EXISTS server_registered_vps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            purchase_request_id BIGINT UNSIGNED NULL DEFAULT NULL,
            advertised_vps_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            request_type ENUM('account','programme') NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            sub_account_id BIGINT UNSIGNED NOT NULL,
            programme_id BIGINT UNSIGNED NULL,
            account_vps_id BIGINT UNSIGNED NULL,
            programme_vps_id BIGINT UNSIGNED NULL,
            product_name VARCHAR(150) NOT NULL DEFAULT '',
            provider_name VARCHAR(120) NULL,
            plan_name VARCHAR(120) NOT NULL DEFAULT '',
            plan_description TEXT NULL,
            cpu_type VARCHAR(150) NULL,
            vcpu_count INT UNSIGNED NOT NULL DEFAULT 0,
            ram_gb DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            storage_type VARCHAR(80) NULL,
            storage_gb DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            network_port_speed_mbps INT UNSIGNED NULL,
            data_transfer VARCHAR(190) NULL,
            server_location VARCHAR(190) NOT NULL DEFAULT '',
            region_code VARCHAR(100) NULL,
            operating_system VARCHAR(150) NULL,
            virtualization_type VARCHAR(120) NULL,
            ipv4_included TINYINT(1) NOT NULL DEFAULT 0,
            ipv6_included TINYINT(1) NOT NULL DEFAULT 0,
            backup_option VARCHAR(190) NULL,
            snapshot_count INT UNSIGNED NOT NULL DEFAULT 0,
            billing_period VARCHAR(40) NOT NULL DEFAULT 'monthly',
            subscription_duration_days INT UNSIGNED NOT NULL DEFAULT 30,
            price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            currency CHAR(3) NOT NULL DEFAULT 'USD',
            setup_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
            computer_username VARCHAR(255) NULL,
            computer_password TEXT NULL,
            ipv4_address VARCHAR(45) NULL,
            ipv6_address VARCHAR(45) NULL,
            registration_status ENUM('needs_attention','registered') NOT NULL DEFAULT 'needs_attention',
            registration_completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_server_registered_purchase (purchase_request_id),
            KEY idx_server_registered_account (request_type,user_id,sub_account_id),
            KEY idx_server_registered_programme (request_type,programme_id),
            KEY idx_server_registered_status (registration_status,updated_at),
            KEY idx_server_registered_plan (advertised_vps_id),
            KEY idx_server_registered_account_vps (account_vps_id),
            KEY idx_server_registered_programme_vps (programme_vps_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // CREATE TABLE IF NOT EXISTS does not repair an older table. Add every field
        // needed by the current account/programme and server provisioning workflows.
        $registeredDefinitions = [
            'purchase_request_id'=>'BIGINT UNSIGNED NULL DEFAULT NULL', 'advertised_vps_id'=>'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'request_type'=>"ENUM('account','programme') NOT NULL DEFAULT 'account'", 'user_id'=>'BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'sub_account_id'=>'BIGINT UNSIGNED NOT NULL DEFAULT 0', 'programme_id'=>'BIGINT UNSIGNED NULL',
            'account_vps_id'=>'BIGINT UNSIGNED NULL', 'programme_vps_id'=>'BIGINT UNSIGNED NULL',
            'product_name'=>"VARCHAR(150) NOT NULL DEFAULT ''", 'provider_name'=>'VARCHAR(120) NULL',
            'plan_name'=>"VARCHAR(120) NOT NULL DEFAULT ''", 'plan_description'=>'TEXT NULL', 'cpu_type'=>'VARCHAR(150) NULL',
            'vcpu_count'=>'INT UNSIGNED NOT NULL DEFAULT 0', 'ram_gb'=>'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'storage_type'=>'VARCHAR(80) NULL', 'storage_gb'=>'DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'network_port_speed_mbps'=>'INT UNSIGNED NULL', 'data_transfer'=>'VARCHAR(190) NULL',
            'server_location'=>"VARCHAR(190) NOT NULL DEFAULT ''", 'region_code'=>'VARCHAR(100) NULL',
            'operating_system'=>'VARCHAR(150) NULL', 'virtualization_type'=>'VARCHAR(120) NULL',
            'ipv4_included'=>'TINYINT(1) NOT NULL DEFAULT 0', 'ipv6_included'=>'TINYINT(1) NOT NULL DEFAULT 0',
            'backup_option'=>'VARCHAR(190) NULL', 'snapshot_count'=>'INT UNSIGNED NOT NULL DEFAULT 0',
            'billing_period'=>"VARCHAR(40) NOT NULL DEFAULT 'monthly'", 'subscription_duration_days'=>'INT UNSIGNED NOT NULL DEFAULT 30',
            'price'=>'DECIMAL(14,2) NOT NULL DEFAULT 0.00', 'currency'=>"CHAR(3) NOT NULL DEFAULT 'USD'",
            'setup_fee'=>'DECIMAL(14,2) NOT NULL DEFAULT 0.00', 'stock_quantity'=>'INT UNSIGNED NOT NULL DEFAULT 0',
            'computer_username'=>'VARCHAR(255) NULL', 'computer_password'=>'TEXT NULL', 'ipv4_address'=>'VARCHAR(45) NULL',
            'ipv6_address'=>'VARCHAR(45) NULL', 'registration_status'=>"ENUM('needs_attention','registered') NOT NULL DEFAULT 'needs_attention'",
            'registration_completed_at'=>'DATETIME NULL', 'created_at'=>'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at'=>'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        foreach ($registeredDefinitions as $column=>$definition) {
            serverVpsSchemaEnsureColumn($pdo, 'server_registered_vps', $column, $definition);
        }
        // Permit snapshots for legacy VPS rows that predate purchase requests.
        $meta = $pdo->prepare("SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='server_registered_vps' AND column_name='purchase_request_id'");
        $meta->execute();
        if (strtoupper((string)$meta->fetchColumn()) !== 'YES') {
            $pdo->exec('ALTER TABLE server_registered_vps MODIFY purchase_request_id BIGINT UNSIGNED NULL DEFAULT NULL');
        }
        serverVpsSchemaEnsureIndex($pdo, 'server_registered_vps', 'idx_server_registered_account_vps', 'KEY idx_server_registered_account_vps (account_vps_id)');
        serverVpsSchemaEnsureIndex($pdo, 'server_registered_vps', 'idx_server_registered_programme_vps', 'KEY idx_server_registered_programme_vps (programme_vps_id)');

        if (serverVpsSchemaTableExists($pdo, 'server_vps_purchase_requests')) {
            serverVpsSchemaEnsureColumn($pdo, 'server_vps_purchase_requests', 'registered_vps_id', 'BIGINT UNSIGNED NULL');
            serverVpsSchemaEnsureColumn($pdo, 'server_vps_purchase_requests', 'registration_id', 'BIGINT UNSIGNED NULL');
        }
        if (serverVpsSchemaTableExists($pdo, 'vps')) {
            serverVpsSchemaEnsureColumn($pdo, 'vps', 'vps_ip_address', 'VARCHAR(255) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'vps', 'ipv6_address', 'VARCHAR(45) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'vps', 'computer_username', 'VARCHAR(255) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'vps', 'computer_password', 'TEXT NULL');
        }
        if (serverVpsSchemaTableExists($pdo, 'programme_vps')) {
            serverVpsSchemaEnsureColumn($pdo, 'programme_vps', 'programme_vps_ip_address', 'VARCHAR(255) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'programme_vps', 'vps_ip_address', 'VARCHAR(255) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'programme_vps', 'ipv6_address', 'VARCHAR(45) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'programme_vps', 'computer_username', 'VARCHAR(255) NULL');
            serverVpsSchemaEnsureColumn($pdo, 'programme_vps', 'computer_password', 'TEXT NULL');
        }

        // Confirmed purchases from the old flow become central unregistered allocations.
        if (serverVpsSchemaTableExists($pdo, 'server_vps_purchase_requests')) {
            try { serverVpsBackfillConfirmedPurchases($pdo); }
            catch (Throwable $e) { error_log('Confirmed VPS purchase backfill failed: ' . $e->getMessage()); }
        }

        // Reconcile every existing operational VPS with the central registration ledger.
        // This also repairs previously unlinked programme allocations and is idempotent.
        if (serverVpsSchemaTableExists($pdo, 'vps')) {
            try {
                $rows = $pdo->query('SELECT * FROM vps ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    try { serverVpsBackfillOperationalRow($pdo, 'account', $row); }
                    catch (Throwable $rowError) { error_log('Account VPS row #' . (int)($row['id'] ?? 0) . ' could not be reconciled: ' . $rowError->getMessage()); }
                }
            } catch (Throwable $e) {
                error_log('Account VPS provisioning backfill failed: ' . $e->getMessage());
            }
        }
        if (serverVpsSchemaTableExists($pdo, 'programme_vps')) {
            try {
                $rows = $pdo->query('SELECT * FROM programme_vps ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    try { serverVpsBackfillOperationalRow($pdo, 'programme', $row); }
                    catch (Throwable $rowError) { error_log('Programme VPS row #' . (int)($row['id'] ?? 0) . ' could not be reconciled: ' . $rowError->getMessage()); }
                }
            } catch (Throwable $e) {
                error_log('Programme VPS provisioning backfill failed: ' . $e->getMessage());
            }
        }

        // Repair confirmed purchases that have a snapshot but have not yet had its
        // back-reference written, without creating any operational VPS prematurely.
        if (serverVpsSchemaTableExists($pdo, 'server_vps_purchase_requests')) {
            try {
                $pdo->exec("UPDATE server_vps_purchase_requests r
                    INNER JOIN server_registered_vps rv ON rv.purchase_request_id=r.id
                    SET r.registered_vps_id=rv.id
                    WHERE r.registered_vps_id IS NULL AND r.payment_status='payment-confirmed'");
            } catch (Throwable $e) {
                error_log('VPS purchase-to-registration link repair failed: ' . $e->getMessage());
            }
        }
    }
}
?>
