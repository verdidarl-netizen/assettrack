<?php
$scope = Auth::is('Employee') ? Auth::id() : null;
$as = Asset::stats($scope);
$ts = Ticket::stats($scope);
$notes = Notification::forUser(Auth::id(), 8);
$recent = array_slice(Ticket::all($scope ? ['created_by' => $scope] : []), 0, 5);
$cards = [
    ['Total Assets', $as['total'], 'fa-boxes-stacked', 'indigo'],
    ['Available', $as['available'], 'fa-circle-check', 'green'],
    ['Assigned', $as['assigned'], 'fa-user-check', 'blue'],
    ['In Maintenance', $as['maintenance'], 'fa-screwdriver-wrench', 'yellow'],
    ['Pending Tasks', $ts['open'], 'fa-list-check', 'blue'],
    ['Warranty ≤ 30 days', $as['warranty_soon'], 'fa-shield-halved', 'red'],
];
if (!$scope) $cards[] = ['Maintenance Cost', money($ts['cost']), 'fa-peso-sign', 'gray'];
?>
<h1 class="text-xl font-bold text-gray-800 mb-4">Dashboard</h1>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-3 mb-6">
<?php foreach ($cards as [$label, $val, $icon, $c]): ?>
    <div class="bg-white rounded-xl shadow p-4">
        <div class="text-<?= $c ?>-600 text-lg"><i class="fa-solid <?= $icon ?>"></i></div>
        <div class="text-2xl font-bold text-gray-800 mt-1"><?= e($val) ?></div>
        <div class="text-xs text-gray-500"><?= e($label) ?></div>
    </div>
<?php endforeach; ?>
</div>

<div class="grid lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl shadow p-5">
        <div class="flex justify-between items-center mb-3">
            <h2 class="font-semibold text-gray-800"><i class="fa-solid fa-bell text-indigo-600 mr-1"></i> Notifications</h2>
            <?php if ($unread): ?>
            <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="notif_read"><input type="hidden" name="page" value="dashboard">
                <button class="text-xs text-indigo-600 hover:underline">Mark all read</button></form>
            <?php endif; ?>
        </div>
        <?php if (!$notes): ?><p class="text-sm text-gray-400">No notifications.</p><?php endif; ?>
        <ul class="divide-y text-sm">
        <?php foreach ($notes as $n): ?>
            <li class="py-2 <?= $n['is_read'] ? 'text-gray-500' : 'text-gray-800 font-medium' ?>">
                <?= e($n['message']) ?> <span class="text-xs text-gray-400 block"><?= e($n['created_at']) ?></span></li>
        <?php endforeach; ?>
        </ul>
    </div>
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-800 mb-3"><i class="fa-solid fa-ticket text-indigo-600 mr-1"></i> Recent Tickets</h2>
        <?php if (!$recent): ?><p class="text-sm text-gray-400">No tickets yet.</p><?php endif; ?>
        <ul class="divide-y text-sm">
        <?php foreach ($recent as $t): ?>
            <li class="py-2 flex justify-between gap-2"><span><?= e($t['title']) ?> <span class="text-gray-400">· <?= e($t['asset_tag']) ?></span></span><?= badge($t['status']) ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
