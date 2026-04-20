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
    // 1. Find the active trip for the dynamically assigned bus
    $busId = isset($data['bus_id']) ? (int)$data['bus_id'] : 0;
    
    if (!$busId) {
        echo json_encode(['status' => 'error', 'message' => 'Device not bound to any bus.']);
        exit;
    }

    // Fetch Bus and Driver details for the receipt
    $busStmt = $pdo->prepare("
        SELECT b.body_number, b.driver_id, d.full_name as driver_name 
        FROM buses b 
        LEFT JOIN drivers d ON b.driver_id = d.id 
        WHERE b.id = ?
    ");
    $busStmt->execute([$busId]);
    $busInfo = $busStmt->fetch();
    $driverName = $busInfo['driver_name'] ?? 'Not Assigned';
    $busNumber = $busInfo['body_number'] ?? 'Unknown';

    $stmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$busId]);
    $trip = $stmt->fetch();

    // 2. If no active trip, auto-create one with direction inference
    if (!$trip) {
        // Try to infer direction from the bus's current GPS position
        $gpsStmt = $pdo->prepare("SELECT latitude, longitude FROM buses WHERE id = ? LIMIT 1");
        $gpsStmt->execute([$busId]);
        $busGps = $gpsStmt->fetch();

        // Get first and last station
        $firstStation = $pdo->query("SELECT id, km_marker, latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order ASC LIMIT 1")->fetch();
        $lastStation  = $pdo->query("SELECT id, km_marker, latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order DESC LIMIT 1")->fetch();

        $startId = $firstStation['id'];
        $endId   = $lastStation['id'];

        // If we have GPS data, check if we're closer to the last station (backward trip)
        if ($busGps && $busGps['latitude'] && $lastStation['latitude']) {
            $distToFirst = abs($busGps['latitude'] - $firstStation['latitude']) + abs($busGps['longitude'] - $firstStation['longitude']);
            $distToLast  = abs($busGps['latitude'] - $lastStation['latitude'])  + abs($busGps['longitude'] - $lastStation['longitude']);
            
            if ($distToLast < $distToFirst) {
                // Bus is closer to the last station → backward trip
                $startId = $lastStation['id'];
                $endId   = $firstStation['id'];
            }
        }

        $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status)
                              SELECT ?, b.driver_id, ?, ?, 'active'
                              FROM buses b WHERE b.id = ?");
        $ins->execute([$busId, $startId, $endId, $busId]);
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
    // Use MAX sequence number instead of COUNT to avoid collisions if records are deleted.
    $datePart  = date('Ymd');
    $maxStmt   = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(ticket_code, '-', -1) AS UNSIGNED)) FROM tickets WHERE ticket_code LIKE 'TKT-{$datePart}-%'");
    $maxSeq    = (int) $maxStmt->fetchColumn();
    $nextSeq   = $maxSeq + 1;
    $ticketCode = 'TKT-' . $datePart . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);

    // Safety net: if somehow still duplicate (race condition), keep incrementing
    while (true) {
        $chk = $pdo->prepare("SELECT id FROM tickets WHERE ticket_code = ? LIMIT 1");
        $chk->execute([$ticketCode]);
        if (!$chk->fetch()) break;
        $nextSeq++;
        $ticketCode = 'TKT-' . $datePart . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    }

    // 5. Compute distance (absolute difference in km_marker)
    $distStmt = $pdo->prepare(
        "SELECT ABS(
            (SELECT km_marker FROM stations WHERE id = ?) -
            (SELECT km_marker FROM stations WHERE id = ?)
        ) AS dist_km"
    );
    $distStmt->execute([$originId, $destId]);
    $distKm = (float) $distStmt->fetchColumn();

    // 6. Determine passenger info from verification step
    $passengerId   = !empty($data['passenger_id']) ? (int)$data['passenger_id'] : null;
    $passengerName = !empty($data['passenger_name']) ? $data['passenger_name'] : 'Walk-in';

    // 7. Insert ticket
    $ticketStmt = $pdo->prepare("
        INSERT INTO tickets
            (ticket_code, trip_id, passenger_id, passenger_name, passenger_type,
             origin_station_id, dest_station_id, origin_name, dest_name,
             distance_km, fare_amount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ticketStmt->execute([
        $ticketCode,
        $tripId,
        $passengerId,
        $passengerName,
        $data['type'],
        $originId,
        $destId,
        $data['origin'],
        $data['dest'],
        $distKm,
        $data['fare']
    ]);
    $ticketId = $pdo->lastInsertId();

    // 7. Update trip totals (Passenger count only)
    $updateTrip = $pdo->prepare("UPDATE trips SET passenger_count = passenger_count + 1 WHERE id = ?");
    $updateTrip->execute([$tripId]);

    echo json_encode([
        'status'      => 'success',
        'ticket_code' => $ticketCode,
        'trip_id'     => $tripId,
        'bus_id'      => $busId,
        'bus_number'  => $busNumber,
        'driver_name' => $driverName,
        'message'     => 'Ticket saved successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>