<?php
/**
 * admin/drivers.php
 * Admin view of all drivers with trip stats and assigned bus.
 */
$requiredRole = 'admin';
$pageTitle    = 'Drivers';
$currentPage  = 'drivers.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$drivers = $pdo->query(
    "SELECT d.*,
            b.body_number, b.plate_number, b.model,
            COUNT(DISTINCT tr.id)   AS total_trips,
            COUNT(DISTINCT t.id)    AS total_tickets,
            COALESCE(SUM(t.fare_amount), 0) AS total_revenue
     FROM   drivers d
     LEFT JOIN buses   b  ON b.driver_id = d.id AND b.is_active = 1
     LEFT JOIN trips   tr ON tr.driver_id = d.id
     LEFT JOIN tickets t  ON t.trip_id   = tr.id
     WHERE  d.is_active = 1
     GROUP  BY d.id
     ORDER  BY total_revenue DESC"
)->fetchAll();

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="flex-1 p-8 overflow-auto bg-slate-50">

        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800">Drivers</h2>
                <p class="text-slate-500 text-sm"><?= count($drivers) ?> active driver(s)</p>
            </div>
            <a href="add_driver.php" 
               class="flex items-center gap-2 bg-orange-600 hover:bg-orange-500 text-white font-bold px-5 py-3 rounded-2xl shadow-lg hover:shadow-orange-500/30 transition active:scale-95">
                <i class="ph ph-plus-circle text-xl"></i> Add New Driver
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($drivers as $d): ?>
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <!-- Driver Header -->
                <div class="flex items-center gap-4 mb-5">
                    <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center shrink-0">
                        <?php if ($d['profile_picture']): ?>
                        <img src="/PARE/<?= htmlspecialchars($d['profile_picture']) ?>" class="w-full h-full object-cover rounded-2xl">
                        <?php else: ?>
                        <i class="ph ph-steering-wheel text-2xl text-orange-500"></i>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-black text-slate-800 truncate"><?= htmlspecialchars($d['full_name']) ?></p>
                        <p class="text-slate-400 text-xs">License: <?= htmlspecialchars($d['license_number']) ?></p>
                        <span class="inline-block mt-1 bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">Active</span>
                    </div>
                </div>

                <!-- Bus Assignment -->
                <div class="bg-orange-50 rounded-2xl px-4 py-3 mb-4 flex items-center gap-3">
                    <i class="ph ph-bus text-orange-500 text-xl shrink-0"></i>
                    <?php if ($d['body_number']): ?>
                    <div>
                        <p class="font-bold text-orange-800 text-sm"><?= htmlspecialchars($d['body_number']) ?></p>
                        <p class="text-orange-500 text-xs"><?= htmlspecialchars($d['plate_number']) ?> · <?= htmlspecialchars($d['model'] ?? '') ?></p>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center justify-between w-full">
                        <p class="text-orange-400 text-sm italic">No bus assigned</p>
                        <a href="add_bus.php?driver_id=<?= $d['id'] ?>" 
                           class="bg-orange-600 text-white text-[10px] uppercase font-black px-3 py-1.5 rounded-lg hover:bg-orange-500 transition shadow-sm">
                            Assign Bus
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <?php foreach ([
                        ['Trips',      $d['total_trips'],   'ph-map-pin'],
                        ['Tickets',    $d['total_tickets'], 'ph-ticket'],
                    ] as [$label, $val, $icon]): ?>
                    <div class="text-center p-2 bg-slate-50 rounded-xl">
                        <p class="font-black text-slate-800"><?= number_format((int)$val) ?></p>
                        <p class="text-slate-400 text-xs"><?= $label ?></p>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center p-2 bg-emerald-50 rounded-xl col-span-1">
                        <p class="font-black text-emerald-700 text-sm"><?= peso((float)$d['total_revenue']) ?></p>
                        <p class="text-slate-400 text-xs">Revenue</p>
                    </div>
                </div>

                <!-- Contact -->
                <div class="space-y-1 text-xs text-slate-500 border-t border-slate-100 pt-3">
                    <p class="flex items-center gap-1.5"><i class="ph ph-phone"></i><?= htmlspecialchars($d['contact_number']) ?></p>
                    <?php if ($d['email']): ?>
                    <p class="flex items-center gap-1.5"><i class="ph ph-envelope"></i><?= htmlspecialchars($d['email']) ?></p>
                    <?php endif; ?>
                    <p class="flex items-center gap-1.5"><i class="ph ph-calendar"></i>Joined <?= date('M d, Y', strtotime($d['created_at'])) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body></html>
