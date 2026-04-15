<?php

declare(strict_types=1);

namespace ThuliumBridge\Domain\Trip;

final class TripMapper
{
    /** @param array<string,mixed> $tripRow @param array<string,mixed> $pickup @param array<string,mixed> $dropoff */
    public function map(array $tripRow, array $pickup, array $dropoff, string $status): TripPayload
    {
        $tripId = (int) $tripRow['id'];

        return new TripPayload(
            $tripId,
            (int) $tripRow['id_pasazera'],
            substr((string) $tripRow['data'], 0, 10),
            $this->formatAddress($pickup),
            $this->formatAddress($dropoff),
            $status,
            sprintf('vipmart-przejazd-%d', $tripId)
        );
    }

    /** @param array<string,mixed> $address */
    public function formatAddress(array $address): string
    {
        $country = trim((string) ($address['kraj'] ?? ''));
        $postal = trim((string) ($address['kod'] ?? ''));
        $city = trim((string) ($address['miejscowosc'] ?? ''));
        $street = trim((string) ($address['adres'] ?? ''));

        $cityBlock = trim($postal . ' ' . $city);
        $parts = array_filter([$country, $cityBlock, $street], static fn (string $v): bool => $v !== '');

        return implode(', ', array_values($parts));
    }
}
