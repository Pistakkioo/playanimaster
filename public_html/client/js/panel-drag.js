/**
 * Drag floating panels within a bounds element (header acts as handle).
 */
var AnimasterPanelDrag = (function ()
{
    function attach(panel, handle, boundsEl)
    {
        if (!panel || !handle || !boundsEl)
        {
            return;
        }

        var drag = {
            active: false,
            offsetX: 0,
            offsetY: 0
        };

        handle.addEventListener('mousedown', function (e)
        {
            if (e.button !== 0)
            {
                return;
            }

            if (e.target.closest('button, input, textarea, select, a, label'))
            {
                return;
            }

            e.preventDefault();

            panel.classList.add('side-panel-dragged');

            var boundsRect = boundsEl.getBoundingClientRect();
            var rect = panel.getBoundingClientRect();

            drag.active = true;
            drag.offsetX = e.clientX - rect.left;
            drag.offsetY = e.clientY - rect.top;

            var onMove = function (ev)
            {
                if (!drag.active)
                {
                    return;
                }

                var left = ev.clientX - boundsRect.left - drag.offsetX;
                var top = ev.clientY - boundsRect.top - drag.offsetY;
                var maxLeft = Math.max(0, boundsRect.width - panel.offsetWidth);
                var maxTop = Math.max(0, boundsRect.height - panel.offsetHeight);

                panel.style.left = Math.min(maxLeft, Math.max(0, left)) + 'px';
                panel.style.top = Math.min(maxTop, Math.max(0, top)) + 'px';
                panel.style.right = 'auto';
                panel.style.bottom = 'auto';
            };

            var onUp = function ()
            {
                drag.active = false;
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        });
    }

    return {
        attach: attach
    };
})();
