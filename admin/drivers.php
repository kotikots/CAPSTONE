<?php
/**
 * admin/drivers.php
 * Admin view of all drivers with trip stats and assigned bus.
 */
$requiredRole = 'admin';
$pageTitle    = 'Drivers';
$currentPage  = 'drivers.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_driver_id'])) {
    $toggleId = (int)$_POST['toggle_driver_id'];
    $newState = (int)$_POST['new_state'];
    $pdo->prepare("UPDATE drivers SET is_active = ? WHERE id = ?")->execute([$newState, $toggleId]);
    header('Location: drivers.php');
    exit;
}

$drivers = $pdo->query(
    "SELECT d.*,
            b.body_number, b.plate_number, b.model,
            COUNT(DISTINCT tr.id)   AS total_trips,
            COUNT(DISTINCT t.id)    AS total_tickets,
            COALESCE(SUM(t.fare_amount), 0) AS total_revenue
     FROM   drivers d
     LEFT JOIN buses   b  ON b.driver_id = d.id AND b.is_active = 1
     LEFT JOIN trips   tr ON tr.driver_id = d.id
     LEFT JOIN tickets t  ON t.trip_id   = tr.id
     GROUP  BY d.id
     ORDER  BY total_revenue DESC"
)->fetchAll();

// Fetch available buses for the selection modal
$availableBuses = $pdo->query("SELECT id, body_number, plate_number, model FROM buses WHERE driver_id IS NULL AND is_active = 1 ORDER BY body_number ASC")->fetchAll();

// Check for active trips to display warnings
$activeTripDrivers = $pdo->query("SELECT DISTINCT driver_id FROM trips WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="flex-1 p-8 overflow-auto bg-slate-50">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Drivers</h2>
                <p class="text-slate-500 text-sm"><?= count($drivers) ?> active driver(s)</p>
            </div>
            <a href="add_driver.php" 
               class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold px-5 py-3 rounded-2xl shadow-lg hover:shadow-blue-600/30 transition active:scale-95">
                <i class="ph ph-plus-circle text-xl"></i> Add New Driver
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($drivers as $d): ?>
            <div id="card-<?= $d['id'] ?>" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 transition-opacity <?= $d['is_active'] ? '' : 'opacity-60 grayscale-[0.5]' ?>">
                <!-- Driver Header -->
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center shrink-0">
                        <?php if ($d['profile_picture']): ?>
                        <img src="/PARE/<?= htmlspecialchars($d['profile_picture']) ?>" class="w-full h-full object-cover rounded-2xl">
                        <?php else: ?>
                        <i class="ph ph-steering-wheel text-2xl text-orange-500"></i>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-black text-slate-800 truncate"><?= htmlspecialchars($d['full_name']) ?></p>
                        <p class="text-slate-400 text-xs">License: <?= htmlspecialchars($d['license_number']) ?></p>
                        <span id="badge-<?= $d['id'] ?>" class="inline-flex justify-center w-16 mt-1 <?= $d['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?> text-[10px] uppercase font-bold px-2 py-0.5 rounded-full">
                            <?= $d['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>

                <!-- Bus Assignment -->
                <div class="bg-orange-50 rounded-2xl px-4 py-3 mb-4 flex items-center justify-between gap-3 h-[60px]">
                    <div class="flex items-center gap-3">
                        <i class="ph ph-bus text-orange-500 text-xl shrink-0"></i>
                        <?php if ($d['body_number']): ?>
                        <div>
                            <p class="font-bold text-orange-800 text-sm"><?= htmlspecialchars($d['body_number']) ?></p>
                            <p class="text-orange-500 text-xs"><?= htmlspecialchars($d['plate_number']) ?> · <?= htmlspecialchars($d['model'] ?? '') ?></p>
                        </div>
                        <?php else: ?>
                        <p class="text-orange-400 text-sm italic">No bus assigned</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php 
                    $isOnRoute = in_array($d['id'], $activeTripDrivers);
                    $btnClass = $d['body_number'] ? 'bg-slate-200 text-slate-600' : 'bg-orange-600 text-white';
                    $btnText = $d['body_number'] ? 'Manage' : 'Assign Bus';
                    ?>
                    
                    <button onclick="openAssignModal(<?= $d['id'] ?>, '<?= addslashes($d['full_name']) ?>', '<?= $d['body_number'] ?? '' ?>', <?= $isOnRoute ? 'true' : 'false' ?>)"
                            class="<?= $btnClass ?> text-[10px] uppercase font-black px-3 py-1.5 rounded-lg hover:opacity-80 transition shadow-sm shrink-0">
                        <?= $btnText ?>
                    </button>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <?php foreach ([
                        ['Trips',      $d['total_trips'],   'ph-map-pin'],
                        ['Tickets',    $d['total_tickets'], 'ph-ticket'],
                    ] as [$label, $val, $icon]): ?>
                    <div class="text-center p-2 bg-slate-50 rounded-xl">
                        <p class="font-black text-slate-800"><?= number_format((int)$val) ?></p>
                        <p class="text-slate-400 text-xs"><?= $label ?></p>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center p-2 bg-emerald-50 rounded-xl col-span-1">
                        <p class="font-black text-emerald-700 text-sm"><?= peso((float)$d['total_revenue']) ?></p>
                        <p class="text-slate-400 text-xs">Revenue</p>
                    </div>
                </div>

                <!-- Contact -->
                <div class="space-y-1 text-xs text-slate-500 border-t border-slate-100 pt-3 mb-4">
                    <p class="flex items-center gap-1.5"><i class="ph ph-phone"></i><?= htmlspecialchars($d['contact_number']) ?></p>
                    <?php if ($d['email']): ?>
                    <p class="flex items-center gap-1.5"><i class="ph ph-envelope"></i><?= htmlspecialchars($d['email']) ?></p>
                    <?php endif; ?>
                    <p class="flex items-center gap-1.5"><i class="ph ph-calendar"></i>Joined <?= date('M d, Y', strtotime($d['created_at'])) ?></p>
                </div>

                <div class="flex items-center gap-2 pt-4 border-t border-slate-100">
                    <button id="toggle-btn-<?= $d['id'] ?>"
                            onclick="toggleDriverStatus(<?= $d['id'] ?>, <?= $d['is_active'] ? 0 : 1 ?>, '<?= addslashes($d['full_name']) ?>')" 
                            class="w-full py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all
                                   <?= $d['is_active'] ? 'bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-500' : 'bg-emerald-600 text-white hover:bg-emerald-500 shadow-lg' ?>">
                        <i id="icon-<?= $d['id'] ?>" class="ph <?= $d['is_active'] ? 'ph-power' : 'ph-check-circle' ?> text-lg"></i>
                        <span id="text-<?= $d['id'] ?>"><?= $d['is_active'] ? 'Deactivate Account' : 'Activate Account' ?></span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script>
async function toggleDriverStatus(driverId, newState, name) {
    const verb = newState ? 'Activate' : 'Deactivate';
    
    const confirmed = await window.showConfirm({
        title: `${verb} Driver Account?`,
        message: `Are you sure you want to ${verb.toLowerCase()} access for ${name}?`,
        type: newState ? 'info' : 'danger',
        confirmText: `Yes, ${verb}`
    });

    if (!confirmed) return;

    try {
        const res = await fetch('api_toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'driver', id: driverId, state: newState })
        });
        
        const data = await res.json();
        
        if (data.success) {
            window.showToast(
                `Driver ${verb}d`, 
                `${name} has been ${verb.toLowerCase()}d successfully.`,
                newState ? 'success' : 'info'
            );

            // Update UI state dynamically
            const card = document.getElementById(`card-${driverId}`);
            const badge = document.getElementById(`badge-${driverId}`);
            const btn = document.getElementById(`toggle-btn-${driverId}`);
            const icon = document.getElementById(`icon-${driverId}`);
            const text = document.getElementById(`text-${driverId}`);
            
            if (newState) {
                card.classList.remove('opacity-60', 'grayscale-[0.5]');
                badge.className = 'inline-flex justify-center w-16 mt-1 bg-emerald-100 text-emerald-700 text-[10px] uppercase font-bold px-2 py-0.5 rounded-full';
                badge.textContent = 'Active';
                btn.className = 'w-full py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all bg-slate-100 text-slate-500 hover:bg-red-50 hover:text-red-500';
                icon.className = 'ph ph-power text-lg';
                text.textContent = 'Deactivate Account';
                btn.onclick = () => toggleDriverStatus(driverId, 0, name);
            } else {
                card.classList.add('opacity-60', 'grayscale-[0.5]');
                badge.className = 'inline-flex justify-center w-16 mt-1 bg-red-100 text-red-600 text-[10px] uppercase font-bold px-2 py-0.5 rounded-full';
                badge.textContent = 'Inactive';
                btn.className = 'w-full py-2.5 rounded-xl font-bold text-xs uppercase tracking-wider flex items-center justify-center gap-2 transition-all bg-emerald-600 text-white hover:bg-emerald-500 shadow-lg';
                icon.className = 'ph ph-check-circle text-lg';
                text.textContent = 'Activate Account';
                btn.onclick = () => toggleDriverStatus(driverId, 1, name);
            }
        } else {
            window.showToast('Error', data.message, 'error');
        }
    } catch (err) {
        window.showToast('Network Error', 'Could not reach the server.', 'error');
    }
}
</script>
    </main>
</div>

<!-- Assign Bus Modal -->
<div id="assignModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-orange-50/50">
            <div>
                <h3 class="font-black text-slate-800 tracking-tight">Assign Vehicle</h3>
                <p id="modalDriverName" class="text-orange-600 text-xs font-bold uppercase tracking-wider mt-0.5"></p>
            </div>
            <button onclick="closeAssignModal()" class="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-600 transition">
                <i class="ph ph-x font-bold"></i>
            </button>
        </div>
        
        <form action="assign_bus_handler.php" method="POST" class="p-6 space-y-6">
            <input type="hidden" name="driver_id" id="modalDriverId">
            
            <div id="routeWarning" class="hidden bg-red-50 border border-red-100 p-4 rounded-2xl flex gap-3 text-red-800 mb-2">
                <i class="ph ph-warning-circle text-xl shrink-0 text-red-500"></i>
                <div class="text-xs">
                    <p class="font-bold">Driver is currently On Route!</p>
                    <p class="opacity-80">Reassigning during an active trip may cause data confusion. Proceed with caution.</p>
                </div>
            </div>

            <div>
                <label class="block text-slate-700 text-sm font-bold mb-3">Available Vehicles</label>
                <div class="grid grid-cols-1 gap-3">
                    <!-- Unassign Option (if applicable) -->
                    <label id="unassignOption" class="flex items-center gap-3 p-4 bg-slate-50 border-2 border-slate-100 rounded-2xl cursor-pointer hover:border-orange-200 transition">
                        <input type="radio" name="bus_id" value="0" class="w-5 h-5 text-orange-600 focus:ring-orange-500 border-slate-300">
                        <div>
                            <p class="font-black text-slate-800 text-sm">No Bus (Unassign Current)</p>
                            <p class="text-slate-400 text-[10px] uppercase font-bold tracking-widest">Free up vehicle</p>
                        </div>
                    </label>

                    <?php foreach ($availableBuses as $bus): ?>
                    <label class="flex items-center gap-3 p-4 border-2 border-slate-100 rounded-2xl cursor-pointer hover:border-orange-200 transition has-[:checked]:border-orange-600 has-[:checked]:bg-orange-50">
                        <input type="radio" name="bus_id" value="<?= $bus['id'] ?>" class="w-5 h-5 text-orange-600 focus:ring-orange-500 border-slate-300">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="font-black text-slate-800 text-sm"><?= htmlspecialchars($bus['body_number']) ?></p>
                                <span class="bg-white border border-slate-200 px-2 py-0.5 rounded text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($bus['plate_number']) ?></span>
                            </div>
                            <p class="text-slate-500 text-[10px] font-bold mt-0.5"><?= htmlspecialchars($bus['model'] ?? 'Standard Bus') ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>

                    <?php if (empty($availableBuses)): ?>
                        <div class="p-8 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200">
                            <i class="ph ph-bus-slash text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-400 text-xs font-bold">No available buses in fleet.</p>
                            <a href="add_bus.php" class="text-orange-600 text-[10px] font-black uppercase mt-2 inline-block">Register New Bus</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closeAssignModal()" class="flex-1 py-3 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="flex-[2] py-3 px-4 bg-orange-600 hover:bg-orange-500 text-white font-black rounded-xl shadow-lg shadow-orange-500/20 transition active:scale-95">
                    Confirm Assignment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignModal(driverId, driverName, currentBus, isOnRoute) {
    const modal = document.getElementById('assignModal');
    const nameEl = document.getElementById('modalDriverName');
    const idInput = document.getElementById('modalDriverId');
    const warning = document.getElementById('routeWarning');
    const unassign = document.getElementById('unassignOption');

    nameEl.textContent = driverName;
    idInput.value = driverId;
    
    // Show warning if on route
    if (isOnRoute) {
        warning.classList.remove('hidden');
    } else {
        warning.classList.add('hidden');
    }

    // Only show unassign option if the driver HAS a bus currently
    if (currentBus) {
        unassign.classList.remove('hidden');
    } else {
        unassign.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeAssignModal() {
    const modal = document.getElementById('assignModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close on background click
document.getElementById('assignModal').addEventListener('click', function(e) {
    if (e.target === this) closeAssignModal();
});
</script>

</body></html>
