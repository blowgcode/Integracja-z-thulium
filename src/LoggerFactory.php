<?php

declare(strict_types=1);

namespace ThuliumBridge;

use ThuliumBridge\Log\LoggerInterface;
use ThuliumBridge\Log\SimpleLogger;

final class LoggerFactory
{
    public static function create(Config $config): LoggerInterface
    {
        $level = (string) $config->get('log.level');
        $file = $config->get('log.file');

        return new SimpleLogger($level, is_string($file) ? $file : null);
    }
}
