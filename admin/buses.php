<?php
/**
 * admin/buses.php
 * Admin view of all buses with driver and revenue info.
 */
$requiredRole = 'admin';
$pageTitle    = 'Buses';
$currentPage  = 'buses.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_bus_id'])) {
    $toggleId = (int)$_POST['toggle_bus_id'];
    $newState = (int)$_POST['new_state'];
    $pdo->prepare("UPDATE buses SET is_active = ? WHERE id = ?")->execute([$newState, $toggleId]);
    header('Location: buses.php');
    exit;
}

$buses = $pdo->query(
    "SELECT b.*,
            d.full_name AS driver_name, d.license_number, d.contact_number,
            COUNT(DISTINCT tr.id)  AS total_trips,
            COUNT(DISTINCT t.id)   AS total_tickets,
            COALESCE(SUM(t.fare_amount), 0) AS total_revenue,
            (SELECT status FROM trips WHERE bus_id = b.id ORDER BY started_at DESC LIMIT 1) AS last_status
     FROM   buses b
     LEFT JOIN drivers d ON d.id = b.driver_id
     LEFT JOIN trips   tr ON tr.bus_id   = b.id
     LEFT JOIN tickets t  ON t.trip_id   = tr.id
     GROUP  BY b.id
     ORDER  BY b.is_active DESC, total_revenue DESC"
)->fetchAll();

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>
    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Fleet Management</h2>
                <p class="text-slate-500 text-sm"><?= count($buses) ?> bus(es) in fleet</p>
            </div>
            <a href="add_bus.php" 
               class="flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-bold px-5 py-3 rounded-2xl shadow-lg hover:shadow-blue-500/30 transition active:scale-95">
                <i class="ph ph-plus-circle text-xl"></i> Add New Bus
            </a>
        </div>

        <!-- Fleet Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr>
                        <?php foreach (['Bus','Model','Capacity','Driver','Total Trips','Tickets','Revenue','Status','Actions'] as $h): ?>
                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-wider"><?= $h ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($buses as $b): ?>
                    <?php $isInactive = !$b['is_active']; ?>
                    <tr class="hover:bg-slate-50 transition <?= $isInactive ? 'opacity-50' : '' ?>">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl <?= $isInactive ? 'bg-red-100' : 'bg-blue-100' ?> flex items-center justify-center">
                                    <i class="ph ph-bus <?= $isInactive ? 'text-red-400' : 'text-blue-600' ?>"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="font-black text-slate-800"><?= htmlspecialchars($b['body_number']) ?></p>
                                        <?php if ($isInactive): ?>
                                        <span class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Inactive</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($b['plate_number']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600"><?= htmlspecialchars($b['model'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-center">
                            <span class="bg-slate-100 text-slate-700 font-bold text-xs px-3 py-1 rounded-full"><?= $b['capacity'] ?> seats</span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($b['driver_name']): ?>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($b['driver_name']) ?></p>
                                <p class="text-slate-400 text-xs"><?= htmlspecialchars($b['contact_number']) ?></p>
                            <?php else: ?>
                                <p class="text-slate-400 italic">Unassigned</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center font-semibold text-slate-700"><?= number_format((int)$b['total_trips']) ?></td>
                        <td class="px-6 py-4 text-center font-semibold text-slate-700"><?= number_format((int)$b['total_tickets']) ?></td>
                        <td class="px-6 py-4 font-black text-emerald-700"><?= peso((float)$b['total_revenue']) ?></td>
                        <td class="px-6 py-4">
                            <?php if ($isInactive): ?>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-red-100 text-red-600">🔴 Inactive</span>
                            <?php else: ?>
                                <?php $status = $b['last_status'] ?? 'idle'; ?>
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold
                                    <?= $status === 'active' ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500' ?>">
                                    <?= $status === 'active' ? '🟠 On Route' : '⚫ Idle' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" class="inline" onsubmit="return confirm('<?= $isInactive ? 'Reactivate' : 'Deactivate' ?> this bus?')">
                                    <input type="hidden" name="toggle_bus_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="new_state" value="<?= $isInactive ? 1 : 0 ?>">
                                    <button type="submit" 
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition shadow-sm
                                                   <?= $isInactive ? 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200' : 'bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600' ?>"
                                            title="<?= $isInactive ? 'Reactivate Bus' : 'Deactivate Bus' ?>">
                                        <i class="ph <?= $isInactive ? 'ph-power' : 'ph-power' ?> font-bold"></i>
                                    </button>
                                </form>
                                <a href="edit_bus.php?id=<?= $b['id'] ?>" 
                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 text-slate-500 hover:bg-blue-100 hover:text-blue-600 transition shadow-sm"
                                   title="Edit Bus">
                                    <i class="ph ph-pencil-simple font-bold"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($buses)): ?>
            <div class="text-center py-16 text-slate-400">
                <i class="ph ph-bus text-5xl mb-3"></i>
                <p>No buses registered yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include '../includes/mobile_nav_admin.php'; ?>
</body></html>
