<?php

$P_MOVE_DESCR = "You removed $p_a_nickname. ";
if($LANG=="_it"){$P_MOVE_DESCR = "Hai rimosso $p_a_nickname. ";}
if($LANG=="_pt"){$P_MOVE_DESCR = "Retiraste $p_a_nickname. ";}

$p_a_id = $id;

$result_user_animal = $conn->query("
    select base_atk,base_def,base_matk,base_mdef,base_hp,base_acc,base_eva,base_cr,base_spd
		  ,dna_atk,dna_def,dna_matk,dna_mdef,dna_hp,dna_acc,dna_eva,dna_cr,dna_spd
          ,pt_atk,pt_def,pt_matk,pt_mdef,pt_hp,pt_acc,pt_eva,pt_cr,pt_spd
          ,xp_atk,xp_def,xp_matk,xp_mdef,xp_hp,xp_acc,xp_eva,xp_cr,xp_spd
          ,lvl, current_hp, max_hp,L.species,L.id_species,id_element,nickname 
        from animals A 
        join species L ON L.id_species = A.id_species 
    where A.id_animal = \"$id\"
");
$row_user_animal = $result_user_animal->fetch();
$p_a_id_element = $row_user_animal['id_element'];
$p_a_lvl = $row_user_animal['lvl'];
$p_a_species = $row_user_animal['species'];
$p_a_id_species = $row_user_animal['id_species'];
$p_a_nickname = $row_user_animal['nickname'];
if($LANG==""){$P_MOVE_DESCR.="$p_a_nickname, it's your turn!";}
if($LANG=="_it"){$P_MOVE_DESCR.="$p_a_nickname, tocca a te!";}
if($LANG=="_pt"){$P_MOVE_DESCR.="$p_a_nickname, vai tu!";}
$p_a_res_hp = $row_user_animal['current_hp'];

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
 
$p_a_res_max_hp = floor(0.01 * (2 * $base_hp + $dna_hp + floor(0.25 * $pt_hp) + floor(0.25 * $xp_hp)) * $p_a_lvl) + $p_a_lvl + 10;

$p_a_res_atk = floor(0.01 * (2 * $base_atk + $dna_atk + floor(0.25 * $pt_atk) + floor(0.25 * $xp_atk)) * $p_a_lvl) + 5;
$p_a_res_def = floor(0.01 * (2 * $base_def + $dna_def + floor(0.25 * $pt_def) + floor(0.25 * $xp_def)) * $p_a_lvl) + 5;
$p_a_res_matk = floor(0.01 * (2 * $base_matk + $dna_matk + floor(0.25 * $pt_matk) + floor(0.25 * $xp_matk)) * $p_a_lvl) + 5;
$p_a_res_mdef = floor(0.01 * (2 * $base_mdef + $dna_mdef + floor(0.25 * $pt_mdef) + floor(0.25 * $xp_mdef)) * $p_a_lvl) + 5;
$p_a_res_spd = floor(0.01 * (2 * $base_spd + $dna_spd + floor(0.25 * $pt_spd) + floor(0.25 * $xp_spd)) * $p_a_lvl) + 5;

$p_a_res_acc = $base_acc;
$p_a_res_eva = $base_eva;
$p_a_res_cr = $base_cr;

$hp_was_empty = ($p_a_res_hp == "");

if ($hp_was_empty)
{
    $p_a_res_hp = $p_a_res_max_hp;
}

if (!class_exists('BUFFS'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
}

$buff_stats = BUFFS::applyForActiveBattleAnimal($conn, 'solo_pve', (int) $id_battle, (int) $p_a_id, (int) $id_user_ig, [
    'atk' => (int) $p_a_res_atk,
    'def' => (int) $p_a_res_def,
    'matk' => (int) $p_a_res_matk,
    'mdef' => (int) $p_a_res_mdef,
    'spd' => (int) $p_a_res_spd,
    'acc' => (int) $p_a_res_acc,
    'eva' => (int) $p_a_res_eva,
    'cr' => (int) $p_a_res_cr,
    'hp' => (int) $p_a_res_hp,
    'max_hp' => (int) $p_a_res_max_hp,
]);

$p_a_res_atk = (int) $buff_stats['atk'];
$p_a_res_def = (int) $buff_stats['def'];
$p_a_res_matk = (int) $buff_stats['matk'];
$p_a_res_mdef = (int) $buff_stats['mdef'];
$p_a_res_spd = (int) $buff_stats['spd'];
$p_a_res_acc = (int) $buff_stats['acc'];
$p_a_res_eva = (int) $buff_stats['eva'];
$p_a_res_cr = (int) $buff_stats['cr'];
$p_a_res_hp = (int) $buff_stats['hp'];
$p_a_res_max_hp = (int) $buff_stats['max_hp'];

if ($hp_was_empty)
{
    BUFFS::persistAnimalHpAfterBattle($conn, (int) $id, (int) $p_a_res_hp);
}
    
                $b_status = "ongoing";
                if($w_a_res_hp<=0){$b_status = "win";}
                if($p_a_res_hp<=0)
                {
                    $result_team_alive = $conn->query("
                        select sum(coalesce(current_hp)) from animals where id_user_ig = \"$id_user_ig\" and team_position>0
                    ");
                    $row_team_alive = $result_team_alive->fetch();
                    $tot_team_hp = intval($row_team_alive[0]);
                    if($tot_team_hp<=0){$b_status = "defeat";}
                }
                
    $result_turn = $conn->query("
        insert into battles_solo_pve_moves
        (   id_battle_solo_pve,dt_creazione,turn,move_type,id_rif,move_speed,order_in_turn,protagonist_type,id_protagonist,target_type,id_target
            ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
            ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
            ,p_a_id,p_a_id_element,p_a_id_species,p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
            ,w_a_id,w_a_id_element,w_a_id_species,w_a_species,w_a_lvl
            ,move_description,move_hit ,resulting_battle_status 
            )
        values
        (\"$id_battle\",now(),\"$turn\",'switch',\"$id\",999,\"$p_order_in_turn\",'user',\"$id_user_ig\",'user_animal',\"$p_a_id\"
          ,\"$p_a_res_atk\",\"$p_a_res_def\",\"$p_a_res_matk\",\"$p_a_res_mdef\",\"$p_a_res_hp\",\"$p_a_res_acc\",\"$p_a_res_eva\",\"$p_a_res_cr\",\"$p_a_res_spd\",\"$p_a_res_max_hp\"
          ,\"$w_a_res_atk\",\"$w_a_res_def\",\"$w_a_res_matk\",\"$w_a_res_mdef\",\"$w_a_res_hp\",\"$w_a_res_acc\",\"$w_a_res_eva\",\"$w_a_res_cr\",\"$w_a_res_spd\",\"$w_a_res_max_hp\"
          ,\"$p_a_id\",\"$p_a_id_element\",\"$p_a_id_species\",\"$p_a_species\",\"$p_a_lvl\",\"$p_a_nickname\",\"$p_a_cur_exp\"
          ,\"$w_a_id\",\"$w_a_id_element\",\"$w_a_id_species\",\"$w_a_species\",\"$w_a_lvl\"
          ,\"$P_MOVE_DESCR\",'A',\"$b_status\"
        )
    "    );
    

?>