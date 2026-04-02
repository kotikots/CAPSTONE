<?php
$currentPage = $currentPage ?? '';
$navItems = [
    ['href' => '/PARE/admin/dashboard.php',  'icon' => 'ph-chart-pie-slice',    'label' => 'Overview'],
    ['href' => '/PARE/admin/buses.php',      'icon' => 'ph-bus',                'label' => 'Buses'],
    ['href' => '/PARE/admin/trips.php',      'icon' => 'ph-map-pin-line',       'label' => 'Trips'],
    ['href' => '/PARE/admin/passengers.php', 'icon' => 'ph-users',              'label' => 'Pax'],
    ['href' => '/PARE/admin/reports.php',    'icon' => 'ph-file-text',          'label' => 'Reports'],
];
?>
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-2xl border-t border-slate-200/50 z-50 px-1 pb-safe shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)]">
    <div class="flex items-center justify-between py-2 px-1">
        <?php foreach ($navItems as $item): 
            $active = str_contains($currentPage, basename($item['href']));
            $color = $active ? 'text-emerald-600' : 'text-slate-400';
            $bg = $active ? 'bg-emerald-50' : 'hover:bg-slate-50';
            $weight = $active ? 'text-emerald-600' : '';
        ?>
        <a href="<?= $item['href'] ?>" class="flex flex-col items-center justify-center w-14 h-14 rounded-2xl <?= $color ?> <?= $bg ?> transition active:scale-90">
            <i class="ph <?= $item['icon'] ?> text-2xl <?= $weight ?> mb-1"></i>
            <span class="text-[8px] font-black tracking-wide uppercase leading-none"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
