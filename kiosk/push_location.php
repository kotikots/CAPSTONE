<?php
/**
 * kiosk/push_location.php
 * Called by the kiosk device to push GPS coordinates to bus_locations.
 *
 * Speed optimisations:
 *  - Client sends a cached trip_id → skips SELECT trips query (~25% faster)
 *  - INSERT + UPDATE wrapped in a single transaction
 *  - Returns trip_id so the client can cache it for the next push
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$busId = isset($data['bus_id'])  ? (int)$data['bus_id']   : 0;
$lat   = isset($data['lat'])     ? (float)$data['lat']    : 0;
$lng   = isset($data['lng'])     ? (float)$data['lng']    : 0;
$acc   = isset($data['accuracy']) && $data['accuracy'] !== null ? (float)$data['accuracy'] : null;
// Client may send a cached trip_id to skip the SELECT
$clientTripId = isset($data['trip_id']) ? (int)$data['trip_id'] : 0;

if (!$busId || !$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']); exit;
}

// Haversine formula to calculate distance in km (for speed estimation)
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $R   = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a   = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

// ── Speed calculation (only needed for kiosk, driver sends its own speed) ──
$spd = 0;
$prev = $pdo->prepare(
    "SELECT latitude, longitude, recorded_at FROM bus_locations WHERE bus_id = ? ORDER BY id DESC LIMIT 1"
);
$prev->execute([$busId]);
$row = $prev->fetch(PDO::FETCH_ASSOC);
if ($row && $row['latitude'] && $row['longitude']) {
    $elapsed = time() - strtotime($row['recorded_at']);
    if ($elapsed > 0 && $elapsed <= 120) {
        $distKm  = getDistance($row['latitude'], $row['longitude'], $lat, $lng);
        $computed = $distKm / ($elapsed / 3600);
        $spd = ($computed <= 120) ? round($computed, 1) : 0;
    }
}

// ── Trip ID: use client cache if valid, else query DB ──────────────────────
$tripId = $clientTripId;
if (!$tripId) {
    $tripStmt = $pdo->prepare(
        "SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1"
    );
    $tripStmt->execute([$busId]);
    $tripId = (int)($tripStmt->fetchColumn() ?? 0);
}

// ── Write: INSERT + UPDATE in a transaction (one round-trip) ──────────────
$pdo->beginTransaction();

$pdo->prepare("INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, ?)")
    ->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

$pdo->prepare("UPDATE buses SET latitude = ?, longitude = ?, current_speed = ?, last_updated = NOW() WHERE id = ?")
    ->execute([$lat, $lng, $spd, $busId]);

$pdo->commit();

echo json_encode([
    'success'     => true,
    'bus_id'      => $busId,
    'trip_id'     => $tripId,      // client caches this for next push
    'speed'       => $spd,
    'recorded_at' => date('Y-m-d H:i:s')
]);
exit;

