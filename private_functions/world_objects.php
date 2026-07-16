<?php

/**
 * Overworld large-object system: shared read/write helpers for the
 * get_world_tiles.php endpoint and the dev_world_tiles.php admin editor.
 *
 * Unlike tile_definitions (always exactly one grid cell), an object has its
 * own world-unit footprint (width_world/height_world) and renders at that
 * size regardless of the source PNG's native pixel resolution -- the same
 * "canvas stretches to destination size" approach tiles already use.
 * Placement origin (grid_x/grid_z) is grid-snapped like tiles; anchor_x/
 * anchor_y (from the definition) locate which point of the image sits on
 * that cell. v1 collision is a simple bounding rectangle (no per-object
 * mask).
 */

/**
 * Object catalog usable anywhere (no zone-scoping for objects yet).
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_objects_fetch_definitions(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_object_definition, code, image_file, width_world, height_world,
               anchor_x, anchor_y, is_walkable, sort_order
        FROM object_definitions
        ORDER BY sort_order ASC, id_object_definition ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Alias kept for symmetry with animaster_world_tiles_fetch_all_definitions()
 * -- the admin editor's catalog table and the live game currently use the
 * same unscoped catalog.
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_objects_fetch_all_definitions(PDO $conn)
{
    return animaster_world_objects_fetch_definitions($conn);
}

/**
 * Placed objects for one zone.
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_objects_fetch_placements(PDO $conn, $id_zone)
{
    $stmt = $conn->prepare('
        SELECT id_world_object, id_zone, grid_x, grid_z, id_object_definition
        FROM world_objects
        WHERE id_zone = :id_zone
    ');
    $stmt->execute([':id_zone' => (int) $id_zone]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Place a new object instance (objects can overlap/scatter freely, no
 * unique-per-cell constraint).
 *
 * @return int inserted id_world_object
 */
function animaster_world_objects_place(PDO $conn, $id_zone, $grid_x, $grid_z, $id_object_definition)
{
    $stmt = $conn->prepare('
        INSERT INTO world_objects (id_zone, grid_x, grid_z, id_object_definition)
        VALUES (:id_zone, :grid_x, :grid_z, :id_object_definition)
    ');
    $stmt->execute([
        ':id_zone' => (int) $id_zone,
        ':grid_x' => (int) $grid_x,
        ':grid_z' => (int) $grid_z,
        ':id_object_definition' => (int) $id_object_definition
    ]);

    return (int) $conn->lastInsertId();
}

/**
 * Remove one placed object instance by id.
 */
function animaster_world_objects_clear(PDO $conn, $id_world_object)
{
    $stmt = $conn->prepare('DELETE FROM world_objects WHERE id_world_object = :id LIMIT 1');

    return $stmt->execute([':id' => (int) $id_world_object]);
}
