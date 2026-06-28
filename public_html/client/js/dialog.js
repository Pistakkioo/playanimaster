/**
 * NPC conversation UI (web port of ConversationManager / Dialog.cs flow).
 */
var AnimasterDialog = (function ()
{
    var overlay = null;
    var bubble = null;
    var talkBtn = null;
    var npcNameEl = null;
    var titleEl = null;
    var textEl = null;
    var optionsEl = null;
    var nextBtn = null;

    var active = false;
    var activeNpc = null;
    var playerRef = null;
    var conversations = [];
    var queue = [];
    var index = 0;
    var selectedOptionId = 0;
    var pickingConversation = false;
    var onCloseCallback = null;

    function t(tag, vars)
    {
        if (typeof AnimasterLang !== 'undefined')
        {
            return AnimasterLang.t(tag, vars);
        }

        return tag;
    }

    function parseOptionsString(str)
    {
        if (!str)
        {
            return [];
        }

        return str.split('[SPLITTER.O]').filter(Boolean).map(function (chunk)
        {
            return JSON.parse(chunk);
        });
    }

    function parseNpcConversations(npc)
    {
        if (!npc || !npc.dialogues)
        {
            return [];
        }

        var lines = npc.dialogues.split('[SPLITTER.D]').filter(Boolean).map(function (chunk)
        {
            return JSON.parse(chunk);
        });

        lines.sort(function (a, b)
        {
            return parseInt(a.order, 10) - parseInt(b.order, 10);
        });

        var map = {};
        var list = [];

        lines.forEach(function (dj)
        {
            var id = String(dj.id_conversation);

            if (!map[id])
            {
                map[id] = {
                    id_conversation: parseInt(dj.id_conversation, 10),
                    id_npc: parseInt(dj.id_npc, 10),
                    title: dj.title,
                    flg_register: dj.flg_register,
                    dialogs: []
                };
                list.push(map[id]);
            }

            map[id].dialogs.push(dj);
        });

        return list;
    }

    function optionColorCss(colorName)
    {
        var map = {
            green: '#2ecc71',
            red: '#e74c3c',
            blue: '#3498db',
            yellow: '#f1c40f'
        };

        return map[String(colorName || '').toLowerCase()] || '#3498db';
    }

    function isFormFieldFocused()
    {
        var tag = document.activeElement && document.activeElement.tagName;

        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    }

    function isDialogOverlayActive()
    {
        return active && overlay && !overlay.hidden;
    }

    function getOptionButtons()
    {
        if (!optionsEl)
        {
            return [];
        }

        return Array.prototype.slice.call(optionsEl.querySelectorAll('.dialog-option'));
    }

    function hasVisibleNext()
    {
        return !!(nextBtn && !nextBtn.hidden && !nextBtn.disabled);
    }

    function formatChoiceLabel(num, text)
    {
        return String(num) + '. ' + text;
    }

    function keyCodeToChoiceNum(code)
    {
        var match = String(code || '').match(/^(?:Digit|Numpad)([1-9])$/);

        return match ? parseInt(match[1], 10) : 0;
    }

    function appendChoiceButton(num, className, label, borderColor, onClick, extraDataset)
    {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = className;
        btn.dataset.choiceNum = String(num);
        btn.textContent = formatChoiceLabel(num, label);

        if (borderColor)
        {
            btn.style.borderColor = borderColor;
        }

        if (extraDataset && typeof extraDataset === 'object')
        {
            Object.keys(extraDataset).forEach(function (key)
            {
                btn.dataset[key] = extraDataset[key];
            });
        }

        btn.addEventListener('click', onClick);
        optionsEl.appendChild(btn);

        return btn;
    }

    function handleDialogKeydown(e)
    {
        var optionButtons = getOptionButtons();

        if (optionButtons.length > 0)
        {
            var num = keyCodeToChoiceNum(e.code);

            if (num >= 1 && num <= optionButtons.length)
            {
                e.preventDefault();
                optionButtons[num - 1].click();
                return true;
            }

            return false;
        }

        if (hasVisibleNext() && (e.code === 'Space' || e.code === 'Enter'))
        {
            e.preventDefault();
            advance();
            return true;
        }

        return false;
    }

    function init(options)
    {
        overlay = document.getElementById('dialog-overlay');
        bubble = document.getElementById('npc-talk-bubble');
        talkBtn = document.getElementById('npc-talk-btn');
        npcNameEl = document.getElementById('dialog-npc-name');
        titleEl = document.getElementById('dialog-title');
        textEl = document.getElementById('dialog-text');
        optionsEl = document.getElementById('dialog-options');
        nextBtn = document.getElementById('dialog-next');

        onCloseCallback = options && options.onClose ? options.onClose : null;

        if (talkBtn)
        {
            talkBtn.addEventListener('click', function (event)
            {
                event.preventDefault();
                event.stopPropagation();
                triggerTalkFromBubble();
            });
        }

        document.addEventListener('keydown', function (e)
        {
            if (isFormFieldFocused())
            {
                return;
            }

            if (isDialogOverlayActive())
            {
                if (handleDialogKeydown(e))
                {
                    return;
                }

                if (e.code === 'Space')
                {
                    e.preventDefault();
                }

                return;
            }

            if (e.code !== 'Space' || e.ctrlKey || e.metaKey || e.altKey)
            {
                return;
            }

            if (!isTalkBubbleVisible())
            {
                return;
            }

            e.preventDefault();
            triggerTalkFromBubble();
        });

        if (nextBtn)
        {
            nextBtn.addEventListener('click', advance);
        }
    }

    function updateTalkBubble(nearbyNpc, screenPos)
    {
        if (!bubble || active)
        {
            if (bubble)
            {
                bubble.hidden = true;
            }

            return;
        }

        if (!nearbyNpc || !screenPos)
        {
            bubble.hidden = true;
            activeNpc = null;
            return;
        }

        activeNpc = nearbyNpc;
        bubble.hidden = false;
        bubble.style.left = screenPos.x + 'px';
        bubble.style.top = screenPos.y + 'px';

        if (talkBtn)
        {
            talkBtn.textContent = t('dialog.talk_button');
            talkBtn.title = nearbyNpc.npc || t('dialog.npc_fallback');
        }
    }

    function isTalkBubbleVisible()
    {
        return !!(bubble && !bubble.hidden && activeNpc && !active);
    }

    function triggerTalkFromBubble()
    {
        if (!activeNpc)
        {
            return;
        }

        var player = playerRef;

        if (!player && typeof AnimasterWorld !== 'undefined')
        {
            player = AnimasterWorld.getPlayer();
        }

        open(activeNpc, player);
    }

    function open(npc, player)
    {
        if (!overlay || !npc || !player)
        {
            return;
        }

        playerRef = player;
        activeNpc = npc;
        conversations = parseNpcConversations(npc);

        if (!conversations.length)
        {
            return;
        }

        selectedOptionId = 0;
        active = true;
        pickingConversation = false;

        bubble.hidden = true;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');

        if (npcNameEl)
        {
            npcNameEl.textContent = npc.npc || t('dialog.npc_fallback');
        }

        if (conversations.length === 1)
        {
            startConversation(conversations[0]);
        }
        else
        {
            showConversationPicker();
        }
    }

    function showConversationPicker()
    {
        pickingConversation = true;
        queue = [];
        index = 0;
        selectedOptionId = 0;

        if (titleEl)
        {
            titleEl.textContent = t('dialog.choose_topic');
        }

        if (textEl)
        {
            textEl.textContent = t('dialog.choose_topic_prompt');
        }

        if (optionsEl)
        {
            optionsEl.innerHTML = '';

            conversations.forEach(function (conversation, i)
            {
                appendChoiceButton(
                    i + 1,
                    'dialog-option dialog-conversation-pick',
                    conversation.title || t('dialog.conversation_fallback', { id: conversation.id_conversation }),
                    null,
                    function ()
                    {
                        startConversation(conversation);
                    }
                );
            });
        }

        if (nextBtn)
        {
            nextBtn.hidden = true;
        }
    }

    function startConversation(conversation)
    {
        if (!conversation || !conversation.dialogs || !conversation.dialogs.length)
        {
            close();
            return;
        }

        pickingConversation = false;
        queue = conversation.dialogs.slice();
        index = 0;
        selectedOptionId = 0;

        if (titleEl)
        {
            titleEl.textContent = conversation.title || '';
        }

        showCurrent();
    }

    function showCurrent()
    {
        var dj = queue[index];

        if (!dj)
        {
            close();
            return;
        }

        if (textEl)
        {
            textEl.textContent = dj.dialog || '';
        }

        if (optionsEl)
        {
            optionsEl.innerHTML = '';
        }

        selectedOptionId = 0;

        if (dj.flg_options === 'S')
        {
            var options = parseOptionsString(dj.dialogOptionsStringone);

            options.forEach(function (opt, i)
            {
                appendChoiceButton(
                    i + 1,
                    'dialog-option',
                    opt.option_text || t('dialog.option_fallback', { id: opt.id_option }),
                    optionColorCss(opt.option_color),
                    function ()
                    {
                        selectOption(parseInt(opt.id_option, 10), dj);
                    },
                    { optionId: String(opt.id_option) }
                );
            });

            if (nextBtn)
            {
                nextBtn.hidden = true;
            }
        }
        else if (nextBtn)
        {
            nextBtn.hidden = false;
            nextBtn.disabled = false;
        }
    }

    function selectOption(optionId, dj)
    {
        selectedOptionId = optionId;

        var buttons = optionsEl ? optionsEl.querySelectorAll('.dialog-option') : [];

        buttons.forEach(function (btn)
        {
            var isSelected = parseInt(btn.dataset.optionId, 10) === optionId;
            btn.classList.toggle('selected', isSelected);
        });

        if (dj.flg_last === 'S')
        {
            finishConversation(dj);
        }
        else
        {
            advance();
        }
    }

    function advance()
    {
        var dj = queue[index];

        if (!dj)
        {
            close();
            return;
        }

        if (dj.flg_last === 'S')
        {
            finishConversation(dj);
            return;
        }

        index += 1;
        showCurrent();
    }

    function finishConversation(dj)
    {
        var idConversation = parseInt(dj.id_conversation, 10);
        var idOption = selectedOptionId || 0;
        var flgRegister = dj.flg_register === 'S';

        close();

        if (!playerRef)
        {
            return;
        }

        var chain = Promise.resolve(null);

        if (flgRegister || idOption > 0)
        {
            chain = AnimasterApi.getConversationConsequences(playerRef, idConversation, idOption).catch(function (err)
            {
                console.warn('[AnimasterDialog] consequences failed:', err && err.message ? err.message : err);
                return null;
            });
        }

        chain.then(function (envelope)
        {
            if (onCloseCallback)
            {
                onCloseCallback(envelope || null);
            }
        });
    }

    function close()
    {
        active = false;
        pickingConversation = false;
        queue = [];
        index = 0;
        selectedOptionId = 0;
        conversations = [];

        if (overlay)
        {
            overlay.hidden = true;
            overlay.setAttribute('aria-hidden', 'true');
        }

        if (optionsEl)
        {
            optionsEl.innerHTML = '';
        }
    }

    function isActive()
    {
        return active;
    }

    function setPlayer(player)
    {
        playerRef = player;
    }

    return {
        init: init,
        setPlayer: setPlayer,
        parseNpcConversations: parseNpcConversations,
        updateTalkBubble: updateTalkBubble,
        open: open,
        close: close,
        isActive: isActive,
        isTalkBubbleVisible: isTalkBubbleVisible,
        triggerTalkFromBubble: triggerTalkFromBubble
    };
})();
