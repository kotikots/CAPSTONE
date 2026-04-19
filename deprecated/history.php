<?php
/**
 * passenger/history.php — Ride history with pagination.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Ride History';
$currentPage  = 'history.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$uid  = $_SESSION['user_id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE passenger_id = ?");
$countStmt->execute([$uid]);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT t.ticket_code, t.origin_name, t.dest_name, t.fare_amount,
            t.passenger_type, t.distance_km, t.issued_at, t.status,
            d.full_name AS driver_name, b.body_number
     FROM   tickets t
     JOIN   trips   tr ON tr.id = t.trip_id
     JOIN   buses   b  ON b.id  = tr.bus_id
     JOIN   drivers d  ON d.id  = tr.driver_id
     WHERE  t.passenger_id = ?
     ORDER  BY t.issued_at DESC
     LIMIT  ? OFFSET ?"
);
$stmt->execute([$uid, $perPage, $offset]);
$tickets = $stmt->fetchAll();

// Totals
$totStmt = $pdo->prepare("SELECT COALESCE(SUM(fare_amount),0), COUNT(*) FROM tickets WHERE passenger_id = ?");
$totStmt->execute([$uid]);
[$totSpent, $totRides] = $totStmt->fetch(\PDO::FETCH_NUM);

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Ride History</h2>
                <p class="text-slate-500 text-sm mt-1">All your past rides in one place.</p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <div class="bg-white border border-slate-100 rounded-2xl px-5 py-3 text-center shadow-sm">
                    <p class="text-slate-400">Total Rides</p>
                    <p class="font-black text-slate-800 text-xl"><?= number_format((int)$totRides) ?></p>
                </div>
                <div class="bg-white border border-slate-100 rounded-2xl px-5 py-3 text-center shadow-sm">
                    <p class="text-slate-400">Total Spent</p>
                    <p class="font-black text-blue-600 text-xl"><?= peso((float)$totSpent) ?></p>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
            <?php if (empty($tickets)): ?>
            <div class="text-center py-20 min-w-[max-content]">
                <i class="ph ph-ticket text-6xl text-slate-200 mb-4"></i>
                <h3 class="text-xl font-black text-slate-400 mb-2">No Rides Yet</h3>
                <a href="booking.php" class="mt-2 inline-block bg-blue-600 text-white font-bold px-8 py-3 rounded-2xl hover:bg-blue-500 transition active:scale-95">Book Your First Ride</a>
            </div>
            <?php else: ?>
            <table class="w-full text-sm min-w-[800px]">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Ticket</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Route</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Driver / Bus</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Distance</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase tracking-wider">Fare</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($tickets as $t): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="ticket.php?code=<?= urlencode($t['ticket_code']) ?>"
                               class="font-mono text-blue-600 hover:underline text-xs font-bold">
                                <?= htmlspecialchars($t['ticket_code']) ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 min-w-[200px]">
                            <p class="font-semibold text-slate-800"><?= htmlspecialchars($t['origin_name']) ?></p>
                            <p class="text-slate-400 text-xs flex items-center gap-1"><i class="ph ph-arrow-down text-xs"></i><?= htmlspecialchars($t['dest_name']) ?></p>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                <?= match(strtolower($t['passenger_type'])) {
                                    'regular' => 'bg-blue-100 text-blue-700',
                                    'special'   => 'bg-pink-100 text-pink-700',
                                    'student' => 'bg-yellow-100 text-yellow-700',
                                    default => 'bg-slate-100 text-slate-600'
                                } ?>">
                                <?= ucfirst(htmlspecialchars($t['passenger_type'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                            <p class="font-medium"><?= htmlspecialchars($t['driver_name']) ?></p>
                            <p class="text-xs text-slate-400"><?= htmlspecialchars($t['body_number']) ?></p>
                        </td>
                        <td class="px-6 py-4 text-slate-600 whitespace-nowrap"><?= $t['distance_km'] ?> km</td>
                        <td class="px-6 py-4 text-slate-500 text-xs whitespace-nowrap"><?= date('M d, Y h:i A', strtotime($t['issued_at'])) ?></td>
                        <td class="px-6 py-4 text-right font-black text-slate-800"><?= peso((float)$t['fare_amount']) ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="text-xs font-bold px-2 py-1 rounded-full
                                <?= $t['status']==='validated' ? 'bg-green-100 text-green-700' : ($t['status']==='cancelled' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600') ?>">
                                <?= ucfirst($t['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100">
                <p class="text-sm text-slate-400">Page <?= $page ?> of <?= $pages ?></p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold text-sm hover:bg-slate-200">← Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $pages): ?>
                    <a href="?page=<?= $page+1 ?>" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-500">Next →</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body>
</html>
