-- Thulium Bridge integration schema.
-- Safety rule: this file creates only new integration objects; it does not alter source business tables.

CREATE TABLE IF NOT EXISTS thulium_sync_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    aggregate_type ENUM('customer', 'trip') NOT NULL,
    aggregate_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    -- Chosen strategy: lightweight reference payload (IDs + metadata),
    -- with full business read deferred to worker runtime.
    -- This keeps triggers deterministic, low-latency, and avoids heavy joins/deadlocks.
    payload_json JSON NOT NULL,
    dedupe_key VARCHAR(191) NULL,
    status ENUM('pending', 'processing', 'done', 'failed', 'dead') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thulium_sync_queue_dedupe_key (dedupe_key),
    KEY idx_thulium_sync_queue_pending (status, available_at, id),
    KEY idx_thulium_sync_queue_aggregate (aggregate_type, aggregate_id, id),
    KEY idx_thulium_sync_queue_locked_at (locked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thulium_customer_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_customer_id BIGINT UNSIGNED NOT NULL,
    thulium_customer_id BIGINT UNSIGNED NOT NULL,
    last_synced_at DATETIME NULL,
    sync_checksum CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thulium_customer_map_source_customer (source_customer_id),
    KEY idx_thulium_customer_map_thulium_customer (thulium_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thulium_trip_map (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_trip_id BIGINT UNSIGNED NOT NULL,
    source_customer_id BIGINT UNSIGNED NOT NULL,
    thulium_customer_id BIGINT UNSIGNED NOT NULL,
    thulium_group_id BIGINT UNSIGNED NOT NULL,
    thulium_item_id BIGINT UNSIGNED NOT NULL,
    last_synced_at DATETIME NULL,
    sync_checksum CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thulium_trip_map_source_trip (source_trip_id),
    KEY idx_thulium_trip_map_source_customer (source_customer_id),
    KEY idx_thulium_trip_map_thulium_customer (thulium_customer_id),
    KEY idx_thulium_trip_map_group_item (thulium_group_id, thulium_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thulium_sync_error_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id BIGINT UNSIGNED NULL,
    context JSON NULL,
    error_message TEXT NOT NULL,
    stacktrace TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_thulium_sync_error_log_queue (queue_id),
    KEY idx_thulium_sync_error_log_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thulium_worker_heartbeat (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    worker_name VARCHAR(100) NOT NULL,
    worker_pid INT UNSIGNED NULL,
    host_name VARCHAR(100) NULL,
    heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('starting', 'running', 'stopping', 'error') NOT NULL DEFAULT 'starting',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_thulium_worker_heartbeat_worker_name (worker_name),
    KEY idx_thulium_worker_heartbeat_status (status, heartbeat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
