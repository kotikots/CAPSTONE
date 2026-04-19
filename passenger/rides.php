<?php
/**
 * passenger/rides.php
 * Full ride history with pagination.
 */
$requiredRole = 'passenger';
$pageTitle    = 'My Rides';
$currentPage  = 'rides.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions_v2.php';

$uid = $_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE passenger_id = ?");
$countStmt->execute([$uid]);
$totalRides = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRides / $perPage));

// Stats
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS rides, COALESCE(SUM(fare_amount),0) AS spent, COALESCE(SUM(distance_km),0) AS km
     FROM tickets WHERE passenger_id = ?"
);
$statsStmt->execute([$uid]);
$stats = $statsStmt->fetch();

// Rides
$ridesStmt = $pdo->prepare(
    "SELECT tk.ticket_code, tk.origin_name, tk.dest_name, tk.fare_amount, tk.passenger_type,
            tk.issued_at, tk.distance_km, tk.status,
            b.body_number, d.full_name AS driver_name
     FROM tickets tk
     JOIN trips tr ON tr.id = tk.trip_id
     JOIN buses b ON b.id = tr.bus_id
     JOIN drivers d ON d.id = tr.driver_id
     WHERE tk.passenger_id = ?
     ORDER BY tk.issued_at DESC
     LIMIT ? OFFSET ?"
);
$ridesStmt->execute([$uid, $perPage, $offset]);
$rides = $ridesStmt->fetchAll();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">My Rides</h2>
                <p class="text-slate-500 text-sm mt-1"><?= number_format($totalRides) ?> total ride(s)</p>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center">
                    <i class="ph ph-ticket text-xl text-blue-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium">Total Rides</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format((int)$stats['rides']) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <i class="ph ph-coins text-xl text-emerald-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium">Total Spent</p>
                    <p class="text-2xl font-black text-emerald-700"><?= peso((float)$stats['spent']) ?></p>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="ph ph-path text-xl text-orange-600"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs font-medium">Total Distance</p>
                    <p class="text-2xl font-black text-slate-800"><?= number_format((float)$stats['km'], 1) ?> km</p>
                </div>
            </div>
        </div>

        <!-- Rides Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6">
            <?php if (empty($rides)): ?>
            <div class="text-center py-16 text-slate-400">
                <i class="ph ph-ticket text-6xl mb-3 block"></i>
                <p class="text-base font-bold mb-1">No rides yet</p>
                <p class="text-sm">Your ride history will appear here after you use the kiosk with your registered ID</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                            <th class="py-3 pr-4">Ticket</th>
                            <th class="py-3 pr-4">Route</th>
                            <th class="py-3 pr-4">Distance</th>
                            <th class="py-3 pr-4">Bus</th>
                            <th class="py-3 pr-4">Type</th>
                            <th class="py-3 pr-4 text-right">Fare</th>
                            <th class="py-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($rides as $r): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="py-3.5 pr-4">
                                <span class="font-mono font-bold text-blue-700 text-xs"><?= htmlspecialchars($r['ticket_code']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4">
                                <p class="text-slate-700"><?= htmlspecialchars($r['origin_name']) ?> <span class="text-slate-400">→</span> <?= htmlspecialchars($r['dest_name']) ?></p>
                                <p class="text-xs text-slate-400"><?= htmlspecialchars($r['driver_name']) ?></p>
                            </td>
                            <td class="py-3.5 pr-4 text-slate-500"><?= number_format((float)$r['distance_km'], 1) ?> km</td>
                            <td class="py-3.5 pr-4">
                                <span class="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-1 rounded-lg"><?= htmlspecialchars($r['body_number']) ?></span>
                            </td>
                            <td class="py-3.5 pr-4 text-xs font-medium text-slate-500"><?= htmlspecialchars($r['passenger_type']) ?></td>
                            <td class="py-3.5 pr-4 text-right font-black text-slate-800"><?= peso((float)$r['fare_amount']) ?></td>
                            <td class="py-3.5 text-right text-slate-400 text-xs">
                                <?= date('M d, Y', strtotime($r['issued_at'])) ?><br>
                                <span class="text-slate-300"><?= date('h:i A', strtotime($r['issued_at'])) ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6 pt-4 border-t border-slate-100">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-blue-100 text-slate-600 hover:text-blue-700 flex items-center justify-center transition">
                    <i class="ph ph-caret-left font-bold"></i>
                </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold transition
                   <?= $i === $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30' : 'bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-700' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-blue-100 text-slate-600 hover:text-blue-700 flex items-center justify-center transition">
                    <i class="ph ph-caret-right font-bold"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body></html>
