<?php
$currentPage = $currentPage ?? '';
$navItems = [
    ['href' => BASE_PATH . '/passenger/dashboard.php',  'icon' => 'ph-squares-four',           'label' => 'Home'],
    ['href' => BASE_PATH . '/passenger/map.php',        'icon' => 'ph-map-trifold',            'label' => 'Map'],
    ['href' => BASE_PATH . '/passenger/rides.php',      'icon' => 'ph-clock-counter-clockwise', 'label' => 'Rides'],
    ['href' => BASE_PATH . '/passenger/profile.php',    'icon' => 'ph-user-circle',            'label' => 'Profile'],
    ['href' => BASE_PATH . '/auth/logout.php',          'icon' => 'ph-sign-out',               'label' => 'Logout'],
];
?>
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 px-2 pb-safe shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.2)]" style="background-color: #061A53;">
    <div class="flex items-center justify-around py-2">
        <?php foreach ($navItems as $item): 
            $isLogout = $item['label'] === 'Logout';
            $active = str_contains($currentPage, basename($item['href']));
            $color = $active ? 'text-white' : ($isLogout ? 'text-red-200 hover:text-red-100' : 'text-blue-100');
            $bg = $active ? 'bg-white/20' : 'hover:bg-white/10';
            $weight = $active ? 'text-white' : '';
        ?>
        <a href="<?= $item['href'] ?>" class="flex flex-col items-center justify-center w-14 h-14 rounded-2xl <?= $color ?> <?= $bg ?> transition active:scale-90">
            <i class="ph <?= $item['icon'] ?> text-[22px] <?= $weight ?> mb-1"></i>
            <span class="text-[9px] font-black tracking-wide uppercase leading-none"><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</nav>
