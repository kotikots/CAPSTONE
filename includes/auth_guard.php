<?php
/**
 * includes/auth_guard.php
 * Session authentication guard for all protected pages.
 * Set $requiredRole ('passenger', 'admin', 'driver') before including.
 *
 * No-cache headers prevent the browser from serving stale protected pages
 * from its back/forward cache after a logout. Every navigation to a
 * protected page will make a fresh server request so the session check runs.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevent browser (and any proxy) from caching protected pages.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');


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
            'driver' => '/PARE/driver/dashboard_v2.php',
            default  => '/PARE/passenger/dashboard.php',
        };
        header('Location: ' . $redirect);
        exit;
    }
}
