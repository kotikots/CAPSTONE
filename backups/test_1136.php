<?php
require 'config/db.php';
$pdo->exec("DROP TABLE IF EXISTS test1136");
$pdo->exec("CREATE TABLE test1136 (id INT, name VARCHAR(20))");
try {
    // This throws 1054 Unknown Column, not 1136
    $pdo->exec("INSERT INTO test1136 (id, name, extra_col) VALUES (1, 'A', 'B')");
} catch(Exception $e) { echo "Test 1: " . $e->getMessage() . "\n"; }

try {
    // This throws 1136 Column match
    $pdo->exec("INSERT INTO test1136 (id, name) VALUES (1, 'A', 'B')");
} catch(Exception $e) { echo "Test 2: " . $e->getMessage() . "\n"; }
