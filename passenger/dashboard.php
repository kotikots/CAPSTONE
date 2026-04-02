<?php
/**
 * passenger/dashboard.php   — STEP 6
 * Main passenger dashboard: stats, active bus, quick booking CTA, recent rides.
 */
$requiredRole = 'passenger';
$pageTitle    = 'Dashboard';
$currentPage  = 'dashboard.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$uid = $_SESSION['user_id'];

// --- Stats ---
$stmtRides = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(fare_amount),0) FROM tickets WHERE passenger_id = ?");
$stmtRides->execute([$uid]);
[$totalRides, $totalSpent] = $stmtRides->fetch(\PDO::FETCH_NUM);

$stmtMonth = $pdo->prepare("SELECT COALESCE(SUM(fare_amount),0) FROM tickets WHERE passenger_id = ? AND MONTH(issued_at)=MONTH(NOW()) AND YEAR(issued_at)=YEAR(NOW())");
$stmtMonth->execute([$uid]);
$monthSpent = (float)$stmtMonth->fetchColumn();

// --- Recent tickets ---
$stmtRecent = $pdo->prepare(
    "SELECT t.ticket_code, t.origin_name, t.dest_name, t.fare_amount, t.passenger_type, t.issued_at, t.status
     FROM   tickets t WHERE t.passenger_id = ? ORDER BY t.issued_at DESC LIMIT 5"
);
$stmtRecent->execute([$uid]);
$recentTickets = $stmtRecent->fetchAll();

// --- Active bus info (Bus 1) ---
$busStmt = $pdo->prepare("SELECT b.*, d.full_name AS driver_name FROM buses b JOIN drivers d ON d.id = b.driver_id WHERE b.id = 1");
$busStmt->execute();
$bus = $busStmt->fetch();
$activeTrip = $bus ? getActiveTripForBus($pdo, 1) : null;
$busLoc     = $bus ? getLatestBusLocation($pdo, 1) : null;

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <!-- Top bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Welcome back, <br class="md:hidden"><?= htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]) ?>! 👋</h2>
                <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
            </div>
            <a href="booking.php"
               class="flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold w-full md:w-auto px-6 py-4 md:py-3 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition-all active:scale-95">
                <i class="ph ph-map-pin text-xl"></i>
                Book a Ride
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <?php foreach ([
                ['label' => 'Total Rides',    'value' => (int)$totalRides,     'icon' => 'ph-ticket',     'color' => 'blue',    'suffix' => ''],
                ['label' => 'Total Spent',    'value' => (float)$totalSpent,   'icon' => 'ph-coins',      'color' => 'emerald', 'suffix' => 'peso'],
                ['label' => 'This Month',     'value' => $monthSpent,          'icon' => 'ph-calendar',   'color' => 'violet',  'suffix' => 'peso'],
            ] as $card): ?>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-<?= $card['color'] ?>-100 flex items-center justify-center shrink-0">
                    <i class="ph <?= $card['icon'] ?> text-2xl text-<?= $card['color'] ?>-600"></i>
                </div>
                <div>
                    <p class="text-slate-500 text-sm font-medium"><?= $card['label'] ?></p>
                    <p class="text-2xl font-black text-slate-800">
                        <?= $card['suffix'] === 'peso' ? peso((float)$card['value']) : number_format((int)$card['value']) ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Active Bus Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 h-full">
                    <h3 class="font-bold text-slate-700 mb-5 flex items-center gap-2">
                        <i class="ph ph-bus text-blue-600"></i> Live Bus Status
                    </h3>

                    <?php if ($bus): ?>
                    <div class="flex items-center gap-4 mb-5">
                        <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                            <i class="ph ph-bus-fill text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="font-black text-lg text-slate-800"><?= htmlspecialchars($bus['body_number']) ?></p>
                            <p class="text-slate-400 text-sm"><?= htmlspecialchars($bus['plate_number']) ?></p>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Driver</span>
                            <span class="font-semibold text-slate-700"><?= htmlspecialchars($bus['driver_name']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Status</span>
                            <?php if ($activeTrip): ?>
                            <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-xs font-bold px-3 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span> On Route
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 bg-slate-400 rounded-full"></span> Not on Route
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($activeTrip): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Route</span>
                            <span class="font-semibold text-slate-700 text-right"><?= htmlspecialchars($activeTrip['start_name']) ?> → <?= htmlspecialchars($activeTrip['end_name']) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Passengers</span>
                            <span class="font-semibold text-slate-700"><?= $activeTrip['passenger_count'] ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($busLoc): ?>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Last Update</span>
                            <span class="font-semibold text-slate-700"><?= date('h:i A', strtotime($busLoc['recorded_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="map.php" class="mt-5 w-full flex items-center justify-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-600 font-bold py-3 rounded-2xl transition text-sm">
                        <i class="ph ph-map-trifold"></i> View Live Map
                    </a>

                    <?php else: ?>
                    <div class="text-center py-8 text-slate-400">
                        <i class="ph ph-bus text-4xl mb-2"></i>
                        <p class="text-sm">No bus data available</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Rides -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="font-bold text-slate-700 flex items-center gap-2">
                            <i class="ph ph-clock-counter-clockwise text-blue-600"></i> Recent Rides
                        </h3>
                        <a href="history.php" class="text-blue-500 text-sm font-semibold hover:underline">View all →</a>
                    </div>

                    <?php if (empty($recentTickets)): ?>
                    <div class="text-center py-12">
                        <i class="ph ph-ticket text-5xl text-slate-200 mb-3"></i>
                        <p class="text-slate-400 font-medium">No rides yet.</p>
                        <a href="booking.php" class="mt-3 inline-block text-blue-500 font-semibold text-sm hover:underline">Book your first ride →</a>
                    </div>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recentTickets as $t): ?>
                        <a href="ticket.php?code=<?= urlencode($t['ticket_code']) ?>"
                           class="flex items-center gap-4 p-4 rounded-2xl hover:bg-slate-50 transition group">
                            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center shrink-0 group-hover:bg-blue-200 transition">
                                <i class="ph ph-ticket text-blue-600"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-slate-800 text-sm truncate">
                                    <?= htmlspecialchars($t['origin_name']) ?> → <?= htmlspecialchars($t['dest_name']) ?>
                                </p>
                                <p class="text-slate-400 text-xs mt-0.5">
                                    <?= date('M d, Y · h:i A', strtotime($t['issued_at'])) ?> ·
                                    <span class="font-medium text-slate-500"><?= htmlspecialchars($t['passenger_type']) ?></span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-black text-slate-800"><?= peso((float)$t['fare_amount']) ?></p>
                                <span class="text-xs font-medium <?= $t['status']==='validated' ? 'text-green-600' : 'text-blue-500' ?>">
                                    <?= ucfirst($t['status']) ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /grid -->
    </main>
</div>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body>
</html>
