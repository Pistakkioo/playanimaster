/**
 * dev_world_tiles.php canvas editor: multi-layer tile painting with a local
 * staged before/after buffer (paint locally, preview/revert, explicit Save).
 * Standalone vanilla JS, does not share state with the live game
 * (AnimasterWorld / AnimasterWorldTiles) -- shows raw world_tiles rows only,
 * no deterministic base-pack fill, so the admin always sees exactly what is
 * stored in the DB for this zone.
 */
(function ()
{
    var CELL_PX = 40;
    var EMPTY_COLOR = '#111a26';
    var GRID_LINE_COLOR = 'rgba(255,255,255,0.08)';
    var CURSOR_COLOR = 'rgba(77,171,247,0.35)';

    var bootstrap = window.DEV_WORLD_TILES_BOOTSTRAP || {};
    var idZone = parseInt(bootstrap.idZone, 10) || 0;
    var token = bootstrap.token || '';
    var imgBase = bootstrap.imgBase || 'client/img/tiles/';
    var tileLayers = bootstrap.tileLayers || [];
    var groundLayerId = parseInt(bootstrap.groundLayerId, 10) || 0;
    var definitionsById = {};
    var layerVisible = {};
    var activeLayerId = groundLayerId;
    var imageCache = {};
    /** @type {Array<number|'ERASER'>} selected brush tile ids (ERASER is alone) */
    var selectedBrushIds = [];
    /** @type {Array<{id:number,dx:number,dy:number,layer?:number}>} stamp cells relative to click */
    var brushStamp = [];
    var brushIsEraser = false;
    /** When true, stamp came from the thermometer (map sample) — do not rebuild from palette grid. */
    var brushStampFromMap = false;
    var lastPaletteBrushId = null;
    var selectedPackFilter = '*';
    var hoverCell = null;
    var painting = false;
    var previewingBefore = false;
    var thermometerActive = false;
    var thermoDragging = false;
    /** @type {{gx:number,gz:number}|null} */
    var thermoDragStart = null;
    /** @type {{gx:number,gz:number}|null} */
    var thermoDragEnd = null;
    var THERMO_SELECT_COLOR = 'rgba(255,176,32,0.40)';
    /** @type {{startIdx:number,additive:boolean,moved:boolean,baseSelection:number[]}|null} */
    var paletteDrag = null;
    var suppressPaletteClick = false;
    var PALETTE_POS_KEY = 'dwt_palette_float_pos_v1';
    var PALETTE_DENSITY_KEY = 'dwt_palette_density_v1';
    var MAX_BRUSH_CELLS = 64;

    var placementsBefore = {};
    var placementsWorking = {};
    var dirtyKeys = {};
    var MAX_UNDO = 50;
    /** @type {Array<Array<{key:string,prev:number|null}>>} */
    var undoStack = [];
    /** @type {{seen:Object<string,true>,entries:Array<{key:string,prev:number|null}>}|null} */
    var undoStroke = null;

    var canvas = document.getElementById('dwt-canvas');
    var ctx = canvas ? canvas.getContext('2d') : null;
    // Grid fills the canvas bitmap exactly (no leftover empty strip).
    var COLS = canvas ? Math.floor(canvas.width / CELL_PX) : 24;
    var ROWS = canvas ? Math.floor(canvas.height / CELL_PX) : 18;
    var originGx = -Math.floor(COLS / 2);
    var originGz = -Math.floor(ROWS / 2);
    var statusEl = document.getElementById('dwt-status');
    var coordsEl = document.getElementById('dwt-coords');
    var brushLabelEl = document.getElementById('dwt-brush-label');
    var dirtyCountEl = document.getElementById('dwt-dirty-count');
    var saveBtn = document.getElementById('dwt-save-btn');
    var revertBtn = document.getElementById('dwt-revert-btn');
    var revertLastBtn = document.getElementById('dwt-revert-last-btn');
    var previewToggleBtn = document.getElementById('dwt-preview-toggle');

    (bootstrap.definitions || []).forEach(function (def)
    {
        definitionsById[def.id_tile_definition] = def;
    });

    function cellLayerKey(gx, gz, idLayer)
    {
        return gx + '_' + gz + '_' + idLayer;
    }

    function clonePlacements(src)
    {
        var out = {};

        Object.keys(src).forEach(function (key)
        {
            out[key] = src[key];
        });

        return out;
    }

    (function seedPlacements()
    {
        (bootstrap.placements || []).forEach(function (p)
        {
            placementsBefore[cellLayerKey(p.grid_x, p.grid_z, parseInt(p.id_tile_layer, 10))] = parseInt(p.id_tile_definition, 10);
        });

        placementsWorking = clonePlacements(placementsBefore);
    })();

    function getImage(imageFile)
    {
        var cached = imageCache[imageFile];

        if (cached)
        {
            return cached;
        }

        var img = new Image();
        cached = { img: img, loaded: false };
        imageCache[imageFile] = cached;
        img.onload = function ()
        {
            cached.loaded = true;
            render();
        };
        img.src = imgBase + imageFile;

        return cached;
    }

    function setStatus(message, isError)
    {
        if (!statusEl)
        {
            return;
        }

        statusEl.textContent = message;
        statusEl.className = isError ? 'text-danger' : 'text-success';
    }

    function isLayerVisible(idLayer)
    {
        return idLayer === activeLayerId || layerVisible[idLayer] !== false;
    }

    function updateDirtyCount()
    {
        var count = Object.keys(dirtyKeys).length;

        if (dirtyCountEl)
        {
            dirtyCountEl.textContent = String(count);
        }

        if (saveBtn)
        {
            saveBtn.disabled = count === 0;
        }

        updateUndoButton();
    }

    function updateUndoButton()
    {
        if (!revertLastBtn)
        {
            return;
        }

        var n = undoStack.length;
        revertLastBtn.disabled = n === 0;
        revertLastBtn.textContent = n > 0 ? ('Revert last (' + n + ')') : 'Revert last';
        revertLastBtn.title = n > 0
            ? ('Undo the last paint stroke · ' + n + '/' + MAX_UNDO + ' in history')
            : 'Undo the last paint stroke (up to ' + MAX_UNDO + ')';
    }

    function beginUndoStroke()
    {
        undoStroke = { seen: {}, entries: [] };
    }

    function recordUndoBefore(key)
    {
        if (!undoStroke || undoStroke.seen[key])
        {
            return;
        }

        undoStroke.seen[key] = true;
        undoStroke.entries.push({
            key: key,
            prev: Object.prototype.hasOwnProperty.call(placementsWorking, key)
                ? placementsWorking[key]
                : null
        });
    }

    function commitUndoStroke()
    {
        if (!undoStroke)
        {
            return;
        }

        if (undoStroke.entries.length)
        {
            undoStack.push(undoStroke.entries);

            while (undoStack.length > MAX_UNDO)
            {
                undoStack.shift();
            }
        }

        undoStroke = null;
        updateUndoButton();
    }

    function clearUndoStack()
    {
        undoStack = [];
        undoStroke = null;
        updateUndoButton();
    }

    function syncDirtyKey(key)
    {
        var beforeVal = placementsBefore[key] || null;
        var workingVal = Object.prototype.hasOwnProperty.call(placementsWorking, key)
            ? placementsWorking[key]
            : null;

        if (beforeVal === workingVal)
        {
            delete dirtyKeys[key];
        }
        else
        {
            dirtyKeys[key] = true;
        }
    }

    function revertLastChange()
    {
        if (!undoStack.length)
        {
            setStatus('Nothing to revert.', true);
            return;
        }

        var entries = undoStack.pop();
        var i;

        for (i = 0; i < entries.length; i++)
        {
            var key = entries[i].key;
            var prev = entries[i].prev;

            if (prev === null || prev === undefined)
            {
                delete placementsWorking[key];
            }
            else
            {
                placementsWorking[key] = prev;
            }

            syncDirtyKey(key);
        }

        updateDirtyCount();
        setStatus('Reverted last change (' + entries.length + ' cell(s) · ' + undoStack.length + ' left).', false);
        render();
    }

    function render()
    {
        if (!ctx)
        {
            return;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var source = previewingBefore ? placementsBefore : placementsWorking;

        for (var row = 0; row < ROWS; row++)
        {
            for (var col = 0; col < COLS; col++)
            {
                var gx = originGx + col;
                var gz = originGz + row;
                var px = col * CELL_PX;
                var py = row * CELL_PX;
                var drewAny = false;
                var hasBarrier = false;
                var cellDirty = false;

                tileLayers.forEach(function (layer)
                {
                    var idLayer = parseInt(layer.id_tile_layer, 10);

                    if (!isLayerVisible(idLayer))
                    {
                        return;
                    }

                    var key = cellLayerKey(gx, gz, idLayer);

                    if (dirtyKeys[key])
                    {
                        cellDirty = true;
                    }

                    var idDef = source[key];
                    var def = idDef ? definitionsById[idDef] : null;

                    if (!def)
                    {
                        return;
                    }

                    var cached = getImage(def.image_file);

                    if (cached.loaded)
                    {
                        ctx.drawImage(cached.img, px, py, CELL_PX, CELL_PX);
                        drewAny = true;
                    }

                    if (def.is_walkable === 'N')
                    {
                        hasBarrier = true;
                    }
                });

                if (!drewAny)
                {
                    ctx.fillStyle = EMPTY_COLOR;
                    ctx.fillRect(px, py, CELL_PX, CELL_PX);
                }

                if (hasBarrier)
                {
                    ctx.strokeStyle = 'rgba(255,80,80,0.9)';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(px + 1, py + 1, CELL_PX - 2, CELL_PX - 2);
                }

                ctx.strokeStyle = GRID_LINE_COLOR;
                ctx.lineWidth = 1;
                ctx.strokeRect(px + 0.5, py + 0.5, CELL_PX - 1, CELL_PX - 1);

                if (gx === 0 && gz === 0)
                {
                    ctx.strokeStyle = 'rgba(255,214,10,0.9)';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(px + 1, py + 1, CELL_PX - 2, CELL_PX - 2);
                }

                if (cellDirty)
                {
                    ctx.save();
                    ctx.setLineDash([4, 3]);
                    ctx.strokeStyle = 'rgba(255,193,7,0.95)';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(px + 2, py + 2, CELL_PX - 4, CELL_PX - 4);
                    ctx.restore();
                }

                if (isThermoCellSelected(gx, gz))
                {
                    ctx.fillStyle = THERMO_SELECT_COLOR;
                    ctx.fillRect(px, py, CELL_PX, CELL_PX);
                }

                if (hoverCell && hoverCell.gx === gx && hoverCell.gz === gz)
                {
                    ctx.fillStyle = CURSOR_COLOR;
                    ctx.fillRect(px, py, CELL_PX, CELL_PX);
                }
            }
        }
    }

    function canvasPosToCell(clientX, clientY)
    {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        var x = (clientX - rect.left) * scaleX;
        var y = (clientY - rect.top) * scaleY;

        return {
            gx: originGx + Math.floor(x / CELL_PX),
            gz: originGz + Math.floor(y / CELL_PX)
        };
    }

    function postAction(fields)
    {
        var body = new URLSearchParams();
        body.append('T', token);
        Object.keys(fields).forEach(function (key)
        {
            body.append(key, String(fields[key]));
        });

        return fetch('dev_world_tiles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (res)
        {
            return res.json();
        });
    }

    function applyPaintAt(gx, gz, brushId, idLayer)
    {
        var layerId = (idLayer === undefined || idLayer === null || idLayer === '')
            ? activeLayerId
            : (parseInt(idLayer, 10) || activeLayerId);
        var key = cellLayerKey(gx, gz, layerId);

        recordUndoBefore(key);

        if (brushId === 'ERASER')
        {
            delete placementsWorking[key];
        }
        else
        {
            placementsWorking[key] = brushId;
        }

        syncDirtyKey(key);
    }

    /**
     * Paints locally only (no network call) -- edits live in
     * placementsWorking until the Save button batches the diff.
     * Multi-tile brushes stamp relative offsets from (gx, gz).
     * Stamp entries may carry a layer id (thermometer multi-layer samples).
     */
    function paintCell(gx, gz)
    {
        if (previewingBefore || thermometerActive)
        {
            return;
        }

        if (brushIsEraser)
        {
            applyPaintAt(gx, gz, 'ERASER');
            updateDirtyCount();
            render();
            return;
        }

        if (!brushStamp.length)
        {
            setStatus('Pick a tile (or Eraser) from the palette first.', true);
            return;
        }

        for (var i = 0; i < brushStamp.length; i++)
        {
            applyPaintAt(
                gx + brushStamp[i].dx,
                gz + brushStamp[i].dy,
                brushStamp[i].id,
                brushStamp[i].layer
            );
        }

        updateDirtyCount();
        render();
    }

    /**
     * Layers sampled by the thermometer: the active (selected) layer plus
     * every layer marked visible in the layer list.
     *
     * @return {number[]}
     */
    function getThermometerLayerIds()
    {
        var out = [];
        var seen = {};
        var i;

        if (activeLayerId > 0)
        {
            out.push(activeLayerId);
            seen[activeLayerId] = true;
        }

        for (i = 0; i < tileLayers.length; i++)
        {
            var idLayer = parseInt(tileLayers[i].id_tile_layer, 10);

            if (!idLayer || seen[idLayer] || !isLayerVisible(idLayer))
            {
                continue;
            }

            out.push(idLayer);
            seen[idLayer] = true;
        }

        return out;
    }

    /**
     * @return {{minGx:number,maxGx:number,minGz:number,maxGz:number}|null}
     */
    function getThermoRect()
    {
        if (!thermoDragStart || !thermoDragEnd)
        {
            return null;
        }

        return {
            minGx: Math.min(thermoDragStart.gx, thermoDragEnd.gx),
            maxGx: Math.max(thermoDragStart.gx, thermoDragEnd.gx),
            minGz: Math.min(thermoDragStart.gz, thermoDragEnd.gz),
            maxGz: Math.max(thermoDragStart.gz, thermoDragEnd.gz)
        };
    }

    function isThermoCellSelected(gx, gz)
    {
        var rect = getThermoRect();

        if (!rect)
        {
            return false;
        }

        return gx >= rect.minGx && gx <= rect.maxGx && gz >= rect.minGz && gz <= rect.maxGz;
    }

    /**
     * Turns the current thermometer rectangle into a multi-layer brush stamp
     * (spatial layout preserved; empty layer cells omitted).
     */
    function finalizeThermometerSelection()
    {
        var rect = getThermoRect();

        if (!rect)
        {
            setStatus('Thermometer: drag a rectangle on the map to sample tiles.', true);
            return;
        }

        var layers = getThermometerLayerIds();
        var source = previewingBefore ? placementsBefore : placementsWorking;
        var stamp = [];
        var idOrder = [];
        var idSeen = {};
        var truncated = false;
        var gz;
        var gx;
        var k;

        for (gz = rect.minGz; gz <= rect.maxGz; gz++)
        {
            for (gx = rect.minGx; gx <= rect.maxGx; gx++)
            {
                for (k = 0; k < layers.length; k++)
                {
                    var idLayer = layers[k];
                    var idDef = source[cellLayerKey(gx, gz, idLayer)];

                    if (!idDef)
                    {
                        continue;
                    }

                    if (stamp.length >= MAX_BRUSH_CELLS)
                    {
                        truncated = true;
                        break;
                    }

                    stamp.push({
                        id: idDef,
                        dx: gx - rect.minGx,
                        dy: gz - rect.minGz,
                        layer: idLayer
                    });

                    if (!idSeen[idDef])
                    {
                        idSeen[idDef] = true;
                        idOrder.push(idDef);
                    }
                }

                if (truncated)
                {
                    break;
                }
            }

            if (truncated)
            {
                break;
            }
        }

        thermoDragging = false;
        thermoDragStart = null;
        thermoDragEnd = null;

        if (!stamp.length)
        {
            setStatus('Thermometer: no tiles on selected/visible layers in that selection.', true);
            render();
            return;
        }

        brushStampFromMap = true;
        brushIsEraser = false;
        brushStamp = stamp;
        selectedBrushIds = idOrder;
        lastPaletteBrushId = idOrder.length ? idOrder[0] : null;
        syncBrushUi();
        setStatus(
            'Thermometer: ' + stamp.length + ' placement(s) · '
            + idOrder.length + ' tile type(s) · '
            + layers.length + ' layer(s)'
            + (truncated ? ' (truncated at ' + MAX_BRUSH_CELLS + ')' : ''),
            truncated
        );
        render();
    }

    function setThermometerActive(active)
    {
        thermometerActive = !!active;
        thermoDragging = false;
        thermoDragStart = null;
        thermoDragEnd = null;

        var btn = document.getElementById('dwt-thermometer-btn');

        if (btn)
        {
            btn.classList.toggle('is-active', thermometerActive);
            btn.setAttribute('aria-pressed', thermometerActive ? 'true' : 'false');
        }

        if (canvas)
        {
            canvas.classList.toggle('is-thermometer', thermometerActive);
        }

        if (thermometerActive)
        {
            setStatus('Thermometer on: drag a rectangle to sample tiles (active + visible layers). Releases on mouse up.');
        }

        render();
    }

    function buildDiff()
    {
        return Object.keys(dirtyKeys).map(function (key)
        {
            var parts = key.split('_');

            return {
                gx: parseInt(parts[0], 10),
                gz: parseInt(parts[1], 10),
                layer: parseInt(parts[2], 10),
                def: placementsWorking[key] || 0
            };
        });
    }

    function getVisibleTileButtons()
    {
        var buttons = document.querySelectorAll('#dwt-palette .dwt-palette-btn');
        var out = [];

        for (var i = 0; i < buttons.length; i++)
        {
            var btn = buttons[i];
            var brush = btn.getAttribute('data-brush');

            if (brush === 'ERASER')
            {
                continue;
            }

            if (btn.style.display === 'none')
            {
                continue;
            }

            out.push(btn);
        }

        return out;
    }

    function estimatePaletteCols(buttons)
    {
        if (!buttons.length)
        {
            return 1;
        }

        var rowTop = buttons[0].offsetTop;
        var cols = 1;

        for (var i = 1; i < buttons.length; i++)
        {
            if (buttons[i].offsetTop !== rowTop)
            {
                break;
            }

            cols++;
        }

        return Math.max(1, cols);
    }

    function brushSelectionLabel()
    {
        if (brushIsEraser)
        {
            return 'Eraser';
        }

        if (!selectedBrushIds.length)
        {
            return 'none';
        }

        if (selectedBrushIds.length === 1)
        {
            var def = definitionsById[selectedBrushIds[0]];
            return def && def.code ? def.code : ('#' + selectedBrushIds[0]);
        }

        var maxDx = 0;
        var maxDy = 0;

        for (var i = 0; i < brushStamp.length; i++)
        {
            if (brushStamp[i].dx > maxDx) { maxDx = brushStamp[i].dx; }
            if (brushStamp[i].dy > maxDy) { maxDy = brushStamp[i].dy; }
        }

        return (maxDx + 1) + '×' + (maxDy + 1) + ' brush (' + selectedBrushIds.length + ')';
    }

    function rebuildBrushStamp()
    {
        if (brushStampFromMap)
        {
            return;
        }

        brushStamp = [];

        if (brushIsEraser || !selectedBrushIds.length)
        {
            return;
        }

        var selectedSet = {};

        for (var s = 0; s < selectedBrushIds.length; s++)
        {
            selectedSet[String(selectedBrushIds[s])] = true;
        }

        var visible = getVisibleTileButtons();
        var cols = estimatePaletteCols(visible);
        var cells = [];
        var minDx = Infinity;
        var minDy = Infinity;

        for (var i = 0; i < visible.length; i++)
        {
            var idStr = visible[i].getAttribute('data-brush');

            if (!selectedSet[idStr])
            {
                continue;
            }

            var dx = i % cols;
            var dy = Math.floor(i / cols);
            cells.push({ id: parseInt(idStr, 10), dx: dx, dy: dy });

            if (dx < minDx) { minDx = dx; }
            if (dy < minDy) { minDy = dy; }
        }

        // Fallback if filter hid every selected tile: pack in selection order.
        if (!cells.length)
        {
            var side = Math.ceil(Math.sqrt(selectedBrushIds.length));

            for (var j = 0; j < selectedBrushIds.length; j++)
            {
                brushStamp.push({
                    id: selectedBrushIds[j],
                    dx: j % side,
                    dy: Math.floor(j / side),
                    layer: paintLayerForTileId(selectedBrushIds[j])
                });
            }

            return;
        }

        for (var k = 0; k < cells.length; k++)
        {
            brushStamp.push({
                id: cells[k].id,
                dx: cells[k].dx - minDx,
                dy: cells[k].dy - minDy,
                layer: paintLayerForTileId(cells[k].id)
            });
        }
    }

    function syncBrushUi()
    {
        var label = brushSelectionLabel();

        if (brushLabelEl)
        {
            brushLabelEl.textContent = label;
        }

        var selectedSet = {};

        if (brushIsEraser)
        {
            selectedSet.ERASER = true;
        }
        else
        {
            for (var i = 0; i < selectedBrushIds.length; i++)
            {
                selectedSet[String(selectedBrushIds[i])] = true;
            }
        }

        var buttons = document.querySelectorAll('.dwt-palette-btn');

        for (var b = 0; b < buttons.length; b++)
        {
            var brush = buttons[b].getAttribute('data-brush');
            buttons[b].classList.toggle('dwt-palette-selected', !!selectedSet[brush]);
        }

        updatePalettePreview();
    }

    function setEditMaskButtonVisible(visible)
    {
        var editBtn = document.getElementById('dwt-edit-mask-btn');
        var toggleWrap = document.getElementById('dwt-preview-mask-toggle-wrap');

        if (editBtn)
        {
            editBtn.hidden = !visible;
        }

        if (toggleWrap)
        {
            toggleWrap.hidden = !visible;
        }
    }

    function setWalkableCheckboxVisible(visible)
    {
        var wrap = document.getElementById('dwt-preview-walkable-wrap');

        if (wrap)
        {
            wrap.hidden = !visible;
        }
    }

    function setLayerSelectVisible(visible)
    {
        var wrap = document.getElementById('dwt-preview-layer-wrap');

        if (wrap)
        {
            wrap.hidden = !visible;
        }
    }

    function defLayerId(def)
    {
        if (!def || def.id_tile_layer === null || def.id_tile_layer === undefined || def.id_tile_layer === '')
        {
            return 0;
        }

        return parseInt(def.id_tile_layer, 10) || 0;
    }

    /**
     * Target layer when painting a palette tile: its scoped id_tile_layer,
     * or the currently selected layer when the def is "any layer" (0/null).
     */
    function paintLayerForTileId(idTileDefinition)
    {
        var def = definitionsById[idTileDefinition];
        var scoped = defLayerId(def);

        return scoped > 0 ? scoped : activeLayerId;
    }

    function layerLabel(idLayer)
    {
        var id = parseInt(idLayer, 10) || 0;

        if (id <= 0)
        {
            return 'any layer';
        }

        for (var i = 0; i < tileLayers.length; i++)
        {
            if (parseInt(tileLayers[i].id_tile_layer, 10) === id)
            {
                return tileLayers[i].name || ('layer #' + id);
            }
        }

        return 'layer #' + id;
    }

    /**
     * Syncs the palette is_walkable checkbox from the current selection.
     * Mixed values across a multi-brush → indeterminate.
     */
    function syncWalkableCheckbox()
    {
        var checkbox = document.getElementById('dwt-preview-is-walkable');

        if (!checkbox)
        {
            return;
        }

        if (brushIsEraser || !selectedBrushIds.length)
        {
            setWalkableCheckboxVisible(false);
            checkbox.checked = false;
            checkbox.indeterminate = false;
            checkbox.disabled = false;
            return;
        }

        var walkableCount = 0;
        var barrierCount = 0;
        var i;

        for (i = 0; i < selectedBrushIds.length; i++)
        {
            var def = definitionsById[selectedBrushIds[i]];

            if (def && def.is_walkable === 'N')
            {
                barrierCount++;
            }
            else
            {
                walkableCount++;
            }
        }

        setWalkableCheckboxVisible(true);
        checkbox.disabled = false;
        checkbox.indeterminate = (walkableCount > 0 && barrierCount > 0);
        checkbox.checked = (barrierCount === 0);
    }

    /**
     * Syncs the palette layer select from the current selection.
     * Mixed values → "mixed…" sentinel.
     */
    function syncLayerSelect()
    {
        var select = document.getElementById('dwt-preview-id-tile-layer');
        var mixedOpt = select ? select.querySelector('option[value="__mixed__"]') : null;

        if (!select)
        {
            return;
        }

        if (brushIsEraser || !selectedBrushIds.length)
        {
            setLayerSelectVisible(false);
            select.disabled = false;
            if (mixedOpt) { mixedOpt.hidden = true; mixedOpt.disabled = true; }
            select.value = '0';
            return;
        }

        var firstLayer = defLayerId(definitionsById[selectedBrushIds[0]]);
        var mixed = false;
        var i;

        for (i = 1; i < selectedBrushIds.length; i++)
        {
            if (defLayerId(definitionsById[selectedBrushIds[i]]) !== firstLayer)
            {
                mixed = true;
                break;
            }
        }

        setLayerSelectVisible(true);
        select.disabled = false;

        if (mixed)
        {
            if (mixedOpt)
            {
                mixedOpt.hidden = false;
                mixedOpt.disabled = false;
            }

            select.value = '__mixed__';
        }
        else
        {
            if (mixedOpt)
            {
                mixedOpt.hidden = true;
                mixedOpt.disabled = true;
            }

            select.value = String(firstLayer);
        }
    }

    function applyLayerToPaletteButtons(ids, idLayer)
    {
        var layerStr = String(parseInt(idLayer, 10) || 0);
        var i;

        for (i = 0; i < ids.length; i++)
        {
            var btn = document.querySelector('.dwt-palette-btn[data-brush="' + ids[i] + '"]');

            if (btn)
            {
                btn.setAttribute('data-layer', layerStr);
            }
        }
    }

    function isPreviewMaskToggleOn()
    {
        var toggle = document.getElementById('dwt-preview-mask-toggle');

        return !!(toggle && toggle.checked);
    }

    function clearPreviewMaskOverlay()
    {
        var canvasEl = document.getElementById('dwt-palette-preview-mask');

        if (!canvasEl)
        {
            return;
        }

        var ctx = canvasEl.getContext('2d');
        ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
        canvasEl.classList.add('is-hidden');
    }

    /**
     * Draws the selected tile's collision_mask onto the preview overlay canvas
     * when the "Show collision mask" checkbox is on (single-tile selection only).
     */
    function refreshPreviewMaskOverlay()
    {
        var canvasEl = document.getElementById('dwt-palette-preview-mask');

        if (!canvasEl)
        {
            return;
        }

        if (!isPreviewMaskToggleOn()
            || brushIsEraser
            || selectedBrushIds.length !== 1)
        {
            clearPreviewMaskOverlay();
            return;
        }

        var def = definitionsById[selectedBrushIds[0]];
        var raw = def && def.collision_mask ? String(def.collision_mask) : '';
        var maskSize = parseInt(bootstrap.maskSize, 10) || 8;
        var expected = maskSize * maskSize;
        var ctx = canvasEl.getContext('2d');
        var w = canvasEl.width;
        var h = canvasEl.height;
        var cellW = w / maskSize;
        var cellH = h / maskSize;
        var r;
        var c;

        ctx.clearRect(0, 0, w, h);
        canvasEl.classList.remove('is-hidden');

        // Subtle grid so empty masks still show the cell layout when toggled on.
        ctx.strokeStyle = 'rgba(255,255,255,0.18)';
        ctx.lineWidth = 1;

        for (r = 0; r < maskSize; r++)
        {
            for (c = 0; c < maskSize; c++)
            {
                var idx = r * maskSize + c;
                var px = c * cellW;
                var py = r * cellH;
                var blocked = (raw.length === expected && raw.charAt(idx) === '0');

                if (blocked)
                {
                    ctx.fillStyle = 'rgba(220,53,69,0.55)';
                    ctx.fillRect(px, py, cellW, cellH);
                }

                ctx.strokeRect(px + 0.5, py + 0.5, cellW - 1, cellH - 1);
            }
        }
    }

    function updatePalettePreview()
    {
        var imgEl = document.getElementById('dwt-palette-preview-img');
        var placeholderEl = document.getElementById('dwt-palette-preview-placeholder');
        var codeEl = document.getElementById('dwt-palette-preview-code');
        var subEl = document.getElementById('dwt-palette-preview-sub');

        if (!codeEl || !subEl)
        {
            return;
        }

        if (brushIsEraser)
        {
            if (imgEl)
            {
                imgEl.classList.add('is-empty');
                imgEl.removeAttribute('src');
            }

            if (placeholderEl)
            {
                placeholderEl.classList.remove('is-hidden');
                placeholderEl.textContent = '✕';
            }

            codeEl.textContent = 'Eraser';
            subEl.textContent = 'Clears the active layer on painted cells';
            setEditMaskButtonVisible(false);
            syncWalkableCheckbox();
            syncLayerSelect();
            clearPreviewMaskOverlay();
            return;
        }

        if (!selectedBrushIds.length)
        {
            if (imgEl)
            {
                imgEl.classList.add('is-empty');
                imgEl.removeAttribute('src');
            }

            if (placeholderEl)
            {
                placeholderEl.classList.remove('is-hidden');
                placeholderEl.textContent = '—';
            }

            codeEl.textContent = 'No brush selected';
            subEl.textContent = 'Click a tile · Ctrl+click add · Shift+click range';
            setEditMaskButtonVisible(false);
            syncWalkableCheckbox();
            syncLayerSelect();
            clearPreviewMaskOverlay();
            return;
        }

        var brushId = selectedBrushIds[0];
        var def = definitionsById[brushId];
        var imageFile = def && def.image_file ? def.image_file : '';
        var code = (def && def.code) ? def.code : ('#' + brushId);

        if (imgEl && imageFile)
        {
            imgEl.src = imgBase + imageFile;
            imgEl.alt = code;
            imgEl.classList.remove('is-empty');
        }
        else if (imgEl)
        {
            imgEl.classList.add('is-empty');
            imgEl.removeAttribute('src');
        }

        if (placeholderEl)
        {
            placeholderEl.classList.toggle('is-hidden', !!imageFile);
            placeholderEl.textContent = imageFile ? '' : '?';
        }

        if (selectedBrushIds.length === 1 && !brushStampFromMap)
        {
            var hasMask = !!(def && def.collision_mask);
            var walkLabel = (def && def.is_walkable === 'N') ? 'barrier' : 'walkable';
            codeEl.textContent = code;
            subEl.textContent = [
                '#' + brushId,
                def && def.pack ? ('pack ' + def.pack) : '',
                def && def.category ? def.category : '',
                layerLabel(defLayerId(def)),
                walkLabel,
                hasMask ? 'masked' : 'no mask',
                imageFile
            ].filter(Boolean).join(' · ');
            setEditMaskButtonVisible(true);
            syncWalkableCheckbox();
            syncLayerSelect();
            refreshPreviewMaskOverlay();
            return;
        }

        if (selectedBrushIds.length === 1 && brushStampFromMap && brushStamp.length === 1)
        {
            var hasMaskMap = !!(def && def.collision_mask);
            var walkLabelMap = (def && def.is_walkable === 'N') ? 'barrier' : 'walkable';
            codeEl.textContent = code;
            subEl.textContent = [
                '#' + brushId,
                'thermometer',
                layerLabel(brushStamp[0].layer || defLayerId(def)),
                walkLabelMap,
                hasMaskMap ? 'masked' : 'no mask',
                imageFile
            ].filter(Boolean).join(' · ');
            setEditMaskButtonVisible(true);
            syncWalkableCheckbox();
            syncLayerSelect();
            refreshPreviewMaskOverlay();
            return;
        }

        var maxDx = 0;
        var maxDy = 0;

        for (var i = 0; i < brushStamp.length; i++)
        {
            if (brushStamp[i].dx > maxDx) { maxDx = brushStamp[i].dx; }
            if (brushStamp[i].dy > maxDy) { maxDy = brushStamp[i].dy; }
        }

        codeEl.textContent = (maxDx + 1) + '×' + (maxDy + 1) + ' brush';
        subEl.textContent = brushStampFromMap
            ? (brushStamp.length + ' placements · thermometer sample · paints original layers')
            : (selectedBrushIds.length + ' tiles · click paints the whole stamp · Ctrl+click to edit set');
        setEditMaskButtonVisible(false);
        syncWalkableCheckbox();
        syncLayerSelect();
        clearPreviewMaskOverlay();
    }

    function selectBrush(brushId, label)
    {
        brushStampFromMap = false;

        if (brushId === null || brushId === undefined || brushId === '')
        {
            brushIsEraser = false;
            selectedBrushIds = [];
            lastPaletteBrushId = null;
            rebuildBrushStamp();
            syncBrushUi();
            return;
        }

        if (brushId === 'ERASER')
        {
            brushIsEraser = true;
            selectedBrushIds = [];
            lastPaletteBrushId = null;
            rebuildBrushStamp();
            syncBrushUi();
            return;
        }

        brushIsEraser = false;
        selectedBrushIds = [parseInt(brushId, 10)];
        lastPaletteBrushId = selectedBrushIds[0];
        rebuildBrushStamp();
        syncBrushUi();
    }

    function toggleBrushTile(brushId)
    {
        brushId = parseInt(brushId, 10);
        brushIsEraser = false;
        brushStampFromMap = false;

        var idx = selectedBrushIds.indexOf(brushId);

        if (idx >= 0)
        {
            selectedBrushIds.splice(idx, 1);
        }
        else
        {
            if (selectedBrushIds.length >= MAX_BRUSH_CELLS)
            {
                setStatus('Brush max is ' + MAX_BRUSH_CELLS + ' tiles.', true);
                return;
            }

            selectedBrushIds.push(brushId);
        }

        lastPaletteBrushId = brushId;
        rebuildBrushStamp();
        syncBrushUi();
    }

    function rangeSelectBrushTiles(brushId)
    {
        brushId = parseInt(brushId, 10);
        brushIsEraser = false;
        brushStampFromMap = false;

        var visible = getVisibleTileButtons();
        var ids = visible.map(function (btn)
        {
            return parseInt(btn.getAttribute('data-brush'), 10);
        });
        var endIdx = ids.indexOf(brushId);
        var startIdx = lastPaletteBrushId !== null ? ids.indexOf(lastPaletteBrushId) : endIdx;

        if (endIdx < 0)
        {
            selectBrush(brushId);
            return;
        }

        if (startIdx < 0)
        {
            startIdx = endIdx;
        }

        var a = Math.min(startIdx, endIdx);
        var b = Math.max(startIdx, endIdx);
        var next = [];

        for (var i = a; i <= b; i++)
        {
            if (next.length >= MAX_BRUSH_CELLS)
            {
                setStatus('Brush max is ' + MAX_BRUSH_CELLS + ' tiles.', true);
                break;
            }

            next.push(ids[i]);
        }

        selectedBrushIds = next;
        lastPaletteBrushId = brushId;
        rebuildBrushStamp();
        syncBrushUi();
    }

    function refreshPaletteFilter()
    {
        var buttons = document.querySelectorAll('.dwt-palette-btn');
        // With a specific pack selected, show every tile in that pack
        // regardless of the active map layer (layer scoping applies on paint).
        var ignoreLayerFilter = selectedPackFilter !== '*';

        for (var i = 0; i < buttons.length; i++)
        {
            var btn = buttons[i];
            var layerAttr = parseInt(btn.getAttribute('data-layer'), 10) || 0;
            var packAttr = btn.getAttribute('data-pack');
            var layerOk = ignoreLayerFilter || layerAttr === 0 || layerAttr === activeLayerId;
            var packOk = true;

            if (selectedPackFilter === '*')
            {
                packOk = true;
            }
            else if (packAttr === '*')
            {
                // Eraser always visible.
                packOk = true;
            }
            else
            {
                packOk = String(packAttr || '') === String(selectedPackFilter);
            }

            btn.style.display = (layerOk && packOk) ? '' : 'none';
        }

        // Stamp offsets follow the visible palette grid.
        rebuildBrushStamp();
        syncBrushUi();
    }

    function initPackFilter()
    {
        var select = document.getElementById('dwt-pack-filter');

        if (!select)
        {
            return;
        }

        select.addEventListener('change', function ()
        {
            selectedPackFilter = select.value;
            refreshPaletteFilter();
        });
    }

    function setPaletteDensity(compact)
    {
        var palette = document.getElementById('dwt-palette');
        var btn = document.getElementById('dwt-palette-density-btn');

        if (palette)
        {
            palette.classList.toggle('is-compact', !!compact);
            palette.classList.toggle('gap-2', !compact);
        }

        if (btn)
        {
            btn.textContent = compact ? 'flush' : 'spaced';
            btn.classList.toggle('btn-info', !!compact);
            btn.classList.toggle('btn-outline-secondary', !compact);
            btn.title = compact
                ? 'Flush grid on — click for spaced tiles with labels'
                : 'Spaced tiles — click for flush grid (no gaps)';
        }

        try
        {
            localStorage.setItem(PALETTE_DENSITY_KEY, compact ? 'compact' : 'spaced');
        }
        catch (e)
        {
            /* ignore */
        }
    }

    function initPaletteDensity()
    {
        var btn = document.getElementById('dwt-palette-density-btn');
        var compact = false;

        try
        {
            compact = localStorage.getItem(PALETTE_DENSITY_KEY) === 'compact';
        }
        catch (e)
        {
            compact = false;
        }

        setPaletteDensity(compact);

        if (!btn)
        {
            return;
        }

        btn.addEventListener('click', function ()
        {
            var palette = document.getElementById('dwt-palette');
            setPaletteDensity(!(palette && palette.classList.contains('is-compact')));
            // Flush vs spaced changes column count → rebuild stamp geometry.
            rebuildBrushStamp();
            syncBrushUi();
        });
    }

    function initFloatingPalette()
    {
        var panel = document.getElementById('dwt-palette-float');
        var header = document.getElementById('dwt-palette-float-header');
        var resetBtn = document.getElementById('dwt-palette-reset-pos');

        if (!panel || !header)
        {
            return;
        }

        function clampPanel()
        {
            var rect = panel.getBoundingClientRect();
            var maxLeft = Math.max(8, window.innerWidth - rect.width - 8);
            var maxTop = Math.max(8, window.innerHeight - 48);
            var left = Math.min(maxLeft, Math.max(8, rect.left));
            var top = Math.min(maxTop, Math.max(8, rect.top));
            panel.style.left = left + 'px';
            panel.style.top = top + 'px';
            panel.style.right = 'auto';
        }

        function savePos()
        {
            try
            {
                var rect = panel.getBoundingClientRect();
                localStorage.setItem(PALETTE_POS_KEY, JSON.stringify({
                    left: Math.round(rect.left),
                    top: Math.round(rect.top),
                    width: Math.round(rect.width),
                    height: Math.round(rect.height)
                }));
            }
            catch (e)
            {
                /* ignore quota / private mode */
            }
        }

        function loadPos()
        {
            try
            {
                var raw = localStorage.getItem(PALETTE_POS_KEY);

                if (!raw)
                {
                    return;
                }

                var pos = JSON.parse(raw);

                if (!pos || typeof pos.left !== 'number' || typeof pos.top !== 'number')
                {
                    return;
                }

                panel.style.left = pos.left + 'px';
                panel.style.top = pos.top + 'px';
                panel.style.right = 'auto';

                if (pos.width >= 240)
                {
                    panel.style.width = pos.width + 'px';
                }

                if (pos.height >= 280)
                {
                    panel.style.height = pos.height + 'px';
                }

                clampPanel();
            }
            catch (e)
            {
                /* ignore */
            }
        }

        function setPaletteVisible(visible)
        {
            panel.style.display = visible ? 'flex' : 'none';
        }

        loadPos();

        var dragging = false;
        var startX = 0;
        var startY = 0;
        var originLeft = 0;
        var originTop = 0;

        header.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0)
            {
                return;
            }

            if (e.target && e.target.closest && e.target.closest('button, select, input, a'))
            {
                return;
            }

            var rect = panel.getBoundingClientRect();
            dragging = true;
            panel.classList.add('is-dragging');
            startX = e.clientX;
            startY = e.clientY;
            originLeft = rect.left;
            originTop = rect.top;
            panel.style.left = originLeft + 'px';
            panel.style.top = originTop + 'px';
            panel.style.right = 'auto';
            e.preventDefault();
        });

        window.addEventListener('mousemove', function (e)
        {
            if (!dragging)
            {
                return;
            }

            var left = originLeft + (e.clientX - startX);
            var top = originTop + (e.clientY - startY);
            var maxLeft = Math.max(8, window.innerWidth - panel.offsetWidth - 8);
            var maxTop = Math.max(8, window.innerHeight - 48);
            panel.style.left = Math.min(maxLeft, Math.max(8, left)) + 'px';
            panel.style.top = Math.min(maxTop, Math.max(8, top)) + 'px';
        });

        window.addEventListener('mouseup', function ()
        {
            if (!dragging)
            {
                return;
            }

            dragging = false;
            panel.classList.remove('is-dragging');
            savePos();
        });

        window.addEventListener('resize', function ()
        {
            clampPanel();
            savePos();
        });

        // Persist size after user finishes a CSS resize drag.
        if (typeof ResizeObserver !== 'undefined')
        {
            var resizeTimer = null;
            var observer = new ResizeObserver(function ()
            {
                if (resizeTimer)
                {
                    clearTimeout(resizeTimer);
                }

                resizeTimer = setTimeout(savePos, 200);
            });
            observer.observe(panel);
        }

        if (resetBtn)
        {
            resetBtn.addEventListener('click', function ()
            {
                try
                {
                    localStorage.removeItem(PALETTE_POS_KEY);
                }
                catch (e)
                {
                    /* ignore */
                }

                panel.style.left = '';
                panel.style.top = '96px';
                panel.style.right = '24px';
                panel.style.width = '560px';
                panel.style.height = '480px';
            });
        }

        var mapPane = document.getElementById('dwt-tab-map');
        setPaletteVisible(!!(mapPane && mapPane.classList.contains('active')));

        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (tabBtn)
        {
            tabBtn.addEventListener('shown.bs.tab', function (e)
            {
                var target = e.target && e.target.getAttribute('data-bs-target');
                setPaletteVisible(target === '#dwt-tab-map');
            });
        });
    }

    function setActiveLayer(idLayer)
    {
        if (!idLayer || idLayer === activeLayerId)
        {
            return;
        }

        activeLayerId = idLayer;

        var rows = document.querySelectorAll('.dwt-layer-row');

        for (var i = 0; i < rows.length; i++)
        {
            var rowLayerId = parseInt(rows[i].getAttribute('data-layer-id'), 10);
            rows[i].classList.toggle('dwt-layer-active', rowLayerId === idLayer);
        }

        refreshPaletteFilter();
        selectBrush(null, 'none');
        render();
    }

    /**
     * Select the rectangular palette region from startIdx..endIdx (inclusive)
     * in the current visible tile grid. Used by click-drag multi-select.
     */
    function applyPaletteDragSelection(startIdx, endIdx)
    {
        var visible = getVisibleTileButtons();

        if (!visible.length || startIdx < 0 || endIdx < 0)
        {
            return;
        }

        var cols = estimatePaletteCols(visible);
        var r0 = Math.floor(startIdx / cols);
        var c0 = startIdx % cols;
        var r1 = Math.floor(endIdx / cols);
        var c1 = endIdx % cols;
        var minR = Math.min(r0, r1);
        var maxR = Math.max(r0, r1);
        var minC = Math.min(c0, c1);
        var maxC = Math.max(c0, c1);
        var rectIds = [];
        var r;
        var c;
        var i;

        for (r = minR; r <= maxR; r++)
        {
            for (c = minC; c <= maxC; c++)
            {
                i = r * cols + c;

                if (i >= visible.length)
                {
                    continue;
                }

                if (rectIds.length >= MAX_BRUSH_CELLS)
                {
                    setStatus('Brush max is ' + MAX_BRUSH_CELLS + ' tiles.', true);
                    break;
                }

                rectIds.push(parseInt(visible[i].getAttribute('data-brush'), 10));
            }
        }

        brushIsEraser = false;
        brushStampFromMap = false;

        if (paletteDrag && paletteDrag.additive)
        {
            var set = {};
            var b;

            for (b = 0; b < paletteDrag.baseSelection.length; b++)
            {
                set[paletteDrag.baseSelection[b]] = true;
            }

            for (b = 0; b < rectIds.length; b++)
            {
                set[rectIds[b]] = true;
            }

            selectedBrushIds = Object.keys(set).map(function (k)
            {
                return parseInt(k, 10);
            });

            if (selectedBrushIds.length > MAX_BRUSH_CELLS)
            {
                selectedBrushIds = selectedBrushIds.slice(0, MAX_BRUSH_CELLS);
                setStatus('Brush max is ' + MAX_BRUSH_CELLS + ' tiles.', true);
            }
        }
        else
        {
            selectedBrushIds = rectIds;
        }

        lastPaletteBrushId = selectedBrushIds.length
            ? selectedBrushIds[selectedBrushIds.length - 1]
            : null;
        rebuildBrushStamp();
        syncBrushUi();
    }

    function initPaletteDragSelect()
    {
        var palette = document.getElementById('dwt-palette');

        if (!palette)
        {
            return;
        }

        palette.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0)
            {
                return;
            }

            var btn = e.target.closest('.dwt-palette-btn');

            if (!btn || !palette.contains(btn))
            {
                return;
            }

            var brush = btn.getAttribute('data-brush');

            if (brush === 'ERASER')
            {
                return;
            }

            // Shift+click keeps discrete range behavior via click handler.
            if (e.shiftKey)
            {
                return;
            }

            var visible = getVisibleTileButtons();
            var startIdx = visible.indexOf(btn);

            if (startIdx < 0)
            {
                return;
            }

            var additive = !!(e.ctrlKey || e.metaKey);

            paletteDrag = {
                startIdx: startIdx,
                startId: parseInt(brush, 10),
                additive: additive,
                moved: false,
                baseSelection: additive ? selectedBrushIds.slice() : []
            };

            if (!additive)
            {
                applyPaletteDragSelection(startIdx, startIdx);
            }

            e.preventDefault();
        });

        palette.addEventListener('mouseover', function (e)
        {
            if (!paletteDrag)
            {
                return;
            }

            var btn = e.target.closest('.dwt-palette-btn');

            if (!btn || !palette.contains(btn))
            {
                return;
            }

            if (btn.getAttribute('data-brush') === 'ERASER')
            {
                return;
            }

            var visible = getVisibleTileButtons();
            var idx = visible.indexOf(btn);

            if (idx < 0)
            {
                return;
            }

            if (idx !== paletteDrag.startIdx)
            {
                paletteDrag.moved = true;
            }

            applyPaletteDragSelection(paletteDrag.startIdx, idx);
        });

        window.addEventListener('mouseup', function ()
        {
            if (!paletteDrag)
            {
                return;
            }

            // mousedown already owns selection (or Ctrl+click toggle below) —
            // always swallow the trailing click.
            suppressPaletteClick = true;

            if (!paletteDrag.moved && paletteDrag.additive)
            {
                toggleBrushTile(paletteDrag.startId);
            }
            else if (!paletteDrag.moved && !paletteDrag.additive)
            {
                // Single click replace already applied on mousedown.
            }

            paletteDrag = null;
        });
    }

    function initPalette()
    {
        var buttons = document.querySelectorAll('.dwt-palette-btn');

        for (var i = 0; i < buttons.length; i++)
        {
            buttons[i].addEventListener('click', function (e)
            {
                if (suppressPaletteClick)
                {
                    suppressPaletteClick = false;
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                var brush = e.currentTarget.getAttribute('data-brush');

                if (brush === 'ERASER')
                {
                    selectBrush('ERASER');
                    return;
                }

                var id = parseInt(brush, 10);

                if (e.shiftKey)
                {
                    rangeSelectBrushTiles(id);
                    return;
                }

                if (e.ctrlKey || e.metaKey)
                {
                    toggleBrushTile(id);
                    return;
                }

                selectBrush(id);
            });
        }

        initPaletteDragSelect();
        initPackFilter();
        initPaletteDensity();
        refreshPaletteFilter();
        initFloatingPalette();
    }

    function initLayers()
    {
        var rows = document.querySelectorAll('.dwt-layer-row');

        for (var i = 0; i < rows.length; i++)
        {
            layerVisible[parseInt(rows[i].getAttribute('data-layer-id'), 10)] = true;
        }

        var selectButtons = document.querySelectorAll('.dwt-layer-select-btn');

        for (var j = 0; j < selectButtons.length; j++)
        {
            selectButtons[j].addEventListener('click', function (e)
            {
                var row = e.currentTarget.closest('.dwt-layer-row');
                var idLayer = row ? parseInt(row.getAttribute('data-layer-id'), 10) : 0;
                setActiveLayer(idLayer);
            });
        }

        var visibleCheckboxes = document.querySelectorAll('.dwt-layer-visible-checkbox');

        for (var k = 0; k < visibleCheckboxes.length; k++)
        {
            visibleCheckboxes[k].addEventListener('change', function (e)
            {
                var row = e.currentTarget.closest('.dwt-layer-row');
                var idLayer = row ? parseInt(row.getAttribute('data-layer-id'), 10) : 0;
                layerVisible[idLayer] = e.currentTarget.checked;
                render();
            });
        }
    }

    function initPan()
    {
        var panButtons = document.querySelectorAll('.dwt-pan-btn');

        for (var i = 0; i < panButtons.length; i++)
        {
            panButtons[i].addEventListener('click', function (e)
            {
                var dir = e.currentTarget.getAttribute('data-pan');
                var step = 4;

                if (dir === 'up') { originGz -= step; }
                if (dir === 'down') { originGz += step; }
                if (dir === 'left') { originGx -= step; }
                if (dir === 'right') { originGx += step; }
                if (dir === 'home')
                {
                    originGx = -Math.floor(COLS / 2);
                    originGz = -Math.floor(ROWS / 2);
                }

                render();
            });
        }

        var thermoBtn = document.getElementById('dwt-thermometer-btn');

        if (thermoBtn)
        {
            thermoBtn.addEventListener('click', function ()
            {
                setThermometerActive(!thermometerActive);
            });
        }
    }

    function initToolbar()
    {
        if (saveBtn)
        {
            saveBtn.addEventListener('click', function ()
            {
                var changes = buildDiff();

                if (!changes.length)
                {
                    setStatus('Nothing to save.', false);
                    return;
                }

                saveBtn.disabled = true;

                postAction({ action: 'save_tile_batch', id_zone: idZone, changes: JSON.stringify(changes) }).then(function (result)
                {
                    if (!result || !result.ok)
                    {
                        setStatus((result && result.message) || 'Failed to save.', true);
                        saveBtn.disabled = false;
                        return;
                    }

                    placementsBefore = clonePlacements(placementsWorking);
                    dirtyKeys = {};
                    clearUndoStack();
                    updateDirtyCount();
                    setStatus(result.message || 'Saved.', false);
                    render();
                });
            });
        }

        if (revertLastBtn)
        {
            revertLastBtn.addEventListener('click', function ()
            {
                revertLastChange();
            });
        }

        if (revertBtn)
        {
            revertBtn.addEventListener('click', function ()
            {
                placementsWorking = clonePlacements(placementsBefore);
                dirtyKeys = {};
                clearUndoStack();
                updateDirtyCount();
                setStatus('Reverted to last save.', false);
                render();
            });
        }

        if (previewToggleBtn)
        {
            previewToggleBtn.addEventListener('click', function ()
            {
                previewingBefore = !previewingBefore;
                previewToggleBtn.textContent = previewingBefore ? 'Preview: showing before' : 'Preview: showing edits';
                previewToggleBtn.classList.toggle('btn-info', previewingBefore);
                previewToggleBtn.classList.toggle('btn-outline-info', !previewingBefore);
                render();
            });
        }

        updateDirtyCount();
    }

    function initCanvas()
    {
        if (!canvas)
        {
            return;
        }

        canvas.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0)
            {
                return;
            }

            var cell = canvasPosToCell(e.clientX, e.clientY);

            if (thermometerActive)
            {
                thermoDragging = true;
                thermoDragStart = { gx: cell.gx, gz: cell.gz };
                thermoDragEnd = { gx: cell.gx, gz: cell.gz };
                render();
                return;
            }

            painting = true;
            beginUndoStroke();
            paintCell(cell.gx, cell.gz);
        });

        canvas.addEventListener('mousemove', function (e)
        {
            hoverCell = canvasPosToCell(e.clientX, e.clientY);

            if (coordsEl)
            {
                coordsEl.textContent = 'grid (' + hoverCell.gx + ', ' + hoverCell.gz + ')';
            }

            if (thermometerActive && thermoDragging)
            {
                thermoDragEnd = { gx: hoverCell.gx, gz: hoverCell.gz };
                render();
                return;
            }

            if (painting)
            {
                paintCell(hoverCell.gx, hoverCell.gz);
            }

            render();
        });

        canvas.addEventListener('mouseleave', function ()
        {
            hoverCell = null;
            render();
        });

        window.addEventListener('mouseup', function ()
        {
            if (thermometerActive && thermoDragging)
            {
                finalizeThermometerSelection();
                setThermometerActive(false);
                return;
            }

            if (painting)
            {
                painting = false;
                commitUndoStroke();
                return;
            }

            painting = false;
        });
    }

    if (canvas && idZone > 0)
    {
        initLayers();
        initPalette();
        initPan();
        initToolbar();
        initCanvas();
        render();
    }

    /**
     * Sub-tile collision mask painter: shows the tile art scaled up with an
     * NxN grid overlay (N = bootstrap.maskSize, kept in sync with
     * ANIMASTER_TILE_MASK_SIZE server-side and TILE_MASK_SIZE in
     * world_tiles.js). Click/drag toggles cells; the resulting row-major
     * '0'/'1' string is written to the hidden collision_mask input on every
     * change. An all-walkable mask is submitted as '' (NULL == no mask, the
     * tile falls back to the whole-tile is_walkable flag).
     */
    function initMaskPainter()
    {
        var maskCanvas = document.getElementById('dwt-mask-canvas');
        var maskInput = document.getElementById('dwt-mask-input');
        var imageFileInput = document.getElementById('dwt-image-file');
        var clearBtn = document.getElementById('dwt-mask-clear');

        if (!maskCanvas || !maskInput)
        {
            return;
        }

        var maskCtx = maskCanvas.getContext('2d');
        var maskSize = parseInt(bootstrap.maskSize, 10) || 8;
        var cellPx = maskCanvas.width / maskSize;
        var previewImg = null;
        var previewLoaded = false;
        var mask = normalizeMask(maskInput.value);
        var maskPainting = false;
        var paintValue = '1';

        function normalizeMask(raw)
        {
            var expected = maskSize * maskSize;

            if (raw && raw.length === expected && /^[01]+$/.test(raw))
            {
                return raw.split('');
            }

            var arr = [];

            for (var i = 0; i < expected; i++)
            {
                arr.push('1');
            }

            return arr;
        }

        function syncInput()
        {
            maskInput.value = (mask.indexOf('0') === -1) ? '' : mask.join('');
        }

        function drawMask()
        {
            maskCtx.clearRect(0, 0, maskCanvas.width, maskCanvas.height);

            if (previewLoaded && previewImg)
            {
                maskCtx.drawImage(previewImg, 0, 0, maskCanvas.width, maskCanvas.height);
            }
            else
            {
                maskCtx.fillStyle = '#111a26';
                maskCtx.fillRect(0, 0, maskCanvas.width, maskCanvas.height);
            }

            for (var r = 0; r < maskSize; r++)
            {
                for (var c = 0; c < maskSize; c++)
                {
                    var idx = r * maskSize + c;
                    var px = c * cellPx;
                    var py = r * cellPx;

                    if (mask[idx] === '0')
                    {
                        maskCtx.fillStyle = 'rgba(220,53,69,0.55)';
                        maskCtx.fillRect(px, py, cellPx, cellPx);
                    }

                    maskCtx.strokeStyle = 'rgba(255,255,255,0.25)';
                    maskCtx.lineWidth = 1;
                    maskCtx.strokeRect(px + 0.5, py + 0.5, cellPx - 1, cellPx - 1);
                }
            }
        }

        function loadPreview(imageFile)
        {
            previewLoaded = false;

            if (!imageFile)
            {
                drawMask();
                return;
            }

            previewImg = new Image();
            previewImg.onload = function ()
            {
                previewLoaded = true;
                drawMask();
            };
            previewImg.onerror = function ()
            {
                previewLoaded = false;
                drawMask();
            };
            previewImg.src = imgBase + imageFile;
        }

        function cellIndexFromEvent(e)
        {
            var rect = maskCanvas.getBoundingClientRect();
            var scaleX = maskCanvas.width / rect.width;
            var scaleY = maskCanvas.height / rect.height;
            var x = (e.clientX - rect.left) * scaleX;
            var y = (e.clientY - rect.top) * scaleY;
            var c = Math.min(maskSize - 1, Math.max(0, Math.floor(x / cellPx)));
            var r = Math.min(maskSize - 1, Math.max(0, Math.floor(y / cellPx)));

            return r * maskSize + c;
        }

        function paintAt(e)
        {
            mask[cellIndexFromEvent(e)] = paintValue;
            syncInput();
            drawMask();
        }

        maskCanvas.addEventListener('mousedown', function (e)
        {
            var idx = cellIndexFromEvent(e);
            paintValue = (mask[idx] === '0') ? '1' : '0';
            maskPainting = true;
            paintAt(e);
        });

        maskCanvas.addEventListener('mousemove', function (e)
        {
            if (maskPainting)
            {
                paintAt(e);
            }
        });

        window.addEventListener('mouseup', function ()
        {
            maskPainting = false;
        });

        if (clearBtn)
        {
            clearBtn.addEventListener('click', function ()
            {
                mask = normalizeMask('');
                syncInput();
                drawMask();
            });
        }

        if (imageFileInput)
        {
            var refreshPreview = function ()
            {
                loadPreview(imageFileInput.value.trim());
            };

            imageFileInput.addEventListener('change', refreshPreview);
            imageFileInput.addEventListener('blur', refreshPreview);
        }

        var initialImageFile = (bootstrap.editDefinition && bootstrap.editDefinition.imageFile)
            || (imageFileInput ? imageFileInput.value.trim() : '');

        loadPreview(initialImageFile);
        syncInput();
        drawMask();
    }

    /**
     * Preview the cut grid for Import tileset: draw the uploaded sheet with
     * source_tile_px cell lines and report cols×rows / expected output count.
     */
    function initTilesetImportPreview()
    {
        var fileInput = document.getElementById('dwt-tileset-file');
        var sourceInput = document.getElementById('dwt-source-tile-px');
        var outputInput = document.getElementById('dwt-output-tile-px');
        var sheetWInput = document.getElementById('dwt-sheet-w');
        var sheetHInput = document.getElementById('dwt-sheet-h');
        var metaEl = document.getElementById('dwt-tileset-meta');
        var boxEl = document.getElementById('dwt-tileset-preview-box');
        var canvasEl = document.getElementById('dwt-tileset-preview');
        var formEl = document.getElementById('dwt-tileset-import-form');

        if (!fileInput || !canvasEl || !metaEl)
        {
            return;
        }

        var previewCtx = canvasEl.getContext('2d');
        var loadedImg = null;
        var objectUrl = null;

        function setMeta(text)
        {
            metaEl.textContent = text;
        }

        function drawPreview()
        {
            if (!loadedImg)
            {
                if (boxEl)
                {
                    boxEl.hidden = true;
                }

                return;
            }

            var sourceTile = parseInt(sourceInput && sourceInput.value, 10) || 0;
            var outputTile = parseInt(outputInput && outputInput.value, 10) || 0;
            var w = loadedImg.naturalWidth;
            var h = loadedImg.naturalHeight;
            var maxCss = 480;
            var scale = Math.min(1, maxCss / Math.max(w, h));
            var drawW = Math.max(1, Math.round(w * scale));
            var drawH = Math.max(1, Math.round(h * scale));

            canvasEl.width = drawW;
            canvasEl.height = drawH;
            previewCtx.imageSmoothingEnabled = false;
            previewCtx.clearRect(0, 0, drawW, drawH);
            previewCtx.drawImage(loadedImg, 0, 0, drawW, drawH);

            if (sourceTile >= 8 && w % sourceTile === 0 && h % sourceTile === 0)
            {
                var cols = w / sourceTile;
                var rows = h / sourceTile;
                var cell = sourceTile * scale;

                previewCtx.strokeStyle = 'rgba(77,171,247,0.75)';
                previewCtx.lineWidth = 1;

                for (var c = 0; c <= cols; c++)
                {
                    var x = Math.round(c * cell) + 0.5;
                    previewCtx.beginPath();
                    previewCtx.moveTo(x, 0);
                    previewCtx.lineTo(x, drawH);
                    previewCtx.stroke();
                }

                for (var r = 0; r <= rows; r++)
                {
                    var y = Math.round(r * cell) + 0.5;
                    previewCtx.beginPath();
                    previewCtx.moveTo(0, y);
                    previewCtx.lineTo(drawW, y);
                    previewCtx.stroke();
                }

                setMeta(
                    'Sheet ' + w + '×' + h + ' → ' + cols + '×' + rows + ' cells ('
                    + (cols * rows) + ' max). Cut at ' + sourceTile + 'px'
                    + (outputTile ? ', save as ' + outputTile + 'px' : '') + '.'
                );
            }
            else if (sourceTile >= 8)
            {
                setMeta(
                    'Sheet ' + w + '×' + h + ' is not divisible by source_tile_px='
                    + sourceTile + '. Adjust the cell size.'
                );
            }
            else
            {
                setMeta('Sheet ' + w + '×' + h + '. Set a valid source_tile_px.');
            }

            if (boxEl)
            {
                boxEl.hidden = false;
            }

            if (sheetWInput && (!sheetWInput.value || sheetWInput.value === '0'))
            {
                /* leave 0 = auto; do not overwrite user intent */
            }
        }

        function loadFile(file)
        {
            if (!file)
            {
                loadedImg = null;

                if (objectUrl)
                {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }

                drawPreview();
                setMeta('Pick a PNG to preview the cut grid.');
                return;
            }

            if (objectUrl)
            {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function ()
            {
                loadedImg = img;

                if (sheetWInput && (sheetWInput.value === '' || sheetWInput.value === '0'))
                {
                    sheetWInput.value = String(img.naturalWidth);
                }

                if (sheetHInput && (sheetHInput.value === '' || sheetHInput.value === '0'))
                {
                    sheetHInput.value = String(img.naturalHeight);
                }

                drawPreview();
            };
            img.onerror = function ()
            {
                loadedImg = null;
                setMeta('Could not preview this file (must be a PNG).');
            };
            img.src = objectUrl;
        }

        fileInput.addEventListener('change', function ()
        {
            var file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            loadFile(file);

            if (file && sheetWInput && sheetHInput)
            {
                // Helpful defaults for the common 2048 pack case: fill expected
                // size once the image loads via drawPreview's naturalWidth path
                // only when fields are still 0 — set after load in onload.
            }
        });

        [sourceInput, outputInput].forEach(function (el)
        {
            if (el)
            {
                el.addEventListener('input', drawPreview);
                el.addEventListener('change', drawPreview);
            }
        });

        if (formEl)
        {
            formEl.addEventListener('submit', function (e)
            {
                if (!fileInput.files || !fileInput.files.length)
                {
                    e.preventDefault();
                    setMeta('Choose a PNG tileset first.');
                    return;
                }

                var sourceTile = parseInt(sourceInput && sourceInput.value, 10) || 0;

                if (loadedImg && sourceTile >= 8)
                {
                    var w = loadedImg.naturalWidth;
                    var h = loadedImg.naturalHeight;

                    if (w % sourceTile !== 0 || h % sourceTile !== 0)
                    {
                        e.preventDefault();
                        setMeta('Cannot import: sheet not divisible by source_tile_px.');
                        return;
                    }

                    if ((w / sourceTile) * (h / sourceTile) > 512)
                    {
                        e.preventDefault();
                        setMeta('Cannot import: more than 512 cells (max safety limit).');
                    }
                }
            });
        }
    }

    /**
     * Floating collision-mask editor for the currently selected single palette tile.
     */
    function initFloatingMaskEditor()
    {
        var panel = document.getElementById('dwt-mask-float');
        var header = document.getElementById('dwt-mask-float-header');
        var titleEl = document.getElementById('dwt-mask-float-title');
        var closeBtn = document.getElementById('dwt-mask-float-close');
        var editBtn = document.getElementById('dwt-edit-mask-btn');
        var canvasEl = document.getElementById('dwt-float-mask-canvas');
        var clearBtn = document.getElementById('dwt-float-mask-clear');
        var saveBtn = document.getElementById('dwt-float-mask-save');
        var statusEl = document.getElementById('dwt-mask-float-status');

        if (!panel || !canvasEl || !editBtn)
        {
            return;
        }

        var ctx = canvasEl.getContext('2d');
        var maskSize = parseInt(bootstrap.maskSize, 10) || 8;
        var cellPx = canvasEl.width / maskSize;
        var editingId = 0;
        var mask = [];
        var previewImg = null;
        var previewLoaded = false;
        var painting = false;
        var paintValue = '0';

        function setStatus(msg, isError)
        {
            if (!statusEl)
            {
                return;
            }

            statusEl.textContent = msg || '';
            statusEl.style.color = isError ? '#f1aeb5' : '#9fb2c3';
        }

        function normalizeMask(raw)
        {
            var expected = maskSize * maskSize;

            if (raw && raw.length === expected && /^[01]+$/.test(raw))
            {
                return raw.split('');
            }

            var arr = [];
            var i;

            for (i = 0; i < expected; i++)
            {
                arr.push('1');
            }

            return arr;
        }

        function maskPayload()
        {
            return (mask.indexOf('0') === -1) ? '' : mask.join('');
        }

        function draw()
        {
            ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

            if (previewLoaded && previewImg)
            {
                ctx.drawImage(previewImg, 0, 0, canvasEl.width, canvasEl.height);
            }
            else
            {
                ctx.fillStyle = '#111a26';
                ctx.fillRect(0, 0, canvasEl.width, canvasEl.height);
            }

            var r;
            var c;

            for (r = 0; r < maskSize; r++)
            {
                for (c = 0; c < maskSize; c++)
                {
                    var idx = r * maskSize + c;
                    var px = c * cellPx;
                    var py = r * cellPx;

                    if (mask[idx] === '0')
                    {
                        ctx.fillStyle = 'rgba(220,53,69,0.55)';
                        ctx.fillRect(px, py, cellPx, cellPx);
                    }

                    ctx.strokeStyle = 'rgba(255,255,255,0.25)';
                    ctx.lineWidth = 1;
                    ctx.strokeRect(px + 0.5, py + 0.5, cellPx - 1, cellPx - 1);
                }
            }
        }

        function loadPreview(imageFile)
        {
            previewLoaded = false;

            if (!imageFile)
            {
                draw();
                return;
            }

            previewImg = new Image();
            previewImg.onload = function ()
            {
                previewLoaded = true;
                draw();
            };
            previewImg.onerror = function ()
            {
                previewLoaded = false;
                draw();
            };
            previewImg.src = imgBase + imageFile;
        }

        function cellIndexFromEvent(e)
        {
            var rect = canvasEl.getBoundingClientRect();
            var scaleX = canvasEl.width / rect.width;
            var scaleY = canvasEl.height / rect.height;
            var x = (e.clientX - rect.left) * scaleX;
            var y = (e.clientY - rect.top) * scaleY;
            var c = Math.min(maskSize - 1, Math.max(0, Math.floor(x / cellPx)));
            var r = Math.min(maskSize - 1, Math.max(0, Math.floor(y / cellPx)));

            return r * maskSize + c;
        }

        function paintAt(e)
        {
            mask[cellIndexFromEvent(e)] = paintValue;
            draw();
        }

        function openForTile(idTileDefinition)
        {
            var def = definitionsById[idTileDefinition];

            if (!def)
            {
                setStatus('Tile definition not found.', true);
                return;
            }

            editingId = idTileDefinition;
            mask = normalizeMask(def.collision_mask || '');

            if (titleEl)
            {
                titleEl.textContent = 'Mask · ' + (def.code || ('#' + idTileDefinition));
            }

            loadPreview(def.image_file || '');
            panel.classList.add('is-open');
            setStatus(def.collision_mask ? 'Loaded existing mask. Paint red = blocked.' : 'No mask yet. Paint blocked cells, then Save.');
            draw();
        }

        function closePanel()
        {
            panel.classList.remove('is-open');
            editingId = 0;
            painting = false;
        }

        editBtn.addEventListener('click', function ()
        {
            if (brushIsEraser || selectedBrushIds.length !== 1)
            {
                return;
            }

            openForTile(selectedBrushIds[0]);
        });

        if (closeBtn)
        {
            closeBtn.addEventListener('click', closePanel);
        }

        if (clearBtn)
        {
            clearBtn.addEventListener('click', function ()
            {
                mask = normalizeMask('');
                draw();
                setStatus('Mask cleared (will save as no mask / whole-tile is_walkable).');
            });
        }

        if (saveBtn)
        {
            saveBtn.addEventListener('click', function ()
            {
                if (editingId <= 0)
                {
                    return;
                }

                saveBtn.disabled = true;
                setStatus('Saving…');

                postAction({
                    action: 'save_tile_collision_mask',
                    id_tile_definition: editingId,
                    collision_mask: maskPayload()
                }).then(function (result)
                {
                    saveBtn.disabled = false;

                    if (!result || !result.ok)
                    {
                        setStatus((result && result.message) || 'Save failed.', true);
                        return;
                    }

                    var savedMask = (result.collision_mask === null || result.collision_mask === undefined)
                        ? ''
                        : String(result.collision_mask);

                    if (definitionsById[editingId])
                    {
                        definitionsById[editingId].collision_mask = savedMask || null;
                    }

                    setStatus(result.message || 'Saved.');
                    updatePalettePreview();
                }).catch(function ()
                {
                    saveBtn.disabled = false;
                    setStatus('Save failed (network).', true);
                });
            });
        }

        canvasEl.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0 || editingId <= 0)
            {
                return;
            }

            var idx = cellIndexFromEvent(e);
            paintValue = (mask[idx] === '0') ? '1' : '0';
            painting = true;
            paintAt(e);
            e.preventDefault();
        });

        canvasEl.addEventListener('mousemove', function (e)
        {
            if (painting)
            {
                paintAt(e);
            }
        });

        window.addEventListener('mouseup', function ()
        {
            painting = false;
        });

        // Draggable panel (header).
        if (header)
        {
            var dragging = false;
            var startX = 0;
            var startY = 0;
            var originLeft = 0;
            var originTop = 0;

            header.addEventListener('mousedown', function (e)
            {
                if (e.button !== 0)
                {
                    return;
                }

                if (e.target && e.target.closest && e.target.closest('button'))
                {
                    return;
                }

                var rect = panel.getBoundingClientRect();
                dragging = true;
                panel.classList.add('is-dragging');
                startX = e.clientX;
                startY = e.clientY;
                originLeft = rect.left;
                originTop = rect.top;
                panel.style.left = originLeft + 'px';
                panel.style.top = originTop + 'px';
                panel.style.right = 'auto';
                e.preventDefault();
            });

            window.addEventListener('mousemove', function (e)
            {
                if (!dragging)
                {
                    return;
                }

                var left = originLeft + (e.clientX - startX);
                var top = originTop + (e.clientY - startY);
                var maxLeft = Math.max(8, window.innerWidth - panel.offsetWidth - 8);
                var maxTop = Math.max(8, window.innerHeight - 48);
                panel.style.left = Math.min(maxLeft, Math.max(8, left)) + 'px';
                panel.style.top = Math.min(maxTop, Math.max(8, top)) + 'px';
            });

            window.addEventListener('mouseup', function ()
            {
                if (!dragging)
                {
                    return;
                }

                dragging = false;
                panel.classList.remove('is-dragging');
            });
        }
    }

    var previewMaskToggle = document.getElementById('dwt-preview-mask-toggle');

    if (previewMaskToggle)
    {
        previewMaskToggle.addEventListener('change', function ()
        {
            refreshPreviewMaskOverlay();
        });
    }

    var previewWalkableCheckbox = document.getElementById('dwt-preview-is-walkable');

    if (previewWalkableCheckbox)
    {
        previewWalkableCheckbox.addEventListener('change', function ()
        {
            if (brushIsEraser || !selectedBrushIds.length)
            {
                syncWalkableCheckbox();
                return;
            }

            if (!confirm('sei sicuro'))
            {
                syncWalkableCheckbox();
                return;
            }

            var nextValue = previewWalkableCheckbox.checked ? 'S' : 'N';
            var ids = selectedBrushIds.slice();

            previewWalkableCheckbox.disabled = true;
            setStatus('Saving is_walkable…');

            postAction({
                action: 'save_tile_is_walkable',
                id_tile_definitions: JSON.stringify(ids),
                is_walkable: nextValue
            }).then(function (result)
            {
                previewWalkableCheckbox.disabled = false;

                if (!result || !result.ok)
                {
                    setStatus((result && result.message) || 'Save failed.', true);
                    syncWalkableCheckbox();
                    return;
                }

                var i;

                for (i = 0; i < ids.length; i++)
                {
                    if (definitionsById[ids[i]])
                    {
                        definitionsById[ids[i]].is_walkable = nextValue;
                    }
                }

                setStatus(result.message || 'is_walkable saved.');
                updatePalettePreview();
                render();
            }).catch(function ()
            {
                previewWalkableCheckbox.disabled = false;
                setStatus('Save failed (network).', true);
                syncWalkableCheckbox();
            });
        });
    }

    var previewLayerSelect = document.getElementById('dwt-preview-id-tile-layer');

    if (previewLayerSelect)
    {
        previewLayerSelect.addEventListener('change', function ()
        {
            if (brushIsEraser || !selectedBrushIds.length)
            {
                syncLayerSelect();
                return;
            }

            if (previewLayerSelect.value === '__mixed__')
            {
                syncLayerSelect();
                return;
            }

            if (!confirm('sei sicuro'))
            {
                syncLayerSelect();
                return;
            }

            var nextLayerId = parseInt(previewLayerSelect.value, 10) || 0;
            var ids = selectedBrushIds.slice();

            previewLayerSelect.disabled = true;
            setStatus('Saving layer…');

            postAction({
                action: 'save_tile_id_tile_layer',
                id_tile_definitions: JSON.stringify(ids),
                id_tile_layer: nextLayerId
            }).then(function (result)
            {
                previewLayerSelect.disabled = false;

                if (!result || !result.ok)
                {
                    setStatus((result && result.message) || 'Save failed.', true);
                    syncLayerSelect();
                    return;
                }

                var stored = (result.id_tile_layer === null || result.id_tile_layer === undefined)
                    ? null
                    : (parseInt(result.id_tile_layer, 10) || null);
                var i;

                for (i = 0; i < ids.length; i++)
                {
                    if (definitionsById[ids[i]])
                    {
                        definitionsById[ids[i]].id_tile_layer = stored;
                    }
                }

                applyLayerToPaletteButtons(ids, nextLayerId);
                setStatus(result.message || 'layer saved.');
                updatePalettePreview();
                refreshPaletteFilter();
            }).catch(function ()
            {
                previewLayerSelect.disabled = false;
                setStatus('Save failed (network).', true);
                syncLayerSelect();
            });
        });
    }

    initMaskPainter();
    initFloatingMaskEditor();
    initTilesetImportPreview();
})();
