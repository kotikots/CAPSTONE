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
                        <td class="px-5 py-4 text-slate-400 font-mono text-xs"><?= $tr['id'] ?></td>
                        <td class="px-5 py-4 font-bold text-slate-800"><?= htmlspecialchars($tr['body_number']) ?></td>
                        <td class="px-5 py-4 text-slate-600"><?= htmlspecialchars($tr['driver_name']) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= htmlspecialchars($tr['start_name']) ?> → <?= htmlspecialchars($tr['end_name']) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= date('M d, Y h:i A', strtotime($tr['started_at'])) ?></td>
                        <td class="px-5 py-4 text-slate-500 text-xs"><?= $tr['ended_at'] ? date('h:i A', strtotime($tr['ended_at'])) : '—' ?></td>
                        <td class="px-5 py-4 text-center font-semibold text-slate-800"><?= $tr['passenger_count'] ?></td>
                        <td class="px-5 py-4 font-black text-emerald-700"><?= peso((float)$tr['total_revenue']) ?></td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                <?= match($tr['status']) { 'active' => 'bg-green-100 text-green-700', 'completed' => 'bg-slate-100 text-slate-600', default => 'bg-red-100 text-red-600' } ?>">
                                <?= ucfirst($tr['status']) ?>
                            </span>
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
                content.innerHTML = data.map(t =>
                    `<span class="inline-block bg-white border border-slate-200 rounded-lg px-3 py-1 mr-2 mb-2 font-mono text-xs">
                        ${t.ticket_code} · ${t.passenger_name} · ${t.origin_name}→${t.dest_name} · ₱${parseFloat(t.fare_amount).toFixed(2)}
                     </span>`
                ).join('');
            });
    } else {
        row.classList.add('hidden');
    }
}
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
