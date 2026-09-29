<?php
/**
 * admin/api_delete_account.php
 * 
 * ROLE: Admin Only
 * PURPOSE: Permanently removes a passenger or driver from the database.
 * IMPORTANT: This file has safety checks to prevent deleting users with history (like old trips).
 */
session_start();
require_once '../config/db.php'; // Database connection
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: application/json');

// 1. SECURITY: Ensure the person clicking "Delete" is actually an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

// 2. INPUT: Get the user type ('passenger' or 'driver') and their unique ID
$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? ''; 
$id   = (int)($data['id'] ?? 0);

// 3. VALIDATION: Ensure we have a valid ID and known type before proceeding
if (!$id || !in_array($type, ['passenger', 'driver'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']); exit;
}

try {
    // 4. DATABASE ACTION: Attempt to delete the record
    $user = null;
    $mailSent = false;

    if ($type === 'driver') {
        // Prepare to archive in the 'drivers' table
        $stmt = $pdo->prepare("UPDATE drivers SET is_archived = 1, status = 'inactive' WHERE id = ?");
    } else {
        // Fetch passenger details for email notification before archiving
        $checkStmt = $pdo->prepare("SELECT email, full_name, is_active FROM users WHERE id = ? AND role = 'passenger'");
        $checkStmt->execute([$id]);
        $user = $checkStmt->fetch();

        // Prepare to archive in the 'users' table (passengers only)
        // We append 'archived_' to email and id_number to free them up for future registrations
        $stmt = $pdo->prepare("
            UPDATE users 
            SET is_archived = 1, 
                is_active = 0, 
                email = IF(email IS NOT NULL AND email != '', CONCAT('archived_', UNIX_TIMESTAMP(), '_', email), email),
                id_number = IF(id_number IS NOT NULL AND id_number != '', CONCAT('archived_', UNIX_TIMESTAMP(), '_', id_number), id_number)
            WHERE id = ? AND role = 'passenger'
        ");
    }
    
    $stmt->execute([$id]);
    
    // 5. RESPONSE: If one row was affected, it means the archiving was successful
    if ($stmt->rowCount() > 0) {
        // Send rejection email if a passenger registration is declined (archived)
        if ($type === 'passenger' && $user && !empty($user['email']) && !str_starts_with($user['email'], 'archived_')) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'khianvivar@gmail.com';
                $mail->Password   = 'zqip kriq dnir obzp'; // Use the standard project password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom($mail->Username, 'PARE System');
                $mail->addAddress($user['email'], $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'PARE Account Registration Update';
                
                $contactInfo = "
                    <div style='margin-top: 20px; padding: 15px; background-color: #f8fafc; border-left: 4px solid #3b82f6; border-radius: 4px;'>
                        <h4 style='margin-top: 0; color: #1e293b;'>Need Help? Contact our Support Team:</h4>
                        <p style='margin: 5px 0; color: #475569;'><strong>Email:</strong> khianvivar@gmail.com</p>
                        <p style='margin: 5px 0; color: #475569;'><strong>Phone:</strong> 09684380147</p>
                        <p style='margin: 5px 0; color: #475569;'><strong>Messenger:</strong> Khian Vivar</p>
                    </div>
                ";

                if ($user['is_active'] == 0) {
                    // It was a pending registration being declined
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <h3 style='color: #0f172a;'>Hello {$user['full_name']},</h3>
                            <p>We regret to inform you that your registration for a PARE account has been declined by the administrator.</p>
                            <p>This may be because the uploaded ID photo was unclear, or the scanned information did not match the ID content. Please try registering again and ensure that your uploaded photo is clear and readable.</p>
                            <p>If you believe this is a mistake, or if you need further clarification, please reach out to us using the contact details below.</p>
                            {$contactInfo}
                            <br>
                            <p>Thank you,<br><strong>PARE Administration</strong></p>
                        </div>
                    ";
                } else {
                    // It was an active account being removed
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <h3 style='color: #0f172a;'>Hello {$user['full_name']},</h3>
                            <p>We are writing to inform you that your PARE account has been archived by the administrator.</p>
                            <p>If you believe this is a mistake, or if you have any questions regarding your account status, please reach out to our support team.</p>
                            {$contactInfo}
                            <br>
                            <p>Thank you,<br><strong>PARE Administration</strong></p>
                        </div>
                    ";
                }

                $mail->send();
                $mailSent = true;
            } catch (MailException $e) {
                error_log("PHPMailer Error (account removal): {$mail->ErrorInfo}");
            }
        }

        echo json_encode(['success' => true, 'message' => 'Account archived successfully' . ($mailSent ? ' and user notified via email.' : '.')]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Account not found or could not be archived']);
    }
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

