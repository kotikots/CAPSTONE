<?php
require_once 'config/db.php';

$stmt = $pdo->query("
    SELECT plate_number, body_number, driver_id 
    FROM buses 
    WHERE driver_id IN (
        SELECT driver_id FROM buses GROUP BY driver_id HAVING COUNT(*) > 1
    )
    ORDER BY driver_id
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results, JSON_PRETTY_PRINT);
