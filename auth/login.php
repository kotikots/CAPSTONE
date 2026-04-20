<?php
/**
 * auth/login.php
 * Unified login for Passengers, Drivers, and Admins.
 * STEP 5: Session-based auth with role redirect.
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions_security.php';



// Already logged in?
if (isset($_SESSION['user_id']) || isset($_SESSION['driver_id'])) {
    $role = $_SESSION['role'] ?? 'passenger';
    header('Location: ' . redirectFor($role));
    exit;
}

function redirectFor(string $role): string {
    return match($role) {
        'admin'    => '/PARE/admin/dashboard.php',
        'driver'   => '/PARE/driver/dashboard.php',
        default    => '/PARE/passenger/dashboard.php',
    };
}

$error = $error ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $identifier = trim($_POST['identifier'] ?? '');  // email OR id_number OR license
    $password   = $_POST['password'] ?? '';
    $loginAs    = $_POST['login_as'] ?? 'passenger'; // passenger | driver

    if (empty($identifier) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {

        if ($loginAs === 'driver') {
            // --- Driver Login ---
            $stmt = $pdo->prepare("SELECT * FROM drivers WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$identifier]);
            $driver = $stmt->fetch();

            if ($driver && password_verify($password, $driver['password'])) {
                // Fetch permanently assigned bus
                $busStmt = $pdo->prepare("SELECT body_number, plate_number FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
                $busStmt->execute([$driver['id']]);
                $bus = $busStmt->fetch();

                session_regenerate_id(true);
                $_SESSION['driver_id'] = $driver['id'];
                $_SESSION['full_name'] = $driver['full_name'];
                $_SESSION['role']      = 'driver';
                $_SESSION['bus_body']  = $bus['body_number']  ?? 'N/A';
                $_SESSION['bus_plate'] = $bus['plate_number'] ?? 'N/A';

                logSecurityEvent($pdo, $identifier, 'driver', 'success', 'Successful login');
                
                header('Location: /PARE/driver/dashboard.php');
                exit;
            } else {
                $error = 'Invalid driver credentials.';
                logSecurityEvent($pdo, $identifier, 'driver', 'failure', 'Invalid credentials');
            }

        } else {
            // --- Passenger / Admin Login ---
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['id_number'] = $user['id_number'];
                $_SESSION['role']      = $user['role'];

                logSecurityEvent($pdo, $identifier, $user['role'], 'success', 'Successful login');

                header('Location: ' . redirectFor($user['role']));
                exit;
            } else {
                $error = 'Invalid email/ID or password.';
                logSecurityEvent($pdo, $identifier, 'passenger/admin', 'failure', 'Invalid credentials');
            }
        }
    }
}

$loginAs = $_POST['login_as'] ?? 'passenger';
?>
<?php $pageTitle = 'Sign In'; include '../includes/header.php'; ?>

<div class="min-h-screen flex bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900">

    <!-- Left: Branding Panel -->
    <div class="hidden lg:flex flex-col justify-center items-center w-1/2 p-16 text-white">
        <div class="max-w-md">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center">
                    <i class="ph ph-bus-fill text-4xl text-blue-200"></i>
                </div>
                <h1 class="text-5xl font-black tracking-tight">PARE</h1>
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
                <i class="ph ph-bus-fill text-4xl text-blue-300"></i>
                <h1 class="text-4xl font-black text-white tracking-tight">PARE</h1>
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
                            <i class="ph ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl"></i>
                            <input type="email" name="identifier" id="identifier"
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                                   placeholder="Your email address"
                                   required
                                   class="w-full bg-white/10 border border-white/20 text-slate-700 placeholder-slate-400 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Password</label>
                        <div class="relative">
                            <i class="ph ph-lock-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xl"></i>
                            <input type="password" name="password" id="password"
                                   placeholder="Your password"
                                   required
                                   class="w-full bg-white/10 border border-white/20 text-slate-700 placeholder-slate-400 rounded-xl pl-11 pr-12 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <button type="button" onclick="togglePasswordVisibility()" 
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition p-1 z-10">
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

            </div>

            <!-- Admin hint -->
            <p class="text-center text-white/30 text-xs mt-4">
                Admin? Use your email and password (role: admin)
            </p>
        </div>
    </div>
</div>

<script>
    // Update label hint and footer visibility based on role selection
    // Register behavior listener
    document.querySelectorAll('input[name="login_as"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const footer = document.getElementById('register-footer');
            if (radio.value === 'driver') {
                footer.classList.add('invisible');
            } else {
                footer.classList.remove('invisible');
            }
        });
    });

    // Run once on load to ensure correct state if radio was pre-selected
    document.addEventListener('DOMContentLoaded', () => {
        const activeRadio = document.querySelector('input[name="login_as"]:checked');
        if (activeRadio && activeRadio.value === 'driver') {
            document.getElementById('register-footer').classList.add('invisible');
        }
    });

    function togglePasswordVisibility() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
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
