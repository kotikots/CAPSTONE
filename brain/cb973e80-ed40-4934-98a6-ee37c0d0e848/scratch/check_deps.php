<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$ids = [1, 2];
$results = [];
foreach ($ids as $id) {
    $trips = $pdo->prepare("SELECT COUNT(*) FROM trips WHERE driver_id = ?");
    $trips->execute([$id]);
    $tripCount = $trips->fetchColumn();
    
    $buses = $pdo->prepare("SELECT COUNT(*) FROM buses WHERE driver_id = ?");
    $buses->execute([$id]);
    $busCount = $buses->fetchColumn();
    
    $results[] = ["id" => $id, "trips" => $tripCount, "buses" => $busCount];
}
echo json_encode($results);
?>
