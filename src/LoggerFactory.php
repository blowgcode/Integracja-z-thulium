<?php

declare(strict_types=1);

namespace ThuliumBridge;

use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(Config $config): LoggerInterface
    {
        $logger = new Logger('thulium-bridge');
        $formatter = new LineFormatter("[%datetime%] %level_name% %message% %context%\n", 'Y-m-d H:i:s', true, true);
        $stdoutHandler = new StreamHandler('php://stdout', Logger::toMonologLevel($config->get('log.level')));
        $stdoutHandler->setFormatter($formatter);
        $logger->pushHandler($stdoutHandler);

        $logFile = $config->get('log.file');
        if (is_string($logFile) && $logFile !== '') {
            $fileHandler = new StreamHandler($logFile, Logger::toMonologLevel($config->get('log.level')));
            $fileHandler->setFormatter($formatter);
            $logger->pushHandler($fileHandler);
        }

        return $logger;
    }
}
