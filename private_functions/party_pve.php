<?php

if (!defined('ANIMASTER_PARTY_PVE_JOIN_RADIUS'))
{
    define('ANIMASTER_PARTY_PVE_JOIN_RADIUS', 50);
}

require_once __DIR__ . '/party.php';

function animaster_party_pve_distance($x1, $z1, $x2, $z2)
{
    $dx = (float) $x1 - (float) $x2;
    $dz = (float) $z1 - (float) $z2;

    return sqrt(($dx * $dx) + ($dz * $dz));
}

function animaster_party_pve_member_eligible(array $member, $ref_x, $ref_z, $id_zone)
{
    if (empty($member['flg_online']))
    {
        return false;
    }

    if ((int) $member['id_zone'] !== (int) $id_zone)
    {
        return false;
    }

    if ($member['position_x'] === null || $member['position_z'] === null)
    {
        return false;
    }

    return animaster_party_pve_distance(
        $member['position_x'],
        $member['position_z'],
        $ref_x,
        $ref_z
    ) <= ANIMASTER_PARTY_PVE_JOIN_RADIUS;
}

function animaster_party_pve_fetch_eligible_members($conn, $id_party, $ref_x, $ref_z, $id_zone)
{
    $members = animaster_party_fetch_members($conn, (int) $id_party);
    $eligible = [];

    foreach ($members as $member)
    {
        if (!animaster_party_pve_member_eligible($member, $ref_x, $ref_z, $id_zone))
        {
            continue;
        }

        if (empty($member['lead_animal']) || (int) ($member['lead_animal']['id_animal'] ?? 0) <= 0)
        {
            continue;
        }

        if ((int) ($member['lead_animal']['current_hp'] ?? 0) <= 0)
        {
            continue;
        }

        $eligible[] = $member;
    }

    return $eligible;
}

function animaster_party_pve_fetch_wild_snapshot($conn, $id_wild_animal, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);
    $id_wild_animal = (int) $id_wild_animal;

    $sql = '
        SELECT WA.id_wild_animal, WA.id_species, WA.level AS lvl, WA.hp,
               WA.atk, WA.def, WA.matk, WA.mdef, WA.acc, WA.eva, WA.cr, WA.spd,
               WA.id_element, WA.id_zone,
               L.species' . $lang_suffix . ' AS species
        FROM wild_animals WA
        INNER JOIN species L ON L.id_species = WA.id_species
        WHERE WA.id_wild_animal = :id_wild
        LIMIT 1
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_wild' => $id_wild_animal]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    $hp = (int) $row['hp'];

    return [
        'id_animal' => (int) $row['id_wild_animal'],
        'id_user_ig' => 0,
        'lvl' => (int) $row['lvl'],
        'current_hp' => $hp,
        'max_hp' => $hp > 0 ? $hp : 1,
        'experience' => 0,
        'nickname' => (string) $row['species'],
        'id_element' => (int) $row['id_element'],
        'id_species' => (int) $row['id_species'],
        'species' => (string) $row['species'],
        'element' => '',
        'id_zone' => (int) $row['id_zone'],
        'atk' => (int) $row['atk'],
        'def' => (int) $row['def'],
        'matk' => (int) $row['matk'],
        'mdef' => (int) $row['mdef'],
        'acc' => (int) $row['acc'],
        'eva' => (int) $row['eva'],
        'cr' => (int) $row['cr'],
        'spd' => (int) $row['spd']
    ];
}

function animaster_party_pve_participant_row_from_snap(array $snap, $side, $id_user_ig, $team_position)
{
    return [
        'id_user_ig' => $side === 'party' ? (int) $id_user_ig : null,
        'id_animal' => (int) $snap['id_animal'],
        'side' => $side,
        'team_position' => $team_position,
        'flg_active' => 'S',
        'flg_fainted' => 'N',
        'current_hp' => (int) $snap['current_hp'],
        'max_hp' => (int) $snap['max_hp'],
        'atk' => (int) $snap['atk'],
        'def' => (int) $snap['def'],
        'matk' => (int) $snap['matk'],
        'mdef' => (int) $snap['mdef'],
        'acc' => (int) $snap['acc'],
        'eva' => (int) $snap['eva'],
        'cr' => (int) $snap['cr'],
        'spd' => (int) $snap['spd'],
        'id_species' => (int) $snap['id_species'],
        'id_element' => (int) $snap['id_element'],
        'lvl' => (int) $snap['lvl'],
        'nickname' => (string) ($snap['nickname'] ?: $snap['species']),
        'species_name' => (string) $snap['species']
    ];
}

function animaster_party_pve_insert_participant($conn, $id_battle, array $row)
{
    $stmt = $conn->prepare('
        INSERT INTO battles_party_pve_participants (
            id_battle_party_pve, id_user_ig, id_animal, side, team_position,
            flg_active, flg_fainted, current_hp, max_hp,
            atk, def, matk, mdef, acc, eva, cr, spd,
            id_species, id_element, lvl, nickname, species_name
        ) VALUES (
            :id_battle, :id_user_ig, :id_animal, :side, :team_position,
            :flg_active, :flg_fainted, :current_hp, :max_hp,
            :atk, :def, :matk, :mdef, :acc, :eva, :cr, :spd,
            :id_species, :id_element, :lvl, :nickname, :species_name
        )
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig' => $row['id_user_ig'],
        ':id_animal' => (int) $row['id_animal'],
        ':side' => $row['side'],
        ':team_position' => $row['team_position'],
        ':flg_active' => $row['flg_active'],
        ':flg_fainted' => $row['flg_fainted'],
        ':current_hp' => (int) $row['current_hp'],
        ':max_hp' => (int) $row['max_hp'],
        ':atk' => (int) $row['atk'],
        ':def' => (int) $row['def'],
        ':matk' => (int) $row['matk'],
        ':mdef' => (int) $row['mdef'],
        ':acc' => (int) $row['acc'],
        ':eva' => (int) $row['eva'],
        ':cr' => (int) $row['cr'],
        ':spd' => (int) $row['spd'],
        ':id_species' => (int) $row['id_species'],
        ':id_element' => (int) $row['id_element'],
        ':lvl' => (int) $row['lvl'],
        ':nickname' => (string) $row['nickname'],
        ':species_name' => (string) $row['species_name']
    ]);

    return (int) $conn->lastInsertId();
}

function animaster_party_pve_fetch_participants($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve_participants
        WHERE id_battle_party_pve = :id_battle
        ORDER BY side ASC, team_position ASC, id_battle_party_pve_participant ASC
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function animaster_party_pve_fetch_participant($conn, $id_participant)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve_participants
        WHERE id_battle_party_pve_participant = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => (int) $id_participant]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_fetch_battle($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve
        WHERE id_battle_party_pve = :id_battle
        LIMIT 1
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_user_in_battle($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT 1
        FROM battles_party_pve_participants
        WHERE id_battle_party_pve = :id_battle
          AND id_user_ig = :id_user_ig
          AND side = \'party\'
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return (bool) $stmt->fetchColumn();
}

function animaster_party_pve_active_for_user($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT b.id_battle_party_pve, b.current_turn, b.awaiting_user_ig
        FROM battles_party_pve b
        INNER JOIN battles_party_pve_participants p
            ON p.id_battle_party_pve = b.id_battle_party_pve
           AND p.id_user_ig = :id_user_ig
           AND p.side = \'party\'
        WHERE b.flg_status = \'O\'
        ORDER BY b.id_battle_party_pve DESC
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    return [
        'id_battle' => (int) $row['id_battle_party_pve'],
        'battle_type' => 'party_pve',
        'current_battle_turn' => (int) $row['current_turn'],
        'awaiting_user_ig' => $row['awaiting_user_ig'] !== null ? (int) $row['awaiting_user_ig'] : 0
    ];
}

function animaster_party_pve_party_has_active_battle($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT id_battle_party_pve
        FROM battles_party_pve
        WHERE id_party = :id_party
          AND flg_status = \'O\'
        LIMIT 1
    ');
    $stmt->execute([':id_party' => (int) $id_party]);

    return (int) $stmt->fetchColumn();
}

function animaster_party_pve_rebuild_queue(array $participants)
{
    $alive = [];

    foreach ($participants as $p)
    {
        if (trim((string) $p['flg_fainted']) === 'S')
        {
            continue;
        }

        if ((int) $p['current_hp'] <= 0)
        {
            continue;
        }

        $alive[] = $p;
    }

    usort($alive, function ($a, $b)
    {
        $spd_a = (int) $a['spd'];
        $spd_b = (int) $b['spd'];

        if ($spd_a !== $spd_b)
        {
            return $spd_b <=> $spd_a;
        }

        return (int) $a['id_battle_party_pve_participant'] <=> (int) $b['id_battle_party_pve_participant'];
    });

    $queue = [];

    foreach ($alive as $p)
    {
        $queue[] = (int) $p['id_battle_party_pve_participant'];
    }

    return $queue;
}

function animaster_party_pve_get_actor_from_battle($conn, array $battle)
{
    $participants = animaster_party_pve_fetch_participants($conn, (int) $battle['id_battle_party_pve']);
    $queue = json_decode((string) ($battle['turn_queue_json'] ?? ''), true);

    if (!is_array($queue) || !$queue)
    {
        $queue = animaster_party_pve_rebuild_queue($participants);
    }

    $turn_index = (int) $battle['turn_index'];

    if ($turn_index < 0 || $turn_index >= count($queue))
    {
        $queue = animaster_party_pve_rebuild_queue($participants);
        $turn_index = 0;
    }

    if (!$queue)
    {
        return null;
    }

    $actor_id = (int) $queue[$turn_index];
    $actor = animaster_party_pve_fetch_participant($conn, $actor_id);

    if (!$actor || trim((string) $actor['flg_fainted']) === 'S' || (int) $actor['current_hp'] <= 0)
    {
        return null;
    }

    return $actor;
}

function animaster_party_pve_get_wild_participant($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve_participants
        WHERE id_battle_party_pve = :id_battle
          AND side = \'wild\'
        LIMIT 1
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_save_participant($conn, array $participant)
{
    $flg_fainted = (int) $participant['current_hp'] <= 0 ? 'S' : 'N';

    $stmt = $conn->prepare('
        UPDATE battles_party_pve_participants
        SET current_hp = :current_hp,
            max_hp = :max_hp,
            atk = :atk,
            def = :def,
            matk = :matk,
            mdef = :mdef,
            acc = :acc,
            eva = :eva,
            cr = :cr,
            spd = :spd,
            flg_fainted = :flg_fainted
        WHERE id_battle_party_pve_participant = :id
    ');
    $stmt->execute([
        ':current_hp' => (int) $participant['current_hp'],
        ':max_hp' => (int) $participant['max_hp'],
        ':atk' => (int) $participant['atk'],
        ':def' => (int) $participant['def'],
        ':matk' => (int) $participant['matk'],
        ':mdef' => (int) $participant['mdef'],
        ':acc' => (int) $participant['acc'],
        ':eva' => (int) $participant['eva'],
        ':cr' => (int) $participant['cr'],
        ':spd' => (int) $participant['spd'],
        ':flg_fainted' => $flg_fainted,
        ':id' => (int) $participant['id_battle_party_pve_participant']
    ]);

    $participant['flg_fainted'] = $flg_fainted;

    if ($participant['side'] === 'party' && $participant['id_user_ig'])
    {
        $stmt = $conn->prepare('
            UPDATE animals
            SET current_hp = :current_hp, max_hp = :max_hp
            WHERE id_animal = :id_animal
        ');
        $stmt->execute([
            ':current_hp' => (int) $participant['current_hp'],
            ':max_hp' => (int) $participant['max_hp'],
            ':id_animal' => (int) $participant['id_animal']
        ]);
    }
}

function animaster_party_pve_participant_to_move_stats(array $p, $prefix)
{
    return [
        $prefix . '_id' => (int) $p['id_animal'],
        $prefix . '_id_element' => (int) $p['id_element'],
        $prefix . '_id_species' => (int) $p['id_species'],
        $prefix . '_species' => (string) $p['species_name'],
        $prefix . '_lvl' => (int) $p['lvl'],
        $prefix . '_nickname' => (string) $p['nickname'],
        $prefix . '_cur_exp' => 0,
        $prefix . '_res_hp' => (int) $p['current_hp'],
        $prefix . '_res_max_hp' => (int) $p['max_hp'],
        $prefix . '_res_atk' => (float) $p['atk'],
        $prefix . '_res_def' => (float) $p['def'],
        $prefix . '_res_matk' => (float) $p['matk'],
        $prefix . '_res_mdef' => (float) $p['mdef'],
        $prefix . '_res_acc' => (int) $p['acc'],
        $prefix . '_res_eva' => (int) $p['eva'],
        $prefix . '_res_cr' => (int) $p['cr'],
        $prefix . '_res_spd' => (float) $p['spd']
    ];
}

function animaster_party_pve_fetch_move_rows($conn, $id_battle, $turn, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $sql = '
        SELECT M.*,
               WE.element' . $lang_suffix . ' AS w_a_element,
               PE.element' . $lang_suffix . ' AS p_a_element,
               WE.color AS w_a_element_color,
               PE.color AS p_a_element_color
        FROM battles_party_pve_moves M
        LEFT JOIN elements WE ON WE.id_element = M.w_a_id_element
        LEFT JOIN elements PE ON PE.id_element = M.p_a_id_element
        WHERE M.id_battle_party_pve = :id_battle
          AND M.turn = :turn
        ORDER BY M.order_in_turn ASC, M.id_battle_party_pve_move ASC
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':turn' => (int) $turn
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function animaster_party_pve_move_exists($conn, $id_battle, $turn)
{
    $stmt = $conn->prepare('
        SELECT 1
        FROM battles_party_pve_moves
        WHERE id_battle_party_pve = :id_battle
          AND turn = :turn
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':turn' => (int) $turn
    ]);

    return (bool) $stmt->fetchColumn();
}

function animaster_party_pve_max_turn($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT COALESCE(MAX(turn), 0)
        FROM battles_party_pve_moves
        WHERE id_battle_party_pve = :id_battle
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return (int) $stmt->fetchColumn();
}

function animaster_party_pve_party_alive_count(array $participants)
{
    $count = 0;

    foreach ($participants as $p)
    {
        if ($p['side'] !== 'party')
        {
            continue;
        }

        if (trim((string) $p['flg_fainted']) === 'S')
        {
            continue;
        }

        if ((int) $p['current_hp'] <= 0)
        {
            continue;
        }

        $count++;
    }

    return $count;
}

function animaster_party_pve_check_end($conn, array $battle, array $participants, array $wild)
{
    if (trim((string) $battle['flg_status']) !== 'O')
    {
        return $battle['end_reason'] ?: 'ongoing';
    }

    if ((int) $wild['current_hp'] <= 0)
    {
        return 'victory';
    }

    if (animaster_party_pve_party_alive_count($participants) <= 0)
    {
        return 'defeat';
    }

    return 'ongoing';
}

function animaster_party_pve_finish_battle($conn, array $battle, $status, $id_wild_animal, array $party_participants, array $wild, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_party_pve'];
    $end_reason = $status;

    $stmt = $conn->prepare('
        UPDATE battles_party_pve
        SET flg_status = \'F\',
            end_reason = :end_reason,
            awaiting_user_ig = NULL,
            dt_finished = NOW()
        WHERE id_battle_party_pve = :id_battle
    ');
    $stmt->execute([
        ':end_reason' => $end_reason,
        ':id_battle' => $id_battle
    ]);

    $stmt = $conn->prepare('
        UPDATE wild_animals
        SET id_battle = 0, battle_type = NULL, dt_modifica = NOW()
        WHERE id_wild_animal = :id_wild
    ');
    $stmt->execute([':id_wild' => (int) $id_wild_animal]);

    if ($status === 'victory')
    {
        if (!class_exists('FUNZIONI'))
        {
            require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/funzioni.php';
        }

        foreach ($party_participants as $p)
        {
            if ($p['side'] !== 'party' || !$p['id_user_ig'])
            {
                continue;
            }

            FUNZIONI::AddExpFromWildAnimal(
                $conn,
                (int) $p['id_user_ig'],
                (int) $p['id_animal'],
                (int) $wild['id_species'],
                (int) $wild['lvl'],
                $lang_suffix
            );

            FUNZIONI::AddDropsWildAnimalUser(
                $conn,
                (int) $wild['id_species'],
                (int) $wild['lvl'],
                (int) $p['id_user_ig'],
                $lang_suffix
            );
        }

        $stmt = $conn->prepare('DELETE FROM wild_animals WHERE id_wild_animal = :id_wild');
        $stmt->execute([':id_wild' => (int) $id_wild_animal]);
    }
    else if ($status === 'fled')
    {
        $stmt = $conn->prepare('
            UPDATE wild_animals
            SET id_battle = 0, battle_type = NULL, dt_modifica = NOW()
            WHERE id_wild_animal = :id_wild
        ');
        $stmt->execute([':id_wild' => (int) $id_wild_animal]);
    }
}

function animaster_party_pve_advance_turn($conn, array $battle)
{
    $participants = animaster_party_pve_fetch_participants($conn, (int) $battle['id_battle_party_pve']);
    $queue = animaster_party_pve_rebuild_queue($participants);
    $turn_index = ((int) $battle['turn_index']) + 1;

    if ($turn_index >= count($queue))
    {
        $turn_index = 0;
    }

    if (!$queue)
    {
        $awaiting = null;
    }
    else
    {
        $actor = animaster_party_pve_fetch_participant($conn, (int) $queue[$turn_index]);
        $awaiting = ($actor && $actor['side'] === 'party') ? (int) $actor['id_user_ig'] : null;
    }

    $stmt = $conn->prepare('
        UPDATE battles_party_pve
        SET turn_queue_json = :queue_json,
            turn_index = :turn_index,
            awaiting_user_ig = :awaiting_user_ig,
            dt_m = NOW()
        WHERE id_battle_party_pve = :id_battle
    ');
    $stmt->execute([
        ':queue_json' => json_encode($queue),
        ':turn_index' => $turn_index,
        ':awaiting_user_ig' => $awaiting,
        ':id_battle' => (int) $battle['id_battle_party_pve']
    ]);
}

function animaster_party_pve_insert_move(
    $conn,
    $id_battle,
    $turn,
    $id_user_ig_actor,
    array $party_actor,
    array $wild,
    $move_type,
    $id_rif,
    $move_description,
    $move_hit,
    $battle_status,
    $protagonist_type,
    $id_protagonist,
    $target_type,
    $id_target
)
{
    $p = animaster_party_pve_participant_to_move_stats($party_actor, 'p_a');
    $w = animaster_party_pve_participant_to_move_stats($wild, 'w_a');

    $stmt = $conn->prepare('
        INSERT INTO battles_party_pve_moves (
            id_battle_party_pve, id_user_ig_actor, dt_creazione, turn, move_type, id_rif,
            move_speed, order_in_turn, protagonist_type, id_protagonist, target_type, id_target,
            w_a_res_atk, w_a_res_def, w_a_res_matk, w_a_res_mdef, w_a_res_hp,
            w_a_res_acc, w_a_res_eva, w_a_res_cr, w_a_res_spd, w_a_res_max_hp,
            p_a_res_atk, p_a_res_def, p_a_res_matk, p_a_res_mdef, p_a_res_hp,
            p_a_res_acc, p_a_res_eva, p_a_res_cr, p_a_res_spd, p_a_res_max_hp,
            w_a_id, w_a_id_element, w_a_id_species, w_a_species, w_a_lvl,
            p_a_id, p_a_id_element, p_a_id_species, p_a_species, p_a_lvl, p_a_nickname, p_a_cur_exp,
            move_description, move_hit, resulting_battle_status
        ) VALUES (
            :id_battle, :id_user_ig_actor, NOW(), :turn, :move_type, :id_rif,
            :move_speed, 1, :protagonist_type, :id_protagonist, :target_type, :id_target,
            :w_a_res_atk, :w_a_res_def, :w_a_res_matk, :w_a_res_mdef, :w_a_res_hp,
            :w_a_res_acc, :w_a_res_eva, :w_a_res_cr, :w_a_res_spd, :w_a_res_max_hp,
            :p_a_res_atk, :p_a_res_def, :p_a_res_matk, :p_a_res_mdef, :p_a_res_hp,
            :p_a_res_acc, :p_a_res_eva, :p_a_res_cr, :p_a_res_spd, :p_a_res_max_hp,
            :w_a_id, :w_a_id_element, :w_a_id_species, :w_a_species, :w_a_lvl,
            :p_a_id, :p_a_id_element, :p_a_id_species, :p_a_species, :p_a_lvl, :p_a_nickname, :p_a_cur_exp,
            :move_description, :move_hit, :resulting_battle_status
        )
    ');

    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig_actor' => $id_user_ig_actor ? (int) $id_user_ig_actor : null,
        ':turn' => (int) $turn,
        ':move_type' => (string) $move_type,
        ':id_rif' => (int) $id_rif,
        ':move_speed' => (float) $party_actor['spd'],
        ':protagonist_type' => (string) $protagonist_type,
        ':id_protagonist' => (int) $id_protagonist,
        ':target_type' => (string) $target_type,
        ':id_target' => (int) $id_target,
        ':w_a_res_atk' => $w['w_a_res_atk'],
        ':w_a_res_def' => $w['w_a_res_def'],
        ':w_a_res_matk' => $w['w_a_res_matk'],
        ':w_a_res_mdef' => $w['w_a_res_mdef'],
        ':w_a_res_hp' => $w['w_a_res_hp'],
        ':w_a_res_acc' => $w['w_a_res_acc'],
        ':w_a_res_eva' => $w['w_a_res_eva'],
        ':w_a_res_cr' => $w['w_a_res_cr'],
        ':w_a_res_spd' => $w['w_a_res_spd'],
        ':w_a_res_max_hp' => $w['w_a_res_max_hp'],
        ':p_a_res_atk' => $p['p_a_res_atk'],
        ':p_a_res_def' => $p['p_a_res_def'],
        ':p_a_res_matk' => $p['p_a_res_matk'],
        ':p_a_res_mdef' => $p['p_a_res_mdef'],
        ':p_a_res_hp' => $p['p_a_res_hp'],
        ':p_a_res_acc' => $p['p_a_res_acc'],
        ':p_a_res_eva' => $p['p_a_res_eva'],
        ':p_a_res_cr' => $p['p_a_res_cr'],
        ':p_a_res_spd' => $p['p_a_res_spd'],
        ':p_a_res_max_hp' => $p['p_a_res_max_hp'],
        ':w_a_id' => $w['w_a_id'],
        ':w_a_id_element' => $w['w_a_id_element'],
        ':w_a_id_species' => $w['w_a_id_species'],
        ':w_a_species' => $w['w_a_species'],
        ':w_a_lvl' => $w['w_a_lvl'],
        ':p_a_id' => $p['p_a_id'],
        ':p_a_id_element' => $p['p_a_id_element'],
        ':p_a_id_species' => $p['p_a_id_species'],
        ':p_a_species' => $p['p_a_species'],
        ':p_a_lvl' => $p['p_a_lvl'],
        ':p_a_nickname' => $p['p_a_nickname'],
        ':p_a_cur_exp' => 0,
        ':move_description' => (string) $move_description,
        ':move_hit' => (string) $move_hit,
        ':resulting_battle_status' => (string) $battle_status
    ]);

    $stmt = $conn->prepare('
        UPDATE battles_party_pve
        SET current_turn = :turn, dt_m = NOW()
        WHERE id_battle_party_pve = :id_battle
    ');
    $stmt->execute([
        ':turn' => (int) $turn,
        ':id_battle' => (int) $id_battle
    ]);
}

function animaster_party_pve_pick_wild_target(array $party_participants)
{
    $alive = [];

    foreach ($party_participants as $p)
    {
        if ($p['side'] !== 'party')
        {
            continue;
        }

        if (trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0)
        {
            continue;
        }

        $alive[] = $p;
    }

    if (!$alive)
    {
        return null;
    }

    return $alive[array_rand($alive)];
}

/**
 * Apply ability stat effect (e.g. lower_target_def_10_%) — same rules as solo PvE.
 *
 * @param array<string, mixed> $ability
 * @param array<string, mixed> $attacker
 * @param array<string, mixed> $defender
 */
function animaster_party_pve_apply_ability_stat_effect(array $ability, array &$attacker, array &$defender)
{
    $effect = trim((string) ($ability['effect'] ?? ''));

    if ($effect === '' || $effect === 'none')
    {
        return;
    }

    $effect_chance = (int) ($ability['effect_chance'] ?? 0);

    if ($effect_chance <= 0 || rand(1, 100) >= $effect_chance)
    {
        return;
    }

    $parts = explode('_', $effect);

    if (count($parts) < 5)
    {
        return;
    }

    $effect_direction = $parts[0];
    $effect_target = $parts[1];
    $effect_stat = $parts[2];
    $effect_mult = (float) $parts[3];
    $effect_unit = $parts[4];
    $allowed_stats = ['atk', 'def', 'matk', 'mdef', 'acc', 'eva', 'cr', 'spd'];

    if (!in_array($effect_stat, $allowed_stats, true))
    {
        return;
    }

    $effect_multiplier = 1.0;

    if ($effect_unit === '%')
    {
        if ($effect_direction === 'lower')
        {
            $effect_multiplier -= $effect_mult / 100;
        }
        else if ($effect_direction === 'increase')
        {
            $effect_multiplier += $effect_mult / 100;
        }
    }

    if ($effect_target === 'target')
    {
        if (!array_key_exists($effect_stat, $defender))
        {
            return;
        }

        $defender[$effect_stat] = (int) round((float) $defender[$effect_stat] * $effect_multiplier);
        $defender[$effect_stat] = max(0, (int) $defender[$effect_stat]);
    }
    else if ($effect_target === 'self')
    {
        if (!array_key_exists($effect_stat, $attacker))
        {
            return;
        }

        $attacker[$effect_stat] = (int) round((float) $attacker[$effect_stat] * $effect_multiplier);
        $attacker[$effect_stat] = max(0, (int) $attacker[$effect_stat]);
    }
}

function animaster_party_pve_apply_ability_damage(
    $conn,
    array $ability,
    array $attacker,
    array $defender,
    $attacker_is_wild,
    $lang_suffix
)
{
    if (!class_exists('FUNZIONI'))
    {
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/funzioni.php';
    }

    $attacker_acc = (int) $attacker['acc'];
    $defender_eva = (int) $defender['eva'];
    $attacker_lvl = (int) $attacker['lvl'];
    $attacker_atk = (float) $attacker['atk'];
    $attacker_matk = (float) $attacker['matk'];
    $defender_def = (float) $defender['def'];
    $defender_mdef = (float) $defender['mdef'];
    $attacker_cr = (int) $attacker['cr'];
    $attacker_element = (int) $attacker['id_element'];
    $defender_element = (int) $defender['id_element'];

    $acc = $attacker_acc * ((float) $ability['accuracy'] / 100);
    $acc *= (100 - $defender_eva) / 100;

    $hit = rand(1, 100) <= $acc ? 'S' : 'N';
    $description = '';
    $move_hit = 'N';

    if ($hit === 'S')
    {
        $crit = 1.0;
        $type_bonus = 1.0;

        if (rand(1, 100) <= $attacker_cr)
        {
            $crit = 1.5;
        }

        if ((int) $ability['id_element'] === $attacker_element)
        {
            $type_bonus = 1.5;
        }

        $dmg = ($attacker_lvl * 0.5 * (int) $ability['power'] * $attacker_atk / max(1, $defender_def))
            + ($attacker_lvl * 0.5 * (int) $ability['m_power'] * $attacker_matk / max(1, $defender_mdef));
        $dmg /= 40;

        if ((int) $ability['power'] > 0 || (int) $ability['m_power'] > 0)
        {
            $dmg += 3;
        }

        $dmg *= $crit;
        $dmg *= $type_bonus;
        $dmg *= FUNZIONI::element_bonus((int) $ability['id_element'], $defender_element);
        $dmg = (int) $dmg;

        $defender['current_hp'] = max(0, (int) $defender['current_hp'] - $dmg);
        $move_hit = 'S';

        if ((int) $defender['current_hp'] > 0)
        {
            animaster_party_pve_apply_ability_stat_effect($ability, $attacker, $defender);
        }
    }

    $ability_name = (string) $ability['ability'];
    $actor_name = (string) $attacker['nickname'];

    if ($lang_suffix === '_it')
    {
        $description = $actor_name . ' ha usato ' . $ability_name;
    }
    else if ($lang_suffix === '_pt')
    {
        $description = $actor_name . ' usou ' . $ability_name;
    }
    else
    {
        $description = $actor_name . ' used ' . $ability_name;
    }

    return [
        'attacker' => $attacker,
        'defender' => $defender,
        'move_hit' => $move_hit,
        'move_description' => $description,
        'id_ability' => (int) $ability['id_ability']
    ];
}

function animaster_party_pve_fetch_ability($conn, $id_ability, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $sql = '
        SELECT id_ability, accuracy, power, m_power, effect, effect_chance, id_element,
               ability' . $lang_suffix . ' AS ability
        FROM abilities
        WHERE id_ability = :id_ability
        LIMIT 1
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id_ability' => (int) $id_ability]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_fetch_random_wild_ability($conn, $id_species, $lvl, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $sql = '
        SELECT A.id_ability, A.accuracy, A.power, A.m_power, A.id_element, A.effect, A.effect_chance,
               A.ability' . $lang_suffix . ' AS ability
        FROM abilities A
        INNER JOIN species_abilities LA ON LA.id_ability = A.id_ability
        WHERE LA.id_species = :id_species
          AND LA.unlock_lvl <= :lvl
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_species' => (int) $id_species,
        ':lvl' => (int) $lvl
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows)
    {
        return null;
    }

    return $rows[array_rand($rows)];
}

function animaster_party_pve_wild_no_ability_move(array $wild, $lang_suffix)
{
    $name = (string) ($wild['nickname'] ?: $wild['species_name'] ?: 'Wild');

    if ($lang_suffix === '_it')
    {
        $description = $name . ' sembra concentrarsi.';
    }
    else if ($lang_suffix === '_pt')
    {
        $description = $name . ' parece concentrar-se.';
    }
    else
    {
        $description = $name . ' seems to focus.';
    }

    return [
        'defender' => $wild,
        'move_hit' => 'N',
        'move_description' => $description,
        'id_ability' => 0
    ];
}

function animaster_party_pve_process_party_ability($conn, array $battle, array $actor, $id_ability, $id_user_ig, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_party_pve'];
    $turn = (int) $battle['current_turn'] + 1;
    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);

    if (!$wild || (int) $wild['current_hp'] <= 0)
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    $ability = animaster_party_pve_fetch_ability($conn, $id_ability, $lang_suffix);

    if (!$ability)
    {
        return ['error' => 'INVALID_ABILITY'];
    }

    $result = animaster_party_pve_apply_ability_damage($conn, $ability, $actor, $wild, false, $lang_suffix);
    $actor = $result['attacker'];
    $wild = $result['defender'];

    animaster_party_pve_save_participant($conn, $actor);
    animaster_party_pve_save_participant($conn, $wild);

    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $status = animaster_party_pve_check_end($conn, $battle, $participants, $wild);

    if ($status === 'victory')
    {
        animaster_party_pve_finish_battle(
            $conn,
            $battle,
            'victory',
            (int) $battle['id_wild_animal'],
            $participants,
            $wild,
            $lang_suffix
        );
    }

    animaster_party_pve_insert_move(
        $conn,
        $id_battle,
        $turn,
        (int) $id_user_ig,
        $actor,
        $wild,
        'ability',
        (int) $result['id_ability'],
        $result['move_description'],
        $result['move_hit'],
        $status,
        'animal',
        (int) $actor['id_animal'],
        'animal',
        (int) $wild['id_animal']
    );

    if ($status === 'ongoing')
    {
        animaster_party_pve_advance_turn($conn, $battle);
    }

    return ['ok' => true, 'turn' => $turn, 'status' => $status];
}

function animaster_party_pve_process_wild_turn($conn, array $battle, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_party_pve'];
    $turn = (int) $battle['current_turn'] + 1;
    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);

    if (!$wild || (int) $wild['current_hp'] <= 0)
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $target = animaster_party_pve_pick_wild_target($participants);

    if (!$target)
    {
        return ['error' => 'NO_TARGETS'];
    }

    $ability = animaster_party_pve_fetch_random_wild_ability(
        $conn,
        (int) $wild['id_species'],
        (int) $wild['lvl'],
        $lang_suffix
    );

    if (!$ability)
    {
        $result = animaster_party_pve_wild_no_ability_move($wild, $lang_suffix);
    }
    else
    {
        $result = animaster_party_pve_apply_ability_damage($conn, $ability, $wild, $target, true, $lang_suffix);
        $wild = $result['attacker'];
        $target = $result['defender'];
        animaster_party_pve_save_participant($conn, $wild);
        animaster_party_pve_save_participant($conn, $target);
    }

    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $status = animaster_party_pve_check_end($conn, $battle, $participants, $wild);

    if ($status === 'defeat')
    {
        animaster_party_pve_finish_battle(
            $conn,
            $battle,
            'defeat',
            (int) $battle['id_wild_animal'],
            $participants,
            $wild,
            $lang_suffix
        );
    }

    animaster_party_pve_insert_move(
        $conn,
        $id_battle,
        $turn,
        null,
        $target,
        $wild,
        'ability',
        (int) $result['id_ability'],
        $result['move_description'],
        $result['move_hit'],
        $status,
        'animal',
        (int) $wild['id_animal'],
        'animal',
        (int) $target['id_animal']
    );

    if ($status === 'ongoing')
    {
        animaster_party_pve_advance_turn($conn, $battle);
    }

    return ['ok' => true, 'turn' => $turn, 'status' => $status];
}

function animaster_party_pve_process_flee($conn, array $battle, $id_user_ig, $lang_suffix)
{
    if ((int) $battle['id_user_ig_leader'] !== (int) $id_user_ig)
    {
        return ['error' => 'NOT_LEADER'];
    }

    $id_battle = (int) $battle['id_battle_party_pve'];
    $turn = (int) $battle['current_turn'] + 1;
    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $actor = null;

    foreach ($participants as $p)
    {
        if ($p['side'] === 'party' && (int) $p['id_user_ig'] === (int) $id_user_ig)
        {
            $actor = $p;
            break;
        }
    }

    if (!$actor || !$wild)
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    if ($lang_suffix === '_it')
    {
        $description = 'La squadra è fuggita!';
    }
    else if ($lang_suffix === '_pt')
    {
        $description = 'O grupo fugiu!';
    }
    else
    {
        $description = 'The party fled!';
    }

    animaster_party_pve_finish_battle(
        $conn,
        $battle,
        'fled',
        (int) $battle['id_wild_animal'],
        $participants,
        $wild,
        $lang_suffix
    );

    animaster_party_pve_insert_move(
        $conn,
        $id_battle,
        $turn,
        (int) $id_user_ig,
        $actor,
        $wild,
        'action',
        4,
        $description,
        'S',
        'fled',
        'player',
        (int) $id_user_ig,
        'wild',
        (int) $wild['id_animal']
    );

    return ['ok' => true, 'turn' => $turn, 'status' => 'fled'];
}

function animaster_party_pve_build_meta($conn, array $battle, $id_user_ig, $lang_suffix)
{
    $participants = animaster_party_pve_fetch_participants($conn, (int) $battle['id_battle_party_pve']);
    $actor = animaster_party_pve_get_actor_from_battle($conn, $battle);
    $awaiting = $battle['awaiting_user_ig'] !== null ? (int) $battle['awaiting_user_ig'] : 0;
    $actor_side = $actor ? trim((string) $actor['side']) : '';
    $can_act = trim((string) $battle['flg_status']) === 'O'
        && $awaiting > 0
        && $awaiting === (int) $id_user_ig;

    $allies = [];
    $actor_name = '';

    foreach ($participants as $p)
    {
        if ($p['side'] !== 'party')
        {
            continue;
        }

        $display_name = '';

        if ($p['id_user_ig'])
        {
            $user = animaster_party_fetch_user($conn, (int) $p['id_user_ig']);
            $display_name = $user ? (string) ($user['display_name'] ?: 'Player') : 'Player';
        }

        if ($actor && (int) $p['id_battle_party_pve_participant'] === (int) $actor['id_battle_party_pve_participant'])
        {
            if ($actor_side === 'wild')
            {
                $actor_name = (string) ($p['nickname'] ?: $p['species_name'] ?: 'Wild');
            }
            else
            {
                $actor_name = $display_name;
            }
        }

        $allies[] = [
            'id_user_ig' => (int) $p['id_user_ig'],
            'display_name' => $display_name,
            'id_animal' => (int) $p['id_animal'],
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0,
            'is_actor' => $actor && (int) $p['id_battle_party_pve_participant'] === (int) $actor['id_battle_party_pve_participant'],
            'is_self' => (int) $p['id_user_ig'] === (int) $id_user_ig
        ];
    }

    $wild = animaster_party_pve_get_wild_participant($conn, (int) $battle['id_battle_party_pve']);
    $wild_combatants = [];

    foreach ($participants as $p)
    {
        if ($p['side'] !== 'wild')
        {
            continue;
        }

        $wild_combatants[] = [
            'id_animal' => (int) $p['id_animal'],
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0,
            'is_actor' => $actor && (int) $p['id_battle_party_pve_participant'] === (int) $actor['id_battle_party_pve_participant']
        ];
    }

    $battle_finished = trim((string) $battle['flg_status']) !== 'O';

    return [
        'current_turn' => (int) $battle['current_turn'],
        'awaiting_user_ig' => $awaiting,
        'can_act' => $can_act,
        'actor_display_name' => $actor_name,
        'actor_side' => $actor_side,
        'party_allies' => $allies,
        'wild_combatants' => $wild_combatants,
        'wild_hp' => $wild ? (int) $wild['current_hp'] : 0,
        'wild_max_hp' => $wild ? (int) $wild['max_hp'] : 0,
        'battle_finished' => $battle_finished,
        'is_leader' => (int) $battle['id_user_ig_leader'] === (int) $id_user_ig
    ];
}

function animaster_party_pve_start($conn, $id_user_ig, $id_zone, $id_wild_animal, $ref_x, $ref_z, $lang_suffix)
{
    require_once __DIR__ . '/pvp.php';

    $id_user_ig = (int) $id_user_ig;
    $id_zone = (int) $id_zone;
    $id_wild_animal = (int) $id_wild_animal;
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $user = animaster_party_fetch_user($conn, $id_user_ig);

    if (!$user || (int) ($user['id_party'] ?? 0) <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    $id_party = (int) $user['id_party'];

    if (!animaster_party_is_leader($conn, $id_party, $id_user_ig))
    {
        return ['error' => 'NOT_LEADER'];
    }

    if (animaster_party_pve_party_has_active_battle($conn, $id_party))
    {
        return ['error' => 'PARTY_ALREADY_FIGHTING'];
    }

    $stmt = $conn->prepare('
        SELECT id_battle, battle_type
        FROM wild_animals
        WHERE id_wild_animal = :id_wild
        LIMIT 1
    ');
    $stmt->execute([':id_wild' => $id_wild_animal]);
    $wild_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wild_row)
    {
        return ['error' => 'WILD_NOT_FOUND'];
    }

    if ((int) ($wild_row['id_battle'] ?? 0) > 0)
    {
        return ['error' => 'TOO_LATE'];
    }

    $wild_snap = animaster_party_pve_fetch_wild_snapshot($conn, $id_wild_animal, $lang_suffix);

    if (!$wild_snap || (int) $wild_snap['id_zone'] !== $id_zone)
    {
        return ['error' => 'WILD_NOT_FOUND'];
    }

    $eligible = animaster_party_pve_fetch_eligible_members($conn, $id_party, $ref_x, $ref_z, $id_zone);

    if (!$eligible)
    {
        return ['error' => 'NO_ELIGIBLE_MEMBERS'];
    }

    $leader_in = false;

    foreach ($eligible as $member)
    {
        if ((int) $member['id_user_ig'] === $id_user_ig)
        {
            $leader_in = true;
            break;
        }
    }

    if (!$leader_in)
    {
        return ['error' => 'LEADER_TOO_FAR'];
    }

    $party_snaps = [];

    foreach ($eligible as $member)
    {
        $id_animal = (int) $member['lead_animal']['id_animal'];
        $snap = animaster_pvp_fetch_animal_snapshot_buffed($conn, $id_animal, $lang_suffix);

        if (!$snap)
        {
            continue;
        }

        $party_snaps[] = [
            'member' => $member,
            'snap' => $snap
        ];
    }

    $leader_has_snap = false;

    foreach ($party_snaps as $entry)
    {
        if ((int) $entry['member']['id_user_ig'] === $id_user_ig)
        {
            $leader_has_snap = true;
            break;
        }
    }

    if (!$leader_has_snap || !$party_snaps)
    {
        return ['error' => 'NO_TEAM_ANIMAL'];
    }

    try
    {
        $conn->beginTransaction();

        $stmt = $conn->prepare('
            INSERT INTO battles_party_pve (
                id_party, id_wild_animal, id_zone, id_user_ig_leader,
                flg_status, current_turn, turn_index, dt_created
            ) VALUES (
                :id_party, :id_wild, :id_zone, :id_leader,
                \'O\', 0, 0, NOW()
            )
        ');
        $stmt->execute([
            ':id_party' => $id_party,
            ':id_wild' => $id_wild_animal,
            ':id_zone' => $id_zone,
            ':id_leader' => $id_user_ig
        ]);

        $id_battle = (int) $conn->lastInsertId();

        $stmt = $conn->prepare('
            UPDATE wild_animals
            SET id_battle = :id_battle, battle_type = \'party_pve\', dt_modifica = NOW()
            WHERE id_wild_animal = :id_wild
              AND (id_battle IS NULL OR id_battle = 0)
        ');
        $stmt->execute([
            ':id_battle' => $id_battle,
            ':id_wild' => $id_wild_animal
        ]);

        if ($stmt->rowCount() <= 0)
        {
            $conn->rollBack();

            return ['error' => 'TOO_LATE'];
        }

        animaster_party_pve_insert_participant(
            $conn,
            $id_battle,
            animaster_party_pve_participant_row_from_snap($wild_snap, 'wild', 0, null)
        );

        $team_pos = 1;
        $first_party_actor = null;

        foreach ($party_snaps as $entry)
        {
            $member = $entry['member'];
            $snap = $entry['snap'];

            $participant_id = animaster_party_pve_insert_participant(
                $conn,
                $id_battle,
                animaster_party_pve_participant_row_from_snap($snap, 'party', (int) $member['id_user_ig'], $team_pos)
            );

            if (!$first_party_actor)
            {
                $first_party_actor = animaster_party_pve_fetch_participant($conn, $participant_id);
            }

            $team_pos++;
        }

        if (!$first_party_actor || !animaster_party_pve_user_in_battle($conn, $id_battle, $id_user_ig))
        {
            $conn->rollBack();

            return ['error' => 'NO_TEAM_ANIMAL'];
        }

        $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
        $queue = animaster_party_pve_rebuild_queue($participants);
        $first_actor = animaster_party_pve_fetch_participant($conn, (int) $queue[0]);
        $awaiting = ($first_actor && $first_actor['side'] === 'party') ? (int) $first_actor['id_user_ig'] : null;

        $stmt = $conn->prepare('
            UPDATE battles_party_pve
            SET turn_queue_json = :queue_json,
                turn_index = 0,
                awaiting_user_ig = :awaiting_user_ig
            WHERE id_battle_party_pve = :id_battle
        ');
        $stmt->execute([
            ':queue_json' => json_encode($queue),
            ':awaiting_user_ig' => $awaiting,
            ':id_battle' => $id_battle
        ]);

        $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);

        animaster_party_pve_insert_move(
            $conn,
            $id_battle,
            0,
            $id_user_ig,
            $first_party_actor,
            $wild,
            'start',
            0,
            'start',
            'S',
            'ongoing',
            'start',
            0,
            'start',
            0
        );

        $conn->commit();

        return [
            'ok' => true,
            'id_battle' => $id_battle,
            'battle_type' => 'party_pve',
            'current_battle_turn' => 0
        ];
    }
    catch (Exception $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        return ['error' => 'START_FAILED'];
    }
}

function animaster_party_pve_fetch_moves_through_turn($conn, $id_battle, $through_turn, $lang_suffix)
{
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $sql = '
        SELECT M.*,
               WE.element' . $lang_suffix . ' AS w_a_element,
               PE.element' . $lang_suffix . ' AS p_a_element,
               WE.color AS w_a_element_color,
               PE.color AS p_a_element_color
        FROM battles_party_pve_moves M
        LEFT JOIN elements WE ON WE.id_element = M.w_a_id_element
        LEFT JOIN elements PE ON PE.id_element = M.p_a_id_element
        WHERE M.id_battle_party_pve = :id_battle
          AND M.turn <= :through_turn
        ORDER BY M.turn ASC, M.order_in_turn ASC, M.id_battle_party_pve_move ASC
    ';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':through_turn' => (int) $through_turn
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function animaster_party_pve_handle_turn_request(
    $conn,
    $id_battle,
    $id_user_ig,
    $turn,
    $restarting,
    $type,
    $id_action,
    $lang_suffix
)
{
    $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);

    if (!$battle || !animaster_party_pve_user_in_battle($conn, (int) $id_battle, (int) $id_user_ig))
    {
        return ['error' => 'BATTLE_NOT_FOUND'];
    }

    if (trim((string) $battle['flg_status']) !== 'O' && (int) $turn > (int) $battle['current_turn'])
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    $process = ((int) $turn > 0 && $restarting !== 'S');

    if ($process && !animaster_party_pve_move_exists($conn, (int) $id_battle, (int) $turn))
    {
        $expected_turn = (int) $battle['current_turn'] + 1;

        if ((int) $turn !== $expected_turn)
        {
            return ['error' => 'TURN_OUT_OF_SYNC'];
        }

        $actor = animaster_party_pve_get_actor_from_battle($conn, $battle);

        if (!$actor)
        {
            return ['error' => 'NO_ACTOR'];
        }

        if ($actor['side'] === 'party')
        {
            if ($type === 'action' && (int) $id_action === 4)
            {
                if ((int) $battle['id_user_ig_leader'] !== (int) $id_user_ig)
                {
                    return ['error' => 'NOT_LEADER'];
                }

                $action_result = animaster_party_pve_process_flee($conn, $battle, (int) $id_user_ig, $lang_suffix);
            }
            else if ((int) $actor['id_user_ig'] !== (int) $id_user_ig)
            {
                return ['waiting' => true, 'battle' => $battle];
            }
            else if (!$type)
            {
                return ['waiting' => true, 'battle' => $battle];
            }
            else if ($type === 'ability')
            {
                $action_result = animaster_party_pve_process_party_ability(
                    $conn,
                    $battle,
                    $actor,
                    (int) $id_action,
                    (int) $id_user_ig,
                    $lang_suffix
                );
            }
            else
            {
                return ['error' => 'UNSUPPORTED_ACTION'];
            }

            if (!empty($action_result['error']))
            {
                return $action_result;
            }

            $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
        }
        else if (!$type)
        {
            $action_result = animaster_party_pve_process_wild_turn($conn, $battle, $lang_suffix);

            if (!empty($action_result['error']))
            {
                return $action_result;
            }

            $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
        }
    }

    $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
    $fetch_turn = (int) $turn;

    if ($restarting === 'S')
    {
        $max_turn = animaster_party_pve_max_turn($conn, (int) $id_battle);
        $rows = animaster_party_pve_fetch_moves_through_turn($conn, (int) $id_battle, $max_turn, $lang_suffix);

        if (!$rows && $fetch_turn >= 0)
        {
            $rows = animaster_party_pve_fetch_move_rows($conn, (int) $id_battle, $fetch_turn, $lang_suffix);
        }
    }
    else
    {
        $rows = animaster_party_pve_fetch_move_rows($conn, (int) $id_battle, $fetch_turn, $lang_suffix);
    }

    if (!$rows)
    {
        $actor = animaster_party_pve_get_actor_from_battle($conn, $battle);

        if ($actor && $actor['side'] === 'party' && (int) $actor['id_user_ig'] !== (int) $id_user_ig)
        {
            return [
                'waiting' => true,
                'battle' => $battle,
                'rows' => []
            ];
        }

        if ($actor && $actor['side'] === 'wild' && trim((string) $battle['flg_status']) === 'O')
        {
            $result = animaster_party_pve_process_wild_turn($conn, $battle, $lang_suffix);

            if (!empty($result['ok']))
            {
                $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
                $rows = animaster_party_pve_fetch_move_rows($conn, (int) $id_battle, (int) $turn, $lang_suffix);
            }
        }
    }
    elseif (
        trim((string) $battle['flg_status']) === 'O'
        && (int) $turn === (int) $battle['current_turn']
        && !animaster_party_pve_move_exists($conn, (int) $id_battle, (int) $battle['current_turn'] + 1)
    )
    {
        $actor = animaster_party_pve_get_actor_from_battle($conn, $battle);

        if ($actor && $actor['side'] === 'wild')
        {
            $result = animaster_party_pve_process_wild_turn($conn, $battle, $lang_suffix);

            if (!empty($result['ok']))
            {
                $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
                $through_turn = (int) $battle['current_turn'];
                $rows = animaster_party_pve_fetch_moves_through_turn($conn, (int) $id_battle, $through_turn, $lang_suffix);

                if (!$rows)
                {
                    $rows = animaster_party_pve_fetch_move_rows($conn, (int) $id_battle, (int) $turn, $lang_suffix);
                }
            }
        }
    }

    if (!$rows)
    {
        return ['error' => 'NO_BATTLE_MOVES'];
    }

    $history_turn = animaster_party_pve_max_turn($conn, (int) $id_battle);

    if ($history_turn >= 0)
    {
        $history = animaster_party_pve_fetch_moves_through_turn(
            $conn,
            (int) $id_battle,
            $history_turn,
            $lang_suffix
        );

        if ($history)
        {
            $rows = $history;
        }
    }

    return [
        'ok' => true,
        'battle' => $battle,
        'rows' => $rows
    ];
}
