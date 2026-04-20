<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';
$stmt = $pdo->query("SHOW CREATE TABLE trips");
$res = $stmt->fetch(PDO::FETCH_ASSOC);
echo $res['Create Table'];
?>
