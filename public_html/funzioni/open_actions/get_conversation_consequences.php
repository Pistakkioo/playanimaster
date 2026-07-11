<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

if (!class_exists('PLAYER_CONVERSATIONS'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/player_conversations.php';
}

$stringone = "";
$types_of_trigger = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_conversation = isset($_POST['id_conversation']) ? (int) $_POST['id_conversation'] : 0;
$id_option = isset($_POST['id_option']) ? (int) $_POST['id_option'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '';

$already_finished = PLAYER_CONVERSATIONS::isFinished($conn, $id_user_ig, $id_conversation);

if (!$already_finished)
{
    $stmt = $conn->prepare('
        SELECT C.consequence_type,
               CC.id_conversation_consequence,
               CC.id_consequence,
               CC.id_ref,
               CC.ref_table,
               CC.ref_description,
               CC.num,
               CC.params_json
        FROM conversation_consequences CC
        JOIN consequences C ON C.id_consequence = CC.id_consequence
        WHERE CC.id_conversation = :id_conversation
          AND CC.id_option = :id_option
    ');
    $stmt->execute([
        ':id_conversation' => $id_conversation,
        ':id_option' => $id_option
    ]);

    $result = true;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
    {
        FUNZIONI::ApplyConsequence($conn, $id_user_ig, $row, $LANG);

        if ($types_of_trigger != "")
        {
            $types_of_trigger .= "#";
        }

        $types_of_trigger .= $row['consequence_type'];

        $singolo_json = json_encode($row);

        if ($stringone != "")
        {
            $stringone .= "#";
        }

        $stringone .= $singolo_json;
    }

    if (PLAYER_CONVERSATIONS::shouldRegisterOnFinish($conn, $id_conversation, $id_option))
    {
        PLAYER_CONVERSATIONS::registerFinished($conn, $id_user_ig, $id_conversation, $id_option);

        if (!class_exists('QUESTS'))
        {
            require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/quests.php';
        }

        QUESTS::onConversationFinished($conn, $id_user_ig, $id_conversation, $LANG);
    }
}
else
{
    $result = true;
}

if (!$result)
{
    $stato = "KO";
    $msg = "KO";
}
else
{
    $stato = "OK";
    $msg = "OK";
}

$riga = array(
    "stato" => $stato,
    "msg" => $msg,
    "response" => $stringone,
    "response2" => $types_of_trigger
);

echo json_encode($riga);

?>
