<?php
/**
 * driver/api_update_profile.php
 * AJAX endpoint for updating driver profile details.
 */
header('Content-Type: application/json');
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['driver_id']) || $_SESSION['role'] !== 'driver') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$driverId = $_SESSION['driver_id'];

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$fullName      = trim($data['full_name'] ?? '');
$contactNumber = trim($data['contact_number'] ?? '');
$email         = trim($data['email'] ?? '');
$address       = trim($data['address'] ?? '');
$region        = trim($data['region'] ?? '');
$province      = trim($data['province'] ?? '');
$city          = trim($data['city'] ?? '');
$barangay      = trim($data['barangay'] ?? '');

// Validation
if (empty($fullName)) {
    echo json_encode(['success' => false, 'message' => 'Full name is required.']);
    exit;
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

if (strlen($contactNumber) > 0 && strlen($contactNumber) < 10) {
    echo json_encode(['success' => false, 'message' => 'Contact number must be at least 10-11 digits.']);
    exit;
}

try {
    // Check if email is already used by another driver
    if (!empty($email)) {
        $chk = $pdo->prepare("SELECT id FROM drivers WHERE email = ? AND id != ?");
        $chk->execute([$email, $driverId]);
        if ($chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email address is already in use by another account.']);
            exit;
        }
    }

    // Update
    $stmt = $pdo->prepare("
        UPDATE drivers 
        SET full_name = ?, contact_number = ?, email = ?, 
            address = ?, region = ?, province = ?, city = ?, barangay = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $fullName, $contactNumber, ($email ?: null), 
        $address, $region, $province, $city, $barangay,
        $driverId
    ]);

    // Update session name if changed
    $_SESSION['full_name'] = $fullName;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
