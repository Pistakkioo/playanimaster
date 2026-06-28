<?php
require ($_SERVER['DOCUMENT_ROOT'].'/funzioni/i.php');

if (!class_exists('PLAYER_CONVERSATIONS'))
{
    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/player_conversations.php';
}

$stringone = "";

$id_zone = $_POST['id_zone'];
$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
$LANG = $_POST['lang'];
if($LANG!="")
{
    if($LANG[0]!="_")
    {
        $LANG = "_".$LANG;
    }
}

// Presence/position: get_other_players.php only (by id_user_ig). This endpoint must not touch users_ig.

$result = $conn->query("
    select *
    from npcs
    where 1=1
    AND id_zone = \"$id_zone\"
");
while($row = $result->fetch())
{
    $id_npc = $row['id_npc'];
    $requirements_met = true;
    $result_requirements = $conn->query("
        select R.* from npc_requirements NR
        JOIN requirements R ON R.id_requirement = NR.id_requirement
        where id_npc = \"$id_npc\"
    ");
    while($row_req = $result_requirements->fetch())
    {
        if (!FUNZIONI::CheckRequirement($conn, $id_user_ig, $row_req['id_requirement']))
        {
            $requirements_met = false;
            break;
        }
    }
    
    if($requirements_met)
    {
        $dialogues = "";
        $result_conversations = $conn->query("
            select id_conversation,id_npc,title$LANG as title,flg_register from conversations where id_npc = \"$id_npc\"
        ");
        while($row_conv = $result_conversations->fetch())
        {
            $requirements_met2 = true;
            $id_conversation = $row_conv['id_conversation'];
            $result_requirements = $conn->query("
                select R.* from conversation_requirements CR
                JOIN requirements R ON R.id_requirement = CR.id_requirement
                where id_conversation = \"$id_conversation\"
            ");
            while($row_req = $result_requirements->fetch())
            {
                if (!FUNZIONI::CheckRequirement($conn, $id_user_ig, $row_req['id_requirement']))
                {
                    $requirements_met2 = false;
                    break;
                }
            }
            
            if($requirements_met2)
            {
                if ($row_conv['flg_register'] === 'S'
                    && PLAYER_CONVERSATIONS::isFinished($conn, $id_user_ig, $id_conversation))
                {
                    continue;
                }

                $result_dialogs = $conn->query("
                    select id_dialog,`order`,flg_last,flg_options,dialog$LANG as dialog
                    from dialogues
                    where id_conversation = \"$id_conversation\"
                    order by `order` asc, id_dialog asc
                ");
                while($row_dlg=$result_dialogs->fetch())
                {
                    
                    $id_dialog = $row_dlg['id_dialog'];
                    
                    $dialogOptions = "";
                    if($row_dlg['flg_options']=="S")
                    {
                        $result_options = $conn->query("
                            select id_dialog_option id_option,option_color,option_text$LANG option_text from dialogues_options where id_dialog = \"$id_dialog\"
                        ");
                        while($row_options = $result_options->fetch())
                        {
                            $single_option = array(
                                "id_option"=>$row_options['id_option'],
                                "option_color"=>$row_options['option_color'],
                                "option_text"=>$row_options['option_text']
                            );
                            $single_opt_json = json_encode($single_option);
                            if($dialogOptions!=""){$dialogOptions.="[SPLITTER.O]";}
                            $dialogOptions.=$single_opt_json;
                        }
                    }
                    
                    
                    $dlg_arr = array(
                        "id_conversation"=>$row_conv['id_conversation'],
                        "id_npc"=>$row_conv['id_npc'],
                        "title"=>$row_conv['title'],
                        "flg_register"=>$row_conv['flg_register'],
                        "id_dialog"=>$row_dlg['id_dialog'],
                        "order"=>$row_dlg['order'],
                        "flg_last"=>$row_dlg['flg_last'],
                        "flg_options"=>$row_dlg['flg_options'],
                        "dialog"=>$row_dlg['dialog'],
                        "dialogOptionsStringone"=>$dialogOptions
                    );
                    $single_dlg_json = json_encode($dlg_arr);
                    if($dialogues!=""){$dialogues.="[SPLITTER.D]";}
                    $dialogues.=$single_dlg_json;
                }
            }
            
                
        }
        
        $npc_arr = array(
            "id_npc"=>$row['id_npc'],
            "npc"=>$row['npc'],
            "type"=>$row['type'],
            "id_zone"=>$row['id_zone'],
            "posx"=>$row['posx'],
            "posy"=>$row['posy'],
            "posz"=>$row['posz'],
            "wander_range"=>$row['wander_range'],
            "euler_x"=>$row['euler_x'],
            "euler_y"=>$row['euler_y'],
            "euler_z"=>$row['euler_z'],
            "sight_distance"=>$row['sight_distance'],
            "gender"=>$row['gender'],
            "npc_type_prefab"=>$row['npc_type_prefab'],
            "dialogues"=>$dialogues
        );
        $singolo_json = json_encode($npc_arr);
        if($stringone!=""){$stringone.="#";}
        $stringone.=$singolo_json;
    }
        
}




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
