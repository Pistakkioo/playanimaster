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

    header('Location: ' . dev_admin_url([
        'msg' => $flash,
        'ok' => $flash_ok ? '1' : '0'
    ]));
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
$token = dev_admin_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — NPC / Dialog Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0f1419; color: #e7ecf1; }
        .dev-card { background: #1a2332; border: 1px solid #2d3a4d; }
        .dev-card .card-header { background: #243044; border-bottom: 1px solid #2d3a4d; }
        .tree-npc { border-left: 3px solid #4dabf7; margin-bottom: 1.25rem; padding-left: .75rem; }
        .tree-conv { border-left: 3px solid #69db7c; margin: .75rem 0 .75rem .75rem; padding-left: .75rem; }
        .tree-dlg { border-left: 3px solid #ffd43b; margin: .5rem 0 .5rem .75rem; padding-left: .75rem; }
        .tree-opt { border-left: 3px solid #ff922b; margin: .35rem 0 .35rem .75rem; padding-left: .75rem; }
        .meta { color: #94a3b8; font-size: .85rem; }
        .badge-req { background: #495057; }
        .badge-cons { background: #862e9c; }
        .schema-box { font-size: .85rem; color: #adb5bd; }
        .form-control, .form-select { background: #0f1419; color: #e7ecf1; border-color: #495057; }
        .form-control:focus, .form-select:focus { background: #0f1419; color: #fff; border-color: #4dabf7; box-shadow: 0 0 0 .2rem rgba(77,171,247,.2); }
        .nav-pills .nav-link { color: #adb5bd; }
        .nav-pills .nav-link.active { background: #364fc7; }
        details summary { cursor: pointer; }

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
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_url()); ?>">Refresh</a>
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
                    <code>conversations</code> → <code>dialogues</code> (order) → <code>dialogues_options</code><br>
                    <code>conversation_consequences</code> links <code>id_conversation</code> + <code>id_option</code> → <code>consequences</code><br>
                    <code>npcs</code> → <code>quests</code> (id_starter_npc) → <code>quest_requirements</code><br>
                    <code>user_conversations</code> / <code>user_quests</code> track player progress (read-only here).
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
                        </summary>

                        <?php if ($npc['requirements']): ?>
                        <div class="mt-2">
                            <span class="badge badge-req">NPC requirements</span>
                            <ul class="small mb-2">
                                <?php foreach ($npc['requirements'] as $req): ?>
                                <li>#<?php echo (int) $req['id_requirement']; ?> <?php echo dev_admin_h($req['requirement_type']); ?> — <?php echo dev_admin_h($req['descrizione']); ?> [<?php echo (int) $req['min']; ?>–<?php echo (int) $req['max']; ?>]</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <?php if ($npc['quests']): ?>
                        <div class="mb-2">
                            <span class="badge bg-info text-dark">Quests</span>
                            <?php foreach ($npc['quests'] as $id_quest => $quest): ?>
                            <div class="ms-2 mt-1">
                                <strong>#<?php echo (int) $id_quest; ?> <?php echo dev_admin_h($quest['quest']); ?></strong>
                                <span class="meta"> (<?php echo dev_admin_h($quest['quest_type']); ?>, lvl <?php echo (int) $quest['lvl_min']; ?>–<?php echo (int) $quest['lvl_max']; ?>)</span>
                                <?php if (!empty($quest['requirements'])): ?>
                                <ul class="small mb-0">
                                    <?php foreach ($quest['requirements'] as $req): ?>
                                    <li>req #<?php echo (int) $req['id_requirement']; ?>: <?php echo dev_admin_h($req['descrizione']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
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
                                <strong>Conv #<?php echo (int) $id_conv; ?>: <?php echo dev_admin_h($conv['title']); ?></strong>
                                <span class="meta"> — visible <?php echo dev_admin_h($conv['visible']); ?>, register <?php echo dev_admin_h($conv['flg_register']); ?></span>
                            </summary>

                            <?php if ($conv['requirements']): ?>
                            <ul class="small mt-2">
                                <?php foreach ($conv['requirements'] as $req): ?>
                                <li><span class="badge badge-req">req</span> #<?php echo (int) $req['id_requirement']; ?> <?php echo dev_admin_h($req['descrizione']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <?php if (!$conv['dialogues']): ?>
                            <p class="meta">No dialogues.</p>
                            <?php endif; ?>

                            <?php foreach ($conv['dialogues'] as $id_dialog => $dlg): ?>
                            <div class="tree-dlg">
                                <div><strong>Dialog #<?php echo (int) $id_dialog; ?></strong> <span class="meta">order <?php echo (int) $dlg['order']; ?>, last=<?php echo dev_admin_h($dlg['flg_last']); ?>, options=<?php echo dev_admin_h($dlg['flg_options']); ?></span></div>
                                <div class="small"><?php echo dev_admin_h($dlg['dialog']); ?></div>
                                <?php if ($dlg['dialog_it']): ?><div class="meta small">IT: <?php echo dev_admin_h($dlg['dialog_it']); ?></div><?php endif; ?>

                                <?php foreach ($dlg['options'] as $opt): ?>
                                <div class="tree-opt">
                                    <strong>Option #<?php echo (int) $opt['id_dialog_option']; ?></strong>
                                    <span class="badge bg-<?php echo dev_admin_h($opt['option_color'] ?: 'secondary'); ?>"><?php echo dev_admin_h($opt['option_color']); ?></span>
                                    <?php echo dev_admin_h($opt['option_text']); ?>
                                    <?php if (!empty($dlg['consequences'])): ?>
                                    <?php foreach ($dlg['consequences'] as $cons): ?>
                                    <?php if ((int) $cons['id_option'] === (int) $opt['id_dialog_option']): ?>
                                    <div class="small"><span class="badge badge-cons">consequence</span> #<?php echo (int) $cons['id_consequence']; ?> <?php echo dev_admin_h($cons['consequence_type']); ?> ref_table=<?php echo dev_admin_h($cons['ref_table']); ?> ×<?php echo (int) $cons['num']; ?></div>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
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
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-npc" type="button">NPC</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-conv" type="button">Conversation</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-dlg" type="button">Dialogue</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-opt" type="button">Option</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-req" type="button">Requirements</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-cons" type="button">Consequences</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-quest" type="button">Quest</button></li>
                    </ul>

                    <div class="tab-content">
                        <!-- NPC -->
                        <div class="tab-pane fade show active" id="tab-npc">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_npc">
                                <div class="mb-2"><label class="form-label">Name</label><input class="form-control form-control-sm" name="npc" required maxlength="100"></div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Type</label><input class="form-control form-control-sm" name="type" value="story" maxlength="100"></div>
                                    <div class="col-6"><label class="form-label">Prefab</label><input class="form-control form-control-sm" name="npc_type_prefab" value="trader" maxlength="100"></div>
                                </div>
                                <div class="mb-2 mt-2"><label class="form-label">Zone</label>
                                    <select class="form-select form-select-sm" name="id_zone">
                                        <?php foreach ($zones as $z): ?>
                                        <option value="<?php echo (int) $z['id_zone']; ?>"><?php echo (int) $z['id_zone']; ?> — <?php echo dev_admin_h($z['scene_name']); ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!$zones): ?><option value="1000">1000</option><?php endif; ?>
                                    </select>
                                </div>
                                <div class="row g-2">
                                    <div class="col-4"><label class="form-label">posx</label><input class="form-control form-control-sm" name="posx" value="0"></div>
                                    <div class="col-4"><label class="form-label">posy</label><input class="form-control form-control-sm" name="posy" value="0"></div>
                                    <div class="col-4"><label class="form-label">posz</label><input class="form-control form-control-sm" name="posz" value="0"></div>
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" type="submit">Create NPC</button>
                            </form>
                        </div>

                        <!-- Conversation -->
                        <div class="tab-pane fade" id="tab-conv">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_conversation">
                                <div class="mb-2"><label class="form-label">NPC</label>
                                    <select class="form-select form-select-sm" name="id_npc" required>
                                        <?php foreach ($tree as $id_npc => $npc): ?>
                                        <option value="<?php echo (int) $id_npc; ?>">#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">Title (EN)</label><input class="form-control form-control-sm" name="title" required maxlength="200"></div>
                                <div class="mb-2"><label class="form-label">Title IT</label><input class="form-control form-control-sm" name="title_it" maxlength="200"></div>
                                <div class="mb-2"><label class="form-label">Title PT</label><input class="form-control form-control-sm" name="title_pt" maxlength="200"></div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Visible</label><select class="form-select form-select-sm" name="visible"><option value="S">S</option><option value="N">N</option></select></div>
                                    <div class="col-6"><label class="form-label">Register on finish</label><select class="form-select form-select-sm" name="flg_register"><option value="N">N</option><option value="S">S</option></select></div>
                                </div>
                                <button class="btn btn-primary btn-sm mt-3" type="submit">Create conversation</button>
                            </form>
                        </div>

                        <!-- Dialogue -->
                        <div class="tab-pane fade" id="tab-dlg">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_dialogue">
                                <div class="mb-2"><label class="form-label">Conversation</label>
                                    <select class="form-select form-select-sm" name="id_conversation" required>
                                        <?php foreach ($flat_conversations as $c): ?>
                                        <option value="<?php echo (int) $c['id_conversation']; ?>">#<?php echo (int) $c['id_conversation']; ?> — <?php echo dev_admin_h($c['npc']); ?>: <?php echo dev_admin_h($c['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">Order</label><input class="form-control form-control-sm" name="order" type="number" value="1" min="1"></div>
                                    <div class="col-4"><label class="form-label">Last?</label><select class="form-select form-select-sm" name="flg_last"><option value="N">N</option><option value="S">S</option></select></div>
                                    <div class="col-4"><label class="form-label">Options?</label><select class="form-select form-select-sm" name="flg_options"><option value="N">N</option><option value="S">S</option></select></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Dialog EN</label><textarea class="form-control form-control-sm" name="dialog" rows="2" maxlength="500" required></textarea></div>
                                <div class="mb-2"><label class="form-label">Dialog IT</label><textarea class="form-control form-control-sm" name="dialog_it" rows="2" maxlength="500"></textarea></div>
                                <div class="mb-2"><label class="form-label">Dialog PT</label><textarea class="form-control form-control-sm" name="dialog_pt" rows="2" maxlength="500"></textarea></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit">Create dialogue line</button>
                            </form>
                        </div>

                        <!-- Option -->
                        <div class="tab-pane fade" id="tab-opt">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_dialog_option">
                                <div class="mb-2"><label class="form-label">Dialogue (must have flg_options=S)</label>
                                    <select class="form-select form-select-sm" name="id_dialog" required>
                                        <?php foreach ($flat_dialogues as $d): ?>
                                        <option value="<?php echo (int) $d['id_dialog']; ?>">#<?php echo (int) $d['id_dialog']; ?> [<?php echo (int) $d['order']; ?>] <?php echo dev_admin_h(mb_substr($d['dialog'], 0, 60)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">option_n</label><input class="form-control form-control-sm" name="option_n" type="number" value="1" min="1"></div>
                                    <div class="col-8"><label class="form-label">Color</label><select class="form-select form-select-sm" name="option_color"><option value="green">green</option><option value="red">red</option><option value="blue">blue</option><option value="yellow">yellow</option></select></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Text EN</label><input class="form-control form-control-sm" name="option_text" required maxlength="200"></div>
                                <div class="mb-2"><label class="form-label">Text IT</label><input class="form-control form-control-sm" name="option_text_it" maxlength="200"></div>
                                <div class="mb-2"><label class="form-label">Text PT</label><input class="form-control form-control-sm" name="option_text_pt" maxlength="200"></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit">Create option</button>
                            </form>
                        </div>

                        <!-- Requirements -->
                        <div class="tab-pane fade" id="tab-req">
                            <h6 class="text-secondary">New requirement</h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_requirement">
                                <div class="mb-2"><label class="form-label">Type</label>
                                    <select class="form-select form-select-sm" name="requirement_type">
                                        <option value="user lvl">user lvl</option>
                                        <option value="number of animals">number of animals</option>
                                        <option value="item">item</option>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">id_ref</label><input class="form-control form-control-sm" name="id_ref" type="number" value="0"></div>
                                    <div class="col-4"><label class="form-label">min</label><input class="form-control form-control-sm" name="min" type="number" value="0"></div>
                                    <div class="col-4"><label class="form-label">max</label><input class="form-control form-control-sm" name="max" type="number" value="9999"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">ref_table</label><input class="form-control form-control-sm" name="ref_table" maxlength="100" placeholder="POTION, HAS_ANIMALS..."></div>
                                <div class="mb-2"><label class="form-label">Description</label><input class="form-control form-control-sm" name="descrizione" maxlength="100"></div>
                                <button class="btn btn-primary btn-sm" type="submit">Create requirement</button>
                            </form>

                            <h6 class="text-secondary">Link existing requirement</h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-3">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="link_npc_requirement">
                                <div class="row g-2 align-items-end">
                                    <div class="col-5"><label class="form-label">NPC</label><select class="form-select form-select-sm" name="id_npc"><?php foreach ($tree as $id_npc => $npc): ?><option value="<?php echo (int) $id_npc; ?>">#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-5"><label class="form-label">Requirement</label><select class="form-select form-select-sm" name="id_requirement"><?php foreach ($requirements as $r): ?><option value="<?php echo (int) $r['id_requirement']; ?>">#<?php echo (int) $r['id_requirement']; ?> <?php echo dev_admin_h($r['descrizione']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-2"><button class="btn btn-outline-light btn-sm w-100" type="submit">→ NPC</button></div>
                                </div>
                            </form>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-3">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="link_conversation_requirement">
                                <div class="row g-2 align-items-end">
                                    <div class="col-5"><label class="form-label">Conversation</label><select class="form-select form-select-sm" name="id_conversation"><?php foreach ($flat_conversations as $c): ?><option value="<?php echo (int) $c['id_conversation']; ?>">#<?php echo (int) $c['id_conversation']; ?> <?php echo dev_admin_h($c['title']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-5"><label class="form-label">Requirement</label><select class="form-select form-select-sm" name="id_requirement"><?php foreach ($requirements as $r): ?><option value="<?php echo (int) $r['id_requirement']; ?>">#<?php echo (int) $r['id_requirement']; ?> <?php echo dev_admin_h($r['descrizione']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-2"><button class="btn btn-outline-light btn-sm w-100" type="submit">→ Conv</button></div>
                                </div>
                            </form>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="link_quest_requirement">
                                <div class="row g-2 align-items-end">
                                    <div class="col-5"><label class="form-label">Quest</label><select class="form-select form-select-sm" name="id_quest"><?php foreach ($flat_quests as $q): ?><option value="<?php echo (int) $q['id_quest']; ?>">#<?php echo (int) $q['id_quest']; ?> <?php echo dev_admin_h($q['quest']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-5"><label class="form-label">Requirement</label><select class="form-select form-select-sm" name="id_requirement"><?php foreach ($requirements as $r): ?><option value="<?php echo (int) $r['id_requirement']; ?>">#<?php echo (int) $r['id_requirement']; ?> <?php echo dev_admin_h($r['descrizione']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-2"><button class="btn btn-outline-light btn-sm w-100" type="submit">→ Quest</button></div>
                                </div>
                            </form>
                        </div>

                        <!-- Consequences -->
                        <div class="tab-pane fade" id="tab-cons">
                            <h6 class="text-secondary">New consequence</h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>" class="mb-4">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_consequence">
                                <div class="mb-2"><label class="form-label">Type</label><input class="form-control form-control-sm" name="consequence_type" value="[obtain item]" maxlength="100"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">id_ref</label><input class="form-control form-control-sm" name="id_ref" type="number" value="1"></div>
                                    <div class="col-4"><label class="form-label">num</label><input class="form-control form-control-sm" name="num" type="number" value="1" min="1"></div>
                                    <div class="col-4"><label class="form-label">ref_table</label><input class="form-control form-control-sm" name="ref_table" value="POTION" maxlength="100"></div>
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit">Create consequence</button>
                            </form>

                            <h6 class="text-secondary">Link to conversation option</h6>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="link_conversation_consequence">
                                <div class="mb-2"><label class="form-label">Conversation</label><select class="form-select form-select-sm" name="id_conversation"><?php foreach ($flat_conversations as $c): ?><option value="<?php echo (int) $c['id_conversation']; ?>">#<?php echo (int) $c['id_conversation']; ?> <?php echo dev_admin_h($c['title']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label class="form-label">Dialog option (id_dialog_option)</label><select class="form-select form-select-sm" name="id_option"><?php foreach ($flat_options as $o): ?><option value="<?php echo (int) $o['id_dialog_option']; ?>">#<?php echo (int) $o['id_dialog_option']; ?> — <?php echo dev_admin_h($o['option_text']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label class="form-label">Consequence</label><select class="form-select form-select-sm" name="id_consequence"><?php foreach ($consequences as $c): ?><option value="<?php echo (int) $c['id_consequence']; ?>">#<?php echo (int) $c['id_consequence']; ?> <?php echo dev_admin_h($c['consequence_type']); ?> <?php echo dev_admin_h($c['ref_table']); ?></option><?php endforeach; ?></select></div>
                                <button class="btn btn-primary btn-sm" type="submit">Link consequence</button>
                            </form>
                        </div>

                        <!-- Quest -->
                        <div class="tab-pane fade" id="tab-quest">
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_url()); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="add_quest">
                                <div class="mb-2"><label class="form-label">Starter NPC</label><select class="form-select form-select-sm" name="id_starter_npc"><?php foreach ($tree as $id_npc => $npc): ?><option value="<?php echo (int) $id_npc; ?>">#<?php echo (int) $id_npc; ?> <?php echo dev_admin_h($npc['npc']); ?></option><?php endforeach; ?></select></div>
                                <div class="mb-2"><label class="form-label">Quest title</label><input class="form-control form-control-sm" name="quest" required maxlength="200"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Type</label><input class="form-control form-control-sm" name="quest_type" maxlength="100"></div>
                                    <div class="col-6"><label class="form-label">Repeatable</label><select class="form-select form-select-sm" name="repeatable"><option value="N">N</option><option value="S">S</option></select></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">lvl min</label><input class="form-control form-control-sm" name="lvl_min" type="number" value="0"></div>
                                    <div class="col-6"><label class="form-label">lvl max</label><input class="form-control form-control-sm" name="lvl_max" type="number" value="100"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">ids_quests_required</label><input class="form-control form-control-sm" name="ids_quests_required" value="-1,-1" maxlength="100"></div>
                                <button class="btn btn-primary btn-sm mt-2" type="submit">Create quest</button>
                            </form>
                            <p class="meta small mt-3 mb-0">Quest runtime is not fully wired in the web client yet; this page lets you seed DB content.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card dev-card mt-3">
                <div class="card-header"><strong>Reference lists</strong></div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Requirements (<?php echo count($requirements); ?>)</strong></p>
                    <ul class="mb-3"><?php foreach ($requirements as $r): ?><li>#<?php echo (int) $r['id_requirement']; ?> <?php echo dev_admin_h($r['requirement_type']); ?> — <?php echo dev_admin_h($r['descrizione']); ?></li><?php endforeach; ?></ul>
                    <p class="mb-1"><strong>Consequences (<?php echo count($consequences); ?>)</strong></p>
                    <ul class="mb-0"><?php foreach ($consequences as $c): ?><li>#<?php echo (int) $c['id_consequence']; ?> <?php echo dev_admin_h($c['consequence_type']); ?> <?php echo dev_admin_h($c['ref_table']); ?> ×<?php echo (int) $c['num']; ?></li><?php endforeach; ?></ul>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
