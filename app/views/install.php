<div class="card shadow-sm mx-auto" style="max-width:720px">
    <div class="card-body p-4">
        <h1 class="h4 mb-1"><i class="bi bi-rocket-takeoff text-primary"></i> Cài đặt SEO Tool</h1>
        <p class="text-muted">Tạo database MySQL trong cPanel trước (MySQL® Databases), gán user vào database với đầy đủ quyền, rồi điền thông tin bên dưới.</p>

        <div class="mb-3">
            <?php foreach ($checks as $label => $ok): ?>
                <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?> me-1 mb-1">
                    <i class="bi <?= $ok ? 'bi-check-lg' : 'bi-x-lg' ?>"></i> <?= e($label) ?>
                </span>
            <?php endforeach; ?>
            <?php if (!$checks['Anthropic SDK (composer install)']): ?>
                <div class="small text-warning-emphasis mt-1">Chưa có thư viện Claude SDK: vẫn cài đặt được, nhưng cần chạy <code>composer install</code> trước khi dùng AI viết bài (xem README).</div>
            <?php endif; ?>
        </div>

        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= url('/install') ?>">
            <h2 class="h6 mt-3 text-uppercase text-muted">Website</h2>
            <div class="mb-3">
                <label class="form-label">Địa chỉ website (URL đầy đủ)</label>
                <input name="app_url" class="form-control" value="<?= e($data['app_url']) ?>" required>
            </div>

            <h2 class="h6 mt-4 text-uppercase text-muted">Cơ sở dữ liệu MySQL</h2>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Host</label><input name="db_host" class="form-control" value="<?= e($data['db_host']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Port</label><input name="db_port" class="form-control" value="<?= e($data['db_port']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Tên database</label><input name="db_name" class="form-control" value="<?= e($data['db_name']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">User</label><input name="db_user" class="form-control" value="<?= e($data['db_user']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Mật khẩu</label><input type="password" name="db_pass" class="form-control"></div>
            </div>

            <h2 class="h6 mt-4 text-uppercase text-muted">Tài khoản quản trị</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Họ tên</label><input name="admin_name" class="form-control" value="<?= e($data['admin_name']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" value="<?= e($data['admin_email']) ?>" required></div>
                <div class="col-md-4"><label class="form-label">Mật khẩu (≥ 8 ký tự)</label><input type="password" name="admin_pass" class="form-control" required minlength="8"></div>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-4"><i class="bi bi-check2-circle"></i> Cài đặt</button>
        </form>
    </div>
</div>
