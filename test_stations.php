<?php
require 'config/db.php';
$stmt = $pdo->query('SELECT * FROM stations ORDER BY km_marker');
foreach($stmt as $r) {
    echo $r['id'] . " | " . $r['station_name'] . " | km=" . $r['km_marker'] . " | active=" . $r['is_active'] . "\n";
}
