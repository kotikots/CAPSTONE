<?php
require 'config/db.php';
require 'includes/functions_v2.php';

echo "Fare for Student at 10km: " . calculateFare(10, 'student', $pdo) . "\n";
echo "Fare for Regular at 10km: " . calculateFare(10, 'regular', $pdo) . "\n";
echo "Fare for Special at 10km: " . calculateFare(10, 'special', $pdo) . "\n";

echo "Fare for Regular at 2km: " . calculateFare(2, 'regular', $pdo) . "\n";
?>
