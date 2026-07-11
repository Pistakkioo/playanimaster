<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

if (!class_exists('QUESTS'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/quests.php';
}

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '';
if ($LANG !== '' && $LANG[0] !== '_')
{
    $LANG = '_' . $LANG;
}

$rows = QUESTS::fetchQuestLog($conn, $id_user_ig, $LANG);

$stringone = json_encode($rows);

if ($stringone === false)
{
    $stringone = '[]';
}

$riga = array(
    "stato" => "OK",
    "msg" => "OK",
    "response" => $stringone
);

echo json_encode($riga);

?>
