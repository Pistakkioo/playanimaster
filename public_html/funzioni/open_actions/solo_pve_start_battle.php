<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_zone = $_POST['id_zone'];
$id_wild_animal = $_POST['id_wild_animal'];
$LANG = $_POST['lang'];

$result_check = $conn->query("
    select id_battle from wild_animals where id_wild_animal = \"$id_wild_animal\"
");
$row_check = $result_check->fetch();
if(intval($row_check[0])>0)
{
    $riga = array(
        "stato"=>"KO",
        "msg"=>"TOO LATE"
    );
    
    echo json_encode($riga);
    
    exit;
}


$result = $conn->query("
    insert into battles_solo_pve
    (dt_creazione,id_user_ig,id_zone)
    VALUES
    (now(),\"$id_user_ig\",\"$id_zone\")
");
$id_battle = $conn->lastInsertId();

$result_wa_u = $conn->query("
    update wild_animals
    set dt_modifica = now(),id_battle = \"$id_battle\",battle_type = 'solo_pve'
    where id_wild_animal = \"$id_wild_animal\"
");

$result_wa = $conn->query("
    select WA.id_wild_animal, WA.dt_creazione, WA.dt_modifica, WA.id_species, WA.level, WA.battle_type, WA.id_battle
            , WA.atk, WA.def, WA.matk, WA.mdef, WA.hp, WA.acc, WA.eva, WA.cr, WA.xp, WA.spd, WA.id_element
            , WA.id_zone, WA.id_spawn_point, WA.pos_x, WA.pos_y, WA.pos_z, L.species$LANG as species 
    from wild_animals WA
    left join species L ON L.id_species = WA.id_species 
    where WA.id_wild_animal = \"$id_wild_animal\"
");
$row_wa = $result_wa->fetch();
$wild_animal_atk = $row_wa['atk'];
$wild_animal_def = $row_wa['def'];
$wild_animal_matk = $row_wa['matk'];
$wild_animal_mdef = $row_wa['mdef'];
$wild_animal_hp = $row_wa['hp'];
$wild_animal_acc = $row_wa['acc'];
$wild_animal_eva = $row_wa['eva'];
$wild_animal_cr = $row_wa['cr'];
$wild_animal_spd = $row_wa['spd'];

$id_element_wa = $row_wa['id_element'];
$id_species_wa = $row_wa['id_species'];
$species_wa = $row_wa['species'];
$lvl_wa = $row_wa['level'];
     
       




// USER SIDE
$result_first_animal = $conn->query("
    select id_animal from animals 
    where id_user_ig = \"$id_user_ig\"
    and team_position > 0
    AND current_hp > 0
    order by team_position
    limit 1 
");
$row_first_animal = $result_first_animal->fetch();
$id_first_animal = $row_first_animal ? intval($row_first_animal[0]) : 0;

if ($id_first_animal <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'NO_TEAM_ANIMAL',
        'response' => ''
    ]);
    exit;
}

$result_upd = $conn->query("
    update battles_solo_pve
        set id_wild_animal = \"$id_wild_animal\"
           ,id_user_animal = \"$id_first_animal\" 
           ,dt_modifica = now()
    where id_battle_solo_pve = \"$id_battle\"
");



//GET THE STATS VALUES OF BOTH ANIMALS// atk,def,matk,mdef,hp,acc,eva,cr,spd



$result_user_animal = $conn->query("
    select base_atk,base_def,base_matk,base_mdef,base_hp,base_acc,base_eva,base_cr,base_spd
		  ,dna_atk,dna_def,dna_matk,dna_mdef,dna_hp,dna_acc,dna_eva,dna_cr,dna_spd
          ,pt_atk,pt_def,pt_matk,pt_mdef,pt_hp,pt_acc,pt_eva,pt_cr,pt_spd
          ,xp_atk,xp_def,xp_matk,xp_mdef,xp_hp,xp_acc,xp_eva,xp_cr,xp_spd
          ,lvl, current_hp, max_hp,L.species$LANG as species,L.id_species,A.id_element,E.element$LANG as element,nickname,experience    
        from animals A 
        join species L ON L.id_species = A.id_species 
        left join elements E ON E.id_element = A.id_element
    where A.id_animal = \"$id_first_animal\"
");
$row_user_animal = $result_user_animal->fetch();
$id_element = $row_user_animal['id_element'];
$lvl = $row_user_animal['lvl'];
$species = $row_user_animal['species'];
$id_species = $row_user_animal['id_species'];
$nick = $row_user_animal['nickname'];
$user_animal_current_hp = $row_user_animal['current_hp'];
$user_animal_current_xp = $row_user_animal['experience'];
$needs_initial_hp = ($user_animal_current_hp === null || $user_animal_current_hp === '');

$base_atk = $row_user_animal['base_atk'];$base_def = $row_user_animal['base_def'];$base_matk = $row_user_animal['base_matk'];
$base_mdef = $row_user_animal['base_mdef'];$base_hp = $row_user_animal['base_hp'];$base_spd = $row_user_animal['base_spd'];

$base_acc = $row_user_animal['base_acc'];$base_eva = $row_user_animal['base_eva'];$base_cr = $row_user_animal['base_cr'];

$dna_atk = $row_user_animal['dna_atk'];$dna_def = $row_user_animal['dna_def'];$dna_matk = $row_user_animal['dna_matk'];
$dna_mdef = $row_user_animal['dna_mdef'];$dna_hp = $row_user_animal['dna_hp'];$dna_spd = $row_user_animal['dna_spd'];
          
$pt_atk = $row_user_animal['pt_atk'];$pt_def = $row_user_animal['pt_def'];$pt_matk = $row_user_animal['pt_matk'];
$pt_mdef = $row_user_animal['pt_mdef'];$pt_hp = $row_user_animal['pt_hp'];$pt_spd = $row_user_animal['pt_spd'];

$xp_atk = $row_user_animal['xp_atk'];$xp_def = $row_user_animal['xp_def'];$xp_matk = $row_user_animal['xp_matk'];
$xp_mdef = $row_user_animal['xp_mdef'];$xp_hp = $row_user_animal['xp_hp'];$xp_spd = $row_user_animal['xp_spd'];

//$wild_animal_spd = floor(0.01 * (2 * $row_wild_species['base_spd'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5;
//$wild_animal_hp = floor(0.01 * (2 * $row_wild_species['base_hp'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + $lvl_selected + 10;
 
$user_animal_hp = floor(0.01 * (2 * $base_hp + $dna_hp + floor(0.25 * $pt_hp) + floor(0.25 * $xp_hp)) * $lvl) + $lvl + 10;

$user_animal_atk = floor(0.01 * (2 * $base_atk + $dna_atk + floor(0.25 * $pt_atk) + floor(0.25 * $xp_atk)) * $lvl) + 5;
$user_animal_def = floor(0.01 * (2 * $base_def + $dna_def + floor(0.25 * $pt_def) + floor(0.25 * $xp_def)) * $lvl) + 5;
$user_animal_matk = floor(0.01 * (2 * $base_matk + $dna_matk + floor(0.25 * $pt_matk) + floor(0.25 * $xp_matk)) * $lvl) + 5;
$user_animal_mdef = floor(0.01 * (2 * $base_mdef + $dna_mdef + floor(0.25 * $pt_mdef) + floor(0.25 * $xp_mdef)) * $lvl) + 5;
$user_animal_spd = floor(0.01 * (2 * $base_spd + $dna_spd + floor(0.25 * $pt_spd) + floor(0.25 * $xp_spd)) * $lvl) + 5;

$user_animal_acc = $base_acc;
$user_animal_eva = $base_eva;
$user_animal_cr = $base_cr;

if (!class_exists('BUFFS'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
}

$row_user_animal['id_animal'] = (int) $id_first_animal;
$row_user_animal['id_user_ig'] = (int) $id_user_ig;
$base_level_stats = BUFFS::computeAnimalLevelStats($row_user_animal);
$user_animal_hp = (int) $base_level_stats['max_hp'];

if ($needs_initial_hp)
{
    $user_animal_current_hp = $user_animal_hp;
}

$buff_stats = BUFFS::applyAtBattleStart($conn, (int) $id_first_animal, (int) $id_user_ig, [
    'atk' => (int) $user_animal_atk,
    'def' => (int) $user_animal_def,
    'matk' => (int) $user_animal_matk,
    'mdef' => (int) $user_animal_mdef,
    'spd' => (int) $user_animal_spd,
    'acc' => (int) $user_animal_acc,
    'eva' => (int) $user_animal_eva,
    'cr' => (int) $user_animal_cr,
    'hp' => (int) $user_animal_current_hp,
    'max_hp' => (int) $user_animal_hp,
]);

$user_animal_atk = (int) $buff_stats['atk'];
$user_animal_def = (int) $buff_stats['def'];
$user_animal_matk = (int) $buff_stats['matk'];
$user_animal_mdef = (int) $buff_stats['mdef'];
$user_animal_spd = (int) $buff_stats['spd'];
$user_animal_acc = (int) $buff_stats['acc'];
$user_animal_eva = (int) $buff_stats['eva'];
$user_animal_cr = (int) $buff_stats['cr'];
$user_animal_current_hp = (int) $buff_stats['hp'];
$user_animal_hp = (int) $buff_stats['max_hp'];

if ($needs_initial_hp)
{
    BUFFS::persistAnimalHpAfterBattle($conn, (int) $id_first_animal, (int) $user_animal_current_hp);
}



// SET THE TURN 0 OF THE BATTLE // atk,def,matk,mdef,hp,acc,eva,cr,spd

$result_turn0 = $conn->query("
    insert into battles_solo_pve_moves
    (   id_battle_solo_pve,dt_creazione,turn,move_type,id_rif,move_speed,order_in_turn,protagonist_type,id_protagonist,target_type,id_target
        ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
        ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
        ,w_a_id,w_a_id_element,w_a_id_species,w_a_species,w_a_lvl
        ,p_a_id,p_a_id_element,p_a_id_species,p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
        ,move_description 
        )
    values
    (\"$id_battle\",now(),0,'start',0,0,0,'start',0,'start',0
      ,\"$wild_animal_atk\",\"$wild_animal_def\",\"$wild_animal_matk\",\"$wild_animal_mdef\",\"$wild_animal_hp\",\"$wild_animal_acc\",\"$wild_animal_eva\",\"$wild_animal_cr\",\"$wild_animal_spd\",\"$wild_animal_hp\"
      ,\"$user_animal_atk\",\"$user_animal_def\",\"$user_animal_matk\",\"$user_animal_mdef\",\"$user_animal_current_hp\",\"$user_animal_acc\",\"$user_animal_eva\",\"$user_animal_cr\",\"$user_animal_spd\",\"$user_animal_hp\"
      ,\"$id_wild_animal\",\"$id_element_wa\",\"$id_species_wa\",\"$species_wa\",\"$lvl_wa\"
      ,\"$id_first_animal\",\"$id_element\",\"$id_species\",\"$species\",\"$lvl\",\"$nick\",\"$user_animal_current_xp\"
      ,'start'
    )
");


$rig = array(
    "id_battle"=>$id_battle
    ,"battle_type"=>"solo_pve"
);

$singolo_json = json_encode($rig);
$stringone.=$singolo_json;




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