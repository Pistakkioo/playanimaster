<?php

require_once dirname(__FILE__) . '/economy.php';

if (!defined('ANIMASTER_SHOP_MAX_QUANTITY'))
{
    define('ANIMASTER_SHOP_MAX_QUANTITY', 999);
}

/**
 * Normalizes a lang value ('', 'en', 'it', '_it', ...) to the '_it' / '_pt' /
 * '' suffix used on item_types/shops i18n columns.
 */
function animaster_shop_lang_suffix($lang)
{
    $lang = (string) $lang;

    if ($lang === '_it' || $lang === '_pt')
    {
        return $lang;
    }

    if ($lang === 'it' || $lang === 'pt')
    {
        return '_' . $lang;
    }

    return '';
}

function animaster_shop_fetch_row($conn, $id_shop)
{
    $stmt = $conn->prepare('
        SELECT id_shop, shop_key, name, name_it, name_pt, shop_type,
               flg_buys_from_player, flg_active
        FROM shops
        WHERE id_shop = :id_shop
        LIMIT 1
    ');
    $stmt->execute(['id_shop' => (int) $id_shop]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Returns ['ok' => true, 'shop' => {...}, 'items' => [...], 'gold' => int]
 * or ['ok' => false, 'error' => string].
 */
function animaster_shop_fetch($conn, $id_user_ig, $id_shop, $lang = '')
{
    $id_shop = (int) $id_shop;
    $id_user_ig = (int) $id_user_ig;
    $lang_suffix = animaster_shop_lang_suffix($lang);

    $shop = animaster_shop_fetch_row($conn, $id_shop);

    if (!$shop || trim((string) $shop['flg_active']) !== 'S')
    {
        return ['ok' => false, 'error' => 'SHOP_NOT_FOUND'];
    }

    $name_col = 'name' . $lang_suffix;

    $stmt = $conn->prepare("
        SELECT si.id_shop_item, si.id_item_type, si.stock_qty, si.sort_order,
               it.item_type, it.nome{$lang_suffix} AS nome,
               it.descrizione{$lang_suffix} AS descrizione,
               it.flg_stackable, it.flg_holdable,
               COALESCE(si.price_override, it.price) AS price,
               COALESCE(si.sell_price_override, it.sell_price) AS sell_price
        FROM shop_items si
        INNER JOIN item_types it ON it.id_item_type = si.id_item_type
        WHERE si.id_shop = :id_shop
          AND si.flg_active = 'S'
          AND it.flg_buyable = 'S'
        ORDER BY si.sort_order ASC, si.id_shop_item ASC
    ");
    $stmt->execute(['id_shop' => $id_shop]);
    $items = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $items[] = [
            'id_shop_item' => (int) $row['id_shop_item'],
            'id_item_type' => (int) $row['id_item_type'],
            'item_type' => $row['item_type'],
            'nome' => $row['nome'],
            'descrizione' => $row['descrizione'],
            'flg_stackable' => $row['flg_stackable'],
            'flg_holdable' => $row['flg_holdable'],
            'price' => $row['price'] !== null ? (int) $row['price'] : null,
            'sell_price' => $row['sell_price'] !== null ? (int) $row['sell_price'] : null,
            'stock_qty' => $row['stock_qty'] !== null ? (int) $row['stock_qty'] : null,
        ];
    }

    return [
        'ok' => true,
        'shop' => [
            'id_shop' => (int) $shop['id_shop'],
            'name' => $shop[$name_col] !== null && $shop[$name_col] !== '' ? $shop[$name_col] : $shop['name'],
            'shop_type' => $shop['shop_type'],
            'flg_buys_from_player' => $shop['flg_buys_from_player'],
        ],
        'items' => $items,
        'gold' => animaster_economy_get_gold($conn, $id_user_ig),
    ];
}

/**
 * Returns ['ok' => true, 'gold' => int, 'quantity' => int, 'total_gold' => int]
 * or ['ok' => false, 'error' => string].
 */
function animaster_shop_buy($conn, $id_user_ig, $id_shop, $id_item_type, $quantity, $lang = '')
{
    $id_user_ig = (int) $id_user_ig;
    $id_shop = (int) $id_shop;
    $id_item_type = (int) $id_item_type;
    $quantity = (int) $quantity;

    if ($id_user_ig <= 0 || $id_shop <= 0 || $id_item_type <= 0)
    {
        return ['ok' => false, 'error' => 'INVALID_REQUEST'];
    }

    if ($quantity <= 0 || $quantity > ANIMASTER_SHOP_MAX_QUANTITY)
    {
        return ['ok' => false, 'error' => 'INVALID_QUANTITY'];
    }

    $shop = animaster_shop_fetch_row($conn, $id_shop);

    if (!$shop || trim((string) $shop['flg_active']) !== 'S')
    {
        return ['ok' => false, 'error' => 'SHOP_NOT_FOUND'];
    }

    $stmt = $conn->prepare('
        SELECT si.id_shop_item, si.stock_qty, si.flg_active,
               it.flg_buyable, COALESCE(si.price_override, it.price) AS price
        FROM shop_items si
        INNER JOIN item_types it ON it.id_item_type = si.id_item_type
        WHERE si.id_shop = :id_shop AND si.id_item_type = :id_item_type
        LIMIT 1
    ');
    $stmt->execute(['id_shop' => $id_shop, 'id_item_type' => $id_item_type]);
    $shop_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shop_item || trim((string) $shop_item['flg_active']) !== 'S')
    {
        return ['ok' => false, 'error' => 'ITEM_NOT_IN_SHOP'];
    }

    if (trim((string) $shop_item['flg_buyable']) !== 'S')
    {
        return ['ok' => false, 'error' => 'ITEM_NOT_BUYABLE'];
    }

    $price = $shop_item['price'] !== null ? (int) $shop_item['price'] : 0;

    if ($price <= 0)
    {
        return ['ok' => false, 'error' => 'ITEM_NOT_BUYABLE'];
    }

    $stock_qty = $shop_item['stock_qty'] !== null ? (int) $shop_item['stock_qty'] : null;

    if ($stock_qty !== null && $stock_qty < $quantity)
    {
        return ['ok' => false, 'error' => 'OUT_OF_STOCK'];
    }

    $total_gold = $price * $quantity;

    $conn->beginTransaction();

    try
    {
        if (!animaster_economy_spend_gold($conn, $id_user_ig, $total_gold))
        {
            $conn->rollBack();
            return ['ok' => false, 'error' => 'INSUFFICIENT_GOLD'];
        }

        if ($stock_qty !== null)
        {
            $stmt_stock = $conn->prepare('
                UPDATE shop_items
                SET stock_qty = stock_qty - :q
                WHERE id_shop_item = :id_shop_item AND stock_qty >= :q2
            ');
            $stmt_stock->execute([
                'q' => $quantity,
                'id_shop_item' => (int) $shop_item['id_shop_item'],
                'q2' => $quantity,
            ]);

            if ($stmt_stock->rowCount() <= 0)
            {
                $conn->rollBack();
                return ['ok' => false, 'error' => 'OUT_OF_STOCK'];
            }
        }

        animaster_economy_give_item($conn, $id_user_ig, $id_item_type, $quantity);

        $gold_after = animaster_economy_get_gold($conn, $id_user_ig);

        $stmt_log = $conn->prepare('
            INSERT INTO shop_transactions
                (id_shop, id_user_ig, id_item_type, direction, quantity, unit_price, total_gold, gold_after, dt_c)
            VALUES
                (:id_shop, :id_user_ig, :id_item_type, \'BUY\', :quantity, :unit_price, :total_gold, :gold_after, NOW())
        ');
        $stmt_log->execute([
            'id_shop' => $id_shop,
            'id_user_ig' => $id_user_ig,
            'id_item_type' => $id_item_type,
            'quantity' => $quantity,
            'unit_price' => $price,
            'total_gold' => $total_gold,
            'gold_after' => $gold_after,
        ]);

        $conn->commit();

        if (!class_exists('QUESTS'))
        {
            require_once dirname(__FILE__) . '/quests.php';
        }

        QUESTS::onInventoryChanged($conn, $id_user_ig, animaster_shop_lang_suffix($lang));

        return [
            'ok' => true,
            'gold' => $gold_after,
            'quantity' => $quantity,
            'total_gold' => $total_gold,
        ];
    }
    catch (Throwable $e)
    {
        $conn->rollBack();
        error_log('[animaster_shop_buy] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'SERVER_ERROR'];
    }
}

/**
 * Returns ['ok' => true, 'gold' => int, 'quantity' => int, 'total_gold' => int]
 * or ['ok' => false, 'error' => string].
 */
function animaster_shop_sell($conn, $id_user_ig, $id_shop, $id_item_type, $quantity, $lang = '')
{
    $id_user_ig = (int) $id_user_ig;
    $id_shop = (int) $id_shop;
    $id_item_type = (int) $id_item_type;
    $quantity = (int) $quantity;

    if ($id_user_ig <= 0 || $id_shop <= 0 || $id_item_type <= 0)
    {
        return ['ok' => false, 'error' => 'INVALID_REQUEST'];
    }

    if ($quantity <= 0 || $quantity > ANIMASTER_SHOP_MAX_QUANTITY)
    {
        return ['ok' => false, 'error' => 'INVALID_QUANTITY'];
    }

    $shop = animaster_shop_fetch_row($conn, $id_shop);

    if (!$shop || trim((string) $shop['flg_active']) !== 'S')
    {
        return ['ok' => false, 'error' => 'SHOP_NOT_FOUND'];
    }

    if (trim((string) $shop['flg_buys_from_player']) !== 'S')
    {
        return ['ok' => false, 'error' => 'SHOP_DOES_NOT_BUY'];
    }

    $stmt = $conn->prepare('
        SELECT sell_price, flg_sellable
        FROM item_types
        WHERE id_item_type = :id_item_type
        LIMIT 1
    ');
    $stmt->execute(['id_item_type' => $id_item_type]);
    $item_type = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_type || trim((string) $item_type['flg_sellable']) !== 'S')
    {
        return ['ok' => false, 'error' => 'ITEM_NOT_SELLABLE'];
    }

    // A shop_items row for this shop can override the sell price; absence of
    // a row is fine (any buying vendor accepts any sellable item).
    $stmt_override = $conn->prepare('
        SELECT sell_price_override
        FROM shop_items
        WHERE id_shop = :id_shop AND id_item_type = :id_item_type AND flg_active = \'S\'
        LIMIT 1
    ');
    $stmt_override->execute(['id_shop' => $id_shop, 'id_item_type' => $id_item_type]);
    $override_row = $stmt_override->fetch(PDO::FETCH_ASSOC);

    $sell_price = ($override_row && $override_row['sell_price_override'] !== null)
        ? (int) $override_row['sell_price_override']
        : (int) $item_type['sell_price'];

    if ($sell_price <= 0)
    {
        return ['ok' => false, 'error' => 'ITEM_NOT_SELLABLE'];
    }

    if (animaster_economy_count_available_items($conn, $id_user_ig, $id_item_type) < $quantity)
    {
        return ['ok' => false, 'error' => 'INSUFFICIENT_ITEMS'];
    }

    $total_gold = $sell_price * $quantity;

    $conn->beginTransaction();

    try
    {
        if (!animaster_economy_take_items($conn, $id_user_ig, $id_item_type, $quantity))
        {
            $conn->rollBack();
            return ['ok' => false, 'error' => 'INSUFFICIENT_ITEMS'];
        }

        animaster_economy_add_gold($conn, $id_user_ig, $total_gold);

        $gold_after = animaster_economy_get_gold($conn, $id_user_ig);

        $stmt_log = $conn->prepare('
            INSERT INTO shop_transactions
                (id_shop, id_user_ig, id_item_type, direction, quantity, unit_price, total_gold, gold_after, dt_c)
            VALUES
                (:id_shop, :id_user_ig, :id_item_type, \'SELL\', :quantity, :unit_price, :total_gold, :gold_after, NOW())
        ');
        $stmt_log->execute([
            'id_shop' => $id_shop,
            'id_user_ig' => $id_user_ig,
            'id_item_type' => $id_item_type,
            'quantity' => $quantity,
            'unit_price' => $sell_price,
            'total_gold' => $total_gold,
            'gold_after' => $gold_after,
        ]);

        $conn->commit();

        if (!class_exists('QUESTS'))
        {
            require_once dirname(__FILE__) . '/quests.php';
        }

        QUESTS::onInventoryChanged($conn, $id_user_ig, animaster_shop_lang_suffix($lang));

        return [
            'ok' => true,
            'gold' => $gold_after,
            'quantity' => $quantity,
            'total_gold' => $total_gold,
        ];
    }
    catch (Throwable $e)
    {
        $conn->rollBack();
        error_log('[animaster_shop_sell] ' . $e->getMessage());
        return ['ok' => false, 'error' => 'SERVER_ERROR'];
    }
}
