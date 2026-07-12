<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$motive = $_POST['motive'];
$id_active_animal = $_POST['id_active_animal'];
$id_item_type_selected = $_POST['id_item_type_selected'];
$LANG = $_POST['lang'];

$where_motive = "";
if($motive=="switch")
{
    $where_motive = " AND id_animal != \"$id_active_animal\" /*AND current_hp > 0*/ ";
}
else if ($motive=="use_item")
{
    $result_item = $conn->query("
        select * from item_types where id_item_type = \"$id_item_type_selected\"
    ");
    $row_item = $result_item->fetch();
    $usable_on_alive = $row_item['flg_usable_on_alive'];
    $usable_on_fainted = $row_item['flg_usable_on_fainted'];
    $usable_outside_battle = $row_item['flg_usable_outside_battle']; // like reviv
    
    if($usable_outside_battle=="N")
    {
        $where_motive.=" AND id_animal = \"$id_active_animal\" ";
    }
    
    if($usable_on_alive=="S" && $usable_on_fainted=="S")
    {
        // USABLE IN ALL ANIMALS
    }
    else if($usable_on_alive=="S" && $usable_on_fainted=="N")
    {
        $where_motive.=" AND current_hp > 0  ";
    }
    else if($usable_on_alive=="N" && $usable_on_fainted=="S")
    {
        $where_motive.=" AND current_hp <= 0  ";
    }
    else
    {
        $where_motive.=" AND 1=2 ";// CANNOT BE USED IN ANIMALS
    }
}


$result = $conn->query("
    select id_animal,id_user_ig,base_atk,base_def,base_matk,base_mdef,base_hp,base_acc,base_eva,base_cr,base_spd
		  ,dna_atk,dna_def,dna_matk,dna_mdef,dna_hp,dna_acc,dna_eva,dna_cr,dna_spd
          ,pt_atk,pt_def,pt_matk,pt_mdef,pt_hp,pt_acc,pt_eva,pt_cr,pt_spd
          ,xp_atk,xp_def,xp_matk,xp_mdef,xp_hp,xp_acc,xp_eva,xp_cr,xp_spd
          ,lvl, current_hp, max_hp,L.species$LANG as species,L.id_species,E.id_element,E.element$LANG as element,E.color as element_color,nickname 
        from animals A 
        join species L ON L.id_species = A.id_species 
        left join elements E ON E.id_element = A.id_element
    WHERE team_position > 0
    AND id_user_ig = \"$id_user_ig\"
    ".$where_motive."
    
");
$rows = [];

while ($row_user_animal = $result->fetch())
{
    if (!class_exists('BUFFS'))
    {
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
    }

    $row_user_animal = BUFFS::normalizeTeamAnimalHpRow($conn, $row_user_animal, true);

    $id_element = $row_user_animal['id_element'];
    $element = $row_user_animal['element'];
    $lvl = $row_user_animal['lvl'];
    $species = $row_user_animal['species'];
    $id_species = $row_user_animal['id_species'];
    $nick = $row_user_animal['nickname'];
    $user_animal_current_hp = $row_user_animal['current_hp'];
    
    $base_atk = $row_user_animal['base_atk'];$base_def = $row_user_animal['base_def'];$base_matk = $row_user_animal['base_matk'];
    $base_mdef = $row_user_animal['base_mdef'];$base_hp = $row_user_animal['base_hp'];$base_spd = $row_user_animal['base_spd'];
    
    $base_acc = $row_user_animal['base_acc'];$base_eva = $row_user_animal['base_eva'];$base_cr = $row_user_animal['base_cr'];
    
    $dna_atk = $row_user_animal['dna_atk'];$dna_def = $row_user_animal['dna_def'];$dna_matk = $row_user_animal['dna_matk'];
    $dna_mdef = $row_user_animal['dna_mdef'];$dna_hp = $row_user_animal['dna_hp'];$dna_spd = $row_user_animal['dna_spd'];
              
    $pt_atk = $row_user_animal['pt_atk'];$pt_def = $row_user_animal['pt_def'];$pt_matk = $row_user_animal['pt_matk'];
    $pt_mdef = $row_user_animal['pt_mdef'];$pt_hp = $row_user_animal['pt_hp'];$pt_spd = $row_user_animal['pt_spd'];
    
    $xp_atk = $row_user_animal['xp_atk'];$xp_def = $row_user_animal['xp_def'];$xp_matk = $row_user_animal['xp_matk'];
    $xp_mdef = $row_user_animal['xp_mdef'];$xp_hp = $row_user_animal['xp_hp'];$xp_spd = $row_user_animal['xp_spd'];
    
    $user_animal_hp = floor(0.01 * (2 * $base_hp + $dna_hp + floor(0.25 * $pt_hp) + floor(0.25 * $xp_hp)) * $lvl) + $lvl + 10;
    $user_animal_current_hp = (int) ($row_user_animal['current_hp'] ?? 0);
    $effective_max_hp = (int) ($row_user_animal['effective_max_hp'] ?? $user_animal_hp);

    $user_animal_atk = floor(0.01 * (2 * $base_atk + $dna_atk + floor(0.25 * $pt_atk) + floor(0.25 * $xp_atk)) * $lvl) + 5;
    $user_animal_def = floor(0.01 * (2 * $base_def + $dna_def + floor(0.25 * $pt_def) + floor(0.25 * $xp_def)) * $lvl) + 5;
    $user_animal_matk = floor(0.01 * (2 * $base_matk + $dna_matk + floor(0.25 * $pt_matk) + floor(0.25 * $xp_matk)) * $lvl) + 5;
    $user_animal_mdef = floor(0.01 * (2 * $base_mdef + $dna_mdef + floor(0.25 * $pt_mdef) + floor(0.25 * $xp_mdef)) * $lvl) + 5;
    $user_animal_spd = floor(0.01 * (2 * $base_spd + $dna_spd + floor(0.25 * $pt_spd) + floor(0.25 * $xp_spd)) * $lvl) + 5;
    
    $user_animal_acc = $base_acc;
    $user_animal_eva = $base_eva;
    $user_animal_cr = $base_cr;
    
    
    
    $rows[] = array(
        'id'=>$row_user_animal['id_animal'],
        'type'=>"team",
        'maxHP'=>$user_animal_hp,
        'curHP'=>$user_animal_current_hp,
        'lvl'=>$lvl,
        'atk'=>$user_animal_atk,
        'def'=>$user_animal_def,
        'matk'=>$user_animal_matk,
        'mdef'=>$user_animal_mdef,
        'acc'=>$user_animal_acc,
        'eva'=>$user_animal_eva,
        'cr'=>$user_animal_cr,
        'spd'=>$user_animal_spd,
        'id_species'=>$id_species,
        'species'=>$species,
        'nickname'=>$nick,
        'element'=>$element,
        'id_element'=>$id_element,
        'element_color'=>$row_user_animal['element_color'] ?? '',
        'side'=>"none"
    );
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
    "response"=>$stringone
);

echo json_encode($riga);

?>