<?php

/**
 * Solo PvE mode on the unified combat schema (docs/modules/005c_full_combat_unification.md).
 * Battle row lives in `battles` (battle_type = solo_pve, planning_mode = instant); the two
 * fighters (side A = player animal, side B = wild) live in `battle_participants` and are the
 * authoritative live state (no per-turn snapshot table); append-only log in `battle_moves`.
 *
 * External surface kept stable for callers: animaster_solo_pve_start(),
 * animaster_solo_pve_handle_request(), animaster_solo_pve_active_for_user().
 * Reuses generic helpers already ported for pvp_duel / party_pve (animal snapshot,
 * wild snapshot, ability fetch, item/switch validation) instead of duplicating them.
 */

require_once __DIR__ . '/pvp.php';
require_once __DIR__ . '/party_pve.php';
require_once __DIR__ . '/f.php';
require_once __DIR__ . '/combat/CombatSession.php';
require_once __DIR__ . '/combat/TurnQueue.php';
require_once __DIR__ . '/combat/AiWild.php';
require_once __DIR__ . '/combat/MoveResolver.php';
require_once __DIR__ . '/combat/BattleRepository.php';
require_once __DIR__ . '/combat/BattleParticipantFactory.php';

function animaster_solo_pve_start($conn, $id_user_ig, $id_zone, $id_wild_animal, $lang_suffix)
{
    $id_user_ig = (int) $id_user_ig;
    $id_zone = (int) $id_zone;
    $id_wild_animal = (int) $id_wild_animal;
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    $stmt = $conn->prepare('SELECT id_battle FROM wild_animals WHERE id_wild_animal = :id LIMIT 1');
    $stmt->execute([':id' => $id_wild_animal]);

    if ((int) $stmt->fetchColumn() > 0)
    {
        return ['error' => 'TOO_LATE'];
    }

    $wild_snap = animaster_party_pve_fetch_wild_snapshot($conn, $id_wild_animal, $lang_suffix);

    if (!$wild_snap)
    {
        return ['error' => 'WILD_NOT_FOUND'];
    }

    $id_first_animal = animaster_pvp_fetch_first_team_animal_id($conn, $id_user_ig);

    if ($id_first_animal <= 0)
    {
        return ['error' => 'NO_TEAM_ANIMAL'];
    }

    $player_snap = animaster_pvp_fetch_animal_snapshot_buffed($conn, $id_first_animal, $lang_suffix);

    if (!$player_snap)
    {
        return ['error' => 'NO_TEAM_ANIMAL'];
    }

    try
    {
        $conn->beginTransaction();

        $id_battle = BattleRepository::createBattle($conn, [
            'battle_type' => 'solo_pve',
            'planning_mode' => 'instant',
            'id_zone' => $id_zone,
            'id_user_ig_initiator' => $id_user_ig
        ]);

        $stmt = $conn->prepare('
            UPDATE wild_animals
            SET id_battle = :id_battle, battle_type = \'solo_pve\', dt_modifica = NOW()
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

        BattleRepository::insertParticipant(
            $conn,
            $id_battle,
            BattleParticipantFactory::playerAnimal($player_snap, BattleRepository::SIDE_A, $id_user_ig, 1)
        );
        BattleRepository::insertParticipant(
            $conn,
            $id_battle,
            BattleParticipantFactory::wild($wild_snap, BattleRepository::SIDE_B, 1)
        );

        animaster_solo_pve_insert_start_move($conn, $id_battle);

        $conn->commit();

        return [
            'ok' => true,
            'id_battle' => $id_battle,
            'battle_type' => 'solo_pve'
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

/**
 * @return array{0: array<string,mixed>|null, 1: array<string,mixed>|null} [sideA player, sideB wild]
 */
function animaster_solo_pve_fetch_participants_ab($conn, $id_battle)
{
    $a = BattleRepository::fetchParticipantsBySide($conn, (int) $id_battle, BattleRepository::SIDE_A);
    $b = BattleRepository::fetchParticipantsBySide($conn, (int) $id_battle, BattleRepository::SIDE_B);

    return [$a ? $a[0] : null, $b ? $b[0] : null];
}

/**
 * State shape reused from pvp.php (p_a_ = side A / player, w_a_ = side B / wild) plus
 * a w_a_nickname alias, since solo wilds don't have a real nickname (species name is used).
 */
function animaster_solo_pve_current_state($conn, $id_battle)
{
    list($pa, $pb) = animaster_solo_pve_fetch_participants_ab($conn, $id_battle);

    if (!$pa || !$pb)
    {
        return null;
    }

    $state = animaster_pvp_state_from_participants($pa, $pb);
    $state['w_a_nickname'] = (string) $pb['species_name'];

    return $state;
}

function animaster_solo_pve_insert_start_move($conn, $id_battle)
{
    $state = animaster_solo_pve_current_state($conn, $id_battle);

    if (!$state)
    {
        return;
    }

    $meta = animaster_pvp_move_meta($conn, $state, '', 'start', 0, 'start', 0);

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

/**
 * @param array<string,mixed> $state animaster_solo_pve_current_state() shape
 */
function animaster_solo_pve_insert_move(
    $conn,
    $id_battle,
    $round,
    $order_in_turn,
    array $state,
    $move_type,
    $id_rif,
    $move_speed,
    $move_description,
    $move_hit,
    $status,
    $protagonist_type,
    $id_protagonist,
    $target_type,
    $id_target,
    $id_user_ig_actor,
    $lang_suffix
)
{
    $meta = animaster_pvp_move_meta($conn, $state, $lang_suffix, $protagonist_type, $id_protagonist, $target_type, $id_target);
    $is_wild_actor = ((string) $protagonist_type === 'wild_animal');

    return BattleRepository::insertMove($conn, [
        'id_battle' => (int) $id_battle,
        'round' => (int) $round,
        'order_in_turn' => (int) $order_in_turn,
        'id_actor_participant' => $is_wild_actor ? (int) $state['w_a_participant'] : (int) $state['p_a_participant'],
        'id_target_participant' => $is_wild_actor ? (int) $state['p_a_participant'] : (int) $state['w_a_participant'],
        'id_user_ig_actor' => $id_user_ig_actor ? (int) $id_user_ig_actor : null,
        'move_type' => (string) $move_type,
        'id_rif' => (int) $id_rif,
        'move_speed' => (float) $move_speed,
        'move_description' => (string) $move_description,
        'move_hit' => (string) $move_hit,
        'actor_hp_after' => (int) $state['p_a_res_hp'],
        'target_hp_after' => (int) $state['w_a_res_hp'],
        'resulting_battle_status' => (string) $status,
        'meta' => $meta
    ]);
}

/**
 * Flatten a battle_moves row (+ meta_json) into the legacy p_a_ / w_a_ shape
 * the client combat log + CombatantSnapshot::fromSoloMoveSide() consume.
 */
function animaster_solo_pve_flatten_move(array $row)
{
    $meta = !empty($row['meta_json']) ? json_decode($row['meta_json'], true) : [];
    $flat = array_merge($row, is_array($meta) ? $meta : []);
    $flat['turn'] = (int) $row['round'];
    $flat['id_battle_solo_pve'] = (int) $row['id_battle'];
    $flat['id_battle_solo_pve_move'] = (int) $row['id_battle_move'];

    return $flat;
}

function animaster_solo_pve_fetch_moves_for_turn($conn, $id_battle, $turn)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM battle_moves
        WHERE id_battle = :id_battle
          AND round = :turn
        ORDER BY order_in_turn ASC, id_battle_move ASC
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':turn' => (int) $turn
    ]);

    return array_map('animaster_solo_pve_flatten_move', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function animaster_solo_pve_max_turn($conn, $id_battle)
{
    return BattleRepository::maxRound($conn, (int) $id_battle);
}

/**
 * Legacy-shaped battle row: exposes id_battle_solo_pve, finished, id_user_ig
 * (owner, from the side A participant) and id_wild_animal (from side B).
 */
function animaster_solo_pve_fetch_battle($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT b.*,
               b.id_battle AS id_battle_solo_pve,
               CASE WHEN b.flg_status = \'F\' THEN \'S\' ELSE \'N\' END AS finished,
               pa.id_user_ig AS id_user_ig,
               pb.id_wild_animal AS id_wild_animal
        FROM battles b
        LEFT JOIN battle_participants pa ON pa.id_battle = b.id_battle AND pa.side = \'A\'
        LEFT JOIN battle_participants pb ON pb.id_battle = b.id_battle AND pb.side = \'B\'
        WHERE b.id_battle = :id_battle
          AND b.battle_type = \'solo_pve\'
        LIMIT 1
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_solo_pve_active_for_user($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT b.id_battle, b.current_round
        FROM battles b
        INNER JOIN battle_participants p
            ON p.id_battle = b.id_battle
           AND p.id_user_ig = :id_user_ig
           AND p.side = \'A\'
        WHERE b.battle_type = \'solo_pve\'
          AND b.flg_status = \'O\'
        ORDER BY b.id_battle DESC
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => (int) $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        return null;
    }

    return [
        'id_battle' => (int) $row['id_battle'],
        'battle_type' => 'solo_pve',
        'current_battle_turn' => (int) $row['current_round']
    ];
}

/**
 * @return array<string, int>
 */
function animaster_solo_pve_fetch_costanti($conn)
{
    $values = [
        'lvl_up_constant_animal' => 41,
        'lvl_up_constant_player' => 81,
        'exp_loss_percent_on_death' => 5
    ];

    $result = $conn->query('SELECT costante, valore FROM costanti');

    if (!$result)
    {
        return $values;
    }

    while ($row = $result->fetch(PDO::FETCH_ASSOC))
    {
        $name = (string) $row['costante'];

        if (array_key_exists($name, $values))
        {
            $values[$name] = (int) $row['valore'];
        }
    }

    return $values;
}

function animaster_solo_pve_team_alive_hp($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT COALESCE(SUM(COALESCE(current_hp, 0)), 0) AS tot
        FROM animals
        WHERE id_user_ig = :id_user_ig
          AND team_position > 0
    ');
    $stmt->execute([':id_user_ig' => (int) $id_user_ig]);

    return (int) $stmt->fetchColumn();
}

function animaster_solo_pve_apply_ability($conn, $id_battle, $turn, $order_in_turn, $id_user_ig, array $state, $id_ability, $lang_suffix)
{
    $ability = animaster_party_pve_fetch_ability($conn, $id_ability, $lang_suffix);

    if (!$ability)
    {
        return ['error' => 'INVALID_ABILITY'];
    }

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
        'nickname' => (string) $state['p_a_nickname']
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
        'nickname' => (string) $state['w_a_species']
    ];

    $move_result = MoveResolver::resolveAbility($ability, $attacker, $defender, [
        'lang_suffix' => (string) $lang_suffix,
        'conn' => $conn,
        'battle_type' => CombatSession::TYPE_SOLO,
        'id_battle' => (int) $id_battle,
        'applied_at_turn' => (int) $turn,
        'attacker_entity' => [
            'entity_type' => 'animal',
            'id_entity' => (int) $state['p_a_id'],
            'id_user_ig' => (int) $id_user_ig
        ],
        'defender_entity' => [
            'entity_type' => 'wild',
            'id_entity' => (int) $state['w_a_id'],
            'id_user_ig' => null
        ]
    ]);

    $state['p_a_res_hp'] = (int) $move_result['attacker']['current_hp'];
    $state['w_a_res_hp'] = (int) $move_result['defender']['current_hp'];

    if (!class_exists('BUFFS'))
    {
        require_once __DIR__ . '/buffs.php';
    }

    BUFFS::persistAnimalHpAfterBattle($conn, (int) $state['p_a_id'], (int) $state['p_a_res_hp']);

    BattleRepository::updateParticipant($conn, (int) $state['p_a_participant'], [
        'current_hp' => (int) $state['p_a_res_hp'],
        'flg_fainted' => (int) $state['p_a_res_hp'] <= 0 ? 'S' : 'N'
    ]);
    BattleRepository::updateParticipant($conn, (int) $state['w_a_participant'], [
        'current_hp' => (int) $state['w_a_res_hp'],
        'flg_fainted' => (int) $state['w_a_res_hp'] <= 0 ? 'S' : 'N'
    ]);

    $b_status = 'ongoing';

    if ($state['w_a_res_hp'] <= 0)
    {
        $b_status = 'win';

        FUNZIONI::AddExpFromWildAnimal($conn, $id_user_ig, (int) $state['p_a_id'], (int) $state['w_a_id_species'], (int) $state['w_a_lvl'], $lang_suffix);

        $stmt = $conn->prepare('SELECT lvl, experience FROM animals WHERE id_animal = :id LIMIT 1');
        $stmt->execute([':id' => (int) $state['p_a_id']]);
        $animal_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($animal_row)
        {
            $state['p_a_cur_exp'] = (int) $animal_row['experience'];
            $state['p_a_lvl'] = (int) $animal_row['lvl'];
        }

        FUNZIONI::AddDropsWildAnimalUser($conn, (int) $state['w_a_id_species'], (int) $state['w_a_lvl'], $id_user_ig, $lang_suffix, 1.0, (int) $state['w_a_id_element']);

        if (!class_exists('QUESTS'))
        {
            require_once __DIR__ . '/quests.php';
        }

        QUESTS::onWildDefeated($conn, $id_user_ig, (int) $state['w_a_id_species'], $lang_suffix);
    }

    if ($state['p_a_res_hp'] <= 0 && animaster_solo_pve_team_alive_hp($conn, $id_user_ig) <= 0)
    {
        $b_status = 'defeat';
    }

    animaster_solo_pve_insert_move(
        $conn, $id_battle, $turn, $order_in_turn, $state,
        'ability', (int) $move_result['id_ability'], (float) $state['p_a_res_spd'],
        $move_result['move_description'], $move_result['move_hit'], $b_status,
        'user_animal', (int) $state['p_a_id'], 'wild_animal', (int) $state['w_a_id'],
        $id_user_ig, $lang_suffix
    );

    return ['status' => $b_status, 'state' => $state];
}

function animaster_solo_pve_apply_wild_move($conn, $id_battle, $turn, $order_in_turn, $id_user_ig, array $state, array $costanti, $lang_suffix)
{
    $ability = AiWild::pickRandomAbility($conn, (int) $state['w_a_id_species'], (int) $state['w_a_lvl'], $lang_suffix);

    if (!$ability)
    {
        return ['status' => 'ongoing', 'state' => $state];
    }

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
        'nickname' => (string) $state['w_a_nickname']
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
        'nickname' => (string) $state['p_a_nickname']
    ];

    $move_result = MoveResolver::resolveAbility($ability, $attacker, $defender, [
        'lang_suffix' => (string) $lang_suffix,
        'conn' => $conn,
        'battle_type' => CombatSession::TYPE_SOLO,
        'id_battle' => (int) $id_battle,
        'applied_at_turn' => (int) $turn,
        'attacker_entity' => [
            'entity_type' => 'wild',
            'id_entity' => (int) $state['w_a_id'],
            'id_user_ig' => null
        ],
        'defender_entity' => [
            'entity_type' => 'animal',
            'id_entity' => (int) $state['p_a_id'],
            'id_user_ig' => (int) $id_user_ig
        ]
    ]);

    $state['w_a_res_hp'] = (int) $move_result['attacker']['current_hp'];
    $state['p_a_res_hp'] = (int) $move_result['defender']['current_hp'];

    if (!class_exists('BUFFS'))
    {
        require_once __DIR__ . '/buffs.php';
    }

    BUFFS::persistAnimalHpAfterBattle($conn, (int) $state['p_a_id'], (int) $state['p_a_res_hp']);

    BattleRepository::updateParticipant($conn, (int) $state['w_a_participant'], [
        'current_hp' => (int) $state['w_a_res_hp'],
        'flg_fainted' => (int) $state['w_a_res_hp'] <= 0 ? 'S' : 'N'
    ]);
    BattleRepository::updateParticipant($conn, (int) $state['p_a_participant'], [
        'current_hp' => (int) $state['p_a_res_hp'],
        'flg_fainted' => (int) $state['p_a_res_hp'] <= 0 ? 'S' : 'N'
    ]);

    $b_status = 'ongoing';

    if ($state['w_a_res_hp'] <= 0)
    {
        $b_status = 'win';
    }

    if ($state['p_a_res_hp'] <= 0)
    {
        $lvl = (int) $state['p_a_lvl'];
        $max = $costanti['lvl_up_constant_animal'] * ($lvl + 1) * ($lvl + 1) * ($lvl + 1);
        $min = $costanti['lvl_up_constant_animal'] * $lvl * $lvl * $lvl;
        $five_perc = ($costanti['exp_loss_percent_on_death'] / 100) * ($max - $min);
        $state['p_a_cur_exp'] = (int) ((int) $state['p_a_cur_exp'] - $five_perc);

        $stmt = $conn->prepare('UPDATE animals SET experience = :exp WHERE id_animal = :id');
        $stmt->execute([
            ':exp' => (int) $state['p_a_cur_exp'],
            ':id' => (int) $state['p_a_id']
        ]);

        if (animaster_solo_pve_team_alive_hp($conn, $id_user_ig) <= 0)
        {
            $b_status = 'defeat';

            $stmt = $conn->prepare('SELECT exp_total, level FROM users_ig WHERE id_user_ig = :id LIMIT 1');
            $stmt->execute([':id' => (int) $id_user_ig]);
            $user_row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_row)
            {
                $u_level = (int) $user_row['level'];
                $u_max = $costanti['lvl_up_constant_player'] * ($u_level + 1) * ($u_level + 1) * ($u_level + 1);
                $u_min = $costanti['lvl_up_constant_player'] * $u_level * $u_level * $u_level;
                $u_five_perc = ($costanti['exp_loss_percent_on_death'] / 100) * ($u_max - $u_min);
                $user_exp = (int) ((int) $user_row['exp_total'] - $u_five_perc);

                $stmt = $conn->prepare('UPDATE users_ig SET exp_total = :exp WHERE id_user_ig = :id');
                $stmt->execute([
                    ':exp' => $user_exp,
                    ':id' => (int) $id_user_ig
                ]);
            }
        }

        FUNZIONI::AdjustUserLvlFromExp($conn, $id_user_ig, $lang_suffix);
        $state['p_a_lvl'] = (int) FUNZIONI::AdjustAnimalLvlFromExp($conn, (int) $state['p_a_id'], $lang_suffix);
    }

    animaster_solo_pve_insert_move(
        $conn, $id_battle, $turn, $order_in_turn, $state,
        'ability', (int) $move_result['id_ability'], (float) $state['w_a_res_spd'],
        $move_result['move_description'], $move_result['move_hit'], $b_status,
        'wild_animal', (int) $state['w_a_id'], 'user_animal', (int) $state['p_a_id'],
        null, $lang_suffix
    );

    return ['status' => $b_status, 'state' => $state];
}

function animaster_solo_pve_apply_escape($conn, $id_battle, $turn, $order_in_turn, $id_user_ig, array $state, $lang_suffix)
{
    $stmt = $conn->prepare('SELECT level FROM users_ig WHERE id_user_ig = :id LIMIT 1');
    $stmt->execute([':id' => (int) $id_user_ig]);
    $user_lvl = (int) $stmt->fetchColumn();

    $diff = $user_lvl - (int) $state['w_a_lvl'];
    $chance_to_block_escape = (-3.5 * $diff) + 35;
    $chance_to_block_escape = max(10, min(90, $chance_to_block_escape));

    $blocked = rand(0, 100) < $chance_to_block_escape;
    $species = (string) $state['w_a_species'];

    if ($blocked)
    {
        $b_status = 'ongoing';
        $description = $species . ' blocked your escape.';

        if ($lang_suffix === '_it')
        {
            $description = $species . ' ha bloccato la tua fuga!';
        }
        else if ($lang_suffix === '_pt')
        {
            $description = $species . ' bloqueou a tua fuga!';
        }
    }
    else
    {
        $b_status = 'escaped';
        $description = 'You have escaped safely!';

        if ($lang_suffix === '_it')
        {
            $description = 'Sei scappato con successo!';
        }
        else if ($lang_suffix === '_pt')
        {
            $description = 'Fugiste com sucesso!';
        }
    }

    animaster_solo_pve_insert_move(
        $conn, $id_battle, $turn, $order_in_turn, $state,
        'escape', 0, 999, $description, 'I', $b_status,
        'user', $id_user_ig, 'user', $id_user_ig, $id_user_ig, $lang_suffix
    );

    return ['status' => $b_status, 'state' => $state];
}

function animaster_solo_pve_apply_switch($conn, $id_battle, $turn, $order_in_turn, $id_user_ig, array $state, $id_new_animal, $lang_suffix)
{
    $actor_like = [
        'id_animal' => (int) $state['p_a_id'],
        'current_hp' => (int) $state['p_a_res_hp']
    ];
    $validated = animaster_party_pve_validate_switch_choice($conn, $actor_like, $id_new_animal, $id_user_ig);

    if (!empty($validated['error']))
    {
        return ['error' => $validated['error']];
    }

    $snap = animaster_pvp_fetch_animal_snapshot_buffed($conn, $id_new_animal, $lang_suffix);

    if (!$snap)
    {
        return ['error' => 'INVALID_ANIMAL'];
    }

    $previous_nickname = (string) $state['p_a_nickname'];

    BattleRepository::updateParticipant($conn, (int) $state['p_a_participant'], [
        'id_animal' => (int) $snap['id_animal'],
        'id_entity' => (int) $snap['id_animal'],
        'id_species' => (int) $snap['id_species'],
        'id_element' => (int) $snap['id_element'],
        'lvl' => (int) $snap['lvl'],
        'nickname' => (string) ($snap['nickname'] ?: $snap['species']),
        'species_name' => (string) $snap['species'],
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
        'flg_fainted' => 'N'
    ]);

    $state['p_a_id'] = (int) $snap['id_animal'];
    $state['p_a_id_element'] = (int) $snap['id_element'];
    $state['p_a_id_species'] = (int) $snap['id_species'];
    $state['p_a_species'] = (string) $snap['species'];
    $state['p_a_lvl'] = (int) $snap['lvl'];
    $state['p_a_nickname'] = (string) ($snap['nickname'] ?: $snap['species']);
    $state['p_a_cur_exp'] = (int) $snap['experience'];
    $state['p_a_res_hp'] = (int) $snap['current_hp'];
    $state['p_a_res_max_hp'] = (int) $snap['max_hp'];
    $state['p_a_res_atk'] = (float) $snap['atk'];
    $state['p_a_res_def'] = (float) $snap['def'];
    $state['p_a_res_matk'] = (float) $snap['matk'];
    $state['p_a_res_mdef'] = (float) $snap['mdef'];
    $state['p_a_res_acc'] = (int) $snap['acc'];
    $state['p_a_res_eva'] = (int) $snap['eva'];
    $state['p_a_res_cr'] = (int) $snap['cr'];
    $state['p_a_res_spd'] = (float) $snap['spd'];

    $description = 'You withdrew ' . $previous_nickname . '. ' . $state['p_a_nickname'] . ", it's your turn!";

    if ($lang_suffix === '_it')
    {
        $description = 'Hai rimosso ' . $previous_nickname . '. ' . $state['p_a_nickname'] . ', tocca a te!';
    }
    else if ($lang_suffix === '_pt')
    {
        $description = 'Retiraste ' . $previous_nickname . '. ' . $state['p_a_nickname'] . ', vai tu!';
    }

    $b_status = 'ongoing';

    if ((int) $state['w_a_res_hp'] <= 0)
    {
        $b_status = 'win';
    }

    if ((int) $state['p_a_res_hp'] <= 0 && animaster_solo_pve_team_alive_hp($conn, $id_user_ig) <= 0)
    {
        $b_status = 'defeat';
    }

    animaster_solo_pve_insert_move(
        $conn, $id_battle, $turn, $order_in_turn, $state,
        'switch', (int) $id_new_animal, 999, $description, 'A', $b_status,
        'user', $id_user_ig, 'user_animal', (int) $state['p_a_id'], $id_user_ig, $lang_suffix
    );

    return ['status' => $b_status, 'state' => $state];
}

function animaster_solo_pve_apply_item($conn, $id_battle, $turn, $order_in_turn, $id_user_ig, array $state, $id_target_animal, $id_item_type_selected, $lang_suffix)
{
    $validated = animaster_party_pve_validate_item_choice($conn, $id_user_ig, $id_item_type_selected, $id_target_animal, $lang_suffix);

    if (!empty($validated['error']))
    {
        return ['error' => $validated['error']];
    }

    $target_animal = $validated['target_animal'];
    $item_type = $validated['item_type'];
    $item_row = $validated['item_row'];

    $hp_recover = (int) $item_type['use_effect'];
    $max_hp = (int) $target_animal['max_hp'];
    $new_hp = min($max_hp, (int) $target_animal['current_hp'] + $hp_recover);

    $stmt = $conn->prepare('UPDATE animals SET current_hp = :hp WHERE id_animal = :id');
    $stmt->execute([
        ':hp' => $new_hp,
        ':id' => (int) $id_target_animal
    ]);

    $stmt = $conn->prepare('UPDATE items SET dt_used = NOW(), dt_modifica = NOW() WHERE id_item = :id');
    $stmt->execute([':id' => (int) $item_row['id_item']]);

    if ((int) $id_target_animal === (int) $state['p_a_id'])
    {
        $state['p_a_res_hp'] = $new_hp;

        BattleRepository::updateParticipant($conn, (int) $state['p_a_participant'], [
            'current_hp' => $new_hp,
            'flg_fainted' => $new_hp <= 0 ? 'S' : 'N'
        ]);
    }

    $item_name = (string) ($item_type['item_name'] ?: '');
    $target_nickname = (string) ($target_animal['nickname'] ?: '');
    $description = 'You used ' . $item_name . ' on ' . $target_nickname;

    if ($lang_suffix === '_it')
    {
        $description = 'Hai usato ' . $item_name . ' su ' . $target_nickname;
    }
    else if ($lang_suffix === '_pt')
    {
        $description = 'Usaste ' . $item_name . ' em ' . $target_nickname;
    }

    $b_status = 'ongoing';

    if ((int) $state['w_a_res_hp'] <= 0)
    {
        $b_status = 'win';
    }

    if ((int) $state['p_a_res_hp'] <= 0 && animaster_solo_pve_team_alive_hp($conn, $id_user_ig) <= 0)
    {
        $b_status = 'defeat';
    }

    animaster_solo_pve_insert_move(
        $conn, $id_battle, $turn, $order_in_turn, $state,
        'item', (int) $id_item_type_selected, 999, $description, 'I', $b_status,
        'user', $id_user_ig, 'user_animal', (int) $id_target_animal, $id_user_ig, $lang_suffix
    );

    return ['status' => $b_status, 'state' => $state];
}

/**
 * @return string|null Error code, or null on success.
 */
function animaster_solo_pve_process_turn($conn, array $battle, $id_user_ig, $turn, $type, $id, $id_item_type_selected, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle_solo_pve'];
    $state = animaster_solo_pve_current_state($conn, $id_battle);

    if (!$state)
    {
        return 'NO_STATE';
    }

    $costanti = animaster_solo_pve_fetch_costanti($conn);
    $order = TurnQueue::orderSoloTurnSlots($state['p_a_res_spd'], $state['w_a_res_spd'], $type);
    $p_order_in_turn = $order[0] === 'player' ? 1 : 2;
    $w_order_in_turn = $order[0] === 'wild' ? 1 : 2;

    $b_status = 'ongoing';

    foreach ($order as $slot)
    {
        if ($b_status !== 'ongoing')
        {
            break;
        }

        if ($slot === 'wild')
        {
            if ((int) $state['w_a_res_hp'] > 0)
            {
                $result = animaster_solo_pve_apply_wild_move($conn, $id_battle, $turn, $w_order_in_turn, $id_user_ig, $state, $costanti, $lang_suffix);
                $state = $result['state'];
                $b_status = $result['status'];
            }

            continue;
        }

        if ((int) $state['p_a_res_hp'] <= 0)
        {
            continue;
        }

        $result = null;

        if ($type === 'action' && (int) $id === 4)
        {
            $result = animaster_solo_pve_apply_escape($conn, $id_battle, $turn, $p_order_in_turn, $id_user_ig, $state, $lang_suffix);
        }
        else if ($type === 'switch')
        {
            $result = animaster_solo_pve_apply_switch($conn, $id_battle, $turn, $p_order_in_turn, $id_user_ig, $state, (int) $id, $lang_suffix);
        }
        else if ($type === 'use_on')
        {
            $result = animaster_solo_pve_apply_item($conn, $id_battle, $turn, $p_order_in_turn, $id_user_ig, $state, (int) $id, (int) $id_item_type_selected, $lang_suffix);
        }
        else if ($type === 'ability')
        {
            $result = animaster_solo_pve_apply_ability($conn, $id_battle, $turn, $p_order_in_turn, $id_user_ig, $state, (int) $id, $lang_suffix);
        }

        if ($result === null)
        {
            continue;
        }

        if (!empty($result['error']))
        {
            return $result['error'];
        }

        $state = $result['state'];
        $b_status = $result['status'];
    }

    CombatSession::tickRoundBuffs($conn, CombatSession::TYPE_SOLO, $id_battle);

    BattleRepository::updateBattle($conn, $id_battle, ['current_round' => (int) $turn]);

    if ($b_status !== 'ongoing')
    {
        $winner_alliance = null;

        if ($b_status === 'win')
        {
            $winner_alliance = BattleRepository::SIDE_A;
        }
        else if ($b_status === 'defeat')
        {
            $winner_alliance = BattleRepository::SIDE_B;
        }

        BattleRepository::finishBattle($conn, $id_battle, $b_status, $winner_alliance);
        CombatSession::onBattleEnd($conn, CombatSession::TYPE_SOLO, $id_battle);
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>> Possibly empty; $turn may be adjusted (by-ref) on fallback.
 */
function animaster_solo_pve_fetch_turn_moves($conn, $id_battle, &$turn, $restarting_old_battle)
{
    $rows = animaster_solo_pve_fetch_moves_for_turn($conn, $id_battle, $turn);

    if ($restarting_old_battle && $rows === [])
    {
        $fallback_turn = animaster_solo_pve_max_turn($conn, $id_battle);

        if ($fallback_turn >= 0 && $fallback_turn !== (int) $turn)
        {
            $turn = $fallback_turn;
            $rows = animaster_solo_pve_fetch_moves_for_turn($conn, $id_battle, $turn);
        }
    }

    return $rows;
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function animaster_solo_pve_encode_moves(array $rows)
{
    $parts = [];

    foreach ($rows as $row)
    {
        $parts[] = json_encode($row, JSON_UNESCAPED_UNICODE);
    }

    return implode('#', $parts);
}

/**
 * @param array<int, array<string, mixed>> $moveRows
 */
function animaster_solo_pve_build_meta($conn, $id_battle, array $moveRows, $id_user_ig, $lang_suffix)
{
    $snapshots = CombatSession::combatantsFromSoloMoves($moveRows);

    if (isset($snapshots[0]) && ($snapshots[0]['battle_role'] ?? '') === 'player')
    {
        $snapshots[0]['id_user_ig'] = (int) $id_user_ig;
    }

    return CombatSession::attachCombatants(
        ['battle_type' => CombatSession::TYPE_SOLO],
        $snapshots,
        $conn,
        [
            'battle_type' => CombatSession::TYPE_SOLO,
            'id_battle' => (int) $id_battle,
            'lang' => (string) $lang_suffix
        ]
    );
}

function animaster_solo_pve_error_response($msg)
{
    return [
        'stato' => 'KO',
        'msg' => (string) $msg,
        'response' => ''
    ];
}

/**
 * @param array<string, mixed> $post Raw POST (id_user_ig, id_battle, turn, type, id, lang, id_item_type_selected, …)
 * @return array{stato: string, msg: string, response: string}
 */
function animaster_solo_pve_handle_request($conn, array $post)
{
    $id_user_ig = isset($post['id_user_ig']) ? (int) $post['id_user_ig'] : 0;
    $id_battle = isset($post['id_battle']) ? (int) $post['id_battle'] : 0;
    $turn = isset($post['turn']) ? (int) $post['turn'] : 0;
    $restarting_old_battle = ($post['restarting_old_battle'] ?? 'N') === 'S';
    $lang = isset($post['lang']) ? (string) $post['lang'] : '_it';
    $lang_suffix = preg_replace('/[^_a-z]/i', '', $lang);

    if ($id_user_ig <= 0 || $id_battle <= 0)
    {
        return animaster_solo_pve_error_response('INVALID_BATTLE_REQUEST');
    }

    $battle = animaster_solo_pve_fetch_battle($conn, $id_battle);

    if (!$battle || (int) $battle['id_user_ig'] !== $id_user_ig)
    {
        return animaster_solo_pve_error_response('BATTLE_NOT_FOUND');
    }

    if ($turn > 0 && !$restarting_old_battle)
    {
        $type = isset($post['type']) ? (string) $post['type'] : '';
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $id_item_type_selected = isset($post['id_item_type_selected']) ? (int) $post['id_item_type_selected'] : 0;

        if ($turn !== animaster_solo_pve_max_turn($conn, $id_battle) + 1)
        {
            return animaster_solo_pve_error_response('NO_PREVIOUS_TURN');
        }

        $error = animaster_solo_pve_process_turn($conn, $battle, $id_user_ig, $turn, $type, $id, $id_item_type_selected, $lang_suffix);

        if ($error !== null)
        {
            return animaster_solo_pve_error_response($error);
        }
    }

    $moves = animaster_solo_pve_fetch_turn_moves($conn, $id_battle, $turn, $restarting_old_battle);

    if (!$moves)
    {
        return animaster_solo_pve_error_response('NO_BATTLE_MOVES');
    }

    return [
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => animaster_solo_pve_encode_moves($moves),
        'battle_meta' => json_encode(animaster_solo_pve_build_meta($conn, $id_battle, $moves, $id_user_ig, $lang_suffix), JSON_UNESCAPED_UNICODE)
    ];
}
