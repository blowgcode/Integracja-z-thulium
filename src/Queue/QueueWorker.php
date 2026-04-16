<?php

declare(strict_types=1);

namespace ThuliumBridge\Queue;

use RuntimeException;
use Throwable;
use ThuliumBridge\Config;
use ThuliumBridge\Domain\Customer\CustomerMapper;
use ThuliumBridge\Domain\Trip\TripMapper;
use ThuliumBridge\Domain\Trip\TripStatusResolver;
use ThuliumBridge\Infrastructure\MySql\SourceRepository;
use ThuliumBridge\Infrastructure\Thulium\CustomerFieldGroupGateway;
use ThuliumBridge\Infrastructure\Thulium\CustomerGatewayInterface;
use ThuliumBridge\Log\LoggerInterface;

final class QueueWorker
{
    public function __construct(
        private readonly QueueRepository $queue,
        private readonly SourceRepository $source,
        private readonly CustomerGatewayInterface $customerGateway,
        private readonly CustomerFieldGroupGateway $groupGateway,
        private readonly CustomerMapper $customerMapper,
        private readonly TripMapper $tripMapper,
        private readonly TripStatusResolver $statusResolver,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function runOnce(bool $dryRun): int
    {
        $events = $this->queue->claimBatch(
            (int) $this->config->get('worker.batch_size'),
            (int) $this->config->get('worker.lock_timeout_seconds')
        );

        foreach ($events as $event) {
            $id = (int) $event['id'];
            $attempt = (int) $event['attempts'];
            $context = $this->eventContext($event, $attempt);

            try {
                if ($dryRun) {
                    $this->logger->info('DRY-RUN event', $context);
                    $this->queue->markDone($id);
                    continue;
                }

                $this->processEvent($event);
                $this->logger->info('event_processed', $context);
                $this->queue->markDone($id);
            } catch (Throwable $e) {
                $this->logger->error('Event processing failed', $context + ['error' => $e->getMessage()]);
                $maxRetries = (int) $this->config->get('worker.max_retries');
                if ($attempt >= $maxRetries) {
                    $this->queue->markDead($id, $e->getMessage());
                    continue;
                }

                $backoff = ((int) $this->config->get('worker.backoff_base_seconds')) ** $attempt;
                $this->queue->markRetry($id, $backoff, $e->getMessage());
            }
        }

        return count($events);
    }

    /** @param array<string,mixed> $event */
    private function processEvent(array $event): void
    {
        $type = (string) $event['event_type'];
        $sourceId = (int) $event['aggregate_id'];
        $payload = $this->decodePayload($event['payload_json'] ?? null);

        if ($type === 'customer.upsert') {
            $this->processCustomerUpsert($sourceId);
            return;
        }

        if ($type === 'trip.upsert') {
            $operation = (string) ($payload['operation'] ?? 'update');
            $status = $this->statusResolver->resolveForUpsert($operation);
            $this->processTripUpsert($sourceId, $status);
            return;
        }

        if ($type === 'trip.deleted') {
            $this->processTripDeleted($sourceId);
            return;
        }

        throw new RuntimeException('Unsupported event type: ' . $type);
    }

    private function processCustomerUpsert(int $sourceCustomerId): void
    {
        $sourceCustomer = $this->source->getPassengerById($sourceCustomerId);
        if ($sourceCustomer === null) {
            throw new RuntimeException('Customer not found in source DB.');
        }

        $customer = $this->customerMapper->map($sourceCustomer);
        $checksum = $customer->checksum();

        $existingMap = $this->queue->findCustomerMap($customer->sourceCustomerId);
        if ($existingMap !== null && $existingMap['sync_checksum'] === $checksum) {
            $this->logger->info('skipped_no_changes', ['entity' => 'customer', 'source_customer_id' => $customer->sourceCustomerId]);
            return;
        }

        $ensured = $this->customerGateway->ensureCustomer($customer);
        $this->queue->upsertCustomerMap($customer->sourceCustomerId, (int) $ensured['id'], $checksum);
    }

    private function processTripUpsert(int $sourceTripId, string $status): void
    {
        $trip = $this->source->getTripById($sourceTripId);
        if ($trip === null) {
            throw new RuntimeException('Trip not found in source DB.');
        }

        $pickup = $this->source->getAddressById((int) $trip['id_adres_z']) ?? [];
        $dropoff = $this->source->getAddressById((int) $trip['id_adres_do']) ?? [];
        $tripPayload = $this->tripMapper->map($trip, $pickup, $dropoff, $status);

        $customerMap = $this->queue->findCustomerMap($tripPayload->sourceCustomerId);
        if ($customerMap === null) {
            $sourceCustomer = $this->source->getPassengerById($tripPayload->sourceCustomerId);
            if ($sourceCustomer === null) {
                throw new RuntimeException('Trip customer does not exist in source DB.');
            }

            $customerPayload = $this->customerMapper->map($sourceCustomer);
            $ensured = $this->customerGateway->ensureCustomer($customerPayload);
            $this->queue->upsertCustomerMap($customerPayload->sourceCustomerId, (int) $ensured['id'], $customerPayload->checksum());
            $thuliumCustomerId = (int) $ensured['id'];
        } else {
            $thuliumCustomerId = $customerMap['thulium_customer_id'];
        }

        $tripMap = $this->queue->findTripMap($sourceTripId);
        $checksum = $tripPayload->checksum();
        if ($tripMap !== null && $tripMap['sync_checksum'] === $checksum) {
            $this->logger->info('skipped_no_changes', ['entity' => 'trip', 'source_trip_id' => $sourceTripId]);
            return;
        }

        $values = $this->buildTripValues($tripPayload);
        $groupId = (int) $this->config->get('thulium.group_id');
        $itemId = $tripMap['thulium_item_id'] ?? null;

        if ($itemId === null) {
            $itemId = $this->findRemoteTripItemId($thuliumCustomerId, $groupId, $tripPayload->externalTripId);
        }

        if ($itemId !== null) {
            $this->groupGateway->updateGroupItem($itemId, $values);
            $this->queue->upsertTripMap($sourceTripId, $tripPayload->sourceCustomerId, $thuliumCustomerId, $groupId, $itemId, $checksum);
            return;
        }

        $created = $this->groupGateway->createGroupItem($groupId, $thuliumCustomerId, $values);
        $createdId = (int) ($created['id'] ?? 0);
        if ($createdId <= 0) {
            throw new RuntimeException('Thulium createGroupItem returned invalid id.');
        }

        $this->queue->upsertTripMap($sourceTripId, $tripPayload->sourceCustomerId, $thuliumCustomerId, $groupId, $createdId, $checksum);
    }

    private function processTripDeleted(int $sourceTripId): void
    {
        $tripMap = $this->queue->findTripMap($sourceTripId);
        if ($tripMap === null) {
            $this->logger->info('trip_deleted_without_map', ['source_trip_id' => $sourceTripId]);
            return;
        }

        $itemId = $tripMap['thulium_item_id'];
        $statusFieldId = (int) $this->config->get('thulium.field_status');

        if ((bool) $this->config->get('thulium.hard_delete')) {
            $this->groupGateway->deleteGroupItem($itemId);
            return;
        }

        $this->groupGateway->updateGroupItem($itemId, [
            ['field_id' => $statusFieldId, 'value' => $this->statusResolver->resolveForDelete()],
        ]);
    }

    /** @return list<array{field_id:int,value:string}> */
    private function buildTripValues(\ThuliumBridge\Domain\Trip\TripPayload $payload): array
    {
        return [
            ['field_id' => (int) $this->config->get('thulium.field_trip_id'), 'value' => $payload->externalTripId],
            ['field_id' => (int) $this->config->get('thulium.field_trip_date'), 'value' => $payload->tripDate],
            ['field_id' => (int) $this->config->get('thulium.field_pickup'), 'value' => $payload->pickup],
            ['field_id' => (int) $this->config->get('thulium.field_dropoff'), 'value' => $payload->dropoff],
            ['field_id' => (int) $this->config->get('thulium.field_status'), 'value' => $payload->status],
        ];
    }

    private function findRemoteTripItemId(int $customerId, int $groupId, string $externalTripId): ?int
    {
        $items = $this->groupGateway->listCustomerGroupItems($customerId, $groupId);
        $tripFieldId = (int) $this->config->get('thulium.field_trip_id');

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $values = $item['values'] ?? [];
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $value) {
                if (!is_array($value)) {
                    continue;
                }
                if ((int) ($value['field_id'] ?? 0) === $tripFieldId && (string) ($value['value'] ?? '') === $externalTripId) {
                    return $itemId > 0 ? $itemId : null;
                }
            }
        }

        return null;
    }

    /** @return array<string,mixed> */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string,mixed> $event @return array<string,mixed> */
    private function eventContext(array $event, int $attempt): array
    {
        return [
            'queue_id' => (int) $event['id'],
            'aggregate_type' => (string) ($event['aggregate_type'] ?? ''),
            'aggregate_id' => (int) ($event['aggregate_id'] ?? 0),
            'event_type' => (string) ($event['event_type'] ?? ''),
            'attempt' => $attempt,
        ];
    }
}
