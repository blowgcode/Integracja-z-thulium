<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use ThuliumBridge\Config;
use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueRepository;

final class CleanupCommand implements CommandInterface
{
    public function __construct(private readonly QueueRepository $queue, private readonly Config $config)
    {
    }

    public function run(array $args): int
    {
        $doneDays = (int) $this->config->get('worker.cleanup_done_older_than_days');
        $logDays = (int) $this->config->get('worker.cleanup_logs_older_than_days');

        $doneDeleted = $this->queue->cleanupOldDone($doneDays);
        $logDeleted = $this->queue->cleanupOldErrorLogs($logDays);

        fwrite(STDOUT, sprintf("Cleanup complete. done=%d logs=%d\n", $doneDeleted, $logDeleted));

        return 0;
    }

    public function description(): string
    {
        return 'Delete old done queue records and old error logs';
    }
}
