<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Dự án</h1>
    <?php if (\App\Auth::is('seo', 'leader')): ?><a href="<?= url('/projects/new') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Tạo dự án</a><?php endif; ?>
</div>
<?php $isWorker = in_array(\App\Auth::role(), ['content', 'design'], true); ?>

<div class="row g-3">
    <?php foreach ($projects as $p): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= url('/projects/' . $p['id'] . ($isWorker ? '' : '/overview')) ?>" class="card project-card h-100 text-decoration-none text-body">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h2 class="h6 mb-1"><?= e($p['name']) ?></h2>
                        <?php if ($p['wp_url']): ?><span class="badge text-bg-light border"><i class="bi bi-wordpress"></i> WP</span><?php endif; ?>
                    </div>
                    <div class="small text-muted mb-3"><?= e($p['domain'] ?: 'Chưa có domain') ?> · <?= e($p['owner_name']) ?></div>
                    <div class="d-flex gap-3 small">
                        <span><i class="bi bi-file-text"></i> <?= (int)$p['article_count'] ?> bài</span>
                        <span class="text-success"><i class="bi bi-check2-circle"></i> <?= (int)$p['published_count'] ?> đã đăng</span>
                        <span class="text-warning"><i class="bi bi-hourglass"></i> <?= (int)$p['review_count'] ?> chờ duyệt</span>
                        <span><i class="bi bi-link-45deg"></i> <?= (int)$p['link_count'] ?></span>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
    <?php if (!$projects): ?>
        <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">
            Chưa có dự án nào. <a href="<?= url('/projects/new') ?>">Tạo dự án đầu tiên</a>.
        </div></div></div>
    <?php endif; ?>
</div>
