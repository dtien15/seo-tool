<?php
use App\Controllers\ResearchController as R;

require __DIR__ . '/_tabs.php';
$plan = $sections['build_plan'] ?? null;
?>
<form method="post" action="<?= url('/projects/' . $project['id'] . '/website') ?>">
    <?= csrf_field() ?>
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap gap-4 align-items-center">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" name="has_website" value="1" id="has_website" <?= $project['has_website'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?> onchange="this.form.submit()">
                <label class="form-check-label fw-medium" for="has_website">Dự án đã có website</label>
            </div>
            <span class="small text-muted"><?= $project['has_website'] ? 'Kiểm tra website → website ổn thì tối ưu lại, không ổn thì đề xuất làm website mới.' : 'Chưa có website → lên kế hoạch build website và KPI website.' ?></span>
        </div>
    </div>

<?php if ($project['has_website']): ?>
    <div class="row g-4">
        <div class="col-xl-8">
            <?php foreach (R::AUDIT_CATEGORIES as $cat => $catLabel): ?>
                <div class="card mb-3">
                    <div class="card-header bg-white"><strong>Tối ưu <?= e($catLabel) ?></strong></div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($items[$cat] ?? [] as $it): ?>
                            <div class="list-group-item">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-6 small"><?= e($it['item']) ?></div>
                                    <div class="col-md-2">
                                        <select name="audit[<?= $it['id'] ?>][status]" class="form-select form-select-sm audit-status" <?= $canEdit ? '' : 'disabled' ?>>
                                            <?php foreach (R::AUDIT_STATUS as $k => [$label]): ?>
                                                <option value="<?= $k ?>" <?= $it['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4"><input name="audit[<?= $it['id'] ?>][note]" class="form-control form-control-sm" value="<?= e($it['note']) ?>" placeholder="Ghi chú / đề xuất" <?= $canEdit ? '' : 'readonly' ?>></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($canEdit): ?>
                <div class="card card-body mb-3">
                    <div class="row g-2">
                        <div class="col-md-3"><select name="new_category" class="form-select form-select-sm"><?php foreach (R::AUDIT_CATEGORIES as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-9"><input name="new_item" class="form-control form-control-sm" placeholder="Thêm mục kiểm tra khác..."></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="col-xl-4">
            <div class="card sticky-xl-top" style="top:1rem">
                <div class="card-header bg-white"><strong>Kết luận</strong></div>
                <div class="card-body">
                    <?php foreach (R::VERDICTS as $k => [$label, $color]): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="website_verdict" value="<?= $k ?>" id="v-<?= $k ?>" <?= $project['website_verdict'] === $k ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="form-check-label text-<?= $color ?>" for="v-<?= $k ?>"><?= e($label) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <label class="form-label mt-2">Phương án đề xuất</label>
                    <textarea name="website_proposal" class="form-control" rows="8" placeholder="Các việc cần tối ưu (technical, content, giao diện), mô tả sản phẩm cần viết lại, nhóm bài blog cần gộp... hoặc lý do đề xuất làm web mới." <?= $canEdit ? '' : 'readonly' ?>><?= e($project['website_proposal']) ?></textarea>
                    <?php if ($canEdit): ?><button class="btn btn-primary w-100 mt-3"><i class="bi bi-save"></i> Lưu kết quả kiểm tra</button><?php endif; ?>
                    <p class="small text-muted mt-3 mb-0">Rà soát từng trang sản phẩm / bài blog cũ ở tab <a href="<?= url('/projects/' . $project['id'] . '/links') ?>">Nội dung web</a>.</p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</form>

<?php if (!$project['has_website']): ?>
    <form method="post" action="<?= url('/projects/' . $project['id'] . '/research') ?>" class="card" id="build_plan">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="build_plan">
        <div class="card-header bg-white"><strong>Kế hoạch build website</strong> <?php if (!empty($plan['is_done'])): ?><span class="badge text-bg-success">Hoàn thành</span><?php endif; ?></div>
        <div class="card-body">
            <textarea name="content" class="form-control" rows="14" placeholder="Nền tảng, domain, cấu trúc trang (sitemap dự kiến theo bộ từ khóa), trang dịch vụ/sản phẩm, blog, yêu cầu technical SEO, timeline, người phụ trách..." <?= $canEdit ? '' : 'readonly' ?>><?= e($plan['content'] ?? '') ?></textarea>
        </div>
        <?php if ($canEdit): ?>
            <div class="card-footer bg-white d-flex align-items-center gap-3">
                <label class="small"><input type="checkbox" name="is_done" value="1" <?= !empty($plan['is_done']) ? 'checked' : '' ?>> Hoàn thành</label>
                <button class="btn btn-sm btn-primary ms-auto">Lưu</button>
            </div>
        <?php endif; ?>
    </form>
    <p class="small text-muted mt-2">Sau khi có kế hoạch: lên KPI website ở tab <a href="<?= url('/projects/' . $project['id'] . '/kpi') ?>">KPI</a> và lên luôn plan content ở tab <a href="<?= url('/projects/' . $project['id'] . '/keywords') ?>">Từ khóa</a>.</p>
<?php endif; ?>
