<?php
require_once __DIR__ . '/../config/db.php';
$stations = $pdo->query('SELECT id, station_name, km_marker, sort_order FROM stations WHERE is_active=1 ORDER BY sort_order ASC')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($stations, JSON_PRETTY_PRINT);
