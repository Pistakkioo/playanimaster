<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$posx = isset($_POST['posx']) ? $_POST['posx'] : '0';
$posy = isset($_POST['posy']) ? $_POST['posy'] : '0';
$posz = isset($_POST['posz']) ? $_POST['posz'] : '0';
$id_zone = isset($_POST['id_zone']) ? $_POST['id_zone'] : '0';
$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_user = isset($_POST['id_user']) ? (int) $_POST['id_user'] : 0;
$LANG = $_POST['lang'];

$where_character = "id_user_ig = \"$id_user_ig\"";

$result_heal = $conn->query("
    update animals set current_hp = max_hp 
    where id_user_ig = \"$id_user_ig\"
");

if ($id_user_ig > 0)
{
    $result_upd = $conn->query("
        update users_ig
        set position_x_last_recover = \"$posx\"
           ,position_y_last_recover = \"$posy\"
           ,position_z_last_recover = \"$posz\"
           ,id_zone_last_recover = \"$id_zone\"
        WHERE $where_character
    ");
}

$profile_where = ($id_user_ig > 0)
    ? "UI.id_user_ig = \"$id_user_ig\""
    : "U.id_user = \"$id_user\"";

$result = $conn->query("
    select UI.id_user_ig
          ,UI.id_user
    	  ,UI.exp_total
    	  ,UI.id_zone 
    	  ,UI.`level`
    	  ,UI.position_x
          ,UI.position_y
          ,UI.position_z
          ,UI.`direction`
          ,UI.position_x_last_recover
          ,UI.position_y_last_recover
          ,UI.position_z_last_recover
          ,UI.id_zone_last_recover
          ,UI.display_name
    	  ,U.username 
    	  ,U.email
          ,Z.scene_name
    from users_ig UI 
        join users U ON U.id_user = UI.id_user
        left join zones Z ON Z.id_zone = UI.id_zone
    WHERE $profile_where
    ORDER BY UI.dt_creazione ASC
    LIMIT 1
");
$count = $result->rowCount();
$row = $result->fetch();

$stato = "OK";
$msg = "OK";

if(!$result)
{
    $stato = "KO";
    $msg = "Failed to connect";
}
else if($count==0)
{
    $stato = "KO";
    $msg = "Incorrect login";
}
else if ($id_user_ig > 0)
{
    $result_upd = $conn->query("
        update users_ig 
        set last_online = now()
           ,flg_online = 'S'
        where id_user_ig = \"$id_user_ig\"
    ");
}

$riga = array(
    "stato"=>$stato,
    "msg"=>$msg,
    "response"=>json_encode($row)
);

echo json_encode($riga);

?>
