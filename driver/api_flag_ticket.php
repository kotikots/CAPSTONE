<?php
/**
 * driver/api_flag_ticket.php
 * Endpoint for drivers to flag a ticket as no-show or invalid.
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
        SELECT t.id, t.status, tr.id as trip_id
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
        echo json_encode(['success' => false, 'message' => 'Cannot flag a paid ticket.']);
        exit;
    }

    // 3. Update ticket status to flagged
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'flagged' WHERE id = ?");
    $stmt->execute([$ticketId]);

    echo json_encode(['success' => true, 'message' => 'Ticket flagged successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
