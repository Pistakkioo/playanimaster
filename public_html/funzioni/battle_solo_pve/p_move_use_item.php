<?php

$id_item_type_selected = $_POST['id_item_type_selected'];



$result_item_type = $conn->query("
    select * from item_types where id_item_type = \"$id_item_type_selected\"
");
$row_item_type = $result_item_type->fetch();
$item_name = $row_item_type['nome'];
if($row_item_type['item_type']=="potions")
{
    $hp_recover = $row_item_type['use_effect'];
    $result_animal = $conn->query("
        update animals set current_hp = case when current_hp + \"$hp_recover\" > max_hp 
									 then max_hp 
									 ELSE current_hp + \"$hp_recover\"
									 end
		where id_animal = \"$id\"
    ");
    
    $result_delete_item = $conn->query("
        update items set dt_used = now(),dt_modifica = now() 
        where id_item_type = \"$id_item_type_selected\"
        AND id_user_ig = \"$id_user_ig\"
        AND (flg_held = 'N' or flg_held is null)
        AND dt_used is null
        order by dt_creazione
        limit 1 
    ");
    
    
    $result_final_hp = $conn->query("
        select current_hp from animals where id_animal = \"$id\"
    ");
    $row_final_hp = $result_final_hp->fetch();
    if($p_a_id==$id){$p_a_res_hp = intval($row_final_hp[0]);}
    
    $P_MOVE_DESCR = "You used $item_name on $p_a_nickname";
    if($LANG=="_it"){$P_MOVE_DESCR = "Hai usato $item_name su $p_a_nickname";}
    if($LANG=="_pt"){$P_MOVE_DESCR = "Usaste $item_name em $p_a_nickname";}
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
                    (\"$id_battle\",now(),\"$turn\",'item',\"$id_item_type_selected\",999,\"$p_order_in_turn\",'user',\"$id_user_ig\",'user_animal',\"$p_a_id\"
                      ,\"$p_a_res_atk\",\"$p_a_res_def\",\"$p_a_res_matk\",\"$p_a_res_mdef\",\"$p_a_res_hp\",\"$p_a_res_acc\",\"$p_a_res_eva\",\"$p_a_res_cr\",\"$p_a_res_spd\",\"$p_a_res_max_hp\"
                      ,\"$w_a_res_atk\",\"$w_a_res_def\",\"$w_a_res_matk\",\"$w_a_res_mdef\",\"$w_a_res_hp\",\"$w_a_res_acc\",\"$w_a_res_eva\",\"$w_a_res_cr\",\"$w_a_res_spd\",\"$w_a_res_max_hp\"
                      ,\"$p_a_id\",\"$p_a_id_element\",\"$p_a_id_species\",\"$p_a_species\",\"$p_a_lvl\",\"$p_a_nickname\",\"$p_a_cur_exp\"
                      ,\"$w_a_id\",\"$w_a_id_element\",\"$w_a_id_species\",\"$w_a_species\",\"$w_a_lvl\"
                      ,'$P_MOVE_DESCR','I',\"$b_status\"
                    )
                ");
                

?>