<?php
/**
 * passenger/submit_booking.php   — STEP 6 & 8
 * AJAX endpoint: validates and creates a ticket + payment row.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$originId  = (int)($data['origin_id'] ?? 0);
$destId    = (int)($data['dest_id']   ?? 0);
$type      = trim($data['passenger_type'] ?? 'Regular');
$fareInput = (float)($data['fare'] ?? 0);

$allowedTypes = ['Regular', 'Non-Regular', 'Discounted'];
if (!in_array($type, $allowedTypes) || !$originId || !$destId || $originId === $destId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking data']);
    exit;
}

// Re-calculate fare server-side (never trust client)
$distance = getDistance($pdo, $originId, $destId);
if ($distance <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid route selection']);
    exit;
}
$fare = calculateFare($distance, $type, $pdo);

// Get origin and destination names
$nameStmt = $pdo->prepare("SELECT station_name FROM stations WHERE id = ? LIMIT 1");
$nameStmt->execute([$originId]);
$originName = $nameStmt->fetchColumn();
$nameStmt->execute([$destId]);
$destName = $nameStmt->fetchColumn();

if (!$originName || !$destName) {
    echo json_encode(['success' => false, 'message' => 'Station not found']);
    exit;
}

// Find active trip (any bus is fine for web booking)
$tripStmt = $pdo->query("SELECT id FROM trips WHERE status = 'active' ORDER BY started_at DESC LIMIT 1");
$trip = $tripStmt->fetch();

if (!$trip) {
    echo json_encode(['success' => false, 'message' => 'No active bus trip available. Please wait for the driver to start a trip.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $ticketCode = generateTicketCode($pdo);

    // Insert ticket
    $tStmt = $pdo->prepare("
        INSERT INTO tickets
            (ticket_code, trip_id, passenger_id, passenger_name, passenger_type,
             origin_station_id, dest_station_id, origin_name, dest_name,
             distance_km, fare_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued')
    ");
    $tStmt->execute([
        $ticketCode, $trip['id'],
        $_SESSION['user_id'], $_SESSION['full_name'],
        $type, $originId, $destId, $originName, $destName,
        $distance, $fare
    ]);
    $ticketId = $pdo->lastInsertId();

    // Insert payment
    $pStmt = $pdo->prepare("INSERT INTO payments (ticket_id, amount_paid, payment_method) VALUES (?, ?, 'cash')");
    $pStmt->execute([$ticketId, $fare]);

    // Update trip totals
    $upd = $pdo->prepare("UPDATE trips SET total_revenue = total_revenue + ?, passenger_count = passenger_count + 1 WHERE id = ?");
    $upd->execute([$fare, $trip['id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'ticket_code' => $ticketCode]);

} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
