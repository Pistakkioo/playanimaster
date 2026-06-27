<?php

function animaster_get_conn()
{
    if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO)
    {
        return $GLOBALS['conn'];
    }

    require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/i.php';

    if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof PDO)
    {
        return $GLOBALS['conn'];
    }

    global $conn;

    if ($conn instanceof PDO)
    {
        $GLOBALS['conn'] = $conn;

        return $conn;
    }

    throw new RuntimeException('Database connection unavailable');
}

function animaster_build_login_envelope($conn, array $profile_row)
{
    $id_user = (int) $profile_row['id_user'];

    $stringone_costanti = '{';
    $result_costanti = $conn->query('SELECT costante, valore FROM costanti');

    while ($row_costanti = $result_costanti->fetch())
    {
        $costante = $row_costanti['costante'];
        $valore = $row_costanti['valore'];

        if ($stringone_costanti !== '{')
        {
            $stringone_costanti .= ',';
        }

        $stringone_costanti .= '"' . $costante . '":' . $valore;
    }

    $stringone_costanti .= '}';

    $id_user_ig = (int) $profile_row['id_user_ig'];

    $stringone_is_battling = '';
    $result_pvp = $conn->query("
        SELECT id_battle_pvp, current_turn
        FROM battles_pvp
        WHERE flg_status = 'O'
          AND (id_user_ig_a = \"$id_user_ig\" OR id_user_ig_b = \"$id_user_ig\")
        ORDER BY id_battle_pvp DESC
        LIMIT 1
    ");

    if ($result_pvp && $result_pvp->rowCount() > 0)
    {
        $row_pvp = $result_pvp->fetch(PDO::FETCH_ASSOC);
        $stringone_is_battling = json_encode([
            'isBattling' => true,
            'id_battle' => (int) $row_pvp['id_battle_pvp'],
            'battle_type' => 'pvp',
            'current_battle_turn' => (int) $row_pvp['current_turn']
        ]);
    }
    else
    {
        $result_battle = $conn->query("
            SELECT id_battle_solo_pve FROM battles_solo_pve
            WHERE id_user_ig = \"$id_user_ig\"
            AND (finished IS NULL OR finished != 'S')
        ");

        if ($result_battle->rowCount() > 0)
        {
            $row_battle = $result_battle->fetch();
            $id_battle = $row_battle['id_battle_solo_pve'];
            $current_battle_turn = 0;
            $result_turn = $conn->query("
                SELECT MAX(turn) FROM battles_solo_pve_moves
                WHERE id_battle_solo_pve = \"$id_battle\"
            ");
            $row_turn = $result_turn->fetch();
            $current_battle_turn = intval($row_turn[0]);

            $stringone_is_battling = json_encode([
                'isBattling' => true,
                'id_battle' => $id_battle,
                'battle_type' => 'solo_pve',
                'current_battle_turn' => $current_battle_turn
            ]);
        }
    }

    return [
        'stato' => 'OK',
        'msg' => 'OK',
        'response' => json_encode(animaster_normalize_profile($profile_row)),
        'response2' => $stringone_costanti,
        'response3' => $stringone_is_battling
    ];
}

function animaster_fetch_character_profile_row($conn, $id_user, $id_user_ig)
{
    $result = $conn->query("
        SELECT UI.id_user_ig
              ,UI.id_user
              ,UI.exp_total
              ,UI.gold
              ,UI.id_zone
              ,UI.`level`
              ,UI.position_x
              ,UI.position_y
              ,UI.position_z
              ,UI.`direction`
              ,UI.position_x_last_recover
              ,UI.position_y_last_recover
              ,UI.position_z_last_recover
              ,UI.id_zone_last_recover
              ,UI.gender
              ,UI.character_type
              ,UI.move_speed
              ,UI.display_name
              ,U.username
              ,U.email
              ,Z.scene_name
        FROM users_ig UI
            JOIN users U ON U.id_user = UI.id_user
            LEFT JOIN zones Z ON Z.id_zone = UI.id_zone
        WHERE UI.id_user = \"$id_user\"
            AND UI.id_user_ig = \"$id_user_ig\"
    ");

    if (!$result || $result->rowCount() === 0)
    {
        return null;
    }

    return $result->fetch(PDO::FETCH_ASSOC);
}

function animaster_resolve_character_id($conn, $id_user, $id_user_ig)
{
    $id_user = (int) $id_user;
    $id_user_ig = (int) $id_user_ig;

    if ($id_user_ig > 0)
    {
        return $id_user_ig;
    }

    return 0;
}

function animaster_normalize_profile(array $profile)
{
    $profile['id_user'] = (int) (isset($profile['id_user']) ? $profile['id_user'] : 0);
    $profile['id_user_ig'] = (int) (isset($profile['id_user_ig']) ? $profile['id_user_ig'] : 0);

    return $profile; 
}

function animaster_update_character_presence($conn, $id_user, $id_user_ig, $posx, $posy, $posz, $target_posx, $target_posy, $target_posz)
{
    $id_user_ig = animaster_resolve_character_id($conn, $id_user, $id_user_ig);

    if ($id_user_ig <= 0)
    {
        return 0;
    }

    $conn->query("
        UPDATE users_ig
        SET last_online = NOW()
           ,flg_online = 'S'
           ,position_x = \"$posx\"
           ,position_y = \"$posy\"
           ,position_z = \"$posz\"
           ,target_position_x = \"$target_posx\"
           ,target_position_y = \"$target_posy\"
           ,target_position_z = \"$target_posz\"
        WHERE id_user_ig = \"$id_user_ig\"
    ");

    animaster_mark_sibling_characters_offline($conn, $id_user, $id_user_ig);

    return $id_user_ig;
}

function animaster_mark_sibling_characters_offline($conn, $id_user, $except_id_user_ig)
{
    $id_user = (int) $id_user;
    $except_id_user_ig = (int) $except_id_user_ig;

    if ($except_id_user_ig <= 0)
    {
        return false;
    }

    if ($id_user <= 0)
    {
        $result_user = $conn->query("
            SELECT id_user FROM users_ig WHERE id_user_ig = \"$except_id_user_ig\"
        ");
        $row_user = $result_user ? $result_user->fetch(PDO::FETCH_ASSOC) : null;

        if (!$row_user)
        {
            return false;
        }

        $id_user = (int) $row_user['id_user'];
    }

    return $conn->query("
        UPDATE users_ig
        SET flg_online = 'N'
        WHERE id_user = \"$id_user\"
          AND id_user_ig != \"$except_id_user_ig\"
    ");
}

function animaster_mark_character_offline($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    return $conn->query("
        UPDATE users_ig
        SET flg_online = 'N'
        WHERE id_user_ig = \"$id_user_ig\"
    ");
}

function animaster_mark_character_online($conn, $id_user_ig)
{
    $id_user_ig = (int) $id_user_ig;

    $result_user = $conn->query("
        SELECT id_user FROM users_ig WHERE id_user_ig = \"$id_user_ig\"
    ");
    $row_user = $result_user ? $result_user->fetch() : null;

    if ($row_user)
    {
        animaster_mark_sibling_characters_offline($conn, (int) $row_user['id_user'], $id_user_ig);
    }

    return $conn->query("
        UPDATE users_ig
        SET last_online = NOW()
           ,flg_online = 'S'
        WHERE id_user_ig = \"$id_user_ig\"
    ");
}

function animaster_fetch_characters_for_user($conn, $id_user)
{
    $result = $conn->query("
        SELECT id_user_ig
              ,display_name
              ,gender
              ,character_type
              ,`level`
              ,id_zone
              ,flg_online
        FROM users_ig
        WHERE id_user = \"$id_user\"
        ORDER BY dt_creazione ASC
    ");

    if (!$result)
    {
        return [];
    }

    return $result->fetchAll(PDO::FETCH_ASSOC);
}

function animaster_post_id_user_ig()
{
    return isset($_POST['id_user_ig']) ? (int) $_POST['id_user_ig'] : 0;
}
