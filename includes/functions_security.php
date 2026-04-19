<?php
/**
 * includes/functions_security.php
 * Security helpers for logging and progressive tiered brute-force protection.
 */

/**
 * Log a security event (login success/failure, etc.)
 */
function logSecurityEvent($pdo, $identifier, $role, $status, $reason) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    try {
        $stmt = $pdo->prepare("INSERT INTO security_logs (identifier, role_attempted, status, reason, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$identifier, $role, $status, $reason, $ip, $ua]);
    } catch (PDOException $e) {
        error_log("Security Log Error: " . $e->getMessage());
    }
}

