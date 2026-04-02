<?php
/**
 * admin/passengers.php — View all registered passengers with ID photo.
 */
$requiredRole = 'admin';
$pageTitle    = 'Passengers';
$currentPage  = 'passengers.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;

$where = "WHERE role = 'passenger'";
$params = [];
if ($search) {
    $where  .= " AND (full_name LIKE ? OR id_number LIKE ? OR contact_number LIKE ?)";
    $params  = ["%$search%", "%$search%", "%$search%"];
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM users $where")->execute($params) ?
         $pdo->prepare("SELECT COUNT(*) FROM users $where")->execute($params) : 0;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$passengers = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Passengers</h2>
                <p class="text-slate-500 text-sm"><?= number_format($total) ?> registered passengers</p>
            </div>
        </div>

        <!-- Search -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-6 flex items-center gap-3 px-5 py-3">
            <i class="ph ph-magnifying-glass text-slate-400 text-xl shrink-0"></i>
            <form method="GET" class="flex-1 flex gap-3">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                       placeholder="Search by name, ID number, or contact..."
                       class="flex-1 outline-none text-slate-700 placeholder-slate-300 text-sm">
                <button type="submit" class="bg-blue-600 text-white font-semibold px-4 py-1.5 rounded-xl text-sm hover:bg-blue-500 transition">Search</button>
                <?php if ($search): ?>
                <a href="passengers.php" class="text-slate-400 font-semibold px-3 py-1.5 rounded-xl text-sm hover:bg-slate-100 transition">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Passengers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
            <?php foreach ($passengers as $p): ?>
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 flex items-start gap-4">
                <!-- ID Photo -->
                <div class="w-16 h-16 rounded-2xl bg-blue-100 overflow-hidden shrink-0">
                    <?php if ($p['id_picture']): ?>
                    <img src="/PARE/<?= htmlspecialchars($p['id_picture']) ?>" alt="ID" class="w-full h-full object-cover">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center"><i class="ph ph-user text-blue-400 text-3xl"></i></div>
                    <?php endif; ?>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="font-black text-slate-800 truncate"><?= htmlspecialchars($p['full_name']) ?></p>
                    <p class="text-slate-400 text-xs mb-2"><?= htmlspecialchars($p['id_number']) ?></p>
                    <div class="space-y-1 text-xs">
                        <p class="flex items-center gap-1 text-slate-500">
                            <i class="ph ph-phone"></i> <?= htmlspecialchars($p['contact_number']) ?>
                        </p>
                        <p class="flex items-start gap-1 text-slate-500">
                            <i class="ph ph-map-pin shrink-0 mt-0.5"></i>
                            <span class="line-clamp-1"><?= htmlspecialchars($p['address']) ?></span>
                        </p>
                        <p class="flex items-center gap-1 text-slate-500">
                            <i class="ph ph-calendar"></i> Joined <?= date('M d, Y', strtotime($p['created_at'])) ?>
                        </p>
                    </div>
                </div>

                <span class="<?= $p['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' ?> text-xs font-bold px-2 py-1 rounded-lg shrink-0">
                    <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex items-center justify-between">
            <p class="text-sm text-slate-400">Page <?= $page ?> of <?= $pages ?></p>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-100">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold text-sm hover:bg-blue-500">Next →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
