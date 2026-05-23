<?php
/**
 * admin/api_delete_account.php
 * 
 * ROLE: Admin Only
 * PURPOSE: Permanently removes a passenger or driver from the database.
 * IMPORTANT: This file has safety checks to prevent deleting users with history (like old trips).
 */
session_start();
require_once '../config/db.php'; // Database connection

header('Content-Type: application/json');

// 1. SECURITY: Ensure the person clicking "Delete" is actually an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

// 2. INPUT: Get the user type ('passenger' or 'driver') and their unique ID
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; 
$id   = (int)($data['id'] ?? 0);

// 3. VALIDATION: Ensure we have a valid ID and known type before proceeding
if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    // 4. DATABASE ACTION: Attempt to delete the record
    if ($type === 'driver') {
        // Prepare to delete from the 'drivers' table
        $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = ?");
    } else {
        // Prepare to delete from the 'users' table (passengers only)
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'passenger'");
    }
    
    $stmt->execute([$id]);
    
    // 5. RESPONSE: If one row was affected, it means the deletion was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Account removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found or could not be removed']);
    }
    exit;
} catch (PDOException $e) {
    // 6. SAFETY TRIGGER: Handle 'Constraint Violations' (Error Code 23000)
    // This happens if you try to delete a driver who is still linked to old tickets or trips.
    // We prevent this to keep your "Reports" accurate.
    if ($e->getCode() == '23000') {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot remove account: This user has historical activity (trips, tickets, or assigned vehicles). Please Deactivate the account instead to preserve system reports.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

