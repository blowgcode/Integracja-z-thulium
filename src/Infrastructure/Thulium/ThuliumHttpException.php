<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

use RuntimeException;

final class ThuliumHttpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct($message);
    }
}
