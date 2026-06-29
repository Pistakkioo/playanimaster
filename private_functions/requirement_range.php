<?php

/**
 * Whether a counted value (level, items, animals) satisfies a min/max link range.
 * max NULL = no upper bound.
 */
function requirement_value_in_range($value, $min, $max)
{
    $value = (int) $value;
    $min = (int) $min;

    if ($value < $min)
    {
        return false;
    }

    if ($max === null || $max === '')
    {
        return true;
    }

    return $value <= (int) $max;
}

/**
 * Human-readable range for dev UI (e.g. 4–∞).
 */
function requirement_range_label($min, $max)
{
    $min = (int) $min;

    if ($max === null || $max === '')
    {
        return $min . '–∞';
    }

    return $min . '–' . (int) $max;
}

function requirement_max_is_unbounded($max)
{
    return $max === null || $max === '';
}

/**
 * Parse max from dev forms: checkbox max_unbounded=S → NULL.
 *
 * @return int|null
 */
function requirement_max_from_post(array $post, $checkbox_key = 'max_unbounded')
{
    if (isset($post[$checkbox_key]) && (string) $post[$checkbox_key] === 'S')
    {
        return null;
    }

    if (!isset($post['max']) || $post['max'] === '')
    {
        return 0;
    }

    return (int) $post['max'];
}
