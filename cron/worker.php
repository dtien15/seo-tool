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
App\Worker::log('Cron khởi động');

if (!is_installed()) {
    exit("Chưa cài đặt ứng dụng.\n");
}

$lock = fopen(sys_get_temp_dir() . '/seo-tool-migrate.lock', 'c');
if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
    db()->migrate();
    flock($lock, LOCK_UN);
}

// Ghi lại thông tin lần chạy để xem ở Cài đặt hệ thống (phiên bản PHP, extension thiếu...)
$missing = array_values(array_filter(['curl', 'mbstring', 'openssl', 'json', 'pdo_mysql'], fn($x) => !extension_loaded($x)));
App\Settings::set('cron_last_run', json_encode([
    'at' => date('Y-m-d H:i:s'),
    'php' => PHP_VERSION,
    'binary' => PHP_BINARY,
    'memory' => ini_get('memory_limit'),
    'missing' => $missing,
], JSON_UNESCAPED_UNICODE));

try {
    (new App\Worker())->run();
} catch (\Throwable $e) {
    App\Worker::log('LỖI worker: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}
App\Worker::log('Cron kết thúc');
