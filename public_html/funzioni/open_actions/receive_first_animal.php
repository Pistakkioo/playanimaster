<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_species = isset($_POST['id_species']) ? (int) $_POST['id_species'] : 0;
$id_element = isset($_POST['id_element']) ? (int) $_POST['id_element'] : 0;
$LANG = isset($_POST['lang']) ? (string) $_POST['lang'] : '';

if (!class_exists('CONSEQUENCES'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/consequences.php';
}

CONSEQUENCES::ApplyHandler($conn, 'receive_random_animal', $id_user_ig, [
    'id_species' => $id_species,
    'id_element' => $id_element,
], $LANG);

$result_notifications = $conn->query("
    select N.*,case when IT.nome is null
                    then N.item_type
                    else IT.nome
                    end as nome
    from notifications N
    left JOIN item_types IT ON IT.id_item_type = N.id_item_type 
    where N.id_user_ig = \"$id_user_ig\"
    and N.flg_viewed = 'N'
");
while($row_notifications = $result_notifications->fetch())
{
    $rig = array(
       "id_notification"=>$row_notifications['id_notification']
        ,"id_user_ig"=>$row_notifications['id_user_ig']
        ,"id_item_type"=>$row_notifications['id_item_type']
        ,"item_type"=>$row_notifications['item_type']
        ,"nome"=>$row_notifications['nome']
        ,"description"=>$row_notifications['description']
        ,"flg_viewed"=>$row_notifications['flg_viewed']
    );
    
    $singolo_json = json_encode($rig);
    if($stringone!=""){$stringone.="#";}
    $stringone.=$singolo_json;
    
}

$result = $conn->query("
    update notifications 
        set dt_m = now()
       ,flg_viewed = 'S'
    where id_user_ig = \"$id_user_ig\"
    and flg_viewed = 'N'
");

$stato = "OK";
$msg = "OK";
    
$riga = array(
    "stato"=>$stato,
    "msg"=>$msg,
    "response"=>$stringone
);

echo json_encode($riga);

?>