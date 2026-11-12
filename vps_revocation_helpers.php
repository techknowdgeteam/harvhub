<?php
/**
 * Separate lifecycle support for account VPS and programme VPS.
 * Account history is stored in vps_revocations; programme history is stored in
 * programme_vps_revocations. The two ownership domains are never cross-linked.
 */
if (!function_exists('vpsRevocationEnsureSchema')) {
    function vpsRevocationEnsureSchema(PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS vps_revocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            vps_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            sub_account_id BIGINT UNSIGNED NOT NULL,
            registered_vps_id BIGINT UNSIGNED NULL,
            purchase_request_id BIGINT UNSIGNED NULL,
            previous_visibility TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('pending','restored','expired') NOT NULL DEFAULT 'pending',
            revoked_at DATETIME NOT NULL,
            scheduled_discontinuation_at DATETIME NOT NULL,
            restored_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_vps_revocation_owner (user_id,sub_account_id,status),
            KEY idx_vps_revocation_due (status,scheduled_discontinuation_at),
            KEY idx_vps_revocation_vps (vps_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS programme_vps_revocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            programme_vps_id BIGINT UNSIGNED NOT NULL,
            programme_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            sub_account_id BIGINT UNSIGNED NOT NULL,
            registered_vps_id BIGINT UNSIGNED NULL,
            purchase_request_id BIGINT UNSIGNED NULL,
            previous_visibility TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('pending','restored','expired') NOT NULL DEFAULT 'pending',
            revoked_at DATETIME NOT NULL,
            scheduled_discontinuation_at DATETIME NOT NULL,
            restored_at DATETIME NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_programme_vps_revocation_owner (programme_id,status),
            KEY idx_programme_vps_revocation_due (status,scheduled_discontinuation_at),
            KEY idx_programme_vps_revocation_vps (programme_vps_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('vpsRevocationTableExists')) {
    function vpsRevocationTableExists(PDO $pdo, string $table): bool {
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$table]);
        return (int)$q->fetchColumn() > 0;
    }
}

if (!function_exists('vpsRevocationBrokerLinked')) {
    function vpsRevocationBrokerLinked(PDO $pdo, string $type, int $userId, int $subId, int $programmeId = 0): bool {
        if ($type === 'programme') {
            $q = $pdo->prepare('SELECT broker,server,login,broker_password,disconnect_broker_request FROM programme WHERE id=? AND userid=? LIMIT 1');
            $q->execute([$programmeId, $userId]);
        } else {
            $q = $pdo->prepare('SELECT broker,server,login,broker_password,disconnect_broker_request FROM harvhub WHERE id=? AND sub_account_id=? LIMIT 1');
            $q->execute([$userId, $subId]);
        }
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
        return trim((string)($row['broker'] ?? '')) !== ''
            || trim((string)($row['server'] ?? '')) !== ''
            || trim((string)($row['login'] ?? '')) !== ''
            || (string)($row['broker_password'] ?? '') !== ''
            || (int)($row['disconnect_broker_request'] ?? 0) === 1;
    }
}

if (!function_exists('vpsRevocationLoadPending')) {
    function vpsRevocationLoadPending(PDO $pdo, string $type, int $userId, int $subId, int $programmeId = 0): array {
        if ($type === 'programme') {
            $sql = "SELECT x.*,rv.product_name,rv.provider_name,rv.plan_name,rv.plan_description,rv.cpu_type,
                           rv.vcpu_count,rv.ram_gb,rv.storage_type,rv.storage_gb,rv.network_port_speed_mbps,
                           rv.data_transfer,COALESCE(NULLIF(rv.server_location,''),op.server_location) AS snapshot_location,rv.region_code,rv.operating_system,
                           rv.virtualization_type,rv.ipv4_included,rv.ipv6_included,rv.backup_option,rv.snapshot_count,
                           rv.billing_period,COALESCE(rv.subscription_duration_days,op.subscription_duration) AS subscription_duration_days,rv.price,rv.currency,UNIX_TIMESTAMP(x.scheduled_discontinuation_at) AS scheduled_discontinuation_epoch
                    FROM programme_vps_revocations x
                    LEFT JOIN server_registered_vps rv ON rv.id=x.registered_vps_id
                    LEFT JOIN programme_vps op ON op.id=x.programme_vps_id
                    WHERE x.programme_id=? AND x.user_id=? AND x.sub_account_id=? AND x.status='pending'
                    ORDER BY x.revoked_at DESC";
            $q = $pdo->prepare($sql);
            $q->execute([$programmeId, $userId, $subId]);
        } else {
            $sql = "SELECT x.*,rv.product_name,rv.provider_name,rv.plan_name,rv.plan_description,rv.cpu_type,
                           rv.vcpu_count,rv.ram_gb,rv.storage_type,rv.storage_gb,rv.network_port_speed_mbps,
                           rv.data_transfer,COALESCE(NULLIF(rv.server_location,''),op.server_location) AS snapshot_location,rv.region_code,rv.operating_system,
                           rv.virtualization_type,rv.ipv4_included,rv.ipv6_included,rv.backup_option,rv.snapshot_count,
                           rv.billing_period,COALESCE(rv.subscription_duration_days,op.subscription_duration) AS subscription_duration_days,rv.price,rv.currency,UNIX_TIMESTAMP(x.scheduled_discontinuation_at) AS scheduled_discontinuation_epoch
                    FROM vps_revocations x
                    LEFT JOIN server_registered_vps rv ON rv.id=x.registered_vps_id
                    LEFT JOIN vps op ON op.id=x.vps_id
                    WHERE x.user_id=? AND x.sub_account_id=? AND x.status='pending'
                    ORDER BY x.revoked_at DESC";
            $q = $pdo->prepare($sql);
            $q->execute([$userId, $subId]);
        }
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('vpsRevocationStart')) {
    /** Returns ['blocked'=>true] when a broker is connected or its removal is pending. */
    function vpsRevocationStart(PDO $pdo, string $type, int $userId, int $subId, int $programmeId, int $opsId): array {
        if (!in_array($type, ['account','programme'], true) || $opsId <= 0) {
            throw new RuntimeException('Invalid VPS selection.');
        }
        $opsTable = $type === 'programme' ? 'programme_vps' : 'vps';
        $historyTable = $type === 'programme' ? 'programme_vps_revocations' : 'vps_revocations';
        $opsKey = $type === 'programme' ? 'programme_vps_id' : 'vps_id';
        $pdo->beginTransaction();
        try {
            if ($type === 'programme') {
                $lock = $pdo->prepare('SELECT * FROM programme_vps WHERE id=? AND programme_id=? AND user_id=? FOR UPDATE');
                $lock->execute([$opsId, $programmeId, $userId]);
            } else {
                $lock = $pdo->prepare('SELECT * FROM vps WHERE id=? AND user_id=? AND sub_account_id=? FOR UPDATE');
                $lock->execute([$opsId, $userId, $subId]);
            }
            $ops = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$ops) throw new RuntimeException('The VPS allocation could not be found.');

            if (vpsRevocationBrokerLinked($pdo, $type, $userId, $subId, $programmeId)) {
                $pdo->rollBack();
                return ['blocked'=>true, 'message'=>'Your broker is already linked to this VPS. Before proceeding, you must disconnect your broker first.'];
            }

            $pending = $pdo->prepare("SELECT id FROM `$historyTable` WHERE `$opsKey`=? AND status='pending' ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $pending->execute([$opsId]);
            if ($pending->fetchColumn()) throw new RuntimeException('This VPS is already pending revocation.');

            $registeredId = null;
            $purchaseId = null;
            if (vpsRevocationTableExists($pdo, 'server_registered_vps')) {
                if ($type === 'programme') {
                    $reg = $pdo->prepare("SELECT id,purchase_request_id FROM server_registered_vps
                        WHERE request_type='programme' AND (programme_vps_id=? OR (programme_id=? AND user_id=?))
                        ORDER BY CASE WHEN programme_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1 FOR UPDATE");
                    $reg->execute([$opsId,$programmeId,$userId,$opsId]);
                } else {
                    $reg = $pdo->prepare("SELECT id,purchase_request_id FROM server_registered_vps
                        WHERE request_type='account' AND (account_vps_id=? OR (user_id=? AND sub_account_id=? AND programme_id IS NULL))
                        ORDER BY CASE WHEN account_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1 FOR UPDATE");
                    $reg->execute([$opsId,$userId,$subId,$opsId]);
                }
                $regRow = $reg->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($regRow['id'])) $registeredId = (int)$regRow['id'];
                if (!empty($regRow['purchase_request_id'])) $purchaseId = (int)$regRow['purchase_request_id'];
            }
            // Older allocations sometimes had a registration snapshot but no purchase
            // back-reference. Recover the confirmed request for this exact owner/type.
            if ($purchaseId <= 0 && vpsRevocationTableExists($pdo, 'server_vps_purchase_requests')) {
                if ($type === 'programme') {
                    $rq = $pdo->prepare("SELECT id FROM server_vps_purchase_requests
                        WHERE request_type='programme' AND programme_id=? AND user_id=? AND sub_account_id=?
                          AND payment_status='payment-confirmed' AND COALESCE(request_status,'')<>'revoked_expired'
                        ORDER BY CASE WHEN registration_id=? THEN 0 WHEN registered_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1");
                    $rq->execute([$programmeId,$userId,$subId,$opsId,(int)($registeredId ?? 0)]);
                } else {
                    $rq = $pdo->prepare("SELECT id FROM server_vps_purchase_requests
                        WHERE request_type='account' AND user_id=? AND sub_account_id=? AND programme_id IS NULL
                          AND payment_status='payment-confirmed' AND COALESCE(request_status,'')<>'revoked_expired'
                        ORDER BY CASE WHEN registration_id=? THEN 0 WHEN registered_vps_id=? THEN 0 ELSE 1 END,id DESC LIMIT 1");
                    $rq->execute([$userId,$subId,$opsId,(int)($registeredId ?? 0)]);
                }
                $purchaseId = (int)($rq->fetchColumn() ?: 0);
            }

            $previousVisibility = (int)($ops['visibility'] ?? 0) === 1 ? 1 : 0;
            if ($type === 'programme') {
                $ins = $pdo->prepare("INSERT INTO programme_vps_revocations
                    (programme_vps_id,programme_id,user_id,sub_account_id,registered_vps_id,purchase_request_id,previous_visibility,status,revoked_at,scheduled_discontinuation_at)
                    VALUES (?,?,?,?,?,?,?,'pending',NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY))");
                $ins->execute([$opsId,$programmeId,$userId,$subId,$registeredId,$purchaseId,$previousVisibility]);
                $update = $pdo->prepare('UPDATE programme_vps SET visibility=0 WHERE id=? AND programme_id=? AND user_id=?');
                $update->execute([$opsId,$programmeId,$userId]);
            } else {
                $ins = $pdo->prepare("INSERT INTO vps_revocations
                    (vps_id,user_id,sub_account_id,registered_vps_id,purchase_request_id,previous_visibility,status,revoked_at,scheduled_discontinuation_at)
                    VALUES (?,?,?,?,?,?,'pending',NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY))");
                $ins->execute([$opsId,$userId,$subId,$registeredId,$purchaseId,$previousVisibility]);
                $update = $pdo->prepare('UPDATE vps SET visibility=0 WHERE id=? AND user_id=? AND sub_account_id=?');
                $update->execute([$opsId,$userId,$subId]);
            }
            $id = (int)$pdo->lastInsertId();
            $deadlineQ = $pdo->prepare("SELECT DATE_FORMAT(scheduled_discontinuation_at,'%Y-%m-%dT%H:%i:%s') FROM `$historyTable` WHERE id=?");
            $deadlineQ->execute([$id]);
            $deadline = (string)$deadlineQ->fetchColumn();
            $pdo->commit();
            return ['blocked'=>false,'id'=>$id,'scheduled_discontinuation_at'=>$deadline];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('vpsRevocationRestore')) {
    function vpsRevocationRestore(PDO $pdo, string $type, int $userId, int $subId, int $programmeId, int $revocationId): void {
        if (!in_array($type, ['account','programme'], true) || $revocationId <= 0) throw new RuntimeException('Invalid restoration request.');
        $historyTable = $type === 'programme' ? 'programme_vps_revocations' : 'vps_revocations';
        $opsTable = $type === 'programme' ? 'programme_vps' : 'vps';
        $opsKey = $type === 'programme' ? 'programme_vps_id' : 'vps_id';
        $pdo->beginTransaction();
        try {
            if ($type === 'programme') {
                $q = $pdo->prepare("SELECT * FROM programme_vps_revocations WHERE id=? AND programme_id=? AND user_id=? AND sub_account_id=? AND status='pending' AND scheduled_discontinuation_at>NOW() FOR UPDATE");
                $q->execute([$revocationId,$programmeId,$userId,$subId]);
            } else {
                $q = $pdo->prepare("SELECT * FROM vps_revocations WHERE id=? AND user_id=? AND sub_account_id=? AND status='pending' AND scheduled_discontinuation_at>NOW() FOR UPDATE");
                $q->execute([$revocationId,$userId,$subId]);
            }
            $revocation = $q->fetch(PDO::FETCH_ASSOC);
            if (!$revocation) throw new RuntimeException('This VPS is no longer available for restoration.');
            $opsId = (int)$revocation[$opsKey];
            if ($type === 'programme') {
                $opLock = $pdo->prepare('SELECT id FROM programme_vps WHERE id=? AND programme_id=? AND user_id=? FOR UPDATE');
                $opLock->execute([$opsId,$programmeId,$userId]);
            } else {
                $opLock = $pdo->prepare('SELECT id FROM vps WHERE id=? AND user_id=? AND sub_account_id=? FOR UPDATE');
                $opLock->execute([$opsId,$userId,$subId]);
            }
            if (!$opLock->fetchColumn()) throw new RuntimeException('The VPS allocation has already been discontinued.');
            $visibility = (int)($revocation['previous_visibility'] ?? 0) === 1 ? 1 : 0;
            if ($type === 'programme') {
                $up = $pdo->prepare('UPDATE programme_vps SET visibility=? WHERE id=? AND programme_id=? AND user_id=?');
                $up->execute([$visibility,$opsId,$programmeId,$userId]);
            } else {
                $up = $pdo->prepare('UPDATE vps SET visibility=? WHERE id=? AND user_id=? AND sub_account_id=?');
                $up->execute([$visibility,$opsId,$userId,$subId]);
            }
            $done = $pdo->prepare("UPDATE `$historyTable` SET status='restored',restored_at=NOW() WHERE id=? AND status='pending'");
            $done->execute([$revocationId]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('vpsRevocationProcessExpired')) {
    /** Process due account and programme revocations. Safe to run from cron and page loads. */
    function vpsRevocationProcessExpired(PDO $pdo, int $limitPerType = 30): int {
        $processed = 0;
        $sets = [
            ['type'=>'account','history'=>'vps_revocations','ops'=>'vps','ops_key'=>'vps_id'],
            ['type'=>'programme','history'=>'programme_vps_revocations','ops'=>'programme_vps','ops_key'=>'programme_vps_id']
        ];
        foreach ($sets as $set) {
            $q = $pdo->prepare("SELECT id FROM `{$set['history']}` WHERE status='pending' AND scheduled_discontinuation_at<=NOW() ORDER BY scheduled_discontinuation_at ASC LIMIT " . max(1,min(100,$limitPerType)));
            $q->execute();
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $pdo->beginTransaction();
                try {
                    $lock = $pdo->prepare("SELECT * FROM `{$set['history']}` WHERE id=? AND status='pending' AND scheduled_discontinuation_at<=NOW() FOR UPDATE");
                    $lock->execute([(int)$id]);
                    $row = $lock->fetch(PDO::FETCH_ASSOC);
                    if (!$row) {
                        $pdo->commit();
                        continue;
                    }
                    $opsId = (int)$row[$set['ops_key']];
                    $userId = (int)$row['user_id'];
                    $subId = (int)$row['sub_account_id'];
                    $purchaseId = (int)($row['purchase_request_id'] ?? 0);
                    if ($set['type'] === 'programme') {
                        $programmeId = (int)$row['programme_id'];
                        $opsLock = $pdo->prepare('SELECT id FROM programme_vps WHERE id=? AND programme_id=? FOR UPDATE');
                        $opsLock->execute([$opsId,$programmeId]);
                        if ($opsLock->fetchColumn()) {
                            if (vpsRevocationTableExists($pdo,'programme_vps_hosts_followers')) {
                                $pdo->prepare('DELETE FROM programme_vps_hosts_followers WHERE owner_programme_id=?')->execute([$programmeId]);
                            }
                            if (vpsRevocationTableExists($pdo,'programme_vps_hosts_requestors')) {
                                $pdo->prepare('DELETE FROM programme_vps_hosts_requestors WHERE owner_programme_id=?')->execute([$programmeId]);
                            }
                            $pdo->prepare('DELETE FROM programme_vps WHERE id=? AND programme_id=?')->execute([$opsId,$programmeId]);
                        }
                        if ($purchaseId > 0 && vpsRevocationTableExists($pdo,'server_vps_purchase_requests')) {
                            $rq = $pdo->prepare("SELECT vps_id FROM server_vps_purchase_requests WHERE id=? FOR UPDATE");
                            $rq->execute([$purchaseId]);
                            $planId = (int)($rq->fetchColumn() ?: 0);
                            $pdo->prepare("UPDATE server_vps_purchase_requests SET request_status='revoked_expired',buy_request=0 WHERE id=?")->execute([$purchaseId]);
                            if ($planId > 0 && vpsRevocationTableExists($pdo,'server_available_vps')) {
                                $pdo->prepare("UPDATE server_available_vps SET allocated_quantity=GREATEST(COALESCE(allocated_quantity,0)-1,0),status=IF(status='sold_out' AND GREATEST(COALESCE(allocated_quantity,0)-1,0)<stock_quantity,'published',status),buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests r WHERE r.vps_id=? AND r.buy_request=1),1,0) WHERE id=?")->execute([$planId,$planId]);
                            }
                        }
                        if (!empty($row['registered_vps_id']) && vpsRevocationTableExists($pdo,'server_registered_vps')) {
                            $snapshotQ = $pdo->prepare("SELECT purchase_request_id FROM server_registered_vps WHERE id=? AND request_type='programme' FOR UPDATE");
                            $snapshotQ->execute([(int)$row['registered_vps_id']]);
                            $snapshotPurchaseId = (int)($snapshotQ->fetchColumn() ?: 0);
                            $keepSnapshot = false;
                            if ($purchaseId > 0 && $snapshotPurchaseId === $purchaseId && vpsRevocationTableExists($pdo,'server_vps_purchase_requests')) {
                                $purchaseCheck = $pdo->prepare('SELECT id FROM server_vps_purchase_requests WHERE id=? LIMIT 1');
                                $purchaseCheck->execute([$purchaseId]);
                                $keepSnapshot = (bool)$purchaseCheck->fetchColumn();
                            }
                            if ($keepSnapshot) {
                                $pdo->prepare('UPDATE server_registered_vps SET programme_vps_id=NULL WHERE id=? AND programme_vps_id=?')->execute([(int)$row['registered_vps_id'],$opsId]);
                            } else {
                                $pdo->prepare("DELETE FROM server_registered_vps WHERE id=? AND request_type='programme'")->execute([(int)$row['registered_vps_id']]);
                            }
                        }
                    } else {
                        $opsLock = $pdo->prepare('SELECT id FROM vps WHERE id=? AND user_id=? AND sub_account_id=? FOR UPDATE');
                        $opsLock->execute([$opsId,$userId,$subId]);
                        if ($opsLock->fetchColumn()) {
                            if (vpsRevocationTableExists($pdo,'vps_hosts_followers')) {
                                $pdo->prepare('DELETE FROM vps_hosts_followers WHERE owner_id=? AND sub_account_id=?')->execute([$userId,$subId]);
                            }
                            if (vpsRevocationTableExists($pdo,'vps_hosts_requestors')) {
                                $pdo->prepare('DELETE FROM vps_hosts_requestors WHERE owner_id=? AND sub_account_id=?')->execute([$userId,$subId]);
                            }
                            $pdo->prepare('DELETE FROM vps WHERE id=? AND user_id=? AND sub_account_id=?')->execute([$opsId,$userId,$subId]);
                        }
                        if ($purchaseId > 0 && vpsRevocationTableExists($pdo,'server_vps_purchase_requests')) {
                            $rq = $pdo->prepare('SELECT vps_id FROM server_vps_purchase_requests WHERE id=? FOR UPDATE');
                            $rq->execute([$purchaseId]);
                            $planId = (int)($rq->fetchColumn() ?: 0);
                            $pdo->prepare("UPDATE server_vps_purchase_requests SET request_status='revoked_expired',buy_request=0 WHERE id=?")->execute([$purchaseId]);
                            if ($planId > 0 && vpsRevocationTableExists($pdo,'server_available_vps')) {
                                $pdo->prepare("UPDATE server_available_vps SET allocated_quantity=GREATEST(COALESCE(allocated_quantity,0)-1,0),status=IF(status='sold_out' AND GREATEST(COALESCE(allocated_quantity,0)-1,0)<stock_quantity,'published',status),buy_request=IF(EXISTS(SELECT 1 FROM server_vps_purchase_requests r WHERE r.vps_id=? AND r.buy_request=1),1,0) WHERE id=?")->execute([$planId,$planId]);
                            }
                        }
                        if (!empty($row['registered_vps_id']) && vpsRevocationTableExists($pdo,'server_registered_vps')) {
                            $snapshotQ = $pdo->prepare("SELECT purchase_request_id FROM server_registered_vps WHERE id=? AND request_type='account' FOR UPDATE");
                            $snapshotQ->execute([(int)$row['registered_vps_id']]);
                            $snapshotPurchaseId = (int)($snapshotQ->fetchColumn() ?: 0);
                            $keepSnapshot = false;
                            if ($purchaseId > 0 && $snapshotPurchaseId === $purchaseId && vpsRevocationTableExists($pdo,'server_vps_purchase_requests')) {
                                $purchaseCheck = $pdo->prepare('SELECT id FROM server_vps_purchase_requests WHERE id=? LIMIT 1');
                                $purchaseCheck->execute([$purchaseId]);
                                $keepSnapshot = (bool)$purchaseCheck->fetchColumn();
                            }
                            if ($keepSnapshot) {
                                $pdo->prepare('UPDATE server_registered_vps SET account_vps_id=NULL WHERE id=? AND account_vps_id=?')->execute([(int)$row['registered_vps_id'],$opsId]);
                            } else {
                                $pdo->prepare("DELETE FROM server_registered_vps WHERE id=? AND request_type='account'")->execute([(int)$row['registered_vps_id']]);
                            }
                        }
                    }
                    $done = $pdo->prepare("UPDATE `{$set['history']}` SET status='expired',completed_at=NOW() WHERE id=? AND status='pending'");
                    $done->execute([(int)$id]);
                    $pdo->commit();
                    $processed++;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[VPS revocation expiry] ' . $set['type'] . ' #' . (int)$id . ': ' . $e->getMessage());
                }
            }
        }
        return $processed;
    }
}
?>
