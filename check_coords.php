<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT body_number, latitude, longitude FROM buses WHERE is_active=1");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
