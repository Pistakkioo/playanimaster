<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_npc_page_content.php';

dev_admin_require_auth();

$id_npc = isset($_GET['id_npc']) ? (int) $_GET['id_npc'] : 0;
$all_npcs = dev_npc_page_fetch_all($conn);
$bundle = $id_npc > 0 ? dev_npc_page_load($conn, $id_npc) : null;
$token = dev_admin_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — NPC Interactions Dev<?php echo $bundle ? ' · #' . (int) $id_npc : ''; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .meta { color: #94a3b8; font-size: .85rem; }
        .npc-picker { max-width: 420px; }

        .npc-interaction-tree {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .npc-flow-chain {
            margin-bottom: 1.5rem;
            overflow-x: auto;
            padding-bottom: .5rem;
        }

        .npc-flow-columns {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 0;
            min-width: min-content;
        }

        .npc-flow-col {
            flex: 0 0 auto;
            width: min(100%, 22rem);
            min-width: 16rem;
            position: relative;
            padding-right: 2.25rem;
        }

        .npc-flow-col:not(:last-child)::after {
            content: '→';
            position: absolute;
            top: 1.25rem;
            right: .35rem;
            color: #74c0fc;
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1;
            pointer-events: none;
        }

        .npc-flow-col[data-column="0"] .npc-conv-card {
            border-color: #495057;
        }

        .npc-flow-col:not([data-column="0"]) .npc-conv-card {
            border-color: #3d5a80;
            box-shadow: 0 4px 14px rgba(116, 192, 252, .12);
        }

        .npc-section-title {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #74c0fc;
            margin-bottom: .75rem;
            padding-bottom: .35rem;
            border-bottom: 1px solid #2d3a4d;
        }

        .npc-interaction-node {
            position: relative;
        }

        .npc-interaction-nested {
            margin-left: calc(1.25rem + var(--depth, 0) * 0.5rem);
            padding-left: 1rem;
            border-left: 2px dashed #495057;
        }

        .npc-else-connector {
            display: flex;
            align-items: center;
            gap: .65rem;
            margin: .75rem 0 .5rem;
            color: #ff922b;
            font-size: .78rem;
            font-weight: 600;
        }

        .npc-else-svg {
            width: 24px;
            height: 40px;
            flex-shrink: 0;
        }

        .npc-else-label {
            background: rgba(255, 146, 43, .15);
            border: 1px solid #ff922b;
            border-radius: 4px;
            padding: .1rem .45rem;
        }

        .npc-else-hint {
            color: #adb5bd;
            font-weight: 400;
        }

        .npc-conv-card {
            border: 2px solid #3d5a80;
            border-radius: 10px;
            background: linear-gradient(180deg, #1e3348 0%, #161f2e 100%);
            padding: 1rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, .22);
        }

        .npc-interaction-else > .npc-conv-card {
            border-color: #ff922b;
            background: linear-gradient(180deg, #2b2118 0%, #161f2e 100%);
        }

        .npc-conv-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .75rem;
            margin-bottom: .85rem;
            padding-bottom: .65rem;
            border-bottom: 1px solid #2d3a4d;
        }

        .npc-conv-title {
            font-size: 1rem;
            margin: 0;
            color: #8ec8f8;
        }

        .npc-block-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #94a3b8;
            margin-bottom: .4rem;
        }

        .npc-req-block,
        .npc-dialog-block,
        .npc-cons-block {
            margin-bottom: .85rem;
        }

        .npc-req-none {
            padding: .5rem .65rem;
            background: rgba(81, 207, 102, .08);
            border-radius: 6px;
            border-left: 3px solid #51cf66;
        }

        .npc-req-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .npc-req-list li {
            padding: .35rem .55rem;
            margin-bottom: .25rem;
            background: rgba(0, 0, 0, .18);
            border-radius: 4px;
            border-left: 3px solid #4dabf7;
            font-size: .82rem;
        }

        .npc-req-list li.npc-req-not-met {
            border-left-color: #ff922b;
            background: rgba(255, 146, 43, .08);
        }

        .npc-dialog-line {
            font-size: .82rem;
            padding: .45rem .55rem;
            margin-bottom: .35rem;
            background: rgba(92, 124, 250, .06);
            border-radius: 6px;
            border-left: 3px solid #5c7cfa;
        }

        .npc-dialog-order {
            color: #748ffc;
            font-weight: 600;
            margin-right: .35rem;
        }

        .npc-option {
            margin-top: .35rem;
            padding: .35rem .5rem;
            border-radius: 4px;
            font-size: .78rem;
            background: rgba(0, 0, 0, .2);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: .35rem .75rem;
        }

        .npc-option-green { border-left: 3px solid #51cf66; }
        .npc-option-red { border-left: 3px solid #ff6b6b; }

        .npc-option-main {
            flex: 1 1 12rem;
            min-width: 0;
        }

        .npc-cons-list {
            flex: 0 1 auto;
            margin-top: 0;
            margin-left: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: .2rem;
            max-width: 100%;
        }

        .npc-cons-block {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: .25rem .35rem;
        }

        .npc-cons-block .npc-block-label {
            flex: 1 1 100%;
            margin-bottom: .25rem;
        }

        .npc-cons-chip {
            display: inline-block;
            font-size: .72rem;
            padding: .12rem .45rem;
            border-radius: 4px;
            margin: 0;
            background: #3d2f5a;
            color: #e599f7;
            white-space: nowrap;
        }

        .npc-else-branches {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .legend-swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: .35rem;
            vertical-align: middle;
        }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">NPC Interactions Dev</h1>
            <p class="meta mb-0">Conversation tree for one NPC: requirements, dialogues, consequences, ELSE fallbacks, and horizontal progression columns.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <form method="get" class="d-flex gap-2 npc-picker align-items-center">
                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                <select class="form-select form-select-sm" name="id_npc" onchange="this.form.submit()">
                    <option value="0">— pick NPC —</option>
                    <?php foreach ($all_npcs as $n): ?>
                    <option value="<?php echo (int) $n['id_npc']; ?>"<?php echo $id_npc === (int) $n['id_npc'] ? ' selected' : ''; ?>>
                        #<?php echo (int) $n['id_npc']; ?> <?php echo dev_admin_h((string) $n['npc']); ?>
                        (<?php echo (int) $n['conversation_count']; ?> conv)
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">View</button>
            </form>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php')); ?>">NPC content</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_quest.php')); ?>">Quest flow</a>
        </div>
    </div>

    <?php if ($id_npc <= 0): ?>
    <div class="card dev-card">
        <div class="card-body">
            <p class="mb-2">Select an NPC via <code>?id_npc=</code> or the dropdown above.</p>
            <ul class="mb-0">
                <?php foreach ($all_npcs as $n): ?>
                <li>
                    <a href="<?php echo dev_admin_h(dev_npc_page_url(['id_npc' => (int) $n['id_npc']])); ?>">
                        #<?php echo (int) $n['id_npc']; ?> <?php echo dev_admin_h((string) $n['npc']); ?>
                    </a>
                    <span class="meta"> — <?php echo (int) $n['conversation_count']; ?> conversations · zone <?php echo dev_admin_h((string) ($n['scene_name'] ?? '')); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php elseif (!$bundle): ?>
    <div class="alert alert-danger">NPC #<?php echo (int) $id_npc; ?> not found.</div>

    <?php else:
        $npc = $bundle['npc'];
        $lookup = $bundle['lookup'];
    ?>
    <div class="card dev-card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>#<?php echo (int) $npc['id_npc']; ?> <?php echo dev_admin_h((string) $npc['npc']); ?></strong>
                <span class="meta ms-2"><?php echo dev_admin_h((string) ($npc['scene_name'] ?? '')); ?> · <?php echo dev_admin_h((string) ($npc['type'] ?? '')); ?></span>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php', ['edit' => 'npc', 'id' => (int) $id_npc])); ?>">Edit NPC</a>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_npc_page_url(['id_npc' => (int) $id_npc])); ?>">Refresh</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <p class="meta mb-1">
                        Position (<?php echo (int) $npc['posx']; ?>, <?php echo (int) $npc['posy']; ?>, <?php echo (int) $npc['posz']; ?>)
                        · prefab <?php echo dev_admin_h((string) ($npc['npc_type_prefab'] ?? '')); ?>
                    </p>
                    <?php if ($bundle['npc_requirements']): ?>
                    <p class="mb-1"><strong class="small">NPC-level gates</strong> <span class="meta">(must pass before any conversation is listed in-game)</span></p>
                    <ul class="small mb-0">
                        <?php foreach ($bundle['npc_requirements'] as $req): ?>
                        <li><?php echo dev_admin_h(dev_npc_page_format_npc_requirement($req, $lookup)); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <p class="meta mb-1"><strong>Legend</strong></p>
                    <div class="meta">
                        <span class="legend-swatch" style="background:#51cf66;border:1px solid #51cf66"></span> Ungated conversation<br>
                        <span class="legend-swatch" style="background:#4dabf7;border:1px solid #4dabf7"></span> Requirement (AND)<br>
                        <span class="legend-swatch" style="background:#ff922b;border:1px solid #ff922b"></span> ELSE / not-met fallback<br>
                        <span class="legend-swatch" style="border:1px solid #5c7cfa;background:transparent"></span> Dialogue line<br>
                        <span class="legend-swatch" style="border:1px solid #74c0fc;background:transparent"></span> Column → unlocked by prior consequence
                    </div>
                    <p class="meta mb-1 mt-2"><strong>Consequence → requirement</strong></p>
                    <ul class="meta small mb-0 ps-3">
                        <li><code>[start quest]</code> → <code>quest started</code> / <code>quest phase</code> min=1</li>
                        <li><code>[complete quest]</code> → <code>quest completed</code></li>
                        <li>Phase advance → <code>quest phase</code> / <code>quest phase completed</code> (min = phase #)</li>
                        <li><code>[set player_class]</code> → <code>player class</code></li>
                        <li><code>[obtain item]</code> → <code>item</code></li>
                        <li>Finish registered conv → <code>conversation finished</code></li>
                        <li>Last quest phase conv → <code>quest ready to turn in</code></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="npc-interaction-tree">
                <?php if ($bundle['ungated']): ?>
                <section>
                    <h2 class="npc-section-title">1 · Conversations without requirements</h2>
                    <?php foreach ($bundle['ungated'] as $node): ?>
                    <?php dev_npc_page_render_interaction_node($node, $lookup, 0, false); ?>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <?php if ($bundle['gated_flow_chains']): ?>
                <section>
                    <h2 class="npc-section-title">2 · Gated conversations<?php echo $bundle['ungated'] ? '' : ''; ?></h2>
                    <p class="meta small mb-3">Each row is a progression chain. Columns shift right when a conversation’s requirements are unlocked by consequences (or completion) of an earlier conversation on this NPC.</p>
                    <?php foreach ($bundle['gated_flow_chains'] as $chain): ?>
                    <?php dev_npc_page_render_flow_chain($chain, $lookup); ?>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <?php if ($bundle['orphan_fallbacks']): ?>
                <section>
                    <h2 class="npc-section-title">Orphan fallbacks</h2>
                    <p class="meta small">These reference a primary conversation on another NPC (or missing id).</p>
                    <?php foreach ($bundle['orphan_fallbacks'] as $node): ?>
                    <?php dev_npc_page_render_interaction_node($node, $lookup, 0, true); ?>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <?php if (!$bundle['ungated'] && !$bundle['gated_flow_chains'] && !$bundle['orphan_fallbacks']): ?>
                <p class="meta mb-0">No conversations on this NPC.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($bundle['quests']): ?>
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Quests started here</strong></div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <?php foreach ($bundle['quests'] as $q): ?>
                        <li>
                            <a href="<?php echo dev_admin_h(dev_admin_page_url('dev_quest.php', ['id_quest' => (int) $q['id_quest']])); ?>">
                                #<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h((string) $q['quest']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <div class="card dev-card">
                <div class="card-header"><strong>All conversations</strong></div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <?php foreach ($bundle['conversations'] as $id_conv => $conv_bundle): ?>
                        <li>
                            #<?php echo (int) $id_conv; ?> <?php echo dev_admin_h((string) $conv_bundle['conversation']['title']); ?>
                            · <?php echo count($conv_bundle['requirements']); ?> req
                            <?php if ($conv_bundle['fallback_parent_id']): ?>
                            <span class="meta">· fallback → #<?php echo (int) $conv_bundle['fallback_parent_id']; ?></span>
                            <?php endif; ?>
                            <?php
                            foreach ($bundle['gated_flow_chains'] as $chain)
                            {
                                if (isset($chain['column_by_conversation'][$id_conv]))
                                {
                                    echo '<span class="meta"> · col ' . (int) $chain['column_by_conversation'][$id_conv] . '</span>';
                                    break;
                                }
                            }
                            ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
