<?php
$tabs = [
    'articles' => ['/projects/' . $project['id'], 'bi-file-text', 'Bài viết'],
    'links' => ['/projects/' . $project['id'] . '/links', 'bi-link-45deg', 'Interlink'],
    'sheet' => ['/projects/' . $project['id'] . '/sheet', 'bi-table', 'Google Sheet'],
    'settings' => ['/projects/' . $project['id'] . '/settings', 'bi-sliders', 'Cài đặt dự án'],
];
?>
<div class="mb-3">
    <div class="small text-muted"><a href="<?= url('/projects') ?>" class="text-muted">Dự án</a> /</div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <h1 class="h4 mb-0"><?= e($project['name']) ?></h1>
        <?php if ($project['domain']): ?><span class="text-muted small"><?= e($project['domain']) ?></span><?php endif; ?>
    </div>
</div>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => [$href, $icon, $label]): ?>
        <li class="nav-item">
            <a class="nav-link <?= ($tab ?? '') === $key ? 'active' : '' ?>" href="<?= url($href) ?>"><i class="bi <?= $icon ?>"></i> <?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
