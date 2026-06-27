<?php
// DOCKER VERSION
/**
 * Local Docker DB bootstrap — copy to private_functions/i.php (gitignored).
 * Adjust credentials to match your .env file.
 */
$host = getenv('DB_HOST') ?: 'db';
$db   = getenv('DB_NAME') ?: 'playanimaster_db';
$user = getenv('DB_USER') ?: 'animaster';
$pass = getenv('DB_PASSWORD') ?: 'change_me_app';

try
{
    $conn = new PDO(
        'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}
catch (PDOException $e)
{
    error_log('[animaster] DB connection failed: ' . $e->getMessage());
    throw $e;
}

/*
// OLD VERSION
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
*/
?>