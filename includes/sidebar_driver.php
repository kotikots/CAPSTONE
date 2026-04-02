<?php
/**
 * includes/sidebar_driver.php
 * Left sidebar for authenticated drivers.
 */
$currentPage = $currentPage ?? '';
?>
<aside class="w-64 bg-gradient-to-b from-orange-900 to-orange-800 text-white hidden md:flex flex-col min-h-screen shadow-2xl shrink-0">
    <!-- Logo -->
    <div class="px-6 py-8 flex items-center gap-3 border-b border-white/10">
        <i class="ph ph-bus-fill text-3xl text-orange-300"></i>
        <div>
            <h1 class="text-2xl font-black tracking-tight">PARE</h1>
            <p class="text-xs text-orange-300 font-medium">Driver Portal</p>
        </div>
    </div>

    <!-- Driver Info -->
    <div class="px-6 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-orange-400/30 flex items-center justify-center">
                <i class="ph ph-steering-wheel text-orange-200 text-xl"></i>
            </div>
            <div>
                <p class="font-semibold text-sm leading-tight"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Driver') ?></p>
                <p class="text-xs text-orange-300">Bus: <?= htmlspecialchars($_SESSION['bus_body'] ?? '—') ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1">
        <?php
        $navItems = [
            ['href' => '/PARE/driver/dashboard.php',  'icon' => 'ph-squares-four',    'label' => 'Dashboard'],
            ['href' => '/PARE/driver/passengers.php', 'icon' => 'ph-users',           'label' => 'Passengers Today'],
            ['href' => '/PARE/driver/earnings.php',   'icon' => 'ph-coins',           'label' => 'My Earnings'],
        ];
        foreach ($navItems as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm
                  <?= $active ? 'bg-white/20 text-white' : 'text-orange-100 hover:bg-white/10 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-lg w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-white/10">
        <a href="/PARE/auth/logout.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-orange-200 hover:bg-red-500/20 hover:text-red-300 text-sm font-medium">
            <i class="ph ph-sign-out text-lg"></i>
            Logout
        </a>
    </div>
</aside>
