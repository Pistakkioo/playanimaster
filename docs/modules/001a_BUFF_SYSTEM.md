# Module 016 — Buff / Debuff System

## Overview

Persistent **time-based** buffs and battle-scoped **turn-based** buffs for `animals` and `users_ig` (party-wide).

All wall-clock times use **`ANIMASTER_SERVER_TIMEZONE`** (default `UTC`) in `character_config.php`.

## Tables

| Table | Purpose |
|-------|---------|
| `buff_definitions` | Catalog: stat, modifier (`flat` / `percent`), debuff flag, i18n text |
| `entity_buffs` | Active **time-based** buffs on animal or user_ig |
| `battle_turn_buffs` | **Turn-based** buffs scoped to one battle (`solo_pve`, `pvp`, `party_pve`) |

## Layering

Effects are applied in order:

1. **Time buffs** — `ORDER BY dt_applied_utc ASC, id_entity_buff ASC`
2. **Turn buffs (in battle)** — `ORDER BY applied_at_turn ASC, applied_order ASC, id_battle_turn_buff ASC`

At **battle start**, animal buffs and owner `user_ig` buffs are merged and sorted by `dt_applied_utc` before modifying turn-0 stats.

## Battle integration

- **Solo PvE:** `solo_pve_start_battle.php` applies time buffs before turn 0 insert.
- **PvP:** `animaster_pvp_fetch_animal_snapshot_buffed()` applies time buffs before turn 0.
- **Turn buffs:** stored in `battle_turn_buffs`; cleared when battle ends (`BUFFS::onSoloPveBattleEnd`, PvP `animaster_pvp_finish_battle`).
- **Mid-battle turn buffs:** use `BUFFS::grantBattleTurnBuff()` + `BUFFS::applyBattleTurnLayersToStats()` (wire into move resolution when abilities grant turn effects).

## PHP API (`private_functions/buffs.php`)

- `BUFFS::grantTimeBuff($conn, $id_buff_definition, $entity_type, $id_entity, $duration_seconds, ...)`
- `BUFFS::grantBattleTurnBuff(...)`
- `BUFFS::applyAtBattleStart($conn, $id_animal, $id_user_ig, $stats)`
- `BUFFS::fetchDisplayForAnimal($conn, $id_animal, $id_user_ig, $lang)` — team panel

## Team panel

`get_team_info.php` returns `active_buffs[]` per animal. Client groups identical effects (same scope, stat, modifier, debuff flag), shows combined stat total (multiplicative % like battle layering), stack count, and expandable per-stack timers (`team.js`).

## Deploy

Run `private_functions/SQL/buff_system.sql` on production DB.

## Future

- Map ability `effect` tokens to `buff_definitions` / grant calls
- Apply `battle_turn_buffs` each turn in PvE/PvP move handlers
- `users_ig`-only buffs that do not map to combat stats (exp bonus, etc.)
