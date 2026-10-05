<?php require __DIR__ . '/_tabs.php'; ?>

<?php if (!$project['wp_url'] || !$linkCount): ?>
    <div class="alert alert-light border small">
        <strong>Bắt đầu nhanh:</strong>
        <?php if (!$project['wp_url']): ?> <a href="<?= url('/projects/' . $project['id'] . '/settings') ?>">① Kết nối WordPress</a> &nbsp;<?php endif; ?>
        <?php if (!$linkCount): ?> <a href="<?= url('/projects/' . $project['id'] . '/links') ?>">② Lấy link nội bộ từ sitemap</a> &nbsp;<?php endif; ?>
        ③ Thêm từ khóa và bấm "Viết bằng AI".
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3 status-chips">
    <a href="<?= url('/projects/' . $project['id']) ?>" class="btn btn-sm <?= input('status') ? 'btn-light' : 'btn-dark' ?>">Tất cả <span class="opacity-75"><?= array_sum($counts) ?></span></a>
    <?php foreach (article_statuses() as $key => [$label, $color]): ?>
        <a href="<?= url('/projects/' . $project['id']) . query_with(['status' => $key, 'page' => null]) ?>"
           class="btn btn-sm <?= input('status') === $key ? 'btn-' . $color : 'btn-light' ?>"><?= e($label) ?> <span class="opacity-75"><?= (int)($counts[$key] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 me-auto" method="get">
            <?php if (input('status')): ?><input type="hidden" name="status" value="<?= e(input('status')) ?>"><?php endif; ?>
            <input name="q" class="form-control form-control-sm" placeholder="Tìm từ khóa, tiêu đề..." value="<?= e(input('q')) ?>">
            <select name="assignee" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Mọi người</option>
                <?php foreach ($assignees as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int)input('assignee') === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
        <a href="<?= url('/projects/' . $project['id'] . '/articles/new') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Thêm bài viết</a>
    </div>

    <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles/bulk') ?>" id="bulk-form">
        <?= csrf_field() ?>
        <div class="bulk-bar d-none align-items-center gap-2 px-3 py-2 border-bottom bg-light">
            <span class="small"><b class="bulk-count">0</b> bài đã chọn</span>
            <select name="action" class="form-select form-select-sm w-auto" required>
                <option value="">— Thao tác —</option>
                <optgroup label="AI">
                    <option value="outline">Tạo outline</option>
                    <option value="write">Viết bài bằng AI</option>
                    <option value="images">Tạo ảnh</option>
                </optgroup>
                <optgroup label="WordPress">
                    <option value="publish">Đăng lên WordPress</option>
                </optgroup>
                <optgroup label="Đổi trạng thái">
                    <?php foreach (article_statuses() as $key => [$label]): ?>
                        <option value="status:<?= $key ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Khác">
                    <option value="assign">Giao cho...</option>
                    <option value="delete">Xóa</option>
                </optgroup>
            </select>
            <select name="assignee" class="form-select form-select-sm w-auto d-none bulk-assignee">
                <?php foreach ($assignees as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-dark" data-confirm-bulk>Thực hiện</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:32px"><input type="checkbox" class="form-check-input" data-check-all></th>
                    <th>Từ khóa / Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th class="d-none d-md-table-cell">Phụ trách</th>
                    <th class="d-none d-lg-table-cell">Cập nhật</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $a): ?>
                    <tr data-article-row="<?= $a['id'] ?>" data-ai-state="<?= e($a['ai_state']) ?>">
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $a['id'] ?>"></td>
                        <td>
                            <a href="<?= url('/articles/' . $a['id']) ?>" class="fw-medium text-decoration-none"><?= e($a['keyword']) ?></a>
                            <?php if ($a['title']): ?><div class="small text-muted text-truncate" style="max-width:520px"><?= e($a['title']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= status_badge($a['status']) ?> <?= ai_state_badge($a) ?></td>
                        <td class="d-none d-md-table-cell small"><?= e($a['assignee_name'] ?? '—') ?></td>
                        <td class="d-none d-lg-table-cell small text-muted"><?= time_ago($a['updated_at']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($a['wp_url']): ?><a href="<?= e($a['wp_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="Xem trên WordPress"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                            <a href="<?= url('/articles/' . $a['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$articles): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">Chưa có bài viết. <a href="<?= url('/projects/' . $project['id'] . '/articles/new') ?>">Thêm bài viết</a></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php require BASE_PATH . '/app/views/partials/pagination.php'; ?>
</div>
<?php $scripts = '<script>SEO.pollRows();</script>'; ?>
