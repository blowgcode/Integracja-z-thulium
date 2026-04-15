<?php

declare(strict_types=1);

namespace ThuliumBridge\Console\Commands;

use Throwable;
use ThuliumBridge\Config;
use ThuliumBridge\Console\CommandInterface;

final class ValidateConfigCommand implements CommandInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function run(array $args): int
    {
        try {
            $this->config->validate();
            fwrite(STDOUT, "Configuration is valid.\n");
            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, "Configuration error: {$e->getMessage()}\n");
            return 1;
        }
    }

    public function description(): string
    {
        return 'Validate required configuration values';
    }
}
