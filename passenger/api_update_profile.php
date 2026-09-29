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
// Fetch current data to retain old values if submitted data is empty
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$current = $stmt->fetch();

// Sanitize & Validate
$full_name = !empty(trim($data['full_name'] ?? '')) ? trim($data['full_name']) : $current['full_name'];
$address   = !empty(trim($data['address'] ?? '')) ? trim($data['address']) : $current['address'];
$region    = !empty(trim($data['region'] ?? '')) ? trim($data['region']) : $current['region'];
$province  = !empty(trim($data['province'] ?? '')) ? trim($data['province']) : $current['province'];
$city      = !empty(trim($data['city'] ?? '')) ? trim($data['city']) : $current['city'];
$barangay  = !empty(trim($data['barangay'] ?? '')) ? trim($data['barangay']) : $current['barangay'];

$ec_name    = !empty(trim($data['emergency_contact_name'] ?? '')) ? trim($data['emergency_contact_name']) : $current['emergency_contact_name'];
$ec_contact = !empty(trim($data['emergency_contact_number'] ?? '')) ? trim($data['emergency_contact_number']) : $current['emergency_contact_number'];
$ec_addr    = !empty(trim($data['emergency_contact_address'] ?? '')) ? trim($data['emergency_contact_address']) : $current['emergency_contact_address'];
$ec_region  = !empty(trim($data['ec_region'] ?? '')) ? trim($data['ec_region']) : $current['ec_region'];
$ec_prov    = !empty(trim($data['ec_province'] ?? '')) ? trim($data['ec_province']) : $current['ec_province'];
$ec_city    = !empty(trim($data['ec_city'] ?? '')) ? trim($data['ec_city']) : $current['ec_city'];
$ec_brgy    = !empty(trim($data['ec_barangay'] ?? '')) ? trim($data['ec_barangay']) : $current['ec_barangay'];

$contactRaw = trim($data['contact_number'] ?? '');
$contact    = !empty($contactRaw) ? $contactRaw : $current['contact_number'];
$email      = isset($data['email']) && trim($data['email']) !== '' ? trim($data['email']) : $current['email'];

if (empty($full_name)) {
    echo json_encode(['success' => false, 'message' => 'Full Name is required']); exit;
}

// Contact Validation (Exactly 11 digits)
if (!preg_match('/^[0-9]{11}$/', $contactRaw)) {
    echo json_encode(['success' => false, 'message' => 'Contact number must be exactly 11 digits (e.g. 09123456789)']); exit;
}

if (!empty($ec_contact) && !preg_match('/^[0-9]{11}$/', $ec_contact)) {
    echo json_encode(['success' => false, 'message' => 'Emergency contact number must be exactly 11 digits']); exit;
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
            emergency_contact_name = ?, emergency_contact_number = ?, emergency_contact_address = ?,
            ec_region = ?, ec_province = ?, ec_city = ?, ec_barangay = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $full_name, $email, $contact, $address, 
        $region, $province, $city, $barangay,
        $ec_name, $ec_contact, $ec_addr,
        $ec_region, $ec_prov, $ec_city, $ec_brgy,
        $uid
    ]);

    // Update Session name for the sidebar
    $_SESSION['full_name'] = $full_name;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
