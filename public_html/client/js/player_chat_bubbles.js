/**
 * Speech bubbles over players when chat messages arrive (3s display).
 */
var AnimasterPlayerChatBubbles = (function ()
{
    var SCALE = 4;
    var DURATION_MS = 3000;
    var BUBBLE_OFFSET_Y = 52;
    var SCREEN_MARGIN = 24;
    var MAX_TEXT_LEN = 72;

    var layer = null;
    var canvas = null;
    var bubbles = {};

    function init()
    {
        layer = document.getElementById('player-chat-bubbles');
        canvas = document.getElementById('game-canvas');
    }

    function channelClass(channel)
    {
        var map = {
            L: 'chat-ch-local',
            '@': 'chat-ch-whisper',
            '!': 'chat-ch-zone',
            '$': 'chat-ch-clan',
            '%': 'chat-ch-alliance',
            '#': 'chat-ch-party',
            '*': 'chat-ch-global'
        };

        return map[channel] || 'chat-ch-local';
    }

    function truncateText(text)
    {
        var s = String(text || '').trim();

        if (s.length <= MAX_TEXT_LEN)
        {
            return s;
        }

        return s.substring(0, MAX_TEXT_LEN - 1) + '\u2026';
    }

    function worldToScreen(wx, wz, player)
    {
        return {
            x: (wx - player.x) * SCALE + canvas.width * 0.5,
            y: (wz - player.z) * SCALE + canvas.height * 0.5
        };
    }

    function getOthers()
    {
        if (typeof AnimasterWorld !== 'undefined' && AnimasterWorld.getOthers)
        {
            return AnimasterWorld.getOthers();
        }

        return [];
    }

    function getSenderWorldPos(senderId)
    {
        var id = parseInt(senderId, 10);
        var player = typeof AnimasterWorld !== 'undefined' ? AnimasterWorld.getPlayer() : null;

        if (!player || !id)
        {
            return null;
        }

        if (id === parseInt(player.id_user_ig, 10))
        {
            return { x: player.x, z: player.z };
        }

        var others = getOthers();
        var i;

        for (i = 0; i < others.length; i++)
        {
            if (parseInt(others[i].id_player, 10) !== id)
            {
                continue;
            }

            var ox = parseFloat(others[i].serverPositionX);
            var oz = parseFloat(others[i].serverPositionZ);

            if (!isFinite(ox) || !isFinite(oz))
            {
                return null;
            }

            return { x: ox, z: oz };
        }

        return null;
    }

    function isOnScreen(wx, wz, player)
    {
        if (!canvas || !player)
        {
            return false;
        }

        var p = worldToScreen(wx, wz, player);

        return p.x >= -SCREEN_MARGIN
            && p.x <= canvas.width + SCREEN_MARGIN
            && p.y >= -SCREEN_MARGIN
            && p.y <= canvas.height + SCREEN_MARGIN;
    }

    function getPagePosition(wx, wz, player)
    {
        if (!canvas || !player)
        {
            return null;
        }

        var p = worldToScreen(wx, wz, player);
        var rect = canvas.getBoundingClientRect();
        var scaleX = rect.width / canvas.width;
        var scaleY = rect.height / canvas.height;

        return {
            x: rect.left + p.x * scaleX,
            y: rect.top + p.y * scaleY - BUBBLE_OFFSET_Y
        };
    }

    function positionEntry(key)
    {
        var entry = bubbles[key];
        var player = typeof AnimasterWorld !== 'undefined' ? AnimasterWorld.getPlayer() : null;

        if (!entry || !entry.el || !player)
        {
            return;
        }

        var worldPos = getSenderWorldPos(parseInt(key, 10));

        if (!worldPos || !isOnScreen(worldPos.x, worldPos.z, player))
        {
            entry.el.hidden = true;
            return;
        }

        var screen = getPagePosition(worldPos.x, worldPos.z, player);

        if (!screen)
        {
            entry.el.hidden = true;
            return;
        }

        entry.el.hidden = false;
        entry.el.style.left = screen.x + 'px';
        entry.el.style.top = screen.y + 'px';
    }

    function push(senderId, text, channel)
    {
        var id = parseInt(senderId, 10);
        var player = typeof AnimasterWorld !== 'undefined' ? AnimasterWorld.getPlayer() : null;

        if (!id || !layer || !text || !player)
        {
            return;
        }

        var worldPos = getSenderWorldPos(id);

        if (!worldPos || !isOnScreen(worldPos.x, worldPos.z, player))
        {
            return;
        }

        var key = String(id);
        var entry = bubbles[key];
        var cssClass = 'player-chat-bubble ' + channelClass(channel);
        var displayText = truncateText(text);

        if (!entry)
        {
            var el = document.createElement('div');
            el.className = cssClass;
            el.textContent = displayText;
            layer.appendChild(el);
            bubbles[key] = {
                el: el,
                expiresAt: Date.now() + DURATION_MS
            };
        }
        else
        {
            entry.el.className = cssClass;
            entry.el.textContent = displayText;
            entry.expiresAt = Date.now() + DURATION_MS;
        }

        positionEntry(key);
    }

    function update()
    {
        if (!layer)
        {
            return;
        }

        var now = Date.now();

        Object.keys(bubbles).forEach(function (key)
        {
            var entry = bubbles[key];

            if (!entry)
            {
                return;
            }

            if (now >= entry.expiresAt)
            {
                if (entry.el && entry.el.parentNode)
                {
                    entry.el.parentNode.removeChild(entry.el);
                }

                delete bubbles[key];
                return;
            }

            positionEntry(key);
        });
    }

    return {
        init: init,
        push: push,
        update: update
    };
})();
