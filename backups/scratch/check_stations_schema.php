<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE stations");
$results = $stmt->fetchAll();
print_r($results);
?>
