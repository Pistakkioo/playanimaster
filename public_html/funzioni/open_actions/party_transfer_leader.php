<?php
require __DIR__ . '/party_action_bootstrap.php';

animaster_party_open_action(function ($conn, $id_user_ig, $post)
{
    $id_new_leader = isset($post['id_new_leader']) ? (int) $post['id_new_leader'] : 0;

    return animaster_party_transfer_leader($conn, $id_user_ig, $id_new_leader);
});
