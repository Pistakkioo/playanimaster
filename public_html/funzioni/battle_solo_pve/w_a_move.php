<?php
if($w_a_res_hp>0 && $b_status=="ongoing")
{
        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/AiWild.php';

        $row_WA_action = AiWild::pickRandomAbility($conn, (int) $w_a_id_species, (int) $w_a_lvl, (string) $LANG);

        if ($row_WA_action)
        {
                require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/MoveResolver.php';

                $attacker = [
                    'lvl' => (int) $w_a_lvl,
                    'acc' => (int) $w_a_res_acc,
                    'cr' => (int) $w_a_res_cr,
                    'atk' => (float) $w_a_res_atk,
                    'def' => (float) $w_a_res_def,
                    'matk' => (float) $w_a_res_matk,
                    'mdef' => (float) $w_a_res_mdef,
                    'eva' => (int) $w_a_res_eva,
                    'spd' => (int) $w_a_res_spd,
                    'current_hp' => (int) $w_a_res_hp,
                    'max_hp' => (int) $w_a_res_max_hp,
                    'id_element' => (int) $w_a_id_element,
                    'nickname' => (string) $w_a_nickname,
                ];
                $defender = [
                    'lvl' => (int) $p_a_lvl,
                    'acc' => (int) $p_a_res_acc,
                    'cr' => (int) $p_a_res_cr,
                    'atk' => (float) $p_a_res_atk,
                    'def' => (float) $p_a_res_def,
                    'matk' => (float) $p_a_res_matk,
                    'mdef' => (float) $p_a_res_mdef,
                    'eva' => (int) $p_a_res_eva,
                    'spd' => (int) $p_a_res_spd,
                    'current_hp' => (int) $p_a_res_hp,
                    'max_hp' => (int) $p_a_res_max_hp,
                    'id_element' => (int) $p_a_id_element,
                    'nickname' => (string) $p_a_nickname,
                ];

                $move_result = MoveResolver::resolveAbility($row_WA_action, $attacker, $defender, [
                    'lang_suffix' => (string) $LANG,
                    'conn' => $conn,
                    'battle_type' => 'solo_pve',
                    'id_battle' => (int) $id_battle,
                    'applied_at_turn' => (int) $turn,
                    'attacker_entity' => [
                        'entity_type' => 'wild',
                        'id_entity' => (int) $w_a_id,
                        'id_user_ig' => null,
                    ],
                    'defender_entity' => [
                        'entity_type' => 'animal',
                        'id_entity' => (int) $p_a_id,
                        'id_user_ig' => (int) $id_user_ig,
                    ],
                ]);

                $w_a_res_hp = (int) $move_result['attacker']['current_hp'];
                $p_a_res_hp = (int) $move_result['defender']['current_hp'];

                $WA_AB_HIT = $move_result['move_hit'];
                $WA_MOVE_DESCR = $move_result['move_description'];
                $WA_id_ab = $move_result['id_ability'];

                if (!class_exists('BUFFS'))
                {
                    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
                }

                BUFFS::persistAnimalHpAfterBattle($conn, (int) $p_a_id, (int) $p_a_res_hp);
                
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
    
}

?>