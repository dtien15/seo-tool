<?php if (($pg['pages'] ?? 1) > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <small class="text-muted">Tổng <?= (int)$pg['total'] ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php
            $start = max(1, $pg['page'] - 3);
            $end = min($pg['pages'], $pg['page'] + 3);
            ?>
            <?php if ($pg['page'] > 1): ?><li class="page-item"><a class="page-link" href="<?= e(query_with(['page' => $pg['page'] - 1])) ?>">‹</a></li><?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?><li class="page-item"><a class="page-link" href="<?= e(query_with(['page' => $pg['page'] + 1])) ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
<?php endif; ?>
