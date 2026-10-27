<?php
/**
 * TCL demo (Transaction Control Language) adapted to AssetTrack Pro.
 * Same idea as the original transaction_demo (beginTransaction -> do steps -> check -> commit, or rollBack on any error),
 * but applied to assets and maintenance tickets instead of money:
 *
 *   1. assignAsset()    Available asset -> employee         (asset + audit_log + notification, all or nothing)
 *   2. transferAsset()  employee A -> employee B            (release + receiver check + assign; like sender/receiver money transfer)
 *   3. completeTicket() resolve ticket + record cost        (ticket + asset status + audit_log + notification)
 *
 * Every transaction can be run with "force failure" to prove the ROLLBACK undoes everything.
 * Requires InnoDB tables (phpMyAdmin default for XAMPP's MariaDB).
 */
class Transaction
{
    private static array $steps = [];

    /* ============ 1. Assign an asset to a user ============ */
    public static function assignAsset(string $assetId, int $userId, bool $fail = false): array
    {
        return self::run('Transaction 1 — Assign Asset', function () use ($assetId, $userId, $fail) {
            $a = self::lockAsset($assetId);
            self::step("SELECT … FOR UPDATE → {$a['asset_tag']} is '{$a['status']}'");
            if ($a['status'] !== 'Available' || !empty($a['assigned_user_id']))
                throw new InvalidArgumentException("Asset {$a['asset_tag']} is not available (status: {$a['status']}).");

            $u = self::findUser($userId);
            self::step("SELECT user → #{$u['user_id']} {$u['full_name']}");

            Database::run("UPDATE asset SET assigned_user_id = ?, status = 'Assigned' WHERE asset_id = ?", [$userId, $assetId]);
            self::step("UPDATE asset → assigned to #$userId, status 'Assigned'");

            Log::add('tx_commit', 'transaction', $assetId, "Assigned {$a['asset_tag']} to {$u['full_name']}");
            self::step('INSERT audit_log');
            Notification::push($userId, "Asset {$a['asset_tag']} ({$a['name']}) was assigned to you.", $assetId);
            self::step('INSERT notifications');

            self::maybeFail($fail);
            return "{$a['asset_tag']} assigned to {$u['full_name']}.";
        });
    }

    /* ============ 2. Transfer an asset between two users ============ */
    public static function transferAsset(string $assetId, int $fromId, int $toId, bool $fail = false): array
    {
        return self::run('Transaction 2 — Transfer Asset', function () use ($assetId, $fromId, $toId, $fail) {
            if ($fromId === $toId) throw new InvalidArgumentException('Sender and receiver must be different users.');
            $a = self::lockAsset($assetId);
            $from = self::findUser($fromId);
            self::step("SELECT … FOR UPDATE → {$a['asset_tag']} is held by #" . ($a['assigned_user_id'] ?: 'nobody'));

            // Step 1: release from sender (like deducting from the sender's balance)
            $st = Database::run("UPDATE asset SET assigned_user_id = NULL, status = 'Available' WHERE asset_id = ? AND assigned_user_id = ?", [$assetId, $fromId]);
            if ($st->rowCount() === 0)
                throw new InvalidArgumentException("{$from['full_name']} (#$fromId) does not hold {$a['asset_tag']}.");
            self::step("UPDATE asset → released from #$fromId {$from['full_name']}");

            // Step 2: make sure the receiver exists BEFORE giving the asset away
            $to = Database::one('SELECT user_id, full_name FROM `user` WHERE user_id = ?', [$toId]);
            if (!$to) throw new InvalidArgumentException("Receiver ID $toId does not exist.");
            self::step("SELECT receiver → #{$to['user_id']} {$to['full_name']}");

            // Step 3: assign to receiver (like adding to the receiver's balance)
            Database::run("UPDATE asset SET assigned_user_id = ?, status = 'Assigned' WHERE asset_id = ?", [$toId, $assetId]);
            self::step("UPDATE asset → assigned to #$toId {$to['full_name']}");

            Log::add('tx_commit', 'transaction', $assetId, "Transferred {$a['asset_tag']}: {$from['full_name']} → {$to['full_name']}");
            self::step('INSERT audit_log');
            Notification::push($toId, "Asset {$a['asset_tag']} ({$a['name']}) was transferred to you from {$from['full_name']}.", $assetId);
            Notification::push($fromId, "Asset {$a['asset_tag']} ({$a['name']}) was transferred from you to {$to['full_name']}.", $assetId);
            self::step('INSERT notifications (sender + receiver)');

            self::maybeFail($fail);
            return "{$a['asset_tag']} transferred from {$from['full_name']} to {$to['full_name']}.";
        });
    }

    /* ============ 3. Resolve a maintenance ticket and record its cost ============ */
    public static function completeTicket(string $ticketId, float $cost, bool $fail = false): array
    {
        return self::run('Transaction 3 — Complete Maintenance Ticket', function () use ($ticketId, $cost, $fail) {
            $t = Database::one("SELECT t.ticket_id, t.asset_id, t.status, t.type, t.created_by, a.asset_tag,
                    COALESCE(NULLIF(t.title, ''), t.issue_description) AS title
                FROM maintenance_ticket t JOIN asset a ON a.asset_id = t.asset_id
                WHERE t.ticket_id = ? FOR UPDATE", [$ticketId]);
            if (!$t) throw new InvalidArgumentException('Ticket not found.');
            self::step("SELECT … FOR UPDATE → \"{$t['title']}\" is '{$t['status']}'");
            if (in_array($t['status'], ['Resolved', 'Closed'], true))
                throw new InvalidArgumentException("Ticket is already {$t['status']}.");
            if ($cost < 0) throw new InvalidArgumentException('Repair cost cannot be negative.');

            Database::run("UPDATE maintenance_ticket SET status = 'Resolved', cost = ?, resolved_at = ? WHERE ticket_id = ?", [$cost, date('Y-m-d H:i:s'), $ticketId]);
            self::step('UPDATE maintenance_ticket → Resolved, cost ' . money($cost));

            Database::run("UPDATE asset SET status = IF(assigned_user_id IS NULL, 'Available', 'Assigned')
                WHERE asset_id = ? AND status = 'In Maintenance'
                AND NOT EXISTS (SELECT 1 FROM maintenance_ticket WHERE asset_id = ? AND type = 'Repair' AND status IN ('Open','In Progress'))",
                [$t['asset_id'], $t['asset_id']]);
            self::step("UPDATE asset → {$t['asset_tag']} released from 'In Maintenance' (if no other open repair)");

            Log::add('tx_commit', 'transaction', $ticketId, "Resolved ticket \"{$t['title']}\" ({$t['asset_tag']}), cost " . money($cost));
            self::step('INSERT audit_log');
            if ($t['created_by']) {
                Notification::push((int)$t['created_by'], "Your ticket \"{$t['title']}\" for {$t['asset_tag']} was resolved.", $t['asset_id'], $ticketId);
                self::step('INSERT notifications');
            }

            self::maybeFail($fail);
            return "Ticket \"{$t['title']}\" resolved at " . money($cost) . '.';
        });
    }

    /* ============ helpers ============ */

    /** BEGIN -> run callback -> COMMIT, or ROLLBACK on any error. Returns a result array for the page. */
    private static function run(string $title, callable $work): array
    {
        $pdo = Database::pdo();
        self::$steps = [];
        try {
            $pdo->beginTransaction();
            self::step('BEGIN TRANSACTION');
            $msg = $work();
            $pdo->commit();
            self::step('COMMIT — all changes saved');
            return ['status' => 'success', 'title' => $title, 'message' => $msg, 'steps' => self::$steps];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $m = ($e instanceof PDOException && !APP_DEBUG) ? 'Database error.' : $e->getMessage();
            self::step("ROLLBACK — $m");
            try { Log::add('tx_rollback', 'transaction', null, "$title: $m"); } catch (Throwable) {}   // logged AFTER the rollback so it survives
            return ['status' => 'error', 'title' => $title, 'message' => $m . ' Nothing was changed.', 'steps' => self::$steps];
        }
    }

    private static function step(string $s): void { self::$steps[] = $s; }

    private static function maybeFail(bool $fail): void
    {
        if (!$fail) return;
        self::step('Simulated error raised after all steps ran');
        throw new RuntimeException('Simulated failure (force-failure option was ticked).');
    }

    private static function lockAsset(string $id): array
    {
        $a = Database::one('SELECT asset_id, asset_tag, name, status, assigned_user_id FROM asset WHERE asset_id = ? FOR UPDATE', [$id]);
        if (!$a) throw new InvalidArgumentException('Asset not found.');
        return $a;
    }

    private static function findUser(int $id): array
    {
        $u = Database::one('SELECT user_id, full_name FROM `user` WHERE user_id = ?', [$id]);
        if (!$u) throw new InvalidArgumentException("User ID $id does not exist.");
        return $u;
    }

    /** Tables that are not InnoDB cannot roll back; the page warns about them. */
    public static function nonInnoDb(): array
    {
        return Database::all("SELECT TABLE_NAME AS t, ENGINE AS e FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB'
            AND TABLE_NAME IN ('asset','user','maintenance_ticket','audit_log','notifications')");
    }

    public static function recentLog(int $n = 10): array
    {
        return Database::all("SELECT * FROM audit_log WHERE entity = 'transaction' ORDER BY id DESC LIMIT " . (int)$n);
    }
}
