<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$stmt = $pdo->query("SELECT id, full_name FROM drivers WHERE full_name LIKE '%Juan%' OR full_name LIKE '%Khian%'");
$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($drivers);
?>
