<?php
/**
 * admin/add_bus.php
 * Admin form to register a new bus and assign a driver.
 */
$requiredRole = 'admin';
$pageTitle    = 'Add New Bus';
$currentPage  = 'buses.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$errors  = [];
$success = false;

// Fetch active drivers who are NOT yet assigned to a bus
$drivers = $pdo->query("
    SELECT d.id, d.full_name, d.license_number 
    FROM drivers d 
    LEFT JOIN buses b ON b.driver_id = d.id AND b.is_active = 1
    WHERE d.is_active = 1 AND b.id IS NULL
    ORDER BY d.full_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
    $bodyNumber  = strtoupper(trim($_POST['body_number'] ?? ''));
    $model       = trim($_POST['model'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 22);
    $driverId    = (int)($_POST['driver_id'] ?? ($_GET['driver_id'] ?? 0));

    // Validation
    if (empty($plateNumber)) $errors[] = 'Plate number is required.';
    if (empty($bodyNumber))  $errors[] = 'Body number is required.';
    if ($driverId <= 0)      $errors[] = 'Please select a driver.';
    if ($capacity <= 0)      $errors[] = 'Capacity must be greater than zero.';

    // Check duplicate plate/body
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM buses WHERE plate_number = ? OR body_number = ?");
        $chk->execute([$plateNumber, $bodyNumber]);
        if ($chk->fetch()) $errors[] = 'Plate number or Body number already exists.';
    }

    // Insert if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO buses (plate_number, body_number, model, capacity, driver_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$plateNumber, $bodyNumber, $model, $capacity, $driverId]);
            $success = true;
            
            // Refetch drivers to update list
            $drivers = $pdo->query("
                SELECT d.id, d.full_name, d.license_number 
                FROM drivers d 
                LEFT JOIN buses b ON b.driver_id = d.id AND b.is_active = 1
                WHERE d.is_active = 1 AND b.id IS NULL
                ORDER BY d.full_name ASC
            ")->fetchAll();
            
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
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Add New Bus</h2>
                    <p class="text-slate-500 text-sm">Register a new vehicle and assign a driver.</p>
                </div>
                <a href="buses.php" class="text-slate-500 hover:text-slate-800 transition flex items-center gap-2 text-sm font-bold">
                    <i class="ph ph-arrow-left"></i> Back to Fleet
                </a>
            </div>

            <!-- Messages -->
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-5 mb-8 flex items-center gap-4">
                    <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 text-xl text-emerald-600">
                        <i class="ph ph-check-circle"></i>
                    </div>
                    <div>
                        <p class="font-bold">Bus Registered Successfully!</p>
                        <p class="text-sm opacity-90">The bus is now active and linked to the driver.</p>
                    </div>
                    <a href="buses.php" class="ml-auto bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold">View Fleet</a>
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
                <form method="POST" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Body Number -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Body Number</label>
                            <input type="text" name="body_number" required placeholder="BUS-001"
                                   value="<?= htmlspecialchars($_POST['body_number'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Plate Number -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Plate Number</label>
                            <input type="text" name="plate_number" required placeholder="ABC-1234"
                                   value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Model -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2">Bus Model</label>
                            <input type="text" name="model" placeholder="E-Jeepney CMCI 2023"
                                   value="<?= htmlspecialchars($_POST['model'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Seating Capacity</label>
                            <input type="number" name="capacity" required min="1" value="<?= htmlspecialchars($_POST['capacity'] ?? 22) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Driver Assignment -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Assign Driver</label>
                            <select name="driver_id" required
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 appearance-none">
                                <option value="">-- Select Driver --</option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (isset($_POST['driver_id']) && $_POST['driver_id'] == $d['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['license_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($drivers)): ?>
                                <p class="text-red-500 text-xs mt-1 italic">No available drivers. Please add a new driver first.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="pt-5">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition active:scale-95 text-lg">
                            Register Bus
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </main>
</div>
</body></html>
