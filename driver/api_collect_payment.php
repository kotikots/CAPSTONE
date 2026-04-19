<?php
/**
 * driver/api_collect_payment.php
 * Endpoint for drivers to mark a ticket as paid (Cash).
 */
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = $input['ticket_id'] ?? 0;

if (!$ticketId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Ticket ID']);
    exit;
}

try {
    // 1. Fetch ticket and ensure it belongs to an active trip for THIS driver
    $stmt = $pdo->prepare("
        SELECT t.id, t.fare_amount, tr.id as trip_id
        FROM   tickets t
        JOIN   trips   tr ON tr.id = t.trip_id
        WHERE  t.id = ? AND tr.driver_id = ? AND tr.status = 'active'
    ");
    $stmt->execute([$ticketId, $_SESSION['driver_id']]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket not found or trip is no longer active.']);
        exit;
    }

    // 2. Check if already paid
    $chk = $pdo->prepare("SELECT id FROM payments WHERE ticket_id = ?");
    $chk->execute([$ticketId]);
    if ($chk->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already paid.']);
        exit;
    }

    // 3. Insert payment record
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO payments (ticket_id, amount_paid, payment_method, paid_at)
        VALUES (?, ?, 'cash', NOW())
    ");
    $stmt->execute([$ticketId, $ticket['fare_amount']]);

    // 4. Update ticket status to validated
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'validated' WHERE id = ?");
    $stmt->execute([$ticketId]);

    // 5. Update trip total revenue
    $stmt = $pdo->prepare("UPDATE trips SET total_revenue = total_revenue + ? WHERE id = ?");
    $stmt->execute([$ticket['fare_amount'], $ticket['trip_id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
