<?php
require 'c:\xampp\htdocs\PARE\config\db.php';
// Clean users table
$pdo->exec("UPDATE users SET contact_number = REPLACE(contact_number, '+630', '0') WHERE contact_number LIKE '+630%'");
$pdo->exec("UPDATE users SET contact_number = REPLACE(contact_number, '+63', '0') WHERE contact_number LIKE '+63%'");
$pdo->exec("UPDATE users SET emergency_contact_number = REPLACE(emergency_contact_number, '+630', '0') WHERE emergency_contact_number LIKE '+630%'");
$pdo->exec("UPDATE users SET emergency_contact_number = REPLACE(emergency_contact_number, '+63', '0') WHERE emergency_contact_number LIKE '+63%'");

// Clean drivers table
$pdo->exec("UPDATE drivers SET contact_number = REPLACE(contact_number, '+630', '0') WHERE contact_number LIKE '+630%'");
$pdo->exec("UPDATE drivers SET contact_number = REPLACE(contact_number, '+63', '0') WHERE contact_number LIKE '+63%'");

echo 'Database successfully scrubbed of all legacy +63 numbers!';
