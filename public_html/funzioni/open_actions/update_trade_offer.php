<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/trade.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_trade = isset($_POST['id_trade']) ? (int) $_POST['id_trade'] : 0;
$gold = isset($_POST['gold']) ? (int) $_POST['gold'] : 0;
$items_json = isset($_POST['items']) ? (string) $_POST['items'] : '[]';

if ($id_user_ig <= 0 || $id_trade <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_REQUEST',
        'response' => ''
    ]);
    exit;
}

$items = json_decode($items_json, true);

if (!is_array($items))
{
    $items = [];
}

try
{
    $result = animaster_trade_update_offer($conn, $id_user_ig, $id_trade, $gold, $items);
}
catch (Throwable $e)
{
    error_log('[update_trade_offer] ' . $e->getMessage());
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
    'msg' => isset($result['error']) ? $result['error'] : 'UPDATE_FAILED',
    'response' => ''
]);
