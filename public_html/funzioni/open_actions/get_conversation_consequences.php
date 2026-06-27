<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";
$types_of_trigger = "";

$id_user_ig = $_POST['id_user_ig'];
$id_conversation = $_POST['id_conversation'];
$id_option = $_POST['id_option'];
$LANG = $_POST['lang'];


$result = $conn->query("
    select C.id_consequence,C.consequence_type,C.id_ref,C.ref_table,C.num 
    from conversation_consequences CC
    join consequences C ON C.id_consequence = CC.id_consequence 
    where CC.id_conversation = \"$id_conversation\"
    AND CC.id_option = \"$id_option\" 
");
while($row = $result->fetch())
{
    $id_consequence = $row['id_consequence'];
    FUNZIONI::ApplyConsequence($conn,$id_user_ig,$id_consequence,$LANG);
    
    if($types_of_trigger!=""){$types_of_trigger.="#";}
    $types_of_trigger.=$row['consequence_type'];
    
    $singolo_json = json_encode($row);
    if($stringone!=""){$stringone.="#";}
    $stringone.=$singolo_json;
}


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
    "response"=>$stringone,
    "response2"=>$types_of_trigger
);

echo json_encode($riga);

?>