<?php

require_once __DIR__ . '/world_tiles.php';
require_once __DIR__ . '/world_objects.php';

function dev_world_tiles_fetch_zones(PDO $conn)
{
    $stmt = $conn->query('SELECT id_zone, scene_name FROM zones ORDER BY id_zone');

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

/**
 * @return array<int, string>
 */
function dev_world_tiles_categories()
{
    return ['base_pack', 'landmark', 'terrain'];
}

function dev_world_tiles_post_int(array $post, $key, $default = 0)
{
    return isset($post[$key]) ? (int) $post[$key] : $default;
}

function dev_world_tiles_post_str(array $post, $key, $max_len = 150)
{
    $value = isset($post[$key]) ? trim((string) $post[$key]) : '';

    return mb_substr($value, 0, $max_len);
}

function dev_world_tiles_post_yn(array $post, $key, $default = 'N')
{
    $value = dev_world_tiles_post_str($post, $key, 1);

    return ($value === 'S') ? 'S' : $default;
}

function dev_world_tiles_post_decimal(array $post, $key, $default = 1.00)
{
    if (!isset($post[$key]) || trim((string) $post[$key]) === '')
    {
        return $default;
    }

    return (float) str_replace(',', '.', (string) $post[$key]);
}

/**
 * Reads+validates the sub-tile collision mask hidden field written by the
 * mask painter in dev_world_tiles.js: a row-major string of exactly
 * ANIMASTER_TILE_MASK_SIZE^2 '0'/'1' characters. Empty or malformed input
 * clears the mask (NULL = whole tile falls back to is_walkable).
 *
 * @return string|null
 */
function dev_world_tiles_post_mask(array $post, $key = 'collision_mask')
{
    $raw = isset($post[$key]) ? trim((string) $post[$key]) : '';

    if ($raw === '')
    {
        return null;
    }

    $expected_len = ANIMASTER_TILE_MASK_SIZE * ANIMASTER_TILE_MASK_SIZE;

    if (strlen($raw) !== $expected_len || !preg_match('/^[01]+$/', $raw))
    {
        return null;
    }

    return $raw;
}

/**
 * Parses+validates the JSON diff posted by the Save button in
 * dev_world_tiles.js: an array of {gx, gz, layer, def} entries (def <= 0
 * means "clear this cell/layer"). Returns null on malformed input.
 *
 * @return array<int, array{gx:int,gz:int,layer:int,def:int}>|null
 */
function dev_world_tiles_post_changes(array $post, $key = 'changes')
{
    $raw = isset($post[$key]) ? (string) $post[$key] : '';

    if ($raw === '')
    {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded) || count($decoded) > 5000)
    {
        return null;
    }

    $changes = [];

    foreach ($decoded as $entry)
    {
        if (!is_array($entry) || !isset($entry['gx'], $entry['gz'], $entry['layer']))
        {
            return null;
        }

        $changes[] = [
            'gx' => (int) $entry['gx'],
            'gz' => (int) $entry['gz'],
            'layer' => (int) $entry['layer'],
            'def' => isset($entry['def']) ? (int) $entry['def'] : 0
        ];
    }

    return $changes;
}

/**
 * AJAX-only actions triggered by the canvas / palette / toolbar in
 * dev_world_tiles.js. Returns null when $post['action'] isn't one of these
 * (caller should fall through to the regular POST-redirect-GET handler for
 * CRUD forms).
 *
 * @return array{ok:bool,message?:string}|null
 */
function dev_world_tiles_handle_ajax_action(PDO $conn, array $post)
{
    $action = dev_world_tiles_post_str($post, 'action', 50);

    if ($action !== 'save_tile_batch')
    {
        return null;
    }

    $id_zone = dev_world_tiles_post_int($post, 'id_zone');

    if ($id_zone <= 0)
    {
        return ['ok' => false, 'message' => 'Invalid zone.'];
    }

    try
    {
        $changes = dev_world_tiles_post_changes($post);

        if ($changes === null)
        {
            return ['ok' => false, 'message' => 'Malformed change list.'];
        }

        if (!$changes)
        {
            return ['ok' => true, 'message' => 'Nothing to save.'];
        }

        $conn->beginTransaction();

        foreach ($changes as $change)
        {
            if ($change['def'] > 0)
            {
                animaster_world_tiles_place($conn, $id_zone, $change['gx'], $change['gz'], $change['layer'], $change['def']);
            }
            else
            {
                animaster_world_tiles_clear($conn, $id_zone, $change['gx'], $change['gz'], $change['layer']);
            }
        }

        $conn->commit();

        return ['ok' => true, 'message' => 'Saved ' . count($changes) . ' change(s).'];
    }
    catch (PDOException $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        error_log('[dev_world_tiles_content] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * @return array{ok:bool,message?:string}
 */
function dev_world_tiles_handle_post(PDO $conn, array $post)
{
    $action = dev_world_tiles_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'add_tile_definition':
                $stmt = $conn->prepare('
                    INSERT INTO tile_definitions
                        (code, image_file, category, id_zone, id_tile_layer, is_base_pack, is_walkable, move_speed_mult, collision_mask, sort_order)
                    VALUES
                        (:code, :image_file, :category, :id_zone, :id_tile_layer, :is_base_pack, :is_walkable, :move_speed_mult, :collision_mask, :sort_order)
                ');
                $stmt->execute([
                    ':code' => dev_world_tiles_post_str($post, 'code', 50),
                    ':image_file' => dev_world_tiles_post_str($post, 'image_file', 150),
                    ':category' => dev_world_tiles_post_str($post, 'category', 20) ?: 'base_pack',
                    ':id_zone' => dev_world_tiles_post_int($post, 'id_zone_scope') ?: null,
                    ':id_tile_layer' => dev_world_tiles_post_int($post, 'id_tile_layer') ?: null,
                    ':is_base_pack' => dev_world_tiles_post_yn($post, 'is_base_pack'),
                    ':is_walkable' => dev_world_tiles_post_yn($post, 'is_walkable', 'S'),
                    ':move_speed_mult' => dev_world_tiles_post_decimal($post, 'move_speed_mult', 1.00),
                    ':collision_mask' => dev_world_tiles_post_mask($post),
                    ':sort_order' => dev_world_tiles_post_int($post, 'sort_order')
                ]);

                return ['ok' => true, 'message' => 'Tile definition created (id ' . $conn->lastInsertId() . ').'];

            case 'update_tile_definition':
                $stmt = $conn->prepare('
                    UPDATE tile_definitions
                    SET code = :code,
                        image_file = :image_file,
                        category = :category,
                        id_zone = :id_zone,
                        id_tile_layer = :id_tile_layer,
                        is_base_pack = :is_base_pack,
                        is_walkable = :is_walkable,
                        move_speed_mult = :move_speed_mult,
                        collision_mask = :collision_mask,
                        sort_order = :sort_order
                    WHERE id_tile_definition = :id_tile_definition
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_tile_definition' => dev_world_tiles_post_int($post, 'id_tile_definition'),
                    ':code' => dev_world_tiles_post_str($post, 'code', 50),
                    ':image_file' => dev_world_tiles_post_str($post, 'image_file', 150),
                    ':category' => dev_world_tiles_post_str($post, 'category', 20) ?: 'base_pack',
                    ':id_zone' => dev_world_tiles_post_int($post, 'id_zone_scope') ?: null,
                    ':id_tile_layer' => dev_world_tiles_post_int($post, 'id_tile_layer') ?: null,
                    ':is_base_pack' => dev_world_tiles_post_yn($post, 'is_base_pack'),
                    ':is_walkable' => dev_world_tiles_post_yn($post, 'is_walkable', 'S'),
                    ':move_speed_mult' => dev_world_tiles_post_decimal($post, 'move_speed_mult', 1.00),
                    ':collision_mask' => dev_world_tiles_post_mask($post),
                    ':sort_order' => dev_world_tiles_post_int($post, 'sort_order')
                ]);

                return ['ok' => true, 'message' => 'Tile definition updated (id ' . dev_world_tiles_post_int($post, 'id_tile_definition') . ').'];

            case 'delete_tile_definition':
                $id_tile_definition = dev_world_tiles_post_int($post, 'id_tile_definition');

                if ($id_tile_definition <= 0)
                {
                    return ['ok' => false, 'message' => 'Invalid tile definition id.'];
                }

                $stmt = $conn->prepare('DELETE FROM tile_definitions WHERE id_tile_definition = :id LIMIT 1');
                $stmt->execute([':id' => $id_tile_definition]);

                if ($stmt->rowCount() === 0)
                {
                    return ['ok' => false, 'message' => 'Tile definition #' . $id_tile_definition . ' not found.'];
                }

                return ['ok' => true, 'message' => 'Tile definition #' . $id_tile_definition . ' deleted.'];

            case 'add_object_definition':
                $stmt = $conn->prepare('
                    INSERT INTO object_definitions
                        (code, image_file, width_world, height_world, anchor_x, anchor_y, is_walkable, sort_order)
                    VALUES
                        (:code, :image_file, :width_world, :height_world, :anchor_x, :anchor_y, :is_walkable, :sort_order)
                ');
                $stmt->execute([
                    ':code' => dev_world_tiles_post_str($post, 'code', 50),
                    ':image_file' => dev_world_tiles_post_str($post, 'image_file', 150),
                    ':width_world' => dev_world_tiles_post_decimal($post, 'width_world', 25.00),
                    ':height_world' => dev_world_tiles_post_decimal($post, 'height_world', 25.00),
                    ':anchor_x' => dev_world_tiles_post_decimal($post, 'anchor_x', 0.50),
                    ':anchor_y' => dev_world_tiles_post_decimal($post, 'anchor_y', 1.00),
                    ':is_walkable' => dev_world_tiles_post_yn($post, 'is_walkable', 'N'),
                    ':sort_order' => dev_world_tiles_post_int($post, 'sort_order')
                ]);

                return ['ok' => true, 'message' => 'Object definition created (id ' . $conn->lastInsertId() . ').'];

            case 'update_object_definition':
                $stmt = $conn->prepare('
                    UPDATE object_definitions
                    SET code = :code,
                        image_file = :image_file,
                        width_world = :width_world,
                        height_world = :height_world,
                        anchor_x = :anchor_x,
                        anchor_y = :anchor_y,
                        is_walkable = :is_walkable,
                        sort_order = :sort_order
                    WHERE id_object_definition = :id_object_definition
                    LIMIT 1
                ');
                $stmt->execute([
                    ':id_object_definition' => dev_world_tiles_post_int($post, 'id_object_definition'),
                    ':code' => dev_world_tiles_post_str($post, 'code', 50),
                    ':image_file' => dev_world_tiles_post_str($post, 'image_file', 150),
                    ':width_world' => dev_world_tiles_post_decimal($post, 'width_world', 25.00),
                    ':height_world' => dev_world_tiles_post_decimal($post, 'height_world', 25.00),
                    ':anchor_x' => dev_world_tiles_post_decimal($post, 'anchor_x', 0.50),
                    ':anchor_y' => dev_world_tiles_post_decimal($post, 'anchor_y', 1.00),
                    ':is_walkable' => dev_world_tiles_post_yn($post, 'is_walkable', 'N'),
                    ':sort_order' => dev_world_tiles_post_int($post, 'sort_order')
                ]);

                return ['ok' => true, 'message' => 'Object definition updated (id ' . dev_world_tiles_post_int($post, 'id_object_definition') . ').'];

            case 'delete_object_definition':
                $id_object_definition = dev_world_tiles_post_int($post, 'id_object_definition');

                if ($id_object_definition <= 0)
                {
                    return ['ok' => false, 'message' => 'Invalid object definition id.'];
                }

                $stmt = $conn->prepare('DELETE FROM object_definitions WHERE id_object_definition = :id LIMIT 1');
                $stmt->execute([':id' => $id_object_definition]);

                if ($stmt->rowCount() === 0)
                {
                    return ['ok' => false, 'message' => 'Object definition #' . $id_object_definition . ' not found.'];
                }

                return ['ok' => true, 'message' => 'Object definition #' . $id_object_definition . ' deleted.'];

            case 'place_object':
                $id_object_definition = dev_world_tiles_post_int($post, 'id_object_definition');
                $id_zone = dev_world_tiles_post_int($post, 'id_zone');

                if ($id_object_definition <= 0 || $id_zone <= 0)
                {
                    return ['ok' => false, 'message' => 'Pick a zone and an object from the catalog first.'];
                }

                animaster_world_objects_place(
                    $conn,
                    $id_zone,
                    dev_world_tiles_post_int($post, 'grid_x'),
                    dev_world_tiles_post_int($post, 'grid_z'),
                    $id_object_definition
                );

                return ['ok' => true, 'message' => 'Object placed.'];

            case 'clear_object':
                $id_world_object = dev_world_tiles_post_int($post, 'id_world_object');

                if ($id_world_object <= 0)
                {
                    return ['ok' => false, 'message' => 'Invalid object id.'];
                }

                animaster_world_objects_clear($conn, $id_world_object);

                return ['ok' => true, 'message' => 'Object removed.'];

            default:
                return ['ok' => false, 'message' => 'Unknown action.'];
        }
    }
    catch (PDOException $e)
    {
        error_log('[dev_world_tiles_content] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}
