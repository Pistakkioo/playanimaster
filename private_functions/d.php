<?php

$servername = "localhost";
$username = "playanimaster_u";
$password = "dpmCs9,1XzgfgdDRNTd";
$dbname = "playanimaster_db";


try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH);
    $setlocal= $conn->query("SET lc_time_names = 'it_IT';");
    }
catch(PDOException $e)
    {
    echo "Connection failed: " . $e->getMessage();
    }

?>