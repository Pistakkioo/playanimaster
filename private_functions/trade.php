<?php

if (!defined('ANIMASTER_TRADE_REQUEST_SECONDS'))
{
    define('ANIMASTER_TRADE_REQUEST_SECONDS', 12);
}

function animaster_trade_expire_requests($conn)
{
    $stmt = $conn->prepare('
        UPDATE trade_requests
        SET flg_status = \'X\', dt_m = NOW()
        WHERE flg_status = \'P\'
          AND dt_expires < NOW()
    ');
    $stmt->execute();
}

function animaster_trade_fetch_user($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_user_ig, display_name, gold, id_zone, flg_online
        FROM users_ig
        WHERE id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function animaster_trade_get_open_trade_id($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    $stmt = $conn->prepare('
        SELECT id_trade
        FROM trades
        WHERE flg_status = \'O\'
          AND (id_user_ig_a = :id_user_ig OR id_user_ig_b = :id_user_ig)
        ORDER BY id_trade DESC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id_trade'] : 0;
}

function animaster_trade_fetch_trade_row($conn, $id_trade)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM trades
        WHERE id_trade = :id_trade
        LIMIT 1
    ');
    $stmt->execute(['id_trade' => (int) $id_trade]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_trade_user_side($trade_row, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ((int) $trade_row['id_user_ig_a'] === $id_user_ig)
    {
        return 'a';
    }

    if ((int) $trade_row['id_user_ig_b'] === $id_user_ig)
    {
        return 'b';
    }

    return '';
}

function animaster_trade_partner_id($trade_row, $id_user_ig)
{
    $side = animaster_trade_user_side($trade_row, $id_user_ig);

    if ($side === 'a')
    {
        return (int) $trade_row['id_user_ig_b'];
    }

    if ($side === 'b')
    {
        return (int) $trade_row['id_user_ig_a'];
    }

    return 0;
}

function animaster_trade_count_available_items($conn, $id_user_ig, $id_item_type, $id_trade_exclude = 0)
{
    $sql = '
        SELECT COUNT(*) AS cnt
        FROM items i
        INNER JOIN item_types it ON it.id_item_type = i.id_item_type
        WHERE i.id_user_ig = :id_user_ig
          AND i.id_item_type = :id_item_type
          AND i.dt_used IS NULL
          AND (i.flg_held IS NULL OR i.flg_held = \'N\')
          AND it.flg_tradable = \'S\'
          AND i.id_item NOT IN (
              SELECT til.id_item
              FROM trade_item_locks til
              INNER JOIN trades t ON t.id_trade = til.id_trade
              WHERE t.flg_status = \'O\'
    ';

    $params = [
        'id_user_ig' => (int) $id_user_ig,
        'id_item_type' => (int) $id_item_type
    ];

    if ($id_trade_exclude > 0)
    {
        $sql .= ' AND t.id_trade <> :id_trade_exclude';
        $params['id_trade_exclude'] = (int) $id_trade_exclude;
    }

    $sql .= '
          )';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['cnt'] : 0;
}

function animaster_trade_fetch_offer_items($conn, $id_trade, $id_user_ig, $lang_suffix = '')
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $stmt = $conn->prepare('
        SELECT toi.id_item_type, toi.quantity,
               it.nome' . $lang_suffix . ' AS nome, it.flg_tradable
        FROM trade_offer_items toi
        INNER JOIN item_types it ON it.id_item_type = toi.id_item_type
        WHERE toi.id_trade = :id_trade
          AND toi.id_user_ig = :id_user_ig
        ORDER BY toi.id_item_type ASC
    ');
    $stmt->execute([
        'id_trade' => (int) $id_trade,
        'id_user_ig' => (int) $id_user_ig
    ]);

    $items = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $items[] = [
            'id_item_type' => (int) $row['id_item_type'],
            'quantity' => (int) $row['quantity'],
            'nome' => $row['nome']
        ];
    }

    return $items;
}

function animaster_trade_build_trade_state($conn, $trade_row, $id_user_ig, $lang_suffix = '')
{
    $side = animaster_trade_user_side($trade_row, $id_user_ig);
    $partner_id = animaster_trade_partner_id($trade_row, $id_user_ig);
    $partner = animaster_trade_fetch_user($conn, $partner_id);
    $me = animaster_trade_fetch_user($conn, $id_user_ig);

    $my_gold_offer = $side === 'a' ? (int) $trade_row['gold_a'] : (int) $trade_row['gold_b'];
    $their_gold_offer = $side === 'a' ? (int) $trade_row['gold_b'] : (int) $trade_row['gold_a'];
    $my_confirmed = $side === 'a'
        ? trim((string) $trade_row['flg_confirm_a']) === 'S'
        : trim((string) $trade_row['flg_confirm_b']) === 'S';
    $their_confirmed = $side === 'a'
        ? trim((string) $trade_row['flg_confirm_b']) === 'S'
        : trim((string) $trade_row['flg_confirm_a']) === 'S';

    return [
        'id_trade' => (int) $trade_row['id_trade'],
        'partner_id' => $partner_id,
        'partner_name' => $partner ? trim((string) $partner['display_name']) : 'Player',
        'my_gold' => $me ? (int) $me['gold'] : 0,
        'my_gold_offer' => $my_gold_offer,
        'their_gold_offer' => $their_gold_offer,
        'my_items' => animaster_trade_fetch_offer_items($conn, (int) $trade_row['id_trade'], $id_user_ig, $lang_suffix),
        'their_items' => animaster_trade_fetch_offer_items($conn, (int) $trade_row['id_trade'], $partner_id, $lang_suffix),
        'my_confirmed' => $my_confirmed,
        'their_confirmed' => $their_confirmed,
        'i_am_a' => $side === 'a'
    ];
}

function animaster_trade_send_request($conn, $id_sender, $id_target)
{
    $id_sender = (int) $id_sender;
    $id_target = (int) $id_target;

    if ($id_sender <= 0 || $id_target <= 0 || $id_sender === $id_target)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $sender = animaster_trade_fetch_user($conn, $id_sender);
    $target = animaster_trade_fetch_user($conn, $id_target);

    if (!$sender || !$target)
    {
        return ['error' => 'INVALID_USER'];
    }

    if (trim((string) $target['flg_online']) !== 'S')
    {
        return ['error' => 'OFFLINE'];
    }

    if ((int) $sender['id_zone'] !== (int) $target['id_zone'])
    {
        return ['error' => 'OFFLINE'];
    }

    if (animaster_trade_get_open_trade_id($conn, $id_sender) > 0
        || animaster_trade_get_open_trade_id($conn, $id_target) > 0)
    {
        return ['error' => 'BUSY'];
    }

    animaster_trade_expire_requests($conn);

    $stmt = $conn->prepare('
        SELECT id_trade_request
        FROM trade_requests
        WHERE flg_status = \'P\'
          AND dt_expires >= NOW()
          AND (
              (id_user_ig_sender = :sender AND id_user_ig_target = :target)
              OR (id_user_ig_sender = :sender2 AND id_user_ig_target = :target2)
          )
        LIMIT 1
    ');
    $stmt->execute([
        'sender' => $id_sender,
        'target' => $id_target,
        'sender2' => $id_sender,
        'target2' => $id_target
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC))
    {
        return ['error' => 'ALREADY_PENDING'];
    }

    $stmt = $conn->prepare('
        INSERT INTO trade_requests (
            id_user_ig_sender, id_user_ig_target, dt_c, dt_expires, flg_status
        ) VALUES (
            :sender, :target, NOW(), DATE_ADD(NOW(), INTERVAL :seconds SECOND), \'P\'
        )
    ');
    $stmt->execute([
        'sender' => $id_sender,
        'target' => $id_target,
        'seconds' => ANIMASTER_TRADE_REQUEST_SECONDS
    ]);

    return [
        'ok' => true,
        'id_trade_request' => (int) $conn->lastInsertId(),
        'expires_seconds' => ANIMASTER_TRADE_REQUEST_SECONDS
    ];
}

function animaster_trade_request_row_to_api($row, $viewer_id)
{
    $viewer_id = (int) $viewer_id;
    $is_incoming = (int) $row['id_user_ig_target'] === $viewer_id;
    $other_id = $is_incoming ? (int) $row['id_user_ig_sender'] : (int) $row['id_user_ig_target'];
    $expires_at = $row['dt_expires'];
    $seconds_left = max(0, strtotime($expires_at) - time());

    return [
        'id_trade_request' => (int) $row['id_trade_request'],
        'incoming' => $is_incoming,
        'other_id' => $other_id,
        'other_name' => $row['other_name'] ?? 'Player',
        'dt_expires' => $expires_at,
        'seconds_left' => $seconds_left
    ];
}

function animaster_trade_poll($conn, $id_user_ig, $lang_suffix = '')
{
    $id_user_ig = (int) $id_user_ig;

    animaster_trade_expire_requests($conn);

    $incoming = [];
    $stmt = $conn->prepare('
        SELECT tr.*, ui.display_name AS other_name
        FROM trade_requests tr
        INNER JOIN users_ig ui ON ui.id_user_ig = tr.id_user_ig_sender
        WHERE tr.id_user_ig_target = :id_user_ig
          AND tr.flg_status = \'P\'
          AND tr.dt_expires >= NOW()
        ORDER BY tr.id_trade_request ASC
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $incoming[] = animaster_trade_request_row_to_api($row, $id_user_ig);
    }

    $outgoing = null;
    $stmt = $conn->prepare('
        SELECT tr.*, ui.display_name AS other_name
        FROM trade_requests tr
        INNER JOIN users_ig ui ON ui.id_user_ig = tr.id_user_ig_target
        WHERE tr.id_user_ig_sender = :id_user_ig
          AND tr.flg_status = \'P\'
          AND tr.dt_expires >= NOW()
        ORDER BY tr.id_trade_request DESC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row)
    {
        $outgoing = animaster_trade_request_row_to_api($row, $id_user_ig);
    }

    $active_trade = null;
    $trade_id = animaster_trade_get_open_trade_id($conn, $id_user_ig);

    if ($trade_id > 0)
    {
        $trade_row = animaster_trade_fetch_trade_row($conn, $trade_id);

        if ($trade_row && trim((string) $trade_row['flg_status']) === 'O')
        {
            $active_trade = animaster_trade_build_trade_state($conn, $trade_row, $id_user_ig, $lang_suffix);
        }
    }

    return [
        'ok' => true,
        'incoming_requests' => $incoming,
        'outgoing_request' => $outgoing,
        'active_trade' => $active_trade
    ];
}

function animaster_trade_respond_request($conn, $id_user_ig, $id_trade_request, $accept)
{
    $id_user_ig = (int) $id_user_ig;
    $id_trade_request = (int) $id_trade_request;
    $accept = $accept ? true : false;

    animaster_trade_expire_requests($conn);

    $stmt = $conn->prepare('
        SELECT *
        FROM trade_requests
        WHERE id_trade_request = :id
        LIMIT 1
    ');
    $stmt->execute(['id' => $id_trade_request]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request || trim((string) $request['flg_status']) !== 'P')
    {
        return ['error' => 'NOT_FOUND'];
    }

    if ((int) $request['id_user_ig_target'] !== $id_user_ig)
    {
        return ['error' => 'FORBIDDEN'];
    }

    if (strtotime($request['dt_expires']) < time())
    {
        return ['error' => 'EXPIRED'];
    }

    if (!$accept)
    {
        $stmt = $conn->prepare('
            UPDATE trade_requests
            SET flg_status = \'D\', dt_m = NOW()
            WHERE id_trade_request = :id
        ');
        $stmt->execute(['id' => $id_trade_request]);

        return ['ok' => true, 'accepted' => false];
    }

    $sender_id = (int) $request['id_user_ig_sender'];
    $target_id = (int) $request['id_user_ig_target'];

    if (animaster_trade_get_open_trade_id($conn, $sender_id) > 0
        || animaster_trade_get_open_trade_id($conn, $target_id) > 0)
    {
        return ['error' => 'BUSY'];
    }

    $conn->beginTransaction();

    try
    {
        $stmt = $conn->prepare('
            UPDATE trade_requests
            SET flg_status = \'A\', dt_m = NOW()
            WHERE id_trade_request = :id AND flg_status = \'P\'
        ');
        $stmt->execute(['id' => $id_trade_request]);

        if ($stmt->rowCount() <= 0)
        {
            throw new RuntimeException('REQUEST_TAKEN');
        }

        $stmt = $conn->prepare('
            INSERT INTO trades (
                id_trade_request, id_user_ig_a, id_user_ig_b,
                gold_a, gold_b, flg_confirm_a, flg_confirm_b, flg_status, dt_c
            ) VALUES (
                :id_trade_request, :id_a, :id_b,
                0, 0, \'N\', \'N\', \'O\', NOW()
            )
        ');
        $stmt->execute([
            'id_trade_request' => $id_trade_request,
            'id_a' => $sender_id,
            'id_b' => $target_id
        ]);

        $id_trade = (int) $conn->lastInsertId();
        $conn->commit();

        return [
            'ok' => true,
            'accepted' => true,
            'id_trade' => $id_trade
        ];
    }
    catch (Throwable $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        return ['error' => 'RESPOND_FAILED'];
    }
}

function animaster_trade_parse_offer_items($raw)
{
    if (!is_array($raw))
    {
        return [];
    }

    $items = [];

    foreach ($raw as $row)
    {
        if (!is_array($row))
        {
            continue;
        }

        $id_item_type = isset($row['id_item_type']) ? (int) $row['id_item_type'] : 0;
        $quantity = isset($row['quantity']) ? (int) $row['quantity'] : 0;

        if ($id_item_type <= 0 || $quantity <= 0)
        {
            continue;
        }

        $items[] = [
            'id_item_type' => $id_item_type,
            'quantity' => min($quantity, 999)
        ];
    }

    return $items;
}

function animaster_trade_update_offer($conn, $id_user_ig, $id_trade, $gold, $items_raw)
{
    $id_user_ig = (int) $id_user_ig;
    $id_trade = (int) $id_trade;
    $gold = max(0, (int) $gold);

    $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

    if (!$trade_row || trim((string) $trade_row['flg_status']) !== 'O')
    {
        return ['error' => 'NOT_FOUND'];
    }

    $side = animaster_trade_user_side($trade_row, $id_user_ig);

    if ($side === '')
    {
        return ['error' => 'FORBIDDEN'];
    }

    $confirmed = $side === 'a'
        ? trim((string) $trade_row['flg_confirm_a']) === 'S'
        : trim((string) $trade_row['flg_confirm_b']) === 'S';

    if ($confirmed)
    {
        return ['error' => 'LOCKED'];
    }

    $me = animaster_trade_fetch_user($conn, $id_user_ig);

    if (!$me || (int) $me['gold'] < $gold)
    {
        return ['error' => 'NOT_ENOUGH_GOLD'];
    }

    $items = animaster_trade_parse_offer_items($items_raw);

    foreach ($items as $item)
    {
        $available = animaster_trade_count_available_items(
            $conn,
            $id_user_ig,
            $item['id_item_type'],
            $id_trade
        );

        if ($available < $item['quantity'])
        {
            return ['error' => 'NOT_ENOUGH_ITEMS'];
        }
    }

    $conn->beginTransaction();

    try
    {
        if ($side === 'a')
        {
            $stmt = $conn->prepare('UPDATE trades SET gold_a = :gold, dt_m = NOW() WHERE id_trade = :id_trade');
        }
        else
        {
            $stmt = $conn->prepare('UPDATE trades SET gold_b = :gold, dt_m = NOW() WHERE id_trade = :id_trade');
        }

        $stmt->execute(['gold' => $gold, 'id_trade' => $id_trade]);

        $stmt = $conn->prepare('
            DELETE FROM trade_offer_items
            WHERE id_trade = :id_trade AND id_user_ig = :id_user_ig
        ');
        $stmt->execute([
            'id_trade' => $id_trade,
            'id_user_ig' => $id_user_ig
        ]);

        $insert = $conn->prepare('
            INSERT INTO trade_offer_items (id_trade, id_user_ig, id_item_type, quantity, dt_c)
            VALUES (:id_trade, :id_user_ig, :id_item_type, :quantity, NOW())
        ');

        foreach ($items as $item)
        {
            $insert->execute([
                'id_trade' => $id_trade,
                'id_user_ig' => $id_user_ig,
                'id_item_type' => $item['id_item_type'],
                'quantity' => $item['quantity']
            ]);
        }

        $conn->commit();
    }
    catch (Throwable $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        return ['error' => 'UPDATE_FAILED'];
    }

    $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

    return [
        'ok' => true,
        'trade' => animaster_trade_build_trade_state($conn, $trade_row, $id_user_ig)
    ];
}

function animaster_trade_lock_side_items($conn, $id_trade, $id_user_ig)
{
    $offer_items = animaster_trade_fetch_offer_items($conn, $id_trade, $id_user_ig);

    $stmt_lock = $conn->prepare('
        INSERT INTO trade_item_locks (id_trade, id_item, id_user_ig, id_item_type, dt_c)
        VALUES (:id_trade, :id_item, :id_user_ig, :id_item_type, NOW())
    ');

    foreach ($offer_items as $offer)
    {
        $limit = (int) $offer['quantity'];
        $stmt = $conn->prepare('
            SELECT i.id_item
            FROM items i
            INNER JOIN item_types it ON it.id_item_type = i.id_item_type
            WHERE i.id_user_ig = :id_user_ig
              AND i.id_item_type = :id_item_type
              AND i.dt_used IS NULL
              AND (i.flg_held IS NULL OR i.flg_held = \'N\')
              AND it.flg_tradable = \'S\'
              AND i.id_item NOT IN (
                  SELECT til.id_item FROM trade_item_locks til
                  INNER JOIN trades t ON t.id_trade = til.id_trade
                  WHERE t.flg_status = \'O\'
              )
            ORDER BY i.dt_creazione ASC
            LIMIT ' . $limit . '
        ');
        $stmt->execute([
            'id_user_ig' => (int) $id_user_ig,
            'id_item_type' => (int) $offer['id_item_type']
        ]);

        $locked = 0;

        while ($item_row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $stmt_lock->execute([
                'id_trade' => (int) $id_trade,
                'id_item' => (int) $item_row['id_item'],
                'id_user_ig' => (int) $id_user_ig,
                'id_item_type' => (int) $offer['id_item_type']
            ]);
            $locked++;
        }

        if ($locked < (int) $offer['quantity'])
        {
            return false;
        }
    }

    return true;
}

function animaster_trade_execute($conn, $trade_row)
{
    $id_trade = (int) $trade_row['id_trade'];
    $id_a = (int) $trade_row['id_user_ig_a'];
    $id_b = (int) $trade_row['id_user_ig_b'];
    $gold_a = (int) $trade_row['gold_a'];
    $gold_b = (int) $trade_row['gold_b'];

    $user_a = animaster_trade_fetch_user($conn, $id_a);
    $user_b = animaster_trade_fetch_user($conn, $id_b);

    if (!$user_a || !$user_b)
    {
        return false;
    }

    if ((int) $user_a['gold'] < $gold_a || (int) $user_b['gold'] < $gold_b)
    {
        return false;
    }

    $stmt = $conn->prepare('
        UPDATE items i
        INNER JOIN trade_item_locks til ON til.id_item = i.id_item
        SET i.id_user_ig = CASE
            WHEN til.id_user_ig = :id_a THEN :id_b
            WHEN til.id_user_ig = :id_b THEN :id_a
            ELSE i.id_user_ig
        END,
        i.dt_modifica = NOW()
        WHERE til.id_trade = :id_trade
    ');
    $stmt->execute([
        'id_a' => $id_a,
        'id_b' => $id_b,
        'id_trade' => $id_trade
    ]);

    if ($gold_a > 0)
    {
        $stmt = $conn->prepare('UPDATE users_ig SET gold = gold - :amt WHERE id_user_ig = :id AND gold >= :amt2');
        $stmt->execute(['amt' => $gold_a, 'id' => $id_a, 'amt2' => $gold_a]);

        if ($stmt->rowCount() <= 0)
        {
            return false;
        }

        $stmt = $conn->prepare('UPDATE users_ig SET gold = gold + :amt WHERE id_user_ig = :id');
        $stmt->execute(['amt' => $gold_a, 'id' => $id_b]);
    }

    if ($gold_b > 0)
    {
        $stmt = $conn->prepare('UPDATE users_ig SET gold = gold - :amt WHERE id_user_ig = :id AND gold >= :amt2');
        $stmt->execute(['amt' => $gold_b, 'id' => $id_b, 'amt2' => $gold_b]);

        if ($stmt->rowCount() <= 0)
        {
            return false;
        }

        $stmt = $conn->prepare('UPDATE users_ig SET gold = gold + :amt WHERE id_user_ig = :id');
        $stmt->execute(['amt' => $gold_b, 'id' => $id_a]);
    }

    $stmt = $conn->prepare('
        UPDATE trades
        SET flg_status = \'C\', dt_m = NOW()
        WHERE id_trade = :id_trade AND flg_status = \'O\'
    ');
    $stmt->execute(['id_trade' => $id_trade]);

    $stmt = $conn->prepare('DELETE FROM trade_item_locks WHERE id_trade = :id_trade');
    $stmt->execute(['id_trade' => $id_trade]);

    $stmt = $conn->prepare('DELETE FROM trade_offer_items WHERE id_trade = :id_trade');
    $stmt->execute(['id_trade' => $id_trade]);

    return true;
}

function animaster_trade_cancel($conn, $id_user_ig, $id_trade)
{
    $id_user_ig = (int) $id_user_ig;
    $id_trade = (int) $id_trade;

    $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

    if (!$trade_row || trim((string) $trade_row['flg_status']) !== 'O')
    {
        return ['error' => 'NOT_FOUND'];
    }

    if (animaster_trade_user_side($trade_row, $id_user_ig) === '')
    {
        return ['error' => 'FORBIDDEN'];
    }

    $conn->beginTransaction();

    try
    {
        $stmt = $conn->prepare('
            UPDATE trades
            SET flg_status = \'X\', dt_m = NOW()
            WHERE id_trade = :id_trade AND flg_status = \'O\'
        ');
        $stmt->execute(['id_trade' => $id_trade]);

        $stmt = $conn->prepare('DELETE FROM trade_item_locks WHERE id_trade = :id_trade');
        $stmt->execute(['id_trade' => $id_trade]);

        $stmt = $conn->prepare('DELETE FROM trade_offer_items WHERE id_trade = :id_trade');
        $stmt->execute(['id_trade' => $id_trade]);

        $conn->commit();
    }
    catch (Throwable $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        return ['error' => 'CANCEL_FAILED'];
    }

    return ['ok' => true];
}

function animaster_trade_confirm($conn, $id_user_ig, $id_trade, $lang_suffix = '')
{
    $id_user_ig = (int) $id_user_ig;
    $id_trade = (int) $id_trade;

    $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

    if (!$trade_row || trim((string) $trade_row['flg_status']) !== 'O')
    {
        return ['error' => 'NOT_FOUND'];
    }

    $side = animaster_trade_user_side($trade_row, $id_user_ig);

    if ($side === '')
    {
        return ['error' => 'FORBIDDEN'];
    }

    $already = $side === 'a'
        ? trim((string) $trade_row['flg_confirm_a']) === 'S'
        : trim((string) $trade_row['flg_confirm_b']) === 'S';

    if ($already)
    {
        return ['ok' => true, 'trade' => animaster_trade_build_trade_state($conn, $trade_row, $id_user_ig, $lang_suffix)];
    }

    $gold = $side === 'a' ? (int) $trade_row['gold_a'] : (int) $trade_row['gold_b'];
    $me = animaster_trade_fetch_user($conn, $id_user_ig);

    if (!$me || (int) $me['gold'] < $gold)
    {
        return ['error' => 'NOT_ENOUGH_GOLD'];
    }

    $offer_items = animaster_trade_fetch_offer_items($conn, $id_trade, $id_user_ig);

    foreach ($offer_items as $item)
    {
        $available = animaster_trade_count_available_items(
            $conn,
            $id_user_ig,
            $item['id_item_type'],
            $id_trade
        );

        if ($available < $item['quantity'])
        {
            return ['error' => 'NOT_ENOUGH_ITEMS'];
        }
    }

    $conn->beginTransaction();

    try
    {
        if (!animaster_trade_lock_side_items($conn, $id_trade, $id_user_ig))
        {
            throw new RuntimeException('LOCK_FAILED');
        }

        if ($side === 'a')
        {
            $stmt = $conn->prepare('
                UPDATE trades SET flg_confirm_a = \'S\', dt_m = NOW()
                WHERE id_trade = :id_trade AND flg_status = \'O\'
            ');
        }
        else
        {
            $stmt = $conn->prepare('
                UPDATE trades SET flg_confirm_b = \'S\', dt_m = NOW()
                WHERE id_trade = :id_trade AND flg_status = \'O\'
            ');
        }

        $stmt->execute(['id_trade' => $id_trade]);

        $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

        $both = trim((string) $trade_row['flg_confirm_a']) === 'S'
            && trim((string) $trade_row['flg_confirm_b']) === 'S';

        if ($both)
        {
            if (!animaster_trade_execute($conn, $trade_row))
            {
                throw new RuntimeException('EXECUTE_FAILED');
            }

            $conn->commit();

            return ['ok' => true, 'completed' => true];
        }

        $conn->commit();
    }
    catch (Throwable $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        return ['error' => 'CONFIRM_FAILED'];
    }

    $trade_row = animaster_trade_fetch_trade_row($conn, $id_trade);

    return [
        'ok' => true,
        'completed' => false,
        'trade' => animaster_trade_build_trade_state($conn, $trade_row, $id_user_ig, $lang_suffix)
    ];
}
