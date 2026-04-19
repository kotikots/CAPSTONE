<?php
require 'config/db.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $id1 = $pdo->query("SELECT id FROM stations LIMIT 1")->fetchColumn();
    $id2 = $pdo->query("SELECT id FROM stations ORDER BY id DESC LIMIT 1")->fetchColumn();
    
    // Test Trip Auto-Create
    $busId = 1;
    $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status)
                              SELECT ?, b.driver_id, ?, ?, 'active'
                              FROM buses b WHERE b.id = ?");
    $ins->execute([$busId, $id1, $id2, $busId]);
    echo "Trip insert OK\n";
    $tripId = $pdo->lastInsertId();

} catch (Exception $e) {
    echo "Trip fail: " . $e->getMessage() . "\n";
}
