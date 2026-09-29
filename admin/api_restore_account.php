<?php
/**
 * admin/api_restore_account.php
 * 
 * ROLE: Admin Only
 * PURPOSE: Restores an archived passenger or driver.
 */
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; 
$id   = (int)($data['id'] ?? 0);

if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    if ($type === 'driver') {
        $stmt = $pdo->prepare("UPDATE drivers SET is_archived = 0, status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        $success = $stmt->rowCount() > 0;
    } else {
        // Fetch passenger to clean up email and id_number
        $stmt = $pdo->prepare("SELECT email, id_number FROM users WHERE id = ? AND role = 'passenger'");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $email = $user['email'];
            $id_number = $user['id_number'];
            
            if (preg_match('/^archived_\d+_(.*)$/', $email, $matches)) {
                $email = $matches[1];
            }
            if (preg_match('/^archived_\d+_(.*)$/', $id_number, $matches)) {
                $id_number = $matches[1];
            }
            
            $upd = $pdo->prepare("UPDATE users SET is_archived = 0, is_active = 1, email = ?, id_number = ? WHERE id = ? AND role = 'passenger'");
            $upd->execute([$email, $id_number, $id]);
            $success = $upd->rowCount() > 0;
        } else {
            $success = false;
        }
    }
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Account restored successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found or could not be restored.']);
    }
} catch (PDOException $e) {
    if ($e->getCode() == '23000') {
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot restore account: The email or ID number is already in use by another active account.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
