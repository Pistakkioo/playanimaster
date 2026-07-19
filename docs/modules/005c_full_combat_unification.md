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
| Party PvE | Up to `costanti.party_max_members` player animals (currently 7) | 1–M wilds (scaled actions) |
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
- Party PvE (N players, 1 wild): N rows side `A` + 1 row side `B` where N ≤ `costanti.party_max_members` (currently 7); wild still rolls N actions per alive A — unchanged rule, implemented in slot builder.
- Solo 1v3 wild pack: 1 row `A` + 3 rows `B`.
- Party vs party: up to N rows `A` + up to N rows `B` (same party max), all `player_animal`.

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

**Future (005d):** multi-fighter control — player may bring multiple animals; wild packs/minions as multiple `B` rows. Not in Phase 5. See [Deferred: multi-fighter control (005d)](#deferred-multi-fighter-control-005d).

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
| Participants (**Phase 5 v1**) | **One** `player_animal` per online in-range member (lead animal only), same snapshot pattern as party PvE |
| Planning | `simultaneous_confirm` per side independently, then resolve when **both sides** meet quorum OR flee policy triggers |
| Win | All fighters on one alliance faint |

**Phase 5 v1 explicitly does not include:** equalize slots for the smaller party, a pre-battle extra-animal pick window, or multiple actions per player. Those require [005d multi-fighter control](#deferred-multi-fighter-control-005d) first; after 005d, party-vs-party can retrofit equalization on top of this scaffold.

Implement scaffold + 2-party test hook after party PvE port; full PK polish in 008.

---

## Deferred: multi-fighter control (005d)

**Status:** Deferred — design locked here so Phase 5 does not invent a conflicting model.  
**Depends on:** 005c Phases 0–4 (unified tables + `battle_meta` envelope).  
**Unlocks:** party-vs-party fighter equalization, solo PvE packs/minions, any mode where one human controls multiple on-field animals.

### Why not in Phase 5

Schema already allows multiple `battle_participants` per `id_user_ig` (no unique on user), and `battle_round_choices` is unique per `(id_battle, round, id_battle_participant)`. Runtime today is still **one action per user**:

| Piece | Schema today | Runtime today |
|-------|--------------|---------------|
| Multiple participants per user | Allowed | Never inserted; lookups `LIMIT 1` |
| Choice uniqueness | Per `id_battle_participant` | Stage/confirm/meta keyed by `id_user_ig` |
| TurnQueue / quorum | Could be per fighter | Per user (`partyByUser`, confirm count vs alive users) |
| Switch | — | Overwrites the one fight row (not a second fighter) |

Shipping 2-alive slots + pick window + multi-actions inside Phase 5 would force a large rekey of planning / `TurnQueue` / `Permissions` / `combat.js` first. Do that as **005d**, then extend party-vs-party.

### Intended rules (when built)

1. **Per-player baseline** — When multi-fighter is enabled for a mode, a player may have up to the first **N alive** animals from their personal team in the fight. Intended `costanti` key: `battle_max_animals_per_player` (default **2**). No SQL seed in 005c — add with 005d.
2. **Party vs party equalization** — Smaller party gets **extra animal slots** so fighter counts match the larger party (`|A_fighters| = |B_fighters|`), filled from smaller-party members’ teams (still respecting the per-player max).
3. **Pre-battle pick window** — Before round-1 planning, the smaller party chooses which extra animals fill those slots. Owners of multiple selected animals get **multiple actions per round** (one staged/confirmed choice per alive `id_battle_participant` they own).
4. **Cross-mode reuse** — Same model for future solo PvE packs/minions: player may bring multiple animals to equalize vs multi-wild; wild side uses multiple `B` participants.
5. **Prerequisite before enabling** — Rekey planning/resolution from `id_user_ig` → `id_battle_participant` (`TurnQueue.php`, `Permissions.php`, party/pvp stage-confirm paths, `combat.js`). Clarify `flg_active` vs `slot_label`: today `flg_active` is overloaded with “left the fight”; multi-fighter needs a clear split (`active` / `bench` on-field vs departed).

### Out of scope for 005d itself

- Ranked / spectators / loot UI.
- Implementing Phase 5 scaffold (stays in 005c Phase 5 checklist below, still 1 animal/member until 005d lands).

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
- [ ] Regression: full-size party (up to `costanti.party_max_members`, currently 7), flee, inactivity vote, mid-fight leave.
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

### Phase 5 — Party vs party scaffold (1 animal per member)

**Scope:** simple scaffold only. Each online in-range member contributes **one** lead `player_animal`. No equalize slots, no pre-battle pick window, no multi-actions per player. Equalization / multi-fighter is **blocked on 005d** (see above).

- [x] `battle_type = party_vs_party` start validator (both party leaders; eligible members in join radius of challenger position).
- [x] Snapshot: one participant per member (lead animal), sides from `id_party_a` / `id_party_b`.
- [x] Two-party confirm resolution in `Permissions` (each side meets quorum, then resolve; either leader flee).
- [x] Dev/smoke start + poll endpoints: `party_vs_party_start_battle.php`, `party_vs_party_get_battle_info.php`; client routes `party_vs_party` through the party confirm UI; party poll auto-joins.
- [x] Explicitly leave equalization / 2-alive / pick window to 005d.

**Smoke (console, two party leaders in range):**

```js
AnimasterApi.startPartyVsPartyBattle(player, idTargetLeaderUserIg)
  .then(function (info) { AnimasterCombat.start(player, info); });
```

Members on both sides join via existing `party_pve_battle` poll payload (also carries `party_vs_party`).

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

High-level summary (kept for quick scan). The **Live session checklist** below is the authoritative step-by-step plan for a multiplayer online session covering Phases 1–4. Phase 5 party-vs-party scaffold is implemented (1 animal/member); live 2v2 smoke still needed.

- [ ] Smoke: create battle, snapshot participants, resolve one round, end battle.
- [ ] Solo PvE: win, lose, flee, switch, item, exp, drops, quest `onWildDefeated`.
- [ ] Party PvE: 2-member and full-size (up to `costanti.party_max_members`, currently 7), confirm reset on change, leader flee, inactivity vote, leave/kick, reward split.
- [ ] PvP duel: two clients, speed order, flee, forfeit on disconnect.
- [ ] Party vs party: two parties, 2v2 smoke (1 animal/member). *(Phase 5 scaffold ready — run console start; no equalize until 005d)*
- [ ] Buffs: time buffs at start, turn buffs mid-fight, clear on end; `max_hp` not persisted buffed to `animals`.
- [ ] Time-buff tier no-stack: same `stat_key` — ≥ tier replaces, lower tier rejected; battle buffs still stack.
- [ ] `wild_animals` lock/release on start/end/flee.
- [ ] `users_ig.flg_battling` cleared on all end paths.
- [ ] Client: all three modes poll/act via unified `battle_meta` (no legacy `*_meta` keys).

---

## Live session checklist (Phases 1–4)

Use this during an online session with friends. Tick boxes as you go. When something fails, record: **step id**, **`id_battle`**, who acted / in what order, browser console error, and whether `flg_battling` / wild lock stuck.

### Suggested schedule (~2–2.5 h)

| When | Who | Block |
|------|-----|-------|
| 0:00 | Host alone | 0 Pre-session → A Solo → B Buffs |
| 0:30 | 2 players | C PvP |
| 1:05 | 2 players | D Party (2-player) |
| 1:35 | 3+ players (up to `party_max_members`, currently 7) | D Party (full-size + inactivity) |
| 2:10 | Anyone | E Meta + F Hygiene / retest fails |

### Accounts & prep (everyone)

Before the call:

1. Each tester has a working login and is on the **same environment** (same deploy).
2. Each has **≥ 2 animals** in the party/bench (needed for switch tests).
3. Each has at least **one healing item** (or any usable battle item) in inventory.
4. Host prepares one animal that can apply a **time-based entity buff** with a known `stat_key` and tier (for Block B), if available.
5. Optional: one quest that completes / progresses on wild kill (`onWildDefeated`) for A.7.

---

### 0 — Pre-session (host, ~10 min)

- [ ] **0.1 Deploy & cache**
  1. Confirm latest code is deployed (Phases 1–4 + buff tier fix).
  2. Note `ANIMASTER_ASSET_VERSION` in `character_config.php`.
  3. Hard-refresh the client (Ctrl+F5 / empty cache) so `package` JS is the new build.
  4. Open DevTools → Network; keep it ready for meta checks later.

- [ ] **0.2 Schema sanity**
  1. Confirm tables exist: `battles`, `battle_participants`, `battle_round_choices`, `battle_moves`, `battle_inactivity_votes`.
  2. After the first fight of each mode (below), verify **new** rows land in those tables — not in `battles_solo_pve*`, `battles_pvp*`, `battles_party_pve*`.

- [ ] **0.3 Roles for the session**
  - **P1** = host / party leader / often challenger.
  - **P2** = PvP opponent + first party member.
  - **P3+** = extra party members for full-size tests (up to `costanti.party_max_members`, currently 7).
  - Shared notepad: column per step → Pass / Fail / `id_battle` / notes.

---

### A — Solo PvE (1 tester, ~25–35 min)

Do these on **P1** before or while others log in. Stay in an overworld zone with wild animals.

- [ ] **A.1 Start a solo battle**
  1. Walk near a wild animal and start combat (normal engage flow).
  2. Confirm the combat UI opens (your animal + wild visible).
  3. In Network, open the battle-info / poll response: envelope must contain **`battle_meta`** (not `solo_pve_meta`).
  4. Note `id_battle` from `battle_meta` (or DB).
  5. **Pass:** battle opens, poll succeeds, combatants render, no console parse errors.

- [ ] **A.2 Win a battle**
  1. Start (or continue) a solo fight against a wild you can beat.
  2. Use damaging abilities each turn until the wild reaches 0 HP.
  3. Watch the end-of-battle flow (victory screen / auto-close / return to overworld).
  4. Check you received expected **exp** and any **drops** for that wild.
  5. **Pass:** wild dies, rewards apply, you are back in overworld and can move; not stuck in combat UI.

- [ ] **A.3 Lose a battle**
  1. Start a fight you will lose (weak animal vs strong wild, or tank hits without healing).
  2. Let your active animal reach 0 HP (and any required lose condition).
  3. Confirm defeat end screen / exit.
  4. **Pass:** battle ends as defeat; overworld usable; you are not permanently `flg_battling`.

- [ ] **A.4 Flee / escape**
  1. Start a new solo fight.
  2. Choose **Escape / Flee** (not finish the kill).
  3. Confirm battle ends as fled.
  4. Try to walk away and optionally re-engage the same or another wild.
  5. **Pass:** flee succeeds (or fails with clear feedback if RNG fail — then retry until flee wins once); after success, wild is free again and you can move.

- [ ] **A.5 Switch animal mid-fight**
  1. Start a fight with ≥ 2 animals available.
  2. On your turn, **Switch** to the other animal.
  3. Confirm the new animal is active (sprite/name/HP).
  4. Perform one ability with the new animal.
  5. **Pass:** switch applies, next action uses the new animal, HP/stats look correct, no desync.

- [ ] **A.6 Use an item mid-fight**
  1. Start a fight; optionally take some damage first so a heal is visible.
  2. Use a healing (or battle) **item** from the combat item action.
  3. Confirm inventory count decreases and the effect applies (HP up, etc.).
  4. Continue one more turn to prove the fight did not soft-lock.
  5. **Pass:** item consumed, effect visible, combat continues normally.

- [ ] **A.7 Quest kill hook (if you have a kill quest)**
  1. Accept / have active a quest that advances on defeating a wild.
  2. Win a solo fight against a qualifying wild (**A.2**).
  3. Check quest progress / completion UI or DB.
  4. **Pass:** `onWildDefeated` (or equivalent) progressed. *Skip if no such quest content is available — mark N/A.*

- [ ] **A.8 Wild buff icons visible**
  1. Start a fight where the wild (or you) applies a buff that should show icons on the wild.
  2. Prefer a wild ability / status that grants a battle buff, or any path that previously broke wild icons.
  3. Inspect the wild’s buff icon row in the combat UI.
  4. Optionally check `battle_moves.meta_json` that the buff was applied (not silently skipped).
  5. **Pass:** icons appear on the wild when a buff is active (regression for the `id_entity` / wild participant bug).

- [ ] **A.9 Reconnect mid-solo fight**
  1. Start a solo fight; play at least one turn.
  2. Refresh the page (F5) or re-login.
  3. Confirm you re-enter the **same** ongoing battle (same `id_battle`).
  4. Finish with win or flee.
  5. **Pass:** reconnect restores combat; you can still act and end cleanly.

- [ ] **A.10 Solo end hygiene (DB)**
  After any of A.2 / A.3 / A.4, check:
  1. `users_ig.flg_battling` cleared for P1.
  2. That wild’s `wild_animals.id_battle` cleared / unlocked.
  3. `animals.current_hp` updated; **buffed** `max_hp` was **not** persisted onto the animal row.
  4. **Pass:** all three clean.

---

### B — Buff stacking rules (1 tester, ~15 min)

Can run inside Solo (or Party). Needs abilities / items that apply **time-based entity buffs** with the same `stat_key` and different tiers.

- [ ] **B.1 Higher or equal tier replaces**
  1. Enter combat (or apply overworld time buff if your flow allows before combat).
  2. Apply buff **X** with `stat_key = S` and tier **T**.
  3. Apply another buff with the **same** `stat_key = S` and tier **≥ T**.
  4. **Pass:** old buff is gone / replaced; UI icons and combat_stat_sheet show the new buff only for that `stat_key`.

- [ ] **B.2 Lower tier does not stick**
  1. Apply buff with `stat_key = S` and tier **T_high**.
  2. Apply another with same `stat_key` and tier **T_low < T_high**.
  3. **Pass:** high-tier buff remains; low-tier does not replace it (no icon swap to the weaker one).

- [ ] **B.3 Battle buffs still stack**
  1. In the same fight, apply two **battle** (turn) buffs that are supposed to stack under existing rules (different keys, or whatever your design allows).
  2. **Pass:** both are present; the new time-buff tier rule did **not** break battle-buff stacking.

- [ ] **B.4 Buffs clear when battle ends**
  1. Enter a fight with active battle and/or time buffs showing.
  2. Win, lose, or flee.
  3. Check overworld / next fight: battle-scoped buffs are gone; any persistent time buffs behave per design.
  4. **Pass:** no ghost combat buffs; stats not permanently inflated.

- [ ] **B.5 Stat panel matches buffs**
  1. Mid-fight with at least one buff active, open the combatant stat sheet / panel.
  2. Compare displayed ATK/DEF/etc. to expected modified values.
  3. **Pass:** panel reflects buffs; no crash / empty panel.

---

### C — PvP duel (2 testers: P1 + P2, ~25–35 min)

Both players in range / same zone. P1 challenges P2 (or reverse — note who is challenger = side A).

- [ ] **C.1 Accept a duel**
  1. P1 sends a duel request to P2.
  2. P2 accepts.
  3. Both clients open the duel UI with both animals visible.
  4. Network: response has **`battle_meta`** (not `pvp_meta`).
  5. Note `id_battle`.
  6. **Pass:** both in the same battle; combatants correct; no console errors.

- [ ] **C.2 Simultaneous submit (happy path)**
  1. Each player selects an ability.
  2. Each **submits / locks** their choice.
  3. Observe: round should **not** resolve until both have locked (unless flee interrupts).
  4. When both locked, round resolves once; HP and move log update on **both** clients.
  5. **Pass:** single resolve; both UIs show the same outcome; next round unlocks for new choices.

- [ ] **C.3 Speed order**
  1. Arrange animals so one has clearly higher SPD than the other (swap animals between duels if needed).
  2. Both submit damaging abilities the same round.
  3. Read the move log / animation order.
  4. **Pass:** higher SPD acts first (ties: whatever your engine’s tie-break is — just be consistent).

- [ ] **C.4 Multi-round duel**
  1. Play at least **3 full rounds** of submit → resolve without ending.
  2. Watch for stuck “waiting for opponent”, double-locks, or skipped rounds.
  3. **Pass:** each round can be planned and resolved; poll stays healthy.

- [ ] **C.5 Flee from PvP**
  1. Start or continue a duel.
  2. One player chooses **Flee**.
  3. Confirm battle ends for **both**.
  4. Both try to move in overworld / start a new duel.
  5. **Pass:** both cleared from battling; no one stuck on the duel screen.

- [ ] **C.6 Win by KO**
  1. Play until one side’s animal hits 0 HP.
  2. Confirm winner/loser presentation.
  3. Both return to overworld.
  4. **Pass:** correct winner; both `flg_battling` clear; can duel again.

- [ ] **C.7 Disconnect / forfeit path**
  1. Start a duel; both lock at least one round so the battle is clearly ongoing.
  2. **P2** closes the tab, kills the browser, or drops network long enough to hit your inactivity/forfeit rule.
  3. **P1** keeps the client open and waits / polls.
  4. **Pass:** battle ends via forfeit (or documented timeout behavior); P1 is not stuck forever. *If you have no timeout yet, mark Fail/N/A with note — still try a hard refresh reconnect as C.8.*

- [ ] **C.8 Reconnect mid-duel**
  1. Start a duel; complete one resolved round.
  2. **P2** refreshes / re-logins.
  3. Confirm P2 rejoins the same `id_battle` and can submit when it is their planning window.
  4. Finish the duel (KO or flee).
  5. **Pass:** reconnect works; no duplicate battles created.

- [ ] **C.9 PvP DB spot-check**
  1. Pick one finished duel `id_battle`.
  2. Confirm rows in `battle_participants` (2 player animals, sides A/B), `battle_round_choices` (for submitted rounds), `battle_moves` (append-only log).
  3. Confirm **no** new rows in legacy `battles_pvp` / `pvp_turn_choices` for that fight.
  4. **Pass:** unified tables only.

---

### D — Party PvE (2 then full-size testers, ~40–60 min)

Party size cap is `costanti.party_max_members` (currently **7**). Test with 2 first, then as many friends as you have up to that max.

#### D0 — Party setup

- [ ] **D0.1 Form party**
  1. P1 creates/leads a party; P2 joins (later P3+ up to the max).
  2. All members travel to the **same zone** and stand in range of the **same wild**.
  3. Confirm the roster UI shows `{count} / {max}` with **max = 7** (or whatever `party_max_members` is set to).
  4. **Pass:** party roster shows everyone online/in range as required by your start rules.

#### D — 2-player party (P1 + P2)

- [ ] **D.1 Start party PvE**
  1. P1 (leader) starts the party battle against the wild.
  2. Confirm **both** P1 and P2 enter combat UI.
  3. Confirm allies list shows both player animals; wild on the opposing side.
  4. Network: **`battle_meta`** present (not `party_pve_meta`).
  5. Note `id_battle`.
  6. **Pass:** both clients in the same battle with correct combatants.

- [ ] **D.2 Stage + confirm (happy path)**
  1. Each member picks an ability (stage).
  2. Each **confirms**.
  3. Round must wait until confirm quorum is met, then resolve **once**.
  4. Both clients see the same HP / move log.
  5. **Pass:** one resolution after all required confirms; wild also acted (see D.4).

- [ ] **D.3 Change action before full confirm**
  1. Both stage an ability.
  2. Before everyone has confirmed, **one player changes** their staged ability.
  3. Observe confirm state: confirms should reset / require re-confirm per your party rules.
  4. Both confirm again; let the round resolve.
  5. **Pass:** the **new** ability is what executes (not the discarded one); no silent wrong action.

- [ ] **D.4 Wild action scaling (2 alive allies)**
  1. With 2 living party animals, resolve a full ability round.
  2. Count wild actions in the move log for that round.
  3. **Pass:** wild action count matches the rule “alive alliance-A fighters” (here: 2), unless a faint mid-round reduces it — note what you saw.

- [ ] **D.5 Win + reward split**
  1. Defeat the wild with the 2-player party.
  2. Check each member gets the expected reward split / exp share.
  3. All members return to overworld.
  4. **Pass:** battle ends for everyone; rewards look correct; nobody stuck battling.

- [ ] **D.6 Leader flee**
  1. Start a new party fight.
  2. **P1 (leader)** uses flee / abandon (whatever the leader-only flee control is).
  3. Confirm battle ends for **all** members.
  4. Confirm wild unlocked; all can move.
  5. **Pass:** full party exit; no orphan battle for P2.

- [ ] **D.7 Non-leader leave / disconnect mid-fight**
  1. Start a party fight; resolve at least one round.
  2. **P2** leaves the party **or** closes the client (pick the path you support).
  3. Observe P1’s client: fight continues, cancels, or shows a clear state — per design.
  4. End or finish whatever remains; check both accounts can play afterward.
  5. **Pass:** no permanent stuck `flg_battling` on either account; no zombie `battles` row left `flg_status='O'` with nobody in it (or document if intentional).

- [ ] **D.8 Kick mid-battle (if supported)**
  1. Start party fight with P2.
  2. P1 kicks P2 from the party (if UI allows mid-battle).
  3. **Pass:** kicked player exits cleanly; remaining members either continue or end per rules. *Mark N/A if kick is disabled during battle.*

#### D — Full-size party (3+ players, up to `party_max_members`)

- [ ] **D.9 Full-size start**
  1. Party with as many members as available (ideally the full max) in range of one wild.
  2. Leader starts battle.
  3. **Pass:** all members enter; UI lists all allies; wild present; `battle_meta` OK on each client.

- [ ] **D.10 Confirm quorum with N**
  1. Have all but one player confirm; **one delays**.
  2. Confirm the round does **not** resolve early.
  3. Last player confirms → round resolves once.
  4. **Pass:** quorum respected; single resolve.

- [ ] **D.11 Inactivity vote**
  1. Start a 3+ player party fight.
  2. One member goes AFK (does not stage/confirm).
  3. Others open / trigger the **inactivity vote** UI against that member.
  4. Cast votes per your rules (majority / all, etc.).
  5. **Pass:** vote applies the documented outcome (skip turn / kick / auto-action / end); battle does not soft-lock forever.

- [ ] **D.12 Wild multi-action with 3+ alive**
  1. Keep 3+ allies alive for a full round.
  2. Count wild actions in the log.
  3. **Pass:** matches alive-A count rule.

- [ ] **D.13 Reconnect mid-party fight**
  1. During an ongoing party battle, one member refreshes / re-logins.
  2. They should rejoin the same `id_battle` with correct staged/confirmed state.
  3. Finish the fight.
  4. **Pass:** reconnect OK; no duplicate party battle.

- [ ] **D.14 Party DB spot-check**
  1. After a finished party fight, inspect `battles` (`battle_type=party_pve`), `battle_participants`, `battle_round_choices`, `battle_moves`, and if used `battle_inactivity_votes`.
  2. Confirm no new writes to `battles_party_pve*`.
  3. **Pass:** unified tables only.

---

### E — Client `battle_meta` unification (all modes, ~10 min)

Do these while running A / C / D (DevTools → Network). No separate gameplay beyond inspecting JSON.

- [ ] **E.1 Solo envelope** — solo poll/action JSON has `battle_meta`, **not** `solo_pve_meta`.
- [ ] **E.2 PvP envelope** — PvP poll JSON has `battle_meta`, **not** `pvp_meta`.
- [ ] **E.3 Party envelope** — party poll JSON has `battle_meta`, **not** `party_pve_meta`.
- [ ] **E.4 Combat UI after asset bump** — no blank combat screen; no console errors about missing meta fields after hard refresh.
- [ ] **E.5 Animate + end in each mode** — move log animates; battle end UI works once each for solo, PvP, and party.

---

### F — Stuck-state / hygiene sweep (end of session, ~10 min)

Run after the messiest tests (flee, disconnect, lose, inactivity).

- [ ] **F.1 `flg_battling`** — for every account that fought: `users_ig.flg_battling` is cleared.
- [ ] **F.2 Wild locks** — no wild left with a stale `id_battle` pointing at a finished/cancelled battle.
- [ ] **F.3 Can fight again** — each account can start: a new solo fight, a new duel, and (if in party) a new party fight without “already battling” errors.
- [ ] **F.4 HP persistence** — animals are not stuck with permanently buffed `max_hp` on the `animals` row.
- [ ] **F.5 Console clean** — no repeating poll/parse error spam on an idle overworld client.

---

### Session done criteria (Phases 1–4 live-tested)

Treat Phases 1–4 as **live-tested** when all of the following are Pass (or N/A with reason):

1. **Solo:** A.1–A.6, A.8–A.10 (A.7 if quest exists).
2. **Buffs:** B.1–B.4.
3. **PvP:** C.1–C.6, C.8–C.9 (C.7 if timeout/forfeit exists).
4. **Party:** D.1–D.6 plus ideally D.9–D.11 (full-size up to `party_max_members`); D.14.
5. **Client meta:** E.1–E.5.
6. **Hygiene:** F.1–F.5.

**Out of scope for this session:** Phase 6 legacy table drops, Phase 7 dungeons, overworld tiles sidequest, multi-fighter / equalize (005d). Phase 5 scaffold can be smoked with the console helper above.

---

## Risks & mitigations


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
- `party_vs_party` scaffold battle startable in dev (**1 animal per member**; equalization is 005d).
- [006_DUNGEONS.md](006_DUNGEONS.md) can reference `battles` without parallel combat tables.

---

## Related doc updates

See also updated: [005_COMBAT_ENGINE.md](005_COMBAT_ENGINE.md), [001_PVP_1v1.md](001_PVP_1v1.md), [002b_PARTY_PVE.md](002b_PARTY_PVE.md), [006_DUNGEONS.md](006_DUNGEONS.md), [012_RAID_BOSSES.md](012_RAID_BOSSES.md), [008_PK_PVP_ZONES.md](008_PK_PVP_ZONES.md), [MODULES.md](../MODULES.md). Multi-fighter / equalization design lives in this file under [Deferred: multi-fighter control (005d)](#deferred-multi-fighter-control-005d).
