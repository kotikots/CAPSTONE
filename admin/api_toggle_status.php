<?php
/**
 * admin/api_toggle_status.php
 * Unified AJAX handler for toggling 'is_active' for users and drivers.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; // 'passenger' or 'driver'
$id   = (int)($data['id'] ?? 0);
$state= (int)($data['state'] ?? 0);

if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    if ($type === 'passenger') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'passenger'");
    } else {
        $stmt = $pdo->prepare("UPDATE drivers SET is_active = ? WHERE id = ?");
    }
    
    $stmt->execute([$state, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
