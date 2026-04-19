<?php
/**
 * passenger/dashboard.php
 * Full passenger dashboard: stats, live fleet, fare calculator, recent rides.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Dashboard';
$currentPage  = 'dashboard.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions_v2.php';

$uid = $_SESSION['user_id'];

// --- Passenger profile + stats ---
$profileStmt = $pdo->prepare("SELECT full_name, id_number, discount_type, id_picture FROM users WHERE id = ?");
$profileStmt->execute([$uid]);
$profile = $profileStmt->fetch();

// --- All active buses ---
$fleetStmt = $pdo->query(
    "SELECT b.id, b.body_number, b.plate_number, b.model,
            d.full_name AS driver_name,
            t.id AS trip_id, t.status AS trip_status, t.started_at,
            (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND id NOT IN (SELECT ticket_id FROM payments)) AS passenger_count,
            s1.station_name AS start_name, s2.station_name AS end_name,
            bl.latitude, bl.longitude, bl.speed_kmh, bl.recorded_at AS loc_time
     FROM buses b
     JOIN drivers d ON d.id = b.driver_id
     LEFT JOIN trips t ON t.bus_id = b.id AND t.status = 'active'
     LEFT JOIN stations s1 ON s1.id = t.start_station_id
     LEFT JOIN stations s2 ON s2.id = t.end_station_id
     LEFT JOIN (
         SELECT bl1.* FROM bus_locations bl1
         INNER JOIN (SELECT bus_id, MAX(recorded_at) AS max_time FROM bus_locations GROUP BY bus_id) bl2
         ON bl1.bus_id = bl2.bus_id AND bl1.recorded_at = bl2.max_time
     ) bl ON bl.bus_id = b.id
     WHERE b.is_active = 1
     GROUP BY b.id
     ORDER BY t.status DESC, b.body_number ASC"
);
$fleet = $fleetStmt->fetchAll();

// --- Stations for fare calculator ---
$stations = $pdo->query("SELECT id, station_name, km_marker FROM stations WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();

// --- Recent rides ---
$ridesStmt = $pdo->prepare(
    "SELECT tk.ticket_code, tk.origin_name, tk.dest_name, tk.fare_amount, tk.passenger_type, tk.issued_at, tk.distance_km,
            b.body_number
     FROM tickets tk
     JOIN trips tr ON tr.id = tk.trip_id
     JOIN buses b ON b.id = tr.bus_id
     WHERE tk.passenger_id = ?
     ORDER BY tk.issued_at DESC
     LIMIT 5"
);
$ridesStmt->execute([$uid]);
$recentRides = $ridesStmt->fetchAll();

$discountType = $profile['discount_type'] ?? 'none';
$discountLabels = [
    'none' => ['Regular', 'bg-slate-100 text-slate-600', 'ph-user'],
    'student' => ['Student', 'bg-blue-100 text-blue-700', 'ph-graduation-cap'],
    'senior' => ['Senior Citizen', 'bg-amber-100 text-amber-700', 'ph-heart'],
    'pwd' => ['PWD', 'bg-purple-100 text-purple-700', 'ph-wheelchair'],
    'teacher' => ['Teacher', 'bg-emerald-100 text-emerald-700', 'ph-chalkboard-teacher'],
    'nurse' => ['Nurse', 'bg-pink-100 text-pink-700', 'ph-first-aid'],
];
$dl = $discountLabels[$discountType] ?? $discountLabels['none'];

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Welcome + Stats Row -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Welcome back, <br class="md:hidden"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>! 👋</h2>
                <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 <?= $dl[1] ?> text-xs font-bold px-4 py-2 rounded-full">
                    <i class="ph <?= $dl[2] ?>"></i> <?= $dl[0] ?>
                </span>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <!-- Passenger ID -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center">
                    <i class="ph ph-identification-card text-2xl text-indigo-600"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Passenger ID</p>
                    <p class="text-xl font-black text-slate-800 truncate"><?= htmlspecialchars($profile['id_number'] ?? 'N/A') ?></p>
                </div>
            </div>

            <!-- Last Trip -->
            <?php 
            $lastTrip = $recentRides[0] ?? null;
            $dest = $lastTrip ? htmlspecialchars($lastTrip['dest_name']) : 'No rides yet';
            $date = $lastTrip ? date('M d', strtotime($lastTrip['issued_at'])) : 'New Account';
            ?>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center">
                    <i class="ph ph-map-pin text-2xl text-orange-600"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Last Trip</p>
                    <p class="text-xl font-black text-slate-800 truncate"><?= $dest ?></p>
                    <p class="text-[10px] text-slate-400 font-medium"><?= $date ?></p>
                </div>
            </div>

            <!-- Discount Type -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl <?= str_replace('text-', 'bg-', explode(' ', $dl[1])[0]) ?> flex items-center justify-center">
                    <i class="ph <?= $dl[2] ?> text-2xl <?= explode(' ', $dl[1])[1] ?>"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Discount Type</p>
                    <p class="text-xl font-black text-slate-800"><?= $dl[0] ?></p>
                </div>
            </div>
        </div>

        <!-- Main Content: 2 columns -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-8">

            <!-- Live Fleet Status (3 col) -->
            <div class="lg:col-span-3 bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-700 flex items-center gap-2">
                        <i class="ph ph-bus text-blue-600"></i> Live Fleet Status
                    </h3>
                    <a href="map.php" class="text-blue-600 text-xs font-bold hover:text-blue-800 flex items-center gap-1">
                        <i class="ph ph-map-trifold"></i> View Map
                    </a>
                </div>

                <div class="space-y-3">
                    <?php if (empty($fleet)): ?>
                    <div class="text-center py-10 text-slate-400">
                        <i class="ph ph-bus text-5xl mb-2"></i>
                        <p class="text-sm">No buses registered yet</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($fleet as $b): ?>
                    <div class="flex items-center gap-4 p-4 rounded-2xl border <?= $b['trip_id'] ? 'border-green-200 bg-green-50/50' : 'border-slate-100 bg-slate-50/50' ?> transition hover:shadow-sm">
                        <div class="w-12 h-12 rounded-xl <?= $b['trip_id'] ? 'bg-green-100' : 'bg-slate-200' ?> flex items-center justify-center shrink-0">
                            <i class="ph ph-bus text-xl <?= $b['trip_id'] ? 'text-green-600' : 'text-slate-400' ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-black text-slate-800"><?= htmlspecialchars($b['body_number']) ?></p>
                                <span class="text-slate-400 text-xs"><?= htmlspecialchars($b['plate_number']) ?></span>
                            </div>
                            <?php if ($b['trip_id']): ?>
                            <p class="text-xs text-slate-500 truncate">
                                <?= htmlspecialchars($b['start_name']) ?> → <?= htmlspecialchars($b['end_name']) ?>
                            </p>
                            <?php else: ?>
                            <p class="text-xs text-slate-400 italic">Not running</p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right shrink-0">
                            <?php if ($b['trip_id']): ?>
                            <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-[10px] font-bold px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> On Route
                            </span>
                            <p class="text-xs text-slate-500 mt-1"><?= $b['passenger_count'] ?> pax · <?= htmlspecialchars($b['driver_name']) ?></p>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-500 text-[10px] font-bold px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span> Idle
                            </span>
                            <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($b['driver_name']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fare Calculator (2 col) -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                    <i class="ph ph-calculator text-emerald-600"></i> Fare Calculator
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-slate-500 text-xs font-bold mb-1.5 uppercase tracking-wider">From</label>
                        <select id="fare-from" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 text-sm font-medium">
                            <option value="">Select origin...</option>
                            <?php foreach ($stations as $s): ?>
                            <option value="<?= $s['km_marker'] ?>"><?= htmlspecialchars($s['station_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-500 text-xs font-bold mb-1.5 uppercase tracking-wider">To</label>
                        <select id="fare-to" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-400 text-sm font-medium">
                            <option value="">Select destination...</option>
                            <?php foreach ($stations as $s): ?>
                            <option value="<?= $s['km_marker'] ?>"><?= htmlspecialchars($s['station_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Result -->
                    <div id="fare-result" class="hidden">
                        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-5 text-white">
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-200 mb-3">Estimated Fare</p>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-100 flex items-center gap-1.5"><i class="ph ph-user"></i> Regular</span>
                                    <span id="fare-regular" class="text-xl font-black">—</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-100 flex items-center gap-1.5"><i class="ph ph-graduation-cap"></i> Student</span>
                                    <span id="fare-student" class="text-xl font-black">—</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-100 flex items-center gap-1.5"><i class="ph ph-heart"></i> Special</span>
                                    <span id="fare-special" class="text-xl font-black">—</span>
                                </div>
                            </div>
                            <div class="mt-3 pt-3 border-t border-white/20 flex items-center justify-between text-xs text-blue-200">
                                <span><i class="ph ph-path"></i> Distance: <span id="fare-distance" class="font-bold text-white">—</span></span>
                            </div>
                        </div>
                    </div>

                    <div id="fare-empty" class="text-center py-6 text-slate-400">
                        <i class="ph ph-calculator text-4xl mb-2 block"></i>
                        <p class="text-xs">Select stations to calculate fare</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Rides -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-5">
                <h3 class="font-bold text-slate-700 flex items-center gap-2">
                    <i class="ph ph-clock-counter-clockwise text-orange-500"></i> Recent Rides
                </h3>
                <?php if (count($recentRides) > 0): ?>
                <a href="rides.php" class="text-blue-600 text-xs font-bold hover:text-blue-800 flex items-center gap-1">
                    View All <i class="ph ph-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($recentRides)): ?>
            <div class="text-center py-10 text-slate-400">
                <i class="ph ph-ticket text-5xl mb-3 block"></i>
                <p class="text-sm font-medium">No ride history yet</p>
                <p class="text-xs mt-1">Your rides will appear here after you use the kiosk</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            <th class="py-3 pr-4">Ticket</th>
                            <th class="py-3 pr-4">Route</th>
                            <th class="py-3 pr-4">Bus</th>
                            <th class="py-3 pr-4">Type</th>
                            <th class="py-3 pr-4 text-right">Fare</th>
                            <th class="py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($recentRides as $r): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 pr-4">
                                <span class="font-mono font-bold text-blue-700 text-xs"><?= htmlspecialchars($r['ticket_code']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4">
                                <span class="text-slate-700"><?= htmlspecialchars($r['origin_name']) ?></span>
                                <span class="text-slate-400 mx-1">→</span>
                                <span class="text-slate-700"><?= htmlspecialchars($r['dest_name']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4">
                                <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-1 rounded-lg"><?= htmlspecialchars($r['body_number']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4">
                                <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars($r['passenger_type']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4 text-right font-black text-slate-800"><?= peso((float)$r['fare_amount']) ?></td>
                            <td class="py-3.5 text-right text-slate-400 text-xs"><?= date('M d, Y', strtotime($r['issued_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
// Fare Calculator
const fareFrom = document.getElementById('fare-from');
const fareTo = document.getElementById('fare-to');
const fareResult = document.getElementById('fare-result');
const fareEmpty = document.getElementById('fare-empty');

function calcFare() {
    const from = parseFloat(fareFrom.value);
    const to = parseFloat(fareTo.value);
    if (isNaN(from) || isNaN(to) || from === to) {
        fareResult.classList.add('hidden');
        fareEmpty.classList.remove('hidden');
        return;
    }
    const dist = Math.abs(to - from);
    // Fetch from API
    fetch('get_fare_estimate.php?distance=' + dist)
        .then(r => r.json())
        .then(data => {
            document.getElementById('fare-regular').textContent = '₱ ' + parseFloat(data.regular).toFixed(2);
            document.getElementById('fare-student').textContent = '₱ ' + parseFloat(data.student).toFixed(2);
            document.getElementById('fare-special').textContent = '₱ ' + parseFloat(data.special).toFixed(2);
            document.getElementById('fare-distance').textContent = dist.toFixed(1) + ' km';
            fareResult.classList.remove('hidden');
            fareEmpty.classList.add('hidden');
        });
}

fareFrom.addEventListener('change', calcFare);
fareTo.addEventListener('change', calcFare);
</script>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body></html>
