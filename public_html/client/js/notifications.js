/**
 * Notification queue (Unity IGManager / Notification.cs).
 */
var AnimasterNotifications = (function ()
{
    var overlay = null;
    var textEl = null;
    var metaEl = null;
    var dismissBtn = null;
    var queue = [];
    var playerRef = null;
    var showing = false;
    var busy = false;

    function init()
    {
        overlay = document.getElementById('notification-overlay');
        textEl = document.getElementById('notification-text');
        metaEl = document.getElementById('notification-meta');
        dismissBtn = document.getElementById('notification-dismiss');

        if (!overlay)
        {
            return;
        }

        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');

        if (dismissBtn)
        {
            dismissBtn.addEventListener('click', function ()
            {
                showNext();
            });
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'Enter' && showing && overlay && !overlay.hidden)
            {
                e.preventDefault();
                showNext();
            }
        });
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function fetch()
    {
        if (!playerRef || busy)
        {
            return Promise.resolve([]);
        }

        busy = true;

        return AnimasterApi.getNotifications(playerRef).then(function (list)
        {
            if (list && list.length)
            {
                enqueue(list);
            }

            return list || [];
        }).catch(function (err)
        {
            console.warn('[AnimasterNotifications] fetch failed:', err && err.message ? err.message : err);
            return [];
        }).finally(function ()
        {
            busy = false;
        });
    }

    function enqueue(items)
    {
        if (!items || !items.length)
        {
            return;
        }

        items.forEach(function (item)
        {
            queue.push(item);
        });

        if (!showing)
        {
            showNext();
        }
    }

    function showLocal(description, itemType, nome)
    {
        enqueue([{
            id_notification: 0,
            description: description,
            item_type: itemType || 'close',
            nome: nome || 'close'
        }]);
    }

    function showNext()
    {
        if (!overlay)
        {
            queue = [];
            showing = false;
            return;
        }

        if (!queue.length)
        {
            showing = false;
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
            return;
        }

        showing = true;
        var note = queue.shift();
        var label = note.nome || note.item_type || '';

        textEl.textContent = note.description || '';
        metaEl.textContent = (note.item_type && note.item_type !== 'close') ? label : '';

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
    }

    function isVisible()
    {
        return overlay && !overlay.hidden;
    }

    return {
        init: init,
        setPlayer: setPlayer,
        fetch: fetch,
        enqueue: enqueue,
        showLocal: showLocal,
        isVisible: isVisible
    };
})();
