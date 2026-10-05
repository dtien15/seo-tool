<?php
use App\Auth;

$me = Auth::user();
$current = '/' . trim(substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path_url())), '/');
$nav = [
    ['/', 'bi-speedometer2', 'Tổng quan'],
    ['/projects', 'bi-folder2-open', 'Dự án'],
];
$adminNav = Auth::isAdmin() ? [
    ['/users', 'bi-people', 'Tài khoản'],
    ['/usage', 'bi-graph-up', 'Chi phí AI'],
    ['/jobs', 'bi-list-task', 'Hàng đợi'],
    ['/settings', 'bi-gear', 'Cài đặt hệ thống'],
] : [['/usage', 'bi-graph-up', 'Chi phí AI']];
$isActive = fn(string $href) => $href === '/' ? $current === '/' : str_starts_with($current, $href);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'SEO Tool') ?> · SEO Tool</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
</head>
<body data-base="<?= e(base_path_url()) ?>">
<div class="app">
    <aside class="sidebar d-flex flex-column" id="sidebar">
        <a href="<?= url('/') ?>" class="brand"><i class="bi bi-rocket-takeoff"></i> SEO Tool</a>
        <nav class="nav flex-column">
            <?php foreach ($nav as [$href, $icon, $label]): ?>
                <a class="nav-link <?= $isActive($href) ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if (Auth::is('leader')): ?>
                <div class="nav-heading">Quản trị</div>
                <?php foreach ($adminNav as [$href, $icon, $label]): ?>
                    <a class="nav-link <?= $isActive($href) ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <div class="mt-auto user-box">
            <a href="<?= url('/profile') ?>" class="text-decoration-none d-block text-truncate">
                <i class="bi bi-person-circle"></i> <?= e($me['name'] ?? '') ?>
                <small class="d-block text-white-50"><?= e(role_label($me['role'] ?? '')) ?></small>
            </a>
            <form method="post" action="<?= url('/logout') ?>" class="mt-2">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
            </form>
        </div>
    </aside>
    <main class="main">
        <div class="topbar d-lg-none">
            <button class="btn btn-sm btn-light" onclick="document.getElementById('sidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
            <strong class="ms-2"><?= e($pageTitle ?? '') ?></strong>
        </div>
        <div class="content">
            <?php foreach (flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show">
                    <?= e($f['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('app.js') ?>"></script>
<?= $scripts ?? '' ?>
</body>
</html>
