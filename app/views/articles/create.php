<?php require BASE_PATH . '/app/views/projects/_tabs.php'; ?>

<div class="card" style="max-width:860px">
    <div class="card-header bg-white">
        <ul class="nav nav-pills card-header-pills" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#single" type="button">Một bài</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#bulk" type="button">Nhiều bài (danh sách từ khóa)</button></li>
        </ul>
    </div>
    <div class="card-body tab-content">
        <?php
        $common = function () use ($assignees, $project) { ?>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label">Giao cho</label>
                    <select name="assigned_to" class="form-select">
                        <?php foreach ($assignees as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (int)$u['id'] === (int)\App\Auth::id() ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Số từ</label>
                    <input type="number" name="word_count" class="form-control" placeholder="<?= (int)$project['word_count'] ?>" min="300" max="6000" step="100">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Sau khi thêm</label>
                    <select name="then" class="form-select">
                        <option value="">Chỉ lưu (Ý tưởng)</option>
                        <option value="outline">Tạo outline bằng AI</option>
                        <option value="write">Viết bài luôn bằng AI</option>
                    </select>
                </div>
            </div>
        <?php }; ?>

        <div class="tab-pane fade show active" id="single">
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="single">
                <div class="mb-3"><label class="form-label">Từ khóa chính *</label><input name="keyword" class="form-control" required autofocus></div>
                <div class="mb-3"><label class="form-label">Từ khóa phụ / LSI</label><input name="secondary_keywords" class="form-control" placeholder="Cách nhau dấu phẩy"></div>
                <div class="mb-3"><label class="form-label">Tiêu đề mong muốn</label><input name="title" class="form-control" placeholder="Để trống để AI tự đặt"></div>
                <div class="mb-1"><label class="form-label">Ghi chú cho AI</label><textarea name="notes" class="form-control" rows="3" placeholder="Ý cần nhấn mạnh, đối thủ tham khảo, sản phẩm cần nhắc tới..."></textarea></div>
                <?php $common(); ?>
                <button class="btn btn-primary mt-4"><i class="bi bi-plus-lg"></i> Thêm bài viết</button>
            </form>
        </div>

        <div class="tab-pane fade" id="bulk">
            <form method="post" action="<?= url('/projects/' . $project['id'] . '/articles') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="mode" value="bulk">
                <label class="form-label">Danh sách từ khóa</label>
                <textarea name="lines" class="form-control font-monospace" rows="10" required placeholder="niềng răng trong suốt giá bao nhiêu | invisalign, niềng răng clear | nhấn mạnh trả góp 0%&#10;bọc răng sứ có đau không&#10;..."></textarea>
                <div class="form-text">Mỗi dòng một bài: <code>từ khóa chính | từ khóa phụ | ghi chú</code> (phần sau dấu | không bắt buộc).</div>
                <?php $common(); ?>
                <button class="btn btn-primary mt-4"><i class="bi bi-plus-lg"></i> Thêm tất cả</button>
            </form>
        </div>
    </div>
</div>
