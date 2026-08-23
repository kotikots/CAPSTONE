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
<aside class="w-64 text-white hidden md:flex flex-col h-screen shadow-2xl shrink-0 sticky top-0" style="background-color: #061A53;">
    <!-- Logo -->
    <div class="px-6 py-5 flex items-center gap-3 border-b border-white/10">
        <img src="<?= BASE_PATH ?>/assets/img/logo_white.png?v=1" alt="PARE Logo" class="w-auto h-8 object-contain drop-shadow-md">
        <div>

            <p class="text-[10px] text-blue-200 font-medium uppercase tracking-wider">Admin Panel</p>
        </div>
    </div>

    <!-- Admin Badge -->
    <div class="px-6 py-3 border-b border-white/10 bg-white/5">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                <i class="ph-fill ph-shield-check text-blue-400 text-lg"></i>
            </div>
            <div>
                <p class="font-bold text-xs truncate w-32"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></p>
                <p class="text-[9px] text-blue-200 font-bold uppercase tracking-tighter">System Admin</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav id="sidebar-nav" class="flex-1 px-3 py-4 space-y-1 overflow-y-auto no-scrollbar">
        <?php
        $navGroups = [
            'Overview' => [
                ['href' => BASE_PATH . '/admin/dashboard.php', 'icon' => 'ph-chart-pie',         'label' => 'Dashboard'],
            ],
            'Operations' => [
                ['href' => BASE_PATH . '/admin/trips.php',     'icon' => 'ph-map-pin-line',       'label' => 'Trip Logs'],
                ['href' => BASE_PATH . '/admin/fare_settings.php','icon' => 'ph-money',           'label' => 'Fare Settings'],
                ['href' => BASE_PATH . '/admin/reports.php',   'icon' => 'ph-file-text',          'label' => 'Reports & Export'],
                ['href' => BASE_PATH . '/admin/remittance.php','icon' => 'ph-wallet',             'label' => 'Remittances'],
            ],
            'Management' => [
                ['href' => BASE_PATH . '/admin/passengers.php','icon' => 'ph-users',              'label' => 'Passengers'],
                ['href' => BASE_PATH . '/admin/drivers.php',   'icon' => 'ph-steering-wheel',     'label' => 'Drivers'],
                ['href' => BASE_PATH . '/admin/buses.php',     'icon' => 'ph-bus',                'label' => 'Buses'],
            ],
            'Security' => [
                ['href' => BASE_PATH . '/admin/security.php',  'icon' => 'ph-shield-checkered',   'label' => 'Security Logs'],
            ],
        ];

        foreach ($navGroups as $group => $items):
        ?>
        <div class="pt-4 pb-1 first:pt-0">
            <p class="px-4 text-[10px] font-black text-blue-300 uppercase tracking-widest mb-1"><?= $group ?></p>
            <?php foreach ($items as $item): 
                $active = str_contains($currentPage, basename($item['href']));
            ?>
            <a href="<?= $item['href'] ?>"
               class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-semibold text-xs
                      <?= $active ? 'bg-white/20 text-white shadow-inner' : 'text-blue-100 hover:bg-white/10 hover:text-white' ?>">
                <i class="ph <?= $item['icon'] ?> text-base w-5 text-center"></i>
                <?= $item['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-3 border-t border-white/10">
        <a href="<?= BASE_PATH ?>/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-blue-200 hover:bg-red-500/20 hover:text-white text-xs font-bold transition-all">
            <i class="ph ph-sign-out text-base"></i>
            Logout
        </a>
    </div>
</aside>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        const nav = document.getElementById('sidebar-nav');
        if (nav) {
            const scrollPos = sessionStorage.getItem('admin-sidebar-scroll');
            if (scrollPos) nav.scrollTop = scrollPos;
            
            nav.addEventListener('scroll', () => {
                sessionStorage.setItem('admin-sidebar-scroll', nav.scrollTop);
            });
        }
    });
</script>
