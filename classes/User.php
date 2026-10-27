<?php
/** Staff accounts and profile updates (table `user`). */
class User
{
    private const SELECT = 'SELECT u.user_id AS id, u.full_name, u.email, u.role, u.department_id, u.notify_enabled, u.created_at, d.name AS department
        FROM `user` u LEFT JOIN department d ON d.department_id = u.department_id';

    public static function all(): array { return Database::all(self::SELECT . ' ORDER BY u.user_id'); }
    public static function find(int $id): ?array { return Database::one(self::SELECT . ' WHERE u.user_id = ?', [$id]); }

    /** id => name, optionally only one role. */
    public static function options(?string $role = null): array
    {
        $rows = $role ? Database::all('SELECT user_id, full_name FROM `user` WHERE role = ? ORDER BY full_name', [$role])
                      : Database::all('SELECT user_id, full_name FROM `user` ORDER BY full_name');
        return array_column($rows, 'full_name', 'user_id');
    }

    /** Admin create/edit. Password optional when editing. */
    public static function save(array $d, ?int $id): int
    {
        $name = trim($d['full_name'] ?? ''); $email = strtolower(trim($d['email'] ?? '')); $pw = $d['password'] ?? '';
        $role = in_array($d['role'] ?? '', ROLES, true) ? $d['role'] : 'Employee';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Name and a valid email are required.');
        if ((!$id || $pw !== '') && strlen($pw) < 8) throw new InvalidArgumentException('Password must be at least 8 characters.');
        $dup = Database::val('SELECT user_id FROM `user` WHERE email = ? AND user_id <> ?', [$email, $id ?? 0]);
        if ($dup) throw new InvalidArgumentException('That email is already registered.');
        $v = ['full_name' => $name, 'email' => $email, 'role' => $role, 'department_id' => ($d['department_id'] ?? '') ?: null];
        if ($pw !== '') $v['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        if ($id) { Database::update('user', 'user_id', $v, $id); return $id; }
        return (int)Database::insert('user', 'user_id', $v + ['created_at' => date('Y-m-d H:i:s')]);
    }

    public static function delete(int $id): void
    {
        // release anything pointing at this user so foreign keys never block the delete
        Database::run('UPDATE asset SET assigned_user_id = NULL WHERE assigned_user_id = ?', [$id]);
        Database::run('UPDATE maintenance_ticket SET created_by = NULL WHERE created_by = ?', [$id]);
        Database::run('UPDATE maintenance_ticket SET assigned_technician_id = NULL WHERE assigned_technician_id = ?', [$id]);
        Database::run('DELETE FROM notifications WHERE user_id = ?', [$id]);
        Database::run('DELETE FROM `user` WHERE user_id = ?', [$id]);
    }

    /** Self-service profile (cannot change own role). */
    public static function updateProfile(int $id, array $d): void
    {
        $name = trim($d['full_name'] ?? ''); $email = strtolower(trim($d['email'] ?? '')); $pw = $d['password'] ?? '';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Name and a valid email are required.');
        if (Database::val('SELECT user_id FROM `user` WHERE email = ? AND user_id <> ?', [$email, $id])) throw new InvalidArgumentException('That email is already registered.');
        $v = ['full_name' => $name, 'email' => $email, 'department_id' => ($d['department_id'] ?? '') ?: null,
              'notify_enabled' => empty($d['notify_enabled']) ? 0 : 1];
        if ($pw !== '') {
            if (strlen($pw) < 8) throw new InvalidArgumentException('New password must be at least 8 characters.');
            $v['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }
        Database::update('user', 'user_id', $v, $id);
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
    }
}
