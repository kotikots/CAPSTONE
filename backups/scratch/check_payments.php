<?php
require_once __DIR__ . '/../config/db.php';
$cols = $pdo->query('DESCRIBE payments')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
