<?php
require __DIR__ . '/includes/session_auth.php';

animaster_logout();

header('Location: login.php');  
exit;
