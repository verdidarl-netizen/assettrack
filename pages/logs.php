<?php Auth::need('Admin'); $logs = Log::recent(200); ?>
<h1 class="text-xl font-bold text-gray-800 mb-4">Activity Logs</h1>
<div class="bg-white rounded-xl shadow overflow-x-auto">
<table class="w-full text-sm text-left">
    <thead class="text-gray-500 border-b bg-gray-50"><tr><th class="p-3">When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?><tr><td colspan="5" class="p-6 text-center text-gray-400">No activity yet.</td></tr><?php endif; ?>
    <?php foreach ($logs as $l): ?>
        <tr class="border-b last:border-0">
            <td class="p-3 text-gray-500 whitespace-nowrap"><?= e($l['created_at']) ?></td>
            <td><?= e($l['actor_name']) ?></td>
            <td><?= e($l['action']) ?></td>
            <td><?= e($l['entity']) ?><?= $l['entity_id'] ? ' #' . e(substr((string)$l['entity_id'], 0, 8)) : '' ?></td>
            <td class="text-gray-500"><?= e($l['details']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
