<?php

declare(strict_types=1);

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    'thulium' => [
        'base_url' => getenv('THULIUM_BASE_URL') ?: '',
        'auth_user' => getenv('THULIUM_AUTH_USER') ?: (getenv('THULIUM_API_TOKEN') ?: ''),
        'auth_pass' => getenv('THULIUM_AUTH_PASS') ?: 'x',
        'timeout' => (int) (getenv('THULIUM_TIMEOUT') ?: 15),
        'hard_delete' => filter_var(getenv('THULIUM_HARD_DELETE') ?: '0', FILTER_VALIDATE_BOOL),
        'group_id' => (int) (getenv('THULIUM_GROUP_ID') ?: 1),
        'field_trip_id' => (int) (getenv('THULIUM_FIELD_TRIP_ID') ?: 7),
        'field_trip_date' => (int) (getenv('THULIUM_FIELD_TRIP_DATE') ?: 8),
        'field_pickup' => (int) (getenv('THULIUM_FIELD_PICKUP') ?: 9),
        'field_dropoff' => (int) (getenv('THULIUM_FIELD_DROPOFF') ?: 10),
        'field_status' => (int) (getenv('THULIUM_FIELD_STATUS') ?: 11),
    ],
    'worker' => [
        'batch_size' => (int) (getenv('WORKER_BATCH_SIZE') ?: 50),
        'idle_sleep_seconds' => (int) (getenv('WORKER_IDLE_SLEEP_SECONDS') ?: 2),
        'max_retries' => (int) (getenv('WORKER_MAX_RETRIES') ?: 10),
        'backoff_base_seconds' => (int) (getenv('WORKER_BACKOFF_BASE_SECONDS') ?: 2),
        'lock_timeout_seconds' => (int) (getenv('WORKER_LOCK_TIMEOUT_SECONDS') ?: 120),
        'cleanup_done_older_than_days' => (int) (getenv('WORKER_CLEANUP_DONE_DAYS') ?: 14),
        'cleanup_logs_older_than_days' => (int) (getenv('WORKER_CLEANUP_LOG_DAYS') ?: 30),
    ],
    'log' => [
        'level' => getenv('LOG_LEVEL') ?: 'info',
        'file' => getenv('LOG_FILE') ?: null,
    ],
];
