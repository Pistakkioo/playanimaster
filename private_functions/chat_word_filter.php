<?php

/**
 * Funny chat word replacements — applied server-side on send only.
 * Matches leetspeak variants (sh1t, f*ck, etc.) via normalization.
 */

function animaster_chat_word_filter_leet_normalize($text)
{
    $text = mb_strtolower((string) $text, 'UTF-8');
    $len = mb_strlen($text, 'UTF-8');
    $out = '';

    for ($i = 0; $i < $len; $i++)
    {
        $ch = mb_substr($text, $i, 1, 'UTF-8');

        switch ($ch)
        {
            case '0':
                $out .= 'o';
                break;
            case '1':
            case '!':
            case '|':
                $out .= 'i';
                break;
            case '3':
                $out .= 'e';
                break;
            case '4':
            case '@':
                $out .= 'a';
                break;
            case '5':
            case '$':
                $out .= 's';
                break;
            case '6':
            case '9':
                $out .= 'g';
                break;
            case '7':
            case '+':
                $out .= 't';
                break;
            case '8':
                $out .= 'b';
                break;
            case '*':
            case '_':
            case '-':
            case '.':
                break;
            default:
                $out .= $ch;
                break;
        }
    }

    return $out;
}

function animaster_chat_word_filter_apply_case($original, $replacement)
{
    if ($original === mb_strtoupper($original, 'UTF-8'))
    {
        return mb_strtoupper($replacement, 'UTF-8');
    }

    $first = mb_substr($original, 0, 1, 'UTF-8');

    if ($first === mb_strtoupper($first, 'UTF-8') && $original !== mb_strtolower($original, 'UTF-8'))
    {
        return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($replacement, 1, null, 'UTF-8');
    }

    return mb_strtolower($replacement, 'UTF-8');
}

function animaster_chat_word_filter_load_rules($conn)
{
    static $loaded = false;
    static $phrase_rules = [];
    static $word_rules = [];

    if ($loaded)
    {
        return ['phrases' => $phrase_rules, 'words' => $word_rules];
    }

    $loaded = true;

    $stmt = $conn->query('
        SELECT bad_word, replacement
        FROM chat_word_replacements
        WHERE flg_active = \'S\'
        ORDER BY CHAR_LENGTH(bad_word) DESC, sort_order ASC, id_chat_word_replacement ASC
    ');

    if (!$stmt)
    {
        return ['phrases' => [], 'words' => []];
    }

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        $bad = mb_strtolower(trim((string) $row['bad_word']), 'UTF-8');
        $replacement = trim((string) $row['replacement']);

        if ($bad === '' || $replacement === '')
        {
            continue;
        }

        if (strpos($bad, ' ') !== false)
        {
            $phrase_rules[] = [
                'bad_word' => $bad,
                'replacement' => $replacement
            ];
        }
        else
        {
            $word_rules[$bad] = $replacement;
        }
    }

    return ['phrases' => $phrase_rules, 'words' => $word_rules];
}

function animaster_chat_word_filter_apply_phrases($text, array $phrase_rules)
{
    foreach ($phrase_rules as $rule)
    {
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($rule['bad_word'], '/') . '(?![\p{L}\p{N}])/iu';
        $text = preg_replace($pattern, $rule['replacement'], $text);
    }

    return $text;
}

function animaster_chat_word_filter_apply_words($text, array $word_rules)
{
    if (empty($word_rules))
    {
        return $text;
    }

    return preg_replace_callback(
        '/(?<![\p{L}\p{N}])([\p{L}\p{N}@$!|+]+)(?![\p{L}\p{N}])/u',
        function (array $match) use ($word_rules)
        {
            $token = $match[1];
            $normalized = animaster_chat_word_filter_leet_normalize($token);

            if (!isset($word_rules[$normalized]))
            {
                return $token;
            }

            return animaster_chat_word_filter_apply_case($token, $word_rules[$normalized]);
        },
        $text
    );
}

function animaster_chat_word_filter_apply($conn, $text)
{
    $text = (string) $text;

    if ($text === '')
    {
        return $text;
    }

    try
    {
        $rules = animaster_chat_word_filter_load_rules($conn);
    }
    catch (Throwable $e)
    {
        error_log('[chat_word_filter] load failed: ' . $e->getMessage());

        return $text;
    }

    if (empty($rules['phrases']) && empty($rules['words']))
    {
        return $text;
    }

    $text = animaster_chat_word_filter_apply_phrases($text, $rules['phrases']);

    return animaster_chat_word_filter_apply_words($text, $rules['words']);
}
