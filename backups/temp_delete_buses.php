<?php
require_once 'c:\xampp\htdocs\PARE\config\db.php';

try {
    $pdo->beginTransaction();
    
    // Find trips for these buses
    $stmt = $pdo->query("SELECT id FROM trips WHERE bus_id IN (1, 3)");
    $tripIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($tripIds)) {
        $inQuery = implode(',', array_fill(0, count($tripIds), '?'));
        
        // Delete tickets for these trips
        $stmtTickets = $pdo->prepare("DELETE FROM tickets WHERE trip_id IN ($inQuery)");
        $stmtTickets->execute($tripIds);
        
        // Delete these trips
        $stmtTrips = $pdo->prepare("DELETE FROM trips WHERE id IN ($inQuery)");
        $stmtTrips->execute($tripIds);
    }
    
    // Now delete the buses
    $stmt = $pdo->prepare("DELETE FROM buses WHERE id IN (1, 3)");
    $stmt->execute();
    
    $deletedCount = $stmt->rowCount();
    $pdo->commit();
    echo "Successfully deleted $deletedCount buses (IDs 1 and 3) along with their associated trips and tickets.";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
