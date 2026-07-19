<?php

/**
 * Party vs party scaffold (005c Phase 5): two parties, simultaneous_confirm,
 * one lead animal per in-range member. No equalize / multi-fighter (005d).
 *
 * Reuses party_pve choice/ability/switch/item helpers where the target is any
 * participant row (not wild-specific). Enemy fighters are exposed to the
 * client in wild_combatants[] so the existing party confirm UI can smoke-test.
 */

require_once __DIR__ . '/party.php';
require_once __DIR__ . '/party_pve.php';
require_once __DIR__ . '/pvp.php';
require_once __DIR__ . '/combat/CombatSession.php';
require_once __DIR__ . '/combat/TurnQueue.php';
require_once __DIR__ . '/combat/Permissions.php';
require_once __DIR__ . '/combat/BattleRepository.php';
require_once __DIR__ . '/combat/BattleParticipantFactory.php';

function animaster_party_vs_party_fetch_battle($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT b.*,
               b.id_battle AS id_battle_party_pve,
               b.current_round AS current_turn,
               pa.id_user_ig_leader AS id_user_ig_leader_a,
               pb.id_user_ig_leader AS id_user_ig_leader_b
        FROM battles b
        LEFT JOIN parties pa ON pa.id_party = b.id_party_a
        LEFT JOIN parties pb ON pb.id_party = b.id_party_b
        WHERE b.id_battle = :id_battle
          AND b.battle_type = \'party_vs_party\'
        LIMIT 1
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_vs_party_user_in_battle($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT side
        FROM battle_participants
        WHERE id_battle = :id_battle
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig' => (int) $id_user_ig
    ]);
    $side = $stmt->fetchColumn();

    return $side ? (string) $side : null;
}

function animaster_party_vs_party_participant_id($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_battle_participant
        FROM battle_participants
        WHERE id_battle = :id_battle
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return (int) $stmt->fetchColumn();
}

function animaster_party_vs_party_active_for_user($conn, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT b.id_battle, b.current_round
        FROM battles b
        INNER JOIN battle_participants p
            ON p.id_battle = b.id_battle
           AND p.id_user_ig = :id_user_ig
        WHERE b.battle_type = \'party_vs_party\'
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
        'battle_type' => 'party_vs_party',
        'current_battle_turn' => (int) $row['current_round']
    ];
}

function animaster_party_vs_party_party_has_active_battle($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT id_battle
        FROM battles
        WHERE battle_type = \'party_vs_party\'
          AND flg_status = \'O\'
          AND (id_party_a = :id_party OR id_party_b = :id_party2)
        LIMIT 1
    ');
    $stmt->execute([
        ':id_party' => (int) $id_party,
        ':id_party2' => (int) $id_party
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * @param array<int, array<string, mixed>> $participants
 * @return array<int, array<string, mixed>>
 */
function animaster_party_vs_party_alive_on_side(array $participants, $side)
{
    $alive = [];

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== (string) $side)
        {
            continue;
        }

        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
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

function animaster_party_vs_party_pick_enemy_target(array $enemies)
{
    $alive = [];

    foreach ($enemies as $p)
    {
        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
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

function animaster_party_vs_party_save_turn_choice($conn, $id_battle, $round, $id_user_ig, $action_type, $action_id, $id_item_type_selected = 0)
{
    $previous = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig);

    $changed = $previous
        && ((string) $previous['action_type'] !== (string) $action_type
            || (int) $previous['action_id'] !== (int) $action_id
            || (int) $previous['id_item_type_selected'] !== (int) $id_item_type_selected);

    $id_participant = animaster_party_vs_party_participant_id($conn, $id_battle, $id_user_ig);

    BattleRepository::saveChoice(
        $conn,
        (int) $id_battle,
        (int) $round,
        (int) $id_user_ig,
        $id_participant,
        (string) $action_type,
        (int) $action_id,
        (int) $id_item_type_selected
    );

    if (Permissions::stagedChangeInvalidatesOthers(Permissions::MODE_PARTY_VS_PARTY, (bool) $previous, $changed))
    {
        animaster_party_pve_unconfirm_others($conn, $id_battle, $round, $id_user_ig);
    }
}

function animaster_party_vs_party_count_confirmed_on_side($conn, $id_battle, $round, array $alive_on_side)
{
    $choices = animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round);
    $alive_ids = [];

    foreach ($alive_on_side as $p)
    {
        $alive_ids[(int) $p['id_user_ig']] = true;
    }

    $count = 0;

    foreach ($choices as $choice)
    {
        if (trim((string) $choice['flg_confirmed']) !== 'Y')
        {
            continue;
        }

        if (!isset($alive_ids[(int) $choice['id_user_ig']]))
        {
            continue;
        }

        $count++;
    }

    return $count;
}

/**
 * Snapshot eligible members for one party around a reference point.
 *
 * @return array{0: array<int, array>, 1: string|null} [party_snaps, error]
 */
function animaster_party_vs_party_snapshot_party($conn, $id_party, $id_leader, $ref_x, $ref_z, $id_zone, $lang_suffix)
{
    $eligible = animaster_party_pve_fetch_eligible_members($conn, $id_party, $ref_x, $ref_z, $id_zone);

    if (!$eligible)
    {
        return [[], 'NO_ELIGIBLE_MEMBERS'];
    }

    $leader_in = false;

    foreach ($eligible as $member)
    {
        if ((int) $member['id_user_ig'] === (int) $id_leader)
        {
            $leader_in = true;
            break;
        }
    }

    if (!$leader_in)
    {
        return [[], 'LEADER_TOO_FAR'];
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

    if (!$party_snaps)
    {
        return [[], 'NO_TEAM_ANIMAL'];
    }

    return [$party_snaps, null];
}

/**
 * Dev/smoke start: challenger party leader vs target party leader.
 * Both parties must be distinct; members must be in join radius of ref point.
 */
function animaster_party_vs_party_start($conn, $id_challenger, $id_target_leader, $id_zone, $ref_x, $ref_z, $lang_suffix)
{
    $id_challenger = (int) $id_challenger;
    $id_target_leader = (int) $id_target_leader;
    $id_zone = (int) $id_zone;
    $lang_suffix = preg_replace('/[^_a-z]/i', '', (string) $lang_suffix);

    if ($id_challenger <= 0 || $id_target_leader <= 0 || $id_challenger === $id_target_leader)
    {
        return ['error' => 'INVALID_TARGET'];
    }

    $challenger = animaster_party_fetch_user($conn, $id_challenger);
    $target = animaster_party_fetch_user($conn, $id_target_leader);

    if (!$challenger || !$target)
    {
        return ['error' => 'INVALID_USER'];
    }

    $id_party_a = (int) ($challenger['id_party'] ?? 0);
    $id_party_b = (int) ($target['id_party'] ?? 0);

    if ($id_party_a <= 0 || $id_party_b <= 0)
    {
        return ['error' => 'NOT_IN_PARTY'];
    }

    if ($id_party_a === $id_party_b)
    {
        return ['error' => 'SAME_PARTY'];
    }

    if (!animaster_party_is_leader($conn, $id_party_a, $id_challenger)
        || !animaster_party_is_leader($conn, $id_party_b, $id_target_leader))
    {
        return ['error' => 'NOT_LEADER'];
    }

    if (animaster_party_vs_party_party_has_active_battle($conn, $id_party_a)
        || animaster_party_vs_party_party_has_active_battle($conn, $id_party_b)
        || animaster_party_pve_party_has_active_battle($conn, $id_party_a)
        || animaster_party_pve_party_has_active_battle($conn, $id_party_b))
    {
        return ['error' => 'PARTY_ALREADY_FIGHTING'];
    }

    if (trim((string) ($challenger['flg_battling'] ?? 'N')) === 'S'
        || trim((string) ($target['flg_battling'] ?? 'N')) === 'S')
    {
        return ['error' => 'BUSY'];
    }

    list($snaps_a, $err_a) = animaster_party_vs_party_snapshot_party(
        $conn, $id_party_a, $id_challenger, $ref_x, $ref_z, $id_zone, $lang_suffix
    );

    if ($err_a)
    {
        return ['error' => $err_a];
    }

    list($snaps_b, $err_b) = animaster_party_vs_party_snapshot_party(
        $conn, $id_party_b, $id_target_leader, $ref_x, $ref_z, $id_zone, $lang_suffix
    );

    if ($err_b)
    {
        return ['error' => $err_b];
    }

    try
    {
        $conn->beginTransaction();

        $id_battle = BattleRepository::createBattle($conn, [
            'battle_type' => 'party_vs_party',
            'planning_mode' => 'simultaneous_confirm',
            'id_zone' => $id_zone,
            'id_user_ig_initiator' => $id_challenger,
            'id_party_a' => $id_party_a,
            'id_party_b' => $id_party_b
        ]);

        $team_pos = 1;
        $first_actor = null;

        foreach ($snaps_a as $entry)
        {
            $pid = BattleRepository::insertParticipant(
                $conn,
                $id_battle,
                BattleParticipantFactory::playerAnimal(
                    $entry['snap'],
                    BattleRepository::SIDE_A,
                    (int) $entry['member']['id_user_ig'],
                    $team_pos
                )
            );

            if (!$first_actor)
            {
                $first_actor = animaster_party_pve_fetch_participant($conn, $pid);
            }

            animaster_pvp_set_user_flags($conn, (int) $entry['member']['id_user_ig'], true);
            $team_pos++;
        }

        $team_pos = 1;
        $first_enemy = null;

        foreach ($snaps_b as $entry)
        {
            $pid = BattleRepository::insertParticipant(
                $conn,
                $id_battle,
                BattleParticipantFactory::playerAnimal(
                    $entry['snap'],
                    BattleRepository::SIDE_B,
                    (int) $entry['member']['id_user_ig'],
                    $team_pos
                )
            );

            if (!$first_enemy)
            {
                $first_enemy = animaster_party_pve_fetch_participant($conn, $pid);
            }

            animaster_pvp_set_user_flags($conn, (int) $entry['member']['id_user_ig'], true);
            $team_pos++;
        }

        if (!$first_actor || !$first_enemy)
        {
            $conn->rollBack();

            return ['error' => 'NO_TEAM_ANIMAL'];
        }

        animaster_party_pve_insert_move(
            $conn,
            $id_battle,
            0,
            $id_challenger,
            $first_actor,
            $first_enemy,
            'start',
            0,
            'start',
            'S',
            'ongoing',
            'start',
            0,
            'start',
            0,
            1,
            $lang_suffix
        );

        $conn->commit();

        return [
            'ok' => true,
            'id_battle' => $id_battle,
            'battle_type' => 'party_vs_party',
            'current_battle_turn' => 0
        ];
    }
    catch (Exception $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        error_log('[party_vs_party] start: ' . $e->getMessage());

        return ['error' => 'START_FAILED'];
    }
}

function animaster_party_vs_party_check_end(array $participants)
{
    $alive_a = animaster_party_vs_party_alive_on_side($participants, 'A');
    $alive_b = animaster_party_vs_party_alive_on_side($participants, 'B');

    if (!$alive_b)
    {
        return ['status' => 'win_a', 'winner' => 'A'];
    }

    if (!$alive_a)
    {
        return ['status' => 'win_b', 'winner' => 'B'];
    }

    return ['status' => 'ongoing', 'winner' => null];
}

function animaster_party_vs_party_finish_battle($conn, array $battle, $end_reason, $winner_alliance)
{
    $id_battle = (int) $battle['id_battle'];

    BattleRepository::finishBattle($conn, $id_battle, (string) $end_reason, $winner_alliance);

    CombatSession::onBattleEnd($conn, CombatSession::TYPE_PARTY_VS_PARTY, $id_battle);

    if (!class_exists('BUFFS'))
    {
        require_once __DIR__ . '/buffs.php';
    }

    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);

    foreach ($participants as $p)
    {
        $id_user = (int) ($p['id_user_ig'] ?? 0);

        if ($id_user > 0)
        {
            animaster_pvp_set_user_flags($conn, $id_user, false);
        }

        $id_animal = (int) ($p['id_animal'] ?? 0);

        if ($id_animal > 0)
        {
            BUFFS::persistAnimalHpAfterBattle($conn, $id_animal, (int) $p['current_hp']);
        }
    }
}

function animaster_party_vs_party_resolve_round($conn, array $battle, $round, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle'];
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $fighters_by_user = [];

    foreach ($participants as $p)
    {
        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
        {
            continue;
        }

        if (trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0)
        {
            continue;
        }

        $fighters_by_user[(int) $p['id_user_ig']] = $p;
    }

    $choices = animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round);
    $confirmed_by_user = [];

    foreach ($choices as $choice)
    {
        if (trim((string) $choice['flg_confirmed']) !== 'Y')
        {
            continue;
        }

        $uid = (int) $choice['id_user_ig'];

        if (!isset($fighters_by_user[$uid]))
        {
            continue;
        }

        $confirmed_by_user[$uid] = $choice;
    }

    if (!$confirmed_by_user)
    {
        return ['error' => 'NO_CONFIRMED_CHOICES'];
    }

    $slots = TurnQueue::buildPartyVsPartyExecutionSlots($confirmed_by_user, $fighters_by_user);
    $order_in_turn = 0;
    $battle_status = 'ongoing';
    $end_info = ['status' => 'ongoing', 'winner' => null];

    foreach ($slots as $slot)
    {
        if ($battle_status !== 'ongoing')
        {
            break;
        }

        $id_user_ig = (int) $slot['id_user_ig'];
        $actor = $fighters_by_user[$id_user_ig] ?? null;

        if (!$actor || trim((string) $actor['flg_fainted']) === 'S' || (int) $actor['current_hp'] <= 0)
        {
            continue;
        }

        $actor_side = (string) $actor['side'];
        $enemy_side = $actor_side === 'A' ? 'B' : 'A';
        $enemies = [];

        foreach ($fighters_by_user as $fp)
        {
            if ((string) $fp['side'] === $enemy_side)
            {
                $enemies[] = $fp;
            }
        }

        $choice = $slot['choice'];
        $action_type = (string) $choice['action_type'];
        $target = animaster_party_vs_party_pick_enemy_target($enemies);

        if ($action_type === 'ability')
        {
            if (!$target)
            {
                continue;
            }

            $result = animaster_party_pve_apply_ability_choice(
                $conn, $actor, $target, (int) $choice['action_id'], $id_user_ig, $lang_suffix, $id_battle, $round
            );

            if (!empty($result['ok']))
            {
                $result['target'] = $result['wild'];
                unset($result['wild']);
            }
        }
        else if ($action_type === 'switch')
        {
            $result = animaster_party_pve_apply_switch_choice($conn, $actor, (int) $choice['action_id'], $id_user_ig, $lang_suffix);

            if (!empty($result['ok']))
            {
                $result['target'] = $target ?: $actor;
            }
        }
        else if ($action_type === 'item')
        {
            $result = animaster_party_pve_apply_item_choice(
                $conn, $actor, $id_user_ig, (int) $choice['id_item_type_selected'], (int) $choice['action_id'], $lang_suffix
            );

            if (!empty($result['ok']))
            {
                $result['target'] = $target ?: $actor;
            }
        }
        else if ($action_type === 'flee')
        {
            $result = [
                'ok' => true,
                'actor' => $actor,
                'target' => $target ?: $actor,
                'move_type' => 'flee',
                'id_rif' => 4,
                'move_description' => 'flee',
                'move_hit' => 'S',
                'protagonist_type' => 'animal',
                'id_protagonist' => (int) $actor['id_animal'],
                'target_type' => 'animal',
                'id_target' => $target ? animaster_party_pve_participant_public_id($target) : 0
            ];
            $battle_status = 'fled';
            $end_info = ['status' => 'fled', 'winner' => null];
        }
        else
        {
            continue;
        }

        if (empty($result['ok']))
        {
            continue;
        }

        if (isset($result['actor']))
        {
            $fighters_by_user[$id_user_ig] = $result['actor'];
            $actor = $result['actor'];
        }

        if (isset($result['target']) && (int) ($result['target']['id_user_ig'] ?? 0) > 0)
        {
            $fighters_by_user[(int) $result['target']['id_user_ig']] = $result['target'];
        }

        $order_in_turn++;
        $move_target = $result['target'] ?? ($target ?: $actor);

        if ($battle_status === 'fled')
        {
            animaster_party_vs_party_finish_battle($conn, $battle, 'fled', null);
        }
        else
        {
            $participants_now = array_values($fighters_by_user);
            // Re-merge with inactive/fainted from DB for accurate end check
            $all = animaster_party_pve_fetch_participants($conn, $id_battle);
            $by_id = [];

            foreach ($all as $row)
            {
                $by_id[(int) $row['id_battle_participant']] = $row;
            }

            foreach ($fighters_by_user as $fp)
            {
                $by_id[(int) $fp['id_battle_participant']] = $fp;
            }

            $end_info = animaster_party_vs_party_check_end(array_values($by_id));

            if ($end_info['status'] !== 'ongoing')
            {
                $battle_status = $end_info['status'];
                animaster_party_vs_party_finish_battle($conn, $battle, $end_info['status'], $end_info['winner']);
            }
        }

        animaster_party_pve_insert_move(
            $conn,
            $id_battle,
            $round,
            $id_user_ig,
            $actor,
            $move_target,
            $result['move_type'],
            $result['id_rif'],
            $result['move_description'],
            $result['move_hit'],
            $battle_status === 'ongoing' ? 'ongoing' : $battle_status,
            $result['protagonist_type'],
            $result['id_protagonist'],
            $result['target_type'],
            $result['id_target'],
            $order_in_turn,
            $lang_suffix
        );
    }

    CombatSession::completePartyConfirmRound(
        $conn,
        $id_battle,
        $round,
        CombatSession::TYPE_PARTY_VS_PARTY,
        function ($resolvedRound) use ($conn, $id_battle)
        {
            animaster_party_pve_clear_turn_choices($conn, $id_battle, $resolvedRound);
        }
    );

    return ['ok' => true, 'round' => $round, 'status' => $battle_status];
}

function animaster_party_vs_party_build_meta($conn, array $battle, $id_user_ig, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle'];
    $my_side = animaster_party_vs_party_user_in_battle($conn, $id_battle, $id_user_ig);

    if (!$my_side)
    {
        $my_side = 'A';
    }

    $enemy_side = $my_side === 'A' ? 'B' : 'A';
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $battle_open = trim((string) $battle['flg_status']) === 'O';
    $round = (int) $battle['current_turn'] + 1;

    $alive_mine = animaster_party_vs_party_alive_on_side($participants, $my_side);
    $alive_user_ids = [];

    foreach ($alive_mine as $p)
    {
        $alive_user_ids[] = (int) $p['id_user_ig'];
    }

    $confirm_required = count($alive_mine);
    $choices = $battle_open ? animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round) : [];
    $choice_by_user = [];

    foreach ($choices as $choice)
    {
        $choice_by_user[(int) $choice['id_user_ig']] = $choice;
    }

    $confirm_done = 0;

    foreach ($alive_user_ids as $uid)
    {
        $c = $choice_by_user[$uid] ?? null;

        if ($c && trim((string) $c['flg_confirmed']) === 'Y')
        {
            $confirm_done++;
        }
    }

    $my_choice = $choice_by_user[(int) $id_user_ig] ?? null;
    $is_eligible = in_array((int) $id_user_ig, $alive_user_ids, true);

    $leader_id = $my_side === 'A'
        ? (int) ($battle['id_user_ig_leader_a'] ?? 0)
        : (int) ($battle['id_user_ig_leader_b'] ?? 0);

    $allies = [];

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== $my_side)
        {
            continue;
        }

        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
        {
            continue;
        }

        $display_name = 'Player';

        if ($p['id_user_ig'])
        {
            $user = animaster_party_fetch_user($conn, (int) $p['id_user_ig']);
            $display_name = $user ? (string) ($user['display_name'] ?: 'Player') : 'Player';
        }

        $ally_id = (int) $p['id_user_ig'];
        $ally_choice = $choice_by_user[$ally_id] ?? null;

        $allies[] = [
            'id_user_ig' => $ally_id,
            'display_name' => $display_name,
            'id_animal' => animaster_party_pve_participant_public_id($p),
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0,
            'staged' => $ally_choice !== null,
            'confirmed' => $ally_choice !== null && trim((string) $ally_choice['flg_confirmed']) === 'Y',
            'action_type' => $ally_choice ? (string) $ally_choice['action_type'] : '',
            'action_label' => $ally_choice ? animaster_party_pve_describe_choice($conn, $ally_choice, $lang_suffix) : '',
            'is_votable' => false,
            'vote_yes' => 0,
            'vote_no' => 0,
            'my_vote' => null,
            'can_vote' => false
        ];
    }

    $wild_combatants = [];
    $first_enemy_hp = 0;
    $first_enemy_max = 0;

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== $enemy_side)
        {
            continue;
        }

        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
        {
            continue;
        }

        $entry = [
            'id_animal' => animaster_party_pve_participant_public_id($p),
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0
        ];
        $wild_combatants[] = $entry;

        if ($first_enemy_max === 0)
        {
            $first_enemy_hp = $entry['hp'];
            $first_enemy_max = $entry['max_hp'];
        }
    }

    $winner_alliance = $battle['id_winner_alliance'] ?? null;
    $viewer_result = null;

    if (!$battle_open && $winner_alliance)
    {
        $viewer_result = ((string) $winner_alliance === (string) $my_side) ? 'win' : 'defeat';
    }
    else if (!$battle_open && (string) ($battle['end_reason'] ?? '') === 'fled')
    {
        $viewer_result = 'fled';
    }

    return CombatSession::attachCombatants(
        [
            'battle_type' => CombatSession::TYPE_PARTY_VS_PARTY,
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
            'wild_hp' => $first_enemy_hp,
            'wild_max_hp' => $first_enemy_max,
            'battle_finished' => !$battle_open,
            'is_leader' => $leader_id === (int) $id_user_ig,
            'allow_inactivity_vote' => false,
            'inactivity_vote_delay_seconds' => 0,
            'seconds_since_round_start' => $battle_open ? animaster_party_pve_seconds_since_round_start($conn, $battle) : 0,
            'my_side' => $my_side,
            'winner_alliance' => $winner_alliance,
            'viewer_result' => $viewer_result
        ],
        CombatSession::combatantsFromPartyParticipants($participants),
        $conn,
        [
            'battle_type' => CombatSession::TYPE_PARTY_VS_PARTY,
            'id_battle' => $id_battle,
            'lang' => $lang_suffix,
        ]
    );
}

function animaster_party_vs_party_try_resolve($conn, array $battle, $round, $lang_suffix)
{
    $id_battle = (int) $battle['id_battle'];
    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $alive_a = animaster_party_vs_party_alive_on_side($participants, 'A');
    $alive_b = animaster_party_vs_party_alive_on_side($participants, 'B');

    $confirmed_a = animaster_party_vs_party_count_confirmed_on_side($conn, $id_battle, $round, $alive_a);
    $confirmed_b = animaster_party_vs_party_count_confirmed_on_side($conn, $id_battle, $round, $alive_b);

    $leader_a = animaster_party_pve_user_turn_choice(
        $conn, $id_battle, $round, (int) ($battle['id_user_ig_leader_a'] ?? 0)
    );
    $leader_b = animaster_party_pve_user_turn_choice(
        $conn, $id_battle, $round, (int) ($battle['id_user_ig_leader_b'] ?? 0)
    );

    if (!Permissions::partyVsPartyShouldResolveRound(
        $confirmed_a,
        count($alive_a),
        $confirmed_b,
        count($alive_b),
        $leader_a,
        $leader_b
    ))
    {
        return null;
    }

    return animaster_party_vs_party_resolve_round($conn, $battle, $round, $lang_suffix);
}

function animaster_party_vs_party_handle_turn_request(
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
    $battle = animaster_party_vs_party_fetch_battle($conn, (int) $id_battle);
    $my_side = animaster_party_vs_party_user_in_battle($conn, (int) $id_battle, (int) $id_user_ig);

    if (!$battle || !$my_side)
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

        $round = CombatSession::planningRoundFromBattle($battle);

        if (!CombatSession::isPlanningRoundInSync($turn, (int) $battle['current_turn']))
        {
            return ['error' => 'TURN_OUT_OF_SYNC'];
        }

        $participants = animaster_party_pve_fetch_participants($conn, (int) $id_battle);
        $alive_mine = animaster_party_vs_party_alive_on_side($participants, $my_side);
        $alive_user_ids = [];

        foreach ($alive_mine as $p)
        {
            $alive_user_ids[] = (int) $p['id_user_ig'];
        }

        if (!in_array((int) $id_user_ig, $alive_user_ids, true))
        {
            return ['error' => 'NOT_ELIGIBLE'];
        }

        $leader_id = $my_side === 'A'
            ? (int) ($battle['id_user_ig_leader_a'] ?? 0)
            : (int) ($battle['id_user_ig_leader_b'] ?? 0);

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
            if ($leader_id !== (int) $id_user_ig)
            {
                return ['error' => 'NOT_LEADER'];
            }

            animaster_party_vs_party_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'flee', 0);
        }
        else if ($type === 'ability')
        {
            $validated = animaster_party_pve_validate_ability_choice($conn, (int) $id_action, $lang_suffix);

            if (!empty($validated['error']))
            {
                return $validated;
            }

            animaster_party_vs_party_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'ability', (int) $id_action);
        }
        else if ($type === 'switch')
        {
            $actor = null;

            foreach ($alive_mine as $p)
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

            animaster_party_vs_party_save_turn_choice($conn, $id_battle, $round, $id_user_ig, 'switch', (int) $id_action);
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

            animaster_party_vs_party_save_turn_choice(
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

        $resolve_result = animaster_party_vs_party_try_resolve($conn, $battle, $round, $lang_suffix);

        if ($resolve_result && !empty($resolve_result['error']))
        {
            return $resolve_result;
        }
    }

    $battle = animaster_party_vs_party_fetch_battle($conn, (int) $id_battle);
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
