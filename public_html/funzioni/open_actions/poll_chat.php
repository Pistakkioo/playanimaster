<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/chat.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$since_id = isset($_POST['since_id']) ? (int) $_POST['since_id'] : 0;
$since_dt = isset($_POST['since_dt']) ? (string) $_POST['since_dt'] : '';
$posx = isset($_POST['posx']) ? (float) str_replace(',', '.', $_POST['posx']) : null;
$posz = isset($_POST['posz']) ? (float) str_replace(',', '.', $_POST['posz']) : null;
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;

if ($id_user_ig <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_USER',
        'response' => '[]'
    ]);
    exit;
}

try
{
    $result = animaster_chat_poll($conn, $id_user_ig, $since_id, $posx, $posz, $id_zone, $since_dt);
}
catch (Throwable $e)
{
    error_log('[poll_chat] ' . $e->getMessage());
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'SERVER_ERROR',
        'response' => '[]'
    ]);
    exit;
}

if (!empty($result['ok']))
{
    echo json_encode([
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => json_encode($result['messages'], JSON_UNESCAPED_UNICODE)
    ]);
    exit;
}

echo json_encode([
    'stato' => 'KO',
    'msg' => isset($result['error']) ? $result['error'] : 'POLL_FAILED',
    'response' => '[]'
]);
