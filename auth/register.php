<?php
/**
 * auth/register.php
 * Passenger Registration — 2-Step Flow.
 * Step 1: Upload ID + Mistral OCR scan (required to continue).
 * Step 2: Locked OCR fields + remaining editable fields.
 * PHP backend (password_hash, PDO) is unchanged.
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
        $errors[] = 'ID picture is required. Please go back and re-upload your ID photo.';
    }

    // --- Check duplicate ID number & Email ---
    if (empty($errors)) {
        $chkId = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $chkId->execute([$idNumber]);
        if ($chkId->fetch()) $errors[] = 'ID number is already registered.';

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
/* ── Dark Neon Blue Theme ── */
body {
    background: linear-gradient(135deg, #020617 0%, #0f172a 40%, #1e3a8a 100%) !important;
    color: #ffffff !important;
}
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
.text-slate-700, .text-slate-800 { color: rgba(255,255,255,0.85) !important; }
.text-slate-500, .text-slate-400 { color: rgba(255,255,255,0.45) !important; }
.border-slate-200, .border-slate-300 { border-color: rgba(255,255,255,0.12) !important; }

/* ── Locked field ── */
.locked-field-box {
    background: rgba(255,255,255,0.04);
    border: 1.5px solid rgba(255,255,255,0.10);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: not-allowed;
    user-select: none;
}
.locked-field-box .lock-icon { color: rgba(255,255,255,0.22); font-size: 13px; flex-shrink: 0; }
.locked-field-box .field-value { color: #e2e8f0; font-weight: 700; font-size: 15px; flex: 1; }
.locked-field-box .locked-badge {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    color: rgba(255,255,255,0.28);
    font-size: 9px; font-weight: 800;
    letter-spacing: 0.08em; text-transform: uppercase;
    padding: 2px 7px; border-radius: 999px; flex-shrink: 0;
}
/* Step connector animation */
#step-connector-fill { transition: width 0.5s ease; }
</style>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-6">

    <!-- Bg blobs -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-cyan-400/15 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-2xl">

        <!-- Logo -->
        <div class="text-center mb-5">
            <img src="/PARE/assets/img/logo.png" alt="PARE Logo" class="w-24 h-24 object-contain drop-shadow-2xl mx-auto mb-2">
            <p class="text-blue-200 text-base">Create your passenger account</p>
        </div>

        <?php if (!$success): ?>
        <!-- Step Indicator -->
        <div class="flex items-center justify-center mb-5">
            <div class="flex items-center gap-2" id="step-ind-1">
                <div id="step-circle-1"
                     class="w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-blue-500/40 transition-all duration-300">1</div>
                <span class="text-sm font-bold text-blue-300" id="step-label-1">ID Scan</span>
            </div>
            <div class="relative w-20 mx-3 h-0.5 bg-white/15 rounded-full overflow-hidden">
                <div id="step-connector-fill" class="absolute inset-y-0 left-0 w-0 bg-blue-400 rounded-full"></div>
            </div>
            <div class="flex items-center gap-2 opacity-35 transition-all duration-300" id="step-ind-2">
                <div id="step-circle-2"
                     class="w-9 h-9 rounded-full bg-white/10 border border-white/20 flex items-center justify-center font-black text-white/50 text-sm transition-all duration-300">2</div>
                <span class="text-sm font-bold text-white/40 transition-colors duration-300" id="step-label-2">Your Details</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Card -->
        <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl p-6 sm:p-8">

            <?php if ($success): ?>
            <!-- ── Success ── -->
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

            <!-- PHP Error Banner -->
            <?php if (!empty($errors)): ?>
            <div class="bg-red-500/15 border border-red-400/30 rounded-2xl p-4 mb-5">
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

            <form method="POST" enctype="multipart/form-data" id="register-form">

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 1 — ID Scan                   ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-1" class="<?= !empty($errors) ? 'hidden' : '' ?> space-y-4">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-identification-card"></i> Step 1 of 2 — Scan Your Government ID
                        </p>
                        <p class="text-white/35 text-xs mt-1">Your name and ID number will be extracted and locked automatically</p>
                    </div>

                    <!-- Dropzone -->
                    <label for="id_picture"
                           class="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed border-white/20 rounded-2xl cursor-pointer hover:border-blue-400 hover:bg-blue-500/10 transition-all group">
                        <div id="upload-placeholder" class="flex flex-col items-center gap-2">
                            <div class="w-16 h-16 rounded-2xl bg-blue-500/15 border border-blue-400/30 flex items-center justify-center group-hover:bg-blue-500/25 transition-colors">
                                <i class="ph ph-camera text-3xl text-blue-400 group-hover:text-blue-300 transition-colors"></i>
                            </div>
                            <p class="text-white/55 text-sm font-semibold">Click to upload your ID photo</p>
                            <div class="bg-blue-500/20 border border-blue-400/30 text-blue-200 text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-wider animate-[pulse_2s_ease-in-out_infinite]">
                                JPG or PNG · Max 2MB
                            </div>
                        </div>
                        <img id="upload-preview" src="" alt="Preview" class="hidden h-36 object-contain rounded-xl">
                    </label>
                    <input type="file" name="id_picture" id="id_picture" accept="image/jpeg,image/png,image/webp" class="sr-only">

                    <!-- AI consent -->
                    <div id="ai-consent-notice" class="hidden bg-indigo-500/10 border border-indigo-400/30 rounded-xl p-3 flex gap-2.5 items-start">
                        <i class="ph ph-robot text-lg shrink-0 mt-0.5 text-indigo-400"></i>
                        <p class="text-indigo-200 text-[11px] leading-relaxed">
                            <span class="font-bold text-indigo-300">AI-Powered ID Scan</span> — Your photo will be sent securely to Mistral Pixtral AI to extract your details. It is not stored by Mistral.
                        </p>
                    </div>

                    <!-- Scan button -->
                    <button type="button" id="ocr-btn" onclick="startMistralScan()"
                            class="hidden w-full bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 active:scale-95 text-white font-bold py-3.5 rounded-xl shadow-lg hover:shadow-indigo-500/30 transition-all flex items-center justify-center gap-2">
                        <i class="ph ph-sparkle"></i> Scan ID with Mistral AI
                    </button>

                    <!-- OCR status -->
                    <div id="ocr-status" class="hidden"></div>

                    <!-- Continue button -->
                    <button type="button" id="continue-btn" onclick="goToStep2()"
                            class="hidden w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all flex items-center justify-center gap-2 text-base">
                        Continue <i class="ph ph-arrow-right"></i>
                    </button>

                    <p class="text-center text-white/35 text-sm pt-1">
                        Already have an account?
                        <a href="login.php" class="text-blue-300 font-semibold hover:text-white">Sign in here</a>
                    </p>

                </div><!-- /step-panel-1 -->

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 2 — Complete Your Profile     ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-2" class="<?= !empty($errors) ? '' : 'hidden' ?> space-y-5">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-user-circle"></i> Step 2 of 2 — Complete Your Profile
                        </p>
                        <p class="text-white/35 text-xs mt-1">Fields marked 🔒 are locked from your ID scan and cannot be changed</p>
                    </div>

                    <!-- Hidden inputs submitted with form -->
                    <input type="hidden" name="full_name"     id="full_name_hidden"     value="<?= htmlspecialchars($_POST['full_name']     ?? '') ?>">
                    <input type="hidden" name="id_number"     id="id_number_hidden"     value="<?= htmlspecialchars($_POST['id_number']     ?? '') ?>">
                    <input type="hidden" name="discount_type" id="discount_type_hidden"  value="<?= htmlspecialchars($_POST['discount_type'] ?? 'none') ?>">

                    <!-- ── Locked OCR Card ── -->
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-4 space-y-3">
                        <p class="text-[10px] font-black uppercase tracking-widest text-blue-400/80 flex items-center gap-1.5 mb-2">
                            <i class="ph ph-identification-card"></i> From Your ID Scan
                        </p>

                        <!-- Full Name -->
                        <div>
                            <label class="block text-white/35 text-[10px] font-bold mb-1.5 uppercase tracking-wider">Full Name</label>
                            <div class="locked-field-box" id="display-full-name-box">
                                <i class="ph ph-lock lock-icon"></i>
                                <span class="field-value" id="display-full-name"><?= htmlspecialchars($_POST['full_name'] ?? '—') ?></span>
                                <span class="locked-badge">🔒 Locked</span>
                            </div>
                            <!-- Editable fallback (scan failed / PHP error) -->
                            <input type="text" id="full_name_editable"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                   placeholder="Enter your full name as printed on your ID"
                                   class="hidden w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:bg-white">
                        </div>

                        <!-- ID Number -->
                        <div>
                            <label class="block text-white/35 text-[10px] font-bold mb-1.5 uppercase tracking-wider">ID Number</label>
                            <div class="locked-field-box" id="display-id-number-box">
                                <i class="ph ph-lock lock-icon"></i>
                                <span class="field-value font-mono" id="display-id-number"><?= htmlspecialchars($_POST['id_number'] ?? '—') ?></span>
                                <span class="locked-badge">🔒 Locked</span>
                            </div>
                            <input type="text" id="id_number_editable"
                                   value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                                   placeholder="e.g. QR-12-34567890-0"
                                   class="hidden w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:bg-white font-mono">
                        </div>

                        <!-- Discount Type -->
                        <div>
                            <label class="block text-white/35 text-[10px] font-bold mb-1.5 uppercase tracking-wider">Discount Type</label>
                            <div class="locked-field-box">
                                <i class="ph ph-lock lock-icon"></i>
                                <span class="field-value" id="display-discount"><?= htmlspecialchars($_POST['discount_type'] ?? 'none') ?></span>
                                <span class="locked-badge">🔒 Locked</span>
                            </div>
                        </div>
                    </div><!-- /locked card -->

                    <!-- ── Personal Info ── -->
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4">
                            <i class="ph ph-user"></i> Personal Information
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Contact Number -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Number <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <div class="absolute left-4 text-white/50 font-bold border-r border-white/20 pr-3">+63</div>
                                <input type="tel" name="contact_number" id="contact_number"
                                       value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                       placeholder="9171234567" required maxlength="10" pattern="[0-9]{10}"
                                       title="Please enter exactly 10 digits"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl pl-16 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white font-bold">
                            </div>
                        </div>

                        <!-- Home Address -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-semibold mb-3">Home Address <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select id="region"   name="region"   required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="province" name="province" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="city"     name="city"     required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="barangay" name="barangay" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            </div>
                            <input type="hidden" name="address" id="full_address">
                        </div>
                    </div>

                    <!-- ── Emergency Contact ── -->
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4">
                            <i class="ph ph-warning-circle"></i> Emergency Contact
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Person <span class="text-red-500">*</span></label>
                            <input type="text" name="ec_name" id="ec_name"
                                   value="<?= htmlspecialchars($_POST['ec_name'] ?? '') ?>"
                                   placeholder="Full name" required
                                   class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                        </div>
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Emergency Contact Number <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <div class="absolute left-4 text-white/50 font-bold border-r border-white/20 pr-3">+63</div>
                                <input type="tel" name="ec_contact" id="ec_contact"
                                       value="<?= htmlspecialchars($_POST['ec_contact'] ?? '') ?>"
                                       placeholder="9171234567" required maxlength="10" pattern="[0-9]{10}"
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
                                <select id="ec_region"   name="ec_region"   required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="ec_province" name="ec_province" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="ec_city"     name="ec_city"     required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                <select id="ec_barangay" name="ec_barangay" required class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                            </div>
                            <input type="hidden" name="ec_address" id="ec_full_address">
                        </div>
                    </div>

                    <!-- ── Account Credentials ── -->
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4">
                            <i class="ph ph-envelope"></i> Account Credentials
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" id="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="yourname@gmail.com"
                                   pattern=".*@gmail\.com$" required
                                   title="Please use a @gmail.com email address"
                                   class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 items-start">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password" id="password"
                                               placeholder="Min. 8 characters" required minlength="8"
                                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white text-sm">
                                        <button type="button" onclick="togglePw('password','eye-pw')"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition p-1">
                                            <i id="eye-pw" class="ph ph-eye-slash text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirm_password"
                                               placeholder="Repeat password" required
                                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                                        <button type="button" onclick="togglePw('confirm_password','eye-cp')"
                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-blue-400 transition p-1">
                                            <i id="eye-cp" class="ph ph-eye-slash text-xl"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="md:h-full flex flex-col justify-end">
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/10 mt-[26px]">
                                    <p class="text-[11px] font-bold text-white/40 uppercase tracking-widest mb-3">Password Requirements</p>
                                    <div class="grid gap-y-2">
                                        <div id="req-length"  class="flex items-center gap-2 text-slate-400 transition-colors duration-200"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">At least 8 characters</span></div>
                                        <div id="req-upper"   class="flex items-center gap-2 text-slate-400 transition-colors duration-200"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Uppercase letter</span></div>
                                        <div id="req-lower"   class="flex items-center gap-2 text-slate-400 transition-colors duration-200"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Lowercase letter</span></div>
                                        <div id="req-number"  class="flex items-center gap-2 text-slate-400 transition-colors duration-200"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Number</span></div>
                                        <div id="req-special" class="flex items-center gap-2 text-slate-400 transition-colors duration-200"><i class="ph ph-circle text-[10px] icon"></i><span class="text-xs font-semibold">Special character</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy notice -->
                    <div class="bg-blue-500/10 border border-blue-400/20 rounded-2xl p-4 flex gap-3 text-blue-100">
                        <i class="ph ph-shield-check text-2xl shrink-0 text-blue-400"></i>
                        <div class="text-xs">
                            <p class="font-bold">Data Privacy Notice</p>
                            <p class="opacity-80 leading-relaxed mt-0.5">Your personal information is handled with care and used strictly for transportation services within the PARE system.</p>
                        </div>
                    </div>

                    <!-- Back + Submit -->
                    <div class="flex gap-3 pt-1">
                        <button type="button" onclick="goToStep1()"
                                class="flex-none bg-white/10 hover:bg-white/15 border border-white/20 text-white font-bold px-6 py-4 rounded-2xl transition-all flex items-center gap-2">
                            <i class="ph ph-arrow-left"></i> Back
                        </button>
                        <button type="submit" id="submit-btn"
                                class="flex-1 bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black text-lg py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                            Create Account
                        </button>
                    </div>

                    <p class="text-center text-white/35 text-sm">
                        Already have an account?
                        <a href="login.php" class="text-blue-300 font-semibold hover:text-white">Sign in here</a>
                    </p>

                </div><!-- /step-panel-2 -->

            </form>
            <?php endif; ?>
        </div><!-- /card -->
    </div>
</div>

<script>
// ── State ────────────────────────────────────────────────────
let ocrData    = { full_name: null, id_number: null, id_type: null, discount_type: 'none', confidence: 'low' };
let scanFailed = false;

// ── Step switching ───────────────────────────────────────────
function goToStep2() {
    // Write OCR values into hidden inputs
    document.getElementById('full_name_hidden').value     = ocrData.full_name    || '';
    document.getElementById('id_number_hidden').value     = ocrData.id_number    || '';
    document.getElementById('discount_type_hidden').value  = ocrData.discount_type || 'none';

    // Populate locked display spans
    document.getElementById('display-full-name').textContent  = ocrData.full_name  || '— Not detected';
    document.getElementById('display-id-number').textContent  = ocrData.id_number  || '— Not detected';
    const dLabels = { none:'None (Regular Passenger)', senior:'Senior Citizen', pwd:'PWD', student:'Student', teacher:'Teacher', nurse:'Nurse' };
    document.getElementById('display-discount').textContent   = dLabels[ocrData.discount_type] || 'None (Regular Passenger)';

    // Scan failed? → show editable inputs instead of locked boxes
    if (scanFailed) {
        document.getElementById('display-full-name-box').classList.add('hidden');
        document.getElementById('full_name_editable').classList.remove('hidden');
        document.getElementById('display-id-number-box').classList.add('hidden');
        document.getElementById('id_number_editable').classList.remove('hidden');
    }

    document.getElementById('step-panel-1').classList.add('hidden');
    document.getElementById('step-panel-2').classList.remove('hidden');
    setStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep1() {
    document.getElementById('step-panel-2').classList.add('hidden');
    document.getElementById('step-panel-1').classList.remove('hidden');
    setStep(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setStep(n) {
    const c1   = document.getElementById('step-circle-1');
    const c2   = document.getElementById('step-circle-2');
    const ind2 = document.getElementById('step-ind-2');
    const fill = document.getElementById('step-connector-fill');
    if (n === 1) {
        c1.className = 'w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-blue-500/40 transition-all duration-300';
        c1.innerHTML = '1';
        c2.className = 'w-9 h-9 rounded-full bg-white/10 border border-white/20 flex items-center justify-center font-black text-white/50 text-sm transition-all duration-300';
        ind2.classList.add('opacity-35');
        fill.style.width = '0';
    } else {
        c1.className = 'w-9 h-9 rounded-full bg-emerald-500 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-emerald-500/40 transition-all duration-300';
        c1.innerHTML = '<i class="ph ph-check text-sm"></i>';
        c2.className = 'w-9 h-9 rounded-full bg-blue-500 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-blue-500/40 transition-all duration-300';
        ind2.classList.remove('opacity-35');
        fill.style.width = '100%';
    }
}

// ── Image preview + reveal scan button ───────────────────────
document.getElementById('id_picture').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('upload-placeholder').classList.add('hidden');
        const prev = document.getElementById('upload-preview');
        prev.src = e.target.result;
        prev.classList.remove('hidden');
        document.getElementById('ai-consent-notice').classList.remove('hidden');
        document.getElementById('ocr-btn').classList.remove('hidden');
        document.getElementById('ocr-status').classList.add('hidden');
        document.getElementById('ocr-status').innerHTML = '';
        document.getElementById('continue-btn').classList.add('hidden');
        scanFailed = false;
        ocrData = { full_name: null, id_number: null, id_type: null, discount_type: 'none', confidence: 'low' };
    };
    reader.readAsDataURL(file);
});

// ── Mistral OCR ──────────────────────────────────────────────
async function startMistralScan() {
    const fileInput = document.getElementById('id_picture');
    const statusEl  = document.getElementById('ocr-status');
    const scanBtn   = document.getElementById('ocr-btn');

    if (!fileInput.files || !fileInput.files[0]) { alert('Please upload your ID photo first.'); return; }

    const file = fileInput.files[0];
    const origHtml = scanBtn.innerHTML;

    scanBtn.disabled = true;
    scanBtn.innerHTML = `<span class="inline-flex items-center gap-2"><svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>Mistral AI is reading your ID...</span>`;
    statusEl.classList.remove('hidden');
    statusEl.innerHTML = `<div class="flex items-center gap-3 bg-indigo-500/10 border border-indigo-400/20 rounded-xl px-4 py-3 text-indigo-300 text-xs font-semibold animate-pulse"><i class="ph ph-robot text-base"></i>Analyzing your ID with Mistral Pixtral AI... please wait</div>`;

    try {
        const base64   = await fileToBase64(file);
        const mimeType = file.type || 'image/jpeg';
        const resp     = await fetch('/PARE/auth/api_mistral_ocr.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_base64: base64, mime_type: mimeType })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.error || 'Unknown error from Mistral.');

        ocrData    = { full_name: data.full_name||null, id_number: data.id_number||null, id_type: data.id_type||null, discount_type: data.discount_type||'none', confidence: data.confidence||'low' };
        scanFailed = false;

        const cc = { high:'emerald', medium:'amber', low:'red' }[data.confidence]||'slate';
        const ci = { high:'✅', medium:'⚠️', low:'❌' }[data.confidence]||'❓';
        const cl = { high:'High Confidence', medium:'Medium Confidence', low:'Low Confidence' }[data.confidence]||'';
        const itb = data.id_type ? `<span class="bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">${data.id_type}</span>` : '';

        statusEl.innerHTML = `
            <div class="rounded-2xl overflow-hidden border border-emerald-400/30 shadow-lg">
                <div class="bg-emerald-500/15 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2"><i class="ph ph-sparkle text-emerald-400 text-lg"></i><span class="text-emerald-300 font-black text-sm">ID Scanned Successfully!</span></div>
                    <div class="flex items-center gap-1.5">${itb}<span class="bg-${cc}-500/20 border border-${cc}-400/30 text-${cc}-300 text-[10px] font-black px-2 py-0.5 rounded-full">${ci} ${cl}</span></div>
                </div>
                <div class="bg-white/5 px-4 py-3 space-y-2">
                    <div class="flex gap-3 items-center"><span class="text-white/40 text-xs w-20 shrink-0">Full Name</span><span class="text-white font-bold text-sm flex-1">${data.full_name||'<span class="text-white/30 italic">Not detected</span>'}</span></div>
                    <div class="flex gap-3 items-center"><span class="text-white/40 text-xs w-20 shrink-0">ID Number</span><span class="text-white font-bold text-sm font-mono flex-1">${data.id_number||'<span class="text-white/30 italic">Not detected</span>'}</span></div>
                    <div class="flex gap-3 items-center"><span class="text-white/40 text-xs w-20 shrink-0">Discount</span><span class="text-white font-bold text-sm capitalize flex-1">${data.discount_type!=='none'?data.discount_type:'None (Regular)'}</span></div>
                </div>
                <div class="bg-white/3 px-4 py-2.5 flex items-center gap-2">
                    <i class="ph ph-lock text-white/30 text-sm"></i>
                    <p class="text-white/40 text-[11px]">These details will be <strong>locked</strong> on the next step and cannot be changed.</p>
                </div>
            </div>`;

        scanBtn.classList.add('hidden');
        document.getElementById('ai-consent-notice').classList.add('hidden');
        const cb = document.getElementById('continue-btn');
        cb.classList.remove('hidden');
        cb.innerHTML = 'Continue <i class="ph ph-arrow-right"></i>';

    } catch (err) {
        console.error('Mistral scan error:', err);
        scanFailed = true;
        statusEl.innerHTML = `
            <div class="bg-red-500/10 border border-red-400/30 rounded-xl px-4 py-3 flex items-start gap-2.5">
                <i class="ph ph-warning-circle text-red-400 text-base shrink-0 mt-0.5"></i>
                <div>
                    <p class="text-red-300 font-bold text-xs">AI Scan Failed</p>
                    <p class="text-red-400/80 text-[11px] mt-0.5">${err.message||'Could not read the ID. Try a clearer, well-lit photo.'}</p>
                    <p class="text-white/40 text-[11px] mt-2">You can still continue and fill in your details manually.</p>
                </div>
            </div>`;
        scanBtn.disabled = false;
        scanBtn.innerHTML = origHtml;
        const cb = document.getElementById('continue-btn');
        cb.classList.remove('hidden');
        cb.innerHTML = 'Continue Manually <i class="ph ph-arrow-right"></i>';
    }
}

function fileToBase64(file) {
    return new Promise((res, rej) => {
        const r = new FileReader();
        r.onload  = e => res(e.target.result.split(',')[1]);
        r.onerror = rej;
        r.readAsDataURL(file);
    });
}

// ── Form submit ──────────────────────────────────────────────
document.getElementById('register-form').addEventListener('submit', function (e) {
    // If scan failed: copy editable inputs → hidden inputs
    if (scanFailed) {
        const n = document.getElementById('full_name_editable').value.trim();
        const i = document.getElementById('id_number_editable').value.trim();
        if (!n) { e.preventDefault(); alert('Please enter your full name.'); return; }
        if (!i) { e.preventDefault(); alert('Please enter your ID number.'); return; }
        document.getElementById('full_name_hidden').value = n;
        document.getElementById('id_number_hidden').value = i;
    }
    // Password checks
    const pw = document.getElementById('password').value;
    const ok = /.{8,}/.test(pw) && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw);
    if (!ok) { e.preventDefault(); alert('Password does not meet all requirements.'); return; }
    if (pw !== document.getElementById('confirm_password').value) { e.preventDefault(); alert('Passwords do not match.'); return; }
});

// ── Password strength ────────────────────────────────────────
const pwInput = document.getElementById('password');
const reqs = {
    length:  { re: /.{8,}/,        el: document.getElementById('req-length') },
    upper:   { re: /[A-Z]/,        el: document.getElementById('req-upper') },
    lower:   { re: /[a-z]/,        el: document.getElementById('req-lower') },
    number:  { re: /[0-9]/,        el: document.getElementById('req-number') },
    special: { re: /[^A-Za-z0-9]/, el: document.getElementById('req-special') }
};
pwInput.addEventListener('input', () => {
    const v = pwInput.value;
    Object.values(reqs).forEach(({ re, el }) => {
        const met  = re.test(v);
        const icon = el.querySelector('.icon');
        el.classList.toggle('text-emerald-500', met);
        el.classList.toggle('text-slate-400', !met);
        icon.className = `ph ${met ? 'ph-check-circle-fill' : 'ph-circle'} text-[10px] icon`;
    });
});

// ── Toggle password visibility ────────────────────────────────
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('ph-eye-slash', inp.type === 'password');
    icon.classList.toggle('ph-eye',       inp.type === 'text');
}

// ── PHP error: auto-show step 2 in editable mode ─────────────
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', () => {
    setStep(2);
    scanFailed = true;
    document.getElementById('display-full-name-box').classList.add('hidden');
    document.getElementById('full_name_editable').classList.remove('hidden');
    document.getElementById('display-id-number-box').classList.add('hidden');
    document.getElementById('id_number_editable').classList.remove('hidden');
});
<?php endif; ?>
</script>

<!-- Address Selection -->
<script src="../assets/js/ph-address-selector.js?v=3"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const homeSelect = initPHAddress('');
    const ecSelect   = initPHAddress('ec_');

    const updateLegacy = (prefix, targetId) => {
        const r = document.getElementById(`${prefix}region`).value;
        const p = document.getElementById(`${prefix}province`).value;
        const c = document.getElementById(`${prefix}city`).value;
        const b = document.getElementById(`${prefix}barangay`).value;
        if (r && p && c && b) document.getElementById(targetId).value = `${b}, ${c}, ${p}, ${r}`;
    };

    ['region', 'province', 'city', 'barangay'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            updateLegacy('', 'full_address');
            if (document.getElementById('sync_address').checked) syncAddr();
        });
        document.getElementById(`ec_${id}`).addEventListener('change', () => updateLegacy('ec_', 'ec_full_address'));
    });

    const syncAddr = async () => {
        await ecSelect.setValues({
            region:   document.getElementById('region').value,
            province: document.getElementById('province').value,
            city:     document.getElementById('city').value,
            barangay: document.getElementById('barangay').value
        });
        updateLegacy('ec_', 'ec_full_address');
    };

    document.getElementById('sync_address').addEventListener('change', function () {
        if (this.checked) syncAddr();
    });
});
</script>

</body>
</html>
