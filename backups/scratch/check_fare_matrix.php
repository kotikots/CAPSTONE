<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT * FROM fare_matrix");
$results = $stmt->fetchAll();
print_r($results);
?>
