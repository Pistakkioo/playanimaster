<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = '';

$posx = str_replace(',', '.', isset($_POST['posx']) ? $_POST['posx'] : '0');
$posy = str_replace(',', '.', isset($_POST['posy']) ? $_POST['posy'] : '0');
$posz = str_replace(',', '.', isset($_POST['posz']) ? $_POST['posz'] : '0');
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;
$id_spawn_point = isset($_POST['id_spawn_point']) ? (int) $_POST['id_spawn_point'] : 0;
$LANG = isset($_POST['lang']) ? $_POST['lang'] : '_it';

$stato = 'OK';
$msg = 'OK';

if ($id_zone <= 0 || $id_spawn_point <= 0)
{
    $stato = 'KO';
    $msg = 'Invalid spawn request';
}
else
{
    $result = $conn->query("
        select id_spawn_point, id_zone, x, y, z, radius, number_of_animals
        from spawn_points
        where id_zone = \"$id_zone\"
        AND id_spawn_point = \"$id_spawn_point\"
    ");
    $row = $result ? $result->fetch() : false;

    if (!$row)
    {
        $stato = 'KO';
        $msg = 'Spawn point not found';
    }
    else
    {
        $number_of_animals = (int) $row['number_of_animals'];
        $radius = (int) $row['radius'];
        $x = (int) $row['x'];
        $z = (int) $row['z'];

        if ($number_of_animals > 0 && $radius > 0)
        {
            $result_c = $conn->query("
                select id_wild_animal from wild_animals
                where id_zone = \"$id_zone\"
                AND id_spawn_point = \"$id_spawn_point\"
                AND id_battle is null
            ");
            $count = $result_c ? $result_c->rowCount() : 0;

            if ($count < $number_of_animals)
            {
                $diff = $number_of_animals - $count;
                FUNZIONI::SpawnAnimals($conn, $id_zone, $id_spawn_point, $diff, $radius, $x, $z);
            }
        }
    }
}

$riga = array(
    'stato' => $stato,
    'msg' => $msg,
    'response' => $stringone
);

echo json_encode($riga);
?>