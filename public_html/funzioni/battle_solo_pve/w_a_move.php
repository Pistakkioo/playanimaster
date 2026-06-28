<?php
if($w_a_res_hp>0 && $b_status=="ongoing")
{
    
        $WA_move = array();
        $result_WA_action = $conn->query("
            select A.id_ability,A.ability$LANG as ability,A.descrizione$LANG as descrizione,A.accuracy,A.power,A.m_power,A.id_element,E.element$LANG as element,A.effect,A.effect_chance
                from abilities A
                join species_abilities LA ON LA.id_ability = A.id_ability
                left join elements E on A.id_element = E.id_element
            where LA.id_species = \"$w_a_id_species\"
            AND LA.unlock_lvl <= \"$w_a_lvl\"
        ");
        $count_WA_actions = $result_WA_action->rowCount();
        $selected_wa_n = rand(0,$count_WA_actions-1);
        $wa_count = 0;
        
        
        
        while($row_WA_action = $result_WA_action->fetch())
        {
            if($wa_count==$selected_wa_n)
            {
                $WA_AB_acc = $w_a_res_acc*$row_WA_action['accuracy']/100;
                $PA_EVA = (100-$p_a_res_eva)/100;
                $WA_AB_acc*=$PA_EVA;// if p_a has 10 evasion, w_a accuracy is multiplied by 0.9 
                $WA_num = rand(1,100);
                $WA_AB_HIT = "N";$WA_CRIT=1;$WA_TYPE_ABILITY = 1;
                if($WA_num<=$WA_AB_acc)
                {
                    $WA_AB_HIT = "S";
                }
                if($WA_AB_HIT=="S")
                {
                    $WA_crit_num = rand(1,100);
                    if($WA_crit_num<=$w_a_res_cr)
                    {
                        $WA_CRIT = 1.5;
                    }
                    if($row_WA_action['id_element']==$w_a_id_element)
                    {
                        $WA_TYPE_ABILITY=1.5;
                    }
                    $WA_AB_DMG = (intval($w_a_lvl)*.5*intval($row_WA_action['power'])*floatval($w_a_res_atk)/floatval($p_a_res_def))+(intval($w_a_lvl)*.5*intval($row_WA_action['m_power'])*floatval($w_a_res_matk)/floatval($p_a_res_mdef));
                    //error_log("WA DMG: $w_a_lvl*.5*$row_WA_action[power]*$w_a_res_atk/$p_a_res_def+$w_a_lvl*.5*$row_WA_action[m_power]*$w_a_res_matk/$p_a_res_mdef");
                    $WA_AB_DMG/=40;
                    if(intval($row_WA_action['power'])>0||intval($row_WA_action['m_power'])>0){$WA_AB_DMG+=3;}
                    $WA_AB_DMG*=$WA_CRIT;
                    $WA_AB_DMG*=$WA_TYPE_ABILITY;
                    
                    $ELEMENT_BONUS = FUNZIONI::element_bonus($row_WA_action['id_element'],$p_a_id_element);
                    $WA_AB_DMG*=$ELEMENT_BONUS;
                    $WA_AB_DMG=intval($WA_AB_DMG);
                    //error_log("WILD ANIMAL DAMAGE:$WA_AB_DMG");
                    $p_a_res_hp-=$WA_AB_DMG;
                    if($p_a_res_hp<=0)
                    {   
                        $p_a_res_hp=0;
                    }
                    else if($row_WA_action['effect']!="none")
                    {
                        $WA_effect_num = rand(1,100);
                        if($WA_effect_num<$row_WA_action['effect_chance'])
                        {
                            $effect = explode('_',$row_WA_action['effect']);
                            //lower_target_atk_10_%
                            $effect_direction = $effect[0];
                            $effect_target = $effect[1];
                            $effect_stat = $effect[2];
                            $effect_mult = $effect[3];
                            $effect_unit = $effect[4];
                            $effect_multiplier = 1;
                            if($effect_unit=="%")
                            {
                                if($effect_direction=="lower")
                                {
                                    $effect_multiplier-=floatval($effect_mult/100);
                                }
                                else if($effect_direction=="increase")
                                {
                                    $effect_multiplier+=floatval($effect_mult/100);
                                }
                            }
                            
                            $STR_EFFECT = "";    
                            if($effect_target=="target")
                            {// WILL ALTER A STAT TO THE PLAYER ANIMAL
                                $STR_EFFECT.="p_a_res_$effect_stat";
                            }
                            else if($effect_target=="self")
                            {// WILL ALTER A STAT TO THE WILD ANIMAL ITSELF
                                $STR_EFFECT.="w_a_res_$effect_stat";
                            }
                            $$STR_EFFECT*=$effect_multiplier;
                        }
                    }
                        
                    
                }
                    
                $WA_MOVE_DESCR = $w_a_nickname." used ".$row_WA_action['ability'];
                if($LANG=="_it"){$WA_MOVE_DESCR = $w_a_nickname." ha usato ".$row_WA_action['ability'];}
                if($LANG=="_pt"){$WA_MOVE_DESCR = $w_a_nickname." usou ".$row_WA_action['ability'];}
        
                $WA_id_ab = $row_WA_action['id_ability'];
                
                
                if($WA_CRIT>1)
                {
                    $WA_AB_HIT = "C";
                }
                
                $result_hp = $conn->query("
                    update animals set current_hp = \"$p_a_res_hp\", max_hp = \"$p_a_res_max_hp\"
                    where id_animal = \"$p_a_id\"
                ");
                
                if($w_a_res_hp<=0){$b_status = "win";}
                if($p_a_res_hp<=0)
                {
                    //error_log("user animal died: exp before death: $p_a_cur_exp");
                    $max = $lvl_up_constant_animal*($p_a_lvl+1)*($p_a_lvl+1)*($p_a_lvl+1);//error_log("user animal died: max:$max");
                    $min = $lvl_up_constant_animal*$p_a_lvl*$p_a_lvl*$p_a_lvl;//error_log("user animal died: min:$min");
                    $five_perc = ($exp_loss_percent_on_death/100) * ($max-$min);//error_log("user animal died: five_perc:$five_perc");
                    $p_a_cur_exp-=$five_perc;
                    //error_log("user animal died: exp after death: $p_a_cur_exp");
                    $result_xp = $conn->query("
                        update animals set experience = \"$p_a_cur_exp\"
                        where id_animal = \"$p_a_id\"
                    "); 
                    
                    $result_team_alive = $conn->query("
                        select sum(coalesce(current_hp,0)) from animals where id_user_ig = \"$id_user_ig\" and team_position>0
                    ");
                    $row_team_alive = $result_team_alive->fetch();
                    $tot_team_hp = intval($row_team_alive[0]);
                    if($tot_team_hp<=0)
                    {
                        $b_status = "defeat";
                        
                        $result_user_xp = $conn->query("
                            select exp_total,level from users_ig where id_user_ig = \"$id_user_ig\"
                        ");
                        $row_user_xp = $result_user_xp->fetch();
                        $user_exp = intval($row_user_xp['exp_total']);
                        $u_level = intval($row_user_xp['level']);
                        
                        //error_log("user WHOLE team died: user exp before death: $user_exp");
                        $u_max = $lvl_up_constant_player*($u_level+1)*($u_level+1)*($u_level+1);//error_log("user WHOLE team died: user max:$u_max");
                        $u_min = $lvl_up_constant_player*$u_level*$u_level*$u_level;//error_log("user WHOLE team died: user min:$u_min");
                        $u_five_perc = ($exp_loss_percent_on_death/100) * ($u_max-$u_min);//error_log("user WHOLE team died: user five_perc:$u_five_perc");
                        $user_exp-=$u_five_perc;
                        //error_log("user WHOLE team died: user exp after death: $user_exp");
                        $result_xp = $conn->query("
                            update users_ig set exp_total = \"$user_exp\"
                            where id_user_ig = \"$id_user_ig\"
                        "); 
                    }
                }
                
                $u_lvl = FUNZIONI::AdjustUserLvlFromExp($conn,$id_user_ig,$LANG);
                $p_a_lvl = FUNZIONI::AdjustAnimalLvlFromExp($conn,$p_a_id,$LANG);
                
                $result_turn = $conn->query("
                    insert into battles_solo_pve_moves
                    (   id_battle_solo_pve,dt_creazione,turn,move_type,id_rif,move_speed,order_in_turn,protagonist_type,id_protagonist,target_type,id_target
                        ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
                        ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
                        ,w_a_id,w_a_id_element,w_a_id_species,w_a_species,w_a_lvl
                        ,p_a_id,p_a_id_element,p_a_id_species,p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
                        ,move_description,move_hit ,resulting_battle_status 
                        )
                    values
                    (\"$id_battle\",now(),\"$turn\",'ability',\"$WA_id_ab\",\"$w_a_prev_spd\",\"$w_order_in_turn\",'wild_animal',\"$w_a_id\",'user_animal',\"$p_a_id\"
                      ,\"$w_a_res_atk\",\"$w_a_res_def\",\"$w_a_res_matk\",\"$w_a_res_mdef\",\"$w_a_res_hp\",\"$w_a_res_acc\",\"$w_a_res_eva\",\"$w_a_res_cr\",\"$w_a_res_spd\",\"$w_a_res_max_hp\"
                      ,\"$p_a_res_atk\",\"$p_a_res_def\",\"$p_a_res_matk\",\"$p_a_res_mdef\",\"$p_a_res_hp\",\"$p_a_res_acc\",\"$p_a_res_eva\",\"$p_a_res_cr\",\"$p_a_res_spd\",\"$p_a_res_max_hp\"
                      ,\"$w_a_id\",\"$w_a_id_element\",\"$w_a_id_species\",\"$w_a_species\",\"$w_a_lvl\"
                      ,\"$p_a_id\",\"$p_a_id_element\",\"$p_a_id_species\",\"$p_a_species\",\"$p_a_lvl\",\"$p_a_nickname\",\"$p_a_cur_exp\"
                      ,'$WA_MOVE_DESCR',\"$WA_AB_HIT\" ,\"$b_status\" 
                    )
                ");
                
                
            }
            $wa_count++;
        } 
        
        
        if($b_status!="ongoing")
        {
            $result_end = $conn->query("
                update battles_solo_pve set finished = 'S'
                where id_battle_solo_pve = \"$id_battle\"
            ");

            if (!class_exists('BUFFS'))
            {
                require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
            }

            BUFFS::onSoloPveBattleEnd($conn, $id_battle);
        }
    
}

?>