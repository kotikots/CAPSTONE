<?php
/**
 * admin/reports.php — Revenue breakdown, driver performance, CSV export.
 */
$requiredRole = 'admin';
$pageTitle    = 'Reports & Export';
$currentPage  = 'reports.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Revenue per driver in range
$driverRevStmt = $pdo->prepare(
    "SELECT d.full_name, b.body_number, b.plate_number,
            COUNT(t.id) AS tickets,
            COALESCE(SUM(t.fare_amount),0) AS revenue,
            COALESCE(SUM(CASE WHEN t.passenger_type='Discounted' THEN 1 ELSE 0 END),0) AS discounted,
            COALESCE(SUM(CASE WHEN t.passenger_type='Regular'    THEN 1 ELSE 0 END),0) AS regular,
            COALESCE(SUM(CASE WHEN t.passenger_type='Non-Regular' THEN 1 ELSE 0 END),0) AS nonregular
     FROM   drivers d
     JOIN   buses   b  ON b.driver_id = d.id
     LEFT JOIN trips   tr ON tr.bus_id = b.id
     LEFT JOIN tickets t  ON t.trip_id  = tr.id AND DATE(t.issued_at) BETWEEN ? AND ?
     GROUP  BY d.id ORDER BY revenue DESC"
);
$driverRevStmt->execute([$from, $to]);
$driverRevs = $driverRevStmt->fetchAll();

// Daily breakdown in range
$dailyStmt = $pdo->prepare(
    "SELECT DATE(t.issued_at) AS day,
            COUNT(t.id) AS tickets,
            SUM(t.fare_amount) AS revenue
     FROM   tickets t
     WHERE  DATE(t.issued_at) BETWEEN ? AND ?
     GROUP  BY DATE(t.issued_at) ORDER BY day ASC"
);
$dailyStmt->execute([$from, $to]);
$daily = $dailyStmt->fetchAll();

$totalRev = array_sum(array_column($daily, 'revenue'));
$totalTix = array_sum(array_column($daily, 'tickets'));

include '../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Reports & Export</h2>
                <p class="text-slate-500 text-sm">Revenue breakdown and driver performance</p>
            </div>
            <!-- Export button -->
            <a href="export_csv.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
               class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold px-5 py-3 rounded-2xl shadow hover:shadow-blue-600/30 transition active:scale-95">
                <i class="ph ph-download-simple text-xl"></i> Export CSV
            </a>
        </div>

        <!-- Date Range Filter -->
        <form method="GET" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex flex-wrap items-end gap-4 mb-8">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>"
                       max="<?= date('Y-m-d') ?>"
                       class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>"
                       max="<?= date('Y-m-d') ?>"
                       class="border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <button type="submit" class="bg-blue-600 text-white font-bold px-6 py-2.5 rounded-xl text-sm hover:bg-blue-500 transition">Apply Filter</button>
            <div class="ml-auto text-right">
                <p class="text-xs text-slate-400">Total Period Revenue</p>
                <p class="text-2xl font-black text-emerald-700"><?= peso((float)$totalRev) ?></p>
                <p class="text-xs text-slate-400"><?= number_format($totalTix) ?> tickets issued</p>
            </div>
        </form>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Daily Revenue Chart -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="ph ph-chart-line text-blue-600"></i> Daily Revenue
                </h3>
                <canvas id="dailyChart" height="120"></canvas>
            </div>

            <!-- Revenue by Driver -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
                <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                    <i class="ph ph-ranking text-emerald-600"></i> Driver Performance
                </h3>
                <div class="space-y-4">
                    <?php foreach ($driverRevs as $i => $dr): ?>
                    <div class="flex items-center gap-4">
                        <span class="text-lg font-black text-slate-300 w-6 shrink-0"><?= $i+1 ?></span>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <div>
                                    <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($dr['full_name']) ?></p>
                                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($dr['body_number']) ?> · <?= $dr['tickets'] ?> tickets</p>
                                </div>
                                <p class="font-black text-emerald-700"><?= peso((float)$dr['revenue']) ?></p>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2">
                                <?php $pct = $totalRev > 0 ? min(100, ($dr['revenue'] / $totalRev) * 100) : 0; ?>
                                <div class="bg-emerald-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Daily Breakdown Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
            <div class="px-6 py-5 border-b border-slate-100">
                <h3 class="font-bold text-slate-700 flex items-center gap-2">
                    <i class="ph ph-table text-slate-500"></i> Daily Breakdown
                    <span class="text-slate-400 font-normal text-sm">(<?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?>)</span>
                </h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-center text-xs font-bold text-slate-400 uppercase tracking-wider">Tickets</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($daily)): ?>
                    <tr><td colspan="3" class="px-6 py-8 text-center text-slate-400">No transactions in this period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($daily as $d): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-3 font-medium text-slate-700"><?= date('l, F j, Y', strtotime($d['day'])) ?></td>
                        <td class="px-6 py-3 text-center text-slate-600"><?= number_format((int)$d['tickets']) ?></td>
                        <td class="px-6 py-3 text-right font-black text-emerald-700"><?= peso((float)$d['revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="bg-slate-50 font-black border-t-2 border-slate-200">
                        <td class="px-6 py-3 text-slate-800">TOTAL</td>
                        <td class="px-6 py-3 text-center text-slate-800"><?= number_format($totalTix) ?></td>
                        <td class="px-6 py-3 text-right text-emerald-700"><?= peso((float)$totalRev) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('M d', strtotime($r['day'])), $daily)) ?>,
        datasets: [{
            label: 'Revenue',
            data:  <?= json_encode(array_map(fn($r) => (float)$r['revenue'], $daily)) ?>,
            borderColor:     'rgb(16,185,129)',
            backgroundColor: 'rgba(16,185,129,0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: 'rgb(16,185,129)',
            pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => '₱' + v } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
