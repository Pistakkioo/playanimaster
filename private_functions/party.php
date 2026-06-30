<?php

if (!defined('ANIMASTER_PARTY_INVITE_SECONDS'))
{
    define('ANIMASTER_PARTY_INVITE_SECONDS', 30);
}

if (!defined('ANIMASTER_PARTY_MAX_MEMBERS'))
{
    define('ANIMASTER_PARTY_MAX_MEMBERS', 4);
}

if (!defined('ANIMASTER_PARTY_INTERACT_RADIUS'))
{
    define('ANIMASTER_PARTY_INTERACT_RADIUS', 10);
}

function animaster_party_expire_invites($conn)
{
    $stmt = $conn->prepare('
        UPDATE party_invites
        SET flg_status = \'X\', dt_m = NOW()
        WHERE flg_status = \'P\'
          AND dt_expires < NOW()
    ');
    $stmt->execute();
}

function animaster_party_fetch_user($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_user_ig, display_name, id_zone, flg_online,
               position_x, position_z, flg_busy, flg_battling, flg_trading, id_party
        FROM users_ig
        WHERE id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_distance($x1, $z1, $x2, $z2)
{
    $dx = (float) $x1 - (float) $x2;
    $dz = (float) $z1 - (float) $z2;

    return sqrt(($dx * $dx) + ($dz * $dz));
}

function animaster_party_get_party_id($conn, $id_user_ig)
{
    $user = animaster_party_fetch_user($conn, (int) $id_user_ig);

    if (!$user)
    {
        return 0;
    }

    return (int) ($user['id_party'] ?? 0);
}

function animaster_party_fetch_party_row($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM parties
        WHERE id_party = :id_party
        LIMIT 1
    ');
    $stmt->execute([':id_party' => (int) $id_party]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_member_count($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS cnt
        FROM party_members
        WHERE id_party = :id_party
    ');
    $stmt->execute([':id_party' => (int) $id_party]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['cnt'] : 0;
}

function animaster_party_is_leader($conn, $id_party, $id_user_ig)
{
    $party = animaster_party_fetch_party_row($conn, $id_party);

    if (!$party)
    {
        return false;
    }

    return (int) $party['id_user_ig_leader'] === (int) $id_user_ig;
}

function animaster_party_is_member($conn, $id_party, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_party_member
        FROM party_members
        WHERE id_party = :id_party
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_party' => (int) $id_party,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function animaster_party_set_user_party($conn, $id_user_ig, $id_party)
{
    $stmt = $conn->prepare('
        UPDATE users_ig
        SET id_party = :id_party,
            dt_modifica = NOW()
        WHERE id_user_ig = :id_user_ig
    ');
    $stmt->execute([
        ':id_user_ig' => (int) $id_user_ig,
        ':id_party' => $id_party > 0 ? (int) $id_party : null
    ]);
}

function animaster_party_fetch_lead_animal($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT A.id_animal, A.lvl, A.current_hp, A.max_hp, A.id_species, A.id_element,
               L.species AS species_key,
               E.color AS element_color
        FROM animals A
        INNER JOIN species L ON L.id_species = A.id_species
        LEFT JOIN elements E ON E.id_element = A.id_element
        WHERE A.id_user_ig = :id_user_ig
          AND A.team_position > 0
          AND COALESCE(A.current_hp, 0) > 0
        ORDER BY A.team_position ASC
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    $max_hp = (int) ($row['max_hp'] ?? 0);
    $current_hp = $row['current_hp'];

    if ($current_hp === null || $current_hp === '')
    {
        $current_hp = $max_hp;
    }

    $current_hp = (int) $current_hp;

    if ($max_hp <= 0)
    {
        $max_hp = max(1, $current_hp);
    }

    return [
        'id_animal' => (int) $row['id_animal'],
        'id_species' => (int) $row['id_species'],
        'species_key' => (string) ($row['species_key'] ?? ''),
        'id_element' => (int) ($row['id_element'] ?? 0),
        'element_color' => (string) ($row['element_color'] ?? ''),
        'lvl' => (int) ($row['lvl'] ?? 1),
        'current_hp' => max(0, $current_hp),
        'max_hp' => $max_hp
    ];
}

function animaster_party_fetch_members($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT pm.id_user_ig, pm.dt_joined,
               ui.display_name, ui.flg_online, ui.id_zone,
               ui.position_x, ui.position_z,
               p.id_user_ig_leader,
               pc.code AS player_class_code
        FROM party_members pm
        INNER JOIN users_ig ui ON ui.id_user_ig = pm.id_user_ig
        INNER JOIN parties p ON p.id_party = pm.id_party
        LEFT JOIN player_classes pc ON pc.id_player_class = ui.id_player_class
        WHERE pm.id_party = :id_party
        ORDER BY (pm.id_user_ig = p.id_user_ig_leader) DESC, pm.dt_joined ASC
    ');
    $stmt->execute([':id_party' => (int) $id_party]);

    $members = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $id_user_ig = (int) $row['id_user_ig'];

        $members[] = [
            'id_user_ig' => $id_user_ig,
            'display_name' => (string) ($row['display_name'] ?: 'Player'),
            'is_leader' => $id_user_ig === (int) $row['id_user_ig_leader'],
            'flg_online' => trim((string) $row['flg_online']) === 'S',
            'id_zone' => (int) $row['id_zone'],
            'position_x' => $row['position_x'],
            'position_z' => $row['position_z'],
            'player_class_code' => (string) ($row['player_class_code'] ?? ''),
            'lead_animal' => animaster_party_fetch_lead_animal($conn, $id_user_ig)
        ];
    }

    return $members;
}

function animaster_party_disband_if_solo_leader($conn, $id_party)
{
    $id_party = (int) $id_party;

    if ($id_party <= 0)
    {
        return false;
    }

    if (animaster_party_member_count($conn, $id_party) !== 1)
    {
        return false;
    }

    if (!animaster_party_fetch_party_row($conn, $id_party))
    {
        return false;
    }

    animaster_party_disband($conn, $id_party);

    return true;
}

function animaster_party_disband($conn, $id_party)
{
    $id_party = (int) $id_party;

    $stmt = $conn->prepare('SELECT id_user_ig FROM party_members WHERE id_party = :id_party');
    $stmt->execute([':id_party' => $id_party]);
    $member_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $conn->prepare('DELETE FROM party_members WHERE id_party = :id_party');
    $stmt->execute([':id_party' => $id_party]);

    $stmt = $conn->prepare('
        UPDATE party_invites
        SET flg_status = \'C\', dt_m = NOW()
        WHERE id_party = :id_party
          AND flg_status = \'P\'
    ');
    $stmt->execute([':id_party' => $id_party]);

    $stmt = $conn->prepare('DELETE FROM parties WHERE id_party = :id_party LIMIT 1');
    $stmt->execute([':id_party' => $id_party]);

    foreach ($member_ids as $member_id)
    {
        animaster_party_set_user_party($conn, (int) $member_id, 0);
    }
}

function animaster_party_user_busy($conn, $id_user_ig)
{
    if (!function_exists('animaster_pvp_is_user_busy'))
    {
        require_once __DIR__ . '/pvp.php';
    }

    return animaster_pvp_is_user_busy($conn, (int) $id_user_ig);
}

function animaster_party_invite_row_to_api($row, $viewer_id)
{
    $viewer_id = (int) $viewer_id;
    $is_incoming = (int) $row['id_user_ig_target'] === $viewer_id;

    if (isset($row['seconds_left']))
    {
        $seconds_left = max(0, (int) $row['seconds_left']);
    }
    else
    {
        $seconds_left = max(0, strtotime((string) $row['dt_expires']) - time());
    }

    return [
        'id_party_invite' => (int) $row['id_party_invite'],
        'id_party' => (int) $row['id_party'],
        'incoming' => $is_incoming,
        'sender_id' => (int) $row['id_user_ig_sender'],
        'sender_name' => (string) ($row['sender_name'] ?? 'Player'),
        'target_id' => (int) $row['id_user_ig_target'],
        'target_name' => (string) ($row['target_name'] ?? 'Player'),
        'dt_expires' => $row['dt_expires'],
        'seconds_left' => $seconds_left
    ];
}

function animaster_party_build_state($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;
    $id_party = animaster_party_get_party_id($conn, $id_user_ig);

    if ($id_party <= 0)
    {
        return null;
    }

    $party = animaster_party_fetch_party_row($conn, $id_party);

    if (!$party)
    {
        animaster_party_set_user_party($conn, $id_user_ig, 0);

        return null;
    }

    $members = animaster_party_fetch_members($conn, $id_party);

    return [
        'id_party' => $id_party,
        'id_leader' => (int) $party['id_user_ig_leader'],
        'is_leader' => (int) $party['id_user_ig_leader'] === $id_user_ig,
        'max_members' => (int) $party['max_members'],
        'member_count' => count($members),
        'members' => $members
    ];
}

function animaster_party_create($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;
    $user = animaster_party_fetch_user($conn, $id_user_ig);

    if (!$user)
    {
        return ['error' => 'INVALID_USER'];
    }

    if ((int) ($user['id_party'] ?? 0) > 0)
    {
        return ['error' => 'ALREADY_IN_PARTY'];
    }

    if (animaster_party_user_busy($conn, $id_user_ig))
    {
        return ['error' => 'BUSY'];
    }

    $conn->beginTransaction();

    try
    {
        $stmt = $conn->prepare('
            INSERT INTO parties (id_user_ig_leader, id_zone, max_members, dt_created)
            VALUES (:leader, :id_zone, :max_members, NOW())
        ');
        $stmt->execute([
            ':leader' => $id_user_ig,
            ':id_zone' => (int) $user['id_zone'],
            ':max_members' => ANIMASTER_PARTY_MAX_MEMBERS
        ]);
        $id_party = (int) $conn->lastInsertId();

        $stmt = $conn->prepare('
            INSERT INTO party_members (id_party, id_user_ig, dt_joined)
            VALUES (:id_party, :id_user_ig, NOW())
        ');
        $stmt->execute([
            ':id_party' => $id_party,
            ':id_user_ig' => $id_user_ig
        ]);

        animaster_party_set_user_party($conn, $id_user_ig, $id_party);

        $conn->commit();

        return [
            'ok' => true,
            'party' => animaster_party_build_state($conn, $id_user_ig)
        ];
    }
    catch (Throwable $e)
    {
        $conn->rollBack();
        error_log('[party] create: ' . $e->getMessage());

        return ['error' => 'SERVER_ERROR'];
    }
}

function animaster_party_invite($conn, $id_sender, $id_target)
{
    $id_sender = (int) $id_sender;
    $id_target = (int) $id_target;

    if ($id_sender <= 0 || $id_target <= 0 || $id_sender === $id_target)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $sender = animaster_party_fetch_user($conn, $id_sender);
    $target = animaster_party_fetch_user($conn, $id_target);

    if (!$sender || !$target)
    {
        return ['error' => 'INVALID_USER'];
    }

    $id_party = (int) ($sender['id_party'] ?? 0);

    if ($id_party <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    if (!animaster_party_is_leader($conn, $id_party, $id_sender))
    {
        return ['error' => 'NOT_LEADER'];
    }

    if (animaster_party_member_count($conn, $id_party) >= ANIMASTER_PARTY_MAX_MEMBERS)
    {
        return ['error' => 'PARTY_FULL'];
    }

    if (trim((string) $target['flg_online']) !== 'S')
    {
        return ['error' => 'OFFLINE'];
    }

    if ((int) $sender['id_zone'] !== (int) $target['id_zone'])
    {
        return ['error' => 'OFFLINE'];
    }

    if (!function_exists('animaster_pvp_distance'))
    {
        require_once __DIR__ . '/pvp.php';
    }

    $dist = animaster_pvp_distance(
        $sender['position_x'],
        $sender['position_z'],
        $target['position_x'],
        $target['position_z']
    );

    if ($dist > ANIMASTER_PARTY_INTERACT_RADIUS)
    {
        return ['error' => 'TOO_FAR'];
    }

    if ((int) ($target['id_party'] ?? 0) > 0)
    {
        return ['error' => 'TARGET_IN_PARTY'];
    }

    if (animaster_party_user_busy($conn, $id_target))
    {
        return ['error' => 'TARGET_BUSY'];
    }

    animaster_party_expire_invites($conn);

    $stmt = $conn->prepare('
        SELECT id_party_invite
        FROM party_invites
        WHERE flg_status = \'P\'
          AND dt_expires >= NOW()
          AND id_party = :id_party
          AND id_user_ig_target = :target
        LIMIT 1
    ');
    $stmt->execute([
        ':id_party' => $id_party,
        ':target' => $id_target
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC))
    {
        return ['error' => 'ALREADY_PENDING'];
    }

    $stmt = $conn->prepare('
        INSERT INTO party_invites (
            id_party, id_user_ig_sender, id_user_ig_target, dt_c, dt_expires, flg_status
        ) VALUES (
            :id_party, :sender, :target, NOW(), DATE_ADD(NOW(), INTERVAL :seconds SECOND), \'P\'
        )
    ');
    $stmt->execute([
        ':id_party' => $id_party,
        ':sender' => $id_sender,
        ':target' => $id_target,
        ':seconds' => ANIMASTER_PARTY_INVITE_SECONDS
    ]);

    return [
        'ok' => true,
        'id_party_invite' => (int) $conn->lastInsertId(),
        'expires_seconds' => ANIMASTER_PARTY_INVITE_SECONDS
    ];
}

function animaster_party_respond($conn, $id_user_ig, $id_party_invite, $accept)
{
    $id_user_ig = (int) $id_user_ig;
    $id_party_invite = (int) $id_party_invite;
    $accept = $accept ? true : false;

    $stmt = $conn->prepare('
        SELECT *
        FROM party_invites
        WHERE id_party_invite = :id
          AND id_user_ig_target = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id' => $id_party_invite,
        ':id_user_ig' => $id_user_ig
    ]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invite || trim((string) $invite['flg_status']) !== 'P')
    {
        return ['error' => 'NOT_FOUND'];
    }

    $stmt = $conn->prepare('
        SELECT id_party_invite
        FROM party_invites
        WHERE id_party_invite = :id
          AND flg_status = \'P\'
          AND dt_expires >= NOW()
        LIMIT 1
    ');
    $stmt->execute([':id' => $id_party_invite]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC))
    {
        $stmt = $conn->prepare('
            UPDATE party_invites
            SET flg_status = \'X\', dt_m = NOW()
            WHERE id_party_invite = :id
              AND flg_status = \'P\'
            LIMIT 1
        ');
        $stmt->execute([':id' => $id_party_invite]);

        return ['error' => 'EXPIRED'];
    }

    if (!$accept)
    {
        $stmt = $conn->prepare('
            UPDATE party_invites
            SET flg_status = \'D\', dt_m = NOW()
            WHERE id_party_invite = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id_party_invite]);

        return ['ok' => true, 'accepted' => false];
    }

    $id_party = (int) $invite['id_party'];
    $party = animaster_party_fetch_party_row($conn, $id_party);

    if (!$party)
    {
        return ['error' => 'PARTY_GONE'];
    }

    $user = animaster_party_fetch_user($conn, $id_user_ig);

    if (!$user || (int) ($user['id_party'] ?? 0) > 0)
    {
        return ['error' => 'ALREADY_IN_PARTY'];
    }

    if (animaster_party_member_count($conn, $id_party) >= (int) $party['max_members'])
    {
        return ['error' => 'PARTY_FULL'];
    }

    if (animaster_party_user_busy($conn, $id_user_ig))
    {
        return ['error' => 'BUSY'];
    }

    $conn->beginTransaction();

    try
    {
        $stmt = $conn->prepare('
            UPDATE party_invites
            SET flg_status = \'A\', dt_m = NOW()
            WHERE id_party_invite = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $id_party_invite]);

        $stmt = $conn->prepare('
            UPDATE party_invites
            SET flg_status = \'C\', dt_m = NOW()
            WHERE id_user_ig_target = :target
              AND flg_status = \'P\'
              AND id_party_invite != :id
        ');
        $stmt->execute([
            ':target' => $id_user_ig,
            ':id' => $id_party_invite
        ]);

        $stmt = $conn->prepare('
            INSERT INTO party_members (id_party, id_user_ig, dt_joined)
            VALUES (:id_party, :id_user_ig, NOW())
        ');
        $stmt->execute([
            ':id_party' => $id_party,
            ':id_user_ig' => $id_user_ig
        ]);

        animaster_party_set_user_party($conn, $id_user_ig, $id_party);

        $conn->commit();

        return [
            'ok' => true,
            'accepted' => true,
            'party' => animaster_party_build_state($conn, $id_user_ig)
        ];
    }
    catch (Throwable $e)
    {
        $conn->rollBack();
        error_log('[party] respond: ' . $e->getMessage());

        return ['error' => 'SERVER_ERROR'];
    }
}

function animaster_party_leave($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;
    $id_party = animaster_party_get_party_id($conn, $id_user_ig);

    if ($id_party <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    if (animaster_party_is_leader($conn, $id_party, $id_user_ig))
    {
        animaster_party_disband($conn, $id_party);

        return ['ok' => true, 'disbanded' => true, 'party' => null];
    }

    $stmt = $conn->prepare('
        DELETE FROM party_members
        WHERE id_party = :id_party
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_party' => $id_party,
        ':id_user_ig' => $id_user_ig
    ]);

    animaster_party_set_user_party($conn, $id_user_ig, 0);

    $solo_disbanded = animaster_party_disband_if_solo_leader($conn, $id_party);

    return [
        'ok' => true,
        'disbanded' => $solo_disbanded,
        'party' => null
    ];
}

function animaster_party_kick($conn, $id_leader, $id_target)
{
    $id_leader = (int) $id_leader;
    $id_target = (int) $id_target;

    if ($id_leader <= 0 || $id_target <= 0 || $id_leader === $id_target)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $id_party = animaster_party_get_party_id($conn, $id_leader);

    if ($id_party <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    if (!animaster_party_is_leader($conn, $id_party, $id_leader))
    {
        return ['error' => 'NOT_LEADER'];
    }

    if (!animaster_party_is_member($conn, $id_party, $id_target))
    {
        return ['error' => 'NOT_MEMBER'];
    }

    $stmt = $conn->prepare('
        DELETE FROM party_members
        WHERE id_party = :id_party
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_party' => $id_party,
        ':id_user_ig' => $id_target
    ]);

    animaster_party_set_user_party($conn, $id_target, 0);

    $solo_disbanded = animaster_party_disband_if_solo_leader($conn, $id_party);

    return [
        'ok' => true,
        'disbanded' => $solo_disbanded,
        'party' => $solo_disbanded ? null : animaster_party_build_state($conn, $id_leader)
    ];
}

function animaster_party_transfer_leader($conn, $id_leader, $id_new_leader)
{
    $id_leader = (int) $id_leader;
    $id_new_leader = (int) $id_new_leader;

    if ($id_leader <= 0 || $id_new_leader <= 0 || $id_leader === $id_new_leader)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $id_party = animaster_party_get_party_id($conn, $id_leader);

    if ($id_party <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    if (!animaster_party_is_leader($conn, $id_party, $id_leader))
    {
        return ['error' => 'NOT_LEADER'];
    }

    if (!animaster_party_is_member($conn, $id_party, $id_new_leader))
    {
        return ['error' => 'NOT_MEMBER'];
    }

    $stmt = $conn->prepare('
        UPDATE parties
        SET id_user_ig_leader = :new_leader,
            dt_m = NOW()
        WHERE id_party = :id_party
        LIMIT 1
    ');
    $stmt->execute([
        ':new_leader' => $id_new_leader,
        ':id_party' => $id_party
    ]);

    return [
        'ok' => true,
        'party' => animaster_party_build_state($conn, $id_leader)
    ];
}

function animaster_party_poll($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    animaster_party_expire_invites($conn);

    $incoming = [];
    $stmt = $conn->prepare('
        SELECT pi.*,
               ui.display_name AS sender_name,
               ut.display_name AS target_name,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), pi.dt_expires)) AS seconds_left
        FROM party_invites pi
        INNER JOIN users_ig ui ON ui.id_user_ig = pi.id_user_ig_sender
        INNER JOIN users_ig ut ON ut.id_user_ig = pi.id_user_ig_target
        WHERE pi.id_user_ig_target = :id_user_ig
          AND pi.flg_status = \'P\'
          AND pi.dt_expires >= NOW()
        ORDER BY pi.id_party_invite ASC
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $incoming[] = animaster_party_invite_row_to_api($row, $id_user_ig);
    }

    $outgoing = null;
    $stmt = $conn->prepare('
        SELECT pi.*,
               ui.display_name AS sender_name,
               ut.display_name AS target_name,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), pi.dt_expires)) AS seconds_left
        FROM party_invites pi
        INNER JOIN users_ig ui ON ui.id_user_ig = pi.id_user_ig_sender
        INNER JOIN users_ig ut ON ut.id_user_ig = pi.id_user_ig_target
        WHERE pi.id_user_ig_sender = :id_user_ig
          AND pi.flg_status = \'P\'
          AND pi.dt_expires >= NOW()
        ORDER BY pi.id_party_invite DESC
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row)
    {
        $outgoing = animaster_party_invite_row_to_api($row, $id_user_ig);
    }

    $party_pve_battle = null;

    if (!function_exists('animaster_party_pve_active_for_user'))
    {
        require_once __DIR__ . '/party_pve.php';
    }

    $active_battle = animaster_party_pve_active_for_user($conn, $id_user_ig);

    if ($active_battle)
    {
        $party_pve_battle = $active_battle;
    }

    return [
        'ok' => true,
        'party' => animaster_party_build_state($conn, $id_user_ig),
        'incoming_invites' => $incoming,
        'outgoing_invite' => $outgoing,
        'party_pve_battle' => $party_pve_battle
    ];
}
