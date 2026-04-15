<?php

declare(strict_types=1);

namespace ThuliumBridge\Console;

interface CommandInterface
{
    public function run(array $args): int;

    public function description(): string;
}

final class CommandRunner
{
    /** @param array<string, CommandInterface> $commands */
    public function __construct(private readonly array $commands)
    {
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        if (in_array($command, ['help', '--help', '-h'], true)) {
            $this->printUsage();
            return 0;
        }

        if (!isset($this->commands[$command])) {
            fwrite(STDERR, "Unknown command: {$command}\n\n");
            $this->printUsage();
            return 2;
        }

        return $this->commands[$command]->run(array_slice($argv, 2));
    }

    public function printUsage(): void
    {
        fwrite(STDOUT, "Usage: php bin/thulium-bridge <command>\n\nCommands:\n");
        foreach ($this->commands as $name => $cmd) {
            fwrite(STDOUT, sprintf("  %-15s %s\n", $name, $cmd->description()));
        }
    }
}
