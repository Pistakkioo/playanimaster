<?php
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';

$conn = animaster_get_conn();

$id_user = isset($_POST['id_user']) ? (int) $_POST['id_user'] : 0;
$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;

$stato = 'OK';
$msg = 'OK';
$envelope = [
    'stato' => 'KO',
    'msg' => 'Invalid request',
    'response' => json_encode(null),
    'response2' => '{}',
    'response3' => ''
];

if ($id_user <= 0 || $id_user_ig <= 0)
{
    $envelope['msg'] = 'Missing character';
    echo json_encode($envelope);
    exit;
}

$row = animaster_fetch_character_profile_row($conn, $id_user, $id_user_ig);

if (!$row)
{
    $envelope['msg'] = 'Character not found';
    echo json_encode($envelope);
    exit;
}

animaster_mark_character_online($conn, $id_user_ig);
$envelope = animaster_build_login_envelope($conn, $row);

echo json_encode($envelope);
