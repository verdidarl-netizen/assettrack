<?php
/** GET /backend/export.php?type=assets|tickets&format=csv|print  (Admin & Technician). */
require __DIR__ . '/../config.php';
Auth::need('Admin', 'Technician');

$type = $_GET['type'] ?? 'assets';
$fmt  = $_GET['format'] ?? 'csv';

if ($type === 'tickets') {
    $title = 'Maintenance History';
    $head  = ['Ticket ID', 'Asset Tag', 'Asset', 'Title', 'Type', 'Priority', 'Status', 'Technician', 'Cost', 'Scheduled', 'Created', 'Resolved'];
    $rows  = array_map(fn($t) => [$t['id'], $t['asset_tag'], $t['asset_name'], $t['title'], $t['type'], $t['priority'], $t['status'], $t['technician'],
                                  number_format((float)$t['cost'], 2, '.', ''), $t['scheduled_date'], $t['created_at'], $t['resolved_at']], Ticket::all());
} else {
    $title = 'Asset Inventory';
    $head  = ['Asset Tag', 'Name', 'Serial', 'Category', 'Location', 'Assigned To', 'Status', 'Purchase Date', 'Cost', 'Warranty', 'Book Value'];
    $rows  = array_map(fn($a) => [$a['asset_tag'], $a['name'], $a['serial_number'], $a['category'], $a['location'], $a['assigned_to'], $a['status'],
                                  $a['purchase_date'], number_format((float)$a['purchase_cost'], 2, '.', ''), $a['warranty_expiry'],
                                  number_format((float)$a['book_value'], 2, '.', '')], Asset::all());
}
Log::add('export', $type, null, $fmt);
$fmt === 'print' ? Exporter::printable($title, $head, $rows) : Exporter::csv($type, $head, $rows);
