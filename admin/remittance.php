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

?>
<?php include '../includes/header.php'; ?>
<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <!-- Header -->
        <div class="mb-8">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Remittances</h2>
            <p class="text-slate-500 text-sm">Manage driver daily cash collections</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="remittance-cards-container">
            <?php include 'ajax_remittance_cards.php'; ?>
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
        
        <form id="admin-pass-form" method="POST" action="remittance.php" class="p-6 space-y-6 bg-white">
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

// Auto-refresh remittance cards every 3 seconds
setInterval(() => {
    fetch('ajax_remittance_cards.php')
        .then(response => response.text())
        .then(html => {
            const container = document.getElementById('remittance-cards-container');
            if (container) container.innerHTML = html;
        })
        .catch(err => console.error('Error fetching remittance cards:', err));
}, 3000);
</script>

<?php include '../includes/mobile_nav_admin.php'; ?>
</body>
</html>
