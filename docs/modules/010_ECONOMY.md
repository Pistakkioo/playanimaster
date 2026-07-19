# Module 10 — Economy & shops

**Status:** Implemented (v1)
**Depends on:** `item_types` / `items` (inventory), `users_ig.gold`, NPC dialog → consequence pipeline (`consequences.php`, `get_conversation_consequences.php`), `quests.php` (`onInventoryChanged`)
**Reuses:** `trade.php`'s guarded gold-debit pattern, `consequences.php::handleObtainItem`'s insert-N-rows item grant pattern

---

## 1. Scope

**In scope (v1):**

- Vendor shops with a per-shop item catalog (price/sell overrides, optional limited stock).
- **Buy** (player → vendor, gold sink) and **Sell** (player → vendor, gold source) transactions.
- `[open shop]` NPC dialog consequence — a pure client signal that opens the shop panel; no server mutation.
- Dev admin tool (`dev_shops.php`) to create shops and manage their catalogs.
- A transaction audit log (`shop_transactions`) that also serves as the logging precedent for future economy features (mail/auction, gold sinks).

**Out of scope (v1) — explicitly deferred:**

- "Gold sinks: repair, travel, clan tax" from the original stub doc — no repair/durability system exists yet; travel (Module 13) and clan tax (Module 09) don't exist yet. v1 only ships the shop sink; other sinks are expected to reuse `animaster_economy_spend_gold` once their modules land.
- Limited-stock auto-restock cron/scheduling — schema supports `shop_items.stock_qty`, but restocking is manual via the dev tool.
- Rate limiting on `shop_buy.php` / `shop_sell.php` — deferred to Module 15 (Ops), same as every other endpoint today.
- Per-NPC 1:1 shop binding — a shop is a standalone entity referenced by `id_ref` from a dialog consequence, so one shop template can be reused by multiple NPCs.

---

## 2. Database schema

Three new tables (added to `00_tables.sql` for greenfield installs, mirrored as `CREATE TABLE IF NOT EXISTS` in `01_alters_structure.sql` for live/testing rollout) plus one `item_types` alter.

### `shops`

One row per vendor template. Not bound 1:1 to an NPC — referenced by `id_ref` from a `[open shop]` dialog consequence, so several NPCs can point at the same shop.

| Column | Notes |
|---|---|
| `id_shop` | PK |
| `shop_key` | optional unique human key for dev/debug reference |
| `name` / `name_it` / `name_pt` | i18n display name |
| `shop_type` | flavor/filter only: `general`, `potion`, `tackle`, ... — not enforced server-side |
| `flg_buys_from_player` | `S` = this vendor buys sellable items from the player (enables the Sell tab client-side) |
| `flg_active` | `S`/`N` — inactive shops 404 on `get_shop.php` |

### `shop_items`

Per-shop catalog row, one per sellable item type in that shop.

| Column | Notes |
|---|---|
| `id_shop_item` | PK |
| `id_shop`, `id_item_type` | unique together (`uq_shop_items_shop_item_type`) |
| `price_override` | NULL = use `item_types.price` for this shop |
| `sell_price_override` | NULL = use `item_types.sell_price` for this shop |
| `stock_qty` | NULL = unlimited; otherwise decremented atomically on buy |
| `flg_active` | per-row on/off without deleting |
| `sort_order` | display order in the Buy tab |

**Selling does not require a `shop_items` row.** Any item with `item_types.flg_sellable = 'S'` and `sell_price > 0` can be sold to a shop with `flg_buys_from_player = 'S'`, at `item_types.sell_price`. A `shop_items` row is only needed if a specific shop should override that sell price.

### `shop_transactions`

Append-only audit log, one row per completed buy or sell.

| Column | Notes |
|---|---|
| `id_shop_transaction` | PK |
| `id_shop`, `id_user_ig`, `id_item_type` | who bought/sold what, where |
| `direction` | `BUY` \| `SELL` |
| `quantity`, `unit_price`, `total_gold` | transaction detail |
| `gold_after` | player's gold balance immediately after, for audit/debug |

Indexed on `(id_user_ig, dt_c)` and `(id_shop, dt_c)` for the dev tool's recent-transactions view.

### `item_types.flg_buyable` (alter)

```sql
ALTER TABLE playanimaster_db.item_types
    ADD COLUMN flg_buyable CHAR(1) NOT NULL DEFAULT 'S'
    COMMENT 'S = purchasable from vendors (independent of flg_sellable); NULL/0 price still blocks a buy regardless'
    AFTER flg_sellable;

UPDATE playanimaster_db.item_types SET flg_buyable = 'N' WHERE price IS NULL OR price <= 0;
```

Independent of `flg_sellable` — an item can be buyable-only, sellable-only, both, or neither. `00_tables.sql`'s `item_types` `CREATE TABLE` block is **never edited** for this (frozen baseline rule); the column only exists via the `01_alters_structure.sql` alter, which runs after `00_tables.sql` on every environment per the SQL deploy order.

### Seed data (`02_insert_static_data.sql`)

- `consequences` catalog row `(7, '[open shop]')` — required so the NPC content dev tool can link a dialog option to this consequence type.
- One starter shop `#1 "General Goods"` (`shop_key = general_goods`) with a `shop_items` catalog covering the five seeded potion item types (ids 1–5), all at default (no override) pricing.
- `language_texts` rows for the `shop.*` UI tags (ids 506–529).

---

## 3. Server logic

### `private_functions/economy.php` — reusable primitives

Centralized gold/item helpers used by `shop.php`. Existing callers (`trade.php`, `consequences.php`) are **not** required to migrate to these — they're the base for new economy code (shops now, mail/auction later).

| Function | Behavior |
|---|---|
| `animaster_economy_get_gold($conn, $id_user_ig)` | Reads `users_ig.gold`. |
| `animaster_economy_add_gold($conn, $id_user_ig, $amount)` | Unconditional credit. |
| `animaster_economy_spend_gold($conn, $id_user_ig, $amount)` | Guarded debit: `UPDATE ... SET gold = gold - :amt WHERE ... AND gold >= :amt2`, returns `rowCount() > 0`. Same pattern as `trade.php`'s `animaster_trade_execute`. Prevents negative gold under concurrent requests without needing `SELECT ... FOR UPDATE`. |
| `animaster_economy_count_available_items($conn, $id_user_ig, $id_item_type)` | Counts unused (`dt_used IS NULL`), unheld (`flg_held != 'S'`) item rows. Mirrors `animaster_trade_count_available_items` minus the tradable/trade-lock filtering (selling doesn't require `flg_tradable`). |
| `animaster_economy_give_item($conn, $id_user_ig, $id_item_type, $quantity)` | Inserts `$quantity` new `items` rows. Same insert-N-rows pattern as `CONSEQUENCES::handleObtainItem`. |
| `animaster_economy_take_items($conn, $id_user_ig, $id_item_type, $quantity)` | `SELECT ... FOR UPDATE` the oldest N available rows, `DELETE`s them; returns `false` (no partial effect) if fewer than `$quantity` are available. Must be called inside a transaction (row locking requires it). |

### `private_functions/shop.php` — buy/sell business logic

- `animaster_shop_fetch($conn, $id_user_ig, $id_shop, $lang)` — shop row + joined `shop_items` × `item_types` (effective price = `COALESCE(price_override, price)`, same for sell price), filtered to `flg_active = 'S'` on both shop and shop_items, and `item_types.flg_buyable = 'S'`. Returns the player's current gold alongside the catalog so the client doesn't need a second round trip.
- `animaster_shop_buy($conn, $id_user_ig, $id_shop, $id_item_type, $quantity, $lang)`:
  1. Validate shop active, shop_item active + buyable, resolved price > 0, `0 < quantity <= ANIMASTER_SHOP_MAX_QUANTITY` (999).
  2. If `stock_qty` is not NULL, pre-check `stock_qty >= quantity`.
  3. `beginTransaction()` → `spend_gold(total)` (fails closed on insufficient gold) → if limited stock, guarded `UPDATE shop_items SET stock_qty = stock_qty - :q WHERE ... AND stock_qty >= :q` + `rowCount()` check (race guard against two simultaneous buyers draining stock) → `give_item(quantity)` → insert `shop_transactions` row → `commit()`. Any failed step rolls back and returns a typed error; `QUESTS::onInventoryChanged` fires after commit (for `collect_item` quest objectives).
- `animaster_shop_sell($conn, $id_user_ig, $id_shop, $id_item_type, $quantity, $lang)`:
  1. Gate on `shops.flg_buys_from_player = 'S'`, `item_types.flg_sellable = 'S'`, resolved sell price > 0 (shop-specific `shop_items.sell_price_override` wins if present and active).
  2. `count_available_items >= quantity`, else error.
  3. `beginTransaction()` → `take_items(quantity)` (fails closed if the availability check above raced) → `add_gold(total)` → log `shop_transactions` → `commit()`.

Both return `['ok' => true, 'gold' => ..., 'quantity' => ..., 'total_gold' => ...]` on success or `['ok' => false, 'error' => 'CODE']` on failure. Error codes: `SHOP_NOT_FOUND`, `SHOP_DOES_NOT_BUY`, `ITEM_NOT_IN_SHOP`, `ITEM_NOT_BUYABLE`, `ITEM_NOT_SELLABLE`, `OUT_OF_STOCK`, `INSUFFICIENT_GOLD`, `INSUFFICIENT_ITEMS`, `INVALID_QUANTITY`, `INVALID_REQUEST`, `SERVER_ERROR`.

---

## 4. Endpoints

Thin wrappers under `public_html/funzioni/open_actions/`, JSON-object `response` (like `trade.php`'s modern endpoints — `poll_trade.php`, `confirm_trade.php` — not the legacy `#`-hash-split style used by `get_inventory.php`).

| Endpoint | POST params | Calls |
|---|---|---|
| `get_shop.php` | `id_user_ig`, `id_shop`, `lang` | `animaster_shop_fetch` |
| `shop_buy.php` | `id_user_ig`, `id_shop`, `id_item_type`, `quantity`, `lang` | `animaster_shop_buy` |
| `shop_sell.php` | `id_user_ig`, `id_shop`, `id_item_type`, `quantity`, `lang` | `animaster_shop_sell` |

All three: `stato: 'OK'|'KO'`, `msg`, `response` = JSON-encoded result object (or `'{}'` on failure before a result exists). On `KO`, `msg` carries the error code from the section above so the client can map it to a translated string.

---

## 5. Consequence wiring

- `consequences.php::handlers()` registers `'[open shop]' => [self::class, 'handleOpenShop']`.
- `handleOpenShop()` is a **no-op returning `true`** — this consequence is a pure client signal. The `id_ref` (the `id_shop`) is not applied server-side; it's read back from the `get_conversation_consequences.php` response/`response2` envelope by the client.
- **`flg_register` gotcha:** if the dialog option that triggers `[open shop]` is marked to register as finished, `get_conversation_consequences.php` will skip consequence processing entirely on the next visit (`PLAYER_CONVERSATIONS::isFinished` short-circuits). The dialog option should normally **not** register as finished (or be repeatable) so the shop reopens every time the player picks that dialog choice.
- Dev tooling: `dev_npc_content.php` adds `[open shop]` to `dev_npc_consequence_types()` and `shops` (`id_ref = id_shop`) to `dev_npc_consequence_ref_tables()`, plus `dev_npc_fetch_shops()` for the id_ref picker. `dev_npcs.php` wires the picker (`dev_npc_consequence_link_ref_fields`) and the consequence-type → ref_table JS suggestion map to include `shops`.

---

## 6. Client

### `public_html/client/js/api.js`

`getShop(player, idShop)`, `shopBuy(player, idShop, idItemType, quantity)`, `shopSell(player, idShop, idItemType, quantity)` — follow the `postJson` + `parseTradeEnvelope` convention already used by the trade endpoints (unwraps `stato`, `JSON.parse`s `response`).

### `public_html/client/js/shop.js` — `AnimasterShop` IIFE

Structural twin of `AnimasterInventory` / the trade overlay, but simpler (no live polling — single request/response per action):

- `#shop-overlay` panel with Buy / Sell tabs, live gold display, per-row quantity input + action button.
- Buy tab data comes from `AnimasterApi.getShop`. Sell tab data reuses `AnimasterApi.getInventory(player, false)` (already returns `sell_price`/`flg_sellable` per item), filtered client-side to `flg_sellable === 'S' && sell_price > 0`.
- Sell tab is hidden entirely when the fetched shop's `flg_buys_from_player !== 'S'`.
- `AnimasterShop.init()` / `.setPlayer(player)` / `.open(idShop, player)` / `.close()` / `.isOpen()` public API.
- On a successful sell, also refreshes `AnimasterInventory` if it's open (bag contents changed).

### `public_html/client/game.php`

`#shop-overlay` markup mirrors `#trade-overlay`'s structure (header + gold row + tabs + list + status line). `<script src=".../shop.js">` added after `trade.js`.

### `public_html/client/css/game.css`

`.shop-*` rules mirror the `.trade-*` overlay/panel styling (dark modal, same z-index layer as trade/duel overlays).

### Dialog consequence → shop UI (`public_html/client/js/game.js`)

In `onDialogClosed(envelope)`, parallel to the existing `[set player_class]` branch:

```js
if (envelope && envelope.response2 && envelope.response2.indexOf('[open shop]') !== -1 && player)
{
    var shopRows = (envelope.response || '').split('#').map(function (s) {
        try { return JSON.parse(s); } catch (e) { return null; }
    });
    var shopRow = shopRows.filter(function (r) { return r && r.consequence_type === '[open shop]'; })[0];

    if (shopRow && shopRow.id_ref)
    {
        AnimasterShop.open(parseInt(shopRow.id_ref, 10), player);
    }
}
```

`AnimasterShop.init()` is called alongside `AnimasterInventory.init()` / `AnimasterTrade.init()` in the `game.js` init block; `AnimasterShop.setPlayer(player)` is added to `syncPlayerToModules()`.

### i18n

`shop.*` tags (title, tab labels, price/stock/owned labels, buy/sell success messages, error strings) live in `language_texts` — loaded unfiltered into `bootstrap.texts` by `animaster_load_language_texts()`, no separate whitelist to maintain.

---

## 7. Dev tooling

### `public_html/dev_shops.php` + `private_functions/dev_shops_content.php`

Mirrors the `dev_species.php` / `dev_species_content.php` structure (simpler than the `dev_npcs.php` tree, since shops have only one child collection):

- List/create/edit `shops` rows (name + i18n, `shop_type`, `flg_buys_from_player`, `flg_active`).
- Catalog table per selected shop with inline edit/remove links, and a form to add/edit a `shop_items` row (item type picker, price/sell overrides, stock, sort order, active toggle). Item type can't be changed after creation — remove and re-add instead, since it's part of the unique key.
- Read-only "recent transactions" table (last 50 `shop_transactions`, joined to shop/item/player names) for auditing/debugging.
- Linked from `dev_static_data.php` and `dev_npcs.php` nav rows ("Shops editor").

### `private_functions/dev_npc_content.php` / `public_html/dev_npcs.php`

See [Consequence wiring](#5-consequence-wiring) above — `dev_npc_fetch_shops()`, the `[open shop]` consequence type entry, and the `shops` ref_table entry.

---

## 8. Deploy checklist

- [ ] `00_tables.sql`: `shops`, `shop_items`, `shop_transactions` `CREATE TABLE` blocks appended (greenfield only — never edit past blocks).
- [ ] `01_alters_structure.sql`: mirrored `CREATE TABLE IF NOT EXISTS` for the same three tables, plus the `item_types.flg_buyable` `ADD COLUMN` + backfill `UPDATE`, appended at the end of the file.
- [ ] `02_insert_static_data.sql`: `consequences` catalog row for `[open shop]`, starter shop + catalog seed, `shop.*` `language_texts` rows.
- [ ] `ANIMASTER_ASSET_VERSION` bumped in `private_functions/character_config.php` (client JS/CSS changed).
- [ ] Run the alters against any already-existing (testing/live) database — `01_alters_structure.sql` statements are idempotent (`CREATE TABLE IF NOT EXISTS`) except the `item_types` alter, which is documented as run-once.

## 9. Testing checklist

- [ ] Buy happy path: gold debited, item(s) granted, `shop_transactions` row logged, `gold_after` matches new balance.
- [ ] Buy with insufficient gold → `INSUFFICIENT_GOLD`, no partial effect (no item granted, no gold change).
- [ ] Buy with limited stock at exactly `stock_qty` → succeeds and stock hits 0; next buy attempt → `OUT_OF_STOCK`.
- [ ] Concurrent buy race on the last unit of limited stock → exactly one request succeeds (guarded `UPDATE ... WHERE stock_qty >= :q`).
- [ ] Sell happy path: item(s) removed, gold credited, `shop_transactions` row logged with `direction = 'SELL'`.
- [ ] Sell blocked: item not sellable (`ITEM_NOT_SELLABLE`), not enough owned units (`INSUFFICIENT_ITEMS`), shop with `flg_buys_from_player = 'N'` (`SHOP_DOES_NOT_BUY`).
- [ ] `[open shop]` dialog option reopens the shop on every visit (verify the option is not `flg_register`-finished after the first click).
- [ ] Inactive shop (`flg_active = 'N'`) → `get_shop.php` returns `SHOP_NOT_FOUND`.
- [ ] Dev tool: create a shop, add/edit/remove catalog items, verify the recent-transactions view after a buy/sell in-game.

## 10. Deferred / future modules

- Repair/durability, travel cost, and clan tax gold sinks — blocked on their respective modules (none exist yet); expected to call `animaster_economy_spend_gold` directly once built.
- Limited-stock restock scheduling (cron/job) — currently manual via `dev_shops.php`.
- Rate limiting on buy/sell endpoints — Module 15 (Ops), applies project-wide.
- Module 11 (Mail/Auction) is expected to depend on `economy.php`'s gold/item primitives for its own transfers, and on `shop_transactions` as the audit-log schema precedent.
