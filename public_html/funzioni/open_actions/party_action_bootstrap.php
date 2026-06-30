<?php

function animaster_party_open_action($callback)
{
    require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/party.php';

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
        $result = $callback($conn, $id_user_ig, $_POST);
    }
    catch (Throwable $e)
    {
        error_log('[party_action] ' . $e->getMessage());
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
        'msg' => isset($result['error']) ? $result['error'] : 'FAILED',
        'response' => '{}'
    ]);
}
