# Module 12 — Raid bosses (turn-based)

**Depends on:** Combat engine (05), Dungeons (06), Party system (03)

## Core rule

**All raid combat is turn-based.** Scale = many participants in one turn queue + boss phase scripts — never real-time action.

---

## Participants

- 1 raid instance per party/alliance (8–24 players design target)
- Each player brings active team; all non-fainted animals enter global speed queue
- Boss may have **multiple parts** (head, claws) as separate entities in queue

---

## Rhythm mechanic (signature)

Boss phases read **raid telemetry from the previous player round**:

| Player round signal | Example boss response |
|---------------------|----------------------|
| `offensive_count >= 3` | Boss uses counter-attack (+50% dmg to last attackers) |
| `offensive_count == 0` (all heal/buff) | Boss enrages, gains stat boost |
| `element_fire >= 2` | Boss shields against fire next turn |
| Mixed pattern | Normal rotation move |

Players learn the **rhythm** — e.g. rotate one heal/buff every third round to prevent enrage.

### Implementation

```sql
raid_boss_templates (
  id_raid_boss,
  phase_json   -- [{ when: { offensive_min: 3 }, move_id: 42 }, ...]
);

raid_instances (
  id_raid_instance,
  id_raid_boss,
  flg_status,
  current_phase,
  telemetry_json  -- last round: { offensive: 4, elements: {...} }
);
```

`phase_json` evaluated server-side at start of **boss turn** before move selection.

---

## Action budget (optional hard mode)

- Max **K offensive** player moves per round (e.g. 5 in 12-player raid)
- Excess moves queued to next round or fizzle with warning
- Forces coordination without real-time

---

## UI telegraphs

Combat log lines before boss acts:

- “The colossus tracks your aggression…”
- “Calm round detected — the boss grows restless.”

Icons on boss portrait for current phase rule.

---

## Loot

- Personal roll + shared pool
- Tier by contribution (damage/heal tracked in move log)

---

## Phases

1. Prototype 4-player vs 1 boss (party PvE engine)
2. Telemetry + one rhythm script
3. Full instance + lockout + 12-player queue perf test
4. Alliance raid (multiple parties, one instance)

See [MMORPG_ROADMAP.md](../MMORPG_ROADMAP.md).
