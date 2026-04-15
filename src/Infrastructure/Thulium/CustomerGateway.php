<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

use RuntimeException;
use ThuliumBridge\Domain\Customer\CustomerPayload;

final class CustomerGateway implements CustomerGatewayInterface
{
    public function __construct(private readonly ThuliumHttpClient $httpClient)
    {
    }

    /** @return array<string,mixed>|null */
    public function findCustomerByExternalId(string $externalId): ?array
    {
        $response = $this->httpClient->request('GET', TenantCustomerApiAssumptions::SEARCH_ENDPOINT, [], [
            'external_id' => $externalId,
        ]);

        return $this->firstItem($response);
    }

    /** @return array<string,mixed>|null */
    public function findCustomerByPhone(string $phone): ?array
    {
        if ($phone === 'brak') {
            return null;
        }

        $response = $this->httpClient->request('GET', TenantCustomerApiAssumptions::SEARCH_ENDPOINT, [], [
            'phone' => $phone,
        ]);

        return $this->firstItem($response);
    }

    /** @return array<string,mixed> */
    public function createCustomer(CustomerPayload $customer): array
    {
        return $this->httpClient->request('POST', TenantCustomerApiAssumptions::CREATE_ENDPOINT, [
            'external_id' => $customer->externalCustomerId,
            'name' => $customer->name,
            'surname' => $customer->surname,
            'phone' => $customer->phone,
            'email' => $customer->email,
        ]);
    }

    /** @return array<string,mixed> */
    public function updateCustomer(int $customerId, CustomerPayload $customer): array
    {
        return $this->httpClient->request('PUT', TenantCustomerApiAssumptions::UPDATE_ENDPOINT_PREFIX . $customerId, [
            'external_id' => $customer->externalCustomerId,
            'name' => $customer->name,
            'surname' => $customer->surname,
            'phone' => $customer->phone,
            'email' => $customer->email,
        ]);
    }

    /** @return array{id:int,source:string} */
    public function ensureCustomer(CustomerPayload $customer): array
    {
        $existing = $this->findCustomerByExternalId($customer->externalCustomerId);
        if ($existing !== null) {
            $id = (int) ($existing['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid customer payload returned for external_id lookup.');
            }

            $this->updateCustomer($id, $customer);

            return ['id' => $id, 'source' => 'external_id'];
        }

        $byPhone = $this->findCustomerByPhone($customer->phone);
        if ($byPhone !== null) {
            $id = (int) ($byPhone['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid customer payload returned for phone lookup.');
            }

            $this->updateCustomer($id, $customer);

            return ['id' => $id, 'source' => 'phone'];
        }

        $created = $this->createCustomer($customer);
        $id = (int) ($created['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Customer create did not return numeric id.');
        }

        return ['id' => $id, 'source' => 'created'];
    }

    /** @param array<string,mixed> $response @return array<string,mixed>|null */
    private function firstItem(array $response): ?array
    {
        $items = $response['data'] ?? $response['items'] ?? $response;
        if (is_array($items) && isset($items[0]) && is_array($items[0])) {
            return $items[0];
        }

        if (isset($items['id']) && is_scalar($items['id'])) {
            return $items;
        }

        return null;
    }
}
