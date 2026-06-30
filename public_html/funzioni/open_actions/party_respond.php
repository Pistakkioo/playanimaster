<?php
require __DIR__ . '/party_action_bootstrap.php';

animaster_party_open_action(function ($conn, $id_user_ig, $post)
{
    $id_party_invite = isset($post['id_party_invite']) ? (int) $post['id_party_invite'] : 0;
    $accept = isset($post['accept']) && trim((string) $post['accept']) === 'S';

    return animaster_party_respond($conn, $id_user_ig, $id_party_invite, $accept);
});
