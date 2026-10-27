<?php
/**
 * One-time, non-destructive upgrade of the existing `assettrack_pro` database.
 * Never drops or deletes anything: it widens a few ENUM columns, adds missing columns/tables, and resets
 * placeholder passwords. Safe to run repeatedly.
 */
class Schema
{
    public static function migrate(): void
    {
        $db = Database::pdo();

        // 1. Widen status/role/priority columns to VARCHAR so the app's values always fit (existing values are kept).
        foreach ([
            "ALTER TABLE `asset` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'Available'",
            "ALTER TABLE `user` MODIFY `role` VARCHAR(20) NOT NULL DEFAULT 'Employee'",
            "ALTER TABLE `maintenance_ticket` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'Open'",
            "ALTER TABLE `maintenance_ticket` MODIFY `priority` VARCHAR(20) NOT NULL DEFAULT 'Medium'",
        ] as $sql) { try { $db->exec($sql); } catch (PDOException) {} }

        // 2. asset: columns used for names, depreciation and warranty tracking.
        self::add('asset', 'name', 'VARCHAR(150) NULL');
        self::add('asset', 'location', 'VARCHAR(100) NULL');
        self::add('asset', 'purchase_date', 'DATE NULL');
        self::add('asset', 'purchase_cost', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        self::add('asset', 'salvage_value', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        self::add('asset', 'useful_life_years', 'INT NOT NULL DEFAULT 5');
        self::add('asset', 'warranty_expiry', 'DATE NULL');
        self::add('asset', 'notes', 'TEXT NULL');
        $db->exec("UPDATE `asset` SET `name` = `asset_tag` WHERE `name` IS NULL OR `name` = ''");

        // 3. maintenance_ticket: extra workflow columns (your issue_description / priority / status are reused).
        self::add('maintenance_ticket', 'title', 'VARCHAR(150) NULL');
        self::add('maintenance_ticket', 'type', "VARCHAR(20) NOT NULL DEFAULT 'Repair'");
        self::add('maintenance_ticket', 'created_by', 'INT NULL');
        self::add('maintenance_ticket', 'assigned_technician_id', 'INT NULL');
        self::add('maintenance_ticket', 'cost', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        self::add('maintenance_ticket', 'scheduled_date', 'DATE NULL');
        self::add('maintenance_ticket', 'resolved_at', 'DATETIME NULL');

        // 4. New tables: activity log + notifications.
        $db->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
            id INT AUTO_INCREMENT PRIMARY KEY, actor_user_id INT NULL, actor_name VARCHAR(100) NULL,
            action VARCHAR(30) NOT NULL, entity VARCHAR(30) NULL, entity_id VARCHAR(64) NULL, details VARCHAR(255) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, message VARCHAR(500) NOT NULL,
            asset_id VARCHAR(64) NULL, ticket_id VARCHAR(64) NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX (user_id, is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 5. Starter departments if the table is empty.
        if (!(int)Database::val('SELECT COUNT(*) FROM `department`')) {
            foreach (['IT', 'Finance', 'HR', 'Operations'] as $n) { try { Database::insert('department', 'department_id', ['name' => $n]); } catch (PDOException) {} }
        }

        // 6. Demo rows were stored with the literal placeholder 'hash' as password, which can never log in.
        //    Give them a real hash of "password123" (real bcrypt hashes are untouched).
        Database::run("UPDATE `user` SET `password_hash` = ? WHERE `password_hash` = 'hash'", [password_hash('password123', PASSWORD_DEFAULT)]);

        // 7. user: department link + notification toggle (last step = "migration done" marker).
        $deptType = Database::colInfo('department', 'department_id')['COLUMN_TYPE'] ?? 'INT';
        self::add('user', 'department_id', "$deptType NULL");
        self::add('user', 'notify_enabled', 'TINYINT(1) NOT NULL DEFAULT 1');
    }

    private static function add(string $table, string $col, string $ddl): void
    {
        if (!Database::hasColumn($table, $col)) Database::pdo()->exec("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
    }
}
