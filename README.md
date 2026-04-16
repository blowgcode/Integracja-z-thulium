# Thulium Bridge (PHP + SQL + cron, bez frameworka i bez Composera)

Projekt jest całkowicie niezależnym bridge'em CLI w czystym PHP.
Nie używa Laravel/Symfony ani Composera.

## Wymagania
- PHP 8.2+ z rozszerzeniami: `pdo_mysql`, `curl`, `json`
- MySQL 8+
- cron

## Struktura uruchomieniowa
- `bin/thulium-bridge` – entrypoint CLI
- `sql/001_schema.sql` – tabele integracyjne
- `sql/003_indexes.sql` – indeksy
- `sql/002_triggers.sql` – triggery enqueue
- `sql/004_uninstall.sql` – rollback integracji
- `deploy/cron/example.cron` – przykładowe wpisy cron

## Konfiguracja
1. Skopiuj env:
   ```bash
   cp .env.example .env
   ```
2. Uzupełnij `.env`.

Kluczowe zmienne:
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `THULIUM_BASE_URL=https://vipmartour1.thulium.com/api`
- `THULIUM_AUTH_USER`, `THULIUM_AUTH_PASS`
- `THULIUM_GROUP_ID=1`
- `THULIUM_FIELD_TRIP_ID=7`
- `THULIUM_FIELD_TRIP_DATE=8`
- `THULIUM_FIELD_PICKUP=9`
- `THULIUM_FIELD_DROPOFF=10`
- `THULIUM_FIELD_STATUS=11`

## Wdrożenie SQL (kolejność obowiązkowa)
```bash
mysql -u USER -p DB_NAME < sql/001_schema.sql
mysql -u USER -p DB_NAME < sql/003_indexes.sql
mysql -u USER -p DB_NAME < sql/002_triggers.sql
```

## Komendy CLI
```bash
php bin/thulium-bridge help
php bin/thulium-bridge validate-config
php bin/thulium-bridge run-once
php bin/thulium-bridge run-loop
php bin/thulium-bridge dry-run
php bin/thulium-bridge retry-failed [limit]
php bin/thulium-bridge cleanup
php bin/thulium-bridge health
```

## Cron (zalecany sposób produkcyjny)
Przykład:
```cron
* * * * * /usr/bin/php /opt/thulium-bridge/bin/thulium-bridge run-once >> /var/log/thulium-bridge-cron.log 2>&1
*/15 * * * * /usr/bin/php /opt/thulium-bridge/bin/thulium-bridge health >> /var/log/thulium-bridge-health.log 2>&1
0 3 * * * /usr/bin/php /opt/thulium-bridge/bin/thulium-bridge cleanup >> /var/log/thulium-bridge-cleanup.log 2>&1
```

## Logika niezawodności
- claim batch + status `processing`
- lock timeout dla porzuconych rekordów
- retry z exponential backoff
- dead-letter po przekroczeniu `WORKER_MAX_RETRIES`
- idempotencja przez checksum i mapowania `thulium_customer_map` / `thulium_trip_map`

## Założenia tenantowego API klientów
Są odseparowane w:
- `src/Infrastructure/Thulium/TenantCustomerApiAssumptions.php`

Do podmiany bez ingerencji w resztę logiki.

## Diagnostyka SQL
```sql
SELECT status, COUNT(*) FROM thulium_sync_queue GROUP BY status;
SELECT * FROM thulium_sync_queue WHERE status IN ('failed','dead') ORDER BY id DESC LIMIT 50;
SELECT * FROM thulium_sync_error_log ORDER BY id DESC LIMIT 100;
```

## Rollback integracji
```bash
mysql -u USER -p DB_NAME < sql/004_uninstall.sql
```
Skrypt usuwa wyłącznie obiekty integracji `thulium_*` oraz `trg_thulium_*`.
