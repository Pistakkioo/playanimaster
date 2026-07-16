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
    var COLS = 18;
    var ROWS = 14;
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
    var originGx = -Math.floor(COLS / 2);
    var originGz = -Math.floor(ROWS / 2);
    var selectedBrush = null;
    var hoverCell = null;
    var painting = false;
    var previewingBefore = false;

    var placementsBefore = {};
    var placementsWorking = {};
    var dirtyKeys = {};

    var canvas = document.getElementById('dwt-canvas');
    var ctx = canvas ? canvas.getContext('2d') : null;
    var statusEl = document.getElementById('dwt-status');
    var coordsEl = document.getElementById('dwt-coords');
    var brushLabelEl = document.getElementById('dwt-brush-label');
    var dirtyCountEl = document.getElementById('dwt-dirty-count');
    var saveBtn = document.getElementById('dwt-save-btn');
    var revertBtn = document.getElementById('dwt-revert-btn');
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

    /**
     * Paints locally only (no network call) -- edits live in
     * placementsWorking until the Save button batches the diff.
     */
    function paintCell(gx, gz)
    {
        if (previewingBefore)
        {
            return;
        }

        if (!selectedBrush)
        {
            setStatus('Pick a tile (or Eraser) from the palette first.', true);
            return;
        }

        var key = cellLayerKey(gx, gz, activeLayerId);

        if (selectedBrush === 'ERASER')
        {
            delete placementsWorking[key];
        }
        else
        {
            placementsWorking[key] = selectedBrush;
        }

        var beforeVal = placementsBefore[key] || null;
        var workingVal = placementsWorking[key] || null;

        if (beforeVal === workingVal)
        {
            delete dirtyKeys[key];
        }
        else
        {
            dirtyKeys[key] = true;
        }

        updateDirtyCount();
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

    function selectBrush(brushId, label)
    {
        selectedBrush = brushId;

        if (brushLabelEl)
        {
            brushLabelEl.textContent = label;
        }

        var buttons = document.querySelectorAll('.dwt-palette-btn');

        for (var i = 0; i < buttons.length; i++)
        {
            buttons[i].classList.toggle('dwt-palette-selected', buttons[i].getAttribute('data-brush') === String(brushId));
        }
    }

    function refreshPaletteFilter()
    {
        var buttons = document.querySelectorAll('.dwt-palette-btn');

        for (var i = 0; i < buttons.length; i++)
        {
            var btn = buttons[i];
            var layerAttr = parseInt(btn.getAttribute('data-layer'), 10) || 0;
            var show = layerAttr === 0 || layerAttr === activeLayerId;
            btn.style.display = show ? '' : 'none';
        }
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

    function initPalette()
    {
        var buttons = document.querySelectorAll('.dwt-palette-btn');

        for (var i = 0; i < buttons.length; i++)
        {
            buttons[i].addEventListener('click', function (e)
            {
                var brush = e.currentTarget.getAttribute('data-brush');
                var label = e.currentTarget.getAttribute('data-label') || brush;
                selectBrush(brush === 'ERASER' ? 'ERASER' : parseInt(brush, 10), label);
            });
        }

        refreshPaletteFilter();
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
                    updateDirtyCount();
                    setStatus(result.message || 'Saved.', false);
                    render();
                });
            });
        }

        if (revertBtn)
        {
            revertBtn.addEventListener('click', function ()
            {
                placementsWorking = clonePlacements(placementsBefore);
                dirtyKeys = {};
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
            painting = true;
            var cell = canvasPosToCell(e.clientX, e.clientY);
            paintCell(cell.gx, cell.gz);
        });

        canvas.addEventListener('mousemove', function (e)
        {
            hoverCell = canvasPosToCell(e.clientX, e.clientY);

            if (coordsEl)
            {
                coordsEl.textContent = 'grid (' + hoverCell.gx + ', ' + hoverCell.gz + ')';
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

    initMaskPainter();
})();
