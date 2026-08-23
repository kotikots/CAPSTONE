<?php
/**
 * driver/profile.php
 * View profile + change password for drivers.
 */
$requiredRole = 'driver';
$pageTitle    = 'My Profile';
$currentPage  = 'profile.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions_v2.php';

$driverId = $_SESSION['driver_id'];
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $captcha  = trim($_POST['captcha'] ?? '');

    // Validate Captcha
    if (strtoupper($captcha) !== strtoupper($_SESSION['captcha_code'] ?? '')) {
        $_SESSION['flash_error'] = 'Security check failed. Please enter the correct captcha text.';
    } else {
        // Validate Password
        $stmt = $pdo->prepare("SELECT password FROM drivers WHERE id = ?");
        $stmt->execute([$driverId]);
        $driver = $stmt->fetch();

        if (!password_verify($current, $driver['password'])) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/[0-9]/', $new) || !preg_match('/[^A-Za-z0-9]/', $new)) {
            $_SESSION['flash_error'] = 'Password must contain uppercase, lowercase, number, and special character.';
        } elseif ($new !== $confirm) {
            $_SESSION['flash_error'] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE drivers SET password = ? WHERE id = ?")->execute([$hash, $driverId]);
            $_SESSION['flash_success'] = 'Password changed successfully!';
        }
    }
    
    unset($_SESSION['captcha_code']);
    header("Location: profile.php");
    exit;
}

// Fetch full profile
$profileStmt = $pdo->prepare(
    "SELECT full_name, license_number, profile_picture, contact_number, email, 
            address, region, province, city, barangay, created_at
     FROM drivers WHERE id = ?"
);
$profileStmt->execute([$driverId]);
$dr = $profileStmt->fetch();

// Trip stats for driver
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_trips FROM trips WHERE driver_id = ?"
);
$statsStmt->execute([$driverId]);
$totalTrips = $statsStmt->fetchColumn();

// Attendance/Revenue stats (Placeholder for consistency with passenger design)
$revStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(fare_amount), 0) AS total_lifetime_revenue 
     FROM tickets t JOIN trips tr ON t.trip_id = tr.id 
     WHERE tr.driver_id = ?"
);
$revStmt->execute([$driverId]);
$totalRev = $revStmt->fetchColumn();

// Init Captcha session
if (!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = 'PARE'; 
}

include '../includes/header.php';
?>

<div class="flex min-h-screen" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%);">
    <?php include '../includes/sidebar_driver.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto pb-24 md:pb-8">

        <div class="mb-8">
            <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">My Profile</h2>
            <p class="text-[#4C5C79] text-sm mt-1">Manage your professional driver account</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT: Profile Card -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-[#E2E8F0] p-6 text-center">
                    <div class="w-24 h-24 rounded-full mx-auto mb-4 overflow-hidden bg-[#FFE8D9] flex items-center justify-center border-4 border-[#FFE8D9]">
                        <?php if ($dr['profile_picture']): ?>
                        <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($dr['profile_picture']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                        <i class="ph ph-steering-wheel text-4xl text-[#FE6B17]"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-xl font-black text-[#0F172A]"><?= htmlspecialchars($dr['full_name']) ?></h3>
                    <p class="text-[#4C5C79] text-sm mt-1">License: <?= htmlspecialchars($dr['license_number']) ?></p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 bg-[#BFDBFE] text-[#0F172A] text-xs font-bold px-4 py-2 rounded-full">
                            <i class="ph ph-shield-check"></i> Verified Driver
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-5 pt-5 border-t border-[#E2E8F0]">
                        <div>
                            <p class="text-2xl font-black text-[#0F172A]"><?= number_format((int)$totalTrips) ?></p>
                            <p class="text-xs text-[#4C5C79]">Total Trips</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-[#0D8E30]"><?= peso((float)$totalRev) ?></p>
                            <p class="text-xs text-[#4C5C79]">Lifetime Revenue</p>
                        </div>
                    </div>
                    <p class="text-xs text-[#64748B] mt-4">Partner since <?= date('M d, Y', strtotime($dr['created_at'])) ?></p>
                </div>
            </div>

            <!-- RIGHT: Details + Password -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Personal Info -->
                <div class="bg-white rounded-3xl shadow-sm border border-[#E2E8F0] p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-bold text-[#0F172A] flex items-center gap-2">
                            <i class="ph ph-identification-card text-[#166AEC]"></i> Driver Information
                        </h3>
                        <div id="toolbar-view">
                            <button onclick="toggleEdit(true)" class="flex items-center gap-2 bg-[#DAE8FD] text-[#166AEC] hover:bg-blue-100 font-bold px-4 py-2 rounded-xl text-xs transition active:scale-95">
                                <i class="ph ph-pencil-simple"></i> Edit Profile
                            </button>
                        </div>
                        <div id="toolbar-edit" class="hidden flex items-center gap-2">
                            <button onclick="toggleEdit(false)" class="bg-[#F8FAFC] text-[#4C5C79] hover:bg-slate-200 font-bold px-4 py-2 rounded-xl text-xs transition">
                                Cancel
                            </button>
                            <button onclick="saveProfile()" class="bg-[#166AEC] text-white hover:bg-[#DAE8FD]0 font-bold px-4 py-2 rounded-xl text-xs transition shadow-lg shadow-blue-500/20 active:scale-95">
                                Save Changes
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php
                        $fields = [
                            ['Full Name', $dr['full_name'], 'ph-user', 'full_name', true],
                            ['License Number', $dr['license_number'], 'ph-certificate', 'license_number', false],
                            ['Contact', $dr['contact_number'], 'ph-phone', 'contact_number', true],
                            ['Email', $dr['email'] ?: '', 'ph-envelope', 'email', true],
                            ['Address', $dr['address'], 'ph-map-pin', 'address', true],
                        ];
                        foreach ($fields as [$label, $value, $icon, $key, $editable]):
                        ?>
                        <div class="<?= $key === 'address' ? 'md:col-span-2' : '' ?>">
                            <label class="block text-[#4C5C79] text-[10px] font-black mb-1.5 uppercase tracking-wider"><?= $label ?></label>
                            
                            <div class="view-mode flex items-center gap-2 bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] rounded-xl px-4 py-3 border border-[#E2E8F0]">
                                <i class="ph <?= $icon ?> text-[#4C5C79] text-lg"></i>
                                <span id="label-<?= $key ?>" class="text-[#0F172A] text-sm font-semibold truncate"><?= htmlspecialchars($value ?: '—') ?></span>
                            </div>

                            <?php if ($editable): ?>
                            <div class="edit-mode hidden relative">
                                <?php if ($key === 'address'): ?>
                                    <div class="space-y-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <select id="region" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="province" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="city" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="barangay" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all"></select>
                                        </div>
                                        <input type="hidden" id="input-address" value="<?= htmlspecialchars($value) ?>">
                                    </div>
                                <?php elseif ($key === 'contact_number'): ?>
                                    <div class="relative flex items-center">
                                        <input type="tel" id="input-contact_number" 
                                               value="<?= htmlspecialchars($value) ?>"
                                               maxlength="11"
                                               pattern="[0-9]{11}"
                                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                               class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-bold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all">
                                    </div>
                                <?php else: ?>
                                    <i class="ph <?= $icon ?> absolute left-4 top-1/2 -translate-y-1/2 text-[#4C5C79] text-lg"></i>
                                    <input type="<?= $key === 'email' ? 'email' : 'text' ?>" 
                                           id="input-<?= $key ?>"
                                           value="<?= htmlspecialchars($value) ?>"
                                           class="w-full bg-white border-2 border-blue-100 rounded-xl pl-11 pr-4 py-3 text-sm font-semibold text-[#0F172A] focus:outline-none focus:border-blue-500 transition-all">
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="edit-mode hidden flex items-center gap-2 bg-[#F8FAFC] rounded-xl px-4 py-3 border border-[#E2E8F0] opacity-60">
                                <i class="ph <?= $icon ?> text-[#4C5C79] text-lg"></i>
                                <span class="text-[#4C5C79] text-sm font-semibold italic"><?= htmlspecialchars($value) ?> (Read-only)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-3xl shadow-sm border border-[#E2E8F0] p-6">
                    <h3 class="font-bold text-[#0F172A] mb-5 flex items-center gap-2">
                        <i class="ph ph-lock text-red-500"></i> Change Password
                    </h3>

                    <?php if ($success): ?>
                    <div class="bg-[#DBF7E4] border border-[#DBF7E4] rounded-2xl p-4 mb-5 flex items-center gap-3">
                        <i class="ph ph-check-circle text-[#0D8E30] text-xl"></i>
                        <span class="text-[#0D8E30] text-sm font-medium"><?= htmlspecialchars($success) ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-5 flex items-center gap-3">
                        <i class="ph ph-warning-circle text-red-500 text-xl"></i>
                        <span class="text-red-700 text-sm font-medium"><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="change_password" value="1">
                        <div>
                            <label class="block text-[#4C5C79] text-xs font-bold mb-1.5 uppercase tracking-wider">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" id="current_password" required
                                       class="w-full bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] border border-[#E2E8F0] text-[#0F172A] rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 text-sm">
                                <button type="button" onclick="togglePasswordVisibility('current_password', 'eye-current')" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4C5C79] hover:text-amber-500 transition p-1">
                                    <i id="eye-current" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-[#4C5C79] text-xs font-bold mb-1.5 uppercase tracking-wider">New Password</label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="new_password" required minlength="8" placeholder="Min. 8 characters"
                                               class="w-full bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] border border-[#E2E8F0] text-[#0F172A] placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 text-sm">
                                        <button type="button" onclick="togglePasswordVisibility('new_password', 'eye-new')" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4C5C79] hover:text-amber-500 transition p-1">
                                            <i id="eye-new" class="ph ph-eye-slash text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[#4C5C79] text-xs font-bold mb-1.5 uppercase tracking-wider">Confirm New Password</label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repeat new password"
                                               class="w-full bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] border border-[#E2E8F0] text-[#0F172A] placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 text-sm">
                                        <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-confirm')" 
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4C5C79] hover:text-amber-500 transition p-1">
                                            <i id="eye-confirm" class="ph ph-eye-slash text-xl"></i>
                                        </button>
                                    </div>
                                    <p id="match-hint" class="text-[10px] mt-1.5 font-bold hidden"></p>
                                </div>
                            </div>
                            
                            <!-- Password Requirements Checklist -->
                            <div class="md:pt-[24px]">
                                <div id="pw-req-box" class="p-3 bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] rounded-2xl border border-[#E2E8F0] transition-all duration-300">
                                    <p class="text-[10px] font-bold text-[#4C5C79] uppercase tracking-widest mb-2">Password Combination Status</p>
                                    <div class="grid grid-cols-1 gap-y-1">
                                        <div id="req-length" class="flex items-center gap-2 text-[#64748B] transition-colors duration-300">
                                            <i class="ph ph-circle text-[10px] icon"></i>
                                            <span class="text-xs font-semibold">At least 8 characters</span>
                                        </div>
                                        <div id="req-upper" class="flex items-center gap-2 text-[#64748B] transition-colors duration-300">
                                            <i class="ph ph-circle text-[10px] icon"></i>
                                            <span class="text-xs font-semibold">Uppercase letter</span>
                                        </div>
                                        <div id="req-lower" class="flex items-center gap-2 text-[#64748B] transition-colors duration-300">
                                            <i class="ph ph-circle text-[10px] icon"></i>
                                            <span class="text-xs font-semibold">Lowercase letter</span>
                                        </div>
                                        <div id="req-number" class="flex items-center gap-2 text-[#64748B] transition-colors duration-300">
                                            <i class="ph ph-circle text-[10px] icon"></i>
                                            <span class="text-xs font-semibold">Number</span>
                                        </div>
                                        <div id="req-special" class="flex items-center gap-2 text-[#64748B] transition-colors duration-300">
                                            <i class="ph ph-circle text-[10px] icon"></i>
                                            <span class="text-xs font-semibold">Special character</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] rounded-2xl border border-[#E2E8F0]">
                            <div class="flex flex-col md:flex-row items-center gap-3">
                                <div class="bg-white border-2 border-[#E2E8F0] rounded-xl p-1.5 h-12 w-32 flex items-center justify-center overflow-hidden shrink-0 shadow-sm">
                                    <img id="captchaImg" src="../includes/api_captcha_svg.php?v=<?= time() ?>" alt="Captcha" class="max-h-full opacity-90">
                                </div>
                                <div class="flex-1 w-full relative">
                                    <input type="text" name="captcha" required placeholder="Type the text" autocomplete="off"
                                           class="w-full bg-white border-2 border-[#E2E8F0] focus:border-orange-500 rounded-xl px-4 py-2.5 text-sm font-black text-[#0F172A] uppercase tracking-widest focus:outline-none transition-all shadow-sm">
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="bg-orange-600 hover:bg-orange-500 text-white font-bold px-6 py-3 rounded-xl shadow-lg hover:shadow-orange-500/20 transition active:scale-95">
                            <i class="ph ph-lock-simple mr-1"></i> Update Password
                        </button>
                    </form>
                </div>

            </div>
        </div>

    </main>
</div>

<script>
function toggleEdit(isActive) {
    document.getElementById('toolbar-view').classList.toggle('hidden', isActive);
    document.getElementById('toolbar-edit').classList.toggle('hidden', !isActive);
    
    document.querySelectorAll('.view-mode').forEach(el => el.classList.toggle('hidden', isActive));
    document.querySelectorAll('.edit-mode').forEach(el => el.classList.toggle('hidden', !isActive));
}

async function saveProfile() {
    const fields = ['full_name', 'contact_number', 'email', 'address'];
    const data = {};
    fields.forEach(f => {
        const el = document.getElementById(`input-${f}`);
        if (el) data[f] = el.value.trim();
    });

    ['region', 'province', 'city', 'barangay'].forEach(key => {
        data[key] = document.getElementById(key).value;
    });

    try {
        const res = await fetch('api_update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            window.showToast('Profile Updated', 'Your information has been saved successfully.', 'success');
            
            fields.forEach(f => {
                const val = data[f];
                const label = document.getElementById(`label-${f}`);
                if (label) label.textContent = val || '—';
            });
 
            const cardName = document.querySelector('h3.text-xl.font-black.text-[#0F172A]');
            if (cardName) cardName.textContent = data.full_name;
 
            toggleEdit(false);
        } else {
            window.showToast('Update Failed', result.message, 'error');
        }
    } catch (err) {
        window.showToast('Network Error', 'Could not reach the server.', 'error');
    }
}
 
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

// ─── Password Strength Real-time Validation ───
const passwordInput = document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');

const reqElements = {
    length:  { regex: /.{8,}/,          el: document.getElementById('req-length') },
    upper:   { regex: /[A-Z]/,          el: document.getElementById('req-upper') },
    lower:   { regex: /[a-z]/,          el: document.getElementById('req-lower') },
    number:  { regex: /[0-9]/,          el: document.getElementById('req-number') },
    special: { regex: /[^A-Za-z0-9]/,   el: document.getElementById('req-special') }
};

passwordInput.addEventListener('input', () => {
    const val = passwordInput.value;
    let allMet = true;
    Object.keys(reqElements).forEach(key => {
        const req = reqElements[key];
        const isMet = req.regex.test(val);
        if (!isMet) allMet = false;
        const icon = req.el.querySelector('.icon');
        
        if (isMet) {
            req.el.classList.add('hidden');
        } else {
            req.el.classList.remove('hidden');
            req.el.classList.remove('text-emerald-500');
            req.el.classList.add('text-[#64748B]');
            icon.classList.replace('ph-check-circle-fill', 'ph-circle');
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
    
    checkMatch();
});

confirmInput.addEventListener('input', checkMatch);

function checkMatch() {
    const hint = document.getElementById('match-hint');
    const pw = passwordInput.value;
    const cpw = confirmInput.value;
    if (!cpw) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (pw === cpw) {
        hint.textContent = '✅ Passwords match';
        hint.className = 'text-[10px] mt-1.5 font-bold text-green-600';
    } else {
        hint.textContent = '❌ Passwords do not match';
        hint.className = 'text-[10px] mt-1.5 font-bold text-red-500';
    }
}

// Form Submit check
document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
    if (this.querySelector('input[name="change_password"]')) {
        const password = passwordInput.value;
        const requirementsMet = 
            /.{8,}/.test(password) &&
            /[A-Z]/.test(password) &&
            /[a-z]/.test(password) &&
            /[0-9]/.test(password) &&
            /[^A-Za-z0-9]/.test(password);

        if (!requirementsMet) {
            e.preventDefault();
            alert('New password does not meet all security requirements.');
            return;
        }

        if (password !== confirmInput.value) {
            e.preventDefault();
            alert('Passwords do not match.');
        }
    }
});
</script>

<script src="../assets/js/ph-address-selector.js?v=2"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const addrSelect = initPHAddress('');

        const updateHidden = () => {
            const r = document.getElementById(`region`).value;
            const p = document.getElementById(`province`).value;
            const c = document.getElementById(`city`).value;
            const b = document.getElementById(`barangay`).value;
            if (r && p && c && b) {
                document.getElementById(`input-address`).value = `${b}, ${c}, ${p}, ${r}`;
            }
        };

        ['region', 'province', 'city', 'barangay'].forEach(id => {
            document.getElementById(id).addEventListener('change', updateHidden);
        });
    });
</script>

<?php include '../includes/mobile_nav_driver.php'; ?>
</body></html>
