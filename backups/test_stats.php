<?php
session_start();
$_SESSION['driver_id'] = 2; // Test driver
$_SESSION['role'] = 'driver';
$_GET['trip_id'] = 12; // Test trip
ob_start();
include 'driver/get_trip_stats.php';
$output = ob_get_clean();
echo "OUTPUT: " . $output . "\n";
?>
