<?php
// Cache Buster: v1.0.2
/**
 * includes/functions.php
 * Shared utility functions for the PARE system.
 */

/**
 * Calculate fare based on distance and passenger type using fare_matrix table.
 */
function calculateFare(float $distanceKm, string $passengerType, PDO $pdo): float {
    $matrix = [];
    $stmt = $pdo->query("SELECT passenger_type, base_km, base_fare, per_km_rate FROM fare_matrix");
    while ($r = $stmt->fetch()) {
        $matrix[$r['passenger_type']] = $r;
    }

    $typeKey = 'Regular';
    if ($passengerType === 'student') $typeKey = 'Student/SR/PWD';
    if ($passengerType === 'special') $typeKey = 'Teacher/Nurse';

    $rules = $matrix[$typeKey] ?? ['base_km' => 4, 'base_fare' => 15, 'per_km_rate' => 2];

    $dist = abs($distanceKm);
    $extra = max(0, $dist - (float)$rules['base_km']);
    $fare = (float)$rules['base_fare'] + ($extra * (float)$rules['per_km_rate']);
    
    return round($fare, 2);
}

/**
 * Generate unique ticket code: TKT-YYYYMMDD-NNNNN
 */
function generateTicketCode(PDO $pdo): string {
    $datePart = date('Ymd');
    $stmt     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(issued_at) = CURDATE()");
    $count    = (int)$stmt->fetchColumn() + 1;
    return 'TKT-' . $datePart . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

/**
 * Get the active trip for a given bus (optional: filter by driver).
 */
function getLiveTrip(PDO $pdo, int $busId, int $driverId = 0): ?array {
    $where = "t.bus_id = ? AND t.status = 'active'";
    $params = [$busId];
    
    if ($driverId > 0) {
        $where .= " AND t.driver_id = ?";
        $params[] = $driverId;
    }

    $stmt = $pdo->prepare(
        "SELECT t.*, b.body_number, b.plate_number, d.full_name AS driver_name,
                s1.station_name AS start_name, s2.station_name AS end_name
         FROM   trips t
         JOIN   buses    b  ON b.id  = t.bus_id
         JOIN   drivers  d  ON d.id  = t.driver_id
         JOIN   stations s1 ON s1.id = t.start_station_id
         JOIN   stations s2 ON s2.id = t.end_station_id
         WHERE  $where
         ORDER  BY t.started_at DESC LIMIT 1"
    );
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

/**
 * Get latest bus location row.
 */
function getLatestBusLocation(PDO $pdo, int $busId): ?array {
    $stmt = $pdo->prepare(
        "SELECT latitude, longitude, speed_kmh, recorded_at
         FROM   bus_locations
         WHERE  bus_id = ?
         ORDER  BY recorded_at DESC LIMIT 1"
    );
    $stmt->execute([$busId]);
    return $stmt->fetch() ?: null;
}

/**
 * Format as Philippine Peso string.
 */
function peso(float $amount): string {
    return '₱ ' . number_format($amount, 2);
}

/**
 * Get distance between two stations in km (using km_marker difference).
 */
function getDistance(PDO $pdo, int $originId, int $destId): float {
    $stmt = $pdo->prepare(
        "SELECT ABS(
            (SELECT km_marker FROM stations WHERE id = ?) -
            (SELECT km_marker FROM stations WHERE id = ?)
         ) AS dist"
    );
    $stmt->execute([$originId, $destId]);
    return (float)($stmt->fetchColumn() ?? 0);
}
