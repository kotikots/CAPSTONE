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

/**
 * Check if an IP or identifier has exceeded the password-reset rate limit.
 * Limits: 3 attempts per 15-minute window per IP, and 3 per identifier.
 * Auto-creates the password_reset_attempts table if it doesn't exist.
 *
 * @param  PDO    $pdo
 * @param  string $ip         The requester's IP address
 * @param  string $identifier The email or ID number submitted
 * @return bool   true = rate-limited (block the request), false = OK to proceed
 */
function checkResetRateLimit(PDO $pdo, string $ip, string $identifier): bool {
    // Ensure the attempts table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_attempts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        ip_address    VARCHAR(45)  NOT NULL,
        identifier    VARCHAR(255) NOT NULL DEFAULT '',
        attempted_at  DATETIME     NOT NULL DEFAULT NOW(),
        INDEX idx_ip   (ip_address),
        INDEX idx_ident (identifier),
        INDEX idx_time  (attempted_at)
    )");

    $window    = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $maxPerIp  = 3;
    $maxPerIdent = 3;

    // Count by IP
    $stmtIp = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = ? AND attempted_at > ?");
    $stmtIp->execute([$ip, $window]);
    if ((int)$stmtIp->fetchColumn() >= $maxPerIp) return true;

    // Count by identifier
    $stmtId = $pdo->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE identifier = ? AND attempted_at > ?");
    $stmtId->execute([$identifier, $window]);
    if ((int)$stmtId->fetchColumn() >= $maxPerIdent) return true;

    return false;
}

/**
 * Record one password-reset attempt for rate-limiting purposes.
 *
 * @param PDO    $pdo
 * @param string $ip
 * @param string $identifier
 */
function logResetAttempt(PDO $pdo, string $ip, string $identifier): void {
    try {
        $pdo->prepare("INSERT INTO password_reset_attempts (ip_address, identifier) VALUES (?, ?)")
            ->execute([$ip, $identifier]);
    } catch (PDOException $e) {
        error_log("Reset Attempt Log Error: " . $e->getMessage());
    }
}
