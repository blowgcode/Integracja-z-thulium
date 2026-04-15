<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

final class CustomerFieldGroupGateway
{
    public function __construct(private readonly ThuliumHttpClient $httpClient)
    {
    }

    /** @return array<string,mixed> */
    public function getGroupDefinitions(): array
    {
        return $this->httpClient->request('GET', '/customer_field_groups');
    }

    /** @return list<array<string,mixed>> */
    public function listCustomerGroupItems(int $customerId, int $groupId): array
    {
        $response = $this->httpClient->request('GET', '/customer_field_group_items', [], [
            'customer_id' => $customerId,
            'group_id' => $groupId,
        ]);

        $items = $response['data'] ?? $response['items'] ?? $response;

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @param list<array{field_id:int,value:string}> $values @return array<string,mixed> */
    public function createGroupItem(int $groupId, int $customerId, array $values): array
    {
        return $this->httpClient->request('POST', '/customer_field_group_items', [
            'group_id' => $groupId,
            'customer_id' => $customerId,
            'values' => $values,
        ]);
    }

    /** @param list<array{field_id:int,value:string}> $values @return array<string,mixed> */
    public function updateGroupItem(int $itemId, array $values): array
    {
        return $this->httpClient->request('PUT', '/customer_field_group_items/' . $itemId, [
            'values' => $values,
        ]);
    }

    public function deleteGroupItem(int $itemId): void
    {
        $this->httpClient->request('DELETE', '/customer_field_group_items/' . $itemId);
    }
}
