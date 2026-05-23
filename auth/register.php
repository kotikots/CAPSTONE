<?php
/**
 * auth/register.php
 * Passenger Registration – Full fields with ID photo upload.
 * STEP 3 + 4: Tailwind UI + PHP backend (password_hash, PDO prepared statements).
 */
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /PARE/passenger/dashboard.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Sanitize & validate inputs ---
    $fullName   = trim($_POST['full_name']   ?? '');
    $idNumber   = trim($_POST['id_number']   ?? '');
    $address    = trim($_POST['address']     ?? ''); // Legacy/Combined
    $region     = $_POST['region']           ?? '';
    $province   = $_POST['province']         ?? '';
    $city       = $_POST['city']             ?? '';
    $barangay   = $_POST['barangay']         ?? '';

    $contactRaw = trim($_POST['contact_number'] ?? '');
    $contact    = '+63' . $contactRaw;
    $ecName     = trim($_POST['ec_name']     ?? '');
    $ecContactRaw = trim($_POST['ec_contact'] ?? '');
    $ecContact    = '+63' . $ecContactRaw;
    $ecAddress  = trim($_POST['ec_address']  ?? ''); // Legacy/Combined
    $ecRegion   = $_POST['ec_region']        ?? '';
    $ecProvince = $_POST['ec_province']      ?? '';
    $ecCity     = $_POST['ec_city']          ?? '';
    $ecBarangay = $_POST['ec_barangay']      ?? '';
    $email      = trim($_POST['email']       ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $discountType = $_POST['discount_type']  ?? 'none';

    if (empty($fullName))  $errors[] = 'Full name is required.';
    if (empty($idNumber))  $errors[] = 'ID number is required.';
    if (empty($region) || empty($province) || empty($city) || empty($barangay)) {
        $errors[] = 'Full home address is required.';
    }
    if (empty($contactRaw)) $errors[] = 'Contact number is required.';
    elseif (!preg_match('/^[0-9]{10}$/', $contactRaw)) $errors[] = 'Contact number must be exactly 10 digits (e.g. 9123456789).';
    
    if (empty($ecName))    $errors[] = 'Emergency contact name is required.';
    if (empty($ecContactRaw)) $errors[] = 'Emergency contact number is required.';
    elseif (!preg_match('/^[0-9]{10}$/', $ecContactRaw)) $errors[] = 'Emergency contact number must be exactly 10 digits.';
    if (empty($ecAddress)) $errors[] = 'Emergency contact address is required.';
    
    if (!empty($email) && !str_ends_with(strtolower($email), '@gmail.com')) {
        $errors[] = 'Email must end with @gmail.com';
    }

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    // --- ID Picture Upload ---
    $picturePath = null;
    if (!empty($_FILES['id_picture']['name'])) {
        $allowed   = ['jpg', 'jpeg', 'png'];
        $maxSize   = 2 * 1024 * 1024; // 2MB
        $ext       = strtolower(pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION));
        $mimeTypes = ['image/jpeg', 'image/png'];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime      = finfo_file($finfo, $_FILES['id_picture']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($ext, $allowed) || !in_array($mime, $mimeTypes)) {
            $errors[] = 'ID picture must be a JPG or PNG image.';
        } elseif ($_FILES['id_picture']['size'] > $maxSize) {
            $errors[] = 'ID picture must be smaller than 2MB.';
        } elseif ($_FILES['id_picture']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error. Please try again.';
        } else {
            $uploadDir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $safeFilename = uniqid('id_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['id_picture']['tmp_name'], $uploadDir . $safeFilename)) {
                $picturePath = 'assets/uploads/' . $safeFilename;
            } else {
                $errors[] = 'Failed to save ID picture. Check folder permissions.';
            }
        }
    } else {
        $errors[] = 'ID picture is required.';
    }

    // --- Check duplicate ID number & Email ---
    if (empty($errors)) {
        // ID Check
        $chkId = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $chkId->execute([$idNumber]);
        if ($chkId->fetch()) $errors[] = 'ID number is already registered.';

        // Email Check (only if email was provided)
        if (!empty($email)) {
            $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chkEmail->execute([$email]);
            if ($chkEmail->fetch()) $errors[] = 'Email address is already registered.';
        }
    }

    // --- Insert if no errors ---
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users
                (full_name, id_number, id_picture, address, region, province, city, barangay, 
                 contact_number, emergency_contact_name, emergency_contact_number, emergency_contact_address, 
                 ec_region, ec_province, ec_city, ec_barangay, email, password, role, discount_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'passenger', ?)
        ");
        $stmt->execute([
            $fullName, $idNumber, $picturePath, $address, $region, $province, $city, $barangay,
            $contact, $ecName, $ecContact, $ecAddress,
            $ecRegion, $ecProvince, $ecCity, $ecBarangay,
            ($email !== '' ? $email : null),
            $hashed,
            $discountType
        ]);
        $success = true;
    }
}
?>
<?php $pageTitle = 'Create Account'; include '../includes/header.php'; ?>

<style>
/* Dark Neon Blue Theme — Registration Page */
body {
    background: linear-gradient(135deg, #020617 0%, #0f172a 40%, #1e3a8a 100%) !important;
    color: #ffffff !important;
}
/* All form inputs & selects */
input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]),
select, textarea {
    background-color: rgba(255,255,255,0.08) !important;
    color: #ffffff !important;
    border-color: rgba(255,255,255,0.15) !important;
}
input::placeholder, textarea::placeholder { color: rgba(255,255,255,0.3) !important; }
input:not([type="checkbox"]):not([type="radio"]):focus, select:focus, textarea:focus {
    background-color: rgba(255,255,255,0.12) !important;
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56,189,248,0.2) !important;
    outline: none !important;
}
input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 30px #0f172a inset !important;
    -webkit-text-fill-color: #ffffff !important;
}
select option { background-color: #0f172a; color: #ffffff; }
/* Text overrides */
.text-slate-700, .text-slate-800 { color: rgba(255,255,255,0.85) !important; }
.text-slate-500, .text-slate-400 { color: rgba(255,255,255,0.45) !important; }
.border-slate-200, .border-slate-300 { border-color: rgba(255,255,255,0.12) !important; }
</style>

<div class="min-h-screen flex items-center justify-center p-6">

    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-cyan-400/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex flex-col items-center gap-2 mb-4">
                <img src="/PARE/assets/img/logo.png" alt="PARE Logo" class="w-40 h-40 object-contain drop-shadow-2xl">
            </div>
            <p class="text-blue-200 text-lg">Create your passenger account</p>
        </div>

        <!-- Card -->
        <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl p-8">

            <?php if ($success): ?>
            <!-- Success State -->
            <div class="text-center py-8">
                <div class="w-20 h-20 bg-emerald-500/20 border border-emerald-400/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-check-circle text-5xl text-emerald-400"></i>
                </div>
                <h2 class="text-2xl font-black text-white mb-2">Account Created!</h2>
                <p class="text-blue-200 mb-8">You can now log in to book your rides.</p>
                <a href="login.php" class="inline-block bg-blue-500 hover:bg-blue-400 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                    Go to Login →
                </a>
            </div>
            <?php else: ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="bg-red-500/15 border border-red-400/30 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <i class="ph ph-warning-circle text-red-400 text-xl"></i>
                    <span class="text-red-300 font-bold text-sm">Please fix the following:</span>
                </div>
                <ul class="space-y-1 pl-6">
                    <?php foreach ($errors as $err): ?>
                    <li class="text-red-300 text-sm list-disc"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" enctype="multipart/form-data" class="space-y-5" id="register-form">

                <!-- Section: Personal Info -->
                <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                    <i class="ph ph-user-circle"></i> Personal Information
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name -->
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="full_name" id="full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               placeholder="e.g. Juan Dela Cruz"
                               required
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:bg-white">
                    </div>

                    <!-- ID Number -->
                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">ID Number <span class="text-red-500">*</span></label>
                        <input type="text" name="id_number" id="id_number"
                               value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                               placeholder="e.g. QR-1234567"
                               required
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:bg-white">
                    </div>

                    <!-- Contact Number -->
                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Number <span class="text-red-500">*</span></label>
                        <div class="relative flex items-center">
                            <div class="absolute left-4 text-white/50 font-bold border-r border-white/20 pr-3">+63</div>
                            <input type="tel" name="contact_number" id="contact_number"
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                   placeholder="9171234567"
                                   required
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   title="Please enter exactly 10 digits"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                                   class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl pl-16 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white font-bold">
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 text-sm font-semibold mb-3">Home Address <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select id="region" name="region" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="province" name="province" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="city" name="city" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="barangay" name="barangay" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                        </div>
                        <input type="hidden" name="address" id="full_address">
                    </div>
                </div>

                <!-- Discount Type -->
                <div class="md:col-span-2">
                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Discount Type</label>
                    <select name="discount_type" id="discount_type"
                            class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                        <option value="none" <?= ($_POST['discount_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>None (Regular Passenger)</option>
                        <option value="student" <?= ($_POST['discount_type'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                        <option value="senior" <?= ($_POST['discount_type'] ?? '') === 'senior' ? 'selected' : '' ?>>Senior Citizen</option>
                        <option value="pwd" <?= ($_POST['discount_type'] ?? '') === 'pwd' ? 'selected' : '' ?>>PWD</option>
                        <option value="teacher" <?= ($_POST['discount_type'] ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                        <option value="nurse" <?= ($_POST['discount_type'] ?? '') === 'nurse' ? 'selected' : '' ?>>Nurse</option>
                    </select>
                    <p class="text-slate-400 text-xs mt-1">Select your discount category for fare verification at the kiosk.</p>
                </div>

                <!-- Divider -->
                <div class="border-t border-white/10 pt-4">
                    <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-warning-circle"></i> Emergency Contact
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Person <span class="text-red-500">*</span></label>
                        <input type="text" name="ec_name" id="ec_name"
                               value="<?= htmlspecialchars($_POST['ec_name'] ?? '') ?>"
                               placeholder="Full name"
                               required
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                    </div>
                    <div>
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Emergency Contact Number <span class="text-red-500">*</span></label>
                        <div class="relative flex items-center">
                            <div class="absolute left-4 text-white/50 font-bold border-r border-white/20 pr-3">+63</div>
                            <input type="tel" name="ec_contact" id="ec_contact"
                                   value="<?= htmlspecialchars($_POST['ec_contact'] ?? '') ?>"
                                   placeholder="9171234567"
                                   required
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   title="Please enter exactly 10 digits"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                                   class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl pl-16 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white font-bold">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-slate-700 text-sm font-semibold">Contact Address <span class="text-red-500">*</span></label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" id="sync_address" class="w-4 h-4 rounded border-white/20 text-blue-500 focus:ring-blue-400">
                                <span class="text-xs text-white/50 group-hover:text-blue-400 transition-colors">Same as home address</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <select id="ec_region" name="ec_region" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="ec_province" name="ec_province" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="ec_city" name="ec_city" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            <select id="ec_barangay" name="ec_barangay" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                        </div>
                        <input type="hidden" name="ec_address" id="ec_full_address">
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-white/10 pt-4">
                    <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-identification-card"></i> Account & ID Photo
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Email (required) -->
                    <div class="md:col-span-2">
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="yourname@gmail.com"
                               pattern=".*@gmail\.com$"
                               required
                               title="Please use a @gmail.com email address"
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                    </div>

                    <!-- Password Section Container -->
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-x-6 items-start">
                        <!-- Left Side: Password Inputs -->
                        <div class="space-y-4">
                            <!-- Initial Password -->
                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-1.5">Initial Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="password" name="password" id="password"
                                           placeholder="Min. 8 characters"
                                           required minlength="8"
                                           class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm">
                                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-password')" 
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition p-1">
                                        <i id="eye-password" class="ph ph-eye-slash text-xl"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           placeholder="Repeat password"
                                           required
                                           class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                                    <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-confirm')" 
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition p-1">
                                        <i id="eye-confirm" class="ph ph-eye-slash text-xl"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Checklist -->
                        <div class="md:h-full flex flex-col justify-end">
                            <div class="p-4 bg-white/5 rounded-2xl border border-white/10 mt-[26px]">
                                <p class="text-[11px] font-bold text-white/40 uppercase tracking-widest mb-3">Password Combination Status</p>
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

                        <!-- ID Picture Upload -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">ID Picture <span class="text-red-500">*</span></label>
                            <div class="flex flex-col gap-3">
                                <label for="id_picture"
                                       class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-white/20 rounded-2xl cursor-pointer hover:border-blue-400 hover:bg-blue-500/10 transition-all group">
                                    <div id="upload-placeholder" class="flex flex-col items-center">
                                        <i class="ph ph-camera text-3xl text-blue-400 group-hover:text-blue-300 mb-2"></i>
                                        <p class="text-white/50 text-sm mb-2">Click to upload ID photo</p>
                                        <div class="bg-blue-500/20 border border-blue-400/30 text-blue-200 text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-wider shadow-sm animate-[pulse_2s_ease-in-out_infinite]">
                                            <i class="ph-shield-check inline-block mr-1"></i> Tanging JPG o PNG lang ang pwede, maximum 2MB
                                        </div>
                                    </div>
                                    <img id="upload-preview" src="" alt="Preview" class="hidden h-24 object-contain rounded-lg">
                                </label>
                                <input type="file" name="id_picture" id="id_picture" accept="image/*" required class="sr-only">
                                
                                <!-- Standalone Verify Button -->
                                <button type="button" id="ocr-btn" onclick="startIdVerification()" 
                                        class="hidden w-full bg-slate-800 text-white font-bold py-3 rounded-xl hover:bg-slate-700 transition flex items-center justify-center gap-2">
                                    <i class="ph ph-magnifying-glass"></i> Verify ID Details
                                </button>
                                
                                <!-- ID Verification Status -->
                                <div id="ocr-status" class="hidden text-center text-xs text-amber-300"></div>
                            </div>
                        </div>
                    </div>

                <!-- Data Privacy Disclosure -->
                <div class="bg-blue-500/10 border border-blue-400/20 rounded-2xl p-4 flex gap-3 text-blue-100">
                    <i class="ph ph-shield-check text-2xl shrink-0 text-blue-400"></i>
                    <div class="text-xs">
                        <p class="font-bold">Data Privacy Notice</p>
                        <p class="opacity-80 leading-relaxed mt-0.5">
                            We value your data privacy. Your personal information is handled with care and used strictly for transportation services within the PARE system.
                        </p>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="submit-btn"
                        class="w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black text-lg py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all mt-2">
                    Create Account
                </button>

                <p class="text-center text-white/40 text-sm">
                    Already have an account?
                    <a href="login.php" class="text-blue-300 font-semibold hover:text-white">Sign in here</a>
                </p>

            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
    // Live image preview on file select
    document.getElementById('id_picture').addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('upload-placeholder').classList.add('hidden');
            const preview = document.getElementById('upload-preview');
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            // Show OCR button after upload
            document.getElementById('ocr-btn').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    // Form submit — Ensures verification passed
    let verificationPassed = false;
    
    document.getElementById('register-form')?.addEventListener('submit', function(e) {
        // --- 1. Check Password Requirements ---
        const password = document.getElementById('password').value;
        const requirementsMet = 
            /.{8,}/.test(password) &&
            /[A-Z]/.test(password) &&
            /[a-z]/.test(password) &&
            /[0-9]/.test(password) &&
            /[^A-Za-z0-9]/.test(password);

        if (!requirementsMet) {
            e.preventDefault();
            alert('Your password does not meet all security requirements.');
            document.getElementById('password').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        // --- 2. Check Password Match ---
        const cp = document.getElementById('confirm_password').value;
        if (password !== cp) {
            e.preventDefault();
            alert('Passwords do not match. Please check and try again.');
            return;
        }

        // --- 3. Check OCR Verification ---
        if (!verificationPassed) {
            e.preventDefault();
            const statusEl = document.getElementById('ocr-status');
            statusEl.classList.remove('hidden');
            statusEl.innerHTML = `<p class="text-amber-500 font-bold">⚠️ Please verify your ID above before creating an account.</p>`;
            document.getElementById('ocr-btn').parentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
    });

    // --- Password Strength Real-time Validation ---
    const passwordInput = document.getElementById('password');
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

    async function startIdVerification() {
        const preview = document.getElementById('upload-preview');
        const typedName = document.getElementById('full_name').value.trim();
        const typedId = document.getElementById('id_number').value.trim();
        const statusEl = document.getElementById('ocr-status');
        const verifyBtn = document.getElementById('ocr-btn');
        const submitBtn = document.getElementById('submit-btn');

        if (!typedName || !typedId) {
            alert('Please enter your full name and ID number before verifying.');
            return;
        }

        if (!preview.src || preview.classList.contains('hidden')) {
            alert('Please upload your ID photo first.');
            return;
        }

        // Show loading state
        const originalBtnText = verifyBtn.innerHTML;
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Checking...';
        statusEl.classList.remove('hidden');
        statusEl.textContent = 'Scanning your ID...';

        try {
            const processedImage = await preprocessImage(preview.src);
            const result = await Tesseract.recognize(processedImage, 'eng', {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        statusEl.textContent = `Scanning: ${Math.round(m.progress * 100)}%`;
                    }
                }
            });

            const ocrText = result.data.text;
            const nameScore = bestMatchScore(typedName, ocrText, false);
            const idScore = bestMatchScore(typedId, ocrText, true);
            const namePct = Math.round(nameScore * 100);
            const idPct = Math.round(idScore * 100);
            const overallPass = nameScore >= 1.0 && idScore >= 1.0;

            const nameIcon = nameScore >= 1.0 ? '✅' : '❌';
            const idIcon = idScore >= 1.0 ? '✅' : '❌';
            const nameColor = nameScore >= 1.0 ? 'emerald' : 'red';
            const idColor = idScore >= 1.0 ? 'emerald' : 'red';

            if (overallPass) {
                // ✅ Passed — show success
                statusEl.innerHTML = `
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-center shadow-sm">
                        <p class="text-emerald-800 font-black text-lg">✅ ID Verified 100%!</p>
                        <p class="text-emerald-600 text-[11px] mb-3 font-medium">Your details exactly match the document. You can now proceed.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-white border border-emerald-100 rounded-lg p-2 shadow-sm">
                                <p class="text-[10px] text-emerald-500 uppercase font-black tracking-wider">Name Match</p>
                                <p class="text-emerald-700 font-black text-xl leading-tight">MATCHED</p>
                            </div>
                            <div class="bg-white border border-emerald-100 rounded-lg p-2 shadow-sm">
                                <p class="text-[10px] text-emerald-500 uppercase font-black tracking-wider">ID Match</p>
                                <p class="text-emerald-700 font-black text-xl leading-tight">MATCHED</p>
                            </div>
                        </div>
                    </div>
                `;
                verificationPassed = true;
                verifyBtn.classList.add('hidden'); // Hide verify button on success
                submitBtn.innerHTML = 'Create My Account';
                submitBtn.classList.remove('opacity-70');
            } else {
                // ❌ Failed
                statusEl.innerHTML = `
                    <div class="space-y-3 mt-1">
                        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center shadow-sm">
                            <p class="text-red-700 font-black text-lg">❌ Verification Failed</p>
                            <p class="text-red-500 text-[11px] mt-1 font-medium">Details must match 100%. Please check your inputs or photo.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-white border border-${nameColor}-200 rounded-lg p-3 text-center shadow-sm">
                                <p class="text-[10px] text-${nameColor}-500 font-black uppercase tracking-wider mb-1">Full Name</p>
                                <p class="text-${nameColor}-700 font-black text-xl leading-none">${nameIcon} ${nameScore === 1 ? '100%' : 'NO MATCH'}</p>
                            </div>
                            <div class="bg-white border border-${idColor}-200 rounded-lg p-3 text-center shadow-sm">
                                <p class="text-[10px] text-${idColor}-500 font-black uppercase tracking-wider mb-1">ID Number</p>
                                <p class="text-${idColor}-700 font-black text-xl leading-none">${idIcon} ${idScore === 1 ? '100%' : 'NO MATCH'}</p>
                            </div>
                        </div>
                    </div>
                `;
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalBtnText;
            }

        } catch (err) {
            console.error(err);
            statusEl.innerHTML = `<p class="text-amber-500 text-xs">⚠️ Error scanning image. Please ensure it is a clear JPG or PNG.</p>`;
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = originalBtnText;
        }
    }

    // OCR: Preprocess image for better accuracy
    function preprocessImage(imgSrc) {
        return new Promise((resolve) => {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Scale up small images for better OCR
                const scale = Math.max(1, 1500 / Math.max(img.width, img.height));
                canvas.width = img.width * scale;
                canvas.height = img.height * scale;
                
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                // Convert to grayscale + boost contrast
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                for (let i = 0; i < data.length; i += 4) {
                    // Grayscale
                    const gray = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
                    
                    // Contrast boost
                    const contrast = 1.3; 
                    const factor = (259 * (contrast * 255 + 255)) / (255 * (259 - contrast * 255));
                    let val = factor * (gray - 128) + 128;
                    
                    // Clamping and optional "soft" binarization
                    val = Math.max(0, Math.min(255, val));
                    
                    // Instead of hard 140 threshold, let's just make it very high contrast
                    // This preserves more detail for Tesseract's internal engine
                    data[i] = data[i+1] = data[i+2] = val;
                }
                ctx.putImageData(imageData, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            };
            img.src = imgSrc;
        });
    }

    // ─── Fuzzy string similarity (Dice coefficient) ───
    function similarity(a, b) {
        a = a.toLowerCase().replace(/[^a-z0-9]/g, '');
        b = b.toLowerCase().replace(/[^a-z0-9]/g, '');
        if (a === b) return 1;
        if (a.length < 2 || b.length < 2) return 0;
        const bigrams = new Map();
        for (let i = 0; i < a.length - 1; i++) {
            const bi = a.substring(i, i + 2);
            bigrams.set(bi, (bigrams.get(bi) || 0) + 1);
        }
        let matches = 0;
        for (let i = 0; i < b.length - 1; i++) {
            const bi = b.substring(i, i + 2);
            const count = bigrams.get(bi) || 0;
            if (count > 0) {
                bigrams.set(bi, count - 1);
                matches++;
            }
        }
        return (2 * matches) / (a.length + b.length - 2);
    }

    // Check if a string appears (even partially) anywhere in the OCR text
    function bestMatchScore(needle, ocrText, isIdNumber = false) {
        const cleanNeedle = needle.toLowerCase().replace(/[^a-z0-9]/g, '');
        const cleanText = ocrText.toLowerCase().replace(/[^a-z0-9]/g, '');
        
        if (cleanNeedle.length < 2) return 0;

        // For 100% match, the cleaned needle must be a substring of the cleaned OCR text
        if (cleanText.includes(cleanNeedle)) {
            return 1.0;
        }

        return 0.0;
    }

    // ─── Show/Hide Password Toggle ───
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
</script>

<!-- Address Selection Script -->
<script src="../assets/js/ph-address-selector.js?v=3"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const homeSelect = initPHAddress('');
        const ecSelect = initPHAddress('ec_');

        // Combined string logic for legacy field
        const updateLegacy = (prefix, targetId) => {
            const r = document.getElementById(`${prefix}region`).value;
            const p = document.getElementById(`${prefix}province`).value;
            const c = document.getElementById(`${prefix}city`).value;
            const b = document.getElementById(`${prefix}barangay`).value;
            if (r && p && c && b) {
                document.getElementById(targetId).value = `${b}, ${c}, ${p}, ${r}`;
            }
        };

        ['region', 'province', 'city', 'barangay'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => {
                updateLegacy('', 'full_address');
                if (document.getElementById('sync_address').checked) {
                    syncAddressFields();
                }
            });
            document.getElementById(`ec_${id}`).addEventListener('change', () => updateLegacy('ec_', 'ec_full_address'));
        });

        // Sync Address Logic
        const checkbox = document.getElementById('sync_address');
        const syncAddressFields = async () => {
            const values = {
                region: document.getElementById('region').value,
                province: document.getElementById('province').value,
                city: document.getElementById('city').value,
                barangay: document.getElementById('barangay').value
            };
            
            // Use the new sequential setter to handle cascading async logic
            await ecSelect.setValues(values);
            
            updateLegacy('ec_', 'ec_full_address');
        };

        checkbox.addEventListener('change', function() {
            if (this.checked) {
                syncAddressFields();
                // Disable EC dropdowns while synced? User might want that. Let's just sync once for now.
            }
        });
    });
</script>

</body>
</html>
