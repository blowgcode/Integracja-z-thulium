<?php

declare(strict_types=1);

namespace ThuliumBridge\Domain\Customer;

final class CustomerMapper
{
    /** @param array<string,mixed> $row */
    public function map(array $row): CustomerPayload
    {
        $sourceId = (int) $row['id'];
        [$name, $surname] = $this->splitName((string) ($row['imie_nazwisko'] ?? 'Nieznany'));

        return new CustomerPayload(
            $sourceId,
            sprintf('vipmart-pasazer-%d', $sourceId),
            $name,
            $surname,
            $this->pickPhone($row),
            isset($row['email']) && trim((string) $row['email']) !== '' ? (string) $row['email'] : null,
        );
    }

    /** @param array<string,mixed> $row */
    public function pickPhone(array $row): string
    {
        foreach (['tel_1', 'tel_2', 'tel_3'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'brak';
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $name = $parts[0] ?? 'Nieznany';
        $surname = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '-';

        return [$name, $surname];
    }
}
