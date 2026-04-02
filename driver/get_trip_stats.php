<?php
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['driver_id']) || $_SESSION['role'] !== 'driver') {
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

// 1. Get recent passengers (last 15 for the feed)
$paxStmt = $pdo->prepare("
    SELECT passenger_name, passenger_type, origin_name, dest_name, fare_amount, issued_at 
    FROM tickets 
    WHERE trip_id = ? 
    ORDER BY issued_at DESC 
    LIMIT 15
");
$paxStmt->execute([$trip_id]);
$recentPax = $paxStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Get trip totals for cash liquidation
$statStmt = $pdo->prepare("
    SELECT IFNULL(SUM(fare_amount), 0) as total_cash, 
           COUNT(id) as total_passengers 
    FROM tickets 
    WHERE trip_id = ?
");
$statStmt->execute([$trip_id]);
$stats = $statStmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total_cash' => (float)$stats['total_cash'],
    'total_passengers' => (int)$stats['total_passengers'],
    'recent_passengers' => $recentPax
]);
?>
