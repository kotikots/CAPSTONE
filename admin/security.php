<?php
/**
 * admin/security.php
 * Security monitoring dashboard.
 */
$requiredRole = 'admin';
$pageTitle    = 'Security Monitoring';
$currentPage  = 'security.php';

require_once '../config/db.php';
require_once '../includes/auth_guard.php';

// Pagination and Search Configuration
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

$searchCondition = "";
$params = [];
if (!empty($search)) {
    $searchCondition = "WHERE identifier LIKE ? OR ip_address LIKE ? OR reason LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
}

// Get Total Rows
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM security_logs $searchCondition");
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

// Fetch logs
$stmt = $pdo->prepare("SELECT * FROM security_logs $searchCondition ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Identify suspicious IPs (more than 5 failures in 24 hours)
$suspiciousStmt = $pdo->query("
    SELECT ip_address, COUNT(*) as fail_count, MAX(created_at) as last_attempt
    FROM security_logs 
    WHERE status = 'failure' AND created_at > (NOW() - INTERVAL 24 HOUR)
    GROUP BY ip_address 
    HAVING fail_count >= 5 
    ORDER BY fail_count DESC
");
$suspiciousIps = $suspiciousStmt->fetchAll();

include '../includes/header.php';
?>

<div class="flex min-h-screen">
    <?php include '../includes/sidebar_admin.php'; ?>

    <main class="flex-1 p-4 md:p-8 overflow-y-scroll overflow-x-hidden bg-slate-50 pb-24 md:pb-8">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl font-black text-slate-800 tracking-tight">Security Monitoring</h2>
                <p class="text-slate-500 text-sm mt-1">Audit logs and brute-force protection status</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 bg-emerald-100 text-emerald-700 text-xs font-bold px-4 py-2 rounded-full">
                    <i class="ph ph-shield-check"></i> System Secure
                </span>
            </div>
        </div>

        <!-- Suspicious Activity Alert (Hidden for now)
        <?php if (!empty($suspiciousIps)): ?>
        <div class="bg-red-50 border-2 border-red-100 rounded-3xl p-6 mb-8 flex items-start gap-5 shadow-sm">
            <div class="w-12 h-12 rounded-2xl bg-red-100 flex items-center justify-center shrink-0">
                <i class="ph ph-warning-bold text-2xl text-red-600"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-red-900 font-black text-lg">Suspicious Activity Detected</h3>
                <p class="text-red-700 text-sm mb-4">The following IP addresses have exceeded the login failure threshold and may be attempting a brute-force attack.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php foreach ($suspiciousIps as $ip): ?>
                    <div class="bg-white/60 border border-red-200 rounded-2xl px-4 py-3 flex justify-between items-center">
                        <div>
                            <p class="text-xs font-bold text-red-800"><?= $ip['ip_address'] ?></p>
                            <p class="text-[10px] text-red-600 font-medium"><?= $ip['fail_count'] ?> failures in 24h</p>
                        </div>
                        <span class="bg-red-600 text-white text-[10px] font-black px-2 py-1 rounded uppercase tracking-tighter">Blocked</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        -->

        <!-- Audit Log Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
                <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2 shrink-0">
                    <i class="ph ph-list-numbers text-blue-500"></i> Live Audit Feed
                </h3>
                
                <form method="GET" class="flex w-full md:w-max gap-2">
                    <div class="relative w-full md:w-64">
                        <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user, IP, or reason..." 
                               class="w-full bg-white border border-slate-200 rounded-xl pl-9 pr-10 py-2 text-sm focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all shadow-sm">
                        <?php if (!empty($search)): ?>
                        <a href="security.php" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-500 rounded-full transition-colors" title="Clear search">
                            <i class="ph ph-x text-[10px] font-black"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-md shadow-blue-600/20 transition-all active:scale-95 whitespace-nowrap shrink-0">
                        Search
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/30">
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">User / Identifier</th>
                            <th class="px-6 py-4">IP Address</th>
                            <th class="px-6 py-4">Date & Time</th>
                            <th class="px-6 py-4">Reason / Device</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($logs as $log): 
                            $isSuccess = $log['status'] === 'success';
                            $statusClass = $isSuccess ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700';
                            $statusIcon = $isSuccess ? 'ph-check-circle' : 'ph-x-circle';
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1.5 <?= $statusClass ?> text-[10px] font-black px-2.5 py-1 rounded-lg uppercase">
                                    <i class="ph <?= $statusIcon ?> text-sm"></i> <?= $log['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-800"><?= htmlspecialchars($log['identifier']) ?></p>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight"><?= $log['role_attempted'] ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-mono text-xs text-slate-500"><?= $log['ip_address'] ?></p>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500">
                                <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                <span class="font-bold opacity-60"><?= date('h:i A', strtotime($log['created_at'])) ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs font-medium text-slate-600 mb-1"><?= htmlspecialchars($log['reason']) ?></p>
                                <p class="text-[10px] text-slate-400 truncate w-48 italic" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= htmlspecialchars($log['user_agent']) ?></p>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="py-20 text-center text-slate-400">
                                <i class="ph ph-shield-check text-6xl mb-3 block opacity-20"></i>
                                <p class="font-bold">No security events logged yet.</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1 || !empty($search)): ?>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs font-medium text-slate-500">
                    Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalLogs) ?></span> to 
                    <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalLogs) ?></span> of 
                    <span class="font-bold text-slate-700"><?= $totalLogs ?></span> entries
                </p>
                
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm cursor-pointer">Prev</a>
                    <?php else: ?>
                    <button disabled class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold text-slate-400 opacity-50 cursor-not-allowed hidden md:block">Prev</button>
                    <?php endif; ?>

                    <div class="flex gap-1 overflow-x-auto max-w-[200px] md:max-w-none no-scrollbar">
                        <?php 
                        $startPg = max(1, $page - 2);
                        $endPg = min($totalPages, $startPg + 4);
                        if ($endPg - $startPg < 4) $startPg = max(1, $endPg - 4);
                        for ($i = $startPg; $i <= $endPg; $i++): 
                        ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                           class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-black transition-all shadow-sm shrink-0 cursor-pointer
                           <?= $i === $page ? 'bg-blue-600 text-white shadow-blue-600/30 ring-2 ring-blue-600/20' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 hover:border-blue-300' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm font-bold text-slate-600 hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm cursor-pointer">Next</a>
                    <?php else: ?>
                    <button disabled class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-sm font-bold text-slate-400 opacity-50 cursor-not-allowed hidden md:block">Next</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

</body></html>
