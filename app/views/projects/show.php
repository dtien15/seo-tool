<?php
require __DIR__ . '/_tabs.php';
$isManager = \App\Auth::is('seo', 'leader');
$isLeader = \App\Auth::is('leader');
$role = \App\Auth::role();
?>

<?php if ($due && input('review') !== 'due' && $isManager): ?>
    <div class="alert alert-warning py-2 small"><i class="bi bi-arrow-repeat"></i> <?= $due ?> bài đã đăng đến hạn kiểm tra & tối ưu lại. <a href="?review=due" class="alert-link">Xem</a></div>
<?php endif; ?>
<?php if (input('review') === 'due'): ?>
    <div class="alert alert-info py-2 small">Đang lọc: bài đã đăng quá <?= (int)$project['reoptimize_days'] ?> ngày chưa kiểm tra lại. Mở từng bài → "Đã kiểm tra" hoặc "Tối ưu lại bài này". <a href="<?= url('/projects/' . $project['id']) ?>">Bỏ lọc</a></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3 status-chips">
    <a href="<?= url('/projects/' . $project['id']) ?>" class="btn btn-sm <?= input('status') ? 'btn-light' : 'btn-dark' ?>">Tất cả <span class="opacity-75"><?= array_sum($counts) ?></span></a>
    <?php foreach (article_statuses() as $key => [$label, $color]): if (empty($counts[$key])) continue; ?>
        <a href="<?= url('/projects/' . $project['id']) . query_with(['status' => $key, 'page' => null]) ?>"
           class="btn btn-sm <?= input('status') === $key ? 'btn-' . $color : 'btn-light' ?>"><?= e($label) ?> <span class="opacity-75"><?= (int)$counts[$key] ?></span></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-wrap gap-2 me-auto" method="get">
            <?php foreach (['status', 'review'] as $keep): if (input($keep)): ?><input type="hidden" name="<?= $keep ?>" value="<?= e(input($keep)) ?>"><?php endif; endforeach; ?>
            <input name="q" class="form-control form-control-sm" style="max-width:200px" placeholder="Tìm từ khóa, tiêu đề..." value="<?= e(input('q')) ?>">
            <?php if ($clusters): ?>
                <select name="cluster" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="">Mọi nhóm chủ đề</option>
                    <?php foreach ($clusters as $c): ?><option <?= input('cluster') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if ($isManager): ?>
                <select name="assignee" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    <option value="">Mọi người</option>
                    <?php foreach (array_merge($assignees, $writers, $designers) as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)input('assignee') === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
        </form>
        <?php if ($isManager): ?>
            <a href="<?= url('/projects/' . $project['id'] . '/keywords') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-key"></i> Từ bộ từ khóa</a>
            <a href="<?= url('/projects/' . $project['id'] . '/articles/new') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Thêm bài</a>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles/bulk') ?>" id="bulk-form">
        <?= csrf_field() ?>
        <div class="bulk-bar d-none align-items-center flex-wrap gap-2 px-3 py-2 border-bottom bg-light">
            <span class="small"><b class="bulk-count">0</b> bài đã chọn</span>
            <select name="action" class="form-select form-select-sm w-auto" required>
                <option value="">— Thao tác —</option>
                <optgroup label="AI">
                    <?php if ($isManager): ?><option value="outline">Tạo outline</option><?php endif; ?>
                    <?php if ($isManager || $role === 'content'): ?><option value="write">AI viết bài (bài đã duyệt outline)</option><?php endif; ?>
                    <?php if ($isManager || $role === 'design'): ?><option value="images">AI tạo hình còn thiếu</option><?php endif; ?>
                </optgroup>
                <optgroup label="Quy trình">
                    <?php if ($isManager): ?><option value="flow:submit_outline">Gửi TP duyệt outline</option><?php endif; ?>
                    <?php if ($isLeader): ?><option value="flow:approve_outline">Duyệt outline</option><?php endif; ?>
                    <?php if ($isManager || $role === 'content'): ?><option value="flow:submit_content">Gửi duyệt bài</option><?php endif; ?>
                    <?php if ($isManager): ?><option value="flow:approve_content">Duyệt bài</option><?php endif; ?>
                    <?php if ($isManager || $role === 'design'): ?><option value="flow:submit_images">Gửi duyệt hình</option><?php endif; ?>
                    <?php if ($isManager): ?><option value="flow:approve_images">Duyệt hình</option><?php endif; ?>
                    <?php if ($isManager): ?><option value="publish">Đăng lên WordPress</option><?php endif; ?>
                    <?php if ($isManager): ?><option value="flow:mark_reviewed">Đã kiểm tra (bài cũ, chưa cần sửa)</option><?php endif; ?>
                </optgroup>
                <?php if ($isManager): ?>
                    <optgroup label="Giao việc">
                        <option value="assign">Giao SEO phụ trách...</option>
                        <option value="assign_writer">Giao Content viết...</option>
                        <option value="assign_designer">Giao Design làm hình...</option>
                    </optgroup>
                <?php endif; ?>
                <?php if ($isLeader): ?>
                    <optgroup label="Đổi trạng thái thủ công (TP)">
                        <?php foreach (article_statuses() as $key => [$label]): ?><option value="status:<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
                <?php if ($isManager): ?><optgroup label="Khác"><option value="delete">Xóa</option></optgroup><?php endif; ?>
            </select>
            <?php foreach (['assign' => $assignees, 'assign_writer' => $writers, 'assign_designer' => $designers] as $name => $list): ?>
                <select name="<?= $name ?>" class="form-select form-select-sm w-auto d-none bulk-extra" data-for="<?= $name ?>">
                    <option value="">— Bỏ giao —</option>
                    <?php foreach ($list as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                </select>
            <?php endforeach; ?>
            <button class="btn btn-sm btn-dark">Thực hiện</button>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th style="width:32px"><input type="checkbox" class="form-check-input" data-check-all></th>
                    <th>Từ khóa / Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th class="d-none d-md-table-cell">Phân công</th>
                    <th class="d-none d-lg-table-cell">Dự kiến</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $a): ?>
                    <tr data-article-row="<?= $a['id'] ?>" data-ai-state="<?= e($a['ai_state']) ?>">
                        <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $a['id'] ?>"></td>
                        <td>
                            <a href="<?= url('/articles/' . $a['id']) ?>" class="fw-medium text-decoration-none"><?= e($a['keyword']) ?></a>
                            <?php if ($a['type'] === 'optimize'): ?><span class="badge text-bg-light border">Tối ưu lại</span><?php endif; ?>
                            <?php if ($a['reject_note'] && in_array($a['status'], ['outline', 'revise', 'image_revise', 'writing'], true)): ?><i class="bi bi-chat-left-text text-danger" title="<?= e($a['reject_note']) ?>"></i><?php endif; ?>
                            <div class="small text-muted text-truncate" style="max-width:480px"><?= e($a['title'] ?: '') ?><?= $a['cluster'] ? ($a['title'] ? ' · ' : '') . 'Nhóm: ' . e($a['cluster']) : '' ?></div>
                        </td>
                        <td class="text-nowrap"><?= status_badge($a['status']) ?> <?= ai_state_badge($a) ?></td>
                        <td class="d-none d-md-table-cell small text-nowrap">
                            <?php if ($a['assignee_name']): ?><div title="SEO"><i class="bi bi-person-gear text-muted"></i> <?= e($a['assignee_name']) ?></div><?php endif; ?>
                            <?php if ($a['writer_name']): ?><div title="Content"><i class="bi bi-pencil text-muted"></i> <?= e($a['writer_name']) ?></div><?php endif; ?>
                            <?php if ($a['designer_name']): ?><div title="Design"><i class="bi bi-palette text-muted"></i> <?= e($a['designer_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell small text-muted text-nowrap"><?= $a['planned_date'] ? e(date('d/m/Y', strtotime($a['planned_date']))) : '' ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($a['wp_url']): ?><a href="<?= e($a['wp_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="Xem trên website"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
                            <a href="<?= url('/articles/' . $a['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$articles): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5"><?= $isManager ? 'Chưa có bài trong kế hoạch. Tạo từ tab Từ khóa hoặc bấm "Thêm bài".' : 'Chưa có bài nào được giao cho bạn.' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php require BASE_PATH . '/app/views/partials/pagination.php'; ?>
</div>
<?php $scripts = '<script>SEO.pollRows();</script>'; ?>
