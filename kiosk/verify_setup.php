<?php
/**
 * kiosk/verify_setup.php
 * Validates an admin password to allow the Kiosk to bind to a specific bus.
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$password = $data['password'] ?? '';

if (!$password) {
    echo json_encode(['status' => 'error', 'message' => 'Password is required.']);
    exit;
}

try {
    // Check if the password matches any active admin account
    $stmt = $pdo->prepare("SELECT password FROM users WHERE role = 'admin' AND is_active = 1");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $authorized = false;
    foreach ($admins as $admin) {
        if (password_verify($password, $admin['password'])) {
            $authorized = true;
            break;
        }
    }

    if ($authorized) {
        echo json_encode(['status' => 'success', 'message' => 'Device Authorized']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Admin Password']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
