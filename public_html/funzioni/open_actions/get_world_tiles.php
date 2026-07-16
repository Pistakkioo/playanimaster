<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/world_tiles.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/world_objects.php';

$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;

if ($id_zone <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_ZONE',
        'response' => ''
    ]);
    exit;
}

$tile_layers = animaster_world_tiles_fetch_layers($conn);
$tile_definitions = animaster_world_tiles_fetch_definitions($conn, $id_zone);
$placements = animaster_world_tiles_fetch_placements($conn, $id_zone);
$object_definitions = animaster_world_objects_fetch_definitions($conn);
$objects = animaster_world_objects_fetch_placements($conn, $id_zone);

echo json_encode([
    'stato' => 'OK',
    'msg' => 'OK',
    'response' => json_encode([
        'tile_world_size' => ANIMASTER_TILE_WORLD_SIZE,
        'tile_mask_size' => ANIMASTER_TILE_MASK_SIZE,
        'tile_layers' => $tile_layers,
        'tile_definitions' => $tile_definitions,
        'placements' => $placements,
        'object_definitions' => $object_definitions,
        'objects' => $objects
    ])
]);
