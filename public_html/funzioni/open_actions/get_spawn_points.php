<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = '';

$posx = str_replace(',', '.', isset($_POST['posx']) ? $_POST['posx'] : '0');
$posy = str_replace(',', '.', isset($_POST['posy']) ? $_POST['posy'] : '0');
$posz = str_replace(',', '.', isset($_POST['posz']) ? $_POST['posz'] : '0');
$id_zone = isset($_POST['id_zone']) ? (int) $_POST['id_zone'] : 0;
$LANG = isset($_POST['lang']) ? $_POST['lang'] : '_it';

$result = false;

if ($id_zone > 0)
{
    $result = $conn->query("
        select id_spawn_point, id_zone, x, y, z, radius, number_of_animals
        from spawn_points
        where id_zone = \"$id_zone\"
    ");

    if ($result)
    {
        while ($row = $result->fetch())
        {
            $rig = array(
                'id_spawn_point' => $row['id_spawn_point'],
                'id_zone' => $row['id_zone'],
                'x' => $row['x'],
                'y' => $row['y'],
                'z' => $row['z'],
                'radius' => $row['radius'],
                'number_of_animals' => $row['number_of_animals']
            );
            $singolo_json = json_encode($rig);

            if ($stringone != '')
            {
                $stringone .= '#';
            }

            $stringone .= $singolo_json;
        }
    }
}

if (!$result)
{
    $stato = 'KO';
    $msg = 'KO';
}
else
{
    $stato = 'OK';
    $msg = 'OK';
}

$riga = array(
    'stato' => $stato,
    'msg' => $msg,
    'response' => $stringone
);

echo json_encode($riga);
?>