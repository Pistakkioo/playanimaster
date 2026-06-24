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
          ,lvl, current_hp, max_hp,L.species$LANG as species,L.id_species,E.id_element,E.element$LANG as element
          ,nickname,experience,team_position,id_user_ig 
        from animals A 
        join species L ON L.id_species = A.id_species 
        left join elements E ON E.id_element = A.id_element
    WHERE team_position > 0
    AND id_user_ig = \"$id_user_ig\"
    order by team_position
";

$result = $conn->query($query_launched);
while($row_user_animal=$result->fetch())
{
    $singolo_json = json_encode($row_user_animal);
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
    "query_launched"=>$query_launched,
    "response"=>$stringone
);

echo json_encode($riga);

?>