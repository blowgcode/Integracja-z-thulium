-- Optional/online index layer separated for safer phased rollout.
-- Run after 001_schema.sql and before 002_triggers.sql.

ALTER TABLE thulium_sync_queue
    ADD KEY idx_thulium_sync_queue_status_attempts_available (status, attempts, available_at),
    ADD KEY idx_thulium_sync_queue_event_type_created (event_type, created_at),
    ADD KEY idx_thulium_sync_queue_updated_at (updated_at);

ALTER TABLE thulium_customer_map
    ADD KEY idx_thulium_customer_map_last_synced (last_synced_at),
    ADD KEY idx_thulium_customer_map_checksum (sync_checksum);

ALTER TABLE thulium_trip_map
    ADD KEY idx_thulium_trip_map_last_synced (last_synced_at),
    ADD KEY idx_thulium_trip_map_checksum (sync_checksum),
    ADD KEY idx_thulium_trip_map_source_customer_trip (source_customer_id, source_trip_id);

ALTER TABLE thulium_sync_error_log
    ADD KEY idx_thulium_sync_error_log_queue_created (queue_id, created_at);

ALTER TABLE thulium_worker_heartbeat
    ADD KEY idx_thulium_worker_heartbeat_updated (updated_at);
