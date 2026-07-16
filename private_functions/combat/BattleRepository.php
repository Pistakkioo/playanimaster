<?php

/**
 * Data access for the unified combat schema (docs/modules/005c_full_combat_unification.md):
 * battles / battle_participants / battle_round_choices / battle_moves / battle_inactivity_votes.
 *
 * Generic across battle_type — mode files (party_pve.php, pvp.php, solo pve) call these
 * instead of touching the legacy per-mode tables (battles_solo_pve*, battles_pvp*,
 * battles_party_pve*), which are retired once every mode is ported (Phase 6).
 */
class BattleRepository
{
    const SIDE_A = 'A';
    const SIDE_B = 'B';

    /**
     * @param array<string, mixed> $fields battle_type, planning_mode, id_zone, id_user_ig_initiator,
     *   id_party_a, id_party_b, id_duel_request, context_json (optional)
     */
    public static function createBattle($conn, array $fields)
    {
        $stmt = $conn->prepare('
            INSERT INTO battles (
                battle_type, planning_mode, flg_status, current_round,
                id_zone, id_user_ig_initiator, id_party_a, id_party_b, id_duel_request,
                dt_round_started, dt_created, context_json
            ) VALUES (
                :battle_type, :planning_mode, \'O\', 0,
                :id_zone, :id_user_ig_initiator, :id_party_a, :id_party_b, :id_duel_request,
                NOW(), NOW(), :context_json
            )
        ');
        $stmt->execute([
            ':battle_type' => (string) $fields['battle_type'],
            ':planning_mode' => (string) $fields['planning_mode'],
            ':id_zone' => isset($fields['id_zone']) ? (int) $fields['id_zone'] : null,
            ':id_user_ig_initiator' => isset($fields['id_user_ig_initiator']) ? (int) $fields['id_user_ig_initiator'] : null,
            ':id_party_a' => isset($fields['id_party_a']) ? (int) $fields['id_party_a'] : null,
            ':id_party_b' => isset($fields['id_party_b']) ? (int) $fields['id_party_b'] : null,
            ':id_duel_request' => isset($fields['id_duel_request']) ? (int) $fields['id_duel_request'] : null,
            ':context_json' => isset($fields['context_json']) ? json_encode($fields['context_json']) : null,
        ]);

        return (int) $conn->lastInsertId();
    }

    public static function fetchBattleRaw($conn, $id_battle)
    {
        $stmt = $conn->prepare('SELECT * FROM battles WHERE id_battle = :id_battle LIMIT 1');
        $stmt->execute([':id_battle' => (int) $id_battle]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @param array<string, mixed> $fields column => value
     */
    public static function updateBattle($conn, $id_battle, array $fields)
    {
        if (!$fields)
        {
            return;
        }

        $sets = [];
        $params = [':id_battle' => (int) $id_battle];

        foreach ($fields as $col => $value)
        {
            $col = preg_replace('/[^a-z_]/i', '', (string) $col);
            $sets[] = $col . ' = :' . $col;
            $params[':' . $col] = $value;
        }

        $stmt = $conn->prepare('UPDATE battles SET ' . implode(', ', $sets) . ', dt_m = NOW() WHERE id_battle = :id_battle');
        $stmt->execute($params);
    }

    public static function finishBattle($conn, $id_battle, $end_reason, $winner_alliance = null)
    {
        $stmt = $conn->prepare('
            UPDATE battles
            SET flg_status = \'F\',
                end_reason = :end_reason,
                id_winner_alliance = :winner,
                dt_finished = NOW()
            WHERE id_battle = :id_battle
        ');
        $stmt->execute([
            ':end_reason' => (string) $end_reason,
            ':winner' => $winner_alliance,
            ':id_battle' => (int) $id_battle,
        ]);
    }

    /**
     * @param array<string, mixed> $row side, participant_kind, id_user_ig, id_animal, id_wild_animal,
     *   id_species, id_element, entity_type, id_entity, team_position, slot_label, flg_active,
     *   flg_fainted, current_hp, max_hp, atk, def, matk, mdef, acc, eva, cr, spd, lvl, nickname,
     *   species_name, experience
     */
    public static function insertParticipant($conn, $id_battle, array $row)
    {
        $stmt = $conn->prepare('
            INSERT INTO battle_participants (
                id_battle, side, participant_kind, id_user_ig, id_animal, id_wild_animal,
                id_species, id_element, entity_type, id_entity, team_position, slot_label,
                flg_active, flg_fainted, current_hp, max_hp,
                atk, def, matk, mdef, acc, eva, cr, spd,
                lvl, nickname, species_name, experience
            ) VALUES (
                :id_battle, :side, :participant_kind, :id_user_ig, :id_animal, :id_wild_animal,
                :id_species, :id_element, :entity_type, :id_entity, :team_position, :slot_label,
                :flg_active, :flg_fainted, :current_hp, :max_hp,
                :atk, :def, :matk, :mdef, :acc, :eva, :cr, :spd,
                :lvl, :nickname, :species_name, :experience
            )
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':side' => (string) $row['side'],
            ':participant_kind' => (string) $row['participant_kind'],
            ':id_user_ig' => isset($row['id_user_ig']) ? (int) $row['id_user_ig'] : null,
            ':id_animal' => isset($row['id_animal']) ? (int) $row['id_animal'] : null,
            ':id_wild_animal' => isset($row['id_wild_animal']) ? (int) $row['id_wild_animal'] : null,
            ':id_species' => (int) ($row['id_species'] ?? 0),
            ':id_element' => (int) ($row['id_element'] ?? 0),
            ':entity_type' => (string) $row['entity_type'],
            ':id_entity' => (int) $row['id_entity'],
            ':team_position' => isset($row['team_position']) ? (int) $row['team_position'] : null,
            ':slot_label' => $row['slot_label'] ?? null,
            ':flg_active' => (string) ($row['flg_active'] ?? 'S'),
            ':flg_fainted' => (string) ($row['flg_fainted'] ?? 'N'),
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
            ':lvl' => (int) $row['lvl'],
            ':nickname' => (string) ($row['nickname'] ?? ''),
            ':species_name' => (string) ($row['species_name'] ?? ''),
            ':experience' => (int) ($row['experience'] ?? 0),
        ]);

        return (int) $conn->lastInsertId();
    }

    public static function fetchParticipants($conn, $id_battle)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_participants
            WHERE id_battle = :id_battle
            ORDER BY side ASC, team_position ASC, id_battle_participant ASC
        ');
        $stmt->execute([':id_battle' => (int) $id_battle]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function fetchParticipant($conn, $id_battle_participant)
    {
        $stmt = $conn->prepare('SELECT * FROM battle_participants WHERE id_battle_participant = :id LIMIT 1');
        $stmt->execute([':id' => (int) $id_battle_participant]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchParticipantsBySide($conn, $id_battle, $side)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_participants
            WHERE id_battle = :id_battle
              AND side = :side
            ORDER BY team_position ASC, id_battle_participant ASC
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':side' => (string) $side,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $fields column => value (current_hp, max_hp, atk, def, ... flg_fainted)
     */
    public static function updateParticipant($conn, $id_battle_participant, array $fields)
    {
        if (!$fields)
        {
            return;
        }

        $sets = [];
        $params = [':id' => (int) $id_battle_participant];

        foreach ($fields as $col => $value)
        {
            $col = preg_replace('/[^a-z_]/i', '', (string) $col);
            $sets[] = $col . ' = :' . $col;
            $params[':' . $col] = $value;
        }

        $stmt = $conn->prepare('UPDATE battle_participants SET ' . implode(', ', $sets) . ' WHERE id_battle_participant = :id');
        $stmt->execute($params);
    }

    /* ---------------------------------------------------------------
     * Round choices
     * ------------------------------------------------------------- */

    public static function fetchChoiceByParticipant($conn, $id_battle, $round, $id_battle_participant)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_round_choices
            WHERE id_battle = :id_battle
              AND round = :round
              AND id_battle_participant = :id_participant
            LIMIT 1
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_participant' => (int) $id_battle_participant,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchChoiceByUser($conn, $id_battle, $round, $id_user_ig)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_round_choices
            WHERE id_battle = :id_battle
              AND round = :round
              AND id_user_ig = :id_user_ig
            LIMIT 1
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_user_ig' => (int) $id_user_ig,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function fetchChoices($conn, $id_battle, $round)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_round_choices
            WHERE id_battle = :id_battle
              AND round = :round
            ORDER BY id_battle_round_choice ASC
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function saveChoice($conn, $id_battle, $round, $id_user_ig, $id_battle_participant, $action_type, $action_id, $id_item_type_selected = null)
    {
        $stmt = $conn->prepare('
            INSERT INTO battle_round_choices (
                id_battle, round, id_user_ig, id_battle_participant,
                action_type, action_id, id_item_type_selected, flg_confirmed
            ) VALUES (
                :id_battle, :round, :id_user_ig, :id_participant,
                :action_type, :action_id, :id_item_type_selected, \'N\'
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
            ':id_participant' => (int) $id_battle_participant,
            ':action_type' => (string) $action_type,
            ':action_id' => (int) $action_id,
            ':id_item_type_selected' => $id_item_type_selected !== null ? (int) $id_item_type_selected : null,
            ':action_type_upd' => (string) $action_type,
            ':action_id_upd' => (int) $action_id,
            ':id_item_type_selected_upd' => $id_item_type_selected !== null ? (int) $id_item_type_selected : null,
        ]);
    }

    public static function setChoiceConfirmed($conn, $id_battle, $round, $id_user_ig, $confirmed)
    {
        $stmt = $conn->prepare('
            UPDATE battle_round_choices
            SET flg_confirmed = :flg
            WHERE id_battle = :id_battle
              AND round = :round
              AND id_user_ig = :id_user_ig
        ');
        $stmt->execute([
            ':flg' => $confirmed ? 'Y' : 'N',
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_user_ig' => (int) $id_user_ig,
        ]);

        return $stmt->rowCount() > 0;
    }

    public static function unconfirmOthers($conn, $id_battle, $round, $id_user_ig)
    {
        $stmt = $conn->prepare('
            UPDATE battle_round_choices
            SET flg_confirmed = \'N\'
            WHERE id_battle = :id_battle
              AND round = :round
              AND id_user_ig <> :id_user_ig
              AND flg_confirmed = \'Y\'
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_user_ig' => (int) $id_user_ig,
        ]);
    }

    public static function countConfirmedChoices($conn, $id_battle, $round)
    {
        $stmt = $conn->prepare('
            SELECT COUNT(*) FROM battle_round_choices
            WHERE id_battle = :id_battle AND round = :round AND flg_confirmed = \'Y\'
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function clearChoices($conn, $id_battle, $round)
    {
        $stmt = $conn->prepare('DELETE FROM battle_round_choices WHERE id_battle = :id_battle AND round = :round');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
        ]);
    }

    /* ---------------------------------------------------------------
     * Moves (append-only log; meta_json carries mode-specific display data)
     * ------------------------------------------------------------- */

    /**
     * @param array<string, mixed> $fields id_battle, round, order_in_turn, id_actor_participant,
     *   id_target_participant, id_user_ig_actor, move_type, id_rif, move_speed, move_description,
     *   move_hit, actor_hp_after, target_hp_after, resulting_battle_status, meta
     */
    public static function insertMove($conn, array $fields)
    {
        $stmt = $conn->prepare('
            INSERT INTO battle_moves (
                id_battle, round, order_in_turn, id_actor_participant, id_target_participant,
                id_user_ig_actor, move_type, id_rif, move_speed, move_description, move_hit,
                actor_hp_after, target_hp_after, resulting_battle_status, meta_json
            ) VALUES (
                :id_battle, :round, :order_in_turn, :id_actor_participant, :id_target_participant,
                :id_user_ig_actor, :move_type, :id_rif, :move_speed, :move_description, :move_hit,
                :actor_hp_after, :target_hp_after, :resulting_battle_status, :meta_json
            )
        ');
        $stmt->execute([
            ':id_battle' => (int) $fields['id_battle'],
            ':round' => (int) $fields['round'],
            ':order_in_turn' => (int) ($fields['order_in_turn'] ?? 0),
            ':id_actor_participant' => (int) $fields['id_actor_participant'],
            ':id_target_participant' => isset($fields['id_target_participant']) ? (int) $fields['id_target_participant'] : null,
            ':id_user_ig_actor' => isset($fields['id_user_ig_actor']) ? (int) $fields['id_user_ig_actor'] : null,
            ':move_type' => (string) $fields['move_type'],
            ':id_rif' => isset($fields['id_rif']) ? (int) $fields['id_rif'] : null,
            ':move_speed' => isset($fields['move_speed']) ? (float) $fields['move_speed'] : null,
            ':move_description' => (string) ($fields['move_description'] ?? ''),
            ':move_hit' => $fields['move_hit'] ?? null,
            ':actor_hp_after' => isset($fields['actor_hp_after']) ? (int) $fields['actor_hp_after'] : null,
            ':target_hp_after' => isset($fields['target_hp_after']) ? (int) $fields['target_hp_after'] : null,
            ':resulting_battle_status' => (string) ($fields['resulting_battle_status'] ?? 'ongoing'),
            ':meta_json' => isset($fields['meta']) ? json_encode($fields['meta']) : null,
        ]);

        return (int) $conn->lastInsertId();
    }

    public static function fetchMovesThroughRound($conn, $id_battle, $throughRound)
    {
        $stmt = $conn->prepare('
            SELECT *
            FROM battle_moves
            WHERE id_battle = :id_battle
              AND round <= :through_round
            ORDER BY round ASC, order_in_turn ASC, id_battle_move ASC
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':through_round' => (int) $throughRound,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function maxRound($conn, $id_battle)
    {
        $stmt = $conn->prepare('SELECT COALESCE(MAX(round), 0) FROM battle_moves WHERE id_battle = :id_battle');
        $stmt->execute([':id_battle' => (int) $id_battle]);

        return (int) $stmt->fetchColumn();
    }

    /* ---------------------------------------------------------------
     * Inactivity votes
     * ------------------------------------------------------------- */

    public static function fetchInactivityVotes($conn, $id_battle, $round, $id_target = null)
    {
        $sql = 'SELECT * FROM battle_inactivity_votes WHERE id_battle = :id_battle AND round = :round';
        $params = [
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
        ];

        if ($id_target !== null)
        {
            $sql .= ' AND id_user_ig_target = :id_target';
            $params[':id_target'] = (int) $id_target;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function castInactivityVote($conn, $id_battle, $round, $id_target, $id_voter, $choice)
    {
        $choice = $choice === 'N' ? 'N' : 'Y';

        $stmt = $conn->prepare('
            INSERT INTO battle_inactivity_votes (
                id_battle, round, id_user_ig_target, id_user_ig_voter, vote_choice
            ) VALUES (
                :id_battle, :round, :id_target, :id_voter, :choice
            )
            ON DUPLICATE KEY UPDATE vote_choice = :choice_upd
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_target' => (int) $id_target,
            ':id_voter' => (int) $id_voter,
            ':choice' => $choice,
            ':choice_upd' => $choice,
        ]);
    }

    public static function clearInactivityVotes($conn, $id_battle, $round, $id_target)
    {
        $stmt = $conn->prepare('
            DELETE FROM battle_inactivity_votes
            WHERE id_battle = :id_battle AND round = :round AND id_user_ig_target = :id_target
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':round' => (int) $round,
            ':id_target' => (int) $id_target,
        ]);
    }
}
