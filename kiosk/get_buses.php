<?php
/**
 * kiosk/get_buses.php
 * Fetches the active fleet of buses for the kiosk initialization dropdown.
 */
require_once '../config/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, body_number, plate_number FROM buses WHERE is_active = 1 ORDER BY body_number ASC");
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'buses' => $buses]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
