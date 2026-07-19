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

    $result = dev_world_tiles_handle_post($conn, $_POST, $_FILES);
    $redirect = [
        'msg' => $result['message'],
        'ok' => $result['ok'] ? '1' : '0',
        'id_zone' => dev_world_tiles_post_int($_POST, 'id_zone'),
        'tab' => dev_world_tiles_post_str($_POST, 'tab', 30) ?: 'map'
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
$tile_packs = animaster_world_tiles_fetch_packs($conn);
$has_unpacked_tiles = false;

foreach ($definitions as $def_row)
{
    if (trim((string) ($def_row['pack'] ?? '')) === '')
    {
        $has_unpacked_tiles = true;
        break;
    }
}

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

$active_tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'map';

if ($edit_definition || $edit_object_definition)
{
    $active_tab = 'definitions';
}
elseif ($active_tab !== 'definitions')
{
    $active_tab = 'map';
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
        #dwt-canvas.is-thermometer { cursor: cell; }
        .dwt-tool-btn.is-active {
            color: #111a26;
            background: #ffb020;
            border-color: #ffb020;
        }
        .dwt-tileset-preview-wrap { max-width: 100%; overflow: auto; border: 1px solid var(--bs-border-color); border-radius: 6px; background: #111a26; }
        #dwt-tileset-preview { display: block; max-width: 100%; height: auto; image-rendering: pixelated; }
        .dwt-import-hint { font-size: 0.85rem; color: var(--bs-secondary-color); }
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
            user-select: none;
        }
        #dwt-palette { user-select: none; }
        .dwt-palette-btn img { width: 40px; height: 40px; image-rendering: pixelated; border-radius: 3px; }
        .dwt-palette-btn > span { line-height: 1.1; text-align: center; word-break: break-all; }
        .dwt-palette-selected { border-color: #4dabf7; box-shadow: 0 0 0 2px rgba(77,171,247,.35); }
        .dwt-palette-eraser { font-size: 1.1rem; }
        /* Compact: flush tile atlas — no gaps, labels, or chrome between cells */
        #dwt-palette.is-compact {
            gap: 0 !important;
            line-height: 0;
        }
        #dwt-palette.is-compact .dwt-palette-btn {
            width: 40px;
            height: 40px;
            padding: 0;
            margin: 0;
            gap: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }
        #dwt-palette.is-compact .dwt-palette-btn img {
            width: 40px;
            height: 40px;
            border-radius: 0;
            display: block;
        }
        #dwt-palette.is-compact .dwt-palette-btn > span { display: none; }
        #dwt-palette.is-compact .dwt-palette-eraser {
            width: 40px;
            height: 40px;
            font-size: .95rem;
            align-items: center;
            justify-content: center;
            background: #15202b;
            color: #dce8f5;
        }
        #dwt-palette.is-compact .dwt-palette-selected {
            outline: 2px solid #4dabf7;
            outline-offset: -2px;
            z-index: 1;
            position: relative;
        }
        .dwt-palette-preview {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .75rem;
            padding: .5rem .65rem;
            border: 1px solid var(--bs-border-color);
            border-radius: 6px;
            background: #0f1419;
            min-height: 104px;
        }
        .dwt-palette-preview-art {
            position: relative;
            width: 96px;
            height: 96px;
            flex: 0 0 96px;
            border-radius: 4px;
            overflow: hidden;
            background: #111a26;
            border: 1px solid rgba(255,255,255,.08);
        }
        .dwt-palette-preview-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            image-rendering: pixelated;
            object-fit: contain;
        }
        .dwt-palette-preview-img.is-empty { display: none; }
        .dwt-palette-preview-mask {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            image-rendering: pixelated;
            pointer-events: none;
        }
        .dwt-palette-preview-mask.is-hidden { display: none; }
        .dwt-palette-preview-placeholder {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #111a26;
            color: #6c7a89;
            font-size: 1.6rem;
            border: 1px dashed rgba(255,255,255,.15);
        }
        .dwt-palette-preview-placeholder.is-hidden { display: none; }
        .dwt-palette-preview-meta { min-width: 0; flex: 1 1 auto; }
        .dwt-palette-preview-code { font-weight: 600; color: #dce8f5; word-break: break-all; }
        .dwt-palette-preview-sub { font-size: .78rem; color: #9fb2c3; margin-top: .15rem; }
        .dwt-palette-preview-actions { margin-top: .4rem; }
        .dwt-mask-float {
            position: fixed;
            z-index: 1090;
            top: 120px;
            left: 24px;
            width: 320px;
            display: none;
            flex-direction: column;
            background: #0f1419;
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            box-shadow: 0 12px 40px rgba(0,0,0,.45);
            overflow: hidden;
        }
        .dwt-mask-float.is-open { display: flex; }
        .dwt-mask-float.is-dragging { opacity: 0.92; user-select: none; }
        .dwt-mask-float-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .45rem .65rem;
            background: #15202b;
            border-bottom: 1px solid var(--bs-border-color);
            cursor: grab;
            flex: 0 0 auto;
        }
        .dwt-mask-float.is-dragging .dwt-mask-float-header { cursor: grabbing; }
        .dwt-mask-float-title { font-weight: 600; font-size: .9rem; color: #dce8f5; margin: 0; }
        .dwt-mask-float-body { padding: .65rem; }
        #dwt-float-mask-canvas { display: block; width: 100%; height: auto; background: #111a26; cursor: crosshair; image-rendering: pixelated; }
        .dwt-mask-float-status { font-size: .78rem; color: #9fb2c3; min-height: 1.2em; }
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
        .dwt-tab-nav .nav-link { color: #9fb2c3; }
        .dwt-tab-nav .nav-link.active { color: #4dabf7; font-weight: 600; }
        .dwt-map-tools { min-width: 220px; max-width: 300px; }
        .dwt-map-layout { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-start; }
        .dwt-palette-float {
            position: fixed;
            z-index: 1080;
            top: 0;
            right: 24px;
            width: 560px;
            height: 480px;
            min-width: 360px;
            min-height: 280px;
            max-width: min(95vw, 960px);
            max-height: 100%;
            display: flex;
            flex-direction: column;
            background: #0f1419;
            border: 1px solid var(--bs-border-color);
            border-radius: 8px;
            box-shadow: 0 12px 40px rgba(0,0,0,.45);
            overflow: hidden;
            resize: both;
        }
        .dwt-palette-float.is-dragging { opacity: 0.92; user-select: none; }
        .dwt-palette-float-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .45rem .65rem;
            background: #15202b;
            border-bottom: 1px solid var(--bs-border-color);
            cursor: grab;
            flex: 0 0 auto;
        }
        .dwt-palette-float.is-dragging .dwt-palette-float-header { cursor: grabbing; }
        .dwt-palette-float-title { font-weight: 600; font-size: .9rem; color: #dce8f5; margin: 0; }
        .dwt-palette-float-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            padding: .65rem;
            display: flex;
            flex-direction: row;
            gap: .65rem;
            align-items: stretch;
        }
        .dwt-palette-tiles-pane {
            flex: 1 1 auto;
            min-width: 0;
            min-height: 0;
            overflow: auto;
        }
        .dwt-palette-side {
            flex: 0 0 200px;
            width: 200px;
            min-width: 160px;
            max-width: 240px;
            display: flex;
            flex-direction: column;
            gap: .65rem;
            overflow: auto;
            border-left: 1px solid var(--bs-border-color);
            padding-left: .65rem;
        }
        .dwt-palette-float .dwt-palette-preview {
            margin-bottom: 0;
            flex-direction: column;
            align-items: stretch;
            min-height: 0;
        }
        .dwt-palette-float .dwt-palette-preview-art {
            width: 100%;
            height: auto;
            aspect-ratio: 1;
            flex: 0 0 auto;
        }
        #dwt-palette { align-content: flex-start; }
        .dwt-palette-dock-hint { font-size: .72rem; color: #9fb2c3; }
        .dwt-map-main { flex: 1 1 720px; min-width: 0; }
        .dwt-defs-catalog { max-height: 70vh; overflow: auto; }
    </style>
</head>
<body>
<div class="container-fluid py-3 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">World Tiles Dev</h1>
            <p class="meta mb-0">Zone Map paints placements; Definitions manages tile/object catalogs.</p>
        </div>
        <form method="get" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
            <input type="hidden" name="tab" value="<?php echo dev_admin_h($active_tab); ?>">
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

    <ul class="nav nav-tabs dwt-tab-nav mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $active_tab === 'map' ? ' active' : ''; ?>" id="dwt-tab-map-btn" data-bs-toggle="tab" data-bs-target="#dwt-tab-map" type="button" role="tab">Zone Map</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link<?php echo $active_tab === 'definitions' ? ' active' : ''; ?>" id="dwt-tab-definitions-btn" data-bs-toggle="tab" data-bs-target="#dwt-tab-definitions" type="button" role="tab">Definitions &amp; Catalogs</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade<?php echo $active_tab === 'map' ? ' show active' : ''; ?>" id="dwt-tab-map" role="tabpanel">
            <div class="card dev-card mb-3">
                <div class="card-header"><strong>Zone #<?php echo (int) $id_zone; ?> grid</strong> <span class="meta">(yellow = origin 0,0 · red = barrier · dashed amber = unsaved)</span></div>
                <div class="card-body">
                    <p class="meta mb-3">Pick a layer, then click or drag to paint. Edits are local until Save. Empty ground cells here are unassigned (live game fills them with base-pack tiles).</p>
                    <div class="dwt-map-layout">
                        <div class="dwt-map-main">
                            <div class="dwt-canvas-wrap">
                                <canvas id="dwt-canvas" width="960" height="720"></canvas>
                            </div>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="left">&larr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="up">&uarr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="down">&darr; pan</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="right">pan &rarr;</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm dwt-pan-btn" data-pan="home">Recenter</button>
                                <button type="button" class="btn btn-outline-warning btn-sm dwt-tool-btn" id="dwt-thermometer-btn" title="Sample tiles from the map into a brush (active + visible layers)">Thermometer</button>
                            </div>
                            <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
                                <button type="button" class="btn btn-outline-info btn-sm" id="dwt-preview-toggle">Preview: showing edits</button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="dwt-revert-last-btn" disabled title="Undo the last paint stroke (up to 50)">Revert last</button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="dwt-revert-btn">Revert all</button>
                                <button type="button" class="btn btn-success btn-sm" id="dwt-save-btn">Save changes</button>
                                <span class="meta">Unsaved cells: <span id="dwt-dirty-count">0</span></span>
                            </div>
                            <p class="meta mt-2 mb-0">Hover: <span id="dwt-coords">—</span> · Brush: <span id="dwt-brush-label">none</span> · <span id="dwt-status"></span></p>
                        </div>
                        <div class="dwt-map-tools">
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
                            <p class="dwt-palette-dock-hint mb-0">Tile palette floats on the right — drag the header, resize from the corner.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card dev-card mb-3">
                <div class="card-header"><strong>Objects in zone #<?php echo (int) $id_zone; ?></strong> <span class="meta">(large multi-cell art; grid-snapped origin)</span></div>
                <div class="card-body">
                    <form method="post" class="d-flex flex-wrap gap-2 align-items-end mb-3">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                        <input type="hidden" name="tab" value="map">
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
                                        <input type="hidden" name="tab" value="map">
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

        <div class="tab-pane fade<?php echo $active_tab === 'definitions' ? ' show active' : ''; ?>" id="dwt-tab-definitions" role="tabpanel">
            <div class="card dev-card mb-4">
                <div class="card-header"><strong>Import tileset (cut PNG → tiles)</strong></div>
                <div class="card-body">
                    <p class="dwt-import-hint mb-3">
                        Upload a packed sheet (e.g. 2048×2048 with 128×128 cells). Each cell is cut out, optionally resized,
                        saved under <code>client/img/tiles/</code>, and registered as a <code>tile_definitions</code> row so it appears in the palette.
                    </p>
                    <form method="post" enctype="multipart/form-data" id="dwt-tileset-import-form">
                        <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                        <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                        <input type="hidden" name="tab" value="definitions">
                        <input type="hidden" name="action" value="import_tileset">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tileset PNG</label>
                                <input class="form-control form-control-sm" type="file" name="tileset" id="dwt-tileset-file" accept="image/png,.png" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">code_prefix <span class="meta">(files: prefix_rr_cc.png)</span></label>
                                <input class="form-control form-control-sm" name="code_prefix" id="dwt-tileset-prefix" maxlength="32" required value="pack" pattern="[A-Za-z0-9_]+">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">pack <span class="meta">(palette filter; blank = code_prefix)</span></label>
                                <input class="form-control form-control-sm" name="pack" id="dwt-tileset-pack" maxlength="50" value="" pattern="[A-Za-z0-9_]*" placeholder="same as prefix">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">source_tile_px <span class="meta">(cell in sheet)</span></label>
                                <input class="form-control form-control-sm" type="number" name="source_tile_px" id="dwt-source-tile-px" min="8" max="1024" required value="128">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">output_tile_px <span class="meta">(saved size)</span></label>
                                <input class="form-control form-control-sm" type="number" name="output_tile_px" id="dwt-output-tile-px" min="8" max="1024" required value="100">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">sheet_width_px <span class="meta">(0 = auto)</span></label>
                                <input class="form-control form-control-sm" type="number" name="sheet_width_px" id="dwt-sheet-w" min="0" max="8192" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">sheet_height_px <span class="meta">(0 = auto)</span></label>
                                <input class="form-control form-control-sm" type="number" name="sheet_height_px" id="dwt-sheet-h" min="0" max="8192" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">category</label>
                                <select class="form-select form-select-sm" name="category">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo dev_admin_h($cat); ?>"<?php echo $cat === 'base_pack' ? ' selected' : ''; ?>><?php echo dev_admin_h($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">id_tile_layer</label>
                                <select class="form-select form-select-sm" name="id_tile_layer">
                                    <option value="0">(any layer)</option>
                                    <?php foreach ($layers as $layer): ?>
                                    <option value="<?php echo (int) $layer['id_tile_layer']; ?>"<?php echo (int) $layer['id_tile_layer'] === (int) $ground_layer_id ? ' selected' : ''; ?>><?php echo dev_admin_h($layer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">id_zone scope</label>
                                <input class="form-control form-control-sm" type="number" name="id_zone_scope" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">sort_order start</label>
                                <input class="form-control form-control-sm" type="number" name="sort_order" value="100">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">move_speed_mult</label>
                                <input class="form-control form-control-sm" type="number" step="0.05" min="0" name="move_speed_mult" value="1.00">
                            </div>
                            <div class="col-md-12">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="is_walkable" value="S" id="dwt-import-walkable" checked>
                                    <label class="form-check-label" for="dwt-import-walkable">is_walkable</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="is_base_pack" value="S" id="dwt-import-base">
                                    <label class="form-check-label" for="dwt-import-base">is_base_pack</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="skip_blank" value="1" id="dwt-import-skip-blank" checked>
                                    <label class="form-check-label" for="dwt-import-skip-blank">skip blank / fully transparent cells</label>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div id="dwt-tileset-meta" class="dwt-import-hint mb-2">Pick a PNG to preview the cut grid.</div>
                                <div class="dwt-tileset-preview-wrap mb-2" hidden id="dwt-tileset-preview-box">
                                    <canvas id="dwt-tileset-preview" width="320" height="320"></canvas>
                                </div>
                                <button type="submit" class="btn btn-success btn-sm" id="dwt-tileset-import-btn">Cut &amp; import tileset</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-5">
                    <div class="card dev-card mb-3">
                        <div class="card-header"><strong><?php echo $edit_definition ? 'Edit tile definition #' . (int) $edit_definition['id_tile_definition'] : 'New tile definition'; ?></strong></div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                                <input type="hidden" name="tab" value="definitions">
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
                                    <label class="form-label">pack <span class="meta">(tileset group for palette filter)</span></label>
                                    <input class="form-control form-control-sm" name="pack" maxlength="50" pattern="[A-Za-z0-9_]*" value="<?php echo dev_admin_h($edit_definition['pack'] ?? ''); ?>" list="dwt-pack-datalist">
                                    <datalist id="dwt-pack-datalist">
                                        <?php foreach ($tile_packs as $pack_name): ?>
                                        <option value="<?php echo dev_admin_h($pack_name); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
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
                                    <label class="form-label mb-1">collision_mask <span class="meta">(sub-tile barrier shape; paint blocked cells — leave blank for whole-tile is_walkable)</span></label>
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
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'tab' => 'definitions'])); ?>">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7">
                    <div class="card dev-card mb-3">
                        <div class="card-header"><strong>Tile catalog (<?php echo count($definitions); ?>)</strong></div>
                        <div class="card-body p-0 dwt-defs-catalog">
                            <table class="table table-sm table-dark align-middle mb-0">
                                <tbody>
                                    <?php foreach ($definitions as $def): ?>
                                    <tr>
                                        <td><img class="dwt-thumb" src="client/img/tiles/<?php echo dev_admin_h($def['image_file']); ?>" alt=""></td>
                                        <td>
                                            #<?php echo (int) $def['id_tile_definition']; ?> <?php echo dev_admin_h($def['code']); ?>
                                            <div class="meta"><?php echo !empty($def['pack']) ? 'pack ' . dev_admin_h($def['pack']) . ' · ' : ''; ?><?php echo dev_admin_h($def['category']); ?> · <?php echo !empty($def['collision_mask']) ? 'mixed terrain' : ($def['is_walkable'] === 'N' ? 'barrier' : 'walkable'); ?> · x<?php echo dev_admin_h((string) $def['move_speed_mult']); ?> speed<?php echo $def['is_base_pack'] === 'S' ? ' · base pack' : ''; ?><?php echo $def['id_zone'] ? ' · zone ' . (int) $def['id_zone'] : ''; ?></div>
                                        </td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'tab' => 'definitions', 'edit_id' => (int) $def['id_tile_definition']])); ?>">Edit</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-5">
                    <div class="card dev-card mb-3">
                        <div class="card-header"><strong><?php echo $edit_object_definition ? 'Edit object definition #' . (int) $edit_object_definition['id_object_definition'] : 'New object definition'; ?></strong></div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="T" value="<?php echo dev_admin_h($token); ?>">
                                <input type="hidden" name="id_zone" value="<?php echo (int) $id_zone; ?>">
                                <input type="hidden" name="tab" value="definitions">
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
                                <p class="meta mb-2">Fraction of the image aligned to the origin cell -- default 0.5/1.0 = bottom-center.</p>
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
                                <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'tab' => 'definitions'])); ?>">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-7">
                    <div class="card dev-card mb-3">
                        <div class="card-header"><strong>Object catalog (<?php echo count($object_definitions); ?>)</strong></div>
                        <div class="card-body p-0 dwt-defs-catalog">
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
                                            <a class="btn btn-outline-secondary btn-sm" href="<?php echo dev_admin_h(dev_admin_page_url('dev_world_tiles.php', ['id_zone' => $id_zone, 'tab' => 'definitions', 'edit_object_id' => (int) $odef['id_object_definition']])); ?>">Edit</a>
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
    </div>

    <div id="dwt-palette-float" class="dwt-palette-float" aria-label="Tile palette">
        <div class="dwt-palette-float-header" id="dwt-palette-float-header">
            <p class="dwt-palette-float-title">Palette</p>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="dwt-palette-reset-pos" title="Reset position/size">reset</button>
        </div>
        <div class="dwt-palette-float-body">
            <div class="dwt-palette-tiles-pane">
                <div id="dwt-palette" class="d-flex flex-wrap gap-2">
                    <?php foreach ($definitions as $def): ?>
                    <button type="button" class="dwt-palette-btn" data-brush="<?php echo (int) $def['id_tile_definition']; ?>" data-label="<?php echo dev_admin_h($def['code']); ?>" data-layer="<?php echo (int) ($def['id_tile_layer'] ?? 0); ?>" data-pack="<?php echo dev_admin_h((string) ($def['pack'] ?? '')); ?>" data-image="<?php echo dev_admin_h($def['image_file']); ?>">
                        <img src="client/img/tiles/<?php echo dev_admin_h($def['image_file']); ?>" alt="">
                        <span><?php echo dev_admin_h($def['code']); ?></span>
                    </button>
                    <?php endforeach; ?>
                    <button type="button" class="dwt-palette-btn dwt-palette-eraser" data-brush="ERASER" data-label="Eraser" data-layer="0" data-pack="*">
                        &#10060;
                        <span>Eraser</span>
                    </button>
                </div>
            </div>
            <div class="dwt-palette-side">
                <div>
                    <label class="form-label meta mb-1" for="dwt-pack-filter">Pack</label>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" id="dwt-pack-filter">
                            <option value="*">All packs</option>
                            <?php if ($has_unpacked_tiles): ?>
                            <option value="">(no pack)</option>
                            <?php endif; ?>
                            <?php foreach ($tile_packs as $pack_name): ?>
                            <option value="<?php echo dev_admin_h($pack_name); ?>"><?php echo dev_admin_h($pack_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" id="dwt-palette-density-btn" title="Toggle flush tile grid (no gaps)">spaced</button>
                    </div>
                    <div class="meta mt-1">Brush: click · drag · Ctrl · Shift</div>
                </div>
                <div class="dwt-palette-preview" id="dwt-palette-preview">
                    <div class="dwt-palette-preview-art" id="dwt-palette-preview-art">
                        <img class="dwt-palette-preview-img is-empty" id="dwt-palette-preview-img" alt="">
                        <div class="dwt-palette-preview-placeholder" id="dwt-palette-preview-placeholder">—</div>
                        <canvas class="dwt-palette-preview-mask is-hidden" id="dwt-palette-preview-mask" width="96" height="96"></canvas>
                    </div>
                    <div class="dwt-palette-preview-meta">
                        <div class="dwt-palette-preview-code" id="dwt-palette-preview-code">No brush selected</div>
                        <div class="dwt-palette-preview-sub" id="dwt-palette-preview-sub">Pick a tile from the palette</div>
                        <div class="dwt-palette-preview-actions">
                            <div class="mb-1" id="dwt-preview-layer-wrap" hidden>
                                <label class="form-label meta mb-0" for="dwt-preview-id-tile-layer">layer</label>
                                <select class="form-select form-select-sm" id="dwt-preview-id-tile-layer">
                                    <option value="__mixed__" disabled hidden>mixed…</option>
                                    <option value="0">(any layer)</option>
                                    <?php foreach ($layers as $layer): ?>
                                    <option value="<?php echo (int) $layer['id_tile_layer']; ?>"><?php echo dev_admin_h($layer['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-check mb-1" id="dwt-preview-walkable-wrap" hidden>
                                <input class="form-check-input" type="checkbox" id="dwt-preview-is-walkable">
                                <label class="form-check-label meta" for="dwt-preview-is-walkable">is_walkable</label>
                            </div>
                            <div class="form-check mb-1" id="dwt-preview-mask-toggle-wrap" hidden>
                                <input class="form-check-input" type="checkbox" id="dwt-preview-mask-toggle">
                                <label class="form-check-label meta" for="dwt-preview-mask-toggle">Show collision mask</label>
                            </div>
                            <button type="button" class="btn btn-outline-info btn-sm" id="dwt-edit-mask-btn" hidden>Edit collision mask</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="dwt-mask-float" class="dwt-mask-float" aria-label="Collision mask editor">
        <div class="dwt-mask-float-header" id="dwt-mask-float-header">
            <p class="dwt-mask-float-title" id="dwt-mask-float-title">Collision mask</p>
            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" id="dwt-mask-float-close" title="Close">✕</button>
        </div>
        <div class="dwt-mask-float-body">
            <p class="meta mb-2">Paint blocked cells (red). Empty mask = whole-tile <code>is_walkable</code>.</p>
            <div class="dwt-mask-canvas-wrap mb-2" style="width:100%;">
                <canvas id="dwt-float-mask-canvas" width="256" height="256"></canvas>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="dwt-float-mask-clear">Clear mask</button>
                <button type="button" class="btn btn-success btn-sm" id="dwt-float-mask-save">Save mask</button>
            </div>
            <div class="dwt-mask-float-status" id="dwt-mask-float-status"></div>
        </div>
    </div>
</div>
<script>
    window.DEV_WORLD_TILES_BOOTSTRAP = <?php echo json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="client/js/dev_world_tiles.js"></script>
</body>
</html>
