<?php
require_once 'c:/xampp/htdocs/PARE/config/db.php';

try {
    $pdo->beginTransaction();

    $ids = [2, 11];

    // 1. Check for tickets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE passenger_id IN (2, 11)");
    $stmt->execute();
    $ticketCount = $stmt->fetchColumn();

    // 2. Delete tickets if any
    if ($ticketCount > 0) {
        $pdo->query("DELETE FROM tickets WHERE passenger_id IN (2, 11)");
    }

    // 3. Delete passengers
    $stmt = $pdo->prepare("DELETE FROM users WHERE id IN (2, 11) AND role = 'passenger'");
    $stmt->execute();
    $passengersDeleted = $stmt->rowCount();

    $pdo->commit();

    echo json_encode([
        "success" => true,
        "passengersDeleted" => $passengersDeleted,
        "ticketsDeleted" => $ticketCount
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
