<?php
if($p_a_res_hp>0)
{
  
        $result_PA_action = $conn->query("
            select id_ability,accuracy,power,m_power,effect,effect_chance,A.id_element
                    , ability$LANG as ability, descrizione$LANG as descrizione, E.element 
            from abilities A
            left join elements E ON E.id_element = A.id_element
            where id_ability = \"$id\"
        ");
        $row_PA_action = $result_PA_action->fetch();
                
        $PA_AB_acc = $p_a_res_acc*$row_PA_action['accuracy']/100;
        $WA_EVA = (100-$w_a_res_eva)/100;
        $PA_AB_acc*=$WA_EVA;// if w_a has 10 evasion, p_a accuracy is multiplied by 0.9 
        $PA_num = rand(1,100);
        $PA_AB_HIT = "N";$PA_CRIT=1;$PA_TYPE_ABILITY = 1;
        if($PA_num<=$PA_AB_acc)
        {
            $PA_AB_HIT = "S";
        }
        if($PA_AB_HIT=="S")
        {
            $PA_crit_num = rand(1,100);
            if($PA_crit_num<=$p_a_res_cr)
            {
                $PA_CRIT = 1.5;
            }
            if($row_PA_action['id_element']==$p_a_id_element)
            {
                $PA_TYPE_ABILITY=1.5;
            }
            $PA_AB_DMG = (intval($p_a_lvl)*.5*intval($row_PA_action['power'])*floatval($p_a_res_atk)/floatval($w_a_res_def))+(intval($p_a_lvl)*.5*intval($row_PA_action['m_power'])*floatval($p_a_res_matk)/floatval($w_a_res_mdef));
            //error_log("PA DMG: $p_a_lvl*.5*$row_PA_action[power]*$p_a_res_atk/$w_a_res_def+$p_a_lvl*.5*$row_PA_action[m_power]*$p_a_res_matk/$w_a_res_mdef");
            //error_log("USER ANIMAL BASE DAMAGE:$PA_AB_DMG");
            $PA_AB_DMG/=40;
            //error_log("USER ANIMAL DAMAGE AFTER /40:$PA_AB_DMG");
            if(intval($row_PA_action['power'])>0||intval($row_PA_action['m_power'])>0){$PA_AB_DMG+=3;}
            //error_log("USER ANIMAL DAMAGE AFTER +3:$PA_AB_DMG");
            $PA_AB_DMG*=$PA_CRIT;
            //error_log("USER ANIMAL DAMAGE AFTER *CRIT:$PA_AB_DMG");
            $PA_AB_DMG*=$PA_TYPE_ABILITY;
            //error_log("USER ANIMAL DAMAGE AFTER *TYPE:$PA_AB_DMG");
            
            $ELEMENT_BONUS = FUNZIONI::element_bonus($row_PA_action['id_element'],$w_a_id_element);
            $PA_AB_DMG*=$ELEMENT_BONUS;
            //error_log("USER ANIMAL DAMAGE AFTER *LMNT BONUS:$PA_AB_DMG");
            $PA_AB_DMG=intval($PA_AB_DMG);
            //error_log("USER ANIMAL DAMAGE:$PA_AB_DMG");
            $w_a_res_hp-=$PA_AB_DMG;
            if($w_a_res_hp<=0)
            {   
                $w_a_res_hp=0;
            }
            else if($row_PA_action['effect']!="none")
            {
                $PA_effect_num = rand(1,100);
                if($PA_effect_num<$row_PA_action['effect_chance'])
                {
                    $effect = explode('_',$row_PA_action['effect']);
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
                    {// WILL ALTER A STAT TO THE WILD ANIMAL
                        $STR_EFFECT.="w_a_res_$effect_stat";
                    }
                    else if($effect_target=="self")
                    {// WILL ALTER A STAT TO THE PLAYER ANIMAL ITSELF
                        $STR_EFFECT.="p_a_res_$effect_stat";
                    }
                    $$STR_EFFECT*=$effect_multiplier;
                }
            }
                
            
        }
             
        $PA_MOVE_DESCR = $p_a_nickname." used ".$row_PA_action['ability'];
        if($LANG=="_it"){$PA_MOVE_DESCR = $p_a_nickname." ha usato ".$row_PA_action['ability'];}
        if($LANG=="_pt"){$PA_MOVE_DESCR = $p_a_nickname." usou ".$row_PA_action['ability'];}
        
        $PA_id_ab = $row_PA_action['id_ability'];
        
        
        ///////////////////////////////////////////////////////////////////////////////////////
        // IF WILD ANIMAL FAINTS!!!
        if($w_a_res_hp<=0)
        {// WILD ANIMAL DIED 
                    
            FUNZIONI::AddExpFromWildAnimal($conn,$id_user_ig,$p_a_id,$w_a_id_species,$w_a_lvl,$LANG);
            
            $result_animal = $conn->query("
                select lvl,experience from animals where id_animal = \"$p_a_id\"
            ");
            $row_animal = $result_animal->fetch();
            $p_a_cur_exp = $row_animal['experience'];
            $p_a_lvl = $row_animal['lvl'];
            
            // INSERT REWARDS 
            FUNZIONI::AddDropsWildAnimalUser($conn,$w_a_id_species,$w_a_lvl,$id_user_ig,$LANG);
            
        }                
        // END IF WILD ANIMAL FAINTS!!!
        ///////////////////////////////////////////////////////////////////////////////////////
        
        
        if($PA_CRIT>1)
        {
            $PA_AB_HIT = "C";
        }
        
        $result_hp = $conn->query("
            update animals set current_hp = \"$p_a_res_hp\", max_hp = \"$p_a_res_max_hp\"
            where id_animal = \"$p_a_id\"
        ");
        
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
                ,move_description,move_hit,resulting_battle_status 
                )
            values
            (\"$id_battle\",now(),\"$turn\",'ability',\"$PA_id_ab\",\"$p_a_prev_spd\",\"$p_order_in_turn\",'user_animal',\"$p_a_id\",'wild_animal',\"$w_a_id\"
              ,\"$p_a_res_atk\",\"$p_a_res_def\",\"$p_a_res_matk\",\"$p_a_res_mdef\",\"$p_a_res_hp\",\"$p_a_res_acc\",\"$p_a_res_eva\",\"$p_a_res_cr\",\"$p_a_res_spd\",\"$p_a_res_max_hp\"
              ,\"$w_a_res_atk\",\"$w_a_res_def\",\"$w_a_res_matk\",\"$w_a_res_mdef\",\"$w_a_res_hp\",\"$w_a_res_acc\",\"$w_a_res_eva\",\"$w_a_res_cr\",\"$w_a_res_spd\",\"$w_a_res_max_hp\"
              ,\"$p_a_id\",\"$p_a_id_element\",\"$p_a_id_species\",\"$p_a_species\",\"$p_a_lvl\",\"$p_a_nickname\",\"$p_a_cur_exp\"
              ,\"$w_a_id\",\"$w_a_id_element\",\"$w_a_id_species\",\"$w_a_species\",\"$w_a_lvl\"
              ,'$PA_MOVE_DESCR',\"$PA_AB_HIT\",\"$b_status\"
            )
        ");
    
        if($b_status!="ongoing")
        {
            $result_end = $conn->query("
                update battles_solo_pve set finished = 'S'
                where id_battle_solo_pve = \"$id_battle\"
            ");
        }
    
}// END IF $p_a_res_hp>0
                      
                
            
?>