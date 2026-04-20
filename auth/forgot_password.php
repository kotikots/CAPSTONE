<?php
/**
 * auth/forgot_password.php
 * Password reset request — generates a token and shows reset link.
 * (In production, this would send an email. For local, we show the link directly.)
 */
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$messageType = ''; // 'success' or 'error'
$resetLink = '';

// Ensure reset_token column exists
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $message = 'Please enter your email or ID number.';
        $messageType = 'error';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE (email = ? OR id_number = ?) AND is_active = 1 LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a random token and store its SHA-256 hash for secure DB lookup
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store hash and expiry
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")->execute([$tokenHash, $expiry, $user['id']]);

            // Build a full URL for the reset link (using IP address so it works on mobile/other devices)
            $resetLink = 'http://192.168.11.186/PARE/auth/reset_password.php?token=' . $rawToken;

            // Send the reset link via PHPMailer
            $subject = 'Password Reset Request';
            $body = "Hi {$user['full_name']},\n\nWe received a request to reset your password. Click the link below to set a new password (valid for 1 hour):\n\n{$resetLink}\n\nIf you didn't request this, you can ignore this email.\n\nRegards,\nPARE Team";

            $mail = new PHPMailer(true);
            try {
                // Server settings for Gmail
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'khianvivar@gmail.com';         // [REPLACE] Your Gmail address
                $mail->Password   = 'zqip kriq dnir obzp';     // [REPLACE] Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom($mail->Username, 'PARE System');
                $mail->addAddress($user['email'], $user['full_name']);

                // Content
                $mail->isHTML(false); // Set email format to plain text
                $mail->Subject = $subject;
                $mail->Body    = $body;

                $mail->send();
                $message = 'Reset link sent to your email address.';
            } catch (Exception $e) {
                // Fallback: If email fails, show the link (useful for debugging/local testing)
                error_log("PHPMailer Error: {$mail->ErrorInfo}");
                $message = 'Reset link generated! Use the link below (Email failed to send).';
            }
            $messageType = 'success';
        } else {
            // Don't reveal whether user exists — generic message
            $message = 'If an account with that email/ID exists, a reset link has been generated.';
            $messageType = 'success';
        }
    }
}

$pageTitle = 'Forgot Password';
include '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
        <!-- Back link -->
        <a href="login.php" class="inline-flex items-center gap-2 text-white/60 hover:text-white text-sm font-medium mb-6 transition">
            <i class="ph ph-arrow-left"></i> Back to Login
        </a>

        <div class="bg-white rounded-3xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-key text-3xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Forgot Password</h2>
                <p class="text-slate-500 text-sm mt-1">Enter your email or ID number to reset your password</p>
            </div>

            <?php if ($message): ?>
            <div class="<?= $messageType === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> border rounded-2xl p-4 mb-5">
                <div class="flex items-center gap-2 mb-1">
                    <i class="ph <?= $messageType === 'success' ? 'ph-check-circle text-green-500' : 'ph-warning-circle text-red-500' ?> text-lg"></i>
                    <span class="<?= $messageType === 'success' ? 'text-green-700' : 'text-red-700' ?> text-sm font-medium"><?= htmlspecialchars($message) ?></span>
                </div>
                <?php if ($resetLink): ?>
                <div class="mt-3 bg-white rounded-xl p-3 border border-green-200">
                    <p class="text-xs text-slate-400 mb-1.5">Click below to reset your password:</p>
                    <a href="<?= htmlspecialchars($resetLink) ?>" class="text-blue-600 font-bold text-sm hover:underline break-all"><?= htmlspecialchars($resetLink) ?></a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email or ID Number</label>
                    <div class="relative">
                        <i class="ph ph-identification-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl"></i>
                        <input type="text" name="identifier" required
                               value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                               placeholder="your@email.com or QR-1234567"
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Send Reset Link
                </button>
            </form>

            <p class="text-center text-slate-400 text-sm mt-6">
                Remember your password?
                <a href="login.php" class="text-blue-600 font-semibold hover:text-blue-800">Sign in</a>
            </p>
        </div>
    </div>
</div>

</body></html>
