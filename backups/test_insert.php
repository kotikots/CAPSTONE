<?php
require 'config/db.php';
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test Trip Auto-Create
    $busId = 1;
    $ins = $pdo->prepare("INSERT INTO trips (bus_id, driver_id, start_station_id, end_station_id, status)
                              SELECT ?, b.driver_id,
                                     (SELECT id FROM stations WHERE sort_order = 1 LIMIT 1),
                                     (SELECT id FROM stations ORDER BY sort_order DESC LIMIT 1),
                                     'active'
                              FROM buses b WHERE b.id = ?");
    $ins->execute([$busId, $busId]);
    echo "Trip insert OK\n";
    $tripId = $pdo->lastInsertId();

} catch (Exception $e) {
    echo "Trip fail: " . $e->getMessage() . "\n";
}
