<?php
Auth::need('Admin', 'Technician');
$isAdmin = Auth::is('Admin');
$result  = $_SESSION['tx_result'] ?? null; unset($_SESSION['tx_result']);

$assets  = Asset::all();
$users   = Database::all('SELECT user_id, full_name, role FROM `user` ORDER BY user_id');
$tickets = Ticket::all();
$badEngine = Transaction::nonInnoDb();

$assetOpts  = [];
foreach ($assets as $a) $assetOpts[$a['id']] = $a['asset_tag'] . ' — ' . $a['name'] . ' (' . $a['status'] . ')';
$ticketOpts = [];
foreach ($tickets as $t) $ticketOpts[$t['id']] = $t['asset_tag'] . ' — ' . mb_substr((string)$t['title'], 0, 40) . ' [' . $t['status'] . ']';

$fail = '<label class="flex items-center gap-2 text-xs text-gray-500"><input type="checkbox" name="force_fail" value="1" class="rounded"> Force a failure at the end (shows the ROLLBACK)</label>';
?>
<div class="mb-4">
    <h1 class="text-xl font-bold text-gray-800">Transaction Demo <span class="text-sm font-normal text-gray-400">(TCL — BEGIN / COMMIT / ROLLBACK)</span></h1>
    <p class="text-sm text-gray-500">Each transaction changes several tables at once. If any step fails, every change is undone — nothing is half-saved.</p>
</div>

<?php if ($badEngine): ?>
<div class="mb-4 p-3 rounded-lg text-sm bg-yellow-50 text-yellow-800 border border-yellow-200">
    <b>Rollback will not work</b> on: <?= e(implode(', ', array_map(fn($r) => $r['t'] . ' (' . $r['e'] . ')', $badEngine))) ?>.
    In phpMyAdmin: table → Operations → Storage Engine → <b>InnoDB</b>.
</div>
<?php endif; ?>

<?php if ($result): $ok = $result['status'] === 'success'; ?>
<div class="mb-4 rounded-xl border <?= $ok ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> p-4">
    <div class="font-semibold <?= $ok ? 'text-green-800' : 'text-red-800' ?>">
        <i class="fa-solid <?= $ok ? 'fa-circle-check' : 'fa-rotate-left' ?> mr-1"></i><?= e($result['title']) ?> — <?= $ok ? 'COMMITTED' : 'ROLLED BACK' ?>
    </div>
    <div class="text-sm <?= $ok ? 'text-green-800' : 'text-red-800' ?> mb-2"><?= e($result['message']) ?></div>
    <ol class="list-decimal list-inside text-xs font-mono text-gray-600 space-y-0.5">
        <?php foreach ($result['steps'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?>
    </ol>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-4 mb-4">
    <!-- Transaction 1 -->
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="font-semibold text-gray-800 mb-1"><span class="text-indigo-600">1.</span> Assign Asset</h2>
        <p class="text-xs text-gray-500 mb-3">Updates the asset, writes the audit log and notifies the user. Fails if the asset is not Available.</p>
        <?php if ($isAdmin): ?>
        <form method="POST" class="space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="tx_assign"><input type="hidden" name="page" value="transactions">
            <?php select_field('Asset', 'asset_id', $assetOpts, false, 'required');
                  field('User ID', 'user_id', 'number', 'required min="1" placeholder="e.g. 2"'); ?>
            <?= $fail ?>
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Run Transaction</button>
        </form>
        <?php else: ?><p class="text-sm text-gray-400">Admin only.</p><?php endif; ?>
    </div>

    <!-- Transaction 2 -->
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="font-semibold text-gray-800 mb-1"><span class="text-indigo-600">2.</span> Transfer Asset</h2>
        <p class="text-xs text-gray-500 mb-3">Release from sender, check receiver exists, assign to receiver. Use a wrong receiver ID to see the rollback.</p>
        <?php if ($isAdmin): ?>
        <form method="POST" class="space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="tx_transfer"><input type="hidden" name="page" value="transactions">
            <?php select_field('Asset', 'asset_id', $assetOpts, false, 'required'); ?>
            <div class="grid grid-cols-2 gap-3">
                <?php field('From User ID', 'from_id', 'number', 'required min="1"');
                      field('To User ID', 'to_id', 'number', 'required min="1"'); ?>
            </div>
            <?= $fail ?>
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Run Transaction</button>
        </form>
        <?php else: ?><p class="text-sm text-gray-400">Admin only.</p><?php endif; ?>
    </div>

    <!-- Transaction 3 -->
    <div class="bg-white rounded-xl shadow p-4">
        <h2 class="font-semibold text-gray-800 mb-1"><span class="text-indigo-600">3.</span> Complete Ticket</h2>
        <p class="text-xs text-gray-500 mb-3">Resolves the ticket, records the repair cost, frees the asset from maintenance and notifies the reporter.</p>
        <form method="POST" class="space-y-3">
            <?= csrf_field() ?><input type="hidden" name="action" value="tx_ticket"><input type="hidden" name="page" value="transactions">
            <?php select_field('Ticket', 'ticket_id', $ticketOpts, false, 'required');
                  field('Repair Cost (₱)', 'cost', 'number', 'required min="0" step="0.01" value="0"'); ?>
            <?= $fail ?>
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Run Transaction</button>
        </form>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 bg-white rounded-xl shadow overflow-x-auto">
        <h3 class="p-3 font-semibold text-gray-800 border-b">Current Asset Holdings</h3>
        <table class="w-full text-sm text-left">
            <thead class="text-gray-500 border-b bg-gray-50"><tr><th class="p-3">Tag</th><th>Name</th><th>Status</th><th>Held by (User ID)</th></tr></thead>
            <tbody>
            <?php if (!$assets): ?><tr><td colspan="4" class="p-6 text-center text-gray-400">No assets yet.</td></tr><?php endif; ?>
            <?php foreach ($assets as $a): ?>
                <tr class="border-b last:border-0">
                    <td class="p-3"><?= e($a['asset_tag']) ?></td><td><?= e($a['name']) ?></td><td><?= badge($a['status']) ?></td>
                    <td><?= $a['assigned_user_id'] ? e($a['assigned_to']) . ' <span class="text-gray-400">(#' . e($a['assigned_user_id']) . ')</span>' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-xl shadow overflow-x-auto">
        <h3 class="p-3 font-semibold text-gray-800 border-b">User IDs</h3>
        <table class="w-full text-sm text-left">
            <thead class="text-gray-500 border-b bg-gray-50"><tr><th class="p-3">ID</th><th>Name</th><th>Role</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="border-b last:border-0"><td class="p-3"><?= e($u['user_id']) ?></td><td><?= e($u['full_name']) ?></td><td class="text-gray-500"><?= e($u['role']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto mt-4">
    <h3 class="p-3 font-semibold text-gray-800 border-b">Recent Transaction Log</h3>
    <table class="w-full text-sm text-left">
        <thead class="text-gray-500 border-b bg-gray-50"><tr><th class="p-3">When</th><th>User</th><th>Result</th><th>Details</th></tr></thead>
        <tbody>
        <?php $tl = Transaction::recentLog(10); if (!$tl): ?><tr><td colspan="4" class="p-6 text-center text-gray-400">No transactions run yet.</td></tr><?php endif; ?>
        <?php foreach ($tl as $l): ?>
            <tr class="border-b last:border-0">
                <td class="p-3 text-gray-500 whitespace-nowrap"><?= e($l['created_at']) ?></td><td><?= e($l['actor_name']) ?></td>
                <td><?= $l['action'] === 'tx_commit'
                    ? '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">COMMIT</span>'
                    : '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">ROLLBACK</span>' ?></td>
                <td class="text-gray-500"><?= e($l['details']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
