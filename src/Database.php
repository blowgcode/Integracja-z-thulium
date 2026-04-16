<?php

declare(strict_types=1);

namespace ThuliumBridge;

use PDO;

final class Database
{
    public function __construct(private readonly Config $config)
    {
    }

    public function pdo(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config->get('db.host'),
            $this->config->get('db.port'),
            $this->config->get('db.name'),
            $this->config->get('db.charset')
        );

        return new PDO($dsn, $this->config->get('db.user'), $this->config->get('db.pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
