<?php

/**
 * Dev-only admin pages: token gate (query param T=...).
 * Set DEV_ADMIN_TOKEN in .env / docker-compose environment.
 */
function dev_admin_expected_token()
{
    $token = getenv('DEV_ADMIN_TOKEN');

    if ($token === false || $token === '')
    {
        $token = 'change_me_dev_only';
    }

    return (string) $token;
}

function dev_admin_token_valid()
{
    if (!isset($_GET['T']) && !isset($_POST['T']))
    {
        return false;
    }

    $provided = isset($_POST['T']) ? (string) $_POST['T'] : (string) $_GET['T'];

    return hash_equals(dev_admin_expected_token(), $provided);
}

function dev_admin_require_auth()
{
    if (!dev_admin_token_valid())
    {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Access denied. Valid dev token required (?T=...).';
        exit;
    }
}

function dev_admin_token()
{
    if (isset($_POST['T']))
    {
        return (string) $_POST['T'];
    }

    if (isset($_GET['T']))
    {
        return (string) $_GET['T'];
    }

    return dev_admin_expected_token();
}

function dev_admin_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dev_admin_url(array $extra = [])
{
    return dev_admin_page_url('dev_npcs.php', $extra);
}

function dev_admin_page_url($page, array $extra = [])
{
    $params = array_merge(['T' => dev_admin_token()], $extra);
    $query = http_build_query($params);

    return $page . '?' . $query;
}
