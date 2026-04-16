<?php

declare(strict_types=1);

namespace ThuliumBridge\Log;

final class SimpleLogger implements LoggerInterface
{
    /** @var resource */
    private $stdout;

    /** @var resource|null */
    private $fileHandle;

    public function __construct(private readonly string $level = 'info', ?string $file = null)
    {
        $this->stdout = fopen('php://stdout', 'wb');
        $this->fileHandle = ($file !== null && $file !== '') ? fopen($file, 'ab') : null;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /** @param array<string,mixed> $context */
    private function log(string $level, string $message, array $context): void
    {
        if ($this->level === 'error' && $level !== 'ERROR') {
            return;
        }

        $line = sprintf("[%s] %s %s %s\n", date('Y-m-d H:i:s'), $level, $message, json_encode($context, JSON_UNESCAPED_UNICODE));
        fwrite($this->stdout, $line);
        if (is_resource($this->fileHandle)) {
            fwrite($this->fileHandle, $line);
        }
    }
}
