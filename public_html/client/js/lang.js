/**
 * Client UI strings loaded from language_texts (bootstrap.texts).
 */
var AnimasterLang = (function ()
{
    var texts = {};
    var langApi = '_it';

    function init(options)
    {
        options = options || {};

        if (options.texts && typeof options.texts === 'object')
        {
            texts = options.texts;
        }

        if (options.langApi)
        {
            langApi = options.langApi;
        }
    }

    function t(tag, vars)
    {
        var str = texts[tag];

        if (str === undefined || str === null || str === '')
        {
            return tag;
        }

        if (vars && typeof vars === 'object')
        {
            Object.keys(vars).forEach(function (key)
            {
                str = str.split('{' + key + '}').join(String(vars[key]));
            });
        }

        return str;
    }

    function getApiLang()
    {
        return langApi;
    }

    function applyStatic(root)
    {
        var scope = root || document;

        scope.querySelectorAll('[data-i18n]').forEach(function (el)
        {
            el.textContent = t(el.getAttribute('data-i18n'));
        });

        scope.querySelectorAll('[data-i18n-title]').forEach(function (el)
        {
            el.title = t(el.getAttribute('data-i18n-title'));
        });
    }

    return {
        init: init,
        t: t,
        getApiLang: getApiLang,
        applyStatic: applyStatic
    };
})();

if (window.ANIMASTER_BOOTSTRAP)
{
    AnimasterLang.init({
        texts: window.ANIMASTER_BOOTSTRAP.texts,
        langApi: window.ANIMASTER_BOOTSTRAP.langApi
    });

    if (document.body)
    {
        AnimasterLang.applyStatic();
    }
}
