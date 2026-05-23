<?php
/**
 * auth/reset_password.php
 * Token-based password reset form.
 */
session_start();
require_once '../config/db.php';

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$validToken = false;
$userId = null;

// Validate token
if (!empty($token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND is_active = 1 LIMIT 1");
    $stmt->execute([$tokenHash]);
    $user = $stmt->fetch();

    if ($user) {
        $validToken = true;
        $userId = $user['id'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageType = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")->execute([$hash, $userId]);
        $message = 'Password has been reset successfully! You can now log in.';
        $messageType = 'success';
        $validToken = false; // Prevent reuse
    }
}

$pageTitle = 'Reset Password';
include '../includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center p-6">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-400/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl p-8">

            <?php if ($messageType === 'success'): ?>
            <!-- Success State -->
            <div class="text-center py-6">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-check-circle text-5xl text-green-500"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800 mb-2">Password Reset!</h2>
                <p class="text-slate-500 mb-6"><?= htmlspecialchars($message) ?></p>
                <a href="login.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Go to Login →
                </a>
            </div>

            <?php elseif ($validToken): ?>
            <!-- Reset Form -->
            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-lock-simple-open text-3xl text-blue-600"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">Set New Password</h2>
                <p class="text-slate-500 text-sm mt-1">Enter your new password below</p>
            </div>

            <?php if ($message && $messageType === 'error'): ?>
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-center gap-2">
                <i class="ph ph-warning-circle text-red-500 text-lg"></i>
                <span class="text-red-700 text-sm font-medium"><?= htmlspecialchars($message) ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" id="reset-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 items-start">
                    <!-- Left Side: Password Inputs -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">New Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required minlength="8"
                                       placeholder="Min. 8 characters"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm">
                                <button type="button" onclick="togglePasswordVisibility('password', 'eye-password')" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1">
                                    <i id="eye-password" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                       placeholder="Repeat new password"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm">
                                <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-confirm')" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1">
                                    <i id="eye-confirm" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Checklist -->
                    <div class="md:pt-[26px]">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-3">Password Combination Status</p>
                            <div class="grid grid-cols-1 gap-y-2">
                                <div id="req-length" class="flex items-center gap-2 text-slate-400 transition-colors duration-300">
                                    <i class="ph ph-circle text-[10px] icon"></i>
                                    <span class="text-xs font-semibold">At least 8 characters</span>
                                </div>
                                <div id="req-upper" class="flex items-center gap-2 text-slate-400 transition-colors duration-300">
                                    <i class="ph ph-circle text-[10px] icon"></i>
                                    <span class="text-xs font-semibold">Uppercase letter</span>
                                </div>
                                <div id="req-lower" class="flex items-center gap-2 text-slate-400 transition-colors duration-300">
                                    <i class="ph ph-circle text-[10px] icon"></i>
                                    <span class="text-xs font-semibold">Lowercase letter</span>
                                </div>
                                <div id="req-number" class="flex items-center gap-2 text-slate-400 transition-colors duration-300">
                                    <i class="ph ph-circle text-[10px] icon"></i>
                                    <span class="text-xs font-semibold">Number</span>
                                </div>
                                <div id="req-special" class="flex items-center gap-2 text-slate-400 transition-colors duration-300">
                                    <i class="ph ph-circle text-[10px] icon"></i>
                                    <span class="text-xs font-semibold">Special character</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-500 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Reset Password
                </button>
            </form>

            <?php else: ?>
            <!-- Invalid / Expired Token -->
            <div class="text-center py-6">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-warning-circle text-5xl text-red-500"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800 mb-2">Invalid or Expired Link</h2>
                <p class="text-slate-500 mb-6">This password reset link is no longer valid. Please request a new one.</p>
                <a href="forgot_password.php" class="inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Request New Link
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('ph-eye-slash', 'ph-eye');
        } else {
            input.type = 'password';
            icon.classList.replace('ph-eye', 'ph-eye-slash');
        }
    }

    // --- Password Strength Real-time Validation ---
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        const reqElements = {
            length:  { regex: /.{8,}/,          el: document.getElementById('req-length') },
            upper:   { regex: /[A-Z]/,          el: document.getElementById('req-upper') },
            lower:   { regex: /[a-z]/,          el: document.getElementById('req-lower') },
            number:  { regex: /[0-9]/,          el: document.getElementById('req-number') },
            special: { regex: /[^A-Za-z0-9]/,   el: document.getElementById('req-special') }
        };

        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            Object.keys(reqElements).forEach(key => {
                const req = reqElements[key];
                const isMet = req.regex.test(val);
                const icon = req.el.querySelector('.icon');
                
                if (isMet) {
                    req.el.classList.remove('text-slate-400');
                    req.el.classList.add('text-emerald-500');
                    icon.classList.replace('ph-circle', 'ph-check-circle-fill');
                } else {
                    req.el.classList.remove('text-emerald-500');
                    req.el.classList.add('text-slate-400');
                    icon.classList.replace('ph-check-circle-fill', 'ph-circle');
                }
            });
        });

        // Form Submit check
        document.getElementById('reset-form')?.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const requirementsMet = 
                /.{8,}/.test(password) &&
                /[A-Z]/.test(password) &&
                /[a-z]/.test(password) &&
                /[0-9]/.test(password) &&
                /[^A-Za-z0-9]/.test(password);

            if (!requirementsMet) {
                e.preventDefault();
                alert('Your password does not meet all security requirements.');
                return;
            }

            const cp = document.getElementById('confirm_password').value;
            if (password !== cp) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
</script>
</body></html>
