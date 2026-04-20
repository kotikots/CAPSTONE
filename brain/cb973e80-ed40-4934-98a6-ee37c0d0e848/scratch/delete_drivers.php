<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Delete tickets associated with trips from these drivers (though we checked and it was 0, it's safer)
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE trip_id IN (SELECT id FROM trips WHERE driver_id IN (1, 2))");
    $stmt->execute();

    // 2. Delete trips associated with these drivers
    $stmt = $pdo->prepare("DELETE FROM trips WHERE driver_id IN (1, 2)");
    $stmt->execute();
    $tripsDeleted = $stmt->rowCount();

    // 3. Unassign them from any buses (just in case)
    $stmt = $pdo->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id IN (1, 2)");
    $stmt->execute();

    // 4. Delete the drivers themselves
    $stmt = $pdo->prepare("DELETE FROM drivers WHERE id IN (1, 2)");
    $stmt->execute();
    $driversDeleted = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "driversDeleted" => $driversDeleted,
        "tripsDeleted" => $tripsDeleted
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
