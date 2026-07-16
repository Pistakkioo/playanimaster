# ANIMASTER — MMORPG roadmap

Turn-based creature MMORPG. **All combat stays turn-based** — solo wild fights, party dungeons, PvP duels, PK, and raid bosses included. Larger fights add **coordination mechanics** (turn budgets, boss phases, rhythm windows) instead of real-time action.

---

## Current state (baseline — Q2 2026)

### Implemented and playable

| Area | Status | Key paths |
|------|--------|-----------|
| Account & characters | Done | `login_account.php`, `create_character.php`, `select_character.php`, session auth |
| Open world (zone 1000) | Done | `world.js`, `get_other_players.php`, presence on `users_ig` |
| Wild spawn | Done | `spawn.js`, `check_spawn.php`, `get_wild_animals.php`, `FUNZIONI::SpawnAnimals` |
| Solo PvE combat | Done | `combat.js`, `battle_solo_pve/*`, `battles_solo_pve*` tables, turn order by speed |
| Team & animals | Done | `team.js`, `get_team_*`, nicknames, team order, HP recovery |
| Inventory & items | Done | `inventory.js`, `use_item.php`, battle items |
| NPC dialogues | Done | `dialog.js`, `get_npcs.php`, consequences, first companion |
| Chat (multi-channel) | Done | `chat.php`, `chat.js` — local, @, !zone, $clan, %alliance, #party |
| Player trade | Done | `trade.php`, `trade.js`, request → offer → confirm |
| Target panel | Done | `target.js` — inspect player, start trade |
| Notifications | Done | `notifications.js`, `get_notifications.php` |
| i18n UI strings | Partial | `language_texts`, `AnimasterLang` |
| Dev content tools | Done | `dev_npcs.php`, `dev_static_data.php` |
| Deploy pipeline | Done | Docker, `deploy.ps1`, `DEVOPS_GUIDE.md` |

### Schema exists, gameplay not wired

| Area | DB / stubs | Gap |
|------|------------|-----|
| Quests | `quests`, `user_quests`, `quest_requirements` | No client/API quest tracker or turn-in |
| Party | `users_ig.id_party`, chat `#` channel | No party entity, invite, roster, or shared activity |
| Clans / alliances | `clans`, `alliances`, chat `$` `%` | User-created orgs not implemented |
| Player classes | `users_ig.character_type` (cosmetic only) | No nerd/stud tree, skills, or quest promotions |
| Dungeons / instances | — | No instanced zones |
| PvP / PK | `flg_battling` flag only | No challenge or battle tables for players |
| Raid bosses | — | No multi-participant boss combat |
| Economy | `gold`, item prices | No shops, mail, auction house |
| Multiple zones | `zones` table, zone 1000 seeded | No travel UI / zone gates |

### Architecture notes (keep for all modules)

- **Server authoritative** — PHP + MariaDB resolve combat, loot, and state.
- **Poll-based sync** — presence ~150 ms, entities ~300 ms; WebSocket optional later.
- **Combat = state machine** — session row + move log; client is view + input.
- **Refactor direction** — shared engine in `private_functions/combat/` is done; **next:** unified `battles` schema ([005c](modules/005c_full_combat_unification.md)) before dungeons/raids.

---

## Design pillars

1. **Turn-based everywhere** — No real-time combat. Scale = more actors per turn queue, not faster clicks.
2. **Readable telegraphs** — Bosses expose phases and “rhythm” (see Raids module) so groups learn patterns.
3. **Module delivery** — One vertical slice per branch: SQL → PHP → JS → `language_texts` → asset bump → deploy.
4. **Same JSON contract** — Future Unity/mobile clients consume the same `open_actions` envelopes.
5. **Safe dev loop** — Docker → `dev_*` tools → static export → deploy → production SQL.

---

## Module map (phases)

Phases are ordered by dependency and player value. **Start with 001, then 001b, then party stack.**

| # | Module | Doc | Depends on | Player-facing goal |
|---|--------|-----|------------|-------------------|
| **01** | **PvP 1v1** | [modules/001_PVP_1v1.md](modules/001_PVP_1v1.md) | Solo combat | Challenge another player to a duel |
| **01b** | **Player classes** | [modules/001b_PLAYER_CLASSES.md](modules/001b_PLAYER_CLASSES.md) | NPC/dialog, quests (partial) | Nerd/Stud → 25 → 50 specializations, class skills |
| **02** | **Party PvE** | [modules/002_PARTY_PVE.md](modules/002_PARTY_PVE.md) | 001b, 03 (party) | Group up and fight wilds together |
| 03 | Party system | [modules/003_PARTY_SYSTEM.md](modules/003_PARTY_SYSTEM.md) | — | Invites, roster, leader, # chat works |
| 04 | Quest system | [modules/004_QUESTS.md](modules/004_QUESTS.md) | NPC/dialog | Track objectives, rewards, chains |
| 05 | Combat engine refactor | [modules/005_COMBAT_ENGINE.md](modules/005_COMBAT_ENGINE.md) | 01–02 | Shared turn resolver ✅ |
| **05c** | **Full combat unification** | [modules/005c_full_combat_unification.md](modules/005c_full_combat_unification.md) | 05, 005b | Single `battles` + N participants (**before dungeons**) |
| 06 | Dungeons (instanced party PvE) | [modules/006_DUNGEONS.md](modules/006_DUNGEONS.md) | 02, 03, **05c** | Fixed encounters, chests, daily lockout |
| 07 | Evolution & DNA | [modules/007_EVOLUTION.md](modules/007_EVOLUTION.md) | Solo PvE drops | Grow and evolve team |
| 08 | PK & PvP zones | [modules/008_PK_PVP_ZONES.md](modules/008_PK_PVP_ZONES.md) | 01 | Opt-in hostile areas, karma/law |
| 09 | Clan & alliance | [modules/009_CLAN_ALLIANCE.md](modules/009_CLAN_ALLIANCE.md) | Social | Player orgs, `$` `%` chat, stash |
| 10 | Economy & shops | [modules/010_ECONOMY.md](modules/010_ECONOMY.md) | Items, gold | NPC vendors, repair, sinks |
| 11 | Mail & auction | [modules/011_MAIL_AUCTION.md](modules/011_MAIL_AUCTION.md) | 10 | Async trade, listings |
| 12 | Raid bosses | [modules/012_RAID_BOSSES.md](modules/012_RAID_BOSSES.md) | **05c**, 06 | Turn-based raids, rhythm mechanics |
| 13 | World expansion | [modules/013_WORLD_ZONES.md](modules/013_WORLD_ZONES.md) | Spawn, NPC tools | Multiple maps, travel, zone rules |
| 14 | Progression meta | [modules/014_PROGRESSION.md](modules/014_PROGRESSION.md) | Quests, raids | Titles, achievements, seasons |
| 15 | Ops & anti-abuse | [modules/015_OPS_SECURITY.md](modules/015_OPS_SECURITY.md) | All | Rate limits, logging, GM tools |

> **Note:** Module 03 (Party system) is a hard prerequisite for **Party PvE** roster rules. If you start PvP first, implement 01 in parallel with a minimal party stub (02 doc includes “Phase A” party tables).

---

## Recommended execution order

```
Now     → 01 PvP 1v1          (reuse solo combat UX, 2 human sides)
Next    → 01b Player classes   (nerd/stud, Lv25/50 quest promotions, class skills)
        → 03 Party system      (minimal: create, invite, leave, leader)
Then    → 02 Party PvE        (multi-player turn queue + class synergy) ✅
        → 05 Combat engine     (shared resolver) ✅
        → 05c Unified battles  (single battles + participants — **do before dungeons**)
        → 04 Quests
        → 06 Dungeons
        → 07 Evolution
        → 08 PK zones
        → 09 Clans
        → 10–11 Economy
        → 12 Raid bosses
        → 13–15 Polish & scale
```

---

## Turn-based combat at scale (raids preview)

Raid bosses stay turn-based. Difficulty comes from **phase scripts**, not APM:

- Each **boss turn** reads raid state: e.g. count of offensive abilities used by players last round.
- **Rhythm windows** — e.g. if ≥3 physical attacks hit, boss counters; if mostly support, boss buffs; groups learn to rotate.
- **Action budget** — optional cap on player offensive moves per round in epic fights.
- **Telegraph log** — combat UI shows “Boss is tracking aggression…” before resolve.

Full spec: [modules/012_RAID_BOSSES.md](modules/012_RAID_BOSSES.md).

---

## MMORPG component checklist (player expectations)

| Component | Expected feature | ANIMASTER status |
|-----------|------------------|------------------|
| Character creation | Name, appearance, starter | Done (cosmetic type); **class tree module 01b** |
| Player classes | Roles, skills, specialization | **Module 01b** |
| Open world | See others, explore, chat | Done |
| Creatures / team | Catch, train, team of 6 | Partial (gift/start, wild free; no catch ball loop) |
| Turn combat | Abilities, items, switch, flee | Solo PvE done |
| PvP duel | Challenge, fair fight | **Module 01** |
| Party | Group, shared content | **Modules 02–03** |
| Dungeons | Instanced loot runs | Module 06 |
| Raids | Large boss, coordination | Module 12 |
| Quests | Story + dailies | Module 04 |
| NPCs & dialog | Branching, rewards | Done (content tools ready) |
| Inventory & items | Use, stack, trade | Done |
| Player trade | Secure exchange | Done |
| Economy | Shops, gold sinks | Module 10 |
| Mail / AH | Async exchange | Module 11 |
| Guilds | Clan, alliance chat | Module 09 |
| PK / law | Risk zones | Module 08 |
| Progression | Level, evolve, gear | Partial |
| Social | Chat channels | Done |
| Leaderboards | PvP, raids | Module 14 |
| Events | Seasonal world | Module 14 |
| Localization | EN / IT / PT | Partial |

---

## Per-module delivery checklist (copy for every module)

1. Design doc in `docs/modules/`
2. SQL migration in `private_functions/SQL/` + entry in `docs/MODULES.md`
3. PHP: `private_functions/<module>.php` + `open_actions/*`
4. Client: JS module + `game.php` hooks + CSS
5. `language_texts_*` inserts
6. Bump `ANIMASTER_ASSET_VERSION`
7. Test Docker → export static data if needed → deploy → production SQL
8. Smoke test with two accounts (for social/combat modules)

---

## Related docs

- [MODULES.md](MODULES.md) — SQL migration log
- [DEVOPS_GUIDE.md](DEVOPS_GUIDE.md) — Git, Docker, deploy
- [private_functions/animaster_planning.md](../private_functions/animaster_planning.md) — Original prototype spec (Italian)
- Module specs: [docs/modules/](modules/)
