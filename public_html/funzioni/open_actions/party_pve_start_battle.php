<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/party_pve.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;
$id_wild_animal = isset($_POST['id_wild_animal']) ? (int) $_POST['id_wild_animal'] : 0;
$ref_x = isset($_POST['pos_x']) ? (float) $_POST['pos_x'] : 0;
$ref_z = isset($_POST['pos_z']) ? (float) $_POST['pos_z'] : 0;
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

$result = animaster_party_pve_start(
    $conn,
    $id_user_ig,
    $id_zone,
    $id_wild_animal,
    $ref_x,
    $ref_z,
    $LANG
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

$payload = [
    'id_battle' => (int) $result['id_battle'],
    'battle_type' => 'party_pve',
    'current_battle_turn' => (int) $result['current_battle_turn']
];

echo json_encode([
    'stato' => 'OK',
    'msg' => 'OK',
    'response' => json_encode($payload)
]);
