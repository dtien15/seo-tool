<?php require __DIR__ . '/_tabs.php'; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-signpost-split"></i> Quy trình triển khai</strong></div>
            <ol class="list-group list-group-flush process-steps">
                <?php foreach ($steps as $i => [$title, $detail, $done, $href]): ?>
                    <a href="<?= url($href) ?>" class="list-group-item list-group-item-action d-flex gap-3 align-items-start">
                        <span class="step-dot <?= $done ? 'done' : '' ?>"><?= $done ? '<i class="bi bi-check-lg"></i>' : $i + 1 ?></span>
                        <span class="flex-grow-1">
                            <span class="fw-medium d-block"><?= e($title) ?></span>
                            <small class="text-muted"><?= e($detail) ?></small>
                        </span>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Tiến độ bài viết</strong></div>
            <ul class="list-group list-group-flush small">
                <?php foreach (article_statuses() as $key => [$label, $color]): if (empty($counts[$key])) continue; ?>
                    <a href="<?= url('/projects/' . $project['id']) ?>?status=<?= $key ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
                        <span><?= status_badge($key) ?></span><strong><?= (int)$counts[$key] ?></strong>
                    </a>
                <?php endforeach; ?>
                <?php if (!$counts): ?><li class="list-group-item text-muted">Chưa có bài nào trong kế hoạch.</li><?php endif; ?>
            </ul>
        </div>
        <?php if ($due): ?>
            <div class="alert alert-warning">
                <i class="bi bi-arrow-repeat"></i> Có <b><?= $due ?></b> bài đã đăng quá <?= (int)$project['reoptimize_days'] ?> ngày cần kiểm tra và tối ưu lại.
                <a href="<?= url('/projects/' . $project['id']) ?>?review=due" class="alert-link">Xem danh sách</a>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body small text-muted">
                <b>Luồng một bài viết:</b><br>
                Kế hoạch → SEO làm outline → <b>TP duyệt outline</b> → Content viết (AI viết nháp, Content sửa) →
                <b>SEO + TP duyệt bài</b> → Design làm hình → <b>SEO + TP duyệt hình</b> → SEO đăng & tối ưu →
                kiểm tra lại mỗi <?= (int)$project['reoptimize_days'] ?> ngày.
            </div>
        </div>
    </div>
</div>
