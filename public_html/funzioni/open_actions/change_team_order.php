<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];
$id_animal_1 = "";$id_animal_2="";$id_animal_3 = "";$id_animal_4="";$id_animal_5 = "";$id_animal_6="";
$pos_1="";$pos_2="";$pos_3="";$pos_4="";$pos_5="";$pos_6="";

if(isset($_POST['id_animal_1']))$id_animal_1 = $_POST['id_animal_1'];
if(isset($_POST['id_animal_2']))$id_animal_2 = $_POST['id_animal_2'];
if(isset($_POST['id_animal_3']))$id_animal_3 = $_POST['id_animal_3'];
if(isset($_POST['id_animal_4']))$id_animal_4 = $_POST['id_animal_4'];
if(isset($_POST['id_animal_5']))$id_animal_5 = $_POST['id_animal_5'];
if(isset($_POST['id_animal_6']))$id_animal_6 = $_POST['id_animal_6'];

if(isset($_POST['xpos_1']))$pos_1 = floatval(str_replace(',','.',$_POST['xpos_1']));
if(isset($_POST['xpos_2']))$pos_2 = floatval(str_replace(',','.',$_POST['xpos_2']));
if(isset($_POST['xpos_3']))$pos_3 = floatval(str_replace(',','.',$_POST['xpos_3']));
if(isset($_POST['xpos_4']))$pos_4 = floatval(str_replace(',','.',$_POST['xpos_4']));
if(isset($_POST['xpos_5']))$pos_5 = floatval(str_replace(',','.',$_POST['xpos_5']));
if(isset($_POST['xpos_6']))$pos_6 = floatval(str_replace(',','.',$_POST['xpos_6']));

if($id_animal_1!="" && $pos_1!="")
{
    $count_lowers = 1;
    if($pos_2!="" && $pos_2<$pos_1){$count_lowers++;}
    if($pos_3!="" && $pos_3<$pos_1){$count_lowers++;}
    if($pos_4!="" && $pos_4<$pos_1){$count_lowers++;}
    if($pos_5!="" && $pos_5<$pos_1){$count_lowers++;}
    if($pos_6!="" && $pos_6<$pos_1){$count_lowers++;}
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_1\"
        and id_user_ig = \"$id_user_ig\"
    ");
}


if($id_animal_2!="" && $pos_2!="")
{
    $count_lowers = 1;
    if($pos_1!="" && $pos_1<$pos_2){$count_lowers++;}
    if($pos_3!="" && $pos_3<$pos_2){$count_lowers++;}
    if($pos_4!="" && $pos_4<$pos_2){$count_lowers++;}
    if($pos_5!="" && $pos_5<$pos_2){$count_lowers++;}
    if($pos_6!="" && $pos_6<$pos_2){$count_lowers++;}    
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_2\"
        and id_user_ig = \"$id_user_ig\"
    ");
}
if($id_animal_3!="" && $pos_3!="")
{
    $count_lowers = 1;
    if($pos_1!="" && $pos_1<$pos_3){$count_lowers++;}
    if($pos_2!="" && $pos_2<$pos_3){$count_lowers++;}
    if($pos_4!="" && $pos_4<$pos_3){$count_lowers++;}
    if($pos_5!="" && $pos_5<$pos_3){$count_lowers++;}
    if($pos_6!="" && $pos_6<$pos_3){$count_lowers++;}
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_3\"
        and id_user_ig = \"$id_user_ig\"
    ");
}
if($id_animal_4!="" && $pos_4!="")
{
    $count_lowers = 1;
    if($pos_1!="" && $pos_1<$pos_4){$count_lowers++;}
    if($pos_2!="" && $pos_2<$pos_4){$count_lowers++;}
    if($pos_3!="" && $pos_3<$pos_4){$count_lowers++;}
    if($pos_5!="" && $pos_5<$pos_4){$count_lowers++;}
    if($pos_6!="" && $pos_6<$pos_4){$count_lowers++;}
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_4\"
        and id_user_ig = \"$id_user_ig\"
    ");
}
if($id_animal_5!="" && $pos_5!="")
{
    $count_lowers = 1;
    if($pos_1!="" && $pos_1<$pos_5){$count_lowers++;}
    if($pos_2!="" && $pos_2<$pos_5){$count_lowers++;}
    if($pos_3!="" && $pos_3<$pos_5){$count_lowers++;}
    if($pos_4!="" && $pos_4<$pos_5){$count_lowers++;}
    if($pos_6!="" && $pos_6<$pos_5){$count_lowers++;}
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_5\"
        and id_user_ig = \"$id_user_ig\"
    ");
}
if($id_animal_6!="" && $pos_6!="")
{
    $count_lowers = 1;
    if($pos_1!="" && $pos_1<$pos_6){$count_lowers++;}
    if($pos_2!="" && $pos_2<$pos_6){$count_lowers++;}
    if($pos_3!="" && $pos_3<$pos_6){$count_lowers++;}
    if($pos_4!="" && $pos_4<$pos_6){$count_lowers++;}
    if($pos_5!="" && $pos_5<$pos_6){$count_lowers++;}
    
    $result = $conn->query("
        update animals 
            set team_position = \"$count_lowers\" 
                ,dt_modifica = now()
        where id_animal = \"$id_animal_6\"
        and id_user_ig = \"$id_user_ig\"
    ");
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
    "response"=>$stringone
);

echo json_encode($riga);

?>