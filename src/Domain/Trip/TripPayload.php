<?php

declare(strict_types=1);

namespace ThuliumBridge\Domain\Trip;

final class TripPayload
{
    public function __construct(
        public readonly int $sourceTripId,
        public readonly int $sourceCustomerId,
        public readonly string $tripDate,
        public readonly string $pickup,
        public readonly string $dropoff,
        public readonly string $status,
        public readonly string $externalTripId
    ) {
    }

    public function checksum(): string
    {
        return hash('sha256', implode('|', [
            $this->externalTripId,
            $this->tripDate,
            $this->pickup,
            $this->dropoff,
            $this->status,
        ]));
    }
}
