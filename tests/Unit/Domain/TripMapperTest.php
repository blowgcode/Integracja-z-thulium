<?php

declare(strict_types=1);

namespace ThuliumBridge\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ThuliumBridge\Domain\Trip\TripMapper;

final class TripMapperTest extends TestCase
{
    public function testAddressFormatting(): void
    {
        $mapper = new TripMapper();
        $value = $mapper->formatAddress([
            'adres' => 'ul. Dluga 1',
            'kod' => '00-001',
            'miejscowosc' => 'Warszawa',
            'kraj' => 'PL',
        ]);

        self::assertSame('PL, 00-001 Warszawa, ul. Dluga 1', $value);
    }

    public function testTripMapBuildsExternalId(): void
    {
        $mapper = new TripMapper();
        $payload = $mapper->map(
            ['id' => 11, 'id_pasazera' => 5, 'data' => '2026-01-01 10:00:00', 'status' => 'Nowy'],
            ['adres' => 'A', 'kod' => '1', 'miejscowosc' => 'M1', 'kraj' => 'PL'],
            ['adres' => 'B', 'kod' => '2', 'miejscowosc' => 'M2', 'kraj' => 'PL'],
            'Nowy'
        );

        self::assertSame('vipmart-przejazd-11', $payload->externalTripId);
        self::assertSame('2026-01-01', $payload->tripDate);
    }

    public function testAddressFormattingSkipsEmptyPartsWithoutDoubleCommas(): void
    {
        $mapper = new TripMapper();
        $value = $mapper->formatAddress([
            'kraj' => 'PL',
            'kod' => '',
            'miejscowosc' => 'Krakow',
            'adres' => '',
        ]);

        self::assertSame('PL, Krakow', $value);
        self::assertStringNotContainsString(',,', $value);
    }
}
