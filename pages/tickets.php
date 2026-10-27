<?php
$filters = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? ''];
if (Auth::is('Employee')) $filters['created_by'] = Auth::id();
$tickets = Ticket::all($filters);
$isStaff = Auth::is('Admin', 'Technician');
?>
<div class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <h1 class="text-xl font-bold text-gray-800">Maintenance Tickets</h1>
    <div class="flex gap-2 text-sm">
        <?php if ($isStaff): ?>
        <a href="backend/export.php?type=tickets&format=csv" class="bg-white border px-3 py-1.5 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-file-csv mr-1"></i>CSV</a>
        <a href="backend/export.php?type=tickets&format=print" target="_blank" class="bg-white border px-3 py-1.5 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-file-pdf mr-1"></i>PDF</a>
        <?php endif; ?>
        <button type="button" onclick="openForm('ticketModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg"><i class="fa-solid fa-plus mr-1"></i>New Ticket</button>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl shadow p-3 mb-4 flex flex-wrap gap-2 text-sm">
    <input type="hidden" name="page" value="tickets">
    <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title or asset…" class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg">
    <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg"><option value="">All statuses</option>
        <?php foreach (TICKET_STATUS as $s): ?><option <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button class="bg-gray-800 text-white px-4 py-2 rounded-lg">Filter</button>
    <a href="?page=tickets" class="px-3 py-2 text-gray-500">Reset</a>
</form>

<div class="bg-white rounded-xl shadow overflow-x-auto">
<table class="w-full text-sm text-left">
    <thead class="text-gray-500 border-b bg-gray-50"><tr>
        <th class="p-3">ID</th><th>Ticket</th><th>Asset</th><th>Type</th><th>Priority</th><th>Status</th><th>Technician</th><th>Scheduled</th><th>Cost</th><th class="pr-3 text-right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$tickets): ?><tr><td colspan="10" class="p-6 text-center text-gray-400">No tickets found.</td></tr><?php endif; ?>
    <?php foreach ($tickets as $t): ?>
        <tr class="border-b last:border-0 hover:bg-gray-50">
            <td class="p-3 text-gray-400"><?= e(substr((string)$t['id'], 0, 8)) ?></td>
            <td><?= e($t['title']) ?><div class="text-xs text-gray-400">by <?= e($t['creator'] ?: '—') ?></div></td>
            <td><?= e($t['asset_tag']) ?><div class="text-xs text-gray-400"><?= e($t['asset_name']) ?></div></td>
            <td><?= e($t['type']) ?></td>
            <td><?= badge((string)$t['priority']) ?></td>
            <td><?= badge($t['status']) ?></td>
            <td><?= e($t['technician'] ?: '—') ?></td>
            <td><?= e($t['scheduled_date'] ?: '—') ?></td>
            <td><?= money($t['cost']) ?></td>
            <td class="pr-3 text-right whitespace-nowrap">
                <?php if ($isStaff): ?>
                <button type="button" title="Edit" class="text-indigo-600 hover:text-indigo-800" data-item="<?= jattr($t) ?>" onclick="openForm('ticketModal', JSON.parse(this.dataset.item))"><i class="fa-solid fa-pen"></i></button>
                <?php endif; if (Auth::is('Admin')) delete_btn('ticket_delete', (string)$t['id']); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
