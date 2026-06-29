/**
 * Character select / create — card picker UI.
 */
(function ()
{
    var picker = document.querySelector('.character-picker');

    if (!picker)
    {
        return;
    }

    var cards = picker.querySelectorAll('.character-slot-card');
    var playPanel = document.getElementById('character-play-panel');
    var createPanel = document.getElementById('character-create-panel');
    var selectInput = document.getElementById('select-id-user-ig');
    var defaultMode = picker.getAttribute('data-default-mode') || 'character';
    var defaultId = picker.getAttribute('data-default-id') || '';

    function selectCard(card)
    {
        if (!card)
        {
            return;
        }

        cards.forEach(function (c)
        {
            c.classList.remove('is-selected');
            c.setAttribute('aria-selected', 'false');
        });

        card.classList.add('is-selected');
        card.setAttribute('aria-selected', 'true');

        var mode = card.getAttribute('data-mode') || 'character';

        if (mode === 'new')
        {
            if (playPanel)
            {
                playPanel.hidden = true;
            }

            if (createPanel)
            {
                createPanel.hidden = false;
            }
        }
        else
        {
            if (playPanel)
            {
                playPanel.hidden = false;
            }

            if (createPanel)
            {
                createPanel.hidden = true;
            }

            if (selectInput)
            {
                selectInput.value = card.getAttribute('data-id-user-ig') || '';
            }
        }
    }

    cards.forEach(function (card)
    {
        card.addEventListener('click', function ()
        {
            selectCard(card);
        });
    });

    var initialCard = null;

    cards.forEach(function (card)
    {
        if (defaultMode === 'new' && card.getAttribute('data-mode') === 'new')
        {
            initialCard = card;
        }
        else if (
            defaultMode === 'character'
            && card.getAttribute('data-id-user-ig') === defaultId
        )
        {
            initialCard = card;
        }
    });

    if (!initialCard && cards.length > 0)
    {
        initialCard = cards[0];
    }

    selectCard(initialCard);
})();
