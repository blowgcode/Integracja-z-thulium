<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use Throwable;
use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueWorker;

final class RunOnceCommand implements CommandInterface
{
    public function __construct(private readonly QueueWorker $worker)
    {
    }

    public function run(array $args): int
    {
        try {
            $this->worker->runOnce(false);
            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, "Critical error: {$e->getMessage()}\n");
            return 1;
        }
    }

    public function description(): string
    {
        return 'Process one batch and exit (cron mode)';
    }
}
