<?php
$p = $project ?? [];
$isEdit = $project !== null;
$v = fn(string $k, $d = '') => e($p[$k] ?? $d);
?>
<?php if ($isEdit): ?>
    <?php require __DIR__ . '/_tabs.php'; ?>
<?php else: ?>
    <h1 class="h4 mb-3">Tạo dự án mới</h1>
<?php endif; ?>

<form method="post" action="<?= url($isEdit ? '/projects/' . $p['id'] . '/settings' : '/projects') ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-info-circle"></i> Thông tin dự án</strong>
                    <div class="small text-muted">AI sẽ đọc phần này mỗi khi viết bài cho dự án.</div></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Tên dự án / thương hiệu *</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Domain</label><input name="domain" class="form-control" value="<?= $v('domain') ?>" placeholder="vd: nhakhoaabc.vn"></div>
                        <div class="col-12"><label class="form-label">Lĩnh vực / sản phẩm dịch vụ</label><input name="niche" class="form-control" value="<?= $v('niche') ?>" placeholder="vd: Nha khoa thẩm mỹ tại TP.HCM"></div>
                        <div class="col-12"><label class="form-label">Giọng văn thương hiệu</label><textarea name="brand_voice" class="form-control" rows="2" placeholder="vd: Chuyên nghiệp, gần gũi, xưng 'chúng tôi' - gọi 'bạn'"><?= $v('brand_voice') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Đối tượng độc giả</label><textarea name="target_audience" class="form-control" rows="2"><?= $v('target_audience') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Từ khóa chính của website</label><textarea name="main_keywords" class="form-control" rows="2" placeholder="Mỗi từ khóa cách nhau dấu phẩy"><?= $v('main_keywords') ?></textarea></div>
                        <div class="col-12"><label class="form-label">Quy tắc nội dung bắt buộc</label><textarea name="content_rules" class="form-control" rows="4" placeholder="vd: Không nhắc tên đối thủ; cuối bài có CTA gọi hotline 0909xxx; không cam kết hiệu quả 100%..."><?= $v('content_rules') ?></textarea></div>
                        <div class="col-md-6"><label class="form-label">Số từ mặc định mỗi bài</label><input type="number" name="word_count" class="form-control" value="<?= $v('word_count', 1500) ?>" min="300" max="6000" step="100"></div>
                        <div class="col-md-6"><label class="form-label">Kiểm tra & tối ưu lại bài cũ sau (ngày)</label><input type="number" name="reoptimize_days" class="form-control" value="<?= $v('reoptimize_days', 30) ?>" min="7" max="365"></div>
                        <div class="col-md-6"><label class="form-label">Số ảnh trong thân bài</label><input type="number" name="images_per_article" class="form-control" value="<?= $v('images_per_article', 2) ?>" min="0" max="6"><div class="form-text">Chưa tính ảnh đại diện (luôn có).</div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-wordpress"></i> Kết nối WordPress</strong></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">URL website WordPress</label><input name="wp_url" class="form-control" value="<?= $v('wp_url') ?>" placeholder="https://example.com"></div>
                    <div class="mb-3"><label class="form-label">Username (tài khoản quản trị/biên tập)</label><input name="wp_username" class="form-control" value="<?= $v('wp_username') ?>" autocomplete="off"></div>
                    <div class="mb-2">
                        <label class="form-label">Application Password</label>
                        <input type="password" name="wp_app_password" class="form-control" autocomplete="new-password"
                               placeholder="<?= !empty($p['wp_app_password_enc']) ? '•••••••• (đã lưu, để trống nếu không đổi)' : 'xxxx xxxx xxxx xxxx xxxx xxxx' ?>">
                        <div class="form-text">Tạo tại WP Admin → Người dùng → Hồ sơ → <b>Application Passwords</b>. Không dùng mật khẩu đăng nhập thật.</div>
                    </div>
                    <?php if ($isEdit): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-wp-test="<?= url('/projects/' . $p['id'] . '/wp-test') ?>"><i class="bi bi-plug"></i> Kiểm tra kết nối</button>
                        <div class="small mt-2" id="wp-test-result"></div>
                    <?php endif; ?>
                    <hr>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Khi đăng bài</label>
                            <select name="wp_default_status" class="form-select">
                                <?php foreach (['draft' => 'Lưu nháp', 'pending' => 'Chờ xét duyệt', 'publish' => 'Đăng ngay'] as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= ($p['wp_default_status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Chuyên mục mặc định</label>
                            <select name="wp_default_category_id" class="form-select" data-wp-categories="<?= $isEdit && $p['wp_url'] ? url('/projects/' . $p['id'] . '/wp-categories') : '' ?>" data-selected="<?= (int)($p['wp_default_category_id'] ?? 0) ?>">
                                <option value="">— Không chọn —</option>
                                <?php if (!empty($p['wp_default_category_id'])): ?><option value="<?= (int)$p['wp_default_category_id'] ?>" selected>#<?= (int)$p['wp_default_category_id'] ?></option><?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="auto_publish" value="1" id="auto_publish" <?= !empty($p['auto_publish']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="auto_publish">Tự động đẩy lên WordPress khi bài được duyệt hình xong (<b>Sẵn sàng đăng</b>)</label>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white"><strong><i class="bi bi-diagram-3"></i> Sitemap</strong></div>
                <div class="card-body">
                    <label class="form-label">Link sitemap.xml</label>
                    <input name="sitemap_url" class="form-control" value="<?= $v('sitemap_url') ?>" placeholder="https://example.com/sitemap_index.xml">
                    <div class="form-text">Dùng để lấy danh sách link nội bộ (tab Interlink).</div>
                </div>
            </div>
        </div>
    </div>
    <div class="sticky-actions">
        <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $isEdit ? 'Lưu cài đặt' : 'Tạo dự án' ?></button>
        <a href="<?= url($isEdit ? '/projects/' . $p['id'] : '/projects') ?>" class="btn btn-light">Hủy</a>
    </div>
</form>

<?php if ($isEdit): ?>
    <div class="row g-4 mt-1">
        <?php if (\App\Auth::is('leader')): ?>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header bg-white"><strong><i class="bi bi-people"></i> Thành viên dự án</strong> <small class="text-muted">(Admin / TP)</small></div>
                    <div class="card-body">
                        <form method="post" action="<?= url('/projects/' . $p['id'] . '/members') ?>">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label">Chủ dự án</label>
                                <select name="owner_id" class="form-select">
                                    <?php foreach ($allUsers as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= (int)$u['id'] === (int)$p['owner_id'] ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <label class="form-label">Chia sẻ thêm cho <small class="text-muted">(Content / Design tự thấy dự án khi được giao bài)</small></label>
                            <?php $memberIds = array_map(fn($m) => (int)$m['id'], $members); ?>
                            <div class="member-list">
                                <?php foreach ($allUsers as $u): if ((int)$u['id'] === (int)$p['owner_id']) continue; ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="members[]" value="<?= $u['id'] ?>" id="m<?= $u['id'] ?>" <?= in_array((int)$u['id'], $memberIds, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="m<?= $u['id'] ?>"><?= e($u['name']) ?> <small class="text-muted"><?= e(role_label($u['role'])) ?> · <?= e($u['email']) ?></small></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-outline-primary btn-sm mt-3">Lưu thành viên</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="col-lg-5">
            <div class="card border-danger">
                <div class="card-header bg-white text-danger"><strong><i class="bi bi-trash"></i> Xóa dự án</strong></div>
                <div class="card-body">
                    <p class="small text-muted">Xóa toàn bộ bài viết, ảnh và link nội bộ của dự án trong tool (không ảnh hưởng bài đã đăng trên WordPress).</p>
                    <form method="post" action="<?= url('/projects/' . $p['id'] . '/delete') ?>">
                        <?= csrf_field() ?>
                        <input name="confirm_name" class="form-control form-control-sm mb-2" placeholder="Nhập đúng tên dự án để xác nhận">
                        <button class="btn btn-outline-danger btn-sm">Xóa vĩnh viễn</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
