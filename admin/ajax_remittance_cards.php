<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    require_once '../config/db.php';
    require_once '../includes/functions.php';
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { exit; }
}

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

foreach ($drivers as $dr): 
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
