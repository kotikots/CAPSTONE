<?php
require 'c:/xampp/htdocs/PARE/config/db.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $t) {
    echo $t . "\n";
    $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) {
        echo "  " . $c['Field'] . " - " . $c['Type'] . "\n";
    }
}
