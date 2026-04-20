<?php
/**
 * backups/apply_coordinate_fix.php
 * Updates the Rizal terminal station to align with the Shell gas station coordinates.
 */
require_once dirname(__DIR__) . '/config/db.php';

try {
    // We target the terminal at KM 40 (the end of the linear route)
    $stmt = $pdo->prepare("
        UPDATE stations 
        SET latitude = 15.7126, 
            longitude = 121.1071
        WHERE is_terminal = 1 AND km_marker >= 39.0
    ");
    $stmt->execute();
    
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        echo "Successfully updated Rizal Terminal coordinates to match Shell station.\n";
    } else {
        echo "No station found matching the criteria (Terminal near KM 40).\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
