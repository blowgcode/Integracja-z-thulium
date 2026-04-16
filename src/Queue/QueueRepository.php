<?php

declare(strict_types=1);

namespace ThuliumBridge\Queue;

use PDO;

final class QueueRepository
{
    private readonly string $driver;

    public function __construct(private readonly PDO $pdo)
    {
        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /** @return list<array<string,mixed>> */
    public function claimBatch(int $limit, int $lockTimeoutSeconds): array
    {
        $this->pdo->beginTransaction();

        $sql = $this->driver === 'sqlite'
            ? "SELECT id FROM thulium_sync_queue WHERE status = 'pending' AND available_at <= CURRENT_TIMESTAMP ORDER BY available_at ASC, id ASC LIMIT :limit"
            : "SELECT id
               FROM thulium_sync_queue
               WHERE (
                  (status = 'pending' AND available_at <= CURRENT_TIMESTAMP)
                  OR
                  (status = 'processing' AND locked_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :timeout SECOND))
               )
               ORDER BY available_at ASC, id ASC
               LIMIT :limit
               FOR UPDATE";
        $select = $this->pdo->prepare($sql);
        if ($this->driver !== 'sqlite') {
            $select->bindValue('timeout', $lockTimeoutSeconds, PDO::PARAM_INT);
        }
        $select->bindValue('limit', $limit, PDO::PARAM_INT);
        $select->execute();

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $select->fetchAll());

        if ($ids === []) {
            $this->pdo->commit();
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $this->pdo->prepare("UPDATE thulium_sync_queue SET status = 'processing', locked_at = CURRENT_TIMESTAMP, attempts = attempts + 1 WHERE id IN ($placeholders)");
        $update->execute($ids);

        $fetch = $this->pdo->prepare("SELECT * FROM thulium_sync_queue WHERE id IN ($placeholders) ORDER BY id ASC");
        $fetch->execute($ids);
        $rows = $fetch->fetchAll();

        $this->pdo->commit();

        return $rows;
    }

    public function markDone(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE thulium_sync_queue SET status = 'done', processed_at = CURRENT_TIMESTAMP, locked_at = NULL, error_message = NULL WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function markRetry(int $id, int $backoffSeconds, string $error): void
    {
        $retrySql = $this->driver === 'sqlite'
            ? 'UPDATE thulium_sync_queue SET status = "pending", available_at = datetime(CURRENT_TIMESTAMP, "+" || :backoff || " seconds"), locked_at = NULL, error_message = :err WHERE id = :id'
            : 'UPDATE thulium_sync_queue SET status = "pending", available_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :backoff SECOND), locked_at = NULL, error_message = :err WHERE id = :id';
        $stmt = $this->pdo->prepare($retrySql);
        $stmt->execute([
            'id' => $id,
            'backoff' => $backoffSeconds,
            'err' => mb_substr($error, 0, 2000),
        ]);

        $log = $this->pdo->prepare('INSERT INTO thulium_sync_error_log(queue_id, context, error_message, stacktrace) VALUES (:qid, :ctx, :msg, :stacktrace)');
        $log->execute([
            'qid' => $id,
            'ctx' => json_encode(['backoff_seconds' => $backoffSeconds], JSON_THROW_ON_ERROR),
            'msg' => mb_substr($error, 0, 5000),
            'stacktrace' => null,
        ]);
    }

    public function markDead(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("UPDATE thulium_sync_queue SET status = 'dead', locked_at = NULL, error_message = :err WHERE id = :id");
        $stmt->execute(['id' => $id, 'err' => mb_substr($error, 0, 2000)]);
    }

    public function retryFailed(int $limit): int
    {
        $stmt = $this->pdo->prepare("UPDATE thulium_sync_queue SET status = 'pending', available_at = CURRENT_TIMESTAMP, locked_at = NULL WHERE status IN ('failed','dead') ORDER BY id ASC LIMIT :lim");
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function cleanupOldDone(int $days): int
    {
        $sql = $this->driver === 'sqlite'
            ? 'DELETE FROM thulium_sync_queue WHERE status = "done" AND processed_at < datetime(CURRENT_TIMESTAMP, "-" || :days || " days")'
            : 'DELETE FROM thulium_sync_queue WHERE status = "done" AND processed_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :days DAY)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function cleanupOldErrorLogs(int $days): int
    {
        $sql = $this->driver === 'sqlite'
            ? 'DELETE FROM thulium_sync_error_log WHERE created_at < datetime(CURRENT_TIMESTAMP, "-" || :days || " days")'
            : 'DELETE FROM thulium_sync_error_log WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :days DAY)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /** @return array{pending:int,processing:int,failed:int,dead:int,done:int} */
    public function getQueueStats(): array
    {
        $stmt = $this->pdo->query('SELECT status, COUNT(*) AS c FROM thulium_sync_queue GROUP BY status');
        $stats = ['pending' => 0, 'processing' => 0, 'failed' => 0, 'dead' => 0, 'done' => 0];

        foreach ($stmt->fetchAll() as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $stats)) {
                $stats[$status] = (int) $row['c'];
            }
        }

        return $stats;
    }

    public function upsertCustomerMap(int $sourceCustomerId, int $thuliumCustomerId, string $checksum): void
    {
        $sql = 'INSERT INTO thulium_customer_map (source_customer_id, thulium_customer_id, last_synced_at, sync_checksum)
                VALUES (:sid, :tid, CURRENT_TIMESTAMP, :checksum)
                ON DUPLICATE KEY UPDATE thulium_customer_id = VALUES(thulium_customer_id),
                    sync_checksum = VALUES(sync_checksum),
                    last_synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP';
        $this->pdo->prepare($sql)->execute(['sid' => $sourceCustomerId, 'tid' => $thuliumCustomerId, 'checksum' => $checksum]);
    }

    /** @return array{thulium_customer_id:int,sync_checksum:?string}|null */
    public function findCustomerMap(int $sourceCustomerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT thulium_customer_id, sync_checksum FROM thulium_customer_map WHERE source_customer_id = :sid');
        $stmt->execute(['sid' => $sourceCustomerId]);
        $row = $stmt->fetch();

        return $row !== false ? [
            'thulium_customer_id' => (int) $row['thulium_customer_id'],
            'sync_checksum' => isset($row['sync_checksum']) ? (string) $row['sync_checksum'] : null,
        ] : null;
    }

    public function upsertTripMap(int $sourceTripId, int $sourceCustomerId, int $thuliumCustomerId, int $groupId, int $thuliumItemId, string $checksum): void
    {
        $sql = 'INSERT INTO thulium_trip_map (source_trip_id, source_customer_id, thulium_customer_id, thulium_group_id, thulium_item_id, last_synced_at, sync_checksum)
                VALUES (:source_trip_id, :source_customer_id, :thulium_customer_id, :group_id, :item_id, CURRENT_TIMESTAMP, :checksum)
                ON DUPLICATE KEY UPDATE source_customer_id = VALUES(source_customer_id),
                    thulium_customer_id = VALUES(thulium_customer_id),
                    thulium_group_id = VALUES(thulium_group_id),
                    thulium_item_id = VALUES(thulium_item_id),
                    sync_checksum = VALUES(sync_checksum),
                    last_synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP';

        $this->pdo->prepare($sql)->execute([
            'source_trip_id' => $sourceTripId,
            'source_customer_id' => $sourceCustomerId,
            'thulium_customer_id' => $thuliumCustomerId,
            'group_id' => $groupId,
            'item_id' => $thuliumItemId,
            'checksum' => $checksum,
        ]);
    }

    /** @return array{thulium_item_id:int,thulium_customer_id:int,sync_checksum:?string}|null */
    public function findTripMap(int $sourceTripId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT thulium_item_id, thulium_customer_id, sync_checksum FROM thulium_trip_map WHERE source_trip_id = :sid');
        $stmt->execute(['sid' => $sourceTripId]);
        $row = $stmt->fetch();

        return $row !== false ? [
            'thulium_item_id' => (int) $row['thulium_item_id'],
            'thulium_customer_id' => (int) $row['thulium_customer_id'],
            'sync_checksum' => isset($row['sync_checksum']) ? (string) $row['sync_checksum'] : null,
        ] : null;
    }
}
