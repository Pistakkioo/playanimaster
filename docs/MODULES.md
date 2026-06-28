# Modules — roadmap, code map, and database deploy

**Full game plan:** [MMORPG_ROADMAP.md](MMORPG_ROADMAP.md)  
**SQL conventions (authoritative):** [private_functions/SQL/README.md](../private_functions/SQL/README.md)  
**Deploy steps:** [DEPLOY_GUIDE.md](DEPLOY_GUIDE.md)

---

## Database: three SQL files only

All schema and static data lives under `private_functions/SQL/`. **Do not** add module-specific `.sql` files elsewhere (`docs/`, `public_html/`, etc.). Legacy per-module files (`chat_system.sql`, `pvp_system.sql`, `buff_system.sql`, …) are **retired** — their content is consolidated here:

| File | Who runs it | Purpose |
|------|-------------|---------|
| [`00_tables.sql`](../private_functions/SQL/00_tables.sql) | **Fresh install only** | Baseline `CREATE TABLE`. **Never edit after commit** — new changes go in `01`. |
| [`01_alters_structure.sql`](../private_functions/SQL/01_alters_structure.sql) | **Existing databases** | Append-only: `ALTER TABLE`, new `CREATE TABLE` for upgrades, schema-tied backfills. |
| [`02_insert_static_data.sql`](../private_functions/SQL/02_insert_static_data.sql) | **All environments** (after schema) | Catalog seeds, `language_texts`, NPC content, `buff_definitions`, etc. Every `INSERT` must use full `ON DUPLICATE KEY UPDATE` (see README). |

### Fresh database (new Docker / greenfield)

1. `00_tables.sql` — full schema  
2. `02_insert_static_data.sql` — all static data  

Skip `01` unless you are replaying history on a clone; greenfield installs get the current shape from `00`.

### Existing database (local Docker, production)

1. Run **only new statements** appended since your last deploy — from the tail of `01_alters_structure.sql`, then `02_insert_static_data.sql`.  
2. **Do not** re-run the whole `01` file blindly if earlier blocks were already applied.  
3. Track what each environment has applied (deploy notes, migration log, or “last applied line / date” in your ops doc).

### When you ship a module that touches the DB

| Change type | File |
|-------------|------|
| New table (greenfield) | Add `CREATE TABLE` to `00` (new repos only) **and** append equivalent `CREATE TABLE` / `ALTER` to `01` |
| Column, index, constraint | Append to `01` |
| Reference data, i18n, buff catalog, NPC seeds | Append to `02` with `ON DUPLICATE KEY UPDATE` |
| PHP/JS that reads new columns/tables | Same change set as the SQL |

After code + SQL: test local → commit → deploy → run new SQL tail on production → smoke test.

**Asset bump:** increment `ANIMASTER_ASSET_VERSION` in `private_functions/character_config.php` when client JS/CSS changes.

---

## Module order (what to build next)

| Order | Module | Doc |
|-------|--------|-----|
| Done | PvP 1v1 | [modules/001_PVP_1v1.md](modules/001_PVP_1v1.md) |
| Done | Buff / debuff | [modules/001a_BUFF_SYSTEM.md](modules/001a_BUFF_SYSTEM.md) |
| **Next** | Player classes | [modules/001b_PLAYER_CLASSES.md](modules/001b_PLAYER_CLASSES.md) |
| Then | Party system | [modules/002_PARTY_SYSTEM.md](modules/002_PARTY_SYSTEM.md) |
| Then | Party PvE | [modules/002b_PARTY_PVE.md](modules/002b_PARTY_PVE.md) |
| Later | Combat engine refactor | [modules/005_COMBAT_ENGINE.md](modules/005_COMBAT_ENGINE.md) (after party PvE proves multi-actor turns) |

See [MMORPG_ROADMAP.md](MMORPG_ROADMAP.md) for the full phase list (quests, dungeons, raids, …).

---

## Shipped modules — code map

Schema for these modules is already in `00_tables.sql` (greenfield). Incremental alters and seeds are in `01` / `02` tails. On an **old** production DB, ensure missing tables/columns exist by applying the relevant `01` blocks (or a one-time catch-up) before new module work.

| Module | Doc | Main code paths | SQL location (consolidated) | Asset bump |
|--------|-----|-----------------|----------------------------|------------|
| Solo PvE | (baseline) | `battle_solo_pve/*`, `combat.js`, `solo_pve_*` | Core tables in `00`; alters in `01` | — |
| Chat | — | `chat.php`, `chat.js`, `poll_chat.php` | `chat_*` tables in `00`; local/party alters in `01`; strings in `02` | yes |
| Word filter | — | `chat_word_filter.php` | `chat_word_replacements` in `00` / `02` | — |
| Target UI | — | `target.js`, `world.js`, `game.css` | `language_texts` in `02` | yes |
| Trade | — | `trade.php`, `trade.js`, `open_actions/*trade*` | `trade_*` tables in `00` | yes |
| PvP 1v1 | [001_PVP_1v1.md](modules/001_PVP_1v1.md) | `pvp.php`, `duel.js`, `private_functions/pvp.php`, `pvp_*` open_actions | `pvp_*`, `battles_pvp*` in `00`; UI strings in `02` | yes |
| Buff / debuff | [001a_BUFF_SYSTEM.md](modules/001a_BUFF_SYSTEM.md) | `buffs.php`, battle start hooks, `get_team_info.php`, `team.js` | `buff_*` tables in `00`; definitions + team UI strings in `02` | yes |
| Team panel & reorder | — | `team.js`, `save_team_order.php`, `get_team_*` | `language_texts` in `02` (no schema) | yes |
| Dev tools | — | `dev_npcs.php`, `dev_static_data.php`, `dev_species.php` | Content export mirrors `02` patterns | — |

Module design docs under `docs/modules/` describe **behavior** only. For deploy, always follow this file and `private_functions/SQL/README.md`.

---

## Upcoming: Player classes (001b) — SQL checklist

When implementing [001b_PLAYER_CLASSES.md](modules/001b_PLAYER_CLASSES.md):

1. **`00_tables.sql`** — `player_classes`, `player_class_abilities`, `user_player_class_abilities` (greenfield only; do not edit if already committed — use `01` for live DBs per README).  
2. **`01_alters_structure.sql`** — `users_ig.id_player_class`, new tables on existing DBs.  
3. **`02_insert_static_data.sql`** — class tree seed, ability rows, `language_texts` for UI.  
4. **Code** — `player_class.php`, character create, profile, requirement/consequence hooks (phased per module doc).  
5. **Asset bump** — character create / HUD if client changes.

---

## Per-module deploy checklist (repeat every release)

- [ ] Schema/data appended to correct SQL file(s) per [SQL/README.md](../private_functions/SQL/README.md)  
- [ ] `02` inserts use full `ON DUPLICATE KEY UPDATE`  
- [ ] `00` untouched unless explicit greenfield baseline change  
- [ ] PHP/JS updated for new columns/tables  
- [ ] `ANIMASTER_ASSET_VERSION` bumped if client assets changed  
- [ ] SQL tail tested on local MariaDB (Docker / Adminer)  
- [ ] Commit → `deploy.ps1` → run **new** SQL on production → smoke test live site  

---

## Static data export

`dev_static_data.php` exports catalog tables for backup and merges. New reference tables should remain compatible with `02_insert_static_data.sql` upsert patterns so export/import and deploy stay aligned.
