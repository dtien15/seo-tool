<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;

class AuthController
{
    public function loginForm(): void
    {
        if (Auth::user()) {
            redirect('/');
        }
        view('login', [], 'layout_guest');
    }

    public function login(): void
    {
        $key = 'login_attempts';
        $attempts = array_filter($_SESSION[$key] ?? [], fn($t) => $t > time() - 900);
        if (count($attempts) >= 8) {
            flash('danger', 'Bạn đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.');
            redirect('/login');
        }
        if (Auth::attempt((string)input('email'), (string)($_POST['password'] ?? ''))) {
            unset($_SESSION[$key]);
            redirect('/');
        }
        $attempts[] = time();
        $_SESSION[$key] = $attempts;
        flash('danger', 'Email hoặc mật khẩu không đúng.');
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }

    public function profile(): void
    {
        $user = Auth::requireLogin();
        view('profile', ['user' => $user, 'pageTitle' => 'Tài khoản của tôi']);
    }

    public function updateProfile(): void
    {
        $user = Auth::requireLogin();
        $name = (string)input('name');
        if ($name !== '') {
            db()->update('users', ['name' => mb_substr($name, 0, 150)], 'id = ?', [$user['id']]);
        }
        $new = (string)($_POST['new_password'] ?? '');
        if ($new !== '') {
            if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
                throw new \RuntimeException('Mật khẩu hiện tại không đúng.');
            }
            if (mb_strlen($new) < 8) {
                throw new \RuntimeException('Mật khẩu mới tối thiểu 8 ký tự.');
            }
            db()->update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        flash('success', 'Đã lưu thông tin tài khoản.');
        redirect('/profile');
    }
}
