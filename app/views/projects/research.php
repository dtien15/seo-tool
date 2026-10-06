<?php
use App\Controllers\ResearchController;

require __DIR__ . '/_tabs.php';
?>
<p class="text-muted small">Bước 1 của quy trình: tìm hiểu thông tin dự án. Tự viết, hoặc bấm <b>"AI viết nháp"</b> để AI đọc website và soạn nháp (nếu ô đã có nội dung, AI nối thêm phía dưới, không xóa phần bạn viết). Đánh dấu "Hoàn thành" khi xong mỗi mục. Phân tích đối thủ nằm ở tab <a href="<?= url('/projects/' . $project['id'] . '/competitors') ?>">Đối thủ</a>.</p>

<div class="row g-4">
    <?php foreach (ResearchController::SECTIONS as $key => [$title, $hint]): $sec = $sections[$key] ?? null; ?>
        <div class="col-xl-6" id="<?= $key ?>">
            <div class="card h-100">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                    <strong><?= e($title) ?></strong>
                    <?php if (!empty($sec['is_done'])): ?><span class="badge text-bg-success">Hoàn thành</span><?php endif; ?>
                    <?php if ($sec): ?><small class="text-muted"><?= e(time_ago($sec['updated_at'])) ?></small><?php endif; ?>
                    <?php if ($canEdit): ?>
                        <span class="ms-auto"><?= ai_button((int)$project['id'], 'ai_research', 'AI viết nháp', ['section' => $key], $aiPending, 'ai_research:' . $key) ?></span>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= url('/projects/' . $project['id'] . '/research') ?>" class="d-flex flex-column flex-grow-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="section" value="<?= $key ?>">
                    <div class="card-body flex-grow-1">
                        <textarea name="content" class="form-control" rows="10" placeholder="<?= e($hint) ?>" <?= $canEdit ? '' : 'readonly' ?>><?= e($sec['content'] ?? '') ?></textarea>
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
        </div>
    <?php endforeach; ?>
</div>
