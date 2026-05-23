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
                <h2 class="text-2xl font-black text-slate-800">Good <?= (date('H')<12 ? 'morning' : (date('H')<17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?>!</h2>
                <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <?php if ($activeTrip): ?>
            <span class="flex items-center gap-2 bg-orange-100 text-orange-700 border border-orange-200 font-bold px-5 py-2.5 rounded-full shadow-sm">
                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(249,115,22,0.8)]"></span> Trip Active
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
                <div class="w-14 h-14 rounded-2xl bg-orange-200 flex items-center justify-center shadow-inner">
                    <i class="ph ph-users text-2xl text-orange-700"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-semibold">Today's Passengers</p>
                    <p class="text-3xl font-black text-slate-800"><?= (int)$todayStats['passengers'] ?></p>
                </div>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-200 flex items-center justify-center shadow-inner">
                    <i class="ph ph-money text-2xl text-emerald-700"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-semibold">Today's Revenue</p>
                    <p class="text-3xl font-black text-emerald-700"><?= peso((float)$todayStats['revenue']) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-200 flex items-center justify-center shadow-inner">
                    <i class="ph ph-bus text-2xl text-amber-700"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-semibold">My Bus</p>
                    <p class="text-2xl font-black text-slate-800"><?= htmlspecialchars($bus['body_number'] ?? '—') ?></p>
                    <p class="text-slate-400 text-xs font-bold"><?= htmlspecialchars($bus['plate_number'] ?? '') ?></p>
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
                <div class="bg-orange-100 border border-orange-300 rounded-2xl p-4 mb-5 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-orange-600 rounded-full animate-pulse shadow-[0_0_8px_rgba(234,88,12,0.8)]"></span>
                        <span class="font-black text-orange-800 text-sm">Trip in progress</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><p class="text-orange-900/60 text-[10px] uppercase font-bold tracking-wider">From</p><p class="font-black text-orange-950"><?= htmlspecialchars($activeTrip['start_name']) ?></p></div>
                        <div><p class="text-orange-900/60 text-[10px] uppercase font-bold tracking-wider">To</p><p class="font-black text-orange-950"><?= htmlspecialchars($activeTrip['end_name']) ?></p></div>
                        <div><p class="text-orange-900/60 text-[10px] uppercase font-bold tracking-wider">Started</p><p class="font-black text-orange-950"><?= date('h:i A', strtotime($activeTrip['started_at'])) ?></p></div>
                        <div><p class="text-orange-900/60 text-[10px] uppercase font-bold tracking-wider">Passengers</p><p class="font-black text-orange-950" id="live-pax-count"><?= $activeTrip['passenger_count'] ?></p></div>
                    </div>
                </div>

                <button onclick="endTrip(<?= $activeTrip['id'] ?>)"
                        class="w-full bg-red-500 hover:bg-red-400 text-white font-black py-4 rounded-2xl transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="ph ph-stop-circle text-xl"></i> End Trip
                </button>

                <?php elseif ($busBusyTrip): ?>
                <!-- Bus is busy with another driver -->
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 mb-5 text-center shadow-[inset_0_0_20px_rgba(245,158,11,0.05)]">
                    <i class="ph ph-warning-diamond text-4xl text-amber-400 mb-2 drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]"></i>
                    <p class="font-bold text-amber-400 text-sm">Bus Currently In Use</p>
                    <p class="text-amber-300 text-xs mt-1">Driver: <span class="font-black text-amber-200"><?= htmlspecialchars($busBusyTrip['driver_name']) ?></span></p>
                    <p class="text-amber-500/80 text-[10px] mt-2 italic">Contact admin to end the previous driver's trip (Trip ID: <?= $busBusyTrip['id'] ?>)</p>
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
                    <button onclick="startTrip('forward')" class="bg-amber-600 hover:bg-amber-500 text-white font-bold py-3 px-2 rounded-2xl transition active:scale-95 flex flex-col items-center justify-center gap-1 shadow-sm text-center">
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
                        <i class="ph ph-navigation-arrow text-amber-500"></i> Location Tracking
                    </h4>

                    <!-- Kiosk handles GPS automatically -->
                    <div class="w-full rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2 bg-emerald-100 border border-emerald-300 text-emerald-800 mb-3 shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-600 shrink-0 animate-pulse"></span>
                        <span>🖥️ Bus Kiosk GPS is active · Live Tracking enabled</span>
                    </div>

                    <!-- Developer Override for Testing on Desktop -->
                    <div class="flex items-center gap-2 mt-2">
                        <select id="mock-gps-select" class="flex-1 bg-white border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-600 focus:outline-none">
                            <option value="">Use Actual Device GPS (ISP/Hardware)</option>
                            <option value="16.1558,119.9806">Mock GPS: Alaminos, Pangasinan</option>
                            <option value="15.4859,120.9665">Mock GPS: Cabanatuan City</option>
                            <option value="15.5771,121.0560">Mock GPS: Rizal, Nueva Ecija</option>
                        </select>
                        <button onclick="setMockGps()" class="bg-slate-800 text-white hover:bg-slate-700 px-3 py-1.5 rounded-lg text-xs font-bold transition">Apply</button>
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
                    <div class="bg-emerald-50 border border-emerald-200 px-4 py-2 rounded-xl text-right shadow-inner">
                        <p class="text-[10px] text-emerald-700 uppercase font-black tracking-wider opacity-90">Cash to Collect</p>
                        <p class="font-black text-xl leading-none text-emerald-800 mt-1" id="live-cash-total">₱ 0.00</p>
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
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 text-amber-400">
                    <i class="ph ph-receipt text-xl drop-shadow-[0_0_5px_rgba(245,158,11,0.5)]"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2">
                        <p class="font-black text-white text-base">₱ ${parseFloat(p.fare_amount).toFixed(2)}</p>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">${p.passenger_type}</p>
                    </div>
                    <p class="text-xs text-slate-400 truncate">${p.origin_name} &rarr; ${p.dest_name}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-[10px] font-bold text-emerald-400 uppercase bg-emerald-500/10 border border-emerald-500/20 px-2 py-1 rounded">Collect Cash</p>
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

// ========================================
// 🛰️ Live GPS Tracking — pushes driver's phone location to server
// ========================================
let mockLat = null;
let mockLng = null;

function setMockGps() {
    const val = document.getElementById('mock-gps-select').value;
    if (val) {
        const parts = val.split(',');
        mockLat = parseFloat(parts[0]);
        mockLng = parseFloat(parts[1]);
        if (window.showToast) window.showToast('GPS Override', 'Using mock coordinates for testing.', 'info');
    } else {
        mockLat = null;
        mockLng = null;
        if (window.showToast) window.showToast('GPS Override', 'Reverted to hardware/ISP GPS.', 'info');
    }
}

(function() {
    let lastPush = 0;
    const PUSH_INTERVAL = 5000; // Push every 5 seconds

    if (!navigator.geolocation) {
        console.warn('GPS not available on this device');
        return;
    }

    const gpsIndicator = document.createElement('div');
    gpsIndicator.id = 'gps-status';
    gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-white border border-slate-200 rounded-full px-4 py-2 shadow-lg text-xs font-bold text-slate-500 transition-colors';
    gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> GPS Connecting...';
    document.body.appendChild(gpsIndicator);

    navigator.geolocation.watchPosition(
        function(pos) {
            const now = Date.now();
            if (now - lastPush < PUSH_INTERVAL) return;
            lastPush = now;

            const lat = mockLat !== null ? mockLat : pos.coords.latitude;
            const lng = mockLng !== null ? mockLng : pos.coords.longitude;
            const speed = (pos.coords.speed || 0) * 3.6;

            fetch('push_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lat, lng, speed })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (mockLat !== null) {
                        gpsIndicator.innerHTML = `<span class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_6px_rgba(245,158,11,0.6)] animate-pulse"></span> Mock GPS Active`;
                        gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-full px-4 py-2 shadow-lg text-xs font-bold text-amber-700 transition-colors';
                    } else {
                        gpsIndicator.innerHTML = `<span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_6px_rgba(34,197,94,0.6)]"></span> GPS Active · ${speed.toFixed(0)} km/h`;
                        gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-green-50 border border-green-200 rounded-full px-4 py-2 shadow-lg text-xs font-bold text-green-700 transition-colors';
                    }
                }
            })
            .catch(() => { gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400"></span> GPS Error'; });
        },
        function(err) {
            console.warn('GPS Error:', err.message);
            gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400"></span> GPS Unavailable';
            gpsIndicator.className = gpsIndicator.className.replace('text-slate-500', 'text-red-500');
        },
        { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
    );
})();
<?php endif; ?>

async function startTrip(direction) {
    const dirTxt = direction === 'forward' ? 'Cabanatuan → Rizal' : 'Rizal → Cabanatuan';

    const confirmed = await window.showConfirm({
        title: 'Start New Trip?',
        message: `You are about to start a trip on route: ${dirTxt}. Passengers will be able to book tickets.`,
        type: 'info',
        confirmText: 'Yes, Start Trip'
    });

    if (!confirmed) return;

    fetch('start_trip.php', { 
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ direction: direction })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.showToast('Trip Started', `Route: ${dirTxt}`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            window.showToast('Error', d.message || 'Failed to start trip', 'error');
        }
    })
    .catch(() => {
        window.showToast('Network Error', 'Failed to connect to server', 'error');
    });
}

async function endTrip(tripId) {
    const confirmed = await window.showConfirm({
        title: 'End This Trip?',
        message: 'This will close all bookings for this run. This action cannot be undone.',
        type: 'danger',
        confirmText: 'Yes, End Trip'
    });

    if (!confirmed) return;

    fetch('end_trip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trip_id: tripId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.showToast('Trip Ended', 'All bookings for this run have been closed.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            window.showToast('Error', d.message || 'Failed to end trip', 'error');
        }
    })
    .catch(() => {
        window.showToast('Network Error', 'Failed to connect to server', 'error');
    });
}

</script>

<?php include '../includes/mobile_nav_driver.php'; ?>
</body>
</html>
