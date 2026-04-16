<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use Throwable;
use ThuliumBridge\Config;
use ThuliumBridge\Console\CommandInterface;
use ThuliumBridge\Queue\QueueWorker;

final class RunWorkerCommand implements CommandInterface
{
    public function __construct(private readonly QueueWorker $worker, private readonly Config $config)
    {
    }

    public function run(array $args): int
    {
        $sleepSeconds = $this->config->get('worker.idle_sleep_seconds');

        try {
            while (true) {
                $processed = $this->worker->runOnce(false);
                if ($processed === 0) {
                    sleep($sleepSeconds);
                }
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Critical loop error: {$e->getMessage()}\n");
            return 1;
        }
    }

    public function description(): string
    {
        return 'Run worker in infinite loop (systemd/supervisor mode)';
    }
}
