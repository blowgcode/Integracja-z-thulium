# Thulium Bridge (PHP 8.2, CLI, bez frameworka)

Niezależny bridge integracyjny synchronizujący dane z MySQL (`pasazerowie`, `przejazdy`, `adresy`) do Thulium API.

## Operacyjne komendy CLI
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

## Wdrożenie na Ubuntu/Debian (krok po kroku)
1. Utwórz katalog i użytkownika runtime (np. `www-data`):
   ```bash
   sudo mkdir -p /opt/thulium-bridge
   sudo chown -R www-data:www-data /opt/thulium-bridge
   ```
2. Skopiuj repozytorium i zainstaluj zależności:
   ```bash
   cd /opt/thulium-bridge
   composer install --no-dev --optimize-autoloader
   ```
3. Konfiguracja środowiska:
   ```bash
   cp .env.example .env
   ```
4. Uzupełnij `.env` (DB + Thulium Basic Auth).
5. SQL deploy (kolejność krytyczna):
   ```bash
   mysql -u USER -p DB_NAME < sql/001_schema.sql
   mysql -u USER -p DB_NAME < sql/003_indexes.sql
   mysql -u USER -p DB_NAME < sql/002_triggers.sql
   ```
6. Walidacja konfiguracji i testowy dry-run:
   ```bash
   php bin/thulium-bridge validate-config
   php bin/thulium-bridge dry-run
   ```

## Konfiguracja `.env`
Najważniejsze:
- `THULIUM_BASE_URL=https://vipmartour1.thulium.com/api`
- `THULIUM_AUTH_USER`, `THULIUM_AUTH_PASS`
- `THULIUM_GROUP_ID=1`
- `THULIUM_FIELD_TRIP_ID=7`
- `THULIUM_FIELD_TRIP_DATE=8`
- `THULIUM_FIELD_PICKUP=9`
- `THULIUM_FIELD_DROPOFF=10`
- `THULIUM_FIELD_STATUS=11`
- `WORKER_MAX_RETRIES=10`
- `WORKER_LOCK_TIMEOUT_SECONDS=120`

## Tryby uruchomienia
### Cron (tryb jednorazowy)
Uruchamiaj `run-once` co minutę.
Przykład: `deploy/cron/example.cron`.

### systemd (tryb pętli)
```bash
sudo cp deploy/systemd/thulium-bridge.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now thulium-bridge
```
Logi:
```bash
sudo journalctl -u thulium-bridge -f
```
Restart:
```bash
sudo systemctl restart thulium-bridge
```

### systemd timer (opcjonalnie)
Dla trybu `run-once`:
```bash
sudo cp deploy/systemd/thulium-bridge-once.service /etc/systemd/system/
sudo cp deploy/systemd/thulium-bridge.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now thulium-bridge.timer
```

### Supervisor
Użyj `deploy/supervisor/thulium-bridge.conf` (komenda `run-loop`).

## Kolejka i niezawodność
- Worker pobiera partię eventów i **atomowo claimuje** je jako `processing`.
- Rekordy z przeterminowanym lockiem (`locked_at`) wracają do obiegu.
- Retry: exponential backoff (`base^attempt`).
- Po przekroczeniu limitu prób event przechodzi do `dead`.
- Retry manualny: `php bin/thulium-bridge retry-failed`.

## Health checks i operacyjność
### Komenda health
```bash
php bin/thulium-bridge health
```
Zwraca JSON z licznikami: `pending`, `processing`, `failed`, `dead`, `done`.

### Cleanup
```bash
php bin/thulium-bridge cleanup
```
Czyści stare `done` i stare wpisy z `thulium_sync_error_log`.

### Przykładowe zapytania diagnostyczne SQL
```sql
SELECT status, COUNT(*) FROM thulium_sync_queue GROUP BY status;
SELECT * FROM thulium_sync_queue WHERE status IN ('failed','dead') ORDER BY id DESC LIMIT 50;
SELECT * FROM thulium_sync_queue WHERE status='processing' AND locked_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
SELECT * FROM thulium_sync_error_log ORDER BY id DESC LIMIT 100;
```

## Logging
- Logi do stdout (czytelne dla systemd/journalctl), opcjonalnie do pliku (`LOG_FILE`).
- Kontekst logów zawiera: `queue_id`, `aggregate_type`, `aggregate_id`, `event_type`, `attempt`.
- Brak logowania haseł/sekretów i pełnych payloadów PII (`ThuliumHttpClient` loguje metadane payloadu, nie wartości).

## Synchronizacja biznesowa
### ensureCustomer
1. lookup po `external_id` (`vipmart-pasazer-{id}`),
2. fallback po telefonie,
3. create jeśli nie znaleziono,
4. zapis mapowania + checksum (`thulium_customer_map`).

### ensureTripItem
1. lokalny lookup w `thulium_trip_map`,
2. jeśli brak mapy: zdalne wyszukanie przez `GET /customer_field_group_items` po field `THULIUM_FIELD_TRIP_ID`,
3. `PUT` gdy znaleziono, `POST` gdy brak,
4. zapis mapowania + checksum.

Statusy:
- insert -> `Nowy`
- update -> `Zmieniony`
- delete -> `Anulow` (domyślnie soft cancel)

## Założenia tenantowego API klientów (do potwierdzenia)
Utrzymywane w jednym miejscu: `src/Infrastructure/Thulium/TenantCustomerApiAssumptions.php`.
Aktualne założenia:
- `GET /customers?external_id=...`
- `GET /customers?phone=...`
- `POST /customers`
- `PUT /customers/{id}`

## Troubleshooting
1. `dead > 0` w health:
   - sprawdź logi `journalctl`,
   - sprawdź `thulium_sync_error_log`,
   - popraw przyczynę i uruchom `retry-failed`.
2. Rosnące `processing`:
   - zweryfikuj lock timeout,
   - sprawdź czy worker nie został ubity bez restartu.
3. Brak nowych synców:
   - sprawdź istnienie triggerów `trg_thulium_*`,
   - sprawdź połączenie DB i API,
   - uruchom `validate-config`.

## Rollback
1. Zatrzymaj usługę:
   ```bash
   sudo systemctl stop thulium-bridge
   ```
2. Wycofaj obiekty integracji:
   ```bash
   mysql -u USER -p DB_NAME < sql/004_uninstall.sql
   ```
3. Przywróć poprzednią wersję kodu i uruchom ponownie.
