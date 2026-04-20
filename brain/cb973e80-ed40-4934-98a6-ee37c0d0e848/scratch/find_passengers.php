<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE full_name LIKE '%Khian%' AND role = 'passenger'");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
?>
