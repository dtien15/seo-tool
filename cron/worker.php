<?php
/**
 * Worker xử lý hàng đợi. Cài cron trên cPanel chạy MỖI PHÚT:
 *   * * * * * /usr/local/bin/php /home/USER/public_html/cron/worker.php >/dev/null 2>&1
 */
declare(strict_types=1);

// Kiểm tra phiên bản PHP trước khi nạp code (code dùng cú pháp PHP 8.1)
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Tool cần PHP 8.1 trở lên, hosting đang chạy PHP ' . PHP_VERSION . ".
Vào cPanel > Select PHP Version (hoặc MultiPHP Manager) để chọn PHP 8.2 cho domain này.
");
}

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

set_time_limit(0);
ignore_user_abort(true);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_installed()) {
    exit("Chưa cài đặt ứng dụng.\n");
}

$lock = fopen(sys_get_temp_dir() . '/seo-tool-migrate.lock', 'c');
if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
    db()->migrate();
    flock($lock, LOCK_UN);
}

(new App\Worker())->run();
