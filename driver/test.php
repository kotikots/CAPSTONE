<?php
session_start();
$_SESSION['driver_id']=3;
$_SESSION['role']='driver';
$_GET['trip_id']=13;
include 'c:/xampp/htdocs/PARE/driver/get_trip_stats.php';
?>
