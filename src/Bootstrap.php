<?php

declare(strict_types=1);

namespace ThuliumBridge;

use Dotenv\Dotenv;
use ThuliumBridge\Console\CommandRunner;
use ThuliumBridge\Console\Commands\DryRunCommand;
use ThuliumBridge\Console\Commands\HealthCommand;
use ThuliumBridge\Console\Commands\CleanupCommand;
use ThuliumBridge\Console\Commands\RetryFailedCommand;
use ThuliumBridge\Console\Commands\RunOnceCommand;
use ThuliumBridge\Console\Commands\RunWorkerCommand;
use ThuliumBridge\Console\Commands\ValidateConfigCommand;
use ThuliumBridge\Domain\Customer\CustomerMapper;
use ThuliumBridge\Domain\Trip\TripMapper;
use ThuliumBridge\Domain\Trip\TripStatusResolver;
use ThuliumBridge\Infrastructure\MySql\SourceRepository;
use ThuliumBridge\Infrastructure\Thulium\CustomerFieldGroupGateway;
use ThuliumBridge\Infrastructure\Thulium\CustomerGateway;
use ThuliumBridge\Infrastructure\Thulium\ThuliumHttpClient;
use ThuliumBridge\Queue\QueueRepository;
use ThuliumBridge\Queue\QueueWorker;

final class Bootstrap
{
    public static function createRunner(string $rootDir): CommandRunner
    {
        if (file_exists($rootDir . '/.env')) {
            Dotenv::createImmutable($rootDir)->safeLoad();
        }

        $config = new Config(require $rootDir . '/config/config.php');
        $logger = LoggerFactory::create($config);

        $pdo = (new Database($config))->pdo();
        $queueRepository = new QueueRepository($pdo);
        $sourceRepository = new SourceRepository($pdo);

        $httpClient = new ThuliumHttpClient(
            $config->get('thulium.base_url'),
            $config->get('thulium.auth_user'),
            $config->get('thulium.auth_pass'),
            $config->get('thulium.timeout'),
            $logger
        );

        $worker = new QueueWorker(
            $queueRepository,
            $sourceRepository,
            new CustomerGateway($httpClient),
            new CustomerFieldGroupGateway($httpClient),
            new CustomerMapper(),
            new TripMapper(),
            new TripStatusResolver(),
            $config,
            $logger
        );

        return new CommandRunner([
            'validate-config' => new ValidateConfigCommand($config),
            'health' => new HealthCommand($queueRepository),
            'dry-run' => new DryRunCommand($worker),
            'run-once' => new RunOnceCommand($worker),
            'run-loop' => new RunWorkerCommand($worker, $config),
            'retry-failed' => new RetryFailedCommand($queueRepository),
            'cleanup' => new CleanupCommand($queueRepository, $config),
        ]);
    }
}
