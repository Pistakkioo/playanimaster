<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/trade.php';

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$lang = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
$lang_suffix = '';

if ($lang !== '' && $lang[0] !== '_')
{
    $lang_suffix = '_' . $lang;
}
elseif ($lang !== '')
{
    $lang_suffix = $lang;
}

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
    $result = animaster_trade_poll($conn, $id_user_ig, $lang_suffix);
}
catch (Throwable $e)
{
    error_log('[poll_trade] ' . $e->getMessage());
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
