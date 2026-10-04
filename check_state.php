<?php
require_once 'config/db.php';
header("Content-Type: text/plain");

echo "=== BUSES ===\n";
$stmt = $pdo->query("SELECT id, body_number, is_active, latitude, longitude, driver_id FROM buses");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== TRIPS ===\n";
$stmt = $pdo->query("SELECT id, bus_id, driver_id, status FROM trips WHERE status='active'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== DRIVERS ===\n";
$stmt = $pdo->query("SELECT id, full_name, is_active FROM drivers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
