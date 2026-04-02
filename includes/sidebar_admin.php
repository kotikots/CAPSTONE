<?php
/**
 * includes/sidebar_admin.php
 * Left sidebar for admin users.
 */
$currentPage = $currentPage ?? '';
?>
<aside class="w-64 bg-gradient-to-b from-slate-900 to-slate-800 text-white hidden md:flex flex-col min-h-screen shadow-2xl shrink-0">
    <!-- Logo -->
    <div class="px-6 py-8 flex items-center gap-3 border-b border-white/10">
        <i class="ph ph-bus-fill text-3xl text-emerald-400"></i>
        <div>
            <h1 class="text-2xl font-black tracking-tight">PARE</h1>
            <p class="text-xs text-slate-400 font-medium">Admin Panel</p>
        </div>
    </div>

    <!-- Admin Badge -->
    <div class="px-6 py-4 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center">
                <i class="ph ph-shield-check-fill text-emerald-400 text-xl"></i>
            </div>
            <div>
                <p class="font-semibold text-sm"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></p>
                <p class="text-xs text-emerald-400 font-medium">System Admin</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1">
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
            'Operations' => [
                ['href' => '/PARE/admin/trips.php',     'icon' => 'ph-map-pin-line',       'label' => 'Trip Logs'],
                ['href' => '/PARE/admin/reports.php',   'icon' => 'ph-file-text',          'label' => 'Reports & Export'],
            ],
        ];
        foreach ($navGroups as $group => $items):
        ?>
        <p class="px-4 pt-3 pb-1 text-xs font-bold text-slate-500 uppercase tracking-widest"><?= $group ?></p>
        <?php foreach ($items as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm
                  <?= $active ? 'bg-emerald-500/20 text-emerald-300' : 'text-slate-300 hover:bg-white/5 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-lg w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-white/10">
        <a href="/PARE/auth/logout.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-400 hover:bg-red-500/20 hover:text-red-300 text-sm font-medium">
            <i class="ph ph-sign-out text-lg"></i>
            Logout
        </a>
    </div>
</aside>
