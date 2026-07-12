<?php

require_once __DIR__ . '/TurnQueue.php';

/**
 * Solo PvE turn orchestration — thin controller over legacy move includes.
 * Move resolution uses MoveResolver / AiWild inside the includes.
 */
class SoloPveController
{
    /**
     * @param array<string, mixed> $post Raw POST (id_user_ig, id_battle, turn, type, id, lang, …)
     * @return array{stato: string, msg: string, response: string}
     */
    public static function handleRequest($conn, array $post)
    {
        $id_user_ig = isset($post['id_user_ig']) ? (int) $post['id_user_ig'] : 0;
        $id_battle = isset($post['id_battle']) ? (int) $post['id_battle'] : 0;
        $turn = isset($post['turn']) ? (int) $post['turn'] : 0;
        $restarting_old_battle = ($post['restarting_old_battle'] ?? 'N') === 'S';
        $lang = isset($post['lang']) ? (string) $post['lang'] : '_it';

        if ($id_user_ig <= 0 || $id_battle <= 0)
        {
            return self::errorResponse('INVALID_BATTLE_REQUEST');
        }

        if (!self::battleBelongsToUser($conn, $id_user_ig, $id_battle))
        {
            return self::errorResponse('BATTLE_NOT_FOUND');
        }

        if ($turn > 0 && !$restarting_old_battle)
        {
            $actionError = self::processTurn($conn, $post, $id_user_ig, $id_battle, $turn, $lang);

            if ($actionError !== null)
            {
                return self::errorResponse($actionError);
            }
        }

        $moves = self::fetchTurnMoves($conn, $id_battle, $turn, $lang, $restarting_old_battle);

        if ($moves === null)
        {
            return self::errorResponse('KO');
        }

        if ($moves === [])
        {
            return self::errorResponse('NO_BATTLE_MOVES');
        }

        return [
            'stato' => 'OK',
            'msg' => 'OK',
            'response' => self::encodeMoves($moves),
            'solo_pve_meta' => json_encode(self::buildSoloMeta($conn, $id_battle, $moves, $lang, $id_user_ig), JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $moveRows
     * @return array<string, mixed>
     */
    private static function buildSoloMeta($conn, $id_battle, array $moveRows, $lang, $id_user_ig)
    {
        if (!class_exists('CombatSession'))
        {
            require_once __DIR__ . '/CombatSession.php';
        }

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
                'lang' => (string) $lang,
            ]
        );
    }

    /**
     * @return array{stato: string, msg: string, response: string}
     */
    private static function errorResponse($msg)
    {
        return [
            'stato' => 'KO',
            'msg' => (string) $msg,
            'response' => '',
        ];
    }

    private static function battleBelongsToUser($conn, $id_user_ig, $id_battle)
    {
        $stmt = $conn->prepare('
            SELECT id_battle_solo_pve
            FROM battles_solo_pve
            WHERE id_battle_solo_pve = :id_battle
              AND id_user_ig = :id_user_ig
            LIMIT 1
        ');
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':id_user_ig' => (int) $id_user_ig,
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return string|null Error code, or null on success.
     */
    private static function processTurn($conn, array $post, $id_user_ig, $id_battle, $turn, $lang)
    {
        $type = isset($post['type']) ? (string) $post['type'] : '';
        $id = isset($post['id']) ? (int) $post['id'] : 0;
        $previous_turn = $turn - 1;

        $row_last_move = self::fetchLastMoveOfTurn($conn, $id_battle, $previous_turn, $lang);

        if (!$row_last_move)
        {
            return 'NO_PREVIOUS_TURN';
        }

        $costanti = self::fetchCostanti($conn);
        $lvl_up_constant_animal = $costanti['lvl_up_constant_animal'];
        $lvl_up_constant_player = $costanti['lvl_up_constant_player'];
        $exp_loss_percent_on_death = $costanti['exp_loss_percent_on_death'];

        $b_status = 'ongoing';

        $w_a_id = $row_last_move['w_a_id'];
        $w_a_id_element = $row_last_move['w_a_id_element'];
        $w_a_id_species = $row_last_move['w_a_id_species'];
        $w_a_species = $row_last_move['w_a_species'];
        $w_a_lvl = $row_last_move['w_a_lvl'];
        $w_a_nickname = $row_last_move['w_a_nickname'];

        $p_a_id = $row_last_move['p_a_id'];
        $p_a_id_element = $row_last_move['p_a_id_element'];
        $p_a_id_species = $row_last_move['p_a_id_species'];
        $p_a_species = $row_last_move['p_a_species'];
        $p_a_lvl = $row_last_move['p_a_lvl'];
        $p_a_nickname = $row_last_move['p_a_nickname'];
        $p_a_cur_exp = $row_last_move['p_a_cur_exp'];

        $w_a_res_atk = (float) $row_last_move['w_a_res_atk'];
        $w_a_res_def = (float) $row_last_move['w_a_res_def'];
        $w_a_res_matk = (float) $row_last_move['w_a_res_matk'];
        $w_a_res_mdef = (float) $row_last_move['w_a_res_mdef'];
        $w_a_res_hp = (int) $row_last_move['w_a_res_hp'];
        $w_a_res_acc = (int) $row_last_move['w_a_res_acc'];
        $w_a_res_eva = (int) $row_last_move['w_a_res_eva'];
        $w_a_res_cr = (int) $row_last_move['w_a_res_cr'];
        $w_a_res_spd = (float) $row_last_move['w_a_res_spd'];
        $w_a_prev_spd = $w_a_res_spd;
        $w_a_res_max_hp = (int) $row_last_move['w_a_res_max_hp'];

        $p_a_res_atk = (float) $row_last_move['p_a_res_atk'];
        $p_a_res_def = (float) $row_last_move['p_a_res_def'];
        $p_a_res_matk = (float) $row_last_move['p_a_res_matk'];
        $p_a_res_mdef = (float) $row_last_move['p_a_res_mdef'];
        $p_a_res_hp = (int) $row_last_move['p_a_res_hp'];
        $p_a_res_acc = (int) $row_last_move['p_a_res_acc'];
        $p_a_res_eva = (int) $row_last_move['p_a_res_eva'];
        $p_a_res_cr = (int) $row_last_move['p_a_res_cr'];
        $p_a_res_spd = (float) $row_last_move['p_a_res_spd'];
        $p_a_prev_spd = $p_a_res_spd;
        $p_a_res_max_hp = (int) $row_last_move['p_a_res_max_hp'];

        $LANG = $lang;
        $order = TurnQueue::orderSoloTurnSlots($p_a_res_spd, $w_a_res_spd, $type);
        $p_order_in_turn = $order[0] === 'player' ? 1 : 2;
        $w_order_in_turn = $order[0] === 'wild' ? 1 : 2;

        $includeDir = self::includeDir();

        foreach ($order as $slot)
        {
            if ($b_status !== 'ongoing')
            {
                break;
            }

            if ($slot === 'wild')
            {
                if ($w_a_res_hp > 0)
                {
                    include $includeDir . '/w_a_move.php';
                }

                continue;
            }

            if ($p_a_res_hp <= 0)
            {
                continue;
            }

            if ($type === 'action' && $id === 4)
            {
                include $includeDir . '/p_move_escape.php';
            }
            else if ($type === 'switch')
            {
                include $includeDir . '/p_move_switch.php';
            }
            else if ($type === 'use_on')
            {
                include $includeDir . '/p_move_use_item.php';
            }
            else if ($type === 'ability')
            {
                include $includeDir . '/p_a_move.php';
            }
        }

        if (!class_exists('CombatSession'))
        {
            require_once __DIR__ . '/CombatSession.php';
        }

        CombatSession::tickRoundBuffs($conn, CombatSession::TYPE_SOLO, $id_battle);

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchLastMoveOfTurn($conn, $id_battle, $turn, $lang)
    {
        $lang = preg_replace('/[^_a-z]/i', '', (string) $lang);
        $sql = '
            SELECT id_battle_solo_pve_move, id_battle_solo_pve, turn, move_type, id_rif, move_speed,
                   protagonist_type, id_protagonist, target_type, id_target,
                   w_a_res_atk, w_a_res_def, w_a_res_matk, w_a_res_mdef, w_a_res_hp,
                   w_a_res_acc, w_a_res_eva, w_a_res_cr, w_a_res_spd, w_a_res_max_hp,
                   p_a_res_atk, p_a_res_def, p_a_res_matk, p_a_res_mdef, p_a_res_hp,
                   p_a_res_acc, p_a_res_eva, p_a_res_cr, p_a_res_spd, p_a_res_max_hp,
                   w_a_id, w_a_id_element, w_a_id_species, WL.species' . $lang . ' AS w_a_species,
                   w_a_lvl, WL.species' . $lang . ' AS w_a_nickname,
                   p_a_id, p_a_id_element, p_a_id_species, PL.species' . $lang . ' AS p_a_species,
                   p_a_lvl, p_a_nickname, p_a_cur_exp,
                   move_description, move_hit, resulting_battle_status
            FROM battles_solo_pve_moves M
            LEFT JOIN species WL ON WL.id_species = M.w_a_id_species
            LEFT JOIN species PL ON PL.id_species = M.p_a_id_species
            WHERE id_battle_solo_pve = :id_battle
              AND turn = :turn
            ORDER BY order_in_turn DESC
            LIMIT 1
        ';
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':turn' => (int) $turn,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>|null null on query failure
     */
    private static function fetchTurnMoves($conn, $id_battle, &$turn, $lang, $restarting_old_battle)
    {
        $lang = preg_replace('/[^_a-z]/i', '', (string) $lang);
        $rows = self::queryTurnMoves($conn, $id_battle, $turn, $lang);

        if ($rows === null)
        {
            return null;
        }

        if ($restarting_old_battle && $rows === [])
        {
            $stmt = $conn->prepare('
                SELECT MAX(turn) AS max_turn
                FROM battles_solo_pve_moves
                WHERE id_battle_solo_pve = :id_battle
            ');
            $stmt->execute([':id_battle' => (int) $id_battle]);
            $max_row = $stmt->fetch(PDO::FETCH_ASSOC);
            $fallback_turn = $max_row ? (int) $max_row['max_turn'] : -1;

            if ($fallback_turn >= 0 && $fallback_turn !== $turn)
            {
                $turn = $fallback_turn;
                $rows = self::queryTurnMoves($conn, $id_battle, $turn, $lang);

                if ($rows === null)
                {
                    return null;
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private static function queryTurnMoves($conn, $id_battle, $turn, $lang)
    {
        $sql = '
            SELECT id_battle_solo_pve_move, id_battle_solo_pve, turn, move_type, id_rif, move_speed,
                   protagonist_type, id_protagonist, target_type, id_target,
                   w_a_res_atk, w_a_res_def, w_a_res_matk, w_a_res_mdef, w_a_res_hp,
                   w_a_res_acc, w_a_res_eva, w_a_res_cr, w_a_res_spd, w_a_res_max_hp,
                   p_a_res_atk, p_a_res_def, p_a_res_matk, p_a_res_mdef, p_a_res_hp,
                   p_a_res_acc, p_a_res_eva, p_a_res_cr, p_a_res_spd, p_a_res_max_hp,
                   w_a_id, w_a_id_element, w_a_id_species, WL.species' . $lang . ' AS w_a_species,
                   w_a_lvl, WL.species' . $lang . ' AS w_a_nickname,
                   p_a_id, p_a_id_element, p_a_id_species, PL.species' . $lang . ' AS p_a_species,
                   p_a_lvl, p_a_nickname, p_a_cur_exp,
                   move_description, move_hit, resulting_battle_status,
                   WE.element' . $lang . ' AS w_a_element, PE.element' . $lang . ' AS p_a_element,
                   WE.color AS w_a_element_color, PE.color AS p_a_element_color
            FROM battles_solo_pve_moves M
            LEFT JOIN elements WE ON WE.id_element = M.w_a_id_element
            LEFT JOIN elements PE ON PE.id_element = M.p_a_id_element
            LEFT JOIN species WL ON WL.id_species = M.w_a_id_species
            LEFT JOIN species PL ON PL.id_species = M.p_a_id_species
            WHERE id_battle_solo_pve = :id_battle
              AND turn = :turn
            ORDER BY move_speed DESC
        ';
        $stmt = $conn->prepare($sql);

        if (!$stmt)
        {
            return null;
        }

        $stmt->execute([
            ':id_battle' => (int) $id_battle,
            ':turn' => (int) $turn,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private static function encodeMoves(array $rows)
    {
        $parts = [];

        foreach ($rows as $row)
        {
            $parts[] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        return implode('#', $parts);
    }

    /**
     * @return array<string, int>
     */
    private static function fetchCostanti($conn)
    {
        $values = [
            'lvl_up_constant_animal' => 41,
            'lvl_up_constant_player' => 81,
            'exp_loss_percent_on_death' => 5,
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

    private static function includeDir()
    {
        return dirname(__DIR__, 2) . '/public_html/funzioni/battle_solo_pve';
    }
}
