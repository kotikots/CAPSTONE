<?php
require_once dirname(__DIR__) . '/config/db.php';

$lat = 15.70535405456752;
$lng = 121.0990126880924;

$stmt = $pdo->prepare("UPDATE stations SET latitude = ?, longitude = ? WHERE is_terminal = 1 AND km_marker >= 39.0");
$stmt->execute([$lat, $lng]);

echo "Rows updated: " . $stmt->rowCount() . "\n";

// Verify
$row = $pdo->query("SELECT station_name, km_marker, latitude, longitude FROM stations WHERE is_terminal = 1 AND km_marker >= 39.0")->fetch();
echo "Station: {$row['station_name']}\n";
echo "KM: {$row['km_marker']}\n";
echo "Lat: {$row['latitude']}\n";
echo "Lng: {$row['longitude']}\n";
