<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_species_content.php';

dev_admin_require_auth();

$flash = '';
$flash_ok = true;
$active_tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'species';
$prefill_species = isset($_GET['id_species']) ? (int) $_GET['id_species'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $result = dev_species_handle_post($conn, $_POST);
    $flash_ok = $result['ok'];
    $flash = $result['message'];
    $redirect = isset($result['redirect']) ? $result['redirect'] : dev_admin_page_url('dev_species.php');

    header('Location: ' . $redirect . '&msg=' . rawurlencode($flash) . '&ok=' . ($flash_ok ? '1' : '0'));
    exit;
}

if (isset($_GET['msg']))
{
    $flash = (string) $_GET['msg'];
    $flash_ok = !isset($_GET['ok']) || $_GET['ok'] === '1';
}

if (isset($_GET['tab']))
{
    $active_tab = (string) $_GET['tab'];
}

if (isset($_GET['id_species']))
{
    $prefill_species = (int) $_GET['id_species'];
}

$species_tree = dev_species_fetch_tree($conn);
$abilities = dev_species_fetch_abilities($conn);
$classes = dev_species_fetch_classes($conn);
$elements = dev_species_fetch_elements($conn);
$effect_presets = dev_species_effect_presets();
$wild_drop_types = dev_species_wild_drop_types();
$item_types = dev_npc_fetch_item_types($conn);
$quests = dev_species_fetch_quests($conn);
$item_types_by_id = [];

foreach ($item_types as $item)
{
    $item_types_by_id[(int) $item['id_item_type']] = $item['nome'] ?: $item['item_type'];
}

$edit_type = isset($_GET['edit']) ? (string) $_GET['edit'] : '';
$edit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit_item = null;

if ($edit_type !== '' && $edit_id > 0)
{
    if ($edit_type === 'species' && isset($species_tree[$edit_id]))
    {
        $edit_item = $species_tree[$edit_id];
        $active_tab = 'species';
    }
    elseif ($edit_type === 'ability')
    {
        foreach ($abilities as $ab)
        {
            if ((int) $ab['id_ability'] === $edit_id)
            {
                $edit_item = $ab;
                $active_tab = 'ability';
                break;
            }
        }
    }
    elseif ($edit_type === 'species_ability')
    {
        foreach ($species_tree as $species)
        {
            foreach ($species['species_abilities'] as $sa)
            {
                if ((int) $sa['id_species_ability'] === $edit_id)
                {
                    $edit_item = $sa;
                    $active_tab = 'species_ability';
                    $prefill_species = (int) $sa['id_species'];
                    break 2;
                }
            }
        }
    }
    elseif ($edit_type === 'wild_drop')
    {
        foreach ($species_tree as $species)
        {
            foreach ($species['wild_drops'] as $drop)
            {
                if ((int) $drop['id_wild_animal_drop_type'] === $edit_id)
                {
                    $edit_item = $drop;
                    $active_tab = 'wild_drop';
                    $prefill_species = (int) $drop['id_species'];
                    break 2;
                }
            }
        }
    }

    if ($edit_item === null)
    {
        $edit_type = '';
        $edit_id = 0;
    }
}

$token = dev_admin_token();

$tab_ids = [
    'species' => 'tab-species',
    'ability' => 'tab-ability',
    'species_ability' => 'tab-species-ability',
    'wild_drop' => 'tab-wild-drop',
];
$active_pane_id = isset($tab_ids[$active_tab]) ? $tab_ids[$active_tab] : 'tab-species';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — Species / Abilities / Drops Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .tree-species { border-left: 3px solid #4dabf7; margin-bottom: 1rem; padding-left: .75rem; }
        .tree-sa { border-left: 3px solid #ffd43b; margin: .35rem 0 .35rem .75rem; padding-left: .75rem; }
        .tree-drop { border-left: 3px solid #69db7c; margin: .35rem 0 .35rem .75rem; padding-left: .75rem; }
        .meta { color: #94a3b8; font-size: .85rem; }
        .schema-box { font-size: .85rem; color: #adb5bd; }
        details summary { cursor: pointer; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
        .species-summary { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        strong { color: #4dabf7; }
        label { color: #4dabf7; }
        li { color: #4dabf7; }
        .effect-hint { font-size: .8rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Species / Abilities / Drops Dev</h1>
            <p class="meta mb-0">Token-protected. Manage species, abilities, species_abilities and wild_animal_drop_types.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npcs.php')); ?>">NPC content</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_npc.php')); ?>">NPC interactions</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_quest.php')); ?>">Quest flow</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_static_data.php')); ?>">Static data</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>">Refresh</a>
        </div>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="alert alert-<?php echo $flash_ok ? 'success' : 'danger'; ?>"><?php echo dev_admin_h($flash); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Combat params reference</strong></div>
                <div class="card-body schema-box">
                    <p class="mb-2"><strong>accuracy</strong> (0–100): hit chance multiplier vs opponent evasion.</p>
                    <p class="mb-2"><strong>power</strong> / <strong>m_power</strong>: physical / magical base damage. Use 0 for non-damaging moves. If either &gt; 0, battle adds a flat +3 after scaling.</p>
                    <p class="mb-2"><strong>effect</strong>: stat modifier token <code>direction_target_stat_amount_unit</code> (e.g. <code>lower_target_atk_10_%</code>). Use <code>none</code> when no stat change.</p>
                    <p class="mb-0"><strong>effect_chance</strong> (0–100): roll on hit; effect applies when rand(1,100) &lt; effect_chance. Set 0 with <code>none</code>.</p>
                    <p class="mb-0"><strong>wild_animal_drop_types</strong>: per-species loot table — <code>drop_type</code> item/gold, level band, quantity range, chance %, optional element filter, optional quest gate.</p>
                </div>
            </div>

            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Data model</strong></div>
                <div class="card-body schema-box">
                    <code>species</code> → <code>species_abilities</code> → <code>abilities</code><br>
                    <code>species</code> → <code>wild_animal_drop_types</code> (id_species, optional id_item_type)
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Species</strong>
                    <span class="badge bg-secondary"><?php echo count($species_tree); ?> species</span>
                </div>
                <div class="card-body">
                    <?php if (!$species_tree): ?>
                    <p class="meta mb-0">No species in database yet.</p>
                    <?php else: ?>
                    <?php foreach ($species_tree as $id_species => $species): ?>
                    <details class="tree-species" <?php echo (count($species['species_abilities']) || count($species['wild_drops'])) ? 'open' : ''; ?>>
                        <summary>
                            <div class="species-summary">
                                <strong>#<?php echo (int) $id_species; ?> <?php echo dev_admin_h($species['species']); ?></strong>
                                <span class="meta">class <?php echo (int) $species['id_class']; ?>, tier <?php echo dev_admin_h($species['tier']); ?>, HP <?php echo (int) $species['base_hp']; ?></span>
                                <?php if (($species['flg_attivo'] ?? 'N') === 'S'): ?>
                                <span class="badge bg-success">active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">inactive</span>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['edit' => 'species', 'id' => (int) $id_species])); ?>" title="Edit species">✎</a>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-add-species-ability" data-id-species="<?php echo (int) $id_species; ?>">+ability</button>
                                <button type="button" class="btn btn-sm btn-outline-success btn-add-wild-drop" data-id-species="<?php echo (int) $id_species; ?>">+drop</button>
                            </div>
                        </summary>

                        <?php if ($species['species_abilities']): ?>
                        <div class="ms-2 mt-2"><span class="badge bg-warning text-dark">Abilities</span></div>
                        <?php foreach ($species['species_abilities'] as $sa): ?>
                        <div class="tree-sa">
                            <div>
                                <strong>#<?php echo (int) $sa['id_species_ability']; ?></strong>
                                ability #<?php echo (int) $sa['id_ability']; ?>
                                <em><?php echo dev_admin_h($sa['ability']); ?></em>
                                <a class="btn btn-sm btn-outline-secondary ms-1" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'species_ability', 'edit' => 'species_ability', 'id' => (int) $sa['id_species_ability']])); ?>" title="Edit link">✎</a>
                            </div>
                            <div class="meta small">
                                unlock lvl <?php echo (int) $sa['unlock_lvl']; ?>
                                · element filter <?php echo (int) $sa['id_element']; ?><?php if (!empty($sa['element'])): ?> (<?php echo dev_admin_h($sa['element']); ?>)<?php endif; ?>
                                · acc <?php echo (int) $sa['accuracy']; ?>
                                · pow <?php echo (int) $sa['power']; ?>/<?php echo (int) $sa['m_power']; ?>
                                · effect <?php echo dev_admin_h($sa['effect']); ?> @ <?php echo (int) $sa['effect_chance']; ?>%
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="meta ms-2 mt-2 mb-0">No species_abilities linked.</p>
                        <?php endif; ?>

                        <?php if ($species['wild_drops']): ?>
                        <div class="ms-2 mt-2"><span class="badge bg-success">Wild drops</span></div>
                        <?php foreach ($species['wild_drops'] as $drop): ?>
                        <div class="tree-drop">
                            <strong>#<?php echo (int) $drop['id_wild_animal_drop_type']; ?></strong>
                            <?php echo dev_admin_h(dev_species_wild_drop_label($drop, $item_types_by_id)); ?>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'wild_drop', 'edit' => 'wild_drop', 'id' => (int) $drop['id_wild_animal_drop_type']])); ?>" title="Edit drop">✎</a>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="meta ms-2 mt-2 mb-0">No wild drops configured.</p>
                        <?php endif; ?>
                    </details>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card dev-card mt-3">
                <div class="card-header"><strong>All abilities (<?php echo count($abilities); ?>)</strong></div>
                <div class="card-body small">
                    <ul class="mb-0">
                        <?php foreach ($abilities as $ab): ?>
                        <li>
                            #<?php echo (int) $ab['id_ability']; ?> <?php echo dev_admin_h($ab['ability']); ?>
                            — acc <?php echo (int) $ab['accuracy']; ?>,
                            pow <?php echo (int) $ab['power']; ?>/<?php echo (int) $ab['m_power']; ?>,
                            <?php echo dev_admin_h($ab['effect']); ?> @ <?php echo (int) $ab['effect_chance']; ?>%,
                            elem <?php echo (int) $ab['id_element']; ?>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'ability', 'edit' => 'ability', 'id' => (int) $ab['id_ability']])); ?>">✎</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card dev-card">
                <div class="card-header"><strong>Add / edit content</strong></div>
                <div class="card-body">
                    <ul class="nav nav-pills mb-3 flex-wrap" id="devSpeciesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_pane_id === 'tab-species' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-species" type="button">Species</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_pane_id === 'tab-ability' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-ability" type="button">Ability</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_pane_id === 'tab-species-ability' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-species-ability" type="button">Species ability</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_pane_id === 'tab-wild-drop' ? 'active' : ''; ?>" data-bs-toggle="pill" data-bs-target="#tab-wild-drop" type="button">Wild drop</button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Species -->
                        <div class="tab-pane fade <?php echo $active_pane_id === 'tab-species' ? 'show active' : ''; ?>" id="tab-species">
                            <?php $is_edit_species = ($edit_type === 'species' && is_array($edit_item)); ?>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="<?php echo $is_edit_species ? 'update_species' : 'add_species'; ?>">
                                <?php if ($is_edit_species): ?>
                                <input type="hidden" name="id_species" value="<?php echo (int) $edit_item['id_species']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Name (EN)</label><input class="form-control form-control-sm" name="species" required maxlength="100" value="<?php echo $is_edit_species ? dev_admin_h($edit_item['species']) : ''; ?>"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Name IT</label><input class="form-control form-control-sm" name="species_it" maxlength="100" value="<?php echo $is_edit_species ? dev_admin_h(isset($edit_item['species_it']) ? $edit_item['species_it'] : '') : ''; ?>"></div>
                                    <div class="col-6"><label class="form-label">Name PT</label><input class="form-control form-control-sm" name="species_pt" maxlength="100" value="<?php echo $is_edit_species ? dev_admin_h(isset($edit_item['species_pt']) ? $edit_item['species_pt'] : '') : ''; ?>"></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Class</label>
                                        <select class="form-select form-select-sm" name="id_class">
                                            <option value="0">—</option>
                                            <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo (int) $class['id_class']; ?>"<?php echo $is_edit_species && (int) $edit_item['id_class'] === (int) $class['id_class'] ? ' selected' : ''; ?>>#<?php echo (int) $class['id_class']; ?> <?php echo dev_admin_h($class['class']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-3"><label class="form-label">Tier</label><input class="form-control form-control-sm" name="tier" value="<?php echo $is_edit_species ? dev_admin_h($edit_item['tier']) : '1.0'; ?>"></div>
                                    <div class="col-3"><label class="form-label">Active</label><select class="form-select form-select-sm" name="flg_attivo"><option value="S"<?php echo $is_edit_species && ($edit_item['flg_attivo'] ?? 'N') === 'S' ? ' selected' : ''; ?>>S</option><option value="N"<?php echo $is_edit_species && ($edit_item['flg_attivo'] ?? 'N') === 'N' ? ' selected' : ''; ?>>N</option></select></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">base_hp</label><input class="form-control form-control-sm" name="base_hp" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_hp'] : 60; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_atk</label><input class="form-control form-control-sm" name="base_atk" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_atk'] : 50; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_def</label><input class="form-control form-control-sm" name="base_def" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_def'] : 50; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_matk</label><input class="form-control form-control-sm" name="base_matk" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_matk'] : 50; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_mdef</label><input class="form-control form-control-sm" name="base_mdef" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_mdef'] : 50; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_spd</label><input class="form-control form-control-sm" name="base_spd" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_spd'] : 50; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_acc</label><input class="form-control form-control-sm" name="base_acc" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_acc'] : 100; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_eva</label><input class="form-control form-control-sm" name="base_eva" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_eva'] : 5; ?>"></div>
                                    <div class="col-4"><label class="form-label">base_cr</label><input class="form-control form-control-sm" name="base_cr" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['base_cr'] : 10; ?>"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">reward_exp</label><input class="form-control form-control-sm" name="reward_exp" type="number" value="<?php echo $is_edit_species ? (int) $edit_item['reward_exp'] : 46; ?>"></div>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_species ? 'Update species' : 'Create species'; ?></button>
                                <?php if ($is_edit_species): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Ability -->
                        <div class="tab-pane fade <?php echo $active_pane_id === 'tab-ability' ? 'show active' : ''; ?>" id="tab-ability">
                            <?php
                            $is_edit_ability = ($edit_type === 'ability' && is_array($edit_item));
                            $edit_effect = $is_edit_ability ? (string) $edit_item['effect'] : 'none';
                            $edit_effect_is_preset = dev_species_effect_is_preset($edit_effect);
                            ?>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>" id="form-add-ability">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="<?php echo $is_edit_ability ? 'update_ability' : 'add_ability'; ?>">
                                <?php if ($is_edit_ability): ?>
                                <input type="hidden" name="id_ability" value="<?php echo (int) $edit_item['id_ability']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Name (EN)</label><input class="form-control form-control-sm" name="ability" required maxlength="100" value="<?php echo $is_edit_ability ? dev_admin_h($edit_item['ability']) : ''; ?>"></div>
                                <div class="mb-2"><label class="form-label">Description (EN)</label><textarea class="form-control form-control-sm" name="descrizione" rows="2" maxlength="300"><?php echo $is_edit_ability ? dev_admin_h(isset($edit_item['descrizione']) ? $edit_item['descrizione'] : '') : ''; ?></textarea></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">Name IT</label><input class="form-control form-control-sm" name="ability_it" maxlength="100" value="<?php echo $is_edit_ability ? dev_admin_h(isset($edit_item['ability_it']) ? $edit_item['ability_it'] : '') : ''; ?>"></div>
                                    <div class="col-6"><label class="form-label">Name PT</label><input class="form-control form-control-sm" name="ability_pt" maxlength="100" value="<?php echo $is_edit_ability ? dev_admin_h(isset($edit_item['ability_pt']) ? $edit_item['ability_pt'] : '') : ''; ?>"></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4"><label class="form-label">accuracy</label><input class="form-control form-control-sm" name="accuracy" type="number" min="0" max="100" value="<?php echo $is_edit_ability ? (int) $edit_item['accuracy'] : 100; ?>"></div>
                                    <div class="col-4"><label class="form-label">power</label><input class="form-control form-control-sm" name="power" type="number" min="0" value="<?php echo $is_edit_ability ? (int) $edit_item['power'] : 40; ?>"></div>
                                    <div class="col-4"><label class="form-label">m_power</label><input class="form-control form-control-sm" name="m_power" type="number" min="0" value="<?php echo $is_edit_ability ? (int) $edit_item['m_power'] : 0; ?>"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">Element</label>
                                    <select class="form-select form-select-sm" name="id_element">
                                        <option value="0">0 — neutral / typeless</option>
                                        <?php foreach ($elements as $el): ?>
                                        <?php if ((int) $el['id_element'] === 8): continue; endif; ?>
                                        <option value="<?php echo (int) $el['id_element']; ?>"<?php echo $is_edit_ability && (int) $edit_item['id_element'] === (int) $el['id_element'] ? ' selected' : ''; ?>>#<?php echo (int) $el['id_element']; ?> <?php echo dev_admin_h($el['element']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">effect</label>
                                    <select class="form-select form-select-sm" name="effect_mode" id="ability-effect-mode">
                                        <option value="preset"<?php echo $is_edit_ability && $edit_effect_is_preset ? ' selected' : ''; ?>>Preset</option>
                                        <option value="custom"<?php echo $is_edit_ability && !$edit_effect_is_preset ? ' selected' : ''; ?>>Custom token</option>
                                    </select>
                                </div>
                                <div class="mb-2" id="ability-effect-preset-wrap">
                                    <select class="form-select form-select-sm" name="effect" id="ability-effect-preset">
                                        <?php foreach ($effect_presets as $preset): ?>
                                        <option value="<?php echo dev_admin_h($preset['value']); ?>"<?php echo $is_edit_ability && $edit_effect_is_preset && $edit_effect === $preset['value'] ? ' selected' : ''; ?>><?php echo dev_admin_h($preset['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2 <?php echo $is_edit_ability && !$edit_effect_is_preset ? '' : 'd-none'; ?>" id="ability-effect-custom-wrap">
                                    <input class="form-control form-control-sm font-monospace" name="effect_custom" id="ability-effect-custom" maxlength="100" placeholder="lower_target_atk_10_%" value="<?php echo $is_edit_ability && !$edit_effect_is_preset ? dev_admin_h($edit_effect) : ''; ?>">
                                    <div class="effect-hint mt-1">Format: direction_target_stat_amount_unit</div>
                                </div>
                                <div class="mb-2"><label class="form-label">effect_chance (%)</label>
                                    <input class="form-control form-control-sm" name="effect_chance" id="ability-effect-chance" type="number" min="0" max="100" value="<?php echo $is_edit_ability ? (int) $edit_item['effect_chance'] : 0; ?>">
                                    <div class="effect-hint mt-1">Ignored when effect is none. Use 100 for guaranteed proc on hit.</div>
                                </div>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_ability ? 'Update ability' : 'Create ability'; ?></button>
                                <?php if ($is_edit_ability): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'ability'])); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Species ability -->
                        <div class="tab-pane fade <?php echo $active_pane_id === 'tab-species-ability' ? 'show active' : ''; ?>" id="tab-species-ability">
                            <?php $is_edit_sa = ($edit_type === 'species_ability' && is_array($edit_item)); ?>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>" id="form-add-species-ability">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="<?php echo $is_edit_sa ? 'update_species_ability' : 'add_species_ability'; ?>">
                                <?php if ($is_edit_sa): ?>
                                <input type="hidden" name="id_species_ability" value="<?php echo (int) $edit_item['id_species_ability']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Species</label>
                                    <select class="form-select form-select-sm" name="id_species" id="sa-id-species" required>
                                        <?php foreach ($species_tree as $id_species => $species): ?>
                                        <option value="<?php echo (int) $id_species; ?>" <?php echo (int) $prefill_species === (int) $id_species || ($is_edit_sa && (int) $edit_item['id_species'] === (int) $id_species) ? 'selected' : ''; ?>>
                                            #<?php echo (int) $id_species; ?> <?php echo dev_admin_h($species['species']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">Ability</label>
                                    <select class="form-select form-select-sm" name="id_ability" required>
                                        <?php foreach ($abilities as $ab): ?>
                                        <option value="<?php echo (int) $ab['id_ability']; ?>"<?php echo $is_edit_sa && (int) $edit_item['id_ability'] === (int) $ab['id_ability'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $ab['id_ability']; ?> <?php echo dev_admin_h($ab['ability']); ?>
                                            (<?php echo (int) $ab['power']; ?>/<?php echo (int) $ab['m_power']; ?>, <?php echo dev_admin_h($ab['effect']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">unlock_lvl</label><input class="form-control form-control-sm" name="unlock_lvl" type="number" min="0" value="<?php echo $is_edit_sa ? (int) $edit_item['unlock_lvl'] : 0; ?>"></div>
                                    <div class="col-6"><label class="form-label">id_element filter</label>
                                        <select class="form-select form-select-sm" name="id_element">
                                            <option value="0">0 — any element</option>
                                            <?php foreach ($elements as $el): ?>
                                            <?php if ((int) $el['id_element'] === 8): continue; endif; ?>
                                            <option value="<?php echo (int) $el['id_element']; ?>"<?php echo $is_edit_sa && (int) $edit_item['id_element'] === (int) $el['id_element'] ? ' selected' : ''; ?>>#<?php echo (int) $el['id_element']; ?> <?php echo dev_admin_h($el['element']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <p class="effect-hint">Animal learns this move when level &ge; unlock_lvl. Element filter must match the animal&apos;s element or be 0.</p>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_sa ? 'Update species ability' : 'Link species ability'; ?></button>
                                <?php if ($is_edit_sa): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'species_ability'])); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Wild drop -->
                        <div class="tab-pane fade <?php echo $active_pane_id === 'tab-wild-drop' ? 'show active' : ''; ?>" id="tab-wild-drop">
                            <?php $is_edit_drop = ($edit_type === 'wild_drop' && is_array($edit_item)); ?>
                            <form method="post" action="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php')); ?>" id="form-wild-drop">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="action" value="<?php echo $is_edit_drop ? 'update_wild_drop' : 'add_wild_drop'; ?>">
                                <?php if ($is_edit_drop): ?>
                                <input type="hidden" name="id_wild_animal_drop_type" value="<?php echo (int) $edit_item['id_wild_animal_drop_type']; ?>">
                                <?php endif; ?>
                                <div class="mb-2"><label class="form-label">Species</label>
                                    <select class="form-select form-select-sm" name="id_species" id="wd-id-species" required>
                                        <?php foreach ($species_tree as $id_species => $species): ?>
                                        <option value="<?php echo (int) $id_species; ?>"<?php echo (int) $prefill_species === (int) $id_species || ($is_edit_drop && (int) $edit_item['id_species'] === (int) $id_species) ? ' selected' : ''; ?>>
                                            #<?php echo (int) $id_species; ?> <?php echo dev_admin_h($species['species']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">drop_type</label>
                                    <select class="form-select form-select-sm" name="drop_type" id="wd-drop-type">
                                        <?php foreach ($wild_drop_types as $dt): ?>
                                        <option value="<?php echo dev_admin_h($dt['value']); ?>"<?php echo $is_edit_drop && $edit_item['drop_type'] === $dt['value'] ? ' selected' : (!$is_edit_drop && $dt['value'] === 'item' ? ' selected' : ''); ?>><?php echo dev_admin_h($dt['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2" id="wd-item-type-wrap">
                                    <label class="form-label">id_item_type</label>
                                    <select class="form-select form-select-sm" name="id_item_type" id="wd-id-item-type">
                                        <option value="0">0 — not used (gold)</option>
                                        <?php foreach ($item_types as $item): ?>
                                        <option value="<?php echo (int) $item['id_item_type']; ?>"<?php echo $is_edit_drop && (int) $edit_item['id_item_type'] === (int) $item['id_item_type'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $item['id_item_type']; ?> <?php echo dev_admin_h($item['nome'] ?: $item['item_type']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">lvl_min</label><input class="form-control form-control-sm" name="lvl_min" type="number" min="1" value="<?php echo $is_edit_drop ? (int) $edit_item['lvl_min'] : 1; ?>"></div>
                                    <div class="col-6"><label class="form-label">lvl_max</label><input class="form-control form-control-sm" name="lvl_max" type="number" min="1" value="<?php echo $is_edit_drop ? (int) $edit_item['lvl_max'] : 100; ?>"></div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">qt_min</label><input class="form-control form-control-sm" name="qt_min" type="number" min="0" value="<?php echo $is_edit_drop ? (int) $edit_item['qt_min'] : 1; ?>"></div>
                                    <div class="col-6"><label class="form-label">qt_max</label><input class="form-control form-control-sm" name="qt_max" type="number" min="0" value="<?php echo $is_edit_drop ? (int) $edit_item['qt_max'] : 1; ?>"></div>
                                </div>
                                <div class="mb-2"><label class="form-label">chance (%)</label><input class="form-control form-control-sm" name="chance" type="number" min="0" max="100" value="<?php echo $is_edit_drop ? (int) $edit_item['chance'] : 10; ?>"></div>
                                <div class="mb-2"><label class="form-label">id_element filter</label>
                                    <select class="form-select form-select-sm" name="id_element">
                                        <option value="0">0 — any element</option>
                                        <?php foreach ($elements as $el): ?>
                                        <?php if ((int) $el['id_element'] === 8): continue; endif; ?>
                                        <option value="<?php echo (int) $el['id_element']; ?>"<?php echo $is_edit_drop && (int) $edit_item['id_element'] === (int) $el['id_element'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $el['id_element']; ?> <?php echo dev_admin_h($el['element']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-2"><label class="form-label">id_quest_required</label>
                                    <select class="form-select form-select-sm" name="id_quest_required">
                                        <option value="0">0 — no quest gate</option>
                                        <?php foreach ($quests as $quest): ?>
                                        <option value="<?php echo (int) $quest['id_quest']; ?>"<?php echo $is_edit_drop && (int) $edit_item['id_quest_required'] === (int) $quest['id_quest'] ? ' selected' : ''; ?>>
                                            #<?php echo (int) $quest['id_quest']; ?> <?php echo dev_admin_h($quest['quest']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p class="effect-hint">Gold drops use <code>id_item_type = 0</code> and roll quantity as gold amount. Item drops require a valid item type.</p>
                                <button class="btn btn-primary btn-sm" type="submit"><?php echo $is_edit_drop ? 'Update wild drop' : 'Create wild drop'; ?></button>
                                <?php if ($is_edit_drop): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_species.php', ['tab' => 'wild_drop'])); ?>">Cancel edit</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function ()
{
    var effectModeEl = document.getElementById('ability-effect-mode');
    var presetWrap = document.getElementById('ability-effect-preset-wrap');
    var customWrap = document.getElementById('ability-effect-custom-wrap');
    var presetEl = document.getElementById('ability-effect-preset');
    var chanceEl = document.getElementById('ability-effect-chance');

    function syncEffectForm()
    {
        if (!effectModeEl)
        {
            return;
        }

        var isCustom = effectModeEl.value === 'custom';
        presetWrap.classList.toggle('d-none', isCustom);
        customWrap.classList.toggle('d-none', !isCustom);

        var effectValue = isCustom
            ? (document.getElementById('ability-effect-custom').value || 'none')
            : presetEl.value;
        var isNone = effectValue === 'none';

        if (chanceEl)
        {
            chanceEl.disabled = isNone;

            if (isNone)
            {
                chanceEl.value = '0';
            }
        }
    }

    if (effectModeEl)
    {
        effectModeEl.addEventListener('change', syncEffectForm);
    }

    if (presetEl)
    {
        presetEl.addEventListener('change', syncEffectForm);
    }

    syncEffectForm();

    document.querySelectorAll('.btn-add-species-ability').forEach(function (btn)
    {
        btn.addEventListener('click', function (event)
        {
            event.preventDefault();
            event.stopPropagation();

            var idSpecies = btn.getAttribute('data-id-species');
            var speciesSelect = document.getElementById('sa-id-species');
            var tabBtn = document.querySelector('[data-bs-target="#tab-species-ability"]');
            var form = document.getElementById('form-add-species-ability');

            if (speciesSelect && idSpecies)
            {
                speciesSelect.value = idSpecies;
            }

            if (tabBtn && window.bootstrap && bootstrap.Tab)
            {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }

            if (form)
            {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    document.querySelectorAll('.btn-add-wild-drop').forEach(function (btn)
    {
        btn.addEventListener('click', function (event)
        {
            event.preventDefault();
            event.stopPropagation();

            var idSpecies = btn.getAttribute('data-id-species');
            var speciesSelect = document.getElementById('wd-id-species');
            var tabBtn = document.querySelector('[data-bs-target="#tab-wild-drop"]');
            var form = document.getElementById('form-wild-drop');

            if (speciesSelect && idSpecies)
            {
                speciesSelect.value = idSpecies;
            }

            if (tabBtn && window.bootstrap && bootstrap.Tab)
            {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }

            if (form)
            {
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    var dropTypeEl = document.getElementById('wd-drop-type');
    var itemTypeWrap = document.getElementById('wd-item-type-wrap');
    var itemTypeEl = document.getElementById('wd-id-item-type');

    function syncWildDropForm()
    {
        if (!dropTypeEl || !itemTypeWrap)
        {
            return;
        }

        var isGold = dropTypeEl.value === 'gold';
        itemTypeWrap.classList.toggle('d-none', isGold);

        if (itemTypeEl)
        {
            itemTypeEl.disabled = isGold;

            if (isGold)
            {
                itemTypeEl.value = '0';
            }
        }
    }

    if (dropTypeEl)
    {
        dropTypeEl.addEventListener('change', syncWildDropForm);
        syncWildDropForm();
    }
})();
</script>
</body>
</html>
