<?php
/**
 * passenger/booking.php   — STEP 6 & 7
 * Ride booking: select route, live fare preview, submit to issue ticket.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Book a Ride';
$currentPage  = 'booking.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Load all active stations ordered by route
$stations = $pdo->query("SELECT id, station_name, km_marker, is_terminal FROM stations WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();

// Check if any bus has an active trip
$tripStmt  = $pdo->query("SELECT t.id, t.bus_id, b.body_number, d.full_name AS driver_name FROM trips t JOIN buses b ON b.id=t.bus_id JOIN drivers d ON d.id=t.driver_id WHERE t.status='active' LIMIT 1");
$activeTrip = $tripStmt->fetch();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <div class="max-w-2xl mx-auto">

            <!-- Header -->
            <div class="mb-8">
                <a href="dashboard.php" class="flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-3 w-fit">
                    <i class="ph ph-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="text-2xl font-black text-slate-800">Book a Ride</h2>
                <p class="text-slate-500 text-sm mt-1">Select your origin, destination, and passenger type.</p>
            </div>

            <?php if (!$activeTrip): ?>
            <!-- No active trip warning -->
            <div class="bg-amber-50 border border-amber-200 rounded-3xl p-6 text-center">
                <i class="ph ph-warning-circle text-5xl text-amber-400 mb-3"></i>
                <h3 class="font-black text-amber-800 text-lg mb-1">No Active Bus Trip</h3>
                <p class="text-amber-700 text-sm">The driver has not started a trip yet. Please wait or check again later.</p>
                <button onclick="location.reload()" class="mt-4 bg-amber-500 text-white font-bold px-6 py-3 rounded-2xl hover:bg-amber-400 transition">
                    Refresh Status
                </button>
            </div>
            <?php else: ?>

            <!-- Active bus banner -->
            <div class="bg-blue-600 text-white rounded-2xl px-5 py-4 flex items-center gap-4 mb-6 shadow-lg shadow-blue-500/20">
                <i class="ph ph-bus-fill text-2xl text-blue-200"></i>
                <div>
                    <p class="font-bold text-sm">Bus <?= htmlspecialchars($activeTrip['body_number']) ?> is on route</p>
                    <p class="text-blue-200 text-xs">Driver: <?= htmlspecialchars($activeTrip['driver_name']) ?></p>
                </div>
                <span class="ml-auto flex items-center gap-1.5 bg-green-400/20 text-green-200 text-xs font-bold px-3 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span> Active
                </span>
            </div>

            <!-- Booking Form -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8">

                <!-- Passenger Type -->
                <div class="mb-6">
                    <label class="block text-slate-600 text-sm font-bold mb-3">
                        <i class="ph ph-user-circle mr-1"></i> Passenger Type
                    </label>
                    <div class="grid grid-cols-3 gap-3" id="type-group">
                        <?php foreach ([
                            ['regular', 'Regular',            'ph-user',    'text-blue-600',  'border-blue-500',  'bg-blue-50'],
                            ['student', 'Student/PWD',        'ph-student', 'text-yellow-600','border-yellow-500','bg-yellow-50'],
                            ['special', 'Teacher/Nurse',      'ph-heart',   'text-pink-600',  'border-pink-500',  'bg-pink-50'],
                        ] as [$key, $label, $icon, $iconColor, $border, $bg]): ?>
                        <button type="button"
                                onclick="selectType('<?= $key ?>', '<?= $label ?>')"
                                id="btn-<?= $key ?>"
                                class="type-btn flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-slate-200 hover:border-blue-300 transition font-semibold text-sm text-slate-700 text-center leading-tight h-full">
                            <i class="ph <?= $icon ?> text-3xl <?= $iconColor ?>"></i>
                            <span><?= $label ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" id="passenger_type" value="regular">
                </div>

                <!-- Route Selection -->
                <div class="grid grid-cols-1 gap-4 mb-6">
                    <!-- Origin -->
                    <div>
                        <label class="block text-slate-600 text-sm font-bold mb-2">
                            <i class="ph ph-map-pin mr-1 text-green-600"></i> Boarding Point (Origin)
                        </label>
                        <select id="origin_id" onchange="updateDestinations(); updateFare()"
                                class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-slate-700 font-medium focus:outline-none focus:border-blue-400">
                            <?php foreach ($stations as $st): ?>
                            <option value="<?= $st['id'] ?>" data-km="<?= $st['km_marker'] ?>">
                                <?= htmlspecialchars($st['station_name']) ?>  (KM <?= $st['km_marker'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Direction arrow -->
                    <div class="flex items-center gap-3 text-slate-400">
                        <div class="flex-1 h-px bg-slate-200"></div>
                        <i class="ph ph-arrow-down text-blue-500 text-xl"></i>
                        <div class="flex-1 h-px bg-slate-200"></div>
                    </div>

                    <!-- Destination -->
                    <div>
                        <label class="block text-slate-600 text-sm font-bold mb-2">
                            <i class="ph ph-map-pin-area mr-1 text-red-500"></i> Drop-off Point (Destination)
                        </label>
                        <select id="dest_id" onchange="updateFare()"
                                class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-slate-700 font-medium focus:outline-none focus:border-blue-400">
                        </select>
                    </div>
                </div>

                <!-- Fare Preview Card -->
                <div id="fare-card" class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl p-6 text-white mb-6 shadow-lg shadow-blue-500/20 hidden">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-blue-200 text-sm font-medium">Route</p>
                            <p class="font-bold text-sm" id="fare-route">—</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-200 text-sm font-medium">Distance</p>
                            <p class="font-bold text-sm" id="fare-distance">—</p>
                        </div>
                    </div>
                    <div class="border-t border-white/20 pt-4 flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-xs">Type</p>
                            <p class="font-bold text-sm" id="fare-type">Regular</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-200 text-xs">Total Fare</p>
                            <p class="text-3xl font-black" id="fare-amount">—</p>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div id="fare-loading" class="hidden text-center py-4 text-slate-400 text-sm">
                    <i class="ph ph-circle-notch animate-spin text-xl mr-2"></i> Calculating fare...
                </div>

                <!-- Submit -->
                <button id="book-btn" onclick="submitBooking()"
                        disabled
                        class="w-full bg-blue-600 hover:bg-blue-500 disabled:bg-slate-300 disabled:cursor-not-allowed text-white font-black text-lg py-4 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all active:scale-95">
                    Issue Ticket
                </button>

            </div><!-- /card -->
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/mobile_nav_passenger.php'; ?>
<script>
const stations = <?= json_encode($stations) ?>;
let currentFare = 0;
let selectedType = 'regular';
let selectedLabel = 'Regular';

// Init type buttons
selectType('regular', 'Regular');

function selectType(type, label) {
    selectedType = type;
    selectedLabel = label;
    document.getElementById('passenger_type').value = type;
    document.querySelectorAll('.type-btn').forEach(btn => {
        btn.classList.remove('border-blue-500','bg-blue-50','border-yellow-500','bg-yellow-50','border-pink-500','bg-pink-50');
        btn.classList.add('border-slate-200');
    });
    const map = { 'regular':'blue', 'student':'yellow', 'special':'pink' };
    const color = map[type] || 'blue';
    const id = 'btn-' + type;
    const btn = document.getElementById(id);
    if (btn) {
        btn.classList.remove('border-slate-200');
        btn.classList.add(`border-${color}-500`, `bg-${color}-50`);
    }
    document.getElementById('fare-type').textContent = label;
    updateFare();
}

function updateDestinations() {
    const originSel = document.getElementById('origin_id');
    const originKm  = parseFloat(originSel.selectedOptions[0]?.dataset.km ?? 0);
    const destSel   = document.getElementById('dest_id');
    destSel.innerHTML = '';
    stations
        .filter(s => parseFloat(s.km_marker) > originKm)
        .forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.dataset.km = s.km_marker;
            opt.textContent = `${s.station_name}  (KM ${s.km_marker})`;
            destSel.appendChild(opt);
        });
    updateFare();
}

function updateFare() {
    const originId = document.getElementById('origin_id').value;
    const destId   = document.getElementById('dest_id').value;
    const type     = selectedType;

    if (!originId || !destId) return;

    document.getElementById('fare-card').classList.add('hidden');
    document.getElementById('fare-loading').classList.remove('hidden');
    document.getElementById('book-btn').disabled = true;

    fetch(`get_fare.php?origin_id=${originId}&dest_id=${destId}&type=${encodeURIComponent(type)}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('fare-loading').classList.add('hidden');
            if (data.success) {
                const originName = document.getElementById('origin_id').selectedOptions[0].textContent.split('(')[0].trim();
                const destName   = document.getElementById('dest_id').selectedOptions[0].textContent.split('(')[0].trim();
                document.getElementById('fare-route').textContent    = `${originName} → ${destName}`;
                document.getElementById('fare-distance').textContent = `${data.distance_km} km`;
                document.getElementById('fare-amount').textContent   = `₱ ${parseFloat(data.fare).toFixed(2)}`;
                document.getElementById('fare-type').textContent     = selectedLabel;
                document.getElementById('fare-card').classList.remove('hidden');
                document.getElementById('book-btn').disabled = false;
                currentFare = data.fare;
            }
        })
        .catch(() => {
            document.getElementById('fare-loading').classList.add('hidden');
        });
}

function submitBooking() {
    const btn = document.getElementById('book-btn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const payload = {
        origin_id:      document.getElementById('origin_id').value,
        dest_id:        document.getElementById('dest_id').value,
        passenger_type: selectedLabel,
        fare:           currentFare
    };

    fetch('submit_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `ticket.php?code=${data.ticket_code}`;
        } else {
            alert('Error: ' + data.message);
            btn.disabled = false;
            btn.textContent = 'Issue Ticket';
        }
    });
}

// Init destinations on load
updateDestinations();
</script>

</body>
</html>
