<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Tài khoản</h1>
    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#new-user"><i class="bi bi-person-plus"></i> Cấp tài khoản</button>
</div>

<div class="collapse mb-3" id="new-user">
    <div class="card"><div class="card-body">
        <form method="post" action="<?= url('/users') ?>" class="row g-3" autocomplete="off">
            <?= csrf_field() ?>
            <div class="col-md-3"><label class="form-label">Họ tên</label><input name="name" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Email đăng nhập</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Mật khẩu</label><input name="password" class="form-control" minlength="8" required value="<?= e(substr(random_token(6), 0, 10)) ?>"></div>
            <div class="col-md-2"><label class="form-label">Vai trò</label>
                <select name="role" class="form-select"><?php foreach (user_roles() as $k => [$label]): ?><option value="<?= $k ?>" <?= $k === 'seo' ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Hạn mức $/tháng</label><input type="number" step="0.5" min="0" name="monthly_budget_usd" class="form-control" placeholder="Mặc định"></div>
            <div class="col-12"><button class="btn btn-primary">Tạo tài khoản</button></div>
        </form>
    </div></div>
</div>

<div class="row g-2 mb-3 small">
    <?php foreach (user_roles() as $k => [$label, $desc]): ?>
        <div class="col-md"><div class="border rounded p-2 h-100 bg-white"><b><?= e($label) ?></b><div class="text-muted"><?= e($desc) ?></div></div></div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Người dùng</th><th>Vai trò</th><th>Dự án</th><th>Chi phí tháng</th><th>Đăng nhập gần nhất</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['is_active'] ? '' : 'opacity-50' ?>">
                    <td><div class="fw-medium"><?= e($u['name']) ?></div><small class="text-muted"><?= e($u['email']) ?></small></td>
                    <td><span class="badge <?= $u['role'] === 'admin' ? 'text-bg-dark' : ($u['role'] === 'leader' ? 'text-bg-primary' : 'text-bg-light border') ?>"><?= e(role_label($u['role'])) ?></span>
                        <?= $u['is_active'] ? '' : '<span class="badge text-bg-danger">Đã khóa</span>' ?></td>
                    <td><?= (int)$u['project_count'] ?></td>
                    <td><?= money($u['month_cost']) ?><?php if ($u['monthly_budget_usd'] !== null): ?> <small class="text-muted">/ <?= money($u['monthly_budget_usd']) ?></small><?php endif; ?></td>
                    <td class="small text-muted"><?= $u['last_login_at'] ? e(time_ago($u['last_login_at'])) : 'Chưa đăng nhập' ?></td>
                    <td class="text-end"><button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#edit-<?= $u['id'] ?>"><i class="bi bi-pencil"></i></button></td>
                </tr>
                <tr class="collapse" id="edit-<?= $u['id'] ?>">
                    <td colspan="6" class="bg-light">
                        <form method="post" action="<?= url('/users/' . $u['id']) ?>" class="row g-2 align-items-end" autocomplete="off">
                            <?= csrf_field() ?>
                            <div class="col-md-3"><label class="form-label small">Họ tên</label><input name="name" class="form-control form-control-sm" value="<?= e($u['name']) ?>"></div>
                            <div class="col-md-2"><label class="form-label small">Vai trò</label>
                                <select name="role" class="form-select form-select-sm">
                                    <?php foreach (user_roles() as $k => [$label]): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="col-md-2"><label class="form-label small">Hạn mức $/tháng</label><input type="number" step="0.5" min="0" name="monthly_budget_usd" class="form-control form-control-sm" value="<?= e($u['monthly_budget_usd']) ?>" placeholder="Mặc định"></div>
                            <div class="col-md-2"><label class="form-label small">Đặt lại mật khẩu</label><input name="password" class="form-control form-control-sm" placeholder="Để trống = giữ"></div>
                            <div class="col-md-2"><div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act<?= $u['id'] ?>" <?= $u['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="act<?= $u['id'] ?>">Đang hoạt động</label></div></div>
                            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Lưu</button></div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
