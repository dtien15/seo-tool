<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

/** Admin quản lý tài khoản SEOer. */
class UserController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $users = db()->fetchAll(
            "SELECT u.*,
               (SELECT COUNT(*) FROM projects p WHERE p.owner_id = u.id) AS project_count,
               (SELECT COALESCE(SUM(cost_usd),0) FROM usage_logs l WHERE l.user_id = u.id AND l.created_at >= ?) AS month_cost
             FROM users u ORDER BY FIELD(u.role, 'admin','leader','seo','content','design'), u.name",
            [date('Y-m-01')]
        );
        view('users/index', ['pageTitle' => 'Tài khoản', 'users' => $users]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        $email = mb_strtolower((string)input('email', ''));
        $password = (string)($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Email không hợp lệ.');
        }
        if (db()->value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            throw new \RuntimeException('Email này đã có tài khoản.');
        }
        if (mb_strlen($password) < 8) {
            throw new \RuntimeException('Mật khẩu tối thiểu 8 ký tự.');
        }
        db()->insert('users', [
            'name' => mb_substr((string)input('name', '') ?: $email, 0, 150),
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => isset(user_roles()[input('role')]) ? input('role') : 'seo',
            'monthly_budget_usd' => input('monthly_budget_usd') !== '' && input('monthly_budget_usd') !== null ? (float)input('monthly_budget_usd') : null,
        ]);
        flash('success', 'Đã tạo tài khoản ' . $email . '. Gửi email + mật khẩu cho nhân viên để đăng nhập.');
        redirect('/users');
    }

    public function update(int $id): void
    {
        $me = Auth::requireAdmin();
        $user = db()->fetch('SELECT * FROM users WHERE id = ?', [$id]) ?? abort(404);
        $data = [
            'name' => mb_substr((string)input('name', $user['name']), 0, 150),
            'role' => isset(user_roles()[input('role')]) ? input('role') : 'seo',
            'is_active' => input('is_active') ? 1 : 0,
            'monthly_budget_usd' => input('monthly_budget_usd') !== '' && input('monthly_budget_usd') !== null ? (float)input('monthly_budget_usd') : null,
        ];
        if ((int)$id === (int)$me['id']) {
            $data['role'] = 'admin';   // không tự hạ quyền / khóa chính mình
            $data['is_active'] = 1;
        }
        $password = (string)($_POST['password'] ?? '');
        if ($password !== '') {
            if (mb_strlen($password) < 8) {
                throw new \RuntimeException('Mật khẩu tối thiểu 8 ký tự.');
            }
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        db()->update('users', $data, 'id = ?', [$id]);
        flash('success', 'Đã cập nhật tài khoản ' . $user['email'] . '.');
        redirect('/users');
    }
}
