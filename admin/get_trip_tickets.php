<?php
/**
 * admin/get_trip_tickets.php — AJAX: Returns tickets for a given trip (for trip log expand).
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { echo '[]'; exit; }
$tripId = (int)($_GET['trip_id'] ?? 0);
if (!$tripId) { echo '[]'; exit; }
$stmt = $pdo->prepare("SELECT ticket_code, passenger_name, origin_name, dest_name, fare_amount FROM tickets WHERE trip_id = ? ORDER BY issued_at ASC");
$stmt->execute([$tripId]);
echo json_encode($stmt->fetchAll());
