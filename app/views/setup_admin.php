<div class="card shadow-sm mx-auto" style="max-width:480px">
    <div class="card-body p-4">
        <h1 class="h5 mb-1"><i class="bi bi-person-plus text-primary"></i> Tạo tài khoản quản trị</h1>
        <p class="text-muted small">Đã kết nối database thành công. Tạo tài khoản admin đầu tiên để đăng nhập.</p>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger py-2"><?= e($err) ?></div>
        <?php endforeach; ?>
        <form method="post" action="<?= url('/setup') ?>">
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label">Họ tên</label><input name="admin_name" class="form-control" value="<?= e($data['admin_name']) ?>"></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" value="<?= e($data['admin_email']) ?>" required></div>
            <div class="mb-3"><label class="form-label">Mật khẩu (≥ 8 ký tự)</label><input type="password" name="admin_pass" class="form-control" minlength="8" required></div>
            <button class="btn btn-primary w-100">Tạo tài khoản</button>
        </form>
    </div>
</div>
