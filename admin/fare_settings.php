<?php
$requiredRole = 'admin';
require_once '../config/db.php';
require_once '../includes/auth_guard.php';

$pageTitle = 'Fare Matrix Settings';
$currentPage = 'fare_settings.php';
$message = '';
$status = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fares'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE fare_matrix SET base_km = ?, base_fare = ?, per_km_rate = ? WHERE passenger_type = ?");
        
        // Loop through POST data to dynamically update types
        foreach (['Regular', 'Student/SR/PWD', 'Teacher/Nurse'] as $type) {
            $typeKey = str_replace([' ', '/'], '_', strtolower($type));
            
            $b_km = $_POST["base_km_$typeKey"] ?? 4.00;
            $b_fare = $_POST["base_fare_$typeKey"] ?? 0;
            $p_rate = $_POST["per_km_rate_$typeKey"] ?? 0;
            
            $stmt->execute([$b_km, $b_fare, $p_rate, $type]);
        }
        
        $pdo->commit();
        $message = "Fare matrix updated successfully!";
        $status = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error updating fares: " . $e->getMessage();
        $status = "error";
    }
}

// Fetch Current Rates
$rates = [];
$stmt = $pdo->query("SELECT * FROM fare_matrix");
while ($row = $stmt->fetch()) {
    $rates[$row['passenger_type']] = $row;
}

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Top Bar -->
        <header class="bg-white px-8 py-5 border-b border-slate-200 flex items-center justify-between sticky top-0 z-10">
            <h2 class="text-2xl font-black text-slate-800 drop-shadow-sm flex items-center gap-3">
                Fare Matrix Settings
            </h2>
        </header>

        <!-- Content -->
        <div class="px-8 py-4 max-w-6xl">
            
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl flex items-center gap-3 font-semibold shadow-sm <?= $status === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                    <i class="ph <?= $status === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-2xl"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <h3 class="text-xl font-bold mb-1">Global LTFRB Formula Settings</h3>
                <p class="text-slate-500 text-sm">
                    Modify the base starting fare and per-kilometer add-on. This automatically calculates accurate fares across Kiosk, Web, and Mobile App for all distances.
                </p>
                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 mt-3 inline-flex items-center gap-4 text-indigo-800 shadow-sm">
                    <i class="ph ph-math-operations text-2xl"></i>
                    <p class="font-mono text-xs tracking-wide">
                        <strong>Formula:</strong> Total Fare = Base Fare + (Extra Kilometers × Per KM Rate)
                    </p>
                </div>
            </div>

            <form method="POST" action="" class="space-y-4">
                <!-- Grid for the 3 Passenger Types -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <?php 
                    $typeConfigs = [
                        'Regular' => ['icon' => 'ph-user', 'color' => 'blue', 'title' => 'Regular'],
                        'Student/SR/PWD' => ['icon' => 'ph-student', 'color' => 'amber', 'title' => 'Student / Senior / PWD'],
                        'Teacher/Nurse' => ['icon' => 'ph-heart', 'color' => 'pink', 'title' => 'Special (Teacher / Nurse)'],
                    ];

                    foreach (['Regular', 'Student/SR/PWD', 'Teacher/Nurse'] as $type): 
                        $cfg = $typeConfigs[$type];
                        $rate = $rates[$type] ?? ['base_km' => 4.00, 'base_fare' => 0.00, 'per_km_rate' => 0.00];
                        $inputPrefix = str_replace([' ', '/'], '_', strtolower($type));
                    ?>
                    <!-- Card -->
                    <div class="bg-white rounded-3xl p-5 shadow-xl border-t-4 border-<?= $cfg['color'] ?>-500 relative overflow-hidden group">
                        <div class="absolute -right-6 -top-6 opacity-5 pointer-events-none transition-transform group-hover:scale-110">
                            <i class="ph <?= $cfg['icon'] ?> text-8xl"></i>
                        </div>
                        
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-<?= $cfg['color'] ?>-100 flex items-center justify-center shrink-0">
                                <i class="ph <?= $cfg['icon'] ?> text-2xl text-<?= $cfg['color'] ?>-600"></i>
                            </div>
                            <h3 class="text-lg font-bold text-slate-800 tracking-tight leading-tight">
                                <?= $cfg['title'] ?>
                            </h3>
                        </div>

                        <div class="space-y-3 relative">
                            <!-- Base Fare -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Base Fare (₱)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 font-bold">₱</span>
                                    <input type="number" step="0.01" name="base_fare_<?= $inputPrefix ?>" value="<?= htmlspecialchars($rate['base_fare']) ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl py-2.5 pl-8 pr-3 font-black text-lg focus:outline-none focus:border-<?= $cfg['color'] ?>-500 transition-colors">
                                </div>
                            </div>
                            
                            <div class="flex gap-3">
                                <!-- Base KMs -->
                                <div class="flex-1">
                                    <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">Base KMs</label>
                                    <input type="number" step="0.1" name="base_km_<?= $inputPrefix ?>" value="<?= htmlspecialchars($rate['base_km']) ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl py-2 px-3 font-bold text-sm focus:outline-none focus:border-<?= $cfg['color'] ?>-500 transition-colors text-center">
                                </div>
                                
                                <!-- Per KM Add-on -->
                                <div class="flex-1">
                                    <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-widest mb-1">+ /KM (₱)</label>
                                    <input type="number" step="0.01" name="per_km_rate_<?= $inputPrefix ?>" value="<?= htmlspecialchars($rate['per_km_rate']) ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl py-2 px-3 font-bold text-sm focus:outline-none focus:border-<?= $cfg['color'] ?>-500 transition-colors text-center">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" name="update_fares" class="bg-blue-600 hover:bg-blue-500 text-white font-black text-base py-3 px-8 rounded-xl shadow-lg shadow-blue-600/30 transition-all flex items-center gap-2">
                        <i class="ph ph-floppy-disk text-xl"></i>
                        Save New Matrix
                    </button>
                </div>
            </form>
            
        </div>
    </main>

</body>
</html>
