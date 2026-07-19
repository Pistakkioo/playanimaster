/**
 * NPC vendor shop: buy/sell panel opened via the [open shop] dialog
 * consequence. Structural twin of AnimasterInventory / the trade overlay.
 */
var AnimasterShop = (function ()
{
    var overlay = null;
    var titleEl = null;
    var closeBtn = null;
    var goldEl = null;
    var tabBuyBtn = null;
    var tabSellBtn = null;
    var listBuyEl = null;
    var listSellEl = null;
    var statusEl = null;

    var playerRef = null;
    var idShop = 0;
    var activeTab = 'buy';
    var buyItems = [];
    var sellItems = [];
    var gold = 0;
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
        overlay = document.getElementById('shop-overlay');
        titleEl = document.getElementById('shop-title');
        closeBtn = document.getElementById('shop-close');
        goldEl = document.getElementById('shop-gold-balance');
        tabBuyBtn = document.getElementById('shop-tab-buy');
        tabSellBtn = document.getElementById('shop-tab-sell');
        listBuyEl = document.getElementById('shop-list-buy');
        listSellEl = document.getElementById('shop-list-sell');
        statusEl = document.getElementById('shop-status');

        if (!overlay)
        {
            return;
        }

        if (closeBtn)
        {
            closeBtn.addEventListener('click', close);
        }

        if (tabBuyBtn)
        {
            tabBuyBtn.addEventListener('click', function ()
            {
                setActiveTab('buy');
            });
        }

        if (tabSellBtn)
        {
            tabSellBtn.addEventListener('click', function ()
            {
                setActiveTab('sell');
            });
        }

        document.addEventListener('keydown', function (e)
        {
            if (e.code === 'Escape' && isOpen())
            {
                close();
            }
        });
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    function isOpen()
    {
        return !!(overlay && !overlay.hidden);
    }

    function open(shopId, player)
    {
        if (!overlay)
        {
            return;
        }

        if (player)
        {
            playerRef = player;
        }

        idShop = parseInt(shopId, 10) || 0;

        if (idShop <= 0 || !playerRef)
        {
            return;
        }

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        setActiveTab('buy');
        load();
    }

    function close()
    {
        if (overlay)
        {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }

        idShop = 0;
        buyItems = [];
        sellItems = [];
    }

    function setStatus(text, isError)
    {
        if (!statusEl)
        {
            return;
        }

        statusEl.textContent = text || '';
        statusEl.classList.toggle('error', !!isError);
    }

    function setActiveTab(tab)
    {
        activeTab = tab;

        if (tabBuyBtn)
        {
            tabBuyBtn.classList.toggle('is-active', tab === 'buy');
        }

        if (tabSellBtn)
        {
            tabSellBtn.classList.toggle('is-active', tab === 'sell');
        }

        if (listBuyEl)
        {
            listBuyEl.hidden = tab !== 'buy';
        }

        if (listSellEl)
        {
            listSellEl.hidden = tab !== 'sell';
        }

        setStatus('');
    }

    function errorText(code)
    {
        var key = String(code || '').toUpperCase();
        var map = {
            SHOP_NOT_FOUND: 'shop.error_not_found',
            SHOP_DOES_NOT_BUY: 'shop.error_does_not_buy',
            ITEM_NOT_IN_SHOP: 'shop.error_not_available',
            ITEM_NOT_BUYABLE: 'shop.error_not_available',
            ITEM_NOT_SELLABLE: 'shop.error_not_sellable',
            OUT_OF_STOCK: 'shop.error_out_of_stock',
            INSUFFICIENT_GOLD: 'shop.error_insufficient_gold',
            INSUFFICIENT_ITEMS: 'shop.error_insufficient_items',
            INVALID_QUANTITY: 'shop.error_invalid_quantity'
        };

        return t(map[key] || 'shop.error_generic');
    }

    function load()
    {
        if (!playerRef || idShop <= 0)
        {
            return;
        }

        setStatus(t('ui.loading'));

        AnimasterApi.getShop(playerRef, idShop).then(function (data)
        {
            gold = (data && data.gold) || 0;
            buyItems = (data && data.items) || [];

            if (titleEl)
            {
                var shopName = (data && data.shop && data.shop.name) || '';
                titleEl.textContent = t('shop.title', { name: shopName });
            }

            if (tabSellBtn)
            {
                var buysFromPlayer = !!(data && data.shop && data.shop.flg_buys_from_player === 'S');
                tabSellBtn.hidden = !buysFromPlayer;
            }

            renderGold();
            renderBuyList();
            setStatus('');

            return loadSellItems();
        }).catch(function (err)
        {
            setStatus(err && err.message ? errorText(err.message) : t('shop.load_failed'), true);
        });
    }

    function loadSellItems()
    {
        if (!playerRef)
        {
            return Promise.resolve();
        }

        return AnimasterApi.getInventory(playerRef, false).then(function (rows)
        {
            sellItems = (rows || []).filter(function (item)
            {
                return String(item.flg_sellable || '') === 'S' && parseInt(item.sell_price, 10) > 0;
            });

            renderSellList();
        }).catch(function ()
        {
            sellItems = [];
            renderSellList();
        });
    }

    function renderGold()
    {
        if (goldEl)
        {
            goldEl.textContent = t('shop.gold_balance', { gold: gold });
        }
    }

    function renderBuyList()
    {
        if (!listBuyEl)
        {
            return;
        }

        listBuyEl.innerHTML = '';

        if (!buyItems.length)
        {
            var empty = document.createElement('p');
            empty.className = 'shop-list-empty';
            empty.textContent = t('shop.empty_buy');
            listBuyEl.appendChild(empty);
            return;
        }

        buyItems.forEach(function (item)
        {
            listBuyEl.appendChild(buildRow({
                name: item.nome || ('#' + item.id_item_type),
                priceLabel: t('shop.price_label', { price: item.price || 0 }),
                stockLabel: item.stock_qty !== null && item.stock_qty !== undefined
                    ? t('shop.stock_label', { stock: item.stock_qty })
                    : '',
                maxQty: item.stock_qty !== null && item.stock_qty !== undefined ? item.stock_qty : 999,
                disabled: !item.price || item.price <= 0 || (item.stock_qty !== null && item.stock_qty !== undefined && item.stock_qty <= 0),
                buttonLabel: t('shop.buy_button'),
                onAction: function (qty)
                {
                    doBuy(item, qty);
                }
            }));
        });
    }

    function renderSellList()
    {
        if (!listSellEl)
        {
            return;
        }

        listSellEl.innerHTML = '';

        if (!sellItems.length)
        {
            var empty = document.createElement('p');
            empty.className = 'shop-list-empty';
            empty.textContent = t('shop.empty_sell');
            listSellEl.appendChild(empty);
            return;
        }

        sellItems.forEach(function (item)
        {
            var owned = parseInt(item.quantita, 10) || 0;

            listSellEl.appendChild(buildRow({
                name: item.nome || ('#' + item.id_item_type),
                priceLabel: t('shop.sell_price_label', { price: item.sell_price || 0 }),
                stockLabel: t('shop.owned_label', { qty: owned }),
                maxQty: owned,
                disabled: owned <= 0,
                buttonLabel: t('shop.sell_button'),
                onAction: function (qty)
                {
                    doSell(item, qty);
                }
            }));
        });
    }

    function buildRow(cfg)
    {
        var row = document.createElement('div');
        row.className = 'shop-item-row';

        var info = document.createElement('div');
        info.className = 'shop-item-info';

        var name = document.createElement('span');
        name.className = 'shop-item-name';
        name.textContent = cfg.name;
        info.appendChild(name);

        var price = document.createElement('span');
        price.className = 'shop-item-price';
        price.textContent = cfg.priceLabel;
        info.appendChild(price);

        if (cfg.stockLabel)
        {
            var stock = document.createElement('span');
            stock.className = 'shop-item-stock';
            stock.textContent = cfg.stockLabel;
            info.appendChild(stock);
        }

        row.appendChild(info);

        var actions = document.createElement('div');
        actions.className = 'shop-item-actions';

        var qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'shop-item-qty';
        qtyInput.min = '1';
        qtyInput.max = String(Math.max(1, cfg.maxQty || 1));
        qtyInput.value = '1';
        qtyInput.disabled = !!cfg.disabled;
        actions.appendChild(qtyInput);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'shop-item-action-btn';
        btn.textContent = cfg.buttonLabel;
        btn.disabled = !!cfg.disabled || busy;
        btn.addEventListener('click', function ()
        {
            var qty = Math.max(1, Math.min(cfg.maxQty || 1, parseInt(qtyInput.value, 10) || 1));
            cfg.onAction(qty);
        });
        actions.appendChild(btn);

        row.appendChild(actions);

        return row;
    }

    function doBuy(item, quantity)
    {
        if (!playerRef || idShop <= 0 || busy)
        {
            return;
        }

        busy = true;
        setStatus(t('ui.loading'));

        AnimasterApi.shopBuy(playerRef, idShop, item.id_item_type, quantity).then(function (result)
        {
            gold = (result && result.gold) || gold;
            renderGold();
            setStatus(t('shop.buy_success', {
                qty: (result && result.quantity) || quantity,
                name: item.nome || ('#' + item.id_item_type),
                gold: (result && result.total_gold) || 0
            }));

            return load();
        }).catch(function (err)
        {
            setStatus(errorText(err && err.message), true);
        }).finally(function ()
        {
            busy = false;
        });
    }

    function doSell(item, quantity)
    {
        if (!playerRef || idShop <= 0 || busy)
        {
            return;
        }

        busy = true;
        setStatus(t('ui.loading'));

        AnimasterApi.shopSell(playerRef, idShop, item.id_item_type, quantity).then(function (result)
        {
            gold = (result && result.gold) || gold;
            renderGold();
            setStatus(t('shop.sell_success', {
                qty: (result && result.quantity) || quantity,
                name: item.nome || ('#' + item.id_item_type),
                gold: (result && result.total_gold) || 0
            }));

            if (typeof AnimasterInventory !== 'undefined' && AnimasterInventory.isOpen())
            {
                AnimasterInventory.refresh();
            }

            return load();
        }).catch(function (err)
        {
            setStatus(errorText(err && err.message), true);
        }).finally(function ()
        {
            busy = false;
        });
    }

    return {
        init: init,
        setPlayer: setPlayer,
        open: open,
        close: close,
        isOpen: isOpen
    };
})();
