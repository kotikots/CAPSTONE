<?php
/**
 * driver/api_get_earnings_records.php
 * Fetches ticket records for a specific earnings period.
 */
require_once '../config/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$driverId = $_SESSION['driver_id'];
$input = json_decode(file_get_contents('php://input'), true);
$period = $input['period'] ?? '';

$where = "tr.driver_id = ?";
switch ($period) {
    case 'today':
        $where .= " AND DATE(t.issued_at) = CURDATE()";
        break;
    case 'week':
        $where .= " AND YEARWEEK(t.issued_at) = YEARWEEK(NOW())";
        break;
    case 'month':
        $where .= " AND MONTH(t.issued_at)=MONTH(NOW()) AND YEAR(t.issued_at)=YEAR(NOW())";
        break;
    case 'all':
        $where .= " AND 1=1";
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid period']);
        exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT t.ticket_code, t.passenger_name, t.origin_name, t.dest_name, t.fare_amount, t.issued_at, t.status, p.remitted
        FROM tickets t
        JOIN trips tr ON tr.id = t.trip_id
        LEFT JOIN payments p ON p.ticket_id = t.id
        WHERE $where
        ORDER BY t.issued_at DESC
        LIMIT 100
    ");
    $stmt->execute([$driverId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'records' => $records]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
