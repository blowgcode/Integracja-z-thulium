<?php

declare(strict_types=1);

namespace ThuliumBridge\Tests\Smoke;

use PDO;
use PHPUnit\Framework\TestCase;
use ThuliumBridge\Queue\QueueRepository;

final class QueueRepositorySmokeTest extends TestCase
{
    public function testClaimBatchForInMemorySqlite(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE thulium_sync_queue (id INTEGER PRIMARY KEY AUTOINCREMENT, aggregate_type TEXT, aggregate_id INTEGER, event_type TEXT, status TEXT, attempts INTEGER DEFAULT 0, available_at TEXT, locked_at TEXT, error_message TEXT, payload_json TEXT, created_at TEXT, processed_at TEXT, updated_at TEXT)');
        $pdo->exec("INSERT INTO thulium_sync_queue(aggregate_type, aggregate_id, event_type, status, attempts, available_at, created_at, updated_at) VALUES ('customer',1,'customer.upsert','pending',0,datetime('now'),datetime('now'),datetime('now'))");

        $repository = new QueueRepository($pdo);
        $rows = $repository->claimBatch(10, 120);

        self::assertCount(1, $rows);
        self::assertSame('customer.upsert', $rows[0]['event_type']);
    }
}
