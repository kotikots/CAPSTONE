<?php
/**
 * driver/push_location.php
 *
 * ROLE: Driver Portal
 * PURPOSE: Receives GPS coordinates from the driver's phone/kiosk and
 * saves them to the database.
 *
 * Speed optimisations:
 *  - Client sends cached trip_id → skips SELECT trips query
 *  - INSERT + UPDATE wrapped in a single transaction
 *  - Returns trip_id so client caches it for the next push
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// 1. AUTH CHECK
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}
$driverId = $_SESSION['driver_id'];

// 2. IDENTIFY BUS
$busStmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();
if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'No bus assigned to your account']); exit;
}
$busId = (int)$bus['id'];

// 3. GET DATA
$data         = json_decode(file_get_contents('php://input'), true);
$lat          = isset($data['lat'])      ? (float)$data['lat']      : 0;
$lng          = isset($data['lng'])      ? (float)$data['lng']      : 0;
$spd          = isset($data['speed'])    ? (float)$data['speed']    : 0;
$acc          = isset($data['accuracy']) ? (float)$data['accuracy'] : null;
$clientTripId = isset($data['trip_id']) ? (int)$data['trip_id']    : 0;

// 4. VALIDATION — reject empty or inaccurate GPS
if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates']); exit;
}
if ($acc !== null && $acc > 500) {
    echo json_encode(['success' => false, 'message' => 'GPS fix too inaccurate', 'accuracy' => $acc]); exit;
}

// 5. TRIP ID — use client cache if available, else query DB once
$tripId = $clientTripId;
if (!$tripId) {
    $tripStmt = $pdo->prepare(
        "SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1"
    );
    $tripStmt->execute([$busId]);
    $tripId = (int)($tripStmt->fetchColumn() ?? 0);
}

// 6. WRITE — single transaction: INSERT log + UPDATE current position
$pdo->beginTransaction();

$pdo->prepare("INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, ?)")
    ->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

$pdo->prepare("UPDATE buses SET latitude = ?, longitude = ?, current_speed = ?, last_updated = NOW() WHERE id = ?")
    ->execute([$lat, $lng, $spd, $busId]);

$pdo->commit();

// 7. RESPOND — include trip_id so client can cache it
echo json_encode(['success' => true, 'bus_id' => $busId, 'trip_id' => $tripId]);
?>
