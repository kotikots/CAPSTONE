<?php
/**
 * driver/api_remit_cash.php
 * Endpoint for drivers to flag their collected cash as "remitted" (pending admin confirmation).
 * Sets payments.remitted = 2  (0 = unremitted, 2 = driver-claimed, 1 = admin-confirmed).
 */
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driverId = $_SESSION['driver_id'];

try {
    // Count how many unremitted payments exist for this driver today
    $checkStmt = $pdo->prepare("
        SELECT COUNT(p.id) AS cnt, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM   payments p
        JOIN   tickets  t  ON t.id = p.ticket_id
        JOIN   trips    tr ON tr.id = t.trip_id
        WHERE  tr.driver_id = ? AND p.payment_method = 'cash' AND p.remitted = 0
    ");
    $checkStmt->execute([$driverId]);
    $check = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if ((int)$check['cnt'] === 0) {
        echo json_encode(['success' => false, 'message' => 'No unremitted cash to submit.']);
        exit;
    }

    // Mark all unremitted cash payments as driver-claimed (remitted = 2)
    $stmt = $pdo->prepare("
        UPDATE payments p
        JOIN   tickets  t  ON t.id = p.ticket_id
        JOIN   trips    tr ON tr.id = t.trip_id
        SET    p.remitted = 2, p.remitted_at = NOW()
        WHERE  tr.driver_id = ? AND p.payment_method = 'cash' AND p.remitted = 0
    ");
    $stmt->execute([$driverId]);
    $affected = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => 'Remittance submitted! Pending admin confirmation.',
        'tickets_flagged' => $affected,
        'amount' => (float)$check['total']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
