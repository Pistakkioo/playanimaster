<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];
$flg_in_battle = $_POST['flg_usable_in_battle'];
$where_in_battle = "";
if($flg_in_battle=="S")
{
    $where_in_battle = " AND IT.flg_usable_in_battle = 'S' ";
}

$query_launched = "
    select IT.id_item_type,IT.item_type,IT.nome$LANG as nome,IT.descrizione$LANG as descrizione,IT.price,IT.sell_price,IT.use_effect
        ,IT.flg_holdable,IT.flg_tradable,IT.flg_sellable,IT.flg_usable,IT.usable_on,IT.flg_stackable,IT.stack_limit,IT.flg_usable_in_battle
        ,IT.flg_usable_outside_battle,IT.flg_usable_on_alive,IT.flg_usable_on_fainted
        ,TAB.quantita from           
        (  
          select id_item_type, count(1) quantita from items 
          where id_user_ig = \"$id_user_ig\" 
          AND dt_used is null
          group by id_item_type 
        ) TAB
        JOIN item_types IT ON TAB.id_item_type = IT.id_item_type 
        WHERE 1=1
        ".$where_in_battle."
";

$result_items = $conn->query($query_launched);
while($row_items = $result_items->fetch())
{
    $rig = array(
        "id_item_type"=>$row_items['id_item_type']
        ,"item_type"=>$row_items['item_type']
        ,"nome"=>$row_items['nome']
        ,"descrizione"=>$row_items['descrizione']
        ,"price"=>$row_items['price']
        ,"sell_price"=>$row_items['sell_price']
        ,"use_effect"=>$row_items['use_effect']
        ,"flg_holdable"=>$row_items['flg_holdable']
        ,"flg_tradable"=>$row_items['flg_tradable']
        ,"flg_sellable"=>$row_items['flg_sellable']
        ,"flg_usable"=>$row_items['flg_usable']
        ,"usable_on"=>$row_items['usable_on']
        ,"flg_stackable"=>$row_items['flg_stackable']
        ,"stack_limit"=>$row_items['stack_limit']
        ,"quantita"=>$row_items['quantita']
        ,"flg_usable_in_battle"=>$row_items['flg_usable_in_battle']
        ,"flg_usable_outside_battle"=>$row_items['flg_usable_outside_battle']
        ,"flg_usable_on_alive"=>$row_items['flg_usable_on_alive']
        ,"flg_usable_on_fainted"=>$row_items['flg_usable_on_fainted']

    );
    
    $singolo_json = json_encode($rig);//error_log("ROW ITEMS: ".$singolo_json);
    if($stringone!=""){$stringone.="#";}
    $stringone.=$singolo_json;
    
}


if(!$result_items)
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