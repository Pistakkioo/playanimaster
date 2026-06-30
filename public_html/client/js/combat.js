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
    var partyPveMeta = null;
    var partyPvePollTimer = null;
    var abilityCacheKey = '';
    var abilityCacheList = [];

    var menuMode = 'main';
    var selectedItemTypeId = 0;

    var COMBAT_PRESENTATION = {
        textSpeedMs: 28,
        bumpDurationMs: 220,
        hpAnimDurationMs: 450,
        stepPauseMs: 400,
        autoAdvance: false,
        skipAnimations: false
    };

    var playbackToken = 0;
    var loadTurnToken = 0;
    var playbackRunning = false;
    var advanceResolver = null;
    var autoAdvanceEl = null;
    var skipAnimationsEl = null;

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

    function elementDataFromAnimal(animal)
    {
        if (!animal)
        {
            return {};
        }

        return {
            id_element: animal.id_element,
            element: animal.element,
            element_color: animal.element_color
        };
    }

    function elementDataFromStats(stats, side)
    {
        if (!stats)
        {
            return {};
        }

        if (side === 'player')
        {
            return {
                id_element: stats.p_id_element,
                element: stats.p_element,
                element_color: stats.p_element_color
            };
        }

        return {
            id_element: stats.w_id_element,
            element: stats.w_element,
            element_color: stats.w_element_color
        };
    }

    function appendCombatMenuRow(btn, animal, label)
    {
        btn.textContent = '';

        var row = document.createElement('span');
        row.className = 'combat-menu-row';

        if (typeof AnimasterElements !== 'undefined')
        {
            row.appendChild(AnimasterElements.createIcon(elementDataFromAnimal(animal), 'element-icon--md'));
        }

        var text = document.createElement('span');
        text.className = 'combat-menu-row-text';
        text.textContent = label;
        row.appendChild(text);
        btn.appendChild(row);

        if (animal && animal.element)
        {
            btn.title = animal.element + ' ' + (animal.species || animal.nickname || '');
        }
    }

    function setUnitElementIcon(card, elementData)
    {
        if (!card)
        {
            return;
        }

        var slot = card.querySelector('.unit-element-slot');

        if (!slot)
        {
            return;
        }

        slot.innerHTML = '';

        if (typeof AnimasterElements === 'undefined')
        {
            return;
        }

        if (!elementData || (!elementData.element && !(parseInt(elementData.id_element, 10) > 0)))
        {
            return;
        }

        slot.appendChild(AnimasterElements.createIcon(elementData, 'element-icon--lg'));
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

        skipAnimationsEl = document.getElementById('combat-skip-animations');

        if (skipAnimationsEl)
        {
            COMBAT_PRESENTATION.skipAnimations = localStorage.getItem('animaster_combat_skip') === '1';
            skipAnimationsEl.checked = COMBAT_PRESENTATION.skipAnimations;
            skipAnimationsEl.addEventListener('change', function ()
            {
                COMBAT_PRESENTATION.skipAnimations = skipAnimationsEl.checked;
                localStorage.setItem('animaster_combat_skip', skipAnimationsEl.checked ? '1' : '0');
            });
        }
    }

    function isSoloPveFastPresentation()
    {
        return !!(battle
            && battle.type !== 'pvp'
            && COMBAT_PRESENTATION.skipAnimations);
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

    function syncPartyPveTurnFromMeta()
    {
        if (!battle || battle.type !== 'party_pve' || !partyPveMeta)
        {
            return;
        }

        var serverTurn = parseInt(partyPveMeta.current_turn, 10);

        if (!isNaN(serverTurn) && serverTurn >= 0)
        {
            battle.turn = serverTurn + 1;
        }
    }

    function partyPveMetaAllowsAct()
    {
        if (!partyPveMeta || !player)
        {
            return false;
        }

        if (partyPveMeta.can_act === true || partyPveMeta.can_act === 1)
        {
            return true;
        }

        var awaiting = parseInt(partyPveMeta.awaiting_user_ig, 10) || 0;
        var me = parseInt(player.id_user_ig, 10) || 0;

        return awaiting > 0 && awaiting === me;
    }

    function partyPveIsMyTurn()
    {
        return partyPveMetaAllowsAct() && !busy;
    }

    function partyPveCanFleeNow()
    {
        return !!(partyPveMeta
            && partyPveMeta.is_leader
            && battle
            && battle.status === 'ongoing'
            && !busy);
    }

    function updatePartyPveFleeButton()
    {
        if (!fleeBtn || !battle || battle.type !== 'party_pve')
        {
            return;
        }

        fleeBtn.disabled = !partyPveCanFleeNow();
    }

    function showPartyPveActionMenu()
    {
        if (!abilitiesEl || !battle || battle.type !== 'party_pve')
        {
            return;
        }

        updatePartyPveFleeButton();

        if (!partyPveMetaAllowsAct())
        {
            abilitiesEl.innerHTML = '';
            return;
        }

        if (battle.status !== 'ongoing')
        {
            abilitiesEl.innerHTML = '';
            return;
        }

        if (menuMode === 'fight')
        {
            showFightMenu();
        }
        else
        {
            showMainMenu();
        }
    }

    function mergePartyPveMoveHistory(existing, incoming)
    {
        var merged = [];
        var seen = {};

        function pushMove(move)
        {
            if (!move)
            {
                return;
            }

            var id = parseInt(move.id_battle_party_pve_move, 10) || 0;
            var key = id > 0
                ? 'id:' + id
                : 't:' + String(move.turn) + ':' + String(move.order_in_turn || 0) + ':' + String(move.move_type || '');

            if (seen[key])
            {
                return;
            }

            seen[key] = true;
            merged.push(move);
        }

        (existing || []).forEach(pushMove);
        (incoming || []).forEach(pushMove);

        merged.sort(function (a, b)
        {
            var turnCmp = (parseInt(a.turn, 10) || 0) - (parseInt(b.turn, 10) || 0);

            if (turnCmp !== 0)
            {
                return turnCmp;
            }

            return (parseInt(a.order_in_turn, 10) || 0) - (parseInt(b.order_in_turn, 10) || 0);
        });

        return merged;
    }

    function isPartyActorFainted()
    {
        if (!partyPveMeta || !partyPveMeta.party_allies || !player)
        {
            return false;
        }

        var me = parseInt(player.id_user_ig, 10) || 0;
        var awaiting = parseInt(partyPveMeta.awaiting_user_ig, 10) || 0;
        var ally = null;

        partyPveMeta.party_allies.forEach(function (entry)
        {
            if (parseInt(entry.id_user_ig, 10) !== me)
            {
                return;
            }

            if (entry.is_actor || (awaiting > 0 && awaiting === me) || partyPveMetaAllowsAct())
            {
                ally = entry;
            }
        });

        if (!ally)
        {
            return false;
        }

        return !!ally.fainted || (parseInt(ally.hp, 10) || 0) <= 0;
    }

    function normalizeBattleResponse(result)
    {
        if (battle && battle.type === 'pvp' && result && result.moves)
        {
            pvpMeta = result.meta || {};
            syncPvpTurnFromMeta();
            return result.moves;
        }

        if (battle && battle.type === 'party_pve' && result && result.moves !== undefined)
        {
            partyPveMeta = result.meta || {};
            syncPartyPveTurnFromMeta();

            if (Array.isArray(result.moves) && result.moves.length)
            {
                moves = mergePartyPveMoveHistory(moves, result.moves);
            }

            return moves;
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

        if (battle.type === 'party_pve')
        {
            return partyPveIsMyTurn();
        }

        return true;
    }

    function stopPartyPvePoll()
    {
        if (partyPvePollTimer)
        {
            clearInterval(partyPvePollTimer);
            partyPvePollTimer = null;
        }
    }

    function enterPartyPveInputPhase()
    {
        stopPartyPvePoll();
        busy = false;
        resetMenu();

        var stats = latestStats();

        if (stats)
        {
            renderUnitsFromStats(statsFromMove(stats));
        }
        else if (partyPveMeta && partyPveMeta.party_allies && partyPveMeta.party_allies.length)
        {
            renderPartyPveUnits({});
        }

        if (partyPveMetaAllowsAct())
        {
            showPartyPveActionMenu();
            setMessage(t('combat.choose_action'));
            return;
        }

        abilitiesEl.innerHTML = '';
        updatePartyPveFleeButton();
        setMessage(partyPveWaitingMessage());
        startPartyPvePoll();
    }

    function applyPartyPveWaitingResponse(meta)
    {
        partyPveMeta = meta || partyPveMeta || {};
        syncPartyPveTurnFromMeta();
        busy = false;

        if (partyPveMetaAllowsAct())
        {
            stopPartyPvePoll();

            if (menuMode === 'fight')
            {
                showFightMenu();
            }
            else
            {
                showPartyPveActionMenu();
            }

            setMessage(t('combat.choose_action'));
            return;
        }

        if (partyPveMeta.party_allies && partyPveMeta.party_allies.length)
        {
            renderPartyPveUnits({});
        }

        abilitiesEl.innerHTML = '';
        updatePartyPveFleeButton();
        setMessage(partyPveWaitingMessage());
        startPartyPvePoll();
    }

    function partyPveWaitingMessage()
    {
        if (!partyPveMeta)
        {
            return t('party_pve.waiting_turn');
        }

        if (partyPveMeta.actor_side === 'wild')
        {
            return t('party_pve.waiting_wild');
        }

        if (partyPveMeta.actor_display_name)
        {
            return t('party_pve.waiting_player', { name: partyPveMeta.actor_display_name });
        }

        return t('party_pve.waiting_turn');
    }

    function pollPartyPveTurn()
    {
        if (!battle || battle.type !== 'party_pve' || battle.status !== 'ongoing' || busy || !player)
        {
            return;
        }

        if (partyPveMetaAllowsAct())
        {
            stopPartyPvePoll();
            return;
        }

        AnimasterApi.getBattleInfo({
            id_user_ig: player.id_user_ig || 0,
            id_battle: battle.id,
            battle_type: battle.type,
            turn: battle.turn,
            restarting_old_battle: 'N',
            lang: AnimasterApi.LANG
        }).then(function (result)
        {
            if (!battle || battle.type !== 'party_pve')
            {
                return;
            }

            if (result && result.waiting)
            {
                applyPartyPveWaitingResponse(result.meta);
                return;
            }

            moves = normalizeBattleResponse(result);

            if (!moves.length)
            {
                return;
            }

            stopPartyPvePoll();
            applyStateFromMoves({ deferTerminalUi: true });
            presentTurn(null, true);
        }).catch(function ()
        {
            /* keep polling */
        });
    }

    function startPartyPvePoll()
    {
        stopPartyPvePoll();

        if (!battle || battle.type !== 'party_pve' || battle.status !== 'ongoing')
        {
            return;
        }

        if (partyPveMetaAllowsAct())
        {
            return;
        }

        partyPvePollTimer = setInterval(pollPartyPveTurn, 1500);
        pollPartyPveTurn();
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
        stopPartyPvePoll();
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
        partyPveMeta = null;
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

    function abortFailedResume(reason)
    {
        console.warn('[AnimasterCombat] battle resume aborted:', reason || 'unknown');
        hide();
    }

    function loadTurn(turn, restarting)
    {
        if (!battle || !player)
        {
            return;
        }

        loadTurnToken += 1;
        var token = loadTurnToken;

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
            if (token !== loadTurnToken)
            {
                return;
            }

            if (battle && battle.type === 'party_pve' && result && result.waiting)
            {
                applyPartyPveWaitingResponse(result.meta);
                return;
            }

            moves = normalizeBattleResponse(result);

            if (!moves.length)
            {
                abortFailedResume(t('combat.load_failed'));
                return;
            }

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

            if (battle.type === 'party_pve')
            {
                presentTurn(null, true);
                return;
            }

            advanceTurnCounter();
            presentTurn(null, true);
        }).catch(function (err)
        {
            if (token !== loadTurnToken)
            {
                return;
            }

            abortFailedResume(err && err.message ? err.message : t('combat.load_failed'));
        }).finally(function ()
        {
            if (!blackoutHandled && !playbackRunning && battle && battle.type === 'party_pve' && busy)
            {
                busy = false;
                enterPartyPveInputPhase();
            }
            else if (!blackoutHandled && !playbackRunning && battle && battle.type !== 'pvp' && battle.type !== 'party_pve')
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

        if (battle.type === 'party_pve')
        {
            syncPartyPveTurnFromMeta();
        }
        else
        {
            setTurnFromMove(last);
        }

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
        setMessage(battle && battle.type === 'pvp'
            ? t('duel.recovering')
            : t('combat.status_blackout_loading'));
        abilitiesEl.innerHTML = '';
        fleeBtn.disabled = true;
        closeBtn.disabled = true;

        onBlackout().then(function ()
        {
            if (battle && battle.type === 'pvp')
            {
                setMessage(t('duel.lose_teleported'));
                closeBtn.disabled = false;
                busy = false;
                return;
            }

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
        if (battle.status !== 'ongoing')
        {
            abilitiesEl.innerHTML = '';
            fleeBtn.disabled = true;
            return;
        }

        if (battle.type === 'party_pve')
        {
            if (!partyPveMetaAllowsAct())
            {
                updatePartyPveFleeButton();
                abilitiesEl.innerHTML = '';
                return;
            }

            updatePartyPveFleeButton();

            if (menuMode === 'fight')
            {
                showFightMenu();
            }
            else
            {
                showMainMenu();
            }

            return;
        }

        if (!latestStats())
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

        var canFlee = true;

        fleeBtn.disabled = busy || !canFlee;

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

        var fainted = battle && battle.type === 'party_pve'
            ? isPartyActorFainted()
            : isActiveAnimalFainted();

        if (battle && battle.type === 'party_pve')
        {
            appendMenuButton(t('combat.fight'), t('combat.fight_hint'), function ()
            {
                showFightMenu();
            }, fainted);
            return;
        }

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

    function resolveActiveAnimalForMenu(stats)
    {
        if (battle && battle.type === 'party_pve' && partyPveMeta && partyPveMeta.party_allies)
        {
            var actor = null;
            var me = parseInt(player.id_user_ig, 10) || 0;
            var awaiting = parseInt(partyPveMeta.awaiting_user_ig, 10) || 0;

            partyPveMeta.party_allies.some(function (ally)
            {
                if (parseInt(ally.id_user_ig, 10) !== me)
                {
                    return false;
                }

                if (ally.is_actor || (awaiting > 0 && awaiting === me) || partyPveMetaAllowsAct())
                {
                    actor = ally;
                    return true;
                }

                return false;
            });

            if (actor)
            {
                return {
                    id_animal: actor.id_animal,
                    lvl: actor.lvl
                };
            }
        }

        return {
            id_animal: parseInt(stats.p_a_id, 10) || 0,
            lvl: stats.p_a_lvl
        };
    }

    function showFightMenu()
    {
        menuMode = 'fight';
        setMessage(t('combat.choose_ability'));

        var stats = latestStats();
        var activeAnimal = resolveActiveAnimalForMenu(stats || {});

        if (!activeAnimal.id_animal)
        {
            showMainMenu();
            return;
        }
        var cacheKey = String(activeAnimal.id_animal) + ':' + String(activeAnimal.lvl);

        if (cacheKey === abilityCacheKey && abilityCacheList.length)
        {
            abilities = abilityCacheList.slice();
            renderFightMenu();
            return;
        }

        abilitiesEl.innerHTML = '<span class="combat-loading">' + escapeHtml(t('combat.loading_abilities')) + '</span>';

        AnimasterApi.getAbilityList(player.id_user_ig, activeAnimal.id_animal, activeAnimal.lvl).then(function (list)
        {
            if (menuMode !== 'fight' || (battle.type === 'pvp' && pvpPhase !== 'input'))
            {
                return;
            }

            if (battle.type === 'party_pve' && !partyPveMetaAllowsAct())
            {
                return;
            }

            var currentStats = latestStats();
            var currentAnimal = resolveActiveAnimalForMenu(currentStats);
            var currentKey = String(currentAnimal.id_animal) + ':' + String(currentAnimal.lvl);

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

            if (battle.type === 'party_pve' && !partyPveMetaAllowsAct())
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

        if (battle.type === 'party_pve' && !partyPveMetaAllowsAct())
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
                appendCombatMenuRow(btn, animal, label);
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

                appendCombatMenuRow(btn, animal, label);
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
        if (!battle || battle.status !== 'ongoing' || busy)
        {
            return;
        }

        var isPartyFlee = battle.type === 'party_pve'
            && type === 'action'
            && parseInt(id, 10) === 4;

        if (isPartyFlee)
        {
            if (!partyPveCanFleeNow())
            {
                return;
            }
        }
        else if (!canActInBattle())
        {
            return;
        }

        if (battle.type === 'pvp' && pvpPhase !== 'input')
        {
            return;
        }

        stopPartyPvePoll();

        if (battle.type === 'pvp')
        {
            stopPvpPoll();
            pvpLockedTurn = battle.turn;
            pvpPhase = 'locked';
            pvpResolvePending = false;
            clearPvpActionMenu();
        }

        busy = true;
        setMessage(t('combat.resolving_turn'));

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

            if (battle.type === 'party_pve')
            {
                presentTurn(beforeMove, false);
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
            p_element: move.p_a_element || '',
            p_id_element: parseInt(move.p_a_id_element, 10) || 0,
            p_element_color: move.p_a_element_color || '',
            w_name: move.w_a_species || t('combat.unit_wild'),
            w_a_id: parseInt(move.w_a_id, 10) || 0,
            w_lvl: move.w_a_lvl,
            w_hp: parseInt(move.w_a_res_hp, 10) || 0,
            w_max_hp: parseInt(move.w_a_res_max_hp, 10) || 1,
            w_element: move.w_a_element || '',
            w_id_element: parseInt(move.w_a_id_element, 10) || 0,
            w_element_color: move.w_a_element_color || ''
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

    function sideUnitId(move, side)
    {
        if (!move)
        {
            return 0;
        }

        var field = side === 'player' ? 'p_a_id' : 'w_a_id';

        return parseInt(move[field], 10) || 0;
    }

    function sideActiveUnitChanged(prevMove, move, side)
    {
        var prevId = sideUnitId(prevMove, side);
        var nextId = sideUnitId(move, side);

        return prevId > 0 && nextId > 0 && prevId !== nextId;
    }

    function appendStatChangeLines(lines, move, prevMove, side)
    {
        if (!prevMove)
        {
            return;
        }

        if (sideActiveUnitChanged(prevMove, move, side))
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

    function applyHpToCard(card, hp, maxHp, animate)
    {
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

    function setUnitHpByAnimalId(animalId, hp, maxHp, animate)
    {
        if (!unitsEl || !animalId)
        {
            return;
        }

        var card = unitsEl.querySelector('[data-animal-id="' + animalId + '"]');
        applyHpToCard(card, hp, maxHp, animate);
    }

    function syncPartyPveHpFromMove(stats)
    {
        if (!partyPveMeta || !stats)
        {
            return;
        }

        var partyId = parseInt(stats.p_a_id, 10) || 0;
        var wildId = parseInt(stats.w_a_id, 10) || 0;
        var partyHp = parseInt(stats.p_hp, 10) || 0;
        var partyMaxHp = parseInt(stats.p_max_hp, 10) || 1;
        var wildHp = parseInt(stats.w_hp, 10) || 0;
        var wildMaxHp = parseInt(stats.w_max_hp, 10) || 1;

        (partyPveMeta.party_allies || []).forEach(function (ally)
        {
            if (parseInt(ally.id_animal, 10) === partyId)
            {
                ally.hp = partyHp;
                ally.max_hp = partyMaxHp;
                ally.fainted = partyHp <= 0;
            }
        });

        (partyPveMeta.wild_combatants || []).forEach(function (wild)
        {
            if (parseInt(wild.id_animal, 10) === wildId)
            {
                wild.hp = wildHp;
                wild.max_hp = wildMaxHp;
                wild.fainted = wildHp <= 0;
            }
        });
    }

    function decoratePartyPveUnitCard(card, unit, isWild)
    {
        if (unit.is_actor)
        {
            card.classList.add('unit-card-actor');
        }

        if (unit.fainted)
        {
            card.classList.add('unit-card-fainted');
        }

        if (!isWild && unit.is_self)
        {
            card.classList.add('unit-card-self');
        }
    }

    function renderPartyPveUnits(unitStats)
    {
        unitsEl.className = 'combat-units combat-units-party-cols';
        unitsEl.innerHTML = '';

        var partyCol = document.createElement('div');
        partyCol.className = 'combat-units-column combat-units-party-col';
        var wildCol = document.createElement('div');
        wildCol.className = 'combat-units-column combat-units-wild-col';

        (partyPveMeta.party_allies || []).forEach(function (ally)
        {
            var card = buildUnitCard(
                ally.nickname || ally.species || t('combat.unit_your_animal'),
                ally.lvl,
                ally.hp,
                ally.max_hp,
                false,
                ally.id_animal,
                { id_element: ally.id_element || 0 },
                { subtitle: ally.display_name || '' }
            );
            decoratePartyPveUnitCard(card, ally, false);
            partyCol.appendChild(card);
        });

        var wilds = partyPveMeta.wild_combatants;

        if (!wilds || !wilds.length)
        {
            wilds = [{
                id_animal: parseInt(unitStats.w_a_id, 10) || 0,
                nickname: unitStats.w_name,
                species: unitStats.w_name,
                lvl: unitStats.w_lvl,
                hp: unitStats.w_hp,
                max_hp: unitStats.w_max_hp,
                id_element: parseInt(unitStats.w_id_element, 10) || 0,
                fainted: (parseInt(unitStats.w_hp, 10) || 0) <= 0,
                is_actor: false
            }];
        }

        wilds.forEach(function (wild)
        {
            var card = buildUnitCard(
                wild.nickname || wild.species || t('combat.unit_wild'),
                wild.lvl,
                wild.hp,
                wild.max_hp,
                true,
                wild.id_animal,
                { id_element: wild.id_element || 0 }
            );
            decoratePartyPveUnitCard(card, wild, true);
            wildCol.appendChild(card);
        });

        unitsEl.appendChild(partyCol);
        unitsEl.appendChild(wildCol);
    }

    function setUnitHp(side, hp, maxHp, animate)
    {
        var card = unitsEl.querySelector('[data-side="' + side + '"]');
        applyHpToCard(card, hp, maxHp, animate);
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
        setUnitElementIcon(card, elementDataFromStats(stats, side));

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

            if (battle && battle.type === 'party_pve' && partyPveMeta)
            {
                syncPartyPveHpFromMove(toStats);
                setUnitHpByAnimalId(parseInt(toStats.p_a_id, 10) || 0, toStats.p_hp, toStats.p_max_hp, true);
                setUnitHpByAnimalId(parseInt(toStats.w_a_id, 10) || 0, toStats.w_hp, toStats.w_max_hp, true);
                return delay(COMBAT_PRESENTATION.hpAnimDurationMs);
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

        if (battle.type === 'party_pve')
        {
            if (partyPveMeta && partyPveMeta.battle_finished)
            {
                if (!blackoutHandled)
                {
                    busy = false;
                }

                maybeHandleBlackout();
                return;
            }

            enterPartyPveInputPhase();
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

        if (instant || !playable.length || isSoloPveFastPresentation())
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
            unitsEl.className = 'combat-units';
            return;
        }

        if (battle && battle.type === 'party_pve' && partyPveMeta)
        {
            renderPartyPveUnits(unitStats);
            return;
        }

        unitsEl.className = 'combat-units';
        unitsEl.innerHTML = '';
        unitsEl.appendChild(buildUnitCard(
            unitStats.p_name,
            unitStats.p_lvl,
            unitStats.p_hp,
            unitStats.p_max_hp,
            false,
            unitStats.p_a_id,
            elementDataFromStats(unitStats, 'player')
        ));
        unitsEl.appendChild(buildUnitCard(
            unitStats.w_name,
            unitStats.w_lvl,
            unitStats.w_hp,
            unitStats.w_max_hp,
            true,
            unitStats.w_a_id,
            elementDataFromStats(unitStats, 'enemy')
        ));
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

    function buildUnitCard(name, lvl, hp, maxHp, isEnemy, animalId, elementData, options)
    {
        options = options || {};
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
        var ownerHtml = options.subtitle
            ? '<div class="unit-owner">' + escapeHtml(options.subtitle) + '</div>'
            : '';

        card.innerHTML =
            ownerHtml +
            '<div class="unit-name-row">' +
                '<span class="unit-element-slot"></span>' +
                '<span class="unit-name">' + escapeHtml(name) + ' ' + escapeHtml(t('team.lv_short', { level: lvl })) + '</span>' +
            '</div>' +
            '<div class="hp-bar"><div class="hp-fill" style="width:' + pct + '%"></div></div>' +
            '<div class="hp-text">' + escapeHtml(t('stats.hp_value', { current: hpVal, max: maxVal })) + '</div>';

        setUnitElementIcon(card, elementData || {});

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

