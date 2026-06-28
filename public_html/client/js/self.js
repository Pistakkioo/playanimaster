/**
 * Self / character panel — profile, EXP, class abilities.
 */
var AnimasterSelf = (function ()
{
    var panel = null;
    var toggleBtn = null;
    var closeBtn = null;
    var thumbEl = null;
    var thumbInitialEl = null;
    var nameEl = null;
    var classEl = null;
    var levelEl = null;
    var goldEl = null;
    var expFillEl = null;
    var expTextEl = null;
    var tabsEl = null;
    var tabPanels = {};
    var messageEl = null;

    var playerRef = null;
    var selfData = null;
    var open = false;
    var busy = false;
    var activeTab = 'overview';
    var lvlUpConstantPlayer = 80;

    var TABS = [
        { id: 'overview', labelKey: 'self.tab_overview' },
        { id: 'abilities', labelKey: 'self.tab_abilities' }
    ];

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
        panel = document.getElementById('self-panel');
        toggleBtn = document.getElementById('self-toggle');
        closeBtn = document.getElementById('self-close');
        thumbEl = document.getElementById('self-thumb');
        thumbInitialEl = document.getElementById('self-thumb-initial');
        nameEl = document.getElementById('self-name');
        classEl = document.getElementById('self-class');
        levelEl = document.getElementById('self-level');
        goldEl = document.getElementById('self-gold');
        expFillEl = document.getElementById('self-exp-fill');
        expTextEl = document.getElementById('self-exp-text');
        tabsEl = document.getElementById('self-tabs');
        tabPanels = {
            overview: document.getElementById('self-tab-overview'),
            abilities: document.getElementById('self-tab-abilities')
        };
        messageEl = document.getElementById('self-message');

        if (options && options.lvlUpConstantPlayer)
        {
            lvlUpConstantPlayer = parseInt(options.lvlUpConstantPlayer, 10) || 80;
        }

        initTabs();
        setActiveTab('overview');

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

        if (panel && typeof AnimasterPanelDrag !== 'undefined')
        {
            var dragBounds = document.querySelector('.canvas-wrap');
            var dragHandle = panel.querySelector('.self-header');

            if (dragBounds && dragHandle)
            {
                AnimasterPanelDrag.attach(panel, dragHandle, dragBounds);
            }
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'KeyP' && !e.ctrlKey && !e.metaKey && !e.altKey)
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

    function initTabs()
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
            btn.className = 'self-tab-btn' + (tab.id === activeTab ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', tab.id === activeTab ? 'true' : 'false');
            btn.dataset.tabId = tab.id;
            btn.textContent = t(tab.labelKey);
            btn.addEventListener('click', function ()
            {
                setActiveTab(tab.id);
            });
            tabsEl.appendChild(btn);
        });
    }

    function setActiveTab(tabId)
    {
        activeTab = tabId;

        if (tabsEl)
        {
            tabsEl.querySelectorAll('.self-tab-btn').forEach(function (btn)
            {
                var isActive = btn.dataset.tabId === tabId;
                btn.classList.toggle('is-active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        Object.keys(tabPanels).forEach(function (key)
        {
            var panelEl = tabPanels[key];

            if (!panelEl)
            {
                return;
            }

            var show = key === tabId;
            panelEl.hidden = !show;
            panelEl.classList.toggle('is-active', show);
        });

        renderActiveTab();
    }

    function setPlayer(player)
    {
        playerRef = player;
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

    function closeOthers()
    {
        if (typeof AnimasterInventory !== 'undefined' && AnimasterInventory.isOpen())
        {
            AnimasterInventory.close();
        }

        if (typeof AnimasterTeam !== 'undefined' && AnimasterTeam.isOpen())
        {
            AnimasterTeam.close();
        }
    }

    function openPanel()
    {
        if (!panel || open || busy)
        {
            return;
        }

        closeOthers();
        open = true;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');

        if (toggleBtn)
        {
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        loadSelf(true);
    }

    function closePanel()
    {
        if (!panel || !open)
        {
            return;
        }

        open = false;
        panel.hidden = true;
        panel.setAttribute('aria-hidden', 'true');

        if (toggleBtn)
        {
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        if (messageEl)
        {
            messageEl.textContent = '';
        }
    }

    function refresh()
    {
        if (open)
        {
            loadSelf(true);
        }
    }

    function loadSelf(force)
    {
        if (!playerRef || (busy && !force))
        {
            return Promise.resolve();
        }

        busy = true;

        if (messageEl)
        {
            messageEl.textContent = t('self.loading');
        }

        return AnimasterApi.getSelfInfo(playerRef).then(function (data)
        {
            selfData = data;
            renderHero();
            renderActiveTab();

            if (messageEl)
            {
                messageEl.textContent = '';
            }
        }).catch(function ()
        {
            if (messageEl)
            {
                messageEl.textContent = t('self.load_failed');
            }
        }).finally(function ()
        {
            busy = false;
        });
    }

    function renderHero()
    {
        if (!selfData)
        {
            return;
        }

        var name = selfData.display_name || t('hud.default_player');
        var initial = name.charAt(0).toUpperCase() || '?';

        if (thumbInitialEl)
        {
            thumbInitialEl.textContent = initial;
        }

        if (thumbEl)
        {
            thumbEl.classList.remove('self-thumb-nerd', 'self-thumb-stud');

            if (selfData.player_class_code === 'nerd')
            {
                thumbEl.classList.add('self-thumb-nerd');
            }
            else if (selfData.player_class_code === 'stud')
            {
                thumbEl.classList.add('self-thumb-stud');
            }
        }

        if (nameEl)
        {
            nameEl.textContent = name;
        }

        if (classEl)
        {
            classEl.textContent = selfData.player_class_name || '—';
        }

        if (levelEl)
        {
            levelEl.textContent = t('self.level_label', { level: selfData.level || 1 });
        }

        if (goldEl)
        {
            goldEl.textContent = t('self.gold_label', { gold: selfData.gold || 0 });
        }

        renderExpBar();
    }

    function renderExpBar()
    {
        if (!selfData || !selfData.exp)
        {
            return;
        }

        var exp = selfData.exp;
        var pct = Math.round((exp.progress || 0) * 100);

        if (expFillEl)
        {
            expFillEl.style.width = pct + '%';
        }

        if (expTextEl)
        {
            expTextEl.textContent = t('self.exp_progress', {
                current: exp.exp_in_level || 0,
                needed: Math.max(0, (exp.exp_max || 0) - (exp.exp_min || 0)),
                level: selfData.level || 1,
                next: (selfData.level || 1) + 1
            });
        }
    }

    function renderActiveTab()
    {
        if (activeTab === 'overview')
        {
            renderOverview();
        }
        else if (activeTab === 'abilities')
        {
            renderAbilities();
        }
    }

    function renderOverview()
    {
        var panelEl = tabPanels.overview;

        if (!panelEl || !selfData)
        {
            return;
        }

        panelEl.innerHTML = '';

        var rows = [
            { label: t('self.field_avatar'), value: selfData.character_type || '—' },
            { label: t('self.field_gender'), value: formatGender(selfData.gender) },
            { label: t('self.field_zone'), value: formatZone(selfData) },
            { label: t('self.field_class_tier'), value: formatClassTier(selfData) }
        ];

        rows.forEach(function (row)
        {
            var item = document.createElement('div');
            item.className = 'self-overview-row';

            var label = document.createElement('span');
            label.className = 'self-overview-label';
            label.textContent = row.label;

            var value = document.createElement('span');
            value.className = 'self-overview-value';
            value.textContent = row.value;

            item.appendChild(label);
            item.appendChild(value);
            panelEl.appendChild(item);
        });

        if (selfData.starter_branch && (selfData.level || 0) < 25)
        {
            var hint = document.createElement('p');
            hint.className = 'self-overview-hint';
            hint.textContent = t('self.specialize_hint', { level: 25 });
            panelEl.appendChild(hint);
        }
    }

    function formatGender(gender)
    {
        if (gender === 'M')
        {
            return t('self.gender_male');
        }

        if (gender === 'F')
        {
            return t('self.gender_female');
        }

        return gender || '—';
    }

    function formatZone(data)
    {
        if (data.scene_name)
        {
            return data.scene_name + ' (' + data.id_zone + ')';
        }

        return String(data.id_zone || '—');
    }

    function formatClassTier(data)
    {
        var tier = data.class_unlock_level || 1;

        return t('self.class_tier', { tier: tier });
    }

    function renderAbilities()
    {
        var panelEl = tabPanels.abilities;

        if (!panelEl)
        {
            return;
        }

        panelEl.innerHTML = '';

        var abilities = selfData && selfData.abilities ? selfData.abilities : [];

        if (!abilities.length)
        {
            var empty = document.createElement('p');
            empty.className = 'self-abilities-empty';
            empty.textContent = t('self.abilities_empty');
            panelEl.appendChild(empty);
            return;
        }

        var list = document.createElement('ul');
        list.className = 'self-abilities-list';

        abilities.forEach(function (ability)
        {
            var item = document.createElement('li');
            item.className = 'self-ability-item';

            if (ability.flg_unlocked !== 'S')
            {
                item.classList.add('is-locked');
            }

            var head = document.createElement('div');
            head.className = 'self-ability-head';

            var title = document.createElement('span');
            title.className = 'self-ability-name';
            title.textContent = ability.name || ability.code;

            var context = document.createElement('span');
            context.className = 'self-ability-context';
            context.textContent = formatAbilityContext(ability.use_context);

            head.appendChild(title);
            head.appendChild(context);
            item.appendChild(head);

            if (ability.description)
            {
                var desc = document.createElement('div');
                desc.className = 'self-ability-desc';
                desc.textContent = ability.description;
                item.appendChild(desc);
            }

            var meta = document.createElement('div');
            meta.className = 'self-ability-meta';

            if (ability.flg_unlocked !== 'S')
            {
                meta.textContent = t('self.ability_locked');
            }
            else if (ability.use_context === 'battle' && ability.cooldown_turns > 0)
            {
                meta.textContent = t('self.ability_cd_turns', { turns: ability.cooldown_turns });
            }
            else if (ability.use_context === 'field' && ability.cooldown_seconds > 0)
            {
                meta.textContent = t('self.ability_cd_seconds', { seconds: ability.cooldown_seconds });
            }
            else
            {
                meta.textContent = t('self.ability_passive');
            }

            item.appendChild(meta);
            list.appendChild(item);
        });

        panelEl.appendChild(list);
    }

    function formatAbilityContext(useContext)
    {
        if (useContext === 'field')
        {
            return t('self.context_field');
        }

        if (useContext === 'both')
        {
            return t('self.context_both');
        }

        return t('self.context_battle');
    }

    return {
        init: init,
        setPlayer: setPlayer,
        setToggleEnabled: setToggleEnabled,
        isOpen: isOpen,
        open: openPanel,
        close: closePanel,
        refresh: refresh
    };
})();
