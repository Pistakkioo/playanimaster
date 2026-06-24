<?php
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/character_profile.php';

$conn = animaster_get_conn();

$username = isset($_POST['username']) ? $_POST['username'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$c_pass = md5(md5($password));

$stato = 'OK';
$msg = 'OK';
$row = null;

$result = $conn->query("
    SELECT id_user, username, email
    FROM users
    WHERE username = \"$username\"
        AND password = \"$c_pass\"
");

if (!$result)
{
    $stato = 'KO';
    $msg = 'Failed to connect';
}
else if ($result->rowCount() === 0)
{
    $stato = 'KO';
    $msg = 'Incorrect login';
}
else
{
    $row = $result->fetch();
}

echo json_encode([
    'stato' => $stato,
    'msg' => $msg,
    'response' => json_encode($row)
]);
