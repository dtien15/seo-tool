<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Xin chào, <?= e(\App\Auth::user()['name']) ?> 👋</h1>
    <a href="<?= url('/projects/new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tạo dự án</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat card"><div class="card-body">
            <div class="stat-label">Dự án</div><div class="stat-value"><?= count($projects) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat card"><div class="card-body">
            <div class="stat-label">Chờ duyệt</div><div class="stat-value text-warning"><?= (int)($statusCounts['review'] ?? 0) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat card"><div class="card-body">
            <div class="stat-label">Đã đăng tháng này</div><div class="stat-value text-success"><?= $publishedThisMonth ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat card"><div class="card-body">
            <div class="stat-label">Chi phí AI của bạn (tháng)</div>
            <div class="stat-value"><?= money($spent) ?><?php if ($budget !== null): ?><small class="text-muted fs-6"> / <?= money($budget) ?></small><?php endif; ?></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white"><strong>Bài viết đang xử lý</strong></div>
            <div class="list-group list-group-flush">
                <?php foreach ($myArticles as $a): ?>
                    <a href="<?= url('/articles/' . $a['id']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                        <div class="text-truncate">
                            <div class="fw-medium text-truncate"><?= e($a['title'] ?: $a['keyword']) ?></div>
                            <small class="text-muted"><?= e($a['project_name']) ?> · <?= time_ago($a['updated_at']) ?></small>
                        </div>
                        <div class="text-nowrap"><?= ai_state_badge($a) ?> <?= status_badge($a['status']) ?></div>
                    </a>
                <?php endforeach; ?>
                <?php if (!$myArticles): ?>
                    <div class="list-group-item text-muted">Chưa có bài viết nào. Vào một dự án và bấm "Thêm bài viết".</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between"><strong>Dự án</strong><a href="<?= url('/projects') ?>" class="small">Xem tất cả</a></div>
            <div class="list-group list-group-flush">
                <?php foreach (array_slice($projects, 0, 8) as $p): ?>
                    <a href="<?= url('/projects/' . $p['id']) ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between">
                            <span class="fw-medium"><?= e($p['name']) ?></span>
                            <small class="text-muted"><?= (int)$p['published_count'] ?>/<?= (int)$p['article_count'] ?> đã đăng</small>
                        </div>
                        <small class="text-muted"><?= e($p['domain'] ?: '—') ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$projects): ?>
                    <div class="list-group-item text-muted">Bạn chưa có dự án nào.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
