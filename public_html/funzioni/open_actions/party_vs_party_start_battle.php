<?php
/**
 * Dev/smoke start for party vs party (005c Phase 5).
 * Challenger must be party A leader; target must be party B leader.
 * POST: id_user_ig, id_target_user_ig, id_zone, position_x, position_z, lang
 */
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/party_vs_party.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_target_user_ig = isset($_POST['id_target_user_ig']) ? (int) $_POST['id_target_user_ig'] : 0;
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;
$ref_x = isset($_POST['position_x']) ? (float) $_POST['position_x'] : 0.0;
$ref_z = isset($_POST['position_z']) ? (float) $_POST['position_z'] : 0.0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '_it';

if ($id_user_ig <= 0 || $id_target_user_ig <= 0 || $id_zone <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_START',
        'response' => ''
    ]);
    exit;
}

$result = animaster_party_vs_party_start(
    $conn,
    $id_user_ig,
    $id_target_user_ig,
    $id_zone,
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
    'battle_type' => 'party_vs_party',
    'current_battle_turn' => (int) $result['current_battle_turn']
];

echo json_encode([
    'stato' => 'OK',
    'msg' => 'OK',
    'response' => json_encode($payload)
]);
