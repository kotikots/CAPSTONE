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

$activeTrip = null;
if ($bus) {
    // First, auto-end any orphaned trips on this bus from previous drivers
    $orphanCheck = getLiveTrip($pdo, (int)$bus['id'], 0);
    if ($orphanCheck && (int)$orphanCheck['driver_id'] !== $driverId) {
        $pdo->prepare("UPDATE trips SET status = 'completed', ended_at = NOW() WHERE id = ?")->execute([$orphanCheck['id']]);
    }
    // Now get the current driver's active trip
    $activeTrip = getLiveTrip($pdo, (int)$bus['id'], (int)$driverId);
}

// Get all stations for Simulator OSRM fetching
$stationsList = $pdo->query("SELECT latitude, longitude FROM stations WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// Today's stats
$statsStmt = $pdo->prepare(
    "SELECT 
        COALESCE(SUM(CASE WHEN DATE(t.issued_at) = CURDATE() THEN t.fare_amount ELSE 0 END), 0) AS total_revenue,
        COALESCE(SUM(CASE WHEN p.id IS NOT NULL AND p.remitted = 0 THEN t.fare_amount ELSE 0 END), 0) AS cash_in_hand,
        COALESCE(SUM(CASE WHEN p.id IS NOT NULL AND p.remitted = 2 THEN t.fare_amount ELSE 0 END), 0) AS pending_remittance,
        SUM(CASE WHEN DATE(t.issued_at) = CURDATE() AND (p.id IS NULL OR p.remitted = 0) THEN 1 ELSE 0 END) AS passengers
     FROM   tickets t
     JOIN   trips   tr ON tr.id = t.trip_id
     LEFT JOIN payments p ON p.ticket_id = t.id
     WHERE  tr.driver_id = ?"
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

<div class="flex min-h-screen" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%);">
    <?php include '../includes/sidebar_driver.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto pb-24 md:pb-8">

        <!-- Top bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-[#0F172A]">Good <?= (date('H')<12 ? 'morning' : (date('H')<17 ? 'afternoon' : 'evening')) ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?>!</h2>
                <p class="text-[#4C5C79] text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <?php if ($activeTrip): ?>
            <span class="flex items-center gap-2 bg-white/70 backdrop-blur-sm text-[#0F172A] border border-white font-bold px-5 py-2.5 rounded-full shadow-sm">
                <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(249,115,22,0.8)]"></span> Trip Active
            </span>
            <?php else: ?>
            <span class="flex items-center gap-2 bg-white/70 backdrop-blur-sm text-[#64748B] border border-white font-bold px-5 py-2.5 rounded-full shadow-sm">
                <span class="w-2 h-2 bg-slate-400 rounded-full"></span> No Active Trip
            </span>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="h-full bg-white rounded-3xl p-6 shadow-sm border border-[#E2E8F0] flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-[#FFE8D9] flex items-center justify-center shrink-0">
                    <i class="ph ph-users text-2xl text-[#FE6B17]"></i>
                </div>
                <div class="text-left">
                    <p class="text-[#4C5C79] text-sm font-semibold tracking-tight">Today's Passengers</p>
                    <p class="text-3xl font-black text-[#0F172A] leading-tight"><?= (int)$todayStats['passengers'] ?></p>
                </div>
            </div>
            <div class="h-full bg-white rounded-3xl p-6 shadow-sm border border-[#E2E8F0] relative">
                <div class="flex items-start gap-4 mb-3">
                    <div class="w-14 h-14 rounded-2xl bg-[#DBF7E4] flex items-center justify-center shrink-0">
                        <i class="ph ph-wallet text-2xl text-[#0D8E30]"></i>
                    </div>
                    <div class="flex-1 text-left">
                        <p class="text-[#4C5C79] text-sm font-semibold tracking-tight">Cash in Hand</p>
                        <p id="top-cash-in-hand" class="text-3xl font-black text-[#0D8E30] leading-tight whitespace-nowrap"><?= peso((float)$todayStats['cash_in_hand']) ?></p>
                        <p class="text-[#4C5C79] text-[10px] uppercase font-black tracking-widest mt-1 opacity-70">Day's Total: <?= peso((float)$todayStats['total_revenue']) ?></p>
                    </div>
                </div>
                <?php if ((float)$todayStats['cash_in_hand'] > 0): ?>
                <div class="mt-1">
                    <?php if (!$activeTrip): ?>
                    <button onclick="remitCash('<?= peso((float)$todayStats['cash_in_hand']) ?>')" id="btn-remit-cash"
                            class="w-full flex justify-center items-center gap-2 bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-400 hover:to-amber-400 text-white font-black px-4 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-orange-500/20 text-xs uppercase tracking-wider">
                        <i class="ph ph-hand-coins text-base"></i> Remit Cash
                    </button>
                    <?php else: ?>
                    <button disabled
                            class="w-full flex justify-center items-center gap-2 bg-[#E8EEF8] text-[#4C5C79] font-black px-4 py-3 rounded-xl text-xs uppercase tracking-wider cursor-not-allowed border border-slate-200">
                        <i class="ph ph-lock-key text-base"></i> End Trip to Remit
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ((float)$todayStats['pending_remittance'] > 0): ?>
                <div class="mt-3 bg-[#E4E1FD] border border-[#4B2EFA] rounded-xl px-3 py-2.5 flex items-center gap-2">
                    <span class="w-2 h-2 bg-[#E4E1FD]0 rounded-full animate-pulse shrink-0"></span>
                    <span class="text-[11px] font-bold text-[#4B2EFA]">Awaiting Admin Confirmation: <?= peso((float)$todayStats['pending_remittance']) ?></span>
                </div>
                <?php elseif ((float)$todayStats['cash_in_hand'] == 0 && (float)$todayStats['total_revenue'] > 0): ?>
                <div class="mt-3 bg-[#DBF7E4] border border-[#0D8E30] rounded-xl px-3 py-2.5 flex items-center gap-2">
                    <i class="ph ph-check-circle text-[#0D8E30]"></i>
                    <span class="text-[11px] font-bold text-[#0D8E30]">All cash remitted & confirmed</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="h-full bg-white rounded-3xl p-6 shadow-sm border border-[#E2E8F0] flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-[#FFF7ED] flex items-center justify-center shrink-0 border border-[#EA580C]/20">
                    <i class="ph ph-bus text-2xl text-[#EA580C]"></i>
                </div>
                <div class="text-left">
                    <p class="text-[#4C5C79] text-sm font-semibold tracking-tight">My Bus</p>
                    <p class="text-2xl font-black text-[#0F172A] leading-tight"><?= htmlspecialchars($bus['body_number'] ?? '—') ?></p>
                    <p class="text-[#4C5C79] text-xs font-semibold mt-0.5"><?= htmlspecialchars($bus['plate_number'] ?? '') ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Trip Control -->
            <div class="bg-white rounded-3xl shadow-sm border border-[#E2E8F0] p-6">
                <h3 class="font-bold text-[#0F172A] mb-5 flex items-center gap-2">
                    <i class="ph ph-steering-wheel text-[#FE6B17]"></i> Trip Control
                </h3>

                <?php if (!$bus): ?>
                <div class="text-center py-8 text-[#4C5C79]">
                    <i class="ph ph-warning-circle text-4xl mb-2"></i>
                    <p>No bus assigned to your account. Contact admin.</p>
                </div>
                <?php elseif ($activeTrip): ?>
                <!-- Active trip info -->
                <div class="bg-[#FFF7ED] border border-[#EA580C]/30 rounded-2xl p-4 mb-5 shadow-sm text-white">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 bg-[#EA580C] rounded-full animate-pulse shadow-[0_0_8px_rgba(234,88,12,0.8)]"></span>
                        <span class="font-black text-sm">Trip in progress</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><p class="opacity-80 text-[10px] uppercase font-bold tracking-wider">From</p><p class="font-black"><?= htmlspecialchars($activeTrip['start_name']) ?></p></div>
                        <div><p class="opacity-80 text-[10px] uppercase font-bold tracking-wider">To</p><p class="font-black"><?= htmlspecialchars($activeTrip['end_name']) ?></p></div>
                        <div><p class="opacity-80 text-[10px] uppercase font-bold tracking-wider">Started</p><p class="font-black"><?= date('h:i A', strtotime($activeTrip['started_at'])) ?></p></div>
                        <div><p class="opacity-80 text-[10px] uppercase font-bold tracking-wider">Passengers</p><p class="font-black" id="live-pax-count"><?= $activeTrip['passenger_count'] ?></p></div>
                    </div>
                </div>

                <button onclick="endTrip(<?= $activeTrip['id'] ?>)"
                        class="w-full bg-red-500 hover:bg-red-400 text-white font-black py-4 rounded-2xl transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="ph ph-stop-circle text-xl"></i> End Trip
                </button>

                <?php else: ?>
                <!-- Start trip -->
                <div class="bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] rounded-2xl p-4 mb-5 text-sm text-[#4C5C79] text-center">
                    <i class="ph ph-bus text-4xl text-slate-300 mb-2"></i>
                    <p>You have no active trip.</p>
                    <p>Select your direction to allow passengers to book.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="startTrip('forward')" class="bg-amber-600 hover:bg-[#E4E1FD]0 text-white font-bold py-3 px-2 rounded-2xl transition active:scale-95 flex flex-col items-center justify-center gap-1 shadow-sm text-center">
                        <i class="ph ph-arrow-circle-right text-2xl"></i>
                        <span class="text-xs">Cabanatuan &rarr; Rizal</span>
                    </button>
                    <button onclick="startTrip('backward')" class="bg-orange-600 hover:bg-[#FE6B17] text-white font-bold py-3 px-2 rounded-2xl transition active:scale-95 flex flex-col items-center justify-center gap-1 shadow-sm text-center">
                        <i class="ph ph-arrow-circle-left text-2xl"></i>
                        <span class="text-xs">Rizal &rarr; Cabanatuan</span>
                    </button>
                </div>
                <?php endif; ?>

                <!-- Location Simulator -->
                <?php if ($activeTrip): ?>
                <div class="mt-4 border-t border-[#E2E8F0] pt-4">
                    <h4 class="text-sm font-bold text-[#4C5C79] mb-2 flex items-center gap-2">
                        <i class="ph ph-navigation-arrow text-[#4B2EFA]"></i> Location Tracking
                    </h4>

                    <!-- Kiosk handles GPS automatically -->
                    <div class="w-full rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2 bg-[#DBF7E4] border border-[#0D8E30] text-[#0D8E30] mb-3">
                        <span class="w-2 h-2 rounded-full bg-[#DBF7E4]0 shrink-0 animate-pulse"></span>
                        <span>🖥️ Bus Kiosk GPS is active · Live Tracking enabled</span>
                    </div>


                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Passengers -->
            <div class="bg-white rounded-3xl shadow-sm border border-[#E2E8F0] p-6">
                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between mb-5 gap-4">
                    <h3 class="font-bold text-[#0F172A] flex items-center gap-2">
                        <i class="ph ph-users text-[#FE6B17]"></i> Passengers This Trip
                    </h3>
                    <?php if ($activeTrip): ?>
                    <div class="flex flex-wrap gap-2 sm:gap-4 w-full xl:w-auto">
                        <div class="bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9] border border-[#E2E8F0] px-4 sm:px-5 py-2 sm:py-2.5 rounded-2xl text-left flex-1">
                            <p class="text-[9px] sm:text-[10px] uppercase font-black tracking-widest text-[#4C5C79] opacity-80 mb-1 whitespace-nowrap">Total Pending</p>
                            <p class="font-black text-xl sm:text-2xl text-[#4C5C79] leading-none whitespace-nowrap" id="live-cash-pending">₱ 0.00</p>
                        </div>
                        <div class="bg-[#DBF7E4] text-[#0D8E30] px-4 sm:px-5 py-2 sm:py-2.5 rounded-2xl text-left flex-1 border border-[#0D8E30]/20">
                            <p class="text-[9px] sm:text-[10px] uppercase font-black tracking-widest opacity-80 mb-1 whitespace-nowrap">Cash in Hand</p>
                            <p class="font-black text-xl sm:text-2xl leading-none whitespace-nowrap" id="live-cash-collected">₱ 0.00</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="live-pax-list" class="space-y-2 overflow-auto max-h-[400px]">
                    <?php if (!$activeTrip): ?>
                    <div class="text-center py-10 text-[#4C5C79]">
                        <i class="ph ph-bus text-5xl mb-2"></i>
                        <p class="text-sm">Start a trip to see passengers</p>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-10 text-[#4C5C79]">
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
        document.getElementById('live-cash-collected').innerText = '₱ ' + parseFloat(data.collected_cash).toFixed(2);
        document.getElementById('live-cash-pending').innerText = '₱ ' + parseFloat(data.pending_cash).toFixed(2);
        
        if (data.all_time_cash_in_hand !== undefined) {
            const topCashDiv = document.getElementById('top-cash-in-hand');
            if (topCashDiv) topCashDiv.innerText = '₱ ' + parseFloat(data.all_time_cash_in_hand).toFixed(2);
        }
        
        const list = document.getElementById('live-pax-list');
        if (data.recent_passengers.length === 0) {
            list.innerHTML = `
                <div class="text-center py-10 text-[#4C5C79]">
                    <i class="ph ph-ticket text-5xl mb-2"></i>
                    <p class="text-sm">No tickets printed yet</p>
                </div>`;
            return;
        }

        // Render live ticket list
        list.innerHTML = data.recent_passengers.map(p => {
            let dateStr = p.issued_at.replace(/-/g, '/');
            const isNear = p.proximity === 'near';
            const iconBg = isNear ? 'bg-[#DBF7E4] border-[#0D8E30]' : 'bg-[#E4E1FD] border-[#4B2EFA]';
            const iconColor = isNear ? 'text-[#0D8E30]' : 'text-[#4B2EFA]';
            const icon = isNear ? 'ph-check-circle' : 'ph-clock';

            let paymentAction = '';
            if (p.is_paid) {
                paymentAction = `<span class="text-[9px] sm:text-[10px] font-bold text-[#0D8E30] uppercase bg-[#DBF7E4] px-2 sm:px-3 py-1 sm:py-1.5 rounded-full flex items-center gap-1 border border-emerald-100"><i class="ph ph-check-fat-fill"></i> Paid</span>`;
            } else {
                paymentAction = `<button onclick="collectPayment(${p.id}, this)" class="text-[9px] sm:text-[11px] font-black text-white uppercase bg-[#0D8E30] hover:bg-[#0D8E30] px-2 sm:px-4 py-1.5 sm:py-2.5 rounded-lg sm:rounded-xl shadow-md transition active:scale-95 flex items-center gap-1 sm:gap-2" style="color: #ffffff !important;">
                    <i class="ph ph-hand-coins text-sm sm:text-base"></i> <span class="hidden sm:inline">Receive Cash</span><span class="sm:hidden">Collect</span>
                </button>`;
            }

            return `
            <div class="flex items-center gap-3 p-3 rounded-xl bg-white border ${isNear ? 'border-[#0D8E30] shadow-md ring-1 ring-emerald-50' : 'border-slate-200 shadow-sm'} animate-[fadeIn_0.5s_ease-out]">
                <div class="w-10 h-10 rounded-xl ${iconBg} border flex items-center justify-center shrink-0 ${iconColor} relative">
                    <i class="ph ph-receipt text-xl"></i>
                    <div class="absolute -top-1 -right-1 w-3 h-3 rounded-full border-2 border-white ${isNear ? 'bg-[#0D8E30]' : 'bg-[#E4E1FD]0'}"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline gap-2">
                        <p class="font-black text-[#0F172A] text-base">₱ ${parseFloat(p.fare_amount).toFixed(2)}</p>
                        <span class="text-[9px] font-bold text-[#4C5C79] uppercase tracking-widest">${p.passenger_type}</span>
                    </div>
                    <p class="text-[11px] text-[#4C5C79] truncate">${p.origin_name} &rarr; <span class="font-bold text-[#0F172A]">${p.dest_name}</span></p>
                    <p class="text-[9px] ${isNear ? 'text-[#0D8E30] font-bold' : 'text-[#4C5C79]'} mt-0.5">
                        <i class="ph ${isNear ? 'ph-map-pin-line' : 'ph-navigation-arrow'}"></i> ${isNear ? 'Arriving Soon' : p.distance_km.toFixed(1) + ' km away'}
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <div class="mb-1">${paymentAction}</div>
                    <p class="text-[10px] font-medium text-[#4C5C79]">${new Date(dateStr).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
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

function collectPayment(ticketId, btn) {
    const originalText = btn.innerText;
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner-gap animate-spin"></i>';
    
    fetch('api_collect_payment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ticket_id: ticketId })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.showToast('Payment Collected', 'Cash recorded successfully', 'success');
            fetchTripStats(); // Refresh list immediately
        } else {
            window.showToast('Error', d.message, 'error');
            btn.disabled = false;
            btn.innerText = originalText;
        }
    })
    .catch(() => {
        window.showToast('Network Error', 'Failed to connect to server', 'error');
        btn.disabled = false;
        btn.innerText = originalText;
    });
}

// Poll Kiosk via database every 3 seconds
setInterval(fetchTripStats, 3000);
fetchTripStats();
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

// ========================================
// 💰 Remit Cash — driver flags collected cash as remitted
// ========================================
async function remitCash(amount) {
    const confirmed = await window.showConfirm({
        title: 'Remit Cash to Admin',
        message: `You are about to remit ${amount} to the admin. Are you sure you have handed over this amount? This action cannot be undone.`,
        type: 'warning',
        confirmText: 'Yes, I Remitted'
    });

    if (!confirmed) return;

    const btn = document.getElementById('btn-remit-cash');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="ph ph-spinner-gap animate-spin text-lg"></i> Processing...';
    }

    try {
        const res = await fetch('api_remit_cash.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();

        if (data.success) {
            window.showToast('Remittance Submitted', 
                `₱ ${data.amount.toFixed(2)} flagged as remitted. Pending admin confirmation.`, 
                'success');
            // Refresh the page after a short delay so the user sees the toast
            setTimeout(() => location.reload(), 1800);
        } else {
            window.showToast('Remittance Failed', data.message || 'Unknown error', 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-hand-coins text-lg"></i> Remit Cash to Admin';
            }
        }
    } catch (err) {
        window.showToast('Network Error', 'Failed to connect to server', 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-hand-coins text-lg"></i> Remit Cash to Admin';
        }
    }
}

// ========================================
// 🛰️ Live GPS Tracking — pushes driver's phone location to server
// This makes the bus icon move on the passenger's live map
// ========================================
<?php if ($activeTrip): ?>
let mockLat = null;
let mockLng = null;

function setMockGps() {
    const val = document.getElementById('mock-gps-select').value;
    if (val) {
        const parts = val.split(',');
        mockLat = parseFloat(parts[0]);
        mockLng = parseFloat(parts[1]);
        window.showToast('GPS Override', 'Using mock coordinates for testing.', 'info');
    } else {
        mockLat = null;
        mockLng = null;
        window.showToast('GPS Override', 'Reverted to hardware/ISP GPS.', 'info');
    }
}

(function() {
    let lastPush = 0;
    const PUSH_INTERVAL = 5000; // Push every 5 seconds

    if (!navigator.geolocation) {
        console.warn('GPS not available on this device');
        return;
    }

    // Show GPS status indicator
    const gpsIndicator = document.createElement('div');
    gpsIndicator.id = 'gps-status';
    gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-white border border-slate-200 rounded-full px-4 py-2 shadow-lg text-xs font-bold text-[#4C5C79] transition-colors';
    gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span> GPS Connecting...';
    document.body.appendChild(gpsIndicator);

    navigator.geolocation.watchPosition(
        function(pos) {
            const now = Date.now();
            if (now - lastPush < PUSH_INTERVAL) return;
            lastPush = now;

            // Use Mock coordinates if active, otherwise use actual device coords
            const lat = mockLat !== null ? mockLat : pos.coords.latitude;
            const lng = mockLng !== null ? mockLng : pos.coords.longitude;
            const speed = (pos.coords.speed || 0) * 3.6; // m/s → km/h

            fetch('push_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lat, lng, speed })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (mockLat !== null) {
                        gpsIndicator.innerHTML = `<span class="w-2 h-2 rounded-full bg-[#E4E1FD]0 shadow-[0_0_6px_rgba(245,158,11,0.6)] animate-pulse"></span> Mock GPS Active`;
                        gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-[#E4E1FD] border border-[#4B2EFA] rounded-full px-4 py-2 shadow-lg text-xs font-bold text-[#4B2EFA] transition-colors';
                    } else {
                        gpsIndicator.innerHTML = `<span class="w-2 h-2 rounded-full bg-[#DBF7E4]0 shadow-[0_0_6px_rgba(34,197,94,0.6)]"></span> GPS Active`;
                        gpsIndicator.className = 'fixed bottom-20 md:bottom-4 right-4 z-50 flex items-center gap-2 bg-[#DBF7E4] border border-[#0D8E30] rounded-full px-4 py-2 shadow-lg text-xs font-bold text-[#0D8E30] transition-colors';
                    }
                }
            })
            .catch(() => {
                gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400"></span> GPS Error';
            });
        },
        function(err) {
            console.warn('GPS Error:', err.message);
            gpsIndicator.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400"></span> GPS Unavailable';
            gpsIndicator.className = gpsIndicator.className.replace('text-[#4C5C79]', 'text-red-500');
        },
        { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
    );
})();
<?php endif; ?>

</script>

<?php include '../includes/mobile_nav_driver.php'; ?>
</body>
</html>
