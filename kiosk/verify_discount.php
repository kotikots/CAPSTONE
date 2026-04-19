<?php
/**
 * kiosk/verify_discount.php
 * API endpoint to verify a passenger's ID number for discount eligibility.
 * 
 * Returns:
 *   - verified: true/false (true = registered with a discount type)
 *   - found: true/false (true = ID exists in the system at all)
 *   - name, discount_type, passenger_id (if found)
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$idNumber = trim($_GET['id_number'] ?? '');

if (empty($idNumber)) {
    echo json_encode(['found' => false, 'verified' => false, 'message' => 'No ID number provided']);
    exit;
}

// Look up the ID number in the users table - using UPPER() for case-insensitive matching
$stmt = $pdo->prepare("SELECT id, full_name, discount_type, role FROM users WHERE UPPER(id_number) = UPPER(?) AND is_active = 1 LIMIT 1");
$stmt->execute([$idNumber]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // ID not found in the system
    echo json_encode([
        'found'    => false,
        'verified' => false,
        'message'  => 'ID not registered in the system.'
    ]);
    exit;
}

// ID found — check if they have a registered discount
$hasDiscount = ($user['discount_type'] !== 'none' && !empty($user['discount_type']));

echo json_encode([
    'found'         => true,
    'verified'      => $hasDiscount,
    'passenger_id'  => (int)$user['id'],
    'name'          => $user['full_name'],
    'discount_type' => $user['discount_type'],
    'message'       => $hasDiscount 
        ? 'Discount verified: ' . ucfirst($user['discount_type'])
        : 'Registered but no discount type on file.'
]);
