<?php

declare(strict_types=1);

namespace ThuliumBridge\Domain\Trip;

final class TripStatusResolver
{
    public function resolveForUpsert(string $operation): string
    {
        return strtolower($operation) === 'insert' ? 'Nowy' : 'Zmieniony';
    }

    public function resolveForDelete(): string
    {
        return 'Anulow';
    }
}
