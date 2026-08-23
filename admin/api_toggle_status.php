<?php
/**
 * admin/api_toggle_status.php
 * 
 * ROLE: Admin Only
 * PURPOSE: A unified worker to enable or disable (activate/deactivate) passengers and drivers.
 */
session_start();
require_once '../config/db.php'; // Database connection
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// Set response type to JSON for AJAX requests
header('Content-Type: application/json');

// 1. SECURITY: Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

// 2. INPUT: Get the data from the frontend (the target user type, their ID, and the new state)
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; // Can be 'passenger' or 'driver'
$id   = (int)($data['id'] ?? 0);
$state= (int)($data['state'] ?? 0); // 1 for Active, 0 for Inactive

// 3. VALIDATION: Check if we have everything we need
if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters provided']); exit;
}

try {
    $mailSent = false;

    // 4. DATABASE ACTION: Decide which table to update based on the 'type'
    if ($type === 'passenger') {
        // Check current status before updating
        $checkStmt = $pdo->prepare("SELECT email, full_name, is_active FROM users WHERE id = ?");
        $checkStmt->execute([$id]);
        $user = $checkStmt->fetch();

        // Toggle status for a passenger in the 'users' table
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'passenger'");
        $stmt->execute([$state, $id]);

        // If activating a previously inactive account that has an email
        if ($user && $user['is_active'] == 0 && $state == 1 && !empty($user['email'])) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'khianvivar@gmail.com';
                $mail->Password   = 'zqip kriq dnir obzp'; // Same password as in forgot_password.php
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom($mail->Username, 'PARE System');
                $mail->addAddress($user['email'], $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Your PARE Account is Verified!';
                $mail->Body    = "
                    <h3>Hello {$user['full_name']},</h3>
                    <p>Great news! Your PARE account and discount application have been successfully verified by the admin.</p>
                    <p>You can now log in and book your rides using your discount.</p>
                    <br>
                    <p>Thank you for using PARE System!</p>
                ";

                $mail->send();
                $mailSent = true;
            } catch (MailException $e) {
                error_log("PHPMailer Error (account verification API): {$mail->ErrorInfo}");
            }
        }
    } else {
        // Toggle status for a driver in the 'drivers' table
        $stmt = $pdo->prepare("UPDATE drivers SET is_active = ? WHERE id = ?");
        $stmt->execute([$state, $id]);
    }
    
    // 5. RESPONSE: Confirm success to the browser
    if ($stmt->rowCount() > 0 || $mailSent) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
    }
    exit;
} catch (PDOException $e) {
    // Error handling
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
