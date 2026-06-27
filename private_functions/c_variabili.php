<?php
/*
if (!defined('INITIAL_POSITION_X'))
{
    define('INITIAL_POSITION_X', '0');
}
if (!defined('INITIAL_POSITION_Y'))
{
    define('INITIAL_POSITION_Y', '0');
}
if (!defined('INITIAL_POSITION_Z'))
{
    define('INITIAL_POSITION_Z', '0');
}*/
if (!defined('INITIAL_DIRECTION'))
{
    define('INITIAL_DIRECTION', 'D');
}


$res_costanti = $conn->query("
    select costante, valore from costanti
");
while($row_costanti = $res_costanti->fetch())
{
    $costante = $row_costanti['costante'];
    $valore = $row_costanti['valore'];
    if(!defined($costante))
    {
        define($costante, $valore);
    }
}


?>