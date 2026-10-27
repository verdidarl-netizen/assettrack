<?php
/** Maintenance / repair tickets (table `maintenance_ticket`). */
class Ticket
{
    private const SELECT = "SELECT t.*, t.ticket_id AS id, COALESCE(NULLIF(t.title, ''), t.issue_description) AS title,
            a.asset_tag, a.name AS asset_name, c.full_name AS creator, tech.full_name AS technician
        FROM maintenance_ticket t
        JOIN asset a ON a.asset_id = t.asset_id
        LEFT JOIN `user` c ON c.user_id = t.created_by
        LEFT JOIN `user` tech ON tech.user_id = t.assigned_technician_id";

    /** Filters: q, status, created_by. */
    public static function all(array $f = []): array
    {
        $w = []; $p = [];
        if (!empty($f['q'])) {
            $w[] = '(t.title LIKE :q1 OR t.issue_description LIKE :q2 OR a.asset_tag LIKE :q3 OR a.name LIKE :q4)';
            foreach (['q1', 'q2', 'q3', 'q4'] as $k) $p[$k] = '%' . $f['q'] . '%';
        }
        if (!empty($f['status']))     { $w[] = 't.status = :s';     $p['s'] = $f['status']; }
        if (!empty($f['created_by'])) { $w[] = 't.created_by = :u'; $p['u'] = $f['created_by']; }
        return Database::all(self::SELECT . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY t.created_at DESC', $p);
    }

    public static function find(string $id): ?array { return Database::one('SELECT * FROM maintenance_ticket WHERE ticket_id = ?', [$id]); }

    public static function save(array $d, ?string $id, int $actor): string
    {
        $title = trim($d['title'] ?? '');
        $desc  = trim($d['issue_description'] ?? '');
        $v = [
            'asset_id'          => trim((string)($d['asset_id'] ?? '')),
            'title'             => $title,
            'issue_description' => $desc !== '' ? $desc : $title,
            'priority'          => in_array($d['priority'] ?? '', PRIORITIES, true) ? $d['priority'] : 'Medium',
            'type'              => in_array($d['type'] ?? '', TICKET_TYPES, true) ? $d['type'] : 'Repair',
            'status'            => in_array($d['status'] ?? '', TICKET_STATUS, true) ? $d['status'] : 'Open',
            'assigned_technician_id' => ($d['assigned_technician_id'] ?? '') ?: null,
            'scheduled_date'    => ($d['scheduled_date'] ?? '') ?: null,
            'cost'              => max(0, (float)($d['cost'] ?? 0)),
        ];
        if ($title === '' || $v['asset_id'] === '') throw new InvalidArgumentException('Asset and title are required.');
        $done = in_array($v['status'], ['Resolved', 'Closed'], true);

        if ($id) {
            $old = self::find($id);
            $v['resolved_at'] = $done ? ($old['resolved_at'] ?? date('Y-m-d H:i:s')) : null;
            Database::update('maintenance_ticket', 'ticket_id', $v, $id);
        } else {
            $v['created_by']  = $actor;
            $v['created_at']  = date('Y-m-d H:i:s');
            $v['resolved_at'] = $done ? date('Y-m-d H:i:s') : null;
            $id = Database::insert('maintenance_ticket', 'ticket_id', $v);
        }

        // Keep the asset's lifecycle status in sync with its repair tickets.
        if (!$done && $v['type'] === 'Repair')
            Database::run("UPDATE asset SET status = 'In Maintenance' WHERE asset_id = ? AND status IN ('Available','Assigned')", [$v['asset_id']]);
        if ($done)
            Database::run("UPDATE asset SET status = IF(assigned_user_id IS NULL, 'Available', 'Assigned')
                WHERE asset_id = ? AND status = 'In Maintenance'
                AND NOT EXISTS (SELECT 1 FROM maintenance_ticket WHERE asset_id = ? AND type = 'Repair' AND status IN ('Open','In Progress'))",
                [$v['asset_id'], $v['asset_id']]);
        return $id;
    }

    public static function delete(string $id): void { Database::run('DELETE FROM maintenance_ticket WHERE ticket_id = ?', [$id]); }

    public static function stats(?int $userId = null): array
    {
        $w = $userId ? ' AND created_by = ' . (int)$userId : '';
        return [
            'open' => (int)Database::val("SELECT COUNT(*) FROM maintenance_ticket WHERE status IN ('Open','In Progress')$w"),
            'cost' => (float)Database::val("SELECT COALESCE(SUM(cost), 0) FROM maintenance_ticket WHERE 1 = 1$w"),
        ];
    }
}
