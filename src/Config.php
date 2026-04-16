<?php

declare(strict_types=1);

namespace ThuliumBridge;

use InvalidArgumentException;

final class Config
{
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $path): mixed
    {
        $cursor = $this->values;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                throw new InvalidArgumentException(sprintf('Missing config key: %s', $path));
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    public function validate(): void
    {
        $required = [
            'db.host', 'db.port', 'db.name', 'db.user',
            'thulium.base_url', 'thulium.auth_user', 'thulium.auth_pass', 'thulium.group_id',
            'thulium.field_trip_id', 'thulium.field_trip_date', 'thulium.field_pickup',
            'thulium.field_dropoff', 'thulium.field_status',
        ];

        foreach ($required as $key) {
            $value = $this->get($key);
            if ($value === '' || $value === null || $value === 0) {
                throw new InvalidArgumentException(sprintf('Invalid empty config value for: %s', $key));
            }
        }
    }
}
