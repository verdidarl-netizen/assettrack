<?php
/** Session auth + role checks (table `user`). */
class Auth
{
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): int { return (int)($_SESSION['user']['id'] ?? 0); }
    public static function is(string ...$roles): bool { return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], $roles, true); }

    /** Block the request unless the user has one of the roles. */
    public static function need(string ...$roles): void
    {
        if (!self::is(...$roles)) { flash('error', 'You do not have permission to do that.'); redirect(); }
    }

    public static function attempt(string $email, string $pass): bool
    {
        if (($_SESSION['attempts'] ?? 0) >= 5 && time() < ($_SESSION['lock_until'] ?? 0)) {
            flash('error', 'Too many failed attempts. Try again in a minute.');
            return false;
        }
        $u = Database::one('SELECT * FROM `user` WHERE email = ?', [trim($email)]);
        if ($u && password_verify($pass, (string)$u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['attempts'] = 0;
            $_SESSION['user'] = ['id' => (int)$u['user_id'], 'name' => $u['full_name'], 'email' => $u['email'], 'role' => $u['role']];
            Log::add('login', 'user', (string)$u['user_id']);
            return true;
        }
        if (($_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1) >= 5) $_SESSION['lock_until'] = time() + 60;
        flash('error', 'Invalid email or password.');
        return false;
    }

    /** Self sign-up: always an Employee. Returns an error string or null. */
    public static function register(array $d): ?string
    {
        $name = trim($d['full_name'] ?? ''); $email = strtolower(trim($d['email'] ?? ''));
        $pw = $d['password'] ?? '';
        if ($name === '' || mb_strlen($name) > 100) return 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';
        if (strlen($pw) < 8) return 'Password must be at least 8 characters.';
        if ($pw !== ($d['confirm'] ?? '')) return 'Passwords do not match.';
        if (Database::val('SELECT 1 FROM `user` WHERE email = ?', [$email])) return 'That email is already registered.';
        $id = Database::insert('user', 'user_id', ['full_name' => $name, 'email' => $email,
            'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'role' => 'Employee', 'created_at' => date('Y-m-d H:i:s')]);
        Log::add('register', 'user', $id, $email);
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        session_start();
    }
}
