/**
 * Shared buff label / multi-stat parsing for team, party HUD, and combat UI.
 */
var AnimasterBuffDisplay = (function ()
{
    function statKeyLabel(statKey)
    {
        return String(statKey || '').split(',').map(function (part)
        {
            return part.trim().toUpperCase();
        }).filter(Boolean).join('/');
    }

    function parseModifierPairs(statKey, modifierValue)
    {
        var keys = String(statKey || '').split(',').map(function (part)
        {
            return part.trim().toLowerCase();
        }).filter(Boolean);
        var values = String(modifierValue || '').split(',').map(function (part)
        {
            return part.trim();
        });
        var pairs = [];
        var i;

        for (i = 0; i < keys.length; i++)
        {
            var raw = values[i];

            if (raw === undefined || raw === '')
            {
                raw = values[0] || '0';
            }

            pairs.push({
                stat_key: keys[i],
                modifier_value: parseFloat(raw) || 0
            });
        }

        return pairs;
    }

    function affectsStat(statKeyField, targetStat)
    {
        var target = String(targetStat || '').toLowerCase();

        return parseModifierPairs(statKeyField, '0').some(function (pair)
        {
            return pair.stat_key === target;
        });
    }

    function badgeText(buff)
    {
        var icon = String(buff && buff.icon ? buff.icon : '').trim();

        if (icon)
        {
            return icon;
        }

        return iconLabel(buff);
    }

    function iconLabel(buff)
    {
        var stat = statKeyLabel(buff && buff.stat_key);
        var isDebuff = buff && (buff.is_debuff === true || buff.is_debuff === 'S');
        var arrow = isDebuff ? '\u25BC' : '\u25B2';

        return stat + arrow;
    }

    function effectLabel(buff)
    {
        if (!buff)
        {
            return '';
        }

        if (buff.total_effect_label)
        {
            return String(buff.total_effect_label);
        }

        if (!buff.stat_key)
        {
            return '';
        }

        return computeStackedEffect([buff]);
    }

    function buffTooltip(buff)
    {
        var parts = [buff.name || buff.buff_code || ''];
        var effect = effectLabel(buff);

        if (effect)
        {
            parts.push(effect);
        }

        if (buff.scope === 'turn' && buff.turns_remaining !== null && buff.turns_remaining !== undefined)
        {
            parts.push(buff.turns_remaining + ' turns');
        }
        else if (buff.scope === 'time' && buff.seconds_remaining !== null && buff.seconds_remaining !== undefined)
        {
            parts.push(Math.max(0, parseInt(buff.seconds_remaining, 10) || 0) + 's');
        }
        else if (buff.seconds_remaining !== null && buff.seconds_remaining !== undefined)
        {
            parts.push(Math.max(0, parseInt(buff.seconds_remaining, 10) || 0) + 's');
        }

        return parts.join(' \u2014 ');
    }

    function normalizeBuffTier(tier)
    {
        var value = parseInt(tier, 10) || 0;

        if (value < 1)
        {
            return 0;
        }

        if (value > 5)
        {
            return 5;
        }

        return value;
    }

    function appendTierStars(iconEl, tier)
    {
        var stars = normalizeBuffTier(tier);

        if (!stars)
        {
            return;
        }

        var tierEl = document.createElement('span');
        var glyph = '\u2605';
        var i;

        tierEl.className = 'combat-buff-tier-stars';
        tierEl.setAttribute('aria-hidden', 'true');

        for (i = 0; i < stars; i++)
        {
            tierEl.appendChild(document.createTextNode(glyph));
        }

        iconEl.classList.add('has-tier');
        iconEl.appendChild(tierEl);
    }

    function createBuffIconElement(buff)
    {
        var iconEl = document.createElement('span');
        var hasCustomIcon = !!(buff.icon && String(buff.icon).trim());
        var glyphEl = document.createElement('span');

        iconEl.className = 'combat-buff-icon'
            + (buff.is_debuff === true || buff.is_debuff === 'S' ? ' is-debuff' : ' is-buff')
            + (hasCustomIcon ? ' has-custom-icon' : '');
        glyphEl.className = 'combat-buff-glyph';
        glyphEl.textContent = badgeText(buff);
        iconEl.title = buffTooltip(buff);
        iconEl.appendChild(glyphEl);
        appendTierStars(iconEl, buff.tier);

        if ((parseInt(buff.stack_count, 10) || 0) > 1)
        {
            var stack = document.createElement('span');
            stack.className = 'combat-buff-stack';
            stack.textContent = String(buff.stack_count);
            iconEl.appendChild(stack);
        }

        return iconEl;
    }

    function renderBuffIcons(containerEl, buffsArray)
    {
        if (!containerEl)
        {
            return false;
        }

        containerEl.innerHTML = '';

        if (!buffsArray || !buffsArray.length)
        {
            containerEl.hidden = true;
            return false;
        }

        containerEl.hidden = false;

        buffsArray.forEach(function (buff)
        {
            containerEl.appendChild(createBuffIconElement(buff));
        });

        return true;
    }

    function modifierValueForStat(statKeyField, modifierValue, targetStat)
    {
        var target = String(targetStat || '').toLowerCase();
        var pairs = parseModifierPairs(statKeyField, modifierValue);
        var i;

        for (i = 0; i < pairs.length; i++)
        {
            if (pairs[i].stat_key === target)
            {
                return pairs[i].modifier_value;
            }
        }

        return 0;
    }

    function computeStackedEffect(stacks)
    {
        if (!stacks || !stacks.length)
        {
            return '';
        }

        var first = stacks[0];
        var isDebuff = first.is_debuff === true || first.is_debuff === 'S';
        var kind = first.modifier_kind || 'percent';
        var templatePairs = parseModifierPairs(first.stat_key, first.modifier_value);

        if (!templatePairs.length)
        {
            return '';
        }

        var parts = templatePairs.map(function (pair)
        {
            var statLabel = pair.stat_key.toUpperCase();

            if (kind === 'flat')
            {
                var flatTotal = 0;

                stacks.forEach(function (buff)
                {
                    var value = modifierValueForStat(buff.stat_key, buff.modifier_value, pair.stat_key);
                    var magnitude = Math.abs(value);
                    flatTotal += isDebuff ? -magnitude : magnitude;
                });

                return (flatTotal >= 0 ? '+' : '') + flatTotal + ' ' + statLabel;
            }

            var multiplier = 1;

            stacks.forEach(function (buff)
            {
                var value = modifierValueForStat(buff.stat_key, buff.modifier_value, pair.stat_key);
                var magnitude = Math.abs(value);
                var signed = isDebuff ? -magnitude : magnitude;
                multiplier *= (1 + (signed / 100));
            });

            var percentTotal = Math.round((multiplier - 1) * 100);

            return (percentTotal >= 0 ? '+' : '') + percentTotal + '% ' + statLabel;
        });

        return parts.join(', ');
    }

    return {
        statKeyLabel: statKeyLabel,
        parseModifierPairs: parseModifierPairs,
        affectsStat: affectsStat,
        badgeText: badgeText,
        iconLabel: iconLabel,
        effectLabel: effectLabel,
        buffTooltip: buffTooltip,
        createBuffIconElement: createBuffIconElement,
        renderBuffIcons: renderBuffIcons,
        computeStackedEffect: computeStackedEffect
    };
})();
