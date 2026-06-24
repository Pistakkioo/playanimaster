<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';

$stringone = "";

$posx = str_replace(',','.',$_POST['posx']);
$posy = str_replace(',','.',$_POST['posy']);
$posz = str_replace(',','.',$_POST['posz']);
$T_posx = str_replace(',','.',$_POST['T_posx']);
$T_posy = str_replace(',','.',$_POST['T_posy']);
$T_posz = str_replace(',','.',$_POST['T_posz']);
$id_zone = $_POST['id_zone'];
$id_user = isset($_POST['id_user']) ? (int) $_POST['id_user'] : 0;
$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];


$stato = 'OK';
$msg = 'OK';

$id_user_ig = animaster_update_character_presence(
    $conn,
    $id_user,
    $id_user_ig,
    $posx,
    $posy,
    $posz,
    $T_posx,
    $T_posy,
    $T_posz
);


if ($id_user_ig <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'Character not found',
        'response' => $stringone
    ]);
    exit;
}


$result_set_others_offline = $conn->query("
    update users_ig 
    set flg_online = 'N'
    where flg_online = 'S'
    AND id_user_ig != \"$id_user_ig\"
    AND last_online is not null
    AND TIMESTAMPDIFF(second, last_online, now()) > 10
");


$result_exp = $conn->query("
    select exp_total from users_ig where id_user_ig = \"$id_user_ig\"
");
$row_exp = $result_exp->fetch(PDO::FETCH_ASSOC);
$exp = $row_exp ? intval($row_exp['exp_total']) : 0;


$result_others = $conn->query("
    select UI.id_user_ig id_player
            , UI.display_name displayName
            , UI.gender
            , UI.character_type
            , UI.move_speed
            , UI.position_x serverPositionX
            , UI.position_y serverPositionY
            , UI.position_z serverPositionZ
            , UI.target_position_x targetPositionX
            , UI.target_position_y targetPositionY
            , UI.target_position_z targetPositionZ
    from users_ig UI
    where UI.id_zone = \"$id_zone\"
    AND UI.flg_online = 'S'
    AND UI.id_user_ig != \"$id_user_ig\"
");

if ($result_others)
{
    while($row_others = $result_others->fetch(PDO::FETCH_ASSOC))
    {
        $id_player = $row_others['id_player'];
        $serverPositionX = $row_others['serverPositionX'];
        $serverPositionY = $row_others['serverPositionY'];
        $serverPositionZ = $row_others['serverPositionZ'];
        $displayName = $row_others['displayName'];
        $gender = $row_others['gender'];
        $character_type = $row_others['character_type'];
        $move_speed = $row_others['move_speed'];
        $objectName  = "OP_".$id_player;
        
        $rig = array(
         "id_player"=>$id_player,
         "serverPositionX"=>$serverPositionX,
         "serverPositionY"=>$serverPositionY,
         "serverPositionZ"=>$serverPositionZ,
         "targetPositionX"=>$serverPositionX,
         "targetPositionY"=>$serverPositionY,
         "targetPositionZ"=>$serverPositionZ,
         "displayName"=>$displayName,
         "gender"=>$gender,
         "character_type"=>$character_type,
         "move_speed"=>$move_speed,
         "objectName"=>$objectName
        );
        $singolo_json = json_encode($rig);
        if($stringone!=""){$stringone.="#";}
        $stringone.=$singolo_json;
    }
}
else
{
    $stato = "KO";
    $msg = "KO";
}

echo json_encode([
    'stato' => $stato,
    'msg' => $msg,
    'response' => $stringone
]);

?>



