<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/player_class.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '';

$stato = 'OK';
$msg = 'OK';
$response = '{}';

if ($id_user_ig <= 0)
{
    $stato = 'KO';
    $msg = 'Invalid character';
}
else
{
    $stmt = $conn->prepare('
        SELECT UI.id_user_ig, UI.display_name, UI.gender, UI.character_type,
               UI.id_player_class, UI.level, UI.exp_total, UI.gold, UI.id_zone,
               PC.code AS player_class_code, PC.name, PC.name_it, PC.name_pt,
               PC.unlock_level AS class_unlock_level, PC.starter_branch,
               Z.scene_name
        FROM users_ig UI
        LEFT JOIN player_classes PC ON PC.id_player_class = UI.id_player_class
        LEFT JOIN zones Z ON Z.id_zone = UI.id_zone
        WHERE UI.id_user_ig = :id_user_ig
        LIMIT 1
    ');
    $stmt->execute([':id_user_ig' => $id_user_ig]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row)
    {
        $stato = 'KO';
        $msg = 'Character not found';
    }
    else
    {
        $lvl_up_constant = 80;
        $costante_row = $conn->query("
            SELECT valore FROM costanti WHERE costante = 'lvl_up_constant_player' LIMIT 1
        ");
        $costante_data = $costante_row ? $costante_row->fetch(PDO::FETCH_ASSOC) : null;

        if ($costante_data && isset($costante_data['valore']))
        {
            $lvl_up_constant = (int) $costante_data['valore'];
        }

        $level = (int) $row['level'];
        $exp_total = (int) $row['exp_total'];
        $class_name = '';

        if (!empty($row['player_class_code']))
        {
            $class_name = PLAYER_CLASS::displayName($row);
        }

        $abilities = [];
        $ability_rows = PLAYER_CLASS::fetchUserAbilities($conn, $id_user_ig);

        foreach ($ability_rows as $ability_row)
        {
            $abilities[] = PLAYER_CLASS::formatAbilityRow($ability_row);
        }

        $payload = [
            'id_user_ig' => $id_user_ig,
            'display_name' => (string) $row['display_name'],
            'gender' => (string) $row['gender'],
            'character_type' => (string) $row['character_type'],
            'id_player_class' => (int) $row['id_player_class'],
            'player_class_code' => (string) ($row['player_class_code'] ?: ''),
            'player_class_name' => $class_name,
            'class_unlock_level' => (int) ($row['class_unlock_level'] ?: 1),
            'starter_branch' => (string) ($row['starter_branch'] ?: ''),
            'level' => $level,
            'gold' => (int) $row['gold'],
            'id_zone' => (int) $row['id_zone'],
            'scene_name' => (string) ($row['scene_name'] ?: ''),
            'exp' => animaster_player_exp_range($level, $exp_total, $lvl_up_constant),
            'abilities' => $abilities
        ];

        $encoded = json_encode($payload);

        if ($encoded === false)
        {
            $stato = 'KO';
            $msg = 'Encode failed';
        }
        else
        {
            $response = $encoded;
        }
    }
}

echo json_encode([
    'stato' => $stato,
    'msg' => $msg,
    'response' => $response
]);
