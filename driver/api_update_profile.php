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
// Fetch current data to retain old values if submitted data is empty
$stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
$stmt->execute([$driverId]);
$current = $stmt->fetch();

$fullName      = !empty(trim($data['full_name'] ?? '')) ? trim($data['full_name']) : $current['full_name'];
$contactNumber = !empty(trim($data['contact_number'] ?? '')) ? trim($data['contact_number']) : $current['contact_number'];
$email         = isset($data['email']) && trim($data['email']) !== '' ? trim($data['email']) : $current['email'];
$address       = !empty(trim($data['address'] ?? '')) ? trim($data['address']) : $current['address'];
$region        = !empty(trim($data['region'] ?? '')) ? trim($data['region']) : $current['region'];
$province      = !empty(trim($data['province'] ?? '')) ? trim($data['province']) : $current['province'];
$city          = !empty(trim($data['city'] ?? '')) ? trim($data['city']) : $current['city'];
$barangay      = !empty(trim($data['barangay'] ?? '')) ? trim($data['barangay']) : $current['barangay'];

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
