<?php

require dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';

require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';



$username = $_POST['username'];

$password = $_POST['password'];

$c_pass = md5(md5($password));

$id_user_ig = isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;



$stato = "OK";

$msg = "OK";

$row = null;

$id_user = null;



$result_user = $conn->query("

    SELECT id_user

    FROM users

    WHERE username = \"$username\"

        AND password = \"$c_pass\"

");



if (!$result_user)

{

    $stato = "KO";

    $msg = "Failed to connect";

}

else if ($result_user->rowCount() === 0)

{

    $stato = "KO";

    $msg = "Incorrect login";

}

else

{

    $user_row = $result_user->fetch();

    $id_user = (int) $user_row['id_user'];



    if ($id_user_ig <= 0)

    {

        $result_default = $conn->query("

            SELECT id_user_ig

            FROM users_ig

            WHERE id_user = \"$id_user\"

            ORDER BY dt_creazione ASC

            LIMIT 1

        ");



        if ($result_default && $result_default->rowCount() > 0)

        {

            $default_row = $result_default->fetch();

            $id_user_ig = (int) $default_row['id_user_ig'];

        }

    }



    if ($id_user_ig <= 0)

    {

        $stato = "KO";

        $msg = "No character found";

    }

    else

    {

        $row = animaster_fetch_character_profile_row($conn, $id_user, $id_user_ig);



        if (!$row)

        {

            $stato = "KO";

            $msg = "Character not found";

        }

        else

        {

            animaster_mark_character_online($conn, $id_user_ig);



            $stringone_is_battling = "";

            require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/solo_pve.php';

            $solo_battle = animaster_solo_pve_active_for_user($conn, $id_user_ig);

            if ($solo_battle)

            {

                $riga_battle = array(

                    "isBattling"=>true,

                    "id_battle"=>(int) $solo_battle['id_battle'],

                    "battle_type"=>'solo_pve',

                    "current_battle_turn"=>(int) $solo_battle['current_battle_turn']

                );

                $stringone_is_battling = json_encode($riga_battle);

            }
            else
            {
                require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/party_pve.php';

                $party_battle = animaster_party_pve_active_for_user($conn, $id_user_ig);

                if ($party_battle)
                {
                    $riga_battle = [
                        'isBattling' => true,
                        'id_battle' => (int) $party_battle['id_battle'],
                        'battle_type' => 'party_pve',
                        'current_battle_turn' => (int) $party_battle['current_battle_turn']
                    ];

                    $stringone_is_battling = json_encode($riga_battle);
                }
            }

        }

    }

}



if (!isset($stringone_is_battling))

{

    $stringone_is_battling = ""; 

}



$stringone_costanti = "{";

$result_costanti = $conn->query("

    select costante,valore from costanti

");

while($row_costanti=$result_costanti->fetch())

{

    $costante = $row_costanti['costante'];

    $valore = $row_costanti['valore'];

    if($stringone_costanti!="{"){$stringone_costanti.=",";}

    $stringone_costanti.="\"$costante\":$valore";

}

$stringone_costanti.="}";



$riga = array(

    "stato"=>$stato,

    "msg"=>$msg,

    "response"=>json_encode($row),

    "response2"=>$stringone_costanti,

    "response3"=>$stringone_is_battling

);



echo json_encode($riga);



?>

