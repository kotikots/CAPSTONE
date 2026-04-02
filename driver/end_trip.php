<?php
/**
 * driver/end_trip.php — AJAX: End an active trip.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']); exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$tripId = (int)($data['trip_id'] ?? 0);

if (!$tripId) {
    echo json_encode(['success' => false, 'message' => 'Missing trip_id']); exit;
}

$stmt = $pdo->prepare(
    "UPDATE trips SET status = 'completed', ended_at = NOW()
     WHERE id = ? AND driver_id = ? AND status = 'active'"
);
$stmt->execute([$tripId, $_SESSION['driver_id']]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Trip not found or already ended']);
}
