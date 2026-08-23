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
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// Handle activate/deactivate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user_id'])) {
    $toggleId = (int)$_POST['toggle_user_id'];
    $newState = (int)$_POST['new_state'];
    
    // Check user info before update to see if we should email
    $stmt = $pdo->prepare("SELECT email, full_name, is_active FROM users WHERE id = ?");
    $stmt->execute([$toggleId]);
    $user = $stmt->fetch();

    $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newState, $toggleId]);
    
    // Send email if account was activated and they have an email
    if ($user && $user['is_active'] == 0 && $newState == 1 && !empty($user['email'])) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'khianvivar@gmail.com';
            $mail->Password   = 'zqip kriq dnir obzp'; // Use the standard project password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($mail->Username, 'PARE System');
            $mail->addAddress($user['email'], $user['full_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Your PARE Account is Verified!';
            $mail->Body    = "
                <h3>Hello {$user['full_name']},</h3>
                <p>Great news! Your PARE account and discount application have been successfully verified by the admin.</p>
                <p>You can now log in and book your rides using your discount.</p>
                <br>
                <p>Thank you for using PARE System!</p>
            ";

            $mail->send();
        } catch (MailException $e) {
            error_log("PHPMailer Error (account verification): {$mail->ErrorInfo}");
        }
    }

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

// Fetch all passenger names for search suggestions
$allNamesStmt = $pdo->query("SELECT DISTINCT full_name FROM users WHERE role = 'passenger' ORDER BY full_name ASC");
$allPassengerNames = $allNamesStmt->fetchAll(PDO::FETCH_COLUMN);

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
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-6 flex items-center gap-3 px-5 py-3 focus-within:ring-2 focus-within:ring-blue-100 transition-all">
            <i class="ph ph-magnifying-glass text-slate-400 text-xl shrink-0"></i>
            <form method="GET" class="flex-1 flex gap-3">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" list="passenger-names" autocomplete="off"
                       placeholder="Search by name, ID number, or contact..."
                       class="flex-1 outline-none focus:outline-none border-none bg-transparent text-slate-700 placeholder-slate-300 text-sm px-2">
                
                <datalist id="passenger-names">
                    <?php foreach ($allPassengerNames as $name): ?>
                        <option value="<?= htmlspecialchars($name) ?>">
                    <?php endforeach; ?>
                </datalist>

                <button type="submit" class="bg-amber-600 text-white font-semibold px-4 py-1.5 rounded-xl text-sm hover:bg-amber-500 transition">Search</button>
                <?php if ($search): ?>
                <a href="passengers.php" class="text-slate-400 font-semibold px-3 py-1.5 rounded-xl text-sm hover:bg-slate-100 transition flex items-center justify-center">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Passengers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
            <?php foreach ($passengers as $p): ?>
            <div id="card-<?= $p['id'] ?>" class="bg-white rounded-3xl border border-slate-100 shadow-sm p-5 flex items-start gap-4 transition-opacity <?= $p['is_active'] ? '' : 'opacity-60 grayscale-[0.5]' ?>">
                <!-- Clickable Area -->
                <div class="flex-1 flex items-start gap-4 cursor-pointer hover:opacity-80 transition min-w-0" onclick="showPassengerModal(<?= $p['id'] ?>)">
                    <!-- ID Photo -->
                    <div class="w-16 h-16 rounded-2xl bg-amber-100 overflow-hidden shrink-0 shadow-inner">
                        <?php if ($p['id_picture']): ?>
                        <img src="<?= BASE_PATH ?>/<?= htmlspecialchars($p['id_picture']) ?>" alt="ID" class="w-full h-full object-cover">
                        <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center"><i class="ph ph-user text-amber-400 text-3xl"></i></div>
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
                    
                    <div class="flex flex-col gap-2">
                        <button id="toggle-btn-<?= $p['id'] ?>"
                                onclick="toggleStatus(<?= $p['id'] ?>, <?= $p['is_active'] ? 0 : 1 ?>, '<?= addslashes($p['full_name']) ?>')"
                                class="w-9 h-9 rounded-xl flex items-center justify-center transition-all shadow-sm
                                       <?= $p['is_active'] ? 'bg-slate-100 text-slate-400 hover:bg-red-50 hover:text-red-500' : 'bg-emerald-600 text-white hover:bg-emerald-500' ?>"
                                title="<?= $p['is_active'] ? 'Deactivate Account' : 'Activate Account' ?>">
                            <i id="icon-<?= $p['id'] ?>" class="ph <?= $p['is_active'] ? 'ph-power' : 'ph-check-circle' ?> text-lg font-bold"></i>
                        </button>

                        <button onclick="removeAccount(<?= $p['id'] ?>, 'passenger', '<?= addslashes($p['full_name']) ?>')"
                                class="w-9 h-9 rounded-xl bg-slate-100 text-slate-400 hover:bg-red-600 hover:text-white flex items-center justify-center transition-all"
                                title="Remove Permanently">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <script>
        async function removeAccount(id, type, name) {
            event.stopPropagation();
            const isConfirmed = await window.showConfirm({
                title: 'Remove Account Permanently?',
                message: `Are you sure you want to delete ${name}? This action cannot be undone and will only succeed if the account has no historical trip data.`,
                type: 'danger',
                confirmText: 'Yes, Remove Permanently'
            });

            if (!isConfirmed) return;

            try {
                const res = await fetch('api_delete_account.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, type })
                });
                const data = await res.json();

                if (data.success) {
                    window.showToast('Account Removed', `${name} has been deleted.`, 'success');
                    const card = document.getElementById(`card-${id}`);
                    card.style.transform = 'scale(0.9)';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                } else {
                    window.showToast('Action Denied', data.message, 'error');
                }
            } catch (err) {
                window.showToast('Network Error', 'Could not reach server.', 'error');
            }
        }
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
                photoEl.src = "<?= BASE_PATH ?>/" + data.photo;
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
                <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="px-4 py-2 rounded-xl bg-amber-600 text-white font-semibold text-sm hover:bg-amber-500">Next →</a>
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
    <div class="rounded-3xl shadow-[0_25px_50px_-12px_rgba(59,111,212,0.3)] w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh] border border-white/60" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 40%, #93c5fd 100%);" onclick="event.stopPropagation()">
        
        <!-- Header -->
        <div class="px-6 py-4 flex items-center justify-between border-b border-white/50 bg-white/40 backdrop-blur-md shrink-0">
            <h3 class="font-black text-blue-900 text-[17px] flex items-center gap-2">
                <i class="ph ph-identification-card text-blue-600 text-xl"></i> Passenger Details
            </h3>
            <button onclick="closePassengerModal()" class="w-8 h-8 rounded-full bg-white/50 hover:bg-rose-500 border border-transparent flex items-center justify-center text-blue-800 hover:text-white transition">
                <i class="ph ph-x font-bold"></i>
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 overflow-y-auto no-scrollbar">
            <!-- ID Photo Enlarge -->
            <div class="w-full h-48 bg-white/50 rounded-2xl mb-6 flex items-center justify-center p-2 border border-white/70 shadow-inner">
                <img id="modal-id-photo" src="" class="max-w-full max-h-full object-contain rounded drop-shadow-sm cursor-pointer hover:scale-105 transition-transform" alt="ID Photo" onclick="openLightbox(this.src)">
                <div id="modal-no-photo" class="hidden text-blue-800/50 flex flex-col items-center">
                    <i class="ph ph-user text-4xl mb-2"></i> No ID Photo
                </div>
            </div>

            <!-- Details Layout -->
            <div class="grid grid-cols-2 gap-4 gap-y-6">
                <!-- Data cells -->
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Full Name</p>
                    <p id="modal-name" class="font-black text-blue-900 tracking-tight"></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">ID Number</p>
                    <p id="modal-idnum" class="font-bold font-mono text-blue-900"></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Discount Type</p>
                    <p id="modal-discount" class="font-bold text-blue-700 bg-white/50 inline-block px-2.5 py-0.5 rounded-md border border-blue-200/50 text-sm shadow-sm"></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Contact</p>
                    <p id="modal-contact" class="font-bold text-blue-900 font-mono"></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Email</p>
                    <p id="modal-email" class="font-bold text-blue-900 break-all bg-white/60 border border-white/80 px-2 py-0.5 rounded-md inline-block max-w-full shadow-sm"></p>
                </div>
                <div>
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Joined Date</p>
                    <p id="modal-date" class="font-bold text-blue-900 text-sm"></p>
                </div>
                <div class="col-span-2 mt-1">
                    <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-widest mb-1">Complete Address</p>
                    <p id="modal-address" class="font-bold text-blue-900 bg-white/60 p-3.5 rounded-xl border border-white/80 leading-snug shadow-sm"></p>
                </div>
                <div class="col-span-2 mt-4 pt-5 border-t border-white/50">
                    <p class="text-[10px] text-rose-600 font-black uppercase tracking-widest mb-3 flex items-center gap-1.5"><i class="ph ph-first-aid text-sm"></i> Emergency Contact</p>
                    <div class="grid grid-cols-2 gap-4 bg-white/60 border border-white/80 p-4 rounded-xl shadow-sm">
                        <div>
                            <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-wide mb-1">Contact Person</p>
                            <p id="modal-ec-name" class="font-bold text-blue-900"></p>
                        </div>
                        <div>
                            <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-wide mb-1">Contact Number</p>
                            <p id="modal-ec-contact" class="font-bold text-blue-900 font-mono"></p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-[10px] text-blue-800/70 font-bold uppercase tracking-wide mb-1">Address</p>
                            <p id="modal-ec-addr" class="font-bold text-blue-900 text-sm"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
    </div>
</div>

<!-- Lightbox Modal -->
<div id="image-lightbox" class="fixed inset-0 z-[200] bg-black/90 hidden flex items-center justify-center p-4 transition-opacity duration-200 opacity-0" onclick="closeLightbox()">
    <button class="absolute top-6 right-6 text-white/60 hover:text-white transition w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-rose-500 rounded-full" onclick="closeLightbox(event)">
        <i class="ph ph-x text-xl font-bold"></i>
    </button>
    <img id="lightbox-img" src="" class="max-w-full max-h-full object-contain rounded-xl shadow-2xl transition-transform duration-300 scale-95" alt="Enlarged ID" onclick="event.stopPropagation()">
</div>

<script>
    function openLightbox(src) {
        if (!src) return;
        const lightbox = document.getElementById('image-lightbox');
        const img = document.getElementById('lightbox-img');
        img.src = src;
        
        lightbox.classList.remove('hidden');
        // trigger animation
        setTimeout(() => {
            lightbox.classList.remove('opacity-0');
            img.classList.remove('scale-95');
            img.classList.add('scale-100');
        }, 10);
    }

    function closeLightbox(e) {
        if (e) e.stopPropagation();
        const lightbox = document.getElementById('image-lightbox');
        const img = document.getElementById('lightbox-img');
        
        lightbox.classList.add('opacity-0');
        img.classList.remove('scale-100');
        img.classList.add('scale-95');
        
        setTimeout(() => {
            lightbox.classList.add('hidden');
            img.src = '';
        }, 200);
    }
</script>

</body>
</html>
