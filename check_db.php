<?php
require 'config/db.php';
try {
    $pdo->query("SELECT 1 FROM payments LIMIT 1");
    echo "TABLE_EXISTS";
} catch (Exception $e) {
    echo "TABLE_MISSING: " . $e->getMessage();
}
