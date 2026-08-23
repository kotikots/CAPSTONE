<?php
/**
 * passenger/get_bus_location.php
 *
 * Returns live GPS positions for ALL active buses.
 *
 * FAST path: reads latitude/longitude directly from the `buses` table,
 * which is updated on every GPS push. No GROUP BY or subquery on
 * bus_locations (the old approach did a full-table scan there).
 *
 * This endpoint is kept for backward compatibility / one-off fetches.
 * The SSE stream (stream_bus_location.php) is preferred for live display.
 */
require_once '../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    $stmt = $pdo->query("
        SELECT
            b.id            AS bus_id,
            b.body_number,
            b.latitude,
            b.longitude,
            b.current_speed AS speed_kmh,
            b.last_updated  AS last_seen,
            d.full_name     AS driver_name,
            s1.station_name AS start_name,
            s2.station_name AS end_name,
            (SELECT COUNT(*) FROM tickets tk
             WHERE tk.trip_id = t.id
               AND tk.id NOT IN (SELECT ticket_id FROM payments)
            ) AS passenger_count
        FROM trips t
        JOIN buses    b  ON b.id  = t.bus_id
        JOIN drivers  d  ON d.id  = t.driver_id
        JOIN stations s1 ON s1.id = t.start_station_id
        JOIN stations s2 ON s2.id = t.end_station_id
        WHERE t.status = 'active'
    ");

    $buses = $stmt->fetchAll();
    echo json_encode([
        'success'     => true,
        'buses'       => $buses,
        'server_time' => date('c')
    ]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}


