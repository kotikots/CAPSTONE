<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$stmt = $pdo->query("DESCRIBE tickets");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);
?>
