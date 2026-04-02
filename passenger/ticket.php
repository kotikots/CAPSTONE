<?php
/**
 * passenger/ticket.php   — STEP 8
 * View and print a ticket. Includes QR code.
 * Usage: ticket.php?code=TKT-YYYYMMDD-NNNNN  OR shows latest ticket.
 */
$requiredRole = 'passenger';
$pageTitle    = 'My Ticket';
$currentPage  = 'ticket.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$uid  = $_SESSION['user_id'];
$code = trim($_GET['code'] ?? '');

if ($code) {
    $stmt = $pdo->prepare(
        "SELECT t.*, d.full_name AS driver_name, b.body_number, b.plate_number
         FROM   tickets t
         JOIN   trips   tr ON tr.id = t.trip_id
         JOIN   buses   b  ON b.id  = tr.bus_id
         JOIN   drivers d  ON d.id  = tr.driver_id
         WHERE  t.ticket_code = ? AND t.passenger_id = ? LIMIT 1"
    );
    $stmt->execute([$code, $uid]);
} else {
    $stmt = $pdo->prepare(
        "SELECT t.*, d.full_name AS driver_name, b.body_number, b.plate_number
         FROM   tickets t
         JOIN   trips   tr ON tr.id = t.trip_id
         JOIN   buses   b  ON b.id  = tr.bus_id
         JOIN   drivers d  ON d.id  = tr.driver_id
         WHERE  t.passenger_id = ? ORDER BY t.issued_at DESC LIMIT 1"
    );
    $stmt->execute([$uid]);
}
$ticket = $stmt->fetch();

include '../includes/header.php';
?>

<!-- QR Code library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_passenger.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-auto bg-slate-50 pb-24 md:pb-8">
        <div class="max-w-lg mx-auto">

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <a href="history.php" class="flex items-center gap-1 text-slate-400 hover:text-slate-600 text-sm mb-2 w-fit">
                        <i class="ph ph-arrow-left"></i> Back to History
                    </a>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Your Ticket</h2>
                </div>
                <?php if ($ticket): ?>
                <button onclick="window.print()"
                        class="flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-white font-bold w-full md:w-auto px-5 py-4 md:py-3 rounded-2xl transition active:scale-95">
                    <i class="ph ph-printer text-xl"></i> Print Ticket
                </button>
                <?php endif; ?>
            </div>

            <?php if (!$ticket): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 p-12 text-center">
                <i class="ph ph-ticket text-6xl text-slate-200 mb-4"></i>
                <h3 class="text-xl font-black text-slate-400 mb-2">No Ticket Found</h3>
                <p class="text-slate-400 text-sm mb-6">Book a ride first to generate a ticket.</p>
                <a href="booking.php" class="bg-blue-600 text-white font-bold px-8 py-3 rounded-2xl hover:bg-blue-500 transition">Book a Ride</a>
            </div>

            <?php else: ?>
            <!-- Screen ticket -->
            <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
                <!-- Header stripe -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-6 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="ph ph-bus-fill text-3xl text-blue-200"></i>
                            <div>
                                <p class="text-2xl font-black tracking-tight">PARE</p>
                                <p class="text-blue-200 text-xs">Passenger & Revenue Engine</p>
                            </div>
                        </div>
                        <span class="bg-white/20 px-4 py-1.5 rounded-full text-sm font-bold">
                            <?= htmlspecialchars($ticket['passenger_type']) ?>
                        </span>
                    </div>
                </div>

                <!-- Dashed divider -->
                <div class="flex items-center px-6 my-0">
                    <div class="w-6 h-6 bg-slate-100 rounded-full -ml-9 shrink-0"></div>
                    <div class="flex-1 border-t-2 border-dashed border-slate-200 mx-2"></div>
                    <div class="w-6 h-6 bg-slate-100 rounded-full -mr-9 shrink-0"></div>
                </div>

                <!-- Ticket Body -->
                <div class="px-8 py-6 space-y-5">

                    <!-- Route -->
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">FROM</p>
                            <p class="font-black text-lg text-slate-800"><?= htmlspecialchars($ticket['origin_name']) ?></p>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <div class="w-px h-6 bg-slate-200"></div>
                            <i class="ph ph-arrow-down text-blue-500"></i>
                            <div class="w-px h-6 bg-slate-200"></div>
                            <div class="w-2 h-2 rounded-full bg-red-500"></div>
                        </div>
                        <div class="flex-1 text-right">
                            <p class="text-xs text-slate-400 font-medium uppercase tracking-wider">TO</p>
                            <p class="font-black text-lg text-slate-800"><?= htmlspecialchars($ticket['dest_name']) ?></p>
                        </div>
                    </div>

                    <!-- Details grid -->
                    <div class="grid grid-cols-2 gap-4 pt-4 border-t border-dashed border-slate-200">
                        <?php foreach ([
                            ['Ticket ID',   $ticket['ticket_code']],
                            ['Passenger',   $ticket['passenger_name']],
                            ['Driver',      $ticket['driver_name']],
                            ['Bus',         $ticket['body_number'] . ' · ' . $ticket['plate_number']],
                            ['Distance',    $ticket['distance_km'] . ' km'],
                            ['Issued',      date('M d, Y · h:i A', strtotime($ticket['issued_at']))],
                        ] as [$label, $value]): ?>
                        <div>
                            <p class="text-xs text-slate-400 font-medium"><?= $label ?></p>
                            <p class="font-semibold text-slate-700 text-sm"><?= htmlspecialchars($value) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Fare -->
                    <div class="bg-blue-50 rounded-2xl px-6 py-4 flex items-center justify-between">
                        <p class="text-blue-700 font-bold">Total Fare</p>
                        <p class="text-3xl font-black text-blue-700"><?= peso((float)$ticket['fare_amount']) ?></p>
                    </div>

                    <!-- QR Code -->
                    <div class="flex flex-col items-center pt-2">
                        <div id="qrcode" class="mb-2"></div>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($ticket['ticket_code']) ?></p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-slate-50 border-t border-slate-100 px-8 py-4 text-center">
                    <p class="text-slate-400 text-xs">Please keep this ticket as proof of fare payment.</p>
                    <p class="text-slate-400 text-xs"><?= htmlspecialchars($ticket['status'] === 'validated' ? '✅ Ticket has been validated' : '⏳ Present this ticket to the driver') ?></p>
                </div>
            </div>

            <!-- Printable ticket (only visible on print) -->
            <div id="print-ticket" style="display:none">
                <div style="width:76mm; font-family:'Courier New',monospace; font-size:11px; padding:8px;">
                    <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:8px; margin-bottom:8px;">
                        <strong style="font-size:16px;">PARE SYSTEM</strong><br>
                        <span style="font-size:9px;">Passenger &amp; Revenue Engine</span>
                    </div>
                    <p><b>TICKET:</b> <?= htmlspecialchars($ticket['ticket_code']) ?></p>
                    <p><b>TYPE:</b> <?= htmlspecialchars($ticket['passenger_type']) ?></p>
                    <p><b>PASSENGER:</b> <?= htmlspecialchars($ticket['passenger_name']) ?></p>
                    <p><b>DRIVER:</b> <?= htmlspecialchars($ticket['driver_name']) ?></p>
                    <p><b>BUS:</b> <?= htmlspecialchars($ticket['body_number']) ?></p>
                    <div style="border-top:1px dashed #000; margin:6px 0; padding-top:6px;">
                        <p><b>FROM:</b> <?= htmlspecialchars($ticket['origin_name']) ?></p>
                        <p><b>TO:</b> <?= htmlspecialchars($ticket['dest_name']) ?></p>
                        <p><b>DISTANCE:</b> <?= $ticket['distance_km'] ?> km</p>
                    </div>
                    <div style="border-top:1px dashed #000; margin:6px 0; padding-top:6px;">
                        <p style="font-size:15px; font-weight:bold;">FARE: ₱<?= number_format((float)$ticket['fare_amount'],2) ?></p>
                    </div>
                    <div style="border-top:1px dashed #000; margin-top:6px; padding-top:6px; font-size:9px; text-align:center;">
                        <p><?= date('Y-m-d H:i', strtotime($ticket['issued_at'])) ?></p>
                        <p>Keep this ticket. Thank you!</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php if ($ticket): ?>
<script>
    new QRCode(document.getElementById('qrcode'), {
        text: '<?= addslashes($ticket['ticket_code']) ?>',
        width:  160,
        height: 160,
        colorDark:  '#1d4ed8',
        colorLight: '#ffffff'
    });
</script>
<?php endif; ?>

<?php include '../includes/mobile_nav_passenger.php'; ?>
</body>
</html>
