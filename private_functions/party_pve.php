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
        SELECT b.id_battle_party_pve, b.current_turn
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
        'current_battle_turn' => (int) $row['current_turn']
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

/**
 * Party members eligible to plan/act this round: alive (not fainted, HP > 0)
 * animals on the 'party' side. Wild is handled separately.
 *
 * @param array<int, array<string, mixed>> $participants
 * @return array<int, array<string, mixed>>
 */
function animaster_party_pve_alive_party_participants(array $participants)
{
    $alive = [];

    foreach ($participants as $p)
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

    return $alive;
}

/**
 * @param array<int, array<string, mixed>> $alive_party_participants
 * @return array<int, int>
 */
function animaster_party_pve_alive_party_user_ids(array $alive_party_participants)
{
    $ids = [];

    foreach ($alive_party_participants as $p)
    {
        $ids[] = (int) $p['id_user_ig'];
    }

    return $ids;
}

/* ---------------------------------------------------------------------
 * Turn choices (staging table): each alive party member stages one action
 * for the upcoming round, then confirms it. Once every alive member has a
 * confirmed choice (or the leader confirms a flee), the round resolves.
 * ------------------------------------------------------------------- */

function animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_user_ig, $action_type, $action_id, $id_item_type_selected = 0)
{
    $stmt = $conn->prepare('
        INSERT INTO battles_party_pve_turn_choices (
            id_battle_party_pve, round, id_user_ig, action_type, action_id, id_item_type_selected, flg_confirmed
        ) VALUES (
            :id_battle, :round, :id_user_ig, :action_type, :action_id, :id_item_type_selected, \'N\'
        )
        ON DUPLICATE KEY UPDATE
            action_type = :action_type_upd,
            action_id = :action_id_upd,
            id_item_type_selected = :id_item_type_selected_upd,
            flg_confirmed = \'N\'
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round,
        ':id_user_ig' => (int) $id_user_ig,
        ':action_type' => (string) $action_type,
        ':action_id' => (int) $action_id,
        ':id_item_type_selected' => (int) $id_item_type_selected,
        ':action_type_upd' => (string) $action_type,
        ':action_id_upd' => (int) $action_id,
        ':id_item_type_selected_upd' => (int) $id_item_type_selected
    ]);
}

function animaster_party_pve_set_turn_choice_confirmed($conn, $id_battle, $round, $id_user_ig, $confirmed)
{
    $stmt = $conn->prepare('
        UPDATE battles_party_pve_turn_choices
        SET flg_confirmed = :flg_confirmed
        WHERE id_battle_party_pve = :id_battle
          AND round = :round
          AND id_user_ig = :id_user_ig
    ');
    $stmt->execute([
        ':flg_confirmed' => $confirmed ? 'Y' : 'N',
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return $stmt->rowCount() > 0;
}

function animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve_turn_choices
        WHERE id_battle_party_pve = :id_battle
          AND round = :round
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battles_party_pve_turn_choices
        WHERE id_battle_party_pve = :id_battle
          AND round = :round
        ORDER BY id_battle_party_pve_turn_choice ASC
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function animaster_party_pve_count_confirmed_turn_choices($conn, $id_battle, $round)
{
    $stmt = $conn->prepare('
        SELECT COUNT(*) AS cnt
        FROM battles_party_pve_turn_choices
        WHERE id_battle_party_pve = :id_battle
          AND round = :round
          AND flg_confirmed = \'Y\'
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round
    ]);

    return (int) $stmt->fetchColumn();
}

function animaster_party_pve_clear_turn_choices($conn, $id_battle, $round)
{
    $stmt = $conn->prepare('
        DELETE FROM battles_party_pve_turn_choices
        WHERE id_battle_party_pve = :id_battle
          AND round = :round
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':round' => (int) $round
    ]);
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
            require_once __DIR__ . '/f.php';
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
    $id_target,
    $order_in_turn = 1
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
            :move_speed, :order_in_turn, :protagonist_type, :id_protagonist, :target_type, :id_target,
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
        ':order_in_turn' => (int) $order_in_turn,
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
        require_once __DIR__ . '/f.php';
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

/**
 * Validate (without applying) a party member's staged ability choice.
 * Returns the ability row, or an error.
 */
function animaster_party_pve_validate_ability_choice($conn, $id_ability, $lang_suffix)
{
    $ability = animaster_party_pve_fetch_ability($conn, $id_ability, $lang_suffix);

    if (!$ability)
    {
        return ['error' => 'INVALID_ABILITY'];
    }

    return ['ok' => true, 'ability' => $ability];
}

/**
 * Validate (without applying) a party member's staged team-switch choice.
 */
function animaster_party_pve_validate_switch_choice($conn, array $actor, $id_new_animal, $id_user_ig)
{
    $id_new_animal = (int) $id_new_animal;

    if ($id_new_animal === (int) $actor['id_animal'])
    {
        return ['error' => 'ALREADY_ACTIVE'];
    }

    $stmt = $conn->prepare('
        SELECT id_animal, current_hp
        FROM animals
        WHERE id_animal = :id_animal
          AND id_user_ig = :id_user_ig
          AND team_position > 0
        LIMIT 1
    ');
    $stmt->execute([
        ':id_animal' => $id_new_animal,
        ':id_user_ig' => (int) $id_user_ig
    ]);
    $owned = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owned)
    {
        return ['error' => 'INVALID_ANIMAL'];
    }

    if ((int) $owned['current_hp'] <= 0)
    {
        return ['error' => 'ANIMAL_FAINTED'];
    }

    return ['ok' => true];
}

/**
 * Validate (without applying) a party member's staged item-use choice.
 */
function animaster_party_pve_validate_item_choice($conn, $id_user_ig, $id_item_type, $id_target_animal, $lang_suffix)
{
    $id_item_type = (int) $id_item_type;
    $id_target_animal = (int) $id_target_animal;

    $stmt = $conn->prepare('
        SELECT id_animal, nickname, current_hp, max_hp
        FROM animals
        WHERE id_animal = :id_animal
          AND id_user_ig = :id_user_ig
          AND team_position > 0
        LIMIT 1
    ');
    $stmt->execute([
        ':id_animal' => $id_target_animal,
        ':id_user_ig' => (int) $id_user_ig
    ]);
    $target_animal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_animal)
    {
        return ['error' => 'INVALID_ANIMAL'];
    }

    $lang_suffix_safe = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $stmt = $conn->prepare('
        SELECT id_item_type, item_type, use_effect, nome' . $lang_suffix_safe . ' AS item_name
        FROM item_types
        WHERE id_item_type = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id_item_type]);
    $item_type = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_type)
    {
        return ['error' => 'INVALID_ITEM'];
    }

    if (trim((string) $item_type['item_type']) !== 'potions')
    {
        return ['error' => 'ITEM_NOT_USABLE'];
    }

    $stmt = $conn->prepare('
        SELECT id_item
        FROM items
        WHERE id_item_type = :id_item_type
          AND id_user_ig = :id_user_ig
          AND (flg_held = \'N\' OR flg_held IS NULL)
          AND dt_used IS NULL
        ORDER BY dt_creazione ASC
        LIMIT 1
    ');
    $stmt->execute([
        ':id_item_type' => $id_item_type,
        ':id_user_ig' => (int) $id_user_ig
    ]);
    $item_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item_row)
    {
        return ['error' => 'ITEM_NOT_AVAILABLE'];
    }

    return [
        'ok' => true,
        'target_animal' => $target_animal,
        'item_type' => $item_type,
        'item_row' => $item_row
    ];
}

/**
 * Apply a party member's confirmed ability choice during round resolution.
 * Mutates & persists $actor/$wild, returns move fields for the resolver.
 */
function animaster_party_pve_apply_ability_choice($conn, array $actor, array $wild, $id_ability, $id_user_ig, $lang_suffix)
{
    $validated = animaster_party_pve_validate_ability_choice($conn, $id_ability, $lang_suffix);

    if (!empty($validated['error']))
    {
        return $validated;
    }

    $result = animaster_party_pve_apply_ability_damage($conn, $validated['ability'], $actor, $wild, false, $lang_suffix);
    animaster_party_pve_save_participant($conn, $result['attacker']);
    animaster_party_pve_save_participant($conn, $result['defender']);

    return [
        'ok' => true,
        'actor' => $result['attacker'],
        'wild' => $result['defender'],
        'move_type' => 'ability',
        'id_rif' => (int) $result['id_ability'],
        'move_description' => $result['move_description'],
        'move_hit' => $result['move_hit'],
        'protagonist_type' => 'animal',
        'id_protagonist' => (int) $result['attacker']['id_animal'],
        'target_type' => 'animal',
        'id_target' => (int) $result['defender']['id_animal']
    ];
}

/**
 * Apply a party member's confirmed team-switch choice during round resolution.
 */
function animaster_party_pve_apply_switch_choice($conn, array $actor, $id_new_animal, $id_user_ig, $lang_suffix)
{
    $validated = animaster_party_pve_validate_switch_choice($conn, $actor, $id_new_animal, $id_user_ig);

    if (!empty($validated['error']))
    {
        return $validated;
    }

    require_once __DIR__ . '/pvp.php';

    $snap = animaster_pvp_fetch_animal_snapshot_buffed($conn, (int) $id_new_animal, $lang_suffix);

    if (!$snap)
    {
        return ['error' => 'INVALID_ANIMAL'];
    }

    $previous_nickname = (string) ($actor['nickname'] ?: $actor['species_name']);

    $stmt = $conn->prepare('
        UPDATE battles_party_pve_participants
        SET id_animal = :id_animal,
            id_species = :id_species,
            id_element = :id_element,
            lvl = :lvl,
            nickname = :nickname,
            species_name = :species_name,
            current_hp = :current_hp,
            max_hp = :max_hp,
            atk = :atk,
            def = :def,
            matk = :matk,
            mdef = :mdef,
            acc = :acc,
            eva = :eva,
            cr = :cr,
            spd = :spd,
            flg_fainted = \'N\'
        WHERE id_battle_party_pve_participant = :id
    ');
    $stmt->execute([
        ':id_animal' => (int) $snap['id_animal'],
        ':id_species' => (int) $snap['id_species'],
        ':id_element' => (int) $snap['id_element'],
        ':lvl' => (int) $snap['lvl'],
        ':nickname' => (string) ($snap['nickname'] ?: $snap['species']),
        ':species_name' => (string) $snap['species'],
        ':current_hp' => (int) $snap['current_hp'],
        ':max_hp' => (int) $snap['max_hp'],
        ':atk' => (int) $snap['atk'],
        ':def' => (int) $snap['def'],
        ':matk' => (int) $snap['matk'],
        ':mdef' => (int) $snap['mdef'],
        ':acc' => (int) $snap['acc'],
        ':eva' => (int) $snap['eva'],
        ':cr' => (int) $snap['cr'],
        ':spd' => (int) $snap['spd'],
        ':id' => (int) $actor['id_battle_party_pve_participant']
    ]);

    $new_actor = animaster_party_pve_fetch_participant($conn, (int) $actor['id_battle_party_pve_participant']);
    $new_nickname = (string) ($new_actor['nickname'] ?: $new_actor['species_name']);

    if ($lang_suffix === '_it')
    {
        $description = 'Hai richiamato ' . $previous_nickname . '. Scendi in campo, ' . $new_nickname . '!';
    }
    else if ($lang_suffix === '_pt')
    {
        $description = 'Retiraste ' . $previous_nickname . '. Vai tu, ' . $new_nickname . '!';
    }
    else
    {
        $description = 'You withdrew ' . $previous_nickname . '. Go, ' . $new_nickname . '!';
    }

    return [
        'ok' => true,
        'actor' => $new_actor,
        'move_type' => 'switch',
        'id_rif' => (int) $new_actor['id_animal'],
        'move_description' => $description,
        'move_hit' => 'I',
        'protagonist_type' => 'player',
        'id_protagonist' => (int) $id_user_ig,
        'target_type' => 'animal',
        'id_target' => (int) $new_actor['id_animal']
    ];
}

/**
 * Apply a party member's confirmed item-use choice during round resolution.
 * The target animal may be the actor's own active participant, or a benched
 * animal (not tracked as a battle participant).
 */
function animaster_party_pve_apply_item_choice($conn, array $actor, $id_user_ig, $id_item_type, $id_target_animal, $lang_suffix)
{
    $validated = animaster_party_pve_validate_item_choice($conn, $id_user_ig, $id_item_type, $id_target_animal, $lang_suffix);

    if (!empty($validated['error']))
    {
        return $validated;
    }

    $id_target_animal = (int) $id_target_animal;
    $target_animal = $validated['target_animal'];
    $item_type = $validated['item_type'];
    $item_row = $validated['item_row'];

    $hp_recover = (int) $item_type['use_effect'];
    $max_hp = (int) $target_animal['max_hp'];
    $new_hp = min($max_hp, (int) $target_animal['current_hp'] + $hp_recover);

    $stmt = $conn->prepare('UPDATE animals SET current_hp = :hp WHERE id_animal = :id_animal');
    $stmt->execute([
        ':hp' => $new_hp,
        ':id_animal' => $id_target_animal
    ]);

    $stmt = $conn->prepare('
        UPDATE items
        SET dt_used = NOW(), dt_modifica = NOW()
        WHERE id_item = :id_item
    ');
    $stmt->execute([':id_item' => (int) $item_row['id_item']]);

    $actor_participant = $actor;

    if ($id_target_animal === (int) $actor['id_animal'])
    {
        $actor_participant['current_hp'] = $new_hp;
        animaster_party_pve_save_participant($conn, $actor_participant);
        $actor_participant = animaster_party_pve_fetch_participant($conn, (int) $actor['id_battle_party_pve_participant']);
    }

    $target_nickname = (string) ($target_animal['nickname'] ?: '');
    $item_name = (string) ($item_type['item_name'] ?: '');

    if ($lang_suffix === '_it')
    {
        $description = 'Hai usato ' . $item_name . ' su ' . $target_nickname;
    }
    else if ($lang_suffix === '_pt')
    {
        $description = 'Usaste ' . $item_name . ' em ' . $target_nickname;
    }
    else
    {
        $description = 'You used ' . $item_name . ' on ' . $target_nickname;
    }

    return [
        'ok' => true,
        'actor' => $actor_participant,
        'move_type' => 'item',
        'id_rif' => $id_item_type,
        'move_description' => $description,
        'move_hit' => 'I',
        'protagonist_type' => 'player',
        'id_protagonist' => (int) $id_user_ig,
        'target_type' => 'animal',
        'id_target' => $id_target_animal
    ];
}

/**
 * Apply the wild's single action within a round (it gets one per alive party
 * member). Picks a random alive party participant as target.
 */
function animaster_party_pve_apply_wild_action($conn, array $wild, array $target, $lang_suffix)
{
    $ability = animaster_party_pve_fetch_random_wild_ability(
        $conn,
        (int) $wild['id_species'],
        (int) $wild['lvl'],
        $lang_suffix
    );

    if (!$ability)
    {
        $result = animaster_party_pve_wild_no_ability_move($wild, $lang_suffix);
        $result['attacker'] = $wild;
        $result['defender'] = $target;
    }
    else
    {
        $result = animaster_party_pve_apply_ability_damage($conn, $ability, $wild, $target, true, $lang_suffix);
    }

    animaster_party_pve_save_participant($conn, $result['attacker']);
    animaster_party_pve_save_participant($conn, $result['defender']);

    return [
        'ok' => true,
        'wild' => $result['attacker'],
        'target' => $result['defender'],
        'move_type' => 'ability',
        'id_rif' => (int) $result['id_ability'],
        'move_description' => $result['move_description'],
        'move_hit' => $result['move_hit'],
        'protagonist_type' => 'animal',
        'id_protagonist' => (int) $result['attacker']['id_animal'],
        'target_type' => 'animal',
        'id_target' => (int) $result['defender']['id_animal']
    ];
}

/**
 * Resolve a fully-confirmed (or leader-flee-triggered) round: builds a single
 * speed-ordered action queue out of every confirmed party choice plus one
 * wild action per alive party member, then executes it slot by slot,
 * recording one battles_party_pve_moves row per slot (shared `round` number,
 * incrementing order_in_turn) until the battle ends or the queue is spent.
 */
function animaster_party_pve_resolve_round($conn, array $battle, $round, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_party_pve'];
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);

    if (!$wild || (int) $wild['current_hp'] <= 0)
    {
        return ['error' => 'BATTLE_ENDED'];
    }

    $alive_party = animaster_party_pve_alive_party_participants($participants);
    $party_by_user = [];

    foreach ($alive_party as $p)
    {
        $party_by_user[(int) $p['id_user_ig']] = $p;
    }

    $choices = animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round);
    $confirmed_by_user = [];

    foreach ($choices as $choice)
    {
        if (trim((string) $choice['flg_confirmed']) !== 'Y')
        {
            continue;
        }

        $id_user_ig = (int) $choice['id_user_ig'];

        if (!isset($party_by_user[$id_user_ig]))
        {
            continue;
        }

        $confirmed_by_user[$id_user_ig] = $choice;
    }

    if (!$confirmed_by_user)
    {
        return ['error' => 'NO_CONFIRMED_CHOICES'];
    }

    $slots = [];
    $idx = 0;

    foreach ($confirmed_by_user as $id_user_ig => $choice)
    {
        $slots[] = [
            'kind' => 'party',
            'id_user_ig' => $id_user_ig,
            'spd' => (float) $party_by_user[$id_user_ig]['spd'],
            'choice' => $choice,
            'idx' => $idx++
        ];
    }

    $wild_action_count = count($alive_party);

    for ($i = 0; $i < $wild_action_count; $i++)
    {
        $slots[] = [
            'kind' => 'wild',
            'spd' => (float) $wild['spd'],
            'idx' => $idx++
        ];
    }

    usort($slots, function ($a, $b)
    {
        if ($a['spd'] !== $b['spd'])
        {
            return $b['spd'] <=> $a['spd'];
        }

        return $a['idx'] <=> $b['idx'];
    });

    $order_in_turn = 0;
    $battle_status = 'ongoing';

    foreach ($slots as $slot)
    {
        if ($battle_status !== 'ongoing')
        {
            break;
        }

        if ($slot['kind'] === 'party')
        {
            $id_user_ig = $slot['id_user_ig'];
            $actor = $party_by_user[$id_user_ig];

            if (trim((string) $actor['flg_fainted']) === 'S' || (int) $actor['current_hp'] <= 0)
            {
                continue;
            }

            $choice = $slot['choice'];
            $action_type = (string) $choice['action_type'];

            if ($action_type === 'ability')
            {
                $result = animaster_party_pve_apply_ability_choice($conn, $actor, $wild, (int) $choice['action_id'], $id_user_ig, $lang_suffix);
            }
            else if ($action_type === 'switch')
            {
                $result = animaster_party_pve_apply_switch_choice($conn, $actor, (int) $choice['action_id'], $id_user_ig, $lang_suffix);
            }
            else if ($action_type === 'item')
            {
                $result = animaster_party_pve_apply_item_choice($conn, $actor, $id_user_ig, (int) $choice['id_item_type_selected'], (int) $choice['action_id'], $lang_suffix);
            }
            else if ($action_type === 'flee')
            {
                $result = animaster_party_pve_apply_flee_action($actor, $id_user_ig, $wild, $lang_suffix);
            }
            else
            {
                continue;
            }

            if (empty($result['ok']))
            {
                continue;
            }

            if (isset($result['wild']))
            {
                $wild = $result['wild'];
            }

            if (isset($result['actor']))
            {
                $party_by_user[$id_user_ig] = $result['actor'];
                $actor = $result['actor'];
            }

            $order_in_turn++;

            if ($action_type === 'flee')
            {
                $battle_status = 'fled';

                animaster_party_pve_finish_battle(
                    $conn,
                    $battle,
                    'fled',
                    (int) $battle['id_wild_animal'],
                    array_values($party_by_user),
                    $wild,
                    $lang_suffix
                );
            }
            else
            {
                $battle_status = animaster_party_pve_check_end($conn, $battle, array_values($party_by_user), $wild);

                if ($battle_status === 'victory')
                {
                    animaster_party_pve_finish_battle(
                        $conn,
                        $battle,
                        'victory',
                        (int) $battle['id_wild_animal'],
                        array_values($party_by_user),
                        $wild,
                        $lang_suffix
                    );
                }
            }

            animaster_party_pve_insert_move(
                $conn,
                $id_battle,
                $round,
                $id_user_ig,
                $actor,
                $wild,
                $result['move_type'],
                $result['id_rif'],
                $result['move_description'],
                $result['move_hit'],
                $battle_status,
                $result['protagonist_type'],
                $result['id_protagonist'],
                $result['target_type'],
                $result['id_target'],
                $order_in_turn
            );
        }
        else
        {
            $target = animaster_party_pve_pick_wild_target(array_values($party_by_user));

            if (!$target)
            {
                continue;
            }

            $result = animaster_party_pve_apply_wild_action($conn, $wild, $target, $lang_suffix);
            $wild = $result['wild'];
            $party_by_user[(int) $result['target']['id_user_ig']] = $result['target'];

            $order_in_turn++;

            $battle_status = animaster_party_pve_check_end($conn, $battle, array_values($party_by_user), $wild);

            if ($battle_status === 'defeat')
            {
                animaster_party_pve_finish_battle(
                    $conn,
                    $battle,
                    'defeat',
                    (int) $battle['id_wild_animal'],
                    array_values($party_by_user),
                    $wild,
                    $lang_suffix
                );
            }

            animaster_party_pve_insert_move(
                $conn,
                $id_battle,
                $round,
                null,
                $result['target'],
                $wild,
                $result['move_type'],
                $result['id_rif'],
                $result['move_description'],
                $result['move_hit'],
                $battle_status,
                $result['protagonist_type'],
                $result['id_protagonist'],
                $result['target_type'],
                $result['id_target'],
                $order_in_turn
            );
        }
    }

    animaster_party_pve_clear_turn_choices($conn, $id_battle, $round);

    $stmt = $conn->prepare('
        UPDATE battles_party_pve
        SET current_turn = :round, dt_m = NOW()
        WHERE id_battle_party_pve = :id_battle
    ');
    $stmt->execute([
        ':round' => (int) $round,
        ':id_battle' => $id_battle
    ]);

    return ['ok' => true, 'round' => $round, 'status' => $battle_status];
}

/**
 * The leader's flee choice: ends the battle the moment it is processed in
 * speed order, regardless of any lower-speed actions still queued this round.
 */
function animaster_party_pve_apply_flee_action(array $actor, $id_user_ig, array $wild, $lang_suffix)
{
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

    return [
        'ok' => true,
        'actor' => $actor,
        'move_type' => 'action',
        'id_rif' => 4,
        'move_description' => $description,
        'move_hit' => 'S',
        'protagonist_type' => 'player',
        'id_protagonist' => (int) $id_user_ig,
        'target_type' => 'wild',
        'id_target' => (int) $wild['id_animal']
    ];
}

/**
 * Human-readable label for a staged choice (ability/animal/item name), shown
 * to every party member in the "current actions" panel while planning.
 */
function animaster_party_pve_describe_choice($conn, array $choice, $lang_suffix)
{
    $type = (string) $choice['action_type'];

    if ($type === 'ability')
    {
        $ability = animaster_party_pve_fetch_ability($conn, (int) $choice['action_id'], $lang_suffix);

        return $ability ? (string) $ability['ability'] : '';
    }

    if ($type === 'switch')
    {
        $stmt = $conn->prepare('SELECT nickname FROM animals WHERE id_animal = :id LIMIT 1');
        $stmt->execute([':id' => (int) $choice['action_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (string) $row['nickname'] : '';
    }

    if ($type === 'item')
    {
        $lang_suffix_safe = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);
        $stmt = $conn->prepare('SELECT nome' . $lang_suffix_safe . ' AS item_name FROM item_types WHERE id_item_type = :id LIMIT 1');
        $stmt->execute([':id' => (int) $choice['id_item_type_selected']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (string) $row['item_name'] : '';
    }

    return '';
}

function animaster_party_pve_build_meta($conn, array $battle, $id_user_ig, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_party_pve'];
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $battle_open = trim((string) $battle['flg_status']) === 'O';
    $round = (int) $battle['current_turn'] + 1;

    $alive_party = animaster_party_pve_alive_party_participants($participants);
    $alive_user_ids = animaster_party_pve_alive_party_user_ids($alive_party);
    $confirm_required = count($alive_party);

    $choices = $battle_open ? animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round) : [];
    $choice_by_user = [];

    foreach ($choices as $choice)
    {
        $choice_by_user[(int) $choice['id_user_ig']] = $choice;
    }

    $confirm_done = 0;

    foreach ($choice_by_user as $choice)
    {
        if (trim((string) $choice['flg_confirmed']) === 'Y')
        {
            $confirm_done++;
        }
    }

    $my_choice = $choice_by_user[(int) $id_user_ig] ?? null;
    $is_eligible = in_array((int) $id_user_ig, $alive_user_ids, true);

    $allies = [];

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

        $ally_choice = $choice_by_user[(int) $p['id_user_ig']] ?? null;

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
            'is_self' => (int) $p['id_user_ig'] === (int) $id_user_ig,
            'has_choice' => $ally_choice !== null,
            'confirmed' => $ally_choice !== null && trim((string) $ally_choice['flg_confirmed']) === 'Y',
            'action_type' => $ally_choice ? (string) $ally_choice['action_type'] : '',
            'action_label' => $ally_choice ? animaster_party_pve_describe_choice($conn, $ally_choice, $lang_suffix) : ''
        ];
    }

    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);
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
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0
        ];
    }

    return [
        'current_turn' => (int) $battle['current_turn'],
        'round' => $round,
        'can_act' => $battle_open && $is_eligible,
        'is_eligible' => $is_eligible,
        'confirm_required' => $confirm_required,
        'confirm_done' => $confirm_done,
        'my_action_type' => $my_choice ? (string) $my_choice['action_type'] : '',
        'my_action_id' => $my_choice ? (int) $my_choice['action_id'] : 0,
        'my_confirmed' => $my_choice !== null && trim((string) $my_choice['flg_confirmed']) === 'Y',
        'party_allies' => $allies,
        'wild_combatants' => $wild_combatants,
        'wild_hp' => $wild ? (int) $wild['current_hp'] : 0,
        'wild_max_hp' => $wild ? (int) $wild['max_hp'] : 0,
        'battle_finished' => !$battle_open,
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

/**
 * Handles both: (a) a party member staging/confirming/un-confirming their
 * round action, and (b) plain polling (empty $type) for the current planning
 * state / newly resolved moves. Always returns the full move history plus
 * the current battle row; the caller builds planning meta on top of it.
 */
function animaster_party_pve_handle_turn_request(
    $conn,
    $id_battle,
    $id_user_ig,
    $turn,
    $restarting,
    $type,
    $id_action,
    $lang_suffix,
    $id_item_type_selected = 0
)
{
    $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);

    if (!$battle || !animaster_party_pve_user_in_battle($conn, (int) $id_battle, (int) $id_user_ig))
    {
        return ['error' => 'BATTLE_NOT_FOUND'];
    }

    $battle_open = trim((string) $battle['flg_status']) === 'O';

    if ((string) $type !== '')
    {
        if (!$battle_open)
        {
            return ['error' => 'BATTLE_ENDED'];
        }

        $round = (int) $battle['current_turn'] + 1;

        if ((int) $turn !== $round)
        {
            return ['error' => 'TURN_OUT_OF_SYNC'];
        }

        $participants = animaster_party_pve_fetch_participants($conn, (int) $id_battle);
        $alive_party = animaster_party_pve_alive_party_participants($participants);
        $alive_user_ids = animaster_party_pve_alive_party_user_ids($alive_party);

        if (!in_array((int) $id_user_ig, $alive_user_ids, true))
        {
            return ['error' => 'NOT_ELIGIBLE'];
        }

        if ($type === 'confirm')
        {
            $existing = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig);

            if (!$existing)
            {
                return ['error' => 'NO_CHOICE_STAGED'];
            }

            animaster_party_pve_set_turn_choice_confirmed($conn, $id_battle, $round, $id_user_ig, true);
        }
        else if ($type === 'unconfirm')
        {
            animaster_party_pve_set_turn_choice_confirmed($conn, $id_battle, $round, $id_user_ig, false);
        }
        else if ($type === 'action' && (int) $id_action === 4)
        {
            if ((int) $battle['id_user_ig_leader'] !== (int) $id_user_ig)
            {
                return ['error' => 'NOT_LEADER'];
            }

            animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'flee', 0);
        }
        else if ($type === 'ability')
        {
            $validated = animaster_party_pve_validate_ability_choice($conn, (int) $id_action, $lang_suffix);

            if (!empty($validated['error']))
            {
                return $validated;
            }

            animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'ability', (int) $id_action);
        }
        else if ($type === 'switch')
        {
            $actor = null;

            foreach ($alive_party as $p)
            {
                if ((int) $p['id_user_ig'] === (int) $id_user_ig)
                {
                    $actor = $p;
                    break;
                }
            }

            if (!$actor)
            {
                return ['error' => 'NOT_ELIGIBLE'];
            }

            $validated = animaster_party_pve_validate_switch_choice($conn, $actor, (int) $id_action, (int) $id_user_ig);

            if (!empty($validated['error']))
            {
                return $validated;
            }

            animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'switch', (int) $id_action);
        }
        else if ($type === 'use_on')
        {
            $validated = animaster_party_pve_validate_item_choice(
                $conn,
                $id_user_ig,
                (int) $id_item_type_selected,
                (int) $id_action,
                $lang_suffix
            );

            if (!empty($validated['error']))
            {
                return $validated;
            }

            animaster_party_pve_save_turn_choice(
                $conn,
                $id_battle,
                $round,
                $id_user_ig,
                'item',
                (int) $id_action,
                (int) $id_item_type_selected
            );
        }
        else
        {
            return ['error' => 'UNSUPPORTED_ACTION'];
        }

        $confirmed_count = animaster_party_pve_count_confirmed_turn_choices($conn, $id_battle, $round);
        $leader_choice = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, (int) $battle['id_user_ig_leader']);
        $leader_fled_confirmed = $leader_choice
            && (string) $leader_choice['action_type'] === 'flee'
            && trim((string) $leader_choice['flg_confirmed']) === 'Y';

        if ($confirmed_count >= count($alive_party) || $leader_fled_confirmed)
        {
            $resolve_result = animaster_party_pve_resolve_round($conn, $battle, $round, $lang_suffix);

            if (!empty($resolve_result['error']))
            {
                return $resolve_result;
            }
        }
    }

    $battle = animaster_party_pve_fetch_battle($conn, (int) $id_battle);
    $history_turn = animaster_party_pve_max_turn($conn, (int) $id_battle);
    $rows = animaster_party_pve_fetch_moves_through_turn($conn, (int) $id_battle, $history_turn, $lang_suffix);

    if (!$rows)
    {
        return ['error' => 'NO_BATTLE_MOVES'];
    }

    return [
        'ok' => true,
        'battle' => $battle,
        'rows' => $rows
    ];
}
