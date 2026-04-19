<?php
/**
 * includes/sidebar_admin.php
 * Left sidebar for admin users.
 */
$currentPage = $currentPage ?? '';
?>
<style>
/* Hide scrollbar for the sidebar */
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar {
    -ms-overflow-style: none;  border:none; /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>
<aside class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-white hidden md:flex flex-col h-screen shadow-2xl shrink-0 sticky top-0">
    <!-- Logo -->
    <div class="px-6 py-5 flex items-center gap-3 border-b border-white/10">
        <i class="ph ph-bus-fill text-2xl text-blue-400"></i>
        <div>
            <h1 class="text-xl font-black tracking-tight">PARE</h1>
            <p class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">Admin Panel</p>
        </div>
    </div>

    <!-- Admin Badge -->
    <div class="px-6 py-3 border-b border-white/10 bg-white/5">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                <i class="ph ph-shield-check-fill text-blue-400 text-lg"></i>
            </div>
            <div>
                <p class="font-bold text-xs truncate w-32"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></p>
                <p class="text-[9px] text-blue-400/80 font-bold uppercase tracking-tighter">System Admin</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto no-scrollbar">
        <?php
        $navGroups = [
            'Overview' => [
                ['href' => '/PARE/admin/dashboard.php', 'icon' => 'ph-chart-pie',         'label' => 'Dashboard'],
            ],
            'Management' => [
                ['href' => '/PARE/admin/passengers.php','icon' => 'ph-users',              'label' => 'Passengers'],
                ['href' => '/PARE/admin/drivers.php',   'icon' => 'ph-steering-wheel',     'label' => 'Drivers'],
                ['href' => '/PARE/admin/buses.php',     'icon' => 'ph-bus',                'label' => 'Buses'],
            ],
            'Security' => [
                ['href' => '/PARE/admin/security.php',  'icon' => 'ph-shield-checkered',   'label' => 'Security Logs'],
            ],
            'Operations' => [
                ['href' => '/PARE/admin/trips.php',     'icon' => 'ph-map-pin-line',       'label' => 'Trip Logs'],
                ['href' => '/PARE/admin/fare_settings.php','icon' => 'ph-money',           'label' => 'Fare Settings'],
                ['href' => '/PARE/admin/reports.php',   'icon' => 'ph-file-text',          'label' => 'Reports & Export'],
                ['href' => '/PARE/admin/remittance.php','icon' => 'ph-wallet',             'label' => 'Remittances'],
            ],
        ];
        foreach ($navGroups as $group => $items):
        ?>
        <p class="px-4 pt-2 pb-1 text-[10px] font-black text-slate-500 uppercase tracking-widest"><?= $group ?></p>
        <?php foreach ($items as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-2 rounded-xl font-semibold text-xs
                  <?= $active ? 'bg-blue-600/20 text-blue-300 shadow-inner' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-base w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; endforeach; ?>
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
