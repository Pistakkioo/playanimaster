<?php
if (session_status() === PHP_SESSION_NONE)
{
    session_start();
}
@ini_set("session.gc_maxlifetime","8000");
@ini_set("session.cookie_lifetime","8000");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

//echo $_SERVER['DOCUMENT_ROOT'];

require __DIR__ . '/c.php';
require __DIR__ . '/d.php';
require __DIR__ . '/c_variabili.php';

if(!class_exists('FUNZIONI'))
{
    require __DIR__ . '/f.php';
}

require_once __DIR__ . '/f_debug.php';
?>