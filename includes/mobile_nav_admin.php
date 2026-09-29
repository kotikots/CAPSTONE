<?php
$currentPage = $currentPage ?? '';
$navItems = [
    ['href' => BASE_PATH . '/dashboard',  'icon' => 'ph-chart-pie-slice',    'label' => 'Overview'],
    ['href' => BASE_PATH . '/passengers', 'icon' => 'ph-users',              'label' => 'Pax'],
    ['href' => BASE_PATH . '/drivers',    'icon' => 'ph-steering-wheel',     'label' => 'Drivers'],
    ['href' => BASE_PATH . '/buses',      'icon' => 'ph-bus',                'label' => 'Buses'],
    ['href' => BASE_PATH . '/trips',      'icon' => 'ph-map-pin-line',       'label' => 'Trips'],
    ['href' => BASE_PATH . '/fare_settings','icon' => 'ph-money',            'label' => 'Fares'],
    ['href' => BASE_PATH . '/remittance', 'icon' => 'ph-wallet',             'label' => 'Cash'],
    ['href' => BASE_PATH . '/reports',    'icon' => 'ph-file-text',          'label' => 'Reports'],
    ['href' => BASE_PATH . '/security',   'icon' => 'ph-shield-checkered',   'label' => 'Security'],
    ['href' => BASE_PATH . '/archives',   'icon' => 'ph-archive',            'label' => 'Archives'],
    ['href' => BASE_PATH . '/logout',      'icon' => 'ph-sign-out',           'label' => 'Logout'],
];
?>
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 px-1 pb-safe shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.2)]" style="background: linear-gradient(90deg, #3b6fd4 0%, #4d8af0 100%);">
    <div class="overflow-x-auto no-scrollbar w-full">
        <div class="flex items-center justify-between py-2 px-1 min-w-[max-content] gap-1">
        <?php foreach ($navItems as $item): 
            $isLogout = $item['label'] === 'Logout';
            $active = str_contains($currentPage, basename($item['href']));
            $color = $active ? 'text-white' : ($isLogout ? 'text-red-200 hover:text-red-100' : 'text-blue-100');
            $bg = $active ? 'bg-white/20' : 'hover:bg-white/10';
            $weight = $active ? 'text-white' : '';
        ?>
        <a href="<?= $item['href'] ?>" class="flex flex-col items-center justify-center w-12 sm:w-14 h-14 rounded-2xl <?= $color ?> <?= $bg ?> transition active:scale-90">
            <i class="ph <?= $item['icon'] ?> text-[20px] sm:text-[22px] <?= $weight ?> mb-1"></i>
            <span class="text-[8px] font-black tracking-wide uppercase leading-none"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
        </div>
    </div>
</nav>
