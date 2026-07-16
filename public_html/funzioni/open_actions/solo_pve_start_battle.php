<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/solo_pve.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;
$id_wild_animal = isset($_POST['id_wild_animal']) ? (int) $_POST['id_wild_animal'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '_it';

if ($id_user_ig <= 0 || $id_zone <= 0 || $id_wild_animal <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_REQUEST',
        'response' => ''
    ]);
    exit;
}

$result = animaster_solo_pve_start($conn, $id_user_ig, $id_zone, $id_wild_animal, $LANG);

if (!empty($result['error']))
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => $result['error'],
        'response' => ''
    ]);
    exit;
}

$payload = [
    'id_battle' => (int) $result['id_battle'],
    'battle_type' => 'solo_pve'
];

echo json_encode([
    'stato' => 'OK',
    'msg' => 'OK',
    'response' => json_encode($payload)
]);
