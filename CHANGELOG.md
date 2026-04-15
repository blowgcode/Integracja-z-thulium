# Changelog

## [Unreleased]
### Added
- Operational commands: `run-once`, `run-loop`, `health`, `retry-failed`, `cleanup`.
- systemd timer and oneshot service examples.
- cron examples for run-once, health, cleanup.
- repository-level `AGENTS.md` rules.

### Changed
- Queue worker now claims and locks events (`processing` + lock timeout handling).
- Retry handling now supports dead-letter status (`dead`) after max attempts.
- README expanded with deployment/troubleshooting/runbook details.
