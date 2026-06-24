<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];
$id_active_animal = $_POST['id_active_animal'];
$lvl = $_POST['lvl'];


$result_species = $conn->query("
    select id_species,id_element from animals where id_animal = \"$id_active_animal\"
");
$row_species = $result_species->fetch();
$id_species = $row_species['id_species'];
$id_element = $row_species['id_element'];


$result = $conn->query("
    select A.id_ability,A.ability$LANG as ability,A.descrizione$LANG as descrizione,A.accuracy,A.power,A.m_power,A.id_element,E.element$LANG as element,A.effect,A.effect_chance
        from abilities A
        join species_abilities LA ON LA.id_ability = A.id_ability
        left join elements E ON E.id_element = A.id_element
    where LA.id_species = \"$id_species\"
    AND LA.unlock_lvl <= \"$lvl\"
    AND (LA.id_element = \"$id_element\" OR LA.id_element=0)
");
while($row = $result->fetch())
{
    $rig = array(
        "id_ability"=>$row['id_ability'],
        "ability"=>$row['ability'],
        "descrizione"=>$row['descrizione'],
        "accuracy"=>$row['accuracy'],
        "power"=>$row['power'],
        "m_power"=>$row['m_power'],
        "element"=>$row['element'],
        "id_element"=>$row['id_element'],
        "effect"=>$row['effect'],
        "effect_chance"=>$row['effect_chance']

    );
    
    $singolo_json = json_encode($rig);
    if($stringone!=""){$stringone.="#";}
    $stringone.=$singolo_json;
    
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