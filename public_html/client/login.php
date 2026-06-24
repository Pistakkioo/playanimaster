<?php
require __DIR__ . '/includes/session_auth.php';

animaster_session_start();
animaster_redirect_if_logged_in();

$error = '';
$success = '';
$active_tab = 'login';
$form = [
    'username' => '',
    'email' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'login')
    {
        $active_tab = 'login';
        $form['username'] = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($form['username'] === '' || $password === '')
        {
            $error = 'Username and password are required.';
        }
        else
        {
            $result = animaster_login($form['username'], $password);

            if ($result['ok'])
            {
                header('Location: character_select.php');
                exit;
            }

            $error = $result['message'];
        }
    }
    elseif ($action === 'register')
    {
        $active_tab = 'register';
        $form['username'] = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $form['email'] = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

        if ($form['username'] === '' || $form['email'] === '' || $password === '')
        {
            $error = 'All fields are required.';
        }
        elseif ($password !== $password_confirm)
        {
            $error = 'Passwords do not match.';
        }
        else
        {
            $register = animaster_dispatch_register(
                $form['username'],
                $form['email'],
                $password
            );

            if (!$register['ok'])
            {
                $error = $register['message'];
            }
            else
            {
                $login = animaster_login($form['username'], $password);

                if ($login['ok'])
                {
                    header('Location: character_select.php');
                    exit;
                }

                $active_tab = 'login';
                $success = 'Account created. Please log in.';
                $error = $login['message'];
            }
        }
    }
    else
    {
        $error = 'Invalid request.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animaster — Login</title>
    <link rel="stylesheet" href="<?php echo animaster_h(animaster_asset_url('css/game.css')); ?>">
</head>
<body>
    <div id="auth-screen" class="screen">
        <div class="panel auth-panel">
            <h1>Animaster</h1>
            <p class="subtitle">Sign in to enter the world</p>

            <div class="auth-tabs" role="tablist">
                <a class="auth-tab<?php echo $active_tab === 'login' ? ' active' : ''; ?>" href="#login" data-tab="login">Login</a>
                <a class="auth-tab<?php echo $active_tab === 'register' ? ' active' : ''; ?>" href="#register" data-tab="register">Register</a>
            </div>

            <?php if ($error !== '') { ?>
                <p class="auth-message error"><?php echo animaster_h($error); ?></p>
            <?php } ?>

            <?php if ($success !== '') { ?>
                <p class="auth-message success"><?php echo animaster_h($success); ?></p>
            <?php } ?>

            <div id="auth-login" class="auth-pane<?php echo $active_tab === 'login' ? ' active' : ''; ?>" role="tabpanel">
                <form method="post" action="login.php">
                    <input type="hidden" name="action" value="login">
                    <label>
                        Username
                        <input type="text" name="username" autocomplete="username" required
                               value="<?php echo animaster_h($form['username']); ?>">
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" autocomplete="current-password" required>
                    </label>
                    <button type="submit">Sign in</button>
                </form>
            </div>

            <div id="auth-register" class="auth-pane<?php echo $active_tab === 'register' ? ' active' : ''; ?>" role="tabpanel">
                <form method="post" action="login.php">
                    <input type="hidden" name="action" value="register">
                    <label>
                        Username
                        <input type="text" name="username" autocomplete="username" required minlength="3" maxlength="50"
                               value="<?php echo animaster_h($form['username']); ?>">
                    </label>
                    <label>
                        Email
                        <input type="email" name="email" autocomplete="email" required
                               value="<?php echo animaster_h($form['email']); ?>">
                    </label>
                    <label>
                        Password
                        <input type="password" name="password" autocomplete="new-password" required minlength="6">
                    </label>
                    <label>
                        Confirm password
                        <input type="password" name="password_confirm" autocomplete="new-password" required minlength="6">
                    </label>
                    <button type="submit">Create account</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function ()
    {
        function syncAuthTab()
        {
            var hash = window.location.hash;

            if (hash !== '#login' && hash !== '#register')
            {
                return;
            }

            var tab = hash === '#register' ? 'register' : 'login';

            document.querySelectorAll('.auth-tab[data-tab]').forEach(function (el)
            {
                el.classList.toggle('active', el.getAttribute('data-tab') === tab);
            });

            document.getElementById('auth-login').classList.toggle('active', tab === 'login');
            document.getElementById('auth-register').classList.toggle('active', tab === 'register');
        }

        syncAuthTab();
        window.addEventListener('hashchange', syncAuthTab);
    })();
    </script>
</body>
</html>
