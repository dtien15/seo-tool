<?php
require __DIR__ . '/_tabs.php';
$current = null;
foreach ($competitors as $c) {
    if ((int)$c['id'] === $selected) {
        $current = $c;
    }
}
$fmt = fn($n) => $n === null ? '–' : number_format((float)$n);
?>
<p class="text-muted small">Phân tích đối thủ: từ khóa đang lên top, traffic theo thời gian (từ khi mới làm web đến nay), backlink và content. Số liệu lấy từ Ahrefs / Semrush: xuất file CSV rồi import, hoặc nhập tay theo tháng.</p>

<div class="row g-4">
    <div class="col-lg-3">
        <div class="card mb-3">
            <div class="list-group list-group-flush">
                <?php foreach ($competitors as $c): ?>
                    <a href="?c=<?= $c['id'] ?>" class="list-group-item list-group-item-action <?= (int)$c['id'] === $selected ? 'active' : '' ?>">
                        <div class="fw-medium text-truncate"><?= e($c['domain']) ?></div>
                        <small class="<?= (int)$c['id'] === $selected ? '' : 'text-muted' ?>"><?= (int)$c['kw_count'] ?> từ khóa · <?= count($metrics[$c['id']] ?? []) ?> tháng số liệu</small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$competitors): ?><div class="list-group-item text-muted small">Chưa có đối thủ.</div><?php endif; ?>
            </div>
        </div>
        <?php if ($canEdit): ?>
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/competitors') ?>" class="card card-body">
                <?= csrf_field() ?>
                <label class="form-label small">Thêm đối thủ (mỗi dòng một domain)</label>
                <textarea name="domains" class="form-control form-control-sm mb-2" rows="3" placeholder="doithu1.vn&#10;doithu2.com"></textarea>
                <button class="btn btn-sm btn-primary">Thêm</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-lg-9">
        <?php if (!$current): ?>
            <div class="card card-body text-muted">Thêm đối thủ ở cột bên trái để bắt đầu phân tích.</div>
        <?php else: $cm = $metrics[$current['id']] ?? []; ?>
            <div class="card mb-3">
                <div class="card-header bg-white d-flex align-items-center">
                    <strong><i class="bi bi-globe"></i> <?= e($current['domain']) ?></strong>
                    <a href="https://<?= e($current['domain']) ?>" target="_blank" rel="noopener" class="ms-2 small"><i class="bi bi-box-arrow-up-right"></i></a>
                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= url('/competitors/' . $current['id'] . '/delete') ?>" class="ms-auto" data-confirm="Xóa đối thủ này và toàn bộ số liệu?">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
                <form method="post" action="<?= url('/competitors/' . $current['id']) ?>" class="card-body">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label small">Bắt đầu làm web từ</label><input name="started_at" class="form-control form-control-sm" value="<?= e($current['started_at']) ?>" placeholder="vd: 2021"></div>
                        <div class="col-md-9"><label class="form-label small">Phân tích backlink</label><textarea name="backlink_notes" class="form-control form-control-sm" rows="2" placeholder="Nguồn backlink chính, DR, loại link (báo, guest post, PBN...), tốc độ tăng"><?= e($current['backlink_notes']) ?></textarea></div>
                        <div class="col-12"><label class="form-label small">Check content web đối thủ</label><textarea name="content_notes" class="form-control form-control-sm" rows="3" placeholder="Cấu trúc web, loại nội dung, độ dài bài, tần suất đăng, điểm mạnh nên học / điểm yếu có thể vượt"><?= e($current['content_notes']) ?></textarea></div>
                    </div>
                    <?php if ($canEdit): ?><button class="btn btn-sm btn-primary mt-3">Lưu ghi chú</button><?php endif; ?>
                </form>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white"><strong><i class="bi bi-graph-up"></i> Traffic & từ khóa theo thời gian</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light"><tr><th>Tháng</th><th class="text-end">Traffic</th><th class="text-end">Số từ khóa</th><th class="text-end">Top 10</th><th class="text-end">Ref. domains</th><th class="text-end">Backlinks</th></tr></thead>
                        <tbody>
                        <?php foreach ($cm as $m): ?>
                            <tr><td><?= e($m['period']) ?></td><td class="text-end"><?= $fmt($m['traffic']) ?></td><td class="text-end"><?= $fmt($m['keywords']) ?></td><td class="text-end"><?= $fmt($m['top10']) ?></td><td class="text-end"><?= $fmt($m['referring_domains']) ?></td><td class="text-end"><?= $fmt($m['backlinks']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$cm): ?><tr><td colspan="6" class="text-muted small">Chưa có số liệu. Nhập theo tháng (xem trong Ahrefs → Overview → biểu đồ Organic traffic).</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($canEdit): ?>
                    <form method="post" action="<?= url('/competitors/' . $current['id'] . '/metrics') ?>" class="card-footer bg-white row g-2 align-items-end mx-0">
                        <?= csrf_field() ?>
                        <div class="col-6 col-md-2"><label class="form-label small mb-0">Tháng</label><input type="month" name="period" class="form-control form-control-sm" value="<?= date('Y-m') ?>" required></div>
                        <?php foreach (['traffic' => 'Traffic', 'keywords' => 'Số từ khóa', 'top10' => 'Top 10', 'referring_domains' => 'Ref. domains', 'backlinks' => 'Backlinks'] as $f => $label): ?>
                            <div class="col-6 col-md-2"><label class="form-label small mb-0"><?= $label ?></label><input name="<?= $f ?>" class="form-control form-control-sm"></div>
                        <?php endforeach; ?>
                        <div class="col-12"><button class="btn btn-sm btn-outline-primary">Lưu số liệu tháng</button> <small class="text-muted">Nhập lại cùng tháng sẽ ghi đè.</small></div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
                    <strong class="me-auto"><i class="bi bi-key"></i> Từ khóa đối thủ đang lên top (<?= (int)$current['kw_count'] ?>)</strong>
                    <?php if ($canEdit): ?>
                        <form method="post" action="<?= url('/competitors/' . $current['id'] . '/import') ?>" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
                            <?= csrf_field() ?>
                            <input type="file" name="file" accept=".csv,.tsv,.txt" class="form-control form-control-sm" style="max-width:220px" required>
                            <input type="month" name="period" class="form-control form-control-sm" value="<?= date('Y-m') ?>" style="max-width:150px">
                            <label class="small"><input type="checkbox" name="replace" value="1"> Thay danh sách cũ</label>
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-upload"></i> Import CSV</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-body py-2 small text-muted">Ahrefs: Site Explorer → Organic keywords → Export. Semrush: Organic Research → Positions → Export. Cần có cột <code>Keyword</code>; các cột Position, Volume, KD, Traffic, URL có thì tự nhận.</div>
                <form method="post" action="<?= url('/competitors/' . $current['id'] . '/to-keywords') ?>">
                    <?= csrf_field() ?>
                    <div class="table-responsive" style="max-height:520px">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead class="table-light sticky-top"><tr><th style="width:30px"><input type="checkbox" class="form-check-input" data-check-all-in="table"></th><th>Từ khóa</th><th class="text-end">Vị trí</th><th class="text-end">Volume</th><th class="text-end">KD</th><th class="text-end">Traffic</th><th>URL</th></tr></thead>
                            <tbody>
                            <?php foreach ($topKeywords as $k): ?>
                                <tr>
                                    <td><?php if (!$k['in_set']): ?><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $k['id'] ?>"><?php else: ?><i class="bi bi-check2 text-success" title="Đã có trong bộ từ khóa"></i><?php endif; ?></td>
                                    <td><?= e($k['keyword']) ?></td>
                                    <td class="text-end"><?= $k['position'] !== null && $k['position'] <= 3 ? '<b class="text-success">' . (int)$k['position'] . '</b>' : $fmt($k['position']) ?></td>
                                    <td class="text-end"><?= $fmt($k['volume']) ?></td>
                                    <td class="text-end"><?= $fmt($k['kd']) ?></td>
                                    <td class="text-end"><?= $fmt($k['traffic']) ?></td>
                                    <td class="small text-truncate" style="max-width:260px"><?= e($k['url']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$topKeywords): ?><tr><td colspan="7" class="text-muted small">Chưa có dữ liệu từ khóa.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($canEdit && $topKeywords): ?>
                        <div class="card-footer bg-white"><button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Thêm từ khóa đã chọn vào bộ từ khóa dự án</button></div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
