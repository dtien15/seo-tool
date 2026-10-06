<?php require __DIR__ . '/_tabs.php'; ?>
<?php
$jobNames = ['import_sitemap' => 'Quét sitemap', 'import_wordpress' => 'Lấy từ WordPress', 'fetch_titles' => 'Lấy tiêu đề trang'];
$jobStatus = ['pending' => 'đang chờ', 'running' => 'đang chạy', 'done' => 'xong', 'failed' => 'lỗi'];
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-diagram-3"></i> Lấy link từ sitemap</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/projects/' . $project['id'] . '/links/import') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="source" value="sitemap">
                    <input name="sitemap_url" class="form-control mb-2" value="<?= e($project['sitemap_url']) ?>" placeholder="https://example.com/sitemap_index.xml" required>
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-cloud-download"></i> Quét sitemap</button>
                </form>
                <div class="form-text">Hỗ trợ sitemap index của Yoast / Rank Math. Bỏ qua trang tag, tác giả, phân trang. Tiêu đề thật được lấy dần ở chế độ nền.</div>
                <?php if ($project['wp_url']): ?>
                    <hr>
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/links/import') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="source" value="wordpress">
                        <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-wordpress"></i> Lấy bài + trang từ WordPress</button>
                    </form>
                    <div class="form-text">Nhanh hơn và có sẵn tiêu đề chính xác.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-plus-lg"></i> Thêm thủ công</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/projects/' . $project['id'] . '/links') ?>">
                    <?= csrf_field() ?>
                    <textarea name="lines" class="form-control mb-2 font-monospace small" rows="5" placeholder="https://site.com/bai-a | Tiêu đề bài A | từ khóa 1, từ khóa 2"></textarea>
                    <div class="form-text mb-2">Mỗi dòng: <code>URL | tiêu đề | từ khóa</code> (tiêu đề, từ khóa không bắt buộc). Link "money page" nên điền từ khóa để AI ưu tiên.</div>
                    <button class="btn btn-outline-secondary btn-sm">Thêm</button>
                </form>
            </div>
        </div>

        <?php if ($jobs): ?>
            <div class="card">
                <div class="card-header bg-white"><strong>Tiến trình gần đây</strong></div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($jobs as $j): ?>
                        <li class="list-group-item">
                            <?= e($jobNames[$j['type']] ?? $j['type']) ?> –
                            <span class="<?= $j['status'] === 'failed' ? 'text-danger' : ($j['status'] === 'done' ? 'text-success' : 'text-info') ?>"><?= e($jobStatus[$j['status']] ?? $j['status']) ?></span>
                            <span class="text-muted">· <?= time_ago($j['created_at']) ?></span>
                            <?php if ($j['last_error']): ?><div class="text-danger"><?= e($j['last_error']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                <strong class="me-auto"><?= (int)$pg['total'] ?> link nội bộ<?php if ($untitled): ?> <small class="text-muted fw-normal">(<?= $untitled ?> đang lấy tiêu đề)</small><?php endif; ?></strong>
                <form method="get" class="d-flex gap-2">
                    <input name="q" class="form-control form-control-sm" placeholder="Tìm URL, tiêu đề..." value="<?= e(input('q')) ?>">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
                </form>
                <?php if ($pg['total']): ?>
                    <?= ai_button((int)$project['id'], 'ai_content_audit', 'AI rà soát 20 trang', [], $aiPending) ?>
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/links/clear') ?>" data-confirm="Xóa toàn bộ link nội bộ của dự án?">
                        <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 small">
                <span class="text-muted">Rà soát:</span>
                <a href="?" class="<?= input('audit') ? 'text-muted' : 'fw-bold' ?>">Tất cả</a>
                <a href="?audit=__none" class="<?= input('audit') === '__none' ? 'fw-bold' : 'text-muted' ?>">Chưa rà soát (<?= (int)($auditCounts['__none'] ?? 0) ?>)</a>
                <?php foreach (\App\Controllers\LinkController::AUDIT_ACTIONS as $k => [$label]): ?>
                    <a href="?audit=<?= $k ?>" class="<?= input('audit') === $k ? 'fw-bold' : 'text-muted' ?>"><?= e($label) ?> (<?= (int)($auditCounts[$k] ?? 0) ?>)</a>
                <?php endforeach; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 links-table">
                    <thead class="table-light"><tr><th>URL / Tiêu đề</th><th style="width:16%">Từ khóa ưu tiên</th><th style="width:13%">Loại trang</th><th style="width:14%">Nhóm chủ đề</th><th style="width:15%">Đề xuất</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($links as $l): ?>
                        <tr data-link="<?= url('/links/' . $l['id']) ?>">
                            <td>
                                <input class="form-control form-control-sm border-0 px-0 fw-medium" data-field="title" value="<?= e($l['title']) ?>">
                                <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener" class="small text-muted text-break"><?= e($l['url']) ?></a>
                                <input class="form-control form-control-sm mt-1 <?= $l['audit_note'] ? '' : 'd-none' ?>" data-field="audit_note" value="<?= e($l['audit_note']) ?>" placeholder="Ghi chú đề xuất tối ưu">
                            </td>
                            <td><input class="form-control form-control-sm" data-field="keywords" value="<?= e($l['keywords']) ?>" placeholder="—"></td>
                            <td><select class="form-select form-select-sm" data-field="page_type"><option value="">—</option><?php foreach (\App\Controllers\LinkController::PAGE_TYPES as $k => $label): ?><option value="<?= $k ?>" <?= $l['page_type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                            <td><input class="form-control form-control-sm" data-field="cluster" value="<?= e($l['cluster']) ?>" placeholder="—"></td>
                            <td><select class="form-select form-select-sm" data-field="audit_action"><option value="">Chưa rà soát</option><?php foreach (\App\Controllers\LinkController::AUDIT_ACTIONS as $k => [$label]): ?><option value="<?= $k ?>" <?= $l['audit_action'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></td>
                            <td class="text-end text-nowrap">
                                <?php if ($l['article_id']): ?>
                                    <a href="<?= url('/articles/' . $l['article_id']) ?>" class="btn btn-sm btn-light" title="Mở bài trong tool"><i class="bi bi-file-text"></i></a>
                                <?php else: ?>
                                    <form method="post" action="<?= url('/links/' . $l['id'] . '/optimize') ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-light" title="Tạo việc tối ưu lại trang này"><i class="bi bi-tools"></i></button></form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-light" data-link-delete title="Xóa khỏi danh sách"><i class="bi bi-x-lg"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$links): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">Chưa có link nào. Nhập sitemap ở bên trái để bắt đầu.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php require BASE_PATH . '/app/views/partials/pagination.php'; ?>
        </div>
        <p class="small text-muted mt-2">Đây vừa là danh sách <b>rà soát nội dung cũ</b> (check lại mô tả sản phẩm, bài blog → nhóm chủ đề → đề xuất giữ / tối ưu / gộp / xóa), vừa là <b>kho interlink</b> cho AI khi viết bài. Mọi ô sửa trực tiếp, tự lưu. Nút <i class="bi bi-tools"></i> tạo việc tối ưu lại trang đó theo quy trình.</p>
    </div>
</div>
