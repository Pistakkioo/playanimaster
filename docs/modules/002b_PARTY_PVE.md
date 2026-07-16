# Module 02 — Party PvE

**Status:** Implemented — **schema migrates to unified `battles*` in [005c_full_combat_unification.md](005c_full_combat_unification.md)**  
**Depends on:** minimal **Party system** (roster + leader) — see Phase A below
**Builds on:** Solo PvE combat, wild spawn, team system

---

## Goal

Up to **4 players** in a party fight **one shared wild encounter**. Combat is **round-based, not single-actor-turn-based**: every round, all alive party members plan and confirm their action **simultaneously** ("strategy team play"), then the whole round resolves at once in a single speed-sorted pass. Players only ever control their own animal.

Non-goals for v1: dungeon instances, need/greed loot rolling, cross-zone party, "intelligent" wild targeting (wild currently targets a random alive party member).

---

## Player flow

1. Party leader initiates the fight on a wild animal (same proximity rules as solo). Only the **leader** can start a party fight.
2. At start, the server snapshots every **online, same-zone, in-range** (`ANIMASTER_PARTY_PVE_JOIN_RADIUS` = 50) party member with a usable (HP > 0) lead animal into `battles_party_pve_participants`. Members who are offline, in a different zone, out of range, or have no usable animal are **not** included — they cannot join once the battle has started (no mid-fight join in v1).
3. **Planning phase (every round):** all alive party members simultaneously see the action buttons (Ability / Switch / Item / Flee) and stage a choice via `battles_party_pve_turn_choices`. Staging an action does **not** commit it — a player can change it freely.
4. Each player then taps **Confirm**. The UI shows a `current confirmed / alive party size` progress bar and a "current actions" panel listing every ally's staged action (e.g. "Rex → Tackle") as soon as they've picked one.
5. If a player changes an *already-confirmed* action, every other already-confirmed player's confirmation is reset to force them to review the new board (`animaster_party_pve_unconfirm_others`). Staging a **first-time** choice does not reset anyone.
6. Once every alive party member has confirmed, the round resolves automatically (`animaster_party_pve_resolve_round`):
   - Confirmed party actions + one wild action **per currently-alive party member** are combined into a single list and executed in **speed order** (ties broken by insertion order).
   - Execution stops early if the battle ends mid-round (wild dies, whole party wipes, or the leader's confirmed flee resolves).
7. **Flee is a staged action**, not an instant escape: only the **leader** may stage `action=flee` (a 4th action slot). Once the leader confirms it, the round resolves immediately (bypassing the "everyone confirmed" requirement) and the whole party flees together.
8. On victory: every party member who started the fight and hasn't left the party gets rewarded (including anyone who fainted along the way) — EXP/gold/drops are split evenly across the party. On defeat: same fainting/blackout rules as solo, for every party member.
9. **AFK / offline members:** by default the round waits **indefinitely** for every alive member to confirm — there is no timeout auto-pass. Only an explicit **leave / kick** removes a player from the confirmation requirement (see Edge cases). Parties can opt in to a majority-vote override instead — see **Inactivity vote** below.

---

## Prerequisite: Phase A — Minimal party (done)

```sql
parties (
  id_party PK AI,
  id_user_ig_leader,
  id_zone,              -- optional: last leader zone
  max_members TINYINT DEFAULT 4,
  flg_allow_inactivity_vote CHAR(1) DEFAULT 'N',  -- leader-only opt-in, see Inactivity vote below
  dt_created
);

party_members (
  id_party_member PK AI,
  id_party,
  id_user_ig,
  dt_joined,
  UNIQUE(id_user_ig)    -- one party per character
);
```

`users_ig.id_party` is kept in sync on join/leave.

| Endpoint | Action |
|----------|--------|
| `party_create.php` | Create party, set leader |
| `party_invite.php` | Target player by id |
| `party_respond.php` | Accept/decline invite |
| `party_leave.php` | Leave; disband if leader leaves; auto-releases any in-progress party PvE battle slot (see Edge cases) |
| `party_kick.php` | Leader kicks; same battle-slot release as leave |
| `party_transfer_leader.php` | Hand off leadership |
| `party_poll.php` | Roster sync for UI |

Chat `#` channel routes by `id_party` — works once `id_party` is set.

Full social spec: [003_PARTY_SYSTEM.md](003_PARTY_SYSTEM.md).

---

## Party PvE tables

```sql
battles_party_pve (
  id_battle_party_pve PK AI,
  id_party,
  id_wild_animal,
  id_zone,
  id_user_ig_leader,
  flg_status CHAR(1) DEFAULT 'O',   -- 'O' open, 'F' finished
  current_turn INT DEFAULT 0,        -- last RESOLVED round number
  dt_round_started TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- reset every time a round resolves; drives the inactivity-vote delay
  turn_queue_json, turn_index,       -- legacy columns, unused by the current resolver
  awaiting_user_ig,
  end_reason VARCHAR(50),            -- 'win' | 'defeat' | 'fled'
  dt_created, dt_finished, dt_m
);

battles_party_pve_participants (
  id_battle_party_pve_participant PK AI,
  id_battle_party_pve,
  id_user_ig,            -- NULL for the wild side
  id_animal,
  side CHAR(5),           -- 'party' | 'wild'
  team_position,
  flg_active CHAR(1) DEFAULT 'S',   -- 'N' once the player leaves/is kicked mid-fight
  flg_fainted CHAR(1) DEFAULT 'N',
  current_hp, max_hp,
  atk, def, matk, mdef, acc, eva, cr, spd,
  id_species, id_element, lvl, nickname, species_name
);

battles_party_pve_turn_choices (
  id_battle_party_pve_turn_choice PK AI,
  id_battle_party_pve,
  round,
  id_user_ig,
  action_type VARCHAR(20),          -- 'ability' | 'switch' | 'item' | 'flee'
  action_id,
  id_item_type_selected,
  flg_confirmed CHAR(1) DEFAULT 'N',
  dt_c, dt_m,
  UNIQUE (id_battle_party_pve, round, id_user_ig)
);

battles_party_pve_moves (
  -- like solo_pve_moves, plus id_user_ig_actor + order_in_turn (execution order within the round)
);

battles_party_pve_inactivity_votes (
  id_battle_party_pve_inactivity_vote PK AI,
  id_battle_party_pve,
  round,
  id_user_ig_target,     -- the inactive player being voted on
  id_user_ig_voter,
  vote_choice CHAR(1) DEFAULT 'Y',  -- 'Y' force random action | 'N' keep waiting
  dt_c, dt_m,
  UNIQUE (id_battle_party_pve, round, id_user_ig_target, id_user_ig_voter)
);
```

Wild row: `wild_animals.battle_type = 'party_pve'`, `id_battle` → party battle id. On win the wild row is deleted; on defeat/flee it's released (`id_battle = 0`) back into the world.

`flg_active` is the soft-lock guard: it starts `'S'` for everyone snapshotted at battle start and flips to `'N'` the moment a player leaves the party or is kicked while the battle is still open. Every "who's alive/required to confirm/eligible for rewards" query filters on it.

---

## Round resolution algorithm

```
Planning (client-visible), per round:
1. Each alive+active party member stages an action (ability/switch/item, or flee if leader)
   into battles_party_pve_turn_choices, then confirms it.
2. Any real change to an already-confirmed choice clears every other confirmed flag.
3. After each stage/confirm/leave/kick, check:
     confirmed_count(round) >= count(alive_active_party)  OR  leader's flee choice is confirmed
   -> if true, resolve the round now.

Resolution (animaster_party_pve_resolve_round), once triggered:
1. Build one slot per confirmed party choice, plus one wild slot per currently-alive
   party member (a 1v1 party still fights a wild taking 1 action/round; a full party
   of 4 faces 4 wild actions/round).
2. Sort all slots by spd DESC (insertion order breaks ties).
3. Execute slots in order:
   - party slot: apply ability/switch/item/flee; flee ends the battle immediately ('fled').
   - wild slot: pick a random alive party member as target, apply wild's move.
   - after each party/wild hit, re-check win/defeat; stop the round early if the
     battle just ended.
4. Insert one battles_party_pve_moves row per executed slot (order_in_turn = execution order).
5. Advance battles_party_pve.current_turn to the resolved round number.
```

**Fairness:** faster party comps still act earlier within the round — same principle as solo. Wild scales its action count to party size so a bigger party doesn't trivially out-turn a single wild.

---

## Inactivity vote (opt-in, implemented)

Waiting indefinitely (see player-flow point 9) is the default, but it can soft-lock a round if a teammate goes AFK without leaving the party. Parties can opt in to a **majority vote** that forces a random action for a silent player instead of waiting forever.

**Setting.** `parties.flg_allow_inactivity_vote` — leader-only toggle (`animaster_party_set_inactivity_vote`, exposed via `party_set_settings.php` and a checkbox in the party panel). Persists across every future fight for that party until changed; non-leaders see it read-only.

**Inactivity.** A player is "inactive" for the round purely if they **haven't staged any action yet** (unconfirmed-but-staged does not count as inactive). `battles_party_pve.dt_round_started` is reset to `NOW()` every time a round resolves (and at battle start), and a player only becomes **votable** once `costanti.party_pve_inactivity_vote_delay_seconds` (default 45s) has elapsed since then — computed in SQL (`TIMESTAMPDIFF`) rather than PHP, to stay immune to any PHP-vs-DB timezone drift.

**Voting.**
- Only party members who have **already staged their own action** this round may vote (they're the ones "prompted" client-side), and they can vote on any other still-inactive, alive+active teammate — never on themselves.
- Each voter casts an explicit **Yes** (force it) or **No** (keep waiting) via `type=inactivity_vote` on `party_pve_get_battle_info.php` (`id` = target's `id_user_ig`, `vote_choice` = `Y`/`N`); re-voting just updates their row.
- The tally is only evaluated once **every currently-eligible voter** (alive+active members who've staged, excluding the target) has cast a vote — so the eligible-voter set can still grow while the vote is "pending" (e.g. someone else finishes planning and is expected to weigh in too).
- **Majority wins.** On an exact **tie**, the party leader's own vote decides — but only if the leader is themself an eligible voter (has staged and isn't the target); otherwise a tie defaults to **No**.
- **Cancellation:** the instant the target stages their real action, any in-progress vote against them for the round is deleted (`animaster_party_pve_save_turn_choice` wipes it on first-time staging) and their real choice is used instead.
- **Per-round only:** votes are keyed by `(battle, round, target, voter)`, so a fresh vote is required every single round a player remains inactive; nothing carries over.

**Forced action.** A decisive "Yes" calls `animaster_party_pve_apply_forced_inactivity_action`, which draws a random ability from the **target's own animal's species/level unlock pool** (`animaster_party_pve_fetch_random_wild_ability` — the same helper the wild AI already uses, just pointed at the party member's species/lvl instead of the wild's), auto-stages it, and auto-confirms it. This can complete the round exactly like a normal confirm (the usual `confirmed_count >= alive_party` check runs right after). If the species has no unlocked ability at all (should not happen in practice), forcing is a no-op and the player simply doesn't act that round — same as if nobody had voted.

`party_pve_meta` exposes, per ally: `is_votable`, `vote_yes`, `vote_no`, `my_vote` (`'Y'`/`'N'`/`null`), `can_vote`; plus top-level `allow_inactivity_vote`, `inactivity_vote_delay_seconds`, `seconds_since_round_start`.

**Client countdown.** While a party member is inactive but not yet votable (this includes the player's own "You" row, so they can see how long they have left before teammates can vote against them), the planning panel shows a ticking "vote available in Ns" hint right next to their name (`party_pve.vote_available_in`), computed client-side by extrapolating `seconds_since_round_start` forward from the moment of the last poll response so it counts down every second instead of jumping only every 1.5s poll cycle. It disappears once the delay elapses; for other teammates that's replaced by the actual Yes/No vote row on the next poll.

---

## Loot & EXP (implemented)

| Reward | Rule |
|--------|------|
| EXP | `round(base_exp * wild_level / party_size)` per participating player — includes players who fainted mid-fight, excludes players who left/were kicked before the win |
| Stat XP (atk/def/matk/mdef/hp/acc/eva/cr/spd) | Same `1/party_size` split, via `FUNZIONI::AddExpFromWildAnimal($reward_multiplier)` |
| Gold / item drops | Each eligible player still rolls independently against `wild_animal_drop_types`, but the roll **chance** is scaled by `1/party_size` via `FUNZIONI::AddDropsWildAnimalUser($reward_multiplier)`, so a full-party kill's *expected* total loot matches a solo kill |
| Recipients | Every participant with `side='party'` and `flg_active='S'` at the moment of victory — fainted-but-still-in-party members count, departed members don't |

`$reward_multiplier` defaults to `1.0` in both `FUNZIONI` methods, so solo PvE (which doesn't pass it) is unaffected.

---

## PHP / client

| Component | Notes |
|-----------|-------|
| `private_functions/party.php` | Roster, invites, leave/kick/disband — now also calls `animaster_party_pve_notify_member_departed()` to release any in-progress battle slot; `animaster_party_set_inactivity_vote()` (leader-only setting toggle) |
| `private_functions/party_pve.php` | Start battle, staging/confirm/unconfirm dispatch (`animaster_party_pve_handle_turn_request`), round resolution, rewards, inactivity-vote casting/tally/forced-action helpers |
| `private_functions/f.php` | Shared `FUNZIONI::AddExpFromWildAnimal` / `AddDropsWildAnimalUser`, now accept an optional `$reward_multiplier` |
| `open_actions/party_pve_start_battle.php` | Leader + wild id |
| `open_actions/party_pve_get_battle_info.php` | Single endpoint for: polling, staging an action, confirm, unconfirm, `inactivity_vote` (`id` = target, `vote_choice` = Y/N) — returns move history + `party_pve_meta` |
| `open_actions/party_set_settings.php` | Leader-only party settings toggle (currently just `allow_inactivity_vote`) |
| `client/js/party.js` | Roster UI, invite list, inactivity-vote leader toggle in the party panel |
| `client/js/combat.js` | Planning panel (staged-actions list, confirm progress bar, confirm/unconfirm buttons), ally HP bars, reconfirm-prompt on board changes, inactivity-vote row (live tally + Yes/No buttons) per votable ally |
| `client/game.php` | `#combat-party-pve-planning` panel markup, `#party-settings` toggle markup |
| `world.js` | Out-of-range indicator for party members |

---

## Edge cases

| Case | Handling |
|------|----------|
| Member joins party mid-fight | Not possible — the battle roster is a fixed snapshot taken at start; new joiners fight in the *next* battle |
| Member leaves / is kicked mid-fight | `animaster_party_pve_handle_member_departed()` flips their `flg_active` to `'N'`, immediately re-checks whether the round (now missing one required confirmation) or the whole battle (if nobody active is left) can resolve, and excludes them from rewards. No more indefinite soft-lock |
| Member disconnects (still in party) | No timeout auto-pass by default — the round waits indefinitely for their confirmation. If the party leader enabled the inactivity vote, teammates who already acted can vote (after the configured delay) to force a random valid action for them instead |
| Member faints mid-fight | Excluded from `alive_party` (skips planning/confirming/acting) for the rest of the fight, but **still receives full rewards on victory** |
| Leader flees | Staged as a 4th action, only by the leader; once confirmed it resolves immediately and ends the fight for the whole party (`end_reason = 'fled'`) |
| Wild already in battle | Reject start (`WILD_NOT_FOUND`/`TOO_LATE`, same as solo) |
| Party scattered on map | Members outside `ANIMASTER_PARTY_PVE_JOIN_RADIUS` at battle start simply aren't included in the fight |
| Whole party wiped | `end_reason = 'defeat'` |
| Victory | `end_reason = 'win'` (matches the string solo PvE uses, so the client's shared victory message renders correctly) |

---

## Test plan

- [x] Solo player cannot start party battle without party
- [x] 2 players, 2 animals each → both stage/confirm simultaneously, wild takes 2 actions/round
- [x] Player B cannot move Player A's animal
- [x] Changing an already-confirmed action resets teammates' confirmations; a first-time stage doesn't
- [x] Victory grants split EXP/gold/drops to every participant, including fainted allies
- [x] Leader flee (staged + confirmed) ends the fight for the whole party
- [x] Leaving/kicking a confirmed-waiting member unblocks round resolution instead of soft-locking it; departed member gets no reward
- [x] Inactivity vote: leader-only setting toggle (non-leader rejected); voting requires the voter to have staged first; voting is blocked before the configured delay elapses and against a target who already acted; self-votes and out-of-party targets are rejected
- [x] Inactivity vote: tally only decides once every eligible voter has voted; unanimous Yes/No majorities decide correctly; an exact tie is broken by the leader's own vote when they're an eligible voter, and defaults to No otherwise
- [x] Inactivity vote: the target staging their real action cancels any in-progress vote against them; a decisive Yes auto-stages+confirms a random ability from the target's own species/level pool and can complete the round like a normal confirm
- [ ] `#` chat only to party members during fight (chat visibility during combat implemented; party-only scoping not yet re-verified against this doc)

---

## Path to dungeons (module 06)

Party PvE wild fights prove the **simultaneous multi-human round system**. Dungeons plug into the unified `battles` shell ([005c_full_combat_unification.md](005c_full_combat_unification.md)): replace overworld wild with **scripted `battle_participants`** on alliance B, same staging/confirm/resolve engine.

### Migration note (005c)

Current tables `battles_party_pve*` map directly to:

| Legacy | Unified |
|--------|---------|
| `battles_party_pve` | `battles` (`battle_type=party_pve`) |
| `battles_party_pve_participants` | `battle_participants` (`side` A/B) |
| `battles_party_pve_turn_choices` | `battle_round_choices` |
| `battles_party_pve_moves` | `battle_moves` (log only) |
| `battles_party_pve_inactivity_votes` | `battle_inactivity_votes` |

Behavior in this doc is preserved; only persistence and PHP entrypoints change.
