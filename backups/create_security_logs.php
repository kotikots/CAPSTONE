<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS security_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(150),
        role_attempted VARCHAR(20),
        status ENUM('success', 'failure'),
        reason VARCHAR(255),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";
    
    $pdo->exec($sql);
    echo "Table security_logs created successfully.\n";
    
    // Add an index for performance
    $pdo->exec("CREATE INDEX idx_security_ip_time ON security_logs(ip_address, created_at)");
    echo "Index created successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
