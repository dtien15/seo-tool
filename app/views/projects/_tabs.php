<?php
$pid = $project['id'];
$isWorker = in_array(\App\Auth::role(), ['content', 'design'], true);
$tabs = $isWorker
    ? ['articles' => ["/projects/$pid", 'bi-file-text', 'Bài được giao']]
    : [
        'overview' => ["/projects/$pid/overview", 'bi-signpost-split', 'Tổng quan'],
        'research' => ["/projects/$pid/research", 'bi-search', 'Nghiên cứu'],
        'competitors' => ["/projects/$pid/competitors", 'bi-people', 'Đối thủ'],
        'website' => ["/projects/$pid/website", 'bi-clipboard-check', 'Website'],
        'keywords' => ["/projects/$pid/keywords", 'bi-key', 'Từ khóa'],
        'kpi' => ["/projects/$pid/kpi", 'bi-bullseye', 'KPI'],
        'articles' => ["/projects/$pid", 'bi-file-text', 'Plan content'],
        'links' => ["/projects/$pid/links", 'bi-link-45deg', 'Nội dung web'],
        'sheet' => ["/projects/$pid/sheet", 'bi-table', 'Google Sheet'],
        'settings' => ["/projects/$pid/settings", 'bi-sliders', 'Cài đặt'],
    ];
?>
<div class="mb-3">
    <div class="small text-muted"><a href="<?= url('/projects') ?>" class="text-muted">Dự án</a> /</div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h1 class="h4 mb-0"><?= e($project['name']) ?></h1>
        <?php if ($project['domain']): ?><span class="text-muted small"><?= e($project['domain']) ?></span><?php endif; ?>
    </div>
</div>
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto project-tabs">
    <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
        <li class="nav-item">
            <a class="nav-link text-nowrap <?= ($tab ?? '') === $key ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
