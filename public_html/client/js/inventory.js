/**
 * Inventory panel (web port of InventoryManager / InventoryItem).
 */
var AnimasterInventory = (function () 
{
    var panel = null;
    var toggleBtn = null;
    var closeBtn = null;
    var listEl = null;
    var detailNameEl = null;
    var detailDescEl = null;
    var detailMetaEl = null;
    var useBtn = null;
    var messageEl = null;
    var teamOverlay = null;
    var teamListEl = null;
    var teamCancelBtn = null;

    var playerRef = null;
    var items = [];
    var selectedItem = null;
    var teamAnimals = [];
    var open = false;
    var busy = false;

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
        panel = document.getElementById('inventory-panel');
        toggleBtn = document.getElementById('inventory-toggle');
        closeBtn = document.getElementById('inventory-close');
        listEl = document.getElementById('inventory-list');
        detailNameEl = document.getElementById('inventory-detail-name');
        detailDescEl = document.getElementById('inventory-detail-desc');
        detailMetaEl = document.getElementById('inventory-detail-meta');
        useBtn = document.getElementById('inventory-use-btn');
        messageEl = document.getElementById('inventory-message');
        teamOverlay = document.getElementById('inventory-team-overlay');
        teamListEl = document.getElementById('inventory-team-list');
        teamCancelBtn = document.getElementById('inventory-team-cancel');

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

        if (useBtn)
        {
            useBtn.addEventListener('click', onUseClicked);
        }

        if (teamCancelBtn)
        {
            teamCancelBtn.addEventListener('click', hideTeamPicker);
        }

        if (panel && typeof AnimasterPanelDrag !== 'undefined')
        {
            var dragBounds = document.querySelector('.canvas-wrap');
            var dragHandle = panel.querySelector('.inventory-header');

            if (dragBounds && dragHandle)
            {
                AnimasterPanelDrag.attach(panel, dragHandle, dragBounds);
            }
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'KeyI' && !e.ctrlKey && !e.metaKey && !e.altKey)
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
                if (teamOverlay && !teamOverlay.hidden)
                {
                    hideTeamPicker();
                }
                else
                {
                    closePanel();
                }
            }
        });
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

        setMessage('');
        loadInventory();
    }

    function closePanel()
    {
        open = false;
        hideTeamPicker();

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

    function loadInventory()
    {
        if (!playerRef || busy)
        {
            return Promise.resolve();
        }

        busy = true;
        setMessage(t('ui.loading'));

        return AnimasterApi.getInventory(playerRef, false).then(function (rows)
        {
            items = rows || [];
            selectedItem = items.length ? items[0] : null;
            renderList();
            renderDetail();
            setMessage(items.length ? '' : t('inventory.empty'));
        }).catch(function (err)
        {
            items = [];
            selectedItem = null;
            renderList();
            renderDetail();
            setMessage(err.message || t('inventory.load_failed'), true);
        }).finally(function ()
        {
            busy = false;
            renderDetail();
        });
    }

    function itemThumbLabel(item)
    {
        if (!item)
        {
            return '?';
        }

        if (item.item_type === 'potions')
        {
            return 'P';
        }

        return String(item.id_item_type);
    }

    function renderList()
    {
        if (!listEl)
        {
            return;
        }

        listEl.innerHTML = '';

        if (!items.length)
        {
            var empty = document.createElement('p');
            empty.className = 'inventory-empty';
            empty.textContent = t('inventory.no_items');
            listEl.appendChild(empty);
            return;
        }

        items.forEach(function (item)
        {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'inventory-item';

            if (selectedItem && selectedItem.id_item_type === item.id_item_type)
            {
                row.classList.add('selected');
            }

            var thumb = document.createElement('span');
            thumb.className = 'inventory-item-thumb';
            thumb.textContent = itemThumbLabel(item);

            var body = document.createElement('span');
            body.className = 'inventory-item-body';

            var name = document.createElement('span');
            name.className = 'inventory-item-name';
            name.textContent = item.nome || t('inventory.item_fallback', { id: item.id_item_type });

            var qty = document.createElement('span');
            qty.className = 'inventory-item-qty';
            qty.textContent = 'x' + (item.quantita || 0);

            body.appendChild(name);
            body.appendChild(qty);
            row.appendChild(thumb);
            row.appendChild(body);

            row.addEventListener('click', function ()
            {
                selectedItem = item;
                renderList();
                renderDetail();
            });

            listEl.appendChild(row);
        });
    }

    function renderDetail()
    {
        if (!detailNameEl || !detailDescEl || !detailMetaEl || !useBtn)
        {
            return;
        }

        if (!selectedItem)
        {
            detailNameEl.textContent = '—';
            detailDescEl.textContent = t('inventory.select_item');
            detailMetaEl.textContent = '';
            useBtn.hidden = true;
            return;
        }

        detailNameEl.textContent = selectedItem.nome || t('inventory.item_fallback', { id: selectedItem.id_item_type });
        detailDescEl.textContent = selectedItem.descrizione || '';

        var meta = [];

        if (selectedItem.use_effect)
        {
            meta.push(t('inventory.meta_effect', { effect: selectedItem.use_effect }));
        }

        if (selectedItem.usable_on)
        {
            meta.push(t('inventory.meta_usable_on', { target: selectedItem.usable_on }));
        }

        detailMetaEl.textContent = meta.join(' · ');

        var canUse = selectedItem.flg_usable === 'S'
            && selectedItem.flg_usable_outside_battle === 'S';

        useBtn.hidden = !canUse;
        useBtn.disabled = busy || !canUse;
    }

    function canUseItemOnAnimal(item, animal)
    {
        if (!item || !animal)
        {
            return false;
        }

        if (item.flg_usable !== 'S')
        {
            return false;
        }

        if (item.flg_usable_outside_battle !== 'S')
        {
            return false;
        }

        var hp = parseInt(animal.current_hp, 10) || 0;

        if (item.flg_usable_on_fainted === 'N' && hp <= 0)
        {
            return false;
        }

        if (item.flg_usable_on_alive === 'N' && hp > 0)
        {
            return false;
        }

        return true;
    }

    function onUseClicked()
    {
        if (!selectedItem || !playerRef || busy)
        {
            return;
        }

        if (selectedItem.usable_on === 'animals')
        {
            showTeamPicker();
            return;
        }

        setMessage(t('inventory.cannot_use_here'), true);
    }

    function showTeamPicker()
    {
        if (!teamOverlay || !teamListEl || !selectedItem)
        {
            return;
        }

        busy = true;
        setMessage(t('combat.loading_team'));

        AnimasterApi.getTeamInfo(playerRef).then(function (animals)
        {
            teamAnimals = animals || [];
            teamListEl.innerHTML = '';

            if (!teamAnimals.length)
            {
                setMessage(t('inventory.no_team_animals'), true);
                return;
            }

            teamAnimals.forEach(function (animal)
            {
                var eligible = canUseItemOnAnimal(selectedItem, animal);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'inventory-team-row';
                btn.disabled = !eligible;

                var hp = parseInt(animal.current_hp, 10) || 0;
                var maxHp = parseInt(animal.max_hp, 10) || 0;
                var label = (animal.nickname || animal.species || t('team.animal_fallback', { id: animal.id_animal || '?' }))
                    + ' ' + t('team.lv_short', { level: animal.lvl || 1 })
                    + ' — ' + hp + '/' + maxHp + ' HP';

                btn.textContent = label;

                if (eligible)
                {
                    btn.addEventListener('click', function ()
                    {
                        useItemOnAnimal(animal);
                    });
                }

                teamListEl.appendChild(btn);
            });

            teamOverlay.hidden = false;
            setMessage('');
        }).catch(function (err)
        {
            setMessage(err.message || t('team.load_failed'), true);
        }).finally(function ()
        {
            busy = false;
            renderDetail();
        });
    }

    function hideTeamPicker()
    {
        if (teamOverlay)
        {
            teamOverlay.hidden = true;
        }
    }

    function useItemOnAnimal(animal)
    {
        if (!selectedItem || !playerRef || !animal || busy)
        {
            return;
        }

        if (!canUseItemOnAnimal(selectedItem, animal))
        {
            setMessage(t('inventory.cannot_use_on_animal'), true);
            return;
        }

        busy = true;
        hideTeamPicker();
        setMessage(t('inventory.using_item'));
        useBtn.disabled = true;

        AnimasterApi.useItem(playerRef, selectedItem.id_item_type, animal.id_animal).then(function ()
        {
            setMessage(t('inventory.item_used', {
                name: animal.nickname || animal.species || t('team.animal_fallback', { id: animal.id_animal || '?' })
            }));

            return loadInventory();
        }).then(function ()
        {
            if (typeof AnimasterTeam !== 'undefined' && AnimasterTeam.isOpen())
            {
                return AnimasterTeam.refresh();
            }
        }).catch(function (err)
        {
            setMessage(err.message || t('inventory.use_failed'), true);
        }).finally(function ()
        {
            busy = false;
            renderDetail();
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        setToggleEnabled: setToggleEnabled,
        isOpen: isOpen,
        open: openPanel,
        close: closePanel,
        refresh: loadInventory
    };
})();
      
