<?php
/**
 * admin/assign_bus_handler.php
 * Handles bus assignment/reassignment from the Drivers list popup.
 */
$requiredRole = 'admin';
require_once '../config/db.php';
require_once '../includes/auth_guard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driverId = (int)($_POST['driver_id'] ?? 0);
    $busId    = (int)($_POST['bus_id']    ?? 0); // 0 means unassign

    if (!$driverId) {
        die("Invalid driver selection.");
    }

    try {
        $pdo->beginTransaction();
        // 0. Prevent unassigning if driver is on the road or has unremitted cash
        if ($busId === 0) {
            $active = $pdo->prepare("SELECT id FROM trips WHERE driver_id = ? AND status = 'active' LIMIT 1");
            $active->execute([$driverId]);
            if ($active->fetch()) {
                header("Location: drivers.php?error=unassign_active_trip");
                exit;
            }
            
            $unremitted = $pdo->prepare("
                SELECT t.id FROM tickets t 
                JOIN trips tr ON t.trip_id = tr.id 
                WHERE tr.driver_id = ? AND t.status != 'remitted' LIMIT 1
            ");
            $unremitted->execute([$driverId]);
            if ($unremitted->fetch()) {
                header("Location: drivers.php?error=unassign_unremitted");
                exit;
            }
        }

        // 1. Unassign the driver from ANY bus they might currently have
        $pdo->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ?")->execute([$driverId]);

        // 2. If a new bus is selected, assign it
        if ($busId > 0) {
            // First, double check that this bus isn't taken (in case of race conditions)
            $check = $pdo->prepare("SELECT driver_id FROM buses WHERE id = ? FOR UPDATE");
            $check->execute([$busId]);
            $current = $check->fetch();

            if ($current && $current['driver_id'] !== null) {
                $pdo->rollBack();
                header("Location: drivers.php?error=already_assigned");
                exit;
            }

            // Assign the bus
            $pdo->prepare("UPDATE buses SET driver_id = ? WHERE id = ?")->execute([$driverId, $busId]);
        }

        $pdo->commit();
        
        // Redirect back with success message
        header("Location: drivers.php?success=assignment_updated");
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Database Error: " . $e->getMessage());
    }
} else {
    header("Location: drivers.php");
    exit;
}
