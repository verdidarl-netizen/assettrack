<?php
/**
 * AssetTrack Pro — front controller.
 * Run:  copy to C:\xampp\htdocs\assettrack and open http://localhost/assettrack/   (PHP 8.1+ with pdo_mysql; XAMPP has both)
 */
require __DIR__ . '/config.php';
require __DIR__ . '/backend/actions.php';          // handles every POST, then redirects

$user = Auth::user();
$menu = ['dashboard' => ['Dashboard', 'fa-gauge'], 'assets' => ['Assets', 'fa-laptop'], 'tickets' => ['Tickets', 'fa-screwdriver-wrench']];
if (Auth::is('Admin', 'Technician')) $menu['transactions'] = ['Transactions', 'fa-right-left'];
if (Auth::is('Admin')) $menu += ['users' => ['Users', 'fa-users'], 'logs' => ['Activity Log', 'fa-clock-rotate-left']];

$page = $_GET['page'] ?? 'dashboard';
if (!isset($menu[$page])) $page = 'dashboard';

$flash = $_SESSION['flash'] ?? null;
$openModal = $_SESSION['open_modal'] ?? '';
unset($_SESSION['flash'], $_SESSION['open_modal']);

$unread = 0;
if ($user) {
    Notification::generate();
    $unread = Notification::unread(Auth::id());
    // dropdown data for the modals
    $categories  = Database::lookup('asset_categories');
    $departments = Database::lookup('departments');
    $userOptions = User::options();
    $techOptions = User::options('Technician');
    $assetOptions = [];
    foreach (Asset::all(Auth::is('Employee') ? ['user_id' => Auth::id()] : []) as $a) $assetOptions[$a['id']] = $a['asset_tag'] . ' — ' . $a['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AssetTrack Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<nav class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap justify-between items-center gap-2">
        <div class="flex items-center gap-5">
            <a href="index.php" class="font-bold text-indigo-600"><i class="fa-solid fa-screwdriver-wrench mr-2"></i>AssetTrack Pro</a>
            <?php if ($user): foreach ($menu as $k => [$label, $icon]): ?>
                <a href="?page=<?= $k ?>" class="text-sm <?= $page === $k ? 'text-indigo-600 font-semibold' : 'text-gray-600 hover:text-gray-900' ?>"><i class="fa-solid <?= $icon ?> mr-1"></i><?= $label ?></a>
            <?php endforeach; endif; ?>
        </div>
        <?php if ($user): ?>
            <div class="flex items-center gap-3 text-sm">
                <a href="?page=dashboard" class="relative text-gray-500 hover:text-gray-800" title="Notifications"><i class="fa-solid fa-bell"></i>
                    <?php if ($unread): ?><span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] rounded-full px-1.5"><?= $unread ?></span><?php endif; ?></a>
                <button type="button" data-item="<?= jattr(User::find(Auth::id()) ?? []) ?>" onclick="openForm('profileModal', JSON.parse(this.dataset.item))" class="text-gray-700 hover:text-indigo-600">
                    <?= e($user['name']) ?> <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full"><?= e($user['role']) ?></span></button>
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="logout">
                    <button class="bg-gray-200 hover:bg-gray-300 px-3 py-1.5 rounded-lg">Logout</button></form>
            </div>
        <?php else: ?>
            <div class="space-x-2">
                <button type="button" onclick="openModal('loginModal')" class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-lg">Login</button>
                <button type="button" onclick="openModal('registerModal')" class="text-sm border border-indigo-600 text-indigo-600 hover:bg-indigo-50 px-4 py-1.5 rounded-lg">Sign Up</button>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="max-w-7xl mx-auto p-4">
    <?php if ($flash): ?>
        <div class="mb-4 p-3 rounded-lg text-sm <?= $flash[0] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <?php if (!$user): ?>
        <div class="bg-white rounded-xl shadow p-10 text-center mt-10">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">AssetTrack Pro</h1>
            <p class="text-gray-500">Equipment &amp; Maintenance Management System. Please log in or create an account.</p>
        </div>
    <?php else: include __DIR__ . "/pages/$page.php"; endif; ?>
</main>

<?php
include __DIR__ . '/modals/login_modal.php';
include __DIR__ . '/modals/register_modal.php';
if ($user) {
    include __DIR__ . '/modals/profile_modal.php';
    include __DIR__ . '/modals/ticket_modal.php';
    if (Auth::is('Admin')) { include __DIR__ . '/modals/asset_modal.php'; include __DIR__ . '/modals/user_modal.php'; }
}
?>

<script>
function openModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
function closeModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
function switchModal(from, to) { closeModal(from); openModal(to); }
function fillCredentials(email, password) {
    document.getElementById('loginEmail').value = email;
    document.getElementById('loginPassword').value = password;
}
/* Open a form modal; pass a row object to pre-fill it for editing (matches inputs by name). */
function openForm(id, data) {
    const m = document.getElementById(id), f = m.querySelector('form');
    f.reset();
    for (const [k, v] of Object.entries(data || {})) {
        const el = f.elements[k];
        if (!el) continue;
        if (el.type === 'checkbox') el.checked = !!Number(v); else el.value = v ?? '';
    }
    const t = m.querySelector('[data-title]');
    if (t) t.textContent = (data && data.id) ? t.dataset.edit : t.dataset.new;
    openModal(id);
}
document.querySelectorAll('.fixed.inset-0').forEach(m => m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.fixed.inset-0').forEach(m => closeModal(m.id)); });
<?php if ($openModal): ?>openModal(<?= json_encode($openModal) ?>);<?php endif; ?>
</script>
</body>
</html>
