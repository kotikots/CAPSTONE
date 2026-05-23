<?php
/**
 * includes/functions.php
 * 
 * PURPOSE: A collection of shared "tools" used across the entire system.
 * Instead of writing the same code many times, we put it here and just "call" it.
 */

/**
 * MATH: Calculate Fare
 * It looks at the 'fare_matrix' table to see the pricing rules for 
 * Regular, Student/SR, and Teacher/Nurse.
 */
function calculateFare(float $distanceKm, string $passengerType, PDO $pdo): float {
    // Fetch all pricing rules from the database
    $matrix = [];
    $stmt = $pdo->query("SELECT passenger_type, base_km, base_fare, per_km_rate FROM fare_matrix");
    while ($r = $stmt->fetch()) {
        $matrix[$r['passenger_type']] = $r;
    }

    // Map the simple codes (student, special) to the long names in the database
    $typeKey = 'Regular';
    if ($passengerType === 'student') $typeKey = 'Student/SR/PWD';
    if ($passengerType === 'special') $typeKey = 'Teacher/Nurse';

    // Get the specific rules for this passenger type
    $rules = $matrix[$typeKey] ?? ['base_km' => 4, 'base_fare' => 15, 'per_km_rate' => 2];

    // THE FORMULA: Base Fare + (Extra distance * Rate per KM)
    $dist = abs($distanceKm);
    $extra = max(0, $dist - (float)$rules['base_km']);
    $fare = (float)$rules['base_fare'] + ($extra * (float)$rules['per_km_rate']);
    
    // Always round to the nearest whole peso for convenience
    return round($fare);
}

/**
 * ID GENERATOR: Create a unique Ticket Code
 * Format: TKT-YYYYMMDD-00001
 */
function generateTicketCode(PDO $pdo): string {
    $datePart = date('Ymd');
    $stmt     = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(issued_at) = CURDATE()");
    $count    = (int)$stmt->fetchColumn() + 1;
    return 'TKT-' . $datePart . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

/**
 * TRACKING: Get the current active trip for a bus
 * Returns the route (Origin -> End), driver name, and bus body number.
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
 * FORMATTING: Display numbers as Philippine Peso
 * Example: 15.5 -> ₱ 15.50
 */
function peso(float $amount): string {
    return '₱ ' . number_format($amount, 2);
}

/**
 * DISTANCE: Get math between two station IDs
 * It simply subtracts the Kilometer Markers of the two stations.
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

/**
 * SECURITY: Encrypt an integer ID into a URL-safe secure token.
 */
function encryptId($id): string {
    if (empty($id)) return '';
    $key = 'PARE_SECRET_ENCRYPTION_KEY_2026';
    $cipher = 'aes-256-cbc';
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext = openssl_encrypt((string)$id, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    $token = base64_encode($iv . $ciphertext);
    return str_replace(['+', '/', '='], ['-', '_', ''], $token);
}

/**
 * SECURITY: Decrypt a URL-safe secure token back into the integer ID.
 */
function decryptId($token): int {
    if (empty($token)) return 0;
    $token = str_replace(['-', '_'], ['+', '/'], $token);
    $padding = strlen($token) % 4;
    if ($padding) {
        $token .= str_repeat('=', 4 - $padding);
    }
    $decoded = base64_decode($token);
    $cipher = 'aes-256-cbc';
    $ivlen = openssl_cipher_iv_length($cipher);
    if (strlen($decoded) <= $ivlen) return 0;
    
    $iv = substr($decoded, 0, $ivlen);
    $ciphertext = substr($decoded, $ivlen);
    $decrypted = openssl_decrypt($ciphertext, $cipher, 'PARE_SECRET_ENCRYPTION_KEY_2026', OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? (int)$decrypted : 0;
}
?>
