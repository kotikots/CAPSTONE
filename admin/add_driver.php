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

    // Validation
    if (empty($fullName))      $errors[] = 'Full name is required.';
    if (empty($licenseNumber)) $errors[] = 'License number is required.';
    if (empty($contactNumber)) $errors[] = 'Contact number is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
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
                INSERT INTO drivers (full_name, license_number, contact_number, email, password, profile_picture)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $licenseNumber, $contactNumber, ($email ?: null), $hashed, $profilePic]);
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
        <div class="max-w-2xl">
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
                            <input type="text" name="full_name" required placeholder="John Dela Cruz"
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
                            <input type="tel" name="contact_number" required placeholder="0917XXXXXXX"
                                   value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
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
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2 text-orange-600">Initial Password</label>
                            <input type="password" name="password" required minlength="8" placeholder="••••••••"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
                        </div>

                        <!-- Confirm -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Confirm Password</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-orange-400">
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
</script>
</body></html>
