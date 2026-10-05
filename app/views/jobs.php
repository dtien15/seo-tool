<?php
$labels = ['pending' => ['Đang chờ', 'secondary'], 'running' => ['Đang chạy', 'info'], 'done' => ['Xong', 'success'], 'failed' => ['Lỗi', 'danger']];
$types = [
    'outline' => 'Tạo outline', 'write' => 'Viết bài', 'images' => 'Tạo ảnh', 'image' => 'Tạo lại ảnh', 'publish' => 'Đăng WP',
    'import_sitemap' => 'Quét sitemap', 'import_wordpress' => 'Lấy link WP', 'fetch_titles' => 'Lấy tiêu đề',
    'sheet_push' => 'Đẩy lên Sheet', 'sheet_push_all' => 'Đẩy toàn bộ lên Sheet', 'sheet_pull' => 'Đọc Sheet',
];
?>
<h1 class="h4 mb-3">Hàng đợi xử lý</h1>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?= url('/jobs') ?>" class="btn btn-sm <?= $status === '' ? 'btn-dark' : 'btn-light' ?>">Tất cả</a>
    <?php foreach ($labels as $k => [$label, $color]): ?>
        <a href="<?= url('/jobs') ?>?status=<?= $k ?>" class="btn btn-sm <?= $status === $k ? 'btn-' . $color : 'btn-light' ?>"><?= $label ?> <span class="opacity-75"><?= (int)($counts[$k] ?? 0) ?></span></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Loại</th><th>Dự án / Bài</th><th>Người tạo</th><th>Trạng thái</th><th>Thời gian</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $j): [$label, $color] = $labels[$j['status']] ?? [$j['status'], 'secondary']; ?>
                <tr>
                    <td class="text-muted"><?= $j['id'] ?></td>
                    <td><?= e($types[$j['type']] ?? $j['type']) ?></td>
                    <td class="small"><?= e($j['project_name'] ?? '') ?><?php if ($j['article_id']): ?> · <a href="<?= url('/articles/' . $j['article_id']) ?>"><?= e($j['keyword'] ?? '#' . $j['article_id']) ?></a><?php endif; ?></td>
                    <td class="small"><?= e($j['user_name'] ?? 'Hệ thống') ?></td>
                    <td><span class="badge text-bg-<?= $color ?>"><?= $label ?></span>
                        <?php if ($j['last_error']): ?><div class="small text-danger text-break" style="max-width:420px"><?= e($j['last_error']) ?></div><?php endif; ?></td>
                    <td class="small text-muted text-nowrap"><?= e(time_ago($j['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ($j['status'] === 'failed'): ?>
                            <form method="post" action="<?= url('/jobs/' . $j['id'] . '/retry') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-light" title="Chạy lại"><i class="bi bi-arrow-clockwise"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$jobs): ?><tr><td colspan="7" class="text-center text-muted py-4">Không có job nào.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
