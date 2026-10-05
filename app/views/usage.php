<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h4 mb-0">Chi phí AI</h1>
    <form method="get" class="d-flex gap-2">
        <input type="month" name="month" class="form-control form-control-sm" value="<?= e($month) ?>">
        <button class="btn btn-sm btn-outline-secondary">Xem</button>
    </form>
</div>
<div class="card mb-4"><div class="card-body">
    <div class="stat-label">Tổng chi phí tháng <?= e(date('m/Y', strtotime($month . '-01'))) ?></div>
    <div class="stat-value"><?= money($total) ?></div>
    <div class="small text-muted">Ước tính theo bảng giá model và giá ảnh trong Cài đặt. Số liệu chính xác xem trên trang billing của Anthropic / OpenAI.</div>
</div></div>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white"><strong>Theo người dùng</strong></div>
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Người dùng</th><th class="text-end">Bài viết AI</th><th class="text-end">Ảnh</th><th class="text-end">Chi phí</th></tr></thead>
                <tbody>
                <?php foreach ($byUser as $r): ?>
                    <tr><td><?= e($r['name'] ?? 'Hệ thống') ?></td><td class="text-end"><?= (int)$r['writes'] ?></td><td class="text-end"><?= (int)$r['imgs'] ?></td><td class="text-end"><?= money($r['cost']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$byUser): ?><tr><td colspan="4" class="text-muted text-center">Chưa có dữ liệu</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white"><strong>Theo dự án</strong></div>
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Dự án</th><th class="text-end">Bài viết AI</th><th class="text-end">Ảnh</th><th class="text-end">Chi phí</th></tr></thead>
                <tbody>
                <?php foreach ($byProject as $r): ?>
                    <tr><td><?= e($r['name'] ?? '(đã xóa)') ?></td><td class="text-end"><?= (int)$r['writes'] ?></td><td class="text-end"><?= (int)$r['imgs'] ?></td><td class="text-end"><?= money($r['cost']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$byProject): ?><tr><td colspan="4" class="text-muted text-center">Chưa có dữ liệu</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
