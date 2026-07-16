# Module 05b — Combat buff/debuff visibility & live-calculated stat layers

**Depends on:** Buff / debuff system (001a), Party PvE (002b), **Combat engine refactor (005) — must land first.**

## Why this comes *after* 005, not before

Buffs/debuffs and "live-calculated stats" are a **turn-resolution concern**. If this module were implemented before 005, the ability-effect-granting + live-stat-recompute logic would have to be built three times (once each for `solo_pve`, `pvp`, `party_pve` — each with its own turn-chain quirks, as documented below), then thrown away/re-merged when 005 unifies them into `MoveResolver.php`. Sequencing this module **after** 005 means:

- `MoveResolver.php` gets buff-aware damage/accuracy/speed math **from day one**, instead of being retrofitted.
- The ability-effect-granting logic (see below) is written **once**, in the shared resolver, not three times.
- The legacy `abilities.effect` string-hack (described below) only needs to be retired in one place.
- `CombatSession.php`'s serialization becomes the single place buff data is exposed to the frontend, rather than bolting `active_combat_buffs` onto three separate ad-hoc `*_meta` envelopes.

This doc still captures the **current (pre-005) state** below for context/rationale on *why* this system is needed — but every implementation section targets the **post-005 shared engine**, not the three legacy codebases.

## Goal

1. Every ability with a stat-changing effect grants a **battle-scoped buff/debuff** to its target (or the caster, for self-buffs), starting the turn it lands.
2. Combat stats are **live-calculated** every time they're needed (base stats + all active layers, recomputed fresh) instead of being permanently multiplied into a persisted "current stat" value. Removing/expiring a layer must instantly restore the correct value with zero residue — no order-dependent rollback math.
3. All battle participants (player animals, wild animals, party allies) show their active buffs/debuffs in the combat UI as a small square icon per buff **type**, with a stack-count badge, in all battle types (`solo_pve`, `pvp`, `party_pve`).
4. While in combat, each animal gets an **(i) info affordance** that opens a **draggable, closeable stat panel** listing that animal's combat stats. Stats modified by active buff/debuff layers show the **effective value in bold**, inline buff icon(s), and a tooltip with **duration + stacked effect label**; unmodified stats show base value only.

## Current state pre-005 (context/rationale only — not the implementation target)

The correct architecture **already exists but is disconnected**:

| Piece | State |
|---|---|
| `buff_definitions`, `entity_buffs` (time-based), `battle_turn_buffs` (turn-based, per-battle, `entity_type` already includes `'wild'`) | Schema is fully built (see [001a_BUFF_SYSTEM.md](001a_BUFF_SYSTEM.md)) |
| `BUFFS` class (`private_functions/buffs.php`) | `applyLayersToStats()` already does the "live-calculate, never persist the modified value" pattern correctly. `grantBattleTurnBuff()`, `tickBattleTurnBuffs()`, `fetchActiveBattleTurnLayers()` all exist. |
| Actual usage of the above | **Nobody calls `grantBattleTurnBuff` in production.** Turn buffs are never ticked. `applyForActiveBattleAnimal` (which would apply them) is only wired into `solo_pve`'s team-switch flow and battle-start baseline — never into the actual per-turn damage/accuracy resolution. `party_pve` never touches `BUFFS` at all. |
| Frontend | No combat-time buff display anywhere. `team.js` shows **time-based** buffs only, out of combat, as a text list (name/description/expandable stack timers) via `get_team_info.php` — good precedent for stacking/grouping logic, wrong visual for combat (list vs. icon). |

Separately, there's a **legacy, currently-live mechanism** that is exactly the bug pattern you want to avoid:

- `abilities.effect` is a free-text token like `lower_target_atk_10_%` (`direction_target_stat_amount_unit`), rolled against `abilities.effect_chance`.
- `solo_pve` (`p_a_move.php` / `w_a_move.php`) and `party_pve` (`animaster_party_pve_apply_ability_stat_effect`) both parse this string and **directly multiply it into the stat value that then gets persisted** — in `solo_pve` that's the `_res_atk`-style column chained turn-to-turn through `battles_solo_pve_moves`; in `party_pve` it's written straight into `battles_party_pve_participants.atk` etc. Once applied there is no stored "original" value and no expiry — it's permanent for the rest of the battle, and stacking multiple different effects multiplies them in whatever order they happened to apply. This is the "weird rollback with multiple layers" problem.
- `pvp` fetches `effect`/`effect_chance` but **never applies them at all** (dead code) — so PvP currently has zero stat-effect abilities in practice, and PvP's own turn-to-turn stat carry (`p_a_res_atk` etc. in `battles_pvp_moves`, set once at turn 0 and never recomputed) has the same latent bug, just unexercised.
- Only 5 abilities currently use this: `Hiss`/`Growl` (atk -10%), `Fool Around` (def -10%), `Scrape dirt` (acc -10%), `Wing Clap` (acc -20%) — all `lower_target_*`, no `increase_*` ones exist in data despite the code supporting that direction.

**Decision (per stakeholder sign-off):** retire the legacy `effect`/`effect_chance` parsing entirely, migrate its 5 abilities into the buff-based structure, buff durations are per-ability (not a single global constant), the new combat icon strip merges **both** time-based and turn-based buffs, and icons are CSS-only (colored square + stat abbreviation, no new art asset pipeline) for v1.

The "base stats vs. live-calculated effective stats" separation — including `solo_pve`'s riskier turn-to-turn `_res_*` chain — is **not** a separate rework this module does on the side; it falls out naturally once 005 migrates every battle type to `MoveResolver.php`, which is buff-aware from the start (see Target architecture below). That's the whole point of the reordering.

## Target architecture

**This targets the post-005 shared engine.** By the time this module starts, `CombatSession.php` owns loading/saving one canonical combatant state per side (regardless of whether the battle is `solo_pve`, `pvp`, `party_pve`, or later `dungeon`/`raid`), and `MoveResolver.php` owns damage/effects/faint math for all of them. Concretely, whatever combatant snapshot shape 005 settles on, it must uphold:

**Base stats vs. effective stats — hard separation:**

- *Base stats* = the combatant's persisted stat snapshot as `CombatSession` loads it (DNA/level formula, or whatever 005's session model stores). These are **never mutated by a combat effect again**. The only writes back to persisted stats are HP changes (damage/heal) and permanent things unrelated to combat buffs (level up, team swap).
- *Effective stats* = base stats with every active layer applied on top, computed **on demand, every time inside `MoveResolver`**, via `BUFFS::applyLayersToStats()` (already correct/order-independent: percent layers multiply the base once each, flat layers add once each — no compounding chains). Effective stats are used for that instant's math (damage roll, accuracy check, speed/turn order) and then discarded — never written back into the session.
- A buff/debuff is a **removable, independent layer row** in `battle_turn_buffs`. Removing/expiring a row simply removes it from the next live-recompute — nothing to "undo" mathematically. This is what makes a future "cleanse" ability trivial: just `DELETE` (or zero out `turns_remaining` on) the target's rows.

**Buff source of truth:** reuse `buff_definitions` + `battle_turn_buffs` as-is (schema already correct). No new buff table needed — the "dedicated table" the feature calls for already exists; it just needs to be fed and consumed. `battle_turn_buffs.battle_type` already enumerates `solo_pve`/`pvp`/`party_pve` — extend that enum when `dungeon`/`raid` land.

**Identity convention for `battle_turn_buffs.id_entity`** (adapt to whatever combatant-id scheme `CombatSession` introduces; these are the constraints it must satisfy):
- `entity_type = 'animal'` → real `id_animal`
- `entity_type = 'user_ig'` → real `id_user_ig` (party-wide buffs)
- `entity_type = 'wild'` → a per-battle synthetic id that's stable for the lifetime of one wild combatant and unique within `id_battle` (so multi-wild encounters like future dungeon/raid fights don't collide)

## Schema changes

All per [db-schema-migrations](../../.cursor/rules/db-schema-migrations.mdc): new table → `00_tables.sql` + `01_alters_structure.sql`; alters to existing tables → `01_alters_structure.sql` only.

### New table: `ability_effects` (replaces reading of `abilities.effect`/`effect_chance`)

```sql
CREATE TABLE IF NOT EXISTS playanimaster_db.ability_effects (
    id_ability_effect INT(11) NOT NULL AUTO_INCREMENT,
    id_ability INT(11) NOT NULL,
    id_buff_definition INT(11) NOT NULL,
    effect_target ENUM('self','target') NOT NULL DEFAULT 'target',
    effect_chance TINYINT UNSIGNED NOT NULL DEFAULT 100,
    duration_turns INT(11) NOT NULL DEFAULT 3,
    sort_order INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_ability_effect),
    KEY idx_ability_effects_ability (id_ability),
    KEY idx_ability_effects_definition (id_buff_definition),
    CONSTRAINT fk_ability_effects_ability FOREIGN KEY (id_ability) REFERENCES abilities (id_ability),
    CONSTRAINT fk_ability_effects_definition FOREIGN KEY (id_buff_definition) REFERENCES buff_definitions (id_buff_definition)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

- One row per (ability, effect). Supports multiple effects per ability in the future (e.g. a move that both lowers `def` and raises caster `spd`) without another schema change.
- `duration_turns` is authoritative per-ability, per the chosen design (no global fallback constant needed since the column is `NOT NULL`).
- `abilities.effect` / `abilities.effect_chance` become **deprecated, unread columns** (kept — `00_tables.sql` is frozen, can't drop). Add a one-line comment pointer to this doc directly above their definitions is not allowed (frozen block); instead note the deprecation here and in `private_functions/SQL/README.md` if it gets a "deprecated columns" section.

### Alter: `battle_turn_buffs` — traceability

```sql
ALTER TABLE playanimaster_db.battle_turn_buffs
    ADD COLUMN id_ability_effect INT(11) NULL AFTER id_buff_definition;
```

Purely informational (debugging/tooltips: "why do I have this debuff" → which ability caused it); not required for the stat math itself.

### New `buff_definitions` seed rows (for the 5 migrated abilities)

`def_down_10` (id 2) already exists and covers `Fool Around`. Need 3 new rows: `atk_down_10`, `acc_down_10`, `acc_down_20` (all `target_entity='animal'`, `is_debuff='S'`, `modifier_kind='percent'`).

### Data migration (`02_insert_static_data.sql`)

Insert the new `buff_definitions` rows, then `ability_effects` rows mapping the 5 abilities:

| Ability | New buff_definition | effect_target | effect_chance | duration_turns |
|---|---|---|---|---|
| Hiss / Growl | `atk_down_10` | target | 100 | 3 |
| Fool Around | `def_down_10` (existing id 2) | target | 100 | 3 |
| Scrape dirt | `acc_down_10` | target | 60 | 3 |
| Wing Clap | `acc_down_20` | target | 100 | 3 |

(3 turns chosen as a sane starting default per ability during migration — tune per-ability afterward via the row itself, no code change needed.)

## Backend wiring (single shared engine, post-005)

Shared pieces added to `private_functions/buffs.php` (battle-type-agnostic — take `battle_type`/`id_battle` as plain parameters, same as `BUFFS` already does today):

- `BUFFS::rollAbilityEffects($conn, $id_ability)` — fetch `ability_effects` rows for an ability (with `buff_definitions` joined).
- `BUFFS::grantAbilityEffect($conn, $battle_type, $id_battle, $ability_effect_row, $caster_entity, $target_entity, $applied_at_turn)` — rolls `effect_chance`, resolves `self`/`target` to the right `(entity_type, id_entity)`, calls `grantBattleTurnBuff()`.
- `BUFFS::computeEffectiveStats($conn, $battle_type, $id_battle, $entity_type, $id_entity, $id_user_ig_or_null, array $base_stats)` — thin wrapper combining `applyAtBattleStart` (time layers) + `applyBattleTurnLayersToStats` (turn layers) in one call.
- `BUFFS::fetchCombatDisplay($conn, $battle_type, $id_battle, $entity_type, $id_entity, $id_user_ig_or_null, $lang)` — merges time layers + turn layers into one list shaped for the frontend icon strip (see Frontend section), reusing the grouping-by-identical-effect logic already proven in `team.js` (ported to PHP or kept client-side — see open question).
- `BUFFS::fetchCombatStatSheet($conn, $battle_type, $id_battle, $entity_type, $id_entity, $id_user_ig_or_null, array $base_stats, $lang)` — returns one row per combat stat (`hp`, `max_hp`, `atk`, `def`, `matk`, `mdef`, `acc`, `eva`, `cr`, `spd`) with `base`, `effective`, and the subset of `active_combat_buffs` entries that touch that `stat_key` (empty when unchanged). Used by the per-animal stat inspector panel; must use the same live-recompute path as `computeEffectiveStats`.

These plug into 005's deliverables at exactly three points, **written once**:

1. **`MoveResolver.php`** — wherever it currently computes "attacker/defender effective stats for this move" (the single successor to today's three separate damage-calc blocks), it calls `BUFFS::computeEffectiveStats(...)` for each side instead of trusting a persisted/carried-forward stat. After resolving an ability that has `ability_effects` rows, it calls `BUFFS::grantAbilityEffect(...)` for each one. This is the crux of the whole module: because there's one resolver, this is a single code path instead of three.
2. **`TurnQueue.php`** — after a **resolved round** completes (all execution slots in the speed-sorted queue have run, or the battle ended early), advance `current_turn` once and call `BUFFS::tickBattleTurnBuffs($conn, $battle_type, $id_battle)` exactly once. A "round" means one planning → resolution cycle: solo = 1 human + 1 wild action; pvp = up to 2 human actions in speed order; party_pve = N confirmed party actions + N wild actions (N = alive party size). Do not tick on planning polls or partial submissions.
3. **`CombatSession.php`** — on battle end (win/loss/flee/disband, wherever that's centralized post-005), calls `BUFFS::clearBattleTurnBuffs($conn, $battle_type, $id_battle)`.

No per-battle-type special-casing should be needed inside `buffs.php` itself — if `MoveResolver`/`TurnQueue` end up needing different call shapes per battle type, that's a signal 005's abstraction isn't finished yet, not that this module should special-case around it.

**Legacy `abilities.effect` retirement** happens as part of wiring `MoveResolver`: the `explode('_', $effect)` parsing (found today in `p_a_move.php`, `w_a_move.php`, `animaster_party_pve_apply_ability_stat_effect`; fetched-but-unused in `pvp.php`) is dropped entirely in favor of reading `ability_effects` rows — there is exactly one place doing this once `MoveResolver` is the only ability-resolution code path left.

## API / meta exposure (frontend needs to see this)

Exact shape depends on what 005 lands on for `CombatSession`'s client-facing serialization. Two acceptable outcomes, in order of preference:

1. **Preferred:** 005 already unifies the response envelope across battle types — in that case `active_combat_buffs` is just one more field per combatant in that shared shape, done once.
2. **Fallback (if 005 keeps each battle type's existing response format for compatibility):** extend `pvp_meta` / `party_pve_meta` per participant with `active_combat_buffs: [...]`, and add a new `solo_pve_meta` envelope (`solo_pve` currently has none — it only returns raw move rows) carrying the same field for `p_a`/`w_a`. Same JSON-string-in-envelope pattern as the other two, additive, existing move-row consumers unaffected.

Each combatant should also expose a **`combat_stat_sheet`** array (or equivalent keyed object) for the stat inspector panel — built server-side via `fetchCombatStatSheet` so the client does not re-derive effective values:

```json
{
  "stat_key": "atk",
  "label": "Attack",
  "base": 42,
  "effective": 38,
  "is_modified": true,
  "buffs": [
    {
      "buff_code": "atk_down_10",
      "name": "Attack Down",
      "is_debuff": true,
      "total_effect_label": "-10% ATK",
      "turns_remaining": 2,
      "scope": "turn"
    }
  ]
}
```

When `is_modified` is false, omit `buffs` (or send `[]`) and set `effective === base`. HP rows follow the same shape; current HP is the live `effective`, max HP uses base + layers if max-HP buffs ever exist.

Either way, the per-combatant buff list shape is the same (already pre-grouped/stacked server-side so the client doesn't need to duplicate `team.js`'s grouping logic per battle type):

```json
{
  "buff_code": "atk_down_10",
  "name": "Attack Down",
  "stat_key": "atk",
  "is_debuff": true,
  "modifier_kind": "percent",
  "stack_count": 2,
  "total_effect_label": "-19% ATK",
  "turns_remaining": 2,
  "scope": "turn"
}
```

`scope` is `"turn"` (from `battle_turn_buffs`) or `"time"` (from `entity_buffs`, merged in per the "merge both" decision) so the icon can show a turn-count badge vs. a small clock affordance if we ever want to distinguish them visually; `turns_remaining` is `null` for `scope: "time"` entries.

## Frontend (`combat.js` — already the single shared module for all 3 battle types)

### Buff icon strip (compact, always visible)

- New shared renderer, e.g. `renderCombatBuffIcons(containerEl, buffsArray)`: for each entry, a small square `div.combat-buff-icon` (green border/background tint for buffs, red for debuffs), a short text label derived client-side from `stat_key` + direction (e.g. `ATK▼`, `SPD▲`) — no icon image assets needed, and a stack-count badge (`.combat-buff-stack`, only rendered when `stack_count > 1`, same UX precedent as `team.js`'s expandable stack badge).
- `title` attribute (native tooltip) with the full name + exact `total_effect_label` + turns remaining for hover detail — no need for a custom tooltip component for v1.
- Placement: one small icon row per combatant, near their name/HP bar —
  - `solo_pve`: player row + wild row in the existing battle panel.
  - `pvp`: both animal panels.
  - `party_pve`: each ally row in `.combat-party-actions-list` (same row structure the inactivity-vote countdown was just added to) + the wild row(s).
- Re-render on the existing poll cadence for each battle type (no new timer needed — buffs change at most once per turn/round, which already triggers a re-render).

### Stat inspector panel (on demand, per animal)

- **Trigger:** a small `(i)` button/icon on each animal row in combat (player active animal, wild, PvP opponent, each party ally row, wild row). One panel instance per open animal is enough for v1; opening another animal's `(i)` may stack a second panel or focus/replace — prefer **one panel at a time** (reuse the same draggable shell, swap contents) to avoid clutter on mobile.
- **Panel chrome:** floating overlay (`div.combat-stat-panel`), **draggable** by a title/header bar (same drag pattern as other game panels if one exists; otherwise lightweight pointer-drag on the header only), **closeable** via `×` and optionally Escape. Panel position persists only for the current battle session (in-memory; no localStorage required for v1).
- **Contents:** scrollable list of stats from `combat_stat_sheet` — label, base value (muted when modified), **effective value in bold** when `is_modified`, otherwise a single normal-weight value.
- **Modified stat row:** to the right of the effective value, render the same buff icon component used in the strip (reuse `renderCombatBuffIcons` on a per-stat subset). Tooltip on each icon: buff name, `total_effect_label`, and duration (`N turns` for `scope: "turn"`, clock-style label for `scope: "time"` when present).
- **Data source:** always trust server `combat_stat_sheet` / `effective` — do not recompute layers client-side (keeps panel consistent with `MoveResolver` math).
- **Accessibility:** `(i)` button has `aria-label` / `title` ("Stats"); panel is `role="dialog"` with labelled header including animal nickname/species.

## Migration / rollout order

0. **Prerequisite:** 005_COMBAT_ENGINE.md ships — `MoveResolver.php`/`TurnQueue.php`/`CombatSession.php` exist and all three battle types (`solo_pve`, `pvp`, `party_pve`) run through them as thin controllers. Do not start this module's implementation before that lands.
1. Schema: `ability_effects` table + `battle_turn_buffs.id_ability_effect` column + 3 new `buff_definitions` rows + `ability_effects` seed rows for the 5 legacy abilities.
2. `BUFFS` class additions (`rollAbilityEffects`, `grantAbilityEffect`, `computeEffectiveStats`, `fetchCombatDisplay`, `fetchCombatStatSheet`).
3. Wire the three call sites into `MoveResolver.php` / `TurnQueue.php` / `CombatSession.php` (single implementation, exercised by all battle types at once).
4. Meta/API exposure (unified shape if 005 provides one; otherwise the three-envelope fallback) — include `combat_stat_sheet` per combatant alongside `active_combat_buffs`.
5. Frontend buff icon strip in `combat.js` + CSS.
6. Frontend stat inspector panel (`(i)` trigger, draggable/closeable panel, modified-stat highlighting) in `combat.js` + CSS.
7. Bump `ANIMASTER_ASSET_VERSION`.
8. (Follow-up, out of scope here) `dev_species.php` admin UI still edits the deprecated `abilities.effect`/`effect_chance` fields — needs a follow-up pass to manage `ability_effects` rows instead so new abilities don't get authored against the dead legacy field.

## Open questions to resolve during implementation (not blocking the plan)

- Exact tick point for "once per full round/turn" inside `TurnQueue.php` — needs to land after every combatant that acts this round has resolved, but before the response is returned, without double-ticking on replay/resume requests (`restarting_old_battle`-style flows).
- Whether `fetchCombatDisplay`'s stacking/grouping logic should be a PHP port of `team.js`'s `groupBuffStacks` (server pre-groups, thinner client) or left for the client to group raw layers — leaning PHP-side grouping per the shape above, so `combat.js` doesn't need its own copy alongside `team.js`'s.
- Whether wild animals can ever be **granted** a buff by their own moves (schema already allows it via `entity_type='wild'`); no current wild ability targets `self`, so this is inert until wild AI gets self-buff moves — no extra work needed now, just confirming the schema already supports it.
- If 005's `CombatSession` ends up modeling dungeon/raid combatants (multiple wilds, or multiple enemy sides) before this module lands, extend `battle_turn_buffs.battle_type`'s enum accordingly in `01_alters_structure.sql` at that time.

## Follow-up: 005c schema unification

[005c_full_combat_unification.md](005c_full_combat_unification.md) will:

- Scope `battle_turn_buffs` by `id_battle` FK only (drop redundant `battle_type` column after cutover).
- Key buff layers to `battle_participants.entity_type` / `id_entity` (unchanged conceptually).
- Expose buffs via unified `battle_meta.combatants[]` — retire separate `solo_pve_meta` / `pvp_meta` / `party_pve_meta` keys.

## Test plan (once implemented)

- [ ] Migrated ability (e.g. `Growl`) grants a real `battle_turn_buffs` row instead of mutating persisted stats; the combatant's base stat snapshot is unchanged after the debuff lands.
- [ ] Effective damage/accuracy math reflects the debuff immediately (live-calculated), and reverts to exact pre-debuff values the turn it expires (`turns_remaining` reaches 0) — no residual drift.
- [ ] Stacking the same debuff twice (e.g. hit by `Growl` twice) shows `stack_count: 2` and the correct combined percentage, matching `applyLayersToStats`' multiplicative-per-layer math.
- [ ] Icon strip renders correctly for `solo_pve`, `pvp`, and `party_pve`, for both buffs (green) and debuffs (red), including merged time-based buffs from `entity_buffs`.
- [ ] `(i)` stat panel opens for each animal row, is draggable and closeable, and lists all combat stats from `combat_stat_sheet`.
- [ ] Modified stats show **bold effective value**, base value when different, buff icon(s) with tooltip (name + duration + effect label); unmodified stats show a single normal value.
- [ ] Stat panel values match damage/accuracy outcomes after a debuff lands and after it expires (same numbers `MoveResolver` used).
- [ ] `battle_turn_buffs` rows are cleared on battle end for all three battle types (win/loss/flee/disband).
- [ ] A debuff granted to a `party_pve` member who then leaves/faints doesn't crash meta building (graceful skip, consistent with existing `flg_active`/fainted handling).
