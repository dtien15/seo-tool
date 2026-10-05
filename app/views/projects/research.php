<?php
use App\Controllers\ResearchController;

require __DIR__ . '/_tabs.php';
?>
<p class="text-muted small">Bước 1 của quy trình: tìm hiểu thông tin dự án. Đánh dấu "Hoàn thành" khi xong mỗi mục. Phân tích đối thủ nằm ở tab <a href="<?= url('/projects/' . $project['id'] . '/competitors') ?>">Đối thủ</a>.</p>

<div class="row g-4">
    <?php foreach (ResearchController::SECTIONS as $key => [$title, $hint]): $sec = $sections[$key] ?? null; ?>
        <div class="col-xl-6" id="<?= $key ?>">
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/research') ?>" class="card h-100">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="<?= $key ?>">
                <div class="card-header bg-white d-flex align-items-center">
                    <strong><?= e($title) ?></strong>
                    <?php if (!empty($sec['is_done'])): ?><span class="badge text-bg-success ms-2">Hoàn thành</span><?php endif; ?>
                    <?php if ($sec): ?><small class="text-muted ms-auto">Cập nhật <?= e(time_ago($sec['updated_at'])) ?></small><?php endif; ?>
                </div>
                <div class="card-body">
                    <textarea name="content" class="form-control" rows="9" placeholder="<?= e($hint) ?>" <?= $canEdit ? '' : 'readonly' ?>><?= e($sec['content'] ?? '') ?></textarea>
                </div>
                <?php if ($canEdit): ?>
                    <div class="card-footer bg-white d-flex align-items-center gap-3">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="is_done" value="1" id="done-<?= $key ?>" <?= !empty($sec['is_done']) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="done-<?= $key ?>">Hoàn thành</label>
                        </div>
                        <button class="btn btn-sm btn-primary ms-auto">Lưu</button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    <?php endforeach; ?>
</div>
