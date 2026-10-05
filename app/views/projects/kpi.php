<?php
use App\Controllers\ResearchController as R;

require __DIR__ . '/_tabs.php';
$fmt = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
?>
<p class="text-muted small">Lên KPI theo tháng. Số bài đăng thực tế được tính tự động từ các bài đã đăng; các chỉ số khác nhập từ Google Search Console / Analytics / Ahrefs.</p>

<form method="post" action="<?= url('/projects/' . $project['id'] . '/kpi') ?>">
    <?= csrf_field() ?>
    <?php if ($canEdit): ?>
        <div class="card card-body mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto"><label class="form-label small mb-0">Thêm KPI từ tháng</label><input type="month" name="from" class="form-control form-control-sm" value="<?= date('Y-m') ?>"></div>
                <div class="col-auto"><label class="form-label small mb-0">Số tháng</label><input type="number" name="months" class="form-control form-control-sm" value="<?= $grid ? 0 : 6 ?>" min="0" max="24" style="width:90px"></div>
                <div class="col-auto"><button class="btn btn-sm btn-outline-primary">Thêm tháng</button></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$grid): ?>
        <div class="card card-body text-muted">Chưa có KPI. Chọn tháng bắt đầu và số tháng rồi bấm "Thêm tháng".</div>
    <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 kpi-table">
                    <thead class="table-light">
                    <tr>
                        <th>Chỉ tiêu</th>
                        <?php foreach (array_keys($grid) as $period): ?>
                            <th class="text-center" colspan="2"><?= e(date('m/Y', strtotime($period . '-01'))) ?>
                                <?php if ($canEdit): ?><button name="delete_period" value="<?= e($period) ?>" class="btn btn-link btn-sm p-0 text-danger" title="Xóa tháng" data-confirm="Xóa KPI tháng này?"><i class="bi bi-x"></i></button><?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="small text-muted">
                        <th></th>
                        <?php foreach ($grid as $period => $_): ?><th class="text-center">Mục tiêu</th><th class="text-center">Thực tế</th><?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (R::KPI_METRICS as $metric => $label): ?>
                        <tr>
                            <td class="text-nowrap small fw-medium"><?= e($label) ?></td>
                            <?php foreach ($grid as $period => $row): $k = $row[$metric] ?? null; ?>
                                <?php if (!$k): ?><td></td><td></td><?php continue; endif; ?>
                                <?php
                                $actual = $metric === 'articles' ? ($published[$period] ?? 0) : $k['actual'];
                                $hit = $k['target'] !== null && $actual !== null && (float)$actual >= (float)$k['target'];
                                ?>
                                <td><input name="kpi[<?= $k['id'] ?>][target]" class="form-control form-control-sm text-end" value="<?= e($fmt($k['target'])) ?>" <?= $canEdit ? '' : 'readonly' ?>></td>
                                <td>
                                    <?php if ($metric === 'articles'): ?>
                                        <div class="text-end small fw-semibold <?= $hit ? 'text-success' : '' ?>" title="Tự tính từ bài đã đăng"><?= (int)$actual ?></div>
                                    <?php else: ?>
                                        <input name="kpi[<?= $k['id'] ?>][actual]" class="form-control form-control-sm text-end <?= $hit ? 'is-valid' : '' ?>" value="<?= e($fmt($k['actual'])) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canEdit): ?><div class="card-footer bg-white"><button class="btn btn-primary btn-sm"><i class="bi bi-save"></i> Lưu KPI</button></div><?php endif; ?>
        </div>
    <?php endif; ?>
</form>
