/**
 * Player-to-player trade: requests, offer UI, confirm flow.
 */
var AnimasterTrade = (function ()
{
    var REQUEST_SECONDS = 12;
    var POLL_MS = 1200;
    var SAVE_DEBOUNCE_MS = 400;

    var requestOverlay = null;
    var requestTextEl = null;
    var requestTimerFill = null;
    var requestAcceptBtn = null;
    var requestDeclineBtn = null;

    var overlay = null;
    var titleEl = null;
    var closeBtn = null;
    var myLabelEl = null;
    var theirLabelEl = null;
    var myGoldBalanceEl = null;
    var myGoldInput = null;
    var theirGoldOfferEl = null;
    var myItemsEl = null;
    var theirItemsEl = null;
    var addItemBtn = null;
    var statusEl = null;
    var confirmBtn = null;
    var cancelBtn = null;

    var pickOverlay = null;
    var pickListEl = null;
    var pickCloseBtn = null;
    var pickQtyPanel = null;
    var pickQtyNameEl = null;
    var pickQtyInput = null;
    var pickQtyHintEl = null;
    var pickQtyConfirmBtn = null;
    var pickQtyBackBtn = null;
    var pickQuantityItem = null;

    var playerRef = null;
    var pollTimer = null;
    var busy = false;
    var saveTimer = null;

    var incomingRequest = null;
    var outgoingRequest = null;
    var dismissedRequestIds = {};
    var tradeState = null;
    var bagItems = [];

    var localGold = 0;
    var localItems = [];
    var offerDirty = false;
    var savingOffer = false;

    function isLocalOfferEditActive()
    {
        if (!tradeState || tradeState.my_confirmed)
        {
            return false;
        }

        if (savingOffer || saveTimer)
        {
            return true;
        }

        if (offerDirty)
        {
            return true;
        }

        if (myGoldInput && document.activeElement === myGoldInput)
        {
            return true;
        }

        if (pickOverlay && !pickOverlay.hidden)
        {
            return true;
        }

        return false;
    }

    function mapOfferItems(rows)
    {
        return (rows || []).map(function (row)
        {
            return {
                id_item_type: row.id_item_type,
                quantity: row.quantity,
                nome: row.nome
            };
        });
    }

    function syncLocalOfferFromState(state)
    {
        localGold = state.my_gold_offer || 0;
        localItems = mapOfferItems(state.my_items);
        offerDirty = false;
    }

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
        requestOverlay = document.getElementById('trade-request-overlay');
        requestTextEl = document.getElementById('trade-request-text');
        requestTimerFill = document.getElementById('trade-request-timer-fill');
        requestAcceptBtn = document.getElementById('trade-request-accept');
        requestDeclineBtn = document.getElementById('trade-request-decline');

        overlay = document.getElementById('trade-overlay');
        titleEl = document.getElementById('trade-title');
        closeBtn = document.getElementById('trade-close');
        myLabelEl = document.getElementById('trade-my-label');
        theirLabelEl = document.getElementById('trade-their-label');
        myGoldBalanceEl = document.getElementById('trade-my-gold-balance');
        myGoldInput = document.getElementById('trade-my-gold-input');
        theirGoldOfferEl = document.getElementById('trade-their-gold-offer');
        myItemsEl = document.getElementById('trade-my-items');
        theirItemsEl = document.getElementById('trade-their-items');
        addItemBtn = document.getElementById('trade-add-item');
        statusEl = document.getElementById('trade-status');
        confirmBtn = document.getElementById('trade-confirm-btn');
        cancelBtn = document.getElementById('trade-cancel-btn');

        pickOverlay = document.getElementById('trade-pick-overlay');
        pickListEl = document.getElementById('trade-pick-list');
        pickCloseBtn = document.getElementById('trade-pick-close');
        pickQtyPanel = document.getElementById('trade-pick-qty');
        pickQtyNameEl = document.getElementById('trade-pick-qty-name');
        pickQtyInput = document.getElementById('trade-pick-qty-input');
        pickQtyHintEl = document.getElementById('trade-pick-qty-hint');
        pickQtyConfirmBtn = document.getElementById('trade-pick-qty-confirm');
        pickQtyBackBtn = document.getElementById('trade-pick-qty-back');

        if (!overlay)
        {
            return;
        }

        if (requestAcceptBtn)
        {
            requestAcceptBtn.addEventListener('click', function ()
            {
                respondIncoming(true);
            });
        }

        if (requestDeclineBtn)
        {
            requestDeclineBtn.addEventListener('click', function ()
            {
                respondIncoming(false);
            });
        }

        if (closeBtn)
        {
            closeBtn.addEventListener('click', onCancelTrade);
        }

        if (cancelBtn)
        {
            cancelBtn.addEventListener('click', onCancelTrade);
        }

        if (confirmBtn)
        {
            confirmBtn.addEventListener('click', onConfirmTrade);
        }

        if (addItemBtn)
        {
            addItemBtn.addEventListener('click', openPickItems);
        }

        if (pickCloseBtn)
        {
            pickCloseBtn.addEventListener('click', closePickItems);
        }

        if (pickQtyConfirmBtn)
        {
            pickQtyConfirmBtn.addEventListener('click', confirmPickQuantity);
        }

        if (pickQtyBackBtn)
        {
            pickQtyBackBtn.addEventListener('click', hidePickQuantity);
        }

        if (pickQtyInput)
        {
            pickQtyInput.addEventListener('keydown', function (e)
            {
                if (e.key === 'Enter')
                {
                    e.preventDefault();
                    confirmPickQuantity();
                }
            });
        }

        if (myGoldInput)
        {
            myGoldInput.addEventListener('input', scheduleSaveOffer);
        }

        pollTimer = setInterval(poll, POLL_MS);
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function isTradeOpen()
    {
        return !!(overlay && !overlay.hidden);
    }

    function requestToPlayer(targetInfo)
    {
        if (!playerRef || !targetInfo || busy)
        {
            return;
        }

        var targetId = parseInt(targetInfo.id, 10) || 0;

        if (targetId <= 0)
        {
            return;
        }

        if (typeof AnimasterCombat !== 'undefined' && AnimasterCombat.isVisible())
        {
            return;
        }

        busy = true;

        AnimasterApi.sendTradeRequest(playerRef, targetId).then(function ()
        {
            if (typeof AnimasterNotifications !== 'undefined')
            {
                AnimasterNotifications.showLocal(t('trade.request_sent'), 'trade', '');
            }
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
            poll();
        });
    }

    function errorText(code)
    {
        var key = String(code || '').toUpperCase();

        if (key === 'OFFLINE')
        {
            return t('trade.error_offline');
        }

        if (key === 'BUSY' || key === 'ALREADY_PENDING')
        {
            return t('trade.error_busy');
        }

        if (key === 'NOT_ENOUGH_GOLD')
        {
            return t('trade.error_not_enough_gold');
        }

        if (key === 'NOT_ENOUGH_ITEMS')
        {
            return t('trade.error_not_enough_items');
        }

        if (key === 'EXPIRED' || key === 'NOT_FOUND')
        {
            return t('trade.expired');
        }

        if (key === 'RESPOND_FAILED')
        {
            return t('trade.error_generic');
        }

        return t('trade.error_generic');
    }

    function pausePoll()
    {
        if (pollTimer)
        {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function resumePoll()
    {
        if (pollTimer || !playerRef)
        {
            return;
        }

        pollTimer = setInterval(function ()
        {
            poll();
        }, POLL_MS);
    }

    function poll(force)
    {
        if (!playerRef || (busy && !force))
        {
            return;
        }

        AnimasterApi.pollTrade(playerRef).then(function (data)
        {
            handlePoll(data);
        }).catch(function (err)
        {
            console.warn('[AnimasterTrade] poll failed:', err && err.message ? err.message : err);
        });
    }

    function handlePoll(data)
    {
        if (!data)
        {
            return;
        }

        outgoingRequest = data.outgoing_request || null;

        if (data.incoming_requests && data.incoming_requests.length)
        {
            var req = data.incoming_requests[0];

            if (!dismissedRequestIds[req.id_trade_request])
            {
                showIncomingRequest(req);
            }
        }
        else if (incomingRequest)
        {
            hideIncomingRequest();
        }

        if (data.active_trade)
        {
            mergePollTradeState(data.active_trade);
            openTradeOverlay();
        }
        else if (tradeState)
        {
            closeTradeOverlay(false);
        }
    }

    function showIncomingRequest(req)
    {
        if (!requestOverlay || !req)
        {
            return;
        }

        incomingRequest = req;

        if (requestOverlay.hidden === false && requestTextEl)
        {
            requestTextEl.textContent = t('trade.incoming_title', { name: req.other_name || 'Player' });
        }
        else
        {
            requestOverlay.hidden = false;
            requestOverlay.setAttribute('aria-hidden', 'false');

            if (requestTextEl)
            {
                requestTextEl.textContent = t('trade.incoming_title', { name: req.other_name || 'Player' });
            }
        }

        updateRequestTimer(req.seconds_left);
    }

    function updateRequestTimer(secondsLeft)
    {
        if (!requestTimerFill)
        {
            return;
        }

        var pct = Math.max(0, Math.min(100, (secondsLeft / REQUEST_SECONDS) * 100));
        requestTimerFill.style.width = pct + '%';
    }

    function hideIncomingRequest()
    {
        incomingRequest = null;

        if (requestOverlay)
        {
            requestOverlay.hidden = true;
            requestOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    function respondIncoming(accept)
    {
        if (!incomingRequest || !playerRef || busy)
        {
            return;
        }

        var requestId = incomingRequest.id_trade_request;

        busy = true;
        pausePoll();

        AnimasterApi.respondTradeRequest(
            playerRef,
            requestId,
            accept
        ).then(function (result)
        {
            dismissedRequestIds[requestId] = true;
            hideIncomingRequest();

            if (accept && result && result.trade)
            {
                applyTradeState(result.trade);
                openTradeOverlay();
            }
        }).catch(function (err)
        {
            var code = err && err.message ? err.message : 'GENERIC';

            if (code === 'EXPIRED' || code === 'NOT_FOUND')
            {
                dismissedRequestIds[requestId] = true;
                hideIncomingRequest();
            }

            alert(errorText(code));
        }).finally(function ()
        {
            busy = false;
            resumePoll();
            poll(true);
        });
    }

    function applyTradeState(state)
    {
        tradeState = state;
        syncLocalOfferFromState(state);
        renderTradePanel();
    }

    function mergePollTradeState(state)
    {
        if (!state)
        {
            return;
        }

        var wasConfirmed = tradeState && tradeState.my_confirmed;
        var preserveLocal = isLocalOfferEditActive();

        if (!tradeState || tradeState.id_trade !== state.id_trade)
        {
            applyTradeState(state);
            return;
        }

        tradeState = state;

        if (state.my_confirmed || wasConfirmed)
        {
            syncLocalOfferFromState(state);
        }
        else if (!preserveLocal)
        {
            syncLocalOfferFromState(state);
        }

        renderTradePanel();
    }

    function openTradeOverlay()
    {
        if (!overlay || !tradeState)
        {
            return;
        }

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        renderTradePanel();
    }

    function closeTradeOverlay(silent)
    {
        tradeState = null;
        localGold = 0;
        localItems = [];
        offerDirty = false;
        savingOffer = false;

        if (saveTimer)
        {
            clearTimeout(saveTimer);
            saveTimer = null;
        }

        closePickItems();

        if (overlay)
        {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }

        if (!silent && typeof AnimasterNotifications !== 'undefined')
        {
            AnimasterNotifications.showLocal(t('trade.cancelled'), 'trade', '');
        }
    }

    function renderTradePanel()
    {
        if (!tradeState || !overlay)
        {
            return;
        }

        var partner = tradeState.partner_name || 'Player';
        var locked = !!tradeState.my_confirmed;
        var preserveMyOffer = isLocalOfferEditActive();

        if (titleEl)
        {
            titleEl.textContent = t('trade.title', { name: partner });
        }

        if (myLabelEl)
        {
            myLabelEl.textContent = t('trade.your_offer');
        }

        if (theirLabelEl)
        {
            theirLabelEl.textContent = t('trade.their_offer', { name: partner });
        }

        if (myGoldBalanceEl)
        {
            myGoldBalanceEl.textContent = t('trade.gold_balance', { gold: tradeState.my_gold || 0 });
        }

        if (myGoldInput)
        {
            if (!preserveMyOffer || document.activeElement !== myGoldInput)
            {
                myGoldInput.value = String(localGold);
            }

            myGoldInput.disabled = locked;
        }

        if (theirGoldOfferEl)
        {
            theirGoldOfferEl.textContent = String(tradeState.their_gold_offer || 0);
        }

        renderItemList(myItemsEl, localItems, !locked);
        renderItemList(theirItemsEl, tradeState.their_items || [], false);

        if (addItemBtn)
        {
            addItemBtn.hidden = locked;
            addItemBtn.disabled = locked;
        }

        if (statusEl)
        {
            if (tradeState.my_confirmed && !tradeState.their_confirmed)
            {
                statusEl.textContent = t('trade.confirmed');
            }
            else if (tradeState.their_confirmed && !tradeState.my_confirmed)
            {
                statusEl.textContent = t('trade.partner_confirmed');
            }
            else
            {
                statusEl.textContent = '';
            }
        }

        if (confirmBtn)
        {
            confirmBtn.textContent = t('trade.confirm');
            confirmBtn.disabled = locked || busy;
            confirmBtn.hidden = locked;
        }

        if (cancelBtn)
        {
            cancelBtn.textContent = t('trade.cancel');
        }
    }

    function renderItemList(container, items, editable)
    {
        if (!container)
        {
            return;
        }

        container.innerHTML = '';

        if (!items.length)
        {
            var empty = document.createElement('p');
            empty.className = 'trade-items-empty';
            empty.textContent = t('trade.no_items');
            container.appendChild(empty);
            return;
        }

        items.forEach(function (item)
        {
            var row = document.createElement('div');
            row.className = 'trade-item-row';

            var name = document.createElement('span');
            name.className = 'trade-item-name';
            name.textContent = (item.nome || ('#' + item.id_item_type)) + ' ×' + item.quantity;
            row.appendChild(name);

            if (editable)
            {
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'trade-item-remove';
                removeBtn.textContent = t('trade.remove_item');
                removeBtn.addEventListener('click', function ()
                {
                    removeLocalItem(item.id_item_type);
                });
                row.appendChild(removeBtn);
            }

            container.appendChild(row);
        });
    }

    function removeLocalItem(idItemType)
    {
        offerDirty = true;
        localItems = localItems.filter(function (row)
        {
            return row.id_item_type !== idItemType;
        });
        renderTradePanel();
        scheduleSaveOffer();
    }

    function scheduleSaveOffer()
    {
        if (!tradeState || tradeState.my_confirmed)
        {
            return;
        }

        offerDirty = true;
        localGold = parseInt(myGoldInput ? myGoldInput.value : '0', 10) || 0;

        if (saveTimer)
        {
            clearTimeout(saveTimer);
        }

        saveTimer = setTimeout(saveOffer, SAVE_DEBOUNCE_MS);
    }

    function saveOffer()
    {
        if (!tradeState || !playerRef || busy || tradeState.my_confirmed)
        {
            return;
        }

        busy = true;
        savingOffer = true;

        AnimasterApi.updateTradeOffer(playerRef, tradeState.id_trade, localGold, localItems).then(function (result)
        {
            if (result && result.trade)
            {
                tradeState = result.trade;
                syncLocalOfferFromState(tradeState);
                renderTradePanel();
            }
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
            savingOffer = false;
        });
    }

    function isItemStackable(item)
    {
        return String(item.flg_stackable || '') === 'S';
    }

    function maxOfferQuantity(item)
    {
        var bagQty = parseInt(item.quantita, 10) || 0;
        var stackLimit = parseInt(item.stack_limit, 10);

        if (isItemStackable(item) && stackLimit > 0)
        {
            return Math.min(bagQty, stackLimit);
        }

        return bagQty > 0 ? bagQty : 1;
    }

    function existingOfferQuantity(idItemType)
    {
        var i;

        for (i = 0; i < localItems.length; i++)
        {
            if (localItems[i].id_item_type === idItemType)
            {
                return localItems[i].quantity;
            }
        }

        return 0;
    }

    function hidePickQuantity()
    {
        pickQuantityItem = null;

        if (pickQtyPanel)
        {
            pickQtyPanel.hidden = true;
        }

        if (pickListEl)
        {
            pickListEl.hidden = false;
        }
    }

    function showPickQuantity(item)
    {
        if (!pickQtyPanel || !pickQtyInput || !item)
        {
            addLocalItem(item, 1);
            return;
        }

        var maxQty = maxOfferQuantity(item);

        if (maxQty <= 1)
        {
            addLocalItem(item, 1);
            return;
        }

        pickQuantityItem = item;

        if (pickListEl)
        {
            pickListEl.hidden = true;
        }

        if (pickQtyNameEl)
        {
            pickQtyNameEl.textContent = item.nome || ('#' + item.id_item_type);
        }

        pickQtyInput.min = '1';
        pickQtyInput.max = String(maxQty);
        pickQtyInput.value = String(Math.min(maxQty, Math.max(1, existingOfferQuantity(item.id_item_type) || 1)));

        if (pickQtyHintEl)
        {
            pickQtyHintEl.textContent = t('trade.pick_quantity_hint', { max: maxQty });
        }

        pickQtyPanel.hidden = false;
        pickQtyInput.focus();
        pickQtyInput.select();
    }

    function confirmPickQuantity()
    {
        if (!pickQuantityItem || !pickQtyInput)
        {
            return;
        }

        var maxQty = maxOfferQuantity(pickQuantityItem);
        var qty = parseInt(pickQtyInput.value, 10) || 1;

        qty = Math.min(maxQty, Math.max(1, qty));
        addLocalItem(pickQuantityItem, qty);
    }

    function onPickItemClick(item)
    {
        if (isItemStackable(item) && maxOfferQuantity(item) > 1)
        {
            showPickQuantity(item);
            return;
        }

        addLocalItem(item, 1);
    }

    function openPickItems()
    {
        if (!pickOverlay || !playerRef || tradeState && tradeState.my_confirmed)
        {
            return;
        }

        pickOverlay.hidden = false;
        pickOverlay.setAttribute('aria-hidden', 'false');
        pickListEl.innerHTML = '';
        hidePickQuantity();

        if (pickListEl)
        {
            pickListEl.hidden = false;
        }

        AnimasterApi.getInventory(playerRef, false).then(function (rows)
        {
            bagItems = (rows || []).filter(function (item)
            {
                return String(item.flg_tradable || '') === 'S' && parseInt(item.quantita, 10) > 0;
            });

            if (!bagItems.length)
            {
                var empty = document.createElement('p');
                empty.className = 'trade-pick-empty';
                empty.textContent = t('trade.pick_item_empty');
                pickListEl.appendChild(empty);
                return;
            }

            bagItems.forEach(function (item)
            {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'trade-pick-row';
                btn.textContent = (item.nome || ('#' + item.id_item_type)) + ' (' + item.quantita + ')';
                btn.addEventListener('click', function ()
                {
                    onPickItemClick(item);
                });
                pickListEl.appendChild(btn);
            });
        }).catch(function ()
        {
            pickListEl.textContent = t('inventory.load_failed');
        });
    }

    function closePickItems()
    {
        hidePickQuantity();

        if (pickOverlay)
        {
            pickOverlay.hidden = true;
            pickOverlay.setAttribute('aria-hidden', 'true');
        }
    }

    function addLocalItem(item, quantity)
    {
        offerDirty = true;
        var idType = parseInt(item.id_item_type, 10);
        var maxQty = maxOfferQuantity(item);
        var qty = parseInt(quantity, 10) || 1;
        var existing = null;
        var i;

        qty = Math.min(maxQty, Math.max(1, qty));

        for (i = 0; i < localItems.length; i++)
        {
            if (localItems[i].id_item_type === idType)
            {
                existing = localItems[i];
                break;
            }
        }

        if (existing)
        {
            existing.quantity = qty;
            existing.nome = item.nome || existing.nome;
        }
        else
        {
            localItems.push({
                id_item_type: idType,
                quantity: qty,
                nome: item.nome
            });
        }

        closePickItems();
        renderTradePanel();
        scheduleSaveOffer();
    }

    function onConfirmTrade()
    {
        if (!tradeState || !playerRef || busy)
        {
            return;
        }

        busy = true;
        saveOfferSync().then(function ()
        {
            return AnimasterApi.confirmTrade(playerRef, tradeState.id_trade);
        }).then(function (result)
        {
            if (result && result.completed)
            {
                closeTradeOverlay(true);

                if (typeof AnimasterNotifications !== 'undefined')
                {
                    AnimasterNotifications.showLocal(t('trade.completed'), 'trade', '');
                }

                if (typeof AnimasterInventory !== 'undefined' && AnimasterInventory.isOpen())
                {
                    AnimasterInventory.refresh();
                }

                poll();
                return;
            }

            if (result && result.trade)
            {
                applyTradeState(result.trade);
            }

            poll();
        }).catch(function (err)
        {
            alert(errorText(err && err.message ? err.message : 'GENERIC'));
        }).finally(function ()
        {
            busy = false;
            renderTradePanel();
        });
    }

    function saveOfferSync()
    {
        if (!tradeState || tradeState.my_confirmed)
        {
            return Promise.resolve();
        }

        localGold = parseInt(myGoldInput ? myGoldInput.value : '0', 10) || 0;
        savingOffer = true;

        return AnimasterApi.updateTradeOffer(playerRef, tradeState.id_trade, localGold, localItems).then(function (result)
        {
            if (result && result.trade)
            {
                tradeState = result.trade;
                syncLocalOfferFromState(tradeState);
            }
        }).finally(function ()
        {
            savingOffer = false;
        });
    }

    function onCancelTrade()
    {
        if (!tradeState || !playerRef || busy)
        {
            closeTradeOverlay(false);
            return;
        }

        busy = true;

        AnimasterApi.cancelTrade(playerRef, tradeState.id_trade).finally(function ()
        {
            busy = false;
            closeTradeOverlay(false);
            poll();
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        requestToPlayer: requestToPlayer,
        isTradeOpen: isTradeOpen,
        poll: poll
    };
})();
