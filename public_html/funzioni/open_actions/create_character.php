<?php
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_config.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';

$conn = animaster_get_conn();

$id_user = isset($_POST['id_user']) ? (int) $_POST['id_user'] : 0;
$display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
$character_type = isset($_POST['character_type']) ? $_POST['character_type'] : '';

$stato = 'OK';
$msg = 'OK';
$id_user_ig = null;

if ($id_user <= 0)
{
    $stato = 'KO';
    $msg = 'Invalid account';
}
else if ($display_name === '' || strlen($display_name) < 3 || strlen($display_name) > 50)
{
    $stato = 'KO';
    $msg = 'Display name must be 3–50 characters';
}
else if (!animaster_is_valid_gender($gender))
{
    $stato = 'KO';
    $msg = 'Invalid gender';
}
else if (!animaster_is_valid_character_type($character_type))
{
    $stato = 'KO';
    $msg = 'Invalid character type';
}
else
{
    $result_count = $conn->query("
        SELECT COUNT(1) FROM users_ig WHERE id_user = \"$id_user\"
    ");
    $count_characters = (int) $result_count->fetch()[0];

    if ($count_characters >= ANIMASTER_MAX_CHARACTERS)
    {
        $stato = 'KO';
        $msg = 'Maximum number of characters reached';
    }
    else
    {
        $result_check_d = $conn->query("
            SELECT COUNT(1) FROM users_ig WHERE display_name = \"$display_name\"
        ");
        $count_display_name_exists = (int) $result_check_d->fetch()[0];

        if ($count_display_name_exists > 0)
        {
            $stato = 'KO';
            $msg = 'Display name already exists';
        }
        else
        {
            $initial_position_z = INITIAL_POSITION_Z;
            $initial_position_x = INITIAL_POSITION_X;
            $initial_position_y = INITIAL_POSITION_Y;

            $result = $conn->query("
                INSERT INTO users_ig
                (id_user, dt_creazione, dt_modifica, id_zone, flg_online, position_x, position_y, position_z,
                 exp_total, level, gender, character_type, display_name)
                VALUES
                (\"$id_user\", NOW(), NOW(), 1000, 'N', \"$initial_position_x\", \"$initial_position_y\",
                 \"$initial_position_z\", 0, 1, \"$gender\", \"$character_type\", \"$display_name\")
            ");

            if (!$result)
            {
                $stato = 'KO';
                $msg = 'Failed to create character';
            }
            else
            {
                $id_user_ig = (int) $conn->lastInsertId();
            }
        }
    }
}

echo json_encode([
    'stato' => $stato,
    'msg' => $msg,
    'response' => json_encode(['id_user_ig' => $id_user_ig])
]);
