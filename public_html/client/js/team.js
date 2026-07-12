/**
 * Team panel (web port of TeamManager / MenuAnimal).
 */
var AnimasterTeam = (function ()
{
    var panel = null;
    var toggleBtn = null;
    var closeBtn = null;
    var reorderToggleBtn = null;
    var reorderBarEl = null;
    var reorderSaveBtn = null;
    var reorderCancelBtn = null;
    var listEl = null;
    var detailSpeciesEl = null;
    var detailNicknameDisplayEl = null;
    var nicknameEditEl = null;
    var detailNicknameInput = null;
    var detailNicknameSaveBtn = null;
    var detailLevelEl = null;
    var detailElementEl = null;
    var detailHpFillEl = null;
    var detailHpTextEl = null;
    var detailXpFillEl = null;
    var detailXpTextEl = null;
    var detailBuffsEl = null;
    var detailBuffsListEl = null;
    var detailTabsEl = null;
    var detailTabPanels = {};
    var messageEl = null;

    var playerRef = null;
    var animals = [];
    var selectedAnimal = null;
    var open = false;
    var busy = false;
    var editingNickname = false;
    var lvlUpConstant = 40;
    var buffTimerId = null;
    var expandedBuffStacks = {};
    var buffRenderAnimalId = null;
    var activeDetailTab = 'overview';
    var abilitiesCache = {};
    var abilitiesLoading = false;
    var detailTabsReady = false;
    var reorderMode = false;
    var reorderAnimals = [];
    var dragSrcIndex = null;
    var dropTargetIndex = null;

    var STAT_FIELDS = [
        { key: 'hp', label: 'HP' },
        { key: 'atk', label: 'ATK' },
        { key: 'def', label: 'DEF' },
        { key: 'matk', label: 'MATK' },
        { key: 'mdef', label: 'MDEF' },
        { key: 'spd', label: 'SPD' },
        { key: 'acc', label: 'ACC' },
        { key: 'eva', label: 'EVA' },
        { key: 'cr', label: 'CR' }
    ];

    var DETAIL_TABS = [
        { id: 'overview', labelKey: 'team.tab_overview' },
        { id: 'current', labelKey: 'team.tab_current' },
        { id: 'base', labelKey: 'team.tab_base', prefix: 'base_' },
        { id: 'dna', labelKey: 'team.tab_dna', prefix: 'dna_' },
        { id: 'exp', labelKey: 'team.tab_exp', prefix: 'xp_' },
        { id: 'points', labelKey: 'team.tab_points', prefix: 'pt_' },
        { id: 'abilities', labelKey: 'team.tab_abilities' }
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
        panel = document.getElementById('team-panel');
        toggleBtn = document.getElementById('team-toggle');
        closeBtn = document.getElementById('team-close');
        reorderToggleBtn = document.getElementById('team-reorder-toggle');
        reorderBarEl = document.getElementById('team-reorder-bar');
        reorderSaveBtn = document.getElementById('team-reorder-save');
        reorderCancelBtn = document.getElementById('team-reorder-cancel');
        listEl = document.getElementById('team-list');
        detailSpeciesEl = document.getElementById('team-detail-species');
        detailNicknameDisplayEl = document.getElementById('team-detail-nickname-display');
        nicknameEditEl = document.getElementById('team-nickname-edit');
        detailNicknameInput = document.getElementById('team-detail-nickname');
        detailNicknameSaveBtn = document.getElementById('team-nickname-save');
        detailLevelEl = document.getElementById('team-detail-level');
        detailElementEl = document.getElementById('team-detail-element');
        detailHpFillEl = document.getElementById('team-detail-hp-fill');
        detailHpTextEl = document.getElementById('team-detail-hp-text');
        detailXpFillEl = document.getElementById('team-detail-xp-fill');
        detailXpTextEl = document.getElementById('team-detail-xp-text');
        detailBuffsEl = document.getElementById('team-detail-buffs');
        detailBuffsListEl = document.getElementById('team-detail-buffs-list');
        detailTabsEl = document.getElementById('team-detail-tabs');
        detailTabPanels = {
            overview: document.getElementById('team-tab-overview'),
            current: document.getElementById('team-tab-current'),
            base: document.getElementById('team-tab-base'),
            dna: document.getElementById('team-tab-dna'),
            exp: document.getElementById('team-tab-exp'),
            points: document.getElementById('team-tab-points'),
            abilities: document.getElementById('team-tab-abilities')
        };
        messageEl = document.getElementById('team-message');

        initDetailTabs();

        if (panel && typeof AnimasterPanelDrag !== 'undefined')
        {
            var dragBounds = document.querySelector('.canvas-wrap');
            var dragHandle = panel.querySelector('.team-header');

            if (dragBounds && dragHandle)
            {
                AnimasterPanelDrag.attach(panel, dragHandle, dragBounds);
            }
        }

        if (options && options.lvlUpConstantAnimal)
        {
            lvlUpConstant = parseInt(options.lvlUpConstantAnimal, 10) || 40;
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

        if (reorderToggleBtn)
        {
            reorderToggleBtn.addEventListener('click', toggleReorderMode);
        }

        if (reorderSaveBtn)
        {
            reorderSaveBtn.addEventListener('click', saveTeamOrder);
        }

        if (reorderCancelBtn)
        {
            reorderCancelBtn.addEventListener('click', function ()
            {
                exitReorderMode(true);
            });
        }

        if (detailNicknameSaveBtn)
        {
            detailNicknameSaveBtn.addEventListener('click', saveNickname);
        }

        if (detailNicknameDisplayEl)
        {
            detailNicknameDisplayEl.addEventListener('click', showNicknameEdit);
        }

        if (detailNicknameInput)
        {
            detailNicknameInput.addEventListener('keydown', function (e)
            {
                if (e.code === 'Enter')
                {
                    e.preventDefault();
                    saveNickname();
                }
            });
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'KeyT' && !e.ctrlKey && !e.metaKey && !e.altKey)
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
                if (reorderMode)
                {
                    exitReorderMode(true);
                    return;
                }

                if (nicknameEditEl && !nicknameEditEl.hidden)
                {
                    hideNicknameEdit();
                    return;
                }

                closePanel();
            }
        });
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function setXpConstant(value)
    {
        lvlUpConstant = parseInt(value, 10) || 40;
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

        if (typeof AnimasterInventory !== 'undefined' && AnimasterInventory.isOpen())
        {
            AnimasterInventory.close();
        }

        open = true;
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');

        if (toggleBtn)
        {
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        setMessage('');
        loadTeam();
        startBuffTimer();
    }

    function closePanel()
    {
        open = false;
        editingNickname = false;
        exitReorderMode(false);
        stopBuffTimer();

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

    function setMessage(text, isError)
    {
        if (!messageEl)
        {
            return;
        }

        messageEl.textContent = text || '';
        messageEl.classList.toggle('error', !!isError);
    }

    function quotedNickname(animal)
    {
        if (!animal)
        {
            return '""';
        }

        return '"' + (animal.nickname || '') + '"';
    }

    function hideNicknameEdit()
    {
        editingNickname = false;

        if (nicknameEditEl)
        {
            nicknameEditEl.hidden = true;
        }

        if (detailNicknameDisplayEl && selectedAnimal)
        {
            detailNicknameDisplayEl.hidden = false;
        }
    }

    function showNicknameEdit()
    {
        if (!selectedAnimal || busy)
        {
            return;
        }

        editingNickname = true;

        if (detailNicknameDisplayEl)
        {
            detailNicknameDisplayEl.hidden = true;
        }

        if (nicknameEditEl)
        {
            nicknameEditEl.hidden = false;
        }

        if (detailNicknameInput)
        {
            detailNicknameInput.value = selectedAnimal.nickname || '';
            detailNicknameInput.disabled = false;
            detailNicknameInput.focus();
            detailNicknameInput.select();
        }

        if (detailNicknameSaveBtn)
        {
            detailNicknameSaveBtn.disabled = false;
        }
    }

    function displayName(animal)
    {
        if (!animal)
        {
            return '—';
        }

        return animal.nickname || animal.species || t('team.animal_fallback', { id: animal.id_animal });
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

    function renderElementMeta(parent, animal)
    {
        if (!parent)
        {
            return;
        }

        parent.textContent = '';

        if (!animal || !animal.element)
        {
            parent.textContent = '—';
            return;
        }

        if (typeof AnimasterElements !== 'undefined')
        {
            AnimasterElements.appendLabel(parent, elementDataFromAnimal(animal), {
                className: 'team-row-meta-label',
                sizeClass: 'element-icon--md'
            });
            return;
        }

        parent.textContent = animal.element;
    }

    function spriteDataFromAnimal(animal)
    {
        if (!animal)
        {
            return {};
        }

        return {
            id_species: animal.id_species,
            species: animal.species,
            species_key: animal.species_key,
            id_wild_animal: parseInt(animal.id_animal, 10) || 0
        };
    }

    function teamThumbPixelSize()
    {
        if (typeof AnimasterWildSprites === 'undefined')
        {
            return 2;
        }

        var grid = AnimasterWildSprites.gridSize || 8;

        return Math.max(1, Math.floor(32 / grid));
    }

    function renderTeamThumb(thumbEl, animal, slotLabel)
    {
        if (!thumbEl)
        {
            return;
        }

        thumbEl.innerHTML = '';
        thumbEl.className = 'team-row-thumb';

        var canvas = document.createElement('canvas');
        canvas.className = 'team-row-thumb-canvas';
        canvas.width = 36;
        canvas.height = 36;
        canvas.setAttribute('aria-hidden', 'true');
        thumbEl.appendChild(canvas);

        if (typeof AnimasterWildSprites !== 'undefined')
        {
            var ctx = canvas.getContext('2d');

            if (ctx)
            {
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                var elementColor = typeof AnimasterElements !== 'undefined'
                    ? AnimasterElements.resolveColor(elementDataFromAnimal(animal))
                    : '#888888';

                AnimasterWildSprites.draw(ctx, canvas.width / 2, canvas.height / 2, spriteDataFromAnimal(animal), {
                    elementColor: elementColor,
                    pixelSize: teamThumbPixelSize()
                });
            }
        }

        var badge = document.createElement('span');
        badge.className = 'team-row-thumb-slot';
        badge.textContent = String(slotLabel);
        thumbEl.appendChild(badge);
    }

    function xpRange(animal)
    {
        var lvl = parseInt(animal.lvl, 10) || 1;
        var exp = parseInt(animal.experience, 10) || 0;
        var min = lvlUpConstant * lvl * lvl * lvl;
        var max = lvlUpConstant * (lvl + 1) * (lvl + 1) * (lvl + 1);
        var span = max - min;
        var pct = span > 0 ? (exp - min) / span : 0;

        if (pct < 0)
        {
            pct = 0;
        }

        if (pct > 1)
        {
            pct = 1;
        }

        return {
            min: min,
            max: max,
            exp: exp,
            pct: pct
        };
    }

    function displayMaxHp(animal)
    {
        var effective = parseInt(animal && animal.effective_max_hp, 10) || 0;

        if (effective > 0)
        {
            return effective;
        }

        return parseInt(animal && animal.max_hp, 10) || 1;
    }

    function hpRatio(animal)
    {
        var hp = parseInt(animal.current_hp, 10) || 0;
        var maxHp = displayMaxHp(animal);
        var ratio = hp / maxHp;

        if (ratio < 0)
        {
            ratio = 0;
        }

        if (ratio > 1)
        {
            ratio = 1;
        }

        return ratio;
    }

    function hpBarClass(ratio)
    {
        if (ratio >= 0.5)
        {
            return 'hp-high';
        }

        if (ratio >= 0.2)
        {
            return 'hp-mid';
        }

        return 'hp-low';
    }

    function loadTeam(force)
    {
        if (!playerRef || (busy && !force))
        {
            return Promise.resolve();
        }

        if (reorderMode)
        {
            exitReorderMode(false);
        }

        busy = true;
        setMessage(t('ui.loading'));

        return AnimasterApi.getTeamInfo(playerRef).then(function (rows)
        {
            animals = (rows || []).slice().sort(function (a, b)
            {
                return (parseInt(a.team_position, 10) || 0) - (parseInt(b.team_position, 10) || 0);
            });

            if (!selectedAnimal && animals.length)
            {
                selectedAnimal = animals[0];
            }
            else if (selectedAnimal)
            {
                var stillThere = animals.some(function (animal)
                {
                    return parseInt(animal.id_animal, 10) === parseInt(selectedAnimal.id_animal, 10);
                });

                if (!stillThere)
                {
                    selectedAnimal = animals.length ? animals[0] : null;
                }
                else
                {
                    animals.some(function (animal)
                    {
                        if (parseInt(animal.id_animal, 10) === parseInt(selectedAnimal.id_animal, 10))
                        {
                            selectedAnimal = animal;
                            return true;
                        }

                        return false;
                    });
                }
            }

            renderList();
            renderDetail();
            setMessage(animals.length ? '' : t('team.empty'));
        }).catch(function (err)
        {
            animals = [];
            selectedAnimal = null;
            renderList();
            renderDetail();
            setMessage(err.message || t('team.load_failed'), true);
        }).finally(function ()
        {
            busy = false;
            updateReorderControls();
            renderDetail();
        });
    }

    function renderMiniBar(container, ratio, barClass)
    {
        container.innerHTML = '';

        var track = document.createElement('div');
        track.className = 'team-mini-bar';

        var fill = document.createElement('div');
        fill.className = 'team-mini-bar-fill ' + barClass;
        fill.style.width = Math.round(ratio * 100) + '%';

        track.appendChild(fill);
        container.appendChild(track);
    }

    function updateReorderControls()
    {
        var canReorder = animals.length >= 2 && !busy;

        if (reorderToggleBtn)
        {
            reorderToggleBtn.disabled = !canReorder;
            reorderToggleBtn.classList.toggle('is-active', reorderMode);
            reorderToggleBtn.setAttribute('aria-pressed', reorderMode ? 'true' : 'false');
        }

        if (reorderSaveBtn)
        {
            reorderSaveBtn.disabled = busy || !reorderMode;
        }

        if (reorderCancelBtn)
        {
            reorderCancelBtn.disabled = busy || !reorderMode;
        }

        if (panel)
        {
            panel.classList.toggle('team-reorder-active', reorderMode);
        }

        if (reorderBarEl)
        {
            reorderBarEl.hidden = !reorderMode;
        }
    }

    function cloneAnimalsList(source)
    {
        return (source || []).map(function (animal)
        {
            return Object.assign({}, animal);
        });
    }

    function enterReorderMode()
    {
        if (reorderMode || busy || animals.length < 2)
        {
            if (animals.length < 2)
            {
                setMessage(t('team.reorder_need_two'), true);
            }

            return;
        }

        editingNickname = false;
        hideNicknameEdit();
        reorderMode = true;
        reorderAnimals = cloneAnimalsList(animals);
        dragSrcIndex = null;
        dropTargetIndex = null;
        updateReorderControls();
        setMessage('');
        renderList();
    }

    function exitReorderMode(rerender)
    {
        if (!reorderMode)
        {
            updateReorderControls();
            return;
        }

        reorderMode = false;
        reorderAnimals = [];
        dragSrcIndex = null;
        dropTargetIndex = null;
        updateReorderControls();

        if (rerender)
        {
            renderList();
        }
    }

    function toggleReorderMode()
    {
        if (reorderMode)
        {
            exitReorderMode(true);
            return;
        }

        enterReorderMode();
    }

    function moveReorderAnimal(fromIndex, toIndex)
    {
        if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= reorderAnimals.length || toIndex >= reorderAnimals.length)
        {
            return;
        }

        var moved = reorderAnimals.splice(fromIndex, 1)[0];
        reorderAnimals.splice(toIndex, 0, moved);
    }

    function clearDropTargetHighlight()
    {
        if (!listEl)
        {
            return;
        }

        listEl.querySelectorAll('.team-row-drop-target').forEach(function (row)
        {
            row.classList.remove('team-row-drop-target');
        });
    }

    function saveTeamOrder()
    {
        if (!reorderMode || !playerRef || busy || reorderAnimals.length < 2)
        {
            return;
        }

        var orderIds = reorderAnimals.map(function (animal)
        {
            return parseInt(animal.id_animal, 10) || 0;
        }).filter(function (id)
        {
            return id > 0;
        });

        if (!orderIds.length)
        {
            return;
        }

        busy = true;
        updateReorderControls();
        setMessage(t('team.reorder_saving'));

        AnimasterApi.saveTeamOrder(playerRef, orderIds).then(function ()
        {
            reorderMode = false;
            reorderAnimals = [];
            setMessage(t('team.reorder_saved'));
            return loadTeam(true);
        }).catch(function (err)
        {
            setMessage(err.message || t('team.reorder_save_failed'), true);
        }).finally(function ()
        {
            busy = false;
            updateReorderControls();
            renderList();
            renderDetail();
        });
    }

    function buildTeamRowContent(animal, slotLabel, includeGrip)
    {
        var fragment = document.createDocumentFragment();

        if (includeGrip)
        {
            var grip = document.createElement('span');
            grip.className = 'team-row-grip';
            grip.textContent = '⋮⋮';
            grip.setAttribute('aria-hidden', 'true');
            fragment.appendChild(grip);
        }

        var thumb = document.createElement('span');
        thumb.className = 'team-row-thumb';
        renderTeamThumb(thumb, animal, slotLabel);

        var body = document.createElement('span');
        body.className = 'team-row-body';

        var top = document.createElement('span');
        top.className = 'team-row-top';

        var name = document.createElement('span');
        name.className = 'team-row-name';
        name.textContent = displayName(animal);

        var lvl = document.createElement('span');
        lvl.className = 'team-row-lvl';
        lvl.textContent = t('team.lv_short', { level: animal.lvl || 1 });

        top.appendChild(name);
        top.appendChild(lvl);

        var meta = document.createElement('span');
        meta.className = 'team-row-meta';
        renderElementMeta(meta, animal);

        var hpWrap = document.createElement('span');
        hpWrap.className = 'team-row-bars';
        renderMiniBar(hpWrap, hpRatio(animal), hpBarClass(hpRatio(animal)));

        var xpWrap = document.createElement('span');
        xpWrap.className = 'team-row-bars team-row-xp';
        renderMiniBar(xpWrap, xpRange(animal).pct, 'xp-fill');

        body.appendChild(top);
        body.appendChild(meta);
        body.appendChild(hpWrap);
        body.appendChild(xpWrap);

        fragment.appendChild(thumb);
        fragment.appendChild(body);

        return fragment;
    }

    function renderReorderList()
    {
        if (!listEl)
        {
            return;
        }

        listEl.innerHTML = '';

        reorderAnimals.forEach(function (animal, index)
        {
            var row = document.createElement('div');
            row.className = 'team-row team-row-draggable';
            row.setAttribute('role', 'listitem');
            row.draggable = true;
            row.dataset.index = String(index);

            var hp = parseInt(animal.current_hp, 10) || 0;

            if (hp <= 0)
            {
                row.classList.add('fainted');
            }

            row.appendChild(buildTeamRowContent(animal, index + 1, true));

            row.addEventListener('dragstart', function (e)
            {
                dragSrcIndex = index;
                dropTargetIndex = null;
                row.classList.add('team-row-dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(index));
            });

            row.addEventListener('dragover', function (e)
            {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (dropTargetIndex !== index)
                {
                    clearDropTargetHighlight();
                    dropTargetIndex = index;
                    row.classList.add('team-row-drop-target');
                }
            });

            row.addEventListener('dragleave', function ()
            {
                if (dropTargetIndex === index)
                {
                    dropTargetIndex = null;
                }

                row.classList.remove('team-row-drop-target');
            });

            row.addEventListener('drop', function (e)
            {
                e.preventDefault();
                var fromIndex = dragSrcIndex;

                if (fromIndex === null)
                {
                    fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
                }

                if (!isNaN(fromIndex))
                {
                    moveReorderAnimal(fromIndex, index);
                }

                dragSrcIndex = null;
                dropTargetIndex = null;
                clearDropTargetHighlight();
                renderList();
            });

            row.addEventListener('dragend', function ()
            {
                dragSrcIndex = null;
                dropTargetIndex = null;
                row.classList.remove('team-row-dragging');
                clearDropTargetHighlight();
            });

            listEl.appendChild(row);
        });
        updateReorderControls();
    }

    function renderList()
    {
        if (!listEl)
        {
            return;
        }

        if (reorderMode)
        {
            renderReorderList();
            return;
        }

        listEl.innerHTML = '';

        if (!animals.length)
        {
            var empty = document.createElement('p');
            empty.className = 'team-empty';
            empty.textContent = t('team.no_animals');
            listEl.appendChild(empty);
            updateReorderControls();
            return;
        }

        animals.forEach(function (animal)
        {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'team-row';
            row.setAttribute('role', 'listitem');

            if (selectedAnimal && parseInt(selectedAnimal.id_animal, 10) === parseInt(animal.id_animal, 10))
            {
                row.classList.add('selected');
            }

            var hp = parseInt(animal.current_hp, 10) || 0;

            if (hp <= 0)
            {
                row.classList.add('fainted');
            }

            row.appendChild(buildTeamRowContent(animal, animal.team_position || '?', false));

            row.addEventListener('click', function ()
            {
                editingNickname = false;
                selectedAnimal = animal;
                renderList();
                renderDetail();
            });

            listEl.appendChild(row);
        });

        updateReorderControls();
    }

    function renderDetail()
    {
        if (!detailSpeciesEl || !detailLevelEl || !detailElementEl)
        {
            return;
        }

        if (!selectedAnimal)
        {
            detailSpeciesEl.textContent = '—';
            detailLevelEl.textContent = '';
            detailElementEl.textContent = '';
            editingNickname = false;

            if (detailNicknameDisplayEl)
            {
                detailNicknameDisplayEl.hidden = true;
            }

            if (nicknameEditEl)
            {
                nicknameEditEl.hidden = true;
            }

            if (detailNicknameInput)
            {
                detailNicknameInput.value = '';
            }

            updateDetailBars(null);
            renderBuffs(null);
            if (detailTabsEl)
            {
                detailTabsEl.hidden = true;
            }
            renderDetailTabPanels();
            return;
        }

        if (detailTabsEl)
        {
            detailTabsEl.hidden = false;
        }

        detailSpeciesEl.textContent = selectedAnimal.species || t('team.species_fallback', { id: selectedAnimal.id_species });
        detailLevelEl.textContent = t('team.level_prefix', { level: selectedAnimal.lvl || 1 });

        if (detailElementEl)
        {
            detailElementEl.innerHTML = '';

            if (selectedAnimal.element && typeof AnimasterElements !== 'undefined')
            {
                AnimasterElements.appendLabel(detailElementEl, elementDataFromAnimal(selectedAnimal), {
                    className: 'team-detail-element-label',
                    sizeClass: 'element-icon--md'
                });
            }
            else if (selectedAnimal.element)
            {
                detailElementEl.textContent = t('team.element_prefix', { element: selectedAnimal.element });
            }
        }

        if (detailNicknameInput)
        {
            detailNicknameInput.value = selectedAnimal.nickname || '';
            detailNicknameInput.disabled = busy;
        }

        if (detailNicknameSaveBtn)
        {
            detailNicknameSaveBtn.disabled = busy;
        }

        if (editingNickname)
        {
            if (detailNicknameDisplayEl)
            {
                detailNicknameDisplayEl.hidden = true;
            }

            if (nicknameEditEl)
            {
                nicknameEditEl.hidden = false;
            }
        }
        else
        {
            if (detailNicknameDisplayEl)
            {
                detailNicknameDisplayEl.textContent = quotedNickname(selectedAnimal);
                detailNicknameDisplayEl.hidden = false;
                detailNicknameDisplayEl.disabled = busy;
            }

            if (nicknameEditEl)
            {
                nicknameEditEl.hidden = true;
            }
        }

        updateDetailBars(selectedAnimal);
        renderBuffs(selectedAnimal);
        renderDetailTabPanels();
    }

    function initDetailTabs()
    {
        if (!detailTabsEl || detailTabsReady)
        {
            return;
        }

        detailTabsEl.innerHTML = '';

        DETAIL_TABS.forEach(function (tab)
        {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'team-detail-tab' + (tab.id === activeDetailTab ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', tab.id === activeDetailTab ? 'true' : 'false');
            btn.dataset.tab = tab.id;
            btn.textContent = t(tab.labelKey);

            btn.addEventListener('click', function ()
            {
                setDetailTab(tab.id);
            });

            detailTabsEl.appendChild(btn);
        });

        detailTabsReady = true;
    }

    function setDetailTab(tabId)
    {
        if (!DETAIL_TABS.some(function (tab) { return tab.id === tabId; }))
        {
            return;
        }

        activeDetailTab = tabId;

        if (detailTabsEl)
        {
            detailTabsEl.querySelectorAll('.team-detail-tab').forEach(function (btn)
            {
                var active = btn.dataset.tab === tabId;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        Object.keys(detailTabPanels).forEach(function (key)
        {
            var panelEl = detailTabPanels[key];

            if (!panelEl)
            {
                return;
            }

            var active = key === tabId;
            panelEl.hidden = !active;
            panelEl.classList.toggle('is-active', active);
        });

        renderDetailTabPanels();
    }

    function statValue(animal, prefix, statKey)
    {
        var value = animal[prefix + statKey];

        if (value === null || value === undefined || value === '')
        {
            return '—';
        }

        return String(value);
    }

    function renderStatPanel(panelEl, animal, prefix)
    {
        if (!panelEl)
        {
            return;
        }

        panelEl.innerHTML = '';

        if (!animal)
        {
            var empty = document.createElement('p');
            empty.className = 'team-detail-empty';
            empty.textContent = t('team.no_animals');
            panelEl.appendChild(empty);
            return;
        }

        var grid = document.createElement('div');
        grid.className = 'team-stat-grid';

        STAT_FIELDS.forEach(function (field)
        {
            var label = document.createElement('span');
            label.className = 'team-stat-label';
            label.textContent = field.label;

            var value = document.createElement('span');
            value.className = 'team-stat-value';
            value.textContent = statValue(animal, prefix, field.key);

            grid.appendChild(label);
            grid.appendChild(value);
        });

        panelEl.appendChild(grid);
    }

    function renderAbilitiesPanel(panelEl, animal)
    {
        if (!panelEl)
        {
            return;
        }

        panelEl.innerHTML = '';

        if (!animal)
        {
            var emptyAnimal = document.createElement('p');
            emptyAnimal.className = 'team-detail-empty';
            emptyAnimal.textContent = t('team.no_animals');
            panelEl.appendChild(emptyAnimal);
            return;
        }

        var cacheKey = String(animal.id_animal) + ':' + String(animal.lvl || 1);
        var cached = abilitiesCache[cacheKey];

        if (cached === 'loading')
        {
            var loading = document.createElement('p');
            loading.className = 'team-detail-loading';
            loading.textContent = t('team.abilities_loading');
            panelEl.appendChild(loading);
            return;
        }

        if (Array.isArray(cached))
        {
            if (!cached.length)
            {
                var emptyList = document.createElement('p');
                emptyList.className = 'team-detail-empty';
                emptyList.textContent = t('team.abilities_empty');
                panelEl.appendChild(emptyList);
                return;
            }

            var list = document.createElement('ul');
            list.className = 'team-abilities-list';

            cached.forEach(function (ability)
            {
                var item = document.createElement('li');
                item.className = 'team-ability-item';

                var name = document.createElement('div');
                name.className = 'team-ability-name';
                name.textContent = ability.ability || t('team.ability_fallback', { id: ability.id_ability || '?' });

                var meta = document.createElement('div');
                meta.className = 'team-ability-meta';
                meta.textContent = formatAbilityMeta(ability);

                item.appendChild(name);
                item.appendChild(meta);

                if (ability.descrizione)
                {
                    var desc = document.createElement('div');
                    desc.className = 'team-ability-desc';
                    desc.textContent = ability.descrizione;
                    item.appendChild(desc);
                }

                list.appendChild(item);
            });

            panelEl.appendChild(list);
            return;
        }

        if (!playerRef || abilitiesLoading)
        {
            var wait = document.createElement('p');
            wait.className = 'team-detail-loading';
            wait.textContent = t('team.abilities_loading');
            panelEl.appendChild(wait);
            return;
        }

        abilitiesCache[cacheKey] = 'loading';
        abilitiesLoading = true;

        var loadingRemote = document.createElement('p');
        loadingRemote.className = 'team-detail-loading';
        loadingRemote.textContent = t('team.abilities_loading');
        panelEl.appendChild(loadingRemote);

        AnimasterApi.getAbilityList(
            playerRef.id_user_ig || 0,
            animal.id_animal,
            animal.lvl || 1
        ).then(function (rows)
        {
            abilitiesCache[cacheKey] = rows || [];
        }).catch(function ()
        {
            abilitiesCache[cacheKey] = [];
        }).finally(function ()
        {
            abilitiesLoading = false;

            if (selectedAnimal
                && parseInt(selectedAnimal.id_animal, 10) === parseInt(animal.id_animal, 10)
                && activeDetailTab === 'abilities')
            {
                renderDetailTabPanels();
            }
        });
    }

    var CURRENT_STAT_LABELS = {
        hp: 'HP',
        max_hp: 'Max HP',
        atk: 'ATK',
        def: 'DEF',
        matk: 'MATK',
        mdef: 'MDEF',
        acc: 'ACC',
        eva: 'EVA',
        cr: 'CR',
        spd: 'SPD'
    };

    function formatCurrentStatValue(statKey, value)
    {
        if (statKey === 'acc' || statKey === 'eva' || statKey === 'cr')
        {
            return String(parseInt(value, 10) || 0);
        }

        var numeric = Number(value);

        if (isNaN(numeric))
        {
            return '0';
        }

        if (Math.abs(numeric - Math.round(numeric)) < 0.001)
        {
            return String(Math.round(numeric));
        }

        return numeric.toFixed(1);
    }

    function renderCurrentStatBuffIcons(containerEl, buffsArray)
    {
        if (typeof AnimasterBuffDisplay !== 'undefined' && typeof AnimasterBuffDisplay.renderBuffIcons === 'function')
        {
            AnimasterBuffDisplay.renderBuffIcons(containerEl, buffsArray);
        }
    }

    function renderCurrentStatPanel(panelEl, animal)
    {
        if (!panelEl)
        {
            return;
        }

        panelEl.innerHTML = '';

        if (!animal)
        {
            var empty = document.createElement('p');
            empty.className = 'team-detail-empty';
            empty.textContent = t('team.no_animals');
            panelEl.appendChild(empty);
            return;
        }

        var hint = document.createElement('p');
        hint.className = 'team-current-stats-hint';
        hint.textContent = t('team.current_stats_hint');
        panelEl.appendChild(hint);

        var sheet = animal.current_stat_sheet || [];

        if (!sheet.length)
        {
            var missing = document.createElement('p');
            missing.className = 'team-detail-empty';
            missing.textContent = t('team.current_stats_empty');
            panelEl.appendChild(missing);
            return;
        }

        var list = document.createElement('div');
        list.className = 'team-current-stats';

        sheet.forEach(function (row)
        {
            var statKey = row.stat_key || '';
            var label = CURRENT_STAT_LABELS[statKey] || statKey;
            var baseText = formatCurrentStatValue(statKey, row.base);
            var effectiveText = formatCurrentStatValue(statKey, row.effective);
            var line = document.createElement('div');
            line.className = 'combat-stat-row';

            if (row.is_modified)
            {
                line.classList.add('combat-stat-row-modified');
            }

            var labelEl = document.createElement('span');
            labelEl.className = 'combat-stat-label';
            labelEl.textContent = label;

            var valueEl = document.createElement('span');
            valueEl.className = 'combat-stat-value';

            if (row.is_modified && row.buffs && row.buffs.length)
            {
                var buffWrap = document.createElement('span');
                buffWrap.className = 'combat-stat-row-buffs';
                renderCurrentStatBuffIcons(buffWrap, row.buffs);
                valueEl.appendChild(buffWrap);
            }

            if (row.is_modified)
            {
                var baseEl = document.createElement('span');
                baseEl.className = 'combat-stat-base';
                baseEl.textContent = baseText;

                var effectiveEl = document.createElement('span');
                effectiveEl.className = 'combat-stat-effective';
                effectiveEl.textContent = effectiveText;

                valueEl.appendChild(baseEl);
                valueEl.appendChild(document.createTextNode(' \u2192 '));
                valueEl.appendChild(effectiveEl);
            }
            else
            {
                valueEl.textContent = effectiveText;
            }

            line.appendChild(labelEl);
            line.appendChild(valueEl);
            list.appendChild(line);
        });

        panelEl.appendChild(list);
    }

    function formatAbilityMeta(ability)
    {
        var parts = [];

        if (ability.element)
        {
            parts.push(ability.element);
        }

        if (ability.power)
        {
            parts.push(t('team.ability_power', { value: ability.power }));
        }
        else if (ability.m_power)
        {
            parts.push(t('team.ability_mpower', { value: ability.m_power }));
        }

        if (ability.accuracy)
        {
            parts.push(t('team.ability_accuracy', { value: ability.accuracy }));
        }

        return parts.join(' · ');
    }

    function renderDetailTabPanels()
    {
        if (!selectedAnimal)
        {
            renderCurrentStatPanel(detailTabPanels.current, null);
            renderStatPanel(detailTabPanels.base, null, 'base_');
            renderStatPanel(detailTabPanels.dna, null, 'dna_');
            renderStatPanel(detailTabPanels.exp, null, 'xp_');
            renderStatPanel(detailTabPanels.points, null, 'pt_');
            renderAbilitiesPanel(detailTabPanels.abilities, null);
            return;
        }

        if (activeDetailTab === 'current')
        {
            renderCurrentStatPanel(detailTabPanels.current, selectedAnimal);
        }
        else if (activeDetailTab === 'base')
        {
            renderStatPanel(detailTabPanels.base, selectedAnimal, 'base_');
        }
        else if (activeDetailTab === 'dna')
        {
            renderStatPanel(detailTabPanels.dna, selectedAnimal, 'dna_');
        }
        else if (activeDetailTab === 'exp')
        {
            renderStatPanel(detailTabPanels.exp, selectedAnimal, 'xp_');
        }
        else if (activeDetailTab === 'points')
        {
            renderStatPanel(detailTabPanels.points, selectedAnimal, 'pt_');
        }
        else if (activeDetailTab === 'abilities')
        {
            renderAbilitiesPanel(detailTabPanels.abilities, selectedAnimal);
        }
    }

    function formatBuffDuration(totalSeconds)
    {
        var seconds = Math.max(0, parseInt(totalSeconds, 10) || 0);
        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;

        if (hours > 0)
        {
            return String(hours) + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        }

        return String(minutes) + ':' + String(secs).padStart(2, '0');
    }

    function buffStackKey(buff)
    {
        var buffCode = String(buff.buff_code || '').trim();

        if (buffCode)
        {
            return [
                buff.scope || 'animal',
                buffCode
            ].join('|');
        }

        return [
            buff.scope || 'animal',
            buff.is_debuff || 'N',
            buff.stat_key || '',
            buff.modifier_kind || 'percent',
            String(buff.modifier_value || 0)
        ].join('|');
    }

    function formatBuffStatLabel(statKey)
    {
        if (typeof AnimasterBuffDisplay !== 'undefined')
        {
            return AnimasterBuffDisplay.statKeyLabel(statKey);
        }

        return String(statKey || '').toUpperCase();
    }

    function computeStackedBuffEffect(stacks)
    {
        if (typeof AnimasterBuffDisplay !== 'undefined')
        {
            return AnimasterBuffDisplay.computeStackedEffect(stacks);
        }

        if (!stacks.length)
        {
            return '';
        }

        var first = stacks[0];
        var statLabel = formatBuffStatLabel(first.stat_key);
        var isDebuff = first.is_debuff === 'S';
        var kind = first.modifier_kind || 'percent';

        if (kind === 'flat')
        {
            var flatTotal = 0;

            stacks.forEach(function (buff)
            {
                var magnitude = Math.abs(parseFloat(buff.modifier_value) || 0);
                flatTotal += isDebuff ? -magnitude : magnitude;
            });

            return (flatTotal >= 0 ? '+' : '') + flatTotal + ' ' + statLabel;
        }

        var multiplier = 1;

        stacks.forEach(function (buff)
        {
            var magnitude = Math.abs(parseFloat(buff.modifier_value) || 0);
            var signed = isDebuff ? -magnitude : magnitude;
            multiplier *= (1 + (signed / 100));
        });

        var percentTotal = Math.round((multiplier - 1) * 100);

        return (percentTotal >= 0 ? '+' : '') + percentTotal + '% ' + statLabel;
    }

    function minStackSecondsRemaining(stacks)
    {
        var min = null;

        stacks.forEach(function (buff)
        {
            var remaining = Math.max(0, parseInt(buff.seconds_remaining, 10) || 0);

            if (min === null || remaining < min)
            {
                min = remaining;
            }
        });

        return min === null ? 0 : min;
    }

    function groupBuffStacks(buffs)
    {
        var map = {};
        var order = [];

        buffs.forEach(function (buff)
        {
            var key = buffStackKey(buff);

            if (!map[key])
            {
                map[key] = {
                    key: key,
                    stacks: []
                };
                order.push(key);
            }

            map[key].stacks.push(buff);
        });

        return order.map(function (key)
        {
            var group = map[key];
            var stacks = group.stacks;
            var first = stacks[0];

            return {
                key: key,
                stacks: stacks,
                stackCount: stacks.length,
                is_debuff: first.is_debuff === 'S',
                scope: first.scope || 'animal',
                name: first.name || first.buff_code || '',
                icon: first.icon || '',
                description: first.description || '',
                totalEffect: computeStackedBuffEffect(stacks),
                minSecondsRemaining: minStackSecondsRemaining(stacks)
            };
        });
    }

    function renderBuffs(animal)
    {
        if (!detailBuffsEl || !detailBuffsListEl)
        {
            return;
        }

        var animalId = animal && animal.id_animal ? parseInt(animal.id_animal, 10) : null;

        if (animalId !== buffRenderAnimalId)
        {
            expandedBuffStacks = {};
            buffRenderAnimalId = animalId;
        }

        var buffs = animal && animal.active_buffs ? animal.active_buffs : [];

        if (!buffs.length)
        {
            detailBuffsEl.hidden = true;
            detailBuffsListEl.innerHTML = '';
            return;
        }

        detailBuffsEl.hidden = false;
        detailBuffsListEl.innerHTML = '';

        groupBuffStacks(buffs).forEach(function (group)
        {
            var item = document.createElement('li');
            var expanded = group.stackCount > 1 && !!expandedBuffStacks[group.key];
            item.className = 'team-buff-item' + (group.is_debuff ? ' is-debuff' : ' is-buff')
                + (expanded ? ' is-stacks-expanded' : '');
            item.dataset.buffStackKey = group.key;
            var displayName = (group.scope === 'party' ? t('team.buff_party_prefix') + ' ' : '') + group.name;

            var head = document.createElement('div');
            head.className = 'team-buff-head';

            if (group.icon && typeof AnimasterBuffDisplay !== 'undefined')
            {
                var iconBuff = Object.assign({}, group.stacks[0], {
                    total_effect_label: group.totalEffect,
                    stack_count: group.stackCount
                });
                var iconBadge = AnimasterBuffDisplay.createBuffIconElement(iconBuff);
                iconBadge.classList.add('team-buff-icon');
                head.appendChild(iconBadge);
            }

            var name = document.createElement('span');
            name.className = 'team-buff-name';
            name.textContent = displayName;

            head.appendChild(name);

            if (group.stackCount > 1)
            {
                var stackBtn = document.createElement('button');
                stackBtn.type = 'button';
                stackBtn.className = 'team-buff-stacks';
                stackBtn.textContent = '×' + group.stackCount;
                stackBtn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                stackBtn.setAttribute('aria-label', t('team.buff_stacks_toggle', { count: group.stackCount }));
                stackBtn.addEventListener('click', function ()
                {
                    expandedBuffStacks[group.key] = !expandedBuffStacks[group.key];
                    renderBuffs(selectedAnimal);
                });
                head.appendChild(stackBtn);
            }

            if (group.stackCount === 1)
            {
                var timer = document.createElement('span');
                timer.className = 'team-buff-timer team-buff-timer-summary';
                timer.textContent = formatBuffDuration(group.stacks[0].seconds_remaining);
                head.appendChild(timer);
            }

            item.appendChild(head);

            if (group.totalEffect)
            {
                var total = document.createElement('div');
                total.className = 'team-buff-total';
                total.textContent = group.totalEffect;
                item.appendChild(total);
            }

            if (group.description)
            {
                var desc = document.createElement('div');
                desc.className = 'team-buff-desc';
                desc.textContent = group.description;
                item.appendChild(desc);
            }

            if (group.stackCount > 1)
            {
                var stackList = document.createElement('ul');
                stackList.className = 'team-buff-stack-list';

                group.stacks.forEach(function (buff, index)
                {
                    var stackRow = document.createElement('li');
                    stackRow.className = 'team-buff-stack-row';

                    var stackLabel = document.createElement('span');
                    stackLabel.className = 'team-buff-stack-label';
                    stackLabel.textContent = t('team.buff_stack_entry', { n: index + 1 });

                    var stackTimer = document.createElement('span');
                    stackTimer.className = 'team-buff-timer';
                    stackTimer.textContent = formatBuffDuration(buff.seconds_remaining);

                    stackRow.appendChild(stackLabel);
                    stackRow.appendChild(stackTimer);
                    stackList.appendChild(stackRow);
                });

                item.appendChild(stackList);
            }

            detailBuffsListEl.appendChild(item);
        });
    }

    function tickBuffTimers()
    {
        if (!open || !selectedAnimal || !detailBuffsListEl)
        {
            return;
        }

        var buffs = selectedAnimal.active_buffs || [];
        var expired = false;

        buffs.forEach(function (buff)
        {
            var remaining = Math.max(0, (parseInt(buff.seconds_remaining, 10) || 0) - 1);
            buff.seconds_remaining = remaining;

            if (remaining <= 0)
            {
                expired = true;
            }
        });

        if (expired)
        {
            loadTeam(true);
            return;
        }

        renderBuffs(selectedAnimal);
    }

    function startBuffTimer()
    {
        stopBuffTimer();
        buffTimerId = window.setInterval(tickBuffTimers, 1000);
    }

    function stopBuffTimer()
    {
        if (buffTimerId)
        {
            window.clearInterval(buffTimerId);
            buffTimerId = null;
        }
    }

    function updateDetailBars(animal)
    {
        if (!detailHpFillEl || !detailHpTextEl || !detailXpFillEl || !detailXpTextEl)
        {
            return;
        }

        if (!animal)
        {
            detailHpFillEl.style.width = '0%';
            detailHpFillEl.className = 'team-detail-bar-fill';
            detailHpTextEl.textContent = '';
            detailXpFillEl.style.width = '0%';
            detailXpTextEl.textContent = '';
            return;
        }

        var hp = parseInt(animal.current_hp, 10) || 0;
        var maxHp = displayMaxHp(animal);
        var hpPctValue = hpRatio(animal);
        var xp = xpRange(animal);

        detailHpFillEl.style.width = Math.round(hpPctValue * 100) + '%';
        detailHpFillEl.className = 'team-detail-bar-fill ' + hpBarClass(hpPctValue);
        detailHpTextEl.textContent = hp + ' / ' + maxHp + (hp <= 0 ? ' ' + t('team.fainted') : '');

        detailXpFillEl.style.width = Math.round(xp.pct * 100) + '%';
        detailXpTextEl.textContent = xp.exp + ' / ' + xp.max;
    }

    function saveNickname()
    {
        if (!selectedAnimal || !playerRef || !detailNicknameInput || busy)
        {
            return;
        }

        var nickname = detailNicknameInput.value.trim();

        busy = true;
        setMessage(t('team.saving_nickname'));

        if (detailNicknameSaveBtn)
        {
            detailNicknameSaveBtn.disabled = true;
        }

        AnimasterApi.changeAnimalNickname(playerRef, selectedAnimal.id_animal, nickname).then(function ()
        {
            editingNickname = false;
            setMessage(t('team.nickname_saved'));
            return loadTeam(true);
        }).catch(function (err)
        {
            setMessage(err.message || t('team.nickname_save_failed'), true);
        }).finally(function ()
        {
            busy = false;

            if (detailNicknameSaveBtn)
            {
                detailNicknameSaveBtn.disabled = false;
            }

            renderDetail();
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        setXpConstant: setXpConstant,
        setToggleEnabled: setToggleEnabled,
        isOpen: isOpen,
        open: openPanel,
        close: closePanel,
        refresh: loadTeam
    };
})();

