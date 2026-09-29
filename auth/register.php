<?php
/**
 * auth/register.php
 * Passenger Registration — 4-Step Flow.
 * Step 0: Account type toggle (Basic vs Discount).
 * Step 1 (new): Email entry + OTP verification.
 * Step 2 (Discount only): Upload ID + Mistral OCR scan.
 * Step 3: Complete profile form.
 * PHP backend blocks final submission if OTP not verified in session.
 */
session_start();
require_once '../config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . '/passenger/dashboard.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $regMode    = $_POST['reg_mode'] ?? 'basic'; // 'basic' or 'discount'
    $fullName   = trim($_POST['full_name']   ?? '');
    $idNumber   = trim($_POST['id_number']   ?? '');
    $address    = trim($_POST['address']     ?? '');
    $region     = $_POST['region']           ?? '';
    $province   = $_POST['province']         ?? '';
    $city       = $_POST['city']             ?? '';
    $barangay   = $_POST['barangay']         ?? '';

    $contactRaw = trim($_POST['contact_number'] ?? '');
    $contact    = $contactRaw;
    $ecName     = trim($_POST['ec_name']     ?? '');
    $ecContactRaw = trim($_POST['ec_contact'] ?? '');
    $ecContact    = $ecContactRaw;
    $ecAddress  = trim($_POST['ec_address']  ?? '');
    $ecRegion   = $_POST['ec_region']        ?? '';
    $ecProvince = $_POST['ec_province']      ?? '';
    $ecCity     = $_POST['ec_city']          ?? '';
    $ecBarangay = $_POST['ec_barangay']      ?? '';
    $email      = trim($_POST['email']       ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $discountType = $_POST['discount_type']  ?? 'none';

    // --- Universal validation ---
    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (empty($region) || empty($province) || empty($city) || empty($barangay)) {
        $errors[] = 'Full home address is required.';
    }
    if (empty($contactRaw)) $errors[] = 'Contact number is required.';
    elseif (!preg_match('/^[0-9]{11}$/', $contactRaw)) $errors[] = 'Contact number must be exactly 11 digits (e.g. 09123456789).';

    // --- Emergency contact: required for discount, optional for basic ---
    if ($regMode === 'discount') {
        if (empty($idNumber))  $errors[] = 'ID number is required for discount accounts.';
        if (empty($ecName))    $errors[] = 'Emergency contact name is required.';
        if (empty($ecContactRaw)) $errors[] = 'Emergency contact number is required.';
        elseif (!preg_match('/^[0-9]{11}$/', $ecContactRaw)) $errors[] = 'Emergency contact number must be exactly 11 digits.';
        if (empty($ecAddress)) $errors[] = 'Emergency contact address is required.';
    } else {
        // Basic: emergency contact is optional, but if partially filled, validate it
        $ecFilled = !empty($ecName) || !empty($ecContactRaw) || !empty($ecAddress);
        if ($ecFilled) {
            if (!empty($ecContactRaw) && !preg_match('/^[0-9]{11}$/', $ecContactRaw)) {
                $errors[] = 'Emergency contact number must be exactly 11 digits.';
            }
        }
        // For basic path, set discount to none always
        $discountType = 'none';
    }

    if (empty($email)) $errors[] = 'Email address is required.';
    elseif (!str_ends_with(strtolower($email), '@gmail.com')) {
        $errors[] = 'Email must end with @gmail.com';
    }

    // --- OTP verification gate (server-side) ---
    $otpVerified      = $_SESSION['reg_otp_verified'] ?? false;
    $otpVerifiedEmail = $_SESSION['reg_otp_email']    ?? '';
    if (!$otpVerified) {
        $errors[] = 'Email verification is required. Please verify your email with the OTP code.';
    } elseif (strtolower($otpVerifiedEmail) !== strtolower($email)) {
        $errors[] = 'The submitted email does not match the verified email. Please re-verify.';
    }

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    // --- ID Picture Upload (required for discount, optional for basic) ---
    $picturePath = null;
    if (!empty($_FILES['id_picture']['name'])) {
        $allowed   = ['jpg', 'jpeg', 'png'];
        $maxSize   = 2 * 1024 * 1024;
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
    } elseif ($regMode === 'discount') {
        $errors[] = 'ID picture is required for discount accounts. Please go back and re-upload your ID photo.';
    }

    // --- Check duplicate Email ---
    if (empty($errors)) {
        if (!empty($email)) {
            $chkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $chkEmail->execute([$email]);
            if ($chkEmail->fetch()) $errors[] = 'Email address is already registered.';
        }
        // Check duplicate ID number only if provided
        if (!empty($idNumber)) {
            $chkId = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
            $chkId->execute([$idNumber]);
            if ($chkId->fetch()) $errors[] = 'ID number is already registered.';
        }
    }

    // --- Insert if no errors ---
    if (empty($errors)) {
        $hashed   = password_hash($password, PASSWORD_BCRYPT);
        $isActive = ($discountType !== 'none') ? 0 : 1;

        // Nullify empty optional fields
        $idNumberVal  = !empty($idNumber)  ? $idNumber  : null;
        $ecNameVal    = !empty($ecName)    ? $ecName    : null;
        $ecContactVal = !empty($ecContact) ? $ecContact : null;
        $ecAddressVal = !empty($ecAddress) ? $ecAddress : null;
        $ecRegionVal  = !empty($ecRegion)  ? $ecRegion  : null;
        $ecProvinceVal= !empty($ecProvince)? $ecProvince: null;
        $ecCityVal    = !empty($ecCity)    ? $ecCity    : null;
        $ecBarangayVal= !empty($ecBarangay)? $ecBarangay: null;

        $stmt = $pdo->prepare("
            INSERT INTO users
                (full_name, id_number, id_picture, address, region, province, city, barangay,
                 contact_number, emergency_contact_name, emergency_contact_number, emergency_contact_address,
                 ec_region, ec_province, ec_city, ec_barangay, email, password, role, discount_type, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'passenger', ?, ?)
        ");
        $stmt->execute([
            $fullName, $idNumberVal, $picturePath, $address, $region, $province, $city, $barangay,
            $contact, $ecNameVal, $ecContactVal, $ecAddressVal,
            $ecRegionVal, $ecProvinceVal, $ecCityVal, $ecBarangayVal,
            ($email !== '' ? $email : null),
            $hashed,
            $discountType,
            $isActive
        ]);

        $success   = true;
        $isPending = ($isActive === 0);
    }
}
?>
<?php $pageTitle = 'Create Account'; include '../includes/header.php'; ?>

<style>
/* ── Theme ── */
body {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%) !important;
    color: #0F172A !important;
}

.register-card {
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 24px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
}

input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="hidden"]),
select, textarea {
    background-color: #F8FAFC !important;
    color: #0F172A !important;
    border: 1px solid #E2E8F0 !important;
}
input::placeholder, textarea::placeholder { color: #94A3B8 !important; }
input:not([type="checkbox"]):not([type="radio"]):focus, select:focus, textarea:focus {
    background-color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.15) !important;
    outline: none !important;
}
input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
    -webkit-box-shadow: 0 0 0 30px #F8FAFC inset !important;
    -webkit-text-fill-color: #0F172A !important;
}
select option { background-color: #FFFFFF; color: #0F172A; }

/* Force all text black inside the card */
.register-card, .register-card * { color: #0F172A; }

/* Specific overrides that should stay colored */
.register-card .text-blue-400,
.register-card [class*="text-blue-4"] { color: #2563EB !important; }
.register-card .text-red-500,
.register-card [class*="text-red-5"] { color: #DC2626 !important; }
.register-card .text-emerald-500,
.register-card .text-emerald-800  { color: #065F46 !important; }
.register-card .text-violet-800   { color: #4C1D95 !important; }
.register-card .text-violet-700   { color: #5B21B6 !important; }
.register-card .text-emerald-700  { color: #047857 !important; }

/* Sub-labels and muted text — all black */
.register-card [class*="text-white"],
.register-card [class*="text-slate-4"],
.register-card [class*="text-slate-5"],
.register-card [class*="text-blue-2"],
.register-card [class*="text-blue-3"] { color: #0F172A !important; }

/* Step dots label */
#step-label-text { color: #0F172A !important; }

/* Buttons with white text must keep white */
#ocr-btn, #continue-btn, #step0-next-btn,
#submit-btn, button[type="submit"],
.bg-blue-500, .bg-blue-500:hover,
[id="ocr-btn"], [id="continue-btn"], [id="step0-next-btn"], [id="submit-btn"] {
    color: #FFFFFF !important;
}
#ocr-btn, #continue-btn, #step0-next-btn, #submit-btn {
    background: #2563EB !important;
    background-image: none !important;
    border: none !important;
}
#ocr-btn:hover, #continue-btn:hover, #step0-next-btn:hover, #submit-btn:hover { background: #1D4ED8 !important; }

/* Skip button */
#skip-id-btn { color: #0F172A !important; background: #F1F5F9 !important; }

/* Back buttons */
button[onclick="backToStep0()"], button[onclick="backToStep1()"] { color: #0F172A !important; }

/* Dropzone */
.border-dashed { border-color: #CBD5E1 !important; background: #F8FAFC !important; }
.border-dashed:hover { border-color: #2563EB !important; background: #EFF6FF !important; }

/* Info panels */
.bg-blue-500\/15 { background: #EFF6FF !important; border-color: #BFDBFE !important; }
.bg-blue-500\/20 { background: #F1F5F9 !important; border-color: #E2E8F0 !important; }
.bg-blue-500\/10 { background: #EFF6FF !important; border-color: #BFDBFE !important; }
.bg-white\/5, .bg-white\/10 { background-color: #F8FAFC !important; }
.bg-white\/3 { background-color: #FFFFFF !important; }
.border-white\/10, .border-white\/20, .border-white\/15, .border-white\/30 { border-color: #E2E8F0 !important; border-width: 1px !important; }
.bg-white\/15 { background-color: #E2E8F0 !important; }
.bg-red-500\/15, .bg-red-500\/10 { background: #FEF2F2 !important; border-color: #FECACA !important; }
.bg-amber-500\/10 { background: #FFF7ED !important; border-color: #FED7AA !important; }

/* Password req box */
#pw-req-box { background: #F8FAFC !important; border-color: #E2E8F0 !important; }
#pw-req-box p, #pw-req-box span, #pw-req-box div { color: #475569 !important; }

/* Locked field */
.locked-field-box {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: not-allowed;
    user-select: none;
}
.locked-field-box .lock-icon { color: #94A3B8; font-size: 13px; flex-shrink: 0; }
.locked-field-box .field-value { color: #0F172A; font-weight: 700; font-size: 15px; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.locked-field-box .locked-badge {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    color: #64748B;
    font-size: 9px; font-weight: 800;
    letter-spacing: 0.08em; text-transform: uppercase;
    padding: 2px 7px; border-radius: 999px; flex-shrink: 0;
}
#step-connector-fill { transition: width 0.5s ease; }

/* ── Account Type Toggle ── */
.acct-toggle-wrapper {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
}
@media (min-width: 640px) {
    .acct-toggle-wrapper { flex-direction: row; }
}
.acct-toggle-option {
    flex: 1;
    position: relative;
    z-index: 1;
    border-radius: 11px;
    padding: 14px 12px;
    cursor: pointer;
    transition: all 0.25s ease;
    text-align: center;
    border: 2px solid transparent;
    background: transparent;
}
.acct-toggle-option.active-basic {
    background: #FFFFFF;
    border-color: #2563EB;
    box-shadow: 0 2px 8px rgba(37,99,235,0.12);
}
.acct-toggle-option.active-discount {
    background: #FFFFFF;
    border-color: #7C3AED;
    box-shadow: 0 2px 8px rgba(124,58,237,0.12);
}
.acct-toggle-option .toggle-icon {
    font-size: 28px;
    margin-bottom: 6px;
    display: block;
    transition: transform 0.2s ease;
}
.acct-toggle-option:hover .toggle-icon { transform: scale(1.1); }
.acct-toggle-option .toggle-title {
    font-weight: 800;
    font-size: 14px;
    color: #0F172A;
    display: block;
    margin-bottom: 3px;
}
.acct-toggle-option .toggle-desc {
    font-size: 11px;
    color: #64748B;
    display: block;
    line-height: 1.4;
}
.acct-toggle-option .toggle-badge {
    display: inline-block;
    margin-top: 6px;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: 999px;
}
.badge-instant {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.badge-pending {
    background: #EDE9FE;
    color: #5B21B6;
    border: 1px solid #C4B5FD;
}
.acct-divider {
    width: 100%; height: 1px;
    background: #E2E8F0; margin: 4px 0;
    align-self: stretch; flex-shrink: 0;
}
@media (min-width: 640px) {
    .acct-divider { width: 1px; height: auto; margin: 8px 0; }
}

/* ── EC optional toggle ── */
.ec-toggle-btn {
    background: #F1F5F9;
    border: 1px dashed #CBD5E1;
    border-radius: 12px;
    padding: 10px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s;
    width: 100%;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
}
.ec-toggle-btn:hover { border-color: #2563EB; color: #2563EB; background: #EFF6FF; }
.ec-panel { overflow: hidden; transition: max-height 0.35s ease, opacity 0.25s ease; max-height: 0; opacity: 0; }
.ec-panel.open { max-height: 600px; opacity: 1; }

/* Step indicator */
.step-dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: #CBD5E1; transition: all 0.3s ease;
    display: inline-block;
}
.step-dot.active { background: #2563EB; transform: scale(1.3); }
.step-dot.done { background: #10B981; }
</style>

<div class="min-h-screen flex items-center justify-center p-4 sm:p-6">

    <div class="relative w-full max-w-2xl">

        <!-- Logo -->
        <div class="text-center mb-5 mt-4">
            <img src="<?= BASE_PATH ?>/assets/img/logo.png?v=2" alt="PARE Logo" class="w-24 h-24 object-contain drop-shadow-md mx-auto mb-2">
            <h1 class="text-2xl font-black text-[#0F172A] tracking-tight">Create your passenger account</h1>
        </div>

        <?php if (!$success): ?>
        <!-- Step Dots Indicator -->
        <div class="flex items-center justify-center gap-2 mb-5" id="step-dots-bar">
            <span class="step-dot active" id="dot-0"></span>
            <span class="step-dot" id="dot-1"></span>
            <span class="step-dot" id="dot-2"></span>
            <span class="step-dot" id="dot-3"></span>
            <span class="text-xs text-slate-400 ml-2 font-semibold" id="step-label-text">Choose account type</span>
        </div>
        <?php endif; ?>

        <!-- Card -->
        <div class="register-card p-6 sm:p-8">

            <?php if ($success): ?>
            <!-- ── Success ── -->
            <div class="text-center py-8">
                <div class="w-20 h-20 bg-emerald-500/20 border border-emerald-400/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-check-circle text-5xl text-emerald-400"></i>
                </div>
                <h2 class="text-2xl font-black text-white mb-2">Account Created!</h2>

                <?php if (!empty($isPending) && $isPending): ?>
                <div class="bg-amber-500/20 border border-amber-400/30 rounded-xl p-4 mb-6 inline-block">
                    <p class="text-amber-900 text-sm font-bold">Your discount application is pending verification by the admin.</p>
                    <p class="text-amber-800 text-xs mt-1">You will receive an email once your account is approved and ready to use.</p>
                </div>
                <?php else: ?>
                <p class="text-blue-200 mb-8">You can now log in to book your rides.</p>
                <?php endif; ?>

                <a href="/" class="inline-block bg-blue-500 hover:bg-blue-400 text-white font-bold px-8 py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
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
                <input type="hidden" name="reg_mode" id="reg_mode_input" value="basic">

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 0 — Account Type Toggle       ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-0" class="<?= !empty($errors) ? 'hidden' : '' ?> space-y-5">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-user-circle"></i> What type of account do you need?
                        </p>
                        <p class="text-slate-400 text-xs mt-1">This determines your registration path</p>
                    </div>

                    <!-- Toggle Cards -->
                    <div class="acct-toggle-wrapper" id="acct-toggle">
                        <!-- Basic Option -->
                        <button type="button" class="acct-toggle-option active-basic" id="opt-basic" onclick="selectAccountType('basic')">
                            <span class="toggle-title">Regular Account</span>
                            <span class="toggle-desc">Standard fare. Quick and simple sign-up with just your basic info.</span>
                            <span class="toggle-badge badge-instant">⚡ Instant Access</span>
                        </button>

                        <div class="acct-divider"></div>

                        <!-- Discount Option -->
                        <button type="button" class="acct-toggle-option" id="opt-discount" onclick="selectAccountType('discount')">
                            <span class="toggle-title">Discount Account</span>
                            <span class="toggle-desc">Senior, PWD, Student & more. Scan your discount ID to apply.</span>
                            <span class="toggle-badge badge-pending">⏳ Pending Approval</span>
                        </button>
                    </div>

                    <!-- Info box (changes based on selection) -->
                    <div id="acct-info-basic" class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex gap-3 items-start">
                        <i class="ph ph-lightning text-emerald-500 text-xl shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-emerald-800 font-bold text-sm">Instant account access</p>
                            <p class="text-emerald-700 text-xs mt-0.5 leading-relaxed">Fill in your basic details and you're good to go — no waiting, no approval needed. You can optionally upload an ID photo for future reference.</p>
                        </div>
                    </div>
                    <div id="acct-info-discount" class="hidden bg-violet-50 border border-violet-200 rounded-2xl p-4 flex gap-3 items-start">
                        <i class="ph ph-identification-card text-violet-500 text-xl shrink-0 mt-0.5"></i>
                        <div>
                            <p class="text-violet-800 font-bold text-sm">Discount application — requires admin approval</p>
                            <p class="text-violet-700 text-xs mt-0.5 leading-relaxed">Upload your government-issued discount card (Senior Citizen, PWD, Student ID, etc.). Our AI will scan it and fill in your details automatically. Your account will be reviewed before you can book rides.</p>
                        </div>
                    </div>

                    <!-- Next button -->
                    <button type="button" id="step0-next-btn" onclick="goToStep1()"
                            class="w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all flex items-center justify-center gap-2 text-base">
                        Continue <i class="ph ph-arrow-right"></i>
                    </button>

                    <p class="text-center text-white/35 text-sm pt-1">
                        Already have an account?
                        <a href="/" class="underline font-black hover:opacity-80 transition-opacity" style="color: #2563EB !important;">Sign in here</a>
                    </p>
                </div><!-- /step-panel-0 -->

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 1 — Email OTP Verification    ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-otp" class="hidden space-y-4">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-envelope-simple"></i> Step 2 of 4 — Verify Your Email
                        </p>
                        <p class="text-slate-400 text-xs mt-1">We'll send a 6-digit code to confirm your email address</p>
                    </div>

                    <!-- Email input row -->
                    <div id="otp-email-row">
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email Address <span class="text-red-500">*</span></label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="email" id="otp-email-input"
                                   placeholder="yourname@gmail.com"
                                   class="flex-1 bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white"
                                   pattern=".*@gmail\.com$" title="Please use a @gmail.com address">
                            <button type="button" id="send-otp-btn" onclick="sendRegOTP()"
                                    class="w-full sm:w-auto shrink-0 bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-bold px-5 py-3 rounded-xl transition-all flex items-center justify-center gap-2">
                                <i class="ph ph-paper-plane-tilt"></i> Send Code
                            </button>
                        </div>
                        <p class="text-slate-400 text-[11px] mt-1.5">Must be a @gmail.com address · We'll send a 6-digit code</p>
                    </div>

                    <!-- OTP status (Moved here so it's visible even when code row is hidden) -->
                    <div id="otp-status"></div>

                    <!-- OTP input row (hidden until sent) -->
                    <div id="otp-code-row" class="hidden space-y-3">
                        <!-- Sent notice -->
                        <div id="otp-sent-notice" class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex gap-2.5 items-start">
                            <i class="ph ph-check-circle text-emerald-500 text-lg shrink-0 mt-0.5"></i>
                            <div>
                                <p class="text-emerald-800 font-bold text-xs">Code sent!</p>
                                <p class="text-emerald-700 text-[11px] mt-0.5">A 6-digit code was sent to <strong id="otp-masked-email"></strong>. Check your inbox (and spam folder).</p>
                            </div>
                        </div>

                        <!-- 6-digit OTP boxes -->
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-2">Enter Verification Code</label>
                            <div class="flex gap-2 justify-center" id="otp-boxes">
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                                <span class="flex items-center text-slate-300 font-black text-xl hidden sm:flex">—</span>
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                                <input type="tel" maxlength="1" class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-black border-2 border-slate-200 rounded-xl bg-slate-50 focus:border-blue-500 focus:bg-white focus:outline-none transition-all" inputmode="numeric">
                            </div>
                        </div>

                        <!-- Verify button -->
                        <button type="button" id="verify-otp-btn" onclick="verifyRegOTP()"
                                class="w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black py-3.5 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="ph ph-shield-check"></i> Verify Code
                        </button>

                        <!-- OTP status (moved) -->

                        <!-- Resend -->
                        <div class="text-center">
                            <button type="button" id="resend-otp-btn" onclick="resendOTP()" class="text-blue-500 text-xs font-bold hover:underline transition">
                                <i class="ph ph-arrow-clockwise"></i> Resend Code
                            </button>
                            <span class="text-slate-300 text-xs mx-1">·</span>
                            <button type="button" onclick="changeOTPEmail()" class="text-slate-400 text-xs hover:text-slate-600 transition">
                                Use different email
                            </button>
                        </div>
                    </div>

                    <!-- Verified badge (shown after success) -->
                    <div id="otp-verified-badge" class="hidden bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex gap-3 items-center">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 border border-emerald-300 flex items-center justify-center shrink-0">
                            <i class="ph ph-check-circle text-2xl text-emerald-600"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-emerald-800 font-black text-sm">Email Verified!</p>
                            <p class="text-emerald-700 text-xs" id="otp-verified-email-display"></p>
                        </div>
                        <button type="button" onclick="changeOTPEmail()" class="text-emerald-500 text-xs font-bold hover:underline">Change</button>
                    </div>

                    <!-- Continue (only shown after verified) -->
                    <button type="button" id="otp-continue-btn" onclick="goToStep2FromOTP()" class="hidden w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black py-4 rounded-2xl shadow-lg transition-all flex items-center justify-center gap-2 text-base">
                        Continue <i class="ph ph-arrow-right"></i>
                    </button>

                    <!-- Back -->
                    <button type="button" onclick="backToStep0()"
                            class="w-full bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-500 font-bold px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 text-sm">
                        <i class="ph ph-arrow-left"></i> Back
                    </button>

                </div><!-- /step-panel-otp -->

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 3 — ID Upload / OCR Scan      ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-1" class="hidden space-y-4">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-identification-card"></i>
                            <span id="step1-label-discount">Step 3 of 4 — Scan Your Discount ID</span>
                            <span id="step1-label-basic" class="hidden">Step 3 of 4 — ID Photo (Optional)</span>
                        </p>
                        <p class="text-slate-400 text-xs mt-1" id="step1-sub-discount">Your name and ID number will be extracted and locked automatically</p>
                        <p class="text-slate-400 text-xs mt-1 hidden" id="step1-sub-basic">Uploading an ID is optional for regular accounts. You can skip this step.</p>
                    </div>

                    <!-- Dropzone -->
                    <label for="id_picture"
                           class="relative flex flex-col items-center justify-center w-full h-44 border-2 border-dashed border-white/20 rounded-2xl cursor-pointer hover:border-blue-400 hover:bg-blue-500/10 transition-all group">
                        <div id="upload-placeholder" class="flex flex-col items-center gap-2">
                            <div class="w-16 h-16 rounded-2xl bg-blue-500/15 border border-blue-400/30 flex items-center justify-center group-hover:bg-blue-500/25 transition-colors">
                                <i class="ph ph-camera text-3xl text-blue-400 group-hover:text-blue-300 transition-colors"></i>
                            </div>
                            <p class="text-white/55 text-sm font-semibold" id="dropzone-label">Click to upload your ID photo</p>
                            <div class="bg-blue-500/20 border border-blue-400/30 text-blue-200 text-[10px] font-black px-3 py-1.5 rounded-full uppercase tracking-wider animate-[pulse_2s_ease-in-out_infinite]">
                                JPG or PNG · Max 2MB
                            </div>
                        </div>
                        <img id="upload-preview" src="" alt="Preview" class="hidden h-36 object-contain rounded-xl">
                        <div id="upload-overlay" class="hidden absolute inset-0 bg-white/50 rounded-2xl flex-col items-center justify-center backdrop-blur-sm opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <i class="ph ph-arrows-clockwise text-4xl text-[#1e3a5f] mb-2"></i>
                            <span class="text-[#1e3a5f] font-black text-sm tracking-wide bg-white/80 px-3 py-1 rounded-full shadow-sm">Tap to Change Photo</span>
                        </div>
                    </label>
                    <input type="file" name="id_picture" id="id_picture" accept="image/jpeg,image/png,image/webp" class="sr-only">

                    <!-- AI consent -->
                    <div id="ai-consent-notice" class="hidden bg-blue-500/10 border border-blue-400/30 rounded-xl p-3 flex gap-2.5 items-start">
                        <i class="ph ph-robot text-lg shrink-0 mt-0.5 text-blue-500"></i>
                        <p class="text-blue-300 text-[11px] leading-relaxed">
                            <span class="font-bold text-blue-400">AI-Powered ID Scan</span> — Your photo will be sent securely to Mistral Pixtral AI to extract your details. It is not stored by Mistral.
                        </p>
                    </div>

                    <!-- Scan button (discount only) -->
                    <button type="button" id="ocr-btn" onclick="startMistralScan()"
                            class="hidden w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 active:scale-95 text-white font-bold py-3.5 rounded-xl shadow-lg hover:shadow-blue-500/30 transition-all flex items-center justify-center gap-2">
                        <i class="ph ph-sparkle"></i> Scan ID with Mistral AI
                    </button>

                    <!-- OCR status -->
                    <div id="ocr-status" class="hidden"></div>

                    <!-- Continue button -->
                    <button type="button" id="continue-btn" onclick="goToStep2()"
                            class="hidden w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all flex items-center justify-center gap-2 text-base">
                        Continue <i class="ph ph-arrow-right"></i>
                    </button>

                    <!-- Skip button (basic only) -->
                    <button type="button" id="skip-id-btn" onclick="skipIdAndContinue()"
                            class="hidden w-full bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-500 font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2 text-sm">
                        <i class="ph ph-arrow-right"></i> Skip — I don't want to upload an ID
                    </button>

                    <!-- Back -->
                    <button type="button" onclick="backToStep0()"
                            class="w-full bg-white/10 hover:bg-white/15 border border-white/20 text-slate-500 font-bold px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 text-sm">
                        <i class="ph ph-arrow-left"></i> Back
                    </button>

                </div><!-- /step-panel-1 -->

                <!-- ╔══════════════════════════════════════╗
                     ║  STEP 2 — Complete Your Profile     ║
                     ╚══════════════════════════════════════╝ -->
                <div id="step-panel-2" class="<?= !empty($errors) ? '' : 'hidden' ?> space-y-5">

                    <div class="text-center">
                        <p class="text-blue-400 text-[11px] font-black uppercase tracking-widest flex items-center justify-center gap-2">
                            <i class="ph ph-user-circle"></i> <span id="step2-label">Step 2 of 2 — Complete Your Profile</span>
                        </p>
                        <p class="text-white/35 text-xs mt-1" id="step2-sub-discount" class="hidden">Fields marked 🔒 are locked from your ID scan and cannot be changed</p>
                    </div>

                    <!-- Hidden inputs submitted with form -->
                    <input type="hidden" name="full_name"     id="full_name_hidden"     value="<?= htmlspecialchars($_POST['full_name']     ?? '') ?>">
                    <input type="hidden" name="id_number"     id="id_number_hidden"     value="<?= htmlspecialchars($_POST['id_number']     ?? '') ?>">
                    <input type="hidden" name="discount_type" id="discount_type_hidden"  value="<?= htmlspecialchars($_POST['discount_type'] ?? 'none') ?>">

                    <!-- ── Discount Path: Locked OCR Card ── -->
                    <div id="ocr-locked-card" class="hidden bg-white/5 border border-white/10 rounded-2xl p-4 space-y-3">
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
                    </div><!-- /ocr-locked-card -->

                    <!-- ── Basic Path: Name field ── -->
                    <div id="basic-name-field" class="hidden">
                        <label class="block text-slate-700 text-sm font-semibold mb-1.5">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" id="basic_full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               placeholder="Enter your full name"
                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                    </div>

                    <!-- ── Personal Info ── -->
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4 ml-2">
                            <i class="ph ph-user"></i> Personal Information
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Contact Number -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Number <span class="text-red-500">*</span></label>
                            <div class="relative flex items-center">
                                <input type="tel" name="contact_number" id="contact_number"
                                       value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                       placeholder="09171234567" required maxlength="11" pattern="[0-9]{11}"
                                       title="Please enter exactly 11 digits"
                                       oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
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
                        <div id="ec-section-discount" class="hidden">
                            <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4 ml-2">
                                <i class="ph ph-warning-circle"></i> Emergency Contact
                            </p>
                        </div>
                        <!-- Basic: collapsible EC -->
                        <div id="ec-section-basic">
                            <button type="button" class="ec-toggle-btn mb-3" id="ec-toggle-btn" onclick="toggleEC()">
                                <span class="flex items-center gap-2">
                                    <i class="ph ph-warning-circle"></i>
                                    Add Emergency Contact <span class="text-xs font-normal text-slate-400">(optional)</span>
                                </span>
                                <i class="ph ph-caret-down transition-transform duration-200" id="ec-caret"></i>
                            </button>
                        </div>
                    </div>

                    <div id="ec-panel" class="ec-panel space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-1.5">Contact Person <span id="ec-required-star" class="text-red-500 hidden">*</span></label>
                                <input type="text" name="ec_name" id="ec_name"
                                       value="<?= htmlspecialchars($_POST['ec_name'] ?? '') ?>"
                                       placeholder="Full name"
                                       class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                            </div>
                            <div>
                                <label class="block text-slate-700 text-sm font-semibold mb-1.5">Emergency Contact Number <span id="ec-contact-required-star" class="text-red-500 hidden">*</span></label>
                                <div class="relative flex items-center">
                                    <input type="tel" name="ec_contact" id="ec_contact"
                                           value="<?= htmlspecialchars($_POST['ec_contact'] ?? '') ?>"
                                           placeholder="09171234567" maxlength="11" pattern="[0-9]{11}"
                                           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                           class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-slate-700 text-sm font-semibold">Contact Address <span id="ec-addr-required-star" class="text-red-500 hidden">*</span></label>
                                    <label class="flex items-center gap-2 cursor-pointer group">
                                        <input type="checkbox" id="sync_address" class="w-4 h-4 rounded border-white/20 text-blue-500 focus:ring-blue-400">
                                        <span class="text-xs text-white/50 group-hover:text-blue-400 transition-colors">Same as home address</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <select id="ec_region"   name="ec_region"   class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                    <select id="ec_province" name="ec_province" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                    <select id="ec_city"     name="ec_city"     class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                    <select id="ec_barangay" name="ec_barangay" class="w-full bg-slate-50 border border-slate-300 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:bg-white text-sm"></select>
                                </div>
                                <input type="hidden" name="ec_address" id="ec_full_address">
                            </div>
                        </div>
                    </div>

                    <!-- ── Account Credentials ── -->
                    <div class="border-t border-white/10 pt-4">
                        <p class="text-blue-400 text-xs font-bold uppercase tracking-widest flex items-center gap-2 mb-4 ml-2">
                            <i class="ph ph-envelope"></i> Account Credentials
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-slate-700 text-sm font-semibold mb-1.5">Email <span class="text-red-500">*</span></label>
                            
                            <!-- Locked Display -->
                            <div class="bg-slate-100 border border-slate-200 rounded-xl px-4 py-3 flex items-center justify-between text-slate-600">
                                <div class="flex items-center gap-2">
                                    <i class="ph ph-lock text-slate-400"></i>
                                    <span class="font-semibold" id="display-email"><?= htmlspecialchars($_POST['email'] ?? '') ?></span>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">✓ Verified</span>
                            </div>

                            <!-- Hidden Input for Form Submission -->
                            <input type="hidden" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 items-start">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-slate-700 text-sm font-semibold mb-1.5">Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" name="password" id="password"
                                               placeholder="Min. 8 characters" required minlength="8"
                                               class="w-full bg-slate-50 border border-slate-300 text-slate-800 placeholder-slate-400 rounded-xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400 focus:bg-white">
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
                            <div class="md:h-full flex flex-col justify-start">
                                <div id="pw-req-box" class="p-3 bg-white/5 rounded-2xl border border-white/10 transition-all duration-300">
                                    <p class="text-[11px] font-bold text-white/40 uppercase tracking-widest mb-2">Password Requirements</p>
                                    <div class="grid gap-y-1">
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
                    <label class="bg-blue-500/10 border border-blue-400/20 rounded-2xl p-4 flex gap-3 text-slate-700 cursor-pointer hover:bg-blue-500/15 transition group">
                        <div class="pt-0.5 shrink-0">
                            <input type="checkbox" id="privacy_consent" name="privacy_consent" required
                                   class="w-5 h-5 rounded border-blue-400/30 text-blue-500 focus:ring-blue-500 bg-white cursor-pointer mt-0.5">
                        </div>
                        <div class="text-xs">
                            <p class="font-bold flex items-center gap-1.5">
                                <i class="ph ph-shield-check text-base text-blue-400"></i>
                                Data Privacy Notice (RA 10173)
                            </p>
                            <p class="opacity-80 leading-relaxed mt-0.5">I agree that my personal information will be handled with care and used strictly for transportation services within the PARE system in compliance with the Data Privacy Act of 2012.</p>
                        </div>
                    </label>

                    <!-- Back + Submit -->
                    <div class="flex gap-3 pt-1">
                        <button type="button" onclick="backToStep1()"
                                class="flex-none bg-white/10 hover:bg-white/15 border border-white/20 text-slate-600 font-bold px-6 py-4 rounded-2xl transition-all flex items-center gap-2">
                            <i class="ph ph-arrow-left"></i> Back
                        </button>
                        <button type="submit" id="submit-btn"
                                class="flex-1 bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black text-lg py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all">
                            Create Account
                        </button>
                    </div>

                    <p class="text-center text-white/35 text-sm">
                        Already have an account?
                        <a href="login.php" class="underline font-black hover:opacity-80 transition-opacity" style="color: #2563EB !important;">Sign in here</a>
                    </p>

                </div><!-- /step-panel-2 -->

            </form>
            <?php endif; ?>
        </div><!-- /card -->
    </div>
</div>

<script>
// ── Global State ─────────────────────────────────────────────
let ocrData    = { full_name: null, id_number: null, id_type: null, discount_type: 'none', confidence: 'low' };
let scanFailed = false;
let scanDone   = false;
let currentMode = 'basic'; // 'basic' or 'discount'
let ecOpen = false;

// ── Account Type Selection ────────────────────────────────────
function selectAccountType(mode) {
    currentMode = mode;
    document.getElementById('reg_mode_input').value = mode;

    const optBasic    = document.getElementById('opt-basic');
    const optDiscount = document.getElementById('opt-discount');
    const infoBasic   = document.getElementById('acct-info-basic');
    const infoDiscount= document.getElementById('acct-info-discount');
    const nextBtn     = document.getElementById('step0-next-btn');

    if (mode === 'basic') {
        optBasic.className    = 'acct-toggle-option active-basic';
        optDiscount.className = 'acct-toggle-option';
        infoBasic.classList.remove('hidden');
        infoDiscount.classList.add('hidden');
        nextBtn.innerHTML = 'Continue <i class="ph ph-arrow-right"></i>';
    } else {
        optDiscount.className = 'acct-toggle-option active-discount';
        optBasic.className    = 'acct-toggle-option';
        infoDiscount.classList.remove('hidden');
        infoBasic.classList.add('hidden');
        nextBtn.innerHTML = 'Continue to ID Scan <i class="ph ph-identification-card"></i>';
    }
}

// ── Step Navigation ───────────────────────────────────────────
function goToStep1() {
    // Step 1 is now the OTP verification step
    document.getElementById('step-panel-0').classList.add('hidden');
    document.getElementById('step-panel-otp').classList.remove('hidden');
    updateDots(1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToStep0() {
    document.getElementById('step-panel-otp').classList.add('hidden');
    document.getElementById('step-panel-1').classList.add('hidden');
    document.getElementById('step-panel-0').classList.remove('hidden');
    updateDots(0);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function goToStep2FromOTP() {
    // Move from OTP panel to the ID scan/upload panel
    document.getElementById('step-panel-otp').classList.add('hidden');
    document.getElementById('step-panel-1').classList.remove('hidden');
    updateDots(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });

    if (currentMode === 'basic') {
        document.getElementById('step1-label-discount').classList.add('hidden');
        document.getElementById('step1-label-basic').classList.remove('hidden');
        document.getElementById('step1-sub-discount').classList.add('hidden');
        document.getElementById('step1-sub-basic').classList.remove('hidden');
        document.getElementById('skip-id-btn').classList.remove('hidden');
        document.getElementById('ocr-btn').classList.add('hidden');
        document.getElementById('dropzone-label').textContent = 'Click to upload your ID photo (optional)';
    } else {
        document.getElementById('step1-label-discount').classList.remove('hidden');
        document.getElementById('step1-label-basic').classList.add('hidden');
        document.getElementById('step1-sub-discount').classList.remove('hidden');
        document.getElementById('step1-sub-basic').classList.add('hidden');
        document.getElementById('skip-id-btn').classList.add('hidden');
        document.getElementById('dropzone-label').textContent = 'Click to upload your discount ID photo';
    }
}

function skipIdAndContinue() {
    // Basic mode: skip ID upload, go straight to profile form
    ocrData    = { full_name: null, id_number: null, id_type: null, discount_type: 'none', confidence: 'low' };
    scanFailed = false;
    scanDone   = false;
    transitionToStep2();
}

function goToStep2() {
    // Write OCR values into hidden inputs (discount path)
    document.getElementById('full_name_hidden').value     = ocrData.full_name    || '';
    document.getElementById('id_number_hidden').value     = ocrData.id_number    || '';
    document.getElementById('discount_type_hidden').value  = ocrData.discount_type || 'none';

    // Populate locked display spans
    document.getElementById('display-full-name').textContent = ocrData.full_name  || '— Not detected';
    document.getElementById('display-id-number').textContent = ocrData.id_number  || '— Not detected';
    const dLabels = { none:'None (Regular Passenger)', senior:'Senior Citizen', pwd:'PWD', student:'Student', teacher:'Teacher', nurse:'Nurse' };
    document.getElementById('display-discount').textContent  = dLabels[ocrData.discount_type] || 'None (Regular Passenger)';

    // Scan failed? → show editable inputs instead of locked boxes
    if (scanFailed) {
        document.getElementById('display-full-name-box').classList.add('hidden');
        document.getElementById('full_name_editable').classList.remove('hidden');
        document.getElementById('display-id-number-box').classList.add('hidden');
        document.getElementById('id_number_editable').classList.remove('hidden');
    }

    transitionToStep2();
}

function transitionToStep2() {
    document.getElementById('step-panel-1').classList.add('hidden');
    document.getElementById('step-panel-2').classList.remove('hidden');
    updateDots(3);
    configureStep2ForMode();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToStep1() {
    document.getElementById('step-panel-2').classList.add('hidden');
    document.getElementById('step-panel-1').classList.remove('hidden');
    updateDots(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function configureStep2ForMode() {
    const isDiscount = currentMode === 'discount';

    // Show/hide OCR locked card
    document.getElementById('ocr-locked-card').classList.toggle('hidden', !isDiscount);

    // Show/hide basic name field
    document.getElementById('basic-name-field').classList.toggle('hidden', isDiscount);

    // Step label
    document.getElementById('step2-label').textContent = isDiscount
        ? 'Step 4 of 4 — Complete Your Profile'
        : 'Step 4 of 4 — Complete Your Profile';

    // EC section — discount shows always-open, basic shows toggle
    document.getElementById('ec-section-discount').classList.toggle('hidden', !isDiscount);
    document.getElementById('ec-section-basic').classList.toggle('hidden', isDiscount);

    if (isDiscount) {
        // EC is required for discount: open the panel, add required attributes
        document.getElementById('ec-panel').classList.add('open');
        setECRequired(true);
    } else {
        // EC is optional for basic: collapsed by default
        if (!ecOpen) document.getElementById('ec-panel').classList.remove('open');
        setECRequired(false);
    }
}

function setECRequired(required) {
    const ecName    = document.getElementById('ec_name');
    const ecContact = document.getElementById('ec_contact');
    const starName  = document.getElementById('ec-required-star');
    const starCont  = document.getElementById('ec-contact-required-star');
    const starAddr  = document.getElementById('ec-addr-required-star');

    if (required) {
        ecName.setAttribute('required', '');
        ecContact.setAttribute('required', '');
        starName.classList.remove('hidden');
        starCont.classList.remove('hidden');
        starAddr.classList.remove('hidden');
    } else {
        ecName.removeAttribute('required');
        ecContact.removeAttribute('required');
        starName.classList.add('hidden');
        starCont.classList.add('hidden');
        starAddr.classList.add('hidden');
    }
}

// ── EC Toggle (basic mode) ────────────────────────────────────
function toggleEC() {
    ecOpen = !ecOpen;
    const panel = document.getElementById('ec-panel');
    const caret = document.getElementById('ec-caret');
    panel.classList.toggle('open', ecOpen);
    caret.style.transform = ecOpen ? 'rotate(180deg)' : 'rotate(0deg)';
}

// ── Step Dots ─────────────────────────────────────────────────
function updateDots(step) {
    const labels = {
        0: 'Choose account type',
        1: 'Verify your email',
        2: currentMode === 'basic' ? 'Upload ID (optional)' : 'Scan your discount ID',
        3: 'Complete your profile'
    };
    ['dot-0','dot-1','dot-2','dot-3'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = 'step-dot';
        if (i < step) el.classList.add('done');
        else if (i === step) el.classList.add('active');
    });
    document.getElementById('step-label-text').textContent = labels[step] || '';
}

// ── Email OTP Verification ────────────────────────────────────
let otpEmailVerified = false;

async function sendRegOTP() {
    const emailInput = document.getElementById('otp-email-input');
    const btn        = document.getElementById('send-otp-btn');
    const email      = emailInput.value.trim();

    if (!email || !email.toLowerCase().endsWith('@gmail.com')) {
        showOTPStatus('error', 'Please enter a valid @gmail.com email address.');
        emailInput.focus();
        return;
    }

    // Save the actual email on the input for later retrieval
    emailInput.dataset.email = email;

    btn.disabled  = true;
    btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Sending…';

    try {
        const res  = await fetch('api_send_reg_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('otp-code-row').classList.remove('hidden');
            document.getElementById('otp-masked-email').textContent = data.masked_email;
            document.getElementById('otp-email-row').classList.add('hidden');
            showOTPStatus('info', `Code sent to ${data.masked_email}. Expires in 10 minutes.`);
            document.querySelectorAll('.otp-box')[0].focus();
            startOTPCountdown(data.expires_in || 600);
        } else {
            showOTPStatus('error', data.error || 'Failed to send code. Try again.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send Code';
        }
    } catch (e) {
        showOTPStatus('error', 'Network error. Please check your connection.');
        btn.disabled  = false;
        btn.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send Code';
    }
}

async function verifyRegOTP() {
    const boxes = document.querySelectorAll('.otp-box');
    const otp   = Array.from(boxes).map(b => b.value).join('');

    if (otp.length !== 6 || !/^\d{6}$/.test(otp)) {
        showOTPStatus('error', 'Please fill in all 6 digits.');
        return;
    }

    const btn = document.getElementById('verify-otp-btn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Verifying…';

    try {
        const res  = await fetch('api_verify_reg_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ otp })
        });
        const data = await res.json();

        if (data.success) {
            otpEmailVerified = true;
            const verifiedEmail = document.getElementById('otp-email-input').dataset.email || '';

            // Show verified badge, hide code entry
            document.getElementById('otp-code-row').classList.add('hidden');
            document.getElementById('otp-verified-badge').classList.remove('hidden');
            document.getElementById('otp-verified-email-display').textContent = verifiedEmail + ' ✓';
            document.getElementById('otp-continue-btn').classList.remove('hidden');

            // Pre-fill email in the final form and lock it
            const emailField = document.getElementById('email');
            const displayEmail = document.getElementById('display-email');
            if (emailField && verifiedEmail) {
                emailField.value = verifiedEmail;
                if (displayEmail) displayEmail.textContent = verifiedEmail;
            }

            showOTPStatus('success', '✓ Email verified successfully!');
            btn.classList.add('hidden');
            clearInterval(otpCountdownInterval);

        } else {
            showOTPStatus('error', data.error || 'Incorrect code.');
            if (data.locked || data.expired) {
                boxes.forEach(b => b.value = '');
                document.getElementById('otp-code-row').classList.add('hidden');
                document.getElementById('otp-email-row').classList.remove('hidden');
                const sb = document.getElementById('send-otp-btn');
                sb.disabled  = false;
                sb.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send New Code';
            }
            btn.disabled  = false;
            btn.innerHTML = '<i class="ph ph-shield-check"></i> Verify Code';
            // Shake effect on wrong code
            boxes.forEach(b => {
                b.classList.add('border-red-400', 'bg-red-50');
                setTimeout(() => b.classList.remove('border-red-400', 'bg-red-50'), 1200);
            });
        }
    } catch (e) {
        showOTPStatus('error', 'Network error. Please check your connection.');
        btn.disabled  = false;
        btn.innerHTML = '<i class="ph ph-shield-check"></i> Verify Code';
    }
}

async function resendOTP() {
    document.getElementById('otp-email-row').classList.remove('hidden');
    document.getElementById('otp-code-row').classList.add('hidden');
    document.getElementById('otp-status').innerHTML = '';
    document.querySelectorAll('.otp-box').forEach(b => b.value = '');
    const sb = document.getElementById('send-otp-btn');
    sb.disabled  = false;
    sb.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send Code';
    await sendRegOTP();
}

function changeOTPEmail() {
    otpEmailVerified = false;
    document.getElementById('otp-verified-badge').classList.add('hidden');
    document.getElementById('otp-continue-btn').classList.add('hidden');
    document.getElementById('otp-code-row').classList.add('hidden');
    document.getElementById('otp-email-row').classList.remove('hidden');
    document.getElementById('otp-status').innerHTML = '';
    document.querySelectorAll('.otp-box').forEach(b => b.value = '');
    const sb = document.getElementById('send-otp-btn');
    sb.disabled  = false;
    sb.innerHTML = '<i class="ph ph-paper-plane-tilt"></i> Send Code';
    // Un-lock the email field in the final form
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.readOnly = false;
        emailField.style.background = '';
        emailField.style.borderColor = '';
        emailField.value = '';
    }
    document.getElementById('otp-email-input').focus();
}

function showOTPStatus(type, msg) {
    const el = document.getElementById('otp-status');
    const styles = {
        error:   'bg-red-50 border border-red-200 text-red-700',
        success: 'bg-emerald-50 border border-emerald-200 text-emerald-800',
        info:    'bg-blue-50 border border-blue-200 text-blue-700',
    };
    el.innerHTML = `<div class="${styles[type] || styles.info} rounded-xl px-4 py-2.5 text-xs font-semibold mt-1">${msg}</div>`;
}

let otpCountdownInterval = null;
function startOTPCountdown(secs) {
    clearInterval(otpCountdownInterval);
    let remaining = secs;
    const resendBtn = document.getElementById('resend-otp-btn');
    resendBtn.disabled = true;
    otpCountdownInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(otpCountdownInterval);
            resendBtn.textContent = '↺ Resend Code';
            resendBtn.disabled    = false;
        } else {
            const m = Math.floor(remaining / 60);
            const s = String(remaining % 60).padStart(2, '0');
            resendBtn.textContent = `↺ Resend (${m}:${s})`;
        }
    }, 1000);
}

// OTP box auto-tab, auto-verify, and paste support
document.addEventListener('DOMContentLoaded', () => {
    const boxes = document.querySelectorAll('.otp-box');
    boxes.forEach((box, idx) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            if (box.value && idx < boxes.length - 1) boxes[idx + 1].focus();
            if (Array.from(boxes).every(b => b.value)) verifyRegOTP();
        });
        box.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !box.value && idx > 0) boxes[idx - 1].focus();
        });
        box.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            [...pasted].forEach((ch, i) => { if (boxes[i]) boxes[i].value = ch; });
            const nextEmpty = [...boxes].findIndex(b => !b.value);
            if (nextEmpty !== -1) boxes[nextEmpty].focus(); else boxes[5].focus();
            if (pasted.length === 6) setTimeout(() => verifyRegOTP(), 100);
        });
    });
});

// ── Image preview ─────────────────────────────────────────────
document.getElementById('id_picture').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('upload-placeholder').classList.add('hidden');
        const prev = document.getElementById('upload-preview');
        prev.src = e.target.result;
        prev.classList.remove('hidden');
        document.getElementById('upload-overlay').classList.remove('hidden');
        document.getElementById('upload-overlay').classList.add('flex');

        if (currentMode === 'discount') {
            document.getElementById('ai-consent-notice').classList.remove('hidden');
            const scanBtn = document.getElementById('ocr-btn');
            scanBtn.classList.remove('hidden');
            scanBtn.disabled = false;
            scanBtn.innerHTML = '<i class="ph ph-sparkle"></i> Scan ID with Mistral AI';
        } else {
            // Basic mode: show continue button directly
            const cb = document.getElementById('continue-btn');
            cb.classList.remove('hidden');
            cb.innerHTML = 'Continue with this photo <i class="ph ph-arrow-right"></i>';
            document.getElementById('skip-id-btn').classList.add('hidden');
        }

        document.getElementById('ocr-status').classList.add('hidden');
        document.getElementById('ocr-status').innerHTML = '';
        if (currentMode === 'discount') document.getElementById('continue-btn').classList.add('hidden');
        scanFailed = false;
        ocrData = { full_name: null, id_number: null, id_type: null, discount_type: 'none', confidence: 'low' };
    };
    reader.readAsDataURL(file);
});

// ── Mistral OCR ───────────────────────────────────────────────
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
    statusEl.innerHTML = `<div class="flex items-center gap-3 bg-blue-500/10 border border-blue-400/20 rounded-xl px-4 py-3 text-blue-400 text-xs font-semibold animate-pulse"><i class="ph ph-robot text-base"></i>Analyzing your ID with Mistral Pixtral AI... please wait</div>`;

    try {
        const base64   = await fileToBase64(file);
        const mimeType = file.type || 'image/jpeg';
        const resp     = await fetch('<?= BASE_PATH ?>/auth/api_mistral_ocr.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_base64: base64, mime_type: mimeType })
        });
        const data = await resp.json();
        if (!data.success) throw new Error(data.error || 'Unknown error from Mistral.');

        ocrData    = { full_name: data.full_name||null, id_number: data.id_number||null, id_type: data.id_type||null, discount_type: data.discount_type||'none', confidence: data.confidence||'low' };
        scanFailed = false;
        scanDone   = true;

        const cc = { high:'emerald', medium:'amber', low:'red' }[data.confidence]||'slate';
        const ci = { high:'✅', medium:'⚠️', low:'❌' }[data.confidence]||'❓';
        const cl = { high:'High Confidence', medium:'Medium Confidence', low:'Low Confidence' }[data.confidence]||'';
        const itb = data.id_type ? `<span class="bg-blue-500/20 border border-blue-400/30 text-blue-500 text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider">${data.id_type}</span>` : '';

        statusEl.innerHTML = `
            <div class="rounded-2xl overflow-hidden border border-emerald-400/30 shadow-lg mb-3">
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
            </div>
            <div class="bg-amber-500/10 px-4 py-3 flex items-start gap-2 rounded-xl border border-amber-500/20">
                <i class="ph ph-warning-circle text-amber-400 text-base mt-0.5 shrink-0"></i>
                <p class="text-amber-200/90 text-[11px] leading-tight">
                    <strong>Is something wrong?</strong> If the AI misread your name or ID number, <strong>tap your ID photo above</strong> to upload a clearer photo before continuing.
                </p>
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
        const reader = new FileReader();
        reader.onload = e => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width, height = img.height;
                const max = 1000;
                if (width > height && width > max) { height = Math.round(height * max / width); width = max; }
                else if (height > max) { width = Math.round(width * max / height); height = max; }
                canvas.width = width; canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.6);
                res(dataUrl.split(',')[1]);
            };
            img.onerror = rej;
            img.src = e.target.result;
        };
        reader.onerror = rej;
        reader.readAsDataURL(file);
    });
}

// ── Form submit ───────────────────────────────────────────────
document.getElementById('register-form').addEventListener('submit', function (e) {
    // Sync reg_mode into hidden input
    document.getElementById('reg_mode_input').value = currentMode;

    if (currentMode === 'discount') {
        // Scan failed → copy editable inputs → hidden inputs
        if (scanFailed) {
            const n = document.getElementById('full_name_editable').value.trim();
            const i = document.getElementById('id_number_editable').value.trim();
            if (!n) { e.preventDefault(); alert('Please enter your full name.'); return; }
            if (!i) { e.preventDefault(); alert('Please enter your ID number.'); return; }
            document.getElementById('full_name_hidden').value = n;
            document.getElementById('id_number_hidden').value = i;
        }
    } else {
        // Basic: copy name from basic_full_name input → full_name_hidden
        const basicName = document.getElementById('basic_full_name').value.trim();
        if (!basicName) { e.preventDefault(); alert('Please enter your full name.'); return; }
        document.getElementById('full_name_hidden').value = basicName;
        document.getElementById('id_number_hidden').value = '';
        document.getElementById('discount_type_hidden').value = 'none';
    }

    // Password checks
    const pw = document.getElementById('password').value;
    const ok = /.{8,}/.test(pw) && /[A-Z]/.test(pw) && /[a-z]/.test(pw) && /[0-9]/.test(pw) && /[^A-Za-z0-9]/.test(pw);
    if (!ok) { e.preventDefault(); alert('Password does not meet all requirements.'); return; }
    if (pw !== document.getElementById('confirm_password').value) { e.preventDefault(); alert('Passwords do not match.'); return; }
});

// ── Password strength ─────────────────────────────────────────
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
    let allMet = true;
    Object.values(reqs).forEach(({ re, el }) => {
        const met = re.test(v);
        if (!met) allMet = false;
        if (met) {
            el.classList.add('hidden');
        } else {
            el.classList.remove('hidden');
            const icon = el.querySelector('.icon');
            el.classList.remove('text-emerald-500');
            el.classList.add('text-slate-400');
            icon.className = 'ph ph-circle text-[10px] icon';
        }
    });
    const box = document.getElementById('pw-req-box');
    if (box) box.classList.toggle('hidden', allMet);
});

// ── Toggle password visibility ────────────────────────────────
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('ph-eye-slash', inp.type === 'password');
    icon.classList.toggle('ph-eye',       inp.type === 'text');
}

// ── PHP error: auto-show step 2 in correct mode ───────────────
<?php if (!empty($errors)): ?>
document.addEventListener('DOMContentLoaded', () => {
    const postedMode = '<?= htmlspecialchars($_POST['reg_mode'] ?? 'basic') ?>';
    currentMode = postedMode;
    document.getElementById('reg_mode_input').value = postedMode;
    updateDots(2);
    configureStep2ForMode();

    if (postedMode === 'discount') {
        scanFailed = true;
        document.getElementById('display-full-name-box').classList.add('hidden');
        document.getElementById('full_name_editable').classList.remove('hidden');
        document.getElementById('display-id-number-box').classList.add('hidden');
        document.getElementById('id_number_editable').classList.remove('hidden');
    }
});
<?php endif; ?>
</script>

<!-- Address Selection -->
<script>
/**
 * Embedded ph-address-selector
 */
const PH_ADDRESS_BASE_URL = 'https://cdn.jsdelivr.net/gh/isaacdarcilla/philippine-addresses@master';

class PHAddressSelector {
    constructor(config) {
        this.prefix = config.prefix || ''; 
        this.selectors = {
            region: document.getElementById(`${this.prefix}region`),
            province: document.getElementById(`${this.prefix}province`),
            city: document.getElementById(`${this.prefix}city`),
            barangay: document.getElementById(`${this.prefix}barangay`)
        };
        this.data = { regions: [], provinces: [], cities: [], barangays: [] };
        this.initPromise = this.init();
    }

    async init() {
        try {
            this.clearSelect('region');
            this.clearSelect('province');
            this.clearSelect('city');
            this.clearSelect('barangay');

            this.data.regions = await this.fetchData('region');
            this.populateSelect('region', this.data.regions);

            if (this.selectors.region) this.selectors.region.addEventListener('change', () => this.handleRegionChange());
            if (this.selectors.province) this.selectors.province.addEventListener('change', () => this.handleProvinceChange());
            if (this.selectors.city) this.selectors.city.addEventListener('change', () => this.handleCityChange());
        } catch (error) {
            console.error('Failed to init PH Address:', error);
            if (this.selectors.region) {
                this.selectors.region.innerHTML = `<option value="">Error loading APIs</option>`;
            }
        }
    }

    async fetchData(type) {
        const response = await fetch(`${PH_ADDRESS_BASE_URL}/${type}.json`);
        return await response.json();
    }

    populateSelect(type, items, selectedValue = '') {
        const select = this.selectors[type];
        if (!select) return;

        select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
        
        items.sort((a, b) => {
            const nameA = a[`${type}_name`] || a.name || a[`brgy_name`] || "";
            const nameB = b[`${type}_name`] || b.name || b[`brgy_name`] || "";
            return String(nameA).localeCompare(String(nameB));
        });

        items.forEach(item => {
            const name = item[`${type}_name`] || item.name || item[`brgy_name`];
            const code = item[`${type}_code`] || item[`brgy_code`];
            const option = document.createElement('option');
            option.value = name; 
            option.dataset.code = code;
            option.textContent = name;
            if (name === selectedValue) option.selected = true;
            select.appendChild(option);
        });
    }

    async handleRegionChange() {
        const selectedOption = this.selectors.region.options[this.selectors.region.selectedIndex];
        const regionCode = selectedOption.dataset.code;

        this.clearSelect('province');
        this.clearSelect('city');
        this.clearSelect('barangay');

        if (!regionCode) return;
        if (this.data.provinces.length === 0) this.data.provinces = await this.fetchData('province');

        const filtered = this.data.provinces.filter(p => p.region_code === regionCode);
        this.populateSelect('province', filtered);
    }

    async handleProvinceChange() {
        const selectedOption = this.selectors.province.options[this.selectors.province.selectedIndex];
        const provinceCode = selectedOption.dataset.code;

        this.clearSelect('city');
        this.clearSelect('barangay');

        if (!provinceCode) return;
        if (this.data.cities.length === 0) this.data.cities = await this.fetchData('city');

        const filtered = this.data.cities.filter(c => c.province_code === provinceCode);
        this.populateSelect('city', filtered);
    }

    async handleCityChange() {
        const selectedOption = this.selectors.city.options[this.selectors.city.selectedIndex];
        const cityCode = selectedOption.dataset.code;

        this.clearSelect('barangay');

        if (!cityCode) return;
        if (this.data.barangays.length === 0) this.data.barangays = await this.fetchData('barangay');

        const filtered = this.data.barangays.filter(b => b.city_code === cityCode);
        this.populateSelect('barangay', filtered);
    }

    clearSelect(type) {
        const select = this.selectors[type];
        if (select) select.innerHTML = `<option value="">Select ${type.charAt(0).toUpperCase() + type.slice(1)}</option>`;
    }

    async setValues(values) {
        if (!values) return;
        await this.initPromise;

        if (values.region) {
            this.selectors.region.value = values.region;
            await this.handleRegionChange();
            
            if (values.province) {
                this.selectors.province.value = values.province;
                await this.handleProvinceChange();
                
                if (values.city) {
                    this.selectors.city.value = values.city;
                    await this.handleCityChange();
                    
                    if (values.barangay) {
                        this.selectors.barangay.value = values.barangay;
                        this.selectors.barangay.dispatchEvent(new Event('change'));
                    }
                }
            }
        }
    }
}
window.initPHAddress = (prefix) => new PHAddressSelector({ prefix });
</script>
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
