<?php
/**
 * admin/dashboard.php   — STEP 10
 * Admin dashboard: revenue cards, Chart.js graphs, stats, recent trips.
 */
$requiredRole = 'admin';
$pageTitle    = 'Admin Dashboard';
$currentPage  = 'dashboard.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Revenue figures
function revQuery(PDO $pdo, string $where): float {
    return (float)$pdo->query("SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN tickets t ON t.id=p.ticket_id WHERE $where")->fetchColumn();
}

$revToday  = revQuery($pdo, "DATE(p.paid_at) = CURDATE()");
$revWeek   = revQuery($pdo, "YEARWEEK(p.paid_at) = YEARWEEK(NOW())");
$revMonth  = revQuery($pdo, "MONTH(p.paid_at)=MONTH(NOW()) AND YEAR(p.paid_at)=YEAR(NOW())");
$revAll    = revQuery($pdo, "1=1");

// Counts
$totalPassengers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='passenger'")->fetchColumn();
$totalDrivers    = (int)$pdo->query("SELECT COUNT(*) FROM drivers WHERE is_active=1")->fetchColumn();
$totalBuses      = (int)$pdo->query("SELECT COUNT(*) FROM buses WHERE is_active=1")->fetchColumn();
$activeTrips     = (int)$pdo->query("SELECT COUNT(*) FROM trips WHERE status='active'")->fetchColumn();
$totalTickets    = (int)$pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();

// Last 7 days revenue (for chart)
$chartStmt = $pdo->query(
    "SELECT DATE(paid_at) AS day, COALESCE(SUM(amount_paid),0) AS total
     FROM   payments
     WHERE  paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP  BY DATE(paid_at)
     ORDER  BY day ASC"
);
$chartRaw  = $chartStmt->fetchAll();
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartData[$d] = 0;
}
foreach ($chartRaw as $row) { $chartData[$row['day']] = (float)$row['total']; }
$chartLabels = array_map(fn($d) => date('D M j', strtotime($d)), array_keys($chartData));
$chartValues = array_values($chartData);

// Revenue per bus
$buseRev = $pdo->query(
    "SELECT b.body_number, b.plate_number, d.full_name AS driver,
            COUNT(t.id) AS tickets, COALESCE(SUM(t.fare_amount),0) AS revenue
     FROM   buses b
     JOIN   drivers d ON d.id = b.driver_id
     LEFT JOIN trips  tr ON tr.bus_id = b.id
     LEFT JOIN tickets t ON t.trip_id = tr.id
     GROUP  BY b.id ORDER BY revenue DESC"
)->fetchAll();

// Recent trips
$recentTrips = $pdo->query(
    "SELECT tr.*, b.body_number, b.plate_number, d.full_name AS driver_name,
            s1.station_name AS start_name, s2.station_name AS end_name
     FROM   trips tr
     JOIN   buses   b  ON b.id  = tr.bus_id
     JOIN   drivers d  ON d.id  = tr.driver_id
     JOIN   stations s1 ON s1.id = tr.start_station_id
     JOIN   stations s2 ON s2.id = tr.end_station_id
     ORDER  BY tr.started_at DESC LIMIT 8"
)->fetchAll();

// Active Fleet Locations for Map
$mapMarkers = $pdo->query(
    "SELECT b.body_number, b.plate_number, l.latitude, l.longitude, l.speed_kmh, l.recorded_at
     FROM buses b
     JOIN (
         SELECT bus_id, MAX(recorded_at) as max_rec
         FROM bus_locations GROUP BY bus_id
     ) latest ON latest.bus_id = b.id
     JOIN bus_locations l ON l.bus_id = b.id AND l.recorded_at = latest.max_rec
     WHERE b.is_active = 1"
)->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<!-- Leaflet CSS for Maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Dashboard Overview</h2>
                <p class="text-slate-500 text-sm"><?= date('l, F j, Y') ?></p>
            </div>
            <a href="reports.php"
               class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-3 rounded-2xl shadow hover:shadow-emerald-500/30 transition active:scale-95">
                <i class="ph ph-file-text text-xl"></i> Reports & Export
            </a>
        </div>

        <!-- Revenue Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ([
                ['Today',       $revToday, 'ph-sun',        'amber',   "vs yesterday"],
                ['This Week',   $revWeek,  'ph-calendar',   'blue',    "current week"],
                ['This Month',  $revMonth, 'ph-chart-line', 'violet',  "current month"],
                ['All Time',    $revAll,   'ph-piggy-bank', 'emerald', "since launched"],
            ] as [$label, $value, $icon, $color, $sub]): ?>
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center">
                        <i class="ph <?= $icon ?> text-xl text-<?= $color ?>-600"></i>
                    </div>
                    <span class="text-xs text-slate-400 font-medium"><?= $sub ?></span>
                </div>
                <p class="text-2xl font-black text-slate-800"><?= peso($value) ?></p>
                <p class="text-slate-400 text-xs mt-1"><?= $label ?> Revenue</p>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Quick Stat Pills -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            <?php foreach ([
                ['Passengers',   $totalPassengers, 'ph-users',         'blue'],
                ['Drivers',      $totalDrivers,    'ph-steering-wheel','orange'],
                ['Buses',        $totalBuses,      'ph-bus',           'teal'],
                ['Active Trips', $activeTrips,     'ph-map-pin',       'green'],
                ['Total Tickets',$totalTickets,    'ph-ticket',        'violet'],
            ] as [$label, $val, $icon, $color]): ?>
            <div class="bg-white rounded-2xl px-4 py-3 shadow-sm border border-slate-100 flex items-center gap-3">
                <i class="ph <?= $icon ?> text-xl text-<?= $color ?>-500"></i>
                <div>
                    <p class="text-xl font-black text-slate-800"><?= number_format($val) ?></p>
                    <p class="text-slate-400 text-xs"><?= $label ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Revenue Chart -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                    <i class="ph ph-chart-bar text-emerald-600"></i> Revenue – Last 7 Days
                </h3>
                <canvas id="revenueChart" height="100"></canvas>
            </div>

            <!-- Revenue per Bus -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                    <i class="ph ph-bus text-blue-600"></i> Earnings per Bus
                </h3>
                <div class="space-y-4">
                    <?php foreach ($buseRev as $b): ?>
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <div>
                                <p class="font-bold text-slate-700"><?= htmlspecialchars($b['body_number']) ?></p>
                                <p class="text-slate-400 text-xs"><?= htmlspecialchars($b['driver']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-black text-emerald-700"><?= peso((float)$b['revenue']) ?></p>
                                <p class="text-xs text-slate-400"><?= $b['tickets'] ?> tickets</p>
                            </div>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <?php $pct = $revAll > 0 ? min(100, ($b['revenue'] / $revAll) * 100) : 0; ?>
                            <div class="bg-emerald-500 h-2 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Fleet Live Tracking Map -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 mb-6">
            <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                <i class="ph ph-map-pin-line text-blue-600"></i> Live Fleet Tracking
            </h3>
            <div id="fleetMap" class="w-full h-[400px] rounded-2xl z-0"></div>
        </div>

        <!-- Recent Trips Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
                <h3 class="font-bold text-slate-700 flex items-center gap-2">
                    <i class="ph ph-map-pin-line text-blue-600"></i> Recent Trips
                </h3>
                <a href="trips.php" class="text-blue-500 text-sm font-semibold hover:underline">View all →</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <?php foreach (['Bus','Driver','Route','Started','Ended','Passengers','Revenue','Status'] as $h): ?>
                        <th class="px-5 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($recentTrips as $tr): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-5 py-4 font-semibold text-slate-800"><?= htmlspecialchars($tr['body_number']) ?></td>
                        <td class="px-5 py-4 text-slate-600"><?= htmlspecialchars($tr['driver_name']) ?></td>
                        <td class="px-5 py-4 text-slate-600 text-xs"><?= htmlspecialchars($tr['start_name']) ?> → <?= htmlspecialchars($tr['end_name']) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= date('M d h:i A', strtotime($tr['started_at'])) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= $tr['ended_at'] ? date('h:i A', strtotime($tr['ended_at'])) : '—' ?></td>
                        <td class="px-5 py-4 text-center font-semibold"><?= $tr['passenger_count'] ?></td>
                        <td class="px-5 py-4 font-black text-emerald-700"><?= peso((float)$tr['total_revenue']) ?></td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                <?= $tr['status']==='active' ? 'bg-green-100 text-green-700' : ($tr['status']==='completed' ? 'bg-slate-100 text-slate-600' : 'bg-red-100 text-red-600') ?>">
                                <?= ucfirst($tr['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>
</div>

<script>
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data:  <?= json_encode($chartValues) ?>,
            backgroundColor: 'rgba(16,185,129,0.15)',
            borderColor:     'rgb(16,185,129)',
            borderWidth:     2,
            borderRadius:    8,
            fill:            true,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' },
                 ticks: { callback: v => '₱' + v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});

// Leaflet Map Initialization
const markers = <?= json_encode($mapMarkers) ?>;
const map = L.map('fleetMap').setView([15.4859, 120.9665], 11); // Default to Cabanatuan

L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap &nbsp; | &copy; CartoDB'
}).addTo(map);

// Custom Bus Icon
const busIcon = L.divIcon({
    html: '<div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center shadow-lg border-2 border-white"><i class="ph ph-bus text-white text-lg"></i></div>',
    className: 'custom-leaflet-icon',
    iconSize: [32, 32],
    iconAnchor: [16, 16]
});

// Add markers to map
const bounds = [];
markers.forEach(m => {
    if (m.latitude && m.longitude) {
        const latLng = [parseFloat(m.latitude), parseFloat(m.longitude)];
        bounds.push(latLng);
        L.marker(latLng, { icon: busIcon })
         .addTo(map)
         .bindPopup(`
            <div class="p-2 min-w-[150px]">
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">Bus ${m.body_number}</p>
                <p class="font-bold text-slate-800 text-sm mb-2">${m.plate_number}</p>
                <div class="flex items-center gap-2 text-xs bg-slate-50 p-2 rounded">
                    <i class="ph ph-gauge text-blue-500"></i>
                    <span>${parseFloat(m.speed_kmh).toFixed(1)} km/h</span>
                </div>
            </div>
         `);
    }
});

// Fit map to visible buses or default bounds
if (bounds.length > 0) {
    if (bounds.length === 1) {
        map.setView(bounds[0], 14);
    } else {
        map.fitBounds(L.latLngBounds(bounds), { padding: [50, 50] });
    }
}

// Auto-refresh admin dashboard to reflect live Kiosk passenger ticks and map
setTimeout(() => {
    window.location.reload();
}, 15000);
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
