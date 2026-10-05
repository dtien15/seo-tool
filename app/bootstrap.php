<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

date_default_timezone_set('Asia/Ho_Chi_Minh');
mb_internal_encoding('UTF-8');

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

$GLOBALS['__config'] = is_file(BASE_PATH . '/config.php') ? require BASE_PATH . '/config.php' : null;

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
