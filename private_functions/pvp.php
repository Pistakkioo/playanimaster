<?php

if (!defined('ANIMASTER_DUEL_REQUEST_SECONDS'))
{
    define('ANIMASTER_DUEL_REQUEST_SECONDS', 30);
}

if (!defined('ANIMASTER_DUEL_COOLDOWN_SECONDS'))
{
    define('ANIMASTER_DUEL_COOLDOWN_SECONDS', 30);
}

require_once __DIR__ . '/combat/CombatSession.php';
require_once __DIR__ . '/combat/TurnQueue.php';
require_once __DIR__ . '/combat/Permissions.php';
require_once __DIR__ . '/combat/BattleRepository.php';
require_once __DIR__ . '/combat/BattleParticipantFactory.php';

function animaster_pvp_expire_requests($conn)
{
    $stmt = $conn->prepare('
        UPDATE pvp_duel_requests
        SET flg_status = \'X\', dt_m = NOW()
        WHERE flg_status = \'P\'
          AND dt_expires < NOW()
    ');
    $stmt->execute();
}

function animaster_pvp_fetch_user($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_user_ig, display_name, id_zone, flg_online,
               position_x, position_z, flg_busy, flg_battling, flg_trading
        FROM users_ig
        WHERE id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_pvp_distance($x1, $z1, $x2, $z2)
{
    $dx = (float) $x1 - (float) $x2;
    $dz = (float) $z1 - (float) $z2;

    return sqrt(($dx * $dx) + ($dz * $dz));
}

function animaster_pvp_has_team_animal($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_animal
        FROM animals
        WHERE id_user_ig = :id_user_ig
          AND team_position > 0
          AND COALESCE(current_hp, 0) > 0
        ORDER BY team_position ASC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function animaster_pvp_get_open_battle_id($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT b.id_battle
        FROM battles b
        INNER JOIN battle_participants p
            ON p.id_battle = b.id_battle
           AND p.id_user_ig = :id_user_ig
        WHERE b.battle_type = \'pvp_duel\'
          AND b.flg_status = \'O\'
        ORDER BY b.id_battle DESC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id_battle'] : 0;
}

function animaster_pvp_has_solo_battle($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT b.id_battle
        FROM battles b
        INNER JOIN battle_participants p
            ON p.id_battle = b.id_battle
           AND p.id_user_ig = :id_user_ig
           AND p.side = \'A\'
        WHERE b.battle_type = \'solo_pve\'
          AND b.flg_status = \'O\'
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function animaster_pvp_has_open_trade($conn, $id_user_ig)
{
    if (!function_exists('animaster_trade_get_open_trade_id'))
    {
        require_once __DIR__ . '/trade.php';
    }

    return animaster_trade_get_open_trade_id($conn, (int) $id_user_ig) > 0;
}

function animaster_pvp_is_user_busy($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if (animaster_pvp_get_open_battle_id($conn, $id_user_ig) > 0)
    {
        return true;
    }

    if (animaster_pvp_has_solo_battle($conn, $id_user_ig))
    {
        return true;
    }

    if (animaster_pvp_has_open_trade($conn, $id_user_ig))
    {
        return true;
    }

    return false;
}

function animaster_pvp_cooldown_active($conn, $id_a, $id_b)
{
    // Two participants of a just-finished duel share the same id_battle; a
    // cooldown match is a finished pvp_duel battle that both users fought in.
    $stmt = $conn->prepare('
        SELECT b.id_battle
        FROM battles b
        INNER JOIN battle_participants pa
            ON pa.id_battle = b.id_battle AND pa.id_user_ig = :a
        INNER JOIN battle_participants pb
            ON pb.id_battle = b.id_battle AND pb.id_user_ig = :b
        WHERE b.battle_type = \'pvp_duel\'
          AND b.flg_status = \'F\'
          AND b.dt_finished >= DATE_SUB(NOW(), INTERVAL :seconds SECOND)
        LIMIT 1
    ');
    $stmt->execute([
        'seconds' => ANIMASTER_DUEL_COOLDOWN_SECONDS,
        'a' => (int) $id_a,
        'b' => (int) $id_b
    ]);

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function animaster_pvp_user_side($battle_row, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    if ((int) $battle_row['id_user_ig_a'] === $id_user_ig)
    {
        return 'a';
    }

    if ((int) $battle_row['id_user_ig_b'] === $id_user_ig)
    {
        return 'b';
    }

    return '';
}

function animaster_pvp_set_user_flags($conn, $id_user_ig, $battling = true)
{
    $stmt = $conn->prepare('
        UPDATE users_ig
        SET flg_busy = \'S\',
            flg_battling = :flg_battling,
            dt_modifica = NOW()
        WHERE id_user_ig = :id_user_ig
    ');
    $stmt->execute([
        'id_user_ig' => (int) $id_user_ig,
        'flg_battling' => $battling ? 'S' : 'N'
    ]);
}

function animaster_pvp_clear_user_flags($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        UPDATE users_ig
        SET flg_busy = \'N\',
            flg_battling = \'N\',
            dt_modifica = NOW()
        WHERE id_user_ig = :id_user_ig
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);
}

function animaster_pvp_request_row_to_api($row, $viewer_id)
{
    $viewer_id = (int) $viewer_id;
    $is_incoming = (int) $row['id_user_ig_target'] === $viewer_id;
    $other_id = $is_incoming
        ? (int) $row['id_user_ig_challenger']
        : (int) $row['id_user_ig_target'];

    if (isset($row['seconds_left']))
    {
        $seconds_left = (int) $row['seconds_left'];
    }
    else
    {
        $seconds_left = max(0, strtotime($row['dt_expires']) - time());
    }

    return [
        'id_duel_request' => (int) $row['id_duel_request'],
        'incoming' => $is_incoming,
        'other_id' => $other_id,
        'other_name' => $row['other_name'] ?? 'Player',
        'dt_expires' => $row['dt_expires'],
        'seconds_left' => $seconds_left
    ];
}

function animaster_pvp_send_request($conn, $id_challenger, $id_target)
{
    $id_challenger = (int) $id_challenger;
    $id_target = (int) $id_target;

    if ($id_challenger <= 0 || $id_target <= 0 || $id_challenger === $id_target)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $challenger = animaster_pvp_fetch_user($conn, $id_challenger);
    $target = animaster_pvp_fetch_user($conn, $id_target);

    if (!$challenger || !$target)
    {
        return ['error' => 'INVALID_USER'];
    }

    if (trim((string) $target['flg_online']) !== 'S')
    {
        return ['error' => 'OFFLINE'];
    }

    if ((int) $challenger['id_zone'] !== (int) $target['id_zone'])
    {
        return ['error' => 'OFFLINE'];
    }

    $distance = animaster_pvp_distance(
        $challenger['position_x'],
        $challenger['position_z'],
        $target['position_x'],
        $target['position_z']
    );

    if ($distance > animaster_request_distance('duel'))
    {
        return ['error' => 'OUT_OF_RANGE'];
    }

    if (!animaster_pvp_has_team_animal($conn, $id_challenger))
    {
        return ['error' => 'NO_TEAM'];
    }

    if (!animaster_pvp_has_team_animal($conn, $id_target))
    {
        return ['error' => 'TARGET_NO_TEAM'];
    }

    if (animaster_pvp_is_user_busy($conn, $id_challenger)
        || animaster_pvp_is_user_busy($conn, $id_target))
    {
        return ['error' => 'BUSY'];
    }

    if (animaster_pvp_cooldown_active($conn, $id_challenger, $id_target))
    {
        return ['error' => 'COOLDOWN'];
    }

    animaster_pvp_expire_requests($conn);

    $stmt = $conn->prepare('
        SELECT id_duel_request
        FROM pvp_duel_requests
        WHERE flg_status = \'P\'
          AND dt_expires >= NOW()
          AND (
              (id_user_ig_challenger = :challenger AND id_user_ig_target = :target)
              OR (id_user_ig_challenger = :challenger2 AND id_user_ig_target = :target2)
          )
        LIMIT 1
    ');
    $stmt->execute([
        'challenger' => $id_challenger,
        'target' => $id_target,
        'challenger2' => $id_target,
        'target2' => $id_challenger
    ]);

    if ($stmt->fetch(PDO::FETCH_ASSOC))
    {
        return ['error' => 'ALREADY_PENDING'];
    }

    $stmt = $conn->prepare('
        INSERT INTO pvp_duel_requests (
            id_user_ig_challenger, id_user_ig_target, id_zone,
            dt_c, dt_expires, flg_status
        ) VALUES (
            :challenger, :target, :id_zone,
            NOW(), DATE_ADD(NOW(), INTERVAL :seconds SECOND), \'P\'
        )
    ');
    $stmt->execute([
        'challenger' => $id_challenger,
        'target' => $id_target,
        'id_zone' => (int) $challenger['id_zone'],
        'seconds' => ANIMASTER_DUEL_REQUEST_SECONDS
    ]);

    return [
        'ok' => true,
        'id_duel_request' => (int) $conn->lastInsertId(),
        'expires_seconds' => ANIMASTER_DUEL_REQUEST_SECONDS
    ];
}

function animaster_pvp_poll($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    animaster_pvp_expire_requests($conn);

    $incoming = [];
    $stmt = $conn->prepare('
        SELECT dr.*, ui.display_name AS other_name,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), dr.dt_expires)) AS seconds_left
        FROM pvp_duel_requests dr
        INNER JOIN users_ig ui ON ui.id_user_ig = dr.id_user_ig_challenger
        WHERE dr.id_user_ig_target = :id_user_ig
          AND dr.flg_status = \'P\'
          AND dr.dt_expires >= NOW()
        ORDER BY dr.id_duel_request ASC
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $incoming[] = animaster_pvp_request_row_to_api($row, $id_user_ig);
    }

    $outgoing = null;
    $stmt = $conn->prepare('
        SELECT dr.*, ui.display_name AS other_name,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), dr.dt_expires)) AS seconds_left
        FROM pvp_duel_requests dr
        INNER JOIN users_ig ui ON ui.id_user_ig = dr.id_user_ig_target
        WHERE dr.id_user_ig_challenger = :id_user_ig
          AND dr.flg_status = \'P\'
          AND dr.dt_expires >= NOW()
        ORDER BY dr.id_duel_request DESC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row)
    {
        $outgoing = animaster_pvp_request_row_to_api($row, $id_user_ig);
    }

    $active_battle = null;
    $battle_id = animaster_pvp_get_open_battle_id($conn, $id_user_ig);

    if ($battle_id > 0)
    {
        $active_battle = [
            'id_battle' => $battle_id,
            'battle_type' => 'pvp'
        ];
    }

    return [
        'ok' => true,
        'incoming_requests' => $incoming,
        'outgoing_request' => $outgoing,
        'active_battle' => $active_battle
    ];
}

function animaster_pvp_fetch_first_team_animal_id($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_animal
        FROM animals
        WHERE id_user_ig = :id_user_ig
          AND team_position > 0
          AND COALESCE(current_hp, 0) > 0
        ORDER BY team_position ASC
        LIMIT 1
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['id_animal'] : 0;
}

function animaster_pvp_fetch_animal_snapshot($conn, $id_animal, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $sql = '
        SELECT A.id_animal, A.id_user_ig, A.lvl, A.current_hp, A.max_hp, A.experience,
               A.nickname, A.id_element, A.id_species,
               A.dna_atk, A.dna_def, A.dna_matk, A.dna_mdef, A.dna_hp,
               A.dna_acc, A.dna_eva, A.dna_cr, A.dna_spd,
               A.pt_atk, A.pt_def, A.pt_matk, A.pt_mdef, A.pt_hp,
               A.pt_acc, A.pt_eva, A.pt_cr, A.pt_spd,
               A.xp_atk, A.xp_def, A.xp_matk, A.xp_mdef, A.xp_hp,
               A.xp_acc, A.xp_eva, A.xp_cr, A.xp_spd,
               L.base_atk, L.base_def, L.base_matk, L.base_mdef, L.base_hp,
               L.base_acc, L.base_eva, L.base_cr, L.base_spd,
               L.species' . $lang_suffix . ' AS species,
               E.element' . $lang_suffix . ' AS element
        FROM animals A
        INNER JOIN species L ON L.id_species = A.id_species
        LEFT JOIN elements E ON E.id_element = A.id_element
        WHERE A.id_animal = :id_animal
        LIMIT 1
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute(['id_animal' => (int) $id_animal]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    $lvl = (int) $row['lvl'];

    $max_hp = (int) floor(0.01 * (2 * $row['base_hp'] + $row['dna_hp'] + floor(0.25 * $row['pt_hp']) + floor(0.25 * $row['xp_hp'])) * $lvl) + $lvl + 10;
    $current_hp = $row['current_hp'];

    if ($current_hp === null || $current_hp === '')
    {
        $current_hp = $max_hp;
    }

    $current_hp = (int) $current_hp;

    if ($current_hp <= 0)
    {
        return null;
    }

    return [
        'id_animal' => (int) $row['id_animal'],
        'id_user_ig' => (int) $row['id_user_ig'],
        'lvl' => $lvl,
        'current_hp' => $current_hp,
        'max_hp' => $max_hp > 0 ? $max_hp : $current_hp,
        'experience' => (int) $row['experience'],
        'nickname' => $row['nickname'],
        'id_element' => (int) $row['id_element'],
        'id_species' => (int) $row['id_species'],
        'species' => $row['species'],
        'element' => $row['element'],
        'atk' => (int) floor(0.01 * (2 * $row['base_atk'] + $row['dna_atk'] + floor(0.25 * $row['pt_atk']) + floor(0.25 * $row['xp_atk'])) * $lvl) + 5,
        'def' => (int) floor(0.01 * (2 * $row['base_def'] + $row['dna_def'] + floor(0.25 * $row['pt_def']) + floor(0.25 * $row['xp_def'])) * $lvl) + 5,
        'matk' => (int) floor(0.01 * (2 * $row['base_matk'] + $row['dna_matk'] + floor(0.25 * $row['pt_matk']) + floor(0.25 * $row['xp_matk'])) * $lvl) + 5,
        'mdef' => (int) floor(0.01 * (2 * $row['base_mdef'] + $row['dna_mdef'] + floor(0.25 * $row['pt_mdef']) + floor(0.25 * $row['xp_mdef'])) * $lvl) + 5,
        'spd' => (int) floor(0.01 * (2 * $row['base_spd'] + $row['dna_spd'] + floor(0.25 * $row['pt_spd']) + floor(0.25 * $row['xp_spd'])) * $lvl) + 5,
        'acc' => (int) $row['base_acc'],
        'eva' => (int) $row['base_eva'],
        'cr' => (int) $row['base_cr']
    ];
}

function animaster_pvp_apply_battle_start_buffs($conn, array $snap)
{
    if (!class_exists('BUFFS'))
    {
        require_once __DIR__ . '/buffs.php';
    }

    $stats = BUFFS::applyAtBattleStart($conn, (int) $snap['id_animal'], (int) $snap['id_user_ig'], [
        'atk' => (int) $snap['atk'],
        'def' => (int) $snap['def'],
        'matk' => (int) $snap['matk'],
        'mdef' => (int) $snap['mdef'],
        'spd' => (int) $snap['spd'],
        'acc' => (int) $snap['acc'],
        'eva' => (int) $snap['eva'],
        'cr' => (int) $snap['cr'],
        'hp' => (int) $snap['current_hp'],
        'max_hp' => (int) $snap['max_hp'],
    ]);

    $snap['atk'] = (int) $stats['atk'];
    $snap['def'] = (int) $stats['def'];
    $snap['matk'] = (int) $stats['matk'];
    $snap['mdef'] = (int) $stats['mdef'];
    $snap['spd'] = (int) $stats['spd'];
    $snap['acc'] = (int) $stats['acc'];
    $snap['eva'] = (int) $stats['eva'];
    $snap['cr'] = (int) $stats['cr'];
    $snap['current_hp'] = (int) $stats['hp'];
    $snap['max_hp'] = (int) $stats['max_hp'];

    return $snap;
}

function animaster_pvp_fetch_animal_snapshot_buffed($conn, $id_animal, $lang_suffix)
{
    $snap = animaster_pvp_fetch_animal_snapshot($conn, $id_animal, $lang_suffix);

    if (!$snap)
    {
        return null;
    }

    return animaster_pvp_apply_battle_start_buffs($conn, $snap);
}

function animaster_pvp_snapshot_to_move_fields($snap, $prefix)
{
    return [
        $prefix . '_id' => $snap['id_animal'],
        $prefix . '_id_element' => $snap['id_element'],
        $prefix . '_id_species' => $snap['id_species'],
        $prefix . '_species' => $snap['species'],
        $prefix . '_lvl' => $snap['lvl'],
        $prefix . '_nickname' => $snap['nickname'],
        $prefix . '_cur_exp' => $snap['experience'],
        $prefix . '_res_hp' => $snap['current_hp'],
        $prefix . '_res_max_hp' => $snap['max_hp'],
        $prefix . '_res_atk' => $snap['atk'],
        $prefix . '_res_def' => $snap['def'],
        $prefix . '_res_matk' => $snap['matk'],
        $prefix . '_res_mdef' => $snap['mdef'],
        $prefix . '_res_acc' => $snap['acc'],
        $prefix . '_res_eva' => $snap['eva'],
        $prefix . '_res_cr' => $snap['cr'],
        $prefix . '_res_spd' => $snap['spd']
    ];
}

/**
 * Round-0 "start" move for a freshly snapshotted duel. State (p_a_* = side A,
 * w_a_* = side B) is derived from the two participants and packed in meta_json.
 */
function animaster_pvp_insert_turn0($conn, $id_battle, $lang_suffix = '')
{
    $state = animaster_pvp_current_state($conn, $id_battle);

    if (!$state)
    {
        return;
    }

    $meta = animaster_pvp_move_meta($conn, $state, $lang_suffix, 'start', 0, 'start', 0);

    BattleRepository::insertMove($conn, [
        'id_battle' => (int) $id_battle,
        'round' => 0,
        'order_in_turn' => 0,
        'id_actor_participant' => (int) $state['p_a_participant'],
        'id_target_participant' => (int) $state['w_a_participant'],
        'id_user_ig_actor' => null,
        'move_type' => 'start',
        'id_rif' => 0,
        'move_speed' => 0,
        'move_description' => 'start',
        'move_hit' => null,
        'actor_hp_after' => (int) $state['p_a_res_hp'],
        'target_hp_after' => (int) $state['w_a_res_hp'],
        'resulting_battle_status' => 'ongoing',
        'meta' => $meta
    ]);
}

function animaster_pvp_first_actor_user_id($battle_row, $p_spd, $w_spd)
{
    if ((float) $p_spd >= (float) $w_spd)
    {
        return (int) $battle_row['id_user_ig_a'];
    }

    return (int) $battle_row['id_user_ig_b'];
}

function animaster_pvp_second_actor_user_id($battle_row, $p_spd, $w_spd)
{
    if ((float) $p_spd >= (float) $w_spd)
    {
        return (int) $battle_row['id_user_ig_b'];
    }

    return (int) $battle_row['id_user_ig_a'];
}

function animaster_pvp_start_battle($conn, $id_duel_request, $id_challenger, $id_target, $id_zone, $lang_suffix)
{
    $animal_a = animaster_pvp_fetch_first_team_animal_id($conn, $id_challenger);
    $animal_b = animaster_pvp_fetch_first_team_animal_id($conn, $id_target);

    if ($animal_a <= 0 || $animal_b <= 0)
    {
        return ['error' => 'NO_TEAM'];
    }

    $snap_a = animaster_pvp_fetch_animal_snapshot_buffed($conn, $animal_a, $lang_suffix);
    $snap_b = animaster_pvp_fetch_animal_snapshot_buffed($conn, $animal_b, $lang_suffix);

    if (!$snap_a || !$snap_b)
    {
        return ['error' => 'NO_TEAM'];
    }

    try
    {
        $id_battle = BattleRepository::createBattle($conn, [
            'battle_type' => 'pvp_duel',
            'planning_mode' => 'simultaneous_submit',
            'id_zone' => (int) $id_zone,
            'id_user_ig_initiator' => (int) $id_challenger,
            'id_duel_request' => (int) $id_duel_request
        ]);

        BattleRepository::insertParticipant(
            $conn,
            $id_battle,
            BattleParticipantFactory::playerAnimal($snap_a, BattleRepository::SIDE_A, (int) $id_challenger, 1)
        );
        BattleRepository::insertParticipant(
            $conn,
            $id_battle,
            BattleParticipantFactory::playerAnimal($snap_b, BattleRepository::SIDE_B, (int) $id_target, 1)
        );

        animaster_pvp_insert_turn0($conn, $id_battle, $lang_suffix);

        // Duel planning turns start at 1 (current_turn semantics: open turn).
        BattleRepository::updateBattle($conn, $id_battle, ['current_round' => 1]);

        animaster_pvp_set_user_flags($conn, $id_challenger);
        animaster_pvp_set_user_flags($conn, $id_target);

        return [
            'ok' => true,
            'id_battle' => $id_battle,
            'battle_type' => 'pvp'
        ];
    }
    catch (Throwable $e)
    {
        error_log('[animaster_pvp_start_battle] ' . $e->getMessage());

        return ['error' => 'START_FAILED'];
    }
}

function animaster_pvp_respond_request($conn, $id_user_ig, $id_duel_request, $accept, $lang_suffix = '')
{
    $id_user_ig = (int) $id_user_ig;
    $id_duel_request = (int) $id_duel_request;
    $accept = $accept ? true : false;

    animaster_pvp_expire_requests($conn);

    $stmt = $conn->prepare('
        SELECT *
        FROM pvp_duel_requests
        WHERE id_duel_request = :id
          AND flg_status = \'P\'
          AND dt_expires >= NOW()
        LIMIT 1
    ');
    $stmt->execute(['id' => $id_duel_request]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request)
    {
        return ['error' => 'EXPIRED'];
    }

    if ((int) $request['id_user_ig_target'] !== $id_user_ig)
    {
        return ['error' => 'FORBIDDEN'];
    }

    if (!$accept)
    {
        $stmt = $conn->prepare('
            UPDATE pvp_duel_requests
            SET flg_status = \'D\', dt_m = NOW()
            WHERE id_duel_request = :id
        ');
        $stmt->execute(['id' => $id_duel_request]);

        return ['ok' => true, 'accepted' => false];
    }

    $challenger_id = (int) $request['id_user_ig_challenger'];
    $target_id = (int) $request['id_user_ig_target'];

    if (animaster_pvp_is_user_busy($conn, $challenger_id)
        || animaster_pvp_is_user_busy($conn, $target_id))
    {
        return ['error' => 'BUSY'];
    }

    $challenger = animaster_pvp_fetch_user($conn, $challenger_id);
    $target = animaster_pvp_fetch_user($conn, $target_id);

    if (!$challenger || !$target
        || trim((string) $challenger['flg_online']) !== 'S'
        || trim((string) $target['flg_online']) !== 'S'
        || (int) $challenger['id_zone'] !== (int) $target['id_zone'])
    {
        return ['error' => 'OFFLINE'];
    }

    $conn->beginTransaction();

    try
    {
        $stmt = $conn->prepare('
            UPDATE pvp_duel_requests
            SET flg_status = \'A\', dt_m = NOW()
            WHERE id_duel_request = :id AND flg_status = \'P\'
        ');
        $stmt->execute(['id' => $id_duel_request]);

        if ($stmt->rowCount() <= 0)
        {
            throw new RuntimeException('REQUEST_TAKEN');
        }

        $battle = animaster_pvp_start_battle(
            $conn,
            $id_duel_request,
            $challenger_id,
            $target_id,
            (int) $request['id_zone'],
            $lang_suffix
        );

        if (empty($battle['ok']))
        {
            throw new RuntimeException($battle['error'] ?? 'START_FAILED');
        }

        $conn->commit();

        return [
            'ok' => true,
            'accepted' => true,
            'id_battle' => (int) $battle['id_battle'],
            'battle_type' => 'pvp'
        ];
    }
    catch (Throwable $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        $code = $e->getMessage();
        $known = ['NO_TEAM', 'START_FAILED', 'REQUEST_TAKEN'];

        if (in_array($code, $known, true))
        {
            return ['error' => $code];
        }

        error_log('[animaster_pvp_respond_request] ' . $e->getMessage());

        return ['error' => 'RESPOND_FAILED'];
    }
}

/**
 * Legacy-shaped duel row over the unified `battles` table: exposes
 * id_battle_pvp, current_turn, id_user_ig_a/b (from side A/B participants) and
 * a derived id_winner_user_ig so the rest of this file reads unchanged.
 */
function animaster_pvp_fetch_battle_row($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT b.*,
               b.id_battle AS id_battle_pvp,
               b.current_round AS current_turn,
               pa.id_user_ig AS id_user_ig_a,
               pb.id_user_ig AS id_user_ig_b
        FROM battles b
        LEFT JOIN battle_participants pa ON pa.id_battle = b.id_battle AND pa.side = \'A\'
        LEFT JOIN battle_participants pb ON pb.id_battle = b.id_battle AND pb.side = \'B\'
        WHERE b.id_battle = :id_battle
          AND b.battle_type = \'pvp_duel\'
        LIMIT 1
    ');
    $stmt->execute(['id_battle' => (int) $id_battle]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    $winner_alliance = trim((string) ($row['id_winner_alliance'] ?? ''));

    if ($winner_alliance === 'A')
    {
        $row['id_winner_user_ig'] = (int) $row['id_user_ig_a'];
    }
    else if ($winner_alliance === 'B')
    {
        $row['id_winner_user_ig'] = (int) $row['id_user_ig_b'];
    }
    else
    {
        $row['id_winner_user_ig'] = null;
    }

    $row['awaiting_user_ig'] = null;

    return $row;
}

/**
 * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null} [sideA, sideB]
 */
function animaster_pvp_fetch_participants_ab($conn, $id_battle)
{
    $a = BattleRepository::fetchParticipantsBySide($conn, (int) $id_battle, BattleRepository::SIDE_A);
    $b = BattleRepository::fetchParticipantsBySide($conn, (int) $id_battle, BattleRepository::SIDE_B);

    return [$a ? $a[0] : null, $b ? $b[0] : null];
}

function animaster_pvp_participant_id_for_user($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_battle_participant
        FROM battle_participants
        WHERE id_battle = :id_battle
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        'id_battle' => (int) $id_battle,
        'id_user_ig' => (int) $id_user_ig
    ]);

    return (int) $stmt->fetchColumn();
}

function animaster_pvp_element_info($conn, $id_element, $lang_suffix)
{
    static $cache = [];

    $id_element = (int) $id_element;
    $lang_suffix_safe = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);
    $key = $id_element . '|' . $lang_suffix_safe;

    if (isset($cache[$key]))
    {
        return $cache[$key];
    }

    $stmt = $conn->prepare('SELECT element' . $lang_suffix_safe . ' AS element, color FROM elements WHERE id_element = :id LIMIT 1');
    $stmt->execute(['id' => $id_element]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $info = [
        'element' => $row ? (string) $row['element'] : '',
        'color' => $row ? (string) $row['color'] : ''
    ];
    $cache[$key] = $info;

    return $info;
}

/**
 * Battle state (p_a_* = side A, w_a_* = side B) from the authoritative
 * participant rows. Replaces reconstructing state from the last move row.
 *
 * @param array<string,mixed> $pa side A participant
 * @param array<string,mixed> $pb side B participant
 */
function animaster_pvp_state_from_participants(array $pa, array $pb)
{
    return [
        'p_a_res_hp' => (int) $pa['current_hp'],
        'w_a_res_hp' => (int) $pb['current_hp'],
        'p_a_res_max_hp' => (int) $pa['max_hp'],
        'w_a_res_max_hp' => (int) $pb['max_hp'],
        'p_a_res_atk' => (float) $pa['atk'],
        'p_a_res_def' => (float) $pa['def'],
        'p_a_res_matk' => (float) $pa['matk'],
        'p_a_res_mdef' => (float) $pa['mdef'],
        'p_a_res_acc' => (int) $pa['acc'],
        'p_a_res_eva' => (int) $pa['eva'],
        'p_a_res_cr' => (int) $pa['cr'],
        'p_a_res_spd' => (float) $pa['spd'],
        'w_a_res_atk' => (float) $pb['atk'],
        'w_a_res_def' => (float) $pb['def'],
        'w_a_res_matk' => (float) $pb['matk'],
        'w_a_res_mdef' => (float) $pb['mdef'],
        'w_a_res_acc' => (int) $pb['acc'],
        'w_a_res_eva' => (int) $pb['eva'],
        'w_a_res_cr' => (int) $pb['cr'],
        'w_a_res_spd' => (float) $pb['spd'],
        'p_a_id' => (int) $pa['id_entity'],
        'w_a_id' => (int) $pb['id_entity'],
        'p_a_id_element' => (int) $pa['id_element'],
        'w_a_id_element' => (int) $pb['id_element'],
        'p_a_id_species' => (int) $pa['id_species'],
        'w_a_id_species' => (int) $pb['id_species'],
        'p_a_species' => (string) $pa['species_name'],
        'w_a_species' => (string) $pb['species_name'],
        'p_a_lvl' => (int) $pa['lvl'],
        'w_a_lvl' => (int) $pb['lvl'],
        'p_a_nickname' => (string) $pa['nickname'],
        'p_a_cur_exp' => (int) $pa['experience'],
        'p_a_participant' => (int) $pa['id_battle_participant'],
        'w_a_participant' => (int) $pb['id_battle_participant']
    ];
}

/**
 * Current authoritative state from the two participants of a duel.
 */
function animaster_pvp_current_state($conn, $id_battle)
{
    list($pa, $pb) = animaster_pvp_fetch_participants_ab($conn, $id_battle);

    if (!$pa || !$pb)
    {
        return null;
    }

    return animaster_pvp_state_from_participants($pa, $pb);
}

/**
 * Flatten a battle_moves row (+ meta_json) into the legacy p_a_ / w_a_ shape
 * the client combat log consumes.
 */
function animaster_pvp_flatten_move(array $row)
{
    $meta = !empty($row['meta_json']) ? json_decode($row['meta_json'], true) : [];
    $flat = array_merge($row, is_array($meta) ? $meta : []);
    $flat['turn'] = (int) $row['round'];
    $flat['id_battle_pvp'] = (int) $row['id_battle'];
    $flat['id_battle_pvp_move'] = (int) $row['id_battle_move'];

    return $flat;
}

/**
 * Display block (p_a_ / w_a_ + element names) packed into a move's meta_json.
 */
function animaster_pvp_move_meta($conn, array $state, $lang_suffix, $protagonist_type, $id_protagonist, $target_type, $id_target)
{
    $pe = animaster_pvp_element_info($conn, (int) $state['p_a_id_element'], $lang_suffix);
    $we = animaster_pvp_element_info($conn, (int) $state['w_a_id_element'], $lang_suffix);

    return array_merge($state, [
        'p_a_element' => $pe['element'],
        'p_a_element_color' => $pe['color'],
        'w_a_element' => $we['element'],
        'w_a_element_color' => $we['color'],
        'protagonist_type' => (string) $protagonist_type,
        'id_protagonist' => (int) $id_protagonist,
        'target_type' => (string) $target_type,
        'id_target' => (int) $id_target
    ]);
}

function animaster_pvp_fetch_moves_for_turn($conn, $id_battle, $turn, $lang_suffix)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battle_moves
        WHERE id_battle = :id_battle
          AND round = :turn
        ORDER BY order_in_turn ASC, id_battle_move ASC
    ');
    $stmt->execute([
        'id_battle' => (int) $id_battle,
        'turn' => (int) $turn
    ]);

    return array_map('animaster_pvp_flatten_move', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function animaster_pvp_fetch_all_moves($conn, $id_battle, $lang_suffix)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battle_moves
        WHERE id_battle = :id_battle
        ORDER BY round ASC, order_in_turn ASC, id_battle_move ASC
    ');
    $stmt->execute([
        'id_battle' => (int) $id_battle
    ]);

    return array_map('animaster_pvp_flatten_move', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function animaster_pvp_fetch_latest_move($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battle_moves
        WHERE id_battle = :id_battle
        ORDER BY round DESC, order_in_turn DESC, id_battle_move DESC
        LIMIT 1
    ');
    $stmt->execute(['id_battle' => (int) $id_battle]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? animaster_pvp_flatten_move($row) : null;
}

function animaster_pvp_team_has_hp($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT COALESCE(SUM(COALESCE(current_hp, 0)), 0) AS tot
        FROM animals
        WHERE id_user_ig = :id_user_ig
          AND team_position > 0
    ');
    $stmt->execute(['id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['tot'] > 0 : false;
}

function animaster_pvp_finish_battle($conn, $battle_row, $winner_id, $end_reason)
{
    $id_battle = (int) $battle_row['id_battle_pvp'];
    $winner_id = (int) $winner_id;

    $winner_alliance = null;

    if ($winner_id > 0 && $winner_id === (int) $battle_row['id_user_ig_a'])
    {
        $winner_alliance = BattleRepository::SIDE_A;
    }
    else if ($winner_id > 0 && $winner_id === (int) $battle_row['id_user_ig_b'])
    {
        $winner_alliance = BattleRepository::SIDE_B;
    }

    BattleRepository::finishBattle($conn, $id_battle, (string) $end_reason, $winner_alliance);

    animaster_pvp_clear_user_flags($conn, (int) $battle_row['id_user_ig_a']);
    animaster_pvp_clear_user_flags($conn, (int) $battle_row['id_user_ig_b']);

    if (!class_exists('CombatSession'))
    {
        require_once __DIR__ . '/combat/CombatSession.php';
    }

    CombatSession::onBattleEnd($conn, CombatSession::TYPE_PVP, $id_battle);
}

function animaster_pvp_viewer_status($battle_row, $viewer_id, $raw_status)
{
    if ($raw_status === 'ongoing' || trim((string) $battle_row['flg_status']) === 'O')
    {
        return 'ongoing';
    }

    $viewer_id = (int) $viewer_id;
    $winner_id = (int) ($battle_row['id_winner_user_ig'] ?? 0);

    if ($winner_id <= 0)
    {
        return $raw_status;
    }

    if ($winner_id === $viewer_id)
    {
        return 'win';
    }

    return 'defeat';
}

function animaster_pvp_swap_move_for_viewer($move, $viewer_side)
{
    if ($viewer_side === 'a')
    {
        return $move;
    }

    $swapped = $move;
    $pairs = [];

    foreach ($move as $key => $value)
    {
        if (strpos($key, 'p_a_') === 0)
        {
            $suffix = substr($key, 4);

            if (!isset($pairs[$suffix]))
            {
                $pairs[$suffix] = [null, null];
            }

            $pairs[$suffix][0] = $value;
        }
        elseif (strpos($key, 'w_a_') === 0)
        {
            $suffix = substr($key, 4);

            if (!isset($pairs[$suffix]))
            {
                $pairs[$suffix] = [null, null];
            }

            $pairs[$suffix][1] = $value;
        }
    }

    foreach ($pairs as $suffix => $values)
    {
        if ($values[0] !== null || $values[1] !== null)
        {
            $swapped['p_a_' . $suffix] = $values[1];
            $swapped['w_a_' . $suffix] = $values[0];
        }
    }

    return $swapped;
}

function animaster_pvp_count_turn_choices($conn, $id_battle, $turn)
{
    return count(BattleRepository::fetchChoices($conn, (int) $id_battle, (int) $turn));
}

function animaster_pvp_user_has_turn_choice($conn, $id_battle, $turn, $id_user_ig)
{
    return BattleRepository::fetchChoiceByUser($conn, (int) $id_battle, (int) $turn, (int) $id_user_ig) !== null;
}

function animaster_pvp_save_turn_choice($conn, $id_battle, $turn, $id_user_ig, $type, $id)
{
    $id_participant = animaster_pvp_participant_id_for_user($conn, (int) $id_battle, (int) $id_user_ig);

    BattleRepository::saveChoice(
        $conn,
        (int) $id_battle,
        (int) $turn,
        (int) $id_user_ig,
        $id_participant,
        (string) $type,
        (int) $id,
        0
    );
}

function animaster_pvp_fetch_turn_choices($conn, $id_battle, $turn)
{
    return BattleRepository::fetchChoices($conn, (int) $id_battle, (int) $turn);
}

function animaster_pvp_clear_turn_choices($conn, $id_battle, $turn)
{
    BattleRepository::clearChoices($conn, (int) $id_battle, (int) $turn);
}

function animaster_pvp_count_resolved_turn_moves($conn, $id_battle, $turn)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS cnt
        FROM battle_moves
        WHERE id_battle = :id_battle
          AND round = :turn
          AND move_type <> \'start\'
    ');
    $stmt->execute([
        'id_battle' => (int) $id_battle,
        'turn' => (int) $turn
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['cnt'] : 0;
}

function animaster_pvp_state_from_move($move)
{
    return [
        'p_a_res_hp' => (int) $move['p_a_res_hp'],
        'w_a_res_hp' => (int) $move['w_a_res_hp'],
        'p_a_res_max_hp' => (int) $move['p_a_res_max_hp'],
        'w_a_res_max_hp' => (int) $move['w_a_res_max_hp'],
        'p_a_res_atk' => (float) $move['p_a_res_atk'],
        'p_a_res_def' => (float) $move['p_a_res_def'],
        'p_a_res_matk' => (float) $move['p_a_res_matk'],
        'p_a_res_mdef' => (float) $move['p_a_res_mdef'],
        'p_a_res_acc' => (int) $move['p_a_res_acc'],
        'p_a_res_eva' => (int) $move['p_a_res_eva'],
        'p_a_res_cr' => (int) $move['p_a_res_cr'],
        'p_a_res_spd' => (float) $move['p_a_res_spd'],
        'w_a_res_atk' => (float) $move['w_a_res_atk'],
        'w_a_res_def' => (float) $move['w_a_res_def'],
        'w_a_res_matk' => (float) $move['w_a_res_matk'],
        'w_a_res_mdef' => (float) $move['w_a_res_mdef'],
        'w_a_res_acc' => (int) $move['w_a_res_acc'],
        'w_a_res_eva' => (int) $move['w_a_res_eva'],
        'w_a_res_cr' => (int) $move['w_a_res_cr'],
        'w_a_res_spd' => (float) $move['w_a_res_spd'],
        'p_a_id' => (int) $move['p_a_id'],
        'w_a_id' => (int) $move['w_a_id'],
        'p_a_id_element' => (int) $move['p_a_id_element'],
        'w_a_id_element' => (int) $move['w_a_id_element'],
        'p_a_id_species' => (int) $move['p_a_id_species'],
        'w_a_id_species' => (int) $move['w_a_id_species'],
        'p_a_species' => $move['p_a_species'],
        'w_a_species' => $move['w_a_species'],
        'p_a_lvl' => (int) $move['p_a_lvl'],
        'w_a_lvl' => (int) $move['w_a_lvl'],
        'p_a_nickname' => $move['p_a_nickname'],
        'p_a_cur_exp' => (int) $move['p_a_cur_exp']
    ];
}

function animaster_pvp_record_move($conn, $battle_row, $turn, $order, $id_user_ig, $state, $move_result, $lang_suffix = '')
{
    $actor_side = ($id_user_ig === (int) $battle_row['id_user_ig_a']) ? 'a' : 'b';
    $protagonist_id = $actor_side === 'a' ? $state['p_a_id'] : $state['w_a_id'];
    $target_id = $actor_side === 'a' ? $state['w_a_id'] : $state['p_a_id'];

    $meta = animaster_pvp_move_meta(
        $conn,
        $state,
        $lang_suffix,
        'user_animal',
        $protagonist_id,
        'user_animal',
        $target_id
    );

    BattleRepository::insertMove($conn, [
        'id_battle' => (int) $battle_row['id_battle_pvp'],
        'round' => (int) $turn,
        'order_in_turn' => (int) $order,
        'id_actor_participant' => $actor_side === 'a' ? (int) $state['p_a_participant'] : (int) $state['w_a_participant'],
        'id_target_participant' => $actor_side === 'a' ? (int) $state['w_a_participant'] : (int) $state['p_a_participant'],
        'id_user_ig_actor' => (int) $id_user_ig,
        'move_type' => (string) $move_result['move_type'],
        'id_rif' => (int) $move_result['id_rif'],
        'move_speed' => (float) $move_result['move_speed'],
        'move_description' => (string) $move_result['move_description'],
        'move_hit' => (string) $move_result['move_hit'],
        'actor_hp_after' => $actor_side === 'a' ? (int) $state['p_a_res_hp'] : (int) $state['w_a_res_hp'],
        'target_hp_after' => $actor_side === 'a' ? (int) $state['w_a_res_hp'] : (int) $state['p_a_res_hp'],
        'resulting_battle_status' => (string) $move_result['b_status'],
        'meta' => $meta
    ]);
}

function animaster_pvp_apply_fighter_to_state(array &$state, $side, array $fighter)
{
    $prefix = ($side === 'w_a') ? 'w_a_res_' : 'p_a_res_';

    if (array_key_exists('current_hp', $fighter))
    {
        $state[$prefix . 'hp'] = (int) $fighter['current_hp'];
    }

    if (array_key_exists('max_hp', $fighter))
    {
        $state[$prefix . 'max_hp'] = (int) $fighter['max_hp'];
    }
}

function animaster_pvp_execute_action($conn, &$battle_row, $state, $id_user_ig, $type, $id, $lang_suffix)
{
    $id_user_ig = (int) $id_user_ig;
    $id = (int) $id;
    $actor_side = ($id_user_ig === (int) $battle_row['id_user_ig_a']) ? 'a' : 'b';

    $move_type = '';
    $move_hit = 'I';
    $move_description = '';
    $b_status = 'ongoing';
    $id_rif = 0;
    $move_speed = 0;

    if ($type === 'action' && $id === 4)
    {
        $move_type = 'escape';
        $move_hit = 'I';
        $move_speed = 999;
        $winner = $actor_side === 'a'
            ? (int) $battle_row['id_user_ig_b']
            : (int) $battle_row['id_user_ig_a'];
        $nick = $actor_side === 'a' ? $state['p_a_nickname'] : $state['w_a_species'];
        $move_description = $nick . ' fled from the duel.';

        if ($lang_suffix === '_it')
        {
            $move_description = $nick . ' è fuggito dal duello.';
        }
        elseif ($lang_suffix === '_pt')
        {
            $move_description = $nick . ' fugiu do duelo.';
        }

        animaster_pvp_finish_battle($conn, $battle_row, $winner, 'flee');
        $battle_row['flg_status'] = 'F';
        $battle_row['id_winner_user_ig'] = $winner;
        $b_status = 'pvp_end';
    }
    elseif ($type === 'ability' && $id > 0)
    {
        $stmt = $conn->prepare('
            SELECT id_ability, accuracy, power, m_power, effect, effect_chance, A.id_element,
                   ability' . preg_replace('/[^_a-z]/i', '', (string) $lang_suffix) . ' AS ability_name
            FROM abilities A
            WHERE id_ability = :id_ability
            LIMIT 1
        ');
        $stmt->execute(['id_ability' => $id]);
        $ability = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ability)
        {
            return ['error' => 'INVALID_ABILITY'];
        }

        if ($actor_side === 'a')
        {
            $attacker = [
                'lvl' => (int) $state['p_a_lvl'],
                'acc' => (int) $state['p_a_res_acc'],
                'cr' => (int) $state['p_a_res_cr'],
                'atk' => (float) $state['p_a_res_atk'],
                'def' => (float) $state['p_a_res_def'],
                'matk' => (float) $state['p_a_res_matk'],
                'mdef' => (float) $state['p_a_res_mdef'],
                'eva' => (int) $state['p_a_res_eva'],
                'spd' => (int) $state['p_a_res_spd'],
                'current_hp' => (int) $state['p_a_res_hp'],
                'max_hp' => (int) $state['p_a_res_max_hp'],
                'id_element' => (int) $state['p_a_id_element'],
                'nickname' => (string) $state['p_a_nickname'],
            ];
            $defender = [
                'lvl' => (int) $state['w_a_lvl'],
                'acc' => (int) $state['w_a_res_acc'],
                'cr' => (int) $state['w_a_res_cr'],
                'atk' => (float) $state['w_a_res_atk'],
                'def' => (float) $state['w_a_res_def'],
                'matk' => (float) $state['w_a_res_matk'],
                'mdef' => (float) $state['w_a_res_mdef'],
                'eva' => (int) $state['w_a_res_eva'],
                'spd' => (int) $state['w_a_res_spd'],
                'current_hp' => (int) $state['w_a_res_hp'],
                'max_hp' => (int) $state['w_a_res_max_hp'],
                'id_element' => (int) $state['w_a_id_element'],
                'nickname' => (string) $state['w_a_species'],
            ];
            $move_speed = (int) $state['p_a_res_spd'];
        }
        else
        {
            $attacker = [
                'lvl' => (int) $state['w_a_lvl'],
                'acc' => (int) $state['w_a_res_acc'],
                'cr' => (int) $state['w_a_res_cr'],
                'atk' => (float) $state['w_a_res_atk'],
                'def' => (float) $state['w_a_res_def'],
                'matk' => (float) $state['w_a_res_matk'],
                'mdef' => (float) $state['w_a_res_mdef'],
                'eva' => (int) $state['w_a_res_eva'],
                'spd' => (int) $state['w_a_res_spd'],
                'current_hp' => (int) $state['w_a_res_hp'],
                'max_hp' => (int) $state['w_a_res_max_hp'],
                'id_element' => (int) $state['w_a_id_element'],
                'nickname' => (string) $state['w_a_species'],
            ];
            $defender = [
                'lvl' => (int) $state['p_a_lvl'],
                'acc' => (int) $state['p_a_res_acc'],
                'cr' => (int) $state['p_a_res_cr'],
                'atk' => (float) $state['p_a_res_atk'],
                'def' => (float) $state['p_a_res_def'],
                'matk' => (float) $state['p_a_res_matk'],
                'mdef' => (float) $state['p_a_res_mdef'],
                'eva' => (int) $state['p_a_res_eva'],
                'spd' => (int) $state['p_a_res_spd'],
                'current_hp' => (int) $state['p_a_res_hp'],
                'max_hp' => (int) $state['p_a_res_max_hp'],
                'id_element' => (int) $state['p_a_id_element'],
                'nickname' => (string) $state['p_a_nickname'],
            ];
            $move_speed = (int) $state['w_a_res_spd'];
        }

        if (!class_exists('MoveResolver'))
        {
            require_once __DIR__ . '/combat/MoveResolver.php';
        }

        $id_battle = (int) $battle_row['id_battle_pvp'];
        $applied_turn = (int) $battle_row['current_turn'];
        $p_a_entity = [
            'entity_type' => 'animal',
            'id_entity' => (int) $state['p_a_id'],
            'id_user_ig' => (int) $battle_row['id_user_ig_a'],
        ];
        $w_a_entity = [
            'entity_type' => 'animal',
            'id_entity' => (int) $state['w_a_id'],
            'id_user_ig' => (int) $battle_row['id_user_ig_b'],
        ];

        if ($actor_side === 'a')
        {
            $attacker_entity = $p_a_entity;
            $defender_entity = $w_a_entity;
        }
        else
        {
            $attacker_entity = $w_a_entity;
            $defender_entity = $p_a_entity;
        }

        $move_result = MoveResolver::resolveAbility($ability, $attacker, $defender, [
            'lang_suffix' => (string) $lang_suffix,
            'conn' => $conn,
            'battle_type' => CombatSession::TYPE_PVP,
            'id_battle' => $id_battle,
            'applied_at_turn' => $applied_turn,
            'attacker_entity' => $attacker_entity,
            'defender_entity' => $defender_entity,
        ]);

        if ($actor_side === 'a')
        {
            animaster_pvp_apply_fighter_to_state($state, 'p_a', $move_result['attacker']);
            animaster_pvp_apply_fighter_to_state($state, 'w_a', $move_result['defender']);
        }
        else
        {
            animaster_pvp_apply_fighter_to_state($state, 'w_a', $move_result['attacker']);
            animaster_pvp_apply_fighter_to_state($state, 'p_a', $move_result['defender']);
        }

        $move_hit = $move_result['move_hit'];
        $move_description = $move_result['move_description'];

        if ($state['w_a_res_hp'] < 0)
        {
            $state['w_a_res_hp'] = 0;
        }

        if ($state['p_a_res_hp'] < 0)
        {
            $state['p_a_res_hp'] = 0;
        }

        $move_type = 'ability';
        $id_rif = $id;

        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/buffs.php';
        }

        BUFFS::persistAnimalHpAfterBattle($conn, (int) $state['p_a_id'], (int) $state['p_a_res_hp']);
        BUFFS::persistAnimalHpAfterBattle($conn, (int) $state['w_a_id'], (int) $state['w_a_res_hp']);

        // Participant rows are the authoritative live state for the next turn.
        if (!empty($state['p_a_participant']))
        {
            BattleRepository::updateParticipant($conn, (int) $state['p_a_participant'], [
                'current_hp' => (int) $state['p_a_res_hp'],
                'flg_fainted' => (int) $state['p_a_res_hp'] <= 0 ? 'S' : 'N'
            ]);
        }

        if (!empty($state['w_a_participant']))
        {
            BattleRepository::updateParticipant($conn, (int) $state['w_a_participant'], [
                'current_hp' => (int) $state['w_a_res_hp'],
                'flg_fainted' => (int) $state['w_a_res_hp'] <= 0 ? 'S' : 'N'
            ]);
        }

        if ($state['w_a_res_hp'] <= 0)
        {
            if (!animaster_pvp_team_has_hp($conn, (int) $battle_row['id_user_ig_b']))
            {
                animaster_pvp_finish_battle($conn, $battle_row, (int) $battle_row['id_user_ig_a'], 'knockout');
                $battle_row['flg_status'] = 'F';
                $battle_row['id_winner_user_ig'] = (int) $battle_row['id_user_ig_a'];
                $b_status = 'pvp_end';
            }
        }

        if ($state['p_a_res_hp'] <= 0 && $b_status === 'ongoing')
        {
            if (!animaster_pvp_team_has_hp($conn, (int) $battle_row['id_user_ig_a']))
            {
                animaster_pvp_finish_battle($conn, $battle_row, (int) $battle_row['id_user_ig_b'], 'knockout');
                $battle_row['flg_status'] = 'F';
                $battle_row['id_winner_user_ig'] = (int) $battle_row['id_user_ig_b'];
                $b_status = 'pvp_end';
            }
        }
    }
    else
    {
        return ['error' => 'INVALID_ACTION'];
    }

    return [
        'ok' => true,
        'state' => $state,
        'move_type' => $move_type,
        'move_description' => $move_description,
        'move_hit' => $move_hit,
        'b_status' => $b_status,
        'id_rif' => $id_rif,
        'move_speed' => $move_speed
    ];
}

function animaster_pvp_resolve_turn_choices($conn, &$battle_row, $turn, $lang_suffix)
{
    $id_battle = (int) $battle_row['id_battle_pvp'];
    $choices = animaster_pvp_fetch_turn_choices($conn, $id_battle, $turn);

    if (!Permissions::pvpShouldResolveTurn(count($choices), $choices))
    {
        return ['error' => 'WAITING_OPPONENT'];
    }

    $state = animaster_pvp_current_state($conn, $id_battle);

    if (!$state)
    {
        return ['error' => 'NO_STATE'];
    }

    $first_user = animaster_pvp_first_actor_user_id($battle_row, $state['p_a_res_spd'], $state['w_a_res_spd']);
    $second_user = animaster_pvp_second_actor_user_id($battle_row, $state['p_a_res_spd'], $state['w_a_res_spd']);
    $ordered_users = TurnQueue::orderPvpActorUserIds(
        $first_user,
        $second_user,
        $state['p_a_res_spd'],
        $state['w_a_res_spd']
    );

    $choice_map = [];

    foreach ($choices as $choice)
    {
        $choice_map[(int) $choice['id_user_ig']] = $choice;
    }

    $order = 0;

    foreach ($ordered_users as $actor_id)
    {
        if (trim((string) $battle_row['flg_status']) !== 'O')
        {
            break;
        }

        if ($state['p_a_res_hp'] <= 0 || $state['w_a_res_hp'] <= 0)
        {
            break;
        }

        if (!isset($choice_map[$actor_id]))
        {
            continue;
        }

        $choice = $choice_map[$actor_id];
        $order++;
        $result = animaster_pvp_execute_action(
            $conn,
            $battle_row,
            $state,
            $actor_id,
            (string) $choice['action_type'],
            (int) $choice['action_id'],
            $lang_suffix
        );

        if (!empty($result['error']))
        {
            return $result;
        }

        $state = $result['state'];
        animaster_pvp_record_move($conn, $battle_row, $turn, $order, $actor_id, $state, $result, $lang_suffix);

        if ($result['b_status'] !== 'ongoing')
        {
            break;
        }
    }

    $advance = CombatSession::completePvpPlanningTurn(
        $conn,
        $id_battle,
        $turn,
        trim((string) $battle_row['flg_status']) === 'O',
        function ($resolvedTurn) use ($conn, $id_battle)
        {
            animaster_pvp_clear_turn_choices($conn, $id_battle, $resolvedTurn);
        }
    );

    $battle_row['awaiting_user_ig'] = $advance['awaiting_user_ig'];
    $battle_row['current_turn'] = $advance['current_turn'];

    return ['ok' => true];
}

function animaster_pvp_build_meta($conn, $battle_row, $id_user_ig, $turn_moves, $view_turn = null, $lang_suffix = '')
{
    $id_user_ig = (int) $id_user_ig;
    $id_battle = (int) $battle_row['id_battle_pvp'];
    $current_turn = (int) $battle_row['current_turn'];
    $view_turn = $view_turn !== null ? (int) $view_turn : $current_turn;
    $battle_open = trim((string) $battle_row['flg_status']) === 'O';
    $current_resolved = animaster_pvp_count_resolved_turn_moves($conn, $id_battle, $current_turn);
    $choice_count = animaster_pvp_count_turn_choices($conn, $id_battle, $current_turn);
    $has_submitted = animaster_pvp_user_has_turn_choice($conn, $id_battle, $current_turn, $id_user_ig);
    $turn_complete = !$battle_open
        || $view_turn < $current_turn
        || ($view_turn === $current_turn && $current_resolved >= 2);

    $needs_recovery = false;

    if (!$battle_open)
    {
        $needs_recovery = (int) ($battle_row['id_winner_user_ig'] ?? 0) !== $id_user_ig
            && !animaster_pvp_team_has_hp($conn, $id_user_ig);
    }

    $combatants = [];
    $state = animaster_pvp_current_state($conn, $id_battle);

    if ($state)
    {
        $combatants = CombatSession::combatantsFromPvpState($state, $battle_row);
    }
    else if ($turn_moves)
    {
        $last_move = $turn_moves[count($turn_moves) - 1];
        $combatants = CombatSession::combatantsFromPvpState(animaster_pvp_state_from_move($last_move), $battle_row);
    }

    return CombatSession::attachCombatants(
        [
            'battle_type' => CombatSession::TYPE_PVP,
            'awaiting_user_ig' => null,
            'can_act' => $battle_open && !$has_submitted && $current_resolved === 0,
            'submitted' => $has_submitted && $current_resolved === 0,
            'opponent_locked' => !$has_submitted && $choice_count > 0,
            'opponent_submitted' => $has_submitted && $choice_count >= 2 && $current_resolved === 0,
            'both_locked' => $choice_count >= 2 && $current_resolved === 0,
            'choices_locked' => $choice_count >= 2 && $current_resolved === 0,
            'turn_complete' => $turn_complete,
            'battle_finished' => !$battle_open,
            'needs_recovery' => $needs_recovery,
            'current_turn' => $current_turn,
        ],
        $combatants,
        $conn,
        [
            'battle_type' => CombatSession::TYPE_PVP,
            'id_battle' => $id_battle,
            'lang' => $lang_suffix,
        ]
    );
}

function animaster_pvp_process_action($conn, $battle_row, $id_user_ig, $turn, $type, $id, $lang_suffix)
{
    $id_user_ig = (int) $id_user_ig;
    $turn = (int) $turn;
    $id = (int) $id;
    $id_battle = (int) $battle_row['id_battle_pvp'];
    $current_turn = (int) $battle_row['current_turn'];

    if (trim((string) $battle_row['flg_status']) !== 'O')
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    if ($turn < 1)
    {
        return ['error' => 'INVALID_TURN'];
    }

    if (!CombatSession::isPvpTurnInSync($turn, $current_turn))
    {
        return ['error' => 'INVALID_TURN'];
    }

    if (animaster_pvp_count_resolved_turn_moves($conn, $id_battle, $turn) > 0)
    {
        return ['error' => 'TURN_COMPLETE'];
    }

    if (animaster_pvp_user_has_turn_choice($conn, $id_battle, $turn, $id_user_ig))
    {
        return ['error' => 'ALREADY_SUBMITTED'];
    }

    animaster_pvp_save_turn_choice($conn, $id_battle, $turn, $id_user_ig, $type, $id);

    $choices = animaster_pvp_fetch_turn_choices($conn, $id_battle, $turn);

    if (!Permissions::pvpShouldResolveTurn(count($choices), $choices))
    {
        return ['ok' => true, 'waiting_opponent' => true];
    }

    return animaster_pvp_resolve_turn_choices($conn, $battle_row, $turn, $lang_suffix);
}
function animaster_pvp_get_battle_info($conn, $params)
{
    $id_user_ig = (int) ($params['id_user_ig'] ?? 0);
    $id_battle = (int) ($params['id_battle'] ?? 0);
    $turn = (int) ($params['turn'] ?? 0);
    $restarting = ($params['restarting_old_battle'] ?? 'N') === 'S';
    $lang = (string) ($params['lang'] ?? '');
    $lang_suffix = '';

    if ($lang !== '' && $lang[0] !== '_')
    {
        $lang_suffix = '_' . $lang;
    }
    elseif ($lang !== '')
    {
        $lang_suffix = $lang;
    }

    if ($id_user_ig <= 0 || $id_battle <= 0)
    {
        return ['error' => 'INVALID_BATTLE_REQUEST'];
    }

    $battle_row = animaster_pvp_fetch_battle_row($conn, $id_battle);

    if (!$battle_row || animaster_pvp_user_side($battle_row, $id_user_ig) === '')
    {
        return ['error' => 'BATTLE_NOT_FOUND'];
    }

    $type = isset($params['type']) ? (string) $params['type'] : '';
    $action_id = isset($params['id']) ? (int) $params['id'] : 0;

    if ($type !== '' && !$restarting && $turn > 0)
    {
        $result = animaster_pvp_process_action(
            $conn,
            $battle_row,
            $id_user_ig,
            $turn,
            $type,
            $action_id,
            $lang_suffix
        );

        if (!empty($result['error']))
        {
            return $result;
        }

        $battle_row = animaster_pvp_fetch_battle_row($conn, $id_battle);
    }

    if ($restarting && $turn > 0)
    {
        $latest = animaster_pvp_fetch_latest_move($conn, $id_battle);

        if ($latest && (int) $latest['turn'] > $turn)
        {
            $turn = (int) $latest['turn'];
        }
    }

    $requested_turn = $turn;
    $current_turn = (int) $battle_row['current_turn'];
    $moves = animaster_pvp_fetch_moves_for_turn($conn, $id_battle, $requested_turn, $lang_suffix);

    if ($restarting && $requested_turn > 0 && count($moves) === 0)
    {
        $latest = animaster_pvp_fetch_latest_move($conn, $id_battle);

        if ($latest)
        {
            $turn = (int) $latest['turn'];
            $moves = animaster_pvp_fetch_moves_for_turn($conn, $id_battle, $turn, $lang_suffix);
        }
    }
    elseif (count($moves) === 0 && $requested_turn > 0 && $requested_turn >= $current_turn)
    {
        // Current turn has no resolved moves yet — both players still choosing.
        $moves = animaster_pvp_fetch_all_moves($conn, $id_battle, $lang_suffix);
        $turn = $requested_turn;
    }
    elseif (count($moves) === 0 && $requested_turn > 0)
    {
        $latest = animaster_pvp_fetch_latest_move($conn, $id_battle);

        if ($latest)
        {
            $turn = (int) $latest['turn'];
            $moves = animaster_pvp_fetch_moves_for_turn($conn, $id_battle, $turn, $lang_suffix);
        }
    }

    if (trim((string) $battle_row['flg_status']) !== 'O')
    {
        $latest = animaster_pvp_fetch_latest_move($conn, $id_battle);

        if ($latest && (int) $latest['turn'] > 0)
        {
            $turn = (int) $latest['turn'];
            $moves = animaster_pvp_fetch_moves_for_turn($conn, $id_battle, $turn, $lang_suffix);
        }
    }

    if (count($moves) === 0)
    {
        return ['error' => 'NO_BATTLE_MOVES'];
    }

    $viewer_side = animaster_pvp_user_side($battle_row, $id_user_ig);
    $output_moves = [];

    foreach ($moves as $move)
    {
        $row = animaster_pvp_swap_move_for_viewer($move, $viewer_side);
        $row['resulting_battle_status'] = animaster_pvp_viewer_status(
            $battle_row,
            $id_user_ig,
            $row['resulting_battle_status']
        );

        if (!empty($row['w_a_species_i18n']))
        {
            $row['w_a_species'] = $row['w_a_species_i18n'];
        }

        if (!empty($row['p_a_species_i18n']))
        {
            $row['p_a_species'] = $row['p_a_species_i18n'];
        }

        $output_moves[] = $row;
    }

    return [
        'ok' => true,
        'moves' => $output_moves,
        'meta' => animaster_pvp_build_meta($conn, $battle_row, $id_user_ig, $moves, $turn, $lang_suffix)
    ];
}
