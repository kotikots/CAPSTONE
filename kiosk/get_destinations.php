<?php
require_once '../config/db.php';
header('Content-Type: application/json');

$current_km = (int)($_GET['current_km'] ?? 0);

// Get all stations ahead of current location, and lookup fare in distance_fares
$sql = "
    SELECT s.*, 
           df.regular_fare, 
           df.student_fare, 
           df.special_fare
    FROM stations s
    LEFT JOIN distance_fares df ON df.distance_km = ABS(s.km_marker - ?)
    WHERE s.km_marker > ? 
    ORDER BY s.km_marker ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$current_km, $current_km]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback to max distance if something goes wrong or goes off chart
foreach ($stations as &$s) {
    if (!$s['regular_fare']) {
        $s['regular_fare'] = 97.00;
        $s['student_fare'] = 79.00;
        $s['special_fare'] = 84.00;
    }
}

echo json_encode($stations);
?>