<?php
declare(strict_types=1);

// Kiểm tra phiên bản PHP trước khi nạp code (code dùng cú pháp PHP 8.1)
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Tool cần PHP 8.1 trở lên, hosting đang chạy PHP ' . PHP_VERSION . ".
Vào cPanel > Select PHP Version (hoặc MultiPHP Manager) để chọn PHP 8.2 cho domain này.
");
}

require __DIR__ . '/app/bootstrap.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path' => base_path_url() . '/',
    'secure' => (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('seotool_session');
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$base = base_path_url();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');
if ($path === '/index.php') {
    $path = '/';
}

// Chưa cài đặt -> chuyển tới trình cài đặt
if (!is_installed()) {
    if ($path !== '/install') {
        redirect('/install');
    }
    (new App\Controllers\InstallController())->handle();
    exit;
}

// Tự chạy migration khi có bản cập nhật mới (sau khi deploy từ Git)
try {
    db()->migrate();
} catch (\Throwable $e) {
    log_error('Migrate: ' . $e->getMessage());
    abort(500, 'Không kết nối được cơ sở dữ liệu. Kiểm tra lại config.php.');
}

// config.php không khai báo APP_URL: ghi nhớ địa chỉ web để cron worker dùng
if ((string)config('app_url', '') === '') {
    app_url();
}

// Tự tạo config.php bằng tay: chưa có tài khoản nào thì tạo admin đầu tiên
if ($path !== '/setup' && !db()->value('SELECT 1 FROM users LIMIT 1')) {
    redirect('/setup');
}

$routes = [
    ['GET',  '/install',                          'InstallController@done'],
    ['GET',  '/setup',                            'InstallController@setupAdmin'],
    ['POST', '/setup',                            'InstallController@setupAdmin'],
    ['GET',  '/login',                            'AuthController@loginForm'],
    ['POST', '/login',                            'AuthController@login'],
    ['POST', '/logout',                           'AuthController@logout'],
    ['GET',  '/profile',                          'AuthController@profile'],
    ['POST', '/profile',                          'AuthController@updateProfile'],

    ['GET',  '/',                                 'DashboardController@index'],

    ['GET',  '/projects',                         'ProjectController@index'],
    ['GET',  '/projects/new',                     'ProjectController@create'],
    ['POST', '/projects',                         'ProjectController@store'],
    ['GET',  '/projects/(\d+)',                   'ProjectController@show'],
    ['GET',  '/projects/(\d+)/settings',          'ProjectController@edit'],
    ['POST', '/projects/(\d+)/settings',          'ProjectController@update'],
    ['POST', '/projects/(\d+)/delete',            'ProjectController@destroy'],
    ['POST', '/projects/(\d+)/members',           'ProjectController@members'],
    ['POST', '/projects/(\d+)/wp-test',           'ProjectController@wpTest'],
    ['GET',  '/projects/(\d+)/wp-categories',     'ProjectController@wpCategories'],

    ['GET',  '/projects/(\d+)/overview',          'ResearchController@overview'],
    ['GET',  '/projects/(\d+)/research',          'ResearchController@research'],
    ['POST', '/projects/(\d+)/research',          'ResearchController@saveSection'],
    ['GET',  '/projects/(\d+)/competitors',       'ResearchController@competitors'],
    ['POST', '/projects/(\d+)/competitors',       'ResearchController@storeCompetitor'],
    ['POST', '/competitors/(\d+)',                'ResearchController@updateCompetitor'],
    ['POST', '/competitors/(\d+)/delete',         'ResearchController@deleteCompetitor'],
    ['POST', '/competitors/(\d+)/metrics',        'ResearchController@saveMetric'],
    ['POST', '/competitors/(\d+)/import',         'ResearchController@importCompetitorKeywords'],
    ['POST', '/competitors/(\d+)/to-keywords',    'ResearchController@competitorToKeywords'],
    ['GET',  '/projects/(\d+)/website',           'ResearchController@website'],
    ['POST', '/projects/(\d+)/website',           'ResearchController@saveWebsite'],
    ['GET',  '/projects/(\d+)/keywords',          'ResearchController@keywords'],
    ['POST', '/projects/(\d+)/keywords',          'ResearchController@storeKeywords'],
    ['POST', '/projects/(\d+)/keywords/bulk',     'ResearchController@bulkKeywords'],
    ['GET',  '/projects/(\d+)/kpi',               'ResearchController@kpi'],
    ['POST', '/projects/(\d+)/kpi',               'ResearchController@saveKpi'],

    ['GET',  '/projects/(\d+)/links',             'LinkController@index'],
    ['POST', '/links/(\d+)/optimize',             'LinkController@optimize'],
    ['POST', '/projects/(\d+)/links',             'LinkController@store'],
    ['POST', '/projects/(\d+)/links/import',      'LinkController@import'],
    ['POST', '/projects/(\d+)/links/clear',       'LinkController@clear'],
    ['POST', '/links/(\d+)/update',               'LinkController@update'],
    ['POST', '/links/(\d+)/delete',               'LinkController@destroy'],

    ['GET',  '/projects/(\d+)/sheet',             'SheetController@show'],
    ['POST', '/projects/(\d+)/sheet',             'SheetController@save'],
    ['POST', '/projects/(\d+)/sheet/init',        'SheetController@init'],
    ['POST', '/projects/(\d+)/sheet/sync',        'SheetController@sync'],
    ['POST', '/api/sheet-webhook',                'SheetController@webhook'],

    ['GET',  '/projects/(\d+)/articles/new',      'ArticleController@create'],
    ['POST', '/projects/(\d+)/articles',          'ArticleController@store'],
    ['POST', '/projects/(\d+)/articles/bulk',     'ArticleController@bulk'],
    ['GET',  '/articles/(\d+)',                   'ArticleController@edit'],
    ['POST', '/articles/(\d+)',                   'ArticleController@update'],
    ['GET',  '/articles/(\d+)/state',             'ArticleController@state'],
    ['POST', '/articles/(\d+)/task',              'ArticleController@task'],
    ['POST', '/articles/(\d+)/status',            'ArticleController@status'],
    ['POST', '/articles/(\d+)/delete',            'ArticleController@destroy'],
    ['POST', '/articles/(\d+)/workflow',          'ArticleController@workflow'],
    ['POST', '/articles/(\d+)/wp-import',         'ArticleController@wpImport'],
    ['POST', '/articles/(\d+)/images/upload',     'ArticleController@uploadImage'],
    ['POST', '/articles/(\d+)/images/(\d+)/delete', 'ArticleController@deleteImage'],
    ['POST', '/articles/(\d+)/images/(\d+)',      'ArticleController@regenerateImage'],
    ['GET',  '/articles/(\d+)/preview',           'ArticleController@preview'],

    ['GET',  '/users',                            'UserController@index'],
    ['POST', '/users',                            'UserController@store'],
    ['POST', '/users/(\d+)',                      'UserController@update'],

    ['GET',  '/settings',                         'SettingsController@index'],
    ['POST', '/settings',                         'SettingsController@save'],
    ['POST', '/settings/test-claude',             'SettingsController@testClaude'],
    ['POST', '/settings/test-google',             'SettingsController@testGoogle'],

    ['GET',  '/usage',                            'SettingsController@usage'],
    ['GET',  '/jobs',                             'SettingsController@jobs'],
    ['POST', '/jobs/(\d+)/retry',                 'SettingsController@retryJob'],
];

foreach ($routes as [$verb, $pattern, $handler]) {
    if ($verb !== $method || !preg_match('~^' . $pattern . '$~', $path, $m)) {
        continue;
    }
    if ($method === 'POST' && $path !== '/api/sheet-webhook') {
        verify_csrf();
    }
    [$class, $action] = explode('@', $handler);
    $class = 'App\\Controllers\\' . $class;
    $args = array_map('intval', array_slice($m, 1));
    try {
        (new $class())->$action(...$args);
    } catch (\PDOException $e) {
        log_error('DB: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        abort(500, config('debug') ? $e->getMessage() : 'Lỗi cơ sở dữ liệu, vui lòng báo quản trị viên.');
    } catch (\RuntimeException $e) {
        // Lỗi nghiệp vụ: báo cho người dùng
        if (wants_json()) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        flash('danger', $e->getMessage());
        back();
    } catch (\Throwable $e) {
        log_error($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        abort(500, config('debug') ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : 'Có lỗi hệ thống, vui lòng thử lại hoặc báo quản trị viên.');
    }
    exit;
}

abort(404, 'Trang bạn tìm không tồn tại.');
