<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

use ThuliumBridge\Domain\Customer\CustomerPayload;

interface CustomerGatewayInterface
{
    /** @return array<string,mixed>|null */
    public function findCustomerByExternalId(string $externalId): ?array;

    /** @return array<string,mixed>|null */
    public function findCustomerByPhone(string $phone): ?array;

    /** @return array<string,mixed> */
    public function createCustomer(CustomerPayload $customer): array;

    /** @return array<string,mixed> */
    public function updateCustomer(int $customerId, CustomerPayload $customer): array;

    /** @return array{id:int,source:string} */
    public function ensureCustomer(CustomerPayload $customer): array;
}
