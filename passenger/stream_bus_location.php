<?php
/**
 * passenger/stream_bus_location.php
 *
 * Server-Sent Events (SSE) endpoint.
 * The passenger map connects ONCE and this script streams bus positions
 * every second — no polling delay, no repeated HTTP round-trips.
 *
 * Protocol: text/event-stream
 *   data: {...json...}\n\n   <- one event per push
 */
require_once '../config/db.php';

// Standard JSON headers
header('Content-Type: application/json');

// Fast query: reads from buses table directly (no subquery on bus_locations)
$sql = "
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
           AND (tk.status IS NULL OR tk.status != 'flagged')
           AND tk.id NOT IN (SELECT ticket_id FROM payments)
        ) AS passenger_count
    FROM trips t
    JOIN buses    b  ON b.id  = t.bus_id
    JOIN drivers  d  ON d.id  = t.driver_id
    JOIN stations s1 ON s1.id = t.start_station_id
    JOIN stations s2 ON s2.id = t.end_station_id
    WHERE t.status = 'active'
";

$stmt = $pdo->prepare($sql);

$stmt->execute();
$buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'     => true,
    'buses'       => $buses,
    'server_time' => date('c')
]);
exit;