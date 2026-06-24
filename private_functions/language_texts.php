<?php

function animaster_normalize_lang_api($lang)
{
    $lang = trim((string) $lang);

    if ($lang === '' || $lang === 'en' || $lang === '_en')
    {
        return '_en';
    }

    if ($lang === 'pt' || $lang === '_pt')
    {
        return '_pt';
    }

    return '_it';
}

function animaster_language_text_column($lang_api)
{
    if ($lang_api === '_pt')
    {
        return 'text_pt';
    }

    if ($lang_api === '_it')
    {
        return 'text_it';
    }

    return 'text';
}

function animaster_get_client_lang_api()
{
    if (function_exists('animaster_session_start'))
    {
        animaster_session_start();
    }

    if (!empty($_SESSION['animaster_lang']))
    {
        return animaster_normalize_lang_api($_SESSION['animaster_lang']);
    }

    return '_it';
}

function animaster_load_language_texts($conn, $lang_api)
{
    $lang_api = animaster_normalize_lang_api($lang_api);
    $column = animaster_language_text_column($lang_api);
    $texts = [];

    $result = $conn->query("
        SELECT tag, text, text_it, text_pt
        FROM language_texts
        WHERE tag IS NOT NULL
          AND tag != ''
    ");

    if (!$result)
    {
        return $texts;
    }

    while ($row = $result->fetch(PDO::FETCH_ASSOC))
    {
        $tag = trim((string) $row['tag']);

        if ($tag === '')
        {
            continue;
        }

        $value = isset($row[$column]) ? trim((string) $row[$column]) : '';

        if ($value === '' && $column !== 'text')
        {
            $value = trim((string) $row['text']);
        }

        if ($value !== '')
        {
            $texts[$tag] = $value;
        }
    }

    return $texts;
}
