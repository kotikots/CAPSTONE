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

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggleId = (int)$_POST['toggle_user_id'];
    $newState = (int)$_POST['new_state'];
    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newState, $toggleId]);
    header('Location: passengers.php' . ($search ? '?q='.urlencode($search) : ''));
    exit;
}

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
            <div id="card-<?= $p['id'] ?>" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 flex items-start gap-4 transition-opacity <?= $p['is_active'] ? '' : 'opacity-60 grayscale-[0.5]' ?>">
                <!-- Clickable Area -->
                <div class="flex-1 flex items-start gap-4 cursor-pointer hover:opacity-80 transition" onclick="showPassengerModal(<?= $p['id'] ?>)">
                    <!-- ID Photo -->
                    <div class="w-16 h-16 rounded-2xl bg-blue-100 overflow-hidden shrink-0 shadow-inner">
                        <?php if ($p['id_picture']): ?>
                        <img src="/PARE/<?= htmlspecialchars($p['id_picture']) ?>" alt="ID" class="w-full h-full object-cover">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center"><i class="ph ph-user text-blue-400 text-3xl"></i></div>
                        <?php endif; ?>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-black text-slate-800 truncate"><?= htmlspecialchars($p['full_name']) ?></p>
                        <p class="text-slate-400 text-xs mb-2 font-mono"><?= htmlspecialchars($p['id_number']) ?></p>
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
                </div>


                <div class="flex flex-col items-end gap-2 shrink-0">
                    <span id="badge-<?= $p['id'] ?>" class="<?= $p['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?> text-[10px] uppercase font-bold px-2 py-1 rounded-lg">
                        <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                    
                    <button id="toggle-btn-<?= $p['id'] ?>"
                            onclick="toggleStatus(<?= $p['id'] ?>, <?= $p['is_active'] ? 0 : 1 ?>, '<?= addslashes($p['full_name']) ?>')"
                            class="w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-sm
                                   <?= $p['is_active'] ? 'bg-slate-100 text-slate-400 hover:bg-red-50 hover:text-red-500' : 'bg-emerald-600 text-white hover:bg-emerald-500' ?>"
                            title="<?= $p['is_active'] ? 'Deactivate Account' : 'Activate Account' ?>">
                        <i id="icon-<?= $p['id'] ?>" class="ph <?= $p['is_active'] ? 'ph-power' : 'ph-check-circle' ?> text-lg font-bold"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <script>
        // Store passenger data securely in JS
        const passengersData = {
            <?php foreach ($passengers as $p): ?>
            "<?= $p['id'] ?>": {
                name: <?= json_encode($p['full_name']) ?>,
                idnum: <?= json_encode($p['id_number']) ?>,
                discount: <?= json_encode($p['discount_type'] ?? 'Regular') ?>,
                contact: <?= json_encode($p['contact_number']) ?>,
                email: <?= json_encode($p['email'] ?? 'N/A') ?>,
                address: <?= json_encode($p['address']) ?>,
                ec_name: <?= json_encode($p['emergency_contact_name'] ?? 'N/A') ?>,
                ec_contact: <?= json_encode($p['emergency_contact_number'] ?? 'N/A') ?>,
                ec_addr: <?= json_encode($p['emergency_contact_address'] ?? 'N/A') ?>,
                date: "<?= date('F j, Y, g:i a', strtotime($p['created_at'])) ?>",
                photo: <?= json_encode($p['id_picture']) ?>
            },
            <?php endforeach; ?>
        };

        function showPassengerModal(id) {
            const data = passengersData[id];
            if (!data) return;

            document.getElementById('modal-name').textContent = data.name;
            document.getElementById('modal-idnum').textContent = data.idnum;
            document.getElementById('modal-discount').textContent = data.discount || 'Regular';
            document.getElementById('modal-contact').textContent = data.contact;
            document.getElementById('modal-email').textContent = data.email || 'N/A';
            document.getElementById('modal-address').textContent = data.address;
            document.getElementById('modal-ec-name').textContent = data.ec_name || 'N/A';
            document.getElementById('modal-ec-contact').textContent = data.ec_contact || 'N/A';
            document.getElementById('modal-ec-addr').textContent = data.ec_addr || 'N/A';
            document.getElementById('modal-date').textContent = data.date;

            const photoEl = document.getElementById('modal-id-photo');
            const noPhotoEl = document.getElementById('modal-no-photo');
            
            if (data.photo) {
                photoEl.src = "/PARE/" + data.photo;
                photoEl.classList.remove('hidden');
                noPhotoEl.classList.add('hidden');
            } else {
                photoEl.classList.add('hidden');
                noPhotoEl.classList.remove('hidden');
            }

            const modal = document.getElementById('passenger-modal');
            modal.classList.remove('hidden');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
            }, 10);
        }

        function closePassengerModal() {
            const modal = document.getElementById('passenger-modal');
            modal.classList.add('opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        async function toggleStatus(userId, newState, name) {
            event.stopPropagation(); // prevent opening the modal when clicking the activate button
            const verb = newState ? 'Activate' : 'Deactivate';
            
            const confirmed = await window.showConfirm({
                title: `${verb} Account?`,
                message: `Are you sure you want to ${verb.toLowerCase()} the account for ${name}?`,
                type: newState ? 'info' : 'danger',
                confirmText: `Yes, ${verb}`
            });

            if (!confirmed) return;

            try {
                const res = await fetch('api_toggle_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'passenger', id: userId, state: newState })
                });
                
                const data = await res.json();
                
                if (data.success) {
                    window.showToast(
                        `Account ${verb}d`, 
                        `${name} has been ${verb.toLowerCase()}d successfully.`,
                        newState ? 'success' : 'info'
                    );

                    // Update UI state dynamically
                    const card = document.getElementById(`card-${userId}`);
                    const badge = document.getElementById(`badge-${userId}`);
                    const btn = document.getElementById(`toggle-btn-${userId}`);
                    const icon = document.getElementById(`icon-${userId}`);
                    
                    if (newState) {
                        card.classList.remove('opacity-60', 'grayscale-[0.5]');
                        badge.className = 'bg-emerald-100 text-emerald-700 text-[10px] uppercase font-bold px-2 py-1 rounded-lg';
                        badge.textContent = 'Active';
                        btn.className = 'w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-sm bg-slate-100 text-slate-400 hover:bg-red-50 hover:text-red-500';
                        icon.className = 'ph ph-power text-lg font-bold';
                        btn.onclick = (e) => { e.stopPropagation(); toggleStatus(userId, 0, name); };
                    } else {
                        card.classList.add('opacity-60', 'grayscale-[0.5]');
                        badge.className = 'bg-red-100 text-red-600 text-[10px] uppercase font-bold px-2 py-1 rounded-lg';
                        badge.textContent = 'Inactive';
                        btn.className = 'w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-sm bg-emerald-600 text-white hover:bg-emerald-500';
                        icon.className = 'ph ph-check-circle text-lg font-bold';
                        btn.onclick = (e) => { e.stopPropagation(); toggleStatus(userId, 1, name); };
                    }
                } else {
                    window.showToast('Error', data.message, 'error');
                }
            } catch (err) {
                window.showToast('Network Error', 'Could not reach the server.', 'error');
            }
        }
        </script>

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

<!-- Passenger Details Modal with Blurred Background -->
<div id="passenger-modal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4 transition-opacity duration-200 opacity-0" onclick="closePassengerModal()">
    <!-- Stop propagation so clicking inside the modal doesn't close it -->
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        
        <!-- Header -->
        <div class="px-6 py-4 flex items-center justify-between border-b border-slate-100 bg-slate-50 shrink-0">
            <h3 class="font-black text-slate-800 text-[17px] flex items-center gap-2">
                <i class="ph ph-identification-card text-blue-600 text-xl"></i> Passenger Details
            </h3>
            <button onclick="closePassengerModal()" class="w-8 h-8 rounded-full bg-slate-200 hover:bg-rose-100 border border-transparent hover:border-rose-200 flex items-center justify-center text-slate-500 hover:text-rose-600 transition">
                <i class="ph ph-x font-bold"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 overflow-y-auto no-scrollbar">
            <!-- ID Photo Enlarge -->
            <div class="w-full h-48 bg-slate-100 rounded-2xl mb-6 flex items-center justify-center p-2 border border-slate-200 shadow-inner">
                <img id="modal-id-photo" src="" class="max-w-full max-h-full object-contain rounded drop-shadow-sm" alt="ID Photo">
                <div id="modal-no-photo" class="hidden text-slate-400 flex flex-col items-center">
                    <i class="ph ph-user text-4xl mb-2"></i> No ID Photo
                </div>
            </div>

            <!-- Details Layout -->
            <div class="grid grid-cols-2 gap-4 gap-y-6">
                <!-- Data cells -->
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Full Name</p>
                    <p id="modal-name" class="font-black text-slate-800 tracking-tight"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">ID Number</p>
                    <p id="modal-idnum" class="font-bold font-mono text-slate-800"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Discount Type</p>
                    <p id="modal-discount" class="font-bold text-blue-700 bg-blue-50 inline-block px-2.5 py-0.5 rounded-md border border-blue-100 text-sm"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Contact</p>
                    <p id="modal-contact" class="font-medium text-slate-600 font-mono"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Email</p>
                    <p id="modal-email" class="font-medium text-slate-600 truncate bg-slate-50 border-slate-100 px-2 py-0.5 rounded-md inline-block max-w-full"></p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Joined Date</p>
                    <p id="modal-date" class="font-medium text-slate-600 text-sm"></p>
                </div>
                <div class="col-span-2 mt-1">
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Complete Address</p>
                    <p id="modal-address" class="font-medium text-slate-800 bg-slate-50 p-3.5 rounded-xl border border-slate-200/60 leading-snug"></p>
                </div>
                <div class="col-span-2 mt-4 pt-5 border-t border-slate-100">
                    <p class="text-[10px] text-rose-500 font-black uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="ph ph-first-aid text-sm"></i> Emergency Contact</p>
                    <div class="grid grid-cols-2 gap-4 bg-rose-50 border border-rose-100 p-4 rounded-xl">
                        <div>
                            <p class="text-[10px] text-rose-600/70 font-bold uppercase tracking-wide mb-1">Contact Person</p>
                            <p id="modal-ec-name" class="font-bold text-rose-900"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-rose-600/70 font-bold uppercase tracking-wide mb-1">Contact Number</p>
                            <p id="modal-ec-contact" class="font-bold text-rose-900 font-mono"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] text-rose-600/70 font-bold uppercase tracking-wide mb-1">Address</p>
                            <p id="modal-ec-addr" class="font-medium text-rose-800 text-sm"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

</body>
</html>
