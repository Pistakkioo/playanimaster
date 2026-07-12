/**
 * Always-visible local player status HUD (lead animal HP, buffs).
 */
var AnimasterSelfHud = (function ()
{
    var hudEl = null;
    var memberState = null;

    function init()
    {
        hudEl = document.getElementById('self-hud');
    }

    function update(member)
    {
        memberState = member || null;

        if (!hudEl)
        {
            return;
        }

        hudEl.innerHTML = '';

        if (!memberState)
        {
            hudEl.hidden = true;
            hudEl.setAttribute('aria-hidden', 'true');
            return;
        }

        if (typeof AnimasterParty === 'undefined' || typeof AnimasterParty.buildHudMemberCard !== 'function')
        {
            hudEl.hidden = true;
            hudEl.setAttribute('aria-hidden', 'true');
            return;
        }

        var card = AnimasterParty.buildHudMemberCard(memberState, {
            isSelf: true,
            hideFarIcon: true
        });

        if (!card)
        {
            hudEl.hidden = true;
            hudEl.setAttribute('aria-hidden', 'true');
            return;
        }

        hudEl.appendChild(card);
        hudEl.hidden = false;
        hudEl.setAttribute('aria-hidden', 'false');
    }

    return {
        init: init,
        update: update
    };
})();
