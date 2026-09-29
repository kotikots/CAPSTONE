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

// Check if there are any unpaid tickets for this trip
$chkStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tickets t 
    WHERE t.trip_id = ? 
      AND (t.status IS NULL OR t.status != 'flagged')
      AND t.id NOT IN (SELECT ticket_id FROM payments)
");
$chkStmt->execute([$tripId]);
$unpaidCount = (int)$chkStmt->fetchColumn();

if ($unpaidCount > 0) {
    echo json_encode([
        'success' => false, 
        'message' => "Cannot end trip. There are still $unpaidCount uncollected ticket(s). Please collect the fare or void them first."
    ]);
    exit;
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
