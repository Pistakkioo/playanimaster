<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/pvp.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;

if ($id_user_ig <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_USER',
        'response' => '{}'
    ]);
    exit;
}

try
{
    $result = animaster_pvp_poll($conn, $id_user_ig);
}
catch (Throwable $e)
{
    error_log('[poll_duel] ' . $e->getMessage());
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'SERVER_ERROR',
        'response' => '{}'
    ]);
    exit;
}

if (!empty($result['ok']))
{
    echo json_encode([
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => json_encode($result, JSON_UNESCAPED_UNICODE)
    ]);
    exit;
}

echo json_encode([
    'stato' => 'KO',
    'msg' => isset($result['error']) ? $result['error'] : 'POLL_FAILED',
    'response' => '{}'
]);
