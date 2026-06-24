<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/trade.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_trade = isset($_POST['id_trade']) ? (int) $_POST['id_trade'] : 0;

if ($id_user_ig <= 0 || $id_trade <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_REQUEST',
        'response' => ''
    ]);
    exit;
}

try
{
    $result = animaster_trade_cancel($conn, $id_user_ig, $id_trade);
}
catch (Throwable $e)
{
    error_log('[cancel_trade] ' . $e->getMessage());
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
        'response' => json_encode($result, JSON_UNESCAPED_UNICODE)
    ]);
    exit;
}

echo json_encode([
    'stato' => 'KO',
    'msg' => isset($result['error']) ? $result['error'] : 'CANCEL_FAILED',
    'response' => ''
]);
