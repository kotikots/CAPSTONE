<?php
/**
 * passenger/get_bus_location.php
 * Returns live GPS positions for ALL active buses.
 * Reads from bus_locations table (latest GPS record per bus).
 */
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT 
            t.id AS trip_id,
            b.id AS bus_id,
            b.body_number,
            COALESCE(bl.latitude, b.latitude)   AS latitude,
            COALESCE(bl.longitude, b.longitude)  AS longitude,
            COALESCE(bl.speed_kmh, 0)            AS speed_kmh,
            d.full_name AS driver_name,
            s1.station_name AS start_name,
            s2.station_name AS end_name,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND id NOT IN (SELECT ticket_id FROM payments)) AS passenger_count
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN drivers d ON d.id = t.driver_id
        JOIN stations s1 ON s1.id = t.start_station_id
        JOIN stations s2 ON s2.id = t.end_station_id
        LEFT JOIN (
            SELECT bl1.bus_id, bl1.latitude, bl1.longitude, bl1.speed_kmh
            FROM bus_locations bl1
            INNER JOIN (
                SELECT bus_id, MAX(recorded_at) AS max_time 
                FROM bus_locations 
                GROUP BY bus_id
            ) bl2 ON bl1.bus_id = bl2.bus_id AND bl1.recorded_at = bl2.max_time
        ) bl ON bl.bus_id = b.id
        WHERE t.status = 'active'
    ");

    $buses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'buses' => $buses]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
