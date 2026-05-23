<?php
/**
 * admin/api_force_end_trip.php
 * 
 * ROLE: Admin Only
 * PURPOSE: This file is a "worker" (API) used when an admin needs to manually stop a bus trip
 * that might have been left active by accident.
 */
session_start();
require_once '../config/db.php'; // Connect to the database
require_once '../includes/auth_guard.php'; // Ensure user is logged in

// Tell the browser we are sending back a JSON response (not a full webpage)
header('Content-Type: application/json');

// 1. SECURITY CHECK: Only allow users with the 'admin' role
if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

// 2. DATA INPUT: Get the JSON data sent from the dashboard button
$data = json_decode(file_get_contents('php://input'), true);
$tripId = isset($data['trip_id']) ? (int)$data['trip_id'] : 0;

// 3. VALIDATION: Check if a valid ID was provided
if (!$tripId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Trip ID']); exit;
}

try {
    // 4. DATABASE ACTION: Update the trip status to 'completed' and set the end time to NOW()
    // We only target trips that are currently 'active'
    $stmt = $pdo->prepare("UPDATE trips SET status = 'completed', ended_at = NOW() WHERE id = ? AND status = 'active'");
    $stmt->execute([$tripId]);

    // 5. RESPONSE: Tell the dashboard if the operation worked or not
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Trip ended successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Trip not found or already ended']);
    }
    exit;
} catch (PDOException $e) {
    // Handle any database errors gracefully
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

