<?php
/** PDO/MySQL wrapper (XAMPP + phpMyAdmin database `assettrack_pro`) with small query helpers. */
class Database
{
    private static ?PDO $pdo = null;
    private static array $cols = [];

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        try {
            self::$pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME), DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h3>Cannot connect to MySQL</h3><p>Start <b>MySQL</b> in the XAMPP Control Panel and check the DB_* settings at the top of <code>config.php</code>.</p>'
                . (APP_DEBUG ? '<pre>' . e($e->getMessage()) . '</pre>' : ''));
        }
        // Adds the extra columns/tables AssetTrack Pro needs to your existing schema (runs once; idempotent).
        if (!self::hasColumn('user', 'notify_enabled')) Schema::migrate();
        return self::$pdo;
    }

    public static function run(string $sql, array $p = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($p);
        return $st;
    }
    public static function all(string $sql, array $p = []): array { return self::run($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { return self::run($sql, $p)->fetch() ?: null; }
    public static function val(string $sql, array $p = []) { return self::run($sql, $p)->fetchColumn(); }

    /** information_schema info for one column (cached). */
    public static function colInfo(string $table, string $col): ?array
    {
        $k = "$table.$col";
        if (!array_key_exists($k, self::$cols)) {
            self::$cols[$k] = self::pdo()->prepare('SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            self::$cols[$k]->execute([$table, $col]);
            self::$cols[$k] = self::$cols[$k]->fetch() ?: null;
        }
        return self::$cols[$k];
    }
    public static function hasColumn(string $table, string $col): bool { self::$cols = []; return self::colInfo($table, $col) !== null; }

    /** Primary keys such as asset_id are CHAR(36) UUIDs in your database; others are auto-increment ints. */
    public static function isUuid(string $table, string $pk): bool
    {
        return in_array(strtolower(self::colInfo($table, $pk)['DATA_TYPE'] ?? ''), ['char', 'varchar'], true);
    }
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** Insert a row and return its primary key (generated UUID or auto-increment id) as a string. Internal names only. */
    public static function insert(string $table, string $pk, array $d): string
    {
        if (self::isUuid($table, $pk) && empty($d[$pk])) $d[$pk] = self::uuid();
        $cols = '`' . implode('`,`', array_keys($d)) . '`';
        self::run("INSERT INTO `$table` ($cols) VALUES (" . implode(',', array_fill(0, count($d), '?')) . ')', array_values($d));
        return (string)($d[$pk] ?? self::pdo()->lastInsertId());
    }
    public static function update(string $table, string $pk, array $d, $id): void
    {
        $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($d)));
        self::run("UPDATE `$table` SET $set WHERE `$pk`=?", [...array_values($d), $id]);
    }

    /** id => name map for dropdowns (duplicate category names collapse to one entry). */
    public static function lookup(string $what): array
    {
        $sql = match ($what) {
            'asset_categories' => 'SELECT MIN(category_id) AS id, name FROM asset_category GROUP BY name ORDER BY name',
            'departments'      => 'SELECT department_id AS id, name FROM department ORDER BY name',
            default            => null,
        };
        return $sql ? array_column(self::all($sql), 'name', 'id') : [];
    }
}
