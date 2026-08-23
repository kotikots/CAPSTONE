<?php
/**
 * auth/forgot_password.php
 * Password reset — single-page OTP-only flow.
 *
 * Step 1: User submits email/ID → OTP sent to their email (no link).
 * Step 2: User enters the OTP on this same page/device.
 * Step 3: User sets a new password on this same page.
 *
 * Security hardening:
 *  - Rate limited: 3 requests per IP + 3 per identifier per 15-minute window
 *  - 6-digit OTP sent via email only — never displayed on screen
 *  - OTP expires in 15 minutes
 *  - Max 5 wrong OTP attempts before OTP is invalidated
 *  - Session-based flow — no URL tokens required
 *  - All events logged to security_logs
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions_security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// ── Ensure required DB columns exist ─────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp         VARCHAR(6)  DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp_expiry  DATETIME    DEFAULT NULL");
} catch (Exception $e) { /* columns already exist */ }

// ── Read current session step ─────────────────────────────────────────────────
// reset_step: null | 'otp' | 'password'
$step        = $_SESSION['reset_step']        ?? null;
$resetUserId = $_SESSION['reset_user_id']     ?? null;
$resetEmail  = $_SESSION['reset_user_email']  ?? '';
$resetName   = $_SESSION['reset_user_name']   ?? '';

$message     = '';
$messageType = ''; // 'success' | 'error' | 'rate_limit' | 'lockout'

// ─────────────────────────────────────────────────────────────────────────────
// POST handler
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'request_otp';
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // ══════════════════════════════════════════════════════════════════════════
    // ACTION: request_otp  (Step 1 → sends OTP to email)
    // ══════════════════════════════════════════════════════════════════════════
    if ($action === 'request_otp') {
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $message     = 'Please enter your email or ID number.';
            $messageType = 'error';

        } elseif (checkResetRateLimit($pdo, $ip, $identifier)) {
            $message     = 'Too many reset requests. Please wait 15 minutes before trying again.';
            $messageType = 'rate_limit';
            logSecurityEvent($pdo, $identifier, 'user', 'BLOCKED', 'Password reset rate limit exceeded');

        } else {
            logResetAttempt($pdo, $ip, $identifier);

            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE (email = ? OR id_number = ?) AND is_active = 1 LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user) {
                $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $pdo->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?")
                    ->execute([$otp, $expiry, $user['id']]);

                // Mask email for display: k***@gmail.com
                $emailParts  = explode('@', $user['email']);
                $maskedEmail = substr($emailParts[0], 0, 1) . '***@' . ($emailParts[1] ?? '');

                // Send OTP-only email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'khianvivar@gmail.com';
                    $mail->Password   = 'zqip kriq dnir obzp';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom($mail->Username, 'PARE System');
                    $mail->addAddress($user['email'], $user['full_name']);

                    $mail->isHTML(false);
                    $mail->Subject = 'Your PARE Password Reset Code';
                    $mail->Body    =
                        "Hi {$user['full_name']},\n\n" .
                        "We received a request to reset your PARE account password.\n\n" .
                        "Your 6-digit verification code is:\n\n" .
                        "  ┌─────────────┐\n" .
                        "  │   {$otp}   │\n" .
                        "  └─────────────┘\n\n" .
                        "This code expires in 15 minutes.\n\n" .
                        "Enter this code on the page where you clicked 'Forgot Password'.\n\n" .
                        "Do NOT share this code with anyone.\n\n" .
                        "If you did NOT request a password reset, you can safely ignore this email.\n\n" .
                        "Regards,\nPARE Team";

                    $mail->send();

                    // Store state in session
                    $_SESSION['reset_step']        = 'otp';
                    $_SESSION['reset_user_id']     = $user['id'];
                    $_SESSION['reset_user_email']  = $user['email'];
                    $_SESSION['reset_user_name']   = $user['full_name'];
                    $_SESSION['reset_masked_email'] = $maskedEmail;
                    $_SESSION['reset_otp_attempts'] = 0;

                    // Refresh local vars
                    $step        = 'otp';
                    $resetUserId = $user['id'];
                    $resetEmail  = $user['email'];
                    $resetName   = $user['full_name'];

                    logSecurityEvent($pdo, $identifier, 'user', 'SUCCESS', 'Password reset OTP sent');

                } catch (MailException $e) {
                    error_log("PHPMailer Error (reset OTP): {$mail->ErrorInfo}");
                    $message     = 'We could not send the reset email. Please try again later.';
                    $messageType = 'error';
                    logSecurityEvent($pdo, $identifier, 'user', 'FAILED', 'Password reset OTP email delivery failure');
                    // Clear the OTP so orphaned record doesn't sit in DB
                    $pdo->prepare("UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?")
                        ->execute([$user['id']]);
                }

            } else {
                // Generic message — prevent account enumeration
                // Fake the OTP step so timing matches
                $_SESSION['reset_step']        = 'otp';
                $_SESSION['reset_user_id']     = null; // null = ghost session
                $_SESSION['reset_masked_email'] = substr($identifier, 0, 1) . '***';
                $_SESSION['reset_otp_attempts'] = 0;

                $step = 'otp';
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ACTION: verify_otp  (Step 2 → check OTP)
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($action === 'verify_otp' && $step === 'otp') {
        $enteredOtp = trim($_POST['otp'] ?? '');

        // Ghost session (user not found) — always fail gracefully
        if (empty($resetUserId)) {
            $_SESSION['reset_otp_attempts'] = ($_SESSION['reset_otp_attempts'] ?? 0) + 1;
            $attemptsLeft = 5 - $_SESSION['reset_otp_attempts'];

            if ($attemptsLeft <= 0) {
                // Destroy ghost session
                unset($_SESSION['reset_step'], $_SESSION['reset_user_id'],
                      $_SESSION['reset_user_email'], $_SESSION['reset_user_name'],
                      $_SESSION['reset_masked_email'], $_SESSION['reset_otp_attempts']);
                $step        = null;
                $message     = 'Too many incorrect codes. Please request a new reset code.';
                $messageType = 'lockout';
            } else {
                $message     = 'Incorrect code. You have ' . $attemptsLeft . ' attempt' . ($attemptsLeft === 1 ? '' : 's') . ' remaining.';
                $messageType = 'error';
            }

        } else {
            // Fetch fresh OTP from DB
            $otpStmt = $pdo->prepare("SELECT reset_otp FROM users WHERE id = ? AND reset_otp_expiry > NOW() LIMIT 1");
            $otpStmt->execute([$resetUserId]);
            $storedOtp = $otpStmt->fetchColumn();

            if ($storedOtp === false) {
                // OTP expired
                unset($_SESSION['reset_step'], $_SESSION['reset_user_id'],
                      $_SESSION['reset_user_email'], $_SESSION['reset_user_name'],
                      $_SESSION['reset_masked_email'], $_SESSION['reset_otp_attempts']);
                $step        = null;
                $message     = 'Your verification code has expired. Please request a new one.';
                $messageType = 'lockout';
                logSecurityEvent($pdo, $resetEmail, 'user', 'FAILED', 'Password reset OTP expired');

            } elseif ($enteredOtp === $storedOtp) {
                // ✅ Correct OTP
                $_SESSION['reset_step'] = 'password';
                $step = 'password';
                logSecurityEvent($pdo, $resetEmail, 'user', 'SUCCESS', 'Password reset OTP verified');

            } else {
                // ❌ Wrong OTP
                $_SESSION['reset_otp_attempts'] = ($_SESSION['reset_otp_attempts'] ?? 0) + 1;
                $attemptsLeft = 5 - $_SESSION['reset_otp_attempts'];
                logSecurityEvent($pdo, $resetEmail, 'user', 'FAILED', 'Password reset OTP incorrect (attempt ' . $_SESSION['reset_otp_attempts'] . ')');

                if ($attemptsLeft <= 0) {
                    // Invalidate OTP in DB
                    $pdo->prepare("UPDATE users SET reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?")
                        ->execute([$resetUserId]);
                    // Clear session
                    unset($_SESSION['reset_step'], $_SESSION['reset_user_id'],
                          $_SESSION['reset_user_email'], $_SESSION['reset_user_name'],
                          $_SESSION['reset_masked_email'], $_SESSION['reset_otp_attempts']);
                    $step        = null;
                    $message     = 'Too many incorrect codes. Your reset code has been invalidated. Please request a new one.';
                    $messageType = 'lockout';
                    logSecurityEvent($pdo, $resetEmail, 'user', 'BLOCKED', 'Password reset OTP invalidated after too many wrong attempts');
                } else {
                    $message     = 'Incorrect code. You have ' . $attemptsLeft . ' attempt' . ($attemptsLeft === 1 ? '' : 's') . ' remaining.';
                    $messageType = 'error';
                }
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ACTION: set_password  (Step 3 → save new password)
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($action === 'set_password' && $step === 'password' && $resetUserId) {
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $strengthOk = strlen($password) >= 8
                   && preg_match('/[A-Z]/', $password)
                   && preg_match('/[a-z]/', $password)
                   && preg_match('/[0-9]/', $password)
                   && preg_match('/[^A-Za-z0-9]/', $password);

        if (!$strengthOk) {
            $message     = 'Password does not meet the security requirements.';
            $messageType = 'error';
        } elseif ($password !== $confirm) {
            $message     = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?")
                ->execute([$hash, $resetUserId]);

            logSecurityEvent($pdo, $resetEmail, 'user', 'SUCCESS', 'Password successfully reset');

            // Send confirmation email (non-blocking)
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'khianvivar@gmail.com';
                $mail->Password   = 'zqip kriq dnir obzp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom($mail->Username, 'PARE System');
                $mail->addAddress($resetEmail, $resetName);
                $mail->isHTML(false);
                $mail->Subject = 'Your PARE Password Was Changed';
                $mail->Body    =
                    "Hi {$resetName},\n\n" .
                    "Your PARE account password was successfully reset.\n\n" .
                    "Time: " . date('F j, Y \a\t g:i A') . "\n" .
                    "IP Address: {$ip}\n\n" .
                    "If you did NOT make this change, please contact support immediately.\n\n" .
                    "Regards,\nPARE Team";
                $mail->send();
            } catch (MailException $e) {
                error_log("Reset confirmation email failed: {$mail->ErrorInfo}");
            }

            // Clear all reset session state
            unset($_SESSION['reset_step'], $_SESSION['reset_user_id'],
                  $_SESSION['reset_user_email'], $_SESSION['reset_user_name'],
                  $_SESSION['reset_masked_email'], $_SESSION['reset_otp_attempts']);

            $step        = 'done';
            $message     = 'Your password has been reset successfully! You can now log in.';
            $messageType = 'success';
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ACTION: restart  (user wants to go back to Step 1)
    // ══════════════════════════════════════════════════════════════════════════
    elseif ($action === 'restart') {
        unset($_SESSION['reset_step'], $_SESSION['reset_user_id'],
              $_SESSION['reset_user_email'], $_SESSION['reset_user_name'],
              $_SESSION['reset_masked_email'], $_SESSION['reset_otp_attempts']);
        $step = null;
    }
}

// Refresh masked email after possible session update
$maskedEmail = $_SESSION['reset_masked_email'] ?? '';

$pageTitle = 'Forgot Password';
include '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center p-6">
    <!-- Background blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">

        <!-- Back link (only on Step 1) -->
        <?php if (!$step || $step === null): ?>
        <a href="login.php" class="inline-flex items-center gap-2 text-[#061A53]/70 hover:text-[#061A53] text-sm font-medium mb-6 transition">
            <i class="ph ph-arrow-left"></i> Back to Login
        </a>
        <?php endif; ?>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <!-- ── Step indicator bar ─────────────────────────────────────── -->
            <?php
            $stepNum = match($step) {
                'otp'      => 2,
                'password' => 3,
                'done'     => 3,
                default    => 1,
            };
            ?>
            <?php if ($step !== 'done' && $messageType !== 'lockout'): ?>
            <div class="px-8 pt-6 pb-0">
                <div class="flex items-center gap-2 mb-1">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="h-1.5 flex-1 rounded-full transition-all duration-500 <?= $i <= $stepNum ? 'bg-blue-600' : 'bg-slate-200' ?>"></div>
                    <?php endfor; ?>
                </div>
                <p class="text-xs text-slate-400 font-medium">
                    Step <?= $stepNum ?> of 3 —
                    <?php if ($stepNum === 1): ?>Enter your email or ID
                    <?php elseif ($stepNum === 2): ?>Verify your identity
                    <?php else: ?>Set new password
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="p-8">

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- ✅  SUCCESS STATE                                           -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <?php if ($step === 'done' && $messageType === 'success'): ?>
            <div class="text-center py-4">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-check-circle text-5xl text-green-500"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800 mb-2">Password Reset!</h2>
                <p class="text-slate-500 text-sm mb-1"><?= htmlspecialchars($message) ?></p>
                <p class="text-slate-400 text-xs mb-6">A confirmation has been sent to your email address.</p>
                <a href="login.php"
                   class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Go to Login →
                </a>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- 🔒  LOCKOUT STATE                                           -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <?php elseif ($messageType === 'lockout'): ?>
            <div class="text-center py-4">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-lock text-5xl text-red-500"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800 mb-2">Code Invalidated</h2>
                <p class="text-slate-500 text-sm mb-6"><?= htmlspecialchars($message) ?></p>
                <form method="POST">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit"
                            class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                        Request New Code
                    </button>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- STEP 1 — Email / ID form                                    -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <?php elseif (!$step || $step === null): ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-key text-3xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Forgot Password?</h2>
                <p class="text-slate-500 text-sm mt-1">Enter your email or ID to receive a 6-digit reset code</p>
            </div>

            <?php if ($message): ?>
            <?php if ($messageType === 'rate_limit'): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5">
                <div class="flex items-start gap-3">
                    <i class="ph ph-clock text-amber-500 text-2xl mt-0.5 flex-shrink-0"></i>
                    <div>
                        <p class="text-amber-800 text-sm font-bold mb-1">Too Many Requests</p>
                        <p class="text-amber-700 text-sm"><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-center gap-2">
                <i class="ph ph-warning-circle text-red-500 text-lg flex-shrink-0"></i>
                <span class="text-red-700 text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="request_otp">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email or ID Number</label>
                    <div class="relative">
                        <i class="ph ph-identification-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl"></i>
                        <input type="text" name="identifier" id="identifier" required
                               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                               placeholder="your@email.com or QR-1234567"
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white transition">
                    </div>
                </div>

                <button type="submit" id="send-btn"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all flex items-center justify-center gap-2">
                    <i class="ph ph-paper-plane-tilt"></i> Send Reset Code
                </button>
            </form>

            <p class="text-center text-slate-400 text-sm mt-6">
                Remember your password?
                <a href="login.php" class="text-blue-600 font-semibold hover:text-blue-800">Sign in</a>
            </p>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- STEP 2 — OTP verification                                   -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <?php elseif ($step === 'otp'): ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-shield-check text-3xl text-indigo-600"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Check Your Email</h2>
                <p class="text-slate-500 text-sm mt-1">
                    We sent a 6-digit code to
                    <span class="font-semibold text-slate-700"><?= htmlspecialchars($maskedEmail) ?></span>
                </p>
            </div>

            <?php if ($message && $messageType === 'error'): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-center gap-2">
                <i class="ph ph-warning-circle text-red-500 text-lg flex-shrink-0"></i>
                <span class="text-red-700 text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="otp-form" class="space-y-5" autocomplete="off">
                <input type="hidden" name="action" value="verify_otp">

                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-3 text-center">Enter Your 6-Digit Code</label>
                    <div class="flex gap-2 justify-center" id="otp-digits">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
                               id="otp-digit-<?= $i ?>"
                               class="otp-digit w-12 h-14 text-center text-2xl font-black text-slate-800 bg-slate-50 border-2 border-slate-300 rounded-xl focus:outline-none focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-400/30 transition-all"
                               aria-label="Digit <?= $i + 1 ?>">
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="otp" id="otp-hidden">
                </div>

                <button type="submit" id="otp-submit" disabled
                        class="w-full bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-indigo-500/30 transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <i class="ph ph-check-circle"></i> Verify Code
                </button>
            </form>

            <div class="mt-4 flex items-center justify-between text-sm">
                <form method="POST">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" class="text-slate-400 hover:text-slate-600 font-medium transition flex items-center gap-1">
                        <i class="ph ph-arrow-left text-sm"></i> Use different email
                    </button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="restart">
                    <button type="submit" class="text-blue-600 hover:text-blue-800 font-semibold transition">
                        Resend code
                    </button>
                </form>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!-- STEP 3 — Set new password                                   -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <?php elseif ($step === 'password'): ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-lock-simple-open text-3xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Set New Password</h2>
                <p class="text-slate-500 text-sm mt-1">Identity verified ✓ — enter your new password below</p>
            </div>

            <?php if ($message && $messageType === 'error'): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-center gap-2">
                <i class="ph ph-warning-circle text-red-500 text-lg flex-shrink-0"></i>
                <span class="text-red-700 text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="reset-form" class="space-y-4">
                <input type="hidden" name="action" value="set_password">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 items-start">
                    <!-- Password Inputs -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required minlength="8"
                                       placeholder="Min. 8 characters"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm transition">
                                <button type="button" onclick="togglePw('password','eye-pw')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1">
                                    <i id="eye-pw" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                       placeholder="Repeat new password"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm transition">
                                <button type="button" onclick="togglePw('confirm_password','eye-confirm')"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1">
                                    <i id="eye-confirm" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Strength Checklist -->
                    <div class="md:pt-[26px]">
                        <div id="pw-req-box" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 transition-all duration-300">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Password Requirements</p>
                            <div class="grid grid-cols-1 gap-y-1">
                                <div id="req-length"  class="flex items-center gap-2 text-slate-400 transition-colors duration-300"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">At least 8 characters</span></div>
                                <div id="req-upper"   class="flex items-center gap-2 text-slate-400 transition-colors duration-300"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Uppercase letter</span></div>
                                <div id="req-lower"   class="flex items-center gap-2 text-slate-400 transition-colors duration-300"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Lowercase letter</span></div>
                                <div id="req-number"  class="flex items-center gap-2 text-slate-400 transition-colors duration-300"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Number</span></div>
                                <div id="req-special" class="flex items-center gap-2 text-slate-400 transition-colors duration-300"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Special character</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Reset Password
                </button>
            </form>

            <?php endif; ?>

            </div><!-- /p-8 -->
        </div><!-- /card -->
    </div>
</div>

<script>
// ── OTP digit-box logic ───────────────────────────────────────────────────────
const digits    = document.querySelectorAll('.otp-digit');
const hiddenOtp = document.getElementById('otp-hidden');
const submitBtn = document.getElementById('otp-submit');

function assembleOtp() {
    let val = '';
    digits.forEach(d => val += d.value);
    if (hiddenOtp) hiddenOtp.value = val;
    if (submitBtn) submitBtn.disabled = (val.length < 6);
}

digits.forEach((digit, idx) => {
    digit.addEventListener('input', () => {
        digit.value = digit.value.replace(/\D/g, '').slice(-1);
        if (digit.value && idx < digits.length - 1) digits[idx + 1].focus();
        assembleOtp();
    });

    digit.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !digit.value && idx > 0) digits[idx - 1].focus();
    });

    digit.addEventListener('paste', e => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...pasted].slice(0, 6).forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
        assembleOtp();
        digits[Math.min(pasted.length, 5)].focus();
    });
});

if (digits.length) digits[0].focus();

// ── Password visibility toggle ────────────────────────────────────────────────
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('ph-eye-slash', 'ph-eye');
    } else {
        input.type = 'password';
        icon.classList.replace('ph-eye', 'ph-eye-slash');
    }
}

// ── Password strength real-time checklist ─────────────────────────────────────
const passwordInput = document.getElementById('password');
if (passwordInput) {
    const reqs = {
        length:  { regex: /.{8,}/,        el: document.getElementById('req-length')  },
        upper:   { regex: /[A-Z]/,         el: document.getElementById('req-upper')   },
        lower:   { regex: /[a-z]/,         el: document.getElementById('req-lower')   },
        number:  { regex: /[0-9]/,         el: document.getElementById('req-number')  },
        special: { regex: /[^A-Za-z0-9]/,  el: document.getElementById('req-special') },
    };

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        let allMet = true;
        Object.values(reqs).forEach(req => {
            const met  = req.regex.test(val);
            if (!met) allMet = false;
            const icon = req.el.querySelector('.icon');
            
            if (met) {
                req.el.classList.add('hidden');
            } else {
                req.el.classList.remove('hidden');
                req.el.classList.remove('text-emerald-500');
                req.el.classList.add('text-slate-400');
                icon.className = 'icon ph text-[10px] ph-circle';
            }
        });
        
        const box = document.getElementById('pw-req-box');
        if (box) {
            if (allMet) {
                box.classList.add('hidden');
            } else {
                box.classList.remove('hidden');
            }
        }
    });

    document.getElementById('reset-form')?.addEventListener('submit', function(e) {
        const pw = passwordInput.value;
        const ok = /.{8,}/.test(pw) && /[A-Z]/.test(pw) && /[a-z]/.test(pw)
                && /[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw);
        if (!ok) { e.preventDefault(); alert('Your password does not meet all security requirements.'); return; }
        if (pw !== document.getElementById('confirm_password').value) {
            e.preventDefault(); alert('Passwords do not match.');
        }
    });
}
</script>
</body></html>
