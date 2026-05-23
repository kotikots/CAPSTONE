<?php
/**
 * admin/api_toggle_status.php
 * 
 * ROLE: Admin Only
 * PURPOSE: A unified worker to enable or disable (activate/deactivate) passengers and drivers.
 */
session_start();
require_once '../config/db.php'; // Database connection

// Set response type to JSON for AJAX requests
header('Content-Type: application/json');

// 1. SECURITY: Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

// 2. INPUT: Get the data from the frontend (the target user type, their ID, and the new state)
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; // Can be 'passenger' or 'driver'
$id   = (int)($data['id'] ?? 0);
$state= (int)($data['state'] ?? 0); // 1 for Active, 0 for Inactive

// 3. VALIDATION: Check if we have everything we need
if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters provided']); exit;
}

try {
    // 4. DATABASE ACTION: Decide which table to update based on the 'type'
    if ($type === 'passenger') {
        // Toggle status for a passenger in the 'users' table
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'passenger'");
    } else {
        // Toggle status for a driver in the 'drivers' table
        $stmt = $pdo->prepare("UPDATE drivers SET is_active = ? WHERE id = ?");
    }
    
    $stmt->execute([$state, $id]);
    
    // 5. RESPONSE: Confirm success to the browser
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
    }
    exit;
} catch (PDOException $e) {
    // Error handling
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
