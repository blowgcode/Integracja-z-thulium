-- IMPORTANT DEPLOY ORDER:
-- 1) run sql/001_schema.sql
-- 2) run sql/003_indexes.sql
-- 3) run this file (sql/002_triggers.sql)
--
-- Triggers are intentionally simple and defensive:
-- - no joins
-- - no business logic
-- - enqueue lightweight reference payloads only
-- - UPDATE triggers enqueue only when integration-relevant fields changed

DELIMITER $$

DROP TRIGGER IF EXISTS trg_thulium_pasazerowie_ai_enqueue_customer_upsert $$
CREATE TRIGGER trg_thulium_pasazerowie_ai_enqueue_customer_upsert
AFTER INSERT ON pasazerowie
FOR EACH ROW
BEGIN
    INSERT INTO thulium_sync_queue (
        aggregate_type,
        aggregate_id,
        event_type,
        payload_json,
        dedupe_key,
        status,
        attempts,
        available_at,
        created_at,
        updated_at
    )
    VALUES (
        'customer',
        NEW.id,
        'customer.upsert',
        JSON_OBJECT('source_customer_id', NEW.id, 'operation', 'insert', 'enqueued_at', NOW()),
        SHA2(CONCAT(
            'customer.upsert|', NEW.id, '|',
            COALESCE(NEW.imie_nazwisko, ''), '|',
            COALESCE(NEW.email, ''), '|',
            COALESCE(NEW.tel_1, ''), '|',
            COALESCE(NEW.tel_2, ''), '|',
            COALESCE(NEW.tel_3, '')
        ), 256),
        'pending',
        0,
        NOW(),
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
END $$

DROP TRIGGER IF EXISTS trg_thulium_pasazerowie_au_enqueue_customer_upsert $$
CREATE TRIGGER trg_thulium_pasazerowie_au_enqueue_customer_upsert
AFTER UPDATE ON pasazerowie
FOR EACH ROW
BEGIN
    IF NOT (
        OLD.imie_nazwisko <=> NEW.imie_nazwisko
        AND OLD.email <=> NEW.email
        AND OLD.tel_1 <=> NEW.tel_1
        AND OLD.tel_2 <=> NEW.tel_2
        AND OLD.tel_3 <=> NEW.tel_3
    ) THEN
        INSERT INTO thulium_sync_queue (
            aggregate_type,
            aggregate_id,
            event_type,
            payload_json,
            dedupe_key,
            status,
            attempts,
            available_at,
            created_at,
            updated_at
        )
        VALUES (
            'customer',
            NEW.id,
            'customer.upsert',
            JSON_OBJECT('source_customer_id', NEW.id, 'operation', 'update', 'enqueued_at', NOW()),
            SHA2(CONCAT(
                'customer.upsert|', NEW.id, '|',
                COALESCE(NEW.imie_nazwisko, ''), '|',
                COALESCE(NEW.email, ''), '|',
                COALESCE(NEW.tel_1, ''), '|',
                COALESCE(NEW.tel_2, ''), '|',
                COALESCE(NEW.tel_3, '')
            ), 256),
            'pending',
            0,
            NOW(),
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
    END IF;
END $$

DROP TRIGGER IF EXISTS trg_thulium_przejazdy_ai_enqueue_trip_upsert $$
CREATE TRIGGER trg_thulium_przejazdy_ai_enqueue_trip_upsert
AFTER INSERT ON przejazdy
FOR EACH ROW
BEGIN
    INSERT INTO thulium_sync_queue (
        aggregate_type,
        aggregate_id,
        event_type,
        payload_json,
        dedupe_key,
        status,
        attempts,
        available_at,
        created_at,
        updated_at
    )
    VALUES (
        'trip',
        NEW.id,
        'trip.upsert',
        JSON_OBJECT('source_trip_id', NEW.id, 'source_customer_id', NEW.id_pasazera, 'operation', 'insert', 'enqueued_at', NOW()),
        SHA2(CONCAT(
            'trip.upsert|', NEW.id, '|',
            COALESCE(NEW.id_pasazera, ''), '|',
            COALESCE(NEW.id_adres_z, ''), '|',
            COALESCE(NEW.id_adres_do, ''), '|',
            COALESCE(NEW.data, '')
        ), 256),
        'pending',
        0,
        NOW(),
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
END $$

DROP TRIGGER IF EXISTS trg_thulium_przejazdy_au_enqueue_trip_upsert $$
CREATE TRIGGER trg_thulium_przejazdy_au_enqueue_trip_upsert
AFTER UPDATE ON przejazdy
FOR EACH ROW
BEGIN
    IF NOT (
        OLD.id_pasazera <=> NEW.id_pasazera
        AND OLD.id_adres_z <=> NEW.id_adres_z
        AND OLD.id_adres_do <=> NEW.id_adres_do
        AND OLD.data <=> NEW.data
    ) THEN
        INSERT INTO thulium_sync_queue (
            aggregate_type,
            aggregate_id,
            event_type,
            payload_json,
            dedupe_key,
            status,
            attempts,
            available_at,
            created_at,
            updated_at
        )
        VALUES (
            'trip',
            NEW.id,
            'trip.upsert',
            JSON_OBJECT('source_trip_id', NEW.id, 'source_customer_id', NEW.id_pasazera, 'operation', 'update', 'enqueued_at', NOW()),
            SHA2(CONCAT(
                'trip.upsert|', NEW.id, '|',
                COALESCE(NEW.id_pasazera, ''), '|',
                COALESCE(NEW.id_adres_z, ''), '|',
                COALESCE(NEW.id_adres_do, ''), '|',
                COALESCE(NEW.data, '')
            ), 256),
            'pending',
            0,
            NOW(),
            NOW(),
            NOW()
        )
        ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
    END IF;
END $$

DROP TRIGGER IF EXISTS trg_thulium_przejazdy_ad_enqueue_trip_deleted $$
CREATE TRIGGER trg_thulium_przejazdy_ad_enqueue_trip_deleted
AFTER DELETE ON przejazdy
FOR EACH ROW
BEGIN
    INSERT INTO thulium_sync_queue (
        aggregate_type,
        aggregate_id,
        event_type,
        payload_json,
        dedupe_key,
        status,
        attempts,
        available_at,
        created_at,
        updated_at
    )
    VALUES (
        'trip',
        OLD.id,
        'trip.deleted',
        JSON_OBJECT('source_trip_id', OLD.id, 'source_customer_id', OLD.id_pasazera, 'operation', 'delete', 'enqueued_at', NOW()),
        SHA2(CONCAT('trip.deleted|', OLD.id), 256),
        'pending',
        0,
        NOW(),
        NOW(),
        NOW()
    )
    ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
END $$

DELIMITER ;
