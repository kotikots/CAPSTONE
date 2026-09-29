<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM tickets");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('schema_out.txt', print_r($res, true));
