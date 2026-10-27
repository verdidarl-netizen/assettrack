<?php Auth::need('Admin'); $rows = User::all(); ?>
<div class="flex justify-between items-center mb-4">
    <h1 class="text-xl font-bold text-gray-800">Users &amp; Employees</h1>
    <button type="button" onclick="openForm('userModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm"><i class="fa-solid fa-plus mr-1"></i>Add User</button>
</div>
<div class="bg-white rounded-xl shadow overflow-x-auto">
<table class="w-full text-sm text-left">
    <thead class="text-gray-500 border-b bg-gray-50"><tr><th class="p-3">Name</th><th>Email</th><th>Role</th><th>Department</th><th>Joined</th><th class="pr-3 text-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
        <tr class="border-b last:border-0 hover:bg-gray-50">
            <td class="p-3 font-medium"><?= e($u['full_name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700"><?= e($u['role']) ?></span></td>
            <td><?= e($u['department'] ?: '—') ?></td>
            <td class="text-gray-500"><?= e(substr($u['created_at'], 0, 10)) ?></td>
            <td class="pr-3 text-right whitespace-nowrap">
                <button type="button" title="Edit" class="text-indigo-600 hover:text-indigo-800" data-item="<?= jattr($u) ?>" onclick="openForm('userModal', JSON.parse(this.dataset.item))"><i class="fa-solid fa-pen"></i></button>
                <?php if ((int)$u['id'] !== Auth::id()) delete_btn('user_delete', (int)$u['id']); ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
