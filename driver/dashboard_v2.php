<?php
/**
 * driver/dashboard.php   — STEP 9
 * Driver dashboard: start/end trip, simulate location, view passengers.
 */
$requiredRole = 'driver';
$pageTitle    = 'Driver Dashboard';
$currentPage  = 'dashboard.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions_v2.php';

$driverId = $_SESSION['driver_id'];

// Get this driver's permanently assigned bus
$busStmt = $pdo->prepare("SELECT * FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();

$activeTrip  = $bus ? getLiveTrip($pdo, (int)$bus['id'], (int)$driverId) : null;

// NEW: Check if the bus has ANY active trip (even if by another driver)
$busBusyTrip = null;
if ($bus && !$activeTrip) {
    $busBusyTrip = getLiveTrip($pdo, (int)$bus['id'], 0);
}

// Get all stations for Simulator OSRM fetching
$stationsList = $pdo->query("SELECT latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Today's stats
$statsStmt = $pdo->prepare(
    "SELECT COALESCE(SUM(t.fare_amount),0) AS revenue, COUNT(t.id) AS passengers
     FROM   tickets t
     JOIN   trips   tr ON tr.id = t.trip_id
     WHERE  tr.driver_id = ? AND DATE(t.issued_at) = CURDATE()"
);
$statsStmt->execute([$driverId]);
$todayStats = $statsStmt->fetch();

// Recent passengers in active trip
$recentPax = [];
if ($activeTrip) {
    $paxStmt = $pdo->prepare(
        "SELECT ticket_code, passenger_name, passenger_type, origin_name, dest_name, fare_amount, issued_at
         FROM   tickets WHERE trip_id = ? ORDER BY issued_at DESC LIMIT 10"
    );
    $paxStmt->execute([$activeTrip['id']]);
    $recentPax = $paxStmt->fetchAll();
}

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_driver.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Top bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Good <?= (date('H')<12 ? 'morning' : (date('H')<17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?>! 🚍</h2>
                <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <?php if ($activeTrip): ?>
            <span class="flex items-center gap-2 bg-orange-100 text-orange-700 font-bold px-5 py-2.5 rounded-full">
                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span> Trip Active
            </span>
            <?php else: ?>
            <span class="flex items-center gap-2 bg-slate-100 text-slate-500 font-bold px-5 py-2.5 rounded-full">
                <span class="w-2 h-2 bg-slate-400 rounded-full"></span> No Active Trip
            </span>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center">
                    <i class="ph ph-users text-2xl text-orange-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-sm">Today's Passengers</p>
                    <p class="text-3xl font-black text-slate-800"><?= (int)$todayStats['passengers'] ?></p>
                </div>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-100 flex items-center justify-center">
                    <i class="ph ph-money text-2xl text-emerald-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-sm">Today's Revenue</p>
                    <p class="text-3xl font-black text-emerald-700"><?= peso((float)$todayStats['revenue']) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center">
                    <i class="ph ph-bus text-2xl text-blue-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-sm">My Bus</p>
                    <p class="text-2xl font-black text-slate-800"><?= htmlspecialchars($bus['body_number'] ?? '—') ?></p>
                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($bus['plate_number'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Trip Control -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                    <i class="ph ph-steering-wheel text-orange-500"></i> Trip Control
                </h3>

                <?php if (!$bus): ?>
                <div class="text-center py-8 text-slate-400">
                    <i class="ph ph-warning-circle text-4xl mb-2"></i>
                    <p>No bus assigned to your account. Contact admin.</p>
                </div>
                <?php elseif ($activeTrip): ?>
                <!-- Active trip info -->
                <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-5">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                        <span class="font-bold text-orange-700 text-sm">Trip in progress</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><p class="text-slate-400 text-xs">From</p><p class="font-semibold"><?= htmlspecialchars($activeTrip['start_name']) ?></p></div>
                        <div><p class="text-slate-400 text-xs">To</p><p class="font-semibold"><?= htmlspecialchars($activeTrip['end_name']) ?></p></div>
                        <div><p class="text-slate-400 text-xs">Started</p><p class="font-semibold"><?= date('h:i A', strtotime($activeTrip['started_at'])) ?></p></div>
                        <div><p class="text-slate-400 text-xs">Passengers</p><p class="font-semibold" id="live-pax-count"><?= $activeTrip['passenger_count'] ?></p></div>
                    </div>
                </div>

                <button onclick="endTrip(<?= $activeTrip['id'] ?>)"
                        class="w-full bg-red-500 hover:bg-red-400 text-white font-black py-4 rounded-2xl transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="ph ph-stop-circle text-xl"></i> End Trip
                </button>

                <?php elseif ($busBusyTrip): ?>
                <!-- Bus is busy with another driver -->
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-5 text-center">
                    <i class="ph ph-warning-diamond text-4xl text-amber-500 mb-2"></i>
                    <p class="font-bold text-amber-800 text-sm">Bus Currently In Use</p>
                    <p class="text-amber-700 text-xs mt-1">Driver: <span class="font-black"><?= htmlspecialchars($busBusyTrip['driver_name']) ?></span></p>
                    <p class="text-amber-600 text-[10px] mt-2 italic">Contact admin to end the previous driver's trip (Trip ID: <?= $busBusyTrip['id'] ?>)</p>
                </div>
                <div class="grid grid-cols-2 gap-3 opacity-40 pointer-events-none">
                    <button class="bg-slate-200 text-slate-400 font-bold py-3 px-2 rounded-2xl flex flex-col items-center justify-center gap-1 shadow-sm text-center cursor-not-allowed">
                        <i class="ph ph-arrow-circle-right text-2xl"></i>
                        <span class="text-xs">Cabanatuan &rarr; Rizal</span>
                    </button>
                    <button class="bg-slate-200 text-slate-400 font-bold py-3 px-2 rounded-2xl flex flex-col items-center justify-center gap-1 shadow-sm text-center cursor-not-allowed">
                        <i class="ph ph-arrow-circle-left text-2xl"></i>
                        <span class="text-xs">Rizal &rarr; Cabanatuan</span>
                    </button>
                </div>

                <?php else: ?>
                <!-- Start trip -->
                <div class="bg-slate-50 rounded-2xl p-4 mb-5 text-sm text-slate-500 text-center">
                    <i class="ph ph-bus text-4xl text-slate-300 mb-2"></i>
                    <p>You have no active trip.</p>
                    <p>Select your direction to allow passengers to book.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="startTrip('forward')" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-2 rounded-2xl transition active:scale-95 flex flex-col items-center justify-center gap-1 shadow-sm text-center">
                        <i class="ph ph-arrow-circle-right text-2xl"></i>
                        <span class="text-xs">Cabanatuan &rarr; Rizal</span>
                    </button>
                    <button onclick="startTrip('backward')" class="bg-orange-600 hover:bg-orange-500 text-white font-bold py-3 px-2 rounded-2xl transition active:scale-95 flex flex-col items-center justify-center gap-1 shadow-sm text-center">
                        <i class="ph ph-arrow-circle-left text-2xl"></i>
                        <span class="text-xs">Rizal &rarr; Cabanatuan</span>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Location Simulator -->
                <?php if ($activeTrip): ?>
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <h4 class="text-sm font-bold text-slate-600 mb-2 flex items-center gap-2">
                        <i class="ph ph-navigation-arrow text-blue-500"></i> Location Tracking
                    </h4>

                    <!-- Kiosk handles GPS automatically -->
                    <div class="w-full rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2 bg-green-50 border border-green-200 text-green-700">
                        <span class="w-2 h-2 rounded-full bg-green-500 shrink-0 animate-pulse"></span>
                        <span>🖥️ Bus Kiosk GPS is active · Live Tracking enabled</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Passengers -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-700 flex items-center gap-2">
                        <i class="ph ph-users text-orange-500"></i> Passengers This Trip
                    </h3>
                    <?php if ($activeTrip): ?>
                    <div class="bg-emerald-100 text-emerald-800 px-4 py-2 rounded-xl text-right">
                        <p class="text-[10px] uppercase font-bold tracking-wider opacity-70">Cash to Collect/Remit</p>
                        <p class="font-black text-xl leading-none" id="live-cash-total">₱ 0.00</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="live-pax-list" class="space-y-2 overflow-auto max-h-[400px]">
                    <?php if (!$activeTrip): ?>
                    <div class="text-center py-10 text-slate-400">
                        <i class="ph ph-bus text-5xl mb-2"></i>
                        <p class="text-sm">Start a trip to see passengers</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-10 text-slate-400">
                        <i class="ph ph-spinner-gap animate-spin text-5xl mb-2 inline-block"></i>
                        <p class="text-sm">Syncing with Kiosk...</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


    </main>
</div>

<script>
let autoSimInterval = null;
let autoActive = false;

<?php if ($activeTrip): ?>
function fetchTripStats() {
    fetch('get_trip_stats.php?trip_id=<?= $activeTrip['id'] ?>')
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            document.getElementById('live-pax-list').innerHTML = `
                <div class="text-center py-10 text-red-400">
                    <i class="ph ph-warning-circle text-5xl mb-2"></i>
                    <p class="text-sm font-bold">Error syncing with Kiosk</p>
                    <p class="text-xs mt-1 text-red-300">${data.error || 'Unknown Error'}</p>
                </div>`;
            return;
        }
        
        // Update top counters
        document.getElementById('live-pax-count').innerText = data.total_passengers;
        document.getElementById('live-cash-total').innerText = '₱ ' + parseFloat(data.total_cash).toFixed(2);
        
        const list = document.getElementById('live-pax-list');
        if (data.recent_passengers.length === 0) {
            list.innerHTML = `
                <div class="text-center py-10 text-slate-400">
                    <i class="ph ph-ticket text-5xl mb-2"></i>
                    <p class="text-sm">No tickets printed yet</p>
                </div>`;
            return;
        }

        // Render live ticket list
        list.innerHTML = data.recent_passengers.map(p => {
            // Fix iOS/Safari date parsing issue by transforming "-" to "/"
            let dateStr = p.issued_at.replace(/-/g, '/');
            return `
            <div class="flex items-center gap-3 p-3 rounded-xl bg-white border border-slate-200 shadow-sm animate-[fadeIn_0.5s_ease-out]">
                <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0 text-blue-600">
                    <i class="ph ph-receipt text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2">
                        <p class="font-black text-slate-800 text-base">₱ ${parseFloat(p.fare_amount).toFixed(2)}</p>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">${p.passenger_type}</p>
                    </div>
                    <p class="text-xs text-slate-400 truncate">${p.origin_name} &rarr; ${p.dest_name}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[10px] font-bold text-emerald-600 uppercase bg-emerald-50 px-2 py-1 rounded">Collect Cash</p>
                    <p class="text-[10px] font-medium text-slate-400 mt-1">${new Date(dateStr).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                </div>
            </div>`;
        }).join('');
    })
    .catch(err => {
        document.getElementById('live-pax-list').innerHTML = `
            <div class="text-center py-10 text-rose-400">
                <i class="ph ph-wifi-slash text-5xl mb-2"></i>
                <p class="text-sm font-bold">Connection lost</p>
                <p class="text-xs mt-1 text-rose-300">Retrying...</p>
            </div>`;
    });
}
// Poll Kiosk via database every 3 seconds
setInterval(fetchTripStats, 3000);
fetchTripStats();
<?php endif; ?>

function startTrip(direction) {
    const dirTxt = direction === 'forward' ? 'Cabanatuan → Rizal' : 'Rizal → Cabanatuan';
    if (!confirm('Start a new trip? Route: ' + dirTxt)) return;
    
    fetch('start_trip.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ direction: direction })
    })
        .then(r => r.json())
        .then(d => {
            if (d.success) location.reload();
            else alert('Error: ' + d.message);
        });
}

function endTrip(tripId) {
    if (!confirm('End this trip? This will close all bookings for this run.')) return;
    fetch('end_trip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trip_id: tripId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) location.reload();
        else alert('Error: ' + d.message);
    });
}

</script>

<?php include '../includes/mobile_nav_driver.php'; ?>
</body>
</html>
