<?php

require_once __DIR__ . '/dev_npc_content.php';

/**
 * @return array<int, array<string, mixed>>
 */
function dev_quest_fetch_all(PDO $conn)
{
    $stmt = $conn->query('
        SELECT q.*, n.npc AS starter_npc_name
        FROM quests q
        LEFT JOIN npcs n ON n.id_npc = q.id_starter_npc
        ORDER BY q.id_quest ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<string, mixed>|null
 */
function dev_quest_load(PDO $conn, $id_quest)
{
    $id_quest = (int) $id_quest;

    if ($id_quest <= 0)
    {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT q.*, n.npc AS starter_npc_name, n.id_zone AS starter_npc_zone
        FROM quests q
        LEFT JOIN npcs n ON n.id_npc = q.id_starter_npc
        WHERE q.id_quest = :id_quest
        LIMIT 1
    ');
    $stmt->execute([':id_quest' => $id_quest]);
    $quest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quest)
    {
        return null;
    }

    $ctx = dev_quest_build_lookup_context($conn);
    $objectives = dev_quest_fetch_objectives($conn, $id_quest);
    $objectives_by_phase = dev_quest_group_objectives_by_phase($objectives);
    $max_phase = dev_quest_max_phase($objectives_by_phase);
    $conversations = dev_quest_fetch_related_conversations($conn, $id_quest, $objectives, $ctx);
    $quest_drops = dev_quest_fetch_quest_drops($conn, $id_quest);
    $flow = dev_quest_build_flow($quest, $objectives_by_phase, $max_phase, $conversations, $ctx);

    return [
        'quest' => $quest,
        'objectives' => $objectives,
        'objectives_by_phase' => $objectives_by_phase,
        'max_phase' => $max_phase,
        'conversations' => $conversations,
        'quest_drops' => $quest_drops,
        'flow' => $flow,
        'lookup' => $ctx,
    ];
}

/**
 * @return array<string, mixed>
 */
function dev_quest_build_lookup_context(PDO $conn)
{
    $species = [];
    $stmt = $conn->query('SELECT id_species, species FROM species ORDER BY id_species');

    if ($stmt)
    {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $species[(int) $row['id_species']] = (string) $row['species'];
        }
    }

    $items = [];

    foreach (dev_npc_fetch_item_types($conn) as $row)
    {
        $items[(int) $row['id_item_type']] = (string) ($row['nome'] ?: $row['item_type']);
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
        'species' => $species,
        'items' => $items,
        'conversations' => $conversations,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function dev_quest_fetch_objectives(PDO $conn, $id_quest)
{
    $stmt = $conn->prepare('
        SELECT *
        FROM quest_objectives
        WHERE id_quest = :id_quest
        ORDER BY phase ASC, sort_order ASC, id_quest_objective ASC
    ');
    $stmt->execute([':id_quest' => (int) $id_quest]);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @param array<int, array<string, mixed>> $objectives
 * @return array<int, array<int, array<string, mixed>>>
 */
function dev_quest_group_objectives_by_phase(array $objectives)
{
    $by_phase = [];

    foreach ($objectives as $obj)
    {
        $phase = (int) $obj['phase'];
        $by_phase[$phase][] = $obj;
    }

    ksort($by_phase);

    return $by_phase;
}

/**
 * @param array<int, array<int, array<string, mixed>>> $objectives_by_phase
 */
function dev_quest_max_phase(array $objectives_by_phase)
{
    if (!$objectives_by_phase)
    {
        return 0;
    }

    return (int) max(array_keys($objectives_by_phase));
}

/**
 * @param array<int, array<string, mixed>> $objectives
 * @param array<string, mixed> $ctx
 * @return array<int, array<string, mixed>>
 */
function dev_quest_fetch_related_conversations(PDO $conn, $id_quest, array $objectives, array $ctx)
{
    $id_quest = (int) $id_quest;
    $conversation_ids = [];

    foreach ($objectives as $obj)
    {
        if (($obj['objective_type'] ?? '') === 'talk_npc' && (int) ($obj['target_ref'] ?? 0) > 0)
        {
            $conversation_ids[(int) $obj['target_ref']] = true;
        }
    }

    $stmt_req = $conn->prepare('
        SELECT cr.id_conversation
        FROM conversation_requirements cr
        JOIN requirements r ON r.id_requirement = cr.id_requirement
        WHERE (cr.ref_table = \'QUEST\' AND cr.id_ref = :id_quest)
           OR (
                r.requirement_type IN (
                    \'quest not started\',
                    \'quest started\',
                    \'quest ready to turn in\',
                    \'quest completed\'
                )
                AND cr.id_ref = :id_quest2
           )
    ');
    $stmt_req->execute([':id_quest' => $id_quest, ':id_quest2' => $id_quest]);

    while ($row = $stmt_req->fetch(PDO::FETCH_ASSOC))
    {
        $conversation_ids[(int) $row['id_conversation']] = true;
    }

    $stmt_cons = $conn->prepare('
        SELECT cc.id_conversation
        FROM conversation_consequences cc
        JOIN consequences co ON co.id_consequence = cc.id_consequence
        WHERE (cc.ref_table = \'QUEST\' AND cc.id_ref = :id_quest)
           OR (
                co.consequence_type IN (\'[start quest]\', \'[complete quest]\')
                AND cc.id_ref = :id_quest2
           )
    ');
    $stmt_cons->execute([':id_quest' => $id_quest, ':id_quest2' => $id_quest]);

    while ($row = $stmt_cons->fetch(PDO::FETCH_ASSOC))
    {
        $conversation_ids[(int) $row['id_conversation']] = true;
    }

    if (!$conversation_ids)
    {
        return [];
    }

    $ids_sql = implode(',', array_map('intval', array_keys($conversation_ids)));
    $conversations = [];

    $stmt = $conn->query("
        SELECT c.*, n.npc, n.id_zone
        FROM conversations c
        LEFT JOIN npcs n ON n.id_npc = c.id_npc
        WHERE c.id_conversation IN ($ids_sql)
        ORDER BY c.id_conversation ASC
    ");

    if ($stmt)
    {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];
            $conversations[$id_conv] = [
                'conversation' => $row,
                'requirements' => [],
                'consequences' => [],
                'dialogues' => [],
                'options_by_dialog' => [],
                'roles' => [],
                'quest_links' => [],
            ];
        }
    }

    if (!$conversations)
    {
        return [];
    }

    $stmt_req_rows = $conn->query("
        SELECT cr.*, r.requirement_type
        FROM conversation_requirements cr
        JOIN requirements r ON r.id_requirement = cr.id_requirement
        WHERE cr.id_conversation IN ($ids_sql)
        ORDER BY cr.id_conversation, cr.id_conversation_requirement
    ");

    if ($stmt_req_rows)
    {
        while ($row = $stmt_req_rows->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (!isset($conversations[$id_conv]))
            {
                continue;
            }

            $conversations[$id_conv]['requirements'][] = $row;
            dev_quest_tag_conversation_quest_link(
                $conversations[$id_conv],
                'requirement',
                $row,
                $id_quest
            );
        }
    }

    $stmt_cons_rows = $conn->query("
        SELECT cc.*, co.consequence_type,
               do.id_dialog, do.option_n, do.option_text, do.option_color
        FROM conversation_consequences cc
        JOIN consequences co ON co.id_consequence = cc.id_consequence
        LEFT JOIN dialogues_options do ON do.id_dialog_option = cc.id_option
        WHERE cc.id_conversation IN ($ids_sql)
        ORDER BY cc.id_conversation, cc.id_conversation_consequence
    ");

    if ($stmt_cons_rows)
    {
        while ($row = $stmt_cons_rows->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (!isset($conversations[$id_conv]))
            {
                continue;
            }

            $conversations[$id_conv]['consequences'][] = $row;
            dev_quest_tag_conversation_quest_link(
                $conversations[$id_conv],
                'consequence',
                $row,
                $id_quest
            );
        }
    }

    $stmt_dialogues = $conn->query("
        SELECT d.*
        FROM dialogues d
        WHERE d.id_conversation IN ($ids_sql)
        ORDER BY d.id_conversation, d.`order` ASC, d.id_dialog ASC
    ");

    if ($stmt_dialogues)
    {
        while ($row = $stmt_dialogues->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            if (isset($conversations[$id_conv]))
            {
                $conversations[$id_conv]['dialogues'][] = $row;
            }
        }
    }

    $stmt_options = $conn->query("
        SELECT o.*
        FROM dialogues_options o
        JOIN dialogues d ON d.id_dialog = o.id_dialog
        WHERE d.id_conversation IN ($ids_sql)
        ORDER BY d.id_conversation, d.`order`, o.option_n
    ");

    if ($stmt_options)
    {
        while ($row = $stmt_options->fetch(PDO::FETCH_ASSOC))
        {
            $id_dialog = (int) $row['id_dialog'];

            foreach ($conversations as $id_conv => $bundle)
            {
                foreach ($bundle['dialogues'] as $dialogue)
                {
                    if ((int) $dialogue['id_dialog'] === $id_dialog)
                    {
                        if (!isset($conversations[$id_conv]['options_by_dialog'][$id_dialog]))
                        {
                            $conversations[$id_conv]['options_by_dialog'][$id_dialog] = [];
                        }

                        $conversations[$id_conv]['options_by_dialog'][$id_dialog][] = $row;
                        break;
                    }
                }
            }
        }
    }

    $talk_objective_convs = [];

    foreach ($objectives as $obj)
    {
        if (($obj['objective_type'] ?? '') === 'talk_npc')
        {
            $talk_objective_convs[(int) ($obj['target_ref'] ?? 0)] = (int) $obj['phase'];
        }
    }

    foreach ($conversations as $id_conv => $bundle)
    {
        $roles = dev_quest_infer_conversation_roles($bundle, $id_quest);

        if (isset($talk_objective_convs[$id_conv]))
        {
            $roles[] = 'talk_objective';
        }

        $conversations[$id_conv]['roles'] = array_values(array_unique($roles));
    }

    return $conversations;
}

/**
 * @param array<string, mixed> $bundle
 * @param array<string, mixed> $row
 */
function dev_quest_tag_conversation_quest_link(array &$bundle, $kind, array $row, $id_quest)
{
    $id_quest = (int) $id_quest;
    $applies = false;
    $label = '';

    if ($kind === 'requirement')
    {
        $req_type = (string) ($row['requirement_type'] ?? '');

        if (($row['ref_table'] ?? '') === 'QUEST' && (int) ($row['id_ref'] ?? 0) === $id_quest)
        {
            $applies = true;
            $label = 'ref_table QUEST';
        }
        elseif (strpos($req_type, 'quest ') === 0 && (int) ($row['id_ref'] ?? 0) === $id_quest)
        {
            $applies = true;
            $label = $req_type;
        }
    }
    else
    {
        $cons_type = (string) ($row['consequence_type'] ?? '');

        if (($row['ref_table'] ?? '') === 'QUEST' && (int) ($row['id_ref'] ?? 0) === $id_quest)
        {
            $applies = true;
            $label = $cons_type !== '' ? $cons_type : 'ref_table QUEST';
        }
        elseif (in_array($cons_type, ['[start quest]', '[complete quest]'], true)
            && (int) ($row['id_ref'] ?? 0) === $id_quest)
        {
            $applies = true;
            $label = $cons_type;
        }
    }

    if ($applies)
    {
        $bundle['quest_links'][] = [
            'kind' => $kind,
            'label' => $label,
            'row' => $row,
        ];
    }
}

/**
 * @param array<string, mixed> $bundle
 * @return array<int, string>
 */
function dev_quest_infer_conversation_roles(array $bundle, $id_quest)
{
    $id_quest = (int) $id_quest;
    $roles = [];

    foreach ($bundle['requirements'] as $row)
    {
        if ((int) ($row['id_ref'] ?? 0) !== $id_quest)
        {
            continue;
        }

        $type = (string) ($row['requirement_type'] ?? '');

        if ($type === 'quest not started')
        {
            $roles[] = 'accept';
        }
        elseif ($type === 'quest ready to turn in')
        {
            $roles[] = 'turn_in';
        }
        elseif ($type === 'quest started')
        {
            $roles[] = 'in_progress';
        }
        elseif ($type === 'quest completed')
        {
            $roles[] = 'completed_gate';
        }
    }

    foreach ($bundle['consequences'] as $row)
    {
        if ((int) ($row['id_ref'] ?? 0) !== $id_quest)
        {
            continue;
        }

        $type = (string) ($row['consequence_type'] ?? '');

        if ($type === '[start quest]')
        {
            $roles[] = 'accept';
        }
        elseif ($type === '[complete quest]')
        {
            $roles[] = 'turn_in';
        }
    }

    return $roles;
}

/**
 * @return array<int, array<string, mixed>>
 */
function dev_quest_fetch_quest_drops(PDO $conn, $id_quest)
{
    $stmt = $conn->prepare('
        SELECT D.*, S.species, IT.nome AS item_name, E.element
        FROM wild_animal_drop_types D
        LEFT JOIN species S ON S.id_species = D.id_species
        LEFT JOIN item_types IT ON IT.id_item_type = D.id_item_type
        LEFT JOIN elements E ON E.id_element = D.id_element
        WHERE D.id_quest_required = :id_quest
        ORDER BY D.id_species, D.id_wild_animal_drop_type
    ');
    $stmt->execute([':id_quest' => (int) $id_quest]);

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Build ordered flow columns for the diagram.
 *
 * @param array<string, mixed> $quest
 * @param array<int, array<int, array<string, mixed>>> $objectives_by_phase
 * @param array<int, array<string, mixed>> $conversations
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>
 */
function dev_quest_build_flow(array $quest, array $objectives_by_phase, $max_phase, array $conversations, array $ctx)
{
    $id_quest = (int) $quest['id_quest'];
    $columns = [];
    $accept_convs = dev_quest_filter_conversations_by_role($conversations, 'accept');
    $turn_in_convs = dev_quest_filter_conversations_by_role($conversations, 'turn_in');

    if ($accept_convs)
    {
        $columns[] = [
            'kind' => 'accept',
            'title' => 'Accept',
            'subtitle' => 'quest not started',
            'conversations' => $accept_convs,
            'objectives' => [],
        ];
    }

    foreach ($objectives_by_phase as $phase => $objectives)
    {
        $phase = (int) $phase;
        $phase_talk_convs = [];

        foreach ($objectives as $obj)
        {
            if (($obj['objective_type'] ?? '') !== 'talk_npc')
            {
                continue;
            }

            $id_conv = (int) ($obj['target_ref'] ?? 0);

            if ($id_conv > 0 && isset($conversations[$id_conv]))
            {
                $phase_talk_convs[$id_conv] = $conversations[$id_conv];
            }
        }

        $columns[] = [
            'kind' => 'phase',
            'phase' => $phase,
            'title' => 'Phase ' . $phase,
            'subtitle' => dev_quest_phase_subtitle($objectives, $ctx),
            'conversations' => $phase_talk_convs,
            'objectives' => array_map(function ($obj) use ($ctx)
            {
                return dev_quest_objective_view($obj, $ctx);
            }, $objectives),
        ];
    }

    if ($turn_in_convs)
    {
        $columns[] = [
            'kind' => 'turn_in',
            'title' => 'Turn in',
            'subtitle' => 'quest ready to turn in',
            'conversations' => $turn_in_convs,
            'objectives' => [],
        ];
    }

    $columns[] = [
        'kind' => 'complete',
        'title' => 'Completed',
        'subtitle' => 'flg_completed = S',
        'conversations' => dev_quest_filter_conversations_by_role($conversations, 'completed_gate'),
        'objectives' => [],
    ];

    return [
        'columns' => $columns,
        'max_phase' => (int) $max_phase,
        'awaiting_turn_in_phase' => (int) $max_phase + 1,
    ];
}

/**
 * @param array<int, array<string, mixed>> $conversations
 * @return array<int, array<string, mixed>>
 */
function dev_quest_filter_conversations_by_role(array $conversations, $role)
{
    $out = [];

    foreach ($conversations as $id_conv => $bundle)
    {
        if (in_array($role, $bundle['roles'] ?? [], true))
        {
            $out[$id_conv] = $bundle;
        }
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $objectives
 * @param array<string, mixed> $ctx
 */
function dev_quest_phase_subtitle(array $objectives, array $ctx)
{
    $parts = [];

    foreach ($objectives as $obj)
    {
        $view = dev_quest_objective_view($obj, $ctx);
        $parts[] = $view['short_label'];
    }

    return implode(' + ', $parts);
}

/**
 * @param array<string, mixed> $obj
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>
 */
function dev_quest_objective_view(array $obj, array $ctx)
{
    $type = (string) ($obj['objective_type'] ?? '');
    $target_ref = (int) ($obj['target_ref'] ?? 0);
    $count = (int) ($obj['target_count'] ?? 1);
    $target_label = '';
    $short_label = $type;

    if ($type === 'kill_species' && isset($ctx['species'][$target_ref]))
    {
        $target_label = $ctx['species'][$target_ref] . ' (#' . $target_ref . ')';
        $short_label = 'Kill ×' . $count;
    }
    elseif ($type === 'collect_item' && isset($ctx['items'][$target_ref]))
    {
        $target_label = $ctx['items'][$target_ref] . ' (#' . $target_ref . ')';
        $short_label = 'Collect ×' . $count;
    }
    elseif ($type === 'talk_npc' && isset($ctx['conversations'][$target_ref]))
    {
        $conv = $ctx['conversations'][$target_ref];
        $target_label = '#' . $target_ref . ' ' . (string) ($conv['title'] ?? '');
        $short_label = 'Talk';
    }
    elseif ($type === 'reach_level')
    {
        $target_label = 'Level ' . $count;
        $short_label = 'Lvl ' . $count;
    }
    elseif ($target_ref > 0)
    {
        $target_label = 'ref #' . $target_ref;
    }

    $description = (string) ($obj['description'] ?? '');

    return [
        'objective' => $obj,
        'type' => $type,
        'target_ref' => $target_ref,
        'target_count' => $count,
        'target_label' => $target_label,
        'short_label' => $short_label,
        'description' => $description,
    ];
}

/**
 * @param array<string, mixed> $bundle
 */
function dev_quest_conversation_role_badges(array $bundle)
{
    $map = [
        'accept' => ['Accept', 'bg-success'],
        'turn_in' => ['Turn in', 'bg-warning text-dark'],
        'talk_objective' => ['Talk obj', 'bg-info text-dark'],
        'in_progress' => ['In progress', 'bg-primary'],
        'completed_gate' => ['Completed gate', 'bg-secondary'],
    ];

    $badges = [];

    foreach ($bundle['roles'] ?? [] as $role)
    {
        if (isset($map[$role]))
        {
            $badges[] = $map[$role];
        }
    }

    return $badges;
}

function dev_quest_page_url(array $extra = [])
{
    return dev_admin_page_url('dev_quest.php', $extra);
}

function dev_quest_npc_edit_url($id_npc, $id_conversation = 0)
{
    $params = ['tab' => 'conv'];

    if ($id_conversation > 0)
    {
        $params['edit'] = 'conversation';
        $params['id'] = (int) $id_conversation;
    }

    return dev_admin_page_url('dev_npcs.php', $params);
}

/**
 * Compact conversation card inside the flow diagram.
 *
 * @param array<string, mixed> $bundle
 */
function dev_quest_render_flow_conversation(array $bundle, $compact)
{
    $conv = $bundle['conversation'];
    $id_conv = (int) $conv['id_conversation'];
    ?>
    <div class="flow-conv<?php echo $compact ? ' mt-1' : ''; ?>">
        <div class="flow-conv-title">#<?php echo $id_conv; ?> <?php echo dev_admin_h((string) $conv['title']); ?></div>
        <div class="flow-conv-npc"><?php echo dev_admin_h((string) ($conv['npc'] ?? '')); ?></div>
        <?php if ($bundle['quest_links']): ?>
        <div class="flow-conv-links">
            <?php foreach ($bundle['quest_links'] as $link): ?>
            <code><?php echo dev_admin_h($link['label']); ?></code>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php
        if (!$compact)
        {
            foreach ($bundle['consequences'] as $cons)
            {
                if (!in_array((string) ($cons['consequence_type'] ?? ''), ['[start quest]', '[complete quest]'], true))
                {
                    continue;
                }

                $color = 'green';
                $text = (string) ($cons['option_text'] ?? $cons['consequence_type']);
                ?>
                <div class="flow-option flow-option-<?php echo dev_admin_h($color); ?>">
                    → <?php echo dev_admin_h($text); ?>
                    <span class="meta">(<?php echo dev_admin_h((string) $cons['consequence_type']); ?>)</span>
                </div>
                <?php
            }
        }
        ?>
        <a class="meta" href="<?php echo dev_admin_h(dev_quest_npc_edit_url((int) $conv['id_npc'], $id_conv)); ?>">edit ↗</a>
    </div>
    <?php
}
