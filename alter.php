<?php
require 'config/db.php';
try {
    $pdo->exec("ALTER TABLE drivers ADD COLUMN is_archived TINYINT(1) DEFAULT 0");
    echo 'drivers is_archived added successfully';
} catch (Exception $e) {
    echo 'Error or already exists: ' . $e->getMessage();
}
