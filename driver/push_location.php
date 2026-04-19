<?php
/**
 * driver/push_location.php
 * Called by the driver's phone browser to push GPS coordinates.
 * Uses the driver's session to determine which bus to update.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$driverId = $_SESSION['driver_id'];

// Get driver's assigned bus
$busStmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();

if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'No bus assigned']); exit;
}

$busId = (int)$bus['id'];

$data = json_decode(file_get_contents('php://input'), true);
$lat  = isset($data['lat'])   ? (float)$data['lat']   : 0;
$lng  = isset($data['lng'])   ? (float)$data['lng']   : 0;
$spd  = isset($data['speed']) ? (float)$data['speed'] : 0;

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']); exit;
}

// Get active trip for this bus
$tripStmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1");
$tripStmt->execute([$busId]);
$tripId = (int)($tripStmt->fetchColumn() ?? 0);

// Insert into bus_locations (historical tracking)
$pdo->prepare("INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, ?)")
    ->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

// Update buses table for quick lookup
$pdo->prepare("UPDATE buses SET latitude = ?, longitude = ?, current_speed = ? WHERE id = ?")
    ->execute([$lat, $lng, $spd, $busId]);

echo json_encode(['success' => true, 'bus_id' => $busId]);
