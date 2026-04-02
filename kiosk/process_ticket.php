<?php
/**
 * Kiosk Ticket Processor
 * Saves walk-in kiosk transactions to the new normalized tickets + payments tables.
 * Works with the active trip for Bus 1 (kiosk is permanently on Bus-001).
 */
require_once '../config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

try {
    // 1. Find the active trip for bus 1
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = 1 AND status = 'active' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute();
    $trip = $stmt->fetch();

    // 2. If no active trip, auto-create one (kiosk fallback)
    if (!$trip) {
        $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status)
                              SELECT 1, b.driver_id,
                                     (SELECT id FROM stations WHERE sort_order = 1 LIMIT 1),
                                     (SELECT id FROM stations ORDER BY sort_order DESC LIMIT 1)
                              FROM buses b WHERE b.id = 1");
        $ins->execute();
        $tripId = $pdo->lastInsertId();
    } else {
        $tripId = $trip['id'];
    }

    // 3. Look up origin and destination station IDs
    $originStmt = $pdo->prepare("SELECT id FROM stations WHERE station_name = ? LIMIT 1");
    $originStmt->execute([$data['origin']]);
    $originRow = $originStmt->fetch();

    $destStmt = $pdo->prepare("SELECT id FROM stations WHERE station_name = ? LIMIT 1");
    $destStmt->execute([$data['dest']]);
    $destRow = $destStmt->fetch();

    $originId = $originRow ? $originRow['id'] : 1;
    $destId   = $destRow   ? $destRow['id']   : 1;

    // 4. Generate unique ticket code: TKT-YYYYMMDD-NNNNN
    $datePart  = date('Ymd');
    $countStmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(issued_at) = CURDATE()");
    $todayCount = (int) $countStmt->fetchColumn() + 1;
    $ticketCode = 'TKT-' . $datePart . '-' . str_pad($todayCount, 5, '0', STR_PAD_LEFT);

    // 5. Compute distance (absolute difference in km_marker)
    $distStmt = $pdo->prepare(
        "SELECT ABS(
            (SELECT km_marker FROM stations WHERE id = ?) -
            (SELECT km_marker FROM stations WHERE id = ?)
        ) AS dist_km"
    );
    $distStmt->execute([$originId, $destId]);
    $distKm = (float) $distStmt->fetchColumn();

    // 6. Insert ticket
    $ticketStmt = $pdo->prepare("
        INSERT INTO tickets
            (ticket_code, trip_id, passenger_name, passenger_type,
             origin_station_id, dest_station_id, origin_name, dest_name,
             distance_km, fare_amount)
        VALUES (?, ?, 'Walk-in', ?, ?, ?, ?, ?, ?, ?)
    ");
    $ticketStmt->execute([
        $ticketCode,
        $tripId,
        $data['type'],
        $originId,
        $destId,
        $data['origin'],
        $data['dest'],
        $distKm,
        $data['fare']
    ]);
    $ticketId = $pdo->lastInsertId();

    // 7. Insert payment record
    $payStmt = $pdo->prepare("INSERT INTO payments (ticket_id, amount_paid, payment_method) VALUES (?, ?, 'cash')");
    $payStmt->execute([$ticketId, $data['fare']]);

    // 8. Update trip totals
    $updateTrip = $pdo->prepare("UPDATE trips SET total_revenue = total_revenue + ?, passenger_count = passenger_count + 1 WHERE id = ?");
    $updateTrip->execute([$data['fare'], $tripId]);

    echo json_encode([
        'status'      => 'success',
        'ticket_code' => $ticketCode,
        'trip_id'     => $tripId,
        'message'     => 'Ticket saved successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>