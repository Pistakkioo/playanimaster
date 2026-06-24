<?php
require __DIR__ . '/includes/session_auth.php';

if (animaster_has_character())
{
    header('Location: game.php');
}
elseif (animaster_is_logged_in())
{
    header('Location: character_select.php');
}
else
{
    header('Location: login.php');
}

exit;
