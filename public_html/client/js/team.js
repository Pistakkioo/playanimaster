/**
 * Team panel (web port of TeamManager / MenuAnimal).
 */
var AnimasterTeam = (function ()
{
    var panel = null;
    var toggleBtn = null;
    var closeBtn = null;
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
    var messageEl = null;

    var playerRef = null;
    var animals = [];
    var selectedAnimal = null;
    var open = false;
    var busy = false;
    var editingNickname = false;
    var lvlUpConstant = 40;

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
        messageEl = document.getElementById('team-message');

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
    }

    function closePanel()
    {
        open = false;
        editingNickname = false;

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

    function hpRatio(animal)
    {
        var hp = parseInt(animal.current_hp, 10) || 0;
        var maxHp = parseInt(animal.max_hp, 10) || 1;
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

    function renderList()
    {
        if (!listEl)
        {
            return;
        }

        listEl.innerHTML = '';

        if (!animals.length)
        {
            var empty = document.createElement('p');
            empty.className = 'team-empty';
            empty.textContent = t('team.no_animals');
            listEl.appendChild(empty);
            return;
        }

        animals.forEach(function (animal)
        {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'team-row';

            if (selectedAnimal && parseInt(selectedAnimal.id_animal, 10) === parseInt(animal.id_animal, 10))
            {
                row.classList.add('selected');
            }

            var hp = parseInt(animal.current_hp, 10) || 0;

            if (hp <= 0)
            {
                row.classList.add('fainted');
            }

            var thumb = document.createElement('span');
            thumb.className = 'team-row-thumb';
            thumb.textContent = String(animal.team_position || '?');

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
            meta.textContent = animal.element || '—';

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

            row.appendChild(thumb);
            row.appendChild(body);

            row.addEventListener('click', function ()
            {
                editingNickname = false;
                selectedAnimal = animal;
                renderList();
                renderDetail();
            });

            listEl.appendChild(row);
        });
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
            return;
        }

        detailSpeciesEl.textContent = selectedAnimal.species || t('team.species_fallback', { id: selectedAnimal.id_species });
        detailLevelEl.textContent = t('team.level_prefix', { level: selectedAnimal.lvl || 1 });
        detailElementEl.textContent = selectedAnimal.element
            ? t('team.element_prefix', { element: selectedAnimal.element })
            : '';

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
        var maxHp = parseInt(animal.max_hp, 10) || 0;
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

