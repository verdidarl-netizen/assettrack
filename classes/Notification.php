<?php
/** In-app alerts: upcoming maintenance, assigned tickets/assets, expiring warranties. */
class Notification
{
    /** Called on each page load; creates de-duplicated alerts. */
    public static function generate(): void
    {
        $admins = Database::all("SELECT user_id FROM `user` WHERE role = 'Admin'");
        $soon = Database::all('SELECT asset_id, asset_tag, name, warranty_expiry FROM asset
            WHERE warranty_expiry BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY');
        foreach ($soon as $a)
            foreach ($admins as $u)
                self::once((int)$u['user_id'], "Warranty for {$a['asset_tag']} ({$a['name']}) expires on {$a['warranty_expiry']}.", $a['asset_id'], null);

        $due = Database::all("SELECT t.ticket_id, t.asset_id, COALESCE(NULLIF(t.title, ''), t.issue_description) AS title,
                t.scheduled_date, t.assigned_technician_id, a.asset_tag
            FROM maintenance_ticket t JOIN asset a ON a.asset_id = t.asset_id
            WHERE t.status IN ('Open','In Progress') AND t.assigned_technician_id IS NOT NULL
              AND t.scheduled_date IS NOT NULL AND t.scheduled_date <= CURDATE() + INTERVAL 7 DAY");
        foreach ($due as $t)
            self::once((int)$t['assigned_technician_id'], "Maintenance due {$t['scheduled_date']}: {$t['title']} ({$t['asset_tag']}).", $t['asset_id'], $t['ticket_id']);
    }

    /** Send an alert right away (respects the user's notification setting). */
    public static function push(int $userId, string $msg, ?string $assetId = null, ?string $ticketId = null): void { self::once($userId, $msg, $assetId, $ticketId); }

    private static function once(int $uid, string $msg, ?string $asset, ?string $ticket): void
    {
        $msg = mb_substr($msg, 0, 500);
        $on = Database::val('SELECT notify_enabled FROM `user` WHERE user_id = ?', [$uid]);
        if (!$on) return;
        if (Database::val('SELECT 1 FROM notifications WHERE user_id = ? AND message = ?', [$uid, $msg])) return;
        Database::insert('notifications', 'id', ['user_id' => $uid, 'message' => $msg, 'asset_id' => $asset, 'ticket_id' => $ticket]);
    }

    public static function forUser(int $uid, int $n = 8): array
    {
        return Database::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int)$n, [$uid]);
    }
    public static function unread(int $uid): int { return (int)Database::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]); }
    public static function markAllRead(int $uid): void { Database::run('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$uid]); }
}
