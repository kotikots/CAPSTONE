<?php
/**
 * driver/passengers.php
 * Shows all passengers boarded during today's trips for this driver.
 */
$requiredRole = 'driver';
$pageTitle    = 'Passengers Today';
$currentPage  = 'passengers.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$driverId = $_SESSION['driver_id'];

// Get driver's bus
$busStmt = $pdo->prepare("SELECT id, body_number FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
$busStmt->execute([$driverId]);
$bus = $busStmt->fetch();

$passengers = [];
$totals = ['count' => 0, 'revenue' => 0];

if ($bus) {
    $stmt = $pdo->prepare(
        "SELECT t.ticket_code, t.passenger_name, t.passenger_type,
                t.origin_name, t.dest_name, t.distance_km,
                t.fare_amount, t.issued_at, t.status
         FROM   tickets t
         JOIN   trips   tr ON tr.id = t.trip_id
         WHERE  tr.driver_id = ? AND DATE(t.issued_at) = CURDATE()
         ORDER  BY t.issued_at DESC"
    );
    $stmt->execute([$driverId]);
    $passengers = $stmt->fetchAll();

    $totals['count']   = count($passengers);
    $totals['revenue'] = array_sum(array_column($passengers, 'fare_amount'));
}

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_driver.php'; ?>
    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 mt-2 md:mt-0">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Passengers Today</h2>
                <p class="text-slate-500 text-sm"><?= date('l, F j, Y') ?> · Bus <?= htmlspecialchars($bus['body_number'] ?? '—') ?></p>
            </div>
            <div class="flex gap-4">
                <div class="bg-white border border-slate-100 rounded-2xl px-5 py-3 text-center shadow-sm">
                    <p class="text-slate-400 text-xs">Passengers</p>
                    <p class="font-black text-slate-800 text-2xl"><?= $totals['count'] ?></p>
                </div>
                <div class="bg-white border border-slate-100 rounded-2xl px-5 py-3 text-center shadow-sm">
                    <p class="text-slate-400 text-xs">Revenue</p>
                    <p class="font-black text-emerald-700 text-2xl"><?= peso((float)$totals['revenue']) ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
            <?php if (empty($passengers)): ?>
            <div class="text-center py-20">
                <i class="ph ph-users text-6xl text-slate-200 mb-4"></i>
                <p class="text-slate-400 font-medium">No passengers yet today.</p>
                <p class="text-slate-300 text-sm mt-1">Start a trip and passengers will appear here.</p>
            </div>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <?php foreach (['Ticket','Passenger','Type','Route','Distance','Time','Fare','Status'] as $h): ?>
                        <th class="px-5 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($passengers as $p): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-5 py-4 font-mono text-xs text-blue-600"><?= htmlspecialchars($p['ticket_code']) ?></td>
                        <td class="px-5 py-4 font-semibold text-slate-800"><?= htmlspecialchars($p['passenger_name']) ?></td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 rounded-lg text-xs font-bold
                                <?= match(strtolower($p['passenger_type'])) { 'regular' => 'bg-blue-100 text-blue-700', 'student' => 'bg-yellow-100 text-yellow-700', 'special' => 'bg-pink-100 text-pink-700', default => 'bg-slate-100 text-slate-600' } ?>">
                                <?= ucfirst(htmlspecialchars($p['passenger_type'])) ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-600 text-xs">
                            <?= htmlspecialchars($p['origin_name']) ?> → <?= htmlspecialchars($p['dest_name']) ?>
                        </td>
                        <td class="px-5 py-4 text-slate-500"><?= $p['distance_km'] ?> km</td>
                        <td class="px-5 py-4 text-slate-400 text-xs"><?= date('h:i A', strtotime($p['issued_at'])) ?></td>
                        <td class="px-5 py-4 font-black text-slate-800"><?= peso((float)$p['fare_amount']) ?></td>
                        <td class="px-5 py-4">
                            <span class="text-xs font-bold px-2 py-1 rounded-full
                                <?= $p['status']==='validated' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-600' ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-50 border-t-2 border-slate-200">
                    <tr>
                        <td colspan="6" class="px-5 py-4 font-black text-slate-600">TOTAL (<?= $totals['count'] ?> passengers)</td>
                        <td class="px-5 py-4 font-black text-emerald-700"><?= peso((float)$totals['revenue']) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include '../includes/mobile_nav_driver.php'; ?>
</body></html>
