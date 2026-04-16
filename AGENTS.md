# AGENTS.md

## Cel repo
Niezależny bridge PHP 8.2 synchronizujący dane z MySQL do Thulium.

## Twarde zasady
- Nie dodawaj Laravel, Symfony ani innego frameworka.
- Nie twórz zależności od Panel3.
- Nie modyfikuj istniejących triggerów biznesowych.
- Nie modyfikuj istniejących tabel biznesowych.
- Twórz tylko nowe tabele i nowe triggery integracyjne.
- Wszystkie założenia o niepotwierdzonych endpointach Thulium izoluj w adapterach.
- Po każdej większej zmianie uruchom testy.
- Aktualizuj README, jeśli zmienia się wdrożenie lub konfiguracja.
- Nie loguj sekretów ani pełnych danych osobowych.
