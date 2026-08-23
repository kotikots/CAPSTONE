<?php
/**
 * includes/sidebar_passenger.php
 * Left sidebar for authenticated passengers.
 */
$currentPage = $currentPage ?? '';

// Get discount type for badge display
$_sidebarDiscount = null;
$_sidebarAvatar = null;
if (isset($pdo) && isset($_SESSION['user_id'])) {
    $_sdStmt = $pdo->prepare("SELECT discount_type, id_picture FROM users WHERE id = ? LIMIT 1");
    $_sdStmt->execute([$_SESSION['user_id']]);
    $_u = $_sdStmt->fetch();
    $_sidebarDiscount = $_u['discount_type'] ?? 'none';
    $_sidebarAvatar = $_u['id_picture'] ?? null;
}
$_discountIcons = [
    'student' => '🎓', 'senior' => '❤️', 'pwd' => '♿', 
    'teacher' => '📚', 'nurse' => '🏥', 'none' => ''
];
$_discountIcon = $_discountIcons[$_sidebarDiscount] ?? '';
?>
<aside class="w-64 text-white hidden md:flex flex-col h-screen shadow-2xl shrink-0 sticky top-0" style="background-color: #061A53;">
    <!-- Logo -->
    <div class="px-6 py-5 flex items-center gap-3 border-b border-white/10">
        <img src="<?= BASE_PATH ?>/assets/img/logo_white.png?v=1" alt="PARE Logo" class="w-auto h-8 object-contain drop-shadow-md">
        <div>

            <p class="text-[10px] text-blue-200 font-medium uppercase tracking-wider">Passenger's Portal</p>
        </div>
    </div>

    <!-- User Info -->
    <div class="px-6 py-3 border-b border-white/10 bg-white/5">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center overflow-hidden shrink-0">
                <?php if ($_sidebarAvatar): ?>
                    <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($_sidebarAvatar) ?>" alt="Avatar" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="ph-fill ph-user text-blue-200 text-lg"></i>
                <?php endif; ?>
            </div>
            <div>
                <p class="font-bold text-xs truncate w-32 leading-tight"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Passenger') ?></p>
                <p class="text-[9px] text-blue-300 uppercase font-bold tracking-tighter">ID: <?= htmlspecialchars($_SESSION['id_number'] ?? '—') ?></p>
                <?php if ($_discountIcon): ?>
                <p class="text-[8px] text-blue-200 font-black uppercase tracking-tighter mt-0.5"><?= $_discountIcon ?> <?= $_sidebarDiscount ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto no-scrollbar">
        <?php
        $navItems = [
            ['href' => BASE_PATH . '/passenger/dashboard', 'icon' => 'ph-squares-four',           'label' => 'Dashboard'],
            ['href' => BASE_PATH . '/passenger/map',       'icon' => 'ph-map-trifold',            'label' => 'Live Map'],
            ['href' => BASE_PATH . '/passenger/rides',     'icon' => 'ph-clock-counter-clockwise', 'label' => 'My Rides'],
            ['href' => BASE_PATH . '/passenger/profile',   'icon' => 'ph-user-circle',            'label' => 'My Profile'],
        ];
        foreach ($navItems as $item):
            $active = str_contains($currentPage, basename($item['href']));
        ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl font-semibold text-xs
                  <?= $active ? 'bg-white/20 text-white shadow-inner' : 'text-blue-100 hover:bg-white/10 hover:text-white' ?>">
            <i class="ph <?= $item['icon'] ?> text-base w-5 text-center"></i>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Logout -->
    <div class="p-3 border-t border-white/10">
        <a href="<?= BASE_PATH ?>/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-blue-200 hover:bg-red-500/20 hover:text-red-300 text-xs font-bold transition-all">
            <i class="ph ph-sign-out text-base"></i>
            Logout
        </a>
    </div>
</aside>
