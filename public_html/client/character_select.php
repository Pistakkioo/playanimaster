<?php
require __DIR__ . '/includes/session_auth.php';

animaster_require_login();

if (isset($_GET['switch']) && $_GET['switch'] === '1')
{
    if (!empty($_SESSION['animaster_id_user_ig']))
    {
        animaster_mark_character_offline(animaster_get_conn(), (int) $_SESSION['animaster_id_user_ig']);
    }

    unset($_SESSION['animaster_id_user_ig']);
    unset($_SESSION['animaster_profile']);
    unset($_SESSION['animaster_battle']);
}
elseif (animaster_has_character())
{
    header('Location: game.php');
    exit;
}

$error = '';
$characters = animaster_get_characters();
$character_types = animaster_get_character_types();
$genders = animaster_get_genders();
$max_characters = ANIMASTER_MAX_CHARACTERS;
$can_create = count($characters) < $max_characters;
$username = isset($_SESSION['animaster_username']) ? $_SESSION['animaster_username'] : '';

$form = [
    'display_name' => '',
    'gender' => 'M',
    'character_type' => $character_types[0]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'select')
    {
        $id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;

        if ($id_user_ig <= 0)
        {
            $error = 'Select a character.';
        }
        else
        {
            $result = animaster_select_character($id_user_ig);

            if ($result['ok'])
            {
                header('Location: game.php');
                exit;
            }

            $error = $result['message'];
        }
    }
    elseif ($action === 'create')
    {
        $form['display_name'] = trim(isset($_POST['display_name']) ? $_POST['display_name'] : '');
        $form['gender'] = isset($_POST['gender']) ? $_POST['gender'] : '';
        $form['character_type'] = isset($_POST['character_type']) ? $_POST['character_type'] : '';

        if (!$can_create)
        {
            $error = 'Maximum number of characters reached.';
        }
        else
        {
            $result = animaster_create_character(
                $form['display_name'],
                $form['gender'],
                $form['character_type']
            );

            if ($result['ok'])
            {
                header('Location: game.php');
                exit;
            }

            $error = $result['message'];
        }
    }
    else
    {
        $error = 'Invalid request.';
    }

    $characters = animaster_get_characters();
    $can_create = count($characters) < $max_characters;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animaster — Choose character</title>
    <link rel="stylesheet" href="<?php echo animaster_h(animaster_asset_url('css/game.css')); ?>">
</head>
<body>
    <div id="character-screen" class="screen">
        <div class="panel character-panel">
            <div class="character-header">
                <div>
                    <h1>Choose your character</h1>
                    <p class="subtitle">Signed in as <?php echo animaster_h($username); ?></p>
                </div>
                <a class="hud-logout" href="logout.php">Logout</a>
            </div>

            <?php if ($error !== '') { ?>
                <p class="auth-message error"><?php echo animaster_h($error); ?></p>
            <?php } ?>

            <?php if (count($characters) > 0) { ?>
                <section class="character-section">
                    <h2>Your characters</h2>
                    <form method="post" action="character_select.php" class="character-list">
                        <input type="hidden" name="action" value="select">
                        <ul class="character-cards">
                            <?php foreach ($characters as $character) { ?>
                                <li>
                                    <label class="character-card">
                                        <input type="radio" name="id_user_ig" value="<?php echo (int) $character['id_user_ig']; ?>" required>
                                        <span class="character-card-body">
                                            <strong><?php echo animaster_h($character['display_name']); ?></strong>
                                            <span class="character-meta">
                                                Lv <?php echo (int) $character['level']; ?>
                                                · <?php echo animaster_h($character['character_type']); ?>
                                                · Zone <?php echo (int) $character['id_zone']; ?>
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            <?php } ?>
                        </ul>
                        <button type="submit">Enter world</button>
                    </form>
                </section>
            <?php } ?>

            <?php if ($can_create) { ?>
                <section class="character-section">
                    <h2><?php echo count($characters) > 0 ? 'Create a new character' : 'Create your first character'; ?></h2>
                    <p class="character-limit-note">
                        <?php echo count($characters); ?> / <?php echo (int) $max_characters; ?> characters
                    </p>
                    <form method="post" action="character_select.php" class="character-create-form">
                        <input type="hidden" name="action" value="create">
                        <label>
                            Display name
                            <input type="text" name="display_name" autocomplete="nickname" required minlength="3" maxlength="50"
                                   value="<?php echo animaster_h($form['display_name']); ?>">
                        </label>
                        <label>
                            Gender
                            <select name="gender" required>
                                <?php foreach ($genders as $gender) { ?>
                                    <option value="<?php echo animaster_h($gender); ?>"<?php echo $form['gender'] === $gender ? ' selected' : ''; ?>>
                                        <?php echo $gender === 'M' ? 'Male' : 'Female'; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <label>
                            Character type
                            <select name="character_type" required>
                                <?php foreach ($character_types as $type) { ?>
                                    <option value="<?php echo animaster_h($type); ?>"<?php echo $form['character_type'] === $type ? ' selected' : ''; ?>>
                                        <?php echo animaster_h($type); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </label>
                        <button type="submit">Create and play</button>
                    </form>
                </section>
            <?php } ?>
        </div>
    </div>
</body>
</html>
