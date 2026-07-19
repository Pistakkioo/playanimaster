/**
 * Overworld tile & terrain system (sidequest): loads the DB-driven tile
 * catalog + sparse per-zone, per-layer placements, fills unassigned ground
 * cells with a deterministic pick from the zone's base pack, composes
 * terrain (walkable / move speed) across every layer plus large objects,
 * and renders it all for world.js.
 *
 * Layers: a fixed, server-seeded set (see tile_layers / 03_world_tiles_seed.sql).
 * Exactly one layer is the "ground" layer -- it always covers every cell
 * (explicit placement or deterministic base-pack fill). Other layers are
 * sparse/explicit-only (nothing placed = fully transparent/empty).
 * Non-canopy overlays draw above ground (ascending z_order) but behind
 * entities. The `canopy` layer is drawn in a second pass after avatars
 * (via drawCanopy) so foliage can sit over players. Terrain still
 * composes across every layer including canopy: a cell is blocked if the
 * ground OR any placed overlay/canopy (or a large object's bounding box)
 * says blocked, and move_speed_mult multiplies across every layer present
 * -- layers can only add restrictions, never remove the ground's.
 *
 * Objects: large multi-cell art (e.g. a house) with its own world-unit
 * footprint (width_world/height_world), rendered at that size regardless
 * of the source PNG's native pixel resolution -- same "canvas stretches to
 * destination size" approach as tiles. v1 collision is a simple bounding
 * rectangle (no per-object mask), and objects always draw behind entities
 * (no dynamic depth-sorting against the player yet).
 */
var AnimasterWorldTiles = (function ()
{
    var IMG_BASE_TILES = 'img/tiles/';
    var IMG_BASE_OBJECTS = 'img/objects/';
    var FALLBACK_COLOR = '#1a2e1a';
    var DEFAULT_TILE_WORLD_SIZE = 25;
    var DEFAULT_TILE_MASK_SIZE = 8;

    var tileWorldSize = DEFAULT_TILE_WORLD_SIZE;
    var tileMaskSize = DEFAULT_TILE_MASK_SIZE;
    var currentZoneId = null;
    var layers = [];
    var layersById = {};
    var groundLayerId = null;
    /** Overlays drawn under entities (everything except canopy). */
    var overlayLayers = [];
    /** Canopy overlays drawn above player / other_players avatars. */
    var canopyLayers = [];
    var defsById = {};
    var basePackDefs = [];
    var placementsByCellLayer = {};
    var objectDefsById = {};
    var objects = [];
    var imageCache = {};
    var loadPromise = null;

    function cellLayerKey(gx, gz, idLayer)
    {
        return gx + '_' + gz + '_' + idLayer;
    }

    function worldToGrid(worldX, worldZ)
    {
        return {
            gx: Math.floor(worldX / tileWorldSize),
            gz: Math.floor(worldZ / tileWorldSize)
        };
    }

    /**
     * Deterministic (stable across reloads) pseudo-random pick, FNV-1a hash
     * of "zone_gx_gz" modulo the base pack size.
     */
    function hashCell(idZone, gx, gz)
    {
        var str = idZone + '_' + gx + '_' + gz;
        var hash = 2166136261;

        for (var i = 0; i < str.length; i++)
        {
            hash ^= str.charCodeAt(i);
            hash = Math.imul(hash, 16777619);
        }

        return hash >>> 0;
    }

    function getImage(src)
    {
        var cached = imageCache[src];

        if (cached)
        {
            return cached;
        }

        var img = new Image();
        cached = { img: img, loaded: false };
        imageCache[src] = cached;

        img.onload = function ()
        {
            cached.loaded = true;
        };
        img.onerror = function ()
        {
            console.warn('[AnimasterWorldTiles] failed to load image:', src);
        };
        img.src = src;

        return cached;
    }

    function getTileImage(imageFile)
    {
        return getImage(IMG_BASE_TILES + imageFile);
    }

    function getObjectImage(imageFile)
    {
        return getImage(IMG_BASE_OBJECTS + imageFile);
    }

    /**
     * Resolves the tile definition occupying (gx, gz) on one layer. The
     * ground layer falls back to a deterministic base-pack pick when
     * nothing is explicitly placed; every other layer is sparse-only and
     * returns null.
     */
    function resolveTileDef(gx, gz, idLayer)
    {
        var key = cellLayerKey(gx, gz, idLayer);

        if (Object.prototype.hasOwnProperty.call(placementsByCellLayer, key))
        {
            return defsById[placementsByCellLayer[key]] || null;
        }

        if (idLayer !== groundLayerId || !basePackDefs.length)
        {
            return null;
        }

        var idx = hashCell(currentZoneId, gx, gz) % basePackDefs.length;

        return basePackDefs[idx];
    }

    function appendLayerDefs(stack, layerList, gx, gz)
    {
        layerList.forEach(function (layer)
        {
            var def = resolveTileDef(gx, gz, layer.id_tile_layer);

            if (def)
            {
                stack.push(def);
            }
        });
    }

    /**
     * Every tile definition present at (gx, gz) across all layers, ground
     * first then overlays then canopy in ascending z_order -- used by
     * terrain resolution (walkability / speed).
     */
    function resolveStack(gx, gz)
    {
        var stack = [];
        var groundDef = groundLayerId !== null ? resolveTileDef(gx, gz, groundLayerId) : null;

        if (groundDef)
        {
            stack.push(groundDef);
        }

        appendLayerDefs(stack, overlayLayers, gx, gz);
        appendLayerDefs(stack, canopyLayers, gx, gz);

        return stack;
    }

    /**
     * Draw stack under entities: ground + non-canopy overlays only.
     */
    function resolveUnderEntityStack(gx, gz)
    {
        var stack = [];
        var groundDef = groundLayerId !== null ? resolveTileDef(gx, gz, groundLayerId) : null;

        if (groundDef)
        {
            stack.push(groundDef);
        }

        appendLayerDefs(stack, overlayLayers, gx, gz);

        return stack;
    }

    function isCanopyLayer(layer)
    {
        return !!(layer && String(layer.code || '').toLowerCase() === 'canopy');
    }

    function computeObjectRect(placement, def)
    {
        var widthWorld = parseFloat(def.width_world) || tileWorldSize;
        var heightWorld = parseFloat(def.height_world) || tileWorldSize;
        var anchorX = parseFloat(def.anchor_x);
        var anchorY = parseFloat(def.anchor_y);

        anchorX = isNaN(anchorX) ? 0.5 : anchorX;
        anchorY = isNaN(anchorY) ? 1 : anchorY;

        var anchorWorldX = placement.grid_x * tileWorldSize + tileWorldSize * 0.5;
        var anchorWorldZ = (placement.grid_z + 1) * tileWorldSize;
        var x0 = anchorWorldX - anchorX * widthWorld;
        var z0 = anchorWorldZ - anchorY * heightWorld;

        return { x0: x0, z0: z0, x1: x0 + widthWorld, z1: z0 + heightWorld, width: widthWorld, height: heightWorld };
    }

    function isInsideBlockedObject(worldX, worldZ)
    {
        for (var i = 0; i < objects.length; i++)
        {
            var obj = objects[i];

            if (!obj.def || obj.def.is_walkable !== 'N')
            {
                continue;
            }

            if (worldX >= obj.rect.x0 && worldX < obj.rect.x1 && worldZ >= obj.rect.z0 && worldZ < obj.rect.z1)
            {
                return true;
            }
        }

        return false;
    }

    function loadZone(idZone)
    {
        idZone = parseInt(idZone, 10) || 0;

        if (idZone <= 0)
        {
            return Promise.resolve();
        }

        if (currentZoneId === idZone && loadPromise)
        {
            return loadPromise;
        }

        loadPromise = AnimasterApi.fetchWorldTiles(idZone).then(function (result)
        {
            currentZoneId = idZone;
            tileWorldSize = (result && result.tile_world_size) || DEFAULT_TILE_WORLD_SIZE;
            tileMaskSize = (result && result.tile_mask_size) || DEFAULT_TILE_MASK_SIZE;

            layers = (result && result.tile_layers ? result.tile_layers : []).slice().sort(function (a, b)
            {
                return (parseInt(a.z_order, 10) || 0) - (parseInt(b.z_order, 10) || 0);
            });
            layersById = {};
            groundLayerId = null;
            overlayLayers = [];
            canopyLayers = [];

            layers.forEach(function (layer)
            {
                layer.id_tile_layer = parseInt(layer.id_tile_layer, 10);
                layersById[layer.id_tile_layer] = layer;

                if (layer.is_ground === 'S' && groundLayerId === null)
                {
                    groundLayerId = layer.id_tile_layer;
                }
                else if (isCanopyLayer(layer))
                {
                    canopyLayers.push(layer);
                }
                else
                {
                    overlayLayers.push(layer);
                }
            });

            defsById = {};
            basePackDefs = [];

            (result && result.tile_definitions ? result.tile_definitions : []).forEach(function (def)
            {
                defsById[def.id_tile_definition] = def;

                if (def.is_base_pack === 'S')
                {
                    basePackDefs.push(def);
                }

                getTileImage(def.image_file);
            });

            placementsByCellLayer = {};

            (result && result.placements ? result.placements : []).forEach(function (placement)
            {
                placementsByCellLayer[cellLayerKey(placement.grid_x, placement.grid_z, parseInt(placement.id_tile_layer, 10))] = placement.id_tile_definition;
            });

            objectDefsById = {};

            (result && result.object_definitions ? result.object_definitions : []).forEach(function (def)
            {
                objectDefsById[def.id_object_definition] = def;
                getObjectImage(def.image_file);
            });

            objects = (result && result.objects ? result.objects : []).map(function (placement)
            {
                var def = objectDefsById[placement.id_object_definition] || null;

                return {
                    placement: placement,
                    def: def,
                    rect: def ? computeObjectRect(placement, def) : null
                };
            }).filter(function (obj)
            {
                return !!obj.def;
            });
        }).catch(function (err)
        {
            console.warn('[AnimasterWorldTiles] loadZone failed:', err && err.message ? err.message : err);
        });

        return loadPromise;
    }

    /**
     * Sub-tile walkability: when a tile definition has a collision_mask
     * (row-major ANIMASTER_TILE_MASK_SIZE^2 grid of '1'=walkable/'0'=blocked),
     * only the sub-cell under (worldX, worldZ) is checked -- this lets a
     * single tile mix walkable ground with a small barrier shape (e.g. a
     * round ruin structure in the middle of an otherwise-walkable tile).
     * Falls back to the whole-tile is_walkable flag when no mask is set.
     */
    function maskWalkableAt(def, worldX, worldZ, gx, gz)
    {
        var mask = def.collision_mask;

        if (!mask || mask.length !== tileMaskSize * tileMaskSize)
        {
            return def.is_walkable !== 'N';
        }

        var fracX = (worldX - gx * tileWorldSize) / tileWorldSize;
        var fracZ = (worldZ - gz * tileWorldSize) / tileWorldSize;
        var sx = Math.min(tileMaskSize - 1, Math.max(0, Math.floor(fracX * tileMaskSize)));
        var sz = Math.min(tileMaskSize - 1, Math.max(0, Math.floor(fracZ * tileMaskSize)));

        return mask.charAt(sz * tileMaskSize + sx) !== '0';
    }

    /**
     * Composes terrain across every layer present at (worldX, worldZ) plus
     * large objects: blocked = ground OR any overlay/object blocked
     * (additive-only restrictions); moveSpeedMult = product of every
     * layer's move_speed_mult present at that cell.
     */
    function getTerrainAt(worldX, worldZ)
    {
        var cell = worldToGrid(worldX, worldZ);
        var stack = resolveStack(cell.gx, cell.gz);

        if (!stack.length)
        {
            return { isWalkable: !isInsideBlockedObject(worldX, worldZ), moveSpeedMult: 1 };
        }

        var blocked = isInsideBlockedObject(worldX, worldZ);
        var moveSpeedMult = 1;

        stack.forEach(function (def)
        {
            if (!maskWalkableAt(def, worldX, worldZ, cell.gx, cell.gz))
            {
                blocked = true;
            }

            moveSpeedMult *= parseFloat(def.move_speed_mult) || 1;
        });

        return { isWalkable: !blocked, moveSpeedMult: moveSpeedMult };
    }

    function isWalkable(worldX, worldZ)
    {
        return getTerrainAt(worldX, worldZ).isWalkable;
    }

    function getSpeedMultiplierAt(worldX, worldZ)
    {
        return getTerrainAt(worldX, worldZ).moveSpeedMult;
    }

    function drawObjects(ctx, worldToScreen, scale, visibleRect)
    {
        objects.forEach(function (obj)
        {
            var rect = obj.rect;

            if (rect.x1 < visibleRect.x0 || rect.x0 > visibleRect.x1 || rect.z1 < visibleRect.z0 || rect.z0 > visibleRect.z1)
            {
                return;
            }

            var screen = worldToScreen(rect.x0, rect.z0);
            var pxW = rect.width * scale;
            var pxH = rect.height * scale;
            var cached = getObjectImage(obj.def.image_file);

            if (cached && cached.loaded)
            {
                ctx.drawImage(cached.img, screen.x, screen.y, pxW, pxH);
            }
        });
    }

    function getViewportGrid(player, canvas, worldToScreen)
    {
        var origin = worldToScreen(0, 0);
        var unit = worldToScreen(1, 0);
        var scale = unit.x - origin.x;

        if (!scale)
        {
            return null;
        }

        var halfWWorld = (canvas.width * 0.5) / scale;
        var halfHWorld = (canvas.height * 0.5) / scale;

        return {
            scale: scale,
            halfWWorld: halfWWorld,
            halfHWorld: halfHWorld,
            startGx: Math.floor((player.x - halfWWorld) / tileWorldSize) - 1,
            endGx: Math.ceil((player.x + halfWWorld) / tileWorldSize) + 1,
            startGz: Math.floor((player.z - halfHWorld) / tileWorldSize) - 1,
            endGz: Math.ceil((player.z + halfHWorld) / tileWorldSize) + 1
        };
    }

    function drawTileStackAt(ctx, worldToScreen, gx, gz, stack, fillFallback)
    {
        // Integer pixel bounds from both cell corners. floor/ceil with a
        // 1px overdraw kills subpixel hairline gaps that otherwise show
        // FALLBACK_COLOR as fake "tile borders" between cells.
        var screen0 = worldToScreen(gx * tileWorldSize, gz * tileWorldSize);
        var screen1 = worldToScreen((gx + 1) * tileWorldSize, (gz + 1) * tileWorldSize);
        var dx = Math.floor(screen0.x);
        var dy = Math.floor(screen0.y);
        var dw = Math.max(1, Math.ceil(screen1.x) - dx + 1);
        var dh = Math.max(1, Math.ceil(screen1.y) - dy + 1);

        if (!stack.length)
        {
            if (fillFallback)
            {
                ctx.fillStyle = FALLBACK_COLOR;
                ctx.fillRect(dx, dy, dw, dh);
            }

            return;
        }

        var drewAny = false;

        stack.forEach(function (def)
        {
            var cached = getTileImage(def.image_file);

            if (cached && cached.loaded)
            {
                ctx.drawImage(cached.img, dx, dy, dw, dh);
                drewAny = true;
            }
        });

        if (!drewAny && fillFallback)
        {
            ctx.fillStyle = FALLBACK_COLOR;
            ctx.fillRect(dx, dy, dw, dh);
        }
    }

    function draw(ctx, player, canvas, worldToScreen)
    {
        if (!ctx || !player || !canvas || typeof worldToScreen !== 'function')
        {
            return;
        }

        var viewport = getViewportGrid(player, canvas, worldToScreen);

        if (!viewport)
        {
            return;
        }

        for (var gz = viewport.startGz; gz <= viewport.endGz; gz++)
        {
            for (var gx = viewport.startGx; gx <= viewport.endGx; gx++)
            {
                drawTileStackAt(ctx, worldToScreen, gx, gz, resolveUnderEntityStack(gx, gz), true);
            }
        }

        drawObjects(ctx, worldToScreen, viewport.scale, {
            x0: player.x - viewport.halfWWorld,
            x1: player.x + viewport.halfWWorld,
            z0: player.z - viewport.halfHWorld,
            z1: player.z + viewport.halfHWorld
        });
    }

    /**
     * Second pass: canopy tiles over entities (player / other players / NPCs).
     */
    function drawCanopy(ctx, player, canvas, worldToScreen)
    {
        if (!ctx || !player || !canvas || typeof worldToScreen !== 'function' || !canopyLayers.length)
        {
            return;
        }

        var viewport = getViewportGrid(player, canvas, worldToScreen);

        if (!viewport)
        {
            return;
        }

        for (var gz = viewport.startGz; gz <= viewport.endGz; gz++)
        {
            for (var gx = viewport.startGx; gx <= viewport.endGx; gx++)
            {
                var stack = [];
                appendLayerDefs(stack, canopyLayers, gx, gz);

                if (stack.length)
                {
                    drawTileStackAt(ctx, worldToScreen, gx, gz, stack, false);
                }
            }
        }
    }

    return {
        loadZone: loadZone,
        getTerrainAt: getTerrainAt,
        isWalkable: isWalkable,
        getSpeedMultiplierAt: getSpeedMultiplierAt,
        draw: draw,
        drawCanopy: drawCanopy
    };
})();
