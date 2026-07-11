/**
 * Quest log panel + persistent tracker widget.
 */
var AnimasterQuests = (function ()
{
    var TRACK_KEY = 'animaster_quest_tracked_id';
    var POLL_MS = 10000;

    var panel = null;
    var toggleBtn = null;
    var closeBtn = null;
    var listEl = null;
    var emptyEl = null;

    var trackerEl = null;
    var trackerNameEl = null;
    var trackerObjectivesEl = null;
    var trackerClearBtn = null;

    var playerRef = null;
    var open = false;
    var busy = false;
    var quests = [];
    var trackedId = 0;
    var pollTimer = null;

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
        panel = document.getElementById('quest-panel');
        toggleBtn = document.getElementById('quest-toggle');
        closeBtn = document.getElementById('quest-close');
        listEl = document.getElementById('quest-list');
        emptyEl = document.getElementById('quest-empty');

        trackerEl = document.getElementById('quest-tracker');
        trackerNameEl = document.getElementById('quest-tracker-name');
        trackerObjectivesEl = document.getElementById('quest-tracker-objectives');
        trackerClearBtn = document.getElementById('quest-tracker-clear');

        trackedId = readTrackedId();

        if (typeof AnimasterPanelDrag !== 'undefined')
        {
            var dragBounds = document.querySelector('.canvas-wrap');

            if (dragBounds)
            {
                if (panel)
                {
                    var dragHandle = panel.querySelector('.quest-header');

                    if (dragHandle)
                    {
                        AnimasterPanelDrag.attach(panel, dragHandle, dragBounds);
                    }
                }

                if (trackerEl)
                {
                    var trackerHandle = document.getElementById('quest-tracker-header');

                    if (trackerHandle)
                    {
                        AnimasterPanelDrag.attach(trackerEl, trackerHandle, dragBounds);
                    }
                }
            }
        }

        if (toggleBtn)
        {
            toggleBtn.addEventListener('click', function ()
            {
                if (isOpen())
                {
                    closePanel();
                }
                else
                {
                    openPanel();
                }
            });
        }

        if (closeBtn)
        {
            closeBtn.addEventListener('click', closePanel);
        }

        if (trackerClearBtn)
        {
            trackerClearBtn.addEventListener('click', function ()
            {
                setTrackedId(0);
                renderTracker();
            });
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'KeyQ' && !e.ctrlKey && !e.metaKey && !e.altKey)
            {
                var tag = document.activeElement && document.activeElement.tagName;

                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT')
                {
                    return;
                }

                if (toggleBtn && !toggleBtn.disabled)
                {
                    e.preventDefault();

                    if (isOpen())
                    {
                        closePanel();
                    }
                    else
                    {
                        openPanel();
                    }
                }
            }

            if (e.code === 'Escape' && isOpen())
            {
                closePanel();
            }
        });
    }

    function setPlayer(player)
    {
        playerRef = player;
        refreshTracker();
        startPoll();
    }

    function setToggleEnabled(enabled)
    {
        if (toggleBtn)
        {
            toggleBtn.disabled = !enabled;
        }
    }

    function isOpen()
    {
        return open;
    }

    function openPanel()
    {
        if (!panel || !playerRef)
        {
            return;
        }

        open = true;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');

        if (toggleBtn)
        {
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        refresh();
    }

    function closePanel()
    {
        open = false;

        if (panel)
        {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
        }

        if (toggleBtn)
        {
            toggleBtn.setAttribute('aria-expanded', 'false');
        }
    }

    function startPoll()
    {
        stopPoll();
        pollTimer = setInterval(refreshTracker, POLL_MS);
    }

    function stopPoll()
    {
        if (pollTimer)
        {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function readTrackedId()
    {
        try
        {
            var raw = window.localStorage ? window.localStorage.getItem(TRACK_KEY) : null;

            return raw !== null ? parseInt(raw, 10) || 0 : -1; // -1 = never set, allow auto-pick
        }
        catch (e)
        {
            return -1;
        }
    }

    function setTrackedId(id)
    {
        trackedId = id;

        try
        {
            if (window.localStorage)
            {
                window.localStorage.setItem(TRACK_KEY, String(id));
            }
        }
        catch (e)
        {
            // ignore storage errors (private browsing etc.)
        }
    }

    /**
     * Fetches the quest log; updates the panel (if open) and the tracker
     * widget. Safe to call after any dialog/battle event.
     */
    function load()
    {
        if (!playerRef || busy)
        {
            return Promise.resolve();
        }

        busy = true;

        return AnimasterApi.getQuests(playerRef).then(function (result)
        {
            quests = Array.isArray(result) ? result : [];
            busy = false;

            if (isOpen())
            {
                renderList();
            }

            renderTracker();
        }).catch(function (err)
        {
            busy = false;
            console.warn('[Animaster] quest log fetch failed:', err && err.message ? err.message : err);
        });
    }

    function refresh()
    {
        return load();
    }

    function refreshTracker()
    {
        return load();
    }

    function activeQuests()
    {
        return quests.filter(function (q) { return !q.flg_completed; });
    }

    function findQuest(idQuest)
    {
        for (var i = 0; i < quests.length; i++)
        {
            if (quests[i].id_quest === idQuest)
            {
                return quests[i];
            }
        }

        return null;
    }

    function progressText(objective)
    {
        return objective.count + '/' + objective.target;
    }

    function renderObjectiveRow(objective)
    {
        var row = document.createElement('div');
        row.className = 'quest-objective' + (objective.complete ? ' is-complete' : '');

        var label = document.createElement('span');
        label.className = 'quest-objective-label';
        label.textContent = objective.description || objective.objective_type;
        row.appendChild(label);

        var progress = document.createElement('span');
        progress.className = 'quest-objective-progress';
        progress.textContent = progressText(objective);
        row.appendChild(progress);

        return row;
    }

    function renderList()
    {
        if (!listEl || !emptyEl)
        {
            return;
        }

        listEl.innerHTML = '';

        if (!quests.length)
        {
            emptyEl.hidden = false;
            return;
        }

        emptyEl.hidden = true;

        quests.forEach(function (quest)
        {
            var card = document.createElement('div');
            card.className = 'quest-card' + (quest.flg_completed ? ' is-completed' : '')
                + (quest.ready_to_turn_in ? ' is-ready' : '');

            var head = document.createElement('div');
            head.className = 'quest-card-head';

            var name = document.createElement('span');
            name.className = 'quest-card-name';
            name.textContent = quest.name;
            head.appendChild(name);

            if (!quest.flg_completed)
            {
                var trackBtn = document.createElement('button');
                trackBtn.type = 'button';
                trackBtn.className = 'quest-track-btn';

                var isTracked = trackedId === quest.id_quest;
                trackBtn.textContent = isTracked ? t('quest.tracking_button') : t('quest.track_button');
                trackBtn.classList.toggle('is-active', isTracked);
                trackBtn.addEventListener('click', function ()
                {
                    setTrackedId(isTracked ? 0 : quest.id_quest);
                    renderList();
                    renderTracker();
                });

                head.appendChild(trackBtn);
            }

            card.appendChild(head);

            if (quest.description)
            {
                var desc = document.createElement('div');
                desc.className = 'quest-card-desc';
                desc.textContent = quest.description;
                card.appendChild(desc);
            }

            if (quest.flg_completed)
            {
                var completedLabel = document.createElement('div');
                completedLabel.className = 'quest-card-status';
                completedLabel.textContent = t('quest.completed');
                card.appendChild(completedLabel);
            }
            else if (quest.ready_to_turn_in)
            {
                var readyLabel = document.createElement('div');
                readyLabel.className = 'quest-card-status is-ready';
                readyLabel.textContent = t('quest.ready_to_turn_in');
                card.appendChild(readyLabel);
            }
            else
            {
                var phaseLabel = document.createElement('div');
                phaseLabel.className = 'quest-card-phase';
                phaseLabel.textContent = t('quest.phase_label', { phase: quest.phase, max_phase: quest.max_phase });
                card.appendChild(phaseLabel);

                var objectivesEl = document.createElement('div');
                objectivesEl.className = 'quest-card-objectives';
                (quest.objectives || []).forEach(function (objective)
                {
                    objectivesEl.appendChild(renderObjectiveRow(objective));
                });
                card.appendChild(objectivesEl);
            }

            listEl.appendChild(card);
        });
    }

    function renderTracker()
    {
        if (!trackerEl || !trackerNameEl || !trackerObjectivesEl)
        {
            return;
        }

        var resolvedTrackedId = trackedId;

        if (resolvedTrackedId === -1)
        {
            var firstActive = activeQuests()[0];
            resolvedTrackedId = firstActive ? firstActive.id_quest : 0;
        }

        var quest = resolvedTrackedId ? findQuest(resolvedTrackedId) : null;

        if (!quest || quest.flg_completed)
        {
            trackerEl.hidden = true;
            trackerEl.setAttribute('aria-hidden', 'true');
            return;
        }

        trackerEl.hidden = false;
        trackerEl.setAttribute('aria-hidden', 'false');
        trackerNameEl.textContent = quest.name;
        trackerObjectivesEl.innerHTML = '';

        if (quest.ready_to_turn_in)
        {
            var readyRow = document.createElement('div');
            readyRow.className = 'quest-tracker-ready';
            readyRow.textContent = t('quest.ready_to_turn_in');
            trackerObjectivesEl.appendChild(readyRow);
            return;
        }

        (quest.objectives || []).forEach(function (objective)
        {
            trackerObjectivesEl.appendChild(renderObjectiveRow(objective));
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        setToggleEnabled: setToggleEnabled,
        isOpen: isOpen,
        open: openPanel,
        close: closePanel,
        refresh: refresh,
        refreshTracker: refreshTracker
    };
})();
