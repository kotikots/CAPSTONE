<?php
/**
 * kiosk/get_destinations.php
 * Returns available destination stations with calculated fares.
 * 
 * NOW DIRECTION-AWARE: Checks the active trip's direction to determine
 * whether to show stations ahead (forward) or behind (backward).
 *
 * Params:
 *   current_km - the kiosk's current km_marker (from GPS match)
 *   bus_id     - the kiosk's bound bus ID (from localStorage)
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$current_km = (float)($_GET['current_km'] ?? 0);
$bus_id     = (int)($_GET['bus_id'] ?? 0);

// ─── Determine trip direction ───────────────────────────────────────
$direction = 'forward'; // default

if ($bus_id > 0) {
    // Find the active trip for this bus
    $tripStmt = $pdo->prepare("
        SELECT t.start_station_id, t.end_station_id,
               s1.km_marker AS start_km, s2.km_marker AS end_km
        FROM trips t
        JOIN stations s1 ON s1.id = t.start_station_id
        JOIN stations s2 ON s2.id = t.end_station_id
        WHERE t.bus_id = ? AND t.status = 'active'
        ORDER BY t.started_at DESC LIMIT 1
    ");
    $tripStmt->execute([$bus_id]);
    $trip = $tripStmt->fetch(PDO::FETCH_ASSOC);

    if ($trip) {
        // If start km > end km, the bus is traveling backward (Rizal → Cabanatuan)
        if ((float)$trip['start_km'] > (float)$trip['end_km']) {
            $direction = 'backward';
        }
    }
}

// ─── Fetch fare matrix ──────────────────────────────────────────────
$matrix = [];
$stmt = $pdo->query("SELECT passenger_type, base_km, base_fare, per_km_rate FROM fare_matrix");
while ($row = $stmt->fetch()) {
    $matrix[$row['passenger_type']] = $row;
}

$reg = $matrix['Regular']        ?? ['base_km' => 4, 'base_fare' => 15, 'per_km_rate' => 2];
$stu = $matrix['Student/SR/PWD'] ?? ['base_km' => 4, 'base_fare' => 12, 'per_km_rate' => 1.6];
$spc = $matrix['Teacher/Nurse']  ?? ['base_km' => 4, 'base_fare' => 14, 'per_km_rate' => 1.8];

// ─── Get stations based on direction ────────────────────────────────
// Use the trip's start_km as reference if GPS position is at/near a terminal edge
$reference_km = $current_km;

if (isset($trip) && $trip) {
    if ($direction === 'backward') {
        // If GPS km is at or below the endpoint, use the trip's start as reference instead
        // This handles: driver starts "Rizal→Cab" trip but kiosk GPS is still at Cabanatuan
        if ($current_km <= (float)$trip['end_km']) {
            $reference_km = (float)$trip['start_km'];
        }
    } else {
        // If GPS km is at or above the endpoint, use the trip's start as reference instead
        if ($current_km >= (float)$trip['end_km']) {
            $reference_km = (float)$trip['start_km'];
        }
    }
}

if ($direction === 'backward') {
    // Traveling Rizal → Cabanatuan: show stations with LOWER km markers
    $sql = "SELECT * FROM stations WHERE km_marker < ? AND is_active = 1 ORDER BY km_marker DESC";
} else {
    // Traveling Cabanatuan → Rizal: show stations with HIGHER km markers
    $sql = "SELECT * FROM stations WHERE km_marker > ? AND is_active = 1 ORDER BY km_marker ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$reference_km]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Calculate dynamic fare for each destination ────────────────────
foreach ($stations as &$s) {
    $dist = abs((float)$s['km_marker'] - $reference_km);
    
    // Regular Fare
    $extra_reg = max(0, $dist - (float)$reg['base_km']);
    $s['regular_fare'] = round((float)$reg['base_fare'] + ($extra_reg * (float)$reg['per_km_rate']));
    
    // Student/SR/PWD Fare
    $extra_stu = max(0, $dist - (float)$stu['base_km']);
    $s['student_fare'] = round((float)$stu['base_fare'] + ($extra_stu * (float)$stu['per_km_rate']));
    
    // Teacher/Nurse Fare
    $extra_spc = max(0, $dist - (float)$spc['base_km']);
    $s['special_fare'] = round((float)$spc['base_fare'] + ($extra_spc * (float)$spc['per_km_rate']));
}

// ─── Determine the correct origin station (direction-aware) ────────
$originStmt = $pdo->prepare("SELECT station_name FROM stations WHERE is_active=1 ORDER BY ABS(km_marker - ?) ASC LIMIT 1");
$originStmt->execute([$reference_km]);
$originName = $originStmt->fetchColumn() ?: 'Current Location';

echo json_encode([
    'origin'   => $originName,
    'direction' => $direction,
    'stations' => $stations
]);
?>