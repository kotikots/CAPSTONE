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
$requiredRole = 'passenger';
require_once '../includes/auth_guard.php';
require_once '../config/db.php';

// SSE headers — override the no-store header set by auth_guard
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

// Disable all PHP output buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) ob_end_clean();
ob_implicit_flush(true);

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

$lastHash   = '';
$maxSeconds = 55;
$startTime  = time();

// RELEASE SESSION LOCK!
// If we don't close the session here, clicking any other tab will HANG
// because this script runs for 55 seconds and holds the PHP session file lock.
session_write_close();

while (true) {
    if (connection_aborted() || (time() - $startTime) > $maxSeconds) break;

    $stmt->execute();
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload = [
        'success'     => true,
        'buses'       => $buses,
        'server_time' => date('c')
    ];

    $hash = md5(json_encode($buses));
    if ($hash !== $lastHash) {
        $lastHash = $hash;
        echo 'data: ' . json_encode($payload) . "\n\n";
        flush();
    } else {
        echo ": ping\n\n";
        flush();
    }

    sleep(1);
}