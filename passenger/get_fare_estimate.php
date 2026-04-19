<?php
/**
 * passenger/get_fare_estimate.php
 * AJAX API: Returns fare estimates for a given distance using dynamic LTFRB Matrix math.
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$distance = abs((float)($_GET['distance'] ?? 0));

if ($distance <= 0) {
    echo json_encode(['regular' => 0, 'student' => 0, 'special' => 0]);
    exit;
}

// 1. Fetch the LTFRB mathematical math matrix
$matrix = [];
$stmt = $pdo->query("SELECT passenger_type, base_km, base_fare, per_km_rate FROM fare_matrix");
while ($row = $stmt->fetch()) {
    $matrix[$row['passenger_type']] = $row;
}

// 2. Fallbacks in case the table is missing data
$reg_m = $matrix['Regular'] ?? ['base_km' => 4, 'base_fare' => 15, 'per_km_rate' => 2];
$stu_m = $matrix['Student/SR/PWD'] ?? ['base_km' => 4, 'base_fare' => 12, 'per_km_rate' => 1.6];
$spc_m = $matrix['Teacher/Nurse'] ?? ['base_km' => 4, 'base_fare' => 14, 'per_km_rate' => 1.8];

// 3. Mathematical calculation
$regular = round((float)$reg_m['base_fare'] + (max(0, $distance - (float)$reg_m['base_km']) * (float)$reg_m['per_km_rate']), 2);
$student = round((float)$stu_m['base_fare'] + (max(0, $distance - (float)$stu_m['base_km']) * (float)$stu_m['per_km_rate']), 2);
$special = round((float)$spc_m['base_fare'] + (max(0, $distance - (float)$spc_m['base_km']) * (float)$spc_m['per_km_rate']), 2);

echo json_encode([
    'regular' => number_format($regular, 2, '.', ''),
    'student' => number_format($student, 2, '.', ''),
    'special' => number_format($special, 2, '.', ''),
]);
?>
