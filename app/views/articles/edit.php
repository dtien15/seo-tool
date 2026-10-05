<?php
use App\Workflow;

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
$ro = fn(bool $can) => $can ? '' : 'readonly';
$dis = fn(bool $can) => $can ? '' : 'disabled';

// Thanh tiến trình
$stages = [
    ['Kế hoạch', ['plan']],
    ['Outline', ['outline']],
    ['TP duyệt outline', ['outline_review']],
    ['Viết bài', ['writing', 'revise']],
    ['SEO + TP duyệt bài', ['content_review']],
    ['Làm hình', ['design', 'image_revise']],
    ['SEO + TP duyệt hình', ['image_review']],
    ['Đăng bài', ['ready']],
    ['Đã đăng', ['wp_draft', 'published']],
];
$currentStage = 0;
foreach ($stages as $i => [, $sts]) {
    if (in_array($a['status'], $sts, true)) {
        $currentStage = $i;
    }
}
$approvalStage = ['outline_review' => 'outline', 'content_review' => 'content', 'image_review' => 'image'][$a['status']] ?? null;
$neededSides = $approvalStage === 'outline' ? ['leader' => 'TP'] : ['seo' => 'SEO', 'leader' => 'TP'];
$showReject = $a['reject_note'] && in_array($a['status'], ['outline', 'revise', 'image_revise', 'writing'], true);
$roleOf = ['admin' => 'Admin', 'leader' => 'TP', 'seo' => 'SEO', 'content' => 'Content', 'design' => 'Design'];
?>
<?php require BASE_PATH . '/app/views/projects/_tabs.php'; ?>

<form method="post" action="<?= url($base . '/task') ?>" id="task-form"><?= csrf_field() ?></form>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="<?= url('/projects/' . $project['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <h2 class="h5 mb-0 me-2"><?= e($a['keyword']) ?></h2>
    <?= status_badge($a['status']) ?>
    <?php if ($a['type'] === 'optimize'): ?><span class="badge text-bg-light border">Tối ưu lại bài cũ</span><?php endif; ?>
    <span id="ai-state" data-state-url="<?= url($base . '/state') ?>" data-busy="<?= $busy ? '1' : '0' ?>"><?= ai_state_badge($a) ?></span>
    <?php if ($a['wp_url']): ?><a href="<?= e($a['wp_url']) ?>" target="_blank" rel="noopener" class="small ms-1"><i class="bi bi-box-arrow-up-right"></i> Xem trên web</a><?php endif; ?>
    <div class="ms-auto"><a href="<?= url($base . '/preview') ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Xem trước</a></div>
</div>

<div class="stage-bar mb-3">
    <?php foreach ($stages as $i => [$label]): ?>
        <div class="stage <?= $i < $currentStage ? 'done' : ($i === $currentStage ? 'current' : '') ?>"><?= e($label) ?></div>
    <?php endforeach; ?>
</div>

<?php if ($a['ai_state'] === 'failed' && $a['ai_error']): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= e($a['ai_error']) ?></div>
<?php endif; ?>
<?php if ($busy): ?>
    <div class="alert alert-info py-2"><span class="spinner-border spinner-border-sm"></span> AI đang xử lý, trang sẽ tự tải lại khi xong (thường 1-3 phút). Bạn có thể rời trang này.</div>
<?php endif; ?>
<?php if ($showReject): ?>
    <div class="alert alert-danger"><b><i class="bi bi-chat-left-text"></i> Yêu cầu sửa:</b><br><?= nl2br(e($a['reject_note'])) ?></div>
<?php endif; ?>

<?php if ($buttons || $approvalStage): ?>
    <div class="card mb-3 border-primary-subtle workflow-card">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <?php if ($approvalStage): ?>
                <span class="small me-2">Duyệt:
                    <?php foreach ($neededSides as $side => $label): $who = $approvals[$approvalStage][$side] ?? null; ?>
                        <span class="badge <?= $who ? 'text-bg-success' : 'text-bg-light border' ?>"><?= $label ?>: <?= $who ? '✓ ' . e($who) : 'chờ duyệt' ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
            <?php foreach ($buttons as $b): ?>
                <?php if ($b['needs_note']): ?>
                    <button type="button" class="btn btn-sm btn-<?= $b['color'] ?>" data-bs-toggle="collapse" data-bs-target="#note-<?= $b['action'] ?>"><?= e($b['label']) ?></button>
                <?php else: ?>
                    <form method="post" action="<?= url($base . '/workflow') ?>" class="d-inline workflow-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $b['action'] ?>">
                        <?php if (!empty($b['side'])): ?><input type="hidden" name="side" value="<?= $b['side'] ?>"><?php endif; ?>
                        <button class="btn btn-sm btn-<?= $b['color'] ?>" <?= $busy ? 'disabled' : '' ?>><?= e($b['label']) ?></button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (!$buttons): ?><span class="small text-muted">Đang chờ người khác xử lý.</span><?php endif; ?>
        </div>
        <?php foreach ($buttons as $b): if (!$b['needs_note']) continue; ?>
            <div class="collapse" id="note-<?= $b['action'] ?>">
                <form method="post" action="<?= url($base . '/workflow') ?>" class="card-footer bg-white workflow-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $b['action'] ?>">
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="3" required placeholder="<?= $b['action'] === 'reoptimize' ? 'Cần tối ưu gì (cập nhật số liệu, thêm mục, đổi từ khóa...)' : 'Ghi rõ những điểm cần sửa' ?>"></textarea>
                    <button class="btn btn-sm btn-<?= str_replace('outline-', '', $b['color']) ?>"><?= e($b['label']) ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= url($base) ?>" id="main-form">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center">
                    <a class="text-decoration-none text-body fw-semibold" data-bs-toggle="collapse" href="#outline-box"><i class="bi bi-list-nested"></i> Outline</a>
                    <?php if (\App\Auth::is('seo', 'leader') && in_array($a['status'], ['plan', 'outline'], true)): ?>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" <?= $busy ? 'disabled' : '' ?>
                                data-manual-ai="<?= url($base . '/prompt?task=outline') ?>" data-paste-url="<?= url($base . '/paste') ?>" data-task="outline" data-title="Tạo outline với ChatGPT / Claude"><i class="bi bi-clipboard"></i> Copy prompt</button>
                            <?php if (!$aiManual): ?>
                                <button class="btn btn-sm btn-outline-primary" form="task-form" name="task" value="outline" <?= $busy || !$hasAi ? 'disabled' : '' ?>
                                    <?= $a['outline'] ? 'data-confirm="Tạo lại outline sẽ thay outline hiện tại. Tiếp tục?"' : '' ?>><i class="bi bi-stars"></i> AI tạo outline</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="collapse <?= in_array($a['status'], ['plan', 'outline', 'outline_review', 'writing'], true) ? 'show' : '' ?>" id="outline-box">
                    <div class="card-body">
                        <textarea name="outline" class="form-control font-monospace small" rows="12" <?= $ro($perm['outline']) ?> placeholder="SEO lên outline (hoặc bấm AI tạo outline), sau đó gửi TP duyệt. AI viết bài sẽ bám theo outline này."><?= e($a['outline']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề (H1)</label>
                        <input name="title" class="form-control form-control-lg" value="<?= e($a['title']) ?>" data-counter="65" <?= $ro($perm['content']) ?>>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Slug</label><input name="slug" class="form-control" value="<?= e($a['slug']) ?>" <?= $ro($perm['content']) ?>></div>
                        <div class="col-md-6"><label class="form-label">Meta title</label><input name="meta_title" class="form-control" value="<?= e($a['meta_title']) ?>" data-counter="60" <?= $ro($perm['content']) ?>></div>
                        <div class="col-12"><label class="form-label">Meta description</label><textarea name="meta_description" class="form-control" rows="2" data-counter="160" <?= $ro($perm['content']) ?>><?= e($a['meta_description']) ?></textarea></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                    <strong><i class="bi bi-file-richtext"></i> Nội dung</strong>
                    <span class="small text-muted"><?= $wordCount ?> từ</span>
                    <div class="ms-auto d-flex gap-2">
                        <?php if ($a['type'] === 'optimize' && $a['wp_url'] && $perm['brief'] && $project['wp_url']): ?>
                            <button class="btn btn-sm btn-outline-secondary" form="wp-import-form" <?= $a['content'] ? 'data-confirm="Thay nội dung hiện tại bằng nội dung đang có trên WordPress?"' : '' ?>><i class="bi bi-cloud-download"></i> Lấy nội dung từ WordPress</button>
                        <?php endif; ?>
                        <?php if (in_array($a['status'], ['writing', 'revise'], true) && $perm['content']): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" <?= $busy ? 'disabled' : '' ?>
                                data-manual-ai="<?= url($base . '/prompt?task=write') ?>" data-paste-url="<?= url($base . '/paste') ?>" data-task="write" data-title="Viết bài với ChatGPT / Claude"
                                <?= $a['content'] ? 'data-confirm="Kết quả dán vào sẽ thay toàn bộ nội dung hiện tại. Tiếp tục?"' : '' ?>><i class="bi bi-clipboard"></i> Copy prompt</button>
                            <?php if (!$aiManual): ?>
                                <button class="btn btn-sm btn-primary" form="task-form" name="task" value="write" <?= $busy || !$hasAi ? 'disabled' : '' ?>
                                    <?= $a['content'] ? 'data-confirm="AI viết lại sẽ thay toàn bộ nội dung hiện tại. Tiếp tục?"' : '' ?>>
                                    <i class="bi bi-stars"></i> <?= $a['content'] ? 'AI viết lại' : 'AI viết bản nháp' ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <textarea name="content" id="content-editor" rows="25" class="form-control border-0" data-readonly="<?= $perm['content'] ? '0' : '1' ?>"><?= e($a['content']) ?></textarea>
                </div>
            </div>
            <?php if (in_array($a['status'], ['plan', 'outline', 'outline_review'], true)): ?>
                <div class="alert alert-light border small">Viết bài bắt đầu sau khi <b>Trưởng phòng duyệt outline</b>.</div>
            <?php endif; ?>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-body">
                    <?php if ($perm['force_status']): ?>
                        <label class="form-label small">Trạng thái <span class="text-muted">(đổi thủ công – chỉ TP/Admin)</span></label>
                        <select name="status" class="form-select form-select-sm mb-3">
                            <?php foreach (article_statuses() as $key => [$label]): ?>
                                <option value="<?= $key ?>" <?= $a['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <div class="d-grid gap-2">
                        <?php if ($perm['brief'] || $perm['content'] || $perm['images']): ?>
                            <button class="btn btn-primary"><i class="bi bi-save"></i> Lưu</button>
                        <?php endif; ?>
                        <?php if ($perm['publish']): ?>
                            <button class="btn btn-success" name="then" value="publish" <?= $busy || !$project['wp_url'] ? 'disabled' : '' ?>
                                    data-confirm="Lưu và <?= $a['wp_post_id'] ? 'cập nhật bài trên' : 'đăng lên' ?> WordPress (<?= e(['draft' => 'nháp', 'publish' => 'đăng ngay', 'pending' => 'chờ duyệt'][$project['wp_default_status']] ?? $project['wp_default_status']) ?>)?">
                                <i class="bi bi-wordpress"></i> <?= $a['wp_post_id'] ? 'Lưu & cập nhật lên WP' : 'Lưu & đăng lên WP' ?>
                            </button>
                            <?php if (!$project['wp_url']): ?><div class="form-text">Chưa kết nối WordPress cho dự án.</div><?php endif; ?>
                        <?php endif; ?>
                    </div>
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
                <div class="card-header bg-white"><strong>Brief & phân công</strong></div>
                <div class="card-body">
                    <div class="mb-2"><label class="form-label small">Từ khóa chính</label><input name="keyword" class="form-control form-control-sm" value="<?= e($a['keyword']) ?>" <?= $ro($perm['brief']) ?> required></div>
                    <div class="mb-2"><label class="form-label small">Từ khóa phụ</label><textarea name="secondary_keywords" class="form-control form-control-sm" rows="2" <?= $ro($perm['brief']) ?>><?= e($a['secondary_keywords']) ?></textarea></div>
                    <div class="mb-2"><label class="form-label small">Ghi chú / brief</label><textarea name="notes" class="form-control form-control-sm" rows="3" <?= $ro($perm['brief']) ?>><?= e($a['notes']) ?></textarea></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Nhóm chủ đề</label><input name="cluster" class="form-control form-control-sm" value="<?= e($a['cluster']) ?>" <?= $ro($perm['brief']) ?>></div>
                        <div class="col-6"><label class="form-label small">Dự kiến đăng</label><input type="date" name="planned_date" class="form-control form-control-sm" value="<?= e($a['planned_date']) ?>" <?= $ro($perm['brief']) ?>></div>
                        <div class="col-6"><label class="form-label small">Số từ</label><input type="number" name="word_count" class="form-control form-control-sm" value="<?= e($a['word_count']) ?>" placeholder="<?= (int)$project['word_count'] ?>" <?= $ro($perm['brief']) ?>></div>
                        <?php foreach (['assigned_to' => ['SEO', $assignees], 'writer_id' => ['Content', $writers], 'designer_id' => ['Design', $designers]] as $field => [$label, $list]): ?>
                            <div class="col-6">
                                <label class="form-label small"><?= $label ?></label>
                                <select name="<?= $field ?>" class="form-select form-select-sm" <?= $dis($perm['brief']) ?>>
                                    <option value="">—</option>
                                    <?php foreach ($list as $u): ?><option value="<?= $u['id'] ?>" <?= (int)$a[$field] === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <label class="form-label small">Chuyên mục WP</label>
                            <select name="wp_category_id" class="form-select form-select-sm" <?= $dis($perm['brief']) ?> data-wp-categories="<?= $project['wp_url'] && $perm['brief'] ? url('/projects/' . $project['id'] . '/wp-categories') : '' ?>" data-selected="<?= (int)$a['wp_category_id'] ?>">
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

<form method="post" action="<?= url($base . '/wp-import') ?>" id="wp-import-form"><?= csrf_field() ?></form>

<div class="card mb-4" id="images">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <strong><i class="bi bi-images"></i> Hình ảnh</strong>
        <span class="small text-muted">Design upload hình; có thể dùng AI tạo hình gợi ý.</span>
        <?php if ($perm['images'] && $hasOpenAI && in_array($a['status'], ['design', 'image_revise'], true) && $images): ?>
            <button class="btn btn-sm btn-outline-primary ms-auto" form="task-form" name="task" value="images" <?= $busy ? 'disabled' : '' ?>><i class="bi bi-stars"></i> AI tạo các hình còn thiếu</button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($images as $img): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-2 h-100">
                        <div class="img-thumb mb-2">
                            <?php if ($img['file_path']): ?>
                                <a href="<?= url($img['file_path']) ?>" target="_blank"><img src="<?= url($img['file_path']) ?>" alt="<?= e($img['alt_text']) ?>"></a>
                            <?php else: ?>
                                <div class="text-muted small">Chưa có hình</div>
                            <?php endif; ?>
                        </div>
                        <div class="small fw-semibold mb-1 d-flex align-items-center gap-1">
                            <?= (int)$img['position'] === 0 ? '⭐ Ảnh đại diện' : 'Hình ' . (int)$img['position'] ?>
                            <?php if ($img['file_path']): ?><span class="badge text-bg-light border"><?= $img['source'] === 'upload' ? 'Upload' : 'AI' ?></span><?php endif; ?>
                            <?php if ($img['wp_media_id']): ?><span class="badge text-bg-light border">WP #<?= (int)$img['wp_media_id'] ?></span><?php endif; ?>
                            <?php if ($img['file_path'] && $perm['images']): ?>
                                <form method="post" action="<?= url($base . '/images/' . $img['id'] . '/delete') ?>" class="ms-auto" data-confirm="Gỡ hình này?"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button></form>
                            <?php endif; ?>
                        </div>
                        <input name="image_alt[<?= $img['id'] ?>]" form="main-form" class="form-control form-control-sm mb-2" value="<?= e($img['alt_text']) ?>" placeholder="Alt text" <?= $ro($perm['images']) ?>>
                        <?php if ($img['prompt']): ?><div class="small text-muted mb-2" title="Gợi ý nội dung hình"><i class="bi bi-lightbulb"></i> <?= e(mb_strimwidth((string)$img['prompt'], 0, 160, '…')) ?></div><?php endif; ?>
                        <?php if ($perm['images']): ?>
                            <form method="post" action="<?= url($base . '/images/upload') ?>" enctype="multipart/form-data" class="d-flex gap-1 mb-1">
                                <?= csrf_field() ?>
                                <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                                <input type="file" name="image" accept="image/*" class="form-control form-control-sm" required>
                                <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-upload"></i></button>
                            </form>
                            <?php if ($hasOpenAI && in_array($a['status'], ['design', 'image_revise'], true)): ?>
                                <details class="small">
                                    <summary class="text-muted">AI tạo hình này</summary>
                                    <form method="post" action="<?= url($base . '/images/' . $img['id']) ?>" class="mt-1">
                                        <?= csrf_field() ?>
                                        <textarea name="prompt" class="form-control form-control-sm mb-1" rows="3"><?= e($img['prompt']) ?></textarea>
                                        <button class="btn btn-sm btn-light w-100" <?= $busy ? 'disabled' : '' ?>><i class="bi bi-stars"></i> Tạo bằng AI</button>
                                    </form>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($perm['images']): ?>
                <div class="col-md-6 col-xl-4">
                    <form method="post" action="<?= url($base . '/images/upload') ?>" enctype="multipart/form-data" class="border border-dashed rounded p-3 h-100 d-flex flex-column justify-content-center">
                        <?= csrf_field() ?>
                        <div class="small fw-semibold mb-2"><i class="bi bi-plus-circle"></i> Thêm hình <?= $images ? 'mới (chèn cuối bài)' : 'đại diện' ?></div>
                        <input name="alt" class="form-control form-control-sm mb-2" placeholder="Alt text">
                        <input type="file" name="image" accept="image/*" class="form-control form-control-sm mb-2" required>
                        <button class="btn btn-sm btn-outline-primary">Upload</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-clock-history"></i> Lịch sử xử lý</strong></div>
            <ul class="list-group list-group-flush small history">
                <?php foreach ($logs as $l): ?>
                    <li class="list-group-item">
                        <span class="text-muted"><?= e(date('d/m H:i', strtotime($l['created_at']))) ?></span>
                        <b><?= e($l['user_name'] ?? 'Hệ thống') ?></b><?php if ($l['user_role']): ?> <span class="text-muted">(<?= $roleOf[$l['user_role']] ?? '' ?>)</span><?php endif; ?>
                        – <?= e(Workflow::actionLabel($l['action'])) ?>
                        <?php if ($l['note']): ?><div class="text-muted ms-4"><?= nl2br(e($l['note'])) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if (!$logs): ?><li class="list-group-item text-muted">Chưa có.</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-4 text-end">
        <?php if ($perm['delete']): ?>
            <form method="post" action="<?= url($base . '/delete') ?>" data-confirm="Xóa bài viết này khỏi tool? (Bài trên WordPress không bị xóa)" class="d-inline">
                <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa bài</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require BASE_PATH . '/app/views/partials/manual_ai.php'; ?>
<?php
$scripts = '<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.1/tinymce.min.js"></script>'
    . '<script>SEO.initEditor("#content-editor"); SEO.pollArticle(); SEO.guardWorkflow();</script>';
?>
