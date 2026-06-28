<?php

/**
 * Dialog / quest consequence handlers.
 *
 * Register new handlers in CONSEQUENCES::handlers().
 * Optional JSON params live in consequences.params_json; id_ref, ref_table and num
 * are merged in as defaults when a key is not present in the JSON.
 */
class CONSEQUENCES
{
    /**
     * @return array<string, callable>
     */
    private static function handlers()
    {
        return [
            '[obtain item]' => [self::class, 'handleObtainItem'],
            'receive_random_animal' => [self::class, 'handleReceiveRandomAnimal'],
            '[set player_class]' => [self::class, 'handleSetPlayerClass'],
        ];
    }

    /**
     * Invoke a handler directly without a consequences row (e.g. legacy endpoints).
     *
     * @param array<string, mixed> $params
     */
    public static function ApplyHandler($conn, $handler_type, $id_user_ig, $params, $LANG)
    {
        $handlers = self::handlers();
        $handler_type = trim((string) $handler_type);

        if (!isset($handlers[$handler_type]))
        {
            error_log('[CONSEQUENCES] unknown handler: ' . $handler_type);
            return false;
        }

        $row = [
            'consequence_type' => $handler_type,
            'id_ref' => (int) ($params['id_ref'] ?? 0),
            'ref_table' => isset($params['ref_table']) ? (string) $params['ref_table'] : '',
            'num' => (int) ($params['num'] ?? 1),
            'params_json' => null,
        ];
        $merged = self::resolveParams(array_merge($row, ['params_json' => json_encode($params)]));

        return call_user_func($handlers[$handler_type], $conn, $id_user_ig, $row, $merged, $LANG);
    }

    public static function Apply($conn, $id_user_ig, $id_consequence, $LANG)
    {
        $row = self::fetchRow($conn, $id_consequence);

        if (!$row)
        {
            return false;
        }

        $type = trim((string) ($row['consequence_type'] ?? ''));

        if ($type === '')
        {
            error_log('[CONSEQUENCES] empty consequence_type for id ' . (int) $id_consequence);
            return false;
        }

        $handlers = self::handlers();

        if (!isset($handlers[$type]))
        {
            error_log('[CONSEQUENCES] unknown consequence_type: ' . $type);
            return false;
        }

        $params = self::resolveParams($row);

        return call_user_func($handlers[$type], $conn, $id_user_ig, $row, $params, $LANG);
    }

    /**
     * @return array<string, mixed>|false
     */
    private static function fetchRow($conn, $id_consequence)
    {
        $stmt = $conn->prepare('
            SELECT id_consequence, consequence_type, id_ref, ref_table, num, params_json
            FROM consequences
            WHERE id_consequence = :id_consequence
            LIMIT 1
        ');
        $stmt->execute([':id_consequence' => (int) $id_consequence]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function resolveParams($row)
    {
        $params = [
            'id_ref' => (int) ($row['id_ref'] ?? 0),
            'ref_table' => isset($row['ref_table']) ? (string) $row['ref_table'] : '',
            'num' => (int) ($row['num'] ?? 1),
        ];

        if (!empty($row['params_json']))
        {
            $decoded = json_decode((string) $row['params_json'], true);

            if (is_array($decoded))
            {
                $params = array_merge($params, $decoded);
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $params
     */
    private static function handleObtainItem($conn, $id_user_ig, $row, $params, $LANG)
    {
        $id_item_type = (int) ($params['id_item_type'] ?? $params['id_ref'] ?? 0);
        $quantity = (int) ($params['quantity'] ?? $params['num'] ?? 1);

        if ($id_item_type <= 0 || $quantity <= 0)
        {
            return false;
        }

        $stmt_insert = $conn->prepare('
            INSERT INTO items (dt_creazione, id_user_ig, id_item_type)
            VALUES (NOW(), :id_user_ig, :id_item_type)
        ');

        for ($i = 0; $i < $quantity; $i++)
        {
            $stmt_insert->execute([
                ':id_user_ig' => (int) $id_user_ig,
                ':id_item_type' => $id_item_type,
            ]);
        }

        $lang_suffix = self::normalizeLangSuffix($LANG);
        $stmt_name = $conn->prepare('
            SELECT nome' . $lang_suffix . ' AS item_name
            FROM item_types
            WHERE id_item_type = :id_item_type
            LIMIT 1
        ');
        $stmt_name->execute([':id_item_type' => $id_item_type]);
        $item_row = $stmt_name->fetch(PDO::FETCH_ASSOC);
        $item_name = $item_row ? (string) $item_row['item_name'] : 'item';

        $notification = "You obtained ($quantity) $item_name";
        if ($LANG === '_it')
        {
            $notification = "Hai trovato ($quantity) $item_name";
        }
        elseif ($LANG === '_pt')
        {
            $notification = "Encontraste ($quantity) $item_name";
        }

        $stmt_notification = $conn->prepare('
            INSERT INTO notifications
            (id_user_ig, description, item_type, id_item_type, flg_viewed, dt_c)
            VALUES
            (:id_user_ig, :description, \'item\', :id_item_type, \'N\', NOW())
        ');
        $stmt_notification->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':description' => $notification,
            ':id_item_type' => $id_item_type,
        ]);

        return true;
    }

    private static function maxTeamAnimals($params)
    {
        $max = (int) ($params['max_team_size'] ?? 5);

        if ($max < 1)
        {
            $max = 5;
        }

        if ($max > 5)
        {
            $max = 5;
        }

        return $max;
    }

    /**
     * First free team slot in 1..$max_slots (positions under 6).
     *
     * @return int 0 when the team is full
     */
    private static function findFirstFreeTeamPosition($conn, $id_user_ig, $max_slots = 5)
    {
        $stmt = $conn->prepare('
            SELECT team_position
            FROM animals
            WHERE id_user_ig = :id_user_ig
              AND team_position > 0
              AND team_position < 6
            ORDER BY team_position ASC
        ');
        $stmt->execute([':id_user_ig' => (int) $id_user_ig]);

        $used = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $pos = (int) ($row['team_position'] ?? 0);

            if ($pos > 0)
            {
                $used[$pos] = true;
            }
        }

        for ($pos = 1; $pos <= $max_slots; $pos++)
        {
            if (empty($used[$pos]))
            {
                return $pos;
            }
        }

        return 0;
    }

    /**
     * Params:
     * - species_pool: int[] — pick a random species from this list
     * - id_species: int — fixed species (used when species_pool is absent)
     * - element_pool: int[] — pick a random element from this list
     * - id_element: int — fixed element (used when element_pool is absent)
     *
     * Legacy columns id_ref / num can supply id_species / id_element when JSON omits them.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $params
     */
    private static function handleReceiveRandomAnimal($conn, $id_user_ig, $row, $params, $LANG)
    {
        $id_species = self::resolveSpeciesId($params);
        $id_element = self::resolveElementId($params);
        $max_team_size = self::maxTeamAnimals($params);

        if (isset($params['team_position']))
        {
            $team_position = (int) $params['team_position'];
        }
        else
        {
            $team_position = self::findFirstFreeTeamPosition($conn, $id_user_ig, $max_team_size);
        }

        if ($id_species <= 0 || $id_element <= 0)
        {
            error_log('[CONSEQUENCES] receive_random_animal missing id_species or id_element');
            return false;
        }

        if ($team_position <= 0 || $team_position >= 6)
        {
            $notification = 'You have reached the limit of 5 animals!';
            if ($LANG === '_it')
            {
                $notification = 'Hai raggiunto il limite di 5 animali!';
            }
            elseif ($LANG === '_pt')
            {
                $notification = 'Atingiste o limite de 5 animais!';
            }

            FUNZIONI::AddNotification($conn, $id_user_ig, $notification, 'lvl_down');
            return true;
        }

        $stmt_taken = $conn->prepare('
            SELECT id_animal
            FROM animals
            WHERE id_user_ig = :id_user_ig
              AND team_position = :team_position
            LIMIT 1
        ');
        $stmt_taken->execute([
            ':id_user_ig' => (int) $id_user_ig,
            ':team_position' => $team_position,
        ]);

        if ($stmt_taken->fetch(PDO::FETCH_ASSOC))
        {
            $team_position = self::findFirstFreeTeamPosition($conn, $id_user_ig, $max_team_size);

            if ($team_position <= 0)
            {
                $notification = 'You have reached the limit of 5 animals!';
                if ($LANG === '_it')
                {
                    $notification = 'Hai raggiunto il limite di 5 animali!';
                }
                elseif ($LANG === '_pt')
                {
                    $notification = 'Atingiste o limite de 5 animais!';
                }

                FUNZIONI::AddNotification($conn, $id_user_ig, $notification, 'lvl_down');
                return true;
            }
        }

        $lang_suffix = self::normalizeLangSuffix($LANG);
        $stmt_species = $conn->prepare('
            SELECT base_hp, species' . $lang_suffix . ' AS species_name
            FROM species
            WHERE id_species = :id_species
            LIMIT 1
        ');
        $stmt_species->execute([':id_species' => $id_species]);
        $species_row = $stmt_species->fetch(PDO::FETCH_ASSOC);

        if (!$species_row)
        {
            error_log('[CONSEQUENCES] receive_random_animal unknown species id ' . $id_species);
            return false;
        }

        $base_hp = (int) $species_row['base_hp'];
        $species_name = (string) $species_row['species_name'];
        $dna_hp = 1;
        $lvl = 1;
        $animal_hp = (int) floor(0.01 * (2 * $base_hp + $dna_hp) * $lvl) + $lvl + 10;

        $stmt_insert = $conn->prepare('
            INSERT INTO animals
            (
                dt_creazione, id_species, id_user_ig, lvl, team_position,
                dna_atk, dna_def, dna_matk, dna_mdef, dna_hp, dna_acc, dna_eva, dna_cr, dna_spd,
                pt_atk, pt_def, pt_matk, pt_mdef, pt_hp, pt_acc, pt_eva, pt_cr, pt_spd,
                xp_atk, xp_def, xp_matk, xp_mdef, xp_hp, xp_acc, xp_eva, xp_cr, xp_spd,
                current_hp, nickname, id_element, max_hp, experience
            )
            VALUES
            (
                NOW(), :id_species, :id_user_ig, 1, :team_position,
                1, 1, 1, 1, 1, 1, 1, 1, 1,
                0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0,
                :current_hp, :nickname, :id_element, :max_hp, 0
            )
        ');
        $stmt_insert->execute([
            ':id_species' => $id_species,
            ':id_user_ig' => (int) $id_user_ig,
            ':team_position' => $team_position,
            ':current_hp' => $animal_hp,
            ':nickname' => $species_name,
            ':id_element' => $id_element,
            ':max_hp' => $animal_hp,
        ]);

        $notification = 'You have received an animal!';
        if ($LANG === '_it')
        {
            $notification = 'Hai ricevuto un animale!';
        }
        elseif ($LANG === '_pt')
        {
            $notification = 'Recebeste um animal!';
        }

        FUNZIONI::AddNotification($conn, $id_user_ig, $notification, 'lvl_up');

        return true;
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolveSpeciesId($params)
    {
        $picked = self::pickRandomFromPool($params['species_pool'] ?? null);

        if ($picked > 0)
        {
            return $picked;
        }

        if (isset($params['id_species']))
        {
            return (int) $params['id_species'];
        }

        return (int) ($params['id_ref'] ?? 0);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function resolveElementId($params)
    {
        $picked = self::pickRandomFromPool($params['element_pool'] ?? null);

        if ($picked > 0)
        {
            return $picked;
        }

        if (isset($params['id_element']))
        {
            return (int) $params['id_element'];
        }

        return (int) ($params['num'] ?? 0);
    }

    /**
     * @param mixed $pool_values
     */
    private static function pickRandomFromPool($pool_values)
    {
        if (!is_array($pool_values))
        {
            return 0;
        }

        $pool = [];

        foreach ($pool_values as $pool_id)
        {
            $pool_id = (int) $pool_id;

            if ($pool_id > 0)
            {
                $pool[] = $pool_id;
            }
        }

        if (count($pool) === 0)
        {
            return 0;
        }

        return (int) $pool[array_rand($pool)];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $params
     */
    private static function handleSetPlayerClass($conn, $id_user_ig, $row, $params, $LANG)
    {
        if (!class_exists('PLAYER_CLASS'))
        {
            require_once dirname(__FILE__) . '/player_class.php';
        }

        $target_id = (int) ($params['id_player_class'] ?? $params['id_ref'] ?? 0);

        if ($target_id <= 0 && !empty($params['class_code']))
        {
            $by_code = PLAYER_CLASS::fetchByCode($conn, (string) $params['class_code']);

            if ($by_code)
            {
                $target_id = (int) $by_code['id_player_class'];
            }
        }

        if ($target_id <= 0 && !empty($row['ref_table']))
        {
            $by_code = PLAYER_CLASS::fetchByCode($conn, (string) $row['ref_table']);

            if ($by_code)
            {
                $target_id = (int) $by_code['id_player_class'];
            }
        }

        if ($target_id <= 0)
        {
            error_log('[CONSEQUENCES] set player_class missing target id');
            return false;
        }

        return PLAYER_CLASS::promoteTo($conn, $id_user_ig, $target_id, $LANG, $params);
    }

    private static function normalizeLangSuffix($LANG)
    {
        if ($LANG === '_it' || $LANG === '_pt')
        {
            return $LANG;
        }

        return '';
    }
}
