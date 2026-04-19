<?php
/**
 * includes/sidebar_driver.php
 * Left sidebar for authenticated drivers.
 */
$currentPage = $currentPage ?? '';

// Live bus query — always reflects admin's latest assignment
$_sidebarBus = null;
if (isset($pdo) && isset($_SESSION['driver_id'])) {
    $_sbStmt = $pdo->prepare("SELECT body_number FROM buses WHERE driver_id = ? AND is_active = 1 LIMIT 1");
    $_sbStmt->execute([$_SESSION['driver_id']]);
    $_sidebarBus = $_sbStmt->fetchColumn() ?: null;
    // Also update the session for consistency
    $_SESSION['bus_body'] = $_sidebarBus ?? '—';
}
?>
<aside class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-white hidden md:flex flex-col h-screen shadow-2xl shrink-0 sticky top-0">
    <!-- Logo -->
    <div class="px-6 py-5 flex items-center gap-3 border-b border-white/10">
        <i class="ph ph-bus-fill text-2xl text-blue-400"></i>
        <div>
            <h1 class="text-xl font-black tracking-tight">PARE</h1>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">Driver Portal</p>
        </div>
    </div>

    <!-- Driver Info -->
    <div class="px-6 py-3 border-b border-white/10 bg-white/5">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                <i class="ph ph-steering-wheel text-blue-400 text-lg"></i>
            </div>
            <div>
                <p class="font-bold text-xs truncate w-32 leading-tight"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?></p>
                <p class="text-[9px] text-blue-400/80 uppercase font-bold tracking-tighter">Bus: <?= htmlspecialchars($_sidebarBus ?? '—') ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto no-scrollbar">
        <?php
        $navItems = [
            ['href' => '/PARE/driver/dashboard_v2.php',  'icon' => 'ph-squares-four',    'label' => 'Dashboard'],
            ['href' => '/PARE/driver/passengers.php', 'icon' => 'ph-users',           'label' => 'Passengers Today'],
            ['href' => '/PARE/driver/earnings.php',   'icon' => 'ph-coins',           'label' => 'My Earnings'],
        ];
        foreach ($navItems as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-semibold text-xs
                  <?= $active ? 'bg-blue-600/20 text-blue-300 shadow-inner' : 'text-slate-400 hover:bg-white/10 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-base w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-3 border-t border-white/10">
        <a href="/PARE/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-slate-500 hover:bg-red-500/20 hover:text-red-300 text-xs font-bold transition-all">
            <i class="ph ph-sign-out text-base"></i>
            Logout
        </a>
    </div>
</aside>
