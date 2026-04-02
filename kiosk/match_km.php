<?php
require_once '../config/db.php';
$lat = $_GET['lat'];
$lng = $_GET['lng'];

$sql = "SELECT station_name, km_marker, 
       (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance 
       FROM stations ORDER BY distance ASC LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$lat, $lng, $lat]);
echo json_encode($stmt->fetch());
?>