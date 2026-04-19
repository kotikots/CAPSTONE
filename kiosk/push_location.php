<?php
/**
 * kiosk/push_location.php
 * Called by the kiosk device to push GPS coordinates to bus_locations.
 * The kiosk is physically installed on Bus-001 and is always on the bus,
 * so its GPS = the bus's real position.
 *
 * No driver session needed — kiosk is a trusted on-bus device.
 * Bus ID is hardcoded (bus 1 = only bus, kiosk is permanently on it).
 */
require_once '../config/db.php';
header('Content-Type: application/json');

// Basic IP / origin check — only accept requests from localhost (same device)
$allowedOrigins = ['127.0.0.1', '::1', 'localhost'];
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remoteIp, $allowedOrigins)) {
    // Allow if you are on LAN — remove this check if kiosk is on same LAN as server
    // echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lat  = isset($data['lat'])   ? (float)$data['lat']   : 0;
$lng  = isset($data['lng'])   ? (float)$data['lng']   : 0;
$spd  = isset($data['speed']) ? (float)$data['speed'] : 0;
$acc  = isset($data['accuracy']) ? (float)$data['accuracy'] : null;

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']); exit;
}

// Bus ID is dynamically pushed from the individual Kiosk client
$busId = isset($data['bus_id']) ? (int)$data['bus_id'] : 0;

if (!$busId) {
    echo json_encode(['success' => false, 'message' => 'No Bus ID configured on device']); exit;
}

// Get the active trip for this bus (if any)
$tripStmt = $pdo->prepare(
    "SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1"
);
$tripStmt->execute([$busId]);
$tripId = (int)($tripStmt->fetchColumn() ?? 0);

// Insert location record for historical tracking
$ins = $pdo->prepare(
    "INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh)
     VALUES (?, ?, ?, ?, ?)"
);
$ins->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

// Sync with buses table for real-time dashboard performance (Kiosk is trusted source)
$upd = $pdo->prepare("UPDATE buses SET latitude = ?, longitude = ?, current_speed = ? WHERE id = ?");
$upd->execute([$lat, $lng, $spd, $busId]);

echo json_encode([
    'success' => true,
    'bus_id'  => $busId,
    'trip_id' => $tripId ?: null,
    'lat'     => $lat,
    'lng'     => $lng,
    'recorded_at' => date('Y-m-d H:i:s')
]);
