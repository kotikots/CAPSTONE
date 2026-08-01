<?php
/**
 * auth/session_check.php
 * Lightweight endpoint: returns JSON session status.
 * Called by the client-side bfcache guard on back/forward navigation.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
// Never cache this response
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$loggedIn = false;
$role     = null;

if (isset($_SESSION['driver_id'])) {
    $loggedIn = true;
    $role     = 'driver';
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $loggedIn = true;
    $role     = $_SESSION['role']; // 'passenger' or 'admin'
}

echo json_encode(['loggedIn' => $loggedIn, 'role' => $role]);
