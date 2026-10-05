<h1 class="h4 mb-3">Tài khoản của tôi</h1>
<div class="card" style="max-width:560px">
    <div class="card-body">
        <form method="post" action="<?= url('/profile') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" value="<?= e($user['email']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Họ tên</label>
                <input name="name" class="form-control" value="<?= e($user['name']) ?>">
            </div>
            <hr>
            <p class="small text-muted">Đổi mật khẩu (để trống nếu không đổi)</p>
            <div class="mb-3">
                <label class="form-label">Mật khẩu hiện tại</label>
                <input type="password" name="current_password" class="form-control" autocomplete="current-password">
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu mới</label>
                <input type="password" name="new_password" class="form-control" minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-primary">Lưu</button>
        </form>
    </div>
</div>
