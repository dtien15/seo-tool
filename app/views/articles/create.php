<?php require BASE_PATH . '/app/views/projects/_tabs.php'; ?>

<div class="card" style="max-width:900px">
    <div class="card-header bg-white">
        <ul class="nav nav-pills card-header-pills" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#single" type="button">Một bài</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#bulk" type="button">Nhiều bài (danh sách từ khóa)</button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <?php
        $common = function () use ($assignees, $writers, $designers, $project) { ?>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label">SEO phụ trách</label>
                    <select name="assigned_to" class="form-select">
                        <?php foreach ($assignees as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (int)$u['id'] === (int)\App\Auth::id() ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Content viết bài</label>
                    <select name="writer_id" class="form-select">
                        <option value="">— Giao sau —</option>
                        <?php foreach ($writers as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Design làm hình</label>
                    <select name="designer_id" class="form-select">
                        <option value="">— Giao sau —</option>
                        <?php foreach ($designers as $u): ?><option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Nhóm chủ đề</label><input name="cluster" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Ngày dự kiến đăng</label><input type="date" name="planned_date" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Số từ</label><input type="number" name="word_count" class="form-control" placeholder="<?= (int)$project['word_count'] ?>" min="300" max="6000" step="100"></div>
                <div class="col-md-3">
                    <label class="form-label">Sau khi thêm</label>
                    <select name="then" class="form-select">
                        <option value="">Để ở Kế hoạch</option>
                        <option value="outline">AI tạo outline luôn</option>
                    </select>
                    <div class="mt-1"><?= ai_select() ?></div>
                </div>
            </div>
        <?php }; ?>

        <div class="tab-pane fade show active" id="single">
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="single">
                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="type" value="new" id="type-new" checked>
                        <label class="btn btn-outline-secondary btn-sm" for="type-new">Bài mới</label>
                        <input type="radio" class="btn-check" name="type" value="optimize" id="type-opt">
                        <label class="btn btn-outline-secondary btn-sm" for="type-opt">Tối ưu lại bài đang có trên web</label>
                    </div>
                </div>
                <div class="mb-3 d-none" data-show-when="optimize"><label class="form-label">Link bài hiện có *</label><input name="wp_url" class="form-control" placeholder="https://..."></div>
                <div class="mb-3"><label class="form-label">Từ khóa chính *</label><input name="keyword" class="form-control" required autofocus></div>
                <div class="mb-3"><label class="form-label">Từ khóa phụ / LSI</label><input name="secondary_keywords" class="form-control" placeholder="Cách nhau dấu phẩy"></div>
                <div class="mb-3"><label class="form-label">Tiêu đề mong muốn</label><input name="title" class="form-control" placeholder="Để trống để AI tự đặt"></div>
                <div class="mb-1"><label class="form-label">Ghi chú / brief</label><textarea name="notes" class="form-control" rows="3" placeholder="Ý cần nhấn mạnh, đối thủ tham khảo, sản phẩm cần nhắc tới..."></textarea></div>
                <?php $common(); ?>
                <button class="btn btn-primary mt-4"><i class="bi bi-plus-lg"></i> Thêm vào kế hoạch</button>
            </form>
        </div>

        <div class="tab-pane fade" id="bulk">
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="bulk">
                <label class="form-label">Danh sách từ khóa</label>
                <textarea name="lines" class="form-control font-monospace" rows="10" required placeholder="niềng răng trong suốt giá bao nhiêu | invisalign, niềng răng clear | nhấn mạnh trả góp 0%&#10;bọc răng sứ có đau không&#10;..."></textarea>
                <div class="form-text">Mỗi dòng một bài: <code>từ khóa chính | từ khóa phụ | ghi chú</code>.</div>
                <?php $common(); ?>
                <button class="btn btn-primary mt-4"><i class="bi bi-plus-lg"></i> Thêm tất cả vào kế hoạch</button>
            </form>
        </div>
    </div>
</div>
