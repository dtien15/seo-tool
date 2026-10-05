<?php
require __DIR__ . '/_tabs.php';
$fmt = fn($n) => $n === null ? '–' : number_format((float)$n);
$priorities = [1 => ['Cao', 'danger'], 2 => ['Trung bình', 'warning'], 3 => ['Thấp', 'secondary']];
$intents = ['informational' => 'Thông tin', 'commercial' => 'Tìm hiểu mua', 'transactional' => 'Mua hàng', 'navigational' => 'Điều hướng', 'local' => 'Địa phương'];
$currentCluster = (string)input('cluster', '');
?>
<p class="text-muted small">Lên bộ từ khóa → gom nhóm chủ đề → chọn từ khóa đưa vào kế hoạch content. Mỗi nhóm chủ đề thường là 1 bài (từ khóa chính + các từ khóa phụ cùng intent).</p>

<div class="row g-4">
    <div class="col-xl-3">
        <div class="card mb-3">
            <div class="card-header bg-white d-flex"><strong>Nhóm chủ đề</strong><span class="ms-auto small text-muted"><?= $total ?> từ khóa</span></div>
            <div class="list-group list-group-flush small" style="max-height:420px;overflow:auto">
                <a href="?" class="list-group-item list-group-item-action <?= $currentCluster === '' ? 'active' : '' ?>">Tất cả</a>
                <?php if ($unclustered): ?>
                    <a href="?cluster=__none" class="list-group-item list-group-item-action <?= $currentCluster === '__none' ? 'active' : '' ?>">Chưa có nhóm <span class="badge text-bg-warning float-end"><?= $unclustered ?></span></a>
                <?php endif; ?>
                <?php foreach ($clusters as $c): ?>
                    <a href="?cluster=<?= urlencode($c['cluster']) ?>" class="list-group-item list-group-item-action <?= $currentCluster === $c['cluster'] ? 'active' : '' ?>">
                        <?= e($c['cluster']) ?>
                        <span class="float-end opacity-75"><?= (int)$c['c'] ?> · <?= $fmt($c['vol']) ?><?= (int)$c['planned'] ? ' · <i class="bi bi-file-text"></i>' . (int)$c['planned'] : '' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($canEdit && $total): ?>
                <form method="post" action="<?= url('/projects/' . $project['id'] . '/keywords/bulk') ?>" class="card-footer bg-white">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cluster_ai">
                    <?php if ($clusterJob): ?>
                        <div class="small text-info"><span class="spinner-border spinner-border-sm"></span> AI đang gom nhóm...</div>
                    <?php else: ?>
                        <label class="small d-block mb-2"><input type="checkbox" name="only_missing" value="1" checked id="only-missing"> Chỉ từ khóa chưa có nhóm</label>
                        <?php if (!$aiManual): ?><button class="btn btn-sm btn-outline-primary w-100 mb-2"><i class="bi bi-stars"></i> AI gom nhóm chủ đề</button><?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" data-manual-ai="<?= url('/projects/' . $project['id'] . '/keywords/prompt') ?>" data-only-missing="#only-missing"
                            data-paste-url="<?= url('/projects/' . $project['id'] . '/keywords/paste') ?>" data-task="cluster" data-title="Gom nhóm từ khóa với ChatGPT / Claude"><i class="bi bi-clipboard"></i> Copy prompt (ChatGPT / Claude)</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($canEdit): ?>
            <div class="card">
                <div class="card-header bg-white"><strong>Thêm từ khóa</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/keywords') ?>" class="mb-3">
                        <?= csrf_field() ?>
                        <textarea name="lines" class="form-control form-control-sm mb-2 font-monospace" rows="5" placeholder="từ khóa | volume | nhóm&#10;niềng răng giá bao nhiêu | 5400 | Giá niềng răng"></textarea>
                        <button class="btn btn-sm btn-primary">Thêm</button>
                    </form>
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/keywords') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <label class="form-label small">Hoặc import CSV (Ahrefs Keywords Explorer, Semrush, Google Sheet)</label>
                        <input type="file" name="file" accept=".csv,.tsv,.txt" class="form-control form-control-sm mb-2" required>
                        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Import</button>
                        <div class="form-text">Cột nhận diện: Keyword, Volume, KD, Intent, Cluster/Nhóm, URL, Position.</div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-xl-9">
        <form method="post" action="<?= url('/projects/' . $project['id'] . '/keywords/bulk') . e(query_with([])) ?>" class="card" id="kw-form">
            <?= csrf_field() ?>
            <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                <input type="search" class="form-control form-control-sm" style="max-width:220px" placeholder="Lọc nhanh..." data-filter-table="#kw-table">
                <?php if ($canEdit): ?>
                    <select name="action" class="form-select form-select-sm w-auto" data-kw-action>
                        <option value="">— Thao tác với từ khóa đã chọn —</option>
                        <option value="plan">➜ Tạo bài trong kế hoạch content</option>
                        <option value="set_cluster">Gán nhóm chủ đề</option>
                        <option value="set_priority">Đặt độ ưu tiên</option>
                        <option value="set_intent">Đặt search intent</option>
                        <option value="delete">Xóa</option>
                    </select>
                    <input name="value" class="form-control form-control-sm w-auto d-none" data-kw-value list="cluster-list">
                    <datalist id="cluster-list"><?php foreach ($clusters as $c): ?><option value="<?= e($c['cluster']) ?>"><?php endforeach; ?></datalist>
                    <button class="btn btn-sm btn-dark">Thực hiện</button>
                <?php endif; ?>
            </div>
            <div class="table-responsive" style="max-height:70vh">
                <table class="table table-sm table-hover align-middle mb-0" id="kw-table">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:30px"><input type="checkbox" class="form-check-input" data-check-all-in="table"></th>
                        <th>Từ khóa</th><th>Nhóm chủ đề</th><th class="text-end">Volume</th><th class="text-end">KD</th><th>Intent</th><th>Ưu tiên</th><th>Bài viết</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($keywords as $k): ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $k['id'] ?>"></td>
                            <td><?= e($k['keyword']) ?><?php if ($k['current_rank']): ?> <span class="badge text-bg-light border" title="Thứ hạng hiện tại">#<?= (int)$k['current_rank'] ?></span><?php endif; ?>
                                <?php if ($k['source']): ?><div class="small text-muted"><?= e($k['source']) ?></div><?php endif; ?></td>
                            <td class="small"><?= e($k['cluster'] ?: '—') ?></td>
                            <td class="text-end"><?= $fmt($k['volume']) ?></td>
                            <td class="text-end"><?= $fmt($k['kd']) ?></td>
                            <td class="small"><?= e($intents[$k['intent']] ?? $k['intent'] ?? '') ?></td>
                            <td><span class="badge text-bg-<?= $priorities[$k['priority']][1] ?? 'light' ?>"><?= $priorities[$k['priority']][0] ?? '' ?></span></td>
                            <td class="text-nowrap"><?php if ($k['article_id']): ?><a href="<?= url('/articles/' . $k['article_id']) ?>"><?= status_badge((string)$k['article_status']) ?></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$keywords): ?><tr><td colspan="8" class="text-center text-muted py-5">Chưa có từ khóa. Thêm ở cột trái hoặc lấy từ tab Đối thủ.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        <div class="small text-muted mt-2">
            Gợi ý giá trị khi chọn thao tác: <b>Đặt độ ưu tiên</b> → 1 (cao), 2, 3 (thấp). <b>Search intent</b> → informational / commercial / transactional / navigational / local.
            <b>Tạo bài</b> → có thể nhập ngày dự kiến đăng (YYYY-MM-DD). Từ khóa có URL đích sẽ tạo việc <i>tối ưu lại</i> trang đó.
        </div>
    </div>
</div>

<?php require BASE_PATH . '/app/views/partials/manual_ai.php'; ?>
