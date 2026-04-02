<?php
// Example Logic based on your image
$originKM = $_POST['origin_km']; // e.g., 5 (Aglipay)
$destKM = $_POST['dest_km'];     // e.g., 14 (Luna)
$type = $_POST['type'];          // Regular, Student, etc.

$distance = abs($destKM - $originKM);

// Query your database fare_matrix table
// SELECT fare FROM fare_matrix WHERE distance_km = $distance AND type = '$type'
$finalFare = 0.00; 

// Base calculation example from your image:
if ($distance <= 4) {
    $finalFare = 15.00;
} else {
    // Logic to look up the specific row in your Fare Matrix table
}

// Save to 'transactions' table for the Admin and Print
?>