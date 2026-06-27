<?php

function dev_npc_fetch_zones(PDO $conn)
{
    $stmt = $conn->query('SELECT id_zone, scene_name FROM zones ORDER BY id_zone');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_requirements(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_requirement, requirement_type, id_ref, ref_table, min, max, descrizione
        FROM requirements
        ORDER BY id_requirement
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_consequences(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_consequence, consequence_type, id_ref, ref_table, num
        FROM consequences
        ORDER BY id_consequence
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function dev_npc_fetch_tree(PDO $conn)
{
    $tree = [];

    $stmt_npcs = $conn->query('SELECT * FROM npcs ORDER BY id_npc ASC');

    if (!$stmt_npcs)
    {
        return [];
    }

    while ($npc = $stmt_npcs->fetch(PDO::FETCH_ASSOC))
    {
        $id_npc = (int) $npc['id_npc'];
        $tree[$id_npc] = $npc;
        $tree[$id_npc]['requirements'] = [];
        $tree[$id_npc]['conversations'] = [];
        $tree[$id_npc]['quests'] = [];
    }

    if (!$tree)
    {
        return [];
    }

    $stmt_nr = $conn->query('
        SELECT NR.id_npc, NR.id_npc_requirement, R.*
        FROM npc_requirements NR
        JOIN requirements R ON R.id_requirement = NR.id_requirement
        ORDER BY NR.id_npc, NR.id_npc_requirement
    ');

    if ($stmt_nr)
    {
        while ($row = $stmt_nr->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $row['id_npc'];

            if (isset($tree[$id_npc]))
            {
                $tree[$id_npc]['requirements'][] = $row;
            }
        }
    }

    $stmt_quests = $conn->query('SELECT * FROM quests ORDER BY id_quest ASC');

    if ($stmt_quests)
    {
        while ($quest = $stmt_quests->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $quest['id_starter_npc'];

            if (isset($tree[$id_npc]))
            {
                $quest['requirements'] = [];
                $tree[$id_npc]['quests'][(int) $quest['id_quest']] = $quest;
            }
        }
    }

    $stmt_qr = $conn->query('
        SELECT QR.id_quest, QR.id_quest_requirement, R.*
        FROM quest_requirements QR
        JOIN requirements R ON R.id_requirement = QR.id_requirement
        ORDER BY QR.id_quest
    ');

    if ($stmt_qr)
    {
        while ($row = $stmt_qr->fetch(PDO::FETCH_ASSOC))
        {
            $id_quest = (int) $row['id_quest'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['quests'][$id_quest]))
                {
                    $tree[$id_npc]['quests'][$id_quest]['requirements'][] = $row;
                    break;
                }
            }
        }
    }

    $stmt_conv = $conn->query('
        SELECT * FROM conversations ORDER BY id_npc ASC, id_conversation ASC
    ');

    if ($stmt_conv)
    {
        while ($conv = $stmt_conv->fetch(PDO::FETCH_ASSOC))
        {
            $id_npc = (int) $conv['id_npc'];

            if (!isset($tree[$id_npc]))
            {
                continue;
            }

            $id_conv = (int) $conv['id_conversation'];
            $conv['requirements'] = [];
            $conv['dialogues'] = [];
            $tree[$id_npc]['conversations'][$id_conv] = $conv;
        }
    }

    $stmt_cr = $conn->query('
        SELECT CR.id_conversation, CR.id_conversation_requirement, R.*
        FROM conversation_requirements CR
        JOIN requirements R ON R.id_requirement = CR.id_requirement
        ORDER BY CR.id_conversation
    ');

    if ($stmt_cr)
    {
        while ($row = $stmt_cr->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['conversations'][$id_conv]))
                {
                    $tree[$id_npc]['conversations'][$id_conv]['requirements'][] = $row;
                    break;
                }
            }
        }
    }

    $stmt_dlg = $conn->query('
        SELECT * FROM dialogues ORDER BY id_conversation ASC, `order` ASC, id_dialog ASC
    ');

    if ($stmt_dlg)
    {
        while ($dlg = $stmt_dlg->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $dlg['id_conversation'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (isset($npc_data['conversations'][$id_conv]))
                {
                    $id_dialog = (int) $dlg['id_dialog'];
                    $dlg['options'] = [];
                    $dlg['consequences'] = [];
                    $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog] = $dlg;
                    break;
                }
            }
        }
    }

    $stmt_opt = $conn->query('
        SELECT * FROM dialogues_options ORDER BY id_dialog ASC, option_n ASC, id_dialog_option ASC
    ');

    if ($stmt_opt)
    {
        while ($opt = $stmt_opt->fetch(PDO::FETCH_ASSOC))
        {
            $id_dialog = (int) $opt['id_dialog'];

            foreach ($tree as $id_npc => $npc_data)
            {
                foreach ($npc_data['conversations'] as $id_conv => $conv)
                {
                    if (isset($conv['dialogues'][$id_dialog]))
                    {
                        $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog]['options'][] = $opt;
                        break 2;
                    }
                }
            }
        }
    }

    $stmt_cc = $conn->query('
        SELECT CC.id_conversation_consequence, CC.id_conversation, CC.id_option, CC.id_consequence,
               C.consequence_type, C.id_ref, C.ref_table, C.num
        FROM conversation_consequences CC
        JOIN consequences C ON C.id_consequence = CC.id_consequence
        ORDER BY CC.id_conversation, CC.id_option
    ');

    if ($stmt_cc)
    {
        while ($row = $stmt_cc->fetch(PDO::FETCH_ASSOC))
        {
            $id_conv = (int) $row['id_conversation'];
            $id_option = (int) $row['id_option'];

            foreach ($tree as $id_npc => $npc_data)
            {
                if (!isset($npc_data['conversations'][$id_conv]))
                {
                    continue;
                }

                foreach ($npc_data['conversations'][$id_conv]['dialogues'] as $id_dialog => $dlg)
                {
                    foreach ($dlg['options'] as $opt)
                    {
                        if ((int) $opt['id_dialog_option'] === $id_option)
                        {
                            $tree[$id_npc]['conversations'][$id_conv]['dialogues'][$id_dialog]['consequences'][] = $row;
                            break 3;
                        }
                    }
                }
            }
        }
    }

    return $tree;
}

function dev_npc_post_int(array $post, $key, $default = 0)
{
    return isset($post[$key]) ? (int) $post[$key] : $default;
}

function dev_npc_post_str(array $post, $key, $max_len = 500)
{
    $value = isset($post[$key]) ? trim((string) $post[$key]) : '';

    return mb_substr($value, 0, $max_len);
}

function dev_npc_post_yn(array $post, $key, $default = 'N')
{
    $value = dev_npc_post_str($post, $key, 1);

    return ($value === 'S') ? 'S' : $default;
}

function dev_npc_handle_post(PDO $conn, array $post)
{
    $action = dev_npc_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'add_npc':
                $stmt = $conn->prepare('
                    INSERT INTO npcs (npc, type, id_zone, posx, posy, posz, wander_range, direction, sight_distance, npc_type_prefab)
                    VALUES (:npc, :type, :id_zone, :posx, :posy, :posz, :wander_range, :direction, :sight_distance, :npc_type_prefab)
                ');
                $stmt->execute([
                    ':npc' => dev_npc_post_str($post, 'npc', 100),
                    ':type' => dev_npc_post_str($post, 'type', 100),
                    ':id_zone' => dev_npc_post_int($post, 'id_zone', 1000),
                    ':posx' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posx', 20)),
                    ':posy' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posy', 20)),
                    ':posz' => (float) str_replace(',', '.', dev_npc_post_str($post, 'posz', 20)),
                    ':wander_range' => dev_npc_post_int($post, 'wander_range'),
                    ':direction' => dev_npc_post_str($post, 'direction', 1) ?: 'D',
                    ':sight_distance' => dev_npc_post_int($post, 'sight_distance'),
                    ':npc_type_prefab' => dev_npc_post_str($post, 'npc_type_prefab', 100) ?: 'trader'
                ]);

                return ['ok' => true, 'message' => 'NPC created (id ' . $conn->lastInsertId() . ').'];

            case 'add_conversation':
                $stmt = $conn->prepare('
                    INSERT INTO conversations (id_npc, visible, title, title_it, title_pt, flg_register)
                    VALUES (:id_npc, :visible, :title, :title_it, :title_pt, :flg_register)
                ');
                $stmt->execute([
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':visible' => dev_npc_post_yn($post, 'visible', 'S'),
                    ':title' => dev_npc_post_str($post, 'title', 200),
                    ':title_it' => dev_npc_post_str($post, 'title_it', 200),
                    ':title_pt' => dev_npc_post_str($post, 'title_pt', 200),
                    ':flg_register' => dev_npc_post_yn($post, 'flg_register', 'N')
                ]);

                return ['ok' => true, 'message' => 'Conversation created (id ' . $conn->lastInsertId() . ').'];

            case 'add_dialogue':
                $stmt = $conn->prepare('
                    INSERT INTO dialogues (id_conversation, `order`, flg_last, flg_options, dialog, dialog_it, dialog_pt)
                    VALUES (:id_conversation, :ord, :flg_last, :flg_options, :dialog, :dialog_it, :dialog_pt)
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':ord' => dev_npc_post_int($post, 'order', 1),
                    ':flg_last' => dev_npc_post_yn($post, 'flg_last', 'N'),
                    ':flg_options' => dev_npc_post_yn($post, 'flg_options', 'N'),
                    ':dialog' => dev_npc_post_str($post, 'dialog', 500),
                    ':dialog_it' => dev_npc_post_str($post, 'dialog_it', 500),
                    ':dialog_pt' => dev_npc_post_str($post, 'dialog_pt', 500)
                ]);

                return ['ok' => true, 'message' => 'Dialogue created (id ' . $conn->lastInsertId() . ').'];

            case 'add_dialog_option':
                $stmt = $conn->prepare('
                    INSERT INTO dialogues_options (id_dialog, option_n, option_color, option_text, option_text_it, option_text_pt)
                    VALUES (:id_dialog, :option_n, :option_color, :option_text, :option_text_it, :option_text_pt)
                ');
                $stmt->execute([
                    ':id_dialog' => dev_npc_post_int($post, 'id_dialog'),
                    ':option_n' => dev_npc_post_int($post, 'option_n', 1),
                    ':option_color' => dev_npc_post_str($post, 'option_color', 50) ?: 'green',
                    ':option_text' => dev_npc_post_str($post, 'option_text', 200),
                    ':option_text_it' => dev_npc_post_str($post, 'option_text_it', 200),
                    ':option_text_pt' => dev_npc_post_str($post, 'option_text_pt', 200)
                ]);

                return ['ok' => true, 'message' => 'Dialog option created (id ' . $conn->lastInsertId() . ').'];

            case 'add_quest':
                $stmt = $conn->prepare('
                    INSERT INTO quests (id_starter_npc, quest, repeatable, quest_type, lvl_min, lvl_max, ids_quests_required)
                    VALUES (:id_starter_npc, :quest, :repeatable, :quest_type, :lvl_min, :lvl_max, :ids_quests_required)
                ');
                $stmt->execute([
                    ':id_starter_npc' => dev_npc_post_int($post, 'id_starter_npc'),
                    ':quest' => dev_npc_post_str($post, 'quest', 200),
                    ':repeatable' => dev_npc_post_yn($post, 'repeatable', 'N'),
                    ':quest_type' => dev_npc_post_str($post, 'quest_type', 100),
                    ':lvl_min' => dev_npc_post_int($post, 'lvl_min'),
                    ':lvl_max' => dev_npc_post_int($post, 'lvl_max', 100),
                    ':ids_quests_required' => dev_npc_post_str($post, 'ids_quests_required', 100) ?: '-1,-1'
                ]);

                return ['ok' => true, 'message' => 'Quest created (id ' . $conn->lastInsertId() . ').'];

            case 'add_requirement':
                $stmt = $conn->prepare('
                    INSERT INTO requirements (requirement_type, id_ref, ref_table, min, max, descrizione)
                    VALUES (:requirement_type, :id_ref, :ref_table, :min, :max, :descrizione)
                ');
                $stmt->execute([
                    ':requirement_type' => dev_npc_post_str($post, 'requirement_type', 100),
                    ':id_ref' => dev_npc_post_int($post, 'id_ref'),
                    ':ref_table' => dev_npc_post_str($post, 'ref_table', 100),
                    ':min' => dev_npc_post_int($post, 'min'),
                    ':max' => dev_npc_post_int($post, 'max', 9999),
                    ':descrizione' => dev_npc_post_str($post, 'descrizione', 100)
                ]);

                return ['ok' => true, 'message' => 'Requirement created (id ' . $conn->lastInsertId() . ').'];

            case 'link_npc_requirement':
                $stmt = $conn->prepare('
                    INSERT INTO npc_requirements (id_npc, id_requirement)
                    VALUES (:id_npc, :id_requirement)
                ');
                $stmt->execute([
                    ':id_npc' => dev_npc_post_int($post, 'id_npc'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement')
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to NPC.'];

            case 'link_conversation_requirement':
                $stmt = $conn->prepare('
                    INSERT INTO conversation_requirements (id_conversation, id_requirement)
                    VALUES (:id_conversation, :id_requirement)
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement')
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to conversation.'];

            case 'link_quest_requirement':
                $stmt = $conn->prepare('
                    INSERT INTO quest_requirements (id_quest, id_requirement)
                    VALUES (:id_quest, :id_requirement)
                ');
                $stmt->execute([
                    ':id_quest' => dev_npc_post_int($post, 'id_quest'),
                    ':id_requirement' => dev_npc_post_int($post, 'id_requirement')
                ]);

                return ['ok' => true, 'message' => 'Requirement linked to quest.'];

            case 'add_consequence':
                $stmt = $conn->prepare('
                    INSERT INTO consequences (consequence_type, id_ref, ref_table, num)
                    VALUES (:consequence_type, :id_ref, :ref_table, :num)
                ');
                $stmt->execute([
                    ':consequence_type' => dev_npc_post_str($post, 'consequence_type', 100),
                    ':id_ref' => dev_npc_post_int($post, 'id_ref'),
                    ':ref_table' => dev_npc_post_str($post, 'ref_table', 100),
                    ':num' => dev_npc_post_int($post, 'num', 1)
                ]);

                return ['ok' => true, 'message' => 'Consequence created (id ' . $conn->lastInsertId() . ').'];

            case 'link_conversation_consequence':
                $stmt = $conn->prepare('
                    INSERT INTO conversation_consequences (id_conversation, id_option, id_consequence)
                    VALUES (:id_conversation, :id_option, :id_consequence)
                ');
                $stmt->execute([
                    ':id_conversation' => dev_npc_post_int($post, 'id_conversation'),
                    ':id_option' => dev_npc_post_int($post, 'id_option'),
                    ':id_consequence' => dev_npc_post_int($post, 'id_consequence')
                ]);

                return ['ok' => true, 'message' => 'Consequence linked to conversation option.'];

            default:
                return ['ok' => false, 'message' => 'Unknown action.'];
        }
    }
    catch (PDOException $e)
    {
        error_log('[dev_npc_content] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

function dev_npc_flat_conversations(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            $list[] = [
                'id_conversation' => $id_conv,
                'id_npc' => $id_npc,
                'npc' => $npc['npc'],
                'title' => $conv['title']
            ];
        }
    }

    return $list;
}

function dev_npc_flat_dialogues(array $tree)
{
    $list = [];

    foreach ($tree as $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            foreach ($conv['dialogues'] as $id_dialog => $dlg)
            {
                $list[] = [
                    'id_dialog' => $id_dialog,
                    'id_conversation' => $id_conv,
                    'title' => $conv['title'],
                    'order' => $dlg['order'],
                    'dialog' => $dlg['dialog']
                ];
            }
        }
    }

    return $list;
}

function dev_npc_flat_options(array $tree)
{
    $list = [];

    foreach ($tree as $npc)
    {
        foreach ($npc['conversations'] as $id_conv => $conv)
        {
            foreach ($conv['dialogues'] as $id_dialog => $dlg)
            {
                foreach ($dlg['options'] as $opt)
                {
                    $list[] = [
                        'id_dialog_option' => (int) $opt['id_dialog_option'],
                        'id_dialog' => $id_dialog,
                        'id_conversation' => $id_conv,
                        'option_text' => $opt['option_text'],
                        'conversation_title' => $conv['title']
                    ];
                }
            }
        }
    }

    return $list;
}

function dev_npc_flat_quests(array $tree)
{
    $list = [];

    foreach ($tree as $id_npc => $npc)
    {
        foreach ($npc['quests'] as $id_quest => $quest)
        {
            $list[] = [
                'id_quest' => $id_quest,
                'id_starter_npc' => $id_npc,
                'npc' => $npc['npc'],
                'quest' => $quest['quest']
            ];
        }
    }

    return $list;
}
