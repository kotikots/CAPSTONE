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
require_once '../includes/functions_v2.php';

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
                ['Today',      $today,   'ph-sun',        'amber',   'today'],
                ['This Week',  $week,    'ph-calendar',   'blue',    'week'],
                ['This Month', $month,   'ph-chart-line', 'violet',  'month'],
                ['All Time',   $allTime, 'ph-trophy',     'emerald', 'all'],
            ] as [$label, $data, $icon, $color, $periodId]): ?>
            <div onclick="showRecordsModal('<?= $periodId ?>', '<?= addslashes($label) ?>')" class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 cursor-pointer hover:shadow-md hover:border-<?= $color ?>-300 transition-all active:scale-95 group">
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-xl bg-<?= $color ?>-100 flex items-center justify-center group-hover:bg-<?= $color ?>-500 group-hover:text-white transition-colors duration-300 text-<?= $color ?>-600">
                        <i class="ph <?= $icon ?> text-xl"></i>
                    </div>
                    <i class="ph ph-caret-right text-slate-300 group-hover:text-<?= $color ?>-500 group-hover:translate-x-1 transition-all"></i>
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

<!-- Records Modal -->
<div id="records-modal" class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300" style="transition: opacity 0.3s ease, visibility 0.3s ease;">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[85vh] transform scale-95 transition-transform duration-300" id="records-modal-box">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
                <h3 id="records-modal-title" class="text-xl font-black text-slate-800">Earnings Records</h3>
                <p class="text-sm text-slate-500 font-medium">Viewing ticket details</p>
            </div>
            <button onclick="closeRecordsModal()" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>
        <div class="px-6 py-4 bg-white border-b border-slate-100">
            <div class="relative">
                <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <input type="text" id="records-search" oninput="filterModalRecords()" placeholder="Search passenger, ticket code, or destination..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:border-blue-300 focus:ring-2 focus:ring-blue-100 transition-all text-slate-700 placeholder-slate-400">
            </div>
        </div>
        <div class="p-6 overflow-y-auto no-scrollbar flex-1 bg-white relative">
            <div id="records-modal-loader" class="absolute inset-0 bg-white/80 z-10 flex flex-col items-center justify-center hidden">
                 <i class="ph ph-spinner-gap animate-spin text-4xl text-blue-500 mb-2"></i>
                 <p class="text-sm font-bold text-slate-500">Loading records...</p>
            </div>
            <div id="records-modal-content" class="space-y-3">
                <!-- Records injected here -->
            </div>
        </div>
    </div>
</div>

<script>
let currentModalRecords = [];

function closeRecordsModal() {
    const modal = document.getElementById('records-modal');
    const box = document.getElementById('records-modal-box');
    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    box.classList.remove('scale-100');
    box.classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 300);
}

function renderModalRecords(records) {
    const content = document.getElementById('records-modal-content');
    
    if (records.length === 0) {
        content.innerHTML = `<div class="text-center text-slate-400 py-10"><i class="ph ph-ticket text-5xl mb-3 block"></i> No matching records found.</div>`;
        return;
    }

    content.innerHTML = records.map(r => {
        const dateStr = new Date(r.issued_at.replace(/-/g, '/')).toLocaleString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
        
        let statusBadge = '';
        let amountClass = 'text-emerald-700';
        
        if (r.remitted == 1) {
            statusBadge = '<span class="px-2 py-1 bg-slate-100 text-slate-500 text-[10px] uppercase font-black rounded-lg">Remitted</span>';
            amountClass = 'text-slate-500';
        } else if (r.status === 'validated') {
            statusBadge = '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] uppercase font-black rounded-lg">Cash collected</span>';
        } else {
            statusBadge = '<span class="px-2 py-1 bg-amber-100 text-amber-700 text-[10px] uppercase font-black rounded-lg">Unpaid / Processing</span>';
            amountClass = 'text-amber-600';
        }

        return `
        <div class="flex items-center gap-4 p-4 rounded-2xl border border-slate-100 shadow-sm hover:border-blue-200 transition bg-slate-50/50">
            <div class="w-12 h-12 rounded-xl bg-white border border-slate-200 flex flex-col items-center justify-center shrink-0 shadow-sm">
                <p class="text-[9px] font-black uppercase text-slate-400 leading-none mb-0.5">${new Date(r.issued_at.replace(/-/g, '/')).toLocaleString('en-US', {month:'short'})}</p>
                <p class="text-lg font-black text-slate-700 leading-none">${new Date(r.issued_at.replace(/-/g, '/')).getDate()}</p>
            </div>
            <div class="flex-1 min-w-0">
                 <p class="font-bold text-slate-800 truncate">${r.passenger_name || 'Walk-in Passenger'}</p>
                 <p class="text-xs text-slate-500 truncate">${r.origin_name} &rarr; <span class="font-semibold text-slate-700">${r.dest_name}</span></p>
                 <p class="text-[10px] font-mono text-slate-400 mt-1">${r.ticket_code}</p>
            </div>
            <div class="text-right shrink-0">
                 <p class="font-black text-lg ${amountClass} whitespace-nowrap">₱ ${parseFloat(r.fare_amount).toFixed(2)}</p>
                 <div class="mt-1">${statusBadge}</div>
            </div>
        </div>`;
    }).join('');
}

function filterModalRecords() {
    const q = document.getElementById('records-search').value.toLowerCase();
    
    if (!q) {
        renderModalRecords(currentModalRecords);
        return;
    }

    const filtered = currentModalRecords.filter(r => 
        (r.passenger_name && r.passenger_name.toLowerCase().includes(q)) ||
        (r.ticket_code && r.ticket_code.toLowerCase().includes(q)) ||
        (r.dest_name && r.dest_name.toLowerCase().includes(q)) ||
        (r.origin_name && r.origin_name.toLowerCase().includes(q))
    );
    renderModalRecords(filtered);
}

function showRecordsModal(periodId, label) {
    const modal = document.getElementById('records-modal');
    const box = document.getElementById('records-modal-box');
    const title = document.getElementById('records-modal-title');
    const content = document.getElementById('records-modal-content');
    const loader = document.getElementById('records-modal-loader');
    const search = document.getElementById('records-search');
    
    title.textContent = label + ' Records';
    search.value = ''; // Reset search
    
    // Animate in
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        box.classList.remove('scale-95');
        box.classList.add('scale-100');
    }, 10);
    
    loader.classList.remove('hidden');
    content.innerHTML = '';
    currentModalRecords = [];

    fetch('api_get_earnings_records.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ period: periodId })
    })
    .then(r => r.json())
    .then(data => {
        loader.classList.add('hidden');
        if (!data.success) {
            content.innerHTML = `<div class="text-center text-red-500 py-8 font-bold"><i class="ph ph-warning-circle text-4xl mb-2 block"></i> Failed to load records</div>`;
            return;
        }

        currentModalRecords = data.records;
        renderModalRecords(currentModalRecords);
    })
    .catch(err => {
        loader.classList.add('hidden');
        content.innerHTML = `<div class="text-center text-red-500 py-8 font-bold"><i class="ph ph-warning-circle text-4xl mb-2 block"></i> Network error</div>`;
    });
}
</script>

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
