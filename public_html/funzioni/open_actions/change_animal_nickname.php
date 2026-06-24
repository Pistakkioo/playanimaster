<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];

$id_animal = $_POST['id_animal'];
$nickname = $_POST['nickname'];

$query_launched = "
    update animals 
        set nickname = \"$nickname\"
           ,dt_modifica = now()
        where id_animal = \"$id_animal\"
        and id_user_ig = \"$id_user_ig\"
";

$result = $conn->query($query_launched);







if(!$result)
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
    "stato"=>$stato,
    "msg"=>$msg,
    "query_launched"=>$query_launched,
    "response"=>$stringone
);

echo json_encode($riga);

?>