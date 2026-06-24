<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');


$stringone = "";

$result = $conn->query("
    select C.id_conversation,C.id_npc,C.visible,C.title,C.flg_register
        ,D.id_dialog,D.order,D.flg_last,D.flg_options,D.dialog
 from conversations C
    join dialogues D ON D.id_conversation = C.id_conversation 
    
");
while($row = $result->fetch())
{
    $arr = array(
        "id_conversation"=>$row['id_conversation'],
        "id_npc"=>$row['id_npc'],
        "visible"=>$row['visible'],
        "title"=>$row['title'],
        "flg_register"=>$row['flg_register'],
        "id_dialog"=>$row['id_dialog'],
        "order"=>$row['order'],
        "flg_last"=>$row['flg_last'],
        "flg_options"=>$row['flg_options'],
        "dialog"=>$row['dialog']
    );
    $singolo_json = json_encode($arr);
    if($stringone!=""){$stringone.="[_SPLITTER.C_]";}
    $stringone.=$singolo_json;
}

echo json_encode($stringone);
?>