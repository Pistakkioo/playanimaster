# Module 02 — Party PvE

**Priority:** After PvP 1v1 (001) and **Player classes (001b)**; overlaps Party system setup  
**Depends on:** [001b_PLAYER_CLASSES.md](001b_PLAYER_CLASSES.md) (party buffs), minimal **Party system** (roster + leader) — see Phase A below  
**Builds on:** Solo PvE combat, wild spawn, team system

---

## Goal

Up to **4 players** in a party fight **one shared wild encounter** (or later, dungeon encounter). Combat remains **turn-based**: every active animal from every party member enters a **single speed-ordered queue**. Players only control their own animals on their turns.

Non-goals for v1: dungeon instances, loot rolling, cross-zone party.

---

## Player flow

1. Party leader (or any member near wild — policy: **leader only** for v1) initiates fight on a wild animal (same proximity rules as solo).
2. Server locks wild for party; all **in-range party members** with usable animals join the battle automatically (or prompt “Join fight?” — v1: auto-join if in zone + range).
3. Combat UI shows: your team strip + party allies’ HP + wild target.
4. Turn order: global queue sorted by **speed** (all animals from all players + wild).
5. Wild AI uses existing `w_a_move.php` logic.
6. **Player class skills** (from [001b_PLAYER_CLASSES.md](001b_PLAYER_CLASSES.md)): optional pre-round phase — Musician/Guardian/Vet etc. affect whole party.
7. On victory: EXP/gold split, wild despawned, drops (v1: equal split or leader loot table TBD).
8. On defeat: blackout/recover rules per player (same as solo, all party members).

---

## Prerequisite: Phase A — Minimal party (do first if not done)

Even if PvP ships first, Party PvE needs:

```sql
parties (
  id_party PK AI,
  id_user_ig_leader,
  id_zone,              -- optional: last leader zone
  max_members TINYINT DEFAULT 4,
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

`users_ig.id_party` already exists — keep in sync on join/leave.

| Endpoint | Action |
|----------|--------|
| `party_create.php` | Create party, set leader |
| `party_invite.php` | Target player by id |
| `party_respond.php` | Accept/decline invite |
| `party_leave.php` | Leave; disband if leader leaves |
| `party_kick.php` | Leader kicks |
| `poll_party.php` | Roster sync for UI |

Chat `#` channel already routes by `id_party` — works once `id_party` is set.

Full social spec: [003_PARTY_SYSTEM.md](003_PARTY_SYSTEM.md).

---

## Party PvE tables

```sql
battles_party_pve (
  id_battle_party_pve PK AI,
  id_party,
  id_wild_animal,
  id_zone,
  flg_status ENUM('active','finished'),
  dt_created, dt_finished
);

battles_party_pve_participants (
  id PK AI,
  id_battle_party_pve,
  id_user_ig,
  id_animal,
  side ENUM('party','wild'),
  -- hp + stat snapshot, fainted flag
);

battles_party_pve_moves (
  -- like solo_pve_moves + id_user_ig
);
```

Wild row: `wild_animals.battle_type = 'party_pve'`, `id_battle` → party battle id.

---

## Turn queue algorithm

```
1. Load all non-fainted animals from battles_party_pve_participants where side='party'
2. Load wild animal(s) — v1: single wild
3. Sort by spd DESC, tie-break random server-side
4. Current actor = queue[turn_index]
5. If actor.side == 'party': wait for POST from actor.id_user_ig (timeout 45s)
6. If actor.side == 'wild': AI move
7. Apply move, check faint, advance index; new round when index wraps
```

**Fairness:** Faster party comps act more often — intentional (same as solo).

---

## PHP / client

| Component | Notes |
|-----------|-------|
| `private_functions/party.php` | Roster, invites (Phase A) |
| `private_functions/party_pve.php` | Start battle, join validation |
| `open_actions/party_pve_start_battle.php` | Leader + wild id |
| `open_actions/party_pve_get_battle_info.php` | Poll state |
| Move handlers | Extend solo handlers with `party_pve` mode |
| `client/js/party.js` | Roster UI, invite list |
| `client/js/combat.js` | Multi-player turn indicator, ally HP bars |
| `world.js` | Show party members on map (optional tint) |

---

## Loot & EXP (v1 proposal)

| Reward | Rule |
|--------|------|
| EXP | `base_exp * wild_level / party_size` per participating player (all active at start) |
| Gold | Split equally |
| Items | v1: each player rolls independently on `wild_animal_drop_types`; v2: need/greed |

---

## Edge cases

| Case | Handling |
|------|----------|
| Member joins party mid-fight | Blocked until battle ends |
| Member dies / faints | Their animals skip turns; player can still use items on allies if in range of rules |
| Leader flees | v1: whole party flees (wild resets) |
| Member disconnect | AI auto-pass after timeout; animal remains until fainted |
| Wild already in battle | Reject start (same as solo TOO LATE) |
| Party scattered on map | v1: all must be within R of wild; v2: “summon to fight” consumable |

---

## Implementation phases

### Phase A — Party shell (2–3 days)
- Tables + CRUD + `#` chat verified
- Party panel in client (member list, leave)

### Phase B — Start party battle (2–3 days)
- Leader triggers; validate roster + range
- Snapshot all participants; lock wild

### Phase C — Multi-actor turn engine (4–6 days)
- Global speed queue
- Per-user move authorization
- Wild AI integration

### Phase D — Rewards & polish (2 days)
- EXP/gold split, notifications
- language_texts, tests with 2–4 accounts

---

## Test plan

- [ ] Solo player cannot start party battle without party  
- [ ] 2 players, 2 animals each → 4 + 1 wild in queue  
- [ ] Player B cannot move Player A’s animal  
- [ ] Victory grants EXP to both  
- [ ] One flees → party flee policy  
- [ ] `#` chat only to party members during fight  

---

## Path to dungeons (module 06)

Party PvE wild fights prove the **multi-human turn queue**. Dungeons replace `id_wild_animal` with **encounter scripts** (fixed teams, phases, no overworld entity).
