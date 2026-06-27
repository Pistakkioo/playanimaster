<?php
require ($_SERVER['DOCUMENT_ROOT'].'/../private_functions/i.php');


$username = $_POST['username'];
//$display_name = $_POST['display_name'];
$password = $_POST['password'];
$email = $_POST['email'];

$c_pass = md5(md5($password)); 


$result_check_u = $conn->query("
    select count(1) from users where username = \"$username\"
");
$row_check_u = $result_check_u->fetch();
$count_username_exists = intval($row_check_u[0]);

$count_display_name_exists = 0;
/*
$result_check_d = $conn->query("
    select count(1) from users where display_name = \"$display_name\"
");
$row_check_d = $result_check_d->fetch();
$count_display_name_exists = intval($row_check_d[0]);
*/

$result_check_e = $conn->query("
    select count(1) from users where email = \"$email\"
");
$row_check_e = $result_check_e->fetch();
$count_email_exists = intval($row_check_e[0]);

$stato = "OK";
$msg = "OK";


$genders = ["M","F"];
$char_types = ["Actionhero","Astronaut","BasketballPlayer","Boxer","Business","Butler","Carpenter","Casual","Chef","Claus","Clown","ConstructionWorker","Cowboy","Cyclist","Dentist","Diving","Doctor","Eskimo","Explorer","Farmer","Fire","Hazard","Judge","Knight","Lumberjack","Mechanic","Metalhead","Mummy","Ninja","NavalOfficer","Paramedic","Pilot","Pirate","Plumber","Police","Post","Prehistoric","Race","Reporter","Scientist","Skater","Skeleton","Ski","Soldier","Sumo","Superhero","Swimsuit","Tennis","Viking","Weightlifter","Wizard","Yeti","Zombie"];

$gender = $genders[rand(0,1)];
$character_type = $char_types[rand(0,50)];


if($count_username_exists>0)
{
    $stato = "KO";
    $msg = "Username Already Exists";
}
else if($count_display_name_exists>0)
{
    $stato = "KO";
    $msg = "Display Name Already Exists";
}
else if($count_email_exists>0)
{
    $stato = "KO";
    $msg = "Email Already Exists";
}
else
{
    $result = $conn->query("
        insert into users
        (dt_creazione,dt_modifica,username,display_name,password,email)
        VALUES 
        (now(),now(),\"$username\",\"$display_name\",\"$c_pass\",\"$email\")
    ");
    $id_user = $conn->lastInsertId();
    
    $initial_position_z = INITIAL_POSITION_Z;
    $initial_position_x = INITIAL_POSITION_X;
    $initial_position_y = INITIAL_POSITION_Y;
    
    
    /*
    $result2 = $conn->query("
        insert into users_ig
        (id_user,dt_creazione,dt_modifica,id_zone,flg_online,position_x,position_y,position_z,exp_total,level,gender,character_type)
        VALUES 
        (\"$id_user\",now(),now(),1000,'N',\"$initial_position_x\",\"$initial_position_y\",\"$initial_position_z\",0,1,\"$gender\",\"$character_type\")
    ");
    $id_user_ig = $conn->lastInsertId();
    */
    

    if(!$result || !$result2)
    {
        $stato = "KO";
        $msg = "Failed to connect";
    }
    
    
}


if($stato=="OK")
{
    echo 0;
}
else
{
    echo $msg;
}

?>