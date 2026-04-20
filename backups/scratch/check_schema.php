<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE fare_matrix");
$results = $stmt->fetchAll();
print_r($results);
?>
