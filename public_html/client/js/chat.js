/**
 * In-game chat overlay (multi-channel, tab filters, position toggle).
 */
var AnimasterChat = (function ()
{
    var CHANNELS = ['L', '@', '!', '$', '%', '#', '*'];

    var TABS = [
        {
            id: 'main',
            labelKey: 'chat.tab_main',
            defaults: { L: true, '@': true, '!': true, '$': false, '%': false, '#': false, '*': false }
        },
        {
            id: 'whisper',
            labelKey: 'chat.tab_whisper',
            defaults: { L: false, '@': true, '!': false, '$': false, '%': false, '#': false, '*': false }
        },
        {
            id: 'zone',
            labelKey: 'chat.tab_zone',
            defaults: { L: false, '@': false, '!': true, '$': false, '%': false, '#': false, '*': false }
        },
        {
            id: 'clan',
            labelKey: 'chat.tab_clan',
            defaults: { L: false, '@': false, '!': false, '$': true, '%': false, '#': false, '*': false }
        },
        {
            id: 'party',
            labelKey: 'chat.tab_party',
            defaults: { L: false, '@': false, '!': false, '$': false, '%': false, '#': true, '*': false }
        },
        {
            id: 'alliance',
            labelKey: 'chat.tab_alliance',
            defaults: { L: false, '@': false, '!': false, '$': false, '%': true, '#': false, '*': false }
        },
        {
            id: 'global',
            labelKey: 'chat.tab_global',
            defaults: { L: false, '@': false, '!': false, '$': false, '%': false, '#': false, '*': true }
        }
    ];

    var panel = null;
    var messagesEl = null;
    var inputEl = null;
    var formEl = null;
    var tabsEl = null;
    var settingsPanel = null;
    var settingsChecksEl = null;
    var settingsTitleEl = null;
    var whisperSuggestEl = null;

    var playerRef = null;
    var allMessages = [];
    var whisperPartners = [];
    var whisperSuggestOpen = false;
    var whisperSuggestIndex = 0;
    var whisperSuggestMatches = [];
    var WHISPER_SUGGEST_NEW = null;
    var whisperLastInputValue = '';
    var whisperSuggestProgrammatic = false;
    var lastMessageId = 0;
    var chatSince = '';
    var activeTabId = 'main';
    var tabFilters = {};
    var pollTimer = null;
    var busy = false;
    var collapsed = false;
    var viewMode = 'expanded';
    var modeBeforeIcon = 'expanded';
    var positionSide = 'left';

    var STORAGE_FILTERS = 'animaster_chat_tab_filters';
    var STORAGE_TAB = 'animaster_chat_active_tab';
    var STORAGE_POSITION = 'animaster_chat_position';
    var STORAGE_COLLAPSED = 'animaster_chat_collapsed';
    var STORAGE_VIEW_MODE = 'animaster_chat_view_mode';

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function channelLabel(channel)
    {
        var map = {
            L: 'chat.channel_local',
            '@': 'chat.channel_whisper',
            '!': 'chat.channel_zone',
            '$': 'chat.channel_clan',
            '%': 'chat.channel_alliance',
            '#': 'chat.channel_party',
            '*': 'chat.channel_global'
        };

        return t(map[channel] || 'chat.channel_local');
    }

    function cssChannelClass(channel)
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

    function cloneDefaults(defaults)
    {
        var copy = {};

        CHANNELS.forEach(function (ch)
        {
            copy[ch] = !!defaults[ch];
        });

        return copy;
    }

    function loadFilters()
    {
        var stored = null;

        try
        {
            stored = JSON.parse(localStorage.getItem(STORAGE_FILTERS) || 'null');
        }
        catch (e)
        {
            stored = null;
        }

        tabFilters = {};

        TABS.forEach(function (tab)
        {
            tabFilters[tab.id] = cloneDefaults(tab.defaults);

            if (stored && stored[tab.id])
            {
                CHANNELS.forEach(function (ch)
                {
                    if (typeof stored[tab.id][ch] === 'boolean')
                    {
                        tabFilters[tab.id][ch] = stored[tab.id][ch];
                    }
                });
            }
        });
    }

    function saveFilters()
    {
        localStorage.setItem(STORAGE_FILTERS, JSON.stringify(tabFilters));
    }

    function init()
    {
        panel = document.getElementById('chat-panel');
        messagesEl = document.getElementById('chat-messages');
        inputEl = document.getElementById('chat-input');
        formEl = document.getElementById('chat-form');
        tabsEl = document.getElementById('chat-tabs');
        settingsPanel = document.getElementById('chat-settings');
        settingsChecksEl = document.getElementById('chat-settings-checks');
        settingsTitleEl = document.getElementById('chat-settings-title');
        whisperSuggestEl = document.getElementById('chat-whisper-suggest');

        if (!panel)
        {
            return;
        }

        if (window.ANIMASTER_BOOTSTRAP && window.ANIMASTER_BOOTSTRAP.chat_since)
        {
            chatSince = String(window.ANIMASTER_BOOTSTRAP.chat_since);
        }

        loadFilters();

        activeTabId = localStorage.getItem(STORAGE_TAB) || 'main';
        positionSide = localStorage.getItem(STORAGE_POSITION) === 'right' ? 'right' : 'left';
        loadViewMode();

        applyPosition();
        applyViewMode();
        renderTabs();
        renderMessages();
        renderSettingsChecks();

        var posBtn = document.getElementById('chat-position-toggle');
        var collapseBtn = document.getElementById('chat-collapse-toggle');
        var settingsBtn = document.getElementById('chat-settings-open');
        var settingsCloseBtn = document.getElementById('chat-settings-close');

        if (posBtn)
        {
            posBtn.addEventListener('click', togglePosition);
        }

        if (collapseBtn)
        {
            collapseBtn.addEventListener('click', toggleCollapsed);
        }

        var iconMinimizeBtn = document.getElementById('chat-icon-minimize');

        if (iconMinimizeBtn)
        {
            iconMinimizeBtn.addEventListener('click', minimizeToIcon);
        }

        var fabRestoreBtn = document.getElementById('chat-fab-restore');

        if (fabRestoreBtn)
        {
            fabRestoreBtn.addEventListener('click', restoreFromIcon);
        }

        if (settingsBtn)
        {
            settingsBtn.addEventListener('click', openSettings);
        }

        if (settingsCloseBtn)
        {
            settingsCloseBtn.addEventListener('click', closeSettings);
        }

        if (formEl)
        {
            formEl.addEventListener('submit', onSubmit);
        }

        if (inputEl)
        {
            inputEl.addEventListener('keydown', onInputKeydown);
            inputEl.addEventListener('input', onInputChange);
            inputEl.addEventListener('blur', function ()
            {
                window.setTimeout(closeWhisperSuggest, 120);
            });
        }

        pollTimer = setInterval(poll, 3000);
    }

    function myPlayerId()
    {
        return playerRef ? parseInt(playerRef.id_user_ig, 10) || 0 : 0;
    }

    function rememberWhisperPartner(name)
    {
        var trimmed = String(name || '').trim();

        if (!trimmed)
        {
            return;
        }

        whisperPartners = whisperPartners.filter(function (item)
        {
            return item.toLowerCase() !== trimmed.toLowerCase();
        });

        whisperPartners.unshift(trimmed);
    }

    function recordWhisperFromMessage(msg)
    {
        if (!msg || msg.channel !== '@' || msg.system)
        {
            return;
        }

        var myId = myPlayerId();

        if (msg.sender_id && parseInt(msg.sender_id, 10) === myId)
        {
            if (msg.target)
            {
                rememberWhisperPartner(msg.target);
            }

            return;
        }

        if (msg.sender)
        {
            rememberWhisperPartner(msg.sender);
        }
    }

    function parseWhisperTargetFromText(text)
    {
        var match = String(text || '').match(/^@([^\s]+)\s+/);

        return match ? match[1] : null;
    }

    function getWhisperPartialName(value)
    {
        if (!value || value.charAt(0) !== '@')
        {
            return null;
        }

        var after = value.slice(1);
        var spaceIdx = after.indexOf(' ');

        if (spaceIdx >= 0)
        {
            return null;
        }

        return after;
    }

    function filterWhisperPartners(partial)
    {
        var needle = String(partial || '').toLowerCase();

        if (!needle)
        {
            return whisperPartners.slice();
        }

        return whisperPartners.filter(function (name)
        {
            return name.toLowerCase().indexOf(needle) === 0;
        });
    }

    function buildWhisperSuggestMatches(partial)
    {
        var partners = partial ? filterWhisperPartners(partial) : whisperPartners.slice();

        if (partial && !partners.length)
        {
            return [];
        }

        var list = [WHISPER_SUGGEST_NEW];

        partners.forEach(function (name)
        {
            list.push(name);
        });

        return list;
    }

    function isWhisperSuggestNew(entry)
    {
        return entry === WHISPER_SUGGEST_NEW;
    }

    function whisperSuggestLabel(entry)
    {
        if (isWhisperSuggestNew(entry))
        {
            return t('chat.whisper_new_player');
        }

        return '@' + entry;
    }

    function setWhisperInputValue(value, cursorPos)
    {
        if (!inputEl)
        {
            return;
        }

        whisperSuggestProgrammatic = true;
        inputEl.value = value;

        if (cursorPos !== undefined && cursorPos !== null)
        {
            inputEl.setSelectionRange(cursorPos, cursorPos);
        }

        whisperLastInputValue = value;
        whisperSuggestProgrammatic = false;
    }

    function applyWhisperSuggestSelection(entry)
    {
        if (!inputEl)
        {
            return;
        }

        if (isWhisperSuggestNew(entry))
        {
            setWhisperInputValue('@', 1);
            return;
        }

        setWhisperInputValue('@' + entry);
    }

    function renderWhisperSuggest(matches)
    {
        if (!whisperSuggestEl)
        {
            return;
        }

        whisperSuggestEl.innerHTML = '';

        if (!matches.length)
        {
            whisperSuggestEl.hidden = true;
            whisperSuggestOpen = false;
            return;
        }

        matches.forEach(function (name, index)
        {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'chat-whisper-suggest-item' + (index === whisperSuggestIndex ? ' active' : '');

            if (isWhisperSuggestNew(name))
            {
                row.className += ' chat-whisper-suggest-new';
            }

            row.textContent = whisperSuggestLabel(name);
            row.addEventListener('mousedown', function (e)
            {
                e.preventDefault();
                whisperSuggestIndex = index;
                whisperSuggestMatches = matches;

                if (isWhisperSuggestNew(name))
                {
                    applyWhisperSuggestSelection(name);
                    closeWhisperSuggest();
                    inputEl.focus();
                    return;
                }

                completeWhisperSuggest();
            });
            whisperSuggestEl.appendChild(row);
        });

        whisperSuggestEl.hidden = false;
        whisperSuggestOpen = true;
    }

    function closeWhisperSuggest()
    {
        whisperSuggestOpen = false;
        whisperSuggestMatches = [];

        if (whisperSuggestEl)
        {
            whisperSuggestEl.hidden = true;
            whisperSuggestEl.innerHTML = '';
        }
    }

    function openWhisperSuggest(matches)
    {
        if (!matches.length)
        {
            closeWhisperSuggest();
            return;
        }

        if (whisperSuggestIndex >= matches.length)
        {
            whisperSuggestIndex = 0;
        }

        whisperSuggestMatches = matches;
        renderWhisperSuggest(matches);
    }

    function moveWhisperSuggest(delta)
    {
        if (!whisperSuggestOpen || !whisperSuggestMatches.length || !inputEl)
        {
            return;
        }

        var count = whisperSuggestMatches.length;
        whisperSuggestIndex = (whisperSuggestIndex + delta + count) % count;
        renderWhisperSuggest(whisperSuggestMatches);
        applyWhisperSuggestSelection(whisperSuggestMatches[whisperSuggestIndex]);
    }

    function completeWhisperSuggest()
    {
        if (!inputEl || !whisperSuggestMatches.length)
        {
            closeWhisperSuggest();
            return;
        }

        var name = whisperSuggestMatches[whisperSuggestIndex];

        if (isWhisperSuggestNew(name))
        {
            setWhisperInputValue('@', 1);
            closeWhisperSuggest();
            inputEl.focus();
            return;
        }

        if (!name)
        {
            closeWhisperSuggest();
            return;
        }

        setWhisperInputValue('@' + name + ' ');
        closeWhisperSuggest();
        inputEl.focus();
    }

    function updateWhisperSuggest(isDeleting)
    {
        if (!inputEl)
        {
            return;
        }

        var value = inputEl.value;
        var partial = getWhisperPartialName(value);
        var autoFilledRecent = false;

        if (partial === null)
        {
            closeWhisperSuggest();
            return;
        }

        if (value === '@' && whisperPartners.length && !isDeleting)
        {
            setWhisperInputValue('@' + whisperPartners[0]);
            value = inputEl.value;
            partial = whisperPartners[0];
            autoFilledRecent = true;
        }

        var matches = buildWhisperSuggestMatches(partial);

        if (!matches.length)
        {
            closeWhisperSuggest();
            return;
        }

        if (autoFilledRecent && matches.length > 1)
        {
            whisperSuggestIndex = 1;
        }
        else if (!partial)
        {
            whisperSuggestIndex = whisperPartners.length ? 1 : 0;
        }
        else
        {
            whisperSuggestIndex = matches.findIndex(function (name)
            {
                return !isWhisperSuggestNew(name)
                    && String(name).toLowerCase() === String(partial).toLowerCase();
            });

            if (whisperSuggestIndex < 0)
            {
                whisperSuggestIndex = isWhisperSuggestNew(matches[0]) ? 1 : 0;
            }
        }

        openWhisperSuggest(matches);
    }

    function onInputChange(e)
    {
        if (whisperSuggestProgrammatic)
        {
            return;
        }

        var value = inputEl ? inputEl.value : '';
        var isDeleting = value.length < whisperLastInputValue.length;

        if (e && e.inputType)
        {
            if (e.inputType.indexOf('delete') === 0)
            {
                isDeleting = true;
            }
        }

        whisperLastInputValue = value;
        updateWhisperSuggest(isDeleting);
    }

    function onInputKeydown(e)
    {
        e.stopPropagation();

        if (whisperSuggestOpen && whisperSuggestMatches.length)
        {
            if (e.code === 'ArrowDown')
            {
                e.preventDefault();
                moveWhisperSuggest(1);
                return;
            }

            if (e.code === 'ArrowUp')
            {
                e.preventDefault();
                moveWhisperSuggest(-1);
                return;
            }

            if (e.code === 'Space')
            {
                e.preventDefault();
                completeWhisperSuggest();
                return;
            }

            if (e.code === 'Escape')
            {
                e.preventDefault();
                closeWhisperSuggest();
                return;
            }
        }
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function isInputFocused()
    {
        return !!(inputEl && document.activeElement === inputEl);
    }

    function focusInput()
    {
        if (!inputEl)
        {
            return;
        }

        if (viewMode === 'icon')
        {
            restoreFromIcon();
        }

        inputEl.focus();
    }

    function releaseGameFocus()
    {
        closeWhisperSuggest();
        whisperLastInputValue = '';

        if (inputEl)
        {
            inputEl.blur();
        }

        var canvas = document.getElementById('game-canvas');

        if (canvas && typeof canvas.focus === 'function')
        {
            canvas.focus();
        }
    }

    function syncChatLayoutClasses()
    {
        document.body.classList.toggle('animaster-chat-left', positionSide === 'left');
        document.body.classList.toggle('animaster-chat-right', positionSide === 'right');
        document.body.classList.toggle('animaster-chat-icon-only', viewMode === 'icon');
    }

    function applyPosition()
    {
        if (!panel)
        {
            return;
        }

        panel.classList.toggle('chat-pos-left', positionSide === 'left');
        panel.classList.toggle('chat-pos-right', positionSide === 'right');
        syncChatLayoutClasses();
    }

    function loadViewMode()
    {
        var stored = localStorage.getItem(STORAGE_VIEW_MODE);
        var storedBeforeIcon = localStorage.getItem('animaster_chat_mode_before_icon');

        if (storedBeforeIcon === 'collapsed' || storedBeforeIcon === 'expanded')
        {
            modeBeforeIcon = storedBeforeIcon;
        }

        if (stored === 'expanded' || stored === 'collapsed' || stored === 'icon')
        {
            viewMode = stored;
            collapsed = viewMode === 'collapsed';
            return;
        }

        if (localStorage.getItem(STORAGE_COLLAPSED) === '1')
        {
            viewMode = 'collapsed';
            collapsed = true;
            return;
        }

        viewMode = 'expanded';
        collapsed = false;
    }

    function saveViewMode()
    {
        localStorage.setItem(STORAGE_VIEW_MODE, viewMode);
        localStorage.setItem(STORAGE_COLLAPSED, viewMode === 'collapsed' ? '1' : '0');
        localStorage.setItem('animaster_chat_mode_before_icon', modeBeforeIcon);
    }

    function applyViewMode()
    {
        if (!panel)
        {
            return;
        }

        collapsed = viewMode === 'collapsed';

        panel.classList.toggle('chat-collapsed', viewMode === 'collapsed');
        panel.classList.toggle('chat-icon-only', viewMode === 'icon');

        var fabRestoreBtn = document.getElementById('chat-fab-restore');

        if (fabRestoreBtn)
        {
            fabRestoreBtn.hidden = viewMode !== 'icon';
        }

        if (viewMode === 'icon' && settingsPanel)
        {
            settingsPanel.hidden = true;
        }

        var iconMinimizeBtn = document.getElementById('chat-icon-minimize');

        if (iconMinimizeBtn)
        {
            iconMinimizeBtn.hidden = viewMode === 'icon';
        }

        var btn = document.getElementById('chat-collapse-toggle');

        if (btn)
        {
            if (viewMode === 'icon')
            {
                btn.hidden = true;
            }
            else
            {
                btn.hidden = false;
                btn.textContent = collapsed ? '+' : '\u2212';
                btn.title = collapsed ? t('chat.expand') : t('chat.collapse');
                btn.setAttribute('aria-label', collapsed ? t('chat.expand') : t('chat.collapse'));
            }
        }

        syncChatLayoutClasses();
    }

    function toggleCollapsed()
    {
        if (viewMode === 'icon')
        {
            return;
        }

        collapsed = !collapsed;
        viewMode = collapsed ? 'collapsed' : 'expanded';
        saveViewMode();
        applyViewMode();
    }

    function minimizeToIcon()
    {
        if (viewMode === 'icon')
        {
            return;
        }

        modeBeforeIcon = collapsed ? 'collapsed' : 'expanded';
        viewMode = 'icon';
        saveViewMode();
        applyViewMode();
    }

    function restoreFromIcon()
    {
        if (viewMode !== 'icon')
        {
            return;
        }

        viewMode = modeBeforeIcon === 'collapsed' ? 'collapsed' : 'expanded';
        collapsed = viewMode === 'collapsed';
        saveViewMode();
        applyViewMode();
    }

    function togglePosition()
    {
        positionSide = positionSide === 'left' ? 'right' : 'left';
        localStorage.setItem(STORAGE_POSITION, positionSide);
        applyPosition();
    }

    function openSettings()
    {
        if (!settingsPanel)
        {
            return;
        }

        renderSettingsChecks();
        settingsPanel.hidden = false;
    }

    function closeSettings()
    {
        if (settingsPanel)
        {
            settingsPanel.hidden = true;
        }
    }

    function setActiveTab(tabId)
    {
        activeTabId = tabId;
        localStorage.setItem(STORAGE_TAB, tabId);
        renderTabs();
        renderMessages();
        renderSettingsChecks();
    }

    function renderTabs()
    {
        if (!tabsEl)
        {
            return;
        }

        tabsEl.innerHTML = '';

        TABS.forEach(function (tab)
        {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-tab' + (tab.id === activeTabId ? ' active' : '');
            btn.textContent = t(tab.labelKey);
            btn.addEventListener('click', function ()
            {
                setActiveTab(tab.id);
            });
            tabsEl.appendChild(btn);
        });
    }

    function renderSettingsChecks()
    {
        if (!settingsChecksEl)
        {
            return;
        }

        var tab = TABS.find(function (item) { return item.id === activeTabId; });
        var filters = tabFilters[activeTabId] || cloneDefaults(tab ? tab.defaults : TABS[0].defaults);

        if (settingsTitleEl && tab)
        {
            settingsTitleEl.textContent = t('chat.settings_title', { tab: t(tab.labelKey) });
        }

        settingsChecksEl.innerHTML = '';

        CHANNELS.forEach(function (channel)
        {
            var label = document.createElement('label');
            label.className = 'chat-settings-check';

            var input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = !!filters[channel];
            input.addEventListener('change', function ()
            {
                tabFilters[activeTabId][channel] = input.checked;
                saveFilters();
                renderMessages();
            });

            var span = document.createElement('span');
            span.className = cssChannelClass(channel);
            span.textContent = channelLabel(channel);

            label.appendChild(input);
            label.appendChild(span);
            settingsChecksEl.appendChild(label);
        });
    }

    function messagePassesTabFilter(msg, tabId)
    {
        var filters = tabFilters[tabId];

        if (!filters)
        {
            return false;
        }

        return !!filters[msg.channel];
    }

    function formatLine(msg)
    {
        var prefix = '';

        if (msg.channel === '@' && msg.target)
        {
            prefix = '[' + channelLabel(msg.channel) + ' → ' + msg.target + '] ';
        }
        else if (msg.channel !== 'L')
        {
            prefix = '[' + channelLabel(msg.channel) + '] ';
        }

        return prefix + (msg.sender || '?') + ': ' + msg.text;
    }

    function renderMessages()
    {
        if (!messagesEl)
        {
            return;
        }

        messagesEl.innerHTML = '';

        var visible = allMessages.filter(function (msg)
        {
            return messagePassesTabFilter(msg, activeTabId);
        });

        if (!visible.length)
        {
            var empty = document.createElement('p');
            empty.className = 'chat-line chat-line-empty';
            empty.textContent = t('chat.empty');
            messagesEl.appendChild(empty);
            return;
        }

        var start = Math.max(0, visible.length - 80);

        visible.slice(start).forEach(function (msg)
        {
            appendMessageLine(msg, false);
        });

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessageLine(msg, scroll)
    {
        if (!messagesEl)
        {
            return;
        }

        var empty = messagesEl.querySelector('.chat-line-empty');

        if (empty)
        {
            empty.remove();
        }

        var line = document.createElement('p');
        line.className = 'chat-line ' + cssChannelClass(msg.channel);

        if (msg.system)
        {
            line.className += ' chat-line-system';
        }

        line.textContent = msg.system ? msg.text : formatLine(msg);
        messagesEl.appendChild(line);

        if (scroll !== false)
        {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function addSystemMessage(text)
    {
        var msg = {
            id: 0,
            channel: 'L',
            sender: '',
            text: text,
            system: true
        };

        appendMessageLine(msg, true);
    }

    function showBubbleForMessage(row)
    {
        if (!row || !row.sender_id || !row.text)
        {
            return;
        }

        if (typeof AnimasterPlayerChatBubbles !== 'undefined')
        {
            AnimasterPlayerChatBubbles.push(row.sender_id, row.text, row.channel);
        }
    }

    function ingestMessages(rows)
    {
        if (!rows || !rows.length)
        {
            return;
        }

        rows.forEach(function (row)
        {
            if (!row || !row.id)
            {
                return;
            }

            if (row.id <= lastMessageId)
            {
                return;
            }

            allMessages.push({
                id: row.id,
                channel: row.channel,
                sender_id: row.sender_id,
                sender: row.sender,
                text: row.text,
                target: row.target,
                dt: row.dt
            });

            showBubbleForMessage(row);
            recordWhisperFromMessage(row);

            if (row.id > lastMessageId)
            {
                lastMessageId = row.id;
            }
        });

        if (allMessages.length > 500)
        {
            allMessages = allMessages.slice(-500);
        }

        renderMessages();
    }

    function poll()
    {
        if (!playerRef || busy)
        {
            return;
        }

        AnimasterApi.pollChat(playerRef, lastMessageId, chatSince).then(function (rows)
        {
            ingestMessages(rows);
        }).catch(function (err)
        {
            console.warn('[AnimasterChat] poll failed:', err && err.message ? err.message : err);
        });
    }

    function errorText(code)
    {
        var normalized = String(code || 'generic').toLowerCase().replace(/[^a-z0-9_]/g, '_');

        if (normalized.indexOf('invalid') !== -1 && normalized.indexOf('json') !== -1)
        {
            normalized = 'server_error';
        }

        if (normalized === 'request_failed')
        {
            normalized = 'generic';
        }

        var key = 'chat.error_' + normalized;
        var str = t(key);

        if (str === key)
        {
            return t('chat.error_generic');
        }

        return str;
    }

    function onSubmit(event)
    {
        event.preventDefault();

        if (!playerRef || !inputEl || busy)
        {
            return;
        }

        var text = inputEl.value.trim();

        if (!text)
        {
            releaseGameFocus();
            return;
        }

        closeWhisperSuggest();

        var whisperTarget = parseWhisperTargetFromText(text);

        busy = true;
        inputEl.disabled = true;

        AnimasterApi.sendChat(playerRef, text).then(function (message)
        {
            setWhisperInputValue('');

            if (whisperTarget)
            {
                rememberWhisperPartner(whisperTarget);
            }

            ingestMessages([message]);
        }).catch(function (err)
        {
            var code = err && err.message ? err.message : '';
            addSystemMessage(errorText(code));
        }).finally(function ()
        {
            busy = false;
            inputEl.disabled = false;
            releaseGameFocus();
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        isInputFocused: isInputFocused,
        focusInput: focusInput,
        releaseGameFocus: releaseGameFocus,
        poll: poll
    };
})();
