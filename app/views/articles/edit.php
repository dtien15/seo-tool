<?php
$a = $article;
$busy = in_array($a['ai_state'], ['queued', 'running'], true);
$plain = trim(strip_tags((string)$a['content']));
$wordCount = $plain === '' ? 0 : count(preg_split('~\s+~u', $plain));
$kwNorm = vn_normalize($a['keyword']);
$siteHost = explode('/', (string)preg_replace('~^(https?://)?(www\.)?~i', '', (string)($project['domain'] ?: $project['wp_url'])))[0];
$internalLinks = $siteHost !== ''
    ? preg_match_all('~<a\s[^>]*href=["\'][^"\']*' . preg_quote($siteHost, '~') . '~i', (string)$a['content'])
    : preg_match_all('~<a\s~i', (string)$a['content']);
$checks = [
    ['Tiêu đề chứa từ khóa chính', $a['title'] && str_contains(vn_normalize($a['title']), $kwNorm)],
    ['Meta title ≤ 60 ký tự (' . mb_strlen((string)$a['meta_title']) . ')', $a['meta_title'] && mb_strlen($a['meta_title']) <= 60],
    ['Meta description 120-160 ký tự (' . mb_strlen((string)$a['meta_description']) . ')', mb_strlen((string)$a['meta_description']) >= 120 && mb_strlen((string)$a['meta_description']) <= 160],
    ['Từ khóa trong đoạn mở đầu', $plain !== '' && str_contains(vn_normalize(mb_substr($plain, 0, 400)), $kwNorm)],
    ['Có link nội bộ (' . $internalLinks . ')', $internalLinks >= 2],
    ['Độ dài ' . $wordCount . ' từ', $wordCount >= 0.8 * (int)($a['word_count'] ?: $project['word_count'])],
    ['Có ảnh đại diện', (bool)array_filter($images, fn($i) => (int)$i['position'] === 0 && $i['file_path'])],
];
$base = '/articles/' . $a['id'];
?>
<?php require BASE_PATH . '/app/views/projects/_tabs.php'; ?>

<form method="post" action="<?= url($base . '/task') ?>" id="task-form"><?= csrf_field() ?></form>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= url('/projects/' . $project['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h5 mb-0 me-2"><?= e($a['keyword']) ?></h2>
    <?= status_badge($a['status']) ?>
    <span id="ai-state" data-state-url="<?= url($base . '/state') ?>" data-busy="<?= $busy ? '1' : '0' ?>"><?= ai_state_badge($a) ?></span>
    <?php if ($a['wp_url']): ?><a href="<?= e($a['wp_url']) ?>" target="_blank" rel="noopener" class="small ms-1"><i class="bi bi-box-arrow-up-right"></i> Xem trên WP</a><?php endif; ?>
    <div class="ms-auto d-flex gap-2">
        <a href="<?= url($base . '/preview') ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Xem trước</a>
    </div>
</div>

<?php if ($a['ai_state'] === 'failed' && $a['ai_error']): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= e($a['ai_error']) ?></div>
<?php endif; ?>
<?php if ($busy): ?>
    <div class="alert alert-info py-2"><span class="spinner-border spinner-border-sm"></span> AI đang xử lý, trang sẽ tự tải lại khi xong (thường 1-3 phút). Bạn có thể rời trang này.</div>
<?php endif; ?>

<form method="post" action="<?= url($base) ?>" id="main-form">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề (H1)</label>
                        <input name="title" class="form-control form-control-lg" value="<?= e($a['title']) ?>" data-counter="65">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Slug</label><input name="slug" class="form-control" value="<?= e($a['slug']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">Meta title</label><input name="meta_title" class="form-control" value="<?= e($a['meta_title']) ?>" data-counter="60"></div>
                        <div class="col-12"><label class="form-label">Meta description</label><textarea name="meta_description" class="form-control" rows="2" data-counter="160"><?= e($a['meta_description']) ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center">
                    <a class="text-decoration-none text-body fw-semibold" data-bs-toggle="collapse" href="#outline-box"><i class="bi bi-list-nested"></i> Outline</a>
                    <button class="btn btn-sm btn-outline-primary ms-auto" form="task-form" name="task" value="outline" <?= $busy || !$hasClaude ? 'disabled' : '' ?>><i class="bi bi-stars"></i> Tạo outline bằng AI</button>
                </div>
                <div class="collapse <?= $a['outline'] && !$a['content'] ? 'show' : '' ?>" id="outline-box">
                    <div class="card-body">
                        <textarea name="outline" class="form-control font-monospace small" rows="12" placeholder="Có thể tự viết outline; AI sẽ bám theo outline này khi viết bài."><?= e($a['outline']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center">
                    <strong><i class="bi bi-file-richtext"></i> Nội dung</strong>
                    <span class="ms-2 small text-muted"><?= $wordCount ?> từ</span>
                    <button class="btn btn-sm btn-primary ms-auto" form="task-form" name="task" value="write" <?= $busy || !$hasClaude ? 'disabled' : '' ?>
                        <?= $a['content'] ? 'data-confirm="Viết lại sẽ thay thế toàn bộ nội dung hiện tại. Tiếp tục?"' : '' ?>>
                        <i class="bi bi-stars"></i> <?= $a['content'] ? 'Viết lại bằng AI' : 'Viết bài bằng AI' ?>
                    </button>
                </div>
                <div class="card-body p-0">
                    <textarea name="content" id="content-editor" rows="25" class="form-control border-0"><?= e($a['content']) ?></textarea>
                </div>
            </div>
            <?php if (!$hasClaude): ?><div class="alert alert-warning small">Chưa có Claude API key – nhờ quản trị viên nhập trong Cài đặt hệ thống.</div><?php endif; ?>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3 sticky-xl-top" style="top:1rem">
                <div class="card-body">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select mb-3">
                        <?php foreach (article_statuses() as $key => [$label]): ?>
                            <option value="<?= $key ?>" <?= $a['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary"><i class="bi bi-save"></i> Lưu</button>
                        <button class="btn btn-success" name="then" value="publish" <?= $busy || !$project['wp_url'] ? 'disabled' : '' ?>
                                data-confirm="Lưu và <?= $a['wp_post_id'] ? 'cập nhật bài trên' : 'đăng lên' ?> WordPress (<?= e(['draft' => 'nháp', 'publish' => 'đăng ngay', 'pending' => 'chờ duyệt'][$project['wp_default_status']] ?? $project['wp_default_status']) ?>)?">
                            <i class="bi bi-wordpress"></i> <?= $a['wp_post_id'] ? 'Lưu & cập nhật lên WP' : 'Lưu & đăng lên WP' ?>
                        </button>
                    </div>
                    <?php if (!$project['wp_url']): ?><div class="form-text">Chưa kết nối WordPress cho dự án.</div><?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Kiểm tra SEO nhanh</strong></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($checks as [$label, $ok]): ?>
                        <li class="list-group-item"><i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?>"></i> <?= e($label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Thông tin bài</strong></div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small">Từ khóa chính</label><input name="keyword" class="form-control form-control-sm" value="<?= e($a['keyword']) ?>" required></div>
                    <div class="mb-2"><label class="form-label small">Từ khóa phụ</label><textarea name="secondary_keywords" class="form-control form-control-sm" rows="2"><?= e($a['secondary_keywords']) ?></textarea></div>
                    <div class="mb-2"><label class="form-label small">Ghi chú cho AI</label><textarea name="notes" class="form-control form-control-sm" rows="3"><?= e($a['notes']) ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Số từ</label><input type="number" name="word_count" class="form-control form-control-sm" value="<?= e($a['word_count']) ?>" placeholder="<?= (int)$project['word_count'] ?>"></div>
                        <div class="col-6">
                            <label class="form-label small">Phụ trách</label>
                            <select name="assigned_to" class="form-select form-select-sm">
                                <option value="">—</option>
                                <?php foreach ($assignees as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (int)$a['assigned_to'] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Chuyên mục WP</label>
                            <select name="wp_category_id" class="form-select form-select-sm" data-wp-categories="<?= $project['wp_url'] ? url('/projects/' . $project['id'] . '/wp-categories') : '' ?>" data-selected="<?= (int)$a['wp_category_id'] ?>">
                                <option value="">Mặc định của dự án</option>
                                <?php if ($a['wp_category_id']): ?><option value="<?= (int)$a['wp_category_id'] ?>" selected>#<?= (int)$a['wp_category_id'] ?></option><?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">Kho interlink dự án: <?= $linkCount ?> link.</div>
                </div>
            </div>
        </div>
    </div>
</form>

<div class="card mb-4" id="images">
    <div class="card-header bg-white d-flex align-items-center">
        <strong><i class="bi bi-images"></i> Hình ảnh</strong>
        <button class="btn btn-sm btn-outline-primary ms-auto" form="task-form" name="task" value="images" <?= $busy || !$images || !$hasOpenAI ? 'disabled' : '' ?>><i class="bi bi-stars"></i> Tạo lại tất cả ảnh</button>
    </div>
    <div class="card-body">
        <?php if (!$hasOpenAI): ?><div class="alert alert-warning small py-2">Chưa có OpenAI API key nên chưa tạo được ảnh.</div><?php endif; ?>
        <?php if (!$images): ?><p class="text-muted small mb-0">Ảnh sẽ được đề xuất (prompt + alt) khi AI viết bài, sau đó tạo tự động.</p><?php endif; ?>
        <div class="row g-3">
            <?php foreach ($images as $img): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-2 h-100">
                        <div class="img-thumb mb-2">
                            <?php if ($img['file_path']): ?>
                                <a href="<?= url($img['file_path']) ?>" target="_blank"><img src="<?= url($img['file_path']) ?>" alt="<?= e($img['alt_text']) ?>"></a>
                            <?php else: ?>
                                <div class="text-muted small">Chưa có ảnh</div>
                            <?php endif; ?>
                        </div>
                        <div class="small fw-semibold mb-1"><?= (int)$img['position'] === 0 ? '⭐ Ảnh đại diện' : 'Ảnh ' . (int)$img['position'] ?>
                            <?php if ($img['wp_media_id']): ?><span class="badge text-bg-light border">WP #<?= (int)$img['wp_media_id'] ?></span><?php endif; ?></div>
                        <input name="image_alt[<?= $img['id'] ?>]" form="main-form" class="form-control form-control-sm mb-1" value="<?= e($img['alt_text']) ?>" placeholder="Alt text">
                        <form method="post" action="<?= url($base . '/images/' . $img['id']) ?>">
                            <?= csrf_field() ?>
                            <textarea name="prompt" class="form-control form-control-sm mb-1 small" rows="3"><?= e($img['prompt']) ?></textarea>
                            <button class="btn btn-sm btn-light w-100" <?= $busy || !$hasOpenAI ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat"></i> Tạo lại ảnh này</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="text-end mb-4">
    <form method="post" action="<?= url($base . '/delete') ?>" data-confirm="Xóa bài viết này khỏi tool? (Bài trên WordPress không bị xóa)" class="d-inline">
        <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa bài</button>
    </form>
</div>

<?php
$scripts = '<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js"></script>'
    . '<script>SEO.initEditor("#content-editor"); SEO.pollArticle();</script>';
?>
