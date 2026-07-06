<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/party_pve.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_battle = isset($_POST['id_battle']) ? (int) $_POST['id_battle'] : 0;
$turn = isset($_POST['turn']) ? (int) $_POST['turn'] : 0;
$restarting_old_battle = isset($_POST['restarting_old_battle']) ? (string) $_POST['restarting_old_battle'] : 'N';
$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
$id_action = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$id_item_type_selected = isset($_POST['id_item_type_selected']) ? (int) $_POST['id_item_type_selected'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '_it';

if ($id_user_ig <= 0 || $id_battle <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_BATTLE_REQUEST',
        'response' => ''
    ]);
    exit;
}

$result = animaster_party_pve_handle_turn_request(
    $conn,
    $id_battle,
    $id_user_ig,
    $turn,
    $restarting_old_battle,
    $type,
    $id_action,
    $LANG,
    $id_item_type_selected
);

if (!empty($result['error']))
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => $result['error'],
        'response' => ''
    ]);
    exit;
}

$battle = $result['battle'];
$rows = $result['rows'];
$stringone = '';

foreach ($rows as $row)
{
    if ($stringone !== '')
    {
        $stringone .= '#';
    }

    $stringone .= json_encode($row);
}

$meta = animaster_party_pve_build_meta($conn, $battle, $id_user_ig, $LANG);

echo json_encode([
    'stato' => 'OK',
    'msg' => 'OK',
    'response' => $stringone,
    'party_pve_meta' => json_encode($meta)
]);
