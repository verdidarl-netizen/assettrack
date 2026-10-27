<?php
$filters = ['q' => trim($_GET['q'] ?? ''), 'status' => $_GET['status'] ?? '', 'category' => $_GET['category'] ?? ''];
if (Auth::is('Employee')) $filters['user_id'] = Auth::id();
$assets = Asset::all($filters);
$isStaff = Auth::is('Admin', 'Technician');
?>
<div class="flex flex-wrap justify-between items-center gap-2 mb-4">
    <h1 class="text-xl font-bold text-gray-800"><?= Auth::is('Employee') ? 'My Assets' : 'Assets' ?></h1>
    <div class="flex gap-2 text-sm">
        <?php if ($isStaff): ?>
        <a href="backend/export.php?type=assets&format=csv" class="bg-white border px-3 py-1.5 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-file-csv mr-1"></i>CSV</a>
        <a href="backend/export.php?type=assets&format=print" target="_blank" class="bg-white border px-3 py-1.5 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-file-pdf mr-1"></i>PDF</a>
        <?php endif; ?>
        <?php if (Auth::is('Admin')): ?>
        <button type="button" onclick="openForm('assetModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg"><i class="fa-solid fa-plus mr-1"></i>Add Asset</button>
        <?php endif; ?>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl shadow p-3 mb-4 flex flex-wrap gap-2 text-sm">
    <input type="hidden" name="page" value="assets">
    <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search tag, name, serial, assigned user…" class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg">
    <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg"><option value="">All categories</option>
        <?php foreach ($categories as $v): ?><option value="<?= e($v) ?>" <?= $filters['category'] === $v ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg"><option value="">All statuses</option>
        <?php foreach (ASSET_STATUS as $s): ?><option <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button class="bg-gray-800 text-white px-4 py-2 rounded-lg">Filter</button>
    <a href="?page=assets" class="px-3 py-2 text-gray-500">Reset</a>
</form>

<div class="bg-white rounded-xl shadow overflow-x-auto">
<table class="w-full text-sm text-left">
    <thead class="text-gray-500 border-b bg-gray-50"><tr>
        <th class="p-3">Tag</th><th>Asset</th><th>Serial</th><th>Assigned To</th><th>Location</th><th>Status</th><th>Warranty</th><th>Book Value</th><th class="pr-3 text-right">Actions</th></tr></thead>
    <tbody>
    <?php if (!$assets): ?><tr><td colspan="9" class="p-6 text-center text-gray-400">No assets found.</td></tr><?php endif; ?>
    <?php foreach ($assets as $a):
        $soon = $a['warranty_expiry'] && $a['warranty_expiry'] >= date('Y-m-d') && $a['warranty_expiry'] <= date('Y-m-d', strtotime('+30 days'));
        $expired = $a['warranty_expiry'] && $a['warranty_expiry'] < date('Y-m-d'); ?>
        <tr class="border-b last:border-0 hover:bg-gray-50">
            <td class="p-3 font-medium"><?= e($a['asset_tag']) ?></td>
            <td><?= e($a['name']) ?><div class="text-xs text-gray-400"><?= e($a['category']) ?></div></td>
            <td class="text-gray-500"><?= e($a['serial_number']) ?></td>
            <td><?= e($a['assigned_to'] ?: '—') ?></td>
            <td><?= e($a['location'] ?: '—') ?></td>
            <td><?= badge($a['status']) ?></td>
            <td class="<?= $soon ? 'text-amber-600 font-medium' : ($expired ? 'text-red-600' : '') ?>"><?= e($a['warranty_expiry'] ?: '—') ?></td>
            <td title="Accumulated depreciation: <?= money($a['accum_dep']) ?>"><?= money($a['book_value']) ?></td>
            <td class="pr-3 text-right whitespace-nowrap">
                <button type="button" title="Report issue" class="text-amber-600 hover:text-amber-800" data-asset="<?= e($a['id']) ?>" onclick="openForm('ticketModal', {asset_id: this.dataset.asset})"><i class="fa-solid fa-triangle-exclamation"></i></button>
                <?php if (Auth::is('Admin')): ?>
                <button type="button" title="Edit" class="text-indigo-600 hover:text-indigo-800 ml-2" data-item="<?= jattr($a) ?>" onclick="openForm('assetModal', JSON.parse(this.dataset.item))"><i class="fa-solid fa-pen"></i></button>
                <?php delete_btn('asset_delete', (string)$a['id']); endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="text-xs text-gray-400 mt-2">Book value = straight-line depreciation from purchase date over useful life, down to salvage value.</p>
