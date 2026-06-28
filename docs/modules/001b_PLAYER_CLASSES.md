# Module 001b — Player classes

**Priority:** After PvP 1v1 foundation, **before** Party PvE (002)  
**Depends on:** Character creation, NPC/quest framework, inventory, solo combat  
**Unlocks:** Party composition value, crafting loop, element extraction, advanced PvP/PvE roles

---

## Design pillars

1. **Starter identity matters** — Nerd vs Stud is a lifestyle choice, not a combat stat stick.
2. **Specialization via quests** — No free respec at level 25/50; class change is a **story milestone** with item collection.
3. **Battle + field split** — Every advanced class has at least one **in-battle** tool and one **out-of-battle** tool.
4. **Party diversity** — Ideal party mixes field roles (scout, buffer, crafter) and battle roles (healer, aggro, damage amp).
5. **Element theme** — Classes tie into extracting, refining, and using **elemental resources** from the world and wild animals.

> **DB naming note:** Table `classes` today = **animal taxonomy** (Mammals, Birds…). Player classes need a new schema, e.g. `player_classes`, `player_class_abilities`, `users_ig.id_player_class`.

---

## Class tree (canonical)

```
                    ┌─────── (start) ──────┐
                    │                      │
              Lv 1  │                      │  Lv 1
                    │                      │
               ┌────┴────┐            ┌────┴────┐
               │  STUD   │            │  NERD   │
               └────┬────┘            └────┬────┘
                    │                      │
              Lv 25 │                      │ Lv 25
         ┌──────────┼──────────┐   ┌───────┴────────┐
         │                     │   │                │
    TRAINER              ADVENTURER  ARTIST        SCIENTIST
         │                     │   │                │
    Lv 50│                Lv 50│   │ Lv 50      Lv 50│
    ┌────┴────┐          ┌─────┴─────┐ ┌───┴───┐  ┌───┴────┐
    │         │          │           │ │       │  │        │
 STRIKER  GUARDIAN    SCOUT    EXPLORER MUSICIAN STYLIST  VET  PHARMACIST
(offense) (defense)  (speed)  (loot)  (buffer) (craft) (heal) (potions)
```

### Starter classes (level 1)

| Class | Fantasy | Exploration angle | Combat angle |
|-------|---------|-------------------|--------------|
| **Nerd** | Knowledge, precision, creativity | Decode zones, analyze elements, craft with formulas | Setup, debuff, technical buffs |
| **Stud** | Action, presence, physical mastery | Cover ground, intimidate, lead encounters | Direct buffs to animals, aggro, tempo |

**Starter passives (examples)**

| | Nerd | Stud |
|---|------|------|
| Field | +5% element shard identify rate on wild inspect | +5% move speed outside battle |
| Battle | +5% accuracy on team when nerd acts first in round | +5% crit on first animal attack each battle |

---

## Tier 2 — Level 25 (quest-gated)

Requirements: **level ≥ 25**, correct starter class, **class quest chain** complete, **quest items** turned in via NPC conversation (see [Quest integration](#quest-integration)).

### Nerd → Artist

| | Detail |
|---|--------|
| Role | Creative support — shapes environment and team morale |
| Field | Place **decorative beacons** (party buff zone, 5 min), sketch wild for bonus inspect info |
| Battle | **Inspire** — one ally animal +10% atk OR def for 2 turns (player action, 3-turn cooldown) |
| Element | Converts mixed shards → **pure element** at reduced cost |

### Nerd → Scientist

| | Detail |
|---|--------|
| Role | Analysis, medicine prep, element research |
| Field | **Sample** wild corpses / freed animals for extra data (unlocks bestiary entries) |
| Battle | **Analyze** — reveal enemy resistances + next AI tendency for 1 turn |
| Element | **Extract** element nodes in world (unique interactable spawns) |

### Stud → Trainer

| | Detail |
|---|--------|
| Role | Animal combat coach |
| Field | **Train** at camp: small EXP drip to one animal (daily cap) |
| Battle | **Command** — next animal move +15% power OR priority +1 (2-turn CD) |
| Element | Minor bonus when team shares one element type |

### Stud → Adventurer

| | Detail |
|---|--------|
| Role | Pathfinder, loot, risk-taker |
| Field | **Trailblazer** — reveal nearby spawn points / hidden nodes on minimap pulse |
| Battle | **Adrenaline** — when 2+ animals fainted, remaining gain +spd for 2 turns |
| Element | Bonus drop chance on **first kill of the day** per species |

---

## Tier 3 — Level 50 (quest-gated)

Requirements: **level ≥ 50**, parent tier-2 class, **advanced quest** + **rare quest items** (often zone-specific + crafted components).

### Artist branch

#### Musician (buffer)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Harmony** | All ally animals +5% spd, +5% acc for 3 turns (party-wide, once/battle) |
| Battle | **Dissonance** | Single enemy −10% atk for 2 turns |
| Field | **Busk** | At safe zone: party regen +10% HP over 30s channel |
| Field | **Setlist** | Choose party-wide buff theme before dungeon (fire/water/etc. +3% res) |
| Element | Amplifies **Music Shard** → team status resist +5% (consumable crafted by Stylist) |

#### Stylist (crafter)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Flashy Distraction** | Wild target −15% acc 1 turn (once/battle) |
| Field | **Tailor** | Craft **Trinkets** (held items): +stat small, cosmetic slot |
| Field | **Dye & Tag** | Mark animals for trade/clan visibility; craft party banners |
| Element | Refine **Style Dust** from any shard type (generic crafting mat) |

### Scientist branch

#### Veterinarian (healer)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Field Medicine** | Heal ally animal 15% max HP (player action, 4-turn CD) |
| Battle | **Triage** | Revive fainted animal to 20% HP once per battle |
| Field | **Clinic** | Out-of-combat full heal one animal (long channel, reagent cost) |
| Field | **Vaccinate** | 24h buff: −10% status ailment chance in PvE |
| Element | Uses **Bio Essence** from water/life-tagged shards |

#### Pharmacist (alchemist)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Throw Potion** | Use inventory potion without consuming animal turn (2/battle) |
| Battle | **Steroid Shot** | +25% atk, −10% def for 3 turns (self-target animal, reagent) |
| Field | **Brew** | Craft potions, **tranquilizers** (reduce wild aggro range), steroids, antidotes |
| Field | **Prescription** | Party members get +1 potion effectiveness |
| Element | Master of **chemical extraction** from fire/poison shards |

### Trainer branch

#### Striker *(name alternatives: **Martialist**, **Offensive Handler**, **Tactician**)*

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Focus Strike** | Next ally physical attack +30% power, +10% crit |
| Battle | **Weak Point** | Mark enemy: takes +15% from combo second hit |
| Field | **Spar** | Sparring mini-game: +small atk EV to one animal |
| Field | **War Paint** | Pre-battle: team +5% atk for first 2 turns |
| Element | Channels **strike crystals** (fire/electric) into attack buffs |

#### Guardian *(name alternatives: **Warden**, **Bulwark**, **Defensive Handler**)*

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Provoke** | Force wild to target guardian's lead animal 2 turns |
| Battle | **Body Block** | Ally takes hit; guardian's animal absorbs 50% redirected once |
| Field | **Escort** | Party members in follow range −wild aggro radius |
| Field | **Fortify** | Deploy temporary **ward** (party def +5% in radius) |
| Element | Uses **guard plates** from earth/defensive shards |

### Adventurer branch

#### Scout (fast & agile)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Hit & Run** | Ally attack then +20% eva 1 turn |
| Battle | **Recon** | See full wild team stats at battle start (multi-wild future) |
| Field | +15% move speed; **Stealth** reduces encounter rate 10% |
| Field | **Mark Target** | Party sees chosen wild on map 60s |
| Element | Light/air shards → speed consumables |

#### Explorer (extra extraction)

| Type | Ability | Effect |
|------|---------|--------|
| Battle | **Salvage** | After win: +1 roll on rare drop table |
| Battle | **Trap Sense** | Party immune to ambush extras (dungeon traps) |
| Field | **Deep Harvest** | +1 common material from wild wins (stack with party) |
| Field | **Cartograph** | Unlock zone chest / node locations after quest |
| Element | Best **multi-element extraction** yield (+10%) |

---

## Suggested naming adjustments

| Current (working) | Suggested EN | IT / PT note | Why |
|-------------------|--------------|--------------|-----|
| Attacker | **Striker** or **Martialist** | Evita “attacker” generico | Clear PvE/PvP role without sounding PK-only |
| Defender | **Guardian** or **Warden** | Tank fantasy | Implies protect party, not just stats |
| Pharmacyst | **Pharmacist** | Ortografia EN | Standard spelling |
| Scientist → Vet/Pharm | Keep split | Medico vs alchimista | Clean healer vs crafter split |
| Nerd / Stud | Optional rename **Savant / Athlete** | Localizzazione | Less slang; keep nerd/stud if tone is humorous |

**Internal codes (stable in DB):** `nerd`, `stud`, `artist`, `scientist`, `trainer`, `adventurer`, `musician`, `stylist`, `veterinarian`, `pharmacist`, `striker`, `guardian`, `scout`, `explorer`.

---

## Additional class ideas (future / optional)

These fit the theme without bloating launch. Use as **level 75 capstones** or **seasonal specializations**.

| Concept | Parent | Hook |
|---------|--------|------|
| **Curator** | Artist + Scientist (rare dual-quest) | Museum NPC; catalog all species; passive XP to team |
| **Ranger** | Scout + Guardian | Zone control; tame (non-catch) wild temporary ally 1 battle |
| **Professor** | Scientist (alt 50?) | Teaches party **class skill** +1 level in raids |
| **Influencer** | Stylist + Musician | Clan-wide buffs; social/emote abilities |
| **Chemist** | Pharmacist upgrade | Element **fusion** — combine 2 shards → rare |
| **Handler** | Trainer umbrella title | Cosmetic title if both striker + guardian quest lines done on alts |

**Not recommended:** Pay-to-win combat classes, real-time-only skills, or classes that skip turn order.

---

## Party synergy matrix (why before 002 Party PvE)

Ideal 4-player party example:

| Slot | Class | Brings to party PvE |
|------|-------|---------------------|
| 1 | Guardian | Provoke wild focus, protect glass cannons |
| 2 | Musician | Party spd/acc buff for turn order |
| 3 | Veterinarian | Sustain long fights |
| 4 | Explorer | Extra loot / materials for quest items |

**Composition rules (soft bonuses, optional v1):**

- No duplicate **tier-3** class → +3% EXP (encourages diversity)
- At least one **field crafter** (Stylist / Pharmacist) → party can repair/reagent mid-dungeon (future)

Party PvE doc: [002_PARTY_PVE.md](002_PARTY_PVE.md) should assume **player class passives** apply to whole party when in range.

---

## Player actions in turn-based combat

Today only **animals** act. Class system adds **player phase**:

```
Each round:
  1. (Optional) Players with off-CD class skills submit ONE command each — parallel, simultaneous
  2. Resolve player skills (buffs/debuffs/heals)
  3. Animal turn queue by speed (existing engine)
  4. Wild AI
```

Rules:

- Max **1 player class skill per player per N rounds** (class-dependent CD)
- Player skills never deal direct damage > 10% wild max HP (keep animals central)
- PvP: same system; Pharmacist potion throw respects inventory

Implement in [005_COMBAT_ENGINE.md](005_COMBAT_ENGINE.md) refactor ideally; stub hooks in 001b.

---

## Quest integration

Uses existing systems: `quests`, `conversation_requirements`, `requirements` (item type + min count), `conversation_consequences` (`[set class]` new type).

### Level 25 — example: Nerd → Scientist

| Step | Detail |
|------|--------|
| Quest giver | NPC “Lab Director” (visible if `player_class = nerd`, `level >= 25`) |
| Prerequisites | Completed starter zone arc; possess items |
| Required quest items | 5× **Element Shard (Fire)**, 3× **Field Notes**, 1× **Microscope Trinket** (crafted) |
| Conversation | `conversation_requirements`: item reqs + `user lvl` 25–999 |
| Turn-in dialog | Option “I’m ready to specialize” → consequence `[set player_class]` → `scientist` |
| Reward | Title, class skill unlock row, notification |

### Level 50 — example: Scientist → Pharmacist

| Required items (sample) | Source |
|-------------------------|--------|
| 10× Purified Water | Explorer zones / shop |
| 5× Venom Sac | Wild drops (snake species) |
| 1× Master Formula | Dungeon chest (future) or long chain |
| 3× Rare Herb | Zone 1001 nodes (Scientist extract ability) |

### Class-exclusive conversations

Filter with new requirement type: `player class` (`id_ref` = class id, min/max = exact tier).

```
requirement_type: 'player class'
id_ref: id_player_class_scientist
min: 1, max: 1
```

Only scientists see Pharmacist vs Veterinarian mentor NPCs.

---

## Element extraction loop (world)

| Class | Extraction role |
|-------|-----------------|
| Scientist | Primary: harvest **element nodes** |
| Explorer | Bonus yield from wild **post-battle** |
| Pharmacist | Convert shards → consumables |
| Stylist | Convert shards → trinkets / mats |
| Musician | Convert shards → resonance buffs |

**Wild animal → elements:** On victory, base drop + class modifiers. Explorer **Salvage** adds roll; Scientist **Sample** adds bestiary progress toward craft unlocks.

---

## Database sketch

```sql
player_classes (
  id_player_class PK,
  code VARCHAR(50) UNIQUE,     -- 'nerd', 'striker', ...
  name, name_it, name_pt,
  parent_id_player_class NULL, -- tree
  unlock_level INT,            -- 1, 25, 50
  starter_branch ENUM('nerd','stud') NULL
);

users_ig ADD id_player_class INT NULL;

player_class_abilities (
  id PK,
  id_player_class,
  code VARCHAR(50),
  name, description,
  use_context ENUM('battle','field','both'),
  cooldown_turns INT,
  cooldown_seconds INT,        -- field
  effect_json TEXT,            -- unified effect DSL
  unlock_level INT DEFAULT 1
);

user_player_class_abilities (
  id_user_ig, id_ability, flg_unlocked
);
```

New consequence type: `[set player_class]` with `id_ref` = target class.

---

## Implementation phases

### Phase A — Data & character create (3–4 days) ✅ shipped (v1)

- `player_classes` seed tree; `users_ig.id_player_class` set at create (Nerd / Stud)
- Cosmetic `character_type` avatar separate from gameplay class
- Profile + HUD show class name

**Deploy:** run new tail of `01_alters_structure.sql` and `02_insert_static_data.sql` on each environment.

### Phase B — Quest-gated promotion (4–5 days) ✅ shipped (v1)

- Requirement type `player class`; consequence `[set player_class]`
- Nerd → Scientist path: NPC **Lab Director** (zone 1000), quest items 6–8, conversation **10**
- Item turn-in via `params_json.consume_items`; client refreshes HUD/self after promotion

**Deploy:** run new tail of `02_insert_static_data.sql` (Phase B block after self-panel i18n).

### Phase C — Abilities v1 (5–7 days)
- 2 abilities per tier-3 class (1 battle, 1 field)
- Combat hook: player skill phase before animal turns
- Field: interact endpoints + buff UI

### Phase D — Party passives (2–3 days)
- Party-wide auras when grouped (Musician, Guardian ward)
- Document in 002 Party PvE

---

## Test plan

- [ ] Create character as Nerd → only Artist/Scientist quests visible at 25  
- [ ] Turn in without items → dialog blocked  
- [ ] Promote to Scientist → Pharmacist quest visible at 50, Veterinarian separate NPC  
- [ ] Class skill usable in solo PvE; CD respected  
- [ ] Two Strikers in party → no soft EXP penalty if feature enabled  
- [ ] Static export includes `player_classes` seed  

---

## Related modules

| Module | Link |
|--------|------|
| PvP 1v1 | [001_PVP_1v1.md](001_PVP_1v1.md) — class skills in duels |
| Party PvE | [002_PARTY_PVE.md](002_PARTY_PVE.md) — composition value |
| Quests | [004_QUESTS.md](004_QUESTS.md) — tracker + chains |
| Combat engine | [005_COMBAT_ENGINE.md](005_COMBAT_ENGINE.md) — player skill phase |
| Economy / craft | [010_ECONOMY.md](010_ECONOMY.md) — Pharmacist, Stylist recipes |

---

## Open decisions (for you)

1. **Striker vs Martialist vs Offensive Handler** — pick one EN name before SQL seed  
2. **Single advanced class per account** vs **one per character** (recommend: per character)  
3. **Respec** — only via rare quest item, or never? (recommend: never for identity)  
4. **Player cosmetic `character_type`** — keep 50+ avatars separate from class (recommend: yes)
