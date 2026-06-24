<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";

$id_user = $_POST['id_user'];
$id_zone = $_POST['id_zone'];
$LANG = $_POST['lang'];

$result = $conn->query("
    insert into battles_solo_pve
    (dt_creazione,id_user,id_zone)
    VALUES
    (now(),\"$id_user\",\"$id_zone\")
");
$id_battle = $conn->lastInsertId();



// PRENDO IL TOTALE DI CHANCE POINTS DELLA ZONA
$result_tot_chance_points = $conn->query("
    select sum(chance_points) tot from zone_animals
    where id_zone = \"$id_zone\"
");
$row_tot = $result_tot_chance_points->fetch();
$tot_chance = intval($row_tot[0]);


$CHANCE_SELECTED = rand(1,$tot_chance);


$chance = 0;

$id_species_selected = -1;
$lvl_min_selected = 1;
$lvl_max_selected = 1;
// PRENDO LA LISTA DEGLI ANIMALI DELLA ZONA, CON IL SUO VALORE DI CHANCE_POINTS
$result_zone_animals = $conn->query("
    select id_species,chance_points,lvl_min,lvl_max from zone_animals 
    where id_zone = \"$id_zone\"
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
    {// SE NON è STATO ANCORA DECISA LA species, VERIFICO
        if($CHANCE_SELECTED<$chance)
        {// SE IL VALORE SELEZIONATO è SOTTO IL TOTALE ATTUALE, SELEZIONO LA species ATTUALE
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
    (dt_creazione,id_species,level,battle_type,id_battle,id_element
        ,atk,def,matk,mdef
        ,hp,acc,eva,cr,spd)
    values
    (now(),\"$id_species_selected\",\"$lvl_selected\",'solo_pve',\"$id_battle\",\"$id_element_selected\"
        ,\"$wild_animal_atk\",\"$wild_animal_def\",\"$wild_animal_matk\",\"$wild_animal_mdef\"
        ,\"$wild_animal_hp\",\"$wild_animal_acc\",\"$wild_animal_eva\",\"$wild_animal_cr\",\"$wild_animal_spd\")
");
$id_wild_animal = $conn->lastInsertId();


$result_first_animal = $conn->query("
    select id_animal from animals 
    where id_user = \"$id_user\"
    and team_position > 0
    AND current_hp > 0
    order by team_position
    limit 1 
");
$row_first_animal = $result_first_animal->fetch();
$id_first_animal = intval($row_first_animal[0]);


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
    

if($user_animal_current_hp=="")
{
    // SET THE CURRENT HP OF THE ANIMAL TO MAX HP
    $result_hp = $conn->query("
        update animals set current_hp = \"$user_animal_hp\", max_hp = \"$user_animal_hp\"
        where id_animal = \"$id_first_animal\"
    ");
    $user_animal_current_hp=$user_animal_hp;
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
      ,\"$id_wild_animal\",\"$id_element_selected\",\"$id_species_selected\",\"$species_selected\",\"$lvl_selected\"
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