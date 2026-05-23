<?php
/**
 * driver/push_location.php
 * 
 * ROLE: Driver Portal
 * PURPOSE: This is the pulse of the tracking system. It receives GPS coordinates 
 * from the driver's phone and saves them to the database.
 */
session_start();
require_once '../config/db.php'; // Database connection

// Set response to JSON for the JavaScript 'fetch' call
header('Content-Type: application/json');

// 1. AUTH CHECK: Make sure the sender is a logged-in driver
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$driverId = $_SESSION['driver_id'];

// 2. IDENTIFY BUS: Find which bus is currently assigned to this driver
$busStmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();

if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'No bus assigned to your account']); exit;
}

$busId = (int)$bus['id'];

// 3. GET DATA: Read the latitude, longitude, and speed from the JSON request
$data = json_decode(file_get_contents('php://input'), true);
$lat  = isset($data['lat'])   ? (float)$data['lat']   : 0;
$lng  = isset($data['lng'])   ? (float)$data['lng']   : 0;
$spd  = isset($data['speed']) ? (float)$data['speed'] : 0;

// 4. VALIDATION: Basic check to ensure we aren't getting empty coordinates
if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates']); exit;
}

// 5. TRIP CONTEXT: Check if this bus is currently on an active trip
// This allows us to link specific coordinates to a specific journey for reports.
$tripStmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1");
$tripStmt->execute([$busId]);
$tripId = (int)($tripStmt->fetchColumn() ?? 0);

// 6. SAVE LOG: Insert into 'bus_locations' for historical map playback (breadcrumb trail)
$pdo->prepare("INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, ?)")
    ->execute([$busId, $tripId ?: null, $lat, $lng, $spd]);

// 7. UPDATE STATUS: Save the latest position directly in the 'buses' table 
// This makes the admin/passenger map loads very fast (1 query instead of searching logs).
$pdo->prepare("UPDATE buses SET latitude = ?, longitude = ?, current_speed = ? WHERE id = ?")
    ->execute([$lat, $lng, $spd, $busId]);

// 8. DONE: Send back success
echo json_encode(['success' => true, 'bus_id' => $busId]);
?>
