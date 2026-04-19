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

// Fetch active drivers who are NOT yet assigned to ANY bus (active or inactive)
$drivers = $pdo->query("
    SELECT d.id, d.full_name, d.license_number 
    FROM drivers d 
    LEFT JOIN buses b ON b.driver_id = d.id
    WHERE d.is_active = 1 AND b.id IS NULL
    ORDER BY d.full_name ASC
")->fetchAll();

// Get the next Bus ID (Database Auto-Increment)
$nextBusIdStmt = $pdo->query("SELECT MAX(id) + 1 FROM buses");
$nextBusId = $nextBusIdStmt->fetchColumn() ?: 1;

// Auto-generate next Body Number
$nextBodyNumber = 'BUS-' . str_pad($nextBusId, 3, '0', STR_PAD_LEFT);
$lastBody = $pdo->query("SELECT body_number FROM buses WHERE body_number LIKE 'BUS-%' ORDER BY LENGTH(body_number) DESC, body_number DESC LIMIT 1")->fetchColumn();
if ($lastBody && preg_match('/BUS-(\d+)/i', $lastBody, $matches)) {
    $nextNum = (int)$matches[1] + 1;
    $nextBodyNumber = 'BUS-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
    $bodyNumber  = strtoupper(trim($_POST['body_number'] ?? ''));
    $model       = trim($_POST['model'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 22);
    $driverId    = (int)($_POST['driver_id'] ?? ($_GET['driver_id'] ?? 0));

    // Validation
    if (empty($plateNumber)) $errors[] = 'Plate number is required.';
    if (empty($bodyNumber))  $errors[] = 'Body number is required.';
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
            $stmt->execute([$plateNumber, $bodyNumber, $model, $capacity, ($driverId > 0 ? $driverId : null)]);
            $success = true;
            
            // Refetch drivers to update list
            $drivers = $pdo->query("
                SELECT d.id, d.full_name, d.license_number 
                FROM drivers d 
                LEFT JOIN buses b ON b.driver_id = d.id
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
        <div class="max-w-2xl mx-auto">
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
                        
                        <!-- Header / ID Info row -->
                        <div class="md:col-span-2 flex flex-col md:flex-row gap-6">
                            <!-- Database ID (Readonly) -->
                            <div class="flex-1">
                                <label class="block text-slate-400 text-[10px] font-black mb-1.5 uppercase tracking-wider">System Bus ID <span class="text-slate-400 font-normal normal-case">(Auto-generated)</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <i class="ph ph-database text-slate-400"></i>
                                    </div>
                                    <input type="text" value="#<?= str_pad($nextBusId, 4, '0', STR_PAD_LEFT) ?>" readonly
                                           class="w-full bg-slate-100 border border-slate-200 text-slate-500 rounded-xl pl-10 pr-4 py-3 font-mono font-bold cursor-not-allowed select-all">
                                </div>
                            </div>
                        </div>

                        <!-- Body Number -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Body Number</label>
                            <input type="text" name="body_number" required placeholder="BUS-001"
                                   value="<?= htmlspecialchars($_POST['body_number'] ?? $nextBodyNumber) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 font-bold text-slate-800">
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
                            <select name="driver_id"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 appearance-none font-semibold">
                                <option value="0">-- No Driver (Unassigned) --</option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= (isset($_POST['driver_id']) && $_POST['driver_id'] == $d['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['license_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-slate-400 text-[10px] mt-1 italic">Only drivers not assigned to any other bus are shown.</p>
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
