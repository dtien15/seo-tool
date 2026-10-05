<div class="card shadow-sm mx-auto mt-5" style="max-width:400px">
    <div class="card-body p-4">
        <h1 class="h4 text-center mb-4"><i class="bi bi-rocket-takeoff text-primary"></i> SEO Tool</h1>
        <form method="post" action="<?= url('/login') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</button>
        </form>
        <p class="small text-muted text-center mt-3 mb-0">Chưa có tài khoản? Liên hệ quản trị viên để được cấp.</p>
    </div>
</div>
