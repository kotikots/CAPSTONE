<?php
/**
 * includes/functions.php
 * Shared utility functions for the PARE system.
 */

/**
 * Calculate fare based on distance and passenger type using fare_matrix table.
 */
function calculateFare(float $distanceKm, string $passengerType, PDO $pdo): float {
    $stmt = $pdo->prepare("SELECT regular_fare, student_fare, special_fare FROM distance_fares WHERE distance_km = ? LIMIT 1");
    $stmt->execute([(int)ceil($distanceKm)]);
    $row = $stmt->fetch();

    if (!$row) {
        $row = ['regular_fare' => 97.00, 'student_fare' => 79.00, 'special_fare' => 84.00];
    }

    if ($passengerType === 'student') {
        return (float)$row['student_fare'];
    } elseif ($passengerType === 'special') {
        return (float)$row['special_fare'];
    }
    return (float)$row['regular_fare'];
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
 * Get the active trip for a given bus (or driver's bus).
 */
function getActiveTripForBus(PDO $pdo, int $busId): ?array {
    $stmt = $pdo->prepare(
        "SELECT t.*, b.body_number, b.plate_number, d.full_name AS driver_name,
                s1.station_name AS start_name, s2.station_name AS end_name
         FROM   trips t
         JOIN   buses    b  ON b.id  = t.bus_id
         JOIN   drivers  d  ON d.id  = t.driver_id
         JOIN   stations s1 ON s1.id = t.start_station_id
         JOIN   stations s2 ON s2.id = t.end_station_id
         WHERE  t.bus_id = ? AND t.status = 'active'
         ORDER  BY t.started_at DESC LIMIT 1"
    );
    $stmt->execute([$busId]);
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
