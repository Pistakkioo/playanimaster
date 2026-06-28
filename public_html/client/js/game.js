/**
 * Main game loop and UI state machine.
 */
(function ()
{
    var State = {
        WORLD: 'world',
        COMBAT: 'combat'
    };

    var state = State.WORLD;
    var player = null;
    var lastPresence = 0;
    var lastEntities = 0;
    var presenceBusy = false;
    var entitiesBusy = false;
    var lastFrame = 0;

    var canvas = document.getElementById('game-canvas');
    var canvasWrap = document.querySelector('.canvas-wrap');
    var hudEl = document.getElementById('hud');
    var hudFabToggle = document.getElementById('hud-fab-toggle');
    var hudPlayer = document.getElementById('hud-player');
    var hudStatus = document.getElementById('hud-status');
    var bootstrap = window.ANIMASTER_BOOTSTRAP;
    var HUD_VISIBLE_KEY = 'animaster_hud_visible';
    var hudVisible = false;

    if (!bootstrap || !bootstrap.profile)
    {
        window.location.href = 'login.php';
        return;
    }

    AnimasterCombat.init({
        onEnd: function ()
        {
            state = State.WORLD;
            AnimasterInventory.setToggleEnabled(true);
            AnimasterTeam.setToggleEnabled(true);
            AnimasterWorld.resetWildEncounter();
            syncWorldEntities(true);
            AnimasterNotifications.fetch();

            if (typeof AnimasterDuel !== 'undefined')
            {
                AnimasterDuel.resetCombatFlag();
            }
        },
        onBlackout: handleBlackout
    });

    AnimasterWorld.init(canvas, null, onWildClicked, onEntityTargeted);
    AnimasterSpawn.init({
        onSpawnChecked: function ()
        {
            syncWorldEntities(true);
        }
    });
    AnimasterDialog.init({
        onClose: onDialogClosed
    });

    AnimasterInventory.init();
    AnimasterTeam.init({
        lvlUpConstantAnimal: bootstrap.costanti
            ? bootstrap.costanti.lvl_up_constant_animal
            : 40
    });

    AnimasterNotifications.init();
    AnimasterPlayerChatBubbles.init();
    AnimasterChat.init();
    AnimasterTarget.init();
    AnimasterTrade.init();
    AnimasterDuel.init({
        onBattleStart: function (battleInfo)
        {
            if (!player || !battleInfo || !battleInfo.id_battle)
            {
                return;
            }

            AnimasterTarget.clear();
            state = State.COMBAT;
            AnimasterInventory.close();
            AnimasterTeam.close();
            AnimasterInventory.setToggleEnabled(false);
            AnimasterTeam.setToggleEnabled(false);
            AnimasterCombat.start(player, battleInfo);
        }
    });

    var dialogCloseBtn = document.getElementById('dialog-close');

    if (dialogCloseBtn)
    {
        dialogCloseBtn.addEventListener('click', function ()
        {
            AnimasterDialog.close();
            onDialogClosed();
        });
    }

    bindCharactersLink();
    bindChatEnterFocus();
    initHudToggle();
    initCanvasResize();
    enterWorld(bootstrap.profile, bootstrap.battle);

    function initCanvasResize()
    {
        if (!canvas || !canvasWrap)
        {
            return;
        }

        function resizeCanvas()
        {
            var width = canvasWrap.clientWidth;
            var height = canvasWrap.clientHeight;

            if (width <= 0 || height <= 0)
            {
                return;
            }

            if (canvas.width !== width || canvas.height !== height)
            {
                canvas.width = width;
                canvas.height = height;
            }
        }

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        if (typeof ResizeObserver !== 'undefined')
        {
            var observer = new ResizeObserver(resizeCanvas);
            observer.observe(canvasWrap);
        }
    }

    function setHudVisible(visible)
    {
        hudVisible = !!visible;

        if (hudEl)
        {
            hudEl.hidden = !hudVisible;
            hudEl.setAttribute('aria-hidden', hudVisible ? 'false' : 'true');
        }

        if (hudFabToggle)
        {
            hudFabToggle.classList.toggle('is-active', hudVisible);
            hudFabToggle.setAttribute('aria-pressed', hudVisible ? 'true' : 'false');

            var titleKey = hudVisible ? 'hud.toggle_hide' : 'hud.toggle_show';
            var label = AnimasterLang.t(titleKey);
            hudFabToggle.title = label;
            hudFabToggle.setAttribute('aria-label', label);
        }

        try
        {
            localStorage.setItem(HUD_VISIBLE_KEY, hudVisible ? '1' : '0');
        }
        catch (e)
        {
            // ignore storage errors
        }
    }

    function initHudToggle()
    {
        try
        {
            hudVisible = localStorage.getItem(HUD_VISIBLE_KEY) === '1';
        }
        catch (e)
        {
            hudVisible = false;
        }

        setHudVisible(hudVisible);

        if (hudFabToggle)
        {
            hudFabToggle.addEventListener('click', function ()
            {
                setHudVisible(!hudVisible);
            });
        }
    }

    function canFocusChatFromWorld(e)
    {
        if (!e || (e.code !== 'Enter' && e.key !== 'Enter'))
        {
            return false;
        }

        if (state !== State.WORLD || !player)
        {
            return false;
        }

        if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
        {
            return false;
        }

        if (AnimasterDialog.isActive())
        {
            return false;
        }

        if (AnimasterInventory.isOpen() || AnimasterTeam.isOpen())
        {
            return false;
        }

        if (AnimasterNotifications.isVisible())
        {
            return false;
        }

        if (typeof AnimasterChat !== 'undefined' && AnimasterChat.isInputFocused())
        {
            return false;
        }

        var tag = document.activeElement && document.activeElement.tagName;

        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'BUTTON')
        {
            return false;
        }

        return true;
    }

    function bindChatEnterFocus()
    {
        document.addEventListener('keydown', function (e)
        {
            if (!canFocusChatFromWorld(e))
            {
                return;
            }

            e.preventDefault();

            if (typeof AnimasterChat !== 'undefined')
            {
                AnimasterChat.focusInput();
            }
        });
    }

    function onDialogClosed()
    {
        syncWorldEntities(true);

        if (AnimasterInventory.isOpen())
        {
            AnimasterInventory.refresh();
        }

        if (AnimasterTeam.isOpen())
        {
            AnimasterTeam.refresh();
        }

        AnimasterNotifications.fetch();
    }

    function bindCharactersLink()
    {
        var charactersLink = document.getElementById('hud-characters');

        if (!charactersLink)
        {
            return;
        }

        charactersLink.addEventListener('click', function (event)
        {
            event.preventDefault();

            var target = charactersLink.getAttribute('href') || 'character_select.php?switch=1';

            AnimasterApi.markCharacterOffline().catch(function (err)
            {
                console.warn('[Animaster] mark offline failed:', err && err.message ? err.message : err);
            }).finally(function ()
            {
                window.location.href = target;
            });
        });
    }

    function enterWorld(profile, battleResume)
    {
        player = {
            id_user: parseInt(profile.id_user, 10) || 0,
            id_user_ig: parseInt(profile.id_user_ig, 10) || 0,
            id_zone: profile.id_zone,
            display_name: profile.display_name,
            character_type: profile.character_type || '',
            x: parseFloat(profile.position_x) || 0,
            y: parseFloat(profile.position_y) || 0,
            z: parseFloat(profile.position_z) || 0,
            direction: profile.direction || 'D',
            facingAngle: null,
            move_speed: parseFloat(profile.move_speed) || 5,
            level: profile.level,
            id_zone_last_recover: profile.id_zone_last_recover,
            position_x_last_recover: profile.position_x_last_recover,
            position_y_last_recover: profile.position_y_last_recover,
            position_z_last_recover: profile.position_z_last_recover
        };

        syncPlayerToModules();
        AnimasterSpawn.loadSpawnPoints().then(function ()
        {
            AnimasterSpawn.tick(true);
        });
        AnimasterInventory.setToggleEnabled(true);
        AnimasterTeam.setToggleEnabled(true);
        state = State.WORLD;

        syncPresence(true);
        syncWorldEntities(true);
        AnimasterNotifications.fetch();

        if (battleResume && battleResume.isBattling && battleResume.id_battle)
        {
            AnimasterTarget.clear();
            state = State.COMBAT;
            AnimasterInventory.close();
            AnimasterTeam.close();
            AnimasterInventory.setToggleEnabled(false);
            AnimasterTeam.setToggleEnabled(false);
            AnimasterCombat.resume(player, battleResume);
        }

        lastFrame = performance.now();
        requestAnimationFrame(gameLoop);
    }

    function syncPlayerToModules()
    {
        if (!player)
        {
            return;
        }

        AnimasterWorld.setPlayer(player);
        AnimasterDialog.setPlayer(player);
        AnimasterInventory.setPlayer(player);
        AnimasterTeam.setPlayer(player);
        AnimasterSpawn.setPlayer(player);
        AnimasterNotifications.setPlayer(player);
        AnimasterChat.setPlayer(player);
        AnimasterTrade.setPlayer(player);
        AnimasterDuel.setPlayer(player);
    }

    function applyProfileToPlayer(profile)
    {
        if (!player || !profile)
        {
            return;
        }

        if (profile.id_zone !== undefined && profile.id_zone !== null)
        {
            player.id_zone = profile.id_zone;
        }

        if (profile.position_x !== undefined && profile.position_x !== null)
        {
            player.x = parseFloat(profile.position_x) || 0;
        }

        if (profile.position_y !== undefined && profile.position_y !== null)
        {
            player.y = parseFloat(profile.position_y) || 0;
        }

        if (profile.position_z !== undefined && profile.position_z !== null)
        {
            player.z = parseFloat(profile.position_z) || 0;
        }

        if (profile.level !== undefined && profile.level !== null)
        {
            player.level = profile.level;
        }

        player.id_zone_last_recover = profile.id_zone_last_recover;
        player.position_x_last_recover = profile.position_x_last_recover;
        player.position_y_last_recover = profile.position_y_last_recover;
        player.position_z_last_recover = profile.position_z_last_recover;
    }

    function teleportToLastRecover()
    {
        var zone = parseInt(player.id_zone_last_recover, 10);

        if (!zone || zone <= 0)
        {
            zone = 1000;
        }

        player.id_zone = zone;
        player.x = parseFloat(player.position_x_last_recover) || 0;
        player.y = parseFloat(player.position_y_last_recover) || 0;
        player.z = parseFloat(player.position_z_last_recover) || 0;
        syncPlayerToModules();
    }

    function handleBlackout()
    {
        if (!player)
        {
            return Promise.resolve();
        }

        teleportToLastRecover();

        return AnimasterApi.recoverTeamHp(player).then(function (profile)
        {
            player.id_zone_last_recover = profile.id_zone_last_recover;
            player.position_x_last_recover = profile.position_x_last_recover;
            player.position_y_last_recover = profile.position_y_last_recover;
            player.position_z_last_recover = profile.position_z_last_recover;

            if (profile.level !== undefined && profile.level !== null)
            {
                player.level = profile.level;
            }

            syncPlayerToModules();

            return AnimasterSpawn.loadSpawnPoints().then(function ()
            {
                AnimasterSpawn.tick(true);
                syncWorldEntities(true);
            });
        });
    }

    function onEntityTargeted(target)
    {
        if (state !== State.WORLD || !target)
        {
            return;
        }

        if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
        {
            return;
        }

        if (AnimasterDialog.isActive())
        {
            return;
        }

        AnimasterTarget.show(target);
    }

    function onWildClicked(wild)
    {
        if (state !== State.WORLD || !player)
        {
            return;
        }

        AnimasterTarget.clear();

        var wx = parseFloat(wild.pos_x);
        var wz = parseFloat(wild.pos_z);

        if (AnimasterWorld.distance(player.x, player.z, wx, wz) > AnimasterWorld.INTERACT_RADIUS)
        {
            return;
        }

        state = State.COMBAT;
        AnimasterInventory.close();
        AnimasterTeam.close();
        AnimasterInventory.setToggleEnabled(false);
        AnimasterTeam.setToggleEnabled(false);

        AnimasterApi.startBattle(player, wild.id_wild_animal).then(function (info)
        {
            AnimasterCombat.start(player, info);
        }).catch(function (err)
        {
            state = State.WORLD;
            AnimasterWorld.resetWildEncounter();
            alert(err.message || AnimasterLang.t('error.start_battle_failed'));
        });
    }

    function syncPresence(force)
    {
        if (state !== State.WORLD || !player || presenceBusy)
        {
            return;
        }

        var now = performance.now();

        if (!force && now - lastPresence < 150)
        {
            return;
        }

        lastPresence = now;
        presenceBusy = true;

        AnimasterApi.sendPresence(player).then(function (others)
        {
            AnimasterWorld.setOthers(others);
        }).catch(function (err)
        {
            console.warn('[Animaster] presence sync failed:', err && err.message ? err.message : err);
        }).finally(function ()
        {
            presenceBusy = false;
        });
    }

    function syncWorldEntities(force)
    {
        if (state !== State.WORLD || !player || entitiesBusy)
        {
            return;
        }

        var now = performance.now();

        if (!force && now - lastEntities < 300)
        {
            return;
        }

        lastEntities = now;
        entitiesBusy = true;

        Promise.all([
            AnimasterApi.fetchWildAnimals(player).then(function (rows)
            {
                AnimasterWorld.setWilds(rows);
                return rows;
            }).catch(function (err)
            {
                console.warn('[Animaster] wild sync failed:', err && err.message ? err.message : err);
                return null;
            }),
            AnimasterApi.fetchNpcs(player).then(function (rows)
            {
                AnimasterWorld.setNpcs(rows);
                return rows;
            }).catch(function (err)
            {
                console.warn('[Animaster] npc sync failed:', err && err.message ? err.message : err);
                return null;
            })
        ]).finally(function ()
        {
            entitiesBusy = false;
        });
    }

    function gameLoop(now)
    {
        var dt = Math.min(0.05, (now - lastFrame) / 1000);
        lastFrame = now;

        if (state === State.WORLD && player)
        {
            if (!AnimasterDialog.isActive()
                && !(typeof AnimasterChat !== 'undefined' && AnimasterChat.isInputFocused()))
            {
                AnimasterWorld.updateMovement(dt);
            }

            if (!AnimasterDialog.isActive()
                && !AnimasterDialog.isTalkBubbleVisible()
                && !AnimasterInventory.isOpen()
                && !AnimasterTeam.isOpen()
                && !AnimasterNotifications.isVisible()
                && !(typeof AnimasterChat !== 'undefined' && AnimasterChat.isInputFocused()))
            {
                AnimasterWorld.tickWildEncounter();
            }

            AnimasterInventory.setToggleEnabled(
                state === State.WORLD && !AnimasterDialog.isActive() && !AnimasterCombat.isVisible()
            );
            AnimasterTeam.setToggleEnabled(
                state === State.WORLD && !AnimasterDialog.isActive() && !AnimasterCombat.isVisible()
            );

            syncPresence(false);
            syncWorldEntities(false);
            AnimasterSpawn.tick(false);
            AnimasterWorld.render();
            AnimasterPlayerChatBubbles.update();

            var nearbyNpc = AnimasterDialog.isActive()
                ? null
                : AnimasterWorld.getNearbyNpc(AnimasterWorld.NPC_TALK_RADIUS);

            if (nearbyNpc && !nearbyNpc.dialogues)
            {
                nearbyNpc = null;
            }

            if (nearbyNpc && AnimasterDialog.parseNpcConversations(nearbyNpc).length === 0)
            {
                nearbyNpc = null;
            }

            var bubblePos = nearbyNpc
                ? AnimasterWorld.getNpcScreenPosition(nearbyNpc)
                : null;

            AnimasterDialog.updateTalkBubble(nearbyNpc, bubblePos);

            var hud = AnimasterWorld.getHudText();

            if (hudPlayer)
            {
                hudPlayer.textContent = hud.player;
            }

            if (hudStatus)
            {
                hudStatus.textContent = hud.status;
            }
        }

        requestAnimationFrame(gameLoop);
    }
})();
