<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$driver_id = $_SESSION['driver_id'];
$trip_id = $_GET['trip_id'] ?? 0;

if (!$trip_id) {
    echo json_encode(['error' => 'No trip ID provided']);
    exit;
}

// Ensure this trip belongs to this driver
$stmt = $pdo->prepare("SELECT id, status FROM trips WHERE id = ? AND driver_id = ?");
$stmt->execute([$trip_id, $driver_id]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Invalid trip']);
    exit;
}

// 1. Get current bus location
$locStmt = $pdo->prepare("SELECT latitude, longitude FROM bus_locations WHERE trip_id = ? ORDER BY recorded_at DESC LIMIT 1");
$locStmt->execute([$trip_id]);
$busLoc = $locStmt->fetch(PDO::FETCH_ASSOC);

/**
 * Distance calculation (Haversine)
 */
function getDistance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999;
    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}

// 2. Get recent passengers with proximity and payment status
$paxStmt = $pdo->prepare("
    SELECT t.id, t.passenger_name, t.passenger_type, t.origin_name, t.dest_name, t.fare_amount, t.issued_at, t.status,
           s.latitude AS dest_lat, s.longitude AS dest_lng,
           (SELECT id FROM payments WHERE ticket_id = t.id LIMIT 1) as payment_id
    FROM   tickets t
    JOIN   stations s ON s.id = t.dest_station_id
    LEFT JOIN payments p ON p.ticket_id = t.id
    WHERE  t.trip_id = ? AND (p.remitted IS NULL OR p.remitted = 0)
    ORDER  BY t.issued_at DESC 
    LIMIT  30
");
$paxStmt->execute([$trip_id]);
$rawPax = $paxStmt->fetchAll(PDO::FETCH_ASSOC);

$recentPax = [];
foreach ($rawPax as $p) {
    $dist = getDistance($busLoc['latitude'] ?? null, $busLoc['longitude'] ?? null, $p['dest_lat'], $p['dest_lng']);
    $p['distance_km'] = $dist;
    $p['proximity'] = ($dist < 1.0) ? 'near' : 'far'; // 1km threshold
    $p['is_paid'] = $p['payment_id'] ? true : false;
    $recentPax[] = $p;
}

// Sort: Near destination first, then by time
usort($recentPax, function($a, $b) {
    if ($a['proximity'] === $b['proximity']) {
        return strtotime($b['issued_at']) - strtotime($a['issued_at']);
    }
    return ($a['proximity'] === 'near') ? -1 : 1;
});

// 3. Get trip totals: Total vs Collected
$statStmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN p.id IS NULL OR p.remitted = 0 THEN t.id END) as total_passengers,
        SUM(t.fare_amount) as total_potential,
        SUM(CASE WHEN p.id IS NOT NULL AND p.remitted = 0 THEN t.fare_amount ELSE 0 END) as collected_cash,
        SUM(CASE WHEN p.id IS NULL THEN t.fare_amount ELSE 0 END) as pending_cash,
        SUM(CASE WHEN p.id IS NOT NULL AND p.remitted = 1 THEN t.fare_amount ELSE 0 END) as remitted_cash
    FROM tickets t
    LEFT JOIN payments p ON p.ticket_id = t.id
    WHERE t.trip_id = ?
");
$statStmt->execute([$trip_id]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

// 4. Get global all-time unremitted cash for the driver's dashboard
$totalUnremittedStmt = $pdo->prepare("
    SELECT COALESCE(SUM(t.fare_amount), 0)
    FROM tickets t
    JOIN trips tr ON tr.id = t.trip_id
    JOIN payments p ON p.ticket_id = t.id
    WHERE tr.driver_id = ? AND p.remitted = 0
");
$totalUnremittedStmt->execute([$driver_id]);
$all_time_cash_in_hand = (float)$totalUnremittedStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'total_passengers' => (int)$stats['total_passengers'],
    'total_potential' => (float)$stats['total_potential'],
    'collected_cash' => (float)$stats['collected_cash'],
    'pending_cash' => (float)$stats['pending_cash'],
    'total_cash' => (float)$stats['collected_cash'] + (float)$stats['pending_cash'],
    'all_time_cash_in_hand' => $all_time_cash_in_hand,
    'recent_passengers' => $recentPax
]);
?>
