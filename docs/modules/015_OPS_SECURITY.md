# Module 15 — Ops, security & GM tools

## Scope

- Rate limiting on `open_actions` (IP + account)
- Structured error logging (no secrets in output)
- GM web panel (token-gated like `dev_npcs.php`): kick, mute, teleport, item grant
- Combat replay from move logs
- Backup/restore runbook for static data exports
- Load testing script (N concurrent presence polls)

## Production

- `display_errors Off` on public endpoints
- MariaDB slow query log review
- Cron: stale battle cleanup, expired duel/trade requests
