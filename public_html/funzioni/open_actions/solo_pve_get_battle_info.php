<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

$stringone = "";
$b_status = "ongoing";

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$id_battle = isset($_POST['id_battle']) ? (int) $_POST['id_battle'] : 0;
$battle_type = isset($_POST['battle_type']) ? $_POST['battle_type'] : '';
$turn = isset($_POST['turn']) ? (int) $_POST['turn'] : 0;
$restarting_old_battle = isset($_POST['restarting_old_battle']) ? $_POST['restarting_old_battle'] : 'N';
$LANG = isset($_POST['lang']) ? $_POST['lang'] : '_it';

if ($id_user_ig <= 0 || $id_battle <= 0)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'INVALID_BATTLE_REQUEST',
        'response' => ''
    ]);
    exit;
}

$result_battle_owner = $conn->query("
    select id_battle_solo_pve, id_zone
    from battles_solo_pve
    where id_battle_solo_pve = \"$id_battle\"
    AND id_user_ig = \"$id_user_ig\"
");
$row_battle_owner = $result_battle_owner ? $result_battle_owner->fetch() : false;

if (!$row_battle_owner)
{
    echo json_encode([
        'stato' => 'KO',
        'msg' => 'BATTLE_NOT_FOUND',
        'response' => ''
    ]);
    exit;
}

$id_zone = $row_battle_owner['id_zone'];

$lvl_up_constant_animal = 41;
$lvl_up_constant_player = 81;
$exp_loss_percent_on_death = 5;

$result_costanti = $conn->query("
    select costante,valore from costanti
");
while($row_costanti=$result_costanti->fetch())
{
    $chiave = $row_costanti['costante'];
    $$chiave = intval($row_costanti['valore']);
}

if($turn>0 && $restarting_old_battle!="S")
{
    $type = $_POST['type'];
    $id = $_POST['id'];
    
    //error_log("turn:$turn ; type:$type ; id:$id ---------------------");
    
    $previous_turn = intval($turn)-1;
    
    // PRENDO I RISULTATI DELL'ULTIMO TURNO
    $result_last_move = $conn->query("
        select id_battle_solo_pve_move,id_battle_solo_pve,turn,move_type,id_rif,move_speed,protagonist_type,id_protagonist,target_type,id_target
            ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
            ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
            ,w_a_id,w_a_id_element,w_a_id_species,WL.species$LANG as w_a_species,w_a_lvl,WL.species$LANG as w_a_nickname
            ,p_a_id,p_a_id_element,p_a_id_species,PL.species$LANG as p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
            ,move_description,move_hit,resulting_battle_status,WE.element$LANG as w_a_element,PE.element$LANG as p_a_element,WE.color as w_a_element_color,PE.color as p_a_element_color
        from battles_solo_pve_moves M
        left join elements WE ON WE.id_element = M.w_a_id_element
        left join elements PE ON PE.id_element = M.p_a_id_element
        left join species WL ON WL.id_species = M.w_a_id_species
        left join species PL ON PL.id_species = M.p_a_id_species
    where id_battle_solo_pve = \"$id_battle\"
        AND turn = \"$previous_turn\"
        order by order_in_turn desc
        limit 1
    ");
    $row_last_move = $result_last_move->fetch();

    if (!$row_last_move)
    {
        echo json_encode([
            'stato' => 'KO',
            'msg' => 'NO_PREVIOUS_TURN',
            'response' => ''
        ]);
        exit;
    }
    
    $w_a_id = $row_last_move['w_a_id'];
    $w_a_id_element = $row_last_move['w_a_id_element'];
    $w_a_id_species = $row_last_move['w_a_id_species'];
    $w_a_species = $row_last_move['w_a_species'];
    $w_a_lvl = $row_last_move['w_a_lvl'];
    $w_a_nickname = $row_last_move['w_a_nickname'];
    
    $p_a_id = $row_last_move['p_a_id'];
    $p_a_id_element = $row_last_move['p_a_id_element'];
    $p_a_id_species = $row_last_move['p_a_id_species'];
    $p_a_species = $row_last_move['p_a_species'];
    $p_a_lvl = $row_last_move['p_a_lvl'];
    $p_a_nickname = $row_last_move['p_a_nickname'];
    $p_a_cur_exp = $row_last_move['p_a_cur_exp'];
    
    $w_a_res_atk = floatval($row_last_move['w_a_res_atk']);
    $w_a_res_def = floatval($row_last_move['w_a_res_def']);
    $w_a_res_matk = floatval($row_last_move['w_a_res_matk']);
    $w_a_res_mdef = floatval($row_last_move['w_a_res_mdef']);
    $w_a_res_hp = intval($row_last_move['w_a_res_hp']);
    $w_a_res_acc = intval($row_last_move['w_a_res_acc']);
    $w_a_res_eva = intval($row_last_move['w_a_res_eva']);
    $w_a_res_cr = intval($row_last_move['w_a_res_cr']);
    $w_a_res_spd = floatval($row_last_move['w_a_res_spd']);$w_a_prev_spd = $w_a_res_spd;
    $w_a_res_max_hp = intval($row_last_move['w_a_res_max_hp']);
    
    $p_a_res_atk = floatval($row_last_move['p_a_res_atk']);
    $p_a_res_def = floatval($row_last_move['p_a_res_def']);
    $p_a_res_matk = floatval($row_last_move['p_a_res_matk']);
    $p_a_res_mdef = floatval($row_last_move['p_a_res_mdef']);
    $p_a_res_hp = intval($row_last_move['p_a_res_hp']);
    $p_a_res_acc = intval($row_last_move['p_a_res_acc']);
    $p_a_res_eva = intval($row_last_move['p_a_res_eva']);
    $p_a_res_cr = intval($row_last_move['p_a_res_cr']);
    $p_a_res_spd = floatval($row_last_move['p_a_res_spd']);$p_a_prev_spd = $p_a_res_spd;
    $p_a_res_max_hp = intval($row_last_move['p_a_res_max_hp']);
    
    $PLAYER_FIRST = false;$p_order_in_turn = 2;$w_order_in_turn = 1;
    if($type!="ability")// use_item/switch_animal/run 
    {
        $PLAYER_FIRST = true;$p_order_in_turn = 1;$w_order_in_turn = 2;
    }
    else if($p_a_res_spd>$w_a_res_spd)// player animal is faster than wild animal
    {
        $PLAYER_FIRST = true;$p_order_in_turn = 1;$w_order_in_turn = 2;
    }
    
    
        
    
    if(!$PLAYER_FIRST)
    {//error_log("WILD ANIMAL FIRST");
        // WILD ANIMAL TURN GOES FIRST
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/w_a_move.php');        
    }
    
    // PLAYER TURN GOES HERE
    if($type=="action" && $id==4) // ESCAPE
    {
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/p_move_escape.php'); 
    }
    else if($type=="switch")
    {
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/p_move_switch.php'); 
    }
    else if($type=="use_on")
    {
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/p_move_use_item.php'); 
    }
    else if($type=="ability")
    {
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/p_a_move.php'); 
    }
    // END PLAYER TURN
    
    if($PLAYER_FIRST)
    {//error_log("PLAYER FIRST");
        // WILD ANIMAL TURN GOES AFTER        
        include($_SERVER['DOCUMENT_ROOT'].'/funzioni/battle_solo_pve/w_a_move.php');
    }           
}


$moves_sql = "
    select id_battle_solo_pve_move,id_battle_solo_pve,turn,move_type,id_rif,move_speed,protagonist_type,id_protagonist,target_type,id_target
            ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
            ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
            ,w_a_id,w_a_id_element,w_a_id_species,WL.species$LANG as w_a_species,w_a_lvl,WL.species$LANG as w_a_nickname
            ,p_a_id,p_a_id_element,p_a_id_species,PL.species$LANG as p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
            ,move_description,move_hit,resulting_battle_status,WE.element$LANG as w_a_element,PE.element$LANG as p_a_element,WE.color as w_a_element_color,PE.color as p_a_element_color
        from battles_solo_pve_moves M
        left join elements WE ON WE.id_element = M.w_a_id_element
        left join elements PE ON PE.id_element = M.p_a_id_element
        left join species WL ON WL.id_species = M.w_a_id_species
        left join species PL ON PL.id_species = M.p_a_id_species
    where id_battle_solo_pve = \"$id_battle\"
    AND turn = \"$turn\"
    order by move_speed desc
";

$result_moves = $conn->query($moves_sql);

if ($restarting_old_battle == "S" && $result_moves && $result_moves->rowCount() == 0)
{
    $result_max_turn = $conn->query("
        select max(turn) from battles_solo_pve_moves
        where id_battle_solo_pve = \"$id_battle\"
    ");
    $row_max_turn = $result_max_turn ? $result_max_turn->fetch() : false;
    $fallback_turn = $row_max_turn ? intval($row_max_turn[0]) : -1;

    if ($fallback_turn >= 0 && $fallback_turn != $turn)
    {
        $turn = $fallback_turn;
        $result_moves = $conn->query("
            select id_battle_solo_pve_move,id_battle_solo_pve,turn,move_type,id_rif,move_speed,protagonist_type,id_protagonist,target_type,id_target
                    ,w_a_res_atk,w_a_res_def,w_a_res_matk,w_a_res_mdef,w_a_res_hp,w_a_res_acc,w_a_res_eva,w_a_res_cr,w_a_res_spd,w_a_res_max_hp
                    ,p_a_res_atk,p_a_res_def,p_a_res_matk,p_a_res_mdef,p_a_res_hp,p_a_res_acc,p_a_res_eva,p_a_res_cr,p_a_res_spd,p_a_res_max_hp
                    ,w_a_id,w_a_id_element,w_a_id_species,WL.species$LANG as w_a_species,w_a_lvl,WL.species$LANG as w_a_nickname
                    ,p_a_id,p_a_id_element,p_a_id_species,PL.species$LANG as p_a_species,p_a_lvl,p_a_nickname,p_a_cur_exp
                    ,move_description,move_hit,resulting_battle_status,WE.element$LANG as w_a_element,PE.element$LANG as p_a_element,WE.color as w_a_element_color,PE.color as p_a_element_color
                from battles_solo_pve_moves M
                left join elements WE ON WE.id_element = M.w_a_id_element
                left join elements PE ON PE.id_element = M.p_a_id_element
                left join species WL ON WL.id_species = M.w_a_id_species
                left join species PL ON PL.id_species = M.p_a_id_species
            where id_battle_solo_pve = \"$id_battle\"
            AND turn = \"$turn\"
            order by move_speed desc
        ");
    }
}


while($row_move = $result_moves->fetch())
{
    $singolo_json = json_encode($row_move);
    if($stringone!=""){$stringone.="#";}
    $stringone.=$singolo_json;
}





if (!$result_moves)
{
    $stato = "KO";
    $msg = "KO";
}
else if ($stringone === "")
{
    $stato = "KO";
    $msg = "NO_BATTLE_MOVES";
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

//error_log(json_encode($riga));

echo json_encode($riga);


?>