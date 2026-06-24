<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];

$id_item_type = $_POST['id_item_type'];
$id_animal  = $_POST['id_animal'];

$result_check_available = $conn->query("
    select * from items 
    where id_item_type = \"$id_item_type\" 
    AND id_user_ig = \"$id_user_ig\"
    AND (flg_held = 'N' or flg_held is null)
    AND dt_used is null
");
if($result_check_available->rowCount()>0)
{
    $result_item_type = $conn->query("
        select * from item_types where id_item_type = \"$id_item_type\"
    ");
    $row_item_type = $result_item_type->fetch();
    if($row_item_type['item_type']=="potions")
    {
        $hp_recover = $row_item_type['use_effect'];
        $conn->query("
            update animals set current_hp = case when current_hp + \"$hp_recover\" > max_hp 
    									 then max_hp 
    									 ELSE current_hp + \"$hp_recover\"
    									 end
    		where id_animal = \"$id_animal\"
        ");
        
        $conn->query("
            update items set dt_used = now(),dt_modifica = now() 
            where id_item_type = \"$id_item_type\"
            AND id_user_ig = \"$id_user_ig\"
            AND (flg_held = 'N' or flg_held is null)
            AND dt_used is null
            order by dt_creazione
            limit 1 
        ");
    }    
}



if(!$result_check_available)
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
    "response"=>$stringone
);

echo json_encode($riga);

?>