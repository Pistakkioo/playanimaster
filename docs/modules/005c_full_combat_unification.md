# Module 05c — Full combat unification

**Status:** Planned (blocking dungeons / raids / party-vs-party)  
**Depends on:** [005_COMBAT_ENGINE.md](005_COMBAT_ENGINE.md) (MoveResolver, TurnQueue, CombatSession, CombatantSnapshot — done), [005b_COMBAT_BUFF_VISIBILITY.md](005b_COMBAT_BUFF_VISIBILITY.md) (done)  
**Unlocks:** [006_DUNGEONS.md](006_DUNGEONS.md), [012_RAID_BOSSES.md](012_RAID_BOSSES.md), [008_PK_PVP_ZONES.md](008_PK_PVP_ZONES.md) (party-scale PK), future party-vs-party wars  
**Replaces:** parallel `battles_solo_pve*`, `battles_pvp*`, `battles_party_pve*` families

---

## Goal

One **definitive** combat persistence model:

- A single `battles` row per fight, keyed by `battle_type`.
- Any number of **player-animal** and **wild** (or scripted) participants on two alliances.
- Live state on `battle_participants` — move rows are **audit log only**, never the source of truth.
- One PHP orchestration path (`BattleService` + planning strategies) behind the existing shared engine (`MoveResolver`, `TurnQueue`, `CombatSession`).

Historical solo/PvP rows may be dropped; only one tester on live — no migration of old move chains required.

### Non-goals (this module)

- Dungeon encounter scripting content (006) — only the battle shell they plug into.
- Raid boss phase AI (012) — only participant/slot model that supports it.
- Ranked ladder, spectators, loot rolling UI.
- Real-time combat.

### Explicitly enabled after this module

| Scenario | Alliance A | Alliance B |
|----------|------------|------------|
| Solo PvE (today) | 1 player animal | 1 wild |
| Solo PvE (future) | 1–N player animals | 1–M wilds |
| Party PvE | Up to 4 player animals (party) | 1–M wilds (scaled actions) |
| PvP duel | 1 active animal per duelist | 1 active animal per duelist |
| Party vs party | Party A animals | Party B animals |
| Dungeon trash wave | Party animals | Scripted wild team |
| Raid | Many player animals | Boss part entities |

---

## Why now

1. **Engine is unified; schema is not** — damage, buffs, speed order, and `combatants[]` meta already share `private_functions/combat/`, but three table families and two state strategies (move-row chain vs participants) duplicate bugs and block features.
2. **`p_a_*` / `w_a_*` columns do not scale** — hard-coded two-fighter move rows cannot represent multi-wild solo, party-vs-party, or raids.
3. **Party PvE proved the model** — `battles_party_pve_participants` is the template; generalize it.
4. **Clean window** — no production player base; deleting legacy tables is acceptable.

---

## Design principles

1. **Participants are authoritative** — HP, stats, faint, active slot live on `battle_participants`. Overworld `animals` table stores canonical base `max_hp` only (see recent HP persistence fix).
2. **Moves are append-only** — `battle_moves` records what happened each execution slot; never read back to reconstruct state.
3. **Planning is pluggable** — `battle_type` + `planning_mode` select quorum rules (`Permissions` strategies); resolution always uses `TurnQueue` + `MoveResolver`.
4. **Two alliances, N fighters each** — `side` = `A` | `B` (aka alliance). Party PvE maps party → `A`, wild(s) → `B`. Party vs party maps party A → `A`, party B → `B`. PvP duel maps challenger → `A`, target → `B`.
5. **One battle id everywhere** — `battle_turn_buffs`, `wild_animals.id_battle`, user `flg_battling` context, and client poll all reference `battles.id_battle`.
6. **Thin mode entrypoints** — `solo_pve_start_battle.php`, `party_pve_start_battle.php`, `pvp_accept_duel.php` become validators + `BattleService::start()` calls; no per-mode move includes.

---

## Schema (definitive)

Append to `01_alters_structure.sql` (and mirror `CREATE TABLE` in `00_tables.sql` for greenfield). After cutover, **drop** legacy battle tables (see Retired tables).

### `battles`

```sql
battles (
  id_battle              INT(11) NOT NULL AUTO_INCREMENT,
  battle_type            VARCHAR(32) NOT NULL,
  -- solo_pve | party_pve | pvp_duel | party_vs_party | dungeon | raid | pk_zone
  planning_mode          VARCHAR(24) NOT NULL,
  -- instant | simultaneous_submit | simultaneous_confirm
  flg_status             CHAR(1) NOT NULL DEFAULT 'O',  -- O=ongoing, F=finished, X=cancelled
  current_round          INT(11) NOT NULL DEFAULT 0,
  -- Semantics: last RESOLVED round (party model). Client plans at current_round + 1.
  -- Exception: pvp_duel may treat current_round as open planning round during transition;
  -- normalize in BattleService so client always receives planning_round explicitly in meta.
  id_zone                INT(11) DEFAULT NULL,
  id_user_ig_initiator   INT(11) DEFAULT NULL,
  id_party_a             INT(11) DEFAULT NULL,
  id_party_b             INT(11) DEFAULT NULL,
  id_duel_request        INT(11) DEFAULT NULL,
  id_winner_alliance     CHAR(1) DEFAULT NULL,          -- 'A' | 'B' | NULL
  end_reason             VARCHAR(50) DEFAULT NULL,
  -- win | defeat | fled | forfeit | cancelled | knockout | ...
  dt_round_started       DATETIME DEFAULT NULL,
  dt_created             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  dt_finished            TIMESTAMP NULL DEFAULT NULL,
  dt_m                   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  context_json           JSON DEFAULT NULL,
  -- Mode-specific extras: dungeon_run_id, encounter_id, pk_flag, reward_split, etc.
  PRIMARY KEY (id_battle),
  KEY idx_battles_type_status (battle_type, flg_status),
  KEY idx_battles_party_a (id_party_a, flg_status),
  KEY idx_battles_party_b (id_party_b, flg_status),
  KEY idx_battles_zone (id_zone, flg_status)
);
```

**`battle_type` values (v1 implement + reserve):**

| Value | Description |
|-------|-------------|
| `solo_pve` | Single player vs overworld/scripted wilds |
| `party_pve` | Party vs wild(s) |
| `pvp_duel` | Consent duel, 1v1 humans |
| `party_vs_party` | Two parties, no wilds |
| `dungeon` | Instanced encounter (006) |
| `raid` | Large coordinated boss (012) |
| `pk_zone` | Non-consent PvP (008), same engine as `party_vs_party` with `pk_flag` in context |

**`planning_mode`:**

| Mode | Used by | Resolve when |
|------|---------|--------------|
| `instant` | `solo_pve` (v1) | Each HTTP action executes immediately (no choices table) |
| `simultaneous_submit` | `pvp_duel` | All required humans submitted, or anyone flees |
| `simultaneous_confirm` | `party_pve`, `party_vs_party`, `dungeon`, `raid` | Quorum per `Permissions` (all confirmed, or leader flee, etc.) |

### `battle_participants`

Generalization of `battles_party_pve_participants`. One row per fighter in the battle.

```sql
battle_participants (
  id_battle_participant  INT(11) NOT NULL AUTO_INCREMENT,
  id_battle              INT(11) NOT NULL,
  side                   CHAR(1) NOT NULL,             -- 'A' | 'B'
  participant_kind       VARCHAR(20) NOT NULL,
  -- player_animal | wild | scripted
  id_user_ig             INT(11) DEFAULT NULL,          -- owner (player_animal)
  id_animal              INT(11) DEFAULT NULL,          -- animals.id_animal when player_animal
  id_wild_animal         INT(11) DEFAULT NULL,          -- overworld wild lock when wild
  id_species             INT(11) DEFAULT NULL,
  id_element             INT(11) DEFAULT NULL,
  entity_type            VARCHAR(16) NOT NULL,
  -- animal | wild | user_ig  (for battle_turn_buffs / MoveResolver)
  id_entity              INT(11) NOT NULL,
  -- id_animal, id_wild_animal, or id_user_ig depending on entity_type
  team_position          TINYINT UNSIGNED DEFAULT NULL,
  slot_label             VARCHAR(32) DEFAULT NULL,
  -- active | bench | boss_part — extensibility for switch/raid parts
  flg_active             CHAR(1) NOT NULL DEFAULT 'S',
  flg_fainted            CHAR(1) NOT NULL DEFAULT 'N',
  current_hp             INT(11) NOT NULL,
  max_hp                 INT(11) NOT NULL,             -- battle snapshot (buffed allowed in-fight)
  atk, def, matk, mdef   INT(11) NOT NULL,
  acc, eva, cr           INT(11) NOT NULL,
  spd                    INT(11) NOT NULL,
  lvl                    INT(11) NOT NULL,
  nickname               VARCHAR(100) DEFAULT NULL,
  species_name           VARCHAR(100) DEFAULT NULL,
  experience             INT(11) DEFAULT 0,            -- player_animal exp snapshot for rewards
  dt_c                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  dt_m                   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_battle_participant),
  KEY idx_bp_battle_side (id_battle, side, flg_active),
  KEY idx_bp_battle_entity (id_battle, entity_type, id_entity),
  KEY idx_bp_user (id_user_ig, id_battle),
  CONSTRAINT fk_bp_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle)
);
```

**Participant rules:**

- **Player animal:** `participant_kind = player_animal`, `entity_type = animal`, `id_entity = id_animal`, `id_user_ig` set.
- **Wild:** `participant_kind = wild`, `entity_type = wild`, `id_entity = id_wild_animal` (or internal wild instance id). Overworld row locked via `wild_animals.id_battle`.
- **Scripted (dungeon/raid):** `participant_kind = scripted`, `entity_type = wild`, `id_entity` = negative synthetic id or `dungeon_spawn_id` (TBD in 006); no `id_wild_animal`.

**Multi-fighter examples:**

- Solo 1v1: 1 row side `A` + 1 row side `B`.
- Party PvE (4 players, 1 wild): 4 rows side `A` + 1 row side `B` (wild still rolls N actions per alive A — unchanged rule, implemented in slot builder).
- Solo 1v3 wild pack: 1 row `A` + 3 rows `B`.
- Party vs party: up to 4 rows `A` + up to 4 rows `B`, all `player_animal`.

### `battle_round_choices`

Unified planning storage (replaces `pvp_turn_choices` + `battles_party_pve_turn_choices`).

```sql
battle_round_choices (
  id_battle_round_choice INT(11) NOT NULL AUTO_INCREMENT,
  id_battle              INT(11) NOT NULL,
  round                  INT(11) NOT NULL,
  id_user_ig             INT(11) NOT NULL,
  id_battle_participant  INT(11) NOT NULL,             -- acting fighter
  action_type            VARCHAR(20) NOT NULL,
  -- ability | switch | item | flee
  action_id              INT(11) NOT NULL DEFAULT 0,
  id_item_type_selected  INT(11) DEFAULT NULL,
  flg_confirmed          CHAR(1) NOT NULL DEFAULT 'N',
  dt_c                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  dt_m                   TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_battle_round_choice),
  UNIQUE KEY uq_brc_battle_round_actor (id_battle, round, id_battle_participant),
  KEY idx_brc_battle_round (id_battle, round),
  CONSTRAINT fk_brc_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle),
  CONSTRAINT fk_brc_participant FOREIGN KEY (id_battle_participant) REFERENCES battle_participants (id_battle_participant)
);
```

For `simultaneous_submit` (PvP), `flg_confirmed` is set `S` on submit (submit = lock). For `simultaneous_confirm`, staging sets `N`, confirm sets `S`.

### `battle_moves`

Append-only execution log. **No `p_a_*` / `w_a_*` columns.**

```sql
battle_moves (
  id_battle_move         INT(11) NOT NULL AUTO_INCREMENT,
  id_battle              INT(11) NOT NULL,
  round                  INT(11) NOT NULL,
  order_in_turn          INT(11) NOT NULL,
  id_actor_participant   INT(11) NOT NULL,
  id_target_participant  INT(11) DEFAULT NULL,
  id_user_ig_actor       INT(11) DEFAULT NULL,
  move_type              VARCHAR(100) NOT NULL,
  id_rif                 INT(11) DEFAULT NULL,
  move_speed             DECIMAL(10,2) DEFAULT NULL,
  move_description       VARCHAR(255) DEFAULT NULL,
  move_hit               CHAR(1) DEFAULT NULL,
  actor_hp_after         INT(11) DEFAULT NULL,
  target_hp_after        INT(11) DEFAULT NULL,
  resulting_battle_status VARCHAR(16) DEFAULT NULL,
  -- ongoing | win | defeat | fled | pvp_end | ...
  meta_json              JSON DEFAULT NULL,
  dt_c                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_battle_move),
  KEY idx_bm_battle_round (id_battle, round, order_in_turn),
  CONSTRAINT fk_bm_battle FOREIGN KEY (id_battle) REFERENCES battles (id_battle)
);
```

Client combat log rebuilds from `battle_moves` + participant display names loaded once in meta.

### `battle_inactivity_votes`

Port of `battles_party_pve_inactivity_votes` (unchanged semantics, generic battle id).

```sql
battle_inactivity_votes (
  id_battle_inactivity_vote INT(11) NOT NULL AUTO_INCREMENT,
  id_battle              INT(11) NOT NULL,
  round                  INT(11) NOT NULL,
  id_user_ig_target      INT(11) NOT NULL,
  id_user_ig_voter       INT(11) NOT NULL,
  vote_choice            CHAR(1) NOT NULL DEFAULT 'Y',
  dt_c                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_battle_inactivity_vote),
  UNIQUE KEY uq_biv (id_battle, round, id_user_ig_target, id_user_ig_voter)
);
```

### `battle_turn_buffs` (alter)

Simplify scope key:

```sql
-- Replace (battle_type, id_battle) with id_battle FK only.
-- battle_type column DROPPED after code cutover; id_battle is authoritative.
ALTER TABLE battle_turn_buffs
  ADD COLUMN id_battle_unified INT(11) NULL AFTER id_battle;
-- Migration backfill maps old id_battle per battle_type; then swap PK index.
```

During transition, dual-write; after cutover, drop `battle_type` column.

### Overworld locks

| Table | Change |
|-------|--------|
| `wild_animals` | Keep `id_battle` (INT). Drop `battle_type` column — battle type lives on `battles.battle_type`. |
| `users_ig` | `flg_battling` + store `id_battle` (nullable INT) instead of mode-specific ids. |
| `animals` | On battle end, sync `current_hp` via `BUFFS::persistAnimalHpAfterBattle()` only — never persist buffed `max_hp`. |

---

## PHP architecture

### New layout

```
private_functions/combat/
  BattleRepository.php       -- CRUD battles, participants, choices, moves
  BattleService.php          -- start / plan / resolve / end / flee / switch
  BattleParticipantFactory.php -- snapshot from animal, wild, scripted template
  Planning/
    PlanningStrategy.php     -- interface
    InstantPlanning.php      -- solo: no choices table
    SubmitPlanning.php       -- pvp_duel
    ConfirmPlanning.php      -- party_pve, party_vs_party
  BattleMetaBuilder.php      -- client battle_meta (replaces *\_meta blobs)
  CombatSession.php          -- keep; delegates to BattleService for round advance
  CombatantSnapshot.php      -- from battle_participants only
  TurnQueue.php              -- slot builders take participant ids, not p_a/w_a
  MoveResolver.php             -- unchanged math
  Permissions.php              -- parameterized by planning_mode + battle_type
  AiWild.php                   -- target pick among alliance A participants
```

### Retire / shrink

| Current | After 005c |
|---------|------------|
| `private_functions/party_pve.php` (~2700 lines) | `PartyPveBattleMode.php` (~start rules, reward split, inactivity vote hooks) + `BattleService` |
| `private_functions/pvp.php` (~1900 lines) | `PvpDuelBattleMode.php` + `BattleService` |
| `public_html/funzioni/battle_solo_pve/*.php` | Deleted — logic in `SoloPveBattleMode` + `BattleService` |
| `SoloPveController.php` | `BattleController::handleRequest(battle_type)` or thin wrapper |

### Single poll/action endpoint (target)

```
public_html/funzioni/open_actions/battle_poll.php
public_html/funzioni/open_actions/battle_action.php
```

Legacy endpoints (`solo_pve_get_battle_info.php`, `pvp_get_battle_info.php`, `party_pve_get_battle_info.php`) remain as **aliases** during transition, returning the same `battle_meta` shape.

### `BattleService` core flow

```
start(BattleStartRequest)
  → validate proximity / party / duel / wild lock
  → insert battles row
  → BattleParticipantFactory::snapshotAll(...)
  → lock wilds / set users flg_battling
  → optional: seed round 0 start move row

plan(id_battle, id_user_ig, action)
  → PlanningStrategy::stage / confirm / submit
  → Permissions::shouldResolveRound?
  → if yes: resolveRound()

resolveRound(id_battle)
  → load participants + choices
  → TurnQueue::buildSlots(participants, choices, battle_type rules)
  → foreach slot: MoveResolver / switch / item / flee handlers
  → update battle_participants hp/faint
  → append battle_moves rows
  → CombatSession::tickRoundBuffs(id_battle)
  → if ended: endBattle() else advance current_round, clear choices

endBattle(id_battle, reason)
  → rewards / drops / exp (mode delegates)
  → BUFFS::clearBattleTurnBuffs
  → release wild locks
  → persist animal HP (base max_hp only)
  → clear user flg_battling
```

---

## Client contract

### Unified `battle_meta` (replaces three meta keys)

Poll/action responses expose:

```json
{
  "battle_meta": {
    "id_battle": 42,
    "battle_type": "party_pve",
    "planning_mode": "simultaneous_confirm",
    "planning_round": 3,
    "last_resolved_round": 2,
    "flg_status": "O",
    "alliance_a_label": "Party",
    "alliance_b_label": "Wild",
    "combatants": [ /* CombatantSnapshot[] from participants */ ],
    "choices": [ /* staged/confirmed this round */ ],
    "permissions": { "can_flee": true, "flee_requires_leader": true },
    "inactivity_vote": { /* party only */ }
  },
  "moves": "#...#" 
}
```

`AnimasterCombat` reads only `battle_meta` + `moves`. Remove branching on `solo_pve_meta` / `pvp_meta` / `party_pve_meta` after client cutover.

> **As implemented (Phase 4):** the envelope key is unified exactly as above (`battle_meta`
> replaces the three legacy keys, one variable client-side), but the *contents* of
> `battle_meta` were kept mode-flavored rather than normalized into the fully generic
> `planning_round` / `alliance_a_label` / `permissions.can_flee` / `choices[]` shape sketched
> here — each mode's existing PHP `*_build_meta()` still returns its own field names
> (`submitted`/`both_locked` for pvp, `party_allies`/`confirm_required` for party). This was a
> deliberate scope cut to avoid rewriting all of `combat.js`'s per-mode phase machines in one
> pass; revisit when `party_vs_party` (Phase 5) needs a real shared `permissions`/`choices[]`
> shape instead of a fourth ad hoc field set.

### Buff / stat panel

Already uses `combatants[]` + `combat_stat_sheet` — no change except participant ids in meta (`id_battle_participant`).

---

## Mode mapping (behavior parity)

### `solo_pve`

| Today | Unified |
|-------|---------|
| `battles_solo_pve` | `battles` (`battle_type=solo_pve`, `planning_mode=instant`) |
| Move row state chain | `battle_participants` (1 A + 1 B) |
| `p_a_move.php` / `w_a_move.php` | `BattleService::executeInstantAction()` |
| Turn 0 start row | `battle_moves` round 0 + participant snapshots at start |

**Future:** leader brings 2+ animals — multiple `A` participants (only one `flg_active`); wild pack — multiple `B` rows.

### `party_pve`

| Today | Unified |
|-------|---------|
| `battles_party_pve` | `battles` (`party_pve`, `simultaneous_confirm`, `id_party_a`) |
| `battles_party_pve_participants` | `battle_participants` |
| `battles_party_pve_turn_choices` | `battle_round_choices` |
| `animaster_party_pve_resolve_round` | `BattleService::resolveRound()` |

Behavior parity: confirm quorum, leader flee, wild actions = alive A count, inactivity vote, reward split.

### `pvp_duel`

| Today | Unified |
|-------|---------|
| `battles_pvp` + move chain | `battles` (`pvp_duel`, `simultaneous_submit`) |
| `pvp_turn_choices` | `battle_round_choices` |
| `p_a`/`w_a` mapping | Both duelists on `A`/`B` with one active `player_animal` each |

Draft `battles_pvp_participants` from 001 doc is superseded by `battle_participants`.

### `party_vs_party` (new in this module)

| Rule | Detail |
|------|--------|
| Start | Two party leaders consent (reuse duel request pattern or PK in 008) |
| Alliances | `id_party_a`, `id_party_b` on `battles` |
| Participants | Each online in-range member snapshotted like party PvE |
| Planning | `simultaneous_confirm` per side independently, then resolve when **both sides** meet quorum OR flee policy triggers |
| Win | All fighters on one alliance faint |

Implement scaffold + 2-party test hook after party PvE port; full PK polish in 008.

---

## Migration plan (execution order)

Because only one tester, prefer **big-bang cutover** over long dual-write. Optional short dual-write window per phase if needed.

> **Implementation note (adopted approach):** instead of a new `BattleService` +
> `Planning` strategy class hierarchy, the existing procedural mode functions
> (`animaster_party_pve_*`, `animaster_pvp_*`) were ported to call
> `BattleRepository` / `BattleParticipantFactory` directly, keeping their
> external signatures and client-facing response shapes unchanged. `Permissions`
> / `TurnQueue` / `CombatSession` already encapsulate the planning-mode logic, so
> a separate strategy layer was unnecessary. `battle_meta` unification is
> deferred to Phase 4; ported modes still emit the legacy meta keys the client
> reads today.

### Phase 0 — Schema & repositories (no user-visible change) — DONE

- [x] Append `CREATE TABLE` for `battles`, `battle_participants`, `battle_round_choices`, `battle_moves`, `battle_inactivity_votes` to `01_alters_structure.sql`.
- [x] Add greenfield copies to `00_tables.sql`.
- [x] Implement `BattleRepository` + `BattleParticipantFactory` (procedural adapter used instead of a `BattleService`/`Planning` class layer).
- [ ] Document `battle_type` / `planning_mode` enums in `02` if needed for dev tools.

### Phase 1 — Party PvE port (reference mode) — DONE (pending live test)

- [x] Snapshot/start ported (`animaster_party_pve_start` → `BattleRepository::createBattle` + participants).
- [x] Confirm-planning resolution ported (`animaster_party_pve_resolve_round` on unified tables via `Permissions`/`TurnQueue`/`CombatSession`).
- [x] `party_pve.php` reads/writes unified tables; endpoints unchanged (still emit `party_pve_meta`, client contract preserved).
- [ ] Regression: 4-player party, flee, inactivity vote, mid-fight leave.
- [x] No more writes to `battles_party_pve*`.

### Phase 2 — PvP duel port — DONE (pending live test)

- [x] `animaster_pvp_start_battle()` — two `player_animal` participants (side A challenger, side B target).
- [x] Submit-planning resolution ported; live state now derived from `battle_participants` instead of the last move row.
- [x] `pvp.php` + `character_profile.php` reconnect reader → unified tables (client contract preserved).
- [ ] Regression: simultaneous submit, flee, disconnect forfeit.

### Phase 3 — Solo PvE port — DONE (pending live test)

- [x] `animaster_solo_pve_start()` — 1×A (player animal) + 1×B (wild) participants via `BattleRepository`/`BattleParticipantFactory`.
- [x] Instant-resolution turn processing ported to `private_functions/solo_pve.php` (ability, switch, item, escape), reusing generic helpers already ported for pvp_duel/party_pve (animal snapshot, wild snapshot, ability fetch, item/switch validation).
- [x] `SoloPveController` now a thin delegator to `solo_pve.php`; `solo_pve_get_battle_info.php` / `solo_pve_start_battle.php` unchanged externally (still emit `solo_pve_meta`, client contract preserved).
- [x] `login_ig.php` + `character_profile.php` reconnect reader → unified tables.
- [x] Deleted `public_html/funzioni/battle_solo_pve/*.php` (p_a_move, w_a_move, p_move_escape, p_move_switch, p_move_use_item) — no more writes to `battles_solo_pve*`.
- [ ] Regression: abilities, switch, item, escape, rewards, quest kill hooks.

### Phase 4 — Client unification — DONE (pending live test)

- [x] `combat.js` — single `battle_meta` parser: the three module-level meta variables (`pvpMeta`, `partyPveMeta`, `soloPveMeta`) are now one `battleMeta` variable; `getActiveBattleMeta()` no longer branches on `battle.type` to pick a meta blob.
- [x] `api.js` — `getBattleInfo()` now has one `parseBattleMeta()` reading a single `envelope.battle_meta` key for all three endpoints (dropped the three separate per-mode parsers and the dead/unreachable `msg === 'WAITING'` branch — no PHP path ever emitted it).
- [x] Remove legacy meta keys — `solo_pve_meta` / `pvp_meta` / `party_pve_meta` replaced by `battle_meta` in `solo_pve.php`, `pvp_get_battle_info.php`, `party_pve_get_battle_info.php`.
- [x] Bump `ANIMASTER_ASSET_VERSION` (158 → 159).

> **Implementation note:** the per-mode `*_build_meta()` PHP functions were **not** collapsed into one generic shape — `animaster_pvp_build_meta()`, `animaster_party_pve_build_meta()`, and `animaster_solo_pve_build_meta()` still each return their own mode-flavored fields (e.g. `submitted`/`both_locked` for pvp, `party_allies`/`confirm_required` for party). Since a given battle is always exactly one `battle_type`, only one mode's fields are ever populated on `battle_meta` at a time, so there is no key collision — the three shapes simply live under one envelope key instead of three. `combatants[]` (already unified since 005b) is unchanged. Mode-specific state machines in `combat.js` (pvp lock/poll phase, party confirm-quorum, solo instant) were kept as-is and still branch on `battle.type` for phase/rendering choices — only the *meta storage/parsing* was unified, matching the checklist above; fully collapsing the phase machines into a generic `planning_mode`-driven engine was scoped out as a much larger, higher-risk follow-up (see Phase 6 cleanup).
- [ ] Regression: solo/pvp/party still poll, act, animate, and end correctly after the meta merge (no server field renames — should be a no-op for gameplay, but needs a live pass per mode).

### Phase 5 — Party vs party scaffold

- [ ] `battle_type = party_vs_party` start validator.
- [ ] Two-party confirm resolution in `Permissions`.
- [ ] Dev-only start endpoint for smoke test.

### Phase 6 — Cleanup

- [ ] Drop tables: `battles_solo_pve`, `battles_solo_pve_moves`, `storico_battles_solo_pve_moves`, `battles_pvp`, `battles_pvp_moves`, `pvp_turn_choices`, `battles_party_pve`, `battles_party_pve_participants`, `battles_party_pve_moves`, `battles_party_pve_turn_choices`, `battles_party_pve_inactivity_votes`.
- [ ] Drop `battle_turn_buffs.battle_type` column; use `id_battle` FK only.
- [ ] Drop `wild_animals.battle_type` if present.
- [ ] Delete `public_html/funzioni/battle_solo_pve/`.
- [ ] Remove dead code paths from `party_pve.php` / `pvp.php` or delete files after extracting mode classes.

### Phase 7 — Dungeons (006) on unified shell

- [ ] `battle_type = dungeon` encounters create `battles` + scripted `B` participants.
- [ ] `dungeon_runs.id_battle` FK optional per encounter wave.

---

## Retired tables (post cutover)

| Table | Reason |
|-------|--------|
| `battles_solo_pve` | → `battles` |
| `battles_solo_pve_moves` | → `battle_moves` + `battle_participants` |
| `storico_battles_solo_pve_moves` | optional archive job on `battle_moves` later |
| `battles_pvp` | → `battles` |
| `battles_pvp_moves` | → `battle_moves` |
| `pvp_turn_choices` | → `battle_round_choices` |
| `battles_party_pve` | → `battles` |
| `battles_party_pve_participants` | → `battle_participants` |
| `battles_party_pve_moves` | → `battle_moves` |
| `battles_party_pve_turn_choices` | → `battle_round_choices` |
| `battles_party_pve_inactivity_votes` | → `battle_inactivity_votes` |

---

## Testing checklist

- [ ] Smoke: create battle, snapshot participants, resolve one round, end battle.
- [ ] Solo PvE: win, lose, flee, switch, item, exp, drops, quest `onWildDefeated`.
- [ ] Party PvE: 2- and 4-member, confirm reset on change, leader flee, inactivity vote, leave/kick, reward split.
- [ ] PvP duel: two clients, speed order, flee, forfeit on disconnect.
- [ ] Party vs party: two parties, 2v2 smoke.
- [ ] Buffs: time buffs at start, turn buffs mid-fight, clear on end; `max_hp` not persisted buffed to `animals`.
- [ ] `wild_animals` lock/release on start/end/flee.
- [ ] `users_ig.flg_battling` cleared on all end paths.

---

## Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Large refactor breaks all combat | Port party PvE first (most complete); keep legacy endpoints as aliases until Phase 4. |
| Client move log format change | Server emits both meta shapes during Phase 1–3 if needed; prefer one cutover with asset bump. |
| Round number semantics differ (PvP vs party) | `battle_meta.planning_round` and `last_resolved_round` explicit; hide DB `current_round` quirks. |
| Multi-wild action count rules | Encode in `TurnQueue::buildSlots(battle_type)` — don't special-case in schema. |

---

## Done when

- All live combat uses `battles` + `battle_participants` + `battle_moves` (log only).
- Legacy `battles_*` tables dropped from schema and code.
- `AnimasterCombat` consumes single `battle_meta`.
- Party PvE, PvP duel, solo PvE parity tests pass.
- `party_vs_party` scaffold battle startable in dev.
- [006_DUNGEONS.md](006_DUNGEONS.md) can reference `battles` without parallel combat tables.

---

## Related doc updates

See also updated: [005_COMBAT_ENGINE.md](005_COMBAT_ENGINE.md), [001_PVP_1v1.md](001_PVP_1v1.md), [002b_PARTY_PVE.md](002b_PARTY_PVE.md), [006_DUNGEONS.md](006_DUNGEONS.md), [012_RAID_BOSSES.md](012_RAID_BOSSES.md), [008_PK_PVP_ZONES.md](008_PK_PVP_ZONES.md), [MODULES.md](../MODULES.md).
