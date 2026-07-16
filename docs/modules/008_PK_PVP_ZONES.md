# Module 08 — PK & PvP zones

**Depends on:** PvP 1v1 (01), [005c_full_combat_unification.md](005c_full_combat_unification.md) (`party_vs_party` / `pk_zone` battle types)

## Scope

- Zone flags: `safe`, `contested`, `lawless`
- PK: attack without duel consent in lawless zones
- Karma / reputation (optional)
- Safe zones: duels only via challenge UI
- Party vs party in lawless zones (multi-fighter per alliance)

## Flow

Lawless: target → attack → consent-less battle using unified engine (`battle_type = pk_zone` or `party_vs_party`, `context_json.pk_flag = true`).

## Balance

Item drop on death (optional), XP loss caps, newbie protection under level N.
