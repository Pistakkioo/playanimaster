# Module 06 — Dungeons (instanced party PvE)

**Depends on:** Party system (03), Party PvE (02), Combat engine (05)

## Scope

- Instanced copy per party (no overworld wild entity)
- Encounter chains: trash → mini-boss → boss
- Chest loot, daily/weekly lockout
- Zone entry via NPC or portal object

## Tables (draft)

`dungeons`, `dungeon_encounters`, `dungeon_runs`, `dungeon_run_participants`

## Combat

Same turn queue as party PvE; encounters spawn scripted enemy teams from static data.
