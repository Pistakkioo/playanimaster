<?php
class FUNZIONI
{
    public static function element_bonus($ATKR_AB_LMNT,$DFNDR_LMNT)
    {
        $multiplier = 1;
        if($ATKR_AB_LMNT==1)
        {
            if($DFNDR_LMNT==2 || $DFNDR_LMNT==6 || $DFNDR_LMNT==4){$multiplier=1.5;}
            else if($DFNDR_LMNT==1 || $DFNDR_LMNT==3 || $DFNDR_LMNT==5 || $DFNDR_LMNT==7){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==2)
        {
            if($DFNDR_LMNT==7 || $DFNDR_LMNT==5 || $DFNDR_LMNT==3){$multiplier=1.5;}
            else if($DFNDR_LMNT==2 || $DFNDR_LMNT==4 || $DFNDR_LMNT==6 || $DFNDR_LMNT==1){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==3)
        {
            if($DFNDR_LMNT==4 || $DFNDR_LMNT==1 || $DFNDR_LMNT==6){$multiplier=1.5;}
            else if($DFNDR_LMNT==3 || $DFNDR_LMNT==2 || $DFNDR_LMNT==5 || $DFNDR_LMNT==7){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==4)
        {
            if($DFNDR_LMNT==5 || $DFNDR_LMNT==2 || $DFNDR_LMNT==6){$multiplier=1.5;}
            else if($DFNDR_LMNT==4 || $DFNDR_LMNT==3 || $DFNDR_LMNT==1 || $DFNDR_LMNT==7){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==5)
        {
            if($DFNDR_LMNT==7 || $DFNDR_LMNT==1 || $DFNDR_LMNT==3){$multiplier=1.5;}
            else if($DFNDR_LMNT==5 || $DFNDR_LMNT==6 || $DFNDR_LMNT==4 || $DFNDR_LMNT==2){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==6)
        {
            if($DFNDR_LMNT==7 || $DFNDR_LMNT==2 || $DFNDR_LMNT==5){$multiplier=1.5;}
            else if($DFNDR_LMNT==6 || $DFNDR_LMNT==3 || $DFNDR_LMNT==1 || $DFNDR_LMNT==4){$multiplier=0.8;}
        }
        else if($ATKR_AB_LMNT==7)
        {
            if($DFNDR_LMNT==1 || $DFNDR_LMNT==3 || $DFNDR_LMNT==4){$multiplier=1.5;}
            else if($DFNDR_LMNT==7 || $DFNDR_LMNT==6 || $DFNDR_LMNT==5 || $DFNDR_LMNT==2){$multiplier=0.8;}
        }// /////////////////////////////////////////////////
        return $multiplier;
    }
    
    
    
    public static function AddDropsWildAnimalUser($conn,$id_species,$lvl,$id_user_ig,$LANG,$reward_multiplier=1.0,$id_element=0)
    {
        //error_log("function ADD_DROPS_WILD_ANIMAL_USER");
        // Quest-gated drop rows (wild_animal_drop_types.id_quest_required) only
        // drop while the referenced quest is active (not started, not completed).
        $result_active_quests = $conn->query("
            select id_quest from user_quests where id_user_ig = \"$id_user_ig\" and flg_completed = 'N'
        ");
        $active_quest_ids = [];
        while ($row_active_quest = $result_active_quests->fetch())
        {
            $active_quest_ids[] = (int) $row_active_quest['id_quest'];
        }
        $user_active_quest_ids = count($active_quest_ids) > 0 ? implode(',', $active_quest_ids) : "-1";
        $got_item_drop = false;
        $id_element = (int) $id_element;
        
        //
        $result_WA_drops = $conn->query("
            select D.drop_type,D.id_item_type,D.qt_min,D.qt_max,D.chance
                  ,L.species 
                  ,IT.nome$LANG as nome 
            from wild_animal_drop_types D
            join species L ON L.id_species = D.id_species
            LEFT JOIN item_types IT ON IT.id_item_type = D.id_item_type 
            where D.id_species = $id_species 
            AND D.lvl_min <=$lvl
            AND D.lvl_max >=$lvl
            AND (D.id_quest_required is null OR D.id_quest_required = 0 OR D.id_quest_required in ($user_active_quest_ids) )
            AND (D.id_element is null OR D.id_element = 0 OR D.id_element = $id_element)
        "); 
        while($row_WA_drops = $result_WA_drops->fetch())
        {
            $drop_type = $row_WA_drops['drop_type'];
            
            if($drop_type=="gold")
            {
                // Scaled down for party PvE so a full party splitting one
                // wild's loot nets the same expected total as a solo kill.
                $chance = intval($row_WA_drops['chance'])*$reward_multiplier;
                $check_chance = rand(1,100) <= $chance;
                
                if($check_chance)
                {
                    $qt_min = intval($row_WA_drops['qt_min']);
                    $qt_max = intval($row_WA_drops['qt_max']);
                    $qty = rand($qt_min,$qt_max);
                    
                    $result_gold = $conn->query("
                        update users_ig
                         set gold = gold+$qty
                         where id_user_ig = \"$id_user_ig\"
                    ");
                    $notification = "You obtained $qty Gold";
                    if($LANG=="_it"){$notification = "Hai trovato $qty Oro";}                
                    if($LANG=="_pt"){$notification = "Encontraste $qty Ouro";}
                    
                    $result_notification = $conn->query("
                        insert into notifications
                        (id_user_ig,description,item_type,id_item_type,flg_viewed,dt_c)
                        VALUES
                        (\"$id_user_ig\",'$notification','gold',0,'N',now())
                    ");
                }
            }
            
            if($drop_type=="item")
            {
                $chance = intval($row_WA_drops['chance'])*$reward_multiplier;
                $check_chance = rand(1,100) <= $chance;
                
                if($check_chance)
                {
                    $qt_min = intval($row_WA_drops['qt_min']);
                    $qt_max = intval($row_WA_drops['qt_max']);
                    $qty = rand($qt_min,$qt_max);
                    $id_item_type = $row_WA_drops['id_item_type'];
                    
                    for($i=0;$i<$qty;$i++)
                    {
                        $result_item = $conn->query("
                            insert into items
                            (dt_creazione,id_user_ig,id_item_type)
                            VALUES
                            (now(),\"$id_user_ig\",\"$id_item_type\") 
                        ");
                    }
                        
                    $item_name = $row_WA_drops['nome'];
                    
                    $notification = "You obtained ($qty) $item_name";
                    if($LANG=="_it"){$notification = "Hai trovato ($qty) $item_name";}                
                    if($LANG=="_pt"){$notification = "Encontraste ($qty) $item_name";}
                    
                    
                    $result_notification = $conn->query("
                        insert into notifications 
                        (id_user_ig,description,item_type,id_item_type,flg_viewed,dt_c)
                        VALUES
                        (\"$id_user_ig\",'$notification','item',\"$id_item_type\",'N',now())
                    ");

                    $got_item_drop = true;
                }
            }
        }

        if ($got_item_drop)
        {
            if (!class_exists('QUESTS'))
            {
                require_once __DIR__ . '/quests.php';
            }
            QUESTS::onInventoryChanged($conn, $id_user_ig, $LANG);
        }
    }
    
    public static function AddNotification($conn,$id_user_ig,$notification,$notification_type)
    {
        $result_notification = $conn->query("
            insert into notifications
            (id_user_ig,description,item_type,flg_viewed,dt_c)
            VALUES
            (\"$id_user_ig\",\"$notification\",\"$notification_type\",'N',now())
        ");
    }
    
    public static function AdjustUserLvlFromExp($conn,$id_user_ig,$LANG)
    {
        $p_lvl = 1;
        $result_user = $conn->query("
            select * from users_ig where id_user_ig = \"$id_user_ig\"
        ");
        $row_user = $result_user->fetch();
        
        $result_constants = $conn->query("
            select valore from costanti where costante = 'lvl_up_constant_player'
        ");
        $row_costante = $result_constants->fetch();
        $CONST = intval($row_costante['valore']);
        
        $lvl = intval($row_user['level']); $next_lvl = $lvl+1;        
        $exp = intval($row_user['exp_total']);
        if($exp<0)
        {
            $p_lvl = 1;
            $exp = 0;
        }
        else if($exp < $CONST*$lvl*$lvl*$lvl)
        {
            $p_lvl = $lvl-1;
            
                $notification = "You have decreased to level $p_lvl!";
                if($LANG=="_it"){$notification = "Sei sceso a livello $p_lvl!";}                
                if($LANG=="_pt"){$notification = "Baixaste para o nivel $p_lvl!";}
                $notification_type = "lvl_down";
                FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
        }
        else if($exp < $CONST*$next_lvl*$next_lvl*$next_lvl)
        {
            $p_lvl = $lvl;
        }
        else
        {
            $p_lvl = $next_lvl;
            
                $notification = "You have leveled up to level $p_lvl!";
                if($LANG=="_it"){$notification = "Sei salito a livello $p_lvl!";}                
                if($LANG=="_pt"){$notification = "Subiste para o nivel $p_lvl!";}
                $notification_type = "lvl_up";
                FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
            
        }
        
        $result_u = $conn->query("
            update users_ig set exp_total = \"$exp\", level=\"$p_lvl\" where id_user_ig = \"$id_user_ig\" 
        ");

        if ($p_lvl != $lvl)
        {
            if (!class_exists('QUESTS'))
            {
                require_once __DIR__ . '/quests.php';
            }
            QUESTS::onLevelChanged($conn, $id_user_ig, $LANG);
        }

        return $p_lvl;
    }
    
    public static function AdjustAnimalLvlFromExp($conn,$id_animal,$LANG)
    {
        $a_lvl = 1;
        $result_animal = $conn->query("
            select * from animals where id_animal = \"$id_animal\"
        ");
        $row_animal = $result_animal->fetch();
        $id_user_ig = $row_animal['id_user_ig'];
        
        $result_user = $conn->query("
            select * from users_ig where id_user_ig = \"$id_user_ig\"
        ");
        $row_user = $result_user->fetch();
        $u_lvl = intval($row_user['level']);
        
        $result_constants = $conn->query("
            select valore from costanti where costante = 'lvl_up_constant_animal'
        ");
        $row_costante = $result_constants->fetch();
        $CONST = intval($row_costante['valore']);
        
        $lvl = intval($row_animal['lvl']); $next_lvl = $lvl+1;        
        $exp = intval($row_animal['experience']);
        if($exp<0)
        {
            $a_lvl = 1;
            $exp = 0;
        }
        else if($exp < $CONST*$lvl*$lvl*$lvl)
        {
            $a_lvl = $lvl-1; 
            // notification for level down
            $notification = "$row_animal[nickname] has lost $exp experience points and is now at level $a_lvl";
            if($LANG=="_it"){$notification = "$row_animal[nickname] ha perso $exp punti esperienza e e' ora al livello $a_lvl";}
            if($LANG=="_pt"){$notification = "$row_animal[nickname] perdeu $exp pontos de experiencia e esta agora no nivel $a_lvl";}
            $notification_type = "lvl_down";
            FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
        }
        else if($exp < $CONST*$next_lvl*$next_lvl*$next_lvl)
        {
            $a_lvl = $lvl;
        }
        else
        {
            $a_lvl = $next_lvl;
            // notification for level up
            $notification = "$row_animal[nickname] has leveled up to level $a_lvl";
            if($LANG=="_it"){$notification = "$row_animal[nickname] ha salito a livello $a_lvl";}
            if($LANG=="_pt"){$notification = "$row_animal[nickname] subiu para o nivel $a_lvl";}
            $notification_type = "lvl_up";
            FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
        }
        
        if($a_lvl>$u_lvl && $a_lvl>5)// PREVENTS ANIMAL FROM LVLING UP IF THE PLAYER LVL IS NOT HIGH ENOUGH
        {
            
            $notification = "$row_animal[nickname] has earned enough exp for lvl $a_lvl, but it cannot grow higher than your current Character level ($u_lvl)!";
            if($LANG=="_it"){$notification = "$row_animal[nickname] ha  guadagnato abbastanza esperienza per livello $a_lvl, ma non puo salire a un livello piu alto del tuo Personaggio (livello $u_lvl)!";}
            if($LANG=="_pt"){$notification = "$row_animal[nickname] recebeu experiencia suficiente para o nivel $a_lvl, mas nao pode subir a um nivel mais alto do que o teu Personagem (nivel $u_lvl)!";}
            $notification_type = "lvl_down";
            FUNZIONI::AddNotification($conn,$id_user_ig,$notification,$notification_type);
            
            if($u_lvl<5){$a_lvl = 5;}
            else
            {
                $a_lvl=$u_lvl;
            }
            
        }
        if($a_lvl<1){$a_lvl=1;}
        
        $result_u = $conn->query("
            update animals set experience = \"$exp\", lvl=\"$a_lvl\" where id_animal = \"$id_animal\" 
        ");
        return $a_lvl;
    }
    
    
    public static function AddExpFromWildAnimal($conn,$id_user_ig,$p_a_id,$w_a_id_species,$w_a_lvl,$LANG,$reward_multiplier=1.0)
    {
        
        $result_wa_exp_reward = $conn->query("
            select reward_atk,reward_def,reward_matk,reward_mdef,reward_hp,reward_acc,reward_eva,reward_cr,reward_spd,reward_exp 
            from species 
            where id_species = \"$w_a_id_species\"
        ");
        $row_wa_exp_reward = $result_wa_exp_reward->fetch();
        // $reward_multiplier lets party PvE split a single wild's rewards evenly
        // across the party (defaults to 1.0, i.e. no change, for solo PvE).
        $rew_exp = (int) round(intval($row_wa_exp_reward['reward_exp'])*intval($w_a_lvl)*$reward_multiplier);
        $reward_atk = (int) round(intval($row_wa_exp_reward['reward_atk'])*$reward_multiplier);
        $reward_def = (int) round(intval($row_wa_exp_reward['reward_def'])*$reward_multiplier);
        $reward_matk = (int) round(intval($row_wa_exp_reward['reward_matk'])*$reward_multiplier);
        $reward_mdef = (int) round(intval($row_wa_exp_reward['reward_mdef'])*$reward_multiplier);
        $reward_hp = (int) round(intval($row_wa_exp_reward['reward_hp'])*$reward_multiplier);
        $reward_acc = (int) round(intval($row_wa_exp_reward['reward_acc'])*$reward_multiplier);
        $reward_eva = (int) round(intval($row_wa_exp_reward['reward_eva'])*$reward_multiplier);
        $reward_cr = (int) round(intval($row_wa_exp_reward['reward_cr'])*$reward_multiplier);
        $reward_spd = (int) round(intval($row_wa_exp_reward['reward_spd'])*$reward_multiplier);
        
        
        $result_user_exp = $conn->query("
            update users_ig set exp_total=exp_total+\"$rew_exp\" where id_user_ig = \"$id_user_ig\"
        ");
        FUNZIONI::AdjustUserLvlFromExp($conn,$id_user_ig,$LANG);
        $result_animal_exp = $conn->query("
            update animals set experience=experience+\"$rew_exp\" where id_animal = \"$p_a_id\"
        ");
        FUNZIONI::AdjustAnimalLvlFromExp($conn,$p_a_id,$LANG);
        
        
        $result_upd = $conn->query("
            update animals set 
                xp_atk = xp_atk+\"$reward_atk\"
               ,xp_def = xp_def+\"$reward_def\"
               ,xp_matk = xp_matk+\"$reward_matk\"
               ,xp_mdef = xp_mdef+\"$reward_mdef\"
               ,xp_hp = xp_hp+\"$reward_hp\"
               ,xp_acc = xp_acc+\"$reward_acc\"
               ,xp_eva = xp_eva+\"$reward_eva\"
               ,xp_cr = xp_cr+\"$reward_cr\"
               ,xp_spd  = xp_spd+\"$reward_spd\"
               where id_animal = \"$p_a_id\"
        ");
    }
    
    
    public static function CheckRequirement($conn, $id_user_ig, $row_req)
    {
        if (is_numeric($row_req))
        {
            $id_requirement = (int) $row_req;
            $result_req = $conn->query("
                select * from requirements where id_requirement = \"$id_requirement\"
            ");
            $row_req = $result_req ? $result_req->fetch(PDO::FETCH_ASSOC) : null;
        }

        if (!is_array($row_req) || empty($row_req['requirement_type']))
        {
            return false;
        }

        if (!isset($row_req['min']) || !array_key_exists('max', $row_req)
            || !array_key_exists('id_ref', $row_req))
        {
            $id_requirement = isset($row_req['id_requirement']) ? (int) $row_req['id_requirement'] : 0;

            if ($id_requirement <= 0)
            {
                return false;
            }

            $result_req = $conn->query("
                select * from requirements where id_requirement = \"$id_requirement\"
            ");
            $catalog = $result_req ? $result_req->fetch(PDO::FETCH_ASSOC) : null;

            if (!$catalog)
            {
                return false;
            }

            $row_req = array_merge($catalog, $row_req);
        }

        if (!function_exists('requirement_value_in_range'))
        {
            require_once __DIR__ . '/requirement_range.php';
        }

        $min = (int) $row_req['min'];
        $max = array_key_exists('max', $row_req) && $row_req['max'] !== '' && $row_req['max'] !== null
            ? (int) $row_req['max']
            : null;
        $id_ref = $row_req['id_ref'];
        
        if($row_req['requirement_type']=="user lvl")
        {
            $result_user = $conn->query("
                select * from users_ig where id_user_ig = \"$id_user_ig\" 
            ");
            $row_user = $result_user->fetch();
            $lvl = intval($row_user['level']);

            return requirement_value_in_range($lvl, $min, $max);
        }
        
        if($row_req['requirement_type']=="number of animals")
        {
            $result_user = $conn->query("
                select id_animal from animals where id_user_ig = \"$id_user_ig\" 
            ");
            $num_animals = $result_user->rowCount();

            return requirement_value_in_range($num_animals, $min, $max);
        }
        
        if($row_req['requirement_type']=="item")
        {
            $result_item = $conn->query("
                select id_item from items 
                where id_user_ig = \"$id_user_ig\" 
                AND id_item_type = \"$id_ref\"
                AND dt_used is null
            ");
            $num_items = $result_item->rowCount();

            return requirement_value_in_range($num_items, $min, $max);
        }

        if ($row_req['requirement_type'] === 'conversation finished'
            || $row_req['requirement_type'] === 'conversation not finished')
        {
            if (!class_exists('PLAYER_CONVERSATIONS'))
            {
                require_once __DIR__ . '/player_conversations.php';
            }

            $finished = PLAYER_CONVERSATIONS::isFinished($conn, $id_user_ig, (int) $id_ref);

            return $row_req['requirement_type'] === 'conversation finished' ? $finished : !$finished;
        }

        if ($row_req['requirement_type'] === 'conversation requirements not met')
        {
            $id_conversation = (int) $id_ref;

            if ($id_conversation <= 0)
            {
                return false;
            }

            $stmt_primary_reqs = $conn->prepare('
                SELECT R.requirement_type, CR.id_requirement, CR.id_ref, CR.ref_table, CR.ref_description,
                       CR.min, CR.max, CR.descrizione
                FROM conversation_requirements CR
                JOIN requirements R ON R.id_requirement = CR.id_requirement
                WHERE CR.id_conversation = :id_conversation
            ');
            $stmt_primary_reqs->execute([':id_conversation' => $id_conversation]);
            $primary_reqs = $stmt_primary_reqs->fetchAll(PDO::FETCH_ASSOC);

            if (count($primary_reqs) === 0)
            {
                return false;
            }

            foreach ($primary_reqs as $primary_req)
            {
                if (($primary_req['requirement_type'] ?? '') === 'conversation requirements not met')
                {
                    continue;
                }

                if (!self::CheckRequirement($conn, $id_user_ig, $primary_req))
                {
                    return true;
                }
            }

            return false;
        }

        if ($row_req['requirement_type'] === 'quest not started'
            || $row_req['requirement_type'] === 'quest started'
            || $row_req['requirement_type'] === 'quest ready to turn in'
            || $row_req['requirement_type'] === 'quest completed'
            || $row_req['requirement_type'] === 'quest phase'
            || $row_req['requirement_type'] === 'quest phase completed')
        {
            if (!class_exists('QUESTS'))
            {
                require_once __DIR__ . '/quests.php';
            }

            $id_quest = (int) $id_ref;
            $quest_phase = (int) $min;

            if ($row_req['requirement_type'] === 'quest not started')
            {
                return QUESTS::isNotStarted($conn, $id_user_ig, $id_quest);
            }

            if ($row_req['requirement_type'] === 'quest started')
            {
                return QUESTS::isStarted($conn, $id_user_ig, $id_quest);
            }

            if ($row_req['requirement_type'] === 'quest ready to turn in')
            {
                return QUESTS::isReadyToTurnIn($conn, $id_user_ig, $id_quest);
            }

            if ($row_req['requirement_type'] === 'quest phase')
            {
                return QUESTS::isOnPhase($conn, $id_user_ig, $id_quest, $quest_phase);
            }

            if ($row_req['requirement_type'] === 'quest phase completed')
            {
                return QUESTS::isPhaseCompleted($conn, $id_user_ig, $id_quest, $quest_phase);
            }

            return QUESTS::isCompleted($conn, $id_user_ig, $id_quest);
        }

        if ($row_req['requirement_type'] === 'player class'
            || $row_req['requirement_type'] === 'player class not')
        {
            $result_user = $conn->query("
                SELECT id_player_class FROM users_ig WHERE id_user_ig = \"$id_user_ig\"
            ");
            $row_user = $result_user ? $result_user->fetch(PDO::FETCH_ASSOC) : null;
            $user_class_id = $row_user ? (int) $row_user['id_player_class'] : 0;
            $target_class_id = (int) $id_ref;

            if ($target_class_id <= 0)
            {
                return false;
            }

            $matches = $user_class_id === $target_class_id;

            if ($row_req['requirement_type'] === 'player class not')
            {
                return !$matches;
            }

            return $matches;
        }

        return false;
    }
    
    
    /**
     * @param array<string, mixed> $row Merged conversation_consequences + consequences row.
     */
    public static function ApplyConsequence($conn, $id_user_ig, $row, $LANG)
    {
        if (!class_exists('CONSEQUENCES'))
        {
            require_once __DIR__ . '/consequences.php';
        }

        return CONSEQUENCES::Apply($conn, $id_user_ig, $row, $LANG);
    }
    
    
    
    public static function SpawnAnimals($conn,$id_zone,$id_spawn_point,$diff,$radius,$x,$z)
    {
        //error_log("SpawnAnimals: $id_zone, $id_spawn_point, $diff, $radius, $x, $z");
        //debug_log("SpawnAnimals: {id_zone: $id_zone, id_spawn_point: $id_spawn_point, diff: $diff, radius: $radius, x: $x, z: $z}");
        for($i=0;$i<$diff;$i++)
        {
            $pos_x = $x + rand(-$radius,$radius);
            $pos_z = $z + rand(-$radius,$radius);
            
            // PRENDO IL TOTALE DI CHANCE POINTS DELLA ZONA
            $result_tot_chance_points = $conn->query("
                select sum(chance_points) tot from zone_animals
                where id_zone = \"$id_zone\"
                AND id_spawn_point = \"$id_spawn_point\"
                group by id_zone, id_spawn_point
            ");
            $row_tot = $result_tot_chance_points->fetch();
            $tot_chance = intval($row_tot['tot']);

            if ($tot_chance <= 0)
            {
                continue;
            }
            
            
            $CHANCE_SELECTED = rand(1,$tot_chance);
            
            
            $chance = 0;
            
            $id_species_selected = -1;
            $lvl_min_selected = 1;
            $lvl_max_selected = 1;
            // PRENDO LA LISTA DEGLI ANIMALI DELLA ZONA, CON IL SUO VALORE DI CHANCE_POINTS
            $result_zone_animals = $conn->query("
                select id_species,chance_points,lvl_min,lvl_max from zone_animals 
                where id_zone = \"$id_zone\"
                AND id_spawn_point = \"$id_spawn_point\"
                order by chance_points desc
            ");
            while($row_zone_animals = $result_zone_animals->fetch())
            {
                $id_species_while = $row_zone_animals['id_species'];
                $chance_points = intval($row_zone_animals['chance_points']);
                
                $chance+=$chance_points;
                
                $lvl_min_while = $row_zone_animals['lvl_min'];
                $lvl_max_while = $row_zone_animals['lvl_max'];
                
                if($id_species_selected == -1)
                {// SE NON � STATO ANCORA DECISA LA species, VERIFICO
                    if($CHANCE_SELECTED<$chance)
                    {// SE IL VALORE SELEZIONATO � SOTTO IL TOTALE ATTUALE, SELEZIONO LA species ATTUALE
                        $id_species_selected = $id_species_while;
                        $lvl_min_selected = $lvl_min_while;
                        $lvl_max_selected = $lvl_max_while;
                    }
                }
                
            }
            
            if($id_species_selected == -1)
            {// SE NON HO SELEZIONATO NESSUNA species PER QUALCHE MOTIVO, SELEZIONO L'ULTIMA species VISTA
                $id_species_selected = $id_species_while;
                $lvl_min_selected = $lvl_min_while;
                $lvl_max_selected = $lvl_max_while;
            }
            
            // IN QUESTO MOMENTO HO PER FORZA SELEZIONATO UNA species 
            
            // seleziono il livello 
            $lvl_selected = rand($lvl_min_selected,$lvl_max_selected);
            
            $id_element_selected = rand(1,7);
            /*$result_element = $conn->query("
                select element$LANG from elements where id_element = \"$id_element_selected\"
            ");
            $row_element = $result_element->fetch();
            $element_selected = $row_element[0];*/
            $element_selected = "";
            
            $result_wild_species = $conn->query("
                select base_atk,base_def,base_matk,base_mdef,base_hp,base_acc,base_eva,base_cr,base_spd,species 
                from species 
                where id_species = \"$id_species_selected\"
            ");
            $row_wild_species = $result_wild_species->fetch();
            $species_selected = $row_wild_species['species'];
            
            //    HP = floor(0.01 x (2 x Base + IV + floor(0.25 x EV)) x Level) + Level + 10.
            //    Other Stats = floor(0.01 x (2 x Base + IV + floor(0.25 x EV)) x Level) + 5) x Nature.
            //$OtherStats = floor(0.01 * (2 * $row_wild_species['base_atk'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5)
            $DNA = 15;
            $PTS = 15;
            $EXP = 15;
            
            $wild_animal_hp = floor(0.01 * (2 * $row_wild_species['base_hp'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + $lvl_selected + 10;
            
            $wild_animal_atk = floor(0.01 * (2 * $row_wild_species['base_atk'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5; // 15 DNA ; 15 pts ; 15 exp (THIS IS HALF OF MAX)
            $wild_animal_def = floor(0.01 * (2 * $row_wild_species['base_def'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5;
            $wild_animal_matk = floor(0.01 * (2 * $row_wild_species['base_matk'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5;
            $wild_animal_mdef = floor(0.01 * (2 * $row_wild_species['base_mdef'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5;
            $wild_animal_spd = floor(0.01 * (2 * $row_wild_species['base_spd'] + $DNA + floor(0.25 * $PTS) + floor(0.25 * $EXP)) * $lvl_selected) + 5;
            
            $wild_animal_acc = $row_wild_species['base_acc'];
            $wild_animal_eva = $row_wild_species['base_eva'];
            $wild_animal_cr = $row_wild_species['base_cr'];
            
            
            $result_ins = $conn->query("
                insert into wild_animals
                (dt_creazione,id_species,level,id_element
                    ,atk,def,matk,mdef
                    ,hp,acc,eva,cr,spd
                    ,id_zone,id_spawn_point,pos_x,pos_y,pos_z
                    )
                values
                (now(),\"$id_species_selected\",\"$lvl_selected\",\"$id_element_selected\"
                    ,\"$wild_animal_atk\",\"$wild_animal_def\",\"$wild_animal_matk\",\"$wild_animal_mdef\"
                    ,\"$wild_animal_hp\",\"$wild_animal_acc\",\"$wild_animal_eva\",\"$wild_animal_cr\",\"$wild_animal_spd\"
                    ,\"$id_zone\",\"$id_spawn_point\",\"$pos_x\",0,\"$pos_z\"
                    )
            ");
            $id_wild_animal = $conn->lastInsertId();
            

        }
    }
    
}

/**
 * Max XZ distance for trade / party / duel requests (from costanti table).
 *
 * @param string $kind trade|party|duel
 */
function animaster_request_distance($kind)
{
    $defaults = [
        'trade' => 50,
        'party' => 1000,
        'duel' => 50,
    ];
    $name = 'request_distance_' . $kind;

    if (defined($name))
    {
        return (int) constant($name);
    }

    return (int) ($defaults[$kind] ?? 50);
}

function animaster_distance_xz($x1, $z1, $x2, $z2)
{
    $dx = (float) $x1 - (float) $x2;
    $dz = (float) $z1 - (float) $z2;

    return sqrt(($dx * $dx) + ($dz * $dz));
}
?>