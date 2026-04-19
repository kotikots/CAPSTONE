<?php
require_once 'config/db.php';

try {
    echo "Starting final DB cleanup to enforce 1:1 assignment...\n";

    // 1. Identify drivers who have more than 1 bus
    $dupes = $pdo->query("SELECT driver_id FROM buses GROUP BY driver_id HAVING COUNT(*) > 1")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($dupes as $driverId) {
        if ($driverId === null) continue;
        
        echo "Fixing duplicates for Driver ID: $driverId\n";
        
        // Find all buses for this driver
        $stmt = $pdo->prepare("SELECT id FROM buses WHERE driver_id = ? ORDER BY id ASC");
        $stmt->execute([$driverId]);
        $busIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Keep the first bus, unassign the rest
        array_shift($busIds); // first one stays
        if (!empty($busIds)) {
            $placeholders = implode(',', array_fill(0, count($busIds), '?'));
            $unassign = $pdo->prepare("UPDATE buses SET driver_id = NULL WHERE id IN ($placeholders)");
            $unassign->execute($busIds);
            echo "Unassigned " . count($busIds) . " extra bus(es) from Driver ID: $driverId\n";
        }
    }

    // 2. Add the UNIQUE constraint
    echo "Adding UNIQUE constraint to driver_id...\n";
    $pdo->exec("ALTER TABLE buses ADD CONSTRAINT uq_driver_per_bus UNIQUE (driver_id)");
    echo "SUCCESS: 1:1 constraint is now enforced at the database level.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
