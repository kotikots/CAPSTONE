<?php
/**
 * includes/sidebar_passenger.php
 * Left sidebar for authenticated passengers.
 */
$currentPage = $currentPage ?? '';
?>
<aside class="w-64 bg-gradient-to-b from-brand-900 to-brand-700 text-white hidden md:flex flex-col min-h-screen shadow-2xl shrink-0">
    <!-- Logo -->
    <div class="px-6 py-8 flex items-center gap-3 border-b border-white/10">
        <i class="ph ph-bus-fill text-3xl text-blue-300"></i>
        <div>
            <h1 class="text-2xl font-black tracking-tight">PARE</h1>
            <p class="text-xs text-blue-200 font-medium">Passenger Portal</p>
        </div>
    </div>

    <!-- User Info -->
    <div class="px-6 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-blue-400/30 flex items-center justify-center">
                <i class="ph ph-user-fill text-blue-200 text-xl"></i>
            </div>
            <div>
                <p class="font-semibold text-sm leading-tight"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Passenger') ?></p>
                <p class="text-xs text-blue-300">ID: <?= htmlspecialchars($_SESSION['id_number'] ?? '—') ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1">
        <?php
        $navItems = [
            ['href' => '/PARE/passenger/dashboard.php',  'icon' => 'ph-squares-four',      'label' => 'Dashboard'],
            ['href' => '/PARE/passenger/booking.php',    'icon' => 'ph-map-pin',            'label' => 'Book a Ride'],
            ['href' => '/PARE/passenger/ticket.php',     'icon' => 'ph-ticket',             'label' => 'My Ticket'],
            ['href' => '/PARE/passenger/history.php',    'icon' => 'ph-clock-counter-clockwise', 'label' => 'Ride History'],
            ['href' => '/PARE/passenger/map.php',        'icon' => 'ph-map-trifold',        'label' => 'Live Map'],
        ];
        foreach ($navItems as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-sm
                  <?= $active ? 'bg-white/20 text-white' : 'text-blue-100 hover:bg-white/10 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-lg w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-white/10">
        <a href="/PARE/auth/logout.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl text-blue-200 hover:bg-red-500/20 hover:text-red-300 text-sm font-medium">
            <i class="ph ph-sign-out text-lg"></i>
            Logout
        </a>
    </div>
</aside>
