<?php
$currentPage = $currentPage ?? '';
$navItems = [
    ['href' => '/PARE/driver/dashboard.php',  'icon' => 'ph-steering-wheel',   'label' => 'Drive'],
    ['href' => '/PARE/driver/earnings.php',   'icon' => 'ph-wallet',           'label' => 'Earnings'],
    ['href' => '/PARE/driver/passengers.php', 'icon' => 'ph-users',            'label' => 'Lists'],
];
?>
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-2xl border-t border-slate-200/50 z-50 px-2 pb-safe shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)]">
    <div class="flex items-center justify-around py-2">
        <?php foreach ($navItems as $item): 
            $active = str_contains($currentPage, basename($item['href']));
            $color = $active ? 'text-amber-600' : 'text-slate-400';
            $bg = $active ? 'bg-amber-50' : 'hover:bg-slate-50';
            $weight = $active ? 'text-amber-600' : '';
        ?>
        <a href="<?= $item['href'] ?>" class="flex flex-col items-center justify-center w-20 h-14 rounded-2xl <?= $color ?> <?= $bg ?> transition active:scale-90">
            <i class="ph <?= $item['icon'] ?> text-2xl <?= $weight ?> mb-1"></i>
            <span class="text-[9px] font-black tracking-wide uppercase leading-none"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
