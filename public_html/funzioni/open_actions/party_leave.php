<?php
require __DIR__ . '/party_action_bootstrap.php';

animaster_party_open_action(function ($conn, $id_user_ig)
{
    return animaster_party_leave($conn, $id_user_ig);
});
