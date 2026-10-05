<?php require __DIR__ . '/_tabs.php'; ?>

<?php if (!$serviceEmail): ?>
    <div class="alert alert-warning">
        Quản trị viên chưa cấu hình <b>Google Service Account</b> trong Cài đặt hệ thống, nên chưa đồng bộ được Google Sheet.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-table"></i> Kết nối Google Sheet</strong></div>
            <div class="card-body">
                <form method="post" action="<?= url('/projects/' . $project['id'] . '/sheet') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Link hoặc ID Google Sheet</label>
                        <input name="gsheet_id" class="form-control" value="<?= e($project['gsheet_id']) ?>" placeholder="https://docs.google.com/spreadsheets/d/...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tên tab (sheet con)</label>
                        <input name="gsheet_tab" class="form-control" value="<?= e($project['gsheet_tab']) ?>">
                        <div class="form-text">Tab sẽ được tạo nếu chưa có.</div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="regenerate_token" value="1" id="regen">
                        <label class="form-check-label small" for="regen">Tạo token webhook mới (phải dán lại Apps Script)</label>
                    </div>
                    <button class="btn btn-primary btn-sm">Lưu</button>
                    <?php if ($project['gsheet_id']): ?>
                        <a href="https://docs.google.com/spreadsheets/d/<?= e($project['gsheet_id']) ?>/edit" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="bi bi-box-arrow-up-right"></i> Mở sheet</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($project['gsheet_id'] && $serviceEmail): ?>
            <div class="card mb-3">
                <div class="card-body d-flex flex-wrap gap-2">
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/sheet/init') ?>">
                        <?= csrf_field() ?><button class="btn btn-success btn-sm"><i class="bi bi-magic"></i> Khởi tạo &amp; đồng bộ</button>
                    </form>
                    <form method="post" action="<?= url('/projects/' . $project['id'] . '/sheet/sync') ?>">
                        <?= csrf_field() ?><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat"></i> Đồng bộ ngay</button>
                    </form>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Lần đọc sheet gần nhất: <?= $project['sheet_pulled_at'] ? e(time_ago($project['sheet_pulled_at'])) : 'chưa có' ?>.
                    Hệ thống tự đọc lại mỗi 5 phút để dự phòng.
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-white"><strong>Cấu trúc cột</strong></div>
            <div class="card-body small">
                <ol class="mb-2 ps-3">
                    <?php foreach (\App\Services\SheetSync::HEADERS as $i => $h): ?>
                        <li><b><?= chr(65 + $i) ?></b> – <?= e($h) ?></li>
                    <?php endforeach; ?>
                </ol>
                <ul class="ps-3 mb-0 text-muted">
                    <li>Đổi <b>Trạng thái</b> trên sheet → web cập nhật sau vài giây (nhờ Apps Script).</li>
                    <li>Thêm dòng mới chỉ cần điền <b>Từ khóa</b> (để trống ID) → web tự tạo bài và điền ID.</li>
                    <li>Không sửa cột ID. Không đổi thứ tự cột.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white"><strong><i class="bi bi-list-ol"></i> Hướng dẫn cài đặt (làm 1 lần cho mỗi sheet)</strong></div>
            <div class="card-body">
                <ol class="setup-steps">
                    <li>Mở Google Sheet → <b>Chia sẻ</b> → thêm email
                        <code class="user-select-all"><?= e($serviceEmail ?: '(chưa cấu hình)') ?></code> với quyền <b>Người chỉnh sửa</b>.</li>
                    <li>Dán link sheet vào ô bên trái → <b>Lưu</b> → bấm <b>Khởi tạo &amp; đồng bộ</b> (tạo tiêu đề cột, dropdown trạng thái và đẩy toàn bộ bài lên).</li>
                    <li>Để sheet → web cập nhật tức thì: trong Google Sheet chọn <b>Tiện ích mở rộng → Apps Script</b>, xóa code mặc định, dán đoạn code dưới đây, bấm 💾 Lưu.</li>
                    <li>Chọn hàm <code>setup</code> ở thanh trên → bấm <b>▶ Chạy</b> → cấp quyền cho script (Advanced → Go to project). Xong!</li>
                </ol>
                <div class="position-relative">
                    <button type="button" class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2" data-copy="#apps-script"><i class="bi bi-clipboard"></i> Copy</button>
                    <pre class="code-block" id="apps-script"><?= e($script) ?></pre>
                </div>
                <p class="small text-muted mb-0">Webhook: <code><?= e($webhookUrl) ?></code></p>
            </div>
        </div>
    </div>
</div>
