<?php

require_once __DIR__ . '/dev_npc_content.php';

function dev_species_effect_presets()
{
    $presets = [
        ['value' => 'none', 'label' => 'none — no stat effect'],
    ];

    $directions = [
        'lower' => 'Lower',
        'increase' => 'Raise',
    ];
    $targets = [
        'target' => 'opponent',
        'self' => 'self',
    ];
    $stats = [
        'atk' => 'ATK',
        'def' => 'DEF',
        'matk' => 'MATK',
        'mdef' => 'MDEF',
        'acc' => 'ACC',
        'eva' => 'EVA',
        'spd' => 'SPD',
    ];
    $amounts = [10, 20, 30];

    foreach ($directions as $dir_key => $dir_label)
    {
        foreach ($targets as $tgt_key => $tgt_label)
        {
            foreach ($stats as $stat_key => $stat_label)
            {
                foreach ($amounts as $amount)
                {
                    $value = $dir_key . '_' . $tgt_key . '_' . $stat_key . '_' . $amount . '_%';
                    $presets[] = [
                        'value' => $value,
                        'label' => $dir_label . ' ' . $tgt_label . ' ' . $stat_label . ' ' . $amount . '%',
                    ];
                }
            }
        }
    }

    return $presets;
}

function dev_species_effect_is_preset($effect)
{
    foreach (dev_species_effect_presets() as $preset)
    {
        if ($preset['value'] === $effect)
        {
            return true;
        }
    }

    return false;
}

function dev_species_fetch_elements(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_element, element
        FROM elements
        ORDER BY id_element
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_species_fetch_classes(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_class, class
        FROM classes
        ORDER BY id_class
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_species_fetch_abilities(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_ability, ability, descrizione, accuracy, power, m_power, effect, effect_chance, id_element,
               ability_it, descrizione_it, ability_pt, descrizione_pt
        FROM abilities
        ORDER BY id_ability
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_species_fetch_quests(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_quest, quest
        FROM quests
        ORDER BY id_quest
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int, array{value: string, label: string}>
 */
function dev_species_wild_drop_types()
{
    return [
        ['value' => 'item', 'label' => 'item'],
        ['value' => 'gold', 'label' => 'gold'],
    ];
}

function dev_species_wild_drop_label(array $row, array $item_types_by_id = [])
{
    $parts = ['#' . (int) $row['id_wild_animal_drop_type'], (string) $row['drop_type']];

    if ($row['drop_type'] === 'item' && (int) $row['id_item_type'] > 0)
    {
        $id_item = (int) $row['id_item_type'];

        if (isset($item_types_by_id[$id_item]))
        {
            $parts[] = $item_types_by_id[$id_item];
        }
        else
        {
            $parts[] = 'item #' . $id_item;
        }
    }

    $parts[] = 'lvl ' . (int) $row['lvl_min'] . '–' . (int) $row['lvl_max'];
    $parts[] = 'qty ' . (int) $row['qt_min'] . '–' . (int) $row['qt_max'];
    $parts[] = (int) $row['chance'] . '%';

    if ((int) $row['id_quest_required'] > 0)
    {
        $parts[] = 'quest #' . (int) $row['id_quest_required'];
    }

    return implode(' · ', $parts);
}

function dev_species_fetch_tree(PDO $conn)
{
    $tree = [];

    $stmt_species = $conn->query('
        SELECT *
        FROM species
        ORDER BY id_species ASC
    ');

    if (!$stmt_species)
    {
        return [];
    }

    while ($row = $stmt_species->fetch(PDO::FETCH_ASSOC))
    {
        $id_species = (int) $row['id_species'];
        $tree[$id_species] = $row;
        $tree[$id_species]['species_abilities'] = [];
        $tree[$id_species]['wild_drops'] = [];
    }

    if (!$tree)
    {
        return [];
    }

    $stmt_sa = $conn->query('
        SELECT SA.id_species_ability, SA.id_species, SA.id_ability, SA.unlock_lvl, SA.id_element,
               A.ability, A.accuracy, A.power, A.m_power, A.effect, A.effect_chance,
               E.element
        FROM species_abilities SA
        JOIN abilities A ON A.id_ability = SA.id_ability
        LEFT JOIN elements E ON E.id_element = SA.id_element
        ORDER BY SA.id_species ASC, SA.unlock_lvl ASC, SA.id_species_ability ASC
    ');

    if ($stmt_sa)
    {
        while ($row = $stmt_sa->fetch(PDO::FETCH_ASSOC))
        {
            $id_species = (int) $row['id_species'];

            if (isset($tree[$id_species]))
            {
                $tree[$id_species]['species_abilities'][] = $row;
            }
        }
    }

    $stmt_drops = $conn->query('
        SELECT D.*, IT.nome AS item_name, IT.item_type
        FROM wild_animal_drop_types D
        LEFT JOIN item_types IT ON IT.id_item_type = D.id_item_type
        ORDER BY D.id_species ASC, D.lvl_min ASC, D.id_wild_animal_drop_type ASC
    ');

    if ($stmt_drops)
    {
        while ($row = $stmt_drops->fetch(PDO::FETCH_ASSOC))
        {
            $id_species = (int) $row['id_species'];

            if (isset($tree[$id_species]))
            {
                $tree[$id_species]['wild_drops'][] = $row;
            }
        }
    }

    return $tree;
}

function dev_species_resolve_effect(array $post)
{
    $mode = dev_npc_post_str($post, 'effect_mode', 20);

    if ($mode === 'custom')
    {
        return dev_npc_post_str($post, 'effect_custom', 100) ?: 'none';
    }

    $effect = dev_npc_post_str($post, 'effect', 100);

    return $effect !== '' ? $effect : 'none';
}

function dev_species_handle_post(PDO $conn, array $post)
{
    $action = dev_npc_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'add_species':
                $stmt = $conn->prepare('
                    INSERT INTO species
                    (
                        dt_creazione, species, id_class, tier,
                        base_hp, base_atk, base_def, base_matk, base_mdef, base_spd, base_acc, base_eva, base_cr,
                        reward_exp, flg_attivo, species_it, species_pt
                    )
                    VALUES
                    (
                        NOW(), :species, :id_class, :tier,
                        :base_hp, :base_atk, :base_def, :base_matk, :base_mdef, :base_spd, :base_acc, :base_eva, :base_cr,
                        :reward_exp, :flg_attivo, :species_it, :species_pt
                    )
                ');
                $stmt->execute([
                    ':species' => dev_npc_post_str($post, 'species', 100),
                    ':id_class' => dev_npc_post_int($post, 'id_class'),
                    ':tier' => (float) str_replace(',', '.', dev_npc_post_str($post, 'tier', 10) ?: '1'),
                    ':base_hp' => dev_npc_post_int($post, 'base_hp', 60),
                    ':base_atk' => dev_npc_post_int($post, 'base_atk', 50),
                    ':base_def' => dev_npc_post_int($post, 'base_def', 50),
                    ':base_matk' => dev_npc_post_int($post, 'base_matk', 50),
                    ':base_mdef' => dev_npc_post_int($post, 'base_mdef', 50),
                    ':base_spd' => dev_npc_post_int($post, 'base_spd', 50),
                    ':base_acc' => dev_npc_post_int($post, 'base_acc', 100),
                    ':base_eva' => dev_npc_post_int($post, 'base_eva', 5),
                    ':base_cr' => dev_npc_post_int($post, 'base_cr', 10),
                    ':reward_exp' => dev_npc_post_int($post, 'reward_exp', 46),
                    ':flg_attivo' => dev_npc_post_yn($post, 'flg_attivo', 'S'),
                    ':species_it' => dev_npc_post_str($post, 'species_it', 100),
                    ':species_pt' => dev_npc_post_str($post, 'species_pt', 100),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Species created (id ' . $conn->lastInsertId() . ').',
                    'redirect' => dev_admin_page_url('dev_species.php'),
                ];

            case 'update_species':
                $id_species = dev_npc_post_int($post, 'id_species');
                $stmt = $conn->prepare('
                    UPDATE species
                    SET species = :species,
                        id_class = :id_class,
                        tier = :tier,
                        base_hp = :base_hp,
                        base_atk = :base_atk,
                        base_def = :base_def,
                        base_matk = :base_matk,
                        base_mdef = :base_mdef,
                        base_spd = :base_spd,
                        base_acc = :base_acc,
                        base_eva = :base_eva,
                        base_cr = :base_cr,
                        reward_exp = :reward_exp,
                        flg_attivo = :flg_attivo,
                        species_it = :species_it,
                        species_pt = :species_pt
                    WHERE id_species = :id_species
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_species' => $id_species,
                    ':species' => dev_npc_post_str($post, 'species', 100),
                    ':id_class' => dev_npc_post_int($post, 'id_class'),
                    ':tier' => (float) str_replace(',', '.', dev_npc_post_str($post, 'tier', 10) ?: '1'),
                    ':base_hp' => dev_npc_post_int($post, 'base_hp', 60),
                    ':base_atk' => dev_npc_post_int($post, 'base_atk', 50),
                    ':base_def' => dev_npc_post_int($post, 'base_def', 50),
                    ':base_matk' => dev_npc_post_int($post, 'base_matk', 50),
                    ':base_mdef' => dev_npc_post_int($post, 'base_mdef', 50),
                    ':base_spd' => dev_npc_post_int($post, 'base_spd', 50),
                    ':base_acc' => dev_npc_post_int($post, 'base_acc', 100),
                    ':base_eva' => dev_npc_post_int($post, 'base_eva', 5),
                    ':base_cr' => dev_npc_post_int($post, 'base_cr', 10),
                    ':reward_exp' => dev_npc_post_int($post, 'reward_exp', 46),
                    ':flg_attivo' => dev_npc_post_yn($post, 'flg_attivo', 'S'),
                    ':species_it' => dev_npc_post_str($post, 'species_it', 100),
                    ':species_pt' => dev_npc_post_str($post, 'species_pt', 100),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Species updated (id ' . $id_species . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', ['edit' => 'species', 'id' => $id_species]),
                ];

            case 'add_ability':
                $effect = dev_species_resolve_effect($post);
                $effect_chance = dev_npc_post_int($post, 'effect_chance');

                if ($effect === 'none')
                {
                    $effect_chance = 0;
                }

                $stmt = $conn->prepare('
                    INSERT INTO abilities
                    (
                        dt_creazione, ability, descrizione, accuracy, power, m_power, effect, effect_chance,
                        ability_it, descrizione_it, ability_pt, descrizione_pt, id_element
                    )
                    VALUES
                    (
                        NOW(), :ability, :descrizione, :accuracy, :power, :m_power, :effect, :effect_chance,
                        :ability_it, :descrizione_it, :ability_pt, :descrizione_pt, :id_element
                    )
                ');
                $stmt->execute([
                    ':ability' => dev_npc_post_str($post, 'ability', 100),
                    ':descrizione' => dev_npc_post_str($post, 'descrizione', 300),
                    ':accuracy' => dev_npc_post_int($post, 'accuracy', 100),
                    ':power' => dev_npc_post_int($post, 'power'),
                    ':m_power' => dev_npc_post_int($post, 'm_power'),
                    ':effect' => $effect,
                    ':effect_chance' => $effect_chance,
                    ':ability_it' => dev_npc_post_str($post, 'ability_it', 100),
                    ':descrizione_it' => dev_npc_post_str($post, 'descrizione_it', 300),
                    ':ability_pt' => dev_npc_post_str($post, 'ability_pt', 100),
                    ':descrizione_pt' => dev_npc_post_str($post, 'descrizione_pt', 300),
                    ':id_element' => dev_npc_post_int($post, 'id_element'),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Ability created (id ' . $conn->lastInsertId() . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', ['tab' => 'ability']),
                ];

            case 'update_ability':
                $id_ability = dev_npc_post_int($post, 'id_ability');
                $effect = dev_species_resolve_effect($post);
                $effect_chance = dev_npc_post_int($post, 'effect_chance');

                if ($effect === 'none')
                {
                    $effect_chance = 0;
                }

                $stmt = $conn->prepare('
                    UPDATE abilities
                    SET ability = :ability,
                        descrizione = :descrizione,
                        accuracy = :accuracy,
                        power = :power,
                        m_power = :m_power,
                        effect = :effect,
                        effect_chance = :effect_chance,
                        ability_it = :ability_it,
                        descrizione_it = :descrizione_it,
                        ability_pt = :ability_pt,
                        descrizione_pt = :descrizione_pt,
                        id_element = :id_element
                    WHERE id_ability = :id_ability
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_ability' => $id_ability,
                    ':ability' => dev_npc_post_str($post, 'ability', 100),
                    ':descrizione' => dev_npc_post_str($post, 'descrizione', 300),
                    ':accuracy' => dev_npc_post_int($post, 'accuracy', 100),
                    ':power' => dev_npc_post_int($post, 'power'),
                    ':m_power' => dev_npc_post_int($post, 'm_power'),
                    ':effect' => $effect,
                    ':effect_chance' => $effect_chance,
                    ':ability_it' => dev_npc_post_str($post, 'ability_it', 100),
                    ':descrizione_it' => dev_npc_post_str($post, 'descrizione_it', 300),
                    ':ability_pt' => dev_npc_post_str($post, 'ability_pt', 100),
                    ':descrizione_pt' => dev_npc_post_str($post, 'descrizione_pt', 300),
                    ':id_element' => dev_npc_post_int($post, 'id_element'),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Ability updated (id ' . $id_ability . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', ['tab' => 'ability', 'edit' => 'ability', 'id' => $id_ability]),
                ];

            case 'add_species_ability':
                $id_species = dev_npc_post_int($post, 'id_species');

                $stmt = $conn->prepare('
                    INSERT INTO species_abilities
                    (dt_creazione, id_species, id_ability, unlock_lvl, id_element)
                    VALUES
                    (NOW(), :id_species, :id_ability, :unlock_lvl, :id_element)
                ');
                $stmt->execute([
                    ':id_species' => $id_species,
                    ':id_ability' => dev_npc_post_int($post, 'id_ability'),
                    ':unlock_lvl' => dev_npc_post_int($post, 'unlock_lvl'),
                    ':id_element' => dev_npc_post_int($post, 'id_element'),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Species ability linked (id ' . $conn->lastInsertId() . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', [
                        'tab' => 'species_ability',
                        'id_species' => $id_species,
                    ]),
                ];

            case 'update_species_ability':
                $id_species_ability = dev_npc_post_int($post, 'id_species_ability');
                $id_species = dev_npc_post_int($post, 'id_species');

                $stmt = $conn->prepare('
                    UPDATE species_abilities
                    SET id_species = :id_species,
                        id_ability = :id_ability,
                        unlock_lvl = :unlock_lvl,
                        id_element = :id_element
                    WHERE id_species_ability = :id_species_ability
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_species_ability' => $id_species_ability,
                    ':id_species' => $id_species,
                    ':id_ability' => dev_npc_post_int($post, 'id_ability'),
                    ':unlock_lvl' => dev_npc_post_int($post, 'unlock_lvl'),
                    ':id_element' => dev_npc_post_int($post, 'id_element'),
                ]);

                return [
                    'ok' => true,
                    'message' => 'Species ability updated (id ' . $id_species_ability . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', [
                        'tab' => 'species_ability',
                        'id_species' => $id_species,
                        'edit' => 'species_ability',
                        'id' => $id_species_ability,
                    ]),
                ];

            case 'add_wild_drop':
                $id_species = dev_npc_post_int($post, 'id_species');
                $id_quest_required = dev_npc_post_int($post, 'id_quest_required');
                $id_quest_required = $id_quest_required > 0 ? $id_quest_required : null;

                $stmt = $conn->prepare('
                    INSERT INTO wild_animal_drop_types
                    (dt_c, drop_type, id_item_type, id_species, lvl_min, lvl_max, qt_min, qt_max, chance, id_quest_required)
                    VALUES
                    (NOW(), :drop_type, :id_item_type, :id_species, :lvl_min, :lvl_max, :qt_min, :qt_max, :chance, :id_quest_required)
                ');
                $stmt->execute([
                    ':drop_type' => dev_npc_post_str($post, 'drop_type', 100) ?: 'item',
                    ':id_item_type' => dev_npc_post_int($post, 'id_item_type'),
                    ':id_species' => $id_species,
                    ':lvl_min' => dev_npc_post_int($post, 'lvl_min', 1),
                    ':lvl_max' => dev_npc_post_int($post, 'lvl_max', 100),
                    ':qt_min' => dev_npc_post_int($post, 'qt_min', 1),
                    ':qt_max' => dev_npc_post_int($post, 'qt_max', 1),
                    ':chance' => dev_npc_post_int($post, 'chance', 100),
                    ':id_quest_required' => $id_quest_required,
                ]);

                return [
                    'ok' => true,
                    'message' => 'Wild drop created (id ' . $conn->lastInsertId() . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', [
                        'tab' => 'wild_drop',
                        'id_species' => $id_species,
                    ]),
                ];

            case 'update_wild_drop':
                $id_drop = dev_npc_post_int($post, 'id_wild_animal_drop_type');
                $id_species = dev_npc_post_int($post, 'id_species');
                $id_quest_required = dev_npc_post_int($post, 'id_quest_required');
                $id_quest_required = $id_quest_required > 0 ? $id_quest_required : null;

                $stmt = $conn->prepare('
                    UPDATE wild_animal_drop_types
                    SET dt_m = NOW(),
                        drop_type = :drop_type,
                        id_item_type = :id_item_type,
                        id_species = :id_species,
                        lvl_min = :lvl_min,
                        lvl_max = :lvl_max,
                        qt_min = :qt_min,
                        qt_max = :qt_max,
                        chance = :chance,
                        id_quest_required = :id_quest_required
                    WHERE id_wild_animal_drop_type = :id_wild_animal_drop_type
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_wild_animal_drop_type' => $id_drop,
                    ':drop_type' => dev_npc_post_str($post, 'drop_type', 100) ?: 'item',
                    ':id_item_type' => dev_npc_post_int($post, 'id_item_type'),
                    ':id_species' => $id_species,
                    ':lvl_min' => dev_npc_post_int($post, 'lvl_min', 1),
                    ':lvl_max' => dev_npc_post_int($post, 'lvl_max', 100),
                    ':qt_min' => dev_npc_post_int($post, 'qt_min', 1),
                    ':qt_max' => dev_npc_post_int($post, 'qt_max', 1),
                    ':chance' => dev_npc_post_int($post, 'chance', 100),
                    ':id_quest_required' => $id_quest_required,
                ]);

                return [
                    'ok' => true,
                    'message' => 'Wild drop updated (id ' . $id_drop . ').',
                    'redirect' => dev_admin_page_url('dev_species.php', [
                        'tab' => 'wild_drop',
                        'id_species' => $id_species,
                        'edit' => 'wild_drop',
                        'id' => $id_drop,
                    ]),
                ];

            default:
                return ['ok' => false, 'message' => 'Unknown action.'];
        }
    }
    catch (PDOException $e)
    {
        error_log('[dev_species_content] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
