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
    'character_type' => $character_types[0],
    'player_class' => 'nerd'
];

$selected_mode = count($characters) === 0 ? 'new' : 'character';
$selected_id_user_ig = count($characters) > 0 ? (int) $characters[0]['id_user_ig'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'select')
    {
        $id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;

        if ($id_user_ig <= 0)
        {
            $error = 'Select a character.';
            $selected_mode = 'character';
            $selected_id_user_ig = $id_user_ig;
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
            $selected_mode = 'character';
            $selected_id_user_ig = $id_user_ig;
        }
    }
    elseif ($action === 'create')
    {
        $form['display_name'] = trim(isset($_POST['display_name']) ? $_POST['display_name'] : '');
        $form['gender'] = isset($_POST['gender']) ? $_POST['gender'] : '';
        $form['character_type'] = isset($_POST['character_type']) ? $_POST['character_type'] : '';
        $form['player_class'] = isset($_POST['player_class']) ? $_POST['player_class'] : '';
        $selected_mode = 'new';

        if (!$can_create)
        {
            $error = 'Maximum number of characters reached.';
        }
        else if (!PLAYER_CLASS::isValidStarterCode($form['player_class']))
        {
            $error = 'Choose Nerd or Stud as your class.';
        }
        else
        {
            $result = animaster_create_character(
                $form['display_name'],
                $form['gender'],
                $form['character_type'],
                $form['player_class']
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

    if ($selected_mode === 'character' && $selected_id_user_ig <= 0 && count($characters) > 0)
    {
        $selected_id_user_ig = (int) $characters[0]['id_user_ig'];
    }
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
<body class="character-screen-page">
    <div id="character-screen" class="screen">
        <div class="panel character-panel">
            <div class="character-header">
                <div>
                    <h1>Choose your character</h1>
                    <p class="subtitle character-subtitle">
                        Signed in as <?php echo animaster_h($username); ?>
                        · <?php echo count($characters); ?> / <?php echo (int) $max_characters; ?> characters
                    </p>
                </div>
                <a class="hud-logout" href="logout.php">Logout</a>
            </div>

            <?php if ($error !== '') { ?>
                <p class="auth-message error"><?php echo animaster_h($error); ?></p>
            <?php } ?>

            <div class="character-picker"
                 data-default-mode="<?php echo animaster_h($selected_mode); ?>"
                 data-default-id="<?php echo (int) $selected_id_user_ig; ?>">
                <div class="character-card-strip" role="listbox" aria-label="Characters">
                    <?php foreach ($characters as $character) {
                        $initial = strtoupper(substr(trim($character['display_name']), 0, 1));
                        if ($initial === '')
                        {
                            $initial = '?';
                        }

                        $portrait_class = 'character-slot-portrait';

                        if (!empty($character['player_class_code']))
                        {
                            if ($character['player_class_code'] === 'nerd')
                            {
                                $portrait_class .= ' character-slot-portrait-nerd';
                            }
                            else if ($character['player_class_code'] === 'stud')
                            {
                                $portrait_class .= ' character-slot-portrait-stud';
                            }
                            else
                            {
                                $portrait_class .= ' character-slot-portrait-specialized';
                            }
                        }
                        ?>
                        <button type="button"
                                class="character-slot-card"
                                role="option"
                                data-mode="character"
                                data-id-user-ig="<?php echo (int) $character['id_user_ig']; ?>"
                                aria-selected="false">
                            <span class="<?php echo animaster_h($portrait_class); ?>">
                                <span class="character-slot-initial"><?php echo animaster_h($initial); ?></span>
                            </span>
                            <span class="character-slot-name"><?php echo animaster_h($character['display_name']); ?></span>
                            <span class="character-slot-class">
                                <?php echo animaster_h(!empty($character['player_class_name']) ? $character['player_class_name'] : '—'); ?>
                            </span>
                            <span class="character-slot-meta">
                                Lv <?php echo (int) $character['level']; ?>
                                · <?php echo animaster_h($character['character_type']); ?>
                            </span>
                            <span class="character-slot-zone">Zone <?php echo (int) $character['id_zone']; ?></span>
                        </button>
                    <?php } ?>

                    <?php if ($can_create) { ?>
                        <button type="button"
                                class="character-slot-card character-slot-new"
                                role="option"
                                data-mode="new"
                                aria-selected="false">
                            <span class="character-slot-plus" aria-hidden="true">+</span>
                            <span class="character-slot-label">New character</span>
                        </button>
                    <?php } ?>
                </div>

                <div class="character-detail">
                    <?php if (count($characters) > 0) { ?>
                        <div id="character-play-panel" class="character-detail-panel" hidden>
                            <form method="post" action="character_select.php" class="character-play-form">
                                <input type="hidden" name="action" value="select">
                                <input type="hidden" name="id_user_ig" id="select-id-user-ig"
                                       value="<?php echo (int) $selected_id_user_ig; ?>">
                                <button type="submit" id="character-play-btn" class="character-primary-btn">
                                    Enter world
                                </button>
                            </form>
                        </div>
                    <?php } ?>

                    <?php if ($can_create) { ?>
                        <div id="character-create-panel" class="character-detail-panel" hidden>
                            <h2 class="character-detail-title">
                                <?php echo count($characters) > 0 ? 'Create a new character' : 'Create your first character'; ?>
                            </h2>
                            <form method="post" action="character_select.php" class="character-create-form">
                                <input type="hidden" name="action" value="create">
                                <label class="character-field-full">
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
                                    Class
                                    <select name="player_class" required>
                                        <option value="nerd"<?php echo $form['player_class'] === 'nerd' ? ' selected' : ''; ?>>Nerd — knowledge and precision</option>
                                        <option value="stud"<?php echo $form['player_class'] === 'stud' ? ' selected' : ''; ?>>Stud — action and presence</option>
                                    </select>
                                </label>
                                <label class="character-field-full">
                                    Avatar look
                                    <select name="character_type" required>
                                        <?php foreach ($character_types as $type) { ?>
                                            <option value="<?php echo animaster_h($type); ?>"<?php echo $form['character_type'] === $type ? ' selected' : ''; ?>>
                                                <?php echo animaster_h($type); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit" class="character-primary-btn character-field-full">
                                    Create and play
                                </button>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <script src="<?php echo animaster_h(animaster_asset_url('js/character_select.js')); ?>"></script>
</body>
</html>
