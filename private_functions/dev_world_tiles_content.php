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
 * Parses a JSON array or comma-separated list of positive tile definition ids.
 *
 * @return array<int, int>|null null when empty or over $max
 */
function dev_world_tiles_post_definition_ids(array $post, $key = 'id_tile_definitions', $max = 64)
{
    $raw_ids = isset($post[$key]) ? trim((string) $post[$key]) : '';
    $ids = [];

    if ($raw_ids === '')
    {
        return null;
    }

    $decoded = json_decode($raw_ids, true);

    if (is_array($decoded))
    {
        foreach ($decoded as $id)
        {
            $id = (int) $id;

            if ($id > 0)
            {
                $ids[$id] = $id;
            }
        }
    }
    else
    {
        foreach (preg_split('/\s*,\s*/', $raw_ids) as $part)
        {
            $id = (int) $part;

            if ($id > 0)
            {
                $ids[$id] = $id;
            }
        }
    }

    $ids = array_values($ids);

    if (!$ids || count($ids) > $max)
    {
        return null;
    }

    return $ids;
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

    if ($action === 'save_tile_collision_mask')
    {
        $id_tile_definition = dev_world_tiles_post_int($post, 'id_tile_definition');

        if ($id_tile_definition <= 0)
        {
            return ['ok' => false, 'message' => 'Invalid tile definition id.'];
        }

        try
        {
            $mask = dev_world_tiles_post_mask($post);
            $stmt = $conn->prepare('
                UPDATE tile_definitions
                SET collision_mask = :collision_mask
                WHERE id_tile_definition = :id_tile_definition
                LIMIT 1
            ');
            $stmt->execute([
                ':collision_mask' => $mask,
                ':id_tile_definition' => $id_tile_definition
            ]);

            if ($stmt->rowCount() === 0)
            {
                // Unchanged mask still counts as success if the row exists.
                $check = $conn->prepare('
                    SELECT id_tile_definition
                    FROM tile_definitions
                    WHERE id_tile_definition = :id
                    LIMIT 1
                ');
                $check->execute([':id' => $id_tile_definition]);

                if (!$check->fetchColumn())
                {
                    return ['ok' => false, 'message' => 'Tile definition #' . $id_tile_definition . ' not found.'];
                }
            }

            return [
                'ok' => true,
                'message' => 'Collision mask saved for tile #' . $id_tile_definition . '.',
                'id_tile_definition' => $id_tile_definition,
                'collision_mask' => $mask
            ];
        }
        catch (PDOException $e)
        {
            error_log('[dev_world_tiles_content] ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    if ($action === 'save_tile_is_walkable')
    {
        $is_walkable = dev_world_tiles_post_str($post, 'is_walkable', 1);

        if ($is_walkable !== 'S' && $is_walkable !== 'N')
        {
            return ['ok' => false, 'message' => 'Invalid is_walkable value.'];
        }

        $ids = dev_world_tiles_post_definition_ids($post);

        if ($ids === null)
        {
            return ['ok' => false, 'message' => 'Invalid tile definition id list.'];
        }

        try
        {
            $placeholders = [];
            $params = [':is_walkable' => $is_walkable];

            foreach ($ids as $i => $id)
            {
                $key = ':id' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }

            $stmt = $conn->prepare('
                UPDATE tile_definitions
                SET is_walkable = :is_walkable
                WHERE id_tile_definition IN (' . implode(', ', $placeholders) . ')
            ');
            $stmt->execute($params);

            return [
                'ok' => true,
                'message' => 'is_walkable=' . $is_walkable . ' saved for ' . count($ids) . ' tile(s).',
                'id_tile_definitions' => $ids,
                'is_walkable' => $is_walkable
            ];
        }
        catch (PDOException $e)
        {
            error_log('[dev_world_tiles_content] ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    if ($action === 'save_tile_id_tile_layer')
    {
        $id_tile_layer = dev_world_tiles_post_int($post, 'id_tile_layer');
        $ids = dev_world_tiles_post_definition_ids($post);

        if ($ids === null)
        {
            return ['ok' => false, 'message' => 'Invalid tile definition id list.'];
        }

        if ($id_tile_layer < 0)
        {
            return ['ok' => false, 'message' => 'Invalid id_tile_layer.'];
        }

        $layer_value = ($id_tile_layer > 0) ? $id_tile_layer : null;

        if ($layer_value !== null)
        {
            try
            {
                $check = $conn->prepare('
                    SELECT id_tile_layer
                    FROM tile_layers
                    WHERE id_tile_layer = :id
                    LIMIT 1
                ');
                $check->execute([':id' => $layer_value]);

                if (!$check->fetchColumn())
                {
                    return ['ok' => false, 'message' => 'Tile layer #' . $layer_value . ' not found.'];
                }
            }
            catch (PDOException $e)
            {
                error_log('[dev_world_tiles_content] ' . $e->getMessage());

                return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
            }
        }

        try
        {
            $placeholders = [];
            $params = [':id_tile_layer' => $layer_value];

            foreach ($ids as $i => $id)
            {
                $key = ':id' . $i;
                $placeholders[] = $key;
                $params[$key] = $id;
            }

            $stmt = $conn->prepare('
                UPDATE tile_definitions
                SET id_tile_layer = :id_tile_layer
                WHERE id_tile_definition IN (' . implode(', ', $placeholders) . ')
            ');
            $stmt->execute($params);

            return [
                'ok' => true,
                'message' => 'id_tile_layer saved for ' . count($ids) . ' tile(s).',
                'id_tile_definitions' => $ids,
                'id_tile_layer' => $layer_value
            ];
        }
        catch (PDOException $e)
        {
            error_log('[dev_world_tiles_content] ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

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
 * Absolute path to public_html/client/img/tiles/.
 */
function dev_world_tiles_img_dir()
{
    return dirname(__DIR__) . '/public_html/client/img/tiles';
}

/**
 * Sanitize a tileset code/file prefix: lowercase snake, max 32 chars.
 */
function dev_world_tiles_sanitize_prefix($prefix)
{
    $prefix = strtolower(trim((string) $prefix));
    $prefix = preg_replace('/[^a-z0-9_]+/', '_', $prefix);
    $prefix = trim($prefix, '_');

    if ($prefix === '')
    {
        $prefix = 'tileset';
    }

    return mb_substr($prefix, 0, 32);
}

/**
 * Sanitize pack key for tile_definitions.pack (may be empty).
 */
function dev_world_tiles_sanitize_pack($pack)
{
    $pack = strtolower(trim((string) $pack));
    $pack = preg_replace('/[^a-z0-9_]+/', '_', $pack);
    $pack = trim($pack, '_');

    return mb_substr($pack, 0, 50);
}

/**
 * True when a cropped RGBA tile is effectively empty (fully transparent / blank).
 *
 * @param resource|\GdImage $tile
 */
function dev_world_tiles_tile_is_blank($tile, $w, $h)
{
    $opaque = 0;
    $samples = 0;
    $step = max(1, (int) floor(min($w, $h) / 16));

    for ($y = 0; $y < $h; $y += $step)
    {
        for ($x = 0; $x < $w; $x += $step)
        {
            $rgba = imagecolorat($tile, $x, $y);
            $alpha = ($rgba >> 24) & 0x7F;
            $samples++;

            // GD alpha: 0 = opaque, 127 = transparent
            if ($alpha < 120)
            {
                $opaque++;
            }
        }
    }

    if ($samples <= 0)
    {
        return true;
    }

    return ($opaque / $samples) < 0.02;
}

/**
 * Cut an uploaded PNG tileset into individual tiles, write them under
 * client/img/tiles/, and insert matching tile_definitions rows.
 *
 * @return array{ok:bool,message?:string}
 */
function dev_world_tiles_import_tileset(PDO $conn, array $post, array $files)
{
    if (!function_exists('imagecreatefrompng'))
    {
        return ['ok' => false, 'message' => 'PHP GD extension with PNG support is required for tileset import.'];
    }

    if (empty($files['tileset']['tmp_name']) || !is_uploaded_file($files['tileset']['tmp_name']))
    {
        return ['ok' => false, 'message' => 'Choose a PNG tileset file to upload.'];
    }

    if (!empty($files['tileset']['error']))
    {
        return ['ok' => false, 'message' => 'Upload failed (PHP error ' . (int) $files['tileset']['error'] . ').'];
    }

    $source_tile_px = dev_world_tiles_post_int($post, 'source_tile_px');
    $output_tile_px = dev_world_tiles_post_int($post, 'output_tile_px');
    $expect_w = dev_world_tiles_post_int($post, 'sheet_width_px');
    $expect_h = dev_world_tiles_post_int($post, 'sheet_height_px');
    $prefix = dev_world_tiles_sanitize_prefix(dev_world_tiles_post_str($post, 'code_prefix', 40));
    $pack = dev_world_tiles_sanitize_pack(dev_world_tiles_post_str($post, 'pack', 50));

    if ($pack === '')
    {
        $pack = $prefix;
    }

    $skip_blank = !empty($post['skip_blank']);
    $category = dev_world_tiles_post_str($post, 'category', 20) ?: 'base_pack';
    $id_zone = dev_world_tiles_post_int($post, 'id_zone_scope') ?: null;
    $id_tile_layer = dev_world_tiles_post_int($post, 'id_tile_layer') ?: null;
    $is_base_pack = dev_world_tiles_post_yn($post, 'is_base_pack');
    $is_walkable = dev_world_tiles_post_yn($post, 'is_walkable', 'S');
    $move_speed_mult = dev_world_tiles_post_decimal($post, 'move_speed_mult', 1.00);
    $sort_order_base = dev_world_tiles_post_int($post, 'sort_order');

    if ($source_tile_px < 8 || $source_tile_px > 1024)
    {
        return ['ok' => false, 'message' => 'source_tile_px must be between 8 and 1024 (cell size in the sheet).'];
    }

    if ($output_tile_px < 8 || $output_tile_px > 1024)
    {
        return ['ok' => false, 'message' => 'output_tile_px must be between 8 and 1024 (saved tile size).'];
    }

    $tmp = $files['tileset']['tmp_name'];
    $info = @getimagesize($tmp);

    if (!$info || (int) $info[2] !== IMAGETYPE_PNG)
    {
        return ['ok' => false, 'message' => 'Tileset must be a PNG image.'];
    }

    $sheet_w = (int) $info[0];
    $sheet_h = (int) $info[1];

    if ($expect_w > 0 && $expect_w !== $sheet_w)
    {
        return ['ok' => false, 'message' => 'Sheet width is ' . $sheet_w . 'px but expected ' . $expect_w . 'px.'];
    }

    if ($expect_h > 0 && $expect_h !== $sheet_h)
    {
        return ['ok' => false, 'message' => 'Sheet height is ' . $sheet_h . 'px but expected ' . $expect_h . 'px.'];
    }

    if ($sheet_w % $source_tile_px !== 0 || $sheet_h % $source_tile_px !== 0)
    {
        return [
            'ok' => false,
            'message' => 'Sheet ' . $sheet_w . 'x' . $sheet_h . ' is not divisible by source_tile_px=' . $source_tile_px . '.'
        ];
    }

    $cols = (int) ($sheet_w / $source_tile_px);
    $rows = (int) ($sheet_h / $source_tile_px);
    $total_cells = $cols * $rows;

    if ($total_cells <= 0 || $total_cells > 512)
    {
        return ['ok' => false, 'message' => 'Refusing import of ' . $total_cells . ' cells (max 512). Check tile size.'];
    }

    $src = @imagecreatefrompng($tmp);

    if (!$src)
    {
        return ['ok' => false, 'message' => 'Could not decode PNG (corrupt or unsupported).'];
    }

    imagealphablending($src, false);
    imagesavealpha($src, true);

    $out_dir = dev_world_tiles_img_dir();

    if (!is_dir($out_dir) || !is_writable($out_dir))
    {
        imagedestroy($src);

        return ['ok' => false, 'message' => 'Tile image directory is missing or not writable: client/img/tiles/'];
    }

    $insert = $conn->prepare('
        INSERT INTO tile_definitions
            (code, image_file, category, pack, id_zone, id_tile_layer, is_base_pack, is_walkable, move_speed_mult, collision_mask, sort_order)
        VALUES
            (:code, :image_file, :category, :pack, :id_zone, :id_tile_layer, :is_base_pack, :is_walkable, :move_speed_mult, NULL, :sort_order)
    ');

    $written_files = [];
    $created = 0;
    $skipped_blank = 0;
    $sort_i = 0;

    try
    {
        $conn->beginTransaction();

        for ($row = 0; $row < $rows; $row++)
        {
            for ($col = 0; $col < $cols; $col++)
            {
                $tile = imagecreatetruecolor($source_tile_px, $source_tile_px);
                imagealphablending($tile, false);
                imagesavealpha($tile, true);
                $transparent = imagecolorallocatealpha($tile, 0, 0, 0, 127);
                imagefilledrectangle($tile, 0, 0, $source_tile_px, $source_tile_px, $transparent);

                imagecopy(
                    $tile,
                    $src,
                    0,
                    0,
                    $col * $source_tile_px,
                    $row * $source_tile_px,
                    $source_tile_px,
                    $source_tile_px
                );

                if ($skip_blank && dev_world_tiles_tile_is_blank($tile, $source_tile_px, $source_tile_px))
                {
                    imagedestroy($tile);
                    $skipped_blank++;
                    continue;
                }

                if ($output_tile_px !== $source_tile_px)
                {
                    $resized = imagecreatetruecolor($output_tile_px, $output_tile_px);
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent2 = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $output_tile_px, $output_tile_px, $transparent2);
                    imagecopyresampled(
                        $resized,
                        $tile,
                        0,
                        0,
                        0,
                        0,
                        $output_tile_px,
                        $output_tile_px,
                        $source_tile_px,
                        $source_tile_px
                    );
                    imagedestroy($tile);
                    $tile = $resized;
                }

                $suffix = sprintf('%02d_%02d', $row, $col);
                $code = mb_substr($prefix . '_' . $suffix, 0, 50);
                $filename = $code . '.png';
                $path = $out_dir . DIRECTORY_SEPARATOR . $filename;

                if (is_file($path))
                {
                    imagedestroy($tile);
                    throw new RuntimeException('File already exists: ' . $filename);
                }

                if (!imagepng($tile, $path, 6))
                {
                    imagedestroy($tile);
                    throw new RuntimeException('Failed to write ' . $filename);
                }

                imagedestroy($tile);
                $written_files[] = $path;

                $insert->execute([
                    ':code' => $code,
                    ':image_file' => $filename,
                    ':category' => $category,
                    ':pack' => $pack,
                    ':id_zone' => $id_zone,
                    ':id_tile_layer' => $id_tile_layer,
                    ':is_base_pack' => $is_base_pack,
                    ':is_walkable' => $is_walkable,
                    ':move_speed_mult' => $move_speed_mult,
                    ':sort_order' => $sort_order_base + $sort_i
                ]);

                $created++;
                $sort_i++;
            }
        }

        $conn->commit();
    }
    catch (Exception $e)
    {
        if ($conn->inTransaction())
        {
            $conn->rollBack();
        }

        foreach ($written_files as $path)
        {
            if (is_file($path))
            {
                @unlink($path);
            }
        }

        imagedestroy($src);
        error_log('[dev_world_tiles_import] ' . $e->getMessage());

        return ['ok' => false, 'message' => 'Import failed: ' . $e->getMessage()];
    }

    imagedestroy($src);

    if ($created <= 0)
    {
        return ['ok' => false, 'message' => 'No tiles imported (all ' . $skipped_blank . ' cells were blank?).'];
    }

    $msg = 'Imported ' . $created . ' tile(s) from ' . $cols . 'x' . $rows
        . ' grid (' . $source_tile_px . '→' . $output_tile_px . 'px), prefix `' . $prefix
        . '`, pack `' . $pack . '`.';

    if ($skipped_blank > 0)
    {
        $msg .= ' Skipped ' . $skipped_blank . ' blank cell(s).';
    }

    return ['ok' => true, 'message' => $msg];
}

/**
 * @return array{ok:bool,message?:string}
 */
function dev_world_tiles_handle_post(PDO $conn, array $post, array $files = [])
{
    $action = dev_world_tiles_post_str($post, 'action', 50);

    try
    {
        switch ($action)
        {
            case 'import_tileset':
                return dev_world_tiles_import_tileset($conn, $post, $files);

            case 'add_tile_definition':
                $stmt = $conn->prepare('
                    INSERT INTO tile_definitions
                        (code, image_file, category, pack, id_zone, id_tile_layer, is_base_pack, is_walkable, move_speed_mult, collision_mask, sort_order)
                    VALUES
                        (:code, :image_file, :category, :pack, :id_zone, :id_tile_layer, :is_base_pack, :is_walkable, :move_speed_mult, :collision_mask, :sort_order)
                ');
                $stmt->execute([
                    ':code' => dev_world_tiles_post_str($post, 'code', 50),
                    ':image_file' => dev_world_tiles_post_str($post, 'image_file', 150),
                    ':category' => dev_world_tiles_post_str($post, 'category', 20) ?: 'base_pack',
                    ':pack' => dev_world_tiles_sanitize_pack(dev_world_tiles_post_str($post, 'pack', 50)),
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
                        pack = :pack,
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
                    ':pack' => dev_world_tiles_sanitize_pack(dev_world_tiles_post_str($post, 'pack', 50)),
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
