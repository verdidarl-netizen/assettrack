<?php
/** Asset records (table `asset`) + straight-line depreciation. */
class Asset
{
    private const FIELDS = ['asset_tag', 'name', 'serial_number', 'category_id', 'assigned_user_id', 'status', 'location',
        'purchase_date', 'purchase_cost', 'salvage_value', 'useful_life_years', 'warranty_expiry', 'notes'];

    private const SELECT = 'SELECT a.*, a.asset_id AS id, c.name AS category, u.full_name AS assigned_to
        FROM asset a
        LEFT JOIN asset_category c ON c.category_id = a.category_id
        LEFT JOIN `user` u ON u.user_id = a.assigned_user_id';

    /** Filters: q, status, category (name), user_id. */
    public static function all(array $f = []): array
    {
        $w = []; $p = [];
        if (!empty($f['q'])) {
            $w[] = '(a.asset_tag LIKE :q1 OR a.name LIKE :q2 OR a.serial_number LIKE :q3 OR u.full_name LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) $p[$k] = '%' . $f['q'] . '%';
        }
        if (!empty($f['status']))   { $w[] = 'a.status = :s';           $p['s'] = $f['status']; }
        if (!empty($f['category'])) { $w[] = 'c.name = :c';             $p['c'] = $f['category']; }
        if (!empty($f['user_id']))  { $w[] = 'a.assigned_user_id = :u'; $p['u'] = $f['user_id']; }
        $sql = self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY a.created_at DESC, a.asset_tag';
        return array_map([self::class, 'withBookValue'], Database::all($sql, $p));
    }

    public static function find(string $id): ?array
    {
        $r = Database::one(self::SELECT . ' WHERE a.asset_id = ?', [$id]);
        return $r ? self::withBookValue($r) : null;
    }

    public static function save(array $d, ?string $id): string
    {
        $v = [];
        foreach (self::FIELDS as $f) { $x = trim((string)($d[$f] ?? '')); $v[$f] = $x === '' ? null : $x; }
        if (!$v['asset_tag'] || !$v['name'] || !$v['serial_number']) throw new InvalidArgumentException('Asset tag, name and serial number are required.');
        if (!in_array($v['status'], ASSET_STATUS, true)) $v['status'] = 'Available';
        // keep status and assignment consistent
        if ($v['assigned_user_id'] && $v['status'] === 'Available') $v['status'] = 'Assigned';
        if (!$v['assigned_user_id'] && $v['status'] === 'Assigned') $v['status'] = 'Available';
        $v['purchase_cost']     = max(0, (float)($v['purchase_cost'] ?? 0));
        $v['salvage_value']     = max(0, (float)($v['salvage_value'] ?? 0));
        $v['useful_life_years'] = max(1, (int)($v['useful_life_years'] ?? 5));
        if ($id) { Database::update('asset', 'asset_id', $v, $id); return $id; }
        return Database::insert('asset', 'asset_id', $v + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(string $id): void
    {
        Database::run('DELETE FROM maintenance_ticket WHERE asset_id = ?', [$id]);
        Database::run('DELETE FROM asset WHERE asset_id = ?', [$id]);
    }

    /** Adds accum_dep and book_value (straight-line from purchase date down to salvage value). */
    public static function withBookValue(array $a): array
    {
        $cost = (float)$a['purchase_cost']; $salv = (float)$a['salvage_value']; $life = max(1, (int)$a['useful_life_years']);
        $years = $a['purchase_date'] ? max(0, (time() - strtotime($a['purchase_date'])) / 31557600) : 0;
        $dep = min(max(0, $cost - $salv) / $life * $years, max(0, $cost - $salv));
        $a['accum_dep'] = round($dep, 2);
        $a['book_value'] = round($cost - $dep, 2);
        return $a;
    }

    public static function stats(?int $userId = null): array
    {
        $w = $userId ? ' AND assigned_user_id = ' . (int)$userId : '';
        $c = fn(string $cond) => (int)Database::val("SELECT COUNT(*) FROM asset WHERE $cond$w");
        return [
            'total'         => $c('1=1'),
            'available'     => $c("status = 'Available'"),
            'assigned'      => $c("status = 'Assigned'"),
            'maintenance'   => $c("status = 'In Maintenance'"),
            'warranty_soon' => $c('warranty_expiry BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY'),
        ];
    }
}
