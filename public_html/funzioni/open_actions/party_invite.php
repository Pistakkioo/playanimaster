<?php
require __DIR__ . '/party_action_bootstrap.php';

animaster_party_open_action(function ($conn, $id_user_ig, $post)
{
    $id_target = isset($post['id_target']) ? (int) $post['id_target'] : 0;

    return animaster_party_invite($conn, $id_user_ig, $id_target);
});
