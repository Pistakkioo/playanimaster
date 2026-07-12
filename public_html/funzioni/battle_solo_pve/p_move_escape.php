<?php

$user_lvl = 1;
$result_u_lvl = $conn->query("
    select level from users_ig where id_user_ig = \"$id_user_ig\"
");
$row_u_lvl = $result_u_lvl->fetch(); 
$user_lvl = intval($row_u_lvl['level']); 

$animal_blocks = false;

$diff = $user_lvl-$w_a_lvl;

$chance_to_block_escape = (-3.5*$diff)+35;
if($chance_to_block_escape<10){$chance_to_block_escape=10;}
if($chance_to_block_escape>90){$chance_to_block_escape=90;}

$numb = rand(0,100);

if($numb<$chance_to_block_escape)
{
    $animal_blocks = true;
}

if($animal_blocks)
{
    $b_status = "ongoing";
    
    $P_MOVE_DESCR = "$w_a_species blocked your escape.";
    if($LANG=="_it"){$P_MOVE_DESCR = "$w_a_species ha bloccato la tua fuga!";}
    if($LANG=="_pt"){$P_MOVE_DESCR = "$w_a_species bloqueou a tua fuga!";}
}
else
{
    $b_status = "escaped";
    
    $P_MOVE_DESCR = "You have escaped safely!";
    if($LANG=="_it"){$P_MOVE_DESCR = "Sei scappato con successo!"; }
    if($LANG=="_pt"){$P_MOVE_DESCR = "Fugiste com sucesso!";}
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
    (\"$id_battle\",now(),\"$turn\",'escape',0,999,\"$p_order_in_turn\",'user',\"$id_user_ig\",'user',\"$id_user_ig\"
      ,\"$p_a_res_atk\",\"$p_a_res_def\",\"$p_a_res_matk\",\"$p_a_res_mdef\",\"$p_a_res_hp\",\"$p_a_res_acc\",\"$p_a_res_eva\",\"$p_a_res_cr\",\"$p_a_res_spd\",\"$p_a_res_max_hp\"
      ,\"$w_a_res_atk\",\"$w_a_res_def\",\"$w_a_res_matk\",\"$w_a_res_mdef\",\"$w_a_res_hp\",\"$w_a_res_acc\",\"$w_a_res_eva\",\"$w_a_res_cr\",\"$w_a_res_spd\",\"$w_a_res_max_hp\"
      ,\"$p_a_id\",\"$p_a_id_element\",\"$p_a_id_species\",\"$p_a_species\",\"$p_a_lvl\",\"$p_a_nickname\",\"$p_a_cur_exp\"
      ,\"$w_a_id\",\"$w_a_id_element\",\"$w_a_id_species\",\"$w_a_species\",\"$w_a_lvl\"
      ,'$P_MOVE_DESCR','I',\"$b_status\"
    )
");


if($b_status!="ongoing")
{
    $result_end = $conn->query("
        update battles_solo_pve set finished = 'S'
        where id_battle_solo_pve = \"$id_battle\"
    ");

    if (!class_exists('CombatSession'))
    {
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/CombatSession.php';
    }

    CombatSession::onBattleEnd($conn, CombatSession::TYPE_SOLO, $id_battle);
}


?>