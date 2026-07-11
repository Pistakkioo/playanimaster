<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_quest_content.php';

dev_admin_require_auth();

$id_quest = isset($_GET['id_quest']) ? (int) $_GET['id_quest'] : 0;
$all_quests = dev_quest_fetch_all($conn);
$bundle = $id_quest > 0 ? dev_quest_load($conn, $id_quest) : null;
$token = dev_admin_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — Quest Flow Dev<?php echo $bundle ? ' · #' . (int) $id_quest : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .meta { color: #94a3b8; font-size: .85rem; }
        .quest-picker { max-width: 420px; }

        /* ── Flow diagram ── */
        .quest-flow-wrap {
            overflow-x: auto;
            padding: 1rem 0 2rem;
        }

        .quest-flow {
            display: flex;
            align-items: stretch;
            gap: 0;
            min-width: min-content;
            position: relative;
        }

        .flow-column {
            display: flex;
            flex-direction: column;
            min-width: 220px;
            max-width: 280px;
            flex-shrink: 0;
        }

        .flow-connector {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            flex-shrink: 0;
            position: relative;
        }

        .flow-connector svg {
            width: 48px;
            height: 24px;
            overflow: visible;
        }

        .flow-connector line,
        .flow-connector path {
            stroke: #4dabf7;
            stroke-width: 2;
            fill: none;
        }

        .flow-connector polygon {
            fill: #4dabf7;
        }

        .flow-node {
            border: 2px solid #3d5a80;
            border-radius: 10px;
            background: linear-gradient(180deg, #1e3348 0%, #161f2e 100%);
            padding: .85rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: .65rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .25);
        }

        .flow-node-accept { border-color: #51cf66; }
        .flow-node-phase { border-color: #4dabf7; }
        .flow-node-turn_in { border-color: #ffd43b; }
        .flow-node-complete { border-color: #868e96; min-width: 140px; max-width: 160px; }

        .flow-node-head {
            border-bottom: 1px solid #2d3a4d;
            padding-bottom: .5rem;
            margin-bottom: .15rem;
        }

        .flow-node-head h3 {
            font-size: .95rem;
            margin: 0 0 .2rem;
            color: #8ec8f8;
            font-weight: 600;
        }

        .flow-node-head .phase-num {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
        }

        .flow-subtitle {
            font-size: .75rem;
            color: #adb5bd;
            line-height: 1.35;
        }

        .flow-objective {
            border-left: 3px solid #69db7c;
            padding: .45rem .55rem;
            background: rgba(105, 219, 132, .08);
            border-radius: 0 6px 6px 0;
            font-size: .78rem;
        }

        .flow-objective-type {
            font-weight: 600;
            color: #69db7c;
            text-transform: uppercase;
            font-size: .65rem;
            letter-spacing: .04em;
        }

        .flow-objective-target {
            color: #e7ecf1;
            margin-top: .15rem;
        }

        .flow-objective-count {
            color: #94a3b8;
            font-size: .72rem;
        }

        .flow-conv {
            border: 1px dashed #5c7cfa;
            border-radius: 8px;
            padding: .55rem;
            background: rgba(92, 124, 250, .07);
            font-size: .78rem;
        }

        .flow-conv-title {
            font-weight: 600;
            color: #91a7ff;
        }

        .flow-conv-npc {
            color: #94a3b8;
            font-size: .72rem;
        }

        .flow-conv-links {
            margin-top: .35rem;
            font-size: .68rem;
            color: #adb5bd;
        }

        .flow-conv-links code {
            color: #ffd43b;
            font-size: .65rem;
        }

        .flow-option {
            margin-top: .35rem;
            padding: .3rem .4rem;
            border-radius: 4px;
            font-size: .7rem;
            background: rgba(0, 0, 0, .2);
        }

        .flow-option-green { border-left: 3px solid #51cf66; }
        .flow-option-red { border-left: 3px solid #ff6b6b; }

        .flow-complete-icon {
            text-align: center;
            font-size: 2rem;
            color: #868e96;
            padding: 1rem 0;
        }

        .legend-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: .35rem;
            vertical-align: middle;
        }

        .conv-detail-card {
            border-left: 4px solid #5c7cfa;
            margin-bottom: 1rem;
        }

        .conv-detail-card.accept { border-left-color: #51cf66; }
        .conv-detail-card.turn_in { border-left-color: #ffd43b; }
        .conv-detail-card.talk_objective { border-left-color: #74c0fc; }

        .req-chip, .cons-chip {
            display: inline-block;
            font-size: .72rem;
            padding: .15rem .45rem;
            border-radius: 4px;
            margin: .1rem .15rem .1rem 0;
            background: #243a52;
            color: #ced4da;
        }

        .cons-chip { background: #3d2f5a; color: #e599f7; }

        .objective-connector {
            display: flex;
            align-items: center;
            gap: .35rem;
            font-size: .68rem;
            color: #74c0fc;
            margin: .25rem 0;
        }

        .objective-connector svg {
            width: 20px;
            height: 12px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Quest Flow Dev</h1>
            <p class="meta mb-0">Visual quest pipeline: phases, objectives, linked conversations, and quest-gated drops.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <form method="get" class="d-flex gap-2 quest-picker align-items-center">
                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                <select class="form-select form-select-sm" name="id_quest" onchange="this.form.submit()">
                    <option value="0">— pick quest —</option>
                    <?php foreach ($all_quests as $q): ?>
                    <option value="<?php echo (int) $q['id_quest']; ?>"<?php echo $id_quest === (int) $q['id_quest'] ? ' selected' : ''; ?>>
                        #<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h($q['quest']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">View</button>
            </form>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php')); ?>">NPC content</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npc.php')); ?>">NPC interactions</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>">Species / drops</a>
        </div>
    </div>

    <?php if ($id_quest <= 0): ?>
    <div class="card dev-card">
        <div class="card-body">
            <p class="mb-2">Select a quest via <code>?id_quest=</code> or the dropdown above.</p>
            <ul class="mb-0">
                <?php foreach ($all_quests as $q): ?>
                <li>
                    <a href="<?php echo dev_admin_h(dev_quest_page_url(['id_quest' => (int) $q['id_quest']])); ?>">
                        #<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h($q['quest']); ?>
                    </a>
                    <?php if (!empty($q['starter_npc_name'])): ?>
                    <span class="meta"> — giver: <?php echo dev_admin_h($q['starter_npc_name']); ?></span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php elseif (!$bundle): ?>
    <div class="alert alert-danger">Quest #<?php echo (int) $id_quest; ?> not found.</div>

    <?php else:
        $quest = $bundle['quest'];
        $flow = $bundle['flow'];
        $conversations = $bundle['conversations'];
        $quest_drops = $bundle['quest_drops'];
        $max_phase = (int) $bundle['max_phase'];
    ?>
    <div class="card dev-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>#<?php echo (int) $quest['id_quest']; ?> <?php echo dev_admin_h($quest['quest']); ?></strong>
                <?php if (!empty($quest['quest_it'])): ?>
                <span class="meta ms-2"><?php echo dev_admin_h($quest['quest_it']); ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'quest', 'id' => (int) $quest['id_quest']])); ?>">Edit quest</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_quest_page_url(['id_quest' => (int) $id_quest])); ?>">Refresh</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <p class="mb-1"><?php echo dev_admin_h((string) ($quest['description'] ?? '')); ?></p>
                    <p class="meta mb-0">
                        Giver: <strong><?php echo dev_admin_h((string) ($quest['starter_npc_name'] ?? '?')); ?></strong>
                        (NPC #<?php echo (int) $quest['id_starter_npc']; ?>)
                        · type <?php echo dev_admin_h((string) ($quest['quest_type'] ?? '')); ?>
                        · lvl <?php echo (int) $quest['lvl_min']; ?>–<?php echo (int) $quest['lvl_max']; ?>
                        · <?php echo ($quest['repeatable'] ?? 'N') === 'S' ? 'repeatable' : 'one-shot'; ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <p class="meta mb-1"><strong>Legend</strong></p>
                    <div class="meta">
                        <span class="legend-dot" style="background:#51cf66;border:1px solid #51cf66"></span> Accept<br>
                        <span class="legend-dot" style="background:#4dabf7;border:1px solid #4dabf7"></span> Phase<br>
                        <span class="legend-dot" style="background:#ffd43b;border:1px solid #ffd43b"></span> Turn in<br>
                        <span class="legend-dot" style="background:#69db7c;border:1px solid #69db7c"></span> Objective<br>
                        <span class="legend-dot" style="border:1px dashed #5c7cfa;background:transparent"></span> Conversation
                    </div>
                </div>
            </div>

            <p class="meta mb-2">
                Runtime: each phase completes when <em>all</em> objectives in that phase are satisfied → phase increments.
                After phase <?php echo $max_phase; ?> the quest enters synthetic phase <strong><?php echo (int) $flow['awaiting_turn_in_phase']; ?></strong> (ready to turn in).
            </p>

            <div class="quest-flow-wrap">
                <div class="quest-flow">
                    <?php
                    $columns = $flow['columns'];
                    $col_count = count($columns);

                    foreach ($columns as $col_index => $col):
                        $kind = (string) $col['kind'];
                        $node_class = 'flow-node';

                        if ($kind === 'accept')
                        {
                            $node_class .= ' flow-node-accept';
                        }
                        elseif ($kind === 'phase')
                        {
                            $node_class .= ' flow-node-phase';
                        }
                        elseif ($kind === 'turn_in')
                        {
                            $node_class .= ' flow-node-turn_in';
                        }
                        else
                        {
                            $node_class .= ' flow-node-complete';
                        }
                    ?>
                    <?php if ($col_index > 0): ?>
                    <div class="flow-connector" aria-hidden="true">
                        <svg viewBox="0 0 48 24">
                            <line x1="0" y1="12" x2="36" y2="12"/>
                            <polygon points="36,8 48,12 36,16"/>
                        </svg>
                    </div>
                    <?php endif; ?>

                    <div class="flow-column">
                        <div class="<?php echo dev_admin_h($node_class); ?>">
                            <div class="flow-node-head">
                                <?php if ($kind === 'phase'): ?>
                                <div class="phase-num">Phase <?php echo (int) $col['phase']; ?></div>
                                <?php endif; ?>
                                <h3><?php echo dev_admin_h((string) $col['title']); ?></h3>
                                <div class="flow-subtitle"><?php echo dev_admin_h((string) $col['subtitle']); ?></div>
                            </div>

                            <?php if ($kind === 'complete'): ?>
                            <div class="flow-complete-icon" title="Quest completed">✓</div>
                            <?php else: ?>

                            <?php foreach ($col['objectives'] as $obj_view):
                                $obj = $obj_view['objective'];
                                $linked_conv_id = ($obj_view['type'] === 'talk_npc') ? (int) $obj_view['target_ref'] : 0;
                            ?>
                            <div class="flow-objective">
                                <div class="flow-objective-type"><?php echo dev_admin_h(str_replace('_', ' ', $obj_view['type'])); ?></div>
                                <?php if ($obj_view['target_label'] !== ''): ?>
                                <div class="flow-objective-target"><?php echo dev_admin_h($obj_view['target_label']); ?></div>
                                <?php endif; ?>
                                <div class="flow-objective-count">×<?php echo (int) $obj_view['target_count']; ?>
                                    <?php if ($obj_view['description'] !== ''): ?>
                                    · <?php echo dev_admin_h($obj_view['description']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($linked_conv_id > 0 && isset($conversations[$linked_conv_id])):
                                    $talk_bundle = $conversations[$linked_conv_id];
                                    $talk_conv = $talk_bundle['conversation'];
                                ?>
                                <div class="objective-connector">
                                    <svg viewBox="0 0 20 12"><line x1="0" y1="6" x2="14" y2="6" stroke="#74c0fc" stroke-width="1.5"/><polygon points="14,3 20,6 14,9" fill="#74c0fc"/></svg>
                                    <span>talk → conv #<?php echo $linked_conv_id; ?></span>
                                </div>
                                <?php dev_quest_render_flow_conversation($talk_bundle, true); ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>

                            <?php foreach ($col['conversations'] as $id_conv => $conv_bundle):
                                if ($kind === 'phase')
                                {
                                    $is_talk_linked = false;

                                    foreach ($col['objectives'] as $obj_view)
                                    {
                                        if ($obj_view['type'] === 'talk_npc'
                                            && (int) $obj_view['target_ref'] === (int) $id_conv)
                                        {
                                            $is_talk_linked = true;
                                            break;
                                        }
                                    }

                                    if ($is_talk_linked)
                                    {
                                        continue;
                                    }
                                }

                                dev_quest_render_flow_conversation($conv_bundle, false);
                            endforeach; ?>

                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card dev-card">
                <div class="card-header">
                    <strong>Related conversations</strong>
                    <span class="badge bg-secondary ms-2"><?php echo count($conversations); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$conversations): ?>
                    <p class="meta mb-0">No conversations linked via quest requirements, consequences, or talk_npc objectives.</p>
                    <?php else: ?>
                    <?php foreach ($conversations as $id_conv => $conv_bundle):
                        $conv = $conv_bundle['conversation'];
                        $card_class = 'conv-detail-card';

                        foreach (['accept', 'turn_in', 'talk_objective'] as $role)
                        {
                            if (in_array($role, $conv_bundle['roles'] ?? [], true))
                            {
                                $card_class .= ' ' . $role;
                                break;
                            }
                        }
                    ?>
                    <div class="<?php echo dev_admin_h($card_class); ?> p-3 mb-3 rounded" style="background:#161f2e">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                            <div>
                                <strong>#<?php echo (int) $id_conv; ?> <?php echo dev_admin_h((string) $conv['title']); ?></strong>
                                <div class="meta">
                                    NPC <?php echo dev_admin_h((string) ($conv['npc'] ?? '?')); ?> (#<?php echo (int) $conv['id_npc']; ?>)
                                    · register <?php echo dev_admin_h((string) ($conv['flg_register'] ?? 'N')); ?>
                                    · repeatable <?php echo dev_admin_h((string) ($conv['flg_repeatable'] ?? 'N')); ?>
                                </div>
                            </div>
                            <div>
                                <?php foreach (dev_quest_conversation_role_badges($conv_bundle) as $badge): ?>
                                <span class="badge <?php echo dev_admin_h($badge[1]); ?>"><?php echo dev_admin_h($badge[0]); ?></span>
                                <?php endforeach; ?>
                                <a class="btn btn-outline-secondary btn-sm ms-1" href="<?php echo dev_admin_h(dev_quest_npc_edit_url((int) $conv['id_npc'], (int) $id_conv)); ?>">Edit</a>
                            </div>
                        </div>

                        <?php if ($conv_bundle['quest_links']): ?>
                        <p class="mb-2"><span class="meta">Quest links:</span>
                            <?php foreach ($conv_bundle['quest_links'] as $link): ?>
                            <span class="req-chip"><?php echo dev_admin_h($link['kind'] . ': ' . $link['label']); ?></span>
                            <?php endforeach; ?>
                        </p>
                        <?php endif; ?>

                        <?php if ($conv_bundle['requirements']): ?>
                        <p class="mb-1 meta">Requirements</p>
                        <div class="mb-2">
                            <?php foreach ($conv_bundle['requirements'] as $req): ?>
                            <span class="req-chip" title="<?php echo dev_admin_h((string) ($req['descrizione'] ?? '')); ?>">
                                <?php echo dev_admin_h((string) $req['requirement_type']); ?>
                                <?php if (!empty($req['ref_table'])): ?> · <?php echo dev_admin_h((string) $req['ref_table']); ?><?php endif; ?>
                                <?php if ((int) ($req['id_ref'] ?? 0) > 0): ?> #<?php echo (int) $req['id_ref']; ?><?php endif; ?>
                                <?php if ((int) ($req['min'] ?? 0) > 0 || ($req['max'] ?? '') !== '' && $req['max'] !== null): ?>
                                · min <?php echo (int) $req['min']; ?><?php if ($req['max'] !== '' && $req['max'] !== null): ?> max <?php echo (int) $req['max']; ?><?php endif; ?>
                                <?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($conv_bundle['consequences']): ?>
                        <p class="mb-1 meta">Consequences (on option)</p>
                        <div class="mb-2">
                            <?php foreach ($conv_bundle['consequences'] as $cons): ?>
                            <span class="cons-chip">
                                <?php echo dev_admin_h((string) $cons['consequence_type']); ?>
                                <?php if (!empty($cons['option_text'])): ?> · “<?php echo dev_admin_h((string) $cons['option_text']); ?>”<?php endif; ?>
                                <?php if ((int) ($cons['id_ref'] ?? 0) > 0): ?> · ref #<?php echo (int) $cons['id_ref']; ?><?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($conv_bundle['dialogues']): ?>
                        <details class="mt-2">
                            <summary class="meta" style="cursor:pointer">Dialogue lines (<?php echo count($conv_bundle['dialogues']); ?>)</summary>
                            <ol class="small mt-2 mb-0 ps-3">
                                <?php foreach ($conv_bundle['dialogues'] as $dlg): ?>
                                <li class="mb-1">
                                    <?php echo dev_admin_h(mb_strimwidth((string) ($dlg['dialog'] ?? ''), 0, 120, '…')); ?>
                                    <?php if (($dlg['flg_options'] ?? 'N') === 'S'): ?>
                                    <span class="badge bg-secondary">options</span>
                                    <?php
                                    $id_dialog = (int) $dlg['id_dialog'];

                                    if (!empty($conv_bundle['options_by_dialog'][$id_dialog])):
                                        foreach ($conv_bundle['options_by_dialog'][$id_dialog] as $opt):
                                            $color = (string) ($opt['option_color'] ?? '');
                                    ?>
                                    <div class="flow-option flow-option-<?php echo dev_admin_h($color === 'green' ? 'green' : ($color === 'red' ? 'red' : 'green')); ?>">
                                        <?php echo dev_admin_h((string) ($opt['option_text'] ?? '')); ?>
                                        (opt #<?php echo (int) $opt['id_dialog_option']; ?>)
                                    </div>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ol>
                        </details>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Objectives by phase</strong></div>
                <div class="card-body">
                    <?php foreach ($bundle['objectives_by_phase'] as $phase => $objs): ?>
                    <h6 class="text-info">Phase <?php echo (int) $phase; ?></h6>
                    <ul class="small">
                        <?php foreach ($objs as $obj):
                            $view = dev_quest_objective_view($obj, $bundle['lookup']);
                        ?>
                        <li>
                            <code><?php echo dev_admin_h($view['type']); ?></code>
                            <?php if ($view['target_label'] !== ''): ?> — <?php echo dev_admin_h($view['target_label']); ?><?php endif; ?>
                            ×<?php echo (int) $view['target_count']; ?>
                            <a class="ms-1" href="<?php echo dev_admin_h(dev_admin_url(['edit' => 'quest_objective', 'id' => (int) $obj['id_quest_objective']])); ?>">✎</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endforeach; ?>
                    <p class="meta mb-0">Then → phase <?php echo (int) $flow['awaiting_turn_in_phase']; ?> (awaiting turn-in)</p>
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header"><strong>Quest-gated wild drops</strong></div>
                <div class="card-body">
                    <?php if (!$quest_drops): ?>
                    <p class="meta mb-0">No <code>wild_animal_drop_types</code> rows with <code>id_quest_required = <?php echo (int) $id_quest; ?></code>.</p>
                    <?php else: ?>
                    <ul class="small mb-0">
                        <?php foreach ($quest_drops as $drop): ?>
                        <li>
                            #<?php echo (int) $drop['id_wild_animal_drop_type']; ?>
                            · <?php echo dev_admin_h((string) ($drop['species'] ?? '?')); ?>
                            <?php if ((int) ($drop['id_element'] ?? 0) > 0): ?>
                            · <?php echo dev_admin_h((string) ($drop['element'] ?? 'elem')); ?> only
                            <?php endif; ?>
                            → <?php echo dev_admin_h((string) ($drop['item_name'] ?? 'item')); ?>
                            · <?php echo (int) $drop['chance']; ?>%
                            <a href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'wild_drop', 'edit' => 'wild_drop', 'id' => (int) $drop['id_wild_animal_drop_type']])); ?>">✎</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>