<?php
/**
 * auth/api_verify_reg_otp.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Verifies the 6-digit registration OTP submitted by the user.
 * Max 5 wrong attempts before the OTP is invalidated.
 * On success: sets $_SESSION['reg_otp_verified'] = true.
 * ─────────────────────────────────────────────────────────────────────────────
 */
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$enteredOtp = trim($body['otp'] ?? '');

// ── Check session state ───────────────────────────────────────────────────────
$storedHash = $_SESSION['reg_otp_code']     ?? null;
$expiry     = $_SESSION['reg_otp_expiry']   ?? 0;
$attempts   = $_SESSION['reg_otp_attempts'] ?? 0;
$maxAttempts = 5;

if (!$storedHash || !$expiry) {
    echo json_encode(['success' => false, 'error' => 'No OTP session found. Please request a new code.']);
    exit;
}

// ── Already verified ──────────────────────────────────────────────────────────
if ($_SESSION['reg_otp_verified'] ?? false) {
    echo json_encode(['success' => true, 'already_verified' => true]);
    exit;
}

// ── Check attempts ────────────────────────────────────────────────────────────
if ($attempts >= $maxAttempts) {
    // Wipe OTP session
    unset($_SESSION['reg_otp_code'], $_SESSION['reg_otp_expiry'],
          $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_verified']);
    echo json_encode(['success' => false, 'error' => 'Too many incorrect attempts. Please request a new verification code.', 'locked' => true]);
    exit;
}

// ── Check expiry ──────────────────────────────────────────────────────────────
if (time() > $expiry) {
    unset($_SESSION['reg_otp_code'], $_SESSION['reg_otp_expiry'],
          $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_verified']);
    echo json_encode(['success' => false, 'error' => 'Verification code has expired. Please request a new one.', 'expired' => true]);
    exit;
}

// ── Validate format ───────────────────────────────────────────────────────────
if (!preg_match('/^[0-9]{6}$/', $enteredOtp)) {
    echo json_encode(['success' => false, 'error' => 'Please enter the 6-digit code exactly as received.']);
    exit;
}

// ── Verify OTP ────────────────────────────────────────────────────────────────
if (password_verify($enteredOtp, $storedHash)) {
    $_SESSION['reg_otp_verified'] = true;
    // Keep reg_otp_email in session so register.php can compare on final submit
    echo json_encode(['success' => true]);
} else {
    $attempts++;
    $_SESSION['reg_otp_attempts'] = $attempts;
    $attemptsLeft = $maxAttempts - $attempts;

    if ($attemptsLeft <= 0) {
        unset($_SESSION['reg_otp_code'], $_SESSION['reg_otp_expiry'],
              $_SESSION['reg_otp_attempts'], $_SESSION['reg_otp_verified']);
        echo json_encode(['success' => false, 'error' => 'Too many incorrect attempts. Please request a new verification code.', 'locked' => true]);
    } else {
        echo json_encode([
            'success'       => false,
            'error'         => "Incorrect code. {$attemptsLeft} attempt" . ($attemptsLeft === 1 ? '' : 's') . " remaining.",
            'attempts_left' => $attemptsLeft,
        ]);
    }
}
