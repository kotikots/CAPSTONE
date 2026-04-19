<?php
require_once 'config/db.php';
header('Content-Type: application/json');

// Get actual tickets table columns
$cols = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_ASSOC);
$columnNames = array_column($cols, 'Field');

// Get actual trips table columns  
$tripCols = $pdo->query("SHOW COLUMNS FROM trips")->fetchAll(PDO::FETCH_ASSOC);
$tripColumnNames = array_column($tripCols, 'Field');

echo json_encode([
    'tickets_columns' => $columnNames,
    'tickets_count' => count($columnNames),
    'trips_columns' => $tripColumnNames,
    'trips_count' => count($tripColumnNames),
], JSON_PRETTY_PRINT);
