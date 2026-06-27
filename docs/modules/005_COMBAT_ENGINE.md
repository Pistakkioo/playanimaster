# Module 05 — Combat engine refactor

**Depends on:** PvP 1v1 (01), Party PvE (02)

## Goal

Single authoritative turn resolver used by:

- `solo_pve`
- `party_pve`
- `pvp`
- `dungeon`
- `raid`

## Deliverables

```
private_functions/combat/
  CombatSession.php      # load/save session
  TurnQueue.php          # speed order, round advance
  MoveResolver.php       # damage, effects, faint
  AiWild.php             # wild move pick
  Permissions.php        # who may act this turn
```

Migrate `battle_solo_pve/*.php` to thin controllers calling shared classes.

## Done when

Solo PvE regression passes; PvP and party use same resolver with zero duplicated damage formulas.
