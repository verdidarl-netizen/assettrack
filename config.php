<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
date_default_timezone_set('Asia/Manila');
define('ROOT', __DIR__);

spl_autoload_register(function (string $c): void {
    $f = ROOT . "/classes/$c.php";
    if (is_file($f)) require $f;
});

const ROLES         = ['Admin', 'Technician', 'Employee'];
const ASSET_STATUS  = ['Available', 'Assigned', 'In Maintenance', 'Retired', 'Lost'];
const PRIORITIES    = ['Low', 'Medium', 'High', 'Critical'];

/* ---------- MySQL connection (XAMPP defaults) ---------- */
const DB_HOST  = '127.0.0.1';
const DB_PORT  = 3306;
const DB_NAME  = 'assettrack_pro';
const DB_USER  = 'root';
const DB_PASS  = '';
const APP_DEBUG = true;   // shows real database errors in the red banner; set false for production
const TICKET_STATUS = ['Open', 'In Progress', 'Resolved', 'Closed'];
const TICKET_TYPES  = ['Repair', 'Routine', 'Inspection'];
const INPUT_CLASS   = 'mt-1 w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500';

/* ---------- small helpers ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function redirect(string $to = 'index.php'): never { header("Location: $to"); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? '')); }
function money($n): string { return '₱' . number_format((float)$n, 2); }
function jattr(array $row): string { return e(json_encode($row)); }

function badge(string $s): string {
    $c = match ($s) {
        'Available', 'Resolved', 'Low' => 'green',
        'In Maintenance', 'In Progress', 'High' => 'yellow',
        'Open', 'Assigned', 'Medium' => 'blue',
        'Lost', 'Critical' => 'red',
        default => 'gray',
    };
    return '<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-' . $c . '-100 text-' . $c . '-800">' . e($s) . '</span>';
}

/* ---------- modal building blocks (used by /modals/*.php) ---------- */
function modal_start(string $id, string $new, string $edit, string $icon, string $action, string $w = 'max-w-lg'): void {
    global $page; ?>
<div id="<?= $id ?>" class="fixed inset-0 bg-gray-900 bg-opacity-50 hidden items-center justify-center p-4 z-50">
  <div class="bg-white rounded-xl shadow-lg <?= $w ?> w-full p-6 max-h-screen overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-bold text-gray-800 flex items-center"><i class="fa-solid <?= $icon ?> text-indigo-600 mr-2"></i>
        <span data-title data-new="<?= e($new) ?>" data-edit="<?= e($edit) ?>"><?= e($new) ?></span></h3>
      <button type="button" onclick="closeModal('<?= $id ?>')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
    </div>
    <form method="POST" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $action ?>">
      <input type="hidden" name="page" value="<?= e($page ?? 'dashboard') ?>">
      <input type="hidden" name="id" value="">
<?php }

function modal_end(string $label): void { ?>
      <div class="flex justify-end space-x-3 pt-2">
        <button type="button" onclick="closeModal(this.closest('.fixed').id)" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-medium">Cancel</button>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium"><?= e($label) ?></button>
      </div>
    </form>
  </div>
</div>
<?php }

function field(string $label, string $name, string $type = 'text', string $extra = ''): void { ?>
<div><label class="block text-sm font-medium text-gray-700"><?= e($label) ?></label>
<?php if ($type === 'textarea'): ?>
<textarea name="<?= $name ?>" rows="3" class="<?= INPUT_CLASS ?>" <?= $extra ?>></textarea>
<?php else: ?>
<input type="<?= $type ?>" name="<?= $name ?>" class="<?= INPUT_CLASS ?>" <?= $extra ?>>
<?php endif; ?></div>
<?php }

/** $options = [value => label]. */
function select_field(string $label, string $name, array $options, bool $blank = true, string $extra = ''): void { ?>
<div><label class="block text-sm font-medium text-gray-700"><?= e($label) ?></label>
<select name="<?= $name ?>" class="<?= INPUT_CLASS ?>" <?= $extra ?>>
<?php if ($blank): ?><option value="">— none —</option><?php endif; ?>
<?php foreach ($options as $v => $l): ?><option value="<?= e((string)$v) ?>"><?= e($l) ?></option><?php endforeach; ?>
</select></div>
<?php }

function delete_btn(string $action, string|int $id): void {
    global $page; ?>
<form method="POST" class="inline" onsubmit="return confirm('Delete this record permanently?')">
  <?= csrf_field() ?><input type="hidden" name="action" value="<?= $action ?>">
  <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="page" value="<?= e($page) ?>">
  <button class="text-red-600 hover:text-red-800 ml-2" title="Delete"><i class="fa-solid fa-trash"></i></button>
</form>
<?php }
