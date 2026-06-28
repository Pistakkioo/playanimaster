<?php

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_config.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';

function animaster_session_start()
{
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
}

function animaster_is_logged_in()
{
    animaster_session_start();

    return !empty($_SESSION['animaster_logged_in'])
        && !empty($_SESSION['animaster_id_user']);
}

function animaster_has_character()
{
    animaster_session_start();

    return animaster_is_logged_in()
        && !empty($_SESSION['animaster_id_user_ig'])
        && !empty($_SESSION['animaster_profile']);
}

function animaster_require_login()
{
    if (!animaster_is_logged_in())
    {
        header('Location: login.php');
        exit;
    }
}

function animaster_require_character()
{
    animaster_require_login();

    if (!animaster_has_character())
    {
        header('Location: character_select.php');
        exit;
    }
}

function animaster_redirect_if_logged_in()
{
    if (!animaster_is_logged_in())
    {
        return;
    }

    if (animaster_has_character())
    {
        header('Location: game.php');
    }
    else
    {
        header('Location: character_select.php');
    }

    exit;
}

function animaster_post_dispatch($relative_path, array $post_fields)
{
    animaster_get_conn();

    foreach ($post_fields as $key => $value)
    {
        $_POST[$key] = $value;
    }

    ob_start();
    include $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    $output = ob_get_clean();

    return json_decode($output, true);
}

function animaster_dispatch_login_account($username, $password)
{
    $data = animaster_post_dispatch('/funzioni/open_actions/login_account.php', [
        'username' => $username,
        'password' => $password
    ]);

    if (!is_array($data))
    {
        return [
            'ok' => false,
            'message' => 'Invalid login response'
        ];
    }

    if ($data['stato'] !== 'OK')
    {
        return [
            'ok' => false,
            'message' => !empty($data['msg']) ? $data['msg'] : 'Login failed'
        ];
    }

    $account = json_decode($data['response'], true);

    if (!is_array($account) || empty($account['id_user']))
    {
        return [
            'ok' => false,
            'message' => 'Invalid account data'
        ];
    }

    return [
        'ok' => true,
        'account' => $account
    ];
}

function animaster_set_session_from_account(array $account)
{
    session_regenerate_id(true);

    $_SESSION['animaster_logged_in'] = true;
    $_SESSION['animaster_id_user'] = (int) $account['id_user'];
    $_SESSION['animaster_username'] = $account['username'];
    unset($_SESSION['animaster_id_user_ig']);
    unset($_SESSION['animaster_profile']);
    unset($_SESSION['animaster_battle']);

    return true;
}

function animaster_dispatch_select_character($id_user, $id_user_ig)
{
    $data = animaster_post_dispatch('/funzioni/open_actions/select_character.php', [
        'id_user' => $id_user,
        'id_user_ig' => $id_user_ig
    ]);

    if (!is_array($data))
    {
        return [
            'ok' => false,
            'message' => 'Invalid character response'
        ];
    }

    if ($data['stato'] !== 'OK')
    {
        return [
            'ok' => false,
            'message' => !empty($data['msg']) ? $data['msg'] : 'Could not select character'
        ];
    }

    return [
        'ok' => true,
        'envelope' => $data
    ];
}

function animaster_set_session_from_login_envelope(array $envelope)
{
    $profile = json_decode($envelope['response'], true);

    if (!is_array($profile))
    {
        return false;
    }

    $profile = animaster_normalize_profile($profile);

    if (empty($profile['id_user']) || empty($profile['id_user_ig']))
    {
        return false;
    }

    $_SESSION['animaster_id_user_ig'] = (int) $profile['id_user_ig'];
    $_SESSION['animaster_profile'] = $profile;

    if (!empty($envelope['response3']))
    {
        $battle = json_decode($envelope['response3'], true);
        $_SESSION['animaster_battle'] = is_array($battle) ? $battle : null;
    }
    else
    {
        $_SESSION['animaster_battle'] = null;
    }

    return true;
}

function animaster_select_character($id_user_ig)
{
    animaster_session_start();

    if (!animaster_is_logged_in())
    {
        return [
            'ok' => false,
            'message' => 'Not logged in'
        ];
    }

    $id_user = (int) $_SESSION['animaster_id_user'];
    $result = animaster_dispatch_select_character($id_user, (int) $id_user_ig);

    if (!$result['ok'])
    {
        return $result;
    }

    if (!animaster_set_session_from_login_envelope($result['envelope']))
    {
        return [
            'ok' => false,
            'message' => 'Could not load character'
        ];
    }

    return [
        'ok' => true
    ];
}

function animaster_dispatch_create_character($id_user, $display_name, $gender, $character_type, $player_class)
{
    $data = animaster_post_dispatch('/funzioni/open_actions/create_character.php', [
        'id_user' => $id_user,
        'display_name' => $display_name,
        'gender' => $gender,
        'character_type' => $character_type,
        'player_class' => $player_class
    ]);

    if (!is_array($data))
    {
        return [
            'ok' => false,
            'message' => 'Invalid create response'
        ];
    }

    if ($data['stato'] !== 'OK')
    {
        return [
            'ok' => false,
            'message' => !empty($data['msg']) ? $data['msg'] : 'Could not create character'
        ];
    }

    $payload = json_decode($data['response'], true);

    if (!is_array($payload) || empty($payload['id_user_ig']))
    {
        return [
            'ok' => false,
            'message' => 'Character was not created'
        ];
    }

    return [
        'ok' => true,
        'id_user_ig' => (int) $payload['id_user_ig']
    ];
}

function animaster_create_character($display_name, $gender, $character_type, $player_class)
{
    animaster_session_start();

    if (!animaster_is_logged_in())
    {
        return [
            'ok' => false,
            'message' => 'Not logged in'
        ];
    }

    $create = animaster_dispatch_create_character(
        (int) $_SESSION['animaster_id_user'],
        $display_name,
        $gender,
        $character_type,
        $player_class
    );

    if (!$create['ok'])
    {
        return $create;
    }

    return animaster_select_character($create['id_user_ig']);
}

function animaster_dispatch_register($username, $email, $password)
{
    $_POST['username'] = $username;
    $_POST['email'] = $email;
    $_POST['password'] = $password;

    ob_start();
    include $_SERVER['DOCUMENT_ROOT'] . '/funzioni/open_actions/register.php';
    $output = trim(ob_get_clean());

    if ($output === '0')
    {
        return [
            'ok' => true
        ];
    }

    return [
        'ok' => false,
        'message' => $output !== '' ? $output : 'Registration failed'
    ];
}

function animaster_login($username, $password)
{
    $result = animaster_dispatch_login_account($username, $password);

    if (!$result['ok'])
    {
        return $result;
    }

    animaster_set_session_from_account($result['account']);

    return [
        'ok' => true
    ];
}

function animaster_get_characters()
{
    animaster_session_start();

    if (!animaster_is_logged_in())
    {
        return [];
    }

    return animaster_fetch_characters_for_user(animaster_get_conn(), (int) $_SESSION['animaster_id_user']);
}

function animaster_logout()
{
    animaster_session_start();

    if (!empty($_SESSION['animaster_id_user_ig']))
    {
        animaster_mark_character_offline(animaster_get_conn(), (int) $_SESSION['animaster_id_user_ig']);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies'))
    {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function animaster_get_profile()
{
    animaster_session_start();

    return isset($_SESSION['animaster_profile']) ? $_SESSION['animaster_profile'] : null;
}

function animaster_refresh_character_session()
{
    animaster_session_start();

    if (!animaster_is_logged_in() || empty($_SESSION['animaster_id_user_ig']))
    {
        return false;
    }

    $conn = animaster_get_conn();
    $id_user = (int) $_SESSION['animaster_id_user'];
    $id_user_ig = (int) $_SESSION['animaster_id_user_ig'];
    $row = animaster_fetch_character_profile_row($conn, $id_user, $id_user_ig);

    if (!$row)
    {
        unset($_SESSION['animaster_id_user_ig']);
        unset($_SESSION['animaster_profile']);
        unset($_SESSION['animaster_battle']);

        return false;
    }

    animaster_mark_character_online($conn, $id_user_ig);
    $envelope = animaster_build_login_envelope($conn, $row);

    return animaster_set_session_from_login_envelope($envelope);
}

function animaster_get_battle_resume()
{
    animaster_session_start();

    return isset($_SESSION['animaster_battle']) ? $_SESSION['animaster_battle'] : null;
}

function animaster_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function animaster_asset_url($path)
{
    return $path . '?v=' . ANIMASTER_ASSET_VERSION;
}

function animaster_wild_sprites_script()
{
    $variants = [
        'default' => 'js/wild_sprites.js',
        'mid' => 'js/wild_sprites_mid.js',
        'large' => 'js/wild_sprites_large.js',
    ];

    $variant = defined('ANIMASTER_WILD_SPRITES_VARIANT') ? ANIMASTER_WILD_SPRITES_VARIANT : 'default';

    if (!isset($variants[$variant]))
    {
        $variant = 'default';
    }

    return $variants[$variant];
}

function animaster_wild_sprites_arch_script()
{
    $variant = defined('ANIMASTER_WILD_SPRITES_VARIANT') ? ANIMASTER_WILD_SPRITES_VARIANT : 'default';

    if ($variant === 'mid')
    {
        return 'js/wild_sprites_arch_16.js';
    }

    if ($variant === 'large')
    {
        return 'js/wild_sprites_arch_32.js';
    }

    return null;
}
