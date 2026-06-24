<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$posx = str_replace(',','.',$_POST['posx']);
$posy = str_replace(',','.',$_POST['posy']);
$posz = str_replace(',','.',$_POST['posz']);
$id_zone = $_POST['id_zone'];
$id_user = $_POST['id_user'];
$LANG = $_POST['lang'];
if ($LANG != "")
{
    if ($LANG[0] != "_")
    {
        $LANG = "_" . $LANG;
    }
}

$result = $conn->query("
    select WA.id_wild_animal,WA.id_species,WA.level,WA.id_element,WA.pos_x,WA.pos_y,WA.pos_z
        ,L.species$LANG as species
        ,L.species as species_key
        ,E.element$LANG as element
        ,E.color as element_color
    from wild_animals WA
    left join species L ON L.id_species = WA.id_species
    left join elements E ON E.id_element = WA.id_element
    where WA.id_zone = \"$id_zone\"
    AND WA.pos_x > $posx - 100
    AND WA.pos_x < $posx + 100
    AND WA.pos_z > $posz - 100
    AND WA.pos_z < $posz + 100
    AND WA.pos_y > $posy - 100
    AND WA.pos_y < $posy + 100
    AND WA.id_battle is null
");

if (!$result)
{
    $stato = "KO";
    $msg = "KO";
    $stringone = "[]";
}
else
{
    $rows = array();

    while ($row = $result->fetch())
    {
        $rows[] = array(
            "id_wild_animal"=>$row['id_wild_animal'],
            "id_species"=>$row['id_species'],
            "level"=>$row['level'],
            "species"=>$row['species'],
            "species_key"=>$row['species_key'],
            "element"=>$row['element'],
            "element_color"=>$row['element_color'],
            "id_element"=>$row['id_element'],
            "pos_x"=>$row['pos_x'],
            "pos_y"=>$row['pos_y'],
            "pos_z"=>$row['pos_z']
        );
    }

    $stringone = json_encode($rows);
    if ($stringone === false)
    {
        $stringone = "[]";
    }

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