<?php
require __DIR__ . '/includes/session_auth.php';

header('Content-Type: application/json; charset=UTF-8');

animaster_session_start();

if (!animaster_is_logged_in())
{
    echo json_encode([
        'ok' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

if (empty($_SESSION['animaster_id_user_ig']))
{
    echo json_encode([
        'ok' => true,
        'message' => 'No active character'
    ]);
    exit;
}

$id_user_ig = (int) $_SESSION['animaster_id_user_ig'];
animaster_mark_character_offline(animaster_get_conn(), $id_user_ig);

echo json_encode([
    'ok' => true,
    'id_user_ig' => $id_user_ig
]);
