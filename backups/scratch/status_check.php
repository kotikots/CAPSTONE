<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=pare', 'root', '');
    $stmt = $pdo->query("
        SELECT 
            b.body_number, 
            t.id AS trip_id, 
            t.status AS trip_status,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id) AS count_total,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'issued') AS count_issued,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'validated') AS count_validated,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND id NOT IN (SELECT ticket_id FROM payments)) AS count_unpaid_by_payments_table,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND id IN (SELECT ticket_id FROM payments)) AS count_paid_by_payments_table
        FROM buses b 
        JOIN trips t ON t.bus_id = b.id 
        WHERE t.status = 'active'
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
