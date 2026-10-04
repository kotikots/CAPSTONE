<?php
/**
 * auth/api_send_reg_otp.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Sends a 6-digit email OTP for registration email verification.
 * OTP is stored entirely in $_SESSION (no DB write — user doesn't exist yet).
 * Rate limited: max 3 sends per 10 minutes per session.
 * ─────────────────────────────────────────────────────────────────────────────
 */
header('Content-Type: application/json');
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body  = json_decode(file_get_contents('php://input'), true);
$email = trim($body['email'] ?? '');

// ── Validate email ────────────────────────────────────────────────────────────
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}
if (!str_ends_with(strtolower($email), '@gmail.com')) {
    echo json_encode(['success' => false, 'error' => 'Email must end with @gmail.com.']);
    exit;
}

// ── Check if email is already registered ─────────────────────────────────────
$chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$chk->execute([$email]);
if ($chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'This email is already registered. Please use a different email or log in.']);
    exit;
}

// ── Rate limit: max 3 sends per 10 min per session ───────────────────────────
$now       = time();
$window    = 600; // 10 minutes
$maxSends  = 3;

$sendLog   = $_SESSION['reg_otp_send_log'] ?? [];
// Remove entries older than window
$sendLog   = array_filter($sendLog, fn($t) => ($now - $t) < $window);

if (count($sendLog) >= $maxSends) {
    $waitSec = $window - ($now - min($sendLog));
    $waitMin = ceil($waitSec / 60);
    echo json_encode(['success' => false, 'error' => "Too many OTP requests. Please wait {$waitMin} minute(s) before trying again."]);
    exit;
}

// ── Generate OTP ──────────────────────────────────────────────────────────────
$otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = $now + 600; // 10 minutes

// ── Send email ────────────────────────────────────────────────────────────────
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'khianvivar@gmail.com';
    $mail->Password   = 'zqip kriq dnir obzp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->Timeout    = 3;

    $mail->setFrom($mail->Username, 'PARE System');
    $mail->addAddress($email);

    $mail->isHTML(false);
    $mail->Subject = 'Your PARE Registration Verification Code';
    $mail->Body    =
        "Hello,\n\n" .
        "You are registering a new PARE passenger account.\n\n" .
        "Your 6-digit email verification code is:\n\n" .
        "  ┌─────────────┐\n" .
        "  │   {$otp}   │\n" .
        "  └─────────────┘\n\n" .
        "This code expires in 10 minutes.\n\n" .
        "Enter this code on the registration page to verify your email address.\n\n" .
        "Do NOT share this code with anyone.\n\n" .
        "If you did NOT initiate this registration, please ignore this email.\n\n" .
        "Regards,\nPARE Team";

    $mail->send();

    // ── Store OTP in session ──────────────────────────────────────────────────
    $sendLog[] = $now;
    $_SESSION['reg_otp_send_log']    = array_values($sendLog);
    $_SESSION['reg_otp_code']        = password_hash($otp, PASSWORD_BCRYPT); // never store raw
    $_SESSION['reg_otp_expiry']      = $expiry;
    $_SESSION['reg_otp_email']       = $email;
    $_SESSION['reg_otp_attempts']    = 0;
    $_SESSION['reg_otp_verified']    = false;

    // Mask email for display: k***@gmail.com
    $parts       = explode('@', $email);
    $maskedEmail = substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');

    echo json_encode([
        'success'      => true,
        'masked_email' => $maskedEmail,
        'expires_in'   => 600,
    ]);

} catch (MailException $e) {
    error_log("PHPMailer Error (reg OTP): {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'error' => 'Could not send the verification email. Please try again.']);
}
