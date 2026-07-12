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

        require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/MoveResolver.php';

        $attacker = [
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
        $defender = [
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
            'nickname' => (string) $w_a_species,
        ];

        $move_result = MoveResolver::resolveAbility($row_PA_action, $attacker, $defender, [
            'lang_suffix' => (string) $LANG,
            'conn' => $conn,
            'battle_type' => 'solo_pve',
            'id_battle' => (int) $id_battle,
            'applied_at_turn' => (int) $turn,
            'attacker_entity' => [
                'entity_type' => 'animal',
                'id_entity' => (int) $p_a_id,
                'id_user_ig' => (int) $id_user_ig,
            ],
            'defender_entity' => [
                'entity_type' => 'wild',
                'id_entity' => (int) $w_a_id,
                'id_user_ig' => null,
            ],
        ]);

        $p_a_res_hp = (int) $move_result['attacker']['current_hp'];
        $w_a_res_hp = (int) $move_result['defender']['current_hp'];

        $PA_AB_HIT = $move_result['move_hit'];
        $PA_MOVE_DESCR = $move_result['move_description'];
        $PA_id_ab = $move_result['id_ability'];
        
        
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
            FUNZIONI::AddDropsWildAnimalUser($conn,$w_a_id_species,$w_a_lvl,$id_user_ig,$LANG,1.0,$w_a_id_element);
            
            if (!class_exists('QUESTS'))
            {
                require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/quests.php';
            }
            QUESTS::onWildDefeated($conn, $id_user_ig, $w_a_id_species, $LANG);
            
        }                
        // END IF WILD ANIMAL FAINTS!!!
        ///////////////////////////////////////////////////////////////////////////////////////
        
        if (!class_exists('BUFFS'))
        {
            require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/buffs.php';
        }

        BUFFS::persistAnimalHpAfterBattle($conn, (int) $p_a_id, (int) $p_a_res_hp);
        
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

            if (!class_exists('CombatSession'))
            {
                require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/CombatSession.php';
            }

            CombatSession::onBattleEnd($conn, CombatSession::TYPE_SOLO, $id_battle);
        }
    
}// END IF $p_a_res_hp>0
                      
                
            
?>