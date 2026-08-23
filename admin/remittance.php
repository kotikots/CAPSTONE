<?php
/**
 * admin/remittance.php
 * View and process driver remittances (Cash payments not yet remitted).
 */
session_start();
$requiredRole = 'admin';
$pageTitle    = 'Remittances';
$currentPage  = 'remittance.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

// Handle Remit Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remit') {
    if (isset($_POST['driver_id']) && isset($_POST['admin_password'])) {
        $driverId = (int)$_POST['driver_id'];
        $adminPassword = $_POST['admin_password'];

        // Verify admin password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($adminPassword, $admin['password'])) {
            $stmt = $pdo->prepare("
                UPDATE payments p
                JOIN tickets t ON p.ticket_id = t.id
                JOIN trips tr ON t.trip_id = tr.id
                SET p.remitted = 1, p.remitted_at = NOW()
                WHERE tr.driver_id = ? AND p.payment_method = 'cash' AND p.remitted = 2
            ");
            $stmt->execute([$driverId]);

            $_SESSION['toast'] = "Remittance logged successfully for the driver.";
        } else {
            $_SESSION['error'] = "Incorrect admin password. Remittance cancelled.";
        }
    }
    header("Location: remittance.php");
    exit;
}

// Fetch all drivers with their pending statuses
$stmt = $pdo->query("
    SELECT d.id, d.full_name, d.contact_number,
           COUNT(CASE WHEN p.remitted IN (0, 2) THEN p.id END) AS pending_tickets,
           COALESCE(SUM(CASE WHEN p.remitted IN (0, 2) THEN p.amount_paid END), 0) AS pending_amount,
           COALESCE(SUM(CASE WHEN p.remitted = 2 THEN p.amount_paid END), 0) AS driver_claimed_amount,
           COUNT(CASE WHEN p.remitted = 2 THEN p.id END) AS driver_claimed_tickets
    FROM drivers d
    LEFT JOIN trips tr ON tr.driver_id = d.id
    LEFT JOIN tickets t ON t.trip_id = tr.id
    LEFT JOIN payments p ON p.ticket_id = t.id AND p.payment_method = 'cash' AND p.remitted IN (0, 2)
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY driver_claimed_amount DESC, pending_amount DESC, d.full_name ASC
");
$drivers = $stmt->fetchAll();

include '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Remittances</h2>
            <p class="text-slate-500 text-sm">Manage driver daily cash collections</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php foreach ($drivers as $dr): 
                $hasPending = (float)$dr['pending_amount'] > 0;
                $hasDriverClaimed = (float)$dr['driver_claimed_amount'] > 0;
            ?>
            <div class="bg-white rounded-3xl p-6 shadow-sm border <?php if ($hasDriverClaimed || $hasPending): ?>border-orange-400 ring-4 ring-orange-50<?php else: ?>border-emerald-200 ring-4 ring-emerald-50<?php endif; ?> flex flex-col relative transition-all">
                
                <?php if ($hasDriverClaimed): ?>
                    <span class="absolute top-4 right-4 bg-orange-100 text-orange-700 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 bg-orange-500 rounded-full animate-pulse"></span> Driver Submitted
                    </span>
                <?php elseif ($hasPending): ?>
                    <span class="absolute top-4 right-4 bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                        <i class="ph ph-hourglass-low"></i> Waiting for Driver
                    </span>
                <?php endif; ?>

                <div class="flex items-center gap-4 mb-6 mt-4">
                    <div class="w-12 h-12 <?php if ($hasDriverClaimed || $hasPending): ?>bg-orange-100<?php else: ?>bg-emerald-100<?php endif; ?> rounded-2xl flex items-center justify-center shrink-0">
                        <i class="ph ph-steering-wheel text-2xl <?php if ($hasDriverClaimed || $hasPending): ?>text-orange-500<?php else: ?>text-emerald-500<?php endif; ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 text-lg truncate pr-16"><?= htmlspecialchars($dr['full_name']) ?></h3>
                        <p class="text-slate-400 text-xs"><?= htmlspecialchars($dr['contact_number']) ?></p>
                    </div>
                </div>

                <div class="<?php 
                    if ($hasDriverClaimed) echo 'bg-orange-50 border-orange-100';
                    elseif ($hasPending) echo 'bg-slate-50 border-slate-100';
                    else echo 'bg-emerald-50 border-emerald-100';
                ?> rounded-2xl p-5 mb-4 border shadow-inner">
                    <p class="text-xs font-bold <?php 
                        if ($hasDriverClaimed) echo 'text-orange-500';
                        elseif ($hasPending) echo 'text-slate-400';
                        else echo 'text-emerald-600';
                    ?> uppercase tracking-wider mb-1">Unremitted Cash</p>
                    <p class="font-black text-4xl <?php 
                        if ($hasDriverClaimed) echo 'text-orange-600';
                        elseif ($hasPending) echo 'text-slate-600';
                        else echo 'text-emerald-600';
                    ?>">
                        <?= peso((float)$dr['pending_amount']) ?>
                    </p>
                    <p class="<?php 
                        if ($hasDriverClaimed) echo 'text-orange-600/70';
                        elseif ($hasPending) echo 'text-slate-400';
                        else echo 'text-emerald-600/70';
                    ?> text-xs mt-2 font-bold"><?= $dr['pending_tickets'] ?> pending tickets</p>
                </div>

                <?php if ($hasDriverClaimed): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                        <i class="ph ph-hand-coins text-orange-600"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-black text-orange-700 uppercase tracking-wider">Driver claims remitted</p>
                        <p class="text-sm font-black text-orange-800"><?= peso((float)$dr['driver_claimed_amount']) ?> <span class="font-medium text-orange-600 text-xs">(<?= $dr['driver_claimed_tickets'] ?> tickets)</span></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-auto">
                    <?php if ($hasDriverClaimed): ?>
                        <button onclick="handleRemit(<?= $dr['id'] ?>, '<?= addslashes($dr['full_name']) ?>', '<?= peso((float)$dr['driver_claimed_amount']) ?>')" 
                                class="w-full flex justify-center items-center gap-2 bg-orange-600 text-white font-bold py-3.5 rounded-xl hover:bg-orange-500 hover:shadow-lg hover:shadow-orange-500/30 transition active:scale-95 text-sm uppercase tracking-wider">
                            <i class="ph ph-check-fat text-lg"></i> Confirm Remittance
                        </button>
                    <?php elseif ($hasPending): ?>
                        <button disabled 
                                class="w-full flex justify-center items-center gap-2 bg-slate-100 border border-slate-200 text-slate-400 font-bold py-3.5 rounded-xl cursor-not-allowed text-sm uppercase tracking-wider">
                            <i class="ph ph-hourglass text-lg"></i> Waiting for Driver
                        </button>
                    <?php else: ?>
                        <button disabled class="w-full bg-emerald-50 border border-emerald-200 text-emerald-600 font-bold py-3.5 rounded-xl flex justify-center items-center gap-2 cursor-not-allowed text-sm uppercase tracking-wider">
                            <i class="ph ph-check-circle text-lg"></i> Up to Date
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<!-- Toast Script -->
<?php if(isset($_SESSION['toast'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.showToast('Success', '<?= htmlspecialchars($_SESSION['toast']) ?>', 'success');
    });
</script>
<?php unset($_SESSION['toast']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.showToast('Authentication Failed', '<?= htmlspecialchars($_SESSION['error']) ?>', 'error');
    });
</script>
<?php unset($_SESSION['error']); endif; ?>

<!-- Admin Password Modal -->
<div id="admin-pass-modal" class="fixed inset-0 z-[9998] bg-[#061A53]/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="p-6 border-b border-[#E2E8F0] flex flex-col items-center justify-center bg-gradient-to-br from-[#F1F5F9] via-[#FFFFFF] to-[#F1F5F9]">
            <div class="w-16 h-16 bg-blue-100 rounded-full mb-4 flex items-center justify-center">
                <i class="ph ph-lock-key text-3xl text-blue-600"></i>
            </div>
            <h3 class="font-black text-slate-800 tracking-tight text-xl">Admin Approval</h3>
        </div>
        
        <form id="admin-pass-form" method="POST" action="remittance.php" class="p-6 space-y-6 bg-slate-100">
            <input type="hidden" name="action" value="remit">
            <input type="hidden" name="driver_id" id="remit-driver-id">
            
            <div class="text-center">
                <p class="text-slate-500 font-medium text-sm mb-4">Please enter your admin password to confirm the remittance of <span id="remit-amount-display" class="font-bold text-slate-800"></span> from <span id="remit-driver-name" class="font-bold text-slate-800"></span>.</p>
                <input type="password" name="admin_password" id="admin-pass-input" required placeholder="Enter Admin Password" class="w-full text-center bg-white border-2 border-slate-200 px-4 py-3 rounded-xl focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all font-medium text-slate-800">
            </div>
            
            <div class="pt-2 flex gap-3">
                <button type="button" onclick="closePassModal()" class="flex-1 py-3 px-4 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" class="flex-[2] py-3 px-4 bg-blue-600 hover:bg-blue-500 text-white font-black rounded-xl shadow-lg shadow-blue-500/20 transition active:scale-95 flex items-center justify-center gap-2">
                    <i class="ph ph-check-circle"></i> Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleRemit(driverId, name, amount) {
    document.getElementById('remit-driver-id').value = driverId;
    document.getElementById('remit-amount-display').textContent = amount;
    document.getElementById('remit-driver-name').textContent = name;
    document.getElementById('admin-pass-input').value = '';
    
    document.getElementById('admin-pass-modal').classList.remove('hidden');
    // Focus the input slightly after modal appears
    setTimeout(() => document.getElementById('admin-pass-input').focus(), 100);
}

function closePassModal() {
    document.getElementById('admin-pass-modal').classList.add('hidden');
}

// Close on background click
document.getElementById('admin-pass-modal').addEventListener('click', function(e) {
    if (e.target === this) closePassModal();
});
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
