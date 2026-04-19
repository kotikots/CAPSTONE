<?php
/**
 * passenger/api_update_profile.php
 * Handle AJAX updates for passenger personal information.
 */
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'passenger') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$uid = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received']); exit;
}

// Sanitize & Validate
$full_name = trim($data['full_name'] ?? '');
$address   = trim($data['address'] ?? '');
$region    = trim($data['region'] ?? '');
$province  = trim($data['province'] ?? '');
$city      = trim($data['city'] ?? '');
$barangay  = trim($data['barangay'] ?? '');

$ec_name   = trim($data['emergency_contact_name'] ?? '');
$ec_addr   = trim($data['emergency_contact_address'] ?? '');
$ec_region = trim($data['ec_region'] ?? '');
$ec_prov   = trim($data['ec_province'] ?? '');
$ec_city   = trim($data['ec_city'] ?? '');
$ec_brgy   = trim($data['ec_barangay'] ?? '');

$contact   = trim($data['contact_number'] ?? '');
$email     = trim($data['email'] ?? '');

if (empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'Full Name is required']); exit;
}

// Contact Validation (Exactly 11 digits)
if (!preg_match('/^[0-9]{11}$/', $contact)) {
    echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits (e.g. 09123456789)']); exit;
}

// Email Validation (@gmail.com)
if (!empty($email)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']); exit;
    }
    if (!str_ends_with(strtolower($email), '@gmail.com')) {
        echo json_encode(['success' => false, 'message' => 'Email must be a @gmail.com address']); exit;
    }
}

try {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, contact_number = ?, address = ?,
            region = ?, province = ?, city = ?, barangay = ?,
            emergency_contact_name = ?, emergency_contact_address = ?,
            ec_region = ?, ec_province = ?, ec_city = ?, ec_barangay = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $full_name, $email, $contact, $address, 
        $region, $province, $city, $barangay,
        $ec_name, $ec_addr,
        $ec_region, $ec_prov, $ec_city, $ec_brgy,
        $uid
    ]);

    // Update Session name for the sidebar
    $_SESSION['full_name'] = $full_name;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
