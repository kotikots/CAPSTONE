<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT t.id, t.bus_id, t.start_station_id, t.end_station_id, s1.station_name as start_name, s1.km_marker as start_km, s2.station_name as end_name, s2.km_marker as end_km FROM trips t JOIN stations s1 ON t.start_station_id = s1.id JOIN stations s2 ON t.end_station_id = s2.id WHERE t.status = 'active'");
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($trips);
