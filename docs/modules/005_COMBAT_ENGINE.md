# Module 05 — Combat engine refactor

**Depends on:** PvP 1v1 (01), Party PvE (02)

## Goal

Single authoritative combat engine used by:

- `solo_pve`
- `party_pve`
- `pvp`
- `dungeon` (future)
- `raid` (future)

Two layers:

1. **Planning** — collect human input for the upcoming round (mode-specific rules).
2. **Resolution** — build a speed-sorted execution queue, run slots via `MoveResolver` / mode handlers, advance the round once.

Party PvE (`002b_PARTY_PVE.md`) is the reference implementation for multi-human planning. PvP uses the same *simultaneous submit → resolve* pattern without a confirm step. Solo remains instant-action (no planning table).

---

## Deliverables

```
private_functions/combat/
  CombatSession.php      # round advance + combatants meta — ✅ party / pvp / solo
  CombatantSnapshot.php  # canonical fighter shape + converters — ✅
  TurnQueue.php          # speed-sorted execution slot builder — ✅ party / pvp / solo order
  MoveResolver.php       # damage, effects, faint — ✅ shared by solo / party / pvp
  Permissions.php        # planning-phase quorum — ✅ party + pvp
  AiWild.php             # wild ability pick + party target pick — ✅ party + solo
  SoloPveController.php  # solo turn orchestration — ✅ endpoint thin wrapper
```

### MoveResolver — done

Wired into:

- `public_html/funzioni/battle_solo_pve/p_a_move.php` and `w_a_move.php`
- `private_functions/party_pve.php` (`animaster_party_pve_apply_ability_damage`)
- `private_functions/pvp.php` (`animaster_pvp_execute_action` ability branch)

### CombatSession — in progress

Owns **round lifecycle** and **shared combatant meta**:

| Concept | Party PvE | PvP |
|---------|-------------|-----|
| `current_turn` at start | `0` (last resolved) | `1` (open planning turn) |
| Client planning param | `current_turn + 1` | `current_turn` |
| After resolve | `completePartyPveRound()` | `completePvpPlanningTurn()` |

**Participant snapshot (`CombatantSnapshot.php`):** canonical fighter shape + converters from party participants / solo move rows / PvP state. Every mode meta now includes additive `combatants[]` (client stat panel + `005b` buff strip). Legacy fields (`party_allies`, move rows) unchanged.

Helpers: `CombatSession::attachCombatants()`, `combatantsFromPartyParticipants()`, `combatantsFromSoloMoves()`, `combatantsFromPvpState()`.

### TurnQueue — in progress

**Resolution only** — not “whose menu is open”. Responsibilities:

1. Accept execution **slots** (human choice + actor snapshot, or AI placeholder).
2. Sort by `spd` DESC; ties broken by insertion order.
3. Return ordered list for the mode executor to walk (early exit on win/loss/flee).

Mode-specific slot builders:

| Mode | Human slots | AI slots | Execute |
|------|-------------|----------|---------|
| `solo_pve` | 1 (instant, no table) | 1 wild | `SoloPveController` → legacy move includes + `MoveResolver` / `AiWild` |
| `pvp` | 1 per player (submit = lock) | 0 | Speed order of active animals (`animaster_pvp_resolve_turn_choices`) |
| `party_pve` | 1 per confirmed alive member | 1 wild **per alive party member** | `animaster_party_pve_resolve_round` |

After all slots in a round execute (or battle ends early): advance `current_turn`, clear planning choices, reset `dt_round_started`, tick buffs (future `005b`).

### Permissions — in progress

**Planning-phase rules**, not resolution:

| Mode | Staging | Confirm | Resolve when |
|------|---------|---------|--------------|
| `solo_pve` | N/A — action executes immediately | N/A | Each request |
| `pvp` | Submit locks choice (`pvp_turn_choices`) | No confirm step | Both players submitted, **or** either submits flee |
| `party_pve` | Stage freely; change clears others’ confirms if already confirmed | Per-player Confirm | All alive+active confirmed, **or** leader confirms flee |

Party-only extras (stay in `party_pve.php` until extracted): inactivity vote, leader-only flee, `flg_active` on depart.

### AiWild — done

Shared wild AI in `AiWild.php`:

- `pickRandomAbility()` — random unlocked ability from `species_abilities` (used by solo `w_a_move.php`, party wild slots, inactivity forced action)
- `pickRandomPartyTarget()` — random alive+active party member (party PvE)
- `fetchUnlockedAbilities()` — full pool for future weighted/smart AI

Party wrappers `animaster_party_pve_fetch_random_wild_ability` / `animaster_party_pve_pick_wild_target` delegate to `AiWild`.

### SoloPveController — done

`SoloPveController::handleRequest()` replaces inline orchestration in `solo_pve_get_battle_info.php`:

- Validates battle ownership
- Loads previous-turn state from `battles_solo_pve_moves`
- `TurnQueue::orderSoloTurnSlots()` — player first for item/switch/flee; speed order for abilities
- Includes legacy move scripts (`p_a_move`, `w_a_move`, etc.) until those are inlined
- Returns `#`-joined move JSON (unchanged client contract)

---

## Migration order

1. ✅ `MoveResolver` — formulas unified
2. ✅ `TurnQueue` + `Permissions` + `CombatSession` helpers; party PvE + PvP resolve gate
3. ✅ `AiWild`; wired party wild slots + solo `w_a_move.php`
4. ✅ Solo PvE thin controller (`SoloPveController` + `TurnQueue::orderSoloTurnSlots`)
5. ✅ Participant snapshot + unified `combatants[]` meta (all three modes)
6. Dungeon/raid slot builders on same `TurnQueue`; buff tick hook (`005b`)

---

## Done when

- All battle types use `MoveResolver` for ability math ✅
- Planning + resolution contracts live in `combat/` with no duplicated speed-sort or quorum logic
- Solo PvE regression passes
- Party PvE + PvP regression passes (staging/confirm/submit flows unchanged for clients)
- Full module: shared session serialization + `TurnQueue` round advance + buff tick hook for `005b`

**Current milestone:** `005b` buff-aware resolver + stat panel UI; optional inline solo move includes into controller.
