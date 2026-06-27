# SQL migrations — run in this order on local or production DB

Track which files you already ran on each environment.

**Full game plan:** [MMORPG_ROADMAP.md](MMORPG_ROADMAP.md)  
**Next modules:** [modules/001_PVP_1v1.md](modules/001_PVP_1v1.md) → [modules/001b_PLAYER_CLASSES.md](modules/001b_PLAYER_CLASSES.md) → [modules/002_PARTY_PVE.md](modules/002_PARTY_PVE.md)

## Base schema
1. `private_functions/SQL/chat_system.sql` (if fresh DB — or skip if DB exists)
2. `private_functions/SQL/chat_system_alter_local.sql`
3. `private_functions/SQL/chat_system_alter_party.sql`
4. `private_functions/SQL/trade_system.sql`
5. `private_functions/SQL/language_texts_chat.sql`
6. `private_functions/SQL/language_texts_game_ui.sql`
7. `private_functions/SQL/chat_word_replacements.sql`

## Per-module checklist

| Module | Code paths | SQL | Asset bump |
|--------|------------|-----|------------|
| Chat | `chat.php`, `chat.js`, `poll_chat.php` | chat_system*, language_texts_chat | `character_config.php` |
| Word filter | `chat_word_filter.php` | chat_word_replacements | — |
| Target UI | `target.js`, `world.js`, `game.css` | language_texts_game_ui | yes |
| Trade | `trade.php`, `trade.js`, `open_actions/*trade*` | trade_system | yes |

After each module: commit → test local Docker → deploy → run new SQL on server → smoke test live site.
