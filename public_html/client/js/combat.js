/**
 * Turn-based combat overlay (solo PvE).
 */
var AnimasterCombat = (function ()
{
    var overlay = null;
    var unitsEl = null;
    var logEl = null;
    var abilitiesEl = null;
    var messageEl = null;
    var fleeBtn = null;
    var closeBtn = null;

    var player = null;
    var battle = null;
    var moves = [];
    var abilities = [];
    var busy = false;
    var onEnd = null;
    var onBlackout = null;
    var blackoutHandled = false;
    var pvpMeta = null;
    var pvpPollTimer = null;
    var pvpPhase = 'idle';
    var pvpStatusEl = null;
    var pvpLockedTurn = 0;
    var pvpResolvePending = false;
    var pvpResolvedMoveCount = 0;
    var abilityCacheKey = '';
    var abilityCacheList = [];

    var menuMode = 'main';
    var selectedItemTypeId = 0;

    var COMBAT_PRESENTATION = {
        textSpeedMs: 28,
        bumpDurationMs: 220,
        hpAnimDurationMs: 450,
        stepPauseMs: 400,
        autoAdvance: false
    };

    var playbackToken = 0;
    var playbackRunning = false;
    var advanceResolver = null;
    var autoAdvanceEl = null;

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function formatAnimalRow(animal, hp, maxHp)
    {
        var name = animal.nickname || animal.species || t('team.animal_fallback', { id: animal.id || animal.id_animal || '?' });

        return name + ' ' + t('team.lv_short', { level: animal.lvl || 1 })
            + ' — ' + hp + '/' + maxHp + ' HP';
    }

    function init(options)
    {
        overlay = document.getElementById('combat-overlay');
        unitsEl = document.getElementById('combat-units');
        logEl = document.getElementById('combat-log');
        abilitiesEl = document.getElementById('combat-abilities');
        messageEl = document.getElementById('combat-message');
        pvpStatusEl = document.getElementById('combat-pvp-status');
        fleeBtn = document.getElementById('combat-flee');
        closeBtn = document.getElementById('combat-close');
        onEnd = options.onEnd;
        onBlackout = options.onBlackout;

        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');

        fleeBtn.addEventListener('click', function ()
        {
            performAction('action', 4);
        });

        closeBtn.addEventListener('click', function ()
        {
            if (canClose())
            {
                hide();
            }
        });

        document.addEventListener('keydown', function (e)
        {
            if (playbackRunning && advanceResolver && (e.code === 'Space' || e.code === 'Enter'))
            {
                e.preventDefault();
                var resolve = advanceResolver;
                advanceResolver = null;
                resolve();
                return;
            }

            if (e.code === 'Escape' && overlay && !overlay.hidden && canClose())
            {
                hide();
            }
        });

        autoAdvanceEl = document.getElementById('combat-auto-advance');

        if (autoAdvanceEl)
        {
            COMBAT_PRESENTATION.autoAdvance = localStorage.getItem('animaster_combat_auto') === '1';
            autoAdvanceEl.checked = COMBAT_PRESENTATION.autoAdvance;
            autoAdvanceEl.addEventListener('change', function ()
            {
                COMBAT_PRESENTATION.autoAdvance = autoAdvanceEl.checked;
                localStorage.setItem('animaster_combat_auto', autoAdvanceEl.checked ? '1' : '0');
            });
        }
    }

    function canClose()
    {
        if (!battle)
        {
            return true;
        }

        if (!latestStats())
        {
            return true;
        }

        return battle.status && battle.status !== 'ongoing';
    }

    function setTurnFromMove(move)
    {
        var parsedTurn = parseInt(move.turn, 10);

        if (!isNaN(parsedTurn))
        {
            battle.turn = parsedTurn;
        }
    }

    function advanceTurnCounter()
    {
        if (battle && battle.status === 'ongoing')
        {
            battle.turn = battle.turn + 1;
        }
    }

    function resetMenu()
    {
        menuMode = 'main';
        selectedItemTypeId = 0;
    }

    function setPvpOverlayMode(active)
    {
        if (!overlay)
        {
            return;
        }

        if (active)
        {
            overlay.classList.add('combat-pvp-mode');
        }
        else
        {
            overlay.classList.remove('combat-pvp-mode');
        }

        if (pvpStatusEl)
        {
            if (active)
            {
                pvpStatusEl.hidden = false;
            }
            else
            {
                pvpStatusEl.hidden = true;
                pvpStatusEl.textContent = '';
            }
        }
    }

    function clearPvpActionMenu()
    {
        menuMode = 'main';
        selectedItemTypeId = 0;
        abilitiesEl.innerHTML = '';
        fleeBtn.disabled = true;
    }

    function pvpResolvingTurnNumber()
    {
        if (pvpLockedTurn > 0)
        {
            return pvpLockedTurn;
        }

        if (pvpMeta && pvpMeta.current_turn)
        {
            var current = parseInt(pvpMeta.current_turn, 10);

            if (!isNaN(current) && current > 1)
            {
                return current - 1;
            }
        }

        return battle ? battle.turn : 0;
    }

    function getPlayableMovesForTurn(turnMoves, turnNum)
    {
        var turn = parseInt(turnNum, 10) || 0;

        return (turnMoves || []).filter(function (move)
        {
            return parseInt(move.turn, 10) === turn
                && move.move_description
                && move.move_type !== 'start';
        });
    }

    function beginPvpTurnAnimation(beforeMove)
    {
        var resolvingTurn = pvpResolvingTurnNumber();
        var turnMoves = getPlayableMovesForTurn(moves, resolvingTurn);

        stopPvpPoll();
        pvpPhase = 'animating';
        pvpResolvePending = false;
        busy = true;
        presentTurn(beforeMove, turnMoves.length <= 1, turnMoves);
    }

    function updatePvpStatusBar()
    {
        if (!pvpStatusEl || !battle || battle.type !== 'pvp')
        {
            return;
        }

        if (!pvpMeta || pvpMeta.battle_finished || battle.status !== 'ongoing')
        {
            pvpStatusEl.textContent = '';
            return;
        }

        if (pvpPhase === 'locked' || pvpMeta.submitted)
        {
            if (pvpMeta.both_locked || pvpMeta.opponent_submitted)
            {
                pvpStatusEl.textContent = t('duel.waiting_resolve');
            }
            else
            {
                pvpStatusEl.textContent = t('duel.choice_locked');
            }

            return;
        }

        if (pvpMeta.opponent_locked)
        {
            pvpStatusEl.textContent = t('duel.opponent_ready');
            return;
        }

        pvpStatusEl.textContent = '';
    }

    function stopPvpPoll()
    {
        if (pvpPollTimer)
        {
            clearInterval(pvpPollTimer);
            pvpPollTimer = null;
        }
    }

    function startPvpPoll()
    {
        stopPvpPoll();

        if (!battle || battle.type !== 'pvp' || battle.status !== 'ongoing')
        {
            return;
        }

        pvpPollTimer = setInterval(pollPvpStatus, pvpPhase === 'locked' ? 1000 : 2000);
    }

    function enterPvpInputPhase(force)
    {
        if (battle && battle.type === 'pvp' && !force)
        {
            if (pvpPhase === 'locked' || pvpPhase === 'animating')
            {
                return;
            }

            if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
            {
                enterPvpLockedPhase();
                return;
            }
        }

        if (battle && battle.type === 'pvp' && force && pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
        {
            enterPvpLockedPhase();
            return;
        }

        pvpPhase = 'input';
        pvpLockedTurn = 0;
        pvpResolvePending = false;
        pvpResolvedMoveCount = 0;
        busy = false;
        fleeBtn.disabled = false;
        setMessage(t('combat.choose_action'));
        updatePvpStatusBar();
        resetMenu();
        showMainMenu();
        preloadAbilitiesForActive();
        startPvpPoll();
    }

    function enterPvpLockedPhase()
    {
        pvpPhase = 'locked';

        if (!pvpLockedTurn)
        {
            pvpLockedTurn = battle.turn;
        }

        if (pvpMeta && pvpMeta.both_locked)
        {
            pvpResolvePending = true;
        }

        clearPvpActionMenu();
        busy = false;
        setMessage(t('combat.choose_action'));
        updatePvpStatusBar();
        pvpResolvedMoveCount = getPlayableMovesForTurn(moves, pvpLockedTurn).length;
        startPvpPoll();
        pollPvpStatus();
    }

    function pvpPollTurn()
    {
        if (pvpPhase === 'locked' && pvpLockedTurn > 0)
        {
            return pvpLockedTurn;
        }

        return battle.turn;
    }

    function pvpTurnJustResolved(rows, prevComplete)
    {
        if (!pvpLockedTurn)
        {
            return !!(pvpMeta && pvpMeta.turn_complete && !prevComplete);
        }

        var resolvedNow = getPlayableMovesForTurn(rows, pvpLockedTurn).length;

        return (resolvedNow > pvpResolvedMoveCount && resolvedNow > 0)
            || !!(pvpMeta && pvpMeta.turn_complete && !prevComplete);
    }

    function pollPvpStatus()
    {
        if (!battle || !player || battle.type !== 'pvp' || pvpPhase === 'animating' || busy)
        {
            return;
        }

        AnimasterApi.getBattleInfo({
            id_user_ig: player.id_user_ig || 0,
            id_battle: battle.id,
            battle_type: battle.type,
            turn: pvpPollTurn(),
            restarting_old_battle: 'N',
            lang: AnimasterApi.LANG
        }).then(function (result)
        {
            if (pvpPhase === 'animating' || busy)
            {
                return;
            }

            var prevComplete = !!(pvpMeta && pvpMeta.turn_complete);
            var prevFinished = !!(pvpMeta && pvpMeta.battle_finished);

            if (pvpPhase === 'input')
            {
                if (result && result.meta)
                {
                    pvpMeta = result.meta;
                    syncPvpTurnFromMeta();
                }

                if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
                {
                    enterPvpLockedPhase();
                    return;
                }

                updatePvpStatusBar();

                if (pvpMeta && pvpMeta.battle_finished && !prevFinished)
                {
                    moves = normalizeBattleResponse(result);
                    applyStateFromMoves({ deferTerminalUi: true });
                    beginPvpTurnAnimation(null);
                }

                return;
            }

            var rows = normalizeBattleResponse(result);

            if (pvpMeta && pvpMeta.both_locked)
            {
                pvpResolvePending = true;
            }

            if (pvpMeta && pvpMeta.battle_finished && !prevFinished)
            {
                moves = rows;
                applyStateFromMoves({ deferTerminalUi: true });
                beginPvpTurnAnimation(null);
                return;
            }

            if (pvpMeta
                && pvpPhase === 'locked'
                && pvpTurnJustResolved(rows, prevComplete))
            {
                pvpResolvedMoveCount = getPlayableMovesForTurn(rows, pvpLockedTurn).length;
                moves = rows;
                applyStateFromMoves({ deferTerminalUi: true });
                beginPvpTurnAnimation(null);
                return;
            }

            if (pvpPhase === 'locked')
            {
                updatePvpStatusBar();
            }
        }).catch(function (err)
        {
            console.warn('[AnimasterCombat] pvp poll failed:', err && err.message ? err.message : err);
        });
    }

    function show()
    {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
    }

    function abilityCacheKeyFor(stats)
    {
        if (!stats)
        {
            return '';
        }

        return String(stats.p_a_id || 0) + ':' + String(stats.p_a_lvl || 0);
    }

    function invalidateAbilityCache()
    {
        abilityCacheKey = '';
        abilityCacheList = [];
    }

    function preloadAbilitiesForActive()
    {
        if (!battle || battle.type !== 'pvp' || !player)
        {
            return;
        }

        var stats = latestStats();

        if (!stats)
        {
            return;
        }

        var cacheKey = abilityCacheKeyFor(stats);

        if (cacheKey === abilityCacheKey && abilityCacheList.length)
        {
            return;
        }

        AnimasterApi.getAbilityList(player.id_user_ig, stats.p_a_id, stats.p_a_lvl).then(function (list)
        {
            if (!battle || battle.type !== 'pvp')
            {
                return;
            }

            var currentStats = latestStats();
            var currentKey = abilityCacheKeyFor(currentStats);

            if (currentKey !== cacheKey)
            {
                return;
            }

            abilityCacheKey = cacheKey;
            abilityCacheList = list || [];
        }).catch(function (err)
        {
            console.warn('[AnimasterCombat] ability preload failed:', err && err.message ? err.message : err);
        });
    }

    function syncPvpTurnFromMeta()
    {
        if (!battle || battle.type !== 'pvp' || !pvpMeta)
        {
            return;
        }

        var serverTurn = parseInt(pvpMeta.current_turn, 10);

        if (!isNaN(serverTurn) && serverTurn > 0)
        {
            battle.turn = serverTurn;
        }
    }

    function normalizeBattleResponse(result)
    {
        if (battle && battle.type === 'pvp' && result && result.moves)
        {
            pvpMeta = result.meta || {};
            syncPvpTurnFromMeta();
            return result.moves;
        }

        pvpMeta = null;
        return result || [];
    }

    function canActInBattle()
    {
        if (!battle || battle.status !== 'ongoing' || busy)
        {
            return false;
        }

        if (battle.type === 'pvp')
        {
            return !!(pvpMeta && pvpMeta.can_act);
        }

        return true;
    }

    function finalizePvpBattleIfNeeded()
    {
        if (!battle || battle.type !== 'pvp' || !pvpMeta || !pvpMeta.battle_finished)
        {
            return false;
        }

        stopPvpPoll();

        if (battle.status === 'ongoing' && moves.length)
        {
            applyStateFromMoves();
        }

        if (battle.status !== 'ongoing')
        {
            setMessage(statusMessage(battle.status));
            closeBtn.disabled = false;
            fleeBtn.disabled = true;

            if (abilitiesEl)
            {
                abilitiesEl.innerHTML = '';
            }

            maybeHandleBlackout();
            return true;
        }

        return false;
    }

    function hide()
    {
        stopPvpPoll();
        cancelPresentation();
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        setPvpOverlayMode(false);
        battle = null;
        moves = [];
        abilities = [];
        busy = false;
        resetMenu();
        blackoutHandled = false;
        pvpPhase = 'idle';
        pvpMeta = null;
        pvpLockedTurn = 0;
        pvpResolvePending = false;
        pvpResolvedMoveCount = 0;
        invalidateAbilityCache();

        if (onEnd)
        {
            onEnd();
        }
    }

    function isVisible()
    {
        return overlay && !overlay.hidden;
    }

    function start(playerState, battleInfo)
    {
        player = playerState;
        battle = {
            id: battleInfo.id_battle,
            type: battleInfo.battle_type || 'solo_pve',
            turn: 0,
            status: 'ongoing'
        };

        resetMenu();
        show();
        setPvpOverlayMode(battle.type === 'pvp');
        loadTurn(0, false);
    }

    function resume(playerState, battleInfo)
    {
        player = playerState;
        var resumeTurn = parseInt(battleInfo.current_battle_turn, 10);

        if (isNaN(resumeTurn) || resumeTurn < 0)
        {
            resumeTurn = 0;
        }

        battle = {
            id: battleInfo.id_battle,
            type: battleInfo.battle_type || 'solo_pve',
            turn: resumeTurn,
            status: 'ongoing'
        };

        resetMenu();
        show();
        setPvpOverlayMode(battle.type === 'pvp');
        loadTurn(resumeTurn, resumeTurn > 0);
    }

    function loadTurn(turn, restarting)
    {
        if (!battle || !player)
        {
            return;
        }

        busy = true;
        setMessage(t('combat.loading_turn'));

        AnimasterApi.getBattleInfo({
            id_user_ig: player.id_user_ig || 0,
            id_battle: battle.id,
            battle_type: battle.type,
            turn: turn,
            restarting_old_battle: restarting ? 'S' : 'N',
            lang: AnimasterApi.LANG
        }).then(function (result)
        {
            moves = normalizeBattleResponse(result);
            applyStateFromMoves({ deferTerminalUi: true });

            if (battle.type === 'pvp')
            {
                syncPvpTurnFromMeta();
                var stats = latestStats();

                if (stats)
                {
                    renderUnitsFromStats(statsFromMove(stats));
                    renderLogComplete(moves);
                }

                busy = false;

                if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
                {
                    enterPvpLockedPhase();
                }
                else
                {
                    enterPvpInputPhase();
                }

                return;
            }

            advanceTurnCounter();
            presentTurn(null, true);
        }).catch(function (err)
        {
            setMessage(err.message || t('combat.load_failed'));
            presentTurn(null, true);
        }).finally(function ()
        {
            if (!blackoutHandled && !playbackRunning && battle && battle.type !== 'pvp')
            {
                busy = false;
                refreshActionMenu();
            }
        });
    }

    function refreshActionMenu()
    {
        if (!battle || !overlay || overlay.hidden)
        {
            return;
        }

        loadActionMenu();
    }

    function applyStateFromMoves(options)
    {
        options = options || {};

        if (!moves.length)
        {
            setMessage(t('combat.no_battle_data_turn'));
            return;
        }

        var last = moves[moves.length - 1];
        battle.status = last.resulting_battle_status || 'ongoing';
        setTurnFromMove(last);

        if (options.deferTerminalUi)
        {
            return;
        }

        if (battle.status !== 'ongoing')
        {
            setMessage(statusMessage(battle.status));
            maybeHandleBlackout();
        }
        else
        {
            setMessage(t('combat.choose_action'));
        }
    }

    function maybeHandleBlackout()
    {
        if (blackoutHandled || typeof onBlackout !== 'function')
        {
            return;
        }

        if (battle && battle.type === 'pvp')
        {
            if (battle.status !== 'defeat')
            {
                return;
            }

            if (!pvpMeta || !pvpMeta.needs_recovery)
            {
                return;
            }
        }
        else if (battle.status !== 'defeat')
        {
            return;
        }

        blackoutHandled = true;
        busy = true;
        setMessage(t('combat.status_blackout_loading'));
        abilitiesEl.innerHTML = '';
        fleeBtn.disabled = true;
        closeBtn.disabled = true;

        onBlackout().then(function ()
        {
            hide();
        }).catch(function (err)
        {
            setMessage((err && err.message) ? err.message : t('combat.recovery_failed'));
            closeBtn.disabled = false;
            busy = false;
            blackoutHandled = false;
        });
    }

    function statusMessage(status)
    {
        if (battle && battle.type === 'pvp')
        {
            if (status === 'win')
            {
                return t('duel.win');
            }

            if (status === 'defeat')
            {
                return t('duel.lose');
            }
        }

        if (status === 'win')
        {
            return t('combat.status_victory');
        }
        if (status === 'defeat')
        {
            return t('combat.status_defeat');
        }
        if (status === 'fled' || status === 'escape' || status === 'escaped')
        {
            return t('combat.status_fled');
        }

        return t('combat.status_ended', { status: status });
    }

    function latestStats()
    {
        if (!moves.length)
        {
            return null;
        }

        return moves[moves.length - 1];
    }

    function isActiveAnimalFainted()
    {
        var stats = latestStats();

        if (!stats)
        {
            return true;
        }

        return (parseInt(stats.p_a_res_hp, 10) || 0) <= 0;
    }

    function loadActionMenu()
    {
        if (!latestStats() || battle.status !== 'ongoing')
        {
            abilitiesEl.innerHTML = '';
            fleeBtn.disabled = true;
            return;
        }

        if (battle.type === 'pvp' && pvpPhase !== 'input')
        {
            return;
        }

        if (battle.type === 'pvp' && !canActInBattle())
        {
            abilitiesEl.innerHTML = '';
            fleeBtn.disabled = true;
            return;
        }

        fleeBtn.disabled = busy;

        if (menuMode === 'fight')
        {
            showFightMenu();
        }
        else if (menuMode === 'items')
        {
            showItemsMenu();
        }
        else if (menuMode === 'item-target')
        {
            showItemTargetMenu();
        }
        else if (menuMode === 'switch')
        {
            showSwitchMenu();
        }
        else
        {
            showMainMenu();
        }
    }

    function appendBackButton(backHandler)
    {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'combat-menu-back';
        btn.textContent = t('ui.back');
        btn.disabled = busy;
        btn.addEventListener('click', function ()
        {
            if (busy)
            {
                return;
            }

            if (typeof backHandler === 'function')
            {
                backHandler();
            }
            else
            {
                showMainMenu();
            }
        });
        abilitiesEl.appendChild(btn);
    }

    function appendMenuButton(label, title, onClick, disabled)
    {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'combat-menu-btn';
        btn.textContent = label;
        btn.title = title || '';
        btn.disabled = !!disabled || busy;
        btn.addEventListener('click', function ()
        {
            if (busy || btn.disabled)
            {
                return;
            }

            onClick();
        });
        abilitiesEl.appendChild(btn);
    }

    function showMainMenu()
    {
        if (battle && battle.type === 'pvp' && pvpPhase !== 'input')
        {
            return;
        }

        menuMode = 'main';
        selectedItemTypeId = 0;
        abilitiesEl.innerHTML = '';

        var fainted = isActiveAnimalFainted();

        appendMenuButton(t('combat.fight'), t('combat.fight_hint'), function ()
        {
            showFightMenu();
        }, fainted);

        appendMenuButton(t('combat.items'), t('combat.items_hint'), function ()
        {
            showItemsMenu();
        }, fainted);

        appendMenuButton(t('combat.team'), t('combat.team_hint'), function ()
        {
            showSwitchMenu();
        }, false);
    }

    function showFightMenu()
    {
        menuMode = 'fight';
        setMessage(t('combat.choose_ability'));

        var stats = latestStats();

        if (!stats)
        {
            showMainMenu();
            return;
        }

        var cacheKey = abilityCacheKeyFor(stats);

        if (cacheKey === abilityCacheKey && abilityCacheList.length)
        {
            abilities = abilityCacheList.slice();
            renderFightMenu();
            return;
        }

        abilitiesEl.innerHTML = '<span class="combat-loading">' + escapeHtml(t('combat.loading_abilities')) + '</span>';

        AnimasterApi.getAbilityList(player.id_user_ig, stats.p_a_id, stats.p_a_lvl).then(function (list)
        {
            if (menuMode !== 'fight' || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            var currentStats = latestStats();
            var currentKey = abilityCacheKeyFor(currentStats);

            if (currentKey !== cacheKey)
            {
                return;
            }

            abilityCacheKey = cacheKey;
            abilityCacheList = list || [];
            abilities = abilityCacheList.slice();
            renderFightMenu();
        }).catch(function (err)
        {
            if (menuMode !== 'fight' || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            abilitiesEl.innerHTML = '<span class="error">' + escapeHtml(err.message || t('combat.load_abilities_failed')) + '</span>';
            appendBackButton();
        });
    }

    function renderFightMenu()
    {
        if (menuMode !== 'fight' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
        {
            return;
        }

        abilitiesEl.innerHTML = '';

        if (!abilities.length)
        {
            var empty = document.createElement('span');
            empty.className = 'combat-empty';
            empty.textContent = t('combat.no_abilities');
            abilitiesEl.appendChild(empty);
        }
        else
        {
            abilities.forEach(function (ab)
            {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'combat-ability-btn';
                btn.textContent = ab.ability + ' (' + ab.power + '/' + ab.m_power + ')';
                btn.title = ab.descrizione || '';
                btn.disabled = busy;
                btn.addEventListener('click', function ()
                {
                    performAction('ability', ab.id_ability);
                });
                abilitiesEl.appendChild(btn);
            });
        }

        appendBackButton();
    }

    function showItemsMenu()
    {
        menuMode = 'items';
        selectedItemTypeId = 0;
        setMessage(t('combat.choose_item'));
        abilitiesEl.innerHTML = '<span class="combat-loading">' + escapeHtml(t('combat.loading_items')) + '</span>';

        AnimasterApi.getInventory(player, true).then(function (items)
        {
            if (menuMode !== 'items' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            renderItemsMenu(items || []);
        }).catch(function (err)
        {
            if (menuMode !== 'items' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            abilitiesEl.innerHTML = '<span class="error">' + escapeHtml(err.message || t('combat.load_items_failed')) + '</span>';
            appendBackButton();
        });
    }

    function renderItemsMenu(items)
    {
        if (menuMode !== 'items' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
        {
            return;
        }

        abilitiesEl.innerHTML = '';

        if (!items.length)
        {
            var empty = document.createElement('span');
            empty.className = 'combat-empty';
            empty.textContent = t('combat.no_items');
            abilitiesEl.appendChild(empty);
        }
        else
        {
            items.forEach(function (item)
            {
                var label = item.nome + ' (×' + item.quantita + ')';
                appendMenuButton(label, item.descrizione || '', function ()
                {
                    onItemSelected(item);
                });
            });
        }

        appendBackButton();
    }

    function showSwitchMenu()
    {
        menuMode = 'switch';
        setMessage(t('combat.choose_switch'));
        abilitiesEl.innerHTML = '<span class="combat-loading">' + escapeHtml(t('combat.loading_team')) + '</span>';

        var stats = latestStats();

        if (!stats)
        {
            showMainMenu();
            return;
        }

        AnimasterApi.getTeamAnimals(player, {
            motive: 'switch',
            id_active_animal: stats.p_a_id,
            id_item_type_selected: 0
        }).then(function (animals)
        {
            if (menuMode !== 'switch' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            renderSwitchMenu(animals || []);
        }).catch(function (err)
        {
            if (menuMode !== 'switch' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            abilitiesEl.innerHTML = '<span class="error">' + escapeHtml(err.message || t('combat.load_team_failed')) + '</span>';
            appendBackButton();
        });
    }

    function renderSwitchMenu(animals)
    {
        if (menuMode !== 'switch' || busy || (battle.type === 'pvp' && pvpPhase !== 'input'))
        {
            return;
        }

        abilitiesEl.innerHTML = '';

        if (!animals.length)
        {
            var empty = document.createElement('span');
            empty.className = 'combat-empty';
            empty.textContent = t('combat.no_switch_targets');
            abilitiesEl.appendChild(empty);
        }
        else
        {
            animals.forEach(function (animal)
            {
                var hp = parseInt(animal.curHP, 10) || 0;
                var label = formatAnimalRow(animal, hp, parseInt(animal.maxHP, 10) || 1);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'combat-menu-btn';
                btn.textContent = label;
                btn.disabled = busy || hp <= 0;
                btn.addEventListener('click', function ()
                {
                    performAction('switch', animal.id);
                });
                abilitiesEl.appendChild(btn);
            });
        }

        appendBackButton();
    }

    function onItemSelected(item)
    {
        selectedItemTypeId = parseInt(item.id_item_type, 10) || 0;

        if (selectedItemTypeId <= 0)
        {
            return;
        }

        menuMode = 'item-target';
        setMessage(t('combat.item_target_prompt', { item: item.nome }));
        showItemTargetMenu();
    }

    function showItemTargetMenu()
    {
        var stats = latestStats();

        if (!stats || selectedItemTypeId <= 0)
        {
            showItemsMenu();
            return;
        }

        abilitiesEl.innerHTML = '<span class="combat-loading">' + escapeHtml(t('combat.loading_team')) + '</span>';

        AnimasterApi.getTeamAnimals(player, {
            motive: 'use_item',
            id_active_animal: stats.p_a_id,
            id_item_type_selected: selectedItemTypeId
        }).then(function (animals)
        {
            renderItemTargetMenu(animals || [], stats);
        }).catch(function (err)
        {
            abilitiesEl.innerHTML = '<span class="error">' + escapeHtml(err.message || t('combat.load_team_failed')) + '</span>';
            appendBackButton(showItemsMenu);
        });
    }

    function renderItemTargetMenu(animals, stats)
    {
        abilitiesEl.innerHTML = '';

        if (!animals.length)
        {
            var empty = document.createElement('span');
            empty.className = 'combat-empty';
            empty.textContent = t('combat.no_item_target');
            abilitiesEl.appendChild(empty);
        }
        else
        {
            var activeId = parseInt(stats.p_a_id, 10) || 0;

            animals.forEach(function (animal)
            {
                var hp = parseInt(animal.curHP, 10) || 0;
                var maxHp = parseInt(animal.maxHP, 10) || 1;
                var label = formatAnimalRow(animal, hp, maxHp);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'combat-menu-btn';

                if ((parseInt(animal.id, 10) || 0) === activeId)
                {
                    btn.className += ' combat-target-active';
                }

                btn.textContent = label;
                btn.title = (animal.element || '') + ' ' + (animal.species || '');
                btn.disabled = busy;
                btn.addEventListener('click', function ()
                {
                    performAction('use_on', animal.id, {
                        id_item_type_selected: selectedItemTypeId
                    });
                });
                abilitiesEl.appendChild(btn);
            });
        }

        appendBackButton(showItemsMenu);
    }

    function performAction(type, id, extra)
    {
        if (!battle || busy || battle.status !== 'ongoing' || !canActInBattle())
        {
            return;
        }

        if (battle.type === 'pvp' && pvpPhase !== 'input')
        {
            return;
        }

        stopPvpPoll();
        pvpLockedTurn = battle.turn;
        pvpPhase = 'locked';
        pvpResolvePending = false;
        busy = true;
        setMessage(t('combat.resolving_turn'));
        clearPvpActionMenu();

        var beforeMove = latestStats();
        var actionTurn = battle.turn;
        var params = {
            id_user_ig: player.id_user_ig || 0,
            id_battle: battle.id,
            battle_type: battle.type,
            turn: actionTurn,
            restarting_old_battle: 'N',
            type: type,
            id: id,
            lang: AnimasterApi.LANG
        };

        if (extra && extra.id_item_type_selected)
        {
            params.id_item_type_selected = extra.id_item_type_selected;
        }

        AnimasterApi.getBattleInfo(params).then(function (result)
        {
            moves = normalizeBattleResponse(result);
            resetMenu();
            applyStateFromMoves({ deferTerminalUi: true });

            if (battle.type === 'pvp')
            {
                if (type === 'switch')
                {
                    invalidateAbilityCache();
                }

                if (pvpMeta && pvpMeta.battle_finished)
                {
                    beginPvpTurnAnimation(beforeMove);
                    return;
                }

                if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
                {
                    enterPvpLockedPhase();
                    return;
                }

                if (pvpMeta && pvpMeta.turn_complete)
                {
                    beginPvpTurnAnimation(beforeMove);
                    return;
                }

                enterPvpInputPhase();
                return;
            }

            advanceTurnCounter();
            presentTurn(beforeMove, false);
        }).catch(function (err)
        {
            setMessage(err.message || t('combat.action_failed'));

            if (battle.type === 'pvp')
            {
                if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
                {
                    enterPvpLockedPhase();
                }
                else
                {
                    enterPvpInputPhase();
                }

                return;
            }

            presentTurn(beforeMove, true);

            if (!blackoutHandled)
            {
                busy = false;
                refreshActionMenu();
            }
        });
    }

    function delay(ms)
    {
        return new Promise(function (resolve)
        {
            setTimeout(resolve, ms);
        });
    }

    function cancelPresentation()
    {
        playbackToken += 1;
        playbackRunning = false;

        if (advanceResolver)
        {
            var resolve = advanceResolver;
            advanceResolver = null;
            resolve();
        }
    }

    function statsFromMove(move)
    {
        if (!move)
        {
            return null;
        }

        return {
            p_name: move.p_a_nickname || move.p_a_species || t('combat.unit_your_animal'),
            p_a_id: parseInt(move.p_a_id, 10) || 0,
            p_lvl: move.p_a_lvl,
            p_hp: parseInt(move.p_a_res_hp, 10) || 0,
            p_max_hp: parseInt(move.p_a_res_max_hp, 10) || 1,
            w_name: move.w_a_species || t('combat.unit_wild'),
            w_a_id: parseInt(move.w_a_id, 10) || 0,
            w_lvl: move.w_a_lvl,
            w_hp: parseInt(move.w_a_res_hp, 10) || 0,
            w_max_hp: parseInt(move.w_a_res_max_hp, 10) || 1
        };
    }

    function getPlayableMoves(turnMoves)
    {
        return (turnMoves || []).filter(function (move)
        {
            return move.move_description && move.move_type !== 'start';
        });
    }

    function formatLogLine(move)
    {
        return t('combat.log_turn', {
            turn: move.turn,
            description: move.move_description
        });
    }

    var STAT_FIELDS = [
        { key: 'atk', tag: 'combat.stat_attack' },
        { key: 'def', tag: 'combat.stat_defense' },
        { key: 'matk', tag: 'combat.stat_matk' },
        { key: 'mdef', tag: 'combat.stat_mdef' },
        { key: 'acc', tag: 'combat.stat_accuracy' },
        { key: 'eva', tag: 'combat.stat_evasion' },
        { key: 'cr', tag: 'combat.stat_critical' },
        { key: 'spd', tag: 'combat.stat_speed' }
    ];

    function sideName(move, side)
    {
        if (side === 'player')
        {
            return move.p_a_nickname || move.p_a_species || t('combat.unit_your_animal');
        }

        return move.w_a_species || t('combat.unit_wild');
    }

    function statFieldValue(move, side, statKey)
    {
        if (!move)
        {
            return null;
        }

        var prefix = side === 'player' ? 'p_a_res_' : 'w_a_res_';
        var raw = move[prefix + statKey];

        if (raw === undefined || raw === null || raw === '')
        {
            return null;
        }

        var value = parseFloat(raw);

        return isNaN(value) ? null : value;
    }

    function statFieldChanged(prevMove, move, side, statKey)
    {
        var prevValue = statFieldValue(prevMove, side, statKey);
        var nextValue = statFieldValue(move, side, statKey);

        if (prevValue === null || nextValue === null)
        {
            return false;
        }

        return Math.abs(prevValue - nextValue) > 0.001;
    }

    function appendStatChangeLines(lines, move, prevMove, side)
    {
        if (!prevMove)
        {
            return;
        }

        var name = sideName(move, side);

        STAT_FIELDS.forEach(function (field)
        {
            if (!statFieldChanged(prevMove, move, side, field.key))
            {
                return;
            }

            var prevValue = statFieldValue(prevMove, side, field.key);
            var nextValue = statFieldValue(move, side, field.key);
            var increased = nextValue > prevValue;

            lines.push(t('combat.stat_changed', {
                name: name,
                stat: t(field.tag),
                direction: t(increased ? 'combat.stat_increased' : 'combat.stat_decreased')
            }));
        });
    }

    function appendHpResultLines(lines, move, prevMove)
    {
        if (!prevMove || move.move_hit === 'N')
        {
            return;
        }

        var prevWildHp = parseInt(prevMove.w_a_res_hp, 10) || 0;
        var nextWildHp = parseInt(move.w_a_res_hp, 10) || 0;

        if (prevWildHp !== nextWildHp && move.move_hit !== 'I')
        {
            lines.push(move.move_hit === 'C' ? t('combat.critical_hit') : t('combat.hit'));
        }

        var prevPlayerHp = parseInt(prevMove.p_a_res_hp, 10) || 0;
        var nextPlayerHp = parseInt(move.p_a_res_hp, 10) || 0;

        if (prevPlayerHp !== nextPlayerHp)
        {
            if (move.move_hit === 'I')
            {
                return;
            }

            lines.push(move.move_hit === 'C' ? t('combat.critical_hit') : t('combat.hit'));
        }
    }

    function buildMoveNarration(move, prevMove)
    {
        var lines = [formatLogLine(move)];

        if (move.move_hit === 'N')
        {
            lines.push(t('combat.missed'));
            return lines;
        }

        appendHpResultLines(lines, move, prevMove);
        appendStatChangeLines(lines, move, prevMove, 'wild');
        appendStatChangeLines(lines, move, prevMove, 'player');

        return lines;
    }

    function appendLogLine(text, className)
    {
        var line = document.createElement('p');
        line.className = className || 'combat-log-line';
        line.textContent = text;
        logEl.appendChild(line);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function narrateLines(lines, token)
    {
        var chain = Promise.resolve();

        lines.forEach(function (line, index)
        {
            chain = chain.then(function ()
            {
                if (token !== playbackToken)
                {
                    return;
                }

                var lineClass = index === 0 ? 'combat-log-line' : 'combat-log-line combat-log-followup';

                return typewriterLog(line, token, lineClass);
            }).then(function ()
            {
                return waitAdvance(token);
            });
        });

        return chain;
    }

    function waitAdvance(token)
    {
        if (COMBAT_PRESENTATION.autoAdvance)
        {
            return delay(COMBAT_PRESENTATION.stepPauseMs);
        }

        setMessage(t('combat.press_space_continue'));

        return new Promise(function (resolve)
        {
            if (token !== playbackToken)
            {
                resolve();
                return;
            }

            advanceResolver = resolve;
        });
    }

    function typewriterLog(text, token, lineClass)
    {
        return new Promise(function (resolve)
        {
            if (token !== playbackToken)
            {
                resolve();
                return;
            }

            var line = document.createElement('p');
            line.className = lineClass || 'combat-log-line combat-log-typing';
            logEl.appendChild(line);
            logEl.scrollTop = logEl.scrollHeight;

            if (!lineClass)
            {
                line.classList.add('combat-log-typing');
            }
            else if (lineClass.indexOf('combat-log-typing') === -1)
            {
                line.classList.add('combat-log-typing');
            }

            var index = 0;

            function tick()
            {
                if (token !== playbackToken)
                {
                    resolve();
                    return;
                }

                if (index >= text.length)
                {
                    line.classList.remove('combat-log-typing');
                    logEl.scrollTop = logEl.scrollHeight;
                    resolve();
                    return;
                }

                line.textContent += text.charAt(index);
                index += 1;
                logEl.scrollTop = logEl.scrollHeight;
                setTimeout(tick, COMBAT_PRESENTATION.textSpeedMs);
            }

            tick();
        });
    }

    function shouldBump(move)
    {
        return move.move_type === 'ability' && !!move.protagonist_type && move.move_hit !== 'N';
    }

    function unitCardForMove(move)
    {
        if (!move || !unitsEl)
        {
            return null;
        }

        var protagonistId = parseInt(move.id_protagonist, 10) || 0;

        if (protagonistId > 0)
        {
            var cardById = unitsEl.querySelector('[data-animal-id="' + protagonistId + '"]');

            if (cardById)
            {
                return cardById;
            }

            var playerAnimalId = parseInt(move.p_a_id, 10) || 0;
            var enemyAnimalId = parseInt(move.w_a_id, 10) || 0;

            if (protagonistId === playerAnimalId)
            {
                return unitsEl.querySelector('[data-side="player"]');
            }

            if (protagonistId === enemyAnimalId)
            {
                return unitsEl.querySelector('[data-side="enemy"]');
            }
        }

        if (move.protagonist_type === 'wild_animal')
        {
            return unitsEl.querySelector('[data-side="enemy"]');
        }

        return unitsEl.querySelector('[data-side="player"]');
    }

    function playBump(move, token)
    {
        if (!shouldBump(move))
        {
            return Promise.resolve();
        }

        var card = unitCardForMove(move);

        if (!card)
        {
            return Promise.resolve();
        }

        card.classList.add('combat-bump');

        return delay(COMBAT_PRESENTATION.bumpDurationMs).then(function ()
        {
            if (token !== playbackToken)
            {
                return;
            }

            card.classList.remove('combat-bump');
        });
    }

    function setUnitHp(side, hp, maxHp, animate)
    {
        var card = unitsEl.querySelector('[data-side="' + side + '"]');

        if (!card)
        {
            return;
        }

        var fill = card.querySelector('.hp-fill');
        var text = card.querySelector('.hp-text');
        var hpVal = parseInt(hp, 10) || 0;
        var maxVal = parseInt(maxHp, 10) || 1;
        var pct = Math.max(0, Math.min(100, (hpVal / maxVal) * 100));

        if (fill)
        {
            if (!animate)
            {
                fill.style.transition = 'none';
            }
            else
            {
                fill.style.transition = 'width ' + (COMBAT_PRESENTATION.hpAnimDurationMs / 1000) + 's ease';
            }

            fill.style.width = pct + '%';

            if (!animate)
            {
                void fill.offsetWidth;
                fill.style.transition = '';
            }
        }

        if (text)
        {
            text.textContent = t('stats.hp_value', { current: hpVal, max: maxVal });
        }
    }

    function applyUnitIdentity(side, stats)
    {
        var card = unitsEl.querySelector('[data-side="' + side + '"]');

        if (!card || !stats)
        {
            return;
        }

        var nameEl = card.querySelector('.unit-name');

        if (!nameEl)
        {
            return;
        }

        var name = side === 'player' ? stats.p_name : stats.w_name;
        var lvl = side === 'player' ? stats.p_lvl : stats.w_lvl;
        var animalId = side === 'player' ? stats.p_a_id : stats.w_a_id;

        nameEl.textContent = name + ' ' + t('team.lv_short', { level: lvl || 1 });

        if (animalId)
        {
            card.dataset.animalId = String(animalId);
        }
    }

    function animateHpTransition(fromStats, toStats, token)
    {
        if (!toStats)
        {
            return Promise.resolve();
        }

        if (!fromStats)
        {
            renderUnitsFromStats(toStats);
            return Promise.resolve();
        }

        renderUnitsFromStats(fromStats);

        return delay(40).then(function ()
        {
            if (token !== playbackToken)
            {
                return;
            }

            setUnitHp('player', toStats.p_hp, toStats.p_max_hp, true);
            setUnitHp('enemy', toStats.w_hp, toStats.w_max_hp, true);
            applyUnitIdentity('player', toStats);
            applyUnitIdentity('enemy', toStats);

            return delay(COMBAT_PRESENTATION.hpAnimDurationMs);
        });
    }

    function playMoveStep(move, prevMoveRow, token)
    {
        var prevStats = statsFromMove(prevMoveRow);
        var nextStats = statsFromMove(move);
        var lines = buildMoveNarration(move, prevMoveRow);

        return narrateLines(lines, token).then(function ()
        {
            return playBump(move, token);
        }).then(function ()
        {
            return animateHpTransition(prevStats, nextStats, token);
        }).then(function ()
        {
            return waitAdvance(token);
        });
    }

    function finishPresentation()
    {
        playbackRunning = false;
        advanceResolver = null;

        var stats = latestStats();

        if (stats)
        {
            renderUnitsFromStats(statsFromMove(stats));
            renderLogComplete(moves);
        }

        closeBtn.disabled = battle && battle.status === 'ongoing';

        if (!battle)
        {
            return;
        }

        if (battle.status !== 'ongoing')
        {
            setMessage(statusMessage(battle.status));
            fleeBtn.disabled = true;
            abilitiesEl.innerHTML = '';

            if (!blackoutHandled)
            {
                busy = false;
            }

            maybeHandleBlackout();
            return;
        }

        if (battle.type === 'pvp')
        {
            if (finalizePvpBattleIfNeeded())
            {
                return;
            }

            if (pvpPhase === 'animating')
            {
                pvpLockedTurn = 0;
                pvpResolvePending = false;
                pvpResolvedMoveCount = 0;
                enterPvpInputPhase(true);
                return;
            }

            if (pvpMeta && pvpMeta.submitted && !pvpMeta.turn_complete)
            {
                enterPvpLockedPhase();
                return;
            }

            if (pvpPhase === 'locked')
            {
                clearPvpActionMenu();
                updatePvpStatusBar();
                return;
            }

            enterPvpInputPhase();
            return;
        }

        if (!blackoutHandled)
        {
            busy = false;
            refreshActionMenu();
        }

        setMessage(t('combat.choose_action'));
    }

    function presentTurn(beforeMove, instant, movesToPlay)
    {
        playbackToken += 1;
        var token = playbackToken;
        var playable = movesToPlay || getPlayableMoves(moves);
        var stats = latestStats();

        if (!stats)
        {
            unitsEl.innerHTML = '<p>' + escapeHtml(t('combat.no_battle_data')) + '</p>';
            logEl.innerHTML = '';
            abilitiesEl.innerHTML = '';
            fleeBtn.disabled = true;
            closeBtn.disabled = false;
            busy = false;
            return;
        }

        closeBtn.disabled = battle.status === 'ongoing';
        abilitiesEl.innerHTML = '';
        fleeBtn.disabled = true;

        if (instant || !playable.length)
        {
            playbackRunning = false;
            renderUnitsFromStats(statsFromMove(stats));
            renderLogComplete(moves);
            finishPresentation();
            return;
        }

        playbackRunning = true;
        busy = true;

        if (battle.type === 'pvp')
        {
            pvpPhase = 'animating';
        }

        var initialStats = beforeMove ? statsFromMove(beforeMove) : statsFromMove(moves[0]);
        renderUnitsFromStats(initialStats);
        logEl.innerHTML = '';

        var chain = Promise.resolve();
        var prevMoveRow = beforeMove;

        playable.forEach(function (move)
        {
            chain = chain.then(function ()
            {
                if (token !== playbackToken)
                {
                    return;
                }

                return playMoveStep(move, prevMoveRow, token);
            }).then(function ()
            {
                prevMoveRow = move;
            });
        });

        chain.then(function ()
        {
            if (token !== playbackToken)
            {
                return;
            }

            finishPresentation();
        });
    }

    function renderUnitsFromStats(unitStats)
    {
        if (!unitStats)
        {
            unitsEl.innerHTML = '';
            return;
        }

        unitsEl.innerHTML = '';
        unitsEl.appendChild(buildUnitCard(unitStats.p_name, unitStats.p_lvl, unitStats.p_hp, unitStats.p_max_hp, false, unitStats.p_a_id));
        unitsEl.appendChild(buildUnitCard(unitStats.w_name, unitStats.w_lvl, unitStats.w_hp, unitStats.w_max_hp, true, unitStats.w_a_id));
    }

    function renderLogComplete(turnMoves)
    {
        logEl.innerHTML = '';

        (turnMoves || []).forEach(function (move, index)
        {
            if (!move.move_description || move.move_type === 'start')
            {
                return;
            }

            var prevMove = index > 0 ? turnMoves[index - 1] : null;

            buildMoveNarration(move, prevMove).forEach(function (line, lineIndex)
            {
                appendLogLine(
                    line,
                    lineIndex === 0 ? 'combat-log-line' : 'combat-log-line combat-log-followup'
                );
            });
        });
    }

    function render()
    {
        presentTurn(null, true);
    }

    function buildUnitCard(name, lvl, hp, maxHp, isEnemy, animalId)
    {
        var card = document.createElement('div');
        card.className = 'unit-card' + (isEnemy ? ' enemy' : '');
        card.dataset.side = isEnemy ? 'enemy' : 'player';

        if (animalId)
        {
            card.dataset.animalId = String(animalId);
        }

        var hpVal = parseInt(hp, 10) || 0;
        var maxVal = parseInt(maxHp, 10) || 1;
        var pct = Math.max(0, Math.min(100, (hpVal / maxVal) * 100));

        card.innerHTML =
            '<div class="unit-name">' + escapeHtml(name) + ' ' + escapeHtml(t('team.lv_short', { level: lvl })) + '</div>' +
            '<div class="hp-bar"><div class="hp-fill" style="width:' + pct + '%"></div></div>' +
            '<div class="hp-text">' + escapeHtml(t('stats.hp_value', { current: hpVal, max: maxVal })) + '</div>';

        return card;
    }

    function setMessage(text)
    {
        messageEl.textContent = text;
    }

    function escapeHtml(str)
    {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    return {
        init: init,
        start: start,
        resume: resume,
        hide: hide,
        isVisible: isVisible
    };
})();

