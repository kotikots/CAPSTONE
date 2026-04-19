<?php
require_once 'c:\xampp\htdocs\PARE\config\db.php';

$stmt = $pdo->query("SELECT * FROM buses");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
