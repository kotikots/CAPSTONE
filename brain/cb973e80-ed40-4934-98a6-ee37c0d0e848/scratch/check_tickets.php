<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE driver_id = 2)");
$stmt->execute();
echo $stmt->fetchColumn();
?>
