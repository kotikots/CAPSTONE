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
    $address    = trim($_POST['address']     ?? '');
    $contact    = trim($_POST['contact_number'] ?? '');
    $ecName     = trim($_POST['ec_name']     ?? '');
    $ecAddress  = trim($_POST['ec_address']  ?? '');
    $email      = trim($_POST['email']       ?? '');
    $password   = $_POST['password']         ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if (empty($fullName))  $errors[] = 'Full name is required.';
    if (empty($idNumber))  $errors[] = 'ID number is required.';
    if (empty($address))   $errors[] = 'Address is required.';
    if (empty($contact))   $errors[] = 'Contact number is required.';
    if (empty($ecName))    $errors[] = 'Emergency contact name is required.';
    if (empty($ecAddress)) $errors[] = 'Emergency contact address is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm)  $errors[] = 'Passwords do not match.';

    // --- ID Picture Upload ---
    $picturePath = null;
    if (!empty($_FILES['id_picture']['name'])) {
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize   = 5 * 1024 * 1024; // 5MB
        $ext       = strtolower(pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION));
        $mimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime      = finfo_file($finfo, $_FILES['id_picture']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($ext, $allowed) || !in_array($mime, $mimeTypes)) {
            $errors[] = 'ID picture must be a JPG, PNG, or WebP image.';
        } elseif ($_FILES['id_picture']['size'] > $maxSize) {
            $errors[] = 'ID picture must be smaller than 5MB.';
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

    // --- Check duplicate ID number ---
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $chk->execute([$idNumber]);
        if ($chk->fetch()) $errors[] = 'ID number is already registered.';
    }

    // --- Insert if no errors ---
    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users
                (full_name, id_number, id_picture, address, contact_number,
                 emergency_contact_name, emergency_contact_address, email, password, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'passenger')
        ");
        $stmt->execute([
            $fullName, $idNumber, $picturePath, $address, $contact,
            $ecName, $ecAddress,
            ($email !== '' ? $email : null),
            $hashed
        ]);
        $success = true;
    }
}
?>
<?php $pageTitle = 'Create Account'; include '../includes/header.php'; ?>

<div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900">

    <!-- Background decoration -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-3 mb-4">
                <i class="ph ph-bus-fill text-5xl text-blue-300"></i>
                <h1 class="text-4xl font-black text-white tracking-tight">PARE</h1>
            </div>
            <p class="text-blue-200 text-lg">Create your passenger account</p>
        </div>

        <!-- Card -->
        <div class="bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl p-8">

            <?php if ($success): ?>
            <!-- Success State -->
            <div class="text-center py-8">
                <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="ph ph-check-circle text-5xl text-green-400"></i>
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
            <div class="bg-red-500/20 border border-red-400/40 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <i class="ph ph-warning-circle text-red-400 text-xl"></i>
                    <span class="text-red-300 font-bold text-sm">Please fix the following:</span>
                </div>
                <ul class="space-y-1 pl-6">
                    <?php foreach ($errors as $err): ?>
                    <li class="text-red-200 text-sm list-disc"><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" enctype="multipart/form-data" class="space-y-5" id="register-form">

                <!-- Section: Personal Info -->
                <p class="text-blue-300 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                    <i class="ph ph-user-circle"></i> Personal Information
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name -->
                    <div class="md:col-span-2">
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Full Name <span class="text-red-400">*</span></label>
                        <input type="text" name="full_name" id="full_name"
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                               placeholder="e.g. Juan Dela Cruz"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                    </div>

                    <!-- ID Number -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">ID Number <span class="text-red-400">*</span></label>
                        <input type="text" name="id_number" id="id_number"
                               value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>"
                               placeholder="e.g. QR-1234567"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <!-- Contact Number -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Contact Number <span class="text-red-400">*</span></label>
                        <input type="tel" name="contact_number" id="contact_number"
                               value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                               placeholder="e.g. 09171234567"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Home Address <span class="text-red-400">*</span></label>
                        <input type="text" name="address" id="address"
                               value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                               placeholder="Barangay, Municipality, Province"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-white/10 pt-4">
                    <p class="text-blue-300 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-warning-circle"></i> Emergency Contact
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Contact Person <span class="text-red-400">*</span></label>
                        <input type="text" name="ec_name" id="ec_name"
                               value="<?= htmlspecialchars($_POST['ec_name'] ?? '') ?>"
                               placeholder="Full name"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Contact Address <span class="text-red-400">*</span></label>
                        <input type="text" name="ec_address" id="ec_address"
                               value="<?= htmlspecialchars($_POST['ec_address'] ?? '') ?>"
                               placeholder="Barangay, Municipality"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>

                <!-- Divider -->
                <div class="border-t border-white/10 pt-4">
                    <p class="text-blue-300 text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                        <i class="ph ph-identification-card"></i> Account & ID Photo
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Email (optional) -->
                    <div class="md:col-span-2">
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Email <span class="text-white/40 text-xs">(optional)</span></label>
                        <input type="email" name="email" id="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="your@email.com"
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Password <span class="text-red-400">*</span></label>
                        <input type="password" name="password" id="password"
                               placeholder="Min. 8 characters"
                               required minlength="8"
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label class="block text-white/70 text-sm font-medium mb-1.5">Confirm Password <span class="text-red-400">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password"
                               placeholder="Repeat password"
                               required
                               class="w-full bg-white/10 border border-white/20 text-white placeholder-white/30 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    <!-- ID Picture Upload -->
                    <div class="md:col-span-2">
                        <label class="block text-white/70 text-sm font-medium mb-1.5">ID Picture <span class="text-red-400">*</span></label>
                        <label for="id_picture"
                               class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-white/30 rounded-2xl cursor-pointer hover:border-blue-400/60 hover:bg-white/5 transition-all group">
                            <div id="upload-placeholder" class="flex flex-col items-center">
                                <i class="ph ph-camera text-3xl text-blue-300 group-hover:text-blue-200 mb-2"></i>
                                <p class="text-white/60 text-sm">Click to upload ID photo</p>
                                <p class="text-white/30 text-xs">JPG, PNG, WebP · Max 5MB</p>
                            </div>
                            <img id="upload-preview" src="" alt="Preview" class="hidden h-24 object-contain rounded-lg">
                        </label>
                        <input type="file" name="id_picture" id="id_picture" accept="image/*" required class="hidden">
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" id="submit-btn"
                        class="w-full bg-blue-500 hover:bg-blue-400 active:scale-95 text-white font-black text-lg py-4 rounded-2xl shadow-lg hover:shadow-blue-500/40 transition-all mt-2">
                    Create Account
                </button>

                <p class="text-center text-white/50 text-sm">
                    Already have an account?
                    <a href="login.php" class="text-blue-300 font-semibold hover:text-white">Sign in here</a>
                </p>

            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

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
        };
        reader.readAsDataURL(file);
    });

    // Client-side password match validation
    document.getElementById('register-form')?.addEventListener('submit', function(e) {
        const p  = document.getElementById('password').value;
        const cp = document.getElementById('confirm_password').value;
        if (p !== cp) {
            e.preventDefault();
            alert('Passwords do not match. Please check and try again.');
        }
    });
</script>

</body>
</html>
