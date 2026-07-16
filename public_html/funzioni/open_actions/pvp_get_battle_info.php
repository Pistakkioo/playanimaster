<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/pvp.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_battle = isset($_POST['id_battle']) ? (int) $_POST['id_battle'] : 0;
$turn = isset($_POST['turn']) ? (int) $_POST['turn'] : 0;
$restarting_old_battle = isset($_POST['restarting_old_battle']) ? (string) $_POST['restarting_old_battle'] : 'N';
$lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$params = [
    'id_user_ig' => $id_user_ig,
    'id_battle' => $id_battle,
    'turn' => $turn,
    'restarting_old_battle' => $restarting_old_battle,
    'lang' => $lang,
    'type' => $type,
    'id' => $id
];

try
{
    $result = animaster_pvp_get_battle_info($conn, $params);
}
catch (Throwable $e)
{
    error_log('[pvp_get_battle_info] ' . $e->getMessage());
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'SERVER_ERROR',
        'response' => '',
        'battle_meta' => '{}'
    ]);
    exit;
}

if (!empty($result['ok']))
{
    $stringone = '';

    foreach ($result['moves'] as $row_move)
    {
        $singolo_json = json_encode($row_move, JSON_UNESCAPED_UNICODE);

        if ($stringone !== '')
        {
            $stringone .= '#';
        }

        $stringone .= $singolo_json;
    }

    echo json_encode([
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => $stringone,
        'battle_meta' => json_encode($result['meta'], JSON_UNESCAPED_UNICODE)
    ]);
    exit;
}

echo json_encode([
    'stato' => 'KO',
    'msg' => isset($result['error']) ? $result['error'] : 'PVP_BATTLE_FAILED',
    'response' => '',
    'battle_meta' => '{}'
]);
