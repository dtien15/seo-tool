<?php
declare(strict_types=1);

use App\Database;

function config(string $key, mixed $default = null): mixed
{
    $cfg = $GLOBALS['__config'] ?? null;
    return is_array($cfg) && array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

function is_installed(): bool
{
    return is_array($GLOBALS['__config'] ?? null);
}

function db(): Database
{
    return Database::instance();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Đường dẫn gốc của ứng dụng (hỗ trợ cài trong thư mục con). */
function base_path_url(): string
{
    $appUrl = (string)config('app_url', '');
    if ($appUrl !== '') {
        return rtrim((string)(parse_url($appUrl, PHP_URL_PATH) ?? ''), '/');
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/');
}

function url(string $path = '/'): string
{
    return base_path_url() . '/' . ltrim($path, '/');
}

function absolute_url(string $path = '/'): string
{
    $appUrl = rtrim((string)config('app_url', ''), '/');
    return $appUrl . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function back(): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect('/');
}

function flash(string $type, string $message): void
{
    $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $items;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['__old'][$key] ?? $default;
}

function csrf_token(): string
{
    if (empty($_SESSION['__csrf'])) {
        $_SESSION['__csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['__csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort(419, 'Phiên làm việc hết hạn, vui lòng tải lại trang và thử lại.');
    }
}

function input(string $key, mixed $default = null): mixed
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, ?int $default = null): ?int
{
    $v = input($key);
    return ($v === null || $v === '') ? $default : (int)$v;
}

function view(string $name, array $data = [], ?string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require BASE_PATH . '/app/views/' . $name . '.php';
    $content = ob_get_clean();
    if ($layout === null) {
        echo $content;
        return;
    }
    require BASE_PATH . '/app/views/' . $layout . '.php';
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    if (wants_json()) {
        json_response(['ok' => false, 'error' => $message ?: 'Lỗi ' . $code], $code);
    }
    $titles = [403 => 'Không có quyền truy cập', 404 => 'Không tìm thấy trang', 419 => 'Phiên hết hạn'];
    try {
        $loggedIn = is_installed() && \App\Auth::user() !== null;
    } catch (\Throwable) {
        $loggedIn = false;
    }
    view('error', ['code' => $code, 'title' => $titles[$code] ?? 'Có lỗi xảy ra', 'message' => $message], $loggedIn ? 'layout' : 'layout_guest');
    exit;
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function random_token(int $bytes = 24): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Trạng thái bài viết theo quy trình:
 * Kế hoạch → Outline → TP duyệt outline → Viết bài → SEO + TP duyệt bài → Làm hình → SEO + TP duyệt hình → Sẵn sàng đăng → Đã đăng
 * key => [nhãn hiển thị (cũng dùng trên Google Sheet), màu badge]
 */
function article_statuses(): array
{
    return [
        'plan'           => ['Kế hoạch', 'secondary'],
        'outline'        => ['Đang làm outline', 'info'],
        'outline_review' => ['Chờ TP duyệt outline', 'warning'],
        'writing'        => ['Đang viết', 'info'],
        'content_review' => ['Chờ duyệt bài', 'warning'],
        'revise'         => ['Cần sửa bài', 'danger'],
        'design'         => ['Đang làm hình', 'info'],
        'image_review'   => ['Chờ duyệt hình', 'warning'],
        'image_revise'   => ['Cần sửa hình', 'danger'],
        'ready'          => ['Sẵn sàng đăng', 'primary'],
        'wp_draft'       => ['Nháp trên WP', 'dark'],
        'published'      => ['Đã đăng', 'success'],
    ];
}

/** Vai trò tài khoản: key => [tên, mô tả] */
function user_roles(): array
{
    return [
        'admin'   => ['Quản trị viên', 'Toàn quyền, cài đặt hệ thống'],
        'leader'  => ['Trưởng phòng', 'Xem mọi dự án, duyệt outline / bài / hình'],
        'seo'     => ['SEO', 'Quản lý dự án, nghiên cứu, kế hoạch, duyệt phía SEO, đăng bài'],
        'content' => ['Content', 'Viết / sửa bài được giao'],
        'design'  => ['Design', 'Làm hình cho bài được giao'],
    ];
}

function role_label(?string $role): string
{
    return user_roles()[$role ?? ''][0] ?? (string)$role;
}

function status_label(string $status): string
{
    return article_statuses()[$status][0] ?? $status;
}

function status_badge(string $status): string
{
    [$label, $color] = article_statuses()[$status] ?? [$status, 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . e($label) . '</span>';
}

function status_from_label(string $label): ?string
{
    $needle = mb_strtolower(trim($label));
    foreach (article_statuses() as $key => [$text]) {
        if (mb_strtolower($text) === $needle || $key === $needle) {
            return $key;
        }
    }
    return null;
}

function ai_state_badge(array $article): string
{
    $tasks = ['outline' => 'outline', 'write' => 'viết bài', 'images' => 'tạo ảnh', 'publish' => 'đăng WP', 'image' => 'tạo lại ảnh'];
    $task = $tasks[$article['ai_task'] ?? ''] ?? '';
    return match ($article['ai_state'] ?? 'idle') {
        'queued'  => '<span class="badge rounded-pill text-bg-light border"><i class="bi bi-hourglass-split"></i> Chờ ' . e($task) . '</span>',
        'running' => '<span class="badge rounded-pill text-bg-info"><span class="spinner-border spinner-border-sm"></span> Đang ' . e($task) . '</span>',
        'failed'  => '<span class="badge rounded-pill text-bg-danger" title="' . e($article['ai_error'] ?? '') . '"><i class="bi bi-exclamation-triangle"></i> Lỗi ' . e($task) . '</span>',
        default   => '',
    };
}

function money(float|string|null $usd): string
{
    return '$' . number_format((float)$usd, 2);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    return match (true) {
        $diff < 60 => 'vừa xong',
        $diff < 3600 => floor($diff / 60) . ' phút trước',
        $diff < 86400 => floor($diff / 3600) . ' giờ trước',
        $diff < 86400 * 30 => floor($diff / 86400) . ' ngày trước',
        default => date('d/m/Y', strtotime($datetime)),
    };
}

/** Bỏ dấu tiếng Việt, chữ thường – dùng để so khớp từ khóa và tạo slug. */
function vn_normalize(string $text): string
{
    $text = mb_strtolower($text);
    $map = [
        'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
        'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
    ];
    foreach ($map as $plain => $chars) {
        $text = str_replace(mb_str_split($chars), $plain, $text);
    }
    return $text;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^a-z0-9]+~', '-', vn_normalize($text)) ?? '';
    return trim($text, '-');
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function query_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    unset($q['__route']);
    return '?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
}

/** Gọi HTTP đơn giản bằng cURL. */
function http_request(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = [];
    foreach ($opts['headers'] ?? [] as $k => $v) {
        $headers[] = is_int($k) ? $v : "$k: $v";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => $opts['follow'] ?? true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $opts['timeout'] ?? 60,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => $opts['user_agent'] ?? 'Mozilla/5.0 (compatible; SEOToolBot/1.0)',
        CURLOPT_ENCODING => '',
    ]);
    if (array_key_exists('body', $opts) && $opts['body'] !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return [
        'status' => $status,
        'body' => $body === false ? '' : (string)$body,
        'error' => $error,
        'json' => is_string($body) ? json_decode($body, true) : null,
    ];
}

function log_error(string $message): void
{
    $dir = BASE_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/app-' . date('Y-m') . '.log', '[' . now() . '] ' . $message . PHP_EOL, FILE_APPEND);
}
