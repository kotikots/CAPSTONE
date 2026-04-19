<?php
/**
 * passenger/get_fare.php   — STEP 7
 * AJAX endpoint: returns calculated fare given origin, destination, passenger type.
 */
require_once '../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$originId = (int)($_GET['origin_id'] ?? 0);
$destId   = (int)($_GET['dest_id']   ?? 0);
$type     = trim($_GET['type'] ?? 'regular');

$allowedTypes = ['regular', 'student', 'special'];
if (!in_array($type, $allowedTypes) || !$originId || !$destId || $originId === $destId) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$distance = getDistance($pdo, $originId, $destId);
if ($distance <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid route: destination must be further than origin']);
    exit;
}

$fare = calculateFare($distance, $type, $pdo);

echo json_encode([
    'success'     => true,
    'fare'        => $fare,
    'distance_km' => $distance,
    'type'        => $type,
]);
