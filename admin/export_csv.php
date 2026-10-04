<?php
/**
 * admin/export_csv.php — Download all tickets as CSV for a given date range.
 */
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_PATH . '/'); exit;
}

// Disable the global custom error handler for this file so harmless notices don't trigger the HTML error page
restore_error_handler();
restore_exception_handler();
error_reporting(0);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

try {
    $stmt = $pdo->prepare(
        "SELECT t.ticket_code, t.passenger_name, t.passenger_type,
                t.origin_name, t.dest_name, t.distance_km, t.fare_amount,
                t.issued_at, t.status,
                d.full_name AS driver_name, b.body_number, b.plate_number,
                p.payment_method, p.remitted
         FROM   tickets  t
         JOIN   trips    tr ON tr.id = t.trip_id
         JOIN   buses    b  ON b.id  = tr.bus_id
         JOIN   drivers  d  ON d.id  = tr.driver_id
         LEFT JOIN payments p ON p.ticket_id = t.id
         WHERE  DATE(t.issued_at) BETWEEN ? AND ?
         ORDER  BY t.issued_at ASC"
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    // If the database fails, return a CSV with the error message so the user can see it!
    $rows = [];
    $db_error = $e->getMessage();
}

$filename = 'PARE_Tickets_' . $from . '_to_' . $to . '.csv';

$fp = fopen('php://temp', 'r+');

// Headers
fputcsv($fp, [
    'Ticket Code','Passenger Name','Passenger Type',
    'Origin','Destination','Distance (km)','Fare (PHP)',
    'Issued At','Status','Driver','Bus Body No.','Plate No.',
    'Payment Method','Remitted'
]);

if (isset($db_error)) {
    fputcsv($fp, ['ERROR', $db_error]);
} else {
    foreach ($rows as $row) {
        fputcsv($fp, [
            $row['ticket_code'],
            $row['passenger_name'],
            $row['passenger_type'],
            $row['origin_name'],
            $row['dest_name'],
            $row['distance_km'],
            number_format((float)$row['fare_amount'], 2),
            $row['issued_at'],
            $row['status'],
            $row['driver_name'],
            $row['body_number'],
            $row['plate_number'],
            $row['payment_method'] ?? 'cash',
            $row['remitted'] ? 'Yes' : 'No',
        ]);
    }
}

rewind($fp);
$csvData = stream_get_contents($fp);
fclose($fp);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csvData));

echo $csvData;
exit;
