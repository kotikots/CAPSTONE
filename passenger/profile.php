<?php
/**
 * passenger/profile.php
 * View profile + change password.
 */
$requiredRole = 'passenger';
$pageTitle    = 'My Profile';
$currentPage  = 'profile.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions_v2.php';

$uid = $_SESSION['user_id'];
$success = $_SESSION['flash_success'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $captcha  = trim($_POST['captcha'] ?? '');

    // Validate Captcha (Alphanumeric)
    if (strtoupper($captcha) !== strtoupper($_SESSION['captcha_code'] ?? '')) {
        $_SESSION['flash_error'] = 'Security check failed. Please enter the correct captcha text.';
    } else {
        // Validate Password
        $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $userStmt->execute([$uid]);
        $user = $userStmt->fetch();

        if (!password_verify($current, $user['password'])) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $_SESSION['flash_error'] = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uid]);
            $_SESSION['flash_success'] = 'Password changed successfully!';
        }
    }
    
    // Always clear captcha after an attempt so it MUST regenerate
    unset($_SESSION['captcha_code']);
    
    header("Location: profile.php");
    exit;
}

// Fetch full profile
$profileStmt = $pdo->prepare(
    "SELECT full_name, id_number, id_picture, address, contact_number, email,
            region, province, city, barangay,
            emergency_contact_name, emergency_contact_address, 
            ec_region, ec_province, ec_city, ec_barangay,
            discount_type, created_at
     FROM users WHERE id = ?"
);
$profileStmt->execute([$uid]);
$p = $profileStmt->fetch();

// Ride stats
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS rides, COALESCE(SUM(fare_amount),0) AS spent FROM tickets WHERE passenger_id = ?"
);
$statsStmt->execute([$uid]);
$stats = $statsStmt->fetch();

$discountLabels = [
    'none' => ['Regular Passenger', 'bg-slate-100 text-slate-600', 'ph-user'],
    'student' => ['Student Discount', 'bg-blue-100 text-blue-700', 'ph-graduation-cap'],
    'senior' => ['Senior Citizen', 'bg-amber-100 text-amber-700', 'ph-heart'],
    'pwd' => ['PWD Discount', 'bg-purple-100 text-purple-700', 'ph-wheelchair'],
    'teacher' => ['Teacher Discount', 'bg-emerald-100 text-emerald-700', 'ph-chalkboard-teacher'],
    'nurse' => ['Nurse Discount', 'bg-pink-100 text-pink-700', 'ph-first-aid'],
];
$dt = $p['discount_type'] ?? 'none';
$dl = $discountLabels[$dt] ?? $discountLabels['none'];

// Initialize Alphanumeric Captcha Session
if (!isset($_SESSION['captcha_code'])) {
    $_SESSION['captcha_code'] = 'PARE'; // Placeholder for first hit
}

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="mb-8">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">My Profile</h2>
            <p class="text-slate-500 text-sm mt-1">Manage your account information</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- LEFT: Profile Card -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Avatar + Name -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 text-center">
                    <div class="w-24 h-24 rounded-full mx-auto mb-4 overflow-hidden bg-blue-100 flex items-center justify-center border-4 border-blue-200">
                        <?php if ($p['id_picture']): ?>
                        <img src="/PARE/<?= htmlspecialchars($p['id_picture']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                        <i class="ph ph-user text-4xl text-blue-400"></i>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-xl font-black text-slate-800"><?= htmlspecialchars($p['full_name']) ?></h3>
                    <p class="text-slate-400 text-sm mt-1">ID: <?= htmlspecialchars($p['id_number']) ?></p>
                    <div class="mt-3">
                        <span class="inline-flex items-center gap-1.5 <?= $dl[1] ?> text-xs font-bold px-4 py-2 rounded-full">
                            <i class="ph <?= $dl[2] ?>"></i> <?= $dl[0] ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mt-5 pt-5 border-t border-slate-100">
                        <div>
                            <p class="text-2xl font-black text-slate-800"><?= number_format((int)$stats['rides']) ?></p>
                            <p class="text-xs text-slate-400">Total Rides</p>
                        </div>
                        <div>
                            <p class="text-2xl font-black text-emerald-700"><?= peso((float)$stats['spent']) ?></p>
                            <p class="text-xs text-slate-400">Total Spent</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 mt-4">Member since <?= date('M d, Y', strtotime($p['created_at'])) ?></p>
                </div>
            </div>

            <!-- RIGHT: Details + Password -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Personal Info -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-bold text-slate-700 flex items-center gap-2">
                            <i class="ph ph-user-circle text-blue-600"></i> Personal Information
                        </h3>
                        <div id="toolbar-view">
                            <button onclick="toggleEdit(true)" class="flex items-center gap-2 bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-4 py-2 rounded-xl text-xs transition active:scale-95">
                                <i class="ph ph-pencil-simple"></i> Edit Profile
                            </button>
                        </div>
                        <div id="toolbar-edit" class="hidden flex items-center gap-2">
                            <button onclick="toggleEdit(false)" class="bg-slate-100 text-slate-500 hover:bg-slate-200 font-bold px-4 py-2 rounded-xl text-xs transition">
                                Cancel
                            </button>
                            <button onclick="saveProfile()" class="bg-blue-600 text-white hover:bg-blue-500 font-bold px-4 py-2 rounded-xl text-xs transition shadow-lg shadow-blue-500/20 active:scale-95">
                                Save Changes
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php
                        $fields = [
                            ['Full Name', $p['full_name'], 'ph-user', 'full_name', true],
                            ['ID Number', $p['id_number'], 'ph-identification-card', 'id_number', false],
                            ['Contact', $p['contact_number'], 'ph-phone', 'contact_number', true],
                            ['Email', $p['email'] ?: '', 'ph-envelope', 'email', true],
                            ['Address', $p['address'], 'ph-map-pin', 'address', true],
                        ];
                        foreach ($fields as [$label, $value, $icon, $key, $editable]):
                        ?>
                        <div class="<?= $key === 'address' ? 'md:col-span-2' : '' ?>">
                            <label class="block text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-wider"><?= $label ?></label>
                            
                            <!-- View mode -->
                            <div class="view-mode flex items-center gap-2 bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                                <i class="ph <?= $icon ?> text-slate-400 text-lg"></i>
                                <span id="label-<?= $key ?>" class="text-slate-700 text-sm font-semibold truncate"><?= htmlspecialchars($value ?: '—') ?></span>
                            </div>

                            <!-- Edit mode -->
                            <?php if ($editable): ?>
                            <div class="edit-mode hidden relative">
                                <?php if ($key === 'address'): ?>
                                    <div class="space-y-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <select id="region" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="province" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="city" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                            <select id="barangay" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                        </div>
                                        <input type="hidden" id="input-address" value="<?= htmlspecialchars($value) ?>">
                                    </div>
                                <?php else: ?>
                                    <i class="ph <?= $icon ?> absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="<?= $key === 'email' ? 'email' : 'text' ?>" 
                                           id="input-<?= $key ?>"
                                           value="<?= htmlspecialchars($value) ?>"
                                           <?php if ($key === 'contact_number'): ?>
                                           maxlength="11"
                                           pattern="[0-9]{11}"
                                           title="Please enter exactly 11 digits"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                           <?php elseif ($key === 'email'): ?>
                                           pattern=".*@gmail\.com$"
                                           title="Please use a @gmail.com email address"
                                           <?php endif; ?>
                                           class="w-full bg-white border-2 border-blue-100 rounded-xl pl-11 pr-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all">
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="edit-mode hidden flex items-center gap-2 bg-slate-100/50 rounded-xl px-4 py-3 border border-slate-100 opacity-60">
                                <i class="ph <?= $icon ?> text-slate-400 text-lg"></i>
                                <span class="text-slate-500 text-sm font-semibold italic"><?= htmlspecialchars($value) ?> (Read-only)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                        <i class="ph ph-warning-circle text-orange-500"></i> Emergency Contact
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-wider">Contact Person</label>
                            
                            <div class="view-mode flex items-center gap-2 bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                                <i class="ph ph-user text-slate-400 text-lg"></i>
                                <span id="label-emergency_contact_name" class="text-slate-700 text-sm font-semibold truncate"><?= htmlspecialchars($p['emergency_contact_name'] ?: '—') ?></span>
                            </div>
                            
                            <div class="edit-mode hidden space-y-3">
                                <div class="relative">
                                    <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                                    <input type="text" id="input-emergency_contact_name" value="<?= htmlspecialchars($p['emergency_contact_name']) ?>"
                                           class="w-full bg-white border-2 border-blue-100 rounded-xl pl-11 pr-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all">
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-wider">Contact Address</label>
                            
                            <div class="view-mode flex items-center gap-2 bg-slate-50 rounded-xl px-4 py-3 border border-slate-100">
                                <i class="ph ph-map-pin text-slate-400 text-lg"></i>
                                <span id="label-emergency_contact_address" class="text-slate-700 text-sm font-semibold truncate"><?= htmlspecialchars($p['emergency_contact_address'] ?: '—') ?></span>
                            </div>
                            
                            <div class="edit-mode hidden space-y-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <select id="ec_region" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                    <select id="ec_province" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                    <select id="ec_city" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                    <select id="ec_barangay" class="w-full bg-white border-2 border-blue-100 rounded-xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:border-blue-500 transition-all"></select>
                                </div>
                                <input type="hidden" id="input-emergency_contact_address" value="<?= htmlspecialchars($p['emergency_contact_address']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                    <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                        <i class="ph ph-lock text-red-500"></i> Change Password
                    </h3>

                    <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-2xl p-4 mb-5 flex items-center gap-3">
                        <i class="ph ph-check-circle text-green-500 text-xl"></i>
                        <span class="text-green-700 text-sm font-medium"><?= htmlspecialchars($success) ?></span>
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
                            <label class="block text-slate-500 text-xs font-bold mb-1.5 uppercase tracking-wider">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-500 text-xs font-bold mb-1.5 uppercase tracking-wider">New Password</label>
                                <input type="password" name="new_password" required minlength="8" placeholder="Min. 8 characters"
                                       class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm">
                            </div>
                            <div>
                                <label class="block text-slate-500 text-xs font-bold mb-1.5 uppercase tracking-wider">Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Repeat new password"
                                       class="w-full bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 text-sm">
                            </div>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <div class="flex flex-col md:flex-row items-center gap-3">
                                <!-- Captcha Image -->
                                <div class="bg-white border-2 border-slate-200 rounded-xl p-1.5 h-12 w-32 flex items-center justify-center overflow-hidden shrink-0 shadow-sm">
                                    <img id="captchaImg" src="../includes/api_captcha_svg.php?v=<?= time() ?>" alt="Captcha" class="max-h-full opacity-90">
                                </div>

                                <!-- Input Area -->
                                <div class="flex-1 w-full relative">
                                    <input type="text" name="captcha" required placeholder="Type the text" autocomplete="off"
                                           class="w-full bg-white border-2 border-slate-200 focus:border-red-500 rounded-xl px-4 py-2.5 text-sm font-black text-slate-700 uppercase tracking-widest focus:outline-none transition-all shadow-sm">
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="bg-red-600 hover:bg-red-500 text-white font-bold px-6 py-3 rounded-xl shadow-lg hover:shadow-red-500/20 transition active:scale-95">
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
    const fields = ['full_name', 'contact_number', 'email', 'address', 'emergency_contact_name', 'emergency_contact_address'];
    const data = {};
    fields.forEach(f => {
        const el = document.getElementById(`input-${f}`);
        if (el) data[f] = el.value.trim();
    });

    // Add structured address fields
    ['region', 'province', 'city', 'barangay'].forEach(key => {
        data[key] = document.getElementById(key).value;
        data[`ec_${key}`] = document.getElementById(`ec_${key}`).value;
    });

    // Validation
    const contact = data.contact_number;
    const email = data.email;

    if (contact.length !== 11 || !/^\d+$/.test(contact)) {
        window.showToast('Validation Error', 'Contact number must be exactly 11 digits.', 'error');
        return;
    }

    if (email && !email.toLowerCase().endsWith('@gmail.com')) {
        window.showToast('Validation Error', 'Email must end with @gmail.com', 'error');
        return;
    }

    try {
        const res = await fetch('api_update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.success) {
            window.showToast('Profile Updated', 'Your information has been saved successfully.', 'success');
            
            // Update labels locally
            fields.forEach(f => {
                const val = data[f];
                const label = document.getElementById(`label-${f}`);
                if (label) label.textContent = val || '—';
            });

            // Update profile card name
            const cardName = document.querySelector('h3.text-xl.font-black.text-slate-800');
            if (cardName) cardName.textContent = data.full_name;

            toggleEdit(false);
        } else {
            window.showToast('Update Failed', result.message, 'error');
        }
    } catch (err) {
        window.showToast('Network Error', 'Could not reach the server.', 'error');
    }
}
</script>

<script src="../assets/js/ph-address-selector.js?v=2"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const homeSelect = initPHAddress('');
        const ecSelect = initPHAddress('ec_');

        // Logic to update the "combined" hidden fields for legacy support
        const updateHidden = (prefix, targetId) => {
            const r = document.getElementById(`${prefix}region`).value;
            const p = document.getElementById(`${prefix}province`).value;
            const c = document.getElementById(`${prefix}city`).value;
            const b = document.getElementById(`${prefix}barangay`).value;
            if (r && p && c && b) {
                document.getElementById(`input-${targetId}`).value = `${b}, ${c}, ${p}, ${r}`;
            }
        };

        ['region', 'province', 'city', 'barangay'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => updateHidden('', 'address'));
            document.getElementById(`ec_${id}`).addEventListener('change', () => updateHidden('ec_', 'emergency_contact_address'));
        });

        // Pre-populating dropdowns works better if we select them sequentially.
        // For now, these will start empty in Edit mode unless we add a sequential loader in the utility.
    });
</script>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body></html>
