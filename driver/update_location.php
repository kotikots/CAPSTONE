<?php
/**
 * driver/update_location.php — AJAX: Insert a bus_locations row (real or simulated GPS).
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lat  = (float)($data['lat']   ?? 0);
$lng  = (float)($data['lng']   ?? 0);
$spd  = (float)($data['speed'] ?? 0);

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']); exit;
}

// Get driver's bus
$busStmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$_SESSION['driver_id']]);
$bus = $busStmt->fetch();

if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'No bus assigned']); exit;
}

$busId = (int)$bus['id'];

// Get active trip ID
$tripStmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1");
$tripStmt->execute([$busId]);
$tripId = (int)($tripStmt->fetchColumn() ?? 0);

$ins = $pdo->prepare(
    "INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, ?)"
);
$ins->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

echo json_encode(['success' => true, 'recorded_at' => date('Y-m-d H:i:s')]);
