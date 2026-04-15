-- Uninstall script for Thulium Bridge integration only.
-- Drops only objects created by this integration.
-- Does NOT modify business tables (`pasazerowie`, `przejazdy`, `adresy`) or non-integration triggers.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_thulium_pasazerowie_ai_enqueue_customer_upsert $$
DROP TRIGGER IF EXISTS trg_thulium_pasazerowie_au_enqueue_customer_upsert $$
DROP TRIGGER IF EXISTS trg_thulium_przejazdy_ai_enqueue_trip_upsert $$
DROP TRIGGER IF EXISTS trg_thulium_przejazdy_au_enqueue_trip_upsert $$
DROP TRIGGER IF EXISTS trg_thulium_przejazdy_ad_enqueue_trip_deleted $$

DELIMITER ;

DROP TABLE IF EXISTS thulium_worker_heartbeat;
DROP TABLE IF EXISTS thulium_sync_error_log;
DROP TABLE IF EXISTS thulium_trip_map;
DROP TABLE IF EXISTS thulium_customer_map;
DROP TABLE IF EXISTS thulium_sync_queue;
