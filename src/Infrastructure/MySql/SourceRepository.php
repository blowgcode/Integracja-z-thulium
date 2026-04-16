<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\MySql;

use PDO;

final class SourceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed>|null */
    public function getPassengerById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, imie_nazwisko, email, tel_1, tel_2, tel_3 FROM pasazerowie WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function getTripById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, id_pasazera, id_adres_z, id_adres_do, data, status FROM przejazdy WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function getAddressById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, kraj, miejscowosc, kod, adres FROM adresy WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }
}
