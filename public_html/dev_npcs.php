<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_npc_content.php';

dev_admin_require_auth();

$flash = '';
$flash_ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $result = dev_npc_handle_post($conn, $_POST);
    $flash_ok = $result['ok'];
    $flash = $result['message'];

    $redirect = ['msg' => $flash, 'ok' => $flash_ok ? '1' : '0'];

    if (isset($_SESSION['dev_npc_preview_lang']))
    {
        $redirect['lang'] = dev_npc_resolve_preview_lang($_SESSION['dev_npc_preview_lang']);
    }

    $posted_action = isset($_POST['action']) ? (string) $_POST['action'] : '';

    if ($posted_action === 'attach_requirement' || $posted_action === 'attach_consequence')
    {
        $redirect['tab'] = 'attach';
    }

    if (isset($result['redirect_edit'], $result['redirect_id']) && $result['redirect_edit'] !== '' && (int) $result['redirect_id'] > 0)
    {
        $redirect['tab'] = 'req';
        $redirect['edit'] = (string) $result['redirect_edit'];
        $redirect['id'] = (int) $result['redirect_id'];
    }

    header('Location: ' . dev_admin_url($redirect));
    exit;
}

if (isset($_GET['msg']))
{
    $flash = (string) $_GET['msg'];
    $flash_ok = !isset($_GET['ok']) || $_GET['ok'] === '1';
}

$tree = dev_npc_fetch_tree($conn);
$zones = dev_npc_fetch_zones($conn);
$requirements = dev_npc_fetch_requirements($conn);
$consequences = dev_npc_fetch_consequences($conn);
$flat_conversations = dev_npc_flat_conversations($tree);
$flat_dialogues = dev_npc_flat_dialogues($tree);
$flat_options = dev_npc_flat_options($tree);
$flat_quests = dev_npc_flat_quests($tree);
$item_types = dev_npc_fetch_item_types($conn);
$shops = dev_npc_fetch_shops($conn);
$player_classes = dev_npc_fetch_player_classes($conn);
$buff_definitions = dev_npc_fetch_buff_definitions($conn);
$species_list = dev_npc_fetch_species($conn);
$objective_types = dev_npc_objective_types();
$requirement_ref_tables = dev_npc_requirement_ref_tables();
$requirement_types = dev_npc_requirement_types();
$preview_lang = dev_npc_get_preview_lang();
$req_ref_field_ctx = [
    'requirement_ref_tables' => $requirement_ref_tables,
    'item_types' => $item_types,
    'flat_conversations' => $flat_conversations,
    'flat_quests' => $flat_quests,
    'player_classes' => $player_classes,
    'preview_lang' => $preview_lang
];
$token = dev_admin_token();
$preview_languages = dev_npc_preview_languages();
$edit_type = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit_item = null;
$edit_link_context = null;

if ($edit_type !== '' && $edit_id > 0)
{
    if ($edit_type === 'npc' && isset($tree[$edit_id]))
    {
        $edit_item = $tree[$edit_id];
    }
    elseif ($edit_type === 'conversation')
    {
        foreach ($flat_conversations as $row)
        {
            if ((int) $row['id_conversation'] === $edit_id)
            {
                foreach ($tree as $npc_data)
                {
                    if (isset($npc_data['conversations'][$edit_id]))
                    {
                        $edit_item = $npc_data['conversations'][$edit_id];
                        break;
                    }
                }
                break;
            }
        }
    }
    elseif ($edit_type === 'dialogue')
    {
        foreach ($tree as $npc_data)
        {
            foreach ($npc_data['conversations'] as $conv)
            {
                if (isset($conv['dialogues'][$edit_id]))
                {
                    $edit_item = $conv['dialogues'][$edit_id];
                    break 2;
                }
            }
        }
    }
    elseif ($edit_type === 'option')
    {
        foreach ($tree as $npc_data)
        {
            foreach ($npc_data['conversations'] as $conv)
            {
                foreach ($conv['dialogues'] as $dlg)
                {
                    foreach ($dlg['options'] as $opt)
                    {
                        if ((int) $opt['id_dialog_option'] === $edit_id)
                        {
                            $edit_item = $opt;
                            break 4;
                        }
                    }
                }
            }
        }
    }
    elseif ($edit_type === 'quest')
    {
        foreach ($tree as $npc_data)
        {
            if (isset($npc_data['quests'][$edit_id]))
            {
                $edit_item = $npc_data['quests'][$edit_id];
                break;
            }
        }
    }
    elseif ($edit_type === 'requirement')
    {
        foreach ($requirements as $req)
        {
            if ((int) $req['id_requirement'] === $edit_id)
            {
                $edit_item = $req;
                break;
            }
        }
    }
    elseif ($edit_type === 'npc_requirement')
    {
        $edit_link_context = dev_npc_find_requirement_link($tree, 'npc', $edit_id);

        if ($edit_link_context)
        {
            $edit_item = $edit_link_context['link'];
        }
    }
    elseif ($edit_type === 'conversation_requirement')
    {
        $edit_link_context = dev_npc_find_requirement_link($tree, 'conversation', $edit_id);

        if ($edit_link_context)
        {
            $edit_item = $edit_link_context['link'];
        }
    }
    elseif ($edit_type === 'quest_requirement')
    {
        $edit_link_context = dev_npc_find_requirement_link($tree, 'quest', $edit_id);

        if ($edit_link_context)
        {
            $edit_item = $edit_link_context['link'];
        }
    }
    elseif ($edit_type === 'quest_objective')
    {
        foreach ($tree as $npc_data)
        {
            foreach ($npc_data['quests'] as $quest)
            {
                foreach ($quest['objectives'] as $obj)
                {
                    if ((int) $obj['id_quest_objective'] === $edit_id)
                    {
                        $edit_item = $obj;
                        break 3;
                    }
                }
            }
        }
    }
    elseif ($edit_type === 'consequence')
    {
        foreach ($consequences as $cons)
        {
            if ((int) $cons['id_consequence'] === $edit_id)
            {
                $edit_item = $cons;
                break;
            }
        }
    }
    elseif ($edit_type === 'conversation_consequence')
    {
        $edit_link_context = dev_npc_find_consequence_link($tree, $edit_id);

        if ($edit_link_context)
        {
            $edit_item = $edit_link_context['link'];
        }
    }

    if ($edit_item === null)
    {
        $edit_type = '';
        $edit_id = 0;
    }
}

$attach_prefill = dev_npc_parse_attach_prefill($_GET);
$attach_tab_active = (isset($_GET['tab']) && $_GET['tab'] === 'attach') || $attach_prefill['attach'] !== '';
$qobj_quest_prefill = isset($_GET['qobj_quest']) ? (int) $_GET['qobj_quest'] : 0;
$is_edit_qobj = ($edit_type === 'quest_objective' && is_array($edit_item));
$quest_tab_active = $qobj_quest_prefill > 0 || $is_edit_qobj || ($edit_type === 'quest' && is_array($edit_item));
$req_tab_active = (isset($_GET['tab']) && $_GET['tab'] === 'req')
    || in_array($edit_type, ['requirement', 'npc_requirement', 'conversation_requirement', 'quest_requirement'], true);
$cons_tab_active = (isset($_GET['tab']) && $_GET['tab'] === 'cons')
    || in_array($edit_type, ['consequence', 'conversation_consequence'], true);
$default_form_tab_active = !$attach_tab_active && !$req_tab_active && !$cons_tab_active && !$quest_tab_active;
$is_edit_req_link = in_array($edit_type, ['npc_requirement', 'conversation_requirement', 'quest_requirement'], true)
    && is_array($edit_item)
    && is_array($edit_link_context);
$is_edit_cons_link = $edit_type === 'conversation_consequence'
    && is_array($edit_item)
    && is_array($edit_link_context);
$consequence_ref_tables = dev_npc_consequence_ref_tables();
$consequence_types = dev_npc_consequence_types();
$cons_ref_field_ctx = [
    'consequence_ref_tables' => $consequence_ref_tables,
    'item_types' => $item_types,
    'player_classes' => $player_classes,
    'buff_definitions' => $buff_definitions,
    'flat_quests' => $flat_quests,
    'shops' => $shops
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — NPC / Dialog Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .tree-npc { border-left: 3px solid #4dabf7; margin-bottom: 1.25rem; padding-left: .75rem; }
        .tree-conv { border-left: 3px solid #69db7c; margin: .75rem 0 .75rem .75rem; padding-left: .75rem; }
        .tree-dlg { border-left: 3px solid #ffd43b; margin: .5rem 0 .5rem .75rem; padding-left: .75rem; }
        .tree-opt { border-left: 3px solid #ff922b; margin: .35rem 0 .35rem .75rem; padding-left: .75rem; }
        .meta { color: #94a3b8; font-size: .85rem; }
        .badge-req { background: #495057; }
        .badge-cons { background: #862e9c; }
        .schema-box { font-size: .85rem; color: #adb5bd; }
        details summary { cursor: pointer; }
        .dev-attach-section { border: 1px solid #495057; border-radius: 6px; padding: .75rem; margin-bottom: 1rem; background: rgba(0,0,0,.15); }
        .dev-attach-section h6 { color: #94a3b8; margin-bottom: .75rem; }
        .dev-btn-mini { padding: 0 .35rem; font-size: .7rem; line-height: 1.4; vertical-align: middle; }
        .dev-req-list { list-style: none; padding-left: 0; margin: .35rem 0 .5rem 0; }
        .dev-req-item {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .35rem .6rem;
            padding: .4rem .55rem;
            margin-bottom: .3rem;
            background: rgba(73, 80, 87, .28);
            border-radius: 5px;
            font-size: .84rem;
            line-height: 1.35;
        }
        .dev-req-catalog { font-weight: 600; color: #e9ecef; }
        .dev-req-range {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .78rem;
            color: #ffd43b;
            background: rgba(0, 0, 0, .2);
            padding: .1rem .35rem;
            border-radius: 3px;
        }
        .dev-req-note { color: #adb5bd; font-style: italic; flex: 1 1 8rem; }
        .dev-req-actions { margin-left: auto; white-space: nowrap; }
        .dev-req-delete-form { display: inline; margin: 0; padding: 0; vertical-align: middle; }

        .dev-lang-switch {
            display: inline-flex;
            border: 1px solid #495057;
            border-radius: 6px;
            overflow: hidden;
        }

        .dev-lang-btn {
            border: none;
            background: #243044;
            color: #adb5bd;
            padding: 4px 12px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
        }

        .dev-lang-btn + .dev-lang-btn {
            border-left: 1px solid #495057;
        }

        .dev-lang-btn:hover {
            background: #2d3a4d;
            color: #fff;
        }

        .dev-lang-btn.active {
            background: #364fc7;
            color: #fff;
        }

        strong { color: #4dabf7; }
        label { color: #4dabf7; }
        li { color: #4dabf7; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">NPC Content Dev</h1>
            <p class="meta mb-0">Token-protected. Not linked from the game client.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="dev-lang-switch" role="group" aria-label="Preview language">
                <?php foreach ($preview_languages as $lang_code => $lang_label): ?>
                <button type="button"
                        class="dev-lang-btn<?php echo $preview_lang === $lang_code ? ' active' : ''; ?>"
                        data-lang="<?php echo dev_admin_h($lang_code); ?>"><?php echo dev_admin_h($lang_label); ?></button>
                <?php endforeach; ?>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>">Species / abilities</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npc.php')); ?>">NPC interactions</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_quest.php')); ?>">Quest flow</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_shops.php')); ?>">Shops editor</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_static_data.php')); ?>">Static data</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Refresh</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="alert alert-<?php echo $flash_ok ? 'success' : 'danger'; ?>"><?php echo dev_admin_h($flash); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Data model</strong></div>
                <div class="card-body schema-box">
                    <code>npcs</code> → <code>npc_requirements</code> → <code>requirements</code><br>
                    <code>npcs</code> → <code>conversations</code> → <code>conversation_requirements</code><br>
                    <code>conversations.flg_register</code> (write finish to <code>user_conversations</code>, for requirements) and <code>flg_repeatable</code> (keep offering after finish) are independent flags<br>
                    <code>conversations</code> → <code>dialogues</code> (order) → <code>dialogues_options</code><br>
                    <code>conversation_consequences</code> links <code>id_conversation</code> + <code>id_option</code> → <code>consequences</code> (refs, num, params on the link)<br>
                    <code>npcs</code> → <code>quests</code> (id_starter_npc) → <code>quest_requirements</code> (accept/turn-in gating uses <code>conversation_requirements</code> instead, see below) → <code>quest_objectives</code> (N per phase; a phase advances once every objective row is met)<br>
                    Accept a quest via a dialog option's <code>[start quest]</code> consequence, turn it in via <code>[complete quest]</code> (both <code>ref_table = QUEST</code>, <code>id_ref = id_quest</code>); gate those options with requirement types <code>quest not started</code> / <code>quest started</code> / <code>quest ready to turn in</code> / <code>quest completed</code>, or phase-aware <code>quest phase</code> / <code>quest phase completed</code> (<code>ref_table = QUEST</code>, <code>id_ref = id_quest</code>, <code>min = phase number</code>).<br>
                    Fallback conversations: requirement <code>conversation requirements not met</code> (<code>ref_table = conversations</code>, <code>id_ref</code> = primary conv) passes when any requirement on that primary conv fails; AND it with audience gates (class, quest state). Pair with specific fallbacks (low level, wrong class) that use positive requirements only.<br>
                    <code>user_conversations</code> / <code>user_quests</code> / <code>user_quest_objective_progress</code> track player progress (read-only here).
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>NPC tree</strong>
                    <span class="badge bg-secondary"><?php echo count($tree); ?> NPCs</span>
                </div>
                <div class="card-body">
                    <?php if (!$tree): ?>
                    <p class="meta mb-0">No NPCs in database yet.</p>
                    <?php else: ?>
                    <?php foreach ($tree as $id_npc => $npc): ?>
                    <details class="tree-npc" open>
                        <summary>
                            <strong>#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></strong>
                            <span class="meta"> — zone <?php echo (int) $npc['id_zone']; ?>, pos (<?php echo dev_admin_h($npc['posx']); ?>, <?php echo dev_admin_h($npc['posz']); ?>), <?php echo dev_admin_h($npc['type']); ?></span>
                            <a class="btn btn-outline-secondary btn-sm ms-2" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'npc', 'id' => (int) $id_npc])); ?>" title="Edit NPC">✎</a>
                            <a class="btn btn-outline-primary btn-sm ms-1" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npc.php', ['id_npc' => (int) $id_npc])); ?>" title="NPC interaction tree">interactions</a>
                            <a class="btn btn-outline-info btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'attach', 'attach' => 'req', 'target' => 'npc', 'id_npc' => (int) $id_npc])); ?>" title="Add requirement to NPC">+req</a>
                        </summary>

                        <?php if ($npc['requirements']): ?>
                        <div class="mt-1 ms-1">
                            <ul class="dev-req-list small">
                                <?php foreach ($npc['requirements'] as $req): ?>
                                <?php echo dev_npc_requirement_link_tree_item($req, 'npc'); ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($npc['quests']): ?>
                        <div class="mb-2">
                            <span class="badge bg-info text-dark">Quests</span>
                            <?php foreach ($npc['quests'] as $id_quest => $quest): ?>
                            <div class="ms-2 mt-1">
                                <strong>#<?php echo (int) $id_quest; ?>
                                    <span class="dev-loc-text" <?php echo dev_npc_loc_data_attrs($quest, 'quest'); ?>>
                                        <?php echo dev_admin_h(dev_npc_localized_field($quest, 'quest', $preview_lang)); ?>
                                    </span>
                                </strong>
                                <span class="meta"> (<?php echo dev_admin_h($quest['quest_type']); ?>, lvl <?php echo (int) $quest['lvl_min']; ?>–<?php echo (int) $quest['lvl_max']; ?>, repeatable=<?php echo dev_admin_h($quest['repeatable']); ?>)</span>
                                <a class="btn btn-outline-secondary btn-sm ms-2" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'quest', 'id' => (int) $id_quest])); ?>" title="Edit quest">✎</a>
                                <a class="btn btn-outline-primary btn-sm ms-1" href="<?php echo dev_admin_h(dev_admin_page_url('dev_quest.php', ['id_quest' => (int) $id_quest])); ?>" title="Quest flow diagram">flow</a>
                                <a class="btn btn-outline-info btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'attach', 'attach' => 'req', 'target' => 'quest', 'id_npc' => (int) $id_npc, 'id_quest' => (int) $id_quest])); ?>" title="Add requirement to quest">+req</a>
                                <a class="btn btn-outline-warning btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_admin_url(['qobj_quest' => (int) $id_quest])); ?>#tab-quest" title="Add objective to quest">+obj</a>
                                <?php if (!empty($quest['description'])): ?>
                                <div class="meta small dev-loc-text" <?php echo dev_npc_loc_data_attrs($quest, 'description'); ?>><?php echo dev_admin_h(dev_npc_localized_field($quest, 'description', $preview_lang)); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($quest['requirements'])): ?>
                                <ul class="dev-req-list small mb-0 ms-2">
                                    <?php foreach ($quest['requirements'] as $req): ?>
                                    <?php echo dev_npc_requirement_link_tree_item($req, 'quest'); ?>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <?php if (!empty($quest['objectives'])): ?>
                                <ul class="dev-req-list small mb-0 ms-2">
                                    <?php foreach ($quest['objectives'] as $obj): ?>
                                    <?php echo dev_npc_quest_objective_tree_item($obj, $preview_lang); ?>
                                    <?php endforeach; ?>
                                </ul>
                                <?php else: ?>
                                <p class="meta small ms-2 mb-0">No objectives yet.</p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!$npc['conversations']): ?>
                        <p class="meta ms-2">No conversations.</p>
                        <?php endif; ?>

                        <?php foreach ($npc['conversations'] as $id_conv => $conv): ?>
                        <details class="tree-conv">
                            <summary>
                                <strong>Conv #<?php echo (int) $id_conv; ?>:
                                    <span class="dev-loc-text" <?php echo dev_npc_loc_data_attrs($conv, 'title'); ?>>
                                        <?php echo dev_admin_h(dev_npc_localized_field($conv, 'title', $preview_lang)); ?>
                                    </span>
                                </strong>
                                <span class="meta"> — visible <?php echo dev_admin_h($conv['visible']); ?>, register <?php echo dev_admin_h($conv['flg_register']); ?>, repeatable <?php echo dev_admin_h(isset($conv['flg_repeatable']) ? $conv['flg_repeatable'] : 'N'); ?></span>
                                <a class="btn btn-outline-secondary btn-sm ms-2" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'conversation', 'id' => (int) $id_conv])); ?>" title="Edit conversation">✎</a>
                                <a class="btn btn-outline-info btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'attach', 'attach' => 'req', 'target' => 'conversation', 'id_npc' => (int) $id_npc, 'id_conversation' => (int) $id_conv])); ?>" title="Add requirement to conversation">+req</a>
                            </summary>

                            <?php if ($conv['requirements']): ?>
                            <ul class="dev-req-list small mt-2 ms-1">
                                <?php foreach ($conv['requirements'] as $req): ?>
                                <?php echo dev_npc_requirement_link_tree_item($req, 'conversation'); ?>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <?php if (!$conv['dialogues']): ?>
                            <p class="meta">No dialogues.</p>
                            <?php endif; ?>

                            <?php foreach ($conv['dialogues'] as $id_dialog => $dlg): ?>
                            <div class="tree-dlg">
                                <div><strong>Dialog #<?php echo (int) $id_dialog; ?></strong> <span class="meta">order <?php echo (int) $dlg['order']; ?>, last=<?php echo dev_admin_h($dlg['flg_last']); ?>, options=<?php echo dev_admin_h($dlg['flg_options']); ?></span> <a class="btn btn-outline-secondary btn-sm ms-2" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'dialogue', 'id' => (int) $id_dialog])); ?>" title="Edit dialogue">✎</a></div>
                                <div class="small dev-loc-text" <?php echo dev_npc_loc_data_attrs($dlg, 'dialog'); ?>>
                                    <?php echo dev_admin_h(dev_npc_localized_field($dlg, 'dialog', $preview_lang)); ?>
                                </div>

                                <?php foreach ($dlg['options'] as $opt): ?>
                                <div class="tree-opt">
                                    <strong>Option #<?php echo (int) $opt['id_dialog_option']; ?></strong>
                                    <span class="badge bg-<?php echo dev_admin_h($opt['option_color'] ?: 'secondary'); ?>"><?php echo dev_admin_h($opt['option_color']); ?></span>
                                    <a class="btn btn-outline-secondary btn-sm ms-2" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'option', 'id' => (int) $opt['id_dialog_option']])); ?>" title="Edit option">✎</a>
                                    <a class="btn btn-outline-warning btn-sm dev-btn-mini ms-1" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'attach', 'attach' => 'cons', 'target' => 'option', 'id_npc' => (int) $id_npc, 'id_conversation' => (int) $id_conv, 'id_dialog' => (int) $id_dialog, 'id_option' => (int) $opt['id_dialog_option']])); ?>" title="Add consequence to option">+cons</a>
                                    <span class="dev-loc-text" <?php echo dev_npc_loc_data_attrs($opt, 'option_text'); ?>>
                                        <?php echo dev_admin_h(dev_npc_localized_field($opt, 'option_text', $preview_lang)); ?>
                                    </span>
                                    <?php if (!empty($dlg['consequences'])): ?>
                                    <ul class="dev-req-list small mt-1 mb-0">
                                    <?php foreach ($dlg['consequences'] as $cons): ?>
                                    <?php if ((int) $cons['id_option'] === (int) $opt['id_dialog_option']): ?>
                                    <?php echo dev_npc_consequence_link_tree_item($cons); ?>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </details>
                        <?php endforeach; ?>
                    </details>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dev-card">
                <div class="card-header"><strong>Add / link content</strong></div>
                <div class="card-body">
                    <input type="hidden" id="dev-token" value="<?php echo dev_admin_h($token); ?>">

                    <ul class="nav nav-pills mb-3 flex-wrap" id="devTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link<?php echo $default_form_tab_active ? ' active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-npc" type="button">NPC</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-conv" type="button">Conversation</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-dlg" type="button">Dialogue</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-opt" type="button">Option</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link<?php echo $req_tab_active ? ' active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-req" type="button">Requirements</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link<?php echo $cons_tab_active ? ' active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-cons" type="button">Consequences</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link<?php echo $attach_tab_active ? ' active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-attach" type="button">Attach</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link<?php echo $quest_tab_active ? ' active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-quest" type="button">Quest</button></li>
                    </ul>

                    <div class="tab-content">
                        <!-- NPC -->
                        <div class="tab-pane fade<?php echo $default_form_tab_active ? ' show active' : ''; ?>" id="tab-npc">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_npc = ($edit_type === 'npc' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_npc ? 'update_npc' : 'add_npc'; ?>">
                                <?php if ($is_edit_npc): ?>
                                <input type="hidden" name="id_npc" value="<?php echo (int) $edit_item['id_npc']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Name</label><input class="form-control form-control-sm" name="npc" required maxlength="100" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['npc']) : ''; ?>"></div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Type</label><input class="form-control form-control-sm" name="type" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['type']) : 'story'; ?>" maxlength="100"></div>
                                    <div class="col-6"><label class="form-label">Prefab</label><input class="form-control form-control-sm" name="npc_type_prefab" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['npc_type_prefab']) : 'trader'; ?>" maxlength="100"></div>
                                </div>
                                <div class="mb-2 mt-2"><label class="form-label">Zone</label>
                                    <select class="form-select form-select-sm" name="id_zone">
                                        <?php foreach ($zones as $z): ?>
                                        <option value="<?php echo (int) $z['id_zone']; ?>"<?php echo $is_edit_npc && (int) $edit_item['id_zone'] === (int) $z['id_zone'] ? ' selected' : ''; ?>><?php echo (int) $z['id_zone']; ?> — <?php echo dev_admin_h($z['scene_name']); ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!$zones): ?><option value="1000">1000</option><?php endif; ?>
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-4"><label class="form-label">posx</label><input class="form-control form-control-sm" name="posx" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['posx']) : '0'; ?>"></div>
                                    <div class="col-4"><label class="form-label">posy</label><input class="form-control form-control-sm" name="posy" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['posy']) : '0'; ?>"></div>
                                    <div class="col-4"><label class="form-label">posz</label><input class="form-control form-control-sm" name="posz" value="<?php echo $is_edit_npc ? dev_admin_h($edit_item['posz']) : '0'; ?>"></div>
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" type="submit"><?php echo $is_edit_npc ? 'Update NPC' : 'Create NPC'; ?></button>
                                <?php if ($is_edit_npc): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-3" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Conversation -->
                        <div class="tab-pane fade" id="tab-conv">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_conv = ($edit_type === 'conversation' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_conv ? 'update_conversation' : 'add_conversation'; ?>">
                                <?php if ($is_edit_conv): ?>
                                <input type="hidden" name="id_conversation" value="<?php echo (int) $edit_item['id_conversation']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">NPC</label>
                                    <select class="form-select form-select-sm" name="id_npc" required>
                                        <?php foreach ($tree as $id_npc => $npc): ?>
                                        <option value="<?php echo (int) $id_npc; ?>"<?php echo $is_edit_conv && (int) $edit_item['id_npc'] === (int) $id_npc ? ' selected' : ''; ?>>#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">Title (EN)</label><input class="form-control form-control-sm" name="title" required maxlength="200" value="<?php echo $is_edit_conv ? dev_admin_h($edit_item['title']) : ''; ?>"></div>
                                <div class="mb-2"><label class="form-label">Title IT</label><input class="form-control form-control-sm" name="title_it" maxlength="200" value="<?php echo $is_edit_conv ? dev_admin_h(isset($edit_item['title_it']) ? $edit_item['title_it'] : '') : ''; ?>"></div>
                                <div class="mb-2"><label class="form-label">Title PT</label><input class="form-control form-control-sm" name="title_pt" maxlength="200" value="<?php echo $is_edit_conv ? dev_admin_h(isset($edit_item['title_pt']) ? $edit_item['title_pt'] : '') : ''; ?>"></div>
                                <div class="row g-2">
                                    <div class="col-4"><label class="form-label">Visible</label><select class="form-select form-select-sm" name="visible"><option value="S"<?php echo $is_edit_conv && $edit_item['visible'] === 'S' ? ' selected' : ''; ?>>S</option><option value="N"<?php echo $is_edit_conv && $edit_item['visible'] === 'N' ? ' selected' : ''; ?>>N</option></select></div>
                                    <div class="col-4"><label class="form-label">Register on finish</label><select class="form-select form-select-sm" name="flg_register"><option value="N"<?php echo $is_edit_conv && $edit_item['flg_register'] === 'N' ? ' selected' : ''; ?>>N</option><option value="S"<?php echo $is_edit_conv && $edit_item['flg_register'] === 'S' ? ' selected' : ''; ?>>S</option></select></div>
                                    <div class="col-4"><label class="form-label">Repeatable</label><select class="form-select form-select-sm" name="flg_repeatable"><option value="N"<?php echo $is_edit_conv && (!isset($edit_item['flg_repeatable']) || $edit_item['flg_repeatable'] === 'N') ? ' selected' : ''; ?>>N</option><option value="S"<?php echo $is_edit_conv && isset($edit_item['flg_repeatable']) && $edit_item['flg_repeatable'] === 'S' ? ' selected' : ''; ?>>S</option></select></div>
                                </div>
                                <p class="meta small mt-1 mb-0">Register on finish = write to <code>user_conversations</code> so <code>conversation finished</code> / <code>not finished</code> requirements can see it. Repeatable = keep offering this conversation after it's finished (otherwise it disappears from the NPC once done, independent of register).</p>
                                <button class="btn btn-primary btn-sm mt-3" type="submit"><?php echo $is_edit_conv ? 'Update conversation' : 'Create conversation'; ?></button>
                                <?php if ($is_edit_conv): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-3" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Dialogue -->
                        <div class="tab-pane fade" id="tab-dlg">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_dlg = ($edit_type === 'dialogue' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_dlg ? 'update_dialogue' : 'add_dialogue'; ?>">
                                <?php if ($is_edit_dlg): ?>
                                <input type="hidden" name="id_dialog" value="<?php echo (int) $edit_item['id_dialog']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Conversation</label>
                                    <select class="form-select form-select-sm" name="id_conversation" required>
                                        <?php foreach ($flat_conversations as $c):
                                            $conv_labels = dev_npc_conversation_select_labels($c); ?>
                                        <option value="<?php echo (int) $c['id_conversation']; ?>" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($conv_labels); ?><?php echo $is_edit_dlg && (int) $edit_item['id_conversation'] === (int) $c['id_conversation'] ? ' selected' : ''; ?>>
                                            <?php echo dev_admin_h($conv_labels[$preview_lang]); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">Order</label><input class="form-control form-control-sm" name="order" type="number" value="<?php echo $is_edit_dlg ? (int) $edit_item['order'] : 1; ?>" min="1"></div>
                                    <div class="col-4"><label class="form-label">Last?</label><select class="form-select form-select-sm" name="flg_last"><option value="N"<?php echo $is_edit_dlg && $edit_item['flg_last'] === 'N' ? ' selected' : ''; ?>>N</option><option value="S"<?php echo $is_edit_dlg && $edit_item['flg_last'] === 'S' ? ' selected' : ''; ?>>S</option></select></div>
                                    <div class="col-4"><label class="form-label">Options?</label><select class="form-select form-select-sm" name="flg_options"><option value="N"<?php echo $is_edit_dlg && $edit_item['flg_options'] === 'N' ? ' selected' : ''; ?>>N</option><option value="S"<?php echo $is_edit_dlg && $edit_item['flg_options'] === 'S' ? ' selected' : ''; ?>>S</option></select></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Dialog EN</label><textarea class="form-control form-control-sm" name="dialog" rows="2" maxlength="500" required><?php echo $is_edit_dlg ? dev_admin_h($edit_item['dialog']) : ''; ?></textarea></div>
                                <div class="mb-2"><label class="form-label">Dialog IT</label><textarea class="form-control form-control-sm" name="dialog_it" rows="2" maxlength="500"><?php echo $is_edit_dlg ? dev_admin_h(isset($edit_item['dialog_it']) ? $edit_item['dialog_it'] : '') : ''; ?></textarea></div>
                                <div class="mb-2"><label class="form-label">Dialog PT</label><textarea class="form-control form-control-sm" name="dialog_pt" rows="2" maxlength="500"><?php echo $is_edit_dlg ? dev_admin_h(isset($edit_item['dialog_pt']) ? $edit_item['dialog_pt'] : '') : ''; ?></textarea></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit"><?php echo $is_edit_dlg ? 'Update dialogue line' : 'Create dialogue line'; ?></button>
                                <?php if ($is_edit_dlg): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-2" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Option -->
                        <div class="tab-pane fade" id="tab-opt">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_opt = ($edit_type === 'option' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_opt ? 'update_dialog_option' : 'add_dialog_option'; ?>">
                                <?php if ($is_edit_opt): ?>
                                <input type="hidden" name="id_dialog_option" value="<?php echo (int) $edit_item['id_dialog_option']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Conversation</label>
                                    <select class="form-select form-select-sm" id="opt-filter-conversation">
                                        <?php foreach ($flat_conversations as $c):
                                            $conv_labels = dev_npc_conversation_select_labels($c); ?>
                                        <option value="<?php echo (int) $c['id_conversation']; ?>" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($conv_labels); ?><?php echo $is_edit_opt && (int) $edit_item['id_conversation'] === (int) $c['id_conversation'] ? ' selected' : ''; ?>>
                                            <?php echo dev_admin_h($conv_labels[$preview_lang]); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">Dialogue (must have flg_options=S)</label>
                                    <select class="form-select form-select-sm" name="id_dialog" id="opt-id-dialog" required>
                                        <?php foreach ($flat_dialogues as $d):
                                            $dlg_labels = dev_npc_dialogue_select_labels($d); ?>
                                        <option value="<?php echo (int) $d['id_dialog']; ?>" class="dev-loc-option" data-id_conversation="<?php echo (int) $d['id_conversation']; ?>" <?php echo dev_npc_loc_data_attrs_from_map($dlg_labels); ?><?php echo $is_edit_opt && (int) $edit_item['id_dialog'] === (int) $d['id_dialog'] ? ' selected' : ''; ?>>
                                            <?php echo dev_admin_h($dlg_labels[$preview_lang]); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">option_n</label><input class="form-control form-control-sm" name="option_n" type="number" value="<?php echo $is_edit_opt ? (int) $edit_item['option_n'] : 1; ?>" min="1"></div>
                                    <div class="col-8"><label class="form-label">Color</label><select class="form-select form-select-sm" name="option_color"><option value="green"<?php echo $is_edit_opt && $edit_item['option_color'] === 'green' ? ' selected' : ''; ?>>green</option><option value="red"<?php echo $is_edit_opt && $edit_item['option_color'] === 'red' ? ' selected' : ''; ?>>red</option><option value="blue"<?php echo $is_edit_opt && $edit_item['option_color'] === 'blue' ? ' selected' : ''; ?>>blue</option><option value="yellow"<?php echo $is_edit_opt && $edit_item['option_color'] === 'yellow' ? ' selected' : ''; ?>>yellow</option></select></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Text EN</label><input class="form-control form-control-sm" name="option_text" required maxlength="200" value="<?php echo $is_edit_opt ? dev_admin_h($edit_item['option_text']) : ''; ?>"></div>
                                <div class="mb-2"><label class="form-label">Text IT</label><input class="form-control form-control-sm" name="option_text_it" maxlength="200" value="<?php echo $is_edit_opt ? dev_admin_h(isset($edit_item['option_text_it']) ? $edit_item['option_text_it'] : '') : ''; ?>"></div>
                                <div class="mb-2"><label class="form-label">Text PT</label><input class="form-control form-control-sm" name="option_text_pt" maxlength="200" value="<?php echo $is_edit_opt ? dev_admin_h(isset($edit_item['option_text_pt']) ? $edit_item['option_text_pt'] : '') : ''; ?>"></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit"><?php echo $is_edit_opt ? 'Update option' : 'Create option'; ?></button>
                                <?php if ($is_edit_opt): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-2" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Requirements -->
                        <div class="tab-pane fade<?php echo $req_tab_active ? ' show active' : ''; ?>" id="tab-req">
                            <?php if ($is_edit_req_link): ?>
                            <h6 class="text-secondary">Edit requirement link</h6>
                            <div class="alert alert-secondary py-2 px-3 small mb-3">
                                Attached to <strong><?php echo dev_admin_h(dev_npc_requirement_link_context_title($edit_link_context, $preview_lang)); ?></strong>
                                <span class="meta d-block mt-1">Link #<?php echo (int) $edit_id; ?> · catalog <?php echo dev_admin_h(dev_npc_requirement_catalog_label($edit_item)); ?></span>
                            </div>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4" id="form-edit-requirement-link">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="update_<?php echo dev_admin_h($edit_type); ?>">
                                <?php if ($edit_type === 'npc_requirement'): ?>
                                <input type="hidden" name="id_npc_requirement" value="<?php echo (int) $edit_item['id_npc_requirement']; ?>">
                                <?php elseif ($edit_type === 'conversation_requirement'): ?>
                                <input type="hidden" name="id_conversation_requirement" value="<?php echo (int) $edit_item['id_conversation_requirement']; ?>">
                                <?php else: ?>
                                <input type="hidden" name="id_quest_requirement" value="<?php echo (int) $edit_item['id_quest_requirement']; ?>">
                                <?php endif; ?>
                                <div class="mb-2">
                                    <label class="form-label">Requirement type</label>
                                    <select class="form-select form-select-sm" name="id_requirement" id="edit-req-catalog" required>
                                        <?php foreach ($requirements as $r): ?>
                                        <option value="<?php echo (int) $r['id_requirement']; ?>" data-entity-type="<?php echo dev_admin_h($r['requirement_type']); ?>"<?php echo (int) $edit_item['id_requirement'] === (int) $r['id_requirement'] ? ' selected' : ''; ?>><?php echo dev_admin_h(dev_npc_requirement_catalog_label($r)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php
                                dev_npc_requirement_link_ref_fields(array_merge($req_ref_field_ctx, [
                                    'prefix' => 'edit-req',
                                    'row' => $edit_item
                                ]));
                                ?>
                                <?php dev_npc_requirement_link_range_fields(
                                    'edit-req-min',
                                    'edit-req-max',
                                    'edit-req-max-unbounded',
                                    $edit_item['min'],
                                    array_key_exists('max', $edit_item) ? $edit_item['max'] : null
                                ); ?>
                                <div class="mb-2">
                                    <label class="form-label">Link description</label>
                                    <input class="form-control form-control-sm" name="descrizione" maxlength="100" value="<?php echo dev_admin_h(isset($edit_item['descrizione']) ? (string) $edit_item['descrizione'] : ''); ?>" placeholder="Shown in dev tree only">
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit">Update link</button>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'req', 'edit' => 'requirement', 'id' => (int) $edit_item['id_requirement']])); ?>">Edit catalog row</a>
                            </form>
                            <hr class="border-secondary my-4">
                            <?php endif; ?>

                            <h6 class="text-secondary"><?php echo ($edit_type === 'requirement' && is_array($edit_item)) ? 'Edit catalog requirement' : 'New catalog requirement'; ?></h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4" id="form-add-requirement">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_req = ($edit_type === 'requirement' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_req ? 'update_requirement' : 'add_requirement'; ?>">
                                <?php if ($is_edit_req): ?>
                                <input type="hidden" name="id_requirement" value="<?php echo (int) $edit_item['id_requirement']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Type</label>
                                    <select class="form-select form-select-sm" name="requirement_type" id="req-requirement-type">
                                        <?php foreach ($requirement_types as $req_type): ?>
                                        <option value="<?php echo dev_admin_h($req_type); ?>"<?php echo $is_edit_req && $edit_item['requirement_type'] === $req_type ? ' selected' : ''; ?>><?php echo dev_admin_h($req_type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="meta small mb-2">One row per requirement <strong>type</strong>. ref_table, id_ref, min, max, and description are set on each NPC / conversation / quest link.</p>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_req ? 'Update requirement' : 'Create requirement'; ?></button>
                                <?php if ($is_edit_req): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>

                            <p class="meta small mb-0">Use the <strong>Attach</strong> tab to link requirements to NPCs, conversations, or quests (with target drill-down and create-or-pick).</p>
                        </div>

                        <!-- Consequences -->
                        <div class="tab-pane fade<?php echo $cons_tab_active ? ' show active' : ''; ?>" id="tab-cons">
                            <?php if ($is_edit_cons_link): ?>
                            <h6 class="text-secondary">Edit consequence link</h6>
                            <div class="alert alert-secondary py-2 px-3 small mb-3">
                                Attached to <strong><?php echo dev_admin_h(dev_npc_consequence_link_context_title($edit_link_context, $preview_lang)); ?></strong>
                                <span class="meta d-block mt-1">Link #<?php echo (int) $edit_id; ?> · catalog <?php echo dev_admin_h(dev_npc_consequence_catalog_label($edit_item)); ?></span>
                            </div>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4" id="form-edit-consequence-link">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="update_conversation_consequence">
                                <input type="hidden" name="id_conversation_consequence" value="<?php echo (int) $edit_item['id_conversation_consequence']; ?>">
                                <div class="mb-2">
                                    <label class="form-label">Consequence type</label>
                                    <select class="form-select form-select-sm" name="id_consequence" id="edit-cons-catalog" required>
                                        <?php foreach ($consequences as $c): ?>
                                        <option value="<?php echo (int) $c['id_consequence']; ?>" data-entity-type="<?php echo dev_admin_h($c['consequence_type']); ?>"<?php echo (int) $edit_item['id_consequence'] === (int) $c['id_consequence'] ? ' selected' : ''; ?>><?php echo dev_admin_h(dev_npc_consequence_catalog_label($c)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php
                                dev_npc_consequence_link_ref_fields(array_merge($cons_ref_field_ctx, [
                                    'prefix' => 'edit-cons',
                                    'row' => $edit_item
                                ]));
                                ?>
                                <button class="btn btn-primary btn-sm" type="submit">Update link</button>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url(['tab' => 'cons', 'edit' => 'consequence', 'id' => (int) $edit_item['id_consequence']])); ?>">Edit catalog row</a>
                            </form>
                            <hr class="border-secondary my-4">
                            <?php endif; ?>

                            <h6 class="text-secondary"><?php echo ($edit_type === 'consequence' && is_array($edit_item)) ? 'Edit catalog consequence' : 'New catalog consequence'; ?></h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_cons = ($edit_type === 'consequence' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_cons ? 'update_consequence' : 'add_consequence'; ?>">
                                <?php if ($is_edit_cons): ?>
                                <input type="hidden" name="id_consequence" value="<?php echo (int) $edit_item['id_consequence']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Type</label>
                                    <select class="form-select form-select-sm" name="consequence_type" id="cons-consequence-type">
                                        <?php foreach ($consequence_types as $ct): ?>
                                        <option value="<?php echo dev_admin_h($ct['value']); ?>"<?php echo $is_edit_cons && $edit_item['consequence_type'] === $ct['value'] ? ' selected' : (!$is_edit_cons && $ct['value'] === '[obtain item]' ? ' selected' : ''); ?>><?php echo dev_admin_h($ct['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="meta small mb-2">One row per consequence <strong>type</strong>. ref_table, id_ref, num, and params_json are set on each dialog-option link.</p>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_cons ? 'Update consequence' : 'Create consequence'; ?></button>
                                <?php if ($is_edit_cons): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>

                            <p class="meta small mb-0">Use the <strong>Attach</strong> tab to link consequences to dialog options (with target drill-down and create-or-pick).</p>
                        </div>

                        <!-- Attach requirement / consequence -->
                        <div class="tab-pane fade<?php echo $attach_tab_active ? ' show active' : ''; ?>" id="tab-attach">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" id="form-attach">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" id="attach-action" value="<?php echo $attach_prefill['attach'] === 'cons' ? 'attach_consequence' : 'attach_requirement'; ?>">

                                <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Attach kind">
                                    <input type="radio" class="btn-check" name="attach_kind_ui" id="attach-kind-req" value="req"<?php echo $attach_prefill['attach'] !== 'cons' ? ' checked' : ''; ?>>
                                    <label class="btn btn-outline-info" for="attach-kind-req">Requirement</label>
                                    <input type="radio" class="btn-check" name="attach_kind_ui" id="attach-kind-cons" value="cons"<?php echo $attach_prefill['attach'] === 'cons' ? ' checked' : ''; ?>>
                                    <label class="btn btn-outline-warning" for="attach-kind-cons">Consequence</label>
                                </div>

                                <div class="dev-attach-section">
                                    <h6>Target</h6>
                                    <div class="mb-2" id="attach-target-type-wrap">
                                        <label class="form-label">Target type</label>
                                        <select class="form-select form-select-sm" name="target_type" id="attach-target-type">
                                            <option value="npc"<?php echo $attach_prefill['target'] === 'npc' ? ' selected' : ''; ?>>NPC</option>
                                            <option value="conversation"<?php echo $attach_prefill['target'] === 'conversation' ? ' selected' : ''; ?>>Conversation</option>
                                            <option value="quest"<?php echo $attach_prefill['target'] === 'quest' ? ' selected' : ''; ?>>Quest</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">NPC</label>
                                        <select class="form-select form-select-sm" name="target_id_npc" id="attach-target-npc">
                                            <?php foreach ($tree as $id_npc => $npc): ?>
                                            <option value="<?php echo (int) $id_npc; ?>"<?php echo $attach_prefill['id_npc'] === (int) $id_npc ? ' selected' : ''; ?>>#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2" id="attach-target-conversation-wrap">
                                        <label class="form-label">Conversation</label>
                                        <select class="form-select form-select-sm" name="target_id_conversation" id="attach-target-conversation">
                                            <?php foreach ($flat_conversations as $c):
                                                $conv_labels = dev_npc_conversation_select_labels($c); ?>
                                            <option value="<?php echo (int) $c['id_conversation']; ?>" data-id_npc="<?php echo (int) $c['id_npc']; ?>" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($conv_labels); ?><?php echo $attach_prefill['id_conversation'] === (int) $c['id_conversation'] ? ' selected' : ''; ?>>
                                                <?php echo dev_admin_h($conv_labels[$preview_lang]); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2" id="attach-target-quest-wrap">
                                        <label class="form-label">Quest</label>
                                        <select class="form-select form-select-sm" name="target_id_quest" id="attach-target-quest">
                                            <?php foreach ($flat_quests as $q): ?>
                                            <option value="<?php echo (int) $q['id_quest']; ?>" data-id_npc="<?php echo (int) $q['id_starter_npc']; ?>"<?php echo $attach_prefill['id_quest'] === (int) $q['id_quest'] ? ' selected' : ''; ?>>#<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h($q['quest']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2" id="attach-target-dialog-wrap">
                                        <label class="form-label">Dialogue</label>
                                        <select class="form-select form-select-sm" id="attach-target-dialog">
                                            <?php foreach ($flat_dialogues as $d):
                                                $dlg_labels = dev_npc_dialogue_select_labels($d); ?>
                                            <option value="<?php echo (int) $d['id_dialog']; ?>" data-id_conversation="<?php echo (int) $d['id_conversation']; ?>" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($dlg_labels); ?><?php echo $attach_prefill['id_dialog'] === (int) $d['id_dialog'] ? ' selected' : ''; ?>>
                                                <?php echo dev_admin_h($dlg_labels[$preview_lang]); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2" id="attach-target-option-wrap">
                                        <label class="form-label">Dialog option</label>
                                        <select class="form-select form-select-sm" name="target_id_option" id="attach-target-option">
                                            <?php foreach ($flat_options as $o):
                                                $opt_labels = dev_npc_option_select_labels($o); ?>
                                            <option value="<?php echo (int) $o['id_dialog_option']; ?>" data-id_dialog="<?php echo (int) $o['id_dialog']; ?>" data-id_conversation="<?php echo (int) $o['id_conversation']; ?>" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($opt_labels); ?><?php echo $attach_prefill['id_option'] === (int) $o['id_dialog_option'] ? ' selected' : ''; ?>>
                                                <?php echo dev_admin_h($opt_labels[$preview_lang]); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="dev-attach-section">
                                    <h6 id="attach-entity-heading">Requirement</h6>
                                    <div class="mb-2">
                                        <label class="form-label">Mode</label>
                                        <select class="form-select form-select-sm" name="entity_mode" id="attach-entity-mode">
                                            <option value="existing">Use existing</option>
                                            <option value="new">Create new</option>
                                        </select>
                                    </div>

                                    <div id="attach-existing-panel">
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <label class="form-label">Filter type</label>
                                                <select class="form-select form-select-sm" id="attach-filter-type">
                                                    <option value="">— any —</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Filter ref_table</label>
                                                <select class="form-select form-select-sm" id="attach-filter-ref-table">
                                                    <option value="">— any —</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="mb-2" id="attach-pick-requirement-wrap">
                                            <label class="form-label">Requirement</label>
                                            <select class="form-select form-select-sm" name="id_requirement" id="attach-pick-requirement">
                                                <?php foreach ($requirements as $r): ?>
                                                <option value="<?php echo (int) $r['id_requirement']; ?>" data-entity-type="<?php echo dev_admin_h($r['requirement_type']); ?>"><?php echo dev_admin_h(dev_npc_requirement_catalog_label($r)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2" id="attach-pick-consequence-wrap">
                                            <label class="form-label">Consequence</label>
                                            <select class="form-select form-select-sm" name="id_consequence" id="attach-pick-consequence">
                                                <?php foreach ($consequences as $c): ?>
                                                <option value="<?php echo (int) $c['id_consequence']; ?>" data-entity-type="<?php echo dev_admin_h($c['consequence_type']); ?>"><?php echo dev_admin_h(dev_npc_consequence_catalog_label($c)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="attach-new-panel" class="d-none">
                                        <div class="mb-2" id="attach-new-req-type-wrap">
                                            <label class="form-label">requirement_type</label>
                                            <select class="form-select form-select-sm" name="requirement_type" id="attach-new-req-type">
                                                <?php foreach ($requirement_types as $req_type): ?>
                                                <option value="<?php echo dev_admin_h($req_type); ?>"><?php echo dev_admin_h($req_type); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-2" id="attach-new-cons-type-wrap">
                                            <label class="form-label">consequence_type</label>
                                            <select class="form-select form-select-sm" name="consequence_type" id="attach-new-cons-type">
                                                <?php foreach ($consequence_types as $ct): ?>
                                                <option value="<?php echo dev_admin_h($ct['value']); ?>"><?php echo dev_admin_h($ct['label']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div id="attach-req-link-params" class="d-none">
                                        <?php
                                        dev_npc_requirement_link_ref_fields(array_merge($req_ref_field_ctx, [
                                            'prefix' => 'attach-req',
                                            'row' => []
                                        ]));
                                        ?>
                                        <?php dev_npc_requirement_link_range_fields(
                                            'attach-req-min',
                                            'attach-req-max',
                                            'attach-req-max-unbounded',
                                            0,
                                            null
                                        ); ?>
                                        <div class="mb-2">
                                            <label class="form-label">Link description</label>
                                            <input class="form-control form-control-sm" name="descrizione" maxlength="100" id="attach-req-descrizione" placeholder="e.g. Player level at least 8">
                                        </div>
                                    </div>

                                    <div id="attach-cons-link-params" class="d-none">
                                        <?php
                                        dev_npc_consequence_link_ref_fields(array_merge($cons_ref_field_ctx, [
                                            'prefix' => 'attach-cons',
                                            'row' => []
                                        ]));
                                        ?>
                                    </div>
                                </div>

                                <button class="btn btn-primary btn-sm" type="submit" id="attach-submit-btn">Attach requirement</button>
                            </form>
                        </div>

                        <!-- Quest -->
                        <div class="tab-pane fade<?php echo $quest_tab_active ? ' show active' : ''; ?>" id="tab-quest">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <?php $is_edit_quest = ($edit_type === 'quest' && is_array($edit_item)); ?>
                                <input type="hidden" name="action" value="<?php echo $is_edit_quest ? 'update_quest' : 'add_quest'; ?>">
                                <?php if ($is_edit_quest): ?>
                                <input type="hidden" name="id_quest" value="<?php echo (int) $edit_item['id_quest']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Starter NPC</label><select class="form-select form-select-sm" name="id_starter_npc"><?php foreach ($tree as $id_npc => $npc): ?><option value="<?php echo (int) $id_npc; ?>"<?php echo $is_edit_quest && (int) $edit_item['id_starter_npc'] === (int) $id_npc ? ' selected' : ''; ?>>#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label class="form-label">Quest title EN</label><input class="form-control form-control-sm" name="quest" required maxlength="200" value="<?php echo $is_edit_quest ? dev_admin_h($edit_item['quest']) : ''; ?>"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Quest title IT</label><input class="form-control form-control-sm" name="quest_it" maxlength="200" value="<?php echo $is_edit_quest ? dev_admin_h((string) ($edit_item['quest_it'] ?? '')) : ''; ?>"></div>
                                    <div class="col-6"><label class="form-label">Quest title PT</label><input class="form-control form-control-sm" name="quest_pt" maxlength="200" value="<?php echo $is_edit_quest ? dev_admin_h((string) ($edit_item['quest_pt'] ?? '')) : ''; ?>"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Description EN</label><textarea class="form-control form-control-sm" name="description" rows="2" maxlength="1000"><?php echo $is_edit_quest ? dev_admin_h((string) ($edit_item['description'] ?? '')) : ''; ?></textarea></div>
                                <div class="mb-2"><label class="form-label">Description IT</label><textarea class="form-control form-control-sm" name="description_it" rows="2" maxlength="1000"><?php echo $is_edit_quest ? dev_admin_h((string) ($edit_item['description_it'] ?? '')) : ''; ?></textarea></div>
                                <div class="mb-2"><label class="form-label">Description PT</label><textarea class="form-control form-control-sm" name="description_pt" rows="2" maxlength="1000"><?php echo $is_edit_quest ? dev_admin_h((string) ($edit_item['description_pt'] ?? '')) : ''; ?></textarea></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Type</label><input class="form-control form-control-sm" name="quest_type" maxlength="100" value="<?php echo $is_edit_quest ? dev_admin_h($edit_item['quest_type']) : ''; ?>"></div>
                                    <div class="col-6"><label class="form-label">Repeatable</label><select class="form-select form-select-sm" name="repeatable"><option value="N"<?php echo $is_edit_quest && $edit_item['repeatable'] === 'N' ? ' selected' : ''; ?>>N</option><option value="S"<?php echo $is_edit_quest && $edit_item['repeatable'] === 'S' ? ' selected' : ''; ?>>S</option></select></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">lvl min</label><input class="form-control form-control-sm" name="lvl_min" type="number" value="<?php echo $is_edit_quest ? (int) $edit_item['lvl_min'] : 0; ?>"></div>
                                    <div class="col-6"><label class="form-label">lvl max</label><input class="form-control form-control-sm" name="lvl_max" type="number" value="<?php echo $is_edit_quest ? (int) $edit_item['lvl_max'] : 100; ?>"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">ids_quests_required</label><input class="form-control form-control-sm" name="ids_quests_required" value="<?php echo $is_edit_quest ? dev_admin_h($edit_item['ids_quests_required']) : '-1,-1'; ?>" maxlength="100"></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit"><?php echo $is_edit_quest ? 'Update quest' : 'Create quest'; ?></button>
                                <?php if ($is_edit_quest): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-2" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                            <p class="meta small mt-3 mb-0">Gate accept/turn-in dialog options with requirement types <code>quest not started</code> / <code>quest started</code> / <code>quest ready to turn in</code> / <code>quest completed</code>, or phase-aware <code>quest phase</code> / <code>quest phase completed</code> (ref_table <code>QUEST</code>, id_ref = this quest, min = phase number), and fire <code>[start quest]</code> / <code>[complete quest]</code> consequences on the chosen option (same ref_table/id_ref) via the <strong>Requirements</strong> / <strong>Consequences</strong> tabs.</p>

                            <hr>
                            <h6>Quest objectives</h6>
                            <p class="meta small">One row per phase objective; a phase advances only once every objective row for it is satisfied (see <code>QUESTS::refreshProgress</code>).</p>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="<?php echo $is_edit_qobj ? 'update_quest_objective' : 'add_quest_objective'; ?>">
                                <?php if ($is_edit_qobj): ?>
                                <input type="hidden" name="id_quest_objective" value="<?php echo (int) $edit_item['id_quest_objective']; ?>">
                                <?php endif; ?>
                                <?php $qobj_default_id_quest = $is_edit_qobj ? (int) $edit_item['id_quest'] : ($qobj_quest_prefill ?: ($is_edit_quest ? (int) $edit_item['id_quest'] : 0)); ?>
                                <div class="mb-2">
                                    <label class="form-label">Quest</label>
                                    <select class="form-select form-select-sm" name="id_quest">
                                        <?php foreach ($flat_quests as $q): ?>
                                        <option value="<?php echo (int) $q['id_quest']; ?>"<?php echo $qobj_default_id_quest === (int) $q['id_quest'] ? ' selected' : ''; ?>>#<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h($q['quest']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Phase</label><input class="form-control form-control-sm" name="phase" type="number" min="1" value="<?php echo $is_edit_qobj ? (int) $edit_item['phase'] : 1; ?>"></div>
                                    <div class="col-6"><label class="form-label">Sort order</label><input class="form-control form-control-sm" name="sort_order" type="number" min="0" value="<?php echo $is_edit_qobj ? (int) $edit_item['sort_order'] : 0; ?>"></div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Objective type</label>
                                    <select class="form-select form-select-sm" name="objective_type" id="qobj-objective-type">
                                        <?php foreach ($objective_types as $ot): ?>
                                        <option value="<?php echo dev_admin_h($ot['value']); ?>"<?php echo $is_edit_qobj && $edit_item['objective_type'] === $ot['value'] ? ' selected' : ''; ?>><?php echo dev_admin_h($ot['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Target (species / item / conversation, per type above)</label>
                                    <select class="form-select form-select-sm" name="target_ref" id="qobj-target-ref">
                                        <option value="0" data-objective-type="reach_level"<?php echo $is_edit_qobj && $edit_item['objective_type'] === 'reach_level' ? ' selected' : ''; ?>>— none (reach_level uses target_count as level) —</option>
                                        <?php foreach ($species_list as $sp): ?>
                                        <option value="<?php echo (int) $sp['id_species']; ?>" data-objective-type="kill_species"<?php echo $is_edit_qobj && $edit_item['objective_type'] === 'kill_species' && (int) $edit_item['target_ref'] === (int) $sp['id_species'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $sp['id_species']; ?> <?php echo dev_admin_h($sp['species']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php foreach ($item_types as $item): ?>
                                        <option value="<?php echo (int) $item['id_item_type']; ?>" data-objective-type="collect_item"<?php echo $is_edit_qobj && $edit_item['objective_type'] === 'collect_item' && (int) $edit_item['target_ref'] === (int) $item['id_item_type'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $item['id_item_type']; ?> <?php echo dev_admin_h($item['nome'] ?: $item['item_type']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        <?php foreach ($flat_conversations as $c):
                                            $conv_labels = dev_npc_conversation_select_labels($c); ?>
                                        <option value="<?php echo (int) $c['id_conversation']; ?>" data-objective-type="talk_npc" class="dev-loc-option" <?php echo dev_npc_loc_data_attrs_from_map($conv_labels); ?><?php echo $is_edit_qobj && $edit_item['objective_type'] === 'talk_npc' && (int) $edit_item['target_ref'] === (int) $c['id_conversation'] ? ' selected' : ''; ?>>
                                            <?php echo dev_admin_h($conv_labels[$preview_lang]); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">Target count <span class="text-muted">(kill/collect count, or the level for reach_level)</span></label><input class="form-control form-control-sm" name="target_count" type="number" min="1" value="<?php echo $is_edit_qobj ? (int) $edit_item['target_count'] : 1; ?>"></div>
                                <div class="mb-2"><label class="form-label">Description EN</label><input class="form-control form-control-sm" name="description" maxlength="200" value="<?php echo $is_edit_qobj ? dev_admin_h((string) ($edit_item['description'] ?? '')) : ''; ?>" placeholder="e.g. Defeat 3 Snakes"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Description IT</label><input class="form-control form-control-sm" name="description_it" maxlength="200" value="<?php echo $is_edit_qobj ? dev_admin_h((string) ($edit_item['description_it'] ?? '')) : ''; ?>"></div>
                                    <div class="col-6"><label class="form-label">Description PT</label><input class="form-control form-control-sm" name="description_pt" maxlength="200" value="<?php echo $is_edit_qobj ? dev_admin_h((string) ($edit_item['description_pt'] ?? '')) : ''; ?>"></div>
                                </div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit"><?php echo $is_edit_qobj ? 'Update objective' : 'Create objective'; ?></button>
                                <?php if ($is_edit_qobj): ?>
                                <a class="btn btn-outline-secondary btn-sm mt-2" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card dev-card mt-3">
                <div class="card-header"><strong>Reference lists</strong></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Requirements (<?php echo count($requirements); ?>)</strong></p>
                    <ul class="mb-3"><?php foreach ($requirements as $r): ?><li><?php echo dev_admin_h(dev_npc_requirement_catalog_label($r)); ?> <a class="btn btn-outline-secondary btn-sm ms-1" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'requirement', 'id' => (int) $r['id_requirement']])); ?>" title="Edit requirement type">✎</a></li><?php endforeach; ?></ul>
                    <p class="mb-1"><strong>Consequences (<?php echo count($consequences); ?>)</strong></p>
                    <ul class="mb-0"><?php foreach ($consequences as $c): ?><li><?php echo dev_admin_h(dev_npc_consequence_catalog_label($c)); ?> <a class="btn btn-outline-secondary btn-sm ms-1" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'consequence', 'id' => (int) $c['id_consequence']])); ?>" title="Edit consequence type">✎</a></li><?php endforeach; ?></ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function ()
{
    var editType = <?php echo json_encode($edit_type); ?>;
    var attachTabActive = <?php echo $attach_tab_active ? 'true' : 'false'; ?>;
    var questTabActive = <?php echo $quest_tab_active ? 'true' : 'false'; ?>;
    var tabByEditType = {
        npc: '#tab-npc',
        conversation: '#tab-conv',
        dialogue: '#tab-dlg',
        option: '#tab-opt',
        requirement: '#tab-req',
        npc_requirement: '#tab-req',
        conversation_requirement: '#tab-req',
        quest_requirement: '#tab-req',
        consequence: '#tab-cons',
        conversation_consequence: '#tab-cons',
        quest: '#tab-quest',
        quest_objective: '#tab-quest'
    };
    var tabPane = tabByEditType[editType];

    if (tabPane)
    {
        var trigger = document.querySelector('[data-bs-target="' + tabPane + '"]');

        if (trigger)
        {
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        }

        return;
    }

    if (attachTabActive)
    {
        var attachTrigger = document.querySelector('[data-bs-target="#tab-attach"]');

        if (attachTrigger)
        {
            bootstrap.Tab.getOrCreateInstance(attachTrigger).show();
        }

        return;
    }

    if (questTabActive)
    {
        var questTrigger = document.querySelector('[data-bs-target="#tab-quest"]');

        if (questTrigger)
        {
            bootstrap.Tab.getOrCreateInstance(questTrigger).show();
        }
    }
})();

(function ()
{
    function setupConversationChildFilter(conversationEl, childEl, dataAttr)
    {
        if (!conversationEl || !childEl)
        {
            return;
        }

        function filterChildOptions()
        {
            var conversationId = conversationEl.value;
            var options = childEl.querySelectorAll('option');
            var firstMatch = null;

            options.forEach(function (opt)
            {
                var match = opt.getAttribute(dataAttr) === conversationId;
                opt.hidden = !match;
                opt.disabled = !match;

                if (match && firstMatch === null)
                {
                    firstMatch = opt;
                }
            });

            if (firstMatch)
            {
                childEl.value = firstMatch.value;
            }
        }

        conversationEl.addEventListener('change', filterChildOptions);
        filterChildOptions();
    }

    setupConversationChildFilter(
        document.getElementById('opt-filter-conversation'),
        document.getElementById('opt-id-dialog'),
        'data-id_conversation'
    );
})();

(function ()
{
    var typeEl = document.getElementById('qobj-objective-type');
    var targetEl = document.getElementById('qobj-target-ref');

    if (!typeEl || !targetEl)
    {
        return;
    }

    function filterQuestObjectiveTarget()
    {
        var type = typeEl.value;
        var currentVal = targetEl.value;
        var firstMatch = null;
        var currentStillValid = false;

        targetEl.querySelectorAll('option').forEach(function (opt)
        {
            var match = opt.getAttribute('data-objective-type') === type;
            opt.hidden = !match;
            opt.disabled = !match;

            if (match && firstMatch === null)
            {
                firstMatch = opt;
            }

            if (match && opt.value === currentVal)
            {
                currentStillValid = true;
            }
        });

        if (!currentStillValid && firstMatch)
        {
            targetEl.value = firstMatch.value;
        }
    }

    typeEl.addEventListener('change', filterQuestObjectiveTarget);
    filterQuestObjectiveTarget();
})();

(function ()
{
    var typeRefMap = {
        'user lvl': 'users_ig.level',
        'number of animals': 'animals',
        'item': 'item_types',
        'conversation finished': 'conversations',
        'conversation not finished': 'conversations',
        'conversation requirements not met': 'conversations',
        'player class': 'player_classes',
        'player class not': 'player_classes',
        'quest not started': 'quests',
        'quest started': 'quests',
        'quest ready to turn in': 'quests',
        'quest completed': 'quests',
        'quest phase': 'quests',
        'quest phase completed': 'quests'
    };  

    var refTableLegacyToCanonical = {
        'QUEST': 'quests',
        'PLAYER_CLASS': 'player_classes',
        'CONVERSATION': 'conversations',
        'POTION': 'item_types',
        'ZERO': 'animals',
        'HAS_ANIMALS': 'animals',
        'LT_2': 'animals',
        'LT_3': 'animals'
    };

    function optionMatchesRefTable(opt, refTable)
    {
        var primary = opt.getAttribute('data-ref-table') || '';

        if (primary === refTable)
        {
            return true;
        }

        var alt = opt.getAttribute('data-ref-table-alt') || '';

        if (alt === '')
        {
            return false;
        }

        return alt.split(',').some(function (part)
        {
            return part.trim() === refTable;
        });
    }

    function filterIdRefOptions(refTableEl, idRefEl)
    {
        var refTable = refTableEl.value;

        if (refTableLegacyToCanonical[refTable])
        {
            refTable = refTableLegacyToCanonical[refTable];
        }

        var firstMatch = null;
        var selectedStillVisible = false;

        idRefEl.querySelectorAll('option').forEach(function (opt)
        {
            var match = optionMatchesRefTable(opt, refTable);
            opt.hidden = !match;
            opt.disabled = !match;

            if (match)
            {
                if (firstMatch === null)
                {
                    firstMatch = opt;
                }

                if (opt.value === idRefEl.value)
                {
                    selectedStillVisible = true;
                }
            }
        });

        if (!selectedStillVisible && firstMatch)
        {
            idRefEl.value = firstMatch.value;
        }
    }

    function suggestRefTableForType(block, reqType)
    {
        var refTableEl = block.querySelector('.dev-req-ref-table');
        var idRefEl = block.querySelector('.dev-req-id-ref');

        if (!refTableEl || !idRefEl)
        {
            return;
        }

        var suggested = typeRefMap[reqType];

        if (suggested === undefined)
        {
            return;
        }

        refTableEl.value = suggested;
        filterIdRefOptions(refTableEl, idRefEl);
    }

    function initRequirementLinkRefBlock(block)
    {
        var refTableEl = block.querySelector('.dev-req-ref-table');
        var idRefEl = block.querySelector('.dev-req-id-ref');
        var refDescEl = block.querySelector('.dev-req-ref-description');

        if (!refTableEl || !idRefEl)
        {
            return;
        }

        refTableEl.addEventListener('change', function ()
        {
            filterIdRefOptions(refTableEl, idRefEl);
        });

        idRefEl.addEventListener('change', function ()
        {
            var sel = idRefEl.options[idRefEl.selectedIndex];

            if (sel && refDescEl && sel.getAttribute('data-ref-description'))
            {
                refDescEl.value = sel.getAttribute('data-ref-description');
            }
        });

        filterIdRefOptions(refTableEl, idRefEl);
    }

    document.querySelectorAll('.dev-req-ref-fields').forEach(initRequirementLinkRefBlock);

    function initConsequenceLinkRefBlock(block)
    {
        var refTableEl = block.querySelector('.dev-cons-ref-table');
        var idRefEl = block.querySelector('.dev-cons-id-ref');
        var refDescEl = block.querySelector('.dev-cons-ref-description');
        var consType = block.getAttribute('data-cons-type') || '';

        if (!refTableEl || !idRefEl)
        {
            return;
        }

        refTableEl.addEventListener('change', function ()
        {
            filterIdRefOptions(refTableEl, idRefEl);
        });

        idRefEl.addEventListener('change', function ()
        {
            var sel = idRefEl.options[idRefEl.selectedIndex];

            if (sel && refDescEl && sel.getAttribute('data-ref-description'))
            {
                refDescEl.value = sel.getAttribute('data-ref-description');
            }
        });

        if (consType)
        {
            suggestConsequenceRefTableForType(block, consType);
        }
        else
        {
            filterIdRefOptions(refTableEl, idRefEl);
        }
    }

    function suggestConsequenceRefTableForType(block, consType)
    {
        var refTableEl = block.querySelector('.dev-cons-ref-table');
        var idRefEl = block.querySelector('.dev-cons-id-ref');

        if (!refTableEl)
        {
            return;
        }

        var map = {
            '[obtain item]': 'item_types',
            'receive_random_animal': '',
            '[set player_class]': 'PLAYER_CLASS',
            'grant_team_buff': 'buff_definitions',
            '[start quest]': 'QUEST',
            '[complete quest]': 'QUEST',
            '[open shop]': 'shops'
        };

        if (map[consType] !== undefined)
        {
            refTableEl.value = map[consType];
        }

        if (idRefEl)
        {
            filterIdRefOptions(refTableEl, idRefEl);
        }
    }

    document.querySelectorAll('.dev-cons-ref-fields').forEach(initConsequenceLinkRefBlock);

    var editCatalog = document.getElementById('edit-req-catalog');
    var editRefBlock = document.querySelector('#form-edit-requirement-link .dev-req-ref-fields');

    if (editCatalog && editRefBlock)
    {
        editCatalog.addEventListener('change', function ()
        {
            var opt = editCatalog.options[editCatalog.selectedIndex];

            if (opt)
            {
                suggestRefTableForType(editRefBlock, opt.getAttribute('data-entity-type') || '');
            }
        });
    }

    var editConsCatalog = document.getElementById('edit-cons-catalog');
    var editConsRefBlock = document.querySelector('#form-edit-consequence-link .dev-cons-ref-fields');

    if (editConsCatalog && editConsRefBlock)
    {
        editConsCatalog.addEventListener('change', function ()
        {
            var opt = editConsCatalog.options[editConsCatalog.selectedIndex];

            if (opt)
            {
                suggestConsequenceRefTableForType(editConsRefBlock, opt.getAttribute('data-entity-type') || '');
            }
        });
    }

    var pickReq = document.getElementById('attach-pick-requirement');
    var attachRefBlock = document.querySelector('#attach-req-link-params .dev-req-ref-fields');

    if (pickReq && attachRefBlock)
    {
        pickReq.addEventListener('change', function ()
        {
            var opt = pickReq.options[pickReq.selectedIndex];

            if (opt)
            {
                suggestRefTableForType(attachRefBlock, opt.getAttribute('data-entity-type') || '');
            }
        });
    }

    var attachNewReqType = document.getElementById('attach-new-req-type');

    if (attachNewReqType && attachRefBlock)
    {
        attachNewReqType.addEventListener('change', function ()
        {
            suggestRefTableForType(attachRefBlock, attachNewReqType.value);
        });
    }

    var pickCons = document.getElementById('attach-pick-consequence');
    var attachConsRefBlock = document.querySelector('#attach-cons-link-params .dev-cons-ref-fields');

    if (pickCons && attachConsRefBlock)
    {
        pickCons.addEventListener('change', function ()
        {
            var opt = pickCons.options[pickCons.selectedIndex];

            if (opt)
            {
                suggestConsequenceRefTableForType(attachConsRefBlock, opt.getAttribute('data-entity-type') || '');
            }
        });
    }

    var attachNewConsType = document.getElementById('attach-new-cons-type');

    if (attachNewConsType && attachConsRefBlock)
    {
        attachNewConsType.addEventListener('change', function ()
        {
            suggestConsequenceRefTableForType(attachConsRefBlock, attachNewConsType.value);
        });
    }
})();

(function ()
{
    var attachPrefill = <?php echo json_encode($attach_prefill); ?>;
    var reqRefTables = <?php echo json_encode($requirement_ref_tables); ?>;
    var consRefTables = <?php echo json_encode($consequence_ref_tables); ?>;
    var form = document.getElementById('form-attach');

    if (!form)
    {
        return;
    }

    var actionEl = document.getElementById('attach-action');
    var kindReq = document.getElementById('attach-kind-req');
    var kindCons = document.getElementById('attach-kind-cons');
    var targetTypeEl = document.getElementById('attach-target-type');
    var targetTypeWrap = document.getElementById('attach-target-type-wrap');
    var npcEl = document.getElementById('attach-target-npc');
    var convEl = document.getElementById('attach-target-conversation');
    var questEl = document.getElementById('attach-target-quest');
    var dialogEl = document.getElementById('attach-target-dialog');
    var optionEl = document.getElementById('attach-target-option');
    var convWrap = document.getElementById('attach-target-conversation-wrap');
    var questWrap = document.getElementById('attach-target-quest-wrap');
    var dialogWrap = document.getElementById('attach-target-dialog-wrap');
    var optionWrap = document.getElementById('attach-target-option-wrap');
    var entityHeading = document.getElementById('attach-entity-heading');
    var entityModeEl = document.getElementById('attach-entity-mode');
    var existingPanel = document.getElementById('attach-existing-panel');
    var newPanel = document.getElementById('attach-new-panel');
    var filterTypeEl = document.getElementById('attach-filter-type');
    var filterRefEl = document.getElementById('attach-filter-ref-table');
    var pickReqEl = document.getElementById('attach-pick-requirement');
    var pickConsEl = document.getElementById('attach-pick-consequence');
    var pickReqWrap = document.getElementById('attach-pick-requirement-wrap');
    var pickConsWrap = document.getElementById('attach-pick-consequence-wrap');
    var newRefTableEl = document.getElementById('attach-new-ref-table');
    var newIdRefEl = document.getElementById('attach-new-id-ref');
    var newRefDescEl = document.getElementById('attach-new-ref-description');
    var submitBtn = document.getElementById('attach-submit-btn');
    var newReqTypeWrap = document.getElementById('attach-new-req-type-wrap');
    var newConsTypeWrap = document.getElementById('attach-new-cons-type-wrap');
    var attachReqLinkParams = document.getElementById('attach-req-link-params');
    var attachConsLinkParams = document.getElementById('attach-cons-link-params');

    function suggestConsequenceRefTableForType(block, consType)
    {
        if (!block)
        {
            return;
        }

        var refTableEl = block.querySelector('.dev-cons-ref-table');
        var idRefEl = block.querySelector('.dev-cons-id-ref');

        if (!refTableEl)
        {
            return;
        }

        var map = {
            '[obtain item]': 'item_types',
            'receive_random_animal': '',
            '[set player_class]': 'PLAYER_CLASS',
            'grant_team_buff': 'buff_definitions',
            '[start quest]': 'QUEST',
            '[complete quest]': 'QUEST',
            '[open shop]': 'shops'
        };

        if (map[consType] !== undefined)
        {
            refTableEl.value = map[consType];
        }

        if (idRefEl)
        {
            var refTable = refTableEl.value;
            var firstMatch = null;

            idRefEl.querySelectorAll('option').forEach(function (opt)
            {
                var match = opt.getAttribute('data-ref-table') === refTable
                    || opt.getAttribute('data-ref-table-alt') === refTable;
                opt.hidden = !match;
                opt.disabled = !match;

                if (match && firstMatch === null)
                {
                    firstMatch = opt;
                }
            });

            if (firstMatch)
            {
                idRefEl.value = firstMatch.value;
            }
        }
    }

    function isConsKind()
    {
        return kindCons && kindCons.checked;
    }

    function filterOptionsByParent(selectEl, attr, parentValue)
    {
        if (!selectEl)
        {
            return null;
        }

        var firstMatch = null;

        selectEl.querySelectorAll('option').forEach(function (opt)
        {
            var match = !parentValue || opt.getAttribute(attr) === parentValue;
            opt.hidden = !match;
            opt.disabled = !match;

            if (match && firstMatch === null)
            {
                firstMatch = opt;
            }
        });

        return firstMatch;
    }

    function isSelectValueEnabled(selectEl, value)
    {
        if (!selectEl || value === '' || value === null)
        {
            return false;
        }

        var opt = selectEl.querySelector('option[value="' + value + '"]');

        return !!(opt && !opt.disabled);
    }

    function selectFirstEnabled(selectEl)
    {
        if (!selectEl)
        {
            return;
        }

        var first = selectEl.querySelector('option:not([disabled])');

        if (first)
        {
            selectEl.value = first.value;
        }
    }

    function syncTargetChain(changedEl)
    {
        var npcId = npcEl ? npcEl.value : '';
        var cons = isConsKind();

        filterOptionsByParent(convEl, 'data-id_npc', npcId);
        filterOptionsByParent(questEl, 'data-id_npc', npcId);

        if (!changedEl || changedEl === npcEl || changedEl === kindReq || changedEl === kindCons || changedEl === targetTypeEl)
        {
            if (convEl && !isSelectValueEnabled(convEl, convEl.value))
            {
                selectFirstEnabled(convEl);
            }

            if (questEl && !isSelectValueEnabled(questEl, questEl.value))
            {
                selectFirstEnabled(questEl);
            }
        }

        var convId = convEl ? convEl.value : '';

        if (cons)
        {
            filterOptionsByParent(dialogEl, 'data-id_conversation', convId);

            if (!changedEl || changedEl === npcEl || changedEl === convEl || changedEl === kindReq || changedEl === kindCons || changedEl === targetTypeEl)
            {
                if (dialogEl && !isSelectValueEnabled(dialogEl, dialogEl.value))
                {
                    selectFirstEnabled(dialogEl);
                }
            }

            var dialogId = dialogEl ? dialogEl.value : '';

            filterOptionsByParent(optionEl, 'data-id_dialog', dialogId);

            if (!changedEl || changedEl === npcEl || changedEl === convEl || changedEl === dialogEl || changedEl === kindReq || changedEl === kindCons || changedEl === targetTypeEl)
            {
                if (optionEl && !isSelectValueEnabled(optionEl, optionEl.value))
                {
                    selectFirstEnabled(optionEl);
                }
            }

            if (changedEl === optionEl && optionEl && convEl)
            {
                var selOpt = optionEl.options[optionEl.selectedIndex];

                if (selOpt && selOpt.getAttribute('data-id_conversation'))
                {
                    convEl.value = selOpt.getAttribute('data-id_conversation');
                }
            }
        }
    }

    function updateTargetVisibility()
    {
        var cons = isConsKind();
        var targetType = targetTypeEl ? targetTypeEl.value : 'npc';

        if (targetTypeWrap)
        {
            targetTypeWrap.classList.toggle('d-none', cons);
        }

        if (cons)
        {
            if (convWrap) convWrap.classList.remove('d-none');
            if (dialogWrap) dialogWrap.classList.remove('d-none');
            if (optionWrap) optionWrap.classList.remove('d-none');
            if (questWrap) questWrap.classList.add('d-none');
        }
        else
        {
            if (convWrap) convWrap.classList.toggle('d-none', targetType !== 'conversation');
            if (questWrap) questWrap.classList.toggle('d-none', targetType !== 'quest');
            if (dialogWrap) dialogWrap.classList.add('d-none');
            if (optionWrap) optionWrap.classList.add('d-none');
        }
    }

    function populateFilterSelect(selectEl, pickEl, attr)
    {
        if (!selectEl || !pickEl)
        {
            return;
        }

        var seen = {};
        var current = selectEl.value;

        while (selectEl.options.length > 1)
        {
            selectEl.remove(1);
        }

        pickEl.querySelectorAll('option').forEach(function (opt)
        {
            var val = opt.getAttribute(attr) || '';

            if (seen[val])
            {
                return;
            }

            seen[val] = true;
            var o = document.createElement('option');
            o.value = val;
            o.textContent = val === '' ? '— empty —' : val;
            selectEl.appendChild(o);
        });

        if (current)
        {
            selectEl.value = current;
        }
    }

    function filterPickList()
    {
        var pickEl = isConsKind() ? pickConsEl : pickReqEl;
        var typeFilter = filterTypeEl ? filterTypeEl.value : '';
        var refFilter = filterRefEl ? filterRefEl.value : '';
        var firstMatch = null;

        if (!pickEl)
        {
            return;
        }

        pickEl.querySelectorAll('option').forEach(function (opt)
        {
            var typeMatch = !typeFilter || opt.getAttribute('data-entity-type') === typeFilter;
            var refVal = opt.getAttribute('data-ref-table') || '';
            var refMatch = !refFilter || refVal === refFilter;
            var match = typeMatch && refMatch;
            opt.hidden = !match;
            opt.disabled = !match;

            if (match && firstMatch === null)
            {
                firstMatch = opt;
            }
        });

        if (firstMatch)
        {
            pickEl.value = firstMatch.value;
        }
    }

    function populateNewRefTableOptions()
    {
        if (!newRefTableEl)
        {
            return;
        }

        var list = isConsKind() ? consRefTables : reqRefTables;
        var current = newRefTableEl.value;
        newRefTableEl.innerHTML = '';

        list.forEach(function (row)
        {
            var o = document.createElement('option');
            o.value = row.value;
            o.textContent = row.label;
            newRefTableEl.appendChild(o);
        });

        if (current)
        {
            newRefTableEl.value = current;
        }
    }

    function filterNewIdRef()
    {
        if (!newRefTableEl || !newIdRefEl)
        {
            return;
        }

        var refTable = newRefTableEl.value;
        var firstMatch = null;

        newIdRefEl.querySelectorAll('option').forEach(function (opt)
        {
            var match = opt.getAttribute('data-ref-table') === refTable
                || opt.getAttribute('data-ref-table-alt') === refTable;
            opt.hidden = !match;
            opt.disabled = !match;

            if (match && firstMatch === null)
            {
                firstMatch = opt;
            }
        });

        if (firstMatch)
        {
            newIdRefEl.value = firstMatch.value;
            syncNewRefDescription();
        }
    }

    function syncNewRefDescription()
    {
        if (!newRefDescEl || !newIdRefEl)
        {
            return;
        }

        var sel = newIdRefEl.options[newIdRefEl.selectedIndex];

        if (sel && sel.getAttribute('data-ref-description'))
        {
            newRefDescEl.value = sel.getAttribute('data-ref-description');
        }
    }

    function updateEntityPanels()
    {
        var cons = isConsKind();
        var isNew = entityModeEl && entityModeEl.value === 'new';

        if (actionEl)
        {
            actionEl.value = cons ? 'attach_consequence' : 'attach_requirement';
        }

        if (entityHeading)
        {
            entityHeading.textContent = cons ? 'Consequence' : 'Requirement';
        }

        if (submitBtn)
        {
            submitBtn.textContent = cons ? 'Attach consequence' : 'Attach requirement';
        }

        if (pickReqWrap) pickReqWrap.classList.toggle('d-none', cons);
        if (pickConsWrap) pickConsWrap.classList.toggle('d-none', !cons);

        if (newReqTypeWrap) newReqTypeWrap.classList.toggle('d-none', cons);
        if (newConsTypeWrap) newConsTypeWrap.classList.toggle('d-none', !cons);
        if (attachReqLinkParams) attachReqLinkParams.classList.toggle('d-none', cons);
        if (attachConsLinkParams) attachConsLinkParams.classList.toggle('d-none', !cons);

        if (filterRefEl && filterRefEl.parentElement && filterRefEl.parentElement.parentElement)
        {
            filterRefEl.parentElement.parentElement.classList.toggle('d-none', true);
        }

        if (existingPanel) existingPanel.classList.toggle('d-none', isNew);
        if (newPanel) newPanel.classList.toggle('d-none', !isNew);

        populateFilterSelect(filterTypeEl, cons ? pickConsEl : pickReqEl, 'data-entity-type');
        populateNewRefTableOptions();
        filterPickList();
        updateTargetVisibility();
        syncTargetChain(null);

        if (cons && pickConsEl)
        {
            var attachConsRefBlock = document.querySelector('#attach-cons-link-params .dev-cons-ref-fields');
            var consOpt = pickConsEl.options[pickConsEl.selectedIndex];

            if (attachConsRefBlock && consOpt)
            {
                suggestConsequenceRefTableForType(attachConsRefBlock, consOpt.getAttribute('data-entity-type') || '');
            }
        }
    }

    function setSubmitFieldState()
    {
        var isNew = entityModeEl && entityModeEl.value === 'new';
        var cons = isConsKind();

        if (pickReqEl) pickReqEl.disabled = isNew || cons;
        if (pickConsEl) pickConsEl.disabled = isNew || !cons;

        if (newPanel)
        {
            newPanel.querySelectorAll('input, select, textarea').forEach(function (el)
            {
                el.disabled = !isNew;
            });
        }

        function setBlockDisabled(block, disabled)
        {
            if (!block)
            {
                return;
            }

            block.querySelectorAll('input, select, textarea').forEach(function (el)
            {
                if (isNew)
                {
                    el.disabled = disabled;
                }
            });
        }

        setBlockDisabled(newReqTypeWrap, cons);
        setBlockDisabled(newConsTypeWrap, !cons);

        if (attachReqLinkParams)
        {
            attachReqLinkParams.querySelectorAll('input, select, textarea').forEach(function (el)
            {
                el.disabled = cons;
            });
        }

        if (attachConsLinkParams)
        {
            attachConsLinkParams.querySelectorAll('input, select, textarea').forEach(function (el)
            {
                el.disabled = !cons;
            });
        }
    }

    if (attachPrefill.target === 'option' || attachPrefill.attach === 'cons')
    {
        if (kindCons) kindCons.checked = true;
    }

    if (attachPrefill.target)
    {
        if (targetTypeEl && attachPrefill.attach !== 'cons')
        {
            targetTypeEl.value = attachPrefill.target;
        }
    }

    [kindReq, kindCons, targetTypeEl, entityModeEl, filterTypeEl, filterRefEl, newRefTableEl].forEach(function (el)
    {
        if (!el)
        {
            return;
        }

        el.addEventListener('change', function ()
        {
            updateEntityPanels();
            setSubmitFieldState();
        });
    });

    [npcEl, convEl, questEl, dialogEl, optionEl].forEach(function (el)
    {
        if (!el)
        {
            return;
        }

        el.addEventListener('change', function ()
        {
            updateTargetVisibility();
            syncTargetChain(el);
            setSubmitFieldState();
        });
    });

    if (newIdRefEl)
    {
        newIdRefEl.addEventListener('change', syncNewRefDescription);
    }

    if (pickConsEl)
    {
        pickConsEl.addEventListener('change', function ()
        {
            if (!isConsKind())
            {
                return;
            }

            var attachConsRefBlock = document.querySelector('#attach-cons-link-params .dev-cons-ref-fields');
            var consOpt = pickConsEl.options[pickConsEl.selectedIndex];

            if (attachConsRefBlock && consOpt)
            {
                suggestConsequenceRefTableForType(attachConsRefBlock, consOpt.getAttribute('data-entity-type') || '');
            }
        });
    }

    var attachNewConsTypeEl = document.getElementById('attach-new-cons-type');

    if (attachNewConsTypeEl)
    {
        attachNewConsTypeEl.addEventListener('change', function ()
        {
            if (!isConsKind())
            {
                return;
            }

            var attachConsRefBlock = document.querySelector('#attach-cons-link-params .dev-cons-ref-fields');

            if (attachConsRefBlock)
            {
                suggestConsequenceRefTableForType(attachConsRefBlock, attachNewConsTypeEl.value);
            }
        });
    }

    form.addEventListener('submit', function ()
    {
        setSubmitFieldState();
    });

    updateEntityPanels();
    setSubmitFieldState();
})();

(function ()
{
    var storageKey = 'dev_npc_preview_lang';
    var initialLang = <?php echo json_encode($preview_lang); ?>;

    function readLang()
    {
        try
        {
            var stored = sessionStorage.getItem(storageKey);

            if (stored === 'en' || stored === 'it' || stored === 'pt')
            {
                return stored;
            }
        }
        catch (e)
        {
            // ignore
        }

        return initialLang;
    }

    function writeLang(lang)
    {
        try
        {
            sessionStorage.setItem(storageKey, lang);
        }
        catch (e)
        {
            // ignore
        }
    }

    function localizedText(el, lang)
    {
        var value = el.getAttribute('data-loc-' + lang);

        if (!value)
        {
            value = el.getAttribute('data-loc-en') || '';
        }

        return value;
    }

    function applyPreviewLang(lang)
    {
        document.querySelectorAll('.dev-loc-text').forEach(function (el)
        {
            el.textContent = localizedText(el, lang);
        });

        document.querySelectorAll('option.dev-loc-option').forEach(function (opt)
        {
            opt.textContent = localizedText(opt, lang);
        });

        document.querySelectorAll('.dev-lang-btn').forEach(function (btn)
        {
            btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
        });
    }

    function syncPreviewLangSession(lang)
    {
        var tokenEl = document.getElementById('dev-token');
        var token = tokenEl ? tokenEl.value : '';
        var url = window.location.pathname + '?T=' + encodeURIComponent(token) + '&lang=' + encodeURIComponent(lang);

        fetch(url, { credentials: 'same-origin' }).catch(function ()
        {
            // ignore
        });
    }

    document.querySelectorAll('.dev-lang-btn').forEach(function (btn)
    {
        btn.addEventListener('click', function ()
        {
            var lang = btn.getAttribute('data-lang') || 'en';

            if (lang !== 'en' && lang !== 'it' && lang !== 'pt')
            {
                lang = 'en';
            }

            writeLang(lang);
            applyPreviewLang(lang);
            syncPreviewLangSession(lang);
        });
    });

    applyPreviewLang(readLang());
})();

(function ()
{
    function syncReqMaxBlock(block)
    {
        var cb = block.querySelector('.dev-req-max-unbounded');
        var input = block.querySelector('.dev-req-max-input');

        if (!cb || !input)
        {
            return;
        }

        input.disabled = cb.checked;

        if (cb.checked)
        {
            input.value = '';
        }
    }

    document.querySelectorAll('.dev-req-range-fields').forEach(function (block)
    {
        var cb = block.querySelector('.dev-req-max-unbounded');

        if (!cb)
        {
            return;
        }

        cb.addEventListener('change', function ()
        {
            syncReqMaxBlock(block);
        });
        syncReqMaxBlock(block);
    });
})();
</script>
</body>
</html>
