<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/shop.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_shop = isset($_POST['id_shop']) ? (int) $_POST['id_shop'] : 0;
$id_item_type = isset($_POST['id_item_type']) ? (int) $_POST['id_item_type'] : 0;
$quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
$lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';

if ($id_user_ig <= 0 || $id_shop <= 0 || $id_item_type <= 0 || $quantity <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_REQUEST',
        'response' => '{}'
    ]);
    exit;
}

try
{
    $result = animaster_shop_sell($conn, $id_user_ig, $id_shop, $id_item_type, $quantity, $lang);
}
catch (Throwable $e)
{
    error_log('[shop_sell] ' . $e->getMessage());
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
    'msg' => isset($result['error']) ? $result['error'] : 'SELL_FAILED',
    'response' => '{}'
]);
