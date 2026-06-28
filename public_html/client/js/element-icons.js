/**
 * Colored element icons (team panel, combat, world labels).
 */
var AnimasterElements = (function ()
{
    var COLORS_BY_ID = {
        1: '#058ef0',
        2: '#f02c05',
        3: '#a0ebf2',
        4: '#9c5313',
        5: '#fce112',
        6: '#fffded',
        7: '#35003d',
        8: '#7a797a'
    };

    function normalizeColor(color)
    {
        var value = String(color || '').trim();

        if (!value)
        {
            return '';
        }

        if (/^#[0-9a-fA-F]{3,8}$/.test(value))
        {
            return value;
        }

        if (/^[0-9a-fA-F]{3,8}$/.test(value))
        {
            return '#' + value;
        }

        return value;
    }

    function resolveColor(data)
    {
        data = data || {};

        var fromApi = normalizeColor(data.element_color || data.color);

        if (fromApi)
        {
            return fromApi;
        }

        var id = parseInt(data.id_element, 10) || 0;

        if (id > 0 && COLORS_BY_ID[id])
        {
            return COLORS_BY_ID[id];
        }

        return '#888888';
    }

    function createIcon(data, sizeClass)
    {
        data = data || {};

        var id = parseInt(data.id_element, 10) || 0;
        var label = String(data.element || '').trim();
        var icon = document.createElement('span');
        var className = 'element-icon';

        if (sizeClass)
        {
            className += ' ' + sizeClass;
        }

        if (id > 0)
        {
            className += ' element-icon--id-' + id;
            icon.dataset.elementId = String(id);
        }

        icon.className = className;
        icon.style.backgroundColor = resolveColor(data);

        if (label)
        {
            icon.title = label;
            icon.setAttribute('aria-label', label);
        }
        else
        {
            icon.setAttribute('aria-hidden', 'true');
        }

        return icon;
    }

    function appendLabel(parent, data, options)
    {
        options = options || {};

        if (!parent)
        {
            return null;
        }

        var row = document.createElement('span');
        var rowClass = 'element-label';

        if (options.className)
        {
            rowClass += ' ' + options.className;
        }

        row.className = rowClass;
        row.appendChild(createIcon(data, options.sizeClass));

        if (options.showLabel !== false && data && data.element)
        {
            var text = document.createElement('span');
            text.className = 'element-label-text';
            text.textContent = data.element;
            row.appendChild(text);
        }

        parent.appendChild(row);

        return row;
    }

    return {
        COLORS_BY_ID: COLORS_BY_ID,
        normalizeColor: normalizeColor,
        resolveColor: resolveColor,
        createIcon: createIcon,
        appendLabel: appendLabel
    };
})();
