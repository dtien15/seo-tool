<?php
declare(strict_types=1);

namespace App;

class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            if ($id) {
                $u = db()->fetch('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]);
                self::$user = $u ?: null;
                if (!$u) {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return isset(self::user()['id']) ? (int)self::user()['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function role(): string
    {
        return (string)(self::user()['role'] ?? '');
    }

    /** Admin được coi như có mọi vai trò. */
    public static function is(string ...$roles): bool
    {
        $role = self::role();
        return $role === 'admin' || in_array($role, $roles, true);
    }

    /** Admin và Trưởng phòng xem được mọi dự án. */
    public static function seesAllProjects(?array $user = null): bool
    {
        $user ??= self::user();
        return in_array($user['role'] ?? '', ['admin', 'leader'], true);
    }

    public static function requireRole(string ...$roles): array
    {
        $u = self::requireLogin();
        if (!self::is(...$roles)) {
            abort(403, 'Vai trò của bạn không thực hiện được thao tác này.');
        }
        return $u;
    }

    public static function attempt(string $email, string $password): bool
    {
        $u = db()->fetch('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
        if (!$u || !$u['is_active'] || !password_verify($password, $u['password_hash'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];
        db()->update('users', ['last_login_at' => now()], 'id = ?', [$u['id']]);
        self::$loaded = false;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            if (wants_json()) {
                abort(401, 'Bạn cần đăng nhập lại.');
            }
            redirect('/login');
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireLogin();
        if ($u['role'] !== 'admin') {
            abort(403, 'Chỉ quản trị viên mới truy cập được trang này.');
        }
        return $u;
    }
}
