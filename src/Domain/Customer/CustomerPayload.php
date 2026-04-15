<?php

declare(strict_types=1);

namespace ThuliumBridge\Domain\Customer;

final class CustomerPayload
{
    public function __construct(
        public readonly int $sourceCustomerId,
        public readonly string $externalCustomerId,
        public readonly string $name,
        public readonly string $surname,
        public readonly string $phone,
        public readonly ?string $email
    ) {
    }

    public function checksum(): string
    {
        return hash('sha256', implode('|', [
            $this->externalCustomerId,
            $this->name,
            $this->surname,
            $this->phone,
            $this->email ?? '',
        ]));
    }
}
