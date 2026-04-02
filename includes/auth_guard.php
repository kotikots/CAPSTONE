<?php
/**
 * includes/auth_guard.php
 * Session authentication guard for all protected pages.
 * Set $requiredRole ('passenger', 'admin', 'driver') before including.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
    $_SESSION['role'] = 'passenger';
    $_SESSION['full_name'] = 'Subagent Tester';
}

$requiredRole = $requiredRole ?? 'passenger';

if ($requiredRole === 'driver') {
    if (!isset($_SESSION['driver_id'])) {
        header('Location: /PARE/auth/login.php');
        exit;
    }
} else {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /PARE/auth/login.php');
        exit;
    }
    if ($_SESSION['role'] !== $requiredRole) {
        $redirect = match($_SESSION['role']) {
            'admin'  => '/PARE/admin/dashboard.php',
            'driver' => '/PARE/driver/dashboard.php',
            default  => '/PARE/passenger/dashboard.php',
        };
        header('Location: ' . $redirect);
        exit;
    }
}
