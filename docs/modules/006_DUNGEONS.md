# Module 06 — Dungeons (instanced party PvE)

**Depends on:** Party system (02), Party PvE (02b), **[005c_full_combat_unification.md](005c_full_combat_unification.md)** (unified `battles` shell — **required before dungeon combat**)

## Scope

- Instanced copy per party (no overworld wild entity)
- Encounter chains: trash → mini-boss → boss
- Chest loot, daily/weekly lockout
- Zone entry via NPC or portal object

## Tables (draft)

`dungeons`, `dungeon_encounters`, `dungeon_runs`, `dungeon_run_participants`

Optional FK: `dungeon_runs.current_id_battle` or per-encounter `dungeon_encounter_runs.id_battle` → `battles.id_battle`.

## Combat

Each encounter starts a `battles` row (`battle_type = dungeon`, `planning_mode = simultaneous_confirm`). Alliance A = party animals (same snapshot rules as party PvE). Alliance B = scripted enemies from `dungeon_encounters` team definition — inserted as `battle_participants` (`participant_kind = scripted`), not overworld `wild_animals`.
