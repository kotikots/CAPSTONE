<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT station_name, latitude, longitude FROM stations");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['station_name'] . " | " . $row['latitude'] . " | " . $row['longitude'] . "\n";
}
