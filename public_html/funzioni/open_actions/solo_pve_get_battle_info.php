<?php

require $_SERVER['DOCUMENT_ROOT'] . '/funzioni/i.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/private_functions/combat/SoloPveController.php';

$result = SoloPveController::handleRequest($conn, $_POST);

echo json_encode($result);
