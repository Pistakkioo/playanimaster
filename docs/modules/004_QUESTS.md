# Module 04 — Quest system

**Status:** ✅ shipped (v1)

**Depends on:** NPC/dialog (done), static data tools

## Scope

- Runtime `user_quests` progression (phases) ✅
- Quest accept from NPC dialog consequences (`[start quest]` consequence type) ✅
- Objectives: kill wild species, collect item, talk to NPC, reach level ✅
- Tracker UI + notifications ✅
- Turn-in and rewards via `CONSEQUENCES::Apply` (dialog `conversation_consequences` loop) ✅

## Tables

- Existing: `quests` (+ i18n name/description columns), `quest_requirements` (unused by v1 — gating uses `conversation_requirements` + new `requirements`/`consequences` rows instead), `user_quests` (+ `flg_completed`, `dt_completed`, unique per user/quest)
- New: `quest_objectives` (N rows per phase; a phase advances only once every row for it is satisfied), `user_quest_objective_progress` (persisted counter for `kill_species` only — other objective types are live-checked against existing state: `items`, `user_conversations`, `users_ig.level`)

## Implementation

- **Engine:** `private_functions/quests.php` (`QUESTS` class) — phase/objective evaluation, `[start quest]` / `[complete quest]` consequences, hooks (`onWildDefeated`, `onConversationFinished`, `onLevelChanged`, `onInventoryChanged`).
- **Requirements:** `quest not started` / `quest started` / `quest ready to turn in` / `quest completed` wired into `FUNZIONI::CheckRequirement` (`private_functions/f.php`). `quest started` is the exact negation of `quest not started` (`QUESTS::isStarted` = `!QUESTS::isNotStarted`). Phase-aware gating: `quest phase` (active quest, `user_quests.phase` equals `min`) and `quest phase completed` (all objectives of phase `min` done — `user_quests.phase` > `min`; also true if the quest is completed). Both use `ref_table = quests`, `id_ref = id_quest`, `min = phase number`. `player class not` is the negation of `player class` (same `PLAYER_CLASS` ref_table / id_ref). `conversation requirements not met` passes when at least one requirement on a **primary** conversation (id_ref = id_conversation) fails — use on fallback dialogs gated with AND alongside audience reqs (stud, quest state, etc.).
- **Hooks:** solo PvE kill (`p_a_move.php`), party PvE kill (`party_pve.php`), conversation finished (`get_conversation_consequences.php`), item drop/obtain (`f.php` / `consequences.php`), level up (`f.php`).
- **API:** `public_html/funzioni/open_actions/get_quests.php` → `AnimasterApi.getQuests`.
- **Frontend:** `public_html/client/js/quests.js` — on-demand Quest Log panel (`Q` key) + persistent HUD tracker widget, both polling-refreshed and event-refreshed (combat end, dialog close).
- **Sample content:** quest 1 "Snake Trouble" (NPC Tamer, id 3) — phase 1 = talk to Assistant + kill 3 Snakes, phase 2 = collect 2 Potions + reach level 6, turn-in rewards a Super Potion. Quest 2 "Become a Trainer" (NPC Master Ki, id 5) — Stud-only class promotion at level 25: multi-phase kill/collect with element-filtered wild drops (`wild_animal_drop_types.id_element`), turn-in applies `[set player_class]` → trainer. Fully localized EN/IT/PT (conversations, dialogues, objectives, UI strings).

## Done when

One multi-step quest works end-to-end in EN/IT/PT. ✅ Verified via a scripted CLI smoke test that ran quest 1 through accept → each objective type → phase advance → ready-to-turn-in → complete + reward, in all three languages, asserting phase transitions, requirement gating, and notification text at each step.

## Follow-ups (not in v1)

- `quest_requirements` (the older prerequisite-quest table) is not consulted yet; `quests.ids_quests_required` is stored but not enforced. Add a `quest prerequisite` requirement check if/when a quest chain needs it.
- No quest abandon/reset action exposed to the player (repeatable quests only reset via a fresh `[start quest]`).
- Objective descriptions are static strings; no dynamic `{count}/{target}` templating beyond what `quests.js` renders client-side.
