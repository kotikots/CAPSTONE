<?php
/**
 * auth/login.php
 */

session_start();

require_once '../config/db.php';
require_once '../includes/functions_security.php';

if (isset($_GET['cancel_mfa']) && $_GET['cancel_mfa'] == 1) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_otp'], $_SESSION['pending_driver_id'], $_SESSION['pending_bus_body'], $_SESSION['pending_bus_plate'], $_SESSION['pending_user_id'], $_SESSION['pending_id_number'], $_SESSION['pending_full_name'], $_SESSION['pending_role']);
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_id']) || isset($_SESSION['driver_id'])) {
    $role = $_SESSION['role'] ?? 'passenger';
    header('Location: ' . redirectFor($role));
    exit;
}

function redirectFor(string $role): string {
    return match($role) {
        'admin'    => '/PARE/admin/dashboard.php',
        'driver'   => '/PARE/driver/dashboard_v2.php',
        default    => '/PARE/passenger/dashboard.php',
    };
}

$error = $error ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (false) {
        // MFA step disabled
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';
        $loginAs    = $_POST['login_as'] ?? 'passenger';

        if (empty($identifier) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            if ($loginAs === 'driver') {
                $stmt = $pdo->prepare("SELECT * FROM drivers WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$identifier]);
                $driver = $stmt->fetch();

                if ($driver && password_verify($password, $driver['password'])) {
                    $busStmt = $pdo->prepare("SELECT body_number, plate_number FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
                    $busStmt->execute([$driver['id']]);
                    $bus = $busStmt->fetch();

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
                    $error = 'Invalid driver credentials.';
                    logSecurityEvent($pdo, $identifier, 'driver', 'failure', 'Invalid credentials');
                }
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$identifier]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['is_active'] == 0) {
                        $error = 'Your account is pending verification by the admin. You will receive an email once approved.';
                        logSecurityEvent($pdo, $identifier, 'passenger', 'failure', 'Login attempt on inactive account');
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['id_number'] = $user['id_number'];
                        $_SESSION['role']      = $user['role'];

                        logSecurityEvent($pdo, 'User', $user['role'], 'success', 'Direct login (MFA disabled)');
                        header('Location: ' . redirectFor($user['role']));
                        exit;
                    }
                } else {
                    $error = 'Invalid email/ID or password.';
                    logSecurityEvent($pdo, $identifier, 'passenger/admin', 'failure', 'Invalid credentials');
                }
            }
        }
    }
}

$loginAs = $_POST['login_as'] ?? 'passenger';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — PARE System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }

        html, body {
            min-height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%);
            background-attachment: fixed;
            min-height: 100vh;
            user-select: none;
            cursor: default;
        }

        /* Dark input styling */
        .login-input {
            background: rgba(255, 255, 255, 0.65) !important;
            border: 1.5px solid rgba(255, 255, 255, 0.8) !important;
            color: #1e3a5f !important;
            border-radius: 10px;
            width: 100%;
            padding: 12px 12px 12px 44px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.2s;
            cursor: text !important;
            user-select: text !important;
        }
        .login-input::placeholder { color: rgba(30,58,95,0.35); }
        .login-input:focus {
            background: rgba(255, 255, 255, 0.9) !important;
            border-color: #3b6fd4 !important;
            box-shadow: 0 0 0 3px rgba(59,111,212,0.15) !important;
        }
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px #eaf3ff inset !important;
            -webkit-text-fill-color: #1e3a5f !important;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(59, 111, 212, 0.12);
        }

        .role-bg {
            background: rgba(0,0,0,0.06);
            border-radius: 10px;
            padding: 4px;
        }

        .role-option {
            border-radius: 8px;
            padding: 9px 16px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(30,58,95,0.55);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1.5px solid transparent;
        }
        .role-option.active {
            background: #3B518F;
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 2px 12px rgba(59,81,143,0.35);
        }
        .role-option:not(.active) {
            border: 1.5px solid rgba(30,58,95,0.15);
        }

        .btn-sign-in {
            background-color: #061A53;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            border-radius: 10px;
            padding: 14px;
            width: 100%;
            border: none;
            cursor: pointer !important;
            transition: all 0.2s;
            box-shadow: 0 4px 18px rgba(6,26,83,0.35);
            letter-spacing: 0.01em;
        }
        .btn-sign-in:hover {
            background-color: #04123b;
            box-shadow: 0 6px 24px rgba(6,26,83,0.5);
            transform: translateY(-1px);
        }
        .btn-sign-in:active { transform: scale(0.98); }

        a { cursor: pointer !important; }
    </style>
</head>
<body class="flex items-stretch min-h-screen">

    <!-- ====== LAYOUT WRAPPER ====== -->
    <div class="flex items-start lg:items-center justify-center min-h-screen w-full py-8 lg:py-12">
        <div class="flex flex-col lg:flex-row items-center lg:items-start justify-center w-full max-w-7xl gap-10 lg:gap-32 px-6 lg:px-12">

            <!-- ====== LEFT: BRANDING ====== -->
            <div class="flex flex-col justify-start items-center lg:items-center text-center lg:text-center w-full lg:w-1/2 max-w-lg mx-auto lg:mx-0">

                <!-- Logo + Name -->
                <div class="flex items-center justify-center gap-4 mt-8 lg:mt-10 mb-2 lg:mb-2 w-full">
                    <img src="/PARE/assets/img/logo.png?v=2" alt="PARE Logo"
                         class="w-auto h-32 lg:h-48 object-contain drop-shadow-xl scale-125">

                </div>

                <!-- Title -->
                <h1 class="text-[22px] sm:text-[26px] lg:text-[34px] font-black leading-tight mb-4 lg:mb-5 text-slate-900" style="line-height:1.2">
                    A Web-Based Passenger and<br>Revenue Monitoring System
                </h1>

                <!-- Subtitle -->
                <p class="text-slate-600 text-[13px] sm:text-[14px] lg:text-base font-normal leading-relaxed">
                    Real-time bus tracking, instant ticketing, and<br class="block">
                    seamless fare collection — all in one platform.
                </p>

            </div>

            <!-- ====== RIGHT: LOGIN FORM ====== -->
            <div class="flex justify-center lg:justify-end w-full lg:w-1/2">

                <div class="login-card w-full max-w-xl p-8 lg:p-12">

                <!-- Card Title -->
                <h2 class="text-slate-900 font-bold text-2xl mb-1">Welcome back</h2>

                <!-- Error -->
                <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-400/30 rounded-xl px-4 py-3 mb-4 flex items-center gap-3 mt-3">
                    <i class="ph ph-warning-circle text-red-500 text-xl shrink-0"></i>
                    <span class="text-red-700 text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['mfa_pending']) && $_SESSION['mfa_pending'] === true): ?>
                <!-- MFA Form -->
                <form method="POST" class="space-y-4 mt-6">
                    <div class="text-center mb-4">
                        <i class="ph ph-shield-check text-4xl text-blue-400"></i>
                        <h3 class="text-lg font-bold text-white mt-2">Two-Step Verification</h3>
                        <p class="text-blue-200 text-sm mt-1">Enter the 6-digit code sent to your device.</p>
                    </div>
                    <div class="relative">
                        <input type="text" name="otp_code" maxlength="6" placeholder="• • • • • •" required
                               class="login-input text-center text-2xl tracking-[0.5em] font-mono" style="padding-left:12px">
                    </div>
                    <button type="submit" class="btn-sign-in mt-2">Verify Code</button>
                    <div class="text-center mt-3">
                        <a href="login.php?cancel_mfa=1" class="text-blue-300 text-sm hover:text-white transition">← Back to login</a>
                    </div>
                </form>

                <?php else: ?>
                <!-- Standard Form -->
                <form method="POST" class="mt-5 space-y-4">

                    <!-- Role Toggle -->
                    <div>
                        <p class="text-slate-600 text-[11px] font-bold uppercase tracking-widest mb-2">Login As</p>
                        <div class="role-bg flex gap-1">
                            <?php foreach (['passenger' => ['ph-user', 'Passenger'], 'driver' => ['ph-clock', 'Driver']] as $role => [$icon, $label]): ?>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="login_as" value="<?= $role ?>"
                                       <?= $loginAs === $role ? 'checked' : '' ?>
                                       class="sr-only peer">
                                <div class="role-option <?= $loginAs === $role ? 'active' : '' ?> justify-center peer-checked:bg-[#3b6fd4] peer-checked:text-white peer-checked:border-transparent peer-checked:shadow-lg">
                                    <i class="ph <?= $icon ?> text-base"></i>
                                    <span><?= $label ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-slate-700 text-sm font-medium mb-1.5">Email Address</label>
                        <div class="relative">
                            <i class="ph ph-envelope-simple absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none"></i>
                            <input type="email" name="identifier" id="identifier"
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                                   placeholder="admin@pare.local"
                                   required
                                   class="login-input">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-slate-700 text-sm font-medium mb-1.5">Password</label>
                        <div class="relative">
                            <i class="ph ph-lock-simple absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none"></i>
                            <input type="password" name="password" id="password"
                                   placeholder="Your password"
                                   required
                                   class="login-input" style="padding-right:44px">
                            <button type="button" onclick="togglePwd()"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-1 cursor-pointer">
                                <i id="eye-icon" class="ph ph-eye-slash text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Sign In -->
                    <button type="submit" class="btn-sign-in mt-2">Sign In</button>

                </form>

                <!-- Footer links -->
                <div class="flex justify-between items-start mt-4">
                    <div id="register-footer" class="<?= $loginAs === 'driver' ? 'invisible' : '' ?>">
                        <p class="text-slate-600 text-[13px] sm:text-sm">
                            No account yet? <br class="block sm:hidden"><a href="register.php" class="text-[#3b6fd4] underline font-bold hover:text-[#2f5ec4] transition">Register here</a>
                        </p>
                    </div>
                    <a href="forgot_password.php" class="text-slate-600 font-bold text-[13px] sm:text-sm hover:text-slate-800 transition shrink-0 ml-2 mt-[2px] sm:mt-0">Forgot password?</a>
                </div>

                <!-- Admin hint -->
                <p class="text-center text-slate-400 text-xs mt-5 pt-4 border-t border-slate-200/60">
                    Admin? Use your email and password (role: admin).
                </p>

                <?php endif; ?>

            </div>
        </div>

    </div>

<?php if (isset($_SESSION['mfa_pending']) && $_SESSION['mfa_pending'] === true): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => { alert('Your verification code is: <?= $_SESSION['mfa_otp'] ?>'); }, 500);
    });
</script>
<?php endif; ?>

<script>
    // Keep role tab visuals in sync on change
    document.querySelectorAll('input[name="login_as"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.role-option').forEach(el => {
                el.classList.remove('active');
                el.style.background = '';
            });
            const tab = radio.closest('label').querySelector('.role-option');
            if (tab) tab.classList.add('active');

            const footer = document.getElementById('register-footer');
            if (footer) {
                footer.classList.toggle('invisible', radio.value === 'driver');
            }
        });
    });

    function togglePwd() {
        const inp = document.getElementById('password');
        const ico = document.getElementById('eye-icon');
        if (!inp) return;
        inp.type = inp.type === 'password' ? 'text' : 'password';
        ico.classList.toggle('ph-eye-slash', inp.type === 'password');
        ico.classList.toggle('ph-eye', inp.type === 'text');
    }
</script>

</body>
</html>
