# Module 04 — Quest system

**Depends on:** NPC/dialog (done), static data tools

## Scope

- Runtime `user_quests` progression (phases)
- Quest accept from NPC dialog consequences (`[start quest]` consequence type)
- Objectives: kill wild species, collect item, talk to NPC, reach level
- Tracker UI + notifications
- Turn-in and rewards via `FUNZIONI::ApplyConsequence`

## Tables

Use existing `quests`, `quest_requirements`, `user_quests`; add `quest_objectives` if needed.

## Done when

One multi-step quest works end-to-end in EN/IT/PT.
