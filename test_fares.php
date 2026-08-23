<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT * FROM fare_matrix');
foreach($stmt as $r) {
    echo $r['passenger_type'] . " | base_km=" . $r['base_km'] . " | base_fare=" . $r['base_fare'] . " | per_km_rate=" . $r['per_km_rate'] . "\n";
}
