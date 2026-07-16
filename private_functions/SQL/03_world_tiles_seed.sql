-- One-off seed data for the overworld tile & terrain system (sidequest).
-- Idempotent (ON DUPLICATE KEY UPDATE) so it can be re-run safely.
-- Requires tile_layers / tile_definitions / world_tiles / object_definitions
-- / world_objects from 01_alters_structure.sql.
--
-- Placeholder art lives in public_html/client/img/tiles/*.png and
-- public_html/client/img/objects/*.png. Swap in real art later by updating
-- `image_file` here (or via the dev_world_tiles.php admin tool) -- no code
-- changes needed.

-- Fixed layer set (SQL-seeded only, no admin CRUD). z_order is the
-- render/paint order, lowest first (below). Exactly one row has
-- is_ground='S': it is the only layer that gets deterministic base-pack
-- fill of unassigned cells; every other layer is sparse/explicit-only.
INSERT INTO `playanimaster_db`.`tile_layers`
    (`code`, `name`, `z_order`, `is_ground`, `sort_order`)
VALUES
    ('ground', 'Ground', 0, 'S', 10),
    ('ground_detail', 'Ground Detail', 10, 'N', 20),
    ('props', 'Props', 20, 'N', 30),
    ('structures', 'Structures', 30, 'N', 40),
    ('canopy', 'Canopy', 40, 'N', 50)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `z_order` = VALUES(`z_order`),
    `is_ground` = VALUES(`is_ground`),
    `sort_order` = VALUES(`sort_order`);

-- Base pack: eligible for deterministic random fill of any unassigned
-- ground-layer cell (id_zone = NULL -> usable in every zone).
INSERT INTO `playanimaster_db`.`tile_definitions`
    (`code`, `image_file`, `category`, `id_zone`, `id_tile_layer`, `is_base_pack`, `is_walkable`, `move_speed_mult`, `sort_order`)
VALUES
    ('grass_1', 'grass_1.png', 'base_pack', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), 'S', 'S', 1.00, 10),
    ('grass_2', 'grass_2.png', 'base_pack', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), 'S', 'S', 1.00, 20),
    ('dirt_path', 'dirt_path.png', 'base_pack', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), 'S', 'S', 1.00, 30)
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `category` = VALUES(`category`),
    `id_zone` = VALUES(`id_zone`),
    `id_tile_layer` = VALUES(`id_tile_layer`),
    `is_base_pack` = VALUES(`is_base_pack`),
    `is_walkable` = VALUES(`is_walkable`),
    `move_speed_mult` = VALUES(`move_speed_mult`),
    `sort_order` = VALUES(`sort_order`);

-- Landmark (ground layer): walkable overall (is_walkable='S'), but mixed
-- terrain via collision_mask -- only the central round ruin structure
-- blocks movement, the surrounding grass corners on the same tile stay
-- walkable. 8x8 grid, row-major, 1=walkable/0=blocked; built with CONCAT()
-- instead of a hand-typed 64-char literal to keep the shape easy to verify.
INSERT INTO `playanimaster_db`.`tile_definitions`
    (`code`, `image_file`, `category`, `id_zone`, `id_tile_layer`, `is_base_pack`, `is_walkable`, `move_speed_mult`, `collision_mask`, `sort_order`)
VALUES
    (
        'ruin_alcove', 'ruin_alcove.png', 'landmark', NULL,
        (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'),
        'N', 'S', 1.00,
        CONCAT(
            '11111111',
            '11000011',
            '10000001',
            '10000001',
            '10000001',
            '10000001',
            '11000011',
            '11111111'
        ),
        100
    )
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `category` = VALUES(`category`),
    `id_zone` = VALUES(`id_zone`),
    `id_tile_layer` = VALUES(`id_tile_layer`),
    `is_base_pack` = VALUES(`is_base_pack`),
    `is_walkable` = VALUES(`is_walkable`),
    `move_speed_mult` = VALUES(`move_speed_mult`),
    `collision_mask` = VALUES(`collision_mask`),
    `sort_order` = VALUES(`sort_order`);

-- Difficult terrain (ground layer): walkable but slows movement to half speed.
INSERT INTO `playanimaster_db`.`tile_definitions`
    (`code`, `image_file`, `category`, `id_zone`, `id_tile_layer`, `is_base_pack`, `is_walkable`, `move_speed_mult`, `sort_order`)
VALUES
    ('swamp_mud', 'swamp_mud.png', 'terrain', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), 'N', 'S', 0.50, 110)
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `category` = VALUES(`category`),
    `id_zone` = VALUES(`id_zone`),
    `id_tile_layer` = VALUES(`id_tile_layer`),
    `is_base_pack` = VALUES(`is_base_pack`),
    `is_walkable` = VALUES(`is_walkable`),
    `move_speed_mult` = VALUES(`move_speed_mult`),
    `sort_order` = VALUES(`sort_order`);

-- Barrier (ground layer): blocks movement entirely.
INSERT INTO `playanimaster_db`.`tile_definitions`
    (`code`, `image_file`, `category`, `id_zone`, `id_tile_layer`, `is_base_pack`, `is_walkable`, `move_speed_mult`, `sort_order`)
VALUES
    ('rock_wall', 'rock_wall.png', 'terrain', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), 'N', 'N', 1.00, 120)
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `category` = VALUES(`category`),
    `id_zone` = VALUES(`id_zone`),
    `id_tile_layer` = VALUES(`id_tile_layer`),
    `is_base_pack` = VALUES(`is_base_pack`),
    `is_walkable` = VALUES(`is_walkable`),
    `move_speed_mult` = VALUES(`move_speed_mult`),
    `sort_order` = VALUES(`sort_order`);

-- Overlay demo (props layer): a fallen log, walkable ground underneath it,
-- but the log itself is a barrier -- proves cumulative OR-blocking across
-- layers (ground stays walkable, only the props-layer cell blocks). Placed
-- as a whole-tile barrier (is_walkable='N'); could also use a
-- collision_mask if only part of the log's tile should block.
INSERT INTO `playanimaster_db`.`tile_definitions`
    (`code`, `image_file`, `category`, `id_zone`, `id_tile_layer`, `is_base_pack`, `is_walkable`, `move_speed_mult`, `sort_order`)
VALUES
    ('tree_log', 'tree_log.png', 'terrain', NULL, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'props'), 'N', 'N', 1.00, 200)
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `category` = VALUES(`category`),
    `id_zone` = VALUES(`id_zone`),
    `id_tile_layer` = VALUES(`id_tile_layer`),
    `is_base_pack` = VALUES(`is_base_pack`),
    `is_walkable` = VALUES(`is_walkable`),
    `move_speed_mult` = VALUES(`move_speed_mult`),
    `sort_order` = VALUES(`sort_order`);

-- Example placements in zone 1000 (default spawn zone), a few tiles from
-- the (0,0) spawn point (TILE_WORLD_SIZE = 25 world units per cell):
--   ruin_alcove  (ground)  at grid (2, 2)                    -- world (50-75, 50-75)
--   swamp_mud    (ground)  at grid (4, 0), (4, 1), (4, 2)     -- a slow patch to the east
--   rock_wall    (ground)  at grid (-3, 0), (-3, 1), (-3, 2)  -- a wall to the west
--   tree_log     (props)   at grid (2, -2)                    -- log on top of walkable grass
INSERT INTO `playanimaster_db`.`world_tiles`
    (`id_zone`, `grid_x`, `grid_z`, `id_tile_layer`, `id_tile_definition`)
VALUES
    (1000, 2, 2, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'ruin_alcove')),
    (1000, 4, 0, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'swamp_mud')),
    (1000, 4, 1, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'swamp_mud')),
    (1000, 4, 2, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'swamp_mud')),
    (1000, -3, 0, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'rock_wall')),
    (1000, -3, 1, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'rock_wall')),
    (1000, -3, 2, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'ground'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'rock_wall')),
    (1000, 2, -2, (SELECT id_tile_layer FROM `playanimaster_db`.`tile_layers` WHERE `code` = 'props'), (SELECT id_tile_definition FROM `playanimaster_db`.`tile_definitions` WHERE `code` = 'tree_log'))
ON DUPLICATE KEY UPDATE
    `id_tile_definition` = VALUES(`id_tile_definition`);

-- Large object demo: a house, sized in world units independent of its
-- source PNG resolution (rendered the same way tiles are -- canvas
-- stretches to the destination size). 100x150 world units = 4x6 grid cells
-- at TILE_WORLD_SIZE=25, matching the placeholder art's ~2:3 aspect ratio.
-- anchor_x/anchor_y default to bottom-center (0.5/1.0), so it "stands" on
-- its origin cell.
INSERT INTO `playanimaster_db`.`object_definitions`
    (`code`, `image_file`, `width_world`, `height_world`, `anchor_x`, `anchor_y`, `is_walkable`, `sort_order`)
VALUES
    ('house_1', 'house_1.png', 100.00, 150.00, 0.50, 1.00, 'N', 10)
ON DUPLICATE KEY UPDATE
    `image_file` = VALUES(`image_file`),
    `width_world` = VALUES(`width_world`),
    `height_world` = VALUES(`height_world`),
    `anchor_x` = VALUES(`anchor_x`),
    `anchor_y` = VALUES(`anchor_y`),
    `is_walkable` = VALUES(`is_walkable`),
    `sort_order` = VALUES(`sort_order`);

-- Placed a few cells south of spawn so it's easy to find while testing.
INSERT INTO `playanimaster_db`.`world_objects`
    (`id_zone`, `grid_x`, `grid_z`, `id_object_definition`)
SELECT 1000, -2, 5, id_object_definition
FROM `playanimaster_db`.`object_definitions`
WHERE `code` = 'house_1'
AND NOT EXISTS (
    SELECT 1 FROM `playanimaster_db`.`world_objects`
    WHERE `id_zone` = 1000 AND `grid_x` = -2 AND `grid_z` = 5
);
