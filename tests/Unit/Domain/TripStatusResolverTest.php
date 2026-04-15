<?php

declare(strict_types=1);

namespace ThuliumBridge\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ThuliumBridge\Domain\Trip\TripStatusResolver;

final class TripStatusResolverTest extends TestCase
{
    public function testResolveForUpsertInsert(): void
    {
        $resolver = new TripStatusResolver();
        self::assertSame('Nowy', $resolver->resolveForUpsert('insert'));
    }

    public function testResolveForUpsertUpdate(): void
    {
        $resolver = new TripStatusResolver();
        self::assertSame('Zmieniony', $resolver->resolveForUpsert('update'));
    }

    public function testResolveForDelete(): void
    {
        $resolver = new TripStatusResolver();
        self::assertSame('Anulow', $resolver->resolveForDelete());
    }
}
