<?php

require_once __DIR__ . '/dev_npc_content.php';

/**
 * @return array<int, array<string, mixed>>
 */
function dev_shops_fetch_tree(PDO $conn)
{
    $tree = [];

    $stmt_shops = $conn->query('
        SELECT *
        FROM shops
        ORDER BY id_shop ASC
    ');

    if (!$stmt_shops)
    {
        return [];
    }

    while ($row = $stmt_shops->fetch(PDO::FETCH_ASSOC))
    {
        $id_shop = (int) $row['id_shop'];
        $tree[$id_shop] = $row;
        $tree[$id_shop]['shop_items'] = [];
    }

    if (!$tree)
    {
        return [];
    }

    $stmt_items = $conn->query('
        SELECT SI.*, IT.item_type, IT.nome AS item_nome, IT.price AS item_price,
               IT.sell_price AS item_sell_price, IT.flg_buyable, IT.flg_sellable
        FROM shop_items SI
        INNER JOIN item_types IT ON IT.id_item_type = SI.id_item_type
        ORDER BY SI.id_shop ASC, SI.sort_order ASC, SI.id_shop_item ASC
    ');

    if ($stmt_items)
    {
        while ($row = $stmt_items->fetch(PDO::FETCH_ASSOC))
        {
            $id_shop = (int) $row['id_shop'];

            if (isset($tree[$id_shop]))
            {
                $tree[$id_shop]['shop_items'][] = $row;
            }
        }
    }

    return $tree;
}

function dev_shops_fetch_transactions(PDO $conn, $limit = 50)
{
    $limit = max(1, min(200, (int) $limit));

    $stmt = $conn->query('
        SELECT ST.*, S.name AS shop_name, IT.nome AS item_nome, U.display_name
        FROM shop_transactions ST
        INNER JOIN shops S ON S.id_shop = ST.id_shop
        INNER JOIN item_types IT ON IT.id_item_type = ST.id_item_type
        LEFT JOIN users_ig U ON U.id_user_ig = ST.id_user_ig
        ORDER BY ST.id_shop_transaction DESC
        LIMIT ' . $limit . '
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_shops_item_label($row)
{
    $name = $row['item_nome'] ?: $row['item_type'];

    return '#' . (int) $row['id_item_type'] . ' ' . $name;
}

function dev_shops_handle_post(PDO $conn, array $post)
{
    $action = dev_npc_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'add_shop':
                $stmt = $conn->prepare('
                    INSERT INTO shops
                        (shop_key, name, name_it, name_pt, shop_type, flg_buys_from_player, flg_active, dt_c)
                    VALUES
                        (:shop_key, :name, :name_it, :name_pt, :shop_type, :flg_buys_from_player, :flg_active, NOW())
                ');
                $stmt->execute([
                    ':shop_key' => dev_npc_post_str($post, 'shop_key', 50) ?: null,
                    ':name' => dev_npc_post_str($post, 'name', 100),
                    ':name_it' => dev_npc_post_str($post, 'name_it', 100) ?: null,
                    ':name_pt' => dev_npc_post_str($post, 'name_pt', 100) ?: null,
                    ':shop_type' => dev_npc_post_str($post, 'shop_type', 30) ?: 'general',
                    ':flg_buys_from_player' => dev_npc_post_yn($post, 'flg_buys_from_player', 'S'),
                    ':flg_active' => dev_npc_post_yn($post, 'flg_active', 'S'),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Shop created (id ' . $conn->lastInsertId() . ').',
                    'redirect' => dev_admin_page_url('dev_shops.php'),
                ];

            case 'update_shop':
                $id_shop = dev_npc_post_int($post, 'id_shop');
                $stmt = $conn->prepare('
                    UPDATE shops
                    SET shop_key = :shop_key,
                        name = :name,
                        name_it = :name_it,
                        name_pt = :name_pt,
                        shop_type = :shop_type,
                        flg_buys_from_player = :flg_buys_from_player,
                        flg_active = :flg_active
                    WHERE id_shop = :id_shop
                ');
                $stmt->execute([
                    ':shop_key' => dev_npc_post_str($post, 'shop_key', 50) ?: null,
                    ':name' => dev_npc_post_str($post, 'name', 100),
                    ':name_it' => dev_npc_post_str($post, 'name_it', 100) ?: null,
                    ':name_pt' => dev_npc_post_str($post, 'name_pt', 100) ?: null,
                    ':shop_type' => dev_npc_post_str($post, 'shop_type', 30) ?: 'general',
                    ':flg_buys_from_player' => dev_npc_post_yn($post, 'flg_buys_from_player', 'S'),
                    ':flg_active' => dev_npc_post_yn($post, 'flg_active', 'S'),
                    ':id_shop' => $id_shop,
                ]);

                return [
                    'ok' => true,
                    'message' => 'Shop updated.',
                    'redirect' => dev_admin_page_url('dev_shops.php'),
                ];

            case 'add_shop_item':
                $id_shop = dev_npc_post_int($post, 'id_shop');
                $id_item_type = dev_npc_post_int($post, 'id_item_type');

                if ($id_shop <= 0 || $id_item_type <= 0)
                {
                    return ['ok' => false, 'message' => 'Shop and item type are required.'];
                }

                $price_override = dev_npc_post_str($post, 'price_override', 10);
                $sell_price_override = dev_npc_post_str($post, 'sell_price_override', 10);
                $stock_qty = dev_npc_post_str($post, 'stock_qty', 10);

                $stmt = $conn->prepare('
                    INSERT INTO shop_items
                        (id_shop, id_item_type, price_override, sell_price_override, stock_qty, flg_active, sort_order, dt_c)
                    VALUES
                        (:id_shop, :id_item_type, :price_override, :sell_price_override, :stock_qty, :flg_active, :sort_order, NOW())
                    ON DUPLICATE KEY UPDATE
                        price_override = VALUES(price_override),
                        sell_price_override = VALUES(sell_price_override),
                        stock_qty = VALUES(stock_qty),
                        flg_active = VALUES(flg_active),
                        sort_order = VALUES(sort_order)
                ');
                $stmt->execute([
                    ':id_shop' => $id_shop,
                    ':id_item_type' => $id_item_type,
                    ':price_override' => $price_override !== '' ? (int) $price_override : null,
                    ':sell_price_override' => $sell_price_override !== '' ? (int) $sell_price_override : null,
                    ':stock_qty' => $stock_qty !== '' ? (int) $stock_qty : null,
                    ':flg_active' => dev_npc_post_yn($post, 'flg_active', 'S'),
                    ':sort_order' => dev_npc_post_int($post, 'sort_order', 0),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Shop item saved.',
                    'redirect' => dev_admin_page_url('dev_shops.php', ['id_shop' => $id_shop]),
                ];

            case 'update_shop_item':
                $id_shop_item = dev_npc_post_int($post, 'id_shop_item');
                $id_shop = dev_npc_post_int($post, 'id_shop');

                if ($id_shop_item <= 0)
                {
                    return ['ok' => false, 'message' => 'Invalid shop item id.'];
                }

                $price_override = dev_npc_post_str($post, 'price_override', 10);
                $sell_price_override = dev_npc_post_str($post, 'sell_price_override', 10);
                $stock_qty = dev_npc_post_str($post, 'stock_qty', 10);

                $stmt = $conn->prepare('
                    UPDATE shop_items
                    SET price_override = :price_override,
                        sell_price_override = :sell_price_override,
                        stock_qty = :stock_qty,
                        flg_active = :flg_active,
                        sort_order = :sort_order
                    WHERE id_shop_item = :id_shop_item
                ');
                $stmt->execute([
                    ':price_override' => $price_override !== '' ? (int) $price_override : null,
                    ':sell_price_override' => $sell_price_override !== '' ? (int) $sell_price_override : null,
                    ':stock_qty' => $stock_qty !== '' ? (int) $stock_qty : null,
                    ':flg_active' => dev_npc_post_yn($post, 'flg_active', 'S'),
                    ':sort_order' => dev_npc_post_int($post, 'sort_order', 0),
                    ':id_shop_item' => $id_shop_item,
                ]);

                return [
                    'ok' => true,
                    'message' => 'Shop item updated.',
                    'redirect' => dev_admin_page_url('dev_shops.php', ['id_shop' => $id_shop]),
                ];

            case 'delete_shop_item':
                $id_shop_item = dev_npc_post_int($post, 'id_shop_item');
                $id_shop = dev_npc_post_int($post, 'id_shop');

                if ($id_shop_item <= 0)
                {
                    return ['ok' => false, 'message' => 'Invalid shop item id.'];
                }

                $stmt = $conn->prepare('DELETE FROM shop_items WHERE id_shop_item = :id_shop_item LIMIT 1');
                $stmt->execute([':id_shop_item' => $id_shop_item]);

                return [
                    'ok' => true,
                    'message' => $stmt->rowCount() > 0 ? 'Shop item removed.' : 'Shop item not found.',
                    'redirect' => dev_admin_page_url('dev_shops.php', ['id_shop' => $id_shop]),
                ];

            default:
                return ['ok' => false, 'message' => 'Unknown action: ' . $action];
        }
    }
    catch (Throwable $e)
    {
        error_log('[dev_shops_handle_post] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Server error: ' . $e->getMessage()];
    }
}
