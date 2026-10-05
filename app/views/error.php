<div class="text-center py-5">
    <div class="display-4 fw-bold text-muted"><?= (int)$code ?></div>
    <h1 class="h4 mt-2"><?= e($title) ?></h1>
    <?php if ($message): ?><p class="text-muted"><?= e($message) ?></p><?php endif; ?>
    <a href="<?= url('/') ?>" class="btn btn-primary mt-2"><i class="bi bi-house"></i> Về trang chủ</a>
</div>
