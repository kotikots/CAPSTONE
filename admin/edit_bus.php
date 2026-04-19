<?php
/**
 * admin/edit_bus.php
 * Admin form to edit bus details and reassign driver.
 */
$requiredRole = 'admin';
$pageTitle    = 'Edit Bus';
$currentPage  = 'buses.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: buses.php');
    exit;
}

$errors  = [];
$success = false;

// Fetch bus details
$stmt = $pdo->prepare("SELECT * FROM buses WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$bus = $stmt->fetch();

if (!$bus) {
    header('Location: buses.php');
    exit;
}

// Fetch active drivers:
// 1. Currently assigned driver (if any)
// 2. Drivers NOT assigned to ANY bus
$drivers = $pdo->prepare("
    SELECT d.id, d.full_name, d.license_number 
    FROM drivers d 
    LEFT JOIN buses b ON b.driver_id = d.id
    WHERE d.is_active = 1 AND (b.id IS NULL OR b.id = ?)
    ORDER BY d.full_name ASC
");
$drivers->execute([$id]);
$drivers = $drivers->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plateNumber = strtoupper(trim($_POST['plate_number'] ?? ''));
    $bodyNumber  = strtoupper(trim($_POST['body_number'] ?? ''));
    $model       = trim($_POST['model'] ?? '');
    $capacity    = (int)($_POST['capacity'] ?? 22);
    $driverId    = (int)($_POST['driver_id'] ?? 0);
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($plateNumber)) $errors[] = 'Plate number is required.';
    if (empty($bodyNumber))  $errors[] = 'Body number is required.';

    // Check duplicate plate/body (excluding current ID)
    if (empty($errors)) {
        $chk = $pdo->prepare("SELECT id FROM buses WHERE (plate_number = ? OR body_number = ?) AND id != ?");
        $chk->execute([$plateNumber, $bodyNumber, $id]);
        if ($chk->fetch()) $errors[] = 'Plate number or Body number already exists on another bus.';
    }

    // Update if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE buses 
                SET plate_number = ?, body_number = ?, model = ?, capacity = ?, driver_id = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$plateNumber, $bodyNumber, $model, $capacity, ($driverId > 0 ? $driverId : null), $isActive, $id]);
            $success = true;
            
            // Refresh local bus data
            $bus['plate_number'] = $plateNumber;
            $bus['body_number']  = $bodyNumber;
            $bus['model']        = $model;
            $bus['capacity']     = $capacity;
            $bus['driver_id']    = ($driverId > 0 ? $driverId : null);
            $bus['is_active']    = $isActive;
            
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
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Edit Bus Details</h2>
                    <p class="text-slate-500 text-sm">Update vehicle information and manage driver assignment.</p>
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
                        <p class="font-bold">Changes Saved!</p>
                        <p class="text-sm opacity-90">Bus information has been successfully updated.</p>
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
                                   value="<?= htmlspecialchars($bus['body_number']) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 font-bold uppercase">
                        </div>

                        <!-- Plate Number -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Plate Number</label>
                            <input type="text" name="plate_number" required placeholder="ABC-1234"
                                   value="<?= htmlspecialchars($bus['plate_number']) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 font-bold uppercase">
                        </div>

                        <!-- Model -->
                        <div class="md:col-span-2">
                            <label class="block text-slate-700 text-sm font-bold mb-2">Bus Model</label>
                            <input type="text" name="model" placeholder="E-Jeepney CMCI 2023"
                                   value="<?= htmlspecialchars($bus['model'] ?? '') ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Capacity -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Seating Capacity</label>
                            <input type="number" name="capacity" required min="1" 
                                   value="<?= htmlspecialchars($bus['capacity']) ?>"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400">
                        </div>

                        <!-- Driver Assignment -->
                        <div>
                            <label class="block text-slate-700 text-sm font-bold mb-2">Assign Driver</label>
                            <select name="driver_id"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 appearance-none font-semibold">
                                <option value="0">-- No Driver (Unassigned) --</option>
                                <?php foreach ($drivers as $d): ?>
                                    <option value="<?= $d['id'] ?>" <?= ($bus['driver_id'] == $d['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($d['full_name']) ?> (<?= htmlspecialchars($d['license_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-slate-400 text-[10px] mt-1 italic">Only drivers not assigned to any other bus are shown.</p>
                        </div>

                        <!-- Status Toggle -->
                        <div class="md:col-span-2">
                             <label class="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" <?= $bus['is_active'] ? 'checked' : '' ?> 
                                       class="w-5 h-5 rounded text-blue-600 focus:ring-blue-500 border-slate-300">
                                <span class="text-slate-700 font-bold">This bus is currently active</span>
                             </label>
                             <p class="text-slate-400 text-xs mt-1 ml-8">Deactivating a bus will prevent it from starting new trips.</p>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-slate-100 flex gap-4">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition active:scale-95 text-lg">
                            Save Changes
                        </button>
                        <a href="buses.php" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-4 rounded-2xl text-center transition">
                            Cancel
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </main>
</div>
</body></html>
