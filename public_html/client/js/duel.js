/**
 * PvP 1v1 duel: challenge requests, accept/decline, enter combat.
 */
var AnimasterDuel = (function ()
{
    var REQUEST_SECONDS = 30;
    var POLL_MS = 1200;

    var requestOverlay = null;
    var requestTextEl = null;
    var requestTimerFill = null;
    var requestAcceptBtn = null;
    var requestDeclineBtn = null;

    var playerRef = null;
    var pollTimer = null;
    var busy = false;
    var incomingRequest = null;
    var incomingLocked = false;
    var incomingExpiresAt = 0;
    var outgoingRequest = null;
    var onBattleStart = null;
    var combatStarted = false;
    var expiryTimer = null;
    var dismissedRequestIds = {};

    function pausePoll()
    {
        if (pollTimer)
        {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function resumePoll()
    {
        if (pollTimer || !playerRef || incomingLocked)
        {
            return;
        }

        pollTimer = setInterval(poll, POLL_MS);
    }

    function stopExpiryTimer()
    {
        if (expiryTimer)
        {
            clearInterval(expiryTimer);
            expiryTimer = null;
        }
    }

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function init(options)
    {
        options = options || {};
        onBattleStart = options.onBattleStart || null;

        requestOverlay = document.getElementById('duel-request-overlay');
        requestTextEl = document.getElementById('duel-request-text');
        requestTimerFill = document.getElementById('duel-request-timer-fill');
        requestAcceptBtn = document.getElementById('duel-request-accept');
        requestDeclineBtn = document.getElementById('duel-request-decline');

        if (requestAcceptBtn)
        {
            requestAcceptBtn.addEventListener('click', function ()
            {
                respondIncoming(true);
            });
        }

        if (requestDeclineBtn)
        {
            requestDeclineBtn.addEventListener('click', function ()
            {
                respondIncoming(false);
            });
        }

        pollTimer = setInterval(poll, POLL_MS);
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function resetCombatFlag()
    {
        combatStarted = false;
    }

    function requestToPlayer(targetInfo)
    {
        if (!playerRef || !targetInfo || busy)
        {
            return;
        }

        var targetId = parseInt(targetInfo.id, 10) || 0;

        if (targetId <= 0)
        {
            return;
        }

        if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
        {
            return;
        }

        if (typeof AnimasterTrade !== 'undefined' && AnimasterTrade.isTradeOpen())
        {
            return;
        }

        busy = true;

        AnimasterApi.sendDuelRequest(playerRef, targetId).then(function ()
        {
            if (typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('duel.request_sent'), 'duel', '');
            }
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;

            if (!incomingLocked)
            {
                poll();
            }
        });
    }

    function errorText(code)
    {
        var key = String(code || '').toUpperCase();

        if (key === 'OFFLINE')
        {
            return t('duel.error_offline');
        }

        if (key === 'OUT_OF_RANGE')
        {
            return t('duel.error_range');
        }

        if (key === 'NO_TEAM' || key === 'TARGET_NO_TEAM')
        {
            return t('duel.error_no_team');
        }

        if (key === 'COOLDOWN')
        {
            return t('duel.error_cooldown');
        }

        if (key === 'BUSY' || key === 'ALREADY_PENDING')
        {
            return t('duel.error_busy');
        }

        if (key === 'EXPIRED' || key === 'NOT_FOUND')
        {
            return t('duel.expired');
        }

        return t('duel.error_generic');
    }

    function poll()
    {
        if (!playerRef || busy || incomingLocked)
        {
            return;
        }

        if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
        {
            return;
        }

        AnimasterApi.pollDuel(playerRef).then(function (data)
        {
            handlePoll(data);
        }).catch(function (err)
        {
            console.warn('[AnimasterDuel] poll failed:', err && err.message ? err.message : err);
        });
    }

    function handlePoll(data)
    {
        if (!data)
        {
            return;
        }

        if (incomingLocked)
        {
            return;
        }

        outgoingRequest = data.outgoing_request || null;

        if (data.incoming_requests && data.incoming_requests.length)
        {
            showIncomingRequest(data.incoming_requests[0]);
        }

        if (data.active_battle && !combatStarted && typeof onBattleStart === 'function')
        {
            combatStarted = true;
            hideIncomingRequest(true);
            onBattleStart({
                id_battle: data.active_battle.id_battle,
                battle_type: data.active_battle.battle_type || 'pvp'
            });
        }
    }

    function startExpiryTimer()
    {
        stopExpiryTimer();

        expiryTimer = setInterval(function ()
        {
            if (!incomingLocked)
            {
                stopExpiryTimer();
                return;
            }

            var secondsLeft = Math.max(0, (incomingExpiresAt - Date.now()) / 1000);
            updateRequestTimer(secondsLeft);

            if (secondsLeft <= 0)
            {
                hideIncomingRequest();
            }
        }, 250);
    }

    function showIncomingRequest(req)
    {
        if (!requestOverlay || !req || incomingLocked)
        {
            return;
        }

        var requestId = parseInt(req.id_duel_request, 10) || 0;

        if (requestId <= 0 || dismissedRequestIds[requestId])
        {
            return;
        }

        var secondsLeft = parseInt(req.seconds_left, 10);

        if (isNaN(secondsLeft) || secondsLeft <= 0)
        {
            return;
        }

        incomingRequest = req;
        incomingLocked = true;
        incomingExpiresAt = Date.now() + (secondsLeft * 1000);

        pausePoll();

        requestOverlay.hidden = false;
        requestOverlay.setAttribute('aria-hidden', 'false');

        if (requestTextEl)
        {
            requestTextEl.textContent = t('duel.incoming_title', { name: req.other_name || 'Player' });
        }

        updateRequestTimer(secondsLeft);
        startExpiryTimer();
    }

    function updateRequestTimer(secondsLeft)
    {
        if (!requestTimerFill)
        {
            return;
        }

        var pct = Math.max(0, Math.min(100, (secondsLeft / REQUEST_SECONDS) * 100));
        requestTimerFill.style.width = pct + '%';
    }

    function hideIncomingRequest(skipResume)
    {
        incomingLocked = false;
        incomingRequest = null;
        incomingExpiresAt = 0;
        stopExpiryTimer();

        if (requestOverlay)
        {
            requestOverlay.hidden = true;
            requestOverlay.setAttribute('aria-hidden', 'true');
        }

        if (!skipResume && !busy && !(typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible()))
        {
            resumePoll();
        }
    }

    function respondIncoming(accept)
    {
        if (!incomingRequest || !playerRef || busy)
        {
            return;
        }

        var requestId = incomingRequest.id_duel_request;

        busy = true;
        pausePoll();
        stopExpiryTimer();
        hideIncomingRequest(true);

        AnimasterApi.respondDuelRequest(playerRef, requestId, accept).then(function (result)
        {
            if (accept && result && result.id_battle && typeof onBattleStart === 'function')
            {
                combatStarted = true;
                onBattleStart({
                    id_battle: result.id_battle,
                    battle_type: result.battle_type || 'pvp'
                });
                return;
            }

            if (accept && (!result || !result.id_battle))
            {
                throw new Error('RESPOND_FAILED');
            }
        }).catch(function (err)
        {
            dismissedRequestIds[requestId] = true;
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;

            if (!combatStarted)
            {
                resumePoll();
            }
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        requestToPlayer: requestToPlayer,
        resetCombatFlag: resetCombatFlag
    };
})();
