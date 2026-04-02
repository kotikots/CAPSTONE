<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT full_name, email, role, is_active FROM users WHERE role='admin'");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($admins, JSON_PRETTY_PRINT);
