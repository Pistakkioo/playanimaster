<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/shop.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_shop = isset($_POST['id_shop']) ? (int) $_POST['id_shop'] : 0;
$lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';

if ($id_user_ig <= 0 || $id_shop <= 0)
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
    $result = animaster_shop_fetch($conn, $id_user_ig, $id_shop, $lang);
}
catch (Throwable $e)
{
    error_log('[get_shop] ' . $e->getMessage());
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
    'msg' => isset($result['error']) ? $result['error'] : 'GET_SHOP_FAILED',
    'response' => '{}'
]);
