<?php
/**
 * Worker xử lý hàng đợi. Cài cron trên cPanel chạy MỖI PHÚT:
 *   * * * * * /usr/local/bin/php /home/USER/public_html/cron/worker.php >/dev/null 2>&1
 */
declare(strict_types=1);

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
