<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_admin_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/dev_world_tiles_content.php';

dev_admin_require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $ajax_result = dev_world_tiles_handle_ajax_action($conn, $_POST);

    if ($ajax_result !== null)
    {
        header('Content-Type: application/json');
        echo json_encode($ajax_result);
        exit;
    }

    $result = dev_world_tiles_handle_post($conn, $_POST);
    $redirect = [
        'msg' => $result['message'],
        'ok' => $result['ok'] ? '1' : '0',
        'id_zone' => dev_world_tiles_post_int($_POST, 'id_zone')
    ];

    header('Location: ' . dev_admin_page_url('dev_world_tiles.php', $redirect));
    exit;
}

$flash = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$flash_ok = !isset($_GET['ok']) || $_GET['ok'] === '1';

$zones = dev_world_tiles_fetch_zones($conn);
$id_zone = isset($_GET['id_zone']) ? (int) $_GET['id_zone'] : 0;

if ($id_zone <= 0 && $zones)
{
    $id_zone = (int) $zones[0]['id_zone'];
}

$layers = animaster_world_tiles_fetch_layers($conn);
$definitions = animaster_world_tiles_fetch_all_definitions($conn);
$placements = $id_zone > 0 ? animaster_world_tiles_fetch_placements($conn, $id_zone) : [];
$categories = dev_world_tiles_categories();
$token = dev_admin_token();

$object_definitions = animaster_world_objects_fetch_all_definitions($conn);
$object_placements = $id_zone > 0 ? animaster_world_objects_fetch_placements($conn, $id_zone) : [];
$object_defs_by_id = [];

foreach ($object_definitions as $odef)
{
    $object_defs_by_id[(int) $odef['id_object_definition']] = $odef;
}

$edit_id = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
$edit_definition = null;

foreach ($definitions as $def)
{
    if ((int) $def['id_tile_definition'] === $edit_id)
    {
        $edit_definition = $def;
        break;
    }
}

$edit_object_id = isset($_GET['edit_object_id']) ? (int) $_GET['edit_object_id'] : 0;
$edit_object_definition = isset($object_defs_by_id[$edit_object_id]) ? $object_defs_by_id[$edit_object_id] : null;

$ground_layer_id = 0;

foreach ($layers as $layer)
{
    if ($layer['is_ground'] === 'S')
    {
        $ground_layer_id = (int) $layer['id_tile_layer'];
        break;
    }
}

$bootstrap = [
    'idZone' => $id_zone,
    'token' => $token,
    'imgBase' => 'client/img/tiles/',
    'tileLayers' => $layers,
    'groundLayerId' => $ground_layer_id,
    'definitions' => $definitions,
    'placements' => $placements,
    'maskSize' => ANIMASTER_TILE_MASK_SIZE,
    'editDefinition' => $edit_definition ? [
        'imageFile' => $edit_definition['image_file'],
        'collisionMask' => $edit_definition['collision_mask']
    ] : null
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Animaster — World Tiles Dev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dev_admin.css">
    <style>
        .dwt-canvas-wrap { display: inline-block; border: 1px solid var(--bs-border-color); border-radius: 6px; overflow: hidden; }
        #dwt-canvas { display: block; background: #111a26; cursor: crosshair; }
        .dwt-palette-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: .25rem;
            width: 72px;
            padding: .35rem;
            border: 2px solid var(--bs-border-color);
            border-radius: 6px;
            background: #0f1419;
            color: #dce8f5;
            font-size: .68rem;
        }
        .dwt-palette-btn img { width: 40px; height: 40px; image-rendering: pixelated; border-radius: 3px; }
        .dwt-palette-selected { border-color: #4dabf7; box-shadow: 0 0 0 2px rgba(77,171,247,.35); }
        .dwt-palette-eraser { font-size: 1.1rem; }
        .dwt-thumb { width: 28px; height: 28px; image-rendering: pixelated; border-radius: 3px; vertical-align: middle; }
        .dwt-mask-canvas-wrap { display: inline-block; border: 1px solid var(--bs-border-color); border-radius: 6px; overflow: hidden; }
        #dwt-mask-canvas { display: block; background: #111a26; cursor: crosshair; }
        .dwt-layer-list { min-width: 170px; }
        .dwt-layer-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .25rem .4rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 6px;
            background: #0f1419;
        }
        .dwt-layer-select-btn {
            flex: 1 1 auto;
            text-align: left;
            border: none;
            background: transparent;
            color: #dce8f5;
            padding: .15rem .2rem;
            font-size: .82rem;
        }
        .dwt-layer-row.dwt-layer-active { border-color: #4dabf7; box-shadow: 0 0 0 2px rgba(77,171,247,.35); }
        .dwt-layer-row.dwt-layer-active .dwt-layer-select-btn { color: #4dabf7; font-weight: 600; }
        .dwt-layer-visible-label { display: flex; align-items: center; gap: .25rem; font-size: .68rem; color: #9fb2c3; margin: 0; }
        .dwt-layer-row.dwt-layer-active .dwt-layer-visible-label { visibility: hidden; }
    </style>
</head>
<body>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">World Tiles Dev</h1>
            <p class="meta mb-0">Pick a layer, then click or drag on the grid to paint the selected palette tile onto it. Edits are local until you hit Save. Empty ground cells shown here are truly unassigned (the live game fills them with a deterministic random base-pack tile).</p>
        </div>
        <form method="get" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
            <select class="form-select form-select-sm" name="id_zone" onchange="this.form.submit()">
                <?php foreach ($zones as $z): ?>
                <option value="<?php echo (int) $z['id_zone']; ?>"<?php echo $id_zone === (int) $z['id_zone'] ? ' selected' : ''; ?>>
                    #<?php echo (int) $z['id_zone']; ?> <?php echo dev_admin_h((string) $z['scene_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Switch zone</button>
        </form>
    </div>

    <?php if ($flash !== ''): ?>
    <div class="alert <?php echo $flash_ok ? 'alert-success' : 'alert-danger'; ?>"><?php echo dev_admin_h($flash); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card dev-card mb-3">
                <div class="card-header"><strong>Zone #<?php echo (int) $id_zone; ?> grid</strong> <span class="meta">(yellow outline = origin 0,0 · red outline = barrier · dashed amber = unsaved)</span></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 align-items-start">
                        <div>
                            <div class="dwt-canvas-wrap">
                                <canvas id="dwt-canvas" width="720" height="560"></canvas>
                            </div>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="left">&larr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="up">&uarr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="down">&darr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="right">pan &rarr;</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="home">Recenter</button>
                            </div>
                            <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
                                <button type="button" class="btn btn-outline-info btn-sm" id="dwt-preview-toggle">Preview: showing edits</button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="dwt-revert-btn">Revert all</button>
                                <button type="button" class="btn btn-success btn-sm" id="dwt-save-btn">Save changes</button>
                                <span class="meta">Unsaved cells: <span id="dwt-dirty-count">0</span></span>
                            </div>
                            <p class="meta mt-2 mb-0">Hover: <span id="dwt-coords">—</span> · Brush: <span id="dwt-brush-label">none</span> · <span id="dwt-status"></span></p>
                        </div>
                        <div>
                            <p class="meta mb-2"><strong>Layers</strong></p>
                            <div id="dwt-layer-list" class="d-flex flex-column gap-1 dwt-layer-list mb-3">
                                <?php foreach ($layers as $layer): ?>
                                <div class="dwt-layer-row<?php echo $layer['is_ground'] === 'S' ? ' dwt-layer-active' : ''; ?>" data-layer-id="<?php echo (int) $layer['id_tile_layer']; ?>">
                                    <button type="button" class="dwt-layer-select-btn"><?php echo dev_admin_h($layer['name']); ?></button>
                                    <label class="dwt-layer-visible-label">
                                        <input type="checkbox" class="dwt-layer-visible-checkbox" checked> visible
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="meta mb-2"><strong>Palette</strong></p>
                            <div id="dwt-palette" class="d-flex flex-wrap gap-2" style="max-width: 260px;">
                                <button type="button" class="dwt-palette-btn dwt-palette-eraser" data-brush="ERASER" data-label="Eraser" data-layer="0">
                                    &#10060;
                                    <span>Eraser</span>
                                </button>
                                <?php foreach ($definitions as $def): ?>
                                <button type="button" class="dwt-palette-btn" data-brush="<?php echo (int) $def['id_tile_definition']; ?>" data-label="<?php echo dev_admin_h($def['code']); ?>" data-layer="<?php echo (int) ($def['id_tile_layer'] ?? 0); ?>">
                                    <img src="client/img/tiles/<?php echo dev_admin_h($def['image_file']); ?>" alt="">
                                    <span><?php echo dev_admin_h($def['code']); ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dev-card mb-3">
                <div class="card-header"><strong><?php echo $edit_definition ? 'Edit tile definition #' . (int) $edit_definition['id_tile_definition'] : 'New tile definition'; ?></strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_definition ? 'update_tile_definition' : 'add_tile_definition'; ?>">
                        <?php if ($edit_definition): ?>
                        <input type="hidden" name="id_tile_definition" value="<?php echo (int) $edit_definition['id_tile_definition']; ?>">
                        <?php endif; ?>

                        <div class="mb-2">
                            <label class="form-label">code</label>
                            <input class="form-control form-control-sm" name="code" maxlength="50" required value="<?php echo dev_admin_h($edit_definition['code'] ?? ''); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">image_file <span class="meta">(public_html/client/img/tiles/)</span></label>
                            <input id="dwt-image-file" class="form-control form-control-sm" name="image_file" maxlength="150" required value="<?php echo dev_admin_h($edit_definition['image_file'] ?? ''); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">category</label>
                            <select class="form-select form-select-sm" name="category">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo dev_admin_h($cat); ?>"<?php echo ($edit_definition['category'] ?? 'base_pack') === $cat ? ' selected' : ''; ?>><?php echo dev_admin_h($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">id_tile_layer <span class="meta">(optional; scopes this def to one layer in the palette)</span></label>
                            <select class="form-select form-select-sm" name="id_tile_layer">
                                <option value="0">(any layer)</option>
                                <?php foreach ($layers as $layer): ?>
                                <option value="<?php echo (int) $layer['id_tile_layer']; ?>"<?php echo (isset($edit_definition['id_tile_layer']) && (int) $edit_definition['id_tile_layer'] === (int) $layer['id_tile_layer']) ? ' selected' : ''; ?>><?php echo dev_admin_h($layer['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">id_zone <span class="meta">(0 = any zone)</span></label>
                            <input class="form-control form-control-sm" type="number" name="id_zone_scope" value="<?php echo (int) ($edit_definition['id_zone'] ?? 0); ?>">
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_base_pack" value="S" id="dwt-is-base-pack"<?php echo ($edit_definition['is_base_pack'] ?? 'N') === 'S' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dwt-is-base-pack">is_base_pack (random fill of empty ground cells)</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_walkable" value="S" id="dwt-is-walkable"<?php echo ($edit_definition['is_walkable'] ?? 'S') === 'S' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dwt-is-walkable">is_walkable (unchecked = barrier)</label>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">move_speed_mult</label>
                            <input class="form-control form-control-sm" type="number" step="0.05" min="0" name="move_speed_mult" value="<?php echo dev_admin_h((string) ($edit_definition['move_speed_mult'] ?? '1.00')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label mb-1">collision_mask <span class="meta">(sub-tile barrier shape; paint the blocked area, e.g. only the round part of a ruin — leave blank for a whole-tile barrier/walkable via is_walkable above)</span></label>
                            <div class="dwt-mask-canvas-wrap">
                                <canvas id="dwt-mask-canvas" width="240" height="240"></canvas>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="dwt-mask-clear">Clear mask (whole tile uses is_walkable)</button>
                            </div>
                            <input type="hidden" name="collision_mask" id="dwt-mask-input" value="<?php echo dev_admin_h((string) ($edit_definition['collision_mask'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">sort_order</label>
                            <input class="form-control form-control-sm" type="number" name="sort_order" value="<?php echo (int) ($edit_definition['sort_order'] ?? 0); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><?php echo $edit_definition ? 'Save changes' : 'Create tile definition'; ?></button>
                        <?php if ($edit_definition): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone])); ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header"><strong>Catalog (<?php echo count($definitions); ?>)</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-dark align-middle mb-0">
                        <tbody>
                            <?php foreach ($definitions as $def): ?>
                            <tr>
                                <td><img class="dwt-thumb" src="client/img/tiles/<?php echo dev_admin_h($def['image_file']); ?>" alt=""></td>
                                <td>
                                    #<?php echo (int) $def['id_tile_definition']; ?> <?php echo dev_admin_h($def['code']); ?>
                                    <div class="meta"><?php echo dev_admin_h($def['category']); ?> · <?php echo !empty($def['collision_mask']) ? 'mixed terrain' : ($def['is_walkable'] === 'N' ? 'barrier' : 'walkable'); ?> · x<?php echo dev_admin_h((string) $def['move_speed_mult']); ?> speed<?php echo $def['is_base_pack'] === 'S' ? ' · base pack' : ''; ?><?php echo $def['id_zone'] ? ' · zone ' . (int) $def['id_zone'] : ''; ?></div>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'edit_id' => (int) $def['id_tile_definition']])); ?>">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card dev-card mb-3">
                <div class="card-header"><strong>Objects in zone #<?php echo (int) $id_zone; ?></strong> <span class="meta">(large multi-cell art, e.g. a house; grid-snapped origin, rendered at its own world-unit size)</span></div>
                <div class="card-body">
                    <form method="post" class="d-flex flex-wrap gap-2 align-items-end mb-3">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                        <input type="hidden" name="action" value="place_object">
                        <div>
                            <label class="form-label mb-1">grid_x</label>
                            <input class="form-control form-control-sm" type="number" name="grid_x" value="0" style="width: 90px;">
                        </div>
                        <div>
                            <label class="form-label mb-1">grid_z</label>
                            <input class="form-control form-control-sm" type="number" name="grid_z" value="0" style="width: 90px;">
                        </div>
                        <div>
                            <label class="form-label mb-1">object</label>
                            <select class="form-select form-select-sm" name="id_object_definition" style="min-width: 160px;">
                                <?php foreach ($object_definitions as $odef): ?>
                                <option value="<?php echo (int) $odef['id_object_definition']; ?>"><?php echo dev_admin_h($odef['code']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Place object</button>
                    </form>

                    <table class="table table-sm table-dark align-middle mb-0">
                        <tbody>
                            <?php foreach ($object_placements as $obj): $odef = $object_defs_by_id[(int) $obj['id_object_definition']] ?? null; ?>
                            <tr>
                                <td><?php if ($odef): ?><img class="dwt-thumb" src="client/img/objects/<?php echo dev_admin_h($odef['image_file']); ?>" alt=""><?php endif; ?></td>
                                <td>
                                    #<?php echo (int) $obj['id_world_object']; ?> <?php echo dev_admin_h($odef['code'] ?? '?'); ?>
                                    <div class="meta">grid (<?php echo (int) $obj['grid_x']; ?>, <?php echo (int) $obj['grid_z']; ?>)</div>
                                </td>
                                <td class="text-end">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                                        <input type="hidden" name="action" value="clear_object">
                                        <input type="hidden" name="id_world_object" value="<?php echo (int) $obj['id_world_object']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$object_placements): ?>
                            <tr><td colspan="3" class="meta text-center py-3">No objects placed in this zone yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dev-card mb-3">
                <div class="card-header"><strong><?php echo $edit_object_definition ? 'Edit object definition #' . (int) $edit_object_definition['id_object_definition'] : 'New object definition'; ?></strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                        <input type="hidden" name="action" value="<?php echo $edit_object_definition ? 'update_object_definition' : 'add_object_definition'; ?>">
                        <?php if ($edit_object_definition): ?>
                        <input type="hidden" name="id_object_definition" value="<?php echo (int) $edit_object_definition['id_object_definition']; ?>">
                        <?php endif; ?>

                        <div class="mb-2">
                            <label class="form-label">code</label>
                            <input class="form-control form-control-sm" name="code" maxlength="50" required value="<?php echo dev_admin_h($edit_object_definition['code'] ?? ''); ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">image_file <span class="meta">(public_html/client/img/objects/)</span></label>
                            <input class="form-control form-control-sm" name="image_file" maxlength="150" required value="<?php echo dev_admin_h($edit_object_definition['image_file'] ?? ''); ?>">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">width_world</label>
                                <input class="form-control form-control-sm" type="number" step="0.5" min="1" name="width_world" value="<?php echo dev_admin_h((string) ($edit_object_definition['width_world'] ?? '25.00')); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">height_world</label>
                                <input class="form-control form-control-sm" type="number" step="0.5" min="1" name="height_world" value="<?php echo dev_admin_h((string) ($edit_object_definition['height_world'] ?? '25.00')); ?>">
                            </div>
                        </div>
                        <p class="meta mb-2">World units, not source px (TILE_WORLD_SIZE = <?php echo (int) ANIMASTER_TILE_WORLD_SIZE; ?> per cell) -- e.g. a 4x4.4 cell house is ~<?php echo (int) (4 * ANIMASTER_TILE_WORLD_SIZE); ?>x<?php echo (int) (4.4 * ANIMASTER_TILE_WORLD_SIZE); ?>.</p>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">anchor_x</label>
                                <input class="form-control form-control-sm" type="number" step="0.05" min="0" max="1" name="anchor_x" value="<?php echo dev_admin_h((string) ($edit_object_definition['anchor_x'] ?? '0.50')); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label">anchor_y</label>
                                <input class="form-control form-control-sm" type="number" step="0.05" min="0" max="1" name="anchor_y" value="<?php echo dev_admin_h((string) ($edit_object_definition['anchor_y'] ?? '1.00')); ?>">
                            </div>
                        </div>
                        <p class="meta mb-2">Fraction of the image aligned to the origin cell -- default 0.5/1.0 = bottom-center (the object "stands" on its cell).</p>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_walkable" value="S" id="dwt-obj-is-walkable"<?php echo ($edit_object_definition['is_walkable'] ?? 'N') === 'S' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="dwt-obj-is-walkable">is_walkable (unchecked = whole footprint blocks movement)</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">sort_order</label>
                            <input class="form-control form-control-sm" type="number" name="sort_order" value="<?php echo (int) ($edit_object_definition['sort_order'] ?? 0); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><?php echo $edit_object_definition ? 'Save changes' : 'Create object definition'; ?></button>
                        <?php if ($edit_object_definition): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone])); ?>">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card dev-card">
                <div class="card-header"><strong>Object catalog (<?php echo count($object_definitions); ?>)</strong></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-dark align-middle mb-0">
                        <tbody>
                            <?php foreach ($object_definitions as $odef): ?>
                            <tr>
                                <td><img class="dwt-thumb" src="client/img/objects/<?php echo dev_admin_h($odef['image_file']); ?>" alt=""></td>
                                <td>
                                    #<?php echo (int) $odef['id_object_definition']; ?> <?php echo dev_admin_h($odef['code']); ?>
                                    <div class="meta"><?php echo dev_admin_h((string) $odef['width_world']); ?>x<?php echo dev_admin_h((string) $odef['height_world']); ?> · <?php echo $odef['is_walkable'] === 'N' ? 'barrier' : 'walkable'; ?></div>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'edit_object_id' => (int) $odef['id_object_definition']])); ?>">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    window.DEV_WORLD_TILES_BOOTSTRAP = <?php echo json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="client/js/dev_world_tiles.js"></script>
</body>
</html>
