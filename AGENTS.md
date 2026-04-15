# AGENTS rules for this repository

## Scope
These instructions apply to the entire repository.

## Hard constraints
1. Do **not** modify existing business tables (`pasazerowie`, `przejazdy`, `adresy`).
2. Do **not** modify existing business triggers.
3. Introduce changes only in integration-owned objects/files (`thulium_*`, `trg_thulium_*`, bridge code).

## Development process
1. Keep changes minimal and production-oriented.
2. Run tests/checks after changes (at least syntax checks if full tests unavailable).
3. Update README/CHANGELOG when behavior or operational procedure changes.
4. Explicitly document any tenant-specific API assumptions (especially customer endpoints).

## SQL safety
- Apply SQL in order: schema -> indexes -> triggers.
- Ensure uninstall scripts remove only integration objects.
