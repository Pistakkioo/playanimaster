<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/chat.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$message = isset($_POST['message']) ? (string) $_POST['message'] : '';

if ($id_user_ig <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_USER',
        'response' => ''
    ]);
    exit;
}

try
{
    $result = animaster_chat_send($conn, $id_user_ig, $message);
}
catch (Throwable $e)
{
    error_log('[send_chat] ' . $e->getMessage());
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'SERVER_ERROR',
        'response' => ''
    ]);
    exit;
}

if (!empty($result['ok']))
{
    echo json_encode([
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => json_encode($result['message'], JSON_UNESCAPED_UNICODE)
    ]);
    exit;
}

echo json_encode([
    'stato' => 'KO',
    'msg' => isset($result['error']) ? $result['error'] : 'SEND_FAILED',
    'response' => ''
]);
