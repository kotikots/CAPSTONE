<?php
$currentPage = $currentPage ?? '';
$navItems = [
    ['href' => BASE_PATH . '/driver/dashboard_v2.php',  'icon' => 'ph-steering-wheel',   'label' => 'Drive'],
    ['href' => BASE_PATH . '/driver/earnings.php',      'icon' => 'ph-wallet',           'label' => 'Earnings'],
    ['href' => BASE_PATH . '/driver/passengers.php',    'icon' => 'ph-users',            'label' => 'Lists'],
    ['href' => BASE_PATH . '/driver/profile.php',       'icon' => 'ph-user-gear',        'label' => 'Profile'],
    ['href' => BASE_PATH . '/auth/logout.php',          'icon' => 'ph-sign-out',         'label' => 'Logout'],
];
?>
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 px-2 pb-safe shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.2)]" style="background: linear-gradient(90deg, #3b6fd4 0%, #4d8af0 100%);">
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
