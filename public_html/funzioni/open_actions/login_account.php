<?php
require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';

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
