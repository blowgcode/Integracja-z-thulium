<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueRepository;

final class RetryFailedCommand implements CommandInterface
{
    public function __construct(private readonly QueueRepository $queue)
    {
    }

    public function run(array $args): int
    {
        $limit = isset($args[0]) ? max(1, (int) $args[0]) : 1000;
        $count = $this->queue->retryFailed($limit);
        fwrite(STDOUT, sprintf("Requeued %d failed/dead events.\n", $count));

        return 0;
    }

    public function description(): string
    {
        return 'Move failed/dead events back to pending (optional limit arg)';
    }
}
