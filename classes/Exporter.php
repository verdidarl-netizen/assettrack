<?php
/** CSV download and print-to-PDF report output. */
class Exporter
{
    public static function csv(string $name, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$name-" . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) fputcsv($out, array_map([self::class, 'safe'], $r));
        exit;
    }

    /** Opens the browser print dialog: choose "Save as PDF". */
    public static function printable(string $title, array $headers, array $rows): never
    { ?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title><?= e($title) ?></title>
<style>body{font-family:sans-serif;font-size:12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:4px 6px;text-align:left}th{background:#eee}</style></head>
<body onload="window.print()"><h2><?= e($title) ?> — <?= date('F j, Y') ?></h2>
<table><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr>
<?php foreach ($rows as $r): ?><tr><?php foreach ($r as $c): ?><td><?= e($c) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</table></body></html>
<?php exit; }

    /** Neutralise spreadsheet formula injection. */
    private static function safe($v): string
    {
        $v = (string)$v;
        return $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v;
    }
}
