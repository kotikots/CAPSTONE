<?php
require 'config/db.php';
$stmt = $pdo->prepare("SELECT * FROM stations WHERE km_marker < ? AND is_active = 1 ORDER BY km_marker DESC");
$stmt->execute([40.16]);
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($stations as $s) {
    echo $s['station_name'] . "\n";
}
