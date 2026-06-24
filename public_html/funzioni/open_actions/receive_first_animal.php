<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_species = $_POST['id_species'];
$id_element = $_POST['id_element'];
$LANG = $_POST['lang'];

$result_c = $conn->query("
    select id_animal from animals where id_user_ig = \"$id_user_ig\"
");
$count_animals = $result_c->rowCount();

if($count_animals<6)
{    
    $result_hp = $conn->query("
        select base_hp,species$LANG as species from species where id_species = \"$id_species\"
    ");
    $row_hp = $result_hp->fetch();
    $base_hp = intval($row_hp['base_hp']);
    $species = $row_hp['species'];
    $DNA_hp = 1;
    $lvl = 1;
    
    
    $animal_hp = floor(0.01 * (2 * $base_hp + $DNA_hp) * $lvl) + $lvl + 10;
    
    $insert = $conn->query("
        INSERT INTO animals
        ( dt_creazione,  id_species, id_user_ig, lvl, team_position,   dna_atk, dna_def, dna_matk, dna_mdef, dna_hp, dna_acc, dna_eva, dna_cr, dna_spd, pt_atk, pt_def, pt_matk, pt_mdef, pt_hp, pt_acc, pt_eva, pt_cr, pt_spd, xp_atk, xp_def, xp_matk, xp_mdef, xp_hp, xp_acc, xp_eva, xp_cr, xp_spd
        , current_hp, nickname, id_element, max_hp, experience)
        VALUES( now(),  \"$id_species\", \"$id_user_ig\", 1, 1,   1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0
        , \"$animal_hp\", \"$species\", \"$id_element\", \"$animal_hp\", 0);
    
    ");
    
    $notification = "You have received an animal!";
    if($LANG=="_it"){$notification = "Hai ricevuto un animale!";}
    if($LANG=="_pt"){$notification = "Recebeste um animal!";}
    $notification_type = "lvl_up";
    FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
    
    

}
else
{
    $notification = "You have reached the limit of 6 animals!";
    if($LANG=="_it"){$notification = "Hai raggiunto il limite di 6 animali aggratisse!";}
    if($LANG=="_pt"){$notification = "Atingiste o limite de 6 animais!";}
    $notification_type = "lvl_down";
    FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
    
}

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
    
    
    
    
if(!$result_c)
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
