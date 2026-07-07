<?php
require __DIR__ . '/party_action_bootstrap.php';

animaster_party_open_action(function ($conn, $id_user_ig, $post)
{
    $setting = isset($post['setting']) ? (string) $post['setting'] : '';
    $value = isset($post['value']) ? (string) $post['value'] : '';
    $enabled = $value === 'S' || $value === '1' || $value === 'true';

    if ($setting === 'allow_inactivity_vote')
    {
        return animaster_party_set_inactivity_vote($conn, $id_user_ig, $enabled);
    }

    return ['error' => 'UNKNOWN_SETTING'];
});
