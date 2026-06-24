<?php

require_once __DIR__ . '/chat_word_filter.php';

if (!defined('ANIMASTER_CHAT_LOCAL_RADIUS'))
{
    define('ANIMASTER_CHAT_LOCAL_RADIUS', 30);
}

if (!defined('ANIMASTER_CHAT_MAX_LENGTH'))
{
    define('ANIMASTER_CHAT_MAX_LENGTH', 160);
}

function animaster_chat_normalize_channel($channel)
{
    $channel = trim((string) $channel);

    if (in_array($channel, ['L', '@', '!', '$', '%', '#', '*'], true))
    {
        return $channel;
    }

    return '';
}

function animaster_chat_parse_input($raw)
{
    $raw = trim((string) $raw);

    if ($raw === '')
    {
        return ['error' => 'EMPTY_MESSAGE'];
    }

    if (mb_strlen($raw) > ANIMASTER_CHAT_MAX_LENGTH)
    {
        return ['error' => 'TOO_LONG'];
    }

    $first = $raw[0];

    if ($first === '@')
    {
        $rest = ltrim(substr($raw, 1));

        if ($rest === '')
        {
            return ['error' => 'WHISPER_INVALID'];
        }

        $space = strpos($rest, ' ');

        if ($space === false)
        {
            return ['error' => 'WHISPER_NO_MESSAGE'];
        }

        $target = trim(substr($rest, 0, $space));
        $body = trim(substr($rest, $space + 1));

        if ($target === '' || $body === '')
        {
            return ['error' => 'WHISPER_INVALID'];
        }

        if (mb_strlen($body) > ANIMASTER_CHAT_MAX_LENGTH)
        {
            return ['error' => 'TOO_LONG'];
        }

        return [
            'channel' => '@',
            'target_name' => $target,
            'body' => $body
        ];
    }

    if (in_array($first, ['!', '$', '%', '#', '*'], true))
    {
        $body = trim(substr($raw, 1));

        if ($body === '')
        {
            return ['error' => 'EMPTY_MESSAGE'];
        }

        if (mb_strlen($body) > ANIMASTER_CHAT_MAX_LENGTH)
        {
            return ['error' => 'TOO_LONG'];
        }

        return [
            'channel' => $first,
            'body' => $body
        ];
    }

    return [
        'channel' => 'L',
        'body' => $raw
    ];
}

function animaster_chat_fetch_sender($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT UI.id_user_ig
              ,UI.display_name
              ,UI.id_zone
              ,UI.position_x
              ,UI.position_y
              ,UI.position_z
              ,UI.id_clan
              ,UI.id_party
              ,C.id_alliance
        FROM users_ig UI
        LEFT JOIN clans C ON C.id_clan = UI.id_clan
        WHERE UI.id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? $row : null;
}

function animaster_chat_find_whisper_target($conn, $target_name, $exclude_id_user_ig)
{
    $target_name = trim((string) $target_name);

    if ($target_name === '')
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_user_ig, display_name, flg_online
        FROM users_ig
        WHERE display_name = :display_name
          AND id_user_ig != :exclude_id
        LIMIT 1
    ');
    $stmt->execute([
        'display_name' => $target_name,
        'exclude_id' => (int) $exclude_id_user_ig
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_chat_find_active_global_pass($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_chat_global_pass, messages_left, dt_expires
        FROM chat_global_passes
        WHERE id_user_ig = :id_user_ig
          AND flg_active = \'S\'
          AND (messages_left IS NULL OR messages_left > 0)
          AND (dt_expires IS NULL OR dt_expires > NOW())
        ORDER BY id_chat_global_pass DESC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_chat_consume_global_pass($conn, $pass_row)
{
    if (!$pass_row || empty($pass_row['id_chat_global_pass']))
    {
        return;
    }

    if ($pass_row['messages_left'] === null)
    {
        return;
    }

    $messages_left = (int) $pass_row['messages_left'] - 1;

    if ($messages_left <= 0)
    {
        $stmt = $conn->prepare('
            UPDATE chat_global_passes
            SET messages_left = 0, flg_active = \'N\', dt_m = NOW()
            WHERE id_chat_global_pass = :id
        ');
        $stmt->execute(['id' => (int) $pass_row['id_chat_global_pass']]);
        return;
    }

    $stmt = $conn->prepare('
        UPDATE chat_global_passes
        SET messages_left = :messages_left, dt_m = NOW()
        WHERE id_chat_global_pass = :id
    ');
    $stmt->execute([
        'messages_left' => $messages_left,
        'id' => (int) $pass_row['id_chat_global_pass']
    ]);
}

function animaster_chat_send($conn, $id_user_ig, $raw_message)
{
    $parsed = animaster_chat_parse_input($raw_message);

    if (!empty($parsed['error']))
    {
        return $parsed;
    }

    $sender = animaster_chat_fetch_sender($conn, $id_user_ig);

    if (!$sender)
    {
        return ['error' => 'INVALID_SENDER'];
    }

    $channel = $parsed['channel'];
    $body = animaster_chat_word_filter_apply($conn, $parsed['body']);
    $display_name = trim((string) $sender['display_name']);

    if ($display_name === '')
    {
        $display_name = 'Player';
    }

    $id_zone = (int) $sender['id_zone'];
    $id_clan = (int) $sender['id_clan'];
    $id_party = (int) $sender['id_party'];
    $id_alliance = (int) $sender['id_alliance'];
    $pos_x = (float) $sender['position_x'];
    $pos_y = (float) $sender['position_y'];
    $pos_z = (float) $sender['position_z'];

    $id_target = null;
    $target_display_name = null;
    $flg_delivered = 'S';
    $id_global_pass = null;

    if ($channel === '@')
    {
        $target = animaster_chat_find_whisper_target($conn, $parsed['target_name'], $id_user_ig);

        if (!$target)
        {
            return ['error' => 'WHISPER_NOT_FOUND'];
        }

        if (trim((string) $target['flg_online']) !== 'S')
        {
            return ['error' => 'WHISPER_OFFLINE'];
        }

        $id_target = (int) $target['id_user_ig'];
        $target_display_name = trim((string) $target['display_name']);
    }
    elseif ($channel === '$')
    {
        if ($id_clan <= 0)
        {
            return ['error' => 'NO_CLAN'];
        }
    }
    elseif ($channel === '%')
    {
        if ($id_clan <= 0 || $id_alliance <= 0)
        {
            return ['error' => 'NO_ALLIANCE'];
        }
    }
    elseif ($channel === '#')
    {
        if ($id_party <= 0)
        {
            return ['error' => 'NO_PARTY'];
        }
    }
    elseif ($channel === '*')
    {
        $pass = animaster_chat_find_active_global_pass($conn, $id_user_ig);

        if (!$pass)
        {
            return ['error' => 'NO_GLOBAL_PASS'];
        }

        $id_global_pass = (int) $pass['id_chat_global_pass'];
    }

    $stmt = $conn->prepare('
        INSERT INTO chat_messages (
            dt_c,
            id_user_ig_sender,
            sender_display_name,
            channel,
            message_text,
            id_zone,
            origin_pos_x,
            origin_pos_y,
            origin_pos_z,
            id_clan,
            id_alliance,
            id_party,
            id_user_ig_target,
            target_display_name,
            id_chat_global_pass,
            flg_delivered
        ) VALUES (
            NOW(),
            :id_user_ig_sender,
            :sender_display_name,
            :channel,
            :message_text,
            :id_zone,
            :origin_pos_x,
            :origin_pos_y,
            :origin_pos_z,
            :id_clan,
            :id_alliance,
            :id_party,
            :id_user_ig_target,
            :target_display_name,
            :id_chat_global_pass,
            :flg_delivered
        )
    ');

    $stmt->execute([
        'id_user_ig_sender' => (int) $id_user_ig,
        'sender_display_name' => $display_name,
        'channel' => $channel,
        'message_text' => $body,
        'id_zone' => $channel === 'L' || $channel === '!' ? $id_zone : null,
        'origin_pos_x' => $channel === 'L' ? $pos_x : null,
        'origin_pos_y' => $channel === 'L' ? $pos_y : null,
        'origin_pos_z' => $channel === 'L' ? $pos_z : null,
        'id_clan' => $channel === '$' ? $id_clan : null,
        'id_alliance' => $channel === '%' ? $id_alliance : null,
        'id_party' => $channel === '#' ? $id_party : null,
        'id_user_ig_target' => $id_target,
        'target_display_name' => $target_display_name,
        'id_chat_global_pass' => $id_global_pass,
        'flg_delivered' => $flg_delivered
    ]);

    $message_id = (int) $conn->lastInsertId();

    if ($channel === '*' && $id_global_pass)
    {
        $pass = animaster_chat_find_active_global_pass($conn, $id_user_ig);

        if ($pass)
        {
            animaster_chat_consume_global_pass($conn, $pass);
        }
    }

    return [
        'ok' => true,
        'message' => animaster_chat_row_to_api([
            'id_chat_message' => $message_id,
            'dt_c' => date('Y-m-d H:i:s'),
            'id_user_ig_sender' => $id_user_ig,
            'sender_display_name' => $display_name,
            'channel' => $channel,
            'message_text' => $body,
            'id_zone' => $channel === 'L' || $channel === '!' ? $id_zone : null,
            'origin_pos_x' => $channel === 'L' ? $pos_x : null,
            'origin_pos_y' => $channel === 'L' ? $pos_y : null,
            'origin_pos_z' => $channel === 'L' ? $pos_z : null,
            'id_clan' => $channel === '$' ? $id_clan : null,
            'id_alliance' => $channel === '%' ? $id_alliance : null,
            'id_party' => $channel === '#' ? $id_party : null,
            'id_user_ig_target' => $id_target,
            'target_display_name' => $target_display_name,
            'flg_delivered' => $flg_delivered
        ])
    ];
}

function animaster_chat_distance_sq($x1, $z1, $x2, $z2)
{
    $dx = (float) $x1 - (float) $x2;
    $dz = (float) $z1 - (float) $z2;

    return ($dx * $dx) + ($dz * $dz);
}

function animaster_chat_message_visible_to_reader($row, $reader)
{
    $channel = animaster_chat_normalize_channel($row['channel']);

    if ($channel === '')
    {
        return false;
    }

    $reader_id = (int) $reader['id_user_ig'];
    $reader_zone = (int) $reader['id_zone'];
    $reader_clan = (int) $reader['id_clan'];
    $reader_party = (int) $reader['id_party'];
    $reader_alliance = (int) $reader['id_alliance'];
    $reader_x = (float) $reader['position_x'];
    $reader_z = (float) $reader['position_z'];

    if ($channel === 'L')
    {
        if ((int) $row['id_zone'] !== $reader_zone)
        {
            return false;
        }

        $radius = ANIMASTER_CHAT_LOCAL_RADIUS;
        $dist = animaster_chat_distance_sq(
            $reader_x,
            $reader_z,
            $row['origin_pos_x'],
            $row['origin_pos_z']
        );

        return $dist <= ($radius * $radius);
    }

    if ($channel === '!')
    {
        return (int) $row['id_zone'] === $reader_zone;
    }

    if ($channel === '$')
    {
        return $reader_clan > 0 && (int) $row['id_clan'] === $reader_clan;
    }

    if ($channel === '%')
    {
        return $reader_alliance > 0 && (int) $row['id_alliance'] === $reader_alliance;
    }

    if ($channel === '#')
    {
        return $reader_party > 0 && (int) $row['id_party'] === $reader_party;
    }

    if ($channel === '*')
    {
        return true;
    }

    if ($channel === '@')
    {
        $sender_id = (int) $row['id_user_ig_sender'];
        $target_id = (int) $row['id_user_ig_target'];

        return $sender_id === $reader_id || $target_id === $reader_id;
    }

    return false;
}

function animaster_chat_row_to_api(array $row)
{
    return [
        'id' => (int) $row['id_chat_message'],
        'dt' => $row['dt_c'],
        'channel' => animaster_chat_normalize_channel($row['channel']),
        'sender_id' => (int) $row['id_user_ig_sender'],
        'sender' => $row['sender_display_name'],
        'text' => $row['message_text'],
        'target' => $row['target_display_name'],
        'id_zone' => isset($row['id_zone']) ? (int) $row['id_zone'] : 0
    ];
}

function animaster_chat_normalize_since_dt($since_dt)
{
    $since_dt = trim((string) $since_dt);

    if ($since_dt === '')
    {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $since_dt);

    if (!$dt)
    {
        return null;
    }

    return $dt->format('Y-m-d H:i:s');
}

function animaster_chat_poll($conn, $id_user_ig, $since_id, $pos_x, $pos_z, $id_zone, $since_dt = null)
{
    $since_id = (int) $since_id;
    $since_dt = animaster_chat_normalize_since_dt($since_dt);

    $reader = animaster_chat_fetch_sender($conn, $id_user_ig);

    if (!$reader)
    {
        return ['error' => 'INVALID_READER'];
    }

    if ($id_zone > 0)
    {
        $reader['id_zone'] = (int) $id_zone;
    }

    if ($pos_x !== null && $pos_z !== null)
    {
        $reader['position_x'] = (float) $pos_x;
        $reader['position_z'] = (float) $pos_z;
    }

    $sql = '
        SELECT id_chat_message, dt_c, id_user_ig_sender, sender_display_name, channel,
               message_text, id_zone, origin_pos_x, origin_pos_y, origin_pos_z,
               id_clan, id_alliance, id_party, id_user_ig_target, target_display_name, flg_delivered
        FROM chat_messages
        WHERE id_chat_message > :since_id';

    $params = ['since_id' => $since_id];

    if ($since_dt !== null)
    {
        $sql .= ' AND dt_c > :since_dt';
        $params['since_dt'] = $since_dt;
    }

    $sql .= '
        ORDER BY id_chat_message ASC
        LIMIT 200';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $messages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        if (!animaster_chat_message_visible_to_reader($row, $reader))
        {
            continue;
        }

        $messages[] = animaster_chat_row_to_api($row);
    }

    return [
        'ok' => true,
        'messages' => $messages,
        'last_id' => !empty($messages) ? (int) $messages[count($messages) - 1]['id'] : $since_id
    ];
}
