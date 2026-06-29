/**
 * System log — append-only feed for server notifications (replaces alert popups).
 */
var AnimasterNotifications = (function ()
{
    var panel = null;
    var messagesEl = null;
    var collapseBtn = null;
    var timeToggleBtn = null;
    var playerRef = null;
    var busy = false;
    var collapsed = false;
    var showTimestamps = true;
    var maxLines = 200;
    var timePrefKey = 'animaster_syslog_times';

    var typeClassMap = {
        lvl_up: 'syslog-lvl_up',
        item: 'syslog-item',
        gold: 'syslog-gold',
        lvl_down: 'syslog-lvl_down',
        class_promotion: 'syslog-class_promotion'
    };

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function pad2(n)
    {
        return String(n).padStart(2, '0');
    }

    function parseNoteDate(note)
    {
        if (note && note.dt_c)
        {
            var parsed = new Date(note.dt_c);

            if (!isNaN(parsed.getTime()))
            {
                return parsed;
            }
        }

        return new Date();
    }

    function formatTimestamp(date)
    {
        var now = new Date();
        var sameDay = date.getFullYear() === now.getFullYear()
            && date.getMonth() === now.getMonth()
            && date.getDate() === now.getDate();
        var time = pad2(date.getHours()) + ':' + pad2(date.getMinutes()) + ':' + pad2(date.getSeconds());

        if (sameDay)
        {
            return time;
        }

        return pad2(date.getMonth() + 1) + '-' + pad2(date.getDate()) + ' ' + time;
    }

    function applyTimestampVisibility()
    {
        if (!panel)
        {
            return;
        }

        panel.classList.toggle('system-log-show-times', showTimestamps);

        if (timeToggleBtn)
        {
            timeToggleBtn.classList.toggle('is-active', showTimestamps);
            timeToggleBtn.setAttribute('aria-pressed', showTimestamps ? 'true' : 'false');
            timeToggleBtn.title = showTimestamps
                ? t('system_log.timestamps_hide')
                : t('system_log.timestamps_show');
        }
    }

    function init()
    {
        panel = document.getElementById('system-log-panel');
        messagesEl = document.getElementById('system-log-messages');
        collapseBtn = document.getElementById('system-log-collapse');
        timeToggleBtn = document.getElementById('system-log-time-toggle');

        if (!panel || !messagesEl)
        {
            return;
        }

        try
        {
            var stored = sessionStorage.getItem(timePrefKey);

            if (stored === '0')
            {
                showTimestamps = false;
            }
            else if (stored === '1')
            {
                showTimestamps = true;
            }
        }
        catch (e)
        {
            showTimestamps = true;
        }

        applyTimestampVisibility();

        if (timeToggleBtn)
        {
            timeToggleBtn.addEventListener('click', function ()
            {
                showTimestamps = !showTimestamps;

                try
                {
                    sessionStorage.setItem(timePrefKey, showTimestamps ? '1' : '0');
                }
                catch (e)
                {
                    // ignore
                }

                applyTimestampVisibility();
            });
        }

        if (collapseBtn)
        {
            collapseBtn.addEventListener('click', function ()
            {
                collapsed = !collapsed;
                panel.classList.toggle('system-log-collapsed', collapsed);
                collapseBtn.textContent = collapsed ? '+' : '\u2212';
                collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        }

        renderEmptyState();
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function typeClass(itemType)
    {
        var key = String(itemType || '').toLowerCase();

        return typeClassMap[key] || 'syslog-default';
    }

    function renderEmptyState()
    {
        if (!messagesEl || messagesEl.children.length > 0)
        {
            return;
        }

        var empty = document.createElement('p');
        empty.className = 'system-log-line system-log-empty';
        empty.textContent = t('system_log.empty');
        messagesEl.appendChild(empty);
    }

    function trimOldLines()
    {
        if (!messagesEl)
        {
            return;
        }

        var lines = messagesEl.querySelectorAll('.system-log-line:not(.system-log-empty)');

        while (lines.length > maxLines)
        {
            lines[0].parentNode.removeChild(lines[0]);
            lines = messagesEl.querySelectorAll('.system-log-line:not(.system-log-empty)');
        }
    }

    function scrollToBottom()
    {
        if (!messagesEl)
        {
            return;
        }

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendLine(note)
    {
        if (!messagesEl || !note)
        {
            return;
        }

        var text = note.description || '';

        if (!text)
        {
            return;
        }

        var empty = messagesEl.querySelector('.system-log-empty');

        if (empty)
        {
            empty.parentNode.removeChild(empty);
        }

        var when = parseNoteDate(note);
        var line = document.createElement('p');
        line.className = 'system-log-line ' + typeClass(note.item_type);

        var timeEl = document.createElement('time');
        timeEl.className = 'system-log-time';
        timeEl.dateTime = when.toISOString();
        timeEl.textContent = '[' + formatTimestamp(when) + ']';

        var textEl = document.createElement('span');
        textEl.className = 'system-log-text';
        textEl.textContent = text;

        line.appendChild(timeEl);
        line.appendChild(textEl);
        messagesEl.appendChild(line);
        trimOldLines();
        scrollToBottom();
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
                list.forEach(function (item)
                {
                    appendLine(item);
                });
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
            appendLine(item);
        });
    }

    function showLocal(description, itemType)
    {
        enqueue([{
            id_notification: 0,
            description: description,
            item_type: itemType || 'default',
            dt_c: new Date().toISOString()
        }]);
    }

    function isVisible()
    {
        return false;
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
