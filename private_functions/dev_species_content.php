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
        SELECT id_ability, ability, accuracy, power, m_power, effect, effect_chance, id_element
        FROM abilities
        ORDER BY id_ability
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
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
