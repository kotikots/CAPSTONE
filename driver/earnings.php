<?php
/**
 * driver/earnings.php
 * Driver's earnings summary — today, this week, this month, all-time.
 */
$requiredRole = 'driver';
$pageTitle    = 'My Earnings';
$currentPage  = 'earnings.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$driverId = $_SESSION['driver_id'];

// Earnings periods
function driverRev(PDO $pdo, int $driverId, string $where): array {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(t.fare_amount),0) AS revenue, COUNT(t.id) AS tickets
         FROM   tickets t
         JOIN   trips   tr ON tr.id = t.trip_id
         WHERE  tr.driver_id = ? AND $where"
    );
    $stmt->execute([$driverId]);
    return $stmt->fetch();
}

$today  = driverRev($pdo, $driverId, "DATE(t.issued_at) = CURDATE()");
$week   = driverRev($pdo, $driverId, "YEARWEEK(t.issued_at) = YEARWEEK(NOW())");
$month  = driverRev($pdo, $driverId, "MONTH(t.issued_at)=MONTH(NOW()) AND YEAR(t.issued_at)=YEAR(NOW())");
$allTime= driverRev($pdo, $driverId, "1=1");

// Daily breakdown — last 14 days
$dailyStmt = $pdo->prepare(
    "SELECT DATE(t.issued_at) AS day,
            COUNT(t.id) AS tickets,
            SUM(t.fare_amount) AS revenue
     FROM   tickets t
     JOIN   trips   tr ON tr.id = t.trip_id
     WHERE  tr.driver_id = ? AND t.issued_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP  BY DATE(t.issued_at) ORDER BY day ASC"
);
$dailyStmt->execute([$driverId]);
$daily = $dailyStmt->fetchAll();

// Recent trips
$tripsStmt = $pdo->prepare(
    "SELECT tr.started_at, tr.ended_at, tr.passenger_count, tr.total_revenue, tr.status,
            s1.station_name AS start_name, s2.station_name AS end_name
     FROM   trips   tr
     JOIN   stations s1 ON s1.id = tr.start_station_id
     JOIN   stations s2 ON s2.id = tr.end_station_id
     WHERE  tr.driver_id = ? ORDER BY tr.started_at DESC LIMIT 10"
);
$tripsStmt->execute([$driverId]);
$trips = $tripsStmt->fetchAll();

include '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_driver.php'; ?>
    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="mb-8 mt-2 md:mt-0">
            <h2 class="text-2xl font-black text-slate-800">My Earnings</h2>
            <p class="text-slate-500 text-sm mt-1">Revenue collected during your trips</p>
        </div>

        <!-- Revenue Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ([
                ['Today',      $today,   'ph-sun',        'amber'],
                ['This Week',  $week,    'ph-calendar',   'blue'],
                ['This Month', $month,   'ph-chart-line', 'violet'],
                ['All Time',   $allTime, 'ph-trophy',     'emerald'],
            ] as [$label, $data, $icon, $color]): ?>
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center">
                        <i class="ph <?= $icon ?> text-xl text-<?= $color ?>-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-black text-slate-800"><?= peso((float)$data['revenue']) ?></p>
                <p class="text-slate-400 text-xs mt-1"><?= $label ?> · <?= (int)$data['tickets'] ?> tickets</p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Chart -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="ph ph-chart-bar text-orange-500"></i> Last 14 Days
                </h3>
                <canvas id="earningsChart" height="120"></canvas>
            </div>

            <!-- Recent trips -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="ph ph-clock-counter-clockwise text-blue-500"></i> Recent Trips
                </h3>
                <?php if (empty($trips)): ?>
                <div class="text-center py-8 text-slate-400"><p class="text-sm">No trips yet.</p></div>
                <?php else: ?>
                <div class="space-y-3 overflow-auto max-h-72">
                    <?php foreach ($trips as $tr): ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0
                            <?= $tr['status']==='active' ? 'bg-green-100' : 'bg-slate-100' ?>">
                            <i class="ph ph-bus text-sm <?= $tr['status']==='active' ? 'text-green-600' : 'text-slate-400' ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-xs text-slate-700 truncate"><?= htmlspecialchars($tr['start_name']) ?> → <?= htmlspecialchars($tr['end_name']) ?></p>
                            <p class="text-slate-400 text-xs"><?= date('M d, h:i A', strtotime($tr['started_at'])) ?> · <?= $tr['passenger_count'] ?> pax</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-black text-sm text-emerald-700"><?= peso((float)$tr['total_revenue']) ?></p>
                            <span class="text-xs <?= $tr['status']==='active' ? 'text-green-600 font-bold' : 'text-slate-400' ?>"><?= ucfirst($tr['status']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
new Chart(document.getElementById('earningsChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('M d', strtotime($r['day'])), $daily)) ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data:  <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $daily)) ?>,
            backgroundColor: 'rgba(249,115,22,0.15)',
            borderColor:     'rgb(249,115,22)',
            borderWidth: 2, borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱'+v } },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php include '../includes/mobile_nav_driver.php'; ?>
</body></html>
