<?php
require_once 'config/db.php';
$stmt = $pdo->query("
    SELECT 
        b.body_number, 
        t.id AS trip_id, 
        t.status AS trip_status,
        (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id) AS total_tix,
        (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'issued') AS issued_tix,
        (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'validated') AS validated_tix,
        (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status = 'cancelled') AS cancelled_tix
    FROM buses b 
    JOIN trips t ON t.bus_id = b.id 
    WHERE t.status = 'active'
");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
