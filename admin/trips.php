<?php
/**
 * admin/trips.php — Full trip log with passenger details.
 */
$requiredRole = 'admin';
$pageTitle    = 'Trip Logs';
$currentPage  = 'trips.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$filterDate = $_GET['date'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = $filterDate ? "WHERE DATE(tr.started_at) = '" . $pdo->quote($filterDate) . "'" : '';
$countStmt = $pdo->query("SELECT COUNT(*) FROM trips tr $where");
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$trips = $pdo->query(
    "SELECT tr.*, b.body_number, b.plate_number, d.full_name AS driver_name,
            s1.station_name AS start_name, s2.station_name AS end_name
     FROM   trips tr
     JOIN   buses   b  ON b.id  = tr.bus_id
     JOIN   drivers d  ON d.id  = tr.driver_id
     JOIN   stations s1 ON s1.id = tr.start_station_id
     JOIN   stations s2 ON s2.id = tr.end_station_id
     $where
     ORDER  BY tr.started_at DESC
     LIMIT  $perPage OFFSET $offset"
)->fetchAll();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Trip Logs</h2>
                <p class="text-slate-500 text-sm"><?= number_format($total) ?> trips recorded</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
                       class="border border-slate-200 rounded-xl px-4 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-400">
                <button type="submit" class="bg-blue-600 text-white font-bold px-4 py-2 rounded-xl text-sm hover:bg-blue-500 transition">Filter</button>
                <?php if ($filterDate): ?>
                <a href="trips.php" class="text-slate-400 font-semibold px-3 py-2 rounded-xl text-sm hover:bg-slate-100">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto mb-6">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <?php foreach (['#','Bus','Driver','Route','Started','Ended','PAX','Revenue','Status'] as $h): ?>
                        <th class="px-5 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($trips as $tr): ?>
                    <tr class="hover:bg-slate-50 transition cursor-pointer" onclick="toggleDetails(<?= $tr['id'] ?>)">
                        <td class="px-5 py-4 text-slate-400 font-mono text-xs whitespace-nowrap"><?= $tr['id'] ?></td>
                        <td class="px-5 py-4 font-bold text-slate-800 whitespace-nowrap"><?= htmlspecialchars($tr['body_number']) ?></td>
                        <td class="px-5 py-4 text-slate-600 whitespace-nowrap"><?= htmlspecialchars($tr['driver_name']) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs min-w-[200px]"><?= htmlspecialchars($tr['start_name']) ?> &rarr; <?= htmlspecialchars($tr['end_name']) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($tr['started_at'])) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs whitespace-nowrap"><?= $tr['ended_at'] ? date('h:i A', strtotime($tr['ended_at'])) : '—' ?></td>
                        <td class="px-5 py-4 text-center font-semibold text-slate-800 whitespace-nowrap"><?= $tr['passenger_count'] ?></td>
                        <td class="px-5 py-4 font-black text-emerald-700 whitespace-nowrap"><?= peso((float)$tr['total_revenue']) ?></td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                <?= match($tr['status']) { 'active' => 'bg-orange-100 text-orange-700', 'completed' => 'bg-emerald-100 text-emerald-700', default => 'bg-red-100 text-red-600' } ?>">
                                <?= ucfirst($tr['status']) ?>
                            </span>
                            <?php if ($tr['status'] === 'active'): ?>
                            <button onclick="event.stopPropagation(); forceEndTrip(<?= $tr['id'] ?>)" 
                                    class="ml-2 text-red-500 hover:text-red-700 text-xs font-black uppercase tracking-tighter"
                                    title="Force End Trip">
                                [Force End]
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable passenger detail row -->
                    <tr id="details-<?= $tr['id'] ?>" class="hidden bg-slate-50">
                        <td colspan="9" class="px-5 py-4">
                            <p class="text-xs font-bold text-slate-400 mb-2">TICKETS IN THIS TRIP</p>
                            <div id="detail-content-<?= $tr['id'] ?>" class="text-slate-500 text-xs italic">Loading...</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-400">Page <?= $page ?> of <?= $pages ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&date=<?= urlencode($filterDate) ?>" class="px-4 py-2 rounded-xl bg-white border text-slate-700 font-semibold text-sm hover:bg-slate-100">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="?page=<?= $page+1 ?>&date=<?= urlencode($filterDate) ?>" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-500">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
function toggleDetails(tripId) {
    const row     = document.getElementById('details-' + tripId);
    const content = document.getElementById('detail-content-' + tripId);
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        fetch(`get_trip_tickets.php?trip_id=${tripId}`)
            .then(r => r.json())
            .then(data => {
                if (!data.length) { content.textContent = 'No tickets in this trip.'; return; }
                let tableHtml = `
                    <div class="border border-slate-200 rounded-xl overflow-x-auto bg-white mt-1 shadow-sm">
                        <table class="w-full text-left border-collapse whitespace-nowrap">
                            <thead class="bg-slate-100/80 text-slate-500 font-bold tracking-wider uppercase text-[10px]">
                                <tr>
                                    <th class="px-4 py-3 border-b border-slate-200">Time</th>
                                    <th class="px-4 py-3 border-b border-slate-200">Ticket Code</th>
                                    <th class="px-4 py-3 border-b border-slate-200">Passenger</th>
                                    <th class="px-4 py-3 border-b border-slate-200">Route</th>
                                    <th class="px-4 py-3 border-b border-slate-200 text-right">Fare</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                `;

                data.forEach(t => {
                    // Format time safely, assuming standard SQL YYYY-MM-DD HH:MM:SS
                    const tDate = t.issued_at.replace(/-/g, '/'); // better Safari support
                    const d = new Date(tDate);
                    // Use fallback if invalid Date
                    const timeStr = isNaN(d) ? t.issued_at.split(' ')[1].substring(0,5) : d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

                    let badgeClass = 'bg-slate-100 text-slate-600';
                    let typeRaw = (t.passenger_type || 'Regular').toLowerCase();
                    let typeText = t.passenger_type || 'Regular';
                    
                    if (typeRaw.includes('student') || typeRaw.includes('pwd') || typeRaw.includes('senior')) {
                        badgeClass = 'bg-amber-100 text-amber-700'; 
                    } else if (typeRaw.includes('special') || typeRaw.includes('teacher') || typeRaw.includes('nurse')) {
                        badgeClass = 'bg-fuchsia-100 text-fuchsia-700'; 
                    } else if (typeRaw === 'regular') {
                        badgeClass = 'bg-blue-50 text-blue-600'; 
                    }

                    tableHtml += `
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-4 py-3 text-slate-500 text-xs">${timeStr}</td>
                            <td class="px-4 py-3 text-slate-600 font-mono text-xs font-semibold">${t.ticket_code}</td>
                            <td class="px-4 py-3 text-slate-700 text-xs font-bold">
                                ${t.passenger_name} <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] uppercase font-black tracking-tight ${badgeClass}">${typeText}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 text-xs">${t.origin_name} &rarr; ${t.dest_name}</td>
                            <td class="px-4 py-3 text-emerald-700 font-black text-xs text-right">₱${parseFloat(t.fare_amount).toFixed(2)}</td>
                        </tr>
                    `;
                });

                tableHtml += `</tbody></table></div>`;
                content.innerHTML = tableHtml;
            });
    } else {
        row.classList.add('hidden');
    }
}

function forceEndTrip(tripId) {
    if (!confirm('Are you sure you want to FORCE END this trip?')) return;
    
    fetch('api_force_end_trip.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ trip_id: tripId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Success: Trip has been ended.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
