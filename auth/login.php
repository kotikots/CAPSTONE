<?php
/**
 * auth/login.php
 */

// Start the session so we can read and write $_SESSION variables.
session_start();

// Load the database connection ($pdo) and the security helper functions.
require_once '../config/db.php';
require_once '../includes/functions_security.php';

// If the user clicks "Cancel" during MFA, wipe all pending session data
// and send them back to a clean login page.
if (isset($_GET['cancel_mfa']) && $_GET['cancel_mfa'] == 1) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_otp'], $_SESSION['pending_driver_id'], $_SESSION['pending_bus_body'], $_SESSION['pending_bus_plate'], $_SESSION['pending_user_id'], $_SESSION['pending_id_number'], $_SESSION['pending_full_name'], $_SESSION['pending_role']);
    header('Location: login.php');
    exit;
}

// If the user is already logged in (session exists), skip the login form
// and redirect them straight to their own dashboard.
if (isset($_SESSION['user_id']) || isset($_SESSION['driver_id'])) {
    $role = $_SESSION['role'] ?? 'passenger';
    header('Location: ' . redirectFor($role));
    exit;
}

// Maps a user's role to their correct dashboard URL.
// Admins → admin portal, Drivers → driver portal, everyone else → passenger portal.
function redirectFor(string $role): string {
    return match($role) {
        'admin'    => '/PARE/admin/dashboard.php',
        'driver'   => '/PARE/driver/dashboard.php',
        default    => '/PARE/passenger/dashboard.php',
    };
}

// Initialize $error to empty string to avoid undefined variable warnings.
$error = $error ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {

    if (false) {
        // MFA step disabled for development — block unreachable
    } else {
       

        // Collect and trim inputs. $identifier can be email, ID number, or license.
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $loginAs    = $_POST['login_as'] ?? 'passenger'; // Which login tab the user selected.

        // Basic presence check — both fields must be filled in.
        if (empty($identifier) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {

            // ── DRIVER LOGIN BRANCH ─────────────────────────────────────────
            if ($loginAs === 'driver') {

                // Look up the driver by email. Only active accounts (is_active=1) can log in.
                // Using a prepared statement to prevent SQL injection.
                $stmt = $pdo->prepare("SELECT * FROM drivers WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$identifier]);
                $driver = $stmt->fetch();

                // password_verify() safely compares the plain-text input against the stored bcrypt hash.
                if ($driver && password_verify($password, $driver['password'])) {

                    // Fetch the bus this driver is assigned to (needed for the dashboard header).
                    $busStmt = $pdo->prepare("SELECT body_number, plate_number FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
                    $busStmt->execute([$driver['id']]);
                    $bus = $busStmt->fetch();

                    // MFA disabled for development — log in directly.
                    session_regenerate_id(true);
                    $_SESSION['driver_id']  = $driver['id'];
                    $_SESSION['full_name']  = $driver['full_name'];
                    $_SESSION['role']       = 'driver';
                    $_SESSION['bus_body']   = $bus['body_number']  ?? 'N/A';
                    $_SESSION['bus_plate']  = $bus['plate_number'] ?? 'N/A';

                    logSecurityEvent($pdo, 'User', 'driver', 'success', 'Direct login (MFA disabled)');
                    header('Location: ' . redirectFor('driver'));
                    exit;

                } else {
                    // Wrong credentials — show a generic error and log the failed attempt.
                    $error = 'Invalid driver credentials.';
                    logSecurityEvent($pdo, $identifier, 'driver', 'failure', 'Invalid credentials');
                }

            // ── PASSENGER / ADMIN LOGIN BRANCH ──────────────────────────────
            } else {

                // Look up passenger or admin by email. Only active accounts can log in.
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();

                // Verify the password against the stored bcrypt hash.
                if ($user && password_verify($password, $user['password'])) {

                    // MFA disabled for development — log in directly.
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['id_number'] = $user['id_number'];
                    $_SESSION['role']      = $user['role'];

                    logSecurityEvent($pdo, 'User', $user['role'], 'success', 'Direct login (MFA disabled)');
                    header('Location: ' . redirectFor($user['role']));
                    exit;

                } else {
                    // Wrong credentials — log the failure for brute-force monitoring.
                    $error = 'Invalid email/ID or password.';
                    logSecurityEvent($pdo, $identifier, 'passenger/admin', 'failure', 'Invalid credentials');
                }
            }
        }
    }
}

$loginAs = $_POST['login_as'] ?? 'passenger';
?>
<?php $pageTitle = 'Sign In'; include '../includes/header.php'; ?>

<style>
/* Restore Dark Neon Blue Theme exclusively for the Auth pages */
body {
    background: linear-gradient(135deg, #020617 0%, #0f172a 40%, #1e3a8a 100%) !important;
    color: #ffffff !important;
}
/* Revert inputs to dark theme styling to match the background */
input, select, textarea {
    background-color: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}
input:focus, select:focus, textarea:focus {
    background-color: rgba(255, 255, 255, 0.15) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 4px rgba(56, 189, 248, 0.25) !important;
}
input:-webkit-autofill, input:-webkit-autofill:hover, 
input:-webkit-autofill:focus, input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px #0f172a inset !important;
    -webkit-text-fill-color: #ffffff !important;
}
</style>

<div class="min-h-screen flex">

    <!-- Left: Branding Panel -->
    <div class="hidden lg:flex flex-col justify-center items-center w-1/2 p-16 text-white">
        <div class="max-w-md">
            <div class="flex items-center gap-4 mb-8">
                <img src="/PARE/assets/img/logo.png" alt="PARE Logo" class="w-28 h-28 object-contain drop-shadow-2xl">
            </div>
            <h2 class="text-3xl font-bold mb-4 leading-tight">
               A Web-Based Passenger <br> and Revenue Monitoring System
            </h2>
            <p class="text-blue-200 text-lg leading-relaxed mb-10">
                Real-time bus tracking, instant ticketing, and seamless fare collection — all in one platform.
            </p>

            <!-- Feature pills -->
            <div class="space-y-3">
                <?php foreach ([
                    ['ph-map-pin',  'Real-time bus location tracking'],
                    ['ph-ticket',   'Instant digital ticket generation'],
                    ['ph-coins',    'Automated fare calculation'],
                    ['ph-chart-bar','Revenue monitoring & reports'],
                ] as [$icon, $text]): ?>
                <div class="flex items-center gap-3 bg-white/10 rounded-2xl px-5 py-3">
                    <i class="ph <?= $icon ?> text-blue-300 text-xl"></i>
                    <span class="text-sm font-medium text-blue-100"><?= $text ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right: Login Form -->
    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md">

            <!-- Mobile Logo -->
            <div class="flex items-center justify-center gap-3 mb-8 lg:hidden">
                <img src="/PARE/assets/img/logo.png" alt="PARE Logo" class="w-24 h-24 object-contain drop-shadow-xl">
            </div>

            <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl p-8">

                <h2 class="text-2xl font-black text-white mb-2">Welcome back</h2>
                <p class="text-blue-200 text-sm mb-6">Sign in to your account to continue.</p>

                <!-- Error Alert -->
                <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-400/40 rounded-xl px-4 py-3 mb-5 flex items-center gap-3">
                    <i class="ph ph-warning-circle text-red-400 text-xl shrink-0"></i>
                    <span class="text-red-200 text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <!-- Check MFA State -->
                <?php if (isset($_SESSION['mfa_pending']) && $_SESSION['mfa_pending'] === true): ?>
                
                <!-- MFA OTP Form -->
                <form method="POST" id="mfa-form" class="space-y-4">
                    <div class="text-center mb-6">
                        <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-400/30">
                            <i class="ph ph-shield-check text-3xl text-blue-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1">Two-Step Verification</h3>
                        <p class="text-blue-200 text-sm">Enter the 6-digit code sent to your device.</p>
                    </div>

                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5 text-center">Authentication Code</label>
                        <input type="text" name="otp_code" id="otp_code"
                               maxlength="6"
                               placeholder="------"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-4 text-center text-3xl tracking-[1em] font-mono focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <button type="submit"
                            class="w-full bg-emerald-500 hover:bg-emerald-400 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-emerald-500/40 transition-all mt-4">
                        Verify Code
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="login.php?cancel_mfa=1" class="text-blue-300 text-sm font-medium hover:text-white transition">Cancel and return to login</a>
                    </div>
                </form>

                <?php else: ?>

                <!-- Standard Login Form -->
                <form method="POST" id="login-form" class="space-y-4">

                    <!-- Role Toggle -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-2">Login as</label>
                        <div class="grid grid-cols-2 gap-2 bg-white/10 rounded-xl p-1">
                            <?php foreach (['passenger' => ['ph-user', 'Passenger'], 'driver' => ['ph-steering-wheel', 'Driver']] as $role => [$icon, $label]): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="login_as" value="<?= $role ?>"
                                       <?= $loginAs === $role ? 'checked' : '' ?>
                                       class="sr-only peer">
                                <div class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg text-sm font-semibold
                                            text-white/50 peer-checked:bg-blue-500 peer-checked:text-white peer-checked:shadow-lg transition-all">
                                    <i class="ph <?= $icon ?>"></i> <?= $label ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Identifier -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5" id="id-label">
                            Email Address
                        </label>
                        <div class="relative">
                            <i class="ph ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl z-10 pointer-events-none"></i>
                            <input type="email" name="identifier" id="identifier"
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                                   placeholder="Your email address"
                                   required
                                   class="w-full bg-white/10 border border-white/20 text-slate-700 placeholder-slate-400 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 relative">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Password</label>
                        <div class="relative">
                            <i class="ph ph-lock-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl z-10 pointer-events-none"></i>
                            <input type="password" name="password" id="password"
                                   placeholder="Your password"
                                   required
                                   class="w-full bg-white/10 border border-white/20 text-slate-700 placeholder-slate-400 rounded-xl pl-11 pr-12 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 relative">
                            <button type="button" onclick="togglePasswordVisibility()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1 z-20">
                                <i id="eye-icon" class="ph ph-eye-slash text-xl"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black text-base py-4 rounded-2xl shadow-lg hover:shadow-blue-500/40 transition-all mt-2">
                        Sign In
                    </button>

                </form>

                <!-- Forgot Password -->
                <div class="text-right mt-2" id="forgot-link">
                    <a href="forgot_password.php" class="text-blue-300 text-xs font-medium hover:text-white transition">Forgot password?</a>
                </div>

                <div class="mt-4 text-center <?= $loginAs === 'driver' ? 'invisible' : '' ?>" id="register-footer">
                    <p class="text-white/40 text-sm">
                        No account yet?
                        <a href="register.php" class="text-blue-300 font-semibold hover:text-white">Register here</a>
                    </p>
                </div>
                
                <?php endif; ?>

            </div>

            <!-- Admin hint -->
            <p class="text-center text-white/30 text-xs mt-4">
                Admin? Use your email and password (role: admin)
            </p>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['mfa_pending']) && $_SESSION['mfa_pending'] === true): ?>
<script>
    // Simulate incoming SMS/Email with the OTP code for the video demonstration
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            if (typeof showToast === 'function') {
                showToast('Authentication Code', 'Your verification code is: <?= $_SESSION['mfa_otp'] ?>', 'info');
            } else {
                alert('Your verification code is: <?= $_SESSION['mfa_otp'] ?>');
            }
        }, 500); // 0.5s delay to make it feel natural
    });
</script>
<?php endif; ?>

<script>
    // Update label hint and footer visibility based on role selection
    // Register behavior listener
    document.querySelectorAll('input[name="login_as"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const footer = document.getElementById('register-footer');
            if(footer) {
                if (radio.value === 'driver') {
                    footer.classList.add('invisible');
                } else {
                    footer.classList.remove('invisible');
                }
            }
        });
    });

    // Run once on load to ensure correct state if radio was pre-selected
    document.addEventListener('DOMContentLoaded', () => {
        const activeRadio = document.querySelector('input[name="login_as"]:checked');
        if (activeRadio && activeRadio.value === 'driver') {
            const footer = document.getElementById('register-footer');
            if(footer) footer.classList.add('invisible');
        }
    });

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        if(!passwordInput) return;
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.classList.replace('ph-eye-slash', 'ph-eye');
        } else {
            passwordInput.type = 'password';
            eyeIcon.classList.replace('ph-eye', 'ph-eye-slash');
        }
    }
</script>

</body>
</html>
