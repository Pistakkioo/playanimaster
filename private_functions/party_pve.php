<?php

/**
 * Party PvE mode on the unified combat schema (docs/modules/005c_full_combat_unification.md).
 * Battle row lives in `battles` (battle_type = party_pve, planning_mode = simultaneous_confirm);
 * fighters in `battle_participants` (side A = party, side B = wild); planning in
 * `battle_round_choices`; append-only log in `battle_moves`; votes in `battle_inactivity_votes`.
 *
 * External surface kept stable for callers: animaster_party_pve_start(),
 * animaster_party_pve_handle_turn_request(), animaster_party_pve_build_meta(),
 * animaster_party_pve_active_for_user(), animaster_party_pve_handle_member_departed().
 */

if (!defined('ANIMASTER_PARTY_PVE_JOIN_RADIUS'))
{
    define('ANIMASTER_PARTY_PVE_JOIN_RADIUS', 50);
}

require_once __DIR__ . '/party.php';
require_once __DIR__ . '/combat/CombatSession.php';
require_once __DIR__ . '/combat/TurnQueue.php';
require_once __DIR__ . '/combat/Permissions.php';
require_once __DIR__ . '/combat/AiWild.php';
require_once __DIR__ . '/combat/BattleRepository.php';
require_once __DIR__ . '/combat/BattleParticipantFactory.php';

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

/**
 * Display id for a participant regardless of side: player animals expose
 * id_animal, wilds expose id_wild_animal (both mirrored on id_entity).
 *
 * @param array<string, mixed> $p
 */
function animaster_party_pve_participant_public_id(array $p)
{
    if (!empty($p['id_animal']))
    {
        return (int) $p['id_animal'];
    }

    if (!empty($p['id_wild_animal']))
    {
        return (int) $p['id_wild_animal'];
    }

    return (int) ($p['id_entity'] ?? 0);
}

function animaster_party_pve_fetch_participants($conn, $id_battle)
{
    return BattleRepository::fetchParticipants($conn, (int) $id_battle);
}

function animaster_party_pve_fetch_participant($conn, $id_participant)
{
    return BattleRepository::fetchParticipant($conn, (int) $id_participant);
}

function animaster_party_pve_get_wild_participant($conn, $id_battle)
{
    $rows = BattleRepository::fetchParticipantsBySide($conn, (int) $id_battle, BattleRepository::SIDE_B);

    return $rows ? $rows[0] : null;
}

function animaster_party_pve_side_a_participant_id($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT id_battle_participant
        FROM battle_participants
        WHERE id_battle = :id_battle
          AND side = \'A\'
          AND id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([
        ':id_battle' => (int) $id_battle,
        ':id_user_ig' => (int) $id_user_ig
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * Aliased battle row: exposes legacy keys (id_battle_party_pve, current_turn,
 * id_party, id_wild_animal, id_user_ig_leader) on top of the unified `battles`
 * row so the rest of this file reads the same as before the 005c port.
 */
function animaster_party_pve_fetch_battle($conn, $id_battle)
{
    $stmt = $conn->prepare('
        SELECT b.*,
               b.id_battle AS id_battle_party_pve,
               b.current_round AS current_turn,
               b.id_party_a AS id_party,
               wp.id_wild_animal AS id_wild_animal,
               p.id_user_ig_leader AS id_user_ig_leader
        FROM battles b
        LEFT JOIN battle_participants wp
            ON wp.id_battle = b.id_battle AND wp.side = \'B\'
        LEFT JOIN parties p
            ON p.id_party = b.id_party_a
        WHERE b.id_battle = :id_battle
          AND b.battle_type = \'party_pve\'
        LIMIT 1
    ');
    $stmt->execute([':id_battle' => (int) $id_battle]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function animaster_party_pve_user_in_battle($conn, $id_battle, $id_user_ig)
{
    $stmt = $conn->prepare('
        SELECT 1
        FROM battle_participants
        WHERE id_battle = :id_battle
          AND id_user_ig = :id_user_ig
          AND side = \'A\'
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
        SELECT b.id_battle, b.current_round
        FROM battles b
        INNER JOIN battle_participants p
            ON p.id_battle = b.id_battle
           AND p.id_user_ig = :id_user_ig
           AND p.side = \'A\'
        WHERE b.battle_type = \'party_pve\'
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
        'battle_type' => 'party_pve',
        'current_battle_turn' => (int) $row['current_round']
    ];
}

function animaster_party_pve_party_has_active_battle($conn, $id_party)
{
    $stmt = $conn->prepare('
        SELECT id_battle
        FROM battles
        WHERE battle_type = \'party_pve\'
          AND id_party_a = :id_party
          AND flg_status = \'O\'
        LIMIT 1
    ');
    $stmt->execute([':id_party' => (int) $id_party]);

    return (int) $stmt->fetchColumn();
}
/**
 * Call this whenever a user leaves, is kicked from, or is otherwise removed
 * from their party. If they have an in-progress party PvE battle, mark their
 * participant slot inactive so the round stops waiting on their confirmation,
 * then immediately re-check whether the round (or the whole battle) can now
 * resolve without them.
 */
function animaster_party_pve_handle_member_departed($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;
    $active = animaster_party_pve_active_for_user($conn, $id_user_ig);

    if (!$active)
    {
        return;
    }

    $id_battle = (int) $active['id_battle'];

    $stmt = $conn->prepare('
        UPDATE battle_participants
        SET flg_active = \'N\'
        WHERE id_battle = :id_battle
          AND id_user_ig = :id_user_ig
          AND side = \'A\'
    ');
    $stmt->execute([
        ':id_battle' => $id_battle,
        ':id_user_ig' => $id_user_ig
    ]);

    $battle = animaster_party_pve_fetch_battle($conn, $id_battle);

    if (!$battle || trim((string) $battle['flg_status']) !== 'O')
    {
        return;
    }

    $participants = animaster_party_pve_fetch_participants($conn, $id_battle);
    $alive_party = animaster_party_pve_alive_party_participants($participants);

    if (!$alive_party)
    {
        // Whole party is gone (left/kicked/fainted): nobody left to resolve
        // the round, so close the battle out rather than leave it dangling.
        $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);

        if ($wild)
        {
            animaster_party_pve_finish_battle(
                $conn,
                $battle,
                'defeat',
                (int) $battle['id_wild_animal'],
                $participants,
                $wild,
                ''
            );
        }

        return;
    }

    $round = CombatSession::planningRoundFromBattle($battle);
    $confirmed_count = animaster_party_pve_count_confirmed_turn_choices($conn, $id_battle, $round);

    if (Permissions::partyPveShouldResolveRound($confirmed_count, count($alive_party), null))
    {
        animaster_party_pve_resolve_round($conn, $battle, $round, '');
    }
}

/**
 * Party members eligible to plan/act this round: alive (not fainted, HP > 0)
 * animals on side A, still marked active.
 *
 * @param array<int, array<string, mixed>> $participants
 * @return array<int, array<string, mixed>>
 */
function animaster_party_pve_alive_party_participants(array $participants)
{
    $alive = [];

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== 'A')
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

function animaster_party_pve_party_alive_count(array $participants)
{
    $count = 0;

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== 'A')
        {
            continue;
        }

        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
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
/* ---------------------------------------------------------------------
 * Turn choices (staging table): each alive party member stages one action
 * for the upcoming round, then confirms it.
 * ------------------------------------------------------------------- */

function animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_user_ig, $action_type, $action_id, $id_item_type_selected = 0)
{
    $previous = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig);

    $changed = $previous
        && ((string) $previous['action_type'] !== (string) $action_type
            || (int) $previous['action_id'] !== (int) $action_id
            || (int) $previous['id_item_type_selected'] !== (int) $id_item_type_selected);

    $id_participant = animaster_party_pve_side_a_participant_id($conn, $id_battle, $id_user_ig);

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

    if (Permissions::stagedChangeInvalidatesOthers(Permissions::MODE_PARTY, (bool) $previous, $changed))
    {
        animaster_party_pve_unconfirm_others($conn, $id_battle, $round, $id_user_ig);
    }

    if (!$previous)
    {
        animaster_party_pve_clear_inactivity_votes($conn, $id_battle, $round, $id_user_ig);
    }
}

function animaster_party_pve_unconfirm_others($conn, $id_battle, $round, $id_user_ig)
{
    BattleRepository::unconfirmOthers($conn, (int) $id_battle, (int) $round, (int) $id_user_ig);
}

function animaster_party_pve_inactivity_vote_delay_seconds()
{
    if (defined('party_pve_inactivity_vote_delay_seconds'))
    {
        return (int) constant('party_pve_inactivity_vote_delay_seconds');
    }

    return 45;
}

function animaster_party_pve_party_allows_inactivity_vote($conn, array $battle)
{
    $party = animaster_party_fetch_party_row($conn, (int) $battle['id_party']);

    return $party && trim((string) ($party['flg_allow_inactivity_vote'] ?? 'N')) === 'S';
}

/**
 * Seconds elapsed since the current round started, computed by MySQL itself
 * so it's immune to PHP/DB timezone mismatch.
 */
function animaster_party_pve_seconds_since_round_start($conn, array $battle)
{
    $stmt = $conn->prepare('SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, :dt, NOW())) AS secs');
    $stmt->execute([':dt' => (string) ($battle['dt_round_started'] ?? date('Y-m-d H:i:s'))]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['secs'] : 0;
}

function animaster_party_pve_fetch_inactivity_votes($conn, $id_battle, $round, $id_target = null)
{
    return BattleRepository::fetchInactivityVotes($conn, (int) $id_battle, (int) $round, $id_target === null ? null : (int) $id_target);
}

function animaster_party_pve_cast_inactivity_vote($conn, $id_battle, $round, $id_target, $id_voter, $choice)
{
    BattleRepository::castInactivityVote($conn, (int) $id_battle, (int) $round, (int) $id_target, (int) $id_voter, $choice);
}

function animaster_party_pve_clear_inactivity_votes($conn, $id_battle, $round, $id_target)
{
    BattleRepository::clearInactivityVotes($conn, (int) $id_battle, (int) $round, (int) $id_target);
}

/**
 * Eligible voters for a given target: alive+active party members, other than
 * the target, who have already staged their own action this round.
 */
function animaster_party_pve_inactivity_vote_eligible_voters(array $alive_party, $id_target, array $choice_by_user)
{
    $eligible = [];

    foreach ($alive_party as $p)
    {
        $uid = (int) $p['id_user_ig'];

        if ($uid === (int) $id_target)
        {
            continue;
        }

        if (!isset($choice_by_user[$uid]))
        {
            continue;
        }

        $eligible[] = $uid;
    }

    return $eligible;
}

/**
 * Tallies a target's vote once every currently-eligible voter has cast one.
 * Majority wins; on a tie the party leader's own vote decides if they are an
 * eligible voter, otherwise the vote fails.
 */
function animaster_party_pve_tally_inactivity_votes($conn, array $battle, $round, $id_target, array $alive_party, array $choice_by_user)
{
    $eligible_voters = animaster_party_pve_inactivity_vote_eligible_voters($alive_party, $id_target, $choice_by_user);

    if (!$eligible_voters)
    {
        return ['decided' => false];
    }

    $votes = animaster_party_pve_fetch_inactivity_votes($conn, (int) $battle['id_battle_party_pve'], $round, $id_target);
    $vote_by_voter = [];

    foreach ($votes as $v)
    {
        $vote_by_voter[(int) $v['id_user_ig_voter']] = trim((string) $v['vote_choice']) === 'N' ? 'N' : 'Y';
    }

    foreach ($eligible_voters as $uid)
    {
        if (!isset($vote_by_voter[$uid]))
        {
            return ['decided' => false];
        }
    }

    $yes = 0;
    $no = 0;

    foreach ($eligible_voters as $uid)
    {
        if ($vote_by_voter[$uid] === 'Y')
        {
            $yes++;
        }
        else
        {
            $no++;
        }
    }

    if ($yes > $no)
    {
        return ['decided' => true, 'result' => 'Y'];
    }

    if ($no > $yes)
    {
        return ['decided' => true, 'result' => 'N'];
    }

    $id_leader = (int) $battle['id_user_ig_leader'];

    if (isset($vote_by_voter[$id_leader]))
    {
        return ['decided' => true, 'result' => $vote_by_voter[$id_leader]];
    }

    return ['decided' => true, 'result' => 'N'];
}

/**
 * Forces a random valid ability (target's own species/level pool) as the
 * target's action for the round, auto-confirmed.
 */
function animaster_party_pve_apply_forced_inactivity_action($conn, $id_battle, $round, $id_target, array $participants, $lang_suffix)
{
    $actor = null;

    foreach ($participants as $p)
    {
        if ((string) $p['side'] === 'A' && (int) $p['id_user_ig'] === (int) $id_target)
        {
            $actor = $p;
            break;
        }
    }

    if (!$actor)
    {
        return;
    }

    $ability = animaster_party_pve_fetch_random_wild_ability($conn, (int) $actor['id_species'], (int) $actor['lvl'], $lang_suffix);

    if (!$ability)
    {
        return;
    }

    animaster_party_pve_save_turn_choice($conn, $id_battle, $round, $id_target, 'ability', (int) $ability['id_ability']);
    animaster_party_pve_set_turn_choice_confirmed($conn, $id_battle, $round, $id_target, true);
}

function animaster_party_pve_set_turn_choice_confirmed($conn, $id_battle, $round, $id_user_ig, $confirmed)
{
    return BattleRepository::setChoiceConfirmed($conn, (int) $id_battle, (int) $round, (int) $id_user_ig, (bool) $confirmed);
}

function animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig)
{
    return BattleRepository::fetchChoiceByUser($conn, (int) $id_battle, (int) $round, (int) $id_user_ig);
}

function animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round)
{
    return BattleRepository::fetchChoices($conn, (int) $id_battle, (int) $round);
}

function animaster_party_pve_count_confirmed_turn_choices($conn, $id_battle, $round)
{
    return BattleRepository::countConfirmedChoices($conn, (int) $id_battle, (int) $round);
}

function animaster_party_pve_clear_turn_choices($conn, $id_battle, $round)
{
    BattleRepository::clearChoices($conn, (int) $id_battle, (int) $round);
}
function animaster_party_pve_save_participant($conn, array $participant)
{
    $flg_fainted = (int) $participant['current_hp'] <= 0 ? 'S' : 'N';

    BattleRepository::updateParticipant($conn, (int) $participant['id_battle_participant'], [
        'current_hp' => (int) $participant['current_hp'],
        'max_hp' => (int) $participant['max_hp'],
        'atk' => (int) $participant['atk'],
        'def' => (int) $participant['def'],
        'matk' => (int) $participant['matk'],
        'mdef' => (int) $participant['mdef'],
        'acc' => (int) $participant['acc'],
        'eva' => (int) $participant['eva'],
        'cr' => (int) $participant['cr'],
        'spd' => (int) $participant['spd'],
        'flg_fainted' => $flg_fainted
    ]);

    if ((string) $participant['side'] === 'A' && !empty($participant['id_user_ig']))
    {
        if (!class_exists('BUFFS'))
        {
            require_once __DIR__ . '/buffs.php';
        }

        BUFFS::persistAnimalHpAfterBattle(
            $conn,
            (int) $participant['id_animal'],
            (int) $participant['current_hp']
        );
    }
}

/**
 * Element name + color for the combat log (cached per request), stored on the
 * move meta so the client log renders without a per-read JOIN.
 */
function animaster_party_pve_element_info($conn, $id_element, $lang_suffix)
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
    $stmt->execute([':id' => $id_element]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $info = [
        'element' => $row ? (string) $row['element'] : '',
        'color' => $row ? (string) $row['color'] : ''
    ];
    $cache[$key] = $info;

    return $info;
}

/**
 * Flat p_a_* / w_a_* display block for a move meta blob (mirrors the legacy
 * move-row columns the client already parses).
 *
 * @param array<string, mixed> $p Participant row
 */
function animaster_party_pve_participant_to_move_stats($conn, array $p, $prefix, $lang_suffix)
{
    $element = animaster_party_pve_element_info($conn, (int) $p['id_element'], $lang_suffix);

    return [
        $prefix . '_id' => animaster_party_pve_participant_public_id($p),
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
        $prefix . '_res_spd' => (float) $p['spd'],
        $prefix . '_element' => $element['element'],
        $prefix . '_element_color' => $element['color']
    ];
}

/**
 * Append one battle_moves row. The client display block (p_a_ / w_a_ + element
 * names + protagonist/target) is packed into meta_json.
 */
function animaster_party_pve_insert_move(
    $conn,
    $id_battle,
    $round,
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
    $order_in_turn,
    $lang_suffix
)
{
    $p = animaster_party_pve_participant_to_move_stats($conn, $party_actor, 'p_a', $lang_suffix);
    $w = animaster_party_pve_participant_to_move_stats($conn, $wild, 'w_a', $lang_suffix);

    $meta = array_merge($p, $w, [
        'protagonist_type' => (string) $protagonist_type,
        'id_protagonist' => (int) $id_protagonist,
        'target_type' => (string) $target_type,
        'id_target' => (int) $id_target
    ]);

    $id_actor_participant = (int) ($party_actor['id_battle_participant'] ?? 0);
    $id_target_participant = (int) ($wild['id_battle_participant'] ?? 0);

    if ($id_actor_participant <= 0)
    {
        $id_actor_participant = $id_target_participant;
    }

    return BattleRepository::insertMove($conn, [
        'id_battle' => (int) $id_battle,
        'round' => (int) $round,
        'order_in_turn' => (int) $order_in_turn,
        'id_actor_participant' => $id_actor_participant,
        'id_target_participant' => $id_target_participant > 0 ? $id_target_participant : null,
        'id_user_ig_actor' => $id_user_ig_actor ? (int) $id_user_ig_actor : null,
        'move_type' => (string) $move_type,
        'id_rif' => (int) $id_rif,
        'move_speed' => (float) $party_actor['spd'],
        'move_description' => (string) $move_description,
        'move_hit' => (string) $move_hit,
        'actor_hp_after' => (int) $party_actor['current_hp'],
        'target_hp_after' => (int) $wild['current_hp'],
        'resulting_battle_status' => (string) $battle_status,
        'meta' => $meta
    ]);
}

/**
 * Rehydrate move rows into the flat legacy shape the client log expects.
 */
function animaster_party_pve_fetch_moves_through_turn($conn, $id_battle, $through_turn, $lang_suffix)
{
    $rows = BattleRepository::fetchMovesThroughRound($conn, (int) $id_battle, (int) $through_turn);
    $out = [];

    foreach ($rows as $row)
    {
        $meta = !empty($row['meta_json']) ? json_decode($row['meta_json'], true) : [];
        $flat = array_merge($row, is_array($meta) ? $meta : []);
        $flat['turn'] = (int) $row['round'];
        $flat['id_battle_party_pve'] = (int) $row['id_battle'];
        $flat['id_battle_party_pve_move'] = (int) $row['id_battle_move'];
        $out[] = $flat;
    }

    return $out;
}

function animaster_party_pve_max_turn($conn, $id_battle)
{
    return BattleRepository::maxRound($conn, (int) $id_battle);
}
function animaster_party_pve_check_end($conn, array $battle, array $participants, array $wild)
{
    if (trim((string) $battle['flg_status']) !== 'O')
    {
        return $battle['end_reason'] ?: 'ongoing';
    }

    if ((int) $wild['current_hp'] <= 0)
    {
        // 'win' matches the status string solo PvE uses (combat.js statusMessage()).
        return 'win';
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

    $winner = null;

    if ($status === 'win')
    {
        $winner = 'A';
    }
    else if ($status === 'defeat')
    {
        $winner = 'B';
    }

    BattleRepository::finishBattle($conn, $id_battle, $status, $winner);

    if (!class_exists('CombatSession'))
    {
        require_once __DIR__ . '/combat/CombatSession.php';
    }

    CombatSession::onBattleEnd($conn, CombatSession::TYPE_PARTY, $id_battle);

    $stmt = $conn->prepare('
        UPDATE wild_animals
        SET id_battle = 0, battle_type = NULL, dt_modifica = NOW()
        WHERE id_wild_animal = :id_wild
    ');
    $stmt->execute([':id_wild' => (int) $id_wild_animal]);

    if ($status === 'win')
    {
        if (!class_exists('FUNZIONI'))
        {
            require_once __DIR__ . '/f.php';
        }

        // Reward every member who started and stayed (flg_active = 'S'),
        // including fainted ones; split evenly so a full party kill nets the
        // same total as a solo kill. Members who left (flg_active = 'N') are
        // excluded.
        $reward_recipients = array_values(array_filter($party_participants, function ($p)
        {
            return (string) $p['side'] === 'A'
                && (int) $p['id_user_ig'] > 0
                && trim((string) ($p['flg_active'] ?? 'S')) === 'S';
        }));

        $party_size = max(1, count($reward_recipients));
        $reward_multiplier = 1.0 / $party_size;

        foreach ($reward_recipients as $p)
        {
            FUNZIONI::AddExpFromWildAnimal(
                $conn,
                (int) $p['id_user_ig'],
                (int) $p['id_animal'],
                (int) $wild['id_species'],
                (int) $wild['lvl'],
                $lang_suffix,
                $reward_multiplier
            );

            FUNZIONI::AddDropsWildAnimalUser(
                $conn,
                (int) $wild['id_species'],
                (int) $wild['lvl'],
                (int) $p['id_user_ig'],
                $lang_suffix,
                $reward_multiplier,
                (int) $wild['id_element']
            );

            if (!class_exists('QUESTS'))
            {
                require_once __DIR__ . '/quests.php';
            }

            QUESTS::onWildDefeated($conn, (int) $p['id_user_ig'], (int) $wild['id_species'], $lang_suffix);
        }

        $stmt = $conn->prepare('DELETE FROM wild_animals WHERE id_wild_animal = :id_wild');
        $stmt->execute([':id_wild' => (int) $id_wild_animal]);
    }
}

function animaster_party_pve_pick_wild_target(array $party_participants)
{
    return AiWild::pickRandomPartyTarget($party_participants);
}

/**
 * Deprecated — buffs are granted via MoveResolver + ability_effects.
 *
 * @param array<string, mixed> $ability
 * @param array<string, mixed> $attacker
 * @param array<string, mixed> $defender
 */
function animaster_party_pve_apply_ability_stat_effect(array $ability, array &$attacker, array &$defender)
{
}

function animaster_party_pve_participant_to_fighter(array $participant)
{
    return [
        'lvl' => (int) ($participant['lvl'] ?? 0),
        'acc' => (int) ($participant['acc'] ?? 0),
        'cr' => (int) ($participant['cr'] ?? 0),
        'atk' => (float) ($participant['atk'] ?? 0),
        'def' => (float) ($participant['def'] ?? 0),
        'matk' => (float) ($participant['matk'] ?? 0),
        'mdef' => (float) ($participant['mdef'] ?? 0),
        'eva' => (int) ($participant['eva'] ?? 0),
        'spd' => (float) ($participant['spd'] ?? 0),
        'current_hp' => (int) ($participant['current_hp'] ?? 0),
        'max_hp' => (int) ($participant['max_hp'] ?? 0),
        'id_element' => (int) ($participant['id_element'] ?? 0),
        'nickname' => (string) ($participant['nickname'] ?: $participant['species_name'] ?? ''),
        'id_battle_participant' => (int) ($participant['id_battle_participant'] ?? 0),
        'id_animal' => animaster_party_pve_participant_public_id($participant),
        'side' => (string) ($participant['side'] ?? 'A'),
        'id_user_ig' => $participant['id_user_ig'] ?? null
    ];
}

function animaster_party_pve_participant_entity(array $participant)
{
    $side = (string) ($participant['side'] ?? 'A');

    return [
        'entity_type' => (string) ($participant['entity_type'] ?? ($side === 'B' ? 'wild' : 'animal')),
        'id_entity' => (int) ($participant['id_entity'] ?? animaster_party_pve_participant_public_id($participant)),
        'id_user_ig' => $side === 'A' ? (int) ($participant['id_user_ig'] ?? 0) : null
    ];
}

function animaster_party_pve_apply_ability_damage(
    $conn,
    array $ability,
    array $attacker,
    array $defender,
    $attacker_is_wild,
    $lang_suffix,
    $id_battle,
    $applied_at_turn
)
{
    if (!class_exists('MoveResolver'))
    {
        require_once __DIR__ . '/combat/MoveResolver.php';
    }

    $attackerFighter = animaster_party_pve_participant_to_fighter($attacker);
    $defenderFighter = animaster_party_pve_participant_to_fighter($defender);

    $result = MoveResolver::resolveAbility($ability, $attackerFighter, $defenderFighter, [
        'lang_suffix' => (string) $lang_suffix,
        'conn' => $conn,
        'battle_type' => CombatSession::TYPE_PARTY,
        'id_battle' => (int) $id_battle,
        'applied_at_turn' => (int) $applied_at_turn,
        'attacker_entity' => animaster_party_pve_participant_entity($attacker),
        'defender_entity' => animaster_party_pve_participant_entity($defender)
    ]);

    $attackerOut = array_merge($attacker, [
        'current_hp' => (int) $result['attacker']['current_hp'],
        'max_hp' => (int) ($result['attacker']['max_hp'] ?? $attacker['max_hp'] ?? 0)
    ]);
    $defenderOut = array_merge($defender, [
        'current_hp' => (int) $result['defender']['current_hp'],
        'max_hp' => (int) ($result['defender']['max_hp'] ?? $defender['max_hp'] ?? 0)
    ]);

    return [
        'attacker' => $attackerOut,
        'defender' => $defenderOut,
        'move_hit' => $result['move_hit'],
        'move_description' => $result['move_description'],
        'id_ability' => $result['id_ability']
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
    return AiWild::pickRandomAbility($conn, $id_species, $lvl, $lang_suffix);
}

function animaster_party_pve_validate_ability_choice($conn, $id_ability, $lang_suffix)
{
    $ability = animaster_party_pve_fetch_ability($conn, $id_ability, $lang_suffix);

    if (!$ability)
    {
        return ['error' => 'INVALID_ABILITY'];
    }

    return ['ok' => true, 'ability' => $ability];
}

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
function animaster_party_pve_apply_ability_choice($conn, array $actor, array $wild, $id_ability, $id_user_ig, $lang_suffix, $id_battle, $round)
{
    $validated = animaster_party_pve_validate_ability_choice($conn, $id_ability, $lang_suffix);

    if (!empty($validated['error']))
    {
        return $validated;
    }

    $result = animaster_party_pve_apply_ability_damage(
        $conn,
        $validated['ability'],
        $actor,
        $wild,
        false,
        $lang_suffix,
        $id_battle,
        $round
    );
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
        'id_target' => animaster_party_pve_participant_public_id($result['defender'])
    ];
}

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

    BattleRepository::updateParticipant($conn, (int) $actor['id_battle_participant'], [
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

    $new_actor = animaster_party_pve_fetch_participant($conn, (int) $actor['id_battle_participant']);
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
        $actor_participant = animaster_party_pve_fetch_participant($conn, (int) $actor['id_battle_participant']);
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
 * Wild's single action within a round (one slot per alive party member).
 * Returns null when the wild species has no unlocked ability at this level.
 */
function animaster_party_pve_apply_wild_action($conn, array $wild, array $target, $lang_suffix, $id_battle, $round)
{
    $ability = animaster_party_pve_fetch_random_wild_ability(
        $conn,
        (int) $wild['id_species'],
        (int) $wild['lvl'],
        $lang_suffix
    );

    if (!$ability)
    {
        return null;
    }

    $result = animaster_party_pve_apply_ability_damage(
        $conn,
        $ability,
        $wild,
        $target,
        true,
        $lang_suffix,
        $id_battle,
        $round
    );

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
        'id_protagonist' => animaster_party_pve_participant_public_id($result['attacker']),
        'target_type' => 'animal',
        'id_target' => (int) $result['defender']['id_animal']
    ];
}

/**
 * Leader's flee choice: ends the battle the moment it is processed in speed
 * order, regardless of lower-speed actions still queued this round.
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
        'id_target' => animaster_party_pve_participant_public_id($wild)
    ];
}
/**
 * Resolve a fully-confirmed (or leader-flee-triggered) round: build one
 * speed-ordered queue of every confirmed party choice plus one wild action per
 * alive party member, execute slot by slot recording one battle_moves row per
 * slot (shared round, incrementing order_in_turn) until the battle ends.
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

    $slots = TurnQueue::buildPartyPveExecutionSlots(
        $confirmed_by_user,
        $party_by_user,
        $wild,
        count($alive_party)
    );

    $order_in_turn = 0;
    $battle_status = 'ongoing';

    foreach ($slots as $slot)
    {
        if ($battle_status !== 'ongoing')
        {
            break;
        }

        if ($slot['kind'] === TurnQueue::SLOT_PARTY)
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
                $result = animaster_party_pve_apply_ability_choice($conn, $actor, $wild, (int) $choice['action_id'], $id_user_ig, $lang_suffix, $id_battle, $round);
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
                    animaster_party_pve_fetch_participants($conn, $id_battle),
                    $wild,
                    $lang_suffix
                );
            }
            else
            {
                $battle_status = animaster_party_pve_check_end($conn, $battle, array_values($party_by_user), $wild);

                if ($battle_status === 'win')
                {
                    animaster_party_pve_finish_battle(
                        $conn,
                        $battle,
                        'win',
                        (int) $battle['id_wild_animal'],
                        animaster_party_pve_fetch_participants($conn, $id_battle),
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
                $order_in_turn,
                $lang_suffix
            );
        }
        else
        {
            $target = animaster_party_pve_pick_wild_target(array_values($party_by_user));

            if (!$target)
            {
                continue;
            }

            $result = animaster_party_pve_apply_wild_action($conn, $wild, $target, $lang_suffix, $id_battle, $round);

            if (!$result)
            {
                continue;
            }

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
                    animaster_party_pve_fetch_participants($conn, $id_battle),
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
                $order_in_turn,
                $lang_suffix
            );
        }
    }

    CombatSession::completePartyPveRound(
        $conn,
        $id_battle,
        $round,
        function ($resolvedRound) use ($conn, $id_battle)
        {
            animaster_party_pve_clear_turn_choices($conn, $id_battle, $resolvedRound);
        }
    );

    return ['ok' => true, 'round' => $round, 'status' => $battle_status];
}
/**
 * Human-readable label for a staged choice (ability/animal/item name).
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

    $allow_inactivity_vote = $battle_open && animaster_party_pve_party_allows_inactivity_vote($conn, $battle);
    $inactivity_vote_delay_seconds = animaster_party_pve_inactivity_vote_delay_seconds();
    $seconds_since_round_start = $battle_open ? animaster_party_pve_seconds_since_round_start($conn, $battle) : 0;

    $votes_by_target = [];

    if ($allow_inactivity_vote)
    {
        foreach (animaster_party_pve_fetch_inactivity_votes($conn, $id_battle, $round) as $vote_row)
        {
            $target_id = (int) $vote_row['id_user_ig_target'];

            if (!isset($votes_by_target[$target_id]))
            {
                $votes_by_target[$target_id] = [];
            }

            $votes_by_target[$target_id][(int) $vote_row['id_user_ig_voter']] = trim((string) $vote_row['vote_choice']) === 'N' ? 'N' : 'Y';
        }
    }

    $allies = [];

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== 'A')
        {
            continue;
        }

        if (trim((string) ($p['flg_active'] ?? 'S')) !== 'S')
        {
            continue;
        }

        $display_name = '';

        if ($p['id_user_ig'])
        {
            $user = animaster_party_fetch_user($conn, (int) $p['id_user_ig']);
            $display_name = $user ? (string) ($user['display_name'] ?: 'Player') : 'Player';
        }

        $ally_id = (int) $p['id_user_ig'];
        $ally_choice = $choice_by_user[$ally_id] ?? null;
        $ally_fainted = trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0;

        $is_votable = false;
        $vote_yes = 0;
        $vote_no = 0;
        $my_vote = null;
        $can_vote = false;

        if ($allow_inactivity_vote && !$ally_fainted && $ally_choice === null && $ally_id !== (int) $id_user_ig)
        {
            $is_votable = $seconds_since_round_start >= $inactivity_vote_delay_seconds;

            $target_votes = $votes_by_target[$ally_id] ?? [];

            foreach ($target_votes as $choice_val)
            {
                if ($choice_val === 'Y')
                {
                    $vote_yes++;
                }
                else
                {
                    $vote_no++;
                }
            }

            $my_vote = $target_votes[(int) $id_user_ig] ?? null;
            $can_vote = $is_votable && $my_choice !== null;
        }

        $allies[] = [
            'id_user_ig' => $ally_id,
            'display_name' => $display_name,
            'id_animal' => (int) $p['id_animal'],
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => $ally_fainted,
            'is_self' => $ally_id === (int) $id_user_ig,
            'has_choice' => $ally_choice !== null,
            'confirmed' => $ally_choice !== null && trim((string) $ally_choice['flg_confirmed']) === 'Y',
            'action_type' => $ally_choice ? (string) $ally_choice['action_type'] : '',
            'action_label' => $ally_choice ? animaster_party_pve_describe_choice($conn, $ally_choice, $lang_suffix) : '',
            'is_votable' => $is_votable,
            'vote_yes' => $vote_yes,
            'vote_no' => $vote_no,
            'my_vote' => $my_vote,
            'can_vote' => $can_vote
        ];
    }

    $wild = animaster_party_pve_get_wild_participant($conn, $id_battle);
    $wild_combatants = [];

    foreach ($participants as $p)
    {
        if ((string) $p['side'] !== 'B')
        {
            continue;
        }

        $wild_combatants[] = [
            'id_animal' => animaster_party_pve_participant_public_id($p),
            'nickname' => (string) $p['nickname'],
            'species' => (string) $p['species_name'],
            'lvl' => (int) $p['lvl'],
            'hp' => (int) $p['current_hp'],
            'max_hp' => (int) $p['max_hp'],
            'id_element' => (int) $p['id_element'],
            'fainted' => trim((string) $p['flg_fainted']) === 'S' || (int) $p['current_hp'] <= 0
        ];
    }

    return CombatSession::attachCombatants(
        [
            'battle_type' => CombatSession::TYPE_PARTY,
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
            'is_leader' => (int) $battle['id_user_ig_leader'] === (int) $id_user_ig,
            'allow_inactivity_vote' => $allow_inactivity_vote,
            'inactivity_vote_delay_seconds' => $inactivity_vote_delay_seconds,
            'seconds_since_round_start' => $seconds_since_round_start,
        ],
        CombatSession::combatantsFromPartyParticipants($participants),
        $conn,
        [
            'battle_type' => CombatSession::TYPE_PARTY,
            'id_battle' => $id_battle,
            'lang' => $lang_suffix,
        ]
    );
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

        $id_battle = BattleRepository::createBattle($conn, [
            'battle_type' => 'party_pve',
            'planning_mode' => 'simultaneous_confirm',
            'id_zone' => $id_zone,
            'id_user_ig_initiator' => $id_user_ig,
            'id_party_a' => $id_party
        ]);

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

        BattleRepository::insertParticipant(
            $conn,
            $id_battle,
            BattleParticipantFactory::wild($wild_snap, BattleRepository::SIDE_B, null)
        );

        $team_pos = 1;
        $first_party_actor = null;

        foreach ($party_snaps as $entry)
        {
            $member = $entry['member'];
            $snap = $entry['snap'];

            $participant_id = BattleRepository::insertParticipant(
                $conn,
                $id_battle,
                BattleParticipantFactory::playerAnimal($snap, BattleRepository::SIDE_A, (int) $member['id_user_ig'], $team_pos)
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
            0,
            1,
            $lang_suffix
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

/**
 * Handles both a party member staging/confirming/un-confirming their round
 * action, and plain polling (empty $type). Always returns the move history plus
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
    $id_item_type_selected = 0,
    $vote_choice = 'Y'
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

        $round = CombatSession::planningRoundFromBattle($battle);

        if (!CombatSession::isPlanningRoundInSync($turn, (int) $battle['current_turn']))
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
        else if ($type === 'inactivity_vote')
        {
            if (!animaster_party_pve_party_allows_inactivity_vote($conn, $battle))
            {
                return ['error' => 'INACTIVITY_VOTE_DISABLED'];
            }

            $voter_choice = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_user_ig);

            if (!$voter_choice)
            {
                return ['error' => 'MUST_ACT_FIRST'];
            }

            $id_target = (int) $id_action;

            if ($id_target === (int) $id_user_ig || !in_array($id_target, $alive_user_ids, true))
            {
                return ['error' => 'INVALID_VOTE_TARGET'];
            }

            $target_choice = animaster_party_pve_user_turn_choice($conn, $id_battle, $round, $id_target);

            if ($target_choice)
            {
                return ['error' => 'TARGET_ALREADY_ACTED'];
            }

            $delay = animaster_party_pve_inactivity_vote_delay_seconds();

            if (animaster_party_pve_seconds_since_round_start($conn, $battle) < $delay)
            {
                return ['error' => 'TOO_EARLY'];
            }

            $choices = animaster_party_pve_fetch_turn_choices($conn, $id_battle, $round);
            $choice_by_user = [];

            foreach ($choices as $choice_row)
            {
                $choice_by_user[(int) $choice_row['id_user_ig']] = $choice_row;
            }

            animaster_party_pve_cast_inactivity_vote($conn, $id_battle, $round, $id_target, $id_user_ig, $vote_choice);

            $tally = animaster_party_pve_tally_inactivity_votes($conn, $battle, $round, $id_target, $alive_party, $choice_by_user);

            if (!empty($tally['decided']))
            {
                if ($tally['result'] === 'Y')
                {
                    animaster_party_pve_apply_forced_inactivity_action($conn, $id_battle, $round, $id_target, $participants, $lang_suffix);
                }

                animaster_party_pve_clear_inactivity_votes($conn, $id_battle, $round, $id_target);
            }
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

        if (Permissions::partyPveShouldResolveRound($confirmed_count, count($alive_party), $leader_choice))
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

