<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];


$query_launched = "
    select id_animal,base_atk,base_def,base_matk,base_mdef,base_hp,base_acc,base_eva,base_cr,base_spd
		  ,dna_atk,dna_def,dna_matk,dna_mdef,dna_hp,dna_acc,dna_eva,dna_cr,dna_spd
          ,pt_atk,pt_def,pt_matk,pt_mdef,pt_hp,pt_acc,pt_eva,pt_cr,pt_spd
          ,xp_atk,xp_def,xp_matk,xp_mdef,xp_hp,xp_acc,xp_eva,xp_cr,xp_spd
          ,lvl, current_hp, max_hp,L.species$LANG as species,L.species as species_key,L.id_species,E.id_element,E.element$LANG as element,E.color as element_color
          ,nickname,experience,team_position,id_user_ig 
        from animals A 
        join species L ON L.id_species = A.id_species 
        left join elements E ON E.id_element = A.id_element
    WHERE team_position > 0
    AND id_user_ig = \"$id_user_ig\"
    order by team_position
";

$result = $conn->query($query_launched);
$rows = [];

while ($row_user_animal = $result->fetch())
{
    if (!class_exists('BUFFS'))
    {
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
    }

    $row_user_animal['active_buffs'] = BUFFS::fetchDisplayForAnimal(
        $conn,
        (int) $row_user_animal['id_animal'],
        (int) $row_user_animal['id_user_ig'],
        $LANG
    );
    $row_user_animal['current_stat_sheet'] = BUFFS::fetchTeamCurrentStatSheet(
        $conn,
        $row_user_animal,
        $LANG
    );
    $row_user_animal = BUFFS::normalizeTeamAnimalHpRow($conn, $row_user_animal, true);

    $rows[] = $row_user_animal;
}

$stringone = json_encode($rows);

if ($stringone === false)
{
    $stringone = '[]';
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
    "query_launched"=>$query_launched,
    "response"=>$stringone
);

echo json_encode($riga);

?>