<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueRepository;

final class HealthCommand implements CommandInterface
{
    public function __construct(private readonly QueueRepository $queue)
    {
    }

    public function run(array $args): int
    {
        $stats = $this->queue->getQueueStats();
        fwrite(STDOUT, json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);

        return ($stats['dead'] > 0) ? 1 : 0;
    }

    public function description(): string
    {
        return 'Show queue counts (pending/processing/failed/dead/done)';
    }
}
