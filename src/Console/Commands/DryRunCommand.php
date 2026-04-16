<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use Throwable;
use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueWorker;

final class DryRunCommand implements CommandInterface
{
    public function __construct(private readonly QueueWorker $worker)
    {
    }

    public function run(array $args): int
    {
        try {
            $this->worker->runOnce(true);
            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, "Critical error: {$e->getMessage()}\n");
            return 1;
        }
    }

    public function description(): string
    {
        return 'Process one batch without external API calls';
    }
}
