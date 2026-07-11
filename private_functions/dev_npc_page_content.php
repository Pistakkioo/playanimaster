<?php

require_once __DIR__ . '/dev_npc_content.php';

const DEV_NPC_PAGE_REQ_NOT_MET = 'conversation requirements not met';

/**
 * @return array<int, array<string, mixed>>
 */
function dev_npc_page_fetch_all(PDO $conn)
{
    $stmt = $conn->query('
        SELECT n.*, z.scene_name,
               (SELECT COUNT(*) FROM conversations c WHERE c.id_npc = n.id_npc) AS conversation_count
        FROM npcs n
        LEFT JOIN zones z ON z.id_zone = n.id_zone
        ORDER BY n.id_npc ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<string, mixed>|null
 */
function dev_npc_page_load(PDO $conn, $id_npc)
{
    $id_npc = (int) $id_npc;

    if ($id_npc <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT n.*, z.scene_name
        FROM npcs n
        LEFT JOIN zones z ON z.id_zone = n.id_zone
        WHERE n.id_npc = :id_npc
        LIMIT 1
    ');
    $stmt->execute([':id_npc' => $id_npc]);
    $npc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$npc)
    {
        return null;
    }

    $lookup = dev_npc_page_build_lookup($conn, $id_npc);
    $npc_requirements = dev_npc_page_fetch_npc_requirements($conn, $id_npc);
    $conversations = dev_npc_page_fetch_conversations($conn, $id_npc, $lookup);
    $quests = dev_npc_page_fetch_npc_quests($conn, $id_npc);
    $tree = dev_npc_page_build_interaction_tree($conversations);
    $gated_flow = dev_npc_page_build_gated_flow($conversations, $tree['children_by_parent']);

    return [
        'npc' => $npc,
        'npc_requirements' => $npc_requirements,
        'conversations' => $conversations,
        'quests' => $quests,
        'lookup' => $lookup,
        'ungated' => $tree['ungated'],
        'gated_flow_chains' => $gated_flow['chains'],
        'orphan_fallbacks' => $tree['orphan_fallbacks'],
    ];
}

/**
 * @return array<string, mixed>
 */
function dev_npc_page_build_lookup(PDO $conn, $id_npc)
{
    $items = [];

    foreach (dev_npc_fetch_item_types($conn) as $row)
    {
        $items[(int) $row['id_item_type']] = (string) ($row['nome'] ?: $row['item_type']);
    }

    $classes = [];

    foreach (dev_npc_fetch_player_classes($conn) as $row)
    {
        $classes[(int) $row['id_player_class']] = (string) ($row['code'] . ' — ' . $row['name']);
    }

    $quests = [];
    $stmt = $conn->query('SELECT id_quest, quest FROM quests ORDER BY id_quest');

    if ($stmt)
    {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $quests[(int) $row['id_quest']] = (string) $row['quest'];
        }
    }

    $conversations = [];
    $stmt = $conn->query('
        SELECT c.id_conversation, c.title, c.id_npc, n.npc
        FROM conversations c
        LEFT JOIN npcs n ON n.id_npc = c.id_npc
        ORDER BY c.id_conversation
    ');

    if ($stmt)
    {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $conversations[(int) $row['id_conversation']] = $row;
        }
    }

    return [
        'items' => $items,
        'classes' => $classes,
        'quests' => $quests,
        'conversations' => $conversations,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function dev_npc_page_fetch_npc_requirements(PDO $conn, $id_npc)
{
    $stmt = $conn->prepare('
        SELECT NR.*, R.requirement_type
        FROM npc_requirements NR
        JOIN requirements R ON R.id_requirement = NR.id_requirement
        WHERE NR.id_npc = :id_npc
        ORDER BY NR.id_npc_requirement
    ');
    $stmt->execute([':id_npc' => (int) $id_npc]);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function dev_npc_page_fetch_npc_quests(PDO $conn, $id_npc)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM quests
        WHERE id_starter_npc = :id_npc
        ORDER BY id_quest
    ');
    $stmt->execute([':id_npc' => (int) $id_npc]);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function dev_npc_page_fetch_conversations(PDO $conn, $id_npc, array $lookup)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM conversations
        WHERE id_npc = :id_npc
        ORDER BY id_conversation ASC
    ');
    $stmt->execute([':id_npc' => (int) $id_npc]);

    $conversations = [];

    if (!$stmt)
    {
        return [];
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $id_conv = (int) $row['id_conversation'];
        $conversations[$id_conv] = [
            'conversation' => $row,
            'requirements' => [],
            'fallback_parent_id' => null,
            'fallback_requirement' => null,
            'other_requirements' => [],
            'dialogues' => [],
            'options_by_dialog' => [],
            'consequences' => [],
            'consequences_by_option' => [],
        ];
    }

    if (!$conversations)
    {
        return [];
    }

    $ids_sql = implode(',', array_map('intval', array_keys($conversations)));

    $stmt_req = $conn->query("
        SELECT CR.*, R.requirement_type
        FROM conversation_requirements CR
        JOIN requirements R ON R.id_requirement = CR.id_requirement
        WHERE CR.id_conversation IN ($ids_sql)
        ORDER BY CR.id_conversation, CR.id_conversation_requirement
    ");

    if ($stmt_req)
    {
        while ($row = $stmt_req->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (!isset($conversations[$id_conv]))
            {
                continue;
            }

            $conversations[$id_conv]['requirements'][] = $row;

            if (($row['requirement_type'] ?? '') === DEV_NPC_PAGE_REQ_NOT_MET)
            {
                $conversations[$id_conv]['fallback_parent_id'] = (int) ($row['id_ref'] ?? 0);
                $conversations[$id_conv]['fallback_requirement'] = $row;
            }
            else
            {
                $conversations[$id_conv]['other_requirements'][] = $row;
            }
        }
    }

    $stmt_dlg = $conn->query("
        SELECT *
        FROM dialogues
        WHERE id_conversation IN ($ids_sql)
        ORDER BY id_conversation, `order` ASC, id_dialog ASC
    ");

    if ($stmt_dlg)
    {
        while ($row = $stmt_dlg->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (isset($conversations[$id_conv]))
            {
                $conversations[$id_conv]['dialogues'][] = $row;
            }
        }
    }

    $stmt_opt = $conn->query("
        SELECT O.*
        FROM dialogues_options O
        JOIN dialogues D ON D.id_dialog = O.id_dialog
        WHERE D.id_conversation IN ($ids_sql)
        ORDER BY D.id_conversation, D.`order`, O.option_n
    ");

    if ($stmt_opt)
    {
        while ($row = $stmt_opt->fetch(PDO::FETCH_ASSOC))
        {
            $id_dialog = (int) $row['id_dialog'];

            foreach ($conversations as $id_conv => $bundle)
            {
                foreach ($bundle['dialogues'] as $dlg)
                {
                    if ((int) $dlg['id_dialog'] === $id_dialog)
                    {
                        $conversations[$id_conv]['options_by_dialog'][$id_dialog][] = $row;
                        break;
                    }
                }
            }
        }
    }

    $stmt_cons = $conn->query("
        SELECT CC.*, CO.consequence_type,
               DO.id_dialog, DO.option_n, DO.option_text, DO.option_color
        FROM conversation_consequences CC
        JOIN consequences CO ON CO.id_consequence = CC.id_consequence
        LEFT JOIN dialogues_options DO ON DO.id_dialog_option = CC.id_option
        WHERE CC.id_conversation IN ($ids_sql)
        ORDER BY CC.id_conversation, CC.id_option, CC.id_conversation_consequence
    ");

    if ($stmt_cons)
    {
        while ($row = $stmt_cons->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (!isset($conversations[$id_conv]))
            {
                continue;
            }

            $conversations[$id_conv]['consequences'][] = $row;
            $opt_key = (int) ($row['id_option'] ?? 0);
            $conversations[$id_conv]['consequences_by_option'][$opt_key][] = $row;
        }
    }

    return $conversations;
}

/**
 * Dev flow layout: which consequence types unlock which requirement types on a later conversation.
 *
 * @return array<string, array<string, string>> consequence_type => [ requirement_type => canonical ref_table ]
 */
function dev_npc_page_consequence_requirement_map()
{
    return [
        '[start quest]' => ['quest started' => 'quests'],
        '[complete quest]' => ['quest completed' => 'quests'],
        '[set player_class]' => ['player class' => 'player_classes'],
        '[obtain item]' => ['item' => 'item_types'],
    ];
}

/**
 * Requirement types that advance the horizontal column (excluding static gates and ELSE fallbacks).
 *
 * @return array<int, string>
 */
function dev_npc_page_progression_requirement_types()
{
    return [
        'conversation finished',
        'quest started',
        'quest ready to turn in',
        'quest completed',
        'quest phase',
        'quest phase completed',
        'item',
        'player class',
    ];
}

/**
 * @param array<string, mixed> $cons_row
 */
function dev_npc_page_consequence_ref_matches(array $cons_row, $id_ref, $canonical_ref_table)
{
    $id_ref = (int) $id_ref;
    $cons_id_ref = (int) ($cons_row['id_ref'] ?? 0);

    if ($cons_id_ref !== $id_ref)
    {
        return false;
    }

    $cons_ref = dev_npc_normalize_requirement_ref_table((string) ($cons_row['ref_table'] ?? ''));

    if ($canonical_ref_table === '' || $cons_ref === $canonical_ref_table)
    {
        return true;
    }

    if ($cons_ref === '')
    {
        return true;
    }

    return dev_npc_requirement_ref_table_is((string) ($cons_row['ref_table'] ?? ''), $canonical_ref_table);
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @return array<int>
 */
function dev_npc_page_find_conversations_with_consequence(
    array $conversations,
    $consequence_type,
    $id_ref,
    $canonical_ref_table
)
{
    $matches = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        foreach ($bundle['consequences'] as $cons)
        {
            if (($cons['consequence_type'] ?? '') !== $consequence_type)
            {
                continue;
            }

            if (!dev_npc_page_consequence_ref_matches($cons, $id_ref, $canonical_ref_table))
            {
                continue;
            }

            $matches[] = (int) $id_conv;
            break;
        }
    }

    sort($matches, SORT_NUMERIC);

    return $matches;
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @param array<int, string> $requirement_types
 * @return array<int>
 */
function dev_npc_page_find_conversations_with_quest_phase_req(
    array $conversations,
    $child_conv_id,
    $id_quest,
    $phase,
    array $requirement_types
)
{
    $child_conv_id = (int) $child_conv_id;
    $id_quest = (int) $id_quest;
    $phase = (int) $phase;
    $type_lookup = array_flip($requirement_types);
    $matches = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        if ((int) $id_conv === $child_conv_id)
        {
            continue;
        }

        foreach ($bundle['other_requirements'] as $other_req)
        {
            $other_type = (string) ($other_req['requirement_type'] ?? '');

            if (!isset($type_lookup[$other_type]))
            {
                continue;
            }

            if ((int) ($other_req['id_ref'] ?? 0) !== $id_quest)
            {
                continue;
            }

            if ((int) ($other_req['min'] ?? 0) !== $phase)
            {
                continue;
            }

            $matches[] = (int) $id_conv;
            break;
        }
    }

    sort($matches, SORT_NUMERIC);

    return $matches;
}

/**
 * Find parent conversation ids whose consequences satisfy this requirement (same NPC).
 *
 * @param array<string, mixed> $req
 * @param array<int, array<string, mixed>> $conversations
 * @return array<int>
 */
function dev_npc_page_progression_parents_for_requirement(array $req, array $conversations, $child_conv_id = 0)
{
    $req_type = (string) ($req['requirement_type'] ?? '');
    $id_ref = (int) ($req['id_ref'] ?? 0);
    $ref_table = dev_npc_normalize_requirement_ref_table((string) ($req['ref_table'] ?? ''));
    $map = dev_npc_page_consequence_requirement_map();
    $child_conv_id = (int) $child_conv_id;

    if ($req_type === 'conversation finished' && $id_ref > 0)
    {
        return isset($conversations[$id_ref]) ? [$id_ref] : [];
    }

    if (($req_type === 'quest phase' || $req_type === 'quest phase completed') && $id_ref > 0)
    {
        $phase = (int) ($req['min'] ?? 0);

        if ($phase <= 0)
        {
            return [];
        }

        if ($req_type === 'quest phase' && $phase <= 1)
        {
            return dev_npc_page_find_conversations_with_consequence(
                $conversations,
                '[start quest]',
                $id_ref,
                'quests'
            );
        }

        $target_phase = $req_type === 'quest phase' ? $phase - 1 : $phase;
        $parent_types = $req_type === 'quest phase'
            ? ['quest phase completed', 'quest phase']
            : ['quest phase'];

        return dev_npc_page_find_conversations_with_quest_phase_req(
            $conversations,
            $child_conv_id,
            $id_ref,
            $target_phase,
            $parent_types
        );
    }

    if ($req_type === 'quest ready to turn in' && $id_ref > 0)
    {
        $parents = [];

        foreach ($conversations as $id_conv => $bundle)
        {
            if ((int) $id_conv === $child_conv_id)
            {
                continue;
            }

            foreach ($bundle['other_requirements'] as $other_req)
            {
                if (($other_req['requirement_type'] ?? '') !== 'quest started')
                {
                    continue;
                }

                if ((int) ($other_req['id_ref'] ?? 0) !== $id_ref)
                {
                    continue;
                }

                if ($ref_table !== '' && $ref_table !== 'quests')
                {
                    continue;
                }

                $parents[] = (int) $id_conv;
                break;
            }
        }

        if (!$parents)
        {
            $parents = dev_npc_page_find_conversations_with_consequence(
                $conversations,
                '[start quest]',
                $id_ref,
                'quests'
            );
        }

        return array_values(array_unique($parents));
    }

    foreach ($map as $consequence_type => $requirement_map)
    {
        if (!isset($requirement_map[$req_type]))
        {
            continue;
        }

        $expected_ref_table = $requirement_map[$req_type];

        if ($ref_table !== '' && $ref_table !== $expected_ref_table)
        {
            continue;
        }

        return dev_npc_page_find_conversations_with_consequence(
            $conversations,
            $consequence_type,
            $id_ref,
            $expected_ref_table
        );
    }

    return [];
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @return array<int, array<int>>
 */
function dev_npc_page_build_progression_parents(array $conversations)
{
    $progression_types = array_flip(dev_npc_page_progression_requirement_types());
    $parents_by_child = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        if ((int) ($bundle['fallback_parent_id'] ?? 0) > 0)
        {
            continue;
        }

        $parents = [];

        foreach ($bundle['other_requirements'] as $req)
        {
            if (!isset($progression_types[(string) ($req['requirement_type'] ?? '')]))
            {
                continue;
            }

            foreach (dev_npc_page_progression_parents_for_requirement($req, $conversations, (int) $id_conv) as $parent_id)
            {
                if ($parent_id > 0 && $parent_id !== (int) $id_conv)
                {
                    $parents[$parent_id] = $parent_id;
                }
            }
        }

        if ($parents)
        {
            $parents_by_child[(int) $id_conv] = array_values($parents);
        }
    }

    return $parents_by_child;
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @param array<int, array<int>> $progression_parents
 * @return array<int, int>
 */
function dev_npc_page_assign_flow_columns(array $conversations, array $progression_parents)
{
    $columns = [];
    $gated_ids = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        if ((int) ($bundle['fallback_parent_id'] ?? 0) > 0)
        {
            continue;
        }

        if (count($bundle['requirements']) === 0)
        {
            continue;
        }

        $gated_ids[(int) $id_conv] = true;
    }

    foreach (array_keys($gated_ids) as $id_conv)
    {
        if (!isset($progression_parents[$id_conv]))
        {
            $columns[$id_conv] = 0;
        }
    }

    $changed = true;

    while ($changed)
    {
        $changed = false;

        foreach (array_keys($gated_ids) as $id_conv)
        {
            if (isset($columns[$id_conv]))
            {
                continue;
            }

            if (empty($progression_parents[$id_conv]))
            {
                $columns[$id_conv] = 0;
                $changed = true;
                continue;
            }

            $parent_cols = [];

            foreach ($progression_parents[$id_conv] as $parent_id)
            {
                if (isset($columns[$parent_id]))
                {
                    $parent_cols[] = $columns[$parent_id];
                }
            }

            if (count($parent_cols) !== count($progression_parents[$id_conv]))
            {
                continue;
            }

            $columns[$id_conv] = max($parent_cols) + 1;
            $changed = true;
        }
    }

    foreach (array_keys($gated_ids) as $id_conv)
    {
        if (!isset($columns[$id_conv]))
        {
            $columns[$id_conv] = 0;
        }
    }

    return $columns;
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @param array<int, array<int>> $children_by_parent
 * @return array{chains: array<int, array<string, mixed>>}
 */
function dev_npc_page_build_gated_flow(array $conversations, array $children_by_parent)
{
    $progression_parents = dev_npc_page_build_progression_parents($conversations);
    $columns = dev_npc_page_assign_flow_columns($conversations, $progression_parents);
    $gated_ids = array_keys($columns);

    if (!$gated_ids)
    {
        return ['chains' => []];
    }

    $adjacency = [];

    foreach ($gated_ids as $id_conv)
    {
        if (!isset($adjacency[$id_conv]))
        {
            $adjacency[$id_conv] = [];
        }

        if (isset($progression_parents[$id_conv]))
        {
            foreach ($progression_parents[$id_conv] as $parent_id)
            {
                if (isset($columns[$parent_id]))
                {
                    $adjacency[$id_conv][$parent_id] = true;
                    $adjacency[$parent_id][$id_conv] = true;
                }
            }
        }
    }

    $visited = [];
    $chains = [];

    foreach ($gated_ids as $start_id)
    {
        if (isset($visited[$start_id]))
        {
            continue;
        }

        $component = [];
        $stack = [$start_id];

        while ($stack)
        {
            $current = array_pop($stack);

            if (isset($visited[$current]))
            {
                continue;
            }

            $visited[$current] = true;
            $component[] = $current;

            if (isset($adjacency[$current]))
            {
                foreach (array_keys($adjacency[$current]) as $neighbor)
                {
                    if (!isset($visited[$neighbor]))
                    {
                        $stack[] = $neighbor;
                    }
                }
            }
        }

        sort($component, SORT_NUMERIC);

        $columns_by_index = [];
        $max_column = 0;

        foreach ($component as $id_conv)
        {
            $col = (int) ($columns[$id_conv] ?? 0);
            $max_column = max($max_column, $col);

            if (!isset($columns_by_index[$col]))
            {
                $columns_by_index[$col] = [];
            }

            $columns_by_index[$col][] = dev_npc_page_build_tree_node(
                $id_conv,
                $conversations,
                $children_by_parent
            );
        }

        ksort($columns_by_index, SORT_NUMERIC);

        foreach ($columns_by_index as $col => $nodes)
        {
            usort($columns_by_index[$col], 'dev_npc_page_sort_nodes_by_id');
        }

        $chains[] = [
            'conversation_ids' => $component,
            'max_column' => $max_column,
            'columns' => $columns_by_index,
            'column_by_conversation' => array_intersect_key($columns, array_flip($component)),
        ];
    }

    usort($chains, function ($a, $b)
    {
        $a_min = min($a['conversation_ids']);
        $b_min = min($b['conversation_ids']);

        return $a_min <=> $b_min;
    });

    return ['chains' => $chains];
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @return array{ungated: array, gated_roots: array, orphan_fallbacks: array, children_by_parent: array<int, array<int>>}
 */
function dev_npc_page_build_interaction_tree(array $conversations)
{
    $children_by_parent = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        $parent_id = (int) ($bundle['fallback_parent_id'] ?? 0);

        if ($parent_id > 0)
        {
            if (!isset($children_by_parent[$parent_id]))
            {
                $children_by_parent[$parent_id] = [];
            }

            $children_by_parent[$parent_id][] = (int) $id_conv;
        }
    }

    foreach ($children_by_parent as $parent_id => $child_ids)
    {
        sort($child_ids, SORT_NUMERIC);
        $children_by_parent[$parent_id] = $child_ids;
    }

    $ungated = [];
    $gated_roots = [];
    $orphan_fallbacks = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        $is_fallback = (int) ($bundle['fallback_parent_id'] ?? 0) > 0;
        $has_requirements = count($bundle['requirements']) > 0;

        if ($is_fallback)
        {
            $parent_id = (int) $bundle['fallback_parent_id'];

            if (!isset($conversations[$parent_id]))
            {
                $orphan_fallbacks[] = dev_npc_page_build_tree_node(
                    (int) $id_conv,
                    $conversations,
                    $children_by_parent
                );
            }

            continue;
        }

        if (!$has_requirements)
        {
            $ungated[] = dev_npc_page_build_tree_node(
                (int) $id_conv,
                $conversations,
                $children_by_parent
            );
            continue;
        }

        $gated_roots[] = dev_npc_page_build_tree_node(
            (int) $id_conv,
            $conversations,
            $children_by_parent
        );
    }

    usort($ungated, 'dev_npc_page_sort_nodes_by_id');
    usort($gated_roots, 'dev_npc_page_sort_nodes_by_id');
    usort($orphan_fallbacks, 'dev_npc_page_sort_nodes_by_id');

    return [
        'ungated' => $ungated,
        'gated_roots' => $gated_roots,
        'orphan_fallbacks' => $orphan_fallbacks,
        'children_by_parent' => $children_by_parent,
    ];
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @param array<int, array<int>> $children_by_parent
 * @return array<string, mixed>
 */
function dev_npc_page_build_tree_node($id_conv, array $conversations, array $children_by_parent)
{
    $bundle = $conversations[$id_conv];
    $children = [];

    if (isset($children_by_parent[$id_conv]))
    {
        foreach ($children_by_parent[$id_conv] as $child_id)
        {
            if (isset($conversations[$child_id]))
            {
                $children[] = dev_npc_page_build_tree_node($child_id, $conversations, $children_by_parent);
            }
        }
    }

    return [
        'id_conversation' => (int) $id_conv,
        'bundle' => $bundle,
        'children' => $children,
    ];
}

function dev_npc_page_sort_nodes_by_id($a, $b)
{
    return $a['id_conversation'] <=> $b['id_conversation'];
}

function dev_npc_page_url(array $extra = [])
{
    return dev_admin_page_url('dev_npc.php', $extra);
}

function dev_npc_page_edit_conversation_url($id_npc, $id_conversation)
{
    return dev_admin_page_url('dev_npcs.php', [
        'tab' => 'conv',
        'edit' => 'conversation',
        'id' => (int) $id_conversation,
    ]);
}

function dev_npc_page_edit_requirement_link_url($link_row)
{
    return dev_admin_page_url('dev_npcs.php', [
        'tab' => 'req',
        'edit' => 'conversation_requirement',
        'id' => (int) $link_row['id_conversation_requirement'],
    ]);
}

/**
 * Human-readable requirement line for the flow diagram.
 */
function dev_npc_page_format_requirement(array $req, array $lookup)
{
    $parts = [(string) ($req['requirement_type'] ?? '?')];
    $id_ref = (int) ($req['id_ref'] ?? 0);
    $ref_table = dev_npc_normalize_requirement_ref_table((string) ($req['ref_table'] ?? ''));
    $min = array_key_exists('min', $req) ? (int) $req['min'] : 0;
    $max = array_key_exists('max', $req) && $req['max'] !== '' && $req['max'] !== null
        ? (int) $req['max']
        : null;

    if ($ref_table !== '')
    {
        $parts[] = $ref_table;
    }

    if (($req['requirement_type'] ?? '') === DEV_NPC_PAGE_REQ_NOT_MET && $id_ref > 0)
    {
        $target = dev_npc_page_conversation_label($id_ref, $lookup);
        $parts[] = '→ ' . $target;
    }
    elseif ($id_ref > 0)
    {
        $parts[] = dev_npc_page_resolve_ref_label($ref_table, $id_ref, $lookup);
    }

    if (!empty($req['ref_description']))
    {
        $parts[] = '(' . (string) $req['ref_description'] . ')';
    }

    if (($req['requirement_type'] ?? '') === 'user lvl' || $ref_table === 'users_ig.level')
    {
        if ($max !== null && $max > 0 && $min <= 0)
        {
            $parts[] = 'max ' . $max;
        }
        elseif ($min > 0)
        {
            $parts[] = 'min ' . $min;
        }
    }
    elseif (($req['requirement_type'] ?? '') === 'quest phase'
        || ($req['requirement_type'] ?? '') === 'quest phase completed')
    {
        if ($min > 0)
        {
            $parts[] = 'phase ' . $min;
        }
    }
    elseif ($min > 0 || $max !== null)
    {
        $range = 'min ' . $min;

        if ($max !== null)
        {
            $range .= ', max ' . $max;
        }

        $parts[] = $range;
    }

    return implode(' · ', $parts);
}

function dev_npc_page_conversation_label($id_conversation, array $lookup)
{
    $id_conversation = (int) $id_conversation;

    if (isset($lookup['conversations'][$id_conversation]))
    {
        $c = $lookup['conversations'][$id_conversation];
        $npc = (string) ($c['npc'] ?? '');
        $title = (string) ($c['title'] ?? '');

        return '#' . $id_conversation . ' ' . $title . ($npc !== '' ? ' [' . $npc . ']' : '');
    }

    return 'conversation #' . $id_conversation;
}

function dev_npc_page_resolve_ref_label($ref_table, $id_ref, array $lookup)
{
    $ref_table = dev_npc_normalize_requirement_ref_table($ref_table);
    $id_ref = (int) $id_ref;

    if ($ref_table === 'quests' && isset($lookup['quests'][$id_ref]))
    {
        return '#' . $id_ref . ' ' . $lookup['quests'][$id_ref];
    }

    if ($ref_table === 'player_classes' && isset($lookup['classes'][$id_ref]))
    {
        return '#' . $id_ref . ' ' . $lookup['classes'][$id_ref];
    }

    if ($ref_table === 'item_types' && isset($lookup['items'][$id_ref]))
    {
        return '#' . $id_ref . ' ' . $lookup['items'][$id_ref];
    }

    if ($ref_table === 'conversations')
    {
        return dev_npc_page_conversation_label($id_ref, $lookup);
    }

    if ($id_ref > 0)
    {
        return '#' . $id_ref;
    }

    return $ref_table !== '' ? $ref_table : '0';
}

function dev_npc_page_format_npc_requirement(array $req, array $lookup)
{
    return dev_npc_page_format_requirement($req, $lookup);
}

/**
 * Recursive HTML renderer for one conversation node + ELSE children.
 *
 * @param array<string, mixed> $node
 */
/**
 * @param array<string, mixed> $chain
 */
function dev_npc_page_render_flow_chain(array $chain, array $lookup)
{
    $columns = $chain['columns'] ?? [];

    if (!$columns)
    {
        return;
    }
    ?>
    <div class="npc-flow-chain">
        <div class="npc-flow-columns">
            <?php foreach ($columns as $col_index => $nodes): ?>
            <div class="npc-flow-col" data-column="<?php echo (int) $col_index; ?>">
                <?php foreach ($nodes as $node): ?>
                <?php dev_npc_page_render_interaction_node($node, $lookup, 0, false); ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Recursive HTML renderer for one conversation node + ELSE children.
 *
 * @param array<string, mixed> $node
 */
function dev_npc_page_render_interaction_node(array $node, array $lookup, $depth = 0, $is_else_branch = false)
{
    $bundle = $node['bundle'];
    $conv = $bundle['conversation'];
    $id_conv = (int) $node['id_conversation'];
    $depth = (int) $depth;
    ?>
    <div class="npc-interaction-node<?php echo $is_else_branch ? ' npc-interaction-else' : ''; ?><?php echo $depth > 0 ? ' npc-interaction-nested' : ''; ?>" style="--depth: <?php echo $depth; ?>">
        <?php if ($is_else_branch): ?>
        <div class="npc-else-connector" aria-hidden="true">
            <svg viewBox="0 0 24 48" class="npc-else-svg"><path d="M12 0 L12 28 M12 28 L4 40 M12 28 L20 40" stroke="#ff922b" stroke-width="2" fill="none"/></svg>
            <span class="npc-else-label">ELSE</span>
            <span class="npc-else-hint">requirements not met on #<?php echo (int) ($bundle['fallback_parent_id'] ?? 0); ?></span>
        </div>
        <?php endif; ?>

        <div class="npc-conv-card">
            <div class="npc-conv-head">
                <div>
                    <h3 class="npc-conv-title">#<?php echo $id_conv; ?> <?php echo dev_admin_h((string) $conv['title']); ?></h3>
                    <div class="meta">
                        visible <?php echo dev_admin_h((string) ($conv['visible'] ?? 'N')); ?>
                        · register <?php echo dev_admin_h((string) ($conv['flg_register'] ?? 'N')); ?>
                        · repeatable <?php echo dev_admin_h((string) ($conv['flg_repeatable'] ?? 'N')); ?>
                    </div>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_npc_page_edit_conversation_url((int) $conv['id_npc'], $id_conv)); ?>">Edit</a>
            </div>

            <?php if ($bundle['requirements']): ?>
            <div class="npc-req-block">
                <div class="npc-block-label">Requirements <span class="meta">(all must pass — AND)</span></div>
                <ul class="npc-req-list mb-0">
                    <?php foreach ($bundle['requirements'] as $req): ?>
                    <li class="<?php echo ($req['requirement_type'] ?? '') === DEV_NPC_PAGE_REQ_NOT_MET ? 'npc-req-not-met' : ''; ?>">
                        <?php echo dev_admin_h(dev_npc_page_format_requirement($req, $lookup)); ?>
                        <a class="btn btn-outline-secondary btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_npc_page_edit_requirement_link_url($req)); ?>">✎</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php else: ?>
            <div class="npc-req-block npc-req-none">
                <div class="npc-block-label">No requirements</div>
                <div class="meta">Always offered (subject to NPC-level gates and finished/repeatable rules).</div>
            </div>
            <?php endif; ?>

            <?php if ($bundle['dialogues']): ?>
            <div class="npc-dialog-block">
                <div class="npc-block-label">Dialogues</div>
                <?php foreach ($bundle['dialogues'] as $dlg): ?>
                <div class="npc-dialog-line">
                    <span class="npc-dialog-order"><?php echo (int) $dlg['order']; ?>.</span>
                    <?php echo dev_admin_h(mb_strimwidth((string) ($dlg['dialog'] ?? ''), 0, 200, '…')); ?>
                    <?php if (($dlg['flg_options'] ?? 'N') === 'S'): ?><span class="badge bg-secondary">options</span><?php endif; ?>
                    <?php
                    $id_dialog = (int) $dlg['id_dialog'];

                    if (!empty($bundle['options_by_dialog'][$id_dialog])):
                        foreach ($bundle['options_by_dialog'][$id_dialog] as $opt):
                            $opt_id = (int) $opt['id_dialog_option'];
                            $color = (string) ($opt['option_color'] ?? '');
                    ?>
                    <div class="npc-option npc-option-<?php echo dev_admin_h($color === 'red' ? 'red' : 'green'); ?>">
                        <div class="npc-option-main">
                            <span class="npc-option-text"><?php echo dev_admin_h((string) ($opt['option_text'] ?? '')); ?></span>
                            <span class="meta">opt #<?php echo $opt_id; ?></span>
                        </div>
                        <?php if (!empty($bundle['consequences_by_option'][$opt_id])): ?>
                        <div class="npc-cons-list">
                            <?php foreach ($bundle['consequences_by_option'][$opt_id] as $cons): ?>
                            <span class="npc-cons-chip"><?php echo dev_admin_h((string) $cons['consequence_type']); ?><?php if ((int) ($cons['id_ref'] ?? 0) > 0): ?> · ref #<?php echo (int) $cons['id_ref']; ?><?php endif; ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php
            $orphan_cons = [];

            foreach ($bundle['consequences'] as $cons)
            {
                if ((int) ($cons['id_option'] ?? 0) <= 0)
                {
                    $orphan_cons[] = $cons;
                }
            }

            if ($orphan_cons):
            ?>
            <div class="npc-cons-block">
                <div class="npc-block-label">Consequences (no option link)</div>
                <div class="npc-cons-list">
                    <?php foreach ($orphan_cons as $cons): ?>
                    <span class="npc-cons-chip"><?php echo dev_admin_h((string) $cons['consequence_type']); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($node['children']): ?>
        <div class="npc-else-branches">
            <?php foreach ($node['children'] as $child): ?>
            <?php dev_npc_page_render_interaction_node($child, $lookup, $depth + 1, true); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
