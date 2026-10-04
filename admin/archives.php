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

// Handle restore account POST (moved from api_restore_account.php to bypass InfinityFree firewall)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $restoreId   = (int)$_POST['restore_id'];
    $restoreType = $_POST['restore_type'] ?? '';

    if ($restoreId && in_array($restoreType, ['passenger', 'driver'])) {
        try {
            if ($restoreType === 'driver') {
                $stmt = $pdo->prepare("UPDATE drivers SET is_archived = 0, is_active = 1 WHERE id = ?");
                $stmt->execute([$restoreId]);
            } else {
                // Fetch passenger to clean up email and id_number
                $stmt = $pdo->prepare("SELECT email, id_number FROM users WHERE id = ? AND role = 'passenger'");
                $stmt->execute([$restoreId]);
                $user = $stmt->fetch();

                if ($user) {
                    $email = $user['email'];
                    $id_number = $user['id_number'];

                    if (preg_match('/^archived_\d+_(.*)$/', $email, $matches)) {
                        $email = $matches[1];
                    }
                    if (preg_match('/^archived_\d+_(.*)$/', $id_number, $matches)) {
                        $id_number = $matches[1];
                    }

                    $upd = $pdo->prepare("UPDATE users SET is_archived = 0, is_active = 1, email = ?, id_number = ? WHERE id = ? AND role = 'passenger'");
                    $upd->execute([$email, $id_number, $restoreId]);
                }
            }
            $_SESSION['flash_msg'] = 'Account restored successfully!';
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $_SESSION['flash_msg'] = 'Cannot restore: email or ID number is already in use by another active account.';
            } else {
                $_SESSION['flash_msg'] = 'Database error while restoring.';
            }
            $_SESSION['flash_type'] = 'error';
        }
    }

    header('Location: /archives');
    exit;
}

// Fetch archived passengers
$stmtP = $pdo->prepare("SELECT * FROM users WHERE role = 'passenger' AND is_archived = 1 ORDER BY created_at DESC");
$stmtP->execute();
$archivedPassengers = $stmtP->fetchAll();

// Fetch archived drivers
$stmtD = $pdo->prepare("SELECT * FROM drivers WHERE is_archived = 1 ORDER BY created_at DESC");
$stmtD->execute();
$archivedDrivers = $stmtD->fetchAll();

// Check for flash messages
$flashMsg  = $_SESSION['flash_msg'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800">Archives</h1>
            <p class="text-slate-500">View and restore archived accounts.</p>
        </div>

        <?php if ($flashMsg): ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            window.showToast(
                '<?= $flashType === "success" ? "Success" : "Error" ?>',
                <?= json_encode($flashMsg) ?>,
                '<?= $flashType ?>'
            );
        });
        </script>
        <?php endif; ?>

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

<script>
async function restoreAccount(type, id) {
    const isConfirmed = await window.showConfirm({
        title: 'Restore Account?',
        message: 'This account will be reactivated and will reappear in the main system.',
        type: 'info',
        confirmText: 'Yes, Restore'
    });

    if (!isConfirmed) return;

    // Use a hidden form POST (bypasses InfinityFree firewall)
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/archives';
    form.style.display = 'none';

    const inputId = document.createElement('input');
    inputId.name = 'restore_id';
    inputId.value = id;
    form.appendChild(inputId);

    const inputType = document.createElement('input');
    inputType.name = 'restore_type';
    inputType.value = type;
    form.appendChild(inputType);

    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>

