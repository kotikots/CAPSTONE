<?php
/**
 * driver/start_trip.php — AJAX: Start a new trip for the driver's assigned bus.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions_v2.php';
header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$driverId = $_SESSION['driver_id'];

// Get driver's bus
$busStmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();

if (!$bus) {
    echo json_encode(['success' => false, 'message' => 'No bus assigned to your account']); exit;
}

$busId = (int)$bus['id'];

// Check if the bus already has an active trip (regardless of driver)
$existing = getLiveTrip($pdo, $busId, 0);
if ($existing) {
    if ((int)$existing['driver_id'] === $driverId) {
        // Same driver — they already have a trip running
        echo json_encode(['success' => false, 'message' => 'You already have an active trip on this bus (ID: ' . $existing['id'] . ')']); exit;
    } else {
        // Different driver — bus was reassigned by admin. Auto-end the orphaned trip.
        $endStmt = $pdo->prepare("UPDATE trips SET status = 'completed', ended_at = NOW() WHERE id = ?");
        $endStmt->execute([$existing['id']]);
    }
}

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$direction = $data['direction'] ?? 'forward'; // 'forward' or 'backward'

// Get terminal/end station IDs
$ascStmt = $pdo->query("SELECT id FROM stations WHERE is_active=1 ORDER BY sort_order ASC LIMIT 1");
$descStmt = $pdo->query("SELECT id FROM stations WHERE is_active=1 ORDER BY sort_order DESC LIMIT 1");
$firstId = (int)$ascStmt->fetchColumn();
$lastId  = (int)$descStmt->fetchColumn();

if ($direction === 'backward') {
    $startId = $lastId;
    $endId   = $firstId;
} else {
    $startId = $firstId;
    $endId   = $lastId;
}

try {
    $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status) VALUES (?, ?, ?, ?, 'active')");
    $ins->execute([$busId, $driverId, $startId, $endId]);
    $tripId = $pdo->lastInsertId();

    // Seed initial GPS position from starting station so bus shows immediately on map
    $stationGps = $pdo->prepare("SELECT latitude, longitude FROM stations WHERE id = ? LIMIT 1");
    $stationGps->execute([$startId]);
    $sGps = $stationGps->fetch();
    if ($sGps && $sGps['latitude'] && $sGps['longitude']) {
        $pdo->prepare("INSERT INTO bus_locations (bus_id, trip_id, latitude, longitude, speed_kmh) VALUES (?, ?, ?, ?, 0)")
            ->execute([$busId, $tripId, $sGps['latitude'], $sGps['longitude']]);
    }

    echo json_encode(['success' => true, 'trip_id' => $tripId]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
