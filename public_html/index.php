<?php
require '../private_functions/i.php';
echo "Hello World";

$query = "
select * From log 
"; 

$res = $conn->query($query);
$rows = $res->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "<br>".$row['dt_creazione']." - ".$row['nome_proc']." - ".$row['note'] . "<br>";
}
?>