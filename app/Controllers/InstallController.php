<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

/** Trình cài đặt lần đầu: tạo config.php, bảng dữ liệu và tài khoản admin. */
class InstallController
{
    public function handle(): void
    {
        $errors = [];
        $data = [
            'app_url' => $this->guessUrl(),
            'db_host' => 'localhost',
            'db_port' => '3306',
            'db_name' => '',
            'db_user' => '',
            'admin_name' => '',
            'admin_email' => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($data as $k => $v) {
                $data[$k] = trim((string)($_POST[$k] ?? ''));
            }
            $dbPass = (string)($_POST['db_pass'] ?? '');
            $adminPass = (string)($_POST['admin_pass'] ?? '');

            if (!filter_var($data['app_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Địa chỉ website không hợp lệ.';
            }
            if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email admin không hợp lệ.';
            }
            if (mb_strlen($adminPass) < 8) {
                $errors[] = 'Mật khẩu admin tối thiểu 8 ký tự.';
            }
            if (!is_writable(BASE_PATH)) {
                $errors[] = 'Thư mục ' . BASE_PATH . ' không cho phép ghi file config.php.';
            }

            if (!$errors) {
                $config = [
                    'app_url' => rtrim($data['app_url'], '/'),
                    'db_host' => $data['db_host'],
                    'db_port' => (int)$data['db_port'],
                    'db_name' => $data['db_name'],
                    'db_user' => $data['db_user'],
                    'db_pass' => $dbPass,
                    'app_key' => base64_encode(random_bytes(32)),
                    'debug' => false,
                ];
                try {
                    $db = new Database($config);
                    $GLOBALS['__config'] = $config;
                    $db->migrate();
                    if ((int)$db->value('SELECT COUNT(*) FROM users') === 0) {
                        $db->insert('users', [
                            'name' => $data['admin_name'] ?: 'Admin',
                            'email' => mb_strtolower($data['admin_email']),
                            'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                            'role' => 'admin',
                        ]);
                    }
                    $php = "<?php\n// File cấu hình – KHÔNG đưa lên Git.\nreturn " . var_export($config, true) . ";\n";
                    if (file_put_contents(BASE_PATH . '/config.php', $php) === false) {
                        throw new \RuntimeException('Không ghi được file config.php');
                    }
                    @chmod(BASE_PATH . '/config.php', 0640);
                    foreach (['storage', 'storage/logs', 'uploads'] as $dir) {
                        if (!is_dir(BASE_PATH . '/' . $dir)) {
                            @mkdir(BASE_PATH . '/' . $dir, 0775, true);
                        }
                    }
                    flash('success', 'Cài đặt thành công! Hãy đăng nhập bằng tài khoản admin vừa tạo.');
                    redirect('/login');
                } catch (\PDOException $e) {
                    $errors[] = 'Không kết nối được MySQL: ' . $e->getMessage();
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $checks = [
            'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'cURL' => extension_loaded('curl'),
            'OpenSSL' => extension_loaded('openssl'),
            'mbstring' => extension_loaded('mbstring'),
            'SimpleXML' => extension_loaded('simplexml'),
            'Anthropic SDK (composer install)' => class_exists(\Anthropic\Client::class),
        ];
        view('install', ['data' => $data, 'errors' => $errors, 'checks' => $checks], 'layout_guest');
    }

    public function done(): void
    {
        redirect('/login');
    }

    /** Khi config.php được tạo bằng tay: tạo tài khoản admin đầu tiên (chỉ khi chưa có tài khoản nào). */
    public function setupAdmin(): void
    {
        if (db()->value('SELECT 1 FROM users LIMIT 1')) {
            redirect('/login');
        }
        $errors = [];
        $data = ['admin_name' => '', 'admin_email' => ''];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data['admin_name'] = trim((string)($_POST['admin_name'] ?? ''));
            $data['admin_email'] = mb_strtolower(trim((string)($_POST['admin_email'] ?? '')));
            $pass = (string)($_POST['admin_pass'] ?? '');
            if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email không hợp lệ.';
            }
            if (mb_strlen($pass) < 8) {
                $errors[] = 'Mật khẩu tối thiểu 8 ký tự.';
            }
            try {
                \App\Crypto::encrypt('test');
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if (!$errors) {
                db()->insert('users', [
                    'name' => $data['admin_name'] ?: 'Admin',
                    'email' => $data['admin_email'],
                    'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                    'role' => 'admin',
                ]);
                flash('success', 'Đã tạo tài khoản admin. Hãy đăng nhập.');
                redirect('/login');
            }
        }
        view('setup_admin', ['data' => $data, 'errors' => $errors], 'layout_guest');
    }

    private function guessUrl(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path_url();
    }
}
