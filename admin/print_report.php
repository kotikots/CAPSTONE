<?php
/**
 * admin/print_report.php — Generates a printable report (for PDF) with header and footer.
 */
$requiredRole = 'admin';
require_once '../config/db.php';
require_once '../includes/auth_guard.php';
require_once '../includes/functions.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Revenue per driver
$driverRevStmt = $pdo->prepare(
    "SELECT d.full_name, b.body_number, b.plate_number,
            COUNT(t.id) AS tickets,
            COALESCE(SUM(t.fare_amount),0) AS revenue,
            COALESCE(SUM(CASE WHEN t.passenger_type='Discounted' THEN 1 ELSE 0 END),0) AS discounted,
            COALESCE(SUM(CASE WHEN t.passenger_type='Regular'    THEN 1 ELSE 0 END),0) AS regular,
            COALESCE(SUM(CASE WHEN t.passenger_type='Non-Regular' THEN 1 ELSE 0 END),0) AS nonregular
     FROM   drivers d
     JOIN   buses   b  ON b.driver_id = d.id
     LEFT JOIN trips   tr ON tr.bus_id = b.id
     LEFT JOIN tickets t  ON t.trip_id  = tr.id AND DATE(t.issued_at) BETWEEN ? AND ?
     GROUP  BY d.id ORDER BY revenue DESC"
);
$driverRevStmt->execute([$from, $to]);
$driverRevs = $driverRevStmt->fetchAll();

// Daily breakdown
$dailyStmt = $pdo->prepare(
    "SELECT DATE(t.issued_at) AS day,
            COUNT(t.id) AS tickets,
            SUM(t.fare_amount) AS revenue
     FROM   tickets t
     WHERE  DATE(t.issued_at) BETWEEN ? AND ?
     GROUP  BY DATE(t.issued_at) ORDER BY day ASC"
);
$dailyStmt->execute([$from, $to]);
$daily = $dailyStmt->fetchAll();

$totalRev = array_sum(array_column($daily, 'revenue'));
$totalTix = array_sum(array_column($daily, 'tickets'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            @page { margin: 15mm; }
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 12px; text-align: left; }
        th { background-color: #f8fafc; font-weight: bold; color: #334155; }
        .stamp-box { border: 2px dashed #94a3b8; padding: 20px; width: 250px; text-align: center; color: #64748b; font-weight: bold; transform: rotate(-5deg); margin-left: auto; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans p-8 print:p-0">

    <div class="max-w-4xl mx-auto bg-white p-10 print:p-0 shadow-lg print:shadow-none min-h-[1056px] relative">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b-2 border-slate-800 pb-6 mb-8">
            <div class="flex items-center gap-4">
                <img src="<?= BASE_PATH ?>/assets/img/logo.png" alt="PARE Logo" class="h-16 w-16 object-contain">
                <div>
                    <h1 class="text-2xl font-black uppercase tracking-widest text-slate-900">PARE System</h1>
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-xl font-bold text-slate-800">Financial Report</h2>
                <p class="text-sm text-slate-500">Date: <?= date('F j, Y', strtotime($from)) ?> to <?= date('F j, Y', strtotime($to)) ?></p>
                <p class="text-xs text-slate-400 mt-1">Generated: <?= date('M d, Y h:i A') ?></p>
            </div>
        </div>

        <!-- Summary -->
        <div class="flex gap-8 mb-8 bg-slate-50 p-6 rounded-xl border border-slate-200 print:border-none print:bg-transparent print:p-0">
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase">Total Revenue</p>
                <p class="text-3xl font-black text-emerald-600"><?= peso((float)$totalRev) ?></p>
            </div>
            <div>
                <p class="text-sm font-bold text-slate-500 uppercase">Total Tickets</p>
                <p class="text-3xl font-black text-blue-600"><?= number_format($totalTix) ?></p>
            </div>
        </div>

        <!-- Driver Performance -->
        <h3 class="text-lg font-black text-slate-800 mb-4 border-l-4 border-amber-500 pl-3">Driver Performance</h3>
        <table>
            <thead>
                <tr>
                    <th>Driver Name</th>
                    <th>Bus Assigned</th>
                    <th class="text-right">Tickets Issued</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($driverRevs as $d): ?>
                <tr>
                    <td class="font-bold"><?= htmlspecialchars($d['full_name']) ?></td>
                    <td><?= htmlspecialchars($d['body_number']) ?> (<?= htmlspecialchars($d['plate_number']) ?>)</td>
                    <td class="text-right"><?= number_format($d['tickets']) ?></td>
                    <td class="text-right font-bold text-emerald-700"><?= peso((float)$d['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($driverRevs)): ?>
                <tr>
                    <td colspan="4" class="text-center text-slate-500 italic py-4">No records found for this period.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Daily Breakdown -->
        <h3 class="text-lg font-black text-slate-800 mb-4 border-l-4 border-blue-500 pl-3">Daily Breakdown</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-right">Tickets Issued</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($daily as $day): ?>
                <tr>
                    <td><?= date('F j, Y', strtotime($day['day'])) ?></td>
                    <td class="text-right"><?= number_format($day['tickets']) ?></td>
                    <td class="text-right font-bold text-emerald-700"><?= peso((float)$day['revenue']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($daily)): ?>
                <tr>
                    <td colspan="3" class="text-center text-slate-500 italic py-4">No records found for this period.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer Stamp -->
        <div class="mt-16 pt-8 border-t border-slate-200">
            <div class="flex justify-between items-end">
                <div>
                    <p class="text-sm font-bold text-slate-800 mb-8">Prepared by:</p>
                    <div class="border-b border-slate-800 w-48 mb-2"></div>
                    <p class="text-xs font-bold text-slate-500 uppercase">System Administrator</p>
                </div>
                <div>
                    <div class="stamp-box">
                        <p class="uppercase text-lg tracking-widest border-b-2 border-dashed border-slate-400 pb-2 mb-2">Verified</p>
                        <p class="text-sm">RPMVFMPC</p>
                        <p class="text-xs mt-1"><?= date('Y-m-d') ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Automatically open print dialog when page loads
        window.onload = () => {
            window.print();
        };
    </script>
</body>
</html>
