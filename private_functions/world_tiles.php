<?php

/**
 * Overworld tile & terrain system (sidequest): shared read helpers for the
 * get_world_tiles.php endpoint and the dev_world_tiles.php / dev_tile_definitions.php
 * admin editor.
 *
 * Grid convention: TILE_WORLD_SIZE world units per cell (kept in sync with
 * the client-side constant in world_tiles.js). grid_x/grid_z = floor(world / TILE_WORLD_SIZE).
 */

const ANIMASTER_TILE_WORLD_SIZE = 25;

/**
 * Side length of the optional sub-tile collision mask grid (see
 * tile_definitions.collision_mask): ANIMASTER_TILE_MASK_SIZE^2 characters,
 * row-major, '1' = walkable / '0' = blocked. Kept in sync with
 * TILE_MASK_SIZE in world_tiles.js and dev_world_tiles.js.
 */
const ANIMASTER_TILE_MASK_SIZE = 8;

/**
 * Fixed, SQL-seeded set of tile layers (see 03_world_tiles_seed.sql), all
 * zones/cells share the same layers -- no per-zone or admin-managed layers.
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_tiles_fetch_layers(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_tile_layer, code, name, z_order, is_ground, sort_order
        FROM tile_layers
        ORDER BY z_order ASC, id_tile_layer ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Tile catalog usable in a given zone: zone-specific rows plus global
 * (id_zone IS NULL) rows.
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_tiles_fetch_definitions(PDO $conn, $id_zone)
{
    $stmt = $conn->prepare('
        SELECT id_tile_definition, code, image_file, category, pack, id_zone, id_tile_layer,
               is_base_pack, is_walkable, move_speed_mult, collision_mask, sort_order
        FROM tile_definitions
        WHERE id_zone IS NULL OR id_zone = :id_zone
        ORDER BY sort_order ASC, id_tile_definition ASC
    ');
    $stmt->execute([':id_zone' => (int) $id_zone]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All tile catalog rows, for the admin editor (every zone's palette).
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_tiles_fetch_all_definitions(PDO $conn)
{
    $stmt = $conn->query('
        SELECT id_tile_definition, code, image_file, category, pack, id_zone, id_tile_layer,
               is_base_pack, is_walkable, move_speed_mult, collision_mask, sort_order
        FROM tile_definitions
        ORDER BY pack ASC, sort_order ASC, id_tile_definition ASC
    ');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * Distinct non-empty pack keys for the admin palette filter.
 *
 * @return array<int, string>
 */
function animaster_world_tiles_fetch_packs(PDO $conn)
{
    $stmt = $conn->query('
        SELECT DISTINCT pack
        FROM tile_definitions
        WHERE pack <> \'\'
        ORDER BY pack ASC
    ');

    if (!$stmt)
    {
        return [];
    }

    $packs = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
    {
        $packs[] = (string) $row['pack'];
    }

    return $packs;
}

/**
 * Sparse per-cell, per-layer placements for one zone.
 *
 * @return array<int, array<string, mixed>>
 */
function animaster_world_tiles_fetch_placements(PDO $conn, $id_zone)
{
    $stmt = $conn->prepare('
        SELECT id_world_tile, id_zone, grid_x, grid_z, id_tile_layer, id_tile_definition
        FROM world_tiles
        WHERE id_zone = :id_zone
    ');
    $stmt->execute([':id_zone' => (int) $id_zone]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Insert or move a placement onto one grid cell of one layer (one
 * placement per cell per layer).
 */
function animaster_world_tiles_place(PDO $conn, $id_zone, $grid_x, $grid_z, $id_tile_layer, $id_tile_definition)
{
    $stmt = $conn->prepare('
        INSERT INTO world_tiles (id_zone, grid_x, grid_z, id_tile_layer, id_tile_definition)
        VALUES (:id_zone, :grid_x, :grid_z, :id_tile_layer, :id_tile_definition)
        ON DUPLICATE KEY UPDATE id_tile_definition = VALUES(id_tile_definition)
    ');

    return $stmt->execute([
        ':id_zone' => (int) $id_zone,
        ':grid_x' => (int) $grid_x,
        ':grid_z' => (int) $grid_z,
        ':id_tile_layer' => (int) $id_tile_layer,
        ':id_tile_definition' => (int) $id_tile_definition
    ]);
}

/**
 * Remove a placement from one layer, exposing the cell back to deterministic
 * base-pack fill (ground layer) or plain emptiness (overlay layers).
 */
function animaster_world_tiles_clear(PDO $conn, $id_zone, $grid_x, $grid_z, $id_tile_layer)
{
    $stmt = $conn->prepare('
        DELETE FROM world_tiles
        WHERE id_zone = :id_zone AND grid_x = :grid_x AND grid_z = :grid_z AND id_tile_layer = :id_tile_layer
        LIMIT 1
    ');

    return $stmt->execute([
        ':id_zone' => (int) $id_zone,
        ':grid_x' => (int) $grid_x,
        ':grid_z' => (int) $grid_z,
        ':id_tile_layer' => (int) $id_tile_layer
    ]);
}
