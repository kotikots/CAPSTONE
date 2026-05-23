<?php
/**
 * kiosk/process_ticket.php
 * 
 * ROLE: Station Kiosk (Interface at the bus stop)
 * PURPOSE: This file handles the "Checkout" when a passenger buys a ticket at a kiosk.
 * It calculates the fare, links it to an active bus trip, and saves the ticket record.
 */
require_once '../config/db.php'; // Connect to the database

// Set response to JSON because the kiosk frontend uses JavaScript to talk to this file
header('Content-Type: application/json');

// Get the raw data (Origin, Destination, Fare, etc.) sent by the kiosk screen
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

try {
    // 1. WHICH BUS? IDENTIFY THE VEHICLE
    // Each kiosk is logically "bound" to a specific bus in its settings.
    $busId = isset($data['bus_id']) ? (int)$data['bus_id'] : 0;
    
    if (!$busId) {
        echo json_encode(['status' => 'error', 'message' => 'Device not bound to any bus.']);
        exit;
    }

    // Fetch Bus and Driver names so we can print them on the ticket receipt
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

    // 2. FIND THE ACTIVE TRIP
    // A ticket MUST be linked to a "Trip ID." We look for a trip that is currently 'active'.
    $stmt = $pdo->prepare("SELECT id FROM trips WHERE bus_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1");
    $stmt->execute([$busId]);
    $trip = $stmt->fetch();

    // 3. AUTO-TRIP LOGIC: If no trip is active, the system starts one automatically!
    // This is a safety feature so that passengers can still buy tickets even if the driver forgot to click "Start Trip."
    if (!$trip) {
        // We try to figure out if the bus is going forward or backward based on its current GPS.
        $gpsStmt = $pdo->prepare("SELECT latitude, longitude FROM buses WHERE id = ? LIMIT 1");
        $gpsStmt->execute([$busId]);
        $busGps = $gpsStmt->fetch();

        $firstStation = $pdo->query("SELECT id, km_marker, latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order ASC LIMIT 1")->fetch();
        $lastStation  = $pdo->query("SELECT id, km_marker, latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order DESC LIMIT 1")->fetch();

        $startId = $firstStation['id'];
        $endId   = $lastStation['id'];

        // Determine if we're closer to the start or the end of the line
        if ($busGps && $busGps['latitude'] && $lastStation['latitude']) {
            $distToFirst = abs($busGps['latitude'] - $firstStation['latitude']) + abs($busGps['longitude'] - $firstStation['longitude']);
            $distToLast  = abs($busGps['latitude'] - $lastStation['latitude'])  + abs($busGps['longitude'] - $lastStation['longitude']);
            
            if ($distToLast < $distToFirst) {
                $startId = $lastStation['id'];
                $endId   = $firstStation['id'];
            }
        }

        // Create the new trip record
        $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status)
                               SELECT ?, b.driver_id, ?, ?, 'active'
                               FROM buses b WHERE b.id = ?");
        $ins->execute([$busId, $startId, $endId, $busId]);
        $tripId = $pdo->lastInsertId();
    } else {
        $tripId = $trip['id'];
    }

    // 4. STATION LOOKUP: Get the numeric IDs for the Origin and Destination names
    $originStmt = $pdo->prepare("SELECT id FROM stations WHERE station_name = ? LIMIT 1");
    $originStmt->execute([$data['origin']]);
    $originRow = $originStmt->fetch();

    $destStmt = $pdo->prepare("SELECT id FROM stations WHERE station_name = ? LIMIT 1");
    $destStmt->execute([$data['dest']]);
    $destRow = $destStmt->fetch();

    $originId = $originRow ? $originRow['id'] : 1;
    $destId   = $destRow   ? $destRow['id']   : 1;

    // 5. GENERATE TICKET CODE: e.g., TKT-20240421-00001
    // We look for the highest existing number for today and add 1.
    $datePart  = date('Ymd');
    $maxStmt   = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(ticket_code, '-', -1) AS UNSIGNED)) FROM tickets WHERE ticket_code LIKE 'TKT-{$datePart}-%'");
    $maxSeq    = (int) $maxStmt->fetchColumn();
    $nextSeq   = $maxSeq + 1;
    $ticketCode = 'TKT-' . $datePart . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);

    // 6. DISTANCE CALCULATION: How many kilometers is this ride?
    $distStmt = $pdo->prepare(
        "SELECT ABS(
            (SELECT km_marker FROM stations WHERE id = ?) -
            (SELECT km_marker FROM stations WHERE id = ?)
        ) AS dist_km"
    );
    $distStmt->execute([$originId, $destId]);
    $distKm = (float) $distStmt->fetchColumn();

    // 7. PASSENGER TYPE: Is this a Walk-in or an ID-verified student/senior?
    $passengerId   = !empty($data['passenger_id']) ? (int)$data['passenger_id'] : null;
    $passengerName = !empty($data['passenger_name']) ? $data['passenger_name'] : 'Walk-in';

    // 8. FINAL SAVE: Insert the ticket into the database
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

    // 9. UPDATE TRIP COUNTER: Increment the passenger count for the current bus trip
    $updateTrip = $pdo->prepare("UPDATE trips SET passenger_count = passenger_count + 1 WHERE id = ?");
    $updateTrip->execute([$tripId]);

    // 10. SEND TO PRINTER: Return all details needed for the physical ticket receipt
    echo json_encode([
        'status'      => 'success',
        'ticket_code' => $ticketCode,
        'trip_id'     => $tripId,
        'bus_id'      => $busId,
        'bus_number'  => $busNumber,
        'driver_name' => $driverName,
        'message'     => 'Ticket saved successfully'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}