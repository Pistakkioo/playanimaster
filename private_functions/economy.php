<?php

/**
 * Centralized gold/item primitives for shops and future economy features
 * (mail/auction, gold sinks, etc.). Existing callers (trade.php, consequences.php)
 * are not required to migrate to these, but new economy code should use them
 * instead of duplicating the raw SQL.
 */

function animaster_economy_get_gold(PDO $conn, $id_user_ig)
{
    $stmt = $conn->prepare('SELECT gold FROM users_ig WHERE id_user_ig = :id_user_ig LIMIT 1');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['gold'] : null;
}

function animaster_economy_add_gold(PDO $conn, $id_user_ig, $amount)
{
    $amount = (int) $amount;

    if ($amount <= 0)
    {
        return true;
    }

    $stmt = $conn->prepare('UPDATE users_ig SET gold = gold + :amt WHERE id_user_ig = :id_user_ig');
    $stmt->execute(['amt' => $amount, 'id_user_ig' => (int) $id_user_ig]);

    return true;
}

/**
 * Atomically debits gold, refusing if the balance would go negative.
 * Same guarded pattern as trade.php's animaster_trade_execute.
 */
function animaster_economy_spend_gold(PDO $conn, $id_user_ig, $amount)
{
    $amount = (int) $amount;

    if ($amount <= 0)
    {
        return true;
    }

    $stmt = $conn->prepare('
        UPDATE users_ig
        SET gold = gold - :amt
        WHERE id_user_ig = :id_user_ig AND gold >= :amt2
    ');
    $stmt->execute([
        'amt' => $amount,
        'id_user_ig' => (int) $id_user_ig,
        'amt2' => $amount,
    ]);

    return $stmt->rowCount() > 0;
}

/**
 * Mirrors animaster_trade_count_available_items but without the tradable/
 * trade-lock filtering — counts any unused, unheld unit of the item type.
 */
function animaster_economy_count_available_items(PDO $conn, $id_user_ig, $id_item_type)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS cnt
        FROM items
        WHERE id_user_ig = :id_user_ig
          AND id_item_type = :id_item_type
          AND dt_used IS NULL
          AND (flg_held IS NULL OR flg_held = \'N\')
    ');
    $stmt->execute([
        'id_user_ig' => (int) $id_user_ig,
        'id_item_type' => (int) $id_item_type,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['cnt'] : 0;
}

/**
 * Inserts $quantity new item rows for the player (same insert-N-rows pattern
 * as CONSEQUENCES::handleObtainItem).
 */
function animaster_economy_give_item(PDO $conn, $id_user_ig, $id_item_type, $quantity)
{
    $quantity = (int) $quantity;

    if ($quantity <= 0)
    {
        return true;
    }

    $stmt = $conn->prepare('
        INSERT INTO items (dt_creazione, id_user_ig, id_item_type)
        VALUES (NOW(), :id_user_ig, :id_item_type)
    ');

    for ($i = 0; $i < $quantity; $i++)
    {
        $stmt->execute([
            'id_user_ig' => (int) $id_user_ig,
            'id_item_type' => (int) $id_item_type,
        ]);
    }

    return true;
}

/**
 * Deletes the oldest $quantity available (unused, unheld) rows of the item
 * type. Returns false without deleting anything if fewer than $quantity are
 * available — callers must check availability first inside the same
 * transaction to avoid a race, but this is a safe no-partial-effect guard.
 */
function animaster_economy_take_items(PDO $conn, $id_user_ig, $id_item_type, $quantity)
{
    $quantity = (int) $quantity;

    if ($quantity <= 0)
    {
        return true;
    }

    $stmt = $conn->prepare('
        SELECT id_item
        FROM items
        WHERE id_user_ig = :id_user_ig
          AND id_item_type = :id_item_type
          AND dt_used IS NULL
          AND (flg_held IS NULL OR flg_held = \'N\')
        ORDER BY id_item ASC
        LIMIT ' . (int) $quantity . '
        FOR UPDATE
    ');
    $stmt->execute([
        'id_user_ig' => (int) $id_user_ig,
        'id_item_type' => (int) $id_item_type,
    ]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) < $quantity)
    {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_delete = $conn->prepare("DELETE FROM items WHERE id_item IN ($placeholders)");
    $stmt_delete->execute($ids);

    return true;
}
