<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');


$username = $_POST['username'];
$password = $_POST['password'];

$id_zone = $_POST['id_zone'];
$position_x = $_POST['position_x'];
$position_y = $_POST['position_y'];
$direction = $_POST['direction'];

$c_pass = md5(md5($password));

$stato = "OK";
$msg = "OK";

$result_U = $conn->query("
    select id_user 
    from users 
    where username = \"$username\"
    AND password = \"$c_pass\"
");
$row_U = $result_U->fetch();
$id_user = $row_U[0];

$result = $conn->query("
    update users_ig
        SET id_zone = \"$id_zone\"
            ,flg_online = 'N'
            ,last_online = now()
            ,position_x = \"$position_x\"
            ,position_y = \"$position_y\"
            ,direction = \"$direction\"
    WHERE id_user = \"$id_user\"
");

if(!$result)
{
    echo "Something went wrong";
}
else
{
    echo 0;
}



?>