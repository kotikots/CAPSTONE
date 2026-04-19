<?php
/**
 * admin/add_driver.php
 * Admin form to create a new driver with profile picture upload.
 */
$requiredRole = 'admin';
$pageTitle    = 'Add New Driver';
$currentPage  = 'drivers.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName      = trim($_POST['full_name'] ?? '');
    $licenseNumber = trim($_POST['license_number'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $confirm       = $_POST['confirm_password'] ?? '';
    
    $address       = $_POST['address'] ?? '';
    $region        = $_POST['region'] ?? '';
    $province      = $_POST['province'] ?? '';
    $city          = $_POST['city'] ?? '';
    $barangay      = $_POST['barangay'] ?? '';

    // Validation
    if (empty($fullName))      $errors[] = 'Full name is required.';
    elseif (!preg_match("/^[a-zA-Z\s]*$/", $fullName)) $errors[] = 'Full name can only contain letters and spaces.';
    if (empty($licenseNumber)) $errors[] = 'License number is required.';
    if (empty($contactNumber)) $errors[] = 'Contact number is required.';
    elseif (!preg_match('/^[0-9]{11}$/', $contactNumber)) $errors[] = 'Contact number must be exactly 11 digits.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password)) $errors[] = 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password must contain at least one special character.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    // Check duplicate license
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM drivers WHERE license_number = ?");
        $chk->execute([$licenseNumber]);
        if ($chk->fetch()) $errors[] = 'License number already exists.';
    }

    // Check duplicate email
    if (empty($errors) && !empty($email)) {
        $chk = $pdo->prepare("SELECT id FROM drivers WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'Email address is already in use.';
    }

    // Handle Image Upload
    $profilePic = null;
    if (empty($errors) && !empty($_FILES['profile_picture']['name'])) {
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize   = 5 * 1024 * 1024; // 5MB
        $ext       = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $errors[] = 'Profile picture must be a JPG, PNG, or WebP.';
        } elseif ($_FILES['profile_picture']['size'] > $maxSize) {
            $errors[] = 'Profile picture must be smaller than 5MB.';
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = uniqid('dr_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $filename)) {
                $profilePic = 'assets/uploads/' . $filename;
            } else {
                $errors[] = 'Failed to save profile picture.';
            }
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO drivers (full_name, license_number, contact_number, email, password, profile_picture, address, region, province, city, barangay)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $licenseNumber, $contactNumber, ($email ?: null), $hashed, $profilePic, $address, $region, $province, $city, $barangay]);
            $success = true;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>
    
    <main class="flex-1 p-8 bg-slate-50 overflow-auto pb-24 md:pb-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Add New Driver</h2>
                    <p class="text-slate-500 text-sm">Register a new driver for your fleet.</p>
                </div>
                <a href="drivers.php" class="text-slate-500 hover:text-slate-800 transition flex items-center gap-2 text-sm font-bold">
                    <i class="ph ph-arrow-left"></i> Back to List
                </a>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 text-xl text-emerald-600">
                        <i class="ph ph-check-circle"></i>
                    </div>
                    <div>
                        <p class="font-bold">Driver Added Successfully!</p>
                        <p class="text-sm opacity-90">The driver can now log in using their email and license number.</p>
                    </div>
                    <a href="drivers.php" class="ml-auto bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold">View Drivers</a>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 rounded-2xl p-5 mb-8">
                    <p class="font-bold mb-2">Please fix the following:</p>
                    <ul class="list-disc pl-5 text-sm space-y-1">
                        <?php foreach($errors as $err): ?> <li><?= htmlspecialchars($err) ?></li> <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-8">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Full Name -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2">Full Name</label>
                            <input type="text" name="full_name" id="full_name" required placeholder="John Dela Cruz"
                                   pattern="[A-Za-z\s]+" title="Only letters and spaces are allowed"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>

                        <!-- License -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">License Number</label>
                            <input type="text" name="license_number" required placeholder="QR-1234567"
                                   value="<?= htmlspecialchars($_POST['license_number'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>

                        <!-- Contact -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Contact Number</label>
                            <input type="tel" name="contact_number" id="contact_number" required placeholder="09171234567"
                                   maxlength="11" pattern="[0-9]{11}" inputmode="numeric"
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 tracking-widest font-mono">
                            <p id="contact-hint" class="text-xs text-slate-400 mt-1"><span id="contact-count">0</span>/11 digits</p>
                        </div>

                        <!-- Address -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-3 uppercase tracking-wider">Home Address (Accurate)</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                <select id="region" name="region" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm"></select>
                                <select id="province" name="province" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm"></select>
                                <select id="city" name="city" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm"></select>
                                <select id="barangay" name="barangay" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400 text-sm"></select>
                            </div>
                            <input type="hidden" name="address" id="full_address">
                        </div>

                        <!-- Email -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2">Email Address (Optional)</label>
                            <input type="email" name="email" placeholder="driver@email.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>

                        <!-- Profile Pic -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2 uppercase tracking-wider">Profile Picture</label>
                            <div class="flex items-center gap-5">
                                <div id="preview-container" class="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center border-2 border-dashed border-slate-200 overflow-hidden">
                                     <i class="ph ph-user text-2xl text-slate-300"></i>
                                     <img id="preview-img" class="hidden w-full h-full object-cover">
                                </div>
                                <label class="cursor-pointer bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-xl text-sm font-bold text-slate-600 transition">
                                    Choose Photo
                                    <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="hidden" onchange="previewImage(this)">
                                </label>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="md:col-span-2 border-t border-slate-100 my-2"></div>

                        <!-- Password -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2 text-orange-600">Initial Password</label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required minlength="8" placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-orange-400">
                                <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-1')" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-1">
                                    <i id="eye-icon-1" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                            <!-- Strength Bar -->
                            <div class="mt-2">
                                <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                    <div id="strength-bar" class="h-full rounded-full transition-all duration-300 ease-out" style="width: 0%; background-color: #ef4444;"></div>
                                </div>
                                <div class="flex items-center justify-between mt-1.5">
                                    <p id="strength-text" class="text-xs font-bold text-slate-400">Enter a password</p>
                                    <span id="strength-icon" class="text-sm"></span>
                                </div>
                            </div>
                            <!-- Requirements Checklist -->
                            <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1">
                                <p id="req-length" class="text-xs text-slate-400 flex items-center gap-1.5"><i class="ph ph-circle text-[10px]"></i> At least 8 characters</p>
                                <p id="req-upper" class="text-xs text-slate-400 flex items-center gap-1.5"><i class="ph ph-circle text-[10px]"></i> Uppercase letter</p>
                                <p id="req-lower" class="text-xs text-slate-400 flex items-center gap-1.5"><i class="ph ph-circle text-[10px]"></i> Lowercase letter</p>
                                <p id="req-number" class="text-xs text-slate-400 flex items-center gap-1.5"><i class="ph ph-circle text-[10px]"></i> Number</p>
                                <p id="req-special" class="text-xs text-slate-400 flex items-center gap-1.5"><i class="ph ph-circle text-[10px]"></i> Special character</p>
                            </div>
                        </div>

                        <!-- Confirm -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2">Confirm Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required placeholder="••••••••"
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-orange-400">
                                <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-icon-2')" 
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition p-1">
                                    <i id="eye-icon-2" class="ph ph-eye-slash text-xl"></i>
                                </button>
                            </div>
                            <p id="match-hint" class="text-xs mt-1.5 font-bold hidden"></p>
                        </div>
                    </div>

                    <div class="pt-5">
                        <button type="submit" class="w-full bg-orange-600 hover:bg-orange-500 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-orange-500/30 transition active:scale-95 text-lg">
                            Register Driver
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-img').src = e.target.result;
            document.getElementById('preview-img').classList.remove('hidden');
            document.querySelector('#preview-container i').classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// ─── Full Name: letters and spaces only ───
const nameInput = document.getElementById('full_name');
nameInput.addEventListener('input', function() {
    // Strip non-alpha characters except spaces
    this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
});

// ─── Contact Number: numbers only, 11 digit limit ───
const contactInput = document.getElementById('contact_number');
const contactCount = document.getElementById('contact-count');
const contactHint = document.getElementById('contact-hint');

contactInput.addEventListener('input', function() {
    // Strip non-numeric characters
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
    contactCount.textContent = this.value.length;
    
    if (this.value.length === 11) {
        contactHint.className = 'text-xs text-green-600 mt-1 font-bold';
    } else {
        contactHint.className = 'text-xs text-slate-400 mt-1';
    }
});
// Init count on page load (for re-submitted forms)
contactCount.textContent = contactInput.value.length;

// ─── Show/Hide Password Toggle ───
function togglePasswordVisibility(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'ph ph-eye text-xl';
    } else {
        input.type = 'password';
        icon.className = 'ph ph-eye-slash text-xl';
    }
}

// ─── Password Strength Meter ───
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const strengthBar = document.getElementById('strength-bar');
const strengthText = document.getElementById('strength-text');
const strengthIcon = document.getElementById('strength-icon');

const requirements = {
    length:  { el: document.getElementById('req-length'),  test: pw => pw.length >= 8 },
    upper:   { el: document.getElementById('req-upper'),   test: pw => /[A-Z]/.test(pw) },
    lower:   { el: document.getElementById('req-lower'),   test: pw => /[a-z]/.test(pw) },
    number:  { el: document.getElementById('req-number'),  test: pw => /[0-9]/.test(pw) },
    special: { el: document.getElementById('req-special'), test: pw => /[^A-Za-z0-9]/.test(pw) },
};

passwordInput.addEventListener('input', function() {
    const pw = this.value;
    let score = 0;

    // Check each requirement
    Object.values(requirements).forEach(req => {
        const passed = req.test(pw);
        if (passed) score++;
        req.el.className = passed
            ? 'text-xs text-green-600 flex items-center gap-1.5 font-bold'
            : 'text-xs text-slate-400 flex items-center gap-1.5';
        req.el.querySelector('i').className = passed
            ? 'ph ph-check-circle text-[10px] text-green-500'
            : 'ph ph-circle text-[10px]';
    });

    // Strength levels
    const levels = [
        { max: 0, width: '0%',   color: '#e2e8f0', text: 'Enter a password', icon: '' },
        { max: 1, width: '20%',  color: '#ef4444', text: 'Very weak',        icon: '❌' },
        { max: 2, width: '40%',  color: '#f97316', text: 'Weak',             icon: '❌' },
        { max: 3, width: '60%',  color: '#eab308', text: 'Fair',             icon: '⚠️' },
        { max: 4, width: '80%',  color: '#22c55e', text: 'Strong',           icon: '✅' },
        { max: 5, width: '100%', color: '#16a34a', text: 'Very strong',      icon: '✅' },
    ];

    const level = pw.length === 0 ? levels[0] : levels[Math.min(score, 5)];
    strengthBar.style.width = level.width;
    strengthBar.style.backgroundColor = level.color;
    strengthText.textContent = level.text;
    strengthText.style.color = level.color;
    strengthIcon.textContent = level.icon;

    // Check confirm match
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
        hint.className = 'text-xs mt-1.5 font-bold text-green-600';
    } else {
        hint.textContent = '❌ Passwords do not match';
        hint.className = 'text-xs mt-1.5 font-bold text-red-500';
    }
}
</script>

<script src="../assets/js/ph-address-selector.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const addrSelect = initPHAddress('');

        // Combined string logic for legacy field
        const updateLegacy = () => {
            const r = document.getElementById(`region`).value;
            const p = document.getElementById(`province`).value;
            const c = document.getElementById(`city`).value;
            const b = document.getElementById(`barangay`).value;
            if (r && p && c && b) {
                document.getElementById('full_address').value = `${b}, ${c}, ${p}, ${r}`;
            }
        };

        ['region', 'province', 'city', 'barangay'].forEach(id => {
            document.getElementById(id).addEventListener('change', updateLegacy);
        });
    });
</script>
</body></html>
