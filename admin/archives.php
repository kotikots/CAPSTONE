<?php
/**
 * admin/archives.php
 * View and restore archived users and drivers.
 */
$requiredRole = 'admin';
$pageTitle    = 'Archives';
$currentPage  = 'archives.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Fetch archived passengers
$stmtP = $pdo->prepare("SELECT * FROM users WHERE role = 'passenger' AND is_archived = 1 ORDER BY created_at DESC");
$stmtP->execute();
$archivedPassengers = $stmtP->fetchAll();

// Fetch archived drivers
$stmtD = $pdo->prepare("SELECT * FROM drivers WHERE is_archived = 1 ORDER BY created_at DESC");
$stmtD->execute();
$archivedDrivers = $stmtD->fetchAll();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800">Archives</h1>
            <p class="text-slate-500">View and restore archived accounts.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <!-- Archived Passengers -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="ph ph-users text-blue-500"></i> Archived Passengers
                </h2>
                <?php if (count($archivedPassengers) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($archivedPassengers as $p): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <div>
                                    <p class="font-bold text-slate-800"><?= htmlspecialchars($p['full_name']) ?></p>
                                    <p class="text-xs text-slate-500 truncate max-w-[200px]"><?= htmlspecialchars($p['email']) ?></p>
                                </div>
                                <button onclick="restoreAccount('passenger', <?= $p['id'] ?>)" class="text-emerald-600 hover:text-emerald-700 font-semibold text-sm bg-emerald-50 px-3 py-1.5 rounded-lg transition-colors">
                                    Restore
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-400 text-sm italic">No archived passengers.</p>
                <?php endif; ?>
            </div>

            <!-- Archived Drivers -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6">
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="ph ph-steering-wheel text-orange-500"></i> Archived Drivers
                </h2>
                <?php if (count($archivedDrivers) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($archivedDrivers as $d): ?>
                            <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                                <div>
                                    <p class="font-bold text-slate-800"><?= htmlspecialchars($d['full_name']) ?></p>
                                    <p class="text-xs text-slate-500">License: <?= htmlspecialchars($d['license_number']) ?></p>
                                </div>
                                <button onclick="restoreAccount('driver', <?= $d['id'] ?>)" class="text-emerald-600 hover:text-emerald-700 font-semibold text-sm bg-emerald-50 px-3 py-1.5 rounded-lg transition-colors">
                                    Restore
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-400 text-sm italic">No archived drivers.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Restore Modal -->
<div id="restore-modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-sm overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="p-6">
            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                <i class="ph ph-arrow-counter-clockwise text-2xl text-emerald-600"></i>
            </div>
            <h3 class="text-lg font-black text-slate-800 mb-2">Restore Account?</h3>
            <p class="text-slate-500 text-sm mb-6">This account will be reactivated and will reappear in the main system.</p>
            
            <div class="flex gap-3">
                <button onclick="closeRestoreModal()" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">Cancel</button>
                <button id="confirm-restore-btn" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors">Restore</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentRestoreType = null;
let currentRestoreId = null;

function restoreAccount(type, id) {
    currentRestoreType = type;
    currentRestoreId = id;
    const modal = document.getElementById('restore-modal');
    modal.classList.remove('hidden');
    // small delay for transition
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
    }, 10);
}

function closeRestoreModal() {
    const modal = document.getElementById('restore-modal');
    modal.classList.add('opacity-0');
    modal.querySelector('div').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

document.getElementById('confirm-restore-btn').addEventListener('click', async function() {
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner animate-spin"></i> Restoring...';

    try {
        const res = await fetch('api_restore_account.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: currentRestoreType, id: currentRestoreId })
        });
        const data = await res.json();
        
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.message || 'Failed to restore account.');
            btn.disabled = false;
            btn.innerHTML = originalText;
            closeRestoreModal();
        }
    } catch (e) {
        alert('Network error while restoring.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        closeRestoreModal();
    }
});
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
