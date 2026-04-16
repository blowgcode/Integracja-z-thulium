<?php

declare(strict_types=1);

namespace ThuliumBridge\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ThuliumBridge\Domain\Customer\CustomerMapper;

final class CustomerMapperTest extends TestCase
{
    public function testPhoneSelectionPriority(): void
    {
        $mapper = new CustomerMapper();
        $phone = $mapper->pickPhone(['tel_1' => '', 'tel_2' => '222', 'tel_3' => '333']);
        self::assertSame('222', $phone);
    }

    public function testMapSplitsNameAndSurname(): void
    {
        $mapper = new CustomerMapper();
        $payload = $mapper->map(['id' => 10, 'imie_nazwisko' => 'Jan Kowalski', 'tel_1' => '123']);

        self::assertSame('Jan', $payload->name);
        self::assertSame('Kowalski', $payload->surname);
        self::assertSame('123', $payload->phone);
        self::assertSame('vipmart-pasazer-10', $payload->externalCustomerId);
    }

    public function testMapHandlesSingleWordNameDefensively(): void
    {
        $mapper = new CustomerMapper();
        $payload = $mapper->map(['id' => 11, 'imie_nazwisko' => 'Madonna', 'tel_2' => '777']);

        self::assertSame('Madonna', $payload->name);
        self::assertSame('-', $payload->surname);
        self::assertSame('777', $payload->phone);
    }
}
