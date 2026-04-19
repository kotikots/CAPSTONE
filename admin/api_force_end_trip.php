<?php
/**
 * admin/api_force_end_trip.php
 * Admin-only endpoint to force end any active trip.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/auth_guard.php';
header('Content-Type: application/json');

// Check role
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$tripId = isset($data['trip_id']) ? (int)$data['trip_id'] : 0;

if (!$tripId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Trip ID']); exit;
}

try {
    // End the trip
    $stmt = $pdo->prepare("UPDATE trips SET status = 'completed', ended_at = NOW() WHERE id = ? AND status = 'active'");
    $stmt->execute([$tripId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Trip ended successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Trip not found or already ended']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
