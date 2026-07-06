/**
 * Canvas world rendering, WASD + click-hold mouse movement, entity display.
 */ 
var AnimasterWorld = (function ()
{
    var SCALE = 4;
    var GRID_STEP = 10;
    var INTERACT_RADIUS = 10;
    var NPC_TALK_RADIUS = 8;
    var SHOW_SPAWN_DEBUG = true;

    var canvas = null;
    var ctx = null;
    var keys = {};
    var holdMoveActive = false;
    var holdTargetWorld = null;
    var player = null;
    var others = [];
    var wilds = [];
    var npcs = [];
    var onWildClick = null;
    var onEntityClick = null;
    var nearbyNpcLogLastAt = 0;
    var wasNearWild = false;

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    

    function init(canvasEl, playerState, wildClickCallback, entityClickCallback)
    {
        canvas = canvasEl;
        ctx = canvas.getContext('2d');
        player = playerState;
        onWildClick = wildClickCallback;
        onEntityClick = entityClickCallback;

        canvas.addEventListener('click', onCanvasClick);
        canvas.addEventListener('mousedown', onCanvasMouseDown);

        window.addEventListener('keydown', function (e)
        {
            keys[e.code] = true;

            if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Space'].indexOf(e.code) !== -1)
            {
                e.preventDefault();
            }
        });

        window.addEventListener('keyup', function (e)
        {
            keys[e.code] = false;
        });
    }

    function setPlayer(state)
    {
        player = state;
    }

    function getPlayer()
    {
        return player;
    }

    function setOthers(list)
    {
        others = list || [];
    }

    function setWilds(list)
    {
        wilds = list || [];
    }

    function setNpcs(list)
    {
        npcs = list || [];
    }

    function worldToScreen(wx, wz)
    {
        return {
            x: (wx - player.x) * SCALE + canvas.width * 0.5,
            y: (wz - player.z) * SCALE + canvas.height * 0.5
        };
    }

    function screenToWorld(sx, sy)
    {
        return {
            x: (sx - canvas.width * 0.5) / SCALE + player.x,
            z: (sy - canvas.height * 0.5) / SCALE + player.z
        };
    }

    function distance(x1, z1, x2, z2)
    {
        var dx = x1 - x2;
        var dz = z1 - z2;
        return Math.sqrt(dx * dx + dz * dz);
    }

    function getNearbyWild(maxRadius)
    {
        var radius = typeof maxRadius === 'number' ? maxRadius : INTERACT_RADIUS;

        if (!player || !wilds.length)
        {
            return null;
        }

        var closest = null;
        var closestDist = radius;

        wilds.forEach(function (wild)
        {
            var pos = entityWorldPos(wild, true);

            if (!pos)
            {
                return;
            }

            var d = distance(player.x, player.z, pos.x, pos.z);

            if (d <= closestDist)
            {
                closestDist = d;
                closest = wild;
            }
        });

        return closest;
    }

    function tickWildEncounter()
    {
        var nearby = getNearbyWild();

        if (!nearby)
        {
            wasNearWild = false;
            return;
        }

        if (!wasNearWild && onWildClick)
        {
            onWildClick(nearby);
        }

        wasNearWild = true;
    }

    function resetWildEncounter()
    {
        wasNearWild = !!getNearbyWild();
    }

    function canvasPointerPos(e)
    {
        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;

        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    function entityHitAt(screenX, screenY, wx, wz)
    {
        var p = worldToScreen(wx, wz);
        var dx = screenX - p.x;
        var dy = screenY - p.y;
        var bodyR = 20;

        if ((dx * dx) + (dy * dy) <= bodyR * bodyR)
        {
            return true;
        }

        if (dy >= -40 && dy <= -6 && Math.abs(dx) <= 70)
        {
            return true;
        }

        return false;
    }

    function wildDisplayName(wild)
    {
        var species = String(wild.species || t('world.wild_fallback', { id: wild.id_species || '?' }));
        var lvl = parseInt(wild.level, 10);

        if (isNaN(lvl))
        {
            return species;
        }

        return species + ' [' + lvl + ']';
    }

    function buildTargetInfo(kind, entity)
    {
        if (kind === 'self')
        {
            return {
                kind: 'self',
                name: player.display_name || t('hud.default_you'),
                typeLabel: entity.character_type || t('target.type_self'),
                ref: entity
            };
        }

        if (kind === 'player')
        {
            return {
                kind: 'player',
                id: entity.id_player,
                name: entity.displayName || t('world.other_player_fallback'),
                typeLabel: entity.character_type || t('target.type_player'),
                ref: entity
            };
        }

        if (kind === 'npc')
        {
            return {
                kind: 'npc',
                id: entity.id_npc,
                name: entity.npc || t('dialog.npc_fallback'),
                typeLabel: entity.type || t('target.type_npc'),
                ref: entity
            };
        }

        if (kind === 'wild')
        {
            return {
                kind: 'wild',
                id: entity.id_wild_animal,
                name: wildDisplayName(entity),
                typeLabel: String(entity.element || '').trim() || t('target.type_wild'),
                ref: entity
            };
        }

        return null;
    }

    function pickEntityAtCanvas(screenX, screenY)
    {
        if (!player)
        {
            return null;
        }

        if (entityHitAt(screenX, screenY, player.x, player.z))
        {
            return buildTargetInfo('self', player);
        }

        var i;
        var other;
        var ox;
        var oz;

        for (i = 0; i < others.length; i++)
        {
            other = others[i];
            ox = parseFloat(other.serverPositionX);
            oz = parseFloat(other.serverPositionZ);

            if (!isFinite(ox) || !isFinite(oz))
            {
                continue;
            }

            if (entityHitAt(screenX, screenY, ox, oz))
            {
                return buildTargetInfo('player', other);
            }
        }

        var wild;
        var pos;

        for (i = 0; i < wilds.length; i++)
        {
            wild = wilds[i];
            pos = entityWorldPos(wild, true);

            if (!pos)
            {
                continue;
            }

            if (entityHitAt(screenX, screenY, pos.x, pos.z))
            {
                return buildTargetInfo('wild', wild);
            }
        }

        var npc;
        var npos;

        for (i = 0; i < npcs.length; i++)
        {
            npc = npcs[i];
            npos = entityWorldPos(npc, false);

            if (!npos)
            {
                continue;
            }

            if (entityHitAt(screenX, screenY, npos.x, npos.z))
            {
                return buildTargetInfo('npc', npc);
            }
        }

        return null;
    }

    function onCanvasMouseDown(e)
    {
        if (!player || !canvas || e.button !== 0)
        {
            return;
        }

        var pos = canvasPointerPos(e);

        if (pickEntityAtCanvas(pos.x, pos.y))
        {
            return;
        }

        e.preventDefault();
        beginHoldMove(pos.x, pos.y);
    }

    function updateHoldTargetFromCanvasPos(canvasX, canvasY)
    {
        if (!player)
        {
            return;
        }

        var world = screenToWorld(canvasX, canvasY);
        holdTargetWorld = {
            x: world.x,
            z: world.z
        };
    }

    function onHoldMove(e)
    {
        if (!holdMoveActive || !canvas)
        {
            return;
        }

        var rect = canvas.getBoundingClientRect();
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        var canvasX = (e.clientX - rect.left) * scaleX;
        var canvasY = (e.clientY - rect.top) * scaleY;

        updateHoldTargetFromCanvasPos(canvasX, canvasY);
    }

    function beginHoldMove(canvasX, canvasY)
    {
        holdMoveActive = true;
        updateHoldTargetFromCanvasPos(canvasX, canvasY);
        window.addEventListener('mousemove', onHoldMove);
        window.addEventListener('mouseup', endHoldMove);
    }

    function endHoldMove()
    {
        holdMoveActive = false;
        holdTargetWorld = null;
        window.removeEventListener('mousemove', onHoldMove);
        window.removeEventListener('mouseup', endHoldMove);
    }

    function keyboardStep(dt)
    {
        var step = (parseFloat(player.move_speed) || 5) * dt * 0.35;
        var dirX = 0;
        var dirZ = 0;

        if (keys['KeyW'] || keys['ArrowUp'])
        {
            dirZ -= 1;
        }

        if (keys['KeyS'] || keys['ArrowDown'])
        {
            dirZ += 1;
        }

        if (keys['KeyA'] || keys['ArrowLeft'])
        {
            dirX -= 1;
        }

        if (keys['KeyD'] || keys['ArrowRight'])
        {
            dirX += 1;
        }

        if (dirX === 0 && dirZ === 0)
        {
            return { dx: 0, dz: 0 };
        }

        var len = Math.sqrt((dirX * dirX) + (dirZ * dirZ));

        return {
            dx: (dirX / len) * step,
            dz: (dirZ / len) * step
        };
    }

    function holdStep(dt)
    {
        if (!holdMoveActive || !holdTargetWorld)
        {
            return { dx: 0, dz: 0 };
        }

        var step = (parseFloat(player.move_speed) || 5) * dt * 0.35;
        var toX = holdTargetWorld.x - player.x;
        var toZ = holdTargetWorld.z - player.z;
        var dist = Math.sqrt((toX * toX) + (toZ * toZ));

        if (dist < 0.05)
        {
            return { dx: 0, dz: 0 };
        }

        if (step >= dist)
        {
            return { dx: toX, dz: toZ };
        }

        return {
            dx: (toX / dist) * step,
            dz: (toZ / dist) * step
        };
    }

    function cardinalToAngle(direction)
    {
        if (direction === 'U')
        {
            return -Math.PI / 2;
        }

        if (direction === 'D')
        {
            return Math.PI / 2;
        }

        if (direction === 'L')
        {
            return Math.PI;
        }

        return 0;
    }

    function applyMovement(dx, dz)
    {
        if (dx === 0 && dz === 0)
        {
            return;
        }

        player.x += dx;
        player.z += dz;
        player.facingAngle = Math.atan2(dz, dx);

        if (Math.abs(dx) >= Math.abs(dz))
        {
            player.direction = dx < 0 ? 'L' : 'R';
        }
        else
        {
            player.direction = dz < 0 ? 'U' : 'D';
        }
    }

    function onCanvasClick(e)
    {
        if (!onEntityClick || !player || !canvas)
        {
            return;
        }

        var pos = canvasPointerPos(e);
        var target = pickEntityAtCanvas(pos.x, pos.y);

        if (target)
        {
            onEntityClick(target);
        }
    }

    function updateMovement(dt)
    {
        if (!player)
        {
            return;
        }

        var keyboard = keyboardStep(dt);
        var useKeyboard = keyboard.dx !== 0 || keyboard.dz !== 0;

        if (useKeyboard)
        {
            applyMovement(keyboard.dx, keyboard.dz);
            return;
        }

        var hold = holdStep(dt);
        applyMovement(hold.dx, hold.dz);
    }

    function drawGrid()
    {
        var halfW = canvas.width * 0.5;
        var halfH = canvas.height * 0.5;
        var startX = Math.floor((player.x - halfW / SCALE) / GRID_STEP) * GRID_STEP;
        var endX = Math.ceil((player.x + halfW / SCALE) / GRID_STEP) * GRID_STEP;
        var startZ = Math.floor((player.z - halfH / SCALE) / GRID_STEP) * GRID_STEP;
        var endZ = Math.ceil((player.z + halfH / SCALE) / GRID_STEP) * GRID_STEP;

        ctx.strokeStyle = 'rgba(255,255,255,0.06)';
        ctx.lineWidth = 1;

        for (var gx = startX; gx <= endX; gx += GRID_STEP)
        {
            var p1 = worldToScreen(gx, startZ);
            var p2 = worldToScreen(gx, endZ);
            ctx.beginPath();
            ctx.moveTo(p1.x, p1.y);
            ctx.lineTo(p2.x, p2.y);
            ctx.stroke();
        }

        for (var gz = startZ; gz <= endZ; gz += GRID_STEP)
        {
            var q1 = worldToScreen(startX, gz);
            var q2 = worldToScreen(endX, gz);
            ctx.beginPath();
            ctx.moveTo(q1.x, q1.y);
            ctx.lineTo(q2.x, q2.y);
            ctx.stroke();
        }
    }

    function drawCircle(wx, wz, radius, fill, stroke)
    {
        var p = worldToScreen(wx, wz);
        ctx.beginPath();
        ctx.arc(p.x, p.y, radius, 0, Math.PI * 2);
        ctx.fillStyle = fill;
        ctx.fill();

        if (stroke)
        {
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2;
            ctx.stroke();
        }
    }

    function drawWildMarker(wx, wz, wild, near)
    {
        var p = worldToScreen(wx, wz);
        var pixelSize = near
            ? (AnimasterWildSprites.nearPixelSize || 3)
            : (AnimasterWildSprites.defaultPixelSize || 2);

        if (typeof AnimasterWildSprites !== 'undefined')
        {
            AnimasterWildSprites.draw(ctx, p.x, p.y, wild, {
                pixelSize: pixelSize,
                elementColor: wildElementColor(wild),
                near: near
            });
            return;
        }

        drawTriangle(wx, wz, near ? 9 : 7, wildElementColor(wild));
    }

    function drawTriangle(wx, wz, size, fill)
    {
        var p = worldToScreen(wx, wz);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y - size);
        ctx.lineTo(p.x - size, p.y + size);
        ctx.lineTo(p.x + size, p.y + size);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();
    }

    function drawSquare(wx, wz, size, fill)
    {
        var p = worldToScreen(wx, wz);
        ctx.fillStyle = fill;
        ctx.fillRect(p.x - size, p.y - size, size * 2, size * 2);
    }

    function drawDirectionArrow(wx, wz, angleRadians)
    {
        var p = worldToScreen(wx, wz);
        var len = 12;
        var dx = Math.cos(angleRadians) * len;
        var dy = Math.sin(angleRadians) * len;

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        ctx.lineTo(p.x + dx, p.y + dy);
        ctx.stroke();
    }

    function drawPartyFarArrow(wx, wz, bearingDeg)
    {
        var p = worldToScreen(wx, wz);
        var bearingRad = bearingDeg * Math.PI / 180;
        var orbit = 17;
        var size = 6;
        var cx = p.x + Math.sin(bearingRad) * orbit;
        var cy = p.y - Math.cos(bearingRad) * orbit;

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(bearingRad);
        ctx.beginPath();
        ctx.moveTo(0, -size);
        ctx.lineTo(-size * 0.65, size * 0.55);
        ctx.lineTo(size * 0.65, size * 0.55);
        ctx.closePath();
        ctx.fillStyle = '#f1c40f';
        ctx.strokeStyle = 'rgba(0,0,0,0.45)';
        ctx.lineWidth = 1;
        ctx.fill();
        ctx.stroke();
        ctx.restore();
    }

    function drawLabel(wx, wz, text, subtext, showFarWarning)
    {
        var p = worldToScreen(wx, wz);
        var y = p.y - 14;

        ctx.textAlign = 'center';
        ctx.font = '11px Segoe UI, sans-serif';

        if (showFarWarning)
        {
            var textWidth = ctx.measureText(text).width;

            ctx.fillStyle = '#e74c3c';
            ctx.font = 'bold 12px Segoe UI, sans-serif';
            ctx.fillText('!', p.x - (textWidth / 2) - 7, y);
        }

        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        ctx.font = '11px Segoe UI, sans-serif';
        ctx.fillText(text, p.x, y);

        if (subtext)
        {
            ctx.fillStyle = 'rgba(255,255,255,0.65)';
            ctx.font = '10px Segoe UI, sans-serif';
            ctx.fillText(subtext, p.x, p.y - 2);
        }
    }

    function wildElementColor(wild)
    {
        var color = String(wild.element_color || '').trim();

        if (!color)
        {
            return '#888888';
        }

        if (/^#[0-9a-fA-F]{3,8}$/.test(color))
        {
            return color;
        }

        if (/^[0-9a-fA-F]{3,8}$/.test(color))
        {
            return '#' + color;
        }

        return color;
    }

    function entityWorldPos(entity, isWild)
    {
        var x = parseFloat(isWild ? entity.pos_x : entity.posx);
        var z = parseFloat(isWild ? entity.pos_z : entity.posz);

        if (!isFinite(x) || !isFinite(z))
        {
            return null;
        }

        return { x: x, z: z };
    }

    function drawWildLabel(wx, wz, wild)
    {
        var p = worldToScreen(wx, wz);
        var species = String(wild.species || t('world.wild_fallback', { id: wild.id_species || '?' }));
        var lvl = parseInt(wild.level, 10);
        var levelPart = isNaN(lvl) ? '' : ' [' + lvl + ']';
        var elementColor = wildElementColor(wild);
        var circleRadius = 4;
        var gap = 3;
        var y = p.y - 14;

        ctx.font = '11px Segoe UI, sans-serif';
        ctx.textBaseline = 'alphabetic';
        ctx.textAlign = 'left';

        var speciesWidth = ctx.measureText(species).width;
        var levelWidth = levelPart ? ctx.measureText(levelPart).width : 0;
        var totalWidth = circleRadius * 2 + gap + speciesWidth + levelWidth;
        var startX = p.x - totalWidth / 2;
        var textX = startX + circleRadius * 2 + gap;

        ctx.beginPath();
        ctx.arc(startX + circleRadius, y - 3, circleRadius, 0, Math.PI * 2);
        ctx.fillStyle = elementColor;
        ctx.fill();
        ctx.strokeStyle = 'rgba(0,0,0,0.35)';
        ctx.lineWidth = 1;
        ctx.stroke();

        ctx.fillStyle = elementColor;
        ctx.fillText(species, textX, y);

        if (levelPart)
        {
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            ctx.fillText(levelPart, textX + speciesWidth, y);
        }
    }

    function drawSpawnZone(wx, wz, radiusWorld, label)
    {
        var p = worldToScreen(wx, wz);
        var screenRadius = radiusWorld * SCALE;

        ctx.beginPath();
        ctx.arc(p.x, p.y, screenRadius, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(155, 89, 182, 0.28)';
        ctx.lineWidth = 1;
        ctx.setLineDash([5, 7]);
        ctx.stroke();
        ctx.setLineDash([]);

        ctx.strokeStyle = 'rgba(155, 89, 182, 0.45)';
        ctx.beginPath();
        ctx.moveTo(p.x - 5, p.y);
        ctx.lineTo(p.x + 5, p.y);
        ctx.moveTo(p.x, p.y - 5);
        ctx.lineTo(p.x, p.y + 5);
        ctx.stroke();

        if (label)
        {
            ctx.fillStyle = 'rgba(155, 89, 182, 0.6)';
            ctx.font = '10px Segoe UI, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(label, p.x, p.y + 3);
        }
    }

    function drawSpawnPoints()
    {
        if (!SHOW_SPAWN_DEBUG || typeof AnimasterSpawn === 'undefined')
        {
            return;
        }

        AnimasterSpawn.getSpawnPoints().forEach(function (spawnPoint)
        {
            var sx = parseFloat(spawnPoint.x);
            var sz = parseFloat(spawnPoint.z);
            var radius = parseFloat(spawnPoint.radius) || 0;

            if (radius <= 0)
            {
                return;
            }

            drawSpawnZone(
                sx,
                sz,
                radius,
                'SP ' + (spawnPoint.id_spawn_point || '?')
            );
        });
    }

    function render()
    {
        if (!ctx || !player)
        {
            return;
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        drawGrid();

        npcs.forEach(function (npc)
        {
            var pos = entityWorldPos(npc, false);

            if (!pos)
            {
                return;
            }

            drawSquare(pos.x, pos.z, 7, '#f1c40f');
            drawLabel(pos.x, pos.z, npc.npc || t('dialog.npc_fallback'));
        });

        wilds.forEach(function (wild)
        {
            var pos = entityWorldPos(wild, true);

            if (!pos)
            {
                return;
            }

            var near = distance(player.x, player.z, pos.x, pos.z) <= INTERACT_RADIUS;
            drawWildMarker(pos.x, pos.z, wild, near);
            drawWildLabel(pos.x, pos.z, wild);
        });

        others.forEach(function (other)
        {
            var ox = parseFloat(other.serverPositionX);
            var oz = parseFloat(other.serverPositionZ);

            if (!isFinite(ox) || !isFinite(oz))
            {
                return;
            }

            drawCircle(ox, oz, 8, '#4488ff', '#2266cc');

            var otherName = other.displayName || t('world.other_player_fallback');
            var showFarWarning = typeof AnimasterParty !== 'undefined'
                && AnimasterParty.isPlayerFarFromParty(parseInt(other.id_player, 10));

            drawLabel(ox, oz, otherName, null, showFarWarning);
        });

        drawCircle(player.x, player.z, 9, '#2ecc71', '#1e8449');
        drawDirectionArrow(
            player.x,
            player.z,
            typeof player.facingAngle === 'number'
                ? player.facingAngle
                : cardinalToAngle(player.direction || 'D')
        );

        var selfFarFromParty = typeof AnimasterParty !== 'undefined'
            && typeof AnimasterParty.isFarFromAnyPartyMember === 'function'
            && AnimasterParty.isFarFromAnyPartyMember();

        drawLabel(player.x, player.z, player.display_name || t('hud.default_you'), null, selfFarFromParty);

        if (typeof AnimasterParty !== 'undefined' && typeof AnimasterParty.getFarPartyBearings === 'function')
        {
            AnimasterParty.getFarPartyBearings().forEach(function (far)
            {
                drawPartyFarArrow(player.x, player.z, far.bearingDeg);
            });
        }

        try
        {
            drawSpawnPoints();
        }
        catch (err)
        {
            console.warn('[AnimasterWorld] spawn debug draw failed:', err && err.message ? err.message : err);
        }
    }

    function getNearbyNpc(maxRadius)
    {
        var radius = typeof maxRadius === 'number' ? maxRadius : NPC_TALK_RADIUS;

        if (!player)
        {
            
            return null;
        }

        if (!npcs.length)
        {
            
            return null;
        }

        var closest = null;
        var closestDist = radius;
        var npcChecks = [];

        npcs.forEach(function (npc)
        {
            var nx = parseFloat(npc.posx);
            var ny = parseFloat(npc.posy);
            var nz = parseFloat(npc.posz);
            var d = distance(player.x, player.z, nx, nz);
            var inRange = d <= radius;

            npcChecks.push({
                id_npc: npc.id_npc,
                name: npc.npc,
                posx: nx,
                posy: ny,
                posz: nz,
                distance: Number(d.toFixed(3)),
                inRange: inRange
            });

            if (inRange && d <= closestDist)
            {
                closestDist = d;
                closest = npc;
            }
        });


        return closest;
    }

    function getNpcScreenPosition(npc)
    {
        if (!npc || !canvas)
        {
            return null;
        }

        var nx = parseFloat(npc.posx);
        var nz = parseFloat(npc.posz);
        var p = worldToScreen(nx, nz);
        var rect = canvas.getBoundingClientRect();
        var scaleX = rect.width / canvas.width;
        var scaleY = rect.height / canvas.height;

        return {
            x: rect.left + p.x * scaleX,
            y: rect.top + p.y * scaleY - 36
        };
    }

    function getOthers()
    {
        return others;
    }

    function getHudText()
    {
        var name = player.display_name || t('hud.default_player');
        var classLabel = player.player_class_name || '';

        if (classLabel)
        {
            name = name + ' · ' + classLabel;
        }

        return {
            player: t('hud.player_position', {
                name: name,
                zone: player.id_zone,
                x: player.x.toFixed(1),
                z: player.z.toFixed(1)
            }),
            status: t('hud.status_counts', {
                others: others.length,
                wilds: wilds.length,
                npcs: npcs.length
            })
        };
    }

    return {
        INTERACT_RADIUS: INTERACT_RADIUS,
        NPC_TALK_RADIUS: NPC_TALK_RADIUS,
        init: init,
        setPlayer: setPlayer,
        getPlayer: getPlayer,
        getOthers: getOthers,
        setOthers: setOthers,
        setWilds: setWilds,
        setNpcs: setNpcs,
        getNearbyNpc: getNearbyNpc,
        getNearbyWild: getNearbyWild,
        tickWildEncounter: tickWildEncounter,
        resetWildEncounter: resetWildEncounter,
        getNpcScreenPosition: getNpcScreenPosition,
        updateMovement: updateMovement,
        render: render,
        getHudText: getHudText,
        distance: distance
    };
})();
   