/**
 * Draggable target info panel (name + type).
 */
var AnimasterTarget = (function ()
{
    var panel = null;
    var wrap = null;
    var nameEl = null;
    var typeEl = null;
    var dragHandle = null;
    var closeBtn = null;
    var tradeBtn = null;
    var partyBtn = null;
    var duelBtn = null;

    var current = null;
    var userDragged = false;
    var drag = {
        active: false,
        offsetX: 0,
        offsetY: 0
    };

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function init()
    {
        panel = document.getElementById('target-panel');
        wrap = panel ? panel.parentElement : null;
        nameEl = document.getElementById('target-name');
        typeEl = document.getElementById('target-type');
        dragHandle = document.getElementById('target-drag-handle');
        closeBtn = document.getElementById('target-close');
        tradeBtn = document.getElementById('target-trade-btn');
        partyBtn = document.getElementById('target-party-btn');
        duelBtn = document.getElementById('target-duel-btn');

        if (!panel || !wrap || !nameEl || !typeEl || !dragHandle || !closeBtn)
        {
            return;
        }

        closeBtn.addEventListener('click', function (e)
        {
            e.stopPropagation();
            clear();
        });

        if (tradeBtn)
        {
            tradeBtn.addEventListener('click', function (e)
            {
                e.stopPropagation();

                if (!current || current.kind !== 'player')
                {
                    return;
                }

                if (typeof AnimasterTrade !== 'undefined')
                {
                    AnimasterTrade.requestToPlayer(current);
                }
            });
        }

        if (partyBtn)
        {
            partyBtn.addEventListener('click', function (e)
            {
                e.stopPropagation();

                if (!current || current.kind !== 'player')
                {
                    return;
                }

                if (typeof AnimasterParty !== 'undefined')
                {
                    AnimasterParty.requestToPlayer(current);
                }
            });
        }

        if (duelBtn)
        {
            duelBtn.addEventListener('click', function (e)
            {
                e.stopPropagation();

                if (!current || current.kind !== 'player')
                {
                    return;
                }

                if (typeof AnimasterDuel !== 'undefined')
                {
                    AnimasterDuel.requestToPlayer(current);
                }
            });
        }

        bindDrag();
        resetPosition();
    }

    function resetPosition()
    {
        panel.classList.remove('target-dragged');
        panel.style.left = '50%';
        panel.style.top = '10px';
        panel.style.transform = 'translateX(-50%)';
        userDragged = false;
    }

    function ensureAbsolutePosition()
    {
        if (panel.classList.contains('target-dragged'))
        {
            return;
        }

        var wrapRect = wrap.getBoundingClientRect();
        var rect = panel.getBoundingClientRect();

        panel.style.left = (rect.left - wrapRect.left) + 'px';
        panel.style.top = (rect.top - wrapRect.top) + 'px';
        panel.style.transform = 'none';
        panel.classList.add('target-dragged');
    }

    function bindDrag()
    {
        dragHandle.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0 || panel.hidden)
            {
                return;
            }

            if (e.target.closest('.target-close') || e.target.closest('.target-trade-btn') || e.target.closest('.target-party-btn') || e.target.closest('.target-duel-btn'))
            {
                return;
            }

            e.preventDefault();
            ensureAbsolutePosition();
            userDragged = true;

            var wrapRect = wrap.getBoundingClientRect();
            var rect = panel.getBoundingClientRect();

            drag.active = true;
            drag.offsetX = e.clientX - rect.left;
            drag.offsetY = e.clientY - rect.top;

            var onMove = function (ev)
            {
                if (!drag.active)
                {
                    return;
                }

                var left = ev.clientX - wrapRect.left - drag.offsetX;
                var top = ev.clientY - wrapRect.top - drag.offsetY;
                var maxLeft = Math.max(0, wrapRect.width - panel.offsetWidth);
                var maxTop = Math.max(0, wrapRect.height - panel.offsetHeight);

                panel.style.left = Math.min(maxLeft, Math.max(0, left)) + 'px';
                panel.style.top = Math.min(maxTop, Math.max(0, top)) + 'px';
            };

            var onUp = function ()
            {
                drag.active = false;
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        });
    }

    function applyKindClass(kind)
    {
        panel.classList.remove(
            'target-kind-self',
            'target-kind-player',
            'target-kind-npc',
            'target-kind-wild'
        );

        if (kind)
        {
            panel.classList.add('target-kind-' + kind);
        }
    }

    function show(info)
    {
        if (!panel || !info)
        {
            return;
        }

        current = info;

        if (!userDragged)
        {
            resetPosition();
        }

        nameEl.textContent = info.name || '—';
        typeEl.textContent = info.typeLabel || '—';
        applyKindClass(info.kind);

        if (tradeBtn)
        {
            tradeBtn.hidden = info.kind !== 'player';
        }

        if (partyBtn)
        {
            partyBtn.hidden = info.kind !== 'player';
        }

        if (duelBtn)
        {
            duelBtn.hidden = info.kind !== 'player';
        }

        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');
    }

    function clear()
    {
        if (!panel)
        {
            return;
        }

        current = null;
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');
        applyKindClass(null);
        resetPosition();
    }

    function isVisible()
    {
        return !!(panel && !panel.hidden);
    }

    function getTarget()
    {
        return current;
    }

    return {
        init: init,
        show: show,
        clear: clear,
        isVisible: isVisible,
        getTarget: getTarget
    };
})();
