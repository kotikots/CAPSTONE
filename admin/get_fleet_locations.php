<?php
/**
 * admin/get_fleet_locations.php
 * Returns real-time bus positions for the admin dashboard map.
 * Reads from the buses table (updated by kiosk push_location.php).
 */
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT 
            b.id AS bus_id,
            b.body_number,
            b.plate_number,
            b.latitude,
            b.longitude,
            b.current_speed AS speed_kmh,
            t.id AS trip_id,
            t.status AS trip_status,
            d.full_name AS driver_name
        FROM buses b
        JOIN drivers d ON d.id = b.driver_id
        LEFT JOIN trips t ON t.bus_id = b.id AND t.status = 'active'
        WHERE b.is_active = 1
          AND b.latitude IS NOT NULL
          AND b.longitude IS NOT NULL
    ");

    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'buses' => $buses]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
