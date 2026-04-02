<?php
/**
 * passenger/get_bus_location.php   — STEP 11
 * AJAX: returns latest GPS coordinates for Bus 1.
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$buses = $pdo->query(
    "SELECT b.id AS bus_id, b.body_number, l.latitude, l.longitude, l.speed_kmh, l.recorded_at,
            s1.station_name AS start_name, s2.station_name AS end_name
     FROM   trips t
     JOIN   buses b ON b.id = t.bus_id
     JOIN   stations s1 ON s1.id = t.start_station_id
     JOIN   stations s2 ON s2.id = t.end_station_id
     JOIN   bus_locations l ON l.bus_id = b.id
     WHERE  t.status = 'active'
       AND  l.recorded_at = (
           SELECT MAX(recorded_at) FROM bus_locations WHERE bus_id = b.id
       )"
)->fetchAll(PDO::FETCH_ASSOC);

if ($buses) {
    echo json_encode(['success' => true, 'buses' => $buses]);
} else {
    echo json_encode(['success' => false, 'buses' => []]);
}
